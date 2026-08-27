<?php
/**
 * DeviationClassifier — الانحرافُ التشغيليُّ وتصنيفُه بقاعدةٍ مكتوبة (RPR-W14)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **حبّةُ الرحلةِ ومالكُها تشغيليّ**: الانحرافُ يقع عند مالكِه، ويبقى عنده.
 *   `chk_ctd_owner_not_control` في القاعدةِ يردُّ أن يملكه نطاقُ رقابة —
 *   وهذا الملفُّ **لا يكتب في جدولِ حوكمةٍ ولا مخاطرَ ولا مراجعة**، إنّما
 *   يُصنِّف ويقول **ما الذي صار مستحقًّا** فيقرؤه كلُّ نطاقٍ بمرجعِه.
 *
 * ◆ **والتمييزُ الثلاثيُّ بقاعدةٍ مكتوبةٍ لا باجتهاد** (§27): `classify` تشترط
 *   قاعدةً **نافذةً** في `ctl_classification_rule` بشروطِها الثلاثةِ مكتوبة،
 *   وتردُّ `CLASSIFY_WITHOUT_WRITTEN_RULE` بدونها.
 *
 * ◆ **والعطلُ لا يُنشئ خطرًا — يُنشئ محفِّزًا** (‏قرارُ المالك ②): والمخطَّطُ
 *   (`PLANNED_MAINTENANCE` · `PLANNED_OVERHAUL` · `CLIENT_STANDBY` ·
 *   `OPERATIONAL_STANDBY`) **مستثنًى من محفِّزِ الأربعِ والعشرين ساعة** ويُسجَّل
 *   بنوعِه — «ولا نريد ملءَ سجلِّ المخاطرِ بصيانةٍ مخطَّطةٍ طبيعيّة».
 *
 * ◆ **وحالةُ الحوكمةِ لا تُفتح لكلِّ توقّف**: `GOVERNANCE_BREACH` تشترط أساسًا
 *   من الثمانيةِ التي سمّاها المالك — تجاهلُ إجراءٍ إلزاميٍّ · عدمُ تصعيدٍ
 *   مطلوبٍ · تجاوزُ صلاحيةٍ · تلاعبٌ · إخفاءٌ · تزويرٌ · خرقُ سياسةٍ · كسرُ ضابط.
 *
 * ⛔ **ولا عتبةٌ رقميّةٌ في هذا الملفّ** — كلُّها عبرَ `ThresholdRegistry`.
 * ═══════════════════════════════════════════════════════════════════════════
 */

namespace App\Services\Control;

use App\Core\TenantDb;

class DeviationClassifier
{
    const CONTROL_DEPTS = array('DEP-08', 'DEP-09', 'IAF');

    /** أنواعُ التوقّفِ المخطَّطةُ — مستثناةٌ من محفِّزِ الأربعِ والعشرين ساعة */
    const PLANNED_KINDS = array('PLANNED_MAINTENANCE', 'PLANNED_OVERHAUL',
                                'CLIENT_STANDBY', 'OPERATIONAL_STANDBY');

    /** أساسُ فتحِ حالةِ الحوكمةِ — الثمانيةُ التي سمّاها المالكُ ولا تاسعَ */
    const BREACH_BASES = array('MANDATORY_STEP_IGNORED', 'NO_ESCALATION', 'AUTHORITY_EXCEEDED',
                               'MANIPULATION', 'CONCEALMENT', 'FORGERY', 'POLICY_BREACH', 'CONTROL_BROKEN');

    const K_DOWNTIME  = 'rsk.trigger.unplanned_downtime_hours';
    const K_SIMPLE    = 'rsk.trigger.simple_issue_days';
    const K_RECUR     = 'rsk.trigger.recurrence_count';

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
       ① القاعدةُ المكتوبةُ — تُكتب قبل أن يُصنَّف انحراف
       ══════════════════════════════════════════════════════════════════════ */

