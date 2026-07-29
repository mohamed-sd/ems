<?php
/**
 * حارس الوثائق المنتهية — DocumentGuard (UX-10 §8.2 · UX-03 §8.3)
 * ───────────────────────────────────────────────────────────────────────────
 * «معدةٌ بوثيقةٍ منتهيةٍ تُعلَّم **حاجبةً للاعتماد**» (UX-10 §8.2)
 * «معدةٌ بوثيقةٍ منتهية → تحذيرٌ حاجبٌ للاعتماد» (UX-03 §8.3)
 *
 * فالحجبُ عند **اعتماد الموقع** لا عند الإدخال — واليومُ يُسجَّل كما وقع ثم
 * يُوقَف قبل أن يصير مالًا. وهذا هو نمطُ CapacityGuard نفسُه: لا يمنع الكتابة
 * ويمنع الاعتماد؛ فالواقعُ يُدوَّن، والحكمُ عليه لاحق.
 *
 * ── لماذا يحجب أصلًا ──────────────────────────────────────────────────────
 * المعدةُ برخصةٍ منتهيةٍ مسؤوليةٌ قانونية: يومُ عملٍ سُجِّل بها قد يسقط كلُّه
 * أمام العميل أو أمام التأمين. فالحجبُ هنا ليس تشدّدًا إداريًّا — هو منعُ
 * فوترةِ يومٍ قد لا يُدفَع ثمنُه.
 *
 * ── قراراتُ المالك (2026-07-29) ───────────────────────────────────────────
 * ① **يحجب**: المعدة (استمارة · تأمين · فحص دوري · رخصة تشغيل)
 *              ‖ المشغّل (رخصة قيادة).
 * ② **ينبّه ولا يحجب**: هوية · جواز سفر · عقد عمل · تصريح · أخرى.
 *    وهي وثائقُ صفةٍ لا وثائقُ أهليةٍ للتشغيل — انتهاؤها لا يُبطل يومَ عمل.
 * ③ **المقياس**: `expiry_date < entry_date` — السريانُ **يومَ العمل** لا يومَ
 *    الاعتماد. فوثيقةٌ كانت ساريةً يوم التشغيل ثم انتهت لا تُبطل ما مضى،
 *    والعكسُ صحيح: تجديدُها اليوم لا يُشرعن يومًا شُغِّل وهي منتهية.
 * ④ **التسليم على `monitor`** — والسببُ مقيسٌ لا مقدَّر: 138 من 138 واقعةٍ
 *    `submitted` تُحجب اليوم (78 بالمعدة · 138 بالمشغّل)، و37 وثيقةً حاجبةً
 *    منتهيةً (12 رخصةَ تشغيل · 25 رخصةَ قيادة) تحتاج تجديدًا قبل `enforce`.
 *    فالعلَمُ هنا إلزامٌ لا احتياط: `enforce` من أول يوم يجمّد الاعتمادَ كلَّه.
 *
 * ── العَلَم ───────────────────────────────────────────────────────────────
 *   `EMS_DOC_EXPIRY_GUARD` (الافتراض `off`):
 *     off      → صفرُ أثر
 *     monitor  → يقيس ويسجّل في error_log **ويمرّ**
 *     enforce  → يرفض بـ422 بأسبابه المسمّاة
 *
 * ⚠️ **الوصلُ في موضعين لا موضعٍ واحد**: `TimesheetEntryService::approve()`
 *    للسجل القانوني، و`Approvals/hours_approval_handler.php` للمسار الحيّ.
 *    والاكتفاءُ بالأول يجعل الحارسَ زخرفةً — المرآةُ تمرّر `enforce_capacity=false`
 *    عمدًا (مسجِّلُ تاريخٍ لا مُنفِذُ قواعد)، فلا يقع حجبٌ حيٌّ أبدًا.
 */

namespace App\Services\Unit;

class DocumentGuard
{
    /**
     * وثائقُ الأهلية للتشغيل — الحاجبةُ وحدَها (قرارُ المالك ①).
     * القيمُ نصُّ ENUM في `equipment_documents.doc_type` حرفًا بحرف.
     */
    const BLOCKING = array(
        'equipment' => array('استمارة', 'تأمين', 'فحص دوري', 'رخصة تشغيل'),
        'operator'  => array('رخصة قيادة'),
    );

    /** وضعُ الحارس: off · monitor · enforce (نمطُ `EMS_RESP_PARTY_STRICT`). */
    public static function mode()
    {
        $m = function_exists('ems_env')
            ? strtolower(trim((string) ems_env('EMS_DOC_EXPIRY_GUARD', 'off'))) : 'off';
        return in_array($m, array('off', 'monitor', 'enforce'), true) ? $m : 'off';
    }

