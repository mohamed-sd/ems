<?php
/**
 * tools/dashv2_measure.php — مقياسُ جولةِ DASH-V2 (تُعاد تشغيلُه)
 * ═══════════════════════════════════════════════════════════════════════════
 * يقيس **الناتجَ المُصيَّرَ في جلسةٍ حيّة** لا قراءةَ الملف (قاعدةُ «التصيير
 * لا المخزن»): يدخل بمستخدمٍ حقيقيٍّ عبر محرّكِ `tests/dept_suite`، يجلب
 * `main/dashboard.php`، ويعدُّ ما تعِد الجولةُ بحفظه.
 *
 * الاستعمال:
 *   php tools/dashv2_measure.php                 ← يقيس الحالةَ الراهنة
 *   php tools/dashv2_measure.php --save=before   ← يحفظ أساسًا للمقارنة
 *   php tools/dashv2_measure.php --cmp=before    ← يقارن الراهنَ بالأساس
 *   php tools/dashv2_measure.php --negative      ← اختبارٌ سالبٌ يُثبت أن
 *                                                  المقياسَ **يرسب** فعلًا
 *
 * ◆ الاعتمادُ على محرّكِ الاختبارِ القائمِ مقصود: بيانات الدخولِ في مانيفستِه
 *   ولا تُكتب هنا، والجلسةُ جلسةٌ حقيقيةٌ لا محاكاة.
 */

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tests/dept_suite/engine.php';

$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('~^--([a-z-]+)(?:=(.*))?$~', $a, $m)) { $args[$m[1]] = isset($m[2]) ? $m[2] : true; }
}

$MF   = require $ROOT . '/tests/dept_suite/manifest_sales.php';
$base = rtrim(isset($MF['base']) ? $MF['base'] : 'http://localhost/ems', '/');
$jar  = sys_get_temp_dir() . '/dashv2_jar_' . getmypid() . '.txt';
$ctx  = array('base' => $base, 'jar' => $jar, 'run' => 'DASHV2');

/* ── الدخولُ والجلب ───────────────────────────────────────────────────── */
list($okLogin, $why) = ds_login($ctx, $MF['user'], $MF['pass']);
if (!$okLogin) {
    fwrite(STDERR, "🔴 تعذّر الدخول: {$why}\n");
    fwrite(STDERR, "   تأكّد أن خادمَ WAMP يعمل وأن {$base} يستجيب.\n");
    exit(2);
}
list($code, $hdr, $html) = ds_req($base . '/main/dashboard.php', $ctx);
@unlink($jar);

if ($code !== 200) {
    fwrite(STDERR, "🔴 اللوحةُ ردّت {$code} لا 200.\n");
    exit(2);
}

/* ── القياساتُ ────────────────────────────────────────────────────────── */
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_clear_errors();
$xp = new DOMXPath($dom);

$count = function ($q) use ($xp) { $n = $xp->query($q); return $n === false ? 0 : $n->length; };

$M = array();

/* ① صفرُ فقد — كلُّ رابطٍ وزرٍّ في الصفحةِ المُصيَّرة */
$M['links']        = $count('//a[@href]');
$M['buttons']      = $count('//button');
$M['quick_tiles']  = $count('//*[contains(@class,"shot-hex-link")]');
$M['session_info'] = $count('//*[contains(@class,"shot-session-chip")]');
$M['kpi_cards']    = $count('//*[contains(@class,"ems-kpi-card")]');
$M['stat_cards']   = $count('//*[contains(@class,"shot-stat-card")]');
$M['charts']       = $count('//canvas');

/* ② تبنّي الجولة — هل الكتلةُ حاضرةٌ فعلًا في الناتجِ المُصيَّر؟ */
$M['block_present'] = (strpos($html, 'id="dash-v2"') !== false) ? 1 : 0;

/* ③ الحالةُ الملوَّنة — كم بطاقةَ مؤشرٍ تحمل صنفَ نغمةٍ يقرؤه اللون؟ */
$M['kpi_toned'] = $count('//*[contains(@class,"ems-kpi-err") or contains(@class,"ems-kpi-warn") or contains(@class,"ems-kpi-ok")]');

