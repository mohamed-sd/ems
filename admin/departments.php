<?php
/**
 * admin/departments.php — إدارةُ الإدارات · لوحةُ الإدارةِ العليا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ `departments` جدولٌ **منصّيٌّ** (`T_PLATFORM`) يخدم المستأجرين جميعًا بلا
 *   `company_id` — فالقراءةُ والكتابةُ عبر `ems_platform_db()` حصرًا.
 * ◆ **الإضافةُ تلزمها موافقةُ المطوّر**: حقلٌ مخفيٌّ يُفحَص في الخادمِ لا في
 *   المتصفّحِ وحدَه — فإدارةٌ بلا صفحاتٍ وأكوادٍ مجهَّزةٍ تكسر أسطحًا حيّة.
 * ⛔ **ولا حذفَ من هذه الشاشة**: الإدارةُ مرتبطةٌ بأكوادٍ وبياناتٍ حيّة، وطلبُ
 *   الحذفِ يُردُّ برسالةٍ ثابتةٍ ولا يمسُّ صفًّا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/includes/auth.php';
super_admin_require_login();

$admin = super_admin_current();
$page_title = 'إدارة الإدارات';
$current_page = 'departments';

if (!function_exists('super_admin_set_flash')) {
    function super_admin_set_flash($type, $text) {
        $_SESSION['super_admin_flash'] = array('type' => $type, 'text' => $text);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    super_admin_require_post_csrf();

    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $actorId = intval($admin['id']);

    if ($action === 'create') {
        $code = trim(isset($_POST['code']) ? $_POST['code'] : '');
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $orderRaw = isset($_POST['display_order']) ? trim($_POST['display_order']) : '0';
        $notes = trim(isset($_POST['notes']) ? $_POST['notes'] : '');
        $devConfirm = isset($_POST['developer_confirm']) ? trim($_POST['developer_confirm']) : '0';

        // تأكيدُ المطوّرِ يُفحَص في الخادم — المودالُ واجهةٌ لا حارس
        if ($devConfirm !== '1') {
            super_admin_set_flash('error', 'يجب تأكيد المطور قبل الإضافة.');
            super_admin_redirect('departments');
        }

        if (!validate_length($code, 3, 20)) {
            super_admin_set_flash('error', 'كود الإدارة يجب أن يكون بين 3 و20 حرف.');
        } elseif (!validate_length($name, 3, 150)) {
            super_admin_set_flash('error', 'اسم الإدارة يجب أن يكون بين 3 و150 حرف.');
        } elseif ($orderRaw !== '' && (!ctype_digit($orderRaw) || intval($orderRaw) < 0)) {
            super_admin_set_flash('error', 'ترتيب العرض يجب أن يكون رقما صحيحا أكبر من أو يساوي صفر.');
        } else {
            $displayOrder = $orderRaw === '' ? 0 : intval($orderRaw);
            $dp_pg = ems_platform_db();

            $exists = null;
            try {
                $exists = $dp_pg->selectOne('departments', array('columns' => array('id'), 'where' => array('code' => $code)));
            } catch (\Throwable $t) {
                error_log('admin/departments code check: ' . $t->getMessage());
                super_admin_set_flash('error', 'تعذر التحقق من كود الإدارة حاليا.');
                super_admin_redirect('departments');
            }

            if ($exists) {
                super_admin_set_flash('error', 'كود الإدارة مستخدم بالفعل — الكود لا يتكرر.');
            } else {
                $newId = 0;
                try {
                    $newId = intval($dp_pg->insert('departments', array(
                        'code' => $code,
                        'name' => $name,
                        'display_order' => $displayOrder,
                        'notes' => ($notes === '' ? null : $notes),
                    )));
                } catch (\Throwable $t) { error_log('admin/departments create: ' . $t->getMessage()); }

                if ($newId > 0) {
                    super_admin_write_audit($actorId, 'create', 'إدارة',
                        'إضافة إدارة جديدة: ' . $code . ' — ' . $name . ' (بتأكيد المطور)', $newId);
                    super_admin_set_flash('success', 'تمت إضافة الإدارة بنجاح.');
                } else {
                    super_admin_set_flash('error', 'فشلت إضافة الإدارة.');
                }
            }
        }

        super_admin_redirect('departments');
    }

    if ($action === 'update') {
        $targetId = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        $code = trim(isset($_POST['code']) ? $_POST['code'] : '');
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $orderRaw = isset($_POST['display_order']) ? trim($_POST['display_order']) : '0';
        $notes = trim(isset($_POST['notes']) ? $_POST['notes'] : '');

        if ($targetId <= 0) {
            super_admin_set_flash('error', 'المعرف غير صالح.');
        } elseif (!validate_length($code, 3, 20)) {
            super_admin_set_flash('error', 'كود الإدارة يجب أن يكون بين 3 و20 حرف.');
        } elseif (!validate_length($name, 3, 150)) {
            super_admin_set_flash('error', 'اسم الإدارة يجب أن يكون بين 3 و150 حرف.');
        } elseif ($orderRaw !== '' && (!ctype_digit($orderRaw) || intval($orderRaw) < 0)) {
            super_admin_set_flash('error', 'ترتيب العرض يجب أن يكون رقما صحيحا أكبر من أو يساوي صفر.');
        } else {
            $displayOrder = $orderRaw === '' ? 0 : intval($orderRaw);
            $dp_pg = ems_platform_db();

            $targetRow = null;
            try {
                $targetRow = $dp_pg->selectOne('departments', array('columns' => array('id', 'code'), 'where' => array('id' => $targetId)));
            } catch (\Throwable $t) {
                error_log('admin/departments target load: ' . $t->getMessage());
                super_admin_set_flash('error', 'تعذر تحميل بيانات الإدارة المطلوبة.');
                super_admin_redirect('departments');
            }

            if (!$targetRow) {
                super_admin_set_flash('error', 'الإدارة المطلوبة غير موجودة.');
                super_admin_redirect('departments');
            }

            $dup = null;
            try {
                $dup = $dp_pg->selectOne('departments', array('columns' => array('id'),
                    'where' => array('code' => $code), 'whereRaw' => 'id <> ' . intval($targetId)));
            } catch (\Throwable $t) {
                error_log('admin/departments dup check: ' . $t->getMessage());
                super_admin_set_flash('error', 'تعذر التحقق من كود الإدارة حاليا.');
                super_admin_redirect('departments');
            }

            if ($dup) {
                super_admin_set_flash('error', 'كود الإدارة مستخدم بواسطة إدارة أخرى.');
            } else {
                $ok = false;
                try {
                    $dp_pg->update('departments', array(
                        'code' => $code,
                        'name' => $name,
                        'display_order' => $displayOrder,
                        'notes' => ($notes === '' ? null : $notes),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ), array('id' => $targetId));
                    $ok = true;
                } catch (\Throwable $t) { error_log('admin/departments update: ' . $t->getMessage()); }

                if ($ok) {
                    super_admin_write_audit($actorId, 'update', 'إدارة',
                        'تحديث بيانات إدارة: ' . $code . ' — ' . $name, $targetId);
                    super_admin_set_flash('success', 'تم تحديث بيانات الإدارة بنجاح.');
                } else {
                    super_admin_set_flash('error', 'فشل تحديث بيانات الإدارة.');
                }
            }
        }

        super_admin_redirect('departments');
    }

    if ($action === 'delete') {
        // ⛔ لا حذفَ — ولا يُمَسُّ صفٌّ. الطلبُ يُردُّ برسالةٍ ثابتة.
        super_admin_set_flash('error', 'الحذف معطّل مؤقتًا — لا يمكن حذف إدارة مرتبطة بصفحات وأكواد وبيانات حية. تواصل مع الفريق البرمجي.');
        super_admin_redirect('departments');
    }

    super_admin_set_flash('error', 'الإجراء غير معروف.');
    super_admin_redirect('departments');
}

$flash = isset($_SESSION['super_admin_flash']) ? $_SESSION['super_admin_flash'] : null;
unset($_SESSION['super_admin_flash']);

$departments = array();
try {
    $departments = ems_platform_db()->scopedQuery(
        array('scope' => array('d' => 'departments')),
        "SELECT d.id, d.code, d.name, d.display_order, d.notes, d.created_at, d.updated_at
           FROM departments d WHERE {TENANT_SCOPE} ORDER BY d.display_order ASC, d.id ASC"
    );
} catch (\Throwable $t) {
    error_log('admin/departments list: ' . $t->getMessage());
}

$csrf = generate_csrf_token();
require_once __DIR__ . '/includes/layout_head.php';
?>

<div class="phead">
    <div>
        <h2>إدارة الإدارات</h2>
        <p class="sub">عرض وتعديل الإدارات المسجلة في المنصة</p>
    </div>
</div>

<div class="alert alert-warning" style="margin-bottom:18px;border-right:4px solid var(--orange);background:#fff8e1;">
    <i class="fas fa-triangle-exclamation" style="color:var(--orange);font-size:1.1rem;"></i>
    <div>
        <strong>تنبيه مهم:</strong>
        الإدارات تُدار بواسطة إدارة المنصة والفريق البرمجي.
        لا يمكن إضافة إدارة جديدة ما لم يتم إنشاء صفحاتها وأكوادها
        وتجهيز بيئة التعامل معها عن طريق مبرمجي ومطوري المنصة.
        <strong>أي إضافة بدون تجهيز مسبق قد تؤدي إلى أعطال في النظام.</strong>
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
            <span class="card-hd-title"><i class="fas fa-plus" style="color:var(--green);margin-left:6px;"></i>إضافة إدارة جديدة</span>
        </div>
        <div class="card-body">
            <form method="post" action="" id="addForm">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="developer_confirm" id="devConfirmField" value="0">

                <div class="form-group">
                    <label class="form-label" for="addCode">كود الإدارة *</label>
                    <input class="form-ctrl" name="code" id="addCode" maxlength="20" required>
                    <p class="form-hint">من 3 إلى 20 حرف — لا يتكرر بين الإدارات.</p>
                </div>
                <div class="form-group">
                    <label class="form-label" for="addName">اسم الإدارة *</label>
                    <input class="form-ctrl" name="name" id="addName" maxlength="150" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="addOrder">ترتيب العرض</label>
                    <input class="form-ctrl" name="display_order" id="addOrder" type="number" min="0" step="1" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label" for="addNotes">ملاحظات</label>
                    <textarea class="form-ctrl" name="notes" id="addNotes" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary" onclick="return confirmDeveloperAdd(event);">
                    <i class="fas fa-save"></i> إضافة الإدارة
                </button>
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
                <div>كل العمليات محمية ب CSRF + جلسة موثقة ببصمة المستخدم.</div>
            </div>
            <div class="alert alert-warning" style="margin-bottom:10px;">
                <i class="fas fa-user-gear"></i>
                <div>الإضافة تتطلب تأكيد المطور — لا يمكن إضافة إدارة بدون تجهيز مسبق لصفحاتها وأكوادها.</div>
            </div>
            <div class="alert alert-danger" style="margin-bottom:0;">
                <i class="fas fa-ban"></i>
                <div>الحذف معطّل — الإدارات مرتبطة بصفحات وأكواد وبيانات حية.</div>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:18px;">
    <div class="card-hd">
        <span class="card-hd-title"><i class="fas fa-sitemap" style="color:var(--blue);margin-left:6px;"></i>الإدارات المسجلة (<?php echo count($departments); ?>)</span>
    </div>

    <?php if (empty($departments)): ?>
    <div class="empty-state">
        <i class="fas fa-sitemap"></i>
        <p>لا توجد إدارات مسجلة بعد</p>
    </div>
    <?php else: ?>
    <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>الإجراءات</th>
                    <th>#</th>
                    <th>كود الإدارة</th>
                    <th>اسم الإدارة</th>
                    <th>ترتيب العرض</th>
                    <th>ملاحظات</th>
                    <th>تاريخ الإنشاء</th>
                    <th>آخر تحديث</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($departments as $i => $d): ?>
                <tr>
                    <td>
                        <div class="flex" style="flex-wrap:wrap;gap:6px;">
                            <button type="button" class="btn btn-ghost btn-sm edit-btn"
                                    data-id="<?php echo intval($d['id']); ?>"
                                    data-code="<?php echo e($d['code']); ?>"
                                    data-name="<?php echo e($d['name']); ?>"
                                    data-order="<?php echo intval($d['display_order']); ?>"
                                    data-notes="<?php echo e($d['notes']); ?>">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="showDeleteDisabled();">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                    <td class="text-muted"><?php echo intval($i) + 1; ?></td>
                    <td><span class="badge bg-blue"><?php echo e($d['code']); ?></span></td>
                    <td style="font-weight:700;"><?php echo e($d['name']); ?></td>
                    <td><?php echo intval($d['display_order']); ?></td>
                    <td class="text-muted"><?php echo ($d['notes'] !== null && trim($d['notes']) !== '') ? e($d['notes']) : '—'; ?></td>
                    <td class="text-muted"><?php echo e(date('d/m/Y H:i', strtotime($d['created_at']))); ?></td>
                    <td class="text-muted"><?php echo e(date('d/m/Y H:i', strtotime($d['updated_at']))); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:500;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="padding:18px 22px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-size:1rem;font-weight:800;">تعديل بيانات الإدارة</h3>
            <button type="button" onclick="closeEditModal()" style="background:none;border:none;color:var(--muted);font-size:1.15rem;cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <form method="post" action="" style="padding:20px 22px;">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId" value="">

            <div class="form-group">
                <label class="form-label" for="editCode">كود الإدارة *</label>
                <input class="form-ctrl" name="code" id="editCode" maxlength="20" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="editName">اسم الإدارة *</label>
                <input class="form-ctrl" name="name" id="editName" maxlength="150" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="editOrder">ترتيب العرض</label>
                <input class="form-ctrl" name="display_order" id="editOrder" type="number" min="0" step="1" value="0">
            </div>
            <div class="form-group">
                <label class="form-label" for="editNotes">ملاحظات</label>
                <textarea class="form-ctrl" name="notes" id="editNotes" rows="3"></textarea>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" class="btn btn-ghost" style="width:auto;height:auto;padding:8px 17px;" onclick="closeEditModal()">إلغاء</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>

<div id="devConfirmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:520;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="padding:18px 22px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-size:1rem;font-weight:800;"><i class="fas fa-triangle-exclamation" style="color:var(--orange);margin-left:6px;"></i>تأكيد المطور</h3>
            <button type="button" onclick="cancelDeveloperConfirm()" style="background:none;border:none;color:var(--muted);font-size:1.15rem;cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <div style="padding:20px 22px;">
            <div class="alert alert-warning" style="margin-bottom:16px;">
                <i class="fas fa-user-gear"></i>
                <div>
                    أنا مبرمج ومطوّر، وأريد فعلًا إضافة هذه الإدارة.
                    كل ما يخصها من صفحات وأكواد وقواعد بيانات جاهز، وتم تجربته واختباره.
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" class="btn btn-ghost" style="width:auto;height:auto;padding:8px 17px;" onclick="cancelDeveloperConfirm()">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="doDeveloperConfirm()"><i class="fas fa-check"></i> تأكيد الإضافة</button>
            </div>
        </div>
    </div>
</div>

<script>
// --- مودال التعديل ---
function openEditModal(btn) {
    document.getElementById('editId').value = btn.getAttribute('data-id') || '';
    document.getElementById('editCode').value = btn.getAttribute('data-code') || '';
    document.getElementById('editName').value = btn.getAttribute('data-name') || '';
    document.getElementById('editOrder').value = btn.getAttribute('data-order') || '0';
    document.getElementById('editNotes').value = btn.getAttribute('data-notes') || '';
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// --- مودال تأكيد المطور (عند الإضافة) ---
// يُعاد false لزرِّ الإرسال: النموذجُ لا يُرسَل إلا من المودال بعد ضبطِ الحقل
function confirmDeveloperAdd(e) {
    e.preventDefault();
    var form = document.getElementById('addForm');
    if (typeof form.reportValidity === 'function' && !form.reportValidity()) { return false; }
    document.getElementById('devConfirmModal').style.display = 'flex';
    return false;
}
function doDeveloperConfirm() {
    document.getElementById('devConfirmField').value = '1';
    document.getElementById('devConfirmModal').style.display = 'none';
    document.getElementById('addForm').submit();
}
function cancelDeveloperConfirm() {
    document.getElementById('devConfirmModal').style.display = 'none';
    document.getElementById('devConfirmField').value = '0';
}

// --- الحذف معطّل ---
function showDeleteDisabled() {
    alert('الحذف معطّل مؤقتًا.\n\nلا يمكن حذف إدارة مرتبطة بصفحات وأكواد وبيانات حية.\nتواصل مع الفريق البرمجي لإجراء أي حذف.');
}

document.querySelectorAll('.edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function() { openEditModal(btn); });
});

// إغلاق المودالات بالضغط على الخلفية
document.querySelectorAll('#editModal, #devConfirmModal').forEach(function(m) {
    m.addEventListener('click', function(e) {
        if (e.target === this) { this.style.display = 'none'; }
    });
});
</script>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
