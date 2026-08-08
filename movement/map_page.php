<?php
/**
 * خريطة الموقع - Map Page
 * عرض خريطة تفاعلية للمعدات والمشغلين مجمّعة حسب المورد
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

// العزل عبر بوابة المستأجر (K9 · هجرة 2026-07-15): كشوف الأعمدة وبُناة النطاق
// أُسقطوا — {TENANT_SCOPE} والبوابة مسؤولا النطاق (قراءة الدوام عبر البوابة
// بسابقة الصيانة)، والسوبر عبر forAllTenants المسجَّل.
$map_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('map page super') : ems_tenant_db();

// تحديد المشروع الحالي من الجلسة
$session_user_project_id = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
$selected_project_id = 0;

if (isset($_GET['project_id']) && intval($_GET['project_id']) > 0) {
    $selected_project_id = intval($_GET['project_id']);
    $_SESSION['operations_project_id'] = $selected_project_id;
} elseif (isset($_SESSION['operations_project_id']) && intval($_SESSION['operations_project_id']) > 0) {
    $selected_project_id = intval($_SESSION['operations_project_id']);
} elseif ($session_user_project_id > 0) {
    $selected_project_id = $session_user_project_id;
    $_SESSION['operations_project_id'] = $selected_project_id;
}

// جلب بيانات المشروع (العزل عبر البوابة)
$selected_project = null;
if ($selected_project_id > 0) {
    try {
        $pq_rows = $map_gate->scopedQuery(array(
            'scope' => array('p' => 'project'),
        ), "SELECT * FROM project p WHERE {TENANT_SCOPE} AND p.id = ? AND p.status = 1 AND p.is_deleted = 0", array($selected_project_id));
    } catch (\Throwable $t) { $pq_rows = array(); }
    if (!empty($pq_rows)) {
        $selected_project = $pq_rows[0];
    }
}

/* UI-13: كان الحكمُ يُقال في نافذةِ alert من المتصفحِ ثم يقفز بالمستخدمِ —
   لا رمزَ ولا أثرَ ولا طريقَ رجوعٍ يختاره. صار رسالةً محكومةً داخلَ اللوحة. */
if (!$selected_project) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا يوجد مشروعٌ مرتبطٌ بهذه الجلسة ❌',
        'GOV-SCOPE-403', 'اختر مشروعًا من شاشةِ اختيار المشروع ثم أعد فتح الخريطة');
}

