<?php
/**
 * includes/self_approval_guard.php — منعُ اعتماد الذات قيدًا عامًّا (M-45)
 * ───────────────────────────────────────────────────────────────────────────
 * UI-01 §8: «لا اعتمادَ للذات» — كان القياسُ: «حيٌّ تطبيقيًّا في المستخلصات
 * والتسويات فقط»؛ وهذا **التعميم**: دالةٌ واحدةٌ يستدعيها كلُّ مسار اعتمادٍ
 * (طلبٌ ماليٌّ · تسويةٌ · قيدٌ · تقييمٌ · إقفال) — والمخالفةُ **403 مسجَّلةٌ**
 * في سجل التدقيق (N-02).
 *
 * «منعُ اعتماد الذات بنيويًّا» (§10-③): الحارسُ في **الخادم** لا بإخفاء زر.
 */

if (!function_exists('ems_no_self_approval')) {
    /**
     * @param mysqli $conn
     * @param int    $creatorId  منشئُ المستند (users.id)
     * @param int    $approverId معتمِدُه
     * @param string $docLabel   وصفُ المستند للسجل (مثل «طلب مالي #7»)
     * @param int    $companyId
     * @return ?array{code:int,reason:string}  null = يجوز الاعتماد
     */
    function ems_no_self_approval($conn, $creatorId, $approverId, $docLabel, $companyId = 0)
    {
        $creatorId = (int) $creatorId;
        $approverId = (int) $approverId;
        if ($creatorId <= 0 || $approverId <= 0 || $creatorId !== $approverId) {
            return null; // يدان مختلفتان — أو منشئٌ مجهولٌ يفصل فيه حارسُ مساره
        }
        // 403 مسجَّلة — «محاولاتُ الرفض مسجَّلة» (PLAN-01 §12.2-③)
        require_once __DIR__ . '/audit_trail.php';
        ems_audit_change($conn, 'permissions', 'self_approval_guard', 'denied_403', $creatorId,
            array(),
            array('doc' => (string) $docLabel, 'creator' => $creatorId, 'approver' => $approverId),
            array('company_id' => (int) $companyId, 'user_id' => $approverId));
        return array('code' => 403,
            'reason' => '**من أنشأ لا يعتمد** — ' . (string) $docLabel
                      . ' أنشأتَه بنفسك، والاعتمادُ يدٌ ثانية (UI-01 §8 · 403 مسجَّلة)');
    }
}

if (!function_exists('ems_assert_not_self_approval')) {
    /**
     * P1-B — الوجهُ المختصرُ لكلِّ مسارِ اعتماد (INJ-0016·0017·0024·0030·0039·0040·0041·0042)
     * ═══════════════════════════════════════════════════════════════════════
     * ◆ العيبُ المقيس: الحارسُ **مبنيٌّ** ويُستدعى في أربعةِ ملفاتٍ فقط، بينما
     *   ثمانيةُ معتمِدين آخرين (اعتماداتُ القمة · ساعاتُ العمل · دفترُ الأسعار ·
     *   الأحداثُ المالية · القيود · الدفعات · إقفالُ الفترة) يعتمد فيها المنشئُ
     *   ما أنشأ. فالمبنيُّ الذي لا يُنادى كالمعدوم.
     *
     * ◆ ولماذا وجهٌ ثانٍ: النداءُ الأصليُّ يُرجع مصفوفةً يفحصها المُنادي —
     *   وثمانيةُ مواضعَ تعني ثمانيَ فرصٍ لإهمالِ المُرجَع. هذا الوجهُ **يوقف
     *   التنفيذَ بنفسِه** (استثناءٌ أو رسالةٌ)، فلا يُهمَل مُرجَعُه.
     *
     * ◆ ويقرأ المنشئَ من الصفِّ مباشرةً فلا يُمرَّر خطأً.
     *
     * @param string $table   جدولُ المستند
     * @param string $pkCol   عمودُ المفتاح
     * @param mixed  $pkVal   قيمتُه
     * @param string $label   وصفُ المستندِ للسجل
     * @return ?array null = يجوز · مصفوفةٌ = ممنوعٌ برمزٍ وسبب
     */
    function ems_assert_not_self_approval($conn, $table, $pkCol, $pkVal, $label, $companyId = 0, $creatorCol = 'created_by')
    {
        $approver = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
        if ($approver <= 0) { return null; }
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
        $pkCol = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $pkCol);
        $creatorCol = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $creatorCol);
        if ($table === '' || $pkCol === '' || $creatorCol === '') { return null; }

        $st = $conn->prepare("SELECT `{$creatorCol}` AS c FROM `{$table}` WHERE `{$pkCol}` = ? LIMIT 1");
        if (!$st) {
            // ◆ لا يُبتلع: تعذّرُ القراءةِ يُسجَّل — ولا يُقرأ إذنًا.
            error_log('ems_assert_not_self_approval: prepare — ' . $conn->error . " [{$table}.{$creatorCol}]");
            return null;
        }
        $st->bind_param('s', $pkVal);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) { return null; }   // لا مستندَ — يفصل فيه حارسُ مسارِه

        return ems_no_self_approval($conn, (int) $row['c'], $approver, (string) $label, (int) $companyId);
    }
}
