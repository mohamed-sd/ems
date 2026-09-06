<?php
namespace App\Services\Security;

/**
 * PolicyWriteService — **منفذُ الكتابةِ الواحدُ المحروس** في جداولِ السياسة
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-01-DEC §0-3 نصًّا: «لا تمسّ جداولَ حيّةً بصفٍّ يدويٍّ ولا بهجرةٍ لتسكيرِ
 * بند. **كلُّ كتابةٍ في جداولِ الصلاحيّاتِ تمرُّ بمنفذِ الكتابةِ المحروس**».
 * وPERM-01 §7-① : «لا تكتب واجهةٌ في جداولِ السياسةِ مباشرةً».
 *
 * ◆ **بابٌ واحدٌ**: إسنادُ قالبٍ وسحبُه (ق-١)، ويلحق به الفتحُ الاضطراريُّ (ق-٢).
 *   وكلُّ واجهةٍ **عميلٌ** لهذا الباب — «إن بنيتَ بابًا خامسًا تُرفض الشاشةُ أوّلًا».
 *
 * ⛔ **وكلُّ كتابةٍ تمرُّ بأربعةِ حرّاسٍ بهذا الترتيب**:
 *   ① **التجميد** — مدًى نافذٌ يردُّ الكتابةَ بإنذارٍ مسمًّى، ولا يُلتفّ عليه.
 *   ② **قواعدُ العمل** — قالبٌ نافذٌ واحدٌ للفاعل · لا إسنادَ لمسودّةٍ · لا منحَ
 *      لحسابٍ غيرِ حيّ · ولا كتابةَ بلا سببٍ مكتوب.
 *   ③ **الكتابةُ والقراءةُ بالبوّابةِ لا باستعلامٍ خامّ** (ADR-02 · سقّاطة GAP-29
 *      «يُخفَّض ولا يُرفع»). ولذلك لا يُرى في هذا الملفِّ نصُّ SQL واحد.
 *   ④ **الأثرُ شرطُ صحّةٍ لا حاشية**: تعذّر سطرُ الأثرِ ⇒ **تُلغى المعاملةُ
 *      كلُّها**. وكتابةٌ بلا أثرٍ تُقرأ «لم يقع تغييرٌ» وقد وقع.
 *
 * ◆ **ولا يُخترع فاعل**: `actorId` صفرٌ يعني هجرةً أو أداةً، ويُسمّى كذلك.
 * ═══════════════════════════════════════════════════════════════════════════
 */
/* ⛔ **والخدمةُ تضمن تبعيّتَها بنفسِها** (PERM-02): الأثرُ **شرطُ صحّةِ** كلِّ
     كتابةٍ هنا، فغيابُ كاتبِه يُسقِط المعاملةَ كلَّها. وقد وقع مقيسًا: شاشةٌ
     جديدةٌ لم تُضمّن `perm_change_log.php` فردَّت كلُّ كتابةٍ بـ
     «Call to undefined function» — والمعاملةُ ارتدّت صحيحًا فلم يبقَ صفٌّ يتيم،
     لكنَّ الشاشةَ بدت معطَّلة. ولا يُعالَج ذلك بسطرٍ في كلِّ شاشةٍ تنادي الخدمةَ
     (‏فذاك سبعُ فرصٍ للنسيان)، بل **بضمانِ التبعيّةِ في مالكِها**.
   ◆ ولم تكشفه شواهدُ CLI لأنّها تُضمّنه صراحةً — فالشاهدُ يقيس ما هيَّأه هو. */
require_once dirname(dirname(dirname(__DIR__))) . '/includes/perm_change_log.php';

class PolicyWriteService
{
    /** مديات التجميد كما في `gov_policy_freeze.scope_code`. */
    const FREEZE_GRANTS     = 'grants';
    const FREEZE_ACTIVATION = 'profile_activation';

    /** الدور 15 — إدارةُ الصلاحيات. مُسنِدٌ لا مجيزَ كسرٍ ولا معتمدَ مسودّة. */
    const ROLE_PERM_ADMIN = 15;

    /* ⚠ **آخرُ تعذُّرٍ في قراءةِ قوائمِ النموذج** — تقرؤه الشاشةُ فلا يمرُّ
         الفراغُ صامتًا (PERM-01-CUTOVER-REPAIR · م-ح-0.1). كان الاستثناءُ
         يُلتقَط وتُرجَع قائمةٌ فارغةٌ، فتبدو الشاشةُ سليمةً وهي معطَّلة. */
    public static $lastReadError = '';

    /** سقوفُ الفتحِ الاضطراريِّ بالساعات (PERM-01-DEC ق-٢). */
    const BG_HOURS_DEFAULT  = 4;
    const BG_HOURS_EXTENDED = 8;
    const BG_HOURS_ABSOLUTE = 24;

    private static function fail($code, $msg)
    {
        return array('ok' => false, 'code' => $code, 'msg' => $msg, 'id' => 0);
    }

    private static function done($id, $msg)
    {
        return array('ok' => true, 'code' => 'OK', 'msg' => $msg, 'id' => (int) $id);
    }

    /**
     * أمدًى مجمَّدٌ الآن؟ — يُقرأ قبلَ كلِّ كتابةٍ ولا يُلتفّ عليه.
     *
     * ⛔ **وتعذُّرُ القراءةِ يُعامَل تجميدًا**: الشكُّ في حالِ البوّابةِ يُحسم
     *   منعًا — فبوّابةٌ لا تُقرأ ليست بوّابةً مفتوحة.
     */
    public static function isFrozen($scope)
    {
        try {
            return \ems_tenant_db()->count('gov_policy_freeze', array(
                'where' => array('scope_code' => (string) $scope, 'active' => 1),
            )) > 0;
        } catch (\Throwable $t) {
            return true;
        }
    }

    /**
     * ① إسنادُ قالبٍ نافذٍ لمستخدم — ق-١.
     *
     * ◆ **قالبٌ واحدٌ لكلِّ فاعل**: منحةٌ نافذةٌ قائمةٌ تمنع ثانيةً — «لا يبقى
     *   دورٌ يرث اتّحادَ قالبَين» (PERM-01 §9).
     * ◆ **ولا يُسنَد إلّا نافذ**: المسودّةُ تُعتمَد وتُفعَّل أوّلًا ثمَّ تُسنَد،
     *   ولا تُسنَد مسودّةٌ ولو كانت مُعتمَدة.
     *
     * @return array{ok:bool,code:string,msg:string,id:int}
     */
    public static function assignProfile(\mysqli $conn, $userId, $profileId, $reason, $actorId = 0)
    {
        $userId = (int) $userId; $profileId = (int) $profileId;
        $reason = trim((string) $reason);
        if ($userId <= 0 || $profileId <= 0) { return self::fail('BAD_INPUT', 'الفاعل أو القالب غير صحيح'); }
        if ($reason === '') { return self::fail('NO_REASON', 'السبب مطلوب — ولا إسناد بلا سبب مكتوب'); }

        /* ① التجميدُ يردُّ ولا يُلتفّ عليه. */
        if (self::isFrozen(self::FREEZE_GRANTS)) {
            return self::fail('FROZEN', 'تجميد المنح نافذ — لا تصدر منحة جديدة حتى اشعار');
        }

        try {
            $gate = \ems_tenant_db();

            /* ② قواعدُ العمل — والقراءةُ بالبوّابةِ أيضًا. */
            $u = $gate->selectOne('users', array(
                'columns' => array('id', 'name', 'role', 'status'),
                'where'   => array('id' => $userId),
            ));
            if (!$u) { return self::fail('NO_USER', 'لا مستخدم حي بهذا المعرف'); }
            if ((string) $u['status'] !== 'active') {
                return self::fail('DEAD_USER', 'الحساب غير حي — ولا منح لحساب غير حي');
            }

            $p = $gate->selectOne('gov_role_profiles', array(
                'columns' => array('profile_id', 'profile_code', 'state'),
                'where'   => array('profile_id' => $profileId),
            ));
            if (!$p) { return self::fail('NO_PROFILE', 'لا قالب بهذا المعرف'); }
            if ((string) $p['state'] !== 'active') {
                return self::fail('DRAFT_PROFILE', 'القالب مسودة — يعتمد ويفعل اولا، ولا تسند مسودة');
            }

            /* ◆ **وقاعدةُ «قالبٌ واحد» على المنحِ الأصليّ وحدَه** (PERM-03):
                 §9 يمنع أن **يرث دورٌ اتّحادَ قالبَين** — وذاك عن المنحِ الدائم.
                 أمّا المؤقّتُ (تفويضٌ · رفعٌ · تصعيد) فهو **الاستثناءُ الذي
                 أذِن به الأمرُ نصًّا**: «لا منحةَ لفردٍ إلا استثناءً موقوتًا
                 بمصدرِه وسببِه ومدّتِه ومراجعتِه». فلو شمل الفحصُ المؤقّتَ
                 لاستحال التفويضُ أصلًا. */
            $live = $gate->selectOne('gov_authority_grants', array(
                'columns'  => array('grant_id', 'profile_id'),
                'where'    => array('user_id' => $userId, 'source' => 'profile'),
                'whereRaw' => 'revoked_at IS NULL AND (valid_to IS NULL OR valid_to > NOW())',
            ));
            if ($live) {
                return self::fail('ALREADY_GRANTED',
                    'للفاعل منحة نافذة رقم ' . (int) $live['grant_id']
                    . ' — قالب واحد لكل موظف. اسحب اولا ثم اسند');
            }
        } catch (\Throwable $t) {
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
            return self::fail('READ_FAILED', 'تعذر قراءة حال الفاعل او القالب');
        }

        /* ③ الكتابةُ بالبوّابة · ④ والأثرُ شرطُ صحّة. */
        return self::write($conn, function () use ($conn, $u, $p, $reason, $actorId) {
            /* ◆ **والمعرِّفُ من البوّابةِ لا من الوصلة**: `insert()` تُرجِع
                 `insert_id` من وصلتِها هي — وقراءتُه من `$conn` تُخرج صفرًا أو
                 معرِّفَ عمليّةٍ أخرى، فيفشل السحبُ بعده بـ«لا منحة». */
            $id = (int) \ems_tenant_db()->insert('gov_authority_grants', array(
                'user_id'    => (int) $u['id'],
                'profile_id' => (int) $p['profile_id'],
                'source'     => 'profile',
                'valid_from' => date('Y-m-d H:i:s'),
                'issued_by'  => (int) $actorId,
                'reason'     => mb_substr($reason, 0, 255),
            ));
            $ok = \ems_perm_change_log($conn, 'grant', 'insert', array(
                'subject_kind' => 'user',
                'subject_id'   => (int) $u['id'],
                'before'       => 'بلا قالب نافذ',
                'after'        => 'مسند الى ' . $p['profile_code'],
                'reason'       => $reason,
                'source'       => 'PolicyWriteService::assignProfile',
            ));
            if (!$ok) { throw new \RuntimeException('تعذر كتابة سطر الاثر'); }
            return self::done($id, 'اسند القالب ' . $p['profile_code'] . ' الى ' . $u['name']);
        });
    }

