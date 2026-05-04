<?php
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
    header('Location: ../main/dashboard.php?msg=' . urlencode('❌ لا توجد صلاحية لعرض هذه الصفحة'));
    exit();
}

$can_add    = $page_permissions['can_add'];
$can_edit   = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

$page_title = "إدارة أكواد الأعطال";

// ── مساعد تعقيم الإدخال
function esc($conn, $v) { return mysqli_real_escape_string($conn, trim($v)); }

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
            mysqli_query($conn, "UPDATE failure_codes SET status = 0 WHERE id = $del_id");
            header("Location: manage_failure_codes.php?msg=" . urlencode("تم حذف الكود بنجاح ✅"));
            exit();
        }
        goto done;
    }

    // استعادة
    if (isset($_POST['action']) && $_POST['action'] === 'restore') {
        $res_id = intval($_POST['res_id']);
        if ($res_id > 0) {
            mysqli_query($conn, "UPDATE failure_codes SET status = 1 WHERE id = $res_id");
            header("Location: manage_failure_codes.php?msg=" . urlencode("تم استعادة الكود ✅"));
            exit();
        }
        goto done;
    }

    // إضافة / تعديل
    if (!$can_add) { $error_msg = "❌ لا توجد صلاحية"; goto done; }

    $edit_id          = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
    $equipment_type   = intval($_POST['equipment_type']);
    $event_type_code  = strtoupper(esc($conn, $_POST['event_type_code']));
    $event_type_name  = esc($conn, $_POST['event_type_name']);
    $main_cat_code    = strtoupper(esc($conn, $_POST['main_category_code']));
    $main_cat_name    = esc($conn, $_POST['main_category_name']);
    $sub_category     = esc($conn, $_POST['sub_category']);
    $failure_detail   = esc($conn, $_POST['failure_detail']);
    $full_code        = strtoupper(esc($conn, $_POST['full_code']));
    $status           = intval($_POST['status']);

    if ($equipment_type < 1 || empty($event_type_code) || empty($event_type_name)
        || empty($main_cat_code) || empty($main_cat_name) || empty($sub_category)
        || empty($failure_detail) || empty($full_code)) {
        $error_msg = "⚠️ يرجى تعبئة جميع الحقول الإلزامية";
        goto done;
    }

    // التحقق من تكرار full_code
    $dup_where = $edit_id > 0 ? " AND id != $edit_id" : '';
    $dup = mysqli_query($conn, "SELECT id FROM failure_codes WHERE full_code = '$full_code'$dup_where LIMIT 1");
    if ($dup && mysqli_num_rows($dup) > 0) {
        $error_msg = "⚠️ الكود الكامل '$full_code' موجود مسبقاً";
        goto done;
    }

    if ($edit_id > 0) {
        $sql = "UPDATE failure_codes SET
                    equipment_type   = $equipment_type,
                    event_type_code  = '$event_type_code',
                    event_type_name  = '$event_type_name',
                    main_category_code = '$main_cat_code',
                    main_category_name = '$main_cat_name',
                    sub_category     = '$sub_category',
                    failure_detail   = '$failure_detail',
                    full_code        = '$full_code',
                    status           = $status
                WHERE id = $edit_id";
        $msg_ok = "تم تعديل الكود بنجاح ✅";
    } else {
        $sql = "INSERT INTO failure_codes
                    (equipment_type, event_type_code, event_type_name, main_category_code,
                     main_category_name, sub_category, failure_detail, full_code, status)
                VALUES
                    ($equipment_type, '$event_type_code', '$event_type_name', '$main_cat_code',
                     '$main_cat_name', '$sub_category', '$failure_detail', '$full_code', $status)";
        $msg_ok = "تم إضافة الكود بنجاح ✅";
    }

    if (mysqli_query($conn, $sql)) {
        header("Location: manage_failure_codes.php?msg=" . urlencode($msg_ok));
        exit();
    } else {
        $error_msg = "خطأ في الحفظ: " . mysqli_error($conn);
    }
}

done:

// رسالة من redirect
if (isset($_GET['msg'])) { $success_msg = htmlspecialchars($_GET['msg']); }

// بيانات تعديل
$edit_data = null;
$edit_id_get = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
if ($edit_id_get > 0 && $can_edit) {
    $er = mysqli_query($conn, "SELECT * FROM failure_codes WHERE id = $edit_id_get LIMIT 1");
    if ($er) $edit_data = mysqli_fetch_assoc($er);
}

