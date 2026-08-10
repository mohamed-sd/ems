<?php
/**
 * app/Services/Finance/FxRevaluationService.php
 * ═══════════════════════════════════════════════════════════════════════════
 * إعادةُ تقييمِ ما ينتظر سعرَ صرف — استُخرجت من `Finance/currencies_fin.php`
 * امتثالًا لـCS-05 «لا عبارةَ كتابةٍ في ملفِّ سطح» (AC-F6).
 *
 * ◆ القاعدةُ المحفوظةُ من الأصل ولا يجوز المساسُ بها: **كلُّ صفٍّ يُقيَّم بسعرِ
 *   تاريخِه هو لا بآخرِ سعرٍ عُرف.** ولذلك الوصلُ على `effective_from` الأكبرِ
 *   الذي **لا يتجاوز** تاريخَ الواقعة، لا على أحدثِ سعرٍ مطلقًا. وخلافُ ذلك
 *   يعيد كتابةَ التاريخِ بأسعارِ اليوم — وهو ما تحظره قاعدةُ «لا رجعية» (CS-11).
 *
 * ◆ ولا تُمسُّ صفوفٌ لها معادلٌ سلفًا: الشرطُ `base_amount IS NULL` هو ما يجعل
 *   النداءَ عاطلًا (idempotent) فيُستدعى بعد كلِّ سعرٍ جديدٍ بلا خوف.
 *
 * ◆ الأساس ضربًا لا قسمة: `base = amount × rate_to_base` (FX-01).
 */

namespace App\Services\Finance;

class FxRevaluationService
{
    /**
     * تُقيّم ما ينتظر سعرًا في الدفترِ والذمم لعملةٍ واحدة.
     *
     * @param  \mysqli $conn
     * @param  int     $companyId
     * @param  string  $code      رمزُ العملةِ ISO
     * @return array{events:int,dues:int} عددُ ما قُيّم في كلٍّ
     */
    public static function revaluePending($conn, $companyId, $code)
    {
        $done = array('events' => 0, 'dues' => 0);
        $companyId = (int) $companyId;
        $code = (string) $code;

        /* ① الدفتر — مرجعُ الصفِّ تاريخُ وقوعِه `occurred_at` */
        $sqlEvents =
            "UPDATE ems_business_events be
               JOIN fin_fx_rates r
                 ON r.company_id = be.company_id
                AND r.currency_code = be.currency
                AND COALESCE(r.is_deleted, 0) = 0
                AND r.effective_from = (
                        SELECT MAX(r2.effective_from) FROM fin_fx_rates r2
                         WHERE r2.company_id = be.company_id
                           AND r2.currency_code = be.currency
                           AND COALESCE(r2.is_deleted, 0) = 0
                           AND r2.effective_from <= DATE(be.occurred_at))
                SET be.fx_rate = r.rate_to_base,
                    be.base_amount = ROUND(be.amount * r.rate_to_base, 2)
              WHERE be.company_id = ? AND be.currency = ?
                AND be.base_amount IS NULL AND be.amount IS NOT NULL";
        if ($st = $conn->prepare($sqlEvents)) {
            $st->bind_param('is', $companyId, $code);
            if ($st->execute()) { $done['events'] = $st->affected_rows; }
            $st->close();
        }

        /* ② الذمم — لا عمودَ تاريخِ استحقاقٍ فيها، فمرجعُها تاريخُ نشوئها */
        $sqlDues =
            "UPDATE fin_dues d
               JOIN fin_fx_rates r
                 ON r.company_id = d.company_id
                AND r.currency_code = d.currency
                AND COALESCE(r.is_deleted, 0) = 0
                AND r.effective_from = (
                        SELECT MAX(r2.effective_from) FROM fin_fx_rates r2
                         WHERE r2.company_id = d.company_id
                           AND r2.currency_code = d.currency
                           AND COALESCE(r2.is_deleted, 0) = 0
                           AND r2.effective_from <= DATE(d.created_at))
                SET d.fx_rate = r.rate_to_base,
                    d.base_amount = ROUND(d.amount * r.rate_to_base, 2)
              WHERE d.company_id = ? AND d.currency = ?
                AND d.base_amount IS NULL AND d.amount IS NOT NULL
                AND COALESCE(d.is_deleted, 0) = 0";
        if ($st = $conn->prepare($sqlDues)) {
            $st->bind_param('is', $companyId, $code);
            if ($st->execute()) { $done['dues'] = $st->affected_rows; }
            $st->close();
        }

        return $done;
    }
}
