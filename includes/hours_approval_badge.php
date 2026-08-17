<?php
/**
 * includes/hours_approval_badge.php — عدّادُ سجلاتِ الساعاتِ المنتظِرةِ اعتمادَ إدارتي
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ◆ **تعريفٌ واحدٌ لا نسخةٌ ثانية**
 *   الرقمُ الذي يظهر على أيقونةِ الشريطِ هو **نفسُه** شرطُ «قيد الاعتماد» في
 *   `Approvals/hours_approval.php` حرفًا بحرف — نُقل إلى هنا ليُنادى من
 *   الموضعين، فلا يفترق عدّادٌ عن عارضِه كما يقع حين يُكتب الشرطُ مرّتين.
 *
 * ◆ **ولمن تظهر**: لمن له **مستوًى في سلسلةِ الاعتماد** المُعلَنةِ في
 *   `EMS_HOURS_APPROVAL_LEVELS` — خمسُ إداراتٍ لا غير: التشغيل (1) ثم الموردين
 *   (2) ثم الأسطول (3) ثم الموارد البشرية/المشغلين (4) ثم المبيعات (12).
 *   والقائمةُ **تُشتقُّ ولا تُكتب**: أيُّ تعديلٍ في السلسلةِ يتبعه العدّادُ
 *   والأيقونةُ من تلقاءِ نفسِهما، فلا قائمةَ أدوارٍ مبثوثةٌ تتعفّن.
 *   ومَن لا مستوى له — السوبر أدمن ومديرُ الموقع وسائرُ الأدوار — لا شأنَ له
 *   بالاعتماد، فلا تُعرض له أيقونةٌ توهمه بعملٍ ينتظره.
 *
 * ◆ **والشرطُ يختلف بالمستوى** (كما في الشاشة):
 *     المستوى ①  ⇐ كلُّ ما لم يعتمده هو بعد.
 *     المستوى ②..⑤ ⇐ ما اعتمده **المستوى الذي قبلَه** ولم يعتمده هو.
 *   فرقمُ كلِّ إدارةٍ يخصُّها وحدَها، ولا يرى المستوى الثالثُ ما لم يصله بعد.
 *
 * ◆ **العزلُ صريحٌ بالكيان**: `t.company_id = ?` مربوطةً — وهي ما يفرضه
 *   `{TENANT_SCOPE}` في الشاشةِ على الجدولِ نفسِه لغيرِ الأدمن.
 *
 * ◆ **ويُفحص مُرجَعُ كلِّ خطوة**: `config.php` يضبط mysqli على **عدمِ الرمي**،
 *   فالفشلُ يعود `false` صامتًا. وأيُّ تعذُّرٍ يعني صفرًا وشارةً لا تظهر — لا
 *   شريطٌ علويٌّ مكسور.
 *
 * ◆ **ما لا يَعِدُ به** — مُعلَنٌ لا مسكوتٌ عنه: الشاشةُ تعرض ٥٠٠ صفٍّ كحدٍّ
 *   أقصى (`LIMIT 500`) بينما هذا عدٌّ كامل. فحين يتجاوز المنتظِرُ خمسَمئةٍ
 *   يقول العدّادُ «+99» وتعرض الشاشةُ خمسَمئة — وكلاهما صادقٌ في بابِه:
 *   هذا عددُ ما ينتظر، وتلك صفحةُ ما يُعرض. (المقيسُ اليومَ في الكيان 4:
 *   المستوى ① 48704 · ② 22 · ③ 15 · ④ 0 · ⑤ 2.)
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/roles.php';

if (!function_exists('ems_hours_approval_level_of')) {
    /**
     * مستوى الدورِ في سلسلةِ اعتمادِ الساعات — أو 0 لمن لا مستوى له.
     *
     * @param  string|int $role  رمزُ الدور كما هو في الجلسة
     * @return int               1..5 أو 0
     */
    function ems_hours_approval_level_of($role)
    {
        if (!function_exists('ems_hours_role_level_map')) { return 0; }
        $map = ems_hours_role_level_map();
        $key = strval($role);
        return isset($map[$key]) ? (int) $map[$key] : 0;
    }
}

if (!function_exists('ems_hours_pending_count')) {
    /**
     * عددُ سجلاتِ الساعاتِ التي تنتظر اعتمادَ هذا الدورِ في هذا الكيان.
     *
     * @param  mysqli     $conn
     * @param  string|int $role        دورُ صاحبِ الجلسة
     * @param  int        $company_id  كِيانُه
     * @return int                     0 لمن لا مستوى له أو عند أيِّ تعذُّر
     */
    function ems_hours_pending_count(mysqli $conn, $role, $company_id)
    {
        $level = ems_hours_approval_level_of($role);
        $co    = (int) $company_id;
        if ($level <= 0 || $co <= 0) { return 0; }

        /* الشرطُ نظيرُ `$pending_condition` في الشاشةِ للمستوياتِ ذاتِ السلسلة */
        if ($level === 1) {
            $sql = "SELECT COUNT(*) c FROM timesheet t
                     WHERE t.status = 1 AND t.company_id = ?
                       AND NOT EXISTS (SELECT 1 FROM timesheet_approvals a
                                        WHERE a.timesheet_id = t.id
                                          AND a.approval_level = 1 AND a.status = 1)";
            $types = 'i'; $args = array($co);
        } else {
            $sql = "SELECT COUNT(*) c FROM timesheet t
                     WHERE t.status = 1 AND t.company_id = ?
                       AND EXISTS     (SELECT 1 FROM timesheet_approvals a
                                        WHERE a.timesheet_id = t.id
                                          AND a.approval_level = ? AND a.status = 1)
                       AND NOT EXISTS (SELECT 1 FROM timesheet_approvals b
                                        WHERE b.timesheet_id = t.id
                                          AND b.approval_level = ? AND b.status = 1)";
            $types = 'iii'; $args = array($co, $level - 1, $level);
        }

        $n = 0;
        try {
            $st = $conn->prepare($sql);
            if (!$st) { return 0; }
            $st->bind_param($types, ...$args);
            if ($st->execute()) {
                $rs = $st->get_result();
                if ($rs && ($row = $rs->fetch_assoc())) { $n = (int) $row['c']; }
            }
            $st->close();
        } catch (\Throwable $e) {
            if (function_exists('ems_catch_log')) { ems_catch_log($e, __FUNCTION__); }
            return 0;
        }
        return $n;
    }
}
