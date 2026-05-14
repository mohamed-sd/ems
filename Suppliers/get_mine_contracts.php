<?php
session_start();

while (ob_get_level()) ob_end_clean();

if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept project_id directly; legacy mine_id fallback: look up project from mines table
    if (isset($_POST['project_id']) && intval($_POST['project_id']) > 0) {
        $project_id = intval($_POST['project_id']);
    } elseif (isset($_POST['mine_id']) && intval($_POST['mine_id']) > 0) {
        $mine_id = intval($_POST['mine_id']);
        $fallback = mysqli_query($conn, "SELECT project_id FROM mines WHERE id = $mine_id LIMIT 1");
        $fallback_row = $fallback ? mysqli_fetch_assoc($fallback) : null;
        $project_id = $fallback_row ? intval($fallback_row['project_id']) : 0;
    } else {
        $project_id = 0;
    }

    if ($project_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    // جلب عقود المشروع المحدد
    $contracts_query = "SELECT
        c.id,
        CONCAT('عقد رقم ', c.id, ' - ', DATE_FORMAT(c.actual_start, '%Y/%m/%d'), ' - ', c.forecasted_contracted_hours, ' ساعة') as display_name,
        c.forecasted_contracted_hours
        FROM contracts c
        WHERE c.project_id = $project_id AND c.status = 1
        ORDER BY c.actual_start DESC";

    $result = mysqli_query($conn, $contracts_query);

    if (!$result) {
        die(json_encode(['success' => false, 'message' => 'خطأ في جلب العقود']));
    }

    $contracts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $contracts[] = [
            'id' => intval($row['id']),
            'display_name' => $row['display_name'],
            'hours' => floatval($row['forecasted_contracted_hours'])
        ];
    }

    echo json_encode([
        'success' => true,
        'contracts' => $contracts
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
exit;
?>
