<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: ../login.php");
  exit();
}

require_once '../config.php';
require_once '../includes/permissions_helper.php';

$page_permissions = check_page_permissions($conn, 'Suppliers/supplierscontracts.php');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

// ── H-20: عزلُ مشرف المورد — يرى عقودَ موردِه وحدها قراءةً (403 مسجَّلة) ──
require_once __DIR__ . '/../app/Services/Portal/SupplierPortalGuard.php';
$spg_scope = \App\Services\Portal\SupplierPortalGuard::enforce(
    $conn, $_SESSION['user'], intval($_GET['id'] ?? 0), 'Suppliers/supplierscontracts.php');
if ($spg_scope !== null) {
    // بوابةُ المشرف قراءةٌ حصرًا — لا إنشاءَ عقدٍ ولا تعديلَه ولا حذفَه من الخارج
    $can_add = false; $can_edit = false; $can_delete = false;
    // الحقنُ البنيوي: كلُّ القوائم والمرشِّحات على موردِه ولو طُلب غيرُه بمعامل
    $_GET['id'] = $spg_scope;
    $_GET['filter_supplier_id'] = $spg_scope;
}

if (!$can_view) {
  ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض عقود الموردين ❌', 'GOV-PERM-403', '');
  exit();
}

$is_super_admin = isset($_SESSION['user']['role']) && (string) $_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
  die('لا يمكن تحديد الشركة الحالية');
}

// العزل عبر بوابة المستأجر (K9 · هجرة 2026-07-15): بُناة شروط النطاق البديلة
// وكشف الأعمدة أُسقطوا — {TENANT_SCOPE} والبوابة مسؤولا النطاق، والسوبر عبر
// forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق شركة).
$scg = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier contracts super') : ems_tenant_db();

try {
  $equipmentTypes = $scg->select('equipments_types', array(
    'columns' => array('id', 'type'),
    'orderBy' => 'type ASC',
  ));
} catch (\Throwable $t) { $equipmentTypes = []; }

$equipmentTypeOptionsHtml = '<option value="">— اختر —</option>';
foreach ($equipmentTypes as $equipmentType) {
  $typeId = (int) $equipmentType['id'];
  $typeName = htmlspecialchars($equipmentType['type'], ENT_QUOTES, 'UTF-8');
  $equipmentTypeOptionsHtml .= '<option value="' . $typeId . '">' . $typeName . '</option>';
}

