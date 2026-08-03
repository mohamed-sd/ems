<?php
/**
 * Portal/my_achievement.php — إنجازي بين تاريخين (H-18 · الشاشة 188)
 * ───────────────────────────────────────────────────────────────────────────
 * USR-01 §8-⑤: «فترةٌ حرةٌ (من/إلى + اختصارات) · المؤشراتُ السبعة ·
 * مقارنةٌ بالسابق» — و«المؤشرُ الذي لا يخصّ فئةً يُخفى بنصِّ لا ينطبق».
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Portal/AchievementService.php';
require_once __DIR__ . '/../app/Services/Portal/CapacityService.php';

use App\Services\Portal\AchievementService as ACH;
use App\Services\Portal\CapacityService as CAP;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid        = intval($_SESSION['user']['id'] ?? 0);
$is_super   = (strval($_SESSION['user']['role'] ?? '') === '-1');
if (!$is_super && $company_id <= 0) { header("Location: ../login.php"); exit(); }

$gate = $is_super ? ems_tenant_db()->forAllTenants('my achievement super') : ems_tenant_db();

$myCaps = array();
foreach (CAP::activeOf($conn, $gate, $uid) as $c) {
    if ((string)$c['state'] === 'active') { $myCaps[] = $c; }
}
$capId = intval($_GET['capacity_id'] ?? ($_SESSION['active_capacity']['id'] ?? 0));
if ($capId <= 0 && $myCaps) { $capId = intval($myCaps[0]['id']); }
$owns = false;
foreach ($myCaps as $c) { if (intval($c['id']) === $capId) { $owns = true; } }
if (!$owns) { $capId = $myCaps ? intval($myCaps[0]['id']) : 0; }

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-m-01');
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($_GET['to'] ?? '')) ? $_GET['to'] : date('Y-m-d');

$res = null; $prev = null;
if ($capId > 0) {
    $res = ACH::compute($conn, $gate, $company_id, $capId, $from, $to, true, $uid);
    // المقارنة: الفترةُ السابقةُ بالطول نفسِه
    $len = strtotime($to) - strtotime($from);
    $pf = date('Y-m-d', strtotime($from) - $len - 86400);
    $pt = date('Y-m-d', strtotime($from) - 86400);
    $prev = ACH::compute($conn, $gate, $company_id, $capId, $pf, $pt, false, $uid);
}

$LABELS = array('requests' => '① الطلبات', 'approvals' => '② الاعتمادات', 'time' => '③ الزمن (ساعات)',
    'sla' => '④ الالتزام بالمهل', 'production' => '⑤ الإنتاج', 'discipline' => '⑥ الانضباط',
    'quality' => '⑦ الجودة والسلامة');

$page_title = 'إيكوبيشن | إنجازي';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'إنجازي بين تاريخين'; $header_icon = 'fa fa-chart-simple';
    $header_actions = array(array('href' => 'my_certificate.php', 'icon' => 'fa fa-certificate', 'label' => 'شهادة الإنجاز'));
    include('../includes/page_header.php');
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
            <button type="submit" class="btn-save"><i class="fa fa-calculator"></i> قِس</button>
            <small style="color:#888">اختصارات:
                <a href="?capacity_id=<?php echo $capId; ?>&from=<?php echo date('Y-m-d'); ?>&to=<?php echo date('Y-m-d'); ?>">يوم</a> ·
                <a href="?capacity_id=<?php echo $capId; ?>&from=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&to=<?php echo date('Y-m-d'); ?>">أسبوع</a> ·
                <a href="?capacity_id=<?php echo $capId; ?>&from=<?php echo date('Y-m-01'); ?>&to=<?php echo date('Y-m-d'); ?>">شهر</a> ·
                <a href="?capacity_id=<?php echo $capId; ?>&from=<?php echo date('Y-01-01'); ?>&to=<?php echo date('Y-m-d'); ?>">سنة</a></small>
        </form>
    </div></div>

    <?php if ($res !== null && !$res['ok']): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($res['reason']); ?></div>
    <?php elseif ($res !== null): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-gauge-high"></i>
        المؤشراتُ السبعة — <?php echo htmlspecialchars($from . ' → ' . $to); ?>
        <small style="color:#888">(لُقطت ببصمتها #<?php echo intval($res['snap_id']); ?>)</small></h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>المؤشر</th><th>الفترة الحالية</th><th>الفترة السابقة (بالطول نفسه)</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              </tr></thead>
            <tbody>
            <?php foreach ($LABELS as $k => $lbl):
                $cur = $res['metrics'][$k] ?? array();
                $pv  = ($prev && $prev['ok']) ? ($prev['metrics'][$k] ?? array()) : array();
            ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($lbl); ?></strong></td>
                    <td><?php if (!empty($cur['not_applicable'])): ?>
                            <span class="badge badge-secondary" title="<?php echo htmlspecialchars((string)($cur['note'] ?? '')); ?>">
                                لا ينطبق على هذه الصفة</span>
                        <?php else: ?>
                            <small><?php echo htmlspecialchars(json_encode($cur, JSON_UNESCAPED_UNICODE)); ?></small>
                        <?php endif; ?></td>
                    <td><?php if (!empty($pv['not_applicable']) || !$pv): ?>
                            <span class="text-muted">—</span>
                        <?php else: ?>
                            <small><?php echo htmlspecialchars(json_encode($pv, JSON_UNESCAPED_UNICODE)); ?></small>
                        <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p style="color:#666;margin-top:8px">«المؤشرُ الذي لا يخصّ فئةً لا يُعرض لها صفرًا مضلِّلًا
        بل يُخفى بنصِّ <strong>لا ينطبق</strong> — فالمقارنةُ تبقى عادلة» (USR-01 §6).</p>
    </div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
