<?php
/**
 * 2028_05_17_perm01_function_screen_map.php — خريطةُ «وظيفة ← شاشة» (ق-٧ · البند ①)
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **المقامُ القديمُ كان معطوبًا**: «شاشاتُ الطرف» كانت تُشتقُّ من **فرقِ
 *   مجموعتَي أدوارِ التركيبة** — فتنسحب معها كلُّ شاشةٍ يملكها أولئك الأدوارُ
 *   ولو لم تمتَّ للوظيفةِ بصلة. مقيسٌ بالاسم: «طرفُ منشئِ المورد» لقالبِ إدارةِ
 *   التشغيل خرج **سجلَّ نشاطٍ وموظّفين ومعدّاتٍ واعتمادَ ساعات**.
 *
 * ◆ **والاشتقاقُ هنا من الكتابةِ الفعليّة لا من الاسم**: لكلِّ وظيفةٍ **جدولُ
 *   مرساةٍ** تكتبه، ثمَّ يُمسح الإنتاجُ (1,324 ملفًّا) عن كلِّ من يكتب فيه.
 *   والمطابقةُ بالكلماتِ جُرِّبت أوّلًا فأخرجت «لوحةَ مخاطرِ الرئيس» لوظيفةِ
 *   الرواتب — فطُرحت.
 *
 * ⛔ **وما لا كاتبَ له يُسمّى «غيرَ منفَّذ» ولا يُترك فارغًا**: وظيفتانِ بلا كاتبٍ
 *   في الإنتاجِ إطلاقًا (إعدادُ الرواتبِ وإطلاقُ دفعتِها · واعتمادُ التخلّصِ من
 *   الأصل) — فتركيبتاهما (SOD-10 · SOD-11) **لا تُقاس بالشاشةِ لأنَّ الوظيفةَ
 *   نفسَها غيرُ مبنيّة**، لا لأنَّ الفصلَ تحقّق.
 *
 * ◆ **وثلاثُ تركيباتٍ طرفاها على شاشةٍ واحدة** — `Finance/bank_reconciliation_fin.php`
 *   تكتب `fin_bank_accounts` و`fin_payments` و`bank_recon_matches` معًا.
 *   ⇒ **الفصلُ على الشاشةِ مستحيلٌ فيها بنيويًّا**، والضابطُ على الفعلِ هو
 *   الوحيدُ الممكن — وهو ما بُني في `ReconSodGuard` (SOD-06 · 11/11 سالبًا).
 *   فالخريطةُ لا تُثبت 39 خرقًا، بل تُثبت **أين يستحيل الفصلُ بالشاشة**.
 *
 * التشغيل: php database/migrations/2028_05_17_perm01_function_screen_map.php
 * العكس:   php database/migrations/2028_05_17_perm01_function_screen_map_down.php
 * ═══════════════════════════════════════════════════════════════════════════
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
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

echo "══ ① السجلّ ══════════════════════════════════════════════════════════\n";
$ok = $conn->query("CREATE TABLE IF NOT EXISTS `perm01_function_screen` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `func_name` VARCHAR(120) NOT NULL COMMENT 'اسم الوظيفة كما في sec_sod_pairs',
    `anchor_table` VARCHAR(64) NOT NULL COMMENT 'جدول المرساة الذي تكتبه الوظيفة',
    `screen_code` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'رمز الشاشة الكاتبة، فارغ اذا لا كاتب',
    `registered` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'أمسجلة في modules',
    `state` ENUM('mapped','unimplemented') NOT NULL DEFAULT 'mapped'
        COMMENT 'unimplemented = لا كاتب في الانتاج فالوظيفة غير مبنية',
    `derived_from` VARCHAR(160) NOT NULL DEFAULT ''
        COMMENT 'كيف اشتق الصف — مسح كتابة على جدول المرساة',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_fn_screen` (`func_name`, `screen_code`),
    KEY `ix_func` (`func_name`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='PERM-01 ق-٧ — خريطة الوظيفة الى شاشتها، مشتقة من الكتابة الفعلية'");
echo '   perm01_function_screen: ' . ($ok ? 'قائم' : $conn->error) . "\n";

/* ◆ المراسي: لكلِّ وظيفةٍ الجدولُ الذي تكتبه — والمرساةُ من المخطَّطِ لا من رأي. */
$ANCHOR = array(
    'منشئُ المورد'                  => array('suppliers'),
    'معتمِدُ الحسابِ البنكي'         => array('fin_bank_accounts'),
    'منفِّذُ الدفع'                  => array('fin_payments'),
    'مُعِدُّ المطابقةِ البنكية'       => array('bank_recon_matches'),
    'معتمِدُ المطابقة'               => array('bank_recon_matches'),
    'مُعِدُّ الرواتب'                => array('hr_payroll_runs', 'hr_payroll_lines'),
    'مُطلِقُ دفعةِ الرواتبِ منفردًا'   => array('hr_payroll_runs'),
    'مسجِّلُ الأصل'                  => array('fin_assets'),
    'معتمِدُ التخلصِ منه منفردًا'     => array('fin_asset_disposal'),
);

