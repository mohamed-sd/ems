<?php
namespace App\Services\Contract;

use App\Core\EventPublisher;

require_once __DIR__ . '/../../Core/EventPublisher.php';
require_once __DIR__ . '/../EffectFanout.php';           // hourPolicy + resolveRuling (سُلَّم هـ-5)
require_once __DIR__ . '/../../../includes/attribution_events.php';

/**
 * AttributionService — خدمةُ إسناد زمن الوحدة (CON-02 §5 · §8 · T-04)
 * ═══════════════════════════════════════════════════════════════════════════
 * **الفجوةُ الأمُّ التي تسدّها:** النظامُ كان يشتقّ المسؤولَ عن زمن التوقف من
 * **حالة الساعة** لا من **بند التزامٍ في عقد العميل**. فنفادُ وقودٍ يلتزم به
 * العميلُ تعاقديًّا كان يُسجَّل `fuel_logistics_stop` ← حكمُه `none` ← فلا يُفوتر؛
 * والوثيقةُ تقول إنه **استعدادٌ مفوتر يُحتسب للمورد والمشغّل** (A1).
 *
 * ── مدخلاتُها ومخرجاتُها ──────────────────────────────────────────────────
 *   المدخل : `unit_entries.id` (هـ-2) + إسنادُ بندٍ لكل سطرِ زمن.
 *   المخرج : لكل سطرٍ **الطرفُ الملتزم وأحكامُه الثلاثة** مشتقةً من **مصفوفة
 *            العقد النافذة بتاريخ الواقعة** — لا من حالة الساعة.
 *
 * ── لماذا لقطةٌ مخزَّنة لا اشتقاقٌ حيّ (هـ-3) ────────────────────────────────
 * الأحكامُ الثلاثةُ تُكتب على السطر وقتَ القرار. بذلك تتحقق «لا رجعية» (§6)
 * مجانًا: ملحقٌ يُوقَّع اليومَ لا يغيّر فاتورةَ الشهر الماضي، لأن الفاتورةَ
 * قُرئت من لقطةٍ لا من قاعدةٍ تتبدّل تحتها.
 *
 * ── سُلَّمُ اشتقاق الأحكام الثلاثة (مُعلَنٌ لا ضمني) ──────────────────────────
 *   الفوترة (`billable`):
 *     ① `effect_on_billing = billable_standby` في المصفوفة ⇒ 1
 *     ② `= non_billable`                                  ⇒ 0
 *     ③ `= per_clause` («اقرأ البند») ⇒ حكمُ الساعة للعميل بسُلَّم هـ-5
 *   المورد (`supplier_countable`): حكمُ الساعة للمورد؛ فإن غاب أو كان
 *     `case_by_case` ⇒ يُشتق من الملتزم: يُحتسب له ما لم يكن **هو** المقصّر.
 *   المشغّل (`operator_countable`): من الملتزم كذلك — فحضورُه لا يسقط بذنب
 *     غيره. (ولا يُقرأ من `contract_hour_policies` لأن صفوفَ المشغّل هناك
 *     **سياسةُ أجرٍ** بمفتاح `operator_id` لا حكمَ ساعةٍ بمفتاح الحالة — قيس.)
 *
 * ── الأخطاء (§8) ──────────────────────────────────────────────────────────
 *   `423` مصفوفةُ العقد غيرُ معرَّفة   — ولا ارتدادَ للافتراضي (ق-2)
 *   `422` فترةُ توقفٍ بلا مسؤول
 *   `422` بندٌ خارج قائمة مصفوفة العقد
 *   `409` الواقعةُ ولّدت مالًا — لا يُكتب فوق حكمٍ ماليٍّ قائم (2026-07-28)
 *
 * ── `409` والتصحيحُ بعكس الحدث (قرارُ المالك 2026-07-28) ────────────────────
 * **حدُّ الـ409 هو وجودُ قيدٍ ماليٍّ مرتبطٍ بالواقعة** — لا حالةُ الواقعة ولا
 * دخولُها مستخلصًا. فالحالةُ رأيٌ في سير العمل، والقيدُ **مالٌ وقع**؛ وما دام
 * لم يقع مالٌ فالتصحيحُ المباشرُ أنظفُ من قيدٍ عاكسٍ لا يقابله شيء.
 *
 * ومتى وقع المال، فالأصلُ **لا يُمحى أبدًا**: يُضاف **سطرٌ عاكس** بساعاتٍ سالبةٍ
 * تلغي الأصلَ جمعًا، ثم — إن صحّح المصحِّحُ البند — سطرٌ ثالثٌ بالحكم الصحيح.
 * وهو قيدُ المحاسبة نفسُه: عكسٌ ثم إعادةُ تسجيل. ويحمل حدثُه `reverses_event_id`
 * فيصير النسبُ بين الأصل وعاكسه **في الجذر المحايد** لا في عمودٍ يُصان.
 *
 * ⛔ **خارجُ النطاق عمدًا**: `POST /units/{id}/attribution` (طبقةُ `api/` خارجَ
 *    بوابة المستأجر بقرار المالك). فلا تُبنى هنا ولا يُتحايل عليها.
 */
class AttributionService
{
    /** بنودُ §4 التسعة — عقدٌ مع المخطط (نفسُ قيم ENUM في الجداول الثلاثة). */
    const TYPES = array('fuel', 'access_road', 'loading_equipment', 'equipment_readiness',
                        'operators', 'permits_safety', 'utilities', 'catering_camp', 'force_majeure');

    /** الحالةُ الوحيدةُ التي لا بندَ التزامٍ لها (هـ-1): التشغيلُ الفعليّ. */
    const NO_OBLIGATION_STATE = 'actual_work';

