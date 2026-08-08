<?php
/**
 * includes/fin_analysis_shell.php — قشرةُ شاشاتِ التحليلِ الماليِّ العشر
 * ─────────────────────────────────────────────────────────────────────────
 * «لا مكوّنَ جديدًا إذا كان هناك معتمدٌ يؤدي الوظيفةَ نفسَها» (المبدأ ١١):
 * الشاشاتُ العشرُ تشترك في الحارسِ والغلافِ الحاكمِ وشريطِ الفترةِ ورأسِ
 * الصفحةِ وحاملِ الأفعال — وتختلف في محتواها وحدَه.
 *
 * عقدُ الإعداد $FA_SCREEN:
 *   file · title · icon · about · notes[] · context[] (بطاقاتُ الرأس)
 * والشاشةُ الحاملةُ تُعرّف دالةَ fa_render_body() لجسمها.
 */
if (!isset($FA_SCREEN) || !is_array($FA_SCREEN)) {
    exit('FA-500: القشرةُ تحتاج عقدَ إعدادٍ من الشاشةِ الحاملة');
}

require_once __DIR__ . '/../includes/session_bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/screen_contract.php';
require_once __DIR__ . '/../app/Services/Finance/FinAnalysisService.php';
require_once __DIR__ . '/../app/Services/Finance/CoaService.php';

use App\Services\Finance\FinAnalysisService as FA;
use App\Services\Finance\CoaService;

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header('Location: ../login.php'); exit(); }

$__pp = check_page_permissions($conn, 'Finance/' . $FA_SCREEN['file']);
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php',
        'لا تملك صلاحية عرض ' . $FA_SCREEN['title'],
        'FIN-PERM-403', 'شاشاتُ التحليلِ الماليِّ يمنحها مديرُ الصلاحيات');
}
$fa_can_write = $is_super_admin || !empty($__pp['can_add']) || !empty($__pp['can_edit']);
ems_shell_axes($__pp);

$fa_period = isset($_GET['period']) && preg_match('/^\d{4}-\d{2}$/', $_GET['period'])
    ? $_GET['period'] : date('Y-m');

$page_title = 'إيكوبيشن | ' . $FA_SCREEN['title'];
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = $FA_SCREEN['title'];
    $header_icon = $FA_SCREEN['icon'];
    $header_actions = array();
    $header_back = array();
    $header_context = array('الفترة' => $fa_period) + ($FA_SCREEN['context'] ?? array());
    include __DIR__ . '/page_header.php';
    ems_screen_about($FA_SCREEN['about'], $FA_SCREEN['notes'] ?? array());
    ?>

    <form method="get" class="ems-toolbar" style="align-items:flex-end">
        <label style="font-size:.8rem">الفترة
            <input type="month" name="period" value="<?php echo htmlspecialchars($fa_period); ?>"
                   class="form-control form-control-sm">
        </label>
        <?php if (!empty($FA_SCREEN['filters'])) { echo $FA_SCREEN['filters']; } ?>
        <button class="ems-btn-primary" type="submit">عرض</button>
    </form>

    <?php fa_render_body($conn, $company_id, $fa_period, $fa_can_write, $uid); ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script>
/* حاملُ أفعالِ التحليلِ العشرة — نداءٌ واحدٌ لكلها بعقدِها الموحَّد */
function faPost(action, data, done) {
    var fd = new FormData();
    fd.append('do', action);
    fd.append('period', '<?php echo htmlspecialchars($fa_period); ?>');
    Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
    if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
    fetch('fin_analysis_actions.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            if (!j.ok) { alert((j.code || 'FIN-500') + ': ' + (j.msg || 'تعذر التنفيذ')); return; }
            if (done) { done(j); } else { location.reload(); }
        })
        .catch(function () { alert('تعذر الاتصال — أعد المحاولة'); });
}
</script>
</body>
</html>
