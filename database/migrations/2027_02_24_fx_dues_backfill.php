<?php
/**
 * 2027_02_24 — تعبئةُ سعرِ الصرفِ ومعادلِه في `fin_dues` (الجدولُ الذي فاتَ P-08)
 * ═══════════════════════════════════════════════════════════════════════════
 * هجرةُ `2027_02_08` عبّأت **أربعةَ** جداول (`fin_payments` · `fin_financial_events`
 * · `fin_receivables` · `fin_journal_entries`) — و`fin_dues` **ليس فيها**، وهو
 * جدولُ الذمم الذي يقيسه `base_equivalent_test` صريحًا («الذمم: صفرٌ بلا سعر»).
 * فبقيت **ثلاثُ ذممٍ** بلا سعرٍ ولا معادل: واحدةٌ بالدولار (وهو **أساسُ** الشركة 4)
 * واثنتان بالجنيه.
 *
 * **والقاعدةُ منقولةٌ من `2027_02_08` حرفيًّا** لا مُخترعةً:
 *   · عملةُ الأساسِ (`fin_currencies.is_base = 1`) ⇒ `fx_rate = 1` والمعادلُ = المبلغ.
 *   · غيرُها ⇒ `rate_to_base` من `fin_fx_rates` **النافذُ في تاريخِ الصف** (أحدثُ
 *     `effective_from` لا يتجاوزه) — فسعرُ اليومِ لا يُطبَّق على أمسِ.
 *   · وما لا يجد سعرًا نافذًا **يُعلَن ولا يُلفَّق له رقم**.
 *
 * والمعادلُ ضربًا لا قسمةً (`base = amount × rate`) — قاعدةُ الأساسِ المسجَّلةُ في
 * هذا المستودع.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$fail = array();
$one  = function ($sql) use ($db) { $r = $db->query($sql); return $r ? $r->fetch_row()[0] : null; };

/* ── ① القياسُ قبل المسّ ─────────────────────────────────────────────────── */
$before = (int) $one('SELECT COUNT(*) FROM fin_dues WHERE fx_rate IS NULL');
echo "── ① ذممٌ بلا سعرِ صرفٍ قبل: {$before}\n";
if ($before === 0) { echo "\n✅ لا ذمّةَ بلا سعر — لا عمل.\n"; exit(0); }

