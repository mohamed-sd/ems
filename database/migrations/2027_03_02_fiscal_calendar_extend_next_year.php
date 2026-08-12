<?php
/**
 * 2027_03_02 — التقويمُ الماليُّ ينتهي قبلَ الأحداث: امتدادُ سنةٍ + إعادةُ إسناد
 * ═══════════════════════════════════════════════════════════════════════════
 * **المقيس**: أحداثُ العقدِ تمتدُّ إلى **2027-05-13**، و`fin_financial_periods`
 * لا يحمل إلا **اثني عشرَ شهرًا لسنةِ 2026** (تنتهي 2026-12-31). فكلُّ حدثٍ بعدها
 * يُنشَر بـ`fiscal_period_id = NULL` — **مالٌ خارجَ الزمن**: لا يدخل إقفالَ فترةٍ
 * ولا ميزانَها ولا تقريرًا مؤرَّخًا.
 *
 * وهو **عينُ العطبِ الذي عولج صباحَ اليوم** في `2027_02_25/26` (أحداثُ التزامِ
 * عقدٍ وأحداثُ شراءٍ بلا زمنٍ ولا فترة) — لكنّ ذاك كان زمنًا ناقصًا، وهذا
 * **تقويمًا أقصرَ من الزمن**. فما لم يمتدَّ التقويمُ تكرَّر العطبُ حتمًا مع كلِّ
 * حدثٍ يقع بعد آخرِ شهرٍ مسجَّل.
 *
 * **الشكلُ منقولٌ من التقويمِ القائمِ لا مُخترَع**: شهورُ 2026-10/11/12 مسجَّلةٌ
 * `state='planned'` و`posting_allowed=0` — أي **معرَّفةٌ زمنيًّا وغيرُ مفتوحةٍ
 * للترحيل**. فتُنشأ شهورُ السنةِ التالية بالشكلِ نفسِه: الحدثُ يجد فترتَه (فيصير
 * داخلَ الزمن) **ولا يُفتح بابُ ترحيلٍ بقرارٍ من هجرة** — فتحُ الفترةِ حكمُ
 * حاكمٍ لا أثرُ ترحيل.
 *
 * مُتحمِّلٌ للتكرار · ويعيد إسنادَ كلِّ حدثٍ صار له فترةٌ الآن.
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

/* ── ① القياسُ: إلى أين يبلغ الزمنُ وإلى أين يبلغ التقويم؟ ────────────────── */
$maxEvent = (string) $one("SELECT MAX(occurred_at) FROM fin_financial_events
                            WHERE COALESCE(is_deleted,0) = 0 AND event_key IS NOT NULL
                              AND occurred_at IS NOT NULL AND occurred_at <> ''");
echo "── ① أقصى زمنِ حدثٍ في العقد: " . ($maxEvent !== '' ? $maxEvent : '(لا شيء)') . "\n";
$r = $db->query("SELECT company_id, MAX(fiscal_year) my, MAX(end_date) mend, COUNT(*) c
                   FROM fin_financial_periods WHERE period_type = 'month'
                  GROUP BY company_id ORDER BY company_id");
$cals = array();
while ($r && ($x = $r->fetch_assoc())) { $cals[] = $x; }
foreach ($cals as $c) {
    echo "     شركة {$c['company_id']}: أقصى سنة={$c['my']} · ينتهي {$c['mend']} · {$c['c']} شهرًا\n";
}
if (empty($cals)) { fwrite(STDERR, "لا تقويمَ شهريًّا قائمًا يُنسَخ شكلُه — لا يُخترَع تقويم\n"); exit(1); }

$orphanBefore = (int) $one("SELECT COUNT(*) FROM fin_financial_events
                             WHERE COALESCE(is_deleted,0) = 0 AND event_key IS NOT NULL
                               AND fiscal_period_id IS NULL");
echo "── ② أحداثُ عقدٍ بلا فترةٍ قبل: {$orphanBefore}\n";

/* ── ③ الشكلُ المنقولُ من آخرِ شهرٍ مسجَّل ─────────────────────────────────── */
$model = null;
$r = $db->query("SELECT state, posting_allowed FROM fin_financial_periods
                  WHERE period_type = 'month' ORDER BY end_date DESC LIMIT 1");
if ($r) { $model = $r->fetch_assoc(); }
if (!$model) { fwrite(STDERR, "لا شكلَ يُنسَخ\n"); exit(1); }
$state = (string) $model['state'];
$post  = (int) $model['posting_allowed'];
echo "── ③ الشكلُ المنقول: state={$state} · posting_allowed={$post}"
   . ($post === 0 ? " (معرَّفٌ زمنيًّا · غيرُ مفتوحٍ للترحيل ✔)\n" : " ⚠ مفتوحٌ للترحيل — يُنقل كما هو ولا يُقرَّر فتحٌ هنا\n");

/* ── ④ إنشاءُ شهورِ السنةِ التالية لكلِّ شركةٍ لها تقويم ─────────────────── */
$st = $db->prepare("INSERT INTO fin_financial_periods
                    (company_id, fiscal_year, period_type, period_no, start_date, end_date,
                     state, posting_allowed, created_by, created_at)
                    VALUES (?, ?, 'month', ?, ?, ?, ?, ?, 0, NOW())");
if (!$st) { fwrite(STDERR, 'prepare: ' . $db->error . "\n"); exit(1); }

$added = 0;
foreach ($cals as $c) {
    $co   = (int) $c['company_id'];
    $year = (int) $c['my'] + 1;
    for ($mn = 1; $mn <= 12; $mn++) {
        $from = sprintf('%04d-%02d-01', $year, $mn);
        $to   = date('Y-m-t', strtotime($from));
        $has  = (int) $one("SELECT COUNT(*) FROM fin_financial_periods
                             WHERE company_id = {$co} AND period_type = 'month'
                               AND start_date = '{$from}'");
        if ($has > 0) { continue; }
        $st->bind_param('iiisssi', $co, $year, $mn, $from, $to, $state, $post);
        if (!$st->execute()) { $fail[] = "شركة {$co} {$from}: " . mb_substr($st->error, 0, 60); continue; }
        $added++;
    }
    echo "     شركة {$co}: سنةُ {$year} — أُنشئ ما نقص\n";
}
$st->close();
echo "── ④ أُنشئ {$added} شهرًا\n";

/* ── ⑤ إعادةُ إسنادِ كلِّ حدثٍ صار له فترةٌ الآن ─────────────────────────── */
$ok = $db->query("UPDATE fin_financial_events e
                    JOIN fin_financial_periods p
                      ON p.company_id = e.company_id AND p.period_type = 'month'
                     AND DATE(e.occurred_at) BETWEEN p.start_date AND p.end_date
                     SET e.fiscal_period_id = p.id
                   WHERE COALESCE(e.is_deleted,0) = 0 AND e.event_key IS NOT NULL
                     AND e.fiscal_period_id IS NULL
                     AND e.occurred_at IS NOT NULL AND e.occurred_at <> ''");
if (!$ok) { $fail[] = 'إعادةُ الإسناد: ' . $db->error; }
echo '── ⑤ أُسند ' . ($ok ? $db->affected_rows : 0) . " حدثًا إلى فترتِه\n";

/* ── ⑥ الشاهدُ المُشغَّل ─────────────────────────────────────────────────── */
echo "── ⑥ الشاهدُ المُشغَّل\n";
$orphanAfter = (int) $one("SELECT COUNT(*) FROM fin_financial_events
                            WHERE COALESCE(is_deleted,0) = 0 AND event_key IS NOT NULL
                              AND fiscal_period_id IS NULL");
echo "     أحداثُ عقدٍ بلا فترةٍ بعد: {$orphanAfter} " . ($orphanAfter === 0 ? "✔\n" : "✘\n");
if ($orphanAfter !== 0) {
    $fail[] = "بقي {$orphanAfter} حدثًا بلا فترة";
    $r = $db->query("SELECT id, event_key, occurred_at FROM fin_financial_events
                      WHERE COALESCE(is_deleted,0) = 0 AND event_key IS NOT NULL
                        AND fiscal_period_id IS NULL LIMIT 4");
    while ($r && ($x = $r->fetch_assoc())) {
        echo "        #{$x['id']} {$x['event_key']} وقع={$x['occurred_at']}\n";
    }
}

// التقويمُ يبلغ الزمنَ ويتجاوزه
$calEnd = (string) $one("SELECT MAX(end_date) FROM fin_financial_periods WHERE period_type = 'month'");
$covers = ($maxEvent === '' ) || (substr($calEnd, 0, 10) >= substr($maxEvent, 0, 10));
echo "     التقويمُ ينتهي {$calEnd} وأقصى حدثٍ " . ($maxEvent !== '' ? substr($maxEvent, 0, 10) : '—')
   . ' ' . ($covers ? "✔\n" : "✘\n");
if (!$covers) { $fail[] = 'التقويمُ أقصرُ من الزمنِ بعد'; }

// ولا فترةٌ فُتحت للترحيلِ بقرارِ هجرة
$openedNew = (int) $one("SELECT COUNT(*) FROM fin_financial_periods
                          WHERE period_type = 'month' AND posting_allowed = 1
                            AND fiscal_year > 2026");
echo "     فتراتٌ جديدةٌ مفتوحةٌ للترحيل: {$openedNew} " . ($openedNew === 0 ? "✔ (الفتحُ حكمُ حاكمٍ لا أثرُ ترحيل)\n" : "⚠\n");

// وحدثٌ في فترةٍ لا تحوي زمنَه = صفر
$mism = (int) $one("SELECT COUNT(*) FROM fin_financial_events e
                      JOIN fin_financial_periods p ON p.id = e.fiscal_period_id
                     WHERE COALESCE(e.is_deleted,0) = 0 AND e.event_key IS NOT NULL
                       AND DATE(e.occurred_at) NOT BETWEEN p.start_date AND p.end_date");
echo "     حدثٌ في فترةٍ لا تحوي زمنَه: {$mism} " . ($mism === 0 ? "✔\n" : "✘\n");
if ($mism !== 0) { $fail[] = "{$mism} حدثًا في فترةٍ غريبة"; }

echo "\n" . (empty($fail)
    ? "✅ التقويمُ صار أطولَ من الزمنِ — فلا حدثَ يقع خارجَ فترةٍ، ولا فترةَ فُتحت للترحيلِ بغيرِ حكم.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
