<?php
/**
 * api/controllers/operations.php — الحركة والتشغيل (مكافئ movement_operations.php).
 *   GET  /api/operations            قائمة التشغيلات مقسّمة نهار/ليل + خريطة
 *   POST /api/operations            إضافة تشغيل (add_new_operation)
 *   PUT  /api/operations/{op_id}    تعديل/إنهاء تشغيل (save_single_operation)
 *
 * القواعد الصارمة مطابقة للشاشة: لا تشغيل مزدوج لمعدة، التحرير بصلاحية فقط،
 * التواريخ Y-m-d والنهاية بعد البداية.
 *
 * @package EMS\Api
 */

if (!defined('EMS_API')) {
    http_response_code(403);
    exit('Forbidden');
}

/** أعلام أعمدة + نطاق الشركة المستخدمة في تشغيلات المشروع. */
function operations_scope(array $ctx, int $projectId): array
{
    global $conn;
    $isSuper = $ctx['is_super'];
    $companyId = intval($ctx['company_id']);

    $operations_has_company = db_table_has_column($conn, 'operations', 'company_id');
    $ed_has_company = db_table_has_column($conn, 'equipment_drivers', 'company_id');
    $ed_has_shift = db_table_has_column($conn, 'equipment_drivers', 'shift_type');

    return [
        'ops_scope'        => (!$isSuper && $operations_has_company) ? ' AND o.company_id = ' . $companyId : '',
        'ops_scope_inline' => (!$isSuper && $operations_has_company) ? ' AND company_id = ' . $companyId : '',
        'ed_scope'         => (!$isSuper && $ed_has_company) ? ' AND ed.company_id = ' . $companyId : '',
        'ed_scope_inline'  => (!$isSuper && $ed_has_company) ? ' AND company_id = ' . $companyId : '',
        'ops_has_company'  => $operations_has_company,
        'ed_has_company'   => $ed_has_company,
        'ed_has_shift'     => $ed_has_shift,
    ];
}

/** GET /api/operations */
function operations_index(): void
{
    global $conn;

    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    $project = api_fetch_project($ctx, $projectId);
    $perms = api_movement_perms();

    if (!$perms['can_view']) {
        api_fail('لا توجد صلاحية عرض الشاشة الموحدة', 403);
    }

    $scope = operations_scope($ctx, $projectId);

    // ── تشغيلات المشروع ───────────────────────────────────────────────────
    $operations_sql = "SELECT o.id, o.equipment, o.equipment_category, o.start, o.end, o.shift_type, o.status,
                              o.total_equipment_hours, o.shift_hours,
                              e.code AS equipment_code, e.name AS equipment_name,
                              et.type AS equipment_type_name,
                              s.name AS supplier_name,
                              COUNT(DISTINCT CASE WHEN ed.status = 1 THEN ed.driver_id END) AS active_drivers_count
                       FROM operations o
                       LEFT JOIN equipments e ON e.id = o.equipment
                       LEFT JOIN equipments_types et ON et.id = e.type
                       LEFT JOIN suppliers s ON s.id = o.supplier_id
                       LEFT JOIN equipment_drivers ed ON ed.equipment_id = o.equipment
                       WHERE o.project_id = $projectId
                         {$scope['ops_scope']}
                       GROUP BY o.id
                       ORDER BY o.status DESC, o.id DESC";
    $operations_res = mysqli_query($conn, $operations_sql);
    $operations_rows = [];
    if ($operations_res) {
        while ($r = mysqli_fetch_assoc($operations_res)) {
            $operations_rows[] = $r;
        }
    }

    // ── سائقو كل معدة ─────────────────────────────────────────────────────
    $shift_select = $scope['ed_has_shift'] ? 'ed.shift_type' : "'B' AS shift_type";
    $drivers_sql = "SELECT ed.id, ed.equipment_id, ed.driver_id, ed.start_date, ed.end_date, ed.status,
                           $shift_select,
                           d.name AS driver_name, d.phone AS driver_phone
                    FROM equipment_drivers ed
                    INNER JOIN drivers d ON d.id = ed.driver_id
                    INNER JOIN equipments e ON e.id = ed.equipment_id
                    WHERE EXISTS (
                        SELECT 1 FROM operations o
                        WHERE o.equipment = ed.equipment_id
                          AND o.project_id = $projectId
                          {$scope['ops_scope']}
                    )
                    {$scope['ed_scope']}
                    ORDER BY ed.status DESC, ed.id DESC";
    $drivers_res = mysqli_query($conn, $drivers_sql);
    $drivers_by_equipment = [];
    if ($drivers_res) {
        while ($d = mysqli_fetch_assoc($drivers_res)) {
            $eid = intval($d['equipment_id']);
            if (!isset($drivers_by_equipment[$eid])) {
                $drivers_by_equipment[$eid] = [];
            }
            $drivers_by_equipment[$eid][] = [
                'rel_id'       => intval($d['id']),
                'driver_id'    => intval($d['driver_id']),
                'driver_name'  => $d['driver_name'] ?? '',
                'driver_phone' => $d['driver_phone'] ?? '',
                'shift_type'   => $d['shift_type'] ?? 'B',
                'start_date'   => $d['start_date'] ?? '',
                'end_date'     => ((string)($d['end_date'] ?? '') === '2099-12-31') ? '' : (string)($d['end_date'] ?? ''),
                'status'       => intval($d['status']),
            ];
        }
    }

    // ── تنسيق التشغيلات + التقسيم نهار/ليل ─────────────────────────────────
    $project_lat = isset($project['latitude']) && $project['latitude'] !== '' ? floatval($project['latitude']) : 0.0;
    $project_lng = isset($project['longitude']) && $project['longitude'] !== '' ? floatval($project['longitude']) : 0.0;

    $day = [];
    $night = [];
    $map = [];
    foreach ($operations_rows as $op) {
        $eqId = intval($op['equipment']);
        $shift = isset($op['shift_type']) ? strtoupper((string)$op['shift_type']) : 'B';
        $endRaw = (string)($op['end'] ?? '');

        $row = [
            'op_id'                 => intval($op['id']),
            'equipment_id'          => $eqId,
            'equipment_code'        => $op['equipment_code'] ?? '',
            'equipment_name'        => $op['equipment_name'] ?? '',
            'equipment_type_name'   => $op['equipment_type_name'] ?? '',
            'supplier_name'         => $op['supplier_name'] ?? '',
            'equipment_category'    => $op['equipment_category'] ?? 'أساسي',
            'shift_type'            => $shift,
            'status'                => intval($op['status']),
            'start'                 => (string)($op['start'] ?? ''),
            'end'                   => ($endRaw === '2099-12-31') ? '' : $endRaw,
            'total_equipment_hours' => floatval($op['total_equipment_hours']),
            'shift_hours'           => floatval($op['shift_hours']),
            'active_drivers_count'  => intval($op['active_drivers_count']),
            'drivers'               => $drivers_by_equipment[$eqId] ?? [],
        ];

        if ($shift === 'D' || $shift === 'B') {
            $day[] = $row;
        }
        if ($shift === 'N' || $shift === 'B') {
            $night[] = $row;
        }

        // خريطة: السارية فقط، وإحداثيات المشروع (المعدّات بلا إحداثيات).
        if (intval($op['status']) === 1 && $project_lat != 0.0 && $project_lng != 0.0) {
            $map[] = [
                'equipment' => trim((string)($op['equipment_code'] ?? '') . ' - ' . (string)($op['equipment_name'] ?? '')),
                'type'      => $op['equipment_type_name'] ?? '-',
                'drivers'   => intval($op['active_drivers_count']),
                'shift'     => $shift,
                'lat'       => $project_lat,
                'lng'       => $project_lng,
            ];
        }
    }

    api_ok([
        'project'     => api_format_project($project),
        'permissions' => $perms,
        'day'         => $day,
        'night'       => $night,
        'map'         => $map,
    ], 'تم جلب التشغيلات');
}

