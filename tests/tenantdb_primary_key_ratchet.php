<?php
/**
 * tests/tenantdb_primary_key_ratchet.php
 *   سقّاطةُ **افتراضِ اسمِ المفتاح** في بوّابةِ العزل — «المفتاحُ يُقرأ لا يُثبَّت»
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **القاعدةُ المُنفَذة**: `TenantDb` **لا تُثبِّت اسمَ المفتاحِ الأساسيِّ**
 *   (`id`) في استعلامٍ عامّ — بل تقرؤه من المخطَّط بـ`primaryKeyOf()`.
 *
 * ⛔ **العطبُ الذي تمنعه — مقيسٌ بالتشغيل لا بالرأي**: **286 جدولًا من 1,249
 *   (23%)** بلا عمودِ `id` — مفاتيحُها `profile_id` · `grant_id` · `item_id` ·
 *   `job_id` · `effect_id` … وكانت البوّابةُ تُثبِّت `id` في خمسِ دوالَّ عامّة،
 *   فيفشل بناءُ الاستعلامِ على **47 جدولًا** من الـ132 المسجَّلةِ بلا `id`
 *   بخطأ `Unknown column 'id'` — **فشلُ بناءٍ لا منعٌ مقصود**.
 *   وفي `logs/security.log` **956 حالةَ فشلٍ** بالسبب نفسِه على جداولِ
 *   الصلاحيّاتِ ذاتِها (`gov_role_profiles` · `gov_authority_grants`).
 *
 * ◆ **والعلاجُ كان مكتوبًا ولم يُعمَّم**: `parentKeyOf()` تقرأ المفتاحَ من
 *   المخطَّط منذ REPAIR01 W13 — وطُبِّقت في **موضعٍ واحدٍ من خمسةَ عشر**.
 *   ⇐ وهذا **ثالثُ ظهورٍ للنمطِ نفسِه** في هذه الشجرة: قاعدةٌ صحيحةٌ تُطبَّق
 *     في مكانٍ وتُنسى في الباقي. فالسقّاطةُ تُبدِّل التذكُّرَ بالعدّ.
 *
 * ⛔ **والانخفاضُ يُرسِّب أيضًا**: من أزال تثبيتًا يُنقِص خطَّ الأساسِ هنا —
 *   فسقّاطةٌ لا تُشدُّ تصير سقفًا يُنسى.
 * ⛔ **وصفرُ مقيسٍ يُرسِّب**: ماسحٌ لا يجد الملفَّ أداةٌ مكسورة.
 *
 * التشغيل: php tests/tenantdb_primary_key_ratchet.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$FILE = $ROOT . '/app/Core/TenantDb.php';

/* ── خطُّ الأساسِ المقيس (2026-09-08) ─────────────────────────────────────
     موضعٌ واحدٌ باقٍ بحقٍّ: القيمةُ الاحتياطيّةُ داخلَ قارئِ المفتاحِ نفسِه
     ($key = 'id' حين يعجز المخطَّط). ولا يجوز أن يزيد. */
$BASELINE_HARD = 1;
$MIN_PK_CALLS  = 5;

$okN = 0; $badN = 0;
function ok($c, $m, &$okN, &$badN, $d = '') {
    if ($c) { $okN++; echo "  ✔ {$m}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $badN++; echo "  ✘ FAIL: {$m}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "\n══ سقّاطةُ المفتاحِ الأساسيّ — «يُقرأ لا يُثبَّت» ══\n";

$src = (string) @file_get_contents($FILE);
ok($src !== '' && strlen($src) > 10000, 'ملفُّ البوّابةِ قُرئ — وصفرُ مقروءٍ عطبُ أداة',
   $okN, $badN, number_format(strlen($src)) . ' بايت');

/* التعليقاتُ لا تُعَدّ — الماسحُ يقرأ التعليقَ كما يقرأ الشيفرةَ إن لم يُنزَع */
$code = preg_replace('~/\*.*?\*/~s', '', $src);
$code = preg_replace('~^\s*//.*$~m', '', $code);

$hard = preg_match_all('~`id`|\'id\'~', $code);
$pk   = preg_match_all('~primaryKeyOf\s*\(~', $code) + preg_match_all('~parentKeyOf\s*\(~', $code);

ok($pk >= $MIN_PK_CALLS, '★★ **المفتاحُ يُقرأ من المخطَّطِ في المسالكِ العامّة**',
   $okN, $badN, "نداءات={$pk} · الحدُّ الأدنى={$MIN_PK_CALLS}");

ok($hard <= $BASELINE_HARD, '★★★ **لا تثبيتَ جديدٌ لاسمِ المفتاح**',
   $okN, $badN, "مقيس={$hard} · الأساس={$BASELINE_HARD}");

ok($hard >= $BASELINE_HARD, 'ولم ينخفضِ العددُ بلا شدِّ السقّاطة — أنقِصِ $BASELINE_HARD',
   $okN, $badN, "مقيس={$hard} · الأساس={$BASELINE_HARD}");

/* الدوالُّ العامّةُ التي كانت مصابةً — لا يعود فيها `id` مثبَّتًا */
$guarded = array('deleteRow', 'deleteChild', 'replaceChildren', 'softDelete');
$relapse = array();
foreach ($guarded as $fn) {
    if (!preg_match('~function\s+' . $fn . '\s*\(.*?\n    \}~s', $code, $m)) { continue; }
    if (preg_match('~WHERE `id`~', $m[0])) { $relapse[] = $fn; }
}
ok(empty($relapse), '★★ ولا دالّةً من الأربعِ عادت تُثبِّت `id` في شرطِها',
   $okN, $badN, $relapse ? implode(' · ', $relapse) : 'أربعٌ نظيفة');

echo "───────────────────────────────────────────────────────────────\n";
echo ($badN === 0 ? "✔" : "✘") . " النتيجة: نجح {$okN} · رسب {$badN}\n";
echo "◆ والسقّاطةُ لا تمنع عمودًا اسمُه `id` — تمنع **افتراضَه**.\n";
exit($badN === 0 ? 0 : 1);
