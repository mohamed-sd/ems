<?php
require_once __DIR__ . '/includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'خطط الاشتراك';
$current_page = 'plans';

$msg = '';

// ── Handle POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = 'error:رمز الحماية غير صحيح';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'edit') {
            $plan_name  = mysqli_real_escape_string($conn, trim($_POST['plan_name'] ?? ''));
            $price      = floatval($_POST['price'] ?? 0);
            $max_users  = intval($_POST['max_users'] ?? 0);
            $max_proj   = intval($_POST['max_projects'] ?? 0);
            $max_equip  = intval($_POST['max_equipments'] ?? 0);
            $features   = mysqli_real_escape_string($conn, trim($_POST['features'] ?? ''));
            $sort       = intval($_POST['sort_order'] ?? 0);
            $is_active  = isset($_POST['is_active']) ? 1 : 0;

            if (empty($plan_name)) {
                $msg = 'error:اسم الخطة مطلوب';
            } elseif ($action === 'create') {
                $ok = @mysqli_query($conn, "INSERT INTO admin_subscription_plans
                    (plan_name, price, max_users, max_projects, max_equipments, features, sort_order, is_active)
                    VALUES ('$plan_name', $price, $max_users, $max_proj, $max_equip, '$features', $sort, $is_active)");
                $msg = $ok ? 'success:تمت إضافة الخطة بنجاح' : 'error:' . mysqli_error($conn);
            } else {
                $edit_id = intval($_POST['edit_id'] ?? 0);
                $ok = @mysqli_query($conn, "UPDATE admin_subscription_plans SET
                    plan_name='$plan_name', price=$price, max_users=$max_users,
                    max_projects=$max_proj, max_equipments=$max_equip,
                    features='$features', sort_order=$sort, is_active=$is_active
                    WHERE id=$edit_id");
                $msg = $ok ? 'success:تم تحديث الخطة بنجاح' : 'error:' . mysqli_error($conn);
            }
        } elseif ($action === 'delete') {
            $del_id = intval($_POST['del_id'] ?? 0);
            $ok = @mysqli_query($conn, "DELETE FROM admin_subscription_plans WHERE id=$del_id");
            $msg = $ok ? 'success:تم حذف الخطة' : 'error:' . mysqli_error($conn);
        } elseif ($action === 'toggle') {
            $tog_id = intval($_POST['tog_id'] ?? 0);
            @mysqli_query($conn, "UPDATE admin_subscription_plans SET is_active = 1 - is_active WHERE id=$tog_id");
            $msg = 'success:تم تغيير الحالة';
        }
        header('Location: ' . super_admin_url('plans') . '?msg=' . urlencode($msg));
        exit;
    }
}

if (!empty($_GET['msg'])) { $msg = $_GET['msg']; }

// ── Load plans ────────────────────────────────────────────────────────────
$plans        = [];
$table_exists = false;
$pq = @mysqli_query($conn, "SELECT *, (SELECT COUNT(*) FROM admin_companies WHERE plan_id = p.id AND status='active') AS companies_count FROM admin_subscription_plans p ORDER BY sort_order, id");
if ($pq) { $table_exists = true; while ($row = mysqli_fetch_assoc($pq)) $plans[] = $row; }

$csrf = generate_csrf_token();
require_once __DIR__ . '/includes/layout_head.php';
?>

<div class="phead">
    <div>
        <h2>خطط الاشتراك</h2>
        <p class="sub">إدارة الباقات المتاحة وحدود كل خطة</p>
    </div>
    <div class="phead-right">
        <button class="btn btn-primary" onclick="openPlanModal()">
            <i class="fas fa-plus"></i> خطة جديدة
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

<?php if (!$table_exists): ?>
<div class="coming-banner">
    <i class="fas fa-database"></i>
    <h3>جدول الخطط غير موجود بعد</h3>
    <p>قم بتنفيذ <code>database/admin_saas_tables.sql</code> لإنشاء جداول النظام.</p>
