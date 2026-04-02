<?php
require_once __DIR__ . '/includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'إعدادات الحساب';
$current_page = 'settings';

$msg = '';

// ── Handle password change ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = 'error:رمز الحماية غير صحيح، حاول مرة أخرى';
    } else {
        $current_pw  = $_POST['current_password'] ?? '';
        $new_pw      = $_POST['new_password']      ?? '';
        $confirm_pw  = $_POST['confirm_password']  ?? '';

        // Load fresh admin record
        $admin_id = intval($admin['id']);
        $row_q    = mysqli_query($conn, "SELECT password FROM super_admins WHERE id = $admin_id");
        $row      = $row_q ? mysqli_fetch_assoc($row_q) : null;

        if (!$row) {
            $msg = 'error:لم يتم العثور على الحساب';
        } elseif (!password_verify($current_pw, $row['password'])) {
            $msg = 'error:كلمة المرور الحالية غير صحيحة';
        } elseif (strlen($new_pw) < 8) {
            $msg = 'error:كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل';
        } elseif ($new_pw !== $confirm_pw) {
            $msg = 'error:كلمة المرور الجديدة وتأكيدها غير متطابقتين';
        } elseif ($new_pw === $current_pw) {
            $msg = 'error:كلمة المرور الجديدة يجب أن تختلف عن الحالية';
        } else {
            $hashed = password_hash($new_pw, PASSWORD_BCRYPT);
            $hashed_esc = mysqli_real_escape_string($conn, $hashed);
            $upd = mysqli_query($conn, "UPDATE super_admins SET password='$hashed_esc', updated_at=NOW() WHERE id=$admin_id");
            if ($upd) {
                $msg = 'success:تم تغيير كلمة المرور بنجاح';
                // Log to audit log
                $ip  = mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR'] ?? '');
                @mysqli_query($conn, "INSERT INTO admin_audit_log (admin_id, action_type, target_name, description, ip_address)
                    VALUES ($admin_id, 'update', 'حساب المدير', 'تغيير كلمة المرور', '$ip')");
            } else {
                $msg = 'error:' . mysqli_error($conn);
            }
        }
    }
}

// ── Load full admin data ──────────────────────────────────────────────────
$admin_id   = intval($admin['id']);
$admin_full = null;
$aq = mysqli_query($conn, "SELECT id, name, email, is_active, last_login_at, created_at, updated_at FROM super_admins WHERE id = $admin_id");
if ($aq) { $admin_full = mysqli_fetch_assoc($aq); }

$csrf = generate_csrf_token();
require_once __DIR__ . '/includes/layout_head.php';
?>

<div class="phead">
    <div>
        <h2>إعدادات الحساب</h2>
        <p class="sub">تغيير كلمة المرور وإدارة بيانات حساب الإدارة العليا</p>
    </div>
</div>

<?php if ($msg):
    $parts = explode(':', $msg, 2);
    $type = isset($parts[0]) ? $parts[0] : 'error';
    $text = isset($parts[1]) ? $parts[1] : $msg;
?>
<div class="alert alert-<?php echo $type === 'success' ? 'success' : 'danger'; ?>" style="margin-bottom:16px;">
    <i class="fas fa-<?php echo $type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <span><?php echo e($text); ?></span>
</div>
<?php endif; ?>

