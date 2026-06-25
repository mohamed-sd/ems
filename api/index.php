<?php
/**
 * api/index.php — المتحكّم الأمامي الموحّد لطبقة REST API.
 *
 * يوجّه الطلبات إلى الدوال المعالِجة حسب (الطريقة + المسار). كل الردود JSON
 * موحّدة { success, message, data }. المصادقة عبر Bearer Token.
 *
 * أمثلة:
 *   POST /ems/api/login
 *   GET  /ems/api/board
 *   POST /ems/api/operations
 *   PUT  /ems/api/operations/{op_id}
 *   POST /ems/api/equipment-drivers
 *   PUT  /ems/api/equipment-drivers/{rel_id}
 *   GET  /ems/api/drivers/available?equipment_id=
 *
 * @package EMS\Api
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/controllers/auth.php';
require_once __DIR__ . '/controllers/board.php';
require_once __DIR__ . '/controllers/operations.php';
require_once __DIR__ . '/controllers/employees.php';
require_once __DIR__ . '/controllers/lists.php';
require_once __DIR__ . '/controllers/timesheet.php';
require_once __DIR__ . '/controllers/sync.php';

// ── CORS (تطبيق جوّال/أدوات اختبار) — توكن لا كوكيز، فلا خطر على الجلسة ──────
if (!headers_sent()) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Max-Age: 86400');
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/** استخراج أجزاء المسار من الطلب (يدعم rewrite و PATH_INFO و ?route=). */
function api_route_segments(): array
{
    $route = '';

    if (isset($_GET['route']) && $_GET['route'] !== '') {
        $route = (string) $_GET['route'];
    } elseif (!empty($_SERVER['PATH_INFO'])) {
        $route = (string) $_SERVER['PATH_INFO'];
    } else {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $uri = explode('?', $uri)[0];
        $pos = strpos($uri, '/api/');
        if ($pos !== false) {
            $route = substr($uri, $pos + 5);
        } elseif (substr($uri, -4) === '/api') {
            $route = '';
        }
    }

    $route = trim($route, '/');
    if (strpos($route, 'index.php') === 0) {
        $route = ltrim(substr($route, strlen('index.php')), '/');
    }
    if ($route === '') {
        return [];
    }
    return array_map('rawurldecode', explode('/', $route));
}

$seg = api_route_segments();
$resource = $seg[0] ?? '';
$id = isset($seg[1]) ? $seg[1] : null;
$sub = isset($seg[2]) ? $seg[2] : null;

/** ردّ 405 موحّد. */
function api_method_not_allowed(): void
{
    api_fail('الطريقة غير مسموحة لهذا المسار', 405);
}

switch ($resource) {

    case '':
        api_ok([
            'name'    => 'EMS Movement & Operations API',
            'version' => '1.0',
        ], 'API يعمل ✅');
        break;

    // ── المصادقة والسياق ──────────────────────────────────────────────────
    case 'login':
        $method === 'POST' ? auth_login() : api_method_not_allowed();
        break;

    case 'logout':
        $method === 'POST' ? auth_logout() : api_method_not_allowed();
        break;

    case 'me':
        $method === 'GET' ? auth_me() : api_method_not_allowed();
        break;

    // ── اللوحة الحيّة ──────────────────────────────────────────────────────
    case 'board':
        $method === 'GET' ? board_index() : api_method_not_allowed();
        break;

    // ── التشغيلات (مشتركة بين تطبيق الحركة وتطبيق مدير الموقع) ───────────────
    case 'operations':
        if ($id === null) {
            if ($method === 'GET') {
                operations_index();
            } elseif ($method === 'POST') {
                operations_create();
            } else {
                api_method_not_allowed();
            }
        } elseif ($id === 'by-type') {
            $method === 'GET' ? timesheet_operations_by_type() : api_method_not_allowed();
        } elseif ($sub === 'drivers') {
            $method === 'GET' ? timesheet_operation_drivers((int) $id) : api_method_not_allowed();
        } elseif ($sub === 'contract-hours') {
            $method === 'GET' ? timesheet_operation_contract_hours((int) $id) : api_method_not_allowed();
        } else {
            $method === 'PUT' ? operations_update((int) $id) : api_method_not_allowed();
        }
        break;

    // ── مدير الموقع: البيانات المرجعية ───────────────────────────────────
    case 'timesheet':
        if ($id === 'refdata' && $method === 'GET') {
            timesheet_refdata();
        } else {
            api_fail('المسار غير موجود', 404);
        }
        break;

    case 'failure-codes':
        $method === 'GET' ? timesheet_failure_codes() : api_method_not_allowed();
        break;

    // ── مدير الموقع: سجلات التايم شيت ─────────────────────────────────────
    case 'timesheets':
        if ($id === null) {
            if ($method === 'GET') {
                timesheets_list();
            } elseif ($method === 'POST') {
                timesheets_create();
            } else {
                api_method_not_allowed();
            }
        } else {
            if ($method === 'GET') {
                timesheets_get((int) $id);
            } elseif ($method === 'PUT') {
                timesheets_update((int) $id);
            } elseif ($method === 'DELETE') {
                timesheets_delete((int) $id);
            } else {
                api_method_not_allowed();
            }
        }
        break;

    // ── مدير الموقع: المزامنة ─────────────────────────────────────────────
    case 'sync':
        if ($id === 'timesheets' && $method === 'POST') {
            sync_push();
        } elseif ($id === 'pull' && $method === 'GET') {
            sync_pull();
        } else {
            api_fail('المسار غير موجود', 404);
        }
        break;

    // ── تعيينات السائقين ─────────────────────────────────────────────────
    case 'equipment-drivers':
        if ($id === null) {
            $method === 'POST' ? driver_create() : api_method_not_allowed();
        } else {
            $method === 'PUT' ? driver_update((int) $id) : api_method_not_allowed();
        }
        break;

    case 'drivers':
        if ($id === 'available' && $method === 'GET') {
            drivers_available();
        } else {
            api_fail('المسار غير موجود', 404);
        }
        break;

    // ── القوائم المساعدة ─────────────────────────────────────────────────
    case 'contracts':
        $method === 'GET' ? lists_contracts() : api_method_not_allowed();
        break;

    case 'suppliers':
        $method === 'GET' ? lists_suppliers() : api_method_not_allowed();
        break;

    case 'equipment-types':
        $method === 'GET' ? lists_equipment_types() : api_method_not_allowed();
        break;

    case 'equipments':
        $method === 'GET' ? lists_equipments() : api_method_not_allowed();
        break;

    default:
        api_fail('المسار غير موجود: ' . $resource, 404);
}
