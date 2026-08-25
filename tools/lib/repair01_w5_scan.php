<?php
/**
 * tools/lib/repair01_w5_scan.php — مسابرُ القياسِ لمرحلةِ W05 (أثرُ الأصلِ والقوى)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **كلُّ دالّةٍ هنا تقيس الحيَّ ولا تقرأ دفترًا**: الأداةُ تكتب ما تقيسه هذه
 *   الدوالُّ، والبوّابةُ تستدعيها هي نفسَها ثمّ تقارن — فلا حاجبَ يقرأ مخرَجَ
 *   الأداةِ التي يفحصها (‏_CONTEXT §قواعد القياس ١).
 *
 * ◆ **وحقُّ الاستخدامِ يُقاس على نوافذِه لا على صفوفِه**: `asset_ownership_shares`
 *   يحمل حصصًا بفتراتٍ متداخلة، والسؤالُ ليس «كم صفًّا» بل **«كم نافذةً يتجاوز
 *   فيها مجموعُ الحصصِ المتزامنةِ المئة»**. والمقيسُ حيًّا: ٥٨ نافذةً على ٣١
 *   أصلًا · **نافذةٌ واحدةٌ تبلغ ٢٠٠٪**.
 *
 * ⚠ **والحساب يُجمَع على النافذةِ مرّةً واحدة**: `JOIN` الجدولِ بنفسِه ثمّ `SUM`
 *   مع `GROUP BY (أصل، تاريخ)` يضاعف المجموعَ بعددِ صفوفِ الحبّةِ نفسِها فيعطي
 *   «٤٠٠٪» كاذبة. فالنوافذُ تُبنى هنا **مفتاحًا فريدًا** ثمّ يُجمَع فوقَه مرّة.
 *
 * ◆ **والمشتقُّ يُعاد اشتقاقُه بصيغةِ الخدمةِ نفسِها** — لا بصيغةٍ ثانيةٍ مكتوبةٍ
 *   هنا. عدّادٌ وعارضٌ في ملفَّين يتفرّقان (درسُ الحملة)، فالصيغةُ في
 *   `AssetLifecycleService::readinessFormula` و`::coverageFormula` وتُستدعى من
 *   الأداةِ والبوّابةِ معًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (!defined('EMS_CLI')) { define('EMS_CLI', true); }

/** استعلامٌ يعيد قيمةً واحدةً أو '' */
function repair01_w5_one(mysqli $c, $sql)
{
    $r = $c->query($sql);
    if (!$r) { return ''; }
    $x = $r->fetch_row();
    return $x ? $x[0] : '';
}