    /**
     * ② سحبُ منحةٍ نافذة — ويسري في أوّلِ طلبٍ بلا مخبأ.
     *
     * @return array{ok:bool,code:string,msg:string,id:int}
     */
    public static function revokeGrant(\mysqli $conn, $grantId, $reason, $actorId = 0)
    {
        $grantId = (int) $grantId; $reason = trim((string) $reason);
        if ($grantId <= 0) { return self::fail('BAD_INPUT', 'رقم المنحة غير صحيح'); }
        if ($reason === '') { return self::fail('NO_REASON', 'السبب مطلوب — ولا سحب بلا سبب مكتوب'); }

        try {
            $gate = \ems_tenant_db();
            $g = $gate->selectOne('gov_authority_grants', array(
                'columns' => array('grant_id', 'user_id', 'profile_id', 'revoked_at', 'reason'),
                'where'   => array('grant_id' => $grantId),
            ));
            if (!$g) { return self::fail('NO_GRANT', 'لا منحة بهذا الرقم'); }
            if ($g['revoked_at'] !== null) { return self::fail('ALREADY_REVOKED', 'المنحة مسحوبة سلفا'); }
            $prof = $gate->selectOne('gov_role_profiles', array(
                'columns' => array('profile_code'),
                'where'   => array('profile_id' => (int) $g['profile_id']),
            ));
        } catch (\Throwable $t) {
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
            return self::fail('READ_FAILED', 'تعذر قراءة المنحة');
        }
        $code = $prof ? (string) $prof['profile_code'] : ('#' . (int) $g['profile_id']);

        return self::write($conn, function () use ($conn, $g, $grantId, $reason, $code) {
            /* ◆ **والسببُ يُضمُّ إلى السابقِ لا يدهسه** — سجلُّ المنحةِ تاريخُها. */
            $n = \ems_tenant_db()->update('gov_authority_grants', array(
                'revoked_at' => date('Y-m-d H:i:s'),
                'reason'     => mb_substr(((string) $g['reason']) . ' | سحب: ' . $reason, 0, 255),
            ), array('grant_id' => $grantId));
            if ($n < 1) { throw new \RuntimeException('لم يتغير شيء'); }
            $ok = \ems_perm_change_log($conn, 'grant', 'revoke', array(
                'subject_kind' => 'user',
                'subject_id'   => (int) $g['user_id'],
                'before'       => 'منحة نافذة على ' . $code,
                'after'        => 'مسحوبة',
                'reason'       => $reason,
                'source'       => 'PolicyWriteService::revokeGrant',
            ));
            if (!$ok) { throw new \RuntimeException('تعذر كتابة سطر الاثر'); }
            return self::done($grantId, 'سحبت المنحة وسجل سببها');
        });
    }

    /**
     * ③ فتحٌ اضطراريٌّ موقوتٌ على شاشة — ق-٢ من PERM-01-DEC.
     *
     * ⛔ **يفتح ولا يغلق أبدًا**، ويمرُّ بخمسةِ قيودٍ لا يُتجاوز واحدٌ منها:
     *   ① **حرّاسُ `never` لا تُمَسّ** — ثمانيةٌ مصنَّفةٌ سلفًا، ولا كسرَ لها
     *      «مهما كان المبرّر».
     *   ② **المجيزُ ليس الدورَ 15 بحال** ولا الطالبَ نفسَه — طالبٌ ≠ مجيزٌ ≠ مُسنِد.
     *      وغيابُ دورِ الحوكمةِ يُعوَّض **بالمالكِ مؤقّتًا** ولا يوقف الفتح.
     *   ③ **المالياتُ لا تُستثنى** بل تشتدّ: مجيزُ حوكمةٍ **ومجيزٌ ماليٌّ** معًا،
     *      ولا تُقبل بمجيزٍ واحد.
     *   ④ **السقفُ 4 ساعاتٍ** — و8 بمجيزٍ ثانٍ، ولا يتجاوز 24 بحال. و24 ليست
     *      قيمةً افتراضيّةً بل سقفًا مطلقًا.
     *   ⑤ **وضابطٌ معوِّضٌ مكتوبٌ** لصنفِ `with_compensating_control`.
     *
     * @param int    $userId    الفاعلُ الذي يُفتَح له
     * @param string $screenCode رمزُ الشاشةِ كما في `modules.code`
     * @param array  $d         approver_gov · approver_fin · hours · reason · compensating
     * @return array{ok:bool,code:string,msg:string,id:int}
     */
    public static function openException(\mysqli $conn, $userId, $screenCode, array $d)
    {
        $userId = (int) $userId;
        $screenCode = trim((string) $screenCode);
        $reason = trim((string) ($d['reason'] ?? ''));
        $govApprover = (int) ($d['approver_gov'] ?? 0);
        $finApprover = (int) ($d['approver_fin'] ?? 0);
        $hours = (int) ($d['hours'] ?? self::BG_HOURS_DEFAULT);
        $comp = trim((string) ($d['compensating'] ?? ''));

        if ($userId <= 0 || $screenCode === '') { return self::fail('BAD_INPUT', 'الفاعل أو الشاشة غير صحيح'); }
        if ($reason === '') { return self::fail('NO_REASON', 'السبب مطلوب — ولا فتح اضطراري بلا سبب'); }
        if ($govApprover <= 0) { return self::fail('NO_APPROVER', 'يلزم مجيز حوكمة (او المالك مؤقتا)'); }

        try {
            $gate = \ems_tenant_db();

            /* ② المجيزُ ليس الطالبَ ولا من الدورِ 15. */
            if ($govApprover === $userId) {
                return self::fail('SELF_APPROVE', 'الطالب لا يجيز لنفسه — طالب غير مجيز');
            }
            $ap = $gate->selectOne('users', array(
                'columns' => array('id', 'role', 'name'), 'where' => array('id' => $govApprover)));
            if (!$ap) { return self::fail('NO_APPROVER', 'لا مجيز حي بهذا المعرف'); }
            if ((int) $ap['role'] === self::ROLE_PERM_ADMIN) {
                return self::fail('ROLE15_APPROVER',
                    'الدور 15 لا يجيز كسر الزجاج بحال — الاسناد شيء والاجازة شيء اخر');
            }

            /* ① حارسُ `never` لا يُمَسّ · ⑤ والضابطُ المعوِّضُ مكتوب. */
            $pol = $gate->selectOne('guard_override_policies', array(
                'columns' => array('guard_code', 'name_ar', 'overridable'),
                'where'   => array('guard_code' => $screenCode)));
            if ($pol && (string) $pol['overridable'] === 'never') {
                return self::fail('GUARD_NEVER',
                    'حارس ' . $pol['name_ar'] . ' لا يكسر زجاجه مهما كان السبب');
            }
            if ($pol && (string) $pol['overridable'] === 'with_compensating_control' && $comp === '') {
                return self::fail('NO_COMPENSATING',
                    'هذا الحارس يكسر بضابط معوض مكتوب — اكتبه في سطر الاثر');
            }

            /* ③ المالياتُ بثنائيّةِ مجيزَين. */
            if (self::isFinancialScreen($screenCode)) {
                if ($finApprover <= 0 || $finApprover === $govApprover) {
                    return self::fail('DUAL_REQUIRED',
                        'شاشة مالية — تلزمها ثنائية: مجيز حوكمة ومجيز مالي مختلفان');
                }
                if ($finApprover === $userId) {
                    return self::fail('SELF_APPROVE', 'الطالب لا يجيز لنفسه ماليا');
                }
            }

            /* ④ السقفُ الزمنيّ. */
            $cap = ($finApprover > 0 && $finApprover !== $govApprover)
                 ? self::BG_HOURS_EXTENDED : self::BG_HOURS_DEFAULT;
            if ($hours < 1) { $hours = self::BG_HOURS_DEFAULT; }
            if ($hours > $cap) {
                return self::fail('OVER_CAP',
                    'السقف ' . $cap . ' ساعات' . ($cap === self::BG_HOURS_DEFAULT ? ' — والتمديد الى 8 بمجيز ثان' : ''));
            }
            if ($hours > self::BG_HOURS_ABSOLUTE) {
                return self::fail('OVER_ABSOLUTE', 'لا يتجاوز 24 ساعة بحال');
            }
        } catch (\Throwable $t) {
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
            return self::fail('READ_FAILED', 'تعذر قراءة سياسة الحارس او المجيز');
        }

        $approvals = 'gov:' . $govApprover . ($finApprover > 0 ? '+fin:' . $finApprover : '')
                   . ($comp !== '' ? '+comp' : '');
        return self::write($conn, function () use ($conn, $userId, $screenCode, $reason,
                                                   $hours, $approvals, $comp) {
            $now = time();
            $id = (int) \ems_tenant_db()->insert('permission_exceptions', array(
                'person_id'       => $userId,
                'permission_code' => mb_substr($screenCode, 0, 120),
                'scope_rule'      => 'screen',
                'effect'          => 'grant',
                'reason'          => mb_substr($reason . ($comp !== '' ? ' | ضابط معوض: ' . $comp : ''), 0, 255),
                'valid_from'      => date('Y-m-d H:i:s', $now),
                'valid_to'        => date('Y-m-d H:i:s', $now + ($hours * 3600)),
                'is_break_glass'  => 1,
                'approvals_ref'   => mb_substr($approvals, 0, 120),
                'state'           => 'active',
            ));
            $ok = \ems_perm_change_log($conn, 'exception', 'open', array(
                'subject_kind' => 'user',
                'subject_id'   => $userId,
                'screen_code'  => $screenCode,
                'before'       => 'ممنوع',
                'after'        => 'مفتوح اضطرارا ' . $hours . ' ساعة (' . $approvals . ')',
                'reason'       => $reason,
                'source'       => 'PolicyWriteService::openException',
            ));
            if (!$ok) { throw new \RuntimeException('تعذر كتابة سطر الاثر'); }
            return self::done($id, 'فتح اضطراري لمدة ' . $hours . ' ساعة على ' . $screenCode);
        });
    }

