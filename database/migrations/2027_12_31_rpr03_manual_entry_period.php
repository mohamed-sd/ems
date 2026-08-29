<?php
/**
 * 2027_12_31_rpr03_manual_entry_period.php — فترةُ القيدِ اليدويِّ **تُشتقّ لا تُخترَع**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما يوجبه الأمر** — `RPR-03` §٨·١: القيدُ اليدويُّ يحمل سبعةَ حقولٍ، ومنها
 *   **الفترة**. والمقيس: **١٬٦٤٤ قيدًا يدويًّا** و**صفرٌ منها بفترة**.
 *
 * ◆ **وواحدٌ من الأربعةِ الناقصةِ يُشتقُّ بدليلٍ — وثلاثةٌ لا تُشتقّ**:
 *   · **الفترةُ تُشتقّ**: `posting_date` يقع في فترةٍ محاسبيّةٍ معرَّفةٍ في
 *     `fin_financial_periods` — **وقِيس فلم يقع تاريخٌ واحدٌ في فترتَين**
 *     (أقصى تطابقٍ = ١). فهي قراءةٌ من جدولٍ محكومٍ لا تأليف.
 *   · ⛔ **ونوعُ المنشأ والمستندُ والمعتمِدُ لا تُشتقّ**: قِيس الصفُّ اليدويُّ
 *     فإذا **`request_no` صفرٌ · و`reversal_link` صفرٌ · و`recognition_request_id`
 *     صفرٌ · و`request_owner` صفر** — **فلا شاهدَ في الصفِّ نفسِه يدلُّ عليها**.
 *     ومن يملؤها يخترعُ مستندًا ومعتمِدًا لقيدٍ ماليّ. ⇒ تبقى فارغةً مُعلَنةً.
 *
 * ◆ **والصيغةُ متَّبَعةٌ لا مُبتكَرة**: `AccountingCycleService::onRecognitionRequested`
 *   يكتب `period_code` = `(string) $period['id']` — فتُتَّبع الصيغةُ نفسُها كي
 *   لا يصير للفترةِ ترميزان في نظامٍ واحد.
 *
 * ◆ **وما لا فترةَ له يُعلَن ولا يُلفَّق**: التقويمُ المحاسبيُّ يعرّف اثنتَين
 *   وعشرين فترةً (`2026-01` … `2027-11`)، **ودفترُ القيدِ يبدأ من `2020-03`**
 *   ⇒ **١٬١٠٣ قيدًا خارجَ أيِّ فترةٍ معرَّفة**. وفتحُ فتراتٍ تاريخيّةٍ **قرارُ
 *   ماليّةٍ لا قرارُ منفِّذ** (أرصدةٌ افتتاحيّةٌ وحوكمةُ فتراتٍ مقفلة).
 *
 * التشغيل: php database/migrations/2027_12_31_rpr03_manual_entry_period.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);

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

$one = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    if (!$r) { exit("✘ استعلامٌ سقط: {$conn->error}\n   $sql\n"); }
    $x = $r->fetch_row(); return $x ? $x[0] : null;
};

$MANUAL = "COALESCE(is_deleted,0) = 0 AND (event_id IS NULL OR event_id = 0)";

/* ① ⛔ **الحارسُ قبلَ الكتابة**: تاريخٌ يقع في فترتَين يُبطل الاشتقاقَ كلَّه. */
$amb = (int) $one("SELECT COUNT(*) FROM (
          SELECT j.id, (SELECT COUNT(*) FROM fin_financial_periods p
                         WHERE p.period_type = 'month' AND p.company_id = j.company_id
                           AND j.posting_date BETWEEN p.start_date AND p.end_date) AS n
            FROM fin_journal_entries j
           WHERE $MANUAL) t WHERE t.n > 1");
if ($amb > 0) {
    exit("⛔ **$amb قيدًا يقع تاريخُه في فترتَين** — والاشتقاقُ لا يُشغَّل على غموض\n");
}
echo "  ✔ لا تاريخَ يقع في فترتَين — الاشتقاقُ حتميّ\n";

