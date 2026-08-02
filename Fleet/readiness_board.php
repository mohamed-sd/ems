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

$COLORS = array('working' => '#1a7f37', 'idle' => '#b58a00', 'stopped' => '#c62828', 'maintenance' => '#6a4fb3');
$LABELS = array('working' => 'تعمل', 'idle' => 'استعداد', 'stopped' => 'متوقفة', 'maintenance' => 'صيانة/بلاغ');

$page_title = 'إيكوبيشن | لوحة الجاهزية';
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
    ?>

    <div class="card"><div class="card-body" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <form method="get" style="display:flex;gap:8px;align-items:center">
            <label>المشروع</label><input type="number" name="project_id" min="0" style="width:90px"
                value="<?php echo $prj ?: ''; ?>" placeholder="الكل">
            <label>النوع</label><input type="text" name="type" style="width:120px"
                value="<?php echo htmlspecialchars($type); ?>" placeholder="الكل">
            <button type="submit" class="btn-save">رشّح</button>
        </form>
        <div class="badge <?php echo ($grid['readiness_pct'] ?? 0) >= 70 ? 'badge-success' : 'badge-danger'; ?>"
             style="font-size:16px;padding:8px 16px">
            جاهزيةُ الآن: <strong><?php echo $grid['readiness_pct'] !== null
                ? ($grid['readiness_pct'] . '٪') : '—'; ?></strong>
            (<?php echo intval($grid['total']); ?> معدة)</div>
        <span style="margin-inline-start:auto">
        <?php foreach ($LABELS as $k => $lbl): ?>
            <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:<?php echo $COLORS[$k]; ?>"></span>
            <small><?php echo $lbl; ?></small>&nbsp;
        <?php endforeach; ?></span>
    </div></div>

    <div class="card"><div class="card-body">
        <?php if (!$grid['cells']): ems_state_empty('لا معداتٍ بهذا الترشيح', 'أظهر الكل', 'readiness_board.php'); else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px">
            <?php foreach ($grid['cells'] as $c): ?>
                <a href="../<?php echo htmlspecialchars($c['link']); ?>"
                   style="display:block;border-radius:10px;padding:12px;color:#fff;text-decoration:none;
                          background:<?php echo $COLORS[$c['status']]; ?>"
                   title="<?php echo htmlspecialchars($LABELS[$c['status']]
                        . ($c['open_tickets'] > 0 ? (' · ' . $c['open_tickets'] . ' بلاغ') : '')); ?>">
                    <div style="font-weight:800"><?php echo htmlspecialchars($c['name']); ?></div>
                    <small><?php echo htmlspecialchars($c['type']); ?> · <?php echo $LABELS[$c['status']]; ?>
                        <?php if ($c['open_tickets'] > 0): ?> · <?php echo intval($c['open_tickets']); ?> ⚠<?php endif; ?></small>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
