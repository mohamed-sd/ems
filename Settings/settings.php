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
	<title> إيكوبيشن | الإعدادات </title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
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

		/* Back Button (زر الرجوع) */
		.back-btn {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			padding: 10px 20px;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			text-decoration: none;
			border-radius: 10px;
			font-weight: 600;
			transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
			box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
			font-size: 14px;
		}

		.back-btn:hover {
			transform: translateY(-2px);
			box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
			color: white;
			background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
		}

		.back-btn i {
			font-size: 14px;
			transition: transform 0.3s ease;
		}

		.back-btn:hover i {
			transform: translateX(3px);
		}
	</style>
</head>

<body>

	<?php include('../insidebar.php'); ?>

	<div class="main">

		<div style="text-align: left; margin-bottom: 1.5rem;">
			<a href="../main/dashboard.php" class="back-btn">
				<i class="fas fa-arrow-right"></i> رجوع
			</a>
		</div>

		<h2> الإعدادات </h2>
		<br /><br />
		<a href="change_password.php" class="settings-btn">
			<i class="fa-solid fa-key"></i>
			تغيير كلمة المرور
		</a>

		<!-- Check if user in project manager -->
		<?php if ($_SESSION['user']['role'] == "1") { ?>
			<br /><br />
			<a href="roles.php" class="settings-btn">
				<i class="fa-solid fa-user-shield"></i>
				صلاحيات المستخدمين
			</a>
		<?php } ?>

	</div>

</body>

</html>