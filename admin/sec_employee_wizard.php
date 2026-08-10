<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * admin/sec_employee_wizard.php — معالج إعداد الموظف: إحدى عشرة خطوة
 * (update0004 · SEC-26 · SEC-01 §10.1)
 * ───────────────────────────────────────────────────────────────────────────
 * ① الهوية والعلاقة ② العائلة ③ المستوى ④ المسمى ⑤ الجهة ⑥ المدير والتبعيتان
 * ⑦ النطاق ⑧ التكليف ⑨ معاينة الصلاحيات بمصادرها ⑩ الإرسال للموافقة
 * ⑪ التفعيل الآلي بعد اكتمالها — «يُعدُّ بها موظف جديد في دقائق».
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';

// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
require_once __DIR__ . '/../includes/screen_contract.php';
require_once __DIR__ . '/../app/Services/Security/PositionService.php';
require_once __DIR__ . '/../app/Services/Security/SelfGrantGuard.php';

use App\Services\Security\PositionService as PS;
use App\Services\Security\SelfGrantGuard as SGG;

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php"); exit(); }
if ($is_super_admin && $company_id <= 0) { $company_id = 4; }

$MODULE_CODE = 'admin/sec_employee_wizard.php';
$can_view = $is_super_admin; $can_add = $is_super_admin;
if (!$is_super_admin) {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view = intval($row['can_view']) === 1;
        $can_add = intval($row['can_add']) === 1;
    }
    $st->close();
}
if (!$can_view) { ems_gov_redirect("Location: ../main/dashboard.php?msg=" . rawurlencode('لا صلاحية للمعالج ❌')); exit(); }

$msg = strval($_GET['msg'] ?? '');
$preview = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wz_action']) && $can_add) {
    $d = array(
        'company_id' => $company_id,
        'relation_code' => strval($_POST['relation_code'] ?? ''),
        'family_code' => strval($_POST['family_code'] ?? ''),
        'level_code' => strval($_POST['level_code'] ?? ''),
        'title_code' => strval($_POST['title_code'] ?? ''),
        'org_unit_id' => intval($_POST['org_unit_id'] ?? 0) ?: null,
        'manager_person_id' => intval($_POST['manager_person_id'] ?? 0) ?: null,
        'scope_type' => strval($_POST['scope_type'] ?? ''),
        'scope_id' => ($_POST['scope_id'] ?? '') !== '' ? intval($_POST['scope_id']) : null,
        'valid_from' => strval($_POST['valid_from'] ?? ''),
        'valid_to' => strval($_POST['valid_to'] ?? '') ?: null,
        'is_primary' => 1,
        'created_by' => $uid,
        'reason' => 'معالج إعداد موظف — ' . strval($_POST['full_name'] ?? ''),
    );
    if (strval($_POST['wz_action']) === 'preview') {
        // ⑨ معاينة الصلاحيات المقترحة بمصادرها قبل الإرسال
        $preview = PS::preview($conn, $d);
    } else {
        // ⑩ الإرسال — الشخص يُنشأ إن كان جديدًا، والمنح الذاتي محجوب
        $personId = intval($_POST['person_id'] ?? 0);
        if ($personId === 0 && strval($_POST['full_name'] ?? '') !== '') {
            $stmt = $conn->prepare('INSERT INTO persons (full_name) VALUES (?)');
            $fn = strval($_POST['full_name']);
            $stmt->bind_param('s', $fn);
            $stmt->execute();
            $personId = intval($conn->insert_id);
            $stmt->close();
            $stmt = $conn->prepare('INSERT INTO person_relationships (person_id, company_id, relation_code, valid_from) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('iiss', $personId, $company_id, $d['relation_code'], $d['valid_from']);
            $stmt->execute();
            $stmt->close();
        }
        $sg = SGG::checkGrant($conn, $uid, $personId, $company_id, 'wizard');
        if (!$sg['ok']) {
            ems_gov_redirect("Location: sec_employee_wizard.php?msg=" . rawurlencode($sg['reason'] . ' ❌'));
            exit();
        }
        $d['person_id'] = $personId;
        if (!empty($_POST['end_previous_p_id'])) { $d['end_previous_p_id'] = intval($_POST['end_previous_p_id']); }
        $r = PS::submit($conn, $d);
        ems_gov_redirect("Location: sec_governance.php?msg=" . rawurlencode($r['reason'] . ($r['ok'] ? ' ✅' : ' ❌')));
        exit();
    }
}

