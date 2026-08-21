<?php
/**
 * main/role_board.php — اللوحة العامة للأدوار (UX-00 §7 · UX-01 §5)
 * ─────────────────────────────────────────────────────────────────────────
 * شاشةٌ واحدةٌ تصيّر لوحةَ أي دورٍ من إعداده في roleBoardGenericConfig —
 * «تختلف محتوياتُها لا بنيتُها»: ثمانيةُ أدوارٍ بلا ثمانية ملفات.
 * الأدوارُ ذواتُ اللوحات المخصصة (17·13·16·23·26) لا تمرّ من هنا — خريطتُها
 * في roleBoardRoute تسبق. كلُّ رقمٍ ينقر لمصدره والتنبيهاتُ من §8 نصًّا.
 *
 * ⚠ **حالتُها اليوم (2026-08-21): مُحوِّلٌ لا شاشة.** بعد أن صارت «الرئيسية»
 *   لوحةَ كلِّ صاحبِ إعدادٍ عام (قرارُ المالك — انظر خريطةَ roleBoardRoute)،
 *   لم يعد أحدٌ يبلغ جسدَها: كلُّ داخلٍ يُردُّ إلى main/dashboard.php من الحارسِ
 *   أدناه. والجسدُ يبقى عمدًا **مسارَ رجوع**: إعادةُ دورٍ إلى هذه الشاشةِ
 *   سطرٌ واحدٌ في الخريطة، لا إعادةُ بناء. ومحرّكُ الحسابِ مشتركٌ بينهما
 *   (roleBoardBuild) فلا تتقادم هذه النسخةُ ما دامت تلك تعمل.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/role_board.php';
require_once __DIR__ . '/../includes/finreq_badges.php';

$current_role    = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin  = ($current_role === '-1');
$company_id      = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$current_user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

// الدورُ الفعّال للوحة: دورُ الجلسة، أو أبوه إن كان فرعيًّا بلا إعدادٍ خاص
$rb_rid = roleBoardConfigRole(ems_tenant_db(), $current_role);
if ($rb_rid <= 0) { header("Location: ../main/dashboard.php"); exit(); }
$rb_cfg = roleBoardGenericConfig($rb_rid);

// لوحتان لصاحبٍ واحدٍ لا تجتمعان: الدورُ الذي صارت «الرئيسية» لوحتَه
// (إدارة التشغيل — قرار المالك 2026-08-21) يُردُّ إليها من هنا أيضًا، فرابطُ
// «لوحة الإدارة» في السايدبار يفتح اللوحةَ نفسَها لا نسخةً ثانيةً منها.
if (roleBoardRoute(intval($current_role), $rb_rid) === 'main/dashboard.php') {
    header('Location: ../main/dashboard.php');
    exit();
}

// الحارس: شاشةٌ مسجَّلةٌ بمنحٍ صريحةٍ لكل دورٍ عام — لا افتراضَ سماح
$page_permissions = check_page_permissions($conn, 'main/role_board.php');
$can_view = $is_super_admin ? true : $page_permissions['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض لوحة الدور ❌', 'GOV-PERM-403', ''); exit(); }

$rb_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('role board super') : ems_tenant_db();
$today = date('Y-m-d');

// ①-⑦ من المحرّك الموحّد (includes/role_board.php · roleBoardBuild) — الحسابُ
// في دالّةٍ واحدةٍ تقرأ منها هذه الشاشةُ و«الرئيسية» معًا، فلا تتفرّق نسختان.
$rb_board = roleBoardBuild($conn, $rb_gate, $rb_rid, $current_user_id);
$rb_cards        = $rb_board['cards'];
$rb_tasks        = $rb_board['tasks'];
$rb_approvals    = $rb_board['approvals'];
$rb_alerts       = $rb_board['alerts'];
$rb_quick        = $rb_board['quick'];
$rb_recent       = $rb_board['recent'];
$rb_pulse        = $rb_board['pulse'];
$rb_pulse_title  = $rb_board['pulse_title'];
$rb_pulse_series = $rb_board['pulse_series'];

$page_title = 'إيكوبيشن | ' . $rb_cfg['title'];
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main role-board-main ems-unified-page-shell">
    <?php
    $header_title = $rb_cfg['title']; $header_icon = $rb_cfg['icon'];
    $header_actions = array();
    $header_back = array();
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا مؤشراتِ يومٍ محسوبةً لدورِك الآن', 'افتحِ الصندوقَ أو المهامَّ من الشريطِ الجانبي — والمؤشراتُ تُحسب مع أولِ حركةٍ مسجَّلة');
    ?>
    <style>
      .rbd-lead { margin: 4px 2px 10px; }
      /* ① خمسُ بطاقاتٍ في صفٍّ واحد.
           الوزنُ لا الرغبة: `ems-statcards.css` يفرض أربعةَ أعمدةٍ بـ`!important`
           على محدِّدٍ وزنُه (0,5,1) — `body.ems-site .main :is(…):not(.dt-button)
           :not(.btn-close)`. فمحدِّدُ الشاشةِ يزيدُه صنفًا ليعلوَه (0,6,1)، وإلا
           بقيت الأربعةُ وظنَّ القارئُ أن السطرَ لم يُكتب. والانكساراتُ تُعاد هنا
           كاملةً لأن الاستعلامَ لا يزيد وزنًا: قاعدةُ الشاشةِ الأساسيةُ تعلو
           انكساراتِ المركزيِّ فتُبطلها لو تُركت. */
      .rbd-kpi-grid,
      body.ems-site .main .rbd-kpi-grid.ems-statgrid:not(.dt-button):not(.btn-close) {
        display: grid !important;
        grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
        gap: 12px !important;
      }
      @media (max-width: 1100px) {
        .rbd-kpi-grid,
        body.ems-site .main .rbd-kpi-grid.ems-statgrid:not(.dt-button):not(.btn-close) {
          grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        }
      }
      @media (max-width: 820px) {
        .rbd-kpi-grid,
        body.ems-site .main .rbd-kpi-grid.ems-statgrid:not(.dt-button):not(.btn-close) {
          grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }
      }
      @media (max-width: 560px) {
        .rbd-kpi-grid,
        body.ems-site .main .rbd-kpi-grid.ems-statgrid:not(.dt-button):not(.btn-close) {
          grid-template-columns: 1fr !important;
        }
      }
    </style>
    <p class="text-muted rbd-lead"><i class="fas fa-mug-hot"></i> أسئلةُ أول اليوم لدورك — اضغط أي رقمٍ لفتح مصدره. (<?php echo $today; ?>)</p>

    <!-- ① مؤشرات اليوم — بطاقة KPI السباعية (UI-07: صفر رقم بلا عقده السبعة).
         الوحدة «سجل» حقيقية (الاستعلامات COUNT) والفترة «لحظي» حقيقية (تُقرأ الآن)،
         والمقارنة تُعلن غيابها صراحةً بدل ادعائها — أول تعميم للمكوّن (17 لوحة). -->
    <?php require_once __DIR__ . '/../includes/kpi_card.php'; ?>
    <div class="rbd-kpi-grid">
        <?php foreach ($rb_cards as $c): list($icon, $val, $lbl, $tone, $href) = $c;
            echo ems_kpi_card(array(
                'title'  => $lbl,
                'value'  => (string) $val,
                'unit'   => 'سجل',
                'period' => 'لحظي (' . $today . ')',
                'status' => in_array($tone, array('ok', 'warn', 'err'), true) ? $tone : 'neutral',
                'drill'  => $href,
                'icon'   => $icon,
            ));
        endforeach; ?>
    </div>

    <?php include __DIR__ . '/../includes/role_board_widgets.php'; ?>
</div>
<script src="/ems/assets/vendor/chartjs/chart.umd.min.js"></script>
</body>
</html>
