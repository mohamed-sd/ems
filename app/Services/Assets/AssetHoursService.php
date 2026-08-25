<?php
/**
 * AssetHoursService — ربطُ الأصلِ بساعاتِ تشغيلِه (ENG-01 · F-17 · CK-17)
 * ───────────────────────────────────────────────────────────────────────────
 * «◆ فالأصلُ لا يعرف كم عمل والقيدُ يعرف»
 *
 * F-17 نصًّا في TS-01:
 *   SELECT machine_code, DATE_FORMAT(work_date,'%Y-%m-01') m, SUM(run_hours)
 *     FROM unit_entries GROUP BY machine_code, m
 *
 * ◆ وفرقُه عن الحيّ (المخطّطُ أصدق — TSP-0003):
 *   unit_entries في هذا النظامِ بلا run_hours ولا machine_code ولا work_date.
 *   والساعاتُ مطبَّعةٌ في unit_time_log(equipment_id, log_date, hours, ops_state)
 *   و«ساعاتُ التشغيل» فيها هي ops_state='actual_work' وحدَها — لا مجموعُ
 *   الساعاتِ كلِّها (فالانتظارُ والعطلُ زمنٌ لا تشغيل).
 *   وmachine_code يُقرأ من equipments.code لا يُخترع.
 */

namespace App\Services\Assets;

class AssetHoursService
{
    /** F-17 بصيغتِه المحلية — ساعاتُ التشغيلِ لكلِّ معدة-شهر. */
    const F17 = "SELECT `company_id`, `equipment_id`,
                        DATE_FORMAT(`log_date`,'%Y-%m') AS `period`,
                        SUM(`hours`) AS `hours_from_shifts`
                   FROM `unit_time_log`
                  WHERE `ops_state` = 'actual_work'
                  GROUP BY `company_id`, `equipment_id`, `period`";

    /**
     * ربطُ أصلٍ بساعاتِه لفترةٍ (asset.hours.link).
     * لا يكتب من الشاشة: الشاشةُ تنادي هذه، وهذه وحدَها تكتب.
     *
     * @return array{ok:bool, reason:string, rec_id?:int, hours?:float}
     */
    public static function link(\mysqli $conn, array $in)
    {
        $co     = (int) ($in['company_id'] ?? 0);
        $eqId   = (int) ($in['equipment_id'] ?? 0);
        $period = (string) ($in['period'] ?? '');
        $assetId= isset($in['asset_id']) && $in['asset_id'] !== '' ? (int) $in['asset_id'] : null;
        $method = isset($in['depr_method']) && $in['depr_method'] !== '' ? (string) $in['depr_method'] : null;
        $life   = isset($in['useful_life_hours']) && $in['useful_life_hours'] !== '' ? (int) $in['useful_life_hours'] : null;
        $rate   = isset($in['rate_per_hour']) && $in['rate_per_hour'] !== '' ? (float) $in['rate_per_hour'] : null;
        $by     = (int) ($in['actor'] ?? 0);

        if ($co <= 0 || $eqId <= 0) { return array('ok' => false, 'reason' => 'الكيان والمعدة إلزاميان'); }
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            return array('ok' => false, 'reason' => 'الفترة بصيغة YYYY-MM');
        }
        if ($method !== null && !in_array($method, array('straight_line', 'usage_hours', 'units_produced'), true)) {
            return array('ok' => false, 'reason' => 'طريقة إهلاك غير معروفة');
        }

        // ◆ الملكيةُ من الحيِّ لا من الشاشة — «معدةُ الموردِ لا تُهلَك عندنا»
        $eq = $conn->prepare("SELECT `code`, `suppliers` FROM `equipments` WHERE `id`=? LIMIT 1");
        $eq->bind_param('i', $eqId); $eq->execute();
        $eqRow = $eq->get_result()->fetch_assoc(); $eq->close();
        if (!$eqRow) { return array('ok' => false, 'reason' => 'معدة غير موجودة'); }
        $machineCode = (string) $eqRow['code'];
        $ownerType = (trim((string) $eqRow['suppliers']) === '' || (string) $eqRow['suppliers'] === '0')
            ? 'company' : 'supplier';

