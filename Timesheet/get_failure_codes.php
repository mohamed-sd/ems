<?php
/**
 * AJAX endpoint للقوائم المنسدلة المتتالية لنظام الأعطال
 * يرجع JSON حسب action المطلوب
 *
 * Actions:
 *   get_event_types    - جلب أنواع الأحداث (EQF, MNT, DEP, CST, MST, HRF, MKF)
 *   get_main_cats      - جلب الفئات الرئيسية بناءً على event_type_code
 *   get_sub_cats       - جلب الفئات الفرعية بناءً على main_category_code
 *   get_details        - جلب تفاصيل العطل بناءً على sub_category
 *   get_by_code        - جلب بيانات صف واحد بناءً على full_code
 */

require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    while (ob_get_level()) ob_end_clean();
    die(json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE));
}

include '../config.php';

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$action          = isset($_GET['action'])         ? trim($_GET['action'])         : '';
$equipment_type  = isset($_GET['equipment_type']) ? intval($_GET['equipment_type']) : 0;
$event_type_code = isset($_GET['event_type_code'])? trim($_GET['event_type_code']) : '';
$main_cat_code   = isset($_GET['main_cat_code'])  ? trim($_GET['main_cat_code'])  : '';
$sub_cat         = isset($_GET['sub_cat'])         ? trim($_GET['sub_cat'])        : '';
$full_code       = isset($_GET['full_code'])       ? trim($_GET['full_code'])      : '';

// failure_codes جدول مرجعي عالمي (T_GLOBAL) — قراءته عبر البوابة بلا تنطيق شركة.
$gfc_gate = ems_tenant_db();

/** إزالة التكرار حفظًا للترتيب (يكافئ DISTINCT بعد ORDER BY على صفوف مرتّبة) */
function gfc_unique_rows($rows) {
    $seen = array(); $out = array();
    foreach ($rows as $r) {
        $k = json_encode(array_values($r));
        if (!isset($seen[$k])) { $seen[$k] = 1; $out[] = $r; }
    }
    return $out;
}

switch ($action) {

    // ── 1. أنواع الأحداث (للقائمة الأولى "نوع الحدث")
    case 'get_event_types':
        if ($equipment_type <= 0) { die(json_encode([])); }
        $data = [];
        try {
            $rows = $gfc_gate->select('failure_codes', array(
                'columns' => array('event_type_code', 'event_type_name'),
                'where'   => array('equipment_type' => $equipment_type, 'status' => 1),
            ));
            $data = gfc_unique_rows($rows);
            // يكافئ ORDER BY FIELD(event_type_code, ...) — غير الموجود رتبته 0 فيتقدم (سلوك FIELD نفسه)
            $gfc_order = array('OPR','EQF','MNT','DEP','CST','MST','HRF','MKF');
            usort($data, function ($a, $b) use ($gfc_order) {
                $ra = array_search($a['event_type_code'], $gfc_order, true); $ra = ($ra === false) ? 0 : $ra + 1;
                $rb = array_search($b['event_type_code'], $gfc_order, true); $rb = ($rb === false) ? 0 : $rb + 1;
                return $ra - $rb;
            });
        } catch (\Throwable $t) { error_log('get_failure_codes event_types: ' . $t->getMessage()); }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        break;

    // ── 2. الفئات الرئيسية (القائمة الثانية "قسم العطل")
    case 'get_main_cats':
        if ($equipment_type <= 0 || $event_type_code === '') { die(json_encode([])); }
        $data = [];
        try {
            $rows = $gfc_gate->select('failure_codes', array(
                'columns' => array('main_category_code', 'main_category_name'),
                'where'   => array('equipment_type' => $equipment_type, 'event_type_code' => $event_type_code, 'status' => 1),
                'orderBy' => 'main_category_name',
            ));
            $data = gfc_unique_rows($rows);
        } catch (\Throwable $t) { error_log('get_failure_codes main_cats: ' . $t->getMessage()); }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        break;

    // ── 3. الفئات الفرعية (القائمة الثالثة "الجزء / السبب")
    case 'get_sub_cats':
        if ($equipment_type <= 0 || $event_type_code === '' || $main_cat_code === '') { die(json_encode([])); }
        $data = [];
        try {
            $rows = $gfc_gate->select('failure_codes', array(
                'columns' => array('sub_category'),
                'where'   => array('equipment_type' => $equipment_type, 'event_type_code' => $event_type_code,
                                   'main_category_code' => $main_cat_code, 'status' => 1),
                'orderBy' => 'sub_category',
            ));
            foreach (gfc_unique_rows($rows) as $row) { $data[] = $row['sub_category']; }
        } catch (\Throwable $t) { error_log('get_failure_codes sub_cats: ' . $t->getMessage()); }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        break;

    // ── 4. تفاصيل العطل (القائمة الرابعة "التفصيل")
    case 'get_details':
        if ($equipment_type <= 0 || $event_type_code === '' || $main_cat_code === '' || $sub_cat === '') { die(json_encode([])); }
        $data = [];
        try {
            $data = $gfc_gate->select('failure_codes', array(
                'columns' => array('id', 'failure_detail', 'full_code'),
                'where'   => array('equipment_type' => $equipment_type, 'event_type_code' => $event_type_code,
                                   'main_category_code' => $main_cat_code, 'sub_category' => $sub_cat, 'status' => 1),
                'orderBy' => 'failure_detail',
            ));
        } catch (\Throwable $t) { error_log('get_failure_codes details: ' . $t->getMessage()); }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        break;

    // ── 5. جلب بيانات صف كامل بالكود (لتعبئة حقول التعديل)
    case 'get_by_code':
        if ($full_code === '') { die(json_encode(null)); }
        $row = null;
        try {
            $row = $gfc_gate->selectOne('failure_codes', array(
                'where' => array('full_code' => $full_code, 'status' => 1),
            ));
        } catch (\Throwable $t) { error_log('get_failure_codes by_code: ' . $t->getMessage()); }
        echo json_encode($row ?: null, JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['error' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
        break;
}
