<?php
/**
 * GovernanceWatchConsumer — الحارسُ الحوكميُّ على الناقل (ENG-01 · CK-11)
 * ───────────────────────────────────────────────────────────────────────────
 * مستهلكٌ حقيقيٌّ لا صوريّ: يفحص كلَّ واقعةٍ منشورةٍ ضدَّ قواعدِ مراقبةٍ مكتوبةٍ
 * في الكودِ (مُصدَّرةٌ مع الإصدارِ ومراجَعةٌ في الدمج)، فإن طابقت قاعدةً كتب
 * إنذارًا في fin_notifications وأعاد مرجعَه. وإن لم تطابق أعاد «فُحص ولا شيء»
 * بمرجعٍ يقول أيَّ قاعدةٍ فُحصت — فالنتيجةُ مسجَّلةٌ في الحالين ولا صمت.
 *
 * لماذا يوجد: ثلاثةٌ وخمسون نوعًا من ثمانيةٍ وخمسين كانت تُنشر بلا مستهلكٍ
 * واحد — «فالنشرُ بلا مستهلكٍ عملٌ ضائع». وأولُ قاعدةٍ فيه هي بعينِها العيبُ
 * الذي كشفته هذه الجولة: إهلاكٌ على معدةٍ مملوكةٍ لمورد.
 *
 * العقد: handle(array $event, \mysqli $conn): string — يُعيد result_ref، أو
 * يرمي استثناءً فيُسجَّل فشلًا بتباعدٍ متزايد. ولا يبتلع خطأً صامتًا.
 */

namespace App\Services\Bus\Consumers;

class GovernanceWatchConsumer
{
    /**
     * قواعدُ المراقبة — لكلِّ قاعدةٍ رمزٌ ومطابِقٌ ونصُّ إنذارٍ ومستوى تبليغ.
     * تُقرأ بالترتيب، وأولُ مطابقةٍ تكتب الإنذار.
     */
    private static function rules()
    {
        return array(
            array(
                'code'  => 'WATCH-DEPR-SUPPLIER',
                'level' => 'finance_manager',
                'match' => function (array $e) {
                    return $e['event_key'] === 'expense.depreciation.recorded'
                        && (float) ($e['amount'] ?? 0) > 0
                        && !empty($e['equipment_id']);
                },
                'text'  => 'إهلاك مسجل على معدة #%s بمبلغ %s — تحقق من ملكيتها قبل الاعتماد '
                         . '(معدة المورد لا تهلك عندنا · CK-18)',
                'args'  => array('equipment_id', 'amount'),
            ),
            array(
                'code'  => 'WATCH-REVERSAL',
                'level' => 'finance_manager',
                'match' => function (array $e) {
                    return ($e['event_status'] ?? '') === 'reversed'
                        || !empty($e['reverses_event_id']);
                },
                'text'  => 'حركة عاكسة على الواقعة #%s (%s) — تراجع ضمن دورة التدقيق',
                'args'  => array('reverses_event_id', 'event_key'),
            ),
            array(
                'code'  => 'WATCH-NO-FX',
                'level' => 'finance_manager',
                'match' => function (array $e) {
                    return $e['amount'] !== null && (float) $e['amount'] != 0.0
                        && ($e['currency'] ?? 'SDG') !== 'SDG'
                        && ($e['base_amount'] ?? null) === null;
                },
                'text'  => 'واقعة بعملة %s ومبلغ %s بلا معادل بعملة الأساس — سعر الفترة مفقود',
                'args'  => array('currency', 'amount'),
            ),
        );
    }

    /**
     * @param  array   $event صفُّ ems_business_events كاملًا
     * @return string  مرجعُ الأثر — إنذارٌ مكتوبٌ أو قرارُ «لا شيءَ يُرفع»
     */
    public function handle(array $event, \mysqli $conn)
    {
        $checked = 0;
        foreach (self::rules() as $rule) {
            $checked++;
            $fn = $rule['match'];
            if (!$fn($event)) { continue; }

            $args = array();
            foreach ($rule['args'] as $k) { $args[] = (string) ($event[$k] ?? '—'); }
            $title = vsprintf($rule['text'], $args);
            $title = mb_substr('[' . $rule['code'] . '] ' . $title, 0, 195);

            $companyId = (int) ($event['company_id'] ?? 1);
            $link = 'Governance/bus_board.php';
            $stmt = $conn->prepare(
                'INSERT INTO `fin_notifications` (`company_id`, `target_level`, `title`, `link`)
                 VALUES (?, ?, ?, ?)'
            );
            if (!$stmt) {
                throw new \RuntimeException('GovernanceWatch: prepare failed — ' . $conn->error);
            }
            $lvl = $rule['level'];
            $stmt->bind_param('isss', $companyId, $lvl, $title, $link);
            if (!$stmt->execute()) {
                $err = $stmt->error; $stmt->close();
                throw new \RuntimeException('GovernanceWatch: insert failed — ' . $err);
            }
            $nid = (int) $conn->insert_id;
            $stmt->close();
            return 'fin_notifications#' . $nid . '/' . $rule['code'];
        }

        // لا مطابقة — والقرارُ يُسجَّل بمرجعِه: كم قاعدةً فُحصت ولم تُرفع.
        return 'watch:clear/' . $checked . '/' . (string) ($event['event_key'] ?? '?');
    }
}
