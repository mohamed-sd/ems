<?php
/**
 * api/controllers/board.php — اللوحة الحيّة (مكافئ movement/map_page.php).
 *   GET /api/board
 *
 * يطابق منطق التجميع حسب المورّد وحساب ساعات التايم شيت في map_page.php حرفياً.
 *
 * @package EMS\Api
 */

if (!defined('EMS_API')) {
    http_response_code(403);
    exit('Forbidden');
}

/** GET /api/board */
function board_index(): void
{
    global $conn;

    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    $project = api_fetch_project($ctx, $projectId);
    $isSuper = $ctx['is_super'];
    $companyId = intval($ctx['company_id']);

    // الحالات التي تُعدّ "متوقفة" (نفس قائمة map_page.php).
    $stopped_states = ['معطلة', 'مبيعة/مسحوبة', 'خارج الخدمة', 'تحت الصيانة', 'موقوفة'];

    // ── المعدّات (المشغّلة حالياً) مجمّعة حسب المورد ─────────────────────────
    $operations_has_company = db_table_has_column($conn, 'operations', 'company_id');
    $ops_company_clause = ($operations_has_company && !$isSuper) ? ' AND o.company_id = ' . $companyId : '';

    $ops_q = mysqli_query($conn, "
        SELECT o.id AS op_id, o.status AS op_status,
               o.start, o.end, o.equipment_category,
               e.id AS eq_id, e.code AS eq_code, e.name AS eq_name,
               e.type AS eq_type_id, e.serial_number, e.chassis_number,
               e.manufacturer, e.model, e.manufacturing_year,
               e.equipment_condition, e.availability_status,
               e.engine_condition, e.operating_hours, e.general_notes AS eq_notes,
               COALESCE(et.type, '') AS type_name,
               COALESCE(s.id, 0) AS supplier_id,
               COALESCE(s.name, 'بدون مورد') AS supplier_name
        FROM operations o
        JOIN equipments e ON o.equipment = e.id
        LEFT JOIN equipments_types et ON CAST(e.type AS UNSIGNED) = et.id
        LEFT JOIN suppliers s ON CAST(o.supplier_id AS UNSIGNED) = s.id
        WHERE CAST(o.project_id AS UNSIGNED) = $projectId
          AND o.status = 1
          $ops_company_clause
        ORDER BY supplier_name ASC, e.code ASC
    ");

    $suppliers_data = [];
    if ($ops_q) {
        while ($op = mysqli_fetch_assoc($ops_q)) {
            $sup_id   = intval($op['supplier_id']);
            $sup_name = $op['supplier_name'];
            $avail    = $op['availability_status'] ?? '';
            $is_working = !in_array($avail, $stopped_states);
            $op['is_working'] = $is_working;
            $op['drivers']    = [];

            if (!isset($suppliers_data[$sup_id])) {
                $suppliers_data[$sup_id] = [
                    'supplier_id'   => $sup_id,
                    'supplier_name' => $sup_name,
                    'equipments'    => [],
                ];
            }
            $suppliers_data[$sup_id]['equipments'][$op['op_id']] = $op;
        }
    }

    // ── المشغّلون لكل معدة ───────────────────────────────────────────────────
    $eq_drivers_has_company = db_table_has_column($conn, 'equipment_drivers', 'company_id');
    $drivers_company_clause = ($eq_drivers_has_company && !$isSuper) ? ' AND ed.company_id = ' . $companyId : '';

    $all_eq_ids = [];
    foreach ($suppliers_data as $sup) {
        foreach ($sup['equipments'] as $op) {
            $all_eq_ids[] = intval($op['eq_id']);
        }
    }

    if (!empty($all_eq_ids)) {
        $eq_ids_str = implode(',', array_unique($all_eq_ids));
        $drv_q = mysqli_query($conn, "
            SELECT ed.equipment_id, ed.start_date, ed.end_date,
                   d.id AS driver_id, d.name AS driver_name, d.driver_code,
                   d.phone, d.skill_level, d.license_type, d.years_in_field,
                   d.years_on_equipment, d.driver_status, d.employment_affiliation,
                   d.specialized_equipment
            FROM equipment_drivers ed
            JOIN drivers d ON ed.driver_id = d.id
            WHERE ed.equipment_id IN ($eq_ids_str)
              AND ed.status = 1
              AND d.status = 1
              $drivers_company_clause
            ORDER BY ed.equipment_id ASC, d.name ASC
        ");

        $eq_drivers_map = [];
        if ($drv_q) {
            while ($dr = mysqli_fetch_assoc($drv_q)) {
                $eq_id = intval($dr['equipment_id']);
                if (!isset($eq_drivers_map[$eq_id])) {
                    $eq_drivers_map[$eq_id] = [];
                }
                $eq_drivers_map[$eq_id][] = $dr;
            }
        }

        foreach ($suppliers_data as $sup_id => &$sup) {
            foreach ($sup['equipments'] as $op_id => &$op) {
                $eq_id = intval($op['eq_id']);
                $op['drivers'] = $eq_drivers_map[$eq_id] ?? [];
            }
            unset($op);
        }
        unset($sup);
    }

    // ── ساعات التشغيل من التايم شيت ─────────────────────────────────────────
    $ts_has_company = db_table_has_column($conn, 'timesheet', 'company_id');
    $ts_company_clause = ($ts_has_company && !$isSuper) ? ' AND t.company_id = ' . $companyId : '';

    $all_op_ids_ts = [];
    foreach ($suppliers_data as $sup_id => &$sup) {
        foreach ($sup['equipments'] as $op_id => &$op) {
            $op['ts_total'] = 0.0;
            $op['ts_today'] = 0.0;
            $all_op_ids_ts[] = intval($op_id);
        }
        unset($op);
    }
    unset($sup);

    if (!empty($all_op_ids_ts)) {
        $op_ids_ts_str = implode(',', array_unique($all_op_ids_ts));
        $ts_q = mysqli_query($conn, "
            SELECT CAST(t.operator AS UNSIGNED) AS op_id,
                   SUM(t.total_work_hours) AS total_hours,
                   SUM(CASE WHEN t.date = CURDATE() THEN t.total_work_hours ELSE 0 END) AS today_hours
            FROM timesheet t
            WHERE CAST(t.operator AS UNSIGNED) IN ($op_ids_ts_str)
              AND t.status = 1
              $ts_company_clause
            GROUP BY t.operator
        ");
        if ($ts_q) {
            $ts_map = [];
            while ($ts_row = mysqli_fetch_assoc($ts_q)) {
                $ts_map[intval($ts_row['op_id'])] = [
                    'total' => floatval($ts_row['total_hours']),
                    'today' => floatval($ts_row['today_hours']),
                ];
            }
            foreach ($suppliers_data as $sup_id => &$sup) {
                foreach ($sup['equipments'] as $op_id => &$op) {
                    if (isset($ts_map[$op_id])) {
                        $op['ts_total'] = $ts_map[$op_id]['total'];
                        $op['ts_today'] = $ts_map[$op_id]['today'];
                    }
                }
                unset($op);
            }
            unset($sup);
        }
    }

    // ── الإحصائيات الإجمالية (نفس حسابات map_page.php) ───────────────────────
    $total_suppliers = count($suppliers_data);
    $totEq = board_scalar($conn, "SELECT COUNT(*) AS t FROM `equipments` WHERE id IN (SELECT operations.equipment FROM operations WHERE operations.project_id = '$projectId')");
    $wrkEq = board_scalar($conn, "SELECT COUNT(*) AS t FROM `equipments` WHERE id IN (SELECT operations.equipment FROM operations WHERE operations.project_id = '$projectId' AND operations.status='1')");
    $stoppedEq = $totEq - $wrkEq;

    $total_operators = 0;
    foreach ($suppliers_data as $sup) {
        foreach ($sup['equipments'] as $op) {
            $total_operators += count($op['drivers']);
        }
    }

    // ── بناء الإخراج ─────────────────────────────────────────────────────────
    $suppliers_out = [];
    foreach ($suppliers_data as $sup) {
        $equips_out = [];
        $sup_working = 0;
        $sup_stopped = 0;
        $sup_operators = 0;

        foreach ($sup['equipments'] as $op) {
            if ($op['is_working']) {
                $sup_working++;
            } else {
                $sup_stopped++;
            }
            $sup_operators += count($op['drivers']);

            $drivers_out = [];
            foreach ($op['drivers'] as $dr) {
                $drivers_out[] = [
                    'driver_id'          => intval($dr['driver_id']),
                    'name'               => $dr['driver_name'] ?? '',
                    'driver_code'        => $dr['driver_code'] ?? '',
                    'phone'              => $dr['phone'] ?? '',
                    'skill_level'        => $dr['skill_level'] ?? '',
                    'license_type'       => $dr['license_type'] ?? '',
                    'years_in_field'     => $dr['years_in_field'] !== null ? intval($dr['years_in_field']) : 0,
                    'years_on_equipment' => $dr['years_on_equipment'] !== null ? intval($dr['years_on_equipment']) : 0,
                ];
            }

            $equips_out[] = [
                'op_id'               => intval($op['op_id']),
                'eq_id'               => intval($op['eq_id']),
                'code'                => $op['eq_code'] ?? '',
                'name'                => $op['eq_name'] ?? '',
                'type_name'           => $op['type_name'] !== '' ? $op['type_name'] : '-',
                'is_working'          => (bool) $op['is_working'],
                'availability_status' => $op['availability_status'] ?? '',
                'manufacturer'        => $op['manufacturer'] ?? '',
                'model'               => $op['model'] ?? '',
                'serial_number'       => $op['serial_number'] ?? '',
                'chassis_number'      => $op['chassis_number'] ?? '',
                'equipment_category'  => $op['equipment_category'] ?? '',
                'ts_total'            => round(floatval($op['ts_total']), 1),
                'ts_today'            => round(floatval($op['ts_today']), 1),
                'drivers'             => $drivers_out,
            ];
        }

        $suppliers_out[] = [
            'supplier_id'   => intval($sup['supplier_id']),
            'supplier_name' => $sup['supplier_name'],
            'totals'        => [
                'total'     => count($sup['equipments']),
                'working'   => $sup_working,
                'stopped'   => $sup_stopped,
                'operators' => $sup_operators,
            ],
            'equipments'    => $equips_out,
        ];
    }

    api_ok([
        'project' => api_format_project($project),
        'stats'   => [
            'suppliers'         => $total_suppliers,
            'equipment_total'   => $totEq,
            'equipment_working' => $wrkEq,
            'equipment_stopped' => $stoppedEq,
            'operators'         => $total_operators,
        ],
        'suppliers' => $suppliers_out,
    ], 'تم جلب اللوحة الحيّة');
}

/** قراءة قيمة عددية مفردة (مكافئ dashboard_scalar في map_page.php). */
function board_scalar(mysqli $conn, string $sql): int
{
    $q = mysqli_query($conn, $sql);
    if ($q && $row = mysqli_fetch_assoc($q)) {
        return isset($row['t']) ? intval($row['t']) : 0;
    }
    return 0;
}
