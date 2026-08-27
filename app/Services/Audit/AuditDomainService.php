<?php
/**
 * AuditDomainService — نطاقُ المراجعةِ الداخليّةِ وحدَه (RPR-W14 · `IAF`)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **خطٌّ ثالثٌ مستقلّ** (§12 · حدودُ W14 النهائيّة): مصدرُ حقيقتِه
 *   `Audit_Engagement_ID` و`Audit_Finding_ID`. **ولا يملك السياساتِ ولا
 *   الامتثالَ اليوميَّ ولا التفويضاتِ ولا `AAM` ولا تشغيلَ الضوابط**،
 *   ⛔ **ولا يكتب حرفًا في `gov_*` ولا في `risk_*`** — والماسحُ البنيويُّ يثبته.
 *
 * ◆ **والحوكمةُ لا تعطيه نطاقَه ولا تغيّر نتيجتَه ولا تغلقها نيابةً عنه**:
 *   `approveProgram` تثبّت `scope_set_by_dept = IAF`، و`raiseFinding` و
 *   `closeFinding` تثبّتان `result_set_by_dept` و`result_closed_by_dept` عند
 *   `IAF` — والقيدانِ في القاعدةِ يردّانِ غيرَها.
 *
 * ◆ **ولا طابورَ تحقيقٍ يوميّ**: لا يوجد في هذا الملفِّ بابٌ يفتح تحقيقًا —
 *   والدخولُ إلى `Special Independent Investigation` يقع من بابِ الحوكمةِ
 *   **بتكليفٍ مكتوب** يردُّه `chk_gin_iaf_mandate` بدونه.
 *
 * ◆ **والوصولُ قراءةُ تأكيدٍ لا كتابةَ معاملة** (‏قرارُ المالك السادس):
 *   `requestEvidence` تطلب من الخاضعِ للمراجعةِ ولا تكتب في سجلِّه،
 *   و`chk_ifr_auditee` يردُّ طلبًا موجَّهًا إلى `IAF` نفسِها.
 *
 * ⛔ **ولا عتبةٌ رقميّةٌ في هذا الملفّ** — كلُّها عبرَ `ThresholdRegistry`.
 * ═══════════════════════════════════════════════════════════════════════════
 */

namespace App\Services\Audit;

use App\Core\TenantDb;
use App\Services\Control\ThresholdRegistry;

class AuditDomainService
{
    const DOMAIN = 'IAF';
    const K_ESCALATION = 'iaf.finding.escalation_days';

    private static $company = 0;
    private static $eventConn = null;

    public static function setCompany($id) { self::$company = (int) $id; }
    public static function setEventConnection(\mysqli $conn) { self::$eventConn = $conn; }

    private static function fail($code, $detail = '')
    { return array('ok' => false, 'code' => $code, 'detail' => $detail); }
    private static function done(array $d = array())
    { return array_merge(array('ok' => true, 'code' => 'OK'), $d); }
    private static function s($r, $k, $d = '') { return isset($r[$k]) ? (string) $r[$k] : $d; }
    private static function i($r, $k, $d = 0) { return isset($r[$k]) ? (int) $r[$k] : $d; }

    /* ══════════════════════════════════════════════════════════════════════
       ① البرنامجُ — النطاقُ من المراجعةِ وحدَها ومن نفَّذ لا يراجع
       ══════════════════════════════════════════════════════════════════════ */

