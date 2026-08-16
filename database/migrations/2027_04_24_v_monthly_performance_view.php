<?php
/**
 * 2027_04_24_v_monthly_performance_view.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تقريرُ الأداءِ **منظرٌ محسوبٌ** من القيدِ اليوميِّ — لا جدولٌ يُكتب فيه
 *
 * حكمُ المواصفة 70 · TSP-0012: «ولا يُبنى تقريرُ أداءٍ قبلَ القيدِ اليومي:
 * كلُّ رقمِ أداءٍ محسوبٌ من `shift_entries` وصفرُ عمودٍ مُدخَلٍ يدويًّا ·
 * تقريرُ الأداءِ **منظرٌ لا جدولٌ** · فمن بنى التقريرَ أولًا بنى مستودعًا
 * لأرقامٍ مقدَّرة».
 *
 * ── لماذا منظرٌ ولا يُوصَل الجدولُ القائم ─────────────────────────────
 * `MonthlyPerformanceService` (207 أسطر · بلا نداءٍ في الشجرة) يُجمّع فوق
 * `container_consumption` — والقياسُ الحيّ: **صفرُ صفٍّ فيه**. فوصلُه يُجمّع
 * عدمًا. وجدولُ `monthly_performance` فيه 20 صفًّا **كلُّها قيمٌ نائبةٌ
 * متطابقة** (ساعاتُ العقد = المنفَّذ = الاستعداد = المتاح = 4.50) — مستودعُ
 * أرقامٍ مقدَّرةٍ بعينِه، وهو ما حذّرت منه المواصفة.
 *
 * والمصدرُ الحيُّ قائم: `unit_time_log` فيه 12,500 سطرَ ساعاتٍ على ستةِ أشهر،
 * مطبَّعةً بـ`ops_state` (عشرُ حالات) و`resp_party` — أغنى مما يطلبه التقرير.
 *
 * ── وما لا يُحسب هنا ──────────────────────────────────────────────────
 * · ساعاتُ العقدِ التعاقديةُ ليست في القيدِ اليومي — فلا عمودَ «نقص» ولا
 *   «نسبةَ إنجاز» في هذا المنظر. من أرادهما يضمُّه إلى مصدرِ العقدِ صراحةً،
 *   ولا يُخمَّن رقمٌ تعاقديٌّ من سجلٍّ تشغيليّ.
 * · والصفوفُ الموسومةُ بذرًا مستبعَدةٌ — فالتقريرُ عن عملٍ وقع لا عن بذور.
 *
 * ◆ ولا DEFINER: يُنشأ بـSQL SECURITY INVOKER فينفَّذ بصلاحيةِ قارئِه لا
 *   بصلاحيةِ منشئِه — شرطُ النقلِ إلى الاستضافةِ ومنعُ تصعيدِ الصلاحية.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ منظرُ الأداءِ الشهريِّ من القيدِ اليومي ══\n\n";

/* الحالاتُ تُقرأ من التعدادِ الحيِّ لا تُكتب حدسًا */
$r = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unit_time_log' AND COLUMN_NAME='ops_state'");
$enum = $r ? (string) $r->fetch_row()[0] : '';
if (!preg_match_all("/'([^']+)'/", $enum, $m)) { exit("  ✘ تعذّر قراءةُ تعدادِ ops_state\n"); }
$states = $m[1];
$stops = array_values(array_diff($states, array('actual_work', 'standby', 'unlogged')));
echo '  حالاتُ التوقفِ المقروءةُ من التعداد (' . count($stops) . '): ' . implode(' · ', $stops) . "\n";
$stopList = "'" . implode("','", array_map(array($conn, 'real_escape_string'), $stops)) . "'";

$sql = "CREATE OR REPLACE
        SQL SECURITY INVOKER
        VIEW `v_monthly_performance` AS
        SELECT
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
                 ue.supplier_entity_id, ue.contract_id, ue.project_id, ue.equipment_id";

if (!$conn->query($sql)) { exit("  ✘ تعذّر إنشاءُ المنظر: {$conn->error}\n"); }
echo "  ✔ v_monthly_performance أُنشئ (SQL SECURITY INVOKER · بلا DEFINER)\n";

/* ── إثباتٌ فوريّ: أينتج المنظرُ رقمًا حقيقيًّا؟ ─────────────────── */
$r = $conn->query("SELECT COUNT(*) FROM v_monthly_performance");
$rows = $r ? (int) $r->fetch_row()[0] : -1;
echo "  صفوفُ المنظر: $rows\n";

$r = $conn->query("SELECT period, COUNT(*) خطوط, ROUND(SUM(run_hours),1) تشغيل,
                          ROUND(SUM(standby_hours),1) استعداد, ROUND(SUM(breakdown_hours),1) تعطل,
                          ROUND(AVG(availability_pct),1) جاهزية
                   FROM v_monthly_performance GROUP BY period ORDER BY period DESC LIMIT 6");
if ($r) {
    printf("\n  %-9s %7s %10s %10s %10s %9s\n", 'الشهر', 'خطوط', 'تشغيل', 'استعداد', 'تعطل', 'جاهزية٪');
    while ($x = $r->fetch_row()) {
        printf("  %-9s %7s %10s %10s %10s %9s\n", $x[0], $x[1], $x[2], $x[3], $x[4], $x[5] ?? '—');
    }
}

/* اتساقٌ: مجموعُ ساعاتِ المنظرِ = مجموعُ السجلِّ الحيِّ نفسِه */
$a = $conn->query("SELECT ROUND(COALESCE(SUM(total_hours),0),2) FROM v_monthly_performance");
$b = $conn->query("SELECT ROUND(COALESCE(SUM(l.hours),0),2) FROM unit_time_log l
                   JOIN unit_entries ue ON ue.id=l.entry_id
                   WHERE ue.seed_tag IS NULL AND ue.state NOT IN ('rejected','cancelled','superseded','reversed')");
$va = $a ? (float) $a->fetch_row()[0] : -1;
$vb = $b ? (float) $b->fetch_row()[0] : -2;
printf("\n  اتساقُ الساعات: المنظر %s · السجلُّ %s — %s\n",
    number_format($va, 2), number_format($vb, 2), abs($va - $vb) < 0.01 ? '✔ متطابق' : '✘ متفارق');

echo "\n◆ وجدولُ `monthly_performance` القائمُ لم يُمسّ: 20 صفَّ بذرٍ بقيمٍ نائبةٍ\n";
echo "  متطابقة. حذفُه أو ردمُه قرارُ مالكٍ — والمنظرُ لا يعتمد عليه البتة.\n";
echo "\n✔ تمّت\n";
