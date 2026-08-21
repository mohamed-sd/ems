<?php
/**
 * tools/trim_timesheet_family.php — تقليصُ سجلاتِ عائلةِ «القيدِ اليومي»
 * ───────────────────────────────────────────────────────────────────────────
 * الغرض: تصغيرُ ملفِّ القاعدةِ بحذفِ الحشوِ من جدولِ التايم‑شيت وما يرتبط به،
 * مع **إبقاءِ ما لا يقلُّ عن ٢٠٠ سجلٍّ متنوِّعٍ في كلِّ جدول** — والتنوُّعُ
 * محسوبٌ لا عشوائي: كلُّ قيمةٍ فئويةٍ مميَّزةٍ (نوعٌ · ورديةٌ · حالةٌ · طرفٌ …)
 * تبقى ممثَّلةً، وكلُّ صفٍّ أبٍ لسجلٍّ في جدولٍ ابنٍ صغيرٍ يبقى مع ابنِه.
 *
 * ◆ **الترتيبُ هو الأمان**: تُبنى مجموعاتُ «الإبقاء» كاملةً أولًا، ثم يُحذَف
 *   ما عداها. فلا يُحذَف أبٌ قبلَ أن يُعلَم أنَّ ابنَه ذاهبٌ معه.
 *
 * ◆ **وسجلُّ الوقائعِ لا يُمَسّ افتراضًا**: من 5203 واقعةٍ من نوع `timesheet`
 *   تُثبِّت 5198 واقعةً سلسلةً ماليةً عبرَ `fin_financial_events.root_event_id`
 *   (قيدُ FK بـRESTRICT). وحمولةُ الواقعةِ **مكتفيةٌ بذاتها** (ساعاتٌ وإنتاجٌ
 *   وتاريخٌ ومراجعُ خام)، فحذفُ صفِّ التايم‑شيت لا يُفقد الدفترَ تفسيرَه —
 *   تبقى إشارةٌ رخوةٌ (`entity_id`) بلا هدفٍ فحسب، ويُعلَن عددُها في الحصيلة.
 *   وبـ`--trim-events` تُحذَف الوقائعُ اليتيمةُ **غيرُ المثبَّتةِ** ماليًّا فقط.
 *
 * التشغيل:
 *   php tools/trim_timesheet_family.php                    # خطةٌ فقط (لا حذف)
 *   php tools/trim_timesheet_family.php --apply
 *   php tools/trim_timesheet_family.php --apply --ts=600 --ue=300
 */
if (PHP_SAPI !== 'cli') { die("CLI only\n"); }
require_once __DIR__ . '/../includes/env.php';

/* ── الخيارات ───────────────────────────────────────────────────────────── */
$opt = array('apply' => false, 'ts' => 600, 'ue' => 300, 'floor' => 200, 'trim_events' => false, 'no_optimize' => false);
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--apply')           { $opt['apply'] = true; }
    elseif ($a === '--trim-events') { $opt['trim_events'] = true; }
    elseif ($a === '--no-optimize') { $opt['no_optimize'] = true; }
    elseif (preg_match('/^--ts=(\d+)$/', $a, $m))    { $opt['ts'] = (int) $m[1]; }
    elseif (preg_match('/^--ue=(\d+)$/', $a, $m))    { $opt['ue'] = (int) $m[1]; }
    elseif (preg_match('/^--floor=(\d+)$/', $a, $m)) { $opt['floor'] = (int) $m[1]; }
    else { fwrite(STDERR, "خيارٌ مجهول: $a\n"); exit(2); }
}
if ($opt['ts'] < $opt['floor'] || $opt['ue'] < $opt['floor']) {
    fwrite(STDERR, "✘ الهدفُ دونَ الأرضية ({$opt['floor']}) — ارفعْ ‎--ts/--ue\n");
    exit(2);
}

