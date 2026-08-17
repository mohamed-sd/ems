<?php
/**
 * ملف مساعد لجلب الروابط الديناميكية من جدول modules
 * Dynamic Navigation Links Helper
 * 
 * @version 1.0
 * @date 2026-03-04
 */

/**
 * التحقق من وجود عمود icon في جدول modules.
 *
 * @param object $conn mysqli connection object
 * @return bool
 */
function dynamicNavHasIconColumn($conn) {
    static $has_icon_column = null;

    if ($has_icon_column !== null) {
        return $has_icon_column;
    }

    $result = mysqli_query($conn, "SHOW COLUMNS FROM modules LIKE 'icon'");
    $has_icon_column = $result && mysqli_num_rows($result) > 0;

    return $has_icon_column;
}

/**
 * التحقق من وجود عمود display_order في جدول modules.
 *
 * @param object $conn mysqli connection object
 * @return bool
 */
function dynamicNavHasDisplayOrderColumn($conn) {
    static $has_display_order_column = null;

    if ($has_display_order_column !== null) {
        return $has_display_order_column;
    }

    $result = mysqli_query($conn, "SHOW COLUMNS FROM modules LIKE 'display_order'");
    $has_display_order_column = $result && mysqli_num_rows($result) > 0;

    return $has_display_order_column;
}

/**
 * التحقق من وجود عمود group_id في جدول modules (مهاجرة 2026_07_25_link_groups).
 *
 * @param object $conn mysqli connection object
 * @return bool
 */
function dynamicNavHasGroupColumn($conn) {
    static $has_group_column = null;

    if ($has_group_column !== null) {
        return $has_group_column;
    }

    $result = mysqli_query($conn, "SHOW COLUMNS FROM modules LIKE 'group_id'");
    $has_group_column = $result && mysqli_num_rows($result) > 0;

    return $has_group_column;
}

/**
 * جلب روابط الصفحات بناءً على دور المستخدم الحالي (الأدوار النشطة فقط)
 * يتم عرض الصفحات التابعة للدور مباشرة أو الصفحات التابعة لأدوار فرعية
 * Get modules/pages based on current user role (active roles only)
 * Shows pages assigned to role directly OR pages assigned to child roles
 * 
 * @param object $conn mysqli connection object
 * @param int $roleId user's role ID
 * @return array array of modules with id, name, code, owner_role_id, icon
 */
function getDynamicNavLinks($conn, $roleId) {
    $links = array();
    $icon_select = dynamicNavHasIconColumn($conn)
        ? "COALESCE(NULLIF(TRIM(m.icon), ''), 'fa fa-link') AS icon"
        : "'fa fa-link' AS icon";
    // مجموعة الرابط (NULL = يظهر في المستوى الأعلى كما كان قبل المهاجرة)
    $group_select = dynamicNavHasGroupColumn($conn) ? "m.group_id" : "NULL AS group_id";

    // التحقق من أن roleId صحيح
    $roleId = intval($roleId);

    // تحديد الترتيب بناءً على وجود عمود display_order
    $order_by = dynamicNavHasDisplayOrderColumn($conn)
        ? "m.display_order ASC, m.name ASC"
        : "m.id ASC";
    $order_col = dynamicNavHasDisplayOrderColumn($conn) ? "m.display_order" : "m.id";

    // استعلام لجلب جميع الروابط للأدوار النشطة فقط
    // يعرض الصفحات التابعة للدور الحالي وأيضاً الصفحات التابعة للدور الأب إن وجد
    // Fetch pages assigned to current role AND pages assigned to parent role (if exists)
    $query = "SELECT m.id, m.name, m.code, m.owner_role_id, $icon_select, $group_select,
                     $order_col AS display_order
              FROM modules m
              INNER JOIN roles mr ON m.owner_role_id = mr.id
              WHERE m.owner_role_id IN (
                  SELECT id FROM roles WHERE id = $roleId
                  UNION
                  SELECT parent_role_id FROM roles WHERE id = $roleId AND parent_role_id IS NOT NULL
              )
              AND (mr.status = '1' OR mr.status = 1)
              AND is_link = '1'
              ORDER BY $order_by";
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $links[] = $row;
        }
    }
    
    return $links;
}

