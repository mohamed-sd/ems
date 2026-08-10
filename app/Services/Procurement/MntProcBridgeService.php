<?php
/**
 * app/Services/Procurement/MntProcBridgeService.php — جسرُ الصيانة ← المشتريات
 * ═══════════════════════════════════════════════════════════════════════════
 * (③ من إضافات الدور 16 · فكُّ تأجيل الوثيقة بقرار المالك 2026-08-06)
 *
 * «أغلى خسارةٍ في التأجير معدةٌ واقفةٌ بانتظار قطعة»: أمرُ الصيانة المفتوح
 * ذو القطع يولّد **طلبَ شراءٍ آليًّا** بأولويةٍ مشتقةٍ من مصدره — فلا فجوةَ
 * «شخصٌ يتذكر فيكتب طلبًا».
 *
 * الاتجاهُ أحاديٌّ حصرًا: الصيانةُ تُقرأ ولا تُكتب (لا مساسَ بجداول mnt_*).
 * العطالة: source_ref = 'MNT#<id>' — أمرُ صيانةٍ واحدٌ = طلبٌ واحدٌ أبدًا.
 * قطعةُ الصيانة نصيةٌ (mnt_order_part.part_name بلا ربط كتالوج) — تُطابَق
 * بالاسم الحرفي على proc_item الحي، وما لم يُطابَق يمضي سطرًا حرًّا معلَنًا.
 */

namespace App\Services\Procurement;

require_once __DIR__ . '/../../../includes/catch_log.php';

class MntProcBridgeService
{
    /** حالاتُ أمر الصيانة المفتوحة (قائمةُ سماحٍ — بذورُ الحالات الفاسدة خارجها تلقائيًا). */
    const OPEN_STATES = array('بلاغ', 'تنفيذ', 'فحص');

