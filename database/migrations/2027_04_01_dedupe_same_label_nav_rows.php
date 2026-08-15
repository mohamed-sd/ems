<?php
/**
 * 2027_04_01_dedupe_same_label_nav_rows.php
 * ═══════════════════════════════════════════════════════════════════════════
 * حذفُ صفِّ تنقّلٍ يكرّر **الملفَّ والتسميةَ معًا** — ⇐ INJ-0513 · INJ-0512
 *                                                    · INJ-0132 · INJ-0154 · INJ-0428
 *
 * القاعدة: صفّانِ في قائمةِ **دورٍ واحد** يقصدان الملفَّ نفسَه **بالتسميةِ
 * نفسِها** لا يميّزهما شيءٌ للمستخدم — فأحدُهما زائدٌ يُحذف (ويُؤرشَف).
 *
 * ◆ **ولا يُحذف صفّانِ بتسميتين مختلفتين**: ذاك سؤالُ تسميةٍ لا تكرارٌ ميكانيكيّ
 *   («البلاغات المتكررة وسببها الجذر» و«البلاغات الدورية المولَّدة» على ملفٍّ
 *   واحد) — يُرفع لمالكِ النطاقِ في `docs/fix_progress/OWNER_DECISIONS_DUP.md`.
 * ◆ ويبقى الحارسُ في `printNavLinkItem` هو خطَّ الدفاعِ الأخير: لو عاد الصفُّ
 *   من بذرةٍ لاحقةٍ لم يُطبع مرتين.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ حذفُ صفوفِ التنقّلِ المكرَّرةِ بالملفِّ والتسمية ══\n\n";

$norm = function ($r) {
    $r = explode('#', (string) $r, 2)[0];
    $r = explode('?', $r, 2)[0];
    $r = preg_replace('~^(\.\./)+~', '', $r);
    return strtolower(trim(ltrim((string) $r, '/')));
};

$groups = array();
$r = $conn->query('SELECT id, role_id, label_ar, route FROM nav_items ORDER BY role_id, id');
while ($r && ($x = $r->fetch_assoc())) {
    $k = (int) $x['role_id'] . '|' . $norm($x['route']) . '|'
       . trim(preg_replace('~\s+~u', ' ', (string) $x['label_ar']));
    $groups[$k][] = $x;
}
$dupRows = array(); $diffLabel = 0;
foreach ($groups as $k => $g) { if (count($g) > 1) { $dupRows = array_merge($dupRows, array_slice($g, 1)); } }

/* وللإعلانِ فقط: كم زوجًا بملفٍّ واحدٍ **وتسميتين** — يُرفع ولا يُحذف */
$byFile = array();
$r = $conn->query('SELECT role_id, label_ar, route FROM nav_items');
while ($r && ($x = $r->fetch_assoc())) {
    $byFile[(int) $x['role_id'] . '|' . $norm($x['route'])][trim((string) $x['label_ar'])] = true;
}
foreach ($byFile as $labels) { if (count($labels) > 1) { $diffLabel++; } }

echo '  صفوفٌ مكرَّرةٌ بالملفِّ والتسمية: ' . count($dupRows) . "\n";
echo '  وأزواجٌ بملفٍّ واحدٍ وتسميتين (تُرفع للمالكِ ولا تُحذف): ' . $diffLabel . "\n\n";
if (!$dupRows) { echo "  · لا شيءَ يُحذف — الهجرةُ مطبَّقةٌ سلفًا\n"; exit(0); }

$conn->query('CREATE TABLE IF NOT EXISTS nav_items_archive_dupes LIKE nav_items');
$done = 0;
foreach ($dupRows as $x) {
    $id = (int) $x['id'];
    $conn->query('INSERT INTO nav_items_archive_dupes SELECT * FROM nav_items WHERE id = ' . $id);
    if ($conn->query('DELETE FROM nav_items WHERE id = ' . $id) && $conn->affected_rows > 0) {
        $done++;
        echo '  ✔ حُذف #' . $id . ' — دور ' . $x['role_id'] . ' «'
           . mb_substr((string) $x['label_ar'], 0, 30) . '»' . "\n";
    }
}
echo "\n  المحذوف: {$done} · المؤرشف في `nav_items_archive_dupes`\n";
exit(0);
