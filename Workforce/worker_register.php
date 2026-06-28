<?php
/**
 * طبقة القوى التشغيلية (EQUIP-OPE-S04) — 8.1 سجل العامل التشغيلي (+ 8.2 المهارات/الرخص).
 *
 * موحَّد: employees هو المصدر الوحيد للعامل (is_workforce=1) ويكتب في worker_qualification.
 * كل الكتابة Prepared Statements. عزل الشركة كالقائم.
 * الهوية والتصميم موحّدان عبر inheader/insidebar/page_header وأصول assets القائمة.
 */
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Workforce/WorkerCategory.php';
require_once __DIR__ . '/../app/Services/Workforce/AccreditationService.php';
require_once __DIR__ . '/../app/Services/Workforce/ViewModal.php';

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id        = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

// ── الصلاحيات (نمط النظام القائم) ─────────────────────────────────────────────
$page_permissions = check_page_permissions($conn, 'Workforce/worker_register.php');
$can_view   = $page_permissions['can_view'];
$can_add    = $page_permissions['can_add'];
$can_edit   = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];
if (!$can_view) {
    header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+سجل+العامل+❌");
    exit();
}

$company_scope_sql = $is_super_admin ? "" : " AND wp.company_id = " . intval($company_id) . " ";
$emp_company_sql   = $is_super_admin ? "" : " AND e.company_id = " . intval($company_id) . " ";

