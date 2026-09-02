<?php
/**
 * includes/navarch_renderer.php — المُصيِّرُ الحاكمُ الجديد (‏NAV-ARCH-02 §20·§21·§22·§23)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعادلةُ حرفًا (§20)**:
 *     `Rendered Sidebar = Active Workspace Placements ∩ User Authorized Screens`
 *   ⛔ **ولا يجوز** `Rendered Sidebar = All Authorized Screens` — وهي معادلةُ
 *   المُصيِّرِ القديمِ حرفًا: `getUnifiedNavItems()` تُرجع **كلَّ صفوفِ الدورِ في
 *   `nav_items` بشرطِ `can_view`** فيبلغ سايدبارُ التشغيلِ 89 رابطًا مقابلَ 12
 *   في ورقةِ الدليل. **فالصلاحيّةُ كانت تُنشئ موضعًا — وهذا ما يُنزَع.**
 *
 * ◆ **والصلاحيّةُ تبقى صحيحةً (§22 · §42)**: «اختفى من السايدبار» **ليس**
 *   «سُحبت صلاحيّتُه». هذا المُصيِّرُ **لا يمسُّ منحةً ولا يُغلق مسارًا**؛ الرابطُ
 *   المباشرُ يعمل كما كان لمن يملك الصلاحيّةَ والنطاق.
 *
 * ◆ **ترتيبُ العملِ التسعُ (§21)** — بهذا الترتيبِ لا غيرِه:
 *   ① المستخدم ② المساحةُ الحاليّة ③ **مواضعُها النشطةُ وحدَها** ④ ترتيبُ
 *   المجموعات ⑤ ترتيبُ المواضعِ داخلَ المجموعة ⑥ الصلاحيّة ⑦ النطاق
 *   ⑧ السياق ⑨ التصيير.
 *
 * ◆ **والسقوطاتُ الخمسةُ منزوعةٌ بنصِّ §23** — ولكلٍّ عدّادٌ يُثبت أنَّه لم يقع:
 *   ① مجموعةٌ افتراضيّة («إن لم نجد Group ⇒ DAILY») ⇒ الموضعُ بلا مجموعةٍ
 *      **يُحجَب ويُعَدُّ** (§35: `Block the affected Placement, not the program`).
 *   ② تصنيفٌ عامٌّ بديل (`emsNavTaxonomy`/`nav_route_group`) ⇒ **لا يُقرأ أصلًا**.
 *   ③ «الدورُ يراها فتُعرَض» ⇒ الصلاحيّةُ **ترشِّح** المواضعَ ولا تُنشئها.
 *   ④ «المسارُ موجودٌ فيُنشأ رابط» ⇒ لا مسارَ يدخل بلا صفِّ موضعٍ حاكم.
 *   ⑤ «بندُ ملاحةٍ إرثيٌّ فعّالٌ فيُظهَر» ⇒ `nav_items` **مصدرُ تفويضٍ فقط**،
 *      لا مصدرَ ظهور: صفٌّ فيه بلا موضعٍ لا يُصيَّر.
 *
 * ◆ **⛔ والظلُّ لا يغيّر تجربةَ المستخدم (§30)**: هذا الملفُّ **لا يُستدعى من
 *   أيِّ شاشةٍ حيّة**؛ يقرؤه أداةُ المقارنةِ وحدَها حتى يُثبَت الطيّارُ ويُقلَب.
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/permissions_helper.php';

if (!function_exists('navarch_norm_route')) {
    /** تسويةُ المسارِ — الصيغةُ الواحدةُ التي يُقارَن بها الموضعُ والتفويضُ والتصيير */
    function navarch_norm_route($s)
    {
        $s = preg_replace('~^(\.\./)+~', '', (string) $s);
        $s = preg_replace('~[?#].*$~', '', $s);
        return strtolower(trim(preg_replace('~\.php$~i', '', $s), '/'));
    }
}

if (!function_exists('navarch_authorized_routes')) {
    /**
     * **⑥ الصلاحيّة** — مجموعةُ المساراتِ التي يُصرَّح لهذا الدورِ برؤيتها.
     *
     * ◆ **ومصدرُها منحُ الأدوارِ لا الموضع**: `nav_items` تُستعمل هنا **خريطةَ
     *   مسارٍ إلى وحدة** ثمَّ يُسأل `role_permissions` — فهي **مصدرُ تفويضٍ**
     *   لا مصدرُ ظهور. وصفٌّ بلا `permission_code` مصرَّحٌ به بحكمِ اليومِ حرفًا
     *   (حارسُه في وجهتِه لا في القائمة).
     *
     * @return array route ⇒ true
     */
    function navarch_authorized_routes($conn, $roleId)
    {
        $roleId = (int) $roleId;
        $sql = "SELECT n.route
                  FROM nav_items n
                 WHERE n.role_id = {$roleId} AND n.active = 1
                   AND (n.permission_code IS NULL OR " . perm_nav_view_exists_sql('n') . ")";
        $out = array();
        $r = @mysqli_query($conn, $sql);
        while ($r && ($x = mysqli_fetch_assoc($r))) { $out[navarch_norm_route($x['route'])] = true; }
        return $out;
    }
}

