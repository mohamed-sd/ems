<?php
/**
 * app/Services/Finance/FinanceM10Service.php — أفعالُ M-10 المعلَنةُ غيرِ المبنية
 * ═══════════════════════════════════════════════════════════════════════════
 * المرجع: M-10 — المالية والخزينة (docs/update0012) §7 و§8 و§11.
 * تبني هذه الخدمةُ حرّاسَ الأفعالِ السبعةِ التي كانت declared_unbuilt — ولا
 * تلمس المبنيَّ سلفًا (pay.execute · je.post · fin.close ... bound_page).
 *
 * الأحكامُ الحاكمةُ المنفَّذة هنا:
 *  · بوابةُ الاستحقاقِ الرباعية (gate.pass): «سلسلةٌ مكتملة · فترةٌ مفتوحة ·
 *    عقدٌ نافذ · حصةٌ متاحة — ثم يتولد الأثرُ الماليُّ ولا يقع قبلها» —
 *    وإخفاقُ فحصٍ يردُّ الواقعةَ بسببٍ محكومٍ (GATE-CHAIN/PERIOD/CONTRACT/QUOTA).
 *  · التوليد (fin.entitle) يمرُّ بمحرّك المروحة القائمِ EffectFanout — «لا
 *    مكوّنَ جديدًا لوظيفةٍ قائمة»: الأحكامُ الثلاثةُ من مخرَجِ المروحةِ نفسِه،
 *    والمتخطَّى يُعلَن بسببِه ولا يُخترع له رقم (قاعدةُ عدم التلفيق).
 *  · العطالة AR-04: مفاتيحُ (شركة×وحدة) و(شركة×مصدر) — الإعادةُ ترجع الأول.
 *  · فصلُ الواجبات §9-3: budget.approve يرفض أن يعتمد المُنشئُ موازنتَه،
 *    وpay-request/approve مفصولان في مصدرهما.
 *  · عدمُ الرجعية §9-4: كلُّ التصحيح بمرجعٍ (supersedes/released) لا حذفًا —
 *    ولا دالةَ حذفٍ في هذه الخدمةِ عمدًا.
 */

namespace App\Services\Finance;

require_once dirname(dirname(__DIR__)) . '/Core/EventPublisher.php';
require_once dirname(__DIR__) . '/EffectFanout.php';
require_once dirname(__DIR__) . '/Revenue/ClientStatementService.php';

use App\Core\EventPublisher;
use App\Services\EffectFanout;
use App\Services\Revenue\ClientStatementService;

class FinanceM10Service
{
    /** أسبابُ ردِّ البوابة المحكومة — تُعرض في الشاشة برمزها (UI-13). */
    const GATE_REJECTS = array(
        'GATE-CHAIN'    => 'سلسلةُ الاعتماد غيرُ مكتملة — تُردُّ لصاحب الحلقة الناقصة',
        'GATE-PERIOD'   => 'الفترةُ المحاسبيةُ غيرُ مفتوحة — تُعلَّق حتى فتحِها أو تصحيحِ التاريخ',
        'GATE-CONTRACT' => 'لا عقدَ نافذًا وقتَ التنفيذ — تُردُّ للمبيعات أو الموردين',
        'GATE-QUOTA'    => 'حصةُ المورد غيرُ متاحة — تُردُّ للموردين لتعديل الحصة',
    );

    /** ترقيمٌ تسلسليٌّ لكل شركةٍ — قائمةُ سماحٍ صلبة (نمطُ RiskService::nextCode). */
    public static function nextCode(\mysqli $db, $companyId, $table, $column, $prefix, $width = 6)
    {
        $allowed = array(
            'fin_entitlements'          => 'entitle_code',
            'fin_entitlement_gate_log'  => 'gate_code',
            'fin_budget_commitments'    => 'commit_code',
            'fin_budget_change_requests' => 'req_code',
            'fin_client_statements'     => 'stmt_code',
            'fin_margin_analysis'       => 'run_code',
            'fin_cycle_time_metrics'    => 'metric_code',
        );
        if (!isset($allowed[$table]) || $allowed[$table] !== $column) {
            throw new \RuntimeException('FIN-500: جدولٌ أو عمودٌ خارجَ قائمةِ الترقيم');
        }
        $len = strlen($prefix) + 2; // البادئة + الشرطة
        $sql = "SELECT COALESCE(MAX(CAST(SUBSTRING(`$column`, $len) AS UNSIGNED)), 0) + 1 nx FROM `$table` WHERE company_id = ?";
        $st = $db->prepare($sql);
        $st->bind_param('i', $companyId);
        $st->execute();
        $nx = (int) $st->get_result()->fetch_assoc()['nx'];
        $st->close();
        return $prefix . '-' . str_pad((string) $nx, $width, '0', STR_PAD_LEFT);
    }

    /* ═══ gate.pass — بوابةُ الاستحقاقِ الرباعية ═══════════════════════════ */

    /**
     * الفحوصُ الأربعةُ على واقعةٍ (fin_unit_records) — قراءةُ حكمٍ بلا كتابة.
     * @return array{chain_ok:int,period_ok:int,contract_ok:int,quota_ok:int,result:string,reject_code:string,unit:array}
     */
    public static function gateChecks(\mysqli $db, $companyId, $unitId)
    {
        $st = $db->prepare("SELECT * FROM fin_unit_records WHERE id = ? AND company_id = ? AND COALESCE(is_deleted,0) = 0");
        $st->bind_param('ii', $unitId, $companyId);
        $st->execute();
        $unit = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$unit) { throw new \RuntimeException('FIN-404: الواقعةُ غيرُ موجودةٍ في نطاقك'); }

