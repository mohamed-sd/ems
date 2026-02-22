<?php
include '../config.php';

$response = ['exists' => false];

if (isset($_GET['username'])) {

    $username = mysqli_real_escape_string($conn, $_GET['username']);
    $uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;

    if ($uid > 0) {
        // في حالة التعديل
        $query = "SELECT id FROM users 
                  WHERE username='$username' 
                  AND id != '$uid' 
                  LIMIT 1";
    } else {
        // في حالة الإضافة
        $query = "SELECT id FROM users 
                  WHERE username='$username' 
                  LIMIT 1";
    }

    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $response['exists'] = true;
    }
}

header('Content-Type: application/json');
echo json_encode($response);

?>