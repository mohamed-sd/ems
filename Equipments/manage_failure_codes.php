<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config.php';
include '../includes/permissions_helper.php';

// ── الصلاحيات من قاعدة البيانات
$page_permissions = check_page_permissions($conn, 'Equipments/manage_failure_codes.php');

if (!$page_permissions['can_view']) {
    ems_gov_flash_redirect('../main/dashboard.php', '❌ لا توجد صلاحية لعرض هذه الصفحة', 'GOV-PERM-403', '');
    exit();
}

$can_add    = $page_permissions['can_add'];
$can_edit   = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

$page_title = "تصنيفات الأعطال";

// بوابة العزل — failure_codes كتالوجٌ عامٌّ (T_GLOBAL) مُدار (managed): يُقرأ للجميع،
// ويُكتب بحوكمة صلاحيّة الصفحة (لا company_id — كتالوج مشترك بين كل الشركات).
$fc_gate = ems_tenant_db();

$success_msg = '';
$error_msg   = '';

// ══════════════════════════════════════════════════════════════
// معالجة طلبات POST
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // حذف (تعطيل ناعم)
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        if (!$can_delete) { $error_msg = "❌ لا توجد صلاحية حذف"; goto done; }
        $del_id = intval($_POST['del_id']);
        if ($del_id > 0) {
            // تعطيل ناعم (status=0) عبر البوابة — كتابةٌ محكومة بالـmanaged
            try { $fc_gate->update('failure_codes', array('status' => 0), array('id' => $del_id)); } catch (\Throwable $e) {}
            ems_gov_flash_redirect('manage_failure_codes.php', 'تم حذف الكود بنجاح ✅', 'GOV-OK-200', '');
            exit();
        }
        goto done;
    }

    // استعادة
    if (isset($_POST['action']) && $_POST['action'] === 'restore') {
        $res_id = intval($_POST['res_id']);
        if ($res_id > 0) {
            try { $fc_gate->update('failure_codes', array('status' => 1), array('id' => $res_id)); } catch (\Throwable $e) {}
            ems_gov_flash_redirect('manage_failure_codes.php', 'تم استعادة الكود ✅', 'GOV-OK-200', '');
            exit();
        }
        goto done;
    }

    // إضافة / تعديل
    if (!$can_add) { $error_msg = "❌ لا توجد صلاحية"; goto done; }

    // قيمٌ خام للبوابة (الربط بالمعاملات يتكفّل بالهروب؛ trim/strtoupper كالأصل)
    $edit_id          = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
    $equipment_type   = intval($_POST['equipment_type']);
    $event_type_code  = strtoupper(trim($_POST['event_type_code'] ?? ''));
    $event_type_name  = trim($_POST['event_type_name'] ?? '');
    $main_cat_code    = strtoupper(trim($_POST['main_category_code'] ?? ''));
    $main_cat_name    = trim($_POST['main_category_name'] ?? '');
    $sub_category     = trim($_POST['sub_category'] ?? '');
    $failure_detail   = trim($_POST['failure_detail'] ?? '');
    $full_code        = strtoupper(trim($_POST['full_code'] ?? ''));
    $status           = intval($_POST['status']);

    if ($equipment_type < 1 || empty($event_type_code) || empty($event_type_name)
        || empty($main_cat_code) || empty($main_cat_name) || empty($sub_category)
        || empty($failure_detail) || empty($full_code)) {
        $error_msg = "⚠️ يرجى تعبئة جميع الحقول الإلزامية";
        goto done;
    }

    // التحقق من تكرار full_code (كتالوجٌ عامّ — البحث بلا عزل شركة، بمعاملات مرتبطة)
    $dupWhere  = "full_code = ?";
    $dupParams = array($full_code);
    if ($edit_id > 0) { $dupWhere .= " AND id != ?"; $dupParams[] = $edit_id; }
    $dup = $fc_gate->selectOne('failure_codes', array('columns' => array('id'), 'whereRaw' => $dupWhere, 'params' => $dupParams));
    if ($dup) {
        $error_msg = "⚠️ الكود الكامل '$full_code' موجود مسبقاً";
        goto done;
    }

    // بيانات الكود (كتالوج عام managed — بلا company_id)
    $fc_data = array(
        'equipment_type'     => $equipment_type,
        'event_type_code'    => $event_type_code,
        'event_type_name'    => $event_type_name,
        'main_category_code' => $main_cat_code,
        'main_category_name' => $main_cat_name,
        'sub_category'       => $sub_category,
        'failure_detail'     => $failure_detail,
        'full_code'          => $full_code,
        'status'             => $status,
    );
    try {
        if ($edit_id > 0) {
            $fc_gate->update('failure_codes', $fc_data, array('id' => $edit_id));
            $msg_ok = "تم تعديل الكود بنجاح ✅";
        } else {
            $fc_gate->insert('failure_codes', $fc_data);
            $msg_ok = "تم إضافة الكود بنجاح ✅";
        }
        ems_gov_flash_redirect('manage_failure_codes.php', $msg_ok, 'GOV-INFO-200', '');
        exit();
    } catch (\Throwable $e) {
        $error_msg = "خطأ في الحفظ: " . $e->getMessage();
    }
}

