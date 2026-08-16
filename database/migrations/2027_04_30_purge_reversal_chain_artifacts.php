<?php
/**
 * 2027_04_30_purge_reversal_chain_artifacts.php
 * ═══════════════════════════════════════════════════════════════════════════
 * كنسُ سلسلةِ عكسٍ ولّدها مسبارٌ ثم أُغلق بابُها
 *
 * `fin02_reversal_proof` اختار **واقعةً معوِّضةً** للعكسِ (وهي Posted ولها قيدٌ
 * فبدت صالحة)، فتولّدت سلسلةٌ: 8577 ⇐ 17982 ⇐ 17983 ⇐ عاكسُها. ومبلغُ المعوِّضةِ
 * **سالبٌ** فأنتج رأسَ قيدٍ بـ-954.80 مقابلَ سطرين موجبين — رأسٌ يخالف سطورَه.
 *
 * والبابُ أُغلق في المصدر: `PostingService::reversePosted` صار يرفض عكسَ عاكس،
 * ويأخذ المبلغَ **مطلقًا** دائمًا. وهذه الهجرةُ تكنس ما وُلد قبلَ الإغلاق.
 *
 * ◆ ولا يُحذف إلا ما تولّد من السلسلةِ نفسِها: الأصلُ الأولُ (8577) **يُعاد**
 *   إلى Posted بقيدِه الأصليّ، ويُحذف عاكسُه وما بعدَه — فيعود الحالُ إلى ما
 *   كان قبلَ المسبارِ تمامًا لا إلى حالٍ ثالث.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? $r->fetch_row()[0] : null; };

echo "══ كنسُ سلسلةِ العكسِ المولَّدة ══\n\n";

/* السلسلةُ تُكتشف: معوِّضةٌ عُكست (لها reverses_event_id ومعكوسةٌ بدورِها) */
$chain = array();
$r = $conn->query("SELECT e.id FROM fin_financial_events e
                   WHERE e.reverses_event_id IS NOT NULL
                     AND EXISTS (SELECT 1 FROM fin_financial_events x WHERE x.reverses_event_id = e.id)");
while ($r && ($x = $r->fetch_row())) { $chain[] = (int) $x[0]; }

if (!$chain) { echo "  · لا سلسلةَ عكسٍ مولَّدة — لا تغيير\n\n✔ تمّت\n"; exit(0); }
echo '  معوِّضاتٌ عُكست: ' . implode(' · ', $chain) . "\n";

$victims = array();
foreach ($chain as $cid) {
    /* عاكسُ المعوِّضةِ — هو الفائض */
    $r = $conn->query("SELECT id, journal_entry_id FROM fin_financial_events WHERE reverses_event_id = $cid");
    while ($r && ($x = $r->fetch_assoc())) { $victims[] = $x; }
}
foreach ($chain as $cid) {
    $r = $conn->query("SELECT id, journal_entry_id FROM fin_financial_events WHERE id = $cid");
    if ($r && ($x = $r->fetch_assoc())) { $victims[] = $x; }
}

$delE = 0; $delJ = 0; $delL = 0;
foreach ($victims as $v) {
    $eid = (int) $v['id']; $jid = (int) ($v['journal_entry_id'] ?? 0);
    if ($jid > 0) {
        $conn->query("DELETE FROM fin_journal_lines WHERE entry_id = $jid"); $delL += $conn->affected_rows;
        $conn->query("DELETE FROM fin_journal_entries WHERE id = $jid");     $delJ += $conn->affected_rows;
    }
    $conn->query("DELETE FROM fin_financial_events WHERE id = $eid");        $delE += $conn->affected_rows;
}
printf("  حُذف: %d واقعةً · %d قيدًا · %d سطرًا\n", $delE, $delJ, $delL);

/* الأصلُ الأولُ يعود Posted بقيدِه — لا إلى حالٍ ثالث */
$restored = 0;
$r = $conn->query("SELECT id FROM fin_financial_events
                   WHERE fes_status='Reversed'
                     AND NOT EXISTS (SELECT 1 FROM fin_financial_events x WHERE x.reverses_event_id = fin_financial_events.id)");
while ($r && ($x = $r->fetch_row())) {
    $id = (int) $x[0];
    $conn->query("UPDATE fin_financial_events SET fes_status='Posted', state='posted' WHERE id=$id");
    $restored += $conn->affected_rows;
}
printf("  أُعيد إلى Posted: %d واقعةً بلا عاكس\n", $restored);

echo "\n── التحقُّق ──\n";
printf("  رؤوسٌ تخالف سطورَها: %s (المتوقَّع 0)\n",
    $one("SELECT COUNT(*) FROM (SELECT j.id FROM fin_journal_entries j JOIN fin_journal_lines l ON l.entry_id=j.id
          WHERE j.company_id=4 GROUP BY j.id, j.total_debit, j.total_credit
          HAVING ABS(SUM(l.debit)-j.total_debit)>0.005 OR ABS(SUM(l.credit)-j.total_credit)>0.005) d"));
printf("  معكوسٌ بلا عاكسٍ مُرحَّل: %s (المتوقَّع 0)\n",
    $one("SELECT COUNT(*) FROM fin_financial_events e WHERE e.fes_status='Reversed'
          AND NOT EXISTS (SELECT 1 FROM fin_financial_events r WHERE r.reverses_event_id=e.id
                          AND r.fes_status='Posted' AND COALESCE(r.journal_entry_id,0)>0)"));
printf("  رؤوسٌ سالبة: %s (المتوقَّع 0)\n",
    $one("SELECT COUNT(*) FROM fin_journal_entries WHERE total_debit < 0 OR total_credit < 0"));
echo "\n✔ تمّت\n";
