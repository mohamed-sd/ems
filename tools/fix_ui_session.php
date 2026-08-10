<?php
/**
 * tools/fix_ui_session.php — جلسةُ تحقُّقٍ بصريٍّ محليةٌ لا أكثر
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لماذا لا تُغيَّر كلمةُ حسابٍ قائم: تغييرُها يكسر `trial_readiness` كاذبًا
 *   (گوتشا مسجَّلة). ولماذا لا يُنشأ حسابٌ جديد: صفٌّ في `users` أثرٌ دائمٌ
 *   في بياناتٍ حيّة لأجلِ فحصٍ عابر.
 * ◆ فالأخفُّ أثرًا: تُكتب **جلسةٌ** على القرصِ بمعرِّفٍ معلوم، ويُحقن المتصفحُ
 *   بمعرِّفها. لا صفَّ يُضاف ولا كلمةَ تُغيَّر — وتزول بانتهاء الجلسة.
 * ◆ محليٌّ حصرًا: يرفض العملَ إن لم يكن المضيفُ محليًّا.
 *
 * التشغيل: php tools/fix_ui_session.php <user_id>
 * المخرَج: معرِّفُ الجلسةِ ليُحقن كوكيًّا.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';

// المضيفُ قد يحمل منفذًا (`localhost:3307`) — يُقتطع قبل المقارنة.
$host = strtolower((string) ems_env('DB_HOST', '127.0.0.1'));
$host = trim(explode(':', $host)[0]);
if (!in_array($host, array('127.0.0.1', 'localhost', '::1'), true)) {
    exit("يعمل على المضيفِ المحليِّ وحدَه — المضيفُ الحاليُّ {$host}\n");
}

$uid = isset($argv[1]) ? (int) $argv[1] : 0;
if ($uid <= 0) { exit("مرِّرْ معرِّفَ المستخدم.\n"); }

require_once $ROOT . '/tools/fix_lib.php';
$db = fix_db();
$st = $db->prepare('SELECT id, username, name, role, company_id FROM users WHERE id = ? LIMIT 1');
$st->bind_param('i', $uid);
$st->execute();
$u = $st->get_result()->fetch_assoc();
$st->close();
if (!$u) { exit("لا مستخدمَ بهذا المعرِّف.\n"); }

$sid  = 'uiverify' . substr(sha1((string) $uid . '|ui-verify'), 0, 18);
$path = session_save_path();
if ($path === '') { $path = sys_get_temp_dir(); }

$payload = 'user|' . serialize(array(
    'id'         => (int) $u['id'],
    'username'   => (string) $u['username'],
    'name'       => (string) $u['name'],
    'role'       => (string) $u['role'],
    'company_id' => (int) $u['company_id'],
));

$file = rtrim($path, "\\/") . '/sess_' . $sid;
if (@file_put_contents($file, $payload) === false) {
    exit("تعذّرت الكتابةُ في {$file}\n");
}

echo "معرِّفُ الجلسة: {$sid}\n";
echo "الملف: {$file}\n";
echo "المستخدم: " . $u['name'] . " · الدور " . $u['role'] . " · الشركة " . $u['company_id'] . "\n";