    /**
     * ③-ب إغلاقُ فتحٍ اضطراريٍّ قبلَ أوانِه — سحبٌ لا حذف.
     *
     * ◆ **والاستثناءُ ينتهي بنفسِه بالوقت**، وهذا يسحبه حين تزول الحاجةُ باكرًا
     *   — فبابٌ مفتوحٌ بلا حاجةٍ بابٌ مفتوحٌ بلا سبب.
     * ⛔ **ولا يُحذف صفُّه**: يُقلَب إلى `revoked` فيبقى الأثرُ مقروءًا.
     */
    public static function closeException(\mysqli $conn, $exId, $reason, $actorId = 0)
    {
        $exId = (int) $exId; $reason = trim((string) $reason);
        if ($exId <= 0) { return self::fail('BAD_INPUT', 'رقم الاستثناء غير صحيح'); }
        if ($reason === '') { return self::fail('NO_REASON', 'السبب مطلوب — ولا اغلاق بلا سبب مكتوب'); }

        try {
            $ex = \ems_tenant_db()->selectOne('permission_exceptions', array(
                'columns' => array('ex_id', 'person_id', 'permission_code', 'state'),
                'where'   => array('ex_id' => $exId)));
            if (!$ex) { return self::fail('NO_EXCEPTION', 'لا استثناء بهذا الرقم'); }
            if ((string) $ex['state'] !== 'active') {
                return self::fail('NOT_ACTIVE', 'الاستثناء ليس ساريا — حاله ' . $ex['state']);
            }
        } catch (\Throwable $t) {
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
            return self::fail('READ_FAILED', 'تعذر قراءة الاستثناء');
        }

        return self::write($conn, function () use ($conn, $exId, $ex, $reason) {
            \ems_tenant_db()->update('permission_exceptions',
                array('state' => 'revoked'), array('ex_id' => $exId));
            $ok = \ems_perm_change_log($conn, 'exception', 'close', array(
                'subject_kind' => 'user', 'subject_id' => (int) $ex['person_id'],
                'screen_code'  => (string) $ex['permission_code'],
                'before' => 'مفتوح اضطرارا', 'after' => 'مغلق قبل اوانه',
                'reason' => $reason, 'source' => 'PolicyWriteService::closeException',
            ));
            if (!$ok) { throw new \RuntimeException('تعذر كتابة سطر الاثر'); }
            return self::done($exId, 'أُغلق الفتح الاضطراري على ' . $ex['permission_code']);
        });
    }

    /**
     * أشاشةٌ ماليّةٌ هي؟ — تُعرَف بموضعِها في الشجرةِ لا برأيٍ فيها.
     *
     * ◆ والعائلاتُ المادّيّةُ في الأمر: مالية · خزينة · مشتريات · منحُ صلاحيات.
     */
    private static function isFinancialScreen($code)
    {
        return (bool) preg_match(
            '~^(Finance|FinRequests|Treasury|Procurement|Governance)/~i', (string) $code);
    }

    /**
     * الفاعلون الذين يصحُّ إسنادُ قالبٍ إليهم — أحياءُ بلا منحةٍ نافذة.
     *
     * ◆ **والقراءةُ في الخدمةِ لا في الشاشة**: استعلامٌ خامٌّ في مسارِ إدارةٍ
     *   يرفع سجلَّ الدَّينِ `RP-04`، والبوّابةُ تُغني عنه. والشاشةُ تعرض ولا تسأل
     *   قاعدةَ البيانات.
     *
     * @return array<int,array>
     */
    public static function assignableUsers()
    {
        /* ⛔ **والفرزُ في PHP لا باستعلامٍ فرعيّ**: نصُّ استعلامٍ فرعيٍّ يذكر
             سجلَّ المنحِ **يُعَدُّ استعلامًا خامًّا على جدولِ مستأجِر** ولو مرَّ
             بالبوّابة، فترصده سقّاطةُ GAP-29 وترفع الأساسَ ملفًّا. فتُقرأ
             المجموعتانِ بالبوّابةِ وتُطرح إحداهما من الأخرى.
           ⛔ **والماسحُ يقرأ التعليقَ كما يقرأ الشيفرة**: كتابةُ النصِّ المحظورِ
             في الشرحِ ترفع السقّاطةَ وإن زال من الكود — مقيسٌ: بقيت 608. */
        /* ⛔ **والفرزُ العدديُّ في PHP لا في الترتيب**: كان الترتيبُ المُمرَّرُ
             يحوي **أقواسًا**، وحارسُ البوّابةِ `assertOrderBy` لا يقبلها، فيرمي
             ويُلتقَط الاستثناءُ وترجع القائمةُ **فارغةً** — فيستحيل إسنادُ قالبٍ
             لأحدٍ والشاشةُ تبدو سليمة. مسجَّلٌ سبعَ مرّاتٍ في يومِ الحادث. */
        self::$lastReadError = '';
        try {
            $gate = \ems_tenant_db();
            $live = $gate->select('gov_authority_grants', array(
                'columns'  => array('user_id'),
                'whereRaw' => 'revoked_at IS NULL AND (valid_to IS NULL OR valid_to > NOW())',
            ));
            $taken = array();
            foreach ((array) $live as $g) { $taken[(int) $g['user_id']] = 1; }

            $rows = $gate->select('users', array(
                'columns' => array('id', 'name', 'role'),
                'where'   => array('status' => 'active'),
                'orderBy' => 'name',
            ));
            $out = array();
            foreach ((array) $rows as $u) {
                if (!isset($taken[(int) $u['id']])) { $out[] = $u; }
            }
            /* ◆ ورقمُ الدورِ محفوظٌ نصًّا، فيُرتَّب عدديًّا هنا ثمَّ بالاسم. */
            usort($out, function ($a, $b) {
                $ra = (int) $a['role'];
                $rb = (int) $b['role'];
                if ($ra !== $rb) { return ($ra < $rb) ? -1 : 1; }
                return strcmp((string) $a['name'], (string) $b['name']);
            });
            return $out;
        } catch (\Throwable $t) {
            self::$lastReadError = (string) $t->getMessage();
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
            return array();
        }
    }

    /** القوالبُ النافذةُ وحدَها — ولا تُعرض مسودّةٌ لا تُسنَد. */
    public static function activeProfiles()
    {
        try {
            $rows = \ems_tenant_db()->select('gov_role_profiles', array(
                'columns' => array('profile_id', 'profile_code', 'title_ar'),
                'where'   => array('state' => 'active'),
                'orderBy' => 'profile_code',
            ));
            return is_array($rows) ? $rows : array();
        } catch (\Throwable $t) {
            self::$lastReadError = (string) $t->getMessage();
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
            return array();
        }
    }

    /* ═══════════════════════════════════════════════════════════════════════
       PERM-02 · بابُ تأليفِ القوالب — الشقُّ الذي كان مفقودًا
       ───────────────────────────────────────────────────────────────────────
       ◆ **العلّةُ المقيسة**: `Governance/auth_profiles.php` كانت عرضًا محضًا
         (تردُّ 405 على كلِّ كتابة)، و`gov_profile_items` **بلا كاتبٍ إطلاقًا** —
         فمديرُ الصلاحيّاتِ يملك إسنادَ قالبٍ ولا يملك **صنعَه**. وكلُّ القوالبِ
         الاثنين والثلاثين مولَّدةٌ بأدواتِ سطرِ أوامرٍ وهجرات.
       ◆ **والبابُ واحدٌ لا خامس**: هذه الأفعالُ تسكن الخدمةَ نفسَها لا شاشةً —
         «إن بنيتَ بابًا خامسًا تُرفض الشاشةُ أوّلًا» (ق-١).
       ◆ **وكلُّها ترث الحرّاسَ الأربعةَ** بترتيبِهم: تجميدٌ · قواعدُ عملٍ ·
         بوّابةٌ لا استعلامٌ خامّ · وأثرٌ شرطُ صحّةٍ يُلغي المعاملةَ إن تعذّر.
       ═══════════════════════════════════════════════════════════════════════ */

    /** وسمُ مصدرِ البذرِ لما تؤلّفه الكونسول — واحدٌ فلا يتعدّد مصدرُ القالب. */
    const SEED_CONSOLE = 'perm02_console';

    /**
     * حالاتُ القالبِ الثلاث — تُقرأ من المخطَّطِ ولا تُخترع.
     * [[enum-silent-empty-write]]: قيمةٌ خارجَ المفرداتِ تصير `''` صامتةً.
     */
    const ST_DRAFT = 'draft';
    const ST_ACTIVE = 'active';
    const ST_RETIRED = 'retired';