    public static function writeRule(TenantDb $gate, array $row, $actorId)
    {
        $code = self::s($row, 'rule_code');
        if ($code === '') { return self::fail('RULE_CODE_REQUIRED'); }
        foreach (array('exposure_test', 'breach_test', 'retain_test') as $k) {
            if (self::s($row, $k) === '') { return self::fail('RULE_TEST_MISSING', $k); }
        }
        try {
            $id = $gate->insert('ctl_classification_rule', array(
                'rule_code'      => $code,
                'title_ar'       => self::s($row, 'title_ar'),
                'deviation_kind' => self::s($row, 'deviation_kind'),
                'exposure_test'  => self::s($row, 'exposure_test'),
                'breach_test'    => self::s($row, 'breach_test'),
                'retain_test'    => self::s($row, 'retain_test'),
                'appetite_key'   => self::s($row, 'appetite_key'),
                'control_ref'    => self::s($row, 'control_ref'),
                'policy_ref'     => self::s($row, 'policy_ref'),
                'state'          => 'draft',
                'authored_by'    => (int) $actorId,
                'src_ref'        => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('RULE_REFUSED', $t->getMessage()); }
        return self::done(array('rule_id' => (int) $id));
    }

    /** **ومن كتب القاعدةَ لا يعتمدها** — `chk_ctlr_sod` يردُّه في القاعدة */
    public static function activateRule(TenantDb $gate, $ruleId, $actorId, $effectiveFrom)
    {
        $r = $gate->selectOne('ctl_classification_rule', array('where' => array('id' => (int) $ruleId)));
        if (!$r) { return self::fail('RULE_NOT_FOUND'); }
        if ((int) $r['authored_by'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_AUTHOR_AND_APPROVE_RULE', 'من كتب القاعدة لا يعتمدها');
        }
        try {
            $gate->update('ctl_classification_rule',
                array('state' => 'active', 'approved_by' => (int) $actorId,
                      'effective_from' => (string) $effectiveFrom),
                array('id' => (int) $ruleId));
        } catch (\Throwable $t) { return self::fail('RULE_ACTIVATION_REFUSED', $t->getMessage()); }
        return self::done();
    }

    /* ══════════════════════════════════════════════════════════════════════
       ② تسجيلُ الانحرافِ — عند مالكِه التشغيليِّ بمرجعِ مصدرِه
       ══════════════════════════════════════════════════════════════════════ */

    public static function register(TenantDb $gate, array $row, $actorId)
    {
        $owner = self::s($row, 'owner_dept');
        if ($owner === '') { return self::fail('DEVIATION_WITHOUT_OWNER'); }
        if (in_array($owner, self::CONTROL_DEPTS, true)) {
            return self::fail('DEVIATION_OWNED_BY_CONTROL', $owner);
        }
        if (self::s($row, 'source_table') === '' || self::i($row, 'source_row_id') <= 0) {
            return self::fail('DEVIATION_WITHOUT_SOURCE_REF');
        }
        try {
            $id = $gate->insert('ctl_deviation', array(
                'deviation_no'   => self::s($row, 'deviation_no'),
                'owner_dept'     => $owner,
                'source_module'  => self::s($row, 'source_module'),
                'source_table'   => self::s($row, 'source_table'),
                'source_row_id'  => self::i($row, 'source_row_id'),
                'deviation_kind' => self::s($row, 'deviation_kind'),
                'downtime_kind'  => self::s($row, 'downtime_kind'),
                'occurred_at'    => self::s($row, 'occurred_at') !== '' ? self::s($row, 'occurred_at') : null,
                'duration_hours' => self::f($row, 'duration_hours'),
                'recurrence_no'  => self::i($row, 'recurrence_no', 1),
                'preventable'    => self::i($row, 'preventable'),
                'classification' => 'PENDING',
                'state'          => 'registered',
                'why'            => self::s($row, 'why'),
                'src_ref'        => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('DEVIATION_REFUSED', $t->getMessage()); }
        self::emit('ctl.deviation.registered', 'ctl_deviation', (int) $id, array(
            'owner_dept' => $owner, 'source_table' => self::s($row, 'source_table'),
            'source_row_id' => self::i($row, 'source_row_id'),
            'registered_by' => (int) $actorId,
        ));
        return self::done(array('deviation_id' => (int) $id));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ③ التصنيفُ — انحرافٌ · تعرُّضٌ · خرقٌ — بقاعدةٍ نافذةٍ وعتبةٍ من السجلّ
       ══════════════════════════════════════════════════════════════════════
       **ولا يكتب هذا الملفُّ حرفًا في سجلِّ المخاطرِ ولا في حالةِ الحوكمة.**
       يقول: «هذا مستحقٌّ لتعرُّضٍ» و«هذا مستحقٌّ لخرق» — ويفتحهما كلُّ نطاقٍ
       من بابِ خدمتِه هو. فالعلاقةُ **مرجعٌ لا مشاركة**.
       ══════════════════════════════════════════════════════════════════════ */

    public static function classify(TenantDb $gate, $deviationId, array $input, $actorId)
    {
        $d = $gate->selectOne('ctl_deviation', array('where' => array('id' => (int) $deviationId)));
        if (!$d) { return self::fail('DEVIATION_NOT_FOUND'); }
        if ((int) $actorId <= 0) { return self::fail('CLASSIFY_WITHOUT_ACTOR'); }

        $ruleCode = self::s($input, 'rule_code');
        if ($ruleCode === '') { return self::fail('CLASSIFY_WITHOUT_WRITTEN_RULE', 'لا قاعدة'); }
        $rule = $gate->selectOne('ctl_classification_rule',
            array('where' => array('rule_code' => $ruleCode, 'state' => 'active')));
        if (!$rule) { return self::fail('CLASSIFY_WITHOUT_WRITTEN_RULE', $ruleCode); }

        /* ⓐ **شقُّ التعرُّض** — محفِّزٌ من قواعدِ المالكِ الأربعِ والعتبةُ من السجلّ */
        $triggers = array(); $pendingKeys = array();
        $kind = (string) $d['downtime_kind'];
        $planned = in_array($kind, self::PLANNED_KINDS, true);

        $t1 = ThresholdRegistry::read(self::K_DOWNTIME);
        if (!$t1['ok']) { $pendingKeys[] = self::K_DOWNTIME; }
        elseif (!$planned && (float) $d['duration_hours'] > $t1['value']) {
            $triggers[] = array('rule' => 'UNPLANNED_24H', 'key' => self::K_DOWNTIME,
                                'value' => (float) $d['duration_hours'], 'tag' => $t1['tagged']);
        }

        $t2 = ThresholdRegistry::read(self::K_SIMPLE);
        if (!$t2['ok']) { $pendingKeys[] = self::K_SIMPLE; }
        elseif (self::s($d, 'deviation_kind') === 'SIMPLE_ISSUE'
                && (float) $d['duration_hours'] > ($t2['value'] * self::hoursPerDay())) {
            $triggers[] = array('rule' => 'SIMPLE_ISSUE_3D', 'key' => self::K_SIMPLE,
                                'value' => (float) $d['duration_hours'], 'tag' => $t2['tagged']);
        }

        $t3 = ThresholdRegistry::read(self::K_RECUR);
        if (!$t3['ok']) { $pendingKeys[] = self::K_RECUR; }
        elseif ((int) $d['recurrence_no'] > $t3['value']) {
            $triggers[] = array('rule' => 'RECURRENCE_3X', 'key' => self::K_RECUR,
                                'value' => (int) $d['recurrence_no'], 'tag' => $t3['tagged']);
        }

        if ((int) $d['preventable'] === 1) {
            $triggers[] = array('rule' => 'PREVENTABLE', 'key' => '', 'value' => 1, 'tag' => 'OWNER_APPROVED');
        }

        /* ⓑ **شقُّ الخرق** — أساسٌ من الثمانيةِ وحدَها، ولا يُفتح لكلِّ توقّف */
        $basis = self::s($input, 'breach_basis');
        $breach = ($basis !== '' && in_array($basis, self::BREACH_BASES, true));
        if ($basis !== '' && !$breach) { return self::fail('BREACH_BASIS_OUTSIDE_EIGHT', $basis); }

        /* ⓒ **الحكم** */
        $exposure = count($triggers) > 0;
        if ($exposure && $breach)       { $cls = 'EXPOSURE_AND_BREACH'; }
        elseif ($exposure)              { $cls = 'RISK_EXPOSURE'; }
        elseif ($breach)                { $cls = 'GOVERNANCE_BREACH'; }
        else                            { $cls = 'DEVIATION_ONLY'; }

        try {
            $gate->update('ctl_deviation', array(
                'classification' => $cls,
                'rule_code'      => $ruleCode,
                'classified_by'  => (int) $actorId,
                'classified_at'  => date('Y-m-d H:i:s'),
                'state'          => ($cls === 'DEVIATION_ONLY') ? 'retained' : 'classified',
            ), array('id' => (int) $deviationId));
        } catch (\Throwable $t) { return self::fail('CLASSIFY_REFUSED', $t->getMessage()); }

        self::emit('ctl.deviation.classified', 'ctl_deviation', (int) $deviationId, array(
            'classification' => $cls, 'rule_code' => $ruleCode,
            'triggers' => count($triggers), 'breach_basis' => $basis,
        ));

        return self::done(array(
            'classification'   => $cls,
            'triggers'         => $triggers,
            'breach_basis'     => $basis,
            'opens_exposure'   => $exposure,
            'opens_breach'     => $breach,
            'pending_thresholds' => $pendingKeys,
            'deviation_no'     => (string) $d['deviation_no'],
        ));
    }

    /** **ومالكُ الانحرافِ لا يحوّله بنفسِه** — الإحالةُ تُسجَّل بمرجعِ من فتحها */
    public static function markReferred(TenantDb $gate, $deviationId, $riskRef, $govRef, $actorId)
    {
        $d = $gate->selectOne('ctl_deviation', array('where' => array('id' => (int) $deviationId)));
        if (!$d) { return self::fail('DEVIATION_NOT_FOUND'); }
        if ((string) $d['classification'] === 'DEVIATION_ONLY') {
            return self::fail('RETAINED_DEVIATION_CANNOT_REFER', 'انحراف يبقى عند مالكه');
        }
        if ((string) $d['classification'] === 'PENDING') {
            return self::fail('REFER_BEFORE_CLASSIFY');
        }
        if ((int) $actorId === (int) $d['classified_by'] && (string) $riskRef !== '' && (string) $govRef !== '') {
            return self::fail('OWNER_CANNOT_SELF_ESCALATE', 'يد واحدة تصنف وتحيل الوجهتين');
        }
        try {
            $gate->update('ctl_deviation',
                array('risk_ref' => (string) $riskRef, 'governance_ref' => (string) $govRef,
                      'state' => 'referred'),
                array('id' => (int) $deviationId));
        } catch (\Throwable $t) { return self::fail('REFER_REFUSED', $t->getMessage()); }
        return self::done();
    }

    /**
     * ساعاتُ اليومِ **ثابتٌ تقويميٌّ لا عتبةُ أعمال** — فلا تُقرأ من سجلِّ
     * العتبات ولا تُنتظر اعتمادُ مالك. والعتبةُ هي **عددُ الأيّام** المقروءُ
     * من `rsk.trigger.simple_issue_days`، وهذا تحويلُ وحدةٍ لا حدُّ قرار.
     */
    private static function hoursPerDay() { return 24.0; }

    private static function emit($eventKey, $table, $entityId, array $payload)
    {
        $conn = self::$eventConn;
        if (!($conn instanceof \mysqli)) { return null; }
        if (!class_exists('\App\Core\EventPublisher')) { return null; }
        try {
            return \App\Core\EventPublisher::publishFact($conn, array(
                'company_id'      => self::$company,
                'event_key'       => $eventKey,
                'category'        => 'control',
                'source_module'   => 'control',
                'entity_type'     => $table,
                'entity_id'       => (int) $entityId,
                'payload'         => $payload,
                'idempotency_key' => 'w14:' . $eventKey . ':' . (int) $entityId . ':'
                                     . substr(sha1(json_encode($payload)), 0, 12),
                'source_ref'      => 'DeviationClassifier',
            ));
        } catch (\Throwable $t) { return null; }
    }
}
