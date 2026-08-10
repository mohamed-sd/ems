<?php
require_once __DIR__ . '/includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'إعدادات الحساب';
$current_page = 'settings';

$msg    = '';
$db_msg = '';

// ═══════════════════════════════════════════════════════════════════════════
// أدوات قاعدة البيانات (سوبر أدمن حصرًا) — نسخ احتياطي / استيراد / استعادة
// محصورة في قاعدة EMS وحدها؛ التنزيل يبثّ الملف وينهي التنفيذ قبل أي إخراج للصفحة.
// ═══════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/includes/db_tools.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strncmp((string) ($_POST['action'] ?? ''), 'db_', 3) === 0) {
    $act = (string) $_POST['action'];
    $aid = intval($admin['id']);

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $db_msg = 'error:رمز الحماية غير صحيح، حاول مرة أخرى';
    } elseif ($act === 'db_backup') {
        $err  = '';
        $path = ems_dbtool_backup($err);
        if ($path) {
            super_admin_write_audit($aid, 'backup', 'قاعدة البيانات', 'إنشاء نسخة احتياطية: ' . basename($path));
            ems_dbtool_stream_download($path); // ينظّف المخازن ويبثّ ثم exit
        }
        $db_msg = 'error:' . $err;
    } elseif ($act === 'db_download') {
        $path = ems_dbtool_resolve_backup($_POST['file'] ?? '');
        if ($path) {
            ems_dbtool_stream_download($path);
        }
        $db_msg = 'error:ملف النسخة غير موجود أو غير صالح';
    } elseif ($act === 'db_delete') {
        $path = ems_dbtool_resolve_backup($_POST['file'] ?? '');
        if ($path && @unlink($path)) {
            super_admin_write_audit($aid, 'delete', 'قاعدة البيانات', 'حذف نسخة احتياطية: ' . basename($path));
            $db_msg = 'success:تم حذف النسخة الاحتياطية';
        } else {
            $db_msg = 'error:تعذّر حذف النسخة (ملف غير موجود أو غير صالح)';
        }
    } elseif ($act === 'db_restore') {
        if (empty($_POST['confirm_replace'])) {
            $db_msg = 'error:يجب تأكيد الاستبدال قبل الاستعادة';
        } else {
            $path = ems_dbtool_resolve_backup($_POST['file'] ?? '');
            if (!$path) {
                $db_msg = 'error:ملف النسخة غير موجود أو غير صالح';
            } else {
                $err = '';
                $ab  = null;
                if (ems_dbtool_import($path, $err, $ab)) {
                    super_admin_write_audit($aid, 'restore', 'قاعدة البيانات', 'استعادة من نسخة: ' . basename($path));
                    $db_msg = 'success:تمت الاستعادة من «' . basename($path) . '». نسخة وقائية قبل الاستبدال: ' . basename($ab);
                } else {
                    $db_msg = 'error:' . $err;
                }
            }
        }
    } elseif ($act === 'db_import') {
        if (empty($_POST['confirm_replace'])) {
            $db_msg = 'error:يجب تأكيد الاستبدال قبل الاستيراد';
        } elseif (!isset($_FILES['sqlfile']) || ($_FILES['sqlfile']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $ecode = $_FILES['sqlfile']['error'] ?? UPLOAD_ERR_NO_FILE;
            $etxt  = ($ecode === UPLOAD_ERR_INI_SIZE || $ecode === UPLOAD_ERR_FORM_SIZE)
                   ? 'حجم الملف يتجاوز الحد المسموح للرفع (' . ini_get('upload_max_filesize') . ')'
                   : 'لم يُرفع ملف صالح';
            $db_msg = 'error:' . $etxt;
        } else {
            $orig = (string) $_FILES['sqlfile']['name'];
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if ($ext !== 'sql') {
                $db_msg = 'error:يُقبل ملف بصيغة .sql فقط';
            } else {
                $dest = ems_dbtool_backup_dir() . '/ems_uploaded_' . date('Ymd_His') . '.sql';
                if (!@move_uploaded_file($_FILES['sqlfile']['tmp_name'], $dest)) {
                    $db_msg = 'error:تعذّر حفظ الملف المرفوع على الخادم';
                } else {
                    $err = '';
                    $ab  = null;
                    if (ems_dbtool_import($dest, $err, $ab)) {
                        super_admin_write_audit($aid, 'import', 'قاعدة البيانات', 'استيراد ملف: ' . $orig);
                        $db_msg = 'success:تم استيراد «' . e($orig) . '» بنجاح. نسخة وقائية قبل الاستبدال: ' . basename($ab);
                    } else {
                        $db_msg = 'error:' . $err;
                    }
                    @unlink($dest); // نُزيل نسخة الرفع المؤقّتة (النسخة الوقائية تحفظ الحالة السابقة)
                }
            }
        }
    } elseif ($act === 'db_schedule') {
        $cfg = ems_dbtool_schedule_get();
        $cfg['enabled']       = !empty($_POST['sched_enabled']);
        $cfg['interval_days'] = intval($_POST['interval_days'] ?? 1);
        $cfg['retention']     = intval($_POST['retention'] ?? 14);
        if (ems_dbtool_schedule_save($cfg)) {
            super_admin_write_audit($aid, 'update', 'قاعدة البيانات',
                'تحديث جدولة النسخ التلقائي (' . ($cfg['enabled'] ? 'مفعّلة كل ' . max(1, intval($cfg['interval_days'])) . ' يوم' : 'معطّلة') . ')');
            $db_msg = 'success:تم حفظ إعدادات الجدولة';
        } else {
            $db_msg = 'error:تعذّر حفظ إعدادات الجدولة';
        }
    } elseif ($act === 'db_run_now') {
        $rerr = '';
        $res = ems_dbtool_run_scheduled($rerr, true);
        if ($res['ok']) {
            super_admin_write_audit($aid, 'backup', 'قاعدة البيانات', 'تشغيل نسخة مجدولة يدويًا: ' . ($res['file'] ?? ''));
            $db_msg = 'success:' . $res['message'];
        } else {
            $db_msg = 'error:' . $res['message'];
        }
    }
}

