<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';
require_once '../includes/driver_contract_dates.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$equipment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($equipment_id <= 0) {
    ems_gov_flash_redirect('equipments.php', 'معرف المعدة غير صحيح ❌', 'GOV-REF-404', '');
    exit();
}

// العزل عبر بوابة المستأجر — والسوبر عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$eqp_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('equipment profile super') : ems_tenant_db();

// صلاحية اعتماد الكرت = صلاحية تعديل المعدات (دور الأسطول)
$__pp = function_exists('check_page_permissions') ? check_page_permissions($conn, 'equipments_fleet') : ['can_edit' => true];
$can_edit = !empty($__pp['can_edit']);

$equipment = null;
try {
    $eq_rows = $eqp_gate->scopedQuery(
        array('scope' => array('e' => 'equipments'), 'enrich' => array('s' => 'suppliers')),
        "SELECT e.*, s.name AS supplier_name, et.type AS equipment_type_name
                    FROM equipments e
                    LEFT JOIN suppliers s ON s.id = e.suppliers
                    LEFT JOIN equipments_types et ON et.id = e.type
                    WHERE e.id = ? AND {TENANT_SCOPE}
                    LIMIT 1", array($equipment_id));
    $equipment = $eq_rows ? $eq_rows[0] : null;
} catch (\Throwable $t) { error_log('equipment_profile card: ' . $t->getMessage()); }

if (!$equipment) {
    ems_gov_flash_redirect('equipments.php', 'المعدة غير موجودة او خارج نطاق الشركة ❌', 'GOV-SCOPE-403', '');
    exit();
}

$operations_count = 0;
$active_operations = 0;
$projects_count = 0;
$drivers_count = 0;
$hours_sum = 0;
$standby_sum = 0;

try {
    $r = $eqp_gate->scopedQuery(array('scope' => array('o' => 'operations')),
        "SELECT COUNT(*) AS c FROM operations o WHERE o.equipment = ? AND {TENANT_SCOPE}", array($equipment_id));
    if ($r) { $operations_count = intval($r[0]['c']); }
    $r = $eqp_gate->scopedQuery(array('scope' => array('o' => 'operations')),
        "SELECT COUNT(*) AS c FROM operations o WHERE o.equipment = ? AND o.status = 1 AND {TENANT_SCOPE}", array($equipment_id));
    if ($r) { $active_operations = intval($r[0]['c']); }
    $r = $eqp_gate->scopedQuery(array('scope' => array('o' => 'operations')),
        "SELECT COUNT(DISTINCT o.project_id) AS c FROM operations o WHERE o.equipment = ? AND {TENANT_SCOPE}", array($equipment_id));
    if ($r) { $projects_count = intval($r[0]['c']); }
    $r = $eqp_gate->scopedQuery(array('scope' => array('ed' => 'equipment_drivers')),
        "SELECT COUNT(DISTINCT ed.employee_id) AS c FROM equipment_drivers ed WHERE ed.equipment_id = ? AND ed.status = 1 AND {TENANT_SCOPE}", array($equipment_id));
    if ($r) { $drivers_count = intval($r[0]['c']); }
    $r = $eqp_gate->scopedQuery(array('scope' => array('t' => 'timesheet', 'o' => 'operations')),
        "SELECT IFNULL(SUM(t.operator_hours),0) AS op_hours,
                                IFNULL(SUM(t.operator_standby_hours),0) AS standby_hours
                         FROM timesheet t
                         INNER JOIN operations o ON o.id = t.operator
                         WHERE o.equipment = ? AND t.status = 1 AND {TENANT_SCOPE}", array($equipment_id));
    if ($r) {
        $hours_sum = floatval($r[0]['op_hours']);
        $standby_sum = floatval($r[0]['standby_hours']);
    }
} catch (\Throwable $t) { error_log('equipment_profile KPIs: ' . $t->getMessage()); }

$projects_list = array();
try {
    $projects_list = $eqp_gate->scopedQuery(
        array('scope' => array('o' => 'operations'), 'enrich' => array('p' => 'project', 't' => 'timesheet')),
        "SELECT
                            p.id,
                            p.name,
                            p.project_code,
                            IFNULL(SUM(t.operator_hours + t.operator_standby_hours),0) AS total_hours,
                            COUNT(t.id) AS shifts_count
                        FROM operations o
                        LEFT JOIN project p ON p.id = o.project_id
                        LEFT JOIN timesheet t ON t.operator = o.id AND t.status = 1
                        WHERE o.equipment = ? AND {TENANT_SCOPE}
                        GROUP BY p.id, p.name, p.project_code
                        ORDER BY total_hours DESC
                        LIMIT 10", array($equipment_id));
} catch (\Throwable $t) { error_log('equipment_profile projects: ' . $t->getMessage()); }

$drivers_list = array();
try {
    $drivers_list = $eqp_gate->scopedQuery(
        array('scope' => array('ed' => 'equipment_drivers', 'd' => 'employees')),
        "SELECT
                           d.id,
                           d.name,
                           ed.start_date,
                           ed.end_date,
                           ed.status
                        FROM equipment_drivers ed
                        INNER JOIN employees d ON d.id = ed.employee_id
                        WHERE ed.equipment_id = ? AND {TENANT_SCOPE}
                        ORDER BY ed.id DESC
                        LIMIT 10", array($equipment_id));
} catch (\Throwable $t) { error_log('equipment_profile drivers: ' . $t->getMessage()); }

// ═══════════════════════════════════════════════════════════════════
//  كرت المعدة — جداول الأبناء (وثائق · حماية · مكوّنات · تاريخ)
// ═══════════════════════════════════════════════════════════════════
$can_edit_card = !empty($can_edit);

// قوائم ثابتة
$DOC_TYPES        = ['تأمين', 'رخصة', 'شهادة فحص', 'شهادة سلامة', 'شهادة رفع', 'شهادة معايرة', 'أخرى'];
$PROTECTION_TYPES = ['تنجيد مقاعد', 'شبك حماية زجاج', 'حمايات معدنية', 'نظام إطفاء', 'نظام تتبّع', 'تجهيزات سلامة', 'حماية تشغيل', 'تأمين شامل', 'تأمين هندسي', 'أخرى'];
$PROTECTION_STATES = ['فعال', 'يحتاج تجديداً', 'منتهٍ/مفكوك'];
$COMPONENT_TYPES  = ['محرك', 'هيدروليك', 'جيربوكس', 'دفرنس', 'مولد', 'أخرى'];
$EVENT_TYPES      = ['دخول', 'تشغيل بمشروع', 'خروج', 'ترحيل', 'صيانة', 'عطل', 'حادث/ضرر', 'تفتيش', 'إيقاف', 'إعادة تشغيل', 'تغيير مصدر', 'خروج/بيع'];

// جلب السطور — عبر البوابة (العزل بالحقن، وسم is_deleted يبقى شرطًا صريحًا كسلوك الأصل)
$compliance_rows = $protection_rows = $component_rows = $history_rows = [];

try {
    $compliance_rows = $eqp_gate->scopedQuery(array('scope' => array('fleet_equipment_compliance' => 'fleet_equipment_compliance')),
        "SELECT * FROM fleet_equipment_compliance WHERE equipment_id = ? AND is_deleted = 0 AND {TENANT_SCOPE} ORDER BY (expiry_date IS NULL), expiry_date ASC, id DESC", array($equipment_id));
} catch (\Throwable $t) { error_log('equipment_profile compliance: ' . $t->getMessage()); }
try {
    $protection_rows = $eqp_gate->scopedQuery(array('scope' => array('p' => 'fleet_equipment_protection')),
        "SELECT p.* FROM fleet_equipment_protection p WHERE p.equipment_id = ? AND p.is_deleted = 0 AND {TENANT_SCOPE} ORDER BY p.id DESC", array($equipment_id));
} catch (\Throwable $t) { error_log('equipment_profile protection: ' . $t->getMessage()); }
try {
    $component_rows = $eqp_gate->scopedQuery(array('scope' => array('fleet_equipment_component' => 'fleet_equipment_component')),
        "SELECT * FROM fleet_equipment_component WHERE equipment_id = ? AND is_deleted = 0 AND {TENANT_SCOPE} ORDER BY is_current DESC, id DESC", array($equipment_id));
} catch (\Throwable $t) { error_log('equipment_profile components: ' . $t->getMessage()); }
try {
    $history_rows = $eqp_gate->scopedQuery(
        array('scope' => array('h' => 'fleet_equipment_history'), 'enrich' => array('pr' => 'project', 'u' => 'users')),
        "SELECT h.*, pr.name AS project_name, u.name AS created_by_name FROM fleet_equipment_history h LEFT JOIN project pr ON pr.id = h.project_id LEFT JOIN users u ON u.id = h.created_by WHERE h.equipment_id = ? AND {TENANT_SCOPE} ORDER BY h.event_date DESC, h.id DESC LIMIT 100", array($equipment_id));
} catch (\Throwable $t) { error_log('equipment_profile history: ' . $t->getMessage()); }
if (true) {

    // الزمن المستغرق: الفارق بين كل حدث والحدث الأسبق له (الصفوف تنازلية ⇒ الأسبق هو التالي).
    if (!function_exists('ems_duration_ar')) {
        function ems_duration_ar($sec)
        {
            $sec = max(0, (int) $sec);
            $units = [['أيام', 'يوم', 86400], ['ساعات', 'ساعة', 3600], ['دقائق', 'دقيقة', 60], ['ثوان', 'ثانية', 1]];
            $parts = [];
            $rem = $sec;
            foreach ($units as $u) {
                if ($rem >= $u[2]) {
                    $v = intdiv($rem, $u[2]);
                    $rem %= $u[2];
                    $parts[] = $v . ' ' . (($v >= 3 && $v <= 10) ? $u[0] : $u[1]);
                    if (count($parts) === 2) {
                        break;
                    }
                }
            }
            return $parts ? implode(' ', $parts) : '0 ثانية';
        }
    }
    $hn = count($history_rows);
    for ($i = 0; $i < $hn; $i++) {
        $cur  = isset($history_rows[$i]['event_date']) ? strtotime((string) $history_rows[$i]['event_date']) : false;
        $prev = ($i + 1 < $hn && isset($history_rows[$i + 1]['event_date'])) ? strtotime((string) $history_rows[$i + 1]['event_date']) : false;
        if ($cur !== false && $prev !== false && $cur >= $prev) {
            $history_rows[$i]['_elapsed_seconds'] = $cur - $prev;
            $history_rows[$i]['_elapsed_label']   = ems_duration_ar($cur - $prev);
        } else {
            $history_rows[$i]['_elapsed_seconds'] = null;
            $history_rows[$i]['_elapsed_label']   = '—';
        }
    }
}

// ═══════════════════════════════════════════════════════════════════
//  قسم الصيانة — أوامر هذه المعدة + مؤشرات (مرئي لكل من يفتح الكرت/قراءة)
//  المصدر: mnt_order (معزول بالشركة). المؤشرات تُحتسب من بيانات الأوامر + ساعات التشغيل.
// ═══════════════════════════════════════════════════════════════════
$mnt_orders = array();
$mnt_total = $mnt_closed = $mnt_failures = $mnt_open = 0;
$mnt_downtime = 0.0; $mnt_cost = 0.0;
$mnt_last = isset($equipment['last_maintenance_date']) ? $equipment['last_maintenance_date'] : null;
if (true) {
    try {
        $mnt_orders = $eqp_gate->scopedQuery(array('scope' => array('mo' => 'mnt_order')),
            "SELECT mo.id, mo.code, mo.source, mo.maint_type, mo.state,
                                     mo.downtime_hours, mo.total_cost, mo.work_start, mo.work_end, mo.closed_at
                                FROM mnt_order mo
                               WHERE mo.equipment_id = ? AND COALESCE(mo.is_deleted,0)=0 AND {TENANT_SCOPE}
                               ORDER BY mo.id DESC LIMIT 50", array($equipment_id));
    } catch (\Throwable $t) { error_log('equipment_profile mnt orders: ' . $t->getMessage()); }

    $agg = array();
    try {
        $agg = $eqp_gate->scopedQuery(array('scope' => array('mo' => 'mnt_order')),
            "SELECT COUNT(*) total,
                                       SUM(state='إغلاق') closed,
                                       SUM(state IN ('بلاغ','تنفيذ','فحص')) opened,
                                       COALESCE(SUM(downtime_hours),0) downtime,
                                       COALESCE(SUM(total_cost),0) cost,
                                       SUM(source='بلاغ') failures,
                                       MAX(closed_at) last_closed
                                  FROM mnt_order mo
                                 WHERE mo.equipment_id = ? AND COALESCE(mo.is_deleted,0)=0 AND {TENANT_SCOPE}", array($equipment_id));
    } catch (\Throwable $t) { error_log('equipment_profile mnt agg: ' . $t->getMessage()); }
    if ($agg && ($a = $agg[0])) {
        $mnt_total    = intval($a['total']);
        $mnt_closed   = intval($a['closed']);
        $mnt_open     = intval($a['opened']);
        $mnt_downtime = floatval($a['downtime']);
        $mnt_cost     = floatval($a['cost']);
        $mnt_failures = intval($a['failures']);
        if (!empty($a['last_closed'])) { $mnt_last = $a['last_closed']; }
    }
}
// المؤشرات: MTBF = ساعات التشغيل / عدد الأعطال · MTTR = إجمالي التوقّف / الأوامر المغلقة
// نسبة الجاهزية = التشغيل / (التشغيل + التوقّف) ×100
$mnt_mtbf  = $mnt_failures > 0 ? ($hours_sum / $mnt_failures) : null;
$mnt_mttr  = $mnt_closed   > 0 ? ($mnt_downtime / $mnt_closed) : null;
$mnt_avail = ($hours_sum + $mnt_downtime) > 0 ? ($hours_sum / ($hours_sum + $mnt_downtime) * 100) : null;

// ═══════════════════════════════════════════════════════════════════
//  قسم التفتيش الفني — تفتيشات هذه المعدة (المصدر: mnt_inspection، معزول بالشركة)
// ═══════════════════════════════════════════════════════════════════
$ins_rows = array();
$ins_lines_map = array();   // [inspection_id] => [ {sec,item,cond,mv,note,rec}, ... ] لعرض النافذة
$ins_total = $ins_done = $ins_open = $ins_critical = 0;
$ins_last = null;
// مجموعات حالة البند (موائمة لمخطّطات الاستمارات: افتراضي/حادث/عمرة)
$INS_GOOD = array('سليم', 'صالح');
$INS_NOTE = array('ملاحظة', 'ضرر طفيف', 'ضرر متوسط', 'تآكل ضمن الحد');
$INS_CRIT = array('حرج', 'ضرر بالغ', 'يحتاج استبدال', 'يحتاج عمرة');
$INS_NA   = array('لا ينطبق');
if (true) {
    $q = array();
    try {
        $q = $eqp_gate->scopedQuery(
            array('scope' => array('i' => 'mnt_inspection'), 'enrich' => array('u' => 'users', 'p' => 'project')),
            "SELECT i.id, i.code, i.inspection_type, i.scheduled_date, i.completed_at,
                                     i.overall_result, i.state, i.score, i.tech_readiness_state,
                                     i.equipment_condition, i.engine_condition, i.notes,
                                     u.name AS inspector_name, p.name AS project_name
                                FROM mnt_inspection i
                                LEFT JOIN users u ON u.id = i.inspector_id
                                LEFT JOIN project p ON p.id = i.project_id
                               WHERE i.equipment_id = ? AND COALESCE(i.is_deleted,0)=0 AND {TENANT_SCOPE}
                               ORDER BY i.id DESC LIMIT 50", array($equipment_id));
    } catch (\Throwable $t) { error_log('equipment_profile inspections: ' . $t->getMessage()); }
    foreach ($q as $r) { $ins_rows[] = $r; }

    $agg = array();
    try {
        $agg = $eqp_gate->scopedQuery(array('scope' => array('i' => 'mnt_inspection')),
            "SELECT COUNT(*) total,
                                       SUM(state IN ('مكتمل','مغلق')) done,
                                       SUM(state IN ('جديد','مجدول','قيد التنفيذ')) opened,
                                       MAX(COALESCE(completed_at, scheduled_date)) last_at
                                  FROM mnt_inspection i
                                 WHERE i.equipment_id = ? AND COALESCE(i.is_deleted,0)=0 AND {TENANT_SCOPE}", array($equipment_id));
    } catch (\Throwable $t) { error_log('equipment_profile ins agg: ' . $t->getMessage()); }
    if ($agg && ($a = $agg[0])) {
        $ins_total = intval($a['total']);
        $ins_done  = intval($a['done']);
        $ins_open  = intval($a['opened']);
        $ins_last  = !empty($a['last_at']) ? $a['last_at'] : null;
    }
    // ملاحظات حرجة + بنود كل تفتيش (للعرض المُفصّل في النافذة)
    if (!empty($ins_rows)) {
        $ins_ids_csv = implode(',', array_map(function ($r) { return intval($r['id']); }, $ins_rows));
        $lq = array();
        try {
            $lq = $eqp_gate->scopedQuery(array('scope' => array('l' => 'mnt_inspection_line')),
                "SELECT l.inspection_id, l.section, l.component, l.condition_state,
                                          l.measured_value, l.note, l.recommendation
                                     FROM mnt_inspection_line l
                                    WHERE l.inspection_id IN ($ins_ids_csv) AND {TENANT_SCOPE}
                                    ORDER BY l.is_template DESC, l.seq, l.id");
        } catch (\Throwable $t) { error_log('equipment_profile ins lines: ' . $t->getMessage()); }
        foreach ($lq as $lr) {
            $iid = intval($lr['inspection_id']);
            $cs  = (string) ($lr['condition_state'] ?? '');
            if (in_array($cs, $INS_CRIT, true)) { $ins_critical++; }
            $ins_lines_map[$iid][] = array(
                'sec'  => (string) ($lr['section'] ?? ''),
                'item' => (string) ($lr['component'] ?? ''),
                'cond' => $cs,
                'mv'   => (string) ($lr['measured_value'] ?? ''),
                'note' => (string) ($lr['note'] ?? ''),
                'rec'  => (string) ($lr['recommendation'] ?? ''),
            );
        }
    }
}

// شارات ملوّنة احترافية لجدول التفتيش (الدرجة/الجاهزية/الحالة)
if (!function_exists('ems_ins_chip')) {
    // UXW-01 ①②: اللونُ صار صنفًا دلاليًّا (ep-chip-*) في كتلةِ الأنماطِ الصفحية —
    // القيمُ المصيَّرةُ هي القيمُ نفسُها حرفًا، والمجموعةُ مغلقةٌ فلا نمطَ محسوب.
    function ems_ins_chip($text, $cls, $wide = false)
    {
        $klass = 'ep-chip ' . $cls . ($wide ? ' ep-chip-w46' : '');
        return '<span class="' . $klass . '">' . htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    function ems_ins_score_chip($score)
    {
        if ($score === null || $score === '') return '<span class="ep-dash">—</span>';
        $s = intval($score);
        if ($s >= 85)      return ems_ins_chip($s . '%', 'ep-chip-ok', true);
        elseif ($s >= 60)  return ems_ins_chip($s . '%', 'ep-chip-warn', true);
        return ems_ins_chip($s . '%', 'ep-chip-crit', true);
    }
    function ems_ins_ready_chip($r)
    {
        $r = trim((string) $r);
        if ($r === '') return '<span class="ep-dash">—</span>';
        $map = array(
            'جاهزة'        => 'ep-chip-ok',
            'جاهزة بتحفّظ' => 'ep-chip-warn',
            'غير جاهزة'    => 'ep-chip-crit',
        );
        $c = isset($map[$r]) ? $map[$r] : 'ep-chip-mute';
        return ems_ins_chip($r, $c);
    }
    function ems_ins_state_chip($st)
    {
        $st = trim((string) $st);
        $map = array(
            'مكتمل'       => 'ep-chip-ok',
            'مغلق'        => 'ep-chip-grey',
            'قيد التنفيذ' => 'ep-chip-warn',
            'مجدول'       => 'ep-chip-info',
            'جديد'        => 'ep-chip-info',
        );
        $c = isset($map[$st]) ? $map[$st] : 'ep-chip-mute';
        return ems_ins_chip($st !== '' ? $st : '—', $c);
    }
    // عدّ بنود تفتيش واحد وإرجاع شارة ملاحظات (سليم ✓ أو ⚠ حرج/ملاحظة)
    function ems_ins_findings_badge($lines, $GOOD, $NOTE, $CRIT, $NA)
    {
        if (empty($lines)) return '<span class="ep-dash">—</span>';
        $crit = $note = $app = 0;
        foreach ($lines as $l) {
            $v = (string) $l['cond'];
            if ($v === '' || in_array($v, $NA, true)) continue;
            $app++;
            if (in_array($v, $CRIT, true)) $crit++;
            elseif (!in_array($v, $GOOD, true)) $note++;
        }
        $out = array();
        if ($crit > 0) $out[] = ems_ins_chip('⚠ حرج ' . $crit, 'ep-chip-crit');
        if ($note > 0) $out[] = ems_ins_chip('ملاحظة ' . $note, 'ep-chip-warn');
        if (empty($out)) $out[] = ems_ins_chip('✓ سليم', 'ep-chip-ok');
        return '<span class="ep-badge-row">' . implode('', $out) . '</span>';
    }
}

// ═══════════════════════════════════════════════════════════════════
//  قسم الصيانة الوقائية — خطط هذه المعدة (المصدر: mnt_plan، معزول بالشركة)
// ═══════════════════════════════════════════════════════════════════
$pln_rows = array();
$pln_total = $pln_active = $pln_due = 0;
$pln_last = null; $pln_next = null;
$today_str = date('Y-m-d');
if (true) {
    $q = array();
    try {
        $q = $eqp_gate->scopedQuery(array('scope' => array('pl' => 'mnt_plan')),
            "SELECT pl.id, pl.code, pl.name, pl.trigger_basis, pl.interval_value,
                                     pl.last_done_date, pl.last_done_meter, pl.next_due_date, pl.next_due_meter, pl.state
                                FROM mnt_plan pl
                               WHERE pl.equipment_id = ? AND COALESCE(pl.is_deleted,0)=0 AND {TENANT_SCOPE}
                               ORDER BY pl.id DESC LIMIT 50", array($equipment_id));
    } catch (\Throwable $t) { error_log('equipment_profile plans: ' . $t->getMessage()); }
    foreach ($q as $r) {
        $pln_rows[] = $r;
        if ($r['state'] === 'نشطة') {
            $pln_active++;
            if ($r['trigger_basis'] === 'ساعات') {
                if ($r['next_due_meter'] !== null && $hours_sum >= floatval($r['next_due_meter'])) { $pln_due++; }
            } else {
                if (!empty($r['next_due_date']) && $r['next_due_date'] <= $today_str) { $pln_due++; }
            }
        }
        if (!empty($r['next_due_date']) && ($pln_next === null || $r['next_due_date'] < $pln_next)) { $pln_next = $r['next_due_date']; }
        if (!empty($r['last_done_date']) && ($pln_last === null || $r['last_done_date'] > $pln_last)) { $pln_last = $r['last_done_date']; }
    }
    $pln_total = count($pln_rows);
}

// المنفّذ/المورد صار إدخالاً يدوياً حرّاً (غير مربوط بجدول الموردين) — لا حاجة لجلب الموردين.

// حساب حالة الوثيقة من تاريخ الانتهاء (سارية/قاربت/منتهية) + تنبيهات حرجة
$DOC_ALERT_DAYS = 30;
$today_ts = strtotime(date('Y-m-d'));
$critical_expired = 0; $docs_expired = 0; $docs_soon = 0;
function ems_doc_status($expiry, $today_ts, $days)
{
    if (empty($expiry) || $expiry === '0000-00-00') return ['code' => 'none', 'label' => '—', 'cls' => ''];
    $ts = strtotime($expiry);
    if (!$ts) return ['code' => 'none', 'label' => '—', 'cls' => ''];
    if ($ts < $today_ts) return ['code' => 'expired', 'label' => 'منتهية', 'cls' => 'status-inactive'];
    if ($ts <= $today_ts + ($days * 86400)) return ['code' => 'soon', 'label' => 'قاربت الانتهاء', 'cls' => 'badge-busy'];
    return ['code' => 'valid', 'label' => 'سارية', 'cls' => 'status-active'];
}
foreach ($compliance_rows as $cr) {
    $stt = ems_doc_status($cr['expiry_date'] ?? null, $today_ts, $DOC_ALERT_DAYS);
    if ($stt['code'] === 'expired') { $docs_expired++; if (!empty($cr['is_critical'])) $critical_expired++; }
    elseif ($stt['code'] === 'soon') { $docs_soon++; }
}

$ee = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

$page_title = 'إيكوبيشن | بطاقة المعدة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
require_once __DIR__ . '/../includes/profile_kit.php';   // عُدّةُ بطاقةِ الكِيان — التأليفُ بديلُ النسخ
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<?php /* ══ كتلةُ الأنماطِ المحليةُ قُلِّمت من 90 سطرًا إلى شريطِ التبويبات وحدَه ══
        سقط منها **لوحُ هويةٍ كامل** (`ep-hero` بتدرّجِه وشريطِه الجانبيِّ
        و`ep-hero-ic` و`ep-chips`/`ep-chip` و`ep-pill` و`ep-facts`/`ep-fact`
        بمفتاحٍ وقيمة) و**شبكةُ مؤشراتٍ** (`profile-grid`/`profile-card`/`kpi`/
        `label`) و**بطاقاتُ الأقسام** (`card`/`card-header h5`) — كلُّها صارت
        إلى `assets/css/ems-profile.css` عبر `includes/profile_kit.php`.
        ولم يبقَ إلا شريطُ التبويباتِ: مكوّنٌ لا نظيرَ له في عُدّةِ البطاقةِ
        (بطاقةُ المعدةِ وحدَها من بين السبعِ تُقسّم على تبويبات). */ ?>
<style>
/* شريطُ تبويباتِ بطاقةِ المعدة — بتصميمِ تابات شاشةِ الحركةِ نفسِه */
.equipment-profile-page .ep-tabs{
  position:sticky; top:var(--topbar-height,60px); z-index:var(--z-sticky,10);
  display:flex; flex-wrap:wrap; gap:6px; margin-bottom:18px;
  background:var(--c-surface); border-bottom:2px solid var(--c-dae5f1, #dae5f1);
}
.equipment-profile-page .ep-tab{
  appearance:none; cursor:pointer; font-family:inherit;
  background:var(--c-f0f5fa, #f0f5fa); color:var(--c-0b4c8c, #0b4c8c);
  border:1px solid var(--c-dae5f1, #dae5f1); border-bottom:none;
  padding:11px 26px; border-radius:10px 10px 0 0;
  font-size:15px; font-weight:700;
  display:inline-flex; align-items:center; gap:8px;
  margin-bottom:-2px; transition:background .2s, color .2s;
}
.equipment-profile-page .ep-tab i{ font-size:14px; opacity:.9; }
.equipment-profile-page .ep-tab:hover{ background:var(--c-e3eef9, #e3eef9); color:var(--c-0b4c8c, #0b4c8c); }
.equipment-profile-page .ep-tab.is-active{
  background:var(--c-0b4c8c, #0b4c8c); color:var(--c-surface); border-color:var(--c-0b4c8c, #0b4c8c); box-shadow:none;
}
.equipment-profile-page .ep-tab-badge{
  font-size:12px; font-weight:700; min-width:20px; padding:1px 9px; border-radius:12px;
  display:inline-block; text-align:center; background:var(--c-rgba117614012, rgba(11,76,140,.12)); color:var(--c-0b4c8c, #0b4c8c);
}
.equipment-profile-page .ep-tab.is-active .ep-tab-badge{ background:var(--c-d4a017); color:var(--c-surface); }

/* اللوحات */
.equipment-profile-page .ep-tab-panel{ display:none; }
.equipment-profile-page .ep-tab-panel.is-active{ display:block; animation:epFade .25s ease; }
@keyframes epFade{ from{opacity:0; transform:translateY(5px);} to{opacity:1; transform:none;} }

/* نموذجُ اعتمادِ الكرتِ أسفلَ لوحِ الهوية */
.equipment-profile-page .ep-approve-card{ margin:0 0 14px; }
</style>

<div class="main equipment-profile-page ems-profile ems-unified-page-shell">
    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'بطاقة المعدة / الشاحنة';
    $header_icon    = 'fas fa-id-card';
    $header_actions = array(
        array('href' => 'add_drivers.php?equipment_id=' . intval($equipment_id), 'class' => 'add-btn', 'icon' => 'fas fa-user-cog', 'label' => 'إدارة المشغلين'),
        array('href' => 'equipments.php?edit=' . intval($equipment_id), 'class' => 'add-btn', 'icon' => 'fas fa-edit', 'label' => 'تعديل المعدة'),
    );
    $header_back = array('href' => 'equipments.php', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا سجل لهذه المعدة في هذا القسم بعد', 'سجل أول وثيقة أو تجهيز أو حدث من أزرار الإضافة في لوحة «الوثائق والسجل»');
    ?>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('equipment', 'نظرة عامة'); ?>

    <?php
    // حالة الكرت (حوكمة خفيفة) + الحالة التشغيلية
    $card_state     = isset($equipment['card_state']) ? $equipment['card_state'] : 'active';
    $card_is_active = ($card_state === 'active');
    $status_avail   = intval($equipment['status']) === 1;
    ?>
    <?php
    /* ══ لوحُ الهوية ═══════════════════════════════════════════════════════
       ◆ كان **نسخةً سابعةً** من لوحِ الهويةِ نفسِه — وأقربَها شبهًا بالمكوّن:
         `ep-hero` · `ep-hero-ic` · `ep-chips`/`ep-chip` · `ep-pill` ·
         `ep-facts`/`ep-fact` بمفتاحٍ وقيمة. البنيةُ ذاتُها بأسماءٍ أخرى —
         وهذا بعينِه ما يجعل النسخَ يتكاثر: كلُّ بطاقةٍ تعيد اختراعَ الشيءِ
         نفسِه لأنّ لا مكوّنَ تنادِيه.
       ◆ وحالتانِ لا واحدة: **الإتاحةُ** (متاحة/مشغولة) و**كرتُ المعدة**
         (معتمد/مسودة). فالإتاحةُ شارةُ الحالةِ لأنها وصفُ المعدةِ الآن،
         وحالُ الكرتِ رقيقةٌ لأنها وصفُ **المستندِ** لا المعدة. */
    echo ems_profile_hero(array(
        'name'   => $equipment['name'],
        'icon'   => 'fas fa-truck-monster',
        'status' => array(
            'text' => $status_avail ? 'متاحة' : 'مشغولة',
            'tone' => $status_avail ? 'ok' : 'warn',
            'icon' => $status_avail ? 'fas fa-circle-check' : 'fas fa-circle-dot',
        ),
        'chips'  => array(
            array('text' => $equipment['code'], 'icon' => 'fas fa-barcode', 'mono' => true),
            array('text' => $equipment['equipment_type_name'] ?: $equipment['type'], 'icon' => 'fas fa-layer-group'),
            array('text' => $equipment['supplier_name'], 'icon' => 'fas fa-truck'),
            array('text' => $card_is_active ? 'كرت معتمد' : 'كرت مسودة', 'icon' => 'fas fa-id-card'),
        ),
        'facts'  => array(
            array('label' => 'الموديل',        'value' => $equipment['model']),
            array('label' => 'سنة الصنع',      'value' => $equipment['manufacturing_year']),
            array('label' => 'رقم الهيكل',     'value' => $equipment['chassis_number']),
            array('label' => 'ساعات التشغيل',  'value' => number_format($hours_sum, 0)),
        ),
    ));
    ?>
    <?php if (!$card_is_active && !empty($can_edit)): ?>
        <?php /* الاعتمادُ فعلٌ كاتبٌ بـPOST — فيبقى نموذجًا بزرِّه المعتمَد،
                 ولا يُقحَم في شريطِ أفعالِ الرأسِ الذي بنيتُه روابط. */ ?>
        <?php echo ems_profile_note('كرت هذه المعدة ما يزال مسودة — لا يعتمد عليه حتى يعتمد.'); ?>
        <form method="post" action="approve_card.php" class="ep-approve-card" onsubmit="return confirm('اعتماد كرت هذه المعدة؟');">
            <?= csrf_field() ?>
            <input type="hidden" name="equipment_id" value="<?php echo intval($equipment_id); ?>">
            <input type="hidden" name="return" value="equipment_profile.php">
            <input type="hidden" name="return_id" value="<?php echo intval($equipment_id); ?>">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-circle-check"></i> اعتماد الكرت</button>
        </form>
    <?php endif; ?>

    <!-- ════════ شريط التبويبات ════════ -->
    <div class="ep-tabs" role="tablist">
        <button type="button" class="ep-tab is-active" data-ep-tab="overview"><i class="fas fa-circle-info"></i> نظرة عامة</button>
        <button type="button" class="ep-tab" data-ep-tab="operations"><i class="fas fa-diagram-project"></i> التشغيل <span class="ep-tab-badge"><?php echo intval($projects_count); ?></span></button>
        <button type="button" class="ep-tab" data-ep-tab="maintenance"><i class="fas fa-wrench"></i> الصيانة والتفتيش <span class="ep-tab-badge"><?php echo intval($mnt_total + $ins_total + $pln_total); ?></span></button>
        <button type="button" class="ep-tab" data-ep-tab="records"><i class="fas fa-folder-open"></i> الوثائق والسجل <span class="ep-tab-badge"><?php echo intval(count($compliance_rows) + count($protection_rows) + count($component_rows)); ?></span></button>
        <button type="button" class="ep-tab" data-ep-tab="movements"><i class="fas fa-timeline"></i> تحركات الآلية <span class="ep-tab-badge"><?php echo intval(count($history_rows)); ?></span></button>
    </div>

    <div class="ep-panels">
    <!-- ════════ لوحة: نظرة عامة ════════ -->
    <div class="ep-tab-panel is-active" id="tab-overview">

    <?php
    /* ── قيمُ الهويةِ والمصدرِ والعدّاد ──────────────────────────────────
       ◆ كانت تُبنى **مهرَّبةً وبـ«—» مخبوزةً فيها** لأن الوسمَ كان يدويًّا.
         وشبكةُ حقائقِ المكوّنِ تُهرِّب بنفسِها وتُعلن الغيابَ بصنفِه — فتمريرُ
         نصٍّ مهرَّبٍ إليها يُهرِّبه مرتَين، و«—» يصير **قيمةً حاضرةً** فيضيع
         تمييزُ الغائب. فالقيمُ هنا خامٌ، والغيابُ فراغٌ يعرفه المكوّن.
       ◆ والوحدةُ الفارغةُ ليست وحدة: `trim` يمنع «12 » بمسافةٍ معلَّقةٍ حين
         لا وحدةَ مسجَّلة. */
    $ep_val = function ($v, $unit = '') use ($equipment) {
        $v = ($v === null) ? '' : trim((string) $v);
        if ($v === '') { return ''; }
        $u = ($unit === null) ? '' : trim((string) $unit);
        return $u === '' ? $v : ($v . ' ' . $u);
    };
    $capacity_val = $ep_val($equipment['capacity'] ?? '', $equipment['capacity_uom'] ?? '');
    $acq_val      = $ep_val($equipment['acquisition_cost'] ?? '', $equipment['acquisition_currency'] ?? '');
    $meter_val    = $ep_val($equipment['opening_meter'] ?? '', $equipment['meter_uom'] ?? '');
    ?>
    <?php
    /* إحدى عشرةَ حقيقةً كانت `profile-card` بـ`label` وقيمةٍ — شبكةُ حقائقَ
       مبنيةٌ يدويًّا. والغيابُ فيها كان «—» **نصًّا** لا يُميَّز من قيمةٍ
       حقيقية؛ وشبكةُ المكوّنِ تُعلنه بصنفِ غيابٍ ظاهر. */
    echo ems_profile_section_open(array('title' => 'الهوية والمصدر والعداد', 'icon' => 'fas fa-id-badge'));
    echo ems_profile_facts(array(
        array('label' => 'الفئة التشغيلية', 'value' => $equipment['operating_category']),
        array('label' => 'بلد الصنع',       'value' => $equipment['origin_country']),
        array('label' => 'رقم الموتور',     'value' => $equipment['engine_no']),
        array('label' => 'رقم اللوحة',      'value' => $equipment['plate_no']),
        array('label' => 'السعة/القدرة',    'value' => $capacity_val),
        array('label' => 'المقاسات الفنية', 'value' => $equipment['dimensions']),
        array('label' => 'نوع المصدر',      'value' => $equipment['source_type']),
        array('label' => 'تاريخ الدخول',    'value' => $equipment['entry_date']),
        array('label' => 'تكلفة الشراء',    'value' => $acq_val),
        array('label' => 'العداد الافتتاحي', 'value' => $meter_val),
        array('label' => 'مصدر العداد',    'value' => $equipment['meter_source']),
    ), true);
    echo ems_profile_section_close();

    /* ستةُ مؤشراتٍ كانت `profile-card` بـ`kpi` و`label` — شريطُ مؤشراتٍ
       مبنيٌّ يدويًّا للمرةِ السابعة. */
    echo ems_profile_section_open(array('title' => 'ملخص التشغيل', 'icon' => 'fas fa-gauge-high'));
    echo ems_profile_stats(array(
        array('value' => $operations_count, 'label' => 'إجمالي عمليات التشغيل'),
        array('value' => $active_operations, 'label' => 'عمليات نشطة', 'tone' => $active_operations > 0 ? 'ok' : 'muted'),
        array('value' => $projects_count,   'label' => 'المشاريع المرتبطة'),
        array('value' => $drivers_count,    'label' => 'المشغلون النشطون'),
        array('value' => number_format($hours_sum, 0),   'label' => 'ساعات التشغيل',  'unit' => 'ساعة'),
        array('value' => number_format($standby_sum, 0), 'label' => 'ساعات الاستعداد', 'unit' => 'ساعة'),
    ));
    echo ems_profile_section_close();
    ?>
    </div><!-- /#tab-overview -->

    <!-- ════════ لوحة: التشغيل ════════ -->
    <div class="ep-tab-panel" id="tab-operations">
    <?php echo ems_profile_section_open(array('title' => 'المشاريع المرتبطة بالمعدة', 'icon' => 'fas fa-diagram-project')); ?>
            <table id="equipmentProjectsTable" class="display ep-w100">
                <thead><tr><th>المشروع</th><th>كود المشروع</th><th>الساعات</th><th>عدد الورديات</th></tr></thead>
                <tbody>
                    <?php if ($projects_list): foreach ($projects_list as $row): ?>
                        <tr>
                            <td><?php if (!empty($row['id'])): ?><a href="../Projects/project_profile.php?id=<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['name']); ?></a><?php else: ?>غير محدد<?php endif; ?></td>
                            <td><?php echo htmlspecialchars($row['project_code'] ?: '-'); ?></td>
                            <td><?php echo number_format($row['total_hours'], 0); ?></td>
                            <td><?php echo intval($row['shifts_count']); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
    <?php echo ems_profile_section_close(); ?>

    <?php echo ems_profile_section_open(array('title' => 'آخر المشغلين المرتبطين', 'icon' => 'fas fa-users')); ?>
            <table id="equipmentDriversTable" class="display ep-w100">
                <thead><tr><th>المشغل</th><th>تاريخ البداية</th><th>تاريخ النهاية</th><th>الحالة</th></tr></thead>
                <tbody>
                    <?php if ($drivers_list): foreach ($drivers_list as $row): ?>
                        <tr>
                            <td><a href="../Employees/employee_profile.php?id=<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['name']); ?></a></td>
                            <td><?php echo htmlspecialchars($row['start_date']); ?></td>
                            <td><?php echo htmlspecialchars(ems_format_open_end($row['end_date'])); ?></td>
                            <td><?php echo intval($row['status']) === 1 ? 'نشط' : 'متوقف'; ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
    <?php echo ems_profile_section_close(); ?>

    </div><!-- /#tab-operations -->

    <!-- ════════ لوحة: الصيانة والتفتيش ════════ -->
    <div class="ep-tab-panel" id="tab-maintenance">
    <!-- قسم الصيانة (مؤشرات + أوامر) -->
    <?php
    echo ems_profile_section_open(array(
        'id'    => 'sec-maintenance',
        'title' => 'الصيانة — المؤشرات وأوامر الصيانة',
        'icon'  => 'fas fa-wrench',
    ));
    /* المقاييسُ الثلاثةُ الأخيرةُ قد تكون **غيرَ محسوبةٍ** (null) لا صفرًا —
       فتُمرَّر فراغًا ليُعلنها المكوّنُ غيابًا، ولا تُلفَّق صفرًا كاذبًا. */
    echo ems_profile_stats(array(
        array('value' => intval($mnt_total),  'label' => 'إجمالي أوامر الصيانة'),
        array('value' => intval($mnt_open),   'label' => 'أوامر مفتوحة', 'tone' => $mnt_open > 0 ? 'warn' : 'muted'),
        array('value' => intval($mnt_failures), 'label' => 'أعطال (من بلاغ)', 'tone' => $mnt_failures > 0 ? 'danger' : 'muted'),
        array('value' => number_format($mnt_downtime, 1), 'label' => 'ساعات التوقف', 'unit' => 'ساعة'),
        array('value' => number_format($mnt_cost, 0),     'label' => 'إجمالي تكلفة الصيانة', 'variant' => 'money'),
        array('value' => $mnt_last !== null && $mnt_last !== '' ? $mnt_last : '', 'label' => 'آخر صيانة', 'variant' => 'date'),
        array('value' => $mnt_mtbf !== null ? number_format($mnt_mtbf, 1) : '', 'label' => 'MTBF (ساعة/عطل)'),
        array('value' => $mnt_mttr !== null ? number_format($mnt_mttr, 1) : '', 'label' => 'MTTR (ساعة/أمر)'),
        array('value' => $mnt_avail !== null ? number_format($mnt_avail, 1) . '%' : '', 'label' => 'نسبة الجاهزية',
              'tone' => ($mnt_avail !== null && $mnt_avail >= 90) ? 'ok' : (($mnt_avail !== null) ? 'warn' : 'muted')),
    ));
    ?>

            <div class="table-container ep-mt12">
                <table class="display ep-w100">
                    <thead><tr><th>المرجع</th><th>المصدر</th><th>النوع</th><th>الحالة</th><th>التوقف (ساعة)</th><th>التكلفة</th><th>الإغلاق</th></tr></thead>
                    <tbody>
                        <?php if (empty($mnt_orders)): ?>
                            <tr><td colspan="7" class="ep-empty-cell">لا توجد أوامر صيانة لهذه المعدة</td></tr>
                        <?php else: foreach ($mnt_orders as $mo): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars((string) $mo['code']); ?></strong></td>
                                <td><?php echo htmlspecialchars((string) $mo['source']); ?></td>
                                <td><?php echo htmlspecialchars((string) ($mo['maint_type'] ?: '—')); ?></td>
                                <td><span class="action-btn"><?php echo htmlspecialchars((string) $mo['state']); ?></span></td>
                                <td><?php echo number_format((float) $mo['downtime_hours'], 1); ?></td>
                                <td><?php echo number_format((float) $mo['total_cost'], 2); ?></td>
                                <td><?php echo htmlspecialchars((string) ($mo['closed_at'] ?: '—')); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
    <?php echo ems_profile_section_close(); ?>

    <!-- ════════════════ قسم التفتيش الفني — مرئي لكل من يفتح الكرت ════════════════ -->
    <?php
    echo ems_profile_section_open(array(
        'id'    => 'sec-inspections',
        'title' => 'التفتيش الفني — المؤشرات والتفتيشات',
        'icon'  => 'fas fa-clipboard-check',
    ));
    echo ems_profile_stats(array(
        array('value' => intval($ins_total), 'label' => 'إجمالي التفتيشات'),
        array('value' => intval($ins_done),  'label' => 'مكتملة',        'tone' => 'ok'),
        array('value' => intval($ins_open),  'label' => 'مجدولة/مفتوحة', 'tone' => 'warn'),
        array('value' => intval($ins_critical), 'label' => 'ملاحظات حرجة',
              'tone' => $ins_critical > 0 ? 'danger' : 'muted'),
        array('value' => ($ins_last !== null && $ins_last !== '') ? $ins_last : '',
              'label' => 'آخر تفتيش', 'variant' => 'date'),
    ));
    ?>

            <div class="table-container ep-mt12">
                <table class="display ep-ins-table ep-w100">
                    <thead><tr><th>المرجع</th><th>النوع</th><th>الفاحص</th><th>التاريخ</th><th>الدرجة</th><th>الجاهزية</th><th>الملاحظات</th><th>الحالة</th><th>عرض</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              </tr></thead>
                    <tbody>
                        <?php if (empty($ins_rows)): ?>
                            <tr><td colspan="9" class="ep-empty-cell">لا توجد تفتيشات لهذه المعدة</td></tr>
                        <?php else: foreach ($ins_rows as $ir):
                            $ir_lines = isset($ins_lines_map[intval($ir['id'])]) ? $ins_lines_map[intval($ir['id'])] : array();
                            $ir_date  = $ir['completed_at'] ?: ($ir['scheduled_date'] ?: '');
                            // سمات البيانات للنافذة الموحّدة (EmsDetailsModal)
                            $da =
                                "data-id='"        . intval($ir['id']) . "' " .
                                "data-code='"      . htmlspecialchars((string) $ir['code'], ENT_QUOTES) . "' " .
                                "data-type='"      . htmlspecialchars((string) ($ir['inspection_type'] ?? ''), ENT_QUOTES) . "' " .
                                "data-inspector='" . htmlspecialchars((string) ($ir['inspector_name'] ?? ''), ENT_QUOTES) . "' " .
                                "data-project='"   . htmlspecialchars((string) ($ir['project_name'] ?? ''), ENT_QUOTES) . "' " .
                                "data-scheduled='" . htmlspecialchars((string) ($ir['scheduled_date'] ?? ''), ENT_QUOTES) . "' " .
                                "data-completed='" . htmlspecialchars((string) ($ir['completed_at'] ?? ''), ENT_QUOTES) . "' " .
                                "data-score='"     . htmlspecialchars((string) ($ir['score'] ?? ''), ENT_QUOTES) . "' " .
                                "data-overall='"   . htmlspecialchars((string) ($ir['overall_result'] ?? ''), ENT_QUOTES) . "' " .
                                "data-readiness='" . htmlspecialchars((string) ($ir['tech_readiness_state'] ?? ''), ENT_QUOTES) . "' " .
                                "data-eqcond='"    . htmlspecialchars((string) ($ir['equipment_condition'] ?? ''), ENT_QUOTES) . "' " .
                                "data-engcond='"   . htmlspecialchars((string) ($ir['engine_condition'] ?? ''), ENT_QUOTES) . "' " .
                                "data-notes='"     . htmlspecialchars((string) ($ir['notes'] ?? ''), ENT_QUOTES) . "' " .
                                "data-state='"     . htmlspecialchars((string) $ir['state'], ENT_QUOTES) . "'";
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars((string) $ir['code']); ?></strong></td>
                                <td><?php echo htmlspecialchars((string) ($ir['inspection_type'] ?: '—')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($ir['inspector_name'] ?: '—')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($ir_date ?: '—')); ?></td>
                                <td data-order="<?php echo $ir['score'] !== null && $ir['score'] !== '' ? intval($ir['score']) : -1; ?>"><?php echo ems_ins_score_chip($ir['score'] ?? null); ?></td>
                                <td><?php echo ems_ins_ready_chip($ir['tech_readiness_state'] ?? ''); ?></td>
                                <td><?php echo ems_ins_findings_badge($ir_lines, $INS_GOOD, $INS_NOTE, $INS_CRIT, $INS_NA); ?></td>
                                <td><?php echo ems_ins_state_chip($ir['state']); ?></td>
                                <td><a href="javascript:void(0)" class="action-btn view ep-ins-view" <?php echo $da; ?> title="عرض تفاصيل التفتيش"><i class="fas fa-eye"></i></a></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
    <?php echo ems_profile_section_close(); ?>
    <script>window.EP_INS_LINES = <?php echo json_encode($ins_lines_map, JSON_UNESCAPED_UNICODE); ?>;</script>

    <!-- ════════════════ قسم الصيانة الوقائية — مرئي لكل من يفتح الكرت ════════════════ -->
    <?php
    echo ems_profile_section_open(array(
        'id'    => 'sec-preventive',
        'title' => 'الصيانة الوقائية — الخطط المسندة للمعدة',
        'icon'  => 'fas fa-calendar-check',
    ));
    echo ems_profile_stats(array(
        array('value' => intval($pln_total),  'label' => 'إجمالي الخطط'),
        array('value' => intval($pln_active), 'label' => 'نشطة', 'tone' => $pln_active > 0 ? 'ok' : 'muted'),
        array('value' => intval($pln_due),    'label' => 'مستحقة الآن', 'tone' => $pln_due > 0 ? 'warn' : 'muted'),
        array('value' => ($pln_last !== null && $pln_last !== '') ? $pln_last : '', 'label' => 'آخر تنفيذ', 'variant' => 'date'),
        array('value' => ($pln_next !== null && $pln_next !== '') ? $pln_next : '', 'label' => 'الاستحقاق القادم', 'variant' => 'date'),
    ));
    ?>

            <div class="table-container ep-mt12">
                <table class="display ep-w100">
                    <thead><tr><th>المرجع</th><th>الخطة</th><th>الأساس</th><th>الفاصل</th><th>آخر تنفيذ</th><th>الاستحقاق القادم</th><th>الحالة</th></tr></thead>
                    <tbody>
                        <?php if (empty($pln_rows)): ?>
                            <tr><td colspan="7" class="ep-empty-cell">لا توجد خطط وقائية لهذه المعدة</td></tr>
                        <?php else: foreach ($pln_rows as $pr):
                            $pr_due = ($pr['trigger_basis'] === 'ساعات')
                                ? (($pr['next_due_meter'] !== null && $pr['next_due_meter'] !== '') ? $pr['next_due_meter'] : '—')
                                : (!empty($pr['next_due_date']) ? $pr['next_due_date'] : '—');
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars((string) $pr['code']); ?></strong></td>
                                <td><?php echo htmlspecialchars((string) $pr['name']); ?></td>
                                <td><?php echo htmlspecialchars((string) $pr['trigger_basis']); ?></td>
                                <td><?php echo htmlspecialchars((string) ($pr['interval_value'] ?: '—')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($pr['last_done_date'] ?: '—')); ?></td>
                                <td><?php echo htmlspecialchars((string) $pr_due); ?></td>
                                <td><span class="action-btn"><?php echo htmlspecialchars((string) $pr['state']); ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
    <?php echo ems_profile_section_close(); ?>

    </div><!-- /#tab-maintenance -->

    <!-- ════════ لوحة: الوثائق والسجل ════════ -->
    <div class="ep-tab-panel" id="tab-records">
    <!-- جداول الأبناء: الوثائق · الحماية · المكوّنات · السجل -->
    <?php if ($critical_expired > 0): ?>
        <div class="success-message is-error ep-critical-alert">
            <i class="fa-solid fa-triangle-exclamation"></i>
            تحذير حرج: توجد <?= (int) $critical_expired; ?> وثيقة حرجة منتهية الصلاحية لهذه المعدة. (سيربط لاحقا بمنع التشغيل/التخصيص)
        </div>
    <?php endif; ?>

    <!-- (1) الوثائق الرسمية -->
    <?php
    /* شارتا الانتهاءِ كانتا `status-inactive` و`badge-busy` — صنفانِ من لغةٍ
       أخرى داخلَ عنوانِ القسم. صارتا شارتَي نغمةٍ في خانةِ `meta`، والزرُّ في
       خانةِ `actions` التي أُضيفت للمكوّنِ لهذا الغرضِ بعينِه. */
    $docs_meta = array();
    if ($docs_expired) { $docs_meta[] = ems_profile_badge('منتهية: ' . (int) $docs_expired, 'danger'); }
    if ($docs_soon)    { $docs_meta[] = ems_profile_badge('قاربت: ' . (int) $docs_soon, 'warn'); }
    echo ems_profile_section_open(array(
        'id'      => 'sec-docs',
        'title'   => 'الوثائق الرسمية',
        'icon'    => 'fas fa-file-contract',
        'actions' => implode(' ', $docs_meta)
                   . ($can_edit_card ? '<button type="button" class="btn btn-primary btn-sm" onclick="emsToggle(\'add-docs\')"><i class="fas fa-plus"></i> إضافة وثيقة</button>' : ''),
    ));
    ?>
            <?php if ($can_edit_card): ?>
            <form id="add-docs" class="child-add-form ems-form ep-mb14 is-hidden" method="post" action="equipment_child_save.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
                <input type="hidden" name="entity" value="compliance"><input type="hidden" name="action" value="add"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>">
                <div class="form-grid">
                    <div><label for="emsf_98_e8d4a">نوع الوثيقة *</label><select name="doc_type" required id="emsf_98_e8d4a"><option value="">-- اختر --</option><?php foreach ($DOC_TYPES as $o) echo '<option>' . $ee($o) . '</option>'; ?></select></div>
                    <div><label for="emsf_99_7d40f">الرقم/المرجع</label><input type="text" name="reference" id="emsf_99_7d40f"></div>
                    <div><label for="emsf_100_2cab9">تاريخ الإصدار</label><input type="date" name="issue_date" id="emsf_100_2cab9"></div>
                    <div><label for="emsf_101_a2db1">تاريخ الانتهاء</label><input type="date" name="expiry_date" id="emsf_101_a2db1"></div>
                    <div><label for="emsf_102_9c6af">مرفق (صورة/PDF)</label><input type="file" name="attachment" accept="image/*,application/pdf" id="emsf_102_9c6af"></div>
                    <div class="ep-check-row"><input type="checkbox" name="is_critical" id="doc_crit" value="1"><label for="doc_crit" class="ep-m0">وثيقة حرجة</label></div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm ep-mt10"><i class="fa-solid fa-save"></i> حفظ</button>
            </form>
            <?php endif; ?>
            <div class="table-container">
                <table class="display ep-w100">
                    <thead><tr><th>النوع</th><th>المرجع</th><th>الإصدار</th><th>الانتهاء</th><th>حرجة</th><th>الحالة</th><th>مرفق</th><?php if ($can_edit_card): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                        <?php if (empty($compliance_rows)): ?>
                            <tr><td colspan="<?= $can_edit_card ? 8 : 7; ?>" class="ep-empty-cell">لا توجد وثائق مسجلة</td></tr>
                        <?php else: foreach ($compliance_rows as $cr): $st = ems_doc_status($cr['expiry_date'] ?? null, $today_ts, $DOC_ALERT_DAYS); ?>
                            <tr>
                                <td><?= $ee($cr['doc_type']); ?></td>
                                <td><?= $ee($cr['reference'] ?: '—'); ?></td>
                                <td><?= $ee($cr['issue_date'] ?: '—'); ?></td>
                                <td><?= $ee($cr['expiry_date'] ?: '—'); ?></td>
                                <td><?= !empty($cr['is_critical']) ? '<span class="status-inactive">حرجة</span>' : '—'; ?></td>
                                <td><?php echo $st['cls'] ? "<span class='{$st['cls']}'>" . $ee($st['label']) . "</span>" : $ee($st['label']); ?></td>
                                <td><?php if (!empty($cr['attachment_path'])): ?><a href="fleet_file.php?f=<?= $ee(basename($cr['attachment_path'])); ?>" target="_blank"><i class="fas fa-paperclip"></i> عرض</a><?php else: ?>—<?php endif; ?></td>
                                <?php if ($can_edit_card): ?><td><form method="post" action="equipment_child_save.php" onsubmit="return confirm('حذف هذه الوثيقة؟');" class="ep-m0">
        <?= csrf_field() ?><input type="hidden" name="entity" value="compliance"><input type="hidden" name="action" value="delete"><input type="hidden" name="row_id" value="<?= (int) $cr['id']; ?>"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>"><button class="action-btn delete" title="حذف"><i class="fa-solid fa-trash"></i></button></form></td><?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
    <?php echo ems_profile_section_close(); ?>

    <!-- (2) تجهيزات الحماية -->
    <?php echo ems_profile_section_open(array(
        'id'      => 'sec-protection',
        'title'   => 'تجهيزات الحماية',
        'icon'    => 'fas fa-shield-halved',
        'actions' => $can_edit_card ? '<button type="button" class="btn btn-primary btn-sm" onclick="emsToggle(\'add-prot\')"><i class="fas fa-plus"></i> إضافة تجهيز</button>' : '',
    )); ?>
            <?php if ($can_edit_card): ?>
            <form id="add-prot" class="child-add-form ems-form ep-mb14 is-hidden" method="post" action="equipment_child_save.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
                <input type="hidden" name="entity" value="protection"><input type="hidden" name="action" value="add"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>">
                <div class="form-grid">
                    <div><label for="ep_prot_type">نوع الحماية *</label><select name="protection_type" required id="ep_prot_type"><option value="">-- اختر --</option><?php foreach ($PROTECTION_TYPES as $o) echo '<option>' . $ee($o) . '</option>'; ?></select></div>
                    <div><label for="emsf_103_b2238">الوصف</label><input type="text" name="description" id="emsf_103_b2238"></div>
                    <div><label for="emsf_104_d3982">تاريخ التركيب/البدء</label><input type="date" name="start_date" id="emsf_104_d3982"></div>
                    <div><label for="emsf_105_2422f">التكلفة</label><input type="number" step="0.01" name="cost" id="emsf_105_2422f"></div>
                    <div><label for="emsf_106_132b7">الحالة</label><select name="state" id="emsf_106_132b7"><option value="">-- اختر --</option><?php foreach ($PROTECTION_STATES as $o) echo '<option>' . $ee($o) . '</option>'; ?></select></div>
                    <div><label for="emsf_107_788c4">تاريخ التجديد</label><input type="date" name="renewal_date" id="emsf_107_788c4"></div>
                    <div><label for="emsf_108_fcdfc">المنفذ/المورد</label><input type="text" name="partner_name" autocomplete="off" placeholder="اكتب اسم المنفذ/المورد (إدخال يدوي)" id="emsf_108_fcdfc"></div>
                    <div><label for="emsf_109_d5915">مرتبط بوثيقة (للتأمين)</label><select name="compliance_id" id="emsf_109_d5915"><option value="">-- بدون --</option><?php foreach ($compliance_rows as $cr) echo '<option value="' . (int) $cr['id'] . '">' . $ee($cr['doc_type'] . ($cr['reference'] ? ' — ' . $cr['reference'] : '')) . '</option>'; ?></select></div>
                    <div><label for="emsf_110_ee635">مرفق</label><input type="file" name="attachment" accept="image/*,application/pdf" id="emsf_110_ee635"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm ep-mt10"><i class="fa-solid fa-save"></i> حفظ</button>
            </form>
            <?php endif; ?>
            <div class="table-container">
                <table class="display ep-w100">
                    <thead><tr><th>النوع</th><th>الوصف</th><th>البدء</th><th>التكلفة</th><th>الحالة</th><th>التجديد</th><th>المنفذ</th><th>مرفق</th><?php if ($can_edit_card): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                        <?php if (empty($protection_rows)): ?>
                            <tr><td colspan="<?= $can_edit_card ? 9 : 8; ?>" class="ep-empty-cell">لا توجد تجهيزات مسجلة</td></tr>
                        <?php else: foreach ($protection_rows as $pr): $needs = ($pr['state'] ?? '') === 'يحتاج تجديداً'; ?>
                            <tr>
                                <td><?= $ee($pr['protection_type']); ?></td>
                                <td><?= $ee($pr['description'] ?: '—'); ?></td>
                                <td><?= $ee($pr['start_date'] ?: '—'); ?></td>
                                <td><?= $pr['cost'] !== null && $pr['cost'] !== '' ? $ee($pr['cost']) : '—'; ?></td>
                                <td><?php echo $needs ? '<span class="badge-busy">' . $ee($pr['state']) . '</span>' : $ee($pr['state'] ?: '—'); ?></td>
                                <td><?= $ee($pr['renewal_date'] ?: '—'); ?></td>
                                <td><?= $ee($pr['partner_name'] ?: '—'); ?></td>
                                <td><?php if (!empty($pr['attachment_path'])): ?><a href="fleet_file.php?f=<?= $ee(basename($pr['attachment_path'])); ?>" target="_blank"><i class="fas fa-paperclip"></i> عرض</a><?php else: ?>—<?php endif; ?></td>
                                <?php if ($can_edit_card): ?><td><form method="post" action="equipment_child_save.php" onsubmit="return confirm('حذف هذا التجهيز؟');" class="ep-m0">
        <?= csrf_field() ?><input type="hidden" name="entity" value="protection"><input type="hidden" name="action" value="delete"><input type="hidden" name="row_id" value="<?= (int) $pr['id']; ?>"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>"><button class="action-btn delete" title="حذف"><i class="fa-solid fa-trash"></i></button></form></td><?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
    <?php echo ems_profile_section_close(); ?>

    <!-- (3) المكوّنات الكبرى -->
    <?php echo ems_profile_section_open(array(
        'id'      => 'sec-components',
        'title'   => 'المكونات الكبرى',
        'icon'    => 'fas fa-gears',
        'actions' => $can_edit_card ? '<button type="button" class="btn btn-primary btn-sm" onclick="emsToggle(\'add-comp\')"><i class="fas fa-plus"></i> إضافة مكون</button>' : '',
    )); ?>
            <?php if ($can_edit_card): ?>
            <form id="add-comp" class="child-add-form ems-form ep-mb14 is-hidden" method="post" action="equipment_child_save.php">
        <?= csrf_field() ?>
                <input type="hidden" name="entity" value="component"><input type="hidden" name="action" value="add"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>">
                <div class="form-grid">
                    <div><label for="emsf_111_0499d">نوع المكون *</label><select name="component_type" required id="emsf_111_0499d"><option value="">-- اختر --</option><?php foreach ($COMPONENT_TYPES as $o) echo '<option>' . $ee($o) . '</option>'; ?></select></div>
                    <div><label for="emsf_112_4aed5">الرقم التسلسلي</label><input type="text" name="serial_no" id="emsf_112_4aed5"></div>
                    <div><label for="emsf_113_11c52">تاريخ التركيب</label><input type="date" name="install_date" id="emsf_113_11c52"></div>
                    <div class="ep-check-row"><input type="checkbox" name="is_current" id="comp_cur" value="1" checked><label for="comp_cur" class="ep-m0">مركب حاليا</label></div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm ep-mt10"><i class="fa-solid fa-save"></i> حفظ</button>
            </form>
            <?php endif; ?>
            <div class="table-container">
                <table class="display ep-w100">
                    <thead><tr><th>النوع</th><th>الرقم التسلسلي</th><th>التركيب</th><th>حالي؟</th><th>الاستبدال</th><th>ساعات المكون</th><th>مرات الاستبدال</th><?php if ($can_edit_card): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                        <?php if (empty($component_rows)): ?>
                            <tr><td colspan="<?= $can_edit_card ? 8 : 7; ?>" class="ep-empty-cell">لا توجد مكونات مسجلة</td></tr>
                        <?php else: foreach ($component_rows as $cm): ?>
                            <tr>
                                <td><?= $ee($cm['component_type']); ?></td>
                                <td><?= $ee($cm['serial_no'] ?: '—'); ?></td>
                                <td><?= $ee($cm['install_date'] ?: '—'); ?></td>
                                <td><?= !empty($cm['is_current']) ? '<span class="status-active">نعم</span>' : 'لا'; ?></td>
                                <td class="ep-later">لاحقا</td>
                                <td class="ep-later">لاحقا</td>
                                <td class="ep-later">لاحقا</td>
                                <?php if ($can_edit_card): ?><td><form method="post" action="equipment_child_save.php" onsubmit="return confirm('حذف هذا المكون؟');" class="ep-m0">
        <?= csrf_field() ?><input type="hidden" name="entity" value="component"><input type="hidden" name="action" value="delete"><input type="hidden" name="row_id" value="<?= (int) $cm['id']; ?>"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>"><button class="action-btn delete" title="حذف"><i class="fa-solid fa-trash"></i></button></form></td><?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
    <?php echo ems_profile_section_close(); ?>

    <!-- (4) سجل تاريخ المعدة (إدراج فقط) -->
    <?php echo ems_profile_section_open(array(
        'id'      => 'sec-history',
        'title'   => 'سجل تاريخ المعدة',
        'icon'    => 'fas fa-timeline',
        'actions' => $can_edit_card ? '<button type="button" class="btn btn-primary btn-sm" onclick="emsToggle(\'add-hist\')"><i class="fas fa-plus"></i> إضافة حدث يدوي</button>' : '',
    )); ?>
            <?php if ($can_edit_card): ?>
            <form id="add-hist" class="child-add-form ems-form ep-mb14 is-hidden" method="post" action="equipment_child_save.php">
        <?= csrf_field() ?>
                <input type="hidden" name="entity" value="history"><input type="hidden" name="action" value="add"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>">
                <div class="form-grid">
                    <div><label for="ep_hist_event">نوع الحدث *</label><select name="event_type" required id="ep_hist_event"><option value="">-- اختر --</option><?php foreach ($EVENT_TYPES as $o) echo '<option>' . $ee($o) . '</option>'; ?></select></div>
                    <div><label for="emsf_114_ac19e">التاريخ والوقت *</label><input type="datetime-local" name="event_date" required id="emsf_114_ac19e" value="<?= date('Y-m-d\TH:i'); ?>"></div>
                    <div><label for="emsf_115_0d700">الموقع</label><input type="text" name="site_id" id="emsf_115_0d700"></div>
                    <div><label for="emsf_116_87147">تاريخ دخول/خروج</label><input type="date" name="in_out_date" id="emsf_116_87147"></div>
                    <div class="ep-col-span"><label for="emsf_117_53545">ملاحظة</label><input type="text" name="note" id="emsf_117_53545"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm ep-mt10"><i class="fa-solid fa-save"></i> تسجيل</button>
            </form>
            <?php endif; ?>
            <?php if (empty($history_rows)): ?>
                <div class="ep-empty-note">لا توجد أحداث مسجلة</div>
            <?php else: ?>
                <ul class="ems-timeline">
                    <?php foreach ($history_rows as $h): ?>
                        <li>
                            <span class="ems-tl-dot"></span>
                            <div class="ems-tl-body">
                                <div><strong><?= $ee($h['event_type']); ?></strong>
                                    <span class="ep-hist-date"><?= $ee($h['event_date']); ?></span></div>
                                <div class="ep-hist-body">
                                    <?php
                                    $bits = [];
                                    if (!empty($h['project_name'])) $bits[] = 'المشروع: ' . $ee($h['project_name']);
                                    if (!empty($h['site_id'])) $bits[] = 'الموقع: ' . $ee($h['site_id']);
                                    if (!empty($h['in_out_date'])) $bits[] = 'دخول/خروج: ' . $ee($h['in_out_date']);
                                    if (!empty($h['note'])) $bits[] = $ee($h['note']);
                                    echo implode(' · ', $bits) ?: '—';
                                    ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
    <?php echo ems_profile_section_close(); ?>
    </div><!-- /#tab-records -->

    <!-- ════════ لوحة: تحركات الآلية ════════ -->
    <div class="ep-tab-panel" id="tab-movements">
        <?php echo ems_profile_section_open(array('title' => 'تحركات الآلية', 'icon' => 'fas fa-timeline')); ?>
                <?php if (empty($history_rows)): ?>
                    <p class="ep-empty-note">لا توجد تحركات مسجلة بعد.</p>
                <?php else: ?>
                    <div class="ep-xscroll">
                        <table id="equipmentMovementsTable" class="ep-movements-table no-datatable ep-w100" data-order='[[0,"desc"]]' data-page-length="25" data-state-save="false">
                            <thead><tr>
                                <th>التاريخ</th><th>الزمن المستغرق</th><th>الحدث</th><th>من</th><th>إلى</th>
                                <th>المشروع</th><th>بواسطة</th><th>ملاحظة</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($history_rows as $h): ?>
                                <?php
                                $ev = (string) ($h['event_type'] ?? '');
                                $ev_map = ['إضافة للنظام' => 'sys', 'إضافة لمشروع' => 'proj', 'تغيير وردية' => 'shift', 'تغيير حالة' => 'state', 'إسناد للصيانة' => 'maint', 'عودة من الصيانة' => 'back', 'إنهاء تشغيل' => 'end'];
                                $ev_cls = $ev_map[$ev] ?? 'def';
                                ?>
                                <tr>
                                    <td data-order="<?= isset($h['event_date']) ? (int) strtotime((string) $h['event_date']) : 0; ?>"><?= $ee($h['event_date'] ?? '-'); ?></td>
                                    <td class="ep-elapsed" data-order="<?= ($h['_elapsed_seconds'] === null) ? -1 : (int) $h['_elapsed_seconds']; ?>"><?= $ee($h['_elapsed_label'] ?? '—'); ?></td>
                                    <td><span class="ep-ev ep-ev-<?= $ev_cls; ?>"><?= $ee($ev !== '' ? $ev : '-'); ?></span></td>
                                    <td><?= $ee(($h['from_value'] ?? '') !== '' ? $h['from_value'] : '-'); ?></td>
                                    <td><?= $ee(($h['to_value'] ?? '') !== '' ? $h['to_value'] : '-'); ?></td>
                                    <td><?= $ee(($h['project_name'] ?? '') !== '' ? $h['project_name'] : '-'); ?></td>
                                    <td><?= $ee(($h['created_by_name'] ?? '') !== '' ? $h['created_by_name'] : '-'); ?></td>
                                    <td class="ep-note"><?= $ee($h['note'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
        <?php echo ems_profile_section_close(); ?>
    </div><!-- /#tab-movements -->
    </div><!-- /.ep-panels -->

    <?php /* NAV-01 §5-④: البلاغاتُ المتصلة — تاريخُ البلاغاتِ جزءٌ من تاريخِ
             الكيان. نُقلت داخلَ الغلافِ بعد أن كانت خلفَ إغلاقِه. وهي أسفلَ
             لوحاتِ التبويبِ لا داخلَ إحداها: تخصُّ المعدةَ كلَّها لا تبويبًا. */
    $rt_kind = 'equipment'; $rt_ref = $equipment_id;
    include __DIR__ . '/../includes/related_tickets_tab.php'; ?>

</div>

<style>
    .ems-timeline { list-style:none; margin:0; padding:0; position:relative; }
    .ems-timeline:before { content:''; position:absolute; right:7px; top:4px; bottom:4px; width:2px; background:var(--c-e3e3e3, #e3e3e3); }
    .ems-timeline li { position:relative; padding:0 26px 16px 0; }
    .ems-tl-dot { position:absolute; right:1px; top:4px; width:14px; height:14px; border-radius:50%; background:var(--c-f3be00); border:2px solid var(--c-surface); box-shadow:0 0 0 1px var(--c-e3e3e3, #e3e3e3); }
    .child-add-form { background:var(--c-fafafa, #fafafa); border:1px solid var(--c-ececec); border-radius:8px; padding:12px; }

    /* جدول تحركات الآلية */
    .equipment-profile-page .ep-movements-table { font-size:13px; width:100%; }
    .equipment-profile-page .ep-movements-table thead th { white-space:nowrap; }
    .equipment-profile-page .ep-movements-table td { vertical-align:middle; }
    .equipment-profile-page .ep-movements-table td.ep-note { white-space:normal; min-width:160px;}
    .equipment-profile-page .ep-elapsed { font-weight:700; color:var(--c-0b6b3a, #0b6b3a); white-space:nowrap; font-variant-numeric:tabular-nums; }
    .equipment-profile-page .ep-ev { display:inline-block; padding:3px 11px; border-radius:20px; font-size:12px; font-weight:700; white-space:nowrap; }
    .equipment-profile-page .ep-ev-sys   { background:var(--c-e0ecff, #e0ecff); color:var(--c-1e40af); }
    .equipment-profile-page .ep-ev-proj  { background:var(--c-d1faf0, #d1faf0); color:var(--c-0f766e); }
    .equipment-profile-page .ep-ev-shift { background:var(--c-ede9fe); color:var(--c-6d28d9); }
    .equipment-profile-page .ep-ev-state { background:var(--c-fef3c7); color:var(--c-92400e); }
    .equipment-profile-page .ep-ev-maint { background:var(--c-fee2e2); color:var(--c-b91c1c); }
    .equipment-profile-page .ep-ev-back  { background:var(--c-dcfce7); color:var(--c-15803d); }
    .equipment-profile-page .ep-ev-end   { background:var(--c-f1f5f9); color:var(--c-ink-600); }
    .equipment-profile-page .ep-ev-def   { background:var(--c-f1f5f9); color:var(--c-ink-600); }

    /* ══ UXW-01 ①②: النمطُ الموضعيُّ صار صنفًا برمزِ لونٍ — القيمُ ذاتُها حرفًا ══ */
    .equipment-profile-page .is-hidden { display:none; }
    .equipment-profile-page .ep-mb14 { margin-bottom:14px; }
    .equipment-profile-page .ep-mt12 { margin-top:12px; }
    .equipment-profile-page .ep-mt10 { margin-top:10px; }
    .equipment-profile-page .ep-m0 { margin:0; }
    .equipment-profile-page .ep-w100 { width:100%; }
    .equipment-profile-page .ep-xscroll { overflow-x:auto; }
    .equipment-profile-page .ep-col-span { grid-column:1/-1; }
    .equipment-profile-page .ep-check-row { display:flex; align-items:center; gap:6px; margin-top:22px; }
    .equipment-profile-page .ep-card-head-row { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; }
    .equipment-profile-page .ep-badge-gap { margin-inline-start:6px; }
    .equipment-profile-page .ep-critical-alert { margin:12px 0; font-weight:700; }
    .equipment-profile-page .ep-empty-cell { text-align:center; color:var(--c-888888, #888); }
    .equipment-profile-page .ep-empty-note { text-align:center; color:var(--c-888888, #888); padding:14px; }
    .equipment-profile-page .ep-later { color:var(--c-aaaaaa); }
    .equipment-profile-page .ep-hist-date { color:var(--c-888888, #888); font-size:12px; margin-inline-start:8px; }
    .equipment-profile-page .ep-hist-body { font-size:13px; color:var(--c-555555); }
    .equipment-profile-page .ep-kpi-ok { color:var(--c-15803d); }
    .equipment-profile-page .ep-kpi-warn { color:var(--c-b45309); }
    .equipment-profile-page .ep-kpi-crit { color:var(--c-b91c1c); }
    .equipment-profile-page .ep-card-crit { border-color:var(--c-fecaca); background:var(--c-fff5f5, #fff5f5); }

    /* شاراتُ التفتيش — مجموعةٌ مغلقةٌ من الحالاتِ الدلالية (كانت أنماطًا محسوبةً بالحروف) */
    .ep-chip { display:inline-block; padding:3px 11px; border-radius:999px; font-weight:700; font-size:12px; }
    .ep-chip-w46 { min-width:46px; text-align:center; }
    .ep-chip-ok   { background:var(--c-rgba221637414, rgba(22,163,74,.14)); color:var(--c-15803d); }
    .ep-chip-warn { background:var(--c-rgba217119616, rgba(217,119,6,.16)); color:var(--c-b45309); }
    .ep-chip-crit { background:var(--c-rgba220383814, rgba(220,38,38,.14)); color:var(--c-b91c1c); }
    .ep-chip-info { background:var(--c-rgba379923512, rgba(37,99,235,.12)); color:var(--c-state-info-deep); }
    .ep-chip-mute { background:var(--c-f1f5f9); color:var(--c-ink-600); }
    .ep-chip-grey { background:var(--c-e2e8f0); color:var(--c-ink-600); }
    .ep-dash { color:var(--c-9ca3af); }
    .ep-badge-row { display:inline-flex; gap:5px; flex-wrap:wrap; }

    /* شاراتُ حالةِ البندِ في نافذةِ التفتيش — تُصيَّر خارجَ غلافِ الصفحة */
    .ep-cond-pill { display:inline-flex; align-items:center; padding:3px 11px; border-radius:999px; font-size:12px; font-weight:800; white-space:nowrap; }
    .ep-cond-crit { background:var(--c-rgba220383814, rgba(220,38,38,.14)); color:var(--c-b91c1c); }
    .ep-cond-note { background:var(--c-rgba217119616, rgba(217,119,6,.16)); color:var(--c-b45309); }
    .ep-cond-na   { background:var(--c-f1efe8, #f1efe8); color:var(--c-ink-500); }
    .ep-cond-ok   { background:var(--c-rgba221637414, rgba(22,163,74,.14)); color:var(--c-15803d); }
</style>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
// UXW-01 ②: الطيُّ بصنفٍ لا بنمطٍ موضعيّ
function emsToggle(id){ var el=document.getElementById(id); if(el){ el.classList.toggle('is-hidden'); } }
// UXW-01 ⑤: تهيئاتُ DataTable المحليةُ الثلاثُ رُفعت — المكوّنُ المركزيُّ
// (assets/js/ui-unification.js) يلتقط الجداولَ ويحمّل ar.json ذاتَه. وسلوكُ
// جدولِ التحركاتِ منقولٌ سماتٍ على وسمِ <table>.

// ════════ تبديل التبويبات (hash-linkable) + ضبط أعمدة DataTables عند الإظهار ════════
$(function () {
    var page = document.querySelector('.equipment-profile-page');
    if (!page) return;
    var tabs   = page.querySelectorAll('.ep-tab');
    var panels = page.querySelectorAll('.ep-tab-panel');
    function activate(name) {
        var found = false;
        tabs.forEach(function (t) { t.classList.toggle('is-active', t.getAttribute('data-ep-tab') === name); });
        panels.forEach(function (p) { var on = (p.id === 'tab-' + name); p.classList.toggle('is-active', on); if (on) found = true; });
        if (!found) return;
        try { history.replaceState(null, '', '#' + name); } catch (e) {}
        if (window.jQuery && $.fn.dataTable) {
            setTimeout(function () { try { $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust(); } catch (e) {} }, 40);
        }
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { activate(t.getAttribute('data-ep-tab')); }); });
    var h = (location.hash || '').replace('#', '');
    if (h && page.querySelector('#tab-' + h)) activate(h);
});

// ════════ نافذة تفاصيل التفتيش الاحترافية (تعيد استخدام EmsDetailsModal) ════════
(function () {
    var GOOD = ['سليم', 'صالح'],
        NA   = ['لا ينطبق', ''],
        CRIT = ['حرج', 'ضرر بالغ', 'يحتاج استبدال', 'يحتاج عمرة'],
        NOTE = ['ملاحظة', 'ضرر طفيف', 'ضرر متوسط', 'تآكل ضمن الحد'];

    function escH(s) {
        s = (s == null ? '' : String(s));
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    // UXW-01 ①②: اللونُ صار صنفًا دلاليًّا (ep-cond-*) في كتلةِ الأنماطِ الصفحية
    function condClass(cs) {
        if (CRIT.indexOf(cs) >= 0) return 'ep-cond-crit';
        if (NOTE.indexOf(cs) >= 0) return 'ep-cond-note';
        if (NA.indexOf(cs)   >= 0) return 'ep-cond-na';
        return 'ep-cond-ok';
    }
    function pill(cs) {
        var t = (cs && cs !== '') ? cs : '—';
        return '<span class="ep-cond-pill ' + condClass(cs) + '">' + escH(t) + '</span>';
    }
    function detailText(l) {
        return [l.mv, l.note].filter(function (x) { return x && x !== ''; }).join(' — ');
    }

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest ? ev.target.closest('.ep-ins-view') : null;
        if (!btn || typeof window.EmsDetailsModal === 'undefined') return;
        var d = btn.dataset;
        var lines = (window.EP_INS_LINES || {})[String(d.id)] || [];

        var good = 0, note = 0, crit = 0, na = 0, app = 0;
        lines.forEach(function (l) {
            var v = l.cond || '';
            if (v === '') return;
            if (NA.indexOf(v) >= 0) { na++; return; }
            app++;
            if (GOOD.indexOf(v) >= 0) good++;
            else if (CRIT.indexOf(v) >= 0) crit++;
            else note++;
        });
        var score = app > 0 ? Math.round(100 * good / app) : null;

        // البنود التي تحتاج إجراء (ملاحظة/حرج) — الجزء القابل للتنفيذ
        var attn = lines.filter(function (l) {
            var v = l.cond || '';
            return v !== '' && GOOD.indexOf(v) < 0 && NA.indexOf(v) < 0;
        }).map(function (l) {
            return [l.item, { html: pill(l.cond) }, (l.rec || detailText(l) || '—')];
        });
        // كل البنود — مجمّعة حسب المنظومة
        var allRows = lines.map(function (l) {
            return [(l.sec || '—'), l.item, { html: pill(l.cond) }, (detailText(l) || '—')];
        });

        window.EmsDetailsModal.open({
            title: 'تفتيش — ' + (d.code || ''),
            icon: 'fas fa-clipboard-check',
            fields: [
                { label: 'المرجع', value: d.code, icon: 'fas fa-hashtag' },
                { label: 'الحالة', value: d.state, icon: 'fas fa-flag', type: 'status', tone: (d.state === 'مكتمل' ? 'active' : null) },
                { label: 'النوع', value: d.type, icon: 'fas fa-list' },
                { label: 'الفاحص', value: d.inspector, icon: 'fas fa-user-gear' },
                { label: 'المشروع', value: d.project, icon: 'fas fa-folder-open' },
                { label: 'التاريخ المجدول', value: d.scheduled, icon: 'fas fa-calendar' },
                { label: 'تاريخ الإكمال', value: d.completed, icon: 'fas fa-calendar-check' },
                { label: 'الدرجة', value: (d.score && d.score !== '') ? d.score + '%' : '', icon: 'fas fa-star' },
                { label: 'الجاهزية الفنية', value: d.readiness, icon: 'fas fa-gauge-high' },
                { label: 'حالة المعدة', value: d.eqcond, icon: 'fas fa-tractor' },
                { label: 'حالة المحرك', value: d.engcond, icon: 'fas fa-gears' },
                { label: 'النتيجة العامة', value: d.overall, icon: 'fas fa-clipboard-check', size: 'lg' },
                { label: 'ملاحظات', value: d.notes, icon: 'fas fa-note-sticky', size: 'full' }
            ],
            sections: [
                { title: 'ملخص الفحص', icon: 'fas fa-chart-pie',
                  pills: [
                      { label: 'سليم', value: good },
                      { label: 'ملاحظة', value: note },
                      { label: 'حرج', value: crit },
                      { label: 'لا ينطبق', value: na },
                      { label: 'الدرجة', value: (score === null ? '—' : score + '%') }
                  ] },
                { title: 'بنود تحتاج إجراء', icon: 'fas fa-triangle-exclamation',
                  pills: [ { label: 'الإجمالي', value: attn.length } ],
                  table: { columns: ['البند', 'الحالة', 'التوصية/الملاحظة'], rows: attn },
                  empty: 'لا ملاحظات — كل البنود المنطبقة سليمة ✅' },
                { title: 'كل بنود الفحص', icon: 'fas fa-list-check',
                  pills: [ { label: 'عدد البنود', value: lines.length } ],
                  table: { columns: ['المنظومة', 'البند', 'الحالة', 'التفاصيل'], rows: allRows },
                  empty: 'لا توجد بنود فحص لهذا التفتيش' }
            ]
        });
    });
})();
</script>

