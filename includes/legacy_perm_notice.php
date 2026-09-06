<?php
/**
 * includes/legacy_perm_notice.php — لافتةُ «تعامل قديم».
 * ───────────────────────────────────────────────────────────────────────────
 * تُستدعى في **أوّلِ متنِ الشاشة** (قبل رأسِ الصفحة) في كلِّ سطحٍ يعرض أو
 * يضيف أو يعدّل على جدولِ صلاحيّاتِ الأدوارِ القديم — الذي حُذف فرعُه من
 * دالّةِ القرارِ بـPERM-01-DEC ق-٥، وبقي مقروءًا **أثرًا لا حكمًا**.
 *
 * لماذا مكوّنٌ مستقلٌّ بأنماطِه: الأسطحُ المستهدَفةُ تقع في **قالبَي عرضٍ
 * مختلفَين** — قالبُ المنتج (`inheader.php` ومعه `ems-alerts.css`) وقالبُ
 * كونسولِ المدير الأعلى (`admin/includes/layout_head.php` ولا يحمّل ملفَّ
 * الرسائل ولا يضع `ems-site` على الجسد). فلافتةٌ تعتمد على أصنافِ الرسائلِ
 * القائمةِ تخرج بلا لونٍ في نصفِ المواضع. الأنماطُ تُطبع **مرّةً واحدةً**
 * لكلِّ طلبٍ مهما تكرّر النداء، والألوانُ من `design-tokens.css` بقيمٍ
 * احتياطيّةٍ لمن لا يحمّله.
 *
 * @param string $mode  'read' يعرض فقط · 'write' يحرّر الجدولَ فعلًا
 * @param string $extra سطرٌ إضافيٌّ اختياريٌّ يخصُّ الشاشةَ (يُهرَّب)
 */

if (!function_exists('ems_legacy_perm_notice')) {
    function ems_legacy_perm_notice($mode = 'read', $extra = '')
    {
        static $css_printed = false;

        if (!$css_printed) {
            $css_printed = true;
            echo '<style id="ems-legacy-perm-css">'
               . '.ems-legacy-perm{display:flex;align-items:flex-start;gap:12px;'
               . 'margin:0 0 16px;padding:14px 16px;border-radius:10px;'
               . 'background:var(--warning-100,#F4E5D3);'
               . 'border:1px solid var(--warning-600,#A8541C);'
               . 'border-inline-start:6px solid var(--warning-600,#A8541C);'
               . 'color:var(--warning-deep,#7C2D12);'
               . 'font-family:inherit;line-height:1.85;'
               . 'box-shadow:0 1px 3px rgba(124,45,18,.12)}'
               . '.ems-legacy-perm > svg{flex:0 0 auto;width:26px;height:26px;'
               . 'margin-top:2px;fill:var(--warning-600,#A8541C)}'
               . '.ems-legacy-perm-body{flex:1 1 auto;min-width:0}'
               . '.ems-legacy-perm-title{display:block;font-weight:800;font-size:1.05rem;'
               . 'letter-spacing:.2px;margin-bottom:2px}'
               . '.ems-legacy-perm-text{display:block;font-size:.92rem;opacity:.95}'
               . '.ems-legacy-perm-extra{display:block;font-size:.88rem;opacity:.9;margin-top:4px}'
               . '@media print{.ems-legacy-perm{box-shadow:none}}'
               . '</style>';
        }

        $write = ($mode === 'write');
        $text  = $write
            ? 'هذه الشاشة تحرر جدول صلاحيات الأدوار القديم، وهو لم يعد مصدر قرار فتح الشاشات. أي حفظ هنا لا يسري على أي مستخدم حي، والحكم النافذ اليوم في قوالب الأدوار وحدها.'
            : 'هذه الشاشة تقرأ من جدول صلاحيات الأدوار القديم، وهو لم يعد مصدر قرار فتح الشاشات. ما يظهر هنا أثر تاريخي للاطلاع لا حكم نافذ، والحكم النافذ اليوم في قوالب الأدوار وحدها.';

        echo '<div class="ems-legacy-perm" role="note" dir="rtl">'
           . '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
           . '<path d="M12 2.6c.62 0 1.19.33 1.5.86l9.2 15.9c.31.53.31 1.19 0 1.72'
           . '-.31.53-.88.86-1.5.86H2.8c-.62 0-1.19-.33-1.5-.86-.31-.53-.31-1.19 0-1.72'
           . 'l9.2-15.9c.31-.53.88-.86 1.5-.86Zm0 5.9a1.15 1.15 0 0 0-1.15 1.24l.4 4.6'
           . 'a.75.75 0 0 0 1.5 0l.4-4.6A1.15 1.15 0 0 0 12 8.5Zm0 8.1a1.2 1.2 0 1 0 0 2.4'
           . 'a1.2 1.2 0 0 0 0-2.4Z"/></svg>'
           . '<span class="ems-legacy-perm-body">'
           . '<span class="ems-legacy-perm-title">تعامل قديم</span>'
           . '<span class="ems-legacy-perm-text">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>'
           . ($extra !== ''
                ? '<span class="ems-legacy-perm-extra">' . htmlspecialchars($extra, ENT_QUOTES, 'UTF-8') . '</span>'
                : '')
           . '</span></div>';
    }
}
