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
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<style>
/* UXW-01: أنماطُ الشاشةِ في كتلةٍ واحدة — لا نمطَ موضعيًّا ولا لونَ خارجَ الرموز */
.dsp-filter { display: flex; gap: 8px; align-items: center; }
.dsp-conflict-card { border-inline-start: 4px solid var(--c-state-danger-strong); }
.dsp-conflict-icon { color: var(--c-state-danger-strong); }
.dsp-conflict-item { margin: 6px 0; }
.dsp-table-full { width: 100%; }
</style>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'مساحة التوزيع البصرية'; $header_icon = 'fa fa-table-cells';
    $header_actions = array(array('href' => 'operations_room.php', 'icon' => 'fa fa-tower-control', 'label' => 'غرفة العمليات'));
    include('../includes/page_header.php');
    ems_screen_about('شبكةُ (معدة × وردية × مشغّل) ليومٍ واحد — بديلُ الشاشتين المنفصلتين. '
        . 'التعارضُ يُفحص في الخادم بحالاته الثلاث: مشغّلٌ في معدتين · معدةٌ بلا مشغّل · '
        . 'رخصةٌ منتهية — ويظهر قبل أي اعتماد. لا أثرَ ماليًّا: تخطيطٌ يسبق التنفيذ.',
        array('اختر اليوم', 'اقرأ شاراتِ التعارض الحمراء', 'عالج التوزيعَ من شاشة الإدخال'));
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضيًا
    echo ems_states_bundle('لا توزيعَ معداتٍ على ورديّاتِ هذا اليوم', 'اختر يومًا آخرَ أو افتحْ إدخالَ الوحداتِ لتكليفِ المشغّلين');
    ?>

    <div class="card"><div class="card-body">
        <form method="get" class="dsp-filter">
            <label for="emsf_351_e5768">اليوم</label><input type="date" name="date" id="emsf_351_e5768" value="<?php echo htmlspecialchars($date); ?>">
            <button type="submit" class="btn-primary">اعرض الشبكة</button>
        </form>
    </div></div>

    <?php if ($conflicts): ?>
    <div class="card dsp-conflict-card"><div class="card-header"><h5>
        <i class="fa fa-triangle-exclamation dsp-conflict-icon"></i>
        تعارضاتُ اليوم (<?php echo count($conflicts); ?>) — **فحصٌ خادميٌّ شامل**</h5></div>
    <div class="card-body">
        <?php foreach ($conflicts as $c): ?>
            <div class="alert alert-danger dsp-conflict-item">
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
        <div class="table-container"><table class="alltables display nowrap dsp-table-full" data-no-dt="1">
            <thead><tr><th>الوردية</th><th>☀ نهارية</th><th>🌙 ليلية</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم التكليف</th>
              <th class="ems-fn-th" data-fn="1">كود المشغّل</th>
              <th class="ems-fn-th" data-fn="1">كود المعدة</th>
              <th class="ems-fn-th" data-fn="1">الوحدة التعاقدية</th>
              <th class="ems-fn-th" data-fn="1">من الموقع</th>
              <th class="ems-fn-th" data-fn="1">التاريخ</th>
              <th class="ems-fn-th" data-fn="1">تاريخ السريان</th>
              <th class="ems-fn-th" data-fn="1">عدد الأيام</th>
              <th class="ems-fn-th" data-fn="1">سبب الإنهاء</th>
              <th class="ems-fn-th" data-fn="1">المشغّل البديل</th>
              <th class="ems-fn-th" data-fn="1">فحص التأهيل</th>
              <th class="ems-fn-th" data-fn="1">فحص الرخصة</th>
              <th class="ems-fn-th" data-fn="1">كلّفه</th>
              <th class="ems-fn-th" data-fn="1">وافق عليه</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم الأمر</th>
              <th class="ems-fn-th" data-fn="1">إلى الموقع</th>
              <th class="ems-fn-th" data-fn="1">نوع المورد</th>
              <th class="ems-fn-th" data-fn="1">كود المورد</th>
              <th class="ems-fn-th" data-fn="1">اسم المورد</th>
              <th class="ems-fn-th none" data-fn="1">سبب النقل</th>
              <th class="ems-fn-th none" data-fn="1">أمر الترحيل المرتبط</th>
              <th class="ems-fn-th none" data-fn="1">موافقة القوى</th>
              <th class="ems-fn-th none" data-fn="1">موافقة الموقع المصدر</th>
              <th class="ems-fn-th none" data-fn="1">أصدره</th>
              <th class="ems-fn-th none" data-fn="1">تاريخ التنفيذ</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
              <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
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