    public static function draftProgram(TenantDb $gate, array $row)
    {
        if (self::s($row, 'objective_ar') === '') { return self::fail('PROGRAM_WITHOUT_OBJECTIVE'); }
        /* **والنطاقُ لا يُقبَل من خارجِ المراجعةِ ولو مُرِّر** */
        $scope = self::s($row, 'scope_set_by_dept', self::DOMAIN);
        if ($scope !== self::DOMAIN) { return self::fail('AUDIT_SCOPE_SET_BY_GOVERNANCE', $scope); }
        try {
            $id = $gate->insert('iaf_program', array(
                'program_no'        => self::s($row, 'program_no'),
                'engagement_no'     => self::s($row, 'engagement_no'),
                'step_no'           => self::i($row, 'step_no', 1),
                'objective_ar'      => self::s($row, 'objective_ar'),
                'test_method'       => self::s($row, 'test_method', 'inspection'),
                'population_ar'     => self::s($row, 'population_ar'),
                'sample_size'       => self::i($row, 'sample_size'),
                'sampling_basis'    => self::s($row, 'sampling_basis'),
                'performer_id'      => self::i($row, 'performer_id'),
                'scope_set_by_dept' => self::DOMAIN,
                'state'             => 'drafted',
                'src_ref'           => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('PROGRAM_REFUSED', $t->getMessage()); }
        return self::done(array('program_id' => (int) $id));
    }

    /** **ومن نفَّذ الخطوةَ لا يراجعها** — و`chk_ifp_hands` يردُّه في القاعدة */
    public static function approveProgram(TenantDb $gate, $programId, $actorId)
    {
        $p = $gate->selectOne('iaf_program', array('where' => array('id' => (int) $programId)));
        if (!$p) { return self::fail('PROGRAM_NOT_FOUND'); }
        if ((int) $p['performer_id'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_PERFORM_AND_REVIEW');
        }
        if ((int) $p['sample_size'] <= 0 || (string) $p['sampling_basis'] === '') {
            return self::fail('PROGRAM_WITHOUT_SAMPLING_BASIS', 'العينة بمنهجية معلنة');
        }
        try {
            $gate->update('iaf_program',
                array('state' => 'approved', 'reviewed_by' => (int) $actorId),
                array('id' => (int) $programId));
        } catch (\Throwable $t) { return self::fail('PROGRAM_APPROVAL_REFUSED', $t->getMessage()); }
        self::emit('iaf.program.approved', 'iaf_program', (int) $programId, array(
            'program_no' => (string) $p['program_no'], 'engagement_no' => (string) $p['engagement_no'],
        ));
        return self::done();
    }

    /* ══════════════════════════════════════════════════════════════════════
       ② طلبُ الدليلِ — بمهلةٍ، والتأخّرُ واقعةٌ تُسجَّل وتُصعَّد
       ══════════════════════════════════════════════════════════════════════ */

    public static function requestEvidence(TenantDb $gate, array $row)
    {
        $dept = self::s($row, 'auditee_dept');
        if ($dept === '') { return self::fail('EVIDENCE_REQUEST_WITHOUT_AUDITEE'); }
        if ($dept === self::DOMAIN) { return self::fail('EVIDENCE_REQUEST_TO_SELF'); }
        if (self::s($row, 'due_date') === '') { return self::fail('EVIDENCE_REQUEST_WITHOUT_DUE'); }
        try {
            $id = $gate->insert('iaf_evidence_request', array(
                'request_no'     => self::s($row, 'request_no'),
                'engagement_no'  => self::s($row, 'engagement_no'),
                'program_no'     => self::s($row, 'program_no'),
                'auditee_dept'   => $dept,
                'auditee_person' => self::i($row, 'auditee_person'),
                'item_ar'        => self::s($row, 'item_ar'),
                'requested_at'   => date('Y-m-d H:i:s'),
                'due_date'       => self::s($row, 'due_date'),
                'state'          => 'requested',
                'src_ref'        => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('EVIDENCE_REQUEST_REFUSED', $t->getMessage()); }
        return self::done(array('request_id' => (int) $id));
    }

    public static function provideEvidence(TenantDb $gate, $requestId, $evidenceRef)
    {
        if ((string) $evidenceRef === '') { return self::fail('EVIDENCE_WITHOUT_REF'); }
        try {
            $gate->update('iaf_evidence_request', array(
                'provided_at'  => date('Y-m-d H:i:s'),
                'evidence_ref' => (string) $evidenceRef,
                'state'        => 'provided',
            ), array('id' => (int) $requestId));
        } catch (\Throwable $t) { return self::fail('EVIDENCE_PROVIDE_REFUSED', $t->getMessage()); }
        return self::done();
    }

    /** **والتصعيدُ بعتبةٍ من السجلِّ** — والمعلَّقةُ تردُّ ولا تُخترَع */
    public static function escalateEvidence(TenantDb $gate, $requestId, $delayDays)
    {
        $th = ThresholdRegistry::read(self::K_ESCALATION);
        if (!$th['ok']) { return self::fail(ThresholdRegistry::NOT_CONFIGURED, self::K_ESCALATION); }
        if ((float) $delayDays <= $th['value']) {
            return self::fail('DELAY_BELOW_ESCALATION_THRESHOLD', (string) $delayDays);
        }
        $r = $gate->selectOne('iaf_evidence_request', array('where' => array('id' => (int) $requestId)));
        if (!$r) { return self::fail('EVIDENCE_REQUEST_NOT_FOUND'); }
        try {
            $gate->update('iaf_evidence_request', array(
                'delay_days'       => (int) $delayDays,
                'escalation_level' => ((int) $r['escalation_level'] + 1),
                'state'            => 'escalated',
            ), array('id' => (int) $requestId));
        } catch (\Throwable $t) { return self::fail('EVIDENCE_ESCALATE_REFUSED', $t->getMessage()); }
        self::emit('iaf.evidence.overdue', 'iaf_evidence_request', (int) $requestId, array(
            'auditee_dept' => (string) $r['auditee_dept'], 'delay_days' => (int) $delayDays,
            'threshold_tag' => $th['tagged'],
        ));
        return self::done(array('threshold_tag' => $th['tagged']));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ③ العيّنةُ ونتيجتُها
       ══════════════════════════════════════════════════════════════════════ */

    public static function drawSample(TenantDb $gate, array $row)
    {
        if (self::s($row, 'source_table') === '' || self::i($row, 'source_row_id') <= 0) {
            return self::fail('SAMPLE_WITHOUT_SOURCE_REF');
        }
        try {
            $id = $gate->insert('iaf_sample', array(
                'sample_no'     => self::s($row, 'sample_no'),
                'program_no'    => self::s($row, 'program_no'),
                'step_no'       => self::i($row, 'step_no', 1),
                'item_ref'      => self::s($row, 'item_ref'),
                'source_table'  => self::s($row, 'source_table'),
                'source_row_id' => self::i($row, 'source_row_id'),
                'state'         => 'drawn',
                'src_ref'       => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('SAMPLE_REFUSED', $t->getMessage()); }
        return self::done(array('sample_id' => (int) $id));
    }

    public static function testSample(TenantDb $gate, $sampleId, $result, $exceptionAr, $actorId)
    {
        if (!in_array($result, array('pass', 'exception', 'not_applicable'), true)) {
            return self::fail('SAMPLE_RESULT_UNKNOWN', (string) $result);
        }
        if ($result === 'exception' && (string) $exceptionAr === '') {
            return self::fail('EXCEPTION_WITHOUT_DETAIL');
        }
        try {
            $gate->update('iaf_sample', array(
                'test_result'  => (string) $result,
                'exception_ar' => (string) $exceptionAr,
                'tested_by'    => (int) $actorId,
                'tested_at'    => date('Y-m-d H:i:s'),
                'state'        => 'tested',
            ), array('id' => (int) $sampleId));
        } catch (\Throwable $t) { return self::fail('SAMPLE_TEST_REFUSED', $t->getMessage()); }
        return self::done();
    }

    /* ══════════════════════════════════════════════════════════════════════
       ④ الملاحظةُ — نتيجتُها تُوضَع وتُغلَق من `IAF` وحدَها
       ══════════════════════════════════════════════════════════════════════ */

    public static function raiseFinding(TenantDb $gate, array $row, $actorId)
    {
        if (self::s($row, 'title') === '') { return self::fail('FINDING_WITHOUT_TITLE'); }
        if (self::s($row, 'auditee_dept') === '') { return self::fail('FINDING_WITHOUT_AUDITEE'); }
        try {
            $id = $gate->insert('iaf_findings', array(
                'finding_no'         => self::s($row, 'finding_no'),
                'engagement_id'      => self::i($row, 'engagement_id'),
                'area_code'          => self::s($row, 'area_code'),
                'auditee_dept'       => self::s($row, 'auditee_dept'),
                'auditee_user_id'    => self::i($row, 'auditee_user_id') ?: null,
                'title'              => self::s($row, 'title'),
                'detail'             => self::s($row, 'detail'),
                'severity'           => self::s($row, 'severity', 'medium'),
                'raised_by'          => (int) $actorId,
                'raised_at'          => date('Y-m-d H:i:s'),
                'evidence_ref'       => self::s($row, 'evidence_ref'),
                'state'              => 'open',
                'result_set_by_dept' => self::DOMAIN,
            ));
        } catch (\Throwable $t) { return self::fail('FINDING_REFUSED', $t->getMessage()); }
        self::emit('iaf.finding.raised', 'iaf_findings', (int) $id, array(
            'finding_no' => self::s($row, 'finding_no'), 'auditee_dept' => self::s($row, 'auditee_dept'),
            'severity' => self::s($row, 'severity', 'medium'),
        ));
        return self::done(array('finding_id' => (int) $id));
    }

    /**
     * **والإغلاقُ بتحقّقِ المراجعةِ لا بادّعاءِ الجهة** — والخاضعُ للمراجعةِ
     * لا يغلق ملاحظتَه، والحوكمةُ لا تغلقها نيابةً عن المراجعة.
     */
    public static function closeFinding(TenantDb $gate, $findingId, array $row, $actorId)
    {
        $f = $gate->selectOne('iaf_findings', array('where' => array('id' => (int) $findingId)));
        if (!$f) { return self::fail('FINDING_NOT_FOUND'); }
        if ((int) $f['auditee_user_id'] !== 0 && (int) $f['auditee_user_id'] === (int) $actorId) {
            return self::fail('AUDITEE_CANNOT_CLOSE_OWN_FINDING');
        }
        if (self::s($row, 'evidence_ref') === '') { return self::fail('FINDING_CLOSE_WITHOUT_EVIDENCE'); }
        $closer = self::s($row, 'closer_dept', self::DOMAIN);
        if ($closer !== self::DOMAIN) {
            return self::fail('AUDIT_RESULT_CLOSED_OUTSIDE_IAF', $closer);
        }
        try {
            $gate->update('iaf_findings', array(
                'evidence_ref'          => self::s($row, 'evidence_ref'),
                'evidence_accepted'     => 1,
                'accepted_by'           => (int) $actorId,
                'closed_by'             => (int) $actorId,
                'closed_at'             => date('Y-m-d H:i:s'),
                'state'                 => 'closed',
                'result_closed_by_dept' => self::DOMAIN,
            ), array('id' => (int) $findingId));
        } catch (\Throwable $t) { return self::fail('FINDING_CLOSE_REFUSED', $t->getMessage()); }
        self::emit('iaf.finding.closed', 'iaf_findings', (int) $findingId, array(
            'finding_no' => (string) $f['finding_no'], 'closed_by_dept' => self::DOMAIN,
        ));
        return self::done();
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑤ مخاطرُ الوظيفةِ نفسِها — وترفع لخطِّ الرفعِ بالميثاق
       ══════════════════════════════════════════════════════════════════════ */

    public static function recordFunctionRisk(TenantDb $gate, array $row)
    {
        $to = self::s($row, 'reported_to');
        if ($to !== '' && !in_array($to, array('owner', 'audit_committee'), true)) {
            return self::fail('FUNCTION_RISK_REPORTED_TO_MANAGEMENT',
                              'خط الرفع بالميثاق لا الادارة التنفيذية');
        }
        try {
            $id = $gate->insert('iaf_function_risk', array(
                'risk_no'      => self::s($row, 'risk_no'),
                'risk_kind'    => self::s($row, 'risk_kind'),
                'title_ar'     => self::s($row, 'title_ar'),
                'level_ar'     => self::s($row, 'level_ar'),
                'treatment_ar' => self::s($row, 'treatment_ar'),
                'owner_person' => self::i($row, 'owner_person'),
                'reported_to'  => $to,
                'review_due'   => self::s($row, 'review_due') !== '' ? self::s($row, 'review_due') : null,
                'state'        => self::s($row, 'state', 'identified'),
                'src_ref'      => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('FUNCTION_RISK_REFUSED', $t->getMessage()); }
        return self::done(array('function_risk_id' => (int) $id));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑥ الحدودُ التي لا تُعبَر — تُختبَر ولا تُدَّعى
       ══════════════════════════════════════════════════════════════════════ */

    public static function attemptOpenDailyInvestigation()
    {
        return self::fail('IAF_HAS_NO_DAILY_INVESTIGATION_QUEUE',
                          'المراجعة تدخل بتكليف مكتوب لا باختصاص اصيل');
    }

    public static function attemptWriteRiskRegister()
    {
        return self::fail('AUDIT_CANNOT_WRITE_RISK_REGISTER',
                          'المراجعة لا تملك سجل المخاطر');
    }

    public static function attemptWriteGovernanceCase()
    {
        return self::fail('AUDIT_CANNOT_OPEN_GOVERNANCE_CASE',
                          'المراجعة لا تفتح حالة حوكمة ولا تملك الامتثال اليومي');
    }

    private static function emit($eventKey, $table, $entityId, array $payload)
    {
        $conn = self::$eventConn;
        if (!($conn instanceof \mysqli)) { return null; }
        if (!class_exists('\App\Core\EventPublisher')) { return null; }
        try {
            return \App\Core\EventPublisher::publishFact($conn, array(
                'company_id'      => self::$company,
                'event_key'       => $eventKey,
                'category'        => 'audit',
                'source_module'   => 'audit',
                'entity_type'     => $table,
                'entity_id'       => (int) $entityId,
                'payload'         => $payload,
                'idempotency_key' => 'w14:' . $eventKey . ':' . (int) $entityId . ':'
                                     . substr(sha1(json_encode($payload)), 0, 12),
                'source_ref'      => 'AuditDomainService',
            ));
        } catch (\Throwable $t) { return null; }
    }
}
