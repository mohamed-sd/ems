<?php
/**
 * شجرة العميل — عرض هرمي قابل للطيّ بخمسة مستويات (عرض فقط):
 *   العميل ← مشاريعه ← موردو كل مشروع ← معدّات كل مورّد ← مشغّلو كل معدّة.
 * يعيد استخدام منطق التجميع/العزل من movement/map_page.php (نفس الجداول والأعمدة).
 */
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=" . urlencode('لا توجد بيئة شركة صالحة'));
    exit();
}

// صلاحية العرض
$perms = function_exists('check_page_permissions') ? check_page_permissions($conn, 'movement/client_tree.php') : ['can_view' => true];
if (empty($perms['can_view'])) {
    header('Location: ../main/dashboard.php?msg=' . urlencode('❌ لا توجد صلاحية لعرض هذه الصفحة'));
    exit();
}

$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

// ── أعلام العزل (توافق رجعي) ──
$clients_has_company = db_table_has_column($conn, 'clients', 'company_id');
$project_has_company = db_table_has_column($conn, 'project', 'company_id');
$ops_has_company     = db_table_has_column($conn, 'operations', 'company_id');
$ed_has_company      = db_table_has_column($conn, 'equipment_drivers', 'company_id');
$ts_has_company      = db_table_has_column($conn, 'timesheet', 'company_id');

$scope_company = (!$is_super_admin) ? $company_id : 0;

// ── الأدوار المقيّدة بمشروع (مثل مدير الموقع) — نطاقها فقط ──
$session_user_project_id = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
$client_filter = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

// ── فلتر الفترة للساعات (الافتراضي: الشهر الحالي) ──
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$valid_periods = ['today', 'month', 'all', 'range'];
if (!in_array($period, $valid_periods, true)) $period = 'month';
$from = $to = '';
if ($period === 'today') {
    $from = $to = date('Y-m-d');
} elseif ($period === 'month') {
    $from = date('Y-m-01');
    $to   = date('Y-m-d');
} elseif ($period === 'range') {
    $from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : date('Y-m-01');
    $to   = isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])   ? $_GET['to']   : date('Y-m-d');
}
$ts_date_clause = '';
if ($period !== 'all') {
    $from_s = mysqli_real_escape_string($conn, $from);
    $to_s   = mysqli_real_escape_string($conn, $to);
    $ts_date_clause = " AND t.date >= '$from_s' AND t.date <= '$to_s'";
}

$is_stopped = function ($avail) {
    return in_array($avail, ['معطلة', 'مبيعة/مسحوبة', 'خارج الخدمة', 'تحت الصيانة', 'موقوفة'], true);
};

// ============================================================
// 1) العملاء (جذور) — مع عزل الشركة
// ============================================================
$clients = [];
$cl_where = "1=1";
if ($clients_has_company && !$is_super_admin) $cl_where .= " AND c.company_id = $scope_company";
if (db_table_has_column($conn, 'clients', 'is_deleted')) $cl_where .= " AND c.is_deleted = 0";
if ($client_filter > 0) $cl_where .= " AND c.id = $client_filter";
$cq = mysqli_query($conn, "SELECT c.id, c.client_code, c.client_name FROM clients c WHERE $cl_where ORDER BY c.client_name ASC");
if ($cq) while ($r = mysqli_fetch_assoc($cq)) {
    $clients[(int) $r['id']] = ['id' => (int) $r['id'], 'code' => $r['client_code'], 'name' => $r['client_name'], 'projects' => []];
}

