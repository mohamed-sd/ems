<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * admin/org_structure.php — شاشة الهيكل التنظيمي (update0004 · ORG-16)
 * ───────────────────────────────────────────────────────────────────────────
 * ORG-01 §1.1: مصفوفة الإدارات الأربع عشرة بطبقاتها الثلاث — ورأس كل وحدة
 * مشتق من التكليف النافذ (v_org_unit_heads) لا من عمود يُكتب.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/tenant_scope.php';   // نطاقُ الكيانِ من السياقِ لا من رقمٍ صلب
require_once __DIR__ . '/../includes/screen_contract.php';

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php"); exit(); }
$company_id = ems_scope_company($conn);

$MODULE_CODE = 'admin/org_structure.php';
$can_view = $is_super_admin;
if (!$is_super_admin) {
    $st = $conn->prepare("SELECT rp.can_view FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) { $can_view = intval($row['can_view']) === 1; }
    $st->close();
}
if (!$can_view) { ems_gov_redirect("Location: ../main/dashboard.php?msg=" . rawurlencode('لا صلاحية لشاشة الهيكل ❌')); exit(); }

$units = array();
$r = $conn->query(
    "SELECT u.unit_id, u.unit_code, u.name_ar, u.layer, u.parent_unit_id, u.owner_doc,
            h.head_person_id, hu.name head_name,
            (SELECT COUNT(*) FROM org_assignments a
              WHERE a.org_unit_id = u.unit_id AND a.state = 'active'
                AND CURDATE() BETWEEN a.valid_from AND a.valid_to) active_asg
       FROM org_units u
       LEFT JOIN v_org_unit_heads h ON h.unit_id = u.unit_id
       LEFT JOIN users hu ON hu.id = h.head_person_id
      WHERE u.company_id = {$company_id} AND u.active = 1
      ORDER BY u.unit_id");
while ($r && ($x = $r->fetch_assoc())) { $units[] = $x; }

$byLayer = array('operational' => array(), 'parallel' => array(), 'oversight' => array());
$children = array();
foreach ($units as $u) {
    if ($u['parent_unit_id'] !== null) { $children[intval($u['parent_unit_id'])][] = $u; }
    else { $byLayer[$u['layer']][] = $u; }
}
$layerTitles = array(
    'operational' => '① الطبقة التشغيلية — تحت مدير التشغيل (تبعية إدارية وتشغيلية مباشرة)',
    'parallel' => '② الطبقة الموازية — تحت المدير التنفيذي (تكامل بمسارات اعتماد لا تبعية)',
    'oversight' => '③ المجالان الرقابيان — يمنحان ويرصدان ولا ينفّذان',
);

$page_title = 'إيكوبيشن | الهيكل التنظيمي';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';

function ems_org_unit_card($u)
{
    $head = $u['head_name'] ? htmlspecialchars($u['head_name'])
        : ($u['head_person_id'] ? '#' . intval($u['head_person_id']) : '<span class="orgst-alert">بلا تكليف نافذ</span>');
    echo '<div class="orgst-card">'
        . '<strong>' . htmlspecialchars($u['name_ar']) . '</strong>'
        . ' <small class="orgst-code">(' . htmlspecialchars($u['unit_code']) . ')</small><br>'
        . '<small>الرأس (مشتق): ' . $head . '</small><br>'
        . '<small>تكليفات نافذة: ' . intval($u['active_asg']) . '</small>'
        . '</div>';
}
?>
<style>
    /* UXW-01 ①+②: بطاقاتُ المخططِ التفاعليِّ بأصنافٍ ورموزِ لوحةٍ — لا أنماطَ موضعية */
    .orgst-card { border: 1px solid var(--c-s-ddd); border-radius: 8px; padding: 10px 14px; min-width: 220px; }
    .orgst-alert { color: var(--c-c0392b); }
    .orgst-code { color: var(--c-s-888); }
    .orgst-row { display: flex; gap: 12px; flex-wrap: wrap; }
    .orgst-children {
        margin-top: 8px;
        margin-inline-start: 22px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        border-inline-start: 2px solid var(--c-f0c419, #f0c419);
        padding-inline-start: 12px;
    }
</style>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'الهيكل التنظيمي — الطبقتان والمجالان'; $header_icon = 'fa fa-sitemap';
    $header_actions = array();
    include('../includes/page_header.php');

    ems_screen_about(
        'مصفوفة ORG-01 §1.1: أربع عشرة وحدة لا يبقى مجال بلا تبعية — '
        . 'ثمان تشغيلية تحت مدير التشغيل (ومنها الموارد البشرية إداريًّا باستقلال فني تام)، '
        . 'وأربع موازية تحت المدير التنفيذي، ومجالان رقابيان. '
        . 'رأس كل وحدة يُشتق من التكليف النافذ ولا يُكتب.',
        array('الرأس «بلا تكليف نافذ» يعني وحدة بلا مكلَّف حي — أنشئ تكليفًا من شاشة التكليفات',
              'الموارد البشرية: لا يعتمد مدير التشغيل تعيينًا ولا أجرًا ولا جزاءً (DEC-ORG-A)'));

    /* UXW-01 ⑨: حزمةُ الحالاتِ الدنيا — مخفيةٌ افتراضًا ويُظهرها منطقُ الشاشةِ عند حالِها */
    echo ems_states_bundle('لا وحداتِ هيكلٍ نشطةً لهذه الشركة',
        'وحداتُ الهيكلِ تُبذر من مصفوفةِ ORG-01 §1.1 — راجع مديرَ الصلاحيات');
    ?>

    <?php foreach ($layerTitles as $layer => $title): ?>
    <div class="card"><div class="card-header"><h5><?php echo htmlspecialchars($title); ?></h5></div>
    <div class="card-body">
        <div class="orgst-row">
            <?php foreach ($byLayer[$layer] as $u): ?>
                <div>
                    <?php ems_org_unit_card($u); ?>
                    <?php if (!empty($children[intval($u['unit_id'])])): ?>
                        <div class="orgst-children">
                            <?php foreach ($children[intval($u['unit_id'])] as $c) { ems_org_unit_card($c); } ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div></div>
    <?php endforeach; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
