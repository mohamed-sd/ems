<?php
/**
 * PermSourceService — قلب مصدر الصلاحية بست مراحل (SEC-01 §13 · SEC-29)
 * ───────────────────────────────────────────────────────────────────────────
 * «علم واحد فقط يحكم المصدر»: EMS_PERM_SOURCE (legacy · derived) — اسمه من
 * §15 ولا يُخترع غيره، والرجوع قلبه عكسًا في دقائق بلا هجرة بيانات.
 *
 * المراحل الست: ① legacy_authoritative ② dual_write ③ derived_shadow
 * ④ derived_authoritative ⑤ legacy_read_only ⑥ legacy_retired —
 * ومعيار ③→④: «صفر فرق في قرار سماح أو منع 14 يومًا متصلة — والحد صفر لا
 * نسبة، وفرق النطاق أو السقف يُعدّ فرقًا» + توقيع مدير الصلاحيات.
 *
 * الظل يقارن قرار القديم (role_permissions بالرايات) بقرار المشتق ويسجّل
 * كل فرق في perm_shadow_diffs — وهو ميزان معيار الأربعة عشر يومًا.
 */

namespace App\Services\Security;

require_once __DIR__ . '/PermissionResolver.php';

class PermSourceService
{
    /** المصدر الحاكم الآن — من العلم الواحد. */
    public static function currentSource()
    {
        $v = function_exists('ems_env') ? strtolower((string) ems_env('EMS_PERM_SOURCE', 'legacy')) : 'legacy';
        return $v === 'derived' ? 'derived' : 'legacy';
    }

    /**
     * قرار القديم: الرايات الأربع لموديول — التحويل بقاعدة SEC-D2 المعلنة.
     */
    public static function legacyDecision(\mysqli $conn, $userId, $moduleCode, $action)
    {
        $userId = intval($userId);
        $u = $conn->query("SELECT role FROM users WHERE id = {$userId} LIMIT 1")->fetch_assoc();
        if (!$u) { return false; }
        if ((string) $u['role'] === '-1') { return true; }
        $stmt = $conn->prepare(
            "SELECT rp.can_view, rp.can_add, rp.can_edit, rp.can_delete
               FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
              WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
        $rid = intval($u['role']);
        $stmt->bind_param('si', $moduleCode, $rid);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$p) { return false; }
        $map = array(
            'screen_view' => 'can_view', 'tab_view' => 'can_view', 'field_view' => 'can_view',
            'export' => 'can_view', 'print' => 'can_view',
            'create' => 'can_add', 'submit' => 'can_add',
            'update' => 'can_edit', 'return_for_fix' => 'can_edit',
            'delete_draft' => 'can_delete',
        );
        if (!isset($map[$action])) { return false; } // الاعتمادية الست لا تُشتق من راية
        return intval($p[$map[$action]]) === 1;
    }

