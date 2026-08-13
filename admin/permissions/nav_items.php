<?php
require_once __DIR__ . '/../includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'إدارة قوائم التنقل الموحّدة';
$current_page = 'permissions';

include '../config.php';

// كونسول المزوّد: nav_items مرجعٌ عام (T_GLOBAL) أسوةً بـmodules وlink_groups،
// وكتابتُه من هنا هي الموضع الشرعي. القراءة والكتابة عبر ems_platform_db().
$mp_nav = ems_platform_db();

// الأبواب الستة الثابتة (UX-00 §6) — مرجعُ العرض والتحقق معًا.
$NAV_DOORS = array(
    'HOME'  => 'الرئيسية',
    'DAILY' => 'العمل اليومي',
    'APPR'  => 'المتابعة والموافقات',
    'REC'   => 'السجلات الرئيسية',
    'REP'   => 'التقارير والتحليلات',
    'SET'   => 'الإعدادات والتدقيق',
);

// المحظور المعماري في الواجهات (UX-00 §4.4) — يُفحص عند حفظ أي اسم عرض.
$NAV_FORBIDDEN_TERMS = array(
    'المروحة', 'المعالجة الذرية', 'تفريع الأثر', 'الحدث الجذري',
    'المحرّك', 'المحرك', 'الناشر', 'المطابقة الثلاثية', 'idempotent', 'الجذر المحايد',
);

/**
 * إنفاذ قواعد التسمية (UX-01 §10.4) — يعيد نص الخطأ أو null.
 * ① «المجموعات ليست شاشات»: الاسم لا يطابق اسم باب.
 * ② خلوّ الاسم من المحظور المعماري.
 */
function nav_label_violation($label, array $doors, array $forbidden)
{
    $t = trim($label);
    foreach ($doors as $doorName) {
        if ($t === $doorName) { return 'الاسم يطابق اسم بابٍ («' . $doorName . '») — المجموعات والأبواب ليست شاشات ❌'; }
    }
    foreach ($forbidden as $term) {
        if ($term !== '' && mb_stripos($t, $term) !== false) {
            return 'الاسم يحمل مصطلحًا معماريًّا محظورًا في الواجهات («' . $term . '») — UX-00 §4.4 ❌';
        }
    }
    return null;
}

$selected_role_id = isset($_GET['role_id']) ? (int) $_GET['role_id'] : 1;

/* تبديل التفعيل — أهم إجراءٍ فردي (تفعيل غير التابع المعطَّل بقرارٍ يدوي) */
if (isset($_GET['toggle_id'])) {
    $id = (int) $_GET['toggle_id'];
    try {
        $row = $mp_nav->selectOne('nav_items', array('columns' => array('id', 'role_id', 'active'), 'where' => array('id' => $id)));
        if ($row) {
            $mp_nav->update('nav_items', array('active' => intval($row['active']) === 1 ? 0 : 1), array('id' => $id));
            ems_flash_set(intval($row['active']) === 1 ? 'عُطّل العنصر — لن يظهر في القائمة ✔' : 'فُعّل العنصر — يظهر لمن يملك صلاحية العرض ✔');
            header('Location: nav_items.php?role_id=' . intval($row['role_id']));
            exit;
        }
    } catch (\Throwable $t) { error_log('admin nav toggle: ' . $t->getMessage()); }
    ems_flash_set('حدث خطأ في التبديل ❌');
    header('Location: nav_items.php?role_id=' . $selected_role_id);
    exit;
}

