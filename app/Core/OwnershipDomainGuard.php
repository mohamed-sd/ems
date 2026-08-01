<?php
/**
 * حارس المجال المقيَّد للملكية — OwnershipDomainGuard (N-21 · FIN-01 §9-⑤)
 * ───────────────────────────────────────────────────────────────────────────
 * البوابة الوحيدة لقراءة بيانات ملكية المعدات:
 *   • fail-closed: بلا صلاحيةٍ **لا يُجلب الحقل أصلًا** — لا أن يُجلب ويُخفى.
 *   • الصلاحية فردية بأكوادها الثلاثة (لا بالعضوية في إدارة):
 *       ownership.owner_view      → المالك وهاتفه وعلاقته
 *       ownership.finance_terms   → شروط التمويل (تُستعمل من N-15 لاحقًا)
 *       ownership.purchase_value  → قيمة الشراء (أشدها — بمدة وسبب بنيويًّا)
 *   • **كل قراءةٍ تُسجَّل** في sensitive_read_log — والمنع يُسجَّل أيضًا.
 *   • التصفية في الخادم قبل الإرسال — filterColumns() للشاشات والتصدير والAPI.
 */

namespace App\Core;

class OwnershipDomainGuard
{
    const PERM_OWNER = 'ownership.owner_view';
    const PERM_TERMS = 'ownership.finance_terms';
    const PERM_VALUE = 'ownership.purchase_value';

    /** الأعمدة المحجوبة لكل كود — تُنزع من أي صفٍّ يمر عبر filterColumns */
    const GUARDED_COLUMNS = array(
        'actual_owner_name' => self::PERM_OWNER,
        'owner_type' => self::PERM_OWNER,
        'owner_phone' => self::PERM_OWNER,
        'owner_supplier_relation' => self::PERM_OWNER,
        'financier_entity_id' => self::PERM_TERMS,
        'financing_terms' => self::PERM_TERMS,
        'purchase_value' => self::PERM_VALUE,
        'purchase_currency' => self::PERM_VALUE,
        'acquisition_cost' => self::PERM_VALUE,
    );

    /** هل للمستخدم منحةٌ نافذةٌ لهذا الكود؟ fail-closed. */
    public static function hasGrant(\mysqli $conn, $companyId, $personId, $permCode)
    {
        $stmt = $conn->prepare(
            "SELECT 1 FROM ownership_access_grants
              WHERE company_id = ? AND person_id = ? AND permission_code = ? AND state = 'active'
                AND (valid_from IS NULL OR valid_from <= CURDATE())
                AND (valid_to IS NULL OR valid_to >= CURDATE()) LIMIT 1");
        if (!$stmt) { return false; }
        $companyId = intval($companyId); $personId = intval($personId); $permCode = (string) $permCode;
        $stmt->bind_param('iis', $companyId, $personId, $permCode);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();
        return $ok;
    }

    /**
     * قراءة سجل ملكية معدةٍ بصلاحيات القارئ — **الحقول غير المصرَّح بها لا
     * تُجلب أصلًا**، وكل قراءةٍ (أو منعٍ) بسطر اطّلاع.
     * @return array{allowed:array,denied:array}
     */
    public static function fetchEquipmentOwnership(\mysqli $conn, $companyId, $personId, $equipmentId)
    {
        $out = array('allowed' => array(), 'denied' => array());
        $companyId = intval($companyId); $personId = intval($personId); $equipmentId = intval($equipmentId);

        $canOwner = self::hasGrant($conn, $companyId, $personId, self::PERM_OWNER);
        $canValue = self::hasGrant($conn, $companyId, $personId, self::PERM_VALUE);

        $cols = array();
        if ($canOwner) { $cols = array_merge($cols, array('actual_owner_name', 'owner_type', 'owner_phone', 'owner_supplier_relation')); }
        if ($canValue) { $cols = array_merge($cols, array('purchase_value', 'purchase_currency')); }

        if (!$canOwner) { $out['denied'][] = self::PERM_OWNER; self::logRead($conn, $personId, 'ownership.owner', 'equipment', $equipmentId, 'denied'); }
        if (!$canValue) { $out['denied'][] = self::PERM_VALUE; }

        if (empty($cols)) {
            return $out; // لا جلبَ أصلًا — fail-closed
        }
        $sql = 'SELECT `' . implode('`,`', $cols) . '` FROM equipment_ownership_registry WHERE company_id = ? AND equipment_id = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $companyId, $equipmentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $out['allowed'] = $row;
            if ($canOwner) { self::logRead($conn, $personId, 'ownership.owner', 'equipment', $equipmentId, 'allowed'); }
            if ($canValue) { self::logRead($conn, $personId, 'ownership.value', 'equipment', $equipmentId, 'allowed'); }
        }
        return $out;
    }

