<?php
/**
 * موزّع الأحداث المؤسسي — EventDispatcher (K4 · ADR-10 §8.1 قاعدتا 5 و7)
 * ───────────────────────────────────────────────────────────────────────────
 * Dispatcher داخلي فوق مخزن الأحداث (لا وسيط رسائل خارجيًا — قاعدة §8.1-7؛
 * العقد §9 فوق النقل، وترقية النقل لاحقًا لا تغيّر الناشرين/المستهلكين).
 *
 * دلالة التسليم — الصادقة معماريًا:
 *   التسليم at-least-once (انهيار العامل بين المعالجة وتحديث الـCursor = إعادة
 *   تسليمٍ عند الاستئناف)، والأثر exactly-once لأن كل مستهلكٍ ملزَمٌ بمعالجةٍ
 *   عاطلة الأثر (§8.1-5) — إعادة التسليم تصطدم بعطالة الأثر (مثال: النشر
 *   المشتق عبر EventPublisher بمفتاحٍ حتمي ⇒ duplicate=true بلا أثرٍ ثانٍ).
 *
 * الضمانات:
 *   • Cursor مستقل لكل مستهلك (ems_event_consumers) — تعثّر مستهلكٍ لا يمسّ غيره.
 *   • حدثٌ سام: retry حتى maxAttempts ثم عزلٌ في ems_event_dead_letter وتقدّمُ
 *     الـCursor خلفه — الطابور لا يتجمّد.
 *   • الترتيب داخل المستهلك: تصاعديًا بـ id (الترقيم الخادمي) لا ULID
 *     (مقايضة K8 الموثقة: داخل الملي-ثانية الترتيب عبر id).
 *   • التوقف الطويل = تراكمٌ آمن (append-only): الاستئناف يمضي من الـCursor
 *     دفعاتٍ محدودة (batch) حتى اللحاق — لا فقدان ولا معالجة مزدوجة الأثر.
 *   • المستهلك يستلم كامل صفّ الحدث ومعه correlation_id ليمرّره حرفيًا لأي
 *     حدثٍ مشتقٍّ ينشره (وراثة السلسلة — عقد K3).
 *
 * حقن الانهيار (اختباري حصرًا): $opts['crash_after_event'] يُنهي العملية بعد
 * نجاح المعالجة وقبل تحديث الـCursor — لمحاكاة الانقطاع في أسوأ نقطة.
 */

namespace App\Core;

class EventDispatcher
{
    /** @var \mysqli */
    private $conn;
    /** @var array<string, callable> */
    private $handlers = array();
    /** @var int */
    private $maxAttempts;
    /** @var int */
    private $batch;
    /** @var int|null معرّف حدثٍ يُحقن الانهيار بعده (اختباري حصرًا) */
    private $crashAfterEvent;

    public function __construct(\mysqli $conn, array $opts = array())
    {
        $this->conn = $conn;
        $this->maxAttempts = isset($opts['max_attempts']) ? max(1, intval($opts['max_attempts'])) : 3;
        $this->batch = isset($opts['batch']) ? max(1, intval($opts['batch'])) : 100;
        $this->crashAfterEvent = isset($opts['crash_after_event']) ? intval($opts['crash_after_event']) : null;
    }

