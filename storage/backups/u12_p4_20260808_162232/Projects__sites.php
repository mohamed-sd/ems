<?php
/**
 * Projects/sites.php — مواقعُ التنفيذ (H-05 · OPM-01 §2-③)
 * ───────────────────────────────────────────────────────────────────────────
 * الهرمُ السباعي ③: «الموقعُ/المنجم مكانُ التنفيذ الفعليُّ الذي تُسجَّل فيه
 * الوحدات — والمنجمُ حالةٌ منه لا فرقَ في المعالجة». المشروعُ قد يضم عدةَ
 * مواقعَ، والموقعُ قد تكون له عدةُ عقود — لا يُفترض واحدٌ أبدًا.
 *
 * **الصلاحيةُ صارمة** (نمطُ التسويات): الوحدةُ بكودها الحرفي وغيابُها منعٌ —
 * لا اعتمادَ على المطابقة الفضفاضة التي تفتح على مصراعيها.
 *
 * لا حذفَ: الموقعُ مرجعُ تسجيلٍ تاريخي — التعطيلُ بـstatus لا بالمسح،
 * والافتراضيُّ (is_default — موقعُ الترحيل الرجعي) لا يُعطَّل من الشاشة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../includes/audit_trail.php';

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

// ── صلاحيةٌ صارمة: الوحدةُ بكودها الحرفي وغيابُها منع ─────────────────────
$MODULE_CODE = 'Projects/sites.php';
$can_view = $can_add = $can_edit = false;
if ($is_super_admin) {
    $can_view = $can_add = $can_edit = true;
} else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add, rp.can_edit
                            FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view = (intval($row['can_view']) === 1);
        $can_add  = (intval($row['can_add'])  === 1);
        $can_edit = (intval($row['can_edit']) === 1);
    }
    $st->close();
}
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+مواقع+التنفيذ+❌");
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('sites super') : ems_tenant_db();

$KINDS = array('site' => 'موقع تشغيل', 'mine' => 'منجم');

// ── حفظٌ (إضافة/تعديل) ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['site_name'])) {
    $sid        = intval($_POST['site_id'] ?? 0);
    $writePerm  = ($sid > 0) ? $can_edit : $can_add;
    if (!$writePerm) {
        header("Location: sites.php?msg=لا+توجد+صلاحية+لهذا+الإجراء+❌"); exit();
    }
    $project_id = intval($_POST['project_id'] ?? 0);
    $name       = trim(strval($_POST['site_name'] ?? ''));
    $kind       = isset($KINDS[$_POST['site_kind'] ?? '']) ? strval($_POST['site_kind']) : 'site';
    $resp       = intval($_POST['responsible_employee_id'] ?? 0);
    $loc        = trim(strval($_POST['location_text'] ?? ''));
    $status     = (intval($_POST['status'] ?? 1) === 1) ? 1 : 0;

    if ($project_id <= 0 || $name === '') {
        header("Location: sites.php?msg=المشروع+والاسم+إلزاميان+❌"); exit();
    }
    // المشروعُ من نطاق الشركة (البوابةُ ترفض الأجنبي)
    $proj = null;
    try { $proj = $gate->selectOne('project', array('columns' => array('id'), 'where' => array('id' => $project_id))); }
    catch (\Throwable $t) { $proj = null; }
    if (!$proj) { header("Location: sites.php?msg=المشروع+غير+موجود+في+نطاقك+❌"); exit(); }
    // المسؤولُ من موظفي الشركة إن حُدّد
    if ($resp > 0) {
        $emp = null;
        try { $emp = $gate->selectOne('employees', array('columns' => array('id'), 'where' => array('id' => $resp))); }
        catch (\Throwable $t) { $emp = null; }
        if (!$emp) { header("Location: sites.php?msg=المسؤول+غير+موجود+في+نطاقك+❌"); exit(); }
    }

    $data = array(
        'project_id' => $project_id, 'name' => $name, 'site_kind' => $kind,
        'responsible_employee_id' => $resp > 0 ? $resp : null,
        'location_text' => $loc !== '' ? $loc : null, 'status' => $status,
    );
    try {
        if ($sid > 0) {
            $before = $gate->selectOne('sites', array('where' => array('id' => $sid)));
            if (!$before) { header("Location: sites.php?msg=الموقع+غير+موجود+❌"); exit(); }
            if (intval($before['is_default']) === 1 && $status === 0) {
                // الافتراضيُّ لا يُعطَّل — هو مرجعُ الترحيل الرجعي للعقود القائمة
                header("Location: sites.php?msg=الموقع+الافتراضي+لا+يُعطَّل+—+هو+مرجع+الترحيل+❌"); exit();
            }
            $gate->update('sites', $data, array('id' => $sid));
            ems_audit_change($conn, 'projects', 'Projects/sites.php', 'update', $sid,
                array_intersect_key($before, $data), $data,
                array('company_id' => $company_id, 'user_id' => $uid));
            header("Location: sites.php?msg=تم+تعديل+الموقع+✅"); exit();
        } else {
            $newId = $gate->insert('sites', $data);
            ems_audit_change($conn, 'projects', 'Projects/sites.php', 'create', intval($newId),
                array(), $data, array('company_id' => $company_id, 'user_id' => $uid));
            header("Location: sites.php?msg=أُنشئ+الموقع+✅"); exit();
        }
    } catch (\Throwable $t) {
        // گوتشا مقيسة: خرقُ UNIQUE يعود false/يرمي بحسب الوضع — الرسالةُ تسمّي السبب الأرجح
        header("Location: sites.php?msg=" . rawurlencode('تعذّر الحفظ — الاسم مكرر داخل المشروع؟') . "+❌"); exit();
    }
}

// ── القراءة ────────────────────────────────────────────────────────────────
$filter_project = intval($_GET['project_id'] ?? 0);
$rows = array();
try {
    $where = "COALESCE(s.is_deleted,0)=0";
    $params = array();
    if ($filter_project > 0) { $where .= " AND s.project_id = ?"; $params[] = $filter_project; }
    $rows = $gate->scopedQuery(array(
        'scope'  => array('s' => 'sites'),
        'enrich' => array('p' => 'project', 'e' => 'employees'),
    ), "SELECT s.*, p.name AS project_name, e.name AS responsible_name,
               (SELECT COUNT(*) FROM contracts c WHERE c.site_id = s.id) AS contracts_count
        FROM sites s
        LEFT JOIN project p ON p.id = s.project_id
        LEFT JOIN employees e ON e.id = s.responsible_employee_id
        WHERE {TENANT_SCOPE} AND {$where}
        ORDER BY p.name, s.is_default DESC, s.name", $params);
} catch (\Throwable $t) { $rows = array(); }

$projects_options = array();
try {
    $projects_options = $gate->scopedQuery(array('scope' => array('p' => 'project')),
        "SELECT p.id, p.name FROM project p
         WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0 AND p.status='1'
         ORDER BY p.name");
} catch (\Throwable $t) { $projects_options = array(); }

$employees_options = array();
try {
    // گوتشا مقيسة (H-08-①): employees بلا عمود is_deleted — الشرطُ السابق كان
    // يُفشل الاستعلامَ صامتًا (catch) فتفرغ منسدلةُ المسؤول.
    $employees_options = $gate->scopedQuery(array('scope' => array('e' => 'employees')),
        "SELECT e.id, e.name FROM employees e
         WHERE {TENANT_SCOPE}
         ORDER BY e.name");
} catch (\Throwable $t) { $employees_options = array(); }

$page_title = 'إيكوبيشن | مواقع التنفيذ';
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'مواقع التنفيذ'; $header_icon = 'fa fa-map-location-dot';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('href' => 'javascript:void(0)', 'id' => 'toggleForm',
            'icon' => 'fa fa-plus', 'label' => 'موقع جديد', 'class' => 'add');
    }
    $header_back = array('href' => 'projects.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'المشاريع');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    ?>

    <?php if ($can_add || $can_edit): ?>
    <form method="post" class="allforms" id="siteForm">
        <div class="card"><div class="card-header"><h5><i class="fa fa-map-location-dot"></i> بيانات الموقع</h5></div>
        <div class="card-body"><div class="form-grid">
            <input type="hidden" name="site_id" id="f_site_id" value="">
            <div class="form-group">
                <label>المشروع <span style="color:#c00">*</span></label>
                <select name="project_id" id="f_project_id" required>
                    <option value="">— اختر المشروع —</option>
                    <?php foreach ($projects_options as $p): ?>
                        <option value="<?php echo intval($p['id']); ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>اسم الموقع <span style="color:#c00">*</span></label>
                <input type="text" name="site_name" id="f_site_name" required maxlength="190" placeholder="مثال: منجم أ — الواجهة الشمالية">
            </div>
            <div class="form-group">
                <label>النوع</label>
                <select name="site_kind" id="f_site_kind">
                    <?php foreach ($KINDS as $k => $lbl): ?>
                        <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>مسؤول الموقع</label>
                <select name="responsible_employee_id" id="f_resp">
                    <option value="">— بلا —</option>
                    <?php foreach ($employees_options as $e): ?>
                        <option value="<?php echo intval($e['id']); ?>"><?php echo htmlspecialchars($e['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>الوصف المكاني</label>
                <input type="text" name="location_text" id="f_loc" maxlength="255">
            </div>
            <div class="form-group">
                <label>الحالة</label>
                <select name="status" id="f_status">
                    <option value="1">نشط</option>
                    <option value="0">معطَّل</option>
                </select>
            </div>
        </div>
        <div style="margin-top:12px">
            <button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ</button>
        </div>
        </div></div>
    </form>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
            <strong>المشروع:</strong>
            <select name="project_id" onchange="this.form.submit()" style="min-width:220px">
                <option value="0">الكل</option>
                <?php foreach ($projects_options as $p): ?>
                    <option value="<?php echo intval($p['id']); ?>" <?php echo $filter_project === intval($p['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="table-container">
            <table class="alltables display nowrap" id="sitesTable" style="width:100%">
                <thead><tr>
                    <th>الإجراءات</th><th>الموقع</th><th>النوع</th><th>المشروع</th>
                    <th>المسؤول</th><th>العقود</th><th>الحالة</th>
                    <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    </tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <?php if ($can_edit): ?>
                            <a href="javascript:void(0)" class="editBtn action-btn edit"
                               data-id="<?php echo intval($r['id']); ?>"
                               data-project="<?php echo intval($r['project_id']); ?>"
                               data-name="<?php echo htmlspecialchars($r['name'], ENT_QUOTES); ?>"
                               data-kind="<?php echo htmlspecialchars($r['site_kind'], ENT_QUOTES); ?>"
                               data-resp="<?php echo intval($r['responsible_employee_id']); ?>"
                               data-loc="<?php echo htmlspecialchars((string)($r['location_text'] ?? ''), ENT_QUOTES); ?>"
                               data-status="<?php echo intval($r['status']); ?>"
                               title="تعديل"><i class="fas fa-edit"></i></a>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($r['name']); ?></strong>
                            <?php if (intval($r['is_default']) === 1): ?>
                                <span class="badge badge-secondary" title="موقعُ الترحيل الرجعي — المشروع كان الموقع ضمنًا">افتراضي</span>
                            <?php endif; ?></td>
                        <td><?php echo htmlspecialchars($KINDS[$r['site_kind']] ?? $r['site_kind']); ?></td>
                        <td><?php echo htmlspecialchars($r['project_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['responsible_name'] ?? '—'); ?></td>
                        <td><?php echo intval($r['contracts_count']); ?></td>
                        <td><?php echo intval($r['status']) === 1
                            ? "<span class='badge badge-success'>نشط</span>"
                            : "<span class='badge badge-danger'>معطَّل</span>"; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var toggleBtn = document.getElementById('toggleForm');
        var form = document.getElementById('siteForm');
        if (toggleBtn && form) {
            toggleBtn.addEventListener('click', function () {
                form.reset();
                document.getElementById('f_site_id').value = '';
                form.classList.toggle('allforms-visible');
            });
        }
        document.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.editBtn');
            if (!btn || !form) return;
            document.getElementById('f_site_id').value = btn.dataset.id;
            document.getElementById('f_project_id').value = btn.dataset.project;
            document.getElementById('f_site_name').value = btn.dataset.name;
            document.getElementById('f_site_kind').value = btn.dataset.kind;
            document.getElementById('f_resp').value = btn.dataset.resp > 0 ? btn.dataset.resp : '';
            document.getElementById('f_loc').value = btn.dataset.loc;
            document.getElementById('f_status').value = btn.dataset.status;
            form.classList.add('allforms-visible');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
})();
</script>
</body>
</html>
