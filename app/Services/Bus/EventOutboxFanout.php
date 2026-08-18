<?php
/**
 * EventOutboxFanout — مروحةُ الصادرِ لحظةَ النشر (ENG-01 · TSP-0202..0244)
 * ───────────────────────────────────────────────────────────────────────────
 * «◆ يُكتب داخلَ معاملةِ الواقعةِ نفسِها» — فهذه الخدمةُ تُنادى من داخلِ
 * EventPublisher::writeRoot على اتصالِ المستدعي، فتشارك معاملتَه: إن انهارت
 * الواقعةُ انهار معها صفُّ الصادرِ وصفوفُ التسليم، ولا يبقى حدثٌ لواقعةٍ لم تقع.
 *
 * وتفعل ثلاثةً لا رابعَ لها:
 *  ① تفحص المشتركينَ قبلَ الكتابة — «◆ ولا يُنشر حدثٌ لا اشتراكَ له».
 *    فالنشرُ بلا مستهلكٍ عملٌ ضائع، والقيدُ chk_consumers يرفضه في القاعدةِ
 *    أيضًا. والفحصُ هنا ليقولَ السببَ بالعربيةِ قبلَ أن يقولَه رقمُ خطإٍ.
 *  ② تُثبت consumers_declared على صفِّ الصادر — عددًا لا تخمينًا.
 *  ③ تفتح صفَّ تسليمٍ لكلِّ مشتركٍ بمفتاحِ عطالةٍ فريد (F-14):
 *    SHA2(CONCAT_WS('|', outbox_id, consumer_key), 256)
 *    فخمسُ إعاداتٍ لتسليمٍ واحدٍ تُنتج صفًّا واحدًا — يفرضه uq_idem لا الكود.
 *
 * ◆ فرقٌ عن الوثيقة: الصادرُ هنا ems_business_events (الجذرُ المحايد ADR-15)
 *   لا جدولٌ ثالثٌ باسم ems_event_outbox — والمنظرُ بذلك الاسمِ فوقَه.
 */

namespace App\Services\Bus;

class EventOutboxFanout
{
    /** رمزُ الرفضِ حين لا مشتركَ لنوعِ الحدث. */
    const ERR_NO_CONSUMER = 'BUS_NO_CONSUMER';

    /** @var bool مفتاحُ إطفاءٍ للاختبارِ السلبيِّ وحدَه — لا يُطفأ في التشغيل. */
    private static $enabled = true;

    public static function disableForTest() { self::$enabled = false; }
    public static function enable() { self::$enabled = true; }

    /**
     * المشتركونَ النشطونَ لنوعِ حدثٍ — من السجلِّ لا من قائمةٍ في الكود.
     *
     * @return array<int, array{consumer_key:string, handler_class:string, handler_method:?string, max_attempts:int, timeout_seconds:int}>
     */
    public static function subscribersFor(\mysqli $conn, $eventCode)
    {
        $out = array();
        $stmt = $conn->prepare(
            'SELECT `consumer_key`, `consumer_class`, `consumer_method`, `max_attempts`, `timeout_seconds`
               FROM `event_consumers`
              WHERE `event_name` = ? AND `active` = 1
              ORDER BY `consumer_key`'
        );
        if (!$stmt) { return $out; }
        $stmt->bind_param('s', $eventCode);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $out[] = array(
                'consumer_key'    => (string) $row['consumer_key'],
                'handler_class'   => (string) $row['consumer_class'],
                'handler_method'  => $row['consumer_method'] !== null ? (string) $row['consumer_method'] : 'handle',
                'max_attempts'    => (int) $row['max_attempts'],
                'timeout_seconds' => (int) $row['timeout_seconds'],
            );
        }
        $stmt->close();
        return $out;
    }

    /**
     * الحارسُ قبلَ الكتابة — يُرمى قبلَ أن يُكتب صفُّ الجذر، ويعيد العددَ المعلَن
     * ليدخل في جملةِ الإدراجِ نفسِها. فـchk_consumers يُفحص لحظةَ الإدراج،
     * وتحديثٌ لاحقٌ يأتي متأخرًا بعدَ أن يكون القيدُ قد رفض.
     *
     * @return int عددُ المشتركينَ النشطين (≥1)
     * @throws \RuntimeException حين لا مشتركَ نشطًا لنوعِ الحدث.
     */
    public static function assertHasConsumer(\mysqli $conn, $eventCode)
    {
        if (!self::$enabled) { return 1; }
        $subs = self::subscribersFor($conn, $eventCode);
        $n = count($subs);
        if ($n === 0) {
            throw new \RuntimeException(
                self::ERR_NO_CONSUMER . ': نوعُ الحدث «' . $eventCode . '» لا مشتركَ نشطًا له — '
                . 'سجّلْ مستهلكَه في event_consumers قبلَ نشرِه. فالنشرُ بلا مستهلكٍ عملٌ ضائع (CK-11).'
            );
        }
        return $n;
    }

    /**
     * بعدَ كتابةِ صفِّ الجذرِ مباشرةً وداخلَ المعاملةِ نفسِها:
     * إثباتُ عددِ المشتركينَ وفتحُ صفوفِ التسليم.
     *
     * @return int عددُ صفوفِ التسليمِ المفتوحة
     */
    public static function open(\mysqli $conn, $outboxId, $eventCode, $companyId)
    {
        if (!self::$enabled) { return 0; }
        $outboxId  = (int) $outboxId;
        $companyId = (int) $companyId;
        $subs = self::subscribersFor($conn, $eventCode);
        $n = count($subs);
        if ($n === 0) { return 0; }

        // ② العددُ المعلَن — يُثبت على صفِّ الصادرِ نفسِه (chk_consumers > 0)
        $up = $conn->prepare('UPDATE `ems_business_events` SET `consumers_declared` = ? WHERE `id` = ?');
        $up->bind_param('ii', $n, $outboxId);
        $up->execute();
        $up->close();

        // ③ صفُّ تسليمٍ لكلِّ مشترك — بمفتاحِ عطالةٍ من القاعدةِ لا من PHP
        //    (F-14 نصًّا: SHA2(CONCAT_WS('|', outbox_id, consumer_key), 256))
        //    وINSERT IGNORE مع uq_idem: إعادةُ النداءِ لا تُنتج صفًّا ثانيًا.
        $ins = $conn->prepare(
            "INSERT IGNORE INTO `ems_event_deliveries`
                (`company_id`, `outbox_id`, `consumer_key`, `consumer`, `event_id`,
                 `state`, `attempt_no`, `attempts`, `next_attempt_at`, `idempotency_key`)
             VALUES (?, ?, ?, ?, ?, 'published', 0, 0, NOW(3),
                     SHA2(CONCAT_WS('|', ?, ?), 256))"
        );
        $opened = 0;
        foreach ($subs as $s) {
            $ck = $s['consumer_key'];
            $ins->bind_param('iississ', $companyId, $outboxId, $ck, $ck, $outboxId, $outboxId, $ck);
            if ($ins->execute() && $conn->affected_rows > 0) { $opened++; }
        }
        $ins->close();
        return $opened;
    }
}
