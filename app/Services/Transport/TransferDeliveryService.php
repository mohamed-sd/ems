<?php
/**
 * TransferDeliveryService — تسليمُ أمرِ الترحيلِ وإقفالُه (FN-08 · CS-05/CS-07)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العيبُ المُصحَّح (P0 · FIXC-0044):
 *   ① **مستندُ التسليمِ لا يُخزَّن** — كان النصُّ يُلقى في جسمِ الحدثِ بلا مرجعٍ
 *      ولا وقتٍ ولا شاهد، فلا يُستدعى ولا يُدقَّق.
 *   ② **تكرارُ الإرسالِ يُدرج حدثًا كلَّ مرة** — لا مفتاحَ عطالةٍ إطلاقًا، فثلاثُ
 *      نقراتٍ = ثلاثةُ أحداثِ تسليمٍ لأمرٍ واحد.
 *   ③ **الإقفالُ يقع بلا مستندِ تسليمٍ مخزَّن** — فتُحمَّل تكلفةٌ على مشروعٍ بلا
 *      سندٍ يُثبت وصولَ البضاعة.
 *
 * ◆ العلاج: مستندٌ مخزَّنٌ بمرجعِه ووقتِه وشاهده · ومفتاحُ عطالةٍ **مركَّبٌ** على
 *   (أمر × نوعِ الحدث) بفريدٍ في القاعدةِ لا بفحصٍ في التطبيق · وحارسُ إقفالٍ
 *   يرفض الإقفالَ قبلَ تخزينِ المستند.
 */

declare(strict_types=1);

namespace App\Services\Transport;

class TransferDeliveryService
{
    /** @var \mysqli */
    private $conn;

    public function __construct(\mysqli $conn)
    {
        $this->conn = $conn;
    }

