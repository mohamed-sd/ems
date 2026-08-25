<?php
/**
 * EventDeliveryWorker — عاملُ التسليمِ بالعطالةِ والتباعدِ وصندوقِ الموتى
 * (ENG-01 · TSP-0227..0244 · F-13 · F-14 · CK-12 · CK-13)
 * ───────────────────────────────────────────────────────────────────────────
 * ستُّ حالاتٍ مسمّاةٌ لا واحدة:
 *   published → claimed → processing → processed
 *                       ↘ failed (بتباعدٍ متزايد) → … → dlq
 *
 * الضماناتُ الثلاثةُ التي يقوم عليها:
 *  ① العطالة (F-14): idempotency_key = SHA2(outbox_id|consumer_key, 256) فريدٌ
 *    في القاعدة. فخمسُ إعاداتٍ لتسليمٍ واحدٍ تُنتج صفًّا واحدًا — يفرضه uq_idem
 *    لا الكود، ولو نادى العاملُ نفسَه خمسَ مرات.
 *  ② الالتقاطُ ذرّيٌّ بشرطٍ في WHERE — ROW_COUNT()=1 يعني الالتقاط. فعاملانِ
 *    على تسليمٍ واحدٍ لا يُنتجان أثرًا مزدوجًا.
 *  ③ التباعدُ المتزايد (F-13): 1 · 4 · 16 · 64 · 256 ثانيةً ثم صندوقُ الموتى.
 *    والصيغةُ قادحٌ في القاعدةِ (trg_delivery_backoff) لا حسابٌ في PHP —
 *    «الصيغُ قوادحُ أو مناظر».
 *
 * ولا يُحذف صفٌّ أبدًا: الفشلُ يُوسَم والعزلُ يُوسَم، والدفترُ يبقى كاملًا.
 * (الموزِّعُ القديم EventDispatcher كان يحذف عند النجاح — فلم يكن للناقلِ دفترٌ
 *  يُقرأ، ومن هنا «صفرُ تسليمٍ حقيقيٍّ» رغم 21,211 واقعة.)
 */

namespace App\Services\Bus;

require_once __DIR__ . '/../../../includes/catch_log.php';
require_once __DIR__ . '/Consumers/GovernanceWatchConsumer.php';

class EventDeliveryWorker
{
    /** @var string معرّفُ هذا العامل — يفصل عاملًا عن عامل في السجل. */
    private $workerId;
    /** @var \mysqli */
    private $conn;

    public function __construct(\mysqli $conn, $workerId = null)
    {
        $this->conn = $conn;
        $this->workerId = $workerId !== null
            ? (string) $workerId
            : ('w' . getmypid() . '-' . substr(bin2hex(random_bytes(4)), 0, 8));
    }

    public function workerId() { return $this->workerId; }

    /**
     * دورةُ تسليمٍ واحدة: يلتقط ما استحق ويسلّمه.
     *
     * @param  int   $limit سقفُ الدفعة
     * @return array{claimed:int, processed:int, failed:int, dlq:int, skipped:int}
     */
    public function runOnce($limit = 50)
    {
        $st = array('claimed' => 0, 'processed' => 0, 'failed' => 0, 'dlq' => 0, 'skipped' => 0);

        $due = $this->conn->query(
            "SELECT `id` FROM `ems_event_deliveries`
              WHERE `state` IN ('published','failed')
                AND `outbox_id` > 0
                AND (`next_attempt_at` IS NULL OR `next_attempt_at` <= NOW(3))
              ORDER BY `id` ASC
              LIMIT " . max(1, (int) $limit)
        );
        if (!$due) { return $st; }

        $ids = array();
        while ($row = $due->fetch_row()) { $ids[] = (int) $row[0]; }

        foreach ($ids as $id) {
            $r = $this->deliverOne($id);
            if ($r === null) { $st['skipped']++; continue; }
            $st['claimed']++;
            $st[$r]++;
        }
        return $st;
    }

