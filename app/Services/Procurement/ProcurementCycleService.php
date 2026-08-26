<?php
/**
 * app/Services/Procurement/ProcurementCycleService.php — دورةُ الشراء (RPR-W09)
 * ═══════════════════════════════════════════════════════════════════════════
 * **من الطلبِ إلى الأمر**: طلبُ شراء ← حزمةُ تجميع ← طلبُ عروض ← دعوات ←
 * عروضٌ بمظاريفَ تُفتح في موعدِها ← محضرُ ترسيةٍ بمعاييرَ مُعلَنةٍ سلفًا ←
 * أمرُ شراءٍ **بسندِه التنافسيِّ أو بسببِ مباشرتِه** ← متابعةُ توريدٍ ←
 * مطابقةٌ ثلاثيّة.
 *
 * ◆ **والسندُ التنافسيُّ ليس زينةً**: `proc_order.award_minute_id` و`rfq_id`
 *   كانا **صفرًا في اثنَين وعشرين أمرًا من اثنَين وعشرين** — أي أنَّ كلَّ أمرِ
 *   شراءٍ في النظامِ بلا أثرٍ يربطه بالعرضِ الذي رساه. فـ`issueOrder` تردُّ
 *   `PO_WITHOUT_AWARD_NEEDS_REASON` على أمرٍ بلا محضرٍ وبلا سببِ مباشرةٍ مكتوب.
 *
 * ◆ **والمظروفُ لا يُقرأ قبل موعدِه** (`PRC-08`): `openEnvelopes` تردُّ
 *   `RFQ_NOT_DUE_NO_OPEN` قبل `open_at`، و`recordOffer` **توسم المتأخّرَ**
 *   `late=1` ولا تُسقطه صامتًا — فالمتأخّرُ يُرى ويُقرَّر فيه، ولا يُخفى.
 *
 * ◆ **والأرخصُ ليس حكمًا آليًّا ولا الأغلى مُحرَّمًا**: `awardRfq` تشتقُّ
 *   `lowest_id` قياسًا، فإن خالفه الفائزُ **وجب `award_why` مكتوبًا** — والردُّ
 *   `AWARD_NOT_LOWEST_NEEDS_REASON`. **والمعاييرُ تُعلَن قبل الفتحِ لا بعده**
 *   (`AWARD_WITHOUT_CRITERIA`) وإلّا صارت المعاييرُ وصفًا للفائزِ لا حكمًا عليه.
 *
 * ◆ **وفصلُ الواجباتِ يُنفَّذ لا يُعلَن**: مَن رسا لا يعتمد المحضرَ
 *   (`SAME_ACTOR_AWARD_AND_APPROVE`)، ومَن اعتمد المحضرَ لا يوقّع المطابقةَ
 *   الثلاثيّةَ للفاتورةِ نفسِها (`SAME_ACTOR_APPROVE_AND_MATCH`).
 *
 * ⛔ **ولا عتبةٌ رقميّةٌ في هذا الملفّ** — حدُّ الشراءِ المباشرِ وسماحُ فرقِ
 *   المطابقةِ ونافذةُ الردِّ كلُّها من `repair01_w9_thresholds`. وغيابُ السجلِّ
 *   **يردُّ العمليّةَ ولا يُخمّن قيمة** (`THRESHOLD_NOT_REGISTERED`).
 *
 * ◆ **وكلُّ وصولٍ إلى جدولِ مستأجِرٍ عبرَ `TenantDb`** — و`company_id`
 *   لا يُمرَّر من المُستدعي (‏FR-SEC-006 · GAP-29).
 */

namespace App\Services\Procurement;

use App\Core\TenantDb;

class ProcurementCycleService
{
    /** حالاتُ طلبِ العروضِ بترتيبِ الدورة — الترتيبُ هو الذي يمنع القفز */
    const RFQ_FLOW = array('draft', 'issued', 'closed', 'opened', 'awarded');

    /** @var \mysqli|null اتصالُ نشرِ الحقائقِ المحايدة (ADR-15) */
    private static $eventConn = null;
    /** @var \mysqli|null اتصالُ سجلِّ العتبات — دفترُ حملةٍ بلا كيانٍ قانونيّ */
    private static $thConn = null;
    /** @var array|null العتباتُ المقروءةُ من السجلّ */
    private static $th = null;

    public static function setEventConnection(\mysqli $conn) { self::$eventConn = $conn; }
    public static function setThresholdConnection(\mysqli $conn) { self::$thConn = $conn; self::$th = null; }

    /**
     * عتبةٌ من السجلِّ — **ولا قيمةٌ افتراضيّةٌ في الشيفرة**.
     * @return float|null `null` تعني «لا سجلَّ» ⇒ العمليّةُ تُردُّ.
     */
    public static function threshold($key)
    {
        if (self::$th === null) {
            self::$th = array();
            if (self::$thConn instanceof \mysqli) {
                $r = @self::$thConn->query("SELECT threshold_key, value_num FROM repair01_w9_thresholds");
                while ($r && $x = $r->fetch_assoc()) { self::$th[(string) $x['threshold_key']] = (float) $x['value_num']; }
            }
        }
        return array_key_exists($key, self::$th) ? self::$th[$key] : null;
    }