    /**
     * ④ تأليفُ قالبٍ جديد — يولد **مسودّةً** دائمًا ولا يولد نافذًا.
     *
     * ◆ **ولا تفعيلَ بالولادة**: المسارُ المعياريُّ مسودّةٌ ⇐ بنودٌ ⇐ اعتمادٌ ⇐
     *   تفعيل. ومن ولد نافذًا تخطّى الاعتمادَ، وهو ما يمنعه القادحُ أصلًا.
     *
     * @param array $d profile_code · title_ar · dept_code · grade · data_scope · fixed_rule
     * @return array{ok:bool,code:string,msg:string,id:int}
     */
    public static function createProfile(\mysqli $conn, array $d, $reason, $actorId = 0)
    {
        $code = strtoupper(trim((string) ($d['profile_code'] ?? '')));
        $title = trim((string) ($d['title_ar'] ?? ''));
        $reason = trim((string) $reason);
        if ($code === '' || $title === '') { return self::fail('BAD_INPUT', 'رمز القالب واسمه مطلوبان'); }
        if (!preg_match('/^[A-Z0-9][A-Z0-9\-_]{1,19}$/', $code)) {
            return self::fail('BAD_CODE', 'رمز القالب حروف لاتينية وارقام وشرطات، بطول 2 الى 20');
        }
        if ($reason === '') { return self::fail('NO_REASON', 'السبب مطلوب — ولا تأليف بلا سبب مكتوب'); }

        $grade = (string) ($d['grade'] ?? 'G3');
        if (!in_array($grade, array('G1','G2','G3','G4','G5','G6','G7','G8','G9'), true)) { $grade = 'G3'; }

        try {
            $gate = \ems_tenant_db();
            $dup = $gate->selectOne('gov_role_profiles', array(
                'columns' => array('profile_id'), 'where' => array('profile_code' => $code)));
            if ($dup) { return self::fail('DUP_CODE', 'الرمز مستعمل — لكل قالب رمز واحد'); }
        } catch (\Throwable $t) {
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
            return self::fail('READ_FAILED', 'تعذر التحقق من تفرد الرمز');
        }

        return self::write($conn, function () use ($conn, $code, $title, $d, $grade, $reason) {
            $id = (int) \ems_tenant_db()->insert('gov_role_profiles', array(
                'company_id'         => 0,
                'profile_code'       => $code,
                'grade'              => $grade,
                'dept_code'          => mb_substr(trim((string) ($d['dept_code'] ?? 'عام')), 0, 120),
                'title_ar'           => mb_substr($title, 0, 120),
                'screens_target'     => 0,
                'prepares_target'    => 0,
                'approves_target'    => 0,
                'approval_cap_label' => mb_substr((string) ($d['approval_cap_label'] ?? ''), 0, 200),
                'data_scope'         => mb_substr((string) ($d['data_scope'] ?? 'ادارته'), 0, 120),
                'sensitive_fields'   => mb_substr((string) ($d['sensitive_fields'] ?? ''), 0, 200),
                'fixed_rule'         => mb_substr((string) ($d['fixed_rule'] ?? ''), 0, 255),
                'version'            => 1,
                'state'              => self::ST_DRAFT,
            ));
            $ok = \ems_perm_change_log($conn, 'profile', 'create', array(
                'subject_kind' => 'profile', 'subject_id' => $id,
                'before' => 'لا وجود', 'after' => 'مسودة ' . $code . ' — ' . $title,
                'reason' => $reason, 'source' => 'PolicyWriteService::createProfile',
            ));
            if (!$ok) { throw new \RuntimeException('تعذر كتابة سطر الاثر'); }
            return self::done($id, 'أُلف القالب ' . $code . ' مسودة — أضف بنوده ثم اعتمده');
        });
    }

    /**
     * ⑤ تعديلُ بياناتِ قالبٍ — **مسودّةً حصرًا**.
     *
     * ⛔ **ولا يُعدَّل نافذٌ في مكانِه**: «بل يُصدَر إصدارٌ جديدٌ ويُرحَّل حاملوه»
     *   — فتحريرُ النافذِ يقلب صلاحيّاتِ حامليه بلا اعتمادٍ ولا إعلام.
     */
    public static function updateProfile(\mysqli $conn, $profileId, array $d, $reason, $actorId = 0)
    {
        $profileId = (int) $profileId; $reason = trim((string) $reason);
        if ($profileId <= 0) { return self::fail('BAD_INPUT', 'رقم القالب غير صحيح'); }
        if ($reason === '') { return self::fail('NO_REASON', 'السبب مطلوب'); }

        $p = self::readProfile($profileId);
        if (!$p) { return self::fail('NO_PROFILE', 'لا قالب بهذا المعرف'); }
        if ((string) $p['state'] !== self::ST_DRAFT) {
            return self::fail('NOT_DRAFT',
                'لا يعدل قالب نافذ في مكانه — انسخه اصدارا جديدا ثم عدله');
        }

        $set = array();
        foreach (array('title_ar' => 120, 'dept_code' => 120, 'data_scope' => 120,
                       'approval_cap_label' => 200, 'sensitive_fields' => 200, 'fixed_rule' => 255) as $k => $len) {
            if (array_key_exists($k, $d)) { $set[$k] = mb_substr(trim((string) $d[$k]), 0, $len); }
        }
        if (isset($d['grade']) && in_array((string) $d['grade'], array('G1','G2','G3','G4','G5','G6','G7','G8','G9'), true)) {
            $set['grade'] = (string) $d['grade'];
        }
        if (!$set) { return self::fail('NOTHING', 'لا حقل للتعديل'); }

        return self::write($conn, function () use ($conn, $profileId, $p, $set, $reason) {
            \ems_tenant_db()->update('gov_role_profiles', $set, array('profile_id' => $profileId));
            $ok = \ems_perm_change_log($conn, 'profile', 'update', array(
                'subject_kind' => 'profile', 'subject_id' => $profileId,
                'before' => (string) $p['title_ar'],
                'after'  => implode(' · ', array_map(function ($k, $v) { return $k . '=' . $v; },
                                                     array_keys($set), array_values($set))),
                'reason' => $reason, 'source' => 'PolicyWriteService::updateProfile',
            ));
            if (!$ok) { throw new \RuntimeException('تعذر كتابة سطر الاثر'); }
            return self::done($profileId, 'عُدل القالب ' . $p['profile_code']);
        });
    }

    /**
     * ⑥ ضبطُ بندِ شاشةٍ في قالب — إضافةً أو تعديلَ أعلامٍ أو استبعادًا.
     *
     * ◆ **والاستبعادُ `allow=0` لا حذفَ صفّ**: صفٌّ يقول «نُظر فيها فمُنعت» أنفعُ
     *   من غيابٍ لا يُفرَّق فيه بين «لم تُدرَس» و«رُفضت». وكلاهما منعٌ في القرار
     *   (`allow=0` و«لا صفّ» ⇒ `-1`)، فالفرقُ في المراجعةِ لا في الحكم.
     * ⛔ **ومصدرُ البذرِ واحدٌ للقالبِ كلِّه**: القادحُ يردُّ تفعيلَ ما تعدَّد
     *   مصدرُه، فيرث البندُ الجديدُ مصدرَ إخوتِه إن وُجدوا.
     * ⛔ **والكتابةُ في النافذِ ممنوعةٌ** — البنودُ تُحرَّر في المسودّةِ وحدَها.
     */
    public static function setProfileItem(\mysqli $conn, $profileId, $screenCode, array $flags, $reason, $actorId = 0, $kind = 'screen')
    {
        $profileId = (int) $profileId;
        $screenCode = trim((string) $screenCode);
        $kind = (string) $kind;
        if (!in_array($kind, array('screen', 'action', 'cap', 'scope', 'field'), true)) {
            return self::fail('BAD_KIND', 'نوع بند غير معروف');
        }
        $reason = trim((string) $reason);
        if ($profileId <= 0 || $screenCode === '') { return self::fail('BAD_INPUT', 'القالب أو الشاشة غير صحيح'); }
        if ($reason === '') { return self::fail('NO_REASON', 'السبب مطلوب'); }

        $p = self::readProfile($profileId);
        if (!$p) { return self::fail('NO_PROFILE', 'لا قالب بهذا المعرف'); }
        if ((string) $p['state'] !== self::ST_DRAFT) {
            return self::fail('NOT_DRAFT', 'بنود القالب تحرر في المسودة — انسخه اصدارا جديدا');
        }

        $allow = !empty($flags['allow']) ? 1 : 0;
        $add   = ($allow && !empty($flags['can_add'])) ? 1 : 0;
        $edit  = ($allow && !empty($flags['can_edit'])) ? 1 : 0;
        $del   = ($allow && !empty($flags['can_delete'])) ? 1 : 0;

        try {
            $gate = \ems_tenant_db();
            /* ◆ **والشاشةُ تُقرأ من سجلِّ الوحداتِ لا تُقبل نصًّا**: بندٌ برمزٍ
                 لا يقابل شاشةً مسجَّلةً لا يحكم شيئًا — ويُقرأ ضمانًا وهو فراغ. */
            if ($kind === 'screen') {
                $m = $gate->selectOne('modules', array(
                    'columns' => array('id', 'code', 'name'), 'where' => array('code' => $screenCode)));
                if (!$m) { return self::fail('NO_SCREEN', 'لا شاشة مسجلة بهذا الرمز في سجل الوحدات'); }
            } else {
                /* ◆ **والمرجعُ من سجلِّه الحاكمِ لا نصًّا حرًّا** (PERM-03): بندٌ
                     برمزٍ لا يقابل مدخلًا مُعلَنًا لا يحكم شيئًا ويُقرأ ضمانًا
                     وهو فراغ. والمفرداتُ تُقرأ من `perm_layers` لا تُكتب هنا. */
                require_once dirname(dirname(dirname(__DIR__))) . '/includes/perm_layers.php';
                $known = false;
                foreach (\ems_layer_vocabulary($conn, $kind) as $v) {
                    if ((string) $v['ref'] === $screenCode) { $known = true; break; }
                }
                if (!$known) {
                    return self::fail('NO_REF', 'لا مدخل معلن بهذا الرمز في سجل ' . $kind . ' الحاكم');
                }
            }

            $cur = $gate->selectOne('gov_profile_items', array(
                'columns' => array('item_id', 'allow', 'can_add', 'can_edit', 'can_delete', 'seeded_from'),
                'where'   => array('profile_id' => $profileId, 'item_kind' => $kind, 'item_ref' => $screenCode)));

            $sibling = $gate->selectOne('gov_profile_items', array(
                'columns' => array('seeded_from'), 'where' => array('profile_id' => $profileId)));
            $seed = $sibling ? (string) $sibling['seeded_from'] : self::SEED_CONSOLE;
        } catch (\Throwable $t) {
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
            return self::fail('READ_FAILED', 'تعذر قراءة الشاشة أو بنود القالب');
        }

        $before = $cur
            ? ('allow=' . (int) $cur['allow'] . ' a=' . (int) $cur['can_add']
               . ' e=' . (int) $cur['can_edit'] . ' d=' . (int) $cur['can_delete'])
            : 'لا بند';
        $after = 'allow=' . $allow . ' a=' . $add . ' e=' . $edit . ' d=' . $del;

        return self::write($conn, function () use ($conn, $profileId, $screenCode, $cur, $seed,
                                                   $allow, $add, $edit, $del, $before, $after, $reason, $p, $kind) {
            $gate = \ems_tenant_db();
            $row = array('allow' => $allow, 'can_add' => $add, 'can_edit' => $edit, 'can_delete' => $del);
            if ($cur) {
                $gate->update('gov_profile_items', $row, array('item_id' => (int) $cur['item_id']));
                $id = (int) $cur['item_id'];
            } else {
                $row['company_id'] = 0;
                $row['profile_id'] = $profileId;
                $row['item_kind']  = $kind;
                $row['item_ref']   = mb_substr($screenCode, 0, 160);
                $row['seeded_from'] = mb_substr($seed, 0, 60);
                $id = (int) $gate->insert('gov_profile_items', $row);
            }
            self::syncScreensTarget($profileId);
            $ok = \ems_perm_change_log($conn, 'profile_item', $cur ? 'update' : 'insert', array(
                'subject_kind' => 'profile', 'subject_id' => $profileId,
                'screen_code'  => $kind . ':' . $screenCode,
                'before' => $before, 'after' => $after,
                'reason' => $reason, 'source' => 'PolicyWriteService::setProfileItem',
            ));
            if (!$ok) { throw new \RuntimeException('تعذر كتابة سطر الاثر'); }
            return self::done($id, ($allow ? 'ضُمنت ' : 'استُبعدت ') . $screenCode . ' في ' . $p['profile_code']);
        });
    }

