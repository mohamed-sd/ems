<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-019 · SCN-020 · SCN-021 · SCN-023 · SCN-024 · SCN-025 · SCN-026 · SCN-027 · SCN-028 · SCN-029 · SCN-030 · SCN-031 · SCN-032 · SCN-033 · SCN-034 · SCN-035 · SCN-036 · SCN-037
/**
 * Portal/my_evaluation.php — التقييمُ الثنائي (H-18 · الشاشة 189)
 * ───────────────────────────────────────────────────────────────────────────
 * USR-01 §8-⑥: «معالجُ خطوات: ذاتيٌّ ← مديرٌ ← مناقشةٌ ← اعتماد · حفظٌ عند
 * كل خطوة» — والمديرُ لا يفتح قبل إقفال الذاتي، والفارقُ الكبيرُ بتعليق.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Portal/EvaluationService.php';
require_once __DIR__ . '/../app/Services/Portal/CapacityService.php';

use App\Services\Portal\EvaluationService as EVS;
use App\Services\Portal\CapacityService as CAP;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid        = intval($_SESSION['user']['id'] ?? 0);
$role       = strval($_SESSION['user']['role'] ?? '');
$is_super   = ($role === '-1');
if (!$is_super && $company_id <= 0) { header("Location: ../login.php"); exit(); }

$gate = $is_super ? ems_tenant_db()->forAllTenants('my evaluation super') : ems_tenant_db();
$redirect = function ($msg, $extra = '') {
    header("Location: my_evaluation.php?{$extra}" . ($extra !== '' ? '&' : '') . "msg=" . rawurlencode($msg)); exit();
};

$AXES = array('quality' => 'جودةُ العمل', 'commitment' => 'الالتزامُ بالمهل',
              'cooperation' => 'التعاون', 'initiative' => 'المبادرة');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = strval($_POST['ev_action'] ?? '');
    $evalId = intval($_POST['eval_id'] ?? 0);
    $ver = intval($_POST['version'] ?? 0);
    $q = 'capacity_id=' . intval($_POST['capacity_id'] ?? 0)
       . '&from=' . rawurlencode(strval($_POST['from'] ?? ''))
       . '&to=' . rawurlencode(strval($_POST['to'] ?? ''));
    $scores = array();
    foreach ($AXES as $k => $lbl) {
        if (isset($_POST['score_' . $k]) && $_POST['score_' . $k] !== '') {
            $scores[$k] = round(floatval($_POST['score_' . $k]), 1);
        }
    }
    if ($a === 'self_save') {
        $r = EVS::selfSave($conn, $gate, $company_id, intval($_POST['capacity_id'] ?? 0),
            strval($_POST['from'] ?? ''), strval($_POST['to'] ?? ''), $scores, $uid);
        $redirect($r['ok'] ? 'حُفظ الذاتي ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $q);
    }
    if ($a === 'self_close') {
        $r = EVS::selfClose($conn, $gate, $company_id, $evalId, $ver, $uid);
        $redirect($r['ok'] ? 'أُقفل الذاتي — وصار بابُ المدير مفتوحًا ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $q);
    }
    if ($a === 'mgr_submit') {
        $r = EVS::mgrSubmit($conn, $gate, $company_id, $evalId, $ver, $scores,
            strval($_POST['mgr_comment'] ?? ''), $uid);
        $redirect($r['ok'] ? 'سُجّل تقييمُ المدير ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $q);
    }
    if ($a === 'discuss') {
        $r = EVS::discuss($conn, $gate, $company_id, $evalId, $ver,
            strval($_POST['notes'] ?? ''), $uid);
        $redirect($r['ok'] ? 'سُجّلت المناقشة ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $q);
    }
    if ($a === 'approve') {
        $r = EVS::approve($conn, $gate, $company_id, $evalId, $ver,
            floatval($_POST['final_score'] ?? 0), $uid);
        $redirect($r['ok'] ? 'اعتُمد التقييم — ويمكن إصدارُ الشهادة ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $q);
    }
}

$myCaps = array();
foreach (CAP::activeOf($conn, $gate, $uid) as $c) {
    if ((string)$c['state'] === 'active') { $myCaps[] = $c; }
}
$capId = intval($_GET['capacity_id'] ?? ($myCaps ? $myCaps[0]['id'] : 0));
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-m-01');
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($_GET['to'] ?? '')) ? $_GET['to'] : date('Y-m-t');
$ev = $capId > 0 ? EVS::find($gate, $capId, $from, $to) : null;

$page_title = 'إيكوبيشن | التقييم الثنائي';
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'التقييم الثنائي'; $header_icon = 'fa fa-user-check';
    $header_actions = array(array('href' => 'my_achievement.php', 'icon' => 'fa fa-chart-simple', 'label' => 'إنجازي'));
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>'; }
    ?>

    <div class="card"><div class="card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <strong>الصفة:</strong>
            <select name="capacity_id">
                <?php foreach ($myCaps as $c): ?>
                    <option value="<?php echo intval($c['id']); ?>" <?php echo intval($c['id']) === $capId ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(CAP::CAPACITY_AR[$c['capacity_type']] ?? $c['capacity_type']); ?></option>
                <?php endforeach; ?>
            </select>
            <label>من</label><input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
            <label>إلى</label><input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
            <button type="submit" class="btn-save">افتح الفترة</button>
        </form>
        <p style="color:#666;margin-top:8px">المعالج: <strong>ذاتيٌّ ← إقفالٌ ← مديرٌ ← مناقشةٌ ← اعتماد</strong> —
            والمديرُ <strong>لا يفتح قبل إقفال الذاتي</strong> (منعًا للتأثير)، وفارقُ درجتين فأكثرَ
            <strong>يوجب تعليقًا</strong>، والانتقالاتُ <strong>بفحص النسخة</strong>.</p>
    </div></div>

    <?php $state = $ev ? (string)$ev['state'] : 'جديد'; ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-diagram-project"></i>
        الحال: <span class="badge badge-secondary"><?php echo htmlspecialchars($state); ?></span>
        <?php if ($ev): ?> · نسخة <?php echo intval($ev['version']); ?><?php endif; ?></h5></div>
    <div class="card-body">

        <?php if (!$ev || $state === 'SelfDraft'): ?>
        <h6>① التقييمُ الذاتي</h6>
        <form method="post" class="ems-form">
            <input type="hidden" name="ev_action" value="self_save">
            <input type="hidden" name="capacity_id" value="<?php echo $capId; ?>">
            <input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>">
            <input type="hidden" name="to" value="<?php echo htmlspecialchars($to); ?>">
            <div class="form-grid">
                <?php $selfScores = $ev ? (json_decode((string)$ev['self_scores_json'], true) ?: array()) : array(); ?>
                <?php foreach ($AXES as $k => $lbl): ?>
                    <div class="form-group"><label><?php echo htmlspecialchars($lbl); ?> (1–5)</label>
                        <input type="number" step="0.5" min="1" max="5" name="score_<?php echo $k; ?>"
                               value="<?php echo htmlspecialchars((string)($selfScores[$k] ?? '')); ?>"></div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:10px;display:flex;gap:8px">
                <button type="submit" class="btn-save">احفظ الذاتي</button>
            </div>
        </form>
        <?php if ($ev): ?>
        <form method="post" style="margin-top:8px">
            <input type="hidden" name="ev_action" value="self_close">
            <input type="hidden" name="eval_id" value="<?php echo intval($ev['id']); ?>">
            <input type="hidden" name="version" value="<?php echo intval($ev['version']); ?>">
            <input type="hidden" name="capacity_id" value="<?php echo $capId; ?>">
            <input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>">
            <input type="hidden" name="to" value="<?php echo htmlspecialchars($to); ?>">
            <button type="submit" class="btn-save"><i class="fa fa-lock"></i> أقفل الذاتي (لا تعديلَ بعده)</button>
        </form>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($ev && in_array($state, array('SelfClosed', 'MgrDraft'), true)): ?>
        <h6>② تقييمُ المدير <small style="color:#888">(يرى الذاتيَّ الآن — بعد إقفاله)</small></h6>
        <p><small>الذاتي: <?php echo htmlspecialchars((string)$ev['self_scores_json']); ?></small></p>
        <form method="post" class="ems-form">
            <input type="hidden" name="ev_action" value="mgr_submit">
            <input type="hidden" name="eval_id" value="<?php echo intval($ev['id']); ?>">
            <input type="hidden" name="version" value="<?php echo intval($ev['version']); ?>">
            <input type="hidden" name="capacity_id" value="<?php echo $capId; ?>">
            <input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>">
            <input type="hidden" name="to" value="<?php echo htmlspecialchars($to); ?>">
            <div class="form-grid">
                <?php foreach ($AXES as $k => $lbl): ?>
                    <div class="form-group"><label><?php echo htmlspecialchars($lbl); ?> (1–5)</label>
                        <input type="number" step="0.5" min="1" max="5" name="score_<?php echo $k; ?>"></div>
                <?php endforeach; ?>
                <div class="form-group"><label>تعليقُ المدير
                    <span class="mnt-req-hint">(إلزاميٌّ عند فارقٍ ≥ درجتين)</span></label>
                    <input type="text" name="mgr_comment" maxlength="500"></div>
            </div>
            <div style="margin-top:10px"><button type="submit" class="btn-save">سجّل تقييمَ المدير</button></div>
        </form>
        <?php endif; ?>

        <?php if ($ev && $state === 'MgrDraft'): ?>
        <h6 style="margin-top:14px">③ المناقشة</h6>
        <form method="post" class="ems-form">
            <input type="hidden" name="ev_action" value="discuss">
            <input type="hidden" name="eval_id" value="<?php echo intval($ev['id']); ?>">
            <input type="hidden" name="version" value="<?php echo intval($ev['version']); ?>">
            <input type="hidden" name="capacity_id" value="<?php echo $capId; ?>">
            <input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>">
            <input type="hidden" name="to" value="<?php echo htmlspecialchars($to); ?>">
            <div class="form-group"><label>نقاطُ الاتفاق والاختلاف وخطةُ التطوير *</label>
                <textarea name="notes" rows="3" required></textarea></div>
            <div style="margin-top:10px"><button type="submit" class="btn-save">سجّل الجلسة</button></div>
        </form>
        <?php endif; ?>

        <?php if ($ev && $state === 'Discussed'): ?>
        <h6 style="margin-top:14px">④ الاعتماد <small style="color:#888">(لا اعتمادَ للذات)</small></h6>
        <form method="post" class="ems-form">
            <input type="hidden" name="ev_action" value="approve">
            <input type="hidden" name="eval_id" value="<?php echo intval($ev['id']); ?>">
            <input type="hidden" name="version" value="<?php echo intval($ev['version']); ?>">
            <input type="hidden" name="capacity_id" value="<?php echo $capId; ?>">
            <input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>">
            <input type="hidden" name="to" value="<?php echo htmlspecialchars($to); ?>">
            <div class="form-group"><label>الدرجةُ النهائية (بأوزان المحاور) *</label>
                <input type="number" step="0.01" min="0.01" max="5" name="final_score" required></div>
            <div style="margin-top:10px"><button type="submit" class="btn-save">اعتمِد</button></div>
        </form>
        <?php endif; ?>

        <?php if ($ev && $state === 'Approved'): ?>
        <div class="alert alert-success">اعتُمد بدرجة <strong><?php echo htmlspecialchars((string)$ev['final_score']); ?></strong>
            في <?php echo htmlspecialchars((string)$ev['approved_at']); ?> —
            <a href="my_certificate.php?eval_id=<?php echo intval($ev['id']); ?>">أصدر شهادةَ الإنجاز ▸</a></div>
        <?php endif; ?>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
