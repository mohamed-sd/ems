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
                /* §11 — **ورأسُه الفرعيُّ من مجموعةِ `WS-MY`** لا من دورةِ المضيف؛
                   وما لا مجموعةَ له يصعد إلى صدرِ «مساحتي» المكشوف. */
                if ($inclShell) { $personal[] = array('route' => $route, 'label' => $p['canonical_label'],
                                                      'sort_no' => (int) $p['sort_no'],
                                                      'group' => (string) $p['group_label'],
                                                      'group_sort' => (int) $p['group_sort']); }
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
        usort($personal, function ($a, $b) {
            if ($a['group_sort'] !== $b['group_sort']) { return $a['group_sort'] - $b['group_sort']; }
            return $a['sort_no'] - $b['sort_no'];
        });

        return array('workspace' => (string) $wsId, 'role_id' => (int) $roleId,
                     'groups' => array_values($groups), 'shell' => $shell, 'personal' => $personal,
                     'rendered' => $rendered, 'blocked' => $blocked, 'counters' => $C);
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
 * ⭐ طورُ القلبِ (‏§20·§21·§22) — **وصلُ المُصيِّرِ الحاكمِ بالإنتاجِ بمفتاح**
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **والمُصيِّرُ أعلاه كان ظلًّا خالصًا** (§30): يُنتج شجرةً ولا يطبع وسمًا،
 *   فلا شاشةَ حيّةٌ تستدعيه. وما دون طباعةٍ **لا يتغيّر سايدبارُ المستخدم**
 *   [[render-not-store-rule]] — والفرقُ المقيسُ كان **تسعين رابطًا حيًّا مقابلَ
 *   تسعةَ عشرَ موضعًا حاكمًا** في `DEP-11`.
 *
 * ◆ **والقلبُ بمفتاحٍ لا بقطعٍ مباشر** (‏على نمطِ `EMS_NAV_WORKSPACE` القائم):
 *   `EMS_NAV_ARCH` في `.env` — **وافتراضُه `off`** أي المسارُ القديمُ حرفًا.
 *   وقيمتُه إمّا `off` أو `on` (‏كلُّ المساحات) أو **قائمةَ مساحاتٍ بفواصل**
 *   (`DEP-11`) — فيُقلَب الطيّارُ وحدَه ثمَّ يُعمَّم (§27 · §36).
 *
 * ◆ **ولا يُقلَب دورٌ بلا مساحةٍ ولا مواضع**: من لا ربطَ `PRIMARY` له في
 *   `nav_ws_roles`، أو مساحتُه لا تُنتج بندَ دورةٍ واحدًا، **يسلك القديمَ
 *   حرفًا** — فالقلبُ لا يُفرِغ سايدبارًا صامتًا (§35: احجبِ الموضعَ لا البرنامج).
 *
 * ◆ **والقشرةُ والشخصيُّ في مِسمارَيهما** (§10 · §11): المرساتان من
 *   `nav_canonical.anchor_key` كما في المسارِ القديمِ حرفًا، ومواضعُ `WS-MY`
 *   تحتَهما في مجموعةِ «مساحتي» — **ولا تدخل مقامَ دورةِ الإدارة**.
 *
 * ⛔ **و«اختفى من السايدبار» ليس «سُحبت صلاحيّتُه»** (§42): هذا الملفُّ
 *   **لا يمسُّ منحةً ولا يُغلق مسارًا** — والرابطُ المباشرُ يعمل لمن يملك
 *   الصلاحيّةَ والنطاق، وهو ما يُثبته `NT-01`-ب حيًّا.
 * ═══════════════════════════════════════════════════════════════════════════ */

