<?php
/**
 * حزمة اختبارات تسرّب المستأجر — Tenant Leak Test Suite (ADR-02 · المخرَج ⑥)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/tenant_leak_test.php
 * رمز الخروج: 0 = كل الاختبارات خضراء · 1 = تسريب/فشل (يوقف أي اعتماد).
 *
 * المنهج: شركتا اختبارٍ مؤقتتان + بيانات مميّزة العلامات، ثم محاولات اختراقٍ
 * منهجية عبر البوابة: قراءة عابرة، تزوير هوية، كتابة على صفوف الغير، حذف،
 * جداول غير مسجَّلة/مقيَّدة، أبناء عبر آباءٍ غير مملوكين، تجاوز غير مصرَّح.
 * كل شيء يُنشأ يُنظَّف في النهاية (teardown كامل حتى عند الفشل).
 *
 * قاعدة الاعتماد (معيار §4 بند ②): كل شاشةٍ تُهاجَر للبوابة تُضاف إلى
 * $MIGRATED_SCREENS أدناه — القسم الشاشي يزور كل شاشةٍ بجلسة الشركة أ
 * ويؤكد خلوّ الاستجابة من علامات الشركة ب.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';

use App\Core\TenantContext;
use App\Core\TenantDb;
use App\Core\TenantGateException;

// ── الشاشات المُهاجَرة للبوابة (تُضاف مع كل هجرة — إلزامي قبل الاعتماد) ─────
// T2 (خطة K9 §2): لكل شاشة: مسارها، مستخدم دخولٍ حقيقي يراها، وبذرتا علامةٍ
// تُزرعان خامًا (شركة المستخدم mine / شركة أخرى other) في جدولها الرئيس —
// القسم 7 يزور الشاشة بجلسةٍ فعلية ويؤكد ظهور mine وغياب other ثم ينظّف.
$MIGRATED_SCREENS = array(
    'Opportunities/opportunities.php' => array(
        'login_user' => 13,          // مبيعات (دور 12) — يملك عرض الفرص
        'user_company' => 4,
        'other_company' => 1,
        'table' => 'opportunities',
        'row' => function ($companyId, $mark) {
            return "INSERT INTO opportunities (company_id, opp_code, title, stage, created_by)
                    VALUES ({$companyId}, '{$mark}', '{$mark}', 'جديدة', 1)";
        },
        'cleanup' => "DELETE FROM opportunities WHERE opp_code LIKE 'LEAKTEST_%'",
    ),
    'Procurement/items_proc.php' => array(
        'login_user' => 71, 'user_company' => 4, 'other_company' => 1,
        'table' => 'proc_item',
        'row' => function ($companyId, $mark) {
            return "INSERT INTO proc_item (company_id, name, code) VALUES ({$companyId}, '{$mark}', '{$mark}')";
        },
        'cleanup' => "DELETE FROM proc_item WHERE name LIKE 'LEAKTEST_%'",
    ),
    'Procurement/suppliers_proc.php' => array(
        'login_user' => 71, 'user_company' => 4, 'other_company' => 1,
        'table' => 'proc_supplier',
        'row' => function ($companyId, $mark) {
            return "INSERT INTO proc_supplier (company_id, name, code) VALUES ({$companyId}, '{$mark}', '{$mark}')";
        },
        'cleanup' => "DELETE FROM proc_supplier WHERE name LIKE 'LEAKTEST_%'",
    ),
    'Procurement/orders_proc.php' => array(
        'login_user' => 71, 'user_company' => 4, 'other_company' => 1,
        'table' => 'proc_order',
        'row' => function ($companyId, $mark) {
            /* ◆ قادحُ `trg_po_request_required` يشترط طلبًا مرتبطًا (INJ-0335) —
                 فصفُّ الجسِّ يحمل طلبًا حقيقيًّا من شركتِه لا فراغًا. */
            $pr = 0;
            $rr = $GLOBALS['conn']->query("SELECT id FROM proc_request WHERE company_id = {$companyId} ORDER BY id LIMIT 1");
            if ($rr && ($rx = $rr->fetch_row())) { $pr = (int) $rx[0]; }
            if ($pr === 0) {
                $rr2 = $GLOBALS['conn']->query('SELECT id FROM proc_request ORDER BY id LIMIT 1');
                if ($rr2 && ($rx2 = $rr2->fetch_row())) { $pr = (int) $rx2[0]; }
            }
            return "INSERT INTO proc_order (company_id, code, request_id) VALUES ({$companyId}, '{$mark}', {$pr})";
        },
        'cleanup' => "DELETE FROM proc_order WHERE code LIKE 'LEAKTEST_%'",
    ),
    'Procurement/requests_proc.php' => array(
        'login_user' => 71, 'user_company' => 4, 'other_company' => 1,
        'table' => 'proc_request',
        'row' => function ($companyId, $mark) {
            return "INSERT INTO proc_request (company_id, code, need_source, op_classification) VALUES ({$companyId}, '{$mark}', 'سوق', 'استهلاكية')";
        },
        'cleanup' => "DELETE FROM proc_request WHERE code LIKE 'LEAKTEST_%'",
    ),
    // ── K9-M2b: شاشات النقل الستة (u78 دور 23 شركة 4) — FKs تشير لصفوفٍ قائمة
    //    (type 1 / locations 3,4)؛ FK لا يفرض تطابق الشركة فزرع الشركة الأخرى ممكن.
    'Transport/transfer_orders_list.php' => array(
        'login_user' => 78, 'user_company' => 4, 'other_company' => 1,
        'table' => 'transfer_orders',
        'row' => function ($companyId, $mark) {
            return "INSERT INTO transfer_orders (company_id, order_no, transfer_type_id, direction, source_module, from_location_id, to_location_id, request_date)
                    VALUES ({$companyId}, '{$mark}', 1, 'direct', 'operations', 3, 4, CURDATE())";
        },
        'cleanup' => "DELETE FROM transfer_orders WHERE order_no LIKE 'LEAKTEST_%'",
    ),
    'Transport/transfer_dashboard.php' => array( // الكانبان (stage=request الافتراضية) يعرض order_no
        'login_user' => 78, 'user_company' => 4, 'other_company' => 1,
        'table' => 'transfer_orders',
        'row' => function ($companyId, $mark) {
            return "INSERT INTO transfer_orders (company_id, order_no, transfer_type_id, direction, source_module, from_location_id, to_location_id, request_date)
                    VALUES ({$companyId}, '{$mark}', 1, 'direct', 'operations', 3, 4, CURDATE())";
        },
        'cleanup' => "DELETE FROM transfer_orders WHERE order_no LIKE 'LEAKTEST_%'",
    ),
    'Transport/transfer_requests.php' => array(
        'login_user' => 78, 'user_company' => 4, 'other_company' => 1,
        'table' => 'transfer_requests',
        'row' => function ($companyId, $mark) {
            return "INSERT INTO transfer_requests (company_id, code, transfer_type_id, source_module, reason)
                    VALUES ({$companyId}, '{$mark}', 1, 'operations', '{$mark}')";
        },
        'cleanup' => "DELETE FROM transfer_requests WHERE code LIKE 'LEAKTEST_%'",
    ),
    'Transport/transfer_types_config.php' => array(
        'login_user' => 78, 'user_company' => 4, 'other_company' => 1,
        'table' => 'transfer_types',
        'row' => function ($companyId, $mark) {
            return "INSERT INTO transfer_types (company_id, code, name, operational_category)
                    VALUES ({$companyId}, '{$mark}', '{$mark}', 'equipment_transfer')";
        },
        'cleanup' => "DELETE FROM transfer_types WHERE code LIKE 'LEAKTEST_%'",
    ),
    'Transport/trs_locations_config.php' => array(
        'login_user' => 78, 'user_company' => 4, 'other_company' => 1,
        'table' => 'trs_locations',
        'row' => function ($companyId, $mark) {
            return "INSERT INTO trs_locations (company_id, code, name, location_type)
                    VALUES ({$companyId}, '{$mark}', '{$mark}', 'base')";
        },
        'cleanup' => "DELETE FROM trs_locations WHERE code LIKE 'LEAKTEST_%'",
    ),
    'Transport/transfer_cost_rules_config.php' => array( // العلامة في basis_note (النص الحر الوحيد المعروض)
        'login_user' => 78, 'user_company' => 4, 'other_company' => 1,
        'table' => 'transfer_cost_rules',
        'row' => function ($companyId, $mark) {
            return "INSERT INTO transfer_cost_rules (company_id, movement_type, default_bearer, basis_note)
                    VALUES ({$companyId}, 'mob', 'client', '{$mark}')";
        },
        'cleanup' => "DELETE FROM transfer_cost_rules WHERE basis_note LIKE 'LEAKTEST_%'",
    ),
);

