<?php
/**
 * الورديات والحضور وسياسات الخصم — WRK-01 النواة (§8 المواصفة التنفيذية)
 * ───────────────────────────────────────────────────────────────────────────
 * أربع خدمات في ملف واحد (المسار واحد):
 *   ① ShiftPeriod: فترة بلا مشغّل 422 · أزمنة تتجاوز مدة الفترة 422 · توقف بلا
 *     سبب 422 · تكرار المفتاح 409 بمرجعه · الليلية تُنسب ليوم بدئها (بالمفتاح).
 *   ② PolicyResolver: السياسة بمحددات §1 — وبلا مطابقة 422 لا افتراض صامتًا.
 *   ③ Impact: الآثار الخمسة لكل يوم من القاموس الموحَّد — وST تقرأ الإسناد
 *     من مصفوفة الالتزامات (كما N-12) لتحديد الفوترة واستحقاق المورد.
 *   ④ Deduction: يُنشئ Proposed حصرًا · حد ثلث الصافي على الاختيارية (DEC ②) ·
 *     ولا Posted إلا بمرجع سلّم GOV-01 (CHECK بنيوي) · الإعفاء قرار مستقل.
 *   + sweep التصنيف الآلي: ما لم يُصنَّف خلال 48 ساعة → A2 بعد إشعار (DEC ①).
 */

namespace App\Services\Workforce;

class AttendanceService
{
    // ═════════════════ ① الفترات ═════════════════

    /** @return array{ok:bool,code:int,reason:string,log_id:int,periods_logged:int} */
    public static function logPeriod($gate, $companyId, array $a, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'log_id' => 0, 'periods_logged' => 0);
        foreach (array('work_date', 'equipment_id', 'shift_no', 'period_no') as $f) {
            if (!isset($a[$f]) || $a[$f] === '') { $out['code'] = 422; $out['reason'] = 'حقل إلزامي: ' . $f; return $out; }
        }
        if (empty($a['operator_person_id'])) {
            $out['code'] = 422; $out['reason'] = 'فترة بلا مشغّل تُرفض — مشغّل واحد لكل فترة إلزامًا';
            return $out;
        }
        $run = (int) (isset($a['run_minutes']) ? $a['run_minutes'] : 0);
        $standby = (int) (isset($a['standby_minutes']) ? $a['standby_minutes'] : 0);
        $stop = (int) (isset($a['stop_minutes']) ? $a['stop_minutes'] : 0);
        if (isset($a['period_minutes']) && ($run + $standby + $stop) > (int) $a['period_minutes']) {
            $out['code'] = 422; $out['reason'] = 'مجموع الأزمنة (' . ($run + $standby + $stop) . ') يتجاوز مدة الفترة (' . $a['period_minutes'] . ')';
            return $out;
        }
        if ($stop > 0 && empty($a['stop_reason_code'])) {
            $out['code'] = 422; $out['reason'] = 'توقف بلا سبب يُرفض — السبب من القائمة المحكومة (N-12)';
            return $out;
        }
        // DEC-01 ⑨: مزامنة تجاوز تأخيرها يومًا من تاريخ العمل تُعلَّم «مزامَن
        // متأخر» — تدخل السلسلة كأي صف ولا تُعتمد آليًّا، والوسم للمراجع.
        $syncedLate = (!empty($a['_sync']) && (time() - strtotime((string) $a['work_date'] . ' 23:59:59')) > 86400) ? 1 : 0;
        try {
            $out['log_id'] = (int) $gate->insert('shift_period_logs', array(
                'work_date' => (string) $a['work_date'],
                'equipment_id' => (int) $a['equipment_id'],
                'shift_no' => (int) $a['shift_no'],
                'period_no' => (int) $a['period_no'],
                'operator_person_id' => (int) $a['operator_person_id'],
                'qty' => isset($a['qty']) ? (float) $a['qty'] : 0,
                'unit' => isset($a['unit']) ? (string) $a['unit'] : 'ton',
                'run_minutes' => $run, 'standby_minutes' => $standby, 'stop_minutes' => $stop,
                'stop_reason_code' => !empty($a['stop_reason_code']) ? (string) $a['stop_reason_code'] : null,
                'site_id' => isset($a['site_id']) ? (int) $a['site_id'] : null,
                'synced_late' => $syncedLate,
                'created_by' => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                $ex = $gate->scopedQuery(array('scope' => array('l' => 'shift_period_logs')),
                    "SELECT l.log_id FROM shift_period_logs l WHERE {TENANT_SCOPE} AND l.work_date = ? AND l.equipment_id = ? AND l.shift_no = ? AND l.period_no = ?",
                    array((string) $a['work_date'], (int) $a['equipment_id'], (int) $a['shift_no'], (int) $a['period_no']));
                $out['code'] = 409;
                $out['reason'] = 'مفتاح مكرر (معدة×تاريخ×وردية×فترة) — السجل القائم #' . ($ex ? $ex[0]['log_id'] : '?');
                return $out;
            }
            throw $t;
        }
        $n = $gate->scopedQuery(array('scope' => array('l' => 'shift_period_logs')),
            "SELECT COUNT(*) c FROM shift_period_logs l WHERE {TENANT_SCOPE} AND l.work_date = ? AND l.equipment_id = ? AND l.shift_no = ?",
            array((string) $a['work_date'], (int) $a['equipment_id'], (int) $a['shift_no']));
        $out['periods_logged'] = (int) $n[0]['c'];
        $out['synced_late'] = $syncedLate;
        $out['ok'] = true; $out['code'] = 201;
        $out['reason'] = 'سُجّلت الفترة ' . $a['period_no'] . ' بمشغّلها #' . $a['operator_person_id']
            . ($syncedLate ? ' — معلَّمةً «مزامَن متأخر» (تجاوز يومًا · DEC-01 ⑨)' : '');
        return $out;
    }

