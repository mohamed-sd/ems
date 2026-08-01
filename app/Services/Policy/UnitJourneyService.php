<?php
/**
 * مسار الوحدة — السلسلة والأثران (POL-01 §3–§6 · §12-②③④⑤)
 * ───────────────────────────────────────────────────────────────────────────
 * ثلاث خدمات متكاملة في ملف واحد (المسار واحد والفصل بالطبقة لا بالملفات):
 *   • UnitApprovalChainService: الحلقات بترتيبها — حلقة قبل سابقتها 409 ·
 *     غير المنطبقة تُتخطى آليًّا · الاعتراض ببند يعلّقه وتمضي البقية ·
 *     كل حلقة توقيع بمرجع تفويضه (approval_signatures).
 *   • PrimaryEffectService: عند اكتمال السلسلة يُطبَّق الأثر الأولي فورًا في
 *     كل إدارة معنية بحالة primary/Applied — **وصفر قيد مالي**.
 *   • FinancialEntitlementService: يجمّع الأولي مقترحًا؛ ولا Posted إلا
 *     باعتماد مدير الإدارة + المالية — عندها يُنشأ حدث FES وتُملأ fin_event_ref.
 *     قيد بلا اعتماد → 403 بنيويًّا (+CHECK في الجدول).
 *   • DeductionEngine: الخصم Proposed حصرًا في كل الإدارات — كخصم الموظف تمامًا.
 */

namespace App\Services\Policy;

require_once __DIR__ . '/../../Core/AuthorityGuard.php';
require_once __DIR__ . '/../../Core/EventPublisher.php';

use App\Core\AuthorityGuard;
use App\Core\EventPublisher;

class UnitJourneyService
{
    // ═════════════════════════ ② السلسلة ═════════════════════════

