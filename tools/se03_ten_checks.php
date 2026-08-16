<?php
/**
 * se03_ten_checks.php — الفحوصُ العشرةُ (المواصفة 70 · TSP-0129..0138)
 * ═══════════════════════════════════════════════════════════════════════════
 * المتوقَّعُ صفرٌ في كلِّ فحص. وما لا يُشغَّل يُعلَن N/A **بسببِه** ولا يُعدُّ صفرًا:
 * «فحصٌ لا يعمل ويُقرأ أخضرَ» هو بعينُه العيبُ الذي مُنعنا منه.
 *
 * ◆ ستةٌ من العشرةِ تخصُّ جداولَ خارجَ جولةِ اليوم (cnt_* · sup_*) — والجولةُ
 *   `unit_entries` وشاشتُه وحدَهما بأمرِ المالك. فتُعلَن N/A بجدولِها المفقود.
 * ◆ وأربعةٌ تُشغَّل بعد **مواءمةٍ مُعلَنةٍ** لأسماءِ المخططِ الحيّ:
 *     shift_entries      ⇐ unit_entries        (لا يُنشأ جدولٌ لمفهومٍ له نظير)
 *     nav_items.path     ⇐ nav_items.route
 *     nav09_action_map.action_code ⇐ canonical_code
 *     activity_logs.action_code ⇐ لا وجودَ له — البديلُ سجلُّ الأفعالِ `actions`
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$db = @mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if (!$db) { fwrite(STDERR, "فشل الاتصال\n"); exit(2); }
$db->set_charset('utf8mb4');

$tableExists = function (string $t) use ($db): bool {
    $r = $db->query("SELECT 1 FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $db->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
};

$CHECKS = [
    ['CK-01', 'مجموعُ الأنواعِ = سعةُ السنوية', ['cnt_annual_containers', 'cnt_type_containers'], null],
    ['CK-02', 'صفرُ خانةٍ بلا كودِ معدة',        ['cnt_machine_slots'], null],
    ['CK-03', 'مجموعُ حصصِ الموردينَ ≤ سعةِ الحاوية', ['cnt_machine_slots', 'sup_slot_allocations', 'cnt_annual_containers'], null],
    ['CK-04', 'صفرُ ساعةٍ بلا خانةٍ ومعدةٍ ومشغّل', ['unit_entries'],
        "SELECT COUNT(*) FROM unit_entries
          WHERE entry_date >= '2026-08-16'
            AND (equipment_id IS NULL OR equipment_id = 0
                 OR operator_employee_id IS NULL OR operator_employee_id = 0)"],
    ['CK-05', 'كلُّ خروجٍ له دخولٌ مقابل',       ['sup_handover_events'], null],
    ['CK-06', 'صفرُ تسويةٍ بلا مستند',            ['sup_settlements'], null],
    ['CK-07', 'صفرُ فعلٍ غيرِ مسجَّلٍ في القاموس', ['actions', 'nav09_action_map'],
        "SELECT COUNT(*) FROM (
            SELECT a.action_code FROM actions a
             WHERE a.active = 1
               AND a.action_code LIKE 'shift.%'
               AND NOT EXISTS (SELECT 1 FROM nav09_action_map m WHERE m.canonical_code = a.action_code)
            UNION ALL
            SELECT m.canonical_code FROM nav09_action_map m
             WHERE m.canonical_code LIKE 'shift.entry.%'
               AND (m.live_code IS NULL OR m.live_code = '')
         ) d"],
    ['CK-08', 'صفرُ شاشةٍ بلا وحدةِ صلاحيات',    ['nav_items'],
        "SELECT COUNT(*) FROM nav_items
          WHERE active = 1 AND module_id IS NULL
            AND permission_code IS NOT NULL AND permission_code <> ''"],
    ['CK-09', 'صفرُ جدولٍ جديدٍ بلا عمودِ عزل',  ['unit_entries'],
        "SELECT COUNT(*) FROM information_schema.TABLES t
          WHERE t.TABLE_SCHEMA = DATABASE()
            AND t.TABLE_NAME IN ('unit_entries','unit_time_log')
            AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS c
                            WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA
                              AND c.TABLE_NAME = t.TABLE_NAME
                              AND c.COLUMN_NAME = 'company_id')"],
    ['CK-10', 'صفرُ صفٍّ مبذورٍ بلا وسم',        ['unit_entries'],
        "SELECT COUNT(*) FROM unit_entries
          WHERE created_at >= '2026-08-16'
            AND seed_tag IS NULL
            AND (note LIKE '%اختبار%' OR source_ref LIKE '%TEST%' OR source_ref LIKE '%test%')"],
];

$NA_WHY = [
    'CK-01' => 'حاوياتُ السنويةِ والأنواعِ خارجَ جولةِ اليوم (الجولةُ: القيدُ اليوميُّ وحدَه)',
    'CK-02' => 'خاناتُ الآلياتِ خارجَ جولةِ اليوم',
    'CK-03' => 'الخاناتُ وإسنادُها خارجَ جولةِ اليوم',
    'CK-05' => 'أحداثُ تسليمِ الحصصِ خارجَ جولةِ اليوم',
    'CK-06' => 'تسوياتُ الموردينَ خارجَ جولةِ اليوم',
];

echo "══════════════════════════════════════════════════════════════════════\n";
echo "  الفحوصُ العشرة — المتوقَّعُ صفرٌ في كلِّ فحصٍ يُشغَّل\n";
echo "══════════════════════════════════════════════════════════════════════\n";

$run = 0; $pass = 0; $fail = 0; $na = 0;
foreach ($CHECKS as [$id, $title, $needs, $sql]) {
    $missing = [];
    foreach ($needs as $t) { if (!$tableExists($t)) { $missing[] = $t; } }

    if ($sql === null || $missing) {
        $na++;
        $why = $NA_WHY[$id] ?? ('جدولٌ مفقود: ' . implode(' · ', $missing));
        printf("\n%-6s %-42s  N/A\n", $id, $title);
        printf("        السبب: %s\n", $why);
        if ($missing) { printf("        الجداولُ غيرُ الموجودة: %s\n", implode(' · ', $missing)); }
        printf("        ◆ لا يُحسب صفرًا ولا يدخل أيَّ مقام.\n");
        continue;
    }

    $r = $db->query($sql);
    $run++;
    if (!$r) {
        $fail++;
        printf("\n%-6s %-42s  ✘ خطأُ استعلام\n        %s\n", $id, $title, $db->error);
        continue;
    }
    $n = (int) $r->fetch_row()[0];
    if ($n === 0) { $pass++; printf("\n%-6s %-42s  ✔ صفر\n", $id, $title); }
    else          { $fail++; printf("\n%-6s %-42s  ✘ %d صفًّا مخالفًا\n", $id, $title, $n); }
    printf("        الاستعلام: %s\n", preg_replace('/\s+/', ' ', trim($sql)));
}

echo "\n══════════════════════════════════════════════════════════════════════\n";
printf("  شُغِّل %d · اجتاز %d · أخفق %d · غيرُ منطبقٍ %d (من 10)\n", $run, $pass, $fail, $na);
echo "══════════════════════════════════════════════════════════════════════\n";
exit($fail === 0 ? 0 : 1);