    /**
     * ⑦ اعتمادُ مسودّةٍ — الشرطُ الذي يفتح بوّابةَ التفعيل (FTRE-0062).
     *
     * ⛔ **والمعتمِدُ ليس المؤلِّفَ**: من ألّف القالبَ لا يعتمده — «المراجعةُ لا
     *   تكون تصديقًا على الذات» (SOD-02 بحرفِه). ويُقرأ المؤلِّفُ من سجلِّ الأثر.
     */
    public static function approveProfile(\mysqli $conn, $profileId, $note, $actorId = 0)
    {
        $profileId = (int) $profileId; $note = trim((string) $note); $actorId = (int) $actorId;
        if ($profileId <= 0) { return self::fail('BAD_INPUT', 'رقم القالب غير صحيح'); }
        if ($note === '') { return self::fail('NO_REASON', 'سند الاعتماد مطلوب — ولا اعتماد بلا سبب'); }
        if ($actorId <= 0) { return self::fail('NO_ACTOR', 'لا اعتماد بلا معتمد معلوم'); }

        $p = self::readProfile($profileId);
        if (!$p) { return self::fail('NO_PROFILE', 'لا قالب بهذا المعرف'); }
        if ((string) $p['state'] !== self::ST_DRAFT) {
            return self::fail('NOT_DRAFT', 'الاعتماد للمسودة — وهذا القالب ' . $p['state']);
        }

        try {
            $gate = \ems_tenant_db();
            $n = $gate->count('gov_profile_items', array(
                'where' => array('profile_id' => $profileId, 'allow' => 1)));
            if ($n < 1) {
                return self::fail('EMPTY_PROFILE', 'لا يعتمد قالب بلا بند مسموح واحد');
            }
            $seeds = $gate->select('gov_profile_items', array(
                'columns' => array('seeded_from'), 'where' => array('profile_id' => $profileId)));
            $distinct = array();
            foreach ((array) $seeds as $s) { $distinct[(string) $s['seeded_from']] = 1; }
            if (count($distinct) > 1) {
                return self::fail('MULTI_SEED',
                    'القالب مبذور من ' . count($distinct) . ' مصادر — يوحد مصدره قبل الاعتماد');
            }
            $already = $gate->count('gov_profile_activation_approval', array(
                'where' => array('profile_id' => $profileId, 'version' => (int) $p['version'])));
            if ($already > 0) {
                return self::fail('ALREADY_APPROVED', 'لهذا الاصدار سجل اعتماد قائم — فعله مباشرة');
            }
        } catch (\Throwable $t) {
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
            return self::fail('READ_FAILED', 'تعذر قراءة بنود القالب أو سجل اعتماده');
        }

        return self::write($conn, function () use ($conn, $profileId, $p, $note, $actorId, $n) {
            $id = (int) \ems_tenant_db()->insert('gov_profile_activation_approval', array(
                'profile_id'  => $profileId,
                'version'     => (int) $p['version'],
                'approved_by' => $actorId,
                'doc_ref'     => mb_substr('PERM-02-CONSOLE', 0, 60),
                'reason'      => mb_substr($note, 0, 255),
            ));
            $ok = \ems_perm_change_log($conn, 'profile', 'approve', array(
                'subject_kind' => 'profile', 'subject_id' => $profileId,
                'before' => 'مسودة بلا اعتماد',
                'after'  => 'معتمدة — اصدار ' . (int) $p['version'] . ' بـ' . $n . ' بندا مسموحا',
                'reason' => $note, 'source' => 'PolicyWriteService::approveProfile',
            ));
            if (!$ok) { throw new \RuntimeException('تعذر كتابة سطر الاثر'); }
            return self::done($id, 'اعتُمد ' . $p['profile_code'] . ' — يمكن تفعيله الآن');
        });
    }

    /**
     * ⑧ تفعيلُ قالبٍ معتمَد — يمرُّ ببوّابةِ التجميدِ والقادحِ معًا.
     *
     * ◆ **ولا يُلتفّ على القادح**: يُقرأ شرطُه هنا ليُعطى المستخدمُ رسالةً
     *   مفهومةً قبلَ أن يردَّه المحرّكُ برسالةٍ خام — والقادحُ يبقى الحكمَ الأخير.
     */
    public static function activateProfile(\mysqli $conn, $profileId, $reason, $actorId = 0)
    {
        $profileId = (int) $profileId; $reason = trim((string) $reason);
        if ($profileId <= 0) { return self::fail('BAD_INPUT', 'رقم القالب غير صحيح'); }
        if ($reason === '') { return self::fail('NO_REASON', 'السبب مطلوب'); }
        if (self::isFrozen(self::FREEZE_ACTIVATION)) {
            return self::fail('FROZEN', 'تجميد تفعيل القوالب نافذ — ارفعه من ضبط البوابات ثم أعد المحاولة');
        }

        $p = self::readProfile($profileId);
        if (!$p) { return self::fail('NO_PROFILE', 'لا قالب بهذا المعرف'); }
        if ((string) $p['state'] === self::ST_ACTIVE) { return self::fail('ALREADY', 'القالب نافذ سلفا'); }

        try {
            $apr = \ems_tenant_db()->count('gov_profile_activation_approval', array(
                'where' => array('profile_id' => $profileId, 'version' => (int) $p['version'])));
            if ($apr < 1) {
                return self::fail('NO_APPROVAL', 'لا تفعيل لمسودة بلا سجل اعتماد — اعتمدها أولا');
            }
        } catch (\Throwable $t) {
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
            return self::fail('READ_FAILED', 'تعذر قراءة سجل الاعتماد');
        }

        return self::write($conn, function () use ($conn, $profileId, $p, $reason) {
            \ems_tenant_db()->update('gov_role_profiles',
                array('state' => self::ST_ACTIVE), array('profile_id' => $profileId));
            $ok = \ems_perm_change_log($conn, 'profile', 'activate', array(
                'subject_kind' => 'profile', 'subject_id' => $profileId,
                'before' => (string) $p['state'], 'after' => 'نافذ',
                'reason' => $reason, 'source' => 'PolicyWriteService::activateProfile',
            ));
            if (!$ok) { throw new \RuntimeException('تعذر كتابة سطر الاثر'); }
            return self::done($profileId, 'نفذ القالب ' . $p['profile_code'] . ' — صار قابلا للإسناد');
        });
    }

