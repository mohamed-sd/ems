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
class PolicyWriteService
{
    /** مديات التجميد كما في `gov_policy_freeze.scope_code`. */
    const FREEZE_GRANTS     = 'grants';
    const FREEZE_ACTIVATION = 'profile_activation';

    /** الدور 15 — إدارةُ الصلاحيات. مُسنِدٌ لا مجيزَ كسرٍ ولا معتمدَ مسودّة. */
    const ROLE_PERM_ADMIN = 15;

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

            $live = $gate->selectOne('gov_authority_grants', array(
                'columns'  => array('grant_id', 'profile_id'),
                'where'    => array('user_id' => $userId),
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
                'orderBy' => 'CAST(role AS UNSIGNED), name',
            ));
            $out = array();
            foreach ((array) $rows as $u) {
                if (!isset($taken[(int) $u['id']])) { $out[] = $u; }
            }
            return $out;
        } catch (\Throwable $t) {
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
            if (function_exists('ems_catch_log')) { \ems_catch_log($t, __METHOD__); }
            return array();
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
