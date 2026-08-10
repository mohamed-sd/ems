<?php
/**
 * ContractApprovalService — أفعالُ آلةِ حالةِ العقدِ في شاشتها المالكة (INJ-0001)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العيب (P0): `ContractStateMachine` بُنيت كاملةً باثنتي عشرةَ حالةً — ومستدعيها
 *   **الوحيدُ** في المستودعِ كلِّه شاشةُ القمة `Portal/ceo_contracts.php:167`.
 *   أمّا «عقود العملاء» — **الشاشةُ الأمُّ للإدارة** — فلا تذكر `contract_status`
 *   ولا مرةً واحدة، ولا تملك فعلًا ينقل العقدَ من «مسودة» إلى «تفاوض» إلى
 *   «معتمد». ⇒ آلةُ حالةٍ مبنيةٌ بلا بابٍ يُدخَل منه: الدورةُ المستنديةُ
 *   الموثَّقةُ لا تعمل من شاشتها.
 *
 * ◆ حارسُ السقف (FIN-01 · CEO-Y01): «المخوَّلُ يعتمد ضمنَ سقفِه ويُرفع للرئيس
 *   التنفيذيِّ إن تجاوز السقف». وهنا **الفشلُ مغلق**: دورٌ **بلا سقفٍ معرَّفٍ
 *   نافذ** لا يُقرأ «سقفًا لا نهائيًّا» بل «سلطةً غيرَ معرَّفة» ⇒ يُصعَّد.
 *   فغيابُ الحدِّ ليس إذنًا.
 */

declare(strict_types=1);

namespace App\Services\Contract;

require_once __DIR__ . '/ContractStateMachine.php';

class ContractApprovalService
{
    /** @var \mysqli */
    private $conn;

