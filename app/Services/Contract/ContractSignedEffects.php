<?php
/**
 * app/Services/Contract/ContractSignedEffects.php — أثرُ التوقيع الرباعي (M-00 ④-٣)
 * ═══════════════════════════════════════════════════════════════════════════
 * «وبالتوقيع يصير العقدُ نافذًا وتُولَّد الحاويةُ ويدخل الالتزامُ الموازنةَ
 * والتدفق ويُقيَّد في السجل الموحَّد» (OWN-046-ج · SCN-959-ج):
 *   ① النفاذ — تُنفذه آلةُ الحالة نفسُها (موقَّع ← نافذ مسارُها المشروع).
 *   ② السجل الموحَّد — يقرأ contracts مباشرةً فالتوقيعُ يظهر فيه بلا نسخ.
 *   ③ الحاوية — رأسُ حاويةٍ رئيسيةٍ في op_containers من العقد (H-01: «لا
 *      حاويةَ تُفتح إلا من عقدٍ نافذ») — عطالتُها بوحدانية (العقد · رئيسية).
 *   ④ الالتزام — بنمط المروحة المالية: خريطةُ الأثر fin_effect_map تُعلن
 *      contract_signed، وصفُّ الأثر في fin_financial_events (enterprise ·
 *      contract.commitment)، والعطالةُ بوصلة fin_event_links كسائر الآثار.
 * القيمةُ لا تُلفَّق: إن لم تُمرَّر من سجل التوقيع تُسجَّل صفرًا معلَنةً
 * بكمية ساعات العقد — والاستكمالُ من سجل التوقيع لا اختراعًا.
 */

namespace App\Services\Contract;

