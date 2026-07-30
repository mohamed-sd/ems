<?php
/**
 * app/Services/Supplier/SupplierDocumentService.php — وثائقُ المورد (M-19)
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-05 §5.1-①: «الهوية والوثائق **بتواريخ صلاحيتها** (**تنبيهٌ آلي قبل
 * الانتهاء**) — والحقولُ النظامية **أعمدةٌ واجبة** (السجل التجاري · الضريبي ·
 * **الحساب البنكي الموثَّق**)».
 *
 * ── أربعُ قواعدَ تحكم كلَّ سطرٍ هنا ─────────────────────────────────────────
 * ① **لا جدولَ ثانيًا لوثائقَ هي وثائق**: `equipment_documents` جدولٌ عامٌّ
 *    بمحاورَ — والموردُ **محورٌ ثالث**؛ فتنبيهُ الانتهاء وفهرسُه وسياستُه
 *    تُورَث بلا نسخٍ ثانٍ يتباعد.
 * ② **التنبيهُ من التاريخ لا من الحالة**: `expiry_date` وحدَها تحكم — و`status`
 *    رأيٌ بشريٌّ يُصان يدويًّا (نفسُ قرار E-08 حرفيًّا).
 * ③ **توثيقٌ بلا مستندٍ دعوى**: توثيقُ الحساب البنكي يلزمه **رقمُ حسابٍ
 *    ومستند** — خدمةً و`CHECK`ًا.
 * ④ **لا سجلَّ تدقيقٍ ثانيًا**: `supplier_audit_log` **قراءةٌ على
 *    `activity_logs`** (N-02) — سجلٌّ واحدٌ يُقرأ بمرشِّحه لا سجلّان يتباعدان.
 */

namespace App\Services\Supplier;

class SupplierDocumentService
{
    /** وثائقُ المورد النظاميةُ وما يجوز معها — نصُّ `doc_type` حرفًا بحرف. */
    const SUPPLIER_DOC_TYPES = array('سجل تجاري', 'شهادة ضريبية', 'شهادة بنكية',
                                     'هوية', 'تصريح', 'أخرى');

    /** الوثيقتان النظاميتان الواجبتان (§5.1-①) — غيابُهما نقصٌ يُعلَن. */
    const REQUIRED_DOC_TYPES = array('سجل تجاري', 'شهادة ضريبية');

    /** وضعُ بوابة المستندات على المسار الحي: off · monitor · enforce. */
    public static function mode()
    {
        $m = function_exists('ems_env')
            ? strtolower(trim((string) ems_env('EMS_SUPPLIER_DOC_GATE', 'monitor'))) : 'monitor';
        return in_array($m, array('off', 'monitor', 'enforce'), true) ? $m : 'monitor';
    }

    // ═════════════════════════════════════════════════════════════════════
    // ① الوثائق
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{ok:bool,code:int,reason:string,doc_id:?int} */
    public static function saveDocument($conn, $gate, $companyId, $supplierId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'doc_id' => null);
        $supplierId = (int) $supplierId;
        if (!self::supplierOf($gate, $supplierId)) {
            $out['code'] = 404; $out['reason'] = 'الموردُ غيرُ موجودٍ في نطاقك'; return $out;
        }

