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
        return 'غير متوفر';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return 'غير متوفر';
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
require_once __DIR__ . '/../includes/profile_kit.php';   // عُدّةُ بطاقةِ الكِيان — التأليفُ بديلُ النسخ
ems_shell_axes(null);
include("../inheader.php");
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

<div class="main profile-main ems-profile ems-unified-page-shell">

    <?php
    $header_title   = 'الملف الشخصي';
    $header_icon    = 'fas fa-id-badge';
    /* ◆ زرّا «كلمةُ المرور» و«الإعدادات» كانا داخلَ لوحِ الهويةِ بصنفَينِ
         محليَّينِ (`profile-hero-btn primary|ghost`) — نسخةٌ سادسةٌ من زرٍّ.
         وموضعُهما الصحيحُ شريطُ أفعالِ الرأسِ الموحَّد: مكانٌ واحدٌ للأفعالِ
         في كلِّ شاشةٍ، وأصنافُ الأزرارِ المعتمدةُ وحدَها. */
    $header_actions = array(
        array('href' => $change_password_url, 'class' => 'add-btn', 'icon' => 'fas fa-key',  'label' => 'تغيير كلمة المرور'),
        array('href' => $settings_url,        'class' => 'add-btn', 'icon' => 'fas fa-gear', 'label' => 'الإعدادات'),
    );
    $header_back    = array('href' => 'dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');

    /* UXW-01 ⑨: حزمةُ الحالاتِ الدنيا — مخفيةٌ افتراضًا ويُظهرها منطقُ الشاشةِ عند حالِها */
    echo ems_states_bundle('لا سجل نشاط لحسابك بعد',
        'الإحصاءات والرسوم تبنى من نشاطك المسجل على الشاشات');
    ?>

    <div class="profile-shell">

        <?php
        /* ══ لوحُ الهوية ═══════════════════════════════════════════════════
           كان بلغةٍ خاصةٍ كاملة: `profile-hero` و`profile-avatar` و
           `profile-badges` و`profile-badge` بأربعِ حالاتٍ (`role` · `user` ·
           `status-on` · `status-off`) — **نسخةٌ سادسةٌ** من لوحِ هويةٍ ومن
           شارة. صار المكوّنَ نفسَه: الحرفانِ الأولانِ يحلُّهما رسمُ المكوّنِ
           (`monogram`)، والدورُ واسمُ المستخدمِ رقيقتان، والحالةُ شارةً. */
        echo ems_profile_hero(array(
            'name'   => $display_name,
            'icon'   => 'fas fa-id-badge',
            'status' => array(
                'text' => $is_active ? 'حساب نشط' : 'حساب موقوف',
                'tone' => $is_active ? 'ok' : 'danger',
                'icon' => $is_active ? 'fas fa-circle-check' : 'fas fa-circle-minus',
            ),
            'chips'  => array(
                array('text' => $role_text, 'icon' => 'fas fa-user-shield'),
                array('text' => isset($user['username']) ? $user['username'] : '', 'icon' => 'fas fa-at', 'mono' => true),
            ),
        ));
        ?>

        <div class="profile-grid">

            <?php
            /* ══ معلوماتُ الحساب ═══════════════════════════════════════════
               كانت ستَّ `profile-row` كلٌّ منها `label` + `value` — أي شبكةَ
               حقائقَ مبنيةً يدويًّا. صارت شبكةَ حقائقِ المكوّنِ نفسِها،
               والغائبُ فيها يُعلَن «—» بصنفِ غيابٍ بدل نصٍّ باهتٍ يدويّ. */
            echo ems_profile_section_open(array('title' => 'معلومات الحساب', 'icon' => 'fas fa-user'));
            echo ems_profile_facts(array(
                array('label' => 'الاسم الكامل',     'value' => $display_name),
                array('label' => 'اسم المستخدم',     'value' => isset($user['username']) ? $user['username'] : ''),
                array('label' => 'البريد الإلكتروني', 'value' => isset($user['email']) ? $user['email'] : ''),
                array('label' => 'رقم الهاتف',       'value' => isset($user['phone']) ? $user['phone'] : ''),
                array('label' => 'الدور / الصلاحية', 'value' => $role_text),
            ));
            ?>
            <?php
            echo ems_profile_section_close();

            /* ══ المؤسسةُ والعمل ══════════════════════════════════════════ */
            echo ems_profile_section_open(array('title' => 'بيانات المؤسسة والعمل', 'icon' => 'fas fa-building'));
            echo ems_profile_facts(array(
                array('label' => 'الشركة',       'value' => $company_text),
                array('label' => 'المشروع',      'value' => $project_text),
                array('label' => 'رقم المستخدم', 'value' => '#' . intval($user_id)),
            ));
            echo ems_profile_section_close();

            /* ══ النشاطُ والتواريخ ════════════════════════════════════════ */
            $last_login = '';
            if (!empty($user['last_login_at'])) {
                $last_login = $user['last_login_at'];
            } elseif (!empty($session_user['last_login'])) {
                $last_login = $session_user['last_login'];
            }
            echo ems_profile_section_open(array('title' => 'النشاط والتواريخ', 'icon' => 'fas fa-clock-rotate-left'));
            echo ems_profile_facts(array(
                array('label' => 'تاريخ الإنشاء',     'value' => profile_date(isset($user['created_at']) ? $user['created_at'] : '', false)),
                array('label' => 'آخر تحديث',         'value' => profile_date(isset($user['updated_at']) ? $user['updated_at'] : '')),
                array('label' => 'آخر تسجيل دخول',    'value' => profile_date($last_login)),
            ));
            echo ems_profile_section_close();

            /* ══ إجراءاتٌ سريعة ═══════════════════════════════════════════
               روابطُ لا حقائق — فتبقى روابطَ في قسمِ المكوّن، بصنفِ `pf-qlink`
               وحدَه (سلوكُ سطرٍ قابلٍ للنقر، لا لغةَ بطاقةٍ ثانية). */
            echo ems_profile_section_open(array('title' => 'إجراءات سريعة', 'icon' => 'fas fa-bolt'));
            ?>
                <a href="<?php echo htmlspecialchars($change_password_url, ENT_QUOTES, 'UTF-8'); ?>" class="pf-qlink">
                    <span><i class="fas fa-key"></i> تغيير كلمة المرور</span>
                    <i class="fas fa-arrow-left pf-arrow"></i>
                </a>
                <a href="<?php echo htmlspecialchars($settings_url, ENT_QUOTES, 'UTF-8'); ?>" class="pf-qlink">
                    <span><i class="fas fa-gear"></i> إعدادات النظام</span>
                    <i class="fas fa-arrow-left pf-arrow"></i>
                </a>
                <a href="dashboard.php" class="pf-qlink">
                    <span><i class="fas fa-house"></i> لوحة التحكم</span>
                    <i class="fas fa-arrow-left pf-arrow"></i>
                </a>
                <a href="<?php echo htmlspecialchars($logout_url, ENT_QUOTES, 'UTF-8'); ?>" class="pf-qlink pf-danger">
                    <span><i class="fas fa-power-off"></i> تسجيل الخروج</span>
                    <i class="fas fa-arrow-left"></i>
                </a>
            <?php echo ems_profile_section_close(); ?>

        </div>

        <?php if ($activity_available && $act_total > 0): ?>

        <?php
        /* ══ شريطُ الحصيلة ════════════════════════════════════════════════
           كانت خمسَ `stat-card` كلٌّ منها أيقونةٌ بأربعةِ ألوانٍ محليةٍ
           (`green` · `blue` · `gray` · الافتراضي) وقيمةٌ وتسمية — **نسخةٌ
           سادسةٌ** من شريطِ مؤشرات. صارت شريطَ المكوّنِ بنغماتِه المعلَنة. */
        echo ems_profile_stats(array(
            array('value' => number_format($act_total),   'label' => 'إجمالي الأنشطة'),
            array('value' => number_format($act_logins),  'label' => 'مرات تسجيل الدخول', 'tone' => 'ok'),
            array('value' => number_format($act_creates), 'label' => 'عمليات إضافة',      'tone' => 'info'),
            array('value' => number_format($act_updates), 'label' => 'عمليات تعديل',      'tone' => 'muted'),
            array('value' => number_format($act_today),   'label' => 'نشاط اليوم',        'tone' => 'gold'),
        ));
        ?>

        <!-- الرسوم البيانية -->
        <div class="profile-analytics">
            <?php echo ems_profile_section_open(array('title' => 'النشاط خلال آخر 14 يوما', 'icon' => 'fas fa-chart-column')); ?>
                <div class="chart-wrap"><canvas id="activityTrendChart"></canvas></div>
            <?php echo ems_profile_section_close(); ?>
            <?php echo ems_profile_section_open(array('title' => 'توزيع أنواع النشاط', 'icon' => 'fas fa-chart-pie')); ?>
                <div class="chart-wrap small"><canvas id="activityTypeChart"></canvas></div>
            <?php echo ems_profile_section_close(); ?>
        </div>

        <!-- آخر الأنشطة + أكثر الشاشات -->
        <div class="profile-analytics">
            <?php echo ems_profile_section_open(array('title' => 'آخر الأنشطة', 'icon' => 'fas fa-clock-rotate-left')); ?>
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
                                <th class="ems-fn-th" data-fn="1">حقول تحتاج اعتمادا</th>
                                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                                <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                                </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($act_recent as $rec): ?>
                                <?php
                                $rtype = $rec['action_type'] !== null && $rec['action_type'] !== '' ? $rec['action_type'] : 'other';
                                /* الشارةُ كانت بصنفٍ محليٍّ لثلاثةِ أنواعٍ وحدَها والباقي بلا
                                   لون. صارت بنغماتِ المكوّن — ولكلِّ نوعٍ نغمةٌ معلَنة. */
                                $actTone = ems_profile_map($rtype, array(
                                    'login'  => 'ok',
                                    'logout' => 'muted',
                                    'create' => 'info',
                                    'update' => 'warn',
                                    'delete' => 'danger',
                                ));
                                $screen = $rec['screen_name'] !== '' ? $rec['screen_name'] : $rec['module_name'];
                                ?>
                                <tr>
                                    <td><?php echo ems_profile_badge(act_label($rtype), $actTone, array('icon' => 'fas ' . act_icon($rtype))); ?></td>
                                    <td><?php echo htmlspecialchars(profile_val($screen, '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(profile_val($rec['module_name'], '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="rtl-number"><?php echo htmlspecialchars(profile_date($rec['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php echo ems_profile_section_close(); ?>

            <?php echo ems_profile_section_open(array('title' => 'أكثر الشاشات استخداما', 'icon' => 'fas fa-ranking-star')); ?>
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
                    <?php echo ems_profile_note('لا توجد بيانات كافية.', 'info'); ?>
                <?php endif; ?>
            <?php echo ems_profile_section_close(); ?>
        </div>

        <?php elseif ($activity_available): ?>
        <?php
        echo ems_profile_section_open(array('title' => 'سجل النشاط', 'icon' => 'fas fa-clock-rotate-left'));
        echo ems_profile_note('لا يوجد سجل نشاط مسجل لحسابك حتى الآن.', 'info');
        echo ems_profile_section_close();
        ?>
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
                    + 'لا بيانات في الفترة المعروضة — الرسم لا يعرض بمحاور افتراضية</div>';
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
