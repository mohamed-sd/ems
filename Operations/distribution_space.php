<?php
/**
 * Operations/distribution_space.php — مساحةُ التوزيع الموحّدة (M-28 · الشاشة 194)
 * ───────────────────────────────────────────────────────────────────────────
 * SPEC-03 بطاقة 5: «شبكةٌ: الصفُّ معدةٌ · العمودُ ورديةٌ · الخليةُ مشغّل —
 * **وفحصُ التعارض في الخادم لا في المتصفح وحده**» بحالاته الثلاث (كان
 * لحالةٍ واحدة في move_oprators.php:396) — لا أثرَ مالي: تخطيطٌ يسبق التنفيذ.
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

$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($_GET['date'] ?? '')) ? $_GET['date'] : date('Y-m-d');
$grid = OBS::distributionGrid($conn, $company_id, $date);
$conflicts = OBS::conflicts($conn, $company_id, $date);

$page_title = 'إيكوبيشن | مساحة التوزيع';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'مساحة التوزيع البصرية'; $header_icon = 'fa fa-table-cells';
    $header_actions = array(array('href' => 'operations_room.php', 'icon' => 'fa fa-tower-control', 'label' => 'غرفة العمليات'));
    include('../includes/page_header.php');
    ems_screen_about('شبكةُ (معدة × وردية × مشغّل) ليومٍ واحد — بديلُ الشاشتين المنفصلتين. '
        . 'التعارضُ يُفحص في الخادم بحالاته الثلاث: مشغّلٌ في معدتين · معدةٌ بلا مشغّل · '
        . 'رخصةٌ منتهية — ويظهر قبل أي اعتماد. لا أثرَ ماليًّا: تخطيطٌ يسبق التنفيذ.',
        array('اختر اليوم', 'اقرأ شاراتِ التعارض الحمراء', 'عالج التوزيعَ من شاشة الإدخال'));
    ?>

    <div class="card"><div class="card-body">
        <form method="get" style="display:flex;gap:8px;align-items:center">
            <label>اليوم</label><input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>">
            <button type="submit" class="btn-save">اعرض الشبكة</button>
        </form>
    </div></div>

    <?php if ($conflicts): ?>
    <div class="card" style="border-inline-start:4px solid #c00"><div class="card-header"><h5>
        <i class="fa fa-triangle-exclamation" style="color:#c00"></i>
        تعارضاتُ اليوم (<?php echo count($conflicts); ?>) — **فحصٌ خادميٌّ شامل**</h5></div>
    <div class="card-body">
        <?php foreach ($conflicts as $c): ?>
            <div class="alert alert-danger" style="margin:6px 0">
                <span class="badge badge-danger"><?php echo htmlspecialchars($c['kind']); ?></span>
                <?php echo htmlspecialchars($c['message']); ?></div>
        <?php endforeach; ?>
    </div></div>
    <?php else: ?>
    <div class="alert alert-success">صفرُ تعارضٍ في توزيع هذا اليوم ✅</div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-table-cells"></i>
        الشبكة — <?php echo count($grid); ?> معدةً عاملةً في <?php echo htmlspecialchars($date); ?></h5></div>
    <div class="card-body">
        <?php if (!$grid): ems_state_empty('لا توزيعَ لهذا اليوم', 'افتح إدخال الوحدات', 'units.php'); else: ?>
        <div class="table-container"><table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>المعدة ↓ / الوردية ←</th><th>☀ نهارية</th><th>🌙 ليلية</th></tr></thead>
            <tbody>
            <?php foreach ($grid as $eqId => $row): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['equipment']); ?></strong></td>
                    <?php foreach (array('day', 'night') as $sh): $cell = $row[$sh]; ?>
                    <td><?php if ($cell === null): ?>
                            <span class="text-muted">—</span>
                        <?php elseif ($cell['operator_id'] === null): ?>
                            <span class="badge badge-danger">⚠ بلا مشغّل</span>
                        <?php else: ?>
                            <?php echo htmlspecialchars($cell['operator']); ?>
                            <small class="badge badge-secondary"><?php echo htmlspecialchars($cell['state']); ?></small>
                        <?php endif; ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
