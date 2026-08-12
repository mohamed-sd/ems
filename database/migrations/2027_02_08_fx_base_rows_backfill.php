<?php
/**
 * 2027_02_08 — P-08: صفوفٌ بعملةِ الأساسِ بلا سعرٍ ولا معادل
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ حكمُ `P-08`: «لا مبلغَ بلا عملةٍ، ولا مبلغَ بعملةٍ بلا سعرٍ ومعادلٍ بالأساس».
 *   و`base_equivalent_test` يقيسه فيجد ثقوبًا مقيسة:
 *     · **28 دفعةً** في `fin_payments` بـ`fx_rate IS NULL` — كلُّها **USD**
 *     · **3 وقائعَ** في `fin_financial_events` بلا سعرٍ — كلُّها **SDG** بمبلغ **0.00**
 *
 * ◆ **والملءُ اشتقاقٌ حتميٌّ لا اجتهاد**: عملةُ الأساسِ في الشركة 4 هي **USD**
 *   (`fin_currencies.is_base = 1`)، و`fin_fx_rates` يسجّل لها **1.00000000 من
 *   2000-01-01** بملاحظةٍ صريحة: «عملةُ الأساس — نسبتُها إلى نفسها واحدٌ أبدًا».
 *   فما كان بعملةِ الأساسِ سعرُه **واحدٌ** ومعادلُه **مبلغُه** — لا تقديرَ فيه.
 *
 * ◆ وما كان بمبلغِ **صفرٍ** معادلُه صفرٌ بأيِّ سعرٍ كان، فيُملأ بسعرِ عملتِه
 *   المسجَّلِ (SDG = 0.000185) ومعادلٍ صفريّ — ولا يُخترع سعرٌ لصفٍّ ذي مبلغ.
 *
 * ◆ **وما لا يُشتَقُّ لا يُملأ**: صفٌّ بعملةٍ غيرِ الأساسِ وبمبلغٍ موجبٍ ولا سعرَ
 *   مسجَّلٌ لتاريخِه — يبقى ويُعلَن. فسعرٌ مخترَعٌ يفسد رقمًا ماليًّا إلى الأبد،
 *   والفراغُ المُعلَنُ يُطارَد.
 *
 * ◆ مُتحمِّلٌ للتكرار · ويُعَدُّ الباقي بعد الملء.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

echo "══ P-08 — ملءُ سعرِ عملةِ الأساسِ ومعادلِها ══\n";

/* ── عملاتُ الأساسِ لكلِّ شركةٍ — من السجلِّ لا من ثابتٍ مكتوب ─────────────── */
$base = array();
$r = $db->query("SELECT company_id, code FROM fin_currencies
                  WHERE is_base = 1 AND COALESCE(is_deleted,0) = 0");
while ($r && ($x = $r->fetch_assoc())) { $base[(int) $x['company_id']] = (string) $x['code']; }
if (!$base) { fwrite(STDERR, "✘ لا عملةَ أساسٍ مسجَّلةٌ لأيِّ شركة — لا يُشتَقُّ شيء\n"); exit(1); }
foreach ($base as $co => $cur) { echo "  شركة {$co} ⇒ الأساس {$cur}\n"; }

$total = 0;
foreach ($base as $co => $cur) {
    $curQ = $db->real_escape_string($cur);

    /* ① الدفعات — بعملةِ الأساس */
    $n = (int) $db->query("SELECT COUNT(*) FROM fin_payments
                            WHERE company_id = {$co} AND currency = '{$curQ}' AND fx_rate IS NULL")
                  ->fetch_row()[0];
    if ($n > 0) {
        if ($db->query("UPDATE fin_payments SET fx_rate = 1.00000000,
                          base_amount = ROUND(amount * 1.00000000, 2)
                        WHERE company_id = {$co} AND currency = '{$curQ}' AND fx_rate IS NULL") === false) {
            fwrite(STDERR, '✘ الدفعات: ' . $db->error . "\n"); exit(1);
        }
        echo "  ✔ دفعاتٌ بالأساس: {$n} ⇒ سعرٌ 1 ومعادلٌ = المبلغ\n";
        $total += $n;
    }

    /* ② وقائعُ الدفتر — بعملةِ الأساس */
    $n = (int) $db->query("SELECT COUNT(*) FROM fin_financial_events
                            WHERE company_id = {$co} AND currency = '{$curQ}' AND fx_rate IS NULL")
                  ->fetch_row()[0];
    if ($n > 0) {
        $db->query("UPDATE fin_financial_events SET fx_rate = 1.00000000,
                      base_amount = ROUND(amount * 1.00000000, 2)
                    WHERE company_id = {$co} AND currency = '{$curQ}' AND fx_rate IS NULL");
        echo "  ✔ وقائعُ دفترٍ بالأساس: {$n}\n";
        $total += $n;
    }

    /* ③ الذمم — بعملةِ الأساس */
    $n = (int) $db->query("SELECT COUNT(*) FROM fin_receivables
                            WHERE company_id = {$co} AND currency = '{$curQ}'
                              AND fx_rate_recognized IS NULL AND COALESCE(is_deleted,0) = 0")
                  ->fetch_row()[0];
    if ($n > 0) {
        $db->query("UPDATE fin_receivables SET fx_rate_recognized = 1.00000000,
                      base_amount = ROUND(amount * 1.00000000, 2)
                    WHERE company_id = {$co} AND currency = '{$curQ}'
                      AND fx_rate_recognized IS NULL AND COALESCE(is_deleted,0) = 0");
        echo "  ✔ ذممٌ بالأساس: {$n}\n";
        $total += $n;
    }
}

/* ── ④ صفوفٌ بمبلغٍ صفريٍّ بعملةٍ غيرِ الأساس: معادلُها صفرٌ بأيِّ سعر ─────── */
echo "\n── صفرُ المبلغِ: معادلُه صفرٌ بأيِّ سعرٍ كان\n";
$zn = (int) $db->query("SELECT COUNT(*) FROM fin_financial_events
                         WHERE fx_rate IS NULL AND ROUND(COALESCE(amount,0), 2) = 0")->fetch_row()[0];
if ($zn > 0) {
    /* السعرُ من السجلِّ لتاريخِ الواقعةِ — أحدثُ سعرٍ ساريٍّ لعملتِها */
    $db->query("UPDATE fin_financial_events e
                  SET e.fx_rate = COALESCE((SELECT r.rate_to_base FROM fin_fx_rates r
                                             WHERE r.currency_code = e.currency AND r.company_id = e.company_id
                                               AND COALESCE(r.is_deleted,0) = 0
                                             ORDER BY r.effective_from DESC LIMIT 1), 1.00000000),
                      e.base_amount = 0.00
                WHERE e.fx_rate IS NULL AND ROUND(COALESCE(e.amount,0), 2) = 0");
    echo "  ✔ وقائعُ بمبلغٍ صفريّ: {$zn} ⇒ سعرٌ من السجلِّ ومعادلٌ صفر\n";
    $total += $zn;
} else { echo "  ○ لا شيء\n"; }

/* ── الحصيلةُ وما بقي مُعلَنًا ─────────────────────────────────────────────── */
echo "\n── الباقي (يُعلَن ولا يُخترع له سعر)\n";
foreach (array(
    'fin_payments'          => 'fx_rate IS NULL',
    'fin_financial_events'  => 'fx_rate IS NULL',
    'fin_receivables'       => 'fx_rate_recognized IS NULL AND COALESCE(is_deleted,0)=0',
    'fin_journal_entries'   => 'fx_rate IS NULL AND COALESCE(is_deleted,0)=0',
) as $t => $w) {
    $q = $db->query("SELECT COUNT(*) FROM `{$t}` WHERE {$w}");
    $left = $q ? (int) $q->fetch_row()[0] : -1;
    echo '  ' . str_pad($t, 26) . ($left === 0 ? '✔ صفر' : '⚠ ' . $left) . "\n";
    if ($left > 0) {
        $g = $db->query("SELECT currency, COUNT(*) c FROM `{$t}` WHERE {$w} GROUP BY currency");
        while ($g && ($x = $g->fetch_assoc())) {
            echo '      ' . str_pad((string) $x['currency'], 8) . $x['c'] . " — لا سعرَ مسجَّلٌ يُشتَقُّ منه\n";
        }
    }
}
echo "\n✅ مُلئ {$total} صفًّا باشتقاقٍ حتميٍّ — ولا سعرَ مخترَعٌ لصفٍّ ذي مبلغ.\n";
exit(0);