    /** هل الحارسُ مُفعَّل؟ عَلَمُ الرجوع (ق-24) — يُقلب بعد ملء المصفوفات. */
    public static function enforced()
    {
        return function_exists('ems_env')
            && strtolower((string) ems_env('EMS_ATTRIBUTION_MATRIX', 'off')) === 'on';
    }

    /**
     * مصفوفةُ العقد **النافذةُ** بتاريخٍ بعينه — المُجازةُ وحدَها (ق-18).
     * المسودةُ لا تسري، والصفُّ خارجَ مدته لا يحكم.
     *
     * @return array<string,array{obligor:string,effect_on_billing:string,valid_from:string}>
     */
    public static function matrixFor($gate, $contractId, $date)
    {
        $rows = $gate->scopedQuery(
            array('scope' => array('o' => 'contract_obligations')),
            "SELECT o.obligation_type, o.obligor, o.effect_on_billing, o.valid_from
               FROM contract_obligations o
              WHERE {TENANT_SCOPE} AND o.is_deleted = 0
                AND o.client_contract_id = ?
                AND o.approval_state = 'approved'
                AND o.valid_from <= ?
                AND (o.valid_to IS NULL OR o.valid_to >= ?)
              ORDER BY o.valid_from DESC",
            array(intval($contractId), $date, $date)
        );
        $out = array();
        foreach ($rows as $r) {
            $t = strval($r['obligation_type']);
            if (isset($out[$t])) { continue; } // الأحدثُ سريانًا يغلب
            $out[$t] = array(
                'obligor' => strval($r['obligor']),
                'effect_on_billing' => strval($r['effect_on_billing']),
                'valid_from' => strval($r['valid_from']),
            );
        }
        return $out;
    }

    /**
     * الأحكامُ الثلاثةُ لسطرٍ واحد — قلبُ §5.
     * قراءةٌ خالصةٌ بلا كتابة، فتصلح للمعاينة كما تصلح للقرار.
     *
     * @param array   $matrix   مخرَجُ matrixFor()
     * @param string  $state    حالةُ الساعة
     * @param ?string $oblig    بندُ الالتزام — NULL للتشغيل الفعليّ
     * @param array   $polClient حكمُ الساعة للعميل (مخرَج EffectFanout::hourPolicy)
     * @param array   $polSupp   حكمُ الساعة للمورد
     * @return array{obligor:?string,billable:?int,supplier_countable:?int,operator_countable:?int,basis:string}
     */
    public static function rulings(array $matrix, $state, $oblig,
                                   array $polClient = array(), array $polSupp = array())
    {
        // التشغيلُ الفعليُّ لا بندَ له ولا إسناد — يُفوتر ويُحتسب للجميع
        if ($state === self::NO_OBLIGATION_STATE || $oblig === null || $oblig === '') {
            return array('obligor' => null, 'billable' => 1,
                         'supplier_countable' => 1, 'operator_countable' => 1,
                         'basis' => 'actual_work');
        }

        $obligor = isset($matrix[$oblig]) ? $matrix[$oblig]['obligor'] : null;
        $effect  = isset($matrix[$oblig]) ? $matrix[$oblig]['effect_on_billing'] : 'per_clause';

        // ── ① الفوترة ──
        $billable = null; $basis = 'matrix';
        if ($effect === 'billable_standby') {
            $billable = 1;
        } elseif ($effect === 'non_billable') {
            $billable = 0;
        } else {
            // per_clause — «اقرأ البند»: حكمُ الساعة للعميل بسُلَّم الأخصّ (هـ-5)
            $basis = 'hour_policy';
            $r = \App\Services\EffectFanout::resolveRuling($polClient, $state, $oblig);
            if ($r['ruling'] === 'full' || $r['ruling'] === 'pct') { $billable = 1; }
            elseif ($r['ruling'] === 'none') { $billable = 0; }
            // pending/case_by_case ⇒ NULL: لا يُخترع حكم (قاعدة عدم التلفيق)
        }

        // ── ② المورد ──
        $supplier = null;
        $rs = \App\Services\EffectFanout::resolveRuling($polSupp, $state, $oblig);
        if ($rs['ruling'] === 'full' || $rs['ruling'] === 'pct') { $supplier = 1; }
        elseif ($rs['ruling'] === 'none') { $supplier = 0; }
        if ($supplier === null && $obligor !== null) {
            // اشتقاقٌ من الملتزم: يُحتسب للمورد ما لم يكن هو المقصّر
            $supplier = ($obligor === 'supplier') ? 0 : 1;
        }

        // ── ③ المشغّل: حضورُه لا يسقط بذنب غيره ──
        $operator = ($obligor === null) ? null : (($obligor === 'operator') ? 0 : 1);

        return array('obligor' => $obligor, 'billable' => $billable,
                     'supplier_countable' => $supplier, 'operator_countable' => $operator,
                     'basis' => $basis);
    }

