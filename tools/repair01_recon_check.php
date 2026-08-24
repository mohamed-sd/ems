<?php
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
require_once __DIR__ . '/lib/xlsx_io.php';
$conn = $GLOBALS['conn']; mysqli_set_charset($conn, 'utf8mb4');

/* ① الحيّ */
$live = array(); $liveFiles = array();
$r = $conn->query("SELECT dept_name, screen_title, screen_file, resp_role, output_doc, next_state FROM gov_screen_cycle");
while ($x = $r->fetch_assoc()) { $live[] = $x; if (trim($x['screen_file']) !== '') $liveFiles[trim($x['screen_file'])] = 1; }
echo "① gov_screen_cycle حيًّا: " . count($live) . " صفًّا · ملفّاتٌ متفرّدة: " . count($liveFiles) . "\n";

/* ② الملفُّ 10 شيت 02 */
$wb = xlsx_read(__DIR__ . '/../docs/REPAIR01_20260823/10 · المصالحة مع النظام.xlsx');
$s = $wb['02_شاشة_بشاشة']; // رأس ص2 : الوحدة | الشاشة المبنية | الملف | ...
$docFiles = array(); $docRows = 0;
foreach ($s as $ri => $row) {
    if ($ri <= 1) continue;
    $f = trim(isset($row[2]) ? $row[2] : '');
    if ($f === '') continue;
    $docRows++; $docFiles[$f] = 1;
}
echo "② الملفّ 10 › 02_شاشة_بشاشة: $docRows صفًّا · ملفّاتٌ متفرّدة: " . count($docFiles) . "\n";

/* ③ التقاطع */
$both = count(array_intersect_key($liveFiles, $docFiles));
echo "③ مشترك: $both  ·  حيٌّ بلا وثيقة: " . count(array_diff_key($liveFiles,$docFiles)) . "  ·  وثيقةٌ بلا حيّ: " . count(array_diff_key($docFiles,$liveFiles)) . "\n";
$d1 = array_slice(array_keys(array_diff_key($liveFiles,$docFiles)),0,8);
$d2 = array_slice(array_keys(array_diff_key($docFiles,$liveFiles)),0,8);
if ($d1) echo "   حيٌّ بلا وثيقة (عيّنة): " . implode(' · ', $d1) . "\n";
if ($d2) echo "   وثيقةٌ بلا حيّ (عيّنة): " . implode(' · ', $d2) . "\n";

/* ④ حقولُ الوثيقة الحاكمة: الدور المسؤول والمستند */
$noRole = 0; $noDoc = 0; $noNext = 0;
foreach ($live as $x) {
    if (trim($x['resp_role']) === '' || trim($x['resp_role']) === '—') $noRole++;
    if (trim($x['output_doc']) === '' || trim($x['output_doc']) === '—') $noDoc++;
    if (trim($x['next_state']) === '' || trim($x['next_state']) === '—') $noNext++;
}
$T = count($live);
printf("④ في الحيِّ: بلا دورٍ مسؤول %d (%.1f%%) · بلا مستندٍ ناتج %d (%.1f%%) · بلا حالةٍ تالية %d (%.1f%%)\n",
    $noRole, 100*$noRole/$T, $noDoc, 100*$noDoc/$T, $noNext, 100*$noNext/$T);
