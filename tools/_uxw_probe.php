<?php
/** tools/_uxw_probe.php — مسبارُ قراءةٍ للقياس: SQL واحدٌ من argv يُطبع JSON سطرًا سطرًا. قراءةٌ فقط. */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$sql = isset($argv[1]) ? $argv[1] : '';
if (!preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $sql)) { fwrite(STDERR, "قراءةٌ فقط\n"); exit(2); }
$r = $conn->query($sql);
if ($r === false) { fwrite(STDERR, $conn->error . "\n"); exit(1); }
while ($x = $r->fetch_assoc()) echo json_encode($x, JSON_UNESCAPED_UNICODE), PHP_EOL;
