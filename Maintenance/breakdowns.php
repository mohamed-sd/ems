<?php
/**
 * Maintenance/breakdowns.php — البلاغ الموحّد (تذكرة شاملة لكل الإدارات).
 * يصلها كل مستخدم مسجّل عبر التوبار (نمط المراسلات — مُستثناة من فحص صلاحية الموديول).
 * - أي مستخدم: إنشاء بلاغ + عرض بلاغات شركته.
 * - مستخدم الصيانة فقط: إصدار أمر صيانة من البلاغ / تغيير الحالة / الحذف.
 * البلاغ لا يغيّر حالة المعدة (القرار DEC-03).
 */
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/mnt_helpers.php';

$current_role    = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin  = ($current_role === '-1');
$company_id      = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$current_user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

// مستخدم الصيانة (مدير/مشرف) يملك إجراءات التحويل/الإدارة.
$is_maintenance = mnt_user_is_maintenance($conn);

$severities = array('منخفضة', 'متوسطة', 'عالية', 'حرجة');
$company_scope_sql = $is_super_admin ? "1=1" : "b.company_id = " . intval($company_id);
$current_role_int  = intval($current_role);

// عمود «موجَّه إلى (الدور/القسم)» — يُضاف تلقائياً مرة واحدة إن لم يكن موجوداً (نمط add-if-missing).
$breakdown_has_target_role = db_table_has_column($conn, 'mnt_breakdown', 'target_role');
if (!$breakdown_has_target_role) {
    @mysqli_query($conn, "ALTER TABLE mnt_breakdown ADD COLUMN target_role INT NULL DEFAULT NULL AFTER reporter_dept");
    $breakdown_has_target_role = db_table_has_column($conn, 'mnt_breakdown', 'target_role');
}

