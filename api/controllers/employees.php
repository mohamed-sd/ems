<?php
/**
 * api/controllers/employees.php — تعيينات السائقين على المعدات (N-07)
 *   POST /api/equipment-drivers            — تعيين سائق لمعدة
 *   PUT  /api/equipment-drivers/{rel_id}   — تعديل تعيين (إنهاء/وردية)
 *   GET  /api/drivers/available?equipment_id= — السائقون المتاحون
 *
 * كان المتحكم الأمامي يطلب هذا الملف وهو غائب فتسقط الطبقة كلها 500 —
 * أُنشئ بحده الأدنى العامل على جدول equipment_drivers القائم وبعقد الغلاف
 * الموحَّد {success,message,data} (N-07).
 *
 * @package EMS\Api
 */

if (!defined('EMS_API')) {
    http_response_code(403);
    exit('Forbidden');
}

/** POST /api/equipment-drivers */
function driver_create(): void
{
    global $conn;
    $ctx = api_require_auth();
    $b = api_input();
    foreach (array('equipment_id', 'employee_id', 'start_date') as $f) {
        if (empty($b[$f])) {
            api_fail('حقل إلزامي مفقود: ' . $f, 422);
        }
    }
    $stmt = mysqli_prepare($conn,
        "INSERT INTO equipment_drivers (company_id, equipment_id, employee_id, start_date, end_date, shift_type, status)
         VALUES (?, ?, ?, ?, ?, ?, 1)");
    $co = (int) $ctx['company_id'];
    $eq = (int) $b['equipment_id'];
    $emp = (int) $b['employee_id'];
    $sd = (string) $b['start_date'];
    $ed = isset($b['end_date']) && $b['end_date'] !== '' ? (string) $b['end_date'] : null;
    $sh = isset($b['shift_type']) ? (string) $b['shift_type'] : 'صباحية';
    mysqli_stmt_bind_param($stmt, 'iiisss', $co, $eq, $emp, $sd, $ed, $sh);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        api_fail('تعذّر التعيين: ' . $err, 422);
    }
    $id = mysqli_stmt_insert_id($stmt);
    mysqli_stmt_close($stmt);
    api_ok(array('rel_id' => (int) $id), 'عُيّن السائق على المعدة');
}

/** PUT /api/equipment-drivers/{rel_id} */
function driver_update(int $relId): void
{
    global $conn;
    $ctx = api_require_auth();
    $b = api_input();
    $sets = array();
    $types = '';
    $vals = array();
    if (isset($b['end_date'])) { $sets[] = 'end_date = ?'; $types .= 's'; $vals[] = (string) $b['end_date']; }
    if (isset($b['shift_type'])) { $sets[] = 'shift_type = ?'; $types .= 's'; $vals[] = (string) $b['shift_type']; }
    if (isset($b['status'])) { $sets[] = 'status = ?'; $types .= 'i'; $vals[] = (int) $b['status']; }
    if (empty($sets)) {
        api_fail('لا حقول للتعديل', 422);
    }
    $sql = 'UPDATE equipment_drivers SET ' . implode(', ', $sets) . ' WHERE id = ? AND company_id = ?';
    $types .= 'ii';
    $vals[] = $relId;
    $vals[] = (int) $ctx['company_id'];
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$vals);
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($n < 1) {
        api_fail('التعيين غير موجود في نطاقك أو لا تغيير', 404);
    }
    api_ok(array('rel_id' => $relId), 'عُدّل التعيين');
}

/** GET /api/drivers/available?equipment_id= */
function drivers_available(): void
{
    global $conn;
    $ctx = api_require_auth();
    $eq = isset($_GET['equipment_id']) ? (int) $_GET['equipment_id'] : 0;
    $co = (int) $ctx['company_id'];
    // السائقون النشطون غير المعيَّنين حاليًّا على هذه المعدة
    $sql = "SELECT e.id, e.name FROM employees e
             WHERE e.company_id = ? AND COALESCE(e.status,1) = 1
               AND NOT EXISTS (SELECT 1 FROM equipment_drivers d
                                WHERE d.company_id = e.company_id AND d.employee_id = e.id
                                  AND d.status = 1 AND (d.end_date IS NULL OR d.end_date >= CURDATE())
                                  AND (? = 0 OR d.equipment_id = ?))
             ORDER BY e.name LIMIT 200";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iii', $co, $eq, $eq);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = array();
    while ($row = mysqli_fetch_assoc($res)) {
        $out[] = array('id' => (int) $row['id'], 'name' => (string) $row['name']);
    }
    mysqli_stmt_close($stmt);
    api_ok(array('drivers' => $out), 'السائقون المتاحون');
}
