<?php
require_once __DIR__ . '/includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'إدارة الشركات';
$current_page = 'companies';
$msg          = trim(isset($_GET['msg']) ? $_GET['msg'] : '');

// ── Filters ──────────────────────────────────────────────────────────────
$search = isset($_GET['q'])      ? trim($_GET['q'])      : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$page   = max(1, intval(isset($_GET['p']) ? $_GET['p'] : 1));
$per    = 20;
$offset = ($page - 1) * $per;

// أعمدة name/company_name قائمة في admin_companies (مثبَّتة من القاعدة) — سقط فحص SHOW COLUMNS
$hasNameCol = true;
$hasCompanyNameCol = true;

// ── Query (عبر بوابة المزوّد العابرة — هـ-1ب) ────────────────────────────────
$cmp_pg = ems_platform_db();
$companies   = [];
$total_count = 0;
$where_parts = ['1=1'];
$cmp_params  = [];

if ($search !== '') {
    $searchParts = ["c.email LIKE ?"];
    $cmp_params[] = '%' . $search . '%';
    if ($hasCompanyNameCol) {
        $searchParts[] = "c.company_name LIKE ?";
        $cmp_params[] = '%' . $search . '%';
    }
    if ($hasNameCol) {
        $searchParts[] = "c.name LIKE ?";
        $cmp_params[] = '%' . $search . '%';
    }
    $where_parts[] = "(" . implode(' OR ', $searchParts) . ")";
}
if (in_array($status, ['pending', 'active', 'suspended', 'cancelled'])) {
    // القيمة من قائمةٍ بيضاء أعلاه — حرفية آمنة
    $where_parts[] = "c.status = '$status'";
}
$where = implode(' AND ', $where_parts);

try {
    $cnt_q = $cmp_pg->scopedQuery(array('scope' => array('c' => 'admin_companies')),
        "SELECT COUNT(*) AS c FROM admin_companies c WHERE $where AND {TENANT_SCOPE}", $cmp_params);
    if (isset($cnt_q[0]['c'])) { $total_count = intval($cnt_q[0]['c']); }
} catch (\Throwable $t) { error_log('admin/companies count: ' . $t->getMessage()); }

$displayNameSelect = "COALESCE(NULLIF(c.company_name, ''), c.name, c.email) AS display_name";

