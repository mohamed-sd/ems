<?php
/**
 * tools/fix_workflow_stages_probe.php — مراحلُ الوثيقةِ مقابلَ مجموعاتِ القاعدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0184 (المالية) · INJ-0552 (المشتريات) · INJ-0375 · INJ-0376 (الموقع)
 *
 * «مجموعاتُ السايدبار لا تطابق مراحلَ الوثيقة» — والوثيقةُ `NAV-09-current.xlsx`
 * هي المصدر. فالمقارنةُ تُشتقُّ منها ولا تُكتب يدويًّا.
 *
 * التشغيل: php tools/fix_workflow_stages_probe.php [--dept=06] [--all]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/tools/nav09_read.php';
require_once $ROOT . '/includes/env.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');

$doc = Nav09Reader::load($ROOT . '/docs/files/NAV-09-current.xlsx');

/* الإدارةُ ← الدور: يُشتقُّ من المجموعاتِ القائمةِ نفسِها (stage_title مطابقٌ للوثيقة) */
$only = null;
foreach ($argv as $a) { if (strpos($a, '--dept=') === 0) { $only = ltrim(substr($a, 7), '0'); } }

$norm = function ($s) {
    $s = preg_replace('~[\x{0640}\x{064B}-\x{0652}]~u', '', (string) $s);   /* تشكيلٌ وتطويل */
    $s = preg_replace('~\s+~u', ' ', $s);
    return trim(mb_strtolower($s));
};

echo "══ مراحلُ الوثيقةِ مقابلَ مجموعاتِ القاعدة ══\n\n";
$grand = array('docStages' => 0, 'dbStages' => 0, 'missing' => 0, 'empty' => 0);

foreach ($doc['depts'] as $no => $d) {
    $n = ltrim((string) $no, '0');
    if ($only !== null && $n !== $only) { continue; }

    /* مراحلُ الوثيقةِ المرقّمةُ (ما عدا ◇ اللوحة) */
    $docStages = array();
    foreach ($d['stages'] as $s) {
        $t = trim((string) $s['title']);
        if ($t === '' || mb_strpos($t, '◇') === 0) { continue; }
        $docStages[$norm($t)] = $t;
    }

    /* الدورُ المالكُ: أكثرُ الأدوارِ مجموعاتٍ تحمل عناوينَ هذه الإدارة */
    $roleGuess = 0; $best = 0;
    $r = $conn->query("SELECT owner_role_id, COUNT(*) c FROM link_groups
                        WHERE stage_title IS NOT NULL AND stage_title <> ''
                        GROUP BY owner_role_id");
    $roles = array();
    while ($r && ($x = $r->fetch_row())) { $roles[] = (int) $x[0]; }
    foreach ($roles as $rid) {
        $hit = 0;
        $q = $conn->query("SELECT DISTINCT stage_title FROM link_groups
                            WHERE owner_role_id = {$rid} AND stage_title IS NOT NULL");
        while ($q && ($x = $q->fetch_row())) {
            foreach ($docStages as $k => $t) {
                if ($norm($x[0]) !== '' && (mb_strpos($norm($t), $norm($x[0])) !== false
                    || mb_strpos($norm($x[0]), $norm($t)) !== false)) { $hit++; break; }
            }
        }
        if ($hit > $best) { $best = $hit; $roleGuess = $rid; }
    }
    if ($roleGuess === 0 || $best < 2) { continue; }

    /* مجموعاتُ القاعدةِ لهذا الدور */
    $dbStages = array();
    $q = $conn->query("SELECT stage_no, stage_title,
                              (SELECT COUNT(*) FROM nav_items ni
                                WHERE ni.group_id IN (SELECT id FROM link_groups g2
                                                       WHERE g2.owner_role_id = {$roleGuess}
                                                         AND g2.stage_no = g.stage_no)
                                  AND ni.role_id = {$roleGuess} AND ni.active = 1) links
                         FROM link_groups g
                        WHERE g.owner_role_id = {$roleGuess} AND g.stage_no IS NOT NULL
                          AND g.stage_no BETWEEN 1 AND 90
                        GROUP BY g.stage_no, g.stage_title ORDER BY g.stage_no");
    while ($q && ($x = $q->fetch_assoc())) {
        $dbStages[(int) $x['stage_no']] = array('title' => (string) $x['stage_title'], 'links' => (int) $x['links']);
    }

    echo '▸ ' . $no . ' ' . $d['name'] . '  (الدور ' . $roleGuess . ")\n";
    echo '   الوثيقة: ' . count($docStages) . ' مرحلةً · القاعدة: ' . count($dbStages) . " مجموعةً مرحليةً\n";
    $grand['docStages'] += count($docStages);
    $grand['dbStages']  += count($dbStages);

    $matched = array();
    foreach ($dbStages as $sn => $g) {
        $hit = null;
        foreach ($docStages as $k => $t) {
            if ($norm($g['title']) !== '' && (mb_strpos($k, $norm($g['title'])) !== false
                || mb_strpos($norm($g['title']), $k) !== false)) { $hit = $k; break; }
        }
        if ($hit !== null) { $matched[$hit] = true; }
        $flag = ($g['links'] === 0) ? '  ⚠ بلا روابط' : '';
        if ($g['links'] === 0) { $grand['empty']++; }
        echo '     م' . str_pad((string) $sn, 2) . ' ' . mb_substr($g['title'], 0, 40)
           . ' — روابط ' . $g['links'] . ($hit === null ? '  ✘ ليست في الوثيقة' : '') . $flag . "\n";
    }
    $missing = array();
    foreach ($docStages as $k => $t) { if (!isset($matched[$k])) { $missing[] = $t; } }
    if ($missing) {
        $grand['missing'] += count($missing);
        echo '     ✘ مراحلُ الوثيقةِ بلا مجموعة (' . count($missing) . "):\n";
        foreach ($missing as $m) { echo '        · ' . mb_substr($m, 0, 60) . "\n"; }
    } else {
        echo "     ✔ كلُّ مراحلِ الوثيقةِ لها مجموعة\n";
    }
    echo "\n";
}

echo '  المجموع: مراحلُ الوثيقةِ ' . $grand['docStages'] . ' · مجموعاتُ القاعدةِ ' . $grand['dbStages']
   . ' · **ناقصةٌ ' . $grand['missing'] . '** · بلا روابطَ ' . $grand['empty'] . "\n";
