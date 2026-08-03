<?php
/**
 * tools/cmp03_gov_check.php — فحص «السبعة الحاكمة» (CMP-03 · الحزام)
 * ───────────────────────────────────────────────────────────────────────────
 * لا شاشةَ مستندٍ مقارنةً ينقصها عمودُ حوكمةٍ يطلبه تصميمُها — أي: لكل شاشةٍ
 * مقارنةٍ missGov = 0 بحكم منهج 99 نفسه. يخرج 1 عند أي مخالفة (يصلح خطافًا).
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
require_once __DIR__ . '/cmp03_lib.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);

$screens = cmp03_doc_screens($ROOT);
$map = cmp03_file_map($conn);
$bad = 0; $checked = 0;
foreach ($screens as $cf => $sc) {
    if (!isset($map[$cf]) || $map[$cf]['state'] === 'soon' || !$map[$cf]['real_path']) { continue; }
    $heads = cmp03_extract_heads($ROOT . '/' . $map[$cf]['real_path']);
    if ($heads === null || !$heads) { continue; } // لوحة — لا تُقارن
    $checked++;
    $j = cmp03_judge($sc['cols'], $heads);
    if ($j['missGov']) {
        $bad++;
        echo "✘ {$sc['title']} ($cf → {$map[$cf]['real_path']}): ناقصُ الحوكمة = " . implode(' · ', $j['missGov']) . "\n";
    }
}
echo "────────────────────────────────────────\n";
echo "شاشاتٌ فُحصت: $checked · مخالفة: $bad\n";
echo $bad === 0 ? "الحكم: ✔ لا شاشةَ مستندٍ بلا حوكمتها الحاكمة\n" : "الحكم: ✘ حوكمةٌ ناقصة\n";
exit($bad === 0 ? 0 : 1);
