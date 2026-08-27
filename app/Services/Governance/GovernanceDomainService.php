<?php
/**
 * GovernanceDomainService — نطاقُ الحوكمةِ والالتزامِ وحدَه (RPR-W14 · `DEP-08`)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **خطٌّ ثانٍ يملك الحوكمةَ ولا يملك سجلَّ المخاطرِ ولا خطّةَ المراجعةِ ولا
 *   نتيجتَها** (‏حدودُ W14 النهائيّة · قيدُ المالك §١). ⛔ **ولا يكتب حرفًا في
 *   `risk_*` ولا في `iaf_*`** — والماسحُ البنيويُّ يقرأ هذا الملفَّ ويسقط إن فعل.
 *
 * ◆ **ولا تُفتح حالةُ حوكمةٍ لانحرافٍ تشغيليٍّ صِرف** (§27 · قرارُ المالك ②):
 *   `openBreach` تقرأ تصنيفَ الانحرافِ **بمرجعِه** فتردُّ
 *   `BREACH_ON_PURE_DEVIATION` على `DEVIATION_ONLY` و`BREACH_BEFORE_CLASSIFY`
 *   على غيرِ المُصنَّف — والأساسُ محصورٌ في الثمانيةِ التي سمّاها المالك.
 *
 * ◆ **والتحقيقُ ثلاثةُ أنواعٍ بثلاثةِ ملّاك** (‏`DEC-OPEN-16` معتمَد): التأديبيُّ
 *   للموارد `DEP-07` — «تستقبل النتيجةَ للأثرِ التأديبيِّ ولا تعيد التحقيقَ
 *   نفسَه» — والنزاهةُ للحوكمة، والتقصّي التشغيليُّ للإدارةِ المختصّة،
 *   و`SPECIAL_INDEPENDENT` للمراجعةِ **بتكليفٍ مكتوبٍ حالةً بحالة**.
 *   **والنقرُ الممنوعُ ليس تحقيقًا**: `DENIAL` يشترط فرزًا سابقًا.
 *
 * ◆ **والحوكمةُ تتابع نتيجةَ المراجعةِ ولا تعدّلها**: `trackAuditFinding` تكتب
 *   في `gov_audit_followup` **وحدَه**، و`attemptSetAuditResult` تردُّ دائمًا —
 *   والقيدُ `chk_iaf_result_dept` في القاعدةِ يردُّها ولو حاولت.
 *
 * ◆ **وسجلُّ أنواعِ الطلباتِ حوكمةٌ لا توجيه** (‏قرارُ المالك ③): الحوكمةُ تملك
 *   قواعدَ إنشائِه وتسميتِه وإصدارِه وتقاعدِه، **والمجالُ يملك تعريفَ طلبِه**،
 *   و`AAM` يحلُّ سلطةَ الاعتماد، والنظامُ ينفّذ التوجيه.
 *
 * ⛔ **ولا عتبةٌ رقميّةٌ في هذا الملفّ** — كلُّها عبرَ `ThresholdRegistry`.
 * ═══════════════════════════════════════════════════════════════════════════
 */

namespace App\Services\Governance;

use App\Core\TenantDb;
use App\Services\Control\ThresholdRegistry;

class GovernanceDomainService
{
    const DOMAIN = 'DEP-08';

    const BREACH_BASES = array('MANDATORY_STEP_IGNORED', 'NO_ESCALATION', 'AUTHORITY_EXCEEDED',
                               'MANIPULATION', 'CONCEALMENT', 'FORGERY', 'POLICY_BREACH', 'CONTROL_BROKEN');

    /** النوعُ ⇐ مالكُه — ولا رابعَ خارجَ هذه الأربعة */
    const INV_OWNER = array(
        'DISCIPLINARY'        => 'DEP-07',
        'INTEGRITY'           => 'DEP-08',
        'SPECIAL_INDEPENDENT' => 'IAF',
    );

    const K_GIFT = 'gov.gift.disclosure_threshold';

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
    private static function f($r, $k, $d = 0.0) { return isset($r[$k]) ? (float) $r[$k] : $d; }

    /* ══════════════════════════════════════════════════════════════════════
       ① السياسةُ والالتزامُ والتقديم
       ══════════════════════════════════════════════════════════════════════ */

