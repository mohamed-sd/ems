<?php
/**
 * app/Services/Fleet/MeterReadingService.php — قراءاتُ العدّادات (M-25)
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-10 §8: «القراءات … **UQ (equipment_id, meter_type, reading_date)** وقيدُ:
 * **value ≥ آخرِ قراءةٍ — لا رجوعَ عدّادٍ إلا بقرارِ تصفيرٍ موثَّق**».
 * §8.3-F2: «قراءةُ عدّادٍ أقلُّ من السابقة ← **422** — وتصفيرٌ بقرارٍ موثَّقٍ
 * **يفتح سلسلةً جديدة**». وErrors: «**409 قراءةُ اليوم قائمة**».
 * UX-04 §3: «الوقاية: الخطة **بعدّاد الساعات** أو الأيام ← استحقاقُ الفحص».
 *
 * ── قاعدتان تحكمان كلَّ ما دونهما ───────────────────────────────────────────
 * ① **العدّادُ سلسلةُ وقائعَ لا حقلٌ مسطَّح.** فالتصحيحُ قراءةٌ جديدةٌ أو تصفيرٌ
 *    موثَّقٌ يفتح سلسلةً — ولا يُمحى ماضٍ ولا يُزوَّر تاريخ.
 * ② **البديلُ يُسمّى بديلًا.** حين لا قراءةَ يُعاد رقمٌ من مصدرٍ آخر **موسومًا
 *    باسمه** (`opening` أو `legacy_proxy`) — فلا يُقدَّم ما ليس عدّادًا عدّادًا.
 *    والبديلُ الموروثُ (مجموعُ ساعات التشغيل) **ليس قراءةَ عدّاد** وهذا مكتوبٌ
 *    في كل مخرجٍ يحمله.
 */

namespace App\Services\Fleet;

require_once __DIR__ . '/../../../includes/catch_log.php';

class MeterReadingService
{
    /** UX-10 §8 نصًّا — لا ثالثَ لهما. */
    const METER_TYPES = array('hour', 'km');

    /** مصادرُ §8 الثلاثة + `reset` (وهو قرارٌ لا قراءةُ ميدان). */
    const SOURCES = array('manual', 'inspection', 'timesheet', 'reset');

    /** ترجمةُ الوحدة الموروثة على `equipments.meter_uom` إلى نوعِ العدّاد. */
    const UOM_MAP = array('ساعات' => 'hour', 'ساعة' => 'hour', 'كم' => 'km', 'كيلومتر' => 'km');

    // ═════════════════════════════════════════════════════════════════════
    // ① التسجيل
    // ═════════════════════════════════════════════════════════════════════

    /**
     * تسجيلُ قراءةٍ في السلسلة الحية.
     *
     * @return array{ok:bool,code:int,reason:string,reading_id:?int,delta:?float,chain_no:?int}
     */
    public static function record($conn, $gate, $companyId, $equipmentId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '',
                     'reading_id' => null, 'delta' => null, 'chain_no' => null);
        $equipmentId = (int) $equipmentId;

        $type = isset($args['meter_type']) ? trim((string) $args['meter_type']) : 'hour';
        if (!in_array($type, self::METER_TYPES, true)) {
            $out['code'] = 422; $out['reason'] = 'نوع عداد خارج (hour · km) — قائمة UX-10 §8'; return $out;
        }
        $date = isset($args['reading_date']) ? trim((string) $args['reading_date']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $out['code'] = 422; $out['reason'] = 'تاريخ القراءة إلزامي — القراءة واقعة بيومها'; return $out;
        }
        if (!isset($args['value']) || trim((string) $args['value']) === '') {
            $out['code'] = 422; $out['reason'] = 'قيمة القراءة إلزامية'; return $out;
        }
        $value = round((float) $args['value'], 2);
        if ($value < 0) { $out['code'] = 422; $out['reason'] = 'قيمة عداد سالبة'; return $out; }

        $source = isset($args['source']) ? trim((string) $args['source']) : 'manual';
        if (!in_array($source, self::SOURCES, true) || $source === 'reset') {
            // `reset` بابُه `reset()` وحدَه — لا يُدخَل من باب القراءة العادية
            $source = 'manual';
        }