/** POST /api/operations — إضافة تشغيل (add_new_operation). */
function operations_create(): void
{
    global $conn;

    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    api_fetch_project($ctx, $projectId);
    $perms = api_movement_perms();

    if (!$perms['can_edit']) {
        api_fail('لا توجد صلاحية للتعديل', 403);
    }

    $scope = operations_scope($ctx, $projectId);

    $equipment      = api_int('equipment');
    $contract_id    = api_int('contract_id');
    $supplier_id    = api_int('supplier_id');
    $equipment_type = api_int('equipment_type');

    if ($equipment <= 0 || $contract_id <= 0 || $supplier_id <= 0 || $equipment_type <= 0) {
        api_fail('بيانات ناقصة: تأكد من اختيار المعدة والعقد والمورد والنوع', 422);
    }

    $equipment_category = api_str('equipment_category', 'أساسي');
    if ($equipment_category !== 'أساسي' && $equipment_category !== 'احتياطي') {
        $equipment_category = 'أساسي';
    }

    $shift_type = api_str('shift_type', 'B');
    if (!in_array($shift_type, ['D', 'N', 'B'], true)) {
        $shift_type = 'B';
    }

    $start = api_str('start', date('Y-m-d'));
    $end = api_str('end', '');
    api_validate_date($start, 'تاريخ البداية');
    api_validate_date($end, 'تاريخ النهاية');
    if ($start !== '' && $end !== '' && strtotime($end) < strtotime($start)) {
        api_fail('تاريخ النهاية يجب أن يكون بعد البداية', 422);
    }

    // منع التشغيل المزدوج للمعدة (نفس فحص الشاشة — عام لكل النظام).
    $chk = mysqli_prepare($conn, 'SELECT id FROM operations WHERE equipment = ? AND status = 1 LIMIT 1');
    mysqli_stmt_bind_param($chk, 'i', $equipment);
    mysqli_stmt_execute($chk);
    $chkRes = mysqli_stmt_get_result($chk);
    $hasActive = $chkRes && mysqli_num_rows($chkRes) > 0;
    mysqli_stmt_close($chk);
    if ($hasActive) {
        api_fail('المعدة تعمل بالفعل في تشغيل ساري آخر', 409);
    }

    $total_equipment_hours = api_float('total_equipment_hours');
    $shift_hours = api_float('shift_hours');
    $status = (api_int('status', 1) === 0) ? 0 : 1;
    $days = '0';
    $reason = '';

    $useCompany = (!$ctx['is_super'] && $scope['ops_has_company']);
    $companyId = intval($ctx['company_id']);

    if ($useCompany) {
        $sql = 'INSERT INTO operations
                (equipment, equipment_type, equipment_category, project_id, contract_id, supplier_id, start, end, reason, days, total_equipment_hours, shift_hours, shift_type, status, company_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            'iisiiissssddsii',
            $equipment, $equipment_type, $equipment_category, $projectId, $contract_id, $supplier_id,
            $start, $end, $reason, $days, $total_equipment_hours, $shift_hours, $shift_type, $status, $companyId
        );
    } else {
        $sql = 'INSERT INTO operations
                (equipment, equipment_type, equipment_category, project_id, contract_id, supplier_id, start, end, reason, days, total_equipment_hours, shift_hours, shift_type, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            'iisiiissssddsi',
            $equipment, $equipment_type, $equipment_category, $projectId, $contract_id, $supplier_id,
            $start, $end, $reason, $days, $total_equipment_hours, $shift_hours, $shift_type, $status
        );
    }

    if (!$stmt || !mysqli_stmt_execute($stmt)) {
        if ($stmt) {
            mysqli_stmt_close($stmt);
        }
        api_fail('خطأ في إضافة التشغيل الجديد', 500);
    }
    $newId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    api_ok(['op_id' => intval($newId)], 'تم إضافة التشغيل ✅', 201);
}

