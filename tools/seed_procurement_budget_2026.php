<?php
/**
 * tools/seed_procurement_budget_2026.php — ميزانيةُ المشتريات 2026 (بذرة عرض)
 * ═══════════════════════════════════════════════════════════════════════════
 * «ميزانيةُ إدارتي» كانت فارغةً للدور 16: صفوفُ procurement القائمةُ بسنواتٍ
 * فاسدةٍ (fiscal_year=4 و20 — بذرٌ ملوث). هذه بذرةُ عرضٍ سليمةُ البنية لسنة
 * 2026 (رأسٌ معتمدٌ + 3 بنودِ مصروفٍ) — **إدراجٌ فقط، لا مساسَ بالقائم**،
 * وidempotent بمفتاح budget_no.
 *
 * php tools/seed_procurement_budget_2026.php --apply   (بلا وسيطة: عرض)
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/env.php';

$APPLY = in_array('--apply', $argv, true);
$conn = new mysqli(ems_env('DB_HOST', 'localhost'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), (int) ems_env('DB_PORT', 3306));
$conn->set_charset('utf8mb4');
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

$NO = 'BUD-2026-PROC';
$r = $conn->query("SELECT id FROM fin_budgets WHERE company_id = 4 AND budget_no = '$NO' AND is_deleted = 0");
if ($r && $r->num_rows) { $o("البذرة قائمة سلفًا (#" . $r->fetch_assoc()['id'] . ") ✓"); exit(0); }
$o("سيُدرج: $NO — procurement · 2026 · annual · approved + 3 بنود مصروف");
if (!$APPLY) { $o('— dry-run: أضف --apply'); exit(0); }

$conn->begin_transaction();
try {
    $conn->query("INSERT INTO fin_budgets (company_id, budget_no, dept_module, period_type, fiscal_year, period_no,
                                           total_revenue, total_expense, state, note, created_by, created_at)
                  VALUES (4, '$NO', 'procurement', 'annual', 2026, 1, 0, 1120000, 'approved',
                          'بذرة عرض — جولة حل مشاكل الدور 2026-08-06', 71, NOW())");
    $bid = intval($conn->insert_id);
    $lines = array(
        array('procurement', 800000, 'قطع الغيار والمواد التشغيلية'),
        array('maintenance', 220000, 'قطع أوامر الصيانة'),
        array('other',       100000, 'نثريات وشحن وتخليص'),
    );
    foreach ($lines as $l) {
        $conn->query("INSERT INTO fin_budget_lines (company_id, budget_id, line_kind, category,
                                                    planned_amount, actual_amount, var_state, note, created_at)
                      VALUES (4, $bid, 'expense', '{$l[0]}', {$l[1]}, 0, 'open',
                              '" . $conn->real_escape_string($l[2]) . "', NOW())");
    }
    $conn->commit();
    $o("✔ أُدرجت الميزانية #$bid ببنودها الثلاثة");
} catch (\Throwable $t) {
    $conn->rollback();
    $o('✘ ROLLED BACK: ' . $t->getMessage());
    exit(1);
}