    /**
     * فحصُ قابلية الإسناد قبل الاعتماد — يقرأ ولا يكتب.
     * هذا ما يستدعيه الحارسُ عند اعتماد الموقع (ق-5).
     *
     * @return array{ok:bool,code:int,reasons:array,lines:array}
     */
    public static function assertAttributable($conn, $gate, $companyId, $entryId)
    {
        $entry = self::entryOf($conn, $companyId, $entryId);
        if (!$entry) {
            return array('ok' => false, 'code' => 404, 'reasons' => array('الواقعة غير موجودة'), 'lines' => array());
        }
        $contractId = intval($entry['contract_id']);
        $date = strval($entry['entry_date']);

        $lines = self::linesOf($conn, $companyId, $entryId);
        // سطورُ التوقف والاستعداد وحدَها تحتاج إسنادًا — التشغيلُ الفعليُّ لا بندَ له
        $needy = array();
        foreach ($lines as $l) {
            if ($l['ops_state'] !== self::NO_OBLIGATION_STATE && (float) $l['hours'] > 0) {
                $needy[] = $l;
            }
        }
        if (empty($needy)) {
            return array('ok' => true, 'code' => 200, 'reasons' => array(), 'lines' => $lines);
        }

        // ── 423: العقدُ بلا مصفوفة — ولا ارتدادَ للافتراضي (ق-2) ──
        if ($contractId <= 0) {
            return array('ok' => false, 'code' => 423, 'lines' => $lines,
                         'reasons' => array('الواقعةُ بلا عقدٍ مرتبط — ولا مصفوفةَ التزاماتٍ تُقرأ منها المسؤولية'));
        }
        $matrix = self::matrixFor($gate, $contractId, $date);
        if (empty($matrix)) {
            return array('ok' => false, 'code' => 423, 'lines' => $lines,
                         'reasons' => array('مصفوفةُ التزامات العقد #' . $contractId
                             . ' غيرُ معرَّفةٍ أو غيرُ مُجازةٍ بتاريخ ' . $date
                             . ' — تُملأ من شاشة «مصفوفة التزامات العقد» وتُجيزها المالية'));
        }

        // ── 422: فترةٌ بلا مسؤول · وبندٌ خارج قائمة العقد ──
        $reasons = array();
        foreach ($needy as $l) {
            $ob = ($l['obligation_type'] === null || $l['obligation_type'] === '') ? null : $l['obligation_type'];
            $label = self::lineLabel($l);
            if ($ob === null) {
                $reasons[] = $label . ': زمنُ توقفٍ بلا بندِ التزامٍ مُسنَد — لا تُقفل واقعةٌ بزمنٍ بلا مسؤول (§5)';
                continue;
            }
            if (!isset($matrix[$ob])) {
                $reasons[] = $label . ': البندُ «' . $ob . '» خارج مصفوفة العقد #' . $contractId
                           . ' النافذةِ بتاريخ ' . $date;
            }
        }
        if (!empty($reasons)) {
            return array('ok' => false, 'code' => 422, 'reasons' => $reasons, 'lines' => $lines);
        }
        return array('ok' => true, 'code' => 200, 'reasons' => array(), 'lines' => $lines);
    }

    /**
     * قرارُ الإسناد: يكتب البندَ والأحكامَ الثلاثةَ لقطةً على سطور الزمن.
     * ذرّيٌّ داخل `runInTransaction` — إما كلُّ السطور أو لا شيء.
     *
     * @param array $assign  خريطةُ `unit_time_log.id => obligation_type`
     * @return array{ok:bool,code:int,reasons:array,decided:int}
     */
    public static function decide($conn, $gate, $companyId, $entryId, array $assign, $actor)
    {
        $entry = self::entryOf($conn, $companyId, $entryId);
        if (!$entry) {
            return array('ok' => false, 'code' => 404, 'reasons' => array('الواقعة غير موجودة'), 'decided' => 0);
        }
        $contractId = intval($entry['contract_id']);
        $date = strval($entry['entry_date']);

        // ── 409: الواقعةُ ولّدت مالًا — لا يُكتب فوق حكمٍ ماليٍّ قائم ──
        $posted = self::postedLedger($conn, $companyId, $entryId);
        if ($posted['count'] > 0) {
            return array('ok' => false, 'code' => 409, 'decided' => 0, 'posted' => $posted,
                         'reasons' => array(self::conflictReason($entryId, $posted)));
        }

        if ($contractId <= 0) {
            return array('ok' => false, 'code' => 423, 'decided' => 0,
                         'reasons' => array('الواقعةُ بلا عقدٍ مرتبط — لا مصفوفةَ تُقرأ'));
        }
        $matrix = self::matrixFor($gate, $contractId, $date);
        if (empty($matrix)) {
            return array('ok' => false, 'code' => 423, 'decided' => 0,
                         'reasons' => array('مصفوفةُ التزامات العقد #' . $contractId . ' غيرُ مُجازةٍ بتاريخ ' . $date));
        }

        $polClient = \App\Services\EffectFanout::hourPolicy($gate, $companyId, 'client', $contractId, $date);
        $polSupp   = \App\Services\EffectFanout::hourPolicy($gate, $companyId, 'supplier', $contractId, $date);

        $lines = self::linesOf($conn, $companyId, $entryId);
        $byId = array();
        foreach ($lines as $l) { $byId[intval($l['id'])] = $l; }

        // ── تحققٌ كاملٌ قبل أي كتابة: لا قرارَ نصفيّ ──
        $reasons = array(); $plan = array();
        foreach ($assign as $lineId => $ob) {
            $lineId = intval($lineId);
            if (!isset($byId[$lineId])) {
                $reasons[] = 'سطرُ زمنٍ #' . $lineId . ' ليس من هذه الواقعة';
                continue;
            }
            $l = $byId[$lineId];
            $ob = ($ob === null || $ob === '') ? null : strval($ob);
            if ($l['ops_state'] === self::NO_OBLIGATION_STATE) {
                $ob = null; // التشغيلُ الفعليُّ لا بندَ له (هـ-1)
            } elseif ($ob === null) {
                $reasons[] = self::lineLabel($l) . ': لا بندَ التزامٍ مُسنَد (422)';
                continue;
            } elseif (!in_array($ob, self::TYPES, true)) {
                $reasons[] = self::lineLabel($l) . ': بندٌ غيرُ معروف «' . $ob . '»';
                continue;
            } elseif (!isset($matrix[$ob])) {
                $reasons[] = self::lineLabel($l) . ': البندُ خارج مصفوفة العقد النافذة (422)';
                continue;
            }
            $plan[$lineId] = array('line' => $l, 'oblig' => $ob,
                                   'r' => self::rulings($matrix, $l['ops_state'], $ob, $polClient, $polSupp));
        }
        if (!empty($reasons)) {
            return array('ok' => false, 'code' => 422, 'reasons' => $reasons, 'decided' => 0);
        }

        $now = gmdate('Y-m-d H:i:s');
        $n = 0;
        $gate->runInTransaction(function ($g) use ($plan, $actor, $now, &$n) {
            foreach ($plan as $lineId => $p) {
                $g->update('unit_time_log', array(
                    'obligation_type'    => $p['oblig'],
                    'billable'           => $p['r']['billable'],
                    'supplier_countable' => $p['r']['supplier_countable'],
                    'operator_countable' => $p['r']['operator_countable'],
                    'decided_by'         => intval($actor),
                    'decided_at'         => $now,
                ), array('id' => $lineId));
                $n++;
            }
        });

        // الحدثُ إشهارٌ لا شرطُ صحة — فشلُه يُسجَّل ولا يُسقط القرار
        self::emit($conn, $companyId, $entryId, EMS_EVT_ATTRIBUTION_DECIDED, $actor, array(
            'contract_id' => $contractId, 'work_date' => $date, 'lines' => $n,
            'assignments' => array_map(function ($p) {
                return array('obligation_type' => $p['oblig'], 'obligor' => $p['r']['obligor'],
                             'billable' => $p['r']['billable']);
            }, $plan),
        ));

        return array('ok' => true, 'code' => 200, 'reasons' => array(), 'decided' => $n);
    }

