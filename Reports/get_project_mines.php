<?php
session_start();
while (ob_get_level()) ob_end_clean();
include '../config.php';
header('Content-Type: application/json; charset=utf-8');
// This endpoint is deprecated - mines feature has been removed
echo json_encode(['success' => true, 'mines' => [], 'count' => 0], JSON_UNESCAPED_UNICODE);