// ── Handle password change ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = 'error:رمز الحماية غير صحيح، حاول مرة أخرى';
    } else {
        $current_pw  = $_POST['current_password'] ?? '';
        $new_pw      = $_POST['new_password']      ?? '';
        $confirm_pw  = $_POST['confirm_password']  ?? '';

        // Load fresh admin record (عبر بوابة المزوّد العابرة)
        $admin_id = intval($admin['id']);
        $row = null;
        try {
            $row = ems_platform_db()->selectOne('super_admins', array(
                'columns' => array('password'), 'where' => array('id' => $admin_id)));
        } catch (\Throwable $t) { error_log('admin/settings read: ' . $t->getMessage()); }

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
            try {
                $st_pg = ems_platform_db();
                $st_pg->update('super_admins',
                    array('password' => $hashed, 'updated_at' => date('Y-m-d H:i:s')),
                    array('id' => $admin_id));
                $msg = 'success:تم تغيير كلمة المرور بنجاح';
                // Log to audit log (عبر البوابة)
                try {
                    $st_pg->insert('admin_audit_log', array(
                        'admin_id' => $admin_id, 'action_type' => 'update', 'target_name' => 'حساب المدير',
                        'description' => 'تغيير كلمة المرور', 'ip_address' => ($_SERVER['REMOTE_ADDR'] ?? '')));
                } catch (\Throwable $t) { error_log('admin/settings audit: ' . $t->getMessage()); }
            } catch (\Throwable $t) {
                error_log('admin/settings update: ' . $t->getMessage());
                $msg = 'error:تعذر تحديث كلمة المرور';
            }
        }
    }
}

// ── Load full admin data ──────────────────────────────────────────────────
$admin_id   = intval($admin['id']);
$admin_full = null;
try {
    $admin_full = ems_platform_db()->selectOne('super_admins', array(
        'columns' => array('id', 'name', 'email', 'is_active', 'last_login_at', 'created_at', 'updated_at'),
        'where' => array('id' => $admin_id)));
} catch (\Throwable $t) { error_log('admin/settings load: ' . $t->getMessage()); }

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