    public function __construct(\mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * قيمةُ العقدِ التقديرية — شهريٌّ × مدةٌ بالأشهر، وإلا المدفوع.
     * ◆ تُعلَن مصدرَها في الرسالةِ فلا يُقارَن رقمٌ مجهولُ الأصلِ بسقف.
     * @return array{value:float,currency:string,source:string}
     */
    public function contractValue(int $contractId): array
    {
        $st = $this->conn->prepare(
            "SELECT total_contract_permonth, contract_duration_months, paid_contract, price_currency_contract
               FROM contracts WHERE id = ? LIMIT 1");
        if (!$st) { return array('value' => 0.0, 'currency' => '', 'source' => 'تعذّر الاستعلام'); }
        $st->bind_param('i', $contractId);
        $st->execute();
        $c = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$c) { return array('value' => 0.0, 'currency' => '', 'source' => 'عقدٌ غيرُ موجود'); }

        $cur = (string) ($c['price_currency_contract'] ?? '');
        $per = (float) ($c['total_contract_permonth'] ?? 0);
        $mon = (float) ($c['contract_duration_months'] ?? 0);
        if ($per > 0 && $mon > 0) {
            return array('value' => round($per * $mon, 2), 'currency' => $cur,
                'source' => 'شهريٌّ ' . $per . ' × ' . $mon . ' شهرًا');
        }
        $paid = (float) ($c['paid_contract'] ?? 0);
        if ($paid > 0) { return array('value' => $paid, 'currency' => $cur, 'source' => 'المدفوعُ التعاقديّ'); }
        return array('value' => 0.0, 'currency' => $cur, 'source' => 'لا قيمةَ معلَنةٌ في العقد');
    }

    /**
     * سقفُ الاعتمادِ النافذُ لدورٍ — أو null إن لم يُعرَّف (⇒ تصعيدٌ لا سماح).
     * @return array{max:float,currency:string,escalates_to:int,ref:string}|null
     */
    public function capFor(int $companyId, int $roleId): ?array
    {
        $st = $this->conn->prepare(
            "SELECT max_amount, currency, escalates_to_role, authority_ref
               FROM fin_authority_caps
              WHERE company_id = ? AND scope_kind = 'role' AND scope_ref = ?
                AND active = 1
                AND (effective_from IS NULL OR effective_from <= CURDATE())
                AND (effective_to   IS NULL OR effective_to   >= CURDATE())
              ORDER BY max_amount DESC LIMIT 1");
        if (!$st) { return null; }
        $ref = (string) $roleId;
        $st->bind_param('is', $companyId, $ref);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$r) { return null; }
        return array(
            'max' => (float) $r['max_amount'], 'currency' => (string) $r['currency'],
            'escalates_to' => (int) $r['escalates_to_role'], 'ref' => (string) $r['authority_ref'],
        );
    }

    /**
     * ① «رفعٌ للتفاوض» — مسودة ← تفاوض. لا سقفَ يحكمه (لا أثرَ ماليًّا بعد).
     * @return array{ok:bool,code:int,reason:string}
     */
    public function submitForNegotiation($gate, int $companyId, int $contractId, string $note, int $actorId): array
    {
        $r = ContractStateMachine::transition(
            $this->conn, $gate, $companyId, $contractId,
            ContractStateMachine::NEGOTIATION,
            ($note !== '' ? $note : 'رُفع للتفاوضِ من شاشةِ عقودِ العملاء'), $actorId);
        return array('ok' => !empty($r['ok']), 'code' => (int) ($r['code'] ?? 0),
            'reason' => (string) ($r['reason'] ?? ''));
    }

    /**
     * ② «اعتماد» — تفاوض ← معتمد، **بحارسِ سقفٍ يُصعّد عند التجاوز**.
     * والعقدُ المعتمدُ يظهر فورًا في قائمةِ التوقيعِ لدى القمة
     * (`Portal/ceo_contracts.php` يستعلم `contract_status='معتمد'`).
     *
     * @return array{ok:bool,code:int,reason:string,escalated:bool}
     */
    public function approve($gate, int $companyId, int $contractId, string $note, int $actorId, int $actorRole): array
    {
        $val = $this->contractValue($contractId);
        $cap = $this->capFor($companyId, $actorRole);

        // ◆ فشلٌ مغلق: لا سقفَ معرَّفٌ ⇒ سلطةٌ غيرُ معرَّفةٍ ⇒ تصعيدٌ لا اعتماد.
        if ($cap === null) {
            return array('ok' => false, 'code' => 403, 'escalated' => true,
                'reason' => 'لا سقفَ اعتمادٍ نافذٌ معرَّفٌ لدورِك (' . $actorRole . ') — '
                    . 'والاعتمادُ يُصعَّد للرئيسِ التنفيذيِّ حتى يُعرَّف السقف. '
                    . 'قيمةُ العقد ' . $val['value'] . ' ' . $val['currency'] . ' (' . $val['source'] . ')');
        }
        if ($val['currency'] !== '' && $cap['currency'] !== '' && $val['currency'] !== $cap['currency']) {
            // ◆ لا تُجمع عملتان في رقمٍ ولا تُقارَنان بلا تحويلٍ معلَن.
            return array('ok' => false, 'code' => 409, 'escalated' => true,
                'reason' => 'عملةُ العقد (' . $val['currency'] . ') تخالف عملةَ السقف (' . $cap['currency']
                    . ') — لا مقارنةَ بلا تحويلٍ معلَن، والاعتمادُ يُصعَّد');
        }
        if ($val['value'] > $cap['max']) {
            return array('ok' => false, 'code' => 403, 'escalated' => true,
                'reason' => 'قيمةُ العقد ' . $val['value'] . ' ' . $val['currency']
                    . ' تتجاوز سقفَك ' . $cap['max'] . ' ' . $cap['currency']
                    . ' (' . $cap['ref'] . ') — يُصعَّد إلى الدور ' . $cap['escalates_to']);
        }

        $r = ContractStateMachine::transition(
            $this->conn, $gate, $companyId, $contractId,
            ContractStateMachine::APPROVED,
            ($note !== '' ? $note : 'اعتُمد ضمنَ السقف ' . $cap['max'] . ' ' . $cap['currency'] . ' — ' . $cap['ref']),
            $actorId);
        return array('ok' => !empty($r['ok']), 'code' => (int) ($r['code'] ?? 0), 'escalated' => false,
            'reason' => (string) ($r['reason'] ?? ''));
    }
}