    /**
     * ⑨ تقاعدُ قالب — ولا يتقاعد وله حاملٌ حيّ.
     *
     * ⛔ **فإسقاطُ قالبٍ بحاملٍ يقطع صاحبَه عن النظامِ كلِّه** — النظامُ مغلقٌ
     *   افتراضيًّا، ومن فقد قالبَه يُمنع من كلِّ شاشة. اسحبِ المنحَ أوّلًا.
     */
    public static function retireProfile(\mysqli $conn, $profileId, $reason, $actorId = 0)
    {
        $profileId = (int) $profileId; $reason = trim((string) $reason);
        if ($profileId <= 0) { return self::fail('BAD_INPUT', 'رقم القالب غير صحيح'); }
        if ($reason === '') { return self::fail('NO_REASON', 'السبب مطلوب'); }

        $p = self::readProfile($profileId);
        if (!$p) { return self::fail('NO_PROFILE', 'لا قالب بهذا المعرف'); }
        if ((string) $p['state'] === self::ST_RETIRED) { return self::fail('ALREADY', 'القالب متقاعد سلفا'); }

        try {
            $held = \ems_tenant_db()->count('gov_authority_grants', array(
                'where'    => array('profile_id' => $profileId),
                'whereRaw' => 'revoked_at IS NULL AND (valid_to IS NULL OR valid_to > NOW())'));
            if ($held > 0) {
                return self::fail('HELD', 'للقالب ' . $held . ' حاملا حيا — اسحب منحهم اولا فالنظام مغلق افتراضيا');
            }
        } catch (\Throwable $t) {
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
            return self::fail('READ_FAILED', 'تعذر عد حاملي القالب');
        }

        return self::write($conn, function () use ($conn, $profileId, $p, $reason) {
            \ems_tenant_db()->update('gov_role_profiles',
                array('state' => self::ST_RETIRED), array('profile_id' => $profileId));
            $ok = \ems_perm_change_log($conn, 'profile', 'retire', array(
                'subject_kind' => 'profile', 'subject_id' => $profileId,
                'before' => (string) $p['state'], 'after' => 'متقاعد',
                'reason' => $reason, 'source' => 'PolicyWriteService::retireProfile',
            ));
            if (!$ok) { throw new \RuntimeException('تعذر كتابة سطر الاثر'); }
            return self::done($profileId, 'تقاعد القالب ' . $p['profile_code']);
        });
    }

    /**
     * ⑩ نسخُ قالبٍ نافذٍ إصدارًا جديدًا — الطريقُ الوحيدُ لتعديلِ النافذ.
     *
     * ◆ **يُنسَخ بنودُه كلُّها بمصدرِ بذرٍ واحد**، فيبقى قابلًا للتفعيلِ بعدَ
     *   الاعتماد. والأصلُ يبقى نافذًا حتى يُرحَّل حاملوه إلى الجديد.
     */
    public static function cloneProfile(\mysqli $conn, $profileId, $newCode, $reason, $actorId = 0)
    {
        $profileId = (int) $profileId;
        $newCode = strtoupper(trim((string) $newCode));
        $reason = trim((string) $reason);
        if ($profileId <= 0 || $newCode === '') { return self::fail('BAD_INPUT', 'القالب أو الرمز الجديد غير صحيح'); }
        if (!preg_match('/^[A-Z0-9][A-Z0-9\-_]{1,19}$/', $newCode)) {
            return self::fail('BAD_CODE', 'رمز القالب حروف لاتينية وارقام وشرطات، بطول 2 الى 20');
        }
        if ($reason === '') { return self::fail('NO_REASON', 'السبب مطلوب'); }

        $p = self::readProfile($profileId);
        if (!$p) { return self::fail('NO_PROFILE', 'لا قالب بهذا المعرف'); }

        try {
            $gate = \ems_tenant_db();
            if ($gate->selectOne('gov_role_profiles', array(
                    'columns' => array('profile_id'), 'where' => array('profile_code' => $newCode)))) {
                return self::fail('DUP_CODE', 'الرمز مستعمل');
            }
            $items = $gate->select('gov_profile_items', array(
                'columns' => array('item_kind', 'item_ref', 'allow', 'can_add', 'can_edit', 'can_delete'),
                'where'   => array('profile_id' => $profileId)));
        } catch (\Throwable $t) {
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
            return self::fail('READ_FAILED', 'تعذر قراءة بنود الأصل');
        }

        return self::write($conn, function () use ($conn, $p, $newCode, $items, $reason) {
            $gate = \ems_tenant_db();
            $id = (int) $gate->insert('gov_role_profiles', array(
                'company_id' => 0,
                'profile_code' => $newCode,
                'grade' => (string) $p['grade'],
                'dept_code' => (string) $p['dept_code'],
                'title_ar' => mb_substr((string) $p['title_ar'], 0, 120),
                'screens_target' => 0,
                'prepares_target' => (int) $p['prepares_target'],
                'approves_target' => (int) $p['approves_target'],
                'approval_cap_label' => (string) $p['approval_cap_label'],
                'data_scope' => (string) $p['data_scope'],
                'sensitive_fields' => (string) $p['sensitive_fields'],
                'fixed_rule' => (string) $p['fixed_rule'],
                'version' => ((int) $p['version']) + 1,
                'state' => self::ST_DRAFT,
            ));
            $n = 0;
            foreach ((array) $items as $it) {
                $gate->insert('gov_profile_items', array(
                    'company_id' => 0, 'profile_id' => $id,
                    'item_kind' => (string) $it['item_kind'],
                    'item_ref'  => (string) $it['item_ref'],
                    'allow' => (int) $it['allow'], 'can_add' => (int) $it['can_add'],
                    'can_edit' => (int) $it['can_edit'], 'can_delete' => (int) $it['can_delete'],
                    'seeded_from' => self::SEED_CONSOLE,
                ));
                $n++;
            }
            self::syncScreensTarget($id);
            $ok = \ems_perm_change_log($conn, 'profile', 'clone', array(
                'subject_kind' => 'profile', 'subject_id' => $id,
                'before' => 'نسخ عن ' . $p['profile_code'] . ' اصدار ' . (int) $p['version'],
                'after'  => 'مسودة ' . $newCode . ' بـ' . $n . ' بندا',
                'reason' => $reason, 'source' => 'PolicyWriteService::cloneProfile',
            ));
            if (!$ok) { throw new \RuntimeException('تعذر كتابة سطر الاثر'); }
            return self::done($id, 'نُسخ ' . $p['profile_code'] . ' إلى ' . $newCode . ' بـ' . $n . ' بندا');
        });
    }

    /**
     * ⑪ ضبطُ بوّابةِ التجميد — والرفعُ **مُعلَنٌ ومُسجَّلٌ** لا التفافَ عليه.
     *
     * ◆ **ولماذا يُرفَع أصلًا**: التجميدُ فُتِح لأنّ الكتابةَ كانت بأدواتِ سطرِ
     *   أوامرٍ بلا حارس. وقد صار لها **بابٌ واحدٌ محروسٌ بأثرٍ** — فالبوّابةُ
     *   تنتقل من «سدٍّ دائمٍ» إلى **وضعِ صيانةٍ يملكه مديرُ الصلاحيّات**.
     * ⛔ **والسببُ يُكتب ولا يُدهَس**: عمودُ السببِ كان يتراكم نصًّا بكلِّ رفعٍ
     *   فصار سلسلةَ «رفع مؤقت» مكرَّرة — فالنصُّ يُستبدَل والتاريخُ في سجلِّ الأثر.
     */
    public static function setFreeze(\mysqli $conn, $scope, $active, $reason, $actorId = 0)
    {
        $scope = (string) $scope; $active = $active ? 1 : 0; $reason = trim((string) $reason);
        if (!in_array($scope, array(self::FREEZE_GRANTS, self::FREEZE_ACTIVATION), true)) {
            return self::fail('BAD_SCOPE', 'مدى تجميد غير معروف');
        }
        if ($reason === '') { return self::fail('NO_REASON', 'السبب مطلوب — ولا فتح بوابة بلا سبب مكتوب'); }

        try {
            $cur = \ems_tenant_db()->selectOne('gov_policy_freeze', array(
                'columns' => array('id', 'active'), 'where' => array('scope_code' => $scope)));
        } catch (\Throwable $t) {
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
            return self::fail('READ_FAILED', 'تعذر قراءة حال البوابة');
        }
        if (!$cur) { return self::fail('NO_SCOPE', 'لا سجل لهذا المدى'); }
        if ((int) $cur['active'] === $active) {
            return self::fail('NOTHING', 'البوابة على هذا الحال سلفا');
        }

        return self::write($conn, function () use ($conn, $cur, $scope, $active, $reason) {
            \ems_tenant_db()->update('gov_policy_freeze', array(
                'active' => $active,
                'reason' => mb_substr($reason, 0, 255),
            ), array('id' => (int) $cur['id']));
            $ok = \ems_perm_change_log($conn, 'freeze', $active ? 'close' : 'open', array(
                'subject_kind' => 'freeze', 'subject_id' => (int) $cur['id'],
                'screen_code'  => $scope,
                'before' => ((int) $cur['active'] === 1 ? 'مجمد' : 'مفتوح'),
                'after'  => ($active ? 'مجمد' : 'مفتوح'),
                'reason' => $reason, 'source' => 'PolicyWriteService::setFreeze',
            ));
            if (!$ok) { throw new \RuntimeException('تعذر كتابة سطر الاثر'); }
            return self::done((int) $cur['id'],
                ($active ? 'أُغلقت بوابة ' : 'فُتحت بوابة ') . $scope . ' وسُجل سببها');
        });
    }

    /* ═══════════════════════════════════════════════════════════════════════
       PERM-03 · مصادرُ المنحِ الثلاثةُ الباقية — المؤقّتُ الموقوت
       ───────────────────────────────────────────────────────────────────────
       `gov_authority_grants.source` مفرداتُه أربعٌ، والمنفَّذُ كان `profile`
       وحدَه. والثلاثةُ الباقيةُ ليست بنيةً زائدةً بل «تصميمُنا منفَّذًا جزئيًّا»:
         `delegation` — تفويضُ صاحبِ القالبِ غيرَه مدّةً (إجازةٌ أو انتداب)
         `elevation`  — رفعٌ استثنائيٌّ بشهودٍ ومجيزٍ لمهمّةٍ بعينها
         `escalation` — تصعيدٌ رأسيٌّ بمرجعِ واقعةٍ
       ◆ **وسجلُّ الواقعةِ يسبق المنحة**: `gov_delegations` و`gov_elevations`
         مبنيّان وفارغان — فيُكتب صفُّ الواقعةِ أوّلًا ويُربط بالمنحةِ بمعرِّفِه،
         ولا يُهجَر السجلُّ ويُحشى السببُ نصًّا. [[registry-rule-vs-value]]
       ⛔ **وكلُّ مؤقّتٍ له نهايةٌ إلزاميّة**: `valid_to` شرطُ صحّةٍ لا خيار —
         ومنحةٌ «مؤقّتةٌ» بلا نهايةٍ منحةٌ دائمةٌ بثوبٍ آخر. والقرارُ يفحص
         `valid_to > NOW()` فالانتهاءُ يسري بنفسِه بلا مهمّةٍ دوريّة.
       ═══════════════════════════════════════════════════════════════════════ */