    public static function draftPolicy(TenantDb $gate, array $row, $actorId)
    {
        if (self::s($row, 'policy_no') === '') { return self::fail('POLICY_NO_REQUIRED'); }
        try {
            $id = $gate->insert('gov_policy', array(
                'policy_no'    => self::s($row, 'policy_no'),
                'version_no'   => self::i($row, 'version_no', 1),
                'title_ar'     => self::s($row, 'title_ar'),
                'domain_ar'    => self::s($row, 'domain_ar'),
                'owner_dept'   => self::s($row, 'owner_dept'),
                'owner_person' => self::i($row, 'owner_person'),
                'doc_ref'      => self::s($row, 'doc_ref'),
                'authored_by'  => (int) $actorId,
                'state'        => 'draft',
                'src_ref'      => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('POLICY_REFUSED', $t->getMessage()); }
        return self::done(array('policy_id' => (int) $id));
    }

    /** **ومن كتب السياسةَ لا يعتمدها** — و`chk_gvp_sod` يردُّه في القاعدة */
    public static function effectPolicy(TenantDb $gate, $policyId, $actorId, $from)
    {
        $p = $gate->selectOne('gov_policy', array('where' => array('id' => (int) $policyId)));
        if (!$p) { return self::fail('POLICY_NOT_FOUND'); }
        if ((int) $p['authored_by'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_AUTHOR_AND_APPROVE_POLICY');
        }
        if ((string) $p['doc_ref'] === '') { return self::fail('POLICY_WITHOUT_DOC'); }
        try {
            $gate->update('gov_policy', array(
                'state' => 'effective', 'approved_by' => (int) $actorId,
                'approved_at' => date('Y-m-d H:i:s'), 'effective_from' => (string) $from,
            ), array('id' => (int) $policyId));
        } catch (\Throwable $t) { return self::fail('POLICY_EFFECT_REFUSED', $t->getMessage()); }
        self::emit('gov.policy.effective', 'gov_policy', (int) $policyId, array(
            'policy_no' => (string) $p['policy_no'], 'version_no' => (int) $p['version_no'],
        ));
        return self::done();
    }

    public static function registerObligation(TenantDb $gate, array $row)
    {
        if (self::s($row, 'authority_ar') === '' || self::s($row, 'owner_dept') === '') {
            return self::fail('OBLIGATION_WITHOUT_AUTHORITY_OR_OWNER');
        }
        try {
            $id = $gate->insert('gov_obligation', array(
                'obligation_no' => self::s($row, 'obligation_no'),
                'title_ar'      => self::s($row, 'title_ar'),
                'authority_ar'  => self::s($row, 'authority_ar'),
                'basis_ref'     => self::s($row, 'basis_ref'),
                'periodicity'   => self::s($row, 'periodicity', 'annual'),
                'owner_dept'    => self::s($row, 'owner_dept'),
                'owner_person'  => self::i($row, 'owner_person'),
                'policy_ref'    => self::s($row, 'policy_ref'),
                'next_due'      => self::s($row, 'next_due') !== '' ? self::s($row, 'next_due') : null,
                'state'         => 'registered',
                'src_ref'       => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('OBLIGATION_REFUSED', $t->getMessage()); }
        return self::done(array('obligation_id' => (int) $id));
    }

    /** **الاستحقاقُ مشتقٌّ بمرجعِ اشتقاقِه** — ولا يُدخَل يدويًّا بلا التزام */
    public static function deriveDue(TenantDb $gate, array $row)
    {
        if (self::s($row, 'obligation_no') === '' || self::s($row, 'derived_from') === '') {
            return self::fail('DUE_WITHOUT_DERIVATION');
        }
        try {
            $id = $gate->insert('gov_compliance_due', array(
                'obligation_no' => self::s($row, 'obligation_no'),
                'due_date'      => self::s($row, 'due_date'),
                'owner_dept'    => self::s($row, 'owner_dept'),
                'owner_person'  => self::i($row, 'owner_person'),
                'derived_from'  => self::s($row, 'derived_from'),
                'state'         => 'due',
                'src_ref'       => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('DUE_REFUSED', $t->getMessage()); }
        self::emit('gov.obligation.due', 'gov_compliance_due', (int) $id, array(
            'obligation_no' => self::s($row, 'obligation_no'), 'due_date' => self::s($row, 'due_date'),
        ));
        return self::done(array('due_id' => (int) $id));
    }

    public static function submitFiling(TenantDb $gate, array $row, $actorId)
    {
        try {
            $id = $gate->insert('gov_filing', array(
                'filing_no'     => self::s($row, 'filing_no'),
                'obligation_no' => self::s($row, 'obligation_no'),
                'authority_ar'  => self::s($row, 'authority_ar'),
                'period_label'  => self::s($row, 'period_label'),
                'due_date'      => self::s($row, 'due_date') !== '' ? self::s($row, 'due_date') : null,
                'submitted_at'  => date('Y-m-d H:i:s'),
                'submitted_by'  => (int) $actorId,
                'receipt_ref'   => self::s($row, 'receipt_ref'),
                'state'         => 'submitted',
                'src_ref'       => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('FILING_REFUSED', $t->getMessage()); }
        self::emit('gov.filing.submitted', 'gov_filing', (int) $id, array(
            'filing_no' => self::s($row, 'filing_no'), 'authority' => self::s($row, 'authority_ar'),
        ));
        return self::done(array('filing_id' => (int) $id));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ② الإفصاحاتُ والأطرافُ وفصلُ الواجبات
       ══════════════════════════════════════════════════════════════════════ */

    public static function discloseConflict(TenantDb $gate, array $row)
    {
        if (self::i($row, 'person_id') <= 0) { return self::fail('DISCLOSURE_WITHOUT_PERSON'); }
        try {
            $id = $gate->insert('gov_conflict_disclosure', array(
                'disclosure_no'    => self::s($row, 'disclosure_no'),
                'person_id'        => self::i($row, 'person_id'),
                'nature_ar'        => self::s($row, 'nature_ar'),
                'counterparty_ar'  => self::s($row, 'counterparty_ar'),
                'related_party_no' => self::s($row, 'related_party_no'),
                'disclosed_at'     => date('Y-m-d H:i:s'),
                'state'            => 'disclosed',
                'src_ref'          => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('DISCLOSURE_REFUSED', $t->getMessage()); }
        return self::done(array('disclosure_id' => (int) $id));
    }

    /** **ولا يقرّر صاحبُ الإفصاحِ في إفصاحِه** — و`chk_gcf_self` يردُّه */
    public static function decideConflict(TenantDb $gate, $disclosureId, $decision, array $row, $actorId)
    {
        $d = $gate->selectOne('gov_conflict_disclosure', array('where' => array('id' => (int) $disclosureId)));
        if (!$d) { return self::fail('DISCLOSURE_NOT_FOUND'); }
        if ((int) $d['person_id'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_DISCLOSE_AND_DECIDE');
        }
        if ($decision === 'recuse' && self::s($row, 'recused_from') === '') {
            return self::fail('RECUSAL_WITHOUT_SCOPE');
        }
        $stateMap = array('mitigate' => 'mitigated', 'recuse' => 'recused', 'reject' => 'rejected');
        if (!isset($stateMap[$decision])) { return self::fail('DECISION_UNKNOWN', (string) $decision); }
        try {
            $gate->update('gov_conflict_disclosure', array(
                'assessed_by'  => (int) $actorId,
                'decision'     => (string) $decision,
                'decision_ref' => self::s($row, 'decision_ref'),
                'recused_from' => self::s($row, 'recused_from'),
                'state'        => $stateMap[$decision],
            ), array('id' => (int) $disclosureId));
        } catch (\Throwable $t) { return self::fail('DECISION_REFUSED', $t->getMessage()); }
        return self::done();
    }

    /** **الوسمُ بين الكيانَين منذ الإنشاء** (‏قرارُ المالك ①) — لا بأثرٍ رجعيّ */
    public static function registerRelatedParty(TenantDb $gate, array $row)
    {
        $ic = self::i($row, 'intercompany_flag');
        if ($ic === 1) {
            foreach (array('from_legal_entity_id', 'to_legal_entity_id', 'counterparty_entity_id') as $k) {
                if (self::i($row, $k) <= 0) { return self::fail('INTERCOMPANY_TUPLE_INCOMPLETE', $k); }
            }
            if (self::s($row, 'transaction_type') === '') {
                return self::fail('INTERCOMPANY_TUPLE_INCOMPLETE', 'transaction_type');
            }
            if (self::i($row, 'from_legal_entity_id') === self::i($row, 'to_legal_entity_id')) {
                return self::fail('INTERCOMPANY_SAME_ENTITY');
            }
        }
        try {
            $id = $gate->insert('gov_related_party', array(
                'party_no'   => self::s($row, 'party_no'),
                'party_name' => self::s($row, 'party_name'),
                'relation_ar' => self::s($row, 'relation_ar'),
                'person_id'  => self::i($row, 'person_id'),
                'deal_ref'   => self::s($row, 'deal_ref'),
                'deal_amount' => isset($row['deal_amount']) ? self::f($row, 'deal_amount') : null,
                'deal_currency' => self::s($row, 'deal_currency'),
                'disclosure_no' => self::s($row, 'disclosure_no'),
                'from_legal_entity_id'   => self::i($row, 'from_legal_entity_id'),
                'to_legal_entity_id'     => self::i($row, 'to_legal_entity_id'),
                'intercompany_flag'      => $ic,
                'counterparty_entity_id' => self::i($row, 'counterparty_entity_id'),
                'transaction_type'       => self::s($row, 'transaction_type'),
                'counterparty_ref'       => self::s($row, 'counterparty_ref'),
                'state'      => 'declared',
                'src_ref'    => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('RELATED_PARTY_REFUSED', $t->getMessage()); }
        return self::done(array('party_id' => (int) $id));
    }

    /** **والحدُّ من السجلِّ لا من الشيفرة** — والمعلَّقُ يردُّ ولا يُخترَع */
    public static function discloseGift(TenantDb $gate, array $row)
    {
        $th = ThresholdRegistry::read(self::K_GIFT);
        if (!$th['ok']) {
            return self::fail(ThresholdRegistry::NOT_CONFIGURED, self::K_GIFT);
        }
        try {
            $id = $gate->insert('gov_gift_disclosure', array(
                'gift_no'       => self::s($row, 'gift_no'),
                'person_id'     => self::i($row, 'person_id'),
                'gift_kind'     => self::s($row, 'gift_kind', 'gift'),
                'giver_ar'      => self::s($row, 'giver_ar'),
                'est_value'     => isset($row['est_value']) ? self::f($row, 'est_value') : null,
                'currency'      => self::s($row, 'currency'),
                'threshold_key' => self::K_GIFT,
                'disclosed_at'  => date('Y-m-d H:i:s'),
                'state'         => 'disclosed',
                'src_ref'       => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('GIFT_REFUSED', $t->getMessage()); }
        return self::done(array('gift_id' => (int) $id, 'threshold_tag' => $th['tagged']));
    }

    public static function acknowledgeConduct(TenantDb $gate, array $row)
    {
        if (self::i($row, 'employee_id') <= 0 || self::s($row, 'code_version') === '') {
            return self::fail('CONDUCT_ACK_INCOMPLETE');
        }
        if (self::s($row, 'evidence_ref') === '') { return self::fail('CONDUCT_ACK_WITHOUT_EVIDENCE'); }
        try {
            $id = $gate->insert('gov_conduct_ack', array(
                'employee_id'  => self::i($row, 'employee_id'),
                'code_version' => self::s($row, 'code_version'),
                'policy_no'    => self::s($row, 'policy_no'),
                'due_date'     => self::s($row, 'due_date') !== '' ? self::s($row, 'due_date') : null,
                'acked_at'     => date('Y-m-d H:i:s'),
                'evidence_ref' => self::s($row, 'evidence_ref'),
                'state'        => 'acknowledged',
                'src_ref'      => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('CONDUCT_ACK_REFUSED', $t->getMessage()); }
        return self::done(array('ack_id' => (int) $id));
    }

    /** **ولا يقرّر المفصحُ في إفصاحِه** — و`chk_ggf_self` يردُّه في القاعدة */
    public static function decideGift(TenantDb $gate, $giftId, $decision, $actorId)
    {
        $g = $gate->selectOne('gov_gift_disclosure', array('where' => array('id' => (int) $giftId)));
        if (!$g) { return self::fail('GIFT_NOT_FOUND'); }
        if ((int) $g['person_id'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_DISCLOSE_AND_DECIDE_GIFT');
        }
        $map = array('accept' => 'accepted', 'return' => 'returned', 'decline' => 'declined');
        if (!isset($map[$decision])) { return self::fail('DECISION_UNKNOWN', (string) $decision); }
        try {
            $gate->update('gov_gift_disclosure', array(
                'decided_by' => (int) $actorId, 'decision' => (string) $decision,
                'state' => $map[$decision],
            ), array('id' => (int) $giftId));
        } catch (\Throwable $t) { return self::fail('GIFT_DECISION_REFUSED', $t->getMessage()); }
        return self::done();
    }

    /**
     * **قبولُ تعارضٍ في فصلِ الواجباتِ لا يقع إلّا باستثناءٍ بمدّتِه.**
     * ⛔ «لا استثناءَ دائمًا: كلُّ استثناءٍ بمدّةٍ وخطورةٍ ومعتمدٌ بحسبها» —
     * والمدّةُ القصوى **عتبةٌ معلَّقةٌ** تُقرأ من السجلِّ ولا تُخترَع، فالقبولُ
     * يردُّ حين لا مدّةَ مُعلَنةً أصلًا.
     */
    public static function acceptSodConflictWithException(TenantDb $gate, $conflictId,
                                                          $exceptionNo, $durationDays, $actorId)
    {
        $cf = $gate->selectOne('gov_sod_conflict', array('where' => array('id' => (int) $conflictId)));
        if (!$cf) { return self::fail('SOD_CONFLICT_NOT_FOUND'); }
        if ((string) $exceptionNo === '' || (int) $durationDays <= 0) {
            return self::fail('SOD_EXCEPTION_WITHOUT_DURATION', 'لا استثناء دائما ولا بلا مدة');
        }
        if ((int) $cf['detected_user_id'] !== 0 && (int) $cf['detected_user_id'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_CONFLICTED_AND_ACCEPTS');
        }
        $th = ThresholdRegistry::read('gov.exception.max_duration_days');
        if ($th['ok'] && (float) $durationDays > $th['value']) {
            return self::fail('SOD_EXCEPTION_ABOVE_MAX_DURATION', (string) $durationDays);
        }
        try {
            $gate->update('gov_sod_conflict', array(
                'exception_no' => (string) $exceptionNo, 'state' => 'accepted',
            ), array('id' => (int) $conflictId));
        } catch (\Throwable $t) { return self::fail('SOD_ACCEPT_REFUSED', $t->getMessage()); }
        return self::done(array('threshold_tag' => $th['tagged']));
    }

    public static function defineSodConflict(TenantDb $gate, array $row)
    {
        if (self::s($row, 'side_a') === '' || self::s($row, 'side_b') === ''
            || self::s($row, 'side_a') === self::s($row, 'side_b')) {
            return self::fail('SOD_SIDES_INVALID');
        }
        try {
            $id = $gate->insert('gov_sod_conflict', array(
                'conflict_code'    => self::s($row, 'conflict_code'),
                'title_ar'         => self::s($row, 'title_ar'),
                'side_a'           => self::s($row, 'side_a'),
                'side_b'           => self::s($row, 'side_b'),
                'process_key'      => self::s($row, 'process_key'),
                'detected_role_id' => self::i($row, 'detected_role_id'),
                'detected_user_id' => self::i($row, 'detected_user_id'),
                'detected_at'      => self::s($row, 'detected_at') !== '' ? self::s($row, 'detected_at') : null,
                'state'            => self::s($row, 'state', 'defined'),
                'src_ref'          => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('SOD_REFUSED', $t->getMessage()); }
        return self::done(array('conflict_id' => (int) $id));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ③ القناةُ المحميّةُ والتحقيقاتُ وحالةُ الحوكمة
       ══════════════════════════════════════════════════════════════════════ */

    public static function receiveIntegrityReport(TenantDb $gate, array $row)
    {
        $token = self::s($row, 'reporter_token');
        if ($token === '') { return self::fail('INTEGRITY_REPORT_WITHOUT_TOKEN'); }
        $anon = self::i($row, 'is_anonymous', 1);
        if ($anon === 1 && self::i($row, 'reporter_person') !== 0) {
            return self::fail('ANONYMOUS_WITH_IDENTITY');
        }
        try {
            $id = $gate->insert('gov_integrity_report', array(
                'report_no'           => self::s($row, 'report_no'),
                'channel'             => self::s($row, 'channel', 'protected'),
                'is_anonymous'        => $anon,
                'reporter_token'      => $token,
                'reporter_person'     => self::i($row, 'reporter_person'),
                'disclosure_role_key' => self::s($row, 'disclosure_role_key'),
                'subject_ar'          => self::s($row, 'subject_ar'),
                'received_at'         => date('Y-m-d H:i:s'),
                'state'               => 'received',
                'src_ref'             => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('INTEGRITY_REPORT_REFUSED', $t->getMessage()); }
        return self::done(array('report_id' => (int) $id));
    }

    /** **والفرزُ سابقٌ للتحقيقِ لا لاحقٌ له** — وهو المحطّةُ التي تمنع أتمتةَ الاتّهام */
    public static function triageIntegrityReport(TenantDb $gate, $reportId, $referTo, $actorId)
    {
        $r = $gate->selectOne('gov_integrity_report', array('where' => array('id' => (int) $reportId)));
        if (!$r) { return self::fail('INTEGRITY_REPORT_NOT_FOUND'); }
        try {
            $gate->update('gov_integrity_report', array(
                'triage_by' => (int) $actorId, 'triage_at' => date('Y-m-d H:i:s'),
                'referred_to' => (string) $referTo,
                'state' => ((string) $referTo !== '' ? 'referred' : 'triaged'),
            ), array('id' => (int) $reportId));
        } catch (\Throwable $t) { return self::fail('TRIAGE_REFUSED', $t->getMessage()); }
        self::emit('gov.integrity.triaged', 'gov_integrity_report', (int) $reportId, array(
            'referred_to' => (string) $referTo,
        ));
        return self::done(array('triage_ref' => (string) $r['report_no']));
    }

    public static function openInvestigation(TenantDb $gate, array $row, $actorId)
    {
        $kind  = self::s($row, 'inv_kind');
        $owner = self::s($row, 'owner_dept');
        if (isset(self::INV_OWNER[$kind])) {
            if ($owner !== self::INV_OWNER[$kind]) {
                return self::fail('INVESTIGATION_KIND_OUTSIDE_OWNER', $kind . ' ⇐ ' . $owner);
            }
        } elseif ($kind === 'OPERATIONAL_FACT') {
            if ($owner === '' || in_array($owner, array('DEP-07', 'DEP-08', 'IAF'), true)) {
                return self::fail('INVESTIGATION_KIND_OUTSIDE_OWNER', $kind . ' ⇐ ' . $owner);
            }
        } else {
            return self::fail('INVESTIGATION_KIND_UNKNOWN', $kind);
        }
        if ($kind === 'SPECIAL_INDEPENDENT' && self::s($row, 'mandate_doc_ref') === '') {
            return self::fail('IAF_INVESTIGATION_WITHOUT_MANDATE');
        }
        if (self::s($row, 'origin') === 'DENIAL' && self::s($row, 'triage_ref') === '') {
            return self::fail('DENIAL_IS_NOT_AN_INVESTIGATION', 'الفرز سابق للتحقيق');
        }
        if (self::i($row, 'conflict_flag') === 1
            && (self::s($row, 'recusal_of') === '' || self::s($row, 'reserved_authority_ref') === '')) {
            return self::fail('CONFLICT_WITHOUT_RECUSAL');
        }
        if (self::i($row, 'investigator_id') !== 0
            && self::i($row, 'investigator_id') === self::i($row, 'subject_person')) {
            return self::fail('INVESTIGATOR_IS_SUBJECT');
        }
        try {
            $id = $gate->insert('gov_investigation', array(
                'inv_no'          => self::s($row, 'inv_no'),
                'inv_kind'        => $kind,
                'owner_dept'      => $owner,
                'origin'          => self::s($row, 'origin'),
                'origin_ref'      => self::s($row, 'origin_ref'),
                'triage_ref'      => self::s($row, 'triage_ref'),
                'mandate_doc_ref' => self::s($row, 'mandate_doc_ref'),
                'subject_person'  => self::i($row, 'subject_person'),
                'scope_ar'        => self::s($row, 'scope_ar'),
                'investigator_id' => self::i($row, 'investigator_id'),
                'opened_by'       => (int) $actorId,
                'conflict_flag'   => self::i($row, 'conflict_flag'),
                'recusal_of'      => self::s($row, 'recusal_of'),
                'reserved_authority_ref' => self::s($row, 'reserved_authority_ref'),
                'state'           => 'mandated',
                'src_ref'         => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('INVESTIGATION_REFUSED', $t->getMessage()); }
        return self::done(array('investigation_id' => (int) $id));
    }

    /** **ومن فتح التحقيقَ لا يحسمه** — والنتيجةُ تُحال لجهةِ أثرِها لا تُعاد عندها */
    public static function concludeInvestigation(TenantDb $gate, $invId, array $row, $actorId)
    {
        $i = $gate->selectOne('gov_investigation', array('where' => array('id' => (int) $invId)));
        if (!$i) { return self::fail('INVESTIGATION_NOT_FOUND'); }
        if ((int) $i['opened_by'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_OPEN_AND_CONCLUDE');
        }
        if (self::s($row, 'conclusion_ar') === '') { return self::fail('CONCLUSION_REQUIRED'); }
        try {
            $gate->update('gov_investigation', array(
                'conclusion_ar' => self::s($row, 'conclusion_ar'),
                'concluded_by'  => (int) $actorId,
                'concluded_at'  => date('Y-m-d H:i:s'),
                'referred_to'   => self::s($row, 'referred_to'),
                'state'         => (self::s($row, 'referred_to') !== '' ? 'referred' : 'concluded'),
            ), array('id' => (int) $invId));
        } catch (\Throwable $t) { return self::fail('CONCLUDE_REFUSED', $t->getMessage()); }
        self::emit('gov.investigation.concluded', 'gov_investigation', (int) $invId, array(
            'inv_kind' => (string) $i['inv_kind'], 'referred_to' => self::s($row, 'referred_to'),
        ));
        return self::done();
    }

    /**
     * **⛔ ولا تُفتح حالةُ حوكمةٍ لانحرافٍ تشغيليٍّ صِرف.**
     * الانحرافُ يُقرأ **بمرجعِه** من سجلِّ مالكِه — ولا يُنسَخ ولا يُعدَّل هنا.
     */
    public static function openBreach(TenantDb $gate, array $row, $actorId)
    {
        $basis = self::s($row, 'opened_basis');
        if (!in_array($basis, self::BREACH_BASES, true)) {
            return self::fail('BREACH_BASIS_OUTSIDE_EIGHT', $basis);
        }
        $dev = self::s($row, 'deviation_no');
        if ($dev !== '') {
            $d = $gate->selectOne('ctl_deviation', array('where' => array('deviation_no' => $dev)));
            if (!$d) { return self::fail('DEVIATION_REF_NOT_FOUND', $dev); }
            if ((string) $d['classification'] === 'DEVIATION_ONLY') {
                return self::fail('BREACH_ON_PURE_DEVIATION', 'انحراف تشغيلي صرف يبقى عند مالكه');
            }
            if ((string) $d['classification'] === 'PENDING') {
                return self::fail('BREACH_BEFORE_CLASSIFY', 'لا حالة حوكمة قبل التصنيف');
            }
        }
        if (self::s($row, 'control_ref') === '' && self::s($row, 'policy_no') === ''
            && self::s($row, 'obligation_no') === '') {
            return self::fail('BREACH_WITHOUT_BROKEN_CONTROL');
        }
        try {
            $id = $gate->insert('gov_breach', array(
                'case_no'       => self::s($row, 'case_no'),
                'opened_basis'  => $basis,
                'control_ref'   => self::s($row, 'control_ref'),
                'policy_no'     => self::s($row, 'policy_no'),
                'obligation_no' => self::s($row, 'obligation_no'),
                'deviation_no'  => $dev,
                'severity'      => self::s($row, 'severity', 'medium'),
                'title_ar'      => self::s($row, 'title_ar'),
                'opened_by'     => (int) $actorId,
                'opened_at'     => date('Y-m-d H:i:s'),
                'state'         => 'opened',
                'src_ref'       => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('BREACH_REFUSED', $t->getMessage()); }
        self::emit('gov.breach.opened', 'gov_breach', (int) $id, array(
            'case_no' => self::s($row, 'case_no'), 'basis' => $basis, 'deviation_no' => $dev,
        ));
        return self::done(array('breach_id' => (int) $id, 'case_no' => self::s($row, 'case_no')));
    }

    public static function assignAction(TenantDb $gate, array $row, $actorId)
    {
        if (self::s($row, 'source_ref') === '') { return self::fail('ACTION_WITHOUT_SOURCE'); }
        if (self::s($row, 'owner_dept') === '' || self::i($row, 'owner_person') <= 0
            || self::s($row, 'due_date') === '') {
            return self::fail('ACTION_WITHOUT_OWNER_OR_DUE');
        }
        try {
            $id = $gate->insert('gov_corrective_action', array(
                'action_no'    => self::s($row, 'action_no'),
                'source_kind'  => self::s($row, 'source_kind'),
                'source_ref'   => self::s($row, 'source_ref'),
                'title_ar'     => self::s($row, 'title_ar'),
                'owner_dept'   => self::s($row, 'owner_dept'),
                'owner_person' => self::i($row, 'owner_person'),
                'due_date'     => self::s($row, 'due_date'),
                'assigned_by'  => (int) $actorId,
                'state'        => 'assigned',
                'src_ref'      => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('ACTION_REFUSED', $t->getMessage()); }
        self::emit('gov.action.assigned', 'gov_corrective_action', (int) $id, array(
            'action_no' => self::s($row, 'action_no'), 'source_ref' => self::s($row, 'source_ref'),
        ));
        return self::done(array('action_id' => (int) $id));
    }

    /** **ومالكُ الإجراءِ لا يتحقّق من إجرائِه** — و`chk_gac_hands` يردُّه */
    public static function verifyAction(TenantDb $gate, $actionId, $evidenceRef, $actorId)
    {
        $a = $gate->selectOne('gov_corrective_action', array('where' => array('id' => (int) $actionId)));
        if (!$a) { return self::fail('ACTION_NOT_FOUND'); }
        if ((int) $a['owner_person'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_OWN_AND_VERIFY_ACTION');
        }
        if ((string) $evidenceRef === '') { return self::fail('ACTION_CLOSE_WITHOUT_EVIDENCE'); }
        try {
            $gate->update('gov_corrective_action', array(
                'evidence_ref' => (string) $evidenceRef, 'verified_by' => (int) $actorId,
                'verified_at' => date('Y-m-d H:i:s'), 'state' => 'verified',
            ), array('id' => (int) $actionId));
        } catch (\Throwable $t) { return self::fail('ACTION_VERIFY_REFUSED', $t->getMessage()); }
        self::emit('gov.action.closed', 'gov_corrective_action', (int) $actionId, array(
            'action_no' => (string) $a['action_no'], 'evidence' => (string) $evidenceRef,
        ));
        return self::done();
    }

    /** **ومن فتح الحالةَ لا يغلقها** — ولا تُغلَق بلا إجراءٍ ودليل */
    public static function closeBreach(TenantDb $gate, $breachId, array $row, $actorId)
    {
        $b = $gate->selectOne('gov_breach', array('where' => array('id' => (int) $breachId)));
        if (!$b) { return self::fail('BREACH_NOT_FOUND'); }
        if ((int) $b['opened_by'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_OPEN_AND_CLOSE_BREACH');
        }
        if (self::s($row, 'action_no') === '' || self::s($row, 'close_evidence') === '') {
            return self::fail('BREACH_CLOSE_WITHOUT_ACTION_OR_EVIDENCE');
        }
        try {
            $gate->update('gov_breach', array(
                'action_no'      => self::s($row, 'action_no'),
                'close_evidence' => self::s($row, 'close_evidence'),
                'closed_by'      => (int) $actorId,
                'closed_at'      => date('Y-m-d H:i:s'),
                'state'          => 'closed',
            ), array('id' => (int) $breachId));
        } catch (\Throwable $t) { return self::fail('BREACH_CLOSE_REFUSED', $t->getMessage()); }
        return self::done();
    }

    /* ══════════════════════════════════════════════════════════════════════
       ④ المتابعةُ لا التعديل — الخطُّ الثالثُ يبقى مستقلًّا
       ══════════════════════════════════════════════════════════════════════ */

    /** **الحوكمةُ تتابع خطّةَ الإدارةِ على الملاحظةِ بمرجعِها ولا تلمس النتيجة** */
    public static function trackAuditFinding(TenantDb $gate, array $row)
    {
        if (self::s($row, 'finding_no') === '') { return self::fail('FOLLOWUP_WITHOUT_FINDING_REF'); }
        if (self::s($row, 'mgmt_plan_ar') !== ''
            && (self::s($row, 'plan_owner_dept') === '' || self::s($row, 'plan_due') === '')) {
            return self::fail('FOLLOWUP_PLAN_WITHOUT_OWNER_OR_DUE');
        }
        try {
            $id = $gate->insert('gov_audit_followup', array(
                'followup_no'     => self::s($row, 'followup_no'),
                'finding_no'      => self::s($row, 'finding_no'),
                'finding_source'  => self::s($row, 'finding_source', 'internal'),
                'mgmt_plan_ar'    => self::s($row, 'mgmt_plan_ar'),
                'plan_owner_dept' => self::s($row, 'plan_owner_dept'),
                'plan_due'        => self::s($row, 'plan_due') !== '' ? self::s($row, 'plan_due') : null,
                'recurrence_no'   => self::i($row, 'recurrence_no', 1),
                'action_no'       => self::s($row, 'action_no'),
                'follow_state'    => self::s($row, 'follow_state', 'tracking'),
                'src_ref'         => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('FOLLOWUP_REFUSED', $t->getMessage()); }
        return self::done(array('followup_id' => (int) $id));
    }

    /**
     * ⛔ **الحدُّ الذي لا يُعبَر.**
     * يُستدعى في المحطّةِ السالبةِ من رحلةِ الضابط ويردُّ **دائمًا** — ولا يوجد
     * في هذا الملفِّ مسارٌ يكتب في `iaf_*` أصلًا، والماسحُ البنيويُّ يثبته،
     * والقيدُ `chk_iaf_result_dept` في القاعدةِ يردُّه ولو كُتب.
     */
    public static function attemptSetAuditResult()
    {
        return self::fail('GOVERNANCE_CANNOT_SET_AUDIT_RESULT',
                          'الحوكمة لا تضع نتيجة مراجعة ولا تغيرها ولا تغلقها');
    }

    public static function attemptSetAuditScope()
    {
        return self::fail('AUDIT_SCOPE_SET_BY_GOVERNANCE',
                          'الحوكمة لا تعطي المراجع نطاقه');
    }

    public static function attemptWriteRiskRegister()
    {
        return self::fail('GOVERNANCE_CANNOT_WRITE_RISK_REGISTER',
                          'الحوكمة لا تملك سجل المخاطر');
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑤ سجلُّ أنواعِ الطلبات — القاعدةُ الرباعيّة
       ══════════════════════════════════════════════════════════════════════ */

    public static function registerRequestType(TenantDb $gate, array $row)
    {
        $dom = self::s($row, 'definition_owner_dept');
        if ($dom === '' || $dom === self::DOMAIN) {
            return self::fail('REQUEST_TYPE_DEFINITION_NOT_OWNED_BY_DOMAIN',
                              'المجال يملك تعريف طلبه والحوكمة تحكم السجل');
        }
        if (self::s($row, 'authority_rule_id') === '') {
            return self::fail('REQUEST_TYPE_WITHOUT_AUTHORITY_RULE', 'AAM يحدد من يعتمد');
        }
        if (self::s($row, 'routing_rule_ref') === '') {
            return self::fail('REQUEST_TYPE_WITHOUT_ROUTING_RULE', 'النظام ينفذ التوجيه');
        }
        try {
            $id = $gate->insert('gov_request_type', array(
                'type_code'             => self::s($row, 'type_code'),
                'version_no'            => self::i($row, 'version_no', 1),
                'name_ar'               => self::s($row, 'name_ar'),
                'definition_owner_dept' => $dom,
                'registry_governed_by'  => self::DOMAIN,
                'authority_rule_id'     => self::s($row, 'authority_rule_id'),
                'routing_rule_ref'      => self::s($row, 'routing_rule_ref'),
                'permission_policy'     => self::s($row, 'permission_policy'),
                'exception_policy'      => self::s($row, 'exception_policy'),
                'state'                 => self::s($row, 'state', 'active'),
                'src_ref'               => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('REQUEST_TYPE_REFUSED', $t->getMessage()); }
        return self::done(array('request_type_id' => (int) $id));
    }

    public static function registerCommittee(TenantDb $gate, array $row)
    {
        try {
            $id = $gate->insert('gov_committee', array(
                'committee_code'    => self::s($row, 'committee_code'),
                'name_ar'           => self::s($row, 'name_ar'),
                'mandate_ar'        => self::s($row, 'mandate_ar'),
                'charter_ref'       => self::s($row, 'charter_ref'),
                'chair_person'      => self::i($row, 'chair_person'),
                'member_count'      => self::i($row, 'member_count'),
                'quorum_key'        => self::s($row, 'quorum_key'),
                'meeting_cycle'     => self::s($row, 'meeting_cycle', 'quarterly'),
                'authority_rule_id' => self::s($row, 'authority_rule_id'),
                'state'             => self::s($row, 'state', 'active'),
                'src_ref'           => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('COMMITTEE_REFUSED', $t->getMessage()); }
        return self::done(array('committee_id' => (int) $id));
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
                'category'        => 'governance',
                'source_module'   => 'governance',
                'entity_type'     => $table,
                'entity_id'       => (int) $entityId,
                'payload'         => $payload,
                'idempotency_key' => 'w14:' . $eventKey . ':' . (int) $entityId . ':'
                                     . substr(sha1(json_encode($payload)), 0, 12),
                'source_ref'      => 'GovernanceDomainService',
            ));
        } catch (\Throwable $t) { return null; }
    }
}
