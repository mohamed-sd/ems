<?php
/**
 * طبقة القوى التشغيلية (EQUIP-OPE-S04) — 8.4 تخصيص العامل (L4).
 * طبقة إثراءٍ فوق equipment_drivers/operations (قرار 9). يطبّق محرّك الجاهزية
 * (HumanReadiness) ومحرّك الحصص (Quota: L4 ≤ L3) قبل أي إسناد. صفر لمسٍ للقائم.
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }

include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Workforce/QuotaService.php';
require_once __DIR__ . '/../app/Services/Workforce/HumanReadinessService.php';
require_once __DIR__ . '/../app/Services/Workforce/CoverageService.php';
require_once __DIR__ . '/../app/Services/Workforce/ViewModal.php';

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id        = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=بيئة+شركة+غير+صالحة+❌"); exit(); }

$page_permissions = check_page_permissions($conn, 'Workforce/worker_allocation.php');
$can_view = $page_permissions['can_view']; $can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit']; $can_delete = $page_permissions['can_delete'];
if (!$can_view) { header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+التخصيص+❌"); exit(); }

$scope_sql        = $is_super_admin ? "" : " AND wa.company_id = " . intval($company_id) . " ";
$ops_company_sql  = $is_super_admin ? "" : " AND o.company_id = " . intval($company_id) . " ";
$wp_company_sql   = $is_super_admin ? "" : " AND wp.company_id = " . intval($company_id) . " ";

// ── إنشاء تخصيص (مع بوّابتَي الجاهزية والحصص) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_alloc' && $can_add) {
    $worker_id    = intval($_POST['worker_id'] ?? 0);
    $operation_id = intval($_POST['operation_id'] ?? 0);
    $eqd          = !empty($_POST['equipment_driver_id']) ? intval($_POST['equipment_driver_id']) : null;
    $alloc_qty    = $_POST['allocated_qty'] !== '' ? floatval($_POST['allocated_qty']) : null;
    $state        = trim($_POST['state'] ?? 'مخطّط');
    $crew_role    = trim($_POST['crew_role'] ?? 'فرد');
    $lead         = !empty($_POST['lead_allocation_id']) ? intval($_POST['lead_allocation_id']) : null;
    $active_bk    = !empty($_POST['active_backup_id']) ? intval($_POST['active_backup_id']) : null;
    $coverage     = trim($_POST['coverage_reason'] ?? '');
    $exp_end      = !empty($_POST['expected_end_date']) ? $_POST['expected_end_date'] : null;
    $source       = trim($_POST['source_type'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');

    $errors = [];
    if ($worker_id <= 0)    $errors[] = 'يجب اختيار عامل';
    if ($operation_id <= 0) $errors[] = 'يجب اختيار عملية (معدة↔مشروع)';

    // بوّابة الجاهزية البشرية
    if ($worker_id > 0) {
        $rd = ems_worker_readiness($conn, $worker_id);
        if (!$rd['ready']) { $errors[] = 'الجاهزية: ' . implode(' · ', $rd['reasons']); }
    }
    // بوّابة الحصص (تُفحَص عند الحالات الفاعلة)
    if ($operation_id > 0 && in_array($state, ['معتمد','نشط'], true)) {
        $q = ems_quota_check_allocation($conn, $operation_id);
        if (!$q['allowed']) { $errors[] = $q['message']; }
    }

    if (!empty($errors)) {
        header("Location: worker_allocation.php?add=1&msg=" . urlencode('❌ ' . implode(' | ', $errors)));
        exit();
    }

    $cid = $is_super_admin ? null : $company_id;
    $cov = $coverage !== '' ? $coverage : null;
    $src = $source !== '' ? $source : null;
    // محرّك التغطية: تخصيصُ تغطيةٍ بلا بديلٍ محدَّد ⇐ نقترح أنسب بديلٍ متاح (أساسي←احتياطي←مؤقت).
    if ($active_bk === null && $cov !== null) {
        $active_bk = ems_coverage_best_id($conn, $worker_id);
    }
    $sql = "INSERT INTO worker_allocation
            (company_id, employee_id, equipment_driver_id, operation_id, allocated_qty, state, crew_role,
             lead_allocation_id, active_backup_id, coverage_reason, expected_end_date, source_type, notes, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    // types: company i, worker i, eqd i, operation i, qty d, state s, crew s, lead i, backup i,
    //        coverage s, exp_end s, source s, notes s, createdby i
    $stmt->bind_param(
        'iiiidssiissssi',
        $cid, $worker_id, $eqd, $operation_id, $alloc_qty, $state, $crew_role,
        $lead, $active_bk, $cov, $exp_end, $src, $notes, $user_id
    );
    $ok = $stmt->execute(); $stmt->close();
    header("Location: worker_allocation.php?msg=" . ($ok ? "✅+تم+إنشاء+التخصيص" : "❌+تعذّر+الحفظ"));
    exit();
}

// ── تغيير حالة التخصيص (آلة حالةٍ مستقلّة) — مع إعادة فحص الحصص عند التفعيل ───────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_state' && $can_edit) {
    $aid       = intval($_POST['aid'] ?? 0);
    $new_state = trim($_POST['new_state'] ?? '');
    $valid = ['مخطّط','معتمد','نشط','منتهٍ'];
    if ($aid > 0 && in_array($new_state, $valid, true)) {
        // جلب العملية لإعادة فحص الحصص عند الانتقال لحالةٍ فاعلة
        $op = 0; $sc = $is_super_admin ? "" : " AND company_id = " . intval($company_id);
        $g = $conn->prepare("SELECT operation_id FROM worker_allocation WHERE id = ? $sc LIMIT 1");
        $g->bind_param('i', $aid); $g->execute();
        $gr = $g->get_result()->fetch_assoc(); $g->close();
        $op = $gr ? intval($gr['operation_id']) : 0;

        if (in_array($new_state, ['معتمد','نشط'], true) && $op > 0) {
            $q = ems_quota_check_allocation($conn, $op, $aid);
            if (!$q['allowed']) { header("Location: worker_allocation.php?msg=" . urlencode('❌ ' . $q['message'])); exit(); }
        }
        $u = $conn->prepare("UPDATE worker_allocation SET state = ? WHERE id = ? $sc");
        $u->bind_param('si', $new_state, $aid); $u->execute(); $u->close();
        header("Location: worker_allocation.php?msg=✅+تم+تحديث+الحالة"); exit();
    }
    header("Location: worker_allocation.php?msg=❌+حالة+غير+صالحة"); exit();
}

if (($_GET['delete'] ?? '') !== '' && $can_delete) {
    $del = intval($_GET['delete']); $sc = $is_super_admin ? "" : " AND company_id = " . intval($company_id);
    $stmt = $conn->prepare("DELETE FROM worker_allocation WHERE id = ? $sc");
    $stmt->bind_param('i', $del); $stmt->execute(); $stmt->close();
    header("Location: worker_allocation.php?msg=✅+تم+الحذف"); exit();
}

// ── قوائم الاختيار ─────────────────────────────────────────────────────────────
$workers = [];
$wq = mysqli_query($conn, "SELECT wp.id, wp.name AS name FROM employees wp WHERE wp.is_workforce = 1 $wp_company_sql ORDER BY wp.name");
if ($wq) { while ($w = mysqli_fetch_assoc($wq)) { $workers[$w['id']] = $w['name']; } }

$operations = [];
$oq = mysqli_query($conn, "SELECT o.id, o.equipment, o.shift_type, p.name AS pname
                           FROM operations o LEFT JOIN project p ON p.id = o.project_id
                           WHERE 1=1 $ops_company_sql ORDER BY o.id DESC LIMIT 500");
if ($oq) { while ($o = mysqli_fetch_assoc($oq)) { $operations[] = $o; } }

$page_title = "إيكوبيشن | تخصيص العاملين";
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main">
    <?php
    $header_title = 'تخصيص العاملين (L4)'; $header_icon = 'fas fa-diagram-project'; $header_actions = array();
    if ($can_add) $header_actions[] = array('id'=>'toggleForm','class'=>'add-btn','icon'=>'fas fa-plus-circle','label'=>'تخصيص جديد');
    $header_back = array('href'=>'worker_register.php','class'=>'','icon'=>'fas fa-arrow-right','label'=>'سجل العامل');
    include('../includes/page_header.php');
    ?>
    <?php if (!empty($_GET['msg'])): $ok = strpos($_GET['msg'],'✅')!==false; ?>
        <div class="success-message <?= $ok?'is-success':'is-error' ?>"><i class="fas <?= $ok?'fa-check-circle':'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>

    <form id="aForm" action="" method="post" class="allforms" style="<?= !empty($_GET['add']) ? '' : 'display:none;' ?>">
        <input type="hidden" name="action" value="save_alloc">
        <div class="card-header"><h5><i class="fas fa-plus"></i> تخصيص عاملٍ على عملية</h5></div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;padding:14px;">
            <div class="field"><label>العامل</label><select name="worker_id" required><option value="">— اختر —</option>
                <?php foreach ($workers as $wid=>$wn): ?><option value="<?= intval($wid) ?>"><?= htmlspecialchars($wn) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>العملية (معدة↔مشروع) = L3</label><select name="operation_id" required><option value="">— اختر —</option>
                <?php foreach ($operations as $o): ?><option value="<?= intval($o['id']) ?>"><?= htmlspecialchars(($o['equipment'] ?: ('#'.$o['id'])) . ' — ' . ($o['pname'] ?: '') . ' [' . $o['shift_type'] . ']') ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>دور الطاقم</label><select name="crew_role"><?php foreach (['فرد','قائد طاقم','عضو طاقم'] as $cr): ?><option value="<?= $cr ?>"><?= $cr ?></option><?php endforeach; ?></select></div>

            <div class="field"><label>الحالة</label><select name="state"><?php foreach (['مخطّط','معتمد','نشط','منتهٍ'] as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>مصدر العامل</label><select name="source_type"><option value="">—</option><?php foreach (['شركة','مورد','مقاول'] as $st): ?><option value="<?= $st ?>"><?= $st ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>السقف (وحدات/ساعات)</label><input type="number" step="0.01" name="allocated_qty"></div>

            <div class="field"><label>سبب التغطية</label><select name="coverage_reason"><option value="">—</option><?php foreach (['غياب مفاجئ','إجازة/مرض','تبديل وردية','توسّع مؤقت','بدء مشروع','طوارئ'] as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>نهاية متوقعة</label><input type="date" name="expected_end_date"></div>
            <div class="field"><label>تخصيص قائد الطاقم (#)</label><input type="number" name="lead_allocation_id" placeholder="رقم تخصيص القائد"></div>

            <div class="field" style="grid-column:1/-1;"><label>ملاحظات</label><textarea name="notes" rows="2"></textarea></div>
        </div>
        <div style="padding:0 14px 16px;display:flex;gap:10px;">
            <button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ (مع فحص الجاهزية والحصص)</button>
            <a href="worker_allocation.php" class="add-btn" style="background:#6b7280;"><i class="fas fa-times"></i> إلغاء</a>
        </div>
    </form>

    <div class="table-wrap" style="margin-top:14px;">
        <table class="data-table" style="width:100%;">
            <thead><tr><th>إجراءات</th><th>#</th><th>العامل</th><th>العملية</th><th>الدور</th><th>المصدر</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php
            $list = mysqli_query($conn, "SELECT wa.*, e.name AS wname, o.equipment, p.name AS pname
                FROM worker_allocation wa
                LEFT JOIN employees e ON e.id = wa.employee_id
                LEFT JOIN operations o ON o.id = wa.operation_id
                LEFT JOIN project p ON p.id = o.project_id
                WHERE 1=1 $scope_sql ORDER BY wa.id DESC");
            $i=1; $WF_VIEW = []; if ($list) { while ($r = mysqli_fetch_assoc($list)):
                $sc = ($r['state']==='نشط')?'status-active':(($r['state']==='منتهٍ')?'status-inactive':'status-warning');
                $WF_VIEW[$r['id']] = ems_wf_view_payload('تفاصيل تخصيص العامل', 'fas fa-diagram-project', [
                    ems_wf_field('العامل', $r['wname'] ?: '-', 'fas fa-user', ['size' => 'lg']),
                    ems_wf_field('المعدة', $r['equipment'] ?: '-', 'fas fa-truck-monster'),
                    ems_wf_field('المشروع', $r['pname'] ?: '-', 'fas fa-folder-open'),
                    ems_wf_field('العملية (L3)', $r['operation_id'] ? ('#' . intval($r['operation_id'])) : '-', 'fas fa-gears'),
                    ems_wf_field('سائق المعدة (L4)', $r['equipment_driver_id'] ? ('#' . intval($r['equipment_driver_id'])) : '-', 'fas fa-id-badge'),
                    ems_wf_field('دور الطاقم', $r['crew_role'], 'fas fa-people-group'),
                    ems_wf_field('المصدر', $r['source_type'] ?: '-', 'fas fa-sitemap'),
                    ems_wf_field('الحالة', $r['state'], 'fas fa-flag', ['type' => 'status']),
                    ems_wf_field('السقف (وحدات/ساعات)', $r['allocated_qty'] !== null ? $r['allocated_qty'] : '-', 'fas fa-gauge'),
                    ems_wf_field('سبب التغطية', $r['coverage_reason'] ?: '-', 'fas fa-user-shield'),
                    ems_wf_field('البديل المُفعّل', $r['active_backup_id'] ? ('#' . intval($r['active_backup_id'])) : '-', 'fas fa-user-clock'),
                    ems_wf_field('قائد الطاقم (تخصيص)', $r['lead_allocation_id'] ? ('#' . intval($r['lead_allocation_id'])) : '-', 'fas fa-user-tie'),
                    ems_wf_field('نهاية متوقعة', $r['expected_end_date'] ?: '-', 'fas fa-calendar-xmark'),
                    ems_wf_field('ملاحظات', $r['notes'] ?: '-', 'fas fa-align-right', ['size' => 'full']),
                ]); ?>
                <tr>
                    <td><div class="action-btns" style="gap:4px;align-items:center;">
                        <?= ems_wf_view_button($r['id']) ?>
                        <form action="" method="post" style="display:inline;">
                            <input type="hidden" name="action" value="set_state">
                            <input type="hidden" name="aid" value="<?= intval($r['id']) ?>">
                            <select name="new_state" onchange="this.form.submit()" <?= $can_edit?'':'disabled' ?> style="padding:2px;">
                                <?php foreach (['مخطّط','معتمد','نشط','منتهٍ'] as $s): ?><option value="<?= $s ?>" <?= ($r['state']===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
                            </select>
                        </form>
                        <?php if ($can_delete): ?><a href="worker_allocation.php?delete=<?= intval($r['id']) ?>" class="action-btn delete" onclick="return confirm('حذف التخصيص؟')"><i class="fas fa-trash"></i></a><?php endif; ?>
                    </div></td>
                    <td><?= intval($r['id']) ?></td>
                    <td><strong><?= htmlspecialchars($r['wname'] ?: '-') ?></strong></td>
                    <td><?= htmlspecialchars(($r['equipment'] ?: '-') . ' / ' . ($r['pname'] ?: '-')) ?></td>
                    <td><?= htmlspecialchars($r['crew_role']) ?></td>
                    <td><?= htmlspecialchars($r['source_type'] ?: '-') ?></td>
                    <td><span class="status-pill <?= $sc ?>"><?= htmlspecialchars($r['state']) ?></span></td>
                </tr>
            <?php endwhile; } if (!$list || $i===1): ?>
                <tr><td colspan="7" style="text-align:center;color:#888;padding:18px;">لا توجد تخصيصاتٌ بعد.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ═══ مرحلة 1: الإسناد القائم (عامل↔آلية) من جدول equipment_drivers — قراءة فقط ═══ -->
    <div class="allforms" style="margin-top:24px;padding:0;">
        <div class="card-header"><h5><i class="fas fa-id-badge"></i> الإسناد القائم للمشغّلين على الآليات <small style="color:#888;font-weight:400;">(من جدول equipment_drivers — مرحلة أولى للقراءة)</small></h5></div>
    </div>
    <div class="table-wrap" style="margin-top:8px;">
        <table class="data-table" style="width:100%;">
            <thead><tr><th>#</th><th>العامل/المشغّل</th><th>المعدة</th><th>الوردية</th><th>تاريخ البداية</th><th>تاريخ النهاية</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php
            $ed_scope = $is_super_admin ? "" : " AND ed.company_id = " . intval($company_id) . " ";
            $shiftMap = ['D' => 'نهاري', 'N' => 'ليلي', 'B' => 'كلاهما'];
            $edq = mysqli_query($conn, "SELECT ed.id, ed.equipment_id, ed.shift_type, ed.start_date, ed.end_date, ed.status,
                        e.name AS worker_name, eq.name AS equipment_name, eq.code AS equipment_code, eq.type AS equipment_type
                    FROM equipment_drivers ed
                    LEFT JOIN employees e ON e.id = ed.employee_id
                    LEFT JOIN equipments eq ON eq.id = ed.equipment_id
                    WHERE 1=1 $ed_scope ORDER BY ed.id DESC");
            $j = 1;
            if ($edq) { while ($ed = mysqli_fetch_assoc($edq)):
                $stc = intval($ed['status']) === 1 ? 'status-active' : 'status-inactive';
                $stt = intval($ed['status']) === 1 ? 'نشط' : 'منتهٍ';
                $eqLabel = trim(($ed['equipment_name'] ?: '') . ($ed['equipment_code'] ? ' (' . $ed['equipment_code'] . ')' : ''));
                if ($eqLabel === '') $eqLabel = '#' . intval($ed['equipment_id']);
            ?>
                <tr>
                    <td><?= $j++ ?></td>
                    <td><strong><?= htmlspecialchars($ed['worker_name'] ?: '-') ?></strong></td>
                    <td><?= htmlspecialchars($eqLabel) ?><?php if (!empty($ed['equipment_type'])): ?> <small style="color:#888;"><?= htmlspecialchars($ed['equipment_type']) ?></small><?php endif; ?></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($shiftMap[$ed['shift_type']] ?? $ed['shift_type']) ?></span></td>
                    <td><?= htmlspecialchars($ed['start_date'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($ed['end_date'] ?: '-') ?></td>
                    <td><span class="status-pill <?= $stc ?>"><?= $stt ?></span></td>
                </tr>
            <?php endwhile; }
            if (!$edq || $j === 1): ?>
                <tr><td colspan="7" style="text-align:center;color:#888;padding:18px;">لا توجد إسنادات في جدول equipment_drivers.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php ems_wf_view_modal($WF_VIEW); ?>
<script>
(function(){ var b=document.getElementById('toggleForm'), f=document.getElementById('aForm');
  if(b&&f) b.addEventListener('click',function(){ f.style.display=(f.style.display==='none'||!f.style.display)?'block':'none'; }); })();
</script>
</body>
</html>