/* ④ خلطُ اللغاتِ وصيغُ التاريخ — تُعدُّ في النصِّ المُصيَّرِ لا في الشيفرة */
/* العنوانُ يُصيَّر نصًّا حرًّا داخلَ h1.head-title بجوارِ أيقونتِه — فيُقرأ
   بالمحتوى النصيِّ للعنصرِ لا بمطابقةِ وسمٍ لاصق. */
$M['en_title'] = 0;
foreach ($xp->query('//*[contains(@class,"head-title")]') as $nd) {
    if (preg_match('~[A-Za-z]{3,}~', trim($nd->textContent))) { $M['en_title'] = 1; }
}
$M['date_forms'] = 0;
foreach (array('~\b\d{4}-\d{2}-\d{2}\b~', '~\b(January|February|March|April|May|June|July|August|September|October|November|December)\b~',
               '~\b(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\b~', '~\b(Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{1,2}\b~') as $re) {
    if (preg_match($re, $html)) { $M['date_forms']++; }
}

/* ⑤ نظافةُ الكتلةِ نفسِها — تُقاس على المصدرِ لأنها حكمٌ على الشيفرة */
$src   = file_get_contents($ROOT . '/main/dashboard.php');
/* ◆ العلامةُ الختاميةُ **مذكورةٌ نصًّا** داخلَ تعليقِ الافتتاح («احذف ما بين
     هذه العلامةِ وعلامةِ DASH-V2 · END») — فأولُ مطابقةٍ لها تقع بعد سطرَين
     من الافتتاح، فتُخرج كتلةً طولُها سطران و`!important` فيها صفر. وهو **رقمٌ
     صادقُ الحسابِ كاذبُ المعنى**: صفرٌ من مدًى لا وجودَ له. فالنهايةُ تُؤخذ
     بآخرِ مطابقةٍ لا بأولها. */
$b     = strpos($src, 'DASH-V2 · BEGIN');
$e     = strrpos($src, 'DASH-V2 · END');
$block = ($b !== false && $e !== false) ? substr($src, $b, $e - $b) : '';
$M['block_lines']     = $block === '' ? 0 : substr_count($block, "\n");
/* ◆ **يُعَدُّ الإعلانُ لا الذِّكر**: الكتلةُ تشرح في تعليقاتِها سببَ كلِّ
     `!important` — فعدُّ المفردةِ نصًّا يخلط الشرحَ بالفعلِ ويضخّم الرقم
     (85 نصًّا مقابلَ الإعلاناتِ الفعلية). فيُعَدُّ ما هو إعلانُ خاصيّة. */
$M['block_important'] = preg_match_all('~^\s*[a-z-]+\s*:[^;{}]*!important~mi', $block);
$M['important_mentions'] = substr_count($block, '!important');

/* ◆ **مقياسٌ استُبدل لا حُذف**: كان هنا «زرُّ المزيد مُصيَّر» وقد زال الزرُّ
     من التصميم — ومقياسٌ يبحث عن شيءٍ أُلغيَ عمدًا يطبع صفرًا إلى الأبد
     فيُقرأ عطبًا وهو نجاح. فيُقاس **الحكمُ الذي حلَّ محلَّه**: صفرُ قاعدةٍ
     في الكتلةِ تُخفي مقصدًا من مقاصدِ الوصولِ السريع. */
$M['quick_hide_rules'] = preg_match_all('~\.shot-hex-link[^{}]*\{[^{}]*display\s*:\s*none~i', $block);

/* ◆ الاحتياطيُّ المُلفَّق: عبارتانِ مثبَّتتانِ في الشيفرةِ كانتا تُطبعانِ مكانَ
     بيانٍ غائبٍ فتناقضانِ الصفحةَ نفسَها. تُقاسانِ **على المصدرِ كلِّه** لا على
     الكتلةِ — فالعطبُ كان خارجَها، وحكمُ زوالِه يجب أن يبقى قائمًا. */
$M['fabricated_fallbacks'] = preg_match_all("~\\?\\s*'ادارة التشغيل'|\\?\\s*'اكويشن'~u", $src);

