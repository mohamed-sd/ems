<?php
/**
 * 2027_05_07_formula_views.php
 * ═══════════════════════════════════════════════════════════════════════════
 * الصيغُ مناظرَ محسوبةً — F-05 · F-06 · F-09 · F-11 · F-12 (وF-10 قائمة)
 *
 * «وسيطُ ساعةِ اليومِ وأيامُ العملِ استعلامانِ لا عمودان — فمن خزّنهما خزّن
 * رقمًا يتقادم» (TS-01). كلُّها SQL SECURITY INVOKER وعلى الجداولِ الحيّة:
 *   F-05 المستهدفُ المنقضي  ⇐ v_container_elapsed_target (ولا يتجاوز السعة)
 *   F-06 حصةُ المورد        ⇐ v_supplier_share_units (بتاريخِ سريانِه هو)
 *   F-09 وسيطُ ساعةِ اليوم  ⇐ v_machine_daily_hours + v_machine_daily_median
 *        (منظرانِ لأن المشتقَّ الجدوليَّ داخلَ منظرٍ غيرُ منقول)
 *   F-11 الاستعدادُ المفوتر ⇐ عمودٌ جديدٌ في v_monthly_performance
 *        (تعطلٌ سببُه العميلُ يُفوتَر استعدادًا — إنفاذُ حكمِ المالك)
 *   F-12 هامشُ الخانةِ الكلي ⇐ v_slot_total_margin (والسالبُ يُوسَم)
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$mk = function (string $name, string $sql) use ($conn) {
    if (!$conn->query("CREATE OR REPLACE SQL SECURITY INVOKER VIEW `$name` AS $sql")) {
        exit("  ✘ $name: {$conn->error}\n");
    }
    $r = $conn->query("SELECT COUNT(*) FROM `$name`");
    echo "  ✔ $name (" . ($r ? number_format((int) $r->fetch_row()[0]) : '؟') . " صفًّا)\n";
};

echo "══ مناظرُ الصيغ ══\n\n";

/* حالاتُ التوقفِ تُقرأ من التعدادِ الحيّ */
$r = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unit_time_log' AND COLUMN_NAME='ops_state'");
preg_match_all("/'([^']+)'/", (string) $r->fetch_row()[0], $m);
$stops = array_values(array_diff($m[1], array('actual_work', 'standby', 'unlogged')));
$stopList = "'" . implode("','", $stops) . "'";