        $type = isset($args['doc_type']) ? trim((string) $args['doc_type']) : '';
        if (!in_array($type, self::SUPPLIER_DOC_TYPES, true)) {
            $out['code'] = 422;
            $out['reason'] = 'نوعُ وثيقةٍ خارج وثائق المورد: ' . implode(' · ', self::SUPPLIER_DOC_TYPES);
            return $out;
        }
        $no = isset($args['doc_no']) ? trim((string) $args['doc_no']) : '';
        if ($no === '') {
            $out['code'] = 422; $out['reason'] = 'رقمُ الوثيقة إلزامي — «وثيقةٌ بلا رقمٍ لا تُراجَع»'; return $out;
        }
        $expiry = isset($args['expiry_date']) ? trim((string) $args['expiry_date']) : '';
        if ($expiry !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) {
            $out['code'] = 422; $out['reason'] = 'تاريخُ الانتهاء بصيغة Y-m-d أو يُترك فارغًا'; return $out;
        }
        // **وثيقةٌ نظاميةٌ بلا تاريخِ انتهاءٍ مرفوضة**: التنبيهُ الآليُّ بلا
        // تاريخٍ وعدٌ لا يُنفَّذ (§5.1-① يشترط «بتواريخ صلاحيتها»).
        if ($expiry === '' && in_array($type, self::REQUIRED_DOC_TYPES, true)) {
            $out['code'] = 422;
            $out['reason'] = '«' . $type . '» **يلزمها تاريخُ صلاحية** — «تنبيهٌ آليٌّ قبل الانتهاء» '
                           . 'بلا تاريخٍ وعدٌ لا يُنفَّذ (UX-05 §5.1-①)';
            return $out;
        }
        $alert = (isset($args['alert_days']) && trim((string) $args['alert_days']) !== '')
                 ? (int) $args['alert_days'] : 30;
        if ($alert <= 0) {
            $out['code'] = 422; $out['reason'] = 'مهلةُ التنبيه أيامٌ موجبة'; return $out;
        }