    /** ردٌّ موحَّدٌ — الرمزُ يُقارَن والرسالةُ تُعرَض */
    private static function fail($code, $detail = '')
    {
        return array('ok' => false, 'code' => $code, 'detail' => $detail);
    }
    private static function done(array $extra = array())
    {
        return array_merge(array('ok' => true, 'code' => ''), $extra);
    }

    /* ══════════════════════════════════════════════════════════════════════
       ① الحزمة — طلبٌ واحدٌ في حزمةٍ واحدةٍ ولا ضمَّ لمقفلة
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * ضمُّ طلبِ شراءٍ إلى حزمةٍ — والسببُ إلزاميٌّ لأنَّ التجميعَ قرارٌ لا صدفة.
     */
    public static function joinPackage(TenantDb $gate, $packageId, $requestId, $reason, $actorId)
    {
        $packageId = (int) $packageId; $requestId = (int) $requestId;
        $reason = trim((string) $reason);
        if ($reason === '') { return self::fail('PACKAGE_JOIN_WITHOUT_REASON', 'التجميع قرار فيلزمه سبب'); }

        $pkg = $gate->selectOne('proc_package', array('where' => array('id' => $packageId)));
        if (!$pkg) { return self::fail('PACKAGE_NOT_FOUND', ''); }
        if ((string) $pkg['state'] !== 'draft') {
            return self::fail('PACKAGE_CLOSED_NO_JOIN', 'الحزمة خرجت من التحرير فلا تضم إليها طلبات');
        }

        $already = $gate->selectOne('proc_package_member', array('where' => array('request_id' => $requestId)));
        if ($already) {
            return self::fail('REQUEST_ALREADY_PACKAGED', 'الطلب مضموم إلى حزمة أخرى');
        }

        $req = $gate->selectOne('proc_request', array('where' => array('id' => $requestId)));
        if (!$req) { return self::fail('REQUEST_NOT_FOUND', ''); }

        $out = null;
        $gate->runInTransaction(function (TenantDb $g) use ($packageId, $requestId, $reason, $actorId, &$out) {
            $g->insert('proc_package_member', array(
                'package_id' => $packageId, 'request_id' => $requestId,
                'join_reason' => $reason, 'joined_by' => (int) $actorId,
            ));
            self::recomputePackage($g, $packageId);
            $out = true;
        }, 'W09 ضم طلب إلى حزمة');

        if ($out !== true) { return self::fail('PACKAGE_JOIN_FAILED', ''); }
        self::emit($gate, 'PRC_PACKAGE_JOINED', array('package_id' => $packageId, 'request_id' => $requestId));
        return self::done(array('package_id' => $packageId));
    }

    /** عدّاداتُ الحزمةِ مشتقّةٌ من أعضائها — لا تُكتب بيد */
    public static function recomputePackage(TenantDb $gate, $packageId)
    {
        $packageId = (int) $packageId;
        $members = $gate->select('proc_package_member', array('where' => array('package_id' => $packageId)));
        $lines = 0; $value = 0.0;
        foreach ($members as $m) {
            $ls = $gate->select('proc_request_line', array('where' => array('request_id' => (int) $m['request_id'])));
            $lines += count($ls);
        }
        $gate->update('proc_package', array(
            'member_count' => count($members), 'line_count' => $lines, 'est_value' => $value,
        ), array('id' => $packageId));
        return array('members' => count($members), 'lines' => $lines);
    }

    /* ══════════════════════════════════════════════════════════════════════
       ② طلبُ العروضِ — المظروفُ لا يُقرأ قبل موعدِه
       ══════════════════════════════════════════════════════════════════════ */

    /** دعوةُ موردٍ — والاعتذارُ بسببٍ مكتوب، والصمتُ يُسجَّل صمتًا لا رفضًا */
    public static function inviteSupplier(TenantDb $gate, $rfqId, $supplierId, $channel, $actorId)
    {
        $rfqId = (int) $rfqId; $supplierId = (int) $supplierId;
        $rfq = $gate->selectOne('proc_rfq', array('where' => array('id' => $rfqId)));
        if (!$rfq) { return self::fail('RFQ_NOT_FOUND', ''); }
        if (in_array((string) $rfq['state'], array('opened', 'awarded'), true)) {
            return self::fail('RFQ_OPENED_NO_INVITE', 'فتحت المظاريف فلا تضاف دعوة');
        }
        $dup = $gate->selectOne('proc_rfq_invite', array('where' => array('rfq_id' => $rfqId, 'supplier_id' => $supplierId)));
        if ($dup) { return self::fail('SUPPLIER_ALREADY_INVITED', ''); }

        $gate->insert('proc_rfq_invite', array(
            'rfq_id' => $rfqId, 'supplier_id' => $supplierId,
            'channel' => (string) $channel, 'invited_by' => (int) $actorId, 'response' => 'silent',
        ));
        self::recomputeRfq($gate, $rfqId);
        self::emit($gate, 'PRC_RFQ_INVITED', array('rfq_id' => $rfqId, 'supplier_id' => $supplierId));
        return self::done();
    }

