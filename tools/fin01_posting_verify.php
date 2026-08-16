<?php
/**
 * fin01_posting_verify.php — فاحصُ سلامةِ الترحيل (قراءةٌ فقط + اختبارُ عطالة)
 * ═══════════════════════════════════════════════════════════════════════════
 * ثمانيةُ فحوصٍ — والمتوقَّعُ صفرُ مخالفٍ في كلٍّ:
 *   ① كلُّ قيدٍ متوازن (مدين = دائن)
 *   ② ومجموعُ سطورِه = ترويستِه
 *   ③ ولكلِّ قيدٍ سطرانِ على الأقل
 *   ④ ولا حسابَ غيرَ قابلٍ للترحيل
 *   ⑤ ولا قيدَ في فترةٍ مغلقة
 *   ⑥ ولا واقعةَ Posted بلا قيد · ولا قيدَ مرتبطٌ بواقعةٍ غيرِ Posted
 *   ⑦ ولا واقعةَ برأسَي قيد (عطالة)
 *   ⑧ وإعادةُ التشغيلِ لا تُنتج قيدًا ثانيًا — اختبارٌ حيّ
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
$fails = 0;
$check = function (string $title, string $sql, string $hint = '') use ($db, &$fails) {
    $r = $db->query($sql);
    if (!$r) { echo "  ✘ $title :: خطأُ استعلام — " . $db->error . "\n"; $fails++; return; }
    $n = (int) $r->fetch_row()[0];
    printf("  %s %-48s %s\n", $n === 0 ? '✔' : '✘', $title, $n === 0 ? 'صفر' : "$n مخالفًا");
    if ($n !== 0) { $fails++; if ($hint) { echo "      ⇐ $hint\n"; } }
};

echo "══ فاحصُ سلامةِ الترحيل — كيان $CO ══\n\n";

$check('① قيودٌ غيرُ متوازنة (رأس)',
    "SELECT COUNT(*) FROM fin_journal_entries WHERE company_id=$CO AND ABS(total_debit-total_credit)>0.005");

$check('② رأسٌ يخالف مجموعَ سطورِه',
    "SELECT COUNT(*) FROM (
       SELECT j.id FROM fin_journal_entries j JOIN fin_journal_lines l ON l.entry_id=j.id
       WHERE j.company_id=$CO GROUP BY j.id, j.total_debit, j.total_credit
       HAVING ABS(SUM(l.debit)-j.total_debit)>0.005 OR ABS(SUM(l.credit)-j.total_credit)>0.005) d");

$check('③ قيدٌ بأقلَّ من سطرين',
    "SELECT COUNT(*) FROM (SELECT j.id FROM fin_journal_entries j
       LEFT JOIN fin_journal_lines l ON l.entry_id=j.id
       WHERE j.company_id=$CO GROUP BY j.id HAVING COUNT(l.id)<2) d");

$check('④ سطرٌ على حسابٍ غيرِ قابلٍ للترحيل',
    "SELECT COUNT(*) FROM fin_journal_lines l
       JOIN fin_journal_entries j ON j.id=l.entry_id AND j.company_id=$CO
       LEFT JOIN fin_chart_of_accounts a ON a.id=l.account_id
      WHERE a.id IS NULL OR a.is_postable<>1 OR a.active<>1");

$check('⑤ قيدٌ آليٌّ في فترةٍ مغلقة',
    "SELECT COUNT(*) FROM fin_journal_entries j
      WHERE j.company_id=$CO AND j.event_id IS NOT NULL AND j.event_id>0
        AND NOT EXISTS (SELECT 1 FROM fin_financial_periods p
                        WHERE p.company_id=$CO AND p.period_type='month' AND p.posting_allowed=1
                          AND j.posting_date BETWEEN p.start_date AND p.end_date)");

$check('⑥-أ واقعةٌ Posted بلا قيد',
    "SELECT COUNT(*) FROM fin_financial_events
      WHERE company_id=$CO AND fes_status='Posted' AND (journal_entry_id IS NULL OR journal_entry_id=0)");

$check('⑥-ب قيدٌ لواقعةٍ ليست Posted',
    "SELECT COUNT(*) FROM fin_journal_entries j JOIN fin_financial_events e ON e.id=j.event_id
      WHERE j.company_id=$CO AND e.fes_status<>'Posted'");

$check('⑦ واقعةٌ برأسَي قيد',
    "SELECT COUNT(*) FROM (SELECT event_id FROM fin_journal_entries
       WHERE company_id=$CO AND event_id IS NOT NULL AND event_id>0
       GROUP BY event_id HAVING COUNT(*)>1) d");

/* ── ⑧ العطالة — والفاحصُ لا يغيّر ما يقيسه ────────────────────────
   ◆ كان هذا الفحصُ ينادي postApproved فيكتب حتى 50 قيدًا في كلِّ تشغيلة:
     فاحصٌ يحرّك العدَّادَ الذي يقرؤه. صار الآن **قراءةً افتراضيًّا**، والكتابةُ
     خلفَ `--live` صراحةً ولا تُشغَّل إلا بطلب. */
