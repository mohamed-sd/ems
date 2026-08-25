<?php
/**
 * AssignmentService — إنشاء التكليف التنظيمي وتعديله ونقله وإنهاؤه (ORG-01 §7 خدمة ②)
 * ───────────────────────────────────────────────────────────────────────────
 * «التكليف سجل تنظيمي رسمي لا تعديل صلاحية يدوي» (ORG-01 §2):
 *   • بلا مدة → 422 · بلا نطاق → 422 (لا تكليف مفتوح المدة أو النطاق).
 *   • مصدر القرار ليس مدير التشغيل ولا المدير التنفيذي → 403.
 *   • تداخل تكليفين لنوع ونطاق واحد → 409 (فوق UQ القاعدة).
 *   • الموقعي بخط تبعية واحد → 422 — لا بد من التشغيلي والفني معًا (§2⑦).
 *   • كل فعل سطر Insert-only في assignment_audit — والأحداث المسماة في الوثيقة
 *     (AssignmentCreated·AssignmentEnded·DeputyActivated) تُمثَّل بأفعال السجل
 *     لأنها وقائع تنظيمية لا حقائق مالية تدخل الجذر المحايد.
 */

namespace App\Services\Org;

require_once __DIR__ . '/OrgAuthorityResolver.php';

class AssignmentService
{
    /** أدوار مصدر القرار المخوَّلة: مدير التشغيل (1) والأعلى (-1). */
    private static function deciderAuthorized(\mysqli $conn, $personId)
    {
        $stmt = $conn->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $personId = intval($personId);
        $stmt->bind_param('i', $personId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) { return false; }
        return in_array((string) $row['role'], array('1', '-1'), true);
    }

    /**
     * إنشاء تكليف.
     * @param array $d {company_id, person_id, assignment_type_code, org_unit_id,
     *   scope_type, scope_id, valid_from, valid_to, decided_by_person_id,
     *   decision_ref?, deputy_person_id?, capabilities? [{code,scope_limit_json?,amount_cap?,currency?}],
     *   reporting_lines? [{line_type, reports_to_assignment_id}]}
     * @return array{ok:bool, code:int, reason:string, asg_id:int}
     */
    public static function create(\mysqli $conn, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'asg_id' => 0);

        // ① الإلزاميات — 422
        foreach (array('valid_from', 'valid_to') as $k) {
            if (empty($d[$k])) {
                $out['code'] = 422; $out['reason'] = 'لا تكليف مفتوح المدة — تاريخا البداية والنهاية إلزاميان (§2④)';
                return $out;
            }
        }
        if (empty($d['scope_type']) || !isset($d['scope_id'])) {
            $out['code'] = 422; $out['reason'] = 'لا تكليف مفتوح النطاق — النطاق إلزامي (§2③)';
            return $out;
        }

        // ② مصدر القرار — 403
        if (empty($d['decided_by_person_id']) || !self::deciderAuthorized($conn, $d['decided_by_person_id'])) {
            $out['code'] = 403; $out['reason'] = 'مصدر القرار ليس مدير التشغيل ولا المدير التنفيذي — مرفوض (§2②)';
            return $out;
        }

        // ③ نوع التكليف والخطان
        $type = self::typeOf($conn, (string) $d['assignment_type_code']);
        if (!$type) {
            $out['code'] = 422; $out['reason'] = 'نوع تكليف غير معرف — يضاف صفا في org_assignment_types لا كودا';
            return $out;
        }
        $lines = isset($d['reporting_lines']) && is_array($d['reporting_lines']) ? $d['reporting_lines'] : array();
        if (intval($type['requires_functional_line']) === 1) {
            $kinds = array();
            foreach ($lines as $l) { $kinds[(string) $l['line_type']] = true; }
            if (!isset($kinds['operational']) || !isset($kinds['functional'])) {
                $out['code'] = 422;
                $out['reason'] = 'التكليف الموقعي بخط تبعية واحد مرفوض — لا بد من التشغيلي والفني معا (§2⑦)';
                return $out;
            }
        }