</div>
<?php elseif (empty($plans)): ?>
<div class="card">
    <div class="empty-state">
        <i class="fas fa-layer-group"></i>
        <p>لا توجد خطط اشتراك بعد — أضف أولى الخطط من الزر أعلاه</p>
    </div>
</div>
<?php else: ?>

<div class="g3">
    <?php foreach ($plans as $plan):
        $active   = intval($plan['is_active']);
        $co_count = intval($plan['companies_count'] ?? 0);
        $features = array_filter(array_map('trim', explode("\n", $plan['features'] ?? '')));
    ?>
    <div class="card" style="<?php echo !$active ? 'opacity:0.65;' : ''; ?>">
        <!-- Plan header -->
        <div style="background:linear-gradient(135deg,#0b1933,#1a3a5c);padding:22px;border-radius:13px 13px 0 0;text-align:center;position:relative;">
            <?php if (!$active): ?>
            <span style="position:absolute;top:10px;right:10px;" class="badge bg-gray">غير نشط</span>
            <?php endif; ?>
            <div style="font-size:1.15rem;font-weight:800;color:#fff;margin-bottom:6px;"><?php echo e($plan['plan_name']); ?></div>
            <div style="font-size:1.6rem;font-weight:800;color:#d6a700;">
                <?php echo $plan['price'] > 0 ? number_format($plan['price'], 0) . ' $' : 'مجاني'; ?>
            </div>
            <div style="font-size:0.78rem;color:rgba(255,255,255,0.5);margin-top:3px;">شهريًا</div>
        </div>

        <!-- Plan limits -->
        <div style="padding:18px 20px;border-bottom:1px solid var(--line);">
            <?php
            $limits = [];
            if ($plan['max_users'])      $limits[] = ['fa-users',             $plan['max_users'] . ' مستخدم'];
            if ($plan['max_projects'])   $limits[] = ['fa-diagram-project',   $plan['max_projects'] . ' مشروع'];
            if ($plan['max_equipments']) $limits[] = ['fa-truck-monster',     $plan['max_equipments'] . ' معدة'];
            foreach ($limits as $lim):
            ?>
            <div style="display:flex;align-items:center;gap:9px;padding:6px 0;font-size:0.86rem;">
                <i class="fas <?php echo $lim[0]; ?>" style="color:var(--blue);width:16px;text-align:center;"></i>
                <?php echo e($lim[1]); ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Features -->
        <?php if (!empty($features)): ?>
        <div style="padding:14px 20px;border-bottom:1px solid var(--line);">
            <?php foreach ($features as $feat): ?>
            <div style="display:flex;align-items:center;gap:8px;padding:4px 0;font-size:0.84rem;color:var(--ink-2);">
                <i class="fas fa-check" style="color:#059669;font-size:0.75rem;"></i>
                <?php echo e($feat); ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div style="padding:14px 20px;display:flex;align-items:center;justify-content:space-between;">
            <span class="text-muted"><?php echo $co_count; ?> شركة</span>
            <div class="flex">
                <button class="btn btn-ghost btn-sm" onclick="editPlan(<?php echo htmlspecialchars(json_encode($plan), ENT_QUOTES); ?>)"
                        title="تعديل">
                    <i class="fas fa-pen"></i>
                </button>
                <form method="post" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="tog_id" value="<?php echo intval($plan['id']); ?>">
                    <button class="btn btn-sm <?php echo $active ? 'btn-orange' : 'btn-success'; ?>" title="<?php echo $active ? 'تعطيل' : 'تفعيل'; ?>">
                        <i class="fas fa-<?php echo $active ? 'eye-slash' : 'eye'; ?>"></i>
                    </button>
                </form>
                <?php if ($co_count === 0): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('حذف الخطة نهائيًا؟')">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="del_id" value="<?php echo intval($plan['id']); ?>">
                    <button class="btn btn-danger btn-sm" title="حذف"><i class="fas fa-trash"></i></button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<!-- ── Add / Edit Plan Modal ─────────────────────────────────────────────── -->
