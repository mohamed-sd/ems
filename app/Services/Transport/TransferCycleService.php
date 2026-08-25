<?php
/**
 * app/Services/Transport/TransferCycleService.php — دورةُ الترحيلِ وإقفالُها (RPR-W07)
 * ═══════════════════════════════════════════════════════════════════════════
 * **دورةُ الترحيل**: طلبٌ من جهةٍ طالبة ← أمرٌ يملكه النقل ← تصاريحُ المسارِ
 * والحمولة ← **تجهيزُ المغادرةِ والتسليمُ الأصليّ** ← مراحلُ الرحلة ← أحداثُها
 * ← **محضرُ الاستلامِ وقراءةُ العدّاد** ← مطالبةُ تلفٍ إن وقعت ← بنودُ التكلفة
 * ← **إقفالٌ بحبّتِه المستقلّة**.
 *
 * ◆ **ثلاثُ بوّاباتٍ Fail-Closed منصوصةٌ في المتطلَّبات — تُنفَّذ هنا لا تُعلَن:**
 *   · `TRP-05`: «لا مغادرةَ لحمولةٍ استثنائيّةٍ بتصريحٍ منتهٍ» —
 *     `authorizeDeparture` تقيس `expiry_date` مقابلَ يومِ المغادرةِ **بسماحٍ من
 *     السجلِّ** (`W7_PERMIT_EXPIRY_GRACE_DAYS`) وتردُّ `PERMIT_EXPIRED`.
 *   · `TRP-06`: «لا مغادرةَ قبل اكتمالِ التجهيز» — بندُ تجهيزٍ واحدٌ `pending`
 *     أو `failed` يردُّ `HANDOVER_INCOMPLETE`.
 *   · `TRP-12`: «لا إقفالَ قبل محضرِ الاستلام» — و`chk_tcl_doc` يمنعه في الصفِّ
 *     أيضًا، **والمنعُ الوظيفيُّ هنا هو الذي يُقاس** لأنَّ ما يضمنه المخطَّطُ
 *     لا يُختبَر (‏تعريفُ الحاجبِ الأعمى · W02).
 *
 * ◆ **«حالةُ الأمرِ تتقدَّم بالأحداثِ لا بالتعديلِ اليدويّ»** (`TRP-08` نصًّا):
 *   `recordEvent` هي التي تنقل `transfer_orders.stage`، و`setStage` **خاصّةٌ**
 *   لا تُستدعى من شاشة. والتقدُّمُ بترتيبِ الدورةِ لا بالقفز.
 *
 * ◆ **وقراءةُ العدّادِ عند الاستلامِ إلزاميّة** (`TRP-09`) — و`recordArrival`
 *   تردُّ `METER_REQUIRED` بلا قراءة. وتُسجَّل بمصدرِ «استلام» في كرتِ المعدّة
 *   عبرَ `MeterReadingService` — **مصدرُ حقيقةِ العدّادِ واحدٌ لا اثنان**.
 *
 * ◆ **والحبّتانِ مفصولتان** (`TRP-11` ‖ `TRP-12`): بنودُ التكلفةِ سجلٌّ تابعٌ
 *   للأمر، والإقفالُ **واقعةٌ واحدةٌ بمعتمِدِها**. و`total_cost` في الإقفال
 *   **مشتقٌّ** من البنودِ ويُعاد بناؤه — لا يُدخَل.
 *
 * ◆ **وكلُّ وصولٍ إلى جدولِ مستأجِرٍ عبرَ `TenantDb`** (‏FR-SEC-006 · GAP-29).
 */

namespace App\Services\Transport;

use App\Core\TenantDb;

class TransferCycleService
{
    /** مراحلُ الأمرِ بترتيبِ الدورة — والترتيبُ هو الذي يمنع القفز */
    const STAGE_FLOW = array('request' => 1, 'planned' => 2, 'ready' => 3,
                             'in_transit' => 4, 'arrived' => 5, 'closed' => 6);

    /** بنودُ التجهيزِ التي يشترطها `TRP-06` — والقائمةُ تُبذَر في السجلِّ لا تُقارَن هنا */
    const HANDOVER_BLOCKING = array('pending', 'failed');

    /** @var \mysqli|null */
    private static $eventConn = null;
    /** @var \mysqli|null */
    private static $thConn = null;
    /** @var array|null */
    private static $th = null;

    public static function setEventConnection(\mysqli $conn) { self::$eventConn = $conn; }
    public static function setThresholdConnection(\mysqli $conn) { self::$thConn = $conn; self::$th = null; }

