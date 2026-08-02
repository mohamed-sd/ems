<?php
/** مسبار فرعي: PermitGate::check بوضع العلم الموروث من تراكب الأب — يطبع JSON. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once dirname(__DIR__) . '/app/Services/Org/PermitGate.php';

$co = intval($argv[1]); $type = (string) $argv[2]; $ref = (string) $argv[3];
$site = intval($argv[4]); $actor = intval($argv[5]);
$g = \App\Services\Org\PermitGate::check($GLOBALS['conn'], $co, $type, $ref, $site, $actor);
echo json_encode($g, JSON_UNESCAPED_UNICODE), "\n";