// ════════════════════════════════════════════════════════════════════════════
// معالجة الإرسال (قبل أي إخراج HTML)
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // ── حفظ/تعديل العامل (تصنيف موظفٍ قائم) ─────────────────────────────────
    if ($action === 'save_worker') {
        $id          = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $is_editing  = $id > 0;
        if ($is_editing && !$can_edit) { header("Location: worker_register.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
        if (!$is_editing && !$can_add)  { header("Location: worker_register.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }

        $employee_id      = intval($_POST['employee_id'] ?? 0);
        $code             = trim($_POST['code'] ?? '');
        $worker_category  = trim($_POST['worker_category'] ?? '');
        $source_type      = trim($_POST['source_type'] ?? 'شركة');
        $workforce_class  = trim($_POST['workforce_class'] ?? 'أساسي');
        $job_grade        = trim($_POST['job_grade'] ?? '');
        $state            = trim($_POST['state'] ?? 'مسجّل');
        $fitness          = trim($_POST['medical_fitness_status'] ?? '');
        $fitness_cond     = trim($_POST['fitness_conditions'] ?? '');
        $primary_backup   = !empty($_POST['primary_backup_id']) ? intval($_POST['primary_backup_id']) : null;
        $is_replaceable   = isset($_POST['is_replaceable']) ? 1 : 0;
        $supplier_id      = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;
        $notes            = trim($_POST['notes'] ?? '');

        // تحقق: الفئة ضمن المعتمدة، والحالة ضمن آلة الحالة
        if (!in_array($worker_category, ems_worker_categories(), true)) { $worker_category = 'مشغّل/سائق'; }

        $scope = $is_super_admin ? "" : " AND company_id = " . intval($company_id);
        $fit = $fitness !== '' ? $fitness : null;
        $jg  = $job_grade !== '' ? $job_grade : null;
        $fc  = $fitness_cond !== '' ? $fitness_cond : null;

        if (!$is_editing) {
            // الموظف هو العامل: التصنيف = رفع علم is_workforce على سجل الموظف القائم
            if ($employee_id <= 0) { header("Location: worker_register.php?msg=يجب+اختيار+موظف+❌"); exit(); }
            $chk = $conn->prepare("SELECT id FROM employees WHERE id = ? AND is_workforce = 1 LIMIT 1");
            $chk->bind_param('i', $employee_id);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) { $chk->close(); header("Location: worker_register.php?msg=الموظف+مصنّفٌ+عاملاً+مسبقاً+❌"); exit(); }
            $chk->close();
            $target_id = $employee_id;
        } else {
            $target_id = $id;
        }

        // التصنيف/التعديل = تحديث أعمدة القوى العاملة مباشرةً على سجل الموظف (employees هو المصدر الوحيد)
        $sql = "UPDATE employees SET
                    is_workforce = 1, worker_code = ?, worker_category = ?, source_type = ?, workforce_class = ?,
                    job_grade = ?, workforce_state = ?, medical_fitness_status = ?, fitness_conditions = ?,
                    primary_backup_id = ?, is_replaceable = ?, supplier_id = COALESCE(?, supplier_id),
                    general_notes = ?
                WHERE id = ? $scope";
        $stmt = $conn->prepare($sql);
        // أنواع: code,cat,source,class,grade,state,fitness,cond (8×s) backup(i) replaceable(i) supplier(i) notes(s) id(i)
        $stmt->bind_param(
            'ssssssssiiisi',
            $code, $worker_category, $source_type, $workforce_class, $jg, $state, $fit, $fc,
            $primary_backup, $is_replaceable, $supplier_id, $notes, $target_id
        );
        $ok = $stmt->execute();
        $stmt->close();
        if ($is_editing) {
            header("Location: worker_register.php?edit=" . $target_id . "&msg=" . ($ok ? "✅+تم+تحديث+بيانات+العامل" : "❌+تعذّر+التحديث"));
        } else {
            header("Location: worker_register.php?msg=" . ($ok ? "✅+تم+تصنيف+الموظف+عاملاً+تشغيلياً" : "❌+تعذّر+الحفظ"));
        }
        exit();
    }

    // ── إضافة اعتماد/مهارة/رخصة/ترقية (8.2) ─────────────────────────────────
    if ($action === 'save_qualification' && $can_edit) {
        $worker_id   = intval($_POST['worker_id'] ?? 0);
        $record_type = trim($_POST['record_type'] ?? 'رخصة');
        $title       = trim($_POST['title'] ?? '');
        $issuer      = trim($_POST['issuer'] ?? '');
        $equip_type  = trim($_POST['equipment_type'] ?? '');
        $issue_date  = !empty($_POST['issue_date']) ? $_POST['issue_date'] : null;
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $acc_cat     = trim($_POST['accreditation_category'] ?? '');
        $prof        = trim($_POST['proficiency_level'] ?? '');
        $is_critical = isset($_POST['is_critical']) ? 1 : 0;
        $alert_days  = intval($_POST['alert_lead_days'] ?? 30);
        $decision    = trim($_POST['decision_ref'] ?? '');

        if ($worker_id > 0 && $title !== '') {
            $cid = $is_super_admin ? null : $company_id;
            $ac  = $acc_cat !== '' ? $acc_cat : null;
            $pf  = $prof !== '' ? $prof : null;
            $eq  = $equip_type !== '' ? $equip_type : null;
            $iss = $issuer !== '' ? $issuer : null;
            $dec = $decision !== '' ? $decision : null;
            $sql = "INSERT INTO worker_qualification
                    (company_id, employee_id, record_type, title, issuer, equipment_type, issue_date, expiry_date,
                     accreditation_category, proficiency_level, is_critical, alert_lead_days, decision_ref, created_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                'iissssssssiisi',
                $cid, $worker_id, $record_type, $title, $iss, $eq, $issue_date, $expiry_date,
                $ac, $pf, $is_critical, $alert_days, $dec, $user_id
            );
            $stmt->execute();
            $stmt->close();
        }
        header("Location: worker_register.php?edit=" . $worker_id . "&tab=quals&msg=✅+تم+حفظ+الاعتماد");
        exit();
    }

    // ── حذف اعتماد ───────────────────────────────────────────────────────────
    if ($action === 'delete_qualification' && $can_delete) {
        $qid       = intval($_POST['qid'] ?? 0);
        $worker_id = intval($_POST['worker_id'] ?? 0);
        $scope = $is_super_admin ? "" : " AND company_id = " . intval($company_id);
        $stmt = $conn->prepare("DELETE FROM worker_qualification WHERE id = ? $scope");
        $stmt->bind_param('i', $qid);
        $stmt->execute();
        $stmt->close();
        header("Location: worker_register.php?edit=" . $worker_id . "&tab=quals&msg=✅+تم+حذف+الاعتماد");
        exit();
    }
}

// ── تحميل عاملٍ للتعديل (GET) ─────────────────────────────────────────────────
$edit_worker = null;
$edit_quals  = [];
$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
if ($edit_id > 0) {
    $scope = $is_super_admin ? "" : " AND e.company_id = " . intval($company_id);
    $stmt = $conn->prepare(
        "SELECT e.*, e.name AS employee_name, e.employee_code AS emp_code,
                e.worker_code AS code, e.workforce_state AS state, e.general_notes AS notes
         FROM employees e
         WHERE e.id = ? AND e.is_workforce = 1 $scope LIMIT 1"
    );
    $stmt->bind_param('i', $edit_id);
    $stmt->execute();
    $edit_worker = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($edit_worker) {
        $edit_quals = ems_worker_accreditations($conn, $edit_id);
    }
}

$page_title = "إيكوبيشن | سجل العامل التشغيلي";
include '../inheader.php';
include '../insidebar.php';
?>

<div class="main equipments-fleet-main drivers-main">
    <?php
    $header_title   = 'سجل العامل التشغيلي';
    $header_icon    = 'fas fa-people-group';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'تصنيف عامل تشغيلي');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <?php
    // قائمة العمال المتاحين كبدائل (نفس الشركة)
    $backup_options = [];
    $bq = mysqli_query($conn, "SELECT wp.id, wp.name AS name FROM employees wp WHERE wp.is_workforce = 1 $company_scope_sql ORDER BY wp.name");
    if ($bq) { while ($b = mysqli_fetch_assoc($bq)) { $backup_options[$b['id']] = $b['name']; } }

    // قائمة الموردين
    $supplier_options = [];
    $sq = mysqli_query($conn, "SELECT id, name FROM suppliers WHERE 1=1" . ($is_super_admin ? "" : " AND company_id = " . intval($company_id)) . " ORDER BY name");
    if ($sq) { while ($s = mysqli_fetch_assoc($sq)) { $supplier_options[$s['id']] = $s['name']; } }

    // موظفون غير مصنّفين بعد (للإضافة) — مع نوعهم للاقتراح الآلي للفئة
    $unclassified = [];
    if ($can_add && !$edit_worker) {
        $uq = mysqli_query($conn, "SELECT e.id, e.name, e.employee_type FROM employees e
                                   WHERE COALESCE(e.is_workforce,0) = 0 $emp_company_sql ORDER BY e.name");
        if ($uq) { while ($u = mysqli_fetch_assoc($uq)) { $unclassified[] = $u; } }
    }
    $show_form = ($edit_worker !== null) || !empty($_GET['add']);
    ?>

    <!-- ═══ فورم تصنيف/تعديل العامل ═══ -->
    <form id="workerForm" action="" method="post" class="allforms" style="<?= $show_form ? '' : 'display:none;' ?>">
        <input type="hidden" name="action" value="save_worker">
        <input type="hidden" name="id" value="<?= $edit_worker ? intval($edit_worker['id']) : 0 ?>">
        <div class="card-header">
            <h5><i class="fas fa-edit"></i> <?= $edit_worker ? 'تعديل بيانات العامل' : 'تصنيف موظفٍ عاملاً تشغيلياً' ?></h5>
        </div>

        <div class="form-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;padding:14px;">
            <div class="field">
                <label><i class="fas fa-user"></i> الموظف</label>
                <?php if ($edit_worker): ?>
                    <input type="text" value="<?= htmlspecialchars($edit_worker['employee_name'] ?? '-') ?>" disabled>
                <?php else: ?>
                    <select name="employee_id" id="employee_id" required onchange="emsSuggestCategory()">
                        <option value="">— اختر موظفاً غير مصنّف —</option>
                        <?php foreach ($unclassified as $u): ?>
                            <option value="<?= intval($u['id']) ?>" data-emptype="<?= htmlspecialchars($u['employee_type'] ?? '') ?>">
                                <?= htmlspecialchars($u['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div class="field">
                <label><i class="fas fa-barcode"></i> كود العامل (يدوي)</label>
                <input type="text" name="code" value="<?= htmlspecialchars($edit_worker['code'] ?? '') ?>" placeholder="اختياري">
            </div>

            <div class="field">
                <label><i class="fas fa-layer-group"></i> الفئة التشغيلية</label>
                <select name="worker_category" id="worker_category" required>
                    <?php foreach (ems_worker_categories() as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= (($edit_worker['worker_category'] ?? '') === $cat) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label><i class="fas fa-sitemap"></i> مصدر التبعية</label>
                <select name="source_type" id="source_type" onchange="emsToggleSupplier()">
                    <?php foreach (['شركة','مورد','مقاول'] as $st): ?>
                        <option value="<?= $st ?>" <?= (($edit_worker['source_type'] ?? 'شركة') === $st) ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field" id="supplierField" style="<?= (($edit_worker['source_type'] ?? 'شركة') === 'شركة') ? 'display:none;' : '' ?>">
                <label><i class="fas fa-truck"></i> المورد/المقاول</label>
                <select name="supplier_id">
                    <option value="">—</option>
                    <?php foreach ($supplier_options as $sid => $sname): ?>
                        <option value="<?= intval($sid) ?>" <?= (intval($edit_worker['supplier_id'] ?? 0) === intval($sid)) ? 'selected' : '' ?>><?= htmlspecialchars($sname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label><i class="fas fa-shield-halved"></i> موقع القوة</label>
                <select name="workforce_class">
                    <?php foreach (['أساسي','احتياطي','بديل مؤقت','تغطية إجازة','تجاري مؤقت'] as $wc): ?>
                        <option value="<?= $wc ?>" <?= (($edit_worker['workforce_class'] ?? 'أساسي') === $wc) ? 'selected' : '' ?>><?= $wc ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label><i class="fas fa-ranking-star"></i> الدرجة المهنية</label>
                <select name="job_grade">
                    <option value="">—</option>
                    <?php foreach (ems_worker_job_grades() as $g): ?>
                        <option value="<?= $g ?>" <?= (($edit_worker['job_grade'] ?? '') === $g) ? 'selected' : '' ?>><?= $g ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label><i class="fas fa-diagram-project"></i> الحالة</label>
                <select name="state">
                    <?php foreach (['مرشّح','مسجّل','مؤهّل','متعاقد','مخصّص','في إجازة','منتهٍ'] as $stt): ?>
                        <option value="<?= $stt ?>" <?= (($edit_worker['state'] ?? 'مسجّل') === $stt) ? 'selected' : '' ?>><?= $stt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label><i class="fas fa-heart-pulse"></i> اللياقة الطبية <small>(تُحدَّث من 8.2 لاحقاً)</small></label>
                <select name="medical_fitness_status">
                    <option value="">—</option>
                    <?php foreach (['لائق للعمل','لائق بشروط','موقوف طبيًّا','يحتاج إعادة تقييم'] as $mf): ?>
                        <option value="<?= $mf ?>" <?= (($edit_worker['medical_fitness_status'] ?? '') === $mf) ? 'selected' : '' ?>><?= $mf ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label><i class="fas fa-notes-medical"></i> شروط اللياقة</label>
                <input type="text" name="fitness_conditions" value="<?= htmlspecialchars($edit_worker['fitness_conditions'] ?? '') ?>" placeholder="عند «لائق بشروط»">
            </div>

            <div class="field">
                <label><i class="fas fa-user-shield"></i> البديل الأساسي</label>
                <select name="primary_backup_id">
                    <option value="">—</option>
                    <?php foreach ($backup_options as $bid => $bname):
                        if ($edit_worker && intval($bid) === intval($edit_worker['id'])) continue; ?>
                        <option value="<?= intval($bid) ?>" <?= (intval($edit_worker['primary_backup_id'] ?? 0) === intval($bid)) ? 'selected' : '' ?>><?= htmlspecialchars($bname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field" style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="is_replaceable" id="is_replaceable" value="1" <?= (!$edit_worker || intval($edit_worker['is_replaceable'] ?? 1) === 1) ? 'checked' : '' ?>>
                <label for="is_replaceable" style="margin:0;">قابل للإحلال</label>
            </div>

            <div class="field" style="grid-column:1/-1;">
                <label><i class="fas fa-align-right"></i> ملاحظات</label>
                <textarea name="notes" rows="2"><?= htmlspecialchars($edit_worker['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div style="padding:0 14px 16px;display:flex;gap:10px;">
            <button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ</button>
            <a href="worker_register.php" class="add-btn" style="background:#6b7280;"><i class="fas fa-times"></i> إلغاء</a>
        </div>
    </form>

    <?php if ($edit_worker): ?>
    <!-- ═══ 8.2 المهارات والرخص والاعتمادات ═══ -->
    <div class="allforms" id="qualsPanel">
        <div class="card-header"><h5><i class="fas fa-certificate"></i> المهارات والرخص والاعتمادات</h5></div>
        <form action="" method="post" class="form-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:14px;">
            <input type="hidden" name="action" value="save_qualification">
            <input type="hidden" name="worker_id" value="<?= intval($edit_worker['id']) ?>">
            <div class="field"><label>النوع</label>
                <select name="record_type"><?php foreach (['رخصة','مؤهل','خبرة','ترقية'] as $rt): ?><option value="<?= $rt ?>"><?= $rt ?></option><?php endforeach; ?></select>
            </div>
            <div class="field"><label>الاسم/العنوان</label><input type="text" name="title" required></div>
            <div class="field"><label>الجهة المانحة</label><input type="text" name="issuer"></div>
            <div class="field"><label>نوع المعدة</label><input type="text" name="equipment_type"></div>
            <div class="field"><label>تاريخ الإصدار</label><input type="date" name="issue_date"></div>
            <div class="field"><label>تاريخ الانتهاء</label><input type="date" name="expiry_date"></div>
            <div class="field"><label>فئة الاعتماد</label>
                <select name="accreditation_category"><option value="">—</option><?php foreach (['مهارة معدة','اعتماد فني','دورة','شهادة','سلامة','فحص طبي','اعتماد موقع','تصريح'] as $ac): ?><option value="<?= $ac ?>"><?= $ac ?></option><?php endforeach; ?></select>
            </div>
            <div class="field"><label>مستوى الكفاءة</label>
                <select name="proficiency_level"><option value="">—</option><?php foreach (['مبتدئ','متوسط','متقدم','خبير'] as $pl): ?><option value="<?= $pl ?>"><?= $pl ?></option><?php endforeach; ?></select>
            </div>
            <div class="field"><label>مدة التنبيه (يوم)</label><input type="number" name="alert_lead_days" value="30" min="0"></div>
            <div class="field" style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="is_critical" id="is_critical_q" value="1"><label for="is_critical_q" style="margin:0;">اعتماد حرج (يمنع التخصيص عند انتهائه)</label></div>
            <div class="field"><label>مرجع القرار (للترقية)</label><input type="text" name="decision_ref"></div>
            <div class="field" style="display:flex;align-items:flex-end;"><button type="submit" class="add-btn"><i class="fas fa-plus"></i> إضافة</button></div>
        </form>

        <table class="data-table" style="width:100%;margin-top:6px;">
            <thead><tr><th>النوع</th><th>العنوان</th><th>الجهة</th><th>المعدة</th><th>الإصدار</th><th>الانتهاء</th><th>الصلاحية</th><th>حرج</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($edit_quals)): ?>
                <tr><td colspan="9" style="text-align:center;color:#888;">لا توجد اعتمادات بعد</td></tr>
            <?php else: foreach ($edit_quals as $q):
                $vClass = $q['validity'] === 'منتهٍ' ? 'status-inactive' : ($q['validity'] === 'قارب الانتهاء' ? 'status-warning' : 'status-active'); ?>
                <tr>
                    <td><span class="badge badge-info"><?= htmlspecialchars($q['record_type']) ?></span></td>
                    <td><?= htmlspecialchars($q['title'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($q['issuer'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($q['equipment_type'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($q['issue_date'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($q['expiry_date'] ?: '-') ?></td>
                    <td><span class="status-pill <?= $vClass ?>"><?= htmlspecialchars($q['validity']) ?></span></td>
                    <td><?= intval($q['is_critical']) ? '⚠️' : '—' ?></td>
                    <td><?php if ($can_delete): ?>
                        <form action="" method="post" onsubmit="return confirm('حذف الاعتماد؟');" style="display:inline;">
                            <input type="hidden" name="action" value="delete_qualification">
                            <input type="hidden" name="qid" value="<?= intval($q['id']) ?>">
                            <input type="hidden" name="worker_id" value="<?= intval($edit_worker['id']) ?>">
                            <button type="submit" class="action-btn delete"><i class="fas fa-trash"></i></button>
                        </form>
                    <?php endif; ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ═══ جدول سجل العمال ═══ -->
    <div class="table-wrap" style="margin-top:14px;">
        <table class="data-table" style="width:100%;">
            <thead>
                <tr><th>إجراءات</th><th>#</th><th>الكود</th><th>الاسم</th><th>الفئة</th><th>المصدر</th><th>موقع القوة</th><th>الدرجة</th><th>الحالة</th><th>اللياقة</th><th>الاعتمادات</th></tr>
            </thead>
            <tbody>
            <?php
            $list_sql = "SELECT wp.*, wp.name AS employee_name, wp.employee_code AS emp_code,
                                wp.worker_code AS code, wp.workforce_state AS state, wp.general_notes AS notes,
                                (SELECT COUNT(*) FROM worker_qualification q WHERE q.employee_id = wp.id) AS quals_count,
                                (SELECT COUNT(*) FROM worker_qualification q WHERE q.employee_id = wp.id AND q.is_critical = 1 AND q.expiry_date IS NOT NULL AND q.expiry_date < CURDATE()) AS expired_critical
                         FROM employees wp
                         WHERE wp.is_workforce = 1 $company_scope_sql
                         ORDER BY wp.id DESC";
            $res = mysqli_query($conn, $list_sql);
            $i = 1; $WF_VIEW = [];
            if ($res) { while ($row = mysqli_fetch_assoc($res)):
                $stateClass = ($row['state'] === 'منتهٍ') ? 'status-inactive' : (($row['state'] === 'مخصّص') ? 'status-active' : 'status-warning');
                $WF_VIEW[$row['id']] = ems_wf_view_payload('تفاصيل العامل التشغيلي', 'fas fa-people-group', [
                    ems_wf_field('الكود', $row['code'] ?: ('W-' . $row['id']), 'fas fa-barcode'),
                    ems_wf_field('الاسم', $row['employee_name'] ?: '-', 'fas fa-user', ['size' => 'lg']),
                    ems_wf_field('الفئة التشغيلية', $row['worker_category'], 'fas fa-layer-group'),
                    ems_wf_field('المصدر', $row['source_type'], 'fas fa-sitemap'),
                    ems_wf_field('موقع القوة', $row['workforce_class'], 'fas fa-shield-halved'),
                    ems_wf_field('الدرجة المهنية', $row['job_grade'] ?: '-', 'fas fa-ranking-star'),
                    ems_wf_field('الحالة', $row['state'], 'fas fa-diagram-project', ['type' => 'status']),
                    ems_wf_field('اللياقة الطبية', $row['medical_fitness_status'] ?: '-', 'fas fa-heart-pulse'),
                    ems_wf_field('شروط اللياقة', $row['fitness_conditions'] ?: '-', 'fas fa-notes-medical', ['size' => 'lg']),
                    ems_wf_field('قابل للإحلال', intval($row['is_replaceable']) ? 'نعم' : 'لا', 'fas fa-right-left'),
                    ems_wf_field('عدد الاعتمادات', intval($row['quals_count']), 'fas fa-certificate'),
                    ems_wf_field('اعتماد حرج منتهٍ', intval($row['expired_critical']) > 0 ? ('نعم (' . intval($row['expired_critical']) . ')') : 'لا', 'fas fa-triangle-exclamation'),
                    ems_wf_field('ملاحظات', $row['notes'] ?: '-', 'fas fa-align-right', ['size' => 'full']),
                ]);
            ?>
                <tr>
                    <td><div class="action-btns">
                        <?= ems_wf_view_button($row['id']) ?>
                        <?php if ($can_edit): ?><a href="worker_register.php?edit=<?= intval($row['id']) ?>" class="action-btn edit" title="تعديل"><i class="fas fa-edit"></i></a><?php endif; ?>
                        <a href="worker_register.php?edit=<?= intval($row['id']) ?>&tab=quals" class="action-btn view" title="المهارات والاعتمادات"><i class="fas fa-certificate"></i></a>
                    </div></td>
                    <td><?= $i++ ?></td>
                    <td><code><?= htmlspecialchars($row['code'] ?: ('W-' . $row['id'])) ?></code></td>
                    <td><strong><?= htmlspecialchars($row['employee_name'] ?: '-') ?></strong></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($row['worker_category']) ?></span></td>
                    <td><?= htmlspecialchars($row['source_type']) ?></td>
                    <td><?= htmlspecialchars($row['workforce_class']) ?></td>
                    <td><?= htmlspecialchars($row['job_grade'] ?: '-') ?></td>
                    <td><span class="status-pill <?= $stateClass ?>"><?= htmlspecialchars($row['state']) ?></span></td>
                    <td><?= htmlspecialchars($row['medical_fitness_status'] ?: '-') ?></td>
                    <td>
                        <span class="badge badge-info"><?= intval($row['quals_count']) ?></span>
                        <?php if (intval($row['expired_critical']) > 0): ?><span class="link-alert-chip" title="اعتماد حرج منتهٍ"><i class="fas fa-exclamation-triangle"></i></span><?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; }
            if (!$res || $i === 1): ?>
                <tr><td colspan="11" style="text-align:center;color:#888;padding:18px;">لا يوجد عمالٌ مصنّفون بعد. استخدم «تصنيف عامل تشغيلي» لإضافة أول عامل.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php ems_wf_view_modal($WF_VIEW); ?>

<script>
// اقتراح الفئة آلياً من نوع الموظف القائم (قرار 2) — قابلٌ للتعديل
var EMS_TYPE_TO_CAT = {
    'سائق/مشغّل':'مشغّل/سائق','مشغّل':'مشغّل/سائق','سائق':'مشغّل/سائق',
    'فني':'فني','فني ورشة':'فني','مهندس':'مهندس','مشرف':'مشرف','مراقب':'مراقب',
    'مبنشر':'عمالة مساندة','مساعد':'عمالة مساندة','إداري':'عمالة مساندة','أمن':'مراقب','أخرى':'عمالة مساندة'
};
function emsSuggestCategory() {
    var sel = document.getElementById('employee_id');
    if (!sel) return;
    var opt = sel.options[sel.selectedIndex];
    var t = opt ? (opt.getAttribute('data-emptype') || '') : '';
    var cat = EMS_TYPE_TO_CAT[t] || 'مشغّل/سائق';
    var catSel = document.getElementById('worker_category');
    if (catSel) catSel.value = cat;
}
function emsToggleSupplier() {
    var st = document.getElementById('source_type');
    var f = document.getElementById('supplierField');
    if (st && f) f.style.display = (st.value === 'شركة') ? 'none' : '';
}
(function(){
    var btn = document.getElementById('toggleForm');
    var form = document.getElementById('workerForm');
    if (btn && form) btn.addEventListener('click', function(){ form.style.display = (form.style.display === 'none' || !form.style.display) ? 'block' : 'none'; });
})();
</script>
</body>
</html>
