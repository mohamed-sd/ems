<?php
/**
 * tools/uxui_css_scope_gate.php — نقلُ نمطٍ لا يجوز أن يُنتج محدِّدًا عامًّا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ الذي يمنعه — وقع فعلًا**: `Settings/change_password.php` صفحةٌ
 *   ضيّقةٌ عمدًا وفيها `.main { max-width: 600px }` **محليًّا**. ولمّا نُقل
 *   النمطُ إلى المركزِ صارت القاعدةُ تحكم **كلَّ شاشةٍ في النظام**: `.main`
 *   عرضُه 600 بدل 1837 على `Portal/my_tasks.php`. **عطبٌ شاملٌ من سطرٍ واحد**،
 *   ولم يكشفه فحصُ البناءِ ولا عدُّ العقدِ — بل قياسُ عرضِ الحاويةِ حيًّا.
 *
 * ◆ **والنقلُ الحرفيُّ صحيحٌ ما دام المحدِّدُ خاصًّا**. فالحارسُ يرفض ما كان
 *   محدِّدُه عامًّا (`.main` · `.btn-primary` · `.table-container` · `body` …)
 *   إلا أن يكون محصورًا بصنفِ نطاقٍ للشاشة.
 *
 * ◆ **وحصرُ النطاقِ `.main.scr-x` لا `.scr-x .main`**: الصنفانِ على العنصرِ
 *   **نفسِه**، وصيغةُ النسلِ لا تُطابق شيئًا أبدًا فتمرُّ صامتةً — وقد وقع
 *   ذلك أيضًا في أولِ إصلاح.
 *
 * التشغيل: php tools/uxui_css_scope_gate.php   (يُرجع 1 عند وجودِ عامّ)
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
mb_internal_encoding('UTF-8');
$f = dirname(__DIR__) . '/assets/css/ems-screens.css';
$s = file_get_contents($f);
/* القواعدُ المنقولةُ تُسبَق بتعليقِ مصدرِها */
preg_match_all('~/\*\s*──\s*([^\s]+\.php)\s*──\s*\*/(.*?)(?=/\*\s*──\s*[^\s]+\.php\s*──|\z)~s', $s, $m, PREG_SET_ORDER);
$GLOBAL_SEL = '~(^|\})\s*((?:html|body|\.main|\.container|\.container-fluid|\.card|\.btn|table|\.table|\.sidebar|\*|:root)\b[^{,]*)\{~';
$hits = array();
foreach ($m as $blk) {
    $src = $blk[1]; $css = $blk[2];
    if (preg_match_all($GLOBAL_SEL, $css, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $r) {
            $sel = trim($r[2]);
            /* المحدِّدُ المركَّبُ (فيه صنفٌ خاصٌّ) ليس عامًّا */
            /* ◆ **المحصورُ يُقبل**: محدِّدٌ عامٌّ مقيَّدٌ بصنفِ نطاقٍ للشاشةِ
                 (`.main.scr-x`) أو بصنفٍ خاصٍّ بوحدةٍ ليس عامًّا — والمرفوضُ
                 هو المطلقُ وحدَه. وحارسٌ يرفض ما أصلحه بنفسِه لا يُوثَق به. */
            if (preg_match('~\.(scr-|rsk-|ems-|ux-|fin-|opr-|mnt-|sup-|cnt-|act-|xf-)~', $sel)) { continue; }
            $hits[] = array($src, $sel);
        }
    }
}
echo "قواعدُ بمحدِّدٍ عامٍّ بعد النقل: " . count($hits) . "\n";
foreach ($hits as $h) { printf("  · %-38s ⇐ %s\n", $h[0], mb_substr($h[1], 0, 46)); }

exit(count($hits) === 0 ? 0 : 1);
