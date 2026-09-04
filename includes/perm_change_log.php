<?php
/**
 * includes/perm_change_log.php — كاتبُ أثرِ تغييرِ الصلاحية (PERM-01 §7-⑤)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **منفذٌ واحدٌ للكتابةِ في الأثر**: كلُّ من يغيّر صلاحيّةً — شاشةً كان أو
 *   أداةً أو هجرة — يمرُّ بهذه الدالّة. فسطرُ الأثرِ لا يتفرّق شكلُه بتفرُّقِ
 *   كاتبِه، والسؤالُ «من غيّر ومتى ولماذا» له جوابٌ واحدٌ لا عدّة.
 *
 * ⛔ **ولا يبتلع فشلَه صامتًا**: أثرٌ لا يُكتب أسوأُ من غيابِه، لأنّه يُقرأ
 *   «لم يقع تغييرٌ» وقد وقع. فالفشلُ يُسجَّل في سجلِّ الأخطاءِ ويُرجع `false`،
 *   ويقرّر المنادي أيمضي أم يردّ.
 *
 * ◆ **والفاعلُ من الجلسةِ إن وُجدت، وصفرٌ يعني هجرةً أو أداة** — ولا يُخترع.
 */

if (!function_exists('ems_perm_change_log')) {
    /**
     * @param string $layer   role_permissions | profile_item | grant | profile_state
     * @param string $verb    insert | update | delete | revoke | activate | retire
     * @param array  $ctx     subject_kind · subject_id · screen_code · before · after · reason · source
     * @return bool
     */
    function ems_perm_change_log(\mysqli $conn, $layer, $verb, array $ctx = array())
    {
        static $available = null;
        if ($available === null) {
            $r = @$conn->query("SELECT 1 FROM information_schema.TABLES
                                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perm_change_log' LIMIT 1");
            $available = ($r && $r->num_rows > 0);
        }
        if (!$available) { return false; }

        $co   = isset($_SESSION['user']['company_id']) ? (int) $_SESSION['user']['company_id'] : 0;
        $uid  = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
        $role = isset($_SESSION['user']['role']) ? (string) $_SESSION['user']['role'] : '';
        $g = function ($k, $d = '') use ($ctx) { return isset($ctx[$k]) ? $ctx[$k] : $d; };

        /* ⛔ **والكتابةُ بالبوّابةِ لا باستعلامٍ خامّ** (ADR-02 · سقّاطة GAP-29):
             كان هنا `INSERT` نصِّيٌّ على جدولِ مستأجِرٍ فرفعَ السقّاطةَ ملفًّا
             فوقَ أساسِها — والأساسُ **يُخفَّض ولا يُرفع**. والبوّابةُ تحقن
             `company_id` من الجلسةِ فلا يُمرَّر يدويًّا. */
        try {
            $data = array(
                'actor_user_id' => $uid,
                'actor_role'    => $role,
                'layer'         => mb_substr((string) $layer, 0, 24),
                'verb'          => mb_substr((string) $verb, 0, 16),
                'subject_kind'  => mb_substr((string) $g('subject_kind'), 0, 16),
                'subject_id'    => (int) $g('subject_id', 0),
                'screen_code'   => mb_substr((string) $g('screen_code'), 0, 160),
                'before_val'    => mb_substr((string) $g('before'), 0, 255),
                'after_val'     => mb_substr((string) $g('after'), 0, 255),
                'reason'        => mb_substr((string) $g('reason'), 0, 255),
                'source_screen' => mb_substr((string) $g('source',
                    isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'cli'), 0, 160),
            );
            /* ◆ والمجالُ يُصرَّح به في سياقِ CLI (هجرةٌ أو أداةٌ بلا جلسة) —
                 ولا يُخترع: `company_id` صفرٌ يعني «بلا مستأجِرٍ مُعلَن». */
            if ($co > 0) { $data['company_id'] = $co; }
            ems_tenant_db()->insert('perm_change_log', $data);
            return true;
        } catch (\Throwable $t) {
            if (function_exists('ems_catch_log')) { ems_catch_log($t, __FUNCTION__); }
            return false;
        }
    }
}
