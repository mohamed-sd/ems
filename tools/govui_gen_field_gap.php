<?php
/**
 * tools/govui_gen_field_gap.php — فجوةُ الحقولِ في الأسطحِ المولَّدةِ بعُدّةِ u13
 * ◆ يُخرج لكلِّ سطحٍ مولَّدٍ ناقصٍ: المتطلَّبَ · الشريحةَ · الجدولَ · الناقصَ
 *   كاملًا (لا عيّنةً) · وما هو مقيَّدٌ في gov_field_class · وأعمدةَ الجدول.
 * ⛔ لا يكتب شيئًا.
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
ob_start(); require $ROOT . '/includes/session_bootstrap.php'; require $ROOT . '/config.php'; ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');

$rows = array();
$q = $conn->query("SELECT screen_id, requirement_id, artifact_path, design_applicable, matched, missing_sample
                     FROM repair01_field_measure ORDER BY screen_id");
while ($r = $q->fetch_assoc()) {
    $p = $ROOT . '/' . ltrim((string) $r['artifact_path'], '/');
    if (!is_file($p)) { continue; }
    $src = (string) file_get_contents($p);
    if (strpos($src, 'u13_screen_kit') === false) { continue; }   // المولَّدُ وحدَه
    $miss = (int) $r['design_applicable'] - (int) $r['matched'];
    if ($miss <= 0) { continue; }
    if (preg_match("~u13_screen\(\s*[\x27\x22]([a-z0-9_]+)[\x27\x22]~i", $src, $m)) { $slug = $m[1]; }
    else { $slug = basename($p, '.php'); }
    $tbl = '';
    if (preg_match("~[\x27\x22]table[\x27\x22]\s*=>\s*[\x27\x22]([a-z0-9_]+)[\x27\x22]~i", $src, $m)) { $tbl = $m[1]; }
    $rows[] = array('sid' => $r['screen_id'], 'req' => $r['requirement_id'], 'path' => $r['artifact_path'],
        'app' => (int) $r['design_applicable'], 'hit' => (int) $r['matched'], 'miss' => $miss,
        'slug' => $slug, 'tbl' => $tbl, 'sample' => (string) $r['missing_sample']);
}
usort($rows, function ($a, $b) { return $b['miss'] - $a['miss']; });
$tot = 0; foreach ($rows as $r) { $tot += $r['miss']; }
printf("اسطح مولدة ناقصة: %d · مجموع الناقص: %d\n\n", count($rows), $tot);

foreach ($rows as $r) {
    $gfc = 0; $keys = array();
    if ($r['slug'] !== '') {
        $st = $conn->prepare("SELECT field_key, label_ar FROM gov_field_class WHERE screen_code = ? ORDER BY id");
        $st->bind_param('s', $r['slug']); $st->execute(); $rs = $st->get_result();
        while ($x = $rs->fetch_assoc()) { $gfc++; $keys[$x['field_key']] = $x['label_ar']; }
        $st->close();
    }
    printf("── %s · %s · %s\n   %s\n   المصمَّم %d · مطابق %d · ناقص %d · gov_field_class %d · جدول %s\n",
        $r['sid'], $r['req'], $r['slug'], $r['path'], $r['app'], $r['hit'], $r['miss'], $gfc, $r['tbl'] ?: '—');
    // الناقصُ كاملًا من دفترِ الدليلِ لا من العيّنة
    $st = $conn->prepare("SELECT seq, field_name, field_type FROM repair01_fields WHERE requirement_id = ? ORDER BY CAST(seq AS UNSIGNED)");
    $st->bind_param('s', $r['req']); $st->execute(); $rs = $st->get_result();
    $g = array(); while ($x = $rs->fetch_assoc()) { $g[] = $x; } $st->close();
    printf("   الدليلُ %d حقلًا: %s\n", count($g), implode(' · ', array_map(function ($x) { return $x['field_name']; }, $g)));
    printf("   المقيَّدُ: %s\n\n", $keys ? implode(' · ', array_map(function ($k, $v) { return $k . '=' . $v; }, array_keys($keys), $keys)) : '—');
}
