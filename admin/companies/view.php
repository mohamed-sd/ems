<?php
require_once dirname(__DIR__) . '/includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$current_page = 'companies';
$msg          = trim(isset($_GET['msg']) ? $_GET['msg'] : '');

$id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
if ($id <= 0) {
    super_admin_redirect('companies');
}

function company_view_has_column($tableName, $columnName) {
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
    $sql = "SHOW COLUMNS FROM " . $safeTable . " LIKE '" . mysqli_real_escape_string($GLOBALS['conn'], $safeCol) . "'";
    $res = @mysqli_query($GLOBALS['conn'], $sql);

    return $res && mysqli_num_rows($res) > 0;
}

// ── Load company ──────────────────────────────────────────────────────────
$company = null;
$res = @mysqli_query($conn,
    "SELECT c.*, p.plan_name, p.max_users, p.max_projects
     FROM admin_companies c
     LEFT JOIN admin_subscription_plans p ON c.plan_id = p.id
     WHERE c.id = $id"
);
if ($res) { $company = mysqli_fetch_assoc($res); }
if (!$company) {
    super_admin_redirect('companies');
}

$displayName = '';
if (isset($company['company_name_ar']) && trim($company['company_name_ar']) !== '') {
    $displayName = trim($company['company_name_ar']);
} elseif (isset($company['company_name']) && trim($company['company_name']) !== '') {
    $displayName = trim($company['company_name']);
} elseif (isset($company['name']) && trim($company['name']) !== '') {
    $displayName = trim($company['name']);
} else {
    $displayName = isset($company['email']) ? $company['email'] : ('شركة #' . $id);
}

$page_title = 'تفاصيل: ' . $displayName;
$csrf = generate_csrf_token();

// ── Stats from EMS (cross-query, stub) ───────────────────────────────────
$cnt_users  = 0;
$cnt_proj   = 0;
$cnt_clients = 0;

if (company_view_has_column('clients', 'company_id')) {
    $cr = @mysqli_query($conn, "SELECT COUNT(*) AS c FROM clients WHERE company_id = $id");
    if ($cr) {
        $clientCountRow = mysqli_fetch_assoc($cr);
        $cnt_clients = intval(isset($clientCountRow['c']) ? $clientCountRow['c'] : 0);
    }
}

// ── Users list ────────────────────────────────────────────────────────────
$users_list = [];
$usersSelect = 'id, username, role, created_at';
if (company_view_has_column('users', 'name')) {
    $usersSelect .= ', name';
}
if (company_view_has_column('users', 'email')) {
    $usersSelect .= ', email';
}
if (company_view_has_column('users', 'status')) {
    $usersSelect .= ', status';
}
$ur = @mysqli_query($conn, "SELECT $usersSelect FROM users WHERE company_id = $id ORDER BY created_at DESC LIMIT 20");
if ($ur) {
    while ($row = mysqli_fetch_assoc($ur)) {
        $users_list[] = $row;
    }
    $cnt_users = count($users_list);
}

$password_target_user = null;
$password_select = 'id, username';
if (company_view_has_column('users', 'name')) {
    $password_select .= ', name';
}
if (company_view_has_column('users', 'email')) {
    $password_select .= ', email';
}

$role_filters = array();
if (company_view_has_column('users', 'role_id')) {
    $role_filters[] = 'role_id = 1';
}
if (company_view_has_column('users', 'role')) {
    $role_filters[] = 'role = 1';
}

$password_where = 'company_id = ' . $id;
if (!empty($role_filters)) {
    $password_where .= ' AND (' . implode(' OR ', $role_filters) . ')';
}

$pr = @mysqli_query($conn, "SELECT $password_select FROM users WHERE $password_where ORDER BY created_at ASC LIMIT 1");
if ($pr) {
    $password_target_user = mysqli_fetch_assoc($pr);
}

if (!$password_target_user) {
    $pr_fallback = @mysqli_query($conn, "SELECT $password_select FROM users WHERE company_id = $id ORDER BY created_at ASC LIMIT 1");
    if ($pr_fallback) {
        $password_target_user = mysqli_fetch_assoc($pr_fallback);
    }
}

require_once dirname(__DIR__) . '/includes/layout_head.php';
?>

