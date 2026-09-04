<?php
require_once __DIR__ . '/permissions_helper.php'; // FINAL_CLOSE ⑦ — المصدر الواحد
/**
 * includes/perm_explain_live.php — «لماذا أرى هذا؟ ولماذا لا أراه؟»
 * ───────────────────────────────────────────────────────────────────────────
 * E-04 SEC-001-د: «من سأل «لماذا يملك فلانٌ هذه الصلاحية؟» وجد جوابًا **مركَّبًا
 * من هذه العناصر** لا «مُنحت له»» · وSEC-031-ي: «يُعرض كلُّ مصدرٍ بحكمه
 * والنتيجةُ النهائية».
 *
 * ⛔ **والشرحُ يُنادي مسارَ القرارِ ولا يحاكيه** (PERM-01 §7-③): كان هذا الملفُّ
 *   يعيد بناءَ الحكمِ من `modules` × `role_permissions` وحدَهما — وذلك وصفٌ
 *   لطبقةٍ **لم تعد تحكم أحدًا**: التغطيةُ بالقوالبِ 75 من 75، والقرارُ يقع في
 *   `gov_profile_items`. فكان المفسِّرُ يقول «مسموح» حيث يمنع الحارسُ والعكس،
 *   **وهو أسوأُ من لا تفسير**: جوابٌ واثقٌ خاطئ.
 *   فصار يُنادي `ems_permission_trace` — أي `get_module_permissions` نفسَها
 *   بخيارِ التتبّع. ولا يمكن للشرحِ أن ينحرف عن الحكمِ لأنّهما **نداءٌ واحد**.
 *
 * ◆ **والحبّةُ مستخدمٌ لا دور**: المنحُ في GOV-AUTH-01 يقع على **الفاعل**
 *   (`gov_authority_grants.user_id`)، فدوران في القالبِ نفسِه قد يفترق
 *   حكمُهما بمنحةٍ. وسؤالٌ بالدورِ وحدَه لا جوابَ له إلا بالتقريب.
 */