    /**
     * تسليمُ صفٍّ واحدٍ بالالتقاطِ الذرّيّ.
     *
     * @return string|null 'processed'|'failed'|'dlq' — وnull أي لم يُلتقط (سبقه عامل)
     */
    public function deliverOne($deliveryId)
    {
        $deliveryId = (int) $deliveryId;

        // ② الالتقاطُ الذرّيّ — الشرطُ في WHERE هو القفل. ROW_COUNT()=1 يعني الالتقاط.
        $cl = $this->conn->prepare(
            "UPDATE `ems_event_deliveries`
                SET `state` = 'claimed', `claimed_at` = NOW(3), `fail_code` = NULL, `fail_text` = NULL
              WHERE `id` = ?
                AND `state` IN ('published','failed')
                AND (`next_attempt_at` IS NULL OR `next_attempt_at` <= NOW(3))"
        );
        $cl->bind_param('i', $deliveryId);
        $cl->execute();
        $got = $this->conn->affected_rows;
        $cl->close();
        if ($got !== 1) { return null; }   // سبقه عاملٌ آخر — لا أثرَ مزدوج

        $d = $this->loadDelivery($deliveryId);
        if (!$d) { return null; }

        $event = $this->loadEvent((int) $d['outbox_id']);
        if (!$event) {
            return $this->markFailed($deliveryId, $d, 'NO_EVENT',
                'صف الصادر #' . $d['outbox_id'] . ' غير موجود');
        }

        $sub = $this->loadSubscription((string) $event['event_key'], (string) $d['consumer_key']);
        if (!$sub) {
            return $this->markFailed($deliveryId, $d, 'NO_SUB',
                'لا اشتراك نشطا للمستهلك «' . $d['consumer_key'] . '» على «' . $event['event_key'] . '»');
        }

        // processing — الحالةُ تُعلن أن التنفيذَ جارٍ (CK-12 يرصد العالقَ فوقَ ساعة)
        $this->conn->query("UPDATE `ems_event_deliveries` SET `state`='processing' WHERE `id`={$deliveryId}");

        try {
            $resultRef = $this->invoke($sub, $event);
            if ($resultRef === null || $resultRef === '') {
                // «◆ نجاحٌ بلا مرجعٍ مرفوض» — chk_result يرفضه في القاعدةِ أيضًا
                return $this->markFailed($deliveryId, $d, 'NO_RESULT_REF',
                    'المستهلك أعاد نجاحا بلا مرجع — والقيد chk_result يرفضه');
            }
            return $this->markProcessed($deliveryId, (int) $d['outbox_id'], (string) $resultRef);
        } catch (\Throwable $t) {
            return $this->markFailed($deliveryId, $d, 'HANDLER_ERROR',
                mb_substr($t->getMessage(), 0, 2000));
        }
    }

    /** نداءُ المعالجِ المسجَّل — الصنفُ من السجلِّ لا من قائمةٍ في الكود. */
    private function invoke(array $sub, array $event)
    {
        $class  = (string) $sub['consumer_class'];
        $method = $sub['consumer_method'] !== null && $sub['consumer_method'] !== ''
            ? (string) $sub['consumer_method'] : 'handle';

        if (!class_exists($class)) {
            // المستهلكونَ القائمونَ (EffectFanout وأخواتُه) يعتمدون مُحمِّلَ
            // app/bootstrap.php، والعاملُ قد يُنادى من سياقٍ لم يحمّله. فيُحمَّل
            // الصنفُ بمسارِه الاصطلاحيِّ قبلَ الحكمِ بأنه مفقود — وإلا سقط
            // مستهلكٌ سليمٌ لسببٍ في العاملِ لا فيه.
            $rel = str_replace('\\', '/', $class);
            foreach (array(
                dirname(__DIR__, 2) . '/' . preg_replace('#^App/#', '', $rel) . '.php',
                dirname(__DIR__, 3) . '/' . $rel . '.php',
            ) as $cand) {
                if (is_readable($cand)) { require_once $cand; break; }
            }
        }
        if (!class_exists($class)) {
            throw new \RuntimeException('صنف المستهلك «' . $class . '» غير محمل — سجله أو صحح اسمه');
        }
        $obj = new $class();
        if (!method_exists($obj, $method)) {
            throw new \RuntimeException('الطريقة «' . $method . '» غير موجودة في ' . $class);
        }
        return $obj->$method($event, $this->conn);
    }

    /** نجاح: مرجعٌ إلزاميٌّ + عدّادُ الصادرِ يتقدّم. */
    private function markProcessed($deliveryId, $outboxId, $resultRef)
    {
        $u = $this->conn->prepare(
            "UPDATE `ems_event_deliveries`
                SET `state`='processed', `processed_at`=NOW(3), `result_ref`=?,
                    `fail_code`=NULL, `fail_text`=NULL, `next_attempt_at`=NULL
              WHERE `id`=?"
        );
        $u->bind_param('si', $resultRef, $deliveryId);
        $ok = $u->execute();
        $err = $u->error;
        $u->close();
        if (!$ok) { throw new \RuntimeException('markProcessed failed: ' . $err); }

        $this->conn->query(
            "UPDATE `ems_business_events`
                SET `delivered_ok` = (SELECT COUNT(*) FROM `ems_event_deliveries`
                                       WHERE `outbox_id`=" . (int) $outboxId . " AND `state`='processed')
              WHERE `id`=" . (int) $outboxId
        );
        return 'processed';
    }