    /** أخُزِّن مستندُ تسليمٍ لهذا الأمر؟ (الشاهدُ الذي يفتح بابَ الإقفال) */
    public function deliveryDocOf(int $companyId, int $orderId): ?array
    {
        $st = $this->conn->prepare(
            "SELECT id, doc_ref, doc_note, witness_name, delivered_at, created_by
               FROM transfer_delivery_docs
              WHERE company_id = ? AND order_id = ? ORDER BY id DESC LIMIT 1");
        if (!$st) { return null; }
        $st->bind_param('ii', $companyId, $orderId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    /**
     * يُوثِّق التسليم: مستندٌ مخزَّنٌ + حدثٌ واحدٌ مهما تكرر الإرسال.
     *
     * @return array{ok:bool,msg:string,replay:bool,doc_id:int}
     */
    public function confirmDelivery(int $companyId, int $orderId, string $note, string $witness, int $actorId): array
    {
        if ($orderId <= 0) { return array('ok' => false, 'msg' => 'أمر غير صالح (422)', 'replay' => false, 'doc_id' => 0); }
        if (trim($witness) === '') {
            return array('ok' => false, 'msg' => 'شاهد التسليم إلزامي — لا تسليم بلا شاهد مسمى (422)', 'replay' => false, 'doc_id' => 0);
        }

        // الأمرُ موجودٌ وواصلٌ ويخصُّ هذا الكيان.
        $st = $this->conn->prepare("SELECT stage FROM transfer_orders
                                     WHERE id = ? AND company_id = ? AND is_deleted = 0 LIMIT 1");
        if (!$st) { return array('ok' => false, 'msg' => 'خطأ داخلي (500)', 'replay' => false, 'doc_id' => 0); }
        $st->bind_param('ii', $orderId, $companyId);
        $st->execute();
        $ord = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$ord) { return array('ok' => false, 'msg' => 'أمر ترحيل غير موجود (404)', 'replay' => false, 'doc_id' => 0); }
        if ($ord['stage'] !== 'arrived') {
            return array('ok' => false, 'msg' => 'الأمر ليس واصلا — لا تسليم قبل الوصول (409)', 'replay' => false, 'doc_id' => 0);
        }

        // ◆ العطالة: المستندُ موجودٌ سلفًا ⇒ لا حدثَ ثانٍ ولا صفَّ ثانٍ.
        $existing = $this->deliveryDocOf($companyId, $orderId);
        if ($existing !== null) {
            return array('ok' => true, 'replay' => true, 'doc_id' => (int) $existing['id'],
                'msg' => 'سجل التسليم سلفا بالمرجع ' . $existing['doc_ref']
                       . ' في ' . $existing['delivered_at'] . ' — لم يكرر الحدث');
        }

        $this->conn->begin_transaction();
        try {
            $docRef = 'DLV-' . $orderId . '-' . date('ymdHis');
            $ins = $this->conn->prepare(
                "INSERT INTO transfer_delivery_docs
                   (company_id, order_id, doc_ref, doc_note, witness_name, delivered_at, created_by, created_at)
                 VALUES (?,?,?,?,?,NOW(),?,NOW())");
            if (!$ins) { throw new \RuntimeException('doc prepare: ' . $this->conn->error); }
            $n = ($note !== '' ? $note : 'تسليم مؤكد');
            $ins->bind_param('iisssi', $companyId, $orderId, $docRef, $n, $witness, $actorId);
            if (!$ins->execute()) { throw new \RuntimeException('doc insert: ' . $ins->error); }
            $docId = (int) $this->conn->insert_id;
            $ins->close();

            // الحدثُ بمفتاحٍ مركَّبٍ فريدٍ: (أمر × نوع) — الفريدُ في القاعدةِ هو
            // الحكم؛ الفحصُ في التطبيقِ وحدَه يمرّره طلبان متزامنان.
            $ev = $this->conn->prepare(
                "INSERT INTO transfer_events (company_id, order_id, event_type, body, actor_user_id, sync_uuid)
                 VALUES (?,?,'delivered',?,?,?)");
            if (!$ev) { throw new \RuntimeException('event prepare: ' . $this->conn->error); }
            $body = 'مستند تسليم ' . $docRef . ' · شاهد: ' . $witness . ' · ' . $n;
            $uuid = 'dlv:' . $companyId . ':' . $orderId;   // مركَّبٌ وفريدٌ في القاعدة
            // الأنواعُ بترتيبِ الأعمدةِ حرفًا بحرف: i i s i s
            $ev->bind_param('iisis', $companyId, $orderId, $body, $actorId, $uuid);
            if (!$ev->execute()) { throw new \RuntimeException('event insert: ' . $ev->error); }
            $ev->close();

            $this->conn->commit();
            return array('ok' => true, 'replay' => false, 'doc_id' => $docId,
                'msg' => 'وثق تسليم الأمر #' . $orderId . ' بالمرجع ' . $docRef
                       . ' — أتم الدورة من شاشة الإقفال وتحميل التكلفة');
        } catch (\Throwable $e) {
            $this->conn->rollback();
            error_log('TransferDeliveryService::confirmDelivery: ' . $e->getMessage());
            if (stripos($e->getMessage(), 'Duplicate') !== false || $this->conn->errno === 1062) {
                $d = $this->deliveryDocOf($companyId, $orderId);
                return array('ok' => true, 'replay' => true, 'doc_id' => $d ? (int) $d['id'] : 0,
                    'msg' => 'سجل التسليم سلفا — لم يكرر الحدث');
            }
            return array('ok' => false, 'replay' => false, 'doc_id' => 0,
                'msg' => 'تعذر توثيق التسليم — لم يكتب شيء (ERR-TRS-1045)');
        }
    }

    /**
     * الإقفالُ بتكلفةٍ — **مرفوضٌ بلا مستندِ تسليمٍ مخزَّن** (FIXC-0048).
     *
     * @return array{ok:bool,msg:string,replay:bool}
     */
    public function closeWithCost(int $companyId, int $orderId, float $cost, int $actorId): array
    {
        if ($cost <= 0) {
            return array('ok' => false, 'replay' => false,
                'msg' => 'التكلفة الفعلية إلزامية للإقفال — ولا إقفال بتكلفة صفر (422)');
        }

        // ◆ الحارسُ الترتيبيُّ: لا إقفالَ قبلَ تخزينِ مستندِ التسليم.
        $doc = $this->deliveryDocOf($companyId, $orderId);
        if ($doc === null) {
            return array('ok' => false, 'replay' => false,
                'msg' => 'لا إقفال بتكلفة قبل تخزين مستند التسليم — وثق التسليم أولا (409)');
        }

        /* ══ INJ-0310 · حارسٌ واحدٌ برسالةٍ واحدةٍ للشاشتين ═══════════════════════
             نصُّ القبول: «**الإقفالُ من أيِّ الشاشتين يُرفض بالرسالة نفسها** إن لم
             يكن للأمر متحمِّلٌ ومراكزُ تكلفةٍ لكل بند».
             والشرطُ المنسوخُ في شاشتين شرطان يتفرّقان مع أوّلِ تعديل — فالحارسُ
             دالّةٌ واحدةٌ في `Transport/trs_helpers.php` تنادِيها الخدمةُ وتنادِيها
             الشاشةُ، **فالرسالةُ واحدةٌ لأنَّ المصدرَ واحد**. */
        require_once dirname(__DIR__, 3) . '/Transport/trs_helpers.php';
        if (function_exists('trs_close_gate') && function_exists('trs_gate')) {
            $g = trs_close_gate(trs_gate(false), $orderId);
            if (empty($g['ok'])) {
                return array('ok' => false, 'replay' => false, 'msg' => $g['reason']);
            }
        }

        $this->conn->begin_transaction();
        try {
            /* ══ INJ-0062 · تبنّي `App\Core\ProcessedOperations` ══════════════
               الصنفُ كان **مبنيًّا بصفرِ مستهلك**. وهو الطبقةُ الثالثةُ من
               العطالة — «عطالةُ المستهلكِ على (المستندِ × الأثر): صمامُ الأمانِ
               عند إعادةِ التشغيلِ أو تكرارِ الحدثِ لمستندٍ واحد» (N-06 ركن ③) —
               وهي غيرُ عطالةِ الطلبِ في `ems_post_idempotency` (فاعلٌ × فعلٌ ×
               محتوى) وغيرُ عطالةِ المروحةِ في `fin_event_links`. الطبقاتُ ثلاثٌ
               لا تُخلط، وهذه كانت غائبةً كلَّها.
               ◆ الأثرُ المحروسُ هنا: **تحميلُ التكلفةِ الفعليةِ على المشروع** —
                 وتكرارُه يُضاعف تكلفةَ مشروعٍ بلا سند. والادعاءُ **داخلَ
                 المعاملةِ** كما تُلزم الوثيقةُ، فيرتدُّ معها إن فشل الإقفال. */
            require_once dirname(__DIR__, 2) . '/Core/ProcessedOperations.php';
            if (!\App\Core\ProcessedOperations::claim(
                    $this->conn, 'transport', 'transfer_order', $orderId, 'actual_cost')) {
                $this->conn->rollback();
                return array('ok' => true, 'replay' => true,
                    'msg' => 'حملت تكلفة هذا الأمر سلفا — لم تضاعف (عطالة المستند)');
            }

            $up = $this->conn->prepare("UPDATE transfer_orders SET stage='closed', actual_cost_usd=?
                                         WHERE id=? AND company_id=? AND stage='arrived'");
            if (!$up) { throw new \RuntimeException('close prepare: ' . $this->conn->error); }
            $up->bind_param('dii', $cost, $orderId, $companyId);
            if (!$up->execute()) { throw new \RuntimeException('close: ' . $up->error); }
            $affected = $up->affected_rows;
            $up->close();
            if ($affected <= 0) {
                $this->conn->rollback();
                return array('ok' => false, 'replay' => true, 'msg' => 'لم يقفل — الأمر ليس واصلا أو أقفل سلفا (409)');
            }

            $ev = $this->conn->prepare(
                "INSERT INTO transfer_events (company_id, order_id, event_type, body, actor_user_id, sync_uuid)
                 VALUES (?,?,'closed',?,?,?)");
            if (!$ev) { throw new \RuntimeException('event prepare: ' . $this->conn->error); }
            $body = 'أقفل بتكلفة فعلية ' . $cost . '$ · بسند التسليم ' . $doc['doc_ref'];
            $uuid = 'cls:' . $companyId . ':' . $orderId;
            $ev->bind_param('iisis', $companyId, $orderId, $body, $actorId, $uuid);
            if (!$ev->execute()) { throw new \RuntimeException('event insert: ' . $ev->error); }
            $ev->close();

            /* ══ INJ-0084 · INJ-0306 · سطرُ التحميلِ بقيمٍ حيّةٍ ومركزٍ حقيقيّ ══════
                 كان السطرُ يُكتب بـ`cost_type='actual_total'` و`cost_bearer='project'`
                 و`analytic_cost_center='PRJ'`، **والقيمتان الأوليان خارج تعدادَيهما**:
                   cost_type   ∈ (fuel · labor · contractor · misc · permit)
                   cost_bearer ∈ (client · company · new_client)
                 و`sql_mode` **خالٍ**، فالخارجُ عن التعدادِ **يُبتر إلى `''` بتحذيرٍ
                 1265 لا بخطأ** — فيمضي الإقفالُ مُعلنًا النجاحَ ويُكتب دفترُ التحميلِ
                 بقيمٍ خاوية. **٩ أسطرَ مقيسةٍ في القاعدةِ بهذه الحال.**
               ◆ فالمصدرُ صار الأمرَ نفسَه: متحمِّلُه ومركزُ تكلفتِه — «تحميلٌ على
                 مصدرها» لا على قيمةٍ نائبة. و`contractor` هو الحيُّ المقابلُ لأجرِ
                 النقلِ في التعداد.
               ◆ **ويُقرأ الصفُّ بعد كتابتِه**: البترُ الصامتُ لا يُكشف من مُرجَعِ
                 `execute` — فما لم يُطابق المكتوبُ المقصودَ ارتدَّت المعاملةُ
                 و**ظهرت رسالةُ خطأٍ لا رسالةُ نجاح** (نصُّ القبولِ حرفًا). */
            $ordRow = null;
            $oq = $this->conn->prepare(
                'SELECT cost_bearer, analytic_cost_center, tariff_currency
                   FROM transfer_orders WHERE id = ? AND company_id = ? LIMIT 1');
            if ($oq) {
                $oq->bind_param('ii', $orderId, $companyId);
                $oq->execute();
                $ordRow = $oq->get_result()->fetch_assoc();
                $oq->close();
            }
            $bearer = $ordRow && trim((string) $ordRow['cost_bearer']) !== ''
                ? (string) $ordRow['cost_bearer'] : '';
            $center = $ordRow ? trim((string) $ordRow['analytic_cost_center']) : '';
            $curr   = $ordRow && trim((string) $ordRow['tariff_currency']) !== ''
                ? (string) $ordRow['tariff_currency'] : 'USD';
            if ($bearer === '') {
                throw new \RuntimeException(
                    'TRS-422: لا تحميل بلا متحمل في الأمر — حدده في نموذج الأمر أولا');
            }
            if ($center === '') {
                throw new \RuntimeException(
                    'TRS-422: لا تحميل بلا مركز تكلفة في الأمر — «تحميل على مصدرها»');
            }
            $type = 'contractor';
            $cl = $this->conn->prepare(
                "INSERT INTO transfer_cost_lines
                   (company_id, order_id, cost_type, amount_local, amount_usd, currency,
                    fx_rate, cost_bearer, analytic_cost_center, created_at)
                 VALUES (?,?,?,?,?,?,1,?,?,NOW())");
            if (!$cl) { throw new \RuntimeException('cost prepare: ' . $this->conn->error); }
            $cl->bind_param('iisddsss', $companyId, $orderId, $type, $cost, $cost, $curr, $bearer, $center);
            if (!$cl->execute()) { throw new \RuntimeException('cost insert: ' . $cl->error); }
            $lineId = (int) $this->conn->insert_id;
            $cl->close();

            /* قراءةُ ما كُتب فعلًا — فالبترُ يُعلن نفسَه هنا أو لا يُعلن أبدًا */
            $chk = $this->conn->query('SELECT cost_type, cost_bearer, analytic_cost_center, amount_usd
                                         FROM transfer_cost_lines WHERE id = ' . $lineId);
            $wrote = $chk ? $chk->fetch_assoc() : null;
            if (!$wrote
                || (string) $wrote['cost_type'] !== $type
                || (string) $wrote['cost_bearer'] !== $bearer
                || trim((string) $wrote['analytic_cost_center']) === ''
                || abs((float) $wrote['amount_usd'] - $cost) > 0.005) {
                throw new \RuntimeException(
                    'TRS-500: سطر التحميل كتب مبتورا (بتر صامت بتحذير 1265) — '
                    . 'النوع «' . ($wrote ? $wrote['cost_type'] : '?') . '» '
                    . 'والمتحمل «' . ($wrote ? $wrote['cost_bearer'] : '?') . '»');
            }

            $this->conn->commit();
            return array('ok' => true, 'replay' => false,
                'msg' => 'أقفل الأمر #' . $orderId . ' بتكلفة ' . $cost . '$ محملة على مشروعه — بسند التسليم ' . $doc['doc_ref']);
        } catch (\Throwable $e) {
            $this->conn->rollback();
            error_log('TransferDeliveryService::closeWithCost: ' . $e->getMessage());
            return array('ok' => false, 'replay' => false, 'msg' => 'تعذر الإقفال — لم يكتب شيء (ERR-TRS-1046)');
        }
    }

    /**
     * تأكيدُ وصولِ أمرِ نقل — استُخرج من `Transport/transfer_in_transit.php`
     * امتثالًا لـCS-05 (AC-F6).
     *
     * ◆ التقدُّمُ مشروطٌ في الجملةِ نفسِها (`stage='in_transit'`) لا بفحصٍ
     *   سابقٍ عليها: نقرتان متزامنتان لا تُنتجان وصولَين، و`affected_rows`
     *   هو الحَكَم.
     * ◆ وواقعةُ الوصولِ تُكتب **فقط** إن تقدّم الأمرُ فعلًا — وإلا امتلأ سجلُّ
     *   الوقائعِ بوصولاتٍ لم تقع. وكانت في الأصلَ تُبنى بالوصلِ النصيِّ
     *   للمتغيّرات، فصارت مُعامَلاتٍ مربوطة.
     *
     * @return array{ok:bool,msg:string}
     */
    public static function confirmArrival($conn, $companyId, $orderId, $userId)
    {
        $cid = (int) $companyId; $oid = (int) $orderId; $uid = (int) $userId;

        $st = $conn->prepare(
            "UPDATE transfer_orders
                SET stage = 'arrived', arrival_datetime = NOW()
              WHERE id = ? AND company_id = ? AND stage = 'in_transit'"
        );
        if (!$st) {
            error_log('TransferDeliveryService::confirmArrival prepare: ' . $conn->error);
            return array('ok' => false, 'msg' => 'تعذر تسجيل الوصول (ERR-TRS-PREP)');
        }
        $st->bind_param('ii', $oid, $cid);
        if (!$st->execute()) {
            error_log('TransferDeliveryService::confirmArrival execute: ' . $st->error);
            $st->close();
            return array('ok' => false, 'msg' => 'تعذر تسجيل الوصول (ERR-TRS-EXEC)');
        }
        $advanced = $st->affected_rows > 0;
        $st->close();

        if (!$advanced) {
            return array('ok' => false, 'msg' => 'لم يتقدم — الأمر ليس في الطريق (409)');
        }

        $ev = $conn->prepare(
            "INSERT INTO transfer_events (company_id, order_id, event_type, body, actor_user_id)
             VALUES (?, ?, 'arrived', 'وصولٌ مؤكَّدٌ من شاشة الحركة في الطريق', ?)"
        );
        if ($ev) {
            $ev->bind_param('iii', $cid, $oid, $uid);
            if (!$ev->execute()) { error_log('TransferDeliveryService::confirmArrival event: ' . $ev->error); }
            $ev->close();
        } else {
            error_log('TransferDeliveryService::confirmArrival event prepare: ' . $conn->error);
        }

        return array('ok' => true, 'msg' => "سجل وصول الأمر #{$oid} — انتقل إلى «الوصول والتسليم»");
    }
}