        // المعدةُ من النطاق (البوابةُ ترفض الأجنبية)
        $eq = null;
        try { $eq = $gate->selectOne('equipments', array('columns' => array('id'), 'where' => array('id' => $equipmentId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $eq'); $eq = null; }
        if (!$eq) { $out['code'] = 422; $out['reason'] = 'المعدة غير موجودة في نطاقك'; return $out; }

        // «409 قراءةُ اليوم قائمة» — بمرجعها لا برفضٍ أصمّ
        $same = self::readingOn($gate, $equipmentId, $type, $date);
        if ($same) {
            $out['code'] = 409;
            $out['reason'] = 'للمعدة قراءة بتاريخ ' . $date . ' سلفا (#' . (int) $same['id']
                           . ' بقيمة ' . $same['value'] . ') — القراءة واقعة يوم لا تكرر';
            return $out;
        }

        $chain = self::currentChain($gate, $equipmentId, $type);
        $prev  = self::lastReading($gate, $equipmentId, $type, $chain, $date);

        // ── القاعدةُ الحاكمة: «value ≥ آخرِ قراءة» داخل السلسلة ────────────
        if ($prev !== null && $value < (float) $prev['value']) {
            $out['code'] = 422;
            $out['reason'] = 'قراءة (' . $value . ') أقل من آخر قراءة في السلسلة ('
                           . $prev['value'] . ' بتاريخ ' . $prev['reading_date'] . ') — '
                           . 'لا رجوع عداد إلا **بقرار تصفير موثق** يفتح سلسلة جديدة (UX-10 §8)';
            return $out;
        }
        // ولا يجوز أن تكون أكبرَ من قراءةٍ **لاحقةٍ** مسجَّلةٍ سلفًا (إدخالٌ متأخر)
        $next = self::nextReading($gate, $equipmentId, $type, $chain, $date);
        if ($next !== null && $value > (float) $next['value']) {
            $out['code'] = 422;
            $out['reason'] = 'قراءة (' . $value . ') تتجاوز قراءة لاحقة مسجلة ('
                           . $next['value'] . ' بتاريخ ' . $next['reading_date'] . ') — السلسلة لا تتراجع';
            return $out;
        }

        $delta = $prev !== null ? round($value - (float) $prev['value'], 2) : null;

        $newId = null;
        try {
            $gate->runInTransaction(function ($g) use ($equipmentId, $type, $chain, $date, $value,
                                                       $delta, $source, $args, $actor, &$newId) {
                $newId = (int) $g->insert('meter_readings', array(
                    'equipment_id' => $equipmentId,
                    'meter_type'   => $type,
                    'chain_no'     => $chain,
                    'reading_date' => $date,
                    'value'        => $value,
                    'delta'        => $delta,
                    'source'       => $source,
                    'source_ref'   => isset($args['source_ref']) && trim((string) $args['source_ref']) !== ''
                                      ? mb_substr(trim((string) $args['source_ref']), 0, 80) : null,
                    'note'         => isset($args['note']) && trim((string) $args['note']) !== ''
                                      ? mb_substr(trim((string) $args['note']), 0, 255) : null,
                    'recorded_by'  => (int) $actor ?: null,
                ));
                // ── المرآةُ الموروثة تُحدَّث كي لا تكذب الشاشاتُ القديمة ──
                // (ساعاتٌ فقط — `operating_hours` عمودُ ساعاتٍ بطبيعته)
                if ($type === 'hour') {
                    $g->update('equipments', array('operating_hours' => (int) round($value)),
                               array('id' => $equipmentId));
                }
                return true;
            }, 'M-25 meter reading eq#' . $equipmentId);
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['code'] = 409; $out['reason'] = 'قراءة اليوم قائمة (سباق كتابة)'; return $out;
            }
            $out['code'] = 422; $out['reason'] = 'تعذر التسجيل: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'record', (int) $newId,
            $prev !== null ? array('value' => $prev['value'], 'date' => $prev['reading_date']) : array(),
            array('value' => $value, 'date' => $date, 'delta' => $delta, 'source' => $source));

        $out['ok'] = true; $out['code'] = 200;
        $out['reading_id'] = $newId; $out['delta'] = $delta; $out['chain_no'] = $chain;
        return $out;
    }

    /**
     * **تصفيرٌ بقرارٍ موثَّق** — يفتح سلسلةً جديدة (§8.3-F2 نصًّا).
     * السببُ ومرجعُ المستند إلزاميان: تصفيرٌ بلا قرارٍ يمحو تاريخَ صيانةٍ كاملًا.
     * @return array{ok:bool,code:int,reason:string,reading_id:?int,chain_no:?int}
     */
    public static function reset($conn, $gate, $companyId, $equipmentId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'reading_id' => null, 'chain_no' => null);
        $equipmentId = (int) $equipmentId;

        $type = isset($args['meter_type']) ? trim((string) $args['meter_type']) : 'hour';
        if (!in_array($type, self::METER_TYPES, true)) {
            $out['code'] = 422; $out['reason'] = 'نوع عداد خارج (hour · km)'; return $out;
        }
        $date = isset($args['reading_date']) ? trim((string) $args['reading_date']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $out['code'] = 422; $out['reason'] = 'تاريخ التصفير إلزامي'; return $out;
        }
        $value = isset($args['value']) ? round((float) $args['value'], 2) : 0.0;
        if ($value < 0) { $out['code'] = 422; $out['reason'] = 'قيمة بداية السلسلة سالبة'; return $out; }

        $reason = isset($args['reset_reason']) ? trim((string) $args['reset_reason']) : '';
        $docRef = isset($args['reset_doc_ref']) ? trim((string) $args['reset_doc_ref']) : '';
        if ($reason === '' || $docRef === '') {
            $out['code'] = 422;
            $out['reason'] = 'التصفير يلزمه **سبب ومرجع مستند** — «لا رجوع عداد إلا بقرار موثق» (UX-10 §8)';
            return $out;
        }

        $eq = null;
        try { $eq = $gate->selectOne('equipments', array('columns' => array('id'), 'where' => array('id' => $equipmentId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $eq'); $eq = null; }
        if (!$eq) { $out['code'] = 422; $out['reason'] = 'المعدة غير موجودة في نطاقك'; return $out; }

        $same = self::readingOn($gate, $equipmentId, $type, $date);
        if ($same) {
            $out['code'] = 409;
            $out['reason'] = 'للمعدة قراءة بتاريخ ' . $date . ' سلفا — صفر بتاريخ خال';
            return $out;
        }

        $newChain = self::currentChain($gate, $equipmentId, $type) + 1;
        $newId = null;
        try {
            $gate->runInTransaction(function ($g) use ($equipmentId, $type, $newChain, $date, $value,
                                                       $reason, $docRef, $actor, &$newId) {
                $newId = (int) $g->insert('meter_readings', array(
                    'equipment_id' => $equipmentId, 'meter_type' => $type,
                    'chain_no' => $newChain, 'reading_date' => $date, 'value' => $value,
                    'delta' => null, 'source' => 'reset', 'is_reset' => 1,
                    'reset_reason' => mb_substr($reason, 0, 255),
                    'reset_doc_ref' => mb_substr($docRef, 0, 120),
                    'note' => 'بداية سلسلة جديدة #' . $newChain . ' — السابقة محفوظة كاملة',
                    'recorded_by' => (int) $actor ?: null,
                ));
                if ($type === 'hour') {
                    $g->update('equipments', array('operating_hours' => (int) round($value)),
                               array('id' => $equipmentId));
                }
                return true;
            }, 'M-25 meter reset eq#' . $equipmentId);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذر التصفير: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'reset', (int) $newId,
            array('chain' => $newChain - 1), array('chain' => $newChain, 'value' => $value, 'doc' => $docRef));

        $out['ok'] = true; $out['code'] = 200; $out['reading_id'] = $newId; $out['chain_no'] = $newChain;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② القراءةُ الحاكمة — وسلّمُ مصادرها المعلَن
    // ═════════════════════════════════════════════════════════════════════

    /**
     * العدّادُ الحاليُّ لمعدةٍ — **بمصدره مسمًّى**.
     *
     * السلّم: ① آخرُ قراءةٍ في السلسلة الحية ← ② `equipments.opening_meter`
     * ← ③ **بديلٌ موروثٌ مُعلَنٌ باسمه** (`legacy_proxy`: مجموعُ ساعات التشغيل
     * من سجل الدوام). والثالثُ **ليس قراءةَ عدّاد** — وهذا مكتوبٌ في `note`
     * فلا يُقرأ رقمُه على أنه عدّادٌ في تقريرٍ ولا شاشة.
     *
     * @return array{value:float,source:string,as_of:?string,is_reading:bool,note:string}
     */
    public static function currentMeter($conn, $gate, $companyId, $equipmentId, $meterType = 'hour')
    {
        $equipmentId = (int) $equipmentId;
        $meterType = in_array((string) $meterType, self::METER_TYPES, true) ? (string) $meterType : 'hour';

        $chain = self::currentChain($gate, $equipmentId, $meterType);
        $last = self::lastReading($gate, $equipmentId, $meterType, $chain, null);
        if ($last !== null) {
            return array('value' => (float) $last['value'], 'source' => 'reading',
                         'as_of' => (string) $last['reading_date'], 'is_reading' => true,
                         'note' => 'قراءة عداد مسجلة (سلسلة ' . $chain . ' · مصدرها '
                                 . $last['source'] . ')');
        }

        $eq = null;
        try {
            $eq = $gate->selectOne('equipments', array(
                'columns' => array('id', 'opening_meter', 'operating_hours'),
                'where' => array('id' => $equipmentId)));
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $eq'); $eq = null; }

        if ($eq && $eq['opening_meter'] !== null && (float) $eq['opening_meter'] > 0) {
            return array('value' => (float) $eq['opening_meter'], 'source' => 'opening',
                         'as_of' => null, 'is_reading' => false,
                         'note' => 'عداد افتتاحي مسجل على المعدة — بلا تاريخ قراءة');
        }

        // ③ البديلُ الموروث — **يُسمّى بديلًا**
        $proxy = self::legacyProxyHours($gate, $equipmentId);
        return array('value' => $proxy, 'source' => 'legacy_proxy', 'as_of' => null, 'is_reading' => false,
                     'note' => '⚠ ليس قراءة عداد: مجموع ساعات التشغيل من سجل الدوام '
                             . '(بديل موروث حتى تسجل أول قراءة)');
    }

    /**
     * استحقاقُ الوقائية بعدّاد الساعات (UX-04 §3).
     * @return array{due:bool,reason:string,current:float,due_at:?float,remaining:?float,
     *               meter_source:string,is_reading:bool}
     */
    public static function preventiveDue($conn, $gate, $companyId, array $plan)
    {
        $basis = trim((string) (isset($plan['trigger_basis']) ? $plan['trigger_basis'] : ''));
        $eqId = (int) (isset($plan['equipment_id']) ? $plan['equipment_id'] : 0);
        $interval = (float) (isset($plan['interval_value']) ? $plan['interval_value'] : 0);
        $out = array('due' => false, 'reason' => '', 'current' => 0.0, 'due_at' => null,
                     'remaining' => null, 'meter_source' => 'none', 'is_reading' => false);

        if ($basis !== 'ساعات') {
            $out['reason'] = 'الخطة بأساس زمني لا بعداد — الاستحقاق بتاريخها';
            return $out;
        }
        if ($eqId <= 0) { $out['reason'] = 'خطة بلا معدة محددة — لا عداد يقاس عليه'; return $out; }
        if ($interval <= 0) { $out['reason'] = 'خطة بلا فترة موجبة — لا استحقاق يحسب'; return $out; }

        $m = self::currentMeter($conn, $gate, $companyId, $eqId, 'hour');
        $out['current'] = $m['value'];
        $out['meter_source'] = $m['source'];
        $out['is_reading'] = (bool) $m['is_reading'];

        $lastDone = (isset($plan['last_done_meter']) && $plan['last_done_meter'] !== null)
                    ? (float) $plan['last_done_meter'] : null;
        $dueAt = ($plan['next_due_meter'] !== null && (float) $plan['next_due_meter'] > 0)
                 ? (float) $plan['next_due_meter']
                 : ($lastDone !== null ? $lastDone + $interval : null);
        if ($dueAt === null) {
            $out['reason'] = 'الخطة بلا عداد آخر تنفيذ ولا استحقاق مسجل — يعلن ولا يخترع';
            return $out;
        }
        $out['due_at'] = round($dueAt, 2);
        $out['remaining'] = round($dueAt - $m['value'], 2);
        $out['due'] = ($m['value'] >= $dueAt);
        $out['reason'] = $out['due']
            ? ('مستحقة: العداد ' . $m['value'] . ' بلغ ' . round($dueAt, 2) . ' — ' . $m['note'])
            : ('غير مستحقة: يتبقى ' . $out['remaining'] . ' — ' . $m['note']);
        return $out;
    }

    /** «عدّاداتٌ لم تُحدَّث» بعدّادها (UX-10 §7). */
    public static function staleMeters($gate, $days = 14)
    {
        $days = (int) $days > 0 ? (int) $days : 14;
        $cut = date('Y-m-d', strtotime('-' . $days . ' day'));
        try {
            return $gate->scopedQuery(array('scope' => array('e' => 'equipments')),
                "SELECT e.id, e.name,
                        (SELECT MAX(r.reading_date) FROM meter_readings r
                          WHERE r.equipment_id = e.id AND r.meter_type = 'hour') AS last_reading
                   FROM equipments e
                  WHERE {TENANT_SCOPE} AND COALESCE(e.status,1) = 1
                 HAVING last_reading IS NULL OR last_reading < ?
                  ORDER BY last_reading IS NOT NULL, last_reading", array($cut));
        } catch (\Throwable $t) { return array(); }
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ الالتقاطُ من سجل الدوام — الوصلُ الأول
    // ═════════════════════════════════════════════════════════════════════

    /**
     * التقاطُ قراءةِ نهاية الوردية من صف الدوام (`source='timesheet'`).
     *
     * **لا تُفشِل كتابةَ الدوام أبدًا**: تعارضُ عدّادٍ أو قراءةٌ قائمةٌ لليوم
     * تُرجَع سببًا معلَنًا ويمضي الدوام — مسجِّلُ تاريخٍ لا مانعُ ميدان (نمطُ
     * مرآة `mirrorFromTimesheet` نفسِه). وعطالتُها مفتاحُها الفريد.
     *
     * @return array{ok:bool,skipped:string,reading_id:?int}
     */
    public static function captureFromTimesheet($conn, $gate, $companyId, array $ts, $actor)
    {
        $out = array('ok' => false, 'skipped' => '', 'reading_id' => null);
        $eqId = (int) (isset($ts['eq_id']) ? $ts['eq_id'] : (isset($ts['equipment_id']) ? $ts['equipment_id'] : 0));
        if ($eqId <= 0) { $out['skipped'] = 'لا معدة على الصف'; return $out; }

        // العدّادُ ساعاتٌ ودقائقُ وثوانٍ في الصف الموروث — تُجمع ساعاتٍ عشرية
        $h = isset($ts['end_hours']) ? (float) $ts['end_hours'] : 0.0;
        $m = isset($ts['end_minutes']) ? (float) $ts['end_minutes'] : 0.0;
        $s = isset($ts['end_seconds']) ? (float) $ts['end_seconds'] : 0.0;
        if ($h <= 0 && $m <= 0 && $s <= 0) {
            $out['skipped'] = 'لا قراءة عداد على الصف (الحقول صفر) — لا يخترع رقم';
            return $out;
        }
        $value = round($h + ($m / 60.0) + ($s / 3600.0), 2);
        $date = isset($ts['date']) ? (string) $ts['date'] : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $out['skipped'] = 'تاريخ الصف غير صالح'; return $out; }

        $r = self::record($conn, $gate, $companyId, $eqId, array(
            'meter_type' => 'hour', 'reading_date' => $date, 'value' => $value,
            'source' => 'timesheet',
            'source_ref' => 'TS-' . (int) (isset($ts['id']) ? $ts['id'] : 0),
            'note' => 'قراءة نهاية الوردية من سجل الدوام',
        ), $actor);

        if (!empty($r['ok'])) { $out['ok'] = true; $out['reading_id'] = $r['reading_id']; return $out; }
        // 409 عطالةٌ طبيعية · 422 تعارضُ عدّادٍ يُعلَن ولا يوقف الميدان
        $out['skipped'] = $r['code'] . ': ' . $r['reason'];
        if ((int) $r['code'] === 422) {
            error_log('M-25 meter conflict on TS-' . (isset($ts['id']) ? $ts['id'] : '?') . ': ' . $r['reason']);
        }
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ④ قراءاتٌ ومساعدات
    // ═════════════════════════════════════════════════════════════════════

    /** رقمُ السلسلة الحية (أكبرُ chain_no مسجَّل — أو 1 حين لا قراءة). */
    public static function currentChain($gate, $equipmentId, $meterType)
    {
        try {
            $rows = $gate->scopedQuery(array('scope' => array('r' => 'meter_readings')),
                "SELECT MAX(r.chain_no) c FROM meter_readings r
                  WHERE {TENANT_SCOPE} AND r.equipment_id = ? AND r.meter_type = ?",
                array((int) $equipmentId, (string) $meterType));
            $c = ($rows && $rows[0]['c'] !== null) ? (int) $rows[0]['c'] : 0;
            return $c > 0 ? $c : 1;
        } catch (\Throwable $t) { return 1; }
    }

    /** آخرُ قراءةٍ في السلسلة قبل تاريخٍ (أو مطلقًا حين `$before === null`). */
    public static function lastReading($gate, $equipmentId, $meterType, $chain, $before)
    {
        try {
            $where = ''; $params = array((int) $equipmentId, (string) $meterType, (int) $chain);
            if ($before !== null) { $where = ' AND r.reading_date < ?'; $params[] = (string) $before; }
            $rows = $gate->scopedQuery(array('scope' => array('r' => 'meter_readings')),
                "SELECT r.* FROM meter_readings r
                  WHERE {TENANT_SCOPE} AND r.equipment_id = ? AND r.meter_type = ? AND r.chain_no = ?"
                . $where . "
                  ORDER BY r.reading_date DESC, r.id DESC LIMIT 1", $params);
            return $rows ? $rows[0] : null;
        } catch (\Throwable $t) { return null; }
    }

    /** أولُ قراءةٍ **بعد** تاريخٍ في السلسلة — لحراسة الإدخال المتأخر. */
    public static function nextReading($gate, $equipmentId, $meterType, $chain, $after)
    {
        try {
            $rows = $gate->scopedQuery(array('scope' => array('r' => 'meter_readings')),
                "SELECT r.* FROM meter_readings r
                  WHERE {TENANT_SCOPE} AND r.equipment_id = ? AND r.meter_type = ? AND r.chain_no = ?
                    AND r.reading_date > ?
                  ORDER BY r.reading_date ASC, r.id ASC LIMIT 1",
                array((int) $equipmentId, (string) $meterType, (int) $chain, (string) $after));
            return $rows ? $rows[0] : null;
        } catch (\Throwable $t) { return null; }
    }

    /** قراءةُ يومٍ بعينه (لكشف 409). */
    public static function readingOn($gate, $equipmentId, $meterType, $date)
    {
        try {
            $rows = $gate->scopedQuery(array('scope' => array('r' => 'meter_readings')),
                "SELECT r.id, r.value, r.chain_no FROM meter_readings r
                  WHERE {TENANT_SCOPE} AND r.equipment_id = ? AND r.meter_type = ? AND r.reading_date = ?
                  LIMIT 1", array((int) $equipmentId, (string) $meterType, (string) $date));
            return $rows ? $rows[0] : null;
        } catch (\Throwable $t) { return null; }
    }

    /** سلسلةُ معدةٍ للعرض. */
    public static function chainOf($gate, $equipmentId, $meterType = 'hour', $limit = 100)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('r' => 'meter_readings')),
                "SELECT r.* FROM meter_readings r
                  WHERE {TENANT_SCOPE} AND r.equipment_id = ? AND r.meter_type = ?
                  ORDER BY r.chain_no DESC, r.reading_date DESC, r.id DESC LIMIT " . (int) $limit,
                array((int) $equipmentId, (string) $meterType));
        } catch (\Throwable $t) { return array(); }
    }

    /**
     * البديلُ الموروث — **ليس عدّادًا**: مجموعُ ساعات التشغيل من سجل الدوام.
     * (نصُّ الدالّة الموروثة `mnt_equipment_actual_hours` نفسُه، محفوظًا كي
     * لا تتغير أرقامُ الوقائية القائمة فجأةً حين لا قراءةَ بعد.)
     */
    public static function legacyProxyHours($gate, $equipmentId)
    {
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('t' => 'timesheet'), 'enrich' => array('o' => 'operations')),
                "SELECT COALESCE(SUM(t.operator_hours),0) AS h
                   FROM timesheet t LEFT JOIN operations o ON o.id = t.operator
                  WHERE {TENANT_SCOPE} AND o.equipment = ?", array((int) $equipmentId));
            return $rows ? round((float) $rows[0]['h'], 2) : 0.0;
        } catch (\Throwable $t) { return 0.0; }
    }

    /** تدقيقُ N-02 — لا يرمي. */
    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'fleet', 'meter_readings', $action, (int) $rowId, $before, $after,
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
