<?php
/**
 * Suppliers/supplier_contacts.php — SUP-02 · جهاتُ الاتصالِ والمفوَّضون
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تبويبٌ في ملفِّ الموردِ لا شاشةٌ مستقلة** — نصُّ المتطلبِ حرفًا، ومعيارُه
 *   «**صفر بندِ تنقّلٍ لجهات الاتصال**». فلا صفَّ لها في `nav_items`، وتُبلَغ
 *   من شريطِ ملفِّ الموردِ وحدَه.
 * ◆ **والتفويضُ حقلُ حجيةٍ بمداه لا شاشة** — نصُّ المتطلب: القيدُ في القاعدةِ
 *   يرفض موقِّعًا بلا صفةٍ ومدًى ومستند.
 * ◆ **والمنطقُ والتصييرُ في عُدَّةٍ مشتركةٍ مع سطحِ العميل** — الحقولُ واحدةٌ
 *   حرفًا، ونسختانِ منها تتفرّقانِ بأوَّلِ تعديل.
 * ═══════════════════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }

include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/party_contacts_kit.php';
require_once __DIR__ . '/../includes/party_contacts_view.php';
require_once __DIR__ . '/../includes/entity_tabs.php';
echo ems_entity_tabs('supplier', 'جهات الاتصال والمفوضون');

/* حارسُ الشاشةِ **فوقَ** المعالجِ الذي يكتب — لا بعدَه */
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}

$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'الحساب غير مرتبط بشركة.', 'GOV-INFO-200', '');
    exit();
}

$SID = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;
if ($SID <= 0 && isset($_GET['id'])) { $SID = intval($_GET['id']); }

/* ◆ **والطرفُ يُقرأ من القاعدةِ لا يُصدَّق من الرابط**: معرِّفٌ لموردٍ خارجَ
     نطاقِ الشركةِ يعود لا شيءَ — والبوابةُ تحقن العزلَ في القراءة. */
$gate     = ems_tenant_db();
$supplier = $SID > 0 ? $gate->selectOne('suppliers', array('where' => array('id' => $SID))) : null;

/* ◆ **والكتابةُ صلاحيةٌ مستقلةٌ عن القراءة**: مَن يقرأ الملفَّ الأمَّ يرى
     التبويبَ، ومَن يملك الإدارةَ وحدَه يكتب فيه. و`$canEdit` يُقرأ من
     السجلِّ لا يُفترَض — وإلا صار كلُّ قارئٍ كاتبًا صامتًا. */
$pcPerm  = function_exists('check_page_permissions')
    ? check_page_permissions($conn, 'Suppliers/supplier_contacts.php') : array();
$canEdit = !empty($pcPerm['can_add']) || !empty($pcPerm['can_edit']);

/* المعالجُ قبلَ أيِّ إخراج */
if ($supplier !== null) {
    ems_pc_handle($conn, 'supplier', $SID, 'supplier_contacts.php?supplier_id=' . $SID);
}

$page_title = 'إيكوبيشن | جهات اتصال المورد';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title   = 'جهات الاتصال والمفوضون';
    $header_icon    = 'fa fa-address-book';
    $header_actions = array();
    $header_back    = array('href' => 'suppliers.php', 'class' => '', 'label' => 'سجل الموردين');
    include __DIR__ . '/../includes/page_header.php'; ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> جهات الاتصال والمفوضون بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'رقم الجهة' => 'c30',
            'رقم المورد' => 'c31',
            'اسم المورد (بحث)' => 'c32',
            'الاسم' => 'c33',
            'الصفة/الدور' => 'c34',
            'نوع التفويض' => 'c35',
            'مستند التفويض' => 'c36',
            'سريان التفويض من' => 'c37',
            'إلى' => 'c38',
            'الهاتف الأساسي' => 'c39',
            'هاتف بديل' => 'c40',
            'البريد' => 'c41',
            'حالة جهة الاتصال' => 'c42',
            'الحجية' => 'c43',
            'حالة البيانات' => 'c44',
            'ملاحظات' => 'c45',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('sup_contact_delegate');
        echo ems_w14_grid('emsList_sup_contacts', $GUIDE_COLS, $__gridRows, $D, 'لا جهة اتصال مسجلة بعد'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }

    if ($supplier === null) {
        /* ◆ **ولا يُصيَّر نموذجٌ بلا طرف**: صفٌّ بلا موردٍ مرجعيٍّ يُكتَب ثم لا
             يُعرَف صاحبُه — فيُمنع الإدخالُ ويُشرَح السبب. */
        echo '<div class="card"><div class="card-body"><p class="pc-note">'
           . '<strong>افتح هذا التبويب من ملف مورد بعينه</strong> — '
           . 'جهة الاتصال تابعة لمورد، ولا تسجل بلا طرف مرجعي.'
           . ' <a href="suppliers.php">سجل الموردين</a></p></div></div>';
    } else {
        $label = (string) (isset($supplier['name']) && $supplier['name'] !== ''
            ? $supplier['name'] : ('#' . $SID));
        echo ems_pc_render(ems_pc_rows($conn, 'supplier', $SID), $label, $canEdit);
    }
    ?>
</div>
<?php include '../infooter.php'; ?>