<!-- Breadcrumb -->
<div class="flex" style="margin-bottom:18px;font-size:0.85rem;color:var(--muted);">
    <a href="<?php echo e(super_admin_url('companies')); ?>" style="color:var(--muted);">إدارة الشركات</a>
    <i class="fas fa-chevron-left" style="font-size:0.7rem;"></i>
    <span style="color:var(--ink);font-weight:700;"><?php echo e($displayName); ?></span>
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

<!-- ── Company header card ─────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:18px;">
    <div class="card-hd" style="background:linear-gradient(135deg,#0b1933,#1a3a5c);border-radius:13px 13px 0 0;padding:24px 26px;">
        <div style="display:flex;align-items:center;gap:16px;">
            <div style="width:52px;height:52px;border-radius:14px;background:rgba(214,167,0,0.15);border:1px solid rgba(214,167,0,0.3);display:flex;align-items:center;justify-content:center;color:#d6a700;font-size:1.3rem;">
                <i class="fas fa-building"></i>
            </div>
            <div>
                <div style="font-size:1.25rem;font-weight:800;color:#fff;"><?php echo e($displayName); ?></div>
                <div style="color:rgba(255,255,255,0.6);font-size:0.85rem;"><?php echo e($company['email']); ?></div>
            </div>
        </div>
        <div class="flex">
            <?php
            $st = isset($company['status']) ? $company['status'] : 'active';
            $sc = ['pending'=>'bg-orange','active'=>'bg-green','suspended'=>'bg-red','cancelled'=>'bg-gray'];
            $sl = ['pending'=>'قيد المراجعة','active'=>'نشط','suspended'=>'موقوف','cancelled'=>'ملغاة'];
            ?>
            <span class="badge <?php echo isset($sc[$st]) ? $sc[$st] : 'bg-gray'; ?>" style="font-size:0.8rem;padding:5px 12px;">
                <?php echo isset($sl[$st]) ? $sl[$st] : $st; ?>
            </span>
            <button type="button" class="btn btn-primary btn-sm" onclick="openPasswordModal()" <?php echo $password_target_user ? '' : 'disabled'; ?>>
                <i class="fas fa-key"></i> تعديل كلمة المرور
            </button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="openEditModal()">
                <i class="fas fa-pen"></i> تعديل
            </button>
            <?php if ($st === 'active'): ?>
            <form method="post" action="<?php echo e(super_admin_url('companies/action.php')); ?>" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="action" value="suspend">
                <input type="hidden" name="redirect_to" value="companies/<?php echo $id; ?>">
                <button class="btn btn-orange btn-sm" onclick="return confirm('تعليق هذه الشركة؟')">
                    <i class="fas fa-pause"></i> تعليق
                </button>
            </form>
            <?php else: ?>
            <form method="post" action="<?php echo e(super_admin_url('companies/action.php')); ?>" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="action" value="activate">
                <input type="hidden" name="redirect_to" value="companies/<?php echo $id; ?>">
                <button class="btn btn-success btn-sm" onclick="return confirm('تفعيل هذه الشركة؟')">
                    <i class="fas fa-play"></i> تفعيل
                </button>
            </form>
            <?php endif; ?>
            <form method="post" action="<?php echo e(super_admin_url('companies/action.php')); ?>" style="display:inline" onsubmit="return confirm('حذف الشركة نهائياً؟ لا يمكن التراجع.');">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="redirect_to" value="companies">
                <button class="btn btn-danger btn-sm">
                    <i class="fas fa-trash"></i> حذف
                </button>
            </form>
        </div>
    </div>
</div>

<?php if (!$password_target_user): ?>
<div class="alert alert-warning" style="margin-bottom:16px;">
    <i class="fas fa-triangle-exclamation"></i>
    <span>لا يمكن تعديل كلمة المرور حالياً لعدم وجود مستخدم مرتبط بهذه الشركة.</span>
</div>
<?php endif; ?>

