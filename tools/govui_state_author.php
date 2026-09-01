<?php
/**
 * tools/govui_state_author.php — تأليفُ آلاتِ الحالةِ من الملفاتِ الحاكمة (§14)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المصدرُ الحاكمُ وحدَه**: ورقةُ `08_آلات_الحالة` في الموجاتِ الخمس — 62 آلةً
 *   بسبعةِ أعمدة. ⛔ **ولا تُلفَّق آلةٌ ولا يُخترَع مرجع** (§5 المحظور ④:
 *   «المرجعُ الأجوفُ يُقرأ خُضرةً وهو فراغ»).
 *
 * ◆ **والربطُ بقاعدةٍ مكتوبةٍ لا بالاسمِ وحدَه** (§14 حرفًا). أربعُ درجاتٍ
 *   تُعلَن كلُّها:
 *   ① `EXACT_ENTITY` — `grain_entity` يطابق مفتاحَ الكيانِ حرفًا.
 *   ② `ENTITY_ALIAS` — يطابق أحدَ أسماءِ الكيانِ المعلَنةِ في هذا الملف.
 *   ③ `ROUTE_TOKEN`  — مفردةُ الكيانِ في المسار **مع** موافقةِ الإدارةِ المالكة.
 *   ④ `OWNER_UNIT`   — الإدارةُ وحدَها ⇒ **لا يُربَط**؛ يُعرَض ليُحسَم.
 *   ⛔ **والدرجةُ الرابعةُ لا تُكتب صفًّا** — رابطٌ ضعيفٌ أسوأُ من غيابِه.
 *
 * ◆ **والمقامُ يُصحَّح لا يُجمَّل**: `STATE_MODEL_CONFORMANCE` كان مقامُه
 *   «كلُّ سطحٍ حبّتُه صفٌّ/بندٌ ويملك حقيقتَه» = 392 — فدخلته **سجلَّاتٌ
 *   مرجعيّةٌ وإعداداتٌ** (`units_of_measure` · `ticket_types` · `wf_category`)
 *   لا دورةَ مستندٍ لها، **ولا يوجب لها ملفٌّ حاكمٌ آلةً**. و§20 يقول
 *   `REQUIRED_STATE_MODEL_MISSING` — **المطلوبُ** لا كلُّ مبنيّ.
 *   ⇒ يُعلَن **المقامانِ معًا** ولا يُخفى الأوسع.
 *
 * التشغيل: php tools/govui_state_author.php [--dry] [--list]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/tools/lib/xlsx_io.php';
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$dry  = in_array('--dry', $argv, true);
$list = in_array('--list', $argv, true);

$WAVES = array(
    '04' => '04 · الموجة أ — التشغيل والأصول',
    '05' => '05 · الموجة ب — التعاقد والتوريد',
    '06' => '06 · الموجة ج — المال',
    '07' => '07 · الموجة د — الأشخاص والرقابة',
    '08' => '08 · الموجة هـ — المساحات والتقارير',
);

$nz = function ($s) {
    $s = preg_replace('~[\x{064B}-\x{0652}\x{0640}]~u', '', (string) $s);
    $s = str_replace(array('أ','إ','آ','ى','ة','ؤ','ئ'), array('ا','ا','ا','ي','ه','و','ي'), $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
};

/* ═══ ① جسرُ الإدارةِ ⇒ المساحة — من السجلِّ لا من الرأس ═══════════════════ */
$unitWs = array();
$r = $conn->query("SELECT workspace_id, name_ar FROM nav_workspaces WHERE active = 1");
while ($x = $r->fetch_assoc()) { $unitWs[$nz($x['name_ar'])] = $x['workspace_id']; }
/* أسماءُ الورقةِ تختصر أحيانًا — ومطابقةُ الاحتواءِ تُعلَن قاعدةً */
$wsOf = function ($unitAr) use ($unitWs, $nz) {
    $u = $nz($unitAr);
    if (isset($unitWs[$u])) { return $unitWs[$u]; }
    foreach ($unitWs as $n => $id) {
        if ($n !== '' && (mb_strpos($n, $u) !== false || mb_strpos($u, $n) !== false)) { return $id; }
    }
    return null;
};

