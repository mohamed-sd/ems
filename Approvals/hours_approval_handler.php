<?php
/**
 * hours_approval_handler.php
 * معالج API لنظام اعتماد ساعات العمل
 * يقبل طلبات POST فقط ويعيد JSON
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

require_once '../config.php';

$role       = strval($_SESSION['user']['role']);
$user_id    = intval($_SESSION['user']['id']);
$user_name  = mysqli_real_escape_string($conn, $_SESSION['user']['name'] ?? 'غير معروف');
$company_id = intval($_SESSION['user']['company_id'] ?? 0);

// الأدوار المسموح لها: المدراء الرئيسيون والأدمن
$allowed_roles = ['-1', '1', '2', '3', '4' , '5'];
if (!in_array($role, $allowed_roles)) {
    die(json_encode(['success' => false, 'message' => 'ليس لديك صلاحية']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']));
}

// التأكد من وجود الجداول
$conn->query("CREATE TABLE IF NOT EXISTS `timesheet_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timesheet_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `approval_level` tinyint(1) NOT NULL,
  `approved_by` int(11) NOT NULL,
  `approved_by_name` varchar(255) NOT NULL,
  `approved_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ts_level` (`timesheet_id`, `approval_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

$conn->query("CREATE TABLE IF NOT EXISTS `timesheet_approval_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timesheet_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `column_name` varchar(100) NOT NULL,
  `column_label` varchar(255) NOT NULL,
  `note_text` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_by_name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

$action = isset($_POST['action']) ? trim($_POST['action']) : '';

// ── خريطة مستوى الاعتماد لكل دور ─────────────────────────
$role_level_map = ['1' => 1, '2' => 2, '3' => 3, '4' => 4];

function role_label_ar($role_id) {
    $role_id = strval($role_id);
    switch ($role_id) {
        case '-1': return 'الأدمن';
        case '1':  return 'مدير المشاريع';
        case '2':  return 'مدير الموردين';
        case '3':  return 'مدير الأسطول';
        case '4':  return 'مدير المشغلين';
        case '5':  return 'مدير الموقع';
        case '6':  return 'مدخل الساعات';
        case '7':  return 'مراجع الموردين';
        case '8':  return 'مراجع المشغلين';
        case '9':  return 'مراجع الأعطال';
        case '10': return 'مدير الحركة والتشغيل';
        default:   return 'غير محدد';
    }
}

// ─────────────────────────────────────────────────────────────
// اعتماد سجل / مجموعة سجلات
// ─────────────────────────────────────────────────────────────
if ($action === 'approve') {
    if (!isset($role_level_map[$role])) {
        die(json_encode(['success' => false, 'message' => 'الأدمن لا يعتمد مباشرة']));
    }
    $my_level = $role_level_map[$role];
    $prev_level = $my_level - 1;

    $ids_raw = isset($_POST['ids']) ? $_POST['ids'] : '';
    $ids = [];
    foreach (explode(',', $ids_raw) as $i) {
        $i = intval(trim($i));
        if ($i > 0) $ids[] = $i;
    }
    if (empty($ids)) {
        die(json_encode(['success' => false, 'message' => 'لم يتم تحديد أي سجل']));
    }

    $approved  = 0;
    $skipped   = 0;
    $escaped_name = mysqli_real_escape_string($conn, $_SESSION['user']['name'] ?? 'غير معروف');

    foreach ($ids as $ts_id) {
        // التحقق من أن السجل ينتمي لنفس الشركة
        $scope = ($company_id > 0) ? " AND (company_id = $company_id OR company_id IS NULL)" : '';
        $chk = $conn->query("SELECT id FROM timesheet WHERE id = $ts_id $scope");
        if (!$chk || $chk->num_rows === 0) { $skipped++; continue; }

        // التحقق من اعتماد المستوى السابق (ما عدا المستوى الأول)
        if ($prev_level > 0) {
            $prev_chk = $conn->query(
                "SELECT id FROM timesheet_approvals
                 WHERE timesheet_id = $ts_id AND approval_level = $prev_level AND status = 1"
            );
            if (!$prev_chk || $prev_chk->num_rows === 0) { $skipped++; continue; }
        }

        // تجنب تكرار الاعتماد
        $dup = $conn->query(
            "SELECT id FROM timesheet_approvals
             WHERE timesheet_id = $ts_id AND approval_level = $my_level"
        );
        if ($dup && $dup->num_rows > 0) { $skipped++; continue; }

        $ins = $conn->query(
            "INSERT INTO timesheet_approvals
             (timesheet_id, company_id, approval_level, approved_by, approved_by_name)
             VALUES ($ts_id, $company_id, $my_level, $user_id, '$escaped_name')"
        );
        if ($ins) $approved++;
    }

    echo json_encode([
        'success' => true,
        'message' => "تم اعتماد $approved سجل" . ($skipped ? " (تم تخطي $skipped)" : ''),
        'approved' => $approved,
        'skipped'  => $skipped
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────
// إضافة ملاحظة على سجل / عمود محدد
// ─────────────────────────────────────────────────────────────
if ($action === 'add_note') {
    $ts_id       = intval($_POST['timesheet_id'] ?? 0);
    $col_name    = mysqli_real_escape_string($conn, trim($_POST['column_name'] ?? ''));
    $col_label   = mysqli_real_escape_string($conn, trim($_POST['column_label'] ?? $col_name));
    $note_text   = mysqli_real_escape_string($conn, trim($_POST['note_text'] ?? ''));
    $escaped_name = mysqli_real_escape_string($conn, $_SESSION['user']['name'] ?? 'غير معروف');

    if ($ts_id <= 0 || $note_text === '') {
        die(json_encode(['success' => false, 'message' => 'بيانات ناقصة']));
    }

    // التحقق من أن السجل ينتمي لنفس الشركة
    $scope = ($company_id > 0) ? " AND (company_id = $company_id OR company_id IS NULL)" : '';
    $chk = $conn->query("SELECT id FROM timesheet WHERE id = $ts_id $scope");
    if (!$chk || $chk->num_rows === 0) {
        die(json_encode(['success' => false, 'message' => 'السجل غير موجود']));
    }

    $ins = $conn->query(
        "INSERT INTO timesheet_approval_notes
         (timesheet_id, company_id, column_name, column_label, note_text, created_by, created_by_name)
         VALUES ($ts_id, $company_id, '$col_name', '$col_label', '$note_text', $user_id, '$escaped_name')"
    );

    if ($ins) {
        echo json_encode(['success' => true, 'message' => 'تمت إضافة الملاحظة']);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل حفظ الملاحظة: ' . $conn->error]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────
// جلب ملاحظات سجل معين
// ─────────────────────────────────────────────────────────────
if ($action === 'get_notes') {
    $ts_id = intval($_POST['timesheet_id'] ?? 0);
    if ($ts_id <= 0) {
        die(json_encode(['success' => false, 'message' => 'معرف غير صحيح']));
    }

    $scope = ($company_id > 0) ? " AND (n.company_id = $company_id OR n.company_id IS NULL)" : '';
    $res = $conn->query(
        "SELECT n.id, n.column_label, n.note_text, n.created_by_name, n.created_at,
                COALESCE(u.role, '') AS created_by_role
         FROM timesheet_approval_notes n
         LEFT JOIN users u ON u.id = n.created_by
         WHERE n.timesheet_id = $ts_id AND n.status = 1 $scope
         ORDER BY n.created_at ASC"
    );

    $notes = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['created_by_role_label'] = role_label_ar($row['created_by_role']);
            $notes[] = $row;
        }
    }
    echo json_encode(['success' => true, 'notes' => $notes]);
    exit;
}

// ─────────────────────────────────────────────────────────────
// حذف ملاحظة (للمنشئ فقط)
// ─────────────────────────────────────────────────────────────
if ($action === 'delete_note') {
    echo json_encode(['success' => false, 'message' => 'حذف الملاحظات غير مسموح']);
    exit;
}

die(json_encode(['success' => false, 'message' => 'إجراء غير معروف']));
