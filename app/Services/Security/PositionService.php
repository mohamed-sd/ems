<?php
/**
 * PositionService — معالج الإحدى عشرة خطوة (SEC-01 §10.1 · §12 خدمة ② · SEC-17)
 * ───────────────────────────────────────────────────────────────────────────
 * «يُعدُّ بها موظف جديد في دقائق»: الهوية والعلاقة ← العائلة ← المستوى ←
 * المسمّى ← الجهة ← المدير والتبعيتان ← النطاق ← التكليف ← معاينة الصلاحيات
 * بمصادرها ← الإرسال للموافقة ← التفعيل الآلي بعد اكتمالها.
 *
 * Validation: نقل بلا إنهاء القديم → 422 · ولا تفعيل إلا باكتمال الموافقات ·
 * ومنع تداخل فترتين لنفس (المسمّى × النطاق) · ولا صف بلا نطاق.
 */

namespace App\Services\Security;

require_once __DIR__ . '/PermissionResolver.php';

class PositionService
{
    /**
     * الخطوة ⑨ — معاينة الصلاحيات المقترحة بمصادرها قبل الإرسال.
     */
    public static function preview(\mysqli $conn, array $d)
    {
        $sources = array();
        foreach (array('relation' => $d['relation_code'], 'family' => $d['family_code'],
                       'level' => $d['level_code'], 'title' => $d['title_code']) as $kind => $key) {
            foreach (PermissionResolver::publishedContent($conn, $kind, (string) $key, PermissionResolver::dbNow($conn)) as $t) {
                $sources[] = array('kind' => $kind, 'ref' => $key,
                    'permission' => $t['permission_code'], 'effect' => $t['effect'],
                    'scope' => $t['scope_rule'] ?: ($d['scope_type'] . ':' . $d['scope_id']));
            }
        }
        return array('proposed' => $sources, 'count' => count($sources));
    }

    /**
     * الخطوات ①→⑩ — إنشاء المركز معلَّقًا وفتح دورة الموافقة.
     * @return array{ok:bool, code:int, reason:string, p_id:int, req_id:int}
     */
    public static function submit(\mysqli $conn, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'p_id' => 0, 'req_id' => 0);
        foreach (array('person_id', 'company_id', 'relation_code', 'family_code', 'level_code', 'title_code', 'valid_from') as $k) {
            if (empty($d[$k])) { $out['code'] = 422; $out['reason'] = "الطبقة {$k} إلزامية — سبع طبقات لا حقل واحد"; return $out; }
        }
        if (empty($d['scope_type']) || !isset($d['scope_id'])) {
            $out['code'] = 422; $out['reason'] = 'لا مركز بلا نطاق — الصلاحية بلا نطاق مرفوضة بنيويًّا';
            return $out;
        }
        $pid = intval($d['person_id']); $co = intval($d['company_id']);

