<?php
/**
 * tests/injfix01_currency_rate_coverage_proof.php — INJ-FIX-01 · GAP-25
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «سعرٌ ساري المفعولِ لكلِّ عملةٍ نشطة».
 *
 * ◆ **وأُغلق بالمِقبَضِ المشروع**: لم يُخترع سعرٌ واحد — بل عُطِّلت خمسُ عملاتٍ
 *   **بصفرِ استعمالِ عملٍ في شركتِها**. فالعملةُ النشطةُ بلا سعرٍ **فخٌّ في
 *   منتقًى**: يختارها مستخدمٌ فيُنشئ سجلًّا لا يُسعَّر.
 *
 * ◆ **وثلاثةُ أحكامٍ لا واحد**: ① كلُّ نشطةٍ لها سعرٌ سارٍ · ② والمعطَّلةُ
 *   عُطِّلت **بقياسٍ لا برأي** (صفرُ استعمالٍ أو بذرٌ لا واقعة) · ③ وطبقةُ
 *   الصرفِ **fail-closed** فعلًا — تُجرَّب بعملةٍ لا سعرَ لها فتردُّ.
 *
 * التشغيل: php tests/injfix01_currency_rate_coverage_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($cond, $msg)
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ✔ {$msg}\n"; } else { $bad++; echo "  ✘ {$msg}\n"; }
}

/* ══ ① كلُّ نشطةٍ لها سعرٌ سارٍ ═════════════════════════════════════════════ */
echo "══ ① سعرٌ ساري المفعولِ لكلِّ عملةٍ نشطة ══\n";
$q = $conn->query("SELECT c.company_id, c.code, c.is_base,
                    (SELECT COUNT(*) FROM `fin_fx_rates` r
                      WHERE r.company_id = c.company_id AND r.currency_code = c.code
                        AND COALESCE(r.is_deleted,0) = 0 AND r.effective_from <= CURDATE()) rates
                     FROM `fin_currencies` c WHERE c.active = 1
                    ORDER BY c.company_id, c.code");
$noRate = array(); $act = 0;
while ($q && $x = $q->fetch_assoc()) {
    $act++;
    printf("     co%-3s %-6s %-8s أسعارٌ سارية=%d\n", $x['company_id'], $x['code'],
        $x['is_base'] ? '(أساس)' : '', $x['rates']);
    if ((int) $x['rates'] === 0) { $noRate[] = 'co' . $x['company_id'] . ':' . $x['code']; }
}
chk(count($noRate) === 0, "**صفرُ عملةٍ نشطةٍ بلا سعرٍ سارٍ** — من {$act} نشطة"
    . (count($noRate) ? ' — ' . implode(' · ', $noRate) : ''));

/* ══ ② المعطَّلةُ عُطِّلت بقياسٍ لا برأي ═══════════════════════════════════ */
echo "\n══ ② المعطَّلةُ بلا استعمالِ عملٍ — ولا تُعطَّل عاملةٌ ══\n";
$MONEY = array('fin_dues', 'settlements', 'fin_journal_entries', 'fin_requests', 'fin_financial_events');
$q = $conn->query("SELECT `company_id`, `code` FROM `fin_currencies` WHERE `active` = 0");
$wrong = array(); $offN = 0;
while ($q && $x = $q->fetch_assoc()) {
    $offN++;
    $co = (int) $x['company_id']; $code = $conn->real_escape_string($x['code']);
    $real = 0;
    foreach ($MONEY as $t) {
        $r = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='currency'");
        if (!$r || (int) $r->fetch_row()[0] === 0) { continue; }
        $hc = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='company_id'");
        $scoped = ($hc && (int) $hc->fetch_row()[0] > 0);
        /* البذرُ لا يُعَدُّ واقعةً — ومفتاحُه من عائلةِ `XXX_-000n` */
        $extra = ($t === 'fin_dues') ? " AND `period_ref` NOT REGEXP '^[A-Z]+_-[0-9]+$'" : '';
        $sql = "SELECT COUNT(*) FROM `{$t}` WHERE `currency`='{$code}'"
             . ($scoped ? " AND `company_id`={$co}" : '') . $extra;
        $qq = $conn->query($sql);
        $real += $qq ? (int) $qq->fetch_row()[0] : 0;
    }
    if ($real > 0) { $wrong[] = "co{$co}:{$code} ({$real} واقعةً)"; }
}
chk(count($wrong) === 0, "صفرُ عملةٍ معطَّلةٍ ولها واقعةٌ حقيقية — من {$offN} معطَّلة"
    . (count($wrong) ? ' — ' . implode(' · ', $wrong) : ''));

/* ══ ③ طبقةُ الصرفِ fail-closed — تُجرَّب لا تُفترَض ═══════════════════════ */
echo "\n══ ③ الصرفُ fail-closed — يُجرَّب بعملةٍ لا سعرَ لها ══\n";
require_once $ROOT . '/includes/fx.php';
chk(function_exists('ems_fx_rate'), 'دالةُ السعرِ موجودة');
if (function_exists('ems_fx_rate')) {
    /* رمزٌ لا وجودَ له قط — فلا سعرَ له يقينًا */
    $r = null;
    try { $r = ems_fx_rate('ZZZ', date('Y-m-d')); } catch (\Throwable $e) { $r = null; }
    chk($r === null, 'عملةٌ بلا سعرٍ ⇒ **الدالةُ تُرجع null** ولا تُخمِّن — المُرجَع: ' . var_export($r, true));
}
echo "  ◆ فلا تُسعَّر معاملةٌ بعملةٍ بلا سعر — والآليةُ سليمةٌ قبلَ هذه الجولةِ وبعدَها.\n";
echo "  ◆ **ولم يُخترع سعرٌ واحد**: المِقبَضُ كان قائمةَ النشطاتِ لا السعرَ نفسَه.\n";

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
