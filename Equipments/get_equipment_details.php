<?php
session_start();
while (ob_get_level()) ob_end_clean();
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('normalize_equipment_availability_state')) {
    function normalize_equipment_availability_state($availability_state, $availability_status)
    {
        $availability_state = trim((string) $availability_state);
        $availability_status = trim((string) $availability_status);

        if ($availability_state === 'متوفرة' || $availability_state === 'غير متوفرة') {
            return $availability_state;
        }

        if ($availability_status === '' || $availability_status === 'متاحة للعمل' || $availability_status === 'قيد الاستخدام') {
            return 'متوفرة';
        }

        return 'غير متوفرة';
    }
}

if (!function_exists('normalize_equipment_availability_status')) {
    function normalize_equipment_availability_status($availability_state, $availability_status)
    {
        $availability_state = normalize_equipment_availability_state($availability_state, $availability_status);
        $availability_status = trim((string) $availability_status);

        if ($availability_state === 'متوفرة') {
            return 'قيد الاستخدام';
        }

        $legacy_map = [
            'موقوفة للصيانة' => 'تحت الصيانة',
            'مبيعة/مسحوبة' => 'مسحوبة',
            'معطلة مؤقتاً' => 'معطلة'
        ];

        if (isset($legacy_map[$availability_status])) {
            return $legacy_map[$availability_status];
        }

        $valid_statuses = ['تحت الصيانة', 'محجوزة', 'مسحوبة', 'في المستودع', 'معطلة'];
        if (in_array($availability_status, $valid_statuses, true)) {
            return $availability_status;
        }

        return 'تحت الصيانة';
    }
}

$equipments_has_machine_number = db_table_has_column($conn, 'equipments', 'machine_number');
$equipments_has_document_type = db_table_has_column($conn, 'equipments', 'document_type');
$equipments_has_site_supervisor_name = db_table_has_column($conn, 'equipments', 'site_supervisor_name');
$equipments_has_site_supervisor_contact = db_table_has_column($conn, 'equipments', 'site_supervisor_contact');
$equipments_has_availability_state = db_table_has_column($conn, 'equipments', 'availability_state');
$equipments_has_company_id = db_table_has_column($conn, 'equipments', 'company_id');
$equipments_has_model_id = db_table_has_column($conn, 'equipments', 'model_id');
$operations_has_project_id = db_table_has_column($conn, 'operations', 'project_id');
$operations_has_project = db_table_has_column($conn, 'operations', 'project');
$operations_project_col = $operations_has_project_id ? 'project_id' : ($operations_has_project ? 'project' : '');
$operations_has_mine_id = db_table_has_column($conn, 'operations', 'mine_id');
$operations_has_status = db_table_has_column($conn, 'operations', 'status');
$mines_has_mine_name = db_table_has_column($conn, 'mines', 'mine_name');
$mines_has_name = db_table_has_column($conn, 'mines', 'name');

$current_company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die(json_encode(['success' => false, 'message' => 'معرف المعدة مطلوب']));
}

$equipment_id = intval($_GET['id']);

// بوابة العزل — نقطة JSON بلا تمييز سوبر أصلًا (الأصل يعزل كلَّ مَن له شركة>0)؛
// البوابة تعزل بشركة السياق وتُغلق مغلقًا عند غيابها (أشدّ من الأصل الذي كان يمرّر).
$det_gate = ems_tenant_db();

// جلب جميع بيانات المعدة (العزل عبر {TENANT_SCOPE}؛ et كتالوج عام لا يُعلَن)
$fleet_model_select = $equipments_has_model_id
    ? ", fm.code AS fleet_model_code, fm.model_name AS fleet_model_name"
    : "";
$fleet_model_join = $equipments_has_model_id
    ? " LEFT JOIN fleet_model fm ON fm.id = e.model_id"
    : "";
$det_enrich = array('s' => 'suppliers');
if ($equipments_has_model_id) { $det_enrich['fm'] = 'fleet_model'; }