        // ◆ الساعاتُ من القيدِ اليوميِّ — لا تُدخَل من الشاشة (F-17)
        $h = $conn->prepare(
            "SELECT COALESCE(SUM(`hours`),0) FROM `unit_time_log`
              WHERE `company_id`=? AND `equipment_id`=?
                AND DATE_FORMAT(`log_date`,'%Y-%m')=? AND `ops_state`='actual_work'"
        );
        $h->bind_param('iis', $co, $eqId, $period); $h->execute();
        $hours = (float) $h->get_result()->fetch_row()[0]; $h->close();

        $st = $conn->prepare(
            "INSERT INTO `asset_hour_reconciliations`
                (`company_id`,`equipment_id`,`period`,`asset_id`,`machine_code`,`owner_type`,
                 `depr_method`,`useful_life_hours`,`depreciation_per_hour`,
                 `timesheet_hours`,`hours_from_shifts`,`hours_undepreciated`,`state`)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'open')
             ON DUPLICATE KEY UPDATE
                `asset_id`=VALUES(`asset_id`), `machine_code`=VALUES(`machine_code`),
                `owner_type`=VALUES(`owner_type`), `depr_method`=VALUES(`depr_method`),
                `useful_life_hours`=VALUES(`useful_life_hours`),
                `depreciation_per_hour`=VALUES(`depreciation_per_hour`),
                `hours_from_shifts`=VALUES(`hours_from_shifts`),
                `hours_undepreciated`=CASE WHEN `depreciation_amount` IS NULL
                                           THEN VALUES(`hours_from_shifts`) ELSE 0 END"
        );
        if (!$st) { return array('ok' => false, 'reason' => 'prepare: ' . $conn->error); }
        $undep = $hours;
        $st->bind_param(
            'iisissiddddd',
            $co, $eqId, $period, $assetId, $machineCode, $ownerType,
            $method, $life, $rate, $hours, $hours, $undep
        );
        if (!$st->execute()) {
            $e = $st->error; $st->close();
            return array('ok' => false, 'reason' => $e);
        }
        $st->close();

        $rec = $conn->prepare(
            "SELECT `rec_id` FROM `asset_hour_reconciliations`
              WHERE `company_id`=? AND `equipment_id`=? AND `period`=? LIMIT 1");
        $rec->bind_param('iis', $co, $eqId, $period); $rec->execute();
        $recId = (int) ($rec->get_result()->fetch_row()[0] ?? 0); $rec->close();

        return array('ok' => true, 'reason' => 'ربط الأصل بساعاته', 'rec_id' => $recId,
            'hours' => $hours, 'owner_type' => $ownerType, 'machine_code' => $machineCode);
    }

    /** ردمٌ جماعيٌّ للساعاتِ من القيدِ اليوميّ — يُنادى من المهمةِ المجدولة. */
    public static function refreshAll(\mysqli $conn, $companyId = 0)
    {
        $where = $companyId > 0 ? ' AND a.`company_id`=' . (int) $companyId : '';
        $conn->query(
            "UPDATE `asset_hour_reconciliations` a
               JOIN (" . self::F17 . ") s
                 ON s.`company_id`=a.`company_id` AND s.`equipment_id`=a.`equipment_id`
                AND s.`period`=a.`period`
                SET a.`hours_from_shifts` = s.`hours_from_shifts`,
                    a.`hours_undepreciated` = CASE WHEN a.`depreciation_amount` IS NULL
                                                   THEN s.`hours_from_shifts` ELSE 0 END
             WHERE 1=1" . $where
        );
        return max(0, $conn->affected_rows);
    }
}
