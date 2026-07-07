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

    /** السياق من الجلسة المصادَق عليها — المصدر الافتراضي الوحيد للويب. */
    public static function fromSession()
    {
        $u = isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : array();
        return new self(
            isset($u['company_id']) ? $u['company_id'] : 0,
            isset($u['id']) ? $u['id'] : 0,
            isset($u['role']) ? $u['role'] : ''
        );
    }

    /**
     * سياق خادمي صريح (cron / API token). يُستخدم فقط حيث تحققت هوية
     * المستأجر خادميًا — تمرير قيمةٍ من مدخلات العميل هنا خرقٌ للعقد.
     */
    public static function forSystem($companyId, $userId = 0, $role = '')
    {
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

    public function isSuperAdmin()
    {
        return $this->role === (defined('EMS_ROLE_SUPER_ADMIN') ? EMS_ROLE_SUPER_ADMIN : '-1');
    }

    public function hasTenant()
    {
        return $this->companyId > 0;
    }
}
