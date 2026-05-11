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
include('../inheader.php');
include('../insidebar.php');
?>

<div class="main map-page-main ems-unified-page-shell">

  <div class="main_head">

    <div class="head_actions">
      <a href="move_oprators.php?project_id=<?php echo intval($selected_project_id); ?>" class="add-btn">
        <i class="fas fa-cogs"></i> إدارة التشغيل
      </a>
      <a href="project_drivers.php?project_id=<?php echo intval($selected_project_id); ?>" class="add-btn" style="background: #6c757d;">
        <i class="fas fa-id-badge"></i> سائقي المشروع
      </a>
      <span class="movement-topbar-live" style="display: inline-block; padding: 0.5rem 1rem; background: #28a745; color: white; border-radius: 0.375rem; font-size: 0.9rem;">
        <span class="live-dot" style="display: inline-block; width: 8px; height: 8px; background: #fff; border-radius: 50%; margin-left: 5px; animation: pulse 2s infinite;"></span>
        عرض مباشر
      </span>
    </div>

    <h1 class="head-title">
      <div class="title-icon"><i class="fas fa-map-marked-alt"></i></div>
      خريطة الموقع
      <small style="display: block; font-size: 0.8rem; color: #6c757d; margin-top: 0.25rem;">
        <i class="fas fa-project-diagram"></i>
        <?php echo htmlspecialchars($selected_project['name']); ?>
        <?php if (!empty($selected_project['project_code'])): ?> · <?php echo htmlspecialchars($selected_project['project_code']); ?><?php endif; ?>
        <?php if (!empty($selected_project['location'])): ?> · <?php echo htmlspecialchars($selected_project['location']); ?><?php endif; ?>
      </small>
    </h1>

    <div class="head_back">
      <a href="../main/dashboard.php" class="back-btn">
        <i class="fa-solid fa-house"></i> الرئيسية
      </a>
    </div>
  </div>

  <div class="movement-content-wrapper">

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

  </div><!-- .movement-content-wrapper -->
</div><!-- .main -->

<style>
/* ═══════════════════════════════════════════════════════════
   خريطة الموقع - التصميم الموحد
═══════════════════════════════════════════════════════════ */

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}

@keyframes slow-spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.movement-content-wrapper {
  padding: 1.5rem;
  background: #f8f9fa;
  min-height: calc(100vh - 120px);
}

/* ═══ الإحصائيات ═══ */
.stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.stat-tile {
  background: #fff;
  border: 1px solid #dee2e6;
  border-radius: 12px;
  padding: 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  transition: all 0.3s ease;
}

.stat-tile:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}

.stat-tile-icon {
  width: 50px;
  height: 50px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  flex-shrink: 0;
}

.stat-tile-icon.c-gold {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: #fff;
}

.stat-tile-icon.c-blue {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: #fff;
}