    /**
     * تسجيل مستهلكٍ ومعالجه. $startAfterId: بدء الاستهلاك بعد هذا المعرّف
     * (افتراضيًا 0 = من أول السجل؛ المستهلك الجديد عادةً يبدأ من MAX(id) الحالي
     * كي لا يعالج الماضي رجعيًا). التسجيل idempotent — الصف القائم لا يُمسّ.
     */
    public function register($name, callable $handler, $startAfterId = 0)
    {
        $this->handlers[$name] = $handler;
        $stmt = $this->conn->prepare(
            'INSERT IGNORE INTO `ems_event_consumers` (`consumer`, `cursor_event_id`) VALUES (?, ?)'
        );
        $stmt->bind_param('si', $name, $startAfterId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * دورة توزيعٍ واحدة على كل المستهلكين المسجَّلين المفعَّلين.
     * @return array إحصاءات لكل مستهلك: processed/failed/dead_lettered/cursor
     */
    public function runOnce()
    {
        $stats = array();
        foreach ($this->handlers as $consumer => $handler) {
            $stats[$consumer] = $this->runConsumer($consumer, $handler);
        }
        return $stats;
    }

    private function runConsumer($consumer, callable $handler)
    {
        $st = array('processed' => 0, 'failed' => 0, 'dead_lettered' => 0, 'cursor' => 0);

        $row = $this->q1('SELECT enabled, cursor_event_id FROM `ems_event_consumers` WHERE consumer = ?', 's', array($consumer));
        if (!$row || intval($row['enabled']) !== 1) {
            return $st;
        }
        $cursor = intval($row['cursor_event_id']);

        // أحداث العقد حصرًا (event_key موجود) — الصفوف القديمة قبل العقد خارج الناقل.
        $stmt = $this->conn->prepare(
            'SELECT * FROM `fin_financial_events`
             WHERE `id` > ? AND `event_key` IS NOT NULL AND COALESCE(`is_deleted`, 0) = 0
             ORDER BY `id` ASC LIMIT ' . intval($this->batch)
        );
        $stmt->bind_param('i', $cursor);
        $stmt->execute();
        $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($events as $event) {
            $eventId = intval($event['id']);

            // عدّاد المحاولات (قبل المعالجة): يتقدّم ذرّيًا مع كل التقاط.
            $this->exec(
                'INSERT INTO `ems_event_deliveries` (`consumer`, `event_id`, `attempts`) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE `attempts` = `attempts` + 1',
                'si', array($consumer, $eventId)
            );
            $d = $this->q1('SELECT attempts, last_error FROM `ems_event_deliveries` WHERE consumer = ? AND event_id = ?', 'si', array($consumer, $eventId));
            $attempts = intval($d['attempts']);

            if ($attempts > $this->maxAttempts) {
                // حدثٌ سام: عزلٌ في الرسائل الميتة + تقدّم الـCursor خلفه — لا تجميد.
                $this->exec(
                    'INSERT IGNORE INTO `ems_event_dead_letter` (`consumer`, `event_id`, `attempts`, `last_error`, `failed_at`) VALUES (?, ?, ?, ?, ?)',
                    'siiss', array($consumer, $eventId, $attempts - 1, (string) $d['last_error'], date('Y-m-d H:i:s'))
                );
                $this->exec('DELETE FROM `ems_event_deliveries` WHERE consumer = ? AND event_id = ?', 'si', array($consumer, $eventId));
                $this->advanceCursor($consumer, $eventId);
                $st['dead_lettered']++;
                $st['cursor'] = $eventId;
                continue;
            }

            try {
                $handler($event, $this->conn); // المعالج يستلم الصفّ كاملًا (ومعه correlation_id للوراثة)
            } catch (\Throwable $t) {
                // فشل: يبقى الـCursor مكانه — إعادة المحاولة في دورةٍ قادمة (حتى الاستنفاد).
                $this->exec(
                    'UPDATE `ems_event_deliveries` SET `last_error` = ? WHERE consumer = ? AND event_id = ?',
                    'ssi', array(substr($t->getMessage(), 0, 500), $consumer, $eventId)
                );
                $st['failed']++;
                break; // ترتيب المستهلك محفوظ: لا نقفز فوق حدثٍ فاشلٍ قبل استنفاده
            }

            // نجاح: إغلاق المحاولة ثم [نقطة حقن الانهيار] ثم تقدّم الـCursor.
            $this->exec('DELETE FROM `ems_event_deliveries` WHERE consumer = ? AND event_id = ?', 'si', array($consumer, $eventId));

            if ($this->crashAfterEvent !== null && $eventId === $this->crashAfterEvent) {
                // اختباري حصرًا: انهيارٌ في أسوأ نقطة — بعد الأثر وقبل الـCursor.
                fwrite(STDERR, "[dispatcher] CRASH INJECTED after event {$eventId} (before cursor update)\n");
                exit(97);
            }

            $this->advanceCursor($consumer, $eventId);
            $st['processed']++;
            $st['cursor'] = $eventId;
        }

        return $st;
    }

    /** تقدّم رتيب (monotonic): لا يعود الـCursor للخلف أبدًا. */
    private function advanceCursor($consumer, $eventId)
    {
        $this->exec(
            'UPDATE `ems_event_consumers` SET `cursor_event_id` = ? WHERE `consumer` = ? AND `cursor_event_id` < ?',
            'isi', array($eventId, $consumer, $eventId)
        );
    }

    private function q1($sql, $types, array $params)
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res;
    }

    private function exec($sql, $types, array $params)
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