    /**
     * ⑨ حكمُ **الكمية** — البُعدُ الأول في عقود الطن/المتر (M-24 · §3-④).
     * ═══════════════════════════════════════════════════════════════════════
     * `decide()` تحكم **زمنَ** الواقعة، وهذه تحكم **كميتَها**: «إعادةُ التنفيذ
     * لعيبٍ لا تُفوتر» — فالمترُ الذي أُعيد تنفيذُه لعيبٍ لا يُدفع مرتين.
     *
     * الحرّاسُ الثلاثة، وكلٌّ منها وقع مرةً في هذا النظام:
     *   • **409 إن ولّدت الواقعةُ مالًا** — لا يُكتب فوق حكمٍ ماليٍّ قائم؛
     *     والتصحيحُ بعده يخصّ محرّكَ العكسيات (نفسُ حدّ `decide()` بالحرف).
     *   • **السببُ إلزامٌ عند المنع** — حكمٌ يمنع مالًا بلا سببٍ مكتوبٍ لا
     *     يُدقَّق بعد سنتين (قاعدةُ عدم التلفيق في اتجاهها الثاني).
     *   • **العطالة**: الحكمُ نفسُه مرتين يُرجع 200 بلا كتابةٍ ثانيةٍ ولا حدثٍ ثانٍ.
     *
     * @param  int|null $billable 0 = لا تُفوتر · 1 = تُفوتر صراحةً · null = رفعُ الحكم
     * @return array{ok:bool,code:int,reasons:array,changed:bool}
     */
    public static function ruleQty($conn, $gate, $companyId, $entryId, $billable, $note, $actor)
    {
        $entry = self::entryOf($conn, $companyId, $entryId);
        if (!$entry) {
            return array('ok' => false, 'code' => 404, 'changed' => false,
                         'reasons' => array('الواقعة غير موجودة'));
        }

        if ($billable !== null && !in_array((int) $billable, array(0, 1), true)) {
            return array('ok' => false, 'code' => 422, 'changed' => false,
                         'reasons' => array('حكمُ الكمية إما «تُفوتر» أو «لا تُفوتر»'));
        }
        $note = trim((string) $note);
        if ($billable !== null && (int) $billable === 0 && $note === '') {
            return array('ok' => false, 'code' => 422, 'changed' => false,
                         'reasons' => array('سببُ منع فوترة الكمية إلزامي — لا مالَ يُمنع بلا سببٍ مكتوب'));
        }

        // العطالةُ قبل كل شيء: الحكمُ نفسُه لا يُكتب مرتين ولا يُنشر مرتين
        $curB = ($entry['qty_billable'] === null) ? null : (int) $entry['qty_billable'];
        $curN = ($entry['qty_ruling_note'] === null) ? '' : trim((string) $entry['qty_ruling_note']);
        $newB = ($billable === null) ? null : (int) $billable;
        if ($curB === $newB && $curN === $note) {
            return array('ok' => true, 'code' => 200, 'changed' => false, 'reasons' => array());
        }

        // 409: مالٌ وقع — الأصلُ لا يُكتب فوقه
        $posted = self::postedLedger($conn, $companyId, $entryId);
        if ($posted['count'] > 0) {
            return array('ok' => false, 'code' => 409, 'changed' => false, 'posted' => $posted,
                         'reasons' => array(self::conflictReason($entryId, $posted)));
        }

        $now = gmdate('Y-m-d H:i:s');
        $gate->update('unit_entries', array(
            'qty_billable'    => $newB,
            'qty_ruling_note' => ($note === '') ? null : mb_substr($note, 0, 200),
            'qty_decided_by'  => intval($actor) ?: null,
            'qty_decided_at'  => $now,
        ), array('id' => intval($entryId)));

        // الحدثُ إشهارٌ لا شرطُ صحة — فشلُه يُسجَّل ولا يُسقط القرار
        self::emit($conn, $companyId, $entryId, EMS_EVT_ATTRIBUTION_QTY_RULED, $actor, array(
            'contract_id'  => intval($entry['contract_id']),
            'work_date'    => strval($entry['entry_date']),
            'unit_type'    => strval($entry['unit_type']),
            'qty'          => (float) $entry['qty'],
            'qty_billable' => $newB,
            'was'          => $curB,
            'note'         => $note,
        ));

        return array('ok' => true, 'code' => 200, 'changed' => true, 'reasons' => array());
    }

