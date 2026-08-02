<?php
/**
 * app/Services/Capacity/CapacityAtomicCommit.php — المعاملةُ الذرية (CAP-28)
 * ═══════════════════════════════════════════════════════════════════════════
 * CAP-01 §14: «خمسُ كتاباتٍ في القاعدة داخل معاملةٍ ذريةٍ واحدة — تنجح كلُّها
 * أو تفشل كلُّها. والنشرُ بعد نجاحها لا داخلَها»:
 *   ① تثبيتُ سجل الوحدة القانوني بنسخته (يمرّره المالكُ — UX-03 يملك التايم شيت)
 *   ② كتابةُ أحكام الأطراف الثلاثة (يمرّرها صاحبُ العقود)
 *   ③ أسطرُ دفتر الاستهلاك (CapacityLedgerService)
 *   ④ مفتاحُ منع التكرار — الUQ الخماسيُّ نفسُه يقيّده مع ③ (CAP-30)
 *   ⑤ صفُّ الصادر (CapacityOutbox::enqueue) — كتابةٌ لا نشر
 *
 * C27: فشلُ إحداها → تُلغى المعاملةُ كلُّها — صفرُ وحدةٍ وصفرُ استهلاكٍ وصفرُ
 * صفٍّ في الصادر.
 * C25: مفتاحٌ مكرر → 409 بمرجع السطر القائم وصفرُ كتابةٍ في كل الطبقات.
 */

namespace App\Services\Capacity;

require_once __DIR__ . '/CapacityLedgerService.php';
require_once __DIR__ . '/CapacityOutbox.php';
require_once __DIR__ . '/CapacityEvents.php';

class CapacityAtomicCommit
{
    /**
     * @param mixed $gate بوابةُ العزل
     * @param array $steps:
     *   fix_unit  ?callable($g)  — الكتابة ① (تثبيتُ سجل الوحدة بنسخته)
     *   rulings   ?callable($g)  — الكتابة ② (أحكامُ الأطراف)
     *   lines     array          — الكتابتان ③④ (أسطرُ الدفتر بمفاتيحها)
     *   events    array          — الكتابة ⑤ (صفوفُ الصادر)
     * @return array{ok:bool,code:int,reason:string,led_ids:array,obx_ids:array,existing_led_id:?int}
     */
    public static function run($gate, array $steps, $actor = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '',
                     'led_ids' => array(), 'obx_ids' => array(), 'existing_led_id' => null);
        $lines = isset($steps['lines']) && is_array($steps['lines']) ? $steps['lines'] : array();
        $events = isset($steps['events']) && is_array($steps['events']) ? $steps['events'] : array();
        if (empty($lines)) {
            $out['code'] = 422; $out['reason'] = 'لا اعتمادَ بلا أسطرِ دفتر — الواقعةُ تُنتج أحكامَها (§13.1)';
            return $out;
        }
        // C25 قبل فتح المعاملة: مفتاحٌ مقيَّدٌ سلفًا → 409 بمرجع السطر القائم
        // (داخلَ المعاملة يبقى الـUQ حارسًا للسباق — لكن مرجعُه يضيع بتغليف
        // البوابة للاستثناءات، فالفحصُ المسبقُ يعيد المرجعَ صريحًا)
        foreach ($lines as $ln) {
            if (!isset($ln['unit_record_id'], $ln['effect_type'], $ln['effect_target_type'], $ln['effect_target_ref'])) {
                continue; // نقصُ المفاتيح يرفضه appendLine داخل المعاملة بـ422
            }
            $existing = CapacityLedgerService::findByKey($gate,
                (int) $ln['unit_record_id'],
                isset($ln['unit_record_version']) ? (int) $ln['unit_record_version'] : 0,
                (string) $ln['effect_type'], (string) $ln['effect_target_type'], (string) $ln['effect_target_ref']);
            if ($existing !== null) {
                $out['code'] = 409;
                $out['existing_led_id'] = $existing;
                $out['reason'] = 'الوحدةُ بنسختها مقيَّدةٌ سلفًا — مرجعُها led#' . $existing
                               . ' · صفرُ خصمٍ ثانٍ في كل الطبقات (C25)';
                return $out;
            }
        }
        try {
            $result = $gate->runInTransaction(function ($g) use ($steps, $lines, $events, $actor) {
                $ledIds = array(); $obxIds = array();
                // ① تثبيتُ سجل الوحدة
                if (isset($steps['fix_unit']) && is_callable($steps['fix_unit'])) {
                    $steps['fix_unit']($g);
                }
                // ② أحكامُ الأطراف
                if (isset($steps['rulings']) && is_callable($steps['rulings'])) {
                    $steps['rulings']($g);
                }
                // ③+④ أسطرُ الدفتر بمفتاح منع التكرار البنيوي
                foreach ($lines as $ln) {
                    $r = CapacityLedgerService::appendLine($g, $ln, $actor);
                    if (!$r['ok']) {
                        throw new AtomicRefused((int) $r['code'], $r['reason'],
                            isset($r['existing_led_id']) ? $r['existing_led_id'] : null);
                    }
                    $ledIds[] = (int) $r['led_id'];
                }
                // ⑤ صفوفُ الصادر — كتابةٌ داخل المعاملة، لا نشر
                foreach ($events as $e) {
                    $r = CapacityOutbox::enqueue($g, $e);
                    if (!$r['ok']) {
                        throw new AtomicRefused((int) $r['code'], 'الصادر: ' . $r['reason'], null);
                    }
                    $obxIds[] = (int) $r['obx_id'];
                }
                return array('led_ids' => $ledIds, 'obx_ids' => $obxIds);
            }, 'capacity-atomic-commit');
        } catch (AtomicRefused $t) {
            // المعاملةُ أُلغيت كلُّها (C27) — والرفضُ برمزه ومرجعِه (C25)
            $out['code'] = $t->httpCode; $out['reason'] = $t->getMessage();
            $out['existing_led_id'] = $t->existingLedId;
            return $out;
        } catch (\Throwable $t) {
            $out['code'] = 422;
            $out['reason'] = 'أُلغيت المعاملةُ كلُّها — ' . $t->getMessage() . ' (C27: صفرُ وحدةٍ وصفرُ استهلاكٍ وصفرُ صادر)';
            return $out;
        }
        $out['ok'] = true; $out['code'] = 201;
        $out['led_ids'] = $result['led_ids']; $out['obx_ids'] = $result['obx_ids'];
        $out['reason'] = 'اكتملت الكتاباتُ الخمسُ ذريًّا — ' . count($result['led_ids']) . ' سطرًا و'
                       . count($result['obx_ids']) . ' صفَّ صادر؛ والنشرُ بعد COMMIT';
        return $out;
    }
}

/** رفضٌ محمولُ الرمز داخل المعاملة — يُترجم 409/422 بعد التراجع. */
class AtomicRefused extends \RuntimeException
{
    public $httpCode;
    public $existingLedId;
    public function __construct($code, $message, $existingLedId)
    {
        parent::__construct($message);
        $this->httpCode = (int) $code;
        $this->existingLedId = $existingLedId;
    }
}
