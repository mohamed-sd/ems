<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "equipation_manage";

// Establish Connection
$conn = new mysqli($host, $user, $pass, $db);

// Check Connection
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

?>