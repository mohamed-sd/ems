<?php
/**
 * 2027_08_12_group_nine_limit_split.php — حدُّ التسعةِ يُنفَّذ: أربعُ مجموعاتٍ تُقسَّم بمعنًى
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الحكمُ المُنفَّذ** — INJAZ-UXUI-01 ف٧-٢: «المجموعةُ الواحدةُ لا تتجاوز
 *   تسعةَ عناصر — وما زاد **يُقسَّم بمعنًى لا بالعدد**»، وما يُرسِّب البناءَ
 *   «مجموعةٌ بعشرةِ عناصرَ فأكثر». وهو حكمٌ ملزمٌ **لم تكن له بوابةٌ قطُّ**:
 *   بواباتُ U1..U8 تفحص الاسمَ والموضعَ والمصطلحَ ولا تفحص **حجمَ المجموعة**.
 *   فأُضيفت U9 في `tools/uxui_gates.php` فأخرجت **أربعَ مخالفاتٍ مُصيَّرة**.
 *
 * ◆ **ولماذا الهجرةُ لا التعديلُ المباشر** — `group_name` في `nav_canonical`
 *   هو مصدرُ التصيير: `printUxuiCanonicalNav()` تقرأ المجموعةَ منه لكلِّ مسار.
 *   وتغييرُه تغييرُ موضعٍ معياريٍّ — فيلزمه أثرٌ يُقرأ ويُعكس.
 *
 * ◆ **والمرساةُ تتبع أصلَها بلا تدخُّل**: المولِّد يردُّ `route#n` إلى مسارِه
 *   الأساسِ بـ`uxuiNavBaseRoute()` ثم يأخذ مجموعةَ الأصل. فنقلُ الأصلِ ينقل
 *   مرساتَه معه — ولذلك يُحسب العددُ **بالمُصيَّرِ لا بصفوفِ السجل**:
 *   «تسجيل الموردين» تسعةُ صفوفٍ في السجلِّ **وأحدَ عشرَ بندًا في الشاشة**.
 *
 * ◆ **وما لا يُمسّ**: `canonical_ar` و`route` و`level_no` و`sort_no` و`decision_state`.
 *   فلا اسمَ يتغيّر ولا رابطَ يُفقد ولا صفَّ يُرقَّى — **موضعُ المجموعةِ وحدَه**.
 *   وترتيبُ الدورةِ محفوظٌ لأن `sort_no` باقٍ (ف٧-٨).
 *
 * ◆ **والأثرُ في `placement_basis`** — لا في `decision_source`: الأخيرُ يحمل
 *   صفَّ المصفوفةِ الذي اشتُقَّ منه الصفُّ أصلًا، ومحوُه يمحو نسبَه.
 *
 * التشغيل:  php database/migrations/2027_08_12_group_nine_limit_split.php [--revert]
 * ═══════════════════════════════════════════════════════════════════════════
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
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$REVERT = in_array('--revert', $argv, true);

$BASIS = 'INJAZ-UXUI-01 ف٧-٢ — حدُّ التسعةِ للمجموعةِ الواحدة · قُسِّمت بمعنى دورةِ المستند (بوابة U9)';

/* المسارُ ⇒ (المجموعةُ الأصلُ، المجموعةُ الجديدة) — والقسمةُ بدورةِ المستندِ لا بالعدد */
$MOVES = array(
    /* ① «تسجيل الموردين» = 11 مُصيَّرًا ⇐ 5 + 6 */
    'Suppliers/suppliers.php'               => array('تسجيل الموردين', 'ملف المورد وتأهيله'),
    'Suppliers/supplier_bank.php'           => array('تسجيل الموردين', 'ملف المورد وتأهيله'),
    'Suppliers/supplier_documents.php'      => array('تسجيل الموردين', 'ملف المورد وتأهيله'),
    'Suppliers/equipment_plan.php'          => array('تسجيل الموردين', 'ملف المورد وتأهيله'),      /* ومرساتُه #2 معه */
    'Suppliers/rfq_requests.php'            => array('تسجيل الموردين', 'تعاقد الموردين وتسويتهم'),
    'Suppliers/supplierscontracts.php'      => array('تسجيل الموردين', 'تعاقد الموردين وتسويتهم'),
    'Suppliers/supplier_contract_lines.php' => array('تسجيل الموردين', 'تعاقد الموردين وتسويتهم'),
    'Suppliers/shares_coverage.php'         => array('تسجيل الموردين', 'تعاقد الموردين وتسويتهم'), /* ومرساتُه #2 معه */
    'Suppliers/settlements.php'             => array('تسجيل الموردين', 'تعاقد الموردين وتسويتهم'),

    /* ② «العقود» = 10 ⇐ 4 + 6 */
    'Contracts/contracts.php'                 => array('العقود', 'العقود وأحكامها'),
    'Contracts/contract_card.php'             => array('العقود', 'العقود وأحكامها'),
    'Contracts/contract_baseline.php'         => array('العقود', 'العقود وأحكامها'),
    'Contracts/contract_sites.php'            => array('العقود', 'العقود وأحكامها'),
    'Clients/contract_commitments.php'        => array('العقود', 'خطط العقد والتزاماته'),
    'Contracts/contract_obligations.php'      => array('العقود', 'خطط العقد والتزاماته'),
    'Contracts/contract_payment_schedule.php' => array('العقود', 'خطط العقد والتزاماته'),
    'Contracts/contract_resource_plan.php'    => array('العقود', 'خطط العقد والتزاماته'),
    'Contracts/penalties.php'                 => array('العقود', 'خطط العقد والتزاماته'),
    'Contracts/contract_lifecycle.php'        => array('العقود', 'خطط العقد والتزاماته'),

    /* ③ «المعاملات المالية الواردة» = 11 مُصيَّرًا (12 صفًّا) ⇐ 7 + 5 */
    'Finance/ob_register.php'          => array('المعاملات المالية الواردة', 'الالتزامات والاستحقاق'),
    'Finance/ob_schedule.php'          => array('المعاملات المالية الواردة', 'الالتزامات والاستحقاق'),
    'Finance/ob_commitments.php'       => array('المعاملات المالية الواردة', 'الالتزامات والاستحقاق'),
    'Finance/ob_due_soon.php'          => array('المعاملات المالية الواردة', 'الالتزامات والاستحقاق'),
    'Finance/ob_overdue.php'           => array('المعاملات المالية الواردة', 'الالتزامات والاستحقاق'),
    'Finance/ob_horizon.php'           => array('المعاملات المالية الواردة', 'الالتزامات والاستحقاق'),
    'Finance/ob_contingent.php'        => array('المعاملات المالية الواردة', 'الالتزامات والاستحقاق'),
    'FinRequests/finance_gateway.php'  => array('المعاملات المالية الواردة', 'الطلبات المالية وموافقاتها'),
    'FinRequests/dept_inbox.php'       => array('المعاملات المالية الواردة', 'الطلبات المالية وموافقاتها'),
    'FinRequests/requests_reports.php' => array('المعاملات المالية الواردة', 'الطلبات المالية وموافقاتها'),
    'Finance/events_list_fin.php'      => array('المعاملات المالية الواردة', 'الطلبات المالية وموافقاتها'),
    'Finance/acc_backflow.php'         => array('المعاملات المالية الواردة', 'الطلبات المالية وموافقاتها'),

    /* ④ «القوائم المالية» = 10 مُصيَّرًا (11 صفًّا) ⇐ 4 + 4 + 3 */
    'Finance/fin_cashflow_stmt.php'       => array('القوائم المالية', 'القوائم والترحيل المحاسبي'),
    'Finance/fin_equity_stmt.php'         => array('القوائم المالية', 'القوائم والترحيل المحاسبي'),
    'Finance/fin_posting_matrix.php'      => array('القوائم المالية', 'القوائم والترحيل المحاسبي'),
    'Finance/executive_dashboard_fin.php' => array('القوائم المالية', 'القوائم والترحيل المحاسبي'),
    'Finance/fin_ratios.php'              => array('القوائم المالية', 'النسب والإنذار المبكر'),
    'Finance/fin_ratio_detail.php'        => array('القوائم المالية', 'النسب والإنذار المبكر'),
    'Finance/fin_ratio_targets.php'       => array('القوائم المالية', 'النسب والإنذار المبكر'),
    'Finance/fin_early_warning.php'       => array('القوائم المالية', 'النسب والإنذار المبكر'),
    'Finance/fin_unit_economics.php'      => array('القوائم المالية', 'اقتصاديات الوحدة والعقد'),
    'Finance/fin_contract_margin.php'     => array('القوائم المالية', 'اقتصاديات الوحدة والعقد'),
    'Finance/fin_project_pl.php'          => array('القوائم المالية', 'اقتصاديات الوحدة والعقد'),
);

