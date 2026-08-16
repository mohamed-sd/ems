<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Governance/signing_authority.php — التفويض بالتوقيع (LEG-01 §8-③ · الشاشة 207)
 * ───────────────────────────────────────────────────────────────────────────
 * باب الحوكمة (DEC-01 ②): المفوَّض والكيان والنوع والسقف والنطاق والمدة
 * والمستند · عرض المنتهية خلال 30 يومًا · **وتعيين مدير الحركة ونائبه هنا**
 * (DEC-01 ①): السقف نطاقي لا نقدي، والنائب بمرجع أصيله وبمدة مكتوبة إلزامًا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';

require_once __DIR__ . '/../includes/tenant_scope.php';   // نطاقُ الكيانِ من السياقِ لا من رقمٍ صلب
// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
require_once __DIR__ . '/../includes/screen_contract.php';
require_once dirname(__DIR__) . '/app/Core/AuthorityGuard.php';
require_once dirname(__DIR__) . '/app/Services/Governance/UnitStateChangeService.php';

use App\Services\Governance\UnitStateChangeService;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$role = strval($_SESSION['user']['role'] ?? '');
// الكتابة للإدارة العليا والمالية العليا (1 · 19 · السوبر) — وإدارة التمويل (26)
// قراءةً فقط (FIN-26: ملكية الشاشة للحوكمة والدور 26 يطالعها — DEC-01 ②)
$gov_write = ($role === '-1' || in_array($role, array('1', '19'), true));
if (!$gov_write && $role !== EMS_ROLE_FINANCING_MGR) {
    ems_gov_flash_redirect('../main/dashboard.php', 'باب الحوكمة خلف صلاحيته ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');
}
$co = ems_scope_company($conn);

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$gov_write) {
    ems_gov_flash_redirect('../main/dashboard.php', 'الدور قارئ هنا: الكتابة لملّاك الشاشة (1 · 19) ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $op = strval($_POST['op'] ?? '');
    if ($op === 'movement') {
        // DEC-01 ①: تفويض حركة — سقف نطاقي (موقع أو كل المواقع) · نائب بمدة
        $r = UnitStateChangeService::appointMovement($conn, $co, array(
            'person_id' => intval($_POST['person_id'] ?? 0),
            'entity_id' => intval($_POST['entity_id'] ?? 0),
            'site_id' => ($_POST['site_id'] ?? '') !== '' ? intval($_POST['site_id']) : null,
            'valid_from' => strval($_POST['valid_from'] ?? date('Y-m-d')),
            'valid_to' => strval($_POST['valid_to'] ?? ''),
            'doc_ref' => trim(strval($_POST['doc_ref'] ?? '')),
            'delegated_from_auth_id' => ($_POST['delegated_from'] ?? '') !== '' ? intval($_POST['delegated_from']) : null,
        ));
        if ($r['ok']) { $msg = $r['reason']; } else { $err = $r['reason']; }
    } elseif ($op === 'general') {
        $pid = intval($_POST['person_id'] ?? 0);
        $eid = intval($_POST['entity_id'] ?? 0);
        $type = strval($_POST['auth_type'] ?? 'general');
        $cap = ($_POST['amount_cap'] ?? '') !== '' ? floatval($_POST['amount_cap']) : null;
        $cur = trim(strval($_POST['currency'] ?? ''));
        $vf = strval($_POST['valid_from'] ?? date('Y-m-d'));
        $vt = ($_POST['valid_to'] ?? '') !== '' ? strval($_POST['valid_to']) : null;
        $doc = trim(strval($_POST['doc_ref'] ?? ''));
        if ($pid <= 0 || $eid <= 0 || $doc === '') {
            $err = 'المفوَّض والكيان والمستند إلزامية — لا تفويض شفوي';
        } elseif (!in_array($type, array('general', 'financial', 'contractual', 'banking', 'operational'), true)) {
            $err = 'نوع التفويض من القائمة';
        } else {
            $st = $conn->prepare("INSERT INTO signing_authorities (company_id, person_id, entity_id, auth_type, amount_cap, currency, valid_from, valid_to, doc_ref, state) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $st->bind_param('iiisdssss', $co, $pid, $eid, $type, $cap, $cur, $vf, $vt, $doc);
            if ($st->execute()) { $msg = 'تفويض #' . $st->insert_id . ' نافذ — وكل اعتماد سيوقَّع بمرجعه'; }
            else { $err = 'تعذّر: ' . $st->error; }
            $st->close();
        }
    }
}

$auths = $conn->query(
    "SELECT a.*, u.name person_name, e.legal_name entity_name,
            DATEDIFF(a.valid_to, CURDATE()) days_left
       FROM signing_authorities a
       LEFT JOIN users u ON u.id = a.person_id
       LEFT JOIN legal_entities e ON e.entity_id = a.entity_id
      WHERE a.company_id = {$co}
      ORDER BY (a.state = 'active') DESC, a.valid_to IS NULL DESC, a.valid_to"
)->fetch_all(MYSQLI_ASSOC);
$entities = $conn->query("SELECT entity_id, legal_name FROM legal_entities WHERE state = 'active' ORDER BY is_tenant DESC, legal_name")->fetch_all(MYSQLI_ASSOC);
$sites = $conn->query("SELECT id, name FROM sites ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$AUTH_AR = array('general' => 'عام', 'financial' => 'مالي', 'contractual' => 'تعاقدي', 'banking' => 'بنكي', 'operational' => 'تشغيلي (حركة — سقف نطاقي)');

$page_title = 'إيكوبيشن | التفويض بالتوقيع';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'التفويض بالتوقيع'; $header_icon = 'fa fa-file-signature';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('لا اعتماد بلا تفويض نافذ ساري — والتفويض بالصفة والكيان معًا لا بالشخص وحده، '
        . 'وينتهي بانتهاء مدته آليًّا. تفويض الحركة (DEC-01 ①) سقفه نطاقي لا نقدي: مواقع لا مبالغ، '
        . 'لأنه يعتمد وقوع الواقعة لا قيمتها — والنائب بمرجع أصيله وبمدة مكتوبة إلزامًا.',
        array('المنتهي خلال 30 يومًا بشارة', 'النائب لا يكون مفتوح المدة'));
    if ($msg !== '') { echo '<div class="alert alert-success">' . htmlspecialchars($msg) . '</div>'; }
    if ($err !== '') { echo '<div class="alert alert-danger">' . htmlspecialchars($err) . '</div>'; }
    ?>
    <div class="card"><div class="card-body">
        <div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">
        <thead><tr><th>#</th><th>المفوَّض</th><th>الكيان المفوِّض</th><th>نوع التفويض</th><th>السقف المالي</th><th>نطاق التفويض</th><th>نيابة عن</th><th>المدة</th><th>مستند التفويض</th><th>الحالة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم التفويض</th>
              <th class="ems-fn-th" data-fn="1">صفته</th>
              <th class="ems-fn-th" data-fn="1">توقيع مشترك مطلوب؟</th>
              <th class="ems-fn-th" data-fn="1">من تاريخ</th>
              <th class="ems-fn-th" data-fn="1">إلى تاريخ</th>
              <th class="ems-fn-th" data-fn="1">جهة التصديق</th>
              <th class="ems-fn-th" data-fn="1">أصدره</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead><tbody>
        <?php foreach ($auths as $a):
            $left = $a['days_left'];
            $expSoon = $left !== null && intval($left) >= 0 && intval($left) <= 30;
        ?>
        <tr>
            <td><?php echo intval($a['auth_id']); ?></td>
            <td><?php echo htmlspecialchars((string) ($a['person_name'] ?: ('#' . $a['person_id']))); ?></td>
            <td><?php echo htmlspecialchars((string) ($a['entity_name'] ?: ('#' . $a['entity_id']))); ?></td>
            <td><?php echo htmlspecialchars($AUTH_AR[$a['auth_type']] ?? $a['auth_type']); ?></td>
            <td><?php echo $a['amount_cap'] !== null ? number_format((float) $a['amount_cap'], 2) . ' ' . htmlspecialchars((string) $a['currency']) : ($a['auth_type'] === 'operational' ? 'نطاقي — لا نقدي' : 'بلا سقف (بقرار)'); ?></td>
            <td><?php echo $a['scope_type'] === 'site' ? 'الموقع #' . intval($a['scope_id']) : ($a['auth_type'] === 'operational' ? 'كل المواقع' : '—'); ?></td>
            <td><?php echo !empty($a['delegated_from_auth_id']) ? 'الأصيل #' . intval($a['delegated_from_auth_id']) : '—'; ?></td>
            <td><?php echo htmlspecialchars($a['valid_from'] . ' → ' . ($a['valid_to'] ?: 'مفتوح'));
                if ($expSoon) { echo ' <span class="badge badge-warning">ينتهي خلال ' . intval($left) . ' يومًا</span>'; } ?></td>
            <td><small><?php echo htmlspecialchars((string) $a['doc_ref']); ?></small></td>
            <td><span class="badge badge-<?php echo $a['state'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($a['state']); ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div></div>

    <?php if ($gov_write): // القارئ (26) لا تُصيَّر له نماذج التعيين والتفويض أصلًا — منع بنيوي لا زر معطَّل ?>
    <div class="card"><div class="card-body">
        <h4>تعيين مدير الحركة والتشغيل أو نائبه (DEC-01 ① — سقف نطاقي)</h4>
        <form method="post" class="ems-form" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">
        <?= csrf_field() ?>
            <input type="hidden" name="op" value="movement">
            <input type="number" name="person_id" placeholder="users.id للمعيَّن *" required aria-label="users.id للمعيَّن">
            <select name="entity_id" required>
                <option value="">— الكيان المفوِّض *</option>
                <?php foreach ($entities as $e): ?>
                <option value="<?php echo intval($e['entity_id']); ?>"><?php echo htmlspecialchars($e['legal_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="site_id">
                <option value="">كل المواقع (الأصيل)</option>
                <?php foreach ($sites as $s): ?>
                <option value="<?php echo intval($s['id']); ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="delegated_from" placeholder="نيابة عن تفويض # (للنائب)" aria-label="نيابة عن تفويض # (للنائب)">
            <input type="date" name="valid_from" value="<?php echo date('Y-m-d'); ?>" required>
            <input type="date" name="valid_to" title="إلزامي للنائب — لا نيابة مفتوحة المدة" aria-label="إلزامي للنائب — لا نيابة مفتوحة المدة">
            <input type="text" name="doc_ref" placeholder="مستند التعيين *" required aria-label="مستند التعيين">
            <button class="btn-primary" type="submit">تعيين — والسلسلة لا تتوقف بغيابه</button>
        </form>
    </div></div>

    <div class="card"><div class="card-body">
        <h4>تفويض عام (مالي · تعاقدي · بنكي) — بسقفه النقدي</h4>
        <form method="post" class="ems-form" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">
        <?= csrf_field() ?>
            <input type="hidden" name="op" value="general">
            <input type="number" name="person_id" placeholder="users.id للمفوَّض *" required aria-label="users.id للمفوَّض">
            <select name="entity_id" required>
                <option value="">— الكيان المفوِّض *</option>
                <?php foreach ($entities as $e): ?>
                <option value="<?php echo intval($e['entity_id']); ?>"><?php echo htmlspecialchars($e['legal_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="auth_type">
                <option value="financial">مالي</option><option value="contractual">تعاقدي</option>
                <option value="banking">بنكي</option><option value="general">عام</option>
            </select>
            <input type="number" step="0.01" name="amount_cap" placeholder="السقف المالي" aria-label="السقف المالي">
            <input type="text" name="currency" placeholder="العملة (USD…)" aria-label="العملة (USD…)">
            <input type="date" name="valid_from" value="<?php echo date('Y-m-d'); ?>" required>
            <input type="date" name="valid_to">
            <input type="text" name="doc_ref" placeholder="مستند التفويض *" required aria-label="مستند التفويض">
            <button class="btn-primary" type="submit" style="grid-column:span 4">إنشاء التفويض</button>
        </form>
    </div></div>
    <?php endif; ?>
</div>
<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
