<?php
/**
 * app/Services/Portal/CapacityService.php — طبقةُ الصفات (H-15)
 * ═══════════════════════════════════════════════════════════════════════════
 * USR-01 §2: «الشخصُ واحدٌ، والصفاتُ متعددةٌ ومتزامنة — وهذا مفتاحُ خدمة كل
 * الفئات بمنصةٍ واحدة» · §2-④: «حزمةُ صلاحياتٍ مرتبطةٌ **بالصفة لا بالشخص**
 * — فانتهاءُ العقد أو التفويض يُسقطها آليًّا» · §2-⑤: «النطاقُ يُحقن في كل
 * استعلامٍ بنيويًّا».
 *
 * ── أربعُ قواعدَ تحكم كلَّ صفةٍ هنا ─────────────────────────────────────────
 * ① **لا صفةَ بلا مصدر**: عقدٌ بمرجعه (CHECK) أو تفويضٌ — والموروثُ قبل
 *    الطبقة **يُعلَن بملاحظته** ولا يُلفَّق له عقد.
 * ② **الانتهاءُ تجميدٌ لا حذف**: مصدرٌ انتهى ⇒ `frozen` بسببه ووقته —
 *    والسجلُّ يبقى للقراءة (U8: «الدخولُ يبقى والصفةُ مجمَّدة»).
 * ③ **الاشتقاقُ عاطل** (idempotent): إعادتُه لا تكرّر صفًّا — والفريدُ
 *    بالحساب (الشخصُ الخارجيُّ بلا `person_id`).
 * ④ **التبديلُ بين صفات الحساب نفسِه حصرًا**: صفةُ غيرِه 403 مسجَّلةً،
 *    والمجمَّدةُ 409 — والدورُ الفعّالُ في الجلسة دورُ الصفة لا دورُ الشخص.
 */

namespace App\Services\Portal;

require_once __DIR__ . '/../../../includes/catch_log.php';

class CapacityService
{
    const CAPACITY_TYPES = array('employee', 'project_employee', 'operator', 'technician',
        'shift_supervisor', 'project_manager', 'supplier_supervisor', 'client_rep',
        'auditor', 'executive');

    const CAPACITY_AR = array(
        'employee' => 'موظفٌ مؤسسي', 'project_employee' => 'موظفُ مشروع',
        'operator' => 'مشغّل', 'technician' => 'فني',
        'shift_supervisor' => 'مشرفُ ورديات', 'project_manager' => 'مدير مشروع',
        'supplier_supervisor' => 'مشرف مورد', 'client_rep' => 'ممثل عميل',
        'auditor' => 'مدقق', 'executive' => 'إدارة عليا',
    );

    /** خريطةُ فئة العقد (H-08) إلى الصفة — والمشغّلُ من فئته لا من ظنّ */
    const CONTRACT_CATEGORY_MAP = array(
        'permanent' => 'employee',
        'project'   => 'project_employee',
        'operator'  => 'operator',
    );

