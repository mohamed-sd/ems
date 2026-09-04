<?php
/**
 * 2028_05_01_exe01_dispatcher_attempts_down.php — عكسُ فصلِ تخزينِ الجيلِ الأول
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العكسُ آمنٌ بشرطٍ واحد**: أن يكون `EventDispatcher` قد رُدَّ إلى الجدولِ
 *   القديمِ أوّلًا (بإطفاءِ مفتاحِ الميزة). فإسقاطُ جدولٍ يقرؤه كاتبٌ حيٌّ
 *   يكسر الناقل — ⇐ فالعكسُ **يفحص المفتاحَ ويرفض إن كان مُشغَّلًا**.
 *
 * ◆ **ولا يُفقَد شيء**: الصفوفُ عدّادُ محاولاتٍ عابرٌ يُبنى من جديدٍ في الدورةِ
 *   التالية — لا حقيقةَ عملٍ فيه ولا أثرَ ماليّ.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

/* ══ حارسُ العكس — يسأل الكاتبَ نفسَه لا متغيّرًا يمثّله ═════════════════════
   ⛔ **والنسخةُ الأولى من هذا الحارسِ فشلت مفتوحةً**: قرأت
      `ems_env('EMS_DISPATCHER_SPLIT_STORE')` — و`ems_env()` تقرأ ملفَّ `.env`
      وحدَه ولا ترى بيئةَ الصَّدفة. فأُسقط الجدولُ **والمفتاحُ مُشغَّل**، وقال
      الحارسُ «آمن». (‏ونجا النظامُ بحارسِ `SHOW TABLES` في الكاتبِ لا بهذا.)
   ⇐ **ولا يُقاس القرارُ بوكيلٍ عنه**: يُنشأ الكاتبُ ويُسأل عن جدولِه الفعّال —
      فما يستعمله كاتبٌ حيٌّ لا يُسقَط. */
require_once $ROOT . '/App/Core/EventDispatcher.php';
$active = (new \App\Core\EventDispatcher($conn))->attemptsTable();
if ($active === 'ems_dispatcher_attempts') {
    exit("⛔ الكاتبُ ما زال يستعمل `ems_dispatcher_attempts`.\n"
       . "   أطفئ `EMS_DISPATCHER_SPLIT_STORE=0` في `.env` أو في بيئةِ التشغيل، ثمَّ أعِد العكس.\n");
}
printf("  ✔ الكاتبُ يستعمل `%s` — العكسُ آمن\n", $active);

$n = $conn->query('SELECT COUNT(*) FROM `ems_dispatcher_attempts`');
if ($n) { printf("  ◆ صفوفٌ عابرةٌ تُسقَط: %s (تُبنى من جديدٍ في الدورةِ التالية)\n", $n->fetch_row()[0]); }
if (!$conn->query('DROP TABLE IF EXISTS `ems_dispatcher_attempts`')) { exit('⛔ ' . $conn->error . "\n"); }
echo "  − `ems_dispatcher_attempts` أُسقط\n\n◆ عُكس فصلُ التخزين. و`ems_event_deliveries` كما كان حرفًا.\n";
