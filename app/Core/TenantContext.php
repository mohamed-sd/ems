<?php
/**
 * سياق المستأجر — هوية مَن تعمل البوابة باسمه (ADR-02)
 * ───────────────────────────────────────────────────────────────────────────
 * مبدأ «منع تمرير الهوية من العميل» يتحقق هنا بنيويًا: لا يوجد مسار إنشاءٍ
 * يقرأ من $_POST/$_GET إطلاقًا — مصدران موثوقان فقط:
 *   fromSession()  جلسة الويب المصادَق عليها (المسار الافتراضي).
 *   forSystem()    سياق خادمي صريح (cron/API بعد مصادقة token خادمية) —
 *                  المُستدعي مسؤولٌ عن أن company_id مصدره الخادم لا العميل.
 * الكائن غير قابلٍ للتغيير بعد إنشائه (immutable).
 */

namespace App\Core;

class TenantContext
{
    private $companyId;
    private $userId;
    private $role;

    private function __construct($companyId, $userId, $role)
    {
        $this->companyId = intval($companyId);
        $this->userId = intval($userId);
        $this->role = strval($role);
    }

    /* ══ تجميدُ الشركةِ الواحدة (FREEZE · single-company) ══════════════
       الشركةُ المُثبَّتة — مقيسةٌ من `admin_companies`/`users` لا مفترضةً:
       المُعرّفُ 4 «ايكوبيشن» (79 مستخدمًا من 80). ثابتٌ صنفيٌّ لا عامٌّ
       فلا يتعلّق بترتيبِ تحميلِ config.php — والملفُ يُحمَل من cron وAPI
       والويب جميعًا. */
    const EMS_FIXED_COMPANY_ID = 4;

    /** السياق من الجلسة المصادَق عليها — المصدر الافتراضي الوحيد للويب. */
    public static function fromSession()
    {
        $u = isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : array();
        /* ══ تجميدُ الشركةِ الواحدة (FREEZE) ═══════════════════════
           ◆ **الصفرُ يُستبدَل بالمُثبَّت**: جلسةٌ بلا `company_id` كانت تُنتج
             سياقًا بشركةِ صفرٍ يُفرّغ كلَّ استعلامٍ صامتًا — وفي نظامِ
             الشركةِ الواحدةِ لا معنى لذلكَ فتُثبَّت القيمةُ.
           ◆ **وقيمةُ الجلسةِ إن وُجدت تُحترم**: فالتجميدُ يُثبّتُ الافتراضيَّ
             ولا يمنحُ مستخدمَ شركةٍ أخرى بياناتِ الشركةِ المُثبَّتة —
             والعزلُ في `TenantDb` يبقى على حالِه حرفًا. */
        $sessionCompanyId = isset($u['company_id']) ? intval($u['company_id']) : 0;
        $companyId = $sessionCompanyId > 0 ? $sessionCompanyId : self::EMS_FIXED_COMPANY_ID;
        return new self(
            $companyId,
            isset($u['id']) ? $u['id'] : 0,
            isset($u['role']) ? $u['role'] : ''
        );
    }

    /**
     * سياق كونسول المزوّد — من جلسة المدير الأعلى المصادَق عليها حصرًا
     * (دفعة المزوّد هـ-0 · 2026-07-16). نفس درجة ثقة fromSession (جلسة خادمية،
     * لا هوية من العميل): يقرأ $_SESSION['super_admin'] التي لا تُبنى إلا عبر
     * admin/login.php، ويرفض مغلقًا عند غيابها — لا سياق مزوّدٍ بلا جلسة مزوّد.
     * الشركة صفر والدور '-1' بنيويًا (طبقة المنصّة فوق المستأجرين لا بينهم).
     */
    public static function fromSuperAdminSession()
    {
        $sa = isset($_SESSION['super_admin']) && is_array($_SESSION['super_admin']) ? $_SESSION['super_admin'] : array();
        $said = isset($sa['id']) ? intval($sa['id']) : 0;
        if ($said <= 0) {
            if (function_exists('log_security_event')) {
                log_security_event('tenant_gate_platform_context',
                    'REFUSED (no super admin session) | script=' . (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '?'));
            }
            require_once __DIR__ . '/TenantGateException.php';
            throw new TenantGateException('platform context refused: super admin session required');
        }
        if (function_exists('log_security_event')) {
            log_security_event('tenant_gate_platform_context',
                'granted | super_admin=' . $said
                . ' script=' . (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '?'));
        }
        return new self(0, $said, defined('EMS_ROLE_SUPER_ADMIN') ? EMS_ROLE_SUPER_ADMIN : '-1');
    }