/* حذف */
if (isset($_GET['delete_id'])) {
    $id = (int) $_GET['delete_id'];
    $rid = $selected_role_id;
    try {
        $row = $mp_nav->selectOne('nav_items', array('columns' => array('role_id'), 'where' => array('id' => $id)));
        if ($row) { $rid = intval($row['role_id']); }
        // [مُستثنى موثَّق — حذف صف مرجعٍ عام أسوة بحذف link_groups]
        $stmt = $conn->prepare('DELETE FROM `nav_items` WHERE `id` = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        ems_flash_set('حُذف العنصر من قائمة الدور ✔');
        header('Location: nav_items.php?role_id=' . $rid);
    } catch (\Throwable $t) {
        error_log('admin nav delete: ' . $t->getMessage());
        ems_flash_set('حدث خطأ في الحذف ❌');
        header('Location: nav_items.php?role_id=' . $rid);
    }
    exit;
}

/* جلب بيانات التعديل */
$editData = null;
if (isset($_GET['edit_id'])) {
    try {
        $editData = $mp_nav->selectOne('nav_items', array('where' => array('id' => (int) $_GET['edit_id'])));
        if ($editData) { $selected_role_id = intval($editData['role_id']); }
    } catch (\Throwable $t) { $editData = null; }
}

/* إضافة / تعديل */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role_id  = (int) ($_POST['role_id'] ?? 0);
    $door     = trim($_POST['door'] ?? '');
    $group_id = !empty($_POST['group_id']) ? (int) $_POST['group_id'] : null;
    $label    = trim($_POST['label_ar'] ?? '');
    $route    = trim(str_replace('\\', '/', $_POST['route'] ?? ''), "/ \t");
    $sort     = (int) ($_POST['sort_order'] ?? 0);
    $counter  = trim($_POST['counter_source'] ?? '');
    $active   = (isset($_POST['active']) && $_POST['active'] == '1') ? 1 : 0;

    $icon = trim($_POST['icon'] ?? 'fa fa-link');
    $icon = trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-zA-Z0-9\-\s]/', '', $icon)));
    if ($icon === '') { $icon = 'fa fa-link'; }

    $violation = nav_label_violation($label, $NAV_DOORS, $NAV_FORBIDDEN_TERMS);

    if ($role_id <= 0 || $label === '' || $route === '' || !isset($NAV_DOORS[$door])) {
        $error_msg = 'الدور والباب والاسم والمسار مطلوبة ❌';
    } elseif ($violation !== null) {
        $error_msg = $violation;
    } else {
        // نسبُ الشاشة تلقائيًّا: أدنى معرّفٍ للمسار (نمط الحارس المركزي) —
        // العنصرُ المربوط يفحص can_view وقت التصيير؛ وغير المربوط ثابتٌ بلا فحص.
        $moduleId = null; $permCode = null;
        try {
            $mod = $mp_nav->selectOne('modules', array('columns' => array('id', 'code'),
                'where' => array('code' => $route), 'orderBy' => 'id'));
            if ($mod) { $moduleId = intval($mod['id']); $permCode = $mod['code']; }
        } catch (\Throwable $t) {}

        $data = array(
            'role_id' => $role_id, 'door' => $door, 'group_id' => $group_id,
            'module_id' => $moduleId, 'label_ar' => $label, 'route' => $route,
            'icon' => $icon, 'sort_order' => $sort,
            'counter_source' => ($counter !== '' ? $counter : null),
            'permission_code' => $permCode, 'active' => $active,
        );
        try {
            if (!empty($_POST['edit_id'])) {
                $mp_nav->update('nav_items', $data, array('id' => (int) $_POST['edit_id']));
                ems_flash_set('حُفظ العنصر ✔');
                header('Location: nav_items.php?role_id=' . $role_id);
                exit;
            }
            $dup = $mp_nav->selectOne('nav_items', array('columns' => array('id'),
                'where' => array('role_id' => $role_id, 'route' => $route)));
            if ($dup !== null) {
                $error_msg = 'هذا المسار موجودٌ في قائمة الدور مسبقًا — لا تكرار ❌';
            } else {
                $mp_nav->insert('nav_items', $data);
                ems_flash_set('أُضيف العنصر لقائمة الدور ✔');
                header('Location: nav_items.php?role_id=' . $role_id);
                exit;
            }
        } catch (\Throwable $t) {
            error_log('admin nav save: ' . $t->getMessage());
            if (!isset($error_msg)) { $error_msg = 'حدث خطأ في الحفظ ❌'; }
        }
    }
}

/* الأدوار النشطة كلها (القوائم لكل دورٍ لا للرئيسي وحده) */
$roles = array();
try {
    $roles = $mp_nav->select('roles', array('columns' => array('id', 'name'),
        'whereRaw' => "status = '1' OR status = 1", 'orderBy' => 'id'));
} catch (\Throwable $t) {}

/* مجموعات الدور المختار (فواصل داخل الأبواب) */
$role_groups = array();
try {
    $role_groups = $mp_nav->select('link_groups', array('columns' => array('id', 'name'),
        'where' => array('owner_role_id' => $selected_role_id, 'is_active' => 1), 'orderBy' => 'display_order'));
} catch (\Throwable $t) {}

