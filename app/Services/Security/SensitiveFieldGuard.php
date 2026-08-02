<?php
/**
 * SensitiveFieldGuard — حارس الحقول الحساسة (SEC-01 §10⑦ · §12 خدمة ⑪ · SEC-21)
 * ───────────────────────────────────────────────────────────────────────────
 * «يحجب أو يُخفي جزئيًّا في الخادم قبل الإرسال — ولا يُجلب الحقل لمن لا
 * يملكه» — يقرأ sensitive_field_policies، وكل قراءة لحقل سري تُسجَّل في
 * sensitive_read_log (Insert-only)، وقراءات المنح الحساس بمرجع منحها.
 */

namespace App\Services\Security;

class SensitiveFieldGuard
{
    /**
     * ترشيح صف قبل الإرسال: يزيل أو يقنّع الحقول الحساسة التي لا يملكها القارئ.
     * @param array $row الصف الخام
     * @param array $fieldMap اسم العمود في الصف => field_code في السياسات
     * @return array الصف بعد الترشيح — والحقل غير المملوك لا يُعاد أصلًا
     */
    public static function filterRow(\mysqli $conn, $row, array $fieldMap, $readerPersonId, $readerRole, $companyId, $subjectRef, $context = '')
    {
        foreach ($fieldMap as $col => $fieldCode) {
            if (!array_key_exists($col, $row)) { continue; }
            $pol = self::policy($conn, $fieldCode);
            if (!$pol) { continue; } // حقل غير مصنَّف — يمر
            $allowed = self::readerAllowed($conn, $pol, $readerPersonId, $readerRole, $companyId, $fieldCode);
            if (!$allowed['ok']) {
                unset($row[$col]); // لا يُجلب أصلًا — لا تعطيل ولا إخفاء محتوى
                continue;
            }
            if ($pol['masking_rule'] === 'partial') {
                $row[$col] = self::maskPartial((string) $row[$col]);
            }
            // كل قراءة لحقل سري تُسجَّل — بمرجع المنح إن كان هو المسوِّغ
            self::logRead($conn, $companyId, $readerPersonId, $fieldCode, $subjectRef, $allowed['grant_ref'], $context);
        }
        return $row;
    }

    /** أيقرأ هذا الشخص الحقل؟ — بالدور المسموح أو بمنح حساس نافذ. */
    public static function readerAllowed(\mysqli $conn, array $pol, $personId, $role, $companyId, $fieldCode)
    {
        $roles = json_decode((string) $pol['allowed_roles_json'], true);
        if (is_array($roles) && in_array((string) $role, array_map('strval', $roles), true)) {
            return array('ok' => true, 'grant_ref' => 'policy:' . $pol['field_code']);
        }
        if ((string) $role === '-1') {
            return array('ok' => true, 'grant_ref' => 'platform_admin');
        }
        // منح حساس نافذ لمجال التصنيف (§1.1-②)
        $stmt = $conn->prepare(
            "SELECT gr_id FROM sensitive_access_grants
              WHERE person_id = ? AND company_id = ? AND state = 'active' AND domain = ? LIMIT 1");
        $personId = intval($personId);
        $companyId = intval($companyId);
        $domain = self::domainOfClass($pol['classification']);
        $stmt->bind_param('iis', $personId, $companyId, $domain);
        $stmt->execute();
        $gr = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($gr) { return array('ok' => true, 'grant_ref' => 'GR-' . $gr['gr_id']); }
        return array('ok' => false, 'grant_ref' => null);
    }

    private static function domainOfClass($classification)
    {
        $map = array('payroll' => 'payroll', 'bank' => 'bank', 'medical' => 'medical',
            'personal' => 'medical', 'ownership' => 'ownership', 'pricing' => 'pricing');
        return isset($map[$classification]) ? $map[$classification] : 'medical';
    }

    /** إخفاء جزئي — كآخر أربعة أرقام من الحساب البنكي (§10⑦). */
    public static function maskPartial($value)
    {
        $len = mb_strlen($value);
        if ($len <= 4) { return $value; }
        return str_repeat('•', max(0, $len - 4)) . mb_substr($value, -4);
    }

    public static function policy(\mysqli $conn, $fieldCode)
    {
        $stmt = $conn->prepare('SELECT * FROM sensitive_field_policies WHERE field_code = ? LIMIT 1');
        $stmt->bind_param('s', $fieldCode);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    }

    /**
     * سطر سجل الاطلاع — Insert-only (§1.1-② سجل اطلاع المنح).
     * الجدول قائم من LEG-01 §9 (element_code · subject_type/id · result)
     * ووُسِّع بcompany_id/grant_ref/context — فيُكتب بمخططه الحي.
     */
    public static function logRead(\mysqli $conn, $companyId, $personId, $fieldCode, $subjectRef, $grantRef, $context = '')
    {
        $parts = explode(':', (string) $subjectRef, 2);
        $subjectType = $parts[0] !== '' ? $parts[0] : 'unknown';
        $subjectId = isset($parts[1]) ? $parts[1] : '0';
        $subjectIdNum = intval(preg_replace('/\D/', '', $subjectId)); // مراجع نصية → 0 والنص يبقى في context
        $ctx = $context !== '' ? $context . ' · ' . $subjectRef : $subjectRef;
        $stmt = $conn->prepare(
            "INSERT INTO sensitive_read_log (company_id, person_id, element_code, subject_type, subject_id, result, grant_ref, context)
             VALUES (?, ?, ?, ?, ?, 'allowed', ?, ?)");
        $companyId = intval($companyId);
        $personId = intval($personId);
        $stmt->bind_param('iississ', $companyId, $personId, $fieldCode, $subjectType, $subjectIdNum, $grantRef, $ctx);
        $stmt->execute();
        $stmt->close();
    }
}
