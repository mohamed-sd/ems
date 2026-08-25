<?php
/**
 * حارس الطاقة اليومية — CapacityGuard (D02 §3.10 · §4.3 · §14⑬)
 * ───────────────────────────────────────────────────────────────────────────
 * «الفصل الواجب — الطاقة النظرية ≠ الخطة ≠ المتاح ≠ الفعلي ≠ المستحق؛ وتشغيلُ
 *  المعدة 20 ساعةً يفترض مشغّلًا لكل ورديةٍ لا مشغّلًا واحدًا 20 ساعة» (§3.10).
 *
 * القاعدة الحاكمة — لا يمنع الإدخال ويمنع الاعتماد:
 *   «تجاوزُ الطاقة لا يُمنع إدخالُه (فبعض التجاوز صحيحٌ كالإضافي والتسوية):
 *    يحذّر النظام ويحفظ أوليًّا ويرفع capacity_flag — ويمنع اعتمادَ الموقع (②)
 *    حتى: إدخال سببٍ، وفحص تداخل الورديات والتكرار، وتحديد وجود مشغّلٍ ثانٍ،
 *    واعتماد المسؤول».
 * فالحارس هنا ليس مانعَ كتابةٍ بل شرطُ اعتماد. ومن ثمّ فُصلت الدالتان:
 * evaluate() ترفع العلم ولا ترفض شيئًا، وassertSiteApprovable() ترفض.
 *
 * محوران مستقلّان بالتصميم — المعدة والمشغّل. الواقعة قد تتجاوز فيهما معًا،
 * ولكلٍّ علمُه وتخليصُه؛ ولا يُخلَّص أحدهما بتخليص الآخر (وإلا صار التخليص
 * ختمًا شاملًا يُفرغ الحارس من معناه).
 *
 * ⚠️ لا يفترض السلامة: الفحوص الثلاثة تبدأ NULL — «لم يُفحص بعد» — والتخليص
 * يلزمه إعلانٌ صريحٌ لكلٍّ منها. غيابُ الإجابة منعٌ لا سماح.
 *
 * ⚠️ القراءة من timesheet قراءةٌ محضة (§14⑬ «إعادة تشغيل بيانات الواقع»):
 * لا كتابةَ ولا تعديلَ ولا حذفَ في المسار الحيّ — تعايشٌ لا استبدال.
 */

namespace App\Services\Unit;

class CapacityGuard
{
    /** قيمتا §3.10 الافتراضيتان: المعدة ورديتان × 10 · المشغّل وردية = 10. */
    const DEFAULT_EQUIPMENT_HOURS = 20.0;
    const DEFAULT_OPERATOR_HOURS  = 10.0;

