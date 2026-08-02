<?php
/**
 * tools/n26_infra_probe.php — فحص جاهزية البنية (update0004 · N-26 · NFR-11→15)
 * ───────────────────────────────────────────────────────────────────────────
 * يقيس البنود الخمسة ويكتب التقرير في docs/nfr/N-26_infra_readiness_ar.md —
 * والفحص الحاكم (آلة واحدة؟) يتصدر: فصلها يسبق كل بند إن كانت.
 * التشغيل: php tools/n26_infra_probe.php
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
$db->set_charset('utf8mb4');

$vars = array();
$r = $db->query("SHOW VARIABLES WHERE Variable_name IN ('max_connections','slow_query_log','long_query_time','hostname','version')");
while ($r && ($x = $r->fetch_row())) { $vars[$x[0]] = $x[1]; }

// NFR-11 الحاكم: أآلة واحدة تستضيف الاثنين؟
$dbHost = strtolower((string) ems_env('DB_HOST'));
$singleMachine = in_array($dbHost, array('localhost', '127.0.0.1'), true);

// NFR-12: OPcache والعمال
$opcacheLoaded = extension_loaded('Zend OPcache');
$opcacheIni = ini_get('opcache.memory_consumption');
$workers = null;
// مجلد أباتشي يُشتق من ثنائي PHP (WAMP: bin/php/… → bin/apache · XAMPP: php/ → apache/)
// أو يُملى صراحةً بـEMS_APACHE_DIR في .env — لا مسار مثبَّت.
$mpmBases = array();
$envApache = trim((string) ems_env('EMS_APACHE_DIR', ''));
if ($envApache !== '') { $mpmBases[] = rtrim(str_replace('\\', '/', $envApache), '/'); }
if (defined('PHP_BINARY') && PHP_BINARY !== '') {
    $binDir = str_replace('\\', '/', dirname(PHP_BINARY));
    $mpmBases[] = dirname(dirname($binDir)) . '/apache';   // C:/wamp64/bin/php/php8.x → C:/wamp64/bin/apache
    $mpmBases[] = dirname($binDir) . '/apache';            // C:/xampp/php            → C:/xampp/apache
}
foreach ($mpmBases as $mpm) {
    foreach (array($mpm . '/apache*/conf/extra/httpd-mpm.conf', $mpm . '/conf/extra/httpd-mpm.conf') as $pattern) {
        foreach (glob($pattern) ?: array() as $f) {
            if (preg_match('/MaxRequestWorkers\s+(\d+)/', (string) file_get_contents($f), $m)) { $workers = intval($m[1]); }
        }
    }
    if ($workers !== null) { break; }
}
$maxConn = intval($vars['max_connections'] ?? 0);
$neededConn = $workers !== null ? (int) ceil($workers * 1.2) : null;

// NFR-13: الجلسات
$sessStore = strtolower((string) ems_env('EMS_SESSION_STORE', 'files'));
$sessTable = $db->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='ems_sessions'");
$sessReady = $sessTable && $sessTable->num_rows === 1;

$today = $db->query('SELECT NOW() n')->fetch_assoc()['n'];
$md = "# N-26 · جاهزية البنية — تقرير الفحص الحاكم والقياسات ({$today})\n\n";
$md .= "## NFR-11 · الفحص الحاكم — يتصدر كل بند\n\n";
$md .= "**أتستضيف آلة واحدة التطبيق والقاعدة معًا؟ " . ($singleMachine ? '**نعم** (DB_HOST=' . $dbHost . ' · WAMP)' : 'لا') . "**\n\n";
$md .= $singleMachine
    ? "> **فصلها يسبق كل بند عند الإطلاق**: خادم قاعدة مستقل أولًا — وكل توصيات هذا التقرير مقيسة على وضع ما بعد الفصل. بيئة التجربة المحلية تبقى موحدة بطبيعتها (UAT معزولة لا إنتاج).\n\n"
    : "> البنية منفصلة — تُطبق البنود مباشرة.\n\n";