/* عناصر الدور المختار + حالة الصلاحية لكل عنصر (استعلامٌ خام للربط الثلاثي) */
$items = array();
$stats = array('total' => 0, 'active' => 0, 'shown' => 0, 'perm_hidden' => 0, 'inactive' => 0);
$q = $conn->prepare(
    "SELECT n.*, g.name AS group_name,
            (SELECT p.can_view FROM role_permissions p
             WHERE p.module_id = n.module_id AND p.role_id = n.role_id LIMIT 1) AS cv
     FROM nav_items n
     LEFT JOIN link_groups g ON g.id = n.group_id
     WHERE n.role_id = ?
     ORDER BY FIELD(n.door,'HOME','DAILY','APPR','REC','REP','SET'), n.sort_order, n.id");
$q->bind_param('i', $selected_role_id);
$q->execute();
$res = $q->get_result();
while ($row = $res->fetch_assoc()) {
    $row['shown'] = intval($row['active']) === 1
        && ($row['permission_code'] === null || intval($row['cv'] ?? 0) === 1);
    $items[] = $row;
    $stats['total']++;
    if (intval($row['active']) === 1) { $stats['active']++; } else { $stats['inactive']++; }
    if ($row['shown']) { $stats['shown']++; }
    elseif (intval($row['active']) === 1) { $stats['perm_hidden']++; }
}

/* الشاشات المرشّحة للإضافة (datalist) */
$module_routes = array();
try {
    foreach ($mp_nav->select('modules', array('columns' => array('code', 'name'),
        'whereRaw' => "is_link = '1'", 'orderBy' => 'code')) as $m) {
        if (!isset($module_routes[$m['code']])) { $module_routes[$m['code']] = $m['name']; }
    }
} catch (\Throwable $t) {}

require_once __DIR__ . '/../includes/layout_head.php';
?>

<style>
.page-shell { background: linear-gradient(135deg,#f5f7fa 0%,#f0f2f5 100%); min-height: calc(100vh - 100px); padding: 2rem; }
.page-header h2 { color: var(--navy); font-weight: 700; margin-bottom: .5rem; }
.page-sub { color:#64748b; margin-bottom:2rem; }
.card { background:#fff; border:none; border-radius:12px; box-shadow:0 2px 8px rgba(12,28,62,.08); margin-bottom:2rem; }
.card-header { background:linear-gradient(135deg,var(--navy) 0%,var(--navy-m) 100%); color:#fff; padding:1.2rem 1.5rem; border-radius:12px 12px 0 0; font-weight:600; display:flex; justify-content:space-between; align-items:center; }
.card-body { padding:1.5rem 2rem; }
.form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1.2rem; }
.form-grid label { font-weight:600; color:var(--navy); margin-bottom:.4rem; display:block; }
.form-grid input, .form-grid select { width:100%; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:8px; font-family:'Cairo',sans-serif; }
.success-message { border-radius:8px; padding:1rem 1.5rem; margin-bottom:1.5rem; border-right:4px solid; }
.success-message.is-success { background:#d1f3d1; color:#059669; border-right-color:#059669; }
.success-message.is-error { background:#fee2e2; color:#dc2626; border-right-color:#ef4444; }
.stats { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1.5rem; }
.stat { background:#fff; border-radius:10px; padding:.8rem 1.4rem; box-shadow:0 2px 6px rgba(12,28,62,.07); font-weight:600; color:var(--navy); }
.stat b { font-size:1.3rem; display:block; }
.door-head td { background:#eef2f7 !important; font-weight:700; color:var(--navy); }
.group-cell { color:#92700a; font-size:.85rem; }
.badge { border-radius:6px; padding:.25rem .6rem; font-size:.78rem; font-weight:700; }
.badge-shown { background:#d1f3d1; color:#059669; }
.badge-perm { background:#fef3c7; color:#b45309; }
.badge-off { background:#e5e7eb; color:#475569; }
.btn { padding:.45rem .9rem; border-radius:6px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; border:none; cursor:pointer; font-size:.85rem; }
.btn-primary { background:var(--blue); color:#fff; }
.btn-danger { background:#f59e0b; color:#fff; }
.btn-primary { background:#059669; color:#fff; }
.btn-danger { background:#ef4444; color:#fff; }
.back-btn { background:#e5e7eb; color:var(--navy); }
table.navtbl { width:100%; border-collapse:collapse; }
table.navtbl th { background:linear-gradient(135deg,var(--navy) 0%,var(--navy-m) 100%); color:#fff; padding:.7rem; font-size:.85rem; }
table.navtbl td { padding:.6rem .7rem; border-bottom:1px solid #eef1f5; font-size:.88rem; }
table.navtbl tr:hover td { background:#f8fafc; }
.route-code { direction:ltr; font-family:monospace; font-size:.78rem; color:#475569; }
</style>

<div class="page-shell">
    <div class="page-header">
        <h2><i class="fa fa-sitemap"></i> قوائم التنقل الموحّدة (الأبواب الستة)</h2>
        <p class="page-sub">
            الظهور للمستخدم = عنصرٌ <strong>تابعٌ للدور</strong> هنا (نشط) <strong>و</strong>عنده
            <strong>صلاحية عرض</strong> على شاشته — العنصر المعطَّل لا يظهر ولو مُنحت صلاحيته،
            والفاقد للصلاحية لا يظهر ولو كان نشطًا.
            <a href="link_groups.php?role_id=<?php echo $selected_role_id; ?>">إدارة المجموعات ↗</a>
        </p>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="success-message is-success"><?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>
    <?php if (isset($error_msg)): ?>
        <div class="success-message is-error"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body" style="display:flex;gap:1rem;align-items:end;flex-wrap:wrap;">
            <div style="min-width:260px;">
                <label style="font-weight:600;color:var(--navy);display:block;margin-bottom:.4rem;" for="emsf_687_b59a5">الدور</label>
                <select onchange="location='nav_items.php?role_id='+this.value" style="width:100%;padding:.7rem 1rem;border:1.5px solid var(--border);border-radius:8px;" id="emsf_687_b59a5">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo intval($r['id']); ?>" <?php echo intval($r['id']) === $selected_role_id ? 'selected' : ''; ?>>
                            <?php echo intval($r['id']) . ' — ' . htmlspecialchars($r['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="stats" style="margin-bottom:0;">
                <div class="stat"><b><?php echo $stats['total']; ?></b> عنصرًا</div>
                <div class="stat" style="color:#059669;"><b><?php echo $stats['shown']; ?></b> يظهر فعلًا</div>
                <div class="stat" style="color:#b45309;"><b><?php echo $stats['perm_hidden']; ?></b> محجوب صلاحيةً</div>
                <div class="stat" style="color:#475569;"><b><?php echo $stats['inactive']; ?></b> معطَّل (غير تابع)</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span><i class="fa <?php echo $editData ? 'fa-pen' : 'fa-plus'; ?>"></i>
                <?php echo $editData ? 'تعديل عنصر' : 'إضافة عنصرٍ لقائمة الدور'; ?></span>
            <?php if ($editData): ?><a class="btn back-btn" href="nav_items.php?role_id=<?php echo $selected_role_id; ?>">إلغاء التعديل</a><?php endif; ?>
        </div>
        <div class="card-body">
            <form method="post">
                <?php if ($editData): ?><input type="hidden" name="edit_id" value="<?php echo intval($editData['id']); ?>"><?php endif; ?>
                <input type="hidden" name="role_id" value="<?php echo $selected_role_id; ?>">
                <div class="form-grid">
                    <div>
                        <label for="emsf_688_59bf8">الباب *</label>
                        <select name="door" required id="emsf_688_59bf8">
                            <?php foreach ($NAV_DOORS as $k => $v): ?>
                                <option value="<?php echo $k; ?>" <?php echo ($editData && $editData['door'] === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="emsf_689_b5688">المجموعة (فاصلٌ داخل الباب)</label>
                        <select name="group_id" id="emsf_689_b5688">
                            <option value="">— بلا مجموعة —</option>
                            <?php foreach ($role_groups as $g): ?>
                                <option value="<?php echo intval($g['id']); ?>" <?php echo ($editData && intval($editData['group_id'] ?? 0) === intval($g['id'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="emsf_690_8d38d">اسم العرض *</label>
                        <input type="text" name="label_ar" required value="<?php echo htmlspecialchars($editData['label_ar'] ?? ''); ?>" placeholder="بلغة المهمة — لا مصطلحَ معماريًّا" id="emsf_690_8d38d">
                    </div>
                    <div>
                        <label for="emsf_691_02116">المسار *</label>
                        <input type="text" name="route" list="routes" required dir="ltr" value="<?php echo htmlspecialchars($editData['route'] ?? ''); ?>" placeholder="Finance/example.php" id="emsf_691_02116">
                        <datalist id="routes">
                            <?php foreach ($module_routes as $code => $name): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div>
                        <label for="emsf_692_b0af7">الأيقونة</label>
                        <input type="text" name="icon" dir="ltr" value="<?php echo htmlspecialchars($editData['icon'] ?? 'fa fa-link'); ?>" id="emsf_692_b0af7">
                    </div>
                    <div>
                        <label for="emsf_693_c424f">الترتيب</label>
                        <input type="number" name="sort_order" value="<?php echo intval($editData['sort_order'] ?? 0); ?>" id="emsf_693_c424f">
                    </div>
                    <div>
                        <label for="emsf_694_43bc6">مصدر العدّاد (اختياري)</label>
                        <input type="text" name="counter_source" dir="ltr" value="<?php echo htmlspecialchars($editData['counter_source'] ?? ''); ?>" placeholder="hours_approval" id="emsf_694_43bc6">
                    </div>
                    <div>
                        <label for="emsf_695_dac58">الحالة</label>
                        <select name="active" id="emsf_695_dac58">
                            <option value="1" <?php echo (!$editData || intval($editData['active']) === 1) ? 'selected' : ''; ?>>نشط (تابعٌ للدور)</option>
                            <option value="0" <?php echo ($editData && intval($editData['active']) === 0) ? 'selected' : ''; ?>>معطَّل</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top:1.2rem;">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> حفظ</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span><i class="fa fa-list"></i> قائمة الدور بالأبواب</span></div>
        <div class="card-body" style="padding:0;">
            <table class="navtbl">
                <thead><tr>
                    <th>الاسم</th><th>المسار</th><th>المجموعة</th><th>الترتيب</th>
                    <th>العدّاد</th><th>الحالة الفعلية</th><th>إجراءات</th>
                </tr></thead>
                <tbody>
                <?php
                $curDoor = null;
                foreach ($items as $it) {
                    if ($it['door'] !== $curDoor) {
                        $curDoor = $it['door'];
                        echo '<tr class="door-head"><td colspan="7">' . htmlspecialchars($NAV_DOORS[$curDoor] ?? $curDoor) . '</td></tr>';
                    }
                    $state = $it['shown']
                        ? '<span class="badge badge-shown">يظهر</span>'
                        : (intval($it['active']) === 1
                            ? '<span class="badge badge-perm">محجوب — بلا صلاحية عرض</span>'
                            : '<span class="badge badge-off">معطَّل — غير تابع</span>');
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($it['label_ar']) . '</td>';
                    echo '<td class="route-code">' . htmlspecialchars($it['route']) . '</td>';
                    echo '<td class="group-cell">' . htmlspecialchars($it['group_name'] ?? '—') . '</td>';
                    echo '<td>' . intval($it['sort_order']) . '</td>';
                    echo '<td class="route-code">' . htmlspecialchars($it['counter_source'] ?? '—') . '</td>';
                    echo '<td>' . $state . '</td>';
                    echo '<td style="white-space:nowrap;">';
                    echo '<a class="btn ' . (intval($it['active']) === 1 ? 'btn-danger' : 'btn-primary') . '" href="?toggle_id=' . intval($it['id']) . '&role_id=' . $selected_role_id . '">'
                        . (intval($it['active']) === 1 ? '<i class="fa fa-eye-slash"></i> تعطيل' : '<i class="fa fa-eye"></i> تفعيل') . '</a> ';
                    echo '<a class="btn btn-primary" href="?edit_id=' . intval($it['id']) . '"><i class="fa fa-pen"></i></a> ';
                    echo '<a class="btn btn-danger" href="?delete_id=' . intval($it['id']) . '&role_id=' . $selected_role_id . '" onclick="return confirm(\'حذف العنصر من قائمة الدور؟\')"><i class="fa fa-trash"></i></a>';
                    echo '</td></tr>';
                }
                if (empty($items)) {
                    echo '<tr><td colspan="7" style="text-align:center;color:#64748b;padding:2rem;">لا عناصرَ لهذا الدور بعد — هذا الدور ما زال على مصادره القديمة حتى يُبذر ويُفعَّل في العلم.</td></tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <a class="btn back-btn" href="index.php"><i class="fa fa-arrow-right"></i> رجوع لمركز الصلاحيات</a>
</div>

<?php require_once __DIR__ . '/../includes/layout_foot.php'; ?>
