<?php
/**
 * app/Services/Operations/OperationalTransformService.php — H-01 المرحلة ②
 * ═══════════════════════════════════════════════════════════════════════════
 * **التحوّلُ من عقدٍ نافذٍ إلى حاوياتٍ بمستوياتها** (OPM-01 §4).
 *
 * ── القاعدةُ الحاكمةُ في التصميم: الحارسُ في القاعدة لا هنا ────────────────
 * لا تجد في هذه الخدمة `if ($sum > $cap)`. قيدُ Σ **بنيويٌّ** في المخطط
 * (`ck_container_alloc`: `allocated_qty <= cap_qty`)، وهذه الخدمةُ تكتب داخل
 * `runInTransaction` فيرفض **المحرّكُ** التجاوزَ ويُسقط المعاملةَ كلَّها.
 * وفحصٌ تطبيقيٌّ فوقه يوهم بالأمان ويُنسى يومَ يُكتب مسارٌ ثانٍ.
 *
 * ⚠️ **گوتشا مثبَتة**: `config.php` يضبط تقاريرَ mysqli على **عدم الرمي**، فخرقُ
 * القيد يعود `false` **صامتًا**. لكن بوابةَ المستأجر (`TenantDb`) ترمي عند فشل
 * الاستعلام، و`runInTransaction` تُرجع التراجعَ — فالكتابةُ عبر البوابة حصرًا
 * هي ما يجعل الذريّةَ حقيقيةً. **لا تكتب هنا بـ`$conn->query` مباشرةً.**
 *
 * ── والحصةُ قرارٌ تجاريٌّ لا اشتقاقٌ حسابي ────────────────────────────────
 * `deriveFromOperations()` تستنتج من الواقع القائم لتوفّر بدايةً، لكنها
 * **توسم كلَّ ما تنتجه `origin='مشتقّة'`** بملاحظةٍ تقول من أين اشتُقّ — فيُدقَّق
 * ولا يُصدَّق، ويبقى ظاهرًا للإدارة حتى تُقرّه. هذا تنفيذُ قرار المالك مع حفظِ
 * قاعدة عدم التلفيق لا نقضٌ لها.
 */

namespace App\Services\Operations;

require_once __DIR__ . '/../../../includes/catch_log.php';

require_once __DIR__ . '/../Contract/ContractStateMachine.php';
require_once __DIR__ . '/../Capacity/CapacityLedgerService.php';
require_once __DIR__ . '/../Capacity/BalanceCalculator.php';
require_once __DIR__ . '/../Capacity/CapacitySourceService.php';

use App\Services\Contract\ContractStateMachine as CSM;
use App\Services\Capacity\CapacityLedgerService;
use App\Services\Capacity\CapacitySourceService;

class OperationalTransformService
{
    const LEVEL_MAIN     = 'رئيسية';
    const LEVEL_SUPPLIER = 'مورد';
    const LEVEL_EQUIP    = 'معدة';
    const LEVEL_OPERATOR = 'مشغّل';

    /** ترتيبُ الشجرة — أبٌ لكل مستوى، فلا يُخصَّص مشغّلٌ تحت مورد. */
    const PARENT_OF = array(
        self::LEVEL_SUPPLIER => self::LEVEL_MAIN,
        self::LEVEL_EQUIP    => self::LEVEL_SUPPLIER,
        self::LEVEL_OPERATOR => self::LEVEL_EQUIP,
    );

