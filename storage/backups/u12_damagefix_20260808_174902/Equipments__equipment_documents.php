<?php
/**
 * Equipments/equipment_documents.php — وثائقُ المعدة والمشغّل (UX-10 §8.1)
 * ───────────────────────────────────────────────────────────────────────────
 * ملفُّ وثائقَ لكل معدةٍ ومشغّل: النوع · الرقم · الجهة · تاريخُ الانتهاء ·
 * مهلةُ التنبيه (alert_days) · المرفق · الحالة. الانتهاءُ الفعلي يُحسب من
 * expiry_date لا من عمود الحالة — فالحالةُ قرارُ بشرٍ (قيد التجديد/ملغاة)
 * والتاريخُ حقيقةٌ لا تُجادَل.
 *
 * المقيسُ يومَ البناء: 37 من 39 وثيقةً مرحَّلةً منتهية (25 رخصةَ مشغّلٍ
 * و12 رخصةَ معدة) — وأصحابُها يعملون. قرارُ المالك: تحذيرٌ ظاهرٌ لا منع.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';

$current_company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$current_user_id    = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
$is_super_admin     = isset($_SESSION['user']['role']) && (string) $_SESSION['user']['role'] === '-1';

if (!$is_super_admin && $current_company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$doc_perm = check_page_permissions($conn, 'Equipments/equipment_documents.php');
$can_view = !empty($doc_perm['can_view']);
$can_add  = !empty($doc_perm['can_add']);
$can_edit = !empty($doc_perm['can_edit']);
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض وثائق المعدات ❌', 'GOV-PERM-403', '');
    exit();
}

$doc_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('equipment documents super') : ems_tenant_db();

$DOC_TYPES = array('استمارة', 'تأمين', 'فحص دوري', 'رخصة قيادة', 'رخصة تشغيل', 'تصريح', 'أخرى');
$DOC_STATUSES = array('سارية', 'قيد التجديد', 'منتهية', 'ملغاة');

// ── إيقاف (soft) ──
if (isset($_GET['stop_doc'])) {
    if (!$can_edit) { ems_gov_flash_redirect('equipment_documents.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }
    try {
        $doc_gate->softDelete('equipment_documents', intval($_GET['stop_doc']));
        ems_gov_flash_redirect('equipment_documents.php', 'أُلغيت الوثيقة ✅', 'GOV-OK-200', '');
    } catch (\Throwable $t) {
        error_log('equipment_documents stop: ' . $t->getMessage());
        ems_gov_flash_redirect('equipment_documents.php', 'تعذّر الإلغاء ❌', 'GOV-FAIL-409', '');
    }
    exit();
}

// ── إضافة/تجديد ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doc'])) {
    if (!$can_add && !$can_edit) { ems_gov_flash_redirect('equipment_documents.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }

    $subjectType = ($_POST['subject_type'] ?? '') === 'operator' ? 'operator' : 'equipment';
    $subjectId = intval($_POST['subject_id'] ?? 0);
    $docType = in_array($_POST['doc_type'] ?? '', $DOC_TYPES, true) ? $_POST['doc_type'] : '';
    $expiry = trim(strval($_POST['expiry_date'] ?? ''));
    $alertDays = max(0, min(365, intval($_POST['alert_days'] ?? 30)));

    $err = null;
    if ($subjectId <= 0)  { $err = 'اختر+المعدة+أو+المشغّل'; }
    elseif ($docType === '') { $err = 'اختر+نوع+الوثيقة'; }
    elseif ($expiry !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) { $err = 'تاريخ+الانتهاء+غير+صحيح'; }
    if ($err !== null) { ems_gov_flash_redirect('equipment_documents.php', '{$err} ❌', 'GOV-FAIL-409', ''); exit(); }

    $row = array(
        'subject_type' => $subjectType, 'subject_id' => $subjectId,
        'doc_type' => $docType,
        'doc_no' => mb_substr(trim(strval($_POST['doc_no'] ?? '')), 0, 100) ?: null,
        'issuer' => mb_substr(trim(strval($_POST['issuer'] ?? '')), 0, 255) ?: null,
        'issue_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($_POST['issue_date'] ?? '')) ? $_POST['issue_date'] : null,
        'expiry_date' => $expiry !== '' ? $expiry : null,
        'alert_days' => $alertDays,
        'status' => in_array($_POST['status'] ?? '', $DOC_STATUSES, true) ? $_POST['status'] : 'سارية',
        'note' => mb_substr(trim(strval($_POST['note'] ?? '')), 0, 200) ?: null,
        'created_by' => $current_user_id,
    );
    try {
        $doc_gate->insert('equipment_documents', $row);
        ems_gov_flash_redirect('equipment_documents.php', 'أُضيفت الوثيقة ✅', 'GOV-OK-200', '');
    } catch (\Throwable $t) {
        error_log('equipment_documents add: ' . $t->getMessage());
        $dup = (strpos($t->getMessage(), 'Duplicate') !== false);
        ems_gov_flash_redirect('equipment_documents.php', $dup ? 'وثيقةٌ مكررة (نفس الصاحب والنوع والرقم) ❌' : 'تعذّرت الإضافة ❌', 'GOV-FAIL-409', '');
    }
    exit();
}

// ── القراءات ──
$today = date('Y-m-d');
$docs = array();
try {
    $docs = $doc_gate->scopedQuery(
        array('scope' => array('d' => 'equipment_documents'),
              'enrich' => array('e' => 'equipments', 'em' => 'employees')),
        "SELECT d.*, e.name AS eq_name, e.code AS eq_code, em.name AS emp_name
           FROM equipment_documents d
           LEFT JOIN equipments e ON d.subject_type = 'equipment' AND e.id = d.subject_id
           LEFT JOIN employees em ON d.subject_type = 'operator' AND em.id = d.subject_id
          WHERE {TENANT_SCOPE}
          ORDER BY (d.expiry_date IS NULL) ASC, d.expiry_date ASC");
} catch (\Throwable $t) { error_log('equipment_documents list: ' . $t->getMessage()); }

// التصنيف الفعلي بالحقيقة (التاريخ) لا بعمود الحالة
$nExpired = 0; $nSoon = 0; $nOk = 0;
foreach ($docs as $d) {
    if ($d['status'] === 'ملغاة') { continue; }
    if ($d['expiry_date'] !== null && $d['expiry_date'] < $today) { $nExpired++; }
    elseif ($d['expiry_date'] !== null
        && $d['expiry_date'] <= date('Y-m-d', strtotime($today . ' +' . intval($d['alert_days']) . ' days'))) { $nSoon++; }
    else { $nOk++; }
}

// خيارا النموذج
$equipments = array();
try {
    $equipments = $doc_gate->scopedQuery(array('scope' => array('e' => 'equipments')),
        "SELECT e.id, e.code, e.name FROM equipments e WHERE {TENANT_SCOPE} ORDER BY e.code");
} catch (\Throwable $t) { /* يُسجَّل أعلاه نمطًا */ }
$operators = array();
try {
    $operators = $doc_gate->scopedQuery(array('scope' => array('em' => 'employees')),
        "SELECT em.id, em.name FROM employees em WHERE {TENANT_SCOPE} AND em.status = 1 ORDER BY em.name");
} catch (\Throwable $t) { /* المصدر العام */ }