/* ── الاتصال ────────────────────────────────────────────────────────────── */
$host = ems_env('DB_HOST', 'localhost:3307');
$port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
if ($host === 'localhost') { $host = '127.0.0.1'; }
$db  = ems_env('DB_NAME', 'equipation_manage');
$pdo = new PDO(
    "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
    ems_env('DB_MIGRATOR_USER', ems_env('DB_USER')),
    ems_env('DB_MIGRATOR_PASS', ems_env('DB_PASS')),
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false)
);

/* ── عدّةٌ صغيرة ────────────────────────────────────────────────────────── */
function col(PDO $p, $sql, $args = array())  { $s = $p->prepare($sql); $s->execute($args); return $s->fetchAll(PDO::FETCH_COLUMN); }
function one(PDO $p, $sql, $args = array())  { $s = $p->prepare($sql); $s->execute($args); return $s->fetchColumn(); }
function hasTable(PDO $p, $t) { return (bool) one($p, "SELECT COUNT(*) FROM information_schema.TABLES WHERE table_schema=DATABASE() AND table_name=?", array($t)); }
function hasCol(PDO $p, $t, $c) { return (bool) one($p, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_schema=DATABASE() AND table_name=? AND column_name=?", array($t, $c)); }

/**
 * انتقاءٌ **موزَّعٌ** لا مقتطَع: يأخذ $k معرِّفًا متباعدًا عبرَ كاملِ المدى،
 * فلا تتكدَّس العيِّنةُ في أقدمِ السجلاتِ ولا في أحدثِها.
 */
function spread(array $ids, $k)
{
    $n = count($ids);
    if ($k <= 0)  { return array(); }
    if ($k >= $n) { return $ids; }
    $out = array();
    $step = $n / $k;
    for ($i = 0; $i < $k; $i++) { $out[] = $ids[(int) floor($i * $step)]; }
    return array_values(array_unique($out));
}

/**
 * تمثيلُ كلِّ قيمةٍ فئوية: لكلِّ قيمةٍ مميَّزةٍ لـ$expr خُذْ حتى $per معرِّفًا
 * موزَّعًا — به يُضمَن ألا تختفيَ قيمةٌ نادرةٌ (نوعٌ بـ١٢ صفًّا مثلًا).
 */
function perValue(PDO $p, $table, $expr, $per)
{
    $keep = array();
    foreach (col($p, "SELECT DISTINCT $expr AS v FROM `$table`") as $v) {
        $cond = ($v === null) ? "$expr IS NULL" : "$expr = " . $p->quote($v);
        foreach (spread(col($p, "SELECT id FROM `$table` WHERE $cond ORDER BY id"), $per) as $id) {
            $keep[(int) $id] = true;
        }
    }
    return $keep;
}

function addAll(array &$keep, $ids) { foreach ($ids as $i) { $keep[(int) $i] = true; } }

function fillTo(PDO $p, array &$keep, $table, $target)
{
    if (count($keep) >= $target) { return; }
    $rest = array();
    foreach (col($p, "SELECT id FROM `$table` ORDER BY id") as $i) {
        if (!isset($keep[(int) $i])) { $rest[] = $i; }
    }
    addAll($keep, spread($rest, $target - count($keep)));
}

function idList(array $keep) { return $keep ? implode(',', array_map('intval', array_keys($keep))) : '0'; }

/* ── الحالةُ قبل ────────────────────────────────────────────────────────── */
$family = array(
    'timesheet', 'timesheet_approvals', 'timesheet_approval_notes', 'timesheet_failure_hours',
    'unit_entries', 'unit_approvals', 'unit_time_log', 'unit_party_awards',
    'unit_capacity_flags', 'unit_match_overrides', 'ems_business_events', 'ems_event_deliveries',
);
$before = array();
foreach ($family as $t) { $before[$t] = hasTable($pdo, $t) ? (int) one($pdo, "SELECT COUNT(*) FROM `$t`") : null; }

echo "══════════════════════════════════════════════════════════════\n";
echo "  تقليصُ عائلةِ التايم‑شيت " . ($opt['apply'] ? "— تنفيذ" : "— خطةٌ فقط (dry-run)") . "\n";
echo "  الهدف: timesheet≈{$opt['ts']} · unit_entries≈{$opt['ue']} · الأرضية {$opt['floor']}\n";
echo "══════════════════════════════════════════════════════════════\n\n";

/* ◆ **الأداةُ تُعاد بلا أثرٍ ثانٍ**: بلا هذا الحارسِ تحلق كلُّ إعادةِ تشغيلٍ
     طبقةً جديدةً — فمجموعةُ الإبقاءِ تُشتَقُّ في المرةِ الثانيةِ من مجتمَعٍ
     أصغرَ فتخرج أصغرَ منه، وهكذا حتى تستقرَّ عندَ ما يفرضه التنوُّعُ وحدَه.
     وذلك انحدارٌ لا فائدةَ فيه: فما دخلَ نطاقَ هدفِه (١٫٥ ضعفًا فأقلَّ) يُعَدُّ
     مستقرًّا ولا يُمَسّ — وإلا كان كلُّ تشغيلٍ يقضم بلا مقابل. */
function converged($current, $target) { return $current !== null && $current <= (int) ceil($target * 1.5); }
$atTarget = converged($before['timesheet'], $opt['ts']);

/* ═══════════ ① مجموعةُ إبقاءِ timesheet ═══════════════════════════════════ */
$kTs = array();
if ($atTarget) { addAll($kTs, col($pdo, "SELECT id FROM timesheet")); }

/* (أ) كلُّ أبٍ لسجلٍّ في جدولٍ ابنٍ صغيرٍ يبقى — وإلا يتيتَّم الابن */
foreach (array('timesheet_approvals', 'timesheet_approval_notes', 'timesheet_failure_hours') as $child) {
    if (hasTable($pdo, $child) && hasCol($pdo, $child, 'timesheet_id')) {
        addAll($kTs, col($pdo, "SELECT DISTINCT timesheet_id FROM `$child` WHERE timesheet_id IS NOT NULL"));
    }
}
$nParents = count($kTs);

/* (ب) تمثيلُ كلِّ قيمةٍ فئوية — النادرُ لا يُفقَد */
foreach (array('type', 'shift', 'status', 'transport_type', 'meters_type', 'fault_type', 'fault_department') as $c) {
    if (hasCol($pdo, 'timesheet', $c)) { $kTs += perValue($pdo, 'timesheet', "`$c`", 20); }
}
/* (ج) انتشارٌ زمني: حتى ٢٠ صفًّا لكلِّ سنة */
$kTs += perValue($pdo, 'timesheet', "YEAR(`date`)", 20);
$nDiverse = count($kTs) - $nParents;

/* (د) صفوفٌ **حيَّةُ السلسلة**: لها استحقاقاتُ أطراف. بها وحدَها يبقى
       `unit_party_awards` فوقَ الأرضية وتبقى سلسلةُ المروحةِ قابلةً للتجريب،
       والانتقاءُ لكلِّ تركيبةِ (طرفٍ · حالةِ استحقاق) فتنجو النادرةُ منها. */
$nAward = 0;
if (hasTable($pdo, 'unit_party_awards')) {
    $combos = $pdo->query("SELECT DISTINCT party, entitlement_state FROM unit_party_awards WHERE source_kind='timesheet'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($combos as $c) {
        $ids = col($pdo, "SELECT DISTINCT a.source_ref FROM unit_party_awards a JOIN timesheet t ON t.id = a.source_ref
                          WHERE a.source_kind='timesheet' AND a.party = ? AND a.entitlement_state = ? ORDER BY a.source_ref",
                   array($c['party'], $c['entitlement_state']));
        addAll($kTs, spread($ids, 45));
    }
    $nAward = count($kTs) - $nParents - $nDiverse;
}

/* (هـ) الإكمالُ إلى الهدفِ بعيِّنةٍ موزَّعة */
fillTo($pdo, $kTs, 'timesheet', $opt['ts']);
$tsKeep = count($kTs);

if ($atTarget) {
    printf("① timesheet: %s — مستقرٌّ في نطاقِ هدفِه سلفًا (≤ %s) فلا يُمَسّ\n",
        number_format($before['timesheet']), number_format((int) ceil($opt['ts'] * 1.5)));
} else {
    printf("① timesheet: %s → %s   (أبٌ لأبناءَ %d · تنوُّعٌ فئويٌّ %d · حيُّ السلسلة %d · إكمالٌ %d)\n",
        number_format($before['timesheet']), number_format($tsKeep),
        $nParents, $nDiverse, $nAward, $tsKeep - $nParents - $nDiverse - $nAward);
}

/* ═══════════ ② مجموعةُ إبقاءِ unit_entries ════════════════════════════════ */
$kUe = array();
$nUeParents = 0;
$ueAtTarget = converged($before['unit_entries'], $opt['ue']);
if ($ueAtTarget) { addAll($kUe, col($pdo, "SELECT id FROM unit_entries")); }
if (hasTable($pdo, 'unit_entries')) {
    /* (أ) أبناءُ FK — والأبُ يبقى مع ابنِه (fk_umo_entry بقيدِ RESTRICT) */
    foreach (array('unit_capacity_flags', 'unit_match_overrides') as $child) {
        if (hasTable($pdo, $child)) {
            addAll($kUe, col($pdo, "SELECT DISTINCT entry_id FROM `$child` WHERE entry_id IS NOT NULL"));
        }
    }
    $nUeParents = count($kUe);

    /* (ب) تمثيلُ كلِّ قيمةٍ فئوية */
    foreach (array('state', 'unit_type', 'record_basis', 'shift', 'client_match_state', 'client_decision',
                   'entity_layer', 'capacity_flag', 'qty_billable', 'cap_context_state',
                   'cap_role_snapshot', 'cap_measure_code', 'revision_kind') as $c) {
        if (hasCol($pdo, 'unit_entries', $c)) { $kUe += perValue($pdo, 'unit_entries', "`$c`", 20); }
    }

    /* (ج) تغطيةُ تنوُّعِ سجلِّ الزمن: كلُّ تركيبةِ (حالةٍ تشغيليةٍ · طرفٍ مسؤولٍ ·
           نوعِ التزام) تجد بعدَ الحذفِ قيدًا حاملًا لها */
    if (hasTable($pdo, 'unit_time_log')) {
        $combos = $pdo->query("SELECT DISTINCT ops_state, resp_party, obligation_type FROM unit_time_log")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($combos as $c) {
            $obC = ($c['obligation_type'] === null) ? 'l.obligation_type IS NULL' : 'l.obligation_type = ' . $pdo->quote($c['obligation_type']);
            $ids = col($pdo, "SELECT DISTINCT l.entry_id FROM unit_time_log l JOIN unit_entries e ON e.id = l.entry_id
                              WHERE l.ops_state = ? AND l.resp_party = ? AND $obC ORDER BY l.entry_id",
                       array($c['ops_state'], $c['resp_party']));
            addAll($kUe, spread($ids, 12));
        }
    }
    /* (د) تغطيةُ تنوُّعِ الاعتمادات: كلُّ (مرحلةٍ · قرار) */
    if (hasTable($pdo, 'unit_approvals')) {
        $combos = $pdo->query("SELECT DISTINCT stage, decision FROM unit_approvals")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($combos as $c) {
            $ids = col($pdo, "SELECT DISTINCT entry_id FROM unit_approvals WHERE stage = ? AND decision = ? ORDER BY entry_id",
                       array($c['stage'], $c['decision']));
            addAll($kUe, spread($ids, 12));
        }
    }
    /* (هـ) مصادرُ استحقاقاتِ «قيدِ الوحدة» */
    if (hasTable($pdo, 'unit_party_awards')) {
        addAll($kUe, col($pdo, "SELECT DISTINCT source_ref FROM unit_party_awards WHERE source_kind='unit_record'"));
    }

    fillTo($pdo, $kUe, 'unit_entries', $opt['ue']);
}
$ueKeep = count($kUe);
if ($ueAtTarget) {
    printf("② unit_entries: %s — مستقرٌّ في نطاقِ هدفِه سلفًا (≤ %s) فلا يُمَسّ\n",
        number_format($before['unit_entries']), number_format((int) ceil($opt['ue'] * 1.5)));
} else {
    printf("② unit_entries: %s → %s   (أبٌ لأبناءَ %d · وبقيتُها تنوُّعٌ وتغطيةٌ وإكمال)\n",
        number_format($before['unit_entries']), number_format($ueKeep), $nUeParents);
}

/* ═══════════ ②-ب إغلاقُ رابطِ الكتابةِ المزدوجة ═══════════════════════════ */
/* ◆ **`unit_entries.sync_uuid = 'ts:<id>'` رابطٌ رخوٌ لا يراه أيُّ قيدِ FK** —
     وهو مرآةُ صفِّ الدوامِ في السجلِّ القانوني. وقطعُه من طرفٍ واحدٍ يخلق
     «مرآةً يتيمة»: أسقطَ ذلك حزمتَي `unit_reconcile_test` (20/0 ← 11/9)
     و`timesheet_time_lines_test` (17/0 ← 11/6) — وكلتاهما تُثبِّت نافذةَ
     المطابقة 2027-02-01…14 عند **50 صفًّا و50 مرآةً** بالضبط.
     فالزوجُ يبقى كاملًا أو يذهب كاملًا. و147 زوجًا فقط ⇒ يُبقى كلُّه،
     فتُصان النافذةُ بلا تاريخٍ مكتوبٍ في الشيفرة. */
$pairs = $pdo->query("SELECT id, CAST(SUBSTRING(sync_uuid, 4) AS UNSIGNED) ts
                      FROM unit_entries WHERE sync_uuid LIKE 'ts:%'")->fetchAll(PDO::FETCH_ASSOC);
$addedTs = 0; $addedUe = 0;
foreach ($pairs as $p) {
    if (!isset($kUe[(int) $p['id']])) { $kUe[(int) $p['id']] = true; $addedUe++; }
    if (!isset($kTs[(int) $p['ts']])) { $kTs[(int) $p['ts']] = true; $addedTs++; }
}
$tsKeep = count($kTs);
$ueKeep = count($kUe);
printf("②-ب رابطُ الكتابةِ المزدوجة: %d زوجًا مُغلَقًا (+%d صفَّ دوامٍ · +%d مرآة)\n"
     . "    ⇒ المُبقى نهائيًّا: timesheet %s · unit_entries %s\n",
    count($pairs), $addedTs, $addedUe, number_format($tsKeep), number_format($ueKeep));

/* ═══════════ ③ الأبناءُ المشتقَّة — تنبُّؤٌ قبلَ أيِّ حذف ═══════════════════ */
$tsIn = idList($kTs);
$ueIn = idList($kUe);

$predict = array();
$predict['unit_approvals']    = (int) one($pdo, "SELECT COUNT(*) FROM unit_approvals WHERE entry_id IN ($ueIn)");
$predict['unit_time_log']     = (int) one($pdo, "SELECT COUNT(*) FROM unit_time_log  WHERE entry_id IN ($ueIn)");
$predict['unit_party_awards'] = (int) one($pdo, "SELECT COUNT(*) FROM unit_party_awards
                                                 WHERE (source_kind='timesheet'   AND source_ref IN ($tsIn))
                                                    OR (source_kind='unit_record' AND source_ref IN ($ueIn))");
/* ◆ **الواقعةُ المثبَّتةُ ماليًّا لا تُحذَف**: `fin_financial_events.root_event_id`
     قيدُ FK بـRESTRICT — فمحاولةُ حذفِها تُفشل العمليةَ كلَّها (1451). ولذا
     تُستثنى صراحةً، لا أن يُترَك القيدُ يكتشفها. */
$pinnedEvents = "SELECT 1 FROM fin_financial_events f WHERE f.root_event_id = ems_business_events.id";
$predict['ems_business_events'] = $opt['trim_events']
    ? (int) one($pdo, "SELECT COUNT(*) FROM ems_business_events
                       WHERE NOT (entity_type='timesheet' AND entity_id NOT IN ($tsIn) AND NOT EXISTS ($pinnedEvents))")
    : (int) $before['ems_business_events'];

echo "\n③ الأبناءُ بعدَ الاشتقاق:\n";
$breach = 0;
foreach ($predict as $t => $n) {
    $b    = (int) $before[$t];
    $mark = ($n < $opt['floor'] && $b >= $opt['floor']) ? '  ⚠ دونَ الأرضية' : '';
    if ($mark !== '') { $breach++; }
    printf("   %-24s %9s → %9s%s\n", $t, number_format($b), number_format($n), $mark);
}
if ($breach > 0) {
    fwrite(STDERR, "\n✘ جدولٌ يهبط دونَ {$opt['floor']} — ارفعِ الهدفَ (‎--ts/--ue) ثم أعِدْ. لم يُحذَفْ شيء.\n");
    exit(1);
}

echo "\n   تُترك كما هي (دونَ الأرضيةِ أصلًا · لا حشوَ فيها): "
   . "timesheet_approvals ({$before['timesheet_approvals']}) · timesheet_approval_notes ({$before['timesheet_approval_notes']}) · "
   . "timesheet_failure_hours ({$before['timesheet_failure_hours']}) · unit_capacity_flags ({$before['unit_capacity_flags']}) · "
   . "unit_match_overrides ({$before['unit_match_overrides']})\n";

if (!$opt['apply']) {
    echo "\n— خطةٌ فقط، لم يُمسَّ صفٌّ واحد. أعِدْ بـ ‎--apply للتنفيذ.\n";
    exit(0);
}

/* ═══════════ ④ التنفيذ ════════════════════════════════════════════════════ */
echo "\n④ التنفيذ…\n";
$deleted = array();

/* ◆ **الحذفُ كتلةٌ واحدةٌ أو لا شيء**: بلا معاملةٍ يترك أيُّ إخفاقٍ في المنتصف
     أبناءً مقلَّصين وآباءً كاملين — حالةٌ لا يُنبِّه إليها شيء. وOPTIMIZE بعدَ
     الإيداعِ حصرًا لأنه DDL يُودِع ضمنيًّا فيُبطل التراجع. */
$pdo->beginTransaction();
try {
    /* الترتيب: الأبناءُ الرخوةُ (بلا FK) أولًا، ثم الآباء — وCASCADE يتكفَّل بالباقي */
    $deleted['unit_party_awards'] = $pdo->exec("DELETE FROM unit_party_awards
        WHERE NOT ((source_kind='timesheet' AND source_ref IN ($tsIn)) OR (source_kind='unit_record' AND source_ref IN ($ueIn)))");
    $deleted['unit_time_log'] = $pdo->exec("DELETE FROM unit_time_log WHERE entry_id IS NULL OR entry_id NOT IN ($ueIn)");

    if ($opt['trim_events']) {
        /* التوصيلاتُ تسقط بـCASCADE (fk_evdeliv_event) — والمثبَّتُ ماليًّا مستثنًى */
        $deleted['ems_business_events'] = $pdo->exec("DELETE FROM ems_business_events
            WHERE entity_type='timesheet' AND entity_id NOT IN ($tsIn) AND NOT EXISTS ($pinnedEvents)");
    }

    $deleted['unit_entries'] = $pdo->exec("DELETE FROM unit_entries WHERE id NOT IN ($ueIn)");  // CASCADE ⇒ unit_approvals · unit_capacity_flags
    $deleted['timesheet']    = $pdo->exec("DELETE FROM timesheet    WHERE id NOT IN ($tsIn)");

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    fwrite(STDERR, "\n✘ أُحبطت العملية وتراجعت كاملةً — لم يُحذَف صفٌّ واحد:\n   " . $e->getMessage() . "\n");
    exit(1);
}

foreach ($deleted as $t => $n) { printf("   ✂ %-24s حُذف %s\n", $t, number_format((int) $n)); }

/* ═══════════ ⑤ استرجاعُ المساحةِ فعليًّا ═══════════════════════════════════ */
if (!$opt['no_optimize']) {
    echo "\n⑤ إعادةُ بناءِ الجداولِ لاسترجاعِ المساحة…\n";
    $rebuild = array('timesheet', 'unit_entries', 'unit_approvals', 'unit_time_log', 'unit_party_awards');
    if ($opt['trim_events']) { $rebuild[] = 'ems_business_events'; $rebuild[] = 'ems_event_deliveries'; }
    /* ◆ **`OPTIMIZE TABLE` لا يُصغِّر الملفَّ هنا**: يرجع «status OK» ولا يُعيد
         بناءَ المِساحة — بقي `timesheet.ibd` عند 30 م.ب لـ587 صفًّا بعدَه.
         و`ALTER TABLE … FORCE` يُعيد البناءَ فعلًا (30 م.ب ← 0.31). فالشاهدُ
         حجمُ الملفِّ على القرصِ لا رمزُ نجاحِ الأمر. */
    foreach ($rebuild as $t) {
        if ($before[$t] === null) { continue; }
        $kbBefore = (int) one($pdo, "SELECT ROUND((data_length+index_length)/1024) FROM information_schema.TABLES WHERE table_schema=DATABASE() AND table_name=?", array($t));
        $pdo->exec("ALTER TABLE `$t` FORCE");
        $kbAfter = (int) one($pdo, "SELECT ROUND((data_length+index_length)/1024) FROM information_schema.TABLES WHERE table_schema=DATABASE() AND table_name=?", array($t));
        printf("   ⟳ %-24s %s KB ← %s KB\n", $t, number_format($kbAfter), number_format($kbBefore));
    }
}

/* ═══════════ ⑥ الشاهد ═════════════════════════════════════════════════════ */
echo "\n⑥ الحصيلة:\n";
printf("   %-26s %10s %10s %10s\n", 'الجدول', 'قبل', 'بعد', 'KB');
$fail = 0;
foreach ($before as $t => $b) {
    if ($b === null) { continue; }
    $a  = (int) one($pdo, "SELECT COUNT(*) FROM `$t`");
    $kb = (int) one($pdo, "SELECT ROUND((data_length+index_length)/1024) FROM information_schema.TABLES WHERE table_schema=DATABASE() AND table_name=?", array($t));
    $mark = ($a < $opt['floor'] && $b >= $opt['floor']) ? ' ⚠' : '';
    if ($mark !== '') { $fail++; }
    printf("   %-26s %10s %10s %10s%s\n", $t, number_format($b), number_format($a), number_format($kb), $mark);
}
/* الإشاراتُ الرخوةُ التي فقدت هدفَها — تُعلَن ولا تُخفى */
$orphan = (int) one($pdo, "SELECT COUNT(*) FROM ems_business_events e
    WHERE e.entity_type='timesheet' AND NOT EXISTS (SELECT 1 FROM timesheet t WHERE t.id = e.entity_id)");
if ($orphan > 0) {
    echo "\n   ⓘ $orphan واقعةً في `ems_business_events` تشير إلى صفِّ تايم‑شيتٍ محذوف.\n"
       . "      لا قيدَ FK بينهما، وحمولةُ الواقعةِ مكتفيةٌ بذاتها — فالدفترُ سليمٌ والسلسلةُ المالية قائمة.\n";
}

$total = one($pdo, "SELECT ROUND(SUM(data_length+index_length)/1024/1024,1) FROM information_schema.TABLES WHERE table_schema=DATABASE()");
echo "\n   إجماليُّ القاعدةِ الآن: {$total} MB\n";
echo $fail > 0
    ? "\n⚠ $fail جدولًا دونَ الأرضية — راجعْ أعلاه\n"
    : "\n✔ كلُّ جدولٍ مُقلَّصٍ فوقَ الأرضية ({$opt['floor']})\n";