    /**
     * تسجيلُ عرضٍ مستلَم — **والمتأخّرُ يُوسَم ولا يُسقَط صامتًا**.
     * فالمتأخّرُ قرارٌ للجنةِ لا حذفٌ من الخدمة.
     */
    public static function recordOffer(TenantDb $gate, $rfqId, $supplierId, array $head, array $lines, $actorId)
    {
        $rfqId = (int) $rfqId; $supplierId = (int) $supplierId;
        $rfq = $gate->selectOne('proc_rfq', array('where' => array('id' => $rfqId)));
        if (!$rfq) { return self::fail('RFQ_NOT_FOUND', ''); }
        if ((string) $rfq['state'] === 'awarded') { return self::fail('RFQ_AWARDED_NO_OFFER', ''); }
        if (!$lines) { return self::fail('OFFER_WITHOUT_LINES', 'عرض بلا بنود ليس عرضا'); }

        $inv = $gate->selectOne('proc_rfq_invite', array('where' => array('rfq_id' => $rfqId, 'supplier_id' => $supplierId)));
        if (!$inv) { return self::fail('OFFER_FROM_UNINVITED_SUPPLIER', 'العرض من مورد غير مدعو يعلن ولا يقبل صامتا'); }

        $cur = trim((string) (isset($head['currency']) ? $head['currency'] : ''));
        if ($cur === '') { return self::fail('OFFER_WITHOUT_CURRENCY', 'لا مبلغ بلا عملة'); }

        $due = (string) $rfq['due_date'];
        $sub = (string) (isset($head['submitted_at']) ? $head['submitted_at'] : '');
        $late = ($due !== '' && $sub !== '' && substr($sub, 0, 10) > $due) ? 1 : 0;

        $total = 0.0;
        foreach ($lines as $l) {
            $q = (float) (isset($l['qty_offered']) ? $l['qty_offered'] : 0);
            $p = (float) (isset($l['unit_price']) ? $l['unit_price'] : 0);
            if ($q <= 0) { return self::fail('OFFER_LINE_ZERO_QTY', ''); }
            $total += $q * $p;
        }

        $offerId = 0;
        $gate->runInTransaction(function (TenantDb $g) use ($rfqId, $supplierId, $head, $lines, $cur, $late, $total, $actorId, &$offerId) {
            $fx = (float) (isset($head['fx_rate']) ? $head['fx_rate'] : 1);
            $offerId = (int) $g->insert('proc_offer', array(
                'rfq_id' => $rfqId, 'supplier_id' => $supplierId,
                'offer_ref' => (string) (isset($head['offer_ref']) ? $head['offer_ref'] : ''),
                'submitted_at' => (string) (isset($head['submitted_at']) ? $head['submitted_at'] : ''),
                'received_by' => (int) $actorId,
                'valid_until' => (string) (isset($head['valid_until']) ? $head['valid_until'] : ''),
                'currency' => $cur, 'fx_rate' => $fx,
                'total_amount' => $total, 'base_amount' => $total * $fx,
                'line_count' => count($lines),
                'delivery_days' => (int) (isset($head['delivery_days']) ? $head['delivery_days'] : 0),
                'payment_terms' => (string) (isset($head['payment_terms']) ? $head['payment_terms'] : ''),
                'late' => $late, 'state' => 'received',
            ));
            foreach ($lines as $l) {
                $q = (float) $l['qty_offered']; $p = (float) $l['unit_price'];
                $g->insert('proc_offer_line', array(
                    'offer_id' => $offerId,
                    'request_line_id' => (int) (isset($l['request_line_id']) ? $l['request_line_id'] : 0),
                    'item_id' => (int) (isset($l['item_id']) ? $l['item_id'] : 0),
                    'item_name' => (string) (isset($l['item_name']) ? $l['item_name'] : ''),
                    'qty_offered' => $q, 'unit_price' => $p, 'subtotal' => $q * $p,
                    'brand' => (string) (isset($l['brand']) ? $l['brand'] : ''),
                    'is_alternative' => (int) (isset($l['is_alternative']) ? $l['is_alternative'] : 0),
                    'alt_why' => (string) (isset($l['alt_why']) ? $l['alt_why'] : ''),
                ));
            }
            $g->update('proc_rfq_invite', array('response' => 'offered', 'responded_at' => date('Y-m-d H:i:s')),
                array('rfq_id' => $rfqId, 'supplier_id' => $supplierId));
        }, 'W09 تسجيل عرض مستلم');

        if ($offerId <= 0) { return self::fail('OFFER_RECORD_FAILED', ''); }
        self::recomputeRfq($gate, $rfqId);
        self::emit($gate, 'PRC_OFFER_RECEIVED', array('rfq_id' => $rfqId, 'offer_id' => $offerId, 'late' => $late));
        return self::done(array('offer_id' => $offerId, 'late' => $late));
    }