<div id="planModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:500;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:560px;max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="padding:20px 24px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;">
            <h3 id="planModalTitle" style="font-size:1rem;font-weight:800;">إضافة خطة جديدة</h3>
            <button onclick="closePlanModal()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--muted);"><i class="fas fa-times"></i></button>
        </div>
        <form method="post" style="padding:24px;">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="action" id="planAction" value="create">
            <input type="hidden" name="edit_id" id="planEditId" value="">

            <div class="g2">
                <div class="form-group">
                    <label class="form-label">اسم الخطة *</label>
                    <input class="form-ctrl" name="plan_name" id="fPlanName" required placeholder="مثال: Professional">
                </div>
                <div class="form-group">
                    <label class="form-label">السعر الشهري ($)</label>
                    <input class="form-ctrl" name="price" id="fPrice" type="number" min="0" step="0.01" value="0">
                </div>
            </div>
            <div class="g3">
                <div class="form-group">
                    <label class="form-label">أقصى مستخدمين</label>
                    <input class="form-ctrl" name="max_users" id="fMaxUsers" type="number" min="0" value="0">
                    <p class="form-hint">0 = غير محدود</p>
                </div>
                <div class="form-group">
                    <label class="form-label">أقصى مشاريع</label>
                    <input class="form-ctrl" name="max_projects" id="fMaxProj" type="number" min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">أقصى معدات</label>
                    <input class="form-ctrl" name="max_equipments" id="fMaxEquip" type="number" min="0" value="0">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">المزايا (سطر لكل ميزة)</label>
                <textarea class="form-ctrl" name="features" id="fFeatures" rows="4" placeholder="تقارير متقدمة&#10;دعم فني 24/7&#10;تصدير البيانات"></textarea>
            </div>
            <div class="g2">
                <div class="form-group">
                    <label class="form-label">ترتيب العرض</label>
                    <input class="form-ctrl" name="sort_order" id="fSort" type="number" min="0" value="0">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;font-size:0.85rem;">
                        <input type="checkbox" name="is_active" id="fIsActive" value="1" checked style="width:16px;height:16px;">
                        تفعيل الخطة فورًا
                    </label>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:6px;">
                <button type="button" onclick="closePlanModal()" class="btn btn-ghost">إلغاء</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ الخطة</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPlanModal() {
    document.getElementById('planModal').style.display = 'flex';
    document.getElementById('planModalTitle').textContent = 'إضافة خطة جديدة';
    document.getElementById('planAction').value = 'create';
    document.getElementById('planEditId').value = '';
    ['fPlanName','fPrice','fMaxUsers','fMaxProj','fMaxEquip','fFeatures','fSort'].forEach(function(id){ document.getElementById(id).value = id === 'fSort' ? '0' : ''; });
    document.getElementById('fPrice').value = '0';
    document.getElementById('fIsActive').checked = true;
}
function closePlanModal() {
    document.getElementById('planModal').style.display = 'none';
}
function editPlan(plan) {
    document.getElementById('planModal').style.display = 'flex';
    document.getElementById('planModalTitle').textContent = 'تعديل الخطة';
    document.getElementById('planAction').value = 'edit';
    document.getElementById('planEditId').value  = plan.id;
    document.getElementById('fPlanName').value   = plan.plan_name;
    document.getElementById('fPrice').value      = plan.price;
    document.getElementById('fMaxUsers').value   = plan.max_users;
    document.getElementById('fMaxProj').value    = plan.max_projects;
    document.getElementById('fMaxEquip').value   = plan.max_equipments;
    document.getElementById('fFeatures').value   = plan.features || '';
    document.getElementById('fSort').value       = plan.sort_order;
    document.getElementById('fIsActive').checked = plan.is_active == 1;
}
</script>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