$page_title = 'إيكوبيشن | وثائق المعدات والمشغّلين';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main eq-docs-main ems-unified-page-shell">
    <?php
    $header_title = 'وثائق المعدات والمشغّلين';
    $header_icon  = 'fa fa-file-shield';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert" style="background:#fffbe6;border:1px solid #f0c36d;border-radius:8px;padding:10px 14px;margin-bottom:12px;">
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <p style="color:#4b5563;margin:0 0 12px;line-height:1.8;">
            <i class="fas fa-circle-info"></i>
            ملفُّ وثائقَ لكل معدةٍ ومشغّل — <strong>الانتهاءُ يُحسب من التاريخ لا من الحالة</strong>،
            والتنبيهُ قبل الانتهاء بمهلة كلِّ وثيقة (alert_days). التجديدُ بإضافة وثيقةٍ جديدةٍ
            بتاريخها الجديد — والقديمةُ تبقى تاريخًا.
        </p>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span class="badge badge-danger" style="padding:6px 12px;font-size:13px;">
                <i class="fas fa-circle-xmark"></i> منتهية: <?php echo $nExpired; ?>
            </span>
            <span class="badge badge-warning" style="padding:6px 12px;font-size:13px;">
                <i class="fas fa-hourglass-half"></i> توشك على الانتهاء: <?php echo $nSoon; ?>
            </span>
            <span class="badge badge-success" style="padding:6px 12px;font-size:13px;">
                <i class="fas fa-circle-check"></i> سارية: <?php echo $nOk; ?>
            </span>
        </div>
    </div></div>

    <?php if ($can_add || $can_edit): ?>
    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px;"><i class="fas fa-plus"></i> وثيقة جديدة / تجديد</h5>
        <form action="" method="post" class="allforms allforms-visible" style="box-shadow:none;padding:0;">
            <input type="hidden" name="add_doc" value="1">
            <div class="form-section"><div class="form-grid">
                <div class="form-group">
                    <label>صاحب الوثيقة *</label>
                    <select name="subject_type" id="docSubjectType" required>
                        <option value="equipment">معدة</option>
                        <option value="operator">مشغّل</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>المعدة / المشغّل *</label>
                    <select name="subject_id" id="docSubjectEq" required>
                        <option value="">— اختر المعدة —</option>
                        <?php foreach ($equipments as $e) {
                            echo "<option value='" . intval($e['id']) . "'>" . htmlspecialchars(trim($e['code'] . ' ' . $e['name'])) . "</option>";
                        } ?>
                    </select>
                    <select name="subject_id_operator" id="docSubjectOp" style="display:none;" disabled>
                        <option value="">— اختر المشغّل —</option>
                        <?php foreach ($operators as $o) {
                            echo "<option value='" . intval($o['id']) . "'>" . htmlspecialchars($o['name']) . "</option>";
                        } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>نوع الوثيقة *</label>
                    <select name="doc_type" required>
                        <?php foreach ($DOC_TYPES as $t) { echo "<option>" . $t . "</option>"; } ?>
                    </select>
                </div>
                <div class="form-group"><label>رقم الوثيقة</label>
                    <input type="text" name="doc_no" maxlength="100"></div>
                <div class="form-group"><label>جهة الإصدار</label>
                    <input type="text" name="issuer" maxlength="255"></div>
                <div class="form-group"><label>تاريخ الإصدار</label>
                    <input type="date" name="issue_date"></div>
                <div class="form-group"><label>تاريخ الانتهاء</label>
                    <input type="date" name="expiry_date"></div>
                <div class="form-group"><label>التنبيه قبل (أيام)</label>
                    <input type="number" name="alert_days" min="0" max="365" value="30"></div>
                <div class="form-group">
                    <label>الحالة</label>
                    <select name="status">
                        <?php foreach ($DOC_STATUSES as $s) { echo "<option>" . $s . "</option>"; } ?>
                    </select>
                </div>
                <div class="form-group"><label>ملاحظة</label>
                    <input type="text" name="note" maxlength="200"></div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ الوثيقة</button>
            </div>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px;"><i class="fas fa-list"></i> الوثائق</h5>
        <div class="table-container">
            <table id="docsTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الصاحب</th><th>نوع الوثيقة</th><th>رقم الوثيقة</th><th>الجهة المصدِرة</th>
                    <th>تاريخ الانتهاء</th><th>الوضع الفعلي</th><th>الحالة</th>
                    <?php if ($can_edit) echo '<th>إجراء</th>'; ?>
                    <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                    <th class="ems-fn-th" data-fn="1">كود المعدة أو المشغّل</th>
                    <th class="ems-fn-th" data-fn="1">الرقم أو المرجع</th>
                    <th class="ems-fn-th" data-fn="1">تاريخ الإصدار</th>
                    <th class="ems-fn-th" data-fn="1">المدة المتبقية بالأيام</th>
                    <th class="ems-fn-th" data-fn="1">وثيقة حرجة؟</th>
                    <th class="ems-fn-th" data-fn="1">أثر الانتهاء</th>
                    <th class="ems-fn-th" data-fn="1">التنبيه قبل بالأيام</th>
                    <th class="ems-fn-th" data-fn="1">المسؤول</th>
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                    <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                    </tr></thead>
                <tbody>
                <?php foreach ($docs as $d) {
                    $stopped = ($d['deleted_at'] !== null || (int) $d['is_deleted'] === 1);
                    if ($stopped) { continue; }
                    $owner = $d['subject_type'] === 'operator'
                        ? '👷 ' . ($d['emp_name'] ?: ('#' . $d['subject_id']))
                        : '🚜 ' . trim(($d['eq_code'] ?: '') . ' ' . ($d['eq_name'] ?: ('#' . $d['subject_id'])));
                    // الوضع الفعلي من التاريخ — الحقيقة لا التصنيف
                    if ($d['status'] === 'ملغاة') { $real = "<span class='badge badge-secondary'>ملغاة</span>"; }
                    elseif ($d['expiry_date'] === null) { $real = "<span class='badge badge-info'>بلا انتهاء</span>"; }
                    elseif ($d['expiry_date'] < $today) {
                        $real = "<span class='badge badge-danger'>⚠ منتهية منذ " . htmlspecialchars($d['expiry_date']) . "</span>";
                    } elseif ($d['expiry_date'] <= date('Y-m-d', strtotime($today . ' +' . intval($d['alert_days']) . ' days'))) {
                        $real = "<span class='badge badge-warning'>توشك — " . htmlspecialchars($d['expiry_date']) . "</span>";
                    } else { $real = "<span class='badge badge-success'>سارية</span>"; }
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($owner) . "</td>";
                    echo "<td>" . htmlspecialchars($d['doc_type']) . "</td>";
                    echo "<td>" . htmlspecialchars($d['doc_no'] ?: '—') . "</td>";
                    echo "<td>" . htmlspecialchars($d['issuer'] ?: '—') . "</td>";
                    echo "<td style='direction:ltr;'>" . htmlspecialchars($d['expiry_date'] ?: '—') . "</td>";
                    echo "<td>" . $real . "</td>";
                    echo "<td>" . htmlspecialchars($d['status']) . "</td>";
                    if ($can_edit) {
                        echo "<td><a href='?stop_doc=" . intval($d['doc_id']) . "' class='badge badge-danger' style='text-decoration:none;padding:5px 10px;' onclick=\"return confirm('إلغاء هذه الوثيقة؟');\"><i class='fas fa-ban'></i> إلغاء</a></td>";
                    }
                    echo "</tr>";
                } ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($docs)): ?>
            <p style="color:#6b7280;text-align:center;padding:16px;"><i class="fas fa-circle-info"></i> لا وثائقَ بعد.</p>
        <?php endif; ?>
    </div></div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function () {
    $('#docsTable').DataTable({ scrollX: true, autoWidth: false, stateSave: false, pageLength: 25, order: [],
        "language": { "url": "/ems/assets/i18n/datatables/ar.json" } });

    // تبديلُ قائمة الصاحب (معدة ⇄ مشغّل) — قائمةٌ واحدةٌ نشطةٌ تحمل الاسم subject_id
    $('#docSubjectType').on('change', function () {
        var isOp = $(this).val() === 'operator';
        $('#docSubjectEq').prop('disabled', isOp).attr('name', isOp ? 'subject_id_equipment' : 'subject_id').toggle(!isOp);
        $('#docSubjectOp').prop('disabled', !isOp).attr('name', isOp ? 'subject_id' : 'subject_id_operator').toggle(isOp);
    });
});
</script>
</body>
</html>
