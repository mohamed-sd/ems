<?php
/**
 * Governance/break_glass.php — صلاحية الطوارئ اللحظية (كسر الزجاج) (CMP-03 ⑥ — بُنيت بتصميم SCR-DES حرفيًّا)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 15 · الحوكمة والالتزام · الأعمدة 18 بترتيب المستند وطبقة
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

$page_title = 'إيكوبيشن | صلاحية الطوارئ اللحظية (كسر الزجاج)';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'صلاحية الطوارئ اللحظية (كسر الزجاج)';
    $header_icon = 'fa fa-hammer';
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
        <table class="alltables display" id="break_glassTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>رقم الطلب</th>
            <th>التاريخ والوقت</th>
            <th>الطالب — الاسم والصفة</th>
            <th>الصلاحية المطلوبة</th>
            <th>الشاشة أو الفعل</th>
            <th>سبب الطوارئ</th>
            <th>الأثر المتوقع لو لم تُمنح</th>
            <th>الموافق الأول</th>
            <th>الموافق الثاني</th>
            <th>وقت المنح</th>
            <th>مدة الصلاحية</th>
            <th>وقت الانتهاء</th>
            <th>عدد الأفعال المنفَّذة تحتها</th>
            <th>تقرير المراجعة</th>
            <th>تاريخ المراجعة</th>
            <th>نتيجة المراجعة</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
                <tr><td colspan="18" class="text-center text-muted">لا بياناتَ بعدُ — تُعرض حين يُربط مصدرُها</td></tr>
            </tbody>
        </table>
        </div>
    </div></div>
</div>
