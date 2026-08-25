<?php
/**
 * SegregationOfDutiesGuard — فصل الواجبات (SEC-01 §5 · §12 خدمة ⑥ · SEC-20)
 * ───────────────────────────────────────────────────────────────────────────
 * «يُفحص عند حساب الصلاحية لا بعد الوقوع»: إن أدى قالب أو استثناء إلى اجتماع
 * طرفَي تعارضٍ في شخص واحد → 409 مع عرض التعارض — ولا يُطبَّق.
 * والاستثناء بموافقة المدير التنفيذي ورقابة تعويضية معلنة — ولا يُمنح صامتًا.
 */

namespace App\Services\Security;

require_once __DIR__ . '/PermissionResolver.php';

class SegregationOfDutiesGuard
{
    /**
     * أيُنتج ضمُّ الصلاحيات المقترحة اجتماعَ طرفَي تعارض؟
     * @param array $proposedCodes أكواد الصلاحيات المقترح منحها
     * @return array{ok:bool, code:int, conflicts:array, reason:string}
     */
    public static function check(\mysqli $conn, $personId, $companyId, array $proposedCodes, $withCompensatingControl = false)
    {
        $out = array('ok' => true, 'code' => 200, 'conflicts' => array(), 'reason' => '');
        $personId = intval($personId);
        $companyId = intval($companyId);

        // ما يملكه فعلًا (المشتق الحالي) + المقترح
        $held = array();
        $res = $conn->query("SELECT DISTINCT permission_code FROM effective_permissions
                              WHERE person_id = {$personId} AND company_id = {$companyId}");
        while ($res && ($x = $res->fetch_assoc())) { $held[$x['permission_code']] = true; }
        foreach ($proposedCodes as $c) { $held[(string) $c] = true; }

        $res = $conn->query("SELECT conflict_code, name_ar, permission_a, permission_b, severity, compensating_control
                              FROM sod_conflicts WHERE active = 1");
        while ($res && ($row = $res->fetch_assoc())) {
            if (self::holdsAll($held, $row['permission_a']) && self::holdsAll($held, $row['permission_b'])) {
                $out['conflicts'][] = $row;
            }
        }

        if ($out['conflicts']) {
            // الاستثناء المشروط: رقابة تعويضية معلنة لتعارضات لها ضابط — ولا يشمل ما ضابطه NULL
            if ($withCompensatingControl) {
                $blocked = array();
                foreach ($out['conflicts'] as $c) {
                    if ($c['compensating_control'] === null) { $blocked[] = $c; }
                }
                if (!$blocked) {
                    $out['reason'] = 'تعارض مسموح استثناء بموافقة التنفيذي ورقابة تعويضية معلنة — ولا يمنح صامتا';
                    return $out; // ok=true مع عرض التعارضات للتوثيق
                }
                $out['conflicts'] = $blocked;
            }
            $out['ok'] = false;
            $out['code'] = 409;
            $names = array();
            foreach ($out['conflicts'] as $c) { $names[] = $c['name_ar']; }
            $out['reason'] = '409 تعارض واجبات — ' . implode(' · ', $names) . ' — يعرض ولا يطبق';
        }
        return $out;
    }

    /** أيحمل كلَّ أكواد الطرف (مفصولة بـ+)؟ */
    private static function holdsAll(array $held, $side)
    {
        foreach (explode('+', (string) $side) as $code) {
            $code = trim($code);
            if ($code !== '' && !isset($held[$code])) { return false; }
        }
        return true;
    }
}
