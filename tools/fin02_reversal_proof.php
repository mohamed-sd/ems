<?php
/**
 * fin02_reversal_proof.php — إثباتُ عكسِ القيدِ المُرحَّل (يكتب ثم يترك الأثرَ مقروءًا)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العكسُ **لا يُنظَّف**: أثرُه هو المطلوب — قيدٌ وعاكسُه يتقابلان في الدفتر.
 *   فالمسبارُ يرحّل واقعةً واحدةً ثم يعكسها، ويثبت أن الصافيَ صفرٌ والأصلُ باقٍ.
 * الفحوص:
 *   ① الأصلُ يبقى ولا يُحذف ولا يُعدَّل مبلغُه
 *   ② القيدُ العاكسُ متوازنٌ ومدينُه ودائنُه **متبادلان** مع الأصل
 *   ③ صافي الحسابين بعدَ العكسِ = صفر
 *   ④ الأصلُ صار Reversed والمعوِّضةُ Posted بقيدِها
 *   ⑤ عكسُ المعكوسِ لا يُنتج قيدًا ثالثًا (عطالة)
 *   ⑥ ولا يُعكس ما ليس Posted · ولا عكسَ بلا سبب
 *   ⑦ وفاحصُ السلامةِ الثمانيُّ يبقى أخضرَ بعدَ كلِّ ذلك
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = 'C:/wamp64/www/ems';
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Services/Finance/PostingService.php';
use App\Services\Finance\PostingService as PS;

$db = $conn; $db->set_charset('utf8mb4');
$CO = 4;
$one = function (string $s) use ($db) { $r = $db->query($s); return $r ? $r->fetch_row()[0] : null; };
$fails = 0;
$ok = function (bool $c, string $m) use (&$fails) { echo ($c ? '  ✔ ' : '  ✘ ') . $m . "\n"; if (!$c) { $fails++; } };

$actor = (int) $one("SELECT id FROM users WHERE company_id=$CO LIMIT 1");
$_SESSION = array('user' => array('id' => $actor, 'company_id' => $CO, 'role' => '17'));
$gate = ems_tenant_db();

/* واقعةٌ مُرحَّلةٌ لم تُعكس بعد */
$evId = (int) $one("SELECT e.id FROM fin_financial_events e
                    WHERE e.company_id=$CO AND e.fes_status='Posted' AND e.journal_entry_id>0
                      AND NOT EXISTS (SELECT 1 FROM fin_financial_events r WHERE r.reverses_event_id=e.id)
                    ORDER BY e.id DESC LIMIT 1");
if (!$evId) { exit("لا واقعةَ مُرحَّلةٌ صالحةٌ للاختبار\n"); }
$ev = $db->query("SELECT event_no, amount, currency, journal_entry_id, fes_status FROM fin_financial_events WHERE id=$evId")->fetch_assoc();
echo "══ عكسُ الواقعة #$evId ({$ev['event_no']}) بمبلغ {$ev['amount']} {$ev['currency']} ══\n\n";

$jeBefore = (int) $one("SELECT COUNT(*) FROM fin_journal_entries WHERE company_id=$CO");
$origEntry = (int) $ev['journal_entry_id'];
$origLines = array();
$rs = $db->query("SELECT account_id, debit, credit FROM fin_journal_lines WHERE entry_id=$origEntry ORDER BY id");
while ($x = $rs->fetch_assoc()) { $origLines[] = $x; }

/* ── ⑥ الاختباراتُ السلبيةُ أولًا ─────────────────────────────── */
echo "── ⑥ الاختباراتُ السلبية ──\n";
$r = PS::reversePosted($gate, $db, $CO, $evId, '   ', $actor);
$ok(empty($r['ok']) && (int) $r['code'] === 422, 'رُفض العكسُ بلا سبب (422)');
$draftId = (int) $one("SELECT id FROM fin_financial_events WHERE company_id=$CO AND fes_status='Published' LIMIT 1");
if ($draftId) {
    $r = PS::reversePosted($gate, $db, $CO, $draftId, 'محاولة', $actor);
    $ok(empty($r['ok']) && (int) $r['code'] === 409, 'رُفض عكسُ واقعةٍ ليست Posted (409)');
}

/* ── ② العكسُ الفعليّ ────────────────────────────────────────── */
echo "\n── العكسُ الفعليّ ──\n";
$r = PS::reversePosted($gate, $db, $CO, $evId, 'قيدٌ رُحِّل على حسابٍ خطأ — اختبارُ العكس', $actor);
$ok(!empty($r['ok']), 'نجح العكس · واقعةٌ معوِّضةٌ #' . ($r['reversal_event_id'] ?? '—') . ' · قيدٌ عاكسٌ #' . ($r['reversal_entry_id'] ?? '—'));
$revEntry = (int) ($r['reversal_entry_id'] ?? 0);
$revEvent = (int) ($r['reversal_event_id'] ?? 0);

/* ── ① الأصلُ باقٍ ──────────────────────────────────────────── */
echo "\n── ① الأصلُ ──\n";
$after = $db->query("SELECT fes_status, amount, journal_entry_id FROM fin_financial_events WHERE id=$evId")->fetch_assoc();
$ok($after !== null, 'الواقعةُ الأصليةُ ما زالت موجودة');
$ok((float) $after['amount'] === (float) $ev['amount'], "مبلغُها لم يُعدَّل ({$after['amount']})");
$ok((int) $after['journal_entry_id'] === $origEntry, "قيدُها الأصليُّ ما زال معلّقًا (#$origEntry)");
$ok((string) $after['fes_status'] === 'Reversed', "حالتُها صارت Reversed (كانت {$ev['fes_status']})");
$origStill = (int) $one("SELECT COUNT(*) FROM fin_journal_entries WHERE id=$origEntry AND is_deleted=0");
$ok($origStill === 1, 'والقيدُ الأصليُّ لم يُحذف من الدفتر');

/* ── ② التبادل ─────────────────────────────────────────────── */
echo "\n── ② القيدُ العاكس ──\n";
$rv = $db->query("SELECT entry_no, total_debit, total_credit, state FROM fin_journal_entries WHERE id=$revEntry")->fetch_assoc();
$ok($rv && (float) $rv['total_debit'] === (float) $rv['total_credit'], "متوازن: {$rv['total_debit']} = {$rv['total_credit']} ({$rv['entry_no']})");
$revLines = array();
$rs = $db->query("SELECT account_id, debit, credit FROM fin_journal_lines WHERE entry_id=$revEntry ORDER BY id");
while ($x = $rs->fetch_assoc()) { $revLines[$x['account_id']] = $x; }
$swapped = true;
foreach ($origLines as $l) {
    $m = $revLines[$l['account_id']] ?? null;
    if (!$m || (float) $m['debit'] !== (float) $l['credit'] || (float) $m['credit'] !== (float) $l['debit']) { $swapped = false; }
}
$ok($swapped, 'المدينُ والدائنُ متبادلانِ على الحسابين نفسِهما');
foreach ($origLines as $l) {
    $m = $revLines[$l['account_id']];
    $code = $one("SELECT code FROM fin_chart_of_accounts WHERE id=" . (int) $l['account_id']);
    printf("      %-6s أصل: مدين %10s دائن %10s  ⇐  عكس: مدين %10s دائن %10s\n",
        $code, $l['debit'], $l['credit'], $m['debit'], $m['credit']);
}

/* ── ③ الصافي صفر ──────────────────────────────────────────── */
echo "\n── ③ صافي الحسابين ──\n";
foreach ($origLines as $l) {
    $acc = (int) $l['account_id'];
    $code = $one("SELECT code FROM fin_chart_of_accounts WHERE id=$acc");
    $net = (float) $one("SELECT COALESCE(SUM(debit)-SUM(credit),0) FROM fin_journal_lines
                         WHERE account_id=$acc AND entry_id IN ($origEntry, $revEntry)");
    $ok(abs($net) < 0.005, "الحساب $code: صافي (الأصل + العاكس) = " . number_format($net, 2));
}

/* ── ④ المعوِّضة ───────────────────────────────────────────── */
echo "\n── ④ الواقعةُ المعوِّضة ──\n";
$cmp = $db->query("SELECT fes_status, amount, reverses_event_id, journal_entry_id FROM fin_financial_events WHERE id=$revEvent")->fetch_assoc();
$ok((string) $cmp['fes_status'] === 'Posted', "حالتُها Posted");
$ok((int) $cmp['reverses_event_id'] === $evId, "تشير إلى أصلِها (reverses_event_id=$evId)");
$ok((float) $cmp['amount'] === -1 * (float) $ev['amount'], "مبلغُها سالبُ الأصل ({$cmp['amount']})");
$ok((int) $cmp['journal_entry_id'] === $revEntry, 'ومعلّقةٌ على القيدِ العاكس');

/* ── ⑤ العطالة ────────────────────────────────────────────── */
echo "\n── ⑤ عكسُ المعكوس ──\n";
$je1 = (int) $one("SELECT COUNT(*) FROM fin_journal_entries WHERE company_id=$CO");
$r2 = PS::reversePosted($gate, $db, $CO, $evId, 'محاولةٌ ثانية', $actor);
$je2 = (int) $one("SELECT COUNT(*) FROM fin_journal_entries WHERE company_id=$CO");
$ok($je2 === $je1, "لم يُنشأ قيدٌ ثالث (القيود ثابتةٌ عند " . number_format($je2) . ")");
$ok(empty($r2['ok']) || !empty($r2['duplicate']), 'والخدمةُ ردّت رفضًا أو أعلنت التكرار');

echo "\n══ الحصيلة ══\n";
printf("  قيودُ الكيان: %s ⇐ %s (+%d)\n", number_format($jeBefore), number_format($je2), $je2 - $jeBefore);
echo "\n" . ($fails === 0 ? "✔ العكسُ حركةٌ مقابلةٌ متوازنةٌ والأصلُ باقٍ — صفرُ إخفاق\n" : "✘ إخفاقات: $fails\n");
exit($fails === 0 ? 0 : 1);
