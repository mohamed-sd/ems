<?php
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
$project_has_company_id = db_table_has_column($conn, 'project', 'company_id');
$project_client_column = db_table_has_column($conn, 'project', 'client_id') ? 'client_id' : 'company_client_id';

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة");
    exit();
}

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

$project_scope_sql = "1=1";
if (!$is_super_admin) {
    if ($project_has_company_id) {
        $project_scope_sql = "p.company_id = $company_id";
    } else {
        $project_scope_sql = "1=1";
    }
}

// جلب بيانات المشروع
$selected_project = null;
if ($selected_project_id > 0) {
    $pq = mysqli_query($conn, "SELECT * FROM project p WHERE p.id = $selected_project_id AND p.status = 1 AND p.is_deleted = 0 AND $project_scope_sql");
    if ($pq && mysqli_num_rows($pq) > 0) {
        $selected_project = mysqli_fetch_assoc($pq);
    }
}

if (!$selected_project) {
    echo "<script>alert('❌ لا يوجد مشروع مرتبط بهذه الجلسة'); window.location.href='../main/dashboard.php';</script>";
    exit();
}

// ============================================================
// جلب المناجم الخاصة بالمشروع
// ============================================================
$mines_data = [];
$mines_has_company = db_table_has_column($conn, 'mines', 'company_id');
$company_mine_clause = ($mines_has_company && !$is_super_admin) ? " AND m.company_id = $company_id" : "";

