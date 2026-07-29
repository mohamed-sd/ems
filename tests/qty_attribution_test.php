<?php
/**
 * tests/qty_attribution_test.php — M-24
 * ═══════════════════════════════════════════════════════════════════════════
 * عقودُ الطن/المتر داخل مصفوفة الإسناد (CON-02 §3-② · §3-④ · §5).
 *
 * ما يُثبته:
 *   ① فرعُ الكمية **يمرّ على سطور الزمن ولقطة الإسناد** — لا يعود بالكمية فورًا.
 *   ② **البُعد ②** «الاستعدادُ مفوترٌ بشرطٍ صريح»: لا يُفوتر افتراضًا، ولا
 *      يُحوَّل إلى مالٍ بلا سعرٍ مقرَّر — يُعلَن بساعاته ويُوسَم `standby_needs_rate`.
 *   ③ **البُعد ①** «إعادةُ التنفيذ لعيبٍ لا تُفوتر»: الكميةُ صفرٌ للعميل بسببها
 *      المكتوب — والمورد **لا يتأثر** (قرارُه لم يُحسم فلا يُبتّ فيه ضمنًا).
 *   ④ **البُعدان لا يُسحقان في واحد**: استعدادٌ مفوترٌ لا يغيّر الكمية، وحكمُ
 *      الكمية لا يغيّر حكمَ الاستعداد.
 *   ⑤ حرّاسُ `ruleQty`: سببٌ إلزاميٌّ عند المنع · عطالةٌ بلا كتابةٍ ثانية ·
 *      **409 إن ولّدت الواقعةُ مالًا** · 404 لواقعةٍ لا وجودَ لها.
 *   ⑥ **صفرُ إعادة احتسابٍ صامتة** (معيارُ القبول): كلُّ وقائع الكمية القائمة
 *      `qty_billable = NULL` ⇒ الكميةُ المحكومةُ = الخام بالضبط. تقريرُ مطابقة.
 *   ⑦ فرعُ الساعة لم يُمسّ — اختبارُ انحدارٍ مباشر.
 *
 * ①–④ بمعطياتٍ مبنيّةٍ في الذاكرة (partyAward دالةٌ خالصةٌ على ctx)، و⑤–⑥ على
 * القاعدة الحيّة. لا يُكتب صفٌّ إلا في ⑤ وتُعاد حالتُه بالضبط.
 * التشغيل: php tests/qty_attribution_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => 4, 'name' => 'qty attribution test');

require_once dirname(__DIR__) . '/includes/attribution_events.php';
require_once dirname(__DIR__) . '/app/Services/Contract/AttributionService.php';
require_once dirname(__DIR__) . '/app/Services/EffectFanout.php';

use App\Services\Contract\AttributionService as ATT;
use App\Services\EffectFanout as FAN;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function info($m) { fwrite(STDOUT, "     · {$m}\n"); }

$conn = $GLOBALS['conn'];
$gate = ems_tenant_db();
$CO   = 4;

/**
 * سياقُ واقعةِ كميةٍ مبنيٌّ في الذاكرة.
 * @param array $lines صفوفُ {state, hours, billable?, supplier_countable?, obligation_type?}
 */
function qtyCtx($unit, $qty, array $lines = array(), $qtyBillable = null, $qtyNote = null) {
    $norm = array();
    foreach ($lines as $l) {
        $norm[] = array_merge(array('obligation_type' => null, 'billable' => null,
                                    'supplier_countable' => null, 'operator_countable' => null), $l);
    }
    return array(
        'company_id' => 4, 'work_date' => '2027-02-01',
        'states' => array(), 'state_lines' => $norm,
        'qty_billable' => $qtyBillable, 'qty_ruling_note' => $qtyNote,
        'client'   => array('ok' => true, 'unit' => $unit, 'qty' => $qty, 'price' => 50.0,
                            'currency' => 'SDG', 'contract_id' => 5),
        'supplier' => array('ok' => true, 'unit' => $unit, 'qty' => $qty, 'price' => 30.0,
                            'currency' => 'SDG', 'contract_id' => 5),
    );
}

fwrite(STDOUT, "\n══ M-24 — عقودُ الطن/المتر داخل مصفوفة الإسناد ══\n");

// ═══ ① الفرعُ يمرّ على السطور ═══
head('① فرعُ الكمية يمرّ على سطور الزمن ولقطة الإسناد');
$a = FAN::partyAward($gate, qtyCtx('ton', 120.50, array(
        array('state' => 'actual_work', 'hours' => 8.0),
        array('state' => 'standby', 'hours' => 2.0, 'billable' => 1, 'obligation_type' => 'access_road'),
     )), 'client');