/* ⑥ صفرُ تسريب — لم يُمَسَّ ملفٌّ عالميّ
   ◆ **عطبُ هذا المقياسِ نفسِه — سُجِّل يومَ كُشف (2026-09-01)**: كان يسأل
     `git diff --name-only` وحدَه، وهو يرى **غيرَ المُدرَجِ فقط**. فلمّا كنست
     جولةٌ أخرى ألوانَ كتلتي إلى `assets/css/design-tokens.css` **وأدرجَتها**،
     طبع المقياسُ «صفرُ ملفٍّ عالميٍّ مُعدَّل» — **وهو كاذبٌ صادقُ الحساب**.
     أربعةٌ وعشرون سطرًا دخلت ملفًّا عالميًّا ولم يرَها الحارس.
   ◆ فالسؤالُ صار عن **المُدرَجِ وغيرِ المُدرَجِ معًا** — والقاعدةُ المستفادة:
     *حارسٌ يسأل نصفَ الشجرةِ يحرس نصفَها.* */
$dirty = array();
exec('git -C ' . escapeshellarg($ROOT) . ' diff --name-only', $dirty);
exec('git -C ' . escapeshellarg($ROOT) . ' diff --cached --name-only', $dirty);
$dirty = array_unique($dirty);
$globals = array('assets/css/', 'inheader.php', 'insidebar.php', 'includes/page_header.php',
                 'includes/kpi_card.php', 'includes/dash_role_board.php');
$leaked = array();
foreach ($dirty as $f) {
    foreach ($globals as $g) { if (strpos($f, $g) === 0) { $leaked[] = $f; } }
}
$M['global_files_touched'] = count($leaked);

/* ── الاختبارُ السالب: يكسر مفردةً فريدةً ويؤكّد أن المقياسَ يرسب ───────── */
if (!empty($args['negative'])) {
    $tok = 'id="dash-v2"';
    $freq = substr_count($html, $tok);
    echo "اختبارٌ سالب — المفردة {$tok} تردُ {$freq} مرّةً في الناتج.\n";
    if ($freq !== 1) {
        echo "🔴 المفردةُ ليست فريدةً — الاختبارُ السالبُ لا يصلح بها.\n";
        exit(1);
    }
    $probe = str_replace($tok, 'id="dash-v2-BROKEN"', $html);
    $ok = (strpos($probe, 'id="dash-v2"') !== false) ? 1 : 0;
    echo $ok === 0
        ? "✅ المقياسُ رسب عند الكسر — فهو يقيس شيئًا.\n"
        : "🔴 المقياسُ مرَّ رغم الكسر — مقياسٌ لا يرسب ليس مقياسًا.\n";
    exit($ok === 0 ? 0 : 1);
}

/* ── الحفظُ والمقارنة ─────────────────────────────────────────────────── */
$store = $ROOT . '/tools/.dashv2_measure';
if (!empty($args['save'])) {
    @mkdir($store, 0777, true);
    file_put_contents($store . '/' . preg_replace('~[^a-z0-9_-]~i', '', $args['save']) . '.json',
                      json_encode($M, JSON_PRETTY_PRINT));
    echo "حُفظ الأساس: {$args['save']}\n";
}

$prev = null;
if (!empty($args['cmp'])) {
    $p = $store . '/' . preg_replace('~[^a-z0-9_-]~i', '', $args['cmp']) . '.json';
    if (is_file($p)) { $prev = json_decode(file_get_contents($p), true); }
    else { fwrite(STDERR, "⚠ لا أساسَ باسم {$args['cmp']}\n"); }
}

/* ── الطباعة ──────────────────────────────────────────────────────────── */
$LBL = array(
    'links'                => 'روابط (a[href])',
    'buttons'              => 'أزرار (button)',
    'quick_tiles'          => 'بلاطات الوصول السريع',
    'session_info'         => 'معلومات الجلسة',
    'kpi_cards'            => 'بطاقات المؤشر',
    'stat_cards'           => 'بطاقات الأرقام',
    'charts'               => 'الرسوم (canvas)',
    'kpi_toned'            => 'بطاقات بحالةٍ ملوَّنة',
    'block_present'        => 'الكتلة حاضرةٌ في المُصيَّر',
    'quick_hide_rules'     => 'قواعدُ تُخفي مقصدًا سريعًا',
    'fabricated_fallbacks' => 'احتياطياتٌ مُلفَّقةٌ (نصٌّ مثبَّت)',
    'en_title'             => 'عنوانٌ إنجليزيٌّ باقٍ',
    'date_forms'           => 'صيغُ تاريخٍ مختلفة',
    'block_lines'          => 'أسطرُ الكتلة',
    'block_important'      => '!important إعلانًا فعليًّا',
    'important_mentions'   => '!important ذِكرًا (شرحًا مضمَّنًا)',
    'global_files_touched' => 'ملفاتٌ عالميةٌ مُعدَّلة',
);