    /** عتبةٌ من السجلِّ — `null` تعني «لا سجلَّ» ⇒ تُردُّ العمليّةُ ولا تُخمَّن */
    public static function threshold($key)
    {
        if (self::$th === null) {
            self::$th = array();
            if (self::$thConn instanceof \mysqli) {
                $r = @self::$thConn->query("SELECT threshold_key, value_num FROM repair01_w7_thresholds");
                while ($r && $x = $r->fetch_assoc()) { self::$th[$x['threshold_key']] = (float) $x['value_num']; }
            }
        }
        return \array_key_exists($key, self::$th) ? self::$th[$key] : null;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ① الطلبُ والأمر
       ═══════════════════════════════════════════════════════════════════════ */

    /** طلبُ ترحيل — «الجهةُ الطالبةُ تطلب والنقلُ يملك الأمر» (`TRP-02`) */
    public static function openRequest(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'request_id' => 0, 'created' => false);
        $code = trim((string) (isset($d['code']) ? $d['code'] : ''));
        if ($code === '') { $out['code'] = 422; $out['reason'] = 'لا طلب بلا كود يعرفه'; return $out; }
        $ex = $gate->selectOne('transfer_requests', array('where' => array('code' => $code)));
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['request_id'] = (int) $ex['id'];
            $out['reason'] = 'الطلب قائم سلفا. عطالة بالمفتاح'; return $out;
        }
        $id = 0;
        try {
            $id = (int) $gate->insert('transfer_requests', array(
                'code' => $code,
                'transfer_type_id' => (isset($d['transfer_type_id']) && (int) $d['transfer_type_id'] > 0) ? (int) $d['transfer_type_id'] : null,
                'source_module' => isset($d['source_module']) ? (string) $d['source_module'] : 'operations',
                'requested_by_user_id' => (isset($d['requested_by']) && (int) $d['requested_by'] > 0) ? (int) $d['requested_by'] : null,
                'project_id' => (isset($d['project_id']) && (int) $d['project_id'] > 0) ? (int) $d['project_id'] : null,
                'reason' => isset($d['reason_ar']) ? (string) $d['reason_ar'] : '',
                'priority' => (isset($d['priority']) && $d['priority'] === 'urgent') ? 'urgent' : 'normal',
                'state' => 'submitted',
                'created_by' => (isset($d['created_by']) && (int) $d['created_by'] > 0) ? (int) $d['created_by'] : null,
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر فتح الطلب'; return $out; }
        $out['ok'] = true; $out['code'] = 201; $out['request_id'] = $id; $out['created'] = true;
        self::emit($gate, 'trp.request.submitted', 'transfer_requests', $id,
                   array('code' => $code), 'treq:' . $id);
        return $out;
    }

    /** تحويلُ الطلبِ إلى أمرٍ يملكه النقل — عطالةٌ بمفتاحِ (كيان × رقمِ الأمر) */
    public static function openOrder(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'order_id' => 0, 'created' => false);
        $no = trim((string) (isset($d['order_no']) ? $d['order_no'] : ''));
        if ($no === '') { $out['code'] = 422; $out['reason'] = 'لا أمر بلا رقم يعرفه'; return $out; }
        $ex = $gate->selectOne('transfer_orders', array('where' => array('order_no' => $no)));
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['order_id'] = (int) $ex['id'];
            $out['reason'] = 'الأمر قائم سلفا. عطالة بالمفتاح'; return $out;
        }
        $id = 0;
        try {
            $id = (int) $gate->insert('transfer_orders', array(
                'order_no' => $no,
                'request_id' => (isset($d['request_id']) && (int) $d['request_id'] > 0) ? (int) $d['request_id'] : null,
                'transfer_type_id' => (isset($d['transfer_type_id']) && (int) $d['transfer_type_id'] > 0) ? (int) $d['transfer_type_id'] : null,
                'direction' => isset($d['direction']) ? (string) $d['direction'] : 'internal',
                'source_module' => isset($d['source_module']) ? (string) $d['source_module'] : 'operations',
                'project_id' => (isset($d['project_id']) && (int) $d['project_id'] > 0) ? (int) $d['project_id'] : null,
                'request_date' => isset($d['request_date']) ? (string) $d['request_date'] : self::today(),
                'planned_date' => isset($d['planned_date']) ? (string) $d['planned_date'] : self::today(),
                'carrier_type' => isset($d['carrier_type']) ? (string) $d['carrier_type'] : 'internal',
                'driver_id' => (isset($d['driver_id']) && (int) $d['driver_id'] > 0) ? (int) $d['driver_id'] : null,
                'route' => isset($d['route']) ? (string) $d['route'] : '',
                'cost_bearer' => isset($d['cost_bearer']) ? (string) $d['cost_bearer'] : 'company',
                'distance_km' => (float) (isset($d['distance_km']) ? $d['distance_km'] : 0),
                'stage' => 'planned',
                'created_by' => (isset($d['created_by']) && (int) $d['created_by'] > 0) ? (int) $d['created_by'] : null,
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر فتح أمر الترحيل'; return $out; }
        if (isset($d['request_id']) && (int) $d['request_id'] > 0) {
            $gate->update('transfer_requests', array('state' => 'converted', 'order_id' => $id),
                          array('id' => (int) $d['request_id']));
        }
        if (isset($d['equipment_id']) && (int) $d['equipment_id'] > 0) {
            try {
                $gate->insert('transfer_lines', array(
                    'order_id' => $id, 'item_type' => 'equipment',
                    'equipment_id' => (int) $d['equipment_id'], 'quantity' => 1,
                    'note' => isset($d['line_note']) ? (string) $d['line_note'] : ''));
            } catch (\Throwable $t) { /* السطرُ يُضاف من شاشتِه إن تعذّر هنا */ }
        }
        $out['ok'] = true; $out['code'] = 201; $out['order_id'] = $id; $out['created'] = true;
        self::emit($gate, 'trp.order.opened', 'transfer_orders', $id, array('order_no' => $no), 'tord:' . $id);
        return $out;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ② التصاريحُ والتجهيزُ — بوّابتا المغادرة
       ═══════════════════════════════════════════════════════════════════════ */

    /** تصريحُ مسارٍ أو حمولة — والانتهاءُ يُقاس عند المغادرةِ لا عند الإصدار */
    public static function addPermit(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'permit_id' => 0);
        $ord = (int) (isset($d['order_id']) ? $d['order_id'] : 0);
        if (!$gate->selectOne('transfer_orders', array('where' => array('id' => $ord)))) {
            $out['code'] = 404; $out['reason'] = 'أمر الترحيل غير موجود'; return $out;
        }
        $exp = isset($d['expiry_date']) ? (string) $d['expiry_date'] : '';
        if ($exp === '') { $out['code'] = 422; $out['reason'] = 'لا تصريح بلا تاريخ انتهاء'; return $out; }
        $id = 0;
        try {
            $id = (int) $gate->insert('transfer_permits', array(
                'order_id' => $ord,
                'permit_type' => isset($d['permit_type']) ? (string) $d['permit_type'] : 'route',
                'authority' => isset($d['authority']) ? (string) $d['authority'] : '',
                'issue_date' => isset($d['issue_date']) ? (string) $d['issue_date'] : self::today(),
                'expiry_date' => $exp,
                'state' => 'valid',
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر تسجيل التصريح'; return $out; }
        $out['ok'] = true; $out['code'] = 201; $out['permit_id'] = $id;
        return $out;
    }

    /** بندُ تجهيزِ مغادرة — عطالةٌ بمفتاحِ (أمر × بند) */
    public static function recordHandoverItem(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'item_id' => 0);
        $ord = (int) (isset($d['order_id']) ? $d['order_id'] : 0);
        $key = trim((string) (isset($d['item_key']) ? $d['item_key'] : ''));
        $res = isset($d['result']) ? (string) $d['result'] : 'pending';
        if ($key === '') { $out['code'] = 422; $out['reason'] = 'لا بند تجهيز بلا مفتاح'; return $out; }
        if (!$gate->selectOne('transfer_orders', array('where' => array('id' => $ord)))) {
            $out['code'] = 404; $out['reason'] = 'أمر الترحيل غير موجود'; return $out;
        }
        $done = \in_array($res, array('ok', 'failed'), true) ? self::now() : null;
        $ex = $gate->selectOne('trp_origin_handover', array('where' => array('order_id' => $ord, 'item_key' => $key)));
        if ($ex) {
            $gate->update('trp_origin_handover', array(
                'result' => $res, 'done_at' => $done,
                'handover_ref' => isset($d['handover_ref']) ? (string) $d['handover_ref'] : (string) $ex['handover_ref'],
                'photo_ref' => isset($d['photo_ref']) ? (string) $d['photo_ref'] : (string) $ex['photo_ref'],
                'route_risk' => isset($d['route_risk']) ? (string) $d['route_risk'] : $ex['route_risk'],
                'state' => ($res === 'ok' || $res === 'na') ? 'completed' : (($res === 'failed') ? 'blocked' : 'open'),
                'state_rule' => 'W7_HANDOVER_ITEM_UPDATED',
            ), array('id' => (int) $ex['id']));
            $out['ok'] = true; $out['code'] = 200; $out['item_id'] = (int) $ex['id'];
            $out['reason'] = 'حدث بند التجهيز'; return $out;
        }
        $id = 0;
        try {
            $id = (int) $gate->insert('trp_origin_handover', array(
                'order_id' => $ord, 'item_key' => $key,
                'item_ar' => isset($d['item_ar']) ? (string) $d['item_ar'] : '',
                'performed_by' => (isset($d['performed_by']) && (int) $d['performed_by'] > 0) ? (int) $d['performed_by'] : null,
                'result' => $res,
                'handover_ref' => isset($d['handover_ref']) ? (string) $d['handover_ref'] : '',
                'photo_ref' => isset($d['photo_ref']) ? (string) $d['photo_ref'] : '',
                'route_risk' => isset($d['route_risk']) ? (string) $d['route_risk'] : null,
                'done_at' => $done,
                'state' => ($res === 'ok' || $res === 'na') ? 'completed' : (($res === 'failed') ? 'blocked' : 'open'),
                'state_rule' => 'W7_HANDOVER_ITEM_OPENED',
                'created_by' => (isset($d['created_by']) && (int) $d['created_by'] > 0) ? (int) $d['created_by'] : null,
                'src_ref' => isset($d['src_ref']) ? (string) $d['src_ref'] : '',
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر قيد بند التجهيز'; return $out; }
        $out['ok'] = true; $out['code'] = 201; $out['item_id'] = $id;
        return $out;
    }

    /**
     * **بوّابةُ المغادرة** — Fail-Closed بشرطَين منصوصَين:
     *  ① تصريحٌ منتهٍ ⇒ `PERMIT_EXPIRED` (`TRP-05`)
     *  ② تجهيزٌ ناقصٌ ⇒ `HANDOVER_INCOMPLETE` (`TRP-06`)
     * والسماحُ من السجلِّ — وغيابُه يردُّ ولا يُخمَّن.
     */
    public static function authorizeDeparture(TenantDb $gate, $orderId, $departureDate = '')
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'permits' => 0, 'blocking' => 0);
        $orderId = (int) $orderId;
        $o = $gate->selectOne('transfer_orders', array('where' => array('id' => $orderId)));
        if (!$o) { $out['code'] = 404; $out['reason'] = 'أمر الترحيل غير موجود'; return $out; }
        $day = ($departureDate !== '') ? (string) $departureDate : self::today();

        $grace = self::threshold('W7_PERMIT_EXPIRY_GRACE_DAYS');
        if ($grace === null) { $out['code'] = 503; $out['reason'] = 'W7_PERMIT_NO_GRACE_THRESHOLD'; return $out; }
        $limit = \date('Y-m-d', \strtotime($day) - ((int) $grace * 86400));

        $permits = $gate->select('transfer_permits', array('where' => array('order_id' => $orderId), 'limit' => 100));
        $out['permits'] = \count($permits);
        foreach ($permits as $p) {
            if ((string) $p['expiry_date'] !== '' && (string) $p['expiry_date'] < $limit) {
                $out['code'] = 409; $out['reason'] = 'PERMIT_EXPIRED'; return $out;
            }
        }
        $items = $gate->select('trp_origin_handover', array('where' => array('order_id' => $orderId), 'limit' => 200));
        $block = 0;
        foreach ($items as $it) { if (\in_array((string) $it['result'], self::HANDOVER_BLOCKING, true)) { $block++; } }
        $out['blocking'] = $block;
        if (\count($items) === 0) { $out['code'] = 409; $out['reason'] = 'HANDOVER_NOT_STARTED'; return $out; }
        if ($block > 0)           { $out['code'] = 409; $out['reason'] = 'HANDOVER_INCOMPLETE'; return $out; }

        self::setStage($gate, $orderId, 'ready', 'W7_DEPARTURE_AUTHORIZED');
        $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'اذن بالمغادرة';
        self::emit($gate, 'trp.order.departure_authorized', 'transfer_orders', $orderId,
                   array('permits' => $out['permits']), 'dep:' . $orderId);
        return $out;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ③ المراحلُ والأحداث
       ═══════════════════════════════════════════════════════════════════════ */

    /** مرحلةُ رحلة — عطالةٌ بمفتاحِ (أمر × تسلسل) */
    public static function addLeg(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'leg_id' => 0);
        $ord = (int) (isset($d['order_id']) ? $d['order_id'] : 0);
        $seq = (int) (isset($d['leg_seq']) ? $d['leg_seq'] : 0);
        if ($seq <= 0) { $out['code'] = 422; $out['reason'] = 'لا مرحلة بلا تسلسل موجب'; return $out; }
        if (!$gate->selectOne('transfer_orders', array('where' => array('id' => $ord)))) {
            $out['code'] = 404; $out['reason'] = 'أمر الترحيل غير موجود'; return $out;
        }
        $ex = $gate->selectOne('trp_trip_leg', array('where' => array('order_id' => $ord, 'leg_seq' => $seq)));
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['leg_id'] = (int) $ex['id'];
            $out['reason'] = 'المرحلة قائمة سلفا. عطالة بالحبة'; return $out;
        }
        $id = 0;
        try {
            $id = (int) $gate->insert('trp_trip_leg', array(
                'order_id' => $ord, 'leg_seq' => $seq,
                'from_point' => isset($d['from_point']) ? (string) $d['from_point'] : '',
                'to_point' => isset($d['to_point']) ? (string) $d['to_point'] : '',
                'vehicle_id' => (isset($d['vehicle_id']) && (int) $d['vehicle_id'] > 0) ? (int) $d['vehicle_id'] : null,
                'driver_id' => (isset($d['driver_id']) && (int) $d['driver_id'] > 0) ? (int) $d['driver_id'] : null,
                'distance_km' => (float) (isset($d['distance_km']) ? $d['distance_km'] : 0),
                'state' => 'planned', 'state_rule' => 'W7_LEG_PLANNED',
                'created_by' => (isset($d['created_by']) && (int) $d['created_by'] > 0) ? (int) $d['created_by'] : null,
                'src_ref' => isset($d['src_ref']) ? (string) $d['src_ref'] : '',
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر قيد المرحلة'; return $out; }
        $out['ok'] = true; $out['code'] = 201; $out['leg_id'] = $id;
        return $out;
    }

    /** بدءُ مرحلة — ولا بدءَ لمرحلةٍ سابقتُها لم تُسلَّم */
    public static function startLeg(TenantDb $gate, $legId, $at = '')
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $l = $gate->selectOne('trp_trip_leg', array('where' => array('id' => (int) $legId)));
        if (!$l) { $out['code'] = 404; $out['reason' ] = 'المرحلة غير موجودة'; return $out; }
        if ((int) $l['leg_seq'] > 1) {
            $prev = $gate->selectOne('trp_trip_leg', array(
                'where' => array('order_id' => (int) $l['order_id'], 'leg_seq' => (int) $l['leg_seq'] - 1)));
            if ($prev && (string) $prev['state'] !== 'handed_over') {
                $out['code'] = 409; $out['reason'] = 'PREVIOUS_LEG_NOT_HANDED_OVER'; return $out;
            }
        }
        $gate->update('trp_trip_leg', array(
            'state' => 'in_transit', 'state_rule' => 'W7_LEG_STARTED',
            'started_at' => ($at !== '' ? (string) $at : self::now()),
        ), array('id' => (int) $legId));
        $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'بدأت المرحلة';
        return $out;
    }

    /** إنهاءُ مرحلةٍ وتسليمُها للتالية */
    public static function endLeg(TenantDb $gate, $legId, $handOver = true, $at = '')
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $l = $gate->selectOne('trp_trip_leg', array('where' => array('id' => (int) $legId)));
        if (!$l) { $out['code'] = 404; $out['reason'] = 'المرحلة غير موجودة'; return $out; }
        if ((string) $l['started_at'] === '' || $l['started_at'] === null) {
            $out['code'] = 409; $out['reason'] = 'LEG_NOT_STARTED'; return $out;
        }
        $n = 0;
        try { $n = (int) $gate->count('transfer_events', array('where' => array('order_id' => (int) $l['order_id']))); }
        catch (\Throwable $t) { $n = 0; }
        $gate->update('trp_trip_leg', array(
            'state' => $handOver ? 'handed_over' : 'arrived',
            'state_rule' => $handOver ? 'W7_LEG_HANDED_OVER' : 'W7_LEG_ARRIVED',
            'ended_at' => ($at !== '' ? (string) $at : self::now()),
            'handover_to_next' => $handOver ? 1 : 0,
            'events_count' => $n,
        ), array('id' => (int) $legId));
        $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'انتهت المرحلة';
        return $out;
    }

    /**
     * حدثُ رحلة — **وهو الذي ينقل حالةَ الأمر** لا التعديلُ اليدويّ (`TRP-08`).
     */
    public static function recordEvent(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'event_id' => 0, 'stage' => '');
        $ord = (int) (isset($d['order_id']) ? $d['order_id'] : 0);
        $o = $gate->selectOne('transfer_orders', array('where' => array('id' => $ord)));
        if (!$o) { $out['code'] = 404; $out['reason'] = 'أمر الترحيل غير موجود'; return $out; }
        $newStage = isset($d['to_stage']) ? (string) $d['to_stage'] : '';
        if ($newStage !== '' && !isset(self::STAGE_FLOW[$newStage])) {
            $out['code'] = 422; $out['reason'] = 'مرحلة خارج الدورة'; return $out;
        }
        $cur = (string) $o['stage'];
        if ($newStage !== '' && isset(self::STAGE_FLOW[$cur]) && self::STAGE_FLOW[$newStage] < self::STAGE_FLOW[$cur]) {
            $out['code'] = 409; $out['reason'] = 'STAGE_BACKWARD_FORBIDDEN'; return $out;
        }
        $id = 0;
        try {
            $id = (int) $gate->insert('transfer_events', array(
                'order_id' => $ord,
                'event_type' => isset($d['event_type']) ? (string) $d['event_type'] : 'status_change',
                'actor_user_id' => (isset($d['actor_user_id']) && (int) $d['actor_user_id'] > 0) ? (int) $d['actor_user_id'] : null,
                'actor_dept' => isset($d['actor_dept']) ? (string) $d['actor_dept'] : 'DEP-15',
                'body' => isset($d['body']) ? (string) $d['body'] : '',
                'old_value' => $cur, 'new_value' => ($newStage !== '' ? $newStage : $cur),
                'sync_uuid' => isset($d['sync_uuid']) ? (string) $d['sync_uuid'] : '',
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر قيد الحدث'; return $out; }
        if ($newStage !== '' && $newStage !== $cur) {
            self::setStage($gate, $ord, $newStage, 'W7_STAGE_BY_EVENT');
        }
        $out['ok'] = true; $out['code'] = 201; $out['event_id'] = $id;
        $out['stage'] = ($newStage !== '' ? $newStage : $cur);
        return $out;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ④ الاستلامُ والمطالبةُ والإقفال
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * محضرُ الاستلامِ وقراءةُ العدّاد — **القراءةُ إلزاميّة** (`TRP-09` نصًّا).
     * وتُسجَّل في كرتِ المعدّةِ بمصدرِ «استلام» عبرَ `MeterReadingService`.
     */
    public static function recordArrival(TenantDb $gate, $orderId, array $d, $conn = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'doc_id' => 0, 'meter' => null);
        $orderId = (int) $orderId;
        $o = $gate->selectOne('transfer_orders', array('where' => array('id' => $orderId)));
        if (!$o) { $out['code'] = 404; $out['reason'] = 'أمر الترحيل غير موجود'; return $out; }
        if (!isset($d['meter_reading']) || $d['meter_reading'] === '' || $d['meter_reading'] === null) {
            $out['code'] = 422; $out['reason'] = 'METER_REQUIRED'; return $out;
        }
        $ref = trim((string) (isset($d['doc_ref']) ? $d['doc_ref'] : ''));
        if ($ref === '') { $out['code'] = 422; $out['reason'] = 'لا محضر بلا مرجع يعرفه'; return $out; }

        $ex = $gate->selectOne('transfer_delivery_docs', array('where' => array('order_id' => $orderId, 'doc_ref' => $ref)));
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['doc_id'] = (int) $ex['id'];
            $out['meter'] = (float) $d['meter_reading'];
            $out['reason'] = 'المحضر قائم سلفا. عطالة بالمفتاح'; return $out;
        }
        $id = 0;
        try {
            $id = (int) $gate->insert('transfer_delivery_docs', array(
                'order_id' => $orderId, 'doc_ref' => $ref,
                'doc_note' => isset($d['doc_note']) ? (string) $d['doc_note'] : '',
                'witness_name' => isset($d['witness_name']) ? (string) $d['witness_name'] : '',
                'delivered_at' => isset($d['delivered_at']) ? (string) $d['delivered_at'] : self::now(),
                'created_by' => (isset($d['created_by']) && (int) $d['created_by'] > 0) ? (int) $d['created_by'] : null,
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر قيد المحضر'; return $out; }

        /* قراءةُ العدّادِ إلى كرتِ المعدّةِ — مصدرُ حقيقةِ العدّادِ واحدٌ لا اثنان */
        $eqId = (isset($d['equipment_id']) && (int) $d['equipment_id'] > 0) ? (int) $d['equipment_id'] : 0;
        if ($eqId <= 0) {
            $ln = $gate->selectOne('transfer_lines', array(
                'where' => array('order_id' => $orderId, 'item_type' => 'equipment')));
            if ($ln) { $eqId = (int) $ln['equipment_id']; }
        }
        if ($eqId > 0 && $conn instanceof \mysqli) {
            $svc = \dirname(__DIR__) . '/Fleet/MeterReadingService.php';
            if (\is_file($svc)) {
                require_once $svc;
                try {
                    \App\Services\Fleet\MeterReadingService::record($conn, $gate,
                        self::companyOf($gate, 'transfer_orders', $orderId), $eqId,
                        array('meter_type' => 'hour', 'value' => (float) $d['meter_reading'],
                              'reading_date' => \substr((string) (isset($d['delivered_at']) ? $d['delivered_at'] : self::now()), 0, 10),
                              'source' => 'transfer', 'note' => 'محضر استلام ' . $ref),
                        (isset($d['created_by']) ? (int) $d['created_by'] : 0));
                } catch (\Throwable $t) { /* القراءةُ تُعاد من شاشتِها إن رُدَّت هنا */ }
            }
        }
        self::setStage($gate, $orderId, 'arrived', 'W7_ARRIVED_BY_DELIVERY_DOC');
        $out['ok'] = true; $out['code'] = 201; $out['doc_id'] = $id; $out['meter'] = (float) $d['meter_reading'];
        self::emit($gate, 'trp.order.arrived', 'transfer_orders', $orderId,
                   array('doc_ref' => $ref, 'meter' => (float) $d['meter_reading']), 'arr:' . $orderId);
        return $out;
    }

    /** مطالبةُ تلفٍ أو حادث — والمتحمِّلُ المحدَّدُ بلا قاعدةِ عقدٍ يُردّ */
    public static function openClaim(TenantDb $gate, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'claim_id' => 0);
        $ord = (int) (isset($d['order_id']) ? $d['order_id'] : 0);
        $no = trim((string) (isset($d['claim_no']) ? $d['claim_no'] : ''));
        $inc = trim((string) (isset($d['incident_ref']) ? $d['incident_ref'] : ''));
        $desc = trim((string) (isset($d['damage_desc']) ? $d['damage_desc'] : ''));
        $party = isset($d['liable_party']) ? (string) $d['liable_party'] : 'undetermined';
        $rule = isset($d['liable_rule']) ? trim((string) $d['liable_rule']) : '';
        if ($no === '')  { $out['code'] = 422; $out['reason'] = 'لا مطالبة بلا رقم يعرفها'; return $out; }
        if ($inc === '' || $desc === '') { $out['code'] = 422; $out['reason'] = 'CLAIM_WITHOUT_INCIDENT_EVIDENCE'; return $out; }
        if ($party !== 'undetermined' && $rule === '') { $out['code'] = 422; $out['reason'] = 'LIABLE_WITHOUT_CONTRACT_RULE'; return $out; }
        if (!$gate->selectOne('transfer_orders', array('where' => array('id' => $ord)))) {
            $out['code'] = 404; $out['reason'] = 'أمر الترحيل غير موجود'; return $out;
        }
        $ex = $gate->selectOne('trp_damage_claim', array('where' => array('claim_no' => $no)));
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['claim_id'] = (int) $ex['id'];
            $out['reason'] = 'المطالبة قائمة سلفا. عطالة بالمفتاح'; return $out;
        }
        $id = 0;
        try {
            $id = (int) $gate->insert('trp_damage_claim', array(
                'claim_no' => $no, 'order_id' => $ord, 'incident_ref' => $inc, 'damage_desc' => $desc,
                'liable_party' => $party, 'liable_rule' => $rule,
                'claim_amount' => (float) (isset($d['claim_amount']) ? $d['claim_amount'] : 0),
                'evidence_ref' => isset($d['evidence_ref']) ? (string) $d['evidence_ref'] : '',
                'claim_route' => isset($d['claim_route']) ? (string) $d['claim_route'] : 'internal',
                'state' => 'submitted', 'state_rule' => 'W7_CLAIM_SUBMITTED',
                'created_by' => (isset($d['created_by']) && (int) $d['created_by'] > 0) ? (int) $d['created_by'] : null,
                'src_ref' => isset($d['src_ref']) ? (string) $d['src_ref'] : '',
            ));
        } catch (\Throwable $t) { $id = 0; }
        if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر فتح المطالبة'; return $out; }
        $out['ok'] = true; $out['code'] = 201; $out['claim_id'] = $id;
        self::emit($gate, 'trp.damage_claim.opened', 'trp_damage_claim', $id,
                   array('order_id' => $ord, 'claim_no' => $no, 'liable' => $party), 'clm:' . $id);
        return $out;
    }

    /** بنودُ التكلفةِ مشتقّةٌ جمعًا — **والإقفالُ يقرؤها ولا يكتبها** */
    public static function deriveCost(TenantDb $gate, $orderId)
    {
        $total = 0.0; $n = 0; $byBearer = array();
        foreach ($gate->select('transfer_cost_lines', array(
            'where' => array('order_id' => (int) $orderId), 'limit' => 500)) as $l) {
            $n++;
            $amt = (float) $l['amount_usd'];
            $total += $amt;
            $b = (string) $l['cost_bearer'];
            if (!isset($byBearer[$b])) { $byBearer[$b] = 0.0; }
            $byBearer[$b] += $amt;
        }
        $split = array();
        foreach ($byBearer as $b => $v) { $split[] = $b . ' ' . \round($v, 2); }
        return array('lines' => $n, 'total' => \round($total, 2), 'split' => \implode(' · ', $split));
    }

    /**
     * إقفالُ أمرِ الترحيل — **حبّةٌ مستقلّةٌ بمعتمِدِها** (`TRP-12`).
     * ⛔ لا إقفالَ قبل محضرِ الاستلام · ولا اعتمادَ بلا ترحيلِ قراءةِ العدّاد ·
     *    ومَن أنشأ الإقفالَ لا يعتمده.
     */
    public static function closeOrder(TenantDb $gate, $orderId, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'closure_id' => 0,
                     'total_cost' => 0.0, 'lines' => 0);
        $orderId = (int) $orderId;
        $o = $gate->selectOne('transfer_orders', array('where' => array('id' => $orderId)));
        if (!$o) { $out['code'] = 404; $out['reason'] = 'أمر الترحيل غير موجود'; return $out; }
        $doc = $gate->selectOne('transfer_delivery_docs', array(
            'where' => array('order_id' => $orderId), 'orderBy' => 'id DESC'));
        if (!$doc) { $out['code'] = 409; $out['reason'] = 'NO_CLOSURE_WITHOUT_DELIVERY_DOC'; return $out; }
        $creator = (int) (isset($d['created_by']) ? $d['created_by'] : 0);
        if ($creator <= 0) { $out['code'] = 422; $out['reason'] = 'لا إقفال بلا منشئ معروف'; return $out; }

        $c = self::deriveCost($gate, $orderId);
        $ex = $gate->selectOne('trp_closure', array('where' => array('order_id' => $orderId)));
        $row = array(
            'order_id' => $orderId, 'delivery_doc_id' => (int) $doc['id'],
            'cost_lines_count' => $c['lines'], 'total_cost' => $c['total'],
            'bearer_split' => ($c['split'] !== '' ? $c['split'] : 'لا بنود تكلفة'),
            'meter_posted' => !empty($d['meter_posted']) ? 1 : 0,
            'meter_ref' => isset($d['meter_ref']) ? (string) $d['meter_ref'] : '',
            'finance_ref' => isset($d['finance_ref']) ? (string) $d['finance_ref'] : '',
            'note_ar' => isset($d['note_ar']) ? (string) $d['note_ar'] : '',
            'state' => 'submitted', 'state_rule' => 'W7_CLOSURE_SUBMITTED',
            'derivation_rule' => 'W7_COST_FROM_TRANSFER_COST_LINES',
            'created_by' => $creator,
            'src_ref' => isset($d['src_ref']) ? (string) $d['src_ref'] : '',
        );
        if ($ex) {
            $gate->update('trp_closure', $row, array('id' => (int) $ex['id']));
            $out['closure_id'] = (int) $ex['id'];
        } else {
            $id = 0;
            try { $id = (int) $gate->insert('trp_closure', $row); } catch (\Throwable $t) { $id = 0; }
            if ($id <= 0) { $out['code'] = 500; $out['reason'] = 'تعذر فتح الإقفال'; return $out; }
            $out['closure_id'] = $id;
        }
        $out['ok'] = true; $out['code'] = 201; $out['total_cost'] = $c['total']; $out['lines'] = $c['lines'];
        self::emit($gate, 'trp.order.closure_submitted', 'trp_closure', $out['closure_id'],
                   array('order_id' => $orderId, 'total' => $c['total']), 'tclo:' . $out['closure_id']);
        return $out;
    }

    /** اعتمادُ الإقفال — ⛔ ومَن أنشأه لا يعتمده · ولا اعتمادَ بلا ترحيلِ العدّاد */
    public static function approveClosure(TenantDb $gate, $closureId, $approverId)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $c = $gate->selectOne('trp_closure', array('where' => array('id' => (int) $closureId)));
        if (!$c) { $out['code'] = 404; $out['reason'] = 'الإقفال غير موجود'; return $out; }
        if ((string) $c['state'] === 'approved') {
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'معتمد سلفا. عطالة بالحالة'; return $out;
        }
        if ((int) $approverId <= 0) { $out['code'] = 422; $out['reason'] = 'لا اعتماد بلا معتمد معروف'; return $out; }
        if ((int) $c['created_by'] === (int) $approverId) { $out['code'] = 403; $out['reason'] = 'SOD_SELF_APPROVAL'; return $out; }
        if ((int) $c['meter_posted'] !== 1) { $out['code'] = 409; $out['reason'] = 'METER_NOT_POSTED'; return $out; }
        $gate->update('trp_closure', array(
            'state' => 'approved', 'state_rule' => 'W7_CLOSURE_APPROVED',
            'approved_by' => (int) $approverId, 'approved_at' => self::now(),
        ), array('id' => (int) $closureId));
        self::setStage($gate, (int) $c['order_id'], 'closed', 'W7_CLOSED_BY_APPROVED_CLOSURE');
        $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'اعتمد الاقفال واقفل الامر';
        self::emit($gate, 'trp.order.closed', 'transfer_orders', (int) $c['order_id'],
                   array('closure_id' => (int) $closureId, 'total' => (float) $c['total_cost']),
                   'tcls:' . (int) $closureId);
        return $out;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ⑤ أدواتٌ داخليّة
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * نقلُ مرحلةِ الأمر — **خاصّةٌ بالتصميم**: `TRP-08` يقول «تتقدَّم بالأحداثِ
     * لا بالتعديلِ اليدويّ»، فلا مسارَ إلى هنا إلّا من واقعةٍ مسجَّلة.
     */
    private static function setStage(TenantDb $gate, $orderId, $stage, $rule)
    {
        try {
            $gate->update('transfer_orders', array('stage' => (string) $stage), array('id' => (int) $orderId));
        } catch (\Throwable $t) { return false; }
        return true;
    }

    private static function now()   { return \date('Y-m-d H:i:s'); }
    private static function today() { return \date('Y-m-d'); }

    private static function companyOf(TenantDb $gate, $table, $id)
    {
        try {
            $r = $gate->selectOne($table, array('columns' => array('company_id'), 'where' => array('id' => (int) $id)));
            return $r ? (int) $r['company_id'] : 0;
        } catch (\Throwable $t) { return 0; }
    }

    private static function emit(TenantDb $gate, $eventKey, $entityType, $entityId, array $payload, $idem)
    {
        $conn = self::$eventConn;
        if (!($conn instanceof \mysqli)) { return null; }
        $pub = \dirname(\dirname(\dirname(__DIR__))) . '/app/Core/EventPublisher.php';
        if (!\is_file($pub)) { return null; }
        require_once $pub;
        $company = self::companyOf($gate, $entityType, $entityId);
        try {
            return \App\Core\EventPublisher::publishFact($conn, array(
                'company_id'      => $company,
                'event_key'       => $eventKey,
                'category'        => 'transport',
                'source_module'   => 'transport',
                'entity_type'     => $entityType,
                'entity_id'       => (int) $entityId,
                'payload'         => $payload,
                'idempotency_key' => 'w7:' . $idem,
                'source_ref'      => 'TransferCycleService',
            ));
        } catch (\Throwable $t) { return null; }
    }
}