check(isset($a['snapshot']['standby_lines']), 'اللقطةُ تحمل سطورَ التوقف بأحكامها');
check(count($a['snapshot']['standby_lines']) === 1,
    'وسطرُ التشغيل الفعلي خارجَها (هو الكميةُ نفسُها): ' . count($a['snapshot']['standby_lines']));
check($a['rule'] === 'delivered_qty', 'والقاعدةُ ما زالت `delivered_qty` — لا انقلابَ في المعنى');

// ═══ ② البُعد ② — الاستعداد ═══
head('② «الاستعدادُ مفوترٌ بشرطٍ صريح» — لا افتراضًا ولا بسعرٍ مخترَع');
$sn = $a['snapshot'];
check((float) $sn['standby_billable_hours'] === 2.0,
    'استعدادٌ مفوترٌ بشرطه الصريح (billable=1): ' . $sn['standby_billable_hours'] . ' ساعة');
check($sn['standby_needs_rate'] === true, 'ومعلَّمٌ أنه يحتاج سعرًا مقرَّرًا');
check(mb_strpos($sn['standby_note'], 'لا سعرَ ساعةٍ في عقدٍ مسعَّرٍ بـton') !== false,
    'والسببُ منصوصٌ: ' . $sn['standby_note']);
check((float) $a['award_qty'] === 120.50,
    'و**الكميةُ لم تتغير**: ' . $a['award_qty'] . ' — الساعاتُ لا تُجمع إلى أطنان');

$b = FAN::partyAward($gate, qtyCtx('ton', 100.0, array(
        array('state' => 'standby', 'hours' => 3.0, 'billable' => 0, 'obligation_type' => 'equipment_readiness'),
     )), 'client');
check((float) $b['snapshot']['standby_excluded_hours'] === 3.0 && $b['snapshot']['standby_needs_rate'] === false,
    'واستعدادٌ حكمُه `billable=0` مستبعَدٌ لا مفوتَر: ' . $b['snapshot']['standby_excluded_hours'] . ' ساعة');

$c2 = FAN::partyAward($gate, qtyCtx('meter', 42.0, array(
        array('state' => 'tech_breakdown', 'hours' => 1.5),   // بلا لقطة — ما قبل المصفوفة
     )), 'client');
check((float) $c2['snapshot']['standby_undecided_hours'] === 1.5,
    'وسطرٌ بلا لقطةٍ يُعلَن «بلا حكمٍ مقرَّر» لا يُفترض مفوترًا: ' . $c2['snapshot']['standby_undecided_hours']);
check($c2['snapshot']['standby_needs_rate'] === false && (float) $c2['award_qty'] === 42.0,
    'ولا يُغيّر الكميةَ ولا يدّعي فوترة');

// كلُّ طرفٍ بلقطته: العميلُ `billable` والمورد `supplier_countable`
$mixed = qtyCtx('ton', 90.0, array(
    array('state' => 'standby', 'hours' => 4.0, 'billable' => 1, 'supplier_countable' => 0),
));
$mc = FAN::partyAward($gate, $mixed, 'client');
$ms = FAN::partyAward($gate, $mixed, 'supplier');
check((float) $mc['snapshot']['standby_billable_hours'] === 4.0
      && (float) $ms['snapshot']['standby_excluded_hours'] === 4.0,
    'ولكلِّ طرفٍ لقطتُه: العميلُ 4 مفوترة · المورد 4 مستبعَدة — لا يُحكم على طرفٍ بلقطة غيره');

// ═══ ③ البُعد ① — الكمية ═══
head('③ «إعادةُ التنفيذ لعيبٍ لا تُفوتر» — حكمٌ على الواقعة');
$rw = qtyCtx('meter', 45.0, array(array('state' => 'actual_work', 'hours' => 6.0)),
             0, 'إعادةُ تنفيذٍ لعيبٍ في الصبّة — المتر مدفوعٌ في الواقعة UNT-000289');
$rwC = FAN::partyAward($gate, $rw, 'client');
check((float) $rwC['award_qty'] === 0.0, 'كميةُ العميل صفر: ' . $rwC['award_qty']);
check($rwC['state'] === 'not_due', 'وحالتُها `not_due` لا `due`');
check(mb_strpos($rwC['snapshot']['note'], 'إعادةُ تنفيذٍ لعيب') !== false,
    'والسببُ المكتوبُ محفوظٌ في اللقطة: ' . mb_substr($rwC['snapshot']['note'], 0, 80));
check((float) $rwC['snapshot']['qty'] === 45.0,
    'والكميةُ الخامُ محفوظةٌ بجانبها (45) — لا تُمحى بل يُعلَن حكمُها');