        try {
            $out['doc_id'] = (int) $gate->insert('equipment_documents', array(
                'subject_type' => 'supplier',
                'subject_id'   => $supplierId,
                'doc_type'     => $type,
                'doc_no'       => $no,
                'issuer'       => isset($args['issuer']) && trim((string) $args['issuer']) !== ''
                                  ? mb_substr(trim((string) $args['issuer']), 0, 255) : null,
                'issue_date'   => (isset($args['issue_date'])
                                   && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $args['issue_date']))
                                  ? (string) $args['issue_date'] : null,
                'expiry_date'  => $expiry !== '' ? $expiry : null,
                'alert_days'   => $alert,
                'file_ref'     => isset($args['file_ref']) && trim((string) $args['file_ref']) !== ''
                                  ? mb_substr(trim((string) $args['file_ref']), 0, 255) : null,
                'status'       => 'سارية',
                'note'         => isset($args['note']) && trim((string) $args['note']) !== ''
                                  ? mb_substr(trim((string) $args['note']), 0, 200) : null,
                'created_by'   => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['code'] = 409; $out['reason'] = 'للمورد وثيقةٌ بهذا النوع والرقم (UQ)'; return $out;
            }
            $out['code'] = 422; $out['reason'] = 'تعذّر الحفظ: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'equipment_documents', 'create', (int) $out['doc_id'],
            array(), array('supplier_id' => $supplierId, 'doc_type' => $type, 'expiry' => $expiry));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    public static function documentsOf($gate, $supplierId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('d' => 'equipment_documents')),
                "SELECT d.* FROM equipment_documents d
                  WHERE {TENANT_SCOPE} AND d.subject_type = 'supplier' AND d.subject_id = ?
                    AND COALESCE(d.is_deleted,0)=0
                  ORDER BY d.expiry_date IS NULL, d.expiry_date", array((int) $supplierId));
        } catch (\Throwable $t) { return array(); }
    }

    /**
     * حالُ وثائق المورد في تاريخ — **التنبيهُ من التاريخ لا من الحالة**.
     *
     * @return array{expired:array,expiring:array,missing:array,as_of:string}
     */
    public static function documentState($gate, $supplierId, $asOf = null)
    {
        $asOf = (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $asOf)) ? (string) $asOf : date('Y-m-d');
        $out = array('expired' => array(), 'expiring' => array(), 'missing' => array(), 'as_of' => $asOf);

        $have = array();
        foreach (self::documentsOf($gate, $supplierId) as $d) {
            $have[(string) $d['doc_type']] = true;
            if ($d['expiry_date'] === null) { continue; }   // لا تنتهي — لا تنبيه
            $exp = (string) $d['expiry_date'];
            if ($exp < $asOf) {
                $out['expired'][] = array('doc_type' => (string) $d['doc_type'],
                    'doc_no' => (string) $d['doc_no'], 'expiry_date' => $exp);
            } else {
                $alertFrom = date('Y-m-d', strtotime($exp . ' -' . (int) $d['alert_days'] . ' days'));
                if ($asOf >= $alertFrom) {
                    $out['expiring'][] = array('doc_type' => (string) $d['doc_type'],
                        'doc_no' => (string) $d['doc_no'], 'expiry_date' => $exp,
                        'alert_days' => (int) $d['alert_days']);
                }
            }
        }
        foreach (self::REQUIRED_DOC_TYPES as $t) {
            if (!isset($have[$t])) { $out['missing'][] = $t; }
        }
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② الحسابُ البنكيُّ الموثَّق
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{ok:bool,code:int,reason:string} */
    public static function verifyBank($conn, $gate, $companyId, $supplierId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $sup = self::supplierOf($gate, (int) $supplierId);
        if (!$sup) { $out['code'] = 404; $out['reason'] = 'الموردُ غيرُ موجودٍ في نطاقك'; return $out; }

        $acc = isset($args['bank_account_no']) ? trim((string) $args['bank_account_no']) : '';
        $doc = isset($args['bank_doc_ref']) ? trim((string) $args['bank_doc_ref']) : '';
        $bank = isset($args['bank_name']) ? trim((string) $args['bank_name']) : '';

        if ($acc === '') {
            $out['code'] = 422; $out['reason'] = 'رقمُ الحساب إلزاميٌّ للتوثيق'; return $out;
        }
        // ── «الحساب البنكي **الموثَّق**» — توثيقٌ بلا مستندٍ دعوى ───────────
        if ($doc === '') {
            $out['code'] = 422;
            $out['reason'] = '**توثيقُ الحساب يلزمه مستند** (شهادةٌ بنكيةٌ أو شيكٌ ملغًى) — '
                           . 'و«الموثَّق» بلا مستندٍ دعوى (UX-05 §5.1-①)';
            return $out;
        }

        try {
            $gate->update('suppliers', array(
                'bank_name'        => $bank !== '' ? mb_substr($bank, 0, 150) : $sup['bank_name'],
                'bank_account_no'  => mb_substr($acc, 0, 60),
                'bank_iban'        => isset($args['bank_iban']) && trim((string) $args['bank_iban']) !== ''
                                      ? mb_substr(trim((string) $args['bank_iban']), 0, 60) : null,
                'bank_doc_ref'     => mb_substr($doc, 0, 120),
                'bank_verified_at' => date('Y-m-d H:i:s'),
                'bank_verified_by' => (int) $actor ?: null,
            ), array('id' => (int) $supplierId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر التوثيق: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'suppliers', 'verify_bank', (int) $supplierId,
            array('bank_account_no' => $sup['bank_account_no'], 'bank_verified_at' => $sup['bank_verified_at']),
            array('bank_account_no' => $acc, 'bank_doc_ref' => $doc, 'bank_verified_at' => date('Y-m-d H:i:s')));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ البوابةُ على المسار الحي — بعلَمها
    // ═════════════════════════════════════════════════════════════════════

    /**
     * أسبابُ الحجب لمورد — **تُقاس دائمًا** ويحكم العلَمُ في أثرها.
     *
     * @return array{blocked:bool,reasons:array,mode:string}
     */
    public static function gateFor($gate, $supplierId, $asOf = null)
    {
        $mode = self::mode();
        $out = array('blocked' => false, 'reasons' => array(), 'mode' => $mode);
        if ($mode === 'off') { return $out; }

        $sup = self::supplierOf($gate, (int) $supplierId);
        if (!$sup) { return $out; }

        if ($sup['bank_verified_at'] === null) {
            $out['reasons'][] = 'الحسابُ البنكيُّ **غيرُ موثَّق** — ودفعٌ إلى حسابٍ غيرِ موثَّقٍ خطرٌ لا إجراء';
        }
        $st = self::documentState($gate, (int) $supplierId, $asOf);
        foreach ($st['missing'] as $m) {
            $out['reasons'][] = 'وثيقةٌ نظاميةٌ ناقصة: **' . $m . '**';
        }
        foreach ($st['expired'] as $e) {
            $out['reasons'][] = 'وثيقةٌ منتهية: **' . $e['doc_type'] . '** (' . $e['expiry_date'] . ')';
        }
        $out['blocked'] = ($mode === 'enforce' && $out['reasons']);
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ④ سجلُّ التدقيق — قراءةٌ على `activity_logs` لا جدولٌ ثانٍ
    // ═════════════════════════════════════════════════════════════════════

    /** جداولُ نطاق المورد التي يُقرأ سجلُّها. */
    const AUDIT_TABLES = array('suppliers', 'supplier_contracts', 'supplier_contract_lines',
        'supplier_charge_rules', 'supplier_penalty_rules', 'supplier_capacity',
        'supplier_evaluations', 'supplier_evaluation_weights', 'supplier_advance_requests',
        'supplier_contract_closures', 'equipment_documents');

    /**
     * سجلُّ تدقيق المورد — «من غيّر ماذا ومتى بقيمٍ قبل/بعد» بمرشِّح نطاقه.
     * (`supplier_audit_log` **اسمُ قراءةٍ** لا اسمُ جدول — سجلٌّ واحدٌ يُقرأ.)
     */
    public static function auditOf($conn, $companyId, $supplierId, $limit = 100)
    {
        $rows = array();
        $ids = self::relatedIds($conn, (int) $companyId, (int) $supplierId);
        $tables = "'" . implode("','", self::AUDIT_TABLES) . "'";
        $sql = "SELECT id, screen_name, action_type, record_id, old_value, new_value,
                       user_id, created_at
                  FROM activity_logs
                 WHERE company_id = ? AND module_name = 'suppliers'
                   AND screen_name IN ({$tables})";
        $params = array((int) $companyId);
        $types = 'i';
        if ($ids) {
            $sql .= ' AND record_id IN (' . implode(',', $ids) . ')';
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . max(1, (int) $limit);
        try {
            $st = $conn->prepare($sql);
            if (!$st) { return array(); }
            $st->bind_param($types, $params[0]);
            $st->execute();
            $res = $st->get_result();
            while ($res && ($x = $res->fetch_assoc())) { $rows[] = $x; }
            $st->close();
        } catch (\Throwable $t) { return array(); }
        return $rows;
    }

    /** معرّفاتُ صفوف المورد وأبنائه — نطاقُ قراءة السجل. */
    private static function relatedIds($conn, $companyId, $supplierId)
    {
        $ids = array((int) $supplierId);
        $q = array(
            "SELECT id FROM supplier_contracts WHERE company_id={$companyId} AND supplier_id={$supplierId}",
            "SELECT id FROM supplier_evaluations WHERE company_id={$companyId} AND supplier_id={$supplierId}",
            "SELECT id FROM supplier_advance_requests WHERE company_id={$companyId} AND supplier_id={$supplierId}",
            "SELECT id FROM supplier_contract_closures WHERE company_id={$companyId} AND supplier_id={$supplierId}",
            "SELECT doc_id FROM equipment_documents WHERE company_id={$companyId}
                AND subject_type='supplier' AND subject_id={$supplierId}",
        );
        foreach ($q as $sql) {
            $r = $conn->query($sql);
            while ($r && ($x = $r->fetch_row())) { $ids[] = (int) $x[0]; }
        }
        return array_values(array_unique($ids));
    }

    private static function supplierOf($gate, $supplierId)
    {
        try { return $gate->selectOne('suppliers', array('where' => array('id' => (int) $supplierId))); }
        catch (\Throwable $t) { return null; }
    }

    private static function audit($conn, $companyId, $actor, $table, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'suppliers', $table, $action, (int) $rowId, $before, $after,
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
