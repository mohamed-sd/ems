<?php
/**
 * app/Services/Contract/ContractSnapshotService.php — بوابةُ اللقطة الواحدة
 * (H-11 · ENT-01 §2) — «لا يقرأ أيُّ احتسابٍ جداولَ العقود مباشرةً».
 *
 * ── العقيدة ────────────────────────────────────────────────────────────────
 * · اللقطةُ **Insert-only**: تُدرج ببصمةٍ من مضمونها القانوني ولا يُمسّ
 *   مضمونُها أبدًا — الإبطالُ كتابةُ أعمدته الثلاثة حصرًا.
 * · العطالة: لقطةٌ صالحةٌ بالبصمة نفسِها ليوم الاحتساب نفسِه تُعاد لا تُكرَّر.
 * · الإبطالُ **من تاريخ السريان فقط** — «فيُعاد احتسابُ ما بعده لا ما قبله».
 * · بوابةُ القراءة: لا لقطةَ إلا لعقدٍ يُقرأ في الاحتساب (isReadable —
 *   active/confirmed/amended/seconded) — 422 لغيره.
 * · المرحَّلُ قراءةً تُؤخذ لقطتُه (قراءةٌ لا كتابةَ عليه — رأسُه هو التعريف).
 */

namespace App\Services\Contract;

require_once __DIR__ . '/../../../includes/catch_log.php';

require_once __DIR__ . '/EmployeeContractStateMachine.php';

