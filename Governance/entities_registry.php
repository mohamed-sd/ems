<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-667 · SCN-668 · SCN-669 · SCN-671 · SCN-672 · SCN-673
/**
 * Governance/entities_registry.php — سجل الكيانات (LEG-01 §8-① · الشاشة 206)
 * ───────────────────────────────────────────────────────────────────────────
 * باب الحوكمة (DEC-01 ②): الكيانات بصفاتها وتراخيصها وحالتها · شارة برتقالية
 * لترخيص ينتهي · فلتر الداخلي والخارجي · **الفرادة بالثلاثة معًا**
 * (البلد × جهة التسجيل × رقم السجل) · ولا يُتعاقد مع كيان غير نشط.
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

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$role = strval($_SESSION['user']['role'] ?? '');
// الكتابة للإدارة العليا والمالية العليا (1 · 19 · السوبر) — وإدارة التمويل (26)
// قراءةً فقط (FIN-26: ملكية الشاشة للحوكمة والدور 26 يطالعها — DEC-01 ②)
$gov_write = ($role === '-1' || in_array($role, array('1', '19'), true));
if (!$gov_write && $role !== EMS_ROLE_FINANCING_MGR) {
    ems_gov_flash_redirect('../main/dashboard.php', 'باب الحوكمة خلف صلاحيته ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');
}
if (!$gov_write && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ems_gov_flash_redirect('../main/dashboard.php', 'الدور قارئ هنا: الكتابة لملّاك الشاشة (1 · 19) ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');
}

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'create') {
    $name = trim(strval($_POST['legal_name'] ?? ''));
    $form = trim(strval($_POST['legal_form'] ?? ''));
    $country = trim(strval($_POST['country'] ?? ''));
    $authority = trim(strval($_POST['registry_authority'] ?? ''));
    $reg = trim(strval($_POST['commercial_reg'] ?? ''));
    $tax = trim(strval($_POST['tax_no'] ?? ''));
    $cur = trim(strval($_POST['base_currency'] ?? 'SDG'));
    $roleCode = strval($_POST['entity_role'] ?? '');
    if ($name === '' || $country === '' || $authority === '' || $reg === '') {
        $err = 'الاسم القانوني والبلد وجهة التسجيل ورقم السجل إلزامية — الفرادة بالثلاثة معًا';
    } elseif (!in_array($roleCode, array('holding', 'operating', 'project', 'client', 'supplier', 'financier', 'government'), true)) {
        $err = 'صفة الكيان من القائمة المحكومة';
    } else {
        $st = $conn->prepare('INSERT INTO legal_entities (legal_name, legal_form, country, registry_authority, commercial_reg, tax_no, base_currency, is_tenant, state) VALUES (?, ?, ?, ?, ?, ?, ?, 0, \'active\')');
        $st->bind_param('sssssss', $name, $form, $country, $authority, $reg, $tax, $cur);
        if (!$st->execute()) {
            $err = (strpos($st->error, 'Duplicate') !== false)
                ? 'كيان قائم بالثلاثية نفسها (البلد × الجهة × الرقم) — سجل واحد لا يتكرر'
                : 'تعذّر الإنشاء: ' . $st->error;
            $st->close();
        } else {
            $eid = intval($st->insert_id);
            $st->close();
            $st = $conn->prepare('INSERT INTO entity_roles (entity_id, role, valid_from) VALUES (?, ?, CURDATE())');
            $st->bind_param('is', $eid, $roleCode);
            $st->execute();
            $st->close();
            $msg = 'أُنشئ الكيان #' . $eid . ' بصفة «' . $roleCode . '» — والصفات جدول علاقة مؤرَّخ لا حقل نصي';
        }
    }
}

$filter = strval($_GET['f'] ?? 'all');
$where = '1=1';
if ($filter === 'internal') { $where = 'e.is_tenant = 1'; }
if ($filter === 'external') { $where = 'e.is_tenant = 0'; }
$rows = $conn->query(
    "SELECT e.*, GROUP_CONCAT(r.role ORDER BY r.role SEPARATOR ' · ') roles,
            (SELECT MIN(l.expiry_date) FROM entity_licenses l WHERE l.entity_id = e.entity_id AND l.state = 'active') next_expiry
       FROM legal_entities e
       LEFT JOIN entity_roles r ON r.entity_id = e.entity_id AND (r.valid_to IS NULL OR r.valid_to >= CURDATE())
      WHERE {$where}
      GROUP BY e.entity_id ORDER BY e.is_tenant DESC, e.legal_name"
)->fetch_all(MYSQLI_ASSOC);

$page_title = 'إيكوبيشن | سجل الكيانات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'سجل الكيانات'; $header_icon = 'fa fa-building-columns';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('كل كيان قانوني بسجله وصفاته وتراخيصه — سجل واحد لا يتكرر (الفرادة: البلد × جهة '
        . 'التسجيل × رقم السجل)، والعقد بلا كيان معرَّف ورقة لا حجة فيها. الكيان ليس مستأجرًا '
        . 'بالضرورة: is_tenant لكيانات المجموعة وحدها.',
        array('ابحث قبل أي إنشاء', 'الترخيص المنتهي بشارة'));
    if ($msg !== '') { echo '<div class="alert alert-success">' . htmlspecialchars($msg) . '</div>'; }
    if ($err !== '') { echo '<div class="alert alert-danger">' . htmlspecialchars($err) . '</div>'; }
    ?>
    <div class="card"><div class="card-body">
        <div style="margin-bottom:10px">
            فلتر: <a href="?f=all">الكل</a> · <a href="?f=internal">كياناتنا (المستأجرة)</a> · <a href="?f=external">الأطراف الخارجية</a>
        </div>
        <div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">
        <thead><tr><th>الكيان</th><th>الثلاثية (بلد · جهة · سجل)</th><th>الصفات</th><th>العملة الأساسية</th><th>داخلي؟</th><th>أقرب انتهاء ترخيص</th><th>الحالة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">كود الكيان</th>
              <th class="ems-fn-th" data-fn="1">الاسم القانوني الكامل</th>
              <th class="ems-fn-th" data-fn="1">الشكل النظامي</th>
              <th class="ems-fn-th" data-fn="1">بلد التسجيل</th>
              <th class="ems-fn-th" data-fn="1">جهة التسجيل</th>
              <th class="ems-fn-th" data-fn="1">رقم السجل</th>
              <th class="ems-fn-th" data-fn="1">الرقم الضريبي</th>
              <th class="ems-fn-th" data-fn="1">العنوان المسجَّل</th>
              <th class="ems-fn-th" data-fn="1">تاريخ التأسيس</th>
              <th class="ems-fn-th" data-fn="1">كيان مجموعة؟</th>
              <th class="ems-fn-th" data-fn="1">اكتمال الملكية</th>
              <th class="ems-fn-th" data-fn="1">سجّله</th>
              <th class="ems-fn-th" data-fn="1">تاريخ التسجيل</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              </tr></thead><tbody>
        <?php foreach ($rows as $e):
            $exp = $e['next_expiry'];
            $expSoon = $exp !== null && strtotime($exp) < strtotime('+30 days');
        ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($e['legal_name']); ?></strong><br><small>#<?php echo intval($e['entity_id']) . ' · ' . htmlspecialchars((string) $e['legal_form']); ?></small></td>
            <td><small><?php echo htmlspecialchars($e['country'] . ' · ' . $e['registry_authority'] . ' · ' . $e['commercial_reg']); ?></small></td>
            <td><?php echo htmlspecialchars((string) $e['roles']); ?></td>
            <td><?php echo htmlspecialchars((string) $e['base_currency']); ?></td>
            <td><?php echo intval($e['is_tenant']) === 1 ? 'نعم — حد عزل' : '—'; ?></td>
            <td><?php if ($exp === null): ?>—<?php else: ?>
                <span class="badge badge-<?php echo $expSoon ? 'warning' : 'info'; ?>"><?php echo htmlspecialchars($exp); ?></span>
            <?php endif; ?></td>
            <td><span class="badge badge-<?php echo $e['state'] === 'active' ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($e['state']); ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div></div>

    <?php if ($gov_write): // القارئ (26) لا يُصيَّر له نموذج الإنشاء أصلًا — منع بنيوي لا زر معطَّل ?>
    <div class="card"><div class="card-body">
        <h4>كيان جديد — ابحث بالثلاثية أولًا فالسجل واحد لا يتكرر</h4>
        <form method="post" class="ems-form" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">
            <input type="hidden" name="op" value="create">
            <input type="text" name="legal_name" placeholder="الاسم القانوني الكامل *" required aria-label="الاسم القانوني الكامل">
            <input type="text" name="legal_form" placeholder="الشكل النظامي (ذ.م.م …)" aria-label="الشكل النظامي (ذ.م.م …)">
            <input type="text" name="country" placeholder="البلد *" required aria-label="البلد">
            <input type="text" name="registry_authority" placeholder="جهة التسجيل *" required aria-label="جهة التسجيل">
            <input type="text" name="commercial_reg" placeholder="رقم السجل التجاري *" required aria-label="رقم السجل التجاري">
            <input type="text" name="tax_no" placeholder="الرقم الضريبي" aria-label="الرقم الضريبي">
            <input type="text" name="base_currency" placeholder="العملة الأساسية" value="SDG" aria-label="العملة الأساسية">
            <select name="entity_role" required>
                <option value="">— الصفة *</option>
                <option value="client">عميل</option><option value="supplier">مورد</option>
                <option value="financier">ممول</option><option value="operating">تشغيلية</option>
                <option value="holding">قابضة</option><option value="project">مشروعية</option>
                <option value="government">جهة حكومية</option>
            </select>
            <button class="btn-primary" type="submit" style="grid-column:span 4">إنشاء الكيان بصفته</button>
        </form>
    </div></div>
    <?php endif; ?>
</div>
<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
