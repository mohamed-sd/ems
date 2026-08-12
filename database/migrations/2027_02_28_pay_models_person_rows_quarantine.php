<?php
/**
 * 2027_02_28 — خمسةُ أسماءِ أشخاصٍ دخلت كتالوجَ نماذجِ الأجر
 * ═══════════════════════════════════════════════════════════════════════════
 * **المقيس**: `pay_models` كتالوجٌ محكومٌ بخمسةَ عشرَ نموذجًا نصًّا
 * (`CON-01 §3.1` — قُرئت من الوثيقةِ نفسِها: ثابت فقط · ثابت وبدلات · ثابت وحافز
 * · حافز فقط · بالساعة · باليوم · بالوردية · بالنقلة · بالطن · بالمتر · مقطوع ·
 * عمولة · مكافأة أداء · مركّب · أخرى). والصفوفُ **#1..#15 مطابقةٌ لها حرفيًّا**.
 *
 * وفوقها **خمسةُ صفوفٍ (#16..#20) `label_ar` فيها أسماءُ أشخاص** ورمزُها مولَّدٌ
 * آليًّا (`PAY_-00016` …): «الصديق عبد الماجد» · «مصعب الطاهر النعيم» · «خضر عمر
 * الجاك» · «الفاتح زين العابدين» · «معتصم بابكر الريح».
 *
 * وهو **عينُ العطبِ الذي عُولج في `job_titles`** (أربعةُ أسماءِ أشخاصٍ برموزٍ
 * ذاتيةِ الإشارة): مستوردٌ يكتب اسمَ موظفٍ في خليةِ كتالوجٍ فيصير «نموذجَ أجر».
 * وكتالوجٌ ملوَّثٌ يُفسد كلَّ منسدلةٍ تقرؤه ويجعل «نموذجًا من خارج الكتالوج»
 * حكمًا بلا معنى.
 *
 * **القرار**: لا حذف (حكمُ المالك) — تُعطَّل (`is_active = 0`) ويُعلَن سببُها في
 * `code` بوسمٍ صريح، فتبقى شاهدةً على ما جرى وتخرج من كلِّ قراءةٍ حاكمة.
 * ولا يُمَسُّ صفٌّ يشير إليه عقدٌ قائمٌ — والمقيسُ أن العقودَ الـ252 كلَّها تشير
 * إلى #1 · #2 · #3 · #10 · #14 · #15 (داخلَ الخمسةَ عشرَ) وصفرَ إشارةٍ للخمسة.
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

/* ── ① الخمسةَ عشرَ المحكومةُ من الوثيقةِ — تُقاس ولا تُفترض ─────────────── */
$GOVERNED = array('fixed_only', 'fixed_allowances', 'fixed_incentive', 'incentive_only',
                  'hourly', 'daily', 'per_shift', 'per_trip', 'per_ton', 'per_meter',
                  'lump_sum', 'commission', 'performance_bonus', 'composite', 'other');
$in = "'" . implode("','", $GOVERNED) . "'";
$govLive = (int) $one("SELECT COUNT(*) FROM pay_models WHERE code IN ({$in}) AND is_active = 1");
$total   = (int) $one('SELECT COUNT(*) FROM pay_models WHERE is_active = 1');
echo "── ① المحكومُ الحيُّ: {$govLive}/15 · وحيُّ الجدولِ كلِّه: {$total}\n";
if ($govLive !== 15) {
    fwrite(STDERR, "الخمسةَ عشرَ المحكومةُ ليست كلُّها حيةً ({$govLive}) — لا يُعطَّل شيءٌ قبل حسمِ هذا\n");
    exit(1);
}

