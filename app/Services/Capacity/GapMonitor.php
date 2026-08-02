<?php
/**
 * app/Services/Capacity/GapMonitor.php — مرقبُ الفجوة اليومي (CAP-21)
 * ═══════════════════════════════════════════════════════════════════════════
 * CAP-01 §10: «مؤشرٌ يوميٌّ لا شهري — فالفجوةُ التي تُكتشف آخرَ الشهر خسارةٌ
 * وقعت» · «تظهر في لوحة العقد ولوحة مدير التشغيل **بالساعات لا بالعدد فقط**» ·
 * «تُصعَّد آليًّا إن تجاوزت مهلتَها المعلنةَ بلا معالجة — لمدير التشغيل ثم
 * للإدارة العامة» (C13).
 *
 * الفجوةُ لكل التزامٍ = (الأساسيُّ المتعاقدُ − المقاعدُ المغطاةُ بتخصيصٍ فعّال)
 * × كميةِ الوحدة الشهرية — **بالساعات (بمقياسه) لا بالعدد**.
 * والأيامُ والمهلُ بساعة القاعدة لا PHP.
 * الأحداث: CoverageGapOpened · GapEscalated · GapClosed — عبر EventPublisher حصرًا.
 */

namespace App\Services\Capacity;

require_once __DIR__ . '/../../Core/EventPublisher.php';

use App\Core\EventPublisher;

class GapMonitor
{
    /** فاعلُ الدورة — النظامُ (1) افتراضًا؛ العقدُ يرفض صفرًا. */
    private static $actor = 1;

