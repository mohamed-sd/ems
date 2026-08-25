<?php
/**
 * app/Services/Portal/VisibilityGuard.php — حارسُ الظهور بمستوى العنصر (H-17)
 * ═══════════════════════════════════════════════════════════════════════════
 * USR-01 §4: «كلُّ عنصرٍ في البوابة يُفحص **بثلاثة أبعادٍ معًا** قبل ظهوره —
 * وفشلُ أيٍّ منها يمنع، **والمنعُ افتراضيٌّ حتى يُمنح**»:
 *   ① الصفةُ والصلاحية — الفشلُ: **Hide Node** (لا يُصيَّر أصلًا · لا خطأ).
 *   ② العلاقةُ بالشخص المعروض — الفشلُ: **403 مسجَّلةٌ** في سجل الأمن.
 *   ③ مفتاحُ الموارد البشرية (H-16) — الفشلُ: **مخفيٌّ بقرارٍ موثَّق**.
 *
 * «ولا يُصيَّر عنصرٌ لم يُفحص» — فالشاشةُ تسأل الحارسَ عن كل عنصرٍ قبل رسمه،
 * والقرارُ ثلاثيٌّ: allow · hide (صامت) · deny (مسجَّل).
 */

namespace App\Services\Portal;

require_once __DIR__ . '/../../../includes/catch_log.php';

require_once __DIR__ . '/VisibilityPolicyService.php';
require_once __DIR__ . '/CapacityService.php';

class VisibilityGuard
{
    /**
     * البُعد ①: ما يمنحه نوعُ الصفة — **الخارجيُّ لا يرى عناصرَ داخلية**
     * (USR-01 §4-⑤: «نطاقَه فقط — ولا يرى بياناتٍ داخليةً للشركة»).
     * القائمةُ سماحٌ لا منعٌ (نمطُ H-02): ما لم يُذكر للصفة الخارجية لا يُصيَّر.
     */
    const EXTERNAL_ELEMENTS = array(
        'supplier_supervisor' => array('card.units', 'card.requests', 'card.approvals',
                                       'card.tickets', 'card.achievement', 'card.activity'),
        'client_rep'          => array('card.units', 'card.requests', 'card.tickets',
                                       'card.achievement', 'card.activity'),
    );

    /** الأدوارُ التي ترى كلَّ المنتسبين (③ الموارد البشرية في مصفوفة §4) */
    const COMPANY_WIDE_ROLES = array('4', '15', '-1');

    /**
     * الفحصُ الثلاثي لعنصرٍ × مشاهدٍ × معروض.
     *
     * @param array $viewer  {account_id, role, capacity_type, scope_type?, scope_id?}
     * @param array $subject {account_id, capacity_type?, project_id?, supplier_id?, client_id?}
     * @return array{decision:string,dimension:int,reason:string} decision: allow|hide|deny
     */
    public static function check($conn, $gate, $companyId, array $viewer, $elementCode, array $subject)
    {
        $elementCode = (string) $elementCode;
        $viewerAcc = (int) ($viewer['account_id'] ?? 0);
        $subjectAcc = (int) ($subject['account_id'] ?? 0);

        // ── البُعد ① — الصفةُ والصلاحية: Hide Node لا خطأ ───────────────────
        $vType = (string) ($viewer['capacity_type'] ?? '');
        if (isset(self::EXTERNAL_ELEMENTS[$vType])
            && !in_array($elementCode, self::EXTERNAL_ELEMENTS[$vType], true)) {
            return array('decision' => 'hide', 'dimension' => 1,
                'reason' => 'عنصر خارج ما تمنحه الصفة الخارجية — لا يصير أصلا (Hide Node)');
        }

        // ── البُعد ② — العلاقةُ بالشخص المعروض: 403 مسجَّلة ─────────────────
        $relation = self::relation($conn, $gate, $companyId, $viewer, $subject);
        if ($relation === 'none') {
            self::logDeny($conn, $companyId, $viewerAcc, $elementCode, $subjectAcc,
                'خارج العلاقة: لا هو نفسه ولا مرؤوسه ولا في نطاقه');
            return array('decision' => 'deny', 'dimension' => 2,
                'reason' => 'الشخص المعروض خارج علاقتك — **403 مسجلة في سجل الأمن**');
        }

        // ── البُعد ③ — مفتاحُ الموارد البشرية (H-16): قرارٌ موثَّق ──────────
        // السياقُ سياقُ **المشاهد** (هل فُتح العنصرُ لهذا الحساب؟) — والحساسُ
        // مغلقٌ افتراضًا فلا يفتح غيرَه إلا منحٌ صريحٌ بمدةٍ وسبب.
        $ctx = array('account_id' => $viewerAcc);
        if ($vType !== '') { $ctx['capacity_type'] = $vType; }
        if (!empty($viewer['scope_type']) && !empty($viewer['scope_id'])) {
            $st = (string) $viewer['scope_type'];
            if ($st === 'project')  { $ctx['project_id'] = (int) $viewer['scope_id']; }
            if ($st === 'supplier') { $ctx['supplier_id'] = (int) $viewer['scope_id']; }
            if ($st === 'client')   { $ctx['client_id'] = (int) $viewer['scope_id']; }
        }
        $d = VisibilityPolicyService::decide($conn, $gate, $companyId, $elementCode, $ctx);

        // «الشخصُ عن نفسه يرى كلَّ بطاقاته» — لكن مفتاحًا **مضبوطًا صراحةً**
        // على الإغلاق يغلب حتى للذات (نصُّ الواجهة: «عناصرُ الراتب مغلقةٌ
        // لهذا الحساب بقرار الموارد البشرية») — أما **الافتراضُ** المغلق
        // للحساس فلا يحجب صاحبَ البيانات عن بياناته.
        if (!$d['visible'] && $relation === 'self' && $d['source'] === 'element_default') {
            $el = VisibilityPolicyService::element($conn, $elementCode);
            if ($el && (int) $el['active'] === 1) {
                return array('decision' => 'allow', 'dimension' => 3,
                    'reason' => 'صاحب البيانات يرى بياناته — والافتراض المغلق يحجب الغير لا الذات');
            }
        }
        if (!$d['visible']) {
            return array('decision' => 'hide', 'dimension' => 3,
                'reason' => 'مخفي بقرار موثق (' . $d['source'] . ')'
                          . ($d['reason'] !== null ? ' — ' . $d['reason'] : ''));
        }

        return array('decision' => 'allow', 'dimension' => 0, 'reason' => 'اجتاز الأبعاد الثلاثة');
    }