$rwS = FAN::partyAward($gate, $rw, 'supplier');
check((float) $rwS['award_qty'] === 45.0 && $rwS['state'] === 'due',
    'و**المورد لا يتأثر**: ' . $rwS['award_qty'] . ' — مَن يتحمل العيبَ قرارٌ لم يُحسم فلا يُبتّ فيه ضمنًا');

// ═══ ④ البُعدان مستقلّان ═══
head('④ البُعدان لا يُسحقان في واحد');
$both = qtyCtx('ton', 80.0, array(
    array('state' => 'standby', 'hours' => 5.0, 'billable' => 1),
), 0, 'إعادةُ تنفيذ');
$bc = FAN::partyAward($gate, $both, 'client');
check((float) $bc['award_qty'] === 0.0 && (float) $bc['snapshot']['standby_billable_hours'] === 5.0,
    'كميةٌ ممنوعةٌ (0) واستعدادٌ مفوترٌ (5س) في واقعةٍ واحدة — حكمان مستقلّان');
$noRule = FAN::partyAward($gate, qtyCtx('ton', 80.0, array(
    array('state' => 'standby', 'hours' => 5.0, 'billable' => 1))), 'client');
check((float) $noRule['award_qty'] === 80.0,
    'ورفعُ حكم الكمية لا يمسّ حكمَ الاستعداد ولا العكس: ' . $noRule['award_qty']);

// ═══ ⑦ فرعُ الساعة لم يُمسّ ═══
head('⑦ فرعُ الساعة — انحدارٌ مباشر');
$hr = FAN::partyAward($gate, qtyCtx('hour', 0.0, array(
        array('state' => 'actual_work', 'hours' => 7.5),
     )), 'client');
check($hr['rule'] === 'hour_policy', 'عقدُ الساعة ما زال على `hour_policy`');
check(!isset($hr['snapshot']['standby_billable_hours']),
    'ولقطتُه بشكلها القديم — لا خلطَ بين الفرعين');
check(isset($hr['snapshot']['lines']) && isset($hr['snapshot']['billable_hours']),
    'وبمفاتيحها المعهودة (lines · billable_hours)');