    // ═════════════════ ② السياسة ═════════════════

    /** بلا سياسة مطابقة → 422 ولا يُفترض. الأولوية: العقد ← المشروع ← النوع. */
    public static function resolvePolicy($gate, $companyId, array $ctx, $onDate = null)
    {
        $onDate = ($onDate !== null) ? (string) $onDate : date('Y-m-d');
        $rows = $gate->scopedQuery(array('scope' => array('p' => 'attendance_policies')),
            "SELECT p.* FROM attendance_policies p WHERE {TENANT_SCOPE} AND p.active = 1
                AND p.valid_from <= ? AND (p.valid_to IS NULL OR p.valid_to >= ?)",
            array($onDate, $onDate));
        $scope = isset($ctx['employee_scope']) ? (string) $ctx['employee_scope'] : '';
        foreach ($rows as $p) {
            $ap = json_decode((string) $p['applies_to_json'], true);
            if (is_array($ap) && isset($ap['employee_scope']) && $ap['employee_scope'] === $scope) {
                return array('ok' => true, 'code' => 200, 'policy' => $p);
            }
        }
        return array('ok' => false, 'code' => 422, 'policy' => null,
            'reason' => 'لا سياسةَ حضورٍ مطابقةً لمحددات (' . $scope . ') — ولا يُفترض شيء');
    }

    // ═════════════════ ③ الأثر الخماسي ═════════════════