    // ═════════════════════════════════════════════════════════════════════
    // الشريحة ① — الاشتقاق من القائم (عاطلٌ · تجريبٌ افتراضًا)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * اشتقاقُ الصفات من الحسابات والعقود القائمة — «① اشتقاقُ الصفات من
     * العقود والتفويضات القائمة» (USR-01 §9.3-الترحيل).
     *
     * @return array{ok:bool,dry:bool,created:int,skipped:int,rows:array,declared:array}
     */
    public static function derive($conn, $gate, $companyId, $actor, $dry = true)
    {
        $out = array('ok' => true, 'dry' => (bool) $dry, 'created' => 0, 'skipped' => 0,
                     'rows' => array(), 'declared' => array());

        $users = array();
        try {
            // (بلا `company_id` في النص — `scopedQuery` ترفضه يدويًّا والبوابةُ تحقنه)
            $users = $gate->scopedQuery(array('scope' => array('u' => 'users')),
                "SELECT u.id, u.employee_id, u.supplier_entity_id, u.role,
                        u.project_id, u.created_at
                   FROM users u
                  WHERE {TENANT_SCOPE} AND u.status = 'active' AND COALESCE(u.is_deleted,0)=0
                  ORDER BY u.id");
        } catch (\Throwable $t) { $out['ok'] = false; return $out; }

        foreach ($users as $u) {
            $accountId = (int) $u['id'];
            $role = (string) $u['role'];
            $plan = null;

            // ── مشرفُ مورد: نطاقُه مورده — من `users.supplier_entity_id` (H-20)
            if ((int) $u['supplier_entity_id'] > 0) {
                $plan = array(
                    'capacity_type' => 'supplier_supervisor',
                    'scope_type' => 'supplier', 'scope_id' => (int) $u['supplier_entity_id'],
                    'source_type' => 'delegation', 'source_id' => null,
                    'source_note' => 'تفويض مشرف مورد قائم قبل طبقة الصفات — يعلن ولا يلفق',
                    'valid_from' => substr((string) $u['created_at'], 0, 10), 'valid_to' => null,
                );
            } elseif ((int) $u['employee_id'] > 0) {
                // ── داخليٌّ بعقده النشط من سجل H-08 — الصفةُ من فئة العقد
                $ec = null;
                try {
                    $rows = $gate->scopedQuery(array('scope' => array('c' => 'employee_contracts')),
                        "SELECT c.id, c.category, c.project_id, c.start_date, c.end_date, c.state
                           FROM employee_contracts c
                          WHERE {TENANT_SCOPE} AND c.employee_id = ? AND c.state = 'active'
                            AND COALESCE(c.is_deleted,0)=0
                          ORDER BY c.start_date DESC LIMIT 1", array((int) $u['employee_id']));
                    $ec = $rows ? $rows[0] : null;
                } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $ec'); $ec = null; }

                if ($ec) {
                    $cat = (string) $ec['category'];
                    $ctype = isset(self::CONTRACT_CATEGORY_MAP[$cat])
                           ? self::CONTRACT_CATEGORY_MAP[$cat] : 'employee';
                    $scopeType = ((int) $ec['project_id'] > 0 && $ctype !== 'employee')
                               ? 'project' : 'company';
                    $plan = array(
                        'capacity_type' => $ctype,
                        'scope_type' => $scopeType,
                        'scope_id' => $scopeType === 'project' ? (int) $ec['project_id'] : null,
                        'source_type' => 'contract', 'source_id' => (int) $ec['id'],
                        'source_note' => null,
                        'valid_from' => (string) $ec['start_date'],
                        'valid_to' => ($ec['end_date'] !== null && $ec['end_date'] !== '')
                                    ? (string) $ec['end_date'] : null,
                    );
                } else {
                    // حسابٌ بلا عقدٍ نشط — **تفويضٌ موروثٌ يُعلَن** (قاعدة ①)
                    $plan = array(
                        'capacity_type' => 'employee',
                        'scope_type' => 'company', 'scope_id' => null,
                        'source_type' => 'delegation', 'source_id' => null,
                        'source_note' => 'حساب قائم قبل طبقة الصفات بلا عقد نشط في السجل — تفويض موروث يعلن',
                        'valid_from' => substr((string) $u['created_at'], 0, 10), 'valid_to' => null,
                    );
                    $out['declared'][] = 'حساب #' . $accountId . ': بلا عقد نشط — صفة موظف بتفويض موروث معلن';
                }
            } else {
                // حسابٌ بلا موظفٍ ولا مورد (نظاميٌّ/خدمي) — تفويضٌ موروثٌ معلَن
                $plan = array(
                    'capacity_type' => 'executive',
                    'scope_type' => 'company', 'scope_id' => null,
                    'source_type' => 'delegation', 'source_id' => null,
                    'source_note' => 'حساب بلا سجل موظف ولا مورد — تفويض موروث يعلن',
                    'valid_from' => substr((string) $u['created_at'], 0, 10), 'valid_to' => null,
                );
                $out['declared'][] = 'حساب #' . $accountId . ': بلا موظف ولا مورد — صفة إدارة بتفويض معلن';
            }

            if ($plan === null) { continue; }
            $plan['role'] = $role;
            $plan['person_id'] = (int) $u['employee_id'] > 0 ? (int) $u['employee_id'] : null;
            $plan['account_id'] = $accountId;
            $plan['company_id'] = (int) $companyId;

            // ③ العطالة: الفريدُ (حساب × صفة × نطاق × بداية) — الموجودُ يُتخطى
            $exists = self::findExisting($gate, $plan);
            if ($exists) { $out['skipped']++; continue; }

            $out['rows'][] = $plan;
            if (!$dry) {
                try {
                    $gate->insert('user_capacities', array(
                        'company_id' => $plan['company_id'],
                        'person_id' => $plan['person_id'],
                        'account_id' => $plan['account_id'],
                        'capacity_type' => $plan['capacity_type'],
                        'role' => $plan['role'],
                        'scope_type' => $plan['scope_type'],
                        'scope_id' => $plan['scope_id'],
                        'source_type' => $plan['source_type'],
                        'source_id' => $plan['source_id'],
                        'source_note' => $plan['source_note'],
                        'valid_from' => $plan['valid_from'],
                        'valid_to' => $plan['valid_to'],
                        'state' => 'active',
                        'created_by' => (int) $actor ?: null,
                    ));
                } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'خطة قدرة واحدة فشلت — تحصى متخطاة وتستمر البقية'); $out['skipped']++; continue; }
            }
            $out['created']++;
        }

        if (!$dry && $out['created'] > 0) {
            self::audit($conn, $companyId, $actor, 'derive', 0, array(),
                array('created' => $out['created'], 'skipped' => $out['skipped']));
        }
        return $out;
    }

