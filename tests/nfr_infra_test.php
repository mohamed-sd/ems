<?php
/**
 * update0004 · الموجة ㉑ — حزام N-26: جاهزية البنية (NFR-11→15)
 * التشغيل: php tests/nfr_infra_test.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/includes/EmsDbSessionHandler.php';

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; $c ? $PASS++ : $FAIL++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

fwrite(STDOUT, "\n══ update0004 موجة ㉑ — N-26 البنية ══\n");

head('NFR-11 — الفحص الحاكم موثق ويتصدر');
$rep = dirname(__DIR__) . '/docs/nfr/N-26_infra_readiness_ar.md';
check(is_file($rep), 'تقرير الجاهزية مكتوب');
$repSrc = file_get_contents($rep);
check(mb_strpos($repSrc, 'الفحص الحاكم') !== false && mb_strpos($repSrc, 'فصلها يسبق كل بند') !== false,
    'الفحص الحاكم (آلة واحدة؟) يتصدر وفصلها يسبق البنود');

head('NFR-12 — OPcache والعمال');
check(extension_loaded('Zend OPcache'), 'OPcache محمَّل');
check(mb_strpos($repSrc, 'opcache.memory_consumption=256') !== false, 'توجيهات OPcache الجاهزة (256M)');
check(mb_strpos($repSrc, 'بحساب الذاكرة') !== false, 'ضبط العمال بحساب الذاكرة موثق');

head('NFR-13 — الجلسات: صف قاعدة لا قفل ملف');
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
$db->set_charset('utf8mb4');
$r = $db->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='ems_sessions'");
check($r && $r->num_rows === 1, 'جدول ems_sessions قائم');
$h = new EmsDbSessionHandler();
check($h->open('', 'PHPSESSID') === true, 'المعالج يفتح اتصاله');
$id = 'n26_' . getmypid();
check($h->write($id, 'user|s:4:"test";') === true, 'كتابة الجلسة صفًّا');
check($h->read($id) === 'user|s:4:"test";', 'قراءة الجلسة');
check($h->destroy($id) === true, 'إتلافها');
$h->gc(1);
$h->close();
// المعالج كان يُحقن بـauto_prepend_file بمسارٍ مطلق يكسر المشروع في أي مسارٍ آخر؛
// صار require_once يسبق كل session_start(). الحارسُ يحرس الأمرين معًا.
$ht = file_get_contents(dirname(__DIR__) . '/.htaccess');
check(!preg_match('/^\s*php_value\s+auto_prepend_file/mi', $ht) && !preg_match('/[A-Za-z]:[\\\\\/]/', $ht),
    '.htaccess خالٍ من auto_prepend_file ومن أي مسارٍ مطلق');

$appRoot = dirname(__DIR__);
$missing = array(); $unresolved = array(); $scanned = 0;
$walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appRoot, FilesystemIterator::SKIP_DOTS));
foreach ($walk as $file) {
    $p = str_replace('\\', '/', $file->getPathname());
    if (substr($p, -4) !== '.php' || preg_match('#/(\.git|\.claude|vendor|node_modules|storage)/#', $p)) { continue; }
    $src = file_get_contents($p);
    // سطرٌ مستقلٌّ فقط — لا معلَّقٌ (//session_start();) ولا نصٌّ داخل سلسلة.
    // وإزاحةُ البايتات لا الحروف: preg_match بايتيّة، وmb_strpos تكذب مع العربية.
    if (!preg_match('/^[ \t]*session_start\(\);/m', $src, $ms, PREG_OFFSET_CAPTURE)) { continue; }
    $posSession = $ms[0][1];
    $scanned++;
    if (!preg_match("#require_once __DIR__ \. '([^']+session_bootstrap\.php)'#", $src, $m, PREG_OFFSET_CAPTURE)) {
        $missing[] = $p; continue;
    }
    if ($m[0][1] > $posSession)                          { $missing[] = $p; }
    if (realpath(dirname($p) . $m[1][0]) === false)      { $unresolved[] = $p; }
}
check($scanned > 200, "صفحاتُ الجلسة ممسوحة ({$scanned} ملفًا)");
check(empty($missing), 'المعالجُ مطلوبٌ قبل session_start() في كل صفحة' .
    (empty($missing) ? '' : ' — ينقص: ' . implode(', ', array_slice($missing, 0, 5))));
check(empty($unresolved), 'كلُّ مسارات require تُحَل نسبيًّا (__DIR__) — لا مسارَ مثبَّتًا' .
    (empty($unresolved) ? '' : ' — مكسور: ' . implode(', ', array_slice($unresolved, 0, 5))));
check(strtolower((string) ems_env('EMS_SESSION_STORE', 'files')) === 'files',
    'العلم files افتراضًا — القلب قرار تشغيل بعد لقطة (§1.3)');

head('NFR-14 — الاتصالات وسجل البطيء');
check(mb_strpos($repSrc, 'max_connections=300') !== false || preg_match('/max_connections=\d{3}/', $repSrc),
    'توجيه max_connections الجاهز (العمال + 20%)');
check(mb_strpos($repSrc, 'long_query_time=0.5') !== false, 'عتبة نصف الثانية في التوجيه الجاهز');
check(mb_strpos($repSrc, 'SYSTEM_VARIABLES_ADMIN') !== false, 'تعذر SET GLOBAL موثق بقياسه (تسليم جاهز لا ادعاء)');

head('NFR-15 — الكرون خارج الذروة');
check(mb_strpos($repSrc, 'بعد الثانية والعشرين') !== false || mb_strpos($repSrc, '22:15') !== false,
    'الجدولة الليلية (22:15+) موثقة بأوامرها');
check(mb_strpos($repSrc, 'schtasks') !== false, 'أوامر جدولة وندوز جاهزة');

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