    /** سقوفُ المدّةِ بالساعاتِ لكلِّ مصدرٍ مؤقّت. */
    const TMP_CAPS = array('delegation' => 720, 'elevation' => 24, 'escalation' => 168);

    /**
     * ⑫ منحةٌ مؤقّتةٌ بمصدرِها — تفويضٌ أو رفعٌ أو تصعيد.
     *
     * @param array $d source · hours · reason · from_user (للتفويض) ·
     *                 approver (للرفع) · doc_ref (للتصعيد)
     */
    public static function grantTemporary(\mysqli $conn, $userId, $profileId, array $d, $actorId = 0)
    {
        $userId = (int) $userId; $profileId = (int) $profileId; $actorId = (int) $actorId;
        $source = (string) ($d['source'] ?? '');
        $hours  = (int) ($d['hours'] ?? 0);
        $reason = trim((string) ($d['reason'] ?? ''));

        if (!isset(self::TMP_CAPS[$source])) {
            return self::fail('BAD_SOURCE', 'مصدر منح مؤقت غير معروف');
        }
        if ($userId <= 0 || $profileId <= 0) { return self::fail('BAD_INPUT', 'الفاعل أو القالب غير صحيح'); }
        if ($reason === '') { return self::fail('NO_REASON', 'السبب مطلوب — ولا منح مؤقت بلا سبب'); }
        $cap = self::TMP_CAPS[$source];
        if ($hours < 1) { return self::fail('NO_DURATION', 'المدة مطلوبة — ولا مؤقت بلا نهاية'); }
        if ($hours > $cap) {
            return self::fail('OVER_CAP', 'سقف ' . $source . ' هو ' . $cap . ' ساعة');
        }
        if (self::isFrozen(self::FREEZE_GRANTS)) {
            return self::fail('FROZEN', 'تجميد المنح نافذ — ارفعه من ضبط البوابات ثم أعد المحاولة');
        }

        $fromUser = (int) ($d['from_user'] ?? 0);
        $approver = (int) ($d['approver'] ?? 0);

        try {
            $gate = \ems_tenant_db();
            $u = $gate->selectOne('users', array(
                'columns' => array('id', 'name', 'status'), 'where' => array('id' => $userId)));
            if (!$u) { return self::fail('NO_USER', 'لا مستخدم بهذا المعرف'); }
            if ((string) $u['status'] !== 'active') {
                return self::fail('DEAD_USER', 'الحساب غير حي — ولا منح لحساب غير حي');
            }
            $p = $gate->selectOne('gov_role_profiles', array(
                'columns' => array('profile_id', 'profile_code', 'grade', 'dept_code', 'state'),
                'where'   => array('profile_id' => $profileId)));
            if (!$p) { return self::fail('NO_PROFILE', 'لا قالب بهذا المعرف'); }
            if ((string) $p['state'] !== self::ST_ACTIVE) {
                return self::fail('DRAFT_PROFILE', 'لا يمنح قالب غير نافذ ولو مؤقتا');
            }

            /* ⛔ **ولا يُمنح مؤقّتٌ ثانٍ بنفسِ المصدرِ والقالبِ وهو نافذ**:
                 تراكمُ منحٍ مؤقّتةٍ على الشخصِ يُخفي متى تنتهي أيُّها. */
            $dup = $gate->selectOne('gov_authority_grants', array(
                'columns'  => array('grant_id'),
                'where'    => array('user_id' => $userId, 'profile_id' => $profileId, 'source' => $source),
                'whereRaw' => 'revoked_at IS NULL AND (valid_to IS NULL OR valid_to > NOW())'));
            if ($dup) {
                return self::fail('ALREADY_GRANTED',
                    'للفاعل منحة ' . $source . ' نافذة على هذا القالب رقم ' . (int) $dup['grant_id']);
            }

            if ($source === 'delegation') {
                if ($fromUser <= 0) { return self::fail('NO_DELEGATOR', 'التفويض يحتاج المفوض'); }
                if ($fromUser === $userId) { return self::fail('SELF', 'لا يفوض أحد نفسه'); }
                /* ◆ **ولا يفوّض ما لا يملك**: المفوِّضُ يحمل هذا القالبَ فعلًا. */
                $holds = $gate->selectOne('gov_authority_grants', array(
                    'columns'  => array('grant_id'),
                    'where'    => array('user_id' => $fromUser, 'profile_id' => $profileId),
                    'whereRaw' => 'revoked_at IS NULL AND (valid_to IS NULL OR valid_to > NOW())'));
                if (!$holds) {
                    return self::fail('NOT_HELD', 'المفوض لا يحمل هذا القالب — ولا يفوض أحد ما لا يملك');
                }
            }
            if ($source === 'elevation') {
                /* ⛔ **والرفعُ أربعةُ أطرافٍ بنصِّ المخطَّط** (`chk_elev_four_parties`):
                     مستفيدٌ وشاهدُ مواردَ بشريّةٍ وشاهدٌ ماليٌّ ومجيزٌ أعلى — ولا
                     يصير الرفعُ نافذًا بأقلَّ منها. والقيدُ في القاعدةِ يردُّ
                     الكتابةَ برسالةٍ خامّة، فتُقرأ الشروطُ هنا لتُقال بلغةِ
                     المستخدمِ قبلَ أن يردَّه المحرّك. */
                $hr  = (int) ($d['hr_witness'] ?? 0);
                $fin = (int) ($d['fin_witness'] ?? 0);
                if ($approver <= 0) { return self::fail('NO_APPROVER', 'الرفع الاستثنائي يحتاج مجيزا اعلى'); }
                if ($hr <= 0 || $fin <= 0) {
                    return self::fail('NO_WITNESS',
                        'الرفع الاستثنائي اربعة اطراف: مستفيد وشاهد موارد بشرية وشاهد مالي ومجيز اعلى');
                }
                $parties = array($approver, $hr, $fin);
                if (in_array($userId, $parties, true)) {
                    return self::fail('SELF', 'الطالب لا يكون شاهدا على نفسه ولا مجيزا لها');
                }
                if ($approver === $actorId) {
                    return self::fail('SELF_ISSUE', 'المسند لا يكون المجيز — طالب غير مجيز غير مسند');
                }
                if (count(array_unique($parties)) !== 3) {
                    return self::fail('SAME_PARTY', 'الشاهدان والمجيز ثلاثة اشخاص مختلفين');
                }
                foreach (array('المجيز' => $approver, 'شاهد الموارد البشرية' => $hr,
                               'الشاهد المالي' => $fin) as $lbl => $pid) {
                    $ap = $gate->selectOne('users', array(
                        'columns' => array('id', 'role', 'status'), 'where' => array('id' => $pid)));
                    if (!$ap || (string) $ap['status'] !== 'active') {
                        return self::fail('NO_PARTY', 'لا حساب حي لـ' . $lbl);
                    }
                    if ((int) $ap['role'] === self::ROLE_PERM_ADMIN) {
                        return self::fail('ROLE15_PARTY',
                            'الدور 15 لا يكون ' . $lbl . ' — الاسناد شيء والاجازة شيء');
                    }
                }
            }
        } catch (\Throwable $t) {
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
            return self::fail('READ_FAILED', 'تعذر قراءة حال الفاعل أو القالب');
        }

        $until = date('Y-m-d H:i:s', time() + ($hours * 3600));
        $docRef = mb_substr((string) ($d['doc_ref'] ?? ''), 0, 60);

        $hrW  = (int) ($d['hr_witness'] ?? 0);
        $finW = (int) ($d['fin_witness'] ?? 0);
        return self::write($conn, function () use ($conn, $userId, $profileId, $p, $u, $source,
                                                   $hours, $until, $reason, $actorId,
                                                   $fromUser, $approver, $docRef, $hrW, $finW) {
            $gate = \ems_tenant_db();
            $row = array(
                'user_id'    => $userId,
                'profile_id' => $profileId,
                'source'     => $source,
                'valid_from' => date('Y-m-d H:i:s'),
                'valid_to'   => $until,
                'issued_by'  => $actorId,
                'reason'     => mb_substr($reason . ($docRef !== '' ? ' | سند: ' . $docRef : ''), 0, 255),
            );

            /* ⓐ سجلُّ الواقعةِ أوّلًا، ثمَّ تُربَط المنحةُ بمعرِّفِه. */
            if ($source === 'delegation') {
                $did = (int) $gate->insert('gov_delegations', array(
                    'from_user'  => $fromUser,
                    'to_user'    => $userId,
                    'scope_json' => json_encode(array('profile_id' => $profileId,
                                                      'profile_code' => (string) $p['profile_code']),
                                                JSON_UNESCAPED_UNICODE),
                    'valid_from' => date('Y-m-d H:i:s'),
                    'valid_to'   => $until,
                    'reason'     => mb_substr($reason, 0, 255),
                ));
                $row['delegation_id'] = $did;
            } elseif ($source === 'elevation') {
                $eid = (int) $gate->insert('gov_elevations', array(
                    'user_id'      => $userId,
                    'target_grade' => (string) $p['grade'],
                    'target_dept'  => mb_substr((string) $p['dept_code'], 0, 120),
                    'reason'       => mb_substr($reason, 0, 255),
                    'scope_note'   => mb_substr('قالب ' . $p['profile_code'], 0, 255),
                    'hr_witness'   => $hrW,
                    'fin_witness'  => $finW,
                    'ceo_approver' => $approver,
                    'valid_to'     => $until,
                    'state'        => 'active',
                ));
                $row['elevation_id'] = $eid;
            }

            $id = (int) $gate->insert('gov_authority_grants', $row);
            $who = ($source === 'delegation') ? (' عن #' . $fromUser)
                 : (($source === 'elevation')
                    ? (' بإجازة #' . $approver . ' وشاهدي #' . $hrW . ' و#' . $finW) : '');
            $ok = \ems_perm_change_log($conn, 'grant', $source, array(
                'subject_kind' => 'user', 'subject_id' => $userId,
                'before' => 'بلا منحة ' . $source,
                'after'  => 'مؤقت على ' . $p['profile_code'] . ' حتى ' . $until . $who,
                'reason' => $reason, 'source' => 'PolicyWriteService::grantTemporary',
            ));
            if (!$ok) { throw new \RuntimeException('تعذر كتابة سطر الاثر'); }
            return self::done($id, 'منح ' . $source . ' على ' . $p['profile_code']
                                 . ' لمدة ' . $hours . ' ساعة، ينتهي ' . $until);
        });
    }

