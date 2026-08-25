<?php
/**
 * includes/client_match_guard.php — حرّاسُ مطابقةِ العميلِ وبوابةِ المبيعات
 * ═══════════════════════════════════════════════════════════════════════════
 * المرجع: `docs/specs/SPEC_TIMESHEET_CYCLE_ar.md` — `TS-04` · `TS-05` · `TS-16`.
 *
 * ◆ ما لا يستطيع قيدُ القاعدةِ فرضَه يُفرَض هنا — وما تستطيعه القاعدةُ **لا
 *   يُكرَّر** هنا (لا حارسان لحكمٍ واحد):
 *     · القاعدةُ تفرض: سندَ المطابقةِ · مرجعَ النزاعِ · حقولَ التجاوزِ السبعة ·
 *       ومنعَ التجاوزِ على مطابقةٍ مكتملة.
 *     · وهذه الدوالُّ تفرض ما يعبر **جدولين**:
 *         `TS-05-ج` لا تجاوزَ فوقَ **رفضٍ صريحٍ** من العميل.
 *         `TS-05-أ` ولا اعتمادَ مبيعاتٍ إلا بمطابقةٍ مكتملةٍ أو تجاوزٍ مسجَّل.
 *
 * ◆ وكلُّ منعٍ يعود **رمزًا وسببًا** لا `false` مجرَّدًا — فالمنعُ الصامتُ يُقرأ
 *   عطلًا، والرسالةُ نصفُ الحارس.
 */

if (!function_exists('ems_client_match_can_override')) {
    /**
     * `TS-05-ج` — أيجوز تسجيلُ قرارِ تجاوزٍ لهذا المدخل؟
     *
     * «لا يجوز لمدير المبيعات استخدامُ التجاوزِ لإلغاءِ رفضٍ صريحٍ من العميل؛
     *  فالرفضُ الصريحُ يفتح نزاعًا ولا يُعامَل معاملةَ غيابِ ردّ.»
     *
     * @return ?array{code:int,reason:string} null = يجوز
     */
    function ems_client_match_can_override(mysqli $conn, $entryId, $companyId = 0)
    {
        $entryId = (int) $entryId;
        if ($entryId <= 0) { return array('code' => 422, 'reason' => 'مدخل غير محدد'); }
        $sql = 'SELECT client_match_state, client_decision, state FROM unit_entries WHERE id = ?';
        $st = $conn->prepare($sql);
        if (!$st) {
            error_log('ems_client_match_can_override: prepare — ' . $conn->error);
            return array('code' => 500, 'reason' => 'تعذرت قراءة المدخل — لا يقرأ الفشل إذنا');
        }
        $st->bind_param('i', $entryId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) { return array('code' => 404, 'reason' => 'مدخل غير موجود'); }

        if ((string) $row['client_decision'] === 'disputed') {
            return array('code' => 403,
                'reason' => '**لا تجاوز فوق رفض صريح** — العميل رفض هذا المدخل فانفتح نزاع، '
                          . 'والرفض الصريح لا يعامل معاملة غياب رد (TS-05-ج)');
        }
        if ((string) $row['client_match_state'] === 'matched') {
            return array('code' => 409,
                'reason' => 'المطابقة مكتملة — يعتمد الناتج ولا يتجاوز ما طابق (TS-05-أ ①)');
        }
        if ((string) $row['client_match_state'] === 'pending') {
            return array('code' => 409,
                'reason' => 'المطابقة لم تحسم بعد — لا تجاوز قبل محاولة ونتيجة (TS-04)');
        }
        return null;
    }
}

if (!function_exists('ems_client_match_can_sales_approve')) {
    /**
     * `TS-05-أ` — أيجوز اعتمادُ المبيعاتِ لهذا المدخل؟
     * إمّا **مطابقةٌ مكتملة**، وإمّا **قرارُ تجاوزٍ مسجَّلٌ** بسندِه.
     *
     * @return ?array{code:int,reason:string} null = يجوز
     */
    function ems_client_match_can_sales_approve(mysqli $conn, $entryId, $forBilling = false)
    {
        $entryId = (int) $entryId;
        $st = $conn->prepare('SELECT client_match_state, client_decision FROM unit_entries WHERE id = ?');
        if (!$st) {
            error_log('ems_client_match_can_sales_approve: prepare — ' . $conn->error);
            return array('code' => 500, 'reason' => 'تعذرت قراءة المدخل');
        }
        $st->bind_param('i', $entryId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) { return array('code' => 404, 'reason' => 'مدخل غير موجود'); }

        if ((string) $row['client_decision'] === 'disputed') {
            return array('code' => 403,
                'reason' => 'مدخل متنازع عليه — لا يتقدم إلى أثر نهائي حتى تسوى (TS-16)');
        }
        if ((string) $row['client_match_state'] === 'matched') { return null; }

        /* غيرُ مطابقٍ ⇒ يلزمه قرارُ تجاوزٍ مسجَّل، وبالسعةِ المطلوبة */
        $st = $conn->prepare('SELECT allows FROM unit_match_overrides WHERE entry_id = ? ORDER BY id DESC LIMIT 1');
        if (!$st) { return array('code' => 500, 'reason' => 'تعذرت قراءة قرارات التجاوز'); }
        $st->bind_param('i', $entryId);
        $st->execute();
        $ov = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$ov) {
            return array('code' => 403,
                'reason' => 'المطابقة «' . $row['client_match_state'] . '» ولا قرار تجاوز مسجل — '
                          . 'وبوابة المبيعات قرار مسجل لا مرور صامت (TS-05-أ)');
        }
        if ($forBilling && (string) $ov['allows'] !== 'billing') {
            return array('code' => 403,
                'reason' => 'قرار التجاوز يسمح بالأثر الأولي فقط — والفوترة تحتاج قرارا يسمح بها صراحة (TS-05-ب ⑥)');
        }
        return null;
    }
}
