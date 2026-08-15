<?php
/**
 * tests/dup_write_paths_test.php — مسارُ كتابةٍ واحدٌ لكلِّ جدولٍ متنازَع
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0031 · INJ-0114 · INJ-0168 · INJ-0250 · INJ-0585 · INJ-0590
 *
 * الفئةُ ②: ملفّانِ أو أكثرُ يكتبان في الجدولِ نفسِه. والقرارُ في كلِّ حالةٍ
 * اتُّخذ **بالدليلِ لا بالرأي**: مَن يكتب فعلًا، وأيُّهما يحمل الحراسةَ الأتمَّ.
 *
 * ── القراراتُ الستّ ────────────────────────────────────────────────────────
 *  ① `rfq_awards` — **`RFQService::award` هو الكاتب** (معامليٌّ، يتحقّق من
 *     الكمياتِ المتاحةِ ويحدّث عدّاداتِ البنودِ وينشر حقيقةً محايدة). و
 *     `RfqAwardService::award` صار **يُفوّض إليه** فيبقى مدخلُ شاشةِ المقارنةِ
 *     ويبقى الكاتبُ واحدًا. وأُضيف إليه **سقفُ التفويض** (409 فوقَه).
 *  ② `achievement_snapshots` — **`AchievementService` وحدَه يكتب**؛
 *     و`EvaluationService` **يقرأ فقط**. فالتهمةُ بمحرّكين كانت خطأً في السجل.
 *  ③ `equipments` — `Equipments/equipments.php` شاشةٌ و`equipment_child_save.php`
 *     **نقطةُ حفظِها** لا شاشةٌ منافسة؛ و`Fleet/readiness_board.php` **لا يكتب
 *     شيئًا**. فلا تنازعَ في الحقيقة.
 *  ④ تصنيفُ الحمايات — `Settings/guard_classification.php` **تكتب**، و
 *     `Governance/sensitive_fields.php` تكتب في المخزنِ البينيِّ لا في جدولِ
 *     التصنيف. فالأولى مالكةٌ، والناقصُ كان **الرابط** — فأُضيف.
 *  ⑤ «مساحةُ العمل» — `main/my_workspace.php` موصولةٌ بـ٣٢ صفَّ تنقّل،
 *     و`Portal/workspace.php` بصفرِ صفّ. فالثانيةُ **تُحوَّل** إلى الأولى.
 *  ⑥ `iaf_findings` — خمسُ شاشاتٍ صارت **شاشةً واحدةً بخمسةِ مناظر**؛ والأربعُ
 *     تُحوَّل إليها بـ`?view=`.
 *
 * ◆ **ولا حذفَ لشاشة**: كلُّ مُلغاةٍ تُحوَّل — فالروابطُ المحفوظةُ والمراجعُ في
 *   التقاريرِ تبقى عاملة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ مسارُ كتابةٍ واحدٌ لكلِّ جدولٍ متنازَع');

$writes = function ($rel, $table) use ($ROOT) {
    $s = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($s === '') { return -1; }
    $t = preg_quote($table, '~');
    return preg_match_all('~INSERT\s+(?:IGNORE\s+)?INTO\s+`?' . $t . '\b'
        . '|UPDATE\s+`?' . $t . '`?\s+SET'
        . '|->insert\(\s*[\'"]' . $t . '[\'"]'
        . '|->update\(\s*[\'"]' . $t . '[\'"]~i', $s);
};

/* ── ① rfq_awards: كاتبٌ واحدٌ والآخرُ يُفوّض ─────────────────────────────── */
$svcA = $writes('app/Services/Procurement/RFQService.php', 'rfq_awards');
$svcB = (string) @file_get_contents($ROOT . '/app/Services/Procurement/RfqAwardService.php');
$ok($svcA >= 1, "‏`RFQService::award` يكتب `rfq_awards` ({$svcA} موضعًا) — وهو الكاتبُ المُقرَّر");
$ok(strpos($svcB, 'RFQService::award(') !== false,
    '**و`RfqAwardService` يُفوّض إليه** بدل أن يكتب مسارًا موازيًا');
$ok(strpos($svcB, 'التفويضُ لا الكتابةُ الموازية') !== false,
    'والسببُ موثَّقٌ في موضعِه — فلا يُعاد المسارُ الموازي سهوًا');
$rfqSrc = (string) @file_get_contents($ROOT . '/app/Services/Procurement/RFQService.php');
$ok(strpos($rfqSrc, 'RFQ-CAP-409') !== false && strpos($rfqSrc, 'AuthorityGuard::sign') !== false,
    '**وسقفُ التفويضِ يحرس الترسيةَ** — فوقَه 409 قبل أيِّ كتابة');