    /**
     * فشل: يُوسَم برمزٍ (chk_fail يلزمه) ويتقدّم attempt_no فيقدح القادحُ موعدَ
     * الإعادة (F-13). وعند استنفادِ المحاولاتِ يُعزل في صندوقِ الموتى.
     */
    private function markFailed($deliveryId, array $d, $code, $text)
    {
        $max = (int) $this->maxAttemptsFor($d);
        $next = (int) $d['attempt_no'] + 1;
        // «1 · 4 · 16 · 64 · 256 ثانيةً ثم صندوقُ الموتى» — خمسُ فتراتِ تباعدٍ
        // كاملةً تسبق العزل، فالعزلُ عند تجاوزِ الحدِّ لا عند بلوغِه.
        $toDlq = $next > $max;
        $state = $toDlq ? 'dlq' : 'failed';

        $u = $this->conn->prepare(
            "UPDATE `ems_event_deliveries`
                SET `state`=?, `attempt_no`=`attempt_no`+1, `attempts`=`attempts`+1,
                    `fail_code`=?, `fail_text`=?,
                    `processed_at` = CASE WHEN ? = 'dlq' THEN NOW(3) ELSE `processed_at` END
              WHERE `id`=?"
        );
        $u->bind_param('ssssi', $state, $code, $text, $state, $deliveryId);
        $ok = $u->execute();
        $err = $u->error;
        $u->close();
        if (!$ok) { throw new \RuntimeException('markFailed failed: ' . $err); }

        $ob = (int) $d['outbox_id'];
        $this->conn->query(
            "UPDATE `ems_business_events`
                SET `delivered_failed` = (SELECT COUNT(*) FROM `ems_event_deliveries`
                                           WHERE `outbox_id`={$ob} AND `state` IN ('failed','dlq')),
                    `in_dlq` = (SELECT COUNT(*) > 0 FROM `ems_event_deliveries`
                                 WHERE `outbox_id`={$ob} AND `state`='dlq')
              WHERE `id`={$ob}"
        );

        if ($toDlq) { $this->alertDlq($d, $code, $text); }
        return $toDlq ? 'dlq' : 'failed';
    }

