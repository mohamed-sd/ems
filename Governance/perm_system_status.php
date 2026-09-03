<?php
/**
 * Governance/perm_system_status.php — حالة منظومة الصلاحيات (PERM-SCR-01 ⑨)
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **قراءة فقط**: لا فورم ولا كتابة. لوحة مراقبة تقيس فجوات المنظومة
 *   وتدل على شاشة العلاج - ورقم لا يتعمق فيه لا يقرر عليه.
 * ◆ **وكل مؤشر بمقامه المعلن**: النسبة تقال مع بسطها ومقامها لا مجردة،
 *   فقارئها يرى مم اشتقت.
 * ◆ **والفجوات مسماة لا مجملة**: سبع فحوص، لكل فحص عدده ووجهة علاجه،
 *   والاخضر منها يقال اخضر ولا يخفى.
 * ⛔ ولا يقاس هنا جدول مستاجر - الجداول السبعة كلها عامة بلا عزل شركة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../company/login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/kpi_card.php';
require_once __DIR__ . '/../includes/date_format.php';

$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$MODULE_CODE = 'Governance/perm_system_status.php';
$__pp = check_page_permissions($conn, $MODULE_CODE);
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحية لحالة النظام', 'GOV-PERM-403',
        'اطلب المنحة من مدير الصلاحيات ان كانت ضمن عملك');
    exit();
}

/** عدد قياسي واحد - يرد صفرا عند تعذر الاستعلام لا يكسر اللوحة. */
function pst_n($conn, $sql)
{
    $r = @mysqli_query($conn, $sql);
    if (!$r) { return 0; }
    $row = mysqli_fetch_row($r);
    return $row ? (int) $row[0] : 0;
}

$n = array(
    'roles'        => pst_n($conn, 'SELECT COUNT(*) FROM roles'),
    'roles_on'     => pst_n($conn, "SELECT COUNT(*) FROM roles WHERE status = '1'"),
    'modules'      => pst_n($conn, 'SELECT COUNT(*) FROM modules'),
    'grants'       => pst_n($conn, 'SELECT COUNT(*) FROM role_permissions'),
    'grants_view'  => pst_n($conn, 'SELECT COUNT(*) FROM role_permissions WHERE can_view = 1'),
    'groups'       => pst_n($conn, 'SELECT COUNT(*) FROM link_groups'),
    'groups_on'    => pst_n($conn, 'SELECT COUNT(*) FROM link_groups WHERE is_active = 1'),
    'navs'         => pst_n($conn, 'SELECT COUNT(*) FROM nav_items'),
    'navs_on'      => pst_n($conn, 'SELECT COUNT(*) FROM nav_items WHERE active = 1'),
    'screens'      => pst_n($conn, 'SELECT COUNT(*) FROM screen_about'),
    'reports'      => pst_n($conn, 'SELECT COUNT(*) FROM report_role_permissions'),
);