    /** فتحُ المظاريف — **ولا فتحَ قبل موعدِه المُعلَن** */
    public static function openEnvelopes(TenantDb $gate, $rfqId, $actorId, $nowTs = null)
    {
        $rfqId = (int) $rfqId;
        $rfq = $gate->selectOne('proc_rfq', array('where' => array('id' => $rfqId)));
        if (!$rfq) { return self::fail('RFQ_NOT_FOUND', ''); }
        if ((string) $rfq['state'] === 'opened' || (string) $rfq['state'] === 'awarded') {
            return self::fail('RFQ_ALREADY_OPENED', '');
        }
        $openAt = (string) $rfq['open_at'];
        if ($openAt === '') { return self::fail('RFQ_WITHOUT_OPEN_TIME', 'موعد الفتح يعلن قبله لا بعده'); }
        $now = $nowTs !== null ? (string) $nowTs : date('Y-m-d H:i:s');
        if ($now < $openAt) {
            return self::fail('RFQ_NOT_DUE_NO_OPEN', 'المظروف لا يقرأ قبل موعده');
        }
        $gate->update('proc_rfq', array(
            'state' => 'opened', 'opened_at' => $now, 'opened_by' => (int) $actorId,
        ), array('id' => $rfqId));
        self::emit($gate, 'PRC_RFQ_OPENED', array('rfq_id' => $rfqId));
        return self::done();
    }

    /** عدّاداتُ طلبِ العروضِ مشتقّة */
    public static function recomputeRfq(TenantDb $gate, $rfqId)
    {
        $rfqId = (int) $rfqId;
        $inv = $gate->count('proc_rfq_invite', array('where' => array('rfq_id' => $rfqId)));
        $off = $gate->count('proc_offer', array('where' => array('rfq_id' => $rfqId)));
        $gate->update('proc_rfq', array('invite_count' => (int) $inv, 'offer_count' => (int) $off),
            array('id' => $rfqId));
        return array('invites' => (int) $inv, 'offers' => (int) $off);
    }

    /* ══════════════════════════════════════════════════════════════════════
       ③ الترسية — المعاييرُ قبل الفتحِ والأدنى مشتقٌّ لا مُدَّعًى
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * محضرُ الترسية. **`is_lowest` مشتقٌّ قياسًا** ولا يُكتب من الشاشة،
     * والفائزُ غيرُ الأدنى يوجب سببًا مكتوبًا.
     */
    public static function awardRfq(TenantDb $gate, $rfqId, $winnerId, array $meta, $actorId)
    {
        $rfqId = (int) $rfqId; $winnerId = (int) $winnerId;
        $rfq = $gate->selectOne('proc_rfq', array('where' => array('id' => $rfqId)));
        if (!$rfq) { return self::fail('RFQ_NOT_FOUND', ''); }
        if ((string) $rfq['state'] !== 'opened') {
            return self::fail('AWARD_BEFORE_OPEN', 'الترسية بعد فتح المظاريف لا قبله');
        }
        $criteria = trim((string) (isset($meta['criteria_ref']) ? $meta['criteria_ref'] : ''));
        if ($criteria === '') {
            return self::fail('AWARD_WITHOUT_CRITERIA', 'معايير التقييم تعلن قبل الفتح');
        }

        $offers = $gate->select('proc_offer', array('where' => array('rfq_id' => $rfqId)));
        if (!$offers) { return self::fail('AWARD_WITHOUT_OFFERS', ''); }

        /* الأدنى **مشتقٌّ** من العروضِ المقيسةِ بأساسِ العملة */
        $lowId = 0; $lowAmt = null; $winAmt = null;
        foreach ($offers as $o) {
            $base = (float) $o['base_amount'];
            if ($lowAmt === null || $base < $lowAmt) { $lowAmt = $base; $lowId = (int) $o['supplier_id']; }
            if ((int) $o['supplier_id'] === $winnerId) { $winAmt = $base; }
        }
        if ($winAmt === null) { return self::fail('WINNER_HAS_NO_OFFER', 'الفائز بلا عرض مسجل'); }

        $isLowest = ($lowId === $winnerId) ? 1 : 0;
        $why = trim((string) (isset($meta['award_why']) ? $meta['award_why'] : ''));
        if ($isLowest === 0 && $why === '') {
            return self::fail('AWARD_NOT_LOWEST_NEEDS_REASON', 'الفائز ليس الأدنى فيلزم سبب مكتوب');
        }

        $awardId = 0;
        $gate->runInTransaction(function (TenantDb $g) use ($rfqId, $winnerId, $meta, $criteria, $isLowest, $why, $lowId, $lowAmt, $winAmt, $actorId, &$awardId) {
            $awardId = (int) $g->insert('proc_award', array(
                'rfq_id' => $rfqId,
                'minute_no' => (string) (isset($meta['minute_no']) ? $meta['minute_no'] : ''),
                'committee_ref' => (string) (isset($meta['committee_ref']) ? $meta['committee_ref'] : ''),
                'criteria_ref' => $criteria,
                'winner_id' => $winnerId, 'winner_amount' => $winAmt,
                'lowest_id' => $lowId, 'lowest_amount' => (float) $lowAmt,
                'is_lowest' => $isLowest, 'award_why' => $why,
                'state' => 'draft',
            ));
            $g->update('proc_rfq', array('state' => 'awarded'), array('id' => $rfqId));
        }, 'W09 محضر ترسية');

        if ($awardId <= 0) { return self::fail('AWARD_FAILED', ''); }
        self::emit($gate, 'PRC_AWARD_DRAFTED', array('rfq_id' => $rfqId, 'award_id' => $awardId, 'is_lowest' => $isLowest));
        return self::done(array('award_id' => $awardId, 'is_lowest' => $isLowest));
    }