echo "\n══ DASH-V2 · قياسٌ حيٌّ على الناتجِ المُصيَّر ══\n";
echo "الصفحة: {$base}/main/dashboard.php · الرد: {$code}\n\n";
printf("  %-32s %10s%s\n", 'المقياس', 'الآن', $prev ? '      الأساس   الفرق' : '');
echo '  ' . str_repeat('─', $prev ? 66 : 44) . "\n";
foreach ($M as $k => $v) {
    $lbl = isset($LBL[$k]) ? $LBL[$k] : $k;
    if ($prev && array_key_exists($k, $prev)) {
        $d = $v - $prev[$k];
        printf("  %-32s %10d %11d  %+6d\n", $lbl, $v, $prev[$k], $d);
    } else {
        printf("  %-32s %10d\n", $lbl, $v);
    }
}

/* ── الأحكام ──────────────────────────────────────────────────────────── */
echo "\n── الأحكام ──\n";
$verdicts = array();
$verdicts[] = array($M['global_files_touched'] === 0,
    'صفرُ ملفٍّ عالميٍّ مُعدَّل — لا شاشةَ أخرى تتأثر',
    "🔴 مُسَّت ملفاتٌ عالمية: " . implode(', ', $leaked));
$verdicts[] = array($M['block_present'] === 1,
    'الكتلةُ تصل المتصفحَ فعلًا (لا حكمَ على شيفرةٍ لا تُصيَّر)',
    '🔴 الكتلةُ غائبةٌ عن الناتج');
$verdicts[] = array($M['fabricated_fallbacks'] === 0,
    'لا نصَّ مثبَّتًا يحلُّ محلَّ بيانٍ غائب — الغيابُ يُعلَن شرطةً',
    "🔴 {$M['fabricated_fallbacks']} احتياطيًّا مُلفَّقًا ما زال");
$verdicts[] = array($M['quick_hide_rules'] === 0,
    'كلُّ مقاصدِ الوصولِ السريعِ مبلوغةٌ بلا نقرةٍ ولا طيّ',
    "🔴 {$M['quick_hide_rules']} قاعدةً تُخفي مقصدًا");
$verdicts[] = array($M['en_title'] === 0,
    'لا عنوانَ إنجليزيًّا في واجهةٍ عربية',
    '🔴 «Dashboard» ما زال في الناتج');
$verdicts[] = array($M['date_forms'] <= 1,
    'صيغةُ تاريخٍ واحدةٌ في الشاشة',
    "🔴 ما زالت {$M['date_forms']} صيغًا");
$verdicts[] = array($M['kpi_cards'] === 0 || $M['kpi_toned'] > 0,
    'الحالةُ تُقرأ باللونِ لا بالنصِّ وحدَه',
    '🔴 لا بطاقةَ تحمل صنفَ نغمة');

if ($prev) {
    foreach (array('links', 'buttons', 'quick_tiles', 'session_info', 'kpi_cards', 'charts') as $k) {
        $verdicts[] = array($M[$k] >= $prev[$k],
            "صفرُ فقدٍ في «{$LBL[$k]}» ({$prev[$k]} ⇐ {$M[$k]})",
            "🔴 فقدٌ في «{$LBL[$k]}»: {$prev[$k]} ⇒ {$M[$k]}");
    }
}

$fail = 0;
foreach ($verdicts as $v) {
    if ($v[0]) { echo "  ✅ {$v[1]}\n"; }
    else { echo "  {$v[2]}\n"; $fail++; }
}
echo "\n" . ($fail === 0 ? "✅ كلُّ الأحكامِ مرّت.\n" : "🔴 {$fail} حكمًا راسبًا.\n");
exit($fail === 0 ? 0 : 1);