$mines_q = mysqli_query($conn, "
    SELECT m.id, m.mine_name, m.mine_code, m.manager_name, m.mineral_type,
           m.mine_type, m.ownership_type, m.mine_area, m.mine_area_unit,
           m.mining_depth, m.contract_nature, m.status, m.notes
    FROM mines m
    WHERE m.project_id = $selected_project_id AND m.status = 1 AND m.is_deleted = 0
    $company_mine_clause
    ORDER BY m.id ASC
");

if ($mines_q) {
    while ($mine = mysqli_fetch_assoc($mines_q)) {
        $mines_data[$mine['id']] = $mine;
        $mines_data[$mine['id']]['equipments'] = [];
    }
}

// ============================================================
// جلب المعدات (الشاحنات) لكل منجم في المشروع
// ============================================================
$operations_has_company = db_table_has_column($conn, 'operations', 'company_id');
$ops_company_clause = ($operations_has_company && !$is_super_admin) ? " AND o.company_id = $company_id" : "";

$ops_q = mysqli_query($conn, "
    SELECT o.id AS op_id, o.mine_id, o.status AS op_status,
           o.start, o.end, o.equipment_category,
           e.id AS eq_id, e.code AS eq_code, e.name AS eq_name,
           e.type AS eq_type_id, e.serial_number, e.chassis_number,
           e.manufacturer, e.model, e.manufacturing_year,
           e.equipment_condition, e.availability_status,
           e.engine_condition, e.operating_hours, e.general_notes AS eq_notes,
           COALESCE(et.type, '') AS type_name,
           s.name AS supplier_name
    FROM operations o
    JOIN equipments e ON o.equipment = e.id
    LEFT JOIN equipments_types et ON CAST(e.type AS UNSIGNED) = et.id
    LEFT JOIN suppliers s ON CAST(o.supplier_id AS UNSIGNED) = s.id
    WHERE CAST(o.project_id AS UNSIGNED) = $selected_project_id
      AND o.status = 1
      $ops_company_clause
    ORDER BY o.mine_id ASC, e.code ASC
");

// معدات بدون منجم محدد
$no_mine_equipments = [];

if ($ops_q) {
    while ($op = mysqli_fetch_assoc($ops_q)) {
        $mine_id = intval($op['mine_id']);
        // تحديد حالة الشاحنة: عاملة / متوقفة
        $avail = $op['availability_status'] ?? '';
        $is_working = !in_array($avail, ['معطلة', 'مبيعة/مسحوبة', 'خارج الخدمة', 'تحت الصيانة', 'موقوفة']);

        $op['is_working'] = $is_working;
        $op['drivers'] = [];

        if ($mine_id > 0 && isset($mines_data[$mine_id])) {
            $mines_data[$mine_id]['equipments'][$op['op_id']] = $op;
        } else {
            $no_mine_equipments[$op['op_id']] = $op;
        }
    }
}

// ============================================================
// جلب المشغلين لكل معدة
// ============================================================
$eq_drivers_has_company = db_table_has_column($conn, 'equipment_drivers', 'company_id');
$drivers_company_clause = ($eq_drivers_has_company && !$is_super_admin) ? " AND ed.company_id = $company_id" : "";

// جمع كل معرفات المعدات
$all_eq_ids = [];
foreach ($mines_data as $mine) {
    foreach ($mine['equipments'] as $op) {
        $all_eq_ids[] = intval($op['eq_id']);
    }
}
foreach ($no_mine_equipments as $op) {
    $all_eq_ids[] = intval($op['eq_id']);
}

if (!empty($all_eq_ids)) {
    $eq_ids_str = implode(',', array_unique($all_eq_ids));
    $drv_q = mysqli_query($conn, "
        SELECT ed.equipment_id, ed.start_date, ed.end_date,
               d.id AS driver_id, d.name AS driver_name, d.driver_code,
               d.phone, d.skill_level, d.license_type, d.years_in_field,
               d.years_on_equipment, d.driver_status, d.employment_affiliation,
               d.specialized_equipment
        FROM equipment_drivers ed
        JOIN drivers d ON ed.driver_id = d.id
        WHERE ed.equipment_id IN ($eq_ids_str)
          AND ed.status = 1
          AND d.status = 1
          $drivers_company_clause
        ORDER BY ed.equipment_id ASC, d.name ASC
    ");

    // بناء خريطة المعدة -> قائمة المشغلين
    $eq_drivers_map = [];
    if ($drv_q) {
        while ($dr = mysqli_fetch_assoc($drv_q)) {
            $eq_id = intval($dr['equipment_id']);
            if (!isset($eq_drivers_map[$eq_id])) $eq_drivers_map[$eq_id] = [];
            $eq_drivers_map[$eq_id][] = $dr;
        }
    }

    // إسناد المشغلين للمعدات في المناجم
    foreach ($mines_data as $mine_id => &$mine) {
        foreach ($mine['equipments'] as $op_id => &$op) {
            $eq_id = intval($op['eq_id']);
            $op['drivers'] = $eq_drivers_map[$eq_id] ?? [];
        }
        unset($op);
    }
    unset($mine);

    foreach ($no_mine_equipments as $op_id => &$op) {
        $eq_id = intval($op['eq_id']);
        $op['drivers'] = $eq_drivers_map[$eq_id] ?? [];
    }
    unset($op);
}

// ============================================================
// جلب ساعات التشغيل من التايم شيت
// ============================================================
$ts_has_company = db_table_has_column($conn, 'timesheet', 'company_id');
$ts_company_clause = ($ts_has_company && !$is_super_admin) ? " AND t.company_id = $company_id" : "";

// تهيئة الساعات بالصفر وتجميع معرّفات التشغيل
$all_op_ids_ts = [];
foreach ($mines_data as $mine_id => &$mine) {
    foreach ($mine['equipments'] as $op_id => &$op) {
        $op['ts_total'] = 0.0;
        $op['ts_today'] = 0.0;
        $all_op_ids_ts[] = intval($op_id);
    }
    unset($op);
}
unset($mine);
foreach ($no_mine_equipments as $op_id => &$op) {
    $op['ts_total'] = 0.0;
    $op['ts_today'] = 0.0;
    $all_op_ids_ts[] = intval($op_id);
}
unset($op);

if (!empty($all_op_ids_ts)) {
    $op_ids_ts_str = implode(',', array_unique($all_op_ids_ts));
    $ts_q = mysqli_query($conn, "
        SELECT CAST(t.operator AS UNSIGNED) AS op_id,
               SUM(t.total_work_hours) AS total_hours,
               SUM(CASE WHEN t.date = CURDATE() THEN t.total_work_hours ELSE 0 END) AS today_hours
        FROM timesheet t
        WHERE CAST(t.operator AS UNSIGNED) IN ($op_ids_ts_str)
          AND t.status = 1
          $ts_company_clause
        GROUP BY t.operator
    ");
    if ($ts_q) {
        $ts_map = [];
        while ($ts_row = mysqli_fetch_assoc($ts_q)) {
            $ts_map[intval($ts_row['op_id'])] = [
                'total' => floatval($ts_row['total_hours']),
                'today' => floatval($ts_row['today_hours']),
            ];
        }
        foreach ($mines_data as $mine_id => &$mine) {
            foreach ($mine['equipments'] as $op_id => &$op) {
                if (isset($ts_map[$op_id])) {
                    $op['ts_total'] = $ts_map[$op_id]['total'];
                    $op['ts_today'] = $ts_map[$op_id]['today'];
                }
            }
            unset($op);
        }
        unset($mine);
        foreach ($no_mine_equipments as $op_id => &$op) {
            if (isset($ts_map[$op_id])) {
                $op['ts_total'] = $ts_map[$op_id]['total'];
                $op['ts_today'] = $ts_map[$op_id]['today'];
            }
        }
        unset($op);
    }
}

// ============================================================
// إحصائيات إجمالية
// ============================================================
$total_mines = count($mines_data);
$total_equip = 0;
$total_working = 0;
$total_stopped = 0;
$total_operators = 0;

foreach ($mines_data as $mine) {
    foreach ($mine['equipments'] as $op) {
        $total_equip++;
        if ($op['is_working']) $total_working++; else $total_stopped++;
        $total_operators += count($op['drivers']);
    }
}

$page_title = "خريطة الموقع | " . htmlspecialchars($selected_project['name']);
?>
<?php include '../inheader.php'; ?>
<?php include '../insidebar.php'; ?>

<div class="main-content" id="main-content">
<div class="map-page movement-page movement-map-page">

<div class="movement-topbar">
    <div class="movement-topbar-left">
        <a href="move_oprators.php?project_id=<?php echo intval($selected_project_id); ?>" class="movement-topbar-btn movement-topbar-btn-primary add-btn"><i class="fas fa-cogs"></i> إدارة التشغيل</a>
        <a href="project_drivers.php?project_id=<?php echo intval($selected_project_id); ?>" class="movement-topbar-btn"><i class="fas fa-id-badge"></i> سائقي المشروع</a>
        <span class="movement-topbar-btn movement-topbar-live"><span class="live-dot"></span> عرض مباشر</span>
    </div>
    <div class="movement-topbar-right">
        <div class="movement-topbar-title">
            <span class="movement-topbar-icon"><i class="fas fa-map-marked-alt"></i></span>
            <div class="movement-topbar-title-text">
                <h1>خريطة الموقع</h1>
                <p>
                    <i class="fas fa-project-diagram"></i>
                    <?php echo htmlspecialchars($selected_project['name']); ?>
                    <?php if (!empty($selected_project['project_code'])): ?> · <?php echo htmlspecialchars($selected_project['project_code']); ?><?php endif; ?>
                    <?php if (!empty($selected_project['location'])): ?> · <?php echo htmlspecialchars($selected_project['location']); ?><?php endif; ?>
                </p>
            </div>
        </div>
        <a href="../main/dashboard.php" class="movement-topbar-btn movement-topbar-btn-back back-btn"><i class="fas fa-arrow-right"></i> رجوع</a>
    </div>
</div>

<!-- ═══ الإحصائيات ═══ -->
<div class="stats-row">
    <div class="stat-tile">
        <div class="stat-tile-icon c-gold"><i class="fas fa-mountain"></i></div>
        <div><div class="stat-tile-val"><?php echo $total_mines; ?></div><div class="stat-tile-lbl">إجمالي المناجم</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-icon c-blue"><i class="fas fa-truck-monster"></i></div>
        <div><div class="stat-tile-val"><?php echo $total_equip; ?></div><div class="stat-tile-lbl">إجمالي الآليات</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-icon c-green"><i class="fas fa-cog fa-spin slow-spin"></i></div>
        <div><div class="stat-tile-val"><?php echo $total_working; ?></div><div class="stat-tile-lbl">آليات عاملة</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-icon c-red"><i class="fas fa-tools"></i></div>
        <div><div class="stat-tile-val"><?php echo $total_stopped; ?></div><div class="stat-tile-lbl">آليات متوقفة</div></div>
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
    <div class="legend-item legend-hint">
        <i class="fas fa-hand-pointer fa-xs"></i> مرّر على أي عنصر لعرض تفاصيله
    </div>
</div>

<?php if (empty($mines_data)): ?>
<!-- حالة لا مناجم -->
<div class="zero-state">
    <i class="fas fa-mountain"></i>
    <h3>لا توجد مناجم مسجّلة في هذا المشروع</h3>
    <p>يمكنك إضافة المناجم من صفحة إدارة المشاريع</p>
</div>
<?php else: ?>

<!-- ═══ شبكة المناجم ═══ -->
<div class="mines-grid">

<?php
$mine_colors = ['mine-accent-0','mine-accent-1','mine-accent-2','mine-accent-3','mine-accent-4'];
$mi = 0;
foreach ($mines_data as $mine_id => $mine):
    $mine_equips  = $mine['equipments'];
    $mine_total   = count($mine_equips);
    $mine_working = 0; $mine_stopped = 0; $mine_ops = 0;
    foreach ($mine_equips as $op) {
        if ($op['is_working']) $mine_working++; else $mine_stopped++;
        $mine_ops += count($op['drivers']);
    }
    $hdr_accent = $mine_colors[$mi % count($mine_colors)];
    $mi++;

    // tooltip المنجم
    $tt_mine_data = [
        'الكود'       => $mine['mine_code'] ?? '-',
        'المدير'      => $mine['manager_name'] ?? '-',
        'النوع'       => $mine['mine_type'] ?? '-',
        'المعدن'      => $mine['mineral_type'] ?? '-',
        'المساحة'     => $mine['mine_area'] ? ($mine['mine_area'] . ' ' . ($mine['mine_area_unit'] ?? '')) : '-',
    ];
?>

<div class="mine-card">
    <!-- رأس المنجم -->
    <div class="mine-hdr <?php echo htmlspecialchars($hdr_accent); ?>">
        <div class="mine-hdr-icon"><i class="fas fa-mountain"></i></div>
        <div>
            <!-- الاسم قابل للـ tooltip -->
            <div class="mine-hdr-name tt-trigger mine-hdr-name-static">
                <?php echo htmlspecialchars($mine['mine_name']); ?>
                <div class="tt-box">
                    <div class="tt-title"><i class="fas fa-mountain"></i> <?php echo htmlspecialchars($mine['mine_name']); ?></div>
                    <?php foreach ($tt_mine_data as $k => $v): ?>
                    <div class="tt-row">
                        <span class="tt-k"><?php echo $k; ?></span>
                        <span class="tt-v"><?php echo htmlspecialchars($v); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mine-hdr-code"><?php echo htmlspecialchars($mine['mine_code'] ?? ''); ?></div>
        </div>
        <div class="mine-hdr-badges">
            <span class="m-badge gold"><i class="fas fa-truck-monster fa-xs"></i> <?php echo $mine_total; ?></span>
            <?php if ($mine_working > 0): ?>
            <span class="m-badge green"><i class="fas fa-check fa-xs"></i> <?php echo $mine_working; ?></span>
            <?php endif; ?>
            <?php if ($mine_stopped > 0): ?>
            <span class="m-badge red"><i class="fas fa-times fa-xs"></i> <?php echo $mine_stopped; ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- شريط إحصائيات المنجم -->
    <div class="mine-stats-strip">
        <div class="mss-item"><span class="mss-n"><?php echo $mine_total; ?></span>إجمالي</div>
        <div class="mss-item"><span class="mss-n green"><?php echo $mine_working; ?></span>عاملة</div>
        <div class="mss-item"><span class="mss-n red"><?php echo $mine_stopped; ?></span>متوقفة</div>
        <div class="mss-item"><span class="mss-n blue"><?php echo $mine_ops; ?></span>مشغّلون</div>
        <?php if (!empty($mine['mineral_type'])): ?>
        <div class="mss-item"><span class="mss-n gold mss-mineral-icon">⛏</span><?php echo htmlspecialchars($mine['mineral_type']); ?></div>
        <?php endif; ?>
    </div>

    <!-- جسم المنجم -->
    <div class="mine-body">
        <?php if (empty($mine_equips)): ?>
        <div class="empty-mine-body">
            <i class="fas fa-truck-monster"></i>
            <p>لا توجد آليات مسندة لهذا المنجم</p>
        </div>
        <?php else:
            $working_list = array_filter($mine_equips, fn($o) => $o['is_working']);
            $stopped_list = array_filter($mine_equips, fn($o) => !$o['is_working']);
        ?>

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
                        <div class="eq-code"><?php echo htmlspecialchars($op['eq_code']); ?></div>
                        <div class="eq-type-lbl"><?php echo htmlspecialchars($op['type_name'] ?: ($op['eq_name'] ?? '')); ?></div>
                    </div>
                    <!-- صف المشغلين -->
                    <div class="op-row">
                        <?php if ($drv_count === 0): ?>
                        <span class="no-op-label"><i class="fas fa-user-slash no-op-icon"></i> لا مشغّل</span>
                        <?php else:
                            foreach ($op['drivers'] as $drv):
                                $dn = htmlspecialchars($drv['driver_name']);
                                $dc = htmlspecialchars($drv['driver_code'] ?? '-');
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

        <?php if (!empty($stopped_list)): ?>
        <div class="section-sep stopped-sep">
            <i class="fas fa-pause-circle"></i> آليات متوقفة (<?php echo count($stopped_list); ?>)
        </div>
        <div class="eq-grid">
            <?php foreach ($stopped_list as $op):
                $drv_count = count($op['drivers']);
                $tt_eq_data = [
                    'الكود'   => $op['eq_code'] ?? '-',
                    'الاسم'   => $op['eq_name'] ?? '-',
                    'النوع'   => $op['type_name'] ?: '-',
                    'الحالة'  => $op['availability_status'] ?? '-',
                    'المورد'  => $op['supplier_name'] ?? '-',
                ];
            ?>
            <div class="eq-card stopped tt-trigger">
                <div class="eq-status-dot"></div>
                <div class="eq-inner">
                    <div class="eq-top-bar"></div>
                    <div class="eq-content">
                        <div class="eq-machine-icon"><i class="fas fa-truck-monster"></i></div>
                        <div class="eq-code"><?php echo htmlspecialchars($op['eq_code']); ?></div>
                        <div class="eq-type-lbl"><?php echo htmlspecialchars($op['type_name'] ?: ($op['eq_name'] ?? '')); ?></div>
                    </div>
                    <div class="op-row">
                        <?php if ($drv_count === 0): ?>
                        <span class="no-op-label"><i class="fas fa-user-slash no-op-icon"></i> لا مشغّل</span>
                        <?php else:
                            foreach ($op['drivers'] as $drv):
                                $dn = htmlspecialchars($drv['driver_name']);
                                $dc = htmlspecialchars($drv['driver_code'] ?? '-');
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

<?php endif; /* end empty($mines_data) */ ?>

<!-- ═══ معدات بلا منجم ═══ -->
<?php if (!empty($no_mine_equipments)): ?>
<div class="orphan-section">
    <div class="orphan-title">
        <i class="fas fa-exclamation-triangle orphan-title-icon"></i>
        آليات غير مرتبطة بمنجم محدد (<?php echo count($no_mine_equipments); ?>)
    </div>
    <div class="eq-grid">
        <?php foreach ($no_mine_equipments as $op):
            $css_cls   = $op['is_working'] ? 'working' : 'stopped';
            $drv_count = count($op['drivers']);
            $tt_eq_data = [
                'الكود'   => $op['eq_code'] ?? '-',
                'النوع'   => $op['type_name'] ?: '-',
                'الحالة'  => $op['availability_status'] ?? '-',
                'المورد'  => $op['supplier_name'] ?? '-',
            ];
        ?>
        <div class="eq-card <?php echo $css_cls; ?> tt-trigger">
            <div class="eq-status-dot"></div>
            <div class="eq-inner">
                <div class="eq-top-bar"></div>
                <div class="eq-content">
                    <div class="eq-machine-icon"><i class="fas fa-truck-monster"></i></div>
                    <div class="eq-code"><?php echo htmlspecialchars($op['eq_code']); ?></div>
                    <div class="eq-type-lbl"><?php echo htmlspecialchars($op['type_name'] ?: ($op['eq_name'] ?? '')); ?></div>
                </div>
                <div class="op-row">
                    <?php if ($drv_count === 0): ?>
                    <span class="no-op-label"><i class="fas fa-user-slash no-op-icon"></i> لا مشغّل</span>
                    <?php else:
                        foreach ($op['drivers'] as $drv):
                            $dn = htmlspecialchars($drv['driver_name']);
                    ?>
                    <div class="op-av active tt-trigger" title="<?php echo $dn; ?>">
                        <i class="fas fa-user op-avatar-icon"></i>
                        <div class="tt-box tt-box-sm">
                            <div class="tt-title"><i class="fas fa-dharmachakra"></i> <?php echo $dn; ?></div>
                            <div class="tt-row"><span class="tt-k">الكود</span><span class="tt-v"><?php echo htmlspecialchars($drv['driver_code'] ?? '-'); ?></span></div>
                            <div class="tt-row"><span class="tt-k">الهاتف</span><span class="tt-v"><?php echo htmlspecialchars($drv['phone'] ?? '-'); ?></span></div>
                            <div class="tt-row"><span class="tt-k">الكفاءة</span><span class="tt-v"><?php echo htmlspecialchars($drv['skill_level'] ?? '-'); ?></span></div>
                            <div class="tt-row"><span class="tt-k">الخبرة</span><span class="tt-v"><?php echo htmlspecialchars($drv['years_in_field'] ?? '0'); ?> سنة</span></div>
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
</div>
<?php endif; ?>

</div><!-- .map-page -->
</div><!-- .main-content -->

<script>
/**
 * نظام Tooltip الذكي — يعرض tooltip للعنصر الأعمق فقط
 * الأولوية: مشغّل > آلية > منجم
 */
(function () {
    'use strict';

    var activeBox = null;

    /* إخفاء الـ tooltip الحالي */
    function closeActive() {
        if (activeBox) {
            activeBox.classList.remove('tt-show');
            activeBox.classList.remove('tt-below');
            activeBox = null;
        }
    }

    /* ضبط موضع الـ tooltip حتى لا يخرج عن الشاشة */
    function positionBox(trigger, box) {
        /* reset */
        box.style.right     = '';
        box.style.left      = '';
        box.style.transform = '';
        box.classList.remove('tt-below');

        var trigRect = trigger.getBoundingClientRect();
        var vw = window.innerWidth;
        var vh = window.innerHeight;

        /* هل يوجد مساحة فوق؟ */
        var boxH = 170; /* تقدير */
        if (trigRect.top < boxH + 18) {
            box.classList.add('tt-below');
        }

        /* ضبط أفقي: ابدأ بالتمركز */
        box.style.right     = '50%';
        box.style.transform = 'translateX(50%)';

        /* بعد render نتحقق من الحدود الفعلية */
        requestAnimationFrame(function () {
            var bRect = box.getBoundingClientRect();
            if (bRect.right > vw - 12) {
                box.style.right     = '0';
                box.style.left      = 'auto';
                box.style.transform = 'none';
            } else if (bRect.left < 12) {
                box.style.right     = 'auto';
                box.style.left      = '0';
                box.style.transform = 'none';
            }
        });
    }

    /* معالج mouseover الرئيسي — يُعالج على مستوى document */
    document.addEventListener('mouseover', function (e) {
        /* ابحث عن أعمق .tt-trigger يحتوي العنصر المحوّم */
        var target = e.target;
        var deepest = null;
        var el = target;

        while (el && el !== document.body) {
            if (el.classList && el.classList.contains('tt-trigger')) {
                deepest = el;
                break;   /* أعمق عنصر هو الأول في الصعود */
            }
            el = el.parentElement;
        }

        if (!deepest) {
            closeActive();
            return;
        }

        /* الـ tt-box المباشر للعنصر الأعمق فقط */
        var box = null;
        var children = deepest.childNodes;
        for (var i = 0; i < children.length; i++) {
            var c = children[i];
            if (c.nodeType === 1 && c.classList.contains('tt-box')) {
                box = c;
                break;
            }
        }

        if (!box) { closeActive(); return; }

        /* إن كان نفس الـ tooltip المفتوح بالفعل، لا حاجة لتغيير */
        if (box === activeBox) return;

        /* إخفاء القديم وعرض الجديد */
        closeActive();
        positionBox(deepest, box);
        box.classList.add('tt-show');
        activeBox = box;
    });

    /* إخفاء عند مغادرة الصفحة */
    document.addEventListener('mouseleave', closeActive);

    /* إخفاء عند النقر */
    document.addEventListener('click', closeActive);

    /* إخفاء عند التمرير (scroll) لتجنب tooltip عالق */
    document.addEventListener('scroll', closeActive, true);

}());
</script>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/ems/assets/js/sidebar.js"></script>
</body>
</html>

