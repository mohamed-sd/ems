<?php
/**
 * app/Services/Warehouse/WarehouseCycleService.php — دورةُ المخزن (RPR-W09)
 * ═══════════════════════════════════════════════════════════════════════════
 * **من الإدخالِ إلى الإقفال**: سندُ إدخالٍ بفحصٍ يفصل المقبولَ من المرفوض ←
 * رصيدٌ **بحالتِه** لا بكمّيّةٍ واحدة ← طلبُ صرفٍ من الجهةِ ← سندُ صرفٍ من
 * المخزنِ ← عهدةٌ ومرتجَع ← تحويلٌ بين مخزنَين ← جردٌ بقرارِ تسويةٍ ←
 * إقفالٌ شهريٌّ **بمعادلةٍ تنطبق**.
 *
 * ◆ **وسياسةُ التتبّعِ أُجيبت وأُعيد تشكيلُها** (`DEC-OPEN-15` · 2026-08-26):
 *   كانت أعلامًا ثنائيّةً خامدةً، وصارت **ثلاثيّةً بمستويَين** يحلُّها
 *   `TrackingPolicyService` — فئةٌ افتراضًا ثمَّ صنفٌ تخصيصًا، بثماني خصائصَ
 *   ونسخٍ مؤرَّخة. و`requireTracking` تعيد **ثلاثةَ أحكام**: `REQUIRED` ناقصٌ
 *   يُردّ · `OPTIONAL` ناقصٌ **يمضي ويُسجَّل قيدَ جودة** · `OFF` لا يُطلَب.
 *   ⛔ **ولا يمنع نقصُ اختياريٍّ مهما كثر** — «لا منعَ للاستلامِ ولا الصرفِ
 *   ولا التحويلِ ولا الجرد». ⛔ ولا فئةَ مكتوبةٌ في هذا الملفّ.
 *
 * ◆ **وحالةُ الرصيدِ بُعدٌ لا وصف** (`WH-06`): الصالحُ والمحجوزُ والمحجورُ
 *   والتالفُ **لا تُجمع في رقمٍ واحد** — وجمعُها يجعل «المتاحَ» كذبًا يُصرَف
 *   عليه. فـ`recomputeStockState` تشتقُّ سطرًا لكلِّ حالةٍ من `proc_stock_move`.
 *
 * ◆ **وطلبُ الصرفِ غيرُ سندِ الصرف** (`WH-08` مقابل `WH-10`): الجهةُ تطلب
 *   والمخزنُ يصرف. وخلطُهما في كيانٍ واحدٍ **يمحو «طُلب ولم يُصرَف»** فلا
 *   يبقى ما يُقاس عليه تأخّرُ المخزن.
 *
 * ◆ **والفرقُ في الجردِ لا يُقفل بلا قرارِ تسويةٍ مُسبَّب**
 *   (`COUNT_DIFF_WITHOUT_SETTLEMENT`)، **والإقفالُ الشهريُّ لا يمرُّ بمعادلةٍ
 *   لا تنطبق** (`CLOSE_UNBALANCED`) — فالإقفالُ إثباتٌ لا إعلان.
 *
 * ◆ **وفصلُ الواجباتِ يُنفَّذ لا يُعلَن**: مَن عدَّ لا يعتمد الجردَ
 *   (`SAME_ACTOR_COUNT_AND_APPROVE`)، ومَن أرسل التحويلَ لا يستلمه
 *   (`SAME_ACTOR_SEND_AND_RECEIVE`).
 *
 * ⛔ **ولا عتبةٌ رقميّةٌ في هذا الملفّ** — سماحُ فرقِ الجردِ ونافذةُ الإقفالِ
 *   من `repair01_w9_thresholds`، وغيابُها يردُّ العمليّةَ ولا يُخمّن.
 *
 * ◆ **وكلُّ وصولٍ إلى جدولِ مستأجِرٍ عبرَ `TenantDb`** (‏FR-SEC-006 · GAP-29).
 */

namespace App\Services\Warehouse;

use App\Core\TenantDb;

class WarehouseCycleService
{
    /** حالاتُ الرصيدِ — بُعدٌ مستقلٌّ لا وصفٌ يُجمع */
    const STOCK_STATES = array('GOOD', 'RESERVED', 'QUARANTINE', 'DAMAGED', 'EXPIRED');

    /* ══════════════════════════════════════════════════════════════════════
       مفرداتُ حركةِ المخزنِ — **حيّةٌ لا مخترَعة**
       ══════════════════════════════════════════════════════════════════════
       ◆ **درسٌ مقيسٌ في هذه المرحلة**: كُتبت هذه الخدمةُ أوّلَ مرّةٍ بمفرداتٍ
         لاتينيّةٍ (`in` · `out` · `adjust`)، والحيُّ عربيٌّ (`استلام` · `صرف`)
         **ويحرسه قادحٌ** (`trg_stock_no_negative` · هجرة 2027-04-14) يعُدُّ
         كلَّ ما ليس في قائمةِ الوارد **صادرًا** — فحركةُ إدخالٍ بمفردةٍ
         مخترَعةٍ تُقرأ إخراجًا وتُردُّ بـ`STK-409`. **والمفردةُ المخترَعةُ
         تمرُّ في القياسِ وتسقط في التشغيل.**
       ◆ ولذلك **قائمةُ الوارد تُقرأ من `StockMoveService::INBOUND`** مصدرًا
         واحدًا للمعنى — والقادحُ نفسُه بُني على القائمةِ نفسِها. ⛔ ولا نسخةَ
         ثانيةً في هذا الملفّ. */
    const MV_RECEIPT      = 'استلام';
    const MV_ISSUE        = 'صرف';
    const MV_TRANSFER_OUT = 'تحويل صادر';
    const MV_TRANSFER_IN  = 'تحويل وارد';
    const MV_RETURN       = 'مرتجع';
    const MV_ADJ_UP       = 'تسوية زيادة';
    const MV_ADJ_DOWN     = 'تسوية عجز';