    /**
     * التصفية الخادمية: تنزع الأعمدة المحجوبة من صفوفٍ قبل إرسالها للشاشة
     * أو التصدير أو الAPI — **النزع قبل الإرسال لا الإخفاء بعده**.
     */
    public static function filterColumns(\mysqli $conn, $companyId, $personId, array $rows)
    {
        $grants = array();
        foreach (array(self::PERM_OWNER, self::PERM_TERMS, self::PERM_VALUE) as $p) {
            $grants[$p] = self::hasGrant($conn, $companyId, $personId, $p);
        }
        $filtered = array();
        foreach ($rows as $row) {
            foreach (self::GUARDED_COLUMNS as $col => $perm) {
                if (array_key_exists($col, $row) && !$grants[$perm]) {
                    unset($row[$col]);
                }
            }
            $filtered[] = $row;
        }
        return $filtered;
    }

    /** منح صلاحيةٍ فردية — قيمة الشراء تُرفض بلا سببٍ ومدة (CHECK بنيوي + رسالة). */
    public static function grant(\mysqli $conn, $companyId, $personId, $permCode, $grantedBy, $reason = null, $from = null, $to = null)
    {
        if ($permCode === self::PERM_VALUE && ($reason === null || $from === null || $to === null)) {
            return array('ok' => false, 'code' => 422, 'reason' => 'صلاحية قيمة الشراء أشد الثلاثة — لا تُمنح إلا بسببٍ ومدةٍ مكتوبين');
        }
        $stmt = $conn->prepare(
            'INSERT INTO ownership_access_grants (company_id, person_id, permission_code, reason, valid_from, valid_to, granted_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)');
        $companyId = intval($companyId); $personId = intval($personId); $grantedBy = intval($grantedBy);
        $stmt->bind_param('iissssi', $companyId, $personId, $permCode, $reason, $from, $to, $grantedBy);
        if (!$stmt->execute()) {
            $err = $stmt->error; $stmt->close();
            return array('ok' => false, 'code' => 422, 'reason' => 'رُفض المنح: ' . $err);
        }
        $id = $stmt->insert_id; $stmt->close();
        return array('ok' => true, 'code' => 201, 'grant_id' => $id, 'reason' => 'مُنحت ' . $permCode);
    }

    /** سطر اطّلاع — Insert-only، وفشله لا يوقف القراءة لكنه يُسجَّل. */
    private static function logRead(\mysqli $conn, $personId, $elementCode, $subjectType, $subjectId, $result)
    {
        try {
            $stmt = $conn->prepare(
                'INSERT INTO sensitive_read_log (person_id, element_code, subject_type, subject_id, ip, result)
                 VALUES (?, ?, ?, ?, ?, ?)');
            $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'cli';
            $personId = intval($personId); $subjectId = intval($subjectId);
            $stmt->bind_param('ississ', $personId, $elementCode, $subjectType, $subjectId, $ip, $result);
            $stmt->execute();
            $stmt->close();
        } catch (\Throwable $t) {
            error_log('[OwnershipDomainGuard] read-log failed: ' . $t->getMessage());
        }
    }
}