try {
    $det_rows = $det_gate->scopedQuery(array(
        'scope'  => array('e' => 'equipments'),
        'enrich' => $det_enrich,
    ), "
    SELECT
        e.*,
        et.type AS equipment_type_name,
        s.name AS supplier_name$fleet_model_select
    FROM equipments e
    LEFT JOIN suppliers s ON e.suppliers = s.id
    LEFT JOIN equipments_types et ON et.id = e.type$fleet_model_join
    WHERE {TENANT_SCOPE} AND e.id = ?
    LIMIT 1
", array($equipment_id));
} catch (\Throwable $t) {
    die(json_encode(['success' => false, 'message' => 'خطأ في الاستعلام']));
}

if (empty($det_rows)) {
    die(json_encode(['success' => false, 'message' => 'المعدة غير موجودة']));
}

$equipment = $det_rows[0];
$equipment['project_name'] = '';
$equipment['mine_name'] = '';

// جلب آخر تشغيل مرتبط بالمعدة بشكل مرن حسب بنية الجدول
if (($operations_project_col !== '' || $operations_has_mine_id) && db_table_has_column($conn, 'operations', 'equipment')) {
    $opsSelect = [];
    if ($operations_project_col !== '') {
        $opsSelect[] = "o.$operations_project_col AS op_project_id";
    }
    if ($operations_has_mine_id) {
        $opsSelect[] = "o.mine_id AS op_mine_id";
    }

    if (!empty($opsSelect)) {
        // آخر تشغيلٍ للمعدة — معزولًا (الأصل كان بلا عزل شركةٍ هنا: تسرّبٌ كامن أُغلق).
        // أعمدة البوابة معرِّفاتٌ صرفة، فتُجلَب بأسمائها وتُسمّى op_* في PHP.
        $ops_cols = array();
        if ($operations_project_col !== '') { $ops_cols[] = $operations_project_col; }
        if ($operations_has_mine_id) { $ops_cols[] = 'mine_id'; }
        $ops_where = "equipment = ?";
        if ($operations_has_status) { $ops_where .= " AND status = '1'"; }
        $opsRowRaw = $det_gate->selectOne('operations', array(
            'columns'  => $ops_cols,
            'whereRaw' => $ops_where,
            'params'   => array($equipment_id),
            'orderBy'  => 'id DESC',
        ));
        if ($opsRowRaw !== null) {
            $opsRow = array();
            if ($operations_project_col !== '' && array_key_exists($operations_project_col, $opsRowRaw)) {
                $opsRow['op_project_id'] = $opsRowRaw[$operations_project_col];
            }
            if ($operations_has_mine_id && array_key_exists('mine_id', $opsRowRaw)) {
                $opsRow['op_mine_id'] = $opsRowRaw['mine_id'];
            }

            $opProjectId = isset($opsRow['op_project_id']) ? intval($opsRow['op_project_id']) : 0;
            if ($opProjectId > 0) {
                $pRow = $det_gate->selectOne('project', array(
                    'columns' => array('name'),
                    'where'   => array('id' => $opProjectId),
                ));
                if ($pRow !== null) {
                    $equipment['project_name'] = isset($pRow['name']) ? $pRow['name'] : '';
                }
            }

            $opMineId = isset($opsRow['op_mine_id']) ? intval($opsRow['op_mine_id']) : 0;
            if ($opMineId > 0) {
                $mineNameCol = $mines_has_mine_name ? 'mine_name' : ($mines_has_name ? 'name' : '');
                if ($mineNameCol !== '') {
                    // فرعٌ خاملٌ عمليًّا (جدول mines محذوف فتفشل فحوص الأعمدة أعلاه)؛
                    // إن عاد الجدول يومًا فغير المسجَّل في العقد يُرفَض مغلقًا (try تحفظ الـJSON).
                    try {
                        $mRow = $det_gate->selectOne('mines', array(
                            'columns' => array($mineNameCol),
                            'where'   => array('id' => $opMineId),
                        ));
                        if ($mRow !== null) {
                            $equipment['mine_name'] = isset($mRow[$mineNameCol]) ? $mRow[$mineNameCol] : '';
                        }
                    } catch (\Throwable $t) { /* غير مسجَّل بالعقد = يبقى الاسم فارغًا */ }
                }
            }
        }
    }
}
$equipment['machine_number'] = $equipments_has_machine_number && isset($equipment['machine_number']) ? $equipment['machine_number'] : '';
$equipment['document_type'] = $equipments_has_document_type && isset($equipment['document_type']) ? $equipment['document_type'] : '';
$equipment['site_supervisor_name'] = $equipments_has_site_supervisor_name && isset($equipment['site_supervisor_name']) ? $equipment['site_supervisor_name'] : '';
$equipment['site_supervisor_contact'] = $equipments_has_site_supervisor_contact && isset($equipment['site_supervisor_contact']) ? $equipment['site_supervisor_contact'] : '';
$equipment['availability_state'] = normalize_equipment_availability_state(
    $equipments_has_availability_state && isset($equipment['availability_state']) ? $equipment['availability_state'] : '',
    isset($equipment['availability_status']) ? $equipment['availability_status'] : ''
);
$equipment['availability_status'] = normalize_equipment_availability_status(
    $equipment['availability_state'],
    isset($equipment['availability_status']) ? $equipment['availability_status'] : ''
);

// ملخّص تنبيهات الوثائق (كرت المعدة) — للعرض في نافذة التفاصيل
$equipment['docs_expired'] = 0;
$equipment['docs_soon'] = 0;
$equipment['docs_critical_expired'] = 0;
if (db_table_has_column($conn, 'fleet_equipment_compliance', 'id')) {
    $today = date('Y-m-d');
    // تجميعٌ (SUM تعبيرية) عبر scopedQuery — العزل مسؤولية البوابة، والتواريخ معاملات.
    try {
        $cRows = $det_gate->scopedQuery(array(
            'scope' => array('fec' => 'fleet_equipment_compliance'),
        ), "SELECT
            SUM(fec.expiry_date IS NOT NULL AND fec.expiry_date <> '0000-00-00' AND fec.expiry_date < ?) AS expired,
            SUM(fec.expiry_date IS NOT NULL AND fec.expiry_date >= ? AND fec.expiry_date <= DATE_ADD(?, INTERVAL 30 DAY)) AS soon,
            SUM(fec.expiry_date IS NOT NULL AND fec.expiry_date <> '0000-00-00' AND fec.expiry_date < ? AND fec.is_critical = 1) AS crit
        FROM fleet_equipment_compliance fec WHERE {TENANT_SCOPE} AND fec.equipment_id = ? AND fec.is_deleted = 0",
            array($today, $today, $today, $today, $equipment_id));
        if (!empty($cRows)) {
            $equipment['docs_expired'] = intval($cRows[0]['expired']);
            $equipment['docs_soon'] = intval($cRows[0]['soon']);
            $equipment['docs_critical_expired'] = intval($cRows[0]['crit']);
        }
    } catch (\Throwable $t) { /* تبقى الأصفار الافتراضية */ }
}

echo json_encode([
    'success' => true,
    'data' => $equipment
]);
?>