        // منع تداخل فترتين لنفس (المسمّى × النطاق) — فمركزان مشروعان يبدآن معًا مقبولان
        $stmt = $conn->prepare(
            "SELECT p_id FROM person_positions
              WHERE person_id = ? AND company_id = ? AND title_code = ? AND scope_type = ? AND scope_id = ?
                AND state IN ('active','suspended')
                AND (valid_to IS NULL OR valid_to >= ?) AND valid_from <= COALESCE(?, '9999-12-31') LIMIT 1");
        $vt = isset($d['valid_to']) && $d['valid_to'] !== '' ? $d['valid_to'] : null;
        $sid = intval($d['scope_id']);
        $stmt->bind_param('iisssss', $pid, $co, $d['title_code'], $d['scope_type'], $sid, $d['valid_from'], $vt);
        $stmt->execute();
        $overlap = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($overlap) {
            $out['code'] = 409;
            $out['reason'] = 'تداخل فترتين للمسمّى نفسه في النطاق نفسه — المركز #' . $overlap['p_id'];
            return $out;
        }

        // نقل: مركز أولي نشط قائم في جهة أخرى — بلا إنهاء القديم → 422
        if (!empty($d['is_primary'])) {
            $r = $conn->query("SELECT p_id, org_unit_id FROM person_positions
                                WHERE person_id = {$pid} AND company_id = {$co} AND is_primary = 1
                                  AND state = 'active' LIMIT 1");
            $prim = $r ? $r->fetch_assoc() : null;
            if ($prim && empty($d['end_previous_p_id'])) {
                $out['code'] = 422;
                $out['reason'] = 'نقل بلا إنهاء القديم — المركز الأولي #' . $prim['p_id'] . ' نشط: مرّر end_previous_p_id (S6: القديمة تسقط فورًا)';
                return $out;
            }
        }

        $conn->begin_transaction();
        try {
            // إنهاء القديم في اللحظة نفسها (S6)
            if (!empty($d['end_previous_p_id'])) {
                $old = intval($d['end_previous_p_id']);
                $conn->query("UPDATE person_positions SET state = 'ended', valid_to = CURDATE()
                              WHERE p_id = {$old} AND person_id = {$pid}");
                self::audit($conn, $co, $pid, 'revoked', 'position:' . $old, null, 'نقل — صلاحيات الإدارة القديمة تسقط في اللحظة نفسها', intval($d['created_by'] ?? 0));
            }
            // المركز معلَّق حتى اكتمال الموافقات — «لا تفعيل إلا باكتمالها»
            $stmt = $conn->prepare(
                "INSERT INTO person_positions (person_id, company_id, relation_code, family_code, level_code,
                    title_code, org_unit_id, manager_person_id, scope_type, scope_id, is_primary,
                    valid_from, valid_to, state)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'suspended')");
            $unit = isset($d['org_unit_id']) ? intval($d['org_unit_id']) : null;
            $mgr = isset($d['manager_person_id']) ? intval($d['manager_person_id']) : null;
            $isP = !empty($d['is_primary']) ? 1 : 0;
            $stmt->bind_param('iissssiisiiss', $pid, $co, $d['relation_code'], $d['family_code'],
                $d['level_code'], $d['title_code'], $unit, $mgr, $d['scope_type'], $sid, $isP,
                $d['valid_from'], $vt);
            if (!$stmt->execute()) {
                $errno = $conn->errno;
                $stmt->close();
                $conn->rollback();
                $out['code'] = $errno === 1062 ? 409 : 422;
                $out['reason'] = $errno === 1062 ? 'مركز مطابق قائم' : 'بيانات مرفوضة (' . $errno . ')';
                return $out;
            }
            $pId = intval($conn->insert_id);
            $stmt->close();

            // دورة الموافقة بحسب المستوى (§8): change_kind من level_code
            $kindMap = array('lvl_supervisor' => 'supervisor', 'lvl_officer' => 'supervisor',
                'lvl_section_mgr' => 'section_mgr', 'lvl_dept_mgr' => 'dept_mgr_or_high',
                'lvl_executive' => 'dept_mgr_or_high');
            $kind = isset($kindMap[$d['level_code']]) ? $kindMap[$d['level_code']] : 'within_role';
            $risk = $kind === 'dept_mgr_or_high' ? 'high' : ($kind === 'within_role' ? 'low' : 'medium');
            $stmt = $conn->prepare(
                "INSERT INTO permission_change_requests (company_id, person_id, change_kind, to_json, reason, risk_level, state, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
            $to = json_encode(array('position_id' => $pId, 'title' => $d['title_code'],
                'level' => $d['level_code'], 'scope' => $d['scope_type'] . ':' . $sid), JSON_UNESCAPED_UNICODE);
            $reason = isset($d['reason']) ? (string) $d['reason'] : 'إعداد موظف — معالج الإحدى عشرة خطوة';
            $by = intval($d['created_by'] ?? 0);
            $stmt->bind_param('iissssi', $co, $pid, $kind, $to, $reason, $risk, $by);
            $stmt->execute();
            $reqId = intval($conn->insert_id);
            $stmt->close();

            // خطوات الموافقة بقواعدها الديناميكية (§8 — بدرجة المخاطرة لا بمسار واحد)
            $steps = self::stepsForKind($kind);
            $seq = 0;
            foreach ($steps as $rule) {
                $seq++;
                $stmt = $conn->prepare(
                    'INSERT INTO permission_approval_steps (req_id, seq_no, approver_rule, mandatory) VALUES (?, ?, ?, 1)');
                $stmt->bind_param('iis', $reqId, $seq, $rule);
                $stmt->execute();
                $stmt->close();
            }
            $conn->commit();
            $out['ok'] = true; $out['code'] = 201; $out['p_id'] = $pId; $out['req_id'] = $reqId;
            $out['reason'] = 'أُرسل للموافقة (' . count($steps) . ' خطوات بدرجة المخاطرة) — ولا تفعيل قبل اكتمالها';
            return $out;
        } catch (\Throwable $e) {
            $conn->rollback();
            $out['code'] = 500; $out['reason'] = 'استثناء: ' . $e->getMessage();
            return $out;
        }
    }

    /** مصفوفة §8: خطوات الموافقة بحسب النوع — والرباعية لا تُختصر. */
    public static function stepsForKind($kind)
    {
        switch ($kind) {
            case 'within_role':
                return array('requester_department_manager'); // + مدير الصلاحيات ينفذ
            case 'supervisor':
                return array('requester_department_manager', 'hr', 'functional_owner');
            case 'section_mgr':
                return array('requester_department_manager', 'hr', 'functional_owner');
            case 'dept_mgr_or_high':
                // أربع موافقات لا تُختصر (§8-④ · S4/S19)
                return array('hr', 'functional_owner', 'finance_owner_if_financial', 'executive');
            default:
                return array('requester_department_manager');
        }
    }

    /**
     * الخطوة ⑪ — التفعيل الآلي بعد اكتمال الموافقات الإلزامية.
     */
    public static function activate(\mysqli $conn, $reqId, $executedBy = 0)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $reqId = intval($reqId);
        $req = $conn->query("SELECT * FROM permission_change_requests WHERE req_id = {$reqId}")->fetch_assoc();
        if (!$req) { $out['code'] = 404; $out['reason'] = 'طلب غير موجود'; return $out; }
        $res = $conn->query("SELECT COUNT(*) c FROM permission_approval_steps
                              WHERE req_id = {$reqId} AND mandatory = 1 AND (decision IS NULL OR decision <> 'approve')");
        $remaining = intval($res->fetch_assoc()['c']);
        if ($remaining > 0) {
            $out['code'] = 409;
            $out['reason'] = 'لا تفعيل إلا باكتمال الموافقات — بقي ' . $remaining;
            return $out;
        }
        $to = json_decode((string) $req['to_json'], true);
        $pId = isset($to['position_id']) ? intval($to['position_id']) : 0;
        $conn->begin_transaction();
        try {
            if ($pId > 0) {
                $conn->query("UPDATE person_positions SET state = 'active' WHERE p_id = {$pId}");
            }
            $conn->query("UPDATE permission_change_requests SET state = 'applied' WHERE req_id = {$reqId}");
            self::audit($conn, intval($req['company_id']), intval($req['person_id']), 'granted',
                'position:' . $pId, $req['to_json'], 'التفعيل الآلي بعد اكتمال الموافقات (⑪)', intval($executedBy));
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollback();
            $out['code'] = 500; $out['reason'] = $e->getMessage();
            return $out;
        }
        // إعادة بناء المشتق — عند أي تغيير في مصدر
        PermissionResolver::rebuild($conn, intval($req['person_id']), intval($req['company_id']));
        $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'فُعِّل المركز وأُعيد بناء المشتق';
        return $out;
    }

    /** سطر سجل تغيير الصلاحيات المستقل (Insert-only). */
    public static function audit(\mysqli $conn, $co, $personId, $eventType, $ref, $afterJson, $reason, $by)
    {
        $foundingOn = 0;
        $r = $conn->query("SELECT enabled FROM founding_mode WHERE enabled = 1 LIMIT 1");
        if ($r && $r->num_rows > 0) { $foundingOn = 1; }
        $stmt = $conn->prepare(
            "INSERT INTO permission_audit_events (company_id, event_type, person_id, permission_code,
                after_json, executed_by, reason, source, founding_mode)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'template', ?)");
        $co = intval($co); $personId = intval($personId); $by = intval($by);
        $stmt->bind_param('isissisi', $co, $eventType, $personId, $ref, $afterJson, $by, $reason, $foundingOn);
        $stmt->execute();
        $stmt->close();
    }
}
