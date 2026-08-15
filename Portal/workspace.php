<?php
/**
 * Portal/workspace.php — مساحةُ العمل السياقية (H-19 · الشاشة 191)
 * ───────────────────────────────────────────────────────────────────────────
 * WSP-01 §1: «لوحةُ العمل هي الصفحةُ الأولى» — الوسطُ المساحةُ · الأيسرُ
 * جناحُ الفريق · الأيمنُ بوابةُ USR-01 (بابُها في الشريط) · «البنيةُ واحدةٌ
 * في كل المساحات — وما يختلف هو المحتوى لا الشكل».
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';

/* ══ INJ-0585 · «مساحةُ العمل» صارت شاشةً واحدة ═════════════════════════════
     كان في النظامِ شاشتانِ لغرضٍ واحد:
       · `main/my_workspace.php` (مودول 228) — **٣٢ صفَّ تنقّلٍ** تشير إليها،
         وهي ما يصل إليه المستخدمُ فعلًا.
       · `Portal/workspace.php` (مودول 191) — **صفرُ صفِّ تنقّل**: لا يبلغها
         أحدٌ من القائمة، ولا تُفتح إلا برابطٍ محفوظٍ أو مرجعٍ في تقرير.

     **القرار: تبقى `main/my_workspace.php`** — لأنها الموصولةُ بالقائمةِ لكلِّ
     الأدوار، وإبقاءُ المهجورةِ يعني شاشتين تتباعدان بلا أن يلاحظ أحد.

     ◆ **ولا تُحذف**: التحويلُ يُبقي الروابطَ المحفوظةَ والمراجعَ في التقاريرِ
       عاملةً — والحذفُ الفوريُّ يكسرها.
     ◆ وكلُّ فتحةٍ تُسجَّل بمُحيلِها، ليُعرف من ما زال يستعمل الرابطَ القديم. */
require_once __DIR__ . '/../includes/audit_trail.php';
ems_audit_change($conn, 'portal', 'route_redirect', 'legacy_hit', 191,
    array(),
    array('from' => 'Portal/workspace.php', 'to' => 'main/my_workspace.php',
          'referer' => mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 180)),
    array('company_id' => intval($_SESSION['user']['company_id'] ?? 0),
          'user_id' => intval($_SESSION['user']['id'] ?? 0)));
header('Location: ../main/my_workspace.php');
exit();

require_once __DIR__ . '/../app/Services/Portal/WorkspaceFeedService.php';

use App\Services\Portal\WorkspaceFeedService as WFS;
use App\Services\Portal\CapacityService as CAP;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid        = intval($_SESSION['user']['id'] ?? 0);
$is_super   = (strval($_SESSION['user']['role'] ?? '') === '-1');
if (!$is_super && $company_id <= 0) { header("Location: ../login.php"); exit(); }

$gate = $is_super ? ems_tenant_db()->forAllTenants('workspace super') : ems_tenant_db();

// لوحةُ الدخول بحسب الحساب (§5) — من الصفة الفعّالة
$myCaps = CAP::activeOf($conn, $gate, $uid);
$activeCap = null;
$activeCapId = intval($_SESSION['active_capacity']['id'] ?? 0);
foreach ($myCaps as $c) {
    if ((string)$c['state'] === 'active'
        && ($activeCap === null || intval($c['id']) === $activeCapId)) { $activeCap = $c; }
}
$entry = $activeCap ? WFS::entryFor($activeCap) : array('entity_type' => 'person', 'entity_id' => $uid);

$etype = strval($_GET['type'] ?? $entry['entity_type']);
$eid   = isset($_GET['id']) ? intval($_GET['id']) : intval($entry['entity_id']);
$period = in_array(strval($_GET['period'] ?? 'today'), array('today', 'week', 'month'), true)
        ? strval($_GET['period'] ?? 'today') : 'today';

// LayerSwitched — حين يأتي من طبقةٍ سابقة (W2)
if (isset($_GET['from'])) {
    WFS::logSwitch($conn, $company_id, $uid, strval($_GET['from']), $etype . ':' . $eid, strval($eid));
}

$feed = WFS::feed($conn, $gate, $company_id, $uid, $etype, $eid, $period);
$layers = WFS::allowedLayers($conn, $gate, $company_id, $uid);
$team = WFS::teamWing($conn, $gate, $company_id, $uid);