// فلاتر جانبية لبناء قوائم الـ select في الفورم
$eq_type_names = [1 => 'حفار (Excavator)', 2 => 'قلاب (Dump Truck)', 3 => 'خرامة (Drill)'];

// بيانات الجدول
$filter_eq   = isset($_GET['f_eq'])   ? intval($_GET['f_eq'])                       : 0;
$filter_evt  = isset($_GET['f_evt'])  ? esc($conn, $_GET['f_evt'])                  : '';
$filter_mc   = isset($_GET['f_mc'])   ? esc($conn, $_GET['f_mc'])                   : '';
$filter_stat = isset($_GET['f_stat']) ? intval($_GET['f_stat'])                     : 1; // افتراضي: نشط

$where = [];
$where[] = $filter_stat >= 0 ? "status = $filter_stat" : "1=1";
if ($filter_eq  > 0)       $where[] = "equipment_type = $filter_eq";
if (!empty($filter_evt))   $where[] = "event_type_code = '$filter_evt'";
if (!empty($filter_mc))    $where[] = "main_category_code = '$filter_mc'";
$where_sql = implode(' AND ', $where);

$list_result = mysqli_query($conn, "SELECT * FROM failure_codes WHERE $where_sql ORDER BY equipment_type, event_type_code, main_category_code, full_code");
$total_count = $list_result ? mysqli_num_rows($list_result) : 0;

// قوائم التصفية
$evt_list = mysqli_query($conn, "SELECT DISTINCT event_type_code, event_type_name FROM failure_codes WHERE status = 1 ORDER BY event_type_code");
$mc_list  = mysqli_query($conn, "SELECT DISTINCT main_category_code, main_category_name FROM failure_codes WHERE status = 1 ORDER BY main_category_code");

include '../inheader.php';
include '../insidebar.php';
?>

<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">

<style>
/* ── Page Hero Header ── */
.fc-page .page-header {
    background: linear-gradient(140deg, #0c1c3e 0%, #1b2f6e 65%, #243a84 100%);
    border-radius: 18px;
    padding: 18px 20px;
    margin-bottom: 18px;
    box-shadow: 0 10px 30px rgba(12,28,62,.22);
}
.fc-page .page-title { color: #fff; }
.fc-page .page-title .title-icon {
    background: rgba(255,255,255,.13);
    color: #ffd740;
    border: 1px solid rgba(255,255,255,.22);
}
.fc-hero-sub {
    color: #b8c8ff;
    font-size: .88rem;
    font-weight: 600;
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 7px;
}

/* ── Cards ── */
.fc-page .card {
    border: 1px solid rgba(12,28,62,.08);
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(12,28,62,.07);
    margin-bottom: 18px;
}
.fc-page .card .card-header {
    background: #fff;
    border-bottom: 1px solid rgba(12,28,62,.08);
    padding: 14px 18px;
    border-radius: 14px 14px 0 0;
}
.fc-page .card .card-header h5 {
    margin: 0; color: #0c1c3e; font-weight: 800; font-size: 1rem;
}

/* ── Form Grid ── */
.fc-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 14px;
}
.fc-form-grid label {
    display: block;
    font-size: .82rem;
    font-weight: 700;
    color: #0c1c3e;
    margin-bottom: 5px;
}
.fc-form-grid input,
.fc-form-grid select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid rgba(12,28,62,.18);
    border-radius: 9px;
    font-size: .9rem;
    color: #0c1c3e;
    transition: border-color .2s;
}
.fc-form-grid input:focus,
.fc-form-grid select:focus {
    outline: none;
    border-color: #e8b800;
    box-shadow: 0 0 0 3px rgba(232,184,0,.13);
}
.fc-form-grid .span2 { grid-column: span 2; }
.fc-form-grid .span3 { grid-column: 1 / -1; }

