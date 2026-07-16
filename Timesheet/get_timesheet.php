<?php
session_start();
if (!isset($_SESSION['user'])) {
    while (ob_get_level()) ob_end_clean();
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

include '../config.php';

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$is_super_admin = isset($_SESSION['user']['role']) && (string)$_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

// العزل عبر بوابة المستأجر — والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$gts_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('timesheet fetch super') : ems_tenant_db();

if (!isset($_GET['id'])) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('normalize_timesheet_date')) {
    function normalize_timesheet_date($date_str)
    {
        $date_str = trim((string)$date_str);
        if ($date_str === '') {
            return '';
        }

        $formats = ['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $date_str);
            if ($dt && $dt->format($fmt) === $date_str) {
                return $dt->format('Y-m-d');
            }
        }

        $ts = strtotime($date_str);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return '';
    }
}

$id = intval($_GET['id']);
$row = null;
try {
    $row = $gts_gate->selectOne('timesheet', array('where' => array('id' => $id)));
} catch (\Throwable $t) { error_log('get_timesheet: ' . $t->getMessage()); }
if (!$row) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($row['date'])) {
    $safe_date = normalize_timesheet_date($row['date']);
    $row['date'] = ($safe_date !== '') ? $safe_date : '';
}

// لإظهار فرق العداد بشكل ودود نعيّن counter_diff_display
$row['counter_diff_display'] = '';
if (!empty($row['counter_diff'])) {
    $diff = intval($row['counter_diff']);
    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);
    $seconds = $diff % 60;
    $row['counter_diff_display'] = $hours . " ساعة " . $minutes . " دقيقة " . $seconds . " ثانية";
}

echo json_encode($row, JSON_UNESCAPED_UNICODE);

?>