$page_title = 'إيكوبيشن | مساحة العمل';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'مساحة العمل'; $header_icon = 'fa fa-table-columns';
    $header_actions = array(array('href' => 'my_portal.php', 'icon' => 'fa fa-id-card', 'label' => 'بوابتي (ماذا يخصّني؟)'));
    include('../includes/page_header.php');
    ?>

    <div class="card"><div class="card-body" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <strong>فتاتُ الطريق:</strong>
        <?php if ($feed['ok']): foreach ($feed['breadcrumb'] as $i => $b): ?>
            <?php if ($i > 0): ?><span>←</span><?php endif; ?>
            <a href="?type=<?php echo htmlspecialchars($b['entity_type']); ?>&id=<?php echo intval($b['entity_id']); ?>&from=<?php echo htmlspecialchars($etype . ':' . $eid); ?>">
                <?php echo htmlspecialchars($b['label']); ?></a>
        <?php endforeach; endif; ?>
        <span style="margin-inline-start:auto"><strong>مبدّلُ الطبقات:</strong>
        <?php foreach ($layers as $l): ?>
            <a class="btn btn-sm" style="border:1px solid #ddd;border-radius:6px;padding:2px 8px;margin:0 2px"
               href="?type=<?php echo htmlspecialchars($l['entity_type']); ?>&id=<?php echo intval($l['entity_id']); ?>&from=<?php echo htmlspecialchars($etype . ':' . $eid); ?>">
                <?php echo htmlspecialchars($l['label']); ?></a>
        <?php endforeach; ?></span>
        <span><strong>الفترة:</strong>
            <?php foreach (array('today' => 'اليوم', 'week' => 'أسبوع', 'month' => 'شهر') as $p => $lbl): ?>
                <a href="?type=<?php echo htmlspecialchars($etype); ?>&id=<?php echo $eid; ?>&period=<?php echo $p; ?>"
                   <?php echo $p === $period ? 'style="font-weight:800"' : ''; ?>><?php echo $lbl; ?></a>
            <?php endforeach; ?></span>
    </div></div>

    <div style="display:grid;grid-template-columns:260px 1fr;gap:14px">
        <div class="card"><div class="card-header"><h5><i class="fa fa-users"></i> جناحُ الفريق</h5></div>
        <div class="card-body">
            <?php if (!$team): ?>
                <p class="text-muted">لا أعضاءَ في نطاقك المباشر</p>
            <?php endif; ?>
            <?php foreach ($team as $t): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px dashed #eee">
                    <a href="?type=person&id=<?php echo intval($t['account_id']); ?>&from=<?php echo htmlspecialchars($etype . ':' . $eid); ?>">
                        <?php echo (string)$t['status'] === 'active' ? '●' : '○'; ?>
                        <?php echo htmlspecialchars($t['name']); ?></a>
                    <small style="color:#999"><?php echo htmlspecialchars($t['last_activity']); ?></small>
                </div>
            <?php endforeach; ?>
            <p style="color:#888;margin-top:8px"><small>اختيارُ عضوٍ يحوّل المساحةَ إلى مساحته —
                بحسب صلاحيتك (حارس USR-01 §4)</small></p>
        </div></div>

        <div>
        <?php if (!$feed['ok']): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($feed['reason']); ?></div>
        <?php else: ?>
            <div class="card"><div class="card-header"><h5><i class="fa fa-cube"></i>
                مساحةُ <?php echo htmlspecialchars($feed['entity']['type']); ?>
                <?php if (intval($feed['entity']['id']) > 0): ?>#<?php echo intval($feed['entity']['id']); ?><?php endif; ?>
                — <?php echo htmlspecialchars($period); ?></h5></div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
                <?php foreach ($feed['cards'] as $c): ?>
                    <div style="border:1px solid #e5e0d5;border-radius:10px;padding:12px;background:#fffdf7">
                        <div style="color:#888;font-size:.85rem"><?php echo htmlspecialchars($c['title']); ?>
                            <?php if ($c['live']): ?><span title="حيٌّ بلا كاش">⚡</span><?php endif; ?></div>
                        <?php if ($c['unavailable'] !== null): ?>
                            <div style="color:#a15c00;margin:6px 0"><small>
                                <?php echo htmlspecialchars($c['unavailable']); ?></small></div>
                        <?php else: ?>
                            <div style="font-size:1.1rem;font-weight:800;margin:6px 0">
                                <?php echo htmlspecialchars((string)$c['value']); ?></div>
                        <?php endif; ?>
                        <?php if ($c['source_link'] !== null): ?>
                            <a href="../<?php echo htmlspecialchars($c['source_link']); ?>">التعمق ▸</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>

                <?php if ($feed['decisions']): ?>
                <h6 style="margin-top:14px"><i class="fa fa-gavel"></i> ما يحتاج قرارًا
                    <small style="color:#888">(القرارُ في صندوق مالكه لا هنا)</small></h6>
                <?php foreach ($feed['decisions'] as $d): ?>
                    <a class="btn-primary" href="../<?php echo htmlspecialchars($d['link']); ?>">
                        <?php echo htmlspecialchars($d['box']); ?> — <?php echo intval($d['count']); ?></a>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($feed['pulse']): ?>
                <h6 style="margin-top:14px"><i class="fa fa-heart-pulse"></i> نبضُ الأحداث</h6>
                <ul><?php foreach ($feed['pulse'] as $p): ?>
                    <li><small><?php echo htmlspecialchars($p); ?></small></li>
                <?php endforeach; ?></ul>
                <?php endif; ?>
            </div></div>
        <?php endif; ?>
        </div>
    </div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
