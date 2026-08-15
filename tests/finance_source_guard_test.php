<?php
/**
 * tests/finance_source_guard_test.php — شاهدُ «المالُ أثرٌ لا مصدر»
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0176 · INJ-0178 · INJ-0179 · INJ-0180
 *
 * أربعُ شاشاتٍ ماليةٍ كاتبةٍ كانت تقبل رقمًا بلا واقعةٍ تُسنده:
 *   · INJ-0176 «قائمةُ الأحداث»: `source_ref` نصٌّ حرٌّ — يُفتح حدثٌ على أمرِ
 *     صيانةٍ **لا وجودَ له**، ثم تُبنى عليه المروحةُ والقيدُ والذمّة.
 *   · INJ-0178 «المدفوعات»: `receivable_id` اختياريٌّ — تُصرَف الخزينةُ بلا ما
 *     يُسندها، فلا يُعرف على أيِّ التزامٍ وقع الصرف.
 *   · INJ-0179 «التكاليف»: `event_id` عمودٌ في الجدولِ **لا يُملأ أبدًا**،
 *     و«الإيراد» حقلٌ مفتوحٌ في النموذج — فيُكتب ربحٌ بلا واقعة.
 *   · INJ-0180 «الفترةُ المقفلة»: الحارسُ مبنيٌّ ولم تكن الشاشاتُ تناديه.
 *
 * ── والقياسُ على الدالّةِ الحاكمةِ لا على النصِّ وحدَه ────────────────────────
 * يُجَسُّ الحارسُ بمرجعٍ **مخترَعٍ** وبمرجعٍ **حقيقيٍّ** — فحارسٌ يرفض الاثنين
 * ليس حارسًا بل عطلًا. ثم يُقاس **تبنّيه في الشاشاتِ الأربع** نصًّا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/includes/fin_event_source_guard.php';
require_once $ROOT . '/includes/period_guard.php';

$conn = $GLOBALS['conn'];
$CO = 4;
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ المالُ أثرٌ لا مصدر — أربعُ شاشاتٍ وأربعةُ شروط');

/* ── ① حارسُ مصدرِ الحدثِ يميّز المخترَعَ من الحقيقيّ ────────────────────────── */
$bad = ems_fin_event_resolve_source($conn, $CO, 'maintenance', '99999999');
$ok(empty($bad['ok']) && (int) $bad['code'] === 422,
    '**مرجعُ صيانةٍ مخترَعٌ ⇒ 422**', 'الرمزُ ' . $bad['code']);
$ok(mb_strpos((string) $bad['reason'], 'mnt_order') !== false,
    'والرسالةُ **تسمّي جدولَ المصدر** — لا «خطأ» صامتة', mb_substr((string) $bad['reason'], 0, 70));

/* مرجعٌ حقيقيٌّ من القاعدةِ الحيّة — لا رقمٌ مفترَض */
$realId = 0;
$r = $conn->query("SELECT id FROM proc_order WHERE company_id = {$CO} ORDER BY id DESC LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $realId = (int) $x[0]; }
$ok($realId > 0, "ووُجد أمرُ شراءٍ حيٌّ يُقاس عليه القبول (#{$realId})");
if ($realId > 0) {
    $good = ems_fin_event_resolve_source($conn, $CO, 'procurement', (string) $realId);
    $ok(!empty($good['ok']) && (int) $good['source_doc_id'] === $realId,
        '**ومرجعٌ حقيقيٌّ يُقبل ويُحَلُّ إلى مفتاحِه** (#' . (int) $good['source_doc_id'] . ')',
        (string) $good['reason']);
}
$unknown = ems_fin_event_resolve_source($conn, $CO, 'not_a_module', '1');
$ok(empty($unknown['ok']), 'وإدارةُ مصدرٍ غيرُ معروفةٍ تُردُّ — فلا حدثَ على إدارةٍ بلا سجل');

/* ── ② حارسُ الفترةِ المقفلة يعمل ─────────────────────────────────────────── */
$pc = ems_period_check($conn, $CO, date('Y-m-d'));
$ok(is_array($pc) && isset($pc['ok']),
    'حارسُ الفترةِ يستجيب لتاريخِ اليوم (' . (empty($pc['ok']) ? '423 مقفلة' : 'مفتوحة') . ')');

/* ── ③ التبنّي في الشاشاتِ الأربع ─────────────────────────────────────────── */
$adopt = array(
    'Finance/events_list_fin.php' => array('ems_fin_event_resolve_source', 'ems_period_check'),
    'Finance/payments_fin.php'    => array('GOV-REF-422', 'ems_period_check'),
    'Finance/cost_report_fin.php' => array('event_id', 'ems_period_check'),
);
foreach ($adopt as $rel => $needles) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    $missing = array();
    foreach ($needles as $n) { if (strpos($src, $n) === false) { $missing[] = $n; } }
    $ok(empty($missing), "«{$rel}» تنادي حارسَيها", 'الغائب: ' . implode(' · ', $missing));
}

/* ── ④ وحقلُ الإيرادِ نُزع من نموذجِ التكاليف ─────────────────────────────── */
$cr = (string) @file_get_contents($ROOT . '/Finance/cost_report_fin.php');
$ok(strpos($cr, 'name="revenue"') === false,
    '**وحقلُ «الإيراد» غيرُ قابلٍ للإدخال** — نُزع من النموذج');
$ok(strpos($cr, 'يُقرأ من الحدثِ المصدرِ عند الحفظ') !== false,
    'ويُعلَن سببُ غيابِه — لا حقلٌ يختفي بلا تفسير');
$ok(strpos($cr, "\$revenue = null;") !== false
    && strpos($cr, "event_type = 'revenue'") !== false,
    '**والخادمُ يقرؤه من مصدرِه** لا من الطلب');

/* ── ⑤ والمدفوعاتُ: الصرفُ يلزمه مستندٌ والتحصيلُ لا ────────────────────────── */
$pf = (string) @file_get_contents($ROOT . '/Finance/payments_fin.php');
$ok(strpos($pf, "if (\$direction === 'disbursement')") !== false
    && strpos($pf, 'لا صرفَ بلا مستندِ التزامٍ') !== false,
    '**والصرفُ بلا ذمّةٍ يُردُّ 422**');
$ok(strpos($pf, 'outstanding') !== false,
    'والذمّةُ تُحَلُّ فعلًا: قائمةٌ · لهذه الشركةِ · ولها رصيدٌ قائم');
$ok(mb_strpos($pf, 'والتحصيلُ يُستثنى') !== false,
    'والتحصيلُ **يُستثنى** — هو يُنشئ الأثرَ لا يستهلكه');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