    // ═══════════════════════════════════════════════════════════════════════
    // ① توليدُ الحاويات الرئيسية — من بنود العقد لا من اليد
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * «الرئيسيةُ مصدرُها بنودُ العقد؛ ولا تُنشأ يدويًّا»: بندٌ ⇒ حاويةٌ بسقفه.
     *
     * ⚠️ **السقفُ من `contractequipments.equip_total_contract` حصرًا** — لا من
     * `contract_commitments`. ذاك التزامُ إتاحةٍ **يوميّ** (8 أو 20 ساعة)، وخلطُه
     * بسقف العقد يجعل السقفَ 8 بدل 55,500 فيرفض المحرّكُ كلَّ استهلاك.
     *
     * والعطالةُ **مجانيةٌ بنيويًّا**: `uq_main_per_item` يمنع رئيسيةً ثانيةً
     * للبند نفسِه، فالنداءُ المكرَّر يتخطّى القائمَ ولا يُنشئ.
     *
     * @return array{ok:bool,code:int,reason:string,created:int,existing:int,skipped:array}
     */
    public static function generateMain($conn, $gate, $companyId, $contractId, $actor = 0)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '',
                     'created' => 0, 'existing' => 0, 'skipped' => array());
        $contractId = (int) $contractId;

        $c = self::contractOf($gate, $contractId);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقدُ غير موجود'; return $out; }

        // «لا حاويةَ تُفتح إلا من عقدٍ نافذ» — والتعريفُ من آلة الحالات لا من نصٍّ هنا
        if (!CSM::isEffective($c['contract_status'])) {
            $out['code'] = 423;
            $out['reason'] = 'العقدُ ليس نافذًا (' . $c['contract_status']
                           . ') — ولا حاويةَ تُفتح إلا من عقدٍ نافذ';
            return $out;
        }

        $items = self::contractItems($gate, $contractId);
        if (empty($items)) {
            $out['code'] = 422;
            $out['reason'] = 'العقدُ بلا بنودٍ في `contractequipments` — ولا سقفَ يُشتق منه';
            return $out;
        }

        foreach ($items as $it) {
            $itemId = (int) $it['id'];
            $cap = round((float) $it['equip_total_contract'], 2);
            $unit = self::unitOf($it['equip_unit']);

            if ($cap <= 0) {
                $out['skipped'][] = 'بند #' . $itemId . ': سقفُه صفرٌ أو غيرُ مسجَّل — لا حاويةَ بلا سقف';
                continue;
            }
            if ($unit === null) {
                $out['skipped'][] = 'بند #' . $itemId . ': وحدتُه «' . $it['equip_unit']
                                  . '» غيرُ معروفة — ولا تُخمَّن';
                continue;
            }
            if (self::mainExists($gate, $itemId)) { $out['existing']++; continue; }

            $no = self::nextNo($gate);
            $gate->insert('op_containers', array(
                'container_no'     => $no,
                'level'            => self::LEVEL_MAIN,
                'parent_id'        => null,
                'contract_id'      => $contractId,
                'contract_item_id' => $itemId,
                'unit_type'        => $unit,
                'work_model'       => (string) $it['equip_type'],
                'cap_qty'          => $cap,
                'project_id'       => !empty($c['project_id']) ? (int) $c['project_id'] : null,
                'valid_from'       => !empty($c['contract_signing_date']) ? $c['contract_signing_date'] : null,
                'valid_to'         => !empty($c['actual_end']) ? $c['actual_end'] : null,
                // سقفُ البند رقمٌ **متفقٌ عليه** في العقد — لا اشتقاقَ فيه
                'origin'           => 'عقد',
                'origin_note'      => 'سقفُ بند العقد #' . $itemId . ' (equip_total_contract)',
                'created_by'       => (int) $actor ?: null,
            ));
            $out['created']++;
        }

        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ② التخصيص — إدراجُ الابن وزيادةُ الأب في معاملةٍ واحدة
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * تخصيصُ حصةٍ من حاويةٍ أبٍ إلى ابنٍ جديد.
     *
     * **الذريّة**: الإدراجُ وزيادةُ `allocated_qty` عند الأب في `runInTransaction`
     * واحدة. فإن تجاوز المجموعُ السقفَ **رفض المحرّكُ المعاملةَ كلَّها** — لا ابنٌ
     * يُكتب ولا أبٌ يُزاد. والحارسُ في القاعدة، وهذه الدالةُ تترجم رفضَه إلى
     * رسالةٍ **تسمّي المتاحَ والمطلوب** لا «حدث خطأ».
     *
     * @param string $childLevel مورد · معدة · مشغّل
     * @param int    $childRef   معرّفُ الطرف بحسب المستوى
     * @return array{ok:bool,code:int,reason:string,container_id:?int}
     */
    public static function allocate($conn, $gate, $companyId, $parentId, $childLevel,
                                    $childRef, $qty, $opts = array())
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'container_id' => null);
        $parentId = (int) $parentId;
        $childRef = (int) $childRef;
        $qty = round((float) $qty, 2);
        $childLevel = (string) $childLevel;

        if (!isset(self::PARENT_OF[$childLevel])) {
            $out['code'] = 422; $out['reason'] = 'مستوًى غيرُ معروف: ' . $childLevel; return $out;
        }
        if ($qty <= 0) {
            $out['code'] = 422; $out['reason'] = 'الحصةُ موجبةٌ — ولا تُخصَّص حاويةٌ بصفر'; return $out;
        }
        if ($childRef <= 0) {
            $out['code'] = 422; $out['reason'] = 'مرجعُ الطرف إلزامي'; return $out;
        }

        $p = self::containerOf($gate, $parentId);
        if (!$p) { $out['code'] = 404; $out['reason'] = 'الحاويةُ الأمُّ غير موجودة'; return $out; }

        if ((string) $p['level'] !== self::PARENT_OF[$childLevel]) {
            $out['code'] = 422;
            $out['reason'] = 'ترتيبُ الشجرة: «' . $childLevel . '» تُخصَّص من «'
                           . self::PARENT_OF[$childLevel] . '» لا من «' . $p['level'] . '»';
            return $out;
        }
        if ((string) $p['state'] !== 'نشطة') {
            $out['code'] = 423;
            $out['reason'] = 'الحاويةُ الأمُّ «' . $p['state'] . '» — لا يُخصَّص منها';
            return $out;
        }

        // العقدُ يجب أن يبقى نافذًا لحظةَ التخصيص (وراثةُ الحالة — H-02)
        $c = self::contractOf($gate, (int) $p['contract_id']);
        $inh = CSM::inheritedState($c ? $c['contract_status'] : '');
        if (empty($inh['active'])) {
            $out['code'] = 423; $out['reason'] = $inh['reason']; return $out;
        }

        // رسالةُ التجاوز **قبل** المحاولة — لتسمّي المتاحَ والمطلوب بلغة المهمة.
        // وهي **ليست الحارس**: الحارسُ قيدُ القاعدة أدناه، وهذه ترجمتُه للمستخدم.
        $free = round((float) $p['cap_qty'] - (float) $p['allocated_qty'], 2);
        if ($qty > $free) {
            $out['code'] = 422;
            $out['reason'] = 'الحصةُ المطلوبة ' . number_format($qty, 2)
                           . ' تتجاوز المتاحَ في «' . $p['container_no'] . '» ('
                           . number_format($free, 2) . ' من ' . number_format((float) $p['cap_qty'], 2)
                           . ') — وزّع أقلَّ أو ارفع سقفَ الأم';
            return $out;
        }

        $no = self::nextNo($gate);
        $refCol = ($childLevel === self::LEVEL_SUPPLIER) ? 'supplier_id'
                : (($childLevel === self::LEVEL_EQUIP) ? 'equipment_id' : 'operator_employee_id');

        $newId = null;
        try {
            $gate->runInTransaction(function ($g) use (
                &$newId, $no, $childLevel, $parentId, $p, $refCol, $childRef, $qty, $opts
            ) {
                $row = array(
                    'container_no' => $no,
                    'level'        => $childLevel,
                    'parent_id'    => $parentId,
                    'contract_id'  => (int) $p['contract_id'],
                    'unit_type'    => (string) $p['unit_type'],
                    'work_model'   => $p['work_model'],
                    'cap_qty'      => $qty,
                    'project_id'   => $p['project_id'],
                    $refCol        => $childRef,
                    'role_kind'    => isset($opts['role_kind']) && $opts['role_kind'] !== '' ? $opts['role_kind'] : null,
                    'shift_no'     => isset($opts['shift_no']) && $opts['shift_no'] !== '' ? (int) $opts['shift_no'] : null,
                    'valid_from'   => isset($opts['valid_from']) && $opts['valid_from'] !== '' ? $opts['valid_from'] : $p['valid_from'],
                    'valid_to'     => isset($opts['valid_to']) && $opts['valid_to'] !== '' ? $opts['valid_to'] : $p['valid_to'],
                    'origin'       => isset($opts['origin']) ? $opts['origin'] : 'عقد',
                    'origin_note'  => isset($opts['origin_note']) ? $opts['origin_note'] : null,
                    'created_by'   => isset($opts['actor']) ? ((int) $opts['actor'] ?: null) : null,
                );
                $newId = (int) $g->insert('op_containers', $row);

                // ★ زيادةُ الأب في المعاملة نفسِها — هنا يقع قيدُ Σ البنيوي
                $g->update('op_containers',
                    array('allocated_qty' => round((float) $p['allocated_qty'] + $qty, 2)),
                    array('id' => $parentId));
            }, 'container-allocate');
        } catch (\Throwable $t) {
            // خرقُ القيد يصل هنا عبر البوابة — يُترجَم ولا يُبتلع
            error_log('container allocate parent#' . $parentId . ': ' . $t->getMessage());
            $out['code'] = 422;
            $out['reason'] = 'تعذّر التخصيص — المتاحُ في الأم '
                           . number_format($free, 2) . ' والمطلوب ' . number_format($qty, 2);
            return $out;
        }

        $out['ok'] = true; $out['code'] = 201; $out['container_id'] = $newId;
        return $out;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ③ الاستهلاك — ذريٌّ على المستويات الأربعة أو لا شيء
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * خصمُ واقعةٍ من سلسلة حاوياتها **كلِّها في معاملةٍ واحدة**.
     *
     * «الاستهلاكُ الذري» = أربعُ زياداتٍ لـ`consumed_qty` من الورقة إلى الجذر،
     * أو لا واحدة. فحاويةٌ استُهلكت وأمُّها لم تُستهلك = رصيدٌ كاذبٌ في التقارير.
     *
     * والعطالةُ بمفتاحٍ فريد (`uq_consumption_idem`): إعادةُ الإرسال لا تخصم
     * ثانيةً — **والقيدُ في القاعدة لا فحصٌ هنا**.
     *
     * @param float  $qty موجبٌ استهلاكًا · **سالبٌ ردًّا** (عكسٌ موثَّقٌ لا حذف)
     * @return array{ok:bool,code:int,reason:string,existing:bool,levels:int}
     */
    public static function consume($conn, $gate, $companyId, $leafContainerId, $qty,
                                   $idemKey, $opts = array())
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'existing' => false, 'levels' => 0);
        $leafId = (int) $leafContainerId;
        $qty = round((float) $qty, 2);
        $idemKey = trim((string) $idemKey);

        if ($idemKey === '') {
            $out['code'] = 422; $out['reason'] = 'مفتاحُ العطالة إلزامي — بدونه يُخصم مرتين'; return $out;
        }
        if ($qty == 0.0) {
            $out['code'] = 422; $out['reason'] = 'لا استهلاكَ بصفر'; return $out;
        }

        // العطالةُ **قبل** فحص الرصيد (القاعدة ⑦)
        try {
            $ex = $gate->selectOne('container_consumption', array(
                'whereRaw' => 'idem_key = ?', 'params' => array($idemKey)));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $ex'); $ex = null; }
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['existing'] = true;
            $out['reason'] = 'مسجَّلٌ سلفًا بهذا المفتاح';
            return $out;
        }

        $chain = self::chainOf($gate, $leafId);
        if (empty($chain)) {
            $out['code'] = 404; $out['reason'] = 'الحاويةُ غير موجودة'; return $out;
        }
        $leaf = $chain[0];

        // رسالةُ العجز قبل المحاولة — والحارسُ قيدُ القاعدة.
        // CAP-15: المصدرُ بحسب العلم — columns من العمود (القائم) · ledger محسوبًا من الدفتر
        if ($qty > 0) {
            $free = CapacitySourceService::freeOf($gate, $leaf);
            if ($qty > $free) {
                $out['code'] = 422;
                $out['reason'] = 'المطلوب ' . number_format($qty, 2) . ' يتجاوز متبقّي «'
                               . $leaf['container_no'] . '» (' . number_format($free, 2) . ')';
                return $out;
            }
        }

        $levels = 0;
        try {
            $gate->runInTransaction(function ($g) use (
                $conn, $chain, $qty, $idemKey, $leafId, $opts, &$levels
            ) {
                foreach ($chain as $node) {
                    $g->update('op_containers',
                        array('consumed_qty' => round((float) $node['consumed_qty'] + $qty, 2)),
                        array('id' => (int) $node['id']));
                    $levels++;
                }
                $g->insert('container_consumption', array(
                    'container_id' => $leafId,
                    'source_kind'  => isset($opts['source_kind']) ? $opts['source_kind'] : 'unit_entry',
                    'source_ref'   => isset($opts['source_ref']) ? (int) $opts['source_ref'] : 0,
                    'qty'          => $qty,
                    'unit_type'    => isset($opts['unit_type']) ? $opts['unit_type'] : 'hour',
                    'consumed_on'  => isset($opts['consumed_on']) ? $opts['consumed_on'] : date('Y-m-d'),
                    'idem_key'     => $idemKey,
                    'note'         => isset($opts['note']) ? $opts['note'] : null,
                    'created_by'   => isset($opts['actor']) ? ((int) $opts['actor'] ?: null) : null,
                ));
                // CAP-14/15: الكتابةُ المزدوجة — سطرُ الدفتر في المعاملة نفسِها.
                // العمودُ صار مخبأً والدفترُ هو السجل؛ ومكررُ المفتاح يمرّ (عطالةٌ
                // متوازيةُ المسارين) — والمتعذّرُ (بلا سجلِّ وحدةٍ أو بمقياسٍ خارج
                // الأربعة) يُترك للظل يرصده لا يُلفَّق له سطر.
                self::dualWriteLedger($conn, $g, $chain[0], $qty, $opts);
            }, 'container-consume');
        } catch (\Throwable $t) {
            error_log('container consume leaf#' . $leafId . ': ' . $t->getMessage());
            $out['code'] = 422;
            $out['reason'] = 'تعذّر الخصم — تجاوزٌ في أحد مستويات السلسلة، فلم يُخصم شيء';
            return $out;
        }

        $out['ok'] = true; $out['code'] = 201; $out['levels'] = $levels;
        return $out;
    }

    /**
     * CAP-14/15 — سطرُ الدفتر المرافقُ للخصم (الكتابةُ المزدوجة داخل المعاملة).
     * لا يرمي على المكرر (409 = المساران كتبا المفتاحَ نفسَه) ويرمي على أي فشلٍ
     * آخرَ فتسقط المعاملةُ كلُّها — لا خصمَ عمودٍ بلا سطرِ دفترٍ لواقعةٍ صالحة.
     */
    private static function dualWriteLedger($conn, $g, array $leaf, $qty, array $opts)
    {
        $sourceKind = isset($opts['source_kind']) ? (string) $opts['source_kind'] : 'unit_entry';
        $unitType = isset($opts['unit_type']) ? (string) $opts['unit_type'] : 'hour';
        $sourceRef = isset($opts['source_ref']) ? (int) $opts['source_ref'] : 0;
        if ($sourceKind !== 'unit_entry' || $sourceRef <= 0
            || !in_array($unitType, array('hour', 'ton', 'trip', 'meter'), true)) {
            return; // بلا سجلِّ وحدةٍ مرقَّمٍ أو بمقياسٍ خارج §16 — يُعلَن بالظل لا يُلفَّق
        }
        // النسخة: من الخيار أولًا ثم من السجل الحي — والغائبُ نسخةٌ أولى
        $version = isset($opts['revision_no']) && (int) $opts['revision_no'] > 0
                   ? (int) $opts['revision_no'] : null;
        if ($version === null) {
            $r = $conn->query('SELECT revision_no FROM unit_entries WHERE id = ' . $sourceRef);
            if ($r && ($x = $r->fetch_assoc())) { $version = (int) $x['revision_no']; }
        }
        if ($version === null) { $version = 1; }
        // الأثرُ بدرجة الورقة (كخرط CAP-12)
        switch ((string) $leaf['level']) {
            case self::LEVEL_OPERATOR:
                $effect = 'operator_entitlement'; $target = 'operator';
                $ref = !empty($leaf['operator_employee_id']) ? 'emp:' . (int) $leaf['operator_employee_id']
                     : 'container:' . (int) $leaf['id'];
                break;
            case self::LEVEL_SUPPLIER:
                $effect = 'supplier_share'; $target = 'supplier';
                $ref = !empty($leaf['supplier_id']) ? 'sup:' . (int) $leaf['supplier_id']
                     : 'container:' . (int) $leaf['id'];
                break;
            default:
                $effect = 'client_obligation'; $target = 'client';
                $ref = 'contract:' . (int) $leaf['contract_id'];
        }
        $consumedOn = isset($opts['consumed_on']) ? (string) $opts['consumed_on'] : date('Y-m-d');
        $actor = isset($opts['actor']) ? ((int) $opts['actor'] ?: null) : null;
        if ($qty < 0) {
            // ردٌّ: سطرٌ عاكسٌ بمرجع الأصل — وبلا أصلٍ يُعلَن بالظل لا يُخترع
            $orig = CapacityLedgerService::findByKey($g, $sourceRef, $version, $effect, $target, $ref);
            if ($orig !== null) {
                $r = CapacityLedgerService::reverse($g, $orig, $actor);
                if (!$r['ok'] && (int) $r['code'] !== 409) {
                    throw new \RuntimeException('فشل سطرُ العكس: ' . $r['reason']);
                }
            }
            return;
        }
        $r = CapacityLedgerService::appendLine($g, array(
            'unit_record_id'      => $sourceRef,
            'unit_record_version' => $version,
            'contract_seat_id'    => (int) $leaf['id'],
            'effect_type'         => $effect,
            'effect_target_type'  => $target,
            'effect_target_ref'   => $ref,
            'measure_code'        => $unitType,
            'qty'                 => abs((float) $qty),
            'period'              => substr($consumedOn, 0, 7),
            'role_snapshot'       => isset($opts['role_snapshot']) ? (string) $opts['role_snapshot'] : null,
        ), $actor);
        if (!$r['ok'] && (int) $r['code'] !== 409) {
            throw new \RuntimeException('فشل سطرُ الدفتر: ' . $r['reason']);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ④ التوليدُ الرجعي — مشتقٌّ وموسومٌ بذلك
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * بناءُ شجرةِ عقدٍ عاملٍ من واقعه القائم — **تقديرٌ مفيدٌ لا حقيقةٌ مقرَّة**.
     *
     * المصدر: `unit_entries` (لا `operations` وحدَها) لأنها تحمل **الأطرافَ
     * الثلاثة معًا** (مورد × معدة × مشغّل) لكل واقعة، فتُبنى السلسلةُ كما وقعت.
     *
     * ⚠️ **التجميعُ بالمعدة لا بصفّ التشغيل** (مفاجأةٌ مقيسة): العقد 5 له صفّا
     * تشغيلٍ للمعدة 5 وصفّان للمعدة 13 — والتجميعُ الساذجُ يُنتج حاويتَي معدةٍ
     * للمعدة الواحدة فيُشطر سقفُها بلا معنى.
     *
     * ⚠️ **والمشغّلُ الواحدُ قد يكون تحت معدتين** (المشغّلان 4 و5 في العقد 5) —
     * وهذا **صحيحٌ ومقصود**: لكل معدةٍ حصتُها منه، فلا يُدمجان.
     *
     * والحصةُ المقترحةُ = ما استُهلك فعلًا، **موسَّعةً بهامشٍ لا يتجاوز سقفَ الأم**.
     * وكلُّ ما يُنتَج `origin='مشتقّة'` ينتظر إقرارَ الإدارة.
     *
     * @return array{ok:bool,code:int,reason:string,created:array,unmatched:array}
     */
    public static function deriveFromOperations($conn, $gate, $companyId, $contractId, $actor = 0)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '',
                     'created' => array('supplier' => 0, 'equipment' => 0, 'operator' => 0),
                     'unmatched' => array(), 'notes' => array());
        $contractId = (int) $contractId;

        // ① الرئيسياتُ أولًا (عطالتُها بنيوية)
        $g = self::generateMain($conn, $gate, $companyId, $contractId, $actor);
        if (empty($g['ok'])) { return $g; }
        $out['notes'][] = 'الرئيسيات: ' . $g['created'] . ' جديدة · ' . $g['existing'] . ' قائمة';
        foreach ($g['skipped'] as $s) { $out['notes'][] = $s; }

        $mains = self::mainsOf($gate, $contractId);
        if (empty($mains)) {
            $out['code'] = 422; $out['reason'] = 'لا حاويةَ رئيسيةً للعقد'; return $out;
        }

        // ② الواقعُ: الأطرافُ الثلاثةُ مجمَّعةً — والكميةُ بوحدتها
        //
        // ⚠️ **ونوعُ المعدة معها** (مفاجأةٌ رابعةٌ مقيسة): العقد 5 له **بندان
        // بالوحدة نفسِها** (23 بـ11,100 ساعة لنوع 1 · 24 بـ44,400 لنوع 2).
        // فالمطابقةُ **بالوحدة وحدَها تسحق أحدَهما** وتُلقي كلَّ الساعات في بندٍ
        // واحدٍ — سقفٌ يبدو صحيحًا ورقمٌ منسوبٌ لغير بنده.
        // والرابطُ الصحيح: `equipments.type` = `contractequipments.equip_type`.
        $rows = $gate->scopedQuery(
            array('scope' => array('e' => 'unit_entries'), 'enrich' => array('q' => 'equipments')),
            "SELECT e.unit_type, e.supplier_entity_id, e.equipment_id, e.operator_employee_id,
                    q.type AS equip_type,
                    COUNT(*) AS n, ROUND(SUM(e.qty),2) AS qty
               FROM unit_entries e
               LEFT JOIN equipments q ON q.id = e.equipment_id
              WHERE {TENANT_SCOPE} AND e.contract_id = ?
                AND e.state NOT IN ('rejected','cancelled','reversed','superseded')
              GROUP BY e.unit_type, e.supplier_entity_id, e.equipment_id,
                       e.operator_employee_id, q.type",
            array($contractId));

        // ③ خريطةُ (نوعِ المعدة × الوحدة) ← الحاويةُ الرئيسية. وما لا مفتاحَ له
        //    يُعلَن ولا تُخترع له حاوية.
        $itemsById = array();
        foreach (self::contractItems($gate, $contractId) as $it) { $itemsById[(int) $it['id']] = $it; }
        $mainByKey = array();
        foreach ($mains as $m) {
            $it = isset($itemsById[(int) $m['contract_item_id']]) ? $itemsById[(int) $m['contract_item_id']] : null;
            $t = $it ? trim((string) $it['equip_type']) : '';
            $mainByKey[$t . '|' . (string) $m['unit_type']] = $m;
        }

        $tree = array();   // key → supplier → equipment → operator → qty
        foreach ($rows as $r) {
            $u = (string) $r['unit_type'];
            $et = trim((string) $r['equip_type']);
            $key = $et . '|' . $u;
            if (!isset($mainByKey[$key])) {
                $out['unmatched'][] = array(
                    'unit_type' => $u, 'equip_type' => $et,
                    'entries' => (int) $r['n'], 'qty' => (float) $r['qty'],
                    'reason' => 'واقعةٌ بوحدة «' . $u . '» لنوع معدةٍ «' . ($et !== '' ? $et : '—')
                              . '» لا بندَ له في العقد — تنتظر بندًا أو ملحقًا',
                );
                continue;
            }
            $sup = (int) $r['supplier_entity_id'];
            $eq  = (int) $r['equipment_id'];
            $op  = (int) $r['operator_employee_id'];
            if ($sup <= 0 || $eq <= 0) {
                $out['unmatched'][] = array(
                    'unit_type' => $u, 'entries' => (int) $r['n'], 'qty' => (float) $r['qty'],
                    'reason' => 'واقعةٌ بلا موردٍ أو بلا معدة — لا تُبنى منها سلسلةٌ ناقصة',
                );
                continue;
            }
            // ★ التجميعُ بالمعدة لا بصفّ التشغيل — والمفتاحُ (نوعُ المعدة × الوحدة)
            if (!isset($tree[$key][$sup][$eq][$op])) { $tree[$key][$sup][$eq][$op] = 0.0; }
            $tree[$key][$sup][$eq][$op] += (float) $r['qty'];
        }

        // ④ البناءُ من الجذر إلى الورقة — كلٌّ بحصته المستنتَجة
        foreach ($tree as $key => $bySup) {
            $main = $mainByKey[$key];
            $unit = (string) $main['unit_type'];
            $mainFree = round((float) $main['cap_qty'] - (float) $main['allocated_qty'], 2);

            foreach ($bySup as $supId => $byEq) {
                $supQty = 0.0;
                foreach ($byEq as $ops) { foreach ($ops as $q) { $supQty += $q; } }
                $supQty = round($supQty, 2);
                if ($supQty <= 0 || $supQty > $mainFree) {
                    $out['unmatched'][] = array('unit_type' => $unit, 'qty' => $supQty,
                        'reason' => 'حصةُ المورد #' . $supId . ' لا تتّسع في المتاح ('
                                    . number_format($mainFree, 2) . ') — تُوزَّع يدويًّا');
                    continue;
                }
                $supC = self::findChild($gate, (int) $main['id'], 'supplier_id', $supId);
                if ($supC === null) {
                    $r1 = self::allocate($conn, $gate, $companyId, (int) $main['id'], self::LEVEL_SUPPLIER,
                        $supId, $supQty, array('actor' => $actor, 'origin' => 'مشتقّة',
                            'origin_note' => 'مشتقّةٌ من صفوف التشغيل: Σ وقائع المورد = '
                                             . number_format($supQty, 2) . ' ' . $unit));
                    if (empty($r1['ok'])) { $out['unmatched'][] = array('reason' => $r1['reason']); continue; }
                    $out['created']['supplier']++;
                    $supC = self::containerOf($gate, (int) $r1['container_id']);
                }
                $mainFree = round($mainFree - $supQty, 2);

                foreach ($byEq as $eqId => $ops) {
                    $eqQty = round(array_sum($ops), 2);
                    if ($eqQty <= 0) { continue; }
                    $eqC = self::findChild($gate, (int) $supC['id'], 'equipment_id', $eqId);
                    if ($eqC === null) {
                        $r2 = self::allocate($conn, $gate, $companyId, (int) $supC['id'], self::LEVEL_EQUIP,
                            $eqId, $eqQty, array('actor' => $actor, 'origin' => 'مشتقّة',
                                'role_kind' => 'أساسية',
                                'origin_note' => 'مشتقّةٌ من صفوف التشغيل: Σ وقائع المعدة = '
                                                 . number_format($eqQty, 2) . ' ' . $unit));
                        if (empty($r2['ok'])) { $out['unmatched'][] = array('reason' => $r2['reason']); continue; }
                        $out['created']['equipment']++;
                        $eqC = self::containerOf($gate, (int) $r2['container_id']);
                    }

                    foreach ($ops as $opId => $opQty) {
                        $opQty = round($opQty, 2);
                        if ($opId <= 0 || $opQty <= 0) { continue; }
                        if (self::findChild($gate, (int) $eqC['id'], 'operator_employee_id', $opId) !== null) { continue; }
                        $r3 = self::allocate($conn, $gate, $companyId, (int) $eqC['id'], self::LEVEL_OPERATOR,
                            $opId, $opQty, array('actor' => $actor, 'origin' => 'مشتقّة',
                                'role_kind' => 'أساسي',
                                'origin_note' => 'مشتقّةٌ من صفوف التشغيل: Σ وقائع المشغّل على هذه المعدة = '
                                                 . number_format($opQty, 2) . ' ' . $unit));
                        if (empty($r3['ok'])) { $out['unmatched'][] = array('reason' => $r3['reason']); continue; }
                        $out['created']['operator']++;
                    }
                }
            }
        }

        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /**
     * **تقريرُ المطابقة** — ما اشتُقّ ولم يُقرّ بعد، وما لا حاويةَ له.
     * يُعلن ولا يُصلح، ويبقى ظاهرًا حتى يُقفله المالكُ بقرار.
     */
    public static function reconciliation($gate, $companyId, $contractId = 0)
    {
        $out = array('derived_pending' => array(), 'unmatched_units' => array());
        $params = array(); $extra = '';
        if ((int) $contractId > 0) { $extra = ' AND c.contract_id = ?'; $params[] = (int) $contractId; }

        try {
            $out['derived_pending'] = $gate->scopedQuery(
                array('scope' => array('c' => 'op_containers')),
                "SELECT c.id, c.container_no, c.level, c.contract_id, c.cap_qty, c.unit_type, c.origin_note
                   FROM op_containers c
                  WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0
                    AND c.origin = 'مشتقّة' AND c.origin_ack_by IS NULL{$extra}
                  ORDER BY c.contract_id, c.level, c.id", $params);
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'containers recon derived'); error_log('containers recon derived: ' . $t->getMessage()); }

        // وحداتٌ لها وقائعُ ولا حاويةَ رئيسيةً بوحدتها.
        // ⚠️ المرشِّحُ يسري على الشقّين: عرضُ عقودٍ أخرى تحت عنوان عقدٍ مختارٍ
        // يجعل المستخدمَ ينسب نقصًا إلى عقدٍ لا يخصّه.
        $params2 = array(); $extra2 = '';
        if ((int) $contractId > 0) { $extra2 = ' AND e.contract_id = ?'; $params2[] = (int) $contractId; }
        try {
            $out['unmatched_units'] = $gate->scopedQuery(
                array('scope' => array('e' => 'unit_entries'), 'enrich' => array('c' => 'op_containers')),
                "SELECT e.contract_id, e.unit_type, COUNT(*) AS entries, ROUND(SUM(e.qty),2) AS qty
                   FROM unit_entries e
                   LEFT JOIN op_containers c
                          ON c.contract_id = e.contract_id AND c.level = 'رئيسية'
                         AND c.unit_type = e.unit_type AND COALESCE(c.is_deleted,0)=0
                  WHERE {TENANT_SCOPE} AND e.contract_id > 0 AND c.id IS NULL
                    AND e.state NOT IN ('rejected','cancelled','reversed','superseded'){$extra2}
                  GROUP BY e.contract_id, e.unit_type
                  ORDER BY e.contract_id, e.unit_type", $params2);
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'containers recon unmatched'); error_log('containers recon unmatched: ' . $t->getMessage()); }

        return $out;
    }

    /** إقرارُ حصةٍ مشتقّة — يرفع الوسمَ عن رقمٍ صار متفقًا عليه. */
    public static function acknowledge($gate, $companyId, $containerId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $c = self::containerOf($gate, (int) $containerId);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'الحاويةُ غير موجودة'; return $out; }
        if ((string) $c['origin'] !== 'مشتقّة') {
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'ليست مشتقّةً — لا إقرارَ لها'; return $out;
        }
        if ($c['origin_ack_by'] !== null) {
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'مُقرَّةٌ سلفًا'; return $out;
        }
        $gate->update('op_containers', array(
            'origin_ack_by' => (int) $actor ?: null,
            'origin_ack_at' => date('Y-m-d H:i:s'),
        ), array('id' => (int) $containerId));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // قراءاتٌ مساعدة
    // ═══════════════════════════════════════════════════════════════════════

    /** السلسلةُ من الورقة إلى الجذر — أساسُ الخصم الذري. */
    public static function chainOf($gate, $containerId)
    {
        $chain = array();
        $id = (int) $containerId;
        $guard = 0;
        while ($id > 0 && $guard++ < 8) {          // 8 > عمقُ الشجرة — حارسُ دورةٍ لا أكثر
            $n = self::containerOf($gate, $id);
            if (!$n) { break; }
            $chain[] = $n;
            $id = ($n['parent_id'] === null) ? 0 : (int) $n['parent_id'];
        }
        return $chain;
    }

    /** شجرةُ عقدٍ كاملةً للعرض — مرتَّبةً بمستوياتها. */
    public static function treeOf($gate, $contractId)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('c' => 'op_containers'),
                      'enrich' => array('s' => 'suppliers', 'q' => 'equipments', 'e' => 'employees')),
                "SELECT c.*, s.name AS supplier_name, q.name AS equipment_name, e.name AS operator_name
                   FROM op_containers c
                   LEFT JOIN suppliers s ON s.id = c.supplier_id
                   LEFT JOIN equipments q ON q.id = c.equipment_id
                   LEFT JOIN employees e ON e.id = c.operator_employee_id
                  WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0 AND c.contract_id = ?
                  ORDER BY FIELD(c.level,'رئيسية','مورد','معدة','مشغّل'), c.id",
                array((int) $contractId));
        } catch (\Throwable $t) {
            error_log('containers treeOf: ' . $t->getMessage());
            return array();
        }
    }

    private static function containerOf($gate, $id)
    {
        try { return $gate->selectOne('op_containers', array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { return null; }
    }

    private static function contractOf($gate, $id)
    {
        try { return $gate->selectOne('contracts', array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { return null; }
    }

    private static function contractItems($gate, $contractId)
    {
        try {
            return $gate->select('contractequipments', array(
                'where' => array('contract_id' => (int) $contractId), 'orderBy' => 'id ASC'));
        } catch (\Throwable $t) { return array(); }
    }

    private static function mainExists($gate, $itemId)
    {
        try {
            $r = $gate->selectOne('op_containers', array(
                'whereRaw' => "contract_item_id = ? AND level = 'رئيسية'",
                'params'   => array((int) $itemId)));
            return $r !== null;
        } catch (\Throwable $t) { return false; }
    }

    private static function mainsOf($gate, $contractId)
    {
        try {
            return $gate->select('op_containers', array(
                'whereRaw' => "contract_id = ? AND level = 'رئيسية'",
                'params'   => array((int) $contractId), 'orderBy' => 'id ASC'));
        } catch (\Throwable $t) { return array(); }
    }

    private static function findChild($gate, $parentId, $refCol, $refVal)
    {
        try {
            return $gate->selectOne('op_containers', array(
                'whereRaw' => "parent_id = ? AND `{$refCol}` = ?",
                'params'   => array((int) $parentId, (int) $refVal)));
        } catch (\Throwable $t) { return null; }
    }

    /**
     * وحدةُ البند من نصّها العربي — **من القاموس القائم لا من خريطةٍ ثانية**.
     *
     * ⚠️ خريطةٌ محليةٌ هنا تفترق عن `EffectFanout::CONTRACT_UNIT` يومَ تُضاف وحدةٌ
     * لأحدهما — وقد وقع: العقد 2 وحدتُه **«متر طولي»** وهي في القاموس القائم
     * ومفقودةٌ من خريطتي، فسقط العقدُ كلُّه بلا حاويةٍ **بسببٍ لا وجودَ له**.
     * فالمصدرُ واحدٌ: قاموسُ المروحة الذي يقرأ به المالُ نفسُه.
     *
     * وما لا يُعرف يبقى null بلا تخمين — ويُعلَن في `skipped`.
     */
    private static function unitOf($label)
    {
        require_once dirname(__DIR__) . '/EffectFanout.php';
        $map = \App\Services\EffectFanout::CONTRACT_UNIT;
        $l = trim((string) $label);
        return isset($map[$l]) ? $map[$l] : null;
    }

    /** ترقيمٌ خادميٌّ CNT-سنة-تسلسل (فريدٌ بقيد `uq_container_no`). */
    private static function nextNo($gate)
    {
        $year = date('Y');
        try {
            $rows = $gate->select('op_containers', array(
                'columns' => array('container_no'),
                'whereRaw' => "container_no LIKE ?", 'params' => array('CNT-' . $year . '-%'),
                'orderBy' => 'id DESC', 'limit' => 1, 'includeDeleted' => true));
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كقائمةٍ فارغة — $rows'); $rows = array(); }
        $seq = 1;
        if ($rows && preg_match('~-(\d+)$~', (string) $rows[0]['container_no'], $m)) {
            $seq = (int) $m[1] + 1;
        }
        return 'CNT-' . $year . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
