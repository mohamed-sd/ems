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

    if (!$row || !password_verify($old_password, $row['password'])) {
        $error = "كلمة السر القديمة غير صحيحة!";
    } elseif ($new_password !== $confirm_password) {
        $error = "كلمة السر الجديدة غير متطابقة!";
    } else {
        // تشفير كلمة السر الجديدة
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET password = '$hashed' WHERE id = $user_id");
        $success = "تم تغيير كلمة السر بنجاح 🎉";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>تغيير كلمة السر</title>
</head>
<body>
<h2>تغيير كلمة السر</h2>
<?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>
<?php if(isset($success)) echo "<p style='color:green'>$success</p>"; ?>
<form method="POST">
    <label>كلمة السر القديمة:</label><br>
    <input type="password" name="old_password" required><br><br>
    
    <label>كلمة السر الجديدة:</label><br>
    <input type="password" name="new_password" required><br><br>
    
    <label>تأكيد كلمة السر الجديدة:</label><br>
    <input type="password" name="confirm_password" required><br><br>
    
    <button type="submit">تغيير</button>
</form>
</body>
</html>