echo "\n══ ② المسحُ — من يكتب في كلِّ مرساة ═══════════════════════════════════\n";
$reg = array();
$r = $conn->query("SELECT DISTINCT code FROM modules WHERE code LIKE '%.php'");
while ($x = $r->fetch_row()) { $reg[$x[0]] = 1; }

$skip = array('/tests/', '/tools/', '/docs/', '/vendor/', '/storage/', '/.git/',
              '/database/', '/node_modules/');
$files = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT,
        FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS));
foreach ($it as $f) {
    $pf = $f->getPathname();
    if (substr($pf, -4) !== '.php') { continue; }
    foreach ($skip as $s) { if (strpos($pf, $s) !== false) { continue 2; } }
    $files[substr($pf, strlen($ROOT) + 1)] = (string) @file_get_contents($pf);
}
printf("   ملفّاتُ إنتاجٍ مُسحت: %d\n\n", count($files));

$conn->query("DELETE FROM perm01_function_screen");
$st = $conn->prepare("INSERT IGNORE INTO perm01_function_screen
        (func_name, anchor_table, screen_code, registered, state, derived_from)
     VALUES (?,?,?,?,?,?)");
$mapped = 0; $unimpl = 0;
foreach ($ANCHOR as $fn => $tabs) {
    $hits = array();
    foreach ($files as $rel => $src) {
        foreach ($tabs as $t) {
            if (preg_match('~(INSERT\s+INTO|UPDATE)\s+`?' . $t . '`?~i', $src)
                || preg_match('~->(insert|update)\(\s*[\x27"]' . $t . '[\x27"]~i', $src)) {
                $hits[$rel] = $t; break;
            }
        }
    }
    $anchor = implode(',', $tabs);
    if (!$hits) {
        $emptyCode = ''; $z = 0; $stt = 'unimplemented';
        $from = 'مسح كتابة على ' . $anchor . ' — صفر كاتب في الانتاج';
        $st->bind_param('sssiss', $fn, $anchor, $emptyCode, $z, $stt, $from);
        $st->execute(); $unimpl++;
        printf("   %-30s ⇐ **غيرُ منفَّذة** (لا كاتب)\n", mb_substr($fn, 0, 28));
        continue;
    }
    foreach ($hits as $code => $t) {
        $isReg = isset($reg[$code]) ? 1 : 0;
        $stt = 'mapped';
        $from = 'كتابة على ' . $t;
        $st->bind_param('sssiss', $fn, $anchor, $code, $isReg, $stt, $from);
        $st->execute(); $mapped++;
    }
    printf("   %-30s ⇐ %d كاتبًا (منها مسجَّلة: %d)\n", mb_substr($fn, 0, 28), count($hits),
        count(array_filter(array_keys($hits), function ($c) use ($reg) { return isset($reg[$c]); })));
}
$st->close();

echo "\n══ ③ الشواهد ═════════════════════════════════════════════════════════\n";
$good = true;
$rows = $one("SELECT COUNT(*) FROM perm01_function_screen");
printf("   ★ صفوفُ الخريطة: %d (مربوطة %d · غيرُ منفَّذة %d)\n", $rows, $mapped, $unimpl);
if ($rows < 1) { $good = false; }

$noSrc = $one("SELECT COUNT(*) FROM perm01_function_screen WHERE derived_from = ''");
printf("   ★ ضابطٌ سالب — صفٌّ بلا بيانِ اشتقاق: %d %s\n", $noSrc, $noSrc === 0 ? '' : '✘');
if ($noSrc !== 0) { $good = false; }

/* ⛔ ولا وظيفةَ في السجلِّ الحاكمِ بلا صفٍّ هنا — ولو كان «غيرَ منفَّذة». */
$fnInMap = $one("SELECT COUNT(DISTINCT func_name) FROM perm01_function_screen");
printf("   ★ وظائفُ مغطّاةٌ بالخريطة: %d من %d مرساةً معرَّفة\n", $fnInMap, count($ANCHOR));
if ($fnInMap !== count($ANCHOR)) { $good = false; }

/* ◆ والكشفُ الحاكم: طرفانِ على شاشةٍ واحدة ⇒ لا فصلَ بالشاشة. */
$both = $one("SELECT COUNT(*) FROM (
        SELECT screen_code FROM perm01_function_screen
         WHERE state='mapped' AND registered=1 AND screen_code<>''
         GROUP BY screen_code HAVING COUNT(DISTINCT func_name) > 1) z");
printf("   ◆ شاشاتٌ تؤدّي أكثرَ من وظيفةٍ متعارضة: %d — وفيها **يستحيل الفصلُ بالشاشة**\n", $both);

foreach (array(basename(__FILE__), str_replace('.php', '_down.php', basename(__FILE__))) as $f) {
    $conn->query("INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('" . $conn->real_escape_string($f) . "')");
}
echo "\n" . ($good ? "تمت — والخريطة مشتقة من الكتابة الفعلية لا من الاسماء.\n" : "سقط شاهد\n");