    // ═══════════════════════════════════════════════════════════════════════
    // القياس
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * الوثائقُ الحاجبةُ المنتهيةُ **يومَ العمل** لمحورَي الواقعة.
     *
     * قراءةٌ خالصةٌ بلا كتابة، فتصلح للمعاينة كما تصلح للقرار — ولا تمرّ على
     * `status` البشريّ ('سارية' … ) لأنه رأيٌ يُصان يدويًّا؛ الانتهاءُ يُحسب من
     * `expiry_date` وحدَها (وهو نصُّ تعليق العمود في المخطط).
     *
     * `expiry_date IS NULL` = وثيقةٌ لا تنتهي — لا تحجب.
     *
     * @param  int    $equipmentId معرّفُ المعدة (0 = لا محور)
     * @param  int    $operatorId  معرّفُ المشغّل employees.id (0 = لا محور)
     * @param  string $onDate      يومُ العمل Y-m-d — لا يومُ الاعتماد (قرار ③)
     * @return array[] صفوفُ {subject_type, doc_type, doc_no, expiry_date, subject_id}
     */
    public static function expiredFor(\mysqli $conn, $companyId, $equipmentId, $operatorId, $onDate)
    {
        $companyId   = (int) $companyId;
        $equipmentId = (int) $equipmentId;
        $operatorId  = (int) $operatorId;
        $onDate      = trim((string) $onDate);

        if ($onDate === '' || ($equipmentId <= 0 && $operatorId <= 0)) { return array(); }

        $eqList = self::inList($conn, self::BLOCKING['equipment']);
        $opList = self::inList($conn, self::BLOCKING['operator']);

        $st = $conn->prepare(
            "SELECT subject_type, subject_id, doc_type, doc_no, expiry_date
               FROM equipment_documents
              WHERE company_id = ?
                AND COALESCE(is_deleted, 0) = 0
                AND expiry_date IS NOT NULL
                AND expiry_date < ?
                AND ( (subject_type = 'equipment' AND subject_id = ? AND doc_type IN ({$eqList}))
                   OR (subject_type = 'operator'  AND subject_id = ? AND doc_type IN ({$opList})) )
              ORDER BY subject_type, expiry_date"
        );
        $st->bind_param('isii', $companyId, $onDate, $equipmentId, $operatorId);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        return $rows;
    }

    /**
     * صياغةُ سببٍ واحدٍ بلغة المهمة (العقيدة ⑥): تسمّي الوثيقةَ وصاحبَها
     * وتاريخَ انتهائها وتقول ماذا يُفعل — ولا تذكر جدولًا ولا عمودًا.
     */
    public static function reasonOf(array $doc)
    {
        $who = ($doc['subject_type'] === 'equipment')
             ? 'المعدة #' . $doc['subject_id']
             : 'المشغّل #' . $doc['subject_id'];
        $no  = (isset($doc['doc_no']) && trim((string) $doc['doc_no']) !== '')
             ? ' رقم ' . $doc['doc_no'] : '';

        return $who . ': «' . $doc['doc_type'] . '»' . $no
             . ' منتهيةٌ بتاريخ ' . $doc['expiry_date']
             . ' — جدّدها من شاشة وثائق المعدات والمشغّلين ثم أعد الاعتماد';
    }

    // ═══════════════════════════════════════════════════════════════════════
    // المنع — شرطُ اعتماد الموقع
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * الحكمُ على محورَين معلومَين — الصورةُ الأساسية التي تبني عليها البقية.
     * يحترم العَلَم: `off` يمرّ صامتًا، و`monitor` يسجّل ويمرّ، و`enforce` يرفض.
     *
     * @param  string $ref وسمُ السجل في `error_log` (مثل `ts#12` أو `entry#7`)
     * @return array{ok: bool, code: int, reasons: string[], docs: array[]}
     */
    public static function assertForRefs(\mysqli $conn, $companyId, $equipmentId, $operatorId, $onDate, $ref = '')
    {
        $mode = self::mode();
        if ($mode === 'off') {
            return array('ok' => true, 'code' => 200, 'reasons' => array(), 'docs' => array());
        }

        $docs = self::expiredFor($conn, $companyId, $equipmentId, $operatorId, $onDate);
        if (empty($docs)) {
            return array('ok' => true, 'code' => 200, 'reasons' => array(), 'docs' => array());
        }

        $reasons = array();
        foreach ($docs as $d) { $reasons[] = self::reasonOf($d); }

        if ($mode === 'enforce') {
            return array('ok' => false, 'code' => 422, 'reasons' => $reasons, 'docs' => $docs);
        }

        // monitor — يرصد ويمرّ: أسبوعُ قياسٍ قبل الإلزام، والرقمُ يُقرأ من السجل
        error_log('[doc-expiry] ' . ($ref !== '' ? $ref . ' ' : '')
            . 'وثائقُ أهليةٍ منتهيةٌ يومَ ' . $onDate . ' (' . count($docs) . '): '
            . implode(' · ', $reasons));

        return array('ok' => true, 'code' => 200, 'reasons' => $reasons, 'docs' => $docs, 'monitored' => true);
    }

