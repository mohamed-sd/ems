<?php
/**
 * app/Services/Maintenance/MaintenanceCycleService.php — دورةُ العطلِ والعودة (RPR-W07)
 * ═══════════════════════════════════════════════════════════════════════════
 * **دورةُ العطل**: بلاغٌ يصل الصيانةَ محالًا ← تشخيصٌ يثبّت عقدةَ الشجرة ←
 * **تصنيفٌ بأربعةِ محاورَ منفصلة** ← أمرُ عملٍ معتمَد ← إيقافٌ ومنعُ تشغيلٍ إن
 * كان حرِجًا للسلامة ← عمالةٌ وبنودٌ وصرفُ قطعٍ وإحالةٌ خارجية ← **شهادةُ إعادةِ
 * خدمةٍ معتمَدة** ← عودةُ المعدّةِ للخدمة.
 *
 * ◆ **`DEC-OPEN-12` هو القاعدةُ الحاكمةُ هنا — ونصُّه بنيةٌ لا شعار:**
 *   · **أربعةُ محاورَ تُفصل ولا تُدمَج**: `failure_kind` · `safety_severity` ·
 *     `ops_impact` · `equipments.w7_readiness_state`. و`classifySafety`
 *     **لا تقرأ `downtime_hours` أصلًا**، و`computeOpsImpact` **لا تعيد
 *     `safety_severity` أبدًا** — «مدّةُ التوقّفِ لا تغيّر تصنيفَ السلامةِ
 *     تلقائيًّا» (‏DEC-OPEN-12 ②).
 *   · **ولا قائمةَ صلبةً واحدةً لكلِّ المعدّات** (‏③): الأنظمةُ الحرجةُ تُقرأ من
 *     `mnt_safety_rule` بمفتاحِ (نوعُ المعدّة × النظام)، والنوعُ الخاصُّ يغلب
 *     العامَّ. **ولا مصفوفةَ أنظمةٍ في هذا الملفّ.**
 *   · **وقائمةُ المكوِّناتِ ليست الحكمَ وحدَها** (‏④): `escalateSeverity` تسمح
 *     للفنّيِّ أو مهندسِ الصيانةِ أو مسؤولِ السلامةِ برفعِ التصنيفِ بسببٍ مكتوب،
 *     **وتمنع خفضَه** — «حتّى تتمَّ مراجعتُه» تعني أنَّ التصعيدَ لا يُنقض بضغطةِ
 *     زرٍّ من الشاشةِ نفسِها.
 *   · **والبسيطُ لا شهادةَ له** (‏①): `issueCert` تردُّ `CERT_NOT_REQUIRED` على
 *     `minor` — فإصدارُ شهادةٍ لا يوجبها القرارُ **يُفرِغ الشهادةَ من معناها**
 *     ويجعل «التكرارَ خلالَ الصلاحية» في `MNT-15` يقيس نافذةً مُخترعة.
 *
 * ◆ **«الشهادةُ وحدَها تعيد المعدّةَ للخدمة»** (`MNT-14` نصًّا) — و`returnToService`
 *   **لا تُستدعى إلّا من `approveCert`**، ولا تكتب في `equipments` بلا شهادةٍ
 *   معتمَدة. والحالةُ الفنيّةُ **ملكُ الأسطول** فالكتابةُ فيها أثرُ الشهادةِ
 *   لا فعلُ الصيانة.
 *
 * ◆ **وفصلُ الواجباتِ يُنفَّذ لا يُعلَن**: مَن أنشأ الشهادةَ لا يعتمدها، ومَن
 *   نفَّذ الإصلاحَ لا يشهد بنفسِه على الحرِجِ للسلامة (`signer_kind` يجب أن يكون
 *   `technical_authority`) — والردُّ برمزٍ لا بتجاهُل.
 *
 * ⛔ **ولا عتبةٌ رقميّةٌ في هذا الملفّ**: صلاحيةُ الشهادةِ وحدودُ الأثرِ
 *   التشغيليِّ ونافذةُ التكرارِ كلُّها من `repair01_w7_thresholds`. وإن غاب
 *   السجلُّ **تُردُّ العمليّةُ ولا تُخمَّن قيمة**.
 *
 * ◆ **وكلُّ وصولٍ إلى جدولِ مستأجِرٍ عبرَ `TenantDb`** — و`company_id`
 *   لا يُمرَّر من المُستدعي (‏FR-SEC-006 · GAP-29).
 */

namespace App\Services\Maintenance;

use App\Core\TenantDb;

class MaintenanceCycleService
{
    /** محاورُ التصنيفِ الأربعةُ — تُفصل ولا تُدمَج (DEC-OPEN-12 ①) */
    const SEVERITY_ORDER = array('minor' => 1, 'major' => 2, 'safety_critical' => 3);

    /** حالاتُ الشهادةِ بترتيبِ الدورة — والترتيبُ هو الذي يمنع القفز */
    const CERT_FLOW = array('draft', 'submitted', 'approved');

    /** @var \mysqli|null اتصالُ نشرِ الحقائقِ المحايدة — يُحقن صراحةً (ADR-15) */
    private static $eventConn = null;
    /** @var \mysqli|null اتصالُ سجلِّ العتبات — دفترُ حملةٍ بلا كيانٍ قانونيّ */
    private static $thConn = null;
    /** @var array|null العتباتُ المقروءةُ من السجلِّ — تُقرأ مرّةً في العمليّة */
    private static $th = null;

    public static function setEventConnection(\mysqli $conn) { self::$eventConn = $conn; }
    public static function setThresholdConnection(\mysqli $conn) { self::$thConn = $conn; self::$th = null; }

