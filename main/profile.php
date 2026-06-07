<?php
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

$session_user = $_SESSION['user'];
$user_id = isset($session_user['id']) ? intval($session_user['id']) : 0;

// ── جلب أحدث بيانات المستخدم من قاعدة البيانات ──
$user = $session_user;
if ($user_id > 0) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, name, username, email, phone, role, role_id, status, project_id, company_id,
                created_at, updated_at, last_login_at
         FROM users WHERE id = ? LIMIT 1"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $user = array_merge($session_user, $row);
        }
        mysqli_stmt_close($stmt);
    }
}

// ── اسم الدور ──
$role_text = 'مستخدم';
$role_value = isset($user['role']) ? strval($user['role']) : '';
if ($role_value !== '') {
    $role_id_int = intval($role_value);
    if ($rstmt = mysqli_prepare($conn, 'SELECT name FROM roles WHERE id = ? LIMIT 1')) {
        mysqli_stmt_bind_param($rstmt, 'i', $role_id_int);
        mysqli_stmt_execute($rstmt);
        $rres = mysqli_stmt_get_result($rstmt);
        if ($rres && ($rrow = mysqli_fetch_assoc($rres)) && trim($rrow['name']) !== '') {
            $role_text = $rrow['name'];
        }
        mysqli_stmt_close($rstmt);
    }
}
if ($role_value === '-1') {
    $role_text = 'الإدارة العليا';
}

// ── اسم المشروع ──
$project_text = '';
$project_id_val = isset($user['project_id']) ? intval($user['project_id']) : 0;
if ($project_id_val > 0) {
    if ($pstmt = mysqli_prepare($conn, 'SELECT name FROM project WHERE id = ? LIMIT 1')) {
        mysqli_stmt_bind_param($pstmt, 'i', $project_id_val);
        mysqli_stmt_execute($pstmt);
        $pres = mysqli_stmt_get_result($pstmt);
        if ($pres && ($prow = mysqli_fetch_assoc($pres))) {
            $project_text = $prow['name'];
        }
        mysqli_stmt_close($pstmt);
    }
}

// ── اسم الشركة ──
$company_text = '';
$company_id_val = isset($user['company_id']) ? intval($user['company_id']) : 0;
if ($company_id_val > 0) {
    if ($cstmt = mysqli_prepare($conn, 'SELECT company_name, name FROM admin_companies WHERE id = ? LIMIT 1')) {
        mysqli_stmt_bind_param($cstmt, 'i', $company_id_val);
        mysqli_stmt_execute($cstmt);
        $cres = mysqli_stmt_get_result($cstmt);
        if ($cres && ($crow = mysqli_fetch_assoc($cres))) {
            $company_text = trim($crow['company_name'] !== '' ? $crow['company_name'] : (string) $crow['name']);
        }
        mysqli_stmt_close($cstmt);
    }
}

// ── سجل النشاط والإحصائيات ──
$activity_available = false;
$act_total       = 0;
$act_logins      = 0;
$act_creates     = 0;
$act_updates     = 0;
$act_today       = 0;
$act_by_type     = array();   // action_type => count
$act_top_screens = array();   // screen => count
$act_recent      = array();   // recent rows
$act_days_labels = array();   // last 14 days labels
$act_days_values = array();   // last 14 days counts
$last_activity_ip = '';

$has_activity_table = false;
if ($user_id > 0) {
    $tbl_res = @mysqli_query($conn, "SHOW TABLES LIKE 'activity_logs'");
    $has_activity_table = ($tbl_res && mysqli_num_rows($tbl_res) > 0);
}