    /**
     * مسحُ أوامر الصيانة المفتوحة ذات القطع وتوليدُ طلبات الشراء الناقصة.
     *
     * @param  bool $dry true = عرضُ الخطة بلا كتابة
     * @return array{generated:array,skipped:array}
     */
    public static function run($conn, $gate, $companyId, $actor, $dry = true)
    {
        $co = (int) $companyId;
        $out = array('generated' => array(), 'skipped' => array());

        $in = "'" . implode("','", self::OPEN_STATES) . "'";
        $r = $conn->query(
            "SELECT mo.id, mo.code, mo.source, mo.state, mo.equipment_id,
                    COUNT(mp.id) parts_n
               FROM mnt_order mo
               JOIN mnt_order_part mp ON mp.order_id = mo.id
              WHERE mo.company_id = {$co} AND COALESCE(mo.is_deleted,0) = 0
                AND mo.state IN ($in)
              GROUP BY mo.id, mo.code, mo.source, mo.state, mo.equipment_id");
        $orders = array();
        while ($r && ($x = $r->fetch_assoc())) { $orders[] = $x; }

        foreach ($orders as $mo) {
            $moId = (int) $mo['id'];
            $ref  = 'MNT#' . $moId;

            // العطالة: طلبٌ حيٌّ بهذا المرجع = لا توليدَ ثانيًا
            $dup = $gate->selectOne('proc_request', array(
                'columns' => array('id', 'code'),
                'whereRaw' => 'source_ref = ? AND COALESCE(is_deleted,0) = 0',
                'params' => array($ref),
            ));
            if ($dup) {
                $out['skipped'][] = array('mnt' => (string) $mo['code'],
                    'reason' => 'طلبٌ قائمٌ ' . $dup['code'] . ' — لا ازدواج');
                continue;
            }

            // القطعُ بأسمائها — مطابقةُ الكتالوج بالاسم الحرفي (الحي فقط)
            $parts = array();
            $pr = $conn->query("SELECT part_name, quantity FROM mnt_order_part
                                 WHERE company_id = {$co} AND order_id = {$moId}");
            while ($pr && ($p = $pr->fetch_assoc())) {
                $pname = trim((string) $p['part_name']);
                if ($pname === '') { continue; }
                $item = $gate->selectOne('proc_item', array(
                    'columns' => array('id'),
                    'whereRaw' => 'name = ? AND status = 1',
                    'params' => array($pname),
                ));
                $parts[] = array(
                    'item_id'   => $item ? intval($item['id']) : null,
                    'item_name' => $pname,
                    'qty'       => max(0.01, (float) $p['quantity']),
                );
            }
            if (!$parts) {
                $out['skipped'][] = array('mnt' => (string) $mo['code'], 'reason' => 'قطعٌ بلا أسماء');
                continue;
            }

            // الأولويةُ من المصدر: بلاغُ عطلٍ = معدةٌ واقفةٌ غالبًا → «حرج»
            $isBreakdown = ((string) $mo['source'] === 'بلاغ' || (string) $mo['state'] === 'بلاغ');
            $plan = array('mnt' => (string) $mo['code'], 'parts' => count($parts),
                          'priority' => $isBreakdown ? 'حرج' : 'عاجل');

            if (!$dry) {
                try {
                    $reqId = 0;
                    $gate->runInTransaction(function ($g) use ($conn, $co, $mo, $moId, $ref, $parts, $isBreakdown, $actor, &$reqId) {
                        require_once dirname(__DIR__, 2) . '/../Procurement/proc_helpers.php';
                        $reqId = (int) $g->insert('proc_request', array(
                            'code'              => proc_gen_code($conn, 'proc_request', 'PRC-REQ', $co),
                            'need_source'       => 'أمر صيانة',
                            'source_ref'        => $ref,
                            'op_classification' => $isBreakdown ? 'تصحيحية' : 'وقائية',
                            'requesting_dept'   => 'الصيانة',
                            'equipment_id'      => intval($mo['equipment_id']) > 0 ? intval($mo['equipment_id']) : null,
                            'priority'          => $isBreakdown ? 'حرج' : 'عاجل',
                            'state'             => 'مقدَّم',
                            'notes'             => 'توليدٌ آليٌّ من أمر الصيانة ' . $mo['code'] . ' — جسرُ MNT↔PROC',
                            'created_by'        => (int) $actor,
                        ));
                        foreach ($parts as $pl) {
                            $g->insert('proc_request_line', array(
                                'request_id' => $reqId, 'item_id' => $pl['item_id'],
                                'item_name' => $pl['item_name'], 'qty' => $pl['qty'],
                            ));
                        }
                    }, 'mnt bridge request ' . $ref);
                    $plan['request_id'] = $reqId;
                } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'جسرُ الصيانةِ إلى المشترياتِ فشل — أمرُ الصيانةِ قائمٌ والطلبُ يُنشأ يدويًّا');
                    $out['skipped'][] = array('mnt' => (string) $mo['code'],
                        'reason' => 'تعذّر التوليد: ' . $t->getMessage());
                    continue;
                }
            }
            $out['generated'][] = $plan;
        }
        return $out;
    }

    /**
     * «معداتٌ واقفةٌ بانتظار قطعة» — مؤشرُ نزيف التوقف للوحات:
     * أوامرُ صيانةٍ مفتوحةٌ بقطعٍ، طلبُها الآليُّ لم يبلغ التحويل لأمر شراءٍ
     * مستلَم بعد. يعيد الصفوف (المعدة · أمر الصيانة · الطلب · حالته).
     */
    public static function waitingEquipment($conn, $companyId, $limit = 10)
    {
        $co = (int) $companyId;
        $in = "'" . implode("','", self::OPEN_STATES) . "'";
        $rows = array();
        $r = $conn->query(
            "SELECT mo.code mnt_code, mo.state mnt_state, e.name equipment_name,
                    pr.code req_code, pr.state req_state, pr.priority,
                    DATEDIFF(CURDATE(), DATE(mo.created_at)) waiting_days
               FROM mnt_order mo
               JOIN mnt_order_part mp ON mp.order_id = mo.id
               LEFT JOIN equipments e ON e.id = mo.equipment_id
               LEFT JOIN proc_request pr ON pr.company_id = mo.company_id
                    AND pr.source_ref = CONCAT('MNT#', mo.id) AND COALESCE(pr.is_deleted,0) = 0
              WHERE mo.company_id = {$co} AND COALESCE(mo.is_deleted,0) = 0
                AND mo.state IN ($in)
                AND (pr.id IS NULL OR pr.state NOT IN ('محوَّل لأمر شراء','مغلق'))
              GROUP BY mo.id, mo.code, mo.state, e.name, pr.code, pr.state, pr.priority, mo.created_at
              ORDER BY waiting_days DESC
              LIMIT " . intval($limit));
        while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
        return $rows;
    }
}
