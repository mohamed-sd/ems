<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_SESSION['user']['id']);
    $old_password = mysqli_real_escape_string($conn, trim($_POST['old_password']));
    $new_password = mysqli_real_escape_string($conn, trim($_POST['new_password']));
    $confirm_password = mysqli_real_escape_string($conn, trim($_POST['confirm_password']));

    // التحقق من طول كلمة السر الجديدة
    if (strlen($new_password) < 6) {
        $error = "كلمة السر الجديدة يجب أن تكون 6 أحرف على الأقل!";
    } elseif ($new_password !== $confirm_password) {
        $error = "كلمة السر الجديدة غير متطابقة!";
    } else {
        // جلب كلمة السر القديمة من قاعدة البيانات
        $query = "SELECT password FROM users WHERE id = $user_id";
        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_assoc($result);

        if (!$row || $old_password != $row['password']) {
            $error = "كلمة السر القديمة غير صحيحة!";
        } else {
            // تحديث كلمة السر
            $update_query = "UPDATE users SET password = '$new_password', updated_at = NOW() WHERE id = $user_id";
            if (mysqli_query($conn, $update_query)) {
                $success = "تم تغيير كلمة السر بنجاح 🎉";
                // تحديث كلمة السر في الجلسة إذا كانت مخزنة
                if (isset($_SESSION['user']['password'])) {
                    $_SESSION['user']['password'] = $new_password;
                }
            } else {
                $error = "حدث خطأ أثناء تحديث كلمة السر!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إيكوبيشن | تغيير كلمة السر</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
  <link rel="stylesheet" href="../assets/css/main_admin_style.css" />
  <style>
    .main {
      padding: 20px;
      max-width: 600px;
      margin: 0 auto;
    }

    .password-strength {
      margin-top: 8px;
      height: 4px;
      background: var(--border);
      border-radius: 2px;
      overflow: hidden;
      display: none;
    }

    .password-strength-bar {
      height: 100%;
      width: 0;
      transition: all 0.3s ease;
      border-radius: 2px;
    }

    .password-strength-text {
      font-size: 0.75rem;
      margin-top: 4px;
      font-weight: 600;
    }

    .strength-weak {
      background: var(--red);
      color: var(--red);
    }

    .strength-medium {
      background: var(--gold);
      color: var(--gold);
    }

    .strength-strong {
      background: var(--green);
      color: var(--green);
    }

    .password-requirements {
      margin-top: 8px;
      padding: 12px;
      background: var(--blue-soft);
      border-radius: var(--radius);
      border-right: 3px solid var(--blue);
    }

    .password-requirements h6 {
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--blue);
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .requirement-item {
      font-size: 0.78rem;
      color: var(--txt-light);
      margin: 4px 0;
      padding-right: 18px;
      position: relative;
    }

    .requirement-item::before {
      content: '○';
      position: absolute;
      right: 0;
      color: var(--txt-light);
      font-weight: 700;
    }

    .requirement-item.met::before {
      content: '✓';
      color: var(--green);
    }

    .toggle-password {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: var(--txt-light);
      font-size: 0.9rem;
      transition: color var(--ease);
    }

    .toggle-password:hover {
      color: var(--navy);
    }

    .field {
      position: relative;
    }

    /* تصميم محسّن للأزرار */
    .form-actions {
      display: flex;
      gap: 14px;
      margin-top: 2.5rem;
      padding-top: 1.5rem;
      border-top: 1.5px solid var(--border);
      justify-content: center;
      flex-wrap: wrap;
    }

    .btn-save {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 14px 32px;
      background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
      color: var(--gold);
      border: none;
      border-radius: 50px;
      font-size: 0.95rem;
      font-weight: 700;
      font-family: 'Cairo', sans-serif;
      cursor: pointer;
      transition: all var(--ease);
      box-shadow: 0 4px 14px rgba(12, 28, 62, 0.25);
      position: relative;
      overflow: hidden;
    }

    .btn-save::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(232, 184, 0, 0.15), transparent);
      transition: left 0.5s ease;
    }

    .btn-save:hover::before {
      left: 100%;
    }

    .btn-save:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(12, 28, 62, 0.35);
      background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
      color: var(--navy);
    }

    .btn-save:active {
      transform: translateY(0);
      box-shadow: 0 2px 8px rgba(12, 28, 62, 0.2);
    }

    .btn-save i {
      font-size: 1.05rem;
      transition: transform var(--ease);
    }

    .btn-save:hover i {
      transform: scale(1.15);
    }

    .btn-reset {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 14px 28px;
      background: var(--surface);
      color: var(--txt);
      border: 2px solid var(--border);
      border-radius: 50px;
      font-size: 0.95rem;
      font-weight: 700;
      font-family: 'Cairo', sans-serif;
      cursor: pointer;
      transition: all var(--ease);
      box-shadow: 0 2px 8px rgba(12, 28, 62, 0.08);
    }

    .btn-reset:hover {
      background: var(--red-soft);
      border-color: var(--red);
      color: var(--red);
      transform: translateY(-2px);
      box-shadow: 0 4px 14px rgba(220, 38, 38, 0.2);
    }

    .btn-reset:active {
      transform: translateY(0);
      box-shadow: 0 2px 6px rgba(220, 38, 38, 0.15);
    }

    .btn-reset i {
      font-size: 1rem;
      transition: transform var(--ease);
    }

    .btn-reset:hover i {
      transform: rotate(-180deg);
    }

    /* Animation for button on page load */
    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .form-actions {
      animation: slideUp 0.4s ease-out 0.2s both;
    }

    /* Mobile responsive */
    @media (max-width: 600px) {
      .form-actions {
        flex-direction: column;
      }
      
      .btn-save,
      .btn-reset {
        width: 100%;
        justify-content: center;
        padding: 13px 24px;
      }
    }

    /* Loading state for submit button */
    .btn-save.loading {
      pointer-events: none;
      opacity: 0.7;
    }

    .btn-save.loading i {
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
  </style>
</head>
<body>

  <?php include('../insidebar.php'); ?>

  <div class="main">
    <!-- Page Header -->
    <div class="page-header">
      <div style="display: flex; align-items: center; gap: 12px;">
        <div class="title-icon"><i class="fas fa-key"></i></div>
        <h1 class="page-title">تغيير كلمة السر</h1>
      </div>
      <div style="display: flex; gap: 10px;">
        <a href="settings.php" class="back-btn">
          <i class="fas fa-arrow-right"></i> العودة للإعدادات
        </a>
      </div>
    </div>

    <?php if(isset($error)): ?>
    <div class="alert alert-danger">
      <div class="alert-icon"><i class="fas fa-exclamation-circle"></i></div>
      <div class="alert-content">
        <strong>خطأ!</strong>
        <p><?php echo htmlspecialchars($error); ?></p>
      </div>
    </div>
    <?php endif; ?>

    <?php if(isset($success)): ?>
    <div class="alert alert-success">
      <div class="alert-icon"><i class="fas fa-check-circle"></i></div>
      <div class="alert-content">
        <strong>نجح!</strong>
        <p><?php echo htmlspecialchars($success); ?></p>
      </div>
    </div>
    <?php endif; ?>

    <!-- Card -->
    <div class="card">
      <div class="card-header">
        <h5><i class="fas fa-shield-alt"></i> تحديث بيانات الدخول</h5>
      </div>
      <div class="card-body">
        <form method="POST" id="changePasswordForm">
          <div class="form-grid">
            <!-- كلمة السر القديمة -->
            <div class="field md-12">
              <label><i class="fas fa-lock"></i> كلمة السر القديمة <span class="required">*</span></label>
              <div class="control">
                <input type="password" name="old_password" id="old_password" required placeholder="أدخل كلمة السر الحالية">
                <i class="fas fa-eye toggle-password" data-target="old_password"></i>
              </div>
            </div>

            <!-- كلمة السر الجديدة -->
            <div class="field md-12">
              <label><i class="fas fa-key"></i> كلمة السر الجديدة <span class="required">*</span></label>
              <div class="control">
                <input type="password" name="new_password" id="new_password" required placeholder="أدخل كلمة السر الجديدة" minlength="6">
                <i class="fas fa-eye toggle-password" data-target="new_password"></i>
              </div>
              <div class="password-strength" id="strengthBar">
                <div class="password-strength-bar" id="strengthBarFill"></div>
              </div>
              <div class="password-strength-text" id="strengthText"></div>
            </div>

            <!-- متطلبات كلمة السر -->
            <div class="field md-12">
              <div class="password-requirements">
                <h6><i class="fas fa-info-circle"></i> متطلبات كلمة السر:</h6>
                <div class="requirement-item" id="req-length">6 أحرف على الأقل</div>
                <div class="requirement-item" id="req-uppercase">حرف كبير واحد على الأقل (A-Z)</div>
                <div class="requirement-item" id="req-lowercase">حرف صغير واحد على الأقل (a-z)</div>
                <div class="requirement-item" id="req-number">رقم واحد على الأقل (0-9)</div>
              </div>
            </div>

            <!-- تأكيد كلمة السر -->
            <div class="field md-12">
              <label><i class="fas fa-check-double"></i> تأكيد كلمة السر الجديدة <span class="required">*</span></label>
              <div class="control">
                <input type="password" name="confirm_password" id="confirm_password" required placeholder="أعد إدخال كلمة السر الجديدة">
                <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
              </div>
              <div id="match-message" style="font-size: 0.82rem; margin-top: 6px; font-weight: 600;"></div>
            </div>
          </div>

          <!-- Buttons -->
          <div class="form-actions">
            <button type="submit" class="btn-save" id="submitBtn">
              <i class="fas fa-save"></i>
              <span>حفظ كلمة السر الجديدة</span>
            </button>
            <button type="reset" class="btn-reset">
              <i class="fas fa-redo"></i>
              <span>إعادة تعيين</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(icon => {
      icon.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        
        if (input.type === 'password') {
          input.type = 'text';
          this.classList.remove('fa-eye');
          this.classList.add('fa-eye-slash');
        } else {
          input.type = 'password';
          this.classList.remove('fa-eye-slash');
          this.classList.add('fa-eye');
        }
      });
    });

    // Password strength checker
    const newPasswordInput = document.getElementById('new_password');
    const strengthBar = document.getElementById('strengthBar');
    const strengthBarFill = document.getElementById('strengthBarFill');
    const strengthText = document.getElementById('strengthText');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const matchMessage = document.getElementById('match-message');
    const submitBtn = document.getElementById('submitBtn');

    // Requirements elements
    const reqLength = document.getElementById('req-length');
    const reqUppercase = document.getElementById('req-uppercase');
    const reqLowercase = document.getElementById('req-lowercase');
    const reqNumber = document.getElementById('req-number');

    newPasswordInput.addEventListener('input', function() {
      const password = this.value;
      
      if (password.length === 0) {
        strengthBar.style.display = 'none';
        strengthText.textContent = '';
        return;
      }

      strengthBar.style.display = 'block';

      // Check requirements
      const hasLength = password.length >= 6;
      const hasUppercase = /[A-Z]/.test(password);
      const hasLowercase = /[a-z]/.test(password);
      const hasNumber = /[0-9]/.test(password);

      // Update requirement indicators
      reqLength.classList.toggle('met', hasLength);
      reqUppercase.classList.toggle('met', hasUppercase);
      reqLowercase.classList.toggle('met', hasLowercase);
      reqNumber.classList.toggle('met', hasNumber);

      // Calculate strength
      let strength = 0;
      if (hasLength) strength++;
      if (hasUppercase) strength++;
      if (hasLowercase) strength++;
      if (hasNumber) strength++;
      if (password.length >= 10) strength++;

      // Update UI
      strengthBarFill.className = 'password-strength-bar';
      strengthText.className = 'password-strength-text';

      if (strength <= 2) {
        strengthBarFill.style.width = '33%';
        strengthBarFill.classList.add('strength-weak');
        strengthText.classList.add('strength-weak');
        strengthText.textContent = 'ضعيفة';
      } else if (strength <= 3) {
        strengthBarFill.style.width = '66%';
        strengthBarFill.classList.add('strength-medium');
        strengthText.classList.add('strength-medium');
        strengthText.textContent = 'متوسطة';
      } else {
        strengthBarFill.style.width = '100%';
        strengthBarFill.classList.add('strength-strong');
        strengthText.classList.add('strength-strong');
        strengthText.textContent = 'قوية';
      }

      checkPasswordMatch();
    });

    confirmPasswordInput.addEventListener('input', checkPasswordMatch);

    function checkPasswordMatch() {
      const password = newPasswordInput.value;
      const confirm = confirmPasswordInput.value;

      if (confirm.length === 0) {
        matchMessage.textContent = '';
        matchMessage.style.color = '';
        return;
      }

      if (password === confirm) {
        matchMessage.innerHTML = '<i class="fas fa-check-circle"></i> كلمات السر متطابقة';
        matchMessage.style.color = 'var(--green)';
      } else {
        matchMessage.innerHTML = '<i class="fas fa-times-circle"></i> كلمات السر غير متطابقة';
        matchMessage.style.color = 'var(--red)';
      }
    }

    // Form validation
    document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
      const newPassword = newPasswordInput.value;
      const confirmPassword = confirmPasswordInput.value;

      if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('❌ كلمة السر الجديدة غير متطابقة!');
        return false;
      }

      if (newPassword.length < 6) {
        e.preventDefault();
        alert('❌ كلمة السر يجب أن تكون 6 أحرف على الأقل!');
        return false;
      }

      // Add loading state to button
      const submitBtn = document.getElementById('submitBtn');
      const btnIcon = submitBtn.querySelector('i');
      const btnText = submitBtn.querySelector('span');
      
      submitBtn.classList.add('loading');
      btnIcon.classList.remove('fa-save');
      btnIcon.classList.add('fa-spinner', 'fa-spin');
      btnText.textContent = 'جاري الحفظ...';
    });

    // Reset button handler
    document.querySelector('.btn-reset').addEventListener('click', function() {
      setTimeout(function() {
        strengthBar.style.display = 'none';
        strengthText.textContent = '';
        matchMessage.textContent = '';
        
        // Reset requirement indicators
        document.querySelectorAll('.requirement-item').forEach(item => {
          item.classList.remove('met');
        });
      }, 100);
    });

    // Auto-hide success message after 5 seconds
    <?php if(isset($success)): ?>
    setTimeout(function() {
      const successAlert = document.querySelector('.alert-success');
      if (successAlert) {
        successAlert.style.transition = 'opacity 0.5s ease';
        successAlert.style.opacity = '0';
        setTimeout(() => successAlert.remove(), 500);
      }
    }, 5000);
    <?php endif; ?>
  </script>

</body>
</html>
