<?php
require_once __DIR__ . '/auth.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$success = '';
$error = '';

function find_plan_id_by_name($conn, $name) {
    if (!company_table_exists('admin_subscription_plans')) {
        return null;
    }

    $planName = trim($name);
    $stmt = mysqli_prepare($conn, 'SELECT id FROM admin_subscription_plans WHERE LOWER(plan_name) = LOWER(?) LIMIT 1');
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 's', $planName);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return $row ? intval($row['id']) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $error = 'رمز الحماية غير صالح. أعد تحميل الصفحة.';
    } else {
        $companyName = trim(isset($_POST['company_name']) ? $_POST['company_name'] : '');
        $officialEmail = trim(isset($_POST['official_email']) ? $_POST['official_email'] : '');
        $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
        $responsibleName = trim(isset($_POST['responsible_name']) ? $_POST['responsible_name'] : '');
        $package = trim(isset($_POST['package']) ? $_POST['package'] : '');
        $notes = trim(isset($_POST['notes']) ? $_POST['notes'] : '');

        $allowedPackages = array('Starter', 'Professional', 'Enterprise');

        if (!validate_length($companyName, 2, 200)) {
            $error = 'اسم الشركة مطلوب.';
        } elseif (!validate_email($officialEmail) || !validate_length($officialEmail, 5, 150)) {
            $error = 'البريد الرسمي غير صالح.';
        } elseif (!validate_length($phone, 6, 30)) {
            $error = 'رقم الهاتف مطلوب.';
        } elseif (!validate_length($responsibleName, 2, 150)) {
            $error = 'اسم المسؤول مطلوب.';
        } elseif (!in_array($package, $allowedPackages, true)) {
            $error = 'الباقة المطلوبة غير صالحة.';
        } elseif (!validate_length($notes, 0, 1000)) {
            $error = 'الملاحظات طويلة جداً.';
        } elseif (!company_table_exists('admin_subscription_requests')) {
            $error = 'جدول طلبات الاشتراك غير موجود. شغّل ملف database/admin_saas_tables.sql أولاً.';
        } else {
            $message = 'المسؤول: ' . $responsibleName;
            if ($notes !== '') {
                $message .= "\n" . $notes;
            }

            $planId = find_plan_id_by_name($conn, $package);

            $insertStmt = mysqli_prepare(
                $conn,
                'INSERT INTO admin_subscription_requests (company_name, email, phone, plan_id, message, status) VALUES (?, ?, ?, ?, ?, "pending")'
            );

            if (!$insertStmt) {
                $error = 'تعذر حفظ الطلب حالياً.';
            } else {
                mysqli_stmt_bind_param($insertStmt, 'sssis', $companyName, $officialEmail, $phone, $planId, $message);
                $ok = mysqli_stmt_execute($insertStmt);
                $reqId = $ok ? intval(mysqli_insert_id($conn)) : 0;
                mysqli_stmt_close($insertStmt);

                if (!$ok) {
                    $error = 'فشل إرسال الطلب. حاول مرة أخرى.';
                } else {
                    // Notification for super admin via admin audit trail.
                    if (company_table_exists('admin_audit_log')) {
                        $desc = 'طلب اشتراك جديد من ' . $companyName . ' - الباقة: ' . $package . ' - المسؤول: ' . $responsibleName;
                        $ip = isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 45) : '';
                        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 300) : '';

                        $auditStmt = @mysqli_prepare(
                            $conn,
                            'INSERT INTO admin_audit_log (admin_id, action_type, target_name, target_id, description, ip_address, user_agent) VALUES (NULL, "new_subscription_request", ?, ?, ?, ?, ?)'
                        );
                        if ($auditStmt) {
                            $targetName = $companyName;
                            mysqli_stmt_bind_param($auditStmt, 'sisss', $targetName, $reqId, $desc, $ip, $ua);
                            @mysqli_stmt_execute($auditStmt);
                            mysqli_stmt_close($auditStmt);
                        }
                    }

                    $success = 'تم استلام طلب الاشتراك بنجاح. سيتم مراجعته من الإدارة العليا قريباً.';
                    $_POST = array();
                }
            }
        }
    }
}