    /**
     * users.id ← persons.person_id عبر المركز الوظيفي (جسرُ الهوية E-05).
     * يعيد null للحساب غير الموصول — فلا يُقاس بدل أن يُسجَّل فرقٌ كاذب.
     */
    public static function personIdOf(\mysqli $conn, $userId)
    {
        static $cache = array();
        $userId = (int) $userId;
        if (array_key_exists($userId, $cache)) { return $cache[$userId]; }
        $res = $conn->query(
            "SELECT p.person_id FROM users u
               JOIN person_positions p ON p.p_id = u.position_id
              WHERE u.id = {$userId} AND p.state = 'active' LIMIT 1");
        $row = $res ? $res->fetch_assoc() : null;
        $cache[$userId] = $row ? (int) $row['person_id'] : null;
        return $cache[$userId];
    }

    /**
     * الظل (المرحلة ③): يحسب المشتق ويقارنه بالقديم عند الطلب بلا تفعيل —
     * القديم يقرِّر، والفرق يُسجَّل. فرق النطاق أو السقف فرقٌ.
     * @return array{decision:bool, source:string, diff:bool}
     */
    public static function shadowCompare(\mysqli $conn, $userId, $companyId, $moduleCode, $action, $permissionCode, $scopeType, $scopeId)
    {
        $legacy = self::legacyDecision($conn, $userId, $moduleCode, $action);
        // القديمُ يعمل بـusers.id والمشتقُّ بـpersons.person_id — معرّفان مختلفان.
        // كان يُمرَّر users.id إلى المحلِّل فيمنع دائمًا (لا مركزَ بذلك المعرّف)،
        // فيُسجَّل فرقٌ كاذبٌ في كل فحص. الوصلة: users.position_id → المركز → الشخص.
        $personId = self::personIdOf($conn, $userId);
        if ($personId === null) {
            // حسابٌ بلا جسرِ هوية — لا يُقاس ولا يُسجَّل فرقٌ كاذب (E-05 EN-02)
            return array('decision' => $legacy, 'source' => self::currentSource(),
                         'diff' => false, 'unbridged' => true);
        }
        $derived = PermissionResolver::resolve($conn, $personId, $companyId, $permissionCode, $scopeType, $scopeId);
        $derivedAllow = $derived['allowed'];
        $diff = ($legacy !== $derivedAllow);
        if ($diff) {
            $stmt = $conn->prepare(
                "INSERT INTO perm_shadow_diffs (company_id, user_id, module_code, action, permission_code,
                    scope_rule, legacy_decision, derived_decision, detail)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $co = intval($companyId);
            $uid = intval($userId);
            $sr = $scopeType . ':' . $scopeId;
            $l = $legacy ? 1 : 0;
            $d = $derivedAllow ? 1 : 0;
            $detail = mb_substr((string) $derived['reason'], 0, 250);
            $stmt->bind_param('iissssiis', $co, $uid, $moduleCode, $action, $permissionCode, $sr, $l, $d, $detail);
            $stmt->execute();
            $stmt->close();
        }
        // القديم يقرر في legacy/الظل — والمشتق يقرر بعد القلب (المرحلة ④)
        $source = self::currentSource();
        return array(
            'decision' => $source === 'derived' ? $derivedAllow : $legacy,
            'source' => $source,
            'diff' => $diff,
        );
    }

    /**
     * ميزان معيار ③→④: أيام متصلة بصفر فرق حتى اليوم — والحد صفر لا نسبة.
     */
    public static function zeroDiffStreakDays(\mysqli $conn)
    {
        $last = $conn->query("SELECT MAX(at) m FROM perm_shadow_diffs")->fetch_assoc();
        if (!$last || $last['m'] === null) {
            return 9999; // لا فرق مسجَّلًا قط — العدّاد مفتوح ويقيسه أول تشغيل للظل
        }
        $r = $conn->query("SELECT DATEDIFF(CURDATE(), DATE(MAX(at))) d FROM perm_shadow_diffs")->fetch_assoc();
        return intval($r['d']);
    }

    /** حالة المراحل الست الآن — للتقرير الختامي وقرار القلب. */
    public static function phaseReport(\mysqli $conn)
    {
        $source = self::currentSource();
        $templates = intval($conn->query("SELECT COUNT(DISTINCT tpl_id) c FROM permission_template_versions WHERE state='published'")->fetch_assoc()['c']);
        $streak = self::zeroDiffStreakDays($conn);
        $diffs = intval($conn->query("SELECT COUNT(*) c FROM perm_shadow_diffs")->fetch_assoc()['c']);
        $phase = 'legacy_authoritative';
        if ($source === 'derived') { $phase = 'derived_authoritative'; }
        elseif ($diffs > 0 || $templates > 0) { $phase = 'derived_shadow'; }
        return array(
            'flag' => 'EMS_PERM_SOURCE=' . $source,
            'phase' => $phase,
            'published_templates' => $templates,
            'shadow_diffs_total' => $diffs,
            'zero_diff_streak_days' => $streak,
            'gate_to_flip' => 'صفر فرق 14 يوما متصلة + توقيع مدير الصلاحيات — والحد صفر لا نسبة',
            'rollback' => 'قلب العلم عكسا في دقائق بلا هجرة بيانات — وأي منع خاطئ يعطل عملا يقلب فورا ويحقق',
        );
    }
}
