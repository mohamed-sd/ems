<?php
/**
 * includes/table_design.php — ضامنُ وصولِ تصميمِ الجداول.        (توحيد 2026-08-17)
 * ═══════════════════════════════════════════════════════════════════════════
 * المشكلةُ التي يحلُّها هذا الملفُّ — وهي مقيسةٌ لا مفترضة:
 *
 *   ① **إحدى وعشرون شاشةً لا تصلها الورقةُ أصلًا.** ستَّ عشرةَ منها تحت
 *      `admin/` بقشرةٍ مستقلّةٍ لا تمرُّ بـinheader، والباقي شاشاتٌ قائمةٌ
 *      بذاتها (`index.php` · `company/team.php` · عُددُ الشاشات).
 *
 *   ② **الصفحةُ تُحمّل أوراقَها بعدَ القشرة.** مثالٌ حيّ: Reports/contractall.php
 *      يُضمِّن inheader في السطر ٢١ ثم يُدرج `main_admin_style.css` و`style.css`
 *      في ٢٤–٢٦ — فيغلبان ورقةَ الجداولِ مهما أخّرتُها داخلَ القشرة.
 *
 *   ③ **الحارسُ الدفاعيُّ القديمُ كان مُعطَّلًا بصمت.** الحاقنُ في insidebar.php
 *      يتخطّى أيَّ ورقةٍ حاضرةٍ أصلًا (`if (querySelector(...)) return;`) —
 *      وورقةُ الجداولِ حاضرةٌ دائمًا من inheader. فالحارسُ لم يكن يعمل مرّةً
 *      واحدةً منذ كُتب.
 *
 * الحلُّ: `appendChild` على عقدةٍ موجودةٍ **ينقلُها** ولا ينسخُها. فنلتقطُ وسمَ
 * الورقةِ أينما كان ونُلحقُه بذيلِ <head> — فيصيرُ آخرَ ورقةٍ يقينًا. ويُعاد
 * الفعلُ عند DOMContentLoaded لالتقاطِ ما تُدرجه الصفحةُ بعدَ هذه النقطة.
 *
 * الاستعمال: `require_once` مرّةً في كلِّ قشرة. الإدراجُ المكرَّرُ آمنٌ ولا يُخرج شيئًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!defined('EMS_TABLE_DESIGN_EMITTED')) {
    define('EMS_TABLE_DESIGN_EMITTED', 1);

    $__td_file = dirname(__DIR__) . '/assets/css/ems-tables.css';
    $__td_href = '/ems/assets/css/ems-tables.css'
               . (is_file($__td_file) ? ('?v=' . filemtime($__td_file)) : '');
    ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($__td_href, ENT_QUOTES, 'UTF-8'); ?>" data-ems-table-design>
<script>
(function () {
    var HREF = <?php echo json_encode($__td_href, JSON_UNESCAPED_SLASHES); ?>;

    function enforce() {
        var head = document.head || document.getElementsByTagName('head')[0];
        if (!head) return;

        var link = head.querySelector('link[data-ems-table-design]')
                || head.querySelector('link[rel="stylesheet"][href*="ems-tables.css"]');

        if (!link) {
            link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = HREF;
            link.setAttribute('data-ems-table-design', '');
            head.appendChild(link);
            return;
        }
        link.setAttribute('data-ems-table-design', '');

        /* نُسخٌ مكرّرةٌ من الورقةِ نفسِها — أدرجتها قشرتان معًا. تُزال الزائدة. */
        var all = document.querySelectorAll('link[rel="stylesheet"][href*="ems-tables.css"]');
        for (var i = 0; i < all.length; i++) if (all[i] !== link) all[i].parentNode.removeChild(all[i]);

        /* ── «آخرُ ورقةٍ» تُقاس بترتيبِ المستندِ لا بترتيبِ <head> ──────────────
           قِيس حيًّا: `includes/excel_ui.php` يحقن `ems-excel.css` داخلَ <body>
           لا <head>. وورقةُ الجسدِ تأتي بعدَ كلِّ أوراقِ الرأسِ في التتالي، فلو
           اكتفينا بذيلِ <head> لبقيت ورقةٌ تغلبنا ونحن نظنُّنا آخِرين.
           فيُلتقط آخرُ وسمِ ورقةٍ في المستندِ كلِّه ونُوضَع بعدَه مباشرةً. */
        var sheets = document.querySelectorAll('link[rel="stylesheet"]');
        var last = sheets.length ? sheets[sheets.length - 1] : null;
        if (!last || last === link) return;                 // نحن آخِرون أصلًا
        last.parentNode.insertBefore(link, last.nextSibling);
    }

    /* تُنشر عالميًّا كي تستدعيَها القشرةُ مرّةً أخرى من متنِ الصفحة: الصفحاتُ
       تُدرج أوراقَها بين القشرتَين، فاستدعاءٌ متأخّرٌ يلتقطُها قبلَ الرسمِ
       فلا يومضُ المظهرُ انتظارًا لـDOMContentLoaded. */
    window.emsTableDesignEnforce = enforce;

    enforce();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enforce);
    }
    window.addEventListener('load', enforce);

    /* ── المراقبُ: الأخيريّةُ بنيويّةٌ لا سباقٌ زمنيّ ──────────────────────────
       قِيس حيًّا: `includes/excel_ui.php` يحقن `ems-excel.css` **بعدَ** حدثِ
       load، ومعالِجُنا مُسجَّلٌ قبلَه فيعمل قبلَه — فتبقى ورقتُنا في الترتيب ٢١
       من ٢٣. قواعدُ ems-excel الجدوليةُ السبعُ ضعيفةٌ فلم تُغيّر لونًا فعلًا،
       لكنّ «لم يضرَّنا لأنه ضعيف» ليس ضمانًا: يكفي أن يُضاف `!important` غدًا.
       فالمراقبُ يُعيد الورقةَ إلى الذيلِ كلَّما أُدرجت ورقةٌ بعدَها — أيًّا كان
       مَن أدرجَها ومتى. وهو رخيصٌ: لا يعمل إلا عندَ تغيُّرِ أبناءِ <head>. */
    if (typeof MutationObserver === 'function' && document.documentElement) {
        var scheduled = false;
        new MutationObserver(function (records) {
            if (scheduled) return;
            for (var i = 0; i < records.length; i++) {
                var added = records[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    var n = added[j];
                    if (n.nodeType !== 1 || n.tagName !== 'LINK') continue;
                    if ((n.getAttribute('rel') || '').toLowerCase() !== 'stylesheet') continue;
                    if (n.hasAttribute('data-ems-table-design')) continue;
                    /* التأجيلُ لدورةٍ صغرى يمنع التكرارَ حين تُدرَج عدّةُ أوراقٍ
                       دفعةً واحدة، ويمنع تغذيةَ المراقبِ لنفسِه بنقلِ عقدتِنا. */
                    scheduled = true;
                    setTimeout(function () { scheduled = false; enforce(); }, 0);
                    return;
                }
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
    }
    /* الطبقةُ الكونيّةُ في ems-tables.css لا تشترط ems-site، لكنّ طبقةَ
       ems-site أدقُّ وأقوى. فمنحُ الجسدِ الصنفَ يجعل الشاشاتِ اليتيمةَ تُصمَّم
       بالطبقةِ نفسِها التي تُصمَّم بها أخواتُها لا بطبقةٍ أضعف. */
    function markBody() {
        if (document.body && !document.body.classList.contains('ems-site')) {
            document.body.classList.add('ems-site');
        }
    }
    markBody();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', markBody);
    }
})();
</script>
<?php
}
