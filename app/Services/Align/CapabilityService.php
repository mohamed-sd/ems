<?php
namespace App\Services\Align;

/**
 * app/Services/Align/CapabilityService.php
 *   خدمةُ القدراتِ الخمسِ التي ثبت غيابُها — INJ-SAL-ALIGN-01 · INJ-SUP-ALIGN-01
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **خدمةٌ كنسيةٌ واحدةٌ لكلِّ قدرةٍ محكومة** — ولا كاتبَ مباشرًا من الشاشة.
 *   وكلُّ وصولٍ للبياناتِ ببوابةِ المستأجِر، فلا استعلامَ خامٍّ على جدولِ مستأجِر.
 *
 * ◆ **وأزرارُ الإنشاءِ بالإسنادِ لها شرطُ إتاحة** — بنصِّ الوثيقة: «إتاحةُ إنشاءِ
 *   المستندِ التالي = سياقُ الأب + انتقالُ حالةٍ مسموح + الاعتماداتُ المطلوبة +
 *   المستندُ المصدريُّ اللازم». والشرطُ **يُفحص في طبقةِ الخدمةِ قبلَ الكتابةِ لا
 *   في الواجهةِ وحدَها**: «الواجهةُ تُخفي والخادمُ يمنع».
 *
 * ◆ **والتعبئةُ بالوراثةِ لا بإعادةِ الإدخال**: ما في الأبِ يُملأ في التابعِ ولا
 *   يُعاد كتابتُه — «لا يبقى فارغًا إلا الحقلُ الذي يحمل معلومةً جديدةً فعلًا».
 * ═══════════════════════════════════════════════════════════════════════════
 */
class CapabilityService
{
    private static function fail($code, $reason) { return array('ok' => false, 'code' => $code, 'reason' => $reason); }
    private static function done($code, $reason, $id = 0) { return array('ok' => true, 'code' => $code, 'reason' => $reason, 'id' => (int) $id); }

    public static function idem($parts)
    {
        return mb_substr(implode(':', array_map('strval', (array) $parts)), 0, 96);
    }

    private static function guardedInsert($gate, $table, array $data, $dupMsg)
    {
        try { return array('ok' => true, 'id' => (int) $gate->insert($table, $data)); }
        catch (\Throwable $e) {
            $m = $e->getMessage();
            if (stripos($m, 'duplicate') !== false || strpos($m, '1062') !== false) {
                return array('ok' => false, 'dup' => true, 'msg' => $dupMsg);
            }
            return array('ok' => false, 'dup' => false, 'msg' => $m);
        }
    }

    /* ══ الورقة 06 — احتياجُ العميلِ وطلبُ العرض ═══════════════════════════ */

