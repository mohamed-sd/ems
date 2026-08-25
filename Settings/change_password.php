<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-076 · SCN-077 · SCN-078 · SCN-079 · SCN-080 · SCN-081 · SCN-082 · SCN-083 · SCN-084 · SCN-085 · SCN-086 · SCN-087 · SCN-088 · SCN-089 · SCN-090 · SCN-091 · SCN-092 · SCN-093
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';

// ══════════════════════════════════════════════════════════════════════════════
// معالجة نموذج تغيير كلمة السر
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ملاحظة: لا نُمرّر كلمة المرور عبر mysqli_real_escape_string لأن ذلك يغيّر النص الخام
    // المطلوب لـ password_verify()/password_hash()؛ الحماية من الحقن تتم بالعبارات المُجهّزة.
    $user_id          = intval($_SESSION['user']['id']);
    $old_password     = isset($_POST['old_password'])     ? trim($_POST['old_password'])     : '';
    $new_password     = isset($_POST['new_password'])     ? trim($_POST['new_password'])     : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

    // التحقق من طول كلمة السر الجديدة
    if (strlen($new_password) < 6) {
        $error = "كلمة السر الجديدة يجب أن تكون 6 أحرف على الأقل!";

    } elseif ($new_password !== $confirm_password) {
        $error = "كلمة السر الجديدة غير متطابقة!";

    } else {
        // جلب هاش كلمة السر القديمة عبر البوابة (نطاق الشركة آلي؛ includeDeleted
        // حفاظًا على سلوك الأصل الذي لم يرشّح المحذوف ناعمًا لجلسةٍ حيّة)
        $cp_gate = (strval($_SESSION['user']['role'] ?? '') === '-1')
            ? ems_tenant_db()->forAllTenants('change password super') : ems_tenant_db();
        $row = null;
        try {
            $row = $cp_gate->selectOne('users', array('columns' => array('password'),
                'where' => array('id' => $user_id), 'includeDeleted' => true));
        } catch (\Throwable $t) { error_log('change_password.php read: ' . $t->getMessage()); }

        // التحقق من كلمة السر القديمة بـ bcrypt (مطابقٌ لـ login.php)
        if (!$row || !password_verify($old_password, $row['password'])) {
            $error = "كلمة السر القديمة غير صحيحة!";
        } else {
            // تخزين كلمة السر الجديدة مُشفّرة (bcrypt) — مطابقٌ لـ main/users.php
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $cp_ok = false;
            try {
                $cp_gate->update('users', array('password' => $new_hash, 'updated_at' => date('Y-m-d H:i:s')),
                    array('id' => $user_id));
                $cp_ok = true;
            } catch (\Throwable $t) { error_log('change_password.php update: ' . $t->getMessage()); }
            if ($cp_ok) {
                $success = "تم تغيير كلمة السر بنجاح 🎉";
                // تحديث الهاش في الجلسة إذا كان مخزناً
                if (isset($_SESSION['user']['password'])) {
                    $_SESSION['user']['password'] = $new_hash;
                }
            } else {
                $error = "حدث خطأ أثناء تحديث كلمة السر!";
            }
        }
    }
}
?>

<?php
/* AC-U1 · SH-01 — قشرةٌ واحدةٌ: كان هنا رأسٌ محليٌّ كاملٌ بـ<!DOCTYPE>
   و<head> وقائمةِ أنماطٍ خاصة. صار `inheader.php` مصدرَ القشرةِ، فيصل
   هذه الشاشةَ كلُّ تحسينٍ فيها (كاسرُ الذاكرةِ · الرموزُ · الأزرار).
   وما تنفرد به من أنماطٍ منقولٌ أدناه ولم يُنزع. */