// ============================================================
// جلب المعدات (الشاحنات) للمشروع — مجمّعة حسب المورد
// ============================================================
try {
    $ops_rows = $map_gate->scopedQuery(array(
        'scope'  => array('o' => 'operations', 'e' => 'equipments'),
        'enrich' => array('s' => 'suppliers'),
    ), "
    SELECT o.id AS op_id, o.status AS op_status,
           o.start, o.end, o.equipment_category,
           e.id AS eq_id, e.code AS eq_code, e.name AS eq_name,
           e.type AS eq_type_id, e.serial_number, e.chassis_number,
           e.manufacturer, e.model, e.manufacturing_year,
           e.equipment_condition, e.availability_status,
           e.engine_condition, e.operating_hours, e.general_notes AS eq_notes,
           COALESCE(et.type, '') AS type_name,
           COALESCE(s.id, 0) AS supplier_id,
           COALESCE(s.name, 'بدون مورد') AS supplier_name
    FROM operations o
    JOIN equipments e ON o.equipment = e.id
    LEFT JOIN equipments_types et ON CAST(e.type AS UNSIGNED) = et.id
    LEFT JOIN suppliers s ON CAST(o.supplier_id AS UNSIGNED) = s.id
    WHERE {TENANT_SCOPE} AND CAST(o.project_id AS UNSIGNED) = ?
      AND o.status = 1
    ORDER BY supplier_name ASC, e.code ASC
", array($selected_project_id));
} catch (\Throwable $t) { $ops_rows = array(); }

// تجميع المعدات حسب المورد
$suppliers_data = [];

{
    foreach ($ops_rows as $op) {
        $sup_id   = intval($op['supplier_id']);
        $sup_name = $op['supplier_name'];
        $avail    = $op['availability_status'] ?? '';
        $is_working = !in_array($avail, ['معطلة', 'مبيعة/مسحوبة', 'خارج الخدمة', 'تحت الصيانة', 'موقوفة']);
        $op['is_working'] = $is_working;
        $op['drivers']    = [];

        if (!isset($suppliers_data[$sup_id])) {
            $suppliers_data[$sup_id] = [
                'supplier_id'   => $sup_id,
                'supplier_name' => $sup_name,
                'equipments'    => [],
            ];
        }
        $suppliers_data[$sup_id]['equipments'][$op['op_id']] = $op;
    }
}

// ============================================================
// جلب المشغلين لكل معدة
// ============================================================
$all_eq_ids = [];
foreach ($suppliers_data as $sup) {
    foreach ($sup['equipments'] as $op) {
        $all_eq_ids[] = intval($op['eq_id']);
    }
}

if (!empty($all_eq_ids)) {
    $eq_ids_uni = array_map('intval', array_unique($all_eq_ids));
    $eq_marks = implode(',', array_fill(0, count($eq_ids_uni), '?'));
    try {
        $drv_rows = $map_gate->scopedQuery(array(
            'scope' => array('ed' => 'equipment_drivers', 'd' => 'employees'),
        ), "
        SELECT ed.equipment_id, ed.start_date, ed.end_date,
               d.id AS employee_id, d.name AS driver_name, d.employee_code,
               d.phone, d.skill_level, d.license_type, d.years_in_field,
               d.years_on_equipment, d.employee_status, d.employment_affiliation,
               d.specialized_equipment
        FROM equipment_drivers ed
        JOIN employees d ON ed.employee_id = d.id
        WHERE {TENANT_SCOPE} AND ed.equipment_id IN ($eq_marks)
          AND ed.status = 1
          AND d.status = 1
        ORDER BY ed.equipment_id ASC, d.name ASC
    ", $eq_ids_uni);
    } catch (\Throwable $t) { $drv_rows = array(); }

    $eq_drivers_map = [];
    foreach ($drv_rows as $dr) {
            $eq_id = intval($dr['equipment_id']);
            if (!isset($eq_drivers_map[$eq_id])) $eq_drivers_map[$eq_id] = [];
            $eq_drivers_map[$eq_id][] = $dr;
    }

    foreach ($suppliers_data as $sup_id => &$sup) {
        foreach ($sup['equipments'] as $op_id => &$op) {
            $eq_id = intval($op['eq_id']);
            $op['drivers'] = $eq_drivers_map[$eq_id] ?? [];
        }
        unset($op);
    }
    unset($sup);
}

// ============================================================
// جلب ساعات التشغيل من التايم شيت
// ============================================================
$all_op_ids_ts = [];
foreach ($suppliers_data as $sup_id => &$sup) {
    foreach ($sup['equipments'] as $op_id => &$op) {
        $op['ts_total'] = 0.0;
        $op['ts_today'] = 0.0;
        $all_op_ids_ts[] = intval($op_id);
    }
    unset($op);
}
unset($sup);

if (!empty($all_op_ids_ts)) {
    $op_ids_uni = array_map('intval', array_unique($all_op_ids_ts));
    $op_marks = implode(',', array_fill(0, count($op_ids_uni), '?'));
    // قراءة الدوام عبر البوابة (سابقة الصيانة — قراءةٌ لا تمسّ مسار الاعتماد)
    try {
        $ts_rows = $map_gate->scopedQuery(array(
            'scope' => array('t' => 'timesheet'),
        ), "
        SELECT CAST(t.operator AS UNSIGNED) AS op_id,
               SUM(t.total_work_hours) AS total_hours,
               SUM(CASE WHEN t.date = CURDATE() THEN t.total_work_hours ELSE 0 END) AS today_hours
        FROM timesheet t
        WHERE {TENANT_SCOPE} AND CAST(t.operator AS UNSIGNED) IN ($op_marks)
          AND t.status = 1
        GROUP BY t.operator
    ", $op_ids_uni);
    } catch (\Throwable $t) { $ts_rows = array(); }
    if (!empty($ts_rows)) {
        $ts_map = [];
        foreach ($ts_rows as $ts_row) {
            $ts_map[intval($ts_row['op_id'])] = [
                'total' => floatval($ts_row['total_hours']),
                'today' => floatval($ts_row['today_hours']),
            ];
        }
        foreach ($suppliers_data as $sup_id => &$sup) {
            foreach ($sup['equipments'] as $op_id => &$op) {
                if (isset($ts_map[$op_id])) {
                    $op['ts_total'] = $ts_map[$op_id]['total'];
                    $op['ts_today'] = $ts_map[$op_id]['today'];
                }
            }
            unset($op);
        }
        unset($sup);
    }
}

// ============================================================
// إحصائيات إجمالية
// ============================================================
$projectId = intval($selected_project_id);

$total_suppliers = count($suppliers_data);
// عدّادا المعدات — العزل عبر البوابة (قائمة operations الداخلية تُعلن enrich بدلالة الأصل)
$totEq = 0; $wrkEq = 0;
try {
    $r = $map_gate->scopedQuery(array(
        'scope'  => array('equipments' => 'equipments'),
        'enrich' => array('operations' => 'operations'),
    ), "SELECT COUNT(*) AS t FROM `equipments` WHERE {TENANT_SCOPE} AND id IN (SELECT operations.equipment FROM operations WHERE operations.project_id = ?)", array($projectId));
    $totEq = !empty($r) ? intval($r[0]['t']) : 0;
} catch (\Throwable $t) {}
try {
    $r = $map_gate->scopedQuery(array(
        'scope'  => array('equipments' => 'equipments'),
        'enrich' => array('operations' => 'operations'),
    ), "SELECT COUNT(*) AS t FROM `equipments` WHERE {TENANT_SCOPE} AND id IN (SELECT operations.equipment FROM operations WHERE operations.project_id = ? AND operations.status='1')", array($projectId));
    $wrkEq = !empty($r) ? intval($r[0]['t']) : 0;
} catch (\Throwable $t) {}
$stoppedEq = $totEq - $wrkEq;

$total_operators = 0;
foreach ($suppliers_data as $sup) {
    foreach ($sup['equipments'] as $op) {
        $total_operators += count($op['drivers']);
    }
}

$page_title = "متابعة المشروع | " . htmlspecialchars($selected_project['name']);
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include('../inheader.php');
// تصميم شاشة الخريطة معزول في ملف خاص (يُحمّل بعد الأنماط العامة، محصّن تحت .movement-map-page)
echo '<link rel="stylesheet" href="/ems/assets/css/map-page.css?v=2026060603">' . "\n";
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main map-page-main movement-map-page ems-unified-page-shell">

  <?php
  // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
  $header_icon       = 'fas fa-map-marked-alt';
  $header_title_html = 'متابعة المشروع
      <small class="map-project-meta">
        <i class="fas fa-project-diagram"></i>
        ' . htmlspecialchars($selected_project['name'])
        . (!empty($selected_project['project_code']) ? ' · ' . htmlspecialchars($selected_project['project_code']) : '')
        . (!empty($selected_project['location']) ? ' · ' . htmlspecialchars($selected_project['location']) : '') . '
      </small>';
  $header_actions = array(
      array('href' => 'move_oprators.php?project_id=' . intval($selected_project_id), 'class' => 'add-btn', 'icon' => 'fas fa-cogs', 'label' => 'إدارة التشغيل'),
      array('href' => 'project_drivers.php?project_id=' . intval($selected_project_id), 'class' => 'add-btn map-project-drivers-btn', 'icon' => 'fas fa-id-badge', 'label' => 'سائقي المشروع'),
      array('raw' => '<span class="movement-topbar-live map-live-badge">
        <span class="live-dot map-live-dot"></span>
        عرض مباشر
      </span>'),
  );
  $header_back = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fa-solid fa-house', 'label' => 'الرئيسية');
  include(__DIR__ . '/../includes/page_header.php');
  ?>

  <div class="movement-content-wrapper">

<!-- ═══ الإحصائيات ═══ -->
<div class="stats-row">
    <div class="stat-tile">
        <div class="stat-tile-icon c-gold"><i class="fas fa-truck-loading"></i></div>
        <div><div class="stat-tile-val"><?php echo $total_suppliers; ?></div><div class="stat-tile-lbl">إجمالي الموردين</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-icon c-blue"><i class="fas fa-truck-monster"></i></div>
        <div><div class="stat-tile-val"><?php echo $totEq; ?></div><div class="stat-tile-lbl">إجمالي الآليات</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-icon c-green"><i class="fas fa-cog fa-spin slow-spin"></i></div>
        <div><div class="stat-tile-val"><?php echo $wrkEq; ?></div><div class="stat-tile-lbl">آليات عاملة</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-icon c-red"><i class="fas fa-tools"></i></div>
        <div><div class="stat-tile-val"><?php echo $stoppedEq; ?></div><div class="stat-tile-lbl">آليات متوقفة</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-icon c-purple"><i class="fas fa-hard-hat"></i></div>
        <div><div class="stat-tile-val"><?php echo $total_operators; ?></div><div class="stat-tile-lbl">إجمالي المشغلين</div></div>
    </div>
</div>

<!-- ═══ مفتاح الألوان ═══ -->
<div class="legend-row">
    <strong class="legend-label">المفتاح:</strong>
    <div class="legend-item"><div class="ldot green"></div> آلية عاملة</div>
    <div class="legend-item"><div class="ldot red"></div> آلية متوقفة</div>
    <div class="legend-item">
        <div class="op-av active legend-op-avatar"><i class="fas fa-user legend-op-avatar-icon"></i></div>
        مشغّل أساسي
    </div>
    <div class="legend-item">
        <div class="op-av reserve legend-op-avatar"><i class="fas fa-user legend-op-avatar-icon"></i></div>
        احتياطي (قريباً)
    </div>
</div>

<?php if (empty($suppliers_data)): ?>
<!-- حالة لا معدات -->
<div class="zero-state">
    <i class="fas fa-truck-monster"></i>
    <h3>لا توجد آليات مسجّلة في هذا المشروع</h3>
    <p>يمكنك إضافة عمليات التشغيل من صفحة إدارة التشغيل</p>
</div>
<?php else: ?>

<!-- ═══ شبكة الموردين ═══ -->
<div class="mines-grid">

<?php
$mine_colors = ['mine-accent-0','mine-accent-1','mine-accent-2','mine-accent-3','mine-accent-4'];
$mi = 0;
foreach ($suppliers_data as $sup_id => $sup):
    $sup_equips  = $sup['equipments'];
    $sup_total   = count($sup_equips);
    $sup_working = 0; $sup_stopped = 0; $sup_ops = 0;
    foreach ($sup_equips as $op) {
        if ($op['is_working']) $sup_working++; else $sup_stopped++;
        $sup_ops += count($op['drivers']);
    }
    $hdr_accent = $mine_colors[$mi % count($mine_colors)];
    $mi++;
?>

<div class="mine-card <?php echo htmlspecialchars($hdr_accent); ?>">
    <!-- رأس المورد -->
    <div class="mine-hdr <?php echo htmlspecialchars($hdr_accent); ?>">
        <div class="mine-hdr-icon"><i class="fas fa-truck-loading"></i></div>
        <div>
            <div class="mine-hdr-name mine-hdr-name-static">
                <?php echo htmlspecialchars($sup['supplier_name']); ?>
            </div>
        </div>
        <div class="mine-hdr-badges">
            <span class="m-badge gold"><i class="fas fa-truck-monster fa-xs"></i> <?php echo $sup_total; ?></span>
            <?php if ($sup_working > 0): ?>
            <span class="m-badge green"><i class="fas fa-check fa-xs"></i> <?php echo $sup_working; ?></span>
            <?php endif; ?>
            <?php if ($sup_stopped > 0): ?>
            <span class="m-badge red"><i class="fas fa-times fa-xs"></i> <?php echo $sup_stopped; ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- شريط إحصائيات المورد -->
    <div class="mine-stats-strip">
        <div class="mss-item"><span class="mss-n"><?php echo $sup_total; ?></span>إجمالي</div>
        <div class="mss-item"><span class="mss-n green"><?php echo $sup_working; ?></span>عاملة</div>
        <div class="mss-item"><span class="mss-n red"><?php echo $sup_stopped; ?></span>متوقفة</div>
        <div class="mss-item"><span class="mss-n blue"><?php echo $sup_ops; ?></span>مشغّلون</div>
    </div>

    <!-- جسم المورد -->
    <div class="mine-body">
        <?php if (empty($sup_equips)): ?>
        <div class="empty-mine-body">
            <i class="fas fa-truck-monster"></i>
            <p>لا توجد آليات لهذا المورد</p>
        </div>
        <?php else:
            $working_list = array_filter($sup_equips, fn($o) => $o['is_working']);
            $stopped_list = array_filter($sup_equips, fn($o) => !$o['is_working']);
        ?>

        <!-- ══ آليات عاملة ══ -->
        <?php if (!empty($working_list)): ?>
        <div class="section-sep working-sep">
            <i class="fas fa-play-circle"></i> آليات عاملة (<?php echo count($working_list); ?>)
        </div>
        <div class="eq-grid">
            <?php foreach ($working_list as $op):
                $drv_count = count($op['drivers']);
                $tt_eq_data = [
                    'الكود'   => $op['eq_code'] ?? '-',
                    'الاسم'   => $op['eq_name'] ?? '-',
                    'النوع'   => $op['type_name'] ?: '-',
                    'الماركة' => trim(($op['manufacturer'] ?? '') . ' ' . ($op['model'] ?? '')) ?: '-',
                    'المورد'  => $op['supplier_name'] ?? '-',
                ];
            ?>
            <div class="eq-card working tt-trigger">
                <div class="eq-status-dot"></div>
                <div class="eq-inner">
                    <div class="eq-top-bar"></div>
                    <div class="eq-content">
                        <div class="eq-machine-icon"><i class="fas fa-truck-monster"></i></div>
                        <div class="eq-headings">
                            <div class="eq-code"><?php echo htmlspecialchars($op['eq_code']); ?></div>
                            <div class="eq-type-lbl"><?php echo htmlspecialchars($op['type_name'] ?: ($op['eq_name'] ?? '')); ?></div>
                        </div>
                    </div>
                    <!-- صف المشغلين -->
                    <div class="op-row">
                        <?php if ($drv_count === 0): ?>
                        <span class="no-op-label"><i class="fas fa-user-slash no-op-icon"></i> لا مشغّل</span>
                        <?php else:
                            foreach ($op['drivers'] as $drv):
                                $dn = htmlspecialchars($drv['driver_name']);
                                $dc = htmlspecialchars($drv['employee_code'] ?? '-');
                                $dp = htmlspecialchars($drv['phone'] ?? '-');
                                $ds = htmlspecialchars($drv['skill_level'] ?? '-');
                                $dy = htmlspecialchars($drv['years_in_field'] ?? '0');
                        ?>
                        <div class="op-av active tt-trigger" title="<?php echo $dn; ?>">
                            <i class="fas fa-user op-avatar-icon"></i>
                            <div class="tt-box tt-box-sm">
                                <div class="tt-title"><i class="fas fa-dharmachakra"></i> <?php echo $dn; ?></div>
                                <div class="tt-row"><span class="tt-k">الكود</span><span class="tt-v"><?php echo $dc; ?></span></div>
                                <div class="tt-row"><span class="tt-k">الهاتف</span><span class="tt-v"><?php echo $dp; ?></span></div>
                                <div class="tt-row"><span class="tt-k">الكفاءة</span><span class="tt-v"><?php echo $ds; ?></span></div>
                                <div class="tt-row"><span class="tt-k">الخبرة</span><span class="tt-v"><?php echo $dy; ?> سنة</span></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <!-- ساعات التشغيل -->
                    <div class="eq-hours-row">
                        <div class="eq-hours-item">
                            <span class="eq-hours-val"><?php echo number_format($op['ts_total'], 1); ?></span>
                            <span class="eq-hours-lbl">إجمالي ساعات</span>
                        </div>
                        <div class="eq-hours-sep"></div>
                        <div class="eq-hours-item">
                            <span class="eq-hours-val today-val"><?php echo number_format($op['ts_today'], 1); ?></span>
                            <span class="eq-hours-lbl">ساعات اليوم</span>
                        </div>
                    </div>
                </div>
                <!-- tooltip بطاقة المعدة -->
                <div class="tt-box">
                    <div class="tt-title"><i class="fas fa-truck-monster"></i> <?php echo htmlspecialchars($op['eq_name']); ?></div>
                    <?php foreach ($tt_eq_data as $k => $v): ?>
                    <div class="tt-row"><span class="tt-k"><?php echo $k; ?></span><span class="tt-v"><?php echo htmlspecialchars($v); ?></span></div>
                    <?php endforeach; ?>
                    <div class="tt-row"><span class="tt-k">المشغلون</span><span class="tt-v"><?php echo $drv_count; ?> أساسي · 0 احتياطي</span></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ══ آليات متوقفة — نفس البنية تماماً مع class="stopped" ══ -->
        <?php if (!empty($stopped_list)): ?>
        <div class="section-sep stopped-sep">
            <i class="fas fa-pause-circle"></i> آليات متوقفة (<?php echo count($stopped_list); ?>)
        </div>
        <div class="eq-grid">
            <?php foreach ($stopped_list as $op):
                $drv_count = count($op['drivers']);
                // نفس بيانات tooltip الآلية العاملة + حالة الإتاحة
                $tt_eq_data = [
                    'الكود'    => $op['eq_code'] ?? '-',
                    'الاسم'    => $op['eq_name'] ?? '-',
                    'النوع'    => $op['type_name'] ?: '-',
                    'الماركة'  => trim(($op['manufacturer'] ?? '') . ' ' . ($op['model'] ?? '')) ?: '-',
                    'الحالة'   => $op['availability_status'] ?? '-',
                    'رقم الهيكل' => $op['chassis_number'] ?? '-',
                    'رقم السيريال' => $op['serial_number'] ?? '-',
                    'المورد'   => $op['supplier_name'] ?? '-',
                ];
            ?>
            <div class="eq-card stopped tt-trigger">
                <div class="eq-status-dot"></div>
                <div class="eq-inner">
                    <div class="eq-top-bar"></div>
                    <div class="eq-content">
                        <div class="eq-machine-icon"><i class="fas fa-truck-monster"></i></div>
                        <div class="eq-headings">
                            <div class="eq-code"><?php echo htmlspecialchars($op['eq_code']); ?></div>
                            <div class="eq-type-lbl"><?php echo htmlspecialchars($op['type_name'] ?: ($op['eq_name'] ?? '')); ?></div>
                        </div>
                    </div>
                    <!-- صف المشغلين — نفس هيكل العاملة تماماً -->
                    <div class="op-row">
                        <?php if ($drv_count === 0): ?>
                        <span class="no-op-label"><i class="fas fa-user-slash no-op-icon"></i> لا مشغّل</span>
                        <?php else:
                            foreach ($op['drivers'] as $drv):
                                $dn = htmlspecialchars($drv['driver_name']);
                                $dc = htmlspecialchars($drv['employee_code'] ?? '-');
                                $dp = htmlspecialchars($drv['phone'] ?? '-');
                                $ds = htmlspecialchars($drv['skill_level'] ?? '-');
                                $dy = htmlspecialchars($drv['years_in_field'] ?? '0');
                                $dl = htmlspecialchars($drv['license_type'] ?? '-');
                                $dye = htmlspecialchars($drv['years_on_equipment'] ?? '0');
                        ?>
                        <div class="op-av active tt-trigger" title="<?php echo $dn; ?>">
                            <i class="fas fa-user op-avatar-icon"></i>
                            <div class="tt-box tt-box-sm">
                                <div class="tt-title"><i class="fas fa-dharmachakra"></i> <?php echo $dn; ?></div>
                                <div class="tt-row"><span class="tt-k">الكود</span><span class="tt-v"><?php echo $dc; ?></span></div>
                                <div class="tt-row"><span class="tt-k">الهاتف</span><span class="tt-v"><?php echo $dp; ?></span></div>
                                <div class="tt-row"><span class="tt-k">الكفاءة</span><span class="tt-v"><?php echo $ds; ?></span></div>
                                <div class="tt-row"><span class="tt-k">الرخصة</span><span class="tt-v"><?php echo $dl; ?></span></div>
                                <div class="tt-row"><span class="tt-k">خبرة المجال</span><span class="tt-v"><?php echo $dy; ?> سنة</span></div>
                                <div class="tt-row"><span class="tt-k">خبرة الآلية</span><span class="tt-v"><?php echo $dye; ?> سنة</span></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <!-- ساعات التشغيل -->
                    <div class="eq-hours-row">
                        <div class="eq-hours-item">
                            <span class="eq-hours-val"><?php echo number_format($op['ts_total'], 1); ?></span>
                            <span class="eq-hours-lbl">إجمالي ساعات</span>
                        </div>
                        <div class="eq-hours-sep"></div>
                        <div class="eq-hours-item">
                            <span class="eq-hours-val today-val"><?php echo number_format($op['ts_today'], 1); ?></span>
                            <span class="eq-hours-lbl">ساعات اليوم</span>
                        </div>
                    </div>
                </div>
                <!-- tooltip بطاقة المعدة المتوقفة -->
                <div class="tt-box">
                    <div class="tt-title"><i class="fas fa-truck-monster"></i> <?php echo htmlspecialchars($op['eq_name']); ?></div>
                    <?php foreach ($tt_eq_data as $k => $v): ?>
                    <div class="tt-row"><span class="tt-k"><?php echo $k; ?></span><span class="tt-v"><?php echo htmlspecialchars($v); ?></span></div>
                    <?php endforeach; ?>
                    <div class="tt-row"><span class="tt-k">المشغلون</span><span class="tt-v"><?php echo $drv_count; ?> أساسي · 0 احتياطي</span></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; /* end empty check */ ?>
    </div><!-- .mine-body -->
</div><!-- .mine-card -->

<?php endforeach; ?>
</div><!-- .mines-grid -->

<?php endif; /* end empty($suppliers_data) */ ?>

  </div><!-- .movement-content-wrapper -->
</div><!-- .main -->

<script>
/**
 * نظام Tooltip الذكي — يعرض tooltip للعنصر الأعمق فقط
 * الأولوية: مشغّل > آلية > مورد · تموضع تلقائي داخل حدود الشاشة · يدعم اللمس · RTL
 */
(function () {
    'use strict';

    var activeBox = null;
    var activeTrigger = null;

    function closeActive() {
        if (activeBox) {
            activeBox.classList.remove('tt-show', 'tt-below');
            activeBox.style.right = '';
            activeBox.style.left = '';
            activeBox.style.transform = '';
            activeBox = null;
            activeTrigger = null;
        }
    }

    function positionBox(trigger, box) {
        box.classList.remove('tt-below');
        box.style.right = '50%';
        box.style.left = 'auto';
        box.style.transform = 'translateX(50%)';

        var trigRect = trigger.getBoundingClientRect();
        var vw = window.innerWidth;
        var boxH = box.offsetHeight || 170;

        if (trigRect.top < boxH + 20) {
            box.classList.add('tt-below');
        }

        requestAnimationFrame(function () {
            var bRect = box.getBoundingClientRect();
            if (bRect.right > vw - 12) {
                box.style.right = '0';
                box.style.left = 'auto';
                box.style.transform = 'none';
            } else if (bRect.left < 12) {
                box.style.right = 'auto';
                box.style.left = '0';
                box.style.transform = 'none';
            }
        });
    }

    function findDeepestTrigger(start) {
        var el = start;
        while (el && el !== document.body) {
            if (el.classList && el.classList.contains('tt-trigger')) return el;
            el = el.parentElement;
        }
        return null;
    }

    function directChildBox(trigger) {
        var c = trigger.children;
        for (var i = 0; i < c.length; i++) {
            if (c[i].classList && c[i].classList.contains('tt-box')) return c[i];
        }
        return null;
    }

    function show(trigger) {
        var box = directChildBox(trigger);
        if (!box) { closeActive(); return; }
        if (box === activeBox) return;
        closeActive();
        positionBox(trigger, box);
        box.classList.add('tt-show');
        activeBox = box;
        activeTrigger = trigger;
    }

    document.addEventListener('mouseover', function (e) {
        var trigger = findDeepestTrigger(e.target);
        if (!trigger) { closeActive(); return; }
        show(trigger);
    });

    document.addEventListener('mouseleave', closeActive);
    document.addEventListener('scroll', closeActive, true);
    window.addEventListener('resize', closeActive);

    document.addEventListener('touchstart', function (e) {
        var trigger = findDeepestTrigger(e.target);
        if (!trigger) { closeActive(); return; }
        if (trigger === activeTrigger) { closeActive(); return; }
        show(trigger);
    }, { passive: true });

    document.addEventListener('click', function (e) {
        if (!findDeepestTrigger(e.target)) closeActive();
    });
}());
</script>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/ems/assets/js/sidebar.js"></script>
</body>
</html>
