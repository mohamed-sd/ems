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

require_once __DIR__ . '/../../../includes/catch_log.php';

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
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $c'); $c = null; }
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
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'SignedEffects container'); error_log('SignedEffects container #' . $contractId . ': ' . $t->getMessage()); }

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

            /* ══ **إدراجٌ مباشرٌ كان يترك حقلين يملؤهما الناشرُ دائمًا**: هذا
                 الصفُّ يُكتب بـ`INSERT` صريحٍ لا عبر `EventPublisher`، فخلا من
                 `occurred_at` (نصٌّ فارغٌ) ومن `fiscal_period_id` (NULL) — فقرأ
                 `fes_event_contract_test` «حدثٌ بلا فترةٍ مالية» **بحقّ**، وحدثٌ
                 بلا فترةٍ لا يدخل إقفالًا ولا ميزانَ فترةٍ فيصير مالًا خارجَ
                 الزمن. والتاريخُ **يُشتقُّ من توقيعِ العقدِ** لا من لحظةِ الكتابة،
                 والفترةُ بالقاعدةِ نفسِها التي يستعملها الناشرُ
                 (`EventPublisher::resolvePeriodId`: شهرٌ يحوي التاريخ). */
            $signed = trim((string) ($c['contract_signing_date'] ?? ''));
            $occurredAt = ($signed !== '' && $signed !== '0000-00-00')
                ? gmdate('Y-m-d H:i:s', strtotime($signed)) : gmdate('Y-m-d H:i:s');
            $periodId = null;
            $ps = mysqli_prepare($conn, "SELECT id FROM fin_financial_periods
                    WHERE company_id = ? AND period_type = 'month'
                      AND ? BETWEEN start_date AND end_date LIMIT 1");
            if ($ps) {
                $pDate = substr($occurredAt, 0, 10);
                mysqli_stmt_bind_param($ps, 'is', $companyId, $pDate);
                if (mysqli_stmt_execute($ps)) {
                    $pr = mysqli_stmt_get_result($ps);
                    $prow = $pr ? mysqli_fetch_assoc($pr) : null;
                    if ($prow) { $periodId = (int) $prow['id']; }
                }
                mysqli_stmt_close($ps);
            }

            $st = mysqli_prepare($conn, "INSERT INTO fin_financial_events
                (company_id, event_no, event_type, event_key, category, source_module,
                 source_ref, entity_type, entity_id, amount, quantity, unit, currency,
                 contract_id, state, occurred_at, fiscal_period_id, created_by)
                VALUES (?, ?, 'enterprise', 'contract.commitment', 'commercial', 'sales',
                        ?, 'contract', ?, ?, ?, 'hour', ?, ?, 'draft', ?, ?, ?)");
            $srcRef = 'contract:' . $contractId;
            mysqli_stmt_bind_param($st, 'issiddsisii',
                $companyId, $no, $srcRef, $contractId, $amount, $qty, $currency, $contractId,
                $occurredAt, $periodId, $actor);
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
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'SignedEffects commitment'); error_log('SignedEffects commitment #' . $contractId . ': ' . $t->getMessage()); }

        /* ── ⑤ محرّكُ الالتزامات (update0013 · OR-01) ──────────────────────────
           «◆ الالتزامُ يُنشأ عند **اعتمادِ العقدِ** لا عند أولِ دفعة — فالعقدُ
            النافذُ يولّد جدولَ استحقاقٍ لكلِّ مدتِه فورًا، والصمتُ عنه يُخفي
            التزامًا حقيقيًّا على الشركة.» (OBL-0021)

           وهذا موضعُه الطبيعي: هنا يصير العقدُ نافذًا وتُولَّد حاويتُه ويدخل
           التزامُه الموازنة. ولا يُنشأ الجدولُ قبلَ **اختبارِ التجنبِ** (OBL-0200:
           «ولا يُترك عقدٌ بلا نتيجةِ اختبارٍ مسجَّلة») — فالاختبارُ يُجرى أولًا
           بمعطياتِ العقدِ نفسِه ثم يُبنى الجدولُ على نتيجته.

           ◆ والفشلُ هنا **لا يُسقط التوقيع**: العقدُ نافذٌ بقرارٍ إداريٍّ وقع،
             وتعثُّرُ توليدِ جدولِه عيبٌ يُسجَّل ويُعالَج لا سببٌ لنقضِ القرار. */
        $out['obligation_id'] = null;
        try {
            require_once dirname(__DIR__) . '/Finance/ObligationEngine.php';
            $ref  = 'contract:' . $contractId;
            $kind = 'client';
            $eng  = 'App\Services\Finance\ObligationEngine';

            $existing = $eng::activeObligation($conn, $companyId, $kind, $ref);
            if ($existing) {
                $out['obligation_id'] = intval($existing['id']);
            } else {
                /* ◆ أسماءُ الأعمدةِ من `contracts` الحيِّ لا من افتراض:
                     البدءُ `actual_start` وإلا `contract_signing_date` ·
                     والانتهاءُ `actual_end` وإلا البدءُ + `contract_duration_months`
                     — فالمدةُ منصوصةٌ والنهايةُ مشتقةٌ منها لا مخترعة. */
                $start = (string) ($c['actual_start'] ?? '');
                if ($start === '' || $start === '0000-00-00') { $start = (string) ($c['contract_signing_date'] ?? ''); }
                $end = (string) ($c['actual_end'] ?? '');
                if (($end === '' || $end === '0000-00-00') && $start !== '' && $start !== '0000-00-00') {
                    $months = (int) ($c['contract_duration_months'] ?? 0);
                    $days   = (int) ($c['contract_duration_days'] ?? 0);
                    if ($months > 0)    { $end = date('Y-m-d', strtotime($start . ' +' . $months . ' months -1 day')); }
                    elseif ($days > 0)  { $end = date('Y-m-d', strtotime($start . ' +' . $days . ' days -1 day')); }
                }
                /* القيمةُ من سجلِّ التوقيعِ إن مُرِّرت — وإلا فمن العقدِ صراحةً.
                   ولا تُلفَّق: الصفرُ المعلَنُ أصدقُ من رقمٍ مخترع. */
                $value = (float) ($ctx['amount'] ?? 0);
                if ($value <= 0) { $value = (float) ($c['total_contract_permonth'] ?? 0) * max(1, (int) ($c['contract_duration_months'] ?? 0)); }
                $cur = (string) ($ctx['currency'] ?? ($c['price_currency_contract'] ?? 'USD'));
                if (trim($cur) === '') { $cur = 'USD'; }

                if ($start !== '' && $end !== '' && $start !== '0000-00-00' && $end !== '0000-00-00') {
                    /* ◆ الحقولُ الحاكمةُ الإلزامية (OBL-0058..0085) تُفحص عند
                         النفاذِ — «ولا يُقبل عقدٌ بلا طرفين مسمَّيين» و«ولا
                         استحقاقَ بلا عملة» و«ولا استحقاقَ بلا سعرِ وحدة».
                         والوضعُ متدرِّجٌ كسائرِ حراسِ الحزمة: `monitor` يسجّل
                         و`enforce` يمنع توليدَ الجدولِ ولا يُسقط التوقيع. */
                    $cfMode = function_exists('ems_env') ? (string) ems_env('EMS_U13_CFIELD_GATE', 'monitor') : 'monitor';
                    if ($cfMode !== 'off') {
                        $cf = $eng::assertContractFields($conn, $companyId, $contractId);
                        $out['contract_fields'] = $cf;
                        if (empty($cf['ok'])) {
                            if (function_exists('log_security_event')) {
                                log_security_event('U13_CFIELD_MISSING', 'عقد #' . $contractId . ' — ' . $cf['reason']);
                            } else {
                                error_log('U13_CFIELD_MISSING #' . $contractId . ': ' . $cf['reason']);
                            }
                            if ($cfMode === 'enforce') {
                                $out['obligation_error'] = $cf['reason'];
                                throw new \RuntimeException($cf['reason']);
                            }
                        }
                    }
                    /* AV-1..AV-5 — بمعطياتِ العقدِ الحيةِ لا بافتراض. */
                    $eng::avoidanceTest($conn, array(
                        'company_id'     => $companyId,
                        'contract_kind'  => $kind,
                        'contract_ref'   => $ref,
                        'contract_value' => $value,
                        'currency'       => $cur,
                        /* AV-1: «أالعقدُ قابلٌ للإلغاءِ من طرفنا بلا تكلفةٍ جوهرية؟»
                           — والعقدُ الذي فيه ضماناتٌ أو احتجازٌ ليس كذلك. */
                        'cancellable'    => (trim((string) ($c['guarantees'] ?? '')) === ''
                                             && (float) ($c['retention_pct'] ?? 0) <= 0) ? 1 : 0,
                        /* AV-2: «قيمةُ الشرطِ الجزائيِّ أو تكلفةِ الإنهاءِ أو الحدِّ
                           الأدنى المضمون · أعلاها» — والاحتجازُ أقربُ ما يُقاس عليه هنا. */
                        'cancel_cost'    => round($value * ((float) ($c['retention_pct'] ?? 0) / 100), 2),
                        'min_guaranteed' => (float) ($c['total_contract_units'] ?? 0) > 0
                                            ? round($value * 0, 2) : 0,
                        'decided_by'     => (int) ($ctx['actor'] ?? 0),
                    ));
                    $r = $eng::generateSchedule($conn, array(
                        'company_id'    => $companyId,
                        'ob_type'       => 'OB-01',
                        'side'          => 'receivable',   // عقدُ عميلٍ — الاتجاهُ قبضٌ لا دفع
                        'contract_kind' => $kind,
                        'contract_ref'  => $ref,
                        'counterparty'  => mb_substr((string) ($c['second_party'] ?? ''), 0, 200),
                        'total_value'   => $value,
                        'currency'      => $cur,
                        'start_date'    => $start,
                        'end_date'      => $end,
                        'project_id'    => !empty($c['project_id']) ? (int) $c['project_id'] : null,
                        'site_id'       => !empty($c['site_id']) ? (int) $c['site_id'] : null,
                        'party_type'    => 'customer',
                        'generated_by'  => (int) ($ctx['actor'] ?? 0),
                    ));
                    if (!empty($r['ok'])) { $out['obligation_id'] = intval($r['obligation_id']); }
                    else { error_log('SignedEffects obligation #' . $contractId . ': ' . (string) $r['reason']); }
                }
            }
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'SignedEffects obligation #');
            error_log('SignedEffects obligation #' . $contractId . ': ' . $t->getMessage());
        }

        return $out;
    }
}