$page_title = 'إيكوبيشن | تغيير كلمة السر';
include __DIR__ . '/../inheader.php';
?>
<!-- أنماطٌ تنفرد بها هذه الشاشة (لا يحمّلها inheader) -->
<link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
<link rel="stylesheet" href="../assets/css/main_admin_style.css" />
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>


    <?php 
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include('../insidebar.php'); ?>
<?php require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); } ?>

    <div class="main scr-change-password">

        <?php
        /* UXW-01 ⑨: حزمةُ الحالاتِ الدنيا — مخفيةٌ افتراضًا ويُظهرها منطقُ الشاشةِ عند حالِها */
        echo ems_states_bundle('لا محتوى بيانات لهذه الشاشة — هي نموذج تغيير كلمة السر',
            'أدخل كلمة السر الحالية ثم الجديدة وتأكيدها واحفظ');
        ?>

        <!-- رأس الصفحة -->
        <div class="header">
            <div class="cp-header-title-row">
                <div class="title-icon"><i class="fas fa-key"></i></div>
                <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ العنوانِ اليدويّ. */
$header_icon = 'fas fa-circle';
$header_title_html = htmlspecialchars('تغيير كلمة السر', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
?>
            </div>
            <div class="cp-header-actions">
                <a href="settings.php" class="back-btn">
                    <i class="fas fa-arrow-right"></i> العودة للإعدادات
                </a>
            </div>
        </div>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <div class="alert-icon"><i class="fas fa-exclamation-circle"></i></div>
            <div class="alert-content">
                <strong>خطأ!</strong>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <div class="alert-icon"><i class="fas fa-check-circle"></i></div>
            <div class="alert-content">
                <strong>نجح!</strong>
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- بطاقة النموذج -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-shield-alt"></i> تحديث بيانات الدخول</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="changePasswordForm">
        <?= csrf_field() ?>
                    <div class="form-grid">

                        <!-- كلمة السر القديمة -->
                        <div class="field md-12">
                            <label>
                                <i class="fas fa-lock"></i> كلمة السر القديمة
                                <span class="required">*</span>
                            </label>
                            <div class="control">
                                <input type="password" name="old_password" id="old_password"
                                       required placeholder="أدخل كلمة السر الحالية" aria-label="أدخل كلمة السر الحالية">
                                <i class="fas fa-eye toggle-password" data-target="old_password"></i>
                            </div>
                        </div>

                        <!-- كلمة السر الجديدة -->
                        <div class="field md-12">
                            <label>
                                <i class="fas fa-key"></i> كلمة السر الجديدة
                                <span class="required">*</span>
                            </label>
                            <div class="control">
                                <input type="password" name="new_password" id="new_password"
                                       required placeholder="أدخل كلمة السر الجديدة" minlength="6" aria-label="أدخل كلمة السر الجديدة">
                                <i class="fas fa-eye toggle-password" data-target="new_password"></i>
                            </div>
                            <!-- شريط قوة كلمة المرور -->
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
                            <label>
                                <i class="fas fa-check-double"></i> تأكيد كلمة السر الجديدة
                                <span class="required">*</span>
                            </label>
                            <div class="control">
                                <input type="password" name="confirm_password" id="confirm_password"
                                       required placeholder="أعد إدخال كلمة السر الجديدة" aria-label="أعد إدخال كلمة السر الجديدة">
                                <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
                            </div>
                            <div id="match-message" class="cp-match-msg"></div>
                        </div>

                    </div>

                    <!-- أزرار الفورم -->
                    <div class="form-actions">
                        <button type="submit" class="btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i>
                            <span>حفظ كلمة السر الجديدة</span>
                        </button>
                        <button type="reset" class="btn-secondary">
                            <i class="fas fa-redo"></i>
                            <span>إعادة تعيين</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // ════════════════════════════════════════════════
        // إظهار / إخفاء كلمة السر
        // ════════════════════════════════════════════════
        document.querySelectorAll('.toggle-password').forEach(icon => {
            icon.addEventListener('click', function () {
                const targetId = this.getAttribute('data-target');
                const input    = document.getElementById(targetId);

                if (input.type === 'password') {
                    input.type = 'text';
                    this.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    input.type = 'password';
                    this.classList.replace('fa-eye-slash', 'fa-eye');
                }
            });
        });

        // ════════════════════════════════════════════════
        // فحص قوة كلمة السر
        // ════════════════════════════════════════════════
        const newPasswordInput    = document.getElementById('new_password');
        const strengthBar         = document.getElementById('strengthBar');
        const strengthBarFill     = document.getElementById('strengthBarFill');
        const strengthText        = document.getElementById('strengthText');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const matchMessage        = document.getElementById('match-message');

        // عناصر متطلبات كلمة السر
        const reqLength    = document.getElementById('req-length');
        const reqUppercase = document.getElementById('req-uppercase');
        const reqLowercase = document.getElementById('req-lowercase');
        const reqNumber    = document.getElementById('req-number');

        newPasswordInput.addEventListener('input', function () {
            const password = this.value;

            if (password.length === 0) {
                strengthBar.style.display = 'none';
                strengthText.textContent  = '';
                return;
            }

            strengthBar.style.display = 'block';

            // التحقق من المتطلبات
            const hasLength    = password.length >= 6;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber    = /[0-9]/.test(password);

            // تحديث مؤشرات المتطلبات
            reqLength.classList.toggle('met', hasLength);
            reqUppercase.classList.toggle('met', hasUppercase);
            reqLowercase.classList.toggle('met', hasLowercase);
            reqNumber.classList.toggle('met', hasNumber);

            // حساب مستوى القوة
            let strength = 0;
            if (hasLength)             strength++;
            if (hasUppercase)          strength++;
            if (hasLowercase)          strength++;
            if (hasNumber)             strength++;
            if (password.length >= 10) strength++;

            // تحديث واجهة شريط القوة
            strengthBarFill.className = 'password-strength-bar';
            strengthText.className    = 'password-strength-text';

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

        // ════════════════════════════════════════════════
        // التحقق من تطابق كلمتي السر
        // ════════════════════════════════════════════════
        function checkPasswordMatch() {
            const password = newPasswordInput.value;
            const confirm  = confirmPasswordInput.value;

            if (confirm.length === 0) {
                matchMessage.textContent = '';
                matchMessage.style.color = '';
                return;
            }

            if (password === confirm) {
                matchMessage.innerHTML   = '<i class="fas fa-check-circle"></i> كلمتا السر متطابقتان';
                matchMessage.style.color = 'var(--green)';
            } else {
                matchMessage.innerHTML   = '<i class="fas fa-times-circle"></i> كلمتا السر غير متطابقتين';
                matchMessage.style.color = 'var(--red)';
            }
        }

        // ════════════════════════════════════════════════
        // التحقق عند الإرسال
        // ════════════════════════════════════════════════
        document.getElementById('changePasswordForm').addEventListener('submit', function (e) {
            const newPassword     = newPasswordInput.value;
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

            // تفعيل حالة التحميل على الزر
            const submitBtn = document.getElementById('submitBtn');
            const btnIcon   = submitBtn.querySelector('i');
            const btnText   = submitBtn.querySelector('span');

            submitBtn.classList.add('loading');
            btnIcon.classList.remove('fa-save');
            btnIcon.classList.add('fa-spinner', 'fa-spin');
            btnText.textContent = 'جاري الحفظ...';
        });

        // ════════════════════════════════════════════════
        // إعادة تعيين الفورم
        // ════════════════════════════════════════════════
        document.querySelector('.btn-secondary').addEventListener('click', function () {
            setTimeout(function () {
                strengthBar.style.display = 'none';
                strengthText.textContent  = '';
                matchMessage.textContent  = '';

                // إزالة علامات المتطلبات
                document.querySelectorAll('.requirement-item').forEach(item => {
                    item.classList.remove('met');
                });
            }, 100);
        });

        // ════════════════════════════════════════════════
        // إخفاء رسالة النجاح تلقائياً بعد 5 ثواني
        // ════════════════════════════════════════════════
        <?php if (isset($success)): ?>
        setTimeout(function () {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                successAlert.style.transition = 'opacity 0.5s ease';
                successAlert.style.opacity    = '0';
                setTimeout(() => successAlert.remove(), 500);
            }
        }, 5000);
        <?php endif; ?>
    </script>

</body>
</html>