<!-- ── Stats ────────────────────────────────────────────────────────────── -->
<div class="g3" style="margin-bottom:18px;">
    <div class="stat-card">
        <div class="stat-row">
            <div><div class="stat-val"><?php echo $cnt_users; ?></div><div class="stat-lbl">مستخدم</div></div>
            <div class="stat-ico" style="background:#2563eb"><i class="fas fa-users"></i></div>
        </div>
        <div class="stat-foot">الحد الأقصى: <?php echo intval(isset($company['max_users']) ? $company['max_users'] : 0); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-row">
            <div><div class="stat-val"><?php echo $cnt_proj; ?></div><div class="stat-lbl">مشروع</div></div>
            <div class="stat-ico" style="background:#7c3aed"><i class="fas fa-diagram-project"></i></div>
        </div>
        <div class="stat-foot">الحد الأقصى: <?php echo intval(isset($company['max_projects']) ? $company['max_projects'] : 0); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-row">
            <div><div class="stat-val"><?php echo $cnt_clients; ?></div><div class="stat-lbl">عميل</div></div>
            <div class="stat-ico" style="background:#0f766e"><i class="fas fa-user-tie"></i></div>
        </div>
        <div class="stat-foot">إجمالي العملاء المرتبطين بالشركة</div>
    </div>
    <div class="stat-card">
        <div class="stat-row">
            <div>
                <div class="stat-val" style="font-size:1.1rem;margin-bottom:4px;">
                    <?php echo $company['plan_name'] ? e($company['plan_name']) : '—'; ?>
                </div>
                <div class="stat-lbl">خطة الاشتراك الحالية</div>
            </div>
            <div class="stat-ico" style="background:#d97706"><i class="fas fa-layer-group"></i></div>
        </div>
        <?php if (!empty($company['subscription_end'])): ?>
        <div class="stat-foot">ينتهي في: <?php echo e(date('d/m/Y', strtotime($company['subscription_end']))); ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Details + Users ────────────────────────────────────────────────── -->