$qa = function ($sql) use ($conn) { $r = $conn->query($sql); return $r ? $r->fetch_all(MYSQLI_ASSOC) : array(); };
$relations = $qa("SELECT code, name_ar FROM hr_dictionaries WHERE layer='relation' AND active=1");
$families = $qa("SELECT code, name_ar FROM hr_dictionaries WHERE layer='family' AND active=1");
$levels = $qa("SELECT code, name_ar FROM hr_dictionaries WHERE layer='level' AND active=1 ORDER BY `rank`");
$titles = $qa("SELECT title_code, name FROM job_titles WHERE active=1 ORDER BY id");
$units = $qa("SELECT unit_id, name_ar FROM org_units WHERE company_id={$company_id} AND active=1");
$sitesList = $qa("SELECT id, name FROM sites WHERE company_id={$company_id} AND is_deleted=0 ORDER BY name");
$usersList = $qa("SELECT id, name FROM users WHERE company_id={$company_id} ORDER BY name");
$personsList = $qa("SELECT person_id, full_name FROM persons WHERE active=1 ORDER BY person_id DESC LIMIT 100");
$assignments = $qa("SELECT a.asg_id, t.name_ar FROM org_assignments a JOIN org_assignment_types t ON t.type_code=a.assignment_type_code WHERE a.company_id={$company_id} AND a.state='active' LIMIT 100");

$page_title = 'إيكوبيشن | معالج إعداد الموظف';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';

