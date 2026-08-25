<?php
/**
 * app/Services/Contract/EmployeeContractAmendmentService.php — ملاحقُ عقد الموظف
 * (H-10 · CON-01 §4/§5/§7.2) — البابُ الشرعيُّ الوحيدُ لتغيير النافذ:
 * «لا تعديلَ مباشرًا على عقدٍ نافذٍ — كلُّ تغييرٍ ملحقٌ بسريان».
 *
 * · «قبل» يُلتقط من الواقع الحي لا من المرسل — صدقُ السجل.
 * · الاعتمادُ يطبّق ذريًّا (runInTransaction) ويقلب العقدَ amended ويُبطل
 *   لقطاتِ H-11 **من تاريخ السريان** («احتسابُ ما قبل السريان بالقديم
 *   وما بعده بالجديد — لا رجعية» §7.3-N3).
 * · حقولُ التغيير المسموحة صيغتُها:
 *     head:<end_date|probation_end|project_id|pay_model_id|currency>
 *     component:<id>:<value|rate> · rule:<id>:<rate|threshold|cap|floor>
 */

namespace App\Services\Contract;

require_once __DIR__ . '/../../../includes/catch_log.php';

require_once __DIR__ . '/EmployeeContractStateMachine.php';
require_once __DIR__ . '/ContractSnapshotService.php';

class EmployeeContractAmendmentService
{
    const AMEND_TYPES = array('pay_change' => 'تغيير أجر', 'duration_change' => 'تغيير مدة',
                              'location_change' => 'تغيير موقع', 'scope_change' => 'تغيير نطاق',
                              'other' => 'آخر');

    const HEAD_FIELDS = array('end_date', 'probation_end', 'project_id', 'pay_model_id', 'currency');
    const COMPONENT_FIELDS = array('value', 'rate');
    const RULE_FIELDS = array('rate', 'threshold', 'cap', 'floor');

    /** الحالاتُ التي بابُ تغييرها الملحق — النافذةُ العاملة. */
    const AMENDABLE = array(EmployeeContractStateMachine::ACTIVE,
                            EmployeeContractStateMachine::CONFIRMED,
                            EmployeeContractStateMachine::AMENDED);

    /**
     * إنشاءُ ملحقٍ (مسودةً) — «قبل» يُلتقط من الواقع الحي.
     * @param array $changes [{field, after}] — before لا يُقبل من المرسل
     * @return array{ok:bool,code:int,reason:string,id:?int,changes:?array}
     */
    public static function createAmendment($conn, $gate, $companyId, $contractId, $data, $changes, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'id' => null, 'changes' => null);
        $contractId = (int) $contractId;

        $c = self::contractOf($gate, $contractId);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقد غير موجود'; return $out; }
        $src = trim((string) ($c['source_table'] ?? ''));
        if ($src !== '') {
            $out['code'] = 423;
            $out['reason'] = 'صف مرحل قراءة — كاتبه مصدره القديم (' . $src . ') حتى إقفال القديم (N-04)';
            return $out;
        }
        $state = (string) $c['state'];
        if (!in_array($state, self::AMENDABLE, true)) {
            $out['code'] = 422;
            $out['reason'] = 'الملحق لتغيير النافذ — والعقد '
                . EmployeeContractStateMachine::labelAr($state)
                . ($state === EmployeeContractStateMachine::DRAFT || $state === EmployeeContractStateMachine::COMPLETED
                    ? ' يعدل مباشرة بلا ملحق' : ' خارج عائلة النفاذ');
            return $out;
        }