    /**
     * الآثار الخمسة ليوم حضور — من القاموس الموحَّد؛ وST بالإسناد (بندها المقابل
     * من مصفوفة الالتزامات كما في N-12).
     * @return array{ok:bool,code:int,pay:string,incentive:bool,presence:string,billable:bool,supplier_due:bool,conduct_violation:bool,reason:string}
     */
    public static function impactOf($gate, $companyId, $statusCode, array $ctx = array())
    {
        $rows = $gate->scopedQuery(array('scope' => array('t' => 'payroll_absence_types')),
            "SELECT t.* FROM payroll_absence_types t WHERE {TENANT_SCOPE} AND t.code = ? AND t.active = 1 LIMIT 1",
            array((string) $statusCode));
        if (!$rows) {
            return array('ok' => false, 'code' => 422,
                'reason' => 'حالة حضور خارج الرموز المعتمدة: «' . $statusCode . '» — لا تُحتسب حالة ليست في القاموس');
        }
        $t = $rows[0];
        $billable = ((string) $t['billable'] === 'yes');
        $supplierDue = ((string) $t['supplier_due'] === 'yes');
        $attributionNote = '';
        if ((string) $t['billable'] === 'by_attribution' || (string) $t['supplier_due'] === 'by_attribution') {
            // ST: الحالة واحدة والأثر يتبع الإسناد لا العكس (WRK-01 §4)
            $bearer = isset($ctx['bearer_party']) ? (string) $ctx['bearer_party'] : '';
            if ($bearer === '') {
                return array('ok' => false, 'code' => 422,
                    'reason' => 'حالة ST تقرأ الإسناد — مرّر bearer_party من بند الالتزام (N-12) ولا يُفترض');
            }
            $billable = ($bearer === 'client');
            $supplierDue = ($bearer === 'client');
            $attributionNote = ' · الإسناد: ' . $bearer . ($billable ? ' → استعداد مفوتر يستحقه الطرفان' : ' → غير مفوتر ولا يستحقه المورد');
        }
        // DEC-01 ④: A1 لا يُخصم من الراتب — يُخصم من رصيد الإجازة إن وُجد،
        // **فإن نفد الرصيد عومل معاملة «بلا أجر» لتلك الأيام** (عدم استحقاق لا عقوبة)
        $pay = (string) $t['pay_effect'];
        $balanceNote = '';
        if ((string) $statusCode === 'A1' && array_key_exists('leave_balance_days', $ctx)
            && (float) $ctx['leave_balance_days'] <= 0) {
            $pay = 'stops_accrual';
            $balanceNote = ' · نفد رصيد الإجازة → يُعامل «بلا أجر» لهذا اليوم (DEC-01 ④) — لا خصم عقوبة';
        }
        return array(
            'ok' => true, 'code' => 200,
            'pay' => $pay,
            'incentive' => intval($t['incentive_base']) === 1,
            'presence' => (string) $t['presence'],
            'billable' => $billable,
            'supplier_due' => $supplierDue,
            'conduct_violation' => intval($t['conduct_violation']) === 1,
            'reason' => $t['label_ar'] . $attributionNote . $balanceNote,
        );
    }