/* F-09-أ: ساعاتُ كلِّ يومٍ لكلِّ آلية */
$mk('v_machine_daily_hours',
    "SELECT ue.company_id, ue.equipment_id, ue.entry_date AS work_date,
            ROUND(COALESCE(SUM(CASE WHEN l.ops_state='actual_work' THEN l.hours END),0),2) AS daily_hours
     FROM unit_entries ue
     LEFT JOIN unit_time_log l ON l.entry_id = ue.id
     WHERE ue.seed_tag IS NULL
       AND ue.state NOT IN ('rejected','cancelled','superseded','reversed')
     GROUP BY ue.company_id, ue.equipment_id, ue.entry_date");

/* F-09-ب: الوسيطُ — نصُّ TSP-0117 (ROW_NUMBER حولَ منتصفِ العدِّ) على المنظرِ الأول */
$mk('v_machine_daily_median',
    "SELECT company_id, equipment_id, ROUND(AVG(daily_hours),2) AS median_daily_hours, MAX(cnt) AS days_counted
     FROM (SELECT company_id, equipment_id, daily_hours,
                  ROW_NUMBER() OVER (PARTITION BY company_id, equipment_id ORDER BY daily_hours) rn,
                  COUNT(*)    OVER (PARTITION BY company_id, equipment_id) cnt
           FROM v_machine_daily_hours WHERE daily_hours > 0) x
     WHERE rn IN (FLOOR((cnt+1)/2), CEIL((cnt+1)/2))
     GROUP BY company_id, equipment_id");

/* F-05: المستهدفُ المنقضي — ولا يتجاوز السعةَ ولا ينزل عن صفر */
$mk('v_container_elapsed_target',
    "SELECT c.company_id, c.id AS container_id, c.container_no, c.cap_qty,
            k.actual_start, k.actual_end,
            LEAST(c.cap_qty, GREATEST(0, ROUND(
                c.cap_qty * DATEDIFF(LEAST(CURDATE(), k.actual_end), k.actual_start)
                / NULLIF(DATEDIFF(k.actual_end, k.actual_start), 0), 2))) AS elapsed_target
     FROM op_containers c
     JOIN contracts k ON k.id = c.contract_id
     WHERE c.level = 'رئيسية' AND c.is_deleted = 0
       AND k.actual_start IS NOT NULL AND k.actual_end IS NOT NULL");

/* F-06: حصةُ الموردِ بتاريخِ سريانِه هو (وسريانُ الأبِ حدُّه الأعلى) */
$mk('v_supplier_share_units',
    "SELECT s.company_id, s.id AS supplier_container_id, s.container_no, s.supplier_id,
            ROUND(COALESCE(SUM(seat.monthly_basis), 0)
                  * (DATEDIFF(COALESCE(s.valid_to, p.valid_to), COALESCE(s.valid_from, p.valid_from)) / 30.0), 2)
              AS share_units,
            COALESCE(s.valid_from, p.valid_from) AS effective_from,
            COALESCE(s.valid_to, p.valid_to)     AS effective_to
     FROM op_containers s
     LEFT JOIN op_containers p    ON p.id = s.parent_id
     LEFT JOIN op_containers seat ON seat.parent_id = s.id AND seat.is_deleted = 0 AND seat.level = 'معدة'
     WHERE s.level = 'مورد' AND s.is_deleted = 0
     GROUP BY s.company_id, s.id, s.container_no, s.supplier_id,
              COALESCE(s.valid_from, p.valid_from), COALESCE(s.valid_to, p.valid_to)");

/* F-12: هامشُ الخانةِ الكلي — هامشُ المقعدِ × ساعاتُ العميلِ المحصَّلةُ لموردِه
   موزّعةً بنسبةِ أساسِ المقعدِ من أساسِ موردِه — والسالبُ يُوسَم للرفعِ لمالكِ العقد */
$mk('v_slot_total_margin',
    "SELECT seat.company_id, seat.id AS slot_id, seat.container_no, sup.supplier_id,
            seat.unit_margin,
            ROUND(COALESCE(st.settled, 0) * COALESCE(seat.monthly_basis, 0)
                  / NULLIF(basis.total_basis, 0), 2) AS slot_settled_hours,
            ROUND(COALESCE(seat.unit_margin, 0) * COALESCE(st.settled, 0)
                  * COALESCE(seat.monthly_basis, 0) / NULLIF(basis.total_basis, 0), 2) AS slot_total_margin,
            CASE WHEN COALESCE(seat.unit_margin, 0) < 0 THEN 'سالبٌ — يُرفع لمالكِ العقد' ELSE '' END AS margin_flag
     FROM op_containers seat
     JOIN op_containers sup ON sup.id = seat.parent_id AND sup.level = 'مورد' AND sup.is_deleted = 0
     LEFT JOIN (SELECT parent_id, SUM(monthly_basis) total_basis
                FROM op_containers WHERE is_deleted = 0 AND level = 'معدة' GROUP BY parent_id) basis
            ON basis.parent_id = sup.id
     LEFT JOIN (SELECT company_id, party_ref, SUM(client_settled_hours) settled
                FROM settlements WHERE is_deleted = 0 AND party_type = 'supplier'
                GROUP BY company_id, party_ref) st
            ON st.company_id = seat.company_id AND st.party_ref = CAST(sup.supplier_id AS CHAR)
     WHERE seat.level = 'معدة' AND seat.is_deleted = 0");

/* F-11: يُعاد بناءُ منظرِ الأداءِ بعمودِ الاستعدادِ المفوتر */
$mk('v_monthly_performance',
    "SELECT
        ue.company_id,
        DATE_FORMAT(ue.entry_date, '%Y-%m')                       AS period,
        ue.supplier_entity_id,
        ue.contract_id,
        ue.project_id,
        ue.equipment_id,
        COUNT(DISTINCT ue.id)                                     AS entries_count,
        COUNT(DISTINCT ue.entry_date)                             AS days_worked,
        ROUND(COALESCE(SUM(CASE WHEN l.ops_state='actual_work' THEN l.hours END),0),2) AS run_hours,
        ROUND(COALESCE(SUM(CASE WHEN l.ops_state='standby'     THEN l.hours END),0),2) AS standby_hours,
        ROUND(COALESCE(SUM(CASE WHEN l.ops_state IN ($stopList)  THEN l.hours END),0),2) AS breakdown_hours,
        ROUND(COALESCE(SUM(l.hours),0),2)                         AS total_hours,
        ROUND(COALESCE(SUM(CASE WHEN l.resp_party='client'   THEN l.hours END),0),2) AS client_liable_hours,
        ROUND(COALESCE(SUM(CASE WHEN l.resp_party='supplier' THEN l.hours END),0),2) AS supplier_liable_hours,
        ROUND(COALESCE(SUM(CASE WHEN l.resp_party='company'  THEN l.hours END),0),2) AS company_liable_hours,
        ROUND(COALESCE(SUM(CASE WHEN l.ops_state='standby'
                                  OR (l.resp_party='client' AND l.ops_state IN ($stopList))
                                THEN l.hours END),0),2)           AS billable_standby_hours,
        CASE WHEN COALESCE(SUM(l.hours),0) > 0
             THEN ROUND(100 * COALESCE(SUM(CASE WHEN l.ops_state='actual_work' THEN l.hours END),0)
                        / SUM(l.hours), 2)
             ELSE NULL END                                        AS availability_pct,
        ROUND(COALESCE(SUM(ue.fuel_issued_qty),0),2)              AS fuel_issued_qty,
        ROUND(COALESCE(SUM(ue.fuel_received_qty),0),2)            AS fuel_received_qty,
        ROUND(COALESCE(SUM(CASE WHEN ue.meter_after IS NOT NULL AND ue.meter_before IS NOT NULL
                                THEN ue.meter_after - ue.meter_before END),0),2) AS meter_delta,
        ROUND(COALESCE(SUM(CASE WHEN ue.unit_type='ton'   THEN ue.qty END),0),2) AS tons,
        ROUND(COALESCE(SUM(CASE WHEN ue.unit_type='meter' THEN ue.qty END),0),2) AS meters,
        ROUND(COALESCE(SUM(CASE WHEN ue.unit_type='trip'  THEN ue.qty END),0),2) AS trips,
        MAX(ue.updated_at)                                        AS last_entry_at
     FROM unit_entries ue
     LEFT JOIN unit_time_log l ON l.entry_id = ue.id
     WHERE ue.seed_tag IS NULL
       AND ue.state NOT IN ('rejected','cancelled','superseded','reversed')
     GROUP BY ue.company_id, DATE_FORMAT(ue.entry_date, '%Y-%m'),
              ue.supplier_entity_id, ue.contract_id, ue.project_id, ue.equipment_id");

echo "\n✔ تمّت\n";
