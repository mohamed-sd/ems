<?php
/**
 * app/Services/Bus/Consumers/FxRealizationConsumer.php — مستهلكُ صرفِ العملة
 * ═══════════════════════════════════════════════════════════════════════════
 * `FINAL_CLOSE` البند ⑨ · `RPR-03` #١٨: صفُّ `fx` في `ems_event_consumers`
 * كان **يتيمًا** — لا `register()` له فلن يتحرّك مؤشِّرُه أبدًا. والعلاجُ
 * المنصوص «توصيلُ معالجٍ أو تقاعدٌ بحكمٍ — لا استئنافُ عامل».
 *
 * ◆ **عقدُ الأثر** (من `ctl_event_effect_crosswalk` · قاعدة `R-FX`): أثرُ
 *   `fx` على الحدثِ هو **تحقُّقُ القيمةِ الأساس**: `base_amount` مملوءٌ أو
 *   العملةُ هي عملةُ الأساس. والناشرُ يملأ الأساسَ عند النشرِ منذ FX-01
 *   (`base = amount × rate`) — فعملُ المستهلكِ **تحقُّقٌ وسدُّ نقص**:
 *   ① محقَّقُ الأثرِ سلفًا ⇒ يمرُّ (لا كتابةَ — الأثرُ وقع عند المُنتِج).
 *   ② ناقصُ الأساسِ وعملتُه أجنبيّةٌ وله **سعرٌ مسجَّلٌ** بتاريخِ وقوعِه ⇒
 *      تُملأ `fx_rate`/`base_amount` من السعرِ المسجَّلِ — اشتقاقٌ حتميٌّ
 *      من سجلِّ التسعيرِ لا اختراعُ قيمة.
 *   ③ ناقصُ الأساسِ **بلا سعرٍ مسجَّل** ⇒ يفشل باسمِ سببِه
 *      (`FX_RATE_MISSING`) فيمرُّ بدورةِ الإعادةِ ثم الرسائلِ الميتةِ
 *      بإنذارِها — فالسعرُ قرارُ ماليّةٍ (التسعيرُ اليوميُّ) لا قرارُ مستهلك.
 * ◆ ⛔ **ولا ترحيلَ ماليًّا هنا**: لا قيدَ ولا `fin_fx_differences` — فروقُ
 *   الصرفِ للتسويةِ بمسارِها (`FxSettlementService`) بيدِ أصحابِها.
 * ◆ **والقراءاتُ من طبقةِ `includes/fx.php` المسجَّلةِ** (سجلُّ SEC-006) —
 *   لا استعلامَ خامًّا في هذا الملفِّ (سقّاطةُ GAP-29 لا تُزاد).
 * ═══════════════════════════════════════════════════════════════════════════
 */

namespace App\Services\Bus\Consumers;

class FxRealizationConsumer
{
    const NAME = 'fx';

    public static function handler()
    {
        require_once dirname(__DIR__, 4) . '/includes/fx.php';
        return function (array $event, $conn) {
            $hasBase = ($event['base_amount'] !== null && $event['base_amount'] !== '');
            $cur = trim((string) (isset($event['currency']) ? $event['currency'] : ''));
            $base = ems_fx_base_for_company($conn, (int) $event['company_id']);
            if ($hasBase || $cur === '' || ($base !== '' && $cur === $base)) {
                return; // ① الأثرُ محقَّقٌ سلفًا عند المُنتِج — تحقُّقٌ وتمرير
            }
            $date = substr((string) $event['occurred_at'], 0, 10);
            $rate = ems_fx_rate_for_company($conn, (int) $event['company_id'], $cur, $date);
            if ($rate === null || $rate <= 0) {
                // ③ لا سعرَ مسجَّلًا — قرارُ ماليّةٍ لا اختراعُ مستهلك
                throw new \RuntimeException('FX_RATE_MISSING: لا سعر مسجل للعملة ' . $cur
                    . ' بتاريخ ' . $date . ' — يسجل في التسعير اليومي ثم يعاد');
            }
            // ② اشتقاقٌ حتميٌّ من السعرِ المسجَّل — والعطالةُ بشرطِ IS NULL
            $baseAmt = round(((float) $event['amount']) * $rate, 2);
            if (!ems_fx_realize_event_base($conn, (int) $event['id'], $rate, $baseAmt)) {
                throw new \RuntimeException('FX_WRITE_FAILED: ' . $conn->error);
            }
        };
    }
}
