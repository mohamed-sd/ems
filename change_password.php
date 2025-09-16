<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user']['id'];
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // جلب كلمة السر القديمة من قاعدة البيانات
    $query = "SELECT password FROM users WHERE id = $user_id";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);

    if (!$row || $old_password != $row['password']) {
        $error = "كلمة السر القديمة غير صحيحة!";
    } elseif ($new_password !== $confirm_password) {
        $error = "كلمة السر الجديدة غير متطابقة!";
    } else {
        mysqli_query($conn, "UPDATE users SET password = '$new_password' WHERE id = $user_id");
        $success = "تم تغيير كلمة السر بنجاح 🎉";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تغيير كلمة السر</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="assets/css/style.css"/>
  <style>
    .main {
        padding: 20px;
    }
    .card {
        max-width: 450px;
        margin: 40px auto;
        background: #fff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .card h2 {
        text-align: center;
        margin-bottom: 20px;
        color: #333;
    }
    .form-group {
        margin-bottom: 15px;
    }
    label {
        display: block;
        margin-bottom: 6px;
        font-weight: bold;
        color: #555;
    }
    input[type="password"] {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
    }
    button {
        width: 100%;
        padding: 12px;
        background: #000022;
        color: #ffcc00;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
        transition: 0.3s;
    }
    button:hover {
        background: #000;
    }
    .alert {
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 15px;
        font-size: 14px;
        text-align: center;
    }
    .alert.error {
        background: #ffe5e5;
        color: #d93025;
        border: 1px solid #f5c2c2;
    }
    .alert.success {
        background: #e5ffe9;
        color: #188038;
        border: 1px solid #b7e2c7;
    }
  </style>
</head>
<body>

  <?php include('sidebar.php'); ?>

  <div class="main">
    <div class="card">
        <h2><i class="fa-solid fa-key"></i> تغيير كلمة السر</h2>

        <?php if(isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if(isset($success)): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label><i class="fa-solid fa-lock"></i> كلمة السر القديمة:</label>
                <input type="password" name="old_password" required>
            </div>

            <div class="form-group">
                <label><i class="fa-solid fa-key"></i> كلمة السر الجديدة:</label>
                <input type="password" name="new_password" required>
            </div>

            <div class="form-group">
                <label><i class="fa-solid fa-check"></i> تأكيد كلمة السر الجديدة:</label>
                <input type="password" name="confirm_password" required>
            </div>

            <button type="submit"><i class="fa-solid fa-rotate"></i> تغيير</button>
        </form>
    </div>
  </div>

</body>
</html>
