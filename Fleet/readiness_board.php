<?php
/**
 * Fleet/readiness_board.php — لوحةُ الجاهزية الشبكةُ الحية (M-26 · الشاشة 196)
 * ───────────────────────────────────────────────────────────────────────────
 * UX-10 §5.2: «الأسطولُ شبكةَ خلايا: كلُّ معدةٍ بلونها الآن (تعمل · استعداد ·
 * متوقفة · صيانة) · جاهزية٪ حية · **كلُّ خليةٍ تنقر إلى بطاقتها — لا طريقَ
 * مسدودًا**» — الجاهزيةُ **محسوبةٌ لا حقولًا محلية**.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Operations/OperationsBoardService.php';
require_once __DIR__ . '/../includes/screen_contract.php';

use App\Services\Operations\OperationsBoardService as OBS;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$is_super   = (strval($_SESSION['user']['role'] ?? '') === '-1');
if (!$is_super && $company_id <= 0) { header("Location: ../login.php"); exit(); }

$prj  = intval($_GET['project_id'] ?? 0);
$type = strval($_GET['type'] ?? '');
$grid = OBS::readinessGrid($conn, $company_id, $prj, $type);

$COLORS = array('working' => 'var(--c-1a7f37, #1a7f37)', 'idle' => 'var(--c-b58a00, #b58a00)',
                'stopped' => 'var(--c-c62828, #c62828)', 'maintenance' => 'var(--c-6a4fb3, #6a4fb3)');
$LABELS = array('working' => 'تعمل', 'idle' => 'استعداد', 'stopped' => 'متوقفة', 'maintenance' => 'صيانة/بلاغ');

$page_title = 'إيكوبيشن | لوحة الجاهزية';
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'لوحة الجاهزية — الشبكة الحية'; $header_icon = 'fa fa-heart-pulse';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('الأسطولُ شبكةَ خلايا حية: كلُّ معدةٍ بلونها الآن، والجاهزيةُ٪ محسوبةٌ '
        . 'من مصادرها (وحداتُ اليوم · البلاغاتُ المفتوحة · حالُ الإتاحة) لا حقولًا محلية. '
        . 'كلُّ خليةٍ تنقر إلى بطاقة معدتها — لا طريقَ مسدودًا.',
        array('رشّح بالمشروع أو النوع', 'انقر الخليةَ لبطاقتها'));
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا معدةَ في شبكةِ الجاهزيةِ بهذا الترشيح',
        'وسّعِ الترشيحَ بحقلَي المشروعِ والنوعِ أعلاه أو اتركهما فارغَين لإظهارِ الكل');
    ?>

    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <div class="card"><div class="card-body fl-rb-bar">
                <!-- صندوقُ الفلاترِ الموحَّد — التصميمُ في assets/css/ems-filters.css -->
        <div class="filter">
            <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
            <div class="filter-body">
        <form method="get" class="fl-rb-filter">
            <label for="emsf_276_15f26">المشروع</label><input type="number" name="project_id" min="0" class="fl-rb-w90" id="emsf_276_15f26" placeholder="الكل" aria-label="رقمُ المشروعِ للترشيح"
                value="<?php echo $prj ?: ''; ?>">
            <label for="emsf_277_43fd1">النوع</label><input type="text" name="type" class="fl-rb-w120" id="emsf_277_43fd1" placeholder="الكل" aria-label="نوعُ المعدةِ للترشيح"
                value="<?php echo htmlspecialchars($type); ?>">
            <button type="submit" class="btn-primary">رشّح</button>
        </form>
            </div>
        </div>
        <div class="fl-rb-pct badge <?php echo ($grid['readiness_pct'] ?? 0) >= 70 ? 'badge-success' : 'badge-danger'; ?>">
            جاهزيةُ الآن: <strong><?php echo $grid['readiness_pct'] !== null
                ? ($grid['readiness_pct'] . '٪') : '—'; ?></strong>
            (<?php echo intval($grid['total']); ?> معدة)</div>
        <span class="fl-rb-legend">
        <?php foreach ($LABELS as $k => $lbl): ?>
            <span class="fl-rb-swatch" data-allow-style style="background:<?php echo $COLORS[$k]; ?>"></span>
            <small><?php echo $lbl; ?></small>&nbsp;
        <?php endforeach; ?></span>
    </div></div>

    <div class="card"><div class="card-body">
        <?php if (!$grid['cells']): ems_state_empty('لا معداتٍ بهذا الترشيح', 'أظهر الكل', 'readiness_board.php'); else: ?>
        <div class="fl-rb-grid">
            <?php foreach ($grid['cells'] as $c): ?>
                <a href="../<?php echo htmlspecialchars($c['link']); ?>" class="fl-rb-cell"
                   data-allow-style style="background:<?php echo $COLORS[$c['status']]; ?>"
                   title="<?php echo htmlspecialchars($LABELS[$c['status']]
                        . ($c['open_tickets'] > 0 ? (' · ' . $c['open_tickets'] . ' بلاغ') : '')); ?>">
                    <div class="fl-rb-name"><?php echo htmlspecialchars($c['name']); ?></div>
                    <small><?php echo htmlspecialchars($c['type']); ?> · <?php echo $LABELS[$c['status']]; ?>
                        <?php if ($c['open_tickets'] > 0): ?> · <?php echo intval($c['open_tickets']); ?> ⚠<?php endif; ?></small>
                </a>
                <?php /* ⇐ INJ-0074 · «كلُّ معدةٍ تعرض **مرجعَ آخر شهادةِ جاهزيةٍ
                         وتاريخَها ومُصدرَها**، والنقرُ عليه **يفتح الأمرَ الذي أصدرها**».
                         و«لا شهادةَ بعد» تُقال صراحةً — فالسكوتُ يُقرأ سلامةً. */ ?>
                <div class="ems-readiness-cert fl-rb-cert">
                    <?php if ($c['cert_ref'] !== '' && $c['cert_link'] !== ''): ?>
                        شهادةُ جاهزية:
                        <a href="../<?php echo htmlspecialchars($c['cert_link'], ENT_QUOTES, 'UTF-8'); ?>"
                           title="افتحْ أمرَ الصيانةِ الذي أصدرها"><?php
                            echo htmlspecialchars(mb_substr($c['cert_ref'], 0, 40), ENT_QUOTES, 'UTF-8'); ?></a>
                        · <?php echo htmlspecialchars(substr($c['cert_at'], 0, 10), ENT_QUOTES, 'UTF-8'); ?>
                        · <?php echo htmlspecialchars($c['cert_by'] !== '' ? $c['cert_by'] : '—', ENT_QUOTES, 'UTF-8'); ?>
                    <?php else: ?>
                        <span class="ems-gov-empty">لا شهادةَ جاهزيةٍ مسجَّلةٌ بعد</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