// ============================================================
// 2) المشاريع تحت كل عميل — عزل الشركة + نطاق الدور المقيّد
// ============================================================
$project_to_client = [];
$pr_where = "p.status = 1";
if (db_table_has_column($conn, 'project', 'is_deleted')) $pr_where .= " AND p.is_deleted = 0";
if ($project_has_company && !$is_super_admin) $pr_where .= " AND p.company_id = $scope_company";
if ($session_user_project_id > 0) $pr_where .= " AND p.id = $session_user_project_id"; // دور مقيّد بمشروع
$pq = mysqli_query($conn, "SELECT p.id, p.name, p.project_code, p.location, p.client_id FROM project p WHERE $pr_where ORDER BY p.name ASC");
if ($pq) while ($r = mysqli_fetch_assoc($pq)) {
    $cid = (int) $r['client_id'];
    if (!isset($clients[$cid])) continue; // المشروع لعميل خارج النطاق
    $pid = (int) $r['id'];
    $clients[$cid]['projects'][$pid] = [
        'id' => $pid, 'name' => $r['name'], 'code' => $r['project_code'], 'location' => $r['location'],
        'suppliers' => [],
    ];
    $project_to_client[$pid] = $cid;
}

$project_ids = array_keys($project_to_client);

// ============================================================
// 3) العمليات (معدّات مشغّلة) لكل مشاريع النطاق — استعلام واحد (نمط map_page)
// ============================================================
$op_to_ctx = []; // op_id => [cid, pid, sid]
$eq_ids = [];
$op_ids = [];
if (!empty($project_ids)) {
    $pids_str = implode(',', array_map('intval', $project_ids));
    $ops_company_clause = ($ops_has_company && !$is_super_admin) ? " AND o.company_id = $scope_company" : "";
    $ops_q = mysqli_query($conn, "
        SELECT o.id AS op_id, CAST(o.project_id AS UNSIGNED) AS project_id,
               e.id AS eq_id, e.code AS eq_code, COALESCE(et.type,'') AS type_name,
               e.availability_status AS avail,
               COALESCE(s.id,0) AS supplier_id, COALESCE(s.name,'بدون مورد') AS supplier_name
        FROM operations o
        JOIN equipments e ON o.equipment = e.id
        LEFT JOIN equipments_types et ON CAST(e.type AS UNSIGNED) = et.id
        LEFT JOIN suppliers s ON CAST(o.supplier_id AS UNSIGNED) = s.id
        WHERE CAST(o.project_id AS UNSIGNED) IN ($pids_str)
          AND o.status = 1
          $ops_company_clause
        ORDER BY supplier_name ASC, e.code ASC
    ");
    if ($ops_q) while ($op = mysqli_fetch_assoc($ops_q)) {
        $pid = (int) $op['project_id'];
        if (!isset($project_to_client[$pid])) continue;
        $cid = $project_to_client[$pid];
        $sid = (int) $op['supplier_id'];
        $opid = (int) $op['op_id'];
        if (!isset($clients[$cid]['projects'][$pid]['suppliers'][$sid])) {
            $clients[$cid]['projects'][$pid]['suppliers'][$sid] = [
                'id' => $sid, 'name' => $op['supplier_name'], 'equipments' => [],
            ];
        }
        $clients[$cid]['projects'][$pid]['suppliers'][$sid]['equipments'][$opid] = [
            'op_id' => $opid, 'eq_id' => (int) $op['eq_id'], 'eq_code' => $op['eq_code'],
            'type_name' => $op['type_name'], 'stopped' => $is_stopped($op['avail']),
            'operators' => [], 'hours' => 0.0, 'hours_today' => 0.0,
        ];
        $op_to_ctx[$opid] = [$cid, $pid, $sid];
        $eq_ids[(int) $op['eq_id']] = true;
        $op_ids[$opid] = true;
    }
}

// ============================================================
// 4) المشغّلون لكل معدّة — استعلام واحد (equipment_drivers JOIN employees)
// ============================================================
$eq_operators = [];
if (!empty($eq_ids)) {
    $eq_ids_str = implode(',', array_map('intval', array_keys($eq_ids)));
    $ed_company_clause = ($ed_has_company && !$is_super_admin) ? " AND ed.company_id = $scope_company" : "";
    $drv_q = mysqli_query($conn, "
        SELECT ed.equipment_id, ed.shift_type,
               d.id AS emp_id, d.name AS emp_name, d.employee_code, d.phone
        FROM equipment_drivers ed
        JOIN employees d ON ed.employee_id = d.id
        WHERE ed.equipment_id IN ($eq_ids_str)
          AND ed.status = 1 AND d.status = 1
          $ed_company_clause
        ORDER BY ed.equipment_id ASC, d.name ASC
    ");
    if ($drv_q) while ($dr = mysqli_fetch_assoc($drv_q)) {
        $eq_operators[(int) $dr['equipment_id']][] = $dr;
    }
}

// ============================================================
// 5) الساعات المنفّذة من التايم شيت (operator = operations.id) — استعلام واحد بفلتر الفترة
// ============================================================
$op_hours = [];
if (!empty($op_ids)) {
    $op_ids_str = implode(',', array_map('intval', array_keys($op_ids)));
    $ts_company_clause = ($ts_has_company && !$is_super_admin) ? " AND t.company_id = $scope_company" : "";
    $ts_q = mysqli_query($conn, "
        SELECT CAST(t.operator AS UNSIGNED) AS op_id,
               SUM(t.total_work_hours) AS total_hours,
               SUM(CASE WHEN t.date = CURDATE() THEN t.total_work_hours ELSE 0 END) AS today_hours
        FROM timesheet t
        WHERE CAST(t.operator AS UNSIGNED) IN ($op_ids_str)
          AND t.status = 1
          $ts_company_clause $ts_date_clause
        GROUP BY t.operator
    ");
    if ($ts_q) while ($r = mysqli_fetch_assoc($ts_q)) {
        $op_hours[(int) $r['op_id']] = ['total' => (float) $r['total_hours'], 'today' => (float) $r['today_hours']];
    }
}

// ============================================================
// التجميع: إسناد المشغّلين والساعات + حساب الإجماليات تصاعدياً
// ============================================================
$grand = ['clients' => 0, 'projects' => 0, 'suppliers' => 0, 'equip' => 0, 'working' => 0, 'stopped' => 0, 'operators' => 0, 'hours' => 0.0];
foreach ($clients as $cid => &$cl) {
    $cl['agg'] = ['projects' => 0, 'suppliers' => 0, 'equip' => 0, 'working' => 0, 'stopped' => 0, 'operators' => 0, 'hours' => 0.0];
    foreach ($cl['projects'] as $pid => &$pr) {
        $pr['agg'] = ['suppliers' => 0, 'equip' => 0, 'working' => 0, 'stopped' => 0, 'operators' => 0, 'hours' => 0.0];
        foreach ($pr['suppliers'] as $sid => &$sup) {
            $sup['agg'] = ['equip' => 0, 'working' => 0, 'stopped' => 0, 'operators' => 0, 'hours' => 0.0];
            foreach ($sup['equipments'] as $opid => &$op) {
                $op['operators'] = $eq_operators[$op['eq_id']] ?? [];
                $op['hours']       = isset($op_hours[$opid]) ? $op_hours[$opid]['total'] : 0.0;
                $op['hours_today'] = isset($op_hours[$opid]) ? $op_hours[$opid]['today'] : 0.0;
                $oc = count($op['operators']);
                $sup['agg']['equip']++;
                $sup['agg'][$op['stopped'] ? 'stopped' : 'working']++;
                $sup['agg']['operators'] += $oc;
                $sup['agg']['hours']     += $op['hours'];
            }
            unset($op);
            $pr['agg']['suppliers']++;
            $pr['agg']['equip']     += $sup['agg']['equip'];
            $pr['agg']['working']   += $sup['agg']['working'];
            $pr['agg']['stopped']   += $sup['agg']['stopped'];
            $pr['agg']['operators'] += $sup['agg']['operators'];
            $pr['agg']['hours']     += $sup['agg']['hours'];
        }
        unset($sup);
        $cl['agg']['projects']++;
        $cl['agg']['suppliers'] += $pr['agg']['suppliers'];
        $cl['agg']['equip']     += $pr['agg']['equip'];
        $cl['agg']['working']   += $pr['agg']['working'];
        $cl['agg']['stopped']   += $pr['agg']['stopped'];
        $cl['agg']['operators'] += $pr['agg']['operators'];
        $cl['agg']['hours']     += $pr['agg']['hours'];
    }
    unset($pr);
    if ($cl['agg']['projects'] > 0 || true) {
        $grand['clients']++;
        $grand['projects']   += $cl['agg']['projects'];
        $grand['suppliers']  += $cl['agg']['suppliers'];
        $grand['equip']      += $cl['agg']['equip'];
        $grand['working']    += $cl['agg']['working'];
        $grand['stopped']    += $cl['agg']['stopped'];
        $grand['operators']  += $cl['agg']['operators'];
        $grand['hours']      += $cl['agg']['hours'];
    }
}
unset($cl);

$fmtH = function ($h) { return rtrim(rtrim(number_format((float) $h, 1, '.', ''), '0'), '.'); };
$shiftIcon = function ($s) {
    $s = (string) $s;
    if ($s === 'D' || strpos($s, 'صباح') !== false) return '☀️';
    if ($s === 'N' || strpos($s, 'لي') !== false) return '🌙';
    return '🔄';
};

$page_title = "إيكوبيشن | شجرة العميل";
include('../inheader.php');
// CSS معزول خاص بهذه الشاشة فقط (محصور تحت .client-tree-page)
$__ctcss = __DIR__ . '/../assets/css/client-tree.css';
echo '<link rel="stylesheet" href="/ems/assets/css/client-tree.css' . (is_file($__ctcss) ? '?v=' . filemtime($__ctcss) : '') . '">' . "\n";
include('../insidebar.php');
?>

<div class="main client-tree-page">
    <?php
    $header_title   = 'شجرة العميل';
    $header_icon    = 'fas fa-sitemap';
    $header_actions = array();
    $header_back    = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <!-- شريط الأدوات: فلتر الفترة + بحث + فتح/طيّ الكل -->
    <form method="get" class="ctree-toolbar" id="ctreeToolbar">
        <?php if ($client_filter > 0): ?><input type="hidden" name="client_id" value="<?= (int) $client_filter; ?>"><?php endif; ?>
        <div class="ctree-tool">
            <label><i class="fas fa-clock"></i> فترة الساعات</label>
            <select name="period" onchange="document.getElementById('ctreeToolbar').submit()">
                <option value="today" <?= $period === 'today' ? 'selected' : ''; ?>>اليوم</option>
                <option value="month" <?= $period === 'month' ? 'selected' : ''; ?>>هذا الشهر</option>
                <option value="all"   <?= $period === 'all' ? 'selected' : ''; ?>>الإجمالي التراكمي</option>
                <option value="range" <?= $period === 'range' ? 'selected' : ''; ?>>مدى تاريخي</option>
            </select>
        </div>
        <?php if ($period === 'range'): ?>
        <div class="ctree-tool"><label>من</label><input type="date" name="from" value="<?= $e($from); ?>"></div>
        <div class="ctree-tool"><label>إلى</label><input type="date" name="to" value="<?= $e($to); ?>"></div>
        <div class="ctree-tool"><label>&nbsp;</label><button type="submit" class="btn btn-primary btn-sm">تطبيق</button></div>
        <?php endif; ?>
        <div class="ctree-tool ctree-search-wrap">
            <label><i class="fas fa-search"></i> بحث</label>
            <input type="text" id="ctreeSearch" placeholder="ابحث باسم/كود العميل أو المشروع أو المعدّة أو المشغّل..." autocomplete="off">
        </div>
        <div class="ctree-tool">
            <label>&nbsp;</label>
            <div class="ctree-btns">
                <button type="button" class="btn btn-sm" onclick="ctreeAll(true)"><i class="fas fa-plus-square"></i> فتح الكل</button>
                <button type="button" class="btn btn-sm" onclick="ctreeAll(false)"><i class="fas fa-minus-square"></i> طيّ الكل</button>
            </div>
        </div>
    </form>

    <!-- الإجماليات العامة -->
    <div class="ctree-grand">
        <span><i class="fas fa-users"></i> عملاء: <b><?= (int) $grand['clients']; ?></b></span>
        <span><i class="fas fa-project-diagram"></i> مشاريع: <b><?= (int) $grand['projects']; ?></b></span>
        <span><i class="fas fa-truck-loading"></i> موردون: <b><?= (int) $grand['suppliers']; ?></b></span>
        <span><i class="fas fa-truck-monster"></i> معدّات: <b><?= (int) $grand['equip']; ?></b></span>
        <span class="ok"><i class="fas fa-circle"></i> عاملة: <b><?= (int) $grand['working']; ?></b></span>
        <span class="bad"><i class="fas fa-circle"></i> متوقفة: <b><?= (int) $grand['stopped']; ?></b></span>
        <span><i class="fas fa-user-hard-hat"></i> مشغّلون: <b><?= (int) $grand['operators']; ?></b></span>
        <span class="hrs"><i class="fas fa-stopwatch"></i> ساعات منفّذة: <b><?= $fmtH($grand['hours']); ?></b></span>
    </div>

    <div class="ctree" id="ctree">
        <?php if (empty($clients)): ?>
            <div class="ctree-empty">لا يوجد عملاء ضمن نطاقك.</div>
        <?php else: foreach ($clients as $cid => $cl):
            $cSearch = $e($cl['name'] . ' ' . $cl['code']); ?>
            <div class="cnode lvl-client open" data-search="<?= $cSearch; ?>">
                <div class="cnode-row" onclick="ctreeToggle(this)">
                    <span class="cnode-arrow"><i class="fas fa-chevron-down"></i></span>
                    <span class="cnode-ico"><i class="fas fa-user-tie"></i></span>
                    <span class="cnode-title"><?= $e($cl['name']); ?></span>
                    <?php if ($cl['code']): ?><span class="cnode-code"><?= $e($cl['code']); ?></span><?php endif; ?>
                    <span class="cnode-badges">
                        <span class="b">مشاريع <?= (int) $cl['agg']['projects']; ?></span>
                        <span class="b">موردون <?= (int) $cl['agg']['suppliers']; ?></span>
                        <span class="b">معدّات <?= (int) $cl['agg']['equip']; ?></span>
                        <span class="b ok"><?= (int) $cl['agg']['working']; ?> عاملة</span>
                        <span class="b bad"><?= (int) $cl['agg']['stopped']; ?> متوقفة</span>
                        <span class="b">مشغّلون <?= (int) $cl['agg']['operators']; ?></span>
                        <span class="b hrs"><?= $fmtH($cl['agg']['hours']); ?> س</span>
                    </span>
                </div>
                <div class="cnode-children">
                    <?php if (empty($cl['projects'])): ?>
                        <div class="ctree-empty sm">لا مشاريع لهذا العميل.</div>
                    <?php else: foreach ($cl['projects'] as $pid => $pr):
                        $pHours = $pr['agg']['hours'];
                        $pSearch = $e($pr['name'] . ' ' . $pr['code'] . ' ' . $pr['location']); ?>
                        <div class="cnode lvl-project open" data-search="<?= $pSearch; ?>">
                            <div class="cnode-row" onclick="ctreeToggle(this)">
                                <span class="cnode-arrow"><i class="fas fa-chevron-down"></i></span>
                                <span class="cnode-ico"><i class="fas fa-project-diagram"></i></span>
                                <a class="cnode-title link" href="map_page.php?project_id=<?= (int) $pid; ?>" onclick="event.stopPropagation();"><?= $e($pr['name']); ?></a>
                                <?php if ($pr['code']): ?><span class="cnode-code"><?= $e($pr['code']); ?></span><?php endif; ?>
                                <?php if ($pr['location']): ?><span class="cnode-loc"><i class="fas fa-map-marker-alt"></i> <?= $e($pr['location']); ?></span><?php endif; ?>
                                <span class="cnode-badges">
                                    <span class="b">موردون <?= (int) $pr['agg']['suppliers']; ?></span>
                                    <span class="b">معدّات <?= (int) $pr['agg']['equip']; ?></span>
                                    <span class="b ok"><?= (int) $pr['agg']['working']; ?> عاملة</span>
                                    <span class="b bad"><?= (int) $pr['agg']['stopped']; ?> متوقفة</span>
                                    <span class="b">مشغّلون <?= (int) $pr['agg']['operators']; ?></span>
                                    <span class="b hrs"><?= $fmtH($pHours); ?> س</span>
                                </span>
                            </div>
                            <div class="cnode-children">
                                <?php if (empty($pr['suppliers'])): ?>
                                    <div class="ctree-empty sm">لا موردون/معدّات في هذا المشروع.</div>
                                <?php else:
                                    // ترتيب الموردين تنازلياً حسب الساعات لإبراز توزيع الحصص
                                    uasort($pr['suppliers'], function ($a, $b) { return ($b['agg']['hours'] <=> $a['agg']['hours']); });
                                    foreach ($pr['suppliers'] as $sid => $sup):
                                        $sHours = $sup['agg']['hours'];
                                        $share  = $pHours > 0 ? ($sHours / $pHours * 100) : 0;
                                        $sSearch = $e($sup['name']); ?>
                                    <div class="cnode lvl-supplier" data-search="<?= $sSearch; ?>">
                                        <div class="cnode-row" onclick="ctreeToggle(this)">
                                            <span class="cnode-arrow"><i class="fas fa-chevron-left"></i></span>
                                            <span class="cnode-ico"><i class="fas fa-truck-loading"></i></span>
                                            <span class="cnode-title"><?= $e($sup['name']); ?></span>
                                            <span class="cnode-badges">
                                                <span class="b">معدّات <?= (int) $sup['agg']['equip']; ?></span>
                                                <span class="b ok"><?= (int) $sup['agg']['working']; ?> عاملة</span>
                                                <span class="b bad"><?= (int) $sup['agg']['stopped']; ?> متوقفة</span>
                                                <span class="b">مشغّلون <?= (int) $sup['agg']['operators']; ?></span>
                                                <span class="b hrs"><?= $fmtH($sHours); ?> س</span>
                                                <span class="b share"><?= number_format($share, 0); ?>% من المشروع</span>
                                            </span>
                                            <span class="cnode-bar" title="<?= number_format($share, 1); ?>% من ساعات المشروع"><span style="width:<?= min(100, $share); ?>%"></span></span>
                                        </div>
                                        <div class="cnode-children">
                                            <?php foreach ($sup['equipments'] as $opid => $op): $opSearch = $e($op['eq_code'] . ' ' . $op['type_name']); ?>
                                                <div class="cnode lvl-equip" data-search="<?= $opSearch; ?>">
                                                    <div class="cnode-row" onclick="ctreeToggle(this)">
                                                        <span class="cnode-arrow"><i class="fas fa-chevron-left"></i></span>
                                                        <span class="status-dot <?= $op['stopped'] ? 'bad' : 'ok'; ?>" title="<?= $op['stopped'] ? 'متوقفة' : 'عاملة'; ?>"></span>
                                                        <a class="cnode-title link" href="../Equipments/equipment_profile.php?id=<?= (int) $op['eq_id']; ?>" onclick="event.stopPropagation();"><?= $e($op['eq_code']); ?></a>
                                                        <span class="cnode-code"><?= $e($op['type_name'] ?: '—'); ?></span>
                                                        <span class="cnode-badges">
                                                            <span class="b">مشغّلون <?= count($op['operators']); ?></span>
                                                            <span class="b hrs"><?= $fmtH($op['hours']); ?> س<?= ($op['hours_today'] > 0) ? ' · اليوم ' . $fmtH($op['hours_today']) : ''; ?></span>
                                                        </span>
                                                    </div>
                                                    <div class="cnode-children">
                                                        <?php if (empty($op['operators'])): ?>
                                                            <div class="cleaf none"><i class="fas fa-user-slash"></i> لا مشغّل</div>
                                                        <?php else: foreach ($op['operators'] as $drv): ?>
                                                            <div class="cleaf" data-search="<?= $e($drv['emp_name'] . ' ' . $drv['employee_code']); ?>">
                                                                <span class="cleaf-shift"><?= $shiftIcon($drv['shift_type']); ?></span>
                                                                <a class="link" href="../Employees/employee_profile.php?id=<?= (int) $drv['emp_id']; ?>"><?= $e($drv['emp_name']); ?></a>
                                                                <?php if (!empty($drv['employee_code'])): ?><span class="cleaf-code"><?= $e($drv['employee_code']); ?></span><?php endif; ?>
                                                                <?php if (!empty($drv['phone'])): ?><span class="cleaf-phone"><i class="fas fa-phone"></i> <?= $e($drv['phone']); ?></span><?php endif; ?>
                                                            </div>
                                                        <?php endforeach; endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<script>
(function () {
    window.ctreeToggle = function (rowEl) {
        var node = rowEl.closest('.cnode');
        if (node) node.classList.toggle('open');
    };
    window.ctreeAll = function (open) {
        document.querySelectorAll('#ctree .cnode').forEach(function (n) {
            if (open) n.classList.add('open'); else n.classList.remove('open');
        });
    };
    // بحث فوري: يُبرز المطابق ويفتح مساره حتى الجذر
    var box = document.getElementById('ctreeSearch');
    if (box) {
        box.addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            var nodes = document.querySelectorAll('#ctree .cnode, #ctree .cleaf');
            if (!q) {
                nodes.forEach(function (n) { n.style.display=''; n.classList.remove('search-hit'); });
                // أعِد الحالة الافتراضية: عملاء/مشاريع مفتوحة، الباقي مطويّ
                document.querySelectorAll('#ctree .cnode').forEach(function (n) {
                    if (n.classList.contains('lvl-client') || n.classList.contains('lvl-project')) n.classList.add('open');
                    else n.classList.remove('open');
                });
                return;
            }
            nodes.forEach(function (n) { n.style.display='none'; n.classList.remove('search-hit'); });
            document.querySelectorAll('#ctree .cnode, #ctree .cleaf').forEach(function (n) {
                var hay = (n.getAttribute('data-search') || '').toLowerCase();
                if (hay.indexOf(q) !== -1) {
                    n.style.display=''; n.classList.add('search-hit');
                    // أظهِر وافتح كل الآباء
                    var p = n.parentElement;
                    while (p && p.id !== 'ctree') {
                        if (p.classList && p.classList.contains('cnode')) { p.style.display=''; p.classList.add('open'); }
                        if (p.classList && p.classList.contains('cnode-children')) p.style.display='';
                        p = p.parentElement;
                    }
                    // أظهِر كل الأبناء
                    n.querySelectorAll('.cnode, .cleaf').forEach(function (c) { c.style.display=''; });
                    n.querySelectorAll('.cnode').forEach(function (c) { c.classList.add('open'); });
                }
            });
        });
    }
})();
</script>
</body>

</html>