if (!function_exists('navarch_role_workspace')) {
    /**
     * مساحةُ الدورِ الحاكمة — `PRIMARY` أوّلًا ثمَّ `SECONDARY` (§6 · §21-②).
     * ⛔ **ولا يُشتقُّ من الدورِ اسمُ مساحة**: من لا صفَّ له يرجع `null`.
     *
     * ◆ **والمقيسُ قبلَ الحكم**: `PRIMARY` وحدَه كان يربط **ثمانيةَ عشرَ دورًا
     *   من خمسةٍ وثلاثين**، فستةَ عشرَ دورًا نشطًا يحملون **1,031 رابطًا**
     *   يرجعون `null` — **فلا يُقلَبون أبدًا** مهما بلغ المفتاحُ `on`.
     *   وهم كلُّهم أدوارٌ فرعيّةٌ داخلَ إداراتٍ قائمة (`roles.parent_role_id`)
     *   لا إداراتٌ جديدة، و`binding='SECONDARY'` مفردةٌ قائمةٌ في المخطَّطِ
     *   سلفًا. ⇒ **الترتيبُ حتميّ**: `PRIMARY` يغلب، ثمَّ `SECONDARY` بأصغرِ
     *   مُعرِّفِ مساحةٍ — فلا يتأرجح قارئان على صفَّين.
     */
    function navarch_role_workspace($conn, $roleId)
    {
        static $byRole = array();
        $rid = (int) $roleId;
        if (array_key_exists($rid, $byRole)) { return $byRole[$rid]; }
        $byRole[$rid] = null;
        $q = @mysqli_query($conn, "SELECT wr.workspace_id
                                     FROM nav_ws_roles wr
                                     JOIN nav_workspaces w ON w.workspace_id = wr.workspace_id
                                                          AND w.active = 1
                                    WHERE wr.role_id = {$rid}
                                      AND wr.binding IN ('PRIMARY','SECONDARY')
                                    ORDER BY (wr.binding = 'PRIMARY') DESC, wr.workspace_id ASC
                                    LIMIT 1");
        if ($q && ($x = mysqli_fetch_row($q))) { $byRole[$rid] = (string) $x[0]; }
        return $byRole[$rid];
    }
}

if (!function_exists('navarch_cutover_workspace')) {
    /**
     * هل يُصيَّر هذا الدورُ بالمُصيِّرِ الحاكم؟ ⇒ مُعرِّفُ مساحتِه، وإلّا `null`.
     * `EMS_NAV_ARCH`: `off` (‏الافتراض) · `on` · قائمةُ مساحاتٍ بفواصل.
     */
    function navarch_cutover_workspace($conn, $roleId)
    {
        if (!function_exists('ems_env')) { return null; }
        $flag = trim((string) ems_env('EMS_NAV_ARCH', 'off'));
        if ($flag === '' || strtolower($flag) === 'off') { return null; }
        $ws = navarch_role_workspace($conn, $roleId);
        if ($ws === null) { return null; }
        if (strtolower($flag) === 'on') { return $ws; }
        foreach (explode(',', $flag) as $one) {
            if (trim($one) === $ws) { return $ws; }
        }
        return null;
    }
}

if (!function_exists('navarch_proper_route')) {
    /**
     * المسارُ **بحرفِه المعياريِّ** من `nav_canonical` — فصفُّ الموضعِ يخزّن
     * المسارَ **مُسوًّى** (‏بلا لاحقةٍ وبأحرفٍ صغيرة) وهو مفتاحُ مطابقةٍ لا وجهة.
     * ⛔ **ولا يُصطنع مسارٌ لا ملفَّ له**: ما لا صفَّ له ولا ملفَّ يرجع `''`
     *   فيُحجَب بندُه — بدلَ رابطٍ مكسورٍ يقع عليه المستخدم.
     */
    function navarch_proper_route($conn, $norm)
    {
        static $map = null;
        if ($map === null) {
            $map = array();
            $r = @mysqli_query($conn, "SELECT route FROM nav_canonical
                                        WHERE route IS NOT NULL AND route <> ''");
            while ($r && ($x = mysqli_fetch_row($r))) { $map[navarch_norm_route($x[0])] = (string) $x[0]; }
        }
        if (isset($map[$norm])) { return $map[$norm]; }
        if (is_file(dirname(__DIR__) . '/' . $norm . '.php')) { return $norm . '.php'; }
        return '';
    }
}

if (!function_exists('navarch_print_sidebar')) {
    /**
     * **يطبع سايدبارَ المساحةِ من المواضعِ الحاكمةِ وحدَها** — بوسمِ المُصيِّرِ
     * القديمِ نفسِه (`nav-group` · `nav-group-head` · `nav-group-items`)
     * فلا يتغيّر شكلٌ ولا سلوكُ طيٍّ ولا مُعرِّفُ حفظِ حالةٍ في المتصفّح.
     *
     * @return bool هل طُبع شيء؟ (`false` ⇒ يسلك النداءُ المسارَ القديمَ حرفًا)
     */
    function navarch_print_sidebar($conn, $wsId, $roleId, $basePrefix = '../',
                                   $badges = array(), $afterHome = '')
    {
        require_once __DIR__ . '/nav_icon_map.php';
        require_once __DIR__ . '/nav_anchors.php';

        $tree = navarch_render($conn, $wsId, $roleId);

        /* ⛔ **مساحةٌ لا تُنتج بندَ دورةٍ واحدًا لا تُقلَب**: قلبٌ يُفرِغ سايدبارًا
           عطبٌ لا إصلاح — يُردُّ للمسارِ القديمِ ويُقيَّد كشفٌ باسمِه (§35). */
        if ($tree['rendered'] === 0) {
            if (function_exists('navrRecordFinding')) {
                navrRecordFinding($conn, 'CUTOVER_EMPTY_LIFECYCLE', (int) $roleId, $wsId,
                    'المصير الحاكم لا ينتج بند دورة واحدا لهذه المساحة، رد للمسار القديم');
            }
            return false;
        }

        /* ── ① «مساحتي»: المرساتان (§10) ثمَّ مواضعُ `WS-MY` (§11) ───────────
              وهي **خارجَ مقامِ دورةِ الإدارة** — يقيسها `NT-06` و`NT-07`. */
        ob_start();
        $aHome = ems_nav_anchor($conn, 'HOME');
        if ($aHome !== null) {
            printNavLinkItem(array('code' => $aHome['route'], 'name' => $aHome['label'],
                                   'icon' => $aHome['icon']), $basePrefix, $badges);
        }
        $aChat = ems_nav_anchor($conn, 'CHATS');
        if ($aChat !== null) {
            ems_nav_mark_printed($aChat['route'] . '||' . $aChat['label']);
            echo ($afterHome !== '') ? $afterHome
                : ems_nav_anchor_li($conn, 'CHATS', $basePrefix, 'id="sidebarChatLink"',
                      '<span id="nav-unread-badge" class="nav-count-badge" style="display:none;"></span>');
        }
        /* §11 — الشخصيُّ برؤوسِ `WS-MY` الفرعيّة: المفردُ وبلا مجموعةٍ يصعد
           إلى الصدرِ المكشوف، والباقي تحتَ عنوانِه — بالقاعدةِ نفسِها التي
           يطبّقها المُصيِّرُ القديمُ (‏عنوانٌ لرابطٍ واحدٍ ضجيجٌ لا بناء). */
        $pBuckets = array(); $pLead = array();
        foreach ($tree['personal'] as $p) {
            $rt = navarch_proper_route($conn, $p['route']);
            if ($rt === '') { continue; }
            $g = trim((string) $p['group']);
            if ($g === '') { $pLead[] = array($rt, $p['label']); }
            else { $pBuckets[$g][] = array($rt, $p['label']); }
        }
        foreach ($pBuckets as $g => $ks) {
            if (count($ks) >= 2) { continue; }
            foreach ($ks as $k) { $pLead[] = $k; }
            unset($pBuckets[$g]);
        }
        foreach ($pLead as $k) {
            printNavLinkItem(array('code' => $k[0], 'name' => $k[1],
                                   'icon' => ems_nav_icon_for($k[1], $k[0])), $basePrefix, $badges);
        }
        foreach ($pBuckets as $g => $ks) {
            ob_start();
            foreach ($ks as $k) {
                printNavLinkItem(array('code' => $k[0], 'name' => $k[1],
                                       'icon' => ems_nav_icon_for($k[1], $k[0])), $basePrefix, $badges);
            }
            $sub = ob_get_clean();
            if (!ems_nav_group_has_link($sub)) { continue; }   /* لا عنوانَ بلا روابط */
            echo '<li class="nav-subhead" aria-hidden="true"><span>'
               . htmlspecialchars($g, ENT_QUOTES, 'UTF-8') . '</span></li>' . "
";
            echo $sub;
        }
        $mineBody = ob_get_clean();

        /* ── ② مجموعاتُ الدورةِ بترتيبِها الحاكمِ — ⛔ ولا مجموعةَ افتراضيّة ── */
        $blocks = array();
        if (ems_nav_group_has_link($mineBody)) {
            $blocks[] = array('key' => 'g-mine', 'name' => 'مساحتي',
                              'icon' => 'fa fa-user', 'body' => $mineBody, 'badge' => 0);
        }
        foreach ($tree['groups'] as $g) {
            ob_start();
            $bsum = 0;
            foreach ($g['items'] as $i) {
                $rt = navarch_proper_route($conn, $i['route']);
                if ($rt === '') { continue; }
                $bsum += printNavLinkItem(array('code' => $rt, 'name' => $i['label'],
                                                'icon' => ems_nav_icon_for($i['label'], $rt)),
                                          $basePrefix, $badges);
            }
            $body = ob_get_clean();
            if (!ems_nav_group_has_link($body)) { continue; }   /* لا غلافَ لمجموعةٍ خلَت */
            $blocks[] = array('key' => 'g-navarch-' . (int) $g['group_id'], 'name' => $g['label'],
                              'icon' => ems_nav_stage_icon((int) $g['sort_no'], $g['label']),
                              'body' => $body, 'badge' => $bsum);
        }
        if (empty($blocks)) { return false; }

        /* ── ③ الطباعة: كلُّ مجموعةٍ مطويّةٌ أوّلَ زيارةٍ — قرارُ المالك 2026-08-17 */
        foreach ($blocks as $B) {
            $nm = htmlspecialchars($B['name'], ENT_QUOTES, 'UTF-8');
            $badge = $B['badge'] > 0
                ? ' <span class="nav-count-badge nav-group-badge">'
                  . ($B['badge'] > 99 ? '99+' : $B['badge']) . '</span>' : '';
            echo '<li class="nav-group" data-group-key="' . $B['key'] . '">' . "\n";
            echo '  <button type="button" class="nav-group-head" aria-expanded="false"'
               . ' aria-controls="navgrp-' . $B['key'] . '" aria-label="' . $nm . '" title="' . $nm . '">'
               . '<i class="' . htmlspecialchars($B['icon'], ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i> '
               . '<span class="nav-group-name">' . $nm . '</span>' . $badge
               . '<i class="fa fa-chevron-down nav-group-caret" aria-hidden="true"></i></button>' . "\n";
            echo '  <ul class="nav-group-items" id="navgrp-' . $B['key'] . '">' . "\n";
            echo $B['body'];
            echo '  </ul>' . "\n" . '</li>' . "\n";
        }
        return true;
    }
}
