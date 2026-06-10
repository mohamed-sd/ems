<?php
/**
 * Unified page header component (.main_head).
 *
 * Renders the standard dark/gold page header used across the app. All visual
 * styling lives ONLY in assets/css/ems.main.all.style.css (selectors
 * `.main_head`, `.head_actions`, `.head-title`, `.head_back`, `.title-icon`).
 * This file owns the HTML STRUCTURE in one place so pages no longer hand-write it.
 *
 * Set these variables before `include`-ing this file:
 *   $header_title       string  page title text (escaped on output)
 *   $header_title_html  string  OPTIONAL raw title HTML (used as-is, instead of
 *                                $header_title) for titles that contain extra
 *                                markup such as inline icons or dynamic values.
 *                                The caller is responsible for escaping.
 *   $header_icon     string  Font Awesome class for the title icon (e.g. 'fas fa-users')
 *   $header_icon_tag string  OPTIONAL element for the icon wrapper: 'div' (default) | 'span'
 *   $header_actions array   left-side action items; each item is an assoc array:
 *        'tag'         => 'a' (default) | 'button'
 *        'href'        => string (for <a>; defaults to 'javascript:void(0)')
 *        'id'          => string (optional)
 *        'class'       => string (optional; '' is allowed and preserved)
 *        'title'       => string (optional tooltip)
 *        'icon'        => string Font Awesome class (optional)
 *        'label'       => string text
 *        'label_class' => string  when set, label is wrapped in <span class="...">
 *                                  (no leading space); otherwise printed as " label"
 *        'disabled'    => bool (for 'button')
 *        'style'       => string  OPTIONAL inline style (preserved as-is)
 *        'attrs'       => string  OPTIONAL raw extra attributes, e.g.
 *                                  'type="button" data-bs-toggle="modal"' or 'onclick="..."'
 *                                  (emitted verbatim — caller controls escaping)
 *   $header_back     mixed  right-side item(s): a single item array, OR a list of
 *                          item arrays (e.g. an extra toggle + back link). Pass an
 *                          empty array to render an empty .head_back div; pass
 *                          (bool) false to omit the .head_back element entirely
 *                          (matches pages that conditionally drop the back column).
 */

if (!function_exists('render_header_action')) {
    /**
     * Build one header action element (anchor or button) as HTML.
     */
    function render_header_action($item)
    {
        // Escape hatch: emit arbitrary raw HTML (e.g. a badge <span>) verbatim.
        if (isset($item['raw'])) {
            return $item['raw'];
        }

        $tag = isset($item['tag']) ? $item['tag'] : 'a';

        $attrs = '';
        if (isset($item['id']) && $item['id'] !== '') {
            $attrs .= ' id="' . htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8') . '"';
        }
        $attrs .= ' class="' . htmlspecialchars(isset($item['class']) ? $item['class'] : '', ENT_QUOTES, 'UTF-8') . '"';
        if (isset($item['title']) && $item['title'] !== '') {
            $attrs .= ' title="' . htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') . '"';
        }
        if (isset($item['style']) && $item['style'] !== '') {
            $attrs .= ' style="' . htmlspecialchars($item['style'], ENT_QUOTES, 'UTF-8') . '"';
        }
        if (isset($item['attrs']) && $item['attrs'] !== '') {
            $attrs .= ' ' . $item['attrs'];
        }

        if ($tag === 'button') {
            if (!empty($item['disabled'])) {
                $attrs .= ' disabled';
            }
            $open = '<button' . $attrs . '>';
            $close = '</button>';
        } else {
            $href = isset($item['href']) ? $item['href'] : 'javascript:void(0)';
            $open = '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' . $attrs . '>';
            $close = '</a>';
        }

        $icon = '';
        if (!empty($item['icon'])) {
            $icon = '<i class="' . htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') . '"></i>';
        }

        $label = isset($item['label']) ? $item['label'] : '';
        if (isset($item['label_class'])) {
            $labelHtml = '<span class="' . htmlspecialchars($item['label_class'], ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        } else {
            $labelHtml = ($label !== '') ? ' ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') : '';
        }

        return $open . $icon . $labelHtml . $close;
    }
}

$__actions = (isset($header_actions) && is_array($header_actions)) ? $header_actions : array();
$__icon      = isset($header_icon) ? $header_icon : 'fas fa-circle';
$__iconTag   = (isset($header_icon_tag) && $header_icon_tag === 'span') ? 'span' : 'div';
$__title     = isset($header_title) ? $header_title : '';
$__titleHtml = isset($header_title_html) ? $header_title_html : null;

// Normalise the back area. (bool) false omits the element entirely; an empty
// array renders an empty .head_back div; otherwise accept a single item or a list.
$__showBack  = !(isset($header_back) && $header_back === false);
$__backItems = array();
if ($__showBack && isset($header_back) && !empty($header_back)) {
    if (isset($header_back[0]) && is_array($header_back[0])) {
        $__backItems = $header_back;          // already a list of items
    } else {
        $__backItems = array($header_back);    // single item
    }
}
?>
<div class="main_head">
    <div class="head_actions">
        <?php foreach ($__actions as $__a) {
            // توحيد زر الإضافة عبر كل الصفحات: أي إجراء أيقونته تحوي fa-plus
            // (fa-plus / fa-plus-circle ...) يُعرض دائماً بأيقونة fa-solid fa-plus
            // وبدون نص. يُنقل النص الأصلي إلى tooltip، وتُزال label_class حتى لا
            // يبقى span فارغ قد تملؤه سكربتات الصفحة.
            if (!empty($__a['icon']) && strpos($__a['icon'], 'fa-plus') !== false) {
                if (empty($__a['title']) && !empty($__a['label'])) { $__a['title'] = $__a['label']; }
                $__a['icon']  = 'fa-solid fa-plus';
                $__a['label'] = '';
                unset($__a['label_class']);
                $__a['class'] = trim((isset($__a['class']) ? $__a['class'] : '') . ' ems-head-circle');
            }
            echo render_header_action($__a);
        } ?>
    </div>

    <h1 class="head-title">
        <<?php echo $__iconTag; ?> class="title-icon"><i class="<?php echo htmlspecialchars($__icon, ENT_QUOTES, 'UTF-8'); ?>"></i></<?php echo $__iconTag; ?>>
        <?php echo ($__titleHtml !== null) ? $__titleHtml : htmlspecialchars($__title, ENT_QUOTES, 'UTF-8'); ?>
    </h1>

    <?php if ($__showBack) { ?>
    <div class="head_back">
        <?php foreach ($__backItems as $__b) {
            // توحيد زر العودة عبر كل الصفحات: أي عنصر عودة نصُّه «رجوع» يُعرض دائماً
            // بأيقونة fa-solid fa-share وبدون كلمة. لا يمسّ الأزرار الأخرى (مثل
            // «الرئيسية» أو أزرار التبديل) لأن شرطه نصُّ العنوان «رجوع» تحديداً.
            if (isset($__b['label']) && trim($__b['label']) === 'رجوع') {
                $__b['icon']  = 'fa-solid fa-share';
                $__b['label'] = '';
                if (empty($__b['title'])) { $__b['title'] = 'رجوع'; }
                $__b['class'] = trim((isset($__b['class']) ? $__b['class'] : '') . ' ems-head-circle');
            }
            echo render_header_action($__b);
        } ?>
    </div>
    <?php } ?>
</div>
