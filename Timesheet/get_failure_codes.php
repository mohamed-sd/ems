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

session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json; charset=utf-8');
include '../config.php';

$action          = isset($_GET['action'])         ? trim($_GET['action'])         : '';
$equipment_type  = isset($_GET['equipment_type']) ? intval($_GET['equipment_type']) : 0;
$event_type_code = isset($_GET['event_type_code'])? mysqli_real_escape_string($conn, trim($_GET['event_type_code'])) : '';
$main_cat_code   = isset($_GET['main_cat_code'])  ? mysqli_real_escape_string($conn, trim($_GET['main_cat_code']))  : '';
$sub_cat         = isset($_GET['sub_cat'])         ? mysqli_real_escape_string($conn, trim($_GET['sub_cat']))        : '';
$full_code       = isset($_GET['full_code'])       ? mysqli_real_escape_string($conn, trim($_GET['full_code']))      : '';

switch ($action) {

    // ── 1. أنواع الأحداث (للقائمة الأولى "نوع الحدث")
    case 'get_event_types':
        if ($equipment_type <= 0) { die(json_encode([])); }
        $res = mysqli_query($conn,
            "SELECT DISTINCT event_type_code, event_type_name
             FROM failure_codes
             WHERE equipment_type = $equipment_type AND status = 1
             ORDER BY FIELD(event_type_code,'OPR','EQF','MNT','DEP','CST','MST','HRF','MKF')"
        );
        $data = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $data[] = $row;
        }
        echo json_encode($data);
        break;

    // ── 2. الفئات الرئيسية (القائمة الثانية "قسم العطل")
    case 'get_main_cats':
        if ($equipment_type <= 0 || $event_type_code === '') { die(json_encode([])); }
        $res = mysqli_query($conn,
            "SELECT DISTINCT main_category_code, main_category_name
             FROM failure_codes
             WHERE equipment_type = $equipment_type
               AND event_type_code = '$event_type_code'
               AND status = 1
             ORDER BY main_category_name"
        );
        $data = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $data[] = $row;
        }
        echo json_encode($data);
        break;

    // ── 3. الفئات الفرعية (القائمة الثالثة "الجزء / السبب")
    case 'get_sub_cats':
        if ($equipment_type <= 0 || $event_type_code === '' || $main_cat_code === '') { die(json_encode([])); }
        $res = mysqli_query($conn,
            "SELECT DISTINCT sub_category
             FROM failure_codes
             WHERE equipment_type = $equipment_type
               AND event_type_code = '$event_type_code'
               AND main_category_code = '$main_cat_code'
               AND status = 1
             ORDER BY sub_category"
        );
        $data = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $data[] = $row['sub_category'];
        }
        echo json_encode($data);
        break;

    // ── 4. تفاصيل العطل (القائمة الرابعة "التفصيل")
    case 'get_details':
        if ($equipment_type <= 0 || $event_type_code === '' || $main_cat_code === '' || $sub_cat === '') { die(json_encode([])); }
        $res = mysqli_query($conn,
            "SELECT id, failure_detail, full_code
             FROM failure_codes
             WHERE equipment_type = $equipment_type
               AND event_type_code = '$event_type_code'
               AND main_category_code = '$main_cat_code'
               AND sub_category = '$sub_cat'
               AND status = 1
             ORDER BY failure_detail"
        );
        $data = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $data[] = $row;
        }
        echo json_encode($data);
        break;

    // ── 5. جلب بيانات صف كامل بالكود (لتعبئة حقول التعديل)
    case 'get_by_code':
        if ($full_code === '') { die(json_encode(null)); }
        $res = mysqli_query($conn,
            "SELECT * FROM failure_codes WHERE full_code = '$full_code' AND status = 1 LIMIT 1"
        );
        $row = mysqli_fetch_assoc($res);
        echo json_encode($row ?: null);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
        break;
}