    /**
     * البُعد ②: العلاقة — نفسُه · مرؤوسُه المباشر · نطاقُه · كلُّ الكيان.
     * @return string self|subordinate|scope|company|none
     */
    public static function relation($conn, $gate, $companyId, array $viewer, array $subject)
    {
        $viewerAcc = (int) ($viewer['account_id'] ?? 0);
        $subjectAcc = (int) ($subject['account_id'] ?? 0);

        if ($viewerAcc > 0 && $viewerAcc === $subjectAcc) { return 'self'; }

        // كلُّ الكيان: الموارد البشرية ومديرُ الصلاحيات والسوبر
        if (in_array((string) ($viewer['role'] ?? ''), self::COMPANY_WIDE_ROLES, true)) {
            return 'company';
        }

        // المرؤوسُ المباشر: users.parent_id يشير إلى حساب المدير
        if ($viewerAcc > 0 && $subjectAcc > 0) {
            $r = $conn->query("SELECT parent_id FROM users WHERE id = " . $subjectAcc . " LIMIT 1");
            $row = $r ? $r->fetch_assoc() : null;
            if ($row && (string) $row['parent_id'] !== '' && (int) $row['parent_id'] === $viewerAcc) {
                return 'subordinate';
            }
        }

        // النطاقُ المشترك: صفةُ المشاهد وصفةُ المعروض على النطاق نفسِه
        $vScopeT = (string) ($viewer['scope_type'] ?? '');
        $vScopeI = (string) ($viewer['scope_id'] ?? '');
        if ($vScopeT !== '' && $vScopeI !== '' && $subjectAcc > 0) {
            try {
                $rows = $gate->scopedQuery(array('scope' => array('c' => 'user_capacities')),
                    "SELECT c.id FROM user_capacities c
                      WHERE {TENANT_SCOPE} AND c.account_id = ? AND c.state = 'active'
                        AND c.scope_type = ? AND c.scope_id = ? LIMIT 1",
                    array($subjectAcc, $vScopeT, (int) $vScopeI));
                if ($rows) { return 'scope'; }
            } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'لا نطاق مشتركا يثبت'); /* لا نطاقَ مشتركًا يُثبت */ }
        }

        return 'none';
    }

    /**
     * فحصُ قائمةِ عناصرَ دفعةً — للشاشات: «لا يُصيَّر عنصرٌ لم يُفحص».
     * يعيد العناصرَ المسموحة فقط؛ وdeny واحدٌ يوقف الصفحةَ كلَّها (403).
     */
    public static function filterElements($conn, $gate, $companyId, array $viewer, array $subject, array $codes)
    {
        $allowed = array();
        foreach ($codes as $code) {
            $v = self::check($conn, $gate, $companyId, $viewer, (string) $code, $subject);
            if ($v['decision'] === 'deny') {
                return array('ok' => false, 'code' => 403, 'reason' => $v['reason'], 'allowed' => array());
            }
            if ($v['decision'] === 'allow') { $allowed[] = (string) $code; }
        }
        return array('ok' => true, 'code' => 200, 'reason' => '', 'allowed' => $allowed);
    }

    /** «كلُّ deny يُقيَّد» — سجلُّ الأمن (N-02 إلى أن يبنيَ H-18 سجلَّ البوابة) */
    private static function logDeny($conn, $companyId, $viewerAcc, $elementCode, $subjectAcc, $why)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'portal', 'visibility_guard', 'denied_403', (int) $subjectAcc,
            array(),
            array('element' => (string) $elementCode, 'subject_account' => (int) $subjectAcc,
                  'why' => (string) $why),
            array('company_id' => (int) $companyId, 'user_id' => (int) $viewerAcc));
    }
}
