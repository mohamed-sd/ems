<?php
require_once __DIR__ . '/auth.php';
company_require_login();

$user = company_current_user();
$role = isset($user['role']) ? strval($user['role']) : '';
$isManager = ($role === '1');

$planName = isset($_SESSION['plan_modules']['plan_name']) ? $_SESSION['plan_modules']['plan_name'] : 'غير محددة';
$companyName = isset($user['company_name']) ? $user['company_name'] : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة الشركة | EMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --ink:#102443; --ink2:#30527f; --gold:#d6a700; --line:rgba(16,36,67,.1); }
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;font-family:'Cairo',sans-serif;background:radial-gradient(circle at top left,rgba(214,167,0,.16),transparent 30%),linear-gradient(135deg,#edf2f8,#f8fafd);color:var(--ink);padding:24px}
        .wrap{max-width:980px;margin:0 auto}
        .top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px}
        .title h1{margin:0;font-size:1.5rem}
        .title p{margin:4px 0 0;color:#627791}
        .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;text-decoration:none;font-weight:800;font-size:.9rem}
        .btn-main{background:linear-gradient(135deg,var(--ink),#1f4f77);color:#fff}
        .btn-ghost{border:1px solid var(--line);color:var(--ink2);background:#fff}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px;box-shadow:0 10px 24px rgba(16,36,67,.08)}
        .card h2{margin:0 0 8px;font-size:1.06rem}
        .meta{color:#627791;font-size:.9rem;line-height:1.8}
        .links{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
        @media(max-width:760px){.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div class="title">
            <h1>مرحباً <?php echo e($user['name']); ?></h1>
            <p>شركة: <?php echo e($companyName); ?> | الباقة: <?php echo e($planName); ?></p>
        </div>
        <div class="links">
            <a class="btn btn-main" href="/ems/main/dashboard.php"><i class="fas fa-gauge-high"></i> لوحة النظام</a>
            <a class="btn btn-ghost" href="<?php echo e(company_url('logout.php')); ?>"><i class="fas fa-right-from-bracket"></i> تسجيل الخروج</a>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h2><i class="fas fa-users-gear" style="color:#2563eb"></i> إدارة المستخدمين</h2>
            <div class="meta">يمكن للمدير العام (مدير المشاريع) إضافة بقية المدراء والمستخدمين داخل نفس الشركة مع تحديد الدور والحالة.</div>
            <div class="links" style="margin-top:14px;">
                <?php if ($isManager): ?>
                    <a class="btn btn-main" href="<?php echo e(company_url('team.php')); ?>"><i class="fas fa-user-plus"></i> إدارة فريق الشركة</a>
                <?php else: ?>
                    <span class="btn btn-ghost"><i class="fas fa-lock"></i> متاح للمدير العام فقط</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h2><i class="fas fa-diagram-project" style="color:#d97706"></i> إدارة الكيانات</h2>
            <div class="meta">بعد الدخول يمكنك استخدام شاشات النظام الحالية لإضافة العملاء والمشاريع والمناجم وبقية الكيانات حسب صلاحيات الدور.</div>
            <div class="links" style="margin-top:14px;">
                <a class="btn btn-main" href="/ems/main/dashboard.php"><i class="fas fa-arrow-left"></i> متابعة إلى لوحة النظام</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