// ── عدّة التقرير ─────────────────────────────────────────────────────────────
$PASS = 0;
$FAIL = 0;
function ok($label, $cond)
{
    global $PASS, $FAIL;
    if ($cond) {
        $PASS++;
        echo "  ✔ {$label}\n";
    } else {
        $FAIL++;
        echo "  ✘ FAIL: {$label}\n";
    }
}
function expect_throw($label, $fn)
{
    try {
        $fn();
        ok($label . ' (توقعنا رفضًا فمرّ!)', false);
    } catch (TenantGateException $e) {
        ok($label, true);
    }
}

$MARK_A = 'LEAKTEST_A_' . getmypid();
$MARK_B = 'LEAKTEST_B_' . getmypid();
$cleanup = array(); // [table, id] صفوف تُحذف خامًا في النهاية

// ═════ Setup: شركتا اختبار ═════
mysqli_query($conn, "INSERT INTO admin_companies (name, email, status) VALUES ('{$MARK_A}_CO', '{$MARK_A}@leaktest.local', 'active')");
$COMPANY_A = intval($conn->insert_id);
mysqli_query($conn, "INSERT INTO admin_companies (name, email, status) VALUES ('{$MARK_B}_CO', '{$MARK_B}@leaktest.local', 'active')");
$COMPANY_B = intval($conn->insert_id);

if ($COMPANY_A <= 0 || $COMPANY_B <= 0) {
    fwrite(STDERR, "FATAL: تعذّر إنشاء شركتي الاختبار\n");
    exit(1);
}
echo "شركتا الاختبار: A={$COMPANY_A} · B={$COMPANY_B}\n\n";

// الأوضاع صريحة: هذه الأقسام تختبر ميكانيكا fail-closed ذاتها، لا وضع البيئة —
// فمع بدء طرح K9 صار .env يحمل EMS_TENANT_GATE=monitor والافتراض يتبعه (بالتصميم).
$gateA = new TenantDb($conn, TenantContext::forSystem($COMPANY_A, 999901, '1'), false, 'enforce');
$gateB = new TenantDb($conn, TenantContext::forSystem($COMPANY_B, 999902, '1'), false, 'enforce');
$gateSuper = new TenantDb($conn, TenantContext::forSystem($COMPANY_A, 999903, EMS_ROLE_SUPER_ADMIN), false, 'enforce');
$gateNoTenant = new TenantDb($conn, TenantContext::forSystem(0, 999904, '1'), false, 'enforce');

