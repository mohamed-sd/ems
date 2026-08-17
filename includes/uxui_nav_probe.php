<?php
/**
 * includes/uxui_nav_probe.php — عُدَّةُ قياسِ التنقلِ المُصيَّرِ (جولة UXUI-01)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ دوالُّ مشترَكةٌ لأدواتِ الجولة: تصييرُ سايدبارِ دورٍ بجلسةِ مستخدمٍ حقيقيٍّ
 *   (فتسري بواباتُ المنحِ الفردية fail-closed كما تسري على المستخدمِ الفعلي)،
 *   والتقاطُ (المجموعة · الاسم · الرابط) بالترتيب، وتطبيعُ المسار، وقراءةُ
 *   مصفوفةِ التنقلِ المعيارية docs/uxui_matrix_20260818.csv.
 * ◆ فخُّ الحارسِ الساكن: ems_nav_mark_printed حارسُ تكرارٍ static لكلِّ عملية —
 *   تصييرُ أدوارٍ متعاقبةٍ بعمليةٍ واحدةٍ يُلزم تصفيرَه قبل كلِّ دور.
 * ◆ قراءةٌ خالصة — لا كتابةَ في القاعدةِ إطلاقًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

/** الأدوارُ الجذريةُ التسعةَ عشرَ — نطاقُ دليلِ السايدبارِ الحيِّ نفسُه */
if (!function_exists('uxp_root_roles')) {
    function uxp_root_roles() { return array(1,2,3,4,5,6,9,12,13,15,16,17,23,25,26,27,28,32,33); }
}

/** أولُ مستخدمٍ حقيقيٍّ لكلِّ دورٍ في شركةِ ايكوبيشن (co4) — لجلساتِ التصيير */
if (!function_exists('uxp_role_users')) {
    function uxp_role_users($conn)
    {
        static $cache = null;
        if ($cache !== null) { return $cache; }
        $cache = array();
        $res = mysqli_query($conn, "SELECT CAST(role AS UNSIGNED) r, MIN(id) uid FROM users WHERE company_id = 4 GROUP BY CAST(role AS UNSIGNED)");
        while ($x = mysqli_fetch_assoc($res)) { $cache[(int) $x['r']] = (int) $x['uid']; }
        return $cache;
    }
}

/** تطبيعُ المسار: تُجرَّد ../ والاستعلامُ والمرساة — هويةُ الملفِّ للقياس */
if (!function_exists('uxp_norm')) {
    function uxp_norm($href)
    {
        $r = preg_replace('~^(\.\./)+~', '', trim((string) $href));
        return preg_replace('/[?#].*$/u', '', $r);
    }
}

/** تصييرُ سايدبارِ دورٍ وإرجاعُ **HTML الخام** — مصدرٌ واحدٌ لكلِّ قياسٍ مُصيَّر
 *  ◆ فُصلت عن `uxp_render_role` (2026-08-20) لأن قياساتٍ تحتاج البنيةَ لا
 *    المواضعَ المستخرَجة: غلافُ المجموعةِ الفارغُ لا يظهر في قائمةِ المواضعِ
 *    أصلًا — فمن قاس المواضعَ وحدَها **لا يستطيع** رؤيتَه (وهو ما أعمى U5). */
if (!function_exists('uxp_render_role_html')) {
    function uxp_render_role_html($conn, $roleId, $uid = null)
    {
        if ($uid === null) { $users = uxp_role_users($conn); $uid = isset($users[(int) $roleId]) ? $users[(int) $roleId] : 0; }
        $_SESSION['user'] = array('id' => (int) $uid, 'role' => (string) $roleId, 'company_id' => 4, 'name' => 'uxui-probe');
        if (function_exists('ems_nav_mark_printed')) { ems_nav_mark_printed('', true); }
        $chats = '<li><a href="../chats/index.php" id="sidebarChatLink"><i class="fa fa-comments"></i>'
               . '<span class="sidebar-link-text">المراسلات</span></a></li>' . "\n";
        ob_start();
        $ok = renderUnifiedNavigationV2($conn, (string) $roleId, '../', array(), $chats);
        $html = ob_get_clean();
        return $ok ? $html : '';
    }
}

/** أغلفةُ المجموعاتِ المُصيَّرةُ وعددُ روابطِ كلٍّ — [['name','links'],…]
 *  تُقرأ بالشجرةِ لا بنمط: غلافُ المجموعةِ يحوي أغلفةً متداخلةً («المزيد»). */
if (!function_exists('uxp_nav_group_shells')) {
    function uxp_nav_group_shells($html)
    {
        if (trim((string) $html) === '') { return array(); }
        $prev = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $ok = $doc->loadHTML('<?xml encoding="utf-8" ?><ul>' . $html . '</ul>');
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) { return array(); }
        $xp = new DOMXPath($doc);
        $out = array();
        foreach ($xp->query('//li[contains(concat(" ", normalize-space(@class), " "), " nav-group ")]') as $li) {
            $nm = $xp->query('.//span[contains(@class,"nav-group-name")]', $li);
            $out[] = array(
                'name'  => $nm->length ? trim($nm->item(0)->textContent) : '(بلا اسم)',
                'links' => $xp->query('.//a[@href]', $li)->length,
            );
        }
        return $out;
    }
}