    /** حقن SAPI في الاختبارات حصرًا (محاكاة مسار ويب لاختبار رفض القناة). */
    public static $sapiOverride = null;

    /**
     * سياق خادمي صريح — القدرة الخامسة المحوكمة (K9-M2b · عقد §11 · 2026-07-08)
     * ───────────────────────────────────────────────────────────────────────
     * شركةٌ واحدة صريحة لكل سياق (البوابة تعزل به كسياق مستخدمٍ تمامًا — لا
     * سياق «يرى الكل»؛ الرؤية العابرة حكر forAllTenants بحارسها).
     *
     * **قناة محروسة لا تجاوز**: التفويض إلزامي بأحد مصدرين حصرًا:
     *   • CLI (PHP_SAPI === 'cli') — جدولة النظام؛
     *   • $systemAuth === true — مرّره المستدعي **فقط** بعد تحقق مفتاح cron
     *     بـ hash_equals ضد مفتاح .env غير الفارغ (مفتاح غائب/فارغ = fail-closed
     *     في حارس المستدعي نفسه — شرط المستخدم).
     * غيابهما = TenantGateException — مسار الويب المصادَق لا يمكنه تركيب
     * سياق نظامٍ (جلساته عبر fromSession حصرًا).
     *
     * **مسجَّل كنمط forAllTenants**: كل إنشاءٍ يقيَّد tenant_gate_system_context.
     */
    public static function forSystem($companyId, $userId = 0, $role = '', $systemAuth = false)
    {
        $sapi = self::$sapiOverride !== null ? self::$sapiOverride : PHP_SAPI;
        if ($sapi !== 'cli' && $systemAuth !== true) {
            if (function_exists('log_security_event')) {
                log_security_event('tenant_gate_system_context',
                    'REFUSED (no system authorization) | company=' . intval($companyId)
                    . ' sapi=' . $sapi . ' script=' . (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '?'));
            }
            require_once __DIR__ . '/TenantGateException.php';
            throw new TenantGateException('forSystem refused: system authorization required (CLI or verified cron key)');
        }
        if (function_exists('log_security_event')) {
            log_security_event('tenant_gate_system_context',
                'granted | company=' . intval($companyId) . ' sapi=' . $sapi
                . ' script=' . (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'cli'));
        }
        return new self($companyId, $userId, $role);
    }

    public function companyId()
    {
        return $this->companyId;
    }

    public function userId()
    {
        return $this->userId;
    }

    public function role()
    {
        return $this->role;
    }

    /* ══ تجميدُ الشركةِ الواحدة (FREEZE) ════════════════════════════
       ◆ **لا مديرَ أعلى بعد اليوم**: بابُ `admin/login.php` مُغلَقٌ بـ403،
         وصفرُ صفٍّ في `users` يحمل الدورَ '-1' (مقيسٌ). فالردُّ false
         دائمًا يجعلُ الواقعَ بنيويًّا لا مرهونًا ببيانات.
       ◆ **والأثرُ مقتصرٌ على مسارِ المنصّة**: `TenantDb::forAllTenants()` وكتابةُ
         `T_GLOBAL` المجمّدة — وكلاهما false أصلًا لمستخدمي المستأجِر،
         فلا تغيّرَ في أيِّ شاشةٍ. */
    public function isSuperAdmin()
    {
        return false;
    }

    public function hasTenant()
    {
        return $this->companyId > 0;
    }
}
