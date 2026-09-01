<?php
/**
 * Clients/client_contacts.php — SAL-02 · جهاتُ اتصالِ العميل
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تبويبٌ في ملفِّ العميلِ لا شاشةٌ مستقلة** — نصُّ المتطلبِ حرفًا، ومعيارُه
 *   «**لا بندَ تنقّلٍ لجهات الاتصال**». فلا صفَّ لها في `nav_items`، وتُبلَغ
 *   من شريطِ ملفِّ العميلِ وحدَه.
 * ◆ **والمنطقُ والتصييرُ في عُدَّةٍ مشتركةٍ مع سطحِ المورد** — الحقولُ واحدةٌ
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
echo ems_entity_tabs('client', 'جهات الاتصال');

/* حارسُ الشاشةِ **فوقَ** المعالجِ الذي يكتب — لا بعدَه */
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}

$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'الحساب غير مرتبط بشركة.', 'GOV-INFO-200', '');
    exit();
}

$CID = isset($_GET['client']) ? intval($_GET['client']) : 0;
if ($CID <= 0 && isset($_GET['id'])) { $CID = intval($_GET['id']); }

/* ◆ **والطرفُ يُقرأ من القاعدةِ لا يُصدَّق من الرابط**: معرِّفٌ لعميلٍ خارجَ
     نطاقِ الشركةِ يعود لا شيءَ — والبوابةُ تحقن العزلَ في القراءة. */
$gate   = ems_tenant_db();
$client = $CID > 0 ? $gate->selectOne('clients', array('where' => array('id' => $CID))) : null;

/* ◆ **والكتابةُ صلاحيةٌ مستقلةٌ عن القراءة**: مَن يقرأ الملفَّ الأمَّ يرى
     التبويبَ، ومَن يملك الإدارةَ وحدَه يكتب فيه. و`$canEdit` يُقرأ من
     السجلِّ لا يُفترَض — وإلا صار كلُّ قارئٍ كاتبًا صامتًا. */
$pcPerm  = function_exists('check_page_permissions')
    ? check_page_permissions($conn, 'Clients/client_contacts.php') : array();
$canEdit = !empty($pcPerm['can_add']) || !empty($pcPerm['can_edit']);

/* المعالجُ قبلَ أيِّ إخراج */
if ($client !== null) {
    ems_pc_handle($conn, 'client', $CID, 'client_contacts.php?client=' . $CID);
}

$page_title = 'إيكوبيشن | جهات اتصال العميل';
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
    $header_back    = array('href' => 'clients.php', 'class' => '', 'label' => 'سجل العملاء');
    include __DIR__ . '/../includes/page_header.php'; ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_sal_client_contacts
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'رقم جهة الاتصال' => 'g234',
            'رقم العميل' => 'g235',
            'اسم العميل (بحث)' => 'g236',
            'الاسم' => 'g237',
            'المسمى الوظيفي' => 'g238',
            'الهاتف' => 'g239',
            'البريد' => 'g240',
            'دوره في القرار' => 'g241',
            'الحالة' => 'g242',
            'ملاحظات' => 'g243',
            'حالة البيانات' => 'g244',
            'أثر البحث في المصادر' => 'g245',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('sal_client_contacts');
        echo ems_w14_grid('emsList_sal_client_contacts', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في جهات اتصال العملاء'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }

    if ($client === null) {
        /* ◆ **ولا يُصيَّر نموذجٌ بلا طرف**: صفٌّ بلا عميلٍ مرجعيٍّ يُكتَب ثم لا
             يُعرَف صاحبُه — فيُمنع الإدخالُ ويُشرَح السبب. */
        echo '<div class="card"><div class="card-body"><p class="pc-note">'
           . '<strong>افتح هذا التبويب من ملف عميل بعينه</strong> — '
           . 'جهة الاتصال تابعة لعميل، ولا تسجل بلا طرف مرجعي.'
           . ' <a href="clients.php">سجل العملاء</a></p></div></div>';
    } else {
        $label = (string) (isset($client['legal_name']) && $client['legal_name'] !== ''
            ? $client['legal_name'] : (isset($client['client_name']) ? $client['client_name'] : ('#' . $CID)));
        echo ems_pc_render(ems_pc_rows($conn, 'client', $CID), $label, $canEdit);
    }
    ?>
</div>
<?php include '../infooter.php'; ?>
