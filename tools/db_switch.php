<?php
/**
 * مبدّل محرّك القاعدة — MySQL (3306) ↔ MariaDB (3307)
 * ───────────────────────────────────────────────────────────────────────────
 * المحرّكان يعملان جنبًا إلى جنب في WAMP، والنظام كله (89 موضع اتصال) يقرأ
 * المضيف من قمعٍ واحد: DB_HOST في .env — وmysqli تقبل صيغة host:port، فالتبديل
 * قلبُ هذا السطر وحده. لا ملفَّي إعدادٍ متوازيين: مصدرُ الحقيقة واحدٌ (ADR-04)
 * والمفتاح صريح.
 *
 *   php tools/db_switch.php status    ← أين أنا الآن؟
 *   php tools/db_switch.php mariadb   ← النظام كله إلى MariaDB:3307
 *   php tools/db_switch.php mysql     ← النظام كله إلى MySQL:3306
 *
 * أمان: بعد قلب السطر يُفحص الاتصال بالوجهة بحساب التطبيق نفسه — فإن فشل
 * ارتدَّ .env إلى ما كان عليه (fail-safe لا يترك النظام معلَّقًا على قاعدة ميتة).
 * تنبيه: الجلسات مخزَّنةٌ في القاعدة — كلُّ تبديلٍ يعني تسجيلَ دخولٍ من جديد.
 * وقاعدتان حيّتان تتباعدان من لحظة النسخ: الحقيقةُ في واحدةٍ فقط في كل وقت،
 * والمزامنة إعادةُ استيرادٍ كاملة (انظر الذاكرة/التوثيق).
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);

const TARGETS = array(
    'mysql'   => 'localhost',
    'mariadb' => 'localhost:3307',
);

$envPath = dirname(__DIR__) . '/.env';
$envRaw  = file_get_contents($envPath);
if ($envRaw === false) { fwrite(STDERR, "تعذرت قراءة .env\n"); exit(1); }
if (!preg_match('/^DB_HOST=(.*)$/m', $envRaw, $m)) {
    fwrite(STDERR, "سطر DB_HOST غائب من .env\n"); exit(1);
}
$currentHost = trim($m[1]);

function probe($host) {
    require_once dirname(__DIR__) . '/includes/env.php';
    $c = @new mysqli($host, ems_env('DB_APP_USER'), ems_env('DB_APP_PASS'), ems_env('DB_NAME'));
    if ($c->connect_errno) { return array(null, $c->connect_error); }
    $info   = $c->server_info;
    $engine = (stripos($info, 'MariaDB') !== false) ? 'MariaDB' : 'MySQL';
    $r = $c->query("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema = DATABASE()");
    $n = $r ? $r->fetch_assoc()['n'] : '?';
    return array("{$engine} {$info} · {$n} كائنًا في " . ems_env('DB_NAME'), null);
}

$cmd = isset($argv[1]) ? strtolower($argv[1]) : 'status';

if ($cmd === 'status') {
    $label = array_search($currentHost, TARGETS, true);
    echo "DB_HOST = {$currentHost}" . ($label ? " ({$label})" : ' (خارج الهدفين المعروفين!)') . "\n";
    list($ok, $err) = probe($currentHost);
    echo $ok !== null ? "  متصل: {$ok}\n" : "  الاتصال فاشل: {$err}\n";
    exit($ok !== null ? 0 : 1);
}

if (!isset(TARGETS[$cmd])) {
    fwrite(STDERR, "الاستخدام: php tools/db_switch.php status|mysql|mariadb\n"); exit(1);
}
$newHost = TARGETS[$cmd];
if ($newHost === $currentHost) {
    echo "أنت على {$cmd} أصلًا ({$currentHost}) — لا تغيير\n"; exit(0);
}

// فحص الوجهة قبل القلب — لا نكتب .env نحو قاعدةٍ لا تستجيب
list($ok, $err) = probe($newHost);
if ($ok === null) { fwrite(STDERR, "الوجهة {$cmd} ({$newHost}) لا تستجيب: {$err} — لم يُمسّ .env\n"); exit(1); }

$newRaw = preg_replace('/^DB_HOST=.*$/m', "DB_HOST={$newHost}", $envRaw, 1);
if (file_put_contents($envPath, $newRaw) === false) { fwrite(STDERR, "تعذرت كتابة .env\n"); exit(1); }

echo "بُدِّل: {$currentHost} ← {$newHost}\n";
echo "  النشط الآن: {$ok}\n";
echo "  تذكير: سجّل دخولك من جديد (الجلسات تسكن القاعدة) — والحقيقة الآن في {$cmd} وحدها\n";
exit(0);