    /**
     * الدورةُ اليومية لشركة — تُستدعى من الكرون (نمطُ forSystem بشركة الوحدة).
     * @return array{ok:bool,checked:int,opened:int,escalated:int,closed:int,gaps:array}
     */
    public static function runDaily($conn, $gate, $companyId, $actor = 1)
    {
        $out = array('ok' => true, 'checked' => 0, 'opened' => 0, 'escalated' => 0, 'closed' => 0, 'gaps' => array());
        self::$actor = (int) $actor ?: 1;
        $today = self::dbToday($conn);

        // الالتزاماتُ ذواتُ العدد المتعاقد — لكلٍّ فجوتُها اليوم
        $obls = $gate->scopedQuery(array('scope' => array('c' => 'contract_commitments')),
            "SELECT c.id, c.primary_units_contracted, c.qty_per_primary_unit_month, c.measure_code
               FROM contract_commitments c
              WHERE {TENANT_SCOPE} AND c.is_deleted = 0 AND c.primary_units_contracted IS NOT NULL
                AND c.equipment_type_code IS NOT NULL", array());
        foreach ($obls as $obl) {
            $out['checked']++;
            $oblId = (int) $obl['id'];
            $target = (int) $obl['primary_units_contracted'];
            // المغطى = مقاعدُ الالتزام ذاتُ تخصيصٍ فعّالٍ اليوم (أساسيٌّ أو احتياطيٌّ مفعَّل)
            $rows = $gate->scopedQuery(array(
                    'scope' => array('c' => 'op_containers', 's' => 'seat_assignments')),
                "SELECT COUNT(DISTINCT c.id) covered
                   FROM op_containers c
                   JOIN seat_assignments s ON s.container_id = c.id AND s.state = 'active'
                        AND (s.assignment_role <> 'احتياطي' OR s.activation_state = 'active')
                        AND s.date_from <= ? AND (s.date_to IS NULL OR s.date_to >= ?)
                  WHERE {TENANT_SCOPE} AND c.obl_id = ? AND c.seat_no IS NOT NULL AND c.is_deleted = 0",
                array($today, $today, $oblId));
            $covered = $rows ? (int) $rows[0]['covered'] : 0;
            $gapUnits = max(0, $target - $covered);
            $qtyMonth = $obl['qty_per_primary_unit_month'] !== null ? (float) $obl['qty_per_primary_unit_month'] : 0.0;
            $gapHours = round($gapUnits * $qtyMonth, 2);
            $measure = $obl['measure_code'] !== null ? (string) $obl['measure_code'] : 'hour';

            $open = $gate->scopedQuery(array('scope' => array('g' => 'capacity_gap_watch')),
                "SELECT g.* FROM capacity_gap_watch g WHERE {TENANT_SCOPE} AND g.obl_id = ? AND g.closed_on IS NULL",
                array($oblId));

            if ($gapUnits <= 0) {
                if ($open) {
                    $gate->update('capacity_gap_watch',
                        array('closed_on' => $today, 'state' => 'closed', 'last_seen_on' => $today),
                        array('gap_id' => (int) $open[0]['gap_id']));
                    self::emit($conn, $companyId, 'capacity.gap_closed', $oblId, 0, 0, $measure,
                        array('closed_on' => $today));
                    $out['closed']++;
                }
                continue;
            }

            if (!$open) {
                $gate->insert('capacity_gap_watch', array(
                    'obl_id' => $oblId, 'gap_units' => $gapUnits, 'gap_hours' => $gapHours,
                    'measure_code' => $measure, 'opened_on' => $today, 'last_seen_on' => $today,
                ));
                self::emit($conn, $companyId, 'capacity.gap_opened', $oblId, $gapUnits, $gapHours, $measure,
                    array('opened_on' => $today, 'covered' => $covered, 'target' => $target));
                $out['opened']++;
                $out['gaps'][] = array('obl_id' => $oblId, 'gap_units' => $gapUnits,
                                       'gap_hours' => $gapHours, 'measure' => $measure, 'state' => 'open');
                continue;
            }

            $g = $open[0];
            $upd = array('gap_units' => $gapUnits, 'gap_hours' => $gapHours, 'last_seen_on' => $today);
            // التصعيدُ الآلي — الأيامُ بساعة القاعدة (DATEDIFF)
            $age = self::dbDateDiff($conn, $today, (string) $g['opened_on']);
            $limit = (int) $g['escalate_after_days'];
            $state = (string) $g['state'];
            if ($age > $limit && $g['escalated_ops_at'] === null) {
                $upd['escalated_ops_at'] = $today . ' 00:00:00';
                $upd['state'] = $state = 'escalated_ops';
                self::emit($conn, $companyId, 'capacity.gap_escalated', $oblId, $gapUnits, $gapHours, $measure,
                    array('to' => 'ops_manager', 'age_days' => $age));
                $out['escalated']++;
            } elseif ($age > $limit * 2 && $g['escalated_gm_at'] === null) {
                $upd['escalated_gm_at'] = $today . ' 00:00:00';
                $upd['state'] = $state = 'escalated_gm';
                self::emit($conn, $companyId, 'capacity.gap_escalated', $oblId, $gapUnits, $gapHours, $measure,
                    array('to' => 'general_management', 'age_days' => $age));
                $out['escalated']++;
            }
            $gate->update('capacity_gap_watch', $upd, array('gap_id' => (int) $g['gap_id']));
            $out['gaps'][] = array('obl_id' => $oblId, 'gap_units' => $gapUnits,
                                   'gap_hours' => $gapHours, 'measure' => $measure, 'state' => $state);
        }
        return $out;
    }

    /** الحدثُ عبر الجذر المحايد — publishFact تُرجع مصفوفةً لا رقمًا. */
    private static function emit($conn, $companyId, $key, $oblId, $gapUnits, $gapHours, $measure, array $payload)
    {
        try {
            EventPublisher::publishFact($conn, array(
                'company_id'   => (int) $companyId,
                'event_key'    => $key,
                'category'     => 'operational',
                'source_module'=> 'capacity',
                'entity_type'  => 'contract_commitment',
                'entity_id'    => (int) $oblId,
                'created_by'   => self::$actor,
                'occurred_at'  => date('Y-m-d H:i:s'),
                'quantity'     => $gapHours,
                'unit'         => $measure,
                'payload'      => array_merge($payload, array('gap_units' => $gapUnits, 'gap_hours' => $gapHours)),
                'idempotency_key' => substr($key . ':' . $oblId . ':' . (isset($payload['opened_on']) ? $payload['opened_on'] : '')
                                    . (isset($payload['to']) ? ':' . $payload['to'] : '')
                                    . (isset($payload['closed_on']) ? $payload['closed_on'] : ''), 0, 64),
            ));
        } catch (\Throwable $t) {
            error_log('GapMonitor emit ' . $key . ': ' . $t->getMessage());
        }
    }

    private static function dbToday($conn)
    {
        $r = $conn->query('SELECT CURDATE() d');
        return $r ? (string) $r->fetch_assoc()['d'] : date('Y-m-d');
    }

    private static function dbDateDiff($conn, $a, $b)
    {
        $stmt = $conn->prepare('SELECT DATEDIFF(?, ?) d');
        $stmt->bind_param('ss', $a, $b);
        $stmt->execute();
        $d = (int) $stmt->get_result()->fetch_assoc()['d'];
        $stmt->close();
        return $d;
    }
}
