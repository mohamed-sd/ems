<?php
/**
 * tools/nav_route_case_guard.php — حارسُ حالةِ أحرفِ مساراتِ الملاحة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ الذي يمنعه**: مسارٌ مسجَّلٌ بحالةِ أحرفٍ تخالف اسمَ الملفِّ على القرص
 *   **يعمل على ويندوز ويردُّ 404 على لينكس**. فالفحصُ اليدويُّ على جهازِ التطوير
 *   لا يكشفه أبدًا، ولا يظهر إلّا بعدَ النشر.
 *
 * ◆ **والمقارنةُ تُبنى على القرصِ لا على تخمين**: يُقرأ اسمُ كلِّ جزءٍ من المسارِ
 *   من `scandir` للمجلَّدِ الحاوي، فيُطابَق حرفًا بحرف. و`realpath` وحدَه لا يكفي
 *   على ويندوز لأنّه يُرجع ما طُلب لا ما هو مكتوبٌ على القرص.
 *
 * ◆ **ولا يُحكَم على غيرِ الموجود**: مسارٌ لا ملفَّ له يُعَدُّ «خارجَ المدى» ويُسمّى
 *   منفصلًا — فخلطُ «غيرِ موجود» بـ«حالةٌ مخالفة» يُنتج تقريرًا أحمرَ كاذبًا.
 *
 * التشغيل: php tools/nav_route_case_guard.php
 * يردُّ 1 إن وُجدت مخالفةٌ واحدة — صالحٌ للاستعمالِ حاجبًا قبلَ النشر.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

/** الاسمُ كما هو مكتوبٌ على القرص، أو null إن لم يوجد. */
function disk_case($root, $rel)
{
    $parts = explode('/', trim(str_replace('\\', '/', $rel), '/'));
    $cur = $root; $out = array();
    foreach ($parts as $part) {
        if ($part === '' || $part === '.' || $part === '..') { return null; }
        $found = null;
        $entries = @scandir($cur);
        if ($entries === false) { return null; }
        foreach ($entries as $e) {
            if ($e === '.' || $e === '..') { continue; }
            if (strcasecmp($e, $part) === 0) { $found = $e; break; }
        }
        if ($found === null) { return null; }
        $out[] = $found;
        $cur .= '/' . $found;
    }
    return implode('/', $out);
}

$SOURCES = array(
    'nav_items'         => array('route',    "active = 1"),
    'modules'           => array('code',     "1"),
    'nav_canonical'     => array('route',    "1"),
    'gov_profile_items' => array('item_ref', "item_kind = 'screen'"),
);

$bad = array(); $missing = 0; $checked = 0;
foreach ($SOURCES as $tbl => $def) {
    list($col, $where) = $def;
    $rs = $conn->query("SELECT DISTINCT `{$col}` v FROM `{$tbl}`
                         WHERE {$where} AND `{$col}` LIKE '%.php'
                           AND `{$col}` NOT LIKE 'http%' AND `{$col}` LIKE '%/%'");
    while ($rs && ($r = $rs->fetch_assoc())) {
        $rel = ltrim(str_replace('\\', '/', (string) $r['v']), '/');
        if ($rel === '') { continue; }
        $checked++;
        $real = disk_case($ROOT, $rel);
        if ($real === null) { $missing++; continue; }
        if ($real !== $rel) { $bad[] = array($tbl, $rel, $real); }
    }
}

printf("مساراتٌ فُحصت: %d · لا ملفَّ لها (خارج المدى): %d · **حالةٌ تخالف القرص: %d**\n",
       $checked, $missing, count($bad));
foreach ($bad as $b) { printf("   %-20s %-46s ⇐ الصواب: %s\n", $b[0], $b[1], $b[2]); }

if ($bad) { echo "\n⛔ مسارٌ بحالةٍ مخالفةٍ يردُّ 404 على لينكس.\n"; exit(1); }
echo "\n✔ كلُّ مسارٍ مسجَّلٍ يطابق حالةَ اسمِه على القرص.\n";
exit(0);