    /**
     * اعتراضٌ مصغَّرٌ على إسنادِ سطر (ق-25): علمٌ + سببٌ + مرجع.
     * **والبندُ المعترَضُ عليه لا يجمّد بقيةَ الواقعة** — البكتاتُ النافذةُ تمضي.
     */
    public static function object($conn, $gate, $companyId, $lineId, $reason, $ref, $actor)
    {
        $reason = trim((string) $reason);
        if ($reason === '') {
            return array('ok' => false, 'code' => 422, 'reasons' => array('سببُ الاعتراض إلزامي'));
        }
        $line = self::lineById($conn, $companyId, $lineId);
        if (!$line) { return array('ok' => false, 'code' => 404, 'reasons' => array('سطرُ الزمن غير موجود')); }

        $gate->update('unit_time_log', array(
            'objection_state'  => 'objected',
            'objection_reason' => mb_substr($reason, 0, 255),
            'objection_ref'    => mb_substr(trim((string) $ref), 0, 60),
        ), array('id' => intval($lineId)));

        self::emit($conn, $companyId, intval($line['entry_id']), EMS_EVT_ATTRIBUTION_OBJECTED, $actor, array(
            'line_id' => intval($lineId), 'reason' => mb_substr($reason, 0, 255),
            'ref' => mb_substr(trim((string) $ref), 0, 60),
            'obligation_type' => $line['obligation_type'],
        ));
        return array('ok' => true, 'code' => 200, 'reasons' => array());
    }

    /**
     * حسمُ الاعتراض — **الدور 19 وحدَه** (ق-25: نفسُ من يُجيز المصفوفةَ والجزاءات).
     * وإن غُيِّر البندُ عند الحسم صار الحدثُ «تجاوزًا» لا مجرّدَ حسم.
     */
    public static function resolve($conn, $gate, $companyId, $lineId, $newOblig, $actor)
    {
        $line = self::lineById($conn, $companyId, $lineId);
        if (!$line) { return array('ok' => false, 'code' => 404, 'reasons' => array('سطرُ الزمن غير موجود')); }
        if ($line['objection_state'] !== 'objected') {
            return array('ok' => false, 'code' => 409, 'reasons' => array('لا اعتراضَ مفتوحًا على هذا السطر'));
        }

        // ── 409 قبل أي كتابة: حسمُ الاعتراض يبدّل الحالة ثم قد يستدعي decide()،
        //    فلو تُرك الحارسُ لـdecide() وحدَها لبقيت الحالةُ مبدَّلةً بلا حسمٍ فعليّ.
        $posted = self::postedLedger($conn, $companyId, intval($line['entry_id']));
        if ($posted['count'] > 0) {
            return array('ok' => false, 'code' => 409, 'posted' => $posted,
                         'reasons' => array(self::conflictReason(intval($line['entry_id']), $posted)));
        }

        $overridden = false;
        $newOblig = ($newOblig === null || $newOblig === '') ? null : strval($newOblig);
        if ($newOblig !== null && $newOblig !== $line['obligation_type']) {
            $entry = self::entryOf($conn, $companyId, intval($line['entry_id']));
            if (!$entry) { return array('ok' => false, 'code' => 404, 'reasons' => array('الواقعة غير موجودة')); }
            $res = self::decide($conn, $gate, $companyId, intval($line['entry_id']),
                                array(intval($lineId) => $newOblig), $actor);
            if (!$res['ok']) { return $res; }
            $overridden = true;
        }

        $gate->update('unit_time_log', array('objection_state' => 'resolved'), array('id' => intval($lineId)));

        self::emit($conn, $companyId, intval($line['entry_id']),
            $overridden ? EMS_EVT_ATTRIBUTION_OVERRIDDEN : EMS_EVT_ATTRIBUTION_OBJECTED, $actor,
            array('line_id' => intval($lineId), 'resolved' => true,
                  'from' => $line['obligation_type'], 'to' => $newOblig));
        return array('ok' => true, 'code' => 200, 'reasons' => array(), 'overridden' => $overridden);
    }