    /** «◆ والعزلُ بإنذارٍ لا بصمت». فشلُ الإنذارِ لا يوقف العزل. */
    private function alertDlq(array $d, $code, $text)
    {
        try {
            $title = mb_substr(
                'صندوق الموتى: تسليم #' . $d['id'] . ' للمستهلك «' . $d['consumer_key']
                . '» بعد ' . ((int) $d['attempt_no'] + 1) . ' محاولات — ' . $code . ': ' . $text, 0, 195);
            $st = $this->conn->prepare(
                "INSERT INTO `fin_notifications` (`company_id`,`target_level`,`title`,`link`)
                 VALUES (?, 'finance_manager', ?, 'Governance/bus_deliveries.php')"
            );
            $co = max(1, (int) $d['company_id']);
            $st->bind_param('is', $co, $title);
            $st->execute();
            $st->close();
        } catch (\Throwable $t) {
            // الإنذارُ غلافٌ والعزلُ نواة: فشلُ الإشعارِ لا يُلغي العزلَ — لكنه
            // **لا يُبتلَع**: يُسجَّل في سجلِّ الأخطاءِ وفي حقلِ فشلِ الصفِّ نفسِه،
            // فيراه من يقرأ الدفترَ لا من يقرأ السجلَّ وحدَه. (AC-F10)
            ems_catch_ignored($t, __METHOD__, '[bus] dlq alert failed');
            error_log('[bus] dlq alert failed: ' . $t->getMessage());
            try {
                $u = $this->conn->prepare(
                    "UPDATE `ems_event_deliveries`
                        SET `fail_text` = CONCAT(COALESCE(`fail_text`,''), ' | إنذارُ العزلِ لم يُرسَل: ', ?)
                      WHERE `id` = ?");
                $msg = mb_substr($t->getMessage(), 0, 180);
                $did = (int) $d['id'];
                $u->bind_param('si', $msg, $did);
                $u->execute();
                $u->close();
            } catch (\Throwable $t2) {
                ems_catch_ignored($t2, __METHOD__, '[bus] dlq alert note failed');
                error_log('[bus] dlq alert note failed: ' . $t2->getMessage());
            }
        }
    }

    /**
     * قرارٌ في صندوقِ الموتى (bus.dlq.decide): إعادةٌ إلى الطابور أو إغلاقٌ بسبب.
     * ولا يُحذف صفٌّ — القرارُ يُسجَّل على الصفِّ نفسِه.
     */
    public function decideDlq($deliveryId, $decision, $reason, $decidedBy = 0)
    {
        $deliveryId = (int) $deliveryId;
        if (!in_array($decision, array('requeue', 'close'), true)) {
            return array('ok' => false, 'reason' => 'قرار غير معروف — requeue أو close');
        }
        if (trim((string) $reason) === '') {
            return array('ok' => false, 'reason' => 'سبب القرار إلزامي — ولا قرار بلا سبب');
        }

        if ($decision === 'requeue') {
            $u = $this->conn->prepare(
                "UPDATE `ems_event_deliveries`
                    SET `state`='published', `attempt_no`=0, `next_attempt_at`=NOW(3),
                        `fail_code`=NULL, `fail_text`=CONCAT('[requeue by ', ?, '] ', ?)
                  WHERE `id`=? AND `state`='dlq'"
            );
            $by = (string) $decidedBy; $rs = (string) $reason;
            $u->bind_param('ssi', $by, $rs, $deliveryId);
        } else {
            // إغلاقٌ بسبب — يبقى في dlq ويحمل رمزَ الإغلاقِ وسببَه (chk_fail مستوفًى)
            $u = $this->conn->prepare(
                "UPDATE `ems_event_deliveries`
                    SET `fail_code`='DLQ_CLOSED', `fail_text`=CONCAT('[closed by ', ?, '] ', ?),
                        `processed_at`=NOW(3)
                  WHERE `id`=? AND `state`='dlq'"
            );
            $by = (string) $decidedBy; $rs = (string) $reason;
            $u->bind_param('ssi', $by, $rs, $deliveryId);
        }
        $u->execute();
        $n = $this->conn->affected_rows;
        $u->close();
        return array('ok' => $n === 1, 'affected' => $n,
            'reason' => $n === 1 ? 'سجل القرار' : 'الصف ليس في صندوق الموتى أو لا تغيير');
    }

    /** F-16 نظيرُه للتسليم: تحريرُ ما عَلِق فوقَ مهلتِه (CK-12). */
    public function releaseStale($seconds = 3600)
    {
        $s = max(60, (int) $seconds);
        $this->conn->query(
            "UPDATE `ems_event_deliveries`
                SET `state`='failed', `attempt_no`=`attempt_no`+1,
                    `fail_code`='STALE_CLAIM',
                    `fail_text`=CONCAT('التقاطٌ عالقٌ فوقَ ', {$s}, ' ثانية — حُرّر آليًّا')
              WHERE `state` IN ('claimed','processing')
                AND `claimed_at` < NOW(3) - INTERVAL {$s} SECOND"
        );
        return $this->conn->affected_rows;
    }

    // ───────────────────────────── قراءاتٌ داخلية ─────────────────────────────

    private function loadDelivery($id)
    {
        $s = $this->conn->prepare(
            'SELECT `id`,`company_id`,`outbox_id`,`consumer_key`,`state`,`attempt_no`
               FROM `ems_event_deliveries` WHERE `id`=? LIMIT 1');
        $s->bind_param('i', $id); $s->execute();
        $r = $s->get_result()->fetch_assoc(); $s->close();
        return $r ?: null;
    }

    private function loadEvent($outboxId)
    {
        $s = $this->conn->prepare('SELECT * FROM `ems_business_events` WHERE `id`=? LIMIT 1');
        $s->bind_param('i', $outboxId); $s->execute();
        $r = $s->get_result()->fetch_assoc(); $s->close();
        return $r ?: null;
    }

    private function loadSubscription($eventCode, $consumerKey)
    {
        $s = $this->conn->prepare(
            'SELECT `consumer_class`,`consumer_method`,`max_attempts`,`timeout_seconds`
               FROM `event_consumers`
              WHERE `event_name`=? AND `consumer_key`=? AND `active`=1 LIMIT 1');
        $s->bind_param('ss', $eventCode, $consumerKey); $s->execute();
        $r = $s->get_result()->fetch_assoc(); $s->close();
        return $r ?: null;
    }

    private function maxAttemptsFor(array $d)
    {
        $s = $this->conn->prepare(
            'SELECT MIN(c.`max_attempts`)
               FROM `event_consumers` c
               JOIN `ems_business_events` e ON e.`event_key` = c.`event_name`
              WHERE e.`id`=? AND c.`consumer_key`=? AND c.`active`=1');
        $ob = (int) $d['outbox_id']; $ck = (string) $d['consumer_key'];
        $s->bind_param('is', $ob, $ck); $s->execute();
        $r = $s->get_result()->fetch_row(); $s->close();
        return ($r && $r[0] !== null) ? (int) $r[0] : 5;
    }
}