/**
 * طباعة روابط التنقل الديناميكية كـ HTML
 * Print dynamic navigation links as HTML
 * 
 * @param array $links array of module links
 * @param string $basePrefix prefix for links (e.g., '../' for subdirectories)
 * @return void
 */
function printDynamicNavLinks($links, $basePrefix = '../', $badges = array()) {
    if (empty($links)) {
        return;
    }

    foreach ($links as $link) {
        // التحقق من وجود الحقول المطلوبة
        if (!isset($link['code']) || !isset($link['name'])) {
            continue;
        }

        $code = htmlspecialchars($link['code'], ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($link['name'], ENT_QUOTES, 'UTF-8');
        $icon = !empty($link['icon']) ? $link['icon'] : 'fa fa-link';
        $icon = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');

        // شارة عدٍّ اختيارية (خريطة code → عدد) بنمط شارة اعتماد الساعات
        $badge = '';
        if (isset($badges[$link['code']]) && intval($badges[$link['code']]) > 0) {
            $n = intval($badges[$link['code']]);
            $badge = ' <span class="nav-count-badge">' . ($n > 99 ? '99+' : $n) . '</span>';
        }

        // تحديد البادئة (prefix) بناءً على موقع الملف الحالي
        $href = $basePrefix . $code;

        echo '<li><a href="' . $href . '"><i class="' . $icon . '"></i> <span>' . $name . '</span>' . $badge . '</a></li>' . "\n";
    }
}

/**
 * جلب وطباعة روابط التنقل الديناميكية بشكل مدمج
 * Get and print all navigation links for current user
 * 
 * @param object $conn mysqli connection object
 * @param int $roleId user's role ID
 * @param string $basePrefix prefix for links
 * @return void
 */
function renderDynamicNavigation($conn, $roleId, $basePrefix = '../', $badges = array()) {
    $links = getDynamicNavLinks($conn, $roleId);
    printDynamicNavLinks($links, $basePrefix, $badges);
}

/* ═══════════════════════════════════════════════════════════════════════════
 * مجموعات الروابط (Link Groups) — طبقة تجميعٍ فوق ما سبق
 * ───────────────────────────────────────────────────────────────────────────
 * صفوف modules مفصولةٌ أصلًا لكل دور، فعمود group_id يعطي «لكل دورٍ أسماء
 * مجموعاته الخاصة» بلا جدول ربطٍ ثلاثيّ. ورابطٌ بلا مجموعة يظل في المستوى
 * الأعلى تمامًا كما كان — فالتوافق الرجعي كامل.
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * التحقق من وجود جدول link_groups (يمنع الانهيار قبل تطبيق المهاجرة).
 *
 * @param object $conn mysqli connection object
 * @return bool
 */
function navGroupsTableExists($conn) {
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    $result = mysqli_query($conn, "SHOW TABLES LIKE 'link_groups'");
    $exists = $result && mysqli_num_rows($result) > 0;

    return $exists;
}

/**
 * جلب مجموعات الروابط المرئية لدورٍ ما — بنفس قاعدة رؤية الشاشات تمامًا
 * (مجموعات الدور نفسه + مجموعات دوره الأب إن وُجد).
 *
 * @param object $conn mysqli connection object
 * @param int $roleId user's role ID
 * @return array array of groups with id, name, icon, display_order
 */
function getNavGroups($conn, $roleId) {
    $groups = array();

    if (!navGroupsTableExists($conn)) {
        return $groups;
    }

    $roleId = intval($roleId);

    $query = "SELECT g.id, g.name,
                     COALESCE(NULLIF(TRIM(g.icon), ''), 'fa fa-folder') AS icon,
                     g.display_order
              FROM link_groups g
              WHERE g.is_active = 1
                AND g.owner_role_id IN (
                    SELECT id FROM roles WHERE id = $roleId
                    UNION
                    SELECT parent_role_id FROM roles WHERE id = $roleId AND parent_role_id IS NOT NULL
                )
              ORDER BY g.display_order ASC, g.name ASC";

    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $groups[] = $row;
        }
    }

    return $groups;
}

/**
 * بناء شجرة التنقل: عُقَدُ مجموعاتٍ تحوي روابطها، وروابطُ مفردة بلا مجموعة،
 * مرتَّبةً معًا بـ display_order فيتحكّم المسؤول في تداخلهما.
 *
 * مجموعةٌ بلا رابطٍ مرئيٍّ واحد تُحذف — فالرأس الفارغ ضجيجٌ لا قائمة.
 *
 * @param array $links  ناتج getDynamicNavLinks
 * @param array $groups ناتج getNavGroups
 * @return array عُقَد: array('type'=>'group'|'link', ...)
 */