    /**
     * تصحيحُ إسنادٍ **ولّد مالًا** بعكس الحدث — **الدور 19 وحدَه**.
     * ═══════════════════════════════════════════════════════════════════════
     * هذا هو البابُ الذي يفتحه `409`. والآلةُ آلةُ المحاسبة حرفيًّا:
     *
     *   ① **سطرٌ عاكس**: نسخةٌ من الأصل بساعاتٍ **سالبة** وبلقطته نفسِها — فجمعُ
     *      الاثنين صفرٌ في كل تقريرٍ يجمع الساعات، بلا أن يُمسّ الأصلُ بحرف.
     *   ② **سطرُ التصحيح** (اختياري): بالساعات الموجبة والبند الجديد، فيُعاد
     *      التسجيلُ بالحكم الصحيح.
     *
     * والأصلُ **لا يُمحى ولا يُعدَّل** — فمن قرأ التقريرَ أمسِ يجد ما قرأه، ومن
     * يقرؤه اليومَ يجد الصوابَ ومعه أثرُ كيف صار صوابًا.
     *
     * ⚠️ **ولا تُعاد المروحةُ هنا** (قرارُ المالك): توليدُ المال فعلٌ تحكمه بوابةُ
     *    التحويل (`EMS_UNIT_CONVERT_GATE` — «لا مالَ إلا بختم المالية»)، فتوليدُه
     *    تلقائيًّا داخل فعلِ تصحيحٍ يتخطّاها. السطرُ العاكسُ يترك الحسابَ متسقًا،
     *    والحكمُ الجديد يُولَّد في دورته.
     *
     * @param ?string $newOblig بندُ التصحيح — NULL لعكسٍ مجرَّدٍ بلا إعادة تسجيل
     * @return array{ok:bool,code:int,reasons:array,reversal_line_id:?int,
     *               correction_line_id:?int,event_id:?int,reverses_event_id:?int}
     */
    public static function reverse($conn, $gate, $companyId, $lineId,
                                   $newOblig, $reason, $actor, $actorRole)
    {
        $out = array('ok' => false, 'code' => 422, 'reasons' => array(),
                     'reversal_line_id' => null, 'correction_line_id' => null,
                     'event_id' => null, 'reverses_event_id' => null);

        // ① اليدُ: الدور 19 وحدَه — نفسُ من يُجيز المصفوفةَ والجزاءات ويحسم
        //    الاعتراض (ق-13 · ق-18 · ق-25). فلا مالكَ قرارٍ جديدٌ يُخترع.
        $role = strval($actorRole);
        if ($role !== '19' && $role !== '-1') {
            $out['code'] = 403;
            $out['reasons'][] = 'عكسُ الإسناد بيد مدير الإدارة المالية وحدَه (ق-13) — لا يُعكس حكمٌ ماليٌّ بغير يده';
            return $out;
        }
        $reason = trim((string) $reason);
        if ($reason === '') {
            $out['reasons'][] = 'سببُ العكس إلزاميٌّ وموثَّق — لا يُبطل قيدٌ بلا تعليل';
            return $out;
        }

        $line = self::lineById($conn, $companyId, $lineId, true);
        if (!$line) { $out['code'] = 404; $out['reasons'][] = 'سطرُ الزمن غير موجود'; return $out; }
        if ($line['decided_at'] === null) {
            $out['reasons'][] = 'لا إسنادَ مقرَّرًا على هذا السطر — لا شيءَ يُعكس (والتعديلُ المباشرُ يكفي)';
            return $out;
        }
        $entryId = intval($line['entry_id']);

        // ② العطالة: لا يُعكس حدثٌ مرتين. والمرجعُ `objection_ref` على السطر
        //    العاكس — حقلُ مرجعٍ نصيٌّ قائم، فصفرُ هجرة (وموسومٌ بالبادئة `REV:`).
        $marker = 'REV:' . intval($lineId);
        $dupe = self::lineByRef($conn, $companyId, $entryId, $marker);
        if ($dupe) {
            $out['ok'] = true; $out['code'] = 200;
            $out['reversal_line_id'] = intval($dupe['id']);
            $out['reasons'][] = 'عُكس سلفًا بالسطر #' . intval($dupe['id']) . ' — ولا يُعكس حدثٌ مرتين';
            return $out;
        }

        // ③ حكمُ التصحيح — يُشتق من المصفوفة كما يُشتق في decide() تمامًا،
        //    فلا يفترق سطرُ التصحيح عن سطرٍ وُلد من الباب العادي.
        $entry = self::entryOf($conn, $companyId, $entryId);
        if (!$entry) { $out['code'] = 404; $out['reasons'][] = 'الواقعة غير موجودة'; return $out; }
        $contractId = intval($entry['contract_id']);
        $date = strval($entry['entry_date']);
        $newOblig = ($newOblig === null || $newOblig === '') ? null : strval($newOblig);
        $corr = null;
        if ($newOblig !== null) {
            if (!in_array($newOblig, self::TYPES, true)) {
                $out['reasons'][] = 'بندُ تصحيحٍ غيرُ معروف «' . $newOblig . '»'; return $out;
            }
            $matrix = self::matrixFor($gate, $contractId, $date);
            if (empty($matrix)) {
                $out['code'] = 423;
                $out['reasons'][] = 'مصفوفةُ التزامات العقد #' . $contractId . ' غيرُ مُجازةٍ بتاريخ ' . $date;
                return $out;
            }
            if (!isset($matrix[$newOblig])) {
                $out['reasons'][] = 'بندُ التصحيح خارج مصفوفة العقد النافذة (422)'; return $out;
            }
            $corr = self::rulings($matrix, $line['ops_state'], $newOblig,
                \App\Services\EffectFanout::hourPolicy($gate, $companyId, 'client', $contractId, $date),
                \App\Services\EffectFanout::hourPolicy($gate, $companyId, 'supplier', $contractId, $date));
        }

        // ④ الكتابةُ ذرّيةً: العاكسُ والمصحِّحُ معًا أو لا شيء
        $hours = (float) $line['hours'];
        $revId = null; $corrId = null;
        try {
            $gate->runInTransaction(function ($g) use (
                $line, $lineId, $entryId, $companyId, $marker, $reason, $actor,
                $hours, $newOblig, $corr, &$revId, &$corrId
            ) {
                $base = array(
                    'company_id'           => intval($companyId),
                    'log_date'             => $line['log_date'],
                    'shift'                => $line['shift'],
                    'project_id'           => $line['project_id'],
                    'equipment_id'         => $line['equipment_id'],
                    'operator_employee_id' => $line['operator_employee_id'],
                    'supplier_entity_id'   => $line['supplier_entity_id'],
                    'ops_state'            => $line['ops_state'],
                    'resp_party'           => $line['resp_party'],
                    'entry_id'             => $entryId,
                    'decided_by'           => intval($actor),
                    'decided_at'           => gmdate('Y-m-d H:i:s'),
                );
                // السطرُ العاكس: لقطةُ الأصل نفسُها بساعاتٍ سالبة ⇒ مجموعُهما صفر
                $revId = intval($g->insert('unit_time_log', array_merge($base, array(
                    'hours'              => -$hours,
                    'obligation_type'    => $line['obligation_type'],
                    'billable'           => $line['billable'],
                    'supplier_countable' => $line['supplier_countable'],
                    'operator_countable' => $line['operator_countable'],
                    'objection_ref'      => $marker,
                    'cause_note'         => mb_substr('عكسُ سطر #' . intval($lineId) . ' — ' . $reason, 0, 200),
                ))));
                if ($corr !== null) {
                    $corrId = intval($g->insert('unit_time_log', array_merge($base, array(
                        'hours'              => $hours,
                        'obligation_type'    => $newOblig,
                        'billable'           => $corr['billable'],
                        'supplier_countable' => $corr['supplier_countable'],
                        'operator_countable' => $corr['operator_countable'],
                        'objection_ref'      => 'FIX:' . intval($lineId),
                        'cause_note'         => mb_substr('تصحيحُ سطر #' . intval($lineId) . ' — ' . $reason, 0, 200),
                    ))));
                }
            }, 'attribution reverse');
        } catch (\Throwable $t) {
            error_log('AttributionService reverse #' . intval($lineId) . ': ' . $t->getMessage());
            $out['code'] = 500; $out['reasons'][] = 'تعذّرت كتابةُ السطر العاكس';
            return $out;
        }

        // ⑤ الحدثُ يحمل نسبَه: `reverses_event_id` في الجذر المحايد (ADR-15)
        $reverses = self::lastRootEvent($conn, $companyId, $entryId, EMS_EVT_ATTRIBUTION_DECIDED);
        $evId = self::emit($conn, $companyId, $entryId, EMS_EVT_ATTRIBUTION_REVERSED, $actor, array(
            'reversed_line_id'   => intval($lineId),
            'reversal_line_id'   => $revId,
            'correction_line_id' => $corrId,
            'hours'              => $hours,
            'from'               => $line['obligation_type'],
            'to'                 => $newOblig,
            'reason'             => mb_substr($reason, 0, 255),
            'contract_id'        => $contractId,
        ), array(
            'reverses_event_id' => $reverses,
            'idempotency_key'   => EMS_EVT_ATTRIBUTION_REVERSED . ':line:' . intval($lineId),
        ));

        $out['ok'] = true; $out['code'] = 200;
        $out['reversal_line_id'] = $revId;
        $out['correction_line_id'] = $corrId;
        $out['event_id'] = $evId;
        $out['reverses_event_id'] = $reverses;
        return $out;
    }

