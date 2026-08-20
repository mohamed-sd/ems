<?php
/**
 * includes/space_scope.php — مساحةُ العملِ الفعّالةُ نطاقٌ حاكمٌ لا مرشِّحُ عرض
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب (رابعًا): «المنعُ مقيَّدٌ بسياقِ المساحة: **`user + active_scope +
 *   route` لا `user + route`**. فمن يملك الشاشةَ في إدارتِها المالكةِ يبلغها
 *   بتبديلِ المساحة — **ومنعُها عالميًّا يكسر صلاحيةً مشروعة**».
 *   و(خامسًا): «تبديلُ المساحةِ **يُعيد نطاقَ ثمانيةٍ** لا السايدبارَ وحدَه».
 *
 * ◆ **ولم يكن في النظامِ سياقُ مساحةٍ أصلًا**: بحثُ الشيفرةِ الحيةِ عن
 *   `active_scope` أعطى صفرًا. فهذا الملفُّ يُنشئه — **ولا يُمنع شيءٌ قبلَه**،
 *   لأن منعًا بلا سياقٍ منعٌ عالميٌّ وهو ما نُهيت عنه حرفًا.
 *
 * ◆ **والمساحةُ الافتراضيةُ مساحةُ الدورِ نفسِه** (`gov_space_roles` · مقيسةٌ
 *   بتقاطعِ المساراتِ المُصيَّرةِ بثقةِ 100٪ لثمانيَ عشرةَ مساحة). فمن لم يبدّلْ
 *   شيئًا يعمل في مساحتِه كما كان — **والتبديلُ فعلٌ صريحٌ لا حالةٌ ضمنية**.
 *
 * ◆ **وعقدُ التبديلِ يُبطل ثمانيةً** (خامسًا): السايدبار · اللوحة · البحثُ العام ·
 *   المناظرُ المحفوظة · المرشِّحاتُ الافتراضية · طوابيرُ العمل · المناظرُ المقيَّدة ·
 *   ذاكرةُ الصفحةِ الأخيرة. **وأيُّ مكوِّنٍ يقرأ نطاقَه مرةً عند أولِ تحميلٍ ولا
 *   يُعيد قراءتَه عند التبديلِ عيبٌ يُرسِّب البناء.**
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_scope_conn')) {
    function ems_scope_conn() {
        return isset($GLOBALS['conn']) && $GLOBALS['conn'] ? $GLOBALS['conn'] : null;
    }
}

if (!function_exists('ems_scope_home')) {
    /** مساحةُ الدورِ الأصليةُ — من الربطِ المقيسِ لا من اسمٍ مشابه. */
    function ems_scope_home($roleId = null) {
        static $cache = array();
        $conn = ems_scope_conn();
        if (!$conn) { return ''; }
        if ($roleId === null) {
            $roleId = isset($_SESSION['user']['role']) ? (int) $_SESSION['user']['role'] : 0;
        }
        $roleId = (int) $roleId;
        if (isset($cache[$roleId])) { return $cache[$roleId]; }
        $st = mysqli_prepare($conn, "SELECT space_ar FROM gov_space_roles WHERE role_id = ? LIMIT 1");
        if (!$st) { return ''; }
        mysqli_stmt_bind_param($st, 'i', $roleId);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $row = $res ? mysqli_fetch_row($res) : null;
        mysqli_stmt_close($st);
        $cache[$roleId] = $row ? (string) $row[0] : '';
        return $cache[$roleId];
    }
}

if (!function_exists('ems_scope_allowed')) {
    /**
     * المساحاتُ التي يجوز لهذا الدورِ التبديلُ إليها.
     *
     * ◆ **مساحتُه هي وحدَها بلا منحٍ صريح** — والتوسيعُ قرارُ حوكمةٍ لا راحةُ
     *   تنقّل. فبابُ التبديلِ لو فُتح على مصراعَيه صار العزلُ زينةً.
     */
    function ems_scope_allowed($roleId = null) {
        $home = ems_scope_home($roleId);
        return $home === '' ? array() : array($home);
    }
}

if (!function_exists('ems_active_scope')) {
    /** المساحةُ الفعّالةُ الآن — الجلسةُ إن صحّت، وإلا مساحةُ الدور. */
    function ems_active_scope() {
        $home = ems_scope_home();
        if (!isset($_SESSION['active_scope'])) { return $home; }
        $cur = (string) $_SESSION['active_scope'];
        /* ◆ لا تُقبل مساحةٌ من الجلسةِ بلا تحقّقٍ: جلسةٌ محقونةٌ تُبدّل النطاقَ
             بلا حقّ. **والقيمةُ المخزَّنةُ مدخلٌ لا حقيقة.** */
        return in_array($cur, ems_scope_allowed(), true) ? $cur : $home;
    }
}

