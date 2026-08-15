<?php
/**
 * includes/my_workspace_counts.php — عدَّادُ مساحةِ عملي: تعريفٌ واحدٌ لثلاثةِ قرّاء
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0407 · INJ-0581
 *
 * ── العلّتان ──────────────────────────────────────────────────────────────
 * · **الشارةُ تعدُّ الشركةَ لا المستخدم (0407)**: `includes/topbar.php` كان
 *   يحسب شارةَ «مساحة عملي» بـ`ApprovalsInboxService::inbox($conn, $company_id)`
 *   — بوسيطِ الكيانِ **وحدَه بلا مستخدم**. فكلُّ من في الكيانِ يرى الرقمَ نفسَه،
 *   وهو رقمُ الشركةِ لا رقمُه. ويُخبَّأ خمسَ دقائقَ فيتأخر عن الواقعِ فوق ذلك.
 *   ونصُّ القبول: «حسابان في الكيانِ نفسِه يريان **رقمين مختلفين** مطابقين
 *   لمجموعِ بلاطاتِ موافقاتي + مهامي لكلٍّ منهما».
 *
 * · **العدَّادُ يجمع ما لا تعرضه شاشتُه (0581)**: بلاطةُ «طلباتي» كانت تجمع
 *   `requests` و`fin_requests`، وتفتح `Portal/my_requests.php` التي تعرض
 *   الأولَ وحدَه. **فلا شاشةَ تعرض ما يعدُّه العدّاد.**
 *   ونصُّ القبول: «رقمُ شارةِ طلباتي = عددُ الصفوفِ في الشاشةِ التي تفتحها
 *   البلاطةُ **بالضبط**، لكلِّ مستخدم».
 *
 * ── القاعدةُ التي تحكم ────────────────────────────────────────────────────
 * **عدّادٌ وعارضٌ في ملفَّين يتفرّقان دائمًا.** فالتعريفُ هنا واحدٌ، وتقرؤه
 * الشاشةُ والبلاطةُ والشارةُ معًا — ولا يُكتب شرطُ عدٍّ ثانٍ في أيٍّ منها.
 *
 * ◆ والماليةُ لا تُخفى: تُعدُّ بدالّتها ويُعرض عددُها **برابطٍ إلى شاشتِها** —
 *   فلا تُجمع في رقمٍ لا شاشةَ له ولا تسقط من نظرِ صاحبِها.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_wsc_one')) {
    /** عددٌ واحدٌ — ورسوبُ الاستعلامِ يُرجع −1 لا صفرًا (فالفشلُ ليس خلوًّا). */
    function ems_wsc_one(mysqli $conn, $sql)
    {
        $r = mysqli_query($conn, $sql);
        if (!$r) { return -1; }
        $x = mysqli_fetch_assoc($r);
        return $x ? (int) reset($x) : 0;
    }
}

if (!function_exists('ems_my_requests_where')) {
    /**
     * شرطُ «طلباتي» — **مرآةُ ما تعرضه `Portal/my_requests.php` في جدولِ
     * «طلباتي»**: طلبٌ أنا طالبُه، في نطاقِ كياني. لا شرطَ حالةٍ هنا لأنَّ
     * الشاشةَ لا تُصفّي بالحالة — والعدُّ يتبع العرضَ لا العكس.
     */
    function ems_my_requests_where($co, $uid)
    {
        $co = (int) $co; $uid = (int) $uid;
        return "rq.company_id = {$co} AND rq.requester_user_id = {$uid}";
    }
}

if (!function_exists('ems_my_requests_count')) {
    /** عددُ صفوفِ «طلباتي» كما تعرضها شاشتُها بالضبط. */
    function ems_my_requests_count(mysqli $conn, $co, $uid)
    {
        return ems_wsc_one($conn,
            'SELECT COUNT(*) n FROM requests rq WHERE ' . ems_my_requests_where($co, $uid));
    }
}

if (!function_exists('ems_my_fin_requests_count')) {
    /** عددُ طلباتي **المالية** — شاشتُها `FinRequests/my_requests.php`. */
    function ems_my_fin_requests_count(mysqli $conn, $co, $uid)
    {
        $co = (int) $co; $uid = (int) $uid;
        return ems_wsc_one($conn,
            "SELECT COUNT(*) n FROM fin_requests
              WHERE company_id = {$co} AND (created_by = {$uid} OR requester_id = {$uid})");
    }
}

if (!function_exists('ems_my_tasks_count')) {
    /** «مهامي» — ما ينتظر تنفيذي أنا. */
    function ems_my_tasks_count(mysqli $conn, $co, $uid)
    {
        $co = (int) $co; $uid = (int) $uid;
        return ems_wsc_one($conn,
            "SELECT COUNT(*) n FROM work_items
              WHERE company_id = {$co} AND assigned_user_id = {$uid}
                AND status NOT IN ('closed_accepted','cancelled','rejected')");
    }
}

if (!function_exists('ems_my_approvals_count')) {
    /** «موافقاتي» — من صندوقِ الاعتمادِ الموحَّدِ نفسِه، لا بنصٍّ ثانٍ. */
    function ems_my_approvals_count(mysqli $conn, $co, $uid, $role)
    {
        require_once __DIR__ . '/approvals_inbox_scope.php';
        $x = ems_approvals_inbox_counts($conn, (int) $co, (int) $uid, (string) $role,
            (string) $role === '-1');
        return isset($x['total']) ? (int) $x['total'] : 0;
    }
}

if (!function_exists('ems_workspace_badge')) {
    /**
     * شارةُ بابِ «مساحة عملي» = **موافقاتي + مهامي** لهذا المستخدمِ بعينِه.
     * وهي بالضبط مجموعُ بلاطتَي المساحةِ الأوليين — نصُّ القبولِ حرفًا.
     * @return int
     */
    function ems_workspace_badge(mysqli $conn, $co, $uid, $role)
    {
        if ((int) $co <= 0 || (int) $uid <= 0) { return 0; }
        $a = ems_my_approvals_count($conn, $co, $uid, $role);
        $t = ems_my_tasks_count($conn, $co, $uid);
        return max(0, $a) + max(0, $t);
    }
}
