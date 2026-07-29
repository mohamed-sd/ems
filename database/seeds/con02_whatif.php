<?php
/**
 * database/seeds/con02_whatif.php
 * ═══════════════════════════════════════════════════════════════════════════
 * المرحلة (د) — «ماذا لو» على التاريخ · **قراءةٌ خالصةٌ بصفر كتابة**.
 *
 * السؤال: لو كانت مصفوفةُ الالتزامات نافذةً منذ بداية العقد، كم ساعةً كانت
 * ستُفوتر ولم تُفوتر — وكم قيمتُها؟
 *
 * ── ما لا يفعله هذا الملف ────────────────────────────────────────────────
 * **لا يكتب صفًّا ولا يعدّل عمودًا ولا يمسّ الصفوفَ الـ256 التاريخية.** حكمُها
 * `NULL` أي «ما قبل المصفوفة»، وهو حكمٌ صحيحٌ يُترك كما هو. والمقارنةُ هنا
 * حسابٌ في الذاكرة لا إسنادٌ يُخزَّن.
 *
 * ── كيف يُحسب الحكمان؟ ───────────────────────────────────────────────────
 * بمحرّك النظام نفسِه:
 *   القديم = `EffectFanout::resolveRuling(policy, state, null)` — عينُ ما
 *            يستدعيه المحرّكُ حين تكون اللقطةُ NULL.
 *   الجديد = `AttributionService::rulings(matrix, state, obligation, ...)`.
 *
 * ── الافتراضُ الوحيد · معلَنٌ لا مدسوس ────────────────────────────────────
 * السطورُ التاريخيةُ بلا بندِ التزام (العمود NULL)، فيلزم ربطُ حالةِ الساعة
 * ببندٍ لنعرف ماذا كانت المصفوفةُ ستقول. ويُقتصر على **الربطِ الدلاليِّ الذي
 * لا يحتمل غيرَه**:
 *      fuel_logistics_stop → fuel
 *      tech_breakdown      → equipment_readiness
 *      operator_stop       → operators
 * وما عداه (standby · planned_stop · client_stop · supplier_stop · القاهرة)
 * **يُترك ولا يُحسب** ويُعدّ في عمودٍ باسمه: ربطُه ببندٍ اجتهادٌ لا يملكه
 * النظامُ ولا كاتبُ هذا الملف. فالرقمُ الناتجُ **حدٌّ أدنى محافظ**، لا تقدير.
 *
 * التشغيل: php database/seeds/con02_whatif.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__, 2) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => 4, 'name' => 'con02 what-if');

require_once dirname(__DIR__, 2) . '/app/Services/Contract/AttributionService.php';
require_once dirname(__DIR__, 2) . '/app/Services/EffectFanout.php';

use App\Services\Contract\AttributionService as ATT;
use App\Services\EffectFanout as FAN;

$COMPANY = 4;
$conn = $GLOBALS['conn'];
$gate = ems_tenant_db();

function say($m)  { fwrite(STDOUT, $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── " . $m . "\n"); }
function h($n) { return rtrim(rtrim(number_format((float) $n, 2), '0'), '.'); }

/** الربطُ الدلاليُّ الذي لا يحتمل غيرَه — وما عداه يُترك معلَنًا. */
$SAFE_MAP = array(
    'fuel_logistics_stop' => 'fuel',
    'tech_breakdown'      => 'equipment_readiness',
    'operator_stop'       => 'operators',
);

// ═══════════════════════════════════════════════════════════════════════════
// وحدةُ فوترة العميل وسعرُه لكل عقد — من `contractequipments` (مصدرُ المروحة)
// ═══════════════════════════════════════════════════════════════════════════
$UNIT_LABEL = array('ساعة' => 'hour', 'طن' => 'ton', 'متر طولي' => 'meter', 'متر' => 'meter', 'نقلة' => 'trip');