if (!function_exists('ems_scope_switch')) {
    /**
     * تبديلُ المساحةِ — **ويُبطل نطاقَ الثمانيةِ كلِّها** (خامسًا).
     * @return bool false إن لم تكن المساحةُ مسموحةً لهذا الدور.
     */
    function ems_scope_switch($space) {
        $space = trim((string) $space);
        if (!in_array($space, ems_scope_allowed(), true)) { return false; }
        $_SESSION['active_scope'] = $space;

        /* ① السايدبار · ② اللوحة · ③ البحثُ العام · ④ المناظرُ المحفوظة ·
           ⑤ المرشِّحاتُ الافتراضية · ⑥ طوابيرُ العمل · ⑦ المناظرُ المقيَّدة ·
           ⑧ ذاكرةُ الصفحةِ الأخيرة — **تُبطَل كلُّها ولا يُقرأ نطاقٌ من ذاكرةٍ سابقة**. */
        foreach (array('nav_cache', 'board_cache', 'search_scope', 'saved_views',
                       'default_filters', 'work_queues', 'restricted_views', 'last_page') as $k) {
            unset($_SESSION[$k]);
        }
        $_SESSION['scope_epoch'] = isset($_SESSION['scope_epoch']) ? ((int) $_SESSION['scope_epoch'] + 1) : 1;
        if (function_exists('ems_nav_mark_printed')) { ems_nav_mark_printed('', true); }
        return true;
    }
}

if (!function_exists('ems_scope_forbids')) {
    /**
     * أممنوعٌ هذا المسارُ **في المساحةِ الفعّالةِ**؟ — `user + active_scope + route`.
     *
     * ◆ **ولا يُمنع مسارٌ عالميًّا**: الشاشةُ الممنوعةُ في مساحةٍ مسموحةٌ في
     *   مساحتِها المالكة. فالسؤالُ دائمًا «هنا» لا «مطلقًا».
     * ◆ **والغيابُ ليس منعًا**: مسارٌ لا صفَّ له في اللقطةِ **يُسمح** — فبوابةٌ
     *   تمنع ما لا تعرفه تكسر النظامَ بصمتٍ عند أولِ شاشةٍ جديدة.
     */
    function ems_scope_forbids($route, $space = null) {
        $conn = ems_scope_conn();
        if (!$conn) { return false; }
        $route = ltrim(preg_replace('/[?#].*$/', '', (string) $route), '/');
        if ($route === '') { return false; }
        if ($space === null) { $space = ems_active_scope(); }
        if ($space === '') { return false; }
        $st = mysqli_prepare($conn, "SELECT 1 FROM gov_space_appearances
                                      WHERE space_ar = ? AND LOWER(route) = LOWER(?)
                                        AND cls = 'FORBIDDEN' LIMIT 1");
        if (!$st) { return false; }
        mysqli_stmt_bind_param($st, 'ss', $space, $route);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $hit = $res ? mysqli_fetch_row($res) : null;
        mysqli_stmt_close($st);
        return $hit !== null;
    }
}

if (!function_exists('ems_scope_forbidden_set')) {
    /**
     * كلُّ ممنوعِ مساحةٍ في استعلامٍ واحد — لترشيحِ السايدبار.
     *
     * ◆ **استعلامٌ واحدٌ لا استعلامٌ لكلِّ رابط**: سايدبارٌ بمئةِ رابطٍ يعني مئةَ
     *   ذهابٍ وإيابٍ لو سُئل عن كلٍّ على حدة — وبطءُ التنقلِ يُغري بتعطيلِ البوابة.
     */
    function ems_scope_forbidden_set($space = null) {
        static $cache = array();
        $conn = ems_scope_conn();
        if (!$conn) { return array(); }
        if ($space === null) { $space = ems_active_scope(); }
        if ($space === '') { return array(); }
        if (isset($cache[$space])) { return $cache[$space]; }
        $out = array();
        $st = mysqli_prepare($conn, "SELECT LOWER(route) FROM gov_space_appearances
                                      WHERE space_ar = ? AND cls = 'FORBIDDEN'");
        if ($st) {
            mysqli_stmt_bind_param($st, 's', $space);
            mysqli_stmt_execute($st);
            $res = mysqli_stmt_get_result($st);
            while ($res && ($row = mysqli_fetch_row($res))) { $out[$row[0]] = 1; }
            mysqli_stmt_close($st);
        }
        $cache[$space] = $out;
        return $out;
    }
}

if (!function_exists('ems_scope_class')) {
    /** صنفُ ظهورِ المسارِ في المساحةِ الفعّالة — أو '' إن لم يكن مسجَّلًا. */
    function ems_scope_class($route, $space = null) {
        $conn = ems_scope_conn();
        if (!$conn) { return ''; }
        $route = ltrim(preg_replace('/[?#].*$/', '', (string) $route), '/');
        if ($space === null) { $space = ems_active_scope(); }
        if ($route === '' || $space === '') { return ''; }
        $st = mysqli_prepare($conn, "SELECT cls FROM gov_space_appearances
                                      WHERE space_ar = ? AND LOWER(route) = LOWER(?) LIMIT 1");
        if (!$st) { return ''; }
        mysqli_stmt_bind_param($st, 'ss', $space, $route);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $row = $res ? mysqli_fetch_row($res) : null;
        mysqli_stmt_close($st);
        return $row ? (string) $row[0] : '';
    }
}