class ContractSnapshotService
{
    /**
     * بوابةُ الاحتساب الواحدة — تجلب لقطةً صالحةً أو تصنعها.
     * @return array{ok:bool,code:int,reason:string,id:?int,fingerprint:?string,reused:bool}
     */
    public static function snapshotFor($conn, $gate, $companyId, $contractId, $asOfDate, $actor = 0)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '',
                     'id' => null, 'fingerprint' => null, 'reused' => false);
        $contractId = (int) $contractId;
        $asOf = self::dateOrNull($asOfDate);
        if ($asOf === null) { $out['code'] = 422; $out['reason'] = 'تاريخُ الاحتساب إلزاميٌّ بصيغةٍ سليمة'; return $out; }

        $c = null;
        try { $c = $gate->selectOne('employee_contracts', array('where' => array('id' => $contractId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $c'); $c = null; }
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقدُ غير موجود'; return $out; }
        $state = (string) $c['state'];
        if (!EmployeeContractStateMachine::isReadable($state)) {
            $out['code'] = 422;
            $out['reason'] = 'لا يُقرأ في الاحتساب إلا عقدٌ نافذ (Active/Confirmed/Amended/Seconded) — العقدُ '
                . EmployeeContractStateMachine::labelAr($state);
            return $out;
        }

        // المضمونُ القانوني — فرزٌ ثابتٌ للمفاتيح والصفوف فتثبت البصمة
        $payload = self::canonicalPayload($gate, $c);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $fp = sha1($json);
        $out['fingerprint'] = $fp;

        // العطالة: الصالحةُ بالبصمة نفسِها لليوم نفسِه تُعاد
        $existing = array();
        try {
            $existing = $gate->scopedQuery(array('scope' => array('cs' => 'contract_snapshots')),
                "SELECT cs.id FROM contract_snapshots cs
                 WHERE {TENANT_SCOPE} AND cs.contract_id = ? AND cs.as_of_date = ?
                   AND cs.fingerprint = ? AND cs.valid = 1
                 ORDER BY cs.id DESC LIMIT 1", array($contractId, $asOf, $fp));
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كقائمةٍ فارغة — $existing'); $existing = array(); }
        if ($existing) {
            $out['ok'] = true; $out['code'] = 200; $out['id'] = (int) $existing[0]['id']; $out['reused'] = true;
            return $out;
        }

        try {
            $newId = (int) $gate->insert('contract_snapshots', array(
                'contract_id'  => $contractId,
                'as_of_date'   => $asOf,
                'snapshot_json' => $json,
                'fingerprint'  => $fp,
                'amendment_ref' => null,   // H-10 لم تُبنَ — NULL معلَنٌ لا مخترَع
                'valid'        => 1,
                'created_by'   => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الإدراج: ' . $t->getMessage(); return $out;
        }
        if ($newId <= 0) { $out['code'] = 422; $out['reason'] = 'تعذّر الإدراج — افحص القيود'; return $out; }

        $out['ok'] = true; $out['code'] = 200; $out['id'] = $newId;
        return $out;
    }

    /**
     * قناةُ التصفية (M-22) — **توسيعٌ لا نقض**.
     * ───────────────────────────────────────────────────────────────────────
     * `snapshotFor` تشترط عقدًا **يُقرأ** — والتصفيةُ **لا تقع إلا بعد الإنهاء**
     * (ENT-01 §5)، فالبوابةُ كما هي تجعل «نهايةُ الخدمة **بقاعدتها من اللقطة**»
     * مستحيلةً. فالقناةُ الثانيةُ **مسمّاةٌ للتصفية** وتقبل الحالاتِ الطرفية
     * الثلاثَ وحدَها — بالمضمون والبصمة والعطالة نفسِها حرفيًّا.
     *
     * والأولويةُ دائمًا **للقطةٍ قائمةٍ صالحةٍ ≤ تاريخِ الأثر**: العقدُ الذي
     * حُسب له في حياته يُصفّى بما حُسب به، ولا تُصنع لقطةٌ إلا حين لا توجد.
     *
     * @return array{ok:bool,code:int,reason:string,id:?int,fingerprint:?string,reused:bool,minted:bool}
     */
    const SETTLEMENT_STATES = array('terminated', 'expired', 'closed');

    public static function snapshotForSettlement($conn, $gate, $companyId, $contractId, $asOfDate, $actor = 0)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '',
                     'id' => null, 'fingerprint' => null, 'reused' => false, 'minted' => false);
        $contractId = (int) $contractId;
        $asOf = self::dateOrNull($asOfDate);
        if ($asOf === null) { $out['code'] = 422; $out['reason'] = 'تاريخُ الأثر إلزاميٌّ بصيغةٍ سليمة'; return $out; }

        $c = null;
        try { $c = $gate->selectOne('employee_contracts', array('where' => array('id' => $contractId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $c'); $c = null; }
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقدُ غير موجود'; return $out; }

        // ① لقطةٌ قائمةٌ صالحةٌ حتى تاريخ الأثر — **تُقرأ ولا تُصنع**
        $prior = array();
        try {
            $prior = $gate->scopedQuery(array('scope' => array('cs' => 'contract_snapshots')),
                "SELECT cs.id, cs.fingerprint FROM contract_snapshots cs
                 WHERE {TENANT_SCOPE} AND cs.contract_id = ? AND cs.valid = 1
                   AND cs.as_of_date <= ?
                 ORDER BY cs.as_of_date DESC, cs.id DESC LIMIT 1", array($contractId, $asOf));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كقائمةٍ فارغة — $prior'); $prior = array(); }
        if ($prior) {
            $out['ok'] = true; $out['code'] = 200; $out['reused'] = true;
            $out['id'] = (int) $prior[0]['id'];
            $out['fingerprint'] = (string) $prior[0]['fingerprint'];
            return $out;
        }

        // ② ولا تُصنع إلا لعقدٍ **انتهى فعلًا** — لا لمسودةٍ لم تحيَ قط
        $state = (string) $c['state'];
        if (!in_array($state, self::SETTLEMENT_STATES, true)) {
            $out['code'] = 422;
            $out['reason'] = 'قناةُ التصفية للمنتهي والمنهَى والمقفل حصرًا — العقدُ '
                . EmployeeContractStateMachine::labelAr($state);
            return $out;
        }

        $payload = self::canonicalPayload($gate, $c);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $fp = sha1($json);
        $out['fingerprint'] = $fp;

        // العطالة: بالبصمة نفسِها لليوم نفسِه تُعاد لا تُكرَّر
        $existing = array();
        try {
            $existing = $gate->scopedQuery(array('scope' => array('cs' => 'contract_snapshots')),
                "SELECT cs.id FROM contract_snapshots cs
                 WHERE {TENANT_SCOPE} AND cs.contract_id = ? AND cs.as_of_date = ?
                   AND cs.fingerprint = ? AND cs.valid = 1
                 ORDER BY cs.id DESC LIMIT 1", array($contractId, $asOf, $fp));
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كقائمةٍ فارغة — $existing'); $existing = array(); }
        if ($existing) {
            $out['ok'] = true; $out['code'] = 200; $out['id'] = (int) $existing[0]['id']; $out['reused'] = true;
            return $out;
        }

        try {
            $newId = (int) $gate->insert('contract_snapshots', array(
                'contract_id'   => $contractId,
                'as_of_date'    => $asOf,
                'snapshot_json' => $json,
                'fingerprint'   => $fp,
                'amendment_ref' => null,
                'valid'         => 1,
                'created_by'    => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الإدراج: ' . $t->getMessage(); return $out;
        }
        if ($newId <= 0) { $out['code'] = 422; $out['reason'] = 'تعذّر الإدراج — افحص القيود'; return $out; }

        $out['ok'] = true; $out['code'] = 200; $out['id'] = $newId; $out['minted'] = true;
        return $out;
    }

    /** مضمونُ لقطةٍ مفكوكًا — «تبويبُ اللقطة يعرض القيمَ التي احتُسب بها». */
    public static function payloadOf($gate, $snapshotId)
    {
        $s = null;
        try { $s = $gate->selectOne('contract_snapshots', array('where' => array('id' => (int) $snapshotId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $s'); $s = null; }
        if (!$s) { return null; }
        $p = json_decode((string) $s['snapshot_json'], true);
        return is_array($p) ? $p : null;
    }

    /** كشفُ التلاعب: إعادةُ حساب البصمة من المخزون ومقارنتُها. */
    public static function verify($gate, $snapshotId)
    {
        $s = null;
        try { $s = $gate->selectOne('contract_snapshots', array('where' => array('id' => (int) $snapshotId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $s'); $s = null; }
        if (!$s) { return array('ok' => false, 'reason' => 'اللقطةُ غير موجودة'); }
        $match = sha1((string) $s['snapshot_json']) === (string) $s['fingerprint'];
        return array('ok' => $match,
                     'reason' => $match ? '' : 'البصمةُ لا تطابق المضمون — تلاعبٌ مكشوف');
    }

    /**
     * الإبطالُ بالسريان — الصالحةُ التي as_of_date >= التاريخ **فقط**.
     * @return int عددُ ما أُبطل
     */
    public static function invalidateFrom($conn, $gate, $companyId, $contractId, $fromDate, $reason, $actor = 0)
    {
        $from = self::dateOrNull($fromDate);
        if ($from === null) { return 0; }
        $rows = array();
        try {
            $rows = $gate->scopedQuery(array('scope' => array('cs' => 'contract_snapshots')),
                "SELECT cs.id FROM contract_snapshots cs
                 WHERE {TENANT_SCOPE} AND cs.contract_id = ? AND cs.valid = 1
                   AND cs.as_of_date >= ?", array((int) $contractId, $from));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كقائمةٍ فارغة — $rows'); $rows = array(); }
        $n = 0;
        foreach ($rows as $row) {
            try {
                $gate->update('contract_snapshots', array(
                    'valid' => 0,
                    'invalidated_at' => gmdate('Y-m-d H:i:s'),
                    'invalidated_from' => $from,
                    'invalidation_reason' => mb_substr(trim((string) $reason), 0, 160),
                ), array('id' => (int) $row['id']));
                $n++;
            } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'ContractSnapshotService invalidate #');
                error_log('ContractSnapshotService invalidate #' . $row['id'] . ': ' . $t->getMessage());
            }
        }
        if ($n > 0) {
            require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
            ems_audit_change($conn, 'workforce', 'contract_snapshots', 'invalidate_from', (int) $contractId,
                array('valid_count' => count($rows)), array('invalidated' => $n, 'from' => $from, 'reason' => (string) $reason),
                array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
        }
        return $n;
    }

    // ═══════════════════════════════════════════════════════════════════════

    /** المضمونُ القانوني: الرأسُ + المكوّناتُ الحية + القواعدُ بتوزيعها + التحمّل — فرزٌ ثابت. */
    private static function canonicalPayload($gate, $c)
    {
        $cid = (int) $c['id'];
        $head = array(
            'contract_id' => $cid,
            'employee_id' => (int) $c['employee_id'],
            'category' => (string) $c['category'],
            'pay_model_id' => (int) $c['pay_model_id'],
            'currency' => $c['currency'] !== null ? (string) $c['currency'] : null,
            'start_date' => $c['start_date'], 'end_date' => $c['end_date'],
            'probation_end' => $c['probation_end'],
            'state' => (string) $c['state'],
            'version' => (int) $c['version'],
            'source_table' => $c['source_table'] !== null ? (string) $c['source_table'] : null,
        );
        $components = self::rowsOf($gate, 'pay_components', 'pc',
            "SELECT pc.id, pc.component_type, pc.calc_method, pc.value, pc.rate,
                    pc.in_insurance, pc.in_tax, pc.in_leave_pay, pc.in_eos, pc.in_hour_base,
                    pc.in_overtime, pc.in_incentive_base, pc.is_variable, pc.periodicity,
                    pc.cost_bearer_type, pc.cost_bearer_id, pc.cost_center_id,
                    pc.valid_from, pc.valid_to, pc.state
               FROM pay_components pc
              WHERE {TENANT_SCOPE} AND pc.contract_id = ? AND COALESCE(pc.is_deleted,0)=0
                AND pc.state = 'active' ORDER BY pc.id", array($cid));
        $rules = self::rowsOf($gate, 'incentive_rules', 'ir',
            "SELECT ir.id, ir.incentive_type, ir.basis, ir.rate, ir.threshold, ir.cap, ir.floor,
                    ir.periodicity, ir.condition_text, ir.scope_type, ir.scope_id,
                    ir.valid_from, ir.valid_to, ir.state
               FROM incentive_rules ir
              WHERE {TENANT_SCOPE} AND ir.contract_id = ? AND COALESCE(ir.is_deleted,0)=0
                AND ir.state = 'active' ORDER BY ir.id", array($cid));
        foreach ($rules as $i => $r) {
            $rules[$i]['allocations'] = self::rowsOf($gate, 'incentive_allocations', 'ia',
                "SELECT ia.beneficiary_type, ia.beneficiary_id, ia.percent
                   FROM incentive_allocations ia
                  WHERE {TENANT_SCOPE} AND ia.rule_id = ?
                  ORDER BY ia.beneficiary_type, ia.beneficiary_id", array((int) $r['id']));
        }
        $bearers = array();
        foreach ($components as $pc) {
            $bearers['component#' . $pc['id']] = self::bearersOf($gate, 'component', (int) $pc['id']);
        }
        foreach ($rules as $r) {
            $bearers['rule#' . $r['id']] = self::bearersOf($gate, 'rule', (int) $r['id']);
        }
        ksort($bearers);
        return array('head' => $head, 'components' => $components,
                     'incentives' => $rules, 'cost_bearers' => $bearers);
    }

    private static function bearersOf($gate, $ownerType, $ownerId)
    {
        return self::rowsOf($gate, 'cost_bearers', 'cb',
            "SELECT cb.bearer_type, cb.bearer_id, cb.percent
               FROM cost_bearers cb
              WHERE {TENANT_SCOPE} AND cb.owner_type = ? AND cb.owner_id = ?
                AND COALESCE(cb.is_deleted,0)=0
              ORDER BY cb.bearer_type, cb.bearer_id", array($ownerType, (int) $ownerId));
    }

    private static function rowsOf($gate, $table, $alias, $sql, $params)
    {
        try {
            return $gate->scopedQuery(array('scope' => array($alias => $table)), $sql, $params);
        } catch (\Throwable $t) {
            error_log('ContractSnapshotService rowsOf ' . $table . ': ' . $t->getMessage());
            return array();
        }
    }

    private static function dateOrNull($v)
    {
        $v = trim((string) $v);
        if ($v === '') { return null; }
        $d = \DateTime::createFromFormat('Y-m-d', $v);
        return ($d && $d->format('Y-m-d') === $v) ? $v : null;
    }
}