function buildNavTree($links, $groups) {
    $byGroup = array();
    $loose   = array();

    foreach ($links as $link) {
        $gid = isset($link['group_id']) && $link['group_id'] !== null ? intval($link['group_id']) : 0;
        if ($gid > 0) {
            if (!isset($byGroup[$gid])) {
                $byGroup[$gid] = array();
            }
            $byGroup[$gid][] = $link;
        } else {
            $loose[] = $link;
        }
    }

    $nodes = array();

    foreach ($groups as $group) {
        $gid = intval($group['id']);
        if (empty($byGroup[$gid])) {
            continue; // مجموعة بلا روابط مرئية — لا تُطبع
        }
        $nodes[] = array(
            'type'  => 'group',
            'key'   => 'g' . $gid,
            'name'  => $group['name'],
            'icon'  => $group['icon'],
            'order' => intval($group['display_order']),
            'links' => $byGroup[$gid]
        );
    }

    foreach ($loose as $link) {
        $nodes[] = array(
            'type'  => 'link',
            'order' => isset($link['display_order']) ? intval($link['display_order']) : 0,
            'link'  => $link
        );
    }

    // ترتيبٌ مستقرّ: display_order أولًا، ثم موضع الإدراج (المجموعات قبل المفردات
    // عند التساوي). usort في PHP 8 مستقرّ، فمفتاح الترتيب وحده يكفي.
    usort($nodes, function ($a, $b) {
        return $a['order'] <=> $b['order'];
    });

    return $nodes;
}

/**
 * طباعة عقدة رابطٍ واحدة (نفس مخرجات printDynamicNavLinks لعنصرٍ واحد).
 *
 * @return int عدد الشارة المطبوعة (لتجميعها على رأس المجموعة)
 */
/* ══ مجموعةٌ بلا رابطٍ لا تُطبع — حارسُ الغلافِ الفارغ ══════════════════════
   ◆ **العلّةُ المقيسة (2026-08-20)**: `printNavLinkItem` تُسقط الرابطَ لسببَين
     مشروعَين — كابحُ مساحةِ العمل (عزلُ الإدارات) وحارسُ التكرار — **وتُسقطه
     بعدَ أن يكون المُصيِّرُ قد طبعَ غلافَ المجموعةِ ورأسَها وفتحَ قائمتَها**.
     فإذا سقطت روابطُ المجموعةِ كلُّها بقيَ رأسٌ بشيفرونٍ وقائمةٌ فارغة.
     المقيس: **٨٢ غلافًا فارغًا من ٤٥٦ في ١٩ دورًا جذريًّا (١٨٪)**.
   ◆ **ولم تكشفه U5**: بوابةُ «مجموعةٌ بلا عناصر» تعدُّ من `$groupCounts` —
     وهي خريطةٌ لا تُنشأ مفاتيحُها إلا **بوجودِ عنصر**، فحكمُها لا يتحقق أبدًا.
     بوابةٌ تقيس ما لا يقع تمرُّ خضراءَ إلى الأبد.
   ◆ **والعلاجُ ترتيبٌ لا شرط**: تُطبع الروابطُ في مخزنٍ مؤقّتٍ أولًا، ثم
     يُطبع الغلافُ **إن نجا رابط**. فالقرارُ يُبنى على ما خرج فعلًا لا على ما
     كان مُزمَعًا. **ولا يُخفى رابطٌ ولا يُلغى مسار** — يُحذف رأسٌ فارغٌ لا غير.
   ══════════════════════════════════════════════════════════════════════════ */
if (!function_exists('ems_nav_group_has_link')) {
    function ems_nav_group_has_link($html) {
        return (bool) preg_match('~<a\b[^>]*\bhref=~i', (string) $html);
    }
}