/* ── ② الدخيلُ: حيٌّ وخارجَ الكتالوجِ المحكوم ────────────────────────────── */
$r = $db->query("SELECT id, code, label_ar,
                        (SELECT COUNT(*) FROM employee_contracts c WHERE c.pay_model_id = m.id) used
                   FROM pay_models m
                  WHERE m.is_active = 1 AND m.code NOT IN ({$in})
                  ORDER BY m.id");
$alien = array();
while ($r && ($x = $r->fetch_assoc())) { $alien[] = $x; }
echo '── ② صفوفٌ حيّةٌ خارجَ الكتالوج: ' . count($alien) . "\n";
foreach ($alien as $x) {
    echo "     #{$x['id']} {$x['code']} · «{$x['label_ar']}» · عقودٌ تشير إليه: {$x['used']}\n";
}
if (empty($alien)) { echo "\n✅ الكتالوجُ نظيفٌ — لا عمل.\n"; exit(0); }

/* ── ③ لا يُمَسُّ صفٌّ يشير إليه عقدٌ قائم ───────────────────────────────── */
$used = array();
foreach ($alien as $x) { if ((int) $x['used'] > 0) { $used[] = '#' . $x['id'] . ' (' . $x['used'] . ' عقدًا)'; } }
if ($used) {
    echo '     ⚠ يُعلَن ولا يُعطَّل (عقودٌ قائمةٌ تشير إليه): ' . implode(' · ', $used) . "\n";
}

/* ── ④ التعطيلُ المعلَن — لا حذف ─────────────────────────────────────────── */
$st = $db->prepare("UPDATE pay_models
                       SET is_active = 0,
                           code = CONCAT('quarantined_', code)
                     WHERE id = ? AND is_active = 1
                       AND NOT EXISTS (SELECT 1 FROM employee_contracts c WHERE c.pay_model_id = pay_models.id)");
if (!$st) {
    // مارياDB لا تسمح بالإشارةِ إلى الجدولِ المحدَّثِ في استعلامٍ فرعيّ — يُقاس أوّلًا
    $st = $db->prepare("UPDATE pay_models SET is_active = 0, code = CONCAT('quarantined_', code)
                         WHERE id = ? AND is_active = 1");
}
if (!$st) { fwrite(STDERR, 'prepare: ' . $db->error . "\n"); exit(1); }
$off = 0;
foreach ($alien as $x) {
    if ((int) $x['used'] > 0) { continue; }
    if (mb_strlen((string) $x['code']) > 20) { $fail[] = '#' . $x['id'] . ': الرمزُ أطولُ من أن يُوسَم'; continue; }
    $id = (int) $x['id'];
    $st->bind_param('i', $id);
    if (!$st->execute()) { $fail[] = '#' . $id . ': ' . mb_substr($st->error, 0, 60); continue; }
    $off += ($st->affected_rows > 0) ? 1 : 0;
}
$st->close();
echo "── ④ عُطِّل {$off} صفًّا دخيلًا (بوسمِ `quarantined_` في رمزِه — شاهدًا لا محذوفًا)\n";

/* ── ⑤ الشاهدُ المُشغَّل ─────────────────────────────────────────────────── */
echo "── ⑤ الشاهدُ المُشغَّل\n";
$liveNow = (int) $one('SELECT COUNT(*) FROM pay_models WHERE is_active = 1');
echo "     نماذجٌ حيّةٌ الآن: {$liveNow} " . ($liveNow === 15 ? "✔ (الكتالوجُ المحكومُ وحدَه)\n" : "✘\n");
if ($liveNow !== 15) { $fail[] = "الحيُّ {$liveNow} لا 15"; }

$govNow = (int) $one("SELECT COUNT(*) FROM pay_models WHERE code IN ({$in}) AND is_active = 1");
echo "     والمحكومُ الخمسةَ عشرَ كلُّها حيّة: {$govNow}/15 " . ($govNow === 15 ? "✔\n" : "✘\n");
if ($govNow !== 15) { $fail[] = "المحكومُ {$govNow} لا 15"; }

$orphan = (int) $one('SELECT COUNT(*) FROM employee_contracts c
                       WHERE NOT EXISTS (SELECT 1 FROM pay_models m
                                          WHERE m.id = c.pay_model_id AND m.is_active = 1)');
echo "     عقودٌ تشير إلى نموذجٍ غيرِ حيّ: {$orphan} " . ($orphan === 0 ? "✔\n" : "✘\n");
if ($orphan !== 0) { $fail[] = "{$orphan} عقدًا بنموذجٍ معطَّل"; }

$kept = (int) $one("SELECT COUNT(*) FROM pay_models WHERE code LIKE 'quarantined_%'");
echo "     محجوزٌ شاهدًا (لم يُحذف): {$kept}\n";

echo "\n" . (empty($fail)
    ? "✅ كتالوجُ نماذجِ الأجرِ صار الخمسةَ عشرَ المحكومةَ وحدَها — والدخيلُ محجوزٌ معلَنًا لا محذوفًا.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