class ContractSignedEffects
{
    /**
     * تطبيق الأثرين ③ و④ بعطالةٍ صارمة — يُستدعى من آلة الحالة عند «موقَّع».
     * @param array $ctx amount/currency (إن عُرفا من سجل التوقيع) · actor
     * @return array{container_id:?int,commitment_id:?int}
     */
    public static function apply($conn, $gate, $companyId, $contractId, array $ctx = array())
    {
        $out = array('container_id' => null, 'commitment_id' => null);
        $companyId = (int) $companyId;
        $contractId = (int) $contractId;
        if ($companyId <= 0 || $contractId <= 0) { return $out; }

        $c = null;
        try { $c = $gate->selectOne('contracts', array('where' => array('id' => $contractId))); }
        catch (\Throwable $t) { $c = null; }
        if (!$c) { return $out; }

        /* ── ③ الحاوية الرئيسية — عطالة بوحدانية (company · contract · رئيسية) ── */
        try {
            $rs = mysqli_query($conn, "SELECT id FROM op_containers
                WHERE company_id = {$companyId} AND contract_id = {$contractId}
                  AND level = 'رئيسية' AND COALESCE(is_deleted,0) = 0 LIMIT 1");
            $exists = $rs ? mysqli_fetch_assoc($rs) : null;
            if ($exists) {
                $out['container_id'] = (int) $exists['id'];
            } else {
                $capQty = (float) ($c['forecasted_contracted_hours'] ?? 0);
                if ($capQty <= 0) {
                    $capQty = (float) ($c['hours_monthly_target'] ?? 0)
                            * max(1, (int) ($c['contract_duration_months'] ?? 0));
                }
                $no = 'CNT-' . $contractId;
                $st = mysqli_prepare($conn, "INSERT INTO op_containers
                    (company_id, container_no, level, contract_id, unit_type, work_model,
                     cap_qty, state, origin, origin_note, created_by)
                    VALUES (?, ?, 'رئيسية', ?, 'hour', NULL, ?, 'نشطة', 'عقد', ?, ?)");
                $note = 'وُلّدت آليًّا بتوقيع العقد (M-00 ④-٣) — السعة من ساعات العقد'
                      . ($capQty <= 0 ? ' (صفرٌ معلَن: لا ساعات مثبتة في العقد)' : '');
                $actor = (int) ($ctx['actor'] ?? 0);
                mysqli_stmt_bind_param($st, 'isidsi', $companyId, $no, $contractId, $capQty, $note, $actor);
                if (mysqli_stmt_execute($st)) { $out['container_id'] = (int) mysqli_insert_id($conn); }
                mysqli_stmt_close($st);
            }
        } catch (\Throwable $t) { error_log('SignedEffects container #' . $contractId . ': ' . $t->getMessage()); }

        /* ── ④ الالتزام بنمط المروحة — الخريطة ثم الأثر ثم وصلة العطالة ────── */
        try {
            // خريطة الأثر تُعلن المصدر (تُزرع مرةً للكيان — إعلانٌ لا سلوك)
            mysqli_query($conn, "INSERT INTO fin_effect_map
                (company_id, source_kind, effect_type, effect_label, target_table, is_active)
                SELECT {$companyId}, 'contract_signed', 'budget_consumption',
                       'التزام عقدٍ موقَّع يدخل الموازنة والتدفق (M-00 ④-٣)', 'fin_financial_events', 1
                FROM DUAL WHERE NOT EXISTS (
                    SELECT 1 FROM fin_effect_map
                    WHERE company_id = {$companyId} AND source_kind = 'contract_signed'
                      AND effect_type = 'budget_consumption')");

            // أبوّة الوصلة: واقعةُ الجذر contract.signed لهذا العقد (نُشرت في emit
            // قبل هذا الأثر) — وإن تعذّرت فمعرّفُ العقد نفسُه مرجعًا معلَنًا
            $rootId = $contractId;
            $rs = mysqli_query($conn, "SELECT id FROM ems_business_events
                WHERE event_key = 'contract.signed' AND company_id = {$companyId}
                  AND entity_id = {$contractId} ORDER BY id DESC LIMIT 1");
            $root = $rs ? mysqli_fetch_assoc($rs) : null;
            if ($root) { $rootId = (int) $root['id']; }

            // عطالة الأثر: وصلة قائمة بالجذر نفسه = أثرٌ وقع سلفًا فلا يتكرر
            $rs = mysqli_query($conn, "SELECT target_id FROM fin_event_links
                WHERE company_id = {$companyId} AND parent_kind = 'event'
                  AND parent_ref = {$rootId} AND effect_type = 'budget_consumption'
                  AND target_table = 'fin_financial_events' LIMIT 1");
            $link = $rs ? mysqli_fetch_assoc($rs) : null;
            if ($link) {
                $out['commitment_id'] = (int) $link['target_id'];
                return $out;
            }

            $amount = $ctx['amount'] ?? null;
            $amount = ($amount !== null && $amount !== '' && is_numeric(str_replace(',', '', (string) $amount)))
                ? (float) str_replace(',', '', (string) $amount) : 0.0;
            $currency = trim((string) ($ctx['currency'] ?? '')) ?: trim((string) ($c['price_currency_contract'] ?? '')) ?: 'SDG';
            $qty = (float) ($c['forecasted_contracted_hours'] ?? 0);
            $no = 'CMT-' . $contractId . '-' . $companyId;
            $party = trim((string) ($c['second_party'] ?? ''));
            $actor = (int) ($ctx['actor'] ?? 0);

            $st = mysqli_prepare($conn, "INSERT INTO fin_financial_events
                (company_id, event_no, event_type, event_key, category, source_module,
                 source_ref, entity_type, entity_id, amount, quantity, unit, currency,
                 contract_id, state, created_by)
                VALUES (?, ?, 'enterprise', 'contract.commitment', 'commercial', 'sales',
                        ?, 'contract', ?, ?, ?, 'hour', ?, ?, 'draft', ?)");
            $srcRef = 'contract:' . $contractId;
            mysqli_stmt_bind_param($st, 'issiddsii',
                $companyId, $no, $srcRef, $contractId, $amount, $qty, $currency, $contractId, $actor);
            if (mysqli_stmt_execute($st)) {
                $out['commitment_id'] = (int) mysqli_insert_id($conn);
                mysqli_stmt_close($st);
                mysqli_query($conn, "INSERT INTO fin_event_links
                    (company_id, parent_kind, parent_ref, effect_type, target_table, target_id)
                    VALUES ({$companyId}, 'event', {$rootId}, 'budget_consumption',
                            'fin_financial_events', " . (int) $out['commitment_id'] . ")");
            } else {
                mysqli_stmt_close($st);
            }
        } catch (\Throwable $t) { error_log('SignedEffects commitment #' . $contractId . ': ' . $t->getMessage()); }

        return $out;
    }
}