/* ═══ ② قراءةُ الآلاتِ الاثنتَينِ والستّين ══════════════════════════════════ */
$models = array();
foreach ($WAVES as $wv => $file) {
    $path = $ROOT . '/docs/REPAIR01_20260823/' . $file . '.xlsx';
    if (!is_file($path)) { fwrite(STDERR, "⛔ مصدرٌ مفقود: {$file}\n"); exit(1); }
    $wb = xlsx_read($path);
    foreach ($wb as $sh => $rows) {
        if (mb_strpos($sh, 'آلات_الحالة') === false) { continue; }
        foreach ($rows as $i => $rw) {
            if ($i < 2) { continue; }
            $unit = trim((string) ($rw[0] ?? ''));
            $ent  = trim((string) ($rw[1] ?? ''));
            if ($ent === '') { continue; }
            /* «Contract — عقد العميل» ⇒ مفتاحُ الكيانِ `contract` */
            $code = $ent;
            if (preg_match('~^\s*([A-Za-z][A-Za-z0-9_]*)\s*[—\-–]~u', $ent, $m)) { $code = $m[1]; }
            $key  = strtolower($code);
            $mc   = 'SM-W' . $wv . '-' . strtoupper($key);
            $flow = trim((string) ($rw[2] ?? ''));
            /* عدُّ الحالاتِ من سهمِ التدفُّق — رقمٌ مقيسٌ لا مقدَّر */
            $st   = count(preg_split('~\s*(?:→|=>|⇒)\s*~u', $flow, -1, PREG_SPLIT_NO_EMPTY));
            $models[$mc] = array(
                'model_code' => $mc, 'wave' => $wv, 'unit_ar' => $unit,
                'workspace_id' => $wsOf($unit), 'entity_code' => $key, 'entity_ar' => $ent,
                'states_flow' => $flow, 'forbidden' => trim((string) ($rw[3] ?? '')),
                'transition_owner' => trim((string) ($rw[4] ?? '')),
                'preconditions' => trim((string) ($rw[5] ?? '')),
                'reopen_cancel' => trim((string) ($rw[6] ?? '')),
                'state_count' => $st, 'source_file' => $file, 'source_sheet' => $sh,
                'source_row' => $i + 1,
            );
        }
    }
}
printf("══ تأليفُ آلاتِ الحالة (§14) ══\n  آلاتٌ في الملفاتِ الحاكمة: **%d**\n", count($models));

/* حارسُ الاكتمال: آلةٌ بعمودٍ فارغٍ ناقصةٌ — تُعلَن ولا تُكتب خضراء */
$thin = array();
foreach ($models as $mc => $m) {
    foreach (array('states_flow', 'forbidden', 'transition_owner', 'preconditions', 'reopen_cancel') as $f) {
        if ($m[$f] === '') { $thin[$mc][] = $f; }
    }
}
printf("  ناقصةُ عمودٍ حاكمٍ: %d\n", count($thin));

/* ═══ ③ الأسطحُ المرشَّحةُ — مدى المقياسِ نفسِه ══════════════════════════════ */
$SC = "on_disk = 1 AND ownership_verdict <> 'RETIRE'
       AND grain_cardinality IN ('ROW','LINE') AND grain_fact_scope = 'OWN_FACT'";