/* ── Badges ── */
.badge-eq-1 { background: #1b2f6e; color:#fff; padding:3px 10px; border-radius:20px; font-size:.78rem; font-weight:700; }
.badge-eq-2 { background: #e8b800; color:#0c1c3e; padding:3px 10px; border-radius:20px; font-size:.78rem; font-weight:700; }
.badge-eq-3 { background: #16a34a; color:#fff; padding:3px 10px; border-radius:20px; font-size:.78rem; font-weight:700; }
.badge-code {
    font-family: monospace;
    background: rgba(12,28,62,.08);
    color: #0c1c3e;
    padding: 3px 8px;
    border-radius: 7px;
    font-size: .82rem;
    font-weight: 700;
    letter-spacing: .5px;
}
.badge-active   { background: rgba(22,163,74,.12); color:#16a34a; border:1px solid rgba(22,163,74,.25); padding:3px 10px; border-radius:20px; font-size:.78rem; font-weight:700; }
.badge-inactive { background: rgba(220,38,38,.1);  color:#dc2626; border:1px solid rgba(220,38,38,.2);  padding:3px 10px; border-radius:20px; font-size:.78rem; font-weight:700; }

/* ── Action Buttons ── */
.btn-edit-row {
    background: rgba(37,99,235,.1); color:#2563eb; border:1px solid rgba(37,99,235,.25);
    padding:4px 12px; border-radius:8px; font-size:.8rem; font-weight:700;
    cursor:pointer; transition:all .2s; white-space:nowrap;
}
.btn-edit-row:hover { background:#2563eb; color:#fff; }
.btn-del-row {
    background: rgba(220,38,38,.09); color:#dc2626; border:1px solid rgba(220,38,38,.2);
    padding:4px 12px; border-radius:8px; font-size:.8rem; font-weight:700;
    cursor:pointer; transition:all .2s; white-space:nowrap;
}
.btn-del-row:hover { background:#dc2626; color:#fff; }
.btn-restore-row {
    background: rgba(22,163,74,.1); color:#16a34a; border:1px solid rgba(22,163,74,.25);
    padding:4px 12px; border-radius:8px; font-size:.8rem; font-weight:700;
    cursor:pointer; transition:all .2s; white-space:nowrap;
}
.btn-restore-row:hover { background:#16a34a; color:#fff; }

/* ── Stats Strip ── */
.fc-stats {
    display:flex; gap:12px; flex-wrap:wrap; margin-bottom:18px;
}
.fc-stat-card {
    flex:1; min-width:120px;
    background:#fff; border-radius:12px; padding:12px 16px;
    border:1px solid rgba(12,28,62,.08);
    box-shadow:0 2px 10px rgba(12,28,62,.06);
    display:flex; flex-direction:column; gap:4px;
}
.fc-stat-card .sc-num { font-size:1.6rem; font-weight:900; color:#0c1c3e; line-height:1; }
.fc-stat-card .sc-lbl { font-size:.78rem; font-weight:700; color:#64748b; }

/* ── Filter bar ── */
.fc-filter-bar {
    display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-bottom:14px;
}
.fc-filter-bar select, .fc-filter-bar input {
    padding:7px 11px; border:1px solid rgba(12,28,62,.18); border-radius:9px;
    font-size:.85rem; color:#0c1c3e; min-width:160px;
}
.fc-filter-bar select:focus, .fc-filter-bar input:focus {
    outline:none; border-color:#e8b800;
    box-shadow:0 0 0 3px rgba(232,184,0,.13);
}

/* ── Table inside DataTables ── */
.fc-page table.dataTable thead th { white-space:nowrap; font-size:.82rem; }
.fc-page table.dataTable tbody td { font-size:.85rem; vertical-align:middle; }
</style>

<?php
// ── حساب إحصائيات سريعة
$stat_total  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM failure_codes"))['c'];
$stat_active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM failure_codes WHERE status=1"))['c'];
$stat_eq1    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM failure_codes WHERE equipment_type=1 AND status=1"))['c'];
$stat_eq2    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM failure_codes WHERE equipment_type=2 AND status=1"))['c'];
$stat_eq3    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM failure_codes WHERE equipment_type=3 AND status=1"))['c'];
?>

<div class="main fc-page">

    <!-- ══ Page Header ══ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <div class="title-icon"><i class="fas fa-exclamation-triangle"></i></div>
                إدارة أكواد الأعطال
            </h1>
            <div class="fc-hero-sub">
                <i class="fas fa-layer-group"></i>
                مكتبة مرجعية شاملة لتصنيف الأعطال — نوع الحدث &rsaquo; الفئة الرئيسية &rsaquo; الفرعية &rsaquo; التفصيل
            </div>
        </div>
        <div class="page-header-actions">
            <a href="../main/dashboard.php" class="back-btn"><i class="fas fa-arrow-right"></i> رجوع</a>
            <?php if ($can_add): ?>
            <button id="toggleFormBtn" class="add-btn" onclick="toggleForm()">
                <i  class="fas fa-plus-circle" style="color:white"></i>  <span style="color:white">إضافة كود جديد </span>
            </button>
            <?php endif; ?>
           <a href="fleet_failures.php" class="back-btn"><i class="fas fa-arrow-right"></i> تقرير الاخطاء </a>
        </div>
    </div>

    <!-- ══ رسائل ══ -->
    <?php if ($success_msg): ?>
        <div class="success-message is-success"><i class="fas fa-check-circle"></i> <?= $success_msg ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="success-message is-error"><i class="fas fa-exclamation-circle"></i> <?= $error_msg ?></div>
    <?php endif; ?>

    <!-- ══ إحصائيات ══ -->
    <div class="fc-stats">
        <div class="fc-stat-card">
            <div class="sc-num"><?= number_format($stat_total) ?></div>
            <div class="sc-lbl"><i class="fas fa-database"></i> إجمالي الأكواد</div>
        </div>
        <div class="fc-stat-card">
            <div class="sc-num" style="color:#16a34a"><?= number_format($stat_active) ?></div>
            <div class="sc-lbl"><i class="fas fa-check-circle"></i> كود نشط</div>
        </div>
        <div class="fc-stat-card">
            <div class="sc-num" style="color:#1b2f6e"><?= number_format($stat_eq1) ?></div>
            <div class="sc-lbl"><i class="fas fa-tractor"></i> حفار</div>
        </div>
        <div class="fc-stat-card">
            <div class="sc-num" style="color:#e8b800"><?= number_format($stat_eq2) ?></div>
            <div class="sc-lbl"><i class="fas fa-truck-moving"></i> قلاب</div>
        </div>
        <div class="fc-stat-card">
            <div class="sc-num" style="color:#16a34a"><?= number_format($stat_eq3) ?></div>
            <div class="sc-lbl"><i class="fas fa-cogs"></i> خرامة</div>
        </div>
    </div>

    <!-- ══ نموذج الإضافة / التعديل ══ -->
    <?php if ($can_add): ?>
    <div class="card" id="addEditCard" style="display:<?= ($edit_data || $error_msg) ? 'block' : 'none'; ?>">
        <div class="card-header">
            <h5>
                <i class="fas fa-<?= $edit_data ? 'edit' : 'plus-circle' ?>"></i>
                <?= $edit_data ? 'تعديل كود العطل' : 'إضافة كود عطل جديد' ?>
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="fcForm">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="edit_id" value="<?= intval($edit_data['id']) ?>">
                <?php endif; ?>

                <div class="fc-form-grid">

                    <!-- نوع المعدة -->
                    <div>
                        <label><i class="fas fa-cog"></i> نوع المعدة <span style="color:red">*</span></label>
                        <select name="equipment_type" id="f_equipment_type" required>
                            <option value="">-- اختر --</option>
                            <option value="1" <?= ($edit_data['equipment_type']??'')=='1' ? 'selected':'' ?>>حفار (Excavator)</option>
                            <option value="2" <?= ($edit_data['equipment_type']??'')=='2' ? 'selected':'' ?>>قلاب (Dump Truck)</option>
                            <option value="3" <?= ($edit_data['equipment_type']??'')=='3' ? 'selected':'' ?>>خرامة (Drill)</option>
                        </select>
                    </div>

                    <!-- كود نوع الحدث -->
                    <div>
                        <label><i class="fas fa-tag"></i> كود نوع الحدث <span style="color:red">*</span></label>
                        <input type="text" name="event_type_code" maxlength="10" placeholder="مثال: EQF"
                               value="<?= htmlspecialchars($edit_data['event_type_code'] ?? '') ?>"
                               style="text-transform:uppercase" required>
                    </div>

                    <!-- اسم نوع الحدث -->
                    <div>
                        <label><i class="fas fa-align-right"></i> اسم نوع الحدث <span style="color:red">*</span></label>
                        <input type="text" name="event_type_name" maxlength="100" placeholder="مثال: عطل معدة"
                               value="<?= htmlspecialchars($edit_data['event_type_name'] ?? '') ?>" required>
                    </div>

                    <!-- كود الفئة الرئيسية -->
                    <div>
                        <label><i class="fas fa-folder"></i> كود الفئة الرئيسية <span style="color:red">*</span></label>
                        <input type="text" name="main_category_code" maxlength="10" placeholder="مثال: MEC"
                               value="<?= htmlspecialchars($edit_data['main_category_code'] ?? '') ?>"
                               style="text-transform:uppercase" required>
                    </div>

                    <!-- اسم الفئة الرئيسية -->
                    <div>
                        <label><i class="fas fa-folder-open"></i> اسم الفئة الرئيسية <span style="color:red">*</span></label>
                        <input type="text" name="main_category_name" maxlength="100" placeholder="مثال: أعطال الميكانيكا"
                               value="<?= htmlspecialchars($edit_data['main_category_name'] ?? '') ?>" required>
                    </div>

                    <!-- الفئة الفرعية -->
                    <div>
                        <label><i class="fas fa-sitemap"></i> الفئة الفرعية (الجزء المعطل) <span style="color:red">*</span></label>
                        <input type="text" name="sub_category" maxlength="100" placeholder="مثال: المحرك"
                               value="<?= htmlspecialchars($edit_data['sub_category'] ?? '') ?>" required>
                    </div>

                    <!-- تفصيل العطل -->
                    <div class="span2">
                        <label><i class="fas fa-info-circle"></i> تفصيل العطل <span style="color:red">*</span></label>
                        <input type="text" name="failure_detail" maxlength="200" placeholder="مثال: منظومة الهواء"
                               value="<?= htmlspecialchars($edit_data['failure_detail'] ?? '') ?>" required>
                    </div>

                    <!-- الكود الكامل -->
                    <div>
                        <label><i class="fas fa-barcode"></i> الكود الكامل <span style="color:red">*</span></label>
                        <input type="text" name="full_code" id="f_full_code" maxlength="30"
                               placeholder="مثال: EX-EQF-MEC-01-01"
                               value="<?= htmlspecialchars($edit_data['full_code'] ?? '') ?>"
                               style="text-transform:uppercase; font-family:monospace; font-weight:700;" required>
                    </div>

                    <!-- الحالة -->
                    <div>
                        <label><i class="fas fa-toggle-on"></i> الحالة</label>
                        <select name="status">
                            <option value="1" <?= ($edit_data['status']??1)==1 ? 'selected':'' ?>>نشط</option>
                            <option value="0" <?= ($edit_data['status']??1)==0 ? 'selected':'' ?>>غير نشط</option>
                        </select>
                    </div>

                    <!-- أزرار -->
                    <div class="span3" style="display:flex; gap:10px; justify-content:flex-start; margin-top:4px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?= $edit_data ? 'حفظ التعديلات' : 'إضافة الكود' ?>
                        </button>
                        <a href="manage_failure_codes.php" class="btn btn-light border">
                            <i class="fas fa-times"></i> إلغاء
                        </a>
                    </div>

                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ الجدول ══ -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5><i class="fas fa-list-alt"></i> قائمة أكواد الأعطال
                    <span style="background:rgba(12,28,62,.07);color:#0c1c3e;border-radius:20px;padding:2px 10px;font-size:.8rem;margin-right:6px;">
                        <?= number_format($total_count) ?> كود
                    </span>
                </h5>
                <div style="display:flex;gap:8px;">
                    <a href="manage_failure_codes.php?f_stat=1" class="btn btn-sm <?= $filter_stat===1?'btn-primary':'btn-light border' ?>">نشط</a>
                    <a href="manage_failure_codes.php?f_stat=0" class="btn btn-sm <?= $filter_stat===0?'btn-danger':'btn-light border' ?>">معطل</a>
                    <a href="manage_failure_codes.php?f_stat=-1" class="btn btn-sm <?= $filter_stat===-1?'btn-secondary':'btn-light border' ?>">الكل</a>
                </div>
            </div>
        </div>

        <!-- شريط الفلاتر السريعة -->
        <div class="card-body" style="padding-bottom:0; border-bottom:1px solid rgba(12,28,62,.07);">
            <form method="GET" action="" class="fc-filter-bar" id="filterBarForm">
                <input type="hidden" name="f_stat" value="<?= $filter_stat ?>">
                <div>
                    <label style="font-size:.78rem;font-weight:700;color:#64748b;display:block;margin-bottom:3px;">نوع المعدة</label>
                    <select name="f_eq" onchange="this.form.submit()">
                        <option value="0">-- الكل --</option>
                        <option value="1" <?= $filter_eq==1?'selected':'' ?>>حفار</option>
                        <option value="2" <?= $filter_eq==2?'selected':'' ?>>قلاب</option>
                        <option value="3" <?= $filter_eq==3?'selected':'' ?>>خرامة</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:.78rem;font-weight:700;color:#64748b;display:block;margin-bottom:3px;">نوع الحدث</label>
                    <select name="f_evt" onchange="this.form.submit()">
                        <option value="">-- الكل --</option>
                        <?php if ($evt_list): while ($e = mysqli_fetch_assoc($evt_list)): ?>
                            <option value="<?= htmlspecialchars($e['event_type_code']) ?>"
                                <?= $filter_evt === $e['event_type_code'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($e['event_type_code'] . ' — ' . $e['event_type_name']) ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:.78rem;font-weight:700;color:#64748b;display:block;margin-bottom:3px;">الفئة الرئيسية</label>
                    <select name="f_mc" onchange="this.form.submit()">
                        <option value="">-- الكل --</option>
                        <?php if ($mc_list): while ($m = mysqli_fetch_assoc($mc_list)): ?>
                            <option value="<?= htmlspecialchars($m['main_category_code']) ?>"
                                <?= $filter_mc === $m['main_category_code'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['main_category_code'] . ' — ' . $m['main_category_name']) ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <?php if ($filter_eq || $filter_evt || $filter_mc): ?>
                    <div style="display:flex;align-items:flex-end;">
                        <a href="manage_failure_codes.php?f_stat=<?= $filter_stat ?>"
                           style="padding:7px 14px;background:rgba(220,38,38,.09);color:#dc2626;border:1px solid rgba(220,38,38,.2);border-radius:9px;font-size:.83rem;font-weight:700;text-decoration:none;">
                            <i class="fas fa-times"></i> مسح الفلاتر
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table id="fcTable" class="display table table-bordered table-hover" style="width:100%">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:40px">#</th>
                            <th>نوع المعدة</th>
                            <th>كود الحدث</th>
                            <th>نوع الحدث</th>
                            <th>كود الفئة</th>
                            <th>الفئة الرئيسية</th>
                            <th>الفئة الفرعية</th>
                            <th>تفصيل العطل</th>
                            <th>الكود الكامل</th>
                            <th>الحالة</th>
                            <th style="width:120px">إجراءات</th>
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
                    if ($list_result && mysqli_num_rows($list_result) > 0):
                        while ($row = mysqli_fetch_assoc($list_result)):
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
                                <div style="display:flex;gap:5px;flex-wrap:wrap;">
                                    <?php if ($can_edit): ?>
                                    <button class="btn-edit-row"
                                            onclick="editRow(<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($can_delete): ?>
                                        <?php if ($is_active): ?>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('تأكيد تعطيل هذا الكود؟')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="del_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn-del-row"><i class="fas fa-ban"></i></button>
                                        </form>
                                        <?php else: ?>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="res_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn-restore-row"><i class="fas fa-redo"></i></button>
                                        </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
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
                className: 'btn btn-success btn-sm',
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
                className: 'btn btn-info btn-sm',
                exportOptions: { columns: ':not(:last-child)' }
            }
        ],
        order: [[0, 'asc']],
        pageLength: 50,
        lengthMenu: [[25, 50, 100, 250, -1], [25, 50, 100, 250, 'الكل']],
        searchDelay: 300,
        deferRender: true,
        responsive: true,
        columnDefs: [
            { orderable: false, targets: -1 }
        ]
    });
});

function toggleForm() {
    var card = document.getElementById('addEditCard');
    if (card.style.display === 'none' || card.style.display === '') {
        card.style.display = 'block';
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        // مسح التعديل إن وجد
        document.querySelector('[name="edit_id"]') && (document.querySelector('[name="edit_id"]').value = '');
        document.getElementById('fcForm').reset();
        card.querySelector('.card-header h5').innerHTML = '<i class="fas fa-plus-circle"></i> إضافة كود عطل جديد';
    } else {
        card.style.display = 'none';
    }
}

function editRow(data) {
    var card = document.getElementById('addEditCard');
    card.style.display = 'block';
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    card.querySelector('.card-header h5').innerHTML = '<i class="fas fa-edit"></i> تعديل كود العطل';

    var form = document.getElementById('fcForm');

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
}
</script>

<?php mysqli_close($conn); ?>