$md .= "## NFR-12 · OPcache والعمال بحساب الذاكرة\n\n";
$md .= "| القياس | القيمة | الحكم |\n|---|---|---|\n";
$md .= "| OPcache محمَّل | " . ($opcacheLoaded ? 'نعم' : '**لا**') . " | " . ($opcacheLoaded ? '✔' : 'يفعَّل') . " |\n";
$md .= "| opcache.memory_consumption | " . ($opcacheIni ?: 'افتراضي 128M') . " | التوجيه الجاهز أدناه يثبته 256M صراحة |\n";
$md .= "| MaxRequestWorkers | " . ($workers ?? 'غير مقيس') . " | ضبط بحساب الذاكرة: عامل PHP ≈ 60-80MB → على 8GB لا يتجاوز ~80 عاملًا مع القاعدة على الآلة نفسها |\n\n";
$md .= "```ini\n; php.ini — توجيهات جاهزة (تطبيقها إعادة تشغيل أباتشي — قرار تشغيل):\nopcache.enable=1\nopcache.memory_consumption=256\nopcache.interned_strings_buffer=16\nopcache.max_accelerated_files=20000\nopcache.validate_timestamps=1\nopcache.revalidate_freq=60\n```\n\n";
$md .= "## NFR-13 · الجلسات إلى مخزن مشترك\n\n";
$md .= "- الحالة: **" . ($sessStore === 'db' ? 'قاعدة (مفعَّل)' : 'ملفات — والبنية جاهزة للقلب') . "** · جدول `ems_sessions`: " . ($sessReady ? 'قائم ✔' : 'غائب') . "\n";
$md .= "- المعالج `includes/session_bootstrap.php` مطلوبٌ بـ`require_once` نسبيًّا (`__DIR__`) قبل كل `session_start()` في الصفحات — **القلب: `EMS_SESSION_STORE=db` والرجوع قلبه عكسًا** (فلا يقفل ملفُ جلسةٍ طلباتِ المستخدم المتوازية). كان يُحقن بـ`auto_prepend_file` بمسارٍ مطلقٍ في `.htaccess` فكان المشروع لا يعمل إلا من ذلك المسار بعينه.\n\n";
$md .= "## NFR-14 · اتصالات القاعدة وسجل البطيء\n\n";
$md .= "| القياس | القيمة | المطلوب |\n|---|---|---|\n";
$md .= "| max_connections | {$maxConn} | " . ($neededConn !== null ? "≥ {$neededConn} (العمال {$workers} + 20%)" : 'العمال + 20%') . ($neededConn !== null && $maxConn < $neededConn ? ' — **ناقص**' : ' ✔') . " |\n";
$md .= "| slow_query_log | " . ($vars['slow_query_log'] ?? '؟') . " | ON |\n";
$md .= "| long_query_time | " . ($vars['long_query_time'] ?? '؟') . " | **0.5 ثانية** |\n\n";
$md .= "> **صلاحية `SYSTEM_VARIABLES_ADMIN` غير متاحة لمستخدمَي التطبيق والمرحِّل (قياس حي)** — فالتوجيهات تُسلَّم جاهزة لmy.ini وتطبيقها إعادة تشغيل خدمة القاعدة (قرار تشغيل لا يُنفَّذ ذاتيًّا):\n\n";
$md .= "```ini\n; my.ini — [mysqld]\nmax_connections=" . ($neededConn ?: 300) . "\nslow_query_log=1\nlong_query_time=0.5\nslow_query_log_file=\"ems-slow.log\"\n```\n\n";
$md .= "## NFR-15 · الكرون خارج الذروة (بعد الثانية والعشرين)\n\n";
$md .= "| الدورية | الموعد المقترح | الأمر |\n|---|---|---|\n";
$md .= "| عامل الطابور (خفيف — يجوز نهارًا) | كل 5 دقائق | `php Operations/cron_job_worker.php 10` |\n";
$md .= "| مهل البلاغات (خفيف) | كل 5 دقائق | `php Tickets/cron_tickets.php` |\n";
$md .= "| التكليفات والأذونات | 22:15 | `php Operations/cron_org_assignments.php` |\n";
$md .= "| الصلاحيات (السقوط وكسر الزجاج) | 22:30 | `php Governance/cron_permissions.php` |\n";
$md .= "| الدوريات المالية (ثقيلة) | 22:45 | `php Operations/cron_job_worker.php` بعد `enqueue periodic_cron` |\n";
$md .= "| الإهلاك والأحداث الدورية | 23:00 | `php Finance/cron_depreciation_fin.php` · `cron_periodic_fin.php` |\n";
$md .= "| النقل الآلي للتناوب | 23:30 | `php Operations/cron_rotation_transfer.php` |\n\n";
// أمثلة الجدولة تُبنى من ثنائي PHP وجذر المشروع الحاليَّين — لا مسار مثبَّت في الكود.
$phpExe  = str_replace('/', '\\', defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php.exe');
$appRoot = str_replace('/', '\\', dirname(__DIR__));
$md .= "```bat\nREM جدولة وندوز (تُنفَّذ بيد المشغّل — أمثلة مبنية على مسار هذا الجهاز):\nschtasks /Create /TN EMS\\JobWorker /SC MINUTE /MO 5 /TR \"{$phpExe} {$appRoot}\\Operations\\cron_job_worker.php 10\"\nschtasks /Create /TN EMS\\NightlyOrg /SC DAILY /ST 22:15 /TR \"{$phpExe} {$appRoot}\\Operations\\cron_org_assignments.php\"\n```\n";

$out = dirname(__DIR__) . '/docs/nfr/N-26_infra_readiness_ar.md';
file_put_contents($out, $md);
echo "كُتب التقرير: docs/nfr/N-26_infra_readiness_ar.md\n";
echo "آلة واحدة=" . ($singleMachine ? 'نعم' : 'لا') . " · opcache=" . ($opcacheLoaded ? 'نعم' : 'لا')
    . " · عمال={$workers} · اتصالات={$maxConn} · جلسات={$sessStore}(" . ($sessReady ? 'جاهز' : 'غائب') . ")\n";