    /**
     * الحكمُ على واقعةٍ في السجل القانوني — نظيرُ `CapacityGuard::assertSiteApprovable`.
     *
     * @return array{ok: bool, code: int, reasons: string[], docs: array[]}
     */
    public static function assertDocumentsValid(\mysqli $conn, $companyId, $entryId)
    {
        if (self::mode() === 'off') {
            return array('ok' => true, 'code' => 200, 'reasons' => array(), 'docs' => array());
        }

        $companyId = (int) $companyId;
        $entryId   = (int) $entryId;

        $st = $conn->prepare(
            "SELECT equipment_id, operator_employee_id, entry_date
               FROM unit_entries WHERE company_id = ? AND id = ? LIMIT 1"
        );
        $st->bind_param('ii', $companyId, $entryId);
        $st->execute();
        $e = $st->get_result()->fetch_assoc();
        $st->close();

        // واقعةٌ غير موجودة: لا يخترع الحارسُ حكمًا — يمرّ، والحكمُ على وجودها
        // لآلة الحالات نفسِها (تُرجع 404 قبل أن تصل إلى هنا).
        if (!$e) {
            return array('ok' => true, 'code' => 200, 'reasons' => array(), 'docs' => array());
        }

        return self::assertForRefs($conn, $companyId,
            (int) $e['equipment_id'], (int) $e['operator_employee_id'],
            (string) $e['entry_date'], 'entry#' . $entryId);
    }

    /**
     * الحكمُ على صفٍّ في المسار الحيّ `timesheet` — والمحاورُ تُقرأ منه مباشرةً
     * لا من مرآته.
     *
     * ⚠️ **ولماذا لا من المرآة**: 221 من 361 صفًّا لا مرآةَ لها (مقيس)، فلو
     * عُلِّق الحارسُ على `sync_uuid` لفتح على مصراعيه لأكثرِ الصفوف — وهو ما وقع
     * لحارس الطاقة (fail-open معلَنٌ هناك لأن مصدرَه العلمُ لا الوثيقة).
     *
     * ⚠️⚠️ **اشتقاقُ المعدة يمرّ بـ`operations` — ولا يُختصر** (عطبٌ أُصلح 2026-07-29):
     * `timesheet.operator` **ليس رقمَ المعدة** بل رقمُ **صفِّ التشغيل**
     * (`operations.id`)، والمعدةُ الحقيقية `operations.equipment`. وكان الحارسُ
     * يقرأ `operator` معرّفًا للمعدة فيفحص **وثائقَ آلةٍ أخرى**: صفُّ الدوام 1801
     * معدتُه 7 (HX 340 SL) والحارسُ يحكم عليه برخصة المعدة 5. والقياسُ يومَ
     * الإصلاح: **351 من 362 صفًّا** يختلف فيها الرقمان — أي الخطأُ في كلِّها
     * عمليًّا، **بالحجب الباطل وبالتمرير الباطل معًا** (المعدةُ المنتهيةُ فعلًا
     * لا يراها الحارسُ أبدًا). والوصلةُ هنا هي نفسُها المعتمدةُ في
     * `TimesheetEntryService::mirrorFromTimesheet` — مصدرُ دلالةٍ واحد.
     * (وهي گوتشا المشروع المسجَّلة: «المعدة 12 القديمة كانت `operations.id`».)
     *
     * @return array{ok: bool, code: int, reasons: string[], docs: array[]}
     */
    public static function assertForTimesheet(\mysqli $conn, $companyId, $timesheetId)
    {
        if (self::mode() === 'off') {
            return array('ok' => true, 'code' => 200, 'reasons' => array(), 'docs' => array());
        }

        $companyId   = (int) $companyId;
        $timesheetId = (int) $timesheetId;

        // الشركةُ تُقرأ من الصفّ نفسِه لا من الجلسة: وضعُ السوبر يجلس بشركةٍ 0،
        // فتعليقُ الوثائق على جلسته يفتح الحارسَ على مصراعيه. والنطاقُ مضمونٌ
        // سلفًا — المنادي أثبت ملكيةَ الصفّ عبر بوابة العزل قبل الوصول إلى هنا.
        $st = $conn->prepare(
            "SELECT ts.company_id, o.equipment AS equipment_ref, ts.employee_id, ts.`date`
               FROM timesheet ts
               LEFT JOIN operations o ON o.id = ts.`operator`
              WHERE ts.id = ? LIMIT 1"
        );
        $st->bind_param('i', $timesheetId);
        $st->execute();
        $t = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$t) {
            return array('ok' => true, 'code' => 200, 'reasons' => array(), 'docs' => array());
        }
        // شركةٌ صُرِّح بها وخالفت الصفَّ: ليس صفَّنا — لا يحكم عليه هذا الحارس
        if ($companyId > 0 && (int) $t['company_id'] !== $companyId) {
            return array('ok' => true, 'code' => 200, 'reasons' => array(), 'docs' => array());
        }

        return self::assertForRefs($conn, (int) $t['company_id'],
            (int) $t['equipment_ref'], (int) $t['employee_id'],
            (string) $t['date'], 'ts#' . $timesheetId);
    }

    // ═══════════════════════════════════════════════════════════════════════

    /** قائمةُ IN مهرَّبةٌ من ثوابتَ داخلية (لا مدخلَ مستخدمٍ يمرّ من هنا). */
    private static function inList(\mysqli $conn, array $vals)
    {
        $out = array();
        foreach ($vals as $v) { $out[] = "'" . $conn->real_escape_string($v) . "'"; }
        return implode(',', $out);
    }
}
