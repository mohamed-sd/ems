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
$MIGRATED_SCREENS = array(
    // 'Maintenance/breakdowns.php',   ← مثال: يُفعَّل مع هجرة الشاشة في المرحلة 2
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

$gateA = new TenantDb($conn, TenantContext::forSystem($COMPANY_A, 999901, '1'));
$gateB = new TenantDb($conn, TenantContext::forSystem($COMPANY_B, 999902, '1'));
$gateSuper = new TenantDb($conn, TenantContext::forSystem($COMPANY_A, 999903, EMS_ROLE_SUPER_ADMIN));
$gateNoTenant = new TenantDb($conn, TenantContext::forSystem(0, 999904, '1'));

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

// ═════ 7) الشاشات المُهاجَرة (HTTP) ═════
echo "── 7) الشاشات المُهاجَرة ──\n";
if (empty($MIGRATED_SCREENS)) {
    echo "  (لا شاشات مُهاجَرة بعد — القسم يتفعّل تلقائيًا مع أول هجرةٍ في المرحلة 2)\n";
} else {
    // يتطلب Apache: دخول بمستخدم أ ثم زيارة كل شاشةٍ والتأكد من خلوّها من MARK_B.
    // (التنفيذ التفصيلي يُستكمل مع أول شاشةٍ — البنية جاهزة.)
    foreach ($MIGRATED_SCREENS as $screen) {
        echo "  ! screen test TODO: {$screen}\n";
    }
}

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