    /**
     * اعتماد حلقةٍ لوحدة. الحلقات من approval_chains لسياسة الإدارة.
     * @param array $chainDef صفوف approval_chains مرتبةً
     * @param array $ctx {applicable_roles: الحلقات المنطبقة على هذه الوحدة}
     * @return array{ok:bool,code:int,reason:string,chain_state:string,completed_links:int,next_link:?string}
     */
    public static function approveLink(\mysqli $conn, $companyId, $unitId, array $chainDef, $role, $personId, array $ctx = array())
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'chain_state' => 'in_progress', 'completed_links' => 0, 'next_link' => null);
        $companyId = intval($companyId); $unitId = intval($unitId); $role = (string) $role;
        $applicable = isset($ctx['applicable_roles']) && is_array($ctx['applicable_roles']) ? $ctx['applicable_roles'] : null;

        // الحلقات الفعلية بعد التخطي الآلي لغير المنطبق (P3)
        $links = array();
        foreach ($chainDef as $c) {
            $r = (string) $c['approver_role'];
            if (intval($c['skip_if_not_applicable']) === 1 && $applicable !== null && !in_array($r, $applicable, true)) {
                continue; // يوم بلا معدة مورد لا يمر بإدارة الموردين
            }
            $links[] = $r;
        }
        if (!in_array($role, $links, true)) {
            $out['code'] = 422; $out['reason'] = 'الحلقة «' . $role . '» غير منطبقة على هذه الوحدة أو ليست في سلسلة السياسة';
            return $out;
        }

        // الحلقات الموقَّعة سلفًا
        $done = self::signedLinks($conn, $companyId, $unitId);
        $pos = array_search($role, $links, true);
        for ($i = 0; $i < $pos; $i++) {
            if (!in_array($links[$i], $done, true)) {
                $out['code'] = 409;
                $out['reason'] = 'لا تُفتح حلقةٌ قبل اكتمال ما قبلها — «' . $links[$i] . '» لم تعتمد بعد';
                return $out;
            }
        }
        if (in_array($role, $done, true)) {
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'حلقة موقَّعة سلفًا — فعل عاطل';
            $out['completed_links'] = count($done);
            return $out;
        }

        // التوقيع بمرجع التفويض (LEG-01 §6-③-ب: كل حلقة توقيع)
        $sig = AuthorityGuard::sign($conn, array(
            'company_id' => $companyId, 'document_type' => 'unit_chain', 'document_id' => $unitId,
            'step' => $role, 'person_id' => intval($personId),
            'created_by_person_id' => isset($ctx['entered_by']) ? intval($ctx['entered_by']) : null,
        ));
        if (!$sig['ok']) { return array_merge($out, array('code' => $sig['code'], 'reason' => $sig['reason'])); }

        $done[] = $role;
        $out['ok'] = true; $out['code'] = 200;
        $out['completed_links'] = count($done);
        $remaining = array_values(array_diff($links, $done));
        if (empty($remaining)) {
            $out['chain_state'] = 'completed';
            $out['reason'] = 'اكتملت السلسلة — يُطبَّق الأثر الأولي التشغيلي فورًا (لا مالي)';
        } else {
            $out['next_link'] = $remaining[0];
            $out['reason'] = 'اعتُمدت حلقة «' . $role . '» — التالية: ' . $remaining[0];
        }
        return $out;
    }

    /**
     * الاعتراض ببند: البند يُعلَّق والبقية تمضي — سبب من القائمة المحكومة حصرًا (P10).
     * DEC-01 ⑥: كل اعتراض **يُرصد** في chain_objections — فاعتراضان في شهر
     * يعيدان دورية السياسة إلى اليومية آليًّا (PeriodicityService::sweepAutoRevert).
     */
    public static function objectLine(\mysqli $conn, $companyId, $unitId, $lineRef, $reasonCode, $domain, $personId, $policyId = null, $siteId = null)
    {
        $stmt = $conn->prepare("SELECT reason_id, requires_document FROM decision_reasons WHERE domain = ? AND reason_kind IN ('return','reject') AND code = ? AND active = 1 LIMIT 1");
        $domain = (string) $domain; $reasonCode = (string) $reasonCode;
        $stmt->bind_param('ss', $domain, $reasonCode);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$r) {
            return array('ok' => false, 'code' => 422, 'reason' => 'سببٌ نصيٌّ حرٌّ أو خارج القائمة المحكومة — يُرفض، والسبب من decision_reasons حصرًا');
        }
        // الرصد (Insert-only) — مقياس الرجوع الآلي لليومية، وفشله لا يعطّل الاعتراض
        try {
            $stmt = $conn->prepare(
                'INSERT INTO chain_objections (company_id, unit_id, line_ref, domain, reason_code, policy_id, site_id, person_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $co = intval($companyId); $uid = intval($unitId); $lr = (string) $lineRef;
            $pid = ($policyId !== null) ? intval($policyId) : null;
            $sid = ($siteId !== null) ? intval($siteId) : null;
            $per = intval($personId);
            $stmt->bind_param('iisssiii', $co, $uid, $lr, $domain, $reasonCode, $pid, $sid, $per);
            $stmt->execute();
            $stmt->close();
        } catch (\Throwable $t) {
            error_log('[UnitJourneyService] objection log failed: ' . $t->getMessage());
        }
        return array('ok' => true, 'code' => 200,
            'reason' => 'اعتُرض على البند ' . $lineRef . ' بسبب «' . $reasonCode . '» — **البند معلَّق والبقية تمضي** ولا تتجمد سلسلة الموقع');
    }

    private static function signedLinks(\mysqli $conn, $companyId, $unitId)
    {
        $stmt = $conn->prepare("SELECT step FROM approval_signatures WHERE company_id = ? AND document_type = 'unit_chain' AND document_id = ? AND result = 'signed'");
        $stmt->bind_param('ii', $companyId, $unitId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(function ($r) { return (string) $r['step']; }, $rows);
    }

    // ═════════════════════════ ③ الأثر الأولي ═════════════════════════

    /**
     * تطبيق الأثر الأولي فور اكتمال السلسلة — primary/Applied في كل إدارة
     * معنية، **وLedger: صفر قيد**. الكتابة الفعلية في الجداول القائمة
     * (container_consumption · meter_readings · سجل الوحدات) عبر مساراتها،
     * وunit_effects سجل التتبع الرابط.
     * @param array $effects [{domain, effect_kind, quantity}]
     */
    public static function applyPrimary(\mysqli $conn, $companyId, $unitId, array $effects, $period = null)
    {
        $companyId = intval($companyId); $unitId = intval($unitId);
        $period = ($period !== null) ? (string) $period : date('Y-m');
        $applied = array();
        foreach ($effects as $e) {
            $stmt = $conn->prepare(
                "INSERT IGNORE INTO unit_effects (company_id, source_unit_id, domain, effect_kind, quantity, stage, state, period)
                 VALUES (?, ?, ?, ?, ?, 'primary', 'Applied', ?)");
            $d = (string) $e['domain']; $k = (string) $e['effect_kind']; $q = (float) $e['quantity'];
            $stmt->bind_param('iissds', $companyId, $unitId, $d, $k, $q, $period);
            $stmt->execute();
            if ($stmt->affected_rows === 1) { $applied[] = array('domain' => $d, 'kind' => $k); }
            $stmt->close();
        }
        return array('ok' => true, 'code' => 200, 'primary_effects' => $applied,
                     'financial_effects' => array(), 'ledger_entries' => 0,
                     'reason' => 'أثر أولي في ' . count($applied) . ' إدارة — القياس فوري والمال مؤجَّل');
    }

    // ═════════════════════════ ④ بوابة الاستحقاق ═════════════════════════

    /** تجميع الأثر الأولي للفترة مقترحًا ماليًّا (Proposed) — لا قيد بعد. */
    public static function proposeEntitlements(\mysqli $conn, $companyId, $period)
    {
        $companyId = intval($companyId); $period = (string) $period;
        $conn->query(
            "INSERT IGNORE INTO unit_effects (company_id, source_unit_id, domain, effect_kind, quantity, stage, state, period)
             SELECT company_id, source_unit_id, domain, effect_kind, quantity, 'financial', 'Proposed', period
               FROM unit_effects
              WHERE company_id = {$companyId} AND period = '" . $conn->real_escape_string($period) . "'
                AND stage = 'primary' AND state = 'Applied'");
        return array('ok' => true, 'proposed' => $conn->affected_rows);
    }

    /**
     * بوابة الاستحقاق: **لا Posted إلا باعتماد مدير الإدارة + المالية** —
     * عندها يُنشأ حدث FES وتُملأ fin_event_ref. قيد بلا اعتماد → 403.
     * @return array{ok:bool,code:int,reason:string,posted:int}
     */
    public static function postEntitlement(\mysqli $conn, $companyId, $peId, $deptManagerId, $financeManagerId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'posted' => 0);
        $companyId = intval($companyId); $peId = intval($peId);
        $stmt = $conn->prepare("SELECT * FROM unit_effects WHERE company_id = ? AND pe_id = ? AND stage = 'financial' LIMIT 1");
        $stmt->bind_param('ii', $companyId, $peId);
        $stmt->execute();
        $pe = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$pe) { $out['code'] = 404; $out['reason'] = 'لا أثر ماليًّا مقترحًا بهذا المعرف'; return $out; }
        if ((string) $pe['state'] === 'Posted') { $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'مقيَّد سلفًا — عاطل'; return $out; }

        if (!$deptManagerId || !$financeManagerId) {
            $out['code'] = 403;
            $out['reason'] = '403 بنيويًّا — لا استحقاق مالي بلا اعتماد **مدير الإدارة والمالية معًا**، ولا يظهر في تسوية ولا مستخلص ولا مسيّر';
            return $out;
        }
        // اعتمادان مستقلان موقَّعان
        foreach (array(array($deptManagerId, 'dept_manager'), array($financeManagerId, 'finance_manager')) as $ap) {
            $sig = AuthorityGuard::sign($conn, array(
                'company_id' => $companyId, 'document_type' => 'entitlement', 'document_id' => $peId,
                'step' => $ap[1], 'person_id' => intval($ap[0]),
            ));
            if (!$sig['ok']) { return array_merge($out, array('code' => $sig['code'], 'reason' => $sig['reason'])); }
        }
        if (intval($deptManagerId) === intval($financeManagerId)) {
            $out['code'] = 403; $out['reason'] = 'الاعتمادان من شخص واحد — استقلال الموافقات شرط صحتها';
            return $out;
        }

        // حدث FES — الخيط متصل ولا جدول مال ثانٍ
        $fact = EventPublisher::publishFact($conn, array(
            'event_key' => 'policy.entitlement.posted',
            'category' => 'financial',
            'source_module' => 'finance',
            'company_id' => $companyId,
            'entity_type' => 'unit_effect',
            'entity_id' => $peId,
            'occurred_at' => gmdate('Y-m-d H:i:s'),
            'created_by' => intval($actor) ?: 1,
            'idempotency_key' => 'entitlement:pe:' . $peId,
            'quantity' => (float) $pe['quantity'],
            'unit' => 'unit',
            'payload' => array('domain' => $pe['domain'], 'effect_kind' => $pe['effect_kind'], 'period' => $pe['period']),
        ));
        $ref = $fact ? intval($fact['id']) : null;
        if ($ref === null) { $out['code'] = 500; $out['reason'] = 'تعذّر نشر حدث FES'; return $out; }
        $stmt = $conn->prepare("UPDATE unit_effects SET state = 'Posted', approved_by = ?, approved_at = NOW(), fin_event_ref = ? WHERE pe_id = ? AND stage = 'financial'");
        $dm = intval($deptManagerId);
        $stmt->bind_param('iii', $dm, $ref, $peId);
        $stmt->execute();
        $stmt->close();
        $out['ok'] = true; $out['code'] = 200; $out['posted'] = 1;
        $out['reason'] = 'EntitlementPosted — حدث FES #' . $ref . ' والخيط متصل';
        return $out;
    }

    // ═════════════════════════ ⑤ الخصومات ═════════════════════════

    /** DeductionEngine — يُنشئ الخصم Proposed حصرًا؛ الترحيل بسلّم GOV-01 (deduction_proposals القادمة في WRK-01). */
    public static function proposeDeduction(\mysqli $conn, $companyId, $unitId, $domain, $kind, $qty, $period)
    {
        $companyId = intval($companyId);
        $stmt = $conn->prepare(
            "INSERT IGNORE INTO unit_effects (company_id, source_unit_id, domain, effect_kind, quantity, stage, state, period, note)
             VALUES (?, ?, ?, 'charge', ?, 'financial', 'Proposed', ?, ?)");
        $unitId = intval($unitId); $domain = (string) $domain; $qty = (float) $qty;
        $period = (string) $period; $note = 'خصم مقترح: ' . (string) $kind;
        $stmt->bind_param('iisdss', $companyId, $unitId, $domain, $qty, $period, $note);
        $stmt->execute();
        $created = ($stmt->affected_rows === 1);
        $stmt->close();
        return array('ok' => true, 'state' => 'Proposed', 'created' => $created,
                     'reason' => 'خصم Proposed حصرًا — ولا Posted إلا بسلّم GOV-01، كخصم الموظف تمامًا');
    }
}
