<?php
/**
 * حارسُ بوابة مشرف المورد — SupplierPortalGuard (H-20 · UX-05 §4/§8.1 · USR-01 §2)
 * ───────────────────────────────────────────────────────────────────────────
 * «الدورُ 8 قائمٌ بلا عزلٍ بنيويّ — فجوةٌ وظيفيةٌ وأمنيةٌ معًا» (الكتالوج).
 * وPLAN-01: «تسريبٌ يقع اليوم: حقنُ النطاق فورًا ولا تنتظر H-15».
 *
 * القواعد:
 *   ① النطاقُ بنيويٌّ لا زخرفي: موردُ الحساب من `users.supplier_entity_id`
 *      (وإلا فمن ربط الموظف `employees.supplier_id`) — لا من مدخلات الطلب.
 *   ② حسابُ دورٍ مقيَّدٍ **بلا مورد = لا يرى شيئًا** (fail-closed) برسالةٍ
 *      تسمّي العلاج، لا fail-open يفتح كلَّ الموردين.
 *   ③ طلبُ موردٍ غير مورده → **403 مسجَّلةٌ** في سجل التدقيق (N-02) بمحاولته.
 *   ④ الإنفاذُ فوريٌّ بلا علم: صفرُ حسابٍ على الدور اليوم (مقيس) فلا مسارَ
 *      حيًّا يُكسر — والرائدةُ هنا بنيويةٌ بطبيعتها.
 *
 * الوريثُ النهائي: H-15 (شخصٌ × صفةٌ × نطاقٌ × مدة) يستوعب هذا الحارسَ في
 * طبقة الوصول العامة (§6.4-②) — وهذا سدُّ الثغرة إلى حينه.
 */

namespace App\Services\Portal;

class SupplierPortalGuard
{
    /** الأدوارُ المقيَّدةُ بمورد — 8 «مشرف موردين» (تُوسَّع مع H-15) */
    const RESTRICTED_ROLES = array('8');

    /**
     * بوابةُ المشرف الخارجي — «سايدبارُه أضيقُ عمدًا: لوحتُه · كشفُه
     * وتحميلاتُه · مراسلاتُه — ولا يرى إعداداتٍ ولا سجلاتِ غيره» (UX-05 §4).
     * مسارٌ خارجَها → 404 (UX-05 §8.1) — والمطابقةُ بذيل المسار أو بادئته.
     * (بابُ «اعتماد وحدات معداته» يُبنى مع H-15/H-18 — لا شاشةَ له اليوم.)
     */
    const PORTAL_SCREENS = array(
        // لوحتُه ومدخلُه ومخرجُه وملفُّه الذاتي
        'main/dashboard.php', 'main/role_board.php', 'main/profile.php', 'logout.php',
        // مراسلاتُه وبلاغاتُه (شاشاتٌ مفتوحةٌ لكل مسجَّلٍ بتصميم النظام)
        'chats/', 'Tickets/tickets_list.php', 'Tickets/ticket_form.php',
        // بطاقةُ موردِه وعقودُه (النطاقُ محقونٌ داخلها)
        'Suppliers/suppliers.php', 'Suppliers/suppliers_details.php',
        'Suppliers/supplier_profile.php', 'Suppliers/supplierscontracts.php',
        'Suppliers/supplierscontracts_details.php', 'Suppliers/showcontractsuppliers.php',
        'Suppliers/get_mine_contracts.php', 'Suppliers/get_project_hours.php',
        'Suppliers/get_supplier_contract_equipments.php',
        // كشفُه وتحميلاتُه
        'Finance/supplier_statement_fin.php',
    );

    /** هل المسارُ داخل بوابة المشرف؟ (مطابقةُ بادئةٍ أو ذيلٍ على المسار النسبي) */
    public static function isPortalScreen($relativeScript)
    {
        $script = ltrim(str_replace('\\', '/', strval($relativeScript)), '/');
        foreach (self::PORTAL_SCREENS as $allowed) {
            if (substr($allowed, -1) === '/') {
                if (strpos($script, $allowed) !== false) { return true; }
            } elseif ($script === $allowed || substr($script, -strlen('/' . $allowed)) === '/' . $allowed) {
                return true;
            }
        }
        return false;
    }

