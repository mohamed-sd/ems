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

require_once __DIR__ . '/../../includes/catch_log.php';

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
    /** @var bool N-06: تفعيل التصاعد الزمني بين المحاولات (يُطفأ اختباريًا) */
    private $backoff = true;

    public function __construct(\mysqli $conn, array $opts = array())
    {
        // N-06 ركن ②: الحد المعياري خمس محاولات (PLAN-05 §3-①) — قابل للتضييق اختباريًا.
        $this->conn = $conn;
        $this->maxAttempts = isset($opts['max_attempts']) ? max(1, intval($opts['max_attempts'])) : 5;
        $this->batch = isset($opts['batch']) ? max(1, intval($opts['batch'])) : 100;
        $this->crashAfterEvent = isset($opts['crash_after_event']) ? intval($opts['crash_after_event']) : null;
        $this->backoff = !isset($opts['backoff']) || (bool) $opts['backoff'];
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

            // N-06 ركن ②: التصاعد الزمني — محاولةٌ غير مستحقةٍ بعدُ توقف دورة
            // هذا المستهلك (لا قفز فوقها حفاظًا على الترتيب) وتُستأنف في موعدها.
            if ($this->backoff) {
                $due = $this->q1(
                    'SELECT 1 AS blocked FROM `ems_event_deliveries`
                      WHERE consumer = ? AND event_id = ? AND next_retry_at IS NOT NULL AND next_retry_at > NOW()',
                    'si', array($consumer, $eventId)
                );
                if ($due) {
                    break;
                }
            }

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
                // N-06 ركن ②: العزل بإنذارٍ لا بصمت — إشعارٌ لمدير المالية برابط شاشة المراقبة.
                $this->alertDeadLetter($consumer, $eventId, intval($event['company_id']), (string) $d['last_error']);
                $this->advanceCursor($consumer, $eventId);
                $st['dead_lettered']++;
                $st['cursor'] = $eventId;
                continue;
            }

            try {
                $handler($event, $this->conn); // المعالج يستلم الصفّ كاملًا (ومعه correlation_id للوراثة)
            } catch (\Throwable $t) {
                // فشل: يبقى الـCursor مكانه — والموعد التالي تصاعديٌّ 2^attempts دقيقة
                // (سقفه 64) — إعادة المحاولة عند استحقاقه حتى الاستنفاد.
                $delayMin = min(64, pow(2, min(6, $attempts)));
                $this->exec(
                    'UPDATE `ems_event_deliveries`
                        SET `last_error` = ?, `next_retry_at` = ' . ($this->backoff ? 'DATE_ADD(NOW(), INTERVAL ' . intval($delayMin) . ' MINUTE)' : 'NULL') . '
                      WHERE consumer = ? AND event_id = ?',
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

    /**
     * N-06 ركن ②: إنذار العزل — إشعارٌ لمدير المالية عند انتقال حدثٍ للرسائل
     * الميتة. فشل الإشعار لا يوقف العزل (الإنذار غلافٌ والعزل نواة).
     */
    private function alertDeadLetter($consumer, $eventId, $companyId, $lastError)
    {
        try {
            $title = mb_substr('إنذار الناقل: حدث #' . $eventId . ' عزل في الرسائل الميتة (المستهلك ' . $consumer . ') — ' . $lastError, 0, 200);
            $link = 'admin/bus_monitor.php';
            $this->exec(
                "INSERT INTO `fin_notifications` (`company_id`, `target_level`, `title`, `link`) VALUES (?, 'finance_manager', ?, ?)",
                'iss', array($companyId > 0 ? $companyId : 1, $title, $link)
            );
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, '[dispatcher] dead-letter alert failed');
            error_log('[dispatcher] dead-letter alert failed: ' . $t->getMessage());
        }
    }

    /* ══ INJ-FIX-01 · الموجة أ ① — إنذارُ تعثُّرِ المستهلك (GAP-07) ═════════════
       ◆ **الثغرةُ التي يسدُّها — وقعت فعلًا**: `alertDeadLetter()` لا يُطلق إلا عند
         العزلِ في `ems_event_dead_letter`، والجدولُ فارغٌ أبدًا. فمرَّ صفّان في
         `ems_event_consumers` **بلا معالجٍ مسجَّلٍ في الشيفرة كلِّها** (`fx` منذ
         2026-08-12 بتأخُّرِ 5054 واقعةً · و`finance_routing_replay`) ولم ينتبه أحد:
         `runOnce()` يدور على `$this->handlers` وحدَها، فالصفُّ اليتيمُ لا يُقرأ
         ولا يُنذَر عنه — **ولا يظهر عطبُه إلا في مؤشرٍ يقرؤه إنسان**.
       ◆ **وحالتان لا حالةٌ واحدة** — والخلطُ بينهما يُخفي الأخطر:
         ① **يتيم**: صفٌّ مُفعَّلٌ بلا معالجٍ مسجَّل. لا يُقاس بالزمنِ أصلًا —
            فمهما مضى لن يتحرّك، وهو عطبُ تعريفٍ لا تعثُّرَ تشغيل.
         ② **متعثِّر**: له معالجٌ ومؤشرُه متأخرٌ ولم يتقدّم منذ مهلةِ الإنذار.
       ◆ **والمهلةُ تُقاس على `updated_at` لا على عمرِ الواقعة**: المستهلكُ الذي
         لا وقائعَ له ليس متعثرًا، والمتأخرُ الذي يتقدّم كلَّ دورةٍ ليس متعثرًا.
       ◆ **ودَورةُ إنذارٍ واحدةٍ في الساعةِ لكلِّ مستهلك** — بالنمطِ نفسِه الذي
         تعتمده `JobScheduleService::alertStalled()`: وسمٌ في العنوانِ يُبحث عنه.
         فلا يُخترع سجلٌّ ثانٍ للإنذارات ولا قناةٌ ثانيةٌ لقارئها. */

    /** مهلةُ اعتبارِ المستهلكِ متعثرًا — ساعةٌ، بمحاذاةِ `alert_after_seconds`
     *  للمهامِّ التي تعمل كلَّ خمسِ دقائق في `ems_job_schedule`. */
    const CONSUMER_STALL_SECONDS = 3600;

    /**
     * المستهلكون المتعثرون أو اليتامى — قراءةٌ محضةٌ لا تكتب شيئًا.
     * @return array<int, array{consumer:string, kind:string, lag:int, idle_seconds:?int, cursor:int}>
     */
    public function stalledConsumers()
    {
        $out = array();
        $res = $this->conn->query(
            "SELECT `consumer`, `cursor_event_id`,
                    TIMESTAMPDIFF(SECOND, `updated_at`, NOW()) AS idle
               FROM `ems_event_consumers`
              WHERE `enabled` = 1
              ORDER BY `consumer`"
        );
        if (!$res) { return $out; }

        while ($row = $res->fetch_assoc()) {
            $consumer = (string) $row['consumer'];
            $cursor   = (int) $row['cursor_event_id'];
            $idle     = $row['idle'] !== null ? (int) $row['idle'] : null;

            /* المقامُ نفسُه الذي يقرأ منه `runConsumer` — وإلا قِيس تأخُّرٌ وهميّ:
               فرقُ المعرِّفات ليس عددَ الوقائع (المعرِّفاتُ متفرقة). */
            $st = $this->conn->prepare(
                'SELECT COUNT(*) FROM `fin_financial_events`
                  WHERE `id` > ? AND `event_key` IS NOT NULL AND COALESCE(`is_deleted`, 0) = 0'
            );
            $st->bind_param('i', $cursor);
            $st->execute();
            $st->bind_result($lag);
            $st->fetch();
            $st->close();
            $lag = (int) $lag;

            if (!isset($this->handlers[$consumer])) {
                $out[] = array('consumer' => $consumer, 'kind' => 'orphan',
                               'lag' => $lag, 'idle_seconds' => $idle, 'cursor' => $cursor);
                continue;
            }
            if ($lag > 0 && $idle !== null && $idle >= self::CONSUMER_STALL_SECONDS) {
                $out[] = array('consumer' => $consumer, 'kind' => 'stalled',
                               'lag' => $lag, 'idle_seconds' => $idle, 'cursor' => $cursor);
            }
        }
        return $out;
    }

    /**
     * أَلِهذا اليتيمِ حكمٌ مقيَّد؟ — FR-EVT-003.
     * ◆ **والغيابُ ليس سكوتًا**: جدولٌ غيرُ موجودٍ أو صفٌّ غيرُ مقيَّدٍ يعني
     *   `false` — فيُنذَر عنه. فالإعفاءُ **بقيدٍ مكتوبٍ لا بغيابِ سجل**.
     */
    private function orphanIsRuled($consumer)
    {
        $st = @$this->conn->prepare(
            'SELECT COUNT(*) FROM `gov_orphan_consumer_rulings` WHERE `consumer_key` = ?');
        if (!$st) { return false; }
        $st->bind_param('s', $consumer);
        if (!$st->execute()) { $st->close(); return false; }
        $st->bind_result($n);
        $st->fetch();
        $st->close();
        return ((int) $n) > 0;
    }

    /**
     * يرفع إنذارًا لكلِّ متعثرٍ أو يتيم — إنذارٌ واحدٌ في الساعةِ لكلِّ مستهلك.
     * @return int عددُ الإنذاراتِ المرفوعة
     */
    public function alertStalledConsumers()
    {
        $n = 0;
        foreach ($this->stalledConsumers() as $s) {
            /* ══ FR-EVT-003 — **ولا يُنذَر لمستهلكٍ بلا معالج** ═══════════════
               ◆ اليتيمُ **لن يتقدّم مهما مضى الزمن**، فإنذارٌ دوريٌّ عنه ليس
                 خبرًا بل ضجيجٌ يُعوِّد القارئَ على تجاهلِ القناة. وقيس: 42 من
                 252 إنذارَ توقفٍ (16.7٪) عن يتيمَين اثنَين لا غير.
               ◆ **ولا يختفي بذلك**: يُقيَّد حكمُه مرّةً في
                 `gov_orphan_consumer_rulings` بمالكِه وسببِه — فيصير مرئيًّا
                 مرّةً بدل أن يكون مسموعًا كلَّ ساعة.
               ◆ **واليتيمُ بلا حكمٍ ما يزال يُنذَر عنه** — فالسكوتُ عنه بلا
                 قيدٍ هو العطبُ نفسُه بوجهٍ آخر. */
            if ($s['kind'] === 'orphan' && $this->orphanIsRuled($s['consumer'])) { continue; }
            $tag = '[BUS-STALL:' . $s['consumer'] . ']';
            $dupe = $this->conn->query(
                "SELECT COUNT(*) FROM `fin_notifications`
                  WHERE `title` LIKE '" . $this->conn->real_escape_string($tag) . "%'
                    AND `created_at` > NOW() - INTERVAL 1 HOUR"
            );
            if ($dupe && (int) $dupe->fetch_row()[0] > 0) { continue; }

            $why = $s['kind'] === 'orphan'
                ? 'صف مفعل بلا معالج مسجل — لن يتقدم مهما مضى الزمن'
                : 'لم يتقدم منذ ' . $this->humanSeconds((int) $s['idle_seconds']);
            $title = mb_substr(
                $tag . ' المستهلك «' . $s['consumer'] . '» — ' . $why
                . ' · متأخر ' . $s['lag'] . ' واقعة عند المؤشر ' . $s['cursor']
                . '. وصمت المستهلك أخطر من فشله.', 0, 195);

            $st = $this->conn->prepare(
                "INSERT INTO `fin_notifications` (`company_id`,`target_level`,`title`,`link`)
                 VALUES (1, 'finance_manager', ?, 'admin/bus_monitor.php')"
            );
            $st->bind_param('s', $title);
            if ($st->execute()) { $n++; }
            $st->close();
        }
        return $n;
    }

    /* ══ INJ-FIX-01 · GAP-05 — سجلُّ الاشتراكاتِ يصير مقروءًا في الإنتاج ═══════
       ◆ **العطبُ المقيس**: `ems_event_subscriptions` يحمل **91 اشتراكًا نشطًا**
         بمعالجاتٍ موجودةٍ على القرص — **ولا يقرؤه سطرُ إنتاجٍ واحد**. تقرؤه
         هجرتان وثلاثُ أدواتِ قياس، و`runOnce()` يدور على `$this->handlers`
         وحدَها (ما سُجِّل بـ`register()` في `cron_events.php`: مستهلكان).
         ⇐ فالسجلُّ **إعلانُ نيّةٍ لا عقدُ تنفيذ**، والوثيقةُ تصف ما لا يجري.
       ◆ **ولا تُفعَّل الواحدُ والتسعون تلقائيًّا**: تشغيلُ معالجاتٍ لم تعمل قطُّ
         على نظامٍ ماليٍّ حيٍّ **دفعةً واحدةً** هو عينُ ما يمنعه بروتوكولُ القلبِ
         السباعيّ — تفعيلٌ بلا ظلٍّ ولا مقارنٍ ولا معيارِ رجوع.
       ◆ **فالقراءةُ حوكمةٌ لا تنفيذ**: يُقارَن المُعلَنُ بالمُنفَّذ، وكلُّ اشتراكٍ
         نشطٍ بلا معالجٍ مسجَّلٍ **يُنذَر عنه بالاسم**. فيصير الفارقُ **مقيسًا
         ومرئيًّا** بدل أن يكون صمتًا، وتفعيلُه يبقى قلبًا مُدارًا في الموجة ج. */

    /**
     * اشتراكاتٌ نشطةٌ في السجلِّ بلا معالجٍ مسجَّلٍ في الشيفرة — قراءةٌ محضة.
     * @return array<int, array{event_code:string, consumer_key:string, handler:string}>
     */
    public function unwiredSubscriptions()
    {
        $out = array();
        $res = $this->conn->query(
            "SELECT `event_code`, `consumer_key`, `handler_class`, `handler_method`
               FROM `ems_event_subscriptions`
              WHERE `is_active` = 1
              ORDER BY `consumer_key`, `event_code`");
        if (!$res) { return $out; }
        while ($row = $res->fetch_assoc()) {
            $key = (string) $row['consumer_key'];
            if (isset($this->handlers[$key])) { continue; }
            $out[] = array(
                'event_code'   => (string) $row['event_code'],
                'consumer_key' => $key,
                'handler'      => (string) $row['handler_class'] . '::' . (string) $row['handler_method'],
            );
        }
        return $out;
    }

    /**
     * إنذارٌ واحدٌ في الساعةِ لكلِّ مستهلكٍ مُعلَنٍ غيرِ موصول.
     * ◆ ويُجمَع بالمستهلكِ لا بالحدث: مستهلكٌ واحدٌ لعشرةِ أحداثٍ عطبٌ واحدٌ
     *   لا عشرة — وإنذارٌ لكلِّ حدثٍ يُغرق القارئَ فيُهمل الجميع.
     * @return int عددُ الإنذاراتِ المرفوعة
     */
    public function alertUnwiredSubscriptions()
    {
        $byConsumer = array();
        foreach ($this->unwiredSubscriptions() as $s) {
            $byConsumer[$s['consumer_key']]['n'] = isset($byConsumer[$s['consumer_key']]['n'])
                ? $byConsumer[$s['consumer_key']]['n'] + 1 : 1;
            $byConsumer[$s['consumer_key']]['handler'] = $s['handler'];
        }
        $n = 0;
        foreach ($byConsumer as $key => $info) {
            $tag = '[BUS-UNWIRED:' . $key . ']';
            $dupe = $this->conn->query(
                "SELECT COUNT(*) FROM `fin_notifications`
                  WHERE `title` LIKE '" . $this->conn->real_escape_string($tag) . "%'
                    AND `created_at` > NOW() - INTERVAL 1 HOUR");
            if ($dupe && (int) $dupe->fetch_row()[0] > 0) { continue; }

            $title = mb_substr(
                $tag . ' اشتراك نشط بلا معالج مسجل — ' . $info['n'] . ' نوع حدث '
                . 'يقود إلى «' . $info['handler'] . '» ولا ينفذ. '
                . 'المعلن ليس المنفذ.', 0, 195);

            $st = $this->conn->prepare(
                "INSERT INTO `fin_notifications` (`company_id`,`target_level`,`title`,`link`)
                 VALUES (1, 'finance_manager', ?, 'admin/bus_monitor.php')");
            $st->bind_param('s', $title);
            if ($st->execute()) { $n++; }
            $st->close();
        }
        return $n;
    }

    /** صياغةٌ بشريةٌ للمدة — نسخةُ `JobScheduleService::humanSeconds` نفسِها. */
    private function humanSeconds($sec)
    {
        if ($sec < 60)    { return $sec . ' ثانية'; }
        if ($sec < 3600)  { return intval($sec / 60) . ' دقيقة'; }
        if ($sec < 86400) { return intval($sec / 3600) . ' ساعة'; }
        return intval($sec / 86400) . ' يوما';
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
