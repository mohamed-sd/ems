<!DOCTYPE html>
<html dir="rtl">
<head>
	<title> إيكوبيشن | تسجيل الدخول </title>
	<link rel="stylesheet" type="text/css" href="assets/css/style.css"/>
</head>
<style type="text/css">
	
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: "Cairo", sans-serif;
    }

    body {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      background-color: #000022;
    }
</style>
<body>
  <div class="login-container">
  	<h1 style="font-size: 30px;" class="logo">Equipation Panel</h2>
    <h2>تسجيل الدخول</h2>
    <form action="dashbourd.php">
      <input type="text" placeholder="اسم المستخدم" required>
      <input type="password" placeholder="كلمة المرور" required>
      <button type="submit">دخول</button>
    </form>
    <div class="forgot">
      <!-- <a href="#">نسيت كلمة المرور؟</a> -->
    </div>
  </div>
</body>
</html>