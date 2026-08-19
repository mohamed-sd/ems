<?php
/**
 * tests/profile_card_contract_test.php — عقدُ «بطاقةِ الكِيان» يُقاس لا يُؤتمَن
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ◆ **العطبُ الأولُ الذي يحرسه — وقد وقع فعلًا مرّتين في جولةِ التوحيد**:
 *   حين بُدِّل `<div class="card">` بـ`<section class="ems-profile__section">`
 *   بقي إغلاقُه `</div>`. وPHP لا تشتكي، والصفحةُ تُصيَّر، والفاحصُ النصيُّ
 *   يمرّ — لكنّ **المتصفحَ يُصحِّح التعشيشَ بطريقتِه**: خرجت ثلاثُ مجموعاتٍ من
 *   غلافِ `.main` وصارت أبناءَ `<body>` مباشرةً، و`<body>` شبكةٌ مرنة، فانهار
 *   عرضُ المحتوى إلى **48 بكسلًا** وامتدَّ الجسدُ إلى 3825. عطبٌ لا يُرى في
 *   المصدرِ ولا في السجل — يُرى في DOM المُصيَّر وحدَه.
 *
 *   فيُقاس هنا بموازنةِ وسومِ `div`/`section` على **مخرَجِ الشاشةِ المُصيَّر**
 *   لا على مصدرِها.
 *
 * ◆ **العطبُ الثاني**: عودةُ كتلةِ `<style>` إلى صفحةِ بطاقة. وهو الأصلُ الذي
 *   خرجت منه الجولةُ كلُّها («تعدّدُ ملفاتِ التصميمِ واتساخُ الكود») — فيُقفل
 *   بقيدٍ لا بنيّة.
 *
 * ◆ **العطبُ الثالث**: أسماءُ أصنافٍ تقع في شِباكِ مُحدِّداتٍ عامةٍ تُطابق
 *   **بالسلسلةِ لا بالمعنى** (`span[class*="badge-"]` · `[class*="chip"]` ·
 *   `[class*="tag"]` · `[class*="status"]`). صنفٌ اسمُه `…__badge--ok` يُمحى
 *   تصميمُه صامتًا — مقيسٌ حيًّا: الخلفيةُ شفافةٌ والحدُّ برتقاليّ. فيُمنع
 *   الاسمُ بالبناء.
 *
 * ◆ ما لا يقيسه — مُعلَنٌ لا مسكوتٌ عنه: لا يفتح متصفحًا فلا يشهد على اللونِ
 *   المحسوبِ ولا على وزنِ التتالي؛ ذاك قِيس يدويًّا في جولةِ التوحيد.
 *
 *   php tests/profile_card_contract_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; echo "  \xE2\x9C\x94 {$m}\n"; }
function bad($m) { global $FAIL; $FAIL++; echo "  \xE2\x9C\x98 {$m}\n"; }

/* الشاشاتُ الخاضعةُ للعقد — تُوسَّع بكلِّ بطاقةِ كِيانٍ جديدة */
$CARDS = array(
    'Clients/client_profile.php',
    'Employees/employee_profile.php',
);

require_once $ROOT . '/includes/profile_kit.php';

/* ═══ ① العُدّةُ نفسُها: تُخرج ما يوازن ═════════════════════════════════════
   كلُّ فاتحٍ في العُدّةِ له مُغلِقٌ يطابقه عددًا ونوعًا — وإلا وُلد العطبُ من
   المكوّنِ لا من الشاشة. */
echo "\n① عُدّةُ البطاقة — الوسومُ متوازنةٌ في المُخرَج\n";

function tag_balance($html)
{
    preg_match_all('~</?(div|section|span|ul|li|p|h[1-6]|a|img|i)\b[^>]*>~i', $html, $m, PREG_SET_ORDER);
    $stack = array();
    foreach ($m as $t) {
        $tag = strtolower($t[1]);
        if (in_array($tag, array('img'), true)) { continue; }   // وسمٌ فارغٌ لا يُغلق
        if ($t[0][1] === '/') {
            $top = array_pop($stack);
            if ($top !== $tag) { return "أُغلق `$tag` وآخرُ مفتوحٍ `" . ($top === null ? '—' : $top) . "`"; }
        } else {
            $stack[] = $tag;
        }
    }
    return $stack ? ('بقي مفتوحًا: ' . implode(', ', $stack)) : true;
}

$pairs = array(
    'hero'    => ems_profile_hero(array('name' => 'كِيان', 'status' => array('text' => 'نشط', 'tone' => 'ok'),
                     'chips' => array(array('text' => 'C-1', 'mono' => true)),
                     'facts' => array(array('label' => 'حقل', 'value' => ''), array('label' => 'آخر', 'value' => 'قيمة')))),
    'stats'   => ems_profile_stats(array(array('value' => 0, 'label' => 'عدّاد', 'unit' => 'سجل'),
                     array('values' => array('10 USD', '20 SDG'), 'label' => 'مال', 'variant' => 'money'),
                     array('value' => 5, 'label' => 'رابط', 'href' => 'x.php'))),
    'group'   => ems_profile_group_open(array('title' => 'مجموعة', 'icon' => 'fas fa-x', 'meta' => 'شرح')) . ems_profile_group_close(),
    'section' => ems_profile_section_open(array('title' => 'قسم', 'icon' => 'fas fa-y', 'note' => 'ملاحظة')) . ems_profile_section_close(),
    'facts'   => ems_profile_facts(array(array('label' => 'ل', 'value' => 'ق')), true),
    'pill'    => ems_profile_badge('حالة', 'warn', array('icon' => 'fas fa-z')),
    'note'    => ems_profile_note('تنبيه', 'info'),
);
foreach ($pairs as $name => $html) {
    $r = tag_balance($html);
    if ($r === true) { ok("`$name` متوازنٌ"); } else { bad("`$name` غيرُ متوازن — $r"); }
}

/* النغمةُ المجهولةُ تسقط إلى `neutral` ولا تُصيَّر صنفًا مخترعًا */
$unknown = ems_profile_badge('س', 'chartreuse');
if (strpos($unknown, 'ems-profile__pill--neutral') !== false && strpos($unknown, 'chartreuse') === false) {
    ok('نغمةٌ غيرُ معلنةٍ تسقط إلى neutral ولا تتسرّب إلى الصنف');
} else {
    bad('نغمةٌ غيرُ معلنةٍ تسرّبت: ' . $unknown);
}

/* «0» قيمةٌ صحيحةٌ لا غياب — والغيابُ يُعلَن «—» */
$f = ems_profile_facts(array(array('label' => 'ل', 'value' => 0), array('label' => 'غ', 'value' => null)));
if (substr_count($f, '>0<') === 1 && strpos($f, 'ems-profile__fact-value--empty') !== false) {
    ok('«0» تُصيَّر قيمةً · والغائبُ يُعلَن غيابَه بصنفٍ ظاهر');
} else {
    bad('معالجةُ الصفرِ/الغيابِ غيرُ صحيحة');
}

/* الهروبُ إلزاميٌّ: وسمٌ في البيانات لا يصير وسمًا في الصفحة */
$xss = ems_profile_hero(array('name' => '<script>x</script>'));
if (strpos($xss, '<script>') === false && strpos($xss, '&lt;script&gt;') !== false) {
    ok('اسمُ الكِيانِ مهرَّبٌ — لا وسمَ يتسرّب من البيانات');
} else {
    bad('ثغرةُ هروبٍ في ems_profile_hero');
}

/* ═══ ② الشاشات: لا كتلةَ `<style>` ولا سمةَ `style=` ═══════════════════
   ◆ **بالموسِّم لا بمطابقةِ نص**: أولُ نسخةٍ من هذا الفاحصِ رسبت على تعليقٍ
     يشرح أن الكتلةَ **أُزيلت** — فمطابقةُ `<style` النصيةُ لا تفرّق بين
     شيفرةٍ وشرحٍ عنها. فتُسقَط التعليقاتُ بـ`token_get_all` ويُفحص ما يصل
     المتصفحَ فعلًا (T_INLINE_HTML وحدَه). */
echo "\n② الشاشات — صفرُ تصميمٍ داخلَ الصفحة\n";

/** الترميزُ الذي يصل المتصفحَ: HTML الحرفيُّ بلا تعليقاتِ PHP. */
function emitted_html($src)
{
    $out = '';
    foreach (token_get_all($src) as $t) {
        if (is_array($t) && $t[0] === T_INLINE_HTML) { $out .= $t[1]; }
    }
    /* وتعليقاتُ HTML نفسُها شرحٌ لا ترميزٌ فعّال */
    return preg_replace('~<!--.*?-->~s', '', $out);
}

