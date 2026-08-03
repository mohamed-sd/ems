<?php
/**
 * Operations/production.php — الإنتاج والقياس (CMP-03 ⑥ — بُنيت بتصميم SCR-DES حرفيًّا)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 01 · إدارة الموقع · الأعمدة 28 بترتيب المستند وطبقة
 * الحوكمة بشرائحها. مصدرُ البيانات يُربط بمهمة لحاقٍ مسجلةٍ في
 * docs/CMP03_FOLLOWUP_SOURCES_ar.md — والفائض فوق 22 عمودًا منهارٌ
 * لسطرٍ تابعٍ أو زرِّ «الأعمدة المطوية» (توصيتا المالك ① و③).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once '../includes/permissions_helper.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=غير+مصرح");
    exit();
}

$page_title = 'إيكوبيشن | الإنتاج والقياس';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'الإنتاج والقياس';
    $header_icon = 'fa fa-weight-scale';
    $header_actions = array();
    $header_back = false;
    include '../includes/page_header.php';
    ?>

    <div class="alert alert-info" style="display:flex;gap:10px;align-items:center">
        <i class="fa fa-plug"></i>
        <span>شاشةٌ وليدةٌ ببنية تصميم SCR-DES الكاملة — مصدرُ بياناتها يُربط بمهمة لحاق (CMP-03 ⑥).</span>
    </div>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="productionTable">
            <thead><tr>
            <th>رقم السجل</th>
            <th>التاريخ</th>
            <th>الموقع</th>
            <th>العقد</th>
            <th>بند العقد</th>
            <th>نوع الإنتاج</th>
            <th>جبهة العمل</th>
            <th>نقطة التفريغ</th>
            <th>طريقة القياس</th>
            <th>الكمية حسب سجلنا</th>
            <th>وحدة القياس</th>
            <th>الكمية المعتمدة من العميل</th>
            <th>الفرق</th>
            <th>سبب الفرق</th>
            <th class="ems-gov-th" data-gov="attached_doc" data-slice="3" title="مستند الإثبات المرفق">المستند المرفق</th>
            <th>المُدخِل</th>
            <th>معتمد العميل</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
            <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
            <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
            <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
            </tr></thead>
            <tbody>
                <tr><td colspan="28" class="text-center text-muted">لا بياناتَ بعدُ — تُعرض حين يُربط مصدرُها</td></tr>
            </tbody>
        </table>
        </div>
    </div></div>
</div>
