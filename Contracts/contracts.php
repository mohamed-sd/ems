<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: ../login.php");
  exit();
}

require_once '../config.php';
require_once '../includes/permissions_helper.php';

if (!headers_sent()) {
  header('Content-Type: text/html; charset=UTF-8');
}

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$project_has_company_id = db_table_has_column($conn, 'project', 'company_id');
$mines_has_company_id = db_table_has_column($conn, 'mines', 'company_id');
$mines_has_is_deleted = db_table_has_column($conn, 'mines', 'is_deleted');
$mines_has_deleted_at = db_table_has_column($conn, 'mines', 'deleted_at');

if (!$is_super_admin && $company_id <= 0) {
  ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
  exit();
}

$contracts_has_company = db_table_has_column($conn, 'contracts', 'company_id');
$contracts_has_is_deleted = db_table_has_column($conn, 'contracts', 'is_deleted');
$contracts_has_deleted_at = db_table_has_column($conn, 'contracts', 'deleted_at');
$contracts_has_deleted_by = db_table_has_column($conn, 'contracts', 'deleted_by');

if (!$contracts_has_is_deleted || !$contracts_has_deleted_at || !$contracts_has_deleted_by) {
  $alter_parts = array();
  if (!$contracts_has_is_deleted) {
    $alter_parts[] = "ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0";
  }
  if (!$contracts_has_deleted_at) {
    $alter_parts[] = "ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL";
  }
  if (!$contracts_has_deleted_by) {
    $alter_parts[] = "ADD COLUMN deleted_by INT(11) NULL DEFAULT NULL";
  }
  if (!empty($alter_parts)) {
    ems_runtime_ddl($conn, "ALTER TABLE contracts " . implode(', ', $alter_parts), 'Contracts/contracts.php');
  }

  $contracts_has_is_deleted = db_table_has_column($conn, 'contracts', 'is_deleted');
  $contracts_has_deleted_at = db_table_has_column($conn, 'contracts', 'deleted_at');
  $contracts_has_deleted_by = db_table_has_column($conn, 'contracts', 'deleted_by');
}

$contract_not_deleted_sql = '1=1';
if ($contracts_has_is_deleted) {
  $contract_not_deleted_sql = 'c.is_deleted = 0';
} elseif ($contracts_has_deleted_at) {
  $contract_not_deleted_sql = 'c.deleted_at IS NULL';
}

$contract_not_deleted_plain_sql = '1=1';
if ($contracts_has_is_deleted) {
  $contract_not_deleted_plain_sql = 'is_deleted = 0';
} elseif ($contracts_has_deleted_at) {
  $contract_not_deleted_plain_sql = 'deleted_at IS NULL';
}