    /**
     * ◆ **شرطُ الإتاحة**: «الفرصةُ مفتوحةٌ ولها مسؤولٌ مسنَد» — يُفحص هنا لا في الزر.
     */
    public static function openNeed($conn, $gate, $companyId, array $d, $by)
    {
        $opp = (int) ($d['opportunity_id'] ?? 0);
        $svc = trim((string) ($d['service_type'] ?? ''));
        $qty = round((float) ($d['qty'] ?? 0), 4);
        if ($opp <= 0) { return self::fail(422, 'لا احتياج بلا فرصة أم — السجل تابع لا مستقل'); }
        if (mb_strlen($svc) < 3) { return self::fail(422, 'نوع الخدمة إلزامي'); }
        if ($qty <= 0) { return self::fail(422, 'الكمية يجب أن تكون موجبة'); }

        /* الأبُ يُقرأ: حالتُه ومسؤولُه — والوراثةُ منه لا إعادةُ إدخال */
        $o = $gate->selectOne('opportunities', array('where' => array('id' => $opp)));
        if (!$o) { return self::fail(404, 'الفرصة غير موجودة'); }
        /* ◆ **المرحلةُ تُقرأ من عمودِها الحيِّ لا من اسمٍ مفترَض**: العمودُ
         *   `stage` بقيمٍ عربيةٍ محكومة، و`status` لا وجودَ له في الجدول.
         *   والمغلقةُ ثلاثٌ: فوز · خسارة · مستبعدة. */
        $stage = trim((string) ($o['stage'] ?? ''));
        $CLOSED = array('فوز', 'خسارة', 'مستبعدة');
        if ($stage !== '' && in_array($stage, $CLOSED, true)) {
            return self::fail(409, "لا يسجل احتياج على فرصة غير مفتوحة — مرحلتها: {$stage}");
        }
        if ((int) ($o['is_deleted'] ?? 0) === 1) {
            return self::fail(409, 'الفرصة محذوفة ناعما — لا يسجل عليها احتياج');
        }
        $no  = 'NED-' . str_pad((string) $opp, 6, '0', STR_PAD_LEFT) . '-'
             . str_pad((string) (int) $gate->count('sal_client_needs',
                   array('where' => array('opportunity_id' => $opp))) + 1, 2, '0', STR_PAD_LEFT);
        $r = self::guardedInsert($gate, 'sal_client_needs', array(
            /* ◆ **والعمودُ المُعلَنُ يُملأ بالحقِّ لا بصفر**: البوابةُ تحقن `company_id`
             *   في `T_TENANT` وحدَها، وتعزل الأبناءَ **بأبيهم** — فيبقى عمودُ الابنِ
             *   صفرًا وهو مُعلَنٌ في الجدول. ورقمٌ يُقرأ صحيحًا وهو خطأ: أيُّ استعلامٍ
             *   لاحقٍ يرشّح بـ`company_id` يعود بصفرِ صفوفٍ صامتًا. ⇒ يُكتب صراحةً. */
            'company_id' => (int) $companyId,
            'need_no' => $no, 'opportunity_id' => $opp,
            'client_id'  => isset($o['client_id']) ? (int) $o['client_id'] : null,
            'project_id' => (int) ($d['project_id'] ?? 0) ?: null,   /* لا عمودَ له في الفرصة */
            'business_model' => mb_substr((string) ($d['business_model'] ?? ''), 0, 80),
            'service_type' => mb_substr($svc, 0, 120), 'qty' => $qty,
            'unit_type' => mb_substr((string) ($d['unit_type'] ?? 'hour'), 0, 16),
            'duration_months' => (int) ($d['duration_months'] ?? 0) ?: null,
            'required_from' => ($d['required_from'] ?? '') !== '' ? $d['required_from'] : null,
            'notes' => mb_substr((string) ($d['notes'] ?? ''), 0, 400),
            'state' => 'draft', 'created_by' => (int) $by,
            'idem_key' => self::idem(array('ned', (int) $companyId, $opp, $svc, $qty)),
        ), 'احتياج مسجل سلفا بالخدمة والكمية نفسهما — عطالة');
        if (!$r['ok']) { return self::fail(!empty($r['dup']) ? 200 : 500, $r['msg']); }
        return self::done(201, "سجل الاحتياج {$no} — ولا يصدر عرض قبل رفعه", $r['id']);
    }

    /** رفعُ الاحتياجِ — وبه وحدَه يُتاح إصدارُ العرض. */
    public static function submitNeed($conn, $gate, $companyId, $id)
    {
        $n = $gate->update('sal_client_needs',
            array('state' => 'submitted', 'submitted_at' => date('Y-m-d H:i:s')),
            array('id' => (int) $id, 'state' => 'draft'));
        if ((int) $n === 0) { return self::fail(409, 'لا يرفع إلا احتياج مسودة'); }
        return self::done(200, 'رفع الاحتياج — وصار إصدار العرض متاحا', $id);
    }

    /* ══ الورقة 08 — بنودُ العروض ═══════════════════════════════════════ */

    public static function addQuotationLine($conn, $gate, $companyId, array $d, $by)
    {
        $q   = (int) ($d['quotation_id'] ?? 0);
        $desc = trim((string) ($d['description'] ?? ''));
        $qty = round((float) ($d['qty'] ?? 0), 4);
        $pr  = round((float) ($d['unit_price'] ?? 0), 2);
        $cur = mb_substr(trim((string) ($d['currency'] ?? '')), 0, 8);
        $disc = round((float) ($d['discount_pct'] ?? 0), 2);
        if ($q <= 0) { return self::fail(422, 'لا بند بلا رأس عرض — البنود تابعة لرأسها'); }
        if (mb_strlen($desc) < 3) { return self::fail(422, 'وصف البند إلزامي'); }
        if ($qty <= 0) { return self::fail(422, 'الكمية يجب أن تكون موجبة'); }
        if ($pr < 0)   { return self::fail(422, 'السعر لا يكون سالبا'); }
        if (mb_strlen($cur) < 3) { return self::fail(422, 'لا مبلغ بلا عملة'); }
        if ($disc < 0 || $disc > 100) { return self::fail(422, 'الخصم بين صفر ومئة'); }

        /* ◆ **الحسابُ في طبقةِ الخدمةِ لا في الشاشة** — يُعرض ولا يُدخَل */
        $total = round($qty * $pr * (1 - $disc / 100), 2);
        $next  = (int) $gate->count('sal_quotation_lines', array('where' => array('quotation_id' => $q))) + 1;
        $r = self::guardedInsert($gate, 'sal_quotation_lines', array(
            'company_id' => (int) $companyId,   /* العمودُ المُعلَنُ يُملأ بالحقِّ لا بصفر */
            'quotation_id' => $q, 'line_no' => $next,
            'product_id' => (int) ($d['product_id'] ?? 0) ?: null,
            'description' => mb_substr($desc, 0, 240), 'qty' => $qty,
            'unit_type' => mb_substr((string) ($d['unit_type'] ?? 'hour'), 0, 16),
            'unit_price' => $pr, 'currency' => $cur, 'discount_pct' => $disc,
            'line_total' => $total, 'notes' => mb_substr((string) ($d['notes'] ?? ''), 0, 200),
            'created_by' => (int) $by,
        ), 'بند بالرقم نفسه مسجل سلفا — عطالة');
        if (!$r['ok']) { return self::fail(!empty($r['dup']) ? 200 : 500, $r['msg']); }
        return self::done(201, "أضيف البند رقم {$next} — الإجمالي " . number_format($total, 2) . " {$cur}", $r['id']);
    }