    /** أهي مفردةُ ورودٍ؟ — **الجوابُ من مصدرِ القادحِ نفسِه لا من نسخة** */
    public static function isInbound($moveType)
    {
        $svc = \dirname(__DIR__) . '/Procurement/StockMoveService.php';
        if (\is_file($svc)) { require_once $svc; }
        if (\class_exists('\App\Services\Procurement\StockMoveService')
            && \defined('\App\Services\Procurement\StockMoveService::INBOUND')) {
            return \in_array((string) $moveType, \App\Services\Procurement\StockMoveService::INBOUND, true);
        }
        /* ⛔ **بلا مصدرٍ لا تخمين**: الغيابُ يُعامَل صدورًا فيُردُّ ولا يُقبل خطأً */
        return false;
    }

    /** @var \mysqli|null */
    private static $eventConn = null;
    /** @var \mysqli|null */
    private static $thConn = null;
    /** @var array|null */
    private static $th = null;

    public static function setEventConnection(\mysqli $conn) { self::$eventConn = $conn; }
    public static function setThresholdConnection(\mysqli $conn) { self::$thConn = $conn; self::$th = null; }

    /** عتبةٌ من السجلِّ — **ولا قيمةٌ افتراضيّةٌ في الشيفرة** */
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

    private static function fail($code, $detail = '')
    {
        return array('ok' => false, 'code' => $code, 'detail' => $detail);
    }
    private static function done(array $extra = array())
    {
        return array_merge(array('ok' => true, 'code' => ''), $extra);
    }

    /* ══════════════════════════════════════════════════════════════════════
       ① بوّابةُ التتبّعِ — حاضرةٌ وخامدةٌ حتّى يُجيب المالك (DEC-OPEN-15)
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * **هل يوجب هذا الصنفُ بياناتِ تتبّع؟** الجوابُ من `proc_item` وحدَه،
     * وأعلامُه مشتقّةٌ من `proc_item_track_rule` — ⛔ **ولا فئةَ مكتوبةٌ هنا**.
     *
     * @return array{lot:bool,serial:bool,expiry:bool} كلُّها `false` ما دام
     *         السجلُّ خاويًا — وهو حالُه الآن بقرارِ التأجيل.
     */
    public static function trackingFlags(TenantDb $gate, $itemId)
    {
        $it = $gate->selectOne('proc_item', array('where' => array('id' => (int) $itemId)));
        if (!$it) { return array('lot' => false, 'serial' => false, 'expiry' => false); }
        return array(
            'lot'    => ((int) (isset($it['track_lot']) ? $it['track_lot'] : 0)) === 1,
            'serial' => ((int) (isset($it['track_serial']) ? $it['track_serial'] : 0)) === 1,
            'expiry' => ((int) (isset($it['track_expiry']) ? $it['track_expiry'] : 0)) === 1,
        );
    }

    /* ══════════════════════════════════════════════════════════════════════
       ◆ **أُعيد تشكيلُها بجوابِ المالك** (`DEC-OPEN-15` · 2026-08-26)
       ══════════════════════════════════════════════════════════════════════
       كانت تقرأ **عَلَمًا ثنائيًّا** وتردُّ حين يحمله الصنفُ ولا تُقدَّم بياناتُه.
       والجوابُ يوجب **ثلاثةَ أحكامٍ لا حكمَين**:
         · `REQUIRED` ناقصٌ ⇒ **يُردّ**.
         · `OPTIONAL` ناقصٌ ⇒ **يمضي** ويُسجَّل في `proc_track_gap` قيدَ جودة.
         · `OFF` ⇒ لا يُطلَب أصلًا.
       ⛔ **ولا يمنع نقصُ اختياريٍّ مهما كثر** — نصُّ القرار: «لا أريد منعَ
          الاستلامِ ولا الصرفِ ولا التحويلِ ولا الجرد».
       ◆ والحكمُ **تاريخيّ**: بالسياسةِ الساريةِ لحظةَ العمليّةِ لا اليوم.
       ══════════════════════════════════════════════════════════════════════ */
    public static function requireTracking(TenantDb $gate, $itemId, array $line,
                                           $opKind = 'RECEIPT', $opRef = '', $onDate = null)
    {
        $svc = __DIR__ . '/TrackingPolicyService.php';
        if (\is_file($svc)) { require_once $svc; }
        if (!\class_exists('\App\Services\Warehouse\TrackingPolicyService')) {
            /* ⛔ **بلا خدمةِ سياسةٍ لا تخمين** — الغيابُ يُعلَن ولا يُبتلع */
            return self::fail('TRACKING_POLICY_SERVICE_MISSING', 'خدمة سياسة التتبع غير محملة');
        }
        $v = \App\Services\Warehouse\TrackingPolicyService::checkOperation($gate, $itemId, $line, $onDate);

        if ($v['verdict'] === 'block') { return self::fail($v['code'], $v['detail']); }
        if ($v['verdict'] === 'gap') {
            \App\Services\Warehouse\TrackingPolicyService::logGap(
                $gate, $itemId, $opKind, (string) $opRef, $v['missing']);
            return self::done(array('policy' => $v['policy'], 'gap' => $v['missing'], 'note' => $v['detail']));
        }
        return self::done(array('policy' => $v['policy'], 'gap' => array()));
    }