$surf = array();
$r = $conn->query("SELECT s.screen_id, s.route, s.grain_entity, s.state_model_ref, s.owner_code,
                          s.canonical_label_ar
                     FROM repair01_screen_registry s
                    WHERE {$SC}");
while ($x = $r->fetch_assoc()) { $surf[$x['screen_id']] = $x; }
printf("  أسطحٌ في مدى المقياس: %d\n", count($surf));

/* مساحةُ كلِّ سطحٍ من موضعِه الحاكمِ إن وُجد */
$wsOfRoute = array();
$r = $conn->query("SELECT workspace_id, route FROM nav_placements WHERE active = 1 AND route IS NOT NULL");
while ($x = $r->fetch_assoc()) {
    $k = strtolower(trim(preg_replace('~\.php$~i', '', (string) $x['route']), '/'));
    if (!isset($wsOfRoute[$k])) { $wsOfRoute[$k] = $x['workspace_id']; }
}

/* ═══ ④ الربطُ بأربعِ درجاتٍ — والرابعةُ لا تُكتب ═════════════════════════ */
/** أسماءُ الكيانِ البديلةُ **معلَنةٌ في هذا الملفِّ** لا مخترَعةٌ وقتَ التشغيل */
$ALIAS = array(
    'contract'   => array('contracts', 'sal_contracts', 'client_contracts'),
    'claim'      => array('claims', 'contract_claims'),
    'container'  => array('op_containers', 'containers', 'contract_commitments'),
    'obligation' => array('contract_obligations', 'obligations'),
    'slot'       => array('supplier_slots', 'sup_handover_slot', 'sup_slot_allocation_quota', 'sup_closure'),
    'scontract'  => array('supplier_contracts', 'sup_contract'),
    'entitlement'=> array('supplier_entitlements', 'sup_aging_obligation'),
    'coverage'   => array('supplier_coverage', 'sup_replacement_reserve', 'shares_coverage'),
    'pr'         => array('proc_requests', 'purchase_requests', 'requests_proc'),
    'po'         => array('proc_orders', 'purchase_orders', 'orders_proc'),
    'match'      => array('proc_three_way_match', 'three_way_match'),
    'grn'        => array('proc_grn', 'stock_receipts', 'goods_receipt'),
    'issue'      => array('proc_issues', 'stock_issues'),
    'count'      => array('proc_stock_count', 'stock_count'),
    'unit'       => array('units', 'timesheet', 'unit_records'),
    'stop_event' => array('ops_stop_decisions', 'stop_decisions'),
    'move_request' => array('ops_resource_move_orders', 'move_orders'),
    'site_day'   => array('site_days'),
    'site_request' => array('site_supply_request', 'site_request_batch', 'site_request_item',
                            'site_state_change_request'),
    'site'       => array('sites', 'site_suspension'),
    'asset'      => array('equipments', 'fleet_assets', 'assets'),
    'inspection_order' => array('inspection_orders', 'flt_inspection'),
    'meter'      => array('equipment_meters', 'meters'),
    'work_order' => array('mnt_order', 'maintenance_orders', 'work_orders'),
    'rts_certificate' => array('mnt_rts', 'rts_certificates'),
    'pm_plan'    => array('mnt_pm_plan', 'pm_plans'),
    'workforce_need' => array('workforce_requirement', 'wf_project_allocation'),
    'assignment' => array('wf_equipment_shift_assignment', 'assignments'),
    'rotation'   => array('wf_rotation', 'rotations'),
    'transport_order' => array('trp_transfer_order_form', 'transfer_orders'),
    'damage_claim' => array('trp_damage_claims', 'damage_claims'),
    'fcontract'  => array('fin_contracts', 'financing_contracts'),
    'financing_need' => array('fin_needs', 'financing_needs'),
    'ownership'  => array('fin_ownership', 'ownership_records'),
    'period'     => array('fin_periods', 'accounting_periods'),
    'je'         => array('fin_journal_entries', 'journal_entries'),
    'invoice'    => array('fin_invoices', 'invoices'),
    'payment'    => array('tre_payments', 'payment_orders'),
    'receipt'    => array('tre_receipts', 'receipts'),
    'bank_recon' => array('tre_bank_recon', 'bank_reconciliation'),
    'employment_contract' => array('employee_contracts', 'employment_contracts'),
    'payroll_run' => array('payroll_runs', 'payroll'),
    'disciplinary_case' => array('hr_disciplinary', 'disciplinary_cases'),
    'ticket'     => array('tkt_tickets_list', 'tkt_ticket_form', 'tickets'),
    'routing'    => array('ticket_routing', 'tkt_routing'),
    'escalation' => array('ticket_escalation_rules', 'tkt_escalation'),
    'risk'       => array('risk_register', 'risks'),
    'treatment'  => array('risk_treatments', 'treatments'),
    'kri'        => array('risk_kri', 'kris'),
    'engagement' => array('iaf_engagements', 'engagements'),
    'finding'    => array('iaf_findings', 'findings'),
    'audit_plan' => array('iaf_plan', 'iaf_audit_programs', 'audit_plans'),
    'policy'     => array('gov_policies', 'policies'),
    'waiver'     => array('gov_waivers', 'waivers'),
    'governance_case' => array('gov_cases', 'governance_cases'),
    'launch_intent' => array('launch_intents'),
    'my_task'    => array('portal_my_tasks', 'my_tasks', 'tasks'),
    'executive_decision' => array('exec_decisions', 'ceo_decisions'),
    'strategic_decision' => array('strategic_decisions'),
    'crisis'     => array('crisis_events', 'crises'),
    'deputy_decision' => array('vp_decisions', 'dvp_vp_pending_actions'),
    'deputy_action' => array('exec_action_followup', 'vp_actions'),
);

$byEntity = array(); $byAlias = array();
foreach ($models as $mc => $m) {
    $byEntity[$m['entity_code']] = $mc;
    if (isset($ALIAS[$m['entity_code']])) {
        foreach ($ALIAS[$m['entity_code']] as $a) { $byAlias[strtolower($a)] = $mc; }
    }
}

$binds = array(); $unmatched = array(); $weak = array();
foreach ($surf as $sid => $s) {
    $ge = strtolower(trim((string) $s['grain_entity']));
    $rt = strtolower(trim(preg_replace('~\.php$~i', '', (string) $s['route']), '/'));
    $ws = isset($wsOfRoute[$rt]) ? $wsOfRoute[$rt] : null;
    $mc = null; $conf = ''; $rule = ''; $wit = '';

    if ($ge !== '' && isset($byEntity[$ge])) {
        $mc = $byEntity[$ge]; $conf = 'EXACT_ENTITY';
        $rule = 'grain_entity يطابق مفتاحَ الكيانِ في ورقةِ 08 حرفًا';
        $wit  = 'grain_entity=' . $ge;
    } elseif ($ge !== '' && isset($byAlias[$ge])) {
        $mc = $byAlias[$ge]; $conf = 'ENTITY_ALIAS';
        $rule = 'grain_entity أحدُ أسماءِ الكيانِ المعلَنةِ في tools/govui_state_author.php';
        $wit  = 'alias(' . $ge . ') ⇒ ' . $models[$mc]['entity_code'];
    } else {
        /* ③ مفردةُ الكيانِ في المسارِ **مع** موافقةِ المساحةِ المالكة */
        foreach ($models as $c => $m) {
            $tok = $m['entity_code'];
            if (mb_strlen($tok) < 4) { continue; }          /* مفردةٌ قصيرةٌ تُطابِق زورًا */
            if (strpos($rt, $tok) === false) { continue; }
            if ($m['workspace_id'] === null || $ws === null || $m['workspace_id'] !== $ws) { continue; }
            $mc = $c; $conf = 'ROUTE_TOKEN';
            $rule = 'مفردةُ الكيانِ في المسارِ **و**مساحةُ الموضعِ = مساحةُ الآلة';
            $wit  = 'route~' . $tok . ' · ws=' . $ws;
            break;
        }
    }

    if ($mc === null) {
        /* ④ الإدارةُ وحدَها — ⛔ لا يُكتب رابط */
        $unmatched[] = array($sid, $s['route'], $ge, $ws);
        continue;
    }
    $binds[] = array('bind_id' => 'SB-' . strtoupper(substr(sha1($sid . $mc), 0, 16)),
                     'model_code' => $mc, 'screen_id' => $sid, 'route' => (string) $s['route'],
                     'workspace_id' => $ws, 'grain_entity' => $ge, 'bind_rule' => $rule,
                     'bind_witness' => $wit, 'confidence' => $conf);
    if ($conf === 'ROUTE_TOKEN') { $weak[] = $sid; }
}

/* ═══ ④-ب فرزُ غيرِ المربوطِ بدليلٍ مقيسٍ — ⛔ لا بانطباع ═══════════════════
   ◆ **السؤالُ الحاسم**: أللسطحِ حالاتٌ أصلًا؟ يُقاس بعمودِ حالةٍ في جدولِ
     كيانِه — فسجلٌّ مرجعيٌّ (`units_of_measure` · `ticket_types`) لا عمودَ
     حالةٍ له، **ولا يوجب له ملفٌّ حاكمٌ آلة** ⇒ خارجَ «المطلوب» بدليل.
   ◆ **وما له عمودُ حالةٍ ولا آلةَ في الملفات**: القرارُ **غيرُ موجودٍ** في
     المصدرِ الحاكم ⇒ `BLOCKED_OWNER` بنصِّ §22 — يُسمّى ويُعرَض ولا يُلفَّق.
   ⛔ **ولا يُستبعَد صفٌّ صامتًا**: البابانِ يُعَدّان ويُسمَّيان. */
$STATE_COL = '~(^|_)(status|state|stage|phase|حال)($|_)~i';
$tblCols = array();
$q = $conn->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()");
while ($q && ($x = $q->fetch_assoc())) { $tblCols[strtolower($x['t'])][] = $x['c']; }

$blockedOwner = array(); $notRequired = array();
foreach ($unmatched as $u) {
    list($sid, $route, $ge, $ws) = $u;
    $has = false; $witCol = '';
    if ($ge !== '' && isset($tblCols[$ge])) {
        foreach ($tblCols[$ge] as $c) {
            if (preg_match($STATE_COL, $c)) { $has = true; $witCol = $c; break; }
        }
    }
    $row = array($sid, $route, $ge, $ws, $witCol, isset($tblCols[$ge]));
    if ($has) { $blockedOwner[] = $row; } else { $notRequired[] = $row; }
}

$byConf = array();
foreach ($binds as $b) { $byConf[$b['confidence']] = (isset($byConf[$b['confidence']]) ? $byConf[$b['confidence']] : 0) + 1; }
printf("\n  ── الربط ──\n  مربوطٌ: **%d** من %d سطحًا · غيرُ مربوطٍ: **%d**\n",
    count($binds), count($surf), count($unmatched));
foreach ($byConf as $k => $v) { printf("     %-14s %4d\n", $k, $v); }

printf("\n  ── فرزُ غيرِ المربوطِ (‏%d) بدليلٍ مقيس ──\n", count($unmatched));
printf("     `BLOCKED_OWNER` — له عمودُ حالةٍ ولا آلةَ في الملفاتِ الحاكمة: **%d**\n",
    count($blockedOwner));
printf("     `NOT_REQUIRED_NO_STATE_FIELD` — سجلٌّ مرجعيٌّ/إعدادٌ بلا عمودِ حالة: **%d**\n",
    count($notRequired));
$noTbl = 0; foreach ($notRequired as $x) { if (!$x[5]) { $noTbl++; } }
printf("        (‏ومنها %d كيانًا لا جدولَ باسمِه — فالحالةُ غيرُ مقيسةٍ فيه أصلًا)\n", $noTbl);

/* أيُّ آلةٍ لم يجدْ لها سطحٌ؟ — خبرٌ يُعلَن: آلةٌ حاكمةٌ بلا سطحٍ مبنيّ */
$used = array();
foreach ($binds as $b) { $used[$b['model_code']] = true; }
$orphanModels = array_diff(array_keys($models), array_keys($used));
printf("  آلاتٌ حاكمةٌ بلا سطحٍ في المدى: **%d**\n", count($orphanModels));

if ($list) {
    echo "\n  ── آلاتٌ بلا سطح ──\n";
    foreach ($orphanModels as $mc) {
        printf("     %-26s %-30s %s\n", $mc, mb_substr($models[$mc]['entity_ar'], 0, 28),
            $models[$mc]['workspace_id'] ?: '— بلا مساحة');
    }
    echo "\n  ── أسطحٌ بلا آلةٍ حاكمة (‏لا يوجب لها ملفٌّ آلة) ──\n";
    foreach ($unmatched as $u) { printf("     %-12s %-46s %-26s %s\n", $u[0], mb_substr($u[1], 0, 44), $u[2], $u[3]); }
}

if ($dry) { echo "\n  ◆ `--dry` — لم يُكتب صفّ\n"; exit(0); }

/* ═══ ⑤ الكتابة ═══════════════════════════════════════════════════════════ */
$now = date('Y-m-d H:i:s');
$conn->query("DELETE FROM gov_state_model_bind");
$conn->query("DELETE FROM gov_state_models");
$stM = $conn->prepare("INSERT INTO gov_state_models
    (model_code, wave, unit_ar, workspace_id, entity_code, entity_ar, states_flow, forbidden,
     transition_owner, preconditions, reopen_cancel, state_count, source_file, source_sheet,
     source_row, version, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?)");
$nM = 0;
foreach ($models as $m) {
    $stM->bind_param('ssssssssssssssis', $m['model_code'], $m['wave'], $m['unit_ar'],
        $m['workspace_id'], $m['entity_code'], $m['entity_ar'], $m['states_flow'], $m['forbidden'],
        $m['transition_owner'], $m['preconditions'], $m['reopen_cancel'], $m['state_count'],
        $m['source_file'], $m['source_sheet'], $m['source_row'], $now);
    if ($stM->execute()) { $nM++; } else { echo "  ✘ {$m['model_code']}: " . $stM->error . "\n"; }
}
$stB = $conn->prepare("INSERT INTO gov_state_model_bind
    (bind_id, model_code, screen_id, route, workspace_id, grain_entity, bind_rule, bind_witness,
     confidence, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
$nB = 0;
foreach ($binds as $b) {
    $stB->bind_param('ssssssssss', $b['bind_id'], $b['model_code'], $b['screen_id'], $b['route'],
        $b['workspace_id'], $b['grain_entity'], $b['bind_rule'], $b['bind_witness'],
        $b['confidence'], $now);
    if ($stB->execute()) { $nB++; } else { echo "  ✘ {$b['screen_id']}: " . $stB->error . "\n"; }
}

/* ⑥ **والمرجعُ في سجلِّ الشاشاتِ يُملأ من الرابطِ المكتوبِ وحدَه** — فلا مرجعَ
   أجوفَ يُقرأ خُضرةً. ⛔ ولا يُمَسُّ مرجعٌ من جولةٍ سابقةٍ إن كان قائمًا. */
$nR = 0;
foreach ($binds as $b) {
    $ref = 'GOV_SM#' . $b['model_code'];
    $q = $conn->prepare("UPDATE repair01_screen_registry SET state_model_ref = ?
                          WHERE screen_id = ? AND (state_model_ref = '' OR state_model_ref IS NULL)");
    $q->bind_param('ss', $ref, $b['screen_id']);
    $q->execute();
    $nR += $q->affected_rows;
    $q->close();
}
printf("\n  ⇒ كُتب: آلاتٌ **%d** · روابطُ **%d** · ومراجعُ سجلٍّ مُلئت **%d**\n", $nM, $nB, $nR);
if ($weak) { printf("  ◆ ومنها بدرجةِ `ROUTE_TOKEN` (‏أضعفُ درجةٍ تُكتب): %d — معلَنةٌ لا مخفيّة\n", count($weak)); }
