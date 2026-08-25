<?php
/**
 * app/Services/Fleet/AssetLifecycleService.php — دورةُ الأصلِ وحقُّ استخدامِه (RPR-W05)
 * ═══════════════════════════════════════════════════════════════════════════
 * **دورةُ الأصل**: طلبُ إدخال ← تحقُّقٌ من المصدر ← أمرُ تفتيشٍ وبطاقتُه ←
 * كرتُ الأصل ← تفعيل ← حقُّ استخدامٍ تشغيليّ ← إسنادٌ لموقع ← حركةٌ واستخدام ←
 * رقابةٌ فنيّة ← خروجٌ (مؤقّتٌ أو دائم).
 *
 * القواعدُ الحاكمة:
 *  · **لا كرتَ أصلٍ قبل اجتيازِ التحقُّقِ من المصدر** (`FLEET-04` نصًّا). والمنعُ
 *    هنا في الخدمةِ ويُثبَت **وظيفيًّا** في البوّابة — `CHECK` لا يقرأ جدولًا
 *    آخر، والقادحُ يحتاج `SUPER` لا يملكه مستخدمُ الهجرات (‏W03 · W04-X-01).
 *  · **حقُّ الاستخدامِ متعاقبٌ لا متزامن** (`FLEET-09` نصًّا). والخدمةُ **تقيس
 *    التزامنَ ولا تمنعه**: تكتب مجموعَ الحصصِ في نافذةِ البدايةِ ووسمَها
 *    (`W5_SUCCESSIVE_OK` أو `W5_CONCURRENT_CLAIM_OPEN`). المنعُ هنا كان
 *    سيمحو ٦٦ تقاطعًا حيًّا قبل أن تُراجَع (‏W3-D-04)، **وحسمُها عند مالكِها**
 *    (التمويل · W11).
 *  · **الجاهزيّةُ تُشتقُّ ولا تُدخَل** (`FLEET-20` · `WRK-03`). و`deriveReadiness`
 *    تكتب القاعدةَ ومصادرَها في الصفِّ نفسِه — **ولا قيمةَ بلا قاعدة**.
 *  · **حالةُ الأصلِ مشتقّةٌ من وقائعِه** لا مُدخَلةٌ في عمود: `deriveLifecycle`
 *    تقرأ الطلبَ والإسنادَ والخروجَ وترجّح بترتيبِ الدورةِ لا بترتيبِ الكتابة.
 *  · **الأصلُ الخارجُ دائمًا لا يُسنَد** — والمحاولةُ تُردُّ 409 لا تُتجاهَل.
 *
 * ◆ **وكلُّ وصولٍ إلى القاعدةِ عبرَ بوابةِ المستأجِر** (`TenantDb`) — لا استعلامَ
 *   خامٍّ على جدولِ مستأجِر (‏FR-SEC-006 · GAP-29 · وسقّاطةُ
 *   `injfix01_raw_query_ratchet`). فالعزلُ يُحقن بنيويًّا و`company_id`
 *   **لا يُمرَّر من المُستدعي**.
 */

namespace App\Services\Fleet;

use App\Core\TenantDb;

class AssetLifecycleService
{
    /** أسبابُ أمرِ التفتيشِ الخمسةُ في دورةِ الأصل (`FLEET-05`) */
    const INSPECTION_REASONS = array('intake', 'periodic', 'post_repair', 'pre_exit', 'incident');

    /** حالاتُ طلبِ الإدخالِ بترتيبِ الدورة — والترتيبُ هو الذي يمنع القفز */
    const INTAKE_FLOW = array('draft', 'submitted', 'source_verified', 'inspection_ordered',
                              'inspected', 'card_issued', 'activated');

    /**
     * اتصالُ نشرِ الحقائقِ المحايدة — يُحقن صراحةً من المُستدعي.
     * (‏ADR-15: `EventPublisher::publishFact` يأخذ `mysqli` بحكمِ عقدِه، ودفترُ
     *  الأحداثِ الجذرُ ليس جدولَ مستأجِرٍ يُكتب عبرَ بوابةِ العزل.)
     * @var \mysqli|null
     */
    private static $eventConn = null;

