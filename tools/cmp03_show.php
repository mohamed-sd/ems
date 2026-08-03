<?php
/* tools/cmp03_show.php — عرض حكم شاشة واحدة مفصلًا: php tools/cmp03_show.php audit.php */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
require_once __DIR__ . '/cmp03_lib.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$cf = $argv[1] ?? '';
$screens = cmp03_doc_screens($ROOT);
$map = cmp03_file_map($conn);
if (!isset($screens[$cf])) { echo "لا شاشة بهذا الاسم في المستند\n"; exit(1); }
$sc = $screens[$cf];
echo "■ {$sc['title']} ({$cf}) — الورقة المالكة: {$sc['owner']}\n";
echo "أعمدة المستند (" . count($sc['cols']) . "): " . implode(' · ', $sc['cols']) . "\n\n";
if (!isset($map[$cf]) || !$map[$cf]['real_path']) { echo "غير مبنية\n"; exit; }
$heads = cmp03_extract_heads($ROOT . '/' . $map[$cf]['real_path']);
echo "رؤوس النظام (" . count($heads) . "): " . implode(' · ', $heads) . "\n\n";
$j = cmp03_judge($sc['cols'], $heads);
echo "🟦 مطابق (" . count($j['match']) . "): " . implode(' · ', $j['match']) . "\n";
echo "🟨 مرادف (" . count($j['syn']) . "): "; foreach ($j['syn'] as $d => $s) { echo "[$d↔$s] "; } echo "\n";
echo "🟧 حوكمة (" . count($j['missGov']) . "): " . implode(' · ', $j['missGov']) . "\n";
echo "🟧 وظيفي (" . count($j['missFn']) . "): " . implode(' · ', $j['missFn']) . "\n";
echo "🟩 زائد (" . count($j['extra']) . "): " . implode(' · ', $j['extra']) . "\n";