    /**
     * عتبةٌ من السجلِّ — **ولا قيمةٌ افتراضيّةٌ في الشيفرة**.
     * @return float|null `null` تعني «لا سجلَّ» ⇒ العمليّةُ تُردُّ ولا تُخمَّن.
     */
    public static function threshold($key)
    {
        if (self::$th === null) {
            self::$th = array();
            if (self::$thConn instanceof \mysqli) {
                $r = @self::$thConn->query("SELECT threshold_key, value_num FROM repair01_w7_thresholds");
                while ($r && $x = $r->fetch_assoc()) { self::$th[$x['threshold_key']] = (float) $x['value_num']; }
            }
        }
        return array_key_exists($key, self::$th) ? self::$th[$key] : null;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ① التصنيف — أربعةُ محاورَ منفصلة
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * المحورُ ②: خطورةُ السلامة — **من السجلِّ بحسبِ نوعِ المعدّة** لا من قائمةٍ صلبة.
     * ⛔ **ولا تقرأ ساعاتِ التوقّفِ إطلاقًا** (DEC-OPEN-12 ②).
     * @return array [severity, rule_ref, requires_cert, requires_lockout, approver_kind, matched]
     */
    public static function classifySafety(TenantDb $gate, $equipmentId, $systemKey)
    {
        $out = array('severity' => 'minor', 'rule_ref' => 'W7_SAFETY_NO_SYSTEM_MATCH',
                     'requires_cert' => 0, 'requires_lockout' => 0, 'approver_kind' => 'technician',
                     'matched' => '');
        $systemKey = trim((string) $systemKey);
        if ($systemKey === '') { return $out; }

        $type = '';
        try {
            $eq = $gate->selectOne('equipments', array('columns' => array('type'), 'where' => array('id' => (int) $equipmentId)));
            if ($eq && isset($eq['type'])) { $type = (string) $eq['type']; }
        } catch (\Throwable $t) { $type = ''; }

        /* النوعُ الخاصُّ يغلب العامَّ — و«كلُّ الأنواع» هو الصفُّ بنوعٍ فارغ */
        $rows = array();
        try {
            $rows = $gate->select('mnt_safety_rule', array(
                'where' => array('system_key' => $systemKey, 'active' => 1), 'limit' => 50));
        } catch (\Throwable $t) { $rows = array(); }
        $pick = null;
        foreach ($rows as $r) {
            if ($type !== '' && (string) $r['equipment_type'] === $type) { $pick = $r; break; }
            if ((string) $r['equipment_type'] === '' && $pick === null) { $pick = $r; }
        }
        if (!$pick) { return $out; }
        return array(
            'severity'         => (string) $pick['default_severity'],
            'rule_ref'         => (string) $pick['rule_ref'],
            'requires_cert'    => (int) $pick['requires_cert'],
            'requires_lockout' => (int) $pick['requires_lockout'],
            'approver_kind'    => (string) $pick['approver_kind'],
            'matched'          => ((string) $pick['equipment_type'] === '' ? 'ALL_TYPES' : (string) $pick['equipment_type']),
        );
    }

    /**
     * المحورُ ③: الأثرُ التشغيليّ — **يُحسب من ساعاتِ التوقّف** وحدودُه من السجلّ.
     * ⛔ **ولا يعيد `safety_severity` أبدًا** — محورٌ ثانٍ مستقلٌّ بنصِّ القرار.
     * @return array [impact, rule]
     */
    public static function computeOpsImpact($downtimeHours)
    {
        $hi   = self::threshold('W7_OPS_IMPACT_HIGH_HOURS');
        $crit = self::threshold('W7_OPS_IMPACT_CRITICAL_HOURS');
        if ($hi === null || $crit === null) {
            return array('impact' => null, 'rule' => 'W7_OPS_IMPACT_NO_THRESHOLD');
        }
        $h = (float) $downtimeHours;
        if ($h >= $crit) { return array('impact' => 'critical', 'rule' => 'W7_OPS_IMPACT_BY_DOWNTIME'); }
        if ($h >= $hi)   { return array('impact' => 'high',     'rule' => 'W7_OPS_IMPACT_BY_DOWNTIME'); }
        if ($h > 0)      { return array('impact' => 'medium',   'rule' => 'W7_OPS_IMPACT_BY_DOWNTIME'); }
        return array('impact' => 'low', 'rule' => 'W7_OPS_IMPACT_BY_DOWNTIME');
    }

    /**
     * تصعيدُ التصنيفِ بحكمِ فنّيٍّ (DEC-OPEN-12 ④) — **رفعٌ فقط، والخفضُ يُردّ**.
     */
    public static function escalateSeverity(TenantDb $gate, $orderId, $toSeverity, $actorId, $reason)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $reason = trim((string) $reason);
        $actorId = (int) $actorId;
        if ($actorId <= 0)  { $out['code'] = 422; $out['reason'] = 'لا تصعيد بلا فاعل معروف'; return $out; }
        if ($reason === '') { $out['code'] = 422; $out['reason'] = 'لا تصعيد بلا سبب مكتوب'; return $out; }
        if (!isset(self::SEVERITY_ORDER[$toSeverity])) { $out['code'] = 422; $out['reason'] = 'تصنيف خارج السلم'; return $out; }
        $o = $gate->selectOne('mnt_order', array('where' => array('id' => (int) $orderId)));
        if (!$o) { $out['code'] = 404; $out['reason'] = 'أمر العمل غير موجود'; return $out; }
        $cur = (string) $o['safety_severity'];
        $curN = isset(self::SEVERITY_ORDER[$cur]) ? self::SEVERITY_ORDER[$cur] : 0;
        if (self::SEVERITY_ORDER[$toSeverity] <= $curN) {
            $out['code'] = 409; $out['reason'] = 'SEVERITY_DOWNGRADE_FORBIDDEN'; return $out;
        }
        $gate->update('mnt_order', array(
            'safety_severity'          => $toSeverity,
            'safety_rule_ref'          => 'W7_SAFETY_JUDGEMENT_OVERRIDE',
            'severity_override_by'     => $actorId,
            'severity_override_reason' => $reason,
            'w7_state_rule'            => 'W7_SEVERITY_ESCALATED',
        ), array('id' => (int) $orderId));
        $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'صعد التصنيف';
        self::emit($gate, 'mnt.order.severity_escalated', 'mnt_order', (int) $orderId,
                   array('to' => $toSeverity, 'by' => $actorId), 'sev:' . (int) $orderId . ':' . $toSeverity);
        return $out;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ② استقبالُ البلاغِ وفتحُ الأمر
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * استقبالُ البلاغِ المُحال — «الصيانةُ لا تنشئ بلاغًا موازيًا» (`MNT-04`).
     * عطالةٌ بمفتاحِ (كيان × كود البلاغ).
     */
    public static function receiveBreakdown(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'breakdown_id' => 0, 'created' => false);
        $code = trim((string) (isset($d['code']) ? $d['code'] : ''));
        $eq   = (int) (isset($d['equipment_id']) ? $d['equipment_id'] : 0);
        if ($code === '') { $out['code'] = 422; $out['reason'] = 'لا بلاغ بلا كود يعرفه'; return $out; }
        if ($eq <= 0)     { $out['code'] = 422; $out['reason'] = 'لا بلاغ بلا معدة'; return $out; }

        $ex = $gate->selectOne('mnt_breakdown', array('where' => array('code' => $code)));
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['breakdown_id'] = (int) $ex['id'];
            $out['reason'] = 'البلاغ قائم سلفا. عطالة بالمفتاح'; return $out;
        }
        $row = array(
            'code'            => $code,
            'equipment_id'    => $eq,
            'project_id'      => (isset($d['project_id']) && (int) $d['project_id'] > 0) ? (int) $d['project_id'] : null,
            'reported_by'     => (isset($d['reported_by']) && (int) $d['reported_by'] > 0) ? (int) $d['reported_by'] : null,
            'reporter_dept'   => isset($d['reporter_dept']) ? (string) $d['reporter_dept'] : '',
            'report_datetime' => isset($d['report_datetime']) ? (string) $d['report_datetime'] : self::now(),
            'severity'        => isset($d['severity']) ? (string) $d['severity'] : '',
            'is_stopped'      => !empty($d['is_stopped']) ? 1 : 0,
            'description'     => isset($d['description']) ? (string) $d['description'] : '',
            'state'           => 'received',
        );
        $id = 0;
        try { $id = (int) $gate->insert('mnt_breakdown', $row); } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر استقبال البلاغ'; return $out; }
        $out['ok'] = true; $out['code'] = 201; $out['breakdown_id'] = $id; $out['created'] = true;
        self::emit($gate, 'mnt.breakdown.received', 'mnt_breakdown', $id,
                   array('code' => $code, 'equipment_id' => $eq, 'is_stopped' => $row['is_stopped']),
                   'brk:' . $id);
        return $out;
    }

    /**
     * فتحُ أمرِ عملٍ بأربعةِ محاورَ مقروءةٍ من قواعدِها — عطالةٌ بمفتاحِ (كيان × كود).
     * والحرِجُ للسلامةِ **يُقفَل تشغيلُه فورًا** ولا يُترك للاجتهاد.
     */
    public static function openOrder(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'order_id' => 0,
                     'severity' => '', 'lockout' => 'none', 'created' => false);
        $code = trim((string) (isset($d['code']) ? $d['code'] : ''));
        $eq   = (int) (isset($d['equipment_id']) ? $d['equipment_id'] : 0);
        $actor = (int) (isset($d['created_by']) ? $d['created_by'] : 0);
        if ($code === '') { $out['code'] = 422; $out['reason'] = 'لا أمر بلا كود يعرفه'; return $out; }
        if ($eq <= 0)     { $out['code'] = 422; $out['reason'] = 'لا أمر بلا معدة'; return $out; }
        if ($actor <= 0)  { $out['code'] = 422; $out['reason'] = 'لا أمر بلا منشئ معروف'; return $out; }

        $ex = $gate->selectOne('mnt_order', array('where' => array('code' => $code)));
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['order_id'] = (int) $ex['id'];
            $out['severity'] = (string) $ex['safety_severity']; $out['lockout'] = (string) $ex['lockout_state'];
            $out['reason'] = 'الأمر قائم سلفا. عطالة بالمفتاح'; return $out;
        }
        $sys = isset($d['safety_system_key']) ? (string) $d['safety_system_key'] : '';
        $cls = self::classifySafety($gate, $eq, $sys);
        $imp = self::computeOpsImpact(isset($d['downtime_hours']) ? $d['downtime_hours'] : 0);
        if ($imp['impact'] === null) {
            $out['code'] = 503; $out['reason'] = 'لا عتبة اثر تشغيلي في السجل'; return $out;
        }
        $row = array(
            'code'              => $code,
            'breakdown_id'      => (isset($d['breakdown_id']) && (int) $d['breakdown_id'] > 0) ? (int) $d['breakdown_id'] : null,
            'plan_id'           => (isset($d['plan_id']) && (int) $d['plan_id'] > 0) ? (int) $d['plan_id'] : null,
            'inspection_id'     => (isset($d['inspection_id']) && (int) $d['inspection_id'] > 0) ? (int) $d['inspection_id'] : null,
            'equipment_id'      => $eq,
            'project_id'        => (isset($d['project_id']) && (int) $d['project_id'] > 0) ? (int) $d['project_id'] : null,
            'source'            => isset($d['source']) ? (string) $d['source'] : 'breakdown',
            'maint_type'        => isset($d['maint_type']) ? (string) $d['maint_type'] : 'corrective',
            'failure_kind'      => isset($d['failure_kind']) ? (string) $d['failure_kind'] : '',
            'safety_severity'   => $cls['severity'],
            'safety_rule_ref'   => $cls['rule_ref'],
            'safety_system_key' => $sys,
            'ops_impact'        => $imp['impact'],
            'ops_impact_rule'   => $imp['rule'],
            'downtime_hours'    => (float) (isset($d['downtime_hours']) ? $d['downtime_hours'] : 0),
            'diagnosis'         => isset($d['diagnosis']) ? (string) $d['diagnosis'] : '',
            'state'             => 'open',
            'w7_state_rule'     => 'W7_ORDER_OPENED_CLASSIFIED',
            'created_by'        => $actor,
        );
        $id = 0;
        try { $id = (int) $gate->insert('mnt_order', $row); } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر فتح أمر العمل'; return $out; }

        $out['ok'] = true; $out['code'] = 201; $out['order_id'] = $id;
        $out['severity'] = $cls['severity']; $out['created'] = true;
        self::emit($gate, 'mnt.order.opened', 'mnt_order', $id,
                   array('code' => $code, 'equipment_id' => $eq, 'safety_severity' => $cls['severity'],
                         'ops_impact' => $imp['impact']), 'ord:' . $id);

        /* الحرِجُ للسلامةِ: إيقافٌ ومنعُ تشغيلٍ قبلَ الإصلاح — لا بعده */
        if ($cls['requires_lockout']) {
            $lo = self::lockout($gate, $id, $actor);
            $out['lockout'] = $lo['ok'] ? 'locked_out' : 'none';
        }
        return $out;
    }

    /** إيقافٌ ومنعُ تشغيل — والأصلُ يصير «محظور تشغيله» في كرتِه */
    public static function lockout(TenantDb $gate, $orderId, $actorId)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $o = $gate->selectOne('mnt_order', array('where' => array('id' => (int) $orderId)));
        if (!$o) { $out['code'] = 404; $out['reason'] = 'أمر العمل غير موجود'; return $out; }
        $gate->update('mnt_order', array(
            'lockout_state' => 'locked_out', 'lockout_at' => self::now(),
            'lockout_by' => (int) $actorId, 'w7_state_rule' => 'W7_LOCKOUT_SAFETY_CRITICAL',
        ), array('id' => (int) $orderId));
        $gate->update('equipments', array(
            'w7_readiness_state' => 'prohibited',
            'w7_readiness_rule'  => 'W7_LOCKOUT_BY_ORDER',
        ), array('id' => (int) $o['equipment_id']));
        $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'اوقف التشغيل ومنع';
        self::emit($gate, 'mnt.order.locked_out', 'mnt_order', (int) $orderId,
                   array('equipment_id' => (int) $o['equipment_id']), 'lock:' . (int) $orderId);
        return $out;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ③ صرفُ القطعِ والإحالةُ الخارجيةُ والعنايةُ اليومية
       ═══════════════════════════════════════════════════════════════════════ */

    /** طلبُ صرفِ قطعٍ — ⛔ «ولا صرفَ لأمرٍ مقفل» (`MNT-10` نصًّا) */
    public static function requestParts(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'request_id' => 0, 'created' => false);
        $no  = trim((string) (isset($d['req_no']) ? $d['req_no'] : ''));
        $ord = (int) (isset($d['order_id']) ? $d['order_id'] : 0);
        if ($no === '') { $out['code'] = 422; $out['reason'] = 'لا طلب بلا رقم يعرفه'; return $out; }
        $o = $gate->selectOne('mnt_order', array('where' => array('id' => $ord)));
        if (!$o) { $out['code'] = 404; $out['reason'] = 'أمر العمل غير موجود'; return $out; }
        if (in_array((string) $o['state'], array('closed', 'cancelled'), true)) {
            $out['code'] = 409; $out['reason'] = 'ORDER_CLOSED_NO_ISSUE'; return $out;
        }
        $ex = $gate->selectOne('mnt_part_request', array('where' => array('req_no' => $no)));
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['request_id'] = (int) $ex['id'];
            $out['reason'] = 'الطلب قائم سلفا. عطالة بالمفتاح'; return $out;
        }
        $lines = 0;
        try { $lines = (int) $gate->count('mnt_order_part', array('where' => array('order_id' => $ord))); }
        catch (\Throwable $t) { $lines = 0; }
        $id = 0;
        try {
            $id = (int) $gate->insert('mnt_part_request', array(
                'req_no' => $no, 'order_id' => $ord, 'request_date' => self::today(),
                'warehouse_ref' => isset($d['warehouse_ref']) ? (string) $d['warehouse_ref'] : '',
                'lines_count' => $lines,
                'priority' => (isset($d['priority']) && $d['priority'] === 'urgent') ? 'urgent' : 'normal',
                'custodian_id' => (isset($d['custodian_id']) && (int) $d['custodian_id'] > 0) ? (int) $d['custodian_id'] : null,
                'state' => 'submitted', 'state_rule' => 'W7_PART_REQUEST_SUBMITTED',
                'created_by' => (isset($d['created_by']) && (int) $d['created_by'] > 0) ? (int) $d['created_by'] : null,
                'src_ref' => isset($d['src_ref']) ? (string) $d['src_ref'] : '',
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر فتح طلب الصرف'; return $out; }
        $out['ok'] = true; $out['code'] = 201; $out['request_id'] = $id; $out['created'] = true;
        self::emit($gate, 'mnt.parts.requested', 'mnt_part_request', $id,
                   array('req_no' => $no, 'order_id' => $ord, 'lines' => $lines), 'preq:' . $id);
        return $out;
    }

    /** إحالةٌ خارجيةٌ أو مطالبةُ ضمان — والضمانُ بلا مرجعِ عقدٍ يُردّ */
    public static function referExternal(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'line_id' => 0);
        $ord = (int) (isset($d['order_id']) ? $d['order_id'] : 0);
        $kind = (isset($d['line_kind']) && $d['line_kind'] === 'warranty_claim') ? 'warranty_claim' : 'external_referral';
        $ref = isset($d['contract_ref']) ? trim((string) $d['contract_ref']) : '';
        if (!$gate->selectOne('mnt_order', array('where' => array('id' => $ord)))) {
            $out['code'] = 404; $out['reason'] = 'أمر العمل غير موجود'; return $out;
        }
        if ($kind === 'warranty_claim' && $ref === '') {
            $out['code'] = 422; $out['reason'] = 'WARRANTY_WITHOUT_CONTRACT_REF'; return $out;
        }
        $id = 0;
        try {
            $id = (int) $gate->insert('mnt_external_repair', array(
                'order_id' => $ord, 'line_kind' => $kind,
                'vendor_id' => (isset($d['vendor_id']) && (int) $d['vendor_id'] > 0) ? (int) $d['vendor_id'] : null,
                'contract_ref' => $ref,
                'scope_ar' => isset($d['scope_ar']) ? (string) $d['scope_ar'] : '',
                'estimated_cost' => (float) (isset($d['estimated_cost']) ? $d['estimated_cost'] : 0),
                'state' => 'sent', 'state_rule' => 'W7_EXTERNAL_SENT',
                'created_by' => (isset($d['created_by']) && (int) $d['created_by'] > 0) ? (int) $d['created_by'] : null,
                'src_ref' => isset($d['src_ref']) ? (string) $d['src_ref'] : '',
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر تسجيل الإحالة'; return $out; }
        $out['ok'] = true; $out['code'] = 201; $out['line_id'] = $id;
        self::emit($gate, 'mnt.external.referred', 'mnt_external_repair', $id,
                   array('order_id' => $ord, 'kind' => $kind), 'ext:' . $id);
        return $out;
    }

    /** عنايةٌ يوميّة — والنتيجةُ غيرُ الطبيعيّةِ بلا ملاحظةٍ تُردّ قبل القيد */
    public static function logDailyCare(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'line_id' => 0);
        $eq = (int) (isset($d['equipment_id']) ? $d['equipment_id'] : 0);
        $day = isset($d['care_date']) ? (string) $d['care_date'] : self::today();
        $task = trim((string) (isset($d['task_key']) ? $d['task_key'] : ''));
        $res = isset($d['result']) ? (string) $d['result'] : 'ok';
        $note = isset($d['abnormal_note']) ? trim((string) $d['abnormal_note']) : '';
        if ($eq <= 0 || $task === '') { $out['code'] = 422; $out['reason'] = 'لا سطر عناية بلا معدة ومهمة'; return $out; }
        if ($res === 'abnormal' && $note === '') { $out['code'] = 422; $out['reason'] = 'ABNORMAL_WITHOUT_NOTE'; return $out; }
        $ex = $gate->selectOne('mnt_daily_care', array(
            'where' => array('equipment_id' => $eq, 'care_date' => $day, 'task_key' => $task)));
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['line_id'] = (int) $ex['id'];
            $out['reason'] = 'السطر قائم سلفا. عطالة بالحبة'; return $out;
        }
        $id = 0;
        try {
            $id = (int) $gate->insert('mnt_daily_care', array(
                'care_date' => $day, 'equipment_id' => $eq,
                'checklist_ref' => isset($d['checklist_ref']) ? (string) $d['checklist_ref'] : '',
                'task_key' => $task, 'task_ar' => isset($d['task_ar']) ? (string) $d['task_ar'] : '',
                'performed_by' => (isset($d['performed_by']) && (int) $d['performed_by'] > 0) ? (int) $d['performed_by'] : null,
                'result' => $res, 'abnormal_note' => $note,
                'state' => ($res === 'abnormal') ? 'open' : 'closed',
                'state_rule' => ($res === 'abnormal') ? 'W7_CARE_ABNORMAL_OPEN' : 'W7_CARE_DONE',
                'created_by' => (isset($d['created_by']) && (int) $d['created_by'] > 0) ? (int) $d['created_by'] : null,
                'src_ref' => isset($d['src_ref']) ? (string) $d['src_ref'] : '',
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر قيد سطر العناية'; return $out; }
        $out['ok'] = true; $out['code'] = 201; $out['line_id'] = $id;
        return $out;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ④ شهادةُ إعادةِ الخدمة — الشهادةُ وحدَها تعيد المعدّة
       ═══════════════════════════════════════════════════════════════════════ */

    /** تكلفةُ الأمرِ الفعليّةُ **مشتقّةٌ** من عمالتِه وقطعِه وخارجيّه — لا تُكتب */
    public static function deriveOrderCost(TenantDb $gate, $orderId)
    {
        $sum = 0.0;
        foreach (array(
            array('mnt_order_labor', 'cost'), array('mnt_order_part', 'subtotal'),
            array('mnt_external_repair', 'actual_cost'),
        ) as $t) {
            try {
                foreach ($gate->select($t[0], array('where' => array('order_id' => (int) $orderId), 'limit' => 500)) as $r) {
                    if (isset($r[$t[1]])) { $sum += (float) $r[$t[1]]; }
                }
            } catch (\Throwable $e) { /* الجدولُ قد لا يحمل العمودَ — المجموعُ يبقى صادقًا بما قِيس */ }
        }
        return round($sum, 2);
    }

    /**
     * إصدارُ شهادةِ إعادةِ خدمة — **البسيطُ لا شهادةَ له** (DEC-OPEN-12 ①).
     * ولا شهادةَ لأمرٍ لم يُنجَز فنّيًّا (`work_end` فارغ) — «الشهادةُ بعد الإصلاح».
     */
    public static function issueCert(TenantDb $gate, $orderId, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'cert_id' => 0, 'created' => false);
        $orderId = (int) $orderId;
        $o = $gate->selectOne('mnt_order', array('where' => array('id' => $orderId)));
        if (!$o) { $out['code'] = 404; $out['reason'] = 'أمر العمل غير موجود'; return $out; }

        $sev = (string) $o['safety_severity'];
        if ($sev === 'minor') { $out['code'] = 409; $out['reason'] = 'CERT_NOT_REQUIRED_FOR_MINOR'; return $out; }
        if ((string) $o['work_end'] === '' || $o['work_end'] === null) {
            $out['code'] = 409; $out['reason'] = 'ORDER_WORK_NOT_COMPLETE'; return $out;
        }
        if ($sev === 'safety_critical' && (string) $o['lockout_state'] === 'none') {
            $out['code'] = 409; $out['reason'] = 'LOCKOUT_REQUIRED_BEFORE_CERT'; return $out;
        }
        $test = isset($d['test_performed']) ? trim((string) $d['test_performed']) : '';
        if ($test === '') { $out['code'] = 422; $out['reason'] = 'لا شهادة بلا اختبار موثق'; return $out; }
        $creator = (int) (isset($d['created_by']) ? $d['created_by'] : 0);
        if ($creator <= 0) { $out['code'] = 422; $out['reason'] = 'لا شهادة بلا منشئ معروف'; return $out; }

        $days = self::threshold($sev === 'safety_critical' ? 'W7_CERT_VALID_DAYS_SAFETY' : 'W7_CERT_VALID_DAYS_MAJOR');
        if ($days === null) { $out['code'] = 503; $out['reason'] = 'W7_CERT_NO_VALIDITY_THRESHOLD'; return $out; }

        $no = trim((string) (isset($d['cert_no']) ? $d['cert_no'] : ''));
        if ($no === '') { $out['code'] = 422; $out['reason'] = 'لا شهادة بلا رقم يعرفها'; return $out; }
        $ex = $gate->selectOne('mnt_return_cert', array('where' => array('cert_no' => $no)));
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['cert_id'] = (int) $ex['id'];
            $out['reason'] = 'الشهادة قائمة سلفا. عطالة بالمفتاح'; return $out;
        }
        $cls = self::classifySafety($gate, (int) $o['equipment_id'], (string) $o['safety_system_key']);
        $doneDate = isset($d['tech_complete_date']) ? (string) $d['tech_complete_date'] : substr((string) $o['work_end'], 0, 10);
        $newState = isset($d['new_readiness_state']) ? (string) $d['new_readiness_state'] : 'operational';
        $limits = isset($d['operating_limits']) ? (string) $d['operating_limits'] : '';
        if ($newState === 'operational_restricted' && $limits === '') {
            $out['code'] = 422; $out['reason'] = 'RESTRICTED_WITHOUT_LIMITS'; return $out;
        }
        $id = 0;
        try {
            $id = (int) $gate->insert('mnt_return_cert', array(
                'cert_no' => $no, 'order_id' => $orderId, 'equipment_id' => (int) $o['equipment_id'],
                'safety_severity' => $sev, 'cert_required' => 1,
                'cert_rule' => ($cls['rule_ref'] !== '' ? $cls['rule_ref'] : 'W7_CERT_REQUIRED_BY_SEVERITY'),
                'tech_complete_date' => $doneDate, 'test_performed' => $test,
                'test_result' => isset($d['test_result']) ? (string) $d['test_result'] : 'pass',
                'meter_at_close' => (isset($d['meter_at_close']) && $d['meter_at_close'] !== '') ? (float) $d['meter_at_close'] : null,
                'downtime_hours' => (float) $o['downtime_hours'],
                'actual_cost' => self::deriveOrderCost($gate, $orderId),
                'cost_rule' => 'W7_COST_FROM_LABOR_PARTS_EXTERNAL',
                'new_readiness_state' => $newState, 'operating_limits' => $limits,
                'valid_days' => (int) $days,
                'signer_kind' => ($sev === 'safety_critical') ? 'technical_authority' : ($cls['approver_kind'] !== '' ? $cls['approver_kind'] : 'technician'),
                'state' => 'submitted', 'state_rule' => 'W7_CERT_SUBMITTED',
                'created_by' => $creator,
                'src_ref' => isset($d['src_ref']) ? (string) $d['src_ref'] : '',
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر إصدار الشهادة'; return $out; }
        $out['ok'] = true; $out['code'] = 201; $out['cert_id'] = $id; $out['created'] = true;
        self::emit($gate, 'mnt.return_cert.issued', 'mnt_return_cert', $id,
                   array('cert_no' => $no, 'order_id' => $orderId, 'severity' => $sev), 'cert:' . $id);
        return $out;
    }

    /**
     * اعتمادُ الشهادة — **وهنا وحدَها تعود المعدّةُ للخدمة**.
     * ⛔ ومَن أنشأها لا يعتمدها · والحرِجُ للسلامةِ لا يعتمده إلّا مخوَّلٌ فنّيّ.
     */
    public static function approveCert(TenantDb $gate, $certId, $approverId, $signerKind = '')
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'valid_until' => '', 'readiness' => '');
        $certId = (int) $certId; $approverId = (int) $approverId;
        $c = $gate->selectOne('mnt_return_cert', array('where' => array('id' => $certId)));
        if (!$c) { $out['code'] = 404; $out['reason'] = 'الشهادة غير موجودة'; return $out; }
        if ((string) $c['state'] === 'approved') {
            $out['ok'] = true; $out['code'] = 200; $out['valid_until'] = (string) $c['valid_until'];
            $out['reason'] = 'معتمدة سلفا. عطالة بالحالة'; return $out;
        }
        if ((string) $c['state'] !== 'submitted') { $out['code'] = 409; $out['reason'] = 'CERT_STATE_NOT_SUBMITTED'; return $out; }
        if ($approverId <= 0) { $out['code'] = 422; $out['reason'] = 'لا اعتماد بلا معتمد معروف'; return $out; }
        if ((int) $c['created_by'] === $approverId) { $out['code'] = 403; $out['reason'] = 'SOD_SELF_APPROVAL'; return $out; }
        if ((string) $c['test_result'] === 'fail') { $out['code'] = 409; $out['reason'] = 'TEST_FAILED_NO_RETURN'; return $out; }
        $kind = $signerKind !== '' ? (string) $signerKind : (string) $c['signer_kind'];
        if ((string) $c['safety_severity'] === 'safety_critical' && $kind !== 'technical_authority') {
            $out['code'] = 403; $out['reason'] = 'SAFETY_CRITICAL_NEEDS_TECHNICAL_AUTHORITY'; return $out;
        }
        $until = self::addDays($c['tech_complete_date'], (int) $c['valid_days']);
        $gate->update('mnt_return_cert', array(
            'state' => 'approved', 'state_rule' => 'W7_CERT_APPROVED',
            'signed_by' => $approverId, 'signer_kind' => $kind,
            'approved_by' => $approverId, 'approved_at' => self::now(),
            'valid_until' => $until,
        ), array('id' => $certId));
        $out['valid_until'] = $until;
        $r = self::returnToService($gate, $certId);
        $out['ok'] = $r['ok']; $out['code'] = $r['ok'] ? 200 : $r['code'];
        $out['reason'] = $r['ok'] ? 'اعتمدت الشهادة وعادت المعدة' : $r['reason'];
        $out['readiness'] = isset($r['readiness']) ? $r['readiness'] : '';
        self::emit($gate, 'mnt.return_cert.approved', 'mnt_return_cert', $certId,
                   array('order_id' => (int) $c['order_id'], 'equipment_id' => (int) $c['equipment_id'],
                         'valid_until' => $until), 'certok:' . $certId);
        return $out;
    }

    /**
     * عودةُ المعدّةِ للخدمة — **لا تُستدعى إلّا بشهادةٍ معتمَدة**.
     * والحالةُ الفنيّةُ ملكُ الأسطول، والكتابةُ فيها **أثرُ الشهادةِ لا فعلُ الصيانة**.
     */
    public static function returnToService(TenantDb $gate, $certId)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'readiness' => '');
        $c = $gate->selectOne('mnt_return_cert', array('where' => array('id' => (int) $certId)));
        if (!$c) { $out['code'] = 404; $out['reason'] = 'الشهادة غير موجودة'; return $out; }
        if ((string) $c['state'] !== 'approved') { $out['code'] = 403; $out['reason'] = 'NO_RETURN_WITHOUT_APPROVED_CERT'; return $out; }
        $state = (string) $c['new_readiness_state'];
        if ($state === '') { $state = 'operational'; }
        $gate->update('equipments', array(
            'w7_readiness_state'  => $state,
            'w7_readiness_rule'   => 'W7_RETURN_BY_APPROVED_CERT',
            'w7_operating_limits' => (string) $c['operating_limits'],
            'w7_cert_id'          => (int) $c['id'],
        ), array('id' => (int) $c['equipment_id']));
        $gate->update('mnt_order', array(
            'state' => 'closed', 'w7_state_rule' => 'W7_ORDER_CLOSED_BY_CERT',
            'w7_cert_id' => (int) $c['id'], 'closed_at' => self::now(),
            'closed_by' => (isset($c['approved_by']) && (int) $c['approved_by'] > 0) ? (int) $c['approved_by'] : null,
            'lockout_state' => ((string) $c['safety_severity'] === 'safety_critical') ? 'released' : 'none',
            'lockout_at' => ((string) $c['safety_severity'] === 'safety_critical') ? self::now() : null,
            'readiness_cert_ref' => (string) $c['cert_no'],
        ), array('id' => (int) $c['order_id']));
        $out['ok'] = true; $out['code'] = 200; $out['readiness'] = $state;
        $out['reason'] = 'عادت المعدة بالشهادة';
        self::emit($gate, 'mnt.equipment.returned_to_service', 'equipment', (int) $c['equipment_id'],
                   array('cert_id' => (int) $c['id'], 'readiness' => $state), 'rts:' . (int) $c['id']);
        return $out;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ⑤ إعادةُ الإصلاح — التكرارُ خلالَ الصلاحيةِ يفتح التحليل
       ═══════════════════════════════════════════════════════════════════════ */

    /** واقعةُ تكرار — و`within_validity` **مشتقٌّ** من شهادةِ الأمرِ الأصليّ لا مُدخَل */
    public static function recordRepeat(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'repeat_id' => 0,
                     'within_validity' => 0, 'days_since_cert' => null, 'trigger' => '');
        $origin = (int) (isset($d['origin_order_id']) ? $d['origin_order_id'] : 0);
        $day    = isset($d['repeat_date']) ? (string) $d['repeat_date'] : self::today();
        $o = $gate->selectOne('mnt_order', array('where' => array('id' => $origin)));
        if (!$o) { $out['code'] = 404; $out['reason'] = 'الأمر الأصلي غير موجود'; return $out; }

        /* الشهادةُ المعتمَدةُ الأحدثُ على الأمرِ الأصليّ — هي مصدرُ الصلاحية */
        $cert = null;
        foreach ($gate->select('mnt_return_cert', array(
            'where' => array('order_id' => $origin, 'state' => 'approved'),
            'orderBy' => 'id DESC', 'limit' => 1)) as $x) { $cert = $x; }

        $days = null; $within = 0; $trigger = 'manual';
        if ($cert && (string) $cert['valid_until'] !== '') {
            $days = self::dayDiff((string) $cert['tech_complete_date'], $day);
            $within = ($day <= (string) $cert['valid_until']) ? 1 : 0;
            if ($within) { $trigger = 'repeat_within_validity'; }
        }
        if ((string) $o['safety_severity'] === 'safety_critical') { $trigger = 'safety_critical'; }
        if (isset($d['rca_trigger']) && (string) $d['rca_trigger'] !== '') { $trigger = (string) $d['rca_trigger']; }

        $ex = $gate->selectOne('mnt_repeat_repair', array(
            'where' => array('origin_order_id' => $origin, 'repeat_date' => $day)));
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['repeat_id'] = (int) $ex['id'];
            $out['within_validity'] = (int) $ex['within_validity']; $out['days_since_cert'] = $ex['days_since_cert'];
            $out['trigger'] = (string) $ex['rca_trigger']; $out['reason'] = 'الواقعة قائمة سلفا. عطالة بالحبة';
            return $out;
        }
        $id = 0;
        try {
            $id = (int) $gate->insert('mnt_repeat_repair', array(
                'equipment_id' => (int) $o['equipment_id'], 'origin_order_id' => $origin,
                'origin_cert_id' => $cert ? (int) $cert['id'] : null,
                'new_order_id' => (isset($d['new_order_id']) && (int) $d['new_order_id'] > 0) ? (int) $d['new_order_id'] : null,
                'tree_node' => isset($d['tree_node']) ? (string) $d['tree_node'] : (string) $o['safety_system_key'],
                'repeat_date' => $day, 'days_since_cert' => $days, 'within_validity' => $within,
                'rca_trigger' => $trigger, 'rca_state' => 'open',
                'derivation_rule' => $cert ? 'W7_REPEAT_VS_CERT_VALIDITY' : 'W7_REPEAT_NO_CERT_BASELINE',
                'created_by' => (isset($d['created_by']) && (int) $d['created_by'] > 0) ? (int) $d['created_by'] : null,
                'src_ref' => isset($d['src_ref']) ? (string) $d['src_ref'] : '',
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر قيد واقعة التكرار'; return $out; }
        $out['ok'] = true; $out['code'] = 201; $out['repeat_id'] = $id;
        $out['within_validity'] = $within; $out['days_since_cert'] = $days; $out['trigger'] = $trigger;
        self::emit($gate, 'mnt.repeat_repair.opened', 'mnt_repeat_repair', $id,
                   array('origin_order_id' => $origin, 'within_validity' => $within, 'trigger' => $trigger),
                   'rep:' . $id);
        return $out;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ⑥ المؤشّرات — مشتقّةٌ بلا إدخال (`MNT-16` نصًّا)
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * اشتقاقُ مؤشّراتِ فترةٍ لأصلٍ — **صيغةٌ واحدةٌ** تستدعيها الأداةُ والبوّابةُ
     * والشاشةُ معًا، فلا يتفرّق عدّادٌ وعارضٌ في ملفَّين.
     */
    public static function kpiFormula($breakdowns, $downtime, $pmDone, $pmDue, $cost, $runHours)
    {
        $b = max(0, (int) $breakdowns);
        $mtbf = ($b > 0) ? round(((float) $runHours) / $b, 2) : 0.0;
        $mttr = ($b > 0) ? round(((float) $downtime) / $b, 2) : 0.0;
        $pm   = ((int) $pmDue > 0) ? round(((int) $pmDone) * 100.0 / (int) $pmDue, 2) : 0.0;
        $cph  = ((float) $runHours > 0) ? round(((float) $cost) / (float) $runHours, 4) : 0.0;
        return array('mtbf' => $mtbf, 'mttr' => $mttr, 'pm_compliance' => $pm, 'cost_per_hour' => $cph);
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ⑦ أدواتٌ داخليّة
       ═══════════════════════════════════════════════════════════════════════ */

    private static function now()   { return \date('Y-m-d H:i:s'); }
    private static function today() { return \date('Y-m-d'); }

    private static function addDays($date, $days)
    {
        $d = \strtotime((string) $date);
        if ($d === false) { return ''; }
        return \date('Y-m-d', $d + ((int) $days * 86400));
    }

    private static function dayDiff($from, $to)
    {
        $a = \strtotime((string) $from); $b = \strtotime((string) $to);
        if ($a === false || $b === false) { return null; }
        return (int) \floor(($b - $a) / 86400);
    }

    private static function companyOf(TenantDb $gate, $table, $id)
    {
        try {
            $r = $gate->selectOne($table, array('columns' => array('company_id'), 'where' => array('id' => (int) $id)));
            return $r ? (int) $r['company_id'] : 0;
        } catch (\Throwable $t) { return 0; }
    }

    private static function entityTable($entityType)
    {
        return ($entityType === 'equipment') ? 'equipments' : $entityType;
    }

    /** نشرُ حقيقةٍ محايدة — و`publishFact` تعيد `null` صامتةً إن أُطفئ الجذر */
    private static function emit(TenantDb $gate, $eventKey, $entityType, $entityId, array $payload, $idem)
    {
        $conn = self::$eventConn;
        if (!($conn instanceof \mysqli)) { return null; }
        $pub = \dirname(\dirname(\dirname(__DIR__))) . '/app/Core/EventPublisher.php';
        if (!\is_file($pub)) { return null; }
        require_once $pub;
        $company = self::companyOf($gate, self::entityTable($entityType), $entityId);
        try {
            return \App\Core\EventPublisher::publishFact($conn, array(
                'company_id'      => $company,
                'event_key'       => $eventKey,
                'category'        => 'maintenance',
                'source_module'   => 'maintenance',
                'entity_type'     => $entityType,
                'entity_id'       => (int) $entityId,
                'payload'         => $payload,
                'idempotency_key' => 'w7:' . $idem,
                'source_ref'      => 'MaintenanceCycleService',
            ));
        } catch (\Throwable $t) { return null; }
    }

    /**
     * نقطةُ الإنشاءِ التي يناديها مُطلِقُ الطلبات (RPR-W15).
     *
     * ◆ **النطاقُ يملك تعريفَ طلبِه** (‏القرار ③): طلبُ الصيانةِ يُنشَأ **في
     *   سجلِّ الصيانةِ** `mnt_breakdown` — و«طلباتي» تُطلِقه ولا تخزّنه.
     * ◆ **والكتابةُ تقع هنا عند المالكِ** لا في المُطلِق، فحاجبُ «كتابةٌ من
     *   مساحةِ الموجة» يبقى صفرًا وهو عينُ ما أمر به المالك.
     */
    public static function createFromLauncher(\mysqli $conn, array $ctx, array $payload)
    {
        $requester = isset($ctx['requester_id']) ? (int) $ctx['requester_id'] : 0;
        $eq = isset($payload['equipment_id']) ? (int) $payload['equipment_id'] : 0;
        if ($requester <= 0) { return array('ok' => false, 'row_id' => 0, 'why' => 'لا طلب بلا صاحب'); }
        if ($eq <= 0) { return array('ok' => false, 'row_id' => 0, 'why' => 'لا طلب صيانة بلا معدة'); }

        $gate = (isset($ctx['gate']) && $ctx['gate'] !== null) ? $ctx['gate'] : \ems_tenant_db();
        $code = (isset($payload['code']) && trim((string) $payload['code']) !== '')
            ? (string) $payload['code']
            : ('WS-' . $requester . '-' . $eq . '-' . date('YmdHis'));
        $res = self::receiveBreakdown($gate, array(
            'code'            => $code,
            'equipment_id'    => $eq,
            'project_id'      => isset($payload['project_id']) ? (int) $payload['project_id'] : 0,
            'reported_by'     => $requester,
            'reporter_dept'   => isset($payload['reporter_dept']) ? (string) $payload['reporter_dept'] : '',
            'report_datetime' => isset($payload['report_datetime']) ? (string) $payload['report_datetime'] : self::now(),
            'severity'        => isset($payload['severity']) ? (string) $payload['severity'] : '',
            'is_stopped'      => !empty($payload['is_stopped']) ? 1 : 0,
            'description'     => isset($payload['description']) ? (string) $payload['description'] : '',
        ));
        if (empty($res['ok'])) {
            return array('ok' => false, 'row_id' => 0,
                         'why' => isset($res['reason']) ? (string) $res['reason'] : 'ردته خدمة المالك');
        }
        return array('ok' => true, 'row_id' => (int) $res['breakdown_id'], 'why' => '');
    }
}