.stat-tile-icon.c-green {
  background: linear-gradient(135deg, #10b981, #059669);
  color: #fff;
}

.stat-tile-icon.c-red {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: #fff;
}

.stat-tile-icon.c-purple {
  background: linear-gradient(135deg, #8b5cf6, #7c3aed);
  color: #fff;
}

.stat-tile-val {
  font-size: 1.75rem;
  font-weight: 900;
  color: #1f2937;
  line-height: 1;
}

.stat-tile-lbl {
  font-size: 0.875rem;
  color: #6b7280;
  margin-top: 0.25rem;
}

.slow-spin {
  animation: slow-spin 3s linear infinite;
}

/* ═══ مفتاح الألوان ═══ */
.legend-row {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  flex-wrap: wrap;
  background: #fff;
  padding: 1rem 1.5rem;
  border-radius: 12px;
  border: 1px solid #dee2e6;
  margin-bottom: 2rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.legend-label {
  font-weight: 700;
  color: #374151;
  font-size: 0.9rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  color: #4b5563;
}

.ldot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
}

.ldot.green {
  background: #10b981;
  box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
}

.ldot.red {
  background: #ef4444;
  box-shadow: 0 0 8px rgba(239, 68, 68, 0.5);
}

.legend-op-avatar {
  margin-left: 0;
}

.legend-hint {
  margin-right: auto;
  color: #9ca3af;
  font-style: italic;
}

/* ═══ حالة فارغة ═══ */
.zero-state {
  background: #fff;
  border: 2px dashed #d1d5db;
  border-radius: 16px;
  padding: 4rem 2rem;
  text-align: center;
  color: #6b7280;
}

.zero-state i {
  font-size: 4rem;
  color: #d1d5db;
  margin-bottom: 1rem;
}

.zero-state h3 {
  font-size: 1.25rem;
  font-weight: 700;
  color: #374151;
  margin-bottom: 0.5rem;
}

.zero-state p {
  color: #9ca3af;
  font-size: 0.95rem;
}

/* ═══ شبكة المناجم ═══ */
.mines-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

/* ═══ بطاقة المنجم ═══ */
.mine-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  transition: all 0.3s ease;
}

.mine-card:hover {
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
}

/* ═══ رأس المنجم ═══ */
.mine-hdr {
  padding: 1rem 1.25rem;
  background: linear-gradient(135deg, #1f2937, #111827);
  color: #fff;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  position: relative;
  overflow: hidden;
}

.mine-hdr::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
  pointer-events: none;
}

.mine-hdr-icon {
  width: 40px;
  height: 40px;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  flex-shrink: 0;
  position: relative;
  z-index: 1;
}

.mine-hdr-name {
  font-size: 1.1rem;
  font-weight: 900;
  line-height: 1.3;
  position: relative;
  z-index: 1;
}

.mine-hdr-name-static {
  cursor: pointer;
}

.mine-hdr-code {
  font-size: 0.75rem;
  opacity: 0.8;
  margin-top: 0.25rem;
  position: relative;
  z-index: 1;
}

.mine-hdr-badges {
  margin-right: auto;
  display: flex;
  gap: 0.5rem;
  position: relative;
  z-index: 1;
}

.m-badge {
  padding: 0.25rem 0.625rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.m-badge.gold {
  background: rgba(245, 158, 11, 0.2);
  color: #fbbf24;
}

.m-badge.green {
  background: rgba(16, 185, 129, 0.2);
  color: #34d399;
}

.m-badge.red {
  background: rgba(239, 68, 68, 0.2);
  color: #f87171;
}

/* ألوان مختلفة للمناجم */
.mine-hdr.mine-accent-0 {
  background: linear-gradient(135deg, #1e3a8a, #1e40af);
}

.mine-hdr.mine-accent-1 {
  background: linear-gradient(135deg, #0f766e, #0d9488);
}

.mine-hdr.mine-accent-2 {
  background: linear-gradient(135deg, #7c2d12, #9a3412);
}

.mine-hdr.mine-accent-3 {
  background: linear-gradient(135deg, #701a75, #86198f);
}

.mine-hdr.mine-accent-4 {
  background: linear-gradient(135deg, #365314, #3f6212);
}

/* ═══ شريط إحصائيات المنجم ═══ */
.mine-stats-strip {
  display: flex;
  align-items: center;
  justify-content: space-around;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
  padding: 0.75rem 0.5rem;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.mss-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  font-size: 0.75rem;
  color: #6b7280;
  min-width: 60px;
}

.mss-n {
  font-size: 1.1rem;
  font-weight: 900;
  color: #374151;
  margin-bottom: 0.125rem;
}

.mss-n.green {
  color: #10b981;
}

.mss-n.red {
  color: #ef4444;
}

.mss-n.blue {
  color: #3b82f6;
}

.mss-n.gold {
  color: #f59e0b;
}

.mss-mineral-icon {
  font-size: 1rem;
}

/* ═══ جسم المنجم ═══ */
.mine-body {
  padding: 1rem;
}

.empty-mine-body {
  padding: 3rem 1rem;
  text-align: center;
  color: #9ca3af;
}

.empty-mine-body i {
  font-size: 3rem;
  color: #d1d5db;
  margin-bottom: 1rem;
}

.empty-mine-body p {
  font-size: 0.9rem;
  color: #6b7280;
}

/* ═══ فواصل الأقسام ═══ */
.section-sep {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 0.75rem;
  margin: 1rem 0 0.75rem;
  font-size: 0.85rem;
  font-weight: 700;
  border-radius: 8px;
}

.section-sep.working-sep {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}

.section-sep.stopped-sep {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}

/* ═══ شبكة الآليات ═══ */
.eq-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 0.875rem;
  margin-top: 0.75rem;
}

/* ═══ بطاقة الآلية ═══ */
.eq-card {
  background: #fff;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  padding: 0;
  position: relative;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.3s ease;
}

.eq-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
}

.eq-card.working {
  border-color: #10b981;
  background: linear-gradient(180deg, #ecfdf5 0%, #fff 100%);
}

.eq-card.stopped {
  border-color: #ef4444;
  background: linear-gradient(180deg, #fef2f2 0%, #fff 100%);
}

.eq-status-dot {
  position: absolute;
  top: 8px;
  left: 8px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  z-index: 2;
}

.eq-card.working .eq-status-dot {
  background: #10b981;
  box-shadow: 0 0 10px rgba(16, 185, 129, 0.7);
}

.eq-card.stopped .eq-status-dot {
  background: #ef4444;
  box-shadow: 0 0 10px rgba(239, 68, 68, 0.7);
}

.eq-inner {
  padding: 0.875rem;
}

.eq-top-bar {
  height: 3px;
  border-radius: 3px 3px 0 0;
  margin-bottom: 0.75rem;
}

.eq-card.working .eq-top-bar {
  background: linear-gradient(90deg, #10b981, #059669);
}

.eq-card.stopped .eq-top-bar {
  background: linear-gradient(90deg, #ef4444, #dc2626);
}

.eq-content {
  text-align: center;
  margin-bottom: 0.75rem;
}

.eq-machine-icon {
  font-size: 2rem;
  color: #6b7280;
  margin-bottom: 0.5rem;
}

.eq-card.working .eq-machine-icon {
  color: #10b981;
}

.eq-card.stopped .eq-machine-icon {
  color: #ef4444;
}

.eq-code {
  font-size: 1rem;
  font-weight: 900;
  color: #1f2937;
  margin-bottom: 0.25rem;
}

.eq-type-lbl {
  font-size: 0.75rem;
  color: #6b7280;
}

/* ═══ صف المشغلين ═══ */
.op-row {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 0.375rem;
  margin-bottom: 0.75rem;
  min-height: 32px;
}

.no-op-label {
  font-size: 0.7rem;
  color: #9ca3af;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.no-op-icon {
  font-size: 0.75rem;
}

.op-av {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  color: #fff;
  flex-shrink: 0;
  position: relative;
  cursor: pointer;
  transition: all 0.2s ease;
}

.op-av:hover {
  transform: scale(1.15);
}

.op-av.active {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  border: 2px solid #fff;
  box-shadow: 0 2px 6px rgba(59, 130, 246, 0.4);
}

.op-av.reserve {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  border: 2px solid #fff;
  box-shadow: 0 2px 6px rgba(245, 158, 11, 0.4);
}

.op-avatar-icon {
  font-size: 0.875rem;
}

/* ═══ صف ساعات التشغيل ═══ */
.eq-hours-row {
  display: flex;
  align-items: center;
  justify-content: space-around;
  padding: 0.625rem 0.5rem;
  background: #f9fafb;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  gap: 0.5rem;
}

.eq-hours-item {
  flex: 1;
  text-align: center;
}

.eq-hours-val {
  display: block;
  font-size: 1rem;
  font-weight: 900;
  color: #1f2937;
  line-height: 1;
}

.eq-hours-val.today-val {
  color: #3b82f6;
}

.eq-hours-lbl {
  display: block;
  font-size: 0.65rem;
  color: #6b7280;
  margin-top: 0.25rem;
}

.eq-hours-sep {
  width: 1px;
  height: 30px;
  background: #d1d5db;
}

/* ═══ قسم الآليات اليتيمة ═══ */
.orphan-section {
  background: #fff;
  border: 2px solid #fbbf24;
  border-radius: 14px;
  padding: 1.25rem;
  margin-top: 2rem;
  box-shadow: 0 4px 12px rgba(251, 191, 36, 0.15);
}

.orphan-title {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 1rem;
  font-weight: 900;
  color: #92400e;
  margin-bottom: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 2px solid #fef3c7;
}

.orphan-title-icon {
  font-size: 1.25rem;
  color: #f59e0b;
}

/* ═══ نظام Tooltips ═══ */
.tt-trigger {
  position: relative;
}

.tt-box {
  position: absolute;
  bottom: 100%;
  right: 50%;
  transform: translateX(50%);
  background: #1f2937;
  color: #fff;
  padding: 0.75rem;
  border-radius: 10px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  z-index: 9999;
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.2s ease, visibility 0.2s ease;
  pointer-events: none;
  min-width: 220px;
  max-width: 300px;
  margin-bottom: 8px;
}

.tt-box::after {
  content: '';
  position: absolute;
  top: 100%;
  right: 50%;
  transform: translateX(50%);
  border: 6px solid transparent;
  border-top-color: #1f2937;
}

.tt-box.tt-below {
  bottom: auto;
  top: 100%;
  margin-bottom: 0;
  margin-top: 8px;
}

.tt-box.tt-below::after {
  top: auto;
  bottom: 100%;
  border-top-color: transparent;
  border-bottom-color: #1f2937;
}

.tt-box.tt-show {
  opacity: 1;
  visibility: visible;
}

.tt-box-sm {
  min-width: 180px;
  padding: 0.625rem;
}

.tt-title {
  font-weight: 900;
  font-size: 0.9rem;
  margin-bottom: 0.625rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.tt-row {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.375rem 0;
  font-size: 0.8rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.tt-row:last-child {
  border-bottom: 0;
}

.tt-k {
  color: #9ca3af;
  font-weight: 600;
}

.tt-v {
  color: #fff;
  font-weight: 700;
  text-align: left;
}

/* ═══ استجابة الشاشات الصغيرة ═══ */
@media (max-width: 768px) {
  .mines-grid {
    grid-template-columns: 1fr;
  }

  .stats-row {
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  }

  .eq-grid {
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  }

  .legend-row {
    flex-direction: column;
    align-items: flex-start;
  }

  .legend-hint {
    margin-right: 0;
  }
}

@media (max-width: 480px) {
  .eq-grid {
    grid-template-columns: 1fr 1fr;
  }
}
</style>

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