/** PUT /api/operations/{op_id} — تعديل/إنهاء تشغيل (save_single_operation). */
function operations_update(int $opId): void
{
    global $conn;

    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    api_fetch_project($ctx, $projectId);
    $perms = api_movement_perms();

    if (!$perms['can_edit']) {
        api_fail('لا توجد صلاحية للتعديل', 403);
    }
    if ($opId <= 0) {
        api_fail('معرّف التشغيل غير صحيح', 400);
    }

    $scope = operations_scope($ctx, $projectId);

    // تأكّد من وجود التشغيل ضمن نطاق المشروع/الشركة (قبل التحديث).
    $existsSql = 'SELECT id FROM operations WHERE id = ? AND project_id = ?'
        . ($scope['ops_scope_inline']) . ' LIMIT 1';
    $exStmt = mysqli_prepare($conn, $existsSql);
    mysqli_stmt_bind_param($exStmt, 'ii', $opId, $projectId);
    mysqli_stmt_execute($exStmt);
    $exRes = mysqli_stmt_get_result($exStmt);
    $exists = $exRes && mysqli_num_rows($exRes) > 0;
    mysqli_stmt_close($exStmt);
    if (!$exists) {
        api_fail('التشغيل غير موجود أو خارج نطاقك', 404);
    }

    $equipment_category = api_str('equipment_category', 'أساسي');
    if ($equipment_category !== 'أساسي' && $equipment_category !== 'احتياطي') {
        $equipment_category = 'أساسي';
    }

    $shift_type = api_str('shift_type', 'B');
    if (!in_array($shift_type, ['D', 'N', 'B'], true)) {
        $shift_type = 'B';
    }

    $status = (api_int('status', 0) === 0) ? 0 : 1;
    $start = api_str('start', '');
    $end = api_str('end', '');
    api_validate_date($start, 'تاريخ البداية');
    api_validate_date($end, 'تاريخ النهاية');
    if ($start !== '' && $end !== '' && strtotime($end) < strtotime($start)) {
        api_fail('تاريخ النهاية يجب أن يكون بعد البداية', 422);
    }

    // بناء SET ديناميكي مع عبارات مُجهّزة.
    $set = ['equipment_category = ?', 'shift_type = ?', 'status = ?'];
    $types = 'ssi';
    $vals = [$equipment_category, $shift_type, $status];
    if ($start !== '') {
        $set[] = 'start = ?';
        $types .= 's';
        $vals[] = $start;
    }
    if ($end !== '') {
        $set[] = 'end = ?';
        $types .= 's';
        $vals[] = $end;
    }

    $companyClause = '';
    if (!$ctx['is_super'] && $scope['ops_has_company']) {
        $companyClause = ' AND company_id = ?';
    }

    $sql = 'UPDATE operations SET ' . implode(', ', $set) . ' WHERE id = ? AND project_id = ?' . $companyClause;
    $types .= 'ii';
    $vals[] = $opId;
    $vals[] = $projectId;
    if ($companyClause !== '') {
        $types .= 'i';
        $vals[] = intval($ctx['company_id']);
    }

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        api_fail('خطأ في تحديث التشغيل', 500);
    }
    mysqli_stmt_bind_param($stmt, $types, ...$vals);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        api_fail('خطأ في تحديث التشغيل', 500);
    }
    mysqli_stmt_close($stmt);

    api_ok(['op_id' => $opId, 'status' => $status], $status === 0 ? 'تم إنهاء التشغيل ✅' : 'تم حفظ التشغيل ✅');
}