        $amendType = isset($data['amend_type']) ? (string) $data['amend_type'] : '';
        if (!isset(self::AMEND_TYPES[$amendType])) {
            $out['code'] = 422; $out['reason'] = 'نوع ملحق من خارج الأنواع (§4)'; return $out;
        }
        $eff = self::dateOrNull(isset($data['effective_from']) ? $data['effective_from'] : null);
        if ($eff === null) { $out['code'] = 422; $out['reason'] = 'تاريخ السريان إلزامي'; return $out; }
        if ($c['start_date'] !== null && $eff < $c['start_date']) {
            // §7.2 نصًّا: «ملحقٌ بسريانٍ قبل بدء العقد → 422»
            $out['code'] = 422; $out['reason'] = 'سريان قبل بدء العقد (' . $c['start_date'] . ')'; return $out;
        }
        if (isset($data['expected_version']) && $data['expected_version'] !== null
            && (int) $data['expected_version'] !== (int) $c['version']) {
            $out['code'] = 409;
            $out['reason'] = 'نسخة متغيرة — أعد التحميل (المسجلة ' . (int) $c['version'] . ')';
            return $out;
        }

        // بناءُ التغييرات: «قبل» من الواقع الحي حصرًا
        $built = array();
        foreach ((array) $changes as $i => $ch) {
            $field = isset($ch['field']) ? trim((string) $ch['field']) : '';
            $after = isset($ch['after']) ? $ch['after'] : null;
            $resolved = self::resolveField($gate, $c, $field);
            if (!$resolved['ok']) {
                $out['code'] = 422; $out['reason'] = 'السطر ' . ($i + 1) . ': ' . $resolved['reason']; return $out;
            }
            $built[] = array('field' => $field, 'before' => $resolved['before'], 'after' => $after);
        }
        if (!$built) { $out['code'] = 422; $out['reason'] = 'ملحق بلا تغييرات'; return $out; }

