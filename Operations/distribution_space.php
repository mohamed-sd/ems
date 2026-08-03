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
            <thead><tr><th>الوردية</th><th>☀ نهارية</th><th>🌙 ليلية</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
              <th class="ems-gov-th" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
              <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              </tr></thead>
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