if ($has_activity_table) {
    $activity_available = true;

    // إجمالي النشاط + توزيع الأنواع
    if ($s = mysqli_prepare($conn, "SELECT action_type, COUNT(*) c FROM activity_logs WHERE user_id = ? GROUP BY action_type ORDER BY c DESC")) {
        mysqli_stmt_bind_param($s, 'i', $user_id);
        mysqli_stmt_execute($s);
        $r = mysqli_stmt_get_result($s);
        while ($r && ($row = mysqli_fetch_assoc($r))) {
            $type = $row['action_type'] !== null && $row['action_type'] !== '' ? $row['action_type'] : 'other';
            $cnt  = intval($row['c']);
            $act_by_type[$type] = $cnt;
            $act_total += $cnt;
            if ($type === 'login') {
                $act_logins += $cnt;
            } elseif ($type === 'create') {
                $act_creates += $cnt;
            } elseif ($type === 'update') {
                $act_updates += $cnt;
            }
        }
        mysqli_stmt_close($s);
    }

    // نشاط اليوم
    if ($s = mysqli_prepare($conn, "SELECT COUNT(*) c FROM activity_logs WHERE user_id = ? AND DATE(created_at) = CURRENT_DATE")) {
        mysqli_stmt_bind_param($s, 'i', $user_id);
        mysqli_stmt_execute($s);
        $r = mysqli_stmt_get_result($s);
        if ($r && ($row = mysqli_fetch_assoc($r))) {
            $act_today = intval($row['c']);
        }
        mysqli_stmt_close($s);
    }

    // أكثر الشاشات استخداماً
    if ($s = mysqli_prepare($conn, "SELECT COALESCE(NULLIF(screen_name,''), NULLIF(module_name,''), 'غير معروف') s, COUNT(*) c FROM activity_logs WHERE user_id = ? GROUP BY s ORDER BY c DESC LIMIT 6")) {
        mysqli_stmt_bind_param($s, 'i', $user_id);
        mysqli_stmt_execute($s);
        $r = mysqli_stmt_get_result($s);
        while ($r && ($row = mysqli_fetch_assoc($r))) {
            $act_top_screens[] = array('name' => $row['s'], 'count' => intval($row['c']));
        }
        mysqli_stmt_close($s);
    }

    // النشاط خلال آخر 14 يوماً
    $day_counts = array();
    if ($s = mysqli_prepare($conn, "SELECT DATE(created_at) d, COUNT(*) c FROM activity_logs WHERE user_id = ? AND created_at >= (CURRENT_DATE - INTERVAL 13 DAY) GROUP BY DATE(created_at)")) {
        mysqli_stmt_bind_param($s, 'i', $user_id);
        mysqli_stmt_execute($s);
        $r = mysqli_stmt_get_result($s);
        while ($r && ($row = mysqli_fetch_assoc($r))) {
            $day_counts[$row['d']] = intval($row['c']);
        }
        mysqli_stmt_close($s);
    }
    for ($d = 13; $d >= 0; $d--) {
        $key = date('Y-m-d', strtotime("-$d day"));
        $act_days_labels[] = date('m-d', strtotime($key));
        $act_days_values[] = isset($day_counts[$key]) ? $day_counts[$key] : 0;
    }

    // آخر الأنشطة
    if ($s = mysqli_prepare($conn, "SELECT action_type, screen_name, module_name, ip_address, created_at FROM activity_logs WHERE user_id = ? ORDER BY id DESC LIMIT 12")) {
        mysqli_stmt_bind_param($s, 'i', $user_id);
        mysqli_stmt_execute($s);
        $r = mysqli_stmt_get_result($s);
        while ($r && ($row = mysqli_fetch_assoc($r))) {
            $act_recent[] = $row;
            if ($last_activity_ip === '' && !empty($row['ip_address'])) {
                $last_activity_ip = $row['ip_address'];
            }
        }
        mysqli_stmt_close($s);
    }
}

// تسمية عربية لأنواع النشاط
function act_label($type)
{
    $map = array(
        'login' => 'تسجيل دخول',
        'logout' => 'تسجيل خروج',
        'create' => 'إضافة',
        'update' => 'تعديل',
        'delete' => 'حذف',
        'export' => 'تصدير',
        'import_preview' => 'معاينة استيراد',
        'import_commit' => 'تنفيذ استيراد',
        'send' => 'إرسال',
        'view' => 'عرض',
        'template' => 'قالب',
        'complete' => 'إكمال',
        'other' => 'أخرى',
    );
    if (isset($map[$type])) {
        return $map[$type];
    }
    if (strpos($type, 'get_') === 0) {
        return 'استعلام';
    }
    if (strpos($type, 'save') === 0) {
        return 'حفظ';
    }
    return $type;
}