    // ── داخليات ───────────────────────────────────────────────────────────

    private static function entryOf($conn, $companyId, $entryId)
    {
        // + أعمدةُ الكمية وحكمِها (M-24): `ruleQty()` تقرأ منها العطالةَ والحدث
        $st = $conn->prepare("SELECT id, contract_id, entry_date, state,
                                     unit_type, qty, qty_billable, qty_ruling_note
                                FROM unit_entries
                               WHERE company_id = ? AND id = ? LIMIT 1");
        $c = intval($companyId); $e = intval($entryId);
        $st->bind_param('ii', $c, $e);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    private static function linesOf($conn, $companyId, $entryId)
    {
        $st = $conn->prepare("SELECT id, ops_state, hours, obligation_type, resp_party,
                                     billable, supplier_countable, operator_countable,
                                     objection_state, time_from, time_to
                                FROM unit_time_log
                               WHERE company_id = ? AND entry_id = ?
                               ORDER BY id ASC");
        $c = intval($companyId); $e = intval($entryId);
        $st->bind_param('ii', $c, $e);
        $st->execute();
        $res = $st->get_result();
        $out = array();
        while ($r = $res->fetch_assoc()) { $out[] = $r; }
        $st->close();
        return $out;
    }

    private static function lineById($conn, $companyId, $lineId, $full = false)
    {
        $cols = $full
            ? "id, entry_id, log_date, shift, project_id, equipment_id, operator_employee_id,
               supplier_entity_id, ops_state, resp_party, hours, obligation_type,
               billable, supplier_countable, operator_countable,
               decided_by, decided_at, objection_state, objection_ref"
            : "id, entry_id, ops_state, hours, obligation_type, objection_state";
        $st = $conn->prepare("SELECT $cols FROM unit_time_log WHERE company_id = ? AND id = ? LIMIT 1");
        $c = intval($companyId); $l = intval($lineId);
        $st->bind_param('ii', $c, $l);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    /** سطرُ الواقعة بمرجعه الموسوم (`REV:`/`FIX:`) — عطالةُ العكس. */
    private static function lineByRef($conn, $companyId, $entryId, $ref)
    {
        $st = $conn->prepare("SELECT id FROM unit_time_log
                               WHERE company_id = ? AND entry_id = ? AND objection_ref = ? LIMIT 1");
        $c = intval($companyId); $e = intval($entryId); $r = strval($ref);
        $st->bind_param('iis', $c, $e, $r);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    /**
     * **هل ولّدت هذه الواقعةُ مالًا؟** — حدُّ الـ409 (قرارُ المالك 2026-07-28).
     * ═══════════════════════════════════════════════════════════════════════
     * القيدُ يُبلَغ من الواقعة بمسارين، وكلاهما مقيسٌ على القاعدة الحية:
     *   ① `unit_entries.event_id` — قيدٌ مباشرٌ على رأس الواقعة.
     *   ② **جسرُ الدوام**: المروحةُ تكتب روابطَها بـ`parent_kind='timesheet'`
     *      و`parent_ref = timesheet.id`، والواقعةُ تحمل نظيرَها في
     *      `sync_uuid = 'ts:<id>'` (EffectFanout:427). فهذا **هو** الرابطُ
     *      الواقعيُّ اليوم: واقعتان من 140 تحملانه، وهما وحدَهما اللتان
     *      ولّدتا مالًا فعلًا.
     *
     * ولمَ لا يُقاس بالحالة (`sales_approved`) ولا بدخول مستخلص؟ لأن الحالةَ
     * **رأيٌ في سير العمل** يسبق المال أو يتخلّف عنه، والمستخلصَ **متأخرٌ** عن
     * الاعتراف. والقيدُ وحدَه **مالٌ وقع** لا يحتمل التأويل.
     *
     * @return array{count:int,effects:array,event_id:?int}
     */
    private static function postedLedger($conn, $companyId, $entryId)
    {
        $out = array('count' => 0, 'effects' => array(), 'event_id' => null);
        $st = $conn->prepare(
            "SELECT e.event_id, COUNT(l.id) links,
                    GROUP_CONCAT(DISTINCT l.effect_type ORDER BY l.effect_type) kinds
               FROM unit_entries e
               LEFT JOIN fin_event_links l
                      ON l.company_id = e.company_id
                     AND l.parent_kind = 'timesheet'
                     AND e.sync_uuid LIKE 'ts:%'
                     AND l.parent_ref = CAST(SUBSTRING(e.sync_uuid, 4) AS UNSIGNED)
              WHERE e.company_id = ? AND e.id = ?
              GROUP BY e.id LIMIT 1");
        $c = intval($companyId); $en = intval($entryId);
        $st->bind_param('ii', $c, $en);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) { return $out; }

        $out['count'] = intval($row['links']);
        $out['effects'] = ($row['kinds'] !== null && $row['kinds'] !== '')
            ? explode(',', (string) $row['kinds']) : array();
        if (!empty($row['event_id'])) {
            $out['event_id'] = intval($row['event_id']);
            if ($out['count'] === 0) { $out['count'] = 1; }   // قيدُ الرأس وحدَه يكفي
        }
        return $out;
    }

    /** رسالةُ الـ409 — تقول ما وقع **وما العلاج**، فالرفضُ يدلّ على بابه. */
    private static function conflictReason($entryId, array $posted)
    {
        $names = array('revenue_event' => 'قيدُ إيراد', 'supplier_due' => 'مستحقُّ مورد',
                       'employee_due' => 'مستحقُّ مشغّل', 'cost_record' => 'قيدُ تكلفة',
                       'receivable' => 'ذمّة', 'party_award' => 'حكمُ طرف');
        $what = array();
        foreach ($posted['effects'] as $e) { $what[] = isset($names[$e]) ? $names[$e] : $e; }
        return 'الواقعةُ #' . intval($entryId) . ' ولّدت مالًا سلفًا ('
             . ($what ? implode(' · ', $what) : $posted['count'] . ' قيدًا')
             . ') — ولا يُكتب فوق حكمٍ ماليٍّ قائم. التصحيحُ **بعكس الحدث**: '
             . 'سطرٌ عاكسٌ يُضاف بيد مدير الإدارة المالية والأصلُ يبقى.';
    }

    /** آخرُ حدثٍ جذريٍّ بمفتاحه على الواقعة — نسبُ العكس إلى أصله (ADR-15). */
    private static function lastRootEvent($conn, $companyId, $entryId, $key)
    {
        try {
            $st = $conn->prepare("SELECT id FROM ems_business_events
                                   WHERE company_id = ? AND entity_type = 'unit_entry'
                                     AND entity_id = ? AND event_key = ?
                                   ORDER BY id DESC LIMIT 1");
            $c = intval($companyId); $e = intval($entryId); $k = strval($key);
            $st->bind_param('iis', $c, $e, $k);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            return $row ? intval($row['id']) : null;
        } catch (\Throwable $t) {
            error_log('AttributionService lastRootEvent: ' . $t->getMessage());
            return null;
        }
    }

    private static function lineLabel(array $l)
    {
        $t = ($l['time_from'] ?? '') !== '' ? (substr((string) $l['time_from'], 0, 5) . '-' . substr((string) $l['time_to'], 0, 5)) : ('#' . $l['id']);
        return 'سطر ' . $t . ' (' . $l['ops_state'] . ' · ' . rtrim(rtrim((string) $l['hours'], '0'), '.') . 'س)';
    }

    /**
     * نشرُ حقيقةٍ محايدة — عبر `EventPublisher` حصرًا (v8 §15-أ).
     *
     * @param array $opts تجاوزاتٌ للعقد — `reverses_event_id` و`idempotency_key`
     * @return ?int معرّفُ الحدث في الجذر (null إن أُطفئ الجذرُ أو فشل النشر)
     */
    private static function emit($conn, $companyId, $entryId, $key, $actor, array $payload, array $opts = array())
    {
        try {
            $res = EventPublisher::publishFact($conn, array_merge(array(
                'event_key' => $key,
                'category' => EMS_EVT_ATTRIBUTION_CATEGORY,
                'source_module' => 'projects',
                'company_id' => intval($companyId),
                'entity_type' => 'unit_entry',
                'entity_id' => intval($entryId),
                'occurred_at' => gmdate('Y-m-d H:i:s'),
                'created_by' => intval($actor) ?: 1,
                'idempotency_key' => $key . ':unit_entry:' . intval($entryId) . ':' . substr(sha1(json_encode($payload)), 0, 12),
                'payload' => $payload,
            ), $opts));
            return ($res !== null && isset($res['id'])) ? intval($res['id']) : null;
        } catch (\Throwable $t) {
            error_log('AttributionService publish ' . $key . ': ' . $t->getMessage());
            return null;
        }
    }
}