    public static function setEventConnection(\mysqli $conn)
    {
        self::$eventConn = $conn;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ① دخولُ الأصل
       ═══════════════════════════════════════════════════════════════════════ */

    /** طلبُ إدخالِ أصل — عطالةٌ بمفتاحِ (كيان × رقمِ الطلب) */
    public static function openIntake(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'intake_id' => 0, 'state' => '', 'created' => false);
        $no = isset($d['intake_no']) ? trim((string) $d['intake_no']) : '';
        $actor = isset($d['requested_by']) ? (int) $d['requested_by'] : 0;
        if ($no === '')    { $out['code'] = 422; $out['reason'] = 'لا طلب بلا رقم يعرفه'; return $out; }
        if ($actor <= 0)   { $out['code'] = 422; $out['reason'] = 'لا طلب بلا طالب معروف'; return $out; }
        $dept = isset($d['requested_dept']) ? (string) $d['requested_dept'] : '';
        if ($dept === '')  { $out['code'] = 422; $out['reason'] = 'لا طلب بلا إدارة طالبة برمزها المعياري'; return $out; }

        $ex = $gate->selectOne('asset_intake', array('where' => array('intake_no' => $no)));
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['intake_id'] = (int) $ex['id'];
            $out['state'] = $ex['state']; $out['reason'] = 'الطلب قائم سلفا — عطالة بالمفتاح';
            return $out;
        }
        $row = array(
            'intake_no'      => $no,
            'requested_dept' => $dept,
            'asset_kind'     => isset($d['asset_kind']) ? (string) $d['asset_kind'] : '',
            'source_type'    => isset($d['source_type']) ? (string) $d['source_type'] : 'owned',
            'supplier_id'    => (isset($d['supplier_id']) && (int) $d['supplier_id'] > 0) ? (int) $d['supplier_id'] : null,
            'state'          => 'submitted',
            'state_rule'     => 'W5_INTAKE_SUBMITTED_BY_DEPT',
            'requested_by'   => $actor,
            'requested_at'   => self::now(),
            'source_ref'     => isset($d['source_ref']) ? (string) $d['source_ref'] : '',
        );
        $id = 0;
        try { $id = (int) $gate->insert('asset_intake', $row); } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) {
            $again = $gate->selectOne('asset_intake', array('where' => array('intake_no' => $no)));
            if ($again) { $out['ok'] = true; $out['code'] = 200; $out['intake_id'] = (int) $again['id']; $out['state'] = $again['state']; $out['reason'] = 'عطالة بالمفتاح'; return $out; }
            $out['code'] = 500; $out['reason'] = 'تعذر فتح طلب الإدخال'; return $out;
        }
        $out['ok'] = true; $out['code'] = 201; $out['intake_id'] = $id; $out['state'] = 'submitted'; $out['created'] = true;
        self::emit($gate, 'fleet.asset.intake_requested', 'asset_intake', $id,
                   array('intake_no' => $no, 'requested_dept' => $dept, 'source_type' => $row['source_type']),
                   'intake:' . $id);
        return $out;
    }

    /** واقعةُ تحقُّقٍ من المصدر — عطالةٌ بمفتاحِ (الطلب × ترتيبِ الواقعة) */
    public static function verifySource(TenantDb $gate, $intakeId, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'check_id' => 0, 'result' => '', 'created' => false);
        $intakeId = (int) $intakeId;
        $intake = $gate->selectOne('asset_intake', array('where' => array('id' => $intakeId)));
        if (!$intake) { $out['code'] = 404; $out['reason'] = 'طلب الإدخال غير موجود'; return $out; }
        $result = (isset($d['verify_result']) && $d['verify_result'] === 'failed') ? 'failed' : 'passed';
        $docRef = isset($d['doc_ref']) ? (string) $d['doc_ref'] : '';
        $fail   = isset($d['fail_reason']) ? (string) $d['fail_reason'] : '';
        if ($result === 'passed' && $docRef === '') {
            $out['code'] = 422; $out['reason'] = 'لا اجتياز بلا مستند مرجعه مكتوب'; return $out;
        }
        if ($result === 'failed' && $fail === '') {
            $out['code'] = 422; $out['reason'] = 'لا إخفاق بلا سبب مكتوب'; return $out;
        }
        $seq = (int) $gate->count('asset_source_check', array('where' => array('intake_id' => $intakeId))) + 1;
        $ex = $gate->selectOne('asset_source_check', array(
            'where' => array('intake_id' => $intakeId, 'check_seq' => $seq)));
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['check_id'] = (int) $ex['id'];
            $out['result'] = $ex['verify_result']; $out['reason'] = 'عطالة بالمفتاح'; return $out;
        }
        $id = 0;
        try {
            $id = (int) $gate->insert('asset_source_check', array(
                'intake_id'      => $intakeId,
                'check_seq'      => $seq,
                'doc_type'       => isset($d['doc_type']) ? (string) $d['doc_type'] : '',
                'doc_ref'        => $docRef,
                'owner_declared' => isset($d['owner_declared']) ? (string) $d['owner_declared'] : '',
                'owner_legal'    => isset($d['owner_legal']) ? (string) $d['owner_legal'] : '',
                'verify_result'  => $result,
                'verify_rule'    => ($result === 'passed') ? 'W5_SOURCE_DOC_MATCHES_LEGAL_OWNER' : 'W5_SOURCE_DOC_MISMATCH',
                'fail_reason'    => $fail,
                'verified_by'    => (isset($d['verified_by']) && (int) $d['verified_by'] > 0) ? (int) $d['verified_by'] : null,
                'verified_at'    => self::now(),
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر تسجيل واقعة التحقق'; return $out; }

        if ($result === 'passed') {
            $gate->update('asset_intake', array('state' => 'source_verified', 'state_rule' => 'W5_SOURCE_VERIFIED_BY_DOC'),
                          array('id' => $intakeId));
        } else {
            $gate->update('asset_intake', array('state' => 'rejected', 'state_rule' => 'W5_SOURCE_CHECK_FAILED',
                                                'reject_reason' => $fail), array('id' => $intakeId));
        }
        $out['ok'] = true; $out['code'] = 201; $out['check_id'] = $id; $out['result'] = $result; $out['created'] = true;
        self::emit($gate, 'fleet.asset.source_verified', 'asset_source_check', $id,
                   array('intake_id' => $intakeId, 'verify_result' => $result, 'doc_ref' => $docRef),
                   'source_check:' . $id);
        return $out;
    }

    /** أمرُ تفتيشٍ — عطالةٌ بمفتاحِ (كيان × رقمِ الأمر) · وله خمسةُ أسبابٍ لا نصٌّ حرّ */
    public static function orderInspection(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'order_id' => 0, 'created' => false);
        $no = isset($d['order_no']) ? trim((string) $d['order_no']) : '';
        $why = isset($d['reason']) ? (string) $d['reason'] : '';
        $intakeId = (isset($d['intake_id']) && (int) $d['intake_id'] > 0) ? (int) $d['intake_id'] : 0;
        $equipId  = (isset($d['equipment_id']) && (int) $d['equipment_id'] > 0) ? (int) $d['equipment_id'] : 0;
        if ($no === '') { $out['code'] = 422; $out['reason'] = 'لا أمر بلا رقم يعرفه'; return $out; }
        if (!\in_array($why, self::INSPECTION_REASONS, true)) {
            $out['code'] = 422; $out['reason'] = 'سبب التفتيش خارج الأسباب الخمسة المعيارية'; return $out;
        }
        if ($intakeId <= 0 && $equipId <= 0) {
            $out['code'] = 422; $out['reason'] = 'لا أمر تفتيش بلا هدف — طلب إدخال أو أصل قائم'; return $out;
        }
        /* ⚠ التفتيشُ عند الدخولِ لا يسبق التحقُّقَ من المصدر: أمرٌ على طلبٍ لم
             يجتزْ تحقُّقَه يُردُّ 409 — والدورةُ ترتيبٌ لا مجموعةُ أفعال. */
        if ($intakeId > 0 && $why === 'intake') {
            $intake = $gate->selectOne('asset_intake', array('where' => array('id' => $intakeId)));
            if (!$intake) { $out['code'] = 404; $out['reason'] = 'طلب الإدخال غير موجود'; return $out; }
            if (!\in_array($intake['state'], array('source_verified', 'inspection_ordered', 'inspected'), true)) {
                $out['code'] = 409; $out['reason_code'] = 'SOURCE_NOT_VERIFIED';
                $out['reason'] = 'لا أمر تفتيش دخول قبل اجتياز التحقق من المصدر'; return $out;
            }
        }
        $ex = $gate->selectOne('asset_inspection_order', array('where' => array('order_no' => $no)));
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['order_id'] = (int) $ex['id'];
            $out['reason'] = 'الأمر قائم سلفا — عطالة بالمفتاح'; return $out;
        }
        $id = 0;
        try {
            $id = (int) $gate->insert('asset_inspection_order', array(
                'order_no'     => $no,
                'intake_id'    => $intakeId > 0 ? $intakeId : null,
                'equipment_id' => $equipId > 0 ? $equipId : null,
                'reason'       => $why,
                'reason_rule'  => 'W5_INSPECTION_REASON_' . strtoupper($why),
                'ordered_by'   => (isset($d['ordered_by']) && (int) $d['ordered_by'] > 0) ? (int) $d['ordered_by'] : null,
                'ordered_at'   => self::now(),
                'due_date'     => isset($d['due_date']) ? (string) $d['due_date'] : null,
                'state'        => 'issued',
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر إصدار أمر التفتيش'; return $out; }
        if ($intakeId > 0) {
            $gate->update('asset_intake', array('state' => 'inspection_ordered', 'state_rule' => 'W5_INSPECTION_ORDERED'),
                          array('id' => $intakeId));
        }
        $out['ok'] = true; $out['code'] = 201; $out['order_id'] = $id; $out['created'] = true;
        self::emit($gate, 'fleet.asset.inspection_ordered', 'asset_inspection_order', $id,
                   array('order_no' => $no, 'reason' => $why, 'intake_id' => $intakeId, 'equipment_id' => $equipId),
                   'insp_order:' . $id);
        return $out;
    }

    /** ربطُ بطاقةِ التفتيشِ بأمرِها — والأمرُ لا يصير منفَّذًا بلا بطاقة */
    public static function recordInspectionCard(TenantDb $gate, $orderId, $inspectionId, $result)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $orderId = (int) $orderId; $inspectionId = (int) $inspectionId;
        $o = $gate->selectOne('asset_inspection_order', array('where' => array('id' => $orderId)));
        if (!$o) { $out['code'] = 404; $out['reason'] = 'أمر التفتيش غير موجود'; return $out; }
        if ($inspectionId <= 0) { $out['code'] = 422; $out['reason'] = 'لا تنفيذ بلا بطاقة تفتيش محفوظة'; return $out; }
        if ($o['state'] === 'executed') { $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'منفذ سلفا — عطالة بالحالة'; return $out; }
        if ($o['state'] === 'cancelled') { $out['code'] = 409; $out['reason'] = 'أمر ملغى لا ينفذ'; return $out; }
        $gate->update('asset_inspection_order',
            array('state' => 'executed', 'inspection_id' => $inspectionId, 'result' => (string) $result),
            array('id' => $orderId));
        if ((int) $o['intake_id'] > 0) {
            $gate->update('asset_intake', array('state' => 'inspected', 'state_rule' => 'W5_INSPECTION_CARD_RECORDED'),
                          array('id' => (int) $o['intake_id']));
        }
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /**
     * إصدارُ كرتِ الأصل — **ولا كرتَ قبل اجتيازِ التحقُّقِ من المصدر**.
     * والمنعُ **وظيفيٌّ لا بنيويّ**: `CHECK` لا يقرأ `asset_source_check`،
     * والقادحُ يحتاج امتيازًا لا يملكه مستخدمُ الهجرات — فالبوّابةُ تستدعي
     * هذه الدالّةَ فعلًا وتقيس رمزَ الرفض (`SOURCE_NOT_VERIFIED`).
     */
    public static function issueCard(TenantDb $gate, $intakeId, $equipmentId, $actorId)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'reason_code' => '', 'equipment_id' => 0);
        $intakeId = (int) $intakeId; $equipmentId = (int) $equipmentId; $actorId = (int) $actorId;
        $intake = $gate->selectOne('asset_intake', array('where' => array('id' => $intakeId)));
        if (!$intake) { $out['code'] = 404; $out['reason'] = 'طلب الإدخال غير موجود'; return $out; }
        if ($equipmentId <= 0) { $out['code'] = 422; $out['reason'] = 'لا كرت بلا أصل يحمله'; return $out; }

        $passed = (int) $gate->count('asset_source_check', array(
            'where' => array('intake_id' => $intakeId, 'verify_result' => 'passed')));
        if ($passed === 0) {
            $out['code'] = 409; $out['reason_code'] = 'SOURCE_NOT_VERIFIED';
            $out['reason'] = 'لا ينشأ كرت أصل قبل اجتياز التحقق من المصدر (FLEET-04)';
            return $out;
        }
        if (\in_array($intake['state'], array('card_issued', 'activated'), true)) {
            $out['ok'] = true; $out['code'] = 200; $out['equipment_id'] = (int) $intake['equipment_id'];
            $out['reason'] = 'الكرت صادر سلفا — عطالة بالحالة'; return $out;
        }
        $gate->update('asset_intake',
            array('state' => 'card_issued', 'state_rule' => 'W5_CARD_ISSUED_AFTER_SOURCE_AND_INSPECTION',
                  'equipment_id' => $equipmentId, 'decided_by' => $actorId, 'decided_at' => self::now()),
            array('id' => $intakeId));
        $gate->update('equipments',
            array('intake_id' => $intakeId, 'lifecycle_state' => 'card_issued',
                  'lifecycle_rule' => 'W5_LIFECYCLE_FROM_INTAKE_STATE'),
            array('id' => $equipmentId));
        $out['ok'] = true; $out['code'] = 200; $out['equipment_id'] = $equipmentId;
        self::emit($gate, 'fleet.asset.card_issued', 'equipment', $equipmentId,
                   array('intake_id' => $intakeId, 'equipment_id' => $equipmentId), 'card:' . $equipmentId);
        return $out;
    }

    /** التفعيلُ وإعادةُ الخدمة — الحالةُ التي تنقل الأصلَ إلى نشط (`FLEET-11`) */
    public static function activateAsset(TenantDb $gate, $intakeId, $actorId)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'equipment_id' => 0);
        $intakeId = (int) $intakeId;
        $intake = $gate->selectOne('asset_intake', array('where' => array('id' => $intakeId)));
        if (!$intake) { $out['code'] = 404; $out['reason'] = 'طلب الإدخال غير موجود'; return $out; }
        if ($intake['state'] === 'activated') {
            $out['ok'] = true; $out['code'] = 200; $out['equipment_id'] = (int) $intake['equipment_id'];
            $out['reason'] = 'مفعل سلفا — عطالة بالحالة'; return $out;
        }
        if ($intake['state'] !== 'card_issued') {
            $out['code'] = 409; $out['reason_code'] = 'CARD_NOT_ISSUED';
            $out['reason'] = 'لا تفعيل قبل إصدار الكرت'; return $out;
        }
        $eq = (int) $intake['equipment_id'];
        $gate->update('asset_intake', array('state' => 'activated', 'state_rule' => 'W5_ACTIVATED_AFTER_CARD',
                                            'decided_by' => (int) $actorId, 'decided_at' => self::now()),
                      array('id' => $intakeId));
        $gate->update('equipments', array('lifecycle_state' => 'active', 'lifecycle_rule' => 'W5_LIFECYCLE_FROM_INTAKE_STATE'),
                      array('id' => $eq));
        $out['ok'] = true; $out['code'] = 200; $out['equipment_id'] = $eq;
        self::emit($gate, 'fleet.asset.activated', 'equipment', $eq,
                   array('intake_id' => $intakeId, 'equipment_id' => $eq), 'activate:' . $eq);
        return $out;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ② حقُّ الاستخدامِ التشغيليّ — يُقاس تزامنُه ولا يُدهَس
       ═══════════════════════════════════════════════════════════════════════ */

    /** مفتاحُ الحائز — نوعُه ومرجعُه معًا؛ فمُموِّلانِ مختلفانِ ليسا صفًّا واحدًا */
    public static function holderKey($kind, $refId, $name)
    {
        $ref = (int) $refId;
        return (string) $kind . ':' . ($ref > 0 ? $ref : 'n' . \substr(\sha1((string) $name), 0, 12));
    }

    /**
     * منحُ حقِّ استخدامٍ في فترة — عطالةٌ بمفتاحِ (أصل × حائز × بدايةِ فترة).
     * ◆ **والتزامنُ يُقاس ويُوسَم ولا يُمنَع**: مجموعُ الحصصِ التي تغطّي يومَ
     *   البدايةِ يُكتب في `concurrency_pct`، ووسمُه `W5_CONCURRENT_CLAIM_OPEN`
     *   متى تجاوز المئةَ. المنعُ هنا كان سيمحو التقاطعَ قبل أن يُراجَع.
     */
    public static function grantUseRight(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'right_id' => 0,
                     'concurrency_pct' => 0.0, 'rule' => '', 'created' => false);
        $eq = isset($d['equipment_id']) ? (int) $d['equipment_id'] : 0;
        $from = isset($d['valid_from']) ? (string) $d['valid_from'] : '';
        $pct = isset($d['percent']) ? (float) $d['percent'] : 100.0;
        $kind = isset($d['holder_kind']) ? (string) $d['holder_kind'] : 'company';
        if ($eq <= 0)          { $out['code'] = 422; $out['reason'] = 'لا حق استخدام بلا أصل'; return $out; }
        if (!self::isDate($from)) { $out['code'] = 422; $out['reason'] = 'بداية الفترة إلزامية بصيغة YYYY-MM-DD'; return $out; }
        if ($pct <= 0 || $pct > 100) { $out['code'] = 422; $out['reason'] = 'الحصة خارج المدى (0 < حصة ≤ 100)'; return $out; }
        $to = (isset($d['valid_to']) && self::isDate($d['valid_to'])) ? (string) $d['valid_to'] : null;
        $key = self::holderKey($kind, isset($d['holder_ref_id']) ? $d['holder_ref_id'] : 0,
                               isset($d['holder_name']) ? $d['holder_name'] : '');

        /* مجموعُ الحصصِ المتزامنةِ في نافذةِ البداية — قبلَ إضافةِ هذا الصفّ */
        $conc = self::concurrentPercent($gate, $eq, $from, $key);
        $total = \round($conc + $pct, 2);
        $rule = ($total > 100.0) ? 'W5_CONCURRENT_CLAIM_OPEN' : 'W5_SUCCESSIVE_OK';

        $ex = $gate->selectOne('asset_use_right', array(
            'where' => array('equipment_id' => $eq, 'holder_key' => $key, 'valid_from' => $from)));
        if ($ex) {
            $gate->update('asset_use_right', array(
                'percent'          => $pct,
                'valid_to'         => $to,
                'concurrency_pct'  => $total,
                'concurrency_rule' => $rule,
                'concurrency_note' => isset($d['concurrency_note']) ? (string) $d['concurrency_note'] : '',
            ), array('id' => (int) $ex['id']));
            $out['ok'] = true; $out['code'] = 200; $out['right_id'] = (int) $ex['id'];
            $out['concurrency_pct'] = $total; $out['rule'] = $rule;
            $out['reason'] = 'الحق قائم سلفا — عطالة بالمفتاح'; return $out;
        }
        $id = 0;
        try {
            $id = (int) $gate->insert('asset_use_right', array(
                'equipment_id'     => $eq,
                'holder_kind'      => $kind,
                'holder_key'       => $key,
                'holder_ref_id'    => (isset($d['holder_ref_id']) && (int) $d['holder_ref_id'] > 0) ? (int) $d['holder_ref_id'] : null,
                'holder_name'      => isset($d['holder_name']) ? (string) $d['holder_name'] : '',
                'percent'          => $pct,
                'valid_from'       => $from,
                'valid_to'         => $to,
                'doc_ref'          => isset($d['doc_ref']) ? (string) $d['doc_ref'] : '',
                'source_register'  => isset($d['source_register']) ? (string) $d['source_register'] : 'declared',
                'source_row_ref'   => isset($d['source_row_ref']) ? (string) $d['source_row_ref'] : '',
                'concurrency_rule' => $rule,
                'concurrency_pct'  => $total,
                'concurrency_note' => isset($d['concurrency_note']) ? (string) $d['concurrency_note'] : '',
                'granted_by'       => (isset($d['granted_by']) && (int) $d['granted_by'] > 0) ? (int) $d['granted_by'] : null,
                'granted_at'       => self::now(),
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر منح حق الاستخدام'; return $out; }
        $out['ok'] = true; $out['code'] = 201; $out['right_id'] = $id; $out['created'] = true;
        $out['concurrency_pct'] = $total; $out['rule'] = $rule;
        self::emit($gate, 'fleet.asset.use_right_granted', 'asset_use_right', $id,
                   array('equipment_id' => $eq, 'holder_key' => $key, 'valid_from' => $from,
                         'percent' => $pct, 'concurrency_pct' => $total), 'use_right:' . $id);
        return $out;
    }

    /** مجموعُ حصصِ الحائزينَ الآخرينَ التي تغطّي يومًا بعينِه */
    public static function concurrentPercent(TenantDb $gate, $equipmentId, $onDate, $exceptHolderKey = '')
    {
        $rows = $gate->select('asset_use_right', array(
            'columns' => array('holder_key', 'percent', 'valid_from', 'valid_to'),
            'where'   => array('equipment_id' => (int) $equipmentId)));
        $sum = 0.0;
        foreach ($rows as $r) {
            if ($exceptHolderKey !== '' && $r['holder_key'] === $exceptHolderKey) { continue; }
            if ($r['valid_from'] > $onDate) { continue; }
            if ($r['valid_to'] !== null && $r['valid_to'] !== '' && $r['valid_to'] < $onDate) { continue; }
            $sum += (float) $r['percent'];
        }
        return \round($sum, 2);
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ③ الإسنادُ والخروج
       ═══════════════════════════════════════════════════════════════════════ */

    /** إسنادُ الأصلِ لموقعٍ أو مشروع — والسابقُ يُنهى ولا يُمحى */
    public static function assignAsset(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'reason_code' => '', 'assign_id' => 0, 'created' => false);
        $eq = isset($d['equipment_id']) ? (int) $d['equipment_id'] : 0;
        $from = isset($d['valid_from']) ? (string) $d['valid_from'] : '';
        $site = (isset($d['site_id']) && (int) $d['site_id'] > 0) ? (int) $d['site_id'] : 0;
        $proj = (isset($d['project_id']) && (int) $d['project_id'] > 0) ? (int) $d['project_id'] : 0;
        if ($eq <= 0) { $out['code'] = 422; $out['reason'] = 'لا إسناد بلا أصل'; return $out; }
        if (!self::isDate($from)) { $out['code'] = 422; $out['reason'] = 'تاريخ الإسناد إلزامي'; return $out; }
        if ($site <= 0 && $proj <= 0) { $out['code'] = 422; $out['reason'] = 'لا إسناد بلا موقع ولا مشروع'; return $out; }

        /* ⚠ **الأصلُ الخارجُ دائمًا لا يُسنَد** — والمحاولةُ تُردُّ لا تُتجاهَل */
        $gone = $gate->selectOne('asset_exit', array(
            'columns' => array('id', 'exit_date'),
            'where'   => array('equipment_id' => $eq, 'exit_kind' => 'permanent')));
        if ($gone && (string) $gone['exit_date'] <= $from) {
            $out['code'] = 409; $out['reason_code'] = 'ASSET_PERMANENTLY_EXITED';
            $out['reason'] = 'أصل خرج خروجا دائما لا يسند'; return $out;
        }
        /* ⚠ **ولا إسنادَ لأصلٍ لم يُفعَّل** حين يكون له طلبُ إدخالٍ يحكمه */
        $card = $gate->selectOne('equipments', array('columns' => array('lifecycle_state', 'intake_id'),
                                                     'where' => array('id' => $eq)));
        if ($card && (int) $card['intake_id'] > 0 && $card['lifecycle_state'] !== 'active') {
            $out['code'] = 409; $out['reason_code'] = 'ASSET_NOT_ACTIVE';
            $out['reason'] = 'أصل لم يفعل لا يسند — التفعيل يسبق الإسناد'; return $out;
        }

        $ex = $gate->selectOne('asset_assignment', array('where' => array('equipment_id' => $eq, 'valid_from' => $from)));
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['assign_id'] = (int) $ex['id'];
            $out['reason'] = 'الإسناد قائم سلفا — عطالة بالمفتاح'; return $out;
        }
        /* السابقُ النشِطُ يُنهى بسببِه — لا موقعانِ لأصلٍ في اليومِ نفسِه */
        $prev = $gate->select('asset_assignment', array(
            'columns' => array('id', 'valid_from'),
            'where'   => array('equipment_id' => $eq, 'state' => 'active'), 'orderBy' => 'valid_from DESC'));
        foreach ($prev as $p) {
            if ((string) $p['valid_from'] >= $from) { continue; }
            $gate->update('asset_assignment',
                array('state' => 'ended', 'valid_to' => \date('Y-m-d', \strtotime($from . ' -1 day')),
                      'end_reason' => 'أنهاه إسناد لاحق في ' . $from),
                array('id' => (int) $p['id']));
        }
        $id = 0;
        try {
            $id = (int) $gate->insert('asset_assignment', array(
                'equipment_id' => $eq,
                'assign_kind'  => isset($d['assign_kind']) ? (string) $d['assign_kind'] : ($site > 0 ? 'site' : 'project'),
                'project_id'   => $proj > 0 ? $proj : null,
                'site_id'      => $site > 0 ? $site : null,
                'unit_ref'     => isset($d['unit_ref']) ? (string) $d['unit_ref'] : '',
                'valid_from'   => $from,
                'state'        => 'active',
                'assigned_by'  => (isset($d['assigned_by']) && (int) $d['assigned_by'] > 0) ? (int) $d['assigned_by'] : null,
                'assigned_at'  => self::now(),
                'decision_ref' => isset($d['decision_ref']) ? (string) $d['decision_ref'] : '',
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر الإسناد'; return $out; }
        $out['ok'] = true; $out['code'] = 201; $out['assign_id'] = $id; $out['created'] = true;
        self::emit($gate, 'fleet.asset.assigned', 'asset_assignment', $id,
                   array('equipment_id' => $eq, 'site_id' => $site, 'project_id' => $proj, 'valid_from' => $from),
                   'assign:' . $id);
        return $out;
    }

    /** خروجُ الأصل — مؤقّتٌ بعودةٍ متوقَّعةٍ أو دائمٌ بمرجعٍ ماليّ */
    public static function exitAsset(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'exit_id' => 0, 'created' => false);
        $eq = isset($d['equipment_id']) ? (int) $d['equipment_id'] : 0;
        $kind = (isset($d['exit_kind']) && $d['exit_kind'] === 'permanent') ? 'permanent' : 'temporary';
        $date = isset($d['exit_date']) ? (string) $d['exit_date'] : '';
        $reason = isset($d['reason_code']) ? (string) $d['reason_code'] : '';
        if ($eq <= 0)   { $out['code'] = 422; $out['reason'] = 'لا خروج بلا أصل'; return $out; }
        if (!self::isDate($date)) { $out['code'] = 422; $out['reason'] = 'تاريخ الخروج إلزامي'; return $out; }
        if ($reason === '') { $out['code'] = 422; $out['reason'] = 'لا خروج بلا سبب مرمز'; return $out; }
        $expected = (isset($d['expected_return']) && self::isDate($d['expected_return'])) ? (string) $d['expected_return'] : null;
        $fin = isset($d['finance_ref']) ? (string) $d['finance_ref'] : '';
        if ($kind === 'temporary' && $expected === null) {
            $out['code'] = 422; $out['reason'] = 'الخروج المؤقت بلا عودة متوقعة خروج دائم باسم آخر'; return $out;
        }
        if ($kind === 'permanent') {
            $expected = null;
            if ($fin === '') { $out['code'] = 422; $out['reason'] = 'الخروج الدائم بلا مرجع مالي من المالية لا يسجل'; return $out; }
        }
        $ex = $gate->selectOne('asset_exit', array(
            'where' => array('equipment_id' => $eq, 'exit_kind' => $kind, 'exit_date' => $date)));
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['exit_id'] = (int) $ex['id'];
            $out['reason'] = 'الخروج مسجل سلفا — عطالة بالمفتاح'; return $out;
        }
        $id = 0;
        try {
            $id = (int) $gate->insert('asset_exit', array(
                'equipment_id'    => $eq,
                'exit_kind'       => $kind,
                'reason_code'     => $reason,
                'exit_date'       => $date,
                'expected_return' => $expected,
                'disposal_kind'   => isset($d['disposal_kind']) ? (string) $d['disposal_kind'] : '',
                'finance_ref'     => $fin,
                'state'           => 'open',
                'decided_by'      => (isset($d['decided_by']) && (int) $d['decided_by'] > 0) ? (int) $d['decided_by'] : null,
                'decided_at'      => self::now(),
                'doc_ref'         => isset($d['doc_ref']) ? (string) $d['doc_ref'] : '',
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر تسجيل الخروج'; return $out; }
        /* الإسنادُ النشِطُ يُنهى بالخروج — لا أصلَ في موقعٍ وهو خارجٌ منه */
        foreach ($gate->select('asset_assignment', array('columns' => array('id', 'valid_from'),
                 'where' => array('equipment_id' => $eq, 'state' => 'active'))) as $p) {
            if ((string) $p['valid_from'] > $date) { continue; }
            $gate->update('asset_assignment',
                array('state' => 'ended', 'valid_to' => $date, 'end_reason' => 'خروج الأصل ' . $kind . ' بسبب ' . $reason),
                array('id' => (int) $p['id']));
        }
        $gate->update('equipments',
            array('lifecycle_state' => ($kind === 'permanent') ? 'retired' : 'out_temporary',
                  'lifecycle_rule'  => 'W5_LIFECYCLE_FROM_EXIT_RECORD'),
            array('id' => $eq));
        $out['ok'] = true; $out['code'] = 201; $out['exit_id'] = $id; $out['created'] = true;
        self::emit($gate, 'fleet.asset.exited', 'asset_exit', $id,
                   array('equipment_id' => $eq, 'exit_kind' => $kind, 'exit_date' => $date, 'reason_code' => $reason),
                   'exit:' . $id);
        return $out;
    }

    /** عودةُ الأصلِ من خروجٍ مؤقّت — والدائمُ لا يعود */
    public static function returnAsset(TenantDb $gate, $exitId, $returnDate, $actorId)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'reason_code' => '');
        $exitId = (int) $exitId;
        $e = $gate->selectOne('asset_exit', array('where' => array('id' => $exitId)));
        if (!$e) { $out['code'] = 404; $out['reason'] = 'واقعة الخروج غير موجودة'; return $out; }
        if ($e['exit_kind'] !== 'temporary') {
            $out['code'] = 409; $out['reason_code'] = 'PERMANENT_EXIT_NO_RETURN';
            $out['reason'] = 'الخروج الدائم لا عودة منه — والعودة كرت جديد بطلب إدخال جديد'; return $out;
        }
        if ($e['state'] === 'returned') { $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'عاد سلفا — عطالة بالحالة'; return $out; }
        if (!self::isDate($returnDate)) { $out['code'] = 422; $out['reason'] = 'تاريخ العودة إلزامي'; return $out; }
        $gate->update('asset_exit',
            array('state' => 'returned', 'actual_return' => (string) $returnDate,
                  'decided_by' => (int) $actorId, 'decided_at' => self::now()),
            array('id' => $exitId));
        $gate->update('equipments', array('lifecycle_state' => 'active', 'lifecycle_rule' => 'W5_LIFECYCLE_FROM_EXIT_RETURN'),
                      array('id' => (int) $e['equipment_id']));
        $out['ok'] = true; $out['code'] = 200;
        self::emit($gate, 'fleet.asset.returned', 'asset_exit', $exitId,
                   array('equipment_id' => (int) $e['equipment_id'], 'actual_return' => (string) $returnDate),
                   'return:' . $exitId);
        return $out;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ④ المشتقّات — الحالةُ والجاهزيّة
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * حالةُ الأصلِ مشتقّةٌ من وقائعِه بترتيبِ الدورةِ لا بترتيبِ الكتابة.
     * @return array [state, rule]
     */
    public static function deriveLifecycle(TenantDb $gate, $equipmentId, $asOf = '')
    {
        $eq = (int) $equipmentId;
        $asOf = self::isDate($asOf) ? (string) $asOf : \date('Y-m-d');

        $exits = $gate->select('asset_exit', array(
            'columns' => array('exit_kind', 'exit_date', 'state', 'actual_return'),
            'where'   => array('equipment_id' => $eq), 'orderBy' => 'exit_date DESC'));
        foreach ($exits as $x) {
            if ((string) $x['exit_date'] > $asOf) { continue; }
            if ($x['exit_kind'] === 'permanent') { return array('retired', 'W5_LIFECYCLE_FROM_EXIT_RECORD'); }
            if ($x['state'] !== 'returned' || (string) $x['actual_return'] > $asOf) {
                return array('out_temporary', 'W5_LIFECYCLE_FROM_EXIT_RECORD');
            }
            break;
        }
        $card = $gate->selectOne('equipments', array('columns' => array('intake_id', 'card_state'),
                                                     'where' => array('id' => $eq)));
        if ($card && (int) $card['intake_id'] > 0) {
            $intake = $gate->selectOne('asset_intake', array('columns' => array('state'),
                                                             'where' => array('id' => (int) $card['intake_id'])));
            if ($intake) {
                $map = array('activated' => 'active', 'card_issued' => 'card_issued', 'rejected' => 'rejected');
                $s = isset($map[$intake['state']]) ? $map[$intake['state']] : 'in_intake';
                return array($s, 'W5_LIFECYCLE_FROM_INTAKE_STATE');
            }
        }
        /* أصلٌ سابقٌ للسجلِّ — حالتُه من كرتِه الحيِّ لا مخترَعة */
        $cs = ($card && $card['card_state'] === 'active') ? 'active' : 'card_draft';
        return array($cs, 'W5_LIFECYCLE_FROM_LEGACY_CARD_STATE');
    }

    /**
     * الجاهزيّةُ الشهريّةُ لأصلٍ — **مشتقّةٌ بالكامل ولا تُدخَل** (`FLEET-20`).
     * والمصادرُ مكتوبةٌ في الصفِّ نفسِه، والقاعدةُ معها — فلا رقمَ بلا قاعدة.
     * @param array $m ['shift'=>, 'executed'=>, 'standby'=>, 'fault'=>, 'stop'=>] مقيسةً من مصادرِها
     */
    public static function writeReadiness(TenantDb $gate, $equipmentId, $period, array $m)
    {
        $eq = (int) $equipmentId;
        $shift    = \round((float) $m['shift'], 2);
        $executed = \round((float) $m['executed'], 2);
        $standby  = \round((float) $m['standby'], 2);
        $fault    = \round((float) $m['fault'], 2);
        $stop     = \round((float) $m['stop'], 2);
        $r = self::readinessFormula($shift, $executed, $standby, $fault, $stop);
        list($lc, ) = self::deriveLifecycle($gate, $eq, self::periodEnd($period));

        $row = array(
            'equipment_id'    => $eq,
            'period'          => (string) $period,
            'shift_hours'     => $shift,
            'executed_hours'  => $executed,
            'standby_hours'   => $standby,
            'fault_hours'     => $fault,
            'stop_hours'      => $stop,
            'readiness_pct'   => $r['readiness'],
            'utilization_pct' => $r['utilization'],
            'lifecycle_state' => $lc,
            'derivation_rule' => 'W5_READINESS_FROM_SHIFT_MINUS_DOWN',
            'derived_from'    => 'timesheet.shift_hours/executed_hours/standby_hours · timesheet.total_fault_hours · ops_stop_register.hours',
            'derived_at'      => self::now(),
        );
        $ex = $gate->selectOne('asset_readiness', array('columns' => array('id'),
                               'where' => array('equipment_id' => $eq, 'period' => (string) $period)));
        if ($ex) { $gate->update('asset_readiness', $row, array('id' => (int) $ex['id'])); return (int) $ex['id']; }
        try { return (int) $gate->insert('asset_readiness', $row); } catch (\Throwable $t) { return 0; }
    }

    /**
     * صيغةُ الجاهزيّة — **دالّةٌ واحدةٌ تستدعيها الأداةُ والبوّابةُ معًا**، فلا
     * تفترق قراءتان في ملفَّين (‏«عدّادٌ وعارضٌ يتفرّقان» — درسُ الحملة).
     */
    public static function readinessFormula($shift, $executed, $standby, $fault, $stop)
    {
        /* ⚠ **التدويرُ داخلَ الدالّةِ لا عند المُستدعي**: `timesheet.executed_hours`
             عمودٌ عائم، فمجموعُه يعود `21.109999999404`. ومَن دوَّر قبلَ الاستدعاء
             يحسب على `21.11` ومَن لم يدوِّر يحسب على الخام — فيفترق الرقمانِ عند
             المنزلةِ الثانية (‏52.78 مقابل 52.77) **بالصيغةِ نفسِها**. فالتدويرُ
             جزءٌ من الصيغةِ لا من نداءِ المُستدعي، وإلّا تفرَّق عدّادٌ وعارضٌ في
             ملفَّين (درسُ الحملة). */
        $shift    = \round((float) $shift, 2);
        $executed = \round((float) $executed, 2);
        $standby  = \round((float) $standby, 2);
        $fault    = \round((float) $fault, 2);
        $stop     = \round((float) $stop, 2);

        $down = \max($fault, $stop);          /* الأكبرُ لا المجموع: الواقعةُ واحدةٌ في سجلَّين (W04) */
        $base = $shift;
        if ($base <= 0) { $base = $executed + $standby + $down; }
        if ($base <= 0) { return array('readiness' => 0.00, 'utilization' => 0.00, 'down' => 0.00, 'base' => 0.00); }
        $ready = \max(0.0, $base - $down);
        return array(
            'readiness'   => \round(\min(100.0, ($ready / $base) * 100.0), 2),
            'utilization' => \round(\min(100.0, ($executed / $base) * 100.0), 2),
            'down'        => \round($down, 2),
            'base'        => \round($base, 2),
        );
    }

    /** حالةُ التغطيةِ مشتقّةٌ من المطلوبِ والمتوفّرِ — دالّةٌ واحدةٌ للأداةِ والبوّابة */
    public static function coverageFormula($required, $available)
    {
        $req = (int) $required; $av = (int) $available;
        $gap = \max($req - $av, 0);
        $sur = \max($av - $req, 0);
        $state = ($gap > 0) ? 'SHORTAGE' : (($sur > 0) ? 'SURPLUS' : 'BALANCED');
        return array('gap' => $gap, 'surplus' => $sur, 'state' => $state,
                     'rule' => 'W5_COVERAGE_REQUIRED_MINUS_AVAILABLE');
    }

    /* ═══════════════════════════════════════════════════════════════════════
       مسابرُ داخلية
       ═══════════════════════════════════════════════════════════════════════ */

    public static function periodEnd($period)
    {
        $p = (string) $period;
        if (!\preg_match('/^\d{4}-\d{2}$/', $p)) { return \date('Y-m-d'); }
        return \date('Y-m-t', \strtotime($p . '-01'));
    }

    private static function isDate($d)
    {
        return \is_string($d) && \preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;
    }

    /**
     * الطابعُ الزمنيُّ من ساعةِ خادمِ التطبيق — و`NOW()` لا تُدَسُّ قيمةً
     * نصّيّةً في كتابةٍ مربوطةٍ بـ`?` (تُكتب حرفًا لا دالّةً · W04).
     */
    private static function now()
    {
        return \date('Y-m-d H:i:s');
    }

    /**
     * الكيانُ القانونيُّ يُقرأ من الصفِّ نفسِه عبرَ البوابة.
     * ◆ **ولا يُؤخذ من سياقِ البوابةِ مباشرةً**: `TenantDb::$ctx` خاصٌّ بالتصميم
     *   — العزلُ مسؤوليّةُ البوابةِ وحدَها ولا يُقرأ من خارجِها. والصفُّ الذي
     *   كتبته البوابةُ يحمل الكيانَ الصحيحَ حتمًا، فقراءتُه أصدقُ من ادّعائه.
     */
    private static function companyOf(TenantDb $gate, $table, $id)
    {
        try {
            $r = $gate->selectOne($table, array('columns' => array('company_id'), 'where' => array('id' => (int) $id)));
            return $r ? (int) $r['company_id'] : 0;
        } catch (\Throwable $t) { return 0; }
    }

    /** جدولُ الكيانِ لكلِّ نوعٍ يُنشَر — النوعُ اسمُ الحدثِ والجدولُ موضعُ الصفّ */
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
                'category'        => 'fleet',
                'source_module'   => 'fleet',
                'entity_type'     => $entityType,
                'entity_id'       => (int) $entityId,
                'payload'         => $payload,
                'idempotency_key' => 'w5:' . $idem,
                'source_ref'      => 'AssetLifecycleService',
            ));
        } catch (\Throwable $t) { return null; }
    }
}
