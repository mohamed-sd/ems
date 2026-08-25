<?php
/**
 * tools/lib/repair01_w4_scan.php — مسابرُ القياسِ لمرحلةِ W04 (الحقيقةُ الميدانية)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **كلُّ دالّةٍ هنا تقيس الحيَّ ولا تقرأ دفترًا**: الأداةُ تكتب ما تقيسه هذه
 *   الدوالُّ، والبوّابةُ تستدعيها هي نفسَها ثمّ تقارن — فلا حاجبَ يقرأ مخرَجَ
 *   الأداةِ التي يفحصها (‏_CONTEXT §قواعد القياس ١).
 *
 * ◆ **ومفرداتُ الوردية أربعٌ لحالتَين**: `timesheet.shift` يحمل حيًّا
 *   `صباحية`/`مسائية` (٣٩٥ صفًّا) و`D`/`N` (٢٥٩ صفًّا) بينما `unit_time_log`
 *   و`unit_entries` يحملان `day`/`night`. فبلا جسرِ مفرداتٍ **لا يلتقي
 *   السجلّان أصلًا** — وواقعةُ التوقّفِ المزدوجةُ تبدو صفرًا لأنَّ الانضمامَ
 *   لا يعقد لا لأنَّ الازدواجَ غيرُ قائم. والجسرُ **مُعلَنٌ ومقيس**: كلُّ مفردةٍ
 *   حيّةٍ خارجَه تُسقط الحاجب.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (!defined('EMS_CLI')) { define('EMS_CLI', true); }

/** استعلامٌ يعيد قيمةً واحدةً أو '' */
function repair01_w4_one(mysqli $c, $sql)
{
    $r = $c->query($sql);
    if (!$r) { return ''; }
    $x = $r->fetch_row();
    return $x ? $x[0] : '';
}