if (!function_exists('navarch_render')) {
    /**
     * **المُصيِّرُ الحاكم** — يُرجِع الشجرةَ ودفترَ ما لم يظهر ولماذا.
     *
     * @param  mysqli $conn
     * @param  string $wsId    ② المساحةُ الحاليّة
     * @param  int    $roleId  ① المستخدم (‏دورُه الممثِّل)
     * @param  array  $opts    ['include_shell'=>bool] القشرةُ العامّةُ والشخصيّةُ
     *                         تُصيَّر في مِسمارَيهما لا في دورةِ الإدارة (§10·§11)
     * @return array {
     *   groups: [ {group_id, label, sort_no, items:[{route,label,sort_no,placement_type}]} ],
     *   shell:  [...] القشرةُ العامّة   personal: [...] مساحةُ عملي
     *   rendered:int, blocked:[…], counters:{…}
     * }
     */
    function navarch_render($conn, $wsId, $roleId, array $opts = array())
    {
        $inclShell = isset($opts['include_shell']) ? (bool) $opts['include_shell'] : true;
        $ws = mysqli_real_escape_string($conn, (string) $wsId);

        /* ③ **مواضعُ هذه المساحةِ النشطةُ وحدَها** — ولا مصدرَ آخرَ للظهور */
        $sql = "SELECT p.placement_id, p.route, p.canonical_label, p.placement_type, p.sort_no,
                       p.group_id, p.status, p.approved_by,
                       g.label_ar AS group_label, g.sort_no AS group_sort
                  FROM nav_workspace_placements p
                  LEFT JOIN nav_lifecycle_groups g ON g.id = p.group_id
                 WHERE p.workspace_id = '{$ws}'
                   AND p.status = 'ACTIVE'
                   AND (p.effective_to IS NULL OR p.effective_to >= CURDATE())";
        $plc = array();
        $r = @mysqli_query($conn, $sql);
        while ($r && ($x = mysqli_fetch_assoc($r))) { $plc[] = $x; }

        $auth = navarch_authorized_routes($conn, $roleId);

        $C = array('placements' => count($plc), 'no_permission' => 0, 'no_group' => 0,
                   'unapproved_secondary' => 0, 'non_sidebar_type' => 0,
                   'global_fallback' => 0, 'legacy_fallback' => 0);
        $blocked = array();
        $groups = array(); $shell = array(); $personal = array();

        foreach ($plc as $p) {
            $route = navarch_norm_route($p['route']);
            $type  = (string) $p['placement_type'];

            /* ⑥ **الصلاحيّة ترشِّح** — ولا تُنشئ موضعًا (§23-③) */
            if ($route === '' || !isset($auth[$route])) {
                $C['no_permission']++;
                $blocked[] = array('route' => $route, 'why' => 'NO_PERMISSION', 'type' => $type);
                continue;
            }

            /* §10 · §11 — القشرةُ العامّةُ والشخصيّةُ **خارجَ مقامِ دورةِ الإدارة** */
            if ($type === 'GLOBAL_SHELL') {
                if ($inclShell) { $shell[] = array('route' => $route, 'label' => $p['canonical_label'],
                                                   'sort_no' => (int) $p['sort_no']); }
                continue;
            }
            if ($type === 'PERSONAL') {
                if ($inclShell) { $personal[] = array('route' => $route, 'label' => $p['canonical_label'],
                                                      'sort_no' => (int) $p['sort_no']); }
                continue;
            }

            /* §9 — **ولا يدخل سايدبارَ مساحةِ الأعمالِ إلّا هذان** */
            if ($type !== 'PRIMARY' && $type !== 'SECONDARY_APPROVED') {
                $C['non_sidebar_type']++;
                $blocked[] = array('route' => $route, 'why' => 'NOT_A_SIDEBAR_TYPE', 'type' => $type);
                continue;
            }

            /* §12-هـ — ثانويٌّ بلا اعتمادٍ مسجَّلٍ **لا يظهر** (‏معيارُ §40) */
            if ($type === 'SECONDARY_APPROVED'
                && ((string) $p['approved_by'] === '' || $p['approved_by'] === null)) {
                $C['unapproved_secondary']++;
                $blocked[] = array('route' => $route, 'why' => 'UNAPPROVED_SECONDARY', 'type' => $type);
                continue;
            }

            /* §23-① — ⛔ **ولا مجموعةَ افتراضيّة**: الموضعُ بلا رأسِ طيٍّ يُحجَب ويُعَدّ */
            if ($p['group_id'] === null || (string) $p['group_label'] === '') {
                $C['no_group']++;
                $blocked[] = array('route' => $route, 'why' => 'NO_LIFECYCLE_GROUP', 'type' => $type);
                continue;
            }

            $gid = (int) $p['group_id'];
            if (!isset($groups[$gid])) {
                $groups[$gid] = array('group_id' => $gid, 'label' => (string) $p['group_label'],
                                      'sort_no' => (int) $p['group_sort'], 'items' => array());
            }
            $groups[$gid]['items'][] = array('route' => $route, 'label' => (string) $p['canonical_label'],
                                             'sort_no' => (int) $p['sort_no'], 'placement_type' => $type);
        }

        /* ④ ترتيبُ المجموعات ⑤ ثمَّ ترتيبُ المواضعِ داخلَ كلٍّ */
        uasort($groups, function ($a, $b) {
            return $a['sort_no'] === $b['sort_no'] ? strcmp($a['label'], $b['label'])
                                                   : $a['sort_no'] - $b['sort_no'];
        });
        $rendered = 0;
        foreach ($groups as $gid => $g) {
            usort($groups[$gid]['items'], function ($a, $b) { return $a['sort_no'] - $b['sort_no']; });
            $rendered += count($g['items']);
        }
        usort($shell, function ($a, $b) { return $a['sort_no'] - $b['sort_no']; });
        usort($personal, function ($a, $b) { return $a['sort_no'] - $b['sort_no']; });

        return array('workspace' => (string) $wsId, 'role_id' => (int) $roleId,
                     'groups' => array_values($groups), 'shell' => $shell, 'personal' => $personal,
                     'rendered' => $rendered, 'blocked' => $blocked, 'counters' => $C);
    }
}
