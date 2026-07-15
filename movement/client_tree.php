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

// العزل عبر بوابة المستأجر (K9 · هجرة 2026-07-15): أعلام التوافق الرجعي أُسقطت —
// {TENANT_SCOPE} والبوابة مسؤولا النطاق (قراءة الدوام عبر البوابة بسابقة الصيانة)،
// والسوبر عبر forAllTenants المسجَّل.
$ct_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('client tree super') : ems_tenant_db();

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

// تصنيف «جاهزة/متوقفة» للمعدّة من حالة الإتاحة (equipments.availability_status):
//   «جاهزة» = حالة الإتاحة ليست ضمن حالات التوقف (نفس قائمة map_page).
//   تُسمّى «جاهزة» لا «عاملة» لأنها تشمل معدّاتٍ صالحةً للعمل وإن لم تُسنَد لعمليةٍ نشطة بعد؛
//   «العاملة فعلاً» = مُضافة لجدول operations بحالة status=1 (تشغيلٌ سارٍ).
$EMS_STOPPED_AVAIL = ['معطلة', 'مبيعة/مسحوبة', 'خارج الخدمة', 'تحت الصيانة', 'موقوفة'];
$is_stopped = function ($avail) use ($EMS_STOPPED_AVAIL) {
    return in_array((string) $avail, $EMS_STOPPED_AVAIL, true);
};

// ============================================================
// 1) العملاء (جذور) — مع عزل الشركة
// ============================================================
$clients = [];
$cl_extra = " AND c.is_deleted = 0"; $cl_params = array();
if ($client_filter > 0) { $cl_extra .= " AND c.id = ?"; $cl_params[] = $client_filter; }
try {
    $cq_rows = $ct_gate->scopedQuery(array(
        'scope' => array('c' => 'clients'),
    ), "SELECT c.id, c.client_code, c.client_name FROM clients c WHERE {TENANT_SCOPE}$cl_extra ORDER BY c.client_name ASC", $cl_params);
} catch (\Throwable $t) { $cq_rows = array(); }
foreach ($cq_rows as $r) {
    $clients[(int) $r['id']] = ['id' => (int) $r['id'], 'code' => $r['client_code'], 'name' => $r['client_name'], 'projects' => []];
}