/* ── حارسُ ما قبلَ الكتابة: لا يُنقل صفٌّ ليس في مجموعتِه المتوقَّعة ──
   فمن كتبَ على حالةٍ لا يعرفها كتبَ على تغييرٍ سبقَه. */
$now = array();
$res = $conn->query("SELECT route, group_name FROM nav_canonical");
while ($res && ($x = $res->fetch_assoc())) { $now[$x['route']] = $x['group_name']; }

$missing = array(); $mismatch = array();
foreach ($MOVES as $route => $gg) {
    list($from, $to) = $gg;
    $expect = $REVERT ? $to : $from;
    if (!isset($now[$route])) { $missing[] = $route; continue; }
    if ($now[$route] !== $expect && $now[$route] !== ($REVERT ? $from : $to)) { $mismatch[] = "{$route}: «{$now[$route]}» ≠ «{$expect}»"; }
}
if ($missing) { echo "✗ مساراتٌ لا صفَّ لها في `nav_canonical` — أُوقفت الهجرة:\n"; foreach ($missing as $m) { echo "   · {$m}\n"; } exit(2); }
if ($mismatch) { echo "✗ مجموعةٌ حاليةٌ غيرُ متوقَّعة — أُوقفت الهجرة:\n"; foreach ($mismatch as $m) { echo "   · {$m}\n"; } exit(2); }

