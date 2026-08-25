<?php
/**
 * DepreciationRunService — احتسابُ إهلاكِ الفترةِ وعكسُه (ENG-01 · F-18 · CK-18)
 * ───────────────────────────────────────────────────────────────────────────
 * F-18 نصًّا: depr_amount = hours_from_shifts * rate_per_hour
 *             عند depr_method='usage_hours'
 * «◆ ويُحمَّل على مركزِ تكلفةِ التشغيلِ من ساعاتِه لا على مركزٍ عام»
 * «◆ القيدُ يمنع إهلاكَ معدةٍ مملوكةٍ لمورد — ومن أهلك ما لا يملك أنشأ
 *    مصروفًا لا سندَ له»  (chk_owner في القاعدة — والخدمةُ تفحصه قبلَه بالعربية)
 *
 * ولا يُحذف صفٌّ: العكسُ حركةٌ عاكسةٌ بمرجعِها (depr.reverse) تحفظ المبلغَ
 * في depr_reversed_amount وتُفرّغ الحيَّ — فالرقمُ مقروءٌ بعدَ العكسِ لا ممحوّ.
 */

namespace App\Services\Assets;

class DepreciationRunService
{
    /**
     * احتسابُ إهلاكِ فترةٍ لكلِّ معدةِ شركةٍ لها ساعاتٌ ومعدَّلُ ساعة.
     *
     * @return array{ok:bool, summary:string, posted:int, skipped_supplier:int, skipped_no_rate:int}
     */
    public static function run(\mysqli $conn, $companyId, $period, $actor = 0)
    {
        $co = (int) $companyId;
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
            return array('ok' => false, 'summary' => 'الفترة بصيغة YYYY-MM',
                'posted' => 0, 'skipped_supplier' => 0, 'skipped_no_rate' => 0);
        }

        $rows = $conn->prepare(
            "SELECT `rec_id`,`equipment_id`,`asset_id`,`owner_type`,`depr_method`,
                    `hours_from_shifts`,`depreciation_per_hour`,`depreciation_amount`
               FROM `asset_hour_reconciliations`
              WHERE `company_id`=? AND `period`=? AND `hours_from_shifts` > 0"
        );
        $rows->bind_param('is', $co, $period);
        $rows->execute();
        $res = $rows->get_result();

        $posted = 0; $skipSup = 0; $skipRate = 0; $already = 0;
        $upd = $conn->prepare(
            "UPDATE `asset_hour_reconciliations`
                SET `depreciation_amount` = ?, `hours_undepreciated` = 0,
                    `journal_ref` = ?
              WHERE `rec_id` = ? AND `owner_type` = 'company'"
        );

        while ($r = $res->fetch_assoc()) {
            // ◆ معدةُ الموردِ لا تُهلَك عندنا — والقيدُ يرفضها أيضًا لو مُرِّرت
            if ($r['owner_type'] === 'supplier') { $skipSup++; continue; }
            if ($r['depreciation_amount'] !== null) { $already++; continue; }
            $rate = $r['depreciation_per_hour'] !== null ? (float) $r['depreciation_per_hour'] : null;
            $method = (string) $r['depr_method'];
            if ($method !== 'usage_hours' || $rate === null || $rate <= 0) { $skipRate++; continue; }

            // F-18
            $amount = round((float) $r['hours_from_shifts'] * $rate, 2);
            $ref = 'DEPR-' . $period . '-' . (int) $r['rec_id'];
            $rid = (int) $r['rec_id'];
            $upd->bind_param('dsi', $amount, $ref, $rid);
            if ($upd->execute() && $conn->affected_rows === 1) { $posted++; }
        }
        $rows->close();
        $upd->close();

        return array(
            'ok' => true, 'posted' => $posted,
            'skipped_supplier' => $skipSup, 'skipped_no_rate' => $skipRate,
            'summary' => "احتسب=$posted · معدات مورد متروكة=$skipSup · "
                . "بلا معدل ساعة أو طريقة مقررة=$skipRate · محتسب سلفا=$already",
        );
    }

    /**
     * العكسُ بمرجعِه (depr.reverse) — «الإلغاءُ حركةٌ عاكسةٌ بمرجعِها».
     * لا يُحذف صفٌّ ولا يضيع رقم: المبلغُ ينتقل إلى عمودِ العكسِ ويُختم بمرجعٍ وسبب.
     *
     * @return array{ok:bool, reason:string, reversed?:float, ref?:string}
     */
    public static function reverse(\mysqli $conn, array $in)
    {
        $recId  = (int) ($in['rec_id'] ?? 0);
        $reason = trim((string) ($in['reason'] ?? ''));
        $actor  = (int) ($in['actor'] ?? 0);
        if ($recId <= 0) { return array('ok' => false, 'reason' => 'رقم السطر إلزامي'); }
        if ($reason === '') { return array('ok' => false, 'reason' => 'سبب العكس إلزامي — ولا عكس بلا سبب'); }

        $q = $conn->prepare(
            "SELECT `depreciation_amount` FROM `asset_hour_reconciliations` WHERE `rec_id`=? LIMIT 1");
        $q->bind_param('i', $recId); $q->execute();
        $row = $q->get_result()->fetch_assoc(); $q->close();
        if (!$row) { return array('ok' => false, 'reason' => 'سطر غير موجود'); }
        if ($row['depreciation_amount'] === null) {
            return array('ok' => false, 'reason' => 'لا إهلاك على هذا السطر ليعكس');
        }

        $amount = (float) $row['depreciation_amount'];
        $ref = 'REV-DEPR-' . $recId . '-' . date('YmdHis');
        $u = $conn->prepare(
            "UPDATE `asset_hour_reconciliations`
                SET `depr_reversed_amount` = COALESCE(`depr_reversed_amount`,0) + `depreciation_amount`,
                    `depr_reversal_ref`    = ?,
                    `depr_reversed_at`     = NOW(),
                    `depreciation_amount`  = NULL,
                    `hours_undepreciated`  = `hours_from_shifts`,
                    `explanation`          = CONCAT('[', ?, '] ', ?),
                    `explained_by`         = ?,
                    `explained_at`         = NOW(),
                    `state`                = 'explained'
              WHERE `rec_id` = ? AND `depreciation_amount` IS NOT NULL"
        );
        $u->bind_param('sssii', $ref, $ref, $reason, $actor, $recId);
        $u->execute();
        $n = $conn->affected_rows;
        $err = $u->error;
        $u->close();
        if ($n !== 1) { return array('ok' => false, 'reason' => $err !== '' ? $err : 'لم يتغير شيء'); }

        return array('ok' => true, 'reason' => 'عكس بمرجعه', 'reversed' => $amount, 'ref' => $ref);
    }
}