try {

// ═════ 1) العزل قراءةً وكتابةً على جدول مستأجرٍ (clients) ═════
echo "── 1) عزل القراءة/الكتابة (clients) ──\n";
$idA = $gateA->insert('clients', array('client_name' => $MARK_A, 'client_code' => $MARK_A));
$cleanup[] = array('clients', $idA);
$rawRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT company_id FROM clients WHERE id = {$idA}"));
ok('الإدراج حقن company_id للشركة أ آليًا', intval($rawRow['company_id']) === $COMPANY_A);

$idB = $gateB->insert('clients', array('client_name' => $MARK_B, 'client_code' => $MARK_B));
$cleanup[] = array('clients', $idB);

$seenByA = array_map(function ($r) { return $r['client_name']; }, $gateA->select('clients', array('columns' => array('client_name'))));
ok('الشركة أ ترى علامتها', in_array($MARK_A, $seenByA, true));
ok('الشركة أ لا ترى علامة ب إطلاقًا', !in_array($MARK_B, $seenByA, true));
ok('selectOne بمعرّف صف ب من بوابة أ = null', $gateA->selectOne('clients', array('where' => array('id' => $idB))) === null);
ok('count معزول (أ ترى صفها فقط ضمن العلامات)', $gateA->count('clients', array('whereRaw' => "client_name LIKE 'LEAKTEST_%'")) === 1);

// ═════ 2) محاولات الاختراق الكتابية ═════
echo "── 2) الكتابة العابرة والتزوير ──\n";
expect_throw('تزوير الهوية: insert بـ company_id مغاير يُرفض',
    function () use ($gateA, $COMPANY_B, $MARK_A) {
        $gateA->insert('clients', array('client_name' => $MARK_A . '_forged', 'client_code' => 'x', 'company_id' => $COMPANY_B));
    });
ok('update صف ب من بوابة أ = صفر صفوف', $gateA->update('clients', array('client_name' => 'HACKED'), array('id' => $idB)) === 0);
$rowB = mysqli_fetch_assoc(mysqli_query($conn, "SELECT client_name FROM clients WHERE id = {$idB}"));
ok('صف ب سليم لم يُمس', $rowB['client_name'] === $MARK_B);
ok('softDelete صف ب من بوابة أ = صفر صفوف', $gateA->softDelete('clients', $idB) === 0);
expect_throw('تغيير company_id عبر update يُرفض',
    function () use ($gateA, $idA, $COMPANY_B) {
        $gateA->update('clients', array('company_id' => $COMPANY_B), array('id' => $idA));
    });
expect_throw('whereRaw يتلاعب بـ company_id يُرفض',
    function () use ($gateA, $COMPANY_B) {
        $gateA->select('clients', array('whereRaw' => "company_id = {$COMPANY_B}"));
    });
expect_throw('update بلا شرط يُرفض', function () use ($gateA) {
    $gateA->update('clients', array('client_name' => 'x'), array());
});

// ═════ 3) الحذف الناعم ═════
echo "── 3) الحذف الناعم ──\n";
ok('softDelete صفّي أنا = صف واحد', $gateA->softDelete('clients', $idA) === 1);
ok('المحذوف يختفي من القراءة الافتراضية', $gateA->selectOne('clients', array('where' => array('id' => $idA))) === null);
$withDeleted = $gateA->selectOne('clients', array('where' => array('id' => $idA), 'includeDeleted' => true));
ok('ويظهر مع includeDeleted (أرشفة لا حذف)', $withDeleted !== null && intval($withDeleted['is_deleted']) === 1);

// ═════ 4) سجل الجداول — Fail-Closed ═════
echo "── 4) السجل والرفض المغلق ──\n";
expect_throw('جدول غير مسجَّل يُرفض', function () use ($gateA) {
    $gateA->select('no_such_table_xyz');
});
expect_throw('جدول مقيَّد (schema_migrations) يُرفض', function () use ($gateA) {
    $gateA->select('schema_migrations');
});
expect_throw('حقن معرّفٍ خبيث يُرفض', function () use ($gateA) {
    $gateA->select('clients; DROP TABLE clients');
});
ok('مرجع عام (roles) مقروء', count($gateA->select('roles', array('limit' => 1))) === 1);
expect_throw('كتابة مرجعٍ عام من غير المدير الأعلى تُرفض', function () use ($gateA) {
    $gateA->insert('roles', array('name' => 'x'));
});
expect_throw('سياق بلا شركة يُرفض (fail-closed)', function () use ($gateNoTenant) {
    $gateNoTenant->select('clients');
});

// ═════ 5) الأبناء عبر الآباء ═════
echo "── 5) عزل الأبناء عبر آبائهم ──\n";
$tplA = $gateA->insert('mnt_inspection_template', array(
    'type_code' => 'LT', 'name' => $MARK_A, 'inspection_type' => 'leaktest',
    'header_type' => 'none', 'condition_scale' => 'none', 'sort_order' => 999,
));
$cleanup[] = array('mnt_inspection_template', $tplA);
$lineA = $gateA->insert('mnt_inspection_template_line', array(
    'template_id' => $tplA, 'seq' => 1, 'item' => $MARK_A,
));
$cleanup[] = array('mnt_inspection_template_line', $lineA);
ok('ابنٌ لأبٍ مملوك يُدرج', $lineA > 0);
expect_throw('إدراج ابنٍ لأبٍ من شركةٍ أخرى يُرفض',
    function () use ($gateB, $tplA, $MARK_B) {
        $gateB->insert('mnt_inspection_template_line', array('template_id' => $tplA, 'seq' => 2, 'item' => $MARK_B));
    });
$linesSeenByB = $gateB->select('mnt_inspection_template_line', array('whereRaw' => "item LIKE 'LEAKTEST_%'"));
ok('الشركة ب لا ترى سطور قوالب أ', count($linesSeenByB) === 0);

// ═════ 6) التجاوز الصريح للمدير الأعلى ═════
echo "── 6) forAllTenants (الاستثناء المرئي) ──\n";
expect_throw('التجاوز من دورٍ عادي يُرفض', function () use ($gateA) {
    $gateA->forAllTenants('leak test');
});
$logBefore = filesize(dirname(__DIR__) . '/logs/security.log');
$all = $gateSuper->forAllTenants('leak-test-audit');
$names = array_map(function ($r) { return $r['client_name']; },
    $all->select('clients', array('whereRaw' => "client_name LIKE 'LEAKTEST_%'", 'includeDeleted' => true)));
ok('المدير الأعلى المتجاوز يرى علامتي الشركتين', in_array($MARK_A, $names, true) && in_array($MARK_B, $names, true));
clearstatcache();
ok('كل تجاوزٍ مُسجَّل (tenant_gate_cross_tenant)', filesize(dirname(__DIR__) . '/logs/security.log') > $logBefore);

// ═════ 6ب) وضع المراقبة log-only (K1 — خطة المرحلة 1 §7-2) ═════
echo "── 6ب) وضع المراقبة log-only ──\n";
$gateMonA = new TenantDb($conn, TenantContext::forSystem($COMPANY_A, 999905, '1'), false, 'monitor');
$gateMonNoTenant = new TenantDb($conn, TenantContext::forSystem(0, 999906, '1'), false, 'monitor');
$secLog = dirname(__DIR__) . '/logs/security.log';

// m1: الافتراض يتبع .env بالتصميم (نمط CSRF) — منذ طرح K9 المفتاح monitor،
// فالاختبار الثابت بيئيًا: enforce الصريح يرفض المقيَّد (fail-closed ميكانيكيًا).
$gateDefault = new TenantDb($conn, TenantContext::forSystem($COMPANY_A, 999907, '1'), false, 'enforce');
expect_throw('m1: enforce الصريح — المقيَّد يُرفض (ميكانيكا fail-closed سليمة)', function () use ($gateDefault) {
    $gateDefault->select('schema_migrations', array('limit' => 1));
});

// m2: monitor — الجدول المقيَّد يُقرأ (عبور مُسجَّل) بدل الرفض.
$before = filesize($secLog); clearstatcache();
$rows = $gateMonA->select('schema_migrations', array('limit' => 1));
ok('m2: monitor يمرّر قراءة المقيَّد (عبورًا لا حجبًا)', is_array($rows));
clearstatcache();
ok('m3: العبور مُسجَّل tenant_gate_would_deny', filesize($secLog) > $before
    && strpos(file_get_contents($secLog), 'tenant_gate_would_deny') !== false);

// m4: monitor بلا شركة في السياق — القراءة تمرّ بلا نطاق (سلوك ما قبل الهجرة): يرى العلامتين.
$namesMon = array_map(function ($r) { return $r['client_name']; },
    $gateMonNoTenant->select('clients', array('whereRaw' => "client_name LIKE 'LEAKTEST_%'", 'includeDeleted' => true)));
ok('m4: monitor بلا سياقٍ يمرّر القراءة بلا نطاق (يرى A وB معًا)',
    in_array($MARK_A, $namesMon, true) && in_array($MARK_B, $namesMon, true));

// m5: المسار السوي المعزول مطابق تمامًا في monitor — أ لا ترى ب.
$seenMonA = array_map(function ($r) { return $r['client_name']; },
    $gateMonA->select('clients', array('columns' => array('client_name'), 'includeDeleted' => true)));
ok('m5: monitor بسياقٍ سويٍّ معزولٌ كـ enforce (أ لا ترى ب)',
    !in_array($MARK_B, $seenMonA, true));

// m6: حرّاس الكتابة تُرمى دائمًا حتى في monitor (تزوير الهوية + update بلا شرط).
expect_throw('m6a: تزوير الهوية يُرفض حتى في monitor', function () use ($gateMonA, $COMPANY_B, $MARK_A) {
    $gateMonA->insert('clients', array('client_name' => $MARK_A . '_mforge', 'client_code' => 'x', 'company_id' => $COMPANY_B));
});
expect_throw('m6b: update عبر عبور المراقبة يُرفض (strict)', function () use ($gateMonA) {
    $gateMonA->update('schema_migrations', array('status' => 'x'), array('id' => 0));
});

// m7: مسار الإنفاذ يغلب monitor — تسجيل المسار في القائمة يعيد fail-closed.
TenantDb::$enforcePathsOverride = array('/tests/tenant_leak_test');
$_SERVER['SCRIPT_NAME'] = '/tests/tenant_leak_test.php';
expect_throw('m7: TENANT_ENFORCE_PATHS يغلب monitor (fail-closed)', function () use ($gateMonA) {
    $gateMonA->select('schema_migrations', array('limit' => 1));
});
TenantDb::$enforcePathsOverride = null;
unset($_SERVER['SCRIPT_NAME']);

// ═════ 6جـ) قناة replaceChildren — اختبارات تسرّبٍ خاصة (شرط الاستعمال المسبق) ═════
echo "── 6جـ) replaceChildren (نمط استبدال الأبناء — قناة مقيدة) ──\n";
/* ◆ والقادحُ نفسُه هنا: صفُّ الجسِّ يحمل طلبًا قائمًا */
$__prAny = 0;
$__rq = $GLOBALS['conn']->query('SELECT id FROM proc_request ORDER BY id LIMIT 1');
if ($__rq && ($__rx = $__rq->fetch_row())) { $__prAny = (int) $__rx[0]; }
$poA = $gateA->insert('proc_order', array('request_id' => $__prAny));
$cleanup[] = array('proc_order', $poA);
$poB = $gateB->insert('proc_order', array('request_id' => $__prAny));
$cleanup[] = array('proc_order', $poB);
$gateA->replaceChildren('proc_order', $poA, 'proc_order_line', 'order_id',
    array(array('item_name' => $MARK_A . '_L1'), array('item_name' => $MARK_A . '_L2')));
$gateB->replaceChildren('proc_order', $poB, 'proc_order_line', 'order_id',
    array(array('item_name' => $MARK_B . '_L1')));

// c1: الاستبدال المشروع يعمل ذرّيًا (القديم يُزال والجديد يُدرج بعدّه الدقيق)
$r = $gateA->replaceChildren('proc_order', $poA, 'proc_order_line', 'order_id',
    array(array('item_name' => $MARK_A . '_NEW1'), array('item_name' => $MARK_A . '_NEW2'), array('item_name' => $MARK_A . '_NEW3')));
$namesA = array_map(function ($x) { return $x['item_name']; },
    $gateA->select('proc_order_line', array('where' => array('order_id' => $poA))));
ok('c1: الاستبدال المشروع (حذف 2 → إدراج 3، القديم زال)', $r['deleted'] === 2 && $r['inserted'] === 3
    && count($namesA) === 3 && !in_array($MARK_A . '_L1', $namesA, true) && in_array($MARK_A . '_NEW1', $namesA, true));

// c2: أبٌ غير مملوك (أب B عبر بوابة A) يُرفض — وسطور B لا تُمسّ
expect_throw('c2: أبٌ من شركةٍ أخرى يُرفض (النطاق المزدوج)', function () use ($gateA, $poB, $MARK_A) {
    $gateA->replaceChildren('proc_order', $poB, 'proc_order_line', 'order_id',
        array(array('item_name' => $MARK_A . '_HACK')));
});
$linesB = $gateB->select('proc_order_line', array('where' => array('order_id' => $poB)));
ok('c3: سطور الشركة الأخرى سليمة لم تُمسّ', count($linesB) === 1 && $linesB[0]['item_name'] === $MARK_B . '_L1');

// c4: الذرّية — صفٌّ فاسد وسط الدفعة ⇒ لا شيء يتغير (القديم باقٍ بعدّه)
expect_throw('c4a: دفعة فيها صفٌّ فاسد تُرفض كاملةً', function () use ($gateA, $poA, $MARK_A) {
    $gateA->replaceChildren('proc_order', $poA, 'proc_order_line', 'order_id',
        array(array('item_name' => $MARK_A . '_X1'), array('no_such_column' => 'boom')));
});
$namesA2 = array_map(function ($x) { return $x['item_name']; },
    $gateA->select('proc_order_line', array('where' => array('order_id' => $poA))));
ok('c4b: الذرّية صانت القديم (3 أسطر NEW كما كانت — rollback كامل)',
    count($namesA2) === 3 && in_array($MARK_A . '_NEW2', $namesA2, true) && !in_array($MARK_A . '_X1', $namesA2, true));

// c5: الأثر مسجَّل (تحذف بيانات — أثرٌ إلزامي)
clearstatcache();
ok('c5: كل استبدالٍ مسجَّل (tenant_gate_replace_children)',
    strpos(file_get_contents(dirname(__DIR__) . '/logs/security.log'), 'tenant_gate_replace_children') !== false);
mysqli_query($conn, "DELETE FROM proc_order_line WHERE item_name LIKE 'LEAKTEST_%'");

// ═════ 6د) runInTransaction — المعاملة المُدارة (شروط المستخدم الثلاثة) ═════
echo "── 6د) runInTransaction (ذرّية مشتركة بحراسة كاملة) ──\n";

// t1: النجاح + الترابط: أبٌ ثم سطران ثم صفٌّ يستعمل line_id الوليد — الكل يُرتكب معًا
$txIds = $gateA->runInTransaction(function ($g) use ($MARK_A, $__prAny) {
    $po = $g->insert('proc_order', array('code' => $MARK_A . '_TX', 'request_id' => $__prAny));
    $l1 = $g->insert('proc_order_line', array('order_id' => $po, 'item_name' => $MARK_A . '_TXL1'));
    // الترابط: اسم السطر الثاني يحمل line_id الوليد للأول (كنمط custody←line_id)
    $l2 = $g->insert('proc_order_line', array('order_id' => $po, 'item_name' => $MARK_A . '_TXL2_of_' . $l1));
    return array($po, $l1, $l2);
}, 'leaktest t1');
$cleanup[] = array('proc_order', $txIds[0]);
$linked = $gateA->selectOne('proc_order_line', array('where' => array('id' => $txIds[2])));
ok('t1: المعاملة ارتُكبت والترابط عمل (line_id الوليد مرئي داخلها)',
    $txIds[1] > 0 && $linked !== null && $linked['item_name'] === $MARK_A . '_TXL2_of_' . $txIds[1]);

// t2: فشل وسط الـcallable = تراجع الكل (الأب المدرَج أولًا يختفي)
$t2_parent = null;
expect_throw('t2a: فشلٌ في العملية الثانية يُرمى', function () use ($gateA, $MARK_A, &$t2_parent, $__prAny) {
    $gateA->runInTransaction(function ($g) use ($MARK_A, &$t2_parent, $__prAny) {
        $t2_parent = $g->insert('proc_order', array('code' => $MARK_A . '_TXFAIL', 'request_id' => $__prAny));
        $g->insert('proc_order_line', array('order_id' => $t2_parent, 'no_such_column' => 'boom'));
    }, 'leaktest t2');
});
$gone = intval($conn->query("SELECT COUNT(*) FROM proc_order WHERE id=" . intval($t2_parent))->fetch_row()[0]);
ok('t2b: الأب المُدرَج قبل الفشل تراجع كليًا (ذرّية مشتركة)', $t2_parent > 0 && $gone === 0);

// t3: الحراسة لا تتعلق داخل المعاملة: تزوير شركةٍ داخل tx يُرفض ويتراجع الكل
$t3_parent = null;
expect_throw('t3a: تزوير الهوية داخل المعاملة يُرفض (الحُرّاس أحياء)', function () use ($gateA, $COMPANY_B, $MARK_A, &$t3_parent, $__prAny) {
    $gateA->runInTransaction(function ($g) use ($COMPANY_B, $MARK_A, &$t3_parent, $__prAny) {
        $t3_parent = $g->insert('proc_order', array('code' => $MARK_A . '_TXFORGE', 'request_id' => $__prAny));
        $g->insert('proc_order_line', array('order_id' => $t3_parent, 'item_name' => 'x', 'company_id' => $COMPANY_B));
    }, 'leaktest t3');
});
$gone3 = intval($conn->query("SELECT COUNT(*) FROM proc_order WHERE id=" . intval($t3_parent))->fetch_row()[0]);
ok('t3b: كل المعاملة تراجعت بعد رفض التزوير', $t3_parent > 0 && $gone3 === 0);

// t4: replaceChildren داخل المعاملة تتبنّاها (لا commit ضمني متداخل):
// استبدالٌ ناجح ثم فشلٌ بعده ⇒ حتى أثر الاستبدال يتراجع
$before4 = array_map(function ($x) { return $x['item_name']; },
    $gateA->select('proc_order_line', array('where' => array('order_id' => $txIds[0]))));
expect_throw('t4a: فشلٌ بعد replaceChildren داخل المعاملة يُرمى', function () use ($gateA, $txIds, $MARK_A) {
    $gateA->runInTransaction(function ($g) use ($txIds, $MARK_A) {
        $g->replaceChildren('proc_order', $txIds[0], 'proc_order_line', 'order_id',
            array(array('item_name' => $MARK_A . '_TXNEW')));
        $g->insert('proc_order_line', array('order_id' => $txIds[0], 'no_such_column' => 'boom'));
    }, 'leaktest t4');
});
$after4 = array_map(function ($x) { return $x['item_name']; },
    $gateA->select('proc_order_line', array('where' => array('order_id' => $txIds[0]))));
sort($before4); sort($after4);
ok('t4b: أثر replaceChildren تراجع مع المعاملة (السطور الأصلية سليمة — لا commit ضمني)',
    $before4 === $after4 && !in_array($MARK_A . '_TXNEW', $after4, true));

// t5: التداخل مرفوض
expect_throw('t5: runInTransaction داخل أخرى يُرفض', function () use ($gateA) {
    $gateA->runInTransaction(function ($g) {
        $g->runInTransaction(function ($g2) { return 1; });
    });
});
mysqli_query($conn, "DELETE FROM proc_order_line WHERE item_name LIKE 'LEAKTEST_%'");

// ═════ 6هـ) scopedQuery — الاستعلام المركّب المعزول (K9-D) ═════
echo "── 6هـ) scopedQuery (العزل النافذ بنيويًا وتجريبيًا) ──\n";
// بذور تجميع لشركتين: حركتا مخزونٍ لكلٍّ
$it_a = $gateA->insert('proc_item', array('name' => $MARK_A . '_SQI'));
$cleanup[] = array('proc_item', $it_a);
$it_b = $gateB->insert('proc_item', array('name' => $MARK_B . '_SQI'));
$cleanup[] = array('proc_item', $it_b);
foreach (array(array($gateA, $it_a, 5), array($gateA, $it_a, 3), array($gateB, $it_b, 70)) as $seed) {
    $seed[0]->insert('proc_stock_move', array('item_id' => $seed[1], 'move_type' => 'استلام', 'qty' => $seed[2], 'ref_type' => 'leaktest', 'ref_id' => 0));
}

// e1: تجميع GROUP BY حقيقي عبر شركتين — لا يظهر إلا مجموع شركتي
$agg = $gateA->scopedQuery(
    array('scope' => array('m' => 'proc_stock_move'), 'enrich' => array('it' => 'proc_item')),
    "SELECT it.name AS iname, SUM(m.qty) AS q
     FROM proc_stock_move m
     LEFT JOIN proc_item it ON it.id = m.item_id
     WHERE {TENANT_SCOPE} AND m.ref_type = ?
     GROUP BY m.item_id, it.name ORDER BY iname",
    array('leaktest')
);
ok('e1: التجميع معزول (صنف A بمجموع 8، وB غائب كليًا)',
    count($agg) === 1 && floatval($agg[0]['q']) === 8.0 && $agg[0]['iname'] === $MARK_A . '_SQI');

// e2..e6: ضمانات «النافذ لا النصي»
expect_throw('e2: بلا رمز = رفض', function () use ($gateA) {
    $gateA->scopedQuery(array('scope' => array('m' => 'proc_stock_move')),
        "SELECT COUNT(*) c FROM proc_stock_move m WHERE m.ref_type='x'");
});
expect_throw('e3: الرمز بعد GROUP BY = رفض (لا يعزل المصادر)', function () use ($gateA) {
    $gateA->scopedQuery(array('scope' => array('m' => 'proc_stock_move')),
        "SELECT m.item_id, COUNT(*) c FROM proc_stock_move m WHERE m.ref_type='x' GROUP BY m.item_id HAVING {TENANT_SCOPE}");
});
expect_throw('e4: company_id يدوي = رفض', function () use ($gateA) {
    $gateA->scopedQuery(array('scope' => array('m' => 'proc_stock_move')),
        "SELECT COUNT(*) c FROM proc_stock_move m WHERE {TENANT_SCOPE} AND m.company_id = 1");
});
expect_throw('e5: جدول مستأجر غير معلَن = رفض', function () use ($gateA) {
    $gateA->scopedQuery(array('scope' => array('m' => 'proc_stock_move')),
        "SELECT COUNT(*) c FROM proc_stock_move m JOIN proc_item it ON it.id=m.item_id WHERE {TENANT_SCOPE}");
});
expect_throw('e6: جدول إثراء بغير LEFT JOIN = رفض', function () use ($gateA) {
    $gateA->scopedQuery(array('scope' => array('m' => 'proc_stock_move'), 'enrich' => array('it' => 'proc_item')),
        "SELECT COUNT(*) c FROM proc_stock_move m INNER JOIN proc_item it ON it.id=m.item_id WHERE {TENANT_SCOPE}");
});
expect_throw('e7: UNION = رفض', function () use ($gateA) {
    $gateA->scopedQuery(array('scope' => array('m' => 'proc_stock_move')),
        "SELECT m.id FROM proc_stock_move m WHERE {TENANT_SCOPE} UNION SELECT 1");
});
mysqli_query($conn, "DELETE FROM proc_stock_move WHERE ref_type='leaktest'");

// ═════ 6و) forSystem — سياق النظام المحوكم (القدرة الخامسة) ═════
echo "── 6و) forSystem (شركة واحدة لكل دورة · قناة محروسة · مسجَّلة) ──\n";
// f1: دورتان معزولتان — A لا ترى/لا تكتب في B والعكس (قراءةً وكتابةً)
$sysA = new TenantDb($conn, TenantContext::forSystem($COMPANY_A, 0, ''), false, 'enforce');
$sysB = new TenantDb($conn, TenantContext::forSystem($COMPANY_B, 0, ''), false, 'enforce');
$sA = $sysA->insert('clients', array('client_name' => $MARK_A . '_SYS', 'client_code' => $MARK_A . '_SYS'));
$cleanup[] = array('clients', $sA);
ok('f1a: كتابة دورة النظام A حُقنت بشركتها', intval($conn->query("SELECT company_id FROM clients WHERE id={$sA}")->fetch_row()[0]) === $COMPANY_A);
$seenB = array_map(function ($r) { return $r['client_name']; },
    $sysB->select('clients', array('whereRaw' => "client_name LIKE 'LEAKTEST_%'", 'includeDeleted' => true)));
ok('f1b: دورة النظام B لا ترى ما كتبته دورة A', !in_array($MARK_A . '_SYS', $seenB, true));
ok('f1c: تحديث B لصف دورة A = صفر صفوف', $sysB->update('clients', array('client_name' => 'X'), array('id' => $sA)) === 0);

// f2: القناة محروسة — محاكاة مسار ويب (sapiOverride) بلا تفويض = رفض؛ بتفويض cron مُتحقق = قبول
TenantContext::$sapiOverride = 'fpm-fcgi';
expect_throw('f2a: forSystem من مسار ويب بلا تفويض نظامٍ يُرفض', function () use ($COMPANY_A) {
    TenantContext::forSystem($COMPANY_A);
});
$okAuth = TenantContext::forSystem($COMPANY_A, 0, '', true); // كما يمرّره حارس cron بعد hash_equals
ok('f2b: بتفويض cron المتحقق يُقبل', $okAuth instanceof TenantContext && $okAuth->companyId() === $COMPANY_A);
TenantContext::$sapiOverride = null;

// f3: الأثر مسجَّل (granted وREFUSED كلاهما)
clearstatcache();
$logTail = file_get_contents(dirname(__DIR__) . '/logs/security.log');
ok('f3: إنشاء سياق النظام مسجَّل (granted + REFUSED)',
    strpos($logTail, 'tenant_gate_system_context') !== false
    && strpos($logTail, 'REFUSED (no system authorization)') !== false);

// ═════ 7) الشاشات المُهاجَرة (HTTP — T2) ═════
echo "── 7) الشاشات المُهاجَرة (زيارة فعلية بجلسة) ──\n";
if (empty($MIGRATED_SCREENS)) {
    echo "  (لا شاشات مُهاجَرة بعد)\n";
} else {
    foreach ($MIGRATED_SCREENS as $screenPath => $cfg) {
        $mineMark  = 'LEAKTEST_MINE_' . getmypid();
        $otherMark = 'LEAKTEST_OTHER_' . getmypid();
        $rowSql = $cfg['row'];
        mysqli_query($conn, $rowSql(intval($cfg['user_company']), $mineMark));
        mysqli_query($conn, $rowSql(intval($cfg['other_company']), $otherMark));

        // دخول فعلي بتبديل hash مؤقت مضمون الاسترجاع
        $uid = intval($cfg['login_user']);
        $u = $conn->query("SELECT username, password FROM users WHERE id={$uid}")->fetch_assoc();
        $origHash = $u ? $u['password'] : '';
        if (!$u || strlen($origHash) < 50) {
            ok("شاشة {$screenPath}: مستخدم الدخول {$uid} صالح", false);
            continue;
        }
        $temp = bin2hex(random_bytes(12));
        $tmpHash = password_hash($temp, PASSWORD_BCRYPT);
        $st = $conn->prepare("UPDATE users SET password=? WHERE id={$uid}");
        $st->bind_param('s', $tmpHash); $st->execute(); $st->close();
        try {
            $jar = tempnam(sys_get_temp_dir(), 'lk7');
            $req = function ($url, $post = null) use ($jar) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true,
                    CURLOPT_COOKIEJAR=>$jar, CURLOPT_COOKIEFILE=>$jar, CURLOPT_TIMEOUT=>40,
                    CURLOPT_USERAGENT=>'EMS-LeakTest-T2']);
                if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
                $b = curl_exec($ch); $i = curl_getinfo($ch); curl_close($ch);
                return [$i['http_code'], $b === false ? '' : $b, $i['url']];
            };
            list(, $lb) = $req('http://localhost/ems/login.php');
            preg_match('/name="csrf_token"\s+value="([^"]+)"/', $lb, $lm);
            list(, , $lf) = $req('http://localhost/ems/login.php',
                ['username' => $u['username'], 'password' => $temp, 'csrf_token' => $lm[1]]);
            ok("شاشة {$screenPath}: الدخول نجح", strpos($lf, 'login.php') === false);
            list($sc, $sb, $sf) = $req('http://localhost/ems/' . ltrim($screenPath, '/'));
            ok("شاشة {$screenPath}: تُعرض (200 بلا طرد)", $sc === 200 && strpos($sf, 'login.php') === false);
            ok("شاشة {$screenPath}: ترى علامة شركتها", strpos($sb, $mineMark) !== false);
            ok("شاشة {$screenPath}: لا ترى علامة الشركة الأخرى إطلاقًا", strpos($sb, $otherMark) === false);
        } finally {
            $st = $conn->prepare("UPDATE users SET password=? WHERE id={$uid}");
            $st->bind_param('s', $origHash); $st->execute(); $st->close();
            $back = $conn->query("SELECT password FROM users WHERE id={$uid}")->fetch_row()[0];
            ok("شاشة {$screenPath}: hash المستخدم أُعيد بايتًا ببايت", $back === $origHash);
            mysqli_query($conn, $cfg['cleanup']);
        }
    }
}