$contracts = $gate->scopedQuery(array('scope' => array('c' => 'contracts')),
    "SELECT c.id, c.actual_start, c.price_currency_contract AS cur
       FROM contracts c WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0 ORDER BY c.id", array());

$priceOf = array();
foreach ($contracts as $c) {
    $rows = $gate->scopedQuery(array('scope' => array('ce' => 'contractequipments')),
        "SELECT ce.equip_unit, ce.equip_price, ce.equip_price_currency
           FROM contractequipments ce WHERE {TENANT_SCOPE} AND ce.contract_id = ? ORDER BY ce.id",
        array((int) $c['id']));
    $unit = null; $price = 0.0; $cur = '';
    foreach ($rows as $r) {
        $lbl = trim((string) $r['equip_unit']);
        if ($lbl === '') { continue; }
        $u = isset($UNIT_LABEL[$lbl]) ? $UNIT_LABEL[$lbl] : null;
        if ($u === null) { continue; }
        $unit = $u; $price = (float) $r['equip_price'];
        $cur = (trim((string) $r['equip_price_currency']) === 'دولار') ? 'USD'
             : ((trim((string) $r['equip_price_currency']) === 'جنيه') ? 'SDG' : trim((string) $r['equip_price_currency']));
        break;
    }
    $priceOf[(int) $c['id']] = array('unit' => $unit, 'price' => $price, 'currency' => $cur,
                                     'start' => (string) $c['actual_start']);
}

// ═══════════════════════════════════════════════════════════════════════════
// المسبار
// ═══════════════════════════════════════════════════════════════════════════
head('مسبارُ «ماذا لو» — قراءةٌ خالصة · صفرُ كتابة');
say('   المصفوفةُ المبذورة نافذةٌ افتراضًا منذ بداية كل عقد.');

$liveTs = null;
$MANIFEST = __DIR__ . '/con02_seed_manifest.json';
if (file_exists($MANIFEST)) {
    $mf = json_decode(file_get_contents($MANIFEST), true);
    if (!empty($mf['live_event']['entry'])) { $liveTs = (int) $mf['live_event']['entry']; }
}

$grand = array();
$skippedStates = array();

foreach ($contracts as $c) {
    $cid = (int) $c['id'];
    $meta = $priceOf[$cid];

    $lines = $gate->scopedQuery(
        array('scope' => array('l' => 'unit_time_log'), 'enrich' => array('e' => 'unit_entries')),
        "SELECT l.id, l.ops_state, l.hours, l.log_date
           FROM unit_time_log l
           LEFT JOIN unit_entries e ON e.id = l.entry_id
          WHERE {TENANT_SCOPE} AND e.contract_id = ?" . ($liveTs ? " AND e.id <> {$liveTs}" : '') . "
          ORDER BY l.log_date", array($cid));
    if (empty($lines)) { continue; }

    $matrix = ATT::matrixFor($gate, $cid, $meta['start']);
    $polClient = FAN::hourPolicy($gate, $COMPANY, 'client', $cid, $meta['start']);
    $polSupp   = FAN::hourPolicy($gate, $COMPANY, 'supplier', $cid, $meta['start']);

    $oldHrs = 0.0; $newHrs = 0.0; $unmapped = 0.0; $total = 0.0;
    foreach ($lines as $l) {
        $st = (string) $l['ops_state'];
        $hh = (float) $l['hours'];
        $total += $hh;

        $oc = FAN::resolveRuling($polClient, $st, null);
        if (in_array($oc['ruling'], array('full', 'pct'), true)) { $oldHrs += $hh; }

        if ($st === ATT::NO_OBLIGATION_STATE) {
            $newHrs += $hh;                       // التشغيلُ الفعليُّ يُفوتر في الحالين
            continue;
        }
        if (!isset($SAFE_MAP[$st])) {             // ربطٌ غيرُ يقينيّ — يُترك معلَنًا
            $unmapped += $hh;
            if (!isset($skippedStates[$st])) { $skippedStates[$st] = 0.0; }
            $skippedStates[$st] += $hh;
            // ويُحسب بحكمه القديم كي لا يُنسب إليه فرقٌ لم يُقس
            if (in_array($oc['ruling'], array('full', 'pct'), true)) { $newHrs += $hh; }
            continue;
        }
        $r = ATT::rulings($matrix, $st, $SAFE_MAP[$st], $polClient, $polSupp);
        if ((int) $r['billable'] === 1) { $newHrs += $hh; }
    }

    $delta = round($newHrs - $oldHrs, 2);
    $grand[$cid] = array('total' => $total, 'old' => $oldHrs, 'new' => $newHrs,
                         'delta' => $delta, 'unmapped' => $unmapped, 'meta' => $meta,
                         'lines' => count($lines));
}

// ═══════════════════════════════════════════════════════════════════════════
// العرض
// ═══════════════════════════════════════════════════════════════════════════
say('');
say('   عقد │ سطور │ إجمالي │ مفوترٌ قديمًا │ مفوترٌ بالمصفوفة │  الفرق  │ وحدةُ فوترة العميل');
say('   ────┼──────┼────────┼──────────────┼──────────────────┼─────────┼────────────────────');
foreach ($grand as $cid => $g) {
    $u = $g['meta']['unit'];
    $unitNote = ($u === 'hour') ? ('ساعة @ ' . h($g['meta']['price']) . ' ' . $g['meta']['currency'])
              : (($u === null) ? 'بلا تسعير' : $u . ' — الساعاتُ لا تُسعَّر');
    say(sprintf('   %3s │ %4s │ %6s │ %12s │ %16s │ %7s │ %s',
        $cid, $g['lines'], h($g['total']), h($g['old']), h($g['new']), h($g['delta']), $unitNote));
}

// ═══════════════════════════════════════════════════════════════════════════
// الجواب: ساعاتٌ وقيمة
// ═══════════════════════════════════════════════════════════════════════════
head('الجواب');

$byCur = array();
$hrsBillable = 0.0; $hrsUnpriced = 0.0;
foreach ($grand as $cid => $g) {
    if ($g['delta'] <= 0) { continue; }
    if ($g['meta']['unit'] === 'hour' && $g['meta']['price'] > 0) {
        $cur = $g['meta']['currency'] !== '' ? $g['meta']['currency'] : '؟';
        if (!isset($byCur[$cur])) { $byCur[$cur] = array('hours' => 0.0, 'amount' => 0.0, 'contracts' => array()); }
        $byCur[$cur]['hours'] += $g['delta'];
        $byCur[$cur]['amount'] += $g['delta'] * $g['meta']['price'];
        $byCur[$cur]['contracts'][] = $cid;
        $hrsBillable += $g['delta'];
    } else {
        $hrsUnpriced += $g['delta'];
    }
}

if (empty($byCur)) {
    say('   لا ساعةَ إضافيةً تُفوتر — المصفوفةُ المبذورة لا تغيّر شيئًا على التاريخ.');
} else {
    foreach ($byCur as $cur => $v) {
        say('   ► ' . h($v['hours']) . ' ساعةً كانت تضيع بلا فوترة · قيمتُها '
            . number_format($v['amount'], 2) . ' ' . $cur
            . '   (العقود: ' . implode('، ', $v['contracts']) . ')');
    }
}
if ($hrsUnpriced > 0) {
    say('   ► و' . h($hrsUnpriced) . ' ساعةً على عقودٍ **لا تفوتر بالساعة** — فلا قيمةَ لها بالمال');
    say('     (عقدٌ يفوتر بالمتر أو الطن لا يقرأ حكمَ الساعة أصلًا — لا تسعيرَ ملفَّق).');
}

if (!empty($skippedStates)) {
    say('');
    say('   ولم تُحتسب هذه الحالاتُ إطلاقًا (ربطُها ببندِ التزامٍ اجتهادٌ لا يملكه النظام):');
    arsort($skippedStates);
    foreach ($skippedStates as $st => $hh) { say(sprintf('       %-22s %s ساعة', $st, h($hh))); }
    say('   فالرقمُ أعلاه **حدٌّ أدنى محافظ** — لا تقديرٌ متفائل.');
}

say('');
say('   ⚠️ هذا الرقمُ مشروطٌ بفرضِ التجربة (الوقودُ على العميل في عقد العرض).');
say('   ⚠️ ولا يعني «مالًا ضاع فعلًا»: وقائعُ هذه العقود لم تُحوَّل ماليًّا بعد');
say('      (صفرُ قيدِ إيرادٍ تاريخيٍّ عليها) — فهو **ساعاتٌ إضافيةٌ تفتحها المصفوفةُ للفوترة**.');
say('');
say('   ✔ صفرُ كتابة: لم يُمسَّ صفٌّ واحدٌ من الـ256 التاريخية.');
say('');
