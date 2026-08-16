<?php
/**
 * pilot_monitor.php — عدّادُ الإطلاقِ التجريبيِّ (PL-03: صفرُ يومٍ مفقود)
 * ═══════════════════════════════════════════════════════════════════════════
 * يقيس: أيامَ القيدِ الحقيقيِّ (بلا بذور) · التتابعَ بلا فجوةٍ · الأيامَ
 * المفقودةَ · وجاهزيةَ محطاتِ PL. قراءةٌ محضة.
 * التشغيل: php tools/pilot_monitor.php --company=4 [--from=YYYY-MM-DD]
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$db = @mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if (!$db) { fwrite(STDERR, "فشل الاتصال\n"); exit(2); }
$db->set_charset('utf8mb4');
$args = array();
foreach (array_slice($argv, 1) as $a) { if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = $m[2] ?? '1'; } }
$CO = (int) ($args['company'] ?? 4);
$one = function (string $s) use ($db) { $r = $db->query($s); return $r ? $r->fetch_row()[0] : null; };

/* بدايةُ التجربة: أولُ قيدٍ حقيقيٍّ (أو --from) */
$from = $args['from'] ?? (string) $one("SELECT MIN(entry_date) FROM unit_entries WHERE company_id=$CO AND seed_tag IS NULL");
if (!$from) { echo "لا قيدَ حقيقيًّا بعدُ — التجربةُ لم تبدأ (PL-01 بانتظارِ التجميد)\n"; exit(0); }

echo "══ عدّادُ التجربة — كيان $CO · منذ $from ══\n\n";
$days = array();
$r = $db->query("SELECT entry_date, COUNT(*) n, COUNT(DISTINCT equipment_id) eq
                 FROM unit_entries WHERE company_id=$CO AND seed_tag IS NULL AND entry_date >= '" . $db->real_escape_string($from) . "'
                 GROUP BY entry_date ORDER BY entry_date");
while ($x = $r->fetch_assoc()) { $days[$x['entry_date']] = $x; }

$d0 = new DateTime($from); $d1 = new DateTime('today');
$total = 0; $covered = 0; $missing = array(); $streak = 0; $bestStreak = 0;
for ($d = clone $d0; $d <= $d1; $d->modify('+1 day')) {
    $k = $d->format('Y-m-d'); $total++;
    if (isset($days[$k])) { $covered++; $streak++; $bestStreak = max($bestStreak, $streak); }
    else { $missing[] = $k; $streak = 0; }
}
printf("  أيامُ النطاق: %d · مُغطّاة: %d · مفقودة: %d\n", $total, $covered, count($missing));
printf("  التتابعُ الحاليُّ بلا فجوة: %d يومًا · وأفضلُه: %d\n", $streak, $bestStreak);
if ($missing) { echo '  الأيامُ المفقودة: ' . implode(' · ', array_slice($missing, -7)) . (count($missing) > 7 ? ' …' : '') . "\n"; }
printf("  آخرُ يومِ قيد: %s (%s قيدًا · %s آلية)\n",
    array_key_last($days) ?: '—',
    $days[array_key_last($days)]['n'] ?? 0, $days[array_key_last($days)]['eq'] ?? 0);

echo "\n── جاهزيةُ المحطات ──\n";
$chk = array(
    array('PL-02 سلسلةٌ كاملةٌ مرتان', (int) $one("SELECT COUNT(DISTINCT ue.id) FROM unit_entries ue JOIN unit_approvals ua ON ua.entry_id=ue.id WHERE ue.company_id=$CO AND ue.seed_tag IS NULL") >= 2),
    array('PL-03 ثلاثون يومَ تتابع', $bestStreak >= 30),
    array('PL-04 تسويةٌ بتسوياتِها ومستندِها', (int) $one("SELECT COUNT(*) FROM settlements WHERE company_id=$CO AND is_deleted=0 AND (adj_work_added+adj_breakdown_added+adj_standby_added+adj_deducted)<>0 AND adj_doc_ref IS NOT NULL") > 0),
    array('PL-05 قيدُ دفترٍ من واقعةِ قيدٍ يومي', (int) $one("SELECT COUNT(*) FROM fin_journal_entries j JOIN fin_financial_events e ON e.journal_entry_id=j.id WHERE j.company_id=$CO AND j.is_deleted=0 AND e.source_module='movement'") > 0),
);
foreach ($chk as [$t, $okC]) { echo '  ' . ($okC ? '✔ ' : '⏳ ') . $t . "\n"; }
echo "\n[pilot " . date('Y-m-d H:i') . "] streak=$streak best=$bestStreak missing=" . count($missing) . "\n";
