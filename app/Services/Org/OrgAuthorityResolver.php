<?php
/**
 * OrgAuthorityResolver — حلّال السلطة التنظيمية (ORG-01 §7 خدمة ①)
 * ───────────────────────────────────────────────────────────────────────────
 * Inputs: الشخص والنطاق والتاريخ · Output: تكليفاته النافذة وصلاحياتها وسقوفها.
 * يُستدعى من حارس التفويض (AuthorityGuard · LEG-01 §9) قبل كل اعتماد — خلف علم
 * `EMS_ORG_AUTHORITY` (off·monitor·enforce).
 *
 * الأحكام (ORG-01 §2 · §7.1):
 *   • تكليف منتهٍ → 403 مسجَّلة في guard_denials (السقوط الآلي — O2).
 *   • نطاق خارج التكليف → 403 (O3).
 *   • النائب يعمل بسقفه ومدته عند غياب المكلَّف (O4 — عبر DeputyResolver).
 *   • النطاقان يتقاطعان ولا يتحدان: مشروع التكليف يغطي مواقعه (sites.project_id)
 *     ولا يتوسع scope_limit_json فوق نطاق التكليف — يضيّقه فقط.
 */

namespace App\Services\Org;

class OrgAuthorityResolver
{
    /**
     * التكليفات النافذة للشخص في لحظة وتاريخ.
     * @return array صفوف: asg + type + unit + capabilities[]
     */
    public static function resolve(\mysqli $conn, $personId, $companyId, $date = null)
    {
        $personId = intval($personId);
        $companyId = intval($companyId);
        $stmt = $conn->prepare(
            "SELECT a.asg_id, a.person_id, a.assignment_type_code, t.name_ar AS type_name,
                    t.level, a.org_unit_id, u.unit_code, a.scope_type, a.scope_id,
                    a.valid_from, a.valid_to, a.deputy_person_id, a.state
               FROM org_assignments a
               JOIN org_assignment_types t ON t.type_code = a.assignment_type_code
               JOIN org_units u ON u.unit_id = a.org_unit_id
              WHERE a.company_id = ? AND a.person_id = ? AND a.state = 'active'
                AND ? BETWEEN a.valid_from AND a.valid_to");
        $d = $date ?: self::dbToday($conn);
        $stmt->bind_param('iis', $companyId, $personId, $d);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as &$r) {
            $r['capabilities'] = self::capabilities($conn, intval($r['asg_id']));
        }
        unset($r);
        return $rows;
    }

