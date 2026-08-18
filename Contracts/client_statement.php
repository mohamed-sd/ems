<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Contracts/client_statement.php — كشفُ حساب العميل بطبقاته (M-04)
 * ───────────────────────────────────────────────────────────────────────────
 * ENT-03 §6: «**كشفُ العميل بطبقاته** (مستخلصاتٌ · فواتيرُ · تحصيلاتٌ · محتجزٌ ·
 * مقدمةٌ · رصيد) **وكلُّ رقمٍ برابط مصدره**».
 *
 * قراءةٌ خالصة: لا زرَّ كتابةٍ واحدًا هنا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Revenue/ClientStatementService.php';

use App\Services\Revenue\ClientStatementService as CSS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$MODULE_CODE = 'Contracts/client_statement.php';
$can_view = false;
if ($is_super_admin) {
    $can_view = true;
} else {
    $st = $conn->prepare("SELECT rp.can_view FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) { $can_view = (intval($row['can_view']) === 1); }
    $st->close();
}
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض كشف حساب العميل ❌', 'GOV-PERM-403', '');
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('client statement super') : ems_tenant_db();

$clients = array();
try {
    $clients = $gate->scopedQuery(array('scope' => array('c' => 'clients')),
        "SELECT c.id, c.client_name FROM clients c
          WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0 ORDER BY c.client_name");
} catch (\Throwable $t) { $clients = array(); }

$selected = intval($_GET['client_id'] ?? 0);
if ($selected <= 0 && $clients) { $selected = intval($clients[0]['id']); }
$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])
        ? $_GET['from'] : date('Y-01-01');
$to   = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])
        ? $_GET['to'] : date('Y-12-31');

$stmt = $selected > 0 ? CSS::build($gate, $selected, $from, $to) : null;

