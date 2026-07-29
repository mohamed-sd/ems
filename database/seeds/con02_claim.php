<?php
/**
 * database/seeds/con02_claim.php
 * ═══════════════════════════════════════════════════════════════════════════
 * المرحلة (هـ) — **أولُ مستخلصٍ حقيقيٍّ في تاريخ النظام** (`claims` كان صفرًا).
 *
 * يحتسب الجزاءاتِ للفترة بدورة اعتمادها (ق-13: النظامُ يحتسب · 12 يراجع ·
 * 19 يُجيز فيولد القيد)، ثم يولّد المستخلصَ ويعرض بنودَه، ثم **يطابقه بالدفتر
 * بندًا ببند** — فلا بندَ مستخلصٍ بلا قيدٍ يقابله (ق-7).
 *
 * ── الفترة ────────────────────────────────────────────────────────────────
 * 2026-07-28 → 2026-07-31: الفترةُ التي تحوي الواقعةَ الحيةَ المحوَّلة.
 * ودوريةُ الالتزام **يومية** فتكتمل داخلها (ق-11: لا احتسابَ نسبيًّا).
 *
 * ⚠️ كلُّ رقمٍ هنا مبنيٌّ على **بياناتِ تجربةٍ موسومة** (الالتزامُ اليوميُّ
 *    ونسبتا الغرامة والاستقطاع) — لا على بنود عقدٍ موقَّع.
 *
 * التشغيل: php database/seeds/con02_claim.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__, 2) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => 4, 'name' => 'con02 claim');

require_once dirname(__DIR__, 2) . '/app/Services/Contract/PenaltyService.php';
require_once dirname(__DIR__, 2) . '/Contracts/claim_helpers.php';

use App\Services\Contract\PenaltyService as PEN;

$MANIFEST = __DIR__ . '/con02_seed_manifest.json';
$SEED_TAG = 'CON02-SEED-20260728';
$COMPANY  = 4;
$FROM = '2026-07-28';
$TO   = '2026-07-31';

// فصلُ اليدين (ق-13): مَن راجع لا يُجيز — والخدمةُ تفرضه بنيويًّا
$ACTOR_SALES   = 12;
$ACTOR_FINANCE = 19;

$conn = $GLOBALS['conn'];
$gate = ems_tenant_db();

function say($m)  { fwrite(STDOUT, $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── " . $m . "\n"); }
function die_with($m) { fwrite(STDERR, "\n✘ " . $m . "\n"); exit(1); }
function n2($v) { return number_format((float) $v, 2); }

if (!file_exists($MANIFEST)) { die_with('لا بيانَ جرد — شغّل con02_seed.php أولًا.'); }
$manifest = json_decode(file_get_contents($MANIFEST), true);
$PILOT = (int) $manifest['pilot'];
function track(&$mf, $t, $id) { if ((int) $id > 0) { $mf['inserted'][$t][] = (int) $id; } }

$root = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                   ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($root->connect_error) { die_with('root: ' . $root->connect_error); }
$root->set_charset('utf8mb4');

// ═══════════════════════════════════════════════════════════════════════════
// ① الاحتساب
// ═══════════════════════════════════════════════════════════════════════════
head("① احتسابُ الجزاءات — العقد #{$PILOT} · {$FROM} → {$TO}");

$as = PEN::assess($conn, $gate, $COMPANY, $PILOT, $FROM, $TO, $ACTOR_SALES);
say('   احتُسب ' . $as['computed'] . ' بندًا · تُرك ' . count($as['skipped']));
foreach ($as['rows'] as $r) {
    track($manifest, 'contract_penalty_assessments', $r['id']);
    say(sprintf('       #%-4s %-14s %-16s %s %s',
        $r['id'], $r['kind'], $r['rule_kind'], n2($r['amount']), $r['currency']));
    if ($r['committed_qty'] !== null) {
        say(sprintf('             ملتزَم %s · منفَّذ %s · فارق %s · سعر %s · أساس %s%s',
            rtrim(rtrim((string) $r['committed_qty'], '0'), '.'),
            rtrim(rtrim((string) $r['actual_qty'], '0'), '.'),
            rtrim(rtrim((string) $r['gap_qty'], '0'), '.'),
            n2($r['unit_price']), n2($r['base_amount']),
            ($r['readiness_pct'] !== null ? (' · جاهزية ' . $r['readiness_pct'] . '٪') : '')));
        if ($r['cap_amount'] !== null) {
            say(sprintf('             خام %s · سقف %s%s', n2($r['raw_amount']), n2($r['cap_amount']),
                ((float) $r['raw_amount'] > (float) $r['cap_amount']) ? '  ← قُصّ عند السقف' : ''));
        }
    }
}
foreach ($as['skipped'] as $s) { say('       ○ ' . $s); }

// ═══════════════════════════════════════════════════════════════════════════
// ② دورةُ الاعتماد — 12 يراجع · 19 يُجيز فيولد القيد
// ═══════════════════════════════════════════════════════════════════════════
head('② دورةُ الاعتماد (ق-13) — ومَن راجع لا يُجيز');

foreach ($as['rows'] as $r) {
    $id = (int) $r['id'];
    $rv = PEN::review($gate, $COMPANY, $id, $ACTOR_SALES);
    say("   #{$id} مراجعة (12): " . ($rv['ok'] ? '✔ ' : '✘ ') . $rv['reason']);
}
// اختبارٌ سلبيّ: نفسُ المراجع يحاول الإجازة
if (!empty($as['rows'])) {
    $selfId = (int) $as['rows'][0]['id'];
    $self = PEN::approve($conn, $gate, $COMPANY, $selfId, $ACTOR_SALES);
    say("   #{$selfId} إجازةٌ بيد المراجع نفسِه: " . ($self['ok'] ? '✘ مرّت!' : '✔ مُنعت — ' . $self['reason']));
}
foreach ($as['rows'] as $r) {
    $id = (int) $r['id'];
    $ap = PEN::approve($conn, $gate, $COMPANY, $id, $ACTOR_FINANCE);
    say("   #{$id} إجازة (19): " . ($ap['ok'] ? ('✔ قيدٌ #' . $ap['event_id']) : ('✘ ' . $ap['reason'])));
    if (!empty($ap['event_id'])) {
        track($manifest, 'fin_financial_events', $ap['event_id']);
        $rr = $root->query("SELECT root_event_id FROM fin_financial_events WHERE id=" . (int) $ap['event_id']);
        if ($rr && ($x = $rr->fetch_assoc()) && !empty($x['root_event_id'])) {
            track($manifest, 'ems_business_events', $x['root_event_id']);
        }
        $lk = $root->query("SELECT id FROM fin_event_links WHERE target_id=" . (int) $ap['event_id']);
        while ($g = $lk->fetch_assoc()) { track($manifest, 'fin_event_links', $g['id']); }
        // ق-16: تمريرُ جزاء المورد يكتب ذمّةً في `fin_dues` — تُتبَّع وإلا بقيت
        // ذمّةٌ يتيمةٌ بعد التراجع تلتقطها التسويةُ لاحقًا بلا قيدٍ يقابلها.
        $fd = $root->query("SELECT id FROM fin_dues WHERE event_id=" . (int) $ap['event_id']);
        while ($g = $fd->fetch_assoc()) { track($manifest, 'fin_dues', $g['id']); }
    }
}
$be = $root->query("SELECT id FROM ems_business_events
                     WHERE entity_type='contract_penalty_assessment' OR
                           (entity_type='contract' AND event_key LIKE 'penalty%')");
while ($g = $be->fetch_assoc()) { track($manifest, 'ems_business_events', $g['id']); }

// ═══════════════════════════════════════════════════════════════════════════
// ③ توليدُ المستخلص
// ═══════════════════════════════════════════════════════════════════════════
head('③ توليدُ المستخلص');

$gen = claim_generate($conn, $PILOT, $FROM, $TO, $ACTOR_SALES);
say('   الحالة: ' . $gen['status'] . ($gen['reason'] !== '' ? (' — ' . $gen['reason']) : ''));
if ($gen['status'] !== 'created') { die_with('لم يُنشأ مستخلص.'); }
$CLAIM = (int) $gen['claim_id'];
track($manifest, 'claims', $CLAIM);

// وسمُ المستخلص كي تلتقطه شبكةُ الأمان في التراجع أيضًا
$root->query("UPDATE claims SET notes = CONCAT(COALESCE(notes,''), ' [{$SEED_TAG}]') WHERE id = {$CLAIM}");
$cl = $root->query("SELECT id FROM claim_lines WHERE claim_id={$CLAIM}");
while ($g = $cl->fetch_assoc()) { track($manifest, 'claim_lines', $g['id']); }

$claim = $gate->selectOne('claims', array('where' => array('id' => $CLAIM)));
$lines = $gate->scopedQuery(array('scope' => array('l' => 'claim_lines')),
    "SELECT l.* FROM claim_lines l WHERE {TENANT_SCOPE} AND l.claim_id = ? ORDER BY l.id",
    array($CLAIM));

// ═══════════════════════════════════════════════════════════════════════════
// ④ بنودُ المستخلص
// ═══════════════════════════════════════════════════════════════════════════
$AR_KIND = array(
    'timesheet'        => 'وحداتُ يوم عمل',
    'penalty'          => 'غرامةٌ تعاقدية',
    'incentive'        => 'حافزٌ تعاقدي',
    'min_guarantee'    => 'فارقُ الحد الأدنى',
    'retention'        => 'ضمانُ حسن التنفيذ (محتجز)',
    'advance_recovery' => 'استهلاكُ الدفعة المقدمة',
);

head("④ بنودُ المستخلص {$claim['claim_no']} — {$claim['currency']}");
say('');
say('   ┌────┬──────────────────────────────┬────────┬────────────┬──────────────┬──────────┐');
say('   │ #  │ البند                        │ كمية   │ سعر الوحدة │ المبلغ       │ قيدُ الدفتر│');
say('   ├────┼──────────────────────────────┼────────┼────────────┼──────────────┼──────────┤');
$sum = 0.0;
foreach ($lines as $i => $l) {
    $k = (string) $l['source_kind'];
    $label = isset($AR_KIND[$k]) ? $AR_KIND[$k] : $k;
    $amt = (float) $l['amount'];
    $sum += $amt;
    printf("   │ %-2s │ %-28s │ %6s │ %10s │ %12s │ %8s │\n",
        $i + 1, $label,
        rtrim(rtrim((string) $l['qty'], '0'), '.'),
        n2($l['unit_price']), n2($amt),
        !empty($l['event_id']) ? ('#' . $l['event_id']) : '—');
}
say('   ├────┴──────────────────────────────┴────────┴────────────┼──────────────┼──────────┤');
printf("   │ الإجمالي                                                 │ %12s │          │\n", n2($sum));
say('   └──────────────────────────────────────────────────────────┴──────────────┴──────────┘');
say('');
say('   المسجَّلُ في رأس المستخلص: gross=' . n2($claim['gross_amount'])
    . ' · net=' . n2($claim['net_amount']) . ' · state=' . $claim['state']);
say('   (الاستقطاعان بندان ظاهران سالبان — ولا يُكتبان في retention_amount وإلا خُصما مرتين.)');

// ═══════════════════════════════════════════════════════════════════════════
// ⑤ المطابقةُ البنيوية — بندًا ببند مقابلَ الدفتر
// ═══════════════════════════════════════════════════════════════════════════
head('⑤ المطابقةُ البنيوية: كلُّ بندٍ ذي قيدٍ = قيدُه في الدفتر');

$mismatch = array(); $matched = 0; $ledgerSum = 0.0; $noEventSum = 0.0;
foreach ($lines as $l) {
    $amt = round((float) $l['amount'], 2);
    if (empty($l['event_id'])) {
        // الاستقطاعان بلا قيد: ق-19 تجعلهما بندين ظاهرين لا قيدَي دفتر
        $noEventSum += $amt;
        if (!in_array((string) $l['source_kind'], array('retention', 'advance_recovery'), true)) {
            $mismatch[] = 'بندٌ بلا قيدٍ وليس استقطاعًا: ' . $l['source_kind'];
        }
        continue;
    }
    $fe = $root->query("SELECT id, amount, currency, event_type, event_status
                          FROM fin_financial_events WHERE id=" . (int) $l['event_id']);
    $e = $fe ? $fe->fetch_assoc() : null;
    if (!$e) { $mismatch[] = "البند يشير إلى قيدٍ #{$l['event_id']} غير موجود"; continue; }
    $eAmt = round((float) $e['amount'], 2);
    if (abs($eAmt - $amt) > 0.005) {
        $mismatch[] = "البند {$l['source_kind']}: مستخلص=" . n2($amt) . ' · دفتر=' . n2($eAmt);
    } else {
        $matched++;
        $ledgerSum += $eAmt;
        say(sprintf('   ✔ %-26s مستخلص %-10s = دفتر #%-5s %-10s %s',
            (isset($AR_KIND[(string) $l['source_kind']]) ? $AR_KIND[(string) $l['source_kind']] : $l['source_kind']),
            n2($amt), $e['id'], n2($eAmt), $e['currency']));
    }
}

say('');
say('   بنودٌ ذاتُ قيدٍ طابقت الدفترَ: ' . $matched . '/' . $matched . ' — مجموعها ' . n2($ledgerSum));
say('   بنودُ الاستقطاع (بلا قيدٍ بقرار ق-19): ' . n2($noEventSum));
say('   Σ بنود المستخلص = ' . n2($sum) . '   ·   Σ الدفتر + الاستقطاع = ' . n2($ledgerSum + $noEventSum));

// ومقارنةٌ ثانيةٌ من جهة الدفتر: كلُّ قيدِ إيرادٍ للعقد في الفترة
$led = $root->query("SELECT id, amount, unit, source_ref FROM fin_financial_events
                      WHERE contract_id={$PILOT} AND event_type='revenue'
                        AND COALESCE(is_deleted,0)=0 AND event_status='active'
                        AND DATE(occurred_at) BETWEEN '{$FROM}' AND '{$TO}' ORDER BY id");
$ledgerAll = 0.0; $ledgerRows = array();
while ($x = $led->fetch_assoc()) { $ledgerAll += (float) $x['amount']; $ledgerRows[] = $x; }
say('');
say('   ومن جهة الدفتر — كلُّ قيدِ إيرادٍ للعقد في الفترة:');
foreach ($ledgerRows as $x) {
    say(sprintf('       #%-5s %-12s %-16s %s', $x['id'], n2($x['amount']), $x['unit'], $x['source_ref']));
}
say('       المجموع: ' . n2($ledgerAll));
if (abs($ledgerAll - $ledgerSum) > 0.005) {
    $mismatch[] = 'الدفترُ فيه ' . n2($ledgerAll) . ' والمستخلصُ التقط ' . n2($ledgerSum) . ' — قيدٌ لم يُفوتر';
}

$claimTotal = round($sum, 2);
$expected = round($ledgerAll + $noEventSum, 2);
say('');
if (abs($claimTotal - $expected) > 0.005) {
    $mismatch[] = 'Σ المستخلص ' . n2($claimTotal) . ' ≠ Σ الدفتر ' . n2($ledgerAll)
                . ' + الاستقطاع ' . n2($noEventSum) . ' = ' . n2($expected);
}
if (abs((float) $claim['gross_amount'] - $claimTotal) > 0.005) {
    $mismatch[] = 'رأسُ المستخلص ' . n2($claim['gross_amount']) . ' ≠ Σ بنوده ' . n2($claimTotal);
}

if (empty($mismatch)) {
    say('   ✔ التطابقُ البنيويُّ تامّ: Σ بنود المستخلص = Σ قيود الدفتر + الاستقطاعات الظاهرة.');
} else {
    say('   ✘ فروقٌ:');
    foreach ($mismatch as $m) { say('       • ' . $m); }
}

$manifest['claim'] = array('id' => $CLAIM, 'no' => $claim['claim_no'],
    'from' => $FROM, 'to' => $TO, 'currency' => $claim['currency'],
    'gross' => (float) $claim['gross_amount'], 'lines' => count($lines),
    'ledger_sum' => $ledgerSum, 'deductions' => $noEventSum,
    'reconciled' => empty($mismatch));
file_put_contents($MANIFEST, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

say('');
say('   ⚠️ أرقامُ الغرامة والاستقطاع مبنيةٌ على بياناتِ تجربةٍ موسومة لا على عقدٍ موقَّع.');
say('   الرجوع: php database/seeds/con02_rollback.php');
say('');
exit(empty($mismatch) ? 0 : 1);
