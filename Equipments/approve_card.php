<?php
// اعتماد كرت المعدة (حوكمة خفيفة): draft → active + تسجيل المعتمِد ووقت الاعتماد.
// لا يُغيّر أي منطق تشغيل قائم — card_state عرضي/حوكمي في هذه المرحلة.
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

// عودة آمنة لشاشة معروفة فقط
$allowed_returns = ['equipments_fleet.php', 'equipments.php', 'equipment_profile.php'];
$return_raw = isset($_POST['return']) ? trim($_POST['return']) : 'equipments_fleet.php';
$return_base = basename(parse_url($return_raw, PHP_URL_PATH));
$return = in_array($return_base, $allowed_returns, true) ? $return_base : 'equipments_fleet.php';
$return_id = isset($_POST['return_id']) ? intval($_POST['return_id']) : 0;
$return_url = $return . ($return === 'equipment_profile.php' && $return_id > 0 ? ('?id=' . $return_id) : '');
$sep = (strpos($return_url, '?') !== false) ? '&' : '?';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $return_url);
    exit();
}

// صلاحية الاعتماد = صلاحية تعديل المعدات (دور الأسطول)
$perm = check_page_permissions($conn, 'equipments_fleet');
if (empty($perm['can_edit'])) {
    header('Location: ' . $return_url . $sep . 'msg=' . urlencode('❌ لا توجد صلاحية لاعتماد الكرت'));
    exit();
}

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id        = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

$equipment_id = isset($_POST['equipment_id']) ? intval($_POST['equipment_id']) : 0;

if ($equipment_id > 0 && db_table_has_column($conn, 'equipments', 'card_state')) {
    $scope = '';
    if (!$is_super_admin && db_table_has_column($conn, 'equipments', 'company_id') && $company_id > 0) {
        $scope = " AND company_id = " . $company_id;
    }
    $sql = "UPDATE equipments SET card_state = 'active', card_approved_by = ?, card_approved_at = NOW()
            WHERE id = ? AND card_state <> 'active'" . $scope;
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $user_id, $equipment_id);
        $stmt->execute();
    }
    header('Location: ' . $return_url . $sep . 'msg=' . urlencode('✅ تم اعتماد الكرت'));
    exit();
}

header('Location: ' . $return_url . $sep . 'msg=' . urlencode('تعذّر اعتماد الكرت'));
exit();
