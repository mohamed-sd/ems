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

$session_user = $_SESSION['user'];
$user_id = isset($session_user['id']) ? intval($session_user['id']) : 0;

// ── جلب أحدث بيانات المستخدم من قاعدة البيانات (عبر البوابة؛ includeDeleted كسلوك الأصل) ──
$pr_gate = (strval($_SESSION['user']['role'] ?? '') === '-1')
    ? ems_tenant_db()->forAllTenants('profile super') : ems_tenant_db();
$user = $session_user;
if ($user_id > 0) {
    try {
        $row = $pr_gate->selectOne('users', array(
            'columns' => array('id', 'name', 'username', 'email', 'phone', 'role', 'role_id', 'status',
                'project_id', 'company_id', 'created_at', 'updated_at', 'last_login_at'),
            'where' => array('id' => $user_id), 'includeDeleted' => true));
        if ($row) { $user = array_merge($session_user, $row); }
    } catch (\Throwable $t) { error_log('profile.php user: ' . $t->getMessage()); }
}

// ── اسم الدور ──
$role_text = 'مستخدم';
$role_value = isset($user['role']) ? strval($user['role']) : '';
if ($role_value !== '') {
    $role_id_int = intval($role_value);
    try {
        $rrow = $pr_gate->selectOne('roles', array('columns' => array('name'), 'where' => array('id' => $role_id_int)));
        if ($rrow && trim($rrow['name']) !== '') { $role_text = $rrow['name']; }
    } catch (\Throwable $t) { error_log('profile.php role: ' . $t->getMessage()); }
}
if ($role_value === '-1') {
    $role_text = 'الإدارة العليا';
}

// ── اسم المشروع ──
$project_text = '';
$project_id_val = isset($user['project_id']) ? intval($user['project_id']) : 0;
if ($project_id_val > 0) {
    try {
        $prow = $pr_gate->selectOne('project', array('columns' => array('name'),
            'where' => array('id' => $project_id_val), 'includeDeleted' => true));
        if ($prow) { $project_text = $prow['name']; }
    } catch (\Throwable $t) { error_log('profile.php project: ' . $t->getMessage()); }
}