/* ── التنفيذ ── */
$st = $conn->prepare("UPDATE nav_canonical SET group_name = ?, placement_basis = ? WHERE route = ?");
if (!$st) { exit("تعذَّر التحضير: {$conn->error}\n"); }
$moved = 0; $already = 0;
foreach ($MOVES as $route => $gg) {
    list($from, $to) = $gg;
    $target = $REVERT ? $from : $to;
    $basis  = $REVERT ? null : $BASIS;
    if ($now[$route] === $target) { $already++; continue; }
    $st->bind_param('sss', $target, $basis, $route);
    if (!$st->execute()) { echo "✗ {$route}: {$st->error}\n"; continue; }
    if ($st->affected_rows > 0) { $moved++; }
}
$st->close();

/* ── الإثباتُ بعدَ الكتابة: أحجامُ المجموعاتِ في السجل ── */
echo ($REVERT ? "◆ عكسُ القسمة\n" : "◆ قسمةُ حدِّ التسعة\n");
echo "  نُقل: {$moved} · كان في موضعِه سلفًا: {$already} · المقام: " . count($MOVES) . "\n\n";
$q = $conn->query("SELECT group_name, COUNT(*) n FROM nav_canonical GROUP BY group_name HAVING n > 9 ORDER BY n DESC");
$over = 0;
while ($q && ($x = $q->fetch_assoc())) { echo "  ⚠ «{$x['group_name']}» = {$x['n']} صفًّا في السجل\n"; $over++; }
echo $over === 0 ? "  ✔ صفرُ مجموعةٍ فوقَ التسعةِ في السجل\n" : "  ◆ {$over} مجموعة/مجموعاتٍ فوقَ التسعةِ في السجل — والحكمُ النافذُ على المُصيَّر (U9)\n";
echo "\n  ▸ الفحصُ المُلزِم: php tools/uxui_gates.php\n";