/** تصييرُ سايدبارِ دورٍ والتقاطُ مواضعِه [['group','label','href'],…] بالترتيب */
if (!function_exists('uxp_render_role')) {
    function uxp_render_role($conn, $roleId, $uid = null)
    {
        $html = uxp_render_role_html($conn, $roleId, $uid);
        if ($html === '') { return array(); }
        return uxp_parse_nav_html($html);
    }
}

/** التقاطُ المواضعِ من HTML المُصيَّر — رؤوسُ المجموعاتِ ثم روابطُها بالتسلسل */
if (!function_exists('uxp_parse_nav_html')) {
    function uxp_parse_nav_html($html)
    {
        /* ══ محوران لا واحد: رأسُ الطيِّ والقسمُ المقروء ═══════════════════════
           ◆ **`group`** = رأسُ المجموعةِ القابلُ للطيِّ (`nav-group-name`) — وهو
             وحدةُ **الموضع**: أين يسكن المسارُ في التبويب.
           ◆ **`section`** = العنوانُ الفرعيُّ (`nav-subhead`) إن وُجد وإلا الرأسُ
             نفسُه — وهو وحدةُ **القراءة**: ما تقع عليه العينُ ككتلةٍ واحدة.
           ◆ **ولماذا فُصلا** (2026-08-17): صار السايدبار طبقتين — عشرُ رؤوسٍ
             للتوجُّهِ وأقسامٌ للمسح. فقارئٌ يرى الرأسَ وحدَه يقيس الموضعَ صحيحًا
             ويقيس القراءةَ خطأً (يعدُّ كتلةً واحدةً ما هو سبعُ كتلٍ معنونة)،
             وقارئٌ يرى القسمَ وحدَه يعكس الخطأ. **والمحوران يُقاسان معًا.**
           ◆ ورأسٌ جديدٌ يُصفِّر القسم — فلا يتسرَّب عنوانُ مجموعةٍ إلى تاليتِها. */
        $positions = array(); $group = '— خارج التبويب'; $section = '';
        /* ◆ و**المُعلِمُ المخفيُّ** (`nav-solo-marker`) ينسب الرابطَ المكشوفَ إلى
             مجموعتِه: المجموعةُ النحيفةُ تُطبع بلا رأسٍ توفيرًا للنقرة، ولولا
             المُعلِمُ لنُسب رابطُها إلى المجموعةِ التي سبقته فأُعلن «تشتّتٌ» كاذب. */
        $re = '/<span class="nav-group-name">(?<g>[^<]*)<\/span>'
            . '|<li class="nav-solo-marker"[^>]*data-group="(?<sg>[^"]*)"'
            . '|<li class="nav-subhead"[^>]*><span>(?<s>[^<]*)<\/span>'
            . '|<a\b[^>]*href="(?<h>[^"]*)"[^>]*>(?<in>.*?)<\/a>/us';
        if (preg_match_all($re, $html, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                if (isset($m['g']) && $m['g'] !== '') {
                    $group = trim(html_entity_decode($m['g'], ENT_QUOTES, 'UTF-8'));
                    $section = '';
                    continue;
                }
                if (isset($m['sg']) && $m['sg'] !== '') {
                    $group = trim(html_entity_decode($m['sg'], ENT_QUOTES, 'UTF-8'));
                    $section = '';
                    continue;
                }
                if (isset($m['s']) && $m['s'] !== '') {
                    $section = trim(html_entity_decode($m['s'], ENT_QUOTES, 'UTF-8'));
                    continue;
                }
                $inner = preg_replace('/<span[^>]*nav-count-badge[^>]*>.*?<\/span>/us', '', $m['in']);
                $label = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES, 'UTF-8'));
                $label = preg_replace('/\s+/u', ' ', $label);
                $positions[] = array(
                    'group'   => $group,
                    'section' => ($section !== '' ? $section : $group),
                    'label'   => $label,
                    'href'    => trim($m['h']),
                );
            }
        }
        return $positions;
    }
}

/** مصفوفةُ التنقلِ المعيارية — المفتاحُ المسارُ المطبَّعُ صغيرًا */
if (!function_exists('uxp_matrix')) {
    function uxp_matrix($root = null)
    {
        static $cache = null;
        if ($cache !== null) { return $cache; }
        $root = $root !== null ? $root : dirname(__DIR__);
        $csv = $root . '/docs/uxui_matrix_20260818.csv';
        if (!is_file($csv)) { return array(); }
        $fh = fopen($csv, 'r');
        $hdr = fgetcsv($fh);
        $cache = array();
        while (($r = fgetcsv($fh)) !== false) {
            $row = array_combine($hdr, $r);
            $cache[mb_strtolower(trim($row['route']))] = $row;
        }
        fclose($fh);
        return $cache;
    }
}
