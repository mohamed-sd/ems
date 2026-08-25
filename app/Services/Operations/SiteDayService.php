<?php
/**
 * app/Services/Operations/SiteDayService.php — اليومُ الميدانيُّ وواقعةُ التوقّف (RPR-W04)
 * ═══════════════════════════════════════════════════════════════════════════
 * **دورةُ اليوم**: فتحُ يومِ موقعٍ ← وردية ← حضورٌ فعليّ ← قيدٌ يوميّ ←
 * تنفيذُ وحدة ← اعتمادٌ ميدانيّ ← إقفالُ اليوم.
 *
 * القواعدُ الحاكمة:
 *  · **يومٌ واحدٌ لكلِّ (كيان × موقع × تاريخ)** — تضمنه `uq_site_day`، والخدمةُ
 *    تعيد القائمَ ولا تُنشئ ثانيًا (عطالةٌ بمفتاحٍ لا بفحصِ رصيد).
 *  · **لا وردية بلا يومٍ مفتوح** — وردية على يومٍ مُقفَلٍ تُردَّ وتُقيَّد.
 *  · **لا قيدَ بعدَ الإقفال** — والمحاولةُ **تُرفَض وتُقيَّد** في
 *    `site_day_attempt`؛ رفضٌ بلا قيدٍ دعوى لا تُقاس.
 *  · **لا تُقفل ورديةٌ بلا محضرِ تسليم** — قيدُ `chk_sds_handover` يمنعه في
 *    القاعدة، والخدمةُ تمنعه قبلَ أن يصل. طبقتانِ لا واحدة.
 *  · **التوقّفُ يُسجَّل مرّةً واحدة** — `occurrence_key` فريدٌ يدّعيه أوّلُ سجلٍّ،
 *    والثاني يُسجَّل **مرآةً** بفارقِه في `ops_stop_source` ولا يُنشئ صفًّا ثانيًا.
 *
 * ◆ **وكلُّ وصولٍ إلى القاعدةِ عبرَ بوابةِ المستأجِر** (`TenantDb`) — لا استعلامَ
 *   خامٍّ على جدولِ مستأجِر (‏FR-SEC-006 · GAP-29). فالعزلُ يُحقن بنيويًّا،
 *   والتدقيقُ يُكتب في البوابةِ لا في الخدمة، و`company_id` لا يُمرَّر من
 *   المُستدعي بل يُشتقُّ من سياقِ البوابةِ نفسِها.
 *
 * ⚠ **حدُّ الإنفاذِ مُعلَنٌ لا مُدَّعًى**: منعُ القيدِ بعدَ الإقفالِ **ليس قيدًا في
 *   القاعدة** — قيدُ `CHECK` لا يقرأ جدولًا آخر، والقادحُ يحتاج `SUPER` لا
 *   يملكه مستخدمُ الهجرات، و`FOREIGN KEY` مركَّبٌ على الحالةِ كان سيمنع
 *   **الإقفالَ نفسَه** لا القيدَ بعده. فالإنفاذُ هنا في الخدمةِ ويُثبَت
 *   **وظيفيًّا** في البوّابة: تُستدعى الخدمةُ فعلًا ويُقاس الرفضُ والقيد.
 *   (‏W04 · الاستثناء W4-X-01)
 */

namespace App\Services\Operations;

use App\Core\TenantDb;

class SiteDayService
{
    /** مفرداتُ الوردية المعياريّة */
    const SHIFTS = array('day', 'night');

