<?php
/**
 * Portal/ceo_approvals.php — اعتمادات المدير التنفيذي (CMP-03 ⑥ — بُنيت بتصميم SCR-DES حرفيًّا)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 00 · الإدارة التنفيذية · الأعمدة 20 بترتيب المستند وطبقة
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

$page_title = 'إيكوبيشن | اعتمادات المدير التنفيذي';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'اعتمادات المدير التنفيذي';
    $header_icon = 'fa fa-stamp';
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
        <table class="alltables display" id="ceo_approvalsTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>رقم الطلب</th>
            <th>تاريخ الورود</th>
            <th>نوع المستند</th>
            <th>المستند</th>
            <th>الإدارة الطالبة</th>
            <th>سبب الرفع للأعلى</th>
            <th>القيمة</th>
            <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
            <th>سقف الإدارة</th>
            <th>التجاوز</th>
            <th>المعتمِدون قبلي</th>
            <th>المهلة</th>
            <th>قراري</th>
            <th>سبب القرار</th>
            <th>تاريخ القرار</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
                <tr><td colspan="20" class="text-center text-muted">لا بياناتَ بعدُ — تُعرض حين يُربط مصدرُها</td></tr>
            </tbody>
        </table>
        </div>
    </div></div>
</div>
