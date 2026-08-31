<?php
/**
 * app/Services/Bus/Consumers/EffectLinkConsumer.php — عقدُ أثرِ أحداثِ الأعمال
 * ═══════════════════════════════════════════════════════════════════════════
 * `FINAL_CLOSE` البند ⑪ · `RPR-03` #٣: ثلاثةٌ وعشرون حدثَ أعمالٍ منطوقًا بلا
 * عقدِ مستهلكٍ فعّال — **والعطبُ المقيسُ لم يكن غيابَ الأثرِ بل غيابَ عقدِه**:
 * أثرُ هذه الأحداثِ يقع **ذرّيًّا عند المُنتِجِ** (مروحةُ الأثرِ `EffectFanout`
 * داخلَ معاملةِ الناشرِ — ADR-15، والحكمُ المسجَّلُ في تقاعدِ صفوفِ
 * `event_consumers` القديمةِ: «أثرُه الماليُّ باقٍ حيث كان»)، أو بمرآتِه على
 * ناقلِ الماليّةِ حيث يستهلكه الموجِّهُ، أو بسجلِّه القانونيِّ القائم.
 *
 * ◆ فهذا **مستهلكُ إنفاذِ العقدِ**: لكلِّ واقعةٍ جديدةٍ من مفرداتِه يتحقّق أنَّ
 *   الأثرَ المتعاقَدَ عليه **وقع فعلًا** بإحدى قنواتِه الثلاث:
 *   ① رابطُ مروحةِ الأثرِ في `fin_event_links` (بمعرِّفِ الحدثِ أو بأبيه) ·
 *   ② مرآةُ الواقعةِ على ناقلِ الماليّةِ `fin_financial_events` ·
 *   ③ السجلُّ القانونيُّ للكيانِ المصدرِ قائمٌ (حدثُ ما-بعد-الكتابة).
 *   فإن خلت الثلاثُ رمى `EFFECT_MISSING` — فإعادةٌ بتباعدٍ ثم عزلٌ في
 *   الرسائلِ الميتةِ **بإنذارِه** — ولا أثرَ مفقودًا بصمتٍ بعد اليوم.
 * ◆ ⛔ **ولا يكتب أثرًا ماليًّا بيدِه** — سدُّ الفجواتِ التاريخيّةِ بمساراتِ
 *   المصالحةِ وبأحكامِ سجلِّ المتراكمِ (`CTL_EVENT_BACKLOG`)، لا باختراعِ قيد.
 * ◆ الوصولُ عبرَ بوّابةِ المستأجرِ بسياقٍ خادميٍّ محوكم
 *   (`TenantContext::forSystem` — K9-M2b) — لا استعلامَ خامًّا هنا.
 *
 * العقد: handle(array $event, \mysqli $conn): string — كعقدِ الناقل.
 * ═══════════════════════════════════════════════════════════════════════════
 */

namespace App\Services\Bus\Consumers;

class EffectLinkConsumer
{
    /** كيانُ المصدرِ ⇒ جدولُه القانونيُّ (من الحمولةِ المقيسةِ للمفردات) */
    private static function entityTable($entityType)
    {
        $map = array(
            'timesheet'                   => 'timesheet',
            'unit_entry'                  => 'unit_entries',
            'unit_record'                 => 'fin_unit_records',
            'fin_unit_record'             => 'fin_unit_records',
            'contract_commitment'         => 'contract_commitments',
            'contract_advance'            => 'contract_advances',
            'fin_asset'                   => 'fin_assets',
            'fin_financial_event'         => 'fin_financial_events',
            'bank_recon_match'            => 'bank_recon_matches',
            'contract_penalty_assessment' => 'contract_penalty_assessments',
            'fin_funding_schedule'        => 'fin_funding_schedules',
            'fin_request'                 => 'fin_requests',
            'mnt_order'                   => 'mnt_order',
            'proc_issue'                  => 'proc_issue',
            'proc_landed_cost'            => 'proc_landed_cost',
            'proc_order'                  => 'proc_order',
            'settlement'                  => 'settlements',
            /* source_event: الكيانُ هو الناقلُ نفسُه (محظورُ البوّابة) —
               والتحقُّقُ له بمرآةِ EV-<entity_id> أدناه لا بقراءةِ الناقل */
        );
        return isset($map[$entityType]) ? $map[$entityType] : null;
    }

    /** parent_kind في روابطِ المروحةِ — بعُرفِ `rpr03_scorecard` المقيس */
    private static function parentKind($entityType)
    {
        if ($entityType === 'timesheet') { return 'timesheet'; }
        if ($entityType === 'fin_unit_record' || $entityType === 'unit_record') { return 'unit_record'; }
        return 'event';
    }

    private static function gateFor(\mysqli $conn, $companyId)
    {
        $core = dirname(__DIR__, 3) . '/Core';
        require_once $core . '/TenantGateException.php';
        require_once $core . '/TenantRegistry.php';
        require_once $core . '/TenantContext.php';
        require_once $core . '/TenantDb.php';
        return new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem((int) $companyId));
    }

    public static function handler()
    {
        return function (array $event, $conn) { return self::handle($event, $conn); };
    }

    public static function handle(array $event, \mysqli $conn)
    {
        $gate = self::gateFor($conn, (int) $event['company_id']);
        $eid = (int) $event['id'];
        $ent = (string) (isset($event['entity_type']) ? $event['entity_type'] : '');
        $entId = isset($event['entity_id']) ? (int) $event['entity_id'] : 0;

        /* ① رابطُ مروحةِ الأثر — بمعرِّفِ الحدثِ أو بأبيه (العُرفان معًا) */
        $l = $gate->select('fin_event_links', array('where' => array('event_id' => $eid), 'limit' => 1));
        if ($l) { return 'effect:fanout-linked(event_id)'; }
        if ($entId > 0) {
            $l = $gate->select('fin_event_links', array(
                'where' => array('parent_ref' => $entId, 'parent_kind' => self::parentKind($ent)), 'limit' => 1));
            if ($l) { return 'effect:fanout-linked(parent_ref)'; }
        }

        /* ② مرآةُ الواقعةِ على ناقلِ الماليّة — بمعرِّفِها أو بمعرِّفِ مصدرِها */
        $m = $gate->select('fin_financial_events', array('where' => array('source_ref' => 'EV-' . $eid), 'limit' => 1));
        if ($m) { return 'effect:finance-bus-mirrored'; }
        if ($ent === 'source_event' && $entId > 0) {
            $m = $gate->select('fin_financial_events', array('where' => array('source_ref' => 'EV-' . $entId), 'limit' => 1));
            if ($m) { return 'effect:finance-bus-mirrored(source)'; }
            $m = $gate->select('fin_financial_events', array('where' => array('id' => $entId), 'limit' => 1));
            if ($m) { return 'effect:finance-bus-row-standing'; }
        }

        /* ③ السجلُّ القانونيُّ للكيانِ المصدرِ قائم */
        if ($entId > 0) {
            $tbl = self::entityTable($ent);
            if ($tbl !== null) {
                $r = $gate->select($tbl, array('where' => array('id' => $entId), 'limit' => 1));
                if ($r) { return 'effect:entity-standing(' . $tbl . '#' . $entId . ')'; }
            }
        }

        throw new \RuntimeException('EFFECT_MISSING: ' . (string) $event['event_key']
            . ' — لا رابط مروحة ولا مراة مالية ولا سجل كيان قائم (كيان '
            . $ent . '#' . $entId . ') — يعزل باسمه ولا يمر بصمت');
    }
}