if (empty($_SESSION['contracts_csrf_token'])) {
  $_SESSION['contracts_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}
$contracts_csrf_token = $_SESSION['contracts_csrf_token'];

$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$filter_project_id = isset($_GET['filter_project_id']) ? intval($_GET['filter_project_id']) : 0;
if ($project_id > 0) {
  $filter_project_id = $project_id;
}

// بوابة العزل — تستبدل سُلَّمَ النطاق اليدوي (وفيه احتياطي created_by القديم):
// contracts لها company_id مقيسةً فالعزل مباشرٌ عبر البوابة؛ فلترُ غير المحذوف
// يبقى صريحًا في الاستعلامات (scopedQuery لا يضيف فلاتر الحذف الناعم بنفسه).
$contracts_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('contracts super view') : ems_tenant_db();

// ════════════════════════════════════════════════════════════════════════════
//  التحقق من صلاحيات المستخدم
// ════════════════════════════════════════════════════════════════════════════
$page_permissions = check_page_permissions($conn, 'Contracts/contracts.php');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

// منع الوصول إذا لم تكن صلاحية عرض
if (!$can_view) {
  ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض العقود ❌', 'GOV-PERM-403', '');
  exit();
}

/* ══ INJ-0001 (P0) — أفعالُ آلةِ حالةِ العقدِ في شاشتها المالكة ═══════════════
   كانت `ContractStateMachine` (اثنتا عشرةَ حالةً) مبنيةً كاملةً ومستدعيها
   **الوحيدُ** شاشةُ القمة `Portal/ceo_contracts.php:167`؛ وهذه الشاشةُ — الأمُّ
   للإدارة — لا تذكر `contract_status` ولا مرة. فالدورةُ «مسودة ← تفاوض ←
   معتمد» لا بابَ لها. الآن فعلان محروسان بالعقدِ السبعيّ:
     ① رفعٌ للتفاوض   (مسودة → تفاوض)
     ② اعتمادٌ         (تفاوض → معتمد) **بحارسِ سقفٍ يُصعّد عند التجاوز**
   والمعتمدُ يظهر فورًا في قائمةِ التوقيعِ لدى القمة (تستعلم 'معتمد'). */
require_once __DIR__ . '/../includes/post_contract.php';
require_once __DIR__ . '/../app/Services/Contract/ContractApprovalService.php';
require_once __DIR__ . '/../app/Services/Contract/ContractStateMachine.php';

$csm_msg = '';
$csm_svc = new \App\Services\Contract\ContractApprovalService($conn);

$__pcNeg = ems_post_contract($conn, array(
    'action'  => 'contract.submit_negotiation',
    'perm'    => 'can_edit',
    'trigger' => 'csm_submit_id',
    'idem'    => array('contract' => intval($_POST['csm_submit_id'] ?? 0), 'to' => 'تفاوض'),
    'validate' => function (array $in) {
        $cid = intval($in['csm_submit_id'] ?? 0);
        if ($cid <= 0) { return array('ok' => false, 'msg' => 'عقدٌ غيرُ صالح (422)'); }
        return array('ok' => true, 'data' => array('cid' => $cid, 'note' => trim($in['csm_note'] ?? '')));
    },
));
if (!$__pcNeg['ok'] && $__pcNeg['msg'] !== '') { $csm_msg = $__pcNeg['msg']; }
if ($__pcNeg['replay'])                        { $csm_msg = $__pcNeg['msg']; }
if ($__pcNeg['run'] && $__pcNeg['ok']) {
    $r = $csm_svc->submitForNegotiation($contracts_gate, $company_id,
        (int) $__pcNeg['data']['cid'], (string) $__pcNeg['data']['note'],
        intval($_SESSION['user']['id'] ?? 0));
    $csm_msg = ($r['ok'] ? '✅ ' : '❌ ') . $r['reason'] . ' (' . $r['code'] . ')';
    if ($r['ok']) { ems_pc_idem_mark($conn, $__pcNeg['idem'], $__pcNeg['code'], 'contracts#' . (int) $__pcNeg['data']['cid']); }
}

$__pcApp = ems_post_contract($conn, array(
    'action'  => 'contract.approve',
    'perm'    => 'can_edit',
    'trigger' => 'csm_approve_id',
    'idem'    => array('contract' => intval($_POST['csm_approve_id'] ?? 0), 'to' => 'معتمد'),
    'validate' => function (array $in) {
        $cid = intval($in['csm_approve_id'] ?? 0);
        if ($cid <= 0) { return array('ok' => false, 'msg' => 'عقدٌ غيرُ صالح (422)'); }
        return array('ok' => true, 'data' => array('cid' => $cid, 'note' => trim($in['csm_note'] ?? '')));
    },
));
if (!$__pcApp['ok'] && $__pcApp['msg'] !== '') { $csm_msg = $__pcApp['msg']; }
if ($__pcApp['replay'])                        { $csm_msg = $__pcApp['msg']; }
if ($__pcApp['run'] && $__pcApp['ok']) {
    $r = $csm_svc->approve($contracts_gate, $company_id,
        (int) $__pcApp['data']['cid'], (string) $__pcApp['data']['note'],
        intval($_SESSION['user']['id'] ?? 0), intval($current_role));
    $csm_msg = ($r['ok'] ? '✅ ' : ($r['escalated'] ? '⬆ ' : '❌ ')) . $r['reason'] . ' (' . $r['code'] . ')';
    if ($r['ok']) { ems_pc_idem_mark($conn, $__pcApp['idem'], $__pcApp['code'], 'contracts#' . (int) $__pcApp['data']['cid']); }
}

/* ══ INJ-0026 · دورةُ الحياةِ كاملةً لا فعلين منها ═══════════════════════════
     نصُّ القبول: «**كلُّ فعلٍ من الثمانية** ينقل `contract_status` إلى حالةٍ
     مشروعةٍ فقط، وينشر حدثًا واحدًا، ويكتب صفَّ تدقيقٍ بقيمةٍ قبل وبعد، **وله
     فعلُ عكسٍ** يعيد الحالةَ السابقة».
     والمقيسُ قبلَه: فعلانِ من ثمانية — فالعقدُ يبلغ «معتمد» ثم **يقف**: لا
     توقيعَ ولا إنفاذَ ولا إنهاءَ ولا إقفال، والآلةُ التي تعرف الطريقَ كلَّه
     مبنيةٌ ولا يُنادى منها إلا بابان.
   ◆ والأفعالُ تُقرأ من `ContractLifecycleActions` — سجلٌّ واحدٌ مشتقٌّ من جدولِ
     الانتقالاتِ نفسِه. فإن تغيّر الجدولُ تغيّرت الأزرارُ معه ولا تتفرّقان.
   ◆ والحكمُ يبقى في الآلةِ والخدمة (CS-05): هذه الكتلةُ تُرسل ولا تحكم. */
require_once __DIR__ . '/../app/Services/Contract/ContractLifecycleActions.php';
$__clcReg = \App\Services\Contract\ContractLifecycleActions::registry('customer');
$__pcLc = ems_post_contract($conn, array(
    'action'  => 'contract.lifecycle',
    'perm'    => 'can_edit',
    'trigger' => 'clc_action',
    'idem'    => array('contract' => intval($_POST['clc_contract_id'] ?? 0),
                       'act'      => strval($_POST['clc_action'] ?? '')),
    'validate' => function (array $in) use ($__clcReg) {
        $cid = intval($in['clc_contract_id'] ?? 0);
        $act = trim(strval($in['clc_action'] ?? ''));
        if ($cid <= 0)              { return array('ok' => false, 'msg' => 'عقدٌ غيرُ صالح (422)'); }
        if (!isset($__clcReg[$act])) { return array('ok' => false, 'msg' => 'فعلٌ غيرُ مُعلَنٍ في سجلِّ دورةِ الحياة (422)'); }
        return array('ok' => true, 'data' => array('cid' => $cid, 'act' => $act,
                                                   'note' => trim(strval($in['clc_note'] ?? ''))));
    },
));
if (!$__pcLc['ok'] && $__pcLc['msg'] !== '') { $csm_msg = $__pcLc['msg']; }
if ($__pcLc['replay'])                        { $csm_msg = $__pcLc['msg']; }
if ($__pcLc['run'] && $__pcLc['ok']) {
    $__r = \App\Services\Contract\ContractLifecycleActions::run(
        $conn, $contracts_gate, $company_id, 'customer',
        (int) $__pcLc['data']['cid'], (string) $__pcLc['data']['act'],
        (string) $__pcLc['data']['note'], intval($_SESSION['user']['id'] ?? 0), intval($current_role));
    $csm_msg = ($__r['ok'] ? '✅ ' : '❌ ') . $__r['reason'] . ' (' . $__r['code'] . ')';
    if ($__r['ok']) { ems_pc_idem_mark($conn, $__pcLc['idem'], $__pcLc['code'], 'contracts#' . (int) $__pcLc['data']['cid']); }
}

// أنواع المعدات — كتالوج عام (managed) عبر البوابة
$equipmentTypes = $contracts_gate->select('equipments_types', array(
  'columns'  => array('id', 'type'),
  'whereRaw' => "status = 'active'",
  'orderBy'  => 'type ASC',
));

$equipmentTypeOptionsHtml = '<option value="">— اختر —</option>';
foreach ($equipmentTypes as $equipmentType) {
  $typeId = (int) $equipmentType['id'];
  $typeName = htmlspecialchars($equipmentType['type'], ENT_QUOTES, 'UTF-8');
  $equipmentTypeOptionsHtml .= '<option value="' . $typeId . '">' . $typeName . '</option>';
}

// مشاريع الشركة (نموذج الإضافة + الفلتر) — معزولةً عبر البوابة (سُلَّم النطاق القديم أُزيل)
$form_projects = $contracts_gate->select('project', array(
  'columns'  => array('id', 'name'),
  'whereRaw' => 'status = 1',
  'orderBy'  => 'name ASC',
));

// جلب قائمة المشاريع للفلتر من جدول المشاريع مباشرة (نفس مصدر النموذج)
$projects_filter_options = array();
foreach ($form_projects as $project_filter_row) {
  $projects_filter_options[intval($project_filter_row['id'])] = $project_filter_row['name'];
}

// (تم إزالة فلتر المناجم - العقود ترتبط مباشرة بالمشروع)
?>
<?php
$page_title = "إيكوبيشن | العقود";
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include("../inheader.php");
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main contracts-main ems-unified-page-shell">

  <?php
  // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
  $header_title = 'العقود';
  $header_icon = 'fas fa-file-contract';
  $header_actions = array();
  if ($can_add) {
    $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'عقد جديد');
  }
  // ── نظام Excel الموحّد (Unified Excel Framework) ──
  require_once __DIR__ . '/../includes/excel_ui.php';
  foreach (ems_excel_header_actions('contracts', 'عقود المشاريع', $can_add) as $__xlAction) {
    $header_actions[] = $__xlAction;
  }
  $header_back = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
  include('../includes/page_header.php');
  ?>

  <?php echo ems_states_bundle('لا عقودَ ضمن هذا الترشيح', 'وسّع الفترةَ أو غيّر المرشِّحات'); ?>

  <?php if ($csm_msg !== ''): ?>
    <!-- INJ-0001: حصيلةُ نقلِ الحالة — والتصعيدُ يُعلَن بلونٍ ثالثٍ لا يُخلط بالفشل -->
    <div class="alert <?= (mb_strpos($csm_msg, '✅') !== false ? 'alert-success'
                          : (mb_strpos($csm_msg, '⬆') !== false ? 'alert-warning' : 'alert-danger')) ?>">
      <?= htmlspecialchars($csm_msg, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <!-- فورم إضافة عقد -->
  <form id="projectForm" action="" method="post" class="allforms">
     <div class="card-header">
        <h5>
          <i class="fas fa-file-signature"></i> إضافة / تعديل العقد
        </h5>
      </div>
    <div class="card">
      <div class="card-body">

        <input type="hidden" name="id" id="contract_id" value="">
        <input type="hidden" name="csrf_token"
          value="<?php echo htmlspecialchars($contracts_csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="form-section">
          <h6><i class="fas fa-file-contract"></i> اختيار المشروع </h6>
          <div class="form-grid">
            <div class="field md-6 sm-6">
              <label for="contract_project_id">المشروع <font color="red">*</font></label>
              <div class="control">
                <select name="project_id" id="contract_project_id" required>
                  <option value="">— اختر المشروع —</option>
                  <?php foreach ($form_projects as $project): ?>
                    <option value="<?php echo intval($project['id']); ?>" <?php echo ($filter_project_id > 0 && intval($project['id']) === $filter_project_id) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6"></div>
          </div>
        </div>
        <!-- القسم 1: إجماليات الساعات (يومياً وللعقد) -->
        <div class="form-section">
          <h6><i class="fas fa-file-contract"></i> إجماليات الساعات (يومياً وللعقد)</h6>
          <div class="totals">
            <div class="kpi">
              <div class="v" id="kpi_month_total">0</div>
              <div class="t">الهدف الشهري للساعات</div>
              <input type="hidden" name="hours_monthly_target" id="hours_monthly_target" value="0" />
            </div>
            <div class="kpi">
              <div class="v" id="kpi_contract_total">0</div>
              <div class="t">إجمالي ساعات العقد</div>
              <input type="hidden" name="forecasted_contracted_hours" id="forecasted_contracted_hours" value="0" />
            </div>
            <div class="kpi">
              <div class="v" id="kpi_equip_month">0</div>
              <div class="t">الساعات اليومية المطلوبة</div>
            </div>
          </div>
        </div>

        <div class="contracts-note-box">
          <p class="contracts-note-text">
            <i class="fas fa-info-circle"></i> <strong>ملاحظة:</strong> يتم حساب الإجماليات تلقائياً بناءً على
            البيانات المدخلة في الأقسام التالية
          </p>
        </div>
        <br />

        <div class="form-section">
          <h6><i class="fas fa-file-contract"></i> البيانات الأساسية للعميل والعقد</h6>
          <div class="form-grid">
            <!-- صف 1: 3 خانات -->
            <div class="field md-3 sm-6">
              <label>تاريخ توقيع العقد </label>
              <div class="control"><input name="contract_signing_date" id="contract_signing_date" type="date" aria-label="تاريخ توقيع العقد"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>فترة السماح بين التوقيع والتنفيذ </label>
              <div class="control"><input name="grace_period_days" id="grace_period_days" type="number" min="0"
                  placeholder="عدد الأيام"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>بداية التنفيذ الفعلي المتفق عليه</label>
              <div class="control"><input name="actual_start" id="actual_start" type="date" aria-label="بداية التنفيذ الفعلي المتفق عليه"></div>
            </div>


            <div class="field md-3 sm-6">
              <label>نهاية التنفيذ الفعلي المتفق عليه</label>
              <div class="control"><input name="actual_end" id="actual_end" type="date" aria-label="نهاية التنفيذ الفعلي المتفق عليه"></div>
            </div>



            <!-- خانتان فارغتان -->


            <!-- صف 2: 3 خانات -->

            <div class="field md-3 sm-6">
              <label>مدة العقد بالأيام </label>
              <div class="control"><input name="contract_duration_days" id="contract_duration_days" type="number"
                  min="0" placeholder="يُحتسب تلقائياً" readonly></div>
            </div>





            <div class="field md-3 sm-6">
              <label>العملة</label>
              <div class="control">
                <select name="price_currency_contract" id="price_currency_contract" aria-label="عملة العقد">
                  <option value="">— اختر —</option>
                  <option value="دولار">دولار</option>
                  <option value="جنيه">جنيه</option>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>المبلغ المدفوع</label>
              <div class="control"><input name="paid_contract" type="text" aria-label="المبلغ المدفوع"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>وقت الدفع</label>
              <div class="control">
                <select name="payment_time" id="payment_time" aria-label="وقت الدفع">
                  <option value="">— اختر —</option>
                  <option value="مقدم">مقدم</option>
                  <option value=" مؤخر">مؤخر </option>

                </select>
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label> الضمانات</label>
              <div class="control"><input name="guarantees" type="text" aria-label="الضمانات"></div>
            </div>

            <div class="field md-3 sm-6">
              <label> تاريخ الدفع</label>
              <div class="control"><input name="payment_date" id="payment_date" type="date" aria-label="تاريخ الدفع"></div>
            </div>











            <div class="field md-3 sm-6">
              <label>عدد الورديات للعقد </label>
              <div class="control"><input name="equip_shifts_contract" type="number" min="0" placeholder="مثال: 2">
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label> ساعات الوردية للعقد</label>
              <div class="control"><input name="shift_contract" type="number" min="0" aria-label="ساعات الوردية للعقد"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>إجمالي الوحدات يومياً للعقد </label>
              <div class="control"><input name="equip_total_contract" type="number" placeholder=" "></div>
            </div>
            <div class="field md-3 sm-6">
              <label>وحدات العمل في الشهر للعقد</label>
              <div class="control"><input name="total_contract_permonth" type="number" min="0" aria-label="وحدات العمل في الشهر للعقد"></div>
            </div>


            <div class="field md-3 sm-6">
              <label>إجمالي وحدات العقد </label>
              <div class="control"><input name="total_contract" type="number" placeholder=" "></div>
            </div>

            <div class="field md-3 sm-6">
              <label>مدراء الموقع </label>
              <div class="control"><input type="number" name="daily_operators" id="daily_operators" min="0"
                  placeholder="مثال: 3"></div>
            </div>



            <div class="field md-3 sm-6">
              <label>الترحيل (Transportation)</label>
              <div class="control">
                <select name="transportation" id="transportation" aria-label="جهة توفير الترحيل">
                  <option value="">— اختر —</option>
                  <option value="مالك المعدة">مالك المعدة</option>
                  <option value="مالك المشروع">مالك المشروع</option>
                  <option value="بدون">بدون</option>
                </select>
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label>السكن (Place for Living)</label>
              <div class="control">
                <select name="place_for_living" id="place_for_living" aria-label="جهة توفير السكن">
                  <option value="">— اختر —</option>
                  <option value="مالك المعدة">مالك المعدة</option>
                  <option value="مالك المشروع">مالك المشروع</option>
                  <option value="بدون">بدون</option>
                </select>
              </div>
            </div>
            <!-- صف 3: 3 خانات -->
            <div class="field md-3 sm-6">
              <label>الإعاشة (Accommodation)</label>
              <div class="control">
                <select name="accommodation" id="accommodation" aria-label="جهة توفير الإعاشة">
                  <option value="">— اختر —</option>
                  <option value="مالك المعدة">مالك المعدة</option>
                  <option value="مالك المشروع">مالك المشروع</option>
                  <option value="بدون">بدون</option>
                </select>
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label>الورشة (Workshop)</label>
              <div class="control">
                <select name="workshop" id="workshop" aria-label="جهة توفير الورشة">
                  <option value="">— اختر —</option>
                  <option value="مالك المعدة">مالك المعدة</option>
                  <option value="مالك المشروع">مالك المشروع</option>
                  <option value="بدون">بدون</option>
                </select>
              </div>
            </div>
            <!-- خانتان فارغتان -->
            <div class="field md-3 sm-6"> </div>
            <div class="field md-3 sm-6"> </div>
          </div>
        </div>



        <!-- القسم 3: بيانات ساعات العمل المطلوبة للمعدات -->
        <div class="form-section">
          <h6><i class="fas fa-file-contract"></i> بيانات ساعات العمل المطلوبة <strong>للمعدات</strong> </h6>
          <div id="equipmentSections">
            <div class="equipment-section" data-index="1">
              <div class="equipment-card">
                <h6 class="equipment-card-title">المعدات رقم 1</h6>
                <div class="form-grid">
                  <div class="field md-3 sm-6">
                    <label>نوع المعدة</label>
                    <div class="control">
                      <select name="equip_type_1" class="equip-type" aria-label="نوع المعدة">
                        <?php echo $equipmentTypeOptionsHtml; ?>
                      </select>
                    </div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>عدد المعدات</label>
                    <div class="control"><input name="equip_count_1" type="number" min="0" aria-label="عدد المعدات"></div>
                  </div>

                  <div class="field md-3 sm-6">
                    <label><span class="equip-basic-mark">■</span> المعدات الأساسية</label>
                    <div class="control"><input name="equip_count_basic_1" type="number" min="0"
                        class="equip-basic-input" aria-label="عدد المعدات الأساسية"></div>
                  </div>

                  <div class="field md-3 sm-6">
                    <label><span class="equip-backup-mark">■</span> المعدات الاحتياطية</label>
                    <div class="control"><input name="equip_count_backup_1" type="number" min="0"
                        class="equip-backup-input" aria-label="عدد المعدات الاحتياطية"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>عدد المشغلين</label>
                    <div class="control"><input name="equip_operators_1" type="number" min="0" aria-label="عدد المشغلين"></div>
                  </div>


                  <div class="field md-3 sm-6">
                    <label>عدد المساعدين</label>
                    <div class="control"><input name="equip_assistants_1" type="number" min="0" aria-label="عدد المساعدين"></div>
                  </div>


                  <div class="field md-3 sm-6">
                    <label>عدد الورديات</label>
                    <div class="control"><input name="equip_shifts_1" type="number" min="0" placeholder="مثال: 2"></div>
                  </div>
                  <!-- أوقات الورديات -->
                  <div class="field md-3 sm-6">
                    <label><i class="fas fa-clock"></i> بداية الوردية الأولى</label>
                    <div class="control"><input name="shift1_start_1" type="time" placeholder="مثال: 08:00"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label><i class="fas fa-clock"></i> نهاية الوردية الأولى</label>
                    <div class="control"><input name="shift1_end_1" type="time" placeholder="مثال: 16:00"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label><i class="fas fa-clock"></i> بداية الوردية الثانية</label>
                    <div class="control"><input name="shift2_start_1" type="time" placeholder="مثال: 16:00"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label><i class="fas fa-clock"></i> نهاية الوردية الثانية</label>
                    <div class="control"><input name="shift2_end_1" type="time" placeholder="مثال: 00:00"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>وحدة القياس</label>
                    <div class="control">
                      <select name="equip_unit_1" class="equip-unit" aria-label="وحدة القياس">
                        <option value="">— اختر —</option>
                        <option value="ساعة">ساعة</option>
                        <option value="طن">طن</option>
                        <option value="متر طولي">متر طولي</option>
                        <option value="متر مكعب">متر مكعب</option>
                      </select>
                    </div>
                  </div>

                  <div class="field md-3 sm-6">
                    <label>ساعات الوردية</label>
                    <div class="control"><input name="shift_hours_1" type="number" min="0" aria-label="ساعات الوردية"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>إجمالي الوحدات يومياً</label>
                    <div class="control"><input name="equip_total_month_1" type="number" readonly
                        placeholder="يُحتسب تلقائياً"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>وحدات العمل في الشهر</label>
                    <div class="control"><input name="equip_target_per_month_1" type="number" min="0" aria-label="وحدات العمل في الشهر"></div>
                  </div>


                  <div class="field md-3 sm-6">
                    <label>إجمالي وحدات العقد</label>
                    <div class="control"><input name="equip_total_contract_1" type="number" readonly
                        placeholder="يُحتسب تلقائياً"></div>
                  </div>


                  <div class="field md-3 sm-6">
                    <label>العملة</label>
                    <div class="control">
                      <select name="equip_price_currency_1" aria-label="عملة التسعير">
                        <option value="">— اختر —</option>
                        <option value="دولار">دولار</option>
                        <option value="جنيه">جنيه</option>
                      </select>
                    </div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>السعر\للوحدة</label>
                    <div class="control"><input name="equip_price_1" type="number" min="0" step="0.01"
                        placeholder="0.00">
                    </div>
                  </div>


                  <!-- خانتان فارغتان للحفاظ على 3 خانات لكل صف -->

                  <div class="field md-3 sm-6">
                    <label>عدد المشرفين</label>
                    <div class="control"><input name="equip_supervisors_1" type="number" min="0" aria-label="عدد المشرفين"></div>
                  </div>

                  <div class="field md-3 sm-6">
                    <label>عدد الفنيين</label>
                    <div class="control"><input name="equip_technicians_1" type="number" min="0" aria-label="عدد الفنيين"></div>
                  </div>
                  <!-- إكمال الصف بثلاث خانات -->
                  <div class="field md-3 sm-6"></div>
                  <div class="field md-3 sm-6"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="equipment-add-row">
          <button type="button" class="primary" id="addEquipmentBtn" class="equipment-add-btn">
            <i class="fas fa-plus-circle"></i> إضافة مزيد من المعدات
          </button>
        </div>

        <div class="form-section">
          <h6><i class="fas fa-file-contract"></i> بيانات إضافية </h6>


          <div class="form-grid">

            <div class="field md-3 sm-6 contracts-hidden-field">
              <label>عدد ساعات العمل اليومية <font color="red"> * مهم </font></label>
              <div class="control"><input type="number" id="daily_work_hours" name="daily_work_hours" min="0"
                  placeholder="مثال: 8" value="20"></div>
            </div>
            <!-- Orgnization Break  -->



            <div class="field md-3 sm-6">
              <label>الطرف الأول </label>
              <div class="control"><input type="text" name="first_party" id="first_party"
                  placeholder="اسم الطرف الاول ">
              </div>
            </div>



            <div class="field md-3 sm-6">
              <label>الطرف الثاني </label>
              <div class="control"><input type="text" name="second_party" id="second_party"
                  placeholder="اسم الطرف الثاني ">
              </div>
            </div>

            <!-- <div class="field md-3 sm-6"> </div> -->

            <div class="field md-3 sm-6">
              <label>الشاهد الأول</label>
              <div class="control"><input type="text" name="witness_one" id="witness_one"
                  placeholder="اسم الشاهد الأول">
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>الشاهد الثاني</label>
              <div class="control"><input type="text" name="witness_two" id="witness_two"
                  placeholder="اسم الشاهد الثاني">
              </div>
            </div>
          </div>
          </div>


          <div class="pu-form-actions">
            <button type="submit" class="btn-primary">
              <i class="fas fa-save"></i> حفظ البيانات
            </button>
            <button type="button" id="contractFormCancelBtn" class="btn-secondary">
              <i class="fas fa-times"></i> إلغاء
            </button>
          </div>
        </div>
      </div>
  </form>


  <form method="get" action="contracts.php">
    <div class="filter">
      <div class="filter-title">
        <span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span>
        فلاتر البحث
      </div>
      <div class="filter-body">
        <div class="filter-field">
          <label for="filter_project_select"><i class="fa fa-calendar"></i> فلتر المشروع</label>
          <select name="filter_project_id" id="filter_project_select" class="form-control">
            <option value="0">كل المشاريع</option>
            <?php foreach ($projects_filter_options as $project_option_id => $project_option_name): ?>
              <option value="<?php echo intval($project_option_id); ?>" <?php echo ($filter_project_id === intval($project_option_id)) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($project_option_name, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- كرّر .filter-field بقدر ما تريد من الحقول -->
        <div class="filter-actions">
          <button type="submit" class="btn-primary"><i class="fa fa-search"></i> تطبيق</button>
          <button type="button" class="btn-secondary" title="إعادة تعيين"><a href="contracts.php" class="ct-1"><i class="fa fa-rotate-right"></i></a></button>
        </div>
      </div>
    </div>
  </form>

  <div class="card">
    <div class="card-body">
      <!-- أزرار التحكم في المجموعات -->
      <div class="card-body">
        <div class="contracts-group-toolbar">
          <span class="contracts-group-toolbar-label">
            <i class="fas fa-filter"></i> عرض المجموعات:
          </span>
          <button class="ems-btn-group-toggle active" data-group="basic" title="المعلومات الأساسية">
            <i class="fas fa-info-circle"></i> أساسية
          </button>
          <button class="ems-btn-group-toggle active" data-group="dates" title="التواريخ والمدد">
            <i class="far fa-calendar"></i> تواريخ
          </button>
          <button class="ems-btn-group-toggle active" data-group="hours" title="الساعات والأهداف">
            <i class="fas fa-clock"></i> ساعات
          </button>
          <button class="ems-btn-group-toggle" data-group="parties" title="أطراف العقد">
            <i class="fas fa-users"></i> أطراف
          </button>
          <button class="ems-btn-group-toggle" data-group="services" title="الخدمات المقدمة">
            <i class="fas fa-hands-helping"></i> خدمات
          </button>
          <button class="ems-btn-group-toggle" data-group="operations" title="التشغيل اليومي">
            <i class="fas fa-cogs"></i> تشغيل
          </button>
          <button class="ems-btn-group-toggle active" data-group="status" title="الحالة والإجراءات">
            <i class="fas fa-check-circle"></i> حالة
          </button>
          <button class="ems-btn-group-toggle-all" title="إظهار/إخفاء الكل">
            <i class="fas fa-eye"></i> الكل
          </button>
        </div>
      </div>



      <div class="card-body contracts-table-wrap table-container">
        <table id="projectsTable" class="display nowrap contracts-table contracts-table-nowrap">
          <thead>
            <tr>
              <th class="group-status"> الإجراءات</th>
              <!-- INJ-0001: حالةُ العقدِ في آلةِ الحالة — عمودٌ حاكمٌ كان غائبًا -->
              <th class="group-status"> حالة العقد</th>
              <th class="group-status ems-lc-cell"> نقلُ الحالة</th>
              <!-- المعلومات الأساسية -->
              <th class="group-basic"> رقم العقد</th>
              <th class="group-basic"> المشروع</th>

              <!-- التواريخ والمدد -->
              <th class="group-dates"> تاريخ التوقيع</th>
              <th class="group-dates"> مدة السماح (أيام)</th>
              <th class="group-dates"> مدة العقد (أيام)</th>
              <th class="group-dates"> بداية التنفيذ</th>
              <th class="group-dates"> نهاية التنفيذ</th>

              <!-- الساعات والأهداف -->
              <th class="group-hours"> هدف ساعات شهري</th>
              <th class="group-hours"> إجمالي ساعات متوقعة</th>

              <!-- أطراف العقد -->
              <th class="group-parties"> الطرف الأول</th>
              <th class="group-parties"> الطرف الثاني</th>
              <th class="group-parties"> شاهد أول</th>
              <th class="group-parties"> شاهد ثاني</th>

              <!-- الخدمات المقدمة -->
              <th class="group-services"> النقل</th>
              <th class="group-services"> السكن</th>
              <th class="group-services"> مكان المعيشة</th>
              <th class="group-services"> الورشة</th>

              <!-- التشغيل اليومي -->
              <th class="group-operations"> ساعات العمل يومياً</th>
              <th class="group-operations"> عدد المشغلين يومياً</th>

              <!-- البيانات المالية -->
              <th class="group-basic"> عملة التسعير</th>
              <th class="group-basic"> المبلغ المدفوع</th>
              <th class="group-basic"> وقت الدفع</th>
              <th class="group-basic"> الضمانات</th>
              <th class="group-basic"> تاريخ الدفع</th>

              <!-- الحالة والإجراءات -->
              <th class="group-status"> الحالة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th none" data-fn="1">نموذج العمل</th>
              <th class="ems-fn-th none" data-fn="1">رمز النموذج</th>
              <th class="ems-fn-th none" data-fn="1">العقد الأساسي</th>
              <th class="ems-fn-th none" data-fn="1">رقم التجديد</th>
              <th class="ems-fn-th none" data-fn="1">العميل</th>
              <th class="ems-fn-th none" data-fn="1">تاريخ البدء</th>
              <th class="ems-fn-th none" data-fn="1">تاريخ الانتهاء</th>
              <th class="ems-fn-th none" data-fn="1">المدة بالأشهر</th>
              <th class="ems-fn-th none" data-fn="1">نموذج التسعير</th>
              <th class="ems-fn-th none" data-fn="1">نوع القيمة</th>
              <th class="ems-fn-th none" data-fn="1">القيمة الموقَّعة</th>
              <th class="ems-fn-th none" data-fn="1">عملة الفوترة</th>
              <th class="ems-fn-th none" data-fn="1">عملة التحصيل</th>
              <th class="ems-fn-th none" data-fn="1">دورة التسوية</th>
              <th class="ems-fn-th none" data-fn="1">مهلة السداد</th>
              <th class="ems-fn-th none" data-fn="1">نسبة المقدم</th>
              <th class="ems-fn-th none" data-fn="1">شامل الضريبة؟</th>
              <th class="ems-fn-th none" data-fn="1">حالة خط الأساس</th>
              <th class="ems-fn-th none" data-fn="1">ملاحظات حرجة مفتوحة</th>
              <th class="ems-fn-th none" data-fn="1">جاهزية الاعتماد القانوني</th>
              <th class="ems-fn-th none" data-fn="1">وقّعه عنّا</th>
              <th class="ems-fn-th none" data-fn="1">وقّعه العميل</th>
              <th class="ems-fn-th none" data-fn="1">نسخة القاعدة المستعملة</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
              <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              </tr>
          </thead>
          <tbody>
            <?php
            include 'contractequipments_handler.php';

            if (isset($_GET['delete_id'])) {
              $delete_id = intval($_GET['delete_id']);
              $delete_csrf = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';

              if (!$can_delete) {
                ems_gov_flash_redirect('contracts.php', 'لا توجد صلاحية حذف العقود ❌', 'GOV-PERM-403', '');
                exit;
              }

              if (empty($delete_csrf) || !hash_equals($contracts_csrf_token, $delete_csrf)) {
                ems_gov_flash_redirect('contracts.php', 'جلسة الحذف غير صالحة ❌', 'GOV-FAIL-409', '');
                exit;
              }

              if (!$contracts_has_is_deleted && !$contracts_has_deleted_at) {
                ems_gov_flash_redirect('contracts.php', 'تعذر تفعيل الحذف الناعم للعقود ❌', 'GOV-FAIL-409', '');
                exit;
              }

              // حذفٌ ناعمٌ معزولٌ عبر البوابة (status='0' + أعمدة الحذف؛ NOW() → توقيت PHP)
              $delete_set = array('status' => '0');
              if ($contracts_has_is_deleted) {
                $delete_set['is_deleted'] = 1;
              }
              if ($contracts_has_deleted_at) {
                $delete_set['deleted_at'] = date('Y-m-d H:i:s');
              }
              if ($contracts_has_deleted_by) {
                $delete_set['deleted_by'] = intval(isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 0);
              }

              try {
                $contracts_gate->update('contracts', $delete_set, array('id' => $delete_id), $contract_not_deleted_plain_sql);
              } catch (\Throwable $e) { /* غير مملوك/سياق ناقص → لا تغيير */ }
              echo "<script>window.location.href='contracts.php';</script>";
              exit;
            }

            // إضافة عقد جديد عند إرسال الفورم
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['project_id'])) {

              $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
              if (empty($posted_csrf) || !hash_equals($contracts_csrf_token, $posted_csrf)) {
                ems_gov_flash_redirect('contracts.php', '❌ جلسة النموذج غير صالحة', 'GOV-SCOPE-403', '');
                exit;
              }

              $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
              $posted_project_id = intval($_POST['project_id']);

              if (!$is_super_admin && $posted_project_id > 0) {
                // فحص ملكية المشروع ضمن نطاق العزل (يستبدل سُلَّم created_by القديم)
                $owned_project = $contracts_gate->selectOne('project', array(
                  'columns' => array('id'),
                  'where'   => array('id' => $posted_project_id),
                ));
                if ($owned_project === null) {
                  ems_gov_flash_redirect('contracts.php', '❌ لا يمكنك الوصول إلى هذا المشروع', 'GOV-SCOPE-403', '');
                  exit;
                }
              }

              if ($id > 0 && !$can_edit) {
                ems_gov_flash_redirect('contracts.php', 'لا توجد صلاحية تعديل العقود ❌', 'GOV-PERM-403', '');
                exit;
              } elseif ($id <= 0 && !$can_add) {
                ems_gov_flash_redirect('contracts.php', 'لا توجد صلاحية إضافة عقود جديدة ❌', 'GOV-PERM-403', '');
                exit;
              }


              $contract_signing_date = $_POST['contract_signing_date'];
              $grace_period_days = $_POST['grace_period_days'];

              // حساب مدة العقد بالأيام من تاريخ البداية والنهاية
              $actual_start = $_POST['actual_start'];
              $actual_end = $_POST['actual_end'];

              // حساب عدد الأيام من تاريخ البداية إلى تاريخ الانتهاء (شامل يوم البداية ويوم النهاية)
              if (!empty($actual_start) && !empty($actual_end)) {
                $start_date = new DateTime($actual_start);
                $end_date = new DateTime($actual_end);
                $interval = $start_date->diff($end_date);
                // توحيد المنطق مع الواجهة: الفرق الفعلي بين التاريخين بدون إضافة يوم إضافي
                $contract_duration_days = $interval->days;
                // ملء مدة العقد بالأشهر (نفس صيغة معالج التجديد) لتفادي صفر/القسمة على صفر في التقارير
                $contract_duration_months = ($interval->y * 12) + $interval->m;
              } else {
                $contract_duration_days = 0;
                $contract_duration_months = 0;
              }

              $transportation = $_POST['transportation'];
              $accommodation = $_POST['accommodation'];
              $place_for_living = $_POST['place_for_living'];
              $workshop = $_POST['workshop'];

              $hours_monthly_target = $_POST['hours_monthly_target'];
              $forecasted_contracted_hours = $_POST['forecasted_contracted_hours'];

              $daily_work_hours = $_POST['daily_work_hours'];
              $daily_operators = $_POST['daily_operators'];
              $first_party = $_POST['first_party'];
              $second_party = $_POST['second_party'];
              $witness_one = $_POST['witness_one'];
              $witness_two = $_POST['witness_two'];

              // (الحقول المالية تُقرأ خامًا داخل مصفوفة البيانات أدناه — الهروب مسؤولية البوابة)

              // الحقول الإضافية للعقد
              $equip_shifts_contract = isset($_POST['equip_shifts_contract']) ? intval($_POST['equip_shifts_contract']) : 0;
              $shift_contract = isset($_POST['shift_contract']) ? intval($_POST['shift_contract']) : 0;
              $equip_total_contract_daily = isset($_POST['equip_total_contract']) ? intval($_POST['equip_total_contract']) : 0;
              $total_contract_permonth = isset($_POST['total_contract_permonth']) ? intval($_POST['total_contract_permonth']) : 0;
              $total_contract_units = isset($_POST['total_contract']) ? intval($_POST['total_contract']) : 0;


              // بيانات العقد بقيمٍ خام (الربط بالمعاملات يتكفّل بالهروب — كان التعديل
              // يُضمِّن POST مباشرةً في النص!). company_id تُحقن آليًّا عند الإدراج.
              $contract_data = array(
                'contract_signing_date'       => $contract_signing_date,
                'grace_period_days'           => $grace_period_days,
                'contract_duration_days'      => $contract_duration_days,
                'contract_duration_months'    => $contract_duration_months,
                'equip_shifts_contract'       => $equip_shifts_contract,
                'shift_contract'              => $shift_contract,
                'equip_total_contract_daily'  => $equip_total_contract_daily,
                'total_contract_permonth'     => $total_contract_permonth,
                'total_contract_units'        => $total_contract_units,
                'actual_start'                => $actual_start,
                'actual_end'                  => $actual_end,
                'transportation'              => $transportation,
                'accommodation'               => $accommodation,
                'place_for_living'            => $place_for_living,
                'workshop'                    => $workshop,
                'hours_monthly_target'        => $hours_monthly_target,
                'forecasted_contracted_hours' => $forecasted_contracted_hours,
                'daily_work_hours'            => $daily_work_hours,
                'daily_operators'             => $daily_operators,
                'first_party'                 => $first_party,
                'second_party'                => $second_party,
                'witness_one'                 => $witness_one,
                'witness_two'                 => $witness_two,
                'price_currency_contract'     => isset($_POST['price_currency_contract']) ? $_POST['price_currency_contract'] : '',
                'paid_contract'               => isset($_POST['paid_contract']) ? $_POST['paid_contract'] : '',
                'payment_time'                => isset($_POST['payment_time']) ? $_POST['payment_time'] : '',
                'guarantees'                  => isset($_POST['guarantees']) ? $_POST['guarantees'] : '',
                'payment_date'                => isset($_POST['payment_date']) ? $_POST['payment_date'] : '',
              );

              $result = false;
              $contract_id = 0;
              try {
                if ($id > 0) {
                  // تعديل (project_id لا يتغيّر كالأصل؛ فلترُ غير المحذوف صريح)
                  $contracts_gate->update('contracts', $contract_data, array('id' => $id), $contract_not_deleted_plain_sql);
                  $contract_id = $id;
                  $result = true;
                } else {
                  // إضافة
                  $contract_data['project_id'] = $posted_project_id;
                  /* ══ **العقدُ يُنسَب إلى موقعٍ من نقطةِ إنشائه.**
                       ثابتٌ يحرسه `sites_entity_test`: «كلُّ العقودِ القائمةِ
                       مربوطةٌ بموقع». وكانت هذه الشاشةُ **لا تكتب `site_id`
                       مطلقًا** (صفرُ ذكرٍ للعمودِ في الملفِّ كلِّه) ولا تفتح
                       نطاقًا تشغيليًّا — فالعقدُ يُنشأ بلا موقعٍ في **أيٍّ** من
                       التمثيلين. (مقيسٌ: ثمانيةُ عقودٍ بلا `site_id` وبصفرِ
                       نطاق، ومشروعُها **له** موقعٌ افتراضيٌّ قائم.)
                     ◆ والموقعُ **مشتقٌّ لا مُختار**: افتراضيُّ مشروعِ العقد.
                       وإن لم يكن للمشروعِ افتراضيٌّ يبقى `null` — فلا يُخترع. */
                  if ($posted_project_id > 0) {
                      try {
                          $__ds = $contracts_gate->selectOne('sites', array(
                              'columns' => array('id'),
                              'where'   => array('project_id' => (int) $posted_project_id,
                                                 'is_default' => 1),
                          ));
                          if ($__ds && (int) $__ds['id'] > 0) {
                              $contract_data['site_id'] = (int) $__ds['id'];
                          }
                      } catch (\Throwable $t) {
                          error_log('contract default site (project ' . $posted_project_id . '): ' . $t->getMessage());
                      }
                  }
                  $contract_id = (int) $contracts_gate->insert('contracts', $contract_data);
                  $result = $contract_id > 0;
                  /* والنطاقُ الرئيسُ يُفتح من النقطةِ نفسِها — فالتمثيلان
                     يُكتبان معًا ولا يفترقان بمرورِ الوقت. */
                  if ($result && !empty($contract_data['site_id'])) {
                      try {
                          require_once dirname(__DIR__) . '/app/Services/Contract/ContractSiteService.php';
                          \App\Services\Contract\ContractSiteService::add(
                              $conn, $contracts_gate, $company_id, $contract_id,
                              array('site_id' => (int) $contract_data['site_id'], 'is_primary' => 1),
                              isset($current_user_id) ? (int) $current_user_id : 0);
                      } catch (\Throwable $t) {
                          error_log('contract primary scope #' . $contract_id . ': ' . $t->getMessage());
                      }
                  }
                }
              } catch (\Throwable $e) {
                $result = false;
              }

              $eq_saved = true; // [ح-8] مرجع حفظ المعدات — يُستشار قبل التوجيه
              if ($result) {

                // جمع بيانات المعدات من الفورم
                $equipment_array = [];
                $i = 1;
                // البحث عن أكبر index موجود
                $max_index = 0;
                foreach ($_POST as $key => $value) {
                  if (preg_match('/equip_type_(\d+)/', $key, $matches)) {
                    $max_index = max($max_index, (int) $matches[1]);
                  }
                }

                // جمع البيانات من جميع الأقسام
                for ($i = 1; $i <= $max_index; $i++) {
                  if (isset($_POST["equip_type_$i"]) && !empty($_POST["equip_type_$i"])) {
                    $equipment_array[] = [
                      'equip_type' => intval($_POST["equip_type_$i"]),
                      'equip_size' => isset($_POST["equip_size_$i"]) ? $_POST["equip_size_$i"] : 0,
                      'equip_count' => isset($_POST["equip_count_$i"]) ? $_POST["equip_count_$i"] : 0,
                      'equip_count_basic' => isset($_POST["equip_count_basic_$i"]) ? intval($_POST["equip_count_basic_$i"]) : 0,
                      'equip_count_backup' => isset($_POST["equip_count_backup_$i"]) ? intval($_POST["equip_count_backup_$i"]) : 0,
                      'equip_shifts' => isset($_POST["equip_shifts_$i"]) ? $_POST["equip_shifts_$i"] : 0,
                      'equip_unit' => isset($_POST["equip_unit_$i"]) ? $_POST["equip_unit_$i"] : '',
                      'shift1_start' => isset($_POST["shift1_start_$i"]) ? $_POST["shift1_start_$i"] : '',
                      'shift1_end' => isset($_POST["shift1_end_$i"]) ? $_POST["shift1_end_$i"] : '',
                      'shift2_start' => isset($_POST["shift2_start_$i"]) ? $_POST["shift2_start_$i"] : '',
                      'shift2_end' => isset($_POST["shift2_end_$i"]) ? $_POST["shift2_end_$i"] : '',
                      'shift_hours' => isset($_POST["shift_hours_$i"]) ? $_POST["shift_hours_$i"] : 0,
                      'equip_total_month' => isset($_POST["equip_total_month_$i"]) ? $_POST["equip_total_month_$i"] : 0,
                      'equip_monthly_target' => isset($_POST["equip_target_per_month_$i"]) ? $_POST["equip_target_per_month_$i"] : 0,
                      'equip_total_contract' => isset($_POST["equip_total_contract_$i"]) ? $_POST["equip_total_contract_$i"] : 0,
                      'equip_price' => isset($_POST["equip_price_$i"]) ? $_POST["equip_price_$i"] : 0,
                      'equip_price_currency' => isset($_POST["equip_price_currency_$i"]) ? $_POST["equip_price_currency_$i"] : '',
                      'equip_operators' => isset($_POST["equip_operators_$i"]) ? $_POST["equip_operators_$i"] : 0,
                      'equip_supervisors' => isset($_POST["equip_supervisors_$i"]) ? $_POST["equip_supervisors_$i"] : 0,
                      'equip_technicians' => isset($_POST["equip_technicians_$i"]) ? $_POST["equip_technicians_$i"] : 0,
                      'equip_assistants' => isset($_POST["equip_assistants_$i"]) ? $_POST["equip_assistants_$i"] : 0
                    ];
                  }
                }

                // إضافة بيانات المعدات الجديدة
                if (!empty($equipment_array)) {
                  include('contractequipments_handler.php');
                  $eq_saved = saveContractEquipments($contract_id, $equipment_array, $conn);
                }
              }

              // [ح-8] التوجيه مشروطٌ بالنجاح الفعلي — فشلُ العقد أو معداته يُبلَّغ لا يُبتلَع
              if ($result && $eq_saved) {
                echo "<script>window.location.href='contracts.php?id=$posted_project_id';</script>";
              } else {
                $ems_save_err = !$result ? 'تعذّر حفظ بيانات العقد — لم يُحفظ' : 'حُفظ العقد لكن فشل حفظ بعض المعدات — يرجى مراجعتها';
                ems_gov_flash_redirect("contracts.php?id=$posted_project_id", "❌ " . $ems_save_err . "", 'GOV-SCOPE-403', '');
              }
              exit;
            }

            // جلب العقود مع بيانات المشروع — معزولةً عبر البوابة ({TENANT_SCOPE})،
            // وفلترُ غير المحذوف صريح (scopedQuery لا يضيف فلاتر الحذف الناعم)
            $contracts_extra  = " AND $contract_not_deleted_sql";
            $contracts_params = array();
            if ($filter_project_id > 0) {
              $contracts_extra .= " AND c.project_id = ?";
              $contracts_params[] = $filter_project_id;
            }

            $contract_rows = $contracts_gate->scopedQuery(array(
              'scope'  => array('c' => 'contracts'),
              'enrich' => array('p' => 'project'),
            ), "SELECT c.*, p.name AS project_name, p.id AS project_id_val
                      FROM `contracts` c
                      LEFT JOIN project p ON c.project_id = p.id
                      WHERE {TENANT_SCOPE}$contracts_extra
                      ORDER BY c.id DESC", $contracts_params);
            $i = 1;


            {
              foreach ($contract_rows as $row) {

                // عرض حالة العقد من status
                $contractStatus = isset($row['status']) ? $row['status'] : 1;
                $statusColor = 'green';
                $statusText = 'ساري';
                if ($contractStatus == 1) {
                  $statusColor = 'green';
                  $statusText = 'ساري';
                } else {
                  $statusColor = 'red';
                  $statusText = 'غير ساري';
                }
                $status = "<font color='" . $statusColor . "'>" . $statusText . "</font>";
                $actions_html = "<div class='action-btns'>";
                $actions_html .= "<a href='contracts_details.php?id=" . $row['id'] . "' class='action-btn view' title='عرض التفاصيل'><i class='fas fa-eye'></i></a>";
                if ($can_edit) {
                  $actions_html .= "<a href='javascript:void(0)' class='editBtn action-btn edit'
             data-id='" . $row['id'] . "'
               data-project_id='" . (isset($row['project_id']) ? intval($row['project_id']) : 0) . "'
             data-contract_signing_date='" . $row['contract_signing_date'] . "'
             data-grace_period_days='" . $row['grace_period_days'] . "'
             data-contract_duration_days='" . (isset($row['contract_duration_days']) ? $row['contract_duration_days'] : 0) . "'
             data-actual_start='" . $row['actual_start'] . "'
             data-actual_end='" . $row['actual_end'] . "'
             data-hours_monthly_target='" . $row['hours_monthly_target'] . "'
             daily_work_hours ='" . $row['daily_work_hours'] . "'
              daily_operators ='" . $row['daily_operators'] . "'
               first_party ='" . $row['first_party'] . "'
                second_party ='" . $row['second_party'] . "'
                 witness_one ='" . $row['witness_one'] . "'
                  witness_two ='" . $row['witness_two'] . "'
                  transportation ='" . $row['transportation'] . "'
                  accommodation ='" . $row['accommodation'] . "'
                  place_for_living ='" . $row['place_for_living'] . "'
                  workshop ='" . $row['workshop'] . "'
                  equip_shifts_contract ='" . (isset($row['equip_shifts_contract']) ? $row['equip_shifts_contract'] : 0) . "'
                  shift_contract ='" . (isset($row['shift_contract']) ? $row['shift_contract'] : 0) . "'
                  equip_total_contract_daily ='" . (isset($row['equip_total_contract_daily']) ? $row['equip_total_contract_daily'] : 0) . "'
                  total_contract_permonth ='" . (isset($row['total_contract_permonth']) ? $row['total_contract_permonth'] : 0) . "'
                  total_contract_units ='" . (isset($row['total_contract_units']) ? $row['total_contract_units'] : 0) . "'
                  price_currency_contract ='" . (isset($row['price_currency_contract']) ? $row['price_currency_contract'] : '') . "'
                  paid_contract ='" . (isset($row['paid_contract']) ? $row['paid_contract'] : '') . "'
                  payment_time ='" . (isset($row['payment_time']) ? $row['payment_time'] : '') . "'
                  guarantees ='" . (isset($row['guarantees']) ? $row['guarantees'] : '') . "'
                  payment_date ='" . (isset($row['payment_date']) ? $row['payment_date'] : '') . "'

             data-forecasted_contracted_hours='" . $row['forecasted_contracted_hours'] . "'
             title='تعديل'><i class='fas fa-edit'></i></a>";
                }

                if ($can_delete) {
                  $delete_project_id = $project_id > 0 ? $project_id : intval(isset($row['project_id']) ? $row['project_id'] : 0);
                  $actions_html .= "<a href='contracts.php?id=" . $delete_project_id . "&delete_id=" . intval($row['id']) . "&csrf_token=" . urlencode($contracts_csrf_token) . "' onclick='return confirm(\"هل أنت متأكد؟\")' class='action-btn delete' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                }
                $actions_html .= "</div>";



                echo "<tr>";
                echo "<td class='group-status'>" . $actions_html . "</td>";

                /* INJ-0001: حالةُ العقدِ ونقلُها — من آلةِ الحالةِ لا من نصٍّ حر.
                   والأفعالُ تظهر **بحسب ما تسمح به الآلةُ من الحالةِ الراهنة**
                   (`allowedFrom`) لا بحسب رأي الشاشة. */
                $csm_state = (string) ($row['contract_status'] ?? '');
                if ($csm_state === '') { $csm_state = \App\Services\Contract\ContractStateMachine::DRAFT; }
                $csm_allowed = \App\Services\Contract\ContractStateMachine::allowedFrom($csm_state);
                echo "<td class='group-status'><span class='badge'>"
                   . htmlspecialchars($csm_state, ENT_QUOTES, 'UTF-8') . "</span></td>";

                /* ══ INJ-0026 · الأفعالُ الثمانيةُ كلُّها من السجلِّ الواحد ═══════
                     كان يُصيَّر فعلانِ بشرطين مكتوبَينِ يدويًّا — فالعقدُ يبلغ
                     «معتمد» ثم يقف بلا بابٍ للتوقيعِ ولا للإنفاذِ ولا للإنهاء.
                     والآن تُصيَّر **كلُّ الأفعالِ المشروعةِ من الحالةِ الراهنة**
                     بحسب `ContractLifecycleActions` — فزيادةُ حالةٍ في الآلةِ
                     تظهر زرًّا بلا لمسِ هذه الشاشة.
                   ◆ **وفعلُ العكسِ يُعرض مع فعلِه**: من يرى «اعتماد» يرى معه أنَّ
                     له عكسًا («إعادةٌ للتفاوض»)؛ ومن لا عكسَ له يحمل زرُّه سببَ
                     الامتناعِ في `title` — فلا يبحث المستخدمُ عن زرٍّ لا وجودَ له. */
                $csm_cell = '';
                if ($can_edit) {
                    $cid = intval($row['id']);
                    $__acts = \App\Services\Contract\ContractLifecycleActions::availableFor('customer', $csm_state);
                    foreach ($__acts as $__code => $__a) {
                        $__rv = \App\Services\Contract\ContractLifecycleActions::reverseOf('customer', $__code);
                        $__tip = $__a['label'] . ' — '
                               . ($__rv['has'] ? ('له عكسٌ: ' . $__rv['label'])
                                               : ('لا عكسَ له: ' . (string) $__rv['why']));
                        $__needNote = (!empty($__a['needs']) && $__a['needs'] === 'note');
                        $csm_cell .= "<form method='post' class='ems-inline-form' data-ems-c='ct-2'>" . csrf_field()
                          . "<input type='hidden' name='clc_contract_id' value='{$cid}'>"
                          . "<input type='hidden' name='clc_action' value='" . htmlspecialchars($__code, ENT_QUOTES, 'UTF-8') . "'>"
                          . ($__needNote
                              ? "<input type='text' name='clc_note' required minlength='3' class='ems-note-inline'"
                                . " placeholder='السبب'>"
                              : '')
                          . "<button class='action-btn ems-lc-btn' type='submit' title='"
                          . htmlspecialchars($__tip, ENT_QUOTES, 'UTF-8') . "'>"
                          . htmlspecialchars($__a['label'], ENT_QUOTES, 'UTF-8')
                          . ($__rv['has'] ? '' : ' <small>⛒</small>')
                          . "</button></form> ";
                    }
                }
                if ($csm_cell === '') { $csm_cell = "<span class='text-muted'>—</span>"; }
                echo "<td class='group-status ems-lc-cell'>" . $csm_cell . "</td>";

                // المعلومات الأساسية
                echo "<td class='group-basic'> " . $row['id'] . "#</td>";
                echo "<td class='group-basic'>" . (isset($row['project_name']) && $row['project_name'] !== '' ? $row['project_name'] : '-') . "</td>";

                // التواريخ والمدد
                echo "<td class='group-dates'>" . $row['contract_signing_date'] . "</td>";
                echo "<td class='group-dates'>" . (isset($row['grace_period_days']) ? $row['grace_period_days'] : 0) . "</td>";
                echo "<td class='group-dates'>" . (isset($row['contract_duration_days']) ? $row['contract_duration_days'] : 0) . "</td>";
                echo "<td class='group-dates'>" . $row['actual_start'] . "</td>";
                echo "<td class='group-dates'>" . $row['actual_end'] . "</td>";

                // الساعات والأهداف
                echo "<td class='group-hours'>" . $row['hours_monthly_target'] . "</td>";
                echo "<td class='group-hours'>" . $row['forecasted_contracted_hours'] . "</td>";

                // أطراف العقد
                echo "<td class='group-parties'>" . (isset($row['first_party']) ? $row['first_party'] : '-') . "</td>";
                echo "<td class='group-parties'>" . (isset($row['second_party']) ? $row['second_party'] : '-') . "</td>";
                echo "<td class='group-parties'>" . (isset($row['witness_one']) ? $row['witness_one'] : '-') . "</td>";
                echo "<td class='group-parties'>" . (isset($row['witness_two']) ? $row['witness_two'] : '-') . "</td>";

                // الخدمات المقدمة
                $transportationText = isset($row['transportation']) && $row['transportation'] ? $row['transportation'] : '-';
                $accommodationText = isset($row['accommodation']) && $row['accommodation'] ? $row['accommodation'] : '-';
                $place_for_livingText = isset($row['place_for_living']) && $row['place_for_living'] ? $row['place_for_living'] : '-';
                $workshopText = isset($row['workshop']) && $row['workshop'] ? $row['workshop'] : '-';

                echo "<td class='group-services'>" . $transportationText . "</td>";
                echo "<td class='group-services'>" . $accommodationText . "</td>";
                echo "<td class='group-services'>" . $place_for_livingText . "</td>";
                echo "<td class='group-services'>" . $workshopText . "</td>";

                // التشغيل اليومي
                echo "<td class='group-operations'>" . (isset($row['daily_work_hours']) ? $row['daily_work_hours'] : '-') . "</td>";
                echo "<td class='group-operations'>" . (isset($row['daily_operators']) ? $row['daily_operators'] : '-') . "</td>";

                // البيانات المالية
                echo "<td class='group-basic'>" . (isset($row['price_currency_contract']) && $row['price_currency_contract'] ? $row['price_currency_contract'] : '-') . "</td>";
                echo "<td class='group-basic'>" . (isset($row['paid_contract']) && $row['paid_contract'] ? $row['paid_contract'] : '-') . "</td>";
                echo "<td class='group-basic'>" . (isset($row['payment_time']) && $row['payment_time'] ? $row['payment_time'] : '-') . "</td>";
                echo "<td class='group-basic'>" . (isset($row['guarantees']) && $row['guarantees'] ? $row['guarantees'] : '-') . "</td>";
                echo "<td class='group-basic'>" . (isset($row['payment_date']) && $row['payment_date'] ? $row['payment_date'] : '-') . "</td>";

                // الحالة والإجراءات
                echo "<td class='group-status'>" . $status . "</td>";
                echo "</tr>";
              }
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- jQuery -->
  <script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
  <!-- DataTables JS -->
  <script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
  <!-- مكوّن مجموعات الأعمدة (إظهار/إخفاء + تمييز لون الزر النشط) -->
  <script src="/ems/assets/js/column-groups.js"></script>

  <!-- JS -->
  <script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
  <script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
  <script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
  <script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
  <script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
  <script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
  <script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
  <script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
  <script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>


  <script>
    (function () {
      // تهيئةُ جدولِ العقود انتقلت إلى المكوّنِ المركزي
      // (assets/js/ui-unification.js — initializeMissingDataTables):
      // لغةٌ عربية وضبطُ أعمدةٍ وتمريرٌ أفقيٌّ وزرُّ إكسل موحَّد.

      // التحكم في إظهار وإخفاء الفورم
      const toggleContractFormBtn = document.getElementById('toggleForm');
      const contractForm = document.getElementById('projectForm');

      if (toggleContractFormBtn && contractForm) {
        toggleContractFormBtn.addEventListener('click', function () {
          contractForm.classList.toggle('allforms-visible');
        });
      }

      // زر الإلغاء — إخفاء الفورم وتفريغ الحقول
      const contractFormCancelBtn = document.getElementById('contractFormCancelBtn');
      if (contractFormCancelBtn && contractForm) {
        contractFormCancelBtn.addEventListener('click', function () {
          contractForm.classList.remove('allforms-visible');
          contractForm.reset();
          const cid = document.getElementById('contract_id');
          if (cid) cid.value = '';
        });
      }

      window.loadContractMines = function (projectId, selectedMineId) {
        // تم حذف المناجم - هذه الدالة محتفظة للتوافق فقط
        return;
      }

      // فلتر المشروع لا يحتاج إجراء AJAX إضافي
    })();

  </script>

<script>
    const $el = (sel) => document.querySelector(sel);
    let equipmentIndex = 1;

    const fields = {
      contractDays: $el('#contract_duration_days'),
      actualStart: $el('#actual_start'),
      actualEnd: $el('#actual_end'),
      kpiMonthTotal: $el('#kpi_month_total'),
      kpiContractTotal: $el('#kpi_contract_total'),
      kpiEquipMonth: $el('#kpi_equip_month'),
      hoursMonthlyTarget: $el('#hours_monthly_target'),
      forecastedContractedHours: $el('#forecasted_contracted_hours'),
    };

    function num(v) {
      const n = parseFloat(v);
      return isFinite(n) ? n : 0;
    }

    function fmt(n) {
      return new Intl.NumberFormat('ar-EG').format(Math.max(0, Math.round(n)));
    }

    function updateEquipmentTypeOptions() {
      const selectedValues = new Set();
      document.querySelectorAll('.equip-type').forEach(select => {
        if (select.value) {
          selectedValues.add(select.value);
        }
      });

      document.querySelectorAll('.equip-type').forEach(select => {
        const currentValue = select.value;
        Array.from(select.options).forEach(option => {
          if (!option.value) {
            option.hidden = false;
            option.disabled = false;
            return;
          }

          if (option.value === currentValue) {
            option.hidden = false;
            option.disabled = false;
            return;
          }

          if (selectedValues.has(option.value)) {
            option.hidden = true;
            option.disabled = true;
          } else {
            option.hidden = false;
            option.disabled = false;
          }
        });
      });
    }

    // حساب مدة العقد بالأيام من التاريخين
    function calculateDaysFromDates() {
      const startDate = fields.actualStart.value;
      const endDate = fields.actualEnd.value;

      if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        const diffTime = Math.abs(end - start);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        fields.contractDays.value = diffDays;
      } else {
        fields.contractDays.value = '';
      }

      // تحديث جميع الإجماليات مباشرة عند تغيير التواريخ
      recalc();
    }

    // تحديث حساب الأيام عند تغيير التواريخ
    fields.actualStart.addEventListener('change', calculateDaysFromDates);
    fields.actualEnd.addEventListener('change', calculateDaysFromDates);

    // إضافة قسم معدات جديد
    function addEquipmentSection() {
      equipmentIndex++;
      const newSection = document.createElement('div');
      newSection.className = 'equipment-section';
      newSection.setAttribute('data-index', equipmentIndex);
      newSection.innerHTML = `
        <div class="equipment-card">
          <div class="equipment-card-head">
            <h6 class="equipment-card-title is-inline">المعدات رقم ${equipmentIndex}</h6>
            <button type="button" class="removeEquipmentBtn remove-equipment-btn" data-index="${equipmentIndex}">
              <i class="fa fa-trash"></i> حذف
            </button>
          </div>
          <div class="form-grid">


            <div class="field md-3 sm-6">
              <label>نوع المعدة</label>
              <div class="control">
                <select name="equip_type_${equipmentIndex}" class="equip-type" aria-label="نوع المعدة">
                  <?php echo $equipmentTypeOptionsHtml; ?>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>عدد المعدات</label>
              <div class="control"><input name="equip_count_${equipmentIndex}" type="number" min="0" aria-label="عدد المعدات"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><span class="equip-basic-mark">■</span> المعدات الأساسية</label>
              <div class="control"><input name="equip_count_basic_${equipmentIndex}" type="number" min="0" class="equip-basic-input" aria-label="عدد المعدات الأساسية"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><span class="equip-backup-mark">■</span> المعدات الاحتياطية</label>
              <div class="control"><input name="equip_count_backup_${equipmentIndex}" type="number" min="0" class="equip-backup-input" aria-label="عدد المعدات الاحتياطية"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>عدد المشغلين</label>
              <div class="control"><input name="equip_operators_${equipmentIndex}" type="number" min="0" aria-label="عدد المشغلين"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>عدد المساعدين</label>
              <div class="control"><input name="equip_assistants_${equipmentIndex}" type="number" min="0" aria-label="عدد المساعدين"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>عدد الورديات</label>
              <div class="control"><input name="equip_shifts_${equipmentIndex}" type="number" min="0" placeholder="مثال: 2" aria-label="عدد الورديات"></div>
            </div>

            <!-- أوقات الورديات -->
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> بداية الوردية الأولى</label>
              <div class="control"><input name="shift1_start_${equipmentIndex}" type="time" placeholder="مثال: 08:00" aria-label="بداية الوردية الأولى"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> نهاية الوردية الأولى</label>
              <div class="control"><input name="shift1_end_${equipmentIndex}" type="time" placeholder="مثال: 16:00" aria-label="نهاية الوردية الأولى"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> بداية الوردية الثانية</label>
              <div class="control"><input name="shift2_start_${equipmentIndex}" type="time" placeholder="مثال: 16:00" aria-label="بداية الوردية الثانية"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> نهاية الوردية الثانية</label>
              <div class="control"><input name="shift2_end_${equipmentIndex}" type="time" placeholder="مثال: 00:00" aria-label="نهاية الوردية الثانية"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>وحدة القياس</label>
              <div class="control">
                <select name="equip_unit_${equipmentIndex}" class="equip-unit" aria-label="وحدة القياس">
                  <option value="">— اختر —</option>
                  <option value="ساعة">ساعة</option>
                  <option value="طن">طن</option>
                  <option value="متر طولي">متر طولي</option>
                  <option value="متر مكعب">متر مكعب</option>
                </select>
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label>ساعات الوردية</label>
              <div class="control"><input name="shift_hours_${equipmentIndex}" type="number" min="0" aria-label="ساعات الوردية"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>إجمالي الساعات يومياً</label>
              <div class="control"><input name="equip_total_month_${equipmentIndex}" type="number" readonly placeholder="يُحتسب تلقائياً" aria-label="إجمالي الساعات يومياً"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>وحدات العمل في الشهر</label>
              <div class="control"><input name="equip_target_per_month_${equipmentIndex}" type="number" min="0" aria-label="وحدات العمل في الشهر"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>إجمالي ساعات العقد</label>
              <div class="control"><input name="equip_total_contract_${equipmentIndex}" type="number" readonly placeholder="يُحتسب تلقائياً" aria-label="إجمالي ساعات العقد"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>العملة</label>
              <div class="control">
                <select name="equip_price_currency_${equipmentIndex}" aria-label="عملة التسعير">
                  <option value="">— اختر —</option>
                  <option value="دولار">دولار</option>
                  <option value="جنيه">جنيه</option>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>السعر</label>
              <div class="control"><input name="equip_price_${equipmentIndex}" type="number" min="0" step="0.01" placeholder="0.00" aria-label="السعر للوحدة"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>عدد المشرفين</label>
              <div class="control"><input name="equip_supervisors_${equipmentIndex}" type="number" min="0" aria-label="عدد المشرفين"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>عدد الفنيين</label>
              <div class="control"><input name="equip_technicians_${equipmentIndex}" type="number" min="0" aria-label="عدد الفنيين"></div>
            </div>
          </div>
        </div>
      `;
      document.getElementById('equipmentSections').appendChild(newSection);

      // إضافة event listeners للحقول الجديدة
      newSection.querySelectorAll('input').forEach(el => el.addEventListener('input', recalc));
      newSection.querySelectorAll('.equip-type').forEach(el => el.addEventListener('change', updateEquipmentTypeOptions));

      // إضافة event listener لزر الحذف
      newSection.querySelector('.removeEquipmentBtn').addEventListener('click', function () {
        newSection.remove();
        recalc();
        updateEquipmentTypeOptions();
      });

      updateEquipmentTypeOptions();
    }

    function recalc() {
      const days = num(fields.contractDays.value);

      // حساب إجمالي المعدات
      let totalEquipMonth = 0;
      let totalEquipContract = 0;
      let totalMonthlyUnits = 0;

      // حساب كل قسم معدات
      document.querySelectorAll('.equipment-section').forEach(section => {
        const index = section.getAttribute('data-index');
        const countInput = section.querySelector(`input[name="equip_count_${index}"]`);
        const countBasicInput = section.querySelector(`input[name="equip_count_basic_${index}"]`);
        const countBackupInput = section.querySelector(`input[name="equip_count_backup_${index}"]`);
        const targetInput = section.querySelector(`input[name="shift_hours_${index}"]`);
        const monthlyTargetInput = section.querySelector(`input[name="equip_target_per_month_${index}"]`);
        const monthInput = section.querySelector(`input[name="equip_total_month_${index}"]`);
        const contractInput = section.querySelector(`input[name="equip_total_contract_${index}"]`);

        if (countInput && targetInput) {
          const countBasic = num(countBasicInput ? countBasicInput.value : 0);
          // المعدات الاحتياطية لا تدخل في الحساب (للتوثيق فقط)
          const rawCount = num(countInput.value);
          const count = countBasic > 0 ? countBasic : rawCount;
          const target = num(targetInput.value);
          const sectionMonth = count * target;
          const sectionMonthlyUnits = count > 0 ? (sectionMonth * 30) / count : 0;
          // حساب إجمالي الساعات على أساس الأيام بدلاً من الشهور
          // نفترض أن الـ target هو الساعات اليومية للمعدة
          const sectionContract = sectionMonth * days;

          monthInput.value = sectionMonth;
          if (monthlyTargetInput) {
            monthlyTargetInput.value = sectionMonthlyUnits;
          }
          contractInput.value = sectionContract;

          totalEquipMonth += sectionMonth;
          totalEquipContract += sectionContract;
          totalMonthlyUnits += sectionMonthlyUnits;
        }
      });

      const monthTotal = totalEquipMonth;
      const contractTotal = totalEquipContract;

      fields.kpiEquipMonth.textContent = fmt(totalEquipMonth);
      fields.kpiMonthTotal.textContent = fmt(totalMonthlyUnits);
      fields.kpiContractTotal.textContent = fmt(contractTotal);

      fields.hoursMonthlyTarget.value = totalMonthlyUnits;
      fields.forecastedContractedHours.value = contractTotal;
    }

    // تشغيل الحسبة عند تغيير أي مدخل
    document.addEventListener('input', function (e) {
      if (e.target.closest('#projectForm')) {
        recalc();
      }
    });

    document.addEventListener('change', function (e) {
      if (e.target.classList && e.target.classList.contains('equip-type')) {
        updateEquipmentTypeOptions();
      }
    });

    // زر إضافة المعدات
    document.getElementById('addEquipmentBtn').addEventListener('click', function (e) {
      e.preventDefault();
      addEquipmentSection();
    });

    // جلب الفورم
    const contractForm = document.getElementById('projectForm');
    if (contractForm) {
      contractForm.addEventListener('reset', () => setTimeout(() => {
        recalc();
        updateEquipmentTypeOptions();
      }, 0));
    }

    // أول تشغيل
    recalc();
    updateEquipmentTypeOptions();



    // تعبئة الفورم عند التعديل
    $(document).on("click", ".editBtn", function () {
      $("#projectForm").addClass('allforms-visible');
      $("#contract_id").val($(this).data("id"));

      const projectId = $(this).data("project_id");
      if ($('#contract_project_id').length) {
        $('#contract_project_id').val(projectId || '');
      }

      $("#projectForm [name='contract_signing_date']").val($(this).data("contract_signing_date"));
      $("#projectForm [name='grace_period_days']").val($(this).data("grace_period_days"));
      $("#projectForm [name='contract_duration_days']").val($(this).data("contract_duration_days"));
      $("#projectForm [name='actual_start']").val($(this).data("actual_start"));
      $("#projectForm [name='actual_end']").val($(this).data("actual_end"));

      // استخدم التاريخين كمصدر الحقيقة قبل أي إعادة حساب للإجماليات
      calculateDaysFromDates();


      $("#projectForm [name='hours_monthly_target']").val($(this).data("hours_monthly_target"));
      $("#projectForm [name='forecasted_contracted_hours']").val($(this).data("forecasted_contracted_hours"));

      $("#projectForm [name='daily_work_hours']").val($(this).attr("daily_work_hours"));

      $("#projectForm [name='daily_operators']").val($(this).attr("daily_operators"));

      // تحميل الحقول الإضافية للعقد
      $("#projectForm [name='equip_shifts_contract']").val($(this).attr("equip_shifts_contract"));
      $("#projectForm [name='shift_contract']").val($(this).attr("shift_contract"));
      $("#projectForm [name='equip_total_contract']").val($(this).attr("equip_total_contract_daily"));
      $("#projectForm [name='total_contract_permonth']").val($(this).attr("total_contract_permonth"));
      $("#projectForm [name='total_contract']").val($(this).attr("total_contract_units"));

      $("#projectForm [name='first_party']").val($(this).attr("first_party"));
      $("#projectForm [name='second_party']").val($(this).attr("second_party"));
      $("#projectForm [name='witness_one']").val($(this).attr("witness_one"));
      $("#projectForm [name='witness_two']").val($(this).attr("witness_two"));
      $("#projectForm [name='transportation']").val($(this).attr("transportation"));
      $("#projectForm [name='accommodation']").val($(this).attr("accommodation"));
      $("#projectForm [name='place_for_living']").val($(this).attr("place_for_living"));
      $("#projectForm [name='workshop']").val($(this).attr("workshop"));

      // البيانات المالية الجديدة
      $("#projectForm [name='price_currency_contract']").val($(this).attr("price_currency_contract"));
      $("#projectForm [name='paid_contract']").val($(this).attr("paid_contract"));
      $("#projectForm [name='payment_time']").val($(this).attr("payment_time"));
      $("#projectForm [name='guarantees']").val($(this).attr("guarantees"));
      $("#projectForm [name='payment_date']").val($(this).attr("payment_date"));

      // تحميل المعدات الخاصة بالعقد
      const contractId = $(this).data("id");
      $.ajax({
        url: 'get_equipments.php',
        type: 'POST',
        data: { contract_id: contractId },
        dataType: 'json',
        success: function (equipments) {
          // مسح الأقسام القديمة ما عدا الأول
          $('#equipmentSections .equipment-section').not(':first').remove();
          equipmentIndex = 1;

          // تحميل المعدات
          if (equipments.length > 0) {
            equipments.forEach(function (equip, index) {
              const sectionIndex = index + 1;

              if (sectionIndex === 1) {
                // تحديث القسم الأول
                $(`select[name="equip_type_1"]`).val(equip.equip_type);
                $(`input[name="equip_size_1"]`).val(equip.equip_size);
                $(`input[name="equip_count_1"]`).val(equip.equip_count);
                $(`input[name="equip_count_basic_1"]`).val(equip.equip_count_basic || 0);
                $(`input[name="equip_count_backup_1"]`).val(equip.equip_count_backup || 0);
                $(`input[name="equip_shifts_1"]`).val(equip.equip_shifts);
                $(`select[name="equip_unit_1"]`).val(equip.equip_unit);
                $(`input[name="shift1_start_1"]`).val(equip.shift1_start);
                $(`input[name="shift1_end_1"]`).val(equip.shift1_end);
                $(`input[name="shift2_start_1"]`).val(equip.shift2_start);
                $(`input[name="shift2_end_1"]`).val(equip.shift2_end);
                $(`input[name="shift_hours_1"]`).val(equip.shift_hours);
                $(`input[name="equip_total_month_1"]`).val(equip.equip_total_month);
                $(`input[name="equip_total_contract_1"]`).val(equip.equip_total_contract);
                $(`input[name="equip_price_1"]`).val(equip.equip_price);
                $(`select[name="equip_price_currency_1"]`).val(equip.equip_price_currency);
                $(`input[name="equip_operators_1"]`).val(equip.equip_operators);
                $(`input[name="equip_supervisors_1"]`).val(equip.equip_supervisors);
                $(`input[name="equip_technicians_1"]`).val(equip.equip_technicians);
                $(`input[name="equip_assistants_1"]`).val(equip.equip_assistants);
                equipmentIndex = 1;
                updateEquipmentTypeOptions();
              } else {
                // إضافة أقسام جديدة
                equipmentIndex++;
                const newSection = document.createElement('div');
                newSection.className = 'equipment-section';
                newSection.setAttribute('data-index', equipmentIndex);
                newSection.innerHTML = `
                  <div class="equipment-card">
                    <div class="equipment-card-head">
                      <h6 class="equipment-card-title is-inline">المعدات رقم ${equipmentIndex}</h6>
                      <button type="button" class="removeEquipmentBtn remove-equipment-btn" data-index="${equipmentIndex}">
                        <i class="fa fa-trash"></i> حذف
                      </button>
                    </div>
                    <div class="form-grid">


                      <div class="field md-3 sm-6">
                        <label>نوع المعدة</label>
                        <div class="control">
                          <select name="equip_type_${equipmentIndex}" class="equip-type" aria-label="نوع المعدة">
                            <?php echo $equipmentTypeOptionsHtml; ?>
                          </select>
                        </div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد المعدات</label>
                        <div class="control"><input name="equip_count_${equipmentIndex}" type="number" min="0" value="${equip.equip_count}" aria-label="عدد المعدات"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><span class="equip-basic-mark">■</span> المعدات الأساسية</label>
                        <div class="control"><input name="equip_count_basic_${equipmentIndex}" type="number" min="0" class="equip-basic-input" value="${equip.equip_count_basic || 0}" aria-label="عدد المعدات الأساسية"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><span class="equip-backup-mark">■</span> المعدات الاحتياطية</label>
                        <div class="control"><input name="equip_count_backup_${equipmentIndex}" type="number" min="0" class="equip-backup-input" value="${equip.equip_count_backup || 0}" aria-label="عدد المعدات الاحتياطية"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد المشغلين</label>
                        <div class="control"><input name="equip_operators_${equipmentIndex}" type="number" min="0" value="${equip.equip_operators}" aria-label="عدد المشغلين"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد المساعدين</label>
                        <div class="control"><input name="equip_assistants_${equipmentIndex}" type="number" min="0" value="${equip.equip_assistants}" aria-label="عدد المساعدين"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد الورديات</label>
                        <div class="control"><input name="equip_shifts_${equipmentIndex}" type="number" min="0" placeholder="مثال: 2" value="${equip.equip_shifts}" aria-label="عدد الورديات"></div>
                      </div>

                      <!-- أوقات الورديات -->
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> بداية الوردية الأولى</label>
                        <div class="control"><input name="shift1_start_${equipmentIndex}" type="time" value="${equip.shift1_start || ''}" aria-label="بداية الوردية الأولى"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> نهاية الوردية الأولى</label>
                        <div class="control"><input name="shift1_end_${equipmentIndex}" type="time" value="${equip.shift1_end || ''}" aria-label="نهاية الوردية الأولى"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> بداية الوردية الثانية</label>
                        <div class="control"><input name="shift2_start_${equipmentIndex}" type="time" value="${equip.shift2_start || ''}" aria-label="بداية الوردية الثانية"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> نهاية الوردية الثانية</label>
                        <div class="control"><input name="shift2_end_${equipmentIndex}" type="time" value="${equip.shift2_end || ''}" aria-label="نهاية الوردية الثانية"></div>
                      </div>

                      <div class="field md-3 sm-6">
                        <label>وحدة القياس</label>
                        <div class="control">
                          <select name="equip_unit_${equipmentIndex}" class="equip-unit" aria-label="وحدة القياس">
                            <option value="">— اختر —</option>
                            <option value="ساعة" ${equip.equip_unit === 'ساعة' ? 'selected' : ''}>ساعة</option>
                            <option value="طن" ${equip.equip_unit === 'طن' ? 'selected' : ''}>طن</option>
                            <option value="متر طولي" ${equip.equip_unit === 'متر طولي' ? 'selected' : ''}>متر طولي</option>
                            <option value="متر مكعب" ${equip.equip_unit === 'متر مكعب' ? 'selected' : ''}>متر مكعب</option>
                          </select>
                        </div>
                      </div>

                      <div class="field md-3 sm-6">
                        <label>ساعات الوردية</label>
                        <div class="control"><input name="shift_hours_${equipmentIndex}" type="number" min="0" value="${equip.shift_hours}" aria-label="ساعات الوردية"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>إجمالي الساعات يومياً</label>
                        <div class="control"><input name="equip_total_month_${equipmentIndex}" type="number" readonly placeholder="يُحتسب تلقائياً" value="${equip.equip_total_month}" aria-label="إجمالي الساعات يومياً"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>وحدات العمل في الشهر</label>
                        <div class="control"><input name="equip_target_per_month_${equipmentIndex}" type="number" min="0" value="${equip.equip_monthly_target || 0}" aria-label="وحدات العمل في الشهر"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>إجمالي ساعات العقد</label>
                        <div class="control"><input name="equip_total_contract_${equipmentIndex}" type="number" readonly placeholder="يُحتسب تلقائياً" value="${equip.equip_total_contract}" aria-label="إجمالي ساعات العقد"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>العملة</label>
                        <div class="control">
                          <select name="equip_price_currency_${equipmentIndex}" aria-label="عملة التسعير">
                            <option value="">— اختر —</option>
                            <option value="دولار" ${equip.equip_price_currency === 'دولار' ? 'selected' : ''}>دولار</option>
                            <option value="جنيه" ${equip.equip_price_currency === 'جنيه' ? 'selected' : ''}>جنيه</option>
                          </select>
                        </div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>السعر</label>
                        <div class="control"><input name="equip_price_${equipmentIndex}" type="number" min="0" step="0.01" placeholder="0.00" value="${equip.equip_price}" aria-label="السعر للوحدة"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد المشرفين</label>
                        <div class="control"><input name="equip_supervisors_${equipmentIndex}" type="number" min="0" value="${equip.equip_supervisors}" aria-label="عدد المشرفين"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد الفنيين</label>
                        <div class="control"><input name="equip_technicians_${equipmentIndex}" type="number" min="0" value="${equip.equip_technicians}" aria-label="عدد الفنيين"></div>
                      </div>
                    </div>
                  </div>
                `;
                document.getElementById('equipmentSections').appendChild(newSection);

                const newSelect = newSection.querySelector(`select[name="equip_type_${equipmentIndex}"]`);
                if (newSelect) {
                  newSelect.value = equip.equip_type;
                }

                // إضافة event listeners
                newSection.querySelectorAll('input').forEach(el => el.addEventListener('input', recalc));
                newSection.querySelectorAll('.equip-type').forEach(el => el.addEventListener('change', updateEquipmentTypeOptions));
                newSection.querySelector('.removeEquipmentBtn').addEventListener('click', function () {
                  newSection.remove();
                  recalc();
                  updateEquipmentTypeOptions();
                });
              }
            });
          }

          recalc();
          updateEquipmentTypeOptions();
        }
      });

      $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
    });

    // ==================== Group Toggle (unified) ====================
    // موحّد عبر assets/js/column-groups.js — مصدر واحد للحقيقة.
    (function () {
      function go() {
        if (window.EmsColumnGroups) {
          // الأصنافُ اعتُمدت باسمِ العائلةِ المؤسسية ems-btn — المحدِّدان يمرَّران صراحةً
          EmsColumnGroups.init({
            storageKey: 'contractGroupStates', mode: 'classic',
            buttons: '.ems-btn-group-toggle[data-group]',
            allButton: '.ems-btn-group-toggle-all'
          });
        }
      }
      if (window.EmsColumnGroups) { go(); } else { window.addEventListener('DOMContentLoaded', go); }
    })();
  </script>

  <?php if (function_exists('ems_excel_render')) {
    ems_excel_render();
  } ?>
  </body>

  </html>