    /**
     * بوابةُ الطبقة (تُستدعى من مُنفِّذ عرض الصفحة المركزي — «الاستعلاماتُ
     * مرشَّحةٌ في الطبقة لا في الشاشات»): جلسةٌ مقيَّدةٌ على مسارٍ خارج
     * بوابتها → 404 مسجَّلةٌ وتنتهي. غيرُ المقيَّد يمرّ بلا أثر.
     */
    public static function gateScreen(\mysqli $conn, $sessionUser, $relativeScript)
    {
        if (!self::isRestricted($sessionUser)) { return; }
        if (self::isPortalScreen($relativeScript)) { return; }
        self::log403($conn, $sessionUser, strval($relativeScript), 0, 'portal_404_out_of_scope');
        require_once dirname(__DIR__, 3) . '/includes/deny_page.php';
        ems_deny_page('PORTAL-404', 'الصفحة خارج حسابك',
            'حسابك حساب بوابة مورد، وهذا المسار ليس ضمن شاشات البوابة.',
            array('status' => 404));
        exit;
    }

    /**
     * تضييقُ السايدبار: عناصرُ nav_items تُرشَّح لقائمة البوابة للجلسة
     * المقيَّدة — إخفاءٌ من القوائم لا حذفُ منح (عقدُ العمل ④).
     */
    public static function filterNavItems($sessionUser, array $items)
    {
        if (!self::isRestricted($sessionUser)) { return $items; }
        $kept = array();
        foreach ($items as $it) {
            if (isset($it['route']) && self::isPortalScreen($it['route'])) { $kept[] = $it; }
        }
        return $kept;
    }

