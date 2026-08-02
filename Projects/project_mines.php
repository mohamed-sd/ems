<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$redirect = $project_id > 0 ? "oprationprojects.php?id={$project_id}" : "oprationprojects.php";
header("Location: {$redirect}");
exit();
