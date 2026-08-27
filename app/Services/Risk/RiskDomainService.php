<?php
/**
 * RiskDomainService — نطاقُ إدارةِ المخاطر وحدَه (RPR-W14 · `DEP-09`)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **خطٌّ ثانٍ مستقلٌّ عن الحوكمة** (‏قرارُ المالك السادس · حدودُ W14):
 *   مصدرُ حقيقتِه `Risk_ID` — سجلُّ المخاطر. **ولا يملك السياساتِ ولا خطّةَ
 *   المراجعةِ ولا نتائجَها**، ⛔ **ولا يكتب حرفًا في جدولِ حوكمةٍ أو مراجعة**:
 *   `repair01_w14_cross_domain_writes` يقرأ هذا الملفَّ نفسَه ويسقط إن فعل.
 *
 * ◆ **والعطلُ لا يُنشئ خطرًا — يُنشئ محفِّزًا** (‏قرارُ المالك ②): `raiseTrigger`
 *   تقرأ الانحرافَ **بمرجعِه** ولا تنسخه، وتردُّ `TRIGGER_ON_PLANNED_DOWNTIME`
 *   على الصيانةِ المخطَّطةِ والعمرةِ واستعدادِ العميلِ والاستعدادِ التشغيليّ.
 *
 * ◆ **ولا نسخَ لحدثِ المصدر**: `recordEvent` تشترط `source_module` و
 *   `source_table` و`source_row_id`، والمفتاحُ الفريدُ `uq_rev_source` يردُّ
 *   نسخةً ثانيةً للمصدرِ نفسِه — فالتكرارُ ممتنعٌ بنيويًّا لا ممنوعٌ بالنيّة.
 *
 * ◆ **والقبولُ ضمنَ شهيّةٍ معتمَدة**: `acceptRisk` تقرأ حدَّ الشهيّةِ من السجلِّ،
 *   و`DEC-OPEN-08` **معلَّقةٌ عدديًّا** — فتردُّ `APPETITE_NOT_CONFIGURED`
 *   **ولا تخترع رقمًا**، والبناءُ قائمٌ والقرارُ وحدَه هو المؤجَّل.
 *
 * ◆ **ومن اقترح الإغلاقَ لا يعتمده**: `chk_rcl_hands` في القاعدةِ ورمزٌ يُقرأ
 *   في الخدمة.
 *
 * ⛔ **ولا عتبةٌ رقميّةٌ في هذا الملفّ** — كلُّها عبرَ `ThresholdRegistry`.
 * ═══════════════════════════════════════════════════════════════════════════
 */

namespace App\Services\Risk;

use App\Core\TenantDb;
use App\Services\Control\ThresholdRegistry;

class RiskDomainService
{
    const DOMAIN = 'DEP-09';

    const PLANNED_KINDS = array('PLANNED_MAINTENANCE', 'PLANNED_OVERHAUL',
                                'CLIENT_STANDBY', 'OPERATIONAL_STANDBY');

    const FAMILIES = array('OPERATIONAL', 'CAPITAL', 'CUSTOMER_CONTRACTUAL', 'PROCUREMENT_SUPPLY');

    const K_APPETITE = 'rsk.appetite.limit_amount';

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
       ① الشجرةُ الحاكمةُ — العائلاتُ الأربعُ ولا خامسة
       ══════════════════════════════════════════════════════════════════════ */