        // ④ تداخل تكليفين لنوع ونطاق واحد — 409
        $stmt = $conn->prepare(
            "SELECT asg_id FROM org_assignments
              WHERE company_id = ? AND assignment_type_code = ? AND scope_type = ? AND scope_id = ?
                AND state IN ('active','suspended')
                AND NOT (valid_to < ? OR valid_from > ?) LIMIT 1");
        $co = intval($d['company_id']); $tc = (string) $d['assignment_type_code'];
        $st = (string) $d['scope_type']; $sid = intval($d['scope_id']);
        $stmt->bind_param('ississ', $co, $tc, $st, $sid, $d['valid_from'], $d['valid_to']);
        $stmt->execute();
        $overlap = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($overlap) {
            $out['code'] = 409;
            $out['reason'] = 'تداخل مع التكليف #' . $overlap['asg_id'] . ' للنوع والنطاق نفسيهما — تكليف واحد في أي لحظة';
            return $out;
        }

        // ⑤ الإنشاء ذريًّا
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "INSERT INTO org_assignments (company_id, person_id, assignment_type_code, org_unit_id,
                    scope_type, scope_id, valid_from, valid_to, decided_by_person_id, decision_ref,
                    deputy_person_id, state)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $pid = intval($d['person_id']); $unit = intval($d['org_unit_id']);
            $dec = intval($d['decided_by_person_id']);
            $ref = isset($d['decision_ref']) ? (string) $d['decision_ref'] : null;
            $dep = isset($d['deputy_person_id']) && $d['deputy_person_id'] !== null ? intval($d['deputy_person_id']) : null;
            $stmt->bind_param('iisissssisi', $co, $pid, $tc, $unit, $st, $sid,
                $d['valid_from'], $d['valid_to'], $dec, $ref, $dep);
            if (!$stmt->execute()) {
                $errno = $conn->errno;
                $stmt->close();
                $conn->rollback();
                if ($errno === 1062) {
                    $out['code'] = 409;
                    $out['reason'] = 'مدير حركة نشط قائم لهذا الموقع — واحد لكل موقع في أي لحظة (O1)';
                    return $out;
                }
                $out['code'] = 500; $out['reason'] = 'فشل الإدراج (' . $errno . ')';
                return $out;
            }
            $asgId = intval($conn->insert_id);
            $stmt->close();

            // الصلاحيات: الممرَّرة أو افتراضيات النوع
            $caps = isset($d['capabilities']) && is_array($d['capabilities']) ? $d['capabilities'] : array();
            if (!$caps && !empty($type['default_capabilities_json'])) {
                $defaults = json_decode((string) $type['default_capabilities_json'], true);
                if (is_array($defaults)) {
                    foreach ($defaults as $code) { $caps[] = array('code' => $code); }
                }
            }
            foreach ($caps as $c) {
                $stmt = $conn->prepare(
                    'INSERT INTO assignment_capabilities (asg_id, capability_code, scope_limit_json, amount_cap, currency)
                     VALUES (?, ?, ?, ?, ?)');
                $code = (string) $c['code'];
                $slj = isset($c['scope_limit_json']) ? (is_string($c['scope_limit_json']) ? $c['scope_limit_json'] : json_encode($c['scope_limit_json'], JSON_UNESCAPED_UNICODE)) : null;
                $cap = isset($c['amount_cap']) && $c['amount_cap'] !== null ? floatval($c['amount_cap']) : null;
                $cur = isset($c['currency']) ? (string) $c['currency'] : null;
                $stmt->bind_param('issds', $asgId, $code, $slj, $cap, $cur);
                $stmt->execute();
                $stmt->close();
            }

            foreach ($lines as $l) {
                $stmt = $conn->prepare(
                    'INSERT INTO assignment_reporting_lines (asg_id, line_type, reports_to_assignment_id, valid_from, valid_to)
                     VALUES (?, ?, ?, ?, ?)');
                $lt = (string) $l['line_type'];
                $rt = intval($l['reports_to_assignment_id']);
                $stmt->bind_param('isiss', $asgId, $lt, $rt, $d['valid_from'], $d['valid_to']);
                $stmt->execute();
                $stmt->close();
            }

            self::audit($conn, $asgId, 'created', isset($d['reason']) ? $d['reason'] : null, null,
                array('person_id' => $pid, 'type' => $tc, 'scope' => $st . ':' . $sid,
                      'valid' => $d['valid_from'] . '→' . $d['valid_to']), $dec);
            $conn->commit();
            $out['ok'] = true; $out['code'] = 201; $out['asg_id'] = $asgId;
            $out['reason'] = 'أنشئ التكليف #' . $asgId;
            return $out;
        } catch (\Throwable $e) {
            $conn->rollback();
            $out['code'] = 500; $out['reason'] = 'استثناء: ' . $e->getMessage();
            return $out;
        }
    }