function act_icon($type)
{
    $map = array(
        'login' => 'fa-right-to-bracket',
        'logout' => 'fa-right-from-bracket',
        'create' => 'fa-plus',
        'update' => 'fa-pen',
        'delete' => 'fa-trash',
        'export' => 'fa-file-export',
        'import_preview' => 'fa-file-import',
        'import_commit' => 'fa-file-import',
        'send' => 'fa-paper-plane',
        'template' => 'fa-file-lines',
        'complete' => 'fa-circle-check',
    );
    if (isset($map[$type])) {
        return $map[$type];
    }
    if (strpos($type, 'get_') === 0) {
        return 'fa-magnifying-glass';
    }
    return 'fa-bolt';
}

// ── مساعدات العرض ──
function profile_val($value, $fallback = 'غير محدّد')
{
    $value = is_string($value) ? trim($value) : $value;
    if ($value === null || $value === '' || $value === 0 || $value === '0') {
        return $fallback;
    }
    return $value;
}

function profile_date($value, $withTime = true)
{
    if (empty($value) || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
        return 'غير متوفّر';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return 'غير متوفّر';
    }
    return $withTime ? date('Y-m-d H:i', $ts) : date('Y-m-d', $ts);
}

$display_name = profile_val(isset($user['name']) ? $user['name'] : '', 'مستخدم النظام');

// الأحرف الأولى للأفاتار
$avatar_initials = '';
$name_parts = preg_split('/\s+/', trim((string) $display_name));
foreach ($name_parts as $part) {
    if ($part !== '') {
        $avatar_initials .= mb_substr($part, 0, 1, 'UTF-8');
    }
    if (mb_strlen($avatar_initials, 'UTF-8') >= 2) {
        break;
    }
}
if ($avatar_initials === '') {
    $avatar_initials = 'م';
}

$status_value = isset($user['status']) ? strval($user['status']) : '1';
$is_active = ($status_value === '1' || $status_value === 'active' || $status_value === '');

$change_password_url = function_exists('ems_url') ? ems_url('Settings/change_password.php') : '../Settings/change_password.php';
$settings_url = function_exists('ems_url') ? ems_url('Settings/settings.php') : '../Settings/settings.php';
$logout_url = function_exists('ems_url') ? ems_url('logout.php') : '../logout.php';

$page_title = "إيكويبيشن | الملف الشخصي";
include("../inheader.php");
include('../insidebar.php');
?>