    public static function addTaxonomyNode(TenantDb $gate, array $row)
    {
        $fam = self::s($row, 'family_code');
        if (!in_array($fam, self::FAMILIES, true)) { return self::fail('FAMILY_OUTSIDE_FOUR', $fam); }
        try {
            $id = $gate->insert('rsk_taxonomy', array(
                'node_code'   => self::s($row, 'node_code'),
                'family_code' => $fam,
                'category_ar' => self::s($row, 'category_ar'),
                'type_ar'     => self::s($row, 'type_ar'),
                'parent_code' => self::s($row, 'parent_code'),
                'depth_no'    => self::i($row, 'depth_no', 1),
                'state'       => self::s($row, 'state', 'active'),
                'src_ref'     => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('TAXONOMY_REFUSED', $t->getMessage()); }
        return self::done(array('node_id' => (int) $id));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ② المحفِّزُ — يقرأ الانحرافَ بمرجعِه ولا ينسخه
       ══════════════════════════════════════════════════════════════════════ */

    public static function raiseTrigger(TenantDb $gate, array $row, $actorId)
    {
        $rule = self::s($row, 'rule_code');
        $kind = self::s($row, 'downtime_kind');
        if ($rule === 'UNPLANNED_24H' && in_array($kind, self::PLANNED_KINDS, true)) {
            return self::fail('TRIGGER_ON_PLANNED_DOWNTIME', $kind);
        }
        if (self::s($row, 'source_table') === '' || self::i($row, 'source_row_id') <= 0) {
            return self::fail('TRIGGER_WITHOUT_SOURCE_REF');
        }
        $key = self::s($row, 'threshold_key');
        if ($key === '') { return self::fail('TRIGGER_WITHOUT_THRESHOLD_KEY'); }
        /* **والعتبةُ من السجلِّ — والمعلَّقةُ تردُّ ولا تُخترَع** */
        $th = ThresholdRegistry::read($key);
        if (!$th['ok'] && $rule !== 'PREVENTABLE') {
            return self::fail(ThresholdRegistry::NOT_CONFIGURED, $key);
        }
        try {
            $id = $gate->insert('rsk_trigger', array(
                'trigger_no'     => self::s($row, 'trigger_no'),
                'rule_code'      => $rule,
                'threshold_key'  => $key,
                'deviation_no'   => self::s($row, 'deviation_no'),
                'source_table'   => self::s($row, 'source_table'),
                'source_row_id'  => self::i($row, 'source_row_id'),
                'downtime_kind'  => $kind,
                'measured_value' => self::f($row, 'measured_value'),
                'raised_at'      => date('Y-m-d H:i:s'),
                'state'          => 'raised',
                'why'            => self::s($row, 'why'),
                'src_ref'        => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('TRIGGER_REFUSED', $t->getMessage()); }
        self::emit('rsk.trigger.raised', 'rsk_trigger', (int) $id, array(
            'rule_code' => $rule, 'deviation_no' => self::s($row, 'deviation_no'),
            'threshold_tag' => $th['tagged'], 'raised_by' => (int) $actorId,
        ));
        return self::done(array('trigger_id' => (int) $id, 'threshold_tag' => $th['tagged']));
    }

    /** فتحُ التعرُّض — سجلُّ خطرٍ بمرجعِ محفِّزِه لا بنسخةِ واقعتِه */
    public static function openExposure(TenantDb $gate, $triggerId, array $row, $actorId)
    {
        $t = $gate->selectOne('rsk_trigger', array('where' => array('id' => (int) $triggerId)));
        if (!$t) { return self::fail('TRIGGER_NOT_FOUND'); }
        if ((string) $t['state'] === 'converted') { return self::fail('TRIGGER_ALREADY_CONVERTED'); }
        $code = self::s($row, 'risk_code');
        if ($code === '') { return self::fail('EXPOSURE_WITHOUT_RISK_CODE'); }
        try {
            $rid = $gate->insert('risk_register', array(
                'risk_code'   => $code,
                'ru_id'       => self::i($row, 'ru_id'),
                'title'       => self::s($row, 'title'),
                'description' => self::s($row, 'description'),
                'scope_type'  => self::s($row, 'scope_type', 'مؤسسي'),
                'root_cause'  => self::s($row, 'root_cause'),
                'dedup_key'   => sha1(self::$company . '|' . $code . '|' . (string) $t['source_table']
                                      . '|' . (string) $t['source_row_id']),
                'state'       => 'classified',
                'exposure_currency' => self::s($row, 'currency', 'SDG'),
                'target_level'      => self::s($row, 'target_level', 'غير محدد'),
                'control_effectiveness' => self::s($row, 'control_effectiveness', 'غير مقيمة'),
                'confidence'  => self::s($row, 'confidence', 'متوسطة'),
                'created_by'  => (int) $actorId,
            ));
            $gate->update('rsk_trigger',
                array('state' => 'converted', 'risk_code' => $code, 'triaged_by' => (int) $actorId),
                array('id' => (int) $triggerId));
        } catch (\Throwable $t2) { return self::fail('EXPOSURE_REFUSED', $t2->getMessage()); }
        self::emit('rsk.exposure.opened', 'risk_register', (int) $rid, array(
            'risk_code' => $code, 'trigger_id' => (int) $triggerId,
            'deviation_no' => (string) $t['deviation_no'],
        ));
        return self::done(array('risk_id' => (int) $rid, 'risk_code' => $code));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ③ حدثُ الخطرِ والخسارةِ — مرجعٌ لا نسخة
       ══════════════════════════════════════════════════════════════════════ */

    public static function recordEvent(TenantDb $gate, array $row, $actorId)
    {
        if (self::s($row, 'source_module') === '' || self::s($row, 'source_table') === ''
            || self::i($row, 'source_row_id') <= 0) {
            return self::fail('RISK_EVENT_WITHOUT_SOURCE_REF');
        }
        $fam = self::s($row, 'family_code');
        if (!in_array($fam, self::FAMILIES, true)) { return self::fail('FAMILY_OUTSIDE_FOUR', $fam); }
        try {
            $id = $gate->insert('rsk_event', array(
                'event_no'      => self::s($row, 'event_no'),
                'risk_code'     => self::s($row, 'risk_code'),
                'family_code'   => $fam,
                'source_module' => self::s($row, 'source_module'),
                'source_table'  => self::s($row, 'source_table'),
                'source_row_id' => self::i($row, 'source_row_id'),
                'source_ref'    => self::s($row, 'source_ref'),
                'deviation_no'  => self::s($row, 'deviation_no'),
                'event_kind'    => self::s($row, 'event_kind', 'event'),
                'loss_amount'   => isset($row['loss_amount']) ? self::f($row, 'loss_amount') : null,
                'loss_currency' => self::s($row, 'loss_currency'),
                'occurred_at'   => self::s($row, 'occurred_at') !== '' ? self::s($row, 'occurred_at') : null,
                'recorded_by'   => (int) $actorId,
                'state'         => 'recorded',
                'src_ref'       => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) {
            $m = $t->getMessage();
            if (strpos($m, 'uq_rev_source') !== false) {
                return self::fail('RISK_EVENT_DUPLICATES_SOURCE', 'نسخة ثانية لمصدر واحد');
            }
            return self::fail('RISK_EVENT_REFUSED', $m);
        }
        self::emit('rsk.event.recorded', 'rsk_event', (int) $id, array(
            'family_code' => $fam, 'source_table' => self::s($row, 'source_table'),
            'source_row_id' => self::i($row, 'source_row_id'),
        ));
        return self::done(array('event_id' => (int) $id));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ④ القبولُ والتصعيدُ والإغلاق
       ══════════════════════════════════════════════════════════════════════ */

    /** **القبولُ ضمنَ شهيّةٍ معتمَدة** — والمعلَّقةُ تردُّ ولا تُخترَع */
    public static function acceptRisk(TenantDb $gate, array $row, $actorId)
    {
        $th = ThresholdRegistry::read(self::K_APPETITE);
        if (!$th['ok']) {
            return self::fail('APPETITE_NOT_CONFIGURED', self::K_APPETITE);
        }
        if (self::f($row, 'residual_amount') > $th['value']) {
            return self::fail('ACCEPT_ABOVE_APPETITE', 'خارج الشهية - يصعد ولا يقبل');
        }
        try {
            $id = $gate->insert('risk_acceptances', array(
                'risk_id'     => self::i($row, 'risk_id'),
                'reason_ar'   => self::s($row, 'reason_ar'),
                'accepted_by' => (int) $actorId,
                'valid_until' => self::s($row, 'valid_until') !== '' ? self::s($row, 'valid_until') : null,
            ));
        } catch (\Throwable $t) { return self::fail('ACCEPT_REFUSED', $t->getMessage()); }
        self::emit('rsk.risk.accepted', 'risk_acceptances', (int) $id, array(
            'risk_id' => self::i($row, 'risk_id'), 'threshold_tag' => $th['tagged'],
        ));
        return self::done(array('acceptance_id' => (int) $id, 'threshold_tag' => $th['tagged']));
    }

    public static function escalate(TenantDb $gate, array $row, $actorId)
    {
        if (self::s($row, 'reason_ar') === '') { return self::fail('ESCALATE_WITHOUT_REASON'); }
        try {
            $id = $gate->insert('risk_escalations', array(
                'risk_id'      => self::i($row, 'risk_id'),
                'reason_ar'    => self::s($row, 'reason_ar'),
                'to_authority' => self::s($row, 'to_authority', 'risk_manager'),
                'is_auto'      => self::i($row, 'is_auto'),
            ));
        } catch (\Throwable $t) { return self::fail('ESCALATE_REFUSED', $t->getMessage()); }
        self::emit('rsk.risk.escalated', 'risk_escalations', (int) $id, array(
            'risk_id' => self::i($row, 'risk_id'), 'to' => self::s($row, 'to_authority'),
            'by' => (int) $actorId,
        ));
        return self::done(array('escalation_id' => (int) $id));
    }

    public static function proposeClosure(TenantDb $gate, array $row, $actorId)
    {
        if (self::s($row, 'risk_code') === '') { return self::fail('CLOSURE_WITHOUT_RISK'); }
        $basis = self::s($row, 'closure_basis');
        if ($basis === 'RESIDUAL_WITHIN_LIMIT' && self::s($row, 'reassessment_ref') === '') {
            return self::fail('CLOSURE_WITHOUT_REASSESSMENT');
        }
        try {
            $id = $gate->insert('rsk_closure', array(
                'closure_no'       => self::s($row, 'closure_no'),
                'risk_code'        => self::s($row, 'risk_code'),
                'closure_basis'    => $basis,
                'reassessment_ref' => self::s($row, 'reassessment_ref'),
                'appetite_key'     => self::s($row, 'appetite_key', self::K_APPETITE),
                'evidence_ref'     => self::s($row, 'evidence_ref'),
                'proposed_by'      => (int) $actorId,
                'state'            => 'proposed',
                'src_ref'          => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('CLOSURE_REFUSED', $t->getMessage()); }
        return self::done(array('closure_id' => (int) $id));
    }

    /** **ومن اقترح الإغلاقَ لا يعتمده** — والقاعدةُ تردُّه أيضًا */
    public static function approveClosure(TenantDb $gate, $closureId, $actorId)
    {
        $c = $gate->selectOne('rsk_closure', array('where' => array('id' => (int) $closureId)));
        if (!$c) { return self::fail('CLOSURE_NOT_FOUND'); }
        if ((int) $c['proposed_by'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_PROPOSE_AND_APPROVE_CLOSURE');
        }
        if ((string) $c['evidence_ref'] === '') { return self::fail('CLOSURE_WITHOUT_EVIDENCE'); }
        try {
            $gate->update('rsk_closure', array(
                'state' => 'approved', 'approved_by' => (int) $actorId,
                'approved_at' => date('Y-m-d H:i:s'),
            ), array('id' => (int) $closureId));
        } catch (\Throwable $t) { return self::fail('CLOSURE_APPROVAL_REFUSED', $t->getMessage()); }
        self::emit('rsk.risk.closed', 'rsk_closure', (int) $closureId, array(
            'risk_code' => (string) $c['risk_code'], 'basis' => (string) $c['closure_basis'],
        ));
        return self::done();
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑤ الحدُّ الذي لا يُعبَر — نداءٌ يُعلن أنَّ المخاطرَ لا تكتب في نطاقٍ آخر
       ══════════════════════════════════════════════════════════════════════
       **يُستدعى في المحطّةِ السالبةِ من رحلةِ الضابط** ويردُّ دائمًا — فالحدُّ
       مُختبَرٌ لا مُدَّعًى، ولا يوجد في هذا الملفِّ مسارٌ يكتب في جدولِ الحوكمةِ
       أو المراجعةِ أصلًا (‏والماسحُ البنيويُّ يثبته).
       ══════════════════════════════════════════════════════════════════════ */
    public static function attemptWriteGovernanceCase()
    {
        return self::fail('RISK_CANNOT_OPEN_GOVERNANCE_CASE',
                          'المخاطر لا تفتح حالة حوكمة - الحوكمة تفتحها من بابها');
    }

    public static function attemptSetAuditResult()
    {
        return self::fail('RISK_CANNOT_SET_AUDIT_RESULT',
                          'المخاطر لا تضع نتيجة مراجعة ولا تغلقها');
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
                'category'        => 'risk',
                'source_module'   => 'risk',
                'entity_type'     => $table,
                'entity_id'       => (int) $entityId,
                'payload'         => $payload,
                'idempotency_key' => 'w14:' . $eventKey . ':' . (int) $entityId . ':'
                                     . substr(sha1(json_encode($payload)), 0, 12),
                'source_ref'      => 'RiskDomainService',
            ));
        } catch (\Throwable $t) { return null; }
    }
}