        // ① اكتمالُ سلسلة الاعتماد — الحلقاتُ شهدت والاعتمادُ النهائيُّ وقع
        $chainOk = ($unit['match_state'] === 'approved' && (float) ($unit['approved_qty'] ?? 0) > 0) ? 1 : 0;

        // ② انفتاحُ الفترة المحاسبية — fail-closed: لا فترةَ تغطي التاريخ = رفض
        $periodOk = 0;
        $st = $db->prepare("SELECT state, posting_allowed FROM fin_financial_periods
                             WHERE company_id = ? AND period_type = 'month'
                               AND start_date <= ? AND end_date >= ? LIMIT 1");
        $st->bind_param('iss', $companyId, $unit['record_date'], $unit['record_date']);
        $st->execute();
        if ($p = $st->get_result()->fetch_assoc()) {
            $periodOk = (in_array($p['state'], array('open', 'reopened'), true)
                         && (int) $p['posting_allowed'] === 1) ? 1 : 0;
        }
        $st->close();

        // ③ نفاذُ العقدِ وقتَ التنفيذ — عقدُ المشروع في حالةِ نفاذٍ من آلةِ H-02
        $contractOk = 0;
        $contractId = null;
        if (!empty($unit['project_id'])) {
            $pid = (int) $unit['project_id'];
            $st = $db->prepare("SELECT id FROM contracts
                                 WHERE company_id = ? AND project_id = ?
                                   AND contract_status IN ('نافذ','قيد التنفيذ','مجدَّد')
                                 ORDER BY id DESC LIMIT 1");
            $st->bind_param('ii', $companyId, $pid);
            $st->execute();
            if ($c = $st->get_result()->fetch_assoc()) { $contractOk = 1; $contractId = (int) $c['id']; }
            $st->close();
        }

        // ④ إتاحةُ حصةِ المورد — المعدةُ المملوكةُ (بلا مورد) لا حصةَ تلزمها؛
        //    وذاتُ المورد تحتاج تغطيةً حيةً: قيدَ استهلاكٍ في دفتر السعة أو
        //    مقعدَ عقدٍ نشطًا للمعدة (fail-closed على الغياب التام).
        $quotaOk = 1;
        if (!empty($unit['supplier_entity_id'])) {
            $quotaOk = 0;
            $st = $db->prepare("SELECT 1 FROM capacity_consumption_ledger
                                 WHERE company_id = ? AND unit_record_id = ? LIMIT 1");
            $st->bind_param('ii', $companyId, $unitId);
            $st->execute();
            if ($st->get_result()->fetch_row()) { $quotaOk = 1; }
            $st->close();
            if (!$quotaOk && !empty($unit['equipment_id'])) {
                $eq = (int) $unit['equipment_id'];
                $r = $db->query("SHOW TABLES LIKE 'contract_seats'");
                if ($r && $r->num_rows) {
                    $st = $db->prepare("SELECT 1 FROM contract_seats
                                         WHERE company_id = ? AND equipment_id = ? LIMIT 1");
                    $st->bind_param('ii', $companyId, $eq);
                    $st->execute();
                    if ($st->get_result()->fetch_row()) { $quotaOk = 1; }
                    $st->close();
                }
            }
        }

        $reject = '';
        if (!$chainOk) { $reject = 'GATE-CHAIN'; }
        elseif (!$periodOk) { $reject = 'GATE-PERIOD'; }
        elseif (!$contractOk) { $reject = 'GATE-CONTRACT'; }
        elseif (!$quotaOk) { $reject = 'GATE-QUOTA'; }

        return array(
            'chain_ok' => $chainOk, 'period_ok' => $periodOk,
            'contract_ok' => $contractOk, 'quota_ok' => $quotaOk,
            'result' => $reject === '' ? 'pass' : 'reject',
            'reject_code' => $reject, 'contract_id' => $contractId, 'unit' => $unit,
        );
    }

    /**
     * gate.pass — يكتب محضرَ الفحص وينشر الحدثَ عند المرور.
     * العطالة: (وحدة×يوم) — إعادةُ الفحصِ في اليوم نفسِه ترجع المحضرَ الأول.
     */
    public static function gatePass(\mysqli $db, $companyId, $unitId, $actor)
    {
        $chk = self::gateChecks($db, $companyId, $unitId);
        $idem = 'gate:unit:' . (int) $unitId . ':' . gmdate('Y-m-d');

        $st = $db->prepare("SELECT id, gate_code, result, reject_code FROM fin_entitlement_gate_log
                             WHERE company_id = ? AND idempotency_key = ? LIMIT 1");
        $st->bind_param('is', $companyId, $idem);
        $st->execute();
        if ($prev = $st->get_result()->fetch_assoc()) {
            $st->close();
            return array('idempotent' => true, 'id' => (int) $prev['id'],
                'gate_code' => $prev['gate_code'], 'result' => $prev['result'],
                'reject_code' => $prev['reject_code'], 'checks' => $chk);
        }
        $st->close();

        $unit = $chk['unit'];
        $period = date('Y-m', strtotime($unit['record_date']));
        $code = self::nextCode($db, $companyId, 'fin_entitlement_gate_log', 'gate_code', 'GTE');
        $impact = null;
        if ($unit['client_unit_price'] !== null) {
            $impact = round((float) ($unit['approved_qty'] ?? 0) * (float) $unit['client_unit_price'], 2);
        }
        $clientRuling = $unit['client_unit_price'] !== null ? 'يُفوتر' : 'لا سعرَ عميلٍ — يُتخطى معلَنًا';
        $supplierRuling = !empty($unit['supplier_entity_id'])
            ? ($unit['supplier_unit_price'] !== null ? 'يستحق' : 'لا سعرَ موردٍ — يُتخطى معلَنًا')
            : 'معدةٌ مملوكة — لا مورد';
        $operatorRuling = 'من مصدر التكليف إن وُجد';

        $factId = null;
        if ($chk['result'] === 'pass') {
            $fact = EventPublisher::publishFact($db, array(
                'event_key' => 'finance.entitlement.gate_passed',
                'category' => 'financial',
                'source_module' => 'finance',
                'company_id' => (int) $companyId,
                'entity_type' => 'fin_unit_record',
                'entity_id' => (int) $unitId,
                'occurred_at' => gmdate('Y-m-d H:i:s'),
                'created_by' => (int) $actor ?: 1,
                'idempotency_key' => 'evt:' . $idem,
                'amount' => $impact !== null ? $impact : 0.00,
                'currency' => 'SDG',
                'source_ref' => (string) $unit['record_no'],
                'payload' => array(
                    'event_name' => 'EntitlementGatePassed',
                    'consumers' => array('المالية', 'المبيعات', 'الموردون', 'القوى'),
                    'checks' => array('chain' => 1, 'period' => 1, 'contract' => 1, 'quota' => 1),
                ),
            ));
            $factId = $fact ? (int) $fact['id'] : null;
        }

        $st = $db->prepare("INSERT INTO fin_entitlement_gate_log
            (company_id, gate_code, period, contract_id, unit_record_id,
             chain_ok, period_ok, contract_ok, quota_ok, result, reject_code,
             client_ruling, supplier_ruling, operator_ruling, impact_amount, currency,
             fact_event_id, idempotency_key, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $cur = 'SDG';
        $st->bind_param('issiiiiiisssssdsisi',
            $companyId, $code, $period, $chk['contract_id'], $unitId,
            $chk['chain_ok'], $chk['period_ok'], $chk['contract_ok'], $chk['quota_ok'],
            $chk['result'], $chk['reject_code'],
            $clientRuling, $supplierRuling, $operatorRuling, $impact, $cur,
            $factId, $idem, $actor);
        $st->execute();
        if ($st->errno) { $err = $st->error; $st->close(); throw new \RuntimeException('FIN-500: ' . $err); }
        $id = (int) $db->insert_id;
        $st->close();

        return array('idempotent' => false, 'id' => $id, 'gate_code' => $code,
            'result' => $chk['result'], 'reject_code' => $chk['reject_code'],
            'reject_msg' => $chk['reject_code'] !== '' ? self::GATE_REJECTS[$chk['reject_code']] : '',
            'fact_id' => $factId, 'checks' => $chk);
    }

    /* ═══ fin.entitle — توليدُ المستحق من العمل المعتمد ════════════════════ */

    /**
     * البوابةُ أولًا ثم المروحةُ القائمة — والمحضرُ يوثّق الأحكامَ الثلاثة.
     * تُستدعى داخل معاملة TenantDb ($gate->runInTransaction) حصرًا.
     */
    public static function generateEntitlement(\mysqli $db, $gate, $companyId, $unitId, $actor)
    {
        // العطالة أولًا: محضرٌ سابقٌ للوحدة يرجع نفسُه (AR-04)
        $idem = 'entitle:unit:' . (int) $unitId;
        $st = $db->prepare("SELECT id, entitle_code FROM fin_entitlements
                             WHERE company_id = ? AND idempotency_key = ? LIMIT 1");
        $st->bind_param('is', $companyId, $idem);
        $st->execute();
        if ($prev = $st->get_result()->fetch_assoc()) {
            $st->close();
            return array('idempotent' => true, 'id' => (int) $prev['id'], 'entitle_code' => $prev['entitle_code']);
        }
        $st->close();

        // البوابةُ الرباعية — «ولا يقع أثرٌ قبلها»
        $gateRes = self::gatePass($db, $companyId, $unitId, $actor);
        if ($gateRes['result'] !== 'pass') {
            throw new \RuntimeException($gateRes['reject_code'] . ': ' . self::GATE_REJECTS[$gateRes['reject_code']]);
        }
        $unit = $gateRes['checks']['unit'];

        // المروحةُ القائمة — الآثارُ الثلاثةُ بأحكامها والمتخطَّى بسببه
        $fan = EffectFanout::forUnitRecord($db, $gate, $unit, (int) $actor);

        $rulings = array('client' => 'تُخُطي', 'supplier' => 'تُخُطي', 'operator' => 'تُخُطي');
        $amounts = array('client' => null, 'supplier' => null, 'operator' => null);
        foreach (array_merge($fan['effects'], $fan['adopted']) as $eff) {
            $t = (string) ($eff['effect'] ?? $eff['effect_type'] ?? '');
            $amt = isset($eff['amount']) ? (float) $eff['amount'] : null;
            if (strpos($t, 'revenue') !== false) { $rulings['client'] = 'وُلّد الإيراد'; $amounts['client'] = $amt; }
            if (strpos($t, 'supplier') !== false) { $rulings['supplier'] = 'وُلّد الاستحقاق'; $amounts['supplier'] = $amt; }
            if (strpos($t, 'operator') !== false || strpos($t, 'pay') !== false) { $rulings['operator'] = 'وُلّد الأجر'; $amounts['operator'] = $amt; }
        }
        foreach ($fan['skipped'] as $sk) {
            $t = (string) ($sk['effect'] ?? '');
            $reason = 'تُخُطي: ' . (string) ($sk['reason'] ?? '');
            if (strpos($t, 'revenue') !== false) { $rulings['client'] = $reason; }
            if (strpos($t, 'supplier') !== false) { $rulings['supplier'] = $reason; }
            if (strpos($t, 'operator') !== false || strpos($t, 'pay') !== false) { $rulings['operator'] = $reason; }
        }
        if ($amounts['client'] === null && $unit['client_unit_price'] !== null) {
            $amounts['client'] = round((float) $unit['approved_qty'] * (float) $unit['client_unit_price'], 2);
        }
        if ($amounts['supplier'] === null && $unit['supplier_unit_price'] !== null && !empty($unit['supplier_entity_id'])) {
            $amounts['supplier'] = round((float) $unit['approved_qty'] * (float) $unit['supplier_unit_price'], 2);
        }

        $code = self::nextCode($db, $companyId, 'fin_entitlements', 'entitle_code', 'ENT');
        $period = date('Y-m', strtotime($unit['record_date']));
        $effectsJson = json_encode(array(
            'effects' => $fan['effects'], 'adopted' => $fan['adopted'], 'skipped' => $fan['skipped'],
        ), JSON_UNESCAPED_UNICODE);
        $factId = $fan['fact_id'] !== null ? (int) $fan['fact_id'] : null;
        $parentRef = (string) $unit['record_no'];
        $chainAt = gmdate('Y-m-d H:i:s', strtotime((string) ($unit['updated_at'] ?: $unit['created_at'])));

        $st = $db->prepare("INSERT INTO fin_entitlements
            (company_id, entitle_code, period, contract_id, unit_record_id,
             client_ruling, client_amount, supplier_ruling, supplier_amount,
             operator_ruling, operator_amount, currency, chain_completed_at,
             fact_event_id, effects_json, generated_by, authority_ref, parent_ref,
             idempotency_key, ruleset_version)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $cur = 'SDG';
        $authorityRef = 'دورُ المديرِ المالي — سلطةُ توليدِ الاستحقاق (M-10 §7-1)';
        $ruleset = 'M-10@update0012';
        $st->bind_param('issiisdsdsdssisissss',
            $companyId, $code, $period, $gateRes['checks']['contract_id'], $unitId,
            $rulings['client'], $amounts['client'], $rulings['supplier'], $amounts['supplier'],
            $rulings['operator'], $amounts['operator'], $cur, $chainAt,
            $factId, $effectsJson, $actor, $authorityRef, $parentRef, $idem, $ruleset);
        $st->execute();
        if ($st->errno) { $err = $st->error; $st->close(); throw new \RuntimeException('FIN-500: ' . $err); }
        $id = (int) $db->insert_id;
        $st->close();

        EventPublisher::publishFact($db, array(
            'event_key' => 'finance.entitlement.generated',
            'category' => 'financial',
            'source_module' => 'finance',
            'company_id' => (int) $companyId,
            'entity_type' => 'fin_entitlement',
            'entity_id' => $id,
            'occurred_at' => gmdate('Y-m-d H:i:s'),
            'created_by' => (int) $actor ?: 1,
            'idempotency_key' => 'evt:' . $idem,
            'amount' => $amounts['client'] !== null ? $amounts['client'] : 0.00,
            'currency' => 'SDG',
            'source_ref' => $code,
            'payload' => array(
                'event_name' => 'EntitlementGenerated',
                'consumers' => array('المبيعات', 'الموردون', 'القوى', 'التمويل'),
                'rulings' => $rulings,
            ),
        ));

        return array('idempotent' => false, 'id' => $id, 'entitle_code' => $code,
            'rulings' => $rulings, 'amounts' => $amounts,
            'effects' => count($fan['effects']), 'adopted' => count($fan['adopted']),
            'skipped' => $fan['skipped']);
    }

    /* ═══ budget.commit / release — الالتزامُ يخفض المتاحَ قبل الصرف ═══════ */

    public static function budgetCommit(\mysqli $db, $companyId, $budgetId, $lineId, $sourceKind, $sourceRef, $amount, $actor)
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) { throw new \RuntimeException('FIN-422: مبلغُ الالتزامِ موجبٌ إلزامًا'); }
        if (trim((string) $sourceRef) === '') { throw new \RuntimeException('FIN-422: مرجعُ المصدرِ إلزاميّ'); }

        $idem = 'commit:' . $sourceKind . ':' . $sourceRef;
        $st = $db->prepare("SELECT id, commit_code, state FROM fin_budget_commitments
                             WHERE company_id = ? AND idempotency_key = ? LIMIT 1");
        $st->bind_param('is', $companyId, $idem);
        $st->execute();
        if ($prev = $st->get_result()->fetch_assoc()) {
            $st->close();
            return array('idempotent' => true, 'id' => (int) $prev['id'], 'commit_code' => $prev['commit_code']);
        }
        $st->close();

        // الحارس: المتاحُ = المخطَّطُ − الفعليُّ − الملتزَمُ القائم — والتجاوزُ يُرفض
        $bind = $lineId ? 'AND l.id = ' . (int) $lineId : '';
        $r = $db->query("SELECT COALESCE(SUM(l.planned_amount),0) planned, COALESCE(SUM(l.actual_amount),0) actual
                           FROM fin_budget_lines l
                          WHERE l.company_id = {$companyId} AND l.budget_id = " . (int) $budgetId . " $bind");
        $row = $r ? $r->fetch_assoc() : array('planned' => 0, 'actual' => 0);
        $r = $db->query("SELECT COALESCE(SUM(amount),0) c FROM fin_budget_commitments
                          WHERE company_id = {$companyId} AND budget_id = " . (int) $budgetId . "
                            AND state = 'committed'" . ($lineId ? ' AND budget_line_id = ' . (int) $lineId : ''));
        $committed = $r ? (float) $r->fetch_assoc()['c'] : 0.0;
        $available = (float) $row['planned'] - (float) $row['actual'] - $committed;
        if ($amount > $available + 0.005) {
            throw new \RuntimeException('FIN-BUDGET-EXCEEDED: المتاحُ ' . number_format($available, 2)
                . ' والمطلوبُ ' . number_format($amount, 2) . ' — يُوقف الطلبُ حتى اعتمادِ تعديلِ الموازنة');
        }

        $code = self::nextCode($db, $companyId, 'fin_budget_commitments', 'commit_code', 'CMB');
        $st = $db->prepare("INSERT INTO fin_budget_commitments
            (company_id, commit_code, budget_id, budget_line_id, source_kind, source_ref,
             amount, available_before, created_by, idempotency_key)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
        $st->bind_param('isiissddis', $companyId, $code, $budgetId, $lineId, $sourceKind, $sourceRef,
            $amount, $available, $actor, $idem);
        $st->execute();
        if ($st->errno) { $err = $st->error; $st->close(); throw new \RuntimeException('FIN-500: ' . $err); }
        $id = (int) $db->insert_id;
        $st->close();

        EventPublisher::publishFact($db, array(
            'event_key' => 'finance.budget.committed', 'category' => 'financial',
            'source_module' => 'finance', 'company_id' => (int) $companyId,
            'entity_type' => 'fin_budget_commitment', 'entity_id' => $id,
            'occurred_at' => gmdate('Y-m-d H:i:s'), 'created_by' => (int) $actor ?: 1,
            'idempotency_key' => 'evt:' . $idem, 'amount' => $amount, 'currency' => 'SDG',
            'source_ref' => $code,
            'payload' => array('event_name' => 'BudgetCommitted',
                'consumers' => array('الإدارةُ المعنية', 'المالية'),
                'available_before' => $available, 'available_after' => $available - $amount),
        ));
        return array('idempotent' => false, 'id' => $id, 'commit_code' => $code,
            'available_before' => $available, 'available_after' => round($available - $amount, 2));
    }

    /** العكس: تحريرُ الالتزام عند الإلغاء — بسببٍ مكتوبٍ لا حذفًا. */
    public static function budgetRelease(\mysqli $db, $companyId, $commitId, $reason, $actor)
    {
        if (trim((string) $reason) === '') { throw new \RuntimeException('FIN-422: سببُ التحريرِ إلزاميّ'); }
        $st = $db->prepare("UPDATE fin_budget_commitments
                               SET state = 'released', released_reason = ?, released_at = NOW()
                             WHERE id = ? AND company_id = ? AND state = 'committed'");
        $st->bind_param('sii', $reason, $commitId, $companyId);
        $st->execute();
        $ok = $db->affected_rows > 0;
        $st->close();
        if (!$ok) { throw new \RuntimeException('FIN-409: الالتزامُ غيرُ قائمٍ أو حُرّر سلفًا'); }
        return array('ok' => true);
    }

    /* ═══ budget.approve — بفصل واجبات §9-3 ════════════════════════════════ */

    public static function budgetApprove(\mysqli $db, $companyId, $budgetId, $actor, $capacity, $authorityRef)
    {
        $st = $db->prepare("SELECT id, state, created_by, submitted_by FROM fin_budgets
                             WHERE id = ? AND company_id = ? LIMIT 1");
        $st->bind_param('ii', $budgetId, $companyId);
        $st->execute();
        $b = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$b) { throw new \RuntimeException('FIN-404: الموازنةُ غيرُ موجودة'); }
        if (!in_array($b['state'], array('submitted', 'draft'), true)) {
            throw new \RuntimeException('FIN-409: حالةُ الموازنةِ «' . $b['state'] . '» لا تقبل الاعتماد — الانتقالُ غيرُ المعرَّفِ يُرفض');
        }
        // فصلُ الواجبات: من أنشأ أو رفع لا يعتمد (§9-3)
        if ((int) $b['created_by'] === (int) $actor || (int) ($b['submitted_by'] ?? 0) === (int) $actor) {
            throw new \RuntimeException('FIN-SOD-403: من أنشأ المستندَ لا يعتمده — القيدُ بنيويّ');
        }
        $st = $db->prepare("UPDATE fin_budgets SET state = 'approved', approved_by = ?, approved_at = NOW()
                             WHERE id = ? AND company_id = ?");
        $st->bind_param('iii', $actor, $budgetId, $companyId);
        $st->execute();
        $st->close();

        EventPublisher::publishFact($db, array(
            'event_key' => 'finance.budget.approved', 'category' => 'financial',
            'source_module' => 'finance', 'company_id' => (int) $companyId,
            'entity_type' => 'fin_budget', 'entity_id' => (int) $budgetId,
            'occurred_at' => gmdate('Y-m-d H:i:s'), 'created_by' => (int) $actor ?: 1,
            'idempotency_key' => 'budget:approve:' . (int) $budgetId,
            'source_ref' => 'BUD-' . (int) $budgetId,
            'payload' => array('event_name' => 'BudgetApproved', 'consumers' => array('كلُّ الإدارات'),
                'capacity' => (string) $capacity, 'authority_ref' => (string) $authorityRef),
        ));
        return array('ok' => true, 'budget_id' => (int) $budgetId);
    }

    /* ═══ budget.request — طلبُ تعديلٍ ببيان أثرٍ إلزامي ════════════════════ */

    public static function budgetChangeRequest(\mysqli $db, $companyId, $budgetId, $lineId, $deptModule, $currentAmount, $requestedAmount, $impactNote, $actor)
    {
        if (trim((string) $impactNote) === '') {
            throw new \RuntimeException('FIN-422: بيانُ الأثرِ إلزاميٌّ — لا يُعدَّل السقفُ قبل الاعتماد');
        }
        $code = self::nextCode($db, $companyId, 'fin_budget_change_requests', 'req_code', 'BCR');
        $st = $db->prepare("INSERT INTO fin_budget_change_requests
            (company_id, req_code, budget_id, budget_line_id, dept_module,
             current_amount, requested_amount, impact_note, created_by, parent_ref)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
        $parent = 'BUD-' . (int) $budgetId;
        $cur = round((float) $currentAmount, 2);
        $req = round((float) $requestedAmount, 2);
        $st->bind_param('isiisddsis', $companyId, $code, $budgetId, $lineId, $deptModule,
            $cur, $req, $impactNote, $actor, $parent);
        $st->execute();
        if ($st->errno) { $err = $st->error; $st->close(); throw new \RuntimeException('FIN-500: ' . $err); }
        $id = (int) $db->insert_id;
        $st->close();

        EventPublisher::publishFact($db, array(
            'event_key' => 'finance.budget.change_requested', 'category' => 'financial',
            'source_module' => 'finance', 'company_id' => (int) $companyId,
            'entity_type' => 'fin_budget_change_request', 'entity_id' => $id,
            'occurred_at' => gmdate('Y-m-d H:i:s'), 'created_by' => (int) $actor ?: 1,
            'idempotency_key' => 'bcr:' . $code, 'amount' => $req, 'currency' => 'SDG',
            'source_ref' => $code,
            'payload' => array('event_name' => 'BudgetChangeRequested', 'consumers' => array('المالية')),
        ));
        return array('id' => $id, 'req_code' => $code);
    }

    /** العكس: سحبُ الطلب قبل البت. */
    public static function budgetChangeWithdraw(\mysqli $db, $companyId, $reqId, $actor)
    {
        $st = $db->prepare("UPDATE fin_budget_change_requests SET state = 'withdrawn'
                             WHERE id = ? AND company_id = ? AND state = 'submitted' AND created_by = ?");
        $st->bind_param('iii', $reqId, $companyId, $actor);
        $st->execute();
        $ok = $db->affected_rows > 0;
        $st->close();
        if (!$ok) { throw new \RuntimeException('FIN-409: لا طلبَ قائمًا لك بهذا الرقم'); }
        return array('ok' => true);
    }

    /* ═══ stmt.client.issue — تثبيتُ كشفِ العميل ═══════════════════════════ */

    public static function issueClientStatement(\mysqli $db, $gate, $companyId, $clientId, $from, $to, $actor, $capacity)
    {
        $clientId = (int) $clientId;
        if ($clientId <= 0) { throw new \RuntimeException('FIN-422: العميلُ إلزاميّ'); }
        $built = ClientStatementService::build($gate, $clientId, $from, $to);

        // النسخة: الإصدارُ الجديدُ للفترة نفسِها يَنسخ السابقَ ويشير إليه (§8-3)
        $st = $db->prepare("SELECT id, stmt_code FROM fin_client_statements
                             WHERE company_id = ? AND client_id = ? AND period_from = ? AND period_to = ?
                               AND state = 'issued' ORDER BY id DESC LIMIT 1");
        $st->bind_param('iiss', $companyId, $clientId, $from, $to);
        $st->execute();
        $prev = $st->get_result()->fetch_assoc();
        $st->close();
        $version = 1;
        if ($prev) {
            $version = 1 + (int) $db->query("SELECT COUNT(*) c FROM fin_client_statements
                WHERE company_id = {$companyId} AND client_id = {$clientId}
                  AND period_from = '" . $db->real_escape_string($from) . "'
                  AND period_to = '" . $db->real_escape_string($to) . "'")->fetch_assoc()['c'];
        }
        $idem = 'cst:' . $clientId . ':' . $from . ':' . $to . ':v' . $version;

        $t = $built['totals'];
        $code = self::nextCode($db, $companyId, 'fin_client_statements', 'stmt_code', 'CST');
        $layers = json_encode($built, JSON_UNESCAPED_UNICODE);
        $balance = (float) $t['balance'];
        $invoices = (float) $t['invoices'];
        $credits = 0.0;
        $collections = (float) $t['collections'];
        $advance = (float) $t['advance'];
        $retention = (float) $t['retention'];
        $authorityRef = 'سلطةُ إصدار الكشف — المالية (M-10 §7-1) · ' . (string) $capacity;
        $parentRef = 'CLIENT-' . $clientId;
        $supersedes = $prev ? (int) $prev['id'] : null;

        $st = $db->prepare("INSERT INTO fin_client_statements
            (company_id, stmt_code, client_id, period_from, period_to,
             invoices_total, credit_notes_total, collections_total, advance_deduction,
             retention_held, closing_balance, layers_json, supersedes_id,
             issued_by, authority_ref, parent_ref, idempotency_key)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $st->bind_param('isissddddddsiisss',
            $companyId, $code, $clientId, $from, $to,
            $invoices, $credits, $collections, $advance,
            $retention, $balance, $layers, $supersedes,
            $actor, $authorityRef, $parentRef, $idem);
        $st->execute();
        if ($st->errno) { $err = $st->error; $st->close(); throw new \RuntimeException('FIN-500: ' . $err); }
        $id = (int) $db->insert_id;
        $st->close();

        if ($prev) {
            $db->query("UPDATE fin_client_statements SET state = 'superseded'
                         WHERE id = " . (int) $prev['id'] . " AND company_id = {$companyId}");
        }

        EventPublisher::publishFact($db, array(
            'event_key' => 'finance.client_statement.issued', 'category' => 'financial',
            'source_module' => 'finance', 'company_id' => (int) $companyId,
            'entity_type' => 'fin_client_statement', 'entity_id' => $id,
            'occurred_at' => gmdate('Y-m-d H:i:s'), 'created_by' => (int) $actor ?: 1,
            'idempotency_key' => 'evt:' . $idem, 'amount' => $balance, 'currency' => 'SDG',
            'source_ref' => $code,
            'payload' => array('event_name' => 'ClientStatementIssued',
                'consumers' => array('المبيعات', 'العميل'),
                'supersedes' => $prev ? $prev['stmt_code'] : null),
        ));
        return array('id' => $id, 'stmt_code' => $code, 'version' => $version,
            'closing_balance' => $balance, 'superseded' => $prev ? $prev['stmt_code'] : null);
    }

    /* ═══ margin.compute — الهامشُ من الاعترافات الثلاثة ════════════════════ */

    public static function computeMargin(\mysqli $db, $companyId, $period, $contractId, $actor)
    {
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
            throw new \RuntimeException('FIN-422: الفترةُ YYYY-MM إلزامًا');
        }
        $contractId = $contractId ? (int) $contractId : null;

        // الإيرادُ والتكاليفُ من دفترِ الآثار (fin_financial_events عبر الروابط)
        $scopeUnit = $contractId
            ? " AND ur.project_id IN (SELECT project_id FROM contracts WHERE company_id = {$companyId} AND id = {$contractId} AND project_id IS NOT NULL)"
            : '';
        $q = "SELECT
                COALESCE(SUM(CASE WHEN l.effect_type = 'revenue_event' THEN COALESCE(fe.base_amount, fe.amount) END),0) rev,
                COALESCE(SUM(CASE WHEN l.effect_type = 'supplier_due' THEN COALESCE(fe.base_amount, fe.amount) END),0) sup,
                COALESCE(SUM(CASE WHEN l.effect_type IN ('operator_due','operator_pay') THEN COALESCE(fe.base_amount, fe.amount) END),0) op,
                COALESCE(SUM(CASE WHEN l.effect_type = 'cost_record' THEN COALESCE(fe.base_amount, fe.amount) END),0) cost
              FROM fin_event_links l
              JOIN fin_unit_records ur ON ur.id = l.parent_ref AND l.parent_kind = 'unit_record'
              LEFT JOIN fin_financial_events fe ON fe.id = l.event_id
             WHERE ur.company_id = {$companyId}
               AND DATE_FORMAT(ur.record_date, '%Y-%m') = '" . $db->real_escape_string($period) . "'"
             . $scopeUnit;
        $r = $db->query($q);
        if ($r === false) { throw new \RuntimeException('FIN-500: تعذّر حسابُ المصادر — ' . $db->error); }
        $agg = $r->fetch_assoc();
        $revenue = round((float) $agg['rev'], 2);
        $costOperators = round((float) $agg['op'], 2);
        $supplierCost = round((float) $agg['sup'], 2);
        $equipCost = round((float) $agg['cost'], 2);
        $totalCost = round($costOperators + $supplierCost + $equipCost, 2);
        $margin = round($revenue - $totalCost, 2);
        $pct = $revenue > 0 ? round($margin / $revenue * 100, 4) : null;

        // النسخة: إعادةُ الاحتساب تُنشئ صفًّا يشير للسابق ولا تكتب فوقه
        $scopeKey = 'mrg:' . $period . ':' . ($contractId ?: 'all');
        $st = $db->prepare("SELECT id FROM fin_margin_analysis
                             WHERE company_id = ? AND idempotency_key LIKE CONCAT(?, ':v%')
                             ORDER BY id DESC LIMIT 1");
        $st->bind_param('is', $companyId, $scopeKey);
        $st->execute();
        $prev = $st->get_result()->fetch_assoc();
        $st->close();
        $version = 1;
        if ($prev) {
            $c = $db->query("SELECT COUNT(*) c FROM fin_margin_analysis
                WHERE company_id = {$companyId} AND idempotency_key LIKE '" . $db->real_escape_string($scopeKey) . ":v%'");
            $version = 1 + (int) $c->fetch_assoc()['c'];
        }
        $idem = $scopeKey . ':v' . $version;
        $code = self::nextCode($db, $companyId, 'fin_margin_analysis', 'run_code', 'MRG');
        $supersedes = $prev ? (int) $prev['id'] : null;
        $parentRef = $contractId ? 'CTR-' . $contractId : 'PERIOD-' . $period;

        $st = $db->prepare("INSERT INTO fin_margin_analysis
            (company_id, run_code, period, contract_id, revenue_recognized,
             cost_operators, cost_maintenance, total_cost, margin, margin_pct,
             supersedes_id, computed_by, parent_ref, idempotency_key)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $st->bind_param('issiddddddiiss',
            $companyId, $code, $period, $contractId, $revenue,
            $costOperators, $equipCost, $totalCost, $margin, $pct,
            $supersedes, $actor, $parentRef, $idem);
        $st->execute();
        if ($st->errno) { $err = $st->error; $st->close(); throw new \RuntimeException('FIN-500: ' . $err); }
        $id = (int) $db->insert_id;
        $st->close();
        if ($prev) {
            $db->query("UPDATE fin_margin_analysis SET state = 'superseded'
                         WHERE id = " . (int) $prev['id'] . " AND company_id = {$companyId}");
        }

        EventPublisher::publishFact($db, array(
            'event_key' => 'finance.margin.computed', 'category' => 'financial',
            'source_module' => 'finance', 'company_id' => (int) $companyId,
            'entity_type' => 'fin_margin_analysis', 'entity_id' => $id,
            'occurred_at' => gmdate('Y-m-d H:i:s'), 'created_by' => (int) $actor ?: 1,
            'idempotency_key' => 'evt:' . $idem, 'amount' => $margin, 'currency' => 'SDG',
            'source_ref' => $code,
            'payload' => array('event_name' => 'MarginComputed',
                'consumers' => array('المبيعات', 'التشغيل'),
                'supplier_cost' => $supplierCost, 'version' => $version),
        ));
        return array('id' => $id, 'run_code' => $code, 'version' => $version,
            'revenue' => $revenue, 'total_cost' => $totalCost, 'margin' => $margin, 'margin_pct' => $pct);
    }

    /* ═══ cycle.measure — زمنُ الدورة بالحلقة والمعتمِد ═════════════════════ */

    public static function measureCycleTime(\mysqli $db, $companyId, $period, $actor)
    {
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
            throw new \RuntimeException('FIN-422: الفترةُ YYYY-MM إلزامًا');
        }
        // من دفتر أحداث الطلبات المالية: أزمانُ الحلقات من الطوابع المتتالية
        $q = "SELECT r.request_type rt,
                     COUNT(DISTINCT r.id) cnt,
                     AVG(TIMESTAMPDIFF(HOUR, r.created_at, COALESCE(r.updated_at, r.created_at))) total_h
                FROM fin_requests r
               WHERE r.company_id = {$companyId}
                 AND DATE_FORMAT(r.created_at, '%Y-%m') = '" . $db->real_escape_string($period) . "'
               GROUP BY r.request_type";
        $r = $db->query($q);
        if ($r === false) { throw new \RuntimeException('FIN-500: ' . $db->error); }
        $rowsOut = array();
        while ($x = $r->fetch_assoc()) {
            $rt = (string) $x['rt'];
            $idem = 'cyc:' . $period . ':' . $rt;
            $st = $db->prepare("SELECT id FROM fin_cycle_time_metrics WHERE company_id = ? AND idempotency_key = ? LIMIT 1");
            $st->bind_param('is', $companyId, $idem);
            $st->execute();
            $prev = $st->get_result()->fetch_assoc();
            $st->close();
            if ($prev) {
                // إعادةُ قياسٍ بفترة: الصفُّ السابقُ يُنسخ (لا كتابةَ فوقه)
                $db->query("UPDATE fin_cycle_time_metrics SET state = 'superseded'
                             WHERE id = " . (int) $prev['id'] . " AND company_id = {$companyId} AND state = 'measured'");
                $idem .= ':r' . time();
            }
            $code = self::nextCode($db, $companyId, 'fin_cycle_time_metrics', 'metric_code', 'CYC');
            $cnt = (int) $x['cnt'];
            $totalH = $x['total_h'] !== null ? round((float) $x['total_h'], 2) : null;
            $st = $db->prepare("INSERT INTO fin_cycle_time_metrics
                (company_id, metric_code, period, request_type, requests_count,
                 total_cycle_hours, computed_by, parent_ref, idempotency_key)
                VALUES (?,?,?,?,?,?,?,?,?)");
            $parent = 'PERIOD-' . $period;
            $st->bind_param('isssidiss', $companyId, $code, $period, $rt, $cnt, $totalH, $actor, $parent, $idem);
            $st->execute();
            if ($st->errno) { $err = $st->error; $st->close(); throw new \RuntimeException('FIN-500: ' . $err); }
            $rowsOut[] = array('metric_code' => $code, 'request_type' => $rt,
                'count' => $cnt, 'total_hours' => $totalH);
            $st->close();
        }

        EventPublisher::publishFact($db, array(
            'event_key' => 'finance.cycle_time.measured', 'category' => 'operational',
            'source_module' => 'finance', 'company_id' => (int) $companyId,
            'entity_type' => 'fin_cycle_time_metric', 'entity_id' => 0,
            'occurred_at' => gmdate('Y-m-d H:i:s'), 'created_by' => (int) $actor ?: 1,
            'idempotency_key' => 'cyc:run:' . $period . ':' . gmdate('YmdHis'),
            'source_ref' => 'CYC-' . $period,
            'payload' => array('event_name' => 'CycleTimeMeasured',
                'consumers' => array('المالية', 'الإدارات'), 'types' => count($rowsOut)),
        ));
        return array('period' => $period, 'rows' => $rowsOut);
    }
}