    /** المنحُ المؤقّتةُ النافذةُ الآن — للعرضِ والمحاسبة. */
    public static function liveTemporaryGrants()
    {
        self::$lastReadError = '';
        try {
            $rows = \ems_tenant_db()->select('gov_authority_grants', array(
                'whereRaw' => "source <> 'profile' AND revoked_at IS NULL"
                            . ' AND valid_to IS NOT NULL AND valid_to > NOW()',
                'orderBy'  => 'valid_to', 'limit' => 200));
            return is_array($rows) ? $rows : array();
        } catch (\Throwable $t) {
            self::$lastReadError = (string) $t->getMessage();
            return array();
        }
    }

    /** حاملو قالبٍ بعينِه — لاختيارِ المفوِّضِ في نموذجِ التفويض. */
    public static function holdersOf($profileId)
    {
        try {
            $rows = \ems_tenant_db()->select('gov_authority_grants', array(
                'columns'  => array('user_id'),
                'where'    => array('profile_id' => (int) $profileId),
                'whereRaw' => 'revoked_at IS NULL AND (valid_to IS NULL OR valid_to > NOW())'));
            $out = array();
            foreach ((array) $rows as $r) { $out[] = (int) $r['user_id']; }
            return $out;
        } catch (\Throwable $t) { return array(); }
    }

    /** قراءةُ قالبٍ بمعرِّفِه — بالبوّابةِ لا باستعلامٍ خامّ. */
    public static function readProfile($profileId)
    {
        try {
            return \ems_tenant_db()->selectOne('gov_role_profiles', array(
                'where' => array('profile_id' => (int) $profileId)));
        } catch (\Throwable $t) {
            self::$lastReadError = (string) $t->getMessage();
            return null;
        }
    }

    /** بنودُ قالبٍ مرتَّبةً — للبنّاءِ ولشاشةِ العرض. */
    public static function profileItems($profileId, $kind = 'screen')
    {
        try {
            $w = array('profile_id' => (int) $profileId);
            if ($kind !== null) { $w['item_kind'] = (string) $kind; }
            $rows = \ems_tenant_db()->select('gov_profile_items', array(
                'where'   => $w,
                'orderBy' => 'item_kind, item_ref'));
            return is_array($rows) ? $rows : array();
        } catch (\Throwable $t) {
            self::$lastReadError = (string) $t->getMessage();
            return array();
        }
    }

    /** كلُّ القوالبِ بحالاتِها — لشاشةِ الإدارة. */
    public static function allProfiles($state = null)
    {
        try {
            $opts = array('orderBy' => 'profile_code');
            if ($state !== null) { $opts['where'] = array('state' => (string) $state); }
            $rows = \ems_tenant_db()->select('gov_role_profiles', $opts);
            return is_array($rows) ? $rows : array();
        } catch (\Throwable $t) {
            self::$lastReadError = (string) $t->getMessage();
            return array();
        }
    }

    /* ═══════════════════════════════════════════════════════════════════════
       PERM-02 · قرّاءُ المحاسبة — الشاشةُ تعرض ولا تسأل القاعدة
       ───────────────────────────────────────────────────────────────────────
       ⛔ **والقراءةُ بالبوّابةِ لا باستعلامٍ خامّ**: سجلُّ الأثرِ والاستثناءاتُ
         سجلّاتُ **مستأجِر**، فاستعلامٌ خامٌّ عليهما في شاشةٍ يرفع سقّاطةَ GAP-29
         ملفًّا فوقَ أساسِها — والأساسُ «يُخفَّض ولا يُرفع».
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * سطورُ سجلِّ تغييرِ الصلاحيّاتِ مرشَّحةً — «من غيّر ماذا لمن ومتى ولماذا».
     *
     * @param array $f layer · verb · subject_kind · subject_id · actor · limit
     */
    public static function changeLog(array $f = array())
    {
        self::$lastReadError = '';
        try {
            $where = array();
            foreach (array('layer', 'verb', 'subject_kind') as $k) {
                if (!empty($f[$k])) { $where[$k] = (string) $f[$k]; }
            }
            if (!empty($f['subject_id'])) { $where['subject_id'] = (int) $f['subject_id']; }
            if (!empty($f['actor']))      { $where['actor_user_id'] = (int) $f['actor']; }
            $opts = array('orderBy' => 'id DESC',
                          'limit'   => max(1, min(1000, (int) ($f['limit'] ?? 300))));
            if ($where) { $opts['where'] = $where; }
            $rows = \ems_tenant_db()->select('perm_change_log', $opts);
            return is_array($rows) ? $rows : array();
        } catch (\Throwable $t) {
            self::$lastReadError = (string) $t->getMessage();
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
            return array();
        }
    }

    /** المفرداتُ الحيّةُ في السجلِّ — للمرشِّحاتِ بلا تخمين. */
    public static function changeLogVocab()
    {
        $out = array('layer' => array(), 'verb' => array());
        try {
            $rows = \ems_tenant_db()->select('perm_change_log',
                array('columns' => array('layer', 'verb'), 'limit' => 5000));
            foreach ((array) $rows as $r) {
                $out['layer'][(string) $r['layer']] = 1;
                $out['verb'][(string) $r['verb']] = 1;
            }
        } catch (\Throwable $t) { /* المرشِّحُ يفرغ ولا يُسقط الشاشة */ }
        $out['layer'] = array_keys($out['layer']);
        $out['verb']  = array_keys($out['verb']);
        sort($out['layer']); sort($out['verb']);
        return $out;
    }

    /** الاستثناءاتُ الحيّةُ (فتحٌ اضطراريٌّ لم ينتهِ) — للمحاسبة اللحظيّة. */
    public static function liveExceptions()
    {
        self::$lastReadError = '';
        try {
            $rows = \ems_tenant_db()->select('permission_exceptions', array(
                'where'    => array('state' => 'active', 'is_break_glass' => 1),
                'whereRaw' => 'valid_to IS NOT NULL AND valid_to > NOW()',
                'orderBy'  => 'valid_to DESC', 'limit' => 200));
            return is_array($rows) ? $rows : array();
        } catch (\Throwable $t) {
            self::$lastReadError = (string) $t->getMessage();
            return array();
        }
    }

    /** أسماءُ الفاعلين بمعرِّفاتِهم — تحلُّ السطورَ بلا وصلةٍ في الاستعلام. */
    public static function nameMap(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) { return array(); }
        $out = array();
        try {
            $rows = \ems_tenant_db()->select('users', array(
                'columns' => array('id', 'name', 'role'),
                'whereIn' => array('id' => $ids), 'limit' => 1000));
            foreach ((array) $rows as $u) { $out[(int) $u['id']] = (string) $u['name']; }
        } catch (\Throwable $t) {
            /* ◆ **والبديلُ قراءةٌ واحدةٌ واسعةٌ لا فشلٌ صامت**: إن لم تدعم
                 البوّابةُ `whereIn` تُقرأ الأسماءُ كلُّها وتُرشَّح هنا. */
            try {
                $rows = \ems_tenant_db()->select('users',
                    array('columns' => array('id', 'name'), 'limit' => 5000));
                $want = array_flip($ids);
                foreach ((array) $rows as $u) {
                    if (isset($want[(int) $u['id']])) { $out[(int) $u['id']] = (string) $u['name']; }
                }
            } catch (\Throwable $t2) { self::$lastReadError = (string) $t2->getMessage(); }
        }
        return $out;
    }

    /**
     * يُبقي `screens_target` مطابقًا لعددِ البنودِ المسموحة.
     * ◆ **فعمودٌ يُعلن هدفًا ولا يطابق المبنيَّ يُقرأ ضمانًا وهو رقمٌ ميّت.**
     */
    private static function syncScreensTarget($profileId)
    {
        try {
            $n = \ems_tenant_db()->count('gov_profile_items', array(
                'where' => array('profile_id' => (int) $profileId, 'item_kind' => 'screen', 'allow' => 1)));
            \ems_tenant_db()->update('gov_role_profiles',
                array('screens_target' => (int) $n), array('profile_id' => (int) $profileId));
        } catch (\Throwable $t) {
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
        }
    }

    /**
     * ينفّذ كتابةً في معاملةٍ واحدة — والأثرُ شرطُ إتمامِها.
     *
     * ⛔ **ولا كتابةَ بلا أثر**: أيُّ رميةٍ داخلَ العمليّةِ تُرجِع كلَّ شيء.
     */
    private static function write(\mysqli $conn, callable $fn)
    {
        $conn->begin_transaction();
        try {
            $out = $fn();
            $conn->commit();
            return $out;
        } catch (\Throwable $t) {
            $conn->rollback();
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
            return self::fail('WRITE_FAILED', 'تعذر اتمام الكتابة: ' . $t->getMessage());
        }
    }
}