    /**
     * الطاقة النافذة — «إعدادٌ لا ثابت» (§3.10).
     * التمييز حسب المشروع ونوع المعدة وخطة التشغيل مؤجَّلٌ صراحةً حتى تقع حاجةٌ
     * حقيقيةٌ تفرّق بينها؛ فلا يُبنى جدولُ إعداداتٍ لحالةٍ لم تقع.
     *
     * @return array{equipment: float, operator: float}
     */
    public static function limits()
    {
        $eq = function_exists('ems_env') ? ems_env('EMS_UNIT_CAPACITY_EQUIPMENT_HOURS', null) : null;
        $op = function_exists('ems_env') ? ems_env('EMS_UNIT_CAPACITY_OPERATOR_HOURS', null) : null;

        return array(
            'equipment' => ($eq === null || $eq === '') ? self::DEFAULT_EQUIPMENT_HOURS : (float) $eq,
            'operator'  => ($op === null || $op === '') ? self::DEFAULT_OPERATOR_HOURS  : (float) $op,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // القياس
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * مجموع ساعات اليوم لمحورٍ واحد، من مسار الزمن (§3.3) ومن وقائع الساعة
     * (§3.1) معًا — وهما المسار الواحد للحقيقة بعد اكتمال المصدر.
     *
     * الوقائع التحليلية (record_basis='analytical') خارج القياس: «لا نظيرَ
     * إنتاجيًّا للتوقف … أما وحدات الإنتاج فلا تكون إلا إنجازًا» (§2.7)، وهي
     * لا تستهلك طاقةً زمنيةً مستقلةً عن الوردية التي سُجّلت داخلها.
     *
     * @param string $subject 'equipment' أو 'operator'
     * @param int    $exceptEntryId واقعةٌ تُستثنى من الجمع (لتقييم بديلٍ عنها)
     */
    public static function measuredHours(\mysqli $conn, $companyId, $date, $subject, $subjectRef, $exceptEntryId = 0)
    {
        $companyId = (int) $companyId;
        $subjectRef = (int) $subjectRef;
        $exceptEntryId = (int) $exceptEntryId;
        $col = ($subject === 'operator') ? 'operator_employee_id' : 'equipment_id';

        /* ① مسار الزمن — سجلّ الزمن القانوني (§3.3)
         * ═══════════════════════════════════════════════════════════════════
         * ◆ **يُشترط أن تكون الواقعةُ حيّةً.** كان الجمعُ على `unit_time_log`
         *   وحدَه بلا ربطٍ بـ`unit_entries` — فسطرُ زمنٍ **يتيمٌ** (حُذفت واقعتُه
         *   ولم يُحذف هو) يبقى محسوبًا في الطاقة إلى الأبد.
         * ◆ والأثرُ مقيسٌ لا مُفترَض: المعدةُ 24 يومَ 2027-02-14 لها **واقعتان
         *   بـ22 ساعةً**، وأعلن الحارسُ **198 ساعةً** من حدٍّ 20 — أي 22 حيّةً
         *   **+ 176 يتيمةً** من 16 سطرًا ميتًا. فعُلِّمت **50 واقعةً من 50**
         *   تجاوزًا وهي سليمةٌ، بدلَ الاثنتين المقصودتين.
         * ◆ وسطرٌ زمنيٌّ لواقعةٍ مرفوضةٍ أو معكوسةٍ لا يُعَدُّ طاقةً مستهلكةً —
         *   فيُستثنى بذاتِ قائمةِ الحالاتِ التي يستثنيها مسارُ الوقائعِ أدناه،
         *   فلا يتفرّق التعريفُ بين مسارَين لشيءٍ واحد.
         * ◆ وفي القاعدةِ **1,939 سطرًا يتيمًا** أُزيلت بهجرةِ 2027_03_22؛ وهذا
         *   الشرطُ يمنع عودةَ أثرِها ولو عادت الأسطرُ نفسُها.
         * ═══════════════════════════════════════════════════════════════════ */
        $sql = "SELECT COALESCE(SUM(l.hours),0) h FROM unit_time_log l
                 JOIN unit_entries e ON e.id = l.entry_id
                WHERE l.company_id=? AND l.log_date=? AND l.`{$col}`=?
                  AND e.state NOT IN ('rejected','cancelled','reversed','superseded')";
        $st = $conn->prepare($sql);
        $st->bind_param('isi', $companyId, $date, $subjectRef);
        $st->execute();
        $fromLog = (float) $st->get_result()->fetch_assoc()['h'];
        $st->close();

        // ② وقائع الساعة التي لا فتراتِ زمنٍ لها بعد (لئلا يُفلت التجاوز حين
        //    يُدخَل الإنجازُ وحده بلا توزيع وردية).
        $sql = "SELECT COALESCE(SUM(e.qty),0) h FROM unit_entries e
                 WHERE e.company_id=? AND e.entry_date=? AND e.`{$col}`=?
                   AND e.unit_type='hour' AND e.record_basis='contract'
                   AND e.state NOT IN ('rejected','cancelled','reversed','superseded')
                   AND e.id <> ?
                   AND NOT EXISTS (SELECT 1 FROM unit_time_log l
                                    WHERE l.company_id=e.company_id AND l.entry_id=e.id)";
        $st = $conn->prepare($sql);
        $st->bind_param('isii', $companyId, $date, $subjectRef, $exceptEntryId);
        $st->execute();
        $fromEntries = (float) $st->get_result()->fetch_assoc()['h'];
        $st->close();

        return round($fromLog + $fromEntries, 2);
    }

    /**
     * فحصا التداخل والتكرار (§3.10) على فترات اليوم لمعدةٍ واحدة.
     * يُجريان آليًّا ويُعرَضان على المخلِّص — فالفحص إلزامٌ، والحكمُ عليه بشر.
     *
     * @return array{overlap: bool, duplicate: bool}
     */
    public static function inspectSpans(\mysqli $conn, $companyId, $date, $equipmentId)
    {
        $companyId = (int) $companyId;
        $equipmentId = (int) $equipmentId;

        $st = $conn->prepare(
            "SELECT id, shift, time_from, time_to, hours, ops_state
               FROM unit_time_log
              WHERE company_id=? AND log_date=? AND equipment_id=?
              ORDER BY time_from IS NULL, time_from"
        );
        $st->bind_param('isi', $companyId, $date, $equipmentId);
        $st->execute();
        $spans = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        $overlap = false;
        $duplicate = false;
        $seen = array();

        foreach ($spans as $i => $a) {
            // تكرارٌ مشتبهٌ به: وردية+حالة+مدّةٌ متطابقة — نمطُ الوردية الليلية
            // المكرّرة الذي وُصف في §3.10 بوصفه حالةَ واقعٍ مكتشفة.
            $key = $a['shift'] . '|' . $a['ops_state'] . '|' . $a['hours'] . '|' . $a['time_from'] . '|' . $a['time_to'];
            if (isset($seen[$key])) { $duplicate = true; }
            $seen[$key] = true;

            if ($a['time_from'] === null || $a['time_to'] === null) { continue; }
            foreach (array_slice($spans, $i + 1) as $b) {
                if ($b['time_from'] === null || $b['time_to'] === null) { continue; }
                // تقاطعٌ زمنيٌّ صريح (الفترات داخل اليوم الواحد؛ العابرُ للمنتصف
                // يُسجَّل فترتين بيومَيه — قاعدةُ الإدخال في §3.3).
                if ($a['time_from'] < $b['time_to'] && $b['time_from'] < $a['time_to']) {
                    $overlap = true;
                }
            }
        }

        return array('overlap' => $overlap, 'duplicate' => $duplicate);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // الرفع — يحذّر ويحفظ ولا يمنع
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * تقييمُ واقعةٍ محفوظة: يقيس المحورين، ويرفع capacity_flag وأعلامَه عند
     * التجاوز. **لا يرفض شيئًا** — «تجاوزُ الطاقة لا يُمنع إدخالُه» (§3.10).
     *
     * @return array قائمة المحاور المتجاوزة بتفاصيلها (فارغةٌ = لا تجاوز)
     */
    public static function evaluate(\mysqli $conn, $gate, array $entry)
    {
        $entryId = (int) $entry['id'];
        $companyId = (int) $entry['company_id'];
        $date = $entry['entry_date'];
        $limits = self::limits();
        $breaches = array();

        $axes = array(
            'equipment' => isset($entry['equipment_id']) ? (int) $entry['equipment_id'] : 0,
            'operator'  => isset($entry['operator_employee_id']) ? (int) $entry['operator_employee_id'] : 0,
        );

        foreach ($axes as $subject => $ref) {
            if ($ref <= 0) { continue; }
            $measured = self::measuredHours($conn, $companyId, $date, $subject, $ref);
            if ($measured <= $limits[$subject]) { continue; }

            $inspect = ($subject === 'equipment')
                ? self::inspectSpans($conn, $companyId, $date, $ref)
                : array('overlap' => null, 'duplicate' => null);

            // العلمُ نتيجةُ قياسٍ لا رأي: يُحدَّث بالقياس الجاري ويبقى تخليصُه
            // كما هو إن كان قد خُلّص — فتعديلُ الكمية لا يمحو اعتمادَ المسؤول.
            $conn->query(
                "INSERT INTO unit_capacity_flags
                    (company_id, entry_id, subject, subject_ref, flag_date,
                     measured_hours, capacity_hours, overlap_found, duplicate_found)
                 VALUES ({$companyId}, {$entryId}, '{$subject}', {$ref}, '"
                     . $conn->real_escape_string($date) . "',
                     {$measured}, {$limits[$subject]}, "
                     . ($inspect['overlap'] === null ? 'NULL' : (int) $inspect['overlap']) . ", "
                     . ($inspect['duplicate'] === null ? 'NULL' : (int) $inspect['duplicate']) . ")
                 ON DUPLICATE KEY UPDATE
                     measured_hours = VALUES(measured_hours),
                     capacity_hours = VALUES(capacity_hours),
                     overlap_found  = VALUES(overlap_found),
                     duplicate_found = VALUES(duplicate_found)"
            );

            $breaches[] = array(
                'subject'   => $subject,
                'ref'       => $ref,
                'measured'  => $measured,
                'capacity'  => $limits[$subject],
                'overlap'   => $inspect['overlap'],
                'duplicate' => $inspect['duplicate'],
            );
        }

        // العلم البوليّ على الواقعة نفسها (§3.1) — يعكس وجودَ تجاوزٍ لا عددَه.
        $flag = empty($breaches) ? 0 : 1;
        $gate->update('unit_entries', array('capacity_flag' => $flag), array('id' => $entryId));

        return $breaches;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // المنع — شرطُ اعتماد الموقع (②)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * «سطرٌ رُفع عليه capacity_flag لا يعتمده الموقعُ (②) قبل: إدخال السبب،
     *  وفحص تداخل الورديات والتكرار، وتحديد وجود مشغّلٍ ثانٍ، واعتماد
     *  المسؤول» (§4.3).
     *
     * @return array{ok: bool, reasons: string[]}
     */
    public static function assertSiteApprovable(\mysqli $conn, $companyId, $entryId)
    {
        $companyId = (int) $companyId;
        $entryId = (int) $entryId;
        $reasons = array();

        $st = $conn->prepare(
            "SELECT subject, subject_ref, measured_hours, capacity_hours,
                    overlap_found, duplicate_found, second_operator_present,
                    cause_note, cleared_by, cleared_at
               FROM unit_capacity_flags WHERE company_id=? AND entry_id=?"
        );
        $st->bind_param('ii', $companyId, $entryId);
        $st->execute();
        $flags = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        foreach ($flags as $f) {
            $label = ($f['subject'] === 'equipment' ? 'المعدة' : 'المشغل') . ' #' . $f['subject_ref'];
            $head = "تجاوز طاقة {$label}: {$f['measured_hours']} من {$f['capacity_hours']} ساعة";

            if ($f['cleared_at'] !== null && $f['cleared_by'] !== null) { continue; }

            $missing = array();
            if ($f['cause_note'] === null || trim($f['cause_note']) === '') {
                $missing[] = 'السبب غير مدخل';
            }
            if ($f['overlap_found'] === null || $f['duplicate_found'] === null) {
                $missing[] = 'فحص تداخل الورديات والتكرار لم يجر';
            }
            if ($f['second_operator_present'] === null) {
                $missing[] = 'وجود مشغل ثان لم يحدد';
            }
            $missing[] = 'اعتماد المسؤول لم يقع';

            $reasons[] = $head . ' — ' . implode(' · ', $missing);
        }

        return array('ok' => empty($reasons), 'reasons' => $reasons);
    }

    /**
     * تخليصُ علمٍ واحد — بالإعلانات الصريحة الأربعة التي يوجبها §3.10.
     * محورٌ واحدٌ لكل نداء: لا يُخلَّص المشغّل بتخليص المعدة.
     *
     * @return bool نجح التخليص (false = العلم غير موجود، أو إعلانٌ ناقص)
     */
    public static function clear(\mysqli $conn, $gate, $companyId, $entryId, $subject,
                                 $causeNote, $secondOperatorPresent, $actorId)
    {
        $causeNote = trim((string) $causeNote);
        if ($causeNote === '') { return false; }              // السبب إلزام
        if ($secondOperatorPresent === null) { return false; } // الإعلان إلزام
        if ((int) $actorId <= 0) { return false; }             // المسؤول إلزام

        $st = $conn->prepare(
            "SELECT id, overlap_found, duplicate_found FROM unit_capacity_flags
              WHERE company_id=? AND entry_id=? AND subject=?"
        );
        $st->bind_param('iis', $companyId, $entryId, $subject);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) { return false; }

        // الفحصان لم يُجريا آليًّا (محور المشغّل) — يُجريان الآن على معدة الواقعة
        // قبل التخليص، فلا يُخلَّص علمٌ وفحصُه معلَّق.
        if ($row['overlap_found'] === null || $row['duplicate_found'] === null) {
            $st = $conn->prepare("SELECT company_id, entry_date, equipment_id FROM unit_entries WHERE id=?");
            $st->bind_param('i', $entryId);
            $st->execute();
            $e = $st->get_result()->fetch_assoc();
            $st->close();
            $ins = ($e && (int) $e['equipment_id'] > 0)
                ? self::inspectSpans($conn, $e['company_id'], $e['entry_date'], $e['equipment_id'])
                : array('overlap' => false, 'duplicate' => false);
            $gate->update('unit_capacity_flags', array(
                'overlap_found'   => (int) $ins['overlap'],
                'duplicate_found' => (int) $ins['duplicate'],
            ), array('id' => (int) $row['id']));
        }

        $gate->update('unit_capacity_flags', array(
            'cause_note'              => $causeNote,
            'second_operator_present' => (int) (bool) $secondOperatorPresent,
            'cleared_by'              => (int) $actorId,
            'cleared_at'              => date('Y-m-d H:i:s'),
        ), array('id' => (int) $row['id']));

        return true;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // §14⑬ — إعادة تشغيل بيانات الواقع (قراءةٌ محضة)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * «إعادة تشغيل بيانات الواقع: حالاتُ 30 ساعةً للآلة و22 تجاوزًا للموظفين
     *  يجب أن يلتقطها الحارس (3.10) كلَّها» — فحصُ قبولٍ ملزم (§14⑬).
     *
     * يقيس على المسار الحيّ timesheet **قراءةً فقط**: لا كتابةَ ولا تعديل.
     * غايتُه إثباتُ أن معيار الحارس نفسَه يلتقط التجاوز القائم اليوم — لا
     * هجرةُ صفٍّ ولا رفعُ علمٍ على المسار القديم.
     *
     * @return array{equipment: array[], operator: array[]}
     */
    public static function scanLegacyTimesheet(\mysqli $conn, $companyId)
    {
        $companyId = (int) $companyId;
        $limits = self::limits();

        $st = $conn->prepare(
            "SELECT operator AS subject_ref, `date` AS d, COUNT(*) rows_n,
                    ROUND(SUM(total_work_hours),2) measured
               FROM timesheet
              WHERE company_id=?
              GROUP BY operator, `date`
             HAVING measured > ?
              ORDER BY measured DESC"
        );
        $st->bind_param('id', $companyId, $limits['equipment']);
        $st->execute();
        $equipment = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        $st = $conn->prepare(
            "SELECT employee_id AS subject_ref, `date` AS d, COUNT(*) rows_n,
                    ROUND(SUM(total_work_hours),2) measured
               FROM timesheet
              WHERE company_id=? AND employee_id IS NOT NULL AND employee_id <> ''
              GROUP BY employee_id, `date`
             HAVING measured > ?
              ORDER BY measured DESC"
        );
        $st->bind_param('id', $companyId, $limits['operator']);
        $st->execute();
        $operator = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        return array('equipment' => $equipment, 'operator' => $operator);
    }
}