$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلب اشتراك جديد | EMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ink: #102443;
            --ink-2: #30527f;
            --gold: #d6a700;
            --bg: #eef2f8;
            --card: #ffffff;
            --line: rgba(16,36,67,0.1);
            --ok: #0f8a5f;
            --danger: #c0392b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Cairo', sans-serif;
            background: radial-gradient(circle at top left, rgba(214,167,0,0.14), transparent 30%), linear-gradient(135deg, #edf2f8, #f7f9fc 60%, #eef1f7);
            color: var(--ink);
            display: grid;
            place-items: center;
            padding: 24px;
        }
        .shell {
            width: 100%;
            max-width: 980px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-radius: 26px;
            overflow: hidden;
            box-shadow: 0 20px 58px rgba(16,36,67,0.15);
            border: 1px solid rgba(255,255,255,0.7);
            background: rgba(255,255,255,0.86);
            backdrop-filter: blur(9px);
        }
        .hero {
            padding: 42px;
            background: linear-gradient(155deg, var(--ink), #173456 56%, #1f466f);
            color: #fff;
            position: relative;
        }
        .hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255,255,255,0.08) 1px, transparent 1px);
            background-size: 22px 22px;
            pointer-events: none;
        }
        .hero-badge {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border-radius: 999px;
            padding: 8px 14px;
            background: rgba(214,167,0,0.16);
            border: 1px solid rgba(214,167,0,0.35);
            color: #f4d064;
            font-weight: 800;
            font-size: 0.88rem;
        }
        .hero h1 {
            margin: 24px 0 12px;
            font-size: 2rem;
            line-height: 1.35;
            position: relative;
            z-index: 1;
        }
        .hero p {
            margin: 0;
            position: relative;
            z-index: 1;
            color: rgba(255,255,255,0.82);
            line-height: 1.9;
        }
        .hero-list {
            margin-top: 26px;
            display: grid;
            gap: 10px;
            position: relative;
            z-index: 1;
        }
        .hero-item {
            background: rgba(255,255,255,0.09);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
        }
        .hero-item i { color: #f4d064; }

        .form-area {
            padding: 34px 28px;
            background: rgba(255,255,255,0.9);
        }
        .form-area h2 {
            margin: 0 0 6px;
            font-size: 1.45rem;
        }
        .form-area .sub {
            margin: 0 0 18px;
            color: #61738f;
            font-size: 0.92rem;
        }
        .alert {
            border-radius: 12px;
            padding: 11px 13px;
            margin-bottom: 14px;
            font-size: 0.92rem;
        }
        .ok { background: rgba(15,138,95,0.1); color: var(--ok); border: 1px solid rgba(15,138,95,0.2); }
        .err { background: rgba(192,57,43,0.1); color: var(--danger); border: 1px solid rgba(192,57,43,0.2); }

        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .field { margin-bottom: 12px; }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 700;
            color: var(--ink-2);
            font-size: 0.83rem;
        }
        input, select, textarea {
            width: 100%;
            border-radius: 12px;
            border: 1px solid var(--line);
            padding: 11px 12px;
            font-family: inherit;
            font-size: 0.95rem;
            background: #fff;
            color: var(--ink);
        }
        textarea { min-height: 88px; resize: vertical; }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: rgba(214,167,0,0.8);
            box-shadow: 0 0 0 4px rgba(214,167,0,0.13);
        }
        .actions {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            margin-top: 10px;
        }
        .btn {
            border: none;
            border-radius: 12px;
            padding: 12px 16px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--ink), #1f4f77);
            color: #fff;
            box-shadow: 0 12px 26px rgba(16,36,67,0.22);
        }
        .link {
            color: var(--ink-2);
            font-weight: 700;
            font-size: 0.87rem;
            text-decoration: none;
        }
        @media (max-width: 920px) {
            .shell { grid-template-columns: 1fr; }
            .hero { display: none; }
        }
        @media (max-width: 560px) {
            .grid2 { grid-template-columns: 1fr; }
            .form-area { padding: 26px 18px; }
        }
    </style>
</head>
<body>
<div class="shell">
    <section class="hero">
        <div class="hero-badge"><i class="fas fa-rocket"></i> بوابة الاشتراك</div>
        <h1>قدّم طلب اشتراك شركتك خلال أقل من دقيقة</h1>
        <p>هذا الرابط مستقل تماماً عن لوحة الإدارة العليا. بعد الإرسال يتم إنشاء طلب بحالة pending ويظهر مباشرة لفريق Super Admin للمراجعة.</p>
        <div class="hero-list">
            <div class="hero-item"><i class="fas fa-building"></i> تسجيل بيانات الشركة الأساسية</div>
            <div class="hero-item"><i class="fas fa-envelope"></i> البريد الرسمي سيصبح بريد المدير العام بعد التفعيل</div>
            <div class="hero-item"><i class="fas fa-list-check"></i> اختيار الباقة: Starter / Professional / Enterprise</div>
        </div>
    </section>

    <section class="form-area">
        <h2>طلب اشتراك جديد</h2>
        <p class="sub">املأ الحقول ثم أرسل الطلب للإدارة العليا.</p>

        <?php if ($success !== ''): ?>
            <div class="alert ok"><?php echo e($success); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert err"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

            <div class="field">
                <label for="company_name">اسم الشركة *</label>
                <input id="company_name" name="company_name" maxlength="200" required value="<?php echo isset($_POST['company_name']) ? e($_POST['company_name']) : ''; ?>">
            </div>

            <div class="grid2">
                <div class="field">
                    <label for="official_email">البريد الرسمي *</label>
                    <input id="official_email" name="official_email" type="email" maxlength="150" required value="<?php echo isset($_POST['official_email']) ? e($_POST['official_email']) : ''; ?>">
                </div>
                <div class="field">
                    <label for="phone">رقم الهاتف *</label>
                    <input id="phone" name="phone" maxlength="30" required value="<?php echo isset($_POST['phone']) ? e($_POST['phone']) : ''; ?>">
                </div>
            </div>

            <div class="grid2">
                <div class="field">
                    <label for="responsible_name">اسم المسؤول *</label>
                    <input id="responsible_name" name="responsible_name" maxlength="150" required value="<?php echo isset($_POST['responsible_name']) ? e($_POST['responsible_name']) : ''; ?>">
                </div>
                <div class="field">
                    <label for="package">الباقة المطلوبة *</label>
                    <select id="package" name="package" required>
                        <option value="">اختر الباقة</option>
                        <?php
                        $packages = array('Starter', 'Professional', 'Enterprise');
                        $selectedPackage = isset($_POST['package']) ? $_POST['package'] : '';
                        foreach ($packages as $pkg) {
                            $sel = ($selectedPackage === $pkg) ? 'selected' : '';
                            echo '<option value="' . e($pkg) . '" ' . $sel . '>' . e($pkg) . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="field">
                <label for="notes">ملاحظات (اختياري)</label>
                <textarea id="notes" name="notes" maxlength="1000"><?php echo isset($_POST['notes']) ? e($_POST['notes']) : ''; ?></textarea>
            </div>

            <div class="actions">
                <a class="link" href="<?php echo e(company_url('register.php')); ?>">تسجيل شركة مباشرة</a>
                <button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane"></i> إرسال الطلب</button>
            </div>
        </form>
    </section>
</div>
</body>
</html>
