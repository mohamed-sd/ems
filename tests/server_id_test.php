<?php
/**
 * اختبارات خدمة الترقيم الخادمي — ServerId (K8)
 * التشغيل: php tests/server_id_test.php            ← الحزمة الأساسية
 *          php tests/server_id_test.php --worker N ← عامل تزامنٍ داخلي (يُستدعى من الدرِل)
 * رمز الخروج: 0 أخضر · 1 فشل.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/app/Core/ServerId.php';

use App\Core\ServerId;

// ── وضع العامل (للدرِل المتوازي): يخصّص N رقمًا من نفس الـscope ويطبعها ──
if (isset($argv[1]) && $argv[1] === '--worker') {
    $n = max(1, intval($argv[2] ?? 100));
    $scope = (string) ($argv[3] ?? 'k8_conc_drill');
    $conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_APP_USER'), ems_env('DB_APP_PASS'), ems_env('DB_NAME'));
    if ($conn->connect_errno) { fwrite(STDERR, "worker: connect failed\n"); exit(1); }
    for ($i = 0; $i < $n; $i++) {
        echo ServerId::next($conn, $scope), "\n";
    }
    exit(0);
}

$PASS = 0; $FAIL = 0;
function ok($label, $cond) {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "  ✔ {$label}\n"; }
    else { $FAIL++; echo "  ✘ FAIL: {$label}\n"; }
}

echo "── 1) ULID: الصيغة والتفرّد والترتيب ──\n";
$u = ServerId::ulid();
ok('الطول 26 وأبجدية Crockford', strlen($u) === 26 && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $u) === 1);

$set = array();
for ($i = 0; $i < 20000; $i++) { $set[ServerId::ulid()] = 1; }
ok('20,000 توليدًا تسلسليًا بلا أي تكرار', count($set) === 20000);

$a = ServerId::ulid(); usleep(3000); $b = ServerId::ulid(); usleep(3000); $c = ServerId::ulid();
ok('زمنيّ الترتيب معجميًّا عبر الملي-ثواني', strcmp($a, $b) < 0 && strcmp($b, $c) < 0);

echo "── 2) correlationId: صيغة ULID (الإرث للمشتقات مسؤولية الناشر K3) ──\n";
ok('correlationId بصيغة ULID', preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', ServerId::correlationId()) === 1);

echo "── 3) idempotencyKey: حتمي ومقيّد بـ64 ──\n";
$k1 = ServerId::idempotencyKey('equipment.hour_logged', 'timesheet', 12345);
$k2 = ServerId::idempotencyKey('equipment.hour_logged', 'timesheet', 12345);
$k3 = ServerId::idempotencyKey('equipment.hour_logged', 'timesheet', 12346);
ok('نفس المدخلات = نفس المفتاح (حتمية إعادة المحاولة)', $k1 === $k2);
ok('مدخل مختلف = مفتاح مختلف', $k1 !== $k3);
ok('ضمن حد العمود 64', strlen($k1) <= 64 && $k1 === 'equipment.hour_logged:timesheet:12345');
$long = ServerId::idempotencyKey(str_repeat('x', 80), 'y', 1);
ok('الطويل يُكثَّف sha1 حتميًّا ≤64', strlen($long) <= 64 && $long === ServerId::idempotencyKey(str_repeat('x', 80), 'y', 1));

echo "── 4) next/nextNo: تسلسل ذرّي وعزل نطاقات ──\n";
$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_APP_USER'), ems_env('DB_APP_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$s1 = 'k8_test_' . getmypid() . '_a';
$s2 = 'k8_test_' . getmypid() . '_b';
$vals = array();
for ($i = 1; $i <= 5; $i++) { $vals[] = ServerId::next($conn, $s1); }
ok('تسلسل 1..5 داخل النطاق', $vals === array(1, 2, 3, 4, 5));
ok('نطاق آخر يبدأ من 1 (عزل النطاقات)', ServerId::next($conn, $s2) === 1);
ok('nextNo بصيغة PREFIX-0006', ServerId::nextNo($conn, $s1, 'EV') === 'EV-0006');
$conn->query("DELETE FROM ems_sequences WHERE scope LIKE 'k8_test_%'");

echo str_repeat('═', 50) . "\n";
echo "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n";
exit($FAIL === 0 ? 0 : 1);
