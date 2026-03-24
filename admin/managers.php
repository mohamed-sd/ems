<?php
require_once __DIR__ . '/includes/auth.php';
super_admin_require_login();

$admin = super_admin_current();
$page_title = 'إدارة المدراء';
$current_page = 'managers';

function super_admin_password_is_strong($password) {
    if (!is_string($password)) {
        return false;
    }

    if (strlen($password) < 12 || strlen($password) > 255) {
        return false;
    }

    return preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

function super_admin_active_count($conn) {
    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM super_admins WHERE is_active = 1");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return intval($row['c']);
    }

    return 0;
}

function super_admin_set_flash($type, $text) {
    $_SESSION['super_admin_flash'] = array('type' => $type, 'text' => $text);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    super_admin_require_post_csrf();

    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $actorId = intval($admin['id']);

    if ($action === 'create') {
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (!validate_length($name, 3, 100)) {
            super_admin_set_flash('error', 'اسم المدير يجب أن يكون بين 3 و100 حرف.');
        } elseif (!validate_email($email) || !validate_length($email, 5, 150)) {
            super_admin_set_flash('error', 'البريد الإلكتروني غير صالح.');
        } elseif (!super_admin_password_is_strong($password)) {
            super_admin_set_flash('error', 'كلمة المرور يجب أن تكون قوية (12+ حرف وتحتوي أحرف كبيرة وصغيرة وأرقام ورمز خاص).');
        } else {
            $checkStmt = mysqli_prepare($conn, 'SELECT id FROM super_admins WHERE email = ? LIMIT 1');
            if (!$checkStmt) {
                super_admin_set_flash('error', 'تعذر التحقق من البريد الإلكتروني حالياً.');
                super_admin_redirect('managers');
            }

            mysqli_stmt_bind_param($checkStmt, 's', $email);
            mysqli_stmt_execute($checkStmt);
            $existsResult = mysqli_stmt_get_result($checkStmt);
            $exists = $existsResult ? mysqli_fetch_assoc($existsResult) : null;
            mysqli_stmt_close($checkStmt);

            if ($exists) {
                super_admin_set_flash('error', 'هذا البريد الإلكتروني مستخدم بالفعل.');
            } else {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $insertStmt = mysqli_prepare($conn, 'INSERT INTO super_admins (name, email, password, is_active) VALUES (?, ?, ?, ?)');

                if (!$insertStmt) {
                    super_admin_set_flash('error', 'تعذر إنشاء الحساب حالياً.');
                } else {
                    mysqli_stmt_bind_param($insertStmt, 'sssi', $name, $email, $passwordHash, $isActive);
                    $ok = mysqli_stmt_execute($insertStmt);
                    $newId = $ok ? intval(mysqli_insert_id($conn)) : 0;
                    mysqli_stmt_close($insertStmt);

                    if ($ok && $newId > 0) {
                        super_admin_write_audit($actorId, 'create', 'مدير أعلى', 'إنشاء حساب مدير أعلى جديد: ' . $email, $newId);
                        super_admin_set_flash('success', 'تم إنشاء حساب المدير بنجاح.');
                    } else {
                        super_admin_set_flash('error', 'فشل إنشاء الحساب.');
                    }
                }
            }
        }

        super_admin_redirect('managers');
    }

    if ($action === 'update') {
        $targetId = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($targetId <= 0) {
            super_admin_set_flash('error', 'المعرف غير صالح.');
        } elseif (!validate_length($name, 3, 100)) {
            super_admin_set_flash('error', 'اسم المدير يجب أن يكون بين 3 و100 حرف.');
        } elseif (!validate_email($email) || !validate_length($email, 5, 150)) {
            super_admin_set_flash('error', 'البريد الإلكتروني غير صالح.');
        } elseif ($newPassword !== '' && !super_admin_password_is_strong($newPassword)) {
            super_admin_set_flash('error', 'كلمة المرور الجديدة غير قوية بما يكفي.');
        } elseif ($targetId === $actorId && $isActive !== 1) {
            super_admin_set_flash('error', 'لا يمكنك تعطيل حسابك الحالي.');
        } else {
            $targetStmt = mysqli_prepare($conn, 'SELECT id, is_active FROM super_admins WHERE id = ? LIMIT 1');
            if (!$targetStmt) {
                super_admin_set_flash('error', 'تعذر تحميل بيانات الحساب المطلوب.');
                super_admin_redirect('managers');
            }

            mysqli_stmt_bind_param($targetStmt, 'i', $targetId);
            mysqli_stmt_execute($targetStmt);
            $targetRes = mysqli_stmt_get_result($targetStmt);
            $targetRow = $targetRes ? mysqli_fetch_assoc($targetRes) : null;
            mysqli_stmt_close($targetStmt);

            if (!$targetRow) {
                super_admin_set_flash('error', 'الحساب المطلوب غير موجود.');
                super_admin_redirect('managers');
            }

            $targetWasActive = intval($targetRow['is_active']) === 1;
            if ($targetWasActive && $isActive !== 1 && super_admin_active_count($conn) <= 1) {
                super_admin_set_flash('error', 'يجب أن يبقى مدير نشط واحد على الأقل.');
                super_admin_redirect('managers');
            }

            $checkStmt = mysqli_prepare($conn, 'SELECT id FROM super_admins WHERE email = ? AND id <> ? LIMIT 1');
            if (!$checkStmt) {
                super_admin_set_flash('error', 'تعذر التحقق من البريد الإلكتروني حالياً.');
                super_admin_redirect('managers');
            }

            mysqli_stmt_bind_param($checkStmt, 'si', $email, $targetId);
            mysqli_stmt_execute($checkStmt);
            $dupResult = mysqli_stmt_get_result($checkStmt);
            $dup = $dupResult ? mysqli_fetch_assoc($dupResult) : null;
            mysqli_stmt_close($checkStmt);

            if ($dup) {
                super_admin_set_flash('error', 'البريد الإلكتروني مستخدم بواسطة مدير آخر.');
            } else {
                if ($newPassword !== '') {
                    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $updateStmt = mysqli_prepare($conn, 'UPDATE super_admins SET name = ?, email = ?, is_active = ?, password = ?, updated_at = NOW() WHERE id = ?');
                    mysqli_stmt_bind_param($updateStmt, 'ssisi', $name, $email, $isActive, $passwordHash, $targetId);
                } else {
                    $updateStmt = mysqli_prepare($conn, 'UPDATE super_admins SET name = ?, email = ?, is_active = ?, updated_at = NOW() WHERE id = ?');
                    mysqli_stmt_bind_param($updateStmt, 'ssii', $name, $email, $isActive, $targetId);
                }

                if (!$updateStmt) {
                    super_admin_set_flash('error', 'تعذر تحديث الحساب حالياً.');
                } else {
                    $ok = mysqli_stmt_execute($updateStmt);
                    mysqli_stmt_close($updateStmt);

                    if ($ok) {
                        super_admin_write_audit($actorId, 'update', 'مدير أعلى', 'تحديث بيانات مدير أعلى: ' . $email, $targetId);
                        super_admin_set_flash('success', 'تم تحديث بيانات المدير بنجاح.');
                    } else {
                        super_admin_set_flash('error', 'فشل تحديث بيانات المدير.');
                    }
                }
            }
        }

        super_admin_redirect('managers');
    }

    if ($action === 'delete') {
        $targetId = intval(isset($_POST['id']) ? $_POST['id'] : 0);

        if ($targetId <= 0) {
            super_admin_set_flash('error', 'المعرف غير صالح.');
        } elseif ($targetId === $actorId) {
            super_admin_set_flash('error', 'لا يمكنك حذف حسابك الحالي.');
        } else {
            $remainActiveStmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM super_admins WHERE is_active = 1 AND id <> ?');
            mysqli_stmt_bind_param($remainActiveStmt, 'i', $targetId);
            mysqli_stmt_execute($remainActiveStmt);
            $remainRes = mysqli_stmt_get_result($remainActiveStmt);
            $remainRow = $remainRes ? mysqli_fetch_assoc($remainRes) : null;
            mysqli_stmt_close($remainActiveStmt);

            $remainingActive = $remainRow ? intval($remainRow['c']) : 0;
            if ($remainingActive <= 0) {
                super_admin_set_flash('error', 'لا يمكن حذف آخر مدير نشط.');
                super_admin_redirect('managers');
            }

            $targetEmail = '';
            $emailStmt = mysqli_prepare($conn, 'SELECT email FROM super_admins WHERE id = ? LIMIT 1');
            mysqli_stmt_bind_param($emailStmt, 'i', $targetId);
            mysqli_stmt_execute($emailStmt);
            $emailRes = mysqli_stmt_get_result($emailStmt);
            $emailRow = $emailRes ? mysqli_fetch_assoc($emailRes) : null;
            mysqli_stmt_close($emailStmt);
            if ($emailRow) {
                $targetEmail = $emailRow['email'];
            }

            $deleteStmt = mysqli_prepare($conn, 'DELETE FROM super_admins WHERE id = ? LIMIT 1');
            mysqli_stmt_bind_param($deleteStmt, 'i', $targetId);
            $ok = mysqli_stmt_execute($deleteStmt);
            mysqli_stmt_close($deleteStmt);

            if ($ok) {
                super_admin_write_audit($actorId, 'delete', 'مدير أعلى', 'حذف حساب مدير أعلى: ' . $targetEmail, $targetId);
                super_admin_set_flash('success', 'تم حذف الحساب بنجاح.');
            } else {
                super_admin_set_flash('error', 'تعذر حذف الحساب حالياً.');
            }
        }

        super_admin_redirect('managers');
    }

    super_admin_set_flash('error', 'الإجراء غير معروف.');
    super_admin_redirect('managers');
}