// ============================================================
// 2) المشاريع تحت كل عميل — عزل الشركة + نطاق الدور المقيّد
// ============================================================
$project_to_client = [];
$pr_extra = " AND p.status = 1 AND p.is_deleted = 0"; $pr_params = array();
if ($session_user_project_id > 0) { $pr_extra .= " AND p.id = ?"; $pr_params[] = $session_user_project_id; } // دور مقيّد بمشروع
try {
    $pq_rows = $ct_gate->scopedQuery(array(
        'scope' => array('p' => 'project'),
    ), "SELECT p.id, p.name, p.project_code, p.location, p.client_id FROM project p WHERE {TENANT_SCOPE}$pr_extra ORDER BY p.name ASC", $pr_params);
} catch (\Throwable $t) { $pq_rows = array(); }
foreach ($pq_rows as $r) {
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

$proj_contract_hours = []; // pid => إجمالي ساعات العقد المتفق عليها (مجموع forecasted_contracted_hours للعقود السارية)
$sup_contract_hours  = []; // pid => sid => إجمالي ساعات عقد المورد المتفق عليها في المشروع

// ============================================================
// 3) العمليات — اكتشاف الموردين المُشغِّلين + ربط كل معدّة بعملياتها (لجمع الساعات لاحقاً).
//    لا نبني المعدّات من هنا؛ معدّات المورد تُجلب بمفتاحها الأجنبي (equipments.suppliers) في الخطوة 5.
// ============================================================
$op_to_ctx = []; // op_id => [cid, pid]
$op_to_sup = []; // op_id => [pid, sid]  (لتجميع ساعات المورد المنفّذة في المشروع)
$eq_to_ops = []; // "pid:eq_id" => [op_id, ...]  (لجمع ساعات كل معدّة من عملياتها)
$proj_sup_eq = []; // pid => sid => [eq_id => true]  (معدّات المورد المنشورة فعلاً في المشروع — مصدر العدّ الصحيح)
$op_ids = [];
$op_meta = []; // op_id => ['target'=>الهدف اليومي, 'start'=>, 'end'=>]  (لحساب الساعات المستهدفة للفترة)
if (!empty($project_ids)) {
    $pids = array_map('intval', $project_ids);
    $pids_marks = implode(',', array_fill(0, count($pids), '?'));
    try {
        $ops_q_rows = $ct_gate->scopedQuery(array(
            'scope'  => array('o' => 'operations'),
            'enrich' => array('s' => 'suppliers'),
        ), "
        SELECT o.id AS op_id, CAST(o.project_id AS UNSIGNED) AS project_id,
               CAST(o.equipment AS UNSIGNED) AS eq_id,
               o.start AS op_start, o.end AS op_end, o.target_daily_hours AS target_daily,
               COALESCE(s.id,0) AS supplier_id, COALESCE(s.name,'بدون مورد') AS supplier_name,
               COALESCE(s.supplier_code,'') AS supplier_code
        FROM operations o
        LEFT JOIN suppliers s ON CAST(o.supplier_id AS UNSIGNED) = s.id
        WHERE {TENANT_SCOPE} AND CAST(o.project_id AS UNSIGNED) IN ($pids_marks)
          AND o.status = 1
    ", $pids);
    } catch (\Throwable $t) { $ops_q_rows = array(); }
    foreach ($ops_q_rows as $op) {
        $pid = (int) $op['project_id'];
        if (!isset($project_to_client[$pid])) continue;
        $cid = $project_to_client[$pid];
        $sid = (int) $op['supplier_id'];
        $opid = (int) $op['op_id'];
        // المورد المُشغِّل (من العملية) يظهر كعقدة حتى لو لم تُسجَّل له عقود
        if ($sid > 0 && !isset($clients[$cid]['projects'][$pid]['suppliers'][$sid])) {
            $clients[$cid]['projects'][$pid]['suppliers'][$sid] = [
                'id' => $sid, 'name' => $op['supplier_name'], 'code' => $op['supplier_code'], 'equipments' => [],
            ];
        }
        $op_to_ctx[$opid] = [$cid, $pid];
        if ($sid > 0) $op_to_sup[$opid] = [$pid, $sid];
        $eq_to_ops[$pid . ':' . (int) $op['eq_id']][] = $opid;
        // المعدّة منشورة في هذا المشروع تحت هذا المورد (مصدر ربط المعدّة بالمشروع)
        if ($sid > 0 && (int) $op['eq_id'] > 0) $proj_sup_eq[$pid][$sid][(int) $op['eq_id']] = true;
        $op_ids[$opid] = true;
        $op_meta[$opid] = [
            'target' => (float) ($op['target_daily'] ?? 0),
            'start'  => $op['op_start'],
            'end'    => $op['op_end'],
        ];
    }
}

// ============================================================
// 3ب) الموردون التعاقديون لكل مشروع — من supplierscontracts المرتبط بالمشروع
//     مباشرةً (project_id) أو عبر عقد المشروع (project_contract_id → contracts.id).
//     السبب: الموردون تابعون للعميل بعقودهم لا بالعمليات؛ هذا يضمن ظهورهم
//     واحتسابهم حتى إن لم تُسجَّل لهم عمليات بعد (كانت تُعطي 0 دائماً).
// ============================================================
if (!empty($project_ids)) {
    $pids = array_map('intval', $project_ids);
    $pids_marks = implode(',', array_fill(0, count($pids), '?'));

    // خريطة: عقد المشروع → المشروع (لربط عقد المورد بعقد المشروع)
    // + تجميع «إجمالي ساعات العقد» (forecasted_contracted_hours) لكل مشروع — الساعات المتفق عليها.
    $contract_to_project = [];
    try {
        $ct_q_rows = $ct_gate->scopedQuery(array(
            'scope' => array('ct' => 'contracts'),
        ), "SELECT ct.id, CAST(ct.project_id AS UNSIGNED) AS project_id, ct.status AS ct_status,
                COALESCE(ct.forecasted_contracted_hours,0) AS contracted_hours
            FROM contracts ct
            WHERE {TENANT_SCOPE} AND CAST(ct.project_id AS UNSIGNED) IN ($pids_marks) AND ct.is_deleted = 0", $pids);
    } catch (\Throwable $t) { $ct_q_rows = array(); }
    foreach ($ct_q_rows as $ct) {
        $contract_to_project[(int) $ct['id']] = (int) $ct['project_id'];
        // إجمالي الساعات المتفق عليها يُحتسب من العقود السارية فقط (status=1)
        if ((int) $ct['ct_status'] === 1) {
            $cpid = (int) $ct['project_id'];
            if (!isset($proj_contract_hours[$cpid])) $proj_contract_hours[$cpid] = 0.0;
            $proj_contract_hours[$cpid] += (float) $ct['contracted_hours'];
        }
    }
    $contract_ids = !empty($contract_to_project) ? array_map('intval', array_keys($contract_to_project)) : array(0);
    $ctids_marks = implode(',', array_fill(0, count($contract_ids), '?'));

    try {
        $sc_q_rows = $ct_gate->scopedQuery(array(
            'scope'  => array('sc' => 'supplierscontracts'),
            'enrich' => array('s' => 'suppliers'),
        ), "
        SELECT sc.supplier_id,
               CAST(sc.project_id AS UNSIGNED) AS project_id,
               sc.project_contract_id,
               COALESCE(sc.forecasted_contracted_hours,0) AS contracted_hours,
               COALESCE(s.name, 'مورد') AS supplier_name,
               COALESCE(s.supplier_code,'') AS supplier_code
        FROM supplierscontracts sc
        LEFT JOIN suppliers s ON s.id = sc.supplier_id
        WHERE {TENANT_SCOPE} AND sc.status = 1 AND sc.supplier_id IS NOT NULL AND sc.supplier_id > 0
          AND ( CAST(sc.project_id AS UNSIGNED) IN ($pids_marks) OR sc.project_contract_id IN ($ctids_marks) )
    ", array_merge($pids, $contract_ids));
    } catch (\Throwable $t) { $sc_q_rows = array(); }
    foreach ($sc_q_rows as $sc) {
        // حدّد المشروع: مباشرةً من project_id، وإلا عبر عقد المشروع المرتبط
        $pid = (int) $sc['project_id'];
        if (!isset($project_to_client[$pid]) && !empty($sc['project_contract_id']) && isset($contract_to_project[(int) $sc['project_contract_id']])) {
            $pid = $contract_to_project[(int) $sc['project_contract_id']];
        }
        if (!isset($project_to_client[$pid])) continue;
        $cid = $project_to_client[$pid];
        $sid = (int) $sc['supplier_id'];
        if (!isset($clients[$cid]['projects'][$pid]['suppliers'][$sid])) {
            $clients[$cid]['projects'][$pid]['suppliers'][$sid] = [
                'id' => $sid, 'name' => $sc['supplier_name'], 'code' => $sc['supplier_code'], 'equipments' => [],
            ];
        }
        // إجمالي ساعات عقد المورد المتفق عليها لهذا المورد في هذا المشروع (عقود سارية فقط)
        if (!isset($sup_contract_hours[$pid][$sid])) $sup_contract_hours[$pid][$sid] = 0.0;
        $sup_contract_hours[$pid][$sid] += (float) $sc['contracted_hours'];
    }
}

// ============================================================
// 4) الساعات المنفّذة من التايم شيت (operator = operations.id) ثم تجميعها لكل معدّة
// ============================================================
$op_hours = [];
if (!empty($op_ids)) {
    $opids = array_map('intval', array_keys($op_ids));
    $opids_marks = implode(',', array_fill(0, count($opids), '?'));
    try {
        $ts_q_rows = $ct_gate->scopedQuery(array(
            'scope' => array('t' => 'timesheet'),
        ), "
        SELECT CAST(t.operator AS UNSIGNED) AS op_id,
               SUM(t.total_work_hours) AS total_hours,
               SUM(CASE WHEN t.date = CURDATE() THEN t.total_work_hours ELSE 0 END) AS today_hours
        FROM timesheet t
        WHERE {TENANT_SCOPE} AND CAST(t.operator AS UNSIGNED) IN ($opids_marks)
          AND t.status = 1
          $ts_date_clause
        GROUP BY t.operator
    ", $opids);
    } catch (\Throwable $t) { $ts_q_rows = array(); }
    foreach ($ts_q_rows as $r) {
        $op_hours[(int) $r['op_id']] = ['total' => (float) $r['total_hours'], 'today' => (float) $r['today_hours']];
    }
}
$eq_hours = []; // "pid:eq_id" => ['total'=>, 'today'=>]  (مجموع ساعات عمليات المعدّة)
foreach ($eq_to_ops as $hk => $ops) {
    $t = 0.0; $d = 0.0;
    foreach ($ops as $opid) { if (isset($op_hours[$opid])) { $t += $op_hours[$opid]['total']; $d += $op_hours[$opid]['today']; } }
    $eq_hours[$hk] = ['total' => $t, 'today' => $d];
}

// ── ساعات عمل المشغّل من التايم شيت (operator_hours) — مفتاح: عملية (operator=operations.id) + موظف (employee_id) ──
$op_emp_hours = []; // op_id => [emp_id => operator_hours (للفترة)]
if (!empty($op_ids)) {
    $opids = array_map('intval', array_keys($op_ids));
    $opids_marks = implode(',', array_fill(0, count($opids), '?'));
    try {
        $oeh_q_rows = $ct_gate->scopedQuery(array(
            'scope' => array('t' => 'timesheet'),
        ), "
        SELECT CAST(t.operator AS UNSIGNED) AS op_id,
               CAST(t.employee_id AS UNSIGNED) AS emp_id,
               SUM(t.operator_hours) AS oh
        FROM timesheet t
        WHERE {TENANT_SCOPE} AND CAST(t.operator AS UNSIGNED) IN ($opids_marks)
          AND t.status = 1
          $ts_date_clause
        GROUP BY t.operator, t.employee_id
    ", $opids);
    } catch (\Throwable $t) { $oeh_q_rows = array(); }
    foreach ($oeh_q_rows as $r) {
        $op_emp_hours[(int) $r['op_id']][(int) $r['emp_id']] = (float) $r['oh'];
    }
}
$eq_emp_hours = []; // "pid:eq_id" => [emp_id => operator_hours]  (مجموع ساعات المشغّل عبر عمليات المعدّة)
foreach ($eq_to_ops as $hk => $ops) {
    $acc = [];
    foreach ($ops as $opid) {
        if (!isset($op_emp_hours[$opid])) continue;
        foreach ($op_emp_hours[$opid] as $emp => $h) { $acc[$emp] = ($acc[$emp] ?? 0.0) + $h; }
    }
    $eq_emp_hours[$hk] = $acc;
}

// ── الساعات المنفّذة التراكمية (كل الفترات) — لمقارنتها بإجمالي ساعات العقد (نسبة الإنجاز من العقد) ──
//    مستقلّة عن فلتر الفترة لأن العقد إجمالي تراكمي. تُجمَّع لكل مشروع عبر عملياته.
$op_hours_all = []; // op_id => إجمالي الساعات التراكمي
if (!empty($op_ids)) {
    $opids = array_map('intval', array_keys($op_ids));
    $opids_marks = implode(',', array_fill(0, count($opids), '?'));
    try {
        $ts_all_rows = $ct_gate->scopedQuery(array(
            'scope' => array('t' => 'timesheet'),
        ), "
        SELECT CAST(t.operator AS UNSIGNED) AS op_id, SUM(t.total_work_hours) AS total_hours
        FROM timesheet t
        WHERE {TENANT_SCOPE} AND CAST(t.operator AS UNSIGNED) IN ($opids_marks)
          AND t.status = 1
        GROUP BY t.operator
    ", $opids);
    } catch (\Throwable $t) { $ts_all_rows = array(); }
    foreach ($ts_all_rows as $r) {
        $op_hours_all[(int) $r['op_id']] = (float) $r['total_hours'];
    }
}
$proj_done_all = []; // pid => إجمالي الساعات المنفّذة التراكمية للمشروع
foreach ($eq_to_ops as $hk => $ops) {
    $pid = (int) explode(':', $hk)[0];
    foreach ($ops as $opid) {
        if (isset($op_hours_all[$opid])) {
            if (!isset($proj_done_all[$pid])) $proj_done_all[$pid] = 0.0;
            $proj_done_all[$pid] += $op_hours_all[$opid];
        }
    }
}
$sup_done_all = []; // pid => sid => إجمالي الساعات المنفّذة التراكمية للمورد في المشروع
foreach ($op_to_sup as $opid => $ps) {
    if (!isset($op_hours_all[$opid])) continue;
    list($pid, $sid) = $ps;
    if (!isset($sup_done_all[$pid][$sid])) $sup_done_all[$pid][$sid] = 0.0;
    $sup_done_all[$pid][$sid] += $op_hours_all[$opid];
}

// ── الساعات المستهدفة للفترة: الهدف اليومي × عدد أيام تقاطع [بداية,نهاية العملية] مع الفترة المختارة ──
//    حدٌّ أعلى = اليوم (لا نحتسب أياماً مستقبلية لأن المنفّذ لا يمكن أن يشملها).
$today_ts = strtotime(date('Y-m-d'));
$ems_overlap_days = function ($opStart, $opEnd, $from, $to) use ($today_ts) {
    if (empty($opStart) || empty($opEnd)) return 0;
    $opS = strtotime($opStart); $opE = strtotime($opEnd);
    if ($opS === false || $opE === false) return 0;
    $lo = $opS; $hi = $opE;
    if (!empty($from)) { $f = strtotime($from); if ($f !== false && $f > $lo) $lo = $f; }
    if (!empty($to))   { $tt = strtotime($to); if ($tt !== false && $tt < $hi) $hi = $tt; }
    if ($hi > $today_ts) $hi = $today_ts;
    if ($hi < $lo) return 0;
    return (int) floor(($hi - $lo) / 86400) + 1;
};
$eq_target = []; // "pid:eq_id" => إجمالي الساعات المستهدفة للفترة (مجموع عمليات المعدّة)
foreach ($eq_to_ops as $hk => $ops) {
    $tt = 0.0;
    foreach ($ops as $opid) {
        if (!isset($op_meta[$opid])) continue;
        $m = $op_meta[$opid];
        $tt += (float) $m['target'] * $ems_overlap_days($m['start'], $m['end'], $from, $to);
    }
    $eq_target[$hk] = $tt;
}
// ── الهدف اليومي المجمَّع لكل معدّة (مجموع target_daily_hours لعمليات المعدّة) — أساس الهدف الشهري (×30) ──
$eq_target_daily = []; // "pid:eq_id" => إجمالي الهدف اليومي
foreach ($eq_to_ops as $hk => $ops) {
    $td = 0.0;
    foreach ($ops as $opid) {
        if (isset($op_meta[$opid])) $td += (float) $op_meta[$opid]['target'];
    }
    $eq_target_daily[$hk] = $td;
}

// ============================================================
// 5) معدّات كل مورّد *داخل المشروع المحدّد* — من العمليات (operations) لا من ملكية المورد.
//    سجل العملية يربط المعدّة بالمشروع وبالمورد المُشغِّل، فعدد/قائمة المعدّات يجب أن
//    تكون «معدّات هذا المورد المنشورة في هذا المشروع» (proj_sup_eq المبنيّة بالخطوة 3).
//    استخدام equipments.suppliers وحده كان يُلصق كامل أسطول المورد بكل مشاريعه (عدّ خاطئ).
//    تفاصيل المعدّة (الكود/النوع/حالة الإتاحة) تُجلب إثراءً من جدول equipments.
// ============================================================
$eq_ids = [];
// كل معرّفات المعدّات المنشورة فعلاً (عبر العمليات) لجلب تفاصيلها دفعةً واحدة
$needed_eq_ids = [];
foreach ($proj_sup_eq as $pid => $sups) {
    foreach ($sups as $sid => $eqset) {
        foreach (array_keys($eqset) as $eqid) { if ((int) $eqid > 0) $needed_eq_ids[(int) $eqid] = true; }
    }
}
$eq_details = []; // eq_id => ['eq_code','type_name','avail']
if (!empty($needed_eq_ids)) {
    $eqids = array_map('intval', array_keys($needed_eq_ids));
    $eqids_marks = implode(',', array_fill(0, count($eqids), '?'));
    try {
        $eq_q_rows = $ct_gate->scopedQuery(array(
            'scope' => array('e' => 'equipments'),
        ), "
        SELECT e.id AS eq_id, e.code AS eq_code,
               e.availability_status AS avail, COALESCE(et.type,'') AS type_name
        FROM equipments e
        LEFT JOIN equipments_types et ON CAST(e.type AS UNSIGNED) = et.id
        WHERE {TENANT_SCOPE} AND e.id IN ($eqids_marks)
    ", $eqids);
    } catch (\Throwable $t) { $eq_q_rows = array(); }
    foreach ($eq_q_rows as $eq) { $eq_details[(int) $eq['eq_id']] = $eq; }
}
foreach ($clients as $cid => &$cl) {
    foreach ($cl['projects'] as $pid => &$pr) {
        foreach ($pr['suppliers'] as $sid => &$sup) {
            $eqset = isset($proj_sup_eq[$pid][(int) $sid]) ? array_keys($proj_sup_eq[$pid][(int) $sid]) : [];
            foreach ($eqset as $eqid) {
                $eqid = (int) $eqid;
                if (!isset($eq_details[$eqid])) continue; // خارج عزل الشركة أو محذوفة
                $eq = $eq_details[$eqid];
                $hk = $pid . ':' . $eqid;
                $sup['equipments'][$eqid] = [
                    'op_id'       => 0,
                    'eq_id'       => $eqid,
                    'eq_code'     => $eq['eq_code'],
                    'type_name'   => $eq['type_name'],
                    'stopped'     => $is_stopped($eq['avail']),
                    'operators'   => [],
                    'hours'       => isset($eq_hours[$hk]) ? $eq_hours[$hk]['total'] : 0.0,
                    'hours_today' => isset($eq_hours[$hk]) ? $eq_hours[$hk]['today'] : 0.0,
                    'target'      => isset($eq_target[$hk]) ? $eq_target[$hk] : 0.0,
                    'target_daily' => isset($eq_target_daily[$hk]) ? $eq_target_daily[$hk] : 0.0,
                ];
                $eq_ids[$eqid] = true;
            }
        }
        unset($sup);
    }
    unset($pr);
}
unset($cl);

// ============================================================
// 6) المشغّلون لكل معدّة — من equipment_drivers (لكل المعدّات المجموعة)
// ============================================================
$eq_operators = [];
if (!empty($eq_ids)) {
    $eqids2 = array_map('intval', array_keys($eq_ids));
    $eqids2_marks = implode(',', array_fill(0, count($eqids2), '?'));
    try {
        $drv_q_rows = $ct_gate->scopedQuery(array(
            'scope' => array('ed' => 'equipment_drivers', 'd' => 'employees'),
        ), "
        SELECT ed.equipment_id, ed.shift_type,
               d.id AS emp_id, d.name AS emp_name, d.employee_code, d.phone
        FROM equipment_drivers ed
        JOIN employees d ON ed.employee_id = d.id
        WHERE {TENANT_SCOPE} AND ed.equipment_id IN ($eqids2_marks)
          AND ed.status = 1 AND d.status = 1
        ORDER BY ed.equipment_id ASC, d.name ASC
    ", $eqids2);
    } catch (\Throwable $t) { $drv_q_rows = array(); }
    foreach ($drv_q_rows as $dr) {
        $eq_operators[(int) $dr['equipment_id']][] = $dr;
    }
}

// ============================================================
// التجميع: إسناد المشغّلين والساعات + حساب الإجماليات تصاعدياً
// ============================================================
$grand = ['clients' => 0, 'projects' => 0, 'suppliers' => 0, 'equip' => 0, 'working' => 0, 'stopped' => 0, 'operators' => 0, 'hours' => 0.0, 'target' => 0.0, 'contracted' => 0.0, 'done_all' => 0.0];
foreach ($clients as $cid => &$cl) {
    $cl['agg'] = ['projects' => 0, 'suppliers' => 0, 'equip' => 0, 'working' => 0, 'stopped' => 0, 'operators' => 0, 'hours' => 0.0, 'target' => 0.0, 'contracted' => 0.0, 'done_all' => 0.0];
    foreach ($cl['projects'] as $pid => &$pr) {
        $pr['agg'] = ['suppliers' => 0, 'equip' => 0, 'working' => 0, 'stopped' => 0, 'operators' => 0, 'hours' => 0.0, 'target' => 0.0, 'contracted' => 0.0, 'done_all' => 0.0];
        // إجمالي ساعات العقد المتفق عليها + المنفّذ التراكمي (لنسبة الإنجاز من العقد)
        $pr['agg']['contracted'] = isset($proj_contract_hours[$pid]) ? (float) $proj_contract_hours[$pid] : 0.0;
        $pr['agg']['done_all']   = isset($proj_done_all[$pid]) ? (float) $proj_done_all[$pid] : 0.0;
        foreach ($pr['suppliers'] as $sid => &$sup) {
            $sup['agg'] = ['equip' => 0, 'working' => 0, 'stopped' => 0, 'operators' => 0, 'hours' => 0.0, 'target' => 0.0,
                'contracted' => isset($sup_contract_hours[$pid][$sid]) ? (float) $sup_contract_hours[$pid][$sid] : 0.0,
                'done_all'   => isset($sup_done_all[$pid][$sid]) ? (float) $sup_done_all[$pid][$sid] : 0.0];
            foreach ($sup['equipments'] as $eqid => &$op) {
                $op['operators'] = $eq_operators[$op['eq_id']] ?? [];
                // إسناد ساعات عمل كل مشغّل (operator_hours) لهذه المعدّة في الفترة
                $hk_emp = $pid . ':' . (int) $op['eq_id'];
                foreach ($op['operators'] as &$drvRow) {
                    $empId = (int) ($drvRow['emp_id'] ?? 0);
                    $drvRow['work_hours'] = isset($eq_emp_hours[$hk_emp][$empId]) ? (float) $eq_emp_hours[$hk_emp][$empId] : 0.0;
                }
                unset($drvRow);
                $oc = count($op['operators']);
                $sup['agg']['equip']++;
                $sup['agg'][$op['stopped'] ? 'stopped' : 'working']++;
                $sup['agg']['operators'] += $oc;
                $sup['agg']['hours']     += $op['hours'];
                $sup['agg']['target']    += $op['target'];
            }
            unset($op);
            $pr['agg']['suppliers']++;
            $pr['agg']['equip']     += $sup['agg']['equip'];
            $pr['agg']['working']   += $sup['agg']['working'];
            $pr['agg']['stopped']   += $sup['agg']['stopped'];
            $pr['agg']['operators'] += $sup['agg']['operators'];
            $pr['agg']['hours']     += $sup['agg']['hours'];
            $pr['agg']['target']    += $sup['agg']['target'];
        }
        unset($sup);
        $cl['agg']['projects']++;
        $cl['agg']['suppliers'] += $pr['agg']['suppliers'];
        $cl['agg']['equip']     += $pr['agg']['equip'];
        $cl['agg']['working']   += $pr['agg']['working'];
        $cl['agg']['stopped']   += $pr['agg']['stopped'];
        $cl['agg']['operators'] += $pr['agg']['operators'];
        $cl['agg']['hours']     += $pr['agg']['hours'];
        $cl['agg']['target']    += $pr['agg']['target'];
        $cl['agg']['contracted'] += $pr['agg']['contracted'];
        $cl['agg']['done_all']   += $pr['agg']['done_all'];
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
        $grand['target']     += $cl['agg']['target'];
        $grand['contracted'] += $cl['agg']['contracted'];
        $grand['done_all']   += $cl['agg']['done_all'];
    }
}
unset($cl);

$fmtH = function ($h) { return rtrim(rtrim(number_format((float) $h, 1, '.', ''), '0'), '.'); };
// شارة ساعات العقد: إجمالي الساعات المتفق عليها (forecasted_contracted_hours) + مؤشّر
//   نسبة الإنجاز (المنفّذ التراكمي ÷ المتفق عليه) بشريط تقدّم ملوّن.
//   ملاحظة: النسبة تقارن المنفّذ بإجمالي العقد، لذا تُحسب على الإجمالي التراكمي بصرف النظر عن فلتر الفترة.
$contractBadge = function ($contracted, $doneHours) use ($fmtH) {
    if ($contracted <= 0) return '<span class="b hrs" title="لا توجد ساعات تعاقدية مسجّلة"><i class="fas fa-file-contract"></i> العقد —</span>';
    $pct  = ($doneHours / $contracted) * 100;
    $color = $pct >= 90 ? '#15803d' : ($pct >= 60 ? '#b45309' : '#b91c1c');
    $bg    = $pct >= 90 ? '#dcfce7' : ($pct >= 60 ? '#fef3c7' : '#fee2e2');
    return '<span class="b hrs" title="إجمالي ساعات العقد المتفق عليها"><i class="fas fa-clock"></i> العقد ' . $fmtH($contracted) . ' س</span>'
        . '<span class="b" style="color:' . $color . ';background:' . $bg . ';font-weight:700" title="نسبة الإنجاز من العقد = المنفّذ التراكمي ÷ المتفق عليه">' . number_format($pct, 0) . '%</span>';
};
// شريط نسبة الإنجاز من العقد — يُوضع كعنصر مباشر في .cnode-row ليمتد بعرض الكارد كاملاً (من أوّله لآخره)
$contractBar = function ($contracted, $doneHours) {
    if ($contracted <= 0) return '';
    $pct  = ($doneHours / $contracted) * 100;
    $pctC = min(100, max(0, $pct));
    return '<span class="cnode-bar" title="' . number_format($pct, 1) . '% من ساعات العقد منجزة"><span style="width:' . $pctC . '%"></span></span>';
};
// شارة الهدف الشهري للآلية: الهدف اليومي × 30 + نسبة الإنجاز (المنفّذ ÷ الهدف الشهري) — بنفس تصميم شارة العقد
$monthlyBadge = function ($monthlyTarget, $doneHours) use ($fmtH) {
    if ($monthlyTarget <= 0) return '<span class="b" title="لا يوجد هدف شهري محدّد للآلية"><i class="fas fa-bullseye"></i> الهدف الشهري —</span>';
    $pct = ($doneHours / $monthlyTarget) * 100;
    $color = $pct >= 90 ? '#15803d' : ($pct >= 60 ? '#b45309' : '#b91c1c');
    $bg    = $pct >= 90 ? '#dcfce7' : ($pct >= 60 ? '#fef3c7' : '#fee2e2');
    return '<span class="b hrs" title="الهدف الشهري للآلية = الهدف اليومي × 30"><i class="fas fa-bullseye"></i> الهدف الشهري ' . $fmtH($monthlyTarget) . ' س</span>'
        . '<span class="b" style="color:' . $color . ';background:' . $bg . ';font-weight:700" title="نسبة الإنجاز الشهري = المنفّذ ÷ الهدف الشهري">' . number_format($pct, 0) . '%</span>';
};
// شريط الإنجاز مقابل الهدف الشهري — يمتد بعرض الكارد كاملاً (عنصر مباشر في .cnode-row / .cleaf)
$monthlyBar = function ($monthlyTarget, $doneHours) {
    if ($monthlyTarget <= 0) return '';
    $pct  = ($doneHours / $monthlyTarget) * 100;
    $pctC = min(100, max(0, $pct));
    return '<span class="cnode-bar" title="' . number_format($pct, 1) . '% من الهدف منجز"><span style="width:' . $pctC . '%"></span></span>';
};
// شارة هدف المشغّل: حصّته من الهدف الشهري للمعدّة (÷ عدد المشغّلين) + نسبة إنجازه — بنفس تصميم باقي الشارات
$operatorBadge = function ($opTarget, $doneHours) use ($fmtH) {
    if ($opTarget <= 0) return '<span class="b" title="لا يوجد هدف محدّد للمشغّل"><i class="fas fa-bullseye"></i> الهدف —</span>';
    $pct = ($doneHours / $opTarget) * 100;
    $color = $pct >= 90 ? '#15803d' : ($pct >= 60 ? '#b45309' : '#b91c1c');
    $bg    = $pct >= 90 ? '#dcfce7' : ($pct >= 60 ? '#fef3c7' : '#fee2e2');
    return '<span class="b hrs" title="هدف المشغّل = الهدف الشهري للمعدّة ÷ عدد المشغّلين"><i class="fas fa-bullseye"></i> هدف ' . $fmtH($opTarget) . ' س</span>'
        . '<span class="b" style="color:' . $color . ';background:' . $bg . ';font-weight:700" title="نسبة إنجاز المشغّل = المنفّذ ÷ هدفه">' . number_format($pct, 0) . '%</span>';
};
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

    <!-- شريط الأدوات: بحث + فتح/طيّ الكل (فلتر الفترة محذوف؛ الافتراضي «هذا الشهر») -->
    <div class="ctree-toolbar">
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
    </div>

    <!-- الإجماليات العامة -->
    <div class="ctree-grand">
        <span><i class="fas fa-users"></i> عملاء: <b><?= (int) $grand['clients']; ?></b></span>
        <span><i class="fas fa-project-diagram"></i> مشاريع: <b><?= (int) $grand['projects']; ?></b></span>
        <span><i class="fas fa-truck-loading"></i> موردون: <b><?= (int) $grand['suppliers']; ?></b></span>
        <span><i class="fas fa-truck-monster"></i> معدّات: <b><?= (int) $grand['equip']; ?></b></span>
        <span><i class="fas fa-user-hard-hat"></i> مشغّلون: <b><?= (int) $grand['operators']; ?></b></span>
        <span class="hrs"><i class="fas fa-stopwatch"></i> ساعات منفّذة: <b><?= $fmtH($grand['hours']); ?></b></span>
        <span class="hrs"><i class="fas fa-bullseye"></i> مستهدفة: <b><?= $fmtH($grand['target']); ?></b><?php if ($grand['target'] > 0): ?> · إنجاز <b><?= number_format($grand['hours'] / $grand['target'] * 100, 0); ?>%</b><?php endif; ?></span>
        <span class="hrs"><i class="fas fa-clock"></i> ساعات العقد: <b><?= $fmtH($grand['contracted']); ?></b><?php if ($grand['contracted'] > 0): ?> · منجز <b><?= number_format($grand['done_all'] / $grand['contracted'] * 100, 0); ?>%</b><?php endif; ?></span>
    </div>

    <div class="ctree" id="ctree">
        <?php if (empty($clients)): ?>
            <div class="ctree-empty">لا يوجد عملاء ضمن نطاقك.</div>
        <?php else: foreach ($clients as $cid => $cl):
            $cSearch = $e($cl['name'] . ' ' . $cl['code']); ?>
            <div class="cnode lvl-client" data-search="<?= $cSearch; ?>">
                <div class="cnode-row" onclick="ctreeToggle(this)">
                    <span class="cnode-arrow"><i class="fas fa-chevron-left"></i></span>
                    <span class="cnode-ico"><i class="fas fa-user-tie"></i></span>
                    <span class="cnode-title"><?= $e($cl['name']); ?></span>
                    <?php if ($cl['code']): ?><span class="cnode-code"><?= $e($cl['code']); ?></span><?php endif; ?>
                    <span class="cnode-badges">
                        <span class="b">مشاريع <?= (int) $cl['agg']['projects']; ?></span>
                        <span class="b">موردون <?= (int) $cl['agg']['suppliers']; ?></span>
                        <span class="b">معدّات <?= (int) $cl['agg']['equip']; ?></span>
                        <span class="b">مشغّلون <?= (int) $cl['agg']['operators']; ?></span>
                        <?php /* كارد العميل يعتمد على المجاميع التراكمية لعقود المشاريع فقط (لا هدف/إنجاز للفترة) */ ?>
                        <?= $contractBadge($cl['agg']['contracted'], $cl['agg']['done_all']); ?>
                    </span>
                    <?= $contractBar($cl['agg']['contracted'], $cl['agg']['done_all']); ?>
                </div>
                <div class="cnode-children">
                    <?php if (empty($cl['projects'])): ?>
                        <div class="ctree-empty sm">لا مشاريع لهذا العميل.</div>
                    <?php else: foreach ($cl['projects'] as $pid => $pr):
                        $pHours = $pr['agg']['hours'];
                        $pSearch = $e($pr['name'] . ' ' . $pr['code'] . ' ' . $pr['location']); ?>
                        <div class="cnode lvl-project" data-search="<?= $pSearch; ?>">
                            <div class="cnode-row" onclick="ctreeToggle(this)">
                                <span class="cnode-arrow"><i class="fas fa-chevron-left"></i></span>
                                <span class="cnode-ico"><i class="fas fa-project-diagram"></i></span>
                                <a class="cnode-title link" href="map_page.php?project_id=<?= (int) $pid; ?>" onclick="event.stopPropagation();"><?= $e($pr['name']); ?></a>
                                <?php if ($pr['code']): ?><span class="cnode-code"><?= $e($pr['code']); ?></span><?php endif; ?>
                                <?php if ($pr['location']): ?><span class="cnode-loc"><i class="fas fa-map-marker-alt"></i> <?= $e($pr['location']); ?></span><?php endif; ?>
                                <span class="cnode-badges">
                                    <span class="b">موردون <?= (int) $pr['agg']['suppliers']; ?></span>
                                    <span class="b">معدّات <?= (int) $pr['agg']['equip']; ?></span>
                                    <span class="b">مشغّلون <?= (int) $pr['agg']['operators']; ?></span>
                                    <span class="b hrs"><?= $fmtH($pHours); ?> س</span>
                                    <?= $contractBadge($pr['agg']['contracted'], $pr['agg']['done_all']); ?>
                                </span>
                                <?= $contractBar($pr['agg']['contracted'], $pr['agg']['done_all']); ?>
                            </div>
                            <div class="cnode-children">
                                <?php if (empty($pr['suppliers'])): ?>
                                    <div class="ctree-empty sm">لا موردون/معدّات في هذا المشروع.</div>
                                <?php else:
                                    // ترتيب الموردين تنازلياً حسب الساعات لإبراز توزيع الحصص
                                    uasort($pr['suppliers'], function ($a, $b) { return ($b['agg']['hours'] <=> $a['agg']['hours']); });
                                    foreach ($pr['suppliers'] as $sid => $sup):
                                        $sHours = $sup['agg']['hours'];
                                        $sSearch = $e($sup['name'] . ' ' . ($sup['code'] ?? '')); ?>
                                    <div class="cnode lvl-supplier" data-search="<?= $sSearch; ?>">
                                        <div class="cnode-row" onclick="ctreeToggle(this)">
                                            <span class="cnode-arrow"><i class="fas fa-chevron-left"></i></span>
                                            <span class="cnode-ico"><i class="fas fa-truck-loading"></i></span>
                                            <span class="cnode-title"><?= $e($sup['name']); ?></span>
                                            <?php if (!empty($sup['code'])): ?><span class="cnode-code"><?= $e($sup['code']); ?></span><?php endif; ?>
                                            <span class="cnode-badges">
                                                <span class="b">معدّات <?= (int) $sup['agg']['equip']; ?></span>
                                                <span class="b">مشغّلون <?= (int) $sup['agg']['operators']; ?></span>
                                                <span class="b hrs"><?= $fmtH($sHours); ?> س</span>
                                                <?= $contractBadge($sup['agg']['contracted'], $sup['agg']['done_all']); ?>
                                            </span>
                                            <?= $contractBar($sup['agg']['contracted'], $sup['agg']['done_all']); ?>
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
                                                            <?php $eqMonthlyTarget = (float) ($op['target_daily'] ?? 0) * 30; ?>
                                                            <?= $monthlyBadge($eqMonthlyTarget, $op['hours']); ?>
                                                        </span>
                                                        <?= $monthlyBar($eqMonthlyTarget, $op['hours']); ?>
                                                    </div>
                                                    <div class="cnode-children">
                                                        <?php if (empty($op['operators'])): ?>
                                                            <div class="cleaf none"><i class="fas fa-user-slash"></i> لا مشغّل</div>
                                                        <?php else:
                                                            $opCount   = count($op['operators']);
                                                            $drvTarget = $opCount > 0 ? ($eqMonthlyTarget / $opCount) : 0.0; // هدف المشغّل = الهدف الشهري للمعدّة ÷ عدد المشغّلين
                                                            foreach ($op['operators'] as $drv):
                                                            $drvDone = (float) ($drv['work_hours'] ?? 0); ?>
                                                            <div class="cleaf" data-search="<?= $e($drv['emp_name'] . ' ' . $drv['employee_code']); ?>">
                                                                <span class="cleaf-shift"><?= $shiftIcon($drv['shift_type']); ?></span>
                                                                <a class="link" href="../Employees/employee_profile.php?id=<?= (int) $drv['emp_id']; ?>"><?= $e($drv['emp_name']); ?></a>
                                                                <?php if (!empty($drv['employee_code'])): ?><span class="cleaf-code"><?= $e($drv['employee_code']); ?></span><?php endif; ?>
                                                                <?php if (!empty($drv['phone'])): ?><span class="cleaf-phone"><i class="fas fa-phone"></i> <?= $e($drv['phone']); ?></span><?php endif; ?>
                                                                <span class="cnode-badges">
                                                                    <span class="b hrs" title="ساعات عمل المشغّل من التايم شيت (operator_hours) للفترة المختارة"><i class="fas fa-user-clock"></i> <?= $fmtH($drvDone); ?> س</span>
                                                                    <?= $operatorBadge($drvTarget, $drvDone); ?>
                                                                </span>
                                                                <?= $monthlyBar($drvTarget, $drvDone); ?>
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
                // أعِد الحالة الافتراضية: كل العُقد مغلقة (مطابقة لحالة أول تحميل)
                document.querySelectorAll('#ctree .cnode').forEach(function (n) { n.classList.remove('open'); });
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