    /** اعتمادُ المحضر — **ومَن رسا لا يعتمد** */
    public static function approveAward(TenantDb $gate, $awardId, $actorId)
    {
        $awardId = (int) $awardId; $actorId = (int) $actorId;
        $a = $gate->selectOne('proc_award', array('where' => array('id' => $awardId)));
        if (!$a) { return self::fail('AWARD_NOT_FOUND', ''); }
        if ((string) $a['state'] === 'approved') { return self::fail('AWARD_ALREADY_APPROVED', ''); }

        $rfq = $gate->selectOne('proc_rfq', array('where' => array('id' => (int) $a['rfq_id'])));
        if ($rfq && (int) $rfq['opened_by'] === $actorId) {
            return self::fail('SAME_ACTOR_AWARD_AND_APPROVE', 'من فتح المظاريف لا يعتمد المحضر');
        }
        $gate->update('proc_award', array(
            'state' => 'approved', 'approved_by' => $actorId, 'approved_at' => date('Y-m-d H:i:s'),
        ), array('id' => $awardId));
        self::emit($gate, 'PRC_AWARD_APPROVED', array('award_id' => $awardId));
        return self::done();
    }

    /* ══════════════════════════════════════════════════════════════════════
       ④ الأمرُ — لا أمرَ بلا سندٍ تنافسيٍّ أو سببِ مباشرة
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * إسنادُ أمرِ شراءٍ إلى محضرِ ترسيةٍ أو تعليلُ مباشرتِه.
     * **وهذا هو موضعُ العطبِ المقيسِ**: `award_minute_id` كان صفرًا في 22/22.
     */
    public static function anchorOrder(TenantDb $gate, $orderId, $awardId, $directReason, $actorId)
    {
        $orderId = (int) $orderId; $awardId = (int) $awardId;
        $directReason = trim((string) $directReason);
        $o = $gate->selectOne('proc_order', array('where' => array('id' => $orderId)));
        if (!$o) { return self::fail('ORDER_NOT_FOUND', ''); }

        if ($awardId > 0) {
            $a = $gate->selectOne('proc_award', array('where' => array('id' => $awardId)));
            if (!$a) { return self::fail('AWARD_NOT_FOUND', ''); }
            if ((string) $a['state'] !== 'approved') {
                return self::fail('AWARD_NOT_APPROVED_NO_ORDER', 'الأمر على محضر غير معتمد سند ناقص');
            }
            if ((int) $a['winner_id'] !== (int) $o['supplier_id']) {
                return self::fail('ORDER_SUPPLIER_NOT_WINNER', 'مورد الأمر ليس الفائز في المحضر');
            }
            $gate->update('proc_order', array(
                'award_minute_id' => $awardId, 'rfq_id' => (int) $a['rfq_id'], 'direct_reason' => '',
            ), array('id' => $orderId));
            self::emit($gate, 'PRC_ORDER_ANCHORED', array('order_id' => $orderId, 'award_id' => $awardId));
            return self::done(array('basis' => 'award'));
        }

        if ($directReason === '') {
            return self::fail('PO_WITHOUT_AWARD_NEEDS_REASON', 'أمر بلا محضر يلزمه سبب شراء مباشر مكتوب');
        }
        $cap = self::threshold('PRC_DIRECT_PURCHASE_CAP');
        if ($cap === null) {
            return self::fail('THRESHOLD_NOT_REGISTERED', 'حد الشراء المباشر غير مسجل فلا يخمن');
        }
        if ((float) $o['base_amount'] > $cap) {
            return self::fail('DIRECT_PURCHASE_OVER_CAP', 'المبلغ يتجاوز حد الشراء المباشر المسجل');
        }
        $gate->update('proc_order', array('direct_reason' => $directReason, 'award_minute_id' => 0),
            array('id' => $orderId));
        self::emit($gate, 'PRC_ORDER_DIRECT', array('order_id' => $orderId));
        return self::done(array('basis' => 'direct'));
    }

    /** تعديلُ أمرٍ — **بلا مسارٍ حوكميٍّ لا تعديل** */
    public static function amendOrder(TenantDb $gate, $orderId, array $a, $actorId)
    {
        $orderId = (int) $orderId;
        $o = $gate->selectOne('proc_order', array('where' => array('id' => $orderId)));
        if (!$o) { return self::fail('ORDER_NOT_FOUND', ''); }
        if ((string) $o['state'] === 'closed') {
            return self::fail('ORDER_CLOSED_NO_AMEND', 'الأمر المقفل لا يعدل — يفتح بقاعدة إعادة الفتح');
        }
        $kind = trim((string) (isset($a['kind']) ? $a['kind'] : ''));
        $reason = trim((string) (isset($a['reason']) ? $a['reason'] : ''));
        $gov = trim((string) (isset($a['gov_path']) ? $a['gov_path'] : ''));
        if ($kind === '') { return self::fail('AMEND_WITHOUT_KIND', ''); }
        if ($reason === '') { return self::fail('AMEND_WITHOUT_REASON', ''); }
        if ($gov === '') { return self::fail('PO_AMEND_WITHOUT_GOV_PATH', 'التعديل بلا مسار حوكمي استثناء لا تعديل'); }

        $seq = (int) $gate->count('proc_po_amendment', array('where' => array('order_id' => $orderId))) + 1;
        $gate->insert('proc_po_amendment', array(
            'order_id' => $orderId, 'seq_no' => $seq, 'kind' => $kind,
            'before_val' => (string) (isset($a['before_val']) ? $a['before_val'] : ''),
            'after_val' => (string) (isset($a['after_val']) ? $a['after_val'] : ''),
            'delta_amount' => (float) (isset($a['delta_amount']) ? $a['delta_amount'] : 0),
            'reason' => $reason, 'gov_path' => $gov,
            'requested_by' => (int) $actorId, 'state' => 'pending',
        ));
        $gate->update('proc_order', array('amend_count' => $seq), array('id' => $orderId));
        self::emit($gate, 'PRC_ORDER_AMENDED', array('order_id' => $orderId, 'seq_no' => $seq, 'kind' => $kind));
        return self::done(array('seq_no' => $seq));
    }

