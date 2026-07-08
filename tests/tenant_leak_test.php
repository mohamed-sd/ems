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
$poA = $gateA->insert('proc_order', array());
$cleanup[] = array('proc_order', $poA);
$poB = $gateB->insert('proc_order', array());
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