echo "\n── ⑧ العطالة ──\n";
$one = function (string $s) use ($db) { $r = $db->query($s); return $r ? (int) $r->fetch_row()[0] : -1; };
$live = in_array('--live', $argv, true);

/* البرهانُ الساكن: لا واقعةَ لها أكثرُ من قيد (وهو الفحصُ ⑦)، ولا قيدَ آليٌّ
   يتيمٌ بلا واقعةٍ Posted (⑥-ب). فالعطالةُ مضمونةٌ بنيويًّا بعمودِ journal_entry_id. */
$twice   = $one("SELECT COUNT(*) FROM (SELECT event_id FROM fin_journal_entries
                 WHERE company_id=$CO AND event_id>0 GROUP BY event_id HAVING COUNT(*)>1) d");
$postedNoJe = $one("SELECT COUNT(*) FROM fin_financial_events
                    WHERE company_id=$CO AND fes_status='Posted' AND COALESCE(journal_entry_id,0)=0");
printf("  %s واقعةٌ بقيدين: %d · Posted بلا قيد: %d\n", ($twice === 0 && $postedNoJe === 0) ? '✔' : '✘', $twice, $postedNoJe);
if ($twice !== 0 || $postedNoJe !== 0) { $fails++; }

if ($live) {
    $je0 = $one("SELECT COUNT(*) FROM fin_journal_entries WHERE company_id=$CO");
    $actor = $one("SELECT id FROM users WHERE company_id=$CO LIMIT 1");
    $_SESSION = array('user' => array('id' => $actor, 'company_id' => $CO, 'role' => '17'));
    $res = PS::postApproved(ems_tenant_db(), $db, $CO, $actor, 25);
    $je1 = $one("SELECT COUNT(*) FROM fin_journal_entries WHERE company_id=$CO");
    printf("  --live: قيودٌ %d ⇐ %d · رُحِّل %d\n", $je0, $je1, (int) $res['posted']);
    if ($je1 - $je0 === (int) $res['posted']) { echo "  ✔ القيودُ الجديدةُ = المُرحَّلُ بالضبط (لا تكرار)\n"; }
    else { echo "  ✘ عدمُ اتساق\n"; $fails++; }
} else {
    echo "  ◆ الاختبارُ الكاتبُ خلفَ --live — والفاحصُ افتراضًا قراءةٌ محضة\n";
}

/* ── الحصيلة ─────────────────────────────────────────────────────── */
echo "\n══ الحصيلة ══\n";
$r = $db->query("SELECT fes_status, COUNT(*) FROM fin_financial_events WHERE company_id=$CO GROUP BY 1 ORDER BY 2 DESC");
$line = array();
while ($x = $r->fetch_row()) { $line[] = "{$x[0]}={$x[1]}"; }
echo '  الوقائع: ' . implode(' · ', $line) . "\n";
$r = $db->query("SELECT COUNT(*), COALESCE(SUM(total_debit),0) FROM fin_journal_entries
                 WHERE company_id=$CO AND event_id IS NOT NULL AND event_id>0");
$x = $r->fetch_row();
printf("  قيودٌ آليةٌ من وقائع: %s · مجموعُها %s\n", number_format((int) $x[0]), number_format((float) $x[1], 2));

echo "\n" . ($fails === 0 ? "✔ الترحيلُ سليمٌ — صفرُ مخالفٍ في الثمانية\n" : "✘ إخفاقات: $fails\n");
exit($fails === 0 ? 0 : 1);