    /** متابعةُ توريدٍ — والتأخّرُ بسببٍ مكتوبٍ وإلّا فهو رقمٌ بلا معنى */
    public static function logDelivery(TenantDb $gate, $orderId, array $e, $actorId)
    {
        $orderId = (int) $orderId;
        $kind = trim((string) (isset($e['event_kind']) ? $e['event_kind'] : ''));
        if ($kind === '') { return self::fail('DELIVERY_WITHOUT_KIND', ''); }
        $why = trim((string) (isset($e['delay_why']) ? $e['delay_why'] : ''));
        if ($kind === 'DELAYED' && $why === '') {
            return self::fail('DELAY_WITHOUT_REASON', 'تأخر بلا سبب رقم بلا معنى');
        }
        $o = $gate->selectOne('proc_order', array('where' => array('id' => $orderId)));
        if (!$o) { return self::fail('ORDER_NOT_FOUND', ''); }

        $promised = (string) $o['expected_delivery_date'];
        $actual = (string) (isset($e['event_date']) ? $e['event_date'] : '');
        $delay = 0;
        if ($promised !== '' && $actual !== '' && $actual > $promised) {
            $delay = (int) floor((strtotime($actual) - strtotime($promised)) / 86400);
        }
        $gate->insert('proc_delivery_event', array(
            'order_id' => $orderId, 'event_kind' => $kind, 'event_date' => $actual,
            'qty_expected' => (float) (isset($e['qty_expected']) ? $e['qty_expected'] : 0),
            'qty_actual' => (float) (isset($e['qty_actual']) ? $e['qty_actual'] : 0),
            'delay_days' => $delay, 'delay_why' => $why,
            'receipt_id' => (int) (isset($e['receipt_id']) ? $e['receipt_id'] : 0),
            'logged_by' => (int) $actorId,
        ));
        self::emit($gate, 'PRC_DELIVERY_LOGGED', array('order_id' => $orderId, 'kind' => $kind, 'delay_days' => $delay));
        return self::done(array('delay_days' => $delay));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑤ المطابقةُ الثلاثيّة — الأمرُ والاستلامُ والفاتورة
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * مطابقةُ فاتورةٍ بأمرِها وسنداتِ إدخالِها. **الفرقُ يُقاس بعتبةِ السجلِّ**
     * ولا رقمَ صلبًا هنا — وخارجَ العتبةِ لا يمرُّ إلّا بقرارٍ مُسبَّب.
     */
    public static function matchInvoice(TenantDb $gate, $orderId, array $inv, $actorId)
    {
        $orderId = (int) $orderId;
        $no = trim((string) (isset($inv['invoice_no']) ? $inv['invoice_no'] : ''));
        if ($no === '') { return self::fail('MATCH_WITHOUT_INVOICE_NO', ''); }
        $o = $gate->selectOne('proc_order', array('where' => array('id' => $orderId)));
        if (!$o) { return self::fail('ORDER_NOT_FOUND', ''); }

        $tol = self::threshold('PRC_MATCH_TOLERANCE_PCT');
        if ($tol === null) {
            return self::fail('THRESHOLD_NOT_REGISTERED', 'سماح فرق المطابقة غير مسجل فلا يخمن');
        }

        /* مبلغُ الأمرِ **مشتقٌّ من بنودِه** لا من رأسِه — فالرأسُ قد يتقادم */
        $poAmt = 0.0;
        foreach ($gate->select('proc_order_line', array('where' => array('order_id' => $orderId))) as $l) {
            $poAmt += (float) $l['subtotal'];
        }
        /* ومبلغُ الاستلامِ من المقبولِ لا من الوارد */
        $grnAmt = 0.0;
        $rc = $gate->select('proc_receipt_custody', array('where' => array('order_id' => $orderId)));
        foreach ($rc as $r) {
            foreach ($gate->select('proc_receipt_line', array('where' => array('custody_id' => (int) $r['id']))) as $rl) {
                $grnAmt += ((float) $rl['qty_accepted']) * ((float) $rl['unit_cost']);
            }
        }

        $invAmt = (float) (isset($inv['invoice_amount']) ? $inv['invoice_amount'] : 0);
        $vInvPo = $invAmt - $poAmt;
        $vGrnPo = $grnAmt - $poAmt;
        $pct = $poAmt > 0 ? (abs($vInvPo) / $poAmt) * 100.0 : ($invAmt > 0 ? 100.0 : 0.0);
        $within = ($pct <= $tol) ? 1 : 0;
        $verdict = $within ? 'MATCHED' : 'VARIANCE';

        $mid = (int) $gate->insert('proc_invoice_match', array(
            'order_id' => $orderId, 'invoice_no' => $no,
            'invoice_date' => (string) (isset($inv['invoice_date']) ? $inv['invoice_date'] : ''),
            'invoice_amount' => $invAmt, 'po_amount' => $poAmt, 'grn_amount' => $grnAmt,
            'var_invoice_po' => $vInvPo, 'var_grn_po' => $vGrnPo,
            'within_tol' => $within, 'verdict' => $verdict,
        ));
        self::emit($gate, 'PRC_INVOICE_MATCHED', array('order_id' => $orderId, 'match_id' => $mid, 'verdict' => $verdict));
        return self::done(array('match_id' => $mid, 'verdict' => $verdict, 'variance_pct' => round($pct, 2)));
    }

    /** قرارُ الفرقِ — **ولا قبولَ بلا سبب**، ومَن اعتمد المحضرَ لا يقرّر هنا */
    public static function decideVariance(TenantDb $gate, $matchId, $decision, $reason, $actorId)
    {
        $matchId = (int) $matchId; $actorId = (int) $actorId;
        $decision = trim((string) $decision); $reason = trim((string) $reason);
        $m = $gate->selectOne('proc_invoice_match', array('where' => array('id' => $matchId)));
        if (!$m) { return self::fail('MATCH_NOT_FOUND', ''); }
        if ((int) $m['within_tol'] === 1) { return self::fail('MATCH_WITHIN_TOLERANCE_NO_DECISION', ''); }
        if (!in_array($decision, array('ACCEPT', 'REJECT'), true)) {
            return self::fail('VARIANCE_DECISION_INVALID', '');
        }
        if ($reason === '') {
            return self::fail('MATCH_VARIANCE_NEEDS_DECISION', 'الفرق خارج العتبة لا يمر بلا سبب مكتوب');
        }
        $o = $gate->selectOne('proc_order', array('where' => array('id' => (int) $m['order_id'])));
        if ($o && (int) $o['award_minute_id'] > 0) {
            $a = $gate->selectOne('proc_award', array('where' => array('id' => (int) $o['award_minute_id'])));
            if ($a && (int) $a['approved_by'] === $actorId) {
                return self::fail('SAME_ACTOR_APPROVE_AND_MATCH', 'من اعتمد المحضر لا يقرر فرق فاتورته');
            }
        }
        $gate->update('proc_invoice_match', array(
            'var_decision' => $decision, 'var_reason' => $reason,
            'decided_by' => $actorId, 'decided_at' => date('Y-m-d H:i:s'),
            'verdict' => ($decision === 'ACCEPT' ? 'MATCHED' : 'BLOCKED'),
        ), array('id' => $matchId));
        self::emit($gate, 'PRC_VARIANCE_DECIDED', array('match_id' => $matchId, 'decision' => $decision));
        return self::done();
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑥ تقييمُ أداءِ التوريد — مشتقٌّ بقاعدةٍ لا مُدخَل
       ══════════════════════════════════════════════════════════════════════ */

    /** سطرُ تقييمٍ لمورّدٍ في فترة — **كلُّ رقمٍ بقاعدةٍ مكتوبة** */
    public static function evaluateSupplier(TenantDb $gate, $supplierId, $periodYm)
    {
        $supplierId = (int) $supplierId; $periodYm = (string) $periodYm;
        if ($periodYm === '') { return self::fail('EVAL_WITHOUT_PERIOD', ''); }

        $orders = $gate->select('proc_order', array('where' => array('supplier_id' => $supplierId)));
        $inPeriod = array();
        foreach ($orders as $o) {
            if (substr((string) $o['created_at'], 0, 7) === $periodYm) { $inPeriod[] = $o; }
        }
        $n = count($inPeriod);

        $onTime = 0; $late = 0; $rejQty = 0.0; $recvQty = 0.0; $varN = 0; $mN = 0;
        foreach ($inPeriod as $o) {
            $evs = $gate->select('proc_delivery_event', array('where' => array('order_id' => (int) $o['id'])));
            foreach ($evs as $e) {
                if ((string) $e['event_kind'] === 'ARRIVED') {
                    if ((int) $e['delay_days'] > 0) { $late++; } else { $onTime++; }
                }
            }
            foreach ($gate->select('proc_receipt_custody', array('where' => array('order_id' => (int) $o['id']))) as $r) {
                foreach ($gate->select('proc_receipt_line', array('where' => array('custody_id' => (int) $r['id']))) as $rl) {
                    $rejQty += (float) $rl['qty_rejected'];
                    $recvQty += (float) $rl['qty_received'];
                }
            }
            foreach ($gate->select('proc_invoice_match', array('where' => array('order_id' => (int) $o['id']))) as $m) {
                $mN++;
                if ((int) $m['within_tol'] === 0) { $varN++; }
            }
        }
        $arrivals = $onTime + $late;
        $onTimePct = $arrivals > 0 ? ($onTime / $arrivals) * 100.0 : 0.0;
        $rejPct = $recvQty > 0 ? ($rejQty / $recvQty) * 100.0 : 0.0;
        $varPct = $mN > 0 ? ($varN / $mN) * 100.0 : 0.0;

        /* أوزانُ الدرجةِ من السجلِّ — ⛔ لا رقمَ صلبًا */
        $wOn = self::threshold('PRC_EVAL_WEIGHT_ONTIME');
        $wRj = self::threshold('PRC_EVAL_WEIGHT_REJECT');
        $wVr = self::threshold('PRC_EVAL_WEIGHT_VARIANCE');
        if ($wOn === null || $wRj === null || $wVr === null) {
            return self::fail('THRESHOLD_NOT_REGISTERED', 'أوزان التقييم غير مسجلة فلا تخمن');
        }
        $score = ($onTimePct * $wOn) + ((100 - $rejPct) * $wRj) + ((100 - $varPct) * $wVr);
        $rule = 'onTime×' . $wOn . ' + (100-reject)×' . $wRj . ' + (100-variance)×' . $wVr;

        $gate->insert('proc_supplier_eval', array(
            'supplier_id' => $supplierId, 'period_ym' => $periodYm,
            'orders_count' => $n, 'on_time_pct' => round($onTimePct, 2),
            'reject_pct' => round($rejPct, 2), 'variance_pct' => round($varPct, 2),
            'score' => round($score, 2), 'score_rule' => $rule,
            'grade' => ($score >= 80 ? 'A' : ($score >= 60 ? 'B' : 'C')),
        ));
        self::emit($gate, 'PRC_SUPPLIER_EVALUATED', array('supplier_id' => $supplierId, 'period_ym' => $periodYm));
        return self::done(array('score' => round($score, 2), 'rule' => $rule));
    }

    /* ══════════════════════════════════════════════════════════════════════
       نشرُ الحقيقةِ المحايدة — والعطالةُ بمفتاحٍ لا بحظّ
       ══════════════════════════════════════════════════════════════════════ */
    /**
     * ◆ **خريطةُ الحدثِ إلى كيانِه — سجلٌّ واحدٌ لا حرفيّاتٌ متناثرة.**
     *   لكلِّ حدثٍ: الجدولُ الحاملُ للكيانِ ومفتاحُ مُعرِّفِه في الحمولة.
     *   والحدثُ غيرُ المسجَّلِ هنا **لا يُنشَر** — فلا حدثَ بلا كيانٍ معلوم.
     * @var array<string,array{0:string,1:string}>
     */
    const EVENT_ENTITY = array(
        'PRC_PACKAGE_JOINED'    => array('proc_package',       'package_id'),
        'PRC_RFQ_INVITED'       => array('proc_rfq',           'rfq_id'),
        'PRC_OFFER_RECEIVED'    => array('proc_offer',         'offer_id'),
        'PRC_RFQ_OPENED'        => array('proc_rfq',           'rfq_id'),
        'PRC_AWARD_DRAFTED'     => array('proc_award',         'award_id'),
        'PRC_AWARD_APPROVED'    => array('proc_award',         'award_id'),
        'PRC_ORDER_ANCHORED'    => array('proc_order',         'order_id'),
        'PRC_ORDER_DIRECT'      => array('proc_order',         'order_id'),
        'PRC_ORDER_AMENDED'     => array('proc_order',         'order_id'),
        'PRC_DELIVERY_LOGGED'   => array('proc_order',         'order_id'),
        'PRC_INVOICE_MATCHED'   => array('proc_invoice_match', 'match_id'),
        'PRC_VARIANCE_DECIDED'  => array('proc_invoice_match', 'match_id'),
        'PRC_SUPPLIER_EVALUATED' => array('proc_supplier_eval', 'supplier_id'),
    );

    /**
     * نشرُ حقيقةٍ محايدة — و`publishFact` تعيد `null` صامتةً إن أُطفئ الجذر.
     * **و`company_id` يُقرأ من صفِّ الكيانِ نفسِه** لا يُمرَّر من المُستدعي.
     */
    private static function emit(TenantDb $gate, $eventKey, array $payload)
    {
        $conn = self::$eventConn;
        if (!($conn instanceof \mysqli)) { return null; }
        if (!isset(self::EVENT_ENTITY[$eventKey])) { return null; }
        list($table, $idKey) = self::EVENT_ENTITY[$eventKey];
        $entityId = isset($payload[$idKey]) ? (int) $payload[$idKey] : 0;

        $pub = \dirname(\dirname(\dirname(__DIR__))) . '/app/Core/EventPublisher.php';
        if (!\is_file($pub)) { return null; }
        require_once $pub;

        $company = 0;
        try {
            $row = $gate->selectOne($table, array('columns' => array('company_id'), 'where' => array('id' => $entityId)));
            if ($row) { $company = (int) $row['company_id']; }
        } catch (\Throwable $t) { $company = 0; }

        try {
            return \App\Core\EventPublisher::publishFact($conn, array(
                'company_id'      => $company,
                'event_key'       => $eventKey,
                'category'        => 'procurement',
                'source_module'   => 'procurement',
                'entity_type'     => $table,
                'entity_id'       => $entityId,
                'payload'         => $payload,
                'idempotency_key' => 'w9:' . $eventKey . ':' . $entityId . ':' . substr(sha1(json_encode($payload)), 0, 12),
                'source_ref'      => 'ProcurementCycleService',
            ));
        } catch (\Throwable $t) { return null; }
    }
}
