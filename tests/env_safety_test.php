<?php
/**
 * tests/env_safety_test.php — سلامة البيئة والأسرار (البوابة ① · PLAN-05 §3-⑩)
 * ═══════════════════════════════════════════════════════════════════════════
 * ① عزل عدة الاختبار: ems_test_env_override لا يكتب في .env الحي (بايت-مطابق)
 *   والتراكب يغلب القيمة الحية ويورَّث للعمليات الفرعية.
 * ② نسخة احتياطية آلية قبل أي تعديل تهيئة: ems_env_write تنشئها أولًا —
 *   وفشل النسخة يمنع التعديل (fail-closed).
 * ③ مصدر ثانٍ للأسرار: نسخة مشفَّرة قائمة وتُفك بمفتاح خارج جذر الويب.
 * ④ الحسابات المخصصة: DB_USER ≠ root · وems_app ممنوع من DDL بنيويًّا.
 * التشغيل: php tests/env_safety_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
$ROOT = dirname(__DIR__);

require_once __DIR__ . '/_guard_env.php';
$envBytesBefore = file_get_contents($ROOT . '/.env');
ems_test_env_override(array('EMS_PROBE_SAFETY' => 'overlay_wins'));
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

head('① عزل عدة الاختبار — التراكب لا الملف الحي');
check(ems_env('EMS_PROBE_SAFETY') === 'overlay_wins', 'قيمة التراكب مقروءة عبر ems_env');
check(file_get_contents($ROOT . '/.env') === $envBytesBefore, '.env الحي بايت-مطابق — صفر كتابة فيه');
$child = shell_exec('php -r "require ' . var_export($ROOT . '/includes/env.php', true) . '; echo ems_env(\'EMS_PROBE_SAFETY\', \'none\');"');
check(trim((string) $child) === 'overlay_wins', 'التراكب يورَّث للعمليات الفرعية (المسابير) عبر البيئة');

head('② النسخة الاحتياطية الآلية قبل أي تعديل');
$histDir = $ROOT . '/database/backups/env_history';
$before = is_dir($histDir) ? count(glob($histDir . '/*.bak')) : 0;
$bak = ems_env_write(array('EMS_PROBE_WRITE' => 'x1'));
check($bak !== false && is_readable($bak), 'ems_env_write أنشأت نسخة قبل الكتابة: ' . basename((string) $bak));
$after = count(glob($histDir . '/*.bak'));
check($after === $before + 1, 'النسخة في env_history — لكل تعديل نسخة');
check(strpos(file_get_contents($ROOT . '/.env'), 'EMS_PROBE_WRITE=x1') !== false, 'والقيمة كُتبت بعد النسخة');
check(strpos(file_get_contents($bak), 'EMS_PROBE_WRITE=x1') === false, 'والنسخة تحمل ما قبل التعديل — استرجاع حقيقي');
// كنس مفتاح المسبار (بنسخة آلية أيضًا — القاعدة على الجميع)
$src = file_get_contents($ROOT . '/.env');
file_put_contents($ROOT . '/.env', preg_replace('/^EMS_PROBE_WRITE=.*\n?/m', '', $src));

head('③ المصدر الثاني للأسرار');
$encs = glob($ROOT . '/database/backups/secrets/*.enc');
check(!empty($encs), 'نسخة مشفَّرة قائمة (' . count($encs) . ')');
$keyFile = rtrim(getenv('USERPROFILE') ?: getenv('HOME'), '/\\') . DIRECTORY_SEPARATOR . '.ems_secrets_key';
check(is_readable($keyFile) && strpos($keyFile, $ROOT) === false, 'المفتاح خارج جذر الويب: ' . $keyFile);
$latest = end($encs);
$restored = $ROOT . '/.env.restored_probe';
@unlink($restored);
shell_exec('php ' . escapeshellarg($ROOT . '/scripts/secrets_backup.php') . ' --restore ' . escapeshellarg($latest) . ' ' . escapeshellarg($restored));
check(is_readable($restored) && strpos(file_get_contents($restored), 'DB_NAME=') !== false, 'الاستعادة من المشفَّرة تعمل فعلًا — لا وعدًا');
@unlink($restored);
$gi = file_get_contents($ROOT . '/.gitignore');
check(strpos($gi, 'database/backups/secrets/') !== false && strpos($gi, 'env_history') !== false, 'المشفَّرة والتاريخ خارج المستودع (.gitignore)');

head('④ الحسابات المخصصة بدل الحساب العام');
check(ems_env('DB_USER') === 'ems_app', 'اتصال التطبيق بحساب ems_app المخصص لا root');
check(ems_env('DB_MIGRATOR_USER') === 'ems_migrator', 'والمرحِّل بحساب ems_migrator');
$r = $conn->query('CREATE TABLE __probe_ddl_denied (id INT)');
check($r === false, 'وems_app ممنوع من DDL بنيويًّا (صلاحيات لا اتفاقًا): ' . mb_substr((string) $conn->error, 0, 50));
$r = $conn->query('SELECT COUNT(*) c FROM users');
check($r !== false, 'وDML يعمل كاملًا');

echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