try {
    $companies = $cmp_pg->scopedQuery(array('scope' => array('c' => 'admin_companies')),
        "SELECT c.*, p.plan_name,
            $displayNameSelect
     FROM admin_companies c
     LEFT JOIN admin_subscription_plans p ON c.plan_id = p.id
     WHERE $where AND {TENANT_SCOPE}
     ORDER BY c.created_at DESC
     LIMIT $per OFFSET $offset", $cmp_params);
} catch (\Throwable $t) { $companies = []; error_log('admin/companies list: ' . $t->getMessage()); }

$total_pages = max(1, (int)ceil($total_count / $per));
$csrf = generate_csrf_token();

require_once __DIR__ . '/includes/layout_head.php';
?>

<div class="phead">
    <div>
        <h2>إدارة الشركات</h2>
        <p class="sub">قائمة جميع الشركات المسجلة على المنصة — إجمالي: <strong><?php echo $total_count; ?></strong></p>
    </div>
    <div class="phead-right">
        <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
            <i class="fas fa-plus"></i> إضافة شركة
        </button>
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

<!-- ── Filter bar ────────────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:18px;">
    <form method="get" class="filter-bar">
        <div class="input-icon-wrap" style="flex:1;min-width:200px;">
            <i class="fas fa-search"></i>
            <input class="form-ctrl form-ctrl-sm" style="width:100%;" name="q"
                   placeholder="بحث بالاسم أو البريد..." value="<?php echo e($search); ?>" aria-label="بحث بالاسم أو البريد...">
        </div>
        <select name="status" class="form-ctrl-sm">
            <option value="">كل الحالات</option>
            <option value="pending"   <?php echo $status === 'pending'   ? 'selected' : ''; ?>>قيد المراجعة</option>
            <option value="active"    <?php echo $status === 'active'    ? 'selected' : ''; ?>>نشط</option>
            <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>موقوف</option>
            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>ملغاة</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> تصفية</button>
        <?php if ($search || $status): ?>
        <a href="<?php echo e(super_admin_url('companies')); ?>" class="btn btn-ghost btn-sm">
            <i class="fas fa-times"></i> مسح الفلتر
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- ── Table ─────────────────────────────────────────────────────────────── -->
<div class="card">
    <?php if (empty($companies)): ?>

        <?php if ($cnt_q === false): // table doesn't exist yet ?>
        <div class="coming-banner" style="margin:20px;">
            <i class="fas fa-database"></i>
            <h3>جدول الشركات غير موجود بعد</h3>
            <p>قم بتنفيذ الملف <code>database/admin_saas_tables.sql</code> لإنشاء جداول النظام الجديدة.</p>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-building"></i>
            <p>لا توجد شركات مطابقة للبحث</p>
        </div>
        <?php endif; ?>

    <?php else: ?>
    <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>الإجراءات</th>
                    <th>الشركة</th>
                    <th>البريد الإلكتروني</th>
                    <th>خطة الاشتراك</th>
                    <th>المستخدمون</th>
                    <th>الحالة</th>
                    <th>تاريخ الانضمام</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $i => $co):
                    $st   = isset($co['status']) ? $co['status'] : 'active';
                    $sc   = ['pending'=>'bg-orange','active'=>'bg-green','suspended'=>'bg-red','cancelled'=>'bg-gray'];
                    $sl   = ['pending'=>'قيد المراجعة','active'=>'نشط','suspended'=>'موقوف','cancelled'=>'ملغاة'];
                ?>
                <tr>
                    <td>
                        <div class="flex">
                            <a href="<?php echo e(super_admin_url('companies/' . intval($co['id']))); ?>"
                               class="btn btn-ghost btn-sm" title="عرض التفاصيل">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button type="button"
                                    class="btn btn-ghost btn-sm"
                                    title="تعديل"
                                    onclick="openEditModal(this)"
                                    data-id="<?php echo intval($co['id']); ?>"
                                    data-company-name="<?php echo e(isset($co['company_name_ar']) && $co['company_name_ar'] !== '' ? $co['company_name_ar'] : $co['display_name']); ?>"
                                    data-email="<?php echo e($co['email']); ?>"
                                    data-phone="<?php echo e(isset($co['phone']) ? $co['phone'] : ''); ?>"
                                    data-plan-id="<?php echo intval(isset($co['plan_id']) ? $co['plan_id'] : 0); ?>"
                                    data-status="<?php echo e($st); ?>"
                                    data-company-name-ar="<?php echo e(isset($co['company_name_ar']) ? $co['company_name_ar'] : ''); ?>"
                                    data-company-name-en="<?php echo e(isset($co['company_name_en']) ? $co['company_name_en'] : ''); ?>"
                                    data-commercial-registration="<?php echo e(isset($co['commercial_registration']) ? $co['commercial_registration'] : ''); ?>"
                                    data-sector="<?php echo e(isset($co['sector']) ? $co['sector'] : ''); ?>"
                                    data-country="<?php echo e(isset($co['country']) ? $co['country'] : ''); ?>"
                                    data-city="<?php echo e(isset($co['city']) ? $co['city'] : ''); ?>"
                                    data-tax-number="<?php echo e(isset($co['tax_number']) ? $co['tax_number'] : ''); ?>"
                                    data-logo-path="<?php echo e(isset($co['logo_path']) ? $co['logo_path'] : ''); ?>"
                                    data-postal-address="<?php echo e(isset($co['postal_address']) ? $co['postal_address'] : ''); ?>"
                                    data-modules-enabled="<?php echo e(isset($co['modules_enabled']) ? $co['modules_enabled'] : ''); ?>"
                                    data-subscription-start="<?php echo e(isset($co['subscription_start']) ? $co['subscription_start'] : ''); ?>"
                                    data-subscription-end="<?php echo e(isset($co['subscription_end']) ? $co['subscription_end'] : ''); ?>"
                                    data-max-users="<?php echo intval(isset($co['max_users']) ? $co['max_users'] : 0); ?>"
                                    data-max-equipments="<?php echo intval(isset($co['max_equipments']) ? $co['max_equipments'] : 0); ?>"
                                    data-max-projects="<?php echo intval(isset($co['max_projects']) ? $co['max_projects'] : 0); ?>"
                                    data-currency="<?php echo e(isset($co['currency']) ? $co['currency'] : 'SAR'); ?>"
                                    data-timezone="<?php echo e(isset($co['timezone']) ? $co['timezone'] : 'Asia/Riyadh'); ?>">
                                <i class="fas fa-pen"></i>
                            </button>
                            <?php if ($st === 'active'): ?>
                            <form method="post" action="<?php echo e(super_admin_url('companies/action.php')); ?>" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                                <input type="hidden" name="id" value="<?php echo intval($co['id']); ?>">
                                <input type="hidden" name="action" value="suspend">
                                <input type="hidden" name="redirect_to" value="companies">
                                <button class="btn btn-secondary btn-sm" title="تعليق" onclick="return confirm('تعليق الشركة؟')">
                                    <i class="fas fa-pause"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <form method="post" action="<?php echo e(super_admin_url('companies/action.php')); ?>" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                                <input type="hidden" name="id" value="<?php echo intval($co['id']); ?>">
                                <input type="hidden" name="action" value="activate">
                                <input type="hidden" name="redirect_to" value="companies">
                                <button class="btn btn-primary btn-sm" title="تفعيل" onclick="return confirm('تفعيل الشركة؟')">
                                    <i class="fas fa-play"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="post" action="<?php echo e(super_admin_url('companies/action.php')); ?>" style="display:inline" onsubmit="return confirm('حذف الشركة نهائياً؟ لا يمكن التراجع.');">
                                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                                <input type="hidden" name="id" value="<?php echo intval($co['id']); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="redirect_to" value="companies">
                                <button class="btn btn-danger btn-sm" title="حذف">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:700;"><?php echo e($co['display_name']); ?></div>
                        <?php if (!empty($co['phone'])): ?>
                        <div class="text-muted"><?php echo e($co['phone']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo e($co['email']); ?></td>
                    <td>
                        <?php if ($co['plan_name']): ?>
                            <span class="badge bg-blue"><?php echo e($co['plan_name']); ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo intval(isset($co['users_count']) ? $co['users_count'] : 0); ?></td>
                    <td>
                        <span class="badge <?php echo isset($sc[$st]) ? $sc[$st] : 'bg-gray'; ?>">
                            <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                            <?php echo isset($sl[$st]) ? $sl[$st] : $st; ?>
                        </span>
                    </td>
                    <td class="text-muted"><?php echo e(date('d/m/Y', strtotime($co['created_at']))); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-top:1px solid var(--line);">
        <span class="text-muted">الصفحة <?php echo $page; ?> من <?php echo $total_pages; ?></span>
        <div class="flex">
            <?php for ($pg = 1; $pg <= $total_pages; $pg++): ?>
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

<!-- ── Add Company Modal ─────────────────────────────────────────────────── -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:500;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,0.2);max-height:90vh;overflow-y:auto;">
        <div style="padding:20px 24px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-size:1rem;font-weight:800;">إضافة شركة جديدة</h3>
            <button onclick="document.getElementById('addModal').style.display='none'"
                    style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--muted);">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="post" action="<?php echo e(super_admin_url('companies/action.php')); ?>" style="padding:24px;">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="redirect_to" value="companies">
            <h4 style="margin-bottom:10px;color:var(--ink-2);">1) بيانات الهوية</h4>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label" for="emsf_641_0b13d">اسم الشركة (عربي) *</label>
                    <input class="form-ctrl" name="company_name" required placeholder="شركة …" id="emsf_641_0b13d">
                </div>
                <div class="form-group">
                    <label class="form-label" for="emsf_642_604ec">اسم الشركة (إنجليزي)</label>
                    <input class="form-ctrl" name="company_name_en" placeholder="Company Name" id="emsf_642_604ec">
                </div>
            </div>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label" for="emsf_643_25ca9">رقم السجل التجاري *</label>
                    <input class="form-ctrl" name="commercial_registration" required placeholder="CR-..." id="emsf_643_25ca9">
                </div>
                <div class="form-group">
                    <label class="form-label" for="emsf_644_6fc9c">قطاع النشاط *</label>
                    <select class="form-ctrl" name="sector" required id="emsf_644_6fc9c">
                        <option value="">— اختر —</option>
                        <option value="تعدين">تعدين</option>
                        <option value="مقاولات">مقاولات</option>
                        <option value="إنشاء">إنشاء</option>
                    </select>
                </div>
            </div>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label" for="emsf_645_42f1f">الدولة *</label>
                    <input class="form-ctrl" name="country" required placeholder="السعودية" id="emsf_645_42f1f">
                </div>
                <div class="form-group">
                    <label class="form-label" for="emsf_646_9fb00">المدينة *</label>
                    <input class="form-ctrl" name="city" required placeholder="الرياض" id="emsf_646_9fb00">
                </div>
            </div>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label" for="emsf_647_2782a">الرقم الضريبي</label>
                    <input class="form-ctrl" name="tax_number" placeholder="Tax ID" id="emsf_647_2782a">
                </div>
                <div class="form-group">
                    <label class="form-label" for="emsf_648_6189a">مسار الشعار (PNG/SVG)</label>
                    <input class="form-ctrl" name="logo_path" placeholder="assets/images/company-logo.png" id="emsf_648_6189a">
                </div>
            </div>

            <h4 style="margin:8px 0 10px;color:var(--ink-2);">2) بيانات التواصل</h4>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label" for="emsf_649_bf4b4">البريد الإلكتروني الرسمي *</label>
                    <input class="form-ctrl" name="email" type="email" required placeholder="info@..." id="emsf_649_bf4b4">
                </div>
                <div class="form-group">
                    <label class="form-label" for="emsf_650_c84bf">رقم الهاتف (مع رمز الدولة) *</label>
                    <input class="form-ctrl" name="phone" required placeholder="+966..." id="emsf_650_c84bf">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="emsf_651_05c31">العنوان البريدي</label>
                <textarea class="form-ctrl" name="postal_address" rows="2" placeholder="العنوان الكامل..." id="emsf_651_05c31"></textarea>
            </div>

            <h4 style="margin:8px 0 10px;color:var(--ink-2);">3) الاشتراك والباقة</h4>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label" for="emsf_652_585dc">نوع الباقة</label>
                    <select class="form-ctrl" name="plan_id" id="emsf_652_585dc">
                        <option value="">— اختر خطة —</option>
                        <?php
                        try {
                            $plans_q = $cmp_pg->select('admin_subscription_plans', array(
                                'columns' => array('id', 'plan_name'), 'where' => array('is_active' => 1), 'orderBy' => 'sort_order'));
                        } catch (\Throwable $t) { $plans_q = []; error_log('admin/companies plans: ' . $t->getMessage()); }
                        foreach ($plans_q as $pl) {
                            echo '<option value="' . intval($pl['id']) . '">' . e($pl['plan_name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="emsf_653_95543">الوحدات المفعلة (comma separated)</label>
                    <input class="form-ctrl" name="modules_enabled" placeholder="projects,timesheet,reports" id="emsf_653_95543">
                </div>
            </div>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label" for="emsf_654_24bb2">تاريخ بدء الاشتراك</label>
                    <input class="form-ctrl" type="date" name="subscription_start" id="emsf_654_24bb2">
                </div>
                <div class="form-group">
                    <label class="form-label" for="emsf_655_8fd27">تاريخ انتهاء الاشتراك</label>
                    <input class="form-ctrl" type="date" name="subscription_end" id="emsf_655_8fd27">
                </div>
            </div>
            <div class="g3">
                <div class="form-group">
                    <label class="form-label" for="emsf_656_8f8a6">حد المستخدمين</label>
                    <input class="form-ctrl" type="number" min="0" name="max_users" value="0" id="emsf_656_8f8a6">
                </div>
                <div class="form-group">
                    <label class="form-label" for="emsf_657_3dfc1">حد الآليات</label>
                    <input class="form-ctrl" type="number" min="0" name="max_equipments" value="0" id="emsf_657_3dfc1">
                </div>
                <div class="form-group">
                    <label class="form-label" for="emsf_658_40cab">حد المشاريع</label>
                    <input class="form-ctrl" type="number" min="0" name="max_projects" value="0" id="emsf_658_40cab">
                </div>
            </div>

            <h4 style="margin:8px 0 10px;color:var(--ink-2);">4) حساب المدير العام</h4>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label" for="emsf_659_48075">الاسم الكامل *</label>
                    <input class="form-ctrl" name="manager_name" required placeholder="الاسم الكامل" id="emsf_659_48075">
                </div>
                <div class="form-group">
                    <label class="form-label" for="emsf_660_2e35f">بريد المدير *</label>
                    <input class="form-ctrl" name="manager_email" type="email" required placeholder="admin@company.com" id="emsf_660_2e35f">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="emsf_661_a8d3f">كلمة مرور مؤقتة *</label>
                <input class="form-ctrl" name="temp_password" type="text" required placeholder="Temp#12345" id="emsf_661_a8d3f">
                <p class="form-hint">سيتم إجبار المدير على تغييرها عند أول تسجيل دخول.</p>
            </div>

            <h4 style="margin:8px 0 10px;color:var(--ink-2);">5) الإعدادات الافتراضية</h4>
            <div class="g3">
                <div class="form-group">
                    <label class="form-label" for="emsf_662_10577">العملة</label>
                    <select class="form-ctrl" name="currency" id="emsf_662_10577">
                        <option value="SAR">SAR</option>
                        <option value="USD">USD</option>
                        <option value="EGP">EGP</option>
                        <option value="SDG">SDG</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="emsf_663_8d555">المنطقة الزمنية</label>
                    <input class="form-ctrl" name="timezone" value="Asia/Riyadh" id="emsf_663_8d555">
                </div>
                <div class="form-group">
                    <label class="form-label" for="emsf_664_709f6">الحالة الابتدائية</label>
                    <select class="form-ctrl" name="status" id="emsf_664_709f6">
                        <option value="pending" selected>pending (مراجعة)</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
                <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn btn-ghost">إلغاء</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Edit Company Modal ────────────────────────────────────────────────── -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:500;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,0.2);max-height:90vh;overflow-y:auto;">
        <div style="padding:20px 24px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-size:1rem;font-weight:800;">تعديل بيانات الشركة</h3>
            <button type="button" onclick="closeEditModal()"
                    style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--muted);">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="post" action="<?php echo e(super_admin_url('companies/action.php')); ?>" style="padding:24px;">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editCompanyId" value="">
            <input type="hidden" name="redirect_to" value="companies">
            <h4 style="margin-bottom:10px;color:var(--ink-2);">بيانات الهوية والتواصل</h4>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label" for="editCompanyName">اسم الشركة (عربي) *</label>
                    <input class="form-ctrl" id="editCompanyName" name="company_name" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="editCompanyNameEn">اسم الشركة (إنجليزي)</label>
                    <input class="form-ctrl" id="editCompanyNameEn" name="company_name_en">
                </div>
            </div>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label" for="editCommercialRegistration">رقم السجل التجاري *</label>
                    <input class="form-ctrl" id="editCommercialRegistration" name="commercial_registration" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="editSector">قطاع النشاط *</label>
                    <select class="form-ctrl" id="editSector" name="sector" required>
                        <option value="">— اختر —</option>
                        <option value="تعدين">تعدين</option>
                        <option value="مقاولات">مقاولات</option>
                        <option value="إنشاء">إنشاء</option>
                    </select>
                </div>
            </div>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label" for="editCountry">الدولة *</label>
                    <input class="form-ctrl" id="editCountry" name="country" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="editCity">المدينة *</label>
                    <input class="form-ctrl" id="editCity" name="city" required>
                </div>
            </div>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label" for="editTaxNumber">الرقم الضريبي</label>
                    <input class="form-ctrl" id="editTaxNumber" name="tax_number">
                </div>
                <div class="form-group">
                    <label class="form-label" for="editLogoPath">مسار الشعار</label>
                    <input class="form-ctrl" id="editLogoPath" name="logo_path">
                </div>
            </div>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label" for="editEmail">البريد الرسمي *</label>
                    <input class="form-ctrl" id="editEmail" name="email" type="email" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="editPhone">رقم الهاتف *</label>
                    <input class="form-ctrl" id="editPhone" name="phone" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="editPostalAddress">العنوان البريدي</label>
                <textarea class="form-ctrl" id="editPostalAddress" name="postal_address" rows="2"></textarea>
            </div>

            <h4 style="margin:8px 0 10px;color:var(--ink-2);">الاشتراك والإعدادات</h4>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label" for="editPlanId">نوع الباقة</label>
                    <select class="form-ctrl" id="editPlanId" name="plan_id">
                        <option value="">— اختر خطة —</option>
                        <?php
                        try {
                            $plans_q2 = $cmp_pg->select('admin_subscription_plans', array(
                                'columns' => array('id', 'plan_name'), 'where' => array('is_active' => 1), 'orderBy' => 'sort_order'));
                        } catch (\Throwable $t) { $plans_q2 = []; error_log('admin/companies plans2: ' . $t->getMessage()); }
                        foreach ($plans_q2 as $pl) {
                            echo '<option value="' . intval($pl['id']) . '">' . e($pl['plan_name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="editModulesEnabled">الوحدات المفعلة</label>
                    <input class="form-ctrl" id="editModulesEnabled" name="modules_enabled" placeholder="projects,timesheet,reports">
                </div>
            </div>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label" for="editSubscriptionStart">تاريخ البدء</label>
                    <input class="form-ctrl" id="editSubscriptionStart" type="date" name="subscription_start">
                </div>
                <div class="form-group">
                    <label class="form-label" for="editSubscriptionEnd">تاريخ الانتهاء</label>
                    <input class="form-ctrl" id="editSubscriptionEnd" type="date" name="subscription_end">
                </div>
            </div>
            <div class="g3">
                <div class="form-group">
                    <label class="form-label" for="editMaxUsers">حد المستخدمين</label>
                    <input class="form-ctrl" id="editMaxUsers" type="number" min="0" name="max_users" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label" for="editMaxEquipments">حد الآليات</label>
                    <input class="form-ctrl" id="editMaxEquipments" type="number" min="0" name="max_equipments" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label" for="editMaxProjects">حد المشاريع</label>
                    <input class="form-ctrl" id="editMaxProjects" type="number" min="0" name="max_projects" value="0">
                </div>
            </div>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label" for="editCurrency">العملة</label>
                    <select class="form-ctrl" id="editCurrency" name="currency">
                        <option value="SAR">SAR</option>
                        <option value="USD">USD</option>
                        <option value="EGP">EGP</option>
                        <option value="SDG">SDG</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="editTimezone">المنطقة الزمنية</label>
                    <input class="form-ctrl" id="editTimezone" name="timezone" value="Asia/Riyadh">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="editStatus">الحالة</label>
                <select class="form-ctrl" id="editStatus" name="status">
                    <option value="pending">قيد المراجعة</option>
                    <option value="active">نشط</option>
                    <option value="suspended">موقوف</option>
                    <option value="cancelled">ملغاة</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
                <button type="button" onclick="closeEditModal()" class="btn btn-ghost">إلغاء</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(btn) {
    document.getElementById('editCompanyId').value = btn.getAttribute('data-id') || '';
    document.getElementById('editCompanyName').value = btn.getAttribute('data-company-name') || '';
    document.getElementById('editCompanyNameEn').value = btn.getAttribute('data-company-name-en') || '';
    document.getElementById('editCommercialRegistration').value = btn.getAttribute('data-commercial-registration') || '';
    document.getElementById('editSector').value = btn.getAttribute('data-sector') || '';
    document.getElementById('editCountry').value = btn.getAttribute('data-country') || '';
    document.getElementById('editCity').value = btn.getAttribute('data-city') || '';
    document.getElementById('editTaxNumber').value = btn.getAttribute('data-tax-number') || '';
    document.getElementById('editLogoPath').value = btn.getAttribute('data-logo-path') || '';
    document.getElementById('editPostalAddress').value = btn.getAttribute('data-postal-address') || '';
    document.getElementById('editModulesEnabled').value = btn.getAttribute('data-modules-enabled') || '';
    document.getElementById('editSubscriptionStart').value = btn.getAttribute('data-subscription-start') || '';
    document.getElementById('editSubscriptionEnd').value = btn.getAttribute('data-subscription-end') || '';
    document.getElementById('editMaxUsers').value = btn.getAttribute('data-max-users') || '0';
    document.getElementById('editMaxEquipments').value = btn.getAttribute('data-max-equipments') || '0';
    document.getElementById('editMaxProjects').value = btn.getAttribute('data-max-projects') || '0';
    document.getElementById('editCurrency').value = btn.getAttribute('data-currency') || 'SAR';
    document.getElementById('editTimezone').value = btn.getAttribute('data-timezone') || 'Asia/Riyadh';
    document.getElementById('editEmail').value = btn.getAttribute('data-email') || '';
    document.getElementById('editPhone').value = btn.getAttribute('data-phone') || '';

    var planId = btn.getAttribute('data-plan-id') || '';
    var status = btn.getAttribute('data-status') || 'active';
    document.getElementById('editPlanId').value = planId;
    document.getElementById('editStatus').value = status;

    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
