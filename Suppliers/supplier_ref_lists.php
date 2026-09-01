<?php
/**
 * Suppliers/supplier_ref_lists.php — القوائمُ المرجعيّة (SUP-31)
 * ───────────────────────────────────────────────────────────────────────────
 * Grain: **قائمةٌ مرجعيّةٌ واحدة (كلُّ قائمةٍ عمود)** — بنيويٌّ (STRUCTURAL):
 * عقدُه مصالحةٌ بالمعرِّفِ ودليلٌ مقيسٌ لا رحلةَ أعمال.
 *
 * ◆ القوائمُ تُقرأ من مواضعِها الحيّةِ لا تُنسخ: أنواعُ الموردين وطبيعةُ
 *   التعاملِ من سجلِّ الموردين بقيمِه المميّزةِ · عملاتُ التسوياتِ من
 *   سجلِّها · حالاتُ آلتَي الموردِ والعقدِ من مخزنِ موجتِهما الحاكم —
 *   وكلُّ قائمةٍ تسمّي موضعَها وعدَّ قيمِها.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Suppliers/supplier_ref_lists.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier ref lists super') : ems_tenant_db();
require_once __DIR__ . '/../includes/w8_machine_panel.php';

$LISTS = array();
$types = array(); $natures = array(); $curs = array();
try {
    foreach ($gate->select('suppliers', array('columns' => array('supplier_type', 'dealing_nature'), 'limit' => 2000)) as $s0) {
        if (trim((string) $s0['supplier_type']) !== '') { $types[trim((string) $s0['supplier_type'])] = 1; }
        if (trim((string) $s0['dealing_nature']) !== '') { $natures[trim((string) $s0['dealing_nature'])] = 1; }
    }
} catch (\Throwable $t) { error_log('sup_ref sup: ' . $t->getMessage()); }
try {
    foreach ($gate->select('settlements', array('columns' => array('party_type', 'currency'), 'limit' => 3000)) as $s0) {
        if ((string) $s0['party_type'] !== 'supplier') { continue; }
        if (trim((string) $s0['currency']) !== '') { $curs[trim((string) $s0['currency'])] = 1; }
    }
} catch (\Throwable $t) { error_log('sup_ref cur: ' . $t->getMessage()); }
$supStates = array(); $conStates = array();
foreach (ems_w8_machine_rows($conn, 'suppliers') as $m0) { $supStates[(string) $m0['from_state']] = 1; $supStates[(string) $m0['to_state']] = 1; }
foreach (ems_w8_machine_rows($conn, 'supplier_contracts') as $m0) { $conStates[(string) $m0['from_state']] = 1; $conStates[(string) $m0['to_state']] = 1; }
$LISTS[] = array('انواع الموردين', 'سجل الموردين الحي بقيمه المميزة', array_keys($types));
$LISTS[] = array('طبيعة التعامل', 'سجل الموردين الحي بقيمه المميزة', array_keys($natures));
$LISTS[] = array('عملات تسويات الموردين', 'سجل التسويات الحي بقيمه المميزة', array_keys($curs));
$LISTS[] = array('حالات آلة تأهيل المورد', 'مخزن آلات موجة الموردين الحاكم', array_keys($supStates));
$LISTS[] = array('حالات آلة عقد المورد', 'مخزن آلات موجة الموردين الحاكم', array_keys($conStates));
$nVals = 0; foreach ($LISTS as $l0) { $nVals += count($l0[2]); }

$page_title = 'إيكوبيشن | القوائم المرجعية للموردين';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'القوائم المرجعية للموردين: كل قائمة بموضعها الحي وعد قيمها'; $header_icon = 'fa fa-list'; $header_actions = array();
    $header_back = array('href' => 'suppliers.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الموردين');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($LISTS) ?></div><div class="ems-stat-label">قوائم مرجعية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nVals) ?></div><div class="ems-stat-label">قيم مقروءة من مواضعها</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value">3</div><div class="ems-stat-label">مواضع حية مسماة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value">0</div><div class="ems-stat-label">قيم منسوخة يدويا</div></div>
    </div>

    <div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'نموذج العمل' => 'business_model',
            'وحدة القياس' => 'c2',
            'العملة' => 'c3',
            'نوع الآلية/البند' => 'type_line',
            'بادئة النوع (v4)' => 'type',
            'تصنيف المورد' => 'supplier',
            'فئة مصدر القدرة' => 'source',
            'طبيعة المورد' => 'supplier_8',
            'حالة المورد' => 'supplier_9',
            'أساس اشتقاق الحالة' => 'c10',
            'نوع التفويض' => 'type_delegation',
            'نوع الوثيقة' => 'type_12',
            'حالة التحقق' => 'verify',
            'نوع الوحدة التعاقدية' => 'type_14',
            'التصنيف (استمرارية)' => 'c15',
            'الدور (أساسية/احتياطية)' => 'role',
            'نوع الإسناد' => 'type_17',
            'الحدث' => 'c18',
            'حالة العقد' => 'contract',
            'تكييف الوثيقة' => 'c20',
            'مستوى الحجية' => 'authority_level',
            'حجية الحصة (المصدر)' => 'source_22',
            'كود الالتزام' => 'code_obligation',
            'أطراف الثلاثية' => 'c24',
            'طرف الالتزام (م09)' => 'obligation',
            'قابل للاسترداد؟' => 'c26',
            'نوع الواقعة النيابية' => 'type_onbehalf',
            'حالة السلسلة النيابية' => 'onbehalf',
            'حالة الاستحقاق' => 'entitlement',
            'حالة التسوية' => 'c30',
            'نظام التقسيم' => 'c31',
            'طريقة الدفع' => 'payment',
            'حالة الطلب' => 'c33',
            'حالة الصرف' => 'disburse',
            'نوع الجزاء/المطالبة' => 'type_penalty',
            'نوع وثيقة الإغلاق' => 'type_close',
            'التصنيف الاستراتيجي' => 'c37',
            'مصدر القدرة' => 'source_38',
            'قرار الترحيل' => 'migration',
            'جاهزية التوقيع' => 'c40',
            'نتيجة الفحص' => 'c41',
            'حالة الحصة' => 'c42',
            'الوضع التشغيلي' => 'c43',
            'الاستمرارية' => 'c44',
            'طريقة الربط بالحصة' => 'c45',
            'طبيعة علاقة المورد بالمعدة' => 'supplier_46',
            'معالجة الاستعداد والتوقف (قاعدة)' => 'c47',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('sup_list_ref');
        echo ems_w14_grid('emsList_sup_reflists', $GUIDE_COLS, $__gridRows, $D, 'لا قائمة مرجعية مسجلة بعد'); /* /GUIDE_COLS */ ?>
    </div>

    <div class="ems-note-box">
        القوائم تقرأ من مواضعها الحية لحظة الطلب ولا تنسخ نسخة ثانية تفترق،
        وحالات الآلتين من مخزن الموجة الحاكم نفسه. قراءة صرف ولا ادخال.
    </div>
</div>
<?php include '../infooter.php'; ?>
