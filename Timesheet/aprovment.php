<?php
include '../config.php';
require_login();
require_once '../includes/approval_workflow.php';
require_once __DIR__ . '/../includes/permissions_helper.php';

$is_super_admin = isset($_SESSION['user']['role']) && (string)$_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

/* UI-13: كلُّ منعٍ يقول ما حدث ويعطي طريقَ رجوعٍ — لا صفحةَ نصٍّ عارية. */
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة لحسابك ❌',
        'GOV-SCOPE-403', 'ادخل بحسابٍ مرتبطٍ بشركة');
}

$type = isset($_GET['type']) ? $_GET['type'] : null;
$id   = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($type == "1") {
    $typetext = "تأكيد ساعات العمل";
} elseif ($type == "2") {
    $typetext = "رفض ساعات العمل";
} else {
    ems_gov_flash_redirect('view_timesheet.php', 'طلبُ الاعتمادِ بلا نوعٍ صالح (تأكيد أو رفض) ❌',
        'GOV-REF-404', 'افتح الاعتمادَ من سجلِّ الوحداتِ اليومية');
}

// عند إرسال الفورم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t = mysqli_real_escape_string($conn, $_POST['t']);
    $notes = mysqli_real_escape_string($conn, $_POST['time_notes']);
    $user_id = approval_get_user_id();

    // فحص الملكية عبر بوابة المستأجر — والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
    // (محرك الاعتمادات approval_create_request قناة محكومة قائمة — لا تُمسّ.)
    $apv_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('timesheet aprovment super') : ems_tenant_db();
    $old_data = null;
    try {
        $old_data = $apv_gate->selectOne('timesheet', array(
            'columns' => array('id', 'status', 'time_notes'),
            'where'   => array('id' => $id),
        ));
    } catch (\Throwable $t) { error_log('aprovment ownership: ' . $t->getMessage()); }
    if (!$old_data) {
        ems_gov_flash_redirect("timesheet.php?type=$t", 'البيان غير موجود', 'GOV-SCOPE-403', '');
        exit();
    }

    // ── قفلُ النسخة (UX-03 §8.2 · قرار المالك 2026-07-27، مع قلب الكاتب) ──
    // اعتمادُ الموقع = قفل: لا تعديلَ ولا طلبَ تعديلٍ بعده — الطريقُ الوحيد
    // الإعادةُ الرسمية بسببٍ وبالرقم نفسه. يسري خلف EMS_TS_WRITER=service.
    require_once __DIR__ . '/../app/Services/Unit/TimesheetEntryService.php';
    if (\App\Services\Unit\TimesheetEntryService::writerServiceOn()) {
        try {
            $vl_uuid = 'ts:' . $id;
            $vl = $conn->prepare("SELECT state FROM unit_entries WHERE sync_uuid = ? LIMIT 1");
            $vl->bind_param('s', $vl_uuid);
            $vl->execute();
            $vl_row = $vl->get_result()->fetch_assoc();
            $vl->close();
            if ($vl_row && !in_array($vl_row['state'],
                    \App\Services\Unit\TimesheetEntryService::REFRESHABLE_STATES, true)) {
                ems_gov_flash_redirect("timesheet.php?type=$t", "🔒 قفلُ نسخة: اعتُمد موقعُ هذا اليوم (" . $vl_row['state'] . ") — التعديلُ بالإعادة الرسمية بسببٍ وبالرقم نفسه لا بطلب تعديل", 'GOV-SCOPE-403', '');
                exit();
            }
        } catch (\Throwable $vlT) { error_log('aprovment version lock: ' . $vlT->getMessage()); }
    }

    $new_status = 0;
    $approval_action = '';
    if ($type == "1") {
        $new_status = 2;
        $approval_action = 'approve';
    } elseif ($type == "2") {
        $new_status = 3;
        $approval_action = 'reject';
    }

    $new_data = [
        'status' => $new_status,
        'time_notes' => $notes
    ];

    $payload = approval_build_simple_update_payload('timesheet', ['id' => $id], $new_data, $old_data);
    $result = approval_create_request('timesheet', $id, $approval_action, $payload, $user_id, $conn);

    if (!empty($result['success'])) {
        $msg = (($result['status'] ?? 'pending') === 'approved') ? 'تم اعتماد الطلب وتنفيذه' : 'تم إرسال الطلب للموافقة';
        ems_gov_flash_redirect("timesheet.php?type=$t", "$msg", 'GOV-SCOPE-403', '');
        exit();
    } else {
        echo "خطأ: " . htmlspecialchars($result['message']);
    }
}

$page_title = "إيكوبيشن | $typetext";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include("../inheader.php");
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main">
<?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ — الشاشةُ كانت بلا رأسٍ معلَن. */
$header_icon = 'fas fa-window-maximize';
$header_title_html = htmlspecialchars('Aprovment', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 (9): حالاتُ الشاشةِ الدنيا (تحميل / فراغ / خطأ) — مخفيةٌ افتراضيًّا
echo ems_states_bundle('لا طلبَ اعتمادٍ مفتوحٌ لهذه الوحدةِ اليومية', 'افتحِ الاعتمادَ من سجلِّ الوحداتِ اليوميةِ بزرِّ «تأكيد» أو «رفض»');
?>

<style>
/* UXW-01 ٢: أنماطُ هذه الشاشةِ الثابتةُ منقولةٌ من الوسومِ إلى أصنافٍ ببادئةِ الشاشة */
.tsapv-title { text-align: center; }
.tsapv-form { margin-top: 40px; text-align: center; max-width: 600px; margin-left: auto; margin-right: auto; }
.tsapv-notes { width: 100%; height: 150px; padding: 10px; font-size: 16px; border-radius: 8px; }
.tsapv-actions { margin-top: 20px; }
.tsapv-submit { padding: 10px 30px; font-size: 18px; border: none; border-radius: 8px; background: var(--c-000022); color: var(--c-s-fff); cursor: pointer; }
</style>

    <h2 class="tsapv-title"><?= $typetext; ?></h2>

    <form id="timesheetForm" action="" method="post" class="tsapv-form">
        <?= csrf_field() ?>
        <div>
            <input name="t" type="hidden" value="<?= $_GET['t']; ?>"/>
            <textarea name="time_notes" required placeholder="أدخل ملاحظاتك هنا" aria-label="ملاحظاتُ قرارِ الاعتماد" class="tsapv-notes"></textarea>
        </div>
        <div class="tsapv-actions">
            <button type="submit" class="tsapv-submit">
                <?= $typetext; ?>
            </button>
        </div>
    </form>
</div>
</body>
</html>