    /** تصنيف يوم — الرمز من القاموس حصرًا (422 لغيره) وUQ (شخص×يوم). */
    public static function classify($gate, $companyId, $personId, $date, $statusCode, array $opt, $actor)
    {
        $imp = self::impactOf($gate, $companyId, $statusCode, array('bearer_party' => isset($opt['bearer_party']) ? $opt['bearer_party'] : 'company'));
        if (!$imp['ok']) { return $imp; }
        try {
            $id = (int) $gate->insert('attendance_days', array(
                'person_id' => (int) $personId, 'att_date' => (string) $date,
                'status_code' => (string) $statusCode,
                'policy_id' => isset($opt['policy_id']) ? (int) $opt['policy_id'] : null,
                'reference_doc' => isset($opt['reference_doc']) ? (string) $opt['reference_doc'] : null,
                'stop_reason_code' => isset($opt['stop_reason_code']) ? (string) $opt['stop_reason_code'] : null,
                'classified_by' => (int) $actor ?: null, 'classified_at' => date('Y-m-d H:i:s'),
            ));
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                return array('ok' => false, 'code' => 409, 'reason' => 'اليوم مصنَّف سلفًا — التغيير بمسار تغيير الحالة (GOV-01 §6) لا بالكتابة فوقه');
            }
            throw $t;
        }
        return array('ok' => true, 'code' => 201, 'att_id' => $id, 'reason' => 'صُنّف ' . $date . ' برمز ' . $statusCode);
    }

    /**
     * التصنيف الآلي (DEC-01 ④ الموقَّع): ما لم يُصنَّف خلال 48 ساعة → **إشعار
     * للمسؤول ومهلة 24 ساعة إضافية** ثم يصير A2 آليًّا — لا A2 بصمت ولا بلا مهلة.
     * المرحلتان: ≥48h → سطر إشعار (attendance_sweep_notices · مرة واحدة)؛
     * ≥24h بعد الإشعار → A2 بوسم auto_reclassified.
     * @return array{notified:int,classified:int}
     */
    public static function sweepUnclassified(\mysqli $conn, $gate, $companyId, array $pendingDays, $actor)
    {
        $notified = 0; $classified = 0;
        $companyIdInt = intval($companyId);
        foreach ($pendingDays as $d) {
            $personId = (int) $d['person_id']; $date = (string) $d['att_date'];
            if ((time() - strtotime($date . ' 23:59:59')) < 48 * 3600) { continue; }

            // المهلة تُقاس بساعة قاعدة البيانات وحدها — لا خلط ساعتين (PHP/MySQL)
            $stmt = $conn->prepare('SELECT notice_id, TIMESTAMPDIFF(SECOND, notified_at, NOW()) elapsed FROM attendance_sweep_notices WHERE company_id = ? AND person_id = ? AND att_date = ? LIMIT 1');
            $stmt->bind_param('iis', $companyIdInt, $personId, $date);
            $stmt->execute();
            $notice = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$notice) {
                // المرحلة ①: إشعار + بدء مهلة الـ24 ساعة — ولا تصنيف بعدُ
                $stmt = $conn->prepare('INSERT IGNORE INTO attendance_sweep_notices (company_id, person_id, att_date) VALUES (?, ?, ?)');
                $stmt->bind_param('iis', $companyIdInt, $personId, $date);
                $stmt->execute();
                $stmt->close();
                $conn->query("INSERT INTO fin_notifications (company_id, target_level, title, link)
                              VALUES ({$companyIdInt}, 'dept_manager',
                              'غياب الموظف #{$personId} يوم {$date} لم يُصنَّف خلال 48 ساعة — مهلة 24 ساعة ثم يصير غيابًا غير مبرَّر (A2)', 'Employees/attendance.php')");
                $notified++;
                continue;
            }
            if (intval($notice['elapsed']) < 24 * 3600) { continue; }

            // المرحلة ②: انقضت المهلة الإضافية — A2 آليًّا بوسمه
            $r = self::classify($gate, $companyId, $personId, $date, 'A2', array(), $actor);
            if ($r['ok']) {
                $gate->update('attendance_days', array('auto_reclassified' => 1), array('att_id' => (int) $r['att_id']));
                $classified++;
            }
        }
        return array('notified' => $notified, 'classified' => $classified);
    }

    /**
     * DEC-01 ④: الحالة الطارئة `EM` تبقى معلَّقة حتى قرار الموارد البشرية
     * **خلال خمسة أيام عمل** (الجمعة والسبت لا يُعدّان) — فإن لم يصدر قرار
     * صارت **A1 احتياطًا لا A2** (الأصل براءة الذمة في الطارئ).
     */
    public static function sweepEmergencyPending(\mysqli $conn, $gate, $companyId)
    {
        $companyIdInt = intval($companyId);
        $rows = $conn->query(
            "SELECT att_id, person_id, att_date, reference_doc FROM attendance_days
              WHERE company_id = {$companyIdInt} AND status_code = 'EM' AND COALESCE(auto_reclassified,0) = 0"
        )->fetch_all(MYSQLI_ASSOC);
        $done = 0;
        foreach ($rows as $r) {
            // خمسة أيام عمل من اليوم التالي لليوم الطارئ
            $deadline = strtotime((string) $r['att_date']);
            $working = 0;
            while ($working < 5) {
                $deadline = strtotime('+1 day', $deadline);
                $dow = (int) date('w', $deadline); // 5=جمعة · 6=سبت
                if ($dow !== 5 && $dow !== 6) { $working++; }
            }
            if (time() <= $deadline + 86399) { continue; }
            $attId = (int) $r['att_id'];
            $ref = trim((string) $r['reference_doc'] . ' · EM-timeout→A1 (DEC-01 ④: لا قرار خلال 5 أيام عمل — مبرَّر احتياطًا)', ' ·');
            $gate->update('attendance_days', array(
                'status_code' => 'A1', 'auto_reclassified' => 1,
                'reference_doc' => mb_substr($ref, 0, 120),
            ), array('att_id' => $attId));
            $conn->query("INSERT INTO fin_notifications (company_id, target_level, title, link)
                          VALUES ({$companyIdInt}, 'dept_manager',
                          'حالة طارئة للموظف #" . (int) $r['person_id'] . " يوم " . $r['att_date'] . " بلا قرار خلال 5 أيام عمل — صُنّفت A1 احتياطًا (DEC-01 ④)', 'Employees/attendance.php')");
            $done++;
        }
        return $done;
    }

    // ═════════════════ ④ الخصم ═════════════════

    /** يُنشئ الخصم Proposed حصرًا — بمصدره (لا خصم بلا مستند M-11). */
    public static function proposeDeduction($gate, $companyId, array $a)
    {
        foreach (array('person_id', 'period', 'source', 'source_ref', 'proposed_amount') as $f) {
            if (!isset($a[$f]) || $a[$f] === '') {
                return array('ok' => false, 'code' => 422, 'reason' => 'حقل إلزامي: ' . $f . ' — لا خصم بلا مصدر');
            }
        }
        try {
            $id = (int) $gate->insert('deduction_proposals', array(
                'person_id' => (int) $a['person_id'], 'period' => (string) $a['period'],
                'source' => (string) $a['source'], 'source_ref' => (string) $a['source_ref'],
                'proposed_amount' => round((float) $a['proposed_amount'], 2),
                'is_voluntary' => !empty($a['is_voluntary']) ? 1 : 0,
            ));
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                return array('ok' => true, 'code' => 200, 'ded_id' => 0, 'reason' => 'مقترح قائم لهذا المصدر — عاطل');
            }
            throw $t;
        }
        return array('ok' => true, 'code' => 201, 'ded_id' => $id, 'state' => 'Proposed',
            'reason' => 'خصم مقترح — ولا ترحيل قبل سلّم GOV-01');
    }

    /**
     * الترحيل: Approved بمرجع سلّمه حصرًا + **حدا حماية الصافي (DEC-01 ⑤ الموقَّع)**:
     *   الحد الأول — الاختيارية (سلف · مدفوعات نيابة · عهد) ≤ **ثلث** الصافي.
     *   الحد الثاني — **مجموع كل الخصومات ≤ نصف** الصافي؛ وما زاد يُعاد جدولة
     *   الاختياري منه ويُمدَّد الاسترداد (الجزاءات والغياب خارج الأول داخل الثاني —
     *   عدم استحقاق لا سلفة، فتُرحَّل كاملة).
     *   التجاوز: **بقرار الإدارة العامة وحدها وبطلب مكتوب من العامل نفسه** —
     *   $override = ['gm_ref' => ..., 'worker_request_ref' => ...] كلاهما إلزامي.
     *   وإعادة الجدولة **بإشعار العامل** بجدوله الجديد — لا تخفيض بلا علمه.
     * @return array{ok:bool,code:int,reason:string,posted_amount:float}
     */
    public static function postDeduction($gate, $companyId, $dedId, $approvalsRef, $runId, $netSalary, $override = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'posted_amount' => 0.0);
        $dedId = (int) $dedId;
        $rows = $gate->scopedQuery(array('scope' => array('d' => 'deduction_proposals')),
            "SELECT d.* FROM deduction_proposals d WHERE {TENANT_SCOPE} AND d.ded_id = ?", array($dedId));
        if (!$rows) { $out['code'] = 404; $out['reason'] = 'المقترح غير موجود'; return $out; }
        $d = $rows[0];
        if ((string) $d['state'] === 'Posted') { $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'مرحَّل سلفًا — عاطل'; return $out; }
        if (!in_array((string) $d['state'], array('Approved',), true)) {
            $out['code'] = 403;
            $out['reason'] = 'لا ترحيلَ إلا من Approved — الحال «' . $d['state'] . '»؛ والاعتماد بسلّم GOV-01 (ثلاث موافقات)';
            return $out;
        }
        if ($approvalsRef === null || $approvalsRef === '') {
            $out['code'] = 403; $out['reason'] = 'مرجع سلّم الموافقات إلزامي للترحيل (CHECK بنيوي)';
            return $out;
        }

        // تجاوز الحدين: قرار الإدارة العامة + طلب مكتوب من العامل — كلاهما لا أحدهما
        $bypass = false;
        if ($override !== null) {
            if (empty($override['gm_ref']) || empty($override['worker_request_ref'])) {
                $out['code'] = 422;
                $out['reason'] = 'تجاوز حدي الحماية بقرار الإدارة العامة **وبطلب مكتوب من العامل نفسه** — كلاهما إلزامي ولا يُفرض عليه (DEC-01 ⑤)';
                return $out;
            }
            $bypass = true;
        }

        $amount = (float) $d['proposed_amount'];
        $rescheduled = false;
        if (!$bypass && $netSalary !== null) {
            $capVol = round((float) $netSalary / 3, 2);   // الحد الأول — الاختيارية
            $capAll = round((float) $netSalary / 2, 2);   // الحد الثاني — الكل
            $sums = $gate->scopedQuery(array('scope' => array('x' => 'deduction_proposals')),
                "SELECT COALESCE(SUM(CASE WHEN x.is_voluntary = 1 THEN x.proposed_amount ELSE 0 END),0) sv,
                        COALESCE(SUM(x.proposed_amount),0) st
                   FROM deduction_proposals x
                  WHERE {TENANT_SCOPE} AND x.person_id = ? AND x.period = ? AND x.state = 'Posted'",
                array((int) $d['person_id'], (string) $d['period']));
            $postedVol = (float) $sums[0]['sv']; $postedAll = (float) $sums[0]['st'];
            if (intval($d['is_voluntary']) === 1) {
                $room = round(min($capVol - $postedVol, $capAll - $postedAll), 2);
                if ($room <= 0) {
                    self::notifyWorker($gate, (int) $d['person_id'],
                        'أُعيدت جدولة استقطاع اختياري (' . $amount . ') للفترة ' . $d['period'] . ' — بلغ الحد (ثلث الصافي ' . $capVol . ' أو نصفه ' . $capAll . ')، ويُمدَّد الاسترداد للفترة القادمة');
                    $out['code'] = 409;
                    $out['reason'] = 'حدا حماية الصافي: الاختيارية ' . $postedVol . '/' . $capVol . ' والمجموع ' . $postedAll . '/' . $capAll . ' — يُعاد جدولة الاختياري ويُمدَّد الاسترداد بإشعار العامل (DEC-01 ⑤)';
                    return $out;
                }
                if ($amount > $room) { $amount = $room; $rescheduled = true; }
            }
            // غير الاختياري (جزاء · غياب): عدم استحقاق أو عقوبة — يُرحَّل كاملًا،
            // خارج الحد الأول وداخل الثاني: تجاوزه يضغط الاختياري القادم لا هو.
        }
        $gate->update('deduction_proposals', array(
            'state' => 'Posted', 'approvals_ref' => (string) $approvalsRef,
            'posted_run_id' => ($runId !== null) ? (int) $runId : null,
            'proposed_amount' => $amount,
        ), array('ded_id' => $dedId));
        if ($rescheduled) {
            self::notifyWorker($gate, (int) $d['person_id'],
                'قُصّ استقطاعك الاختياري للفترة ' . $d['period'] . ' إلى ' . $amount . ' (حد حماية الصافي) — والباقي مجدولٌ للفترة القادمة');
        }
        $out['ok'] = true; $out['code'] = 200; $out['posted_amount'] = $amount;
        $out['reason'] = 'رُحّل ' . $amount . ' بمرجع سلّمه ' . $approvalsRef
            . ($bypass ? ' — بتجاوز الحدين بقرار الإدارة العامة (' . $override['gm_ref'] . ') وطلب العامل (' . $override['worker_request_ref'] . ')' : '')
            . ($rescheduled ? ' — والباقي أعيدت جدولته بإشعار العامل' : '');
        return $out;
    }

    /** إشعار العامل بجدوله الجديد — لا تخفيض بلا علمه (DEC-01 ⑤). فشله لا يوقف الترحيل. */
    private static function notifyWorker($gate, $personId, $title)
    {
        try {
            $gate->insert('fin_notifications', array(
                'target_level' => 'employee:' . (int) $personId,
                'title' => mb_substr($title, 0, 250),
                'link' => 'Employees/employee_settlements.php',
            ));
        } catch (\Throwable $t) {
            error_log('[AttendanceService] worker notify failed: ' . $t->getMessage());
        }
    }
}