<div class="g2">

    <!-- ── Profile card ────────────────────────────────────────────────── -->
    <div>
        <div class="card" style="margin-bottom:16px;">
            <div class="card-hd"><span class="card-hd-title"><i class="fas fa-user-shield" style="color:var(--blue);margin-left:6px"></i>معلومات الحساب</span></div>
            <div class="card-body">
                <!-- Avatar -->
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;padding-bottom:18px;border-bottom:1px solid var(--line);">
                    <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#0f2240,#2563eb);display:flex;align-items:center;justify-content:center;color:#d6a700;font-size:1.6rem;flex-shrink:0;">
                        <?php echo mb_substr($admin['name'], 0, 1, 'UTF-8'); ?>
                    </div>
                    <div>
                        <div style="font-size:1.05rem;font-weight:800;"><?php echo e($admin_full['name'] ?? $admin['name']); ?></div>
                        <div class="text-muted"><?php echo e($admin_full['email'] ?? $admin['email']); ?></div>
                        <span class="badge bg-gold" style="margin-top:5px;"><i class="fas fa-user-tie"></i> Super Admin</span>
                    </div>
                </div>

                <?php
                $profile_rows = [
                    'الاسم'              => $admin_full['name']          ?? '—',
                    'البريد الإلكتروني'  => $admin_full['email']         ?? '—',
                    'الحالة'             => ($admin_full['is_active'] ?? 1) ? 'نشط' : 'غير نشط',
                    'آخر تسجيل دخول'    => $admin_full['last_login_at']  ? date('d/m/Y H:i', strtotime($admin_full['last_login_at'])) : 'أول تسجيل',
                    'تاريخ إنشاء الحساب' => $admin_full['created_at']    ? date('d/m/Y', strtotime($admin_full['created_at'])) : '—',
                ];
                foreach ($profile_rows as $lbl => $val):
                ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid var(--line);font-size:0.87rem;">
                    <span class="text-muted"><?php echo e($lbl); ?></span>
                    <span style="font-weight:600;"><?php echo e($val); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Security info -->
        <div class="card">
            <div class="card-hd"><span class="card-hd-title"><i class="fas fa-shield-halved" style="color:#059669;margin-left:6px"></i>معلومات الأمان</span></div>
            <div class="card-body">
                <div class="alert alert-success" style="margin-bottom:0;">
                    <i class="fas fa-lock"></i>
                    <div style="font-size:0.84rem;">
                        التحقق يتم بـ <strong>bcrypt</strong> مع CSRF token لكل طلب.<br>
                        الجلسة مستقلة عن باقي المستخدمين عبر <code>$_SESSION['super_admin']</code>.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Password change form ────────────────────────────────────────── -->
    <div class="card">
        <div class="card-hd">
            <span class="card-hd-title"><i class="fas fa-key" style="color:var(--gold);margin-left:6px"></i>تغيير كلمة المرور</span>
        </div>
        <div class="card-body">
            <form method="post" id="pwForm" novalidate>
                <input type="hidden" name="action"     value="change_password">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

                <div class="form-group">
                    <label class="form-label">كلمة المرور الحالية *</label>
                    <div style="position:relative;">
                        <input class="form-ctrl" type="password" name="current_password" id="fCurrent" required
                               autocomplete="current-password" placeholder="أدخل كلمة مرورك الحالية">
                        <button type="button" onclick="togglePw('fCurrent', this)"
                                style="position:absolute;left:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">كلمة المرور الجديدة *</label>
                    <div style="position:relative;">
                        <input class="form-ctrl" type="password" name="new_password" id="fNew" required
                               autocomplete="new-password" placeholder="8 أحرف على الأقل"
                               oninput="checkStrength(this.value)">
                        <button type="button" onclick="togglePw('fNew', this)"
                                style="position:absolute;left:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <!-- Strength indicator -->
                    <div style="margin-top:6px;height:5px;border-radius:999px;background:var(--line);overflow:hidden;">
                        <div id="strengthBar" style="height:100%;width:0;border-radius:999px;transition:all 0.3s;"></div>
                    </div>
                    <p id="strengthText" class="form-hint" style="margin-top:4px;"></p>
                </div>

                <div class="form-group">
                    <label class="form-label">تأكيد كلمة المرور الجديدة *</label>
                    <div style="position:relative;">
                        <input class="form-ctrl" type="password" name="confirm_password" id="fConfirm" required
                               autocomplete="new-password" placeholder="أعد كتابة كلمة المرور">
                        <button type="button" onclick="togglePw('fConfirm', this)"
                                style="position:absolute;left:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="alert alert-warning" style="font-size:0.83rem;">
                    <i class="fas fa-triangle-exclamation"></i>
                    <div>بعد تغيير كلمة المرور، ستحتاج إلى استخدام كلمة المرور الجديدة في تسجيل الدخول القادم.</div>
                </div>

                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
                    <button type="reset" class="btn btn-ghost">إعادة تعيين</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> حفظ كلمة المرور
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function togglePw(inputId, btn) {
    var inp = document.getElementById(inputId);
    var icon = btn.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

function checkStrength(val) {
    var bar  = document.getElementById('strengthBar');
    var text = document.getElementById('strengthText');
    if (!val) { bar.style.width = '0'; text.textContent = ''; return; }

    var score = 0;
    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    var levels = [
        { w:'20%', color:'#dc2626', label:'ضعيفة جدًا' },
        { w:'40%', color:'#d97706', label:'ضعيفة' },
        { w:'60%', color:'#d6a700', label:'متوسطة' },
        { w:'80%', color:'#2563eb', label:'جيدة' },
        { w:'100%',color:'#059669', label:'قوية جدًا' },
    ];
    var lvl = levels[Math.min(score, 4)];
    bar.style.width = lvl.w;
    bar.style.background = lvl.color;
    text.textContent = 'قوة كلمة المرور: ' + lvl.label;
    text.style.color = lvl.color;
}
</script>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