if (!function_exists('ems_explain_screen_access')) {
    /**
     * تفسيرُ وصولِ **مستخدمٍ بعينِه** إلى شاشة — من مسارِ القرارِ نفسِه.
     *
     * @param int    $userId     الفاعلُ المسؤولُ عنه السؤال
     * @param string $screenCode رمزُ الشاشةِ كما في `modules.code`
     * @return array{allowed:bool, reason:string, chain:array<int,array{step:string,verdict:string}>,
     *               grantor:?string, module_id:?int, subject:?array}
     */
    function ems_explain_screen_access(mysqli $conn, $userId, $screenCode)
    {
        $userId = (int) $userId;
        $chain  = array();

        /* ① أالشاشةُ مسجَّلةٌ أصلًا؟ — وغيرُ المسجَّلةِ تُرفض منذ 2026-08-05،
              والقرارُ يحتاج معرِّفَ وحدةٍ فلا بدَّ من حلِّها أوّلًا. */
        $e = mysqli_real_escape_string($conn, (string) $screenCode);
        $mod = null;
        $r = mysqli_query($conn, "SELECT id, name, owner_role_id FROM modules WHERE code='{$e}' LIMIT 1");
        if ($r && ($x = mysqli_fetch_assoc($r))) { $mod = $x; }
        if (!$mod) {
            $chain[] = array('step' => 'تسجيل الشاشة', 'verdict' => '✘ غير مسجلة في سجل الشاشات');
            return array('allowed' => false,
                'reason' => 'الشاشة غير مسجلة — فلا تمنح صلاحيتها لأحد. تسجيلها من إدارة الصلاحيات.',
                'chain' => $chain, 'grantor' => 'إدارة الصلاحيات (الدور 15)',
                'module_id' => null, 'subject' => null);
        }
        $mid = (int) $mod['id'];
        $chain[] = array('step' => 'تسجيل الشاشة', 'verdict' => '✔ مسجلة باسم «' . $mod['name'] . '»');

        /* ② **الحكمُ من مسارِ القرارِ نفسِه** — لا استعلامٌ موازٍ. */
        $t = ems_permission_trace($conn, $mid, $userId);
        foreach ($t['chain'] as $step) {
            $chain[] = array('step' => $step['step'],
                'verdict' => $step['verdict'] . ($step['detail'] !== '' ? '، ' . $step['detail'] : ''));
        }

        /* ③ وضعُ الانتقالِ يُعرَض صراحةً — فهو يفسّر «لماذا لم يسقط إلى القديم». */
        if (function_exists('ems_auth_mode') && $userId > 0) {
            $modeLbl = array('canonical' => 'معياري منفذ، لا يسقط الى الجدول القديم',
                             'shadow' => 'ظل معياري، يقاس ولا ينفذ',
                             'legacy' => 'قديم، يحكمه جدول صلاحيات الدور',
                             'none' => 'بلا وضع معلن', 'unknown' => 'تعذرت قراءة وضعه، يعامل معياريا');
            $m = ems_auth_mode($conn, $userId);
            $chain[] = array('step' => 'وضع الانتقال',
                'verdict' => (isset($modeLbl[$m]) ? $modeLbl[$m] : $m));
        }

        /* ④ أالرابطُ في القائمة مربوطٌ بها؟ — الظهورُ تابعٌ للربطِ لا للحكم،
              ويُعرض لأنَّ «أراه ولا أدخله» و«أدخله ولا أراه» شكويان مختلفتان. */
        $navN = 0;
        if (!empty($t['subject']['role'])) {
            $rid = (int) $t['subject']['role'];
            $r = mysqli_query($conn, "SELECT COUNT(*) n FROM nav_items
                                       WHERE role_id={$rid} AND module_id={$mid} AND active=1");
            $navN = ($r && ($x = mysqli_fetch_assoc($r))) ? (int) $x['n'] : 0;
        }
        $chain[] = array('step' => 'رابط القائمة',
            'verdict' => $navN > 0 ? "✔ {$navN} رابطا في قائمة دوره" : '— لا رابط في القائمة (تفتح بمسارها)');

        $allowed = !empty($t['allowed']);
        $acts = array();
        foreach (array('can_add' => 'إضافة', 'can_edit' => 'تعديل', 'can_delete' => 'حذف') as $k => $lbl) {
            if (!empty($t['perms'][$k])) { $acts[] = $lbl; }
        }
        return array(
            'allowed'   => $allowed,
            'reason'    => $allowed
                ? ('مسموح' . ($acts ? '، ومعه ' . implode(' و', $acts) : ' (قراءة فقط)'))
                : 'ممنوع، والسبب في سلسلة الخطوات اعلاه، وهي عين ما نفذه الحارس.',
            'chain'     => $chain,
            'grantor'   => 'إدارة الصلاحيات (الدور 15)',
            'module_id' => $mid,
            'subject'   => isset($t['subject']) ? $t['subject'] : null,
        );
    }
}

if (!function_exists('ems_deny_message')) {
    /**
     * رسالةُ رفضٍ تحمل سببَها ومن يملك منحَها — لا «لا صلاحية» مجردة.
     *
     * ◆ **والتوقيعُ بالدورِ يبقى** لأنَّ نداءاتِه كثيرةٌ وكلُّها عن **صاحبِ
     *   الجلسةِ نفسِه** — فيُؤخذ معرِّفُه من الجلسةِ وهو الحبّةُ الصحيحة،
     *   ويبقى الدورُ وسمًا للرسالةِ لا مصدرَ حكم.
     */
    function ems_deny_message(mysqli $conn, $roleId, $screenCode)
    {
        $uid = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
        if ($uid <= 0) { return 'لا صلاحية، ولا جلسة تنسب اليها. المنح من ادارة الصلاحيات (الدور 15).'; }
        $x = ems_explain_screen_access($conn, $uid, $screenCode);
        $msg = '❌ ' . $x['reason'];
        if (!empty($x['grantor'])) { $msg .= ' — المنح من ' . $x['grantor'] . '.'; }
        return $msg;
    }
}