$flash = isset($_SESSION['super_admin_flash']) ? $_SESSION['super_admin_flash'] : null;
unset($_SESSION['super_admin_flash']);

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = max(1, intval(isset($_GET['p']) ? $_GET['p'] : 1));
$per = 15;
$offset = ($page - 1) * $per;

$whereParts = array('1=1');
if ($search !== '') {
    $s = mysqli_real_escape_string($conn, $search);
    $whereParts[] = "(name LIKE '%$s%' OR email LIKE '%$s%')";
}
if ($status === 'active') {
    $whereParts[] = 'is_active = 1';
} elseif ($status === 'inactive') {
    $whereParts[] = 'is_active = 0';
}
$where = implode(' AND ', $whereParts);

$totalCount = 0;
$countQ = mysqli_query($conn, "SELECT COUNT(*) AS c FROM super_admins WHERE $where");
if ($countQ && ($countRow = mysqli_fetch_assoc($countQ))) {
    $totalCount = intval($countRow['c']);
}
$totalPages = max(1, intval(ceil($totalCount / $per)));

$managers = array();
$listQ = mysqli_query($conn, "SELECT id, name, email, is_active, last_login_at, created_at FROM super_admins WHERE $where ORDER BY id DESC LIMIT $per OFFSET $offset");
if ($listQ) {
    while ($row = mysqli_fetch_assoc($listQ)) {
        $managers[] = $row;
    }
}