try {
  $active_suppliers_options = $scg->scopedQuery(array(
    'scope' => array('s' => 'suppliers'),
  ), "SELECT s.id, s.name
      FROM suppliers s
      WHERE {TENANT_SCOPE} AND s.status = 1
      ORDER BY s.name ASC");
} catch (\Throwable $t) { $active_suppliers_options = array(); }

$supplier_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$has_supplier_filter = ($supplier_id > 0);
$filter_supplier_id = isset($_GET['filter_supplier_id']) ? intval($_GET['filter_supplier_id']) : 0;
$filter_project_id = isset($_GET['filter_project_id']) ? intval($_GET['filter_project_id']) : 0;
if ($has_supplier_filter) {
  $filter_supplier_id = $supplier_id;
}

if ($has_supplier_filter) {
  // فحص ملكية المورد — الأصل بلا فلتر حذفٍ ناعم (includeDeleted يحفظ السلوك حرفيًّا)
  try {
    $supplier_check = $scg->selectOne('suppliers', array(
      'columns' => array('id'), 'where' => array('id' => $supplier_id),
      'includeDeleted' => true,
    ));
  } catch (\Throwable $t) { $supplier_check = null; }
  if (!$supplier_check) {
    header('Location: suppliers.php');
    exit();
  }
}

$suppliers_filter_options = array();
try {
  $sf_rows = $scg->scopedQuery(array(
    'scope' => array('sc' => 'supplierscontracts', 's' => 'suppliers'),
  ), "SELECT DISTINCT s.id, s.name FROM supplierscontracts sc
      JOIN suppliers s ON sc.supplier_id = s.id
      WHERE {TENANT_SCOPE} ORDER BY s.name ASC");
} catch (\Throwable $t) { $sf_rows = array(); }
foreach ($sf_rows as $sf_row) {
  $suppliers_filter_options[intval($sf_row['id'])] = $sf_row['name'];
}

// جلب قائمة المشاريع للفلتر من جدول المشاريع مباشرة
$projects_filter_options = array();
try {
  $pf_rows = $scg->scopedQuery(array(
    'scope' => array('p' => 'project'),
  ), "SELECT p.id, p.name FROM project p WHERE {TENANT_SCOPE} AND p.status = 1 ORDER BY p.name ASC");
} catch (\Throwable $t) { $pf_rows = array(); }
foreach ($pf_rows as $project_filter_row) {
  $projects_filter_options[intval($project_filter_row['id'])] = $project_filter_row['name'];
}

// (mine filter removed - contracts link directly to project)

if (isset($_GET['delete_id'])) {
  if (!$can_delete) {
    ems_gov_redirect("Location: supplierscontracts.php?id=$supplier_id&msg=لا+توجد+صلاحية+حذف+عقود+الموردين+❌");
    exit();
  }

  $delete_id = intval($_GET['delete_id']);
  if ($delete_id > 0) {
    // الحذف الصلب المتسلسل عبر قنوات البوابة الذرّية (المعدات ← الملاحظات ← العقد)
    // بشرط الأصل: العقد يخصّ موردَ الرابط وشركةَ السياق (فحصٌ مسبقٌ صريح).
    try {
      $del_own = $scg->selectOne('supplierscontracts', array(
        'columns' => array('id'),
        'where'   => array('id' => $delete_id, 'supplier_id' => $supplier_id),
      ));
      if (!$del_own) {
        throw new \RuntimeException('out of scope');
      }
      $scg->runInTransaction(function ($g) use ($delete_id) {
        // صفوفٌ فارغة = مسحُ كل الأبناء (ملكية الأب تُفحص داخل القناة)
        $g->replaceChildren('supplierscontracts', $delete_id, 'suppliercontractequipments', 'contract_id', array(), 'حذف عقد مورد: مسح معداته');
        $g->replaceChildren('supplierscontracts', $delete_id, 'supplier_contract_notes', 'contract_id', array(), 'حذف عقد مورد: مسح ملاحظاته');
        $n = $g->deleteRow('supplierscontracts', $delete_id, 'حذف عقد مورد من شاشة العقود');
        if ($n <= 0) {
          throw new \RuntimeException('contract delete matched 0 rows');
        }
      }, 'حذف عقد مورد متسلسل');
      ems_gov_redirect("Location: supplierscontracts.php?id=$supplier_id&msg=تم+حذف+العقد+بنجاح+✅");
      exit();
    } catch (\Throwable $t) {
      ems_gov_redirect("Location: supplierscontracts.php?id=$supplier_id&msg=تعذر+حذف+العقد+أو+أنه+خارج+النطاق+❌");
      exit();
    }
  }

  ems_gov_redirect("Location: supplierscontracts.php?id=$supplier_id&msg=معرف+العقد+غير+صحيح+❌");
  exit();
}

$page_title = 'إيكوبيشن | عقود المورد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include('../inheader.php');
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main contracts-main supplier-contracts-page ems-unified-page-shell">

  <?php
  // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
  $header_title = 'عقود المورد';
  $header_icon = 'fas fa-file-contract';
  $header_actions = array();
  if ($can_add) {
    $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'عقد جديد');
  }
  // ── نظام Excel الموحّد (Unified Excel Framework) ──
  require_once __DIR__ . '/../includes/excel_ui.php';
  foreach (ems_excel_header_actions('supplier_contracts', 'عقود الموردين', $can_add) as $__xlAction) {
    $header_actions[] = $__xlAction;
  }
  $header_back = array(
    array('href' => 'suppliers.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'العودة للموردين'),
    array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fa-solid fa-house', 'label' => 'الرئيسية'),
  );
  include('../includes/page_header.php');
  // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
  echo ems_states_bundle('لا عقودَ مورِّدين مسجَّلةً بعدُ', 'أضف أولَ عقدِ مورِّدٍ بزرِّ «عقد جديد» في رأسِ الشاشة');
  ?>

  <!-- فورم إضافة عقد -->
  <?php if ($can_add || $can_edit): ?>
    <form id="projectForm" action="" method="post" class="allforms">
        <?= csrf_field() ?>
        <div class="card-header">
          <h5>
            <i class="fas fa-file-signature"></i> إضافة / تعديل عقد المورد
          </h5>
        </div>
      <div class="card">
        <div class="card-body">
          <input type="hidden" name="id" id="contract_id" value="">
          <?php if ($has_supplier_filter): ?>
            <input type="hidden" name="supplier_id" value="<?php echo $supplier_id; ?>" required />
          <?php endif; ?>

          <!-- القسم 1: اختيار المشروع والعقد -->
          <div class="form-section">
            <h6><i class="fas fa-file-contract"></i> اختيار المشروع والعقد </h6>
            <div class="form-grid">
              <?php if (!$has_supplier_filter): ?>
                <div class="field md-3 sm-6">
                  <label>المورد <font color="red">*</font></label>
                  <div class="control">
                    <select name="supplier_id" id="supplier_id_select" aria-label="المورد" required>
                      <option value="">— اختر المورد —</option>
                      <?php foreach ($active_suppliers_options as $supplier_option): ?>
                        <option value="<?php echo intval($supplier_option['id']); ?>">
                          <?php echo htmlspecialchars($supplier_option['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              <?php endif; ?>

              <div class="field md-3 sm-6">
                <label>اسم المشروع <font color="red">*</font></label>
                <div class="control">
                  <select name="project_id" id="project_id" aria-label="اسم المشروع" required>
                    <option value="">— اختر المشروع —</option>
                    <?php

                    try {
                      $form_projects = $scg->scopedQuery(array(
                        'scope' => array('p' => 'project'),
                      ), "SELECT p.id, p.name FROM project p WHERE {TENANT_SCOPE} AND p.status = 1 ORDER BY p.name ASC");
                    } catch (\Throwable $t) { $form_projects = array(); }
                    foreach ($form_projects as $project) {
                      echo "<option value='" . $project['id'] . "'>" . $project['name'] . "</option>";
                    }
                    ?>
                  </select>
                </div>
              </div>

              <div class="field md-3 sm-6">
                <label>عقد المشروع <font color="red">*</font></label>
                <div class="control">
                  <select name="project_contract_id" id="project_contract_id" aria-label="عقد المشروع المرتبط" required disabled>
                    <option value="">— اختر المشروع أولاً —</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- عرض معلومات ساعات العقد -->
          <div class="form-section">
            <h6><i class="fas fa-file-contract"></i> إجماليات الساعات (يومياً وللعقد)</h6>
            <div id="projectHoursInfo" class="project-hours-info sc-is-hidden">
              <div class="project-hours-grid">
                <div class="project-hours-card">
                  <strong class="project-hours-label project-hours-label-blue">
                    <i class="fas fa-clock"></i> إجمالي ساعات العقد
                  </strong>
                  <div class="project-hours-value project-hours-value-blue" id="contractTotalHours">0</div>
                  <div id="equipmentBreakdown" class="project-hours-breakdown">
                    <!-- سيتم ملء التفصيل هنا -->
                  </div>
                </div>
                <div class="project-hours-card">
                  <strong class="project-hours-label project-hours-label-red">
                    <i class="fas fa-handshake"></i> المتعاقد عليه مع موردين
                  </strong>
                  <div class="project-hours-value project-hours-value-red" id="suppliersContractedHours">0</div>
                  <div id="suppliersBreakdown" class="project-hours-breakdown">
                    <!-- سيتم ملء تفصيل الموردين هنا -->
                  </div>
                </div>
                <div class="project-hours-card">
                  <strong class="project-hours-label project-hours-label-green">
                    <i class="fas fa-chart-line"></i> الساعات المتبقية
                  </strong>
                  <div class="project-hours-value project-hours-value-green" id="remainingHours">0</div>
                  <div id="remainingBreakdown" class="project-hours-breakdown">
                    <!-- سيتم ملء تفصيل الساعات المتبقية هنا -->
                  </div>
                </div>
              </div>
            </div>
          </div>


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

          <div class="contracts-note-box">
            <p class="contracts-note-text">
              <i class="fas fa-info-circle"></i> <strong>ملاحظة:</strong> يتم حساب الإجماليات تلقائياً بناءً على
              البيانات المدخلة في الأقسام التالية
            </p>
          </div>



          <div class="form-section">
            <h6><i class="fas fa-file-contract"></i> البيانات الأساسية للعميل والعقد</h6>
            <div class="form-grid">

              <!-- صف 1: 3 خانات -->
              <div class="field md-3 sm-6">
                <label>تاريخ توقيع العقد </label>
                <div class="control"><input name="contract_signing_date" id="contract_signing_date" aria-label="تاريخ توقيع العقد" type="date"></div>
              </div>

              <div class="field md-3 sm-6">
                <label>فترة السماح بين التوقيع والتنفيذ </label>
                <div class="control"><input name="grace_period_days" id="grace_period_days" type="number" min="0"
                    placeholder="عدد الأيام"></div>
              </div>

              <div class="field md-3 sm-6">
                <label>بداية التنفيذ الفعلي المتفق عليه</label>
                <div class="control"><input name="actual_start" id="actual_start" aria-label="بداية التنفيذ الفعلي المتفق عليه" type="date"></div>
              </div>


              <div class="field md-3 sm-6">
                <label>نهاية التنفيذ الفعلي المتفق عليه</label>
                <div class="control"><input name="actual_end" id="actual_end" aria-label="نهاية التنفيذ الفعلي المتفق عليه" type="date"></div>
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
                <div class="control"><input name="paid_contract" aria-label="المبلغ المدفوع من قيمة العقد" type="text"></div>
              </div>

              <div class="field md-3 sm-6">
                <label>وقت الدفع</label>
                <div class="control">
                  <select name="payment_time" id="payment_time" aria-label="وقت الدفع — مقدم أو مؤخر">
                    <option value="">— اختر —</option>
                    <option value="مقدم">مقدم</option>
                    <option value=" مؤخر">مؤخر </option>

                  </select>
                </div>
              </div>

              <div class="field md-3 sm-6">
                <label> الضمانات</label>
                <div class="control"><input name="guarantees" aria-label="الضمانات المتفق عليها في العقد" type="text"></div>
              </div>

              <div class="field md-3 sm-6">
                <label> تاريخ الدفع</label>
                <div class="control"><input name="payment_date" id="payment_date" aria-label="تاريخ الدفع" type="date"></div>
              </div>











              <div class="field md-3 sm-6">
                <label>عدد الورديات للعقد </label>
                <div class="control"><input name="equip_shifts_contract" type="number" min="0" placeholder="مثال: 2">
                </div>
              </div>

              <div class="field md-3 sm-6">
                <label> ساعات الوردية للعقد</label>
                <div class="control"><input name="shift_contract" aria-label="ساعات الوردية للعقد" type="number" min="0"></div>
              </div>
              <div class="field md-3 sm-6">
                <label>إجمالي الوحدات يومياً للعقد </label>
                <div class="control"><input name="equip_total_contract" type="number" placeholder=" "></div>
              </div>
              <div class="field md-3 sm-6">
                <label>وحدات العمل في الشهر للعقد</label>
                <div class="control"><input name="total_contract_permonth" aria-label="وحدات العمل في الشهر للعقد" type="number" min="0"></div>
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
                  <select name="transportation" id="transportation" aria-label="الترحيل — الجهة المسؤولة عنه">
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
                  <select name="place_for_living" id="place_for_living" aria-label="السكن — الجهة المسؤولة عنه">
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
                  <select name="accommodation" id="accommodation" aria-label="الإعاشة — الجهة المسؤولة عنها">
                    <option value="">— اختر —</option>
                    <option value="مالك المعدة">مالك المعدة</option>
                    <option value="مالك المشروع">مالك المشروع</option>
                    <option value="بدون">بدون</option>
                  </select>
                </div>
              </div>

              <div class="field md-3 sm-6">
                <label>الصيانة (Workshop)</label>
                <div class="control">
                  <select name="workshop" id="workshop" aria-label="الصيانة — الجهة المسؤولة عنها">
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

            <!-- القسم 4: بيانات ساعات العمل المطلوبة للمعدات -->
              <div class="form-section">
            <h6><i class="fas fa-file-contract"></i>بيانات ساعات العمل المطلوبة <strong>للمعدات</strong> </h6>

            <div id="equipmentSections">
              <div class="equipment-section" data-index="1">
                <div class="sc-equip-box">
                  <h6 class="sc-equip-box-title">المعدات رقم 1</h6>
                  <div class="form-grid">
                    <div class="field md-3 sm-6">
                      <label>نوع المعدة</label>
                      <div class="control">
                        <select name="equip_type_1" aria-label="نوع المعدة للمعدة رقم 1" class="equip-type">
                          <?php echo $equipmentTypeOptionsHtml; ?>
                        </select>
                      </div>
                    </div>
                    <div class="field md-3 sm-6">
                      <label>عدد المعدات</label>
                      <div class="control"><input name="equip_count_1" aria-label="عدد المعدات للمعدة رقم 1" type="number" min="0"></div>
                    </div>

                    <div class="field md-3 sm-6">
                      <label><span class="sc-dot-basic">■</span> المعدات الأساسية</label>
                      <div class="control"><input name="equip_count_basic_1" type="number" min="0"
                          aria-label="عدد المعدات الأساسية للمعدة رقم 1" class="sc-input-basic"></div>
                    </div>

                    <div class="field md-3 sm-6">
                      <label><span class="sc-dot-backup">■</span> المعدات الاحتياطية</label>
                      <div class="control"><input name="equip_count_backup_1" type="number" min="0"
                          aria-label="عدد المعدات الاحتياطية للمعدة رقم 1" class="sc-input-backup"></div>
                    </div>
                    <div class="field md-3 sm-6">
                      <label>عدد المشغلين</label>
                      <div class="control"><input name="equip_operators_1" aria-label="عدد المشغلين للمعدة رقم 1" type="number" min="0"></div>
                    </div>


                    <div class="field md-3 sm-6">
                      <label>عدد المساعدين</label>
                      <div class="control"><input name="equip_assistants_1" aria-label="عدد المساعدين للمعدة رقم 1" type="number" min="0"></div>
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
                        <select name="equip_unit_1" aria-label="وحدة القياس للمعدة رقم 1" class="equip-unit">
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
                      <div class="control"><input name="shift_hours_1" aria-label="ساعات الوردية للمعدة رقم 1" type="number" min="0"></div>
                    </div>
                    <div class="field md-3 sm-6">
                      <label>إجمالي الوحدات يومياً</label>
                      <div class="control"><input name="equip_total_month_1" type="number" readonly
                          placeholder="يُحتسب تلقائياً"></div>
                    </div>
                    <div class="field md-3 sm-6">
                      <label>وحدات العمل في الشهر</label>
                      <div class="control"><input name="equip_target_per_month_1" aria-label="وحدات العمل في الشهر للمعدة رقم 1" type="number" min="0"></div>
                    </div>


                    <div class="field md-3 sm-6">
                      <label>إجمالي وحدات العقد</label>
                      <div class="control"><input name="equip_total_contract_1" type="number" readonly
                          placeholder="يُحتسب تلقائياً"></div>
                    </div>


                    <div class="field md-3 sm-6">
                      <label>العملة</label>
                      <div class="control">
                        <select name="equip_price_currency_1" aria-label="عملة سعر الوحدة للمعدة رقم 1">
                          <option value="">— اختر —</option>
                          <option value="دولار">دولار</option>
                          <option value="جنيه">جنيه</option>
                        </select>
                      </div>
                    </div>
                    <div class="field md-3 sm-6">
                      <label>السعر\للوحدة</label>
                      <div class="control"><input name="equip_price_1" type="number" min="0" step="0.01"
                          placeholder="0.00"></div>
                    </div>



                    <!-- خانتان فارغتان للحفاظ على 3 خانات لكل صف -->

                    <div class="field md-3 sm-6">
                      <label>عدد المشرفين</label>
                      <div class="control"><input name="equip_supervisors_1" aria-label="عدد المشرفين للمعدة رقم 1" type="number" min="0"></div>
                    </div>

                    <div class="field md-3 sm-6">
                      <label>عدد الفنيين</label>
                      <div class="control"><input name="equip_technicians_1" aria-label="عدد الفنيين للمعدة رقم 1" type="number" min="0"></div>
                    </div>
                    <!-- إكمال الصف بثلاث خانات -->
                    <div class="field md-3 sm-6"></div>
                    <div class="field md-3 sm-6"></div>
                  </div>
                </div>
              </div>
            </div>
            </div>

            <div class="sc-add-equip-row">
              <button type="button" class="primary sc-add-equip-btn" id="addEquipmentBtn">
                <i class="fas fa-plus-circle"></i> إضافة مزيد من المعدات
              </button>
            </div>


               <div class="form-section">
            <h6><i class="fas fa-file-contract"></i> بيانات إضافية </h6>

            <div class="form-grid">

              <div class="field md-3 sm-6 sc-is-hidden">
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
  <?php endif; ?>
  <div class="card">
    <!-- أزرار التحكم في المجموعات -->
    <div class="card-body sc-filter-bar">
            <!-- صندوقُ الفلاترِ الموحَّد — التصميمُ في assets/css/ems-filters.css -->
      <div class="filter">
          <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
          <div class="filter-body">
      <form method="get" action="supplierscontracts.php" class="sc-filter-form">
        <?php if ($has_supplier_filter): ?>
          <input type="hidden" name="id" value="<?php echo intval($supplier_id); ?>">
        <?php endif; ?>

        <div class="sc-filter-col">
          <label class="sc-filter-label" for="sc_filter_supplier_select">فلتر المورد</label>
          <select name="filter_supplier_id" id="sc_filter_supplier_select" aria-label="فلتر المورد" class="form-control" <?php echo $has_supplier_filter ? 'disabled' : ''; ?>>
            <option value="0">كل الموردين</option>
            <?php foreach ($suppliers_filter_options as $supplier_option_id => $supplier_option_name): ?>
              <option value="<?php echo intval($supplier_option_id); ?>" <?php echo ($filter_supplier_id === intval($supplier_option_id)) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($supplier_option_name, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($has_supplier_filter): ?>
            <input type="hidden" name="filter_supplier_id" value="<?php echo intval($filter_supplier_id); ?>">
          <?php endif; ?>
        </div>

        <div class="sc-filter-col">
          <label class="sc-filter-label" for="sc_filter_project_select">فلتر المشروع</label>
          <select name="filter_project_id" id="sc_filter_project_select" class="form-control">
            <option value="0">كل المشاريع</option>
            <?php foreach ($projects_filter_options as $project_option_id => $project_option_name): ?>
              <option value="<?php echo intval($project_option_id); ?>" <?php echo ($filter_project_id === intval($project_option_id)) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($project_option_name, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> تطبيق</button>
        <a href="supplierscontracts.php<?php echo $has_supplier_filter ? '?id=' . intval($supplier_id) : ''; ?>"
          class="btn btn-secondary"><i class="fas fa-undo"></i> مسح</a>
      </form>
          </div>
      </div>
    </div>

    <!-- أزرار التحكم في المجموعات -->
    <div class="card-body sc-group-bar">
      <div class="sc-group-toolbar">
        <span class="sc-group-toolbar-label">
          <i class="fas fa-layer-group"></i> عرض المجموعات:
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

    <div class="card-body table-container sc-table-wrap">
      <table id="projectsTable" class="display nowrap contracts-table-nowrap sc-table">
        <thead>
          <tr>
            <th class="group-status"> الإجراءات</th>
            <!-- المعلومات الأساسية -->
            <th class="group-basic"> رقم العقد</th>
            <th class="group-basic"> المشروع</th>
            <th class="group-basic"> المورد</th>
            <th class="group-basic"> العقد العميل المرتبط</th>

            <!-- التواريخ والمدد -->
            <th class="group-dates"> تاريخ التوقيع</th>
            <th class="group-dates"> مدة السماح (أيام)</th>
            <th class="group-dates"> مدة العقد (أيام)</th>
            <th class="group-dates"> بداية التنفيذ</th>
            <th class="group-dates"> نهاية التنفيذ</th>

            <!-- الساعات والأهداف -->
            <th class="group-hours"> هدف ساعات شهري</th>
            <th class="group-hours"> وقّعه</th>

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
            <th class="group-basic"> العملة</th>
            <th class="group-basic"> المبلغ المدفوع</th>
            <th class="group-basic"> وقت الدفع</th>
            <th class="group-basic"> الضمانات</th>
            <th class="group-basic"> تاريخ الدفع</th>

            <!-- الحالة والإجراءات -->
            <th class="group-status"> الحالة</th>
            <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
            <th class="ems-fn-th none" data-fn="1">تاريخ البدء</th>
            <th class="ems-fn-th none" data-fn="1">تاريخ الانتهاء</th>
            <th class="ems-fn-th none" data-fn="1">نموذج التعاقد</th>
            <th class="ems-fn-th none" data-fn="1">وحدة التعاقد</th>
            <th class="ems-fn-th none" data-fn="1">نوع المعدة</th>
            <th class="ems-fn-th none" data-fn="1">عدد الوحدات المتعاقد عليها</th>
            <th class="ems-fn-th none" data-fn="1">عدد الورديات المتفق عليها</th>
            <th class="ems-fn-th none" data-fn="1">وحدات الوردية الواحدة</th>
            <th class="ems-fn-th none" data-fn="1">الوحدات الشهرية الملزمة</th>
            <th class="ems-fn-th none" data-fn="1">الساعات الشهرية للوحدة</th>
            <th class="ems-fn-th none" data-fn="1">سعر الساعة</th>
            <th class="ems-fn-th none" data-fn="1">نسبة الجاهزية الدنيا</th>
            <th class="ems-fn-th none" data-fn="1">مهلة الإحلال</th>
            <th class="ems-fn-th none" data-fn="1">غرامة العجز</th>
            <th class="ems-fn-th none" data-fn="1">دورية التسوية</th>
            <th class="ems-fn-th none" data-fn="1">مهلة السداد</th>
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


          // إضافة عقد جديد عند إرسال الفورم
          if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['supplier_id']) && !empty($_POST['project_id']) && !empty($_POST['project_contract_id'])) {

            $supplier_id_post = intval($_POST['supplier_id']);
            $redirect_after_save = 'supplierscontracts.php?id=' . $supplier_id_post;

            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            if ($id > 0 && !$can_edit) {
              ems_gov_flash_redirect("" . $redirect_after_save . "", '❌ ليس لديك صلاحية تعديل عقود الموردين', 'GOV-PERM-403', '');
              exit();
            }
            if ($id === 0 && !$can_add) {
              ems_gov_flash_redirect("" . $redirect_after_save . "", '❌ ليس لديك صلاحية إضافة عقود موردين', 'GOV-PERM-403', '');
              exit();
            }

            $project_id = intval($_POST['project_id']);
            $project_contract_id = intval($_POST['project_contract_id']);


            // القيم تُمرَّر خامًا — البوابة prepared بالكامل (لا escape يدوي)
            $contract_signing_date = $_POST['contract_signing_date'];
            $grace_period_days = intval($_POST['grace_period_days']);

            // حساب مدة العقد بالأيام من تاريخ البداية والنهاية
            $actual_start = $_POST['actual_start'];
            $actual_end = $_POST['actual_end'];

            // حساب عدد الأيام من تاريخ البداية إلى تاريخ الانتهاء (شامل يوم البداية ويوم النهاية)
            if (!empty($actual_start) && !empty($actual_end)) {
              $start_date = new DateTime($actual_start);
              $end_date = new DateTime($actual_end);
              $interval = $start_date->diff($end_date);
              // توحيد المنطق مع الواجهة: الفرق الفعلي بدون إضافة يوم إضافي
              $contract_duration_days = $interval->days;
              // ملء مدة العقد بالأشهر (نفس صيغة معالج التجديد) لتفادي صفر/القسمة على صفر
              $contract_duration_months = ($interval->y * 12) + $interval->m;
            } else {
              $contract_duration_days = 0;
              $contract_duration_months = 0;
            }

            $transportation = $_POST['transportation'];
            $accommodation = $_POST['accommodation'];
            $place_for_living = $_POST['place_for_living'];
            $workshop = $_POST['workshop'];

            $hours_monthly_target = floatval($_POST['hours_monthly_target']);
            $forecasted_contracted_hours = floatval($_POST['forecasted_contracted_hours']);

            $daily_work_hours = floatval($_POST['daily_work_hours']);
            $daily_operators = intval($_POST['daily_operators']);
            $first_party = $_POST['first_party'];
            $second_party = $_POST['second_party'];
            $witness_one = $_POST['witness_one'];
            $witness_two = $_POST['witness_two'];

            // الحقول المالية الجديدة
            $price_currency_contract = isset($_POST['price_currency_contract']) ? $_POST['price_currency_contract'] : '';
            $paid_contract = isset($_POST['paid_contract']) ? $_POST['paid_contract'] : '';
            $payment_time = isset($_POST['payment_time']) ? $_POST['payment_time'] : '';
            $guarantees = isset($_POST['guarantees']) ? $_POST['guarantees'] : '';
            $payment_date = isset($_POST['payment_date']) ? $_POST['payment_date'] : '';

            // الحقول الإضافية للعقد
            $equip_shifts_contract = isset($_POST['equip_shifts_contract']) ? intval($_POST['equip_shifts_contract']) : 0;
            $shift_contract = isset($_POST['shift_contract']) ? intval($_POST['shift_contract']) : 0;
            $equip_total_contract_daily = isset($_POST['equip_total_contract']) ? intval($_POST['equip_total_contract']) : 0;
            $total_contract_permonth = isset($_POST['total_contract_permonth']) ? intval($_POST['total_contract_permonth']) : 0;
            $total_contract_units = isset($_POST['total_contract']) ? intval($_POST['total_contract']) : 0;


            if ($has_supplier_filter && $supplier_id_post !== $supplier_id) {
              die('بيانات المورد غير متطابقة');
            }

            // حقول العقد الموحّدة (تحديثًا وإدراجًا) — الكتابة عبر البوابة حصرًا
            $contract_fields = array(
              'project_id'                  => $project_id,
              'project_contract_id'         => $project_contract_id,
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
              'price_currency_contract'     => $price_currency_contract,
              'paid_contract'               => $paid_contract,
              'payment_time'                => $payment_time,
              'guarantees'                  => $guarantees,
              'payment_date'                => $payment_date,
            );

            $result = false;
            $contract_id = 0;
            try {
              if ($id > 0) {
                // تعديل — بشرط الأصل (العقد يخصّ المورد المرسَل) والنطاق عبر البوابة
                $scg->update('supplierscontracts', $contract_fields,
                  array('id' => $id, 'supplier_id' => $supplier_id_post));
                $contract_id = $id;
              } else {
                // إضافة — company_id تحقنه البوابة
                $insert_fields = array('supplier_id' => $supplier_id_post) + $contract_fields;
                $contract_id = (int) $scg->insert('supplierscontracts', $insert_fields);
              }
              $result = true;
            } catch (\Throwable $t) {
              error_log('supplierscontracts.php save failed: ' . $t->getMessage());
              $result = false;
            }

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
                    'equip_size' => isset($_POST["equip_size_$i"]) ? intval($_POST["equip_size_$i"]) : 0,
                    'equip_count' => isset($_POST["equip_count_$i"]) ? intval($_POST["equip_count_$i"]) : 0,
                    'equip_count_basic' => isset($_POST["equip_count_basic_$i"]) ? intval($_POST["equip_count_basic_$i"]) : 0,
                    'equip_count_backup' => isset($_POST["equip_count_backup_$i"]) ? intval($_POST["equip_count_backup_$i"]) : 0,
                    'equip_shifts' => isset($_POST["equip_shifts_$i"]) ? intval($_POST["equip_shifts_$i"]) : 0,
                    'equip_unit' => isset($_POST["equip_unit_$i"]) ? $_POST["equip_unit_$i"] : '',
                    'shift1_start' => isset($_POST["shift1_start_$i"]) ? $_POST["shift1_start_$i"] : '',
                    'shift1_end' => isset($_POST["shift1_end_$i"]) ? $_POST["shift1_end_$i"] : '',
                    'shift2_start' => isset($_POST["shift2_start_$i"]) ? $_POST["shift2_start_$i"] : '',
                    'shift2_end' => isset($_POST["shift2_end_$i"]) ? $_POST["shift2_end_$i"] : '',
                    'shift_hours' => isset($_POST["shift_hours_$i"]) ? floatval($_POST["shift_hours_$i"]) : 0,
                    'equip_total_month' => isset($_POST["equip_total_month_$i"]) ? floatval($_POST["equip_total_month_$i"]) : 0,
                    'equip_monthly_target' => isset($_POST["equip_target_per_month_$i"]) ? floatval($_POST["equip_target_per_month_$i"]) : 0,
                    'equip_total_contract' => isset($_POST["equip_total_contract_$i"]) ? floatval($_POST["equip_total_contract_$i"]) : 0,
                    'equip_price' => isset($_POST["equip_price_$i"]) ? floatval($_POST["equip_price_$i"]) : 0,
                    'equip_price_currency' => isset($_POST["equip_price_currency_$i"]) ? $_POST["equip_price_currency_$i"] : '',
                    'equip_operators' => isset($_POST["equip_operators_$i"]) ? intval($_POST["equip_operators_$i"]) : 0,
                    'equip_supervisors' => isset($_POST["equip_supervisors_$i"]) ? intval($_POST["equip_supervisors_$i"]) : 0,
                    'equip_technicians' => isset($_POST["equip_technicians_$i"]) ? intval($_POST["equip_technicians_$i"]) : 0,
                    'equip_assistants' => isset($_POST["equip_assistants_$i"]) ? intval($_POST["equip_assistants_$i"]) : 0
                  ];
                }
              }

              // إضافة بيانات المعدات الجديدة — استبدال الأبناء الذرّي عبر البوابة
              // (سلوك الأصل: لا مساس بالأبناء القدامى إن لم تُرسل معدات جديدة)
              if (!empty($equipment_array)) {
                try {
                  $scg->replaceChildren('supplierscontracts', $contract_id,
                    'suppliercontractequipments', 'contract_id', $equipment_array,
                    'حفظ عقد مورد: استبدال معداته');
                } catch (\Throwable $t) {
                  error_log('supplierscontracts.php children replace failed: ' . $t->getMessage());
                }
              }
            }

            echo "<script>window.location.href='" . $redirect_after_save . "';</script>";
            exit;
          }

          // جلب عقود الموردين مع فلترة المورد/المشروع — العزل عبر البوابة
          $scl_extra = ''; $scl_params = array();
          if ($has_supplier_filter) {
            $scl_extra .= " AND sc.supplier_id = ?"; $scl_params[] = $supplier_id;
          } elseif ($filter_supplier_id > 0) {
            $scl_extra .= " AND sc.supplier_id = ?"; $scl_params[] = $filter_supplier_id;
          }
          if ($filter_project_id > 0) {
            $scl_extra .= " AND sc.project_id = ?"; $scl_params[] = $filter_project_id;
          }

          try {
            $contracts_rows = $scg->scopedQuery(array(
              'scope'  => array('sc' => 'supplierscontracts'),
              'enrich' => array('s' => 'suppliers', 'op' => 'project'), // أسماء عرضية — LEFT بلا تنطيق (سلوك الأصل)
            ), "SELECT sc.*,
                  s.name AS supplier_name,
                  op.name AS project_name
                  FROM supplierscontracts sc
                  LEFT JOIN suppliers s ON sc.supplier_id = s.id
                  LEFT JOIN project op ON sc.project_id = op.id
                  WHERE {TENANT_SCOPE}$scl_extra
                  ORDER BY sc.id DESC", $scl_params);
          } catch (\Throwable $t) { $contracts_rows = array(); }
          $i = 1;


          {
            foreach ($contracts_rows as $row) {

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
              $actions_html .= "<a href='supplierscontracts_details.php?id=" . $row['id'] . "' class='action-btn view' title='عرض التفاصيل'><i class='fas fa-eye'></i></a>";

              if ($can_edit) {
                $actions_html .= "<a href='javascript:void(0)' class='editBtn action-btn edit' title='تعديل'
             data-id='" . $row['id'] . "'
             data-supplier_id='" . (isset($row['supplier_id']) ? intval($row['supplier_id']) : 0) . "'
             data-project_id='" . $row['project_id'] . "'
             data-project_contract_id='" . (isset($row['project_contract_id']) ? $row['project_contract_id'] : '') . "'
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

             data-forecasted_contracted_hours='" . $row['forecasted_contracted_hours'] . "'><i class='fas fa-edit'></i></a>";
              }
              if ($can_delete) {
                $actions_html .= "<a href='supplierscontracts.php?id=" . intval($row['supplier_id']) . "&delete_id=" . $row['id'] . "' onclick='return confirm(\"هل أنت متأكد من حذف العقد؟\")' class='action-btn delete' title='حذف'><i class='fas fa-trash-alt'></i></a>";
              }
              $actions_html .= "</div>";

              echo "<tr>";
              echo "<td class='group-status'>" . $actions_html . "</td>";

              // المعلومات الأساسية
              echo "<td class='group-basic'>" . $row['id'] . "#</td>";
              $mineProjectName = isset($row['project_name']) ? $row['project_name'] : '-';
              echo "<td class='group-basic'>" . $mineProjectName . "</td>";
              echo "<td class='group-basic'>" . (isset($row['supplier_name']) && $row['supplier_name'] !== '' ? $row['supplier_name'] : '-') . "</td>";
              echo "<td class='group-basic'>" . (isset($row['project_contract_id']) ? 'عقد #' . $row['project_contract_id'] : '-') . "</td>";

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
    // تهيئةُ جدولِ عقودِ الموردين انتقلت إلى المكوّنِ المركزي
    // (assets/js/ui-unification.js — initializeMissingDataTables):
    // لغةٌ عربية وضبطُ أعمدةٍ وزرُّ إكسل موحَّد. والتمريرُ الأفقيُّ من
    // القاعدةِ الصفحية .contracts-main .table-container (overflow-x:auto).

    $(document).ready(function () {
      // ==================== Group Toggle (unified) ====================
      // موحّد عبر assets/js/column-groups.js — مفتاح مستقل خاص بعقود الموردين.
      // الأصنافُ اعتُمدت باسمِ العائلةِ المؤسسية ems-btn — المحدِّدان يمرَّران صراحةً
      (function () {
        function go() {
          if (window.EmsColumnGroups) {
            EmsColumnGroups.init({
              storageKey: 'supplierContractGroupStates', mode: 'classic',
              buttons: '.ems-btn-group-toggle[data-group]',
              allButton: '.ems-btn-group-toggle-all'
            });
          }
        }
        if (window.EmsColumnGroups) { go(); } else { window.addEventListener('DOMContentLoaded', go); }
      })();
    });


    // التحكم في إظهار وإخفاء الفورم
    const toggleContractFormBtn = document.getElementById('toggleForm');
    const contractForm = document.getElementById('projectForm');

    if (toggleContractFormBtn && contractForm) {
      toggleContractFormBtn.addEventListener('click', function () {
        contractForm.style.display = contractForm.style.display === "none" ? "block" : "none";
      });
    }

    // زر الإلغاء — إخفاء الفورم وتفريغ الحقول
    const contractFormCancelBtn = document.getElementById('contractFormCancelBtn');
    if (contractFormCancelBtn && contractForm) {
      contractFormCancelBtn.addEventListener('click', function () {
        contractForm.style.display = "none";
        contractForm.reset();
        const cid = document.getElementById('contract_id');
        if (cid) cid.value = '';
      });
    }
  })();

</script>

<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

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

  // تحديث خيارات نوع المعدة لإخفاء الأنواع المختارة
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

    // تحديث الإجماليات مباشرة عند تغيير التواريخ
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
        <div class="sc-equip-box">
          <div class="sc-equip-head">
            <h6 class="sc-equip-head-title">المعدات رقم ${equipmentIndex}</h6>
            <button type="button" class="removeEquipmentBtn sc-equip-remove-btn" data-index="${equipmentIndex}">
              <i class="fa fa-trash"></i> حذف
            </button>
          </div>
          <div class="form-grid">
            <div class="field md-3 sm-6">
              <label>نوع المعدة</label>
              <div class="control">
                <select name="equip_type_${equipmentIndex}" aria-label="نوع المعدة للمعدة رقم ${equipmentIndex}" class="equip-type">
                  <?php echo $equipmentTypeOptionsHtml; ?>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>عدد المعدات</label>
              <div class="control"><input name="equip_count_${equipmentIndex}" aria-label="عدد المعدات للمعدة رقم ${equipmentIndex}" type="number" min="0"></div>
            </div>

            <div class="field md-3 sm-6">
              <label><span class="sc-dot-basic">■</span> المعدات الأساسية</label>
              <div class="control"><input name="equip_count_basic_${equipmentIndex}" type="number" min="0" aria-label="عدد المعدات الأساسية للمعدة رقم ${equipmentIndex}" class="sc-input-basic"></div>
            </div>

            <div class="field md-3 sm-6">
              <label><span class="sc-dot-backup">■</span> المعدات الاحتياطية</label>
              <div class="control"><input name="equip_count_backup_${equipmentIndex}" type="number" min="0" aria-label="عدد المعدات الاحتياطية للمعدة رقم ${equipmentIndex}" class="sc-input-backup"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>عدد المساعدين</label>
              <div class="control"><input name="equip_assistants_${equipmentIndex}" aria-label="عدد المساعدين للمعدة رقم ${equipmentIndex}" type="number" min="0"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>عدد الورديات</label>
              <div class="control"><input name="equip_shifts_${equipmentIndex}" type="number" min="0" placeholder="مثال: 2" aria-label="مثال: 2"></div>
            </div>

            <!-- أوقات الورديات -->
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> بداية الوردية الأولى</label>
              <div class="control"><input name="shift1_start_${equipmentIndex}" type="time" placeholder="مثال: 08:00" aria-label="مثال: 08:00"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> نهاية الوردية الأولى</label>
              <div class="control"><input name="shift1_end_${equipmentIndex}" type="time" placeholder="مثال: 16:00" aria-label="مثال: 16:00"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> بداية الوردية الثانية</label>
              <div class="control"><input name="shift2_start_${equipmentIndex}" type="time" placeholder="مثال: 16:00" aria-label="مثال: 16:00"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> نهاية الوردية الثانية</label>
              <div class="control"><input name="shift2_end_${equipmentIndex}" type="time" placeholder="مثال: 00:00" aria-label="مثال: 00:00"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>وحدة القياس</label>
              <div class="control">
                <select name="equip_unit_${equipmentIndex}" aria-label="وحدة القياس للمعدة رقم ${equipmentIndex}" class="equip-unit">
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
              <div class="control"><input name="shift_hours_${equipmentIndex}" aria-label="ساعات الوردية للمعدة رقم ${equipmentIndex}" type="number" min="0"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>إجمالي الساعات يومياً</label>
              <div class="control"><input name="equip_total_month_${equipmentIndex}" type="number" readonly placeholder="يُحتسب تلقائياً" aria-label="يُحتسب تلقائياً"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>وحدات العمل في الشهر</label>
              <div class="control"><input name="equip_target_per_month_${equipmentIndex}" aria-label="وحدات العمل في الشهر للمعدة رقم ${equipmentIndex}" type="number" min="0"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>إجمالي ساعات العقد</label>
              <div class="control"><input name="equip_total_contract_${equipmentIndex}" type="number" readonly placeholder="يُحتسب تلقائياً" aria-label="يُحتسب تلقائياً"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>العملة</label>
              <div class="control">
                <select name="equip_price_currency_${equipmentIndex}" aria-label="عملة سعر الوحدة للمعدة رقم ${equipmentIndex}">
                  <option value="">— اختر —</option>
                  <option value="دولار">دولار</option>
                  <option value="جنيه">جنيه</option>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>السعر</label>
              <div class="control"><input name="equip_price_${equipmentIndex}" type="number" min="0" step="0.01" placeholder="0.00" aria-label="0.00"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>عدد المشرفين</label>
              <div class="control"><input name="equip_supervisors_${equipmentIndex}" aria-label="عدد المشرفين للمعدة رقم ${equipmentIndex}" type="number" min="0"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>عدد الفنيين</label>
              <div class="control"><input name="equip_technicians_${equipmentIndex}" aria-label="عدد الفنيين للمعدة رقم ${equipmentIndex}" type="number" min="0"></div>
            </div>
          </div>
        </div>
      `;
    document.getElementById('equipmentSections').appendChild(newSection);

    // إضافة event listeners للحقول الجديدة المهمة للحسبة
    const countInput = newSection.querySelector(`input[name="equip_count_${equipmentIndex}"]`);
    const shiftHoursInput = newSection.querySelector(`input[name="shift_hours_${equipmentIndex}"]`);

    if (countInput) countInput.addEventListener('input', recalc);
    if (shiftHoursInput) shiftHoursInput.addEventListener('input', recalc);

    // إضافة event listener لتحديث خيارات نوع المعدة عند التغيير
    newSection.querySelectorAll('.equip-type').forEach(el => el.addEventListener('change', updateEquipmentTypeOptions));

    // إضافة event listener لزر الحذف
    newSection.querySelector('.removeEquipmentBtn').addEventListener('click', function () {
      newSection.remove();
      recalc();
      updateEquipmentTypeOptions();
    });

    // تحديث خيارات نوع المعدة بعد إضافة القسم
    updateEquipmentTypeOptions();

    // تشغيل الحسبة فوراً بعد إضافة القسم الجديد
    recalc();
  }

  function recalc() {
    const days = num(fields.contractDays.value);

    // حساب إجمالي المعدات
    let totalEquipMonth = 0;
    let totalEquipContract = 0;

    // حساب كل قسم معدات
    document.querySelectorAll('.equipment-section').forEach(section => {
      const index = section.getAttribute('data-index');
      const countInput = section.querySelector(`input[name="equip_count_${index}"]`);
      const countBasicInput = section.querySelector(`input[name="equip_count_basic_${index}"]`);
      const targetInput = section.querySelector(`input[name="shift_hours_${index}"]`);
      const monthInput = section.querySelector(`input[name="equip_total_month_${index}"]`);
      const contractInput = section.querySelector(`input[name="equip_total_contract_${index}"]`);

      if (countInput && targetInput) {
        const countBasic = num(countBasicInput ? countBasicInput.value : 0);
        // المعدات الاحتياطية لا تدخل في الحساب (للتوثيق فقط)
        const rawCount = num(countInput.value);
        const count = countBasic > 0 ? countBasic : rawCount;
        const target = num(targetInput.value);
        const sectionMonth = count * target;
        // حساب إجمالي الساعات على أساس الأيام بدلاً من الشهور
        // نفترض أن الـ target هو الساعات اليومية للمعدة
        const sectionContract = sectionMonth * days;

        monthInput.value = sectionMonth;
        contractInput.value = sectionContract;

        totalEquipMonth += sectionMonth;
        totalEquipContract += sectionContract;
      }
    });

    const monthTotal = totalEquipMonth;
    const contractTotal = totalEquipContract;
    // الهدف الشهري = إجمالي الساعات اليومية للأسطول × 30 (وحدة شهرية متّسقة مع اسم العمود والتقارير)
    const monthlyTotal = monthTotal * 30;

    fields.kpiEquipMonth.textContent = fmt(totalEquipMonth);
    fields.kpiMonthTotal.textContent = fmt(monthlyTotal);
    fields.kpiContractTotal.textContent = fmt(contractTotal);

    fields.hoursMonthlyTarget.value = monthlyTotal;
    fields.forecastedContractedHours.value = contractTotal;
  }

  // تشغيل الحسبة عند تغيير أي مدخل
  document.addEventListener('input', function (e) {
    if (e.target.closest('#projectForm')) {
      recalc();
    }
  });

  // تحديث خيارات نوع المعدة عند التغيير
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

  // إضافة event listeners للقسم الأول من المعدات
  document.querySelectorAll('.equipment-section').forEach(section => {
    const index = section.getAttribute('data-index') || '1';
    const countInput = section.querySelector(`input[name="equip_count_${index}"]`);
    const shiftHoursInput = section.querySelector(`input[name="shift_hours_${index}"]`);
    const equipTypeSelect = section.querySelector(`select[name="equip_type_${index}"]`);

    if (countInput) countInput.addEventListener('input', recalc);
    if (shiftHoursInput) shiftHoursInput.addEventListener('input', recalc);
    if (equipTypeSelect) equipTypeSelect.addEventListener('change', updateEquipmentTypeOptions);
  });

  // جلب عقود المشروع عند تغيير المشروع
  $('#project_id').on('change', function () {
    const projectId = $(this).val();
    $('#project_contract_id').prop('disabled', true).html('<option value="">— جاري التحميل... —</option>');
    $('#projectHoursInfo').fadeOut();

    if (projectId) {
      $.ajax({
        url: 'get_mine_contracts.php',
        type: 'POST',
        data: { project_id: projectId },
        dataType: 'json',
        success: function (response) {
          if (response.success && response.contracts.length > 0) {
            let options = '<option value="">— اختر العقد —</option>';
            response.contracts.forEach(function (contract) {
              options += `<option value="${contract.id}">${contract.display_name}</option>`;
            });
            $('#project_contract_id').html(options).prop('disabled', false);
          } else {
            $('#project_contract_id').html('<option value="">— لا توجد عقود لهذا المشروع —</option>').prop('disabled', true);
          }
        },
        error: function () {
          $('#project_contract_id').html('<option value="">— خطأ في التحميل —</option>').prop('disabled', true);
        }
      });
    } else {
      $('#project_contract_id').html('<option value="">— اختر المشروع أولاً —</option>').prop('disabled', true);
    }
  });

  // جلب بيانات ساعات العقد عند تغيير العقد
  $('#project_contract_id').on('change', function () {
    const contractId = $(this).val();
    const supplierContractId = $('#contract_id').val();
    if (contractId) {
      $.ajax({
        url: 'get_project_hours.php',
        type: 'POST',
        data: {
          project_contract_id: contractId,
          supplier_contract_id: supplierContractId || 0
        },
        dataType: 'json',
        success: function (response) {
          if (response.success) {
            $('#contractTotalHours').text(new Intl.NumberFormat('ar-EG').format(response.contract_total_hours));
            $('#suppliersContractedHours').text(new Intl.NumberFormat('ar-EG').format(response.suppliers_contracted_hours));
            $('#remainingHours').text(new Intl.NumberFormat('ar-EG').format(response.remaining_hours));

            // عرض تفصيل المعدات
            var breakdownDiv = $('#equipmentBreakdown');
            breakdownDiv.empty();

            if (response.equipment_breakdown && response.equipment_breakdown.length > 0) {
              var breakdownHtml = '<div class="sc-bd"><strong class="sc-bd-title-blue">تفصيل الساعات:</strong>';

              response.equipment_breakdown.forEach(function (item) {
                var percentage = ((item.hours / response.contract_total_hours) * 100).toFixed(1);
                breakdownHtml += '<div class="sc-bd-item">';
                breakdownHtml += '<div class="sc-bd-row">';
                breakdownHtml += '<span><i class="fas fa-tools sc-ic-blue"></i>' + item.type + '</span>';
                breakdownHtml += '<span class="sc-bd-val-blue">' + new Intl.NumberFormat('ar-EG').format(item.hours) + ' ساعة (' + percentage + '%)</span>';
                breakdownHtml += '</div>';
                breakdownHtml += '<div class="sc-bd-sub">';
                breakdownHtml += '<span><span class="sc-chip-basic">أساسية</span> ' + item.count_basic + '</span>';
                breakdownHtml += '<span><span class="sc-chip-backup">احتياطية</span> ' + item.count_backup + '</span>';
                breakdownHtml += '<span><span class="sc-chip-total">إجمالي</span> ' + item.count + '</span>';
                breakdownHtml += '</div>';
                breakdownHtml += '</div>';
              });

              breakdownHtml += '</div>';
              breakdownDiv.html(breakdownHtml);
            } else {
              breakdownDiv.html('<span class="sc-empty-note">لا توجد معدات مسجلة لهذا العقد</span>');
            }

            // عرض تفصيل الموردين وساعاتهم التعاقدية
            var suppliersDiv = $('#suppliersBreakdown');
            suppliersDiv.empty();

            if (response.suppliers_list && response.suppliers_list.length > 0) {
              var suppliersHtml = '<div class="sc-bd"><strong class="sc-bd-title-red">تفصيل الموردين:</strong>';

              response.suppliers_list.forEach(function (supplier) {
                var percentage = response.suppliers_contracted_hours > 0 ? ((supplier.hours / response.suppliers_contracted_hours) * 100).toFixed(1) : 0;

                suppliersHtml += '<div class="sc-sup-item">';
                suppliersHtml += '<div class="sc-bd-row">';
                suppliersHtml += '<span><i class="fas fa-building sc-ic-red"></i>' + supplier.name + '</span>';
                suppliersHtml += '<span class="sc-sup-val">' + new Intl.NumberFormat('ar-EG').format(supplier.hours) + ' ساعة (' + percentage + '%)</span>';
                suppliersHtml += '</div>';

                // معلومات العقد
                if (supplier.contract_date || supplier.start_date || supplier.end_date) {
                  suppliersHtml += '<div class="sc-sup-sub">';
                  if (supplier.contract_date) {
                    suppliersHtml += '<span class="sc-meta"><i class="fas fa-calendar-check sc-ic-red-plain"></i> توقيع: ' + supplier.contract_date + '</span>';
                  }
                  if (supplier.start_date) {
                    suppliersHtml += '<span class="sc-meta"><i class="fas fa-play-circle sc-ic-green"></i> بداية: ' + supplier.start_date + '</span>';
                  }
                  if (supplier.end_date) {
                    suppliersHtml += '<span class="sc-meta"><i class="fas fa-stop-circle sc-ic-crimson"></i> نهاية: ' + supplier.end_date + '</span>';
                  }
                  suppliersHtml += '</div>';
                }

                // شريط نسبة المساهمة — العرضُ محسوبٌ لحظيًّا من الاستجابة
                suppliersHtml += '<div class="sc-bar-wrap">';
                suppliersHtml += '<div class="sc-bar-track">';
                suppliersHtml += '<div class="sc-bar-fill-red" data-allow-style style="width: ' + percentage + '%;"></div>';
                suppliersHtml += '</div>';
                suppliersHtml += '<span class="sc-bar-note">نسبة المساهمة: ' + percentage + '%</span>';
                suppliersHtml += '</div>';

                suppliersHtml += '</div>';
              });

              suppliersHtml += '</div>';
              suppliersDiv.html(suppliersHtml);
            } else {
              suppliersDiv.html('<span class="sc-empty-note-block">لا توجد عقود موردين لهذا المشروع</span>');
            }

            // عرض تفصيل الساعات المتبقية حسب نوع الآلية
            var remainingDiv = $('#remainingBreakdown');
            remainingDiv.empty();

            if (response.remaining_breakdown && response.remaining_breakdown.length > 0) {
              var remainingHtml = '<div class="sc-bd"><strong class="sc-bd-title-green">تفصيل الساعات المتبقية:</strong>';

              response.remaining_breakdown.forEach(function (item) {
                var percentage = response.remaining_hours > 0 ? ((item.remaining_hours / response.remaining_hours) * 100).toFixed(1) : 0;
                // لونُ الحالةِ محسوبٌ من الرصيدِ المتبقي — رموزُ design-tokens مع احتياطيٍّ حرفي
                var statusColor = item.remaining_hours > 0 ? 'var(--c-43a047, #43a047)' : (item.remaining_hours < 0 ? 'var(--c-d32f2f, #d32f2f)' : 'var(--c-999999, #999999)');
                var statusIcon = item.remaining_hours > 0 ? 'fa-check-circle' : (item.remaining_hours < 0 ? 'fa-exclamation-triangle' : 'fa-minus-circle');

                remainingHtml += '<div class="sc-rem-item" data-allow-style style="border-left-color: ' + statusColor + ';">';
                remainingHtml += '<div class="sc-bd-row">';
                remainingHtml += '<span><i class="fas ' + statusIcon + ' sc-ic-dyn" data-allow-style style="color: ' + statusColor + ';"></i>' + item.type + '</span>';
                remainingHtml += '<span class="sc-rem-val" data-allow-style style="color: ' + statusColor + ';">' + new Intl.NumberFormat('ar-EG').format(item.remaining_hours) + ' ساعة</span>';
                remainingHtml += '</div>';
                remainingHtml += '<div class="sc-rem-sub">';
                remainingHtml += '<span class="sc-tone-blue"><i class="fas fa-calculator"></i> إجمالي: ' + new Intl.NumberFormat('ar-EG').format(item.total_hours) + '</span>';
                remainingHtml += '<span class="sc-tone-red"><i class="fas fa-handshake"></i> متعاقد: ' + new Intl.NumberFormat('ar-EG').format(item.suppliers_hours) + '</span>';
                remainingHtml += '<span data-allow-style style="color: ' + statusColor + ';"><i class="fas fa-balance-scale"></i> متبقي: ' + new Intl.NumberFormat('ar-EG').format(item.remaining_hours) + '</span>';
                remainingHtml += '</div>';

                // إضافة شريط تقدم — اللونُ والعرضُ محسوبان لحظيًّا من نسبةِ التعاقد
                if (item.total_hours > 0) {
                  var progressPercentage = Math.min(100, (item.suppliers_hours / item.total_hours) * 100);
                  var progressColor = progressPercentage >= 90 ? 'var(--c-d32f2f, #d32f2f)' : (progressPercentage >= 70 ? 'var(--c-ffa000, #ffa000)' : 'var(--c-43a047, #43a047)');
                  remainingHtml += '<div class="sc-bar-wrap">';
                  remainingHtml += '<div class="sc-bar-track">';
                  remainingHtml += '<div class="sc-bar-fill" data-allow-style style="background: ' + progressColor + '; width: ' + progressPercentage.toFixed(1) + '%;"></div>';
                  remainingHtml += '</div>';
                  remainingHtml += '<span class="sc-bar-note">نسبة التعاقد: ' + progressPercentage.toFixed(1) + '%</span>';
                  remainingHtml += '</div>';
                }

                remainingHtml += '</div>';
              });

              remainingHtml += '</div>';
              remainingDiv.html(remainingHtml);
            } else {
              remainingDiv.html('<span class="sc-empty-note-block">لا توجد تفاصيل</span>');
            }

            $('#projectHoursInfo').fadeIn();
          } else {
            $('#projectHoursInfo').fadeOut();
          }
        },
        error: function () {
          $('#projectHoursInfo').fadeOut();
        }
      });
    } else {
      $('#projectHoursInfo').fadeOut();
    }
  });

  // تعبئة الفورم عند التعديل
  $(document).on("click", ".editBtn", function () {
    $("#projectForm").show();
    $("#contract_id").val($(this).data("id"));

    // تحميل المشروع والعقد
    const supplierId = $(this).data("supplier_id");
    const projectId = $(this).data("project_id");
    const projectContractId = $(this).data("project_contract_id");

    if ($('#supplier_id_select').length) {
      $('#supplier_id_select').val(supplierId || '');
    }

    $("#project_id").val(projectId);

    // تحميل عقود المشروع
    if (projectId) {
      $.ajax({
        url: 'get_mine_contracts.php',
        type: 'POST',
        data: { project_id: projectId },
        dataType: 'json',
        success: function (response) {
          if (response.success && response.contracts.length > 0) {
            let options = '<option value="">— اختر العقد —</option>';
            response.contracts.forEach(function (contract) {
              const selected = contract.id == projectContractId ? 'selected' : '';
              options += `<option value="${contract.id}" ${selected}>${contract.display_name}</option>`;
            });
            $('#project_contract_id').html(options).prop('disabled', false);

            if (projectContractId) {
              $('#project_contract_id').trigger('change');
            }
          }
        }
      });
    }

    $("#projectForm [name='contract_signing_date']").val($(this).data("contract_signing_date"));
    $("#projectForm [name='grace_period_days']").val($(this).data("grace_period_days"));
    $("#projectForm [name='contract_duration_days']").val($(this).data("contract_duration_days"));
    $("#projectForm [name='actual_start']").val($(this).data("actual_start"));
    $("#projectForm [name='actual_end']").val($(this).data("actual_end"));

    // اعتمد التاريخين كمصدر الحقيقة قبل أي إعادة حساب
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
      url: 'get_supplier_contract_equipments.php',
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
              $(`input[name="equip_assistants_1"]`).val(equip.equip_assistants);
              $(`input[name="equip_shifts_1"]`).val(equip.equip_shifts);
              $(`select[name="equip_unit_1"]`).val(equip.equip_unit);
              $(`input[name="shift1_start_1"]`).val(equip.shift1_start);
              $(`input[name="shift1_end_1"]`).val(equip.shift1_end);
              $(`input[name="shift2_start_1"]`).val(equip.shift2_start);
              $(`input[name="shift2_end_1"]`).val(equip.shift2_end);
              $(`input[name="shift_hours_1"]`).val(equip.shift_hours);
              $(`input[name="equip_total_month_1"]`).val(equip.equip_total_month);
              $(`input[name="equip_target_per_month_1"]`).val(equip.equip_target_per_month);
              $(`input[name="equip_total_contract_1"]`).val(equip.equip_total_contract);
              $(`input[name="equip_price_1"]`).val(equip.equip_price);
              $(`select[name="equip_price_currency_1"]`).val(equip.equip_price_currency);
              $(`input[name="equip_supervisors_1"]`).val(equip.equip_supervisors);
              $(`input[name="equip_technicians_1"]`).val(equip.equip_technicians);
              equipmentIndex = 1;
            } else {
              // إضافة أقسام جديدة
              equipmentIndex++;
              const newSection = document.createElement('div');
              newSection.className = 'equipment-section';
              newSection.setAttribute('data-index', equipmentIndex);
              newSection.innerHTML = `
                  <div class="sc-equip-box">
                    <div class="sc-equip-head">
                      <h6 class="sc-equip-head-title">المعدات رقم ${equipmentIndex}</h6>
                      <button type="button" class="removeEquipmentBtn sc-equip-remove-btn" data-index="${equipmentIndex}">
                        <i class="fa fa-trash"></i> حذف
                      </button>
                    </div>
                    <div class="form-grid">
                      <div class="field md-3 sm-6">
                        <label>نوع المعدة</label>
                        <div class="control">
                          <select name="equip_type_${equipmentIndex}" aria-label="نوع المعدة للمعدة رقم ${equipmentIndex}" class="equip-type">
                            <?php echo $equipmentTypeOptionsHtml; ?>
                          </select>
                        </div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد المعدات</label>
                        <div class="control"><input name="equip_count_${equipmentIndex}" aria-label="عدد المعدات للمعدة رقم ${equipmentIndex}" type="number" min="0" value="${equip.equip_count}"></div>
                      </div>

                      <div class="field md-3 sm-6">
                        <label><span class="sc-dot-basic">■</span> المعدات الأساسية</label>
                        <div class="control"><input name="equip_count_basic_${equipmentIndex}" type="number" min="0" aria-label="عدد المعدات الأساسية للمعدة رقم ${equipmentIndex}" class="sc-input-basic" value="${equip.equip_count_basic || 0}"></div>
                      </div>

                      <div class="field md-3 sm-6">
                        <label><span class="sc-dot-backup">■</span> المعدات الاحتياطية</label>
                        <div class="control"><input name="equip_count_backup_${equipmentIndex}" type="number" min="0" aria-label="عدد المعدات الاحتياطية للمعدة رقم ${equipmentIndex}" class="sc-input-backup" value="${equip.equip_count_backup || 0}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد المساعدين</label>
                        <div class="control"><input name="equip_assistants_${equipmentIndex}" aria-label="عدد المساعدين للمعدة رقم ${equipmentIndex}" type="number" min="0" value="${equip.equip_assistants}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد الورديات</label>
                        <div class="control"><input name="equip_shifts_${equipmentIndex}" type="number" min="0" placeholder="مثال: 2" value="${equip.equip_shifts}" aria-label="مثال: 2"></div>
                      </div>

                      <!-- أوقات الورديات -->
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> بداية الوردية الأولى</label>
                        <div class="control"><input name="shift1_start_${equipmentIndex}" aria-label="بداية الوردية الأولى للمعدة رقم ${equipmentIndex}" type="time" value="${equip.shift1_start || ''}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> نهاية الوردية الأولى</label>
                        <div class="control"><input name="shift1_end_${equipmentIndex}" aria-label="نهاية الوردية الأولى للمعدة رقم ${equipmentIndex}" type="time" value="${equip.shift1_end || ''}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> بداية الوردية الثانية</label>
                        <div class="control"><input name="shift2_start_${equipmentIndex}" aria-label="بداية الوردية الثانية للمعدة رقم ${equipmentIndex}" type="time" value="${equip.shift2_start || ''}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> نهاية الوردية الثانية</label>
                        <div class="control"><input name="shift2_end_${equipmentIndex}" aria-label="نهاية الوردية الثانية للمعدة رقم ${equipmentIndex}" type="time" value="${equip.shift2_end || ''}"></div>
                      </div>

                      <div class="field md-3 sm-6">
                        <label>وحدة القياس</label>
                        <div class="control">
                          <select name="equip_unit_${equipmentIndex}" aria-label="وحدة القياس للمعدة رقم ${equipmentIndex}" class="equip-unit">
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
                        <div class="control"><input name="shift_hours_${equipmentIndex}" aria-label="ساعات الوردية للمعدة رقم ${equipmentIndex}" type="number" min="0" value="${equip.shift_hours}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>إجمالي الساعات يومياً</label>
                        <div class="control"><input name="equip_total_month_${equipmentIndex}" type="number" readonly placeholder="يُحتسب تلقائياً" value="${equip.equip_total_month}" aria-label="يُحتسب تلقائياً"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>وحدات العمل في الشهر</label>
                        <div class="control"><input name="equip_target_per_month_${equipmentIndex}" aria-label="وحدات العمل في الشهر للمعدة رقم ${equipmentIndex}" type="number" min="0" value="${equip.equip_monthly_target || 0}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>إجمالي ساعات العقد</label>
                        <div class="control"><input name="equip_total_contract_${equipmentIndex}" type="number" readonly placeholder="يُحتسب تلقائياً" value="${equip.equip_total_contract}" aria-label="يُحتسب تلقائياً"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>العملة</label>
                        <div class="control">
                          <select name="equip_price_currency_${equipmentIndex}" aria-label="عملة سعر الوحدة للمعدة رقم ${equipmentIndex}">
                            <option value="">— اختر —</option>
                            <option value="دولار" ${equip.equip_price_currency === 'دولار' ? 'selected' : ''}>دولار</option>
                            <option value="جنيه" ${equip.equip_price_currency === 'جنيه' ? 'selected' : ''}>جنيه</option>
                          </select>
                        </div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>السعر</label>
                        <div class="control"><input name="equip_price_${equipmentIndex}" type="number" min="0" step="0.01" placeholder="0.00" value="${equip.equip_price}" aria-label="0.00"></div>
                      </div>

                      <div class="field md-3 sm-6">
                        <label>عدد المشرفين</label>
                        <div class="control"><input name="equip_supervisors_${equipmentIndex}" aria-label="عدد المشرفين للمعدة رقم ${equipmentIndex}" type="number" min="0" value="${equip.equip_supervisors}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد الفنيين</label>
                        <div class="control"><input name="equip_technicians_${equipmentIndex}" aria-label="عدد الفنيين للمعدة رقم ${equipmentIndex}" type="number" min="0" value="${equip.equip_technicians}"></div>
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
</script>


<?php if (function_exists('ems_excel_render')) {
  ems_excel_render();
} ?>
</body>

</html>