<!-- ══════════════════ DB TOOLS — نسخ احتياطي / استيراد / استعادة ══════════════════ -->
<?php
$db_size = ems_dbtool_size_info($conn);
$backups = ems_dbtool_list_backups();
$db_name = ems_dbtool_db_name();
?>
<div class="card" style="margin-top:18px;">
    <div class="card-hd">
        <span class="card-hd-title"><i class="fas fa-database" style="color:var(--blue);margin-left:6px"></i>قاعدة البيانات — النسخ الاحتياطي والاستيراد</span>
        <span class="badge bg-blue"><i class="fas fa-hard-drive"></i> <?php echo e($db_name); ?></span>
    </div>
    <div class="card-body">

        <?php if ($db_msg):
            $dp = explode(':', $db_msg, 2);
            $dt = $dp[0];
            $dtx = isset($dp[1]) ? $dp[1] : $db_msg;
        ?>
        <div class="alert alert-<?php echo $dt === 'success' ? 'success' : 'danger'; ?>">
            <i class="fas fa-<?php echo $dt === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <span><?php echo e($dtx); ?></span>
        </div>
        <?php endif; ?>

        <!-- info -->
        <div class="flex" style="gap:26px;flex-wrap:wrap;margin-bottom:18px;padding-bottom:16px;border-bottom:1px solid var(--line);">
            <div><div class="text-muted">القاعدة</div><div style="font-weight:800;"><?php echo e($db_name); ?></div></div>
            <div><div class="text-muted">الحجم</div><div style="font-weight:800;"><?php echo e($db_size['mb']); ?> MB</div></div>
            <div><div class="text-muted">عدد الجداول</div><div style="font-weight:800;"><?php echo intval($db_size['tables']); ?></div></div>
        </div>

        <!-- auto-schedule -->
        <?php $sched = ems_dbtool_schedule_get(); ?>
        <div class="card" style="box-shadow:none;border:1px solid var(--line);margin-bottom:18px;">
            <div class="card-hd">
                <span class="card-hd-title"><i class="fas fa-calendar-check" style="color:var(--gold);margin-left:6px"></i>النسخ الاحتياطي التلقائي المجدوَل</span>
                <?php echo !empty($sched['enabled'])
                    ? '<span class="badge bg-green"><i class="fas fa-circle-play"></i> مفعّل</span>'
                    : '<span class="badge bg-gray">متوقّف</span>'; ?>
            </div>
            <div class="card-body">
                <p class="text-muted" style="margin-bottom:14px;">تُؤخَذ نسخة تلقائيًا كل عددٍ من الأيام تحدّده، ويُحتفَظ بأحدث عددٍ منها فقط (تُحذف الأقدم تلقائيًا). تعمل عبر مهمّة نظامٍ مجدولة، مع فحصٍ احتياطي عند فتح اللوحة.</p>
                <div class="flex" style="gap:16px;flex-wrap:wrap;align-items:flex-end;">
                    <form method="post" class="flex" style="gap:16px;flex-wrap:wrap;align-items:flex-end;margin:0;">
                        <input type="hidden" name="action" value="db_schedule">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                        <label class="flex" style="gap:8px;cursor:pointer;">
                            <input type="checkbox" name="sched_enabled" value="1" <?php echo !empty($sched['enabled']) ? 'checked' : ''; ?>>
                            <span style="font-weight:700;">تفعيل الجدولة</span>
                        </label>
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">نسخة كل (أيام)</label>
                            <input class="form-ctrl" type="number" name="interval_days" min="1" max="365" value="<?php echo intval($sched['interval_days']); ?>" style="width:120px;">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label class="form-label" for="emsf_702_af672">الاحتفاظ بآخر (نُسخ)</label>
                            <input class="form-ctrl" type="number" name="retention" min="1" max="365" value="<?php echo intval($sched['retention']); ?>" style="width:140px;" id="emsf_702_af672">
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ الجدولة</button>
                    </form>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="action" value="db_run_now">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                        <button type="submit" class="btn btn-ghost"><i class="fas fa-play"></i> تشغيل نسخة الآن</button>
                    </form>
                </div>
                <div class="flex" style="gap:24px;flex-wrap:wrap;margin-top:14px;padding-top:12px;border-top:1px solid var(--line);font-size:0.82rem;">
                    <span class="text-muted">آخر تشغيل: <b style="color:var(--ink);"><?php echo e($sched['last_run_at'] ?: '—'); ?></b></span>
                    <span class="text-muted">الحالة:
                        <?php if (($sched['last_status'] ?? '') === 'success'): ?><span class="badge bg-green">نجحت</span>
                        <?php elseif (($sched['last_status'] ?? '') === 'error'): ?><span class="badge bg-red">فشلت</span>
                        <?php else: ?>—<?php endif; ?>
                    </span>
                    <span class="text-muted">الموعد التالي: <b style="color:var(--ink);"><?php echo e(ems_dbtool_schedule_next_text($sched)); ?></b></span>
                </div>
                <?php if (!empty($sched['last_message'])): ?>
                    <div class="text-muted" style="margin-top:6px;font-size:0.78rem;"><?php echo e($sched['last_message']); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="g2">
            <!-- backup -->
            <div class="card" style="box-shadow:none;border:1px solid var(--line);">
                <div class="card-hd"><span class="card-hd-title"><i class="fas fa-download" style="color:var(--green);margin-left:6px"></i>إنشاء نسخة احتياطية</span></div>
                <div class="card-body">
                    <p class="text-muted" style="margin-bottom:14px;">نسخة كاملة (جداول + إجراءات + مشغّلات) من قاعدة EMS، تُحفَظ على الخادم وتُنزَّل إلى جهازك مباشرةً.</p>
                    <form method="post">
                        <input type="hidden" name="action" value="db_backup">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-download"></i> إنشاء وتنزيل نسخة الآن</button>
                    </form>
                </div>
            </div>

            <!-- import -->
            <div class="card" style="box-shadow:none;border:1px solid var(--line);">
                <div class="card-hd"><span class="card-hd-title"><i class="fas fa-upload" style="color:var(--orange);margin-left:6px"></i>استيراد قاعدة بيانات</span></div>
                <div class="card-body">
                    <div class="alert alert-warning" style="font-size:0.8rem;margin-bottom:14px;">
                        <i class="fas fa-triangle-exclamation"></i>
                        <div>عملية استبدال: يحلّ محتوى الملف محلّ بيانات القاعدة الحالية. تُؤخَذ نسخة وقائية تلقائيًا قبل الاستبدال. الحدّ الأقصى للرفع: <?php echo e(ini_get('upload_max_filesize')); ?>.</div>
                    </div>
                    <form method="post" enctype="multipart/form-data" onsubmit="return confirm('سيُستبدَل محتوى قاعدة البيانات الحالية بالملف المرفوع (مع أخذ نسخة وقائية أولًا). هل أنت متأكد؟');">
                        <input type="hidden" name="action" value="db_import">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                        <div class="form-group">
                            <label class="form-label" for="emsf_703_ce84c">ملف SQL بصيغة .sql</label>
                            <input class="form-ctrl" type="file" name="sqlfile" accept=".sql" required id="emsf_703_ce84c">
                        </div>
                        <label class="flex" style="gap:8px;cursor:pointer;margin-bottom:14px;">
                            <input type="checkbox" name="confirm_replace" value="1" required>
                            <span style="font-size:0.83rem;">أفهم أن هذا سيستبدل البيانات الحالية</span>
                        </label>
                        <button type="submit" class="btn btn-secondary"><i class="fas fa-upload"></i> استيراد الآن</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- recent backups -->
        <div style="margin-top:22px;">
            <h4 style="font-size:0.9rem;font-weight:800;margin-bottom:10px;"><i class="fas fa-clock-rotate-left" style="margin-left:6px;color:var(--muted)"></i>النسخ المحفوظة على الخادم</h4>
            <?php if (empty($backups)): ?>
                <div class="empty-state" style="padding:26px;"><i class="fas fa-box-open"></i><p>لا توجد نسخ محفوظة بعد</p></div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>الملف</th><th>النوع</th><th>الحجم</th><th>التاريخ</th><th>إجراءات</th></tr></thead>
                    <tbody>
                    <?php foreach ($backups as $b): ?>
                        <tr>
                            <td style="font-family:monospace;font-size:0.78rem;"><?php echo e($b['name']); ?></td>
                            <td><?php echo $b['is_auto'] ? '<span class="badge bg-gray">تلقائية</span>' : '<span class="badge bg-blue">يدوية</span>'; ?></td>
                            <td><?php echo e($b['size_h']); ?></td>
                            <td><?php echo e(date('Y/m/d H:i', $b['mtime'])); ?></td>
                            <td>
                                <div class="flex" style="gap:5px;">
                                    <form method="post" style="display:inline;margin:0;">
                                        <input type="hidden" name="action" value="db_download"><input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>"><input type="hidden" name="file" value="<?php echo e($b['name']); ?>">
                                        <button class="btn btn-ghost btn-sm" title="تنزيل"><i class="fas fa-download"></i></button>
                                    </form>
                                    <form method="post" style="display:inline;margin:0;" onsubmit="return confirm('ستُستبدَل القاعدة الحالية بمحتوى هذه النسخة (مع نسخة وقائية أولًا). متابعة؟');">
                                        <input type="hidden" name="action" value="db_restore"><input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>"><input type="hidden" name="file" value="<?php echo e($b['name']); ?>"><input type="hidden" name="confirm_replace" value="1">
                                        <button class="btn btn-secondary btn-sm" title="استعادة"><i class="fas fa-rotate-left"></i></button>
                                    </form>
                                    <form method="post" style="display:inline;margin:0;" onsubmit="return confirm('حذف هذه النسخة نهائيًا؟');">
                                        <input type="hidden" name="action" value="db_delete"><input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>"><input type="hidden" name="file" value="<?php echo e($b['name']); ?>">
                                        <button class="btn btn-danger btn-sm" title="حذف"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
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
