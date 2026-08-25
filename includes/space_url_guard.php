<?php
/**
 * includes/space_url_guard.php
 *   FR-SEC-008 — العنوانُ المباشرُ يسأل عن مساحتِه · ظلٌّ ثمّ إنفاذ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ مقيسٌ من هذه الجولةِ لا مفترَض**: الدورُ 4 يفتح
 *   `Equipments/equipments.php` بـHTTP 200 **وهو مُعلَنٌ `FORBIDDEN` في مساحتِه**
 *   (`gov_space_appearances`). فأربعُ قنواتٍ تنفّذ العزلَ — السايدبارُ والبحثُ
 *   ونداءاتُ الخلفيةِ والتصديرُ — **والعنوانُ المباشرُ لا يسأل أصلًا**. والعزلُ
 *   الذي يُلتفُّ عليه بكتابةِ الرابطِ في شريطِ العنوانِ ليس عزلًا.
 *
 * ◆ **ولا يُنشأ محرِّكٌ جديد**: يُنادى `ems_scope_forbids()` — **نقطةُ القرارِ
 *   نفسُها** التي تناديها القنواتُ الأربع. فهذا **وصلٌ لمبنيٍّ غيرِ موصول**
 *   لا سلطةٌ جديدة، ولا قارئَ قرارٍ سادسًا يُضاف (FR-SEC-003).
 *
 * ◆ **والإنفاذُ لا يُفتَح بضغطةٍ واحدةٍ على 265 ظهورًا ممنوعًا**: تبديلُ المنعِ
 *   دفعةً **تغييرُ وصولٍ حيٍّ** يقرّره مالكُ المجالِ لا منفِّذ. فالوضعُ الافتراضيُّ
 *   **ظلٌّ**: يُقاس ما كان سيُمنَع ويُكتب في `gov_space_url_shadow` **ولا يُمنع
 *   أحد**. والقلبُ إلى `enforce` بمتغيّرِ بيئةٍ مُعلَنٍ بعدَ قراءةِ الأثرِ المقيس.
 *   وهو النمطُ نفسُه المعتمَدُ في هذه الحزمة (`gov_ladder_shadow` · FR-APP-001).
 *
 * ◆ **والغيابُ ليس منعًا**: مسارٌ لا صفَّ له في اللقطةِ يمرُّ — فبوابةٌ تمنع ما
 *   لا تعرفه تكسر النظامَ بصمتٍ عندَ أوّلِ شاشةٍ جديدة.
 *
 * ◆ **ولا يبتلع فشلَه صامتًا ولا يوقف الشاشة**: الملاحظةُ في `try` واسعٍ
 *   **بقرارٍ صريح** — فحارسٌ يُسقِط صفحةً لأن سجلَّ الظلِّ تعذّر أسوأُ من
 *   العطبِ الذي يرصده. والإنفاذُ وحدَه يمنع، والملاحظةُ لا.
 *
 * التشغيل: يُحمَّل من `config.php` لكلِّ صفحةٍ غيرِ CLI.
 * التحويل: `EMS_SPACE_URL_GUARD=enforce` في البيئة (الافتراضيّ `observe`).
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_space_url_guard')) {

    /** المسارُ الحاليُّ نسبةً إلى جذرِ التطبيقِ — كما يُخزَّن في `gov_space_appearances`. */
    function ems_space_url_route()
    {
        $s = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
        if ($s === '') { return ''; }
        $s = str_replace('\\', '/', $s);
        /* الجذرُ قد يكون `/ems` أو `/` — يُقتطع ما قبلَ آخرِ مجلَّدَين */
        $parts = array_values(array_filter(explode('/', $s), 'strlen'));
        $n = count($parts);
        if ($n === 0) { return ''; }
        if ($n === 1) { return $parts[0]; }
        return $parts[$n - 2] . '/' . $parts[$n - 1];
    }

    /**
     * @return string '' إن مرّ · اسمُ الوضعِ إن رُصد ('observe'|'enforce')
     */
    function ems_space_url_guard()
    {
        if (php_sapi_name() === 'cli') { return ''; }
        if (!isset($_SESSION['user'])) { return ''; }
        $role = isset($_SESSION['user']['role']) ? (string) $_SESSION['user']['role'] : '';
        if ($role === '' || $role === '-1') { return ''; }   /* المديرُ الأعلى خارجَ العزل */

        $sf = dirname(__DIR__) . '/includes/space_scope.php';
        if (!is_file($sf)) { return ''; }
        require_once $sf;
        if (!function_exists('ems_scope_forbids') || !function_exists('ems_active_scope')) {
            return '';
        }

        $route = ems_space_url_route();
        if ($route === '') { return ''; }
        if (!ems_scope_forbids($route)) { return ''; }

        $mode = getenv('EMS_SPACE_URL_GUARD');
        $mode = ($mode === 'enforce') ? 'enforce' : 'observe';

        /* ◆ الملاحظةُ لا تُسقِط الصفحةَ مهما تعثّرت — بقرارٍ صريحٍ لا بإهمال */
        try {
            $conn = isset($GLOBALS['conn']) ? $GLOBALS['conn'] : null;
            if ($conn) {
                $st = @mysqli_prepare($conn,
                    "INSERT INTO `gov_space_url_shadow`
                       (`route`,`space_ar`,`role_id`,`user_id`,`mode`,`seen_at`)
                     VALUES (?,?,?,?,?,NOW())");
                if ($st) {
                    $space = ems_active_scope();
                    $rid   = (int) $role;
                    $uid   = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
                    mysqli_stmt_bind_param($st, 'ssiis', $route, $space, $rid, $uid, $mode);
                    @mysqli_stmt_execute($st);
                    mysqli_stmt_close($st);
                }
            }
        } catch (\Throwable $t) {
            /* مقصودٌ: حارسٌ يُسقِط صفحةً لأن سجلَّ الظلِّ تعذّر أسوأُ مما يرصد */
        }

        if ($mode === 'enforce') {
            if (!headers_sent()) {
                header('HTTP/1.1 403 Forbidden');
                header('Content-Type: text/html; charset=utf-8');
            }
            echo '<!doctype html><meta charset="utf-8">'
               . '<div style="font-family:system-ui;padding:2rem;text-align:center">'
               . '<h2>هذه الشاشة تخص مساحة عمل أخرى</h2>'
               . '<p>بدل المساحة للوصول إليها.</p></div>';
            exit;
        }
        return $mode;
    }
}
