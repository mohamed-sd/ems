<?php
/**
 * api/controllers/drivers.php — تعيينات السائقين (equipment_drivers).
 *   POST /api/equipment-drivers          إضافة سائق لمعدة (add_new_driver)
 *   PUT  /api/equipment-drivers/{rel_id} تعديل/إنهاء تعيين (save_single_driver)
 *   GET  /api/drivers/available?equipment_id=  السائقون المتاحون للإضافة
 *
 * القاعدة الصارمة: السائق الواحد لا يكون نشطاً على أكثر من تعيين — تُنهى
 * تعييناته السارية السابقة تلقائياً عند الإضافة.
 *
 * @package EMS\Api
 */

if (!defined('EMS_API')) {
    http_response_code(403);
    exit('Forbidden');
}

/** POST /api/equipment-drivers — إضافة سائق لمعدة (add_new_driver). */
function driver_create(): void
{
    global $conn;

    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    api_fetch_project($ctx, $projectId);
    $perms = api_movement_perms();

    if (!$perms['can_edit']) {
        api_fail('لا توجد صلاحية للتعديل', 403);
    }

    $driver_id    = api_int('driver_id');
    $equipment_id = api_int('equipment_id');
    if ($driver_id <= 0 || $equipment_id <= 0) {
        api_fail('بيانات ناقصة: اختر السائق والمعدة', 422);
    }

    $shift_type = api_str('shift_type', 'B');
    if (!in_array($shift_type, ['D', 'N', 'B'], true)) {
        $shift_type = 'B';
    }

    $start_date = api_str('start_date', date('Y-m-d'));
    $end_date = api_str('end_date', '');
    api_validate_date($start_date, 'تاريخ بداية التعيين');
    api_validate_date($end_date, 'تاريخ نهاية التعيين');
    if ($start_date === '') {
        $start_date = date('Y-m-d');
    }
    if ($end_date === '') {
        $end_date = '2099-12-31';
    }
    if (strtotime($end_date) < strtotime($start_date)) {
        api_fail('تاريخ النهاية يجب أن يكون بعد البداية', 422);
    }

    // المعدة يجب أن تكون مشغّلة في تشغيل ساري ضمن المشروع.
    $scope = operations_scope($ctx, $projectId);
    $eqCheckSql = "SELECT 1 FROM operations WHERE equipment = ? AND project_id = ? AND status = 1"
        . ($scope['ops_scope_inline']) . " LIMIT 1";
    $chk = mysqli_prepare($conn, $eqCheckSql);
    mysqli_stmt_bind_param($chk, 'ii', $equipment_id, $projectId);
    mysqli_stmt_execute($chk);
    $chkRes = mysqli_stmt_get_result($chk);
    $eqRunning = $chkRes && mysqli_num_rows($chkRes) > 0;
    mysqli_stmt_close($chk);
    if (!$eqRunning) {
        api_fail('المعدة المختارة غير مشغّلة في مشروع ساري', 409);
    }

    $ed_has_company = $scope['ed_has_company'];
    $ed_has_shift = $scope['ed_has_shift'];
    $useCompany = (!$ctx['is_super'] && $ed_has_company);
    $companyId = intval($ctx['company_id']);

    // إنهاء التعيينات السارية السابقة لنفس السائق (سائق واحد = تعيين نشط واحد).
    if ($useCompany) {
        $endPrev = mysqli_prepare(
            $conn,
            'UPDATE equipment_drivers SET status = 0, end_date = ? WHERE driver_id = ? AND status = 1 AND company_id = ?'
        );
        mysqli_stmt_bind_param($endPrev, 'sii', $start_date, $driver_id, $companyId);
    } else {
        $endPrev = mysqli_prepare(
            $conn,
            'UPDATE equipment_drivers SET status = 0, end_date = ? WHERE driver_id = ? AND status = 1'
        );
        mysqli_stmt_bind_param($endPrev, 'si', $start_date, $driver_id);
    }
    mysqli_stmt_execute($endPrev);
    mysqli_stmt_close($endPrev);

    // الإدراج (مع الأعمدة المتاحة فقط).
    $cols = ['equipment_id', 'driver_id', 'start_date', 'end_date', 'status'];
    $place = ['?', '?', '?', '?', '1'];
    $types = 'iiss';
    $vals = [$equipment_id, $driver_id, $start_date, $end_date];

    if ($ed_has_shift) {
        $cols[] = 'shift_type';
        $place[] = '?';
        $types .= 's';
        $vals[] = $shift_type;
    }
    if ($useCompany) {
        $cols[] = 'company_id';
        $place[] = '?';
        $types .= 'i';
        $vals[] = $companyId;
    }

    $sql = 'INSERT INTO equipment_drivers (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $place) . ')';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        api_fail('خطأ في إضافة السائق', 500);
    }
    mysqli_stmt_bind_param($stmt, $types, ...$vals);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        api_fail('خطأ في إضافة السائق', 500);
    }
    $newId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    api_ok(['rel_id' => intval($newId)], 'تم إضافة السائق ✅ (أُنهيت تعييناته السابقة)', 201);
}