done:

// رسالة من redirect
if (isset($_GET['msg'])) { $success_msg = htmlspecialchars($_GET['msg']); }

// بيانات تعديل
$edit_data = null;
$edit_id_get = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
if ($edit_id_get > 0 && $can_edit) {
    $edit_data = $fc_gate->selectOne('failure_codes', array('where' => array('id' => $edit_id_get)));
}

// فلاتر جانبية لبناء قوائم الـ select في الفورم
$eq_type_names = [1 => 'حفار (Excavator)', 2 => 'قلاب (Dump Truck)', 3 => 'خرامة (Drill)'];

// بيانات الجدول
$filter_eq   = isset($_GET['f_eq'])   ? intval($_GET['f_eq'])   : 0;
$filter_evt  = isset($_GET['f_evt'])  ? trim($_GET['f_evt'])    : '';
$filter_mc   = isset($_GET['f_mc'])   ? trim($_GET['f_mc'])     : '';
$filter_stat = isset($_GET['f_stat']) ? intval($_GET['f_stat']) : 1; // افتراضي: نشط

// شروط الفلترة بمعاملات مرتبطة (لا حقن)
$fc_where  = [];
$fc_params = [];
$fc_where[] = $filter_stat >= 0 ? "status = ?" : "1=1";
if ($filter_stat >= 0)   { $fc_params[] = $filter_stat; }
if ($filter_eq  > 0)     { $fc_where[] = "equipment_type = ?";     $fc_params[] = $filter_eq; }
if (!empty($filter_evt)) { $fc_where[] = "event_type_code = ?";    $fc_params[] = $filter_evt; }
if (!empty($filter_mc))  { $fc_where[] = "main_category_code = ?"; $fc_params[] = $filter_mc; }
$where_sql = implode(' AND ', $fc_where);

// كتالوجٌ عامٌّ عبر البوابة (بلا عزل شركة — مشترك بين كل الشركات)
$list_rows = $fc_gate->select('failure_codes', array(
    'whereRaw' => $where_sql,
    'params'   => $fc_params,
    'orderBy'  => 'equipment_type, event_type_code, main_category_code, full_code',
));
$total_count = count($list_rows);

// قوائم التصفية (قيم مميّزة) — أعمدة البوابة معرِّفاتٌ صرفة (لا تعابير مثل DISTINCT)،
// فتُجلَب الأزواج ثم تُميَّز في PHP (نفس نتيجة SELECT DISTINCT .. ORDER BY code).
$evt_list_rows = array();
foreach ($fc_gate->select('failure_codes', array('columns' => array('event_type_code', 'event_type_name'), 'whereRaw' => "status = 1", 'orderBy' => 'event_type_code')) as $r) {
    $evt_list_rows[$r['event_type_code'] . '|' . $r['event_type_name']] = $r;
}
$evt_list_rows = array_values($evt_list_rows);
$mc_list_rows = array();
foreach ($fc_gate->select('failure_codes', array('columns' => array('main_category_code', 'main_category_name'), 'whereRaw' => "status = 1", 'orderBy' => 'main_category_code')) as $r) {
    $mc_list_rows[$r['main_category_code'] . '|' . $r['main_category_name']] = $r;
}
$mc_list_rows = array_values($mc_list_rows);

// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<?php
// ── حساب إحصائيات سريعة
$stat_total  = $fc_gate->count('failure_codes');
$stat_active = $fc_gate->count('failure_codes', array('whereRaw' => "status = 1"));
$stat_eq1    = $fc_gate->count('failure_codes', array('whereRaw' => "equipment_type = 1 AND status = 1"));
$stat_eq2    = $fc_gate->count('failure_codes', array('whereRaw' => "equipment_type = 2 AND status = 1"));
$stat_eq3    = $fc_gate->count('failure_codes', array('whereRaw' => "equipment_type = 3 AND status = 1"));
?>

<div class="main fc-page ems-unified-page-shell">


   <!-- عنوان الصفحة -->
    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'تصنيفات الأعطال';
    $header_icon    = 'fas fa-exclamation-triangle';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('tag' => 'button', 'id' => 'toggleFormBtn', 'class' => 'add-btn', 'attrs' => 'onclick="toggleForm()"', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة كود جديد', 'label_class' => '');
    }
    $header_actions[] = array('href' => 'fleet_failures.php', 'class' => 'btn-primary', 'icon' => 'fas fa-chart-line', 'label' => 'تقرير الاخطاء');
    // ── نظام Excel الموحّد (Unified Excel Framework) ──
    require_once __DIR__ . '/../includes/excel_ui.php';
    foreach (ems_excel_header_actions('failure_codes', 'أكواد الأعطال', $can_add) as $__xlAction) { $header_actions[] = $__xlAction; }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <!-- ══ رسائل ══ -->
    <?php if ($success_msg): ?>
        <div class="success-message is-success"><i class="fas fa-check-circle"></i> <?= $success_msg ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="success-message is-error"><i class="fas fa-exclamation-circle"></i> <?= $error_msg ?></div>
    <?php endif; ?>

    <!-- ══ نموذج الإضافة / التعديل (بنفس بنية وتصميم فورم صفحة العملاء) ══ -->
    <?php if ($can_add): ?>
    <form method="POST" action="" id="fcForm" class="allforms<?= ($edit_data || $error_msg) ? ' allforms-visible' : '' ?>">
        <div class="card-header">
            <h5>
                <i class="fas fa-<?= $edit_data ? 'edit' : 'plus-circle' ?>"></i>
                <span id="fcFormTitle"><?= $edit_data ? 'تعديل كود العطل' : 'إضافة كود عطل جديد' ?></span>
            </h5>
        </div>
        <input type="hidden" name="edit_id" id="edit_id" value="<?= $edit_data ? intval($edit_data['id']) : '' ?>">
        <div class="card shadow-sm pu-form-card">
            <div class="card-body">
                <div class="form-grid">

                    <!-- نوع المعدة -->
                    <div>
                        <label for="f_equipment_type"><i class="fas fa-cog"></i> نوع المعدة <span class="required-indicator">*</span></label>
                        <select name="equipment_type" id="f_equipment_type" required>
                            <option value="">-- اختر --</option>
                            <option value="1" <?= ($edit_data['equipment_type']??'')=='1' ? 'selected':'' ?>>حفار (Excavator)</option>
                            <option value="2" <?= ($edit_data['equipment_type']??'')=='2' ? 'selected':'' ?>>قلاب (Dump Truck)</option>
                            <option value="3" <?= ($edit_data['equipment_type']??'')=='3' ? 'selected':'' ?>>خرامة (Drill)</option>
                        </select>
                    </div>

                    <!-- كود نوع الحدث -->
                    <div>
                           <label for="emsf_147_a264c"><i class="fas fa-tag"></i> كود نوع الحدث <span class="required-indicator">*</span></label>
                        <input type="text" name="event_type_code" maxlength="10" placeholder="مثال: EQF"
                               value="<?= htmlspecialchars($edit_data['event_type_code'] ?? '') ?>"
                               class="fc-uppercase" required id="emsf_147_a264c">
                    </div>

                    <!-- اسم نوع الحدث -->
                    <div>
                        <label for="emsf_148_ff873"><i class="fas fa-align-right"></i> اسم نوع الحدث <span class="required-indicator">*</span></label>
                        <input type="text" name="event_type_name" maxlength="100" placeholder="مثال: عطل معدة"
                               value="<?= htmlspecialchars($edit_data['event_type_name'] ?? '') ?>" required id="emsf_148_ff873">
                    </div>

                    <!-- كود الفئة الرئيسية -->
                    <div>
                           <label for="emsf_149_8649f"><i class="fas fa-folder"></i> كود الفئة الرئيسية <span class="required-indicator">*</span></label>
                        <input type="text" name="main_category_code" maxlength="10" placeholder="مثال: MEC"
                               value="<?= htmlspecialchars($edit_data['main_category_code'] ?? '') ?>"
                               class="fc-uppercase" required id="emsf_149_8649f">
                    </div>

                    <!-- اسم الفئة الرئيسية -->
                    <div>
                        <label for="emsf_150_d53c7"><i class="fas fa-folder-open"></i> اسم الفئة الرئيسية <span class="required-indicator">*</span></label>
                        <input type="text" name="main_category_name" maxlength="100" placeholder="مثال: أعطال الميكانيكا"
                               value="<?= htmlspecialchars($edit_data['main_category_name'] ?? '') ?>" required id="emsf_150_d53c7">
                    </div>

                    <!-- الفئة الفرعية -->
                    <div>
                        <label for="emsf_151_c589b"><i class="fas fa-sitemap"></i> الفئة الفرعية (الجزء المعطل) <span class="required-indicator">*</span></label>
                        <input type="text" name="sub_category" maxlength="100" placeholder="مثال: المحرك"
                               value="<?= htmlspecialchars($edit_data['sub_category'] ?? '') ?>" required id="emsf_151_c589b">
                    </div>

                    <!-- تفصيل العطل -->
                    <div class="span2">
                        <label for="emsf_152_d9ea1"><i class="fas fa-info-circle"></i> تفصيل العطل <span class="required-indicator">*</span></label>
                        <input type="text" name="failure_detail" maxlength="200" placeholder="مثال: منظومة الهواء"
                               value="<?= htmlspecialchars($edit_data['failure_detail'] ?? '') ?>" required id="emsf_152_d9ea1">
                    </div>

                    <!-- الكود الكامل -->
                    <div>
                           <label for="f_full_code"><i class="fas fa-barcode"></i> الكود الكامل <span class="required-indicator">*</span></label>
                        <input type="text" name="full_code" id="f_full_code" maxlength="30"
                               placeholder="مثال: EX-EQF-MEC-01-01"
                               value="<?= htmlspecialchars($edit_data['full_code'] ?? '') ?>"
                               class="fc-code-input" required>
                    </div>

                    <!-- الحالة -->
                    <div>
                        <label for="emsf_153_5695e"><i class="fas fa-toggle-on"></i> الحالة</label>
                        <select name="status" id="emsf_153_5695e">
                            <option value="1" <?= ($edit_data['status']??1)==1 ? 'selected':'' ?>>نشط</option>
                            <option value="0" <?= ($edit_data['status']??1)==0 ? 'selected':'' ?>>غير نشط</option>
                        </select>
                    </div>

                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> <span id="fcSubmitText"><?= $edit_data ? 'حفظ التعديلات' : 'إضافة الكود' ?></span>
                    </button>
                    <button type="button" class="btn-secondary" onclick="hideFcForm()">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>

    <!-- ══ إحصائيات ══ -->
    <div class="fc-stats">
        <div class="fc-stat-card">
            <div class="sc-icon"><i class="fas fa-database"></i></div>
            <div class="sc-num"><?= number_format($stat_total) ?></div>
            <div class="sc-lbl">إجمالي الأكواد</div>
        </div>
        <div class="fc-stat-card">
            <div class="sc-icon"><i class="fas fa-check-circle"></i></div>
            <div class="sc-num sc-num-ok"><?= number_format($stat_active) ?></div>
            <div class="sc-lbl">كود نشط</div>
        </div>
        <div class="fc-stat-card">
            <div class="sc-icon"><i class="fas fa-tractor"></i></div>
            <div class="sc-num sc-num-navy"><?= number_format($stat_eq1) ?></div>
            <div class="sc-lbl">حفار</div>
        </div>
        <div class="fc-stat-card">
            <div class="sc-icon"><i class="fas fa-truck-moving"></i></div>
            <div class="sc-num sc-num-gold"><?= number_format($stat_eq2) ?></div>
            <div class="sc-lbl">قلاب</div>
        </div>
        <div class="fc-stat-card">
            <div class="sc-icon"><i class="fas fa-cogs"></i></div>
            <div class="sc-num sc-num-ok"><?= number_format($stat_eq3) ?></div>
            <div class="sc-lbl">خرامة</div>
        </div>
    </div>

    <!-- ══ الجدول ══ -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5><i class="fas fa-list-alt"></i> قائمة أكواد الأعطال
                    <span class="fc-count-badge">
                        <?= number_format($total_count) ?> كود
                    </span>
                </h5>
                <div class="fc-status-switch">
                    <a href="manage_failure_codes.php?f_stat=1" class="btn btn-sm <?= $filter_stat===1?'btn-primary':'btn-secondary border' ?>">نشط</a>
                    <a href="manage_failure_codes.php?f_stat=0" class="btn btn-sm <?= $filter_stat===0?'btn-danger':'btn-secondary border' ?>">معطل</a>
                    <a href="manage_failure_codes.php?f_stat=-1" class="btn btn-sm <?= $filter_stat===-1?'btn-secondary':'btn-secondary border' ?>">الكل</a>
                </div>
            </div>
        </div>

        <!-- شريط الفلاتر السريعة -->
        <div class="card-body fc-filter-body">
            <form method="GET" action="" class="fc-filter-bar" id="filterBarForm">
                <input type="hidden" name="f_stat" value="<?= $filter_stat ?>">
                <div>
                    <label class="fc-filter-label" for="emsf_154_04aa8">نوع المعدة</label>
                    <select name="f_eq" onchange="this.form.submit()" id="emsf_154_04aa8">
                        <option value="0">-- الكل --</option>
                        <option value="1" <?= $filter_eq==1?'selected':'' ?>>حفار</option>
                        <option value="2" <?= $filter_eq==2?'selected':'' ?>>قلاب</option>
                        <option value="3" <?= $filter_eq==3?'selected':'' ?>>خرامة</option>
                    </select>
                </div>
                <div>
                    <label class="fc-filter-label" for="emsf_155_ba779">نوع الحدث</label>
                    <select name="f_evt" onchange="this.form.submit()" id="emsf_155_ba779">
                        <option value="">-- الكل --</option>
                        <?php foreach ($evt_list_rows as $e): ?>
                            <option value="<?= htmlspecialchars($e['event_type_code']) ?>"
                                <?= $filter_evt === $e['event_type_code'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($e['event_type_code'] . ' — ' . $e['event_type_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="fc-filter-label" for="emsf_156_f008f">الفئة الرئيسية</label>
                    <select name="f_mc" onchange="this.form.submit()" id="emsf_156_f008f">
                        <option value="">-- الكل --</option>
                        <?php foreach ($mc_list_rows as $m): ?>
                            <option value="<?= htmlspecialchars($m['main_category_code']) ?>"
                                <?= $filter_mc === $m['main_category_code'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['main_category_code'] . ' — ' . $m['main_category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($filter_eq || $filter_evt || $filter_mc): ?>
                    <div class="fc-filter-actions">
                        <a href="manage_failure_codes.php?f_stat=<?= $filter_stat ?>"
                           class="fc-clear-link">
                            <i class="fas fa-times"></i> مسح الفلاتر
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table id="fcTable" class="display table table-bordered table-hover fc-table">
                    <thead class="table-dark">
                        <tr>
                            <th class="fc-col-id">#</th>
                            <th>نوع المعدة</th>
                            <th>كود الحدث</th>
                            <th>نوع الحدث</th>
                            <th>كود الفئة</th>
                            <th>الفئة الرئيسية</th>
                            <th>الفئة الفرعية</th>
                            <th>تفصيل العطل</th>
                            <th>الكود الكامل</th>
                            <th>الحالة</th>
                            <th class="fc-col-actions">إجراءات</th>
                            <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                            <th class="ems-fn-th" data-fn="1">رقم السجل</th>
                            <th class="ems-fn-th" data-fn="1">تاريخ العطل</th>
                            <th class="ems-fn-th" data-fn="1">كود المعدة</th>
                            <th class="ems-fn-th" data-fn="1">أمر العمل</th>
                            <th class="ems-fn-th" data-fn="1">نظام العطل</th>
                            <th class="ems-fn-th" data-fn="1">تصنيف العطل</th>
                            <th class="ems-fn-th" data-fn="1">السبب المباشر</th>
                            <th class="ems-fn-th" data-fn="1">السبب الجذري</th>
                            <th class="ems-fn-th" data-fn="1">تكرار العطل خلال 90 يومًا</th>
                            <th class="ems-fn-th" data-fn="1">مجموعة التكرار</th>
                            <th class="ems-fn-th" data-fn="1">ساعات التوقف التراكمية</th>
                            <th class="ems-fn-th none" data-fn="1">التكلفة التراكمية</th>
                            <th class="ems-fn-th none" data-fn="1">الإجراء التصحيحي</th>
                            <th class="ems-fn-th none" data-fn="1">المسؤول</th>
                            <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                            <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                            <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                            </tr>
                    </thead>
                    <tbody>
                    <?php
                    $eq_badge = [
                        1 => '<span class="badge-eq-1"><i class="fas fa-tractor"></i> حفار</span>',
                        2 => '<span class="badge-eq-2"><i class="fas fa-truck-moving"></i> قلاب</span>',
                        3 => '<span class="badge-eq-3"><i class="fas fa-cogs"></i> خرامة</span>',
                    ];
                    $i = 1;
                    if (!empty($list_rows)):
                        foreach ($list_rows as $row):
                            $is_active = $row['status'] == 1;
                    ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= $eq_badge[$row['equipment_type']] ?? '<span class="badge-code">'.$row['equipment_type'].'</span>' ?></td>
                            <td><span class="badge-code"><?= htmlspecialchars($row['event_type_code']) ?></span></td>
                            <td><?= htmlspecialchars($row['event_type_name']) ?></td>
                            <td><span class="badge-code"><?= htmlspecialchars($row['main_category_code']) ?></span></td>
                            <td><?= htmlspecialchars($row['main_category_name']) ?></td>
                            <td><?= htmlspecialchars($row['sub_category']) ?></td>
                            <td><?= htmlspecialchars($row['failure_detail']) ?></td>
                            <td><span class="badge-code"><?= htmlspecialchars($row['full_code']) ?></span></td>
                            <td>
                                <?php if ($is_active): ?>
                                    <span class="badge-active"><i class="fas fa-check-circle"></i> نشط</span>
                                <?php else: ?>
                                    <span class="badge-inactive"><i class="fas fa-ban"></i> معطل</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fc-row-actions">
                                    <?php if ($can_edit): ?>
                                    <button class="btn-secondary"
                                            onclick="editRow(<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($can_delete): ?>
                                        <?php if ($is_active): ?>
                                        <form method="POST" class="fc-inline-form" onsubmit="return confirm('تأكيد تعطيل هذا الكود؟')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="del_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn-danger"><i class="fas fa-ban"></i></button>
                                        </form>
                                        <?php else: ?>
                                        <form method="POST" class="fc-inline-form">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="res_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn-secondary"><i class="fas fa-redo"></i></button>
                                        </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="11" class="text-center py-4">لا توجد أكواد للعرض</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /.main -->

<!-- مودال تأكيد الحذف النهائي -->
<div class="modal fade" id="hardDeleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle"></i> تأكيد الحذف النهائي</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">هل أنت متأكد من حذف هذا الكود نهائياً؟ لا يمكن التراجع عن هذا الإجراء.</div>
            <div class="modal-footer">
                <form method="POST" id="hardDeleteForm">
                    <input type="hidden" name="action" value="hard_delete">
                    <input type="hidden" name="del_id" id="hardDelId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger">حذف نهائي</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>

<script>
$(document).ready(function () {
    $('#fcTable').DataTable({
        language: { url: '/ems/assets/i18n/datatables/ar.json' },
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-primary btn-sm',
                title: 'أكواد الأعطال',
                exportOptions: { columns: ':not(:last-child)' }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-danger btn-sm',
                title: 'أكواد الأعطال',
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: { columns: ':not(:last-child)' }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> طباعة',
                className: 'btn btn-secondary btn-sm',
                exportOptions: { columns: ':not(:last-child)' }
            }
        ],
        order: [[0, 'asc']],
        pageLength: 50,
        lengthMenu: [[25, 50, 100, 250, -1], [25, 50, 100, 250, 'الكل']],
        searchDelay: 300,
        deferRender: true,
        columnDefs: [
            { orderable: false, targets: -1 }
        ]
    });
});

function toggleForm() {
    var form = document.getElementById('fcForm');
    if (!form.classList.contains('allforms-visible')) {
        // وضع الإضافة: تصفير الحقول والعنوان
        form.reset();
        var hid = form.querySelector('[name="edit_id"]');
        if (hid) hid.value = '';
        var icon = form.querySelector('.card-header h5 i');
        if (icon) icon.className = 'fas fa-plus-circle';
        document.getElementById('fcFormTitle').textContent = 'إضافة كود عطل جديد';
        document.getElementById('fcSubmitText').textContent = 'إضافة الكود';
        form.classList.add('allforms-visible');
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        form.classList.remove('allforms-visible');
    }
}

function hideFcForm() {
    document.getElementById('fcForm').classList.remove('allforms-visible');
}

function editRow(data) {
    var form = document.getElementById('fcForm');
    form.classList.add('allforms-visible');
    var icon = form.querySelector('.card-header h5 i');
    if (icon) icon.className = 'fas fa-edit';
    document.getElementById('fcFormTitle').textContent = 'تعديل كود العطل';
    document.getElementById('fcSubmitText').textContent = 'حفظ التعديلات';

    // إضافة حقل edit_id إن لم يكن موجوداً
    var hiddenId = form.querySelector('[name="edit_id"]');
    if (!hiddenId) {
        hiddenId = document.createElement('input');
        hiddenId.type = 'hidden';
        hiddenId.name = 'edit_id';
        form.appendChild(hiddenId);
    }
    hiddenId.value = data.id;

    // ملء الحقول
    form.equipment_type.value   = data.equipment_type;
    form.event_type_code.value  = data.event_type_code;
    form.event_type_name.value  = data.event_type_name;
    form.main_category_code.value = data.main_category_code;
    form.main_category_name.value = data.main_category_name;
    form.sub_category.value     = data.sub_category;
    form.failure_detail.value   = data.failure_detail;
    form.full_code.value        = data.full_code;
    form.status.value           = data.status;

    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>
<?php if (function_exists('ems_excel_render')) { ems_excel_render(); } ?>
<?php mysqli_close($conn); ?>
