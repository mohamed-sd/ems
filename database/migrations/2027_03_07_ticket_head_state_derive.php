<?php
/**
 * 2027_03_07 — `head_state` عمودٌ **مشتقٌّ**: يُستكمل لِما أُلغي في 2027_03_06
 * ═══════════════════════════════════════════════════════════════════════════
 * **عطبٌ أحدثتُه بنفسي وكشفه فاحصٌ في الحال.** هجرةُ `2027_03_06` نقلت خمسةَ
 * عشرَ بلاغًا (آثارَ تحقُّقٍ يدويّ) إلى `cancelled` — **ونصفَ الاشتقاقِ فقط**:
 * `tickets.head_state` عمودٌ **مشتقٌّ** لا مصدرُ حقيقةٍ (تعليقُ `schema.sql`)،
 * وقاعدتُه: `المغلقُ أو الملغى ⇒ head_state = 'closed'`
 * (و`done` منجَزٌ بانتظارِ التأكيدِ فرأسُه **مفتوح** — قرارٌ مسجَّل).
 *
 * فبقيت **أربعةَ عشرَ** صفًّا مرحلتُها `cancelled`/`closed` ورأسُها `open`، فرسب
 * `tkt_state_effect_test` و`tkt_structure_test` على «ولا مغلقٌ أو ملغًى قديمٌ
 * برأسٍ مفتوح» — **وهو حكمٌ صحيحٌ أدانني بحقّ**.
 *
 * ⇒ يُستكمل الاشتقاقُ من المرحلةِ (مصدرِ الحقيقة)، ولا يُمَسُّ `done`.
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
$before = (int) $one("SELECT COUNT(*) FROM tickets
                       WHERE stage IN ('closed','cancelled') AND head_state = 'open'");
echo "── ① مغلقٌ أو ملغًى برأسٍ مفتوح: {$before}\n";
$r = $db->query("SELECT stage, head_state, COUNT(*) n FROM tickets
                  GROUP BY stage, head_state ORDER BY n DESC");
while ($r && ($x = $r->fetch_assoc())) {
    printf("     %-12s ⇒ رأس=%-8s %s\n", $x['stage'], $x['head_state'], $x['n']);
}
if ($before === 0) { echo "\n✅ الاشتقاقُ كاملٌ — لا عمل.\n"; exit(0); }

/* ── ② الاشتقاقُ من المرحلة — و`done` لا يُمَسّ ─────────────────────────── */
$ok = $db->query("UPDATE tickets SET head_state = 'closed'
                   WHERE stage IN ('closed','cancelled') AND head_state <> 'closed'");
if (!$ok) { $fail[] = 'الاشتقاق: ' . $db->error; }
echo '── ② اشتُقَّ رأسُ ' . ($ok ? $db->affected_rows : 0) . " بلاغًا من مرحلتِه\n";

/* ── ③ الشاهدُ المُشغَّل ─────────────────────────────────────────────────── */
echo "── ③ الشاهدُ المُشغَّل\n";
$after = (int) $one("SELECT COUNT(*) FROM tickets
                      WHERE stage IN ('closed','cancelled') AND head_state = 'open'");
echo "     مغلقٌ أو ملغًى برأسٍ مفتوح: {$after} " . ($after === 0 ? "✔\n" : "✘\n");
if ($after !== 0) { $fail[] = "بقي {$after} برأسٍ مفتوح"; }

// و`done` بقي برأسٍ مفتوحٍ — قرارٌ مسجَّلٌ لا سهو
$doneOpen = (int) $one("SELECT COUNT(*) FROM tickets WHERE stage = 'done' AND head_state = 'open'");
$doneAll  = (int) $one("SELECT COUNT(*) FROM tickets WHERE stage = 'done'");
echo "     و«منجَز» برأسٍ مفتوح: {$doneOpen} من {$doneAll} — قرارٌ مسجَّلٌ (منجَزٌ بانتظارِ التأكيد)\n";

// ولا رأسَ مغلقٌ لبلاغٍ عاملٍ — الاتجاهُ الآخر
$liveClosedHead = (int) $one("SELECT COUNT(*) FROM tickets
                              WHERE stage IN ('new','classified','routed','in_progress','waiting','follow_up')
                                AND head_state = 'closed'");
echo "     وبلاغٌ عاملٌ برأسٍ مغلق: {$liveClosedHead} " . ($liveClosedHead === 0 ? "✔\n" : "⚠ يُعلَن\n");

echo "\n" . (empty($fail)
    ? "✅ `head_state` صار مشتقًّا من مرحلتِه في كلِّ صفّ — والاشتقاقُ الذي بدأتُه أمسِ اكتمل.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