/** PUT /api/equipment-drivers/{rel_id} — تعديل/إنهاء تعيين (save_single_driver). */
function driver_update(int $relId): void
{
    global $conn;

    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    api_fetch_project($ctx, $projectId);
    $perms = api_movement_perms();

    if (!$perms['can_edit']) {
        api_fail('لا توجد صلاحية للتعديل', 403);
    }
    if ($relId <= 0) {
        api_fail('معرّف السائق غير صحيح', 400);
    }

    $scope = operations_scope($ctx, $projectId);

    // تأكّد من وجود التعيين ضمن نطاق الشركة (قبل التحديث).
    $existsSql = 'SELECT id FROM equipment_drivers WHERE id = ?'
        . ($scope['ed_scope_inline']) . ' LIMIT 1';
    $exStmt = mysqli_prepare($conn, $existsSql);
    mysqli_stmt_bind_param($exStmt, 'i', $relId);
    mysqli_stmt_execute($exStmt);
    $exRes = mysqli_stmt_get_result($exStmt);
    $exists = $exRes && mysqli_num_rows($exRes) > 0;
    mysqli_stmt_close($exStmt);
    if (!$exists) {
        api_fail('التعيين غير موجود أو خارج نطاقك', 404);
    }

    $shift_type = api_str('shift_type', 'B');
    if (!in_array($shift_type, ['D', 'N', 'B'], true)) {
        $shift_type = 'B';
    }

    $status = (api_int('status', 0) === 0) ? 0 : 1;
    $start_date = api_str('start_date', '');
    $end_date = api_str('end_date', '');
    api_validate_date($start_date, 'تاريخ بداية التعيين');
    api_validate_date($end_date, 'تاريخ نهاية التعيين');
    if ($start_date !== '' && $end_date !== '' && strtotime($end_date) < strtotime($start_date)) {
        api_fail('تاريخ النهاية يجب أن يكون بعد البداية', 422);
    }

    // الافتراضات (مطابقة save_single_driver): فارغ البداية = اليوم، فارغ النهاية = 2099-12-31.
    $start_save = $start_date !== '' ? $start_date : date('Y-m-d');
    $end_save = $end_date !== '' ? $end_date : '2099-12-31';

    $set = ['start_date = ?', 'end_date = ?', 'status = ?'];
    $types = 'ssi';
    $vals = [$start_save, $end_save, $status];
    if ($scope['ed_has_shift']) {
        $set[] = 'shift_type = ?';
        $types .= 's';
        $vals[] = $shift_type;
    }

    $companyClause = '';
    if (!$ctx['is_super'] && $scope['ed_has_company']) {
        $companyClause = ' AND company_id = ?';
    }

    $sql = 'UPDATE equipment_drivers SET ' . implode(', ', $set) . ' WHERE id = ?' . $companyClause;
    $types .= 'i';
    $vals[] = $relId;
    if ($companyClause !== '') {
        $types .= 'i';
        $vals[] = intval($ctx['company_id']);
    }

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        api_fail('خطأ في تحديث السائق', 500);
    }
    mysqli_stmt_bind_param($stmt, $types, ...$vals);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        api_fail('خطأ في تحديث السائق', 500);
    }
    mysqli_stmt_close($stmt);

    api_ok(['rel_id' => $relId, 'status' => $status], $status === 0 ? 'تم إنهاء التعيين ✅' : 'تم حفظ السائق ✅');
}

/** GET /api/drivers/available?equipment_id= — السائقون المتاحون للإضافة. */
function drivers_available(): void
{
    global $conn;

    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    api_fetch_project($ctx, $projectId);
    $perms = api_movement_perms();
    if (!$perms['can_view']) {
        api_fail('لا توجد صلاحية عرض', 403);
    }

    $isSuper = $ctx['is_super'];
    $companyId = intval($ctx['company_id']);

    $drivers_has_company = db_table_has_column($conn, 'employees', 'company_id');
    $drivers_has_status = db_table_has_column($conn, 'employees', 'status');
    $drivers_has_project = db_table_has_column($conn, 'employees', 'project_id');
    $ed_has_company = db_table_has_column($conn, 'equipment_drivers', 'company_id');

    $driver_company_scope = (!$isSuper && $drivers_has_company) ? ' AND d.company_id = ' . $companyId : '';
    $driver_status_scope = $drivers_has_status ? ' AND d.status = 1' : '';
    $driver_project_scope = $drivers_has_project ? " AND (d.project_id = $projectId OR d.project_id IS NULL)" : '';

    $all_drivers_sql = "SELECT DISTINCT d.id, d.name, d.phone, d.driver_code, d.skill_level
                        FROM employees d
                        WHERE 1=1
                          $driver_company_scope
                          $driver_status_scope
                          $driver_project_scope
                        ORDER BY d.name ASC";
    $res = mysqli_query($conn, $all_drivers_sql);
    $all = [];
    if ($res) {
        while ($d = mysqli_fetch_assoc($res)) {
            $intId = intval($d['id']);
            $all[$intId] = [
                'id'          => $intId,
                'name'        => $d['name'] ?? '',
                'phone'       => $d['phone'] ?? '',
                'driver_code' => $d['driver_code'] ?? '',
                'skill_level' => $d['skill_level'] ?? '',
            ];
        }
    }

    // استثناء أصحاب التعيين الساري.
    $active_scope = (!$isSuper && $ed_has_company) ? ' AND company_id = ' . $companyId : '';
    $activeRes = mysqli_query($conn, "SELECT DISTINCT driver_id FROM equipment_drivers WHERE status = 1$active_scope");
    if ($activeRes) {
        while ($a = mysqli_fetch_assoc($activeRes)) {
            unset($all[intval($a['driver_id'])]);
        }
    }

    api_ok(['drivers' => array_values($all)], 'تم جلب السائقين المتاحين');
}