// ══════════════════════════════════════════════════════════════════════════════
// إصدار أمر صيانة من بلاغ (مستخدم الصيانة فقط)
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'issue_order') {
    if (!$is_maintenance) {
        header("Location: breakdowns.php?msg=لا+توجد+صلاحية+إصدار+أمر+صيانة+❌"); exit();
    }
    if ($company_id <= 0) {
        header("Location: breakdowns.php?msg=لا+يمكن+الإصدار+بلا+شركة+صالحة+❌"); exit();
    }
    $bid = intval($_POST['breakdown_id'] ?? 0);

    // جلب البلاغ (مقيّد بالشركة وغير محوّل مسبقاً)
    $brk = null;
    if ($stmt = mysqli_prepare($conn, "SELECT id, equipment_id, project_id, failure_code_id, order_id, state FROM mnt_breakdown WHERE id = ? AND company_id = ? AND COALESCE(is_deleted,0)=0 LIMIT 1")) {
        mysqli_stmt_bind_param($stmt, 'ii', $bid, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $brk = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
    }
    if (!$brk) {
        header("Location: breakdowns.php?msg=البلاغ+غير+موجود+❌"); exit();
    }
    if (intval($brk['order_id']) > 0) {
        header("Location: orders.php?id=" . intval($brk['order_id']) . "&msg=البلاغ+محوّل+مسبقاً+لأمر+صيانة"); exit();
    }
    // لا يجوز إصدار أمر من بلاغ مُغلق أو محوّل (الخادم يفرض ما تفرضه الواجهة)
    if (in_array($brk['state'], array('مغلق', 'محوّل'), true)) {
        header("Location: breakdowns.php?msg=" . urlencode('لا يمكن إصدار أمر من بلاغ مُغلق أو محوّل ❌')); exit();
    }

    $code = mnt_next_code($conn, 'mnt_order', 'MNT', $company_id);
    $eq   = $brk['equipment_id'] !== null ? intval($brk['equipment_id']) : null;
    $pr   = $brk['project_id'] !== null ? intval($brk['project_id']) : null;
    $fc   = $brk['failure_code_id'] !== null ? intval($brk['failure_code_id']) : null;

    $new_order_id = 0;
    $sql = "INSERT INTO mnt_order (company_id, code, breakdown_id, equipment_id, project_id, source, failure_code_id, state, created_by)
            VALUES (?, ?, ?, ?, ?, 'بلاغ', ?, 'بلاغ', ?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'isiiiii', $company_id, $code, $bid, $eq, $pr, $fc, $current_user_id);
        mysqli_stmt_execute($stmt);
        $new_order_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
    }

    // ربط البلاغ بالأمر + تحويل حالته
    if ($new_order_id > 0 && ($stmt = mysqli_prepare($conn, "UPDATE mnt_breakdown SET order_id = ?, state = 'محوّل' WHERE id = ? AND company_id = ?"))) {
        mysqli_stmt_bind_param($stmt, 'iii', $new_order_id, $bid, $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    header("Location: orders.php?id=" . intval($new_order_id) . "&msg=تم+إصدار+أمر+صيانة+من+البلاغ+✅"); exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// تغيير حالة البلاغ (مستخدم الصيانة) — مثال: إغلاق بلاغ بلا أمر
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['close_id'])) {
    if (!$is_maintenance) { header("Location: breakdowns.php?msg=لا+توجد+صلاحية+❌"); exit(); }
    $cid = intval($_GET['close_id']);
    if ($stmt = mysqli_prepare($conn, "UPDATE mnt_breakdown SET state = 'مغلق' WHERE id = ? AND company_id = ? AND COALESCE(is_deleted,0)=0")) {
        mysqli_stmt_bind_param($stmt, 'ii', $cid, $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header("Location: breakdowns.php?msg=تم+إغلاق+البلاغ+✅"); exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// حذف ناعم (مستخدم الصيانة)
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete_id'])) {
    if (!$is_maintenance) { header("Location: breakdowns.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $did = intval($_GET['delete_id']);
    if ($stmt = mysqli_prepare($conn, "UPDATE mnt_breakdown SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ? AND company_id = ?")) {
        mysqli_stmt_bind_param($stmt, 'iii', $current_user_id, $did, $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header("Location: breakdowns.php?msg=تم+حذف+البلاغ+✅"); exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// إنشاء بلاغ (أي مستخدم مسجّل من شركته)
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['description'])) {
    if ($company_id <= 0) {
        header("Location: breakdowns.php?msg=لا+يمكن+الإبلاغ+بلا+شركة+صالحة+❌"); exit();
    }

    $equipment_id  = !empty($_POST['equipment_id']) ? intval($_POST['equipment_id']) : null;
    $project_id    = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $target_role   = !empty($_POST['target_role']) ? intval($_POST['target_role']) : null;
    $reporter_dept = trim($_POST['reporter_dept'] ?? '');
    $report_dt     = trim($_POST['report_datetime'] ?? '');
    $failure_code  = !empty($_POST['failure_code_id']) ? intval($_POST['failure_code_id']) : null;
    $severity      = trim($_POST['severity'] ?? '');
    $is_stopped    = isset($_POST['is_stopped']) ? 1 : 0;
    $description   = trim($_POST['description'] ?? '');

    if (!in_array($severity, $severities, true)) { $severity = 'متوسطة'; }
    if ($report_dt === '') { $report_dt = date('Y-m-d H:i:s'); }
    else { $report_dt = str_replace('T', ' ', $report_dt); }
    if ($description === '') {
        header("Location: breakdowns.php?msg=وصف+البلاغ+مطلوب+❌"); exit();
    }
    if ($target_role === null) {
        header("Location: breakdowns.php?msg=القسم+الموجَّه+له+البلاغ+مطلوب+❌"); exit();
    }

    // مرفق اختياري (تحقّق صارم من الامتداد)
    $attachment = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $allowed_ext = array('jpg', 'jpeg', 'png', 'pdf', 'webp');
        $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_ext, true) && $_FILES['attachment']['size'] <= 5 * 1024 * 1024) {
            $dir = __DIR__ . '/uploads/breakdowns';
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $fname = 'brk_' . $company_id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (@move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . '/' . $fname)) {
                $attachment = 'uploads/breakdowns/' . $fname;
            }
        }
    }

    $code = mnt_next_code($conn, 'mnt_breakdown', 'BR', $company_id);

    $sql = "INSERT INTO mnt_breakdown
            (company_id, code, equipment_id, project_id, reported_by, reporter_dept, target_role, report_datetime,
             failure_code_id, severity, is_stopped, description, attachment, state, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'جديد', ?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        // الترتيب: company_id,i code,s equipment_id,i project_id,i reported_by,i reporter_dept,s
        // target_role,i report_datetime,s failure_code_id,i severity,s is_stopped,i description,s
        // attachment,s created_by,i  ⇒ "isiiisisisissi"
        mysqli_stmt_bind_param(
            $stmt, 'isiiisisisissi',
            $company_id, $code, $equipment_id, $project_id, $current_user_id, $reporter_dept, $target_role, $report_dt,
            $failure_code, $severity, $is_stopped, $description, $attachment, $current_user_id
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header("Location: breakdowns.php?msg=تم+تسجيل+البلاغ+بنجاح+✅"); exit();
}

// ── بيانات القوائم المنسدلة (مقيّدة بالشركة حيث ينطبق) ──
$equipments = array();
$eq_sql = "SELECT id, name, code FROM equipments WHERE " . ($is_super_admin ? "1=1" : "company_id = " . intval($company_id)) . " ORDER BY name ASC";
if ($r = mysqli_query($conn, $eq_sql)) { while ($row = mysqli_fetch_assoc($r)) { $equipments[] = $row; } }

$projects = array();
$pr_sql = "SELECT id, name FROM project WHERE " . ($is_super_admin ? "1=1" : "company_id = " . intval($company_id)) . " ORDER BY name ASC";
if ($r = mysqli_query($conn, $pr_sql)) { while ($row = mysqli_fetch_assoc($r)) { $projects[] = $row; } }

$failure_codes = array();
$fc_sql = "SELECT id, full_code, failure_detail, main_category_name FROM failure_codes WHERE status = 1 ORDER BY full_code ASC";
if ($r = mysqli_query($conn, $fc_sql)) { while ($row = mysqli_fetch_assoc($r)) { $failure_codes[] = $row; } }

// الأقسام/الأدوار المتاحة للتوجيه (تصنيف عام؛ العزل يضمنه نطاق الشركة على البلاغات).
$roles_list = array();
$roles_sql = "SELECT id, name FROM roles WHERE (status = '1' OR status = 1) AND id <> -1 ORDER BY name ASC";
if ($r = mysqli_query($conn, $roles_sql)) { while ($row = mysqli_fetch_assoc($r)) { $roles_list[] = $row; } }

$page_title = 'إيكوبيشن | البلاغات';
include '../inheader.php';
include '../insidebar.php';
?>

<div class="main mnt-breakdowns-main ems-unified-page-shell">

    <?php
    $new_count = ($company_id > 0) ? mnt_new_breakdowns_count($conn, $company_id, $current_role_int) : 0;
    $header_title_html = 'البلاغات' . ($new_count > 0 ? ' <span class="mnt-head-badge">' . intval($new_count) . ' وارد</span>' : '');
    $header_icon    = 'fa fa-triangle-exclamation';
    $header_actions = array();
    $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'بلاغ جديد');
    if ($is_maintenance) {
        $header_actions[] = array('tag' => 'a', 'href' => 'orders.php', 'class' => 'suppliers-header-link', 'icon' => 'fa fa-wrench', 'label' => 'أوامر الصيانة');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- ══ فورم بلاغ جديد ══ -->
    <form id="mntForm" action="" method="post" class="allforms" enctype="multipart/form-data">
        <div class="card-header"><h5><i class="fas fa-triangle-exclamation"></i> تسجيل بلاغ جديد</h5></div>
        <div class="card">
            <div class="card-body">
                <div class="form-section">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>المعدة</label>
                            <select name="equipment_id" id="bk_equipment">
                                <option value="">-- اختر المعدة --</option>
                                <?php foreach ($equipments as $e): ?>
                                    <option value="<?php echo intval($e['id']); ?>"><?php echo htmlspecialchars($e['name'] . (!empty($e['code']) ? ' (' . $e['code'] . ')' : '')); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>المشروع / الموقع</label>
                            <select name="project_id" id="bk_project">
                                <option value="">-- اختر المشروع --</option>
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?php echo intval($p['id']); ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>القسم المُبلِّغ</label>
                            <input type="text" name="reporter_dept" id="bk_dept" placeholder="مثال: التشغيل">
                        </div>
                        <div class="form-group">
                            <label>موجَّه إلى (القسم) <span class="required">*</span></label>
                            <select name="target_role" id="bk_target_role" required>
                                <option value="">-- اختر القسم --</option>
                                <?php foreach ($roles_list as $rl): ?>
                                    <option value="<?php echo intval($rl['id']); ?>"><?php echo htmlspecialchars($rl['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>تاريخ ووقت البلاغ</label>
                            <input type="datetime-local" name="report_datetime" id="bk_dt">
                        </div>
                        <div class="form-group">
                            <label>نوع العطل (تصنيف الأعطال)</label>
                            <select name="failure_code_id" id="bk_failure">
                                <option value="">-- اختر --</option>
                                <?php foreach ($failure_codes as $f): ?>
                                    <option value="<?php echo intval($f['id']); ?>"><?php echo htmlspecialchars($f['full_code'] . ' — ' . $f['failure_detail']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>الخطورة</label>
                            <select name="severity" id="bk_severity">
                                <?php foreach ($severities as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $s === 'متوسطة' ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>هل المعدة متوقفة؟</label>
                            <label class="checkbox-label"><input type="checkbox" name="is_stopped" id="bk_stopped" value="1"><span>نعم، المعدة متوقفة</span></label>
                        </div>
                        <div class="form-group">
                            <label>مرفق (صورة/PDF)</label>
                            <input type="file" name="attachment" id="bk_attachment" accept=".jpg,.jpeg,.png,.pdf,.webp">
                        </div>
                        <div class="form-group allforms-span-full">
                            <label>وصف البلاغ <span class="required">*</span></label>
                            <textarea name="description" id="bk_description" rows="3" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> إرسال البلاغ</button>
                    <button type="button" class="btn-cancel" onclick="mntToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
                </div>
            </div>
        </div>
    </form>

    <?php
    // ══ دالة رسم صف بلاغ واحد (تُستخدم لكلا التبويبين) ══
    $render_breakdown_row = function ($row) use ($is_maintenance) {
        $sev = (string) $row['severity'];
        $sev_class = ($sev === 'حرجة' || $sev === 'عالية') ? 'status-inactive' : 'status-active';
        $state = (string) $row['state'];
        $view_attrs =
            "data-code='" . htmlspecialchars((string) $row['code'], ENT_QUOTES) . "' " .
            "data-equipment='" . htmlspecialchars((string) ($row['equipment_name'] ?? ''), ENT_QUOTES) . "' " .
            "data-project='" . htmlspecialchars((string) ($row['project_name'] ?? ''), ENT_QUOTES) . "' " .
            "data-dept='" . htmlspecialchars((string) ($row['reporter_dept'] ?? ''), ENT_QUOTES) . "' " .
            "data-reporter='" . htmlspecialchars((string) ($row['reporter_name'] ?? ''), ENT_QUOTES) . "' " .
            "data-target='" . htmlspecialchars((string) ($row['target_role_name'] ?? ''), ENT_QUOTES) . "' " .
            "data-severity='" . htmlspecialchars($sev, ENT_QUOTES) . "' " .
            "data-stopped='" . (intval($row['is_stopped']) ? 'نعم' : 'لا') . "' " .
            "data-dt='" . htmlspecialchars((string) ($row['report_datetime'] ?? ''), ENT_QUOTES) . "' " .
            "data-state='" . htmlspecialchars($state, ENT_QUOTES) . "' " .
            "data-desc='" . htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES) . "'";
        echo "<tr>";
        echo "<td><div class='action-btns'>";
        echo "<a href='javascript:void(0)' class='viewBtn action-btn view' $view_attrs title='عرض'><i class='fas fa-eye'></i></a>";
        if ($is_maintenance) {
            if ($state === 'جديد' || $state === 'قيد التقييم') {
                echo "<form method='post' style='display:inline' onsubmit='return confirm(\"إصدار أمر صيانة من هذا البلاغ؟\")'>"
                   . "<input type='hidden' name='action' value='issue_order'>"
                   . "<input type='hidden' name='breakdown_id' value='" . intval($row['id']) . "'>"
                   . "<button type='submit' class='action-btn edit' title='إصدار أمر صيانة'><i class='fas fa-wrench'></i></button>"
                   . "</form>";
                echo "<a href='?close_id=" . intval($row['id']) . "' class='action-btn' title='إغلاق البلاغ' onclick='return confirm(\"إغلاق البلاغ بدون أمر؟\")'><i class='fas fa-times-circle'></i></a>";
            } elseif (intval($row['order_id']) > 0) {
                echo "<a href='orders.php?id=" . intval($row['order_id']) . "' class='action-btn' title='فتح أمر الصيانة'><i class='fas fa-up-right-from-square'></i></a>";
            }
            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف البلاغ؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
        }
        echo "</div></td>";
        echo "<td><strong>" . htmlspecialchars((string) $row['code']) . "</strong></td>";
        echo "<td>" . htmlspecialchars((string) ($row['equipment_name'] ?? '-')) . "</td>";
        echo "<td>" . htmlspecialchars((string) ($row['project_name'] ?? '-')) . "</td>";
        echo "<td>" . htmlspecialchars((string) ($row['reporter_name'] ?? '-')) . "</td>";
        echo "<td>" . htmlspecialchars((string) ($row['target_role_name'] ?? '-')) . "</td>";
        echo "<td><span class='$sev_class'>" . htmlspecialchars($sev) . "</span></td>";
        echo "<td>" . (intval($row['is_stopped']) ? '<i class="fas fa-ban" style="color:#dc2626"></i>' : '—') . "</td>";
        echo "<td>" . htmlspecialchars((string) ($row['report_datetime'] ?? '')) . "</td>";
        echo "<td><span class='action-btn'>" . htmlspecialchars($state) . "</span></td>";
        echo "</tr>";
    };

    // ══ جلب البلاغات: «بلاغاتي» (أنا المُبلِّغ) و«الواردة إليّ» (target_role = دوري) ══
    $bk_select = "SELECT b.id, b.code, b.severity, b.is_stopped, b.report_datetime, b.state, b.order_id,
                         e.name AS equipment_name, p.name AS project_name, b.description, b.reporter_dept,
                         r.name AS target_role_name, u.name AS reporter_name
                    FROM mnt_breakdown b
                    LEFT JOIN equipments e ON e.id = b.equipment_id
                    LEFT JOIN project p ON p.id = b.project_id
                    LEFT JOIN roles r ON r.id = b.target_role
                    LEFT JOIN users u ON u.id = b.reported_by
                   WHERE $company_scope_sql AND COALESCE(b.is_deleted,0)=0 ";
    $mine_rows = array();
    $in_rows   = array();
    $mine_q = mysqli_query($conn, $bk_select . " AND b.reported_by = " . intval($current_user_id) . " ORDER BY b.id DESC");
    if ($mine_q) { while ($row = mysqli_fetch_assoc($mine_q)) { $mine_rows[] = $row; } }
    $in_q = mysqli_query($conn, $bk_select . " AND b.target_role = " . $current_role_int . " ORDER BY b.id DESC");
    if ($in_q) { while ($row = mysqli_fetch_assoc($in_q)) { $in_rows[] = $row; } }
    ?>

    <!-- ══ تبويبان: بلاغاتي / الواردة إليّ ══ -->
    <div class="card">
        <div class="card-body">
            <div class="bk-tabs" role="tablist">
                <button type="button" class="bk-tab is-active" data-bk-tab="mine"><i class="fas fa-paper-plane"></i> بلاغاتي <span class="bk-tab-badge"><?php echo count($mine_rows); ?></span></button>
                <button type="button" class="bk-tab" data-bk-tab="in"><i class="fas fa-inbox"></i> الواردة إليّ <span class="bk-tab-badge"><?php echo count($in_rows); ?></span></button>
            </div>

            <div class="form-grid" style="margin-top:12px;">
                <div class="form-group">
                    <label>تصفية حسب الحالة</label>
                    <select id="filterState">
                        <option value="">كل الحالات</option>
                        <option value="جديد">جديد</option>
                        <option value="قيد التقييم">قيد التقييم</option>
                        <option value="محوّل">محوّل</option>
                        <option value="مغلق">مغلق</option>
                    </select>
                </div>
            </div>

            <?php
            $bk_thead = '<thead><tr>'
                . '<th>الإجراءات</th><th>المرجع</th><th>المعدة</th><th>المشروع</th>'
                . '<th>المُبلِّغ</th><th>موجَّه إلى</th><th>الخطورة</th><th>متوقفة</th><th>التاريخ</th><th>الحالة</th>'
                . '</tr></thead>';
            ?>

            <div class="bk-panel is-active" id="bk-mine">
                <div class="table-container">
                    <table id="mntTableMine" class="display nowrap alltables no-datatable" style="width:100%;">
                        <?php echo $bk_thead; ?>
                        <tbody>
                            <?php if (empty($mine_rows)): ?>
                                <tr><td colspan="10" style="text-align:center;color:#888;padding:14px;">لا توجد بلاغات رفعتَها بعد</td></tr>
                            <?php else: foreach ($mine_rows as $row) { $render_breakdown_row($row); } endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bk-panel" id="bk-in">
                <div class="table-container">
                    <table id="mntTableIn" class="display nowrap alltables no-datatable" style="width:100%;">
                        <?php echo $bk_thead; ?>
                        <tbody>
                            <?php if (empty($in_rows)): ?>
                                <tr><td colspan="10" style="text-align:center;color:#888;padding:14px;">لا توجد بلاغات واردة إلى قسمك</td></tr>
                            <?php else: foreach ($in_rows as $row) { $render_breakdown_row($row); } endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

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
    $(document).ready(function () {
        // 1) ربط زر «بلاغ جديد» والتبويبات أولاً — حتى لا يعطّلها أي خطأ في DataTables.
        var toggleBtn = document.getElementById('toggleForm');
        if (toggleBtn) { toggleBtn.addEventListener('click', function () { $('#mntForm').toggleClass('allforms-visible'); }); }

        var dtConfig = {
            scrollX: true, autoWidth: false, stateSave: false, order: [[1, 'desc']],
            dom: 'Bfrtip',
            buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
            "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
        };
        // تهيئة كسولة: الجدول المخفي (داخل تبويب display:none) يُهيّأ عند أول إظهار فقط — لتفادي خطأ scrollX.
        var dtCache = {};
        function initTable(sel) {
            try {
                if (!dtCache[sel] && $(sel).length && !$.fn.dataTable.isDataTable(sel)) {
                    dtCache[sel] = $(sel).DataTable(dtConfig);
                }
            } catch (e) {}
            return dtCache[sel];
        }
        initTable('#mntTableMine'); // المرئي افتراضياً

        $('#filterState').on('change', function () {
            var v = this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '';
            $.each(dtCache, function (k, t) { try { t.column(9).search(v, true, false).draw(); } catch (e) {} });
        });

        // تبديل التبويبات + تهيئة/ضبط أعمدة الجدول المُظهَر
        $(document).on('click', '.bk-tab', function () {
            var key = $(this).data('bk-tab');
            $('.bk-tab').removeClass('is-active');
            $(this).addClass('is-active');
            $('.bk-panel').removeClass('is-active');
            $('#bk-' + key).addClass('is-active');
            var t = initTable(key === 'in' ? '#mntTableIn' : '#mntTableMine');
            if (t) { try { t.columns.adjust(); } catch (e) {} }
        });

        $(document).on('click', '.viewBtn', function () {
            var $t = $(this);
            EmsDetailsModal.open({
                title: 'تفاصيل البلاغ', icon: 'fas fa-triangle-exclamation',
                fields: [
                    { label: 'المرجع', value: $t.data('code'), icon: 'fas fa-hashtag' },
                    { label: 'المعدة', value: $t.data('equipment'), icon: 'fas fa-tractor' },
                    { label: 'المشروع', value: $t.data('project'), icon: 'fas fa-folder-open' },
                    { label: 'المُبلِّغ', value: $t.data('reporter'), icon: 'fas fa-user' },
                    { label: 'القسم المُبلِّغ', value: $t.data('dept'), icon: 'fas fa-building' },
                    { label: 'موجَّه إلى', value: $t.data('target'), icon: 'fas fa-share-from-square' },
                    { label: 'الخطورة', value: $t.data('severity'), icon: 'fas fa-fire' },
                    { label: 'متوقفة', value: $t.data('stopped'), icon: 'fas fa-ban' },
                    { label: 'التاريخ', value: $t.data('dt'), icon: 'fas fa-clock' },
                    { label: 'الحالة', value: $t.data('state'), icon: 'fas fa-flag', type: 'status' },
                    { label: 'الوصف', value: $t.data('desc'), icon: 'fas fa-align-left', size: 'full' }
                ]
            });
        });
    });
    window.mntToggleForm = function () { $('#mntForm').toggleClass('allforms-visible'); };
})();
</script>
<style>
    .mnt-head-badge { background:#dc2626;color:#fff;border-radius:999px;padding:2px 9px;font-size:.7rem;font-weight:800;margin-inline-start:8px;vertical-align:middle; }
    .bk-tabs { display:flex; flex-wrap:wrap; gap:6px; padding:4px; border:1px solid var(--gray-200,#E7E5E4); border-radius:var(--radius-lg,12px); background:var(--gray-50,#FAFAF9); }
    .bk-tab { appearance:none; border:0; cursor:pointer; font-family:inherit; font-weight:700; font-size:13.5px; display:inline-flex; align-items:center; gap:7px; padding:9px 16px; border-radius:var(--radius-md,8px); color:var(--gray-700,#44403C); background:transparent; transition:all .15s ease; }
    .bk-tab i { font-size:14px; opacity:.9; }
    .bk-tab:hover { background:var(--gray-100,#F5F5F4); color:var(--gray-900,#1C1917); }
    .bk-tab.is-active { background:linear-gradient(160deg,var(--brand-amber,#F2AA2A),var(--brand-orange-bright,#E67E00)); color:#fff; box-shadow:0 4px 12px rgba(230,126,0,.28); }
    .bk-tab-badge { font-size:11px; font-weight:800; min-width:19px; height:19px; padding:0 6px; border-radius:10px; display:inline-grid; place-items:center; background:var(--gray-200,#E7E5E4); color:var(--gray-700,#44403C); }
    .bk-tab.is-active .bk-tab-badge { background:rgba(255,255,255,.30); color:#fff; }
    .bk-panel { display:none; }
    .bk-panel.is-active { display:block; }
</style>
</body>
</html>
