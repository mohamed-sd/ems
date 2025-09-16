<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
  	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>  إيكوبيشن | الإعدادات </title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
	<link rel="stylesheet" type="text/css" href="assets/css/style.css"/>
	<style>
		.settings-btn {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			padding: 10px 16px;
			background: #000022;
			color: #ffcc00;
			border: none;
			border-radius: 8px;
			text-decoration: none;
			font-size: 16px;
			transition: 0.3s;
		}
		.settings-btn:hover {
			background: #ffcc00;
      color: #000022;
		}
	</style>
</head>
<body>

  <?php include('sidebar.php'); ?>

  <div class="main">

    <h2> الإعدادات </h2>

    <a href="change_password.php" class="settings-btn">
        <i class="fa-solid fa-key"></i>
        تغيير كلمة المرور
    </a>
    
  </div>

</body>
</html>