$r = $db->query('SELECT d.id, d.company_id, d.currency, d.amount,
                        COALESCE(d.period_ref, DATE_FORMAT(d.created_at, "%Y-%m")) per,
                        DATE(d.created_at) dt,
                        (SELECT c.is_base FROM fin_currencies c
                          WHERE c.company_id = d.company_id AND c.code = d.currency LIMIT 1) is_base
                   FROM fin_dues d WHERE d.fx_rate IS NULL ORDER BY d.id');
$rows = array();
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
foreach ($rows as $x) {
    echo "     #{$x['id']} شركة={$x['company_id']} {$x['currency']} {$x['amount']}"
       . ' أساس=' . ($x['is_base'] === null ? 'غيرُ مسجَّلة' : $x['is_base'])
       . " تاريخ={$x['dt']}\n";
}

/* ── ② التعبئةُ صفًّا صفًّا بسعرِ تاريخِه ─────────────────────────────────── */
$st = $db->prepare('UPDATE fin_dues SET fx_rate = ?, base_amount = ROUND(amount * ?, 2) WHERE id = ?');
if (!$st) { fwrite(STDERR, 'prepare: ' . $db->error . "\n"); exit(1); }
$done = 0; $declared = array();
foreach ($rows as $x) {
    $rate = null;
    if ((int) $x['is_base'] === 1) {
        $rate = 1.00000000;   // عملةُ الأساس: المعادلُ هو المبلغُ نفسُه
    } else {
        $co  = (int) $x['company_id'];
        $cur = $db->real_escape_string((string) $x['currency']);
        $dt  = $db->real_escape_string((string) $x['dt']);
        $v = $one("SELECT rate_to_base FROM fin_fx_rates
                    WHERE company_id = {$co} AND currency_code = '{$cur}'
                      AND effective_from <= '{$dt}'
                    ORDER BY effective_from DESC LIMIT 1");
        if ($v !== null) { $rate = (float) $v; }
    }
    if ($rate === null) {
        $declared[] = '#' . $x['id'] . ' (' . $x['currency'] . ' في ' . $x['dt'] . ')';
        continue;
    }
    $id = (int) $x['id'];
    $st->bind_param('ddi', $rate, $rate, $id);
    if (!$st->execute()) { $fail[] = '#' . $id . ': ' . mb_substr($st->error, 0, 60); continue; }
    $done++;
}
$st->close();
echo "── ② عُبِّئت {$done} ذمّةً\n";
if ($declared) {
    echo '     ○ بلا سعرٍ نافذٍ في تاريخِها — تُعلَن ولا يُلفَّق لها رقم: '
       . implode(' · ', $declared) . "\n";
}

/* ── ③ الشاهدُ المُشغَّل ─────────────────────────────────────────────────── */
echo "── ③ الشاهدُ المُشغَّل\n";
$after = (int) $one('SELECT COUNT(*) FROM fin_dues WHERE fx_rate IS NULL');
echo "     ذممٌ بلا سعرٍ بعد: {$after} " . ($after === count($declared) ? "✔\n" : "✘\n");
if ($after !== count($declared)) { $fail[] = "المتبقي {$after} والمعلَنُ " . count($declared); }

/* المعادلُ **ضربًا** لا قسمةً — يُقاس على **صفوفِ هذه الهجرةِ** لا على الجدولِ
   كلِّه: ففي الجدولِ صفّان أقدمُ معادلُهما = المبلغُ الخامُ بسعرٍ **مقلوب**
   (2716.50 SAR بسعرِ 15.50 — أي «ريالًا للدولار» لا العكس)، ولا سعرَ SAR/QAR في
   `fin_fx_rates` أصلًا. فإعادةُ حسابِهما تُحوِّل ذمّةَ 2716 ريالًا إلى 42,105
   دولارًا — **رقمٌ مُلفَّقٌ من سعرٍ لا يُوثَق به أسوأُ من رقمٍ معلَنٍ خاطئ**.
   فيُعلَنان أدناه ويُتركان لقرارٍ يحمل سعرَيهما الصحيحَين. */
$mine = array_map(function ($x) { return (int) $x['id']; }, $rows);
$mineIn = $mine ? implode(',', $mine) : '0';
$bad = (int) $one("SELECT COUNT(*) FROM fin_dues
                    WHERE id IN ({$mineIn}) AND fx_rate IS NOT NULL AND base_amount IS NOT NULL
                      AND ABS(base_amount - ROUND(amount * fx_rate, 2)) > 0.01");
echo "     معادلُ صفوفِ هذه الهجرةِ يخالف amount × rate: {$bad} " . ($bad === 0 ? "✔\n" : "✘\n");
if ($bad !== 0) { $fail[] = "{$bad} صفًّا معادلُه محرَّف"; }

// ── إعلانٌ لا حكم: تحريفٌ أقدمُ سببُه سعرٌ مقلوبٌ بلا مصدرٍ يُصحَّح منه ──
$r2 = $db->query('SELECT d.id, d.currency, d.amount, d.fx_rate, d.base_amount,
                         (SELECT COUNT(*) FROM fin_fx_rates x
                           WHERE x.company_id = d.company_id AND x.currency_code = d.currency) has_rate
                    FROM fin_dues d
                   WHERE d.fx_rate IS NOT NULL AND d.base_amount IS NOT NULL
                     AND ABS(d.base_amount - ROUND(d.amount * d.fx_rate, 2)) > 0.01
                   ORDER BY d.id');
$old = array();
while ($r2 && ($x = $r2->fetch_assoc())) {
    $old[] = '#' . $x['id'] . ' (' . $x['amount'] . ' ' . $x['currency'] . ' × ' . $x['fx_rate']
           . ' · المخزَّن ' . $x['base_amount']
           . ' · سعرٌ مسجَّلٌ لعملتِه: ' . ((int) $x['has_rate'] > 0 ? 'نعم' : '**لا**') . ')';
}
if ($old) {
    echo "     ⚠ تحريفٌ أقدمُ **يُعلَن ولا يُعاد حسابُه** (سعرٌ مقلوبٌ بلا مصدر):\n";
    foreach ($old as $o) { echo "        {$o}\n"; }
}

$noBase = (int) $one('SELECT COUNT(*) FROM fin_dues WHERE fx_rate IS NOT NULL AND base_amount IS NULL');
echo "     سعرٌ بلا معادل: {$noBase} " . ($noBase === 0 ? "✔\n" : "✘\n");
if ($noBase !== 0) { $fail[] = "{$noBase} صفًّا بسعرٍ بلا معادل"; }

echo "\n" . (empty($fail)
    ? "✅ الذممُ صارت بمعادلٍ محسوبٍ بسعرِ تاريخِها — والجدولُ الذي فاتَ P-08 لحق بها.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