$csrf = generate_csrf_token();
require_once __DIR__ . '/includes/layout_head.php';
?>

<div class="phead">
    <div>
        <h2>إدارة المدراء</h2>
        <p class="sub">إدارة كاملة لحسابات الإدارة العليا: إضافة، تعديل، تعطيل، حذف</p>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" style="margin-bottom:16px;">
    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'triangle-exclamation'; ?>"></i>
    <span><?php echo e($flash['text']); ?></span>
</div>
<?php endif; ?>

<div class="g2" style="align-items:start;">
    <div class="card">
        <div class="card-hd">
            <span class="card-hd-title"><i class="fas fa-user-plus" style="color:var(--green);margin-left:6px;"></i>إضافة مدير جديد</span>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                <input type="hidden" name="action" value="create">

                <div class="form-group">
                    <label class="form-label">الاسم *</label>
                    <input class="form-ctrl" name="name" maxlength="100" required>
                </div>
                <div class="form-group">
                    <label class="form-label">البريد الإلكتروني *</label>
                    <input class="form-ctrl" name="email" type="email" maxlength="150" required>
                </div>
                <div class="form-group">
                    <label class="form-label">كلمة المرور *</label>
                    <input class="form-ctrl" name="password" type="password" maxlength="255" required autocomplete="new-password">
                    <p class="form-hint">الحد الأدنى 12 حرف ويجب أن تحتوي على حروف كبيرة وصغيرة وأرقام ورمز خاص.</p>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:700;cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" checked style="width:auto;"> تفعيل الحساب فوراً
                    </label>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> إنشاء الحساب</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-hd">
            <span class="card-hd-title"><i class="fas fa-shield-halved" style="color:var(--gold);margin-left:6px;"></i>ضوابط الحماية</span>
        </div>
        <div class="card-body">
            <div class="alert alert-info" style="margin-bottom:10px;">
                <i class="fas fa-lock"></i>
                <div>كل عمليات الإدارة محمية بـ CSRF + جلسة موثقة ببصمة المستخدم.</div>
            </div>
            <div class="alert alert-warning" style="margin-bottom:10px;">
                <i class="fas fa-user-shield"></i>
                <div>لا يمكن حذف أو تعطيل الحساب الحالي، ولا حذف آخر مدير نشط في النظام.</div>
            </div>
            <div class="alert alert-success" style="margin-bottom:0;">
                <i class="fas fa-scroll"></i>
                <div>كل العمليات (إضافة/تعديل/حذف) تُسجّل في سجل المراجعة الإداري.</div>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:18px;">
    <form method="get" class="filter-bar">
        <div class="input-icon-wrap" style="flex:1;min-width:220px;">
            <i class="fas fa-search"></i>
            <input class="form-ctrl form-ctrl-sm" style="width:100%;padding-right:32px;" name="q" value="<?php echo e($search); ?>" placeholder="بحث بالاسم أو البريد...">
        </div>
        <select name="status" class="form-ctrl-sm">
            <option value="">كل الحالات</option>
            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>نشط</option>
            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>غير نشط</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> تصفية</button>
        <?php if ($search !== '' || $status !== ''): ?>
        <a href="<?php echo e(super_admin_url('managers')); ?>" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> مسح</a>
        <?php endif; ?>
    </form>

    <?php if (empty($managers)): ?>
    <div class="empty-state">
        <i class="fas fa-users-slash"></i>
        <p>لا توجد حسابات مطابقة لخيارات البحث</p>
    </div>
    <?php else: ?>
    <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>الاسم</th>
                    <th>البريد الإلكتروني</th>
                    <th>الحالة</th>
                    <th>آخر تسجيل دخول</th>
                    <th>تاريخ الإنشاء</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($managers as $i => $m): ?>
                <tr>
                    <td class="text-muted"><?php echo $offset + $i + 1; ?></td>
                    <td style="font-weight:700;"><?php echo e($m['name']); ?></td>
                    <td><?php echo e($m['email']); ?></td>
                    <td>
                        <?php if (intval($m['is_active']) === 1): ?>
                        <span class="badge bg-green">نشط</span>
                        <?php else: ?>
                        <span class="badge bg-red">غير نشط</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?php echo $m['last_login_at'] ? e(date('d/m/Y H:i', strtotime($m['last_login_at']))) : '—'; ?></td>
                    <td class="text-muted"><?php echo e(date('d/m/Y', strtotime($m['created_at']))); ?></td>
                    <td>
                        <div class="flex" style="flex-wrap:wrap;gap:6px;">
                            <button type="button"
                                    class="btn btn-ghost btn-sm edit-btn"
                                    data-id="<?php echo intval($m['id']); ?>"
                                    data-name="<?php echo e($m['name']); ?>"
                                    data-email="<?php echo e($m['email']); ?>"
                                    data-active="<?php echo intval($m['is_active']); ?>">
                                <i class="fas fa-pen"></i>
                            </button>

                            <form method="post" action="" onsubmit="return confirm('هل أنت متأكد من حذف هذا الحساب؟');" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo intval($m['id']); ?>">
                                <button type="submit" class="btn btn-danger btn-sm" <?php echo intval($m['id']) === intval($admin['id']) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-top:1px solid var(--line);">
        <span class="text-muted">الصفحة <?php echo $page; ?> من <?php echo $totalPages; ?></span>
        <div class="flex" style="gap:6px;flex-wrap:wrap;">
            <?php for ($pg = max(1, $page - 2); $pg <= min($totalPages, $page + 2); $pg++): ?>
            <a href="?p=<?php echo $pg; ?>&q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>"
               class="btn btn-sm <?php echo $pg === $page ? 'btn-primary' : 'btn-ghost'; ?>"
               style="min-width:36px;justify-content:center;">
                <?php echo $pg; ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:500;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="padding:18px 22px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-size:1rem;font-weight:800;">تعديل بيانات المدير</h3>
            <button type="button" onclick="closeEditModal()" style="background:none;border:none;color:var(--muted);font-size:1.15rem;cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <form method="post" action="" style="padding:20px 22px;">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId" value="">

            <div class="form-group">
                <label class="form-label">الاسم *</label>
                <input class="form-ctrl" name="name" id="editName" maxlength="100" required>
            </div>
            <div class="form-group">
                <label class="form-label">البريد الإلكتروني *</label>
                <input class="form-ctrl" name="email" id="editEmail" type="email" maxlength="150" required>
            </div>
            <div class="form-group">
                <label class="form-label">كلمة مرور جديدة (اختياري)</label>
                <input class="form-ctrl" name="new_password" id="editPassword" type="password" maxlength="255" autocomplete="new-password">
                <p class="form-hint">اتركه فارغاً إذا لا تريد تغيير كلمة المرور.</p>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;font-weight:700;cursor:pointer;">
                    <input type="checkbox" name="is_active" id="editActive" value="1" style="width:auto;"> حساب نشط
                </label>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" class="btn btn-ghost" onclick="closeEditModal()">إلغاء</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('editModal');
    var btns = document.querySelectorAll('.edit-btn');

    btns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('editId').value = btn.getAttribute('data-id') || '';
            document.getElementById('editName').value = btn.getAttribute('data-name') || '';
            document.getElementById('editEmail').value = btn.getAttribute('data-email') || '';
            document.getElementById('editPassword').value = '';
            document.getElementById('editActive').checked = (btn.getAttribute('data-active') === '1');
            modal.style.display = 'flex';
        });
    });
})();

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
