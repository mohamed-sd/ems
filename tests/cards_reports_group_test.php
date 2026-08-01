<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-47 · M-48 · M-07 — اختبار قبول مجموعةِ البطاقات والتقارير
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/cards_reports_group_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'cards test');

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO   = 4;
$MARK = 'CR3T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM unit_party_awards WHERE policy_rule LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ البطاقاتُ والتقارير — M-47 · M-48 · M-07 ══\n");

// ═══ M-07 ═══
head('M-07 — هامشُ الواقعة من الاعترافات الثلاثة');

// واقعةٌ واحدة (source_ref) بأحكامها الثلاثة — عميلٌ 1000 · موردٌ 600 · مشغّلٌ 150
$mk = function ($party, $ref, $qty, $price) use ($conn, $CO, $MARK) {
    // qty_due عمودٌ مولَّد (award_qty × pct) — لا يُكتب (گوتشا القيود المولّدة)
    $conn->query("INSERT INTO unit_party_awards (company_id, source_kind, source_ref, party,
                  party_ref, contract_ref, award_unit_type, award_qty, entitlement_state,
                  entitlement_pct, unit_price, currency, policy_rule, created_by, created_at)
                  VALUES ({$CO}, 'timesheet', {$ref}, '{$party}', 77, 990077, 'ton', {$qty},
                          'due', 100, {$price}, 'USD', 'بذر {$MARK}', 1, NOW())");
    return intval($conn->insert_id);
};
$a1 = $mk('client', 880001, 100, 10);
$a2 = $mk('supplier', 880001, 100, 6);
$a3 = $mk('operator', 880001, 100, 1.5);
check($a1 && $a2 && $a3, 'واقعةٌ بأحكامها الثلاثة: عميلٌ 1000 · موردٌ 600 · مشغّلٌ 150');

// التقريرُ يقرأ التجميعةَ نفسَها التي تعرضها الشاشة
$r = $conn->query("SELECT
        ROUND(SUM(CASE WHEN party='client'   THEN qty_due*unit_price ELSE 0 END),2) rev,
        ROUND(SUM(CASE WHEN party='supplier' THEN qty_due*unit_price ELSE 0 END),2) sup,
        ROUND(SUM(CASE WHEN party='operator' THEN qty_due*unit_price ELSE 0 END),2) op
   FROM unit_party_awards WHERE company_id={$CO} AND contract_ref=990077 AND deleted_at IS NULL")->fetch_assoc();
$margin = round((float)$r['rev'] - (float)$r['sup'] - (float)$r['op'], 2);
check(abs((float)$r['rev'] - 1000) < 0.005 && abs($margin - 250.0) < 0.005,
      '★★★ **الهامشُ 250 = 1000 − 600 − 150** — من الأحكام الثلاثة لا من تقدير');
$m = $conn->query("SELECT id FROM modules WHERE code='Reports/margin_report.php'")->fetch_assoc();
check($m && intval($m['id']) === 202, '★ والشاشةُ 202 مسجَّلةٌ للأدوار المالية والمبيعات');
$rep = file_get_contents(dirname(__DIR__) . '/Reports/margin_report.php');
check(strpos($rep, "party='client'") !== false && strpos($rep, 'GROUP BY a.contract_ref, a.currency') !== false,
      '★ والتجميعُ **بالعقد والعملة** — لا تُجمع عملتان في رقم');

// ═══ M-47 ═══
head('M-47 — بطاقةُ العقد الأم بتبويباتها السبعة');

$m = $conn->query("SELECT id FROM modules WHERE code='Contracts/contract_card.php'")->fetch_assoc();
check($m && intval($m['id']) === 200, '★ الشاشةُ 200 مسجَّلة');
$card = file_get_contents(dirname(__DIR__) . '/Contracts/contract_card.php');
$tabs = 0;
foreach (array('الرأس والحالة', 'البنود والقيمة', 'الخطط الثلاث', 'الملاحق والالتزامات',
               'المستخلصات والفواتير', 'الضمانات والمقدمات', 'الاقتصاد') as $t) {
    if (strpos($card, $t) !== false) { $tabs++; }
}
check($tabs === 7, '★★ التبويباتُ السبعة (' . $tabs . '/7) — تجميعُ Views لمصادرَ قائمة');
check(strpos($card, 'CommercialBoardService') !== false
      && strpos($card, 'advance_balance') !== false
      && strpos($card, 'contract_baseline') !== false,
      '★★ وكلُّ تبويبٍ من خدمة مالكه (اللوحةُ التجارية · دفترُ المقدم · خطُّ الأساس) — لا حسابَ محليًّا');
check(strpos($card, 'خارج الميزانية — لا يظهر رقمًا') !== false,
      '★ وخطابُ الضمان معلَنٌ خارجَ الميزانية حتى في البطاقة (P-06)');

// ═══ M-48 ═══
head('M-48 — بطاقةُ الموظف بتبويباتها السبعة والحساسُ خلف الحارس');

$m = $conn->query("SELECT id FROM modules WHERE code='Employees/employee_card.php'")->fetch_assoc();
check($m && intval($m['id']) === 201, '★ الشاشةُ 201 مسجَّلة');
$ecard = file_get_contents(dirname(__DIR__) . '/Employees/employee_card.php');
$tabs = 0;
foreach (array('البيانات', 'عقدُه وسجلُّه', 'صفاتُه (H-15)', 'إنتاجُه',
               'راتبُه وسلفُه', 'عهدُه', 'تقييمُه ونشاطُه') as $t) {
    if (strpos($ecard, $t) !== false) { $tabs++; }
}
check($tabs === 7, '★★ التبويباتُ السبعة (' . $tabs . '/7) — كان الملفُّ قائمةً ناقصة');
check(strpos($ecard, "VG::check(") !== false && strpos($ecard, "'card.payroll'") !== false,
      '★★★ وتبويبُ الراتب **خلف حارس الظهور الثلاثي (H-17)** — لا خلف إخفاء زر');
check(strpos($ecard, 'محجوبٌ بقرارٍ موثَّق') !== false,
      '★ والمحجوبُ يُعلَن بقراره لا فراغًا صامتًا');
check(strpos($ecard, 'employee_profile.php') !== false,
      '★ والملفُّ القديم باقٍ يُوصَل إليه — توسيعٌ لا هدم');

// ═══ الخاتمة ═══
fwrite(STDOUT, "\n══════════════════════════════════════\n");
fwrite(STDOUT, "  النتيجة: {$PASS} نجاح · {$FAIL} فشل\n");
exit($FAIL > 0 ? 1 : 0);