function printNavLinkItem($link, $basePrefix = '../', $badges = array(), $extraClass = '') {
    if (!isset($link['code']) || !isset($link['name'])) {
        return 0;
    }

    /* ══ حارسُ التكرارِ في المخنقِ الواحد ═══════════════════════════════════════
       ⇐ INJ-0414 · 0489 · 0540 · 0448 · 0222 · 0562 · 0127 · 0132 · 0154
                 · 0428 · 0512 · 0513 · 0554
       كلُّ رابطٍ في السايدبارِ يمرُّ من هنا — بذرةً كان أو حقنًا في المولِّد أو
       صفًّا يدويًّا في `nav_items`. فمسارٌ طُبع مرةً لا يُطبع ثانيةً، والعلّةُ
       لا تعود من أيِّ مصدرٍ جديد.
       ◆ والمقارنةُ **بعد التطبيع**: المرساةُ (`#n9g7i1`) والبادئةُ (`../`)
         وسلسلةُ الاستعلامِ تُجرَّد — وإلا عُدَّ الرابطُ ذو المرساةِ ملفًّا آخرَ
         فمرَّ التكرارُ صامتًا (وهو جذرُ سبعةِ بنودٍ في السجل). */
    /* ◆ ووحدةُ التكرارِ **الملفُّ مع تسميتِه** لا الرابطُ وحدَه:
         · ملفٌّ بتسميتين = مدخلانِ مقصودانِ لقسمين (تُميّزهما المِرساة) — يُطبعان.
         · ملفٌّ **بالتسميةِ نفسِها** مرتين = تكرارٌ لا يميّزه شيءٌ — يُطبع مرةً.
       وأوّلُ صياغةٍ حرست بالمسارِ وحدَه فابتلعت أربعين رابطًا مشروعًا. */
    /* ══ عزلُ الإدارات (ثامنًا-⑦) — المنعُ في المخنقِ نفسِه ═══════════════════
       ◆ **وُضع الترشيحُ أولًا في `getUnifiedNavItems` فمرَّ صامتًا**: قِيس بعدَه
         فأعطى **صفرَ تسرّبٍ في القائمة**، ثم قِيس **السايدبارُ المُصيَّرُ** فأعطى
         **أربعةً وأربعين متسرّبًا**. والسببُ أن كتلةَ UXUI-01 **تصطنع روابطَ من
         السجلِّ بعدَ الترشيح** («لا سايدبارَ موروثٌ خلفَ المصفوفة») فتتخطّاه.
         **وترشيحٌ في طبقةٍ يسبقها مولِّدٌ ليس ترشيحًا.**
       ◆ فمحلُّه هنا: هذه الدالةُ **يمرُّ بها كلُّ رابطٍ** — بذرةً كان أو حقنًا في
         المولِّد أو صفًّا يدويًّا، وهو ما تقوله ترويستُها حرفًا.
       ◆ والمنعُ **بسياقِ المساحةِ** لا بالمستخدم: `user + active_scope + route`.
       ◆ وهذا **إخفاءُ ظهورٍ لا حذفُ مسار**: نقطةُ النهايةِ والحدثُ والمقصدُ باقيةٌ. */
    if (isset($_SESSION['user'])
        && strval(isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : '') !== '-1') {
        $__sf = dirname(__DIR__) . '/includes/space_scope.php';
        if (is_file($__sf)) {
            require_once $__sf;
            if (function_exists('ems_scope_forbidden_set')) {
                $__forb = ems_scope_forbidden_set();
                if (!empty($__forb)) {
                    $__r = preg_replace('/[?#].*$/', '', (string) $link['code']);
                    $__r = ltrim(preg_replace('~^(\.\./)+~', '', $__r), '/');
                    if (isset($__forb[mb_strtolower($__r)])) { return 0; }
                }
            }
        }
    }

    if (function_exists('ems_nav_mark_printed')
        && !ems_nav_mark_printed($link['code'] . '||' . trim((string) $link['name']))) {
        return 0;
    }

    $code = htmlspecialchars($link['code'], ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($link['name'], ENT_QUOTES, 'UTF-8');
    $icon = !empty($link['icon']) ? $link['icon'] : 'fa fa-link';
    $icon = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');

    $n = isset($badges[$link['code']]) ? intval($badges[$link['code']]) : 0;
    $badge = $n > 0 ? ' <span class="nav-count-badge">' . ($n > 99 ? '99+' : $n) . '</span>' : '';

    $cls = $extraClass !== '' ? ' class="' . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') . '"' : '';

    echo '<li' . $cls . '><a href="' . $basePrefix . $code . '"><i class="' . $icon . '"></i> '
       . '<span>' . $name . '</span>' . $badge . '</a></li>' . "\n";

    return $n;
}

/**
 * طباعة عقدة مجموعةٍ قابلة للطيّ.
 *
 * رأس المجموعة زرٌّ لا رابط، لثلاثة أسباب مقصودة:
 *   • إغلاق السايدبار على الموبايل مربوطٌ بالنقر على أي <a> داخله — والزرّ
 *     يخرج من ذلك الشرط تلقائيًا فلا تنغلق القائمة عند مجرّد التوسيع.
 *   • كاشف الصفحة النشطة يمرّ على li a[href] وحدها فلا يلتبس عليه الرأس.
 *   • الوصولية: عنصرٌ يوسّع/يطوي هو زرّ لا وجهة تنقّل.
 *
 * شارة الرأس = مجموع شارات أبنائه، وإلا اختفت العدادات المعلَّقة داخل مجموعةٍ
 * مطويّة (والافتراض أن الكل مطويّ).
 */
function printNavGroupItem($node, $basePrefix = '../', $badges = array()) {
    $key  = htmlspecialchars($node['key'], ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($node['name'], ENT_QUOTES, 'UTF-8');
    $icon = htmlspecialchars(!empty($node['icon']) ? $node['icon'] : 'fa fa-folder', ENT_QUOTES, 'UTF-8');

    $total = 0;
    foreach ($node['links'] as $link) {
        if (isset($link['code']) && isset($badges[$link['code']])) {
            $total += intval($badges[$link['code']]);
        }
    }
    $badge = $total > 0 ? ' <span class="nav-count-badge nav-group-badge">' . ($total > 99 ? '99+' : $total) . '</span>' : '';

    /* ① الروابطُ في مخزنٍ مؤقّتٍ قبلَ الغلاف */
    ob_start();
    foreach ($node['links'] as $link) {
        printNavLinkItem($link, $basePrefix, $badges);
    }
    $body = ob_get_clean();
    /* ② ولا غلافَ لمجموعةٍ لم ينجُ منها رابط */
    if (!ems_nav_group_has_link($body)) { return; }

    echo '<li class="nav-group" data-group-key="' . $key . '">' . "\n";
    echo '  <button type="button" class="nav-group-head" aria-expanded="false" aria-controls="navgrp-' . $key . '" aria-label="' . $name . '" title="' . $name . '">'
       . '<i class="' . $icon . '" aria-hidden="true"></i> '
       . '<span class="nav-group-name">' . $name . '</span>' . $badge
       . '<i class="fa fa-chevron-down nav-group-caret" aria-hidden="true"></i>'
       . '</button>' . "\n";
    echo '  <ul class="nav-group-items" id="navgrp-' . $key . '">' . "\n";
    echo $body;
    echo '  </ul>' . "\n";
    echo '</li>' . "\n";
}

/**
 * طباعة شجرة التنقل كاملةً (مجموعاتٍ ومفردات).
 *
 * @param array $nodes ناتج buildNavTree (وقد تُلحق به عُقَدٌ مبنيّة يدويًا)
 */
function printNavTree($nodes, $basePrefix = '../', $badges = array()) {
    foreach ($nodes as $node) {
        if (isset($node['type']) && $node['type'] === 'group') {
            printNavGroupItem($node, $basePrefix, $badges);
        } elseif (isset($node['link'])) {
            printNavLinkItem($node['link'], $basePrefix, $badges);
        }
    }
}

/**
 * بناء عقدة مجموعةٍ اصطناعية من روابطَ لا تأتي من جدول modules — مثل روابط
 * بوابة الطلب المالي (تُشتقّ من جدول التوجيه) والشاشات المالية المُعارة
 * بمنحة عرض. مجموعتها محايدةٌ ثابتة لأن صفّها مملوكٌ للمالية لا للدور الرائي.
 *
 * @param string $key   مفتاح ثابت (يُستعمل في حفظ حالة الطيّ)
 * @param array  $links مصفوفة روابط بصيغة code/name/icon
 * @return array|null عقدة مجموعة، أو null إن لم يكن فيها رابطٌ واحد
 */
function makeNavGroupNode($key, $name, $icon, $order, $links) {
    if (empty($links)) {
        return null;
    }

    return array(
        'type'  => 'group',
        'key'   => $key,
        'name'  => $name,
        'icon'  => $icon,
        'order' => intval($order),
        'links' => $links
    );
}

?>
