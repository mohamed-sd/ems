<?php
/**
 * مطابقة الأصول بالتشغيل + فصل المصدر — N-17 · N-19 (PLAN-04 §2.4 · §1.2)
 * ───────────────────────────────────────────────────────────────────────────
 * N-17: «تقرير دوري يقارن ساعات سجل الأصول بساعات التايم شيت لكل معدة ويُلزم
 * بتفسير الفرق — فلا فرق بلا سبب. ومنه معدل الإهلاك بالساعة والإهلاك غير
 * المحتسب — ومعدة عملت ولم تُهلك تشوّه تكلفة المشروع وربحيته».
 * N-19: قرار إقفال «غير محددة الملكية» لكل معدة — حالة نقص تُغلق لا صنف دائم.
 */

namespace App\Services\Fleet;

class AssetReconciliationService
{
    /**
     * بناء مطابقة فترة: لكل معدة لها نشاط — ساعات العدّاد (Σ delta بنوع hour)
     * مقابل ساعات التايم شيت المعتمدة (total_work_hours) + إهلاك الفترة.
     * @return array{ok:bool,rows:int,undepreciated:int,reason:string}
     */
    public static function buildPeriod(\mysqli $conn, $companyId, $period, $toleranceHours = 1.0)
    {
        $companyId = intval($companyId);
        $period = (string) $period;
        // المعدات ذات النشاط في الفترة (عدّاد أو تايم شيت)
        // التايم شيت يصل المعدة عبر operations (t.operator → operations.id → equipment)
        $sql = "SELECT e.eq FROM (
                    SELECT m.equipment_id eq FROM meter_readings m
                     WHERE m.company_id = ? AND m.meter_type = 'hour' AND DATE_FORMAT(m.reading_date, '%Y-%m') = ?
                    UNION
                    SELECT o.equipment eq FROM timesheet t JOIN operations o ON o.id = t.operator
                     WHERE t.company_id = ? AND DATE_FORMAT(t.date, '%Y-%m') = ?
                ) e";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('isis', $companyId, $period, $companyId, $period);
        $stmt->execute();
        $eqs = array_map(function ($r) { return intval($r['eq']); }, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        $stmt->close();

        $rows = 0; $undep = 0;
        foreach (array_unique(array_filter($eqs)) as $eq) {
            // ساعات السجل: Σ delta لعدّاد الساعات في الفترة (القراءات المتسلسلة)
            $stmt = $conn->prepare(
                "SELECT COALESCE(SUM(m.delta),0) h FROM meter_readings m
                  WHERE m.company_id = ? AND m.equipment_id = ? AND m.meter_type = 'hour'
                    AND m.is_reset = 0 AND DATE_FORMAT(m.reading_date, '%Y-%m') = ?");
            $stmt->bind_param('iis', $companyId, $eq, $period);
            $stmt->execute();
            $reg = (float) $stmt->get_result()->fetch_assoc()['h'];
            $stmt->close();
            // ساعات التايم شيت — عبر رابط العملية
            $stmt = $conn->prepare(
                "SELECT COALESCE(SUM(t.total_work_hours),0) h FROM timesheet t
                   JOIN operations o ON o.id = t.operator
                  WHERE t.company_id = ? AND o.equipment = ? AND DATE_FORMAT(t.date, '%Y-%m') = ?");
            $stmt->bind_param('iis', $companyId, $eq, $period);
            $stmt->execute();
            $ts = (float) $stmt->get_result()->fetch_assoc()['h'];
            $stmt->close();
            // إهلاك الفترة للأصل المرتبط بالمعدة
            $stmt = $conn->prepare(
                "SELECT COALESCE(SUM(d.depreciation_amount),0) a FROM fin_depreciation d
                   JOIN fin_assets fa ON fa.id = d.asset_id
                  WHERE fa.company_id = ? AND fa.equipment_id = ? AND d.period_ref = ?");
            $stmt->bind_param('iis', $companyId, $eq, $period);
            $stmt->execute();
            $dep = (float) $stmt->get_result()->fetch_assoc()['a'];
            $stmt->close();

            $worked = max($reg, $ts);
            $perHour = ($dep > 0 && $worked > 0) ? round($dep / $worked, 4) : null;
            $undepFlag = ($worked > 0 && $dep <= 0) ? 1 : 0;
            if ($undepFlag) { $undep++; }
            $state = (abs($reg - $ts) <= $toleranceHours) ? 'explained' : 'open';
            $explanation = ($state === 'explained') ? 'ضمن حد السماح (' . $toleranceHours . ' ساعة)' : null;
            $explainedBy = ($state === 'explained') ? 0 : null;

            $stmt = $conn->prepare(
                "INSERT INTO asset_hour_reconciliations
                   (company_id, equipment_id, period, register_hours, timesheet_hours, depreciation_amount, depreciation_per_hour, undepreciated_flag, state, explanation, explained_by, explained_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, IF(? = 'explained', NOW(), NULL))
                 ON DUPLICATE KEY UPDATE
                   register_hours = VALUES(register_hours), timesheet_hours = VALUES(timesheet_hours),
                   depreciation_amount = VALUES(depreciation_amount), depreciation_per_hour = VALUES(depreciation_per_hour),
                   undepreciated_flag = VALUES(undepreciated_flag)");
            $stmt->bind_param('iisdddsissis', $companyId, $eq, $period, $reg, $ts, $dep, $perHour, $undepFlag, $state, $explanation, $explainedBy, $state);
            $stmt->execute();
            $stmt->close();
            $rows++;
        }
        return array('ok' => true, 'rows' => $rows, 'undepreciated' => $undep,
            'reason' => $rows . ' معدة طوبقت — ' . $undep . ' عملت ولم تهلك (تشوه التكلفة معلن)');
    }

    /** تفسير فرق — إلزامي لإغلاق الصف (CHECK يسنده). */
    public static function explainDiff(\mysqli $conn, $companyId, $recId, $explanation, $actor)
    {
        $explanation = trim((string) $explanation);
        if ($explanation === '') {
            return array('ok' => false, 'code' => 422, 'reason' => 'لا فرق بلا سبب — التفسير إلزامي');
        }
        $stmt = $conn->prepare(
            "UPDATE asset_hour_reconciliations SET state = 'explained', explanation = ?, explained_by = ?, explained_at = NOW()
              WHERE company_id = ? AND rec_id = ? AND state = 'open'");
        $companyId = intval($companyId); $recId = intval($recId); $act = intval($actor);
        $stmt->bind_param('siii', $explanation, $act, $companyId, $recId);
        $stmt->execute();
        $done = $stmt->affected_rows > 0;
        $stmt->close();
        return array('ok' => $done, 'code' => $done ? 200 : 404, 'reason' => $done ? 'فسر الفرق بسببه' : 'غير موجود أو مفسر');
    }

    // ═══════════ N-19 — فصل المصدر عن الملكية ═══════════

    /** إقفال «غير محددة الملكية» بقرار لكل معدة — حالة نقص تُغلق لا صنف دائم. */
    public static function decideSource(\mysqli $conn, $companyId, $equipmentId, $source, $note, $actor)
    {
        if (!in_array((string) $source, array('financed', 'supplier_external'), true)) {
            return array('ok' => false, 'code' => 422, 'reason' => 'قيمتان لا ثالثة: financed أو supplier_external');
        }
        $note = trim((string) $note);
        if ($note === '') {
            return array('ok' => false, 'code' => 422, 'reason' => 'قرار الإقفال بسببه — لا إقفال صامتا');
        }
        $stmt = $conn->prepare(
            "INSERT INTO equipment_ownership_registry (company_id, equipment_id, operational_source, source_decided_by, source_decided_at, source_decision_note, note)
             VALUES (?, ?, ?, ?, NOW(), ?, 'أُنشئ بقرار مصدر N-19')
             ON DUPLICATE KEY UPDATE operational_source = VALUES(operational_source),
               source_decided_by = VALUES(source_decided_by), source_decided_at = NOW(),
               source_decision_note = VALUES(source_decision_note)");
        $companyId = intval($companyId); $equipmentId = intval($equipmentId);
        $src = (string) $source; $act = intval($actor);
        $stmt->bind_param('iisis', $companyId, $equipmentId, $src, $act, $note);
        $stmt->execute();
        $stmt->close();
        return array('ok' => true, 'code' => 200, 'reason' => 'حسم مصدر المعدة #' . $equipmentId . ': ' . $src . ' — بقرار موثق');
    }

    /** تقرير غير المحدد — المتبقي للإقفال (في المجال المقيَّد — للمخوَّلين). */
    public static function undecidedSources(\mysqli $conn, $companyId)
    {
        $companyId = intval($companyId);
        $r = $conn->query(
            "SELECT equipment_id FROM equipment_ownership_registry
              WHERE company_id = {$companyId} AND operational_source IS NULL");
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : array();
    }

}
