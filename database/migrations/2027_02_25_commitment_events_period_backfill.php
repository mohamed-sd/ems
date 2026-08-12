<?php
/**
 * 2027_02_25 — ثلاثةُ أحداثِ «التزامِ عقدٍ» بلا زمنٍ ولا فترةٍ مالية
 * ═══════════════════════════════════════════════════════════════════════════
 * `ContractSignedEffects` كان يكتب حدثَ `contract.commitment` بـ`INSERT` صريحٍ
 * **لا عبر `EventPublisher`**، فخلا الصفُّ من `occurred_at` (نصٌّ فارغ) ومن
 * `fiscal_period_id` (NULL). **وحدثٌ بلا فترةٍ ماليةٍ مالٌ خارجَ الزمن**: لا
 * يدخل إقفالَ فترةٍ ولا ميزانَها، ولا يظهر في تقريرٍ مؤرَّخ.
 * (أُصلح المنتجُ قبل هذه الهجرة: التاريخُ من **توقيعِ العقد** والفترةُ بقاعدةِ
 *  `EventPublisher::resolvePeriodId` نفسِها.)
 *
 * وهذه الهجرةُ تُعبِّئ الثلاثةَ القائمة: زمنُها **تاريخُ توقيعِ عقدِها** —
 * مصدرٌ مقيسٌ لا لحظةُ الترحيل — وفترتُها الشهرُ الحاوي له. وما لا يجد فترةً
 * مسجَّلةً **يُعلَن**: زمنُه يُصحَّح ولا تُخترَع له فترةٌ غيرُ موجودة.
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
$before = (int) $one('SELECT COUNT(*) FROM fin_financial_events
                       WHERE COALESCE(is_deleted,0) = 0 AND fiscal_period_id IS NULL');
echo "── ① أحداثٌ بلا فترةٍ ماليةٍ قبل: {$before}\n";
if ($before === 0) { echo "\n✅ لا حدثَ بلا فترة — لا عمل.\n"; exit(0); }

$r = $db->query("SELECT e.id, e.event_key, e.company_id, e.contract_id,
                        e.occurred_at, c.contract_signing_date sig
                   FROM fin_financial_events e
                   LEFT JOIN contracts c ON c.id = e.contract_id
                  WHERE COALESCE(e.is_deleted,0) = 0 AND e.fiscal_period_id IS NULL
                  ORDER BY e.id");
$rows = array();
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
foreach ($rows as $x) {
    echo "     #{$x['id']} {$x['event_key']} عقد=" . ($x['contract_id'] ?: '—')
       . ' توقيع=' . ($x['sig'] ?: '—')
       . ' وقع=' . ($x['occurred_at'] === null || $x['occurred_at'] === '' ? '(فارغ)' : $x['occurred_at']) . "\n";
}

/* ── ② الزمنُ من مصدرِه ثم الفترةُ من الزمن ────────────────────────────── */
$st = $db->prepare('UPDATE fin_financial_events SET occurred_at = ?, fiscal_period_id = ? WHERE id = ?');
if (!$st) { fwrite(STDERR, 'prepare: ' . $db->error . "\n"); exit(1); }
$filled = 0; $timeOnly = array();
foreach ($rows as $x) {
    $sig = trim((string) $x['sig']);
    if ($sig === '' || $sig === '0000-00-00') {
        $timeOnly[] = '#' . $x['id'] . ' (لا تاريخَ توقيعٍ لعقدِه)';
        continue;
    }
    $occ = gmdate('Y-m-d H:i:s', strtotime($sig));
    $co  = (int) $x['company_id'];
    $day = substr($occ, 0, 10);
    $pid = $one("SELECT id FROM fin_financial_periods
                  WHERE company_id = {$co} AND period_type = 'month'
                    AND '{$day}' BETWEEN start_date AND end_date LIMIT 1");
    $pidV = ($pid === null) ? null : (int) $pid;
    $id = (int) $x['id'];
    $st->bind_param('sii', $occ, $pidV, $id);
    if (!$st->execute()) { $fail[] = '#' . $id . ': ' . mb_substr($st->error, 0, 60); continue; }
    if ($pidV === null) { $timeOnly[] = '#' . $id . ' (زمنُه صُحِّح ولا فترةَ مسجَّلةً لشهرِ ' . substr($day, 0, 7) . ')'; }
    else { $filled++; }
}
$st->close();
echo "── ② عُبِّئ {$filled} حدثًا بزمنِه وفترتِه\n";
if ($timeOnly) {
    echo '     ○ يُعلَن ولا تُخترَع له فترة: ' . implode(' · ', $timeOnly) . "\n";
}

/* ── ③ الشاهدُ المُشغَّل ─────────────────────────────────────────────────── */
echo "── ③ الشاهدُ المُشغَّل\n";
$after = (int) $one('SELECT COUNT(*) FROM fin_financial_events
                      WHERE COALESCE(is_deleted,0) = 0 AND fiscal_period_id IS NULL');
echo "     أحداثٌ بلا فترةٍ بعد: {$after} " . ($after === count($timeOnly) ? "✔\n" : "✘\n");
if ($after !== count($timeOnly)) { $fail[] = "المتبقي {$after} والمعلَنُ " . count($timeOnly); }

/* ── ②-ب كشفٌ جانبيٌّ أثناء القياس: عشرةُ أحداثِ شراءٍ بلا زمن ──────────────
     `expense.purchase.recorded` (#1198..#1207) تحمل `event_key` — أي **داخلَ
     العقد** — وبلا `occurred_at`. وكلٌّ منها يشير إلى `proc_order` حقيقيٍّ
     بمرجعِه (`PRC-PO-xxxx`)، فزمنُها **مشتقٌّ من أمرِ شرائها** لا مُخترَع.
     وثمانيةٌ أخرى (#46..#53) بـ`event_key = NULL` — وهي صفوفُ ما **قبلَ العقد**
     التي يستثنيها الفاحصُ صراحةً، فلا تُمَسّ ولا تُحسَب. */
$pr = $db->query("SELECT e.id, e.entity_id FROM fin_financial_events e
                   WHERE COALESCE(e.is_deleted,0) = 0 AND e.event_key IS NOT NULL
                     AND e.entity_type = 'proc_order'
                     AND (e.occurred_at IS NULL OR e.occurred_at = ''
                          OR e.occurred_at = '0000-00-00 00:00:00')");
$procRows = array();
while ($pr && ($x = $pr->fetch_assoc())) { $procRows[] = $x; }
if ($procRows) {
    // عمودُ التاريخِ في أوامرِ الشراءِ يُقاس ولا يُفترض
    $dateCol = null;
    foreach (array('order_date', 'po_date', 'issued_at', 'created_at') as $cand) {
        $has = (int) $one("SELECT COUNT(*) FROM information_schema.columns
                            WHERE table_schema = DATABASE() AND table_name = 'proc_orders'
                              AND column_name = '{$cand}'");
        if ($has > 0) { $dateCol = $cand; break; }
    }
    echo '── ②-ب أحداثُ شراءٍ بلا زمن: ' . count($procRows)
       . ' · عمودُ تاريخِ أمرِ الشراء: ' . ($dateCol ?: '**لا يوجد**') . "\n";
    if ($dateCol !== null) {
        $ps = $db->prepare("UPDATE fin_financial_events e
                              JOIN proc_orders o ON o.id = e.entity_id
                               SET e.occurred_at = CONCAT(DATE(o.{$dateCol}), ' 00:00:00'),
                                   e.fiscal_period_id = COALESCE(e.fiscal_period_id,
                                       (SELECT p.id FROM fin_financial_periods p
                                         WHERE p.company_id = e.company_id AND p.period_type = 'month'
                                           AND DATE(o.{$dateCol}) BETWEEN p.start_date AND p.end_date LIMIT 1))
                             WHERE e.id = ?");
        if ($ps) {
            $fixedProc = 0;
            foreach ($procRows as $x) {
                $eid = (int) $x['id'];
                $ps->bind_param('i', $eid);
                if ($ps->execute() && $ps->affected_rows > 0) { $fixedProc++; }
            }
            $ps->close();
            echo "     عُبِّئ {$fixedProc} حدثَ شراءٍ بزمنِ أمرِه\n";
        }
    }
}

// الشرطُ في نطاقِ عقدِ الأحداث: `event_key` حاضرٌ (وصفوفُ ما قبلَ العقد مستثناةٌ بنصِّه)
$noTime = (int) $one("SELECT COUNT(*) FROM fin_financial_events
                       WHERE COALESCE(is_deleted,0) = 0 AND event_key IS NOT NULL
                         AND (occurred_at IS NULL OR occurred_at = '' OR occurred_at = '0000-00-00 00:00:00')");
$legacy = (int) $one("SELECT COUNT(*) FROM fin_financial_events
                       WHERE COALESCE(is_deleted,0) = 0 AND event_key IS NULL
                         AND (occurred_at IS NULL OR occurred_at = '' OR occurred_at = '0000-00-00 00:00:00')");
echo "     أحداثُ العقدِ بلا زمنٍ: {$noTime} " . ($noTime === 0 ? "✔\n" : "✘\n");
echo "     ○ وصفوفُ ما قبلَ العقدِ (event_key = NULL) خارجَ الناقلِ بنصِّ العقد: {$legacy}\n";
if ($noTime !== 0) { $fail[] = "{$noTime} حدثَ عقدٍ بلا زمن"; }

// والفترةُ تحوي زمنَ حدثِها فعلًا — لا إسنادَ إلى شهرٍ غريب
$mismatch = (int) $one("SELECT COUNT(*) FROM fin_financial_events e
                          JOIN fin_financial_periods p ON p.id = e.fiscal_period_id
                         WHERE COALESCE(e.is_deleted,0) = 0
                           AND DATE(e.occurred_at) NOT BETWEEN p.start_date AND p.end_date");
echo "     حدثٌ في فترةٍ لا تحوي زمنَه: {$mismatch} " . ($mismatch === 0 ? "✔\n" : "✘ (أقدمُ من هذه الهجرة — يُعلَن)\n");

echo "\n" . (empty($fail)
    ? "✅ أحداثُ الالتزامِ صارت مؤرَّخةً بتوقيعِ عقودِها وداخلَ فتراتِها — لا مالَ خارجَ الزمن.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
