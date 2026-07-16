<?php
include '../config.php';
require_login();
require_once '../includes/approval_workflow.php';

$is_super_admin = isset($_SESSION['user']['role']) && (string)$_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    die('Unauthorized company context');
}

$type = isset($_GET['type']) ? $_GET['type'] : null;
$id   = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($type == "1") {
    $typetext = "تأكيد ساعات العمل";
} elseif ($type == "2") {
    $typetext = "رفض ساعات العمل";
} else {
    die("طلب غير صحيح");
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
        echo "<script>alert('البيان غير موجود');window.location.href='timesheet.php?type=$t';</script>";
        exit();
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
        echo "<script>alert('$msg');window.location.href='timesheet.php?type=$t';</script>";
        exit();
    } else {
        echo "خطأ: " . htmlspecialchars($result['message']);
    }
}

$page_title = "إيكوبيشن | $typetext";
include("../inheader.php");
include('../insidebar.php');
?>
<div class="main">
    <h2 style="text-align:center;"><?= $typetext; ?></h2>

    <form id="timesheetForm" action="" method="post" style="margin-top:40px; text-align:center; max-width:600px; margin-left:auto; margin-right:auto;">
        <div>
            <input name="t" type="hidden" value="<?= $_GET['t']; ?>"/>
            <textarea name="time_notes" required placeholder="أدخل ملاحظاتك هنا" 
                      style="width:100%; height:150px; padding:10px; font-size:16px; border-radius:8px;"></textarea>
        </div>
        <div style="margin-top:20px;">
            <button type="submit" 
                    style="padding:10px 30px; font-size:18px; border:none; border-radius:8px; background:#000022; color:white; cursor:pointer;">
                <?= $typetext; ?>
            </button>
        </div>
    </form>
</div>
</body>
</html>