/* ── الفحوص السبعة: كل واحد عدده ووجهة علاجه ── */
$CHECKS = array(
    array(
        'key'  => 'roles_no_grant',
        'name' => 'ادوار نشطة بلا صلاحية عرض واحدة',
        'n'    => pst_n($conn, "SELECT COUNT(*) FROM roles r WHERE r.status = '1'
                                  AND NOT EXISTS (SELECT 1 FROM role_permissions p
                                                   WHERE p.role_id = r.id AND p.can_view = 1)"),
        'why'  => 'دور نشط لا يفتح شاشة واحدة: اما ينبغي تعطيله او ينقصه منح',
        'go'   => 'perm_matrix.php',
        'goAr' => 'امنحه من المصفوفة',
    ),
    array(
        'key'  => 'modules_no_owner',
        'name' => 'وحدات بلا دور مالك',
        'n'    => pst_n($conn, 'SELECT COUNT(*) FROM modules WHERE owner_role_id IS NULL OR owner_role_id = 0'),
        'why'  => 'شاشة لا تنسب الى دور يملك قرارها فلا يعرف من يراجع صلاحيتها',
        'go'   => 'perm_modules.php?owner=0',
        'goAr' => 'اسند مالكا',
    ),
    array(
        'key'  => 'modules_no_grant',
        'name' => 'وحدات لا يفتحها اي دور',
        'n'    => pst_n($conn, 'SELECT COUNT(*) FROM modules m
                                 WHERE NOT EXISTS (SELECT 1 FROM role_permissions p
                                                    WHERE p.module_id = m.id AND p.can_view = 1)'),
        'why'  => 'شاشة مسجلة لا يصل اليها احد: اما مهجورة او ينقصها منح',
        'go'   => 'perm_matrix.php',
        'goAr' => 'راجع المصفوفة',
    ),
    array(
        'key'  => 'nav_no_perm',
        'name' => 'بنود ملاحة نشطة بلا كود صلاحية',
        'n'    => pst_n($conn, "SELECT COUNT(*) FROM nav_items
                                 WHERE active = 1 AND (permission_code IS NULL OR permission_code = '')"),
        'why'  => 'البند يظهر لكل من يملك الدور بلا سؤال عن صلاحية الشاشة',
        'go'   => 'perm_nav_items.php?active=1',
        'goAr' => 'املا كود الصلاحية',
    ),
    array(
        'key'  => 'nav_no_group',
        'name' => 'بنود نشطة في مجموعة معطلة او مفقودة',
        'n'    => pst_n($conn, 'SELECT COUNT(*) FROM nav_items n
                                 WHERE n.active = 1
                                   AND NOT EXISTS (SELECT 1 FROM link_groups g
                                                    WHERE g.id = n.group_id AND g.is_active = 1)'),
        'why'  => 'البند بلا وعاء نشط يسقط من السايدبار صامتا',
        'go'   => 'perm_link_groups.php',
        'goAr' => 'فعل وعاءه',
    ),
    array(
        'key'  => 'groups_empty',
        'name' => 'مجموعات نشطة بلا بند واحد',
        'n'    => pst_n($conn, 'SELECT COUNT(*) FROM link_groups g
                                 WHERE g.is_active = 1
                                   AND NOT EXISTS (SELECT 1 FROM nav_items n
                                                    WHERE n.group_id = g.id AND n.active = 1)'),
        'why'  => 'راس طي فارغ لا يظهر شيئا فيشوش السجل',
        'go'   => 'perm_link_groups.php?is_active=1',
        'goAr' => 'املاها او عطلها',
    ),
    array(
        'key'  => 'about_short',
        'name' => 'شاشات وصفها ناقص',
        'n'    => pst_n($conn, 'SELECT COUNT(*) FROM screen_about WHERE CHAR_LENGTH(description) < 40'),
        'why'  => 'بطاقة عن الشاشة تفتح على وصف لا يفيد قارئه',
        'go'   => 'perm_screen_guide.php?state=short',
        'goAr' => 'اكتب وصفها',
    ),
);

$openN = 0;
foreach ($CHECKS as $c) { if ($c['n'] > 0) { $openN++; } }

$possible = $n['roles'] * $n['modules'];
$coverage = $possible > 0 ? round(($n['grants_view'] / $possible) * 100, 1) : 0.0;

$page_title = 'ايكوبيشن | حالة نظام الصلاحيات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
function pt_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
$NOW = 'لحظي (' . ems_fmt_date(time(), 'datetime') . ')';
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'حالة نظام الصلاحيات';
    $header_icon = 'fa fa-heartbeat';
    $header_actions = array();
    $header_back = false;
    include '../includes/page_header.php';
    ?>

    <div class="ems-grid">
        <?php
        echo ems_kpi_card(array(
            'title' => 'فحوص مفتوحة', 'value' => number_format($openN), 'unit' => 'فحص',
            'period' => $NOW, 'status' => $openN > 0 ? 'warn' : 'ok', 'drill' => 'perm_system_status.php',
            'comparison' => 'من ' . count($CHECKS) . ' فحصا', 'icon' => 'fa-clipboard-check', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'تغطية المصفوفة', 'value' => $coverage, 'unit' => 'بالمئة',
            'period' => $NOW, 'status' => 'neutral', 'drill' => 'perm_matrix.php',
            'comparison' => number_format($n['grants_view']) . ' من ' . number_format($possible) . ' ممكنة',
            'icon' => 'fa-chart-pie', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'الادوار النشطة', 'value' => number_format($n['roles_on']), 'unit' => 'دور',
            'period' => $NOW, 'status' => 'neutral', 'drill' => 'perm_roles.php?status=1',
            'comparison' => 'من ' . number_format($n['roles']) . ' مسجلا', 'icon' => 'fa-user-tag', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'بنود الملاحة النشطة', 'value' => number_format($n['navs_on']), 'unit' => 'بند',
            'period' => $NOW, 'status' => 'neutral', 'drill' => 'perm_nav_items.php?active=1',
            'comparison' => 'من ' . number_format($n['navs']) . ' مسجلا', 'icon' => 'fa-compass', 'class' => 'ems-col-3'));
        ?>
    </div>

    <div class="card">
        <div class="card-header"><h5><i class="fa fa-clipboard-check"></i> فجوات المنظومة</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="alltables display" id="permStatusTable" data-no-dt="hard">
                    <thead><tr>
                        <th>الفحص</th><th>العدد</th><th>لماذا يهم</th><th>العلاج</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($CHECKS as $c): ?>
                        <tr>
                            <td><?php echo pt_e($c['name']); ?></td>
                            <td><strong><?php echo (int) $c['n']; ?></strong>
                                <?php echo $c['n'] > 0 ? '' : '(سليم)'; ?></td>
                            <td><?php echo pt_e($c['why']); ?></td>
                            <td><a href="<?php echo pt_e($c['go']); ?>"><?php echo pt_e($c['goAr']); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p>الجدول ثابت لا يرتب ولا يقسم صفحات: سبعة صفوف تقرا دفعة واحدة.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5><i class="fa fa-database"></i> احجام السجلات</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="alltables display" id="permSizesTable" data-no-dt="hard">
                    <thead><tr><th>السجل</th><th>الكل</th><th>النشط</th><th>الشاشة</th></tr></thead>
                    <tbody>
                        <tr><td>الادوار</td><td><?php echo (int) $n['roles']; ?></td>
                            <td><?php echo (int) $n['roles_on']; ?></td>
                            <td><a href="perm_roles.php">الادوار</a></td></tr>
                        <tr><td>الوحدات</td><td><?php echo (int) $n['modules']; ?></td><td>-</td>
                            <td><a href="perm_modules.php">الوحدات والشاشات</a></td></tr>
                        <tr><td>صفوف الصلاحيات</td><td><?php echo (int) $n['grants']; ?></td>
                            <td><?php echo (int) $n['grants_view']; ?></td>
                            <td><a href="perm_matrix.php">المصفوفة</a></td></tr>
                        <tr><td>مجموعات السايدبار</td><td><?php echo (int) $n['groups']; ?></td>
                            <td><?php echo (int) $n['groups_on']; ?></td>
                            <td><a href="perm_link_groups.php">المجموعات</a></td></tr>
                        <tr><td>بنود الملاحة</td><td><?php echo (int) $n['navs']; ?></td>
                            <td><?php echo (int) $n['navs_on']; ?></td>
                            <td><a href="perm_nav_items.php">البنود</a></td></tr>
                        <tr><td>شاشات الدليل</td><td><?php echo (int) $n['screens']; ?></td><td>-</td>
                            <td><a href="perm_screen_guide.php">دليل الشاشات</a></td></tr>
                        <tr><td>منح التقارير</td><td><?php echo (int) $n['reports']; ?></td><td>-</td>
                            <td><a href="perm_reports.php">صلاحيات التقارير</a></td></tr>
                    </tbody>
                </table>
            </div>
            <p>كل رقم مقيس من القاعدة عند فتح هذه الشاشة لا محفوظ في عداد.</p>
        </div>
    </div>
</div>
