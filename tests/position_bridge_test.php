<?php
/**
 * اختبارات جسر المنصب — K6/ADR-07
 * التشغيل: php tests/position_bridge_test.php — رمز الخروج 0/1.
 * يثبت: الجسر يمنح دور المنصب حين يصح، ويرتدّ لusers.role في كل حالة ريبة
 * (لا منصب/معطّل/محذوف/عابر للشركات) — أي أن الشاشات القائمة لا تتأثر بنيويًا.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/includes/positions.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';

use App\Core\TenantContext;
use App\Core\TenantDb;

$PASS = 0; $FAIL = 0;
function ok($label, $cond) {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "  ✔ {$label}\n"; }
    else { $FAIL++; echo "  ✘ FAIL: {$label}\n"; }
}

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_APP_USER'), ems_env('DB_APP_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$conn->set_charset('utf8mb4');

$MARK = 'K6TEST_' . getmypid();
$cleanupUsers = array(); $cleanupPos = array();

try {

echo "── 1) البنية والبوابة: positions معزول عبر TenantDb (T_TENANT + soft) ──\n";
$gateA = new TenantDb($conn, TenantContext::forSystem(4, 999908, '1'));
$posId = $gateA->insert('positions', array('name' => $MARK . '_مدير حركة', 'role_id' => 10));
$cleanupPos[] = $posId;
ok('إنشاء منصب عبر البوابة (حقن company_id آليًا)', $posId > 0);
$raw = $conn->query("SELECT company_id, role_id FROM positions WHERE id={$posId}")->fetch_assoc();
ok('العزل حُقن (company=4) والدور جسر (role_id=10)', intval($raw['company_id']) === 4 && intval($raw['role_id']) === 10);
$gateB = new TenantDb($conn, TenantContext::forSystem(5, 999909, '1'));
ok('شركة أخرى لا ترى المنصب (عزل قراءة)', $gateB->selectOne('positions', array('where' => array('id' => $posId))) === null);

echo "── 2) مستخدم اختبار (شركة 4، دور 6) ──\n";
$conn->query("INSERT INTO users (name, username, password, role, company_id, status) VALUES ('{$MARK}', '{$MARK}', 'x', '6', 4, 'active')");
$uid = intval($conn->insert_id);
$cleanupUsers[] = $uid;
ok('مستخدم اختبار أُنشئ', $uid > 0);

echo "── 3) fallback: بلا منصب = السلوك القائم حرفيًا ──\n";
$eff = ems_user_effective_role($conn, $uid);
ok('بلا منصب → role=6 source=role', $eff['role'] === '6' && $eff['source'] === 'role' && $eff['position_id'] === null);

echo "── 4) الجسر: منصب صالح يمنح دوره ──\n";
$conn->query("UPDATE users SET position_id={$posId} WHERE id={$uid}");
$eff = ems_user_effective_role($conn, $uid);
ok('منصب صالح → role=10 (دور المنصب) source=position', $eff['role'] === '10' && $eff['source'] === 'position' && $eff['position_id'] === $posId);
ok('الدور نصًا (كنمط الجلسة والمقارنات الصارمة)', $eff['role'] === '10' && $eff['role'] !== 10);

echo "── 5) كل حالات الريبة ترتدّ للسلوك القائم ──\n";
$conn->query("UPDATE positions SET is_active=0 WHERE id={$posId}");
$eff = ems_user_effective_role($conn, $uid);
ok('منصب معطّل → fallback إلى role=6', $eff['role'] === '6' && $eff['source'] === 'role');
$conn->query("UPDATE positions SET is_active=1, is_deleted=1, deleted_at=NOW() WHERE id={$posId}");
$eff = ems_user_effective_role($conn, $uid);
ok('منصب محذوف ناعمًا → fallback', $eff['role'] === '6' && $eff['source'] === 'role');
$conn->query("UPDATE positions SET is_deleted=0, deleted_at=NULL, company_id=5 WHERE id={$posId}");
$eff = ems_user_effective_role($conn, $uid);
ok('منصب من شركة أخرى → رفض + fallback (عزل)', $eff['role'] === '6' && $eff['source'] === 'role_x_company');
$conn->query("UPDATE positions SET company_id=4 WHERE id={$posId}");
$conn->query("UPDATE users SET position_id=999999999 WHERE id={$uid}");
$eff = ems_user_effective_role($conn, $uid);
ok('position_id يتيم (لا صف) → fallback', $eff['role'] === '6' && $eff['source'] === 'role');

echo "── 6) صفر أثر على القائم: كل مستخدمي النظام position_id=NULL ──\n";
$nulls = intval($conn->query("SELECT COUNT(*) FROM users WHERE position_id IS NULL AND username NOT LIKE 'K6TEST%'")->fetch_row()[0]);
$all = intval($conn->query("SELECT COUNT(*) FROM users WHERE username NOT LIKE 'K6TEST%'")->fetch_row()[0]);
ok("جميع المستخدمين الحقيقيين ({$all}) بلا منصب = سلوكهم القائم بلا أي تغيير", $nulls === $all);

} catch (\Throwable $t) {
    $FAIL++;
    echo "  ✘ استثناء غير متوقع: " . $t->getMessage() . "\n";
}

// ── teardown ──
foreach ($cleanupUsers as $u) { $conn->query("DELETE FROM users WHERE id=" . intval($u)); }
foreach ($cleanupPos as $p) { $conn->query("DELETE FROM positions WHERE id=" . intval($p)); }
$left = intval($conn->query("SELECT COUNT(*) FROM users WHERE username LIKE 'K6TEST%'")->fetch_row()[0])
      + intval($conn->query("SELECT COUNT(*) FROM positions WHERE name LIKE 'K6TEST%'")->fetch_row()[0]);
ok('teardown: صفر بقايا', $left === 0);

echo str_repeat('═', 50) . "\n";
echo "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n";
exit($FAIL === 0 ? 0 : 1);