    private static function findExisting($gate, $plan)
    {
        try {
            $rows = $gate->scopedQuery(array('scope' => array('c' => 'user_capacities')),
                "SELECT c.id FROM user_capacities c
                  WHERE {TENANT_SCOPE} AND c.account_id = ? AND c.capacity_type = ?
                    AND c.scope_type = ? AND COALESCE(c.scope_id,0) = ? LIMIT 1",
                array((int) $plan['account_id'], (string) $plan['capacity_type'],
                      (string) $plan['scope_type'], (int) $plan['scope_id']));
            return $rows ? $rows[0] : null;
        } catch (\Throwable $t) { return null; }
    }

    // ═════════════════════════════════════════════════════════════════════
    // الشريحة ② — القراءةُ بالتجميد الكسول + استيعابُ H-20
    // ═════════════════════════════════════════════════════════════════════

    /**
     * صفاتُ الحساب — **والانتهاءُ يُطبَّق عند كل قراءة**: صفةٌ نافذتُها
     * انقضت أو عقدُها لم يعد نشطًا تُجمَّد فورًا (§2 «الانتهاء الآلي»).
     */
    public static function activeOf($conn, $gate, $accountId, $onDate = '')
    {
        $onDate = ($onDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $onDate))
                ? (string) $onDate : date('Y-m-d');
        $caps = array();
        try {
            $caps = $gate->scopedQuery(array('scope' => array('c' => 'user_capacities')),
                "SELECT c.* FROM user_capacities c
                  WHERE {TENANT_SCOPE} AND c.account_id = ? ORDER BY c.id",
                array((int) $accountId));
        } catch (\Throwable $t) { return array(); }