// ═════ 6ز) حارس الحصانة — دفتر الأحداث (§12 · A0) ═════
echo "── 6ز) حارس الحصانة: الحدث المنشور لا يُعدَّل/يُحذَف عبر البوابة ──\n";
// (ز1) حدثٌ يدوي (idempotency_key = NULL) — قابلٌ للتعديل والحذف الناعم عبر البوابة
$manId = $gateA->insert('fin_financial_events', array(
    'event_no' => $MARK_A . '_MAN', 'event_type' => 'expense', 'source_module' => 'finance',
    'source_ref' => $MARK_A, 'amount' => 100.00, 'currency' => 'SDG', 'state' => 'draft', 'created_by' => 999901,
));
$cleanup[] = array('fin_financial_events', $manId);
$aff = $gateA->update('fin_financial_events', array('amount' => 150.00), array('id' => $manId));
ok('ز1: الحدث اليدوي (بلا مفتاح) قابلٌ للتعديل عبر البوابة', $aff === 1
    && floatval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT amount FROM fin_financial_events WHERE id=$manId"))['amount']) === 150.00);
$aff2 = $gateA->softDelete('fin_financial_events', $manId);
ok('ز1: الحدث اليدوي قابلٌ للحذف الناعم عبر البوابة', $aff2 === 1
    && intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT is_deleted FROM fin_financial_events WHERE id=$manId"))['is_deleted']) === 1);

// (ز2) حدثُ ناقلٍ منشور (idempotency_key موسوم) — التعديل والحذف مرفوضان
mysqli_query($conn, "INSERT INTO fin_financial_events (company_id, event_no, event_type, source_module, source_ref, amount, currency, state, created_by, idempotency_key, correlation_id)
    VALUES ({$COMPANY_A}, '{$MARK_A}_BUS', 'expense', 'finance', '{$MARK_A}', 50.00, 'SDG', 'draft', 999901, 'equipment.hour_logged:timesheet:999999:a999999', '01LEAKTESTCORRELATIONXXXXXX')");
$busId = intval($conn->insert_id);
$cleanup[] = array('fin_financial_events', $busId);
expect_throw('ز2: تعديل حدثٍ منشورٍ مرفوض (حصانة §12)', function () use ($gateA, $busId) {
    $gateA->update('fin_financial_events', array('amount' => 9999.00), array('id' => $busId));
});
expect_throw('ز2: حذف حدثٍ منشورٍ مرفوض (حصانة §12)', function () use ($gateA, $busId) {
    $gateA->softDelete('fin_financial_events', $busId);
});
$busRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT amount, is_deleted FROM fin_financial_events WHERE id=$busId"));
ok('ز2: الحدث المنشور بقي سليمًا (المبلغ 50، غير محذوف)',
    floatval($busRow['amount']) === 50.00 && intval($busRow['is_deleted']) === 0);
// (ز3) الحصانة تسري حتى على السوبر (عن التعديل لا عن العزل)
expect_throw('ز3: السوبر أيضًا يُرفض تعديله للحدث المنشور', function () use ($gateSuper, $busId) {
    $gateSuper->update('fin_financial_events', array('amount' => 1.00), array('id' => $busId));
});

// (ز4) فصل المضمون عن حالة المعالجة (immutable_allow) — المعيار المتبع في
// Event Sourcing/Outbox: الحقيقة المنشورة لا تتغيّر، ودورتها تسير.
// بدونه يولد كل حدثٍ من بوابة D05 مجمَّدًا فلا تبدأ دورة D04 ولا يُشتقّ الطلب.
$advanced = $gateA->update('fin_financial_events', array('state' => 'dept_review'), array('id' => $busId));
ok('ز4: تقدّم الحالة على حدثٍ منشور يمرّ (state ضمن immutable_allow)', $advanced === 1
    && mysqli_fetch_assoc(mysqli_query($conn, "SELECT state FROM fin_financial_events WHERE id=$busId"))['state'] === 'dept_review');
$pair = $gateA->update('fin_financial_events', array('state' => 'posted', 'journal_entry_id' => 4242), array('id' => $busId));
ok('ز4: الزوج الكتابي (state + journal_entry_id) يمرّ — ترحيل القيد', $pair === 1
    && mysqli_fetch_assoc(mysqli_query($conn, "SELECT journal_entry_id FROM fin_financial_events WHERE id=$busId"))['journal_entry_id'] === '4242');
expect_throw('ز4: المضمون ما زال محصَّنًا (المبلغ وحده)', function () use ($gateA, $busId) {
    $gateA->update('fin_financial_events', array('amount' => 7777.00), array('id' => $busId));
});
expect_throw('ز4: التحديث المختلط مرفوض كليًا (state مع amount — لا تهريب)', function () use ($gateA, $busId) {
    $gateA->update('fin_financial_events', array('state' => 'closed', 'amount' => 7777.00), array('id' => $busId));
});
expect_throw('ز4: تهريبٌ عبر عمودٍ مرجعي مرفوض (state مع equipment_id)', function () use ($gateA, $busId) {
    $gateA->update('fin_financial_events', array('state' => 'closed', 'equipment_id' => 5), array('id' => $busId));
});
expect_throw('ز4: الحذف الناعم ما زال مرفوضًا (is_deleted خارج القائمة)', function () use ($gateA, $busId) {
    $gateA->softDelete('fin_financial_events', $busId);
});
$guardRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT amount, equipment_id, state, is_deleted FROM fin_financial_events WHERE id=$busId"));
ok('ز4: بعد كل محاولات التهريب — المضمون سليمٌ والحالة وحدها تحرّكت',
    floatval($guardRow['amount']) === 50.00 && $guardRow['equipment_id'] === null
    && $guardRow['state'] === 'posted' && intval($guardRow['is_deleted']) === 0);

} catch (\Throwable $e) {
    $FAIL++;
    echo "  ✘ استثناء غير متوقع: " . $e->getMessage() . "\n";
}

// ═════ Teardown كامل ═════
foreach ($cleanup as $c) {
    mysqli_query($conn, "DELETE FROM `{$c[0]}` WHERE id = " . intval($c[1]));
}
mysqli_query($conn, "DELETE FROM clients WHERE client_name LIKE 'LEAKTEST_%'");
mysqli_query($conn, "DELETE FROM mnt_inspection_template_line WHERE item LIKE 'LEAKTEST_%'");
mysqli_query($conn, "DELETE FROM mnt_inspection_template WHERE name LIKE 'LEAKTEST_%'");
mysqli_query($conn, "DELETE FROM admin_companies WHERE email LIKE '%@leaktest.local'");
echo "\nteardown: نُظِّفت كل بيانات الاختبار.\n";

echo str_repeat('═', 50) . "\n";
echo "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n";
exit($FAIL === 0 ? 0 : 1);