function wz_step($n, $title) { echo '<h5 style="margin-top:14px"><span class="badge badge-warning">' . $n . '</span> ' . htmlspecialchars($title) . '</h5>'; }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'معالج إعداد الموظف — إحدى عشرة خطوة'; $header_icon = 'fa fa-user-plus';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about(
        'الطبقات السبع في معالج واحد: يُعدُّ به موظف جديد في دقائق — يختار قالبه فيمنحه '
        . 'النظام حزمته، ويعاين الصلاحيات بمصادرها قبل الإرسال، ولا تفعيل إلا باكتمال الموافقات.',
        array('لا موظف بلا عائلة ولا مركز بلا نطاق',
              'المعاينة (⑨) تُظهر ما سيُمنح ومن أي مصدر قبل الإرسال',
              'التفعيل (⑪) آليٌّ بعد الموافقات — لا نقرة صلاحية واحدة'));
    if ($msg !== '') { echo '<div class="alert alert-info">' . htmlspecialchars($msg) . '</div>'; }
    ?>

    <form method="post" class="allforms allforms-visible" id="wzForm">
        <input type="hidden" name="wz_action" value="preview" id="wzAction">
        <?php wz_step('①', 'هوية الشخص ونوع العلاقة'); ?>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
            <div class="form-group"><label for="emsf_1991_7a2c4">شخص قائم (أو اتركه واكتب اسمًا)</label>
                <select name="person_id" id="emsf_1991_7a2c4"><option value="0">— جديد —</option>
                    <?php foreach ($personsList as $p) { echo '<option value="' . intval($p['person_id']) . '">' . htmlspecialchars($p['full_name']) . '</option>'; } ?></select></div>
            <div class="form-group"><label for="emsf_1992_f9f7a">الاسم الكامل (للجديد)</label><input type="text" name="full_name" id="emsf_1992_f9f7a"></div>
            <div class="form-group"><label for="emsf_1993_4bb1a">نوع العلاقة *</label>
                <select name="relation_code" required id="emsf_1993_4bb1a"><?php foreach ($relations as $x) { echo '<option value="' . htmlspecialchars($x['code']) . '">' . htmlspecialchars($x['name_ar']) . '</option>'; } ?></select></div>
        </div>
        <?php wz_step('②–④', 'العائلة الوظيفية · المستوى · المسمى'); ?>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
            <div class="form-group"><label for="emsf_1994_bed48">العائلة * (لا موظف بلا عائلة)</label>
                <select name="family_code" required id="emsf_1994_bed48"><?php foreach ($families as $x) { echo '<option value="' . htmlspecialchars($x['code']) . '">' . htmlspecialchars($x['name_ar']) . '</option>'; } ?></select></div>
            <div class="form-group"><label for="emsf_1995_1ba1c">المستوى *</label>
                <select name="level_code" required id="emsf_1995_1ba1c"><?php foreach ($levels as $x) { echo '<option value="' . htmlspecialchars($x['code']) . '">' . htmlspecialchars($x['name_ar']) . '</option>'; } ?></select></div>
            <div class="form-group"><label for="emsf_1996_bf6bd">المسمى *</label>
                <select name="title_code" required id="emsf_1996_bf6bd"><?php foreach ($titles as $x) { echo '<option value="' . htmlspecialchars($x['title_code']) . '">' . htmlspecialchars($x['name']) . '</option>'; } ?></select></div>
        </div>
        <?php wz_step('⑤–⑥', 'الجهة · المدير والتبعيتان'); ?>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px">
            <div class="form-group"><label for="emsf_1997_c7c5f">الوحدة التنظيمية</label>
                <select name="org_unit_id" id="emsf_1997_c7c5f"><option value="0">—</option><?php foreach ($units as $x) { echo '<option value="' . intval($x['unit_id']) . '">' . htmlspecialchars($x['name_ar']) . '</option>'; } ?></select></div>
            <div class="form-group"><label for="emsf_1998_8bed3">المدير المباشر</label>
                <select name="manager_person_id" id="emsf_1998_8bed3"><option value="0">—</option><?php foreach ($usersList as $x) { echo '<option value="' . intval($x['id']) . '">' . htmlspecialchars($x['name']) . '</option>'; } ?></select></div>
        </div>
        <?php wz_step('⑦', 'النطاق التشغيلي — إلزامي'); ?>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
            <div class="form-group"><label for="emsf_1999_1cc51">نوع النطاق *</label>
                <select name="scope_type" required id="emsf_1999_1cc51">
                    <option value="site">موقع</option><option value="project">مشروع</option>
                    <option value="company">شركة</option><option value="shift">وردية</option>
                    <option value="own_records">سجلاته هو</option>
                </select></div>
            <div class="form-group"><label for="emsf_2000_8e88c">الموقع/المعرف *</label>
                <select name="scope_id" required id="emsf_2000_8e88c"><option value="0">0 (شركة/عام)</option>
                    <?php foreach ($sitesList as $s) { echo '<option value="' . intval($s['id']) . '">' . htmlspecialchars($s['name']) . '</option>'; } ?></select></div>
            <div class="form-group"><label for="emsf_2001_1f175">من * / إلى</label>
                <input type="date" name="valid_from" required style="margin-bottom:4px" id="emsf_2001_1f175"><input type="date" name="valid_to"></div>
        </div>
        <?php wz_step('⑧', 'التكليف (اختياري — بيته ORG-01)'); ?>
        <div class="form-group"><label for="emsf_2002_0f1ee">تكليف نافذ مرتبط</label>
            <select name="linked_asg" id="emsf_2002_0f1ee"><option value="">— بلا —</option>
                <?php foreach ($assignments as $a) { echo '<option value="' . intval($a['asg_id']) . '">#' . intval($a['asg_id']) . ' ' . htmlspecialchars($a['name_ar']) . '</option>'; } ?></select>
            <small style="color:#888">التكليف يُنشأ ويُدار من شاشة التكليفات — وهنا إحالة قراءة.</small></div>

        <div style="display:flex;gap:10px;margin-top:14px">
            <button type="submit" class="btn-save" onclick="document.getElementById('wzAction').value='preview'">⑨ معاينة الصلاحيات بمصادرها</button>
            <?php if ($can_add): ?>
            <button type="submit" class="btn-save" onclick="document.getElementById('wzAction').value='submit'"
                    style="background:#27ae60">⑩ إرسال للموافقة (والتفعيل ⑪ آلي بعدها)</button>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($preview !== null): ?>
    <div class="card"><div class="card-header"><h5>⑨ الصلاحيات المقترحة بمصادرها (<?php echo intval($preview['count']); ?>)</h5></div>
    <div class="card-body">
        <?php if (!$preview['proposed']) { ems_state_empty('لا محتوى منشورًا لقوالب هذه الطبقات بعد — تُبنى في شاشة القوالب'); } else { ?>
        <div class="table-container"><table class="alltables" style="width:100%" data-no-dt="1">
            <thead><tr><th>المصدر</th><th>المرجع</th><th>الصلاحية</th><th>الحكم</th><th>النطاق</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              </tr></thead>
            <tbody><?php foreach ($preview['proposed'] as $p) {
                echo '<tr><td>' . htmlspecialchars($p['kind']) . '</td><td>' . htmlspecialchars($p['ref'])
                    . '</td><td>' . htmlspecialchars($p['permission']) . '</td><td>'
                    . ($p['effect'] === 'deny' ? '<span style="color:#c0392b">منع</span>' : 'منح')
                    . '</td><td>' . htmlspecialchars((string) $p['scope']) . '</td></tr>';
            } ?></tbody></table></div>
        <?php } ?>
    </div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