<div class="g2">
    <div>
        <div class="card">
            <div class="card-hd">
                <span class="card-hd-title"><i class="fas fa-circle-info" style="color:#2563eb;margin-left:6px"></i>بيانات الهوية والتواصل</span>
            </div>
            <div class="card-body">
                <?php
                $countryCity = trim((isset($company['country']) ? $company['country'] : '') . ' / ' . (isset($company['city']) ? $company['city'] : ''), ' /');
                $fields = [
                    'اسم الشركة (عربي)'   => $displayName,
                    'اسم الشركة (إنجليزي)' => isset($company['company_name_en']) && $company['company_name_en'] !== '' ? $company['company_name_en'] : '—',
                    'رقم السجل التجاري'    => isset($company['commercial_registration']) && $company['commercial_registration'] !== '' ? $company['commercial_registration'] : '—',
                    'قطاع النشاط'          => isset($company['sector']) && $company['sector'] !== '' ? $company['sector'] : '—',
                    'الدولة / المدينة'     => $countryCity !== '' ? $countryCity : '—',
                    'الرقم الضريبي'        => isset($company['tax_number']) && $company['tax_number'] !== '' ? $company['tax_number'] : '—',
                    'البريد الإلكتروني'    => isset($company['email']) ? $company['email'] : '—',
                    'رقم الهاتف'           => isset($company['phone']) && $company['phone'] !== '' ? $company['phone'] : '—',
                    'العنوان البريدي'      => isset($company['postal_address']) && $company['postal_address'] !== '' ? $company['postal_address'] : (isset($company['address']) ? $company['address'] : '—'),
                    'مسار الشعار'          => isset($company['logo_path']) && $company['logo_path'] !== '' ? $company['logo_path'] : '—',
                    'تاريخ الانضمام'       => !empty($company['created_at']) ? date('d/m/Y H:i', strtotime($company['created_at'])) : '—',
                    'آخر تحديث'            => !empty($company['updated_at']) ? date('d/m/Y H:i', strtotime($company['updated_at'])) : '—',
                ];
                foreach ($fields as $lbl => $val):
                ?>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:10px 0;border-bottom:1px solid var(--line);">
                    <span class="text-muted"><?php echo e($lbl); ?></span>
                    <span style="font-weight:600;text-align:left;max-width:62%;"><?php echo e($val); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-hd">
                <span class="card-hd-title"><i class="fas fa-sliders" style="color:#d97706;margin-left:6px"></i>الاشتراك والإعدادات</span>
            </div>
            <div class="card-body">
                <?php
                $settingsFields = [
                    'الخطة'                 => $company['plan_name'] ? $company['plan_name'] : '—',
                    'الحالة'                => isset($sl[$st]) ? $sl[$st] : $st,
                    'الوحدات المفعلة'       => isset($company['modules_enabled']) && trim($company['modules_enabled']) !== '' ? $company['modules_enabled'] : '—',
                    'تاريخ بدء الاشتراك'    => !empty($company['subscription_start']) ? date('d/m/Y', strtotime($company['subscription_start'])) : '—',
                    'تاريخ انتهاء الاشتراك' => !empty($company['subscription_end']) ? date('d/m/Y', strtotime($company['subscription_end'])) : '—',
                    'حد المستخدمين'         => intval(isset($company['max_users']) ? $company['max_users'] : 0),
                    'حد الآليات'            => intval(isset($company['max_equipments']) ? $company['max_equipments'] : 0),
                    'حد المشاريع'           => intval(isset($company['max_projects']) ? $company['max_projects'] : 0),
                    'العملة'                => isset($company['currency']) && $company['currency'] !== '' ? $company['currency'] : 'SAR',
                    'المنطقة الزمنية'       => isset($company['timezone']) && $company['timezone'] !== '' ? $company['timezone'] : 'Asia/Riyadh',
                ];
                foreach ($settingsFields as $lbl => $val):
                ?>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:10px 0;border-bottom:1px solid var(--line);">
                    <span class="text-muted"><?php echo e($lbl); ?></span>
                    <span style="font-weight:600;text-align:left;max-width:62%;"><?php echo e($val); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Users list -->
    <div class="card">
        <div class="card-hd">
            <span class="card-hd-title"><i class="fas fa-users" style="color:#059669;margin-left:6px"></i>مستخدمو الشركة</span>
        </div>
        <?php if (empty($users_list)): ?>
        <div class="empty-state">
            <i class="fas fa-user-slash"></i>
            <p>لا يوجد مستخدمون مرتبطون بهذه الشركة بعد</p>
        </div>
        <?php else: ?>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>اسم المستخدم</th><th>البريد</th><th>الدور</th><th>الحالة</th><th>تاريخ الإنشاء</th></tr></thead>
                <tbody>
                    <?php foreach ($users_list as $u): ?>
                    <tr>
                        <td style="font-weight:600;"><?php echo e(isset($u['name']) && $u['name'] !== '' ? $u['name'] : $u['username']); ?></td>
                        <td><?php echo e(isset($u['email']) ? $u['email'] : '—'); ?></td>
                        <td><?php echo intval($u['role']); ?></td>
                        <td><?php echo e(isset($u['status']) ? $u['status'] : '—'); ?></td>
                        <td class="text-muted"><?php echo e(date('d/m/Y', strtotime($u['created_at']))); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Edit Company Modal ────────────────────────────────────────────────── -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:500;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:620px;box-shadow:0 20px 60px rgba(0,0,0,0.2);max-height:90vh;overflow-y:auto;">
        <div style="padding:20px 24px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-size:1rem;font-weight:800;">تعديل بيانات الشركة</h3>
            <button type="button" onclick="closeEditModal()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--muted);">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="post" action="<?php echo e(super_admin_url('companies/action.php')); ?>" style="padding:24px;">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="redirect_to" value="companies/<?php echo $id; ?>">

            <h4 style="margin-bottom:10px;color:var(--ink-2);">بيانات الهوية والتواصل</h4>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label">اسم الشركة (عربي) *</label>
                    <input class="form-ctrl" name="company_name" required value="<?php echo e($displayName); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">اسم الشركة (إنجليزي)</label>
                    <input class="form-ctrl" name="company_name_en" value="<?php echo e(isset($company['company_name_en']) ? $company['company_name_en'] : ''); ?>">
                </div>
            </div>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label">رقم السجل التجاري *</label>
                    <input class="form-ctrl" name="commercial_registration" required value="<?php echo e(isset($company['commercial_registration']) ? $company['commercial_registration'] : ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">قطاع النشاط *</label>
                    <select class="form-ctrl" name="sector" required>
                        <option value="">— اختر —</option>
                        <option value="تعدين" <?php echo (isset($company['sector']) && $company['sector'] === 'تعدين') ? 'selected' : ''; ?>>تعدين</option>
                        <option value="مقاولات" <?php echo (isset($company['sector']) && $company['sector'] === 'مقاولات') ? 'selected' : ''; ?>>مقاولات</option>
                        <option value="إنشاء" <?php echo (isset($company['sector']) && $company['sector'] === 'إنشاء') ? 'selected' : ''; ?>>إنشاء</option>
                    </select>
                </div>
            </div>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label">الدولة *</label>
                    <input class="form-ctrl" name="country" required value="<?php echo e(isset($company['country']) ? $company['country'] : ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">المدينة *</label>
                    <input class="form-ctrl" name="city" required value="<?php echo e(isset($company['city']) ? $company['city'] : ''); ?>">
                </div>
            </div>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label">الرقم الضريبي</label>
                    <input class="form-ctrl" name="tax_number" value="<?php echo e(isset($company['tax_number']) ? $company['tax_number'] : ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">مسار الشعار</label>
                    <input class="form-ctrl" name="logo_path" value="<?php echo e(isset($company['logo_path']) ? $company['logo_path'] : ''); ?>">
                </div>
            </div>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label">البريد الإلكتروني *</label>
                    <input class="form-ctrl" name="email" type="email" required value="<?php echo e($company['email']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">رقم الهاتف *</label>
                    <input class="form-ctrl" name="phone" required value="<?php echo e(isset($company['phone']) ? $company['phone'] : ''); ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">العنوان البريدي</label>
                <textarea class="form-ctrl" name="postal_address" rows="2"><?php echo e(isset($company['postal_address']) ? $company['postal_address'] : ''); ?></textarea>
            </div>

            <h4 style="margin:8px 0 10px;color:var(--ink-2);">الاشتراك والإعدادات</h4>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label">نوع الباقة</label>
                    <select class="form-ctrl" name="plan_id">
                        <option value="">— اختر خطة —</option>
                        <?php
                        $plans_q = @mysqli_query($conn, "SELECT id, plan_name FROM admin_subscription_plans WHERE is_active=1 ORDER BY sort_order");
                        if ($plans_q) while ($pl = mysqli_fetch_assoc($plans_q)) {
                            $selected = intval(isset($company['plan_id']) ? $company['plan_id'] : 0) === intval($pl['id']) ? ' selected' : '';
                            echo '<option value="' . intval($pl['id']) . '"' . $selected . '>' . e($pl['plan_name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">الوحدات المفعلة</label>
                    <input class="form-ctrl" name="modules_enabled" value="<?php echo e(isset($company['modules_enabled']) ? $company['modules_enabled'] : ''); ?>">
                </div>
            </div>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label">تاريخ البدء</label>
                    <input class="form-ctrl" type="date" name="subscription_start" value="<?php echo e(isset($company['subscription_start']) ? $company['subscription_start'] : ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">تاريخ الانتهاء</label>
                    <input class="form-ctrl" type="date" name="subscription_end" value="<?php echo e(isset($company['subscription_end']) ? $company['subscription_end'] : ''); ?>">
                </div>
            </div>
            <div class="g3">
                <div class="form-group">
                    <label class="form-label">حد المستخدمين</label>
                    <input class="form-ctrl" type="number" min="0" name="max_users" value="<?php echo intval(isset($company['max_users']) ? $company['max_users'] : 0); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">حد الآليات</label>
                    <input class="form-ctrl" type="number" min="0" name="max_equipments" value="<?php echo intval(isset($company['max_equipments']) ? $company['max_equipments'] : 0); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">حد المشاريع</label>
                    <input class="form-ctrl" type="number" min="0" name="max_projects" value="<?php echo intval(isset($company['max_projects']) ? $company['max_projects'] : 0); ?>">
                </div>
            </div>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label">العملة</label>
                    <select class="form-ctrl" name="currency">
                        <option value="SAR" <?php echo (isset($company['currency']) && $company['currency'] === 'SAR') ? 'selected' : ''; ?>>SAR</option>
                        <option value="USD" <?php echo (isset($company['currency']) && $company['currency'] === 'USD') ? 'selected' : ''; ?>>USD</option>
                        <option value="EGP" <?php echo (isset($company['currency']) && $company['currency'] === 'EGP') ? 'selected' : ''; ?>>EGP</option>
                        <option value="SDG" <?php echo (isset($company['currency']) && $company['currency'] === 'SDG') ? 'selected' : ''; ?>>SDG</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">المنطقة الزمنية</label>
                    <input class="form-ctrl" name="timezone" value="<?php echo e(isset($company['timezone']) && $company['timezone'] !== '' ? $company['timezone'] : 'Asia/Riyadh'); ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">الحالة</label>
                <select class="form-ctrl" name="status">
                    <option value="pending" <?php echo $st === 'pending' ? 'selected' : ''; ?>>قيد المراجعة</option>
                    <option value="active" <?php echo $st === 'active' ? 'selected' : ''; ?>>نشط</option>
                    <option value="suspended" <?php echo $st === 'suspended' ? 'selected' : ''; ?>>موقوف</option>
                    <option value="cancelled" <?php echo $st === 'cancelled' ? 'selected' : ''; ?>>ملغاة</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
                <button type="button" onclick="closeEditModal()" class="btn btn-ghost">إلغاء</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Change Password Modal ─────────────────────────────────────────────── -->
<div id="passwordModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:520;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,0.2);max-height:90vh;overflow-y:auto;">
        <div style="padding:20px 24px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-size:1rem;font-weight:800;">تعديل كلمة مرور مدير الشركة</h3>
            <button type="button" onclick="closePasswordModal()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--muted);">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="post" action="<?php echo e(super_admin_url('companies/action.php')); ?>" style="padding:24px;" onsubmit="return validatePasswordForm();">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="action" value="update_company_user_password">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="redirect_to" value="companies/<?php echo $id; ?>">
            <input type="hidden" name="user_id" value="<?php echo $password_target_user ? intval($password_target_user['id']) : 0; ?>">

            <div class="form-group" style="margin-bottom:10px;">
                <label class="form-label">المستخدم المستهدف</label>
                <input class="form-ctrl" disabled value="<?php
                    if ($password_target_user) {
                        $target_name = (isset($password_target_user['name']) && trim($password_target_user['name']) !== '') ? $password_target_user['name'] : $password_target_user['username'];
                        $target_email = isset($password_target_user['email']) ? $password_target_user['email'] : '';
                        echo e($target_name . ($target_email !== '' ? ' - ' . $target_email : ''));
                    } else {
                        echo 'لا يوجد مستخدم متاح';
                    }
                ?>">
            </div>

            <div class="form-group">
                <label class="form-label">كلمة المرور الجديدة *</label>
                <input id="newPassword" class="form-ctrl" type="password" name="new_password" minlength="8" required placeholder="8 أحرف على الأقل">
                <small class="text-muted">يفضل أن تحتوي على أحرف كبيرة وصغيرة وأرقام.</small>
            </div>

            <div class="form-group" style="margin-top:10px;">
                <label class="form-label">تأكيد كلمة المرور *</label>
                <input id="confirmPassword" class="form-ctrl" type="password" name="confirm_password" minlength="8" required placeholder="أعد إدخال كلمة المرور">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px;">
                <button type="button" onclick="closePasswordModal()" class="btn btn-ghost">إلغاء</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> حفظ كلمة المرور</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal() {
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function openPasswordModal() {
    var modal = document.getElementById('passwordModal');
    if (!modal) {
        return;
    }
    modal.style.display = 'flex';
}

function closePasswordModal() {
    var modal = document.getElementById('passwordModal');
    if (!modal) {
        return;
    }
    modal.style.display = 'none';
}

function validatePasswordForm() {
    var pass = document.getElementById('newPassword');
    var confirmPass = document.getElementById('confirmPassword');

    if (!pass || !confirmPass) {
        return true;
    }

    if (pass.value.length < 8) {
        alert('كلمة المرور يجب ألا تقل عن 8 أحرف.');
        pass.focus();
        return false;
    }

    if (pass.value !== confirmPass.value) {
        alert('تأكيد كلمة المرور غير متطابق.');
        confirmPass.focus();
        return false;
    }

    return true;
}
</script>

<?php require_once dirname(__DIR__) . '/includes/layout_foot.php'; ?>
