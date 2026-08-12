<?php
/**
 * 2027_02_26 — عشرةُ أحداثِ شراءٍ بلا زمن: زمنُها من أمرِ شرائها
 * ═══════════════════════════════════════════════════════════════════════════
 * كُشفت أثناء قياسِ `2027_02_25` (التي خرجت مبكرًا بعد إصلاحِ صنفِها فلم تبلغ
 * هذا الصنف): عشرةُ أحداثِ `expense.purchase.recorded` (#1198..#1207) تحمل
 * `event_key` — أي **داخلَ عقدِ الأحداث** — و`occurred_at` فيها **فارغ**.
 * وحدثٌ بلا زمنٍ لا يدخل إقفالَ فترةٍ ولا تقريرًا مؤرَّخًا.
 *
 * وكلٌّ منها يشير إلى `proc_orders` حقيقيٍّ بمرجعِه (`PRC-PO-xxxx`) — فالزمنُ
 * **مشتقٌّ من أمرِ شرائه**، لا لحظةَ ترحيلٍ تُكتب مكانَ تاريخٍ ضائع.
 *
 * وثمانيةُ صفوفٍ أخرى (#46..#53) بـ`event_key = NULL` **لا تُمَسّ**: هي ما قبلَ
 * عقدِ الأحداثِ ويستثنيها الفاحصُ بنصِّه («أحداث العقد حصرًا»).
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
$EMPTY_TIME = "(occurred_at IS NULL OR occurred_at = '' OR occurred_at = '0000-00-00 00:00:00')";

/* ── ① القياسُ ───────────────────────────────────────────────────────────── */
$before = (int) $one("SELECT COUNT(*) FROM fin_financial_events
                       WHERE COALESCE(is_deleted,0) = 0 AND event_key IS NOT NULL AND {$EMPTY_TIME}");
$legacy = (int) $one("SELECT COUNT(*) FROM fin_financial_events
                       WHERE COALESCE(is_deleted,0) = 0 AND event_key IS NULL AND {$EMPTY_TIME}");
echo "── ① أحداثُ العقدِ بلا زمنٍ قبل: {$before}\n";
echo "     ○ وما قبلَ العقدِ (event_key = NULL) خارجَ الناقلِ بنصِّه: {$legacy} — لا تُمَسّ\n";
if ($before === 0) { echo "\n✅ لا حدثَ عقدٍ بلا زمن — لا عمل.\n"; exit(0); }

$r = $db->query("SELECT event_key, entity_type, COUNT(*) n, MIN(id) mn, MAX(id) mx
                   FROM fin_financial_events
                  WHERE COALESCE(is_deleted,0) = 0 AND event_key IS NOT NULL AND {$EMPTY_TIME}
                  GROUP BY event_key, entity_type ORDER BY n DESC");
while ($r && ($x = $r->fetch_assoc())) {
    echo "     {$x['event_key']} · كيان={$x['entity_type']} · {$x['n']} (#{$x['mn']}..#{$x['mx']})\n";
}

/* ── ② جدولُ أوامرِ الشراءِ وعمودُ تاريخِه — **يُقاسان ولا يُفترضان** ────────
     الجدولُ `proc_order` **بالمفرد** (لا `proc_orders`)، وأولُ محاولةٍ سألت عن
     الجمعِ فردَّت «لا عمودَ تاريخ» — وهو **صوابُ سؤالٍ عن جدولٍ لا وجودَ له**.
     وأعمدةُ التاريخِ فيه ثلاثةَ عشرَ، وأدقُّها محاسبيًّا `invoice_date` ثم
     `sent_at`؛ **والمقيسُ أن كليهما فارغٌ في العشرة** فبقي `created_at`
     (2026-07-01) وحدَه مصدرًا. فتُرتَّب الأولويةُ صراحةً ويُعلَن أيُّها أفاد. */
$T = null;
foreach (array('proc_order', 'proc_orders') as $cand) {
    if ((int) $one("SELECT COUNT(*) FROM information_schema.tables
                     WHERE table_schema = DATABASE() AND table_name = '{$cand}'") > 0) { $T = $cand; break; }
}
if ($T === null) { fwrite(STDERR, "لا جدولَ أوامرِ شراءٍ — لا يُلفَّق زمن\n"); exit(1); }

$cols = array();
foreach (array('invoice_date', 'sent_at', 'created_at') as $cand) {
    if ((int) $one("SELECT COUNT(*) FROM information_schema.columns
                     WHERE table_schema = DATABASE() AND table_name = '{$T}'
                       AND column_name = '{$cand}'") > 0) { $cols[] = $cand; }
}
if (empty($cols)) { fwrite(STDERR, "لا عمودَ تاريخٍ في {$T} — لا يُلفَّق زمن\n"); exit(1); }
$dateExpr = 'COALESCE(' . implode(', ', array_map(function ($c) { return 'o.' . $c; }, $cols)) . ')';
echo "── ② الجدول: {$T} · أولويةُ التاريخ: " . implode(' ← ', $cols) . "\n";
foreach ($cols as $c) {
    $n = (int) $one("SELECT COUNT(*) FROM {$T} o
                      JOIN fin_financial_events e ON e.entity_id = o.id AND e.entity_type = 'proc_order'
                     WHERE COALESCE(e.is_deleted,0) = 0 AND e.event_key IS NOT NULL
                       AND {$EMPTY_TIME} AND o.{$c} IS NOT NULL");
    echo "     {$c}: مُعبَّأٌ في {$n} أمرًا من أوامرِ هذه الأحداث\n";
}

/* ── ③ الزمنُ من أمرِ الشراءِ ثم الفترةُ من الزمن ───────────────────────── */
$ok = $db->query("UPDATE fin_financial_events e
                    JOIN {$T} o ON o.id = e.entity_id
                     SET e.occurred_at = CONCAT(DATE({$dateExpr}), ' 00:00:00'),
                         e.fiscal_period_id = COALESCE(e.fiscal_period_id,
                             (SELECT p.id FROM fin_financial_periods p
                               WHERE p.company_id = e.company_id AND p.period_type = 'month'
                                 AND DATE({$dateExpr}) BETWEEN p.start_date AND p.end_date LIMIT 1))
                   WHERE COALESCE(e.is_deleted,0) = 0 AND e.event_key IS NOT NULL
                     AND e.entity_type = 'proc_order' AND {$EMPTY_TIME}
                     AND {$dateExpr} IS NOT NULL");
if (!$ok) { $fail[] = 'التعبئة: ' . $db->error; }
echo '── ③ عُبِّئ ' . ($ok ? $db->affected_rows : 0) . " حدثًا بزمنِ أمرِ شرائه\n";

/* ── ④ الشاهدُ المُشغَّل ─────────────────────────────────────────────────── */
echo "── ④ الشاهدُ المُشغَّل\n";
$after = (int) $one("SELECT COUNT(*) FROM fin_financial_events
                      WHERE COALESCE(is_deleted,0) = 0 AND event_key IS NOT NULL AND {$EMPTY_TIME}");
echo "     أحداثُ العقدِ بلا زمنٍ بعد: {$after} " . ($after === 0 ? "✔\n" : "✘\n");
if ($after !== 0) {
    $fail[] = "بقي {$after} حدثًا بلا زمن";
    $r = $db->query("SELECT id, event_key, entity_type, entity_id FROM fin_financial_events
                      WHERE COALESCE(is_deleted,0) = 0 AND event_key IS NOT NULL AND {$EMPTY_TIME} LIMIT 5");
    while ($r && ($x = $r->fetch_assoc())) {
        echo "        #{$x['id']} {$x['event_key']} {$x['entity_type']}#{$x['entity_id']}\n";
    }
}

$noPeriod = (int) $one('SELECT COUNT(*) FROM fin_financial_events
                         WHERE COALESCE(is_deleted,0) = 0 AND event_key IS NOT NULL
                           AND fiscal_period_id IS NULL');
echo "     أحداثُ العقدِ بلا فترةٍ: {$noPeriod} " . ($noPeriod === 0 ? "✔\n" : "✘\n");
if ($noPeriod !== 0) { $fail[] = "{$noPeriod} حدثًا بلا فترة"; }

// والفترةُ تحوي زمنَ حدثِها فعلًا
$mism = (int) $one('SELECT COUNT(*) FROM fin_financial_events e
                      JOIN fin_financial_periods p ON p.id = e.fiscal_period_id
                     WHERE COALESCE(e.is_deleted,0) = 0 AND e.event_key IS NOT NULL
                       AND DATE(e.occurred_at) NOT BETWEEN p.start_date AND p.end_date');
echo "     حدثٌ في فترةٍ لا تحوي زمنَه: {$mism}" . ($mism === 0 ? " ✔\n" : " (يُعلَن — أقدمُ من هذه الهجرة)\n");

echo "\n" . (empty($fail)
    ? "✅ كلُّ حدثٍ داخلَ عقدِ الأحداثِ صار مؤرَّخًا بمصدرِه — لا مالَ خارجَ الزمن.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
