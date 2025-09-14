<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
include("../config.php"); // ملف الاتصال بقاعدة البيانات

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

    if ($type == "1") {
        // تأكيد
        $sql = "UPDATE timesheet 
                SET status = 2, time_notes = '$notes' 
                WHERE id = $id";
    } elseif ($type == "2") {
        // رفض
        $sql = "UPDATE timesheet 
                SET status = 3, time_notes = '$notes' 
                WHERE id = $id";
    }

    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('تمت العملية بنجاح');window.location.href='timesheet.php?type=$t';</script>";
        exit();
    } else {
        echo "خطأ: " . mysqli_error($conn);
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
