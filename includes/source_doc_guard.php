<?php
/**
 * includes/source_doc_guard.php — لا كتابةَ ماليةً بلا مستندِ مصدرٍ أو استثناءٍ نافذ
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0177 · INJ-0178 · INJ-0179 · INJ-0183 · INJ-0249
 *
 * ثلاثةُ بنودٍ ماليةٍ تقول الجملةَ نفسَها بألفاظٍ مختلفة:
 *   · «حفظُ قيدٍ **بلا حدثٍ مرتبطٍ** وبلا مرجعِ استثناءٍ معتمدٍ يُرفض 422»
 *   · «حفظُ حركةِ صرفٍ **بلا مستندِ التزامٍ أو ذمّةٍ معتمدةٍ** يُرفض 422»
 *   · «إنجازُ بندِ إقفالٍ **بلا مرجعِ دليلٍ** يُرفض 422»
 *
 * فالحكمُ واحد: **الرقمُ المالي أثرُ واقعةٍ لا مصدرُها** (ADR-15). ومن كتب رقمًا
 * بلا مستندٍ فقد أنشأ حقيقةً من العدم — ولا سبيلَ بعدها إلى مراجعتها.
 *
 * ── ولماذا حارسٌ واحدٌ لا ثلاثة ────────────────────────────────────────────
 * لأنَّ الفرقَ بين البنودِ **في المستندِ المقبولِ لا في القاعدة**. فالقاعدةُ هنا
 * مرةً واحدةً، والمقبولُ يُعلَن في `ems_source_doc_registry()` — فبندٌ رابعٌ
 * يُضاف بسطرٍ لا بحارسٍ جديدٍ يُنسى تبنّيه.
 *
 * ── ومسلكُ الاستثناءِ ليس ثغرةً بل هو البند ─────────────────────────────────
 * INJ-0249: «استثناءٌ نافذٌ بمدةٍ يفتح الشاشةَ الممنوعة **فورًا**، وينقلب المنعُ
 * عند انتهاء المدة، **ويظهر عدّادُ الاستعمال**». فالاستثناءُ يمرُّ من هنا
 * ويُحسب: `exception_requests.usage_count` يزيد وصفٌّ يُكتب في `exception_usages`.
 * فمن استعمل استثناءً مرةً واحدةً يُقرأ في الشاشةِ أنه استعمله مرةً واحدة.
 *
 * ◆ **والمدةُ تُقاس لا تُعلَن**: `valid_from <= الآن <= valid_to` — فاستثناءٌ
 *   انقضى **لا يمرُّ** ولو بقيت حالتُه `Active` في القاعدة. والحالةُ بيانٌ
 *   والمدةُ حكم.
 * ◆ **والردُّ رمزٌ وسببٌ** لا `false` مجرَّد: `FIN-SRC-422` — فالمنعُ الصامتُ
 *   يُقرأ عطلًا فيُلتَفُّ عليه.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_source_doc_registry')) {
    /**
     * المستندُ المقبولُ لكلِّ نوعِ كتابة.
     *
     *  · `any_of`  حقولٌ يكفي أن يحمل أحدُها مفتاحًا — مع جدولِه للتحقّق.
     *  · `text_of` حقلٌ نصيٌّ يكفي أن يكون غيرَ فارغٍ (مرجعُ دليل).
     *  · `guard`   رمزُ الحارسِ الذي يُطابَق به الاستثناءُ في `exception_requests`.
     */
    function ems_source_doc_registry()
    {
        return array(
            'journal_entry' => array(
                'label'  => 'قيدٌ يدويّ',
                'guard'  => 'FIN-SRC-JV',
                'any_of' => array(
                    'event_id' => array('table' => 'fin_financial_events', 'pk' => 'id',
                                        'label' => 'حدثٌ ماليٌّ مرتبط'),
                ),
            ),
            'payment' => array(
                'label'  => 'حركةُ صرفٍ أو تحصيل',
                'guard'  => 'FIN-SRC-PAY',
                'any_of' => array(
                    'event_id'      => array('table' => 'fin_financial_events', 'pk' => 'id',
                                             'label' => 'حدثٌ ماليّ'),
                    'due_id'        => array('table' => 'fin_dues', 'pk' => 'id',
                                             'label' => 'مستندُ التزام'),
                    'receivable_id' => array('table' => 'fin_receivables', 'pk' => 'id',
                                             'label' => 'ذمّةٌ معتمدة'),
                ),
            ),
            'closing_item' => array(
                'label'   => 'بندُ إقفالٍ منجَز',
                'guard'   => 'FIN-SRC-CLOSE',
                'text_of' => array('note' => 'مرجعُ الدليل'),
            ),
            'cost_record' => array(
                'label'  => 'سجلُّ تكلفة',
                'guard'  => 'FIN-SRC-COST',
                'any_of' => array(
                    'event_id' => array('table' => 'fin_financial_events', 'pk' => 'id',
                                        'label' => 'حدثٌ مصدر'),
                ),
            ),
        );
    }
}