// ═══ ⑤ حرّاسُ ruleQty ═══
head('⑤ حرّاسُ حكم الكمية');
$victim = $conn->query("SELECT id, entry_no, qty_billable, qty_ruling_note, unit_type, qty
                          FROM unit_entries
                         WHERE company_id={$CO} AND unit_type IN ('ton','meter')
                           AND sync_uuid IS NULL
                         ORDER BY id LIMIT 1")->fetch_assoc();
if (!$victim) {
    $victim = $conn->query("SELECT id, entry_no, qty_billable, qty_ruling_note, unit_type, qty
                              FROM unit_entries WHERE company_id={$CO} AND unit_type IN ('ton','meter')
                             ORDER BY id LIMIT 1")->fetch_assoc();
}
if (!$victim) {
    bad('لا واقعةَ كميةٍ للاختبار');
} else {
    $EID = (int) $victim['id'];
    $restore = function () use ($conn, $EID, $victim) {
        $nb = ($victim['qty_billable'] === null) ? 'NULL' : (int) $victim['qty_billable'];
        $nn = ($victim['qty_ruling_note'] === null) ? 'NULL'
              : "'" . $conn->real_escape_string($victim['qty_ruling_note']) . "'";
        $conn->query("UPDATE unit_entries SET qty_billable={$nb}, qty_ruling_note={$nn},
                        qty_decided_by=NULL, qty_decided_at=NULL WHERE id={$EID}");
        $conn->query("DELETE FROM ems_business_events
                       WHERE event_key='attribution.qty_ruled' AND entity_id={$EID}");
    };
    $restore();
    register_shutdown_function($restore);
    info("الواقعةُ {$victim['entry_no']} ({$victim['unit_type']} · {$victim['qty']})");

    $r = ATT::ruleQty($conn, $gate, $CO, $EID, 0, '', 1);
    check(empty($r['ok']) && (int) $r['code'] === 422,
        'منعٌ بلا سبب: مرفوضٌ بـ422 — ' . ($r['reasons'][0] ?? ''));

    $r = ATT::ruleQty($conn, $gate, $CO, $EID, 5, 'أيًّا كان', 1);
    check(empty($r['ok']) && (int) $r['code'] === 422, 'وحكمٌ غيرُ (0/1) مرفوض');

    $r = ATT::ruleQty($conn, $gate, $CO, $EID, 0, 'إعادةُ تنفيذٍ لعيب', 1);
    check(!empty($r['ok']) && !empty($r['changed']), 'والمنعُ بسببه يقع');
    $row = $conn->query("SELECT qty_billable, qty_ruling_note, qty_decided_by, qty_decided_at
                           FROM unit_entries WHERE id={$EID}")->fetch_assoc();
    check((int) $row['qty_billable'] === 0 && $row['qty_ruling_note'] === 'إعادةُ تنفيذٍ لعيب',
        'ويُكتب بسببه في السجل');
    check((int) $row['qty_decided_by'] === 1 && $row['qty_decided_at'] !== null,
        'وباسم مَن حكم ووقته — الحكمُ لا يكون مجهولَ الصاحب');
    $ev = (int) $conn->query("SELECT COUNT(*) c FROM ems_business_events
                               WHERE event_key='attribution.qty_ruled' AND entity_id={$EID}")
                     ->fetch_assoc()['c'];
    check($ev === 1, "وحدثٌ واحدٌ نُشر: {$ev}");

    // العطالة
    $r2 = ATT::ruleQty($conn, $gate, $CO, $EID, 0, 'إعادةُ تنفيذٍ لعيب', 1);
    check(!empty($r2['ok']) && empty($r2['changed']),
        'والحكمُ نفسُه مرةً ثانيةً: 200 بلا تغيير');
    $ev2 = (int) $conn->query("SELECT COUNT(*) c FROM ems_business_events
                                WHERE event_key='attribution.qty_ruled' AND entity_id={$EID}")
                      ->fetch_assoc()['c'];
    check($ev2 === 1, "ولا حدثَ ثانٍ: {$ev2} — العطالةُ قبل الكتابة");

    $r3 = ATT::ruleQty($conn, $gate, $CO, 99999999, 0, 'x', 1);
    check(empty($r3['ok']) && (int) $r3['code'] === 404, 'وواقعةٌ لا وجودَ لها: 404');
    $restore();

    // 409 — واقعةٌ ولّدت مالًا
    $paid = $conn->query("SELECT e.id, e.entry_no FROM unit_entries e
                           WHERE e.company_id={$CO} AND e.sync_uuid LIKE 'ts:%'
                             AND EXISTS (SELECT 1 FROM fin_event_links l
                                          WHERE l.parent_kind='timesheet'
                                            AND l.parent_ref = CAST(SUBSTRING(e.sync_uuid,4) AS UNSIGNED))
                           LIMIT 1")->fetch_assoc();
    if ($paid) {
        $r4 = ATT::ruleQty($conn, $gate, $CO, (int) $paid['id'], 0, 'محاولةٌ متأخرة', 1);
        check(empty($r4['ok']) && (int) $r4['code'] === 409,
            "وواقعةٌ ولّدت مالًا ({$paid['entry_no']}): 409 — لا يُكتب فوق حكمٍ ماليٍّ قائم");
        $chk = $conn->query("SELECT qty_billable FROM unit_entries WHERE id=" . (int) $paid['id'])->fetch_assoc();
        check($chk['qty_billable'] === null, 'ولا صفَّ تغيّر — الرفضُ قبل الكتابة لا بعدها');
    } else { ok('(لا واقعةَ مولِّدةً للمال — تُخطّى)'); }
}

// ═══ ⑥ تقريرُ المطابقة — صفرُ إعادة احتسابٍ صامتة ═══
head('⑥ تقريرُ المطابقة — الوقائعُ السابقة لا يُعاد احتسابُها صامتةً');
$rows = $conn->query("SELECT id, entry_no, unit_type, qty, qty_billable, contract_id
                        FROM unit_entries
                       WHERE company_id={$CO} AND unit_type IN ('ton','meter')
                       ORDER BY unit_type, id")->fetch_all(MYSQLI_ASSOC);
check(count($rows) === 20, 'وقائعُ الكمية القائمة: ' . count($rows));
$unruled = 0; $changed = 0;
foreach ($rows as $e) {
    if ($e['qty_billable'] === null) { $unruled++; }
    $ctx = qtyCtx($e['unit_type'], (float) $e['qty'], array(),
                  ($e['qty_billable'] === null) ? null : (int) $e['qty_billable']);
    $aw = FAN::partyAward($gate, $ctx, 'client');
    if (round((float) $aw['award_qty'], 2) !== round((float) $e['qty'], 2)) { $changed++; }
}
check($unruled === count($rows),
    "كلُّها بلا حكمٍ على كميتها (qty_billable=NULL): {$unruled} من " . count($rows));
check($changed === 0,
    "و**صفرُ واقعةٍ تغيّرت كميتُها المحكومة**: {$changed} — الترحيلُ صفرُ أثرٍ على المال القائم");
$posted = (int) $conn->query("SELECT COUNT(*) c FROM unit_party_awards
                               WHERE award_unit_type IN ('ton','meter')")->fetch_assoc()['c'];
info("أحكامُ كميةٍ مكتوبةٌ سلفًا في السجل: {$posted} — لم يُمسّ منها صفّ");

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
