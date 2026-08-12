<?php
/**
 * app/Services/Operations/RotationSwapService.php — البدلاءُ والتناوب (H-04)
 * ═══════════════════════════════════════════════════════════════════════════
 * OPM-01 §5.1/§5.2:
 *  · الاستبدالُ الذري: «تُجمَّد حاويةُ المعطلة **عند رصيدها** وتُفتح حاويةُ
 *    البديلة **بالمتبقي** — بحركة نقلٍ واحدةٍ ذرية، فلا تُفقد وحدةٌ ولا
 *    تُحتسب مرتين» — و«حصةُ المورد لا تتغير» تصير **جبرًا**: Σ أبناءِ الأب
 *    الحيُّ بعد النقل = consumed الخارجةِ + متبقي البديلة = cap الخارجة.
 *  · جدولُ المناوبة: «الجدولُ يعرض من يعمل في أي تاريخٍ مستقبلًا» — من دورات
 *    operator_rotations (on/off/anchor)، وبلا دورةٍ = مناوبٌ دائمًا (الغيابُ
 *    لا يُخترع له نمط)، والترتيبُ بالدور (أساسي ← بديل أول ← ثانٍ).
 *  · انتقالُ الحصة **المؤتمتُ** في مواعيد التناوب بيتُه N-05 (تكملةُ H-04) —
 *    أساسُه هنا: الجدولُ + النقلُ الذري.
 */

namespace App\Services\Operations;

require_once __DIR__ . '/../../../includes/catch_log.php';

class RotationSwapService
{
    const SWAPPABLE_LEVELS = array('معدة', 'مشغّل');
    const ROLE_ORDER = array('أساسي' => 1, 'أساسية' => 1, 'بديل أول' => 2,
                             'بديل ثانٍ' => 3, 'احتياطية' => 3, 'مشترك' => 4);

