<?php
/**
 * Governance/perm_dashboard.php — لوحةُ الصلاحيات (PERM-SCR-01 ①)
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **لوحةُ توجيهٍ لا جدول**: ثماني بطاقاتِ إحصاءٍ حيّةٍ، كلٌّ منها مدخلٌ إلى
 *   شاشتِها. لا فورمَ ولا جدولَ هنا بنصِّ الطلب.
 * ◆ **والأرقامُ مقيسةٌ من القاعدةِ في كلِّ تحميل** — لا عدّادَ محفوظٌ يتقادم.
 * ◆ الجداولُ السبعةُ `T_GLOBAL` (بلا `company_id`) — فالاستعلامُ المُجهَّزُ
 *   بـ`$conn` هو البابُ الصحيح، ولا تمسُّها بوابةُ عزلِ المستأجِر.
 * ⛔ ولا CSS محليٌّ — كلُّ التنسيقِ من الملفّاتِ الموحَّدةِ التي يحمّلها
 *   `inheader.php` تلقائيًّا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../company/login.php');
    exit();
}
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/kpi_card.php';    // بطاقةُ المؤشر: سبعةُ حقولٍ إلزامًا
require_once __DIR__ . '/../includes/date_format.php'; // مُوحِّدُ التاريخ: صيغةٌ واحدةٌ عبرَ النظام

$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');

/* حارسُ الشاشة — `check_page_permissions` بمفتاحِ المسار (M-14 BR-GOV-01). */
$MODULE_CODE = 'Governance/perm_dashboard.php';
$__pp = check_page_permissions($conn, $MODULE_CODE);
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحية للوحة الصلاحيات ', 'GOV-PERM-403',
        'اطلب المنحة من مدير الصلاحيات إن كانت ضمن عملك');
    exit();
}

/** عدَدٌ قياسيٌّ واحد — يردُّ صفرًا عند تعذُّرِ الاستعلامِ لا يكسر الشاشة. */
function permd_count($conn, $sql)
{
    $r = @mysqli_query($conn, $sql);
    if (!$r) { return 0; }
    $row = mysqli_fetch_row($r);
    return $row ? (int) $row[0] : 0;
}

$stat = array(
    'roles'        => permd_count($conn, 'SELECT COUNT(*) FROM roles'),
    'roles_on'     => permd_count($conn, "SELECT COUNT(*) FROM roles WHERE status = '1'"),
    'modules'      => permd_count($conn, 'SELECT COUNT(*) FROM modules'),
    'modules_free' => permd_count($conn, 'SELECT COUNT(*) FROM modules WHERE owner_role_id IS NULL OR owner_role_id = 0'),
    'grants'       => permd_count($conn, 'SELECT COUNT(*) FROM role_permissions WHERE can_view = 1'),
    'grants_all'   => permd_count($conn, 'SELECT COUNT(*) FROM role_permissions'),
    'groups'       => permd_count($conn, 'SELECT COUNT(*) FROM link_groups WHERE is_active = 1'),
    'navs'         => permd_count($conn, 'SELECT COUNT(*) FROM nav_items WHERE active = 1'),
    'screens'      => permd_count($conn, 'SELECT COUNT(*) FROM screen_about WHERE active = 1'),
    'reports'      => permd_count($conn, 'SELECT COUNT(*) FROM report_role_permissions'),
);
/* التغطيةُ: نسبةُ (دورٍ × وحدة) الممنوحةِ من كلِّ الممكن — مقامٌ مقيسٌ لا مفترض. */
$possible = $stat['roles'] * $stat['modules'];
$coverage = $possible > 0 ? round(($stat['grants'] / $possible) * 100, 1) : 0.0;

/* بطاقاتُ التوجيه: كلُّ بطاقةٍ شاشةٌ ولا بطاقةَ بلا وجهة.
   ⛔ **والمكوّنُ الموحَّدُ لا وسمٌ خام**: `ems_kpi_card()` يفرض سبعةَ حقولٍ
      (عنوانٌ وقيمةٌ ووحدةٌ وفترةٌ وحالةٌ وتعمُّقٌ ومقارنة) ويرفض التصييرَ بأقلّ.
      والفترةُ هنا **لحظيّةٌ صادقة**: الأرقامُ تُقاس عند كلِّ فتحٍ لا تُخزَّن. */
$NOW = 'لحظي (' . ems_fmt_date(time(), 'datetime') . ')';
$CARDS = array(
    array('الأدوار', $stat['roles'], 'دور', 'perm_roles.php', 'fa-user-tag',
          'منها ' . $stat['roles_on'] . ' نشطا', 'neutral'),
    array('الوحدات والشاشات', $stat['modules'], 'وحدة', 'perm_modules.php', 'fa-cubes',
          $stat['modules_free'] . ' بلا دور مالك', $stat['modules_free'] > 0 ? 'warn' : 'ok'),
    array('الصلاحيات الممنوحة', $stat['grants'], 'منحة', 'perm_matrix.php', 'fa-table',
          'التغطية ' . $coverage . ' بالمئة من ' . $possible . ' ممكنة', 'neutral'),
    array('مجموعات السايدبار', $stat['groups'], 'مجموعة', 'perm_link_groups.php', 'fa-layer-group',
          'النشطة وحدها', 'neutral'),
    array('بنود الملاحة', $stat['navs'], 'بند', 'perm_nav_items.php', 'fa-compass',
          'النشطة وحدها', 'neutral'),
    array('الشاشات الموصوفة', $stat['screens'], 'شاشة', 'perm_screen_guide.php', 'fa-book',
          'في دليل الشاشات', 'neutral'),
    array('صلاحيات التقارير', $stat['reports'], 'منحة', 'perm_reports.php', 'fa-file-shield',
          'صف سماح قائم', 'neutral'),
    array('صفوف الصلاحيات', $stat['grants_all'], 'صف', 'perm_system_status.php', 'fa-heartbeat',
          'منها ' . $stat['grants'] . ' بحق العرض', 'neutral'),
);

$page_title = 'إيكوبيشن | لوحة الصلاحيات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'لوحة الصلاحيات';
    $header_icon = 'fa fa-th-large';
    $header_actions = array();
    $header_back = false;
    include '../includes/page_header.php';
    ?>

    <div class="ems-grid">
        <?php foreach ($CARDS as $c) {
            echo ems_kpi_card(array(
                'title' => $c[0], 'value' => number_format((int) $c[1]), 'unit' => $c[2],
                'period' => $NOW, 'status' => $c[6], 'drill' => $c[3],
                'comparison' => $c[5], 'icon' => $c[4], 'class' => 'ems-col-3'));
        } ?>
    </div>

    <div class="card">
 <div class="card-header"><h5><i class="fa fa-circle-info"></i> كيف تقرأ هذه اللوحة</h5></div>
 <div class="card-body">
 <p>الأرقام **مقيسة من القاعدة عند كل فتح** لا محفوظة في عداد.
 والتغطية نسبة ما منح فعلا من كل (دور × وحدة) الممكنة:
 <strong><?php echo (int) $stat['grants']; ?></strong> من
 <strong><?php echo (int) $possible; ?></strong>.</p>
 <p>«وحدة بلا دور مالك» تعني شاشة مسجلة لا تنسب إلى دور يملك
 قرارها - وهي أول ما يراجع في <a href="perm_modules.php">الوحدات والشاشات</a>.</p>
 </div>
 </div>
</div>
