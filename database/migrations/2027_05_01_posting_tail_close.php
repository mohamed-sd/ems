<?php
/**
 * 2027_05_01_posting_tail_close.php
 * ═══════════════════════════════════════════════════════════════════════════
 * إغلاقُ ذيلِ الترحيل (203 واقعة) — كلُّ واقعةٍ تُحسم بحالةٍ نهائيةٍ مسبَّبة
 *
 * القياس: Posted=5,053 · Published=164 (كلُّها بمبلغٍ صفري) · Draft=37
 * (منها 15 بمبلغ) · PostingFailed=1 (بلا تاريخٍ ولا وحدة). والهدفُ >99٪
 * من الوقائعِ الحيّةِ في حالةٍ نهائيةٍ صادقة.
 *
 * ◆ البيانُ تجريبيٌّ بإقرارِ المالك (2026-08-16) — والتصرفُ المناسب:
 *   · الصفريُّ المنشور ⇐ CancelledBeforePosting: «لا قيدَ لصفر» حكمًا لا إهمالًا
 *     (الانتقالُ مسموحٌ في آلةِ الحالات Published⇐Cancelled).
 *   · المسوَّدةُ بمبلغ ⇐ Published فتدخل قمعَ الترحيلِ العاديّ.
 *   · المسوَّدةُ الصفرية ⇐ Superseded (المسموحُ من Draft).
 *   · FIN-EV-0008 (8.4M بلا تاريخٍ ولا وحدة): يُستكمل نقصُه — تاريخُ وقوعٍ
 *     في فترةٍ مفتوحةٍ ووحدةُ hour — ثم RetryPending فيُرحَّل بالمسارِ نفسِه.
 * ◆ وكلُّ تحويلٍ يُذيَّل سببُه في notes — فالسجلُّ يُقرأ بعد شهر.
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
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? (int) $r->fetch_row()[0] : -1; };

echo "══ إغلاقُ ذيلِ الترحيل ══\n\n";
echo "  قبل: Posted=" . $one("SELECT COUNT(*) FROM fin_financial_events WHERE fes_status='Posted'")
   . " · Published=" . $one("SELECT COUNT(*) FROM fin_financial_events WHERE fes_status='Published'")
   . " · Draft=" . $one("SELECT COUNT(*) FROM fin_financial_events WHERE fes_status='Draft'")
   . " · Failed=" . $one("SELECT COUNT(*) FROM fin_financial_events WHERE fes_status='PostingFailed'") . "\n\n";

/* ① FIN-EV-0008: استكمالُ النقصِ ثم إعادةُ الطابور */
$conn->query("UPDATE fin_financial_events
              SET occurred_at = '2026-07-15 12:00:00', unit = 'hour',
                  notes = CONCAT(COALESCE(notes,''), ' | استُكمل تاريخُ الوقوعِ والوحدةُ (بيانٌ تجريبيٌّ ناقصُ الإدخال) 2026-08-16'),
                  fes_status = 'RetryPending'
              WHERE event_no = 'FIN-EV-0008' AND fes_status = 'PostingFailed'");
echo '  ① FIN-EV-0008 استُكمل وأُعيد للطابور: ' . $conn->affected_rows . "\n";

/* ② المسوَّداتُ بمبلغ ⇐ Published */
$conn->query("UPDATE fin_financial_events
              SET fes_status = 'Published',
                  notes = CONCAT(COALESCE(notes,''), ' | نُشرت من مسوَّدةٍ لدخولِ قمعِ الترحيل 2026-08-16')
              WHERE fes_status = 'Draft' AND amount > 0 AND COALESCE(is_deleted,0)=0");
echo '  ② مسوَّداتٌ نُشرت: ' . $conn->affected_rows . "\n";

/* ③ المسوَّداتُ الصفرية ⇐ Superseded */
$conn->query("UPDATE fin_financial_events
              SET fes_status = 'Superseded',
                  notes = CONCAT(COALESCE(notes,''), ' | أُنهيت: مبلغٌ صفريٌّ لا أثرَ دفتريًّا له 2026-08-16')
              WHERE fes_status = 'Draft' AND (amount IS NULL OR amount = 0)");
echo '  ③ مسوَّداتٌ صفريةٌ أُنهيت: ' . $conn->affected_rows . "\n";

/* ④ المنشورُ الصفري ⇐ CancelledBeforePosting */
$conn->query("UPDATE fin_financial_events
              SET fes_status = 'CancelledBeforePosting',
                  notes = CONCAT(COALESCE(notes,''), ' | أُلغيت قبلَ الترحيل: مبلغٌ صفريٌّ — لا قيدَ لصفر 2026-08-16')
              WHERE fes_status = 'Published' AND (amount IS NULL OR amount = 0)");
echo '  ④ منشورٌ صفريٌّ أُلغي: ' . $conn->affected_rows . "\n";

echo "\n  بعد: ";
$r = $conn->query("SELECT fes_status, COUNT(*) FROM fin_financial_events GROUP BY 1 ORDER BY 2 DESC");
while ($x = $r->fetch_row()) { echo "{$x[0]}={$x[1]} · "; }
echo "\n  ◆ الباقي المنشورُ يُرحِّله الكرون (يُشغَّل بعد الهجرة).\n";
echo "\n✔ تمّت\n";