/* والسببُ مفروضٌ عند المدخلين */
$entryA = (string) @file_get_contents($ROOT . '/Suppliers/rfq_requests.php');
$ok(mb_strpos($svcB, 'سببُ الترسية إلزامي') !== false
    && mb_strpos($entryA, 'award_reason') !== false,
    'والسببُ إلزاميٌّ عند المدخلين — فلا ترسيةَ صامتة');

/* ── ② achievement_snapshots: كاتبٌ واحدٌ وقارئٌ ──────────────────────────── */
$ach = $writes('app/Services/Portal/AchievementService.php', 'achievement_snapshots');
$evl = $writes('app/Services/Portal/EvaluationService.php', 'achievement_snapshots');
$ok($ach >= 1, "‏`AchievementService` يكتب اللقطات ({$ach} موضعًا)");
$ok($evl === 0, "**و`EvaluationService` لا يكتبها إطلاقًا** ({$evl}) — قارئٌ لا محرّكٌ ثانٍ");

/* ── ③ equipments: شاشةٌ ونقطةُ حفظٍ ولوحةٌ قارئة ─────────────────────────── */
$eq1 = $writes('Equipments/equipments.php', 'equipments');
$eq2 = $writes('Equipments/equipment_child_save.php', 'equipments');
$eq3 = $writes('Fleet/readiness_board.php', 'equipments');
$ok($eq3 === 0, "**ولوحةُ الجاهزيةِ لا تكتب `equipments`** ({$eq3}) — قارئةٌ لا ثالثة");
$saveSrc = (string) @file_get_contents($ROOT . '/Equipments/equipment_child_save.php');
$ok(strpos($saveSrc, "check_page_permissions(\$conn, 'equipments_fleet')") !== false,
    'ونقطةُ الحفظِ ترث صلاحيةَ شاشتِها الأمِّ — فهي مسلكُها لا شاشةٌ منافسة');

/* ── ④ تصنيفُ الحمايات: مالكةٌ برابطٍ في القائمة ──────────────────────────── */
$navGuard = 0;
$r = $conn->query("SELECT COUNT(*) FROM nav_items WHERE route LIKE '%guard_classification%'");
if ($r) { $navGuard = (int) $r->fetch_row()[0]; }
$ok($navGuard > 0, "و«تصنيف قواعد المنع» لها {$navGuard} رابطًا في القائمة — بعد صفر");
$gc = $writes('Settings/guard_classification.php', 'guard_classification');
$sf = $writes('Governance/sensitive_fields.php', 'guard_classification');
$ok($sf === 0, "**وشاشةُ السياساتِ لا تكتب جدولَ التصنيف** ({$sf}) — فلا تنازع");

/* ── ⑤ «مساحةُ العمل»: واحدةٌ والأخرى تُحوَّل ────────────────────────────── */
$wsSrc = (string) @file_get_contents($ROOT . '/Portal/workspace.php');
$ok(strpos($wsSrc, "header('Location: ../main/my_workspace.php')") !== false,
    '**و`Portal/workspace.php` تُحوَّل إلى `main/my_workspace.php`**');
$ok(strpos($wsSrc, 'route_redirect') !== false,
    'وكلُّ فتحةٍ تُسجَّل بمُحيلِها — ليُعرف من ما زال يستعمل القديم');
$wsNav = 0;
$r = $conn->query("SELECT COUNT(*) FROM nav_items WHERE route LIKE '%Portal/workspace.php%'");
if ($r) { $wsNav = (int) $r->fetch_row()[0]; }
$ok($wsNav === 0, "ولا صفَّ تنقّلٍ يشير إلى المحوَّلة ({$wsNav})");

/* ── ⑥ iaf_findings: شاشةٌ واحدةٌ بمناظر ─────────────────────────────────── */
$kit = (string) @file_get_contents($ROOT . '/includes/u13_screen_kit.php');
$ok(strpos($kit, 'function u13_siblings') !== false
    && strpos($kit, "?view=") !== false,
    '**وعُدَّةُ الشاشاتِ تحمل منتقيَ المنظر** — الأخواتُ مناظرُ في المضيفة');
$ok(strpos($kit, "header('Location: ' . basename(\$u13Sibs[\$u13Host]['file'])") !== false,
    'وفتحُ أختٍ مباشرةً يُحوَّل إلى المضيفةِ بمنظرِها');
$audit = glob($ROOT . '/Audit/iaf_*.php');
$overFindings = 0;
foreach ($audit as $f) {
    $s = (string) @file_get_contents($f);
    if (preg_match("~'table'\s*=>\s*'iaf_findings'~", $s)) { $overFindings++; }
}
$ok($overFindings >= 2,
    "وخمسُ شاشاتٍ تصريحيةٌ فوق `iaf_findings` ({$overFindings}) — تُصيَّر من ملفٍّ واحدٍ بمناظر");

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