    /**
     * إنهاء تكليف — قرار موثَّق لا تغيير صامت (§2⑨).
     * «الطلب للمدير الفني والقرار لمن كلَّف» (§2⑦): من ليس مصدرَ قرارٍ مخوَّلًا
     * يُسجَّل طلبُه ولا يُنفَّذ.
     * @return array{ok:bool, code:int, reason:string, requested:bool}
     */
    public static function end(\mysqli $conn, $asgId, $byPersonId, $reason = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'requested' => false);
        $asgId = intval($asgId);
        $row = self::fetch($conn, $asgId);
        if (!$row) { $out['code'] = 404; $out['reason'] = 'تكليف غير موجود'; return $out; }
        if ($row['state'] === 'ended') { $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'منته أصلا — فعل عاطل'; return $out; }

        if (!self::deciderAuthorized($conn, $byPersonId)) {
            // طلب إنهاء من المدير الفني — يُرفع ولا يُنفَّذ (O6-و)
            self::audit($conn, $asgId, 'amended', 'طلب إنهاء تكليف — يرفع لمصدر القرار ولا ينفذ',
                array('state' => $row['state']), array('requested_end_by' => intval($byPersonId), 'note' => $reason), intval($byPersonId));
            $out['ok'] = true; $out['code'] = 202; $out['requested'] = true;
            $out['reason'] = 'طلب الإنهاء سجل — والاعتماد النهائي لمصدر القرار وحده (§2⑦)';
            return $out;
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE org_assignments SET state = 'ended' WHERE asg_id = ?");
            $stmt->bind_param('i', $asgId);
            $stmt->execute();
            $stmt->close();
            self::audit($conn, $asgId, 'ended', $reason,
                array('state' => $row['state'], 'valid_to' => $row['valid_to']),
                array('state' => 'ended'), intval($byPersonId));
            $conn->commit();
            $out['ok'] = true; $out['code'] = 200;
            $out['reason'] = 'أنهي التكليف #' . $asgId . ' — والصلاحية سقطت في اللحظة نفسها';
            return $out;
        } catch (\Throwable $e) {
            $conn->rollback();
            $out['code'] = 500; $out['reason'] = 'استثناء: ' . $e->getMessage();
            return $out;
        }
    }

    /** تعليق/نقل — قرارات موثَّقة (§2⑨). */
    public static function suspend(\mysqli $conn, $asgId, $byPersonId, $reason = null)
    {
        return self::transition($conn, intval($asgId), 'suspended', 'suspended', intval($byPersonId), $reason);
    }

    private static function transition(\mysqli $conn, $asgId, $state, $action, $byPersonId, $reason)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $row = self::fetch($conn, $asgId);
        if (!$row) { $out['code'] = 404; $out['reason'] = 'تكليف غير موجود'; return $out; }
        if (!self::deciderAuthorized($conn, $byPersonId)) {
            $out['code'] = 403; $out['reason'] = 'القرار لمصدر القرار وحده'; return $out;
        }
        $stmt = $conn->prepare('UPDATE org_assignments SET state = ? WHERE asg_id = ?');
        $stmt->bind_param('si', $state, $asgId);
        $stmt->execute();
        $stmt->close();
        self::audit($conn, $asgId, $action, $reason, array('state' => $row['state']), array('state' => $state), $byPersonId);
        $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'تم';
        return $out;
    }

    public static function fetch(\mysqli $conn, $asgId)
    {
        $stmt = $conn->prepare('SELECT * FROM org_assignments WHERE asg_id = ? LIMIT 1');
        $asgId = intval($asgId);
        $stmt->bind_param('i', $asgId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    }

    private static function typeOf(\mysqli $conn, $code)
    {
        $stmt = $conn->prepare('SELECT * FROM org_assignment_types WHERE type_code = ? AND active = 1 LIMIT 1');
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    }

    /** سطر السجل — Insert-only (§2⑧). */
    public static function audit(\mysqli $conn, $asgId, $action, $reason, $before, $after, $byPersonId)
    {
        $stmt = $conn->prepare(
            'INSERT INTO assignment_audit (asg_id, action, reason, before_json, after_json, by_person_id)
             VALUES (?, ?, ?, ?, ?, ?)');
        $b = $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE) : null;
        $a = $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE) : null;
        $asgId = intval($asgId); $byPersonId = intval($byPersonId);
        $stmt->bind_param('issssi', $asgId, $action, $reason, $b, $a, $byPersonId);
        $stmt->execute();
        $stmt->close();
    }
}