foreach ($CARDS as $rel) {
    $src = @file_get_contents($ROOT . '/' . $rel);
    if ($src === false) { bad("$rel — غيرُ موجود"); continue; }
    $html      = emitted_html($src);
    $styleTags = preg_match_all('~<style[\s>]~i', $html);
    $styleAttr = preg_match_all('~\sstyle\s*=\s*["\']~i', $html);
    if ($styleTags === 0 && $styleAttr === 0) {
        ok("$rel — لا `<style>` ولا `style=`");
    } else {
        bad("$rel — كتلُ style: $styleTags · سماتُ style: $styleAttr");
    }
    if (strpos($src, "includes/profile_kit.php") !== false) {
        ok("$rel — يستعمل العُدّةَ لا ترميزًا يدويًّا");
    } else {
        bad("$rel — لا يُدرج profile_kit.php");
    }
    if (preg_match('~class="[^"]*\bems-profile\b~', $src)) {
        ok("$rel — نطاقُ `.ems-profile` معلَنٌ على الغلاف");
    } else {
        bad("$rel — نطاقُ `.ems-profile` غائبٌ فلا تنطبق الطبقة");
    }
}

/* ═══ ③ الأسماء: لا اسمَ يقع في شِباكِ مُحدِّدٍ عامٍّ يطابق بالسلسلة ══════ */
echo "\n③ الأسماء — خارجَ شِباكِ المُحدِّداتِ العامة\n";
$css = @file_get_contents($ROOT . '/assets/css/ems-profile.css');
if ($css === false) { bad('assets/css/ems-profile.css غيرُ موجود'); }
else {
    preg_match_all('~\.(ems-profile[A-Za-z0-9_-]*)~', $css, $m);
    $names = array_unique($m[1]);
    /* الفخاخُ المقيسةُ في المستودع — مصدرُها grep على `[class*=` في assets/css */
    $traps = array('badge-', ' badge', 'chip', 'tag', 'status', 'topbar');
    $caught = array();
    foreach ($names as $n) {
        foreach ($traps as $t) {
            if ($t === ' badge') { continue; }              // يلزمه فراغٌ سابقٌ في السمة
            if (strpos($n, $t) !== false) { $caught[] = "$n ⇐ [class*=\"$t\"]"; }
        }
    }
    if (!$caught) {
        ok(count($names) . ' صنفًا — ولا واحدٌ يقع في شِباكِ المُحدِّداتِ العامة');
    } else {
        bad('أصنافٌ محاصَرةٌ: ' . implode(' · ', $caught));
    }

    /* لا لونَ حرفيًّا: كلُّ قيمةٍ رمزٌ — والاحتياطيُّ داخلَ `var()` مسموح */
    $stripped = preg_replace('~/\*.*?\*/~s', '', $css);
    $stripped = preg_replace('~var\([^()]*(\([^()]*\))?[^()]*\)~', '', $stripped);
    $hard = array();
    if (preg_match_all('~#[0-9a-fA-F]{3,8}\b|\brgba?\(~', $stripped, $mm)) { $hard = $mm[0]; }
    if (!$hard) {
        ok('صفرُ لونٍ حرفيٍّ خارجَ `var()`');
    } else {
        bad('ألوانٌ حرفيةٌ خارجَ `var()`: ' . implode(' · ', array_slice(array_unique($hard), 0, 6)));
    }
}

/* ═══ ④ لا نسخةَ ثانيةٍ من المكوّن ═══════════════════════════════════════
   الكتلةُ القديمةُ `.driver-profile-page` أُسقطت — وعودتُها تعني لغتين. */
echo "\n④ مصدرٌ واحدٌ — لا نسخةَ ثانيةَ للبطاقة\n";
$main = @file_get_contents($ROOT . '/assets/css/ems.main.all.style.css');
if ($main !== false && preg_match_all('~^\s*\.driver-profile-page\b~m', $main) === 0) {
    ok('`ems.main.all.style.css` — صفرُ قاعدةٍ بـ`.driver-profile-page`');
} else {
    bad('`.driver-profile-page` عادت إلى الملفِّ العام — نسختان للبطاقةِ من جديد');
}

echo "\n" . str_repeat('─', 60) . "\n";
echo "  نجح: $PASS   ·   رسب: $FAIL\n";
exit($FAIL > 0 ? 1 : 0);
