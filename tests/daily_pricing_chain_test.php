<?php
/**
 * tests/daily_pricing_chain_test.php — سلسلةُ «سعرِ اليوم» من طرفٍ إلى طرف
 * ═══════════════════════════════════════════════════════════════════════════
 * **قرارُ المالك 2026-08-12** جوابًا على «ما مصدرُ مؤشراتِ الأسعار؟»:
 *   «مصدرُها التحديثُ الوقتيُّ للأسعارِ **من الإدارةِ المالية** لكلِّ عمليةٍ
 *    بشكلِ تسعيرٍ **لكلِّ معاملةٍ**، مع إمكانيةِ تحديدِ السعرِ **لليومِ بشكلٍ يوميّ**.»
 *
 * ⇒ فلا مؤشرَ خارجيًّا منشورًا (وهو ما كان المحرِّكُ يفترضه)، والماليةُ هي المصدر.
 *   وهذا الفاحصُ يُثبِت أنَّ القرارَ **صار حسابًا** لا نصًّا في وثيقة:
 *     ① دوريةٌ يوميةٌ يقبلها المدى والخدمةُ معًا.
 *     ② سعرٌ تُدخِله الماليةُ ليومٍ ⇒ مراجعةٌ بمفتاحِ ذلك اليومِ **وسريانٍ فيه**.
 *     ③ ثلاثةُ أيامٍ ⇒ ثلاثُ مراجعاتٍ لا تتصادم (المفتاحُ الفريدُ يحكم).
 *     ④ **وسعرُ المعاملةِ يتبعُ يومَها**: واقعةُ الأمسِ بسعرِ الأمسِ، وواقعةُ
 *        اليومِ بسعرِ اليومِ — «لا رجعية» محفوظةٌ بالحسابِ لا بتأخيرِ السريان.
 *     ⑤ ولا سعرَ بلا مُسعِّرٍ مُعرَّف.
 *
 * ◆ الوسمُ عائليٌّ (`DPC`) والكنسُ **بعائلةِ الوسمِ** لا بالوسمِ وحدَه.
 * ◆ ولا يُحكَم على فرعٍ لم يُشغَّل: ما تعذّر يُعلَن `○` ولا يُحسَب نجاحًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/app/Services/Contract/PriceAdjustmentService.php';

use App\Services\Contract\PriceAdjustmentService as PAS;

$conn = $GLOBALS['conn'];
$CO = 4;
$ACTOR = 1;              // مُسعِّرٌ من الإدارةِ المالية (مُعرَّفٌ موجب)
$APPROVER = 2;           // معتمِدٌ آخرُ — «من أنشأ لا يعتمد»
$_SESSION['user'] = array('id' => $ACTOR, 'role' => '1', 'company_id' => $CO, 'name' => 'daily pricing test');
$gate = ems_tenant_db();

$FAMILY = 'DPC';
$MARK = $FAMILY . getmypid();
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $extra = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($extra !== '' ? "  ⟵ {$extra}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };

/** كنسٌ بعائلةِ الوسم — ثغرةُ جولةٍ ماتت تُعمي التالية */
$sweep = function () use ($conn, $CO, $FAMILY) {
    $n = 0;
    $conn->query("DELETE FROM contract_price_revisions
                   WHERE company_id = {$CO} AND period_key LIKE '{$FAMILY}%'");
    $n += $conn->affected_rows;
    /* المراجعاتُ اليوميةُ مفتاحُها تاريخٌ لا وسمٌ — فتُكنَس بشرطِها من بندِها */
    $conn->query("DELETE r FROM contract_price_revisions r
                   JOIN contract_price_terms t ON t.id = r.term_id
                  WHERE r.company_id = {$CO} AND t.index_code LIKE '{$FAMILY}%'");
    $n += $conn->affected_rows;
    $conn->query("DELETE FROM contract_price_index_readings WHERE index_code LIKE '{$FAMILY}%'");
    $n += $conn->affected_rows;
    $conn->query("DELETE FROM contract_price_terms WHERE index_code LIKE '{$FAMILY}%'");
    $n += $conn->affected_rows;
    return $n;
};
$say('══ سلسلةُ «سعرِ اليوم» — قرارُ المالك 2026-08-12  (كُنس ' . $sweep() . ' من عائلةِ ' . $FAMILY . ')');

/* ══ ① المدى والخدمةُ يقبلان اليوميَّ ═════════════════════════════════════════ */
$ok(in_array('daily', PAS::PERIODICITIES, true), "الخدمةُ تعرف الدوريةَ 'daily'");
$col = $conn->query("SHOW COLUMNS FROM contract_price_terms LIKE 'periodicity'");
$col = $col ? (string) $col->fetch_assoc()['Type'] : '';
$ok(strpos($col, "'daily'") !== false, "والقاعدةُ تقبلها في المدى");
$ok(PAS::periodKey('daily', '2026-08-12') === '2026-08-12', 'مفتاحُ الدورةِ اليوميةِ هو اليومُ نفسُه');
$ok(PAS::nextPeriodStart('daily', '2026-08-12') === '2026-08-12',
    '**وسريانُه يومُه نفسُه** — لا غدًا (نصُّ قرارِ المالك)',
    'لو سرى غدًا لكان «سعرُ اليوم» ساريًا غدًا وهو نقضُ القرار');
/* والدوريّاتُ الخشنةُ تبقى على قاعدتِها — وإلا كان التغييرُ كسرًا لا إضافةً */
$ok(PAS::nextPeriodStart('monthly', '2026-08-12') === '2026-09-01',
    'والشهريُّ ما زال يسري بعدَ الفترةِ (2026-09-01) — الإضافةُ لم تكسر القائم');

/* ══ ② بندُ تسعيرٍ يوميٍّ على عقدٍ وبندٍ حقيقيَّين ══════════════════════════════ */
/* ◆ **بندٌ حرٌّ لا أوّلُ بندٍ.** أوّلُ صياغةٍ أخذت `ORDER BY ce.id LIMIT 1` فوقعت
     على بندٍ صارت له شرطُ تسعيرٍ من بذرةِ uat0002، فردَّ `saveTerm` بـ409
     («للعقد شرطٌ بالمحفِّز والنطاق والسريان نفسِها») — أي أنَّ الفاحصَ كان يرسب
     **بوجودِ بياناتٍ سليمةٍ** لا بعيب. فيُختار بندٌ **لا شرطَ عليه**، فيصمد
     الفاحصُ أمام أيِّ بذرةٍ لاحقةٍ ولا يحتلُّ ما احتلَّه غيرُه. */
$item = $conn->query("SELECT ce.id, ce.contract_id, ce.equip_price
                        FROM contractequipments ce
                        JOIN contracts c ON c.id = ce.contract_id
                       WHERE c.company_id = {$CO} AND ce.equip_price > 0
                         AND NOT EXISTS (SELECT 1 FROM contract_price_terms t
                                          WHERE t.contract_item_id = ce.id
                                             OR (t.contract_id = ce.contract_id AND t.contract_item_id = 0))
                       ORDER BY ce.id LIMIT 1");
$item = $item ? $item->fetch_assoc() : null;
$ok($item !== null, 'وُجد بندُ عقدٍ حقيقيٌّ **حرٌّ** بسعرٍ أساسيٍّ (لا رقمٌ مخترَع)',
    'كلُّ البنودِ مشغولةٌ بشروطِ تسعيرٍ — أضِف بندًا أو وسّع النطاق');
if ($item === null) { $say(''); $say("PASS={$PASS} · FAIL={$FAIL}"); exit(1); }

$CID = (int) $item['contract_id'];
$IID = (int) $item['id'];
$BASE = (float) $item['equip_price'];
$CODE = $FAMILY . 'FUEL';
$say('   العقدُ ' . $CID . ' · البندُ ' . $IID . ' · السعرُ الأساسيُّ ' . $BASE);

$t = PAS::saveTerm($conn, $gate, $CO, $CID, array(
    'contract_item_id' => $IID, 'trigger_kind' => 'fuel', 'index_code' => $CODE,
    'base_index' => 100.0, 'base_date' => '2026-08-01',
    'threshold_percent' => 0.0, 'pass_through_percent' => 100.0,
    'periodicity' => 'daily', 'valid_from' => '2026-08-01',
), $ACTOR);
$ok(!empty($t['ok']), 'أُنشئ بندُ تسعيرٍ **يوميٍّ** عبر الخدمة',
    'كودٌ ' . (isset($t['code']) ? $t['code'] : '—') . ' · ' . (isset($t['reason']) ? $t['reason'] : ''));
if (empty($t['ok'])) { $sweep(); $say(''); $say("PASS={$PASS} · FAIL={$FAIL}"); exit(1); }

/* ══ ③ الماليةُ تُسعّر ثلاثةَ أيامٍ — كلُّ يومٍ بمرجعِ قرارِه ════════════════════ */
$days = array(
    array('2026-08-10', 100.0, 'سعرُ اليومِ بلا تغيير'),
    array('2026-08-11', 110.0, 'ارتفاعُ الجازولينِ 10٪ — قرارُ تسعيرٍ يوميٌّ من المالية'),
    array('2026-08-12', 121.0, 'ارتفاعٌ ثانٍ — قرارُ تسعيرٍ يوميٌّ من المالية'),
);
$recorded = 0;
foreach ($days as $i => $d) {
    $r = PAS::recordIndexReading($conn, $gate, $CO, array(
        'index_code' => $CODE, 'reading_date' => $d[0], 'value' => $d[1],
        'source_ref' => $MARK . '-FIN-' . str_replace('-', '', $d[0]),
        'note' => $d[2],
    ), $ACTOR);
    if (!empty($r['ok'])) { $recorded++; }
    else { $say('     ✘ ' . $d[0] . ': ' . (isset($r['reason']) ? $r['reason'] : '')); }
}
$ok($recorded === 3, 'سجّلت الماليةُ سعرَ ثلاثةِ أيامٍ (' . $recorded . '/3) — كلٌّ بمرجعِ قرارِه');

/* ⑤ ولا سعرَ بلا مُسعِّرٍ مُعرَّف */
$bad = PAS::recordIndexReading($conn, $gate, $CO, array(
    'index_code' => $CODE, 'reading_date' => '2026-08-09', 'value' => 99.0,
    'source_ref' => $MARK . '-NOACTOR',
), 0);
$ok(empty($bad['ok']) && (int) (isset($bad['code']) ? $bad['code'] : 0) === 403,
    'وتُردُّ قراءةٌ بلا مُسعِّرٍ مُعرَّفٍ (403)',
    'كودٌ ' . (isset($bad['code']) ? $bad['code'] : '—'));
/* والقراءةُ واقعةٌ لا تُكرَّر */
$dup = PAS::recordIndexReading($conn, $gate, $CO, array(
    'index_code' => $CODE, 'reading_date' => '2026-08-11', 'value' => 999.0,
    'source_ref' => $MARK . '-DUP',
), $ACTOR);
$ok(empty($dup['ok']) && (int) (isset($dup['code']) ? $dup['code'] : 0) === 409,
    'وسعرُ يومٍ لا يُسجَّل مرتين (409) — القراءةُ واقعةٌ لا تُكرَّر',
    'كودٌ ' . (isset($dup['code']) ? $dup['code'] : '—'));

/* ══ ④ المراجعةُ لكلِّ يومٍ — بمفتاحِ يومِه وسريانِ يومِه ═══════════════════════ */
$made = array();
foreach (array('2026-08-11', '2026-08-12') as $d) {
    $r = PAS::applyDue($conn, $gate, $CO, $CID, $d, $ACTOR, 'user');
    $made[$d] = isset($r['created']) ? (int) $r['created'] : 0;
}
$ok($made['2026-08-11'] >= 1 && $made['2026-08-12'] >= 1,
    'وُلّدت مراجعةٌ لكلِّ يومٍ (11 ⇒ ' . $made['2026-08-11'] . ' · 12 ⇒ ' . $made['2026-08-12'] . ')',
    'لو صفرٌ فالمحرِّكُ لم يرَ قراءةَ اليوم');

$rows = array();
$q = $conn->query("SELECT r.id, r.period_key, r.effective_from, r.new_price, r.outcome, r.created_origin
                     FROM contract_price_revisions r
                     JOIN contract_price_terms t ON t.id = r.term_id
                    WHERE r.company_id = {$CO} AND t.index_code = '" . $conn->real_escape_string($CODE) . "'
                    ORDER BY r.period_key");
while ($q && ($x = $q->fetch_assoc())) { $rows[] = $x; }
$ok(count($rows) === 2, 'مراجعتان لا واحدةٌ ولا ثلاثٌ (' . count($rows) . ') — المفتاحُ الفريدُ يمنع التصادم');
foreach ($rows as $x) {
    $ok((string) $x['period_key'] === (string) $x['effective_from'],
        'المراجعةُ ' . $x['period_key'] . ': مفتاحُها = سريانُها ' . $x['effective_from']
        . ' · سعرٌ ' . $x['new_price'] . ' · ' . $x['outcome'],
        'اليوميُّ يسري يومَه — فاختلافُهما يعني عودةَ «يسري غدًا»');
}

/* اعتمادٌ بمعتمِدٍ آخرَ ثم قياسُ سعرِ المعاملةِ بيومِها */
$approved = 0;
foreach ($rows as $x) {
    $a = PAS::approve($conn, $gate, $CO, (int) $x['id'], $APPROVER);
    if (!empty($a['ok'])) { $approved++; }
    else { $say('     ○ اعتمادُ ' . $x['period_key'] . ' لم يمرَّ: ' . (isset($a['reason']) ? $a['reason'] : '')); }
}
$ok($approved === count($rows), 'اعتُمدت المراجعتان بمعتمِدٍ غيرِ المُنشئ (' . $approved . '/' . count($rows) . ')');

if ($approved === count($rows)) {
    $p10 = PAS::effectivePrice($conn, $CO, $CID, $IID, '2026-08-10', $BASE);
    $p11 = PAS::effectivePrice($conn, $CO, $CID, $IID, '2026-08-11', $BASE);
    $p12 = PAS::effectivePrice($conn, $CO, $CID, $IID, '2026-08-12', $BASE);
    $say('   سعرُ المعاملةِ بيومِها: 10 ⇒ ' . $p10 . ' · 11 ⇒ ' . $p11 . ' · 12 ⇒ ' . $p12
         . '  (الأساسُ ' . $BASE . ')');
    $ok((float) $p10 === (float) $BASE,
        'واقعةُ 2026-08-10 (قبلَ أوّلِ سريانٍ) بالسعرِ **الأساسيّ** — لا رجعية',
        'لو تغيّرت لكانت الرجعيةُ قد وقعت');
    $ok((float) $p11 > (float) $BASE, 'وواقعةُ 08-11 بسعرِ **يومِها** لا بالأساس');
    $ok((float) $p12 > (float) $p11,
        'وواقعةُ 08-12 بسعرٍ أعلى من 08-11 — **تسعيرٌ لكلِّ معاملةٍ بيومِها**',
        'هذا هو نصُّ قرارِ المالك؛ لو تساويا فالتسعيرُ اليوميُّ لا يصل الواقعة');
    /* والسعرُ الأساسيُّ لم يُمَسّ — الطبقةُ فوقه لا بدلًا منه */
    $base2 = $conn->query("SELECT equip_price FROM contractequipments WHERE id = {$IID}");
    $base2 = $base2 ? (float) $base2->fetch_assoc()['equip_price'] : -1.0;
    $ok($base2 === (float) $BASE, 'والسعرُ الأساسيُّ في العقدِ لم يُمَسّ (' . $base2 . ')');
} else {
    $say('  ○ لم تُعتمد المراجعاتُ — فروعُ «سعرِ المعاملةِ بيومِها» لا يُحكَم عليها');
}

/* ── كنسٌ ختاميٌّ بعائلةِ الوسم ─────────────────────────────────────────────── */
$left = $sweep();
$say('   كُنس ختامًا: ' . $left . ' صفًّا');
$chk = $conn->query("SELECT COUNT(*) n FROM contract_price_index_readings WHERE index_code LIKE '{$FAMILY}%'");
$chk = $chk ? (int) $chk->fetch_assoc()['n'] : -1;
$ok($chk === 0, 'صفرُ ثغرةٍ من عائلةِ الوسمِ بعد الجولة (' . $chk . ')');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