    /* ══ الورقة 09 — التفاوضُ ومراجعاتُ العرض ══════════════════════════ */

    public static function logNegotiation($conn, $gate, $companyId, array $d, $by)
    {
        $q    = (int) ($d['quotation_id'] ?? 0);
        $kind = (string) ($d['event_kind'] ?? '');
        $party = (string) ($d['party'] ?? '');
        $note = trim((string) ($d['note'] ?? ''));
        $KINDS = array('issued', 'sent', 'client_counter', 'revised', 'accepted', 'rejected', 'expired');
        if ($q <= 0) { return self::fail(422, 'لا واقعة تفاوض بلا عرض'); }
        if (!in_array($kind, $KINDS, true)) { return self::fail(422, 'نوع الواقعة محكوم من قائمة مغلقة'); }
        if (!in_array($party, array('us', 'client'), true)) { return self::fail(422, 'الطرف: نحن أو العميل'); }
        if (mb_strlen($note) < 8) { return self::fail(422, 'لا واقعة تفاوض بلا نص يشرحها'); }

        $next = (int) $gate->count('sal_quotation_revisions', array('where' => array('quotation_id' => $q))) + 1;
        $r = self::guardedInsert($gate, 'sal_quotation_revisions', array(
            'company_id' => (int) $companyId,   /* العمودُ المُعلَنُ يُملأ بالحقِّ لا بصفر */
            'quotation_id' => $q, 'revision_no' => $next, 'event_kind' => $kind,
            'party' => $party, 'note' => mb_substr($note, 0, 400),
            'doc_ref' => mb_substr((string) ($d['doc_ref'] ?? ''), 0, 120) ?: null,
            'amount_before' => ($d['amount_before'] ?? '') !== '' ? round((float) $d['amount_before'], 2) : null,
            'amount_after'  => ($d['amount_after'] ?? '') !== '' ? round((float) $d['amount_after'], 2) : null,
            'currency' => mb_substr(trim((string) ($d['currency'] ?? '')), 0, 8) ?: null,
            'valid_until' => ($d['valid_until'] ?? '') !== '' ? $d['valid_until'] : null,
            'decided_by' => (int) $by,
            'idem_key' => self::idem(array('sqr', (int) $companyId, $q, $kind, $note)),
        ), 'واقعة مسجلة سلفا بالنص نفسه — عطالة');
        if (!$r['ok']) { return self::fail(!empty($r['dup']) ? 200 : 500, $r['msg']); }
        return self::done(201, "سجلت المراجعة رقم {$next}", $r['id']);
    }

    /* ══ الورقة م19 — المخالفاتُ والجزاءات ═════════════════════════════ */

    public static function recordViolation($conn, $gate, $companyId, array $d, $by)
    {
        $sup  = (int) ($d['supplier_id'] ?? 0);
        $kind = (string) ($d['violation_kind'] ?? '');
        $on   = (string) ($d['occurred_on'] ?? '');
        $desc = trim((string) ($d['description'] ?? ''));
        $amt  = round((float) ($d['penalty_amount'] ?? 0), 2);
        $cur  = mb_substr(trim((string) ($d['currency'] ?? '')), 0, 8);
        $KINDS = array('availability', 'quality', 'safety', 'document', 'delay', 'other');
        if ($sup <= 0) { return self::fail(422, 'لا مخالفة بلا مورد'); }
        if (!in_array($kind, $KINDS, true)) { return self::fail(422, 'نوع المخالفة محكوم من قائمة مغلقة'); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $on)) { return self::fail(422, 'تاريخ الوقوع بصيغة YYYY-MM-DD'); }
        if (mb_strlen($desc) < 8) { return self::fail(422, 'لا مخالفة بلا وصف مفهوم'); }
        if ($amt > 0 && mb_strlen($cur) < 3) { return self::fail(422, 'لا جزاء بمبلغ بلا عملة'); }