        $row = array(
            'contract_id' => $contractId,
            'amend_type' => $amendType,
            'effective_from' => $eff,
            'changes_json' => json_encode($built, JSON_UNESCAPED_UNICODE),
            'state' => 'draft',
            'created_by' => (int) $actor ?: null,
        );
        $newId = 0;
        try { $newId = (int) $gate->insert('employee_contract_amendments', $row); }
        catch (\Throwable $t) {
            $out['code'] = 409;
            $out['reason'] = 'ملحق بالنوع والسريان نفسهما قائم لهذا العقد (UQ §7.1)';
            return $out;
        }
        if ($newId <= 0) {
            $out['code'] = 409; $out['reason'] = 'ملحق بالنوع والسريان نفسهما قائم (UQ §7.1)'; return $out;
        }

        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'employee_contract_amendments', 'create', $newId,
            array(), $row, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));

        $out['ok'] = true; $out['code'] = 200; $out['id'] = $newId; $out['changes'] = $built;
        return $out;
    }

    /**
     * اعتمادُ الملحق — يطبّق ذريًّا ويقلب العقدَ amended ويُبطل اللقطاتِ من سريانه.
     * @return array{ok:bool,code:int,reason:string,snapshot_invalidated_from:?string}
     */
    public static function approveAmendment($conn, $gate, $companyId, $amendmentId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'snapshot_invalidated_from' => null);
        $a = self::amendmentOf($gate, $amendmentId);
        if (!$a) { $out['code'] = 404; $out['reason'] = 'الملحق غير موجود'; return $out; }
        if ((string) $a['state'] !== 'draft') {
            $out['code'] = 422; $out['reason'] = 'الملحق ليس مسودة (' . $a['state'] . ')'; return $out;
        }
        if ((int) $a['created_by'] === (int) $actor && (int) $actor > 0) {
            $out['code'] = 403; $out['reason'] = 'لا اعتماد لمن أنشأ — فصل الواجبات بنيوي'; return $out;
        }
        $c = self::contractOf($gate, (int) $a['contract_id']);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'عقد الملحق غير موجود'; return $out; }
        if (!in_array((string) $c['state'], self::AMENDABLE, true)) {
            $out['code'] = 422;
            $out['reason'] = 'العقد خرج من عائلة النفاذ (' . EmployeeContractStateMachine::labelAr($c['state']) . ')';
            return $out;
        }

        $changes = json_decode((string) $a['changes_json'], true);
        if (!is_array($changes) || !$changes) {
            $out['code'] = 422; $out['reason'] = 'تغييرات الملحق تالفة'; return $out;
        }
        // «قبل» ما زال واقعًا؟ تغيّرَ تحته → 409 (لا تطبيقَ فوق واقعٍ مغاير)
        foreach ($changes as $i => $ch) {
            $resolved = self::resolveField($gate, $c, (string) $ch['field']);
            if (!$resolved['ok']) {
                $out['code'] = 422; $out['reason'] = 'السطر ' . ($i + 1) . ': ' . $resolved['reason']; return $out;
            }
            if ((string) $resolved['before'] !== (string) $ch['before']) {
                $out['code'] = 409;
                $out['reason'] = 'الواقع تغير تحت الملحق (' . $ch['field'] . ': المسجل «' . $ch['before']
                    . '» والحي «' . $resolved['before'] . '») — أنشئ ملحقا جديدا';
                return $out;
            }
        }

        try {
            $gate->runInTransaction(function ($g) use ($a, $c, $changes, $actor) {
                foreach ($changes as $ch) {
                    self::applyChange($g, $c, (string) $ch['field'], $ch['after']);
                }
                $g->update('employee_contracts', array(
                    'state' => EmployeeContractStateMachine::AMENDED,
                    'version' => (int) $c['version'] + 1,
                ), array('id' => (int) $c['id']));
                $g->update('employee_contract_amendments', array(
                    'state' => 'approved',
                    'approved_by' => (int) $actor ?: null,
                    'approved_at' => gmdate('Y-m-d H:i:s'),
                ), array('id' => (int) $a['id']));
                return true;
            }, 'H-10 amendment apply #' . (int) $a['id']);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذر التطبيق الذري: ' . $t->getMessage(); return $out;
        }

        // H-11: الإبطالُ من السريان — «ما قبله بالقديم وما بعده بالجديد»
        $eff = (string) $a['effective_from'];
        ContractSnapshotService::invalidateFrom($conn, $gate, (int) $companyId, (int) $c['id'],
            $eff, 'ملحق معتمد #' . (int) $a['id'] . ' (' . $a['amend_type'] . ')', $actor);
        $out['snapshot_invalidated_from'] = $eff;

        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'employee_contract_amendments', 'approve', (int) $a['id'],
            array('state' => 'draft'), array('state' => 'approved', 'applied' => $changes),
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
        try {
            require_once dirname(dirname(__DIR__)) . '/Core/EventPublisher.php';
            \App\Core\EventPublisher::publishFact($conn, array(
                // النطاقُ الأولُ بلا شرطةٍ سفلية (عقد §9) — وإلا رُفض الحدثُ صامتًا
                'event_key' => 'workforce.contract.amended',
                'category' => 'operational', 'source_module' => 'workforce',
                'company_id' => (int) $companyId,
                'entity_type' => 'employee_contract_amendment', 'entity_id' => (int) $a['id'],
                'occurred_at' => gmdate('Y-m-d H:i:s'), 'created_by' => (int) $actor ?: 1,
                'idempotency_key' => 'employee_contract_amend:' . (int) $a['id'],
                'notes' => 'ملحق عقد معتمد — سريانه ' . $eff,
                'payload' => array('contract_id' => (int) $c['id'], 'amendment_id' => (int) $a['id'],
                                   'effective_from' => $eff, 'changes' => $changes),
            ));
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'EmployeeContractAmendmentService publish #');
            error_log('EmployeeContractAmendmentService publish #' . $a['id'] . ': ' . $t->getMessage());
        }

        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** رفضُ الملحق — بسببٍ إلزامي (§4: «Rejected بسببٍ إلزامي» قياسًا). */
    public static function rejectAmendment($conn, $gate, $companyId, $amendmentId, $reason, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $reason = trim((string) $reason);
        if ($reason === '') { $out['code'] = 422; $out['reason'] = 'سبب الرفض إلزامي'; return $out; }
        $a = self::amendmentOf($gate, $amendmentId);
        if (!$a) { $out['code'] = 404; $out['reason'] = 'الملحق غير موجود'; return $out; }
        if ((string) $a['state'] !== 'draft') {
            $out['code'] = 422; $out['reason'] = 'الملحق ليس مسودة'; return $out;
        }
        $gate->update('employee_contract_amendments', array(
            'state' => 'rejected', 'reject_reason' => mb_substr($reason, 0, 255),
        ), array('id' => (int) $amendmentId));
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'employee_contract_amendments', 'reject', (int) $amendmentId,
            array('state' => 'draft'), array('state' => 'rejected', 'reason' => $reason),
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═══════════════════════════════════════════════════════════════════════

    /** حلُّ حقلِ تغييرٍ إلى قيمته الحية — أو رفضُه. */
    private static function resolveField($gate, $c, $field)
    {
        $parts = explode(':', (string) $field);
        if (count($parts) === 2 && $parts[0] === 'head') {
            if (!in_array($parts[1], self::HEAD_FIELDS, true)) {
                return array('ok' => false, 'reason' => 'حقل رأس غير قابل للملحق: ' . $parts[1]);
            }
            return array('ok' => true, 'before' => $c[$parts[1]]);
        }
        if (count($parts) === 3 && $parts[0] === 'component') {
            if (!in_array($parts[2], self::COMPONENT_FIELDS, true)) {
                return array('ok' => false, 'reason' => 'حقل مكون غير قابل: ' . $parts[2]);
            }
            $pc = self::rowOf($gate, 'pay_components', (int) $parts[1]);
            if (!$pc || (int) $pc['contract_id'] !== (int) $c['id']) {
                return array('ok' => false, 'reason' => 'المكون #' . $parts[1] . ' ليس لهذا العقد');
            }
            return array('ok' => true, 'before' => $pc[$parts[2]]);
        }
        if (count($parts) === 3 && $parts[0] === 'rule') {
            if (!in_array($parts[2], self::RULE_FIELDS, true)) {
                return array('ok' => false, 'reason' => 'حقل قاعدة غير قابل: ' . $parts[2]);
            }
            $ir = self::rowOf($gate, 'incentive_rules', (int) $parts[1]);
            if (!$ir || (int) $ir['contract_id'] !== (int) $c['id']) {
                return array('ok' => false, 'reason' => 'القاعدة #' . $parts[1] . ' ليست لهذا العقد');
            }
            return array('ok' => true, 'before' => $ir[$parts[2]]);
        }
        return array('ok' => false, 'reason' => 'صيغة حقل مجهولة: ' . $field);
    }

    /** تطبيقُ تغييرٍ واحد — داخل المعاملة الذرية حصرًا. */
    private static function applyChange($g, $c, $field, $after)
    {
        $parts = explode(':', (string) $field);
        if ($parts[0] === 'head') {
            $val = ($after === '' || $after === null) ? null : $after;
            if (in_array($parts[1], array('project_id', 'pay_model_id'), true) && $val !== null) { $val = (int) $val; }
            $g->update('employee_contracts', array($parts[1] => $val), array('id' => (int) $c['id']));
            return;
        }
        $table = $parts[0] === 'component' ? 'pay_components' : 'incentive_rules';
        $val = ($after === '' || $after === null) ? null : round((float) $after, 4);
        $g->update($table, array($parts[2] => $val), array('id' => (int) $parts[1]));
    }

    private static function contractOf($gate, $id)
    {
        try { return $gate->selectOne('employee_contracts', array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { return null; }
    }

    private static function amendmentOf($gate, $id)
    {
        try { return $gate->selectOne('employee_contract_amendments', array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { return null; }
    }

    private static function rowOf($gate, $table, $id)
    {
        try { return $gate->selectOne($table, array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { return null; }
    }

    private static function dateOrNull($v)
    {
        $v = trim((string) $v);
        if ($v === '') { return null; }
        $d = \DateTime::createFromFormat('Y-m-d', $v);
        return ($d && $d->format('Y-m-d') === $v) ? $v : null;
    }
}