    /**
     * اتصالُ نشرِ الحقائقِ المحايدة — يُحقن صراحةً من المُستدعي.
     * ◆ **ولمَ منفصلٌ عن البوابة**: `EventPublisher::publishFact` يأخذ `mysqli`
     *   بحكمِ عقدِه (ADR-15)، ودفترُ الأحداثِ الجذرُ ليس جدولَ مستأجِرٍ يُكتب
     *   عبرَ بوابةِ العزل — بل حقيقةٌ محايدةٌ لها حارسُها وحصانتُها. وغيابُ
     *   الاتصالِ **لا يمنع الحقيقةَ الميدانيّةَ من الوقوع**: الحالةُ في
     *   `site_day` هي الحقيقةُ والحدثُ إشعارُها.
     * @var \mysqli|null
     */
    private static $eventConn = null;

    public static function setEventConnection(\mysqli $conn)
    {
        self::$eventConn = $conn;
    }

    /** فتحُ يومِ موقعٍ — عطالةٌ بالمفتاحِ الفريد: الموجودُ يُعاد ولا يُنشأ ثانيًا */
    public static function openDay(TenantDb $gate, $siteId, $projectId, $date, $actorId, $sourceRef = '')
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'day_id' => 0, 'state' => '', 'created' => false);
        $siteId = (int) $siteId; $actorId = (int) $actorId;
        if ($siteId <= 0)    { $out['code'] = 422; $out['reason'] = 'لا يومَ بلا موقع'; return $out; }
        if (!self::isDate($date)) { $out['code'] = 422; $out['reason'] = 'تاريخُ اليومِ إلزاميٌّ بصيغةِ YYYY-MM-DD'; return $out; }
        if ($actorId <= 0)   { $out['code'] = 422; $out['reason'] = 'لا فتحَ بلا فاعلٍ معروف'; return $out; }

        $existing = self::findDay($gate, $siteId, $date);
        if ($existing) {
            $out['ok'] = true; $out['code'] = 200; $out['day_id'] = (int) $existing['id'];
            $out['state'] = $existing['state']; $out['reason'] = 'اليومُ مفتوحٌ سلفًا — عطالةٌ بالمفتاح';
            return $out;
        }
        /* ⚠ `company_id` **لا يُمرَّر**: البوابةُ تحقنه من سياقِها، وتمريرُ غيرِه
             محاولةُ تزويرِ هوية تُردّ (`identity forgery attempt`). */
        $row = array(
            'site_id'    => $siteId,
            'project_id' => ((int) $projectId > 0) ? (int) $projectId : null,
            'day_date'   => (string) $date,
            'state'      => 'open',
            'opened_by'  => $actorId,
            'opened_at'  => self::now($gate),
            'source_ref' => (string) $sourceRef,
        );
        $newId = 0;
        try { $newId = (int) $gate->insert('site_day', $row); }
        catch (\Throwable $t) { $newId = 0; }
        if ($newId <= 0) {
            /* سباقٌ على المفتاحِ الفريد ⇒ الآخرُ سبق: تُعاد قراءتُه لا يُعاد الإدراج */
            $again = self::findDay($gate, $siteId, $date);
            if ($again) { $out['ok'] = true; $out['code'] = 200; $out['day_id'] = (int) $again['id']; $out['state'] = $again['state']; $out['reason'] = 'عطالةٌ بالمفتاح'; return $out; }
            $out['code'] = 500; $out['reason'] = 'تعذّر فتحُ اليوم'; return $out;
        }
        $out['ok'] = true; $out['code'] = 201; $out['day_id'] = $newId;
        $out['state'] = 'open'; $out['created'] = true;
        $day = self::dayById($gate, $newId);
        self::emit($gate, $day, 'operations.site_day.opened', 'site_day', $newId,
                   array('site_id' => $siteId, 'day_date' => (string) $date), 'site_day:' . $newId);
        return $out;
    }

    /** فتحُ ورديةٍ داخلَ يومٍ مفتوح — والوردية على يومٍ مُقفَلٍ تُردُّ وتُقيَّد */
    public static function openShift(TenantDb $gate, $dayId, $shift, $supervisorId, $actorId)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'shift_id' => 0, 'created' => false);
        $dayId = (int) $dayId;
        if (!\in_array($shift, self::SHIFTS, true)) { $out['code'] = 422; $out['reason'] = 'مفردةُ وردية خارجَ المعياريّ'; return $out; }
        $day = self::dayById($gate, $dayId);
        if (!$day) { $out['code'] = 404; $out['reason'] = 'اليومُ غيرُ موجود'; return $out; }
        if ($day['state'] === 'closed') {
            self::logAttempt($gate, $day, 'shift_open', $shift, $actorId, 'DAY_CLOSED', 'وردية على يومٍ مُقفَل');
            $out['code'] = 409; $out['reason'] = 'اليومُ مُقفَلٌ — لا تُفتح فيه وردية'; return $out;
        }
        $ex = $gate->selectOne('site_day_shift', array(
            'columns' => array('id'), 'where' => array('day_id' => $dayId, 'shift' => $shift)));
        if ($ex) { $out['ok'] = true; $out['code'] = 200; $out['shift_id'] = (int) $ex['id']; $out['reason'] = 'الورديةُ مفتوحةٌ سلفًا — عطالةٌ بالمفتاح'; return $out; }

        $newId = 0;
        try {
            $newId = (int) $gate->insert('site_day_shift', array(
                'day_id'        => $dayId,
                'shift'         => (string) $shift,
                'state'         => 'open',
                'supervisor_id' => ((int) $supervisorId > 0) ? (int) $supervisorId : null,
                'opened_at'     => self::now($gate),
            ));
        } catch (\Throwable $t) { $newId = 0; }
        if ($newId <= 0) { $out['code'] = 500; $out['reason'] = 'تعذّر فتحُ الوردية'; return $out; }
        $out['ok'] = true; $out['code'] = 201; $out['shift_id'] = $newId; $out['created'] = true;
        return $out;
    }

    /**
     * الحارسُ: أيُقبل قيدٌ يوميٌّ في هذا (الموقع × اليوم × الوردية)؟
     * **والرفضُ يُقيَّد دائمًا** — والقبولُ يُقيَّد أيضًا كي يكون المقامُ كاملًا.
     */
    public static function assertOpenForEntry(TenantDb $gate, $siteId, $date, $shift, $actorId, $payloadRef = '')
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'reason_code' => '', 'day_id' => 0, 'attempt_id' => 0);
        $day = self::findDay($gate, (int) $siteId, $date);
        if (!$day) {
            $out['reason_code'] = 'NO_DAY'; $out['code'] = 409; $out['reason'] = 'لا يومَ ميدانيًّا مفتوحًا لهذا الموقعِ والتاريخ';
            $out['attempt_id'] = self::logAttempt($gate, array('id' => null, 'site_id' => (int) $siteId, 'day_date' => (string) $date),
                                                  'unit_entry', $shift, $actorId, 'NO_DAY', $out['reason'], $payloadRef);
            return $out;
        }
        $out['day_id'] = (int) $day['id'];
        if ($day['state'] === 'closed') {
            $out['reason_code'] = 'DAY_CLOSED'; $out['code'] = 409; $out['reason'] = 'يومُ الموقعِ مُقفَلٌ — القيدُ بعدَ الإقفالِ مرفوض';
            $out['attempt_id'] = self::logAttempt($gate, $day, 'unit_entry', $shift, $actorId, 'DAY_CLOSED', $out['reason'], $payloadRef);
            return $out;
        }
        $sh = $gate->selectOne('site_day_shift', array(
            'columns' => array('id', 'state'), 'where' => array('day_id' => (int) $day['id'], 'shift' => (string) $shift)));
        if (!$sh) {
            $out['reason_code'] = 'NO_SHIFT'; $out['code'] = 409; $out['reason'] = 'لا وردية مفتوحةً بهذا الاسمِ في يومِ الموقع';
            $out['attempt_id'] = self::logAttempt($gate, $day, 'unit_entry', $shift, $actorId, 'NO_SHIFT', $out['reason'], $payloadRef);
            return $out;
        }
        if ($sh['state'] === 'closed') {
            $out['reason_code'] = 'SHIFT_CLOSED'; $out['code'] = 409; $out['reason'] = 'الورديةُ مُقفَلة';
            $out['attempt_id'] = self::logAttempt($gate, $day, 'unit_entry', $shift, $actorId, 'SHIFT_CLOSED', $out['reason'], $payloadRef);
            return $out;
        }
        $out['ok'] = true; $out['code'] = 200; $out['reason_code'] = 'OPEN'; $out['reason'] = 'اليومُ والورديةُ مفتوحان';
        $out['attempt_id'] = self::logAttempt($gate, $day, 'unit_entry', $shift, $actorId, 'OPEN', $out['reason'], $payloadRef, 'allowed');
        return $out;
    }

    /** تسليمُ الورديةِ بمحضرٍ — ولا إقفالَ بلا مُستلِم */
    public static function handOverShift(TenantDb $gate, $shiftId, $handoverTo, $note)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $shiftId = (int) $shiftId; $handoverTo = (int) $handoverTo;
        if ($handoverTo <= 0) { $out['code'] = 422; $out['reason'] = 'لا تُقفل ورديةٌ بلا محضرٍ بين المشرفَين'; return $out; }
        $cur = $gate->selectOne('site_day_shift', array('columns' => array('id', 'state'), 'where' => array('id' => $shiftId)));
        if (!$cur || $cur['state'] !== 'open') { $out['code'] = 409; $out['reason'] = 'الورديةُ ليست مفتوحةً أو تعذّر التسليم'; return $out; }
        try {
            $gate->update('site_day_shift', array(
                'state' => 'handed_over', 'handover_to' => $handoverTo,
                'handover_at' => self::now($gate), 'handover_note' => (string) $note,
            ), array('id' => $shiftId));
        } catch (\Throwable $t) { $out['code'] = 500; $out['reason'] = 'تعذّر التسليم'; return $out; }
        $out['ok'] = true; $out['code'] = 200; return $out;
    }

    /** إقفالُ يومِ الموقعِ — ولا إقفالَ ووردياتُه مفتوحةٌ بلا محضر */
    public static function closeDay(TenantDb $gate, $dayId, $actorId, $note = '')
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'open_shifts' => 0);
        $dayId = (int) $dayId; $actorId = (int) $actorId;
        $day = self::dayById($gate, $dayId);
        if (!$day) { $out['code'] = 404; $out['reason'] = 'اليومُ غيرُ موجود'; return $out; }
        if ($day['state'] === 'closed') { $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'مُقفَلٌ سلفًا — عطالةٌ بالحالة'; return $out; }
        if ($actorId <= 0) { $out['code'] = 422; $out['reason'] = 'لا إقفالَ بلا فاعلٍ معروف'; return $out; }
        $openN = (int) $gate->count('site_day_shift', array('where' => array('day_id' => $dayId, 'state' => 'open')));
        if ($openN > 0) {
            self::logAttempt($gate, $day, 'day_close', '', $actorId, 'SHIFT_STILL_OPEN', "وردياتٌ مفتوحةٌ بلا محضرِ تسليم: $openN");
            $out['code'] = 409; $out['open_shifts'] = $openN; $out['reason'] = 'لا يُقفل اليومُ ووردياتُه مفتوحةٌ بلا محضرِ تسليم'; return $out;
        }
        try {
            $gate->update('site_day_shift', array('state' => 'closed', 'closed_at' => self::now($gate)),
                          array('day_id' => $dayId, 'state' => 'handed_over'));
            $gate->update('site_day', array(
                'state' => 'closed', 'closed_by' => $actorId,
                'closed_at' => self::now($gate), 'close_note' => (string) $note,
            ), array('id' => $dayId));
        } catch (\Throwable $t) { $out['code'] = 500; $out['reason'] = 'تعذّر الإقفال'; return $out; }
        $out['ok'] = true; $out['code'] = 200;
        self::emit($gate, $day, 'operations.site_day.closed', 'site_day', $dayId,
                   array('site_id' => (int) $day['site_id'], 'day_date' => $day['day_date']), 'site_day:' . $dayId . ':closed');
        return $out;
    }

    /** إعادةُ الفتحِ بسببٍ مكتوب — والقيدُ في القاعدةِ يمنعها بلا سبب */
    public static function reopenDay(TenantDb $gate, $dayId, $actorId, $reason)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $dayId = (int) $dayId; $actorId = (int) $actorId;
        if (\trim((string) $reason) === '') { $out['code'] = 422; $out['reason'] = 'لا إعادةَ فتحٍ بلا سببٍ مكتوب'; return $out; }
        $day = self::dayById($gate, $dayId);
        if (!$day || $day['state'] !== 'closed') { $out['code'] = 409; $out['reason'] = 'اليومُ ليس مُقفَلًا'; return $out; }
        try {
            $gate->update('site_day', array(
                'state' => 'reopened', 'reopened_by' => $actorId,
                'reopened_at' => self::now($gate), 'reopen_reason' => (string) $reason,
            ), array('id' => $dayId));
        } catch (\Throwable $t) { $out['code'] = 500; $out['reason'] = 'تعذّرت إعادةُ الفتح'; return $out; }
        $out['ok'] = true; $out['code'] = 200; return $out;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       واقعةُ التوقّف — تُسجَّل مرّةً واحدة، والثاني مرآةٌ بفارقِها
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * ادّعاءُ واقعةِ توقّفٍ بمفتاحِها. أوّلُ سجلٍّ يدّعيها يصير **حاكمًا**،
     * وكلُّ سجلٍّ بعده يُكتب **مرآةً** بفارقِ ساعاتِه — ولا صفَّ ثانٍ للواقعة.
     */
    public static function registerStop(TenantDb $gate, array $o)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'stop_id' => 0, 'role' => '', 'created' => false);
        $key = (string) $o['occurrence_key'];
        if (\strlen($key) !== 40) { $out['code'] = 422; $out['reason'] = 'مفتاحُ الواقعةِ غيرُ صالح'; return $out; }
        $reg = (string) $o['register_name'];
        if (!\in_array($reg, array('unit_time_log', 'timesheet'), true)) { $out['code'] = 422; $out['reason'] = 'سجلُّ مصدرٍ غيرُ معروف'; return $out; }

        $cur = $gate->selectOne('ops_stop_register', array(
            'columns' => array('id', 'authority', 'hours'), 'where' => array('occurrence_key' => $key)));
        if (!$cur) {
            $sla = isset($o['sla_hours']) ? (int) $o['sla_hours'] : 48;
            $newId = 0;
            try {
                $newId = (int) $gate->insert('ops_stop_register', array(
                    'occurrence_key'  => $key,
                    'stop_date'       => (string) $o['stop_date'],
                    'shift'           => (string) $o['shift'],
                    'equipment_id'    => ((int) $o['equipment_id'] > 0) ? (int) $o['equipment_id'] : null,
                    'site_id'         => (isset($o['site_id']) && (int) $o['site_id'] > 0) ? (int) $o['site_id'] : null,
                    'project_id'      => (isset($o['project_id']) && (int) $o['project_id'] > 0) ? (int) $o['project_id'] : null,
                    'ops_state'       => (string) $o['ops_state'],
                    'hours'           => (float) $o['hours'],
                    'resp_party'      => (string) $o['resp_party'],
                    'obligation_type' => (string) $o['obligation_type'],
                    'billable'        => ((int) $o['billable'] ? 1 : 0),
                    'authority'       => $reg,
                    'authority_rule'  => (string) $o['authority_rule'],
                    'authority_ref'   => (string) $o['authority_ref'],
                    'decision'        => 'pending',
                    'sla_due_at'      => self::nowPlusHours($gate, $sla),
                ));
            } catch (\Throwable $t) { $newId = 0; }
            if ($newId > 0) {
                $out['created'] = true; $out['stop_id'] = $newId; $out['role'] = 'AUTHORITY';
                $day = array('company_id' => 0);
                self::emitRaw($gate, 'operations.stop.registered', 'ops_stop', $newId,
                    array('occurrence_key' => $key, 'stop_date' => (string) $o['stop_date'], 'shift' => (string) $o['shift'],
                          'equipment_id' => (int) $o['equipment_id'], 'ops_state' => (string) $o['ops_state'],
                          'hours' => (float) $o['hours'], 'authority' => $reg),
                    'stop:' . $key);
            } else {
                $cur = $gate->selectOne('ops_stop_register', array(
                    'columns' => array('id', 'authority', 'hours'), 'where' => array('occurrence_key' => $key)));
                if (!$cur) { $out['code'] = 500; $out['reason'] = 'تعذّر تسجيلُ الواقعة'; return $out; }
            }
        }
        if ($out['stop_id'] === 0 && $cur) { $out['stop_id'] = (int) $cur['id']; $out['role'] = ($cur['authority'] === $reg) ? 'AUTHORITY' : 'MIRROR'; }

        $auth = $gate->selectOne('ops_stop_register', array('columns' => array('hours'), 'where' => array('occurrence_key' => $key)));
        $authHours = $auth ? (float) $auth['hours'] : 0.0;
        $variance = \round((float) $o['hours'] - $authHours, 2);
        $vrule = ($out['role'] === 'AUTHORITY') ? 'W4_AUTHORITY_SELF'
               : (\abs($variance) < 0.005 ? 'W4_MIRROR_AGREES' : 'W4_MIRROR_VARIANCE_OPEN');
        $src = array(
            'hours_read'     => (float) $o['hours'],
            'variance_hours' => $variance,
            'variance_rule'  => $vrule,
            'variance_note'  => isset($o['variance_note']) ? (string) $o['variance_note'] : '',
            'source_ref'     => (string) $o['authority_ref'],
            'role'           => $out['role'],
            'measured_at'    => self::now($gate),
        );
        $exists = $gate->selectOne('ops_stop_source', array(
            'columns' => array('id'), 'where' => array('occurrence_key' => $key, 'register_name' => $reg)));
        try {
            if ($exists) { $gate->update('ops_stop_source', $src, array('id' => (int) $exists['id'])); }
            else { $gate->insert('ops_stop_source', \array_merge($src, array('occurrence_key' => $key, 'register_name' => $reg))); }
        } catch (\Throwable $t) { $out['code'] = 500; $out['reason'] = 'تعذّر تسجيلُ قراءةِ السجلّ'; return $out; }
        $out['ok'] = true; $out['code'] = $out['created'] ? 201 : 200;
        return $out;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       مسابرُ داخلية — كلُّها عبرَ البوابة
       ═══════════════════════════════════════════════════════════════════════ */

    public static function findDay(TenantDb $gate, $siteId, $date)
    {
        return $gate->selectOne('site_day', array(
            'where' => array('site_id' => (int) $siteId, 'day_date' => (string) $date)));
    }

    public static function dayById(TenantDb $gate, $dayId)
    {
        return $gate->selectOne('site_day', array('where' => array('id' => (int) $dayId)));
    }

    private static function isDate($d)
    {
        return \is_string($d) && \preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;
    }

    /**
     * الطابعُ الزمنيُّ من ساعةِ خادمِ التطبيق.
     * ◆ **ولمَ لا `NOW()`**: الكتابةُ كلُّها عبرَ البوابةِ بقيمٍ مربوطةٍ
     *   (`?`) — ودسُّ `NOW()` قيمةً نصّيّةً يكتبها حرفًا لا دالّةً. وأعمدةُ
     *   `created_at` تبقى على ساعةِ القاعدةِ بقيمتِها الافتراضيّة، فالمصدران
     *   مُعلَنان: المُعلَنُ من التطبيق (`opened_at` · `closed_at` · `measured_at`)
     *   والمُعلَنُ من القاعدة (`created_at` · `updated_at`).
     */
    private static function now(TenantDb $gate)
    {
        return \date('Y-m-d H:i:s');
    }

    private static function nowPlusHours(TenantDb $gate, $hours)
    {
        return \date('Y-m-d H:i:s', \time() + ((int) $hours * 3600));
    }

    /** قيدُ المحاولة — الرفضُ والقبولُ معًا كي يكون المقامُ كاملًا لا مختارًا */
    private static function logAttempt(TenantDb $gate, $day, $kind, $shift, $actorId, $reasonCode, $note, $payloadRef = '', $outcome = 'rejected')
    {
        try {
            return (int) $gate->insert('site_day_attempt', array(
                'day_id'       => (isset($day['id']) && (int) $day['id'] > 0) ? (int) $day['id'] : null,
                'site_id'      => (int) $day['site_id'],
                'day_date'     => (string) $day['day_date'],
                'shift'        => (string) $shift,
                'attempt_kind' => (string) $kind,
                'actor_id'     => ((int) $actorId > 0) ? (int) $actorId : null,
                'outcome'      => (string) $outcome,
                'reason_code'  => (string) $reasonCode,
                'reason_note'  => (string) $note,
                'payload_ref'  => (string) $payloadRef,
            ));
        } catch (\Throwable $t) { return 0; }
    }

    /** نشرُ حقيقةٍ محايدة — و`publishFact` تعيد `null` صامتةً إن أُطفئ الجذر */
    private static function emit(TenantDb $gate, $day, $eventKey, $entityType, $entityId, array $payload, $idem)
    {
        $company = ($day && isset($day['company_id'])) ? (int) $day['company_id'] : 0;
        return self::publish($gate, $company, $eventKey, $entityType, $entityId, $payload, $idem);
    }

    private static function emitRaw(TenantDb $gate, $eventKey, $entityType, $entityId, array $payload, $idem)
    {
        return self::publish($gate, 0, $eventKey, $entityType, $entityId, $payload, $idem);
    }

    /**
     * ◆ **الناشرُ يأخذ `mysqli` بحكمِ عقدِه** (‏ADR-15 · `EventPublisher::publishFact`)
     *   — ودفترُ الأحداثِ الجذرُ ليس جدولَ مستأجِرٍ يُكتب عبرَ البوابة، بل حقيقةٌ
     *   محايدةٌ لها حارسُها الخاصُّ وحصانتُها. فالاتصالُ يُمرَّر من المُستدعي
     *   ولا تُبنى هنا جملةُ SQL واحدة.
     */
    private static function publish(TenantDb $gate, $companyId, $eventKey, $entityType, $entityId, array $payload, $idem)
    {
        $conn = self::$eventConn;
        if (!($conn instanceof \mysqli)) { return null; }
        $pub = \dirname(\dirname(\dirname(__DIR__))) . '/app/Core/EventPublisher.php';
        if (!\is_file($pub)) { return null; }
        require_once $pub;
        try {
            return \App\Core\EventPublisher::publishFact($conn, array(
                'company_id'      => (int) $companyId,
                'event_key'       => $eventKey,
                'category'        => 'operations',
                'source_module'   => 'operations',
                'entity_type'     => $entityType,
                'entity_id'       => (int) $entityId,
                'payload'         => $payload,
                'idempotency_key' => 'w4:' . $idem,
                'source_ref'      => 'SiteDayService',
            ));
        } catch (\Throwable $t) { return null; }
    }
}