<style>
    .profile-shell {
        display: grid;
        gap: 18px;
    }

    /* ── بطاقة الترويسة (Hero) ── */
    .profile-hero {
        position: relative;
        display: flex;
        align-items: center;
        gap: 22px;
        flex-wrap: wrap;
        background:#ccc;
        border: 1px solid rgba(244, 197, 66, 0.32);
        border-radius: var(--rl, 14px);
        padding: 26px;
        color: #fff;
        box-shadow: var(--sh2);
        overflow: hidden;
    }

    .profile-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background-image: radial-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px);
        background-size: 18px 18px;
        pointer-events: none;
    }

    .profile-hero::after {
        content: "";
        position: absolute;
        left: -40px;
        top: -50px;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(244, 197, 66, 0.35) 0%, transparent 70%);
        pointer-events: none;
    }

    .profile-avatar {
        position: relative;
        z-index: 1;
        width: 96px;
        height: 96px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.2rem;
        font-weight: 900;
        color: #2a2010;
        background: linear-gradient(135deg, var(--primary-yellow, #F4C542), #D9AB32);
        border: 3px solid rgba(255, 255, 255, 0.25);
        box-shadow: 0 8px 22px rgba(0, 0, 0, 0.25);
        flex-shrink: 0;
    }

    .profile-hero-info {
        position: relative;
        z-index: 1;
        display: grid;
        gap: 8px;
        flex: 1;
        min-width: 240px;
    }

    .profile-hero-info h2 {
        margin: 0;
        font-size: 1.6rem;
        font-weight: 900;
        color: #fff;
    }

    .profile-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .profile-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 13px;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .profile-badge.role {
        background: rgba(244, 197, 66, 0.18);
        border: 1px solid rgba(244, 197, 66, 0.4);
        color: #ffe7b5;
    }

    .profile-badge.user {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.18);
        color: #f3f4f6;
    }

    .profile-badge.status-on {
        background: rgba(22, 163, 74, 0.18);
        border: 1px solid rgba(22, 163, 74, 0.45);
        color: #bbf7d0;
    }

    .profile-badge.status-off {
        background: rgba(220, 38, 38, 0.18);
        border: 1px solid rgba(220, 38, 38, 0.45);
        color: #fecaca;
    }

    .profile-hero-actions {
        position: relative;
        z-index: 1;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .profile-hero-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 16px;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
        border: 1.5px solid transparent;
        transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .profile-hero-btn.primary {
        background: linear-gradient(135deg, var(--primary-yellow, #F4C542), #D9AB32);
        color: #2a2010;
    }

    .profile-hero-btn.ghost {
        background: #999;
        border-color: rgba(255, 255, 255, 0.22);
        color: #000;
    }

    .profile-hero-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(0, 0, 0, 0.25);
    }

    /* ── شبكة الأقسام ── */
    .profile-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 18px;
    }

    .profile-card {
        background: var(--s1, #fff);
        border: 1.5px solid var(--bdr, #D7DBE0);
        border-radius: var(--rl, 14px);
        padding: 18px;
        box-shadow: var(--sh);
        display: grid;
        gap: 6px;
        align-content: start;
    }

    .profile-card-title {
        display: flex;
        align-items: center;
        gap: 9px;
        margin: 0 0 8px;
        padding-bottom: 12px;
        border-bottom: 1px dashed var(--bdr, #D7DBE0);
        color: var(--t1, #111);
        font-size: 1rem;
        font-weight: 800;
    }

    .profile-card-title i {
        width: 32px;
        height: 32px;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(244, 197, 66, 0.16);
        color: #b8860b;
        font-size: .9rem;
    }

    .profile-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 11px 4px;
        border-bottom: 1px solid var(--bdr2, #EEF0F3);
    }

    .profile-row:last-child {
        border-bottom: none;
    }

    .profile-row-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--t3, #666E78);
        font-size: 0.85rem;
        font-weight: 700;
    }

    .profile-row-label i {
        color: var(--or2, #D9AB32);
        width: 16px;
        text-align: center;
    }

    .profile-row-value {
        color: var(--t1, #111);
        font-size: 0.9rem;
        font-weight: 800;
        text-align: left;
        word-break: break-word;
    }

    .profile-row-value.muted {
        color: var(--t3, #9aa0a8);
        font-weight: 700;
    }

    .profile-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 800;
    }

    .profile-pill.on {
        background: rgba(22, 163, 74, 0.12);
        color: #15803d;
    }

    .profile-pill.off {
        background: rgba(220, 38, 38, 0.12);
        color: #b91c1c;
    }

    /* ── بطاقات الإحصائيات ── */
    .profile-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: 14px;
    }

    .stat-card {
        display: flex;
        align-items: center;
        gap: 14px;
        background: var(--s1, #fff);
        border: 1.5px solid var(--bdr, #D7DBE0);
        border-radius: var(--rl, 14px);
        padding: 16px 18px;
        box-shadow: var(--sh);
        position: relative;
        overflow: hidden;
    }

    .stat-card::after {
        content: "";
        position: absolute;
        right: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, var(--primary-yellow, #F4C542), #D9AB32);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 13px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
        background: rgba(244, 197, 66, 0.16);
        color: #b8860b;
    }

    .stat-icon.blue { background: rgba(30, 58, 95, 0.12); color: #1E3A5F; }
    .stat-icon.green { background: rgba(22, 163, 74, 0.13); color: #15803d; }
    .stat-icon.gray  { background: rgba(110, 110, 110, 0.14); color: #555; }

    .stat-meta {
        display: grid;
        gap: 2px;
    }

    .stat-value {
        font-size: 1.55rem;
        font-weight: 900;
        color: var(--t1, #111);
        line-height: 1.1;
    }

    .stat-label {
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--t3, #666E78);
    }

    /* ── شبكة الرسوم البيانية ── */
    .profile-analytics {
        display: grid;
        grid-template-columns: 1.4fr 1fr;
        gap: 18px;
    }

    .chart-card {
        background: var(--s1, #fff);
        border: 1.5px solid var(--bdr, #D7DBE0);
        border-radius: var(--rl, 14px);
        padding: 18px;
        box-shadow: var(--sh);
    }

    .chart-card .profile-card-title {
        border-bottom: 1px dashed var(--bdr, #D7DBE0);
    }

    .chart-wrap {
        position: relative;
        height: 280px;
    }

    .chart-wrap.small {
        height: 260px;
    }

    /* ── أكثر الشاشات استخداماً ── */
    .top-screen-row {
        display: grid;
        gap: 6px;
        padding: 9px 2px;
    }

    .top-screen-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 0.84rem;
        font-weight: 800;
        color: var(--t1, #111);
    }

    .top-screen-head .cnt {
        color: var(--t3, #666E78);
        font-weight: 800;
    }

    .top-screen-bar {
        height: 8px;
        border-radius: 99px;
        background: var(--bdr2, #EEF0F3);
        overflow: hidden;
    }

    .top-screen-bar span {
        display: block;
        height: 100%;
        border-radius: 99px;
        background: linear-gradient(90deg, var(--primary-yellow, #F4C542), #D9AB32);
    }

    /* ── جدول آخر الأنشطة ── */
    .activity-table-wrap {
        overflow-x: auto;
    }

    .activity-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .activity-table thead th {
        background: #666;
        color: #F4C542;
        padding: 10px 12px;
        text-align: right;
        font-weight: 800;
        white-space: nowrap;
    }

    .activity-table thead th:first-child { border-radius: 0 9px 0 0; }
    .activity-table thead th:last-child  { border-radius: 9px 0 0 0; }

    .activity-table tbody td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--bdr2, #EEF0F3);
        color: var(--t1, #111);
        font-weight: 600;
        white-space: nowrap;
    }

    .activity-table tbody tr:hover td {
        background: #fffaf0;
    }

    .act-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 0.76rem;
        font-weight: 800;
        background: rgba(244, 197, 66, 0.16);
        color: #8a6d0c;
    }

    .act-badge.login  { background: rgba(22, 163, 74, 0.13); color: #15803d; }
    .act-badge.logout { background: rgba(220, 38, 38, 0.12); color: #b91c1c; }
    .act-badge.create { background: rgba(30, 58, 95, 0.12); color: #1E3A5F; }

    .profile-empty {
        text-align: center;
        color: var(--t3, #9aa0a8);
        font-weight: 700;
        padding: 26px;
    }

    @media (max-width: 900px) {
        .profile-analytics {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .profile-hero {
            padding: 20px;
            justify-content: center;
            text-align: center;
        }

        .profile-hero-info {
            text-align: center;
            justify-items: center;
        }

        .profile-badges,
        .profile-hero-actions {
            justify-content: center;
        }
    }
</style>

<div class="main profile-main ems-unified-page-shell">

    <?php
    $header_title   = 'الملف الشخصي';
    $header_icon    = 'fas fa-id-badge';
    $header_actions = array();
    $header_back    = array('href' => 'dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="profile-shell">

        <!-- بطاقة الترويسة -->
        <div class="profile-hero">
            <div class="profile-avatar"><?php echo htmlspecialchars($avatar_initials, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="profile-hero-info">
                <h2><?php echo htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8'); ?></h2>
                <div class="profile-badges">
                    <span class="profile-badge role"><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($role_text, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if (!empty($user['username'])): ?>
                        <span class="profile-badge user"><i class="fas fa-at"></i> <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <span class="profile-badge <?php echo $is_active ? 'status-on' : 'status-off'; ?>">
                        <i class="fas fa-circle" style="font-size:.5rem;"></i> <?php echo $is_active ? 'حساب نشط' : 'حساب موقوف'; ?>
                    </span>
                </div>
            </div>
            <div class="profile-hero-actions">
                <a href="<?php echo htmlspecialchars($change_password_url, ENT_QUOTES, 'UTF-8'); ?>" class="profile-hero-btn primary">
                    <i class="fas fa-key"></i> تغيير كلمة المرور
                </a>
                <a href="<?php echo htmlspecialchars($settings_url, ENT_QUOTES, 'UTF-8'); ?>" class="profile-hero-btn ghost">
                    <i class="fas fa-gear"></i> الإعدادات
                </a>
            </div>
        </div>

        <div class="profile-grid">

            <!-- معلومات الحساب -->
            <div class="profile-card">
                <h3 class="profile-card-title"><i class="fas fa-user"></i> معلومات الحساب</h3>

                <div class="profile-row">
                    <span class="profile-row-label"><i class="fas fa-signature"></i> الاسم الكامل</span>
                    <span class="profile-row-value"><?php echo htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="profile-row">
                    <span class="profile-row-label"><i class="fas fa-at"></i> اسم المستخدم</span>
                    <span class="profile-row-value"><?php echo htmlspecialchars(profile_val(isset($user['username']) ? $user['username'] : ''), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="profile-row">
                    <span class="profile-row-label"><i class="fas fa-envelope"></i> البريد الإلكتروني</span>
                    <span class="profile-row-value"><?php echo htmlspecialchars(profile_val(isset($user['email']) ? $user['email'] : ''), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="profile-row">
                    <span class="profile-row-label"><i class="fas fa-phone"></i> رقم الهاتف</span>
                    <span class="profile-row-value rtl-number"><?php echo htmlspecialchars(profile_val(isset($user['phone']) ? $user['phone'] : ''), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="profile-row">
                    <span class="profile-row-label"><i class="fas fa-user-shield"></i> الدور / الصلاحية</span>
                    <span class="profile-row-value"><?php echo htmlspecialchars($role_text, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="profile-row">
                    <span class="profile-row-label"><i class="fas fa-toggle-on"></i> حالة الحساب</span>
                    <span class="profile-row-value">
                        <span class="profile-pill <?php echo $is_active ? 'on' : 'off'; ?>">
                            <i class="fas fa-circle" style="font-size:.5rem;"></i> <?php echo $is_active ? 'نشط' : 'موقوف'; ?>
                        </span>
                    </span>
                </div>
            </div>

            <!-- بيانات المؤسسة -->
            <div class="profile-card">
                <h3 class="profile-card-title"><i class="fas fa-building"></i> بيانات المؤسسة والعمل</h3>

                <div class="profile-row">
                    <span class="profile-row-label"><i class="fas fa-city"></i> الشركة</span>
                    <span class="profile-row-value <?php echo $company_text === '' ? 'muted' : ''; ?>">
                        <?php echo htmlspecialchars(profile_val($company_text), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="profile-row">
                    <span class="profile-row-label"><i class="fas fa-diagram-project"></i> المشروع</span>
                    <span class="profile-row-value <?php echo $project_text === '' ? 'muted' : ''; ?>">
                        <?php echo htmlspecialchars(profile_val($project_text, 'غير مرتبط بمشروع'), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="profile-row">
                    <span class="profile-row-label"><i class="fas fa-hashtag"></i> رقم المستخدم</span>
                    <span class="profile-row-value rtl-number">#<?php echo intval($user_id); ?></span>
                </div>
            </div>

            <!-- النشاط والتواريخ -->
            <div class="profile-card">
                <h3 class="profile-card-title"><i class="fas fa-clock-rotate-left"></i> النشاط والتواريخ</h3>

                <div class="profile-row">
                    <span class="profile-row-label"><i class="fas fa-calendar-plus"></i> تاريخ الإنشاء</span>
                    <span class="profile-row-value rtl-number"><?php echo htmlspecialchars(profile_date(isset($user['created_at']) ? $user['created_at'] : '', false), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="profile-row">
                    <span class="profile-row-label"><i class="fas fa-pen-to-square"></i> آخر تحديث</span>
                    <span class="profile-row-value rtl-number"><?php echo htmlspecialchars(profile_date(isset($user['updated_at']) ? $user['updated_at'] : ''), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="profile-row">
                    <span class="profile-row-label"><i class="fas fa-right-to-bracket"></i> آخر تسجيل دخول</span>
                    <span class="profile-row-value rtl-number">
                        <?php
                        $last_login = '';
                        if (!empty($user['last_login_at'])) {
                            $last_login = $user['last_login_at'];
                        } elseif (!empty($session_user['last_login'])) {
                            $last_login = $session_user['last_login'];
                        }
                        echo htmlspecialchars(profile_date($last_login), ENT_QUOTES, 'UTF-8');
                        ?>
                    </span>
                </div>
            </div>

            <!-- إجراءات سريعة -->
            <div class="profile-card">
                <h3 class="profile-card-title"><i class="fas fa-bolt"></i> إجراءات سريعة</h3>

                <a href="<?php echo htmlspecialchars($change_password_url, ENT_QUOTES, 'UTF-8'); ?>" class="profile-row" style="text-decoration:none;">
                    <span class="profile-row-label"><i class="fas fa-key"></i> تغيير كلمة المرور</span>
                    <span class="profile-row-value"><i class="fas fa-arrow-left" style="color:var(--t3);"></i></span>
                </a>
                <a href="<?php echo htmlspecialchars($settings_url, ENT_QUOTES, 'UTF-8'); ?>" class="profile-row" style="text-decoration:none;">
                    <span class="profile-row-label"><i class="fas fa-gear"></i> إعدادات النظام</span>
                    <span class="profile-row-value"><i class="fas fa-arrow-left" style="color:var(--t3);"></i></span>
                </a>
                <a href="dashboard.php" class="profile-row" style="text-decoration:none;">
                    <span class="profile-row-label"><i class="fas fa-house"></i> لوحة التحكم</span>
                    <span class="profile-row-value"><i class="fas fa-arrow-left" style="color:var(--t3);"></i></span>
                </a>
                <a href="<?php echo htmlspecialchars($logout_url, ENT_QUOTES, 'UTF-8'); ?>" class="profile-row" style="text-decoration:none;">
                    <span class="profile-row-label" style="color:#b91c1c;"><i class="fas fa-power-off" style="color:#b91c1c;"></i> تسجيل الخروج</span>
                    <span class="profile-row-value"><i class="fas fa-arrow-left" style="color:#b91c1c;"></i></span>
                </a>
            </div>

        </div>

        <?php if ($activity_available && $act_total > 0): ?>

        <!-- بطاقات الإحصائيات -->
        <div class="profile-stats">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <div class="stat-meta">
                    <div class="stat-value rtl-number"><?php echo number_format($act_total); ?></div>
                    <div class="stat-label">إجمالي الأنشطة</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-right-to-bracket"></i></div>
                <div class="stat-meta">
                    <div class="stat-value rtl-number"><?php echo number_format($act_logins); ?></div>
                    <div class="stat-label">مرات تسجيل الدخول</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-plus"></i></div>
                <div class="stat-meta">
                    <div class="stat-value rtl-number"><?php echo number_format($act_creates); ?></div>
                    <div class="stat-label">عمليات إضافة</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon gray"><i class="fas fa-pen"></i></div>
                <div class="stat-meta">
                    <div class="stat-value rtl-number"><?php echo number_format($act_updates); ?></div>
                    <div class="stat-label">عمليات تعديل</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-meta">
                    <div class="stat-value rtl-number"><?php echo number_format($act_today); ?></div>
                    <div class="stat-label">نشاط اليوم</div>
                </div>
            </div>
        </div>

        <!-- الرسوم البيانية -->
        <div class="profile-analytics">
            <div class="chart-card">
                <h3 class="profile-card-title"><i class="fas fa-chart-column"></i> النشاط خلال آخر 14 يوماً</h3>
                <div class="chart-wrap"><canvas id="activityTrendChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h3 class="profile-card-title"><i class="fas fa-chart-pie"></i> توزيع أنواع النشاط</h3>
                <div class="chart-wrap small"><canvas id="activityTypeChart"></canvas></div>
            </div>
        </div>

        <!-- آخر الأنشطة + أكثر الشاشات -->
        <div class="profile-analytics">
            <div class="chart-card">
                <h3 class="profile-card-title"><i class="fas fa-clock-rotate-left"></i> آخر الأنشطة</h3>
                <div class="activity-table-wrap">
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>النشاط</th>
                                <th>الشاشة</th>
                                <th>العنوان</th>
                                <th>التاريخ والوقت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($act_recent as $rec): ?>
                                <?php
                                $rtype = $rec['action_type'] !== null && $rec['action_type'] !== '' ? $rec['action_type'] : 'other';
                                $badge_class = in_array($rtype, array('login', 'logout', 'create')) ? $rtype : '';
                                $screen = $rec['screen_name'] !== '' ? $rec['screen_name'] : $rec['module_name'];
                                ?>
                                <tr>
                                    <td><span class="act-badge <?php echo $badge_class; ?>"><i class="fas <?php echo act_icon($rtype); ?>"></i> <?php echo htmlspecialchars(act_label($rtype), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td><?php echo htmlspecialchars(profile_val($screen, '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(profile_val($rec['module_name'], '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="rtl-number"><?php echo htmlspecialchars(profile_date($rec['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="chart-card">
                <h3 class="profile-card-title"><i class="fas fa-ranking-star"></i> أكثر الشاشات استخداماً</h3>
                <?php if (!empty($act_top_screens)): ?>
                    <?php $top_max = max(array_map(function ($x) { return $x['count']; }, $act_top_screens)); ?>
                    <?php foreach ($act_top_screens as $screen): ?>
                        <?php $pct = $top_max > 0 ? round(($screen['count'] / $top_max) * 100) : 0; ?>
                        <div class="top-screen-row">
                            <div class="top-screen-head">
                                <span><?php echo htmlspecialchars($screen['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="cnt rtl-number"><?php echo number_format($screen['count']); ?></span>
                            </div>
                            <div class="top-screen-bar"><span style="width: <?php echo $pct; ?>%;"></span></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="profile-empty">لا توجد بيانات كافية.</div>
                <?php endif; ?>
            </div>
        </div>

        <?php elseif ($activity_available): ?>
        <div class="chart-card">
            <div class="profile-empty"><i class="fas fa-circle-info"></i> لا يوجد سجل نشاط مسجّل لحسابك حتى الآن.</div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php if ($activity_available && $act_total > 0): ?>
<script src="/ems/assets/vendor/chartjs/chart.umd.min.js"></script>
<script>
    (function () {
        if (typeof Chart === 'undefined') { return; }

        var goldPalette = ['#D9AB32', '#F4C542', '#1E3A5F', '#6e6e6e', '#15803d', '#b8860b', '#C2941C', '#9aa0a8', '#b91c1c', '#3b82f6'];

        // الاتجاه خلال 14 يوماً
        var trendCtx = document.getElementById('activityTrendChart');
        if (trendCtx) {
            new Chart(trendCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($act_days_labels, JSON_UNESCAPED_UNICODE); ?>,
                    datasets: [{
                        label: 'عدد الأنشطة',
                        data: <?php echo json_encode($act_days_values); ?>,
                        backgroundColor: 'rgba(217, 171, 50, 0.75)',
                        hoverBackgroundColor: '#C2941C',
                        borderRadius: 6,
                        maxBarThickness: 26
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef0f3' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        // توزيع الأنواع
        var typeCtx = document.getElementById('activityTypeChart');
        if (typeCtx) {
            new Chart(typeCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_map('act_label', array_keys($act_by_type)), JSON_UNESCAPED_UNICODE); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($act_by_type)); ?>,
                        backgroundColor: goldPalette,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { family: 'IBM Plex Sans Arabic, Tajawal, Cairo, sans-serif' }, padding: 12, boxWidth: 14 } }
                    }
                }
            });
        }
    })();
</script>
<?php endif; ?>

</body>

</html>