        $out = array();
        foreach ($caps as $c) {
            if ((string) $c['state'] === 'active') {
                $reason = self::expiryReason($gate, $c, $onDate);
                if ($reason !== null) {
                    self::freeze($conn, $gate, (int) $c['company_id'], (int) $c['id'], $reason, 0);
                    $c['state'] = 'frozen';
                    $c['state_reason'] = $reason;
                }
            }
            $out[] = $c;
        }
        return $out;
    }

    /** سببُ الانتهاء إن وُجد — النافذةُ أو حالُ العقد المصدر. */
    private static function expiryReason($gate, $cap, $onDate)
    {
        if ($cap['valid_to'] !== null && (string) $cap['valid_to'] !== ''
            && (string) $cap['valid_to'] < $onDate) {
            return 'انقضت نافذة الصفة في ' . $cap['valid_to'] . ' — انتهاء آلي (USR-01 §2)';
        }
        if ((string) $cap['source_type'] === 'contract' && (int) $cap['source_id'] > 0) {
            try {
                $rows = $gate->scopedQuery(array('scope' => array('c' => 'employee_contracts')),
                    "SELECT c.state FROM employee_contracts c
                      WHERE {TENANT_SCOPE} AND c.id = ? LIMIT 1", array((int) $cap['source_id']));
                if ($rows && (string) $rows[0]['state'] !== 'active') {
                    return 'عقد المصدر #' . $cap['source_id'] . ' لم يعد نشطا (حاله: '
                         . $rows[0]['state'] . ') — الصفة تسقط بسقوط مصدرها';
                }
            } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'تعذر القياس ⇒ لا تجميد بالظن'); /* تعذّر القياس ⇒ لا تجميدَ بالظن */ }
        }
        return null;
    }

    /** التجميد — لا حذفَ: السجلُّ يبقى للقراءة (U8). */
    public static function freeze($conn, $gate, $companyId, $capacityId, $reason, $actor)
    {
        $reason = trim((string) $reason);
        if ($reason === '') { return array('ok' => false, 'code' => 422,
            'reason' => 'التجميد بسبب مكتوب — CHECK يرفض غيره'); }
        try {
            $gate->update('user_capacities', array(
                'state' => 'frozen', 'state_reason' => mb_substr($reason, 0, 255),
                'state_at' => date('Y-m-d H:i:s'),
            ), array('id' => (int) $capacityId));
        } catch (\Throwable $t) {
            return array('ok' => false, 'code' => 422, 'reason' => $t->getMessage());
        }
        self::audit($conn, $companyId, $actor, 'freeze', (int) $capacityId,
            array('state' => 'active'), array('state' => 'frozen', 'reason' => $reason));
        return array('ok' => true, 'code' => 200, 'reason' => '');
    }

    /**
     * استيعابُ H-20 (الشريحة ②): حسابُ موردٍ يلزمه **صفةُ مشرفِ موردٍ نشطةٌ
     * بنطاق مورده** — الغيابُ أو عدمُ التطابق **fail-closed** لا fail-open.
     * (الحارسُ القائمُ `SupplierPortalGuard` يبقى خطَّ الدفاع الأول —
     *  وهذه الطبقةُ فوقَه لا بدلَه: توسيعٌ لا هدم.)
     *
     * @return ?array{code:int,reason:string} null = سليم
     */
    public static function assertSupplierCapacity($conn, $gate, $accountId, $supplierId)
    {
        $caps = self::activeOf($conn, $gate, (int) $accountId);
        foreach ($caps as $c) {
            if ((string) $c['capacity_type'] === 'supplier_supervisor'
                && (string) $c['state'] === 'active'
                && (string) $c['scope_type'] === 'supplier'
                && (int) $c['scope_id'] === (int) $supplierId) {
                return null;
            }
        }
        return array('code' => 403,
            'reason' => 'لا صفة مشرف مورد نشطة بنطاق المورد #' . (int) $supplierId
                      . ' لهذا الحساب — والعزل fail-closed (H-15 يستوعب H-20)');
    }

    // ═════════════════════════════════════════════════════════════════════
    // الشريحة ③ — مبدّلُ المساحة: الدورُ الفعّالُ دورُ الصفة
    // ═════════════════════════════════════════════════════════════════════

    /**
     * التبديلُ إلى صفةٍ — **بين صفات الحساب نفسِه حصرًا**: صفةُ غيرِه 403
     * مسجَّلة، والمجمَّدةُ 409. والجلسةُ تحمل الصفةَ الفعّالة ودورَها.
     *
     * @return array{ok:bool,code:int,reason:string,capacity:?array}
     */
    public static function switchTo($conn, $gate, $companyId, $accountId, $capacityId, &$session, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'capacity' => null);
        $cap = null;
        try {
            $cap = $gate->selectOne('user_capacities', array('where' => array('id' => (int) $capacityId)));
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $cap'); $cap = null; }
        if (!$cap) { $out['code'] = 404; $out['reason'] = 'الصفة غير موجودة في نطاقك'; return $out; }

        if ((int) $cap['account_id'] !== (int) $accountId) {
            self::audit($conn, $companyId, $actor, 'switch_denied', (int) $capacityId,
                array(), array('attempted_by_account' => (int) $accountId,
                               'owner_account' => (int) $cap['account_id']));
            $out['code'] = 403;
            $out['reason'] = 'الصفة لحساب آخر — **التبديل بين صفاتك أنت حصرا** (403 مسجلة)';
            return $out;
        }
        // الانتهاءُ الكسول قبل التبديل — لا تبديلَ إلى منتهية
        $fresh = self::activeOf($conn, $gate, (int) $accountId);
        foreach ($fresh as $f) { if ((int) $f['id'] === (int) $capacityId) { $cap = $f; break; } }
        if ((string) $cap['state'] !== 'active') {
            $out['code'] = 409;
            $out['reason'] = 'الصفة ' . $cap['state'] . ' (' . $cap['state_reason']
                           . ') — المجمدة تقرأ ولا تلبس';
            return $out;
        }

        // الجلسة: الصفةُ الفعّالة + **الدورُ الفعّالُ دورُ الصفة** (§2-④)
        $session['active_capacity'] = array(
            'id' => (int) $cap['id'],
            'capacity_type' => (string) $cap['capacity_type'],
            'label' => isset(self::CAPACITY_AR[$cap['capacity_type']])
                     ? self::CAPACITY_AR[$cap['capacity_type']] : (string) $cap['capacity_type'],
            'role' => (string) $cap['role'],
            'scope_type' => (string) $cap['scope_type'],
            'scope_id' => $cap['scope_id'] !== null ? (int) $cap['scope_id'] : null,
        );
        if (isset($session['user']) && is_array($session['user'])) {
            $session['user']['role'] = (string) $cap['role'];
            unset($session['ems_topbar_role_label']);
        }
        self::audit($conn, $companyId, $actor, 'switch', (int) $cap['id'],
            array(), array('capacity_type' => (string) $cap['capacity_type'],
                           'role' => (string) $cap['role']));
        $out['ok'] = true; $out['code'] = 200; $out['capacity'] = $cap;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // قراءاتٌ ومرافق
    // ═════════════════════════════════════════════════════════════════════

    public static function listAll($gate, $limit = 500)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('c' => 'user_capacities'),
                      'enrich' => array('u' => 'users', 'e' => 'employees')),
                "SELECT c.*, u.username, u.name AS account_name, e.name AS person_name
                   FROM user_capacities c
                   LEFT JOIN users u ON u.id = c.account_id
                   LEFT JOIN employees e ON e.id = c.person_id
                  WHERE {TENANT_SCOPE}
                  ORDER BY c.account_id, c.id LIMIT " . max(1, (int) $limit));
        } catch (\Throwable $t) { return array(); }
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'portal', 'user_capacities', $action, (int) $rowId, $before, $after,
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