        $no = 'VIO-' . str_replace('-', '', $on) . '-' . str_pad((string) $sup, 5, '0', STR_PAD_LEFT);
        $r = self::guardedInsert($gate, 'sup_violations', array(
            'violation_no' => $no, 'supplier_id' => $sup,
            'contract_id' => (int) ($d['contract_id'] ?? 0) ?: null,
            'settlement_id' => (int) ($d['settlement_id'] ?? 0) ?: null,
            'rule_ref' => mb_substr((string) ($d['rule_ref'] ?? ''), 0, 120) ?: null,
            'violation_kind' => $kind, 'occurred_on' => $on,
            'description' => mb_substr($desc, 0, 400),
            'evidence_ref' => mb_substr((string) ($d['evidence_ref'] ?? ''), 0, 120) ?: null,
            'penalty_amount' => $amt, 'currency' => $amt > 0 ? $cur : null,
            'recorded_by' => (int) $by, 'state' => 'recorded',
            'idem_key' => self::idem(array('vio', (int) $companyId, $sup, $on, $kind, $desc)),
        ), 'مخالفة مسجلة سلفا لهذا المورد بالتاريخ والنوع نفسهما — عطالة');
        if (!$r['ok']) { return self::fail(!empty($r['dup']) ? 200 : 500, $r['msg']); }
        return self::done(201, "سجلت المخالفة {$no} — والاعتماد بيد غير يد راصدها", $r['id']);
    }

    /** الاعتماد — **مَن رصد لا يعتمد**، وقيدُ القاعدةِ يسنده. */
    public static function approveViolation($conn, $gate, $companyId, $id, $actor)
    {
        $row = $gate->selectOne('sup_violations', array(
            'columns' => array('state', 'recorded_by'), 'where' => array('id' => (int) $id)));
        if (!$row) { return self::fail(404, 'المخالفة غير موجودة'); }
        if ($row['state'] === 'approved') { return self::fail(200, 'معتمدة سلفا — عطالة'); }
        if ($row['state'] !== 'recorded') { return self::fail(409, "الاعتماد على «مرصودة» وحدها — الحال: {$row['state']}"); }
        if ((int) $row['recorded_by'] === (int) $actor) {
            return self::fail(403, '**من رصد لا يعتمد** — والجزاء أثر مالي لا يقرره راصده وحده');
        }
        $n = $gate->update('sup_violations',
            array('state' => 'approved', 'approved_by' => (int) $actor, 'approved_at' => date('Y-m-d H:i:s')),
            array('id' => (int) $id, 'state' => 'recorded'));
        if ((int) $n === 0) { return self::fail(409, 'تغيرت الحالة بين القراءة والكتابة'); }
        return self::done(200, 'اعتمدت المخالفة — وأثرها يظهر في التسوية', $id);
    }

    /** الإسقاط — بسببٍ مكتوبٍ إلزامًا (قيدُ القاعدةِ يرفض دونَه). */
    public static function waiveViolation($conn, $gate, $companyId, $id, $reason, $actor)
    {
        $reason = trim((string) $reason);
        if (mb_strlen($reason) < 8) { return self::fail(422, 'لا إسقاط بلا سبب مكتوب مفهوم'); }
        $row = $gate->selectOne('sup_violations', array(
            'columns' => array('state', 'recorded_by'), 'where' => array('id' => (int) $id)));
        if (!$row) { return self::fail(404, 'المخالفة غير موجودة'); }
        if ((int) $row['recorded_by'] === (int) $actor) {
            return self::fail(403, '**من رصد لا يسقط** — فصل الواجبات لا يختصر');
        }
        $n = $gate->update('sup_violations',
            array('state' => 'waived', 'waive_reason' => mb_substr($reason, 0, 300),
                  'approved_by' => (int) $actor, 'approved_at' => date('Y-m-d H:i:s')),
            array('id' => (int) $id), "`state` IN ('recorded','reviewed')");
        if ((int) $n === 0) { return self::fail(409, 'لا يسقط إلا ما لم يعتمد بعد'); }
        return self::done(200, 'أسقطت المخالفة بسبب مكتوب', $id);
    }
}