function repair01_w4_table_exists(mysqli $c, $t)
{
    $r = $c->query("SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $c->real_escape_string($t) . "'");
    $x = $r ? $r->fetch_row() : null;
    return $x ? (int) $x[0] > 0 : false;
}

/** الرموزُ المعياريّةُ لإداراتِ هذه المرحلة — من `repair01_requirements` لا مكتوبةً */
function repair01_w4_scope_codes(mysqli $c)
{
    $codes = array();
    $r = $c->query("SELECT DISTINCT unit FROM repair01_requirements WHERE stage_no = 4");
    while ($r && $x = $r->fetch_row()) {
        if (preg_match('/^(\d{2})\s/u', $x[0], $m)) { $codes['DEP-' . $m[1]] = true; }
    }
    return array_keys($codes);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ① جسرُ مفرداتِ الوردية — مُعلَنٌ ومقيس
   ═══════════════════════════════════════════════════════════════════════════ */

/** المفردةُ الحيّةُ ⇐ الحالةُ المعياريّة. `null` تعني «غيرُ مُجسَّرة». */
function repair01_w4_shift_bridge()
{
    return array(
        'D'       => 'day',
        'N'       => 'night',
        'صباحية'  => 'day',
        'مسائية'  => 'night',
        'day'     => 'day',
        'night'   => 'night',
    );
}

/** كلُّ مفردةِ ورديةٍ حيّةٍ في `timesheet` ومَن منها بلا جسر */
function repair01_w4_shift_vocab(mysqli $c)
{
    $bridge = repair01_w4_shift_bridge();
    $live = array(); $unmapped = array();
    $r = $c->query("SELECT shift, COUNT(*) n FROM timesheet GROUP BY shift");
    while ($r && $x = $r->fetch_assoc()) {
        $live[$x['shift']] = (int) $x['n'];
        if (!isset($bridge[$x['shift']])) { $unmapped[] = $x['shift'] . ' (' . (int) $x['n'] . ')'; }
    }
    return array('live' => $live, 'unmapped' => $unmapped);
}

/** تعبيرُ SQL يترجم `timesheet.shift` إلى المفردةِ المعياريّة — من الجسرِ لا مكتوبًا */
function repair01_w4_shift_sql($alias = 't')
{
    $cases = array();
    foreach (repair01_w4_shift_bridge() as $live => $norm) {
        $cases[] = "WHEN '" . str_replace("'", "''", $live) . "' THEN '" . $norm . "'";
    }
    return 'CASE `' . $alias . '`.`shift` ' . implode(' ', $cases) . ' ELSE NULL END';
}

/* ═══════════════════════════════════════════════════════════════════════════
   ② واقعةُ التوقّف — قياسُ السجلَّين الحيَّين على حبّةٍ واحدة
   ═══════════════════════════════════════════════════════════════════════════ */

/** مفرداتُ التوقّفِ في `unit_time_log` — كلُّ ما ليس عملًا فعليًّا ولا استعدادًا */
function repair01_w4_stop_states()
{
    return array('tech_breakdown', 'supplier_stop', 'operator_stop', 'client_stop',
                 'fuel_logistics_stop', 'planned_stop', 'force_majeure');
}

/** أعمدةُ ساعاتِ التوقّفِ في `timesheet` — الوجهُ الثاني للواقعةِ نفسِها */
function repair01_w4_ts_fault_cols()
{
    return array('hr_fault', 'maintenance_fault', 'marketing_fault', 'approval_fault',
                 'other_fault_hours', 'ts_supplier_stop_hours', 'ts_planned_stop_hours',
                 'ts_force_majeure_hours');
}

function repair01_w4_ts_fault_sum($alias = 't')
{
    $parts = array();
    foreach (repair01_w4_ts_fault_cols() as $col) { $parts[] = "COALESCE(`$alias`.`$col`,0)"; }
    return '(' . implode('+', $parts) . ')';
}

/** مفتاحُ العطالةِ لواقعةِ توقّفٍ — حبّتُها: كيان × يوم × وردية × معدة */
function repair01_w4_occurrence_key($companyId, $date, $shift, $equipmentId)
{
    return sha1('W4STOP|' . (int) $companyId . '|' . $date . '|' . $shift . '|' . (int) $equipmentId);
}

/**
 * قياسُ كلِّ واقعةِ توقّفٍ من السجلَّين الحيَّين معًا.
 * ◆ **والقياسُ من المصدرَين لا من `ops_stop_register`**: قراءةُ السجلِّ الذي
 *   كتبته الأداةُ تجعل الحاجبَ يفحص نفسَه. فهنا `unit_time_log` و`timesheet`
 *   حصرًا، والدفترُ يُقارَن بهما لا يُقرأ عنهما.
 * @return array occurrence_key ⇒ [company,date,shift,equipment,utl_hours,ts_hours,utl_rows,ts_ids,state]
 */
function repair01_w4_stop_occurrences(mysqli $c)
{
    $states = "'" . implode("','", repair01_w4_stop_states()) . "'";
    $occ = array();

    /* ⓐ الوجهُ الأوّل: `unit_time_log` — الحالةُ والمسؤولُ وقابليّةُ الفوترة */
    $r = $c->query("SELECT company_id, log_date, shift, equipment_id, ops_state,
                           SUM(hours) h, COUNT(*) n, MIN(resp_party) rp, MIN(obligation_type) ob,
                           MAX(billable) bl, MIN(id) first_id, MIN(project_id) pj
                      FROM unit_time_log
                     WHERE ops_state IN ($states) AND equipment_id IS NOT NULL
                     GROUP BY company_id, log_date, shift, equipment_id, ops_state");
    while ($r && $x = $r->fetch_assoc()) {
        $k = repair01_w4_occurrence_key($x['company_id'], $x['log_date'], $x['shift'], $x['equipment_id']);
        if (!isset($occ[$k])) {
            $occ[$k] = array('key' => $k, 'company' => (int) $x['company_id'], 'date' => $x['log_date'],
                             'shift' => $x['shift'], 'equipment' => (int) $x['equipment_id'],
                             'project' => (int) $x['pj'], 'utl_hours' => 0.0, 'ts_hours' => 0.0,
                             'utl_rows' => 0, 'ts_ids' => array(), 'state' => '', 'state_hours' => -1.0,
                             'resp' => '', 'oblig' => '', 'billable' => 0, 'utl_ref' => '');
        }
        $occ[$k]['utl_hours'] += (float) $x['h'];
        $occ[$k]['utl_rows']  += (int) $x['n'];
        if ((float) $x['h'] > $occ[$k]['state_hours']) {          /* الحالةُ الغالبةُ بالساعات */
            $occ[$k]['state_hours'] = (float) $x['h'];
            $occ[$k]['state']    = $x['ops_state'];
            $occ[$k]['resp']     = (string) $x['rp'];
            $occ[$k]['oblig']    = (string) $x['ob'];
            $occ[$k]['billable'] = (int) $x['bl'];
            $occ[$k]['utl_ref']  = 'unit_time_log#' . (int) $x['first_id'];
        }
    }

    /* ⓑ الوجهُ الثاني: أعمدةُ ساعاتِ العطلِ في `timesheet` — بجسرِ المفردات */
    $shiftSql = repair01_w4_shift_sql('t');
    $faultSql = repair01_w4_ts_fault_sum('t');
    $r = $c->query("SELECT t.id, t.company_id, t.date, $shiftSql nshift, t.operator eq, $faultSql fh
                      FROM timesheet t
                     WHERE $faultSql > 0 AND t.operator REGEXP '^[0-9]+$'");
    while ($r && $x = $r->fetch_assoc()) {
        if ($x['nshift'] === null || $x['nshift'] === '') { continue; }   /* مفردةٌ بلا جسر — تُقاس على حِدة */
        $k = repair01_w4_occurrence_key($x['company_id'], $x['date'], $x['nshift'], $x['eq']);
        if (!isset($occ[$k])) {
            $occ[$k] = array('key' => $k, 'company' => (int) $x['company_id'], 'date' => $x['date'],
                             'shift' => $x['nshift'], 'equipment' => (int) $x['eq'],
                             'project' => 0, 'utl_hours' => 0.0, 'ts_hours' => 0.0,
                             'utl_rows' => 0, 'ts_ids' => array(), 'state' => '', 'state_hours' => -1.0,
                             'resp' => '', 'oblig' => '', 'billable' => 0, 'utl_ref' => '');
        }
        $occ[$k]['ts_hours'] += (float) $x['fh'];
        $occ[$k]['ts_ids'][] = (int) $x['id'];
    }
    return $occ;
}

/** الوقائعُ التي يدّعيها السجلّانِ معًا — مقامُ «التوقّفِ مزدوجِ التسجيل» */
function repair01_w4_double_registered(array $occ)
{
    $out = array();
    foreach ($occ as $k => $o) {
        if ($o['utl_hours'] > 0 && $o['ts_hours'] > 0) { $out[$k] = $o; }
    }
    return $out;
}

/* ═══════════════════════════════════════════════════════════════════════════
   ③ تصنيفُ القيدِ اليوميّ — يُعاد اشتقاقُه ولا يُقرأ من العمود
   ═══════════════════════════════════════════════════════════════════════════ */

/**
 * القاعدةُ المقيسة: قيدٌ يحمل معدةً ⇒ قيدٌ ميدانيّ؛ وقيدٌ بلا معدةٍ ولا مُدخِلٍ
 * ولا وردية ⇒ إسقاطُ التزامٍ تعاقديّ. وما عدا ذلك **بلا قاعدة** — يُعدُّ خرقًا.
 * @return array [field ⇒ n, projection ⇒ n, unruled ⇒ [ids], mislabeled ⇒ [ids]]
 */
function repair01_w4_classify_entries(mysqli $c)
{
    $field = 0; $proj = 0; $unruled = array(); $mis = array();
    $r = $c->query("SELECT id, equipment_id, entered_by, shift, field_kind, field_kind_rule FROM unit_entries");
    while ($r && $x = $r->fetch_assoc()) {
        if ($x['equipment_id'] !== null) { $want = 'FIELD_DAILY'; $rule = 'W4_HAS_EQUIPMENT'; $field++; }
        elseif ($x['entered_by'] === null && $x['shift'] === null) { $want = 'CONTRACT_PROJECTION'; $rule = 'W4_NO_EQUIPMENT_NO_ENTERER'; $proj++; }
        else { $unruled[] = (int) $x['id']; continue; }
        if ($x['field_kind'] !== $want || $x['field_kind_rule'] !== $rule) { $mis[] = (int) $x['id'] . ':' . $x['field_kind']; }
    }
    return array('field' => $field, 'projection' => $proj, 'unruled' => $unruled, 'mislabeled' => $mis);
}

/** قيدٌ ميدانيٌّ بلا وردية — المقيسُ من الصفوفِ لا من القيد */
function repair01_w4_daily_without_shift(mysqli $c)
{
    return (int) repair01_w4_one($c, "SELECT COUNT(*) FROM unit_entries
                                       WHERE field_kind = 'FIELD_DAILY' AND shift IS NULL");
}

/* ═══════════════════════════════════════════════════════════════════════════
   ④ مِرساةُ المتطلَّبِ إلى سطحِه — مُعلَنةٌ ومقيسةٌ معًا
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **لماذا مسارٌ مُعلَنٌ لا ترجيحٌ بالجدولِ وحدَه**: قاعدةُ W03 (الجدولُ ⇐
     كاتبُه الوحيد) تنكسر هنا — `timesheet` تكتبه شاشتان، والترجيحُ بالإدارةِ
     المالكةِ يُنزل «التايم شيت اليوميّ» على «التوقّفاتُ بلا مسؤول» لأنَّ كلتيهما
     في DEP-11. فالمسارُ يُعلَن **والمقياسُ يبقى حاكمًا**: الملفُّ يجب أن يمسّ
     الجدولَ (أو يُعلن الصنفَ) المُعلَنَ وإلا سقطت المِرساة.
   ◆ `probe_kind`: `TABLE` جدولٌ يمسُّه الملفّ · `CMP03` ثابتُ `$CANONICAL` ·
     `SERVICE` صنفُ خدمةٍ يستدعيه · `SPINE` عمودٌ فقريٌّ بلا سطحٍ بعد. */
function repair01_w4_anchors()
{
    return array(
        /* ── 11 إدارة التشغيل ─────────────────────────────────────────── */
        'OPS-01' => array('route' => 'Operations/operations_room.php', 'probe' => 'OperationsBoardService', 'kind' => 'SERVICE',
                          'why' => 'لوحة قراءة حية بلا إدخال — مرساتها خدمة اللوحة لا جدول مالك'),
        'OPS-02' => array('route' => '', 'probe' => '', 'kind' => 'GAP', 'wave' => 'W07',
                          'why' => 'معاملات الموسم بيانات مرجعية — تبنى مع طبقة المراجع'),
        'OPS-03' => array('route' => 'Operations/monthly_plan.php', 'probe' => 'scr_op_monthly', 'kind' => 'TABLE',
                          'why' => 'الخطة الشهرية للتشغيل — سطر خطة لكل معدة ومشروع وشهر'),
        'OPS-04' => array('route' => 'Operations/daily_plan.php', 'probe' => 'daily_plans', 'kind' => 'TABLE',
                          'why' => 'خطة الغد وتوزيع الموارد — Header يومي وسطوره في daily_plan_lines'),
        'OPS-05' => array('route' => 'Operations/swap_request.php', 'probe' => 'substitute_coverages', 'kind' => 'TABLE',
                          'why' => 'أمر حركة موارد واحد — طلب التبديل وقراره'),
        'OPS-06' => array('route' => 'Timesheet/timesheet.php', 'probe' => 'timesheet', 'kind' => 'TABLE',
                          'why' => 'السجل اليومي الحاكم للساعات والكميات — معدة × وردية × يوم'),
        'OPS-07' => array('route' => 'Operations/shift_entry.php', 'probe' => 'unit_time_log', 'kind' => 'TABLE',
                          'why' => 'توزيع زمن الوردية بحالاته — Child Register داخل السجل اليومي'),
        'OPS-08' => array('route' => 'Timesheet/timesheet_details.php', 'probe' => 'unit_approvals', 'kind' => 'TABLE',
                          'why' => 'دفعة اعتماد الوحدات — الاعتماد الخماسي بمراحله في unit_approvals'),
        'OPS-09' => array('route' => 'Operations/stops_unattributed.php', 'probe' => 'timesheet', 'kind' => 'TABLE',
                          'why' => 'قرار التوقف كيان مستقل بمهلة — ops_stop_register سجله والشاشة تقرر عليه'),
        'OPS-10' => array('route' => '', 'probe' => '', 'kind' => 'GAP', 'wave' => 'W13',
                          'why' => 'سجل زمني للقراءة مشتق من مصادره — التسمية المعيارية تطابق شاشة قوى عاملة لا شاشة تشغيل'),
        'OPS-11' => array('route' => 'Operations/unit_perf.php', 'probe' => 'unit_perf.php', 'kind' => 'CMP03',
                          'why' => 'سطر انحراف لكل معدة ومشروع وفترة — حد الانحراف من السجل لا من الشيفرة'),
        'OPS-12' => array('route' => 'Operations/monthly_close.php', 'probe' => 'monthly_close.php', 'kind' => 'CMP03',
                          'why' => 'الإقفال الشهري — والسطح مبني تحت مالك مالي لا تشغيلي'),
        /* ── 12 إدارة الموقع ───────────────────────────────────────────── */
        'SITE-01' => array('route' => 'Operations/sites_board.php', 'probe' => 'op_containers', 'kind' => 'TABLE',
                           'why' => 'لوحة الموقع — قراءة حية مشتقة من يوم الموقع والتايم شيت'),
        'SITE-05' => array('route' => '', 'probe' => 'site_day', 'kind' => 'SPINE', 'wave' => 'W05',
                           'why' => 'كيان اليوم الميداني بني في W04؛ وسطحه يبنى بعده — ولا سطح جديد في W04 لأن مقام السجل مجمد في بوابة W03'),
        'SITE-06' => array('route' => 'Operations/shift_log.php', 'probe' => 'shift_log.php', 'kind' => 'CMP03',
                           'why' => 'سجل الوردية — وردية × يوم موقع؛ وكيانها site_day_shift'),
        'SITE-07' => array('route' => 'Operations/shift_entry.php', 'probe' => 'unit_entries', 'kind' => 'TABLE',
                           'why' => 'تسجيل وحدات اليوم — صف وحدة × قياس ميداني'),
        'SITE-08' => array('route' => 'Timesheet/timesheet_details.php', 'probe' => 'unit_approvals', 'kind' => 'TABLE',
                           'why' => 'محضر اعتماد الموقع — المرحلة site في سلسلة الاعتماد'),
        'SITE-09' => array('route' => '', 'probe' => '', 'kind' => 'GAP', 'wave' => 'W05',
                           'why' => 'محضر التسليم بين المشرفين — قيده قائم في site_day_shift.handed_over وسطحه يبنى بعده'),
        'SITE-10' => array('route' => '', 'probe' => '', 'kind' => 'GAP', 'wave' => 'W06',
                           'why' => 'طلب تغيير الحالة — قراره عند مالكه: الصيانة للعطل والنقل للترحيل'),
        'SITE-11' => array('route' => '', 'probe' => '', 'kind' => 'GAP', 'wave' => 'W08',
                           'why' => 'بنود طلب الموقع — Child لطلب الصرف عند المشتريات'),
        'SITE-12' => array('route' => 'Procurement/requests_proc.php', 'probe' => 'proc_request', 'kind' => 'TABLE',
                           'why' => 'طلب الموقع للصرف — مبني تحت مالك المشتريات؛ والموقع يطلب لا يصرف'),
        'SITE-13' => array('route' => '', 'probe' => '', 'kind' => 'GAP', 'wave' => 'W08',
                           'why' => 'دفعات الاستلام — Child لطلب الصرف عند المشتريات'),
        'SITE-14' => array('route' => 'Operations/shift_entry.php', 'probe' => 'unit_time_log', 'kind' => 'TABLE',
                           'why' => 'قرار الاستعداد أو التعطل — تصنيف زمني في السجل نفسه؛ والفرق مالي لا فني'),
        'SITE-15' => array('route' => '', 'probe' => 'proc_custody', 'kind' => 'GAP', 'wave' => 'W08',
                           'why' => 'الصرف الميداني من عهدة معتمدة بسقفها — الجدول proc_custody قائم ولا شاشة تكتبه؛ وProcurement/receipt_custody_proc.php يكتب proc_receipt_custody وهي عهدة استلام لا عهدة صرف ميداني'),
        'SITE-16' => array('route' => '', 'probe' => '', 'kind' => 'GAP', 'wave' => 'W05',
                           'why' => 'تقرير إقفال يوم الموقع — مشتق من site_day ومحاضره وسطحه يبنى بعده'),
        'SITE-17' => array('route' => '', 'probe' => '', 'kind' => 'GAP', 'wave' => 'W14',
                           'why' => 'الإيقاف المؤقت للموقع — أثره على العقد والموارد فقراره خارج اليوم الميداني'),
        'SITE-18' => array('route' => '', 'probe' => '', 'kind' => 'GAP', 'wave' => 'W14',
                           'why' => 'إغلاق الموقع وتسريحه — قائمة عابرة للإدارات: ترحيل وتسوية عهد وإخلاء سكن'),
        'SITE-19' => array('route' => 'Operations/sites_board.php', 'probe' => 'project', 'kind' => 'TABLE',
                           'why' => 'سجلات مرجعية للقراءة من مالكيها — Need-to-Know بلا إدخال'),
    );
}

/**
 * إثباتُ المِرساةِ قياسًا: هل يمسُّ ملفُّ المسارِ المُعلَنِ ما أُعلن له؟
 * @return array [screen_id, owner, verdict, rule]
 */
function repair01_w4_prove_anchor(mysqli $c, $ROOT, array $a)
{
    if ($a['kind'] === 'GAP' || $a['route'] === '') {
        return array('sid' => '', 'owner' => '', 'verdict' => 'NOT_BUILT', 'rule' => $a['kind'] === 'SPINE' ? 'W4_SPINE_NO_SURFACE' : 'W4_TARGET_GAP');
    }
    $rt = $c->real_escape_string($a['route']);
    $row = $c->query("SELECT screen_id, owner_code, on_disk FROM repair01_screen_registry WHERE route = '$rt' LIMIT 1");
    $row = $row ? $row->fetch_assoc() : null;
    if (!$row) { return array('sid' => '', 'owner' => '', 'verdict' => 'ROUTE_NOT_IN_REGISTRY', 'rule' => 'W4_ANCHOR_UNPROVEN'); }
    if ((int) $row['on_disk'] !== 1) { return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'], 'verdict' => 'ROUTE_NOT_ON_DISK', 'rule' => 'W4_ANCHOR_UNPROVEN'); }
    $path = $ROOT . '/' . $a['route'];
    $src = is_file($path) ? (string) file_get_contents($path) : '';
    if ($src === '') { return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'], 'verdict' => 'FILE_UNREADABLE', 'rule' => 'W4_ANCHOR_UNPROVEN'); }

    $p = preg_quote($a['probe'], '~'); $hit = false; $rule = '';
    if ($a['kind'] === 'TABLE') {
        $hit = (bool) (preg_match('~\b(FROM|INTO|UPDATE|JOIN)\s+`?' . $p . '`?\b~i', $src)
                    || preg_match('~[\'"]' . $p . '[\'"]\s*,~', $src));
        $rule = 'W4_ROUTE_TOUCHES_TABLE';
    } elseif ($a['kind'] === 'CMP03') {
        $hit = strpos($src, "\$CANONICAL = '" . $a['probe'] . "'") !== false;
        $rule = 'W4_ROUTE_DECLARES_CANONICAL';
    } elseif ($a['kind'] === 'SERVICE') {
        $hit = strpos($src, $a['probe']) !== false;
        $rule = 'W4_ROUTE_REQUIRES_SERVICE';
    }
    return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                 'verdict' => $hit ? 'ANCHORED' : 'ANCHOR_PROBE_MISSED',
                 'rule' => $hit ? $rule : 'W4_ANCHOR_UNPROVEN');
}

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ أحداثُ النطاقِ الحيّة — الكياناتُ التي تصدر عنها
   ═══════════════════════════════════════════════════════════════════════════ */
function repair01_w4_entity_types()
{
    return array('timesheet', 'unit_entry', 'site_day', 'ops_stop');
}