// ── اسم الشركة ──
$company_text = '';
$company_id_val = isset($user['company_id']) ? intval($user['company_id']) : 0;
if ($company_id_val > 0) {
    // [مُستثنى موثَّق — قراءة اسم شركة الجلسة] admin_companies جدول منصّةٍ مقيَّد تعاقديًا
    // (T_RESTRICTED بانتظار عقد دفعة المزوّد admin/)، والمقروء هنا اسمُ عرض شركةِ
    // المستخدم نفسه بمعرّف جلسته — تبقى القراءة خامًا حتى تُعرَّف قناة المنصّة.
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

// جدول activity_logs قائم ومسجَّل في البوابة — سقط فحص SHOW TABLES الذاتي
$has_activity_table = ($user_id > 0);

if ($has_activity_table) {
    $activity_available = true;
    $day_counts = array();
    // (قراءات ذاتية بمعرّف مستخدم الجلسة عبر البوابة؛ CURRENT_DATE → تاريخ PHP معاملًا)
    try {
        // إجمالي النشاط + توزيع الأنواع
        $pr_rows = $pr_gate->scopedQuery(array('scope' => array('al' => 'activity_logs')),
            "SELECT al.action_type, COUNT(*) c FROM activity_logs al WHERE al.user_id = ? AND {TENANT_SCOPE} GROUP BY al.action_type ORDER BY c DESC",
            array($user_id));
        foreach ($pr_rows as $row) {
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

        // نشاط اليوم
        $pr_rows = $pr_gate->scopedQuery(array('scope' => array('al' => 'activity_logs')),
            "SELECT COUNT(*) c FROM activity_logs al WHERE al.user_id = ? AND DATE(al.created_at) = ? AND {TENANT_SCOPE}",
            array($user_id, date('Y-m-d')));
        if (!empty($pr_rows)) { $act_today = intval($pr_rows[0]['c']); }

        // أكثر الشاشات استخداماً
        $pr_rows = $pr_gate->scopedQuery(array('scope' => array('al' => 'activity_logs')),
            "SELECT COALESCE(NULLIF(al.screen_name,''), NULLIF(al.module_name,''), 'غير معروف') s, COUNT(*) c FROM activity_logs al WHERE al.user_id = ? AND {TENANT_SCOPE} GROUP BY s ORDER BY c DESC LIMIT 6",
            array($user_id));
        foreach ($pr_rows as $row) {
            $act_top_screens[] = array('name' => $row['s'], 'count' => intval($row['c']));
        }

        // النشاط خلال آخر 14 يوماً
        $pr_rows = $pr_gate->scopedQuery(array('scope' => array('al' => 'activity_logs')),
            "SELECT DATE(al.created_at) d, COUNT(*) c FROM activity_logs al WHERE al.user_id = ? AND al.created_at >= ? AND {TENANT_SCOPE} GROUP BY DATE(al.created_at)",
            array($user_id, date('Y-m-d', strtotime('-13 day'))));
        foreach ($pr_rows as $row) {
            $day_counts[$row['d']] = intval($row['c']);
        }

        // آخر الأنشطة
        $pr_rows = $pr_gate->scopedQuery(array('scope' => array('al' => 'activity_logs')),
            "SELECT al.action_type, al.screen_name, al.module_name, al.ip_address, al.created_at FROM activity_logs al WHERE al.user_id = ? AND {TENANT_SCOPE} ORDER BY al.id DESC LIMIT 12",
            array($user_id));
        foreach ($pr_rows as $row) {
            $act_recent[] = $row;
            if ($last_activity_ip === '' && !empty($row['ip_address'])) {
                $last_activity_ip = $row['ip_address'];
            }
        }
    } catch (\Throwable $t) { error_log('profile.php activity: ' . $t->getMessage()); }
    for ($d = 13; $d >= 0; $d--) {
        $key = date('Y-m-d', strtotime("-$d day"));
        $act_days_labels[] = date('m-d', strtotime($key));
        $act_days_values[] = isset($day_counts[$key]) ? $day_counts[$key] : 0;
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
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include("../inheader.php");
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

<div class="main profile-main ems-unified-page-shell">

    <?php
    $header_title   = 'الملف الشخصي';
    $header_icon    = 'fas fa-id-badge';
    $header_actions = array();
    $header_back    = array('href' => 'dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');

    /* UXW-01 ⑨: حزمةُ الحالاتِ الدنيا — مخفيةٌ افتراضًا ويُظهرها منطقُ الشاشةِ عند حالِها */
    echo ems_states_bundle('لا سجلَّ نشاطٍ لحسابك بعدُ',
        'الإحصاءاتُ والرسومُ تُبنى من نشاطِك المسجَّلِ على الشاشات');
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
                        <i class="fas fa-circle pf-dot"></i> <?php echo $is_active ? 'حساب نشط' : 'حساب موقوف'; ?>
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
                            <i class="fas fa-circle pf-dot"></i> <?php echo $is_active ? 'نشط' : 'موقوف'; ?>
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

                <a href="<?php echo htmlspecialchars($change_password_url, ENT_QUOTES, 'UTF-8'); ?>" class="profile-row pf-qlink">
                    <span class="profile-row-label"><i class="fas fa-key"></i> تغيير كلمة المرور</span>
                    <span class="profile-row-value"><i class="fas fa-arrow-left pf-arrow"></i></span>
                </a>
                <a href="<?php echo htmlspecialchars($settings_url, ENT_QUOTES, 'UTF-8'); ?>" class="profile-row pf-qlink">
                    <span class="profile-row-label"><i class="fas fa-gear"></i> إعدادات النظام</span>
                    <span class="profile-row-value"><i class="fas fa-arrow-left pf-arrow"></i></span>
                </a>
                <a href="dashboard.php" class="profile-row pf-qlink">
                    <span class="profile-row-label"><i class="fas fa-house"></i> لوحة التحكم</span>
                    <span class="profile-row-value"><i class="fas fa-arrow-left pf-arrow"></i></span>
                </a>
                <a href="<?php echo htmlspecialchars($logout_url, ENT_QUOTES, 'UTF-8'); ?>" class="profile-row pf-qlink">
                    <span class="profile-row-label pf-danger"><i class="fas fa-power-off pf-danger"></i> تسجيل الخروج</span>
                    <span class="profile-row-value"><i class="fas fa-arrow-left pf-danger"></i></span>
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
                                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                                <th class="ems-fn-th" data-fn="1">كود الموظف</th>
                                <th class="ems-fn-th" data-fn="1">الاسم</th>
                                <th class="ems-fn-th" data-fn="1">رقم الهوية</th>
                                <th class="ems-fn-th" data-fn="1">تاريخ الميلاد</th>
                                <th class="ems-fn-th" data-fn="1">الهاتف</th>
                                <th class="ems-fn-th" data-fn="1">البريد</th>
                                <th class="ems-fn-th" data-fn="1">حالة اجتماعية</th>
                                <th class="ems-fn-th" data-fn="1">المستفيدون</th>
                                <th class="ems-fn-th" data-fn="1">الحساب البنكي</th>
                                <th class="ems-fn-th" data-fn="1">الوثائق المرفقة</th>
                                <th class="ems-fn-th" data-fn="1">تاريخ آخر تحديث</th>
                                <th class="ems-fn-th" data-fn="1">حقول تحتاج اعتمادًا</th>
                                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                                <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
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
                            <!-- UXW-01 ②: عرضُ الشريطِ محسوبٌ لحظيًّا من البيانات — تصريحُ data-allow-style -->
                            <div class="top-screen-bar"><span data-allow-style style="width: <?php echo $pct; ?>%;"></span></div>
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

        /* UXW-01 ①: ألوانُ الرسومِ تُقرأ من رموزِ اللوحةِ المعرَّفةِ على .profile-shell
           (--pf-chart-*) لا من ثوابتَ مدفونةٍ في JS */
        var pfShell = document.querySelector('.profile-shell');
        var pfCss = pfShell ? getComputedStyle(pfShell) : null;
        function pfTok(name) { return pfCss ? pfCss.getPropertyValue(name).trim() : ''; }
        var goldPalette = ['--pf-chart-1', '--pf-chart-2', '--pf-chart-3', '--pf-chart-4', '--pf-chart-5',
            '--pf-chart-6', '--pf-chart-7', '--pf-chart-8', '--pf-chart-9', '--pf-chart-10'].map(pfTok);

        /* UI-DEF-07 (L4): الكتلةُ محكومةٌ خادميًّا بـact_total>0 — والحارسُ
           النصيُّ يوحّد العقدَ: لا رسمَ بلا بياناتٍ بمحاورَ افتراضية. */
        function emsChartGuard(c, seriesArrays, renderFn) {
            var t = 0;
            (seriesArrays || []).forEach(function (a) { (a || []).forEach(function (v) { t += Math.abs(parseFloat(v) || 0); }); });
            if (t > 0) { return renderFn(); }
            var host = c && c.parentNode ? c.parentNode : null;
            if (host) {
                host.innerHTML = '<div class="pf-chart-empty">'
                    + 'لا بيانات في الفترة المعروضة — الرسم لا يُعرض بمحاور افتراضية</div>';
            }
            return null;
        }

        // الاتجاه خلال 14 يوماً
        var trendCtx = document.getElementById('activityTrendChart');
        if (trendCtx) {
            emsChartGuard(trendCtx, [<?php echo json_encode($act_days_values); ?>], function () {
            return new Chart(trendCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($act_days_labels, JSON_UNESCAPED_UNICODE); ?>,
                    datasets: [{
                        label: 'عدد الأنشطة',
                        data: <?php echo json_encode($act_days_values); ?>,
                        backgroundColor: pfTok('--pf-chart-bar'),
                        hoverBackgroundColor: pfTok('--pf-chart-7'),
                        borderRadius: 6,
                        maxBarThickness: 26
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: pfTok('--pf-chart-grid') } },
                        x: { grid: { display: false } }
                    }
                }
            });
            });
        }

        // توزيع الأنواع
        var typeCtx = document.getElementById('activityTypeChart');
        if (typeCtx) {
            emsChartGuard(typeCtx, [<?php echo json_encode(array_values($act_by_type)); ?>], function () {
            return new Chart(typeCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_map('act_label', array_keys($act_by_type)), JSON_UNESCAPED_UNICODE); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($act_by_type)); ?>,
                        backgroundColor: goldPalette,
                        borderWidth: 2,
                        borderColor: pfTok('--pf-chart-border')
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
            });
        }
    })();
</script>
<?php endif; ?>

</body>

</html>