    /** موردُ عقدِ موردٍ (supplierscontracts.id → supplier_id) — null إن لم يوجد */
    public static function supplierOfContract(\mysqli $conn, $contractId)
    {
        $cid = intval($contractId);
        if ($cid <= 0) { return null; }
        $st = $conn->prepare("SELECT supplier_id FROM supplierscontracts WHERE id = ? LIMIT 1");
        if (!$st) { return null; }
        $st->bind_param('i', $cid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return ($row && intval($row['supplier_id']) > 0) ? intval($row['supplier_id']) : null;
    }

    /**
     * إنفاذٌ لنقاط AJAX (JSON): كالإنفاذ الصفحي لكن الردُّ JSON برمز 403.
     * @return int|null موردُ الحقن (null = غيرُ مقيَّد)
     */
    public static function enforceJson(\mysqli $conn, $sessionUser, $requestedSupplierId, $screen = '')
    {
        if (!self::isRestricted($sessionUser)) { return null; }
        $v = self::assertSupplier($conn, $sessionUser, $requestedSupplierId, $screen);
        if (!$v['ok']) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode(array('success' => false, 'message' => $v['reason']), JSON_UNESCAPED_UNICODE));
        }
        return $v['supplier_id'];
    }

    /** هل هذا الحسابُ مقيَّدٌ بنطاق مورد؟ */
    public static function isRestricted($sessionUser)
    {
        $role = isset($sessionUser['role']) ? strval($sessionUser['role']) : '';
        return in_array($role, self::RESTRICTED_ROLES, true);
    }

    /**
     * موردُ الحساب — من الرابط الصريح ثم من ربط الموظف. null = بلا مورد.
     */
    public static function supplierOf(\mysqli $conn, $sessionUser)
    {
        $uid = isset($sessionUser['id']) ? intval($sessionUser['id']) : 0;
        if ($uid <= 0) { return null; }
        $st = $conn->prepare(
            "SELECT COALESCE(u.supplier_entity_id, e.supplier_id) sid
               FROM users u LEFT JOIN employees e ON e.id = u.employee_id
              WHERE u.id = ? LIMIT 1");
        if (!$st) { return null; }
        $st->bind_param('i', $uid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        $sid = ($row && $row['sid'] !== null) ? intval($row['sid']) : 0;
        if ($sid <= 0) { return null; }

        // ── استيعابُ H-15 (الشريحة ②): إن كانت للحساب صفاتُ مشرفِ موردٍ في
        //    طبقة الصفات فهي **الحكم**: يلزم صفةٌ نشطةٌ بنطاق هذا المورد
        //    بعينه — وغيابُها fail-closed. والحسابُ الذي لم تُشتقّ صفاتُه
        //    بعدُ يمرّ بالمسار القائم (توسيعٌ لا هدم — لا كسرَ للرائد).
        $capCount = 0; $capMatch = 0;
        if ($cst = @$conn->prepare(
            "SELECT COUNT(*) n,
                    SUM(CASE WHEN state='active' AND scope_type='supplier' AND scope_id=? THEN 1 ELSE 0 END) m
               FROM user_capacities
              WHERE account_id = ? AND capacity_type = 'supplier_supervisor'")) {
            $cst->bind_param('ii', $sid, $uid);
            $cst->execute();
            if ($crow = $cst->get_result()->fetch_assoc()) {
                $capCount = intval($crow['n']);
                $capMatch = intval($crow['m']);
            }
            $cst->close();
        }
        if ($capCount > 0 && $capMatch === 0) {
            // تسجيلٌ مباشرٌ — لا عبر log403 لأنها تستدعي supplierOf (عودٌ لانهائي)
            require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
            ems_audit_change($conn, 'permissions', 'capacity_layer', 'denied_403', $sid,
                array(),
                array('kind' => 'supplier_capacity_mismatch_or_frozen',
                      'attempted_supplier_id' => $sid, 'own_supplier_id' => $sid),
                array('company_id' => isset($sessionUser['company_id']) ? intval($sessionUser['company_id']) : 0,
                      'user_id' => $uid));
            return null; // بلا صفةٍ نشطةٍ مطابقة = بلا مورد (fail-closed)
        }
        return $sid;
    }

    /**
     * نطاقُ القوائم: null = غيرُ مقيَّد (يرى الكل) · int = موردُه حصرًا ·
     * 0 = مقيَّدٌ بلا مورد (fail-closed: القوائمُ تُعرض فارغةً برسالة).
     */
    public static function scope(\mysqli $conn, $sessionUser)
    {
        if (!self::isRestricted($sessionUser)) { return null; }
        $sid = self::supplierOf($conn, $sessionUser);
        return ($sid === null) ? 0 : $sid;
    }

    /**
     * حارسُ معرّفٍ مطلوب: يمرّ لغير المقيَّد؛ وللمقيَّد يرفض غيرَ مورده
     * بـ403 **مسجَّلة**. لا يُخرج ولا يطبع — القرارُ للمنادي.
     *
     * @return array{ok:bool, code:int, supplier_id:?int, reason:string}
     */
    public static function assertSupplier(\mysqli $conn, $sessionUser, $requestedSupplierId, $screen = '')
    {
        $out = array('ok' => true, 'code' => 200, 'supplier_id' => null, 'reason' => '');
        if (!self::isRestricted($sessionUser)) { return $out; }

        $mine = self::supplierOf($conn, $sessionUser);
        $out['supplier_id'] = $mine;
        if ($mine === null) {
            $out['ok'] = false; $out['code'] = 403;
            $out['reason'] = 'حسابك على دور مشرف الموردين وغير مربوط بأي مورد — يربطه مدير الصلاحيات من شاشة المستخدمين ثم تدخل';
            self::log403($conn, $sessionUser, $screen, 0, 'unlinked_account');
            return $out;
        }
        $req = intval($requestedSupplierId);
        if ($req > 0 && $req !== $mine) {
            $out['ok'] = false; $out['code'] = 403;
            $out['reason'] = 'هذا المورد خارج نطاق حسابك — محاولة التجاوز سجلت';
            self::log403($conn, $sessionUser, $screen, $req, 'cross_supplier_attempt');
            return $out;
        }
        return $out;
    }

    /**
     * إنفاذٌ صفحيٌّ جاهز: يمرّر غيرَ المقيَّد؛ وللمقيَّد يعيد موردَه (يحقنه)،
     * وعند الرفض يطبع 403 وينهي الطلب — للاستعمال أول الشاشة.
     *
     * @return int|null موردُ الحقن (null = غيرُ مقيَّدٍ فلا حقن)
     */
    public static function enforce(\mysqli $conn, $sessionUser, $requestedSupplierId, $screen = '')
    {
        if (!self::isRestricted($sessionUser)) { return null; }
        $v = self::assertSupplier($conn, $sessionUser, $requestedSupplierId, $screen);
        if (!$v['ok']) {
            require_once dirname(__DIR__, 3) . '/includes/deny_page.php';
            ems_deny_page('PORTAL-403', 'خارج نطاق حسابك',
                (string) $v['reason'], array('status' => 403));
            exit;
        }
        return $v['supplier_id'];
    }

    /** تسجيلُ محاولة التجاوز في سجل التدقيق (N-02) — «محاولاتُ الرفض مسجَّلة» (§10-③) */
    private static function log403(\mysqli $conn, $sessionUser, $screen, $attemptedId, $kind)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'permissions', ($screen !== '' ? $screen : 'supplier_portal'),
            'denied_403', intval($attemptedId),
            array(),
            array('kind' => $kind,
                  'attempted_supplier_id' => intval($attemptedId),
                  'own_supplier_id' => self::supplierOf($conn, $sessionUser)),
            array('company_id' => isset($sessionUser['company_id']) ? intval($sessionUser['company_id']) : 0,
                  'user_id' => isset($sessionUser['id']) ? intval($sessionUser['id']) : 0));
    }
}
