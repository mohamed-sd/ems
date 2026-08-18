<?php
/**
 * main/role_board.php — اللوحة العامة للأدوار (UX-00 §7 · UX-01 §5)
 * ─────────────────────────────────────────────────────────────────────────
 * شاشةٌ واحدةٌ تصيّر لوحةَ أي دورٍ من إعداده في roleBoardGenericConfig —
 * «تختلف محتوياتُها لا بنيتُها»: ثمانيةُ أدوارٍ بلا ثمانية ملفات.
 * الأدوارُ ذواتُ اللوحات المخصصة (17·13·16·23) لا تمرّ من هنا — خريطتُها
 * في roleBoardRoute تسبق. كلُّ رقمٍ ينقر لمصدره والتنبيهاتُ من §8 نصًّا.
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
$rb_rid = intval($current_role);
$rb_cfg = roleBoardGenericConfig($rb_rid);
if ($rb_cfg === null) {
    try {
        $p = ems_tenant_db()->selectOne('roles', array('columns' => array('parent_role_id'), 'where' => array('id' => $rb_rid)));
        if ($p && !empty($p['parent_role_id'])) {
            $rb_rid = intval($p['parent_role_id']);
            $rb_cfg = roleBoardGenericConfig($rb_rid);
        }
    } catch (\Throwable $t) {}
}
if ($rb_cfg === null) { header("Location: ../main/dashboard.php"); exit(); }

// الحارس: شاشةٌ مسجَّلةٌ بمنحٍ صريحةٍ لكل دورٍ عام — لا افتراضَ سماح
$page_permissions = check_page_permissions($conn, 'main/role_board.php');
$can_view = $is_super_admin ? true : $page_permissions['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض لوحة الدور ❌', 'GOV-PERM-403', ''); exit(); }

$rb_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('role board super') : ems_tenant_db();
$today = date('Y-m-d');

/** تنفيذُ عنصرِ إعدادٍ [label,icon,scope,sql,href(,tone)] عدًّا معزولًا. */
$rb_exec = function (array $def, array $params = array()) use ($rb_gate) {
    $scopeArr = array('scope' => array($def[2]['a'] => $def[2]['t']));
    if (isset($def[2]['enrich'])) { $scopeArr['enrich'] = $def[2]['enrich']; }
    return roleBoardScalar($rb_gate, $scopeArr, $def[3], $params);
};

// ① مؤشرات اليوم
$rb_cards = array();
foreach ($rb_cfg['cards'] as $def) {
    $n = $rb_exec($def);
    $tone = isset($def[5]) ? $def[5] : 'or';
    if ($tone === 'err' && $n <= 0) { $tone = 'ok'; }   // الحرجُ الصفري يهدأ لونًا لا يختفي
    $rb_cards[] = array($def[1], $n, $def[0], $tone, $def[4]);
}

// ② مهامي (من الإعداد) — والصفريُّ يختفي
$rb_tasks = array();
foreach ($rb_cfg['tasks'] as $def) {
    $n = (int) $rb_exec($def);
    if ($n > 0) { $rb_tasks[] = array('label' => $def[0], 'icon' => $def[1], 'count' => $n, 'href' => $def[4]); }
}

// ③④⑤⑦ عبر المحرك الموحّد
$rb_badges    = ems_finreq_nav_badges($conn);
$rb_approvals = roleBoardApprovals($conn, $rb_gate, $rb_rid, $rb_badges);
$rb_alerts    = roleBoardAlerts($conn, $rb_gate, $rb_rid);
$rb_quick     = roleBoardQuickActions($conn, $rb_rid, $current_user_id);
$rb_recent    = roleBoardRecent($conn, $current_user_id);

// ⑥ نبض الأداء من إعداده (سلسلةٌ ثانيةٌ اختيارية)
list($rb_pulse_title, $rb_pulse_series, $psA, $sqlA, $psB, $sqlB) = $rb_cfg['pulse'];
$rb_pulse = array('labels' => array(), 'in' => array(), 'out' => array());
for ($d = 6; $d >= 0; $d--) {
    $day = date('Y-m-d', strtotime("-{$d} days"));
    $rb_pulse['labels'][] = date('m/d', strtotime($day));
    $rb_pulse['in'][]  = $sqlA !== null ? roleBoardScalar($rb_gate, array('scope' => array($psA['a'] => $psA['t'])), $sqlA, array($day)) : 0;
    $rb_pulse['out'][] = $sqlB !== null ? roleBoardScalar($rb_gate, array('scope' => array($psB['a'] => $psB['t'])), $sqlB, array($day)) : 0;
}

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
      .rbd-kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(215px, 1fr)); gap: 16px; }
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