$page_title = 'إيكوبيشن | كشف حساب العميل';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'كشف حساب العميل'; $header_icon = 'fa fa-receipt';
    $header_actions = array();
    $header_back = array('href' => 'claims.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'المستخلصات');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا حركاتٍ في كشفِ هذا العميلِ للفترة', 'وسّع الفترةَ أو اختر عميلًا آخر — والكشفُ يقرأ الفواتيرَ والتحصيلاتِ الحية');
    ?>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('client', 'الذمم'); ?>

    <div class="card"><div class="card-body">
        <form method="get" class="cst-filter">
            <strong>العميل:</strong>
            <select name="client_id" aria-label="العميل" class="cst-client-select" onchange="this.form.submit()">
                <?php foreach ($clients as $c): ?>
                    <option value="<?php echo intval($c['id']); ?>" <?php echo $selected === intval($c['id']) ? 'selected' : ''; ?>>
                        #<?php echo intval($c['id']); ?> — <?php echo htmlspecialchars((string)$c['client_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label for="emsf_21_8b3c3">من</label><input type="date" name="from" id="emsf_21_8b3c3" value="<?php echo htmlspecialchars($from); ?>">
            <label for="emsf_22_eabe1">إلى</label><input type="date" name="to" id="emsf_22_eabe1" value="<?php echo htmlspecialchars($to); ?>">
            <button type="submit" class="btn-primary"><i class="fa fa-filter"></i> اقرأ الكشف</button>
        </form>
        <p class="cst-note">
            <strong>كلُّ رقمٍ برابط مصدره</strong> — ومن لا مصدرَ له <strong>يُعلَن</strong> ولا يُخفى.
            و<strong>المحتجزُ والمقدمةُ كلٌّ في طبقته</strong> لا يُخلطان بالذمة الجارية (§4)،
            و<strong>الرصيدُ من الفواتير</strong> لا من المستخلصات — فالمستخلصُ اعترافٌ سابقٌ على المطالبة،
            وجمعُهما يحتسب الدَّينَ مرتين.
        </p>
    </div></div>

    <?php if ($stmt !== null): ?>
    <div class="card"><div class="card-body">
        <div class="cst-badges">
            <?php foreach (CSS::LAYER_LABELS as $k => $lbl): ?>
                <div class="badge badge-secondary cst-badge">
                    <?php echo htmlspecialchars($lbl); ?>:
                    <strong><?php echo htmlspecialchars((string)$stmt['totals'][$k]); ?></strong>
                </div>
            <?php endforeach; ?>
            <div class="badge cst-badge-balance <?php echo $stmt['totals']['balance'] > 0 ? 'badge-danger' : 'badge-success'; ?>">
                الرصيدُ الجاري: <strong><?php echo htmlspecialchars((string)$stmt['totals']['balance']); ?></strong>
            </div>
        </div>
        <?php foreach ($stmt['notes'] as $n): ?>
            <div class="alert alert-info cst-alert"><?php echo htmlspecialchars($n); ?></div>
        <?php endforeach; ?>
    </div></div>

    <?php foreach ($stmt['layers'] as $key => $layer): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-layer-group"></i>
        <?php echo htmlspecialchars($layer['label']); ?>
        — <?php echo count($layer['rows']); ?> سطرًا · المجموع
        <strong><?php echo htmlspecialchars((string)$layer['total']); ?></strong></h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap cst-w100">
            <thead><tr><th>تاريخ الإنشاء</th><th>البيان</th><th>المبلغ</th><th>العملة</th>
                <th>السياق</th><th>المصدر</th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">رقم الكشف</th>
                <th class="ems-fn-th" data-fn="1">العميل</th>
                <th class="ems-fn-th" data-fn="1">الفترة</th>
                <th class="ems-fn-th" data-fn="1">رصيد أول المدة</th>
                <th class="ems-fn-th" data-fn="1">فواتير الفترة</th>
                <th class="ems-fn-th" data-fn="1">إشعارات دائنة</th>
                <th class="ems-fn-th" data-fn="1">تحصيلات الفترة</th>
                <th class="ems-fn-th" data-fn="1">خصم المقدم</th>
                <th class="ems-fn-th" data-fn="1">محتجز الضمان</th>
                <th class="ems-fn-th" data-fn="1">رصيد آخر المدة</th>
                <th class="ems-fn-th" data-fn="1">المعادل بعملة الدفاتر</th>
                <th class="ems-fn-th" data-fn="1">أقدم فاتورة غير مسدَّدة</th>
                <th class="ems-fn-th" data-fn="1">أيام التأخر</th>
                <th class="ems-fn-th" data-fn="1">حالة مطابقة العميل</th>
                <th class="ems-fn-th" data-fn="1">أصدره</th>
                <th class="ems-fn-th" data-fn="1">اعتمده</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                </tr></thead>
            <tbody>
            <?php foreach ($layer['rows'] as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)$r['date']); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['description']); ?></td>
                    <td<?php echo $r['amount'] < 0 ? ' class="cst-credit"' : ''; ?>>
                        <?php echo htmlspecialchars((string)$r['amount']); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['currency']); ?></td>
                    <td><small><?php echo htmlspecialchars((string)$r['context']); ?></small></td>
                    <td><?php if ($r['orphan']): ?>
                            <span class="badge badge-warning">بلا مصدر</span>
                        <?php elseif ($r['link'] !== null): ?>
                            <a href="<?php echo htmlspecialchars($r['link']); ?>">افتح المصدر ▸</a>
                        <?php else: ?>
                            <small><?php echo htmlspecialchars($r['source_kind'] . ' #' . $r['source_ref']); ?></small>
                        <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<style>
    /* UXW-01 ①+②: الأنماطُ الموضعيةُ صارت أصنافًا والألوانُ من الرموز — القيمُ ذاتُها بلا style= */
    .cst-filter { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .cst-client-select { min-width:320px; }
    .cst-note { color:var(--c-666, #666); margin-top:10px; }
    .cst-badges { display:flex; gap:14px; flex-wrap:wrap; }
    .cst-badge { font-size:15px; padding:8px 14px; }
    .cst-badge-balance { font-size:16px; padding:8px 14px; }
    .cst-alert { margin:8px 0; }
    .cst-w100 { width:100%; }
    .cst-credit { color:var(--c-0a7, #0a7); }
</style>
</body>
</html>