$before = (int) $one("SELECT COUNT(*) FROM fin_journal_entries WHERE $MANUAL AND period_code <> ''");
$total  = (int) $one("SELECT COUNT(*) FROM fin_journal_entries WHERE $MANUAL");

/* ② الاشتقاقُ — مطابقةً بالشركةِ والتاريخِ معًا */
$ok = $conn->query("UPDATE fin_journal_entries j
    JOIN fin_financial_periods p
      ON p.period_type = 'month' AND p.company_id = j.company_id
     AND j.posting_date BETWEEN p.start_date AND p.end_date
     SET j.period_code = CAST(p.id AS CHAR)
   WHERE $MANUAL AND j.period_code = ''");
if (!$ok) { exit("✘ تعذّر الاشتقاق: {$conn->error}\n"); }
$filled = $conn->affected_rows;

$after  = (int) $one("SELECT COUNT(*) FROM fin_journal_entries WHERE $MANUAL AND period_code <> ''");
$noPer  = (int) $one("SELECT COUNT(*) FROM fin_journal_entries j WHERE $MANUAL
                       AND NOT EXISTS(SELECT 1 FROM fin_financial_periods p
                                       WHERE p.period_type = 'month' AND p.company_id = j.company_id
                                         AND j.posting_date BETWEEN p.start_date AND p.end_date)");
$span = $conn->query("SELECT MIN(posting_date), MAX(posting_date) FROM fin_journal_entries WHERE $MANUAL")->fetch_row();
$cal  = $conn->query("SELECT MIN(start_date), MAX(end_date) FROM fin_financial_periods WHERE period_type='month'")->fetch_row();

printf("\n  القيدُ اليدويُّ **%d** · بفترةٍ قبلُ %d ⇐ بعدُ **%d** (‏اشتُقَّ %d)\n",
       $total, $before, $after, $filled);
printf("  ⛔ **خارجَ أيِّ فترةٍ معرَّفة: %d** — ودفترُ القيدِ يمتدُّ %s … %s والتقويمُ %s … %s\n",
       $noPer, $span[0], $span[1], $cal[0], $cal[1]);
echo "     ⇒ `Track RPR-03 §٨·١ blocked at stage: تقويمٌ محاسبيٌّ لا يغطّي تاريخَ الدفتر`\n";
echo "     ⛔ **وفتحُ فتراتٍ تاريخيّةٍ قرارُ ماليّةٍ لا قرارُ منفِّذ.**\n";

/* ③ الثلاثةُ الباقيةُ — تُقاس ولا تُملأ */
$noKind = (int) $one("SELECT COUNT(*) FROM fin_journal_entries WHERE $MANUAL AND manual_kind = ''");
$noDoc  = (int) $one("SELECT COUNT(*) FROM fin_journal_entries WHERE $MANUAL AND source_doc_ref = ''");
$noApp  = (int) $one("SELECT COUNT(*) FROM fin_journal_entries WHERE $MANUAL AND approval_ref = ''");
$evi    = (int) $one("SELECT COUNT(*) FROM fin_journal_entries WHERE $MANUAL
                       AND (COALESCE(request_no,'') <> '' OR reversal_link IS NOT NULL
                            OR recognition_request_id > 0 OR COALESCE(request_owner,'') <> '')");
printf("\n  ── الثلاثةُ التي لا تُشتقّ — تُقاس ولا تُملأ ──\n");
printf("     نوعُ المنشأ فارغٌ %d · المستندُ %d · المعتمِدُ %d\n", $noKind, $noDoc, $noApp);
printf("     **وصفٌّ يحمل شاهدًا يدلُّ عليها: %d من %d** ⇒ لا مصدرَ اشتقاقٍ في الصفِّ نفسِه\n", $evi, $total);
echo "     ⛔ ومن يملؤها **يخترع مستندًا ومعتمِدًا لقيدٍ ماليّ** — فتبقى فارغةً مُعلَنة.\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ الفترةُ اشتُقَّت بدليلِها — والثلاثةُ الباقيةُ مُعلَنةٌ لا مُلفَّقة\n";
