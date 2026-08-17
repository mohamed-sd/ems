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
    // super_admins جدول منصّي — العدّ عبر بوابة المزوّد العابرة
    try {
        return intval(ems_platform_db()->count('super_admins', array('where' => array('is_active' => 1))));
    } catch (\Throwable $t) { error_log('admin/managers active count: ' . $t->getMessage()); return 0; }
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
            // super_admins جدول منصّي — الفحص والإدراج عبر بوابة المزوّد العابرة
            $exists = null;
            try {
                $mg_pg = ems_platform_db();
                $exists = $mg_pg->selectOne('super_admins', array('columns' => array('id'), 'where' => array('email' => $email)));
            } catch (\Throwable $t) {
                error_log('admin/managers email check: ' . $t->getMessage());
                super_admin_set_flash('error', 'تعذر التحقق من البريد الإلكتروني حالياً.');
                super_admin_redirect('managers');
            }

            if ($exists) {
                super_admin_set_flash('error', 'هذا البريد الإلكتروني مستخدم بالفعل.');
            } else {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $newId = 0;
                try {
                    $newId = intval($mg_pg->insert('super_admins', array(
                        'name' => $name, 'email' => $email, 'password' => $passwordHash, 'is_active' => $isActive)));
                } catch (\Throwable $t) { error_log('admin/managers create: ' . $t->getMessage()); }

                if ($newId > 0) {
                    super_admin_write_audit($actorId, 'create', 'مدير أعلى', 'إنشاء حساب مدير أعلى جديد: ' . $email, $newId);
                    super_admin_set_flash('success', 'تم إنشاء حساب المدير بنجاح.');
                } else {
                    super_admin_set_flash('error', 'فشل إنشاء الحساب.');
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
            $mg_pg = ems_platform_db();
            $targetRow = null;
            try {
                $targetRow = $mg_pg->selectOne('super_admins', array('columns' => array('id', 'is_active'), 'where' => array('id' => $targetId)));
            } catch (\Throwable $t) {
                error_log('admin/managers target load: ' . $t->getMessage());
                super_admin_set_flash('error', 'تعذر تحميل بيانات الحساب المطلوب.');
                super_admin_redirect('managers');
            }

            if (!$targetRow) {
                super_admin_set_flash('error', 'الحساب المطلوب غير موجود.');
                super_admin_redirect('managers');
            }

            $targetWasActive = intval($targetRow['is_active']) === 1;
            if ($targetWasActive && $isActive !== 1 && super_admin_active_count($conn) <= 1) {
                super_admin_set_flash('error', 'يجب أن يبقى مدير نشط واحد على الأقل.');
                super_admin_redirect('managers');
            }

            $dup = null;
            try {
                $dup = $mg_pg->selectOne('super_admins', array('columns' => array('id'),
                    'where' => array('email' => $email), 'whereRaw' => 'id <> ' . intval($targetId)));
            } catch (\Throwable $t) {
                error_log('admin/managers dup check: ' . $t->getMessage());
                super_admin_set_flash('error', 'تعذر التحقق من البريد الإلكتروني حالياً.');
                super_admin_redirect('managers');
            }

            if ($dup) {
                super_admin_set_flash('error', 'البريد الإلكتروني مستخدم بواسطة مدير آخر.');
            } else {
                $mg_data = array('name' => $name, 'email' => $email, 'is_active' => $isActive, 'updated_at' => date('Y-m-d H:i:s'));
                if ($newPassword !== '') { $mg_data['password'] = password_hash($newPassword, PASSWORD_BCRYPT); }
                $ok = false;
                try { $mg_pg->update('super_admins', $mg_data, array('id' => $targetId)); $ok = true; }
                catch (\Throwable $t) { error_log('admin/managers update: ' . $t->getMessage()); }

                if ($ok) {
                    super_admin_write_audit($actorId, 'update', 'مدير أعلى', 'تحديث بيانات مدير أعلى: ' . $email, $targetId);
                    super_admin_set_flash('success', 'تم تحديث بيانات المدير بنجاح.');
                } else {
                    super_admin_set_flash('error', 'فشل تحديث بيانات المدير.');
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
            $mg_pg = ems_platform_db();
            $remainingActive = 0;
            try {
                $remainingActive = intval($mg_pg->count('super_admins', array(
                    'where' => array('is_active' => 1), 'whereRaw' => 'id <> ' . intval($targetId))));
            } catch (\Throwable $t) { error_log('admin/managers remain: ' . $t->getMessage()); }
            if ($remainingActive <= 0) {
                super_admin_set_flash('error', 'لا يمكن حذف آخر مدير نشط.');
                super_admin_redirect('managers');
            }

            $targetEmail = '';
            try {
                $mg_email_row = $mg_pg->selectOne('super_admins', array('columns' => array('email'), 'where' => array('id' => $targetId)));
                if ($mg_email_row) { $targetEmail = $mg_email_row['email']; }
            } catch (\Throwable $t) { error_log('admin/managers email: ' . $t->getMessage()); }

            // [مُستثنى موثَّق — حذف صف منصّي] deleteRow حكرٌ تعاقديًا على جداول المستأجر؛
            // حذف حساب المدير الأعلى يبقى خامًا بهوية الكونسول حتى قناة حذفٍ منصّية.
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
$whereParams = array();
if ($search !== '') {
    $whereParts[] = "(name LIKE ? OR email LIKE ?)";
    $whereParams[] = '%' . $search . '%';
    $whereParams[] = '%' . $search . '%';
}
if ($status === 'active') {
    $whereParts[] = 'is_active = 1';
} elseif ($status === 'inactive') {
    $whereParts[] = 'is_active = 0';
}
$where = implode(' AND ', $whereParts);

$mg_pg = ems_platform_db();
$totalCount = 0;
try {
    $mg_cnt = $mg_pg->scopedQuery(array('scope' => array('super_admins' => 'super_admins')),
        "SELECT COUNT(*) AS c FROM super_admins WHERE $where AND {TENANT_SCOPE}", $whereParams);
    if (isset($mg_cnt[0]['c'])) { $totalCount = intval($mg_cnt[0]['c']); }
} catch (\Throwable $t) { error_log('admin/managers count: ' . $t->getMessage()); }
$totalPages = max(1, intval(ceil($totalCount / $per)));

$managers = array();
try {
    $managers = $mg_pg->scopedQuery(array('scope' => array('super_admins' => 'super_admins')),
        "SELECT id, name, email, is_active, last_login_at, created_at FROM super_admins WHERE $where AND {TENANT_SCOPE} ORDER BY id DESC LIMIT $per OFFSET $offset", $whereParams);
} catch (\Throwable $t) { error_log('admin/managers list: ' . $t->getMessage()); }

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
                    <label class="form-label" for="emsf_1964_964db">الاسم *</label>
                    <input class="form-ctrl" name="name" maxlength="100" required id="emsf_1964_964db">
                </div>
                <div class="form-group">
                    <label class="form-label" for="emsf_1965_08b10">البريد الإلكتروني *</label>
                    <input class="form-ctrl" name="email" type="email" maxlength="150" required id="emsf_1965_08b10">
                </div>
                <div class="form-group">
                    <label class="form-label" for="emsf_1966_a7306">كلمة المرور *</label>
                    <input class="form-ctrl" name="password" type="password" maxlength="255" required autocomplete="new-password" id="emsf_1966_a7306">
                    <p class="form-hint">الحد الأدنى 12 حرف ويجب أن تحتوي على حروف كبيرة وصغيرة وأرقام ورمز خاص.</p>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:700;cursor:pointer;" for="editName">
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
                    <th>الإجراءات</th>
                    <th>الاسم</th>
                    <th>البريد الإلكتروني</th>
                    <th>الحالة</th>
                    <th>آخر تسجيل دخول</th>
                    <th>تاريخ الإنشاء</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($managers as $i => $m): ?>
                <tr>
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
                    <th>الإجراءات</th>
                    <th>الاسم</th>
                    <th>البريد الإلكتروني</th>
                    <th>الحالة</th>
                    <th>آخر تسجيل دخول</th>
                    <th>تاريخ الإنشاء</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($managers as $i => $m): ?>
                <tr>
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
                <label class="form-label" for="editEmail">البريد الإلكتروني *</label>
                <input class="form-ctrl" name="email" id="editEmail" type="email" maxlength="150" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="editPassword">كلمة مرور جديدة (اختياري)</label>
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