function repair01_w5_table_exists(mysqli $c, $t)
{
    $r = $c->query("SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $c->real_escape_string($t) . "'");
    $x = $r ? $r->fetch_row() : null;
    return $x ? (int) $x[0] > 0 : false;
}

/** الرموزُ المعياريّةُ لإداراتِ هذه المرحلة — من `repair01_requirements` لا مكتوبةً */
function repair01_w5_scope_codes(mysqli $c)
{
    $codes = array();
    $r = $c->query("SELECT DISTINCT unit FROM repair01_requirements WHERE stage_no = 5");
    while ($r && $x = $r->fetch_row()) {
        if (preg_match('/^(\d{2})\s/u', $x[0], $m)) { $codes['DEP-' . $m[1]] = true; }
    }
    return array_keys($codes);
}

/**
 * حارسُ العرضِ لسطحٍ جديدٍ — **يُقاس من القرصِ بالمقياسِ نفسِه** الذي تستعمله
 * بوّابةُ W02، لا يُعلَن. فلو بُني سطحٌ بلا حارسٍ سقط `W2-09` — ولا يمرُّ
 * بادّعاءٍ مكتوبٍ في الأداة.
 * @return array{kind:string, evidence:string}
 */
function repair01_w5_guard_of($ROOT, $route)
{
    require_once $ROOT . '/tools/lib/repair01_w2_scan.php';
    $files = repair01_w2_php_files($ROOT);
    $incs  = repair01_w2_include_map($files);
    $bear  = repair01_w2_shell_bearers($incs);
    $rel   = $route;
    if (!isset($files[$rel])) { return array('kind' => 'NONE', 'evidence' => 'لا ملفَّ مقروءًا على القرص'); }
    return repair01_w2_guard($rel, $files[$rel], isset($incs[$rel]) ? $incs[$rel] : array(), $bear);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ① حقُّ الاستخدامِ التشغيليّ — التزامنُ يُقاس على النوافذ
   ═══════════════════════════════════════════════════════════════════════════ */

/**
 * كلُّ نافذةِ بدايةٍ في `asset_ownership_shares` ومجموعُ الحصصِ التي تغطّيها.
 * @return array مفتاحُه "asset|from" ⇒ [asset, from, pct, rows]
 */
function repair01_w5_ownership_windows(mysqli $c)
{
    $out = array();
    $rows = array();
    $r = $c->query("SELECT share_id, company_id, asset_id, financier_entity_id, model_code, percent,
                           valid_from, valid_to, doc_ref
                      FROM asset_ownership_shares
                     WHERE asset_kind = 'equipment' AND valid_from IS NOT NULL
                     ORDER BY asset_id, valid_from, share_id");
    while ($r && $x = $r->fetch_assoc()) { $rows[] = $x; }

    foreach ($rows as $a) {
        $key = $a['asset_id'] . '|' . $a['valid_from'];
        if (isset($out[$key])) { continue; }
        $pct = 0.0; $n = 0; $holders = array();
        foreach ($rows as $b) {
            if ($b['asset_id'] !== $a['asset_id']) { continue; }
            if ($b['valid_from'] > $a['valid_from']) { continue; }
            $to = ($b['valid_to'] === null || $b['valid_to'] === '') ? '9999-12-31' : $b['valid_to'];
            if ($to < $a['valid_from']) { continue; }
            $pct += (float) $b['percent']; $n++;
            $holders[] = (int) $b['share_id'];
        }
        $out[$key] = array('asset' => (int) $a['asset_id'], 'company' => (int) $a['company_id'],
                           'from' => $a['valid_from'], 'pct' => round($pct, 2), 'rows' => $n,
                           'shares' => $holders);
    }
    return $out;
}

/** النوافذُ التي يتجاوز فيها المجموعُ المئةَ — مقامُ «حقٍّ متزامنٍ مفتوح» */
function repair01_w5_concurrent_windows(array $windows)
{
    $out = array();
    foreach ($windows as $k => $w) { if ($w['pct'] > 100.0) { $out[$k] = $w; } }
    return $out;
}

/**
 * **حبّةٌ واحدةٌ بصفَّين**: `FLEET-09` يقول «صفٌّ واحدٌ = حصّةُ مالكٍ واحدٍ في
 * فترةٍ واحدة». والمقيسُ حيًّا: مجموعةٌ واحدةٌ تخالفه — الأصلُ ٤ · المموِّلُ ٥٩ ·
 * من 2026-07-01 بصفَّين (‏٢٠٪ و٣٠٪). فالنقلُ إلى الحبّةِ الصحيحةِ **يطوي**
 * الصفَّين في واحدٍ حتمًا، والمطويُّ يُقاس ويُعلَن ويُكتب نصُّه في الصفِّ الناجي
 * — لا يُفقد صمتًا.
 * @return array "asset|holder|from" ⇒ [n, note]
 */
function repair01_w5_ownership_dupes(mysqli $c)
{
    $out = array();
    $r = $c->query("SELECT asset_id, COALESCE(financier_entity_id, op_id) holder, valid_from,
                           COUNT(*) n, GROUP_CONCAT(CONCAT('share#', share_id, '=', percent) ORDER BY share_id) rows_x
                      FROM asset_ownership_shares
                     WHERE asset_kind = 'equipment' AND valid_from IS NOT NULL
                       AND percent > 0 AND percent <= 100
                     GROUP BY asset_id, holder, valid_from HAVING n > 1");
    while ($r && $x = $r->fetch_assoc()) {
        $out[(int) $x['asset_id'] . '|' . (int) $x['holder'] . '|' . $x['valid_from']] = array(
            'n' => (int) $x['n'],
            'note' => 'حصتان لحائز واحد في النافذة نفسها (' . $x['rows_x']
                    . ') — والحبة تقبل واحدة (FLEET-09) والفارق يحسم عند مالكه',
        );
    }
    return $out;
}

/** عددُ الصفوفِ التي تُطوى حتمًا بالنقلِ إلى الحبّةِ الصحيحة */
function repair01_w5_ownership_collapsed(array $dupes)
{
    $n = 0;
    foreach ($dupes as $d) { $n += ($d['n'] - 1); }
    return $n;
}

/** صفوفُ المصدرِ الحيِّ لحقِّ الاستخدام — تُنقل ولا تُدهَس */
function repair01_w5_ownership_rows(mysqli $c)
{
    $rows = array();
    $r = $c->query("SELECT share_id, company_id, asset_id, financier_entity_id, op_id, model_code,
                           percent, valid_from, valid_to, doc_ref
                      FROM asset_ownership_shares
                     WHERE asset_kind = 'equipment' AND valid_from IS NOT NULL
                       AND percent > 0 AND percent <= 100
                     ORDER BY asset_id, valid_from, share_id");
    while ($r && $x = $r->fetch_assoc()) { $rows[] = $x; }
    return $rows;
}

/* ═══════════════════════════════════════════════════════════════════════════
   ② الجاهزيّةُ الشهريّة — تُشتقُّ من التايم شيتِ وسجلِّ التوقّف
   ═══════════════════════════════════════════════════════════════════════════ */

/** مُعرِّفاتُ الأصولِ الحيّةِ في سجلِّ الأصول — مقامُ «الأصلُ قائم» */
function repair01_w5_asset_ids(mysqli $c)
{
    $ids = array();
    $r = $c->query("SELECT id FROM equipments");
    while ($r && $x = $r->fetch_row()) { $ids[(int) $x[0]] = true; }
    return $ids;
}

/**
 * **جسرُ التشغيلةِ إلى الأصل** — `operations.id` ⇐ `equipments.id`.
 *
 * ⚠ **الاكتشافُ الحاكمُ للمرحلة**: عمودُ `timesheet.operator` اسمُه «مشغّل»
 *   وقيمتُه **مُعرِّفُ تشغيلةٍ** (`operations.id`) — لا مُعرِّفُ أصلٍ ولا
 *   مُعرِّفُ شخص. والشاشةُ الحيّةُ تُثبت ذلك بنفسِها:
 *   `Timesheet/timesheet.php` تضمُّ `JOIN operations o ON t.operator = o.id`.
 *
 *   والمقيسُ حيًّا: ٢٠٦ قيمةٍ متمايزة — **٢٠٥ تُحَلُّ تشغيلةً · و٨ فقط تصادف
 *   أن يكون لها صفٌّ في `equipments` بالمُعرِّفِ نفسِه · و١٤ لها صفٌّ في
 *   `employees`**. فالقراءةُ المباشرةُ للعمودِ بوصفِه أصلًا **تُسند ساعاتِ ١٠٨
 *   أصولٍ إلى ٨** — ولا تُخطئ الرقمَ فحسب بل تُخطئ الكيان.
 *
 *   والقفزةُ الثانية: `operations.equipment` عمودٌ نصّيٌّ يحمل رقمَ الأصل
 *   (‏١٩١ من ١٩٥ قيمةً تُحَلّ). فالسلسلةُ الصادقة:
 *   `timesheet.operator → operations.id → operations.equipment → equipments.id`
 *   وتحلُّ **٦٥٣ من ٦٥٤ صفًّا** إلى ٣٢٠ شهرَ أصلٍ على ١٠٨ أصول.
 *
 * @return array operation_id ⇒ equipment_id
 */
function repair01_w5_asset_bridge(mysqli $c)
{
    $live = repair01_w5_asset_ids($c);
    $map = array();
    $r = $c->query("SELECT id, equipment FROM operations WHERE equipment REGEXP '^[0-9]+$'");
    while ($r && $x = $r->fetch_assoc()) {
        $eq = (int) $x['equipment'];
        if ($eq > 0 && isset($live[$eq])) { $map[(int) $x['id']] = $eq; }
    }
    return $map;
}

/**
 * قياسُ ساعاتِ كلِّ (أصل × شهر) من مصادرِها الحيّةِ **عبرَ جسرِ التشغيلة**.
 *
 * ◆ **والتوقّفُ يُؤخَذ بالأكبرِ لا بالمجموع**: الواقعةُ الواحدةُ في سجلَّين
 *   (‏W04 §٢) — وجمعُهما يحتسب الساعةَ مرّتين فيضاعف الخصمَ من الجاهزيّة.
 *
 * ◆ **وسجلُّ التوقُّفِ يخلط فضاءَي مفاتيح**: `ops_stop_register.equipment_id`
 *   يحمل مُعرِّفَ أصلٍ حقيقيًّا حين كان الحاكمُ `unit_time_log` (‏١٦٤ صفًّا ·
 *   صفرُ يتيم)، ويحمل **مُعرِّفَ تشغيلةٍ** حين كان الحاكمُ `timesheet`
 *   (‏٢١٢ صفًّا · **١٢٨ يتيمًا**). فكلُّ صفٍّ يُترجَم بحسبِ حاكمِه لا بحسبِ عمودِه.
 *
 * ◆ **والصفُّ الذي لا يُحَلُّ يُقاس ويُوسَم ولا يُحذف من المقام** — المقامُ كاملٌ
 *   لا مختار (§قواعد القياس ٤)، والاستثناءُ مُعلَنٌ في `W5-D-08`.
 *
 * @return array مفتاحُه "company|equipment|period" — وفيه `resolved`
 */
function repair01_w5_readiness_measure(mysqli $c, $limitAssets = 0)
{
    $out = array();
    $bridge = repair01_w5_asset_bridge($c);
    $lim = ((int) $limitAssets > 0) ? ' LIMIT ' . (int) $limitAssets : '';

    /* ⓐ الوجهُ الأوّل: ساعاتُ التايم شيتِ بحبّةِ (تشغيلة × شهر) ثمّ تُترجَم أصلًا */
    $r = $c->query("SELECT t.company_id, CAST(t.operator AS UNSIGNED) op, DATE_FORMAT(t.date,'%Y-%m') period,
                           COALESCE(SUM(t.shift_hours),0)     sh,
                           COALESCE(SUM(t.executed_hours),0)  ex,
                           COALESCE(SUM(t.standby_hours),0)   sb,
                           COALESCE(SUM(t.total_fault_hours),0) ft
                      FROM timesheet t
                     WHERE t.operator REGEXP '^[0-9]+$' AND t.date IS NOT NULL
                     GROUP BY t.company_id, op, period" . $lim);
    while ($r && $x = $r->fetch_assoc()) {
        $op = (int) $x['op'];
        $eq = isset($bridge[$op]) ? $bridge[$op] : 0;
        $ok = ($eq > 0);
        $k = (int) $x['company_id'] . '|' . ($ok ? $eq : ('op' . $op)) . '|' . $x['period'];
        if (!isset($out[$k])) {
            $out[$k] = array('company' => (int) $x['company_id'], 'equipment' => $eq,
                             'operation' => $op, 'period' => $x['period'],
                             'shift' => 0.0, 'executed' => 0.0, 'standby' => 0.0,
                             'fault' => 0.0, 'stop' => 0.0,
                             'resolved' => $ok,
                             'resolve_rule' => $ok ? 'W5_ASSET_VIA_OPERATION_BRIDGE' : 'W5_OPERATION_HAS_NO_ASSET');
        }
        $out[$k]['shift']    += (float) $x['sh'];
        $out[$k]['executed'] += (float) $x['ex'];
        $out[$k]['standby']  += (float) $x['sb'];
        $out[$k]['fault']    += (float) $x['ft'];
    }

    /* ⓑ الوجهُ الثاني: ساعاتُ التوقُّفِ — وكلُّ صفٍّ يُترجَم بحسبِ حاكمِه */
    $r = $c->query("SELECT company_id, equipment_id eq, authority, DATE_FORMAT(stop_date,'%Y-%m') period,
                           COALESCE(SUM(hours),0) st
                      FROM ops_stop_register
                     WHERE equipment_id IS NOT NULL
                     GROUP BY company_id, eq, authority, period");
    while ($r && $x = $r->fetch_assoc()) {
        $raw = (int) $x['eq'];
        $eq = ($x['authority'] === 'timesheet' && isset($bridge[$raw])) ? $bridge[$raw] : $raw;
        $k = (int) $x['company_id'] . '|' . $eq . '|' . $x['period'];
        if (!isset($out[$k])) { continue; }          /* توقُّفٌ بلا سجلٍّ يوميٍّ — لا أرضيّةَ لجاهزيّةٍ */
        $out[$k]['stop'] += (float) $x['st'];
    }
    return $out;
}

/* ═══════════════════════════════════════════════════════════════════════════
   ③ تغطيةُ القوى — المطلوبُ مقابل المتوفّر
   ═══════════════════════════════════════════════════════════════════════════ */

/** صفوفُ الاحتياجِ الحيّةُ بمشتقِّها ومُدخَلِها معًا */
function repair01_w5_coverage_measure(mysqli $c)
{
    $out = array();
    $r = $c->query("SELECT id, company_id, project_id, worker_category, required_qty, available_qty,
                           shortage_qty, surplus_qty, state
                      FROM workforce_requirement ORDER BY id");
    while ($r && $x = $r->fetch_assoc()) {
        $f = \App\Services\Fleet\AssetLifecycleService::coverageFormula($x['required_qty'], $x['available_qty']);
        $declared = repair01_w5_coverage_ar_to_code((string) $x['state']);
        $agrees = ($declared === $f['state'] && (int) $x['shortage_qty'] === $f['gap']);
        $out[(int) $x['id']] = array(
            'id' => (int) $x['id'], 'company' => (int) $x['company_id'],
            'project' => ($x['project_id'] === null ? 0 : (int) $x['project_id']),
            'category' => (string) $x['worker_category'],
            'required' => (int) $x['required_qty'], 'available' => (int) $x['available_qty'],
            'gap' => $f['gap'], 'surplus' => $f['surplus'], 'state' => $f['state'], 'rule' => $f['rule'],
            'declared_state' => $declared, 'declared_state_ar' => (string) $x['state'],
            'declared_gap' => (int) $x['shortage_qty'],
            'variance_rule' => $agrees ? 'W5_COVERAGE_AGREES' : 'W5_COVERAGE_VARIANCE_OPEN',
        );
    }
    return $out;
}

/** جسرُ مفرداتِ حالةِ الاحتياجِ الحيّةِ إلى الرمزِ المعياريّ — مُعلَنٌ لا مُخمَّن */
function repair01_w5_coverage_ar_to_code($ar)
{
    $map = array('عجز' => 'SHORTAGE', 'فائض' => 'SURPLUS', 'متوازن' => 'BALANCED', 'مخطّط' => 'PLANNED');
    return isset($map[$ar]) ? $map[$ar] : 'UNMAPPED';
}

/** مفرداتُ حالةِ الاحتياجِ الحيّةُ ومَن منها بلا جسر */
function repair01_w5_coverage_vocab(mysqli $c)
{
    $live = array(); $unmapped = array();
    $r = $c->query("SELECT state, COUNT(*) n FROM workforce_requirement GROUP BY state");
    while ($r && $x = $r->fetch_assoc()) {
        $live[$x['state']] = (int) $x['n'];
        if (repair01_w5_coverage_ar_to_code((string) $x['state']) === 'UNMAPPED') {
            $unmapped[] = $x['state'] . ' (' . (int) $x['n'] . ')';
        }
    }
    return array('live' => $live, 'unmapped' => $unmapped);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ④ حالةُ الأصل — تُعاد اشتقاقُها من الوقائعِ لا تُقرأ من العمود
   ═══════════════════════════════════════════════════════════════════════════ */

/**
 * اشتقاقُ حالةِ كلِّ أصلٍ من وقائعِه — بالمنطقِ نفسِه الذي في الخدمة، بقراءةٍ
 * خامٍّ مجموعةٍ (البوّابةُ تقرأ ولا تكتب، والقراءةُ الخامُّ هنا في أداةِ قياسٍ
 * لا في مسارِ تطبيقٍ — وهي خارجُ نطاقِ `FR-SEC-006`).
 * @return array equipment_id ⇒ [state, rule]
 */
function repair01_w5_lifecycle_measure(mysqli $c, $asOf = '')
{
    $asOf = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $asOf) ? $asOf : date('Y-m-d');
    $asOfE = $c->real_escape_string($asOf);
    $out = array();

    $r = $c->query("SELECT id, intake_id, card_state FROM equipments");
    while ($r && $x = $r->fetch_assoc()) {
        $out[(int) $x['id']] = array('intake' => (int) $x['intake_id'], 'card' => (string) $x['card_state'],
                                     'state' => '', 'rule' => '');
    }
    $intakeState = array();
    $r = $c->query("SELECT id, state FROM asset_intake");
    while ($r && $x = $r->fetch_assoc()) { $intakeState[(int) $x['id']] = (string) $x['state']; }

    $exits = array();
    $r = $c->query("SELECT equipment_id, exit_kind, exit_date, state, actual_return
                      FROM asset_exit WHERE exit_date <= '$asOfE' ORDER BY equipment_id, exit_date DESC");
    while ($r && $x = $r->fetch_assoc()) {
        $eq = (int) $x['equipment_id'];
        if (isset($exits[$eq])) { continue; }
        $exits[$eq] = $x;
    }

    foreach ($out as $eq => $v) {
        if (isset($exits[$eq])) {
            $x = $exits[$eq];
            if ($x['exit_kind'] === 'permanent') { $out[$eq]['state'] = 'retired'; $out[$eq]['rule'] = 'W5_LIFECYCLE_FROM_EXIT_RECORD'; continue; }
            if ($x['state'] !== 'returned' || (string) $x['actual_return'] > $asOf) {
                $out[$eq]['state'] = 'out_temporary'; $out[$eq]['rule'] = 'W5_LIFECYCLE_FROM_EXIT_RECORD'; continue;
            }
        }
        if ($v['intake'] > 0 && isset($intakeState[$v['intake']])) {
            $map = array('activated' => 'active', 'card_issued' => 'card_issued', 'rejected' => 'rejected');
            $s = isset($map[$intakeState[$v['intake']]]) ? $map[$intakeState[$v['intake']]] : 'in_intake';
            $out[$eq]['state'] = $s; $out[$eq]['rule'] = 'W5_LIFECYCLE_FROM_INTAKE_STATE'; continue;
        }
        $out[$eq]['state'] = ($v['card'] === 'active') ? 'active' : 'card_draft';
        $out[$eq]['rule'] = 'W5_LIFECYCLE_FROM_LEGACY_CARD_STATE';
    }
    return $out;
}

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ مِرساةُ المتطلَّبِ إلى سطحِه — مُعلَنةٌ ومقيسةٌ معًا
   ═══════════════════════════════════════════════════════════════════════════
   ◆ `probe_kind`: `TABLE` جدولٌ يمسُّه الملفّ · `CMP03` ثابتُ `$CANONICAL` ·
     `SERVICE` صنفٌ يستدعيه · `GAP` لم يُبنَ وله موجةٌ · `SPINE` عمودٌ فقريٌّ
     بلا سطحٍ بعد. */
function repair01_w5_anchors()
{
    return array(
        /* ── 04 إدارة الأسطول والأصول · ب دخول الأصل ───────────────────── */
        'FLEET-03' => array('route' => 'Fleet/asset_intake.php', 'probe' => 'asset_intake', 'kind' => 'TABLE',
                            'why' => 'طلب ادخال الاصل — هنا تبدا دورة الاصل قبل الكرت لا بعده؛ والكيان بني في W05'),
        'FLEET-04' => array('route' => 'Fleet/asset_intake.php', 'probe' => 'asset_source_check', 'kind' => 'TABLE',
                            'why' => 'واقعة تحقق من المصدر — تبويب داخل طلب الادخال لان العلاقة ١:ن ولا معنى لها خارجه'),
        'FLEET-05' => array('route' => 'Fleet/asset_intake.php', 'probe' => 'asset_inspection_order', 'kind' => 'TABLE',
                            'why' => 'امر التفتيش — التفتيش يبدا بامر لا بزيارة؛ والامر يصدر من الاسطول وتستقبله الصيانة (MNT-05 · W06)'),
        'FLEET-06' => array('route' => 'Maintenance/inspections.php', 'probe' => 'mnt_inspection', 'kind' => 'TABLE',
                            'why' => 'بطاقة التفتيش محفوظة تاريخيا — مبنية تحت مالك الصيانة والاسطول يامر ويقرا'),
        'FLEET-07' => array('route' => 'Equipments/equipments.php', 'probe' => 'equipments', 'kind' => 'TABLE',
                            'why' => 'كرت الاصل — معدة واحدة طوال عمرها؛ والهوية ملك الاسطول والقياس المالي مرجع'),
        'FLEET-08' => array('route' => 'Equipments/equipment_documents.php', 'probe' => 'equipment_documents', 'kind' => 'TABLE',
                            'why' => 'مستندات الاصل — Child Register لكرت الاصل بعلاقة ١:ن'),
        'FLEET-09' => array('route' => 'Fleet/asset_use_rights.php', 'probe' => 'asset_use_right', 'kind' => 'TABLE',
                            'why' => 'حق الاستخدام التشغيلي — حصة مالك واحد في فترة واحدة والملكية متعاقبة لا متزامنة'),
        'FLEET-10' => array('route' => 'Equipments/equipment_profile.php', 'probe' => 'fleet_equipment_component', 'kind' => 'TABLE',
                            'why' => 'المكونات والملحقات — Child Register لكرت الاصل؛ والجدول قائم بصفر صف ويقراه ملف البطاقة وحده'),
        /* ── ج دخول التشغيل ──────────────────────────────────────────── */
        'FLEET-11' => array('route' => 'Fleet/asset_intake.php', 'probe' => 'activateAsset', 'kind' => 'SERVICE',
                            'why' => 'التفعيل واعادة الخدمة — واقعة تنقل الاصل الى نشط؛ ومحلها في نهاية طلب الادخال'),
        'FLEET-12' => array('route' => 'Fleet/asset_assignments.php', 'probe' => 'asset_assignment', 'kind' => 'TABLE',
                            'why' => 'التخصيص على الوحدات — المعدة تنفذ الحصة ولا تنشئها؛ ومصدر حقيقة الوحدة المبيعات'),
        /* ── د الحركة والاستخدام ─────────────────────────────────────── */
        'FLEET-13' => array('route' => 'Fleet/asset_assignments.php', 'probe' => 'fleet_equipment_history', 'kind' => 'TABLE',
                            'why' => 'حركة الموقع والمشروع — لا ينشا اصل جديد عند الانتقال والتاريخ لا يمحى'),
        'FLEET-14' => array('route' => 'Timesheet/timesheet.php', 'probe' => 'timesheet', 'kind' => 'TABLE',
                            'why' => 'السجل اليومي للتشغيل — اصل × يوم × وردية؛ ومصدر حقيقته العمليات (W04)'),
        'FLEET-15' => array('route' => 'Equipments/equipments_drivers.php', 'probe' => 'equipment_drivers', 'kind' => 'TABLE',
                            'why' => 'تاريخ المشغلين على المعدة — فترة تشغيل مشغل واحد؛ ووجوده في الوردية ليس اثبات مسؤولية'),
        /* ── هـ الرقابة الفنية ───────────────────────────────────────── */
        'FLEET-16' => array('route' => 'Fleet/readiness_board.php', 'probe' => 'OperationsBoardService', 'kind' => 'SERVICE',
                            'why' => 'الحالة الفنية الراهنة — اصل واحد بحالته الان؛ لوحة خلايا محسوبة بلا حقول محلية والتاريخ في سجل تغير الحالة'),
        'FLEET-17' => array('route' => 'Equipments/equipment_profile.php', 'probe' => 'fleet_equipment_history', 'kind' => 'TABLE',
                            'why' => 'سجل تغير الحالة — Child Register لان الاصل يمر بحالات كثيرة ولا تكتب في خلية'),
        'FLEET-18' => array('route' => 'Equipments/fleet_failures.php', 'probe' => 'timesheet_failure_hours', 'kind' => 'TABLE',
                            'why' => 'سجل الاعطال والتوقفات — الاسطول يسجل الواقعة ولا يملك التشخيص ولا امر العمل؛ والمقيس ان الشاشة تقرا ساعات العطل لا جدول الاعطال'),
        'FLEET-19' => array('route' => 'Fleet/asset_readiness.php', 'probe' => 'asset_readiness', 'kind' => 'TABLE',
                            'why' => 'الملخص التشغيلي الشهري — اصل × شهر مشتق من التايم شيت؛ لا تدخل الساعات مرتين'),
        'FLEET-20' => array('route' => 'Fleet/asset_readiness.php', 'probe' => 'asset_readiness', 'kind' => 'TABLE',
                            'why' => 'الجاهزية الشهرية — مشتقة بالكامل لا ادخال'),
        /* ── و الخروج ────────────────────────────────────────────────── */
        'FLEET-21' => array('route' => 'Fleet/asset_exit.php', 'probe' => 'asset_exit', 'kind' => 'TABLE',
                            'why' => 'الخروج المؤقت — الاصل يخرج ويعود والعودة تسجل في واقعتها لا في كرت جديد'),
        'FLEET-22' => array('route' => 'Fleet/asset_exit.php', 'probe' => 'asset_exit', 'kind' => 'TABLE',
                            'why' => 'الخروج الدائم — الاسطول يوثق الواقعة والاثر المالي مرجع من المالية'),
        /* ── ز الاستثناءات والقرارات — مالكها الحوكمة لا الاسطول ─────── */
        'FLEET-23' => array('route' => 'Governance/exceptions.php', 'probe' => 'exceptions.php', 'kind' => 'CMP03',
                            'why' => 'سجل الاستثناءات — مالكه الحوكمة؛ والاسطول طرف لا مالك (W13)'),
        'FLEET-24' => array('route' => '', 'probe' => '', 'kind' => 'GAP', 'wave' => 'W13',
                            'why' => 'ملاحظات المراجع الخارجي — مالكها الحوكمة والمراجعة الداخلية المستقلة'),
        'FLEET-25' => array('route' => '', 'probe' => '', 'kind' => 'GAP', 'wave' => 'W13',
                            'why' => 'التضاربات بين المصادر — مالكها الحوكمة؛ ومقامها عابر للادارات لا نطاق اسطول'),
        'FLEET-26' => array('route' => '', 'probe' => '', 'kind' => 'GAP', 'wave' => 'W11',
                            'why' => 'النقاط غير المحسومة — مالكها مسؤول التمويل وملفها التمويل والملكية'),
        'FLEET-27' => array('route' => '', 'probe' => '', 'kind' => 'GAP', 'wave' => 'W14',
                            'why' => 'حزمة القرارات الادارية — مالكها الرئيس التنفيذي ومحلها مساحته'),
        /* ── ح المخرجات ──────────────────────────────────────────────── */
        'FLEET-28' => array('route' => 'Equipments/equipment_profile.php', 'probe' => 'fleet_equipment_history', 'kind' => 'TABLE',
                            'why' => 'تاريخ المعدة الكامل — واقعة واحدة في حياة الاصل؛ تقرير لا مصدر حقيقة'),
        'FLEET-29' => array('route' => 'Fleet/readiness_board.php', 'probe' => 'OperationsBoardService', 'kind' => 'SERVICE',
                            'why' => 'لوحة الاسطول — قراءة حية مشتقة بلا ادخال؛ ومرساتها خدمة اللوحة لا جدول مالك'),
        'FLEET-30' => array('route' => 'Finance/asset_hours_link.php', 'probe' => 'asset_hour_reconciliations', 'kind' => 'TABLE',
                            'why' => 'مرجع ساعات التشغيل للاهلاك — الاسطول يقرا ولا يكتب ومصدر الحقيقة سجل الاصول المحاسبي'),

        /* ── 13 إدارة القوى التشغيلية · الاحتياج والتغطية ─────────────── */
        'WRK-01' => array('route' => 'Workforce/wf_coverage.php', 'probe' => 'wf_coverage', 'kind' => 'TABLE',
                          'why' => 'لوحة القوى التشغيلية — مؤشر × فترة قراءة حية؛ ومؤشراتها مشتقة من التغطية والتخصيص والتواجد والتناوب في السطح نفسه'),
        'WRK-02' => array('route' => 'Workforce/workforce_requirement.php', 'probe' => 'workforce_requirement', 'kind' => 'TABLE',
                          'why' => 'احتياج المشروع من القوى — مشروع × فئة × فترة؛ يبدا من عقد المشروع لا من سجل الافراد'),
        'WRK-03' => array('route' => 'Workforce/wf_coverage.php', 'probe' => 'wf_coverage', 'kind' => 'TABLE',
                          'why' => 'المطلوب مقابل المتوفر — سطر فجوة مشتق من الاحتياج المعتمد والمتوفر الجاهز'),
        'WRK-04' => array('route' => 'Workforce/recruitment_pipeline.php', 'probe' => 'rec_applications', 'kind' => 'TABLE',
                          'why' => 'الترشيح والاختيار للتغطية — مرشح × احتياج؛ والتوظيف عبر الموارد البشرية'),
        'WRK-05' => array('route' => 'Workforce/worker_contract.php', 'probe' => 'worker_contract', 'kind' => 'TABLE',
                          'why' => 'عقود المشاريع المرجعية — حقيقة التعاقد والاجر عند الموارد البشرية والقوى تقرا'),
        /* ── التخصيص والحركة ─────────────────────────────────────────── */
        'WRK-10' => array('route' => 'Oprators/select_project.php', 'probe' => 'project', 'kind' => 'TABLE',
                          'why' => 'التخصيص للمشروع — فرد × مشروع × مدة؛ والتخصيص للمشروع اولا ثم المعدة والوردية فالشاشة تختار المشروع من سجله'),
        'WRK-11' => array('route' => 'Oprators/oprators.php', 'probe' => 'equipment_drivers', 'kind' => 'TABLE',
                          'why' => 'تخصيص المعدة والوردية — فرد × معدة × وردية عند الانطباق للفئة؛ والسجل الحي equipment_drivers لا equipment_operators'),
        'WRK-12' => array('route' => 'Workforce/worker_movement.php', 'probe' => 'worker_movement', 'kind' => 'TABLE',
                          'why' => 'الحركة والتواجد اليومي — القوى تسجل الميداني والحضور النظامي للاجر عند الموارد'),
        'WRK-13' => array('route' => 'Workforce/rotation.php', 'probe' => 'rotation.php', 'kind' => 'CMP03',
                          'why' => 'التناوب والبدلاء — لا دورة بلا بديل او قرار فجوة موثق؛ والسطح مخزن CMP-03 وجدول operator_rotations لا يقراه ملف حي'),
        /* ── الأداء والتسوية ─────────────────────────────────────────── */
        'WRK-14' => array('route' => 'Workforce/worker_worklog.php', 'probe' => 'EventService', 'kind' => 'SERVICE',
                          'why' => 'اداء الافراد التشغيلي — سطر اداء مشتق من الساعات والوحدات المعتمدة ولا يدخل؛ والسطح يقرا Views محسوبة عبر خدمته'),
        'WRK-15' => array('route' => 'Workforce/worker_settlement.php', 'probe' => 'worker_settlement', 'kind' => 'TABLE',
                          'why' => 'التسوية ونهاية التخصيص — العهد والسكن والمستحق؛ والصرف والاجر عند مالكيهما'),
        'WRK-16' => array('route' => 'Workforce/housing_units.php', 'probe' => 'housing_unit', 'kind' => 'TABLE',
                          'why' => 'وحدات السكن والاعاشة — ملكية الادارة معلقة وتدار هنا مؤقتا حتى الحسم'),
        'WRK-17' => array('route' => 'Workforce/worker_leave_absence.php', 'probe' => 'worker_leave_absence', 'kind' => 'TABLE',
                          'why' => 'وقائع الميدان والاحالة التاديبية — القوى توثق وتحيل والقرار العمالي في القضية التاديبية'),
    );
}

/**
 * إثباتُ المِرساةِ قياسًا: هل يمسُّ ملفُّ المسارِ المُعلَنِ ما أُعلن له؟
 * @return array [sid, owner, verdict, rule]
 */
function repair01_w5_prove_anchor(mysqli $c, $ROOT, array $a)
{
    if ($a['kind'] === 'GAP' || $a['route'] === '') {
        return array('sid' => '', 'owner' => '', 'verdict' => 'NOT_BUILT',
                     'rule' => ($a['kind'] === 'SPINE') ? 'W5_SPINE_NO_SURFACE' : 'W5_TARGET_GAP');
    }
    $rt = $c->real_escape_string($a['route']);
    $row = $c->query("SELECT screen_id, owner_code, on_disk FROM repair01_screen_registry WHERE route = '$rt' LIMIT 1");
    $row = $row ? $row->fetch_assoc() : null;
    if (!$row) { return array('sid' => '', 'owner' => '', 'verdict' => 'ROUTE_NOT_IN_REGISTRY', 'rule' => 'W5_ANCHOR_UNPROVEN'); }
    if ((int) $row['on_disk'] !== 1) { return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'], 'verdict' => 'ROUTE_NOT_ON_DISK', 'rule' => 'W5_ANCHOR_UNPROVEN'); }
    $path = $ROOT . '/' . $a['route'];
    $src = is_file($path) ? (string) file_get_contents($path) : '';
    if ($src === '') { return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'], 'verdict' => 'FILE_UNREADABLE', 'rule' => 'W5_ANCHOR_UNPROVEN'); }

    $p = preg_quote($a['probe'], '~'); $hit = false; $rule = '';
    if ($a['kind'] === 'TABLE') {
        $hit = (bool) (preg_match('~\b(FROM|INTO|UPDATE|JOIN)\s+`?' . $p . '`?\b~i', $src)
                    || preg_match('~[\'"]' . $p . '[\'"]\s*[,\)]~', $src));
        $rule = 'W5_ROUTE_TOUCHES_TABLE';
    } elseif ($a['kind'] === 'CMP03') {
        $hit = strpos($src, "\$CANONICAL = '" . $a['probe'] . "'") !== false;
        $rule = 'W5_ROUTE_DECLARES_CANONICAL';
    } elseif ($a['kind'] === 'SERVICE') {
        $hit = strpos($src, $a['probe']) !== false;
        $rule = 'W5_ROUTE_REQUIRES_SERVICE';
    }
    return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                 'verdict' => $hit ? 'ANCHORED' : 'ANCHOR_PROBE_MISSED',
                 'rule' => $hit ? $rule : 'W5_ANCHOR_UNPROVEN');
}

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ أحداثُ النطاقِ — الكياناتُ التي تصدر عنها
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **والمقامُ يُشتقُّ من الكياناتِ التي تملكها هذه المرحلة**: `fin_asset`
     (‏`expense.depreciation.recorded`) يمسُّ الأصلَ لكنَّ سطحَه تحت `DEP-03`
     في السجلِّ ومالكَ حدثِه المالية — فعقدُه في موجتِها لا هنا (‏W5-D-05). */
function repair01_w5_entity_types()
{
    return array('asset_intake', 'asset_source_check', 'asset_inspection_order',
                 'equipment', 'asset_use_right', 'asset_assignment', 'asset_exit');
}

/** أحداثُ العمودِ الفقريِّ التي تصدر عن هذه المرحلة — عقدُها قبلَ أوّلِ إطلاق */
function repair01_w5_stage_events()
{
    return array(
        'fleet.asset.intake_requested',
        'fleet.asset.source_verified',
        'fleet.asset.inspection_ordered',
        'fleet.asset.card_issued',
        'fleet.asset.activated',
        'fleet.asset.use_right_granted',
        'fleet.asset.assigned',
        'fleet.asset.exited',
        'fleet.asset.returned',
    );
}
