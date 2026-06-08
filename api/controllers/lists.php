<?php
/**
 * api/controllers/lists.php — قوائم مساعدة لنماذج الإضافة.
 *   GET /api/contracts          عقود المشروع النشطة
 *   GET /api/suppliers          موردو الشركة النشطون
 *   GET /api/equipment-types    أنواع المعدات النشطة
 *   GET /api/equipments?type=&supplier=   المعدات المتاحة (بلا تشغيل ساري)
 *
 * @package EMS\Api
 */

if (!defined('EMS_API')) {
    http_response_code(403);
    exit('Forbidden');
}

/** GET /api/contracts — عقود المشروع النشطة (الأحدث أولاً). */
function lists_contracts(): void
{
    global $conn;
    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    api_fetch_project($ctx, $projectId);

    $has_is_deleted = db_table_has_column($conn, 'contracts', 'is_deleted');
    $deletedClause = $has_is_deleted ? ' AND c.is_deleted = 0' : '';

    $stmt = mysqli_prepare(
        $conn,
        "SELECT c.id, c.contract_signing_date, c.project_id
         FROM contracts c
         WHERE c.project_id = ? AND c.status = 1$deletedClause
         ORDER BY c.contract_signing_date DESC, c.id DESC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $projectId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $date = (string)($r['contract_signing_date'] ?? '');
            $out[] = [
                'id'                    => intval($r['id']),
                'contract_signing_date' => $date,
                'label'                 => 'عقد ' . intval($r['id']) . ($date !== '' ? ' · ' . $date : ''),
            ];
        }
    }
    mysqli_stmt_close($stmt);

    api_ok(['contracts' => $out], 'تم جلب العقود');
}

/** GET /api/suppliers — موردو الشركة النشطون. */
function lists_suppliers(): void
{
    global $conn;
    $ctx = api_require_auth();
    $projectId = api_resolve_project_id($ctx);
    api_fetch_project($ctx, $projectId);

    $has_company = db_table_has_column($conn, 'suppliers', 'company_id');
    $has_is_deleted = db_table_has_column($conn, 'suppliers', 'is_deleted');

    $where = ['s.status = 1'];
    if ($has_is_deleted) {
        $where[] = 's.is_deleted = 0';
    }
    if (!$ctx['is_super'] && $has_company) {
        $where[] = 's.company_id = ' . intval($ctx['company_id']);
    }
    $whereSql = implode(' AND ', $where);

    $res = mysqli_query($conn, "SELECT s.id, s.name FROM suppliers s WHERE $whereSql ORDER BY s.name ASC");
    $out = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $out[] = ['id' => intval($r['id']), 'name' => $r['name'] ?? ''];
        }
    }

    api_ok(['suppliers' => $out], 'تم جلب الموردين');
}

/** GET /api/equipment-types — أنواع المعدات النشطة. */
function lists_equipment_types(): void
{
    global $conn;
    api_require_auth();

    $statusClause = db_table_has_column($conn, 'equipments_types', 'status') ? " WHERE status = 'active'" : '';
    $res = mysqli_query($conn, "SELECT id, type FROM equipments_types$statusClause ORDER BY type ASC");
    $out = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $out[] = ['id' => intval($r['id']), 'type' => $r['type'] ?? ''];
        }
    }

    api_ok(['equipment_types' => $out], 'تم جلب أنواع المعدات');
}

/** GET /api/equipments?type=&supplier= — المعدات المتاحة (بلا تشغيل ساري). */
function lists_equipments(): void
{
    global $conn;
    $ctx = api_require_auth();
    api_resolve_project_id($ctx);

    $typeFilter = api_int('type', 0);
    $supplierFilter = api_int('supplier', 0);

    $has_company = db_table_has_column($conn, 'equipments', 'company_id');

    $where = ['e.status = 1'];
    if (!$ctx['is_super'] && $has_company) {
        $where[] = 'e.company_id = ' . intval($ctx['company_id']);
    }
    if ($typeFilter > 0) {
        $where[] = 'CAST(e.type AS UNSIGNED) = ' . $typeFilter;
    }
    if ($supplierFilter > 0) {
        $where[] = 'CAST(e.suppliers AS UNSIGNED) = ' . $supplierFilter;
    }
    // استبعاد المعدات التي لها تشغيل ساري (منع التشغيل المزدوج).
    $where[] = 'NOT EXISTS (SELECT 1 FROM operations o WHERE o.equipment = e.id AND o.status = 1)';
    $whereSql = implode(' AND ', $where);

    $sql = "SELECT e.id, e.code, e.name, e.type AS type_id, e.suppliers AS supplier_id,
                   COALESCE(et.type, '') AS type_name,
                   COALESCE(s.name, '') AS supplier_name
            FROM equipments e
            LEFT JOIN equipments_types et ON CAST(e.type AS UNSIGNED) = et.id
            LEFT JOIN suppliers s ON CAST(e.suppliers AS UNSIGNED) = s.id
            WHERE $whereSql
            ORDER BY e.code ASC";
    $res = mysqli_query($conn, $sql);
    $out = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $out[] = [
                'id'            => intval($r['id']),
                'code'          => $r['code'] ?? '',
                'name'          => $r['name'] ?? '',
                'type_id'       => intval($r['type_id']),
                'type_name'     => $r['type_name'] ?? '',
                'supplier_id'   => intval($r['supplier_id']),
                'supplier_name' => $r['supplier_name'] ?? '',
            ];
        }
    }

    api_ok(['equipments' => $out], 'تم جلب المعدات المتاحة');
}