    /**
     * أيملك الشخص سلطةً نافذةً تغطي النطاق؟ — قلب الحارس.
     * @param array $opt {scope_type?, scope_id?, capability?, amount?, date?}
     * @return array{ok:bool, code:int, reason:string, asg_id:?int, amount_cap:?float}
     */
    public static function can(\mysqli $conn, $personId, $companyId, array $opt = array())
    {
        $out = array('ok' => false, 'code' => 403, 'reason' => '', 'asg_id' => null, 'amount_cap' => null);
        $date = isset($opt['date']) ? $opt['date'] : self::dbToday($conn);
        $active = self::resolve($conn, $personId, $companyId, $date);

        if (!$active) {
            // أنافذٌ سابقًا وانتهى؟ فالسبب «السقوط الآلي» لا «لا تكليف»
            $stmt = $conn->prepare(
                "SELECT COUNT(*) c FROM org_assignments
                  WHERE company_id = ? AND person_id = ? AND (state = 'ended' OR valid_to < ?)");
            $stmt->bind_param('iis', $companyId, $personId, $date);
            $stmt->execute();
            $had = intval($stmt->get_result()->fetch_assoc()['c']);
            $stmt->close();
            $out['reason'] = $had > 0
                ? 'التكليف منته — الصلاحية سقطت آليا بانتهاء مدته (ORG-01 §2)'
                : 'لا تكليف تنظيميا نافذا لهذا الشخص';
            self::deny($conn, $companyId, $personId, $out['reason'], $opt);
            return $out;
        }

        $needScope = isset($opt['scope_type'], $opt['scope_id']) && $opt['scope_type'] !== null;
        foreach ($active as $a) {
            if ($needScope && !self::scopeCovers($conn, $a, (string) $opt['scope_type'], intval($opt['scope_id']))) {
                continue;
            }
            $cap = null;
            if (isset($opt['capability']) && $opt['capability'] !== null) {
                $cap = self::findCapability($a['capabilities'], (string) $opt['capability']);
                if ($cap === null) { continue; }
                if ($cap['amount_cap'] !== null && isset($opt['amount']) && $opt['amount'] !== null
                    && floatval($opt['amount']) > floatval($cap['amount_cap'])) {
                    $out['code'] = 409;
                    $out['reason'] = 'فوق سقف التكليف — المتاح ' . number_format(floatval($cap['amount_cap']), 2)
                        . ' ' . ($cap['currency'] ?: '') . '؛ يرفع للمستوى الأعلى';
                    $out['asg_id'] = intval($a['asg_id']);
                    return $out;
                }
            }
            $out['ok'] = true;
            $out['code'] = 200;
            $out['asg_id'] = intval($a['asg_id']);
            $out['amount_cap'] = $cap !== null && $cap['amount_cap'] !== null ? floatval($cap['amount_cap']) : null;
            $out['reason'] = 'سلطة نافذة بمرجع التكليف #' . $a['asg_id'];
            return $out;
        }

        $out['reason'] = $needScope
            ? 'النطاق خارج التكليف — التكليف لا يغطي هذا الموقع أو المشروع (ORG-01 §7.1 O3)'
            : 'لا تكليف يحمل الصلاحية المطلوبة';
        self::deny($conn, $companyId, $personId, $out['reason'], $opt);
        return $out;
    }

    /** أيغطي نطاقُ التكليف النطاقَ المطلوب؟ — يتقاطعان ولا يتحدان. */
    public static function scopeCovers(\mysqli $conn, array $asg, $scopeType, $scopeId)
    {
        $aType = (string) $asg['scope_type'];
        $aId = intval($asg['scope_id']);
        if ($aType === $scopeType && $aId === intval($scopeId)) { return true; }
        // مشروع التكليف يغطي مواقعه
        if ($aType === 'project' && $scopeType === 'site') {
            $stmt = $conn->prepare('SELECT project_id FROM sites WHERE id = ? LIMIT 1');
            $sid = intval($scopeId);
            $stmt->bind_param('i', $sid);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row && intval($row['project_id']) === $aId;
        }
        // مجموعة مواقع بscope_id=0: كل مواقع الشركة (تكليف مركزي)
        if ($aType === 'site_group' && $aId === 0) { return true; }
        return false;
    }

    /** صلاحيات تكليف. */
    public static function capabilities(\mysqli $conn, $asgId)
    {
        $stmt = $conn->prepare(
            'SELECT capability_code, scope_limit_json, amount_cap, currency
               FROM assignment_capabilities WHERE asg_id = ?');
        $asgId = intval($asgId);
        $stmt->bind_param('i', $asgId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    private static function findCapability(array $caps, $code)
    {
        foreach ($caps as $c) {
            if ($c['capability_code'] === $code) { return $c; }
        }
        return null;
    }

    /** تسجيل المنع — guard_denials (GOV-01 §9) بكود org_authority. */
    private static function deny(\mysqli $conn, $companyId, $personId, $reason, array $opt)
    {
        $ref = isset($opt['attempted_ref']) ? (string) $opt['attempted_ref']
             : ((isset($opt['scope_type']) ? $opt['scope_type'] . ':' . (isset($opt['scope_id']) ? $opt['scope_id'] : '') : ''));
        $stmt = $conn->prepare(
            "INSERT INTO guard_denials (company_id, guard_code, person_id, attempted_ref, reason_code)
             VALUES (?, 'org_authority', ?, ?, ?)");
        $reasonCode = mb_substr($reason, 0, 78);
        $companyId = intval($companyId);
        $personId = intval($personId);
        $stmt->bind_param('iiss', $companyId, $personId, $ref, $reasonCode);
        $stmt->execute();
        $stmt->close();
    }

    /** تاريخ اليوم بساعة القاعدة لا PHP (فارق ساعة معلوم). */
    public static function dbToday(\mysqli $conn)
    {
        $r = $conn->query('SELECT CURDATE() d')->fetch_assoc();
        return $r['d'];
    }
}
