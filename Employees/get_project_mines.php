<?php
session_start();
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
// This endpoint is deprecated - mines feature has been removed
echo json_encode(['success' => true, 'mines' => []], JSON_UNESCAPED_UNICODE);
exit;

exit;
?>