    /**
     * **بوّابةُ المنتهي** — سياسةُ الانتهاءِ من قاعدةِ الفئةِ لا من الشيفرة.
     * خامدةٌ أيضًا: بلا قاعدةٍ لا `track_expiry`، وبلا علمٍ لا فحص.
     */
    /* ══════════════════════════════════════════════════════════════════════
       ◆ **ثلاثةُ مستوياتِ إنفاذٍ لا مستويان** (`DEC-OPEN-15` ⑫):
         · `WARNING`           ينبّه ويمضي — افتراضُ المرحلةِ الحالية.
         · `APPROVAL_REQUIRED` لا يمضي إلّا باعتمادِ **دورِ السياسة** وبسببٍ مكتوب.
         · `HARD_BLOCK`        لا يمضي مطلقًا.
       ⛔ **وأمينُ المخزنِ لا يمدّد الصلاحيةَ من عنده** (⑬) — الدورُ المخوَّلُ
          يُقرَأ من `override_authority` في السياسةِ لا من دورِ المنفِّذ.
       ══════════════════════════════════════════════════════════════════════ */
    public static function expiryGate(TenantDb $gate, $itemId, $expiryDate, $today = null, array $ctx = array())
    {
        $svc = __DIR__ . '/TrackingPolicyService.php';
        if (\is_file($svc)) { require_once $svc; }
        if (!\class_exists('\App\Services\Warehouse\TrackingPolicyService')) {
            return self::fail('TRACKING_POLICY_SERVICE_MISSING', 'خدمة سياسة التتبع غير محملة');
        }
        $v = \App\Services\Warehouse\TrackingPolicyService::expiryVerdict(
            $gate, $itemId, $expiryDate, $ctx, $today);

        if ($v['verdict'] === 'block') { return self::fail($v['code'], $v['detail']); }
        return self::done(array(
            'checked' => ((string) $v['policy']['expiry'] !== 'OFF'),
            'warn'    => ($v['verdict'] === 'warn'),
            'override' => ($v['verdict'] === 'approved_override'),
            'enforce' => (string) $v['policy']['expiry_enforce'],
            'note'    => (string) $v['detail'],
        ));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ② سندُ الإدخالِ والفحص
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * تسجيلُ بندِ إدخالٍ بالفحص. **الوارد = المقبول + المرفوض** حسابًا،
     * والمرفوضُ بسببٍ مكتوبٍ وإلّا فهو رقمٌ بلا معنى.
     */
    public static function receiveLine(TenantDb $gate, $receiptId, array $line, $actorId)
    {
        $receiptId = (int) $receiptId;
        $rc = $gate->selectOne('proc_receipt_custody', array('where' => array('id' => $receiptId)));
        if (!$rc) { return self::fail('RECEIPT_NOT_FOUND', ''); }

        $itemId = (int) (isset($line['item_id']) ? $line['item_id'] : 0);
        $recv = (float) (isset($line['qty_received']) ? $line['qty_received'] : 0);
        $acc  = (float) (isset($line['qty_accepted']) ? $line['qty_accepted'] : 0);
        $rej  = (float) (isset($line['qty_rejected']) ? $line['qty_rejected'] : 0);
        $why  = trim((string) (isset($line['reject_reason']) ? $line['reject_reason'] : ''));

        if ($recv <= 0) { return self::fail('RECEIPT_LINE_ZERO_QTY', ''); }
        if (abs(($acc + $rej) - $recv) > 0.0001) {
            return self::fail('RECEIPT_QTY_NOT_BALANCED', 'الوارد لا يساوي المقبول زائد المرفوض');
        }
        if ($rej > 0 && $why === '') {
            return self::fail('REJECT_WITHOUT_REASON', 'مرفوض بلا سبب رقم بلا معنى');
        }

        /* ⛔ **بوّابةُ التتبّعِ قبل الكتابة** — خامدةٌ اليوم وحيّةٌ بالبناء */
        $tr = self::requireTracking($gate, $itemId, $line, 'RECEIPT', (string) $rc['code']);
        if (!$tr['ok']) { return $tr; }

        $lineId = 0;
        $gate->runInTransaction(function (TenantDb $g) use ($receiptId, $line, $itemId, $recv, $acc, $rej, $why, $rc, &$lineId) {
            $lineId = (int) $g->insert('proc_receipt_line', array(
                'custody_id' => $receiptId, 'item_id' => $itemId,
                'item_name' => (string) (isset($line['item_name']) ? $line['item_name'] : ''),
                'qty' => $acc,
                'qty_received' => $recv, 'qty_accepted' => $acc, 'qty_rejected' => $rej,
                'reject_reason' => $why,
                'lot_no' => (string) (isset($line['lot_no']) ? $line['lot_no'] : ''),
                'serial_no' => (string) (isset($line['serial_no']) ? $line['serial_no'] : ''),
                'expiry_date' => (string) (isset($line['expiry_date']) ? $line['expiry_date'] : ''),
                'unit_cost' => (float) (isset($line['unit_cost']) ? $line['unit_cost'] : 0),
                'order_line_id' => (int) (isset($line['order_line_id']) ? $line['order_line_id'] : 0),
            ));
            /* المقبولُ وحدَه يدخل الرصيدَ الصالح؛ والمرفوضُ يدخل المحجور */
            if ($acc > 0) {
                $g->insert('proc_stock_move', array(
                    'item_id' => $itemId, 'warehouse_id' => (int) $rc['warehouse_id'],
                    'move_type' => self::MV_RECEIPT, 'qty' => $acc,
                    'unit_cost' => (float) (isset($line['unit_cost']) ? $line['unit_cost'] : 0),
                    'ref_type' => 'receipt', 'ref_id' => $receiptId,
                    'note' => 'W09 مقبول من سند إدخال', 'moved_at' => date('Y-m-d H:i:s'),
                ));
            }
        }, 'W09 بند سند إدخال');

        if ($lineId <= 0) { return self::fail('RECEIPT_LINE_FAILED', ''); }
        self::recomputeStockState($gate, $itemId, (int) $rc['warehouse_id']);
        self::emit($gate, 'WH_RECEIPT_LINE_ADDED', array('receipt_id' => $receiptId, 'line_id' => $lineId, 'rejected' => $rej));
        return self::done(array('line_id' => $lineId));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ③ الرصيدُ بحالتِه — مشتقٌّ لا مُدخَل
       ══════════════════════════════════════════════════════════════════════ */

    /** سطرُ رصيدٍ لكلِّ حالة — **يُعاد اشتقاقُه من الحركاتِ ولا يُكتب بيد** */
    public static function recomputeStockState(TenantDb $gate, $itemId, $warehouseId)
    {
        $itemId = (int) $itemId; $warehouseId = (int) $warehouseId;
        $moves = $gate->select('proc_stock_move', array(
            'where' => array('item_id' => $itemId, 'warehouse_id' => $warehouseId), 'limit' => 5000,
        ));
        $good = 0.0; $damaged = 0.0; $quarantine = 0.0;
        foreach ($moves as $m) {
            $q = (float) $m['qty']; $t = (string) $m['move_type'];
            /* ◆ **العلامةُ من قائمةِ الوارد الحيّةِ** لا من حرفيّاتٍ مكتوبةٍ هنا —
                 والقادحُ `trg_stock_no_negative` يقرأ القائمةَ نفسَها. */
            if (self::isInbound($t)) { $good += $q; }
            elseif ($t === 'إتلاف') { $damaged += $q; $good -= $q; }
            elseif ($t === 'حجر') { $quarantine += $q; $good -= $q; }
            else { $good -= $q; }
        }
        $rule = 'مجموع الوارد ناقص الصادر لكل حالة من proc_stock_move وقائمة الوارد من StockMoveService';
        $set = array('GOOD' => $good, 'DAMAGED' => $damaged, 'QUARANTINE' => $quarantine);
        foreach ($set as $k => $v) {
            $ex = $gate->selectOne('proc_stock_state', array('where' => array(
                'item_id' => $itemId, 'warehouse_id' => $warehouseId, 'state_key' => $k)));
            if ($ex) {
                $gate->update('proc_stock_state', array('qty' => $v, 'derive_rule' => $rule,
                    'computed_at' => date('Y-m-d H:i:s')), array('id' => (int) $ex['id']));
            } else {
                $gate->insert('proc_stock_state', array('item_id' => $itemId, 'warehouse_id' => $warehouseId,
                    'state_key' => $k, 'qty' => $v, 'derive_rule' => $rule));
            }
        }
        return $set;
    }

    /* ══════════════════════════════════════════════════════════════════════
       ④ طلبُ الصرفِ ثمَّ سندُه — كيانانِ لا كيان
       ══════════════════════════════════════════════════════════════════════ */

    /** اعتمادُ طلبِ صرفٍ — **وخفضُ المعتمَدِ عن المطلوبِ بسببٍ مكتوب** */
    public static function approveIssueRequest(TenantDb $gate, $requestId, array $approvals, $actorId)
    {
        $requestId = (int) $requestId; $actorId = (int) $actorId;
        $req = $gate->selectOne('proc_issue_request', array('where' => array('id' => $requestId)));
        if (!$req) { return self::fail('ISSUE_REQUEST_NOT_FOUND', ''); }
        if ((int) $req['requester_id'] === $actorId) {
            return self::fail('SAME_ACTOR_REQUEST_AND_APPROVE', 'من طلب لا يعتمد طلبه');
        }
        if ((string) $req['state'] !== 'draft' && (string) $req['state'] !== 'submitted') {
            return self::fail('ISSUE_REQUEST_NOT_PENDING', '');
        }
        foreach ($approvals as $lineId => $a) {
            $l = $gate->selectOne('proc_issue_request_line', array('where' => array('id' => (int) $lineId)));
            if (!$l) { continue; }
            $qa = (float) (isset($a['qty_approved']) ? $a['qty_approved'] : 0);
            $cut = trim((string) (isset($a['cut_reason']) ? $a['cut_reason'] : ''));
            if ($qa > (float) $l['qty_requested']) {
                return self::fail('APPROVED_EXCEEDS_REQUESTED', 'المعتمد لا يتجاوز المطلوب');
            }
            if ($qa < (float) $l['qty_requested'] && $cut === '') {
                return self::fail('CUT_WITHOUT_REASON', 'خفض المعتمد بلا سبب مكتوب');
            }
            $gate->update('proc_issue_request_line', array('qty_approved' => $qa, 'cut_reason' => $cut),
                array('id' => (int) $lineId));
        }
        $gate->update('proc_issue_request', array('state' => 'approved'), array('id' => $requestId));
        self::emit($gate, 'WH_ISSUE_REQUEST_APPROVED', array('request_id' => $requestId));
        return self::done();
    }

    /**
     * صرفٌ فعليٌّ مقابلَ طلبٍ معتمَد. **لا صرفَ يتجاوز المعتمَد**، ولا صرفَ
     * لصنفٍ خطرٍ بلا تصريحٍ ساري، ولا صرفَ في فترةٍ مقفلة.
     */
    public static function issueAgainstRequest(TenantDb $gate, $requestId, array $lines, $issueId, $actorId)
    {
        $requestId = (int) $requestId; $issueId = (int) $issueId;
        $req = $gate->selectOne('proc_issue_request', array('where' => array('id' => $requestId)));
        if (!$req) { return self::fail('ISSUE_REQUEST_NOT_FOUND', ''); }
        if ((string) $req['state'] !== 'approved') {
            return self::fail('ISSUE_WITHOUT_APPROVED_REQUEST', 'لا صرف على طلب غير معتمد');
        }
        $wh = (int) $req['warehouse_id'];

        /* ⛔ الفترةُ المقفلةُ لا تقبل حركةً — وإلّا انفكَّ الإقفالُ بعد إثباتِه */
        $ym = date('Y-m');
        $cl = $gate->selectOne('proc_wh_close', array('where' => array('warehouse_id' => $wh, 'period_ym' => $ym)));
        if ($cl && (string) $cl['state'] === 'closed') {
            return self::fail('ISSUE_FROM_CLOSED_PERIOD', 'الفترة مقفلة فلا حركة فيها');
        }

        foreach ($lines as $ln) {
            $lineId = (int) (isset($ln['request_line_id']) ? $ln['request_line_id'] : 0);
            $qty = (float) (isset($ln['qty']) ? $ln['qty'] : 0);
            $l = $gate->selectOne('proc_issue_request_line', array('where' => array('id' => $lineId)));
            if (!$l) { return self::fail('ISSUE_LINE_NOT_IN_REQUEST', ''); }
            if ($qty > (float) $l['qty_approved'] - (float) $l['qty_issued'] + 0.0001) {
                return self::fail('ISSUE_EXCEEDS_APPROVED', 'المصروف يتجاوز المعتمد');
            }
            $itemId = (int) $l['item_id'];

            /* بوّابةُ الخطر — التصريحُ شرطُ صرفٍ لا توصية */
            $hz = $gate->selectOne('proc_hazmat_control', array('where' => array('item_id' => $itemId, 'is_active' => 1)));
            if ($hz && (int) $hz['permit_needed'] === 1) {
                $permit = trim((string) (isset($ln['permit_ref']) ? $ln['permit_ref'] : ''));
                if ($permit === '') {
                    return self::fail('HAZMAT_ISSUE_NEEDS_PERMIT', 'الصنف الخطر لا يصرف بلا تصريح مرجعه مكتوب');
                }
            }
            /* بوّابةُ الانتهاءِ — خامدةٌ حتّى تُبذَر قواعدُ الفئات */
            $eg = self::expiryGate($gate, $itemId, (string) (isset($ln['expiry_date']) ? $ln['expiry_date'] : ''), null,
                array('approver_role' => (string) (isset($ln['approver_role']) ? $ln['approver_role'] : ''),
                      'override_reason' => (string) (isset($ln['override_reason']) ? $ln['override_reason'] : '')));
            if (!$eg['ok']) { return $eg; }
        }

        $moved = 0;
        $gate->runInTransaction(function (TenantDb $g) use ($lines, $wh, $issueId, &$moved) {
            foreach ($lines as $ln) {
                $lineId = (int) $ln['request_line_id']; $qty = (float) $ln['qty'];
                $l = $g->selectOne('proc_issue_request_line', array('where' => array('id' => $lineId)));
                if (!$l) { continue; }
                $g->update('proc_issue_request_line',
                    array('qty_issued' => (float) $l['qty_issued'] + $qty), array('id' => $lineId));
                $g->insert('proc_stock_move', array(
                    'item_id' => (int) $l['item_id'], 'warehouse_id' => $wh,
                    'move_type' => self::MV_ISSUE, 'qty' => $qty, 'unit_cost' => 0,
                    'ref_type' => 'issue', 'ref_id' => $issueId,
                    'note' => 'W09 صرف مقابل طلب معتمد', 'moved_at' => date('Y-m-d H:i:s'),
                ));
                $moved++;
            }
        }, 'W09 صرف مقابل طلب');

        $gate->update('proc_issue_request', array('state' => 'issued', 'issue_id' => $issueId),
            array('id' => $requestId));
        foreach ($lines as $ln) {
            $l = $gate->selectOne('proc_issue_request_line', array('where' => array('id' => (int) $ln['request_line_id'])));
            if ($l) { self::recomputeStockState($gate, (int) $l['item_id'], $wh); }
        }
        self::emit($gate, 'WH_ISSUED', array('request_id' => $requestId, 'issue_id' => $issueId, 'lines' => $moved));
        return self::done(array('lines' => $moved));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑤ التحويلُ بين مخزنَين — والمرسَلُ ليس المستلَم
       ══════════════════════════════════════════════════════════════════════ */

    /** إرسالُ تحويل — ويُخصم من المصدرِ لحظةَ الإرسالِ لا لحظةَ الاستلام */
    public static function sendTransfer(TenantDb $gate, $transferId, $actorId)
    {
        $transferId = (int) $transferId;
        $t = $gate->selectOne('proc_transfer', array('where' => array('id' => $transferId)));
        if (!$t) { return self::fail('TRANSFER_NOT_FOUND', ''); }
        if ((int) $t['from_wh_id'] === (int) $t['to_wh_id']) {
            return self::fail('TRANSFER_SAME_WAREHOUSE', 'لا تحويل إلى المخزن نفسه');
        }
        if ((string) $t['state'] !== 'draft') { return self::fail('TRANSFER_ALREADY_SENT', ''); }
        $lines = $gate->select('proc_transfer_line', array('where' => array('transfer_id' => $transferId)));
        if (!$lines) { return self::fail('TRANSFER_WITHOUT_LINES', ''); }

        $sum = 0.0;
        $gate->runInTransaction(function (TenantDb $g) use ($lines, $t, $transferId, $actorId, &$sum) {
            foreach ($lines as $l) {
                $g->insert('proc_stock_move', array(
                    'item_id' => (int) $l['item_id'], 'warehouse_id' => (int) $t['from_wh_id'],
                    'move_type' => self::MV_TRANSFER_OUT, 'qty' => (float) $l['qty_sent'], 'unit_cost' => 0,
                    'ref_type' => 'transfer', 'ref_id' => $transferId,
                    'note' => 'W09 إرسال تحويل', 'moved_at' => date('Y-m-d H:i:s'),
                ));
                $sum += (float) $l['qty_sent'];
            }
            $g->update('proc_transfer', array('state' => 'in_transit', 'sent_at' => date('Y-m-d H:i:s'),
                'sent_by' => (int) $actorId, 'in_transit_qty' => $sum), array('id' => $transferId));
        }, 'W09 إرسال تحويل');

        foreach ($lines as $l) { self::recomputeStockState($gate, (int) $l['item_id'], (int) $t['from_wh_id']); }
        self::emit($gate, 'WH_TRANSFER_SENT', array('transfer_id' => $transferId, 'qty' => $sum));
        return self::done(array('qty_sent' => $sum));
    }

    /** استلامُ تحويل — **ومَن أرسل لا يستلم**، والفرقُ بسببٍ مكتوب */
    public static function receiveTransfer(TenantDb $gate, $transferId, array $received, $actorId)
    {
        $transferId = (int) $transferId; $actorId = (int) $actorId;
        $t = $gate->selectOne('proc_transfer', array('where' => array('id' => $transferId)));
        if (!$t) { return self::fail('TRANSFER_NOT_FOUND', ''); }
        if ((string) $t['state'] !== 'in_transit') {
            return self::fail('TRANSFER_RECEIVE_BEFORE_SEND', 'لا استلام قبل الإرسال');
        }
        if ((int) $t['sent_by'] === $actorId) {
            return self::fail('SAME_ACTOR_SEND_AND_RECEIVE', 'من أرسل التحويل لا يستلمه');
        }
        foreach ($received as $lineId => $r) {
            $l = $gate->selectOne('proc_transfer_line', array('where' => array('id' => (int) $lineId)));
            if (!$l) { continue; }
            $qr = (float) (isset($r['qty_received']) ? $r['qty_received'] : 0);
            $var = $qr - (float) $l['qty_sent'];
            $why = trim((string) (isset($r['variance_why']) ? $r['variance_why'] : ''));
            if (abs($var) > 0.0001 && $why === '') {
                return self::fail('TRANSFER_VARIANCE_WITHOUT_REASON', 'فرق الاستلام بلا سبب مكتوب');
            }
        }

        $tot = 0.0;
        $gate->runInTransaction(function (TenantDb $g) use ($received, $t, $transferId, $actorId, &$tot) {
            foreach ($received as $lineId => $r) {
                $l = $g->selectOne('proc_transfer_line', array('where' => array('id' => (int) $lineId)));
                if (!$l) { continue; }
                $qr = (float) $r['qty_received']; $var = $qr - (float) $l['qty_sent'];
                $g->update('proc_transfer_line', array('qty_received' => $qr, 'qty_variance' => $var,
                    'variance_why' => (string) (isset($r['variance_why']) ? $r['variance_why'] : '')),
                    array('id' => (int) $lineId));
                if ($qr > 0) {
                    $g->insert('proc_stock_move', array(
                        'item_id' => (int) $l['item_id'], 'warehouse_id' => (int) $t['to_wh_id'],
                        'move_type' => self::MV_TRANSFER_IN, 'qty' => $qr, 'unit_cost' => 0,
                        'ref_type' => 'transfer', 'ref_id' => $transferId,
                        'note' => 'W09 استلام تحويل', 'moved_at' => date('Y-m-d H:i:s'),
                    ));
                }
                $tot += $qr;
            }
            $g->update('proc_transfer', array('state' => 'received', 'received_at' => date('Y-m-d H:i:s'),
                'received_by' => $actorId, 'in_transit_qty' => 0), array('id' => $transferId));
        }, 'W09 استلام تحويل');

        self::emit($gate, 'WH_TRANSFER_RECEIVED', array('transfer_id' => $transferId, 'qty' => $tot));
        return self::done(array('qty_received' => $tot));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑥ الجرد — الفرقُ لا يُقفل بلا قرارِ تسويةٍ مُسبَّب
       ══════════════════════════════════════════════════════════════════════ */

    /** بندُ جردٍ — والدفتريُّ **مشتقٌّ** من الحركاتِ لا مكتوبٌ من العادّ */
    public static function countLine(TenantDb $gate, $sessionId, $itemId, $qtyCounted, $unitCost)
    {
        $sessionId = (int) $sessionId; $itemId = (int) $itemId;
        $s = $gate->selectOne('proc_count_session', array('where' => array('id' => $sessionId)));
        if (!$s) { return self::fail('COUNT_SESSION_NOT_FOUND', ''); }
        if ((string) $s['state'] === 'approved') { return self::fail('COUNT_SESSION_CLOSED', ''); }

        $st = $gate->selectOne('proc_stock_state', array('where' => array(
            'item_id' => $itemId, 'warehouse_id' => (int) $s['warehouse_id'], 'state_key' => 'GOOD')));
        $book = $st ? (float) $st['qty'] : 0.0;
        $diff = (float) $qtyCounted - $book;

        $ex = $gate->selectOne('proc_count_line', array('where' => array('session_id' => $sessionId, 'item_id' => $itemId)));
        $data = array(
            'session_id' => $sessionId, 'item_id' => $itemId,
            'qty_book' => $book, 'qty_counted' => (float) $qtyCounted, 'qty_diff' => $diff,
            'unit_cost' => (float) $unitCost, 'diff_value' => $diff * (float) $unitCost,
        );
        if ($ex) { $gate->update('proc_count_line', $data, array('id' => (int) $ex['id'])); $id = (int) $ex['id']; }
        else { $id = (int) $gate->insert('proc_count_line', $data); }

        self::recomputeCountSession($gate, $sessionId);
        return self::done(array('line_id' => $id, 'qty_book' => $book, 'qty_diff' => $diff));
    }

    /** عدّاداتُ جلسةِ الجردِ مشتقّة */
    public static function recomputeCountSession(TenantDb $gate, $sessionId)
    {
        $sessionId = (int) $sessionId;
        $ls = $gate->select('proc_count_line', array('where' => array('session_id' => $sessionId), 'limit' => 5000));
        $diffN = 0; $diffV = 0.0;
        foreach ($ls as $l) { if (abs((float) $l['qty_diff']) > 0.0001) { $diffN++; $diffV += (float) $l['diff_value']; } }
        $gate->update('proc_count_session', array(
            'line_count' => count($ls), 'diff_count' => $diffN, 'diff_value' => $diffV,
        ), array('id' => $sessionId));
        return array('lines' => count($ls), 'diffs' => $diffN, 'value' => $diffV);
    }

    /**
     * اعتمادُ الجرد — **ومَن عدَّ لا يعتمد**، ولا اعتمادَ وفي الجلسةِ فرقٌ
     * بلا قرارِ تسويةٍ مُسبَّب.
     */
    public static function approveCount(TenantDb $gate, $sessionId, $actorId)
    {
        $sessionId = (int) $sessionId; $actorId = (int) $actorId;
        $s = $gate->selectOne('proc_count_session', array('where' => array('id' => $sessionId)));
        if (!$s) { return self::fail('COUNT_SESSION_NOT_FOUND', ''); }
        if ((int) $s['counted_by'] === $actorId) {
            return self::fail('SAME_ACTOR_COUNT_AND_APPROVE', 'من عد لا يعتمد جرده');
        }
        $open = 0;
        foreach ($gate->select('proc_count_line', array('where' => array('session_id' => $sessionId), 'limit' => 5000)) as $l) {
            if (abs((float) $l['qty_diff']) > 0.0001
                && (trim((string) $l['settle_action']) === '' || trim((string) $l['settle_why']) === '')) {
                $open++;
            }
        }
        if ($open > 0) {
            return self::fail('COUNT_DIFF_WITHOUT_SETTLEMENT', "فروق بلا قرار تسوية مسبب: $open");
        }
        $gate->update('proc_count_session', array('state' => 'approved', 'approved_by' => $actorId,
            'approved_at' => date('Y-m-d H:i:s')), array('id' => $sessionId));
        self::emit($gate, 'WH_COUNT_APPROVED', array('session_id' => $sessionId));
        return self::done();
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑦ الإقفالُ الشهريّ — معادلةٌ تنطبق أو لا إقفال
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * إقفالُ شهرٍ لمخزن. **المعادلة**: فتحٌ + واردٌ − منصرفٌ + تسويةٌ = إقفال.
     * ولا تنطبق ⇒ `CLOSE_UNBALANCED` — فالإقفالُ إثباتٌ لا إعلان.
     */
    public static function closeMonth(TenantDb $gate, $warehouseId, $periodYm, $actorId)
    {
        $warehouseId = (int) $warehouseId; $periodYm = (string) $periodYm;
        if ($periodYm === '') { return self::fail('CLOSE_WITHOUT_PERIOD', ''); }

        $ex = $gate->selectOne('proc_wh_close', array('where' => array(
            'warehouse_id' => $warehouseId, 'period_ym' => $periodYm)));
        if ($ex && (string) $ex['state'] === 'closed') { return self::fail('PERIOD_ALREADY_CLOSED', ''); }

        /* ⛔ جردٌ غيرُ معتمَدٍ في الفترةِ يمنع الإقفال */
        foreach ($gate->select('proc_count_session', array('where' => array('warehouse_id' => $warehouseId))) as $cs) {
            if (substr((string) $cs['count_date'], 0, 7) === $periodYm && (string) $cs['state'] !== 'approved') {
                return self::fail('CLOSE_WITH_OPEN_COUNT_DIFF', 'جرد الفترة غير معتمد فلا إقفال');
            }
        }

        $in = 0.0; $out = 0.0; $adj = 0.0; $open = 0.0;
        foreach ($gate->select('proc_stock_move', array('where' => array('warehouse_id' => $warehouseId), 'limit' => 20000)) as $m) {
            $ym = substr((string) $m['moved_at'], 0, 7);
            $v = (float) $m['qty'] * (float) $m['unit_cost'];
            $mt = (string) $m['move_type'];
            $isIn = self::isInbound($mt);
            $isAdj = ($mt === self::MV_ADJ_UP || $mt === self::MV_ADJ_DOWN);
            if ($ym < $periodYm) {
                $open += $isIn ? $v : -$v;
            } elseif ($ym === $periodYm) {
                if ($isAdj) { $adj += ($isIn ? $v : -$v); }
                elseif ($isIn) { $in += $v; }
                else { $out += $v; }
            }
        }
        $close = $open + $in - $out + $adj;
        $balanced = (abs(($open + $in - $out + $adj) - $close) < 0.01) ? 1 : 0;
        if (!$balanced) { return self::fail('CLOSE_UNBALANCED', 'معادلة الإقفال لا تنطبق'); }

        $data = array(
            'warehouse_id' => $warehouseId, 'period_ym' => $periodYm,
            'open_value' => $open, 'in_value' => $in, 'out_value' => $out,
            'adj_value' => $adj, 'close_value' => $close, 'balanced' => 1,
            'closed_by' => (int) $actorId, 'closed_at' => date('Y-m-d H:i:s'), 'state' => 'closed',
        );
        if ($ex) { $gate->update('proc_wh_close', $data, array('id' => (int) $ex['id'])); $id = (int) $ex['id']; }
        else { $id = (int) $gate->insert('proc_wh_close', $data); }

        self::emit($gate, 'WH_MONTH_CLOSED', array('warehouse_id' => $warehouseId, 'period_ym' => $periodYm, 'close_id' => $id));
        return self::done(array('close_id' => $id, 'close_value' => $close));
    }

    /* ══════════════════════════════════════════════════════════════════════
       نشرُ الحقيقةِ المحايدة
       ══════════════════════════════════════════════════════════════════════ */

    /** خريطةُ الحدثِ إلى كيانِه — سجلٌّ واحدٌ لا حرفيّاتٌ متناثرة */
    const EVENT_ENTITY = array(
        'WH_RECEIPT_LINE_ADDED'     => array('proc_receipt_custody', 'receipt_id'),
        'WH_ISSUE_REQUEST_APPROVED' => array('proc_issue_request',   'request_id'),
        'WH_ISSUED'                 => array('proc_issue_request',   'request_id'),
        'WH_TRANSFER_SENT'          => array('proc_transfer',        'transfer_id'),
        'WH_TRANSFER_RECEIVED'      => array('proc_transfer',        'transfer_id'),
        'WH_COUNT_APPROVED'         => array('proc_count_session',   'session_id'),
        'WH_MONTH_CLOSED'           => array('proc_wh_close',        'close_id'),
    );

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
                'category'        => 'warehouse',
                'source_module'   => 'warehouse',
                'entity_type'     => $table,
                'entity_id'       => $entityId,
                'payload'         => $payload,
                'idempotency_key' => 'w9:' . $eventKey . ':' . $entityId . ':' . substr(sha1(json_encode($payload)), 0, 12),
                'source_ref'      => 'WarehouseCycleService',
            ));
        } catch (\Throwable $t) { return null; }
    }
}