if (!function_exists('ems_source_doc_exception')) {
    /**
     * استثناءٌ **نافذٌ الآن** لهذا الحارسِ في هذه الشركة — أو null.
     * ◆ المدةُ تُقاس بساعةِ القاعدةِ لا بساعةِ PHP: تفاوتُهما يُمرِّر منقضيًا.
     */
    function ems_source_doc_exception(mysqli $conn, $companyId, $guardCode)
    {
        $sql = "SELECT req_id, usage_count, valid_to
                  FROM exception_requests
                 WHERE company_id = ? AND guard_code = ?
                   AND state IN ('Approved','Active')
                   AND (valid_from IS NULL OR valid_from <= NOW())
                   AND (valid_to   IS NULL OR valid_to   >= NOW())
                 ORDER BY req_id DESC LIMIT 1";
        $st = $conn->prepare($sql);
        if (!$st) { return null; }
        $cid = (int) $companyId; $gc = (string) $guardCode;
        $st->bind_param('is', $cid, $gc);
        if (!$st->execute()) { $st->close(); return null; }
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? $row : null;
    }
}

if (!function_exists('ems_source_doc_use_exception')) {
    /** يحسب استعمالَ الاستثناء — عدّادًا وصفَّ استعمال. يُرجع true إن سُجِّل. */
    function ems_source_doc_use_exception(mysqli $conn, $reqId, $personId, $operationRef)
    {
        $ok = false;
        $st = $conn->prepare('UPDATE exception_requests SET usage_count = COALESCE(usage_count,0) + 1 WHERE req_id = ?');
        if ($st) {
            $rid = (int) $reqId;
            $st->bind_param('i', $rid);
            $ok = ($st->execute() && $conn->affected_rows > 0);
            $st->close();
        }
        $st2 = $conn->prepare('INSERT INTO exception_usages (req_id, operation_ref, person_id, at)
                               VALUES (?, ?, ?, NOW())');
        if ($st2) {
            $rid2 = (int) $reqId; $op = mb_substr((string) $operationRef, 0, 120); $pid = (int) $personId;
            $st2->bind_param('isi', $rid2, $op, $pid);
            $st2->execute();
            $st2->close();
        }
        return $ok;
    }
}

if (!function_exists('ems_require_source_doc')) {
    /**
     * الحارس. يُرجع array{ok:bool,code:int,reason:string,via:string}.
     *
     * @param string $kind   مفتاحٌ في `ems_source_doc_registry()`
     * @param array  $data   الصفُّ المزمعُ كتابتُه (المفاتيحُ تُقرأ منه)
     */
    function ems_require_source_doc(mysqli $conn, $companyId, $kind, array $data, $actorId = 0, $opRef = '')
    {
        $reg = ems_source_doc_registry();
        if (!isset($reg[$kind])) {
            /* نوعٌ غيرُ مُعلَن: **يُرفض ولا يُخمَّن** — فالسكوتُ يفتح بابًا لا يُغلق */
            return array('ok' => false, 'code' => 422, 'via' => '',
                         'reason' => 'FIN-SRC-422: نوعُ كتابةٍ غيرُ مُعلَنٍ في سجلِّ المصادر — لا تمرُّ');
        }
        $spec = $reg[$kind];

        /* ① مفتاحٌ يشير إلى مستندٍ **قائمٍ لهذه الشركة** */
        if (!empty($spec['any_of'])) {
            foreach ($spec['any_of'] as $col => $src) {
                $val = isset($data[$col]) ? (int) $data[$col] : 0;
                if ($val <= 0) { continue; }
                $sql = 'SELECT 1 FROM `' . $src['table'] . '` WHERE `' . $src['pk'] . '` = ? AND company_id = ? LIMIT 1';
                $st = $conn->prepare($sql);
                if (!$st) { continue; }
                $cid = (int) $companyId;
                $st->bind_param('ii', $val, $cid);
                $st->execute();
                $hit = (bool) $st->get_result()->fetch_row();
                $st->close();
                if ($hit) {
                    return array('ok' => true, 'code' => 200, 'via' => $col,
                                 'reason' => $src['label']);
                }
            }
        }

        /* ② مرجعُ دليلٍ نصيٌّ غيرُ فارغ */
        if (!empty($spec['text_of'])) {
            foreach ($spec['text_of'] as $col => $lbl) {
                $v = isset($data[$col]) ? trim((string) $data[$col]) : '';
                if (mb_strlen($v) >= 3) {
                    return array('ok' => true, 'code' => 200, 'via' => $col, 'reason' => $lbl);
                }
            }
        }

        /* ③ استثناءٌ نافذٌ — يمرُّ **ويُحسب** */
        $exc = ems_source_doc_exception($conn, $companyId, $spec['guard']);
        if ($exc !== null) {
            ems_source_doc_use_exception($conn, (int) $exc['req_id'], (int) $actorId,
                ($opRef !== '' ? $opRef : $kind));
            return array('ok' => true, 'code' => 200, 'via' => 'exception',
                         'reason' => 'استثناءٌ نافذٌ #' . (int) $exc['req_id']
                                   . ' — استعمالٌ محسوبٌ حتى ' . (string) $exc['valid_to']);
        }

        /* ④ ولا شيء: 422 يسمّي المطلوب */
        $need = array();
        if (!empty($spec['any_of'])) { foreach ($spec['any_of'] as $s) { $need[] = $s['label']; } }
        if (!empty($spec['text_of'])) { foreach ($spec['text_of'] as $l) { $need[] = $l; } }
        return array('ok' => false, 'code' => 422, 'via' => '',
                     'reason' => 'FIN-SRC-422: ' . $spec['label'] . ' بلا مستندِ مصدر — المطلوبُ أحدُ: '
                               . implode(' أو ', $need) . ' (أو استثناءٌ معتمدٌ نافذ)');
    }
}