    /**
     * الاستبدالُ الذري لحاوية معدةٍ أو مشغّل.
     *
     * @param int    $containerId الحاويةُ الخارجة (معدة أو مشغّل)
     * @param int    $inRef       الحائزُ الداخل (equipment_id أو employee_id)
     * @param string $reason      إلزامي — «بقرارٍ موثَّق»
     * @return array{ok:bool,code:int,reason:string,to_container_id:?int,moved_qty:?float,swap_id:?int}
     */
    public static function swap($conn, $gate, $companyId, $containerId, $inRef, $reason, $docRef, $actor, $date = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '',
                     'to_container_id' => null, 'moved_qty' => null, 'swap_id' => null);
        $containerId = (int) $containerId; $inRef = (int) $inRef;
        $reason = trim((string) $reason);
        $date = $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) ? (string) $date : date('Y-m-d');

        if ($reason === '') { $out['code'] = 422; $out['reason'] = 'سببُ الاستبدال إلزامي — «بقرارٍ موثَّق»'; return $out; }
        if ($inRef <= 0) { $out['code'] = 422; $out['reason'] = 'الحائزُ الداخل إلزامي'; return $out; }

        $c = null;
        try { $c = $gate->selectOne('op_containers', array('where' => array('id' => $containerId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $c'); $c = null; }
        if (!$c) { $out['code'] = 404; $out['reason'] = 'الحاويةُ غير موجودةٍ في نطاقك'; return $out; }
        $level = (string) $c['level'];
        if (!in_array($level, self::SWAPPABLE_LEVELS, true)) {
            $out['code'] = 422;
            $out['reason'] = 'الاستبدالُ لحاويات المعدات والمشغّلين — لا «' . $level . '»';
            return $out;
        }
        if ((string) $c['state'] !== 'نشطة') {
            $out['code'] = 423; $out['reason'] = 'الحاويةُ «' . $c['state'] . '» — الاستبدالُ للنشطة'; return $out;
        }
        $holderCol = $level === 'معدة' ? 'equipment_id' : 'operator_employee_id';
        $outRef = (int) $c[$holderCol];
        if ($outRef === $inRef) {
            $out['code'] = 422; $out['reason'] = 'الداخلُ هو الخارجُ نفسُه — لا استبدال'; return $out;
        }
        // الحائزُ الداخل من النطاق
        $refTable = $level === 'معدة' ? 'equipments' : 'employees';
        $refRow = null;
        try { $refRow = $gate->selectOne($refTable, array('columns' => array('id'), 'where' => array('id' => $inRef))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $refRow'); $refRow = null; }
        if (!$refRow) { $out['code'] = 422; $out['reason'] = 'الحائزُ الداخل غيرُ موجودٍ في نطاقك'; return $out; }

        /* المتبقي = الرصيدُ **الحرُّ** — «لا تُفقد وحدةٌ ولا تُحتسب مرتين»
           ─────────────────────────────────────────────────────────────────────
           كان الحسابُ `cap − consumed` وحدَه، و**يتجاهل `allocated_qty`**: أي ما
           سُلّم سلفًا إلى حاوياتٍ ابنةٍ تبقى معلَّقةً بالخارجةِ لا تنتقل معها.
           فحاويةُ معدةٍ سقفُها 600 وزّعت 300 على مشغّليها ولم تستهلك شيئًا كانت
           تُعطي البديلةَ **600** والأبناءُ ما زالوا يحملون 300 — فيصير من سقفٍ
           واحدٍ 900، وهو نقضُ الثابتِ الذي تعلنه هذه الخدمةُ نفسُها.
           والأثرُ المقيسُ كان أفدح: التجميدُ على `cap = consumed` يُخفِق في
           `ck_container_alloc` (`allocated <= cap`) فتُلغى المعاملةُ كلُّها —
           **19 حاويةً حيةً لا تقبل الاستبدالَ أصلًا** (مقيسٌ).
           ⇒ فالمنقولُ هو الحرُّ وحدَه، والخارجةُ تُجمَّد على ما استهلكت **وما
             سلّمت**: `keep = consumed + allocated`. فيُحفظ Σ جبرًا:
             `keep + free = cap` بلا فقدٍ ولا تكرار. */
        $allocated = round((float) $c['allocated_qty'], 2);
        $keep      = round((float) $c['consumed_qty'] + $allocated, 2);
        $remaining = round((float) $c['cap_qty'] - $keep, 2);
        if ($remaining <= 0) {
            $out['code'] = 422;
            $out['reason'] = 'لا رصيدَ حرًّا يُنقل (سقف ' . $c['cap_qty'] . ' · مستهلكةٌ '
                           . $c['consumed_qty'] . ' · موزَّعةٌ على أبنائها ' . $allocated . ')';
            return $out;
        }

        // البديلةُ القائمة (الاحتياطيةُ «بحصةٍ صفريةٍ حتى تُفعَّل») — أختٌ للحائز الداخل
        $sibling = null;
        try {
            $rows = $gate->scopedQuery(array('scope' => array('o' => 'op_containers')),
                "SELECT o.* FROM op_containers o
                 WHERE {TENANT_SCOPE} AND o.parent_id = ? AND o.level = ?
                   AND o." . $holderCol . " = ? AND COALESCE(o.is_deleted,0)=0
                   AND o.state <> 'مقفلة'
                 ORDER BY o.id LIMIT 1",
                array((int) $c['parent_id'], $level, $inRef));
            $sibling = $rows ? $rows[0] : null;
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $sibling'); $sibling = null; }

        $toId = null; $swapId = null;
        try {
            $gate->runInTransaction(function ($g) use ($c, $containerId, $level, $holderCol, $inRef, $outRef,
                                                       $remaining, $keep, $sibling, $reason, $docRef, $actor, $date,
                                                       &$toId, &$swapId) {
                // ① الخارجةُ تُجمَّد عند رصيدها: **تُقفل على ما استهلكت وما سلّمت**
                //   (`cap = consumed + allocated`) — فالحرُّ وحدَه انتقل، وحصصُ
                //   أبنائها تبقى مغطّاةً بسقفها فلا يُنقَض `ck_container_alloc`.
                //   وإعادةُ تفعيلها لاحقًا (عودةُ التناوب) تضيف العائدَ وحدَه فلا
                //   يتضاعف رصيدٌ أبدًا.
                $g->update('op_containers', array(
                    'state' => 'معلَّقة', 'valid_to' => $date,
                    'cap_qty' => $keep,
                    'close_reason' => mb_substr('استبدالٌ: ' . $reason, 0, 200),
                ), array('id' => $containerId));

                // ② البديلة: تفعيلُ القائمة أو إنشاءٌ بالمتبقي
                if ($sibling) {
                    $toId = (int) $sibling['id'];
                    $g->update('op_containers', array(
                        'cap_qty' => round((float) $sibling['cap_qty'] + $remaining, 2),
                        'state' => 'نشطة',
                        'origin_note' => mb_substr('فُعّلت بالمتبقي من #' . $containerId . ' — ' . $reason, 0, 255),
                    ), array('id' => $toId));
                } else {
                    $no = 'SWP-' . $containerId . '-' . preg_replace('/\D/', '', $date);
                    $toId = (int) $g->insert('op_containers', array(
                        'container_no' => $no,
                        'level'        => $level,
                        'parent_id'    => (int) $c['parent_id'],
                        'contract_id'  => (int) $c['contract_id'],
                        'contract_item_id' => $c['contract_item_id'],
                        'unit_type'    => (string) $c['unit_type'],
                        'work_model'   => $c['work_model'],
                        'cap_qty'      => $remaining,
                        $holderCol     => $inRef,
                        'project_id'   => $c['project_id'],
                        'role_kind'    => $c['role_kind'],
                        'shift_no'     => $c['shift_no'],
                        'valid_from'   => $date,
                        'valid_to'     => $c['valid_to'] !== $date ? $c['valid_to'] : null,
                        'origin'       => 'عقد',
                        'origin_note'  => mb_substr('بديلةُ #' . $containerId . ' بالمتبقي — ' . $reason, 0, 255),
                        'created_by'   => (int) $actor ?: null,
                    ));
                }

                // ③ الأبُ لا يُمسّ — Σ الحيّ محفوظٌ جبرًا (consumed الخارجة + متبقي البديلة)

                // ④ سجلُّ الحركة الثابت
                $swapId = (int) $g->insert('container_swaps', array(
                    'container_id'   => $containerId,
                    'swap_kind'      => $level,
                    'out_ref'        => $outRef ?: null,
                    'in_ref'         => $inRef,
                    'moved_qty'      => $remaining,
                    'to_container_id' => $toId,
                    'effective_from' => $date,
                    'reason'         => mb_substr($reason, 0, 255),
                    'doc_ref'        => trim((string) $docRef) !== '' ? mb_substr(trim((string) $docRef), 0, 120) : null,
                    'created_by'     => (int) $actor ?: null,
                ));
                return true;
            }, 'H-04 atomic swap #' . $containerId);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر النقلُ الذري: ' . $t->getMessage(); return $out;
        }

        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'operations', 'container_swaps', 'atomic_swap', (int) $swapId,
            array('container' => $containerId, 'holder' => $outRef, 'state' => 'نشطة'),
            array('to_container' => $toId, 'holder' => $inRef, 'moved_qty' => $remaining, 'reason' => $reason),
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor,
                  'contract_id' => (int) $c['contract_id']));

        $out['ok'] = true; $out['code'] = 200;
        $out['to_container_id'] = $toId; $out['moved_qty'] = $remaining; $out['swap_id'] = $swapId;
        return $out;
    }

    /**
     * N-05 · انتقالُ الحصة آليًّا في مواعيد التناوب (تكملةُ H-04).
     *
     * «الحصةُ تنتقل آليًّا بين المشغّلين في مواعيد التناوب بلا إعادة بناء
     * التخصيصات» (OPM-01 §5.2-③): لكل حاوية معدةٍ نشطةٍ في المشروع — إن كان
     * المناوبُ بحسب الجدول غيرَ حائزِ الحصة النشطِ نُقلت ذريًّا إليه (swap
     * بسجل قرارٍ آلي). عطالةٌ: بعد النقل الحائزُ = المناوبُ فلا نقلَ ثانيًا.
     * الشراكةُ (أكثرُ من حاوية مشغّلٍ نشطة) ليست تناوبًا — تُتخطى معلَنةً.
     * العلمُ قائمةُ مشاريع: `EMS_ROTATION_AUTOTRANSFER` (فارغٌ = مطفأ — N-04 §2-③).
     *
     * @return array{ok:bool,transferred:int,skipped:array}
     */
    public static function autoTransferForDate($conn, $gate, $companyId, $projectId, $date, $actor = 0)
    {
        $out = array('ok' => true, 'transferred' => 0, 'skipped' => array());
        $projectId = (int) $projectId;
        if (!self::autoTransferEnabledFor($projectId)) {
            $out['skipped'][] = 'المشروعُ خارج علم EMS_ROTATION_AUTOTRANSFER — لا نقلَ آليًّا';
            return $out;
        }
        $eqs = array();
        try {
            $eqs = $gate->scopedQuery(array('scope' => array('c' => 'op_containers')),
                "SELECT c.id FROM op_containers c
                 WHERE {TENANT_SCOPE} AND c.project_id = ? AND c.level = 'معدة'
                   AND c.state = 'نشطة' AND COALESCE(c.is_deleted,0)=0
                 ORDER BY c.id", array($projectId));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كقائمةٍ فارغة — $eqs'); $eqs = array(); }

        foreach ($eqs as $eq) {
            $eqId = (int) $eq['id'];
            $duty = self::onDuty($gate, $eqId, $date);
            if ($duty['on_duty'] === null) {
                $out['skipped'][] = 'معدة#' . $eqId . ': لا مناوبَ بحسب الجدول — يُعلَن ولا يُخترع';
                continue;
            }
            // الحائزُ النشطُ الحالي
            $actives = array();
            try {
                $actives = $gate->scopedQuery(array('scope' => array('o' => 'op_containers')),
                    "SELECT o.id, o.operator_employee_id FROM op_containers o
                     WHERE {TENANT_SCOPE} AND o.parent_id = ? AND o.level = 'مشغّل'
                       AND o.state = 'نشطة' AND COALESCE(o.is_deleted,0)=0
                     ORDER BY o.id", array($eqId));
            } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كقائمةٍ فارغة — $actives'); $actives = array(); }
            if (count($actives) !== 1) {
                if (count($actives) > 1) {
                    $out['skipped'][] = 'معدة#' . $eqId . ': ' . count($actives)
                        . ' حاوياتٍ نشطة (شراكةٌ لا تناوب) — تُدار يدويًّا';
                }
                continue;
            }
            $holder = (int) $actives[0]['operator_employee_id'];
            if ($holder === (int) $duty['on_duty']) { continue; }   // عطالة — الحائزُ هو المناوب

            $r = self::swap($conn, $gate, $companyId, (int) $actives[0]['id'], (int) $duty['on_duty'],
                'تناوبٌ مجدول — انتقالُ الحصة آليًّا لمناوبِ ' . $date . ' (N-05)', '', $actor, $date);
            if (!empty($r['ok'])) { $out['transferred']++; }
            else { $out['skipped'][] = 'معدة#' . $eqId . ': ' . $r['reason']; }
        }
        return $out;
    }

    /** علمُ النقل الآلي — قائمةُ مشاريعَ لا ثنائي (N-04 §2-③). */
    public static function autoTransferEnabledFor($projectId)
    {
        $raw = function_exists('ems_env') ? trim((string) ems_env('EMS_ROTATION_AUTOTRANSFER', '')) : '';
        if ($raw === '') { return false; }
        foreach (explode(',', $raw) as $p) {
            if ((int) trim($p) === (int) $projectId && (int) $projectId > 0) { return true; }
        }
        return false;
    }

    /**
     * «من يعمل في أي تاريخ» — جدولُ المناوبة لسلسلة حاوية معدة.
     *
     * لكل مشغّلٍ أحدثُ دورةٍ ساريةٍ له: موقعُ اليوم في (on+off) من anchor —
     * داخل on = مناوب. بلا دورةٍ = مناوبٌ دائمًا. الترتيبُ بالدور.
     * @return array{on_duty:?int,on_duty_container:?int,chain:array}
     */
    public static function onDuty($gate, $equipmentContainerId, $date)
    {
        $result = array('on_duty' => null, 'on_duty_container' => null, 'chain' => array());
        $ops = array();
        try {
            // السلسلةُ كاملةً: النشطةُ **والمعلَّقة** (عضويةُ السلسلة غيرُ حال
            // رصيدها — المجمَّدةُ بالتناوب تعود) · المقفلةُ وحدَها خارجَها.
            $ops = $gate->scopedQuery(array('scope' => array('o' => 'op_containers')),
                "SELECT o.id, o.operator_employee_id, o.role_kind FROM op_containers o
                 WHERE {TENANT_SCOPE} AND o.parent_id = ? AND o.level = 'مشغّل'
                   AND o.state <> 'مقفلة' AND COALESCE(o.is_deleted,0)=0
                 ORDER BY o.id", array((int) $equipmentContainerId));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كقائمةٍ فارغة — $ops'); $ops = array(); }

        foreach ($ops as $o) {
            $rot = null;
            try {
                $rot = $gate->selectOne('operator_rotations', array(
                    'whereRaw' => "container_id = ? AND (valid_to IS NULL OR valid_to >= ?)",
                    'params' => array((int) $o['id'], (string) $date)));
            } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $rot'); $rot = null; }

            $duty = true; $why = 'بلا دورةٍ — مناوبٌ دائمًا (الغيابُ لا يُخترع له نمط)';
            if ($rot) {
                $on = (int) $rot['cycle_on_days']; $off = (int) $rot['cycle_off_days'];
                $anchor = (string) $rot['cycle_start'];
                $span = $on + $off;
                if ($on <= 0 || $span <= 0 || $anchor === '') {
                    $duty = true; $why = 'دورةٌ غيرُ صالحةٍ — تُعامل مناوبًا وتُعلَن';
                } else {
                    $days = (int) floor((strtotime($date) - strtotime($anchor)) / 86400);
                    if ($days < 0) { $duty = false; $why = 'دورتُه لم تبدأ بعد (' . $anchor . ')'; }
                    else {
                        $pos = $days % $span;
                        $duty = $pos < $on;
                        $why = $duty
                            ? 'مناوبٌ (اليوم ' . ($pos + 1) . ' من ' . $on . ' عمل)'
                            : 'في راحته (اليوم ' . ($pos - $on + 1) . ' من ' . $off . ' راحة)';
                    }
                }
            }
            $result['chain'][] = array(
                'container_id' => (int) $o['id'],
                'operator_employee_id' => (int) $o['operator_employee_id'],
                'role_kind' => (string) $o['role_kind'],
                'on_duty' => $duty, 'why' => $why,
                'order' => self::ROLE_ORDER[(string) $o['role_kind']] ?? 9,
            );
        }
        usort($result['chain'], function ($a, $b) { return $a['order'] <=> $b['order']; });
        foreach ($result['chain'] as $link) {
            if ($link['on_duty']) {
                $result['on_duty'] = $link['operator_employee_id'];
                $result['on_duty_container'] = $link['container_id'];
                break;
            }
        }
        return $result;
    }
}
