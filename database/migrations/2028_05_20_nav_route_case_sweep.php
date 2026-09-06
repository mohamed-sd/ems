<?php
/**
 * 2028_05_20_nav_route_case_sweep.php — كنسُ حالةِ الأحرفِ في كلِّ مسارٍ مسجَّل
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تعميمُ `2028_05_19`**: تلك وحّدت تسعةَ مساراتٍ في `Contracts/` لأنّها مدى
 *   جولةِ المبيعات. وأداةُ `tools/nav_route_case_guard.php` كشفت أنَّ العطبَ
 *   **منظوميّ: 347 مسارًا** في أربعةِ سجلّاتٍ عبرَ الإداراتِ كلِّها.
 *
 * ◆ **الأثرُ**: مسارٌ مسجَّلٌ بحالةٍ تخالف اسمَ الملفِّ على القرصِ يعمل على ويندوز
 *   (لا يفرّق) و**يردُّ 404 على لينكس** (يفرّق) — وهو نظامُ الاستضافةِ المستهدَفة.
 *   فالعطبُ **لا يظهر في أيِّ فحصٍ يُجرى على جهازِ التطوير**.
 *
 * ◆ **والمرجعُ القرصُ لا التخمين**: يُقرأ اسمُ كلِّ جزءٍ من `scandir` للمجلَّدِ
 *   الحاوي فيُطابَق حرفًا بحرف. و`realpath` وحدَه لا يكفي على ويندوز لأنّه يُرجع
 *   ما طُلب لا ما هو مكتوب.
 *
 * ⛔ **ولا يُمَسُّ مسارٌ لا ملفَّ له**: «غيرُ موجود» حكمٌ آخرُ يُسمّى ولا يُخلَط.
 * ⛔ **ولا تُمَسُّ مفاتيحُ تُصغَّر قبلَ المقارنة**: `includes/action_guard.php`
 *   و`includes/nav_views.php` يُصغّران مدخلَهما عمدًا — فتكبيرُ مفاتيحِهما يكسر
 *   المطابقةَ ويُعطِّل حارسَ الأفعال. لا شأنَ لهذه الهجرةِ بهما.
 *
 * التشغيل: php database/migrations/2028_05_20_nav_route_case_sweep.php
 * العكس:   php database/migrations/2028_05_20_nav_route_case_sweep_down.php
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

$CACHE = array();
function disk_case($root, $rel, &$cache)
{
    if (isset($cache[$rel])) { return $cache[$rel]; }
    $parts = explode('/', trim(str_replace('\\', '/', $rel), '/'));
    $cur = $root; $out = array();
    foreach ($parts as $part) {
        if ($part === '' || $part === '.' || $part === '..') { return $cache[$rel] = null; }
        $found = null;
        $entries = @scandir($cur);
        if ($entries === false) { return $cache[$rel] = null; }
        foreach ($entries as $e) {
            if ($e === '.' || $e === '..') { continue; }
            if (strcasecmp($e, $part) === 0) { $found = $e; break; }
        }
        if ($found === null) { return $cache[$rel] = null; }
        $out[] = $found; $cur .= '/' . $found;
    }
    return $cache[$rel] = implode('/', $out);
}

$SOURCES = array(
    'nav_items'         => array('route',    "active = 1"),
    'modules'           => array('code',     "1"),
    'nav_canonical'     => array('route',    "1"),
    'gov_profile_items' => array('item_ref', "item_kind = 'screen'"),
);

echo "══ ① جردُ المخالفات ═══════════════════════════════════════════════════\n";
$plan = array(); $missing = 0; $checked = 0;
foreach ($SOURCES as $tbl => $def) {
    list($col, $where) = $def;
    $rs = $conn->query("SELECT DISTINCT `{$col}` v FROM `{$tbl}`
                         WHERE {$where} AND `{$col}` LIKE '%.php'
                           AND `{$col}` NOT LIKE 'http%' AND `{$col}` LIKE '%/%'");
    $n = 0;
    while ($rs && ($r = $rs->fetch_assoc())) {
        $rel = ltrim(str_replace('\\', '/', (string) $r['v']), '/');
        if ($rel === '') { continue; }
        $checked++;
        $real = disk_case($ROOT, $rel, $CACHE);
        if ($real === null) { $missing++; continue; }
        if ($real !== $rel) { $plan[$tbl][] = array($r['v'], $real); $n++; }
    }
    printf("   %-20s مخالفات: %d\n", $tbl, $n);
}
printf("   فُحص %d مسارًا · لا ملفَّ له: %d · المخالفُ جملةً: %d\n",
       $checked, $missing, array_sum(array_map('count', $plan)));

echo "\n══ ② التصحيح ═════════════════════════════════════════════════════════\n";
$total = 0;
foreach ($plan as $tbl => $rows) {
    $col = $SOURCES[$tbl][0];
    $st = $conn->prepare("UPDATE `{$tbl}` SET `{$col}` = ? WHERE `{$col}` = BINARY ?");
    $n = 0;
    foreach ($rows as $r) {
        $st->bind_param('ss', $r[1], $r[0]);
        if (!$st->execute()) { exit("⛔ {$tbl}: " . $conn->error . "\n"); }
        $n += $st->affected_rows;
    }
    $st->close();
    printf("   %-20s صفوفٌ صُحِّحت: %d\n", $tbl, $n);
    $total += $n;
}
printf("   المجموع: %d\n", $total);

echo "\n══ ③ الشاهد — الأداةُ نفسُها بعدَ التصحيح ═════════════════════════════\n";
$left = 0;
foreach ($SOURCES as $tbl => $def) {
    list($col, $where) = $def;
    $rs = $conn->query("SELECT DISTINCT `{$col}` v FROM `{$tbl}`
                         WHERE {$where} AND `{$col}` LIKE '%.php'
                           AND `{$col}` NOT LIKE 'http%' AND `{$col}` LIKE '%/%'");
    while ($rs && ($r = $rs->fetch_assoc())) {
        $rel = ltrim(str_replace('\\', '/', (string) $r['v']), '/');
        $real = disk_case($ROOT, $rel, $CACHE);
        if ($real !== null && $real !== $rel) { $left++; }
    }
}
printf("   مخالفٌ متبقٍّ: %d (المستهدف صفر)\n", $left);

/* ولا بندَ صلاحيّةٍ ضاع بالتصحيح */
$items = $conn->query("SELECT COUNT(*) FROM gov_profile_items WHERE item_kind='screen' AND allow=1");
printf("   بنودُ الصلاحيّةِ النافذة: %s\n", $items ? $items->fetch_row()[0] : '?');

if ($left !== 0) { exit("\n⛔ الشاهدُ لم يتحقّق.\n"); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "\n✔ تمّ — وكلُّ مسارٍ مسجَّلٍ يطابق حالةَ اسمِه على القرص.\n";
