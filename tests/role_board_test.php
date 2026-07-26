<?php
/**
 * حارس لوحة الدور — UX-00 §7 · UX-01 §5
 * يحرس: قاعدة «كل رقمٍ ينقر إلى مصدره» (لا مؤشرَ بلا href) · تعريفاتِ
 * التنبيهات المنقولةَ نصًّا من UX-01 §8 · علمَ التشغيل المزدوج وعزلَه ·
 * وسلامةَ ربط الرئيسية (التحويلُ للمفعَّل وحده).
 * التشغيل: php tests/role_board_test.php — رمز الخروج 0/1.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/includes/role_board.php';

$PASS = 0; $FAIL = 0;
function ok($label, $cond) {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "  ✔ {$label}\n"; }
    else { $FAIL++; echo "  ✘ FAIL: {$label}\n"; }
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_APP_USER'), ems_env('DB_APP_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$conn->set_charset('utf8mb4');

echo "── ① العلم والتوجيه (التشغيل المزدوج) ──\n";

ok('العلم: المذكور يفعَّل وغيره لا (حتميًّا بتجاوز البيئة)',
    roleBoardEnabled(17, '17') === true && roleBoardEnabled(1, '17') === false
    && roleBoardEnabled(17, '') === false && roleBoardEnabled(13, '17,13') === true);

ok('خريطة اللوحات الأربع (17·13·16·23) · دورٌ بلا لوحةٍ → null',
    roleBoardRoute(17) === 'Finance/cfo_daily_board_fin.php'
    && roleBoardRoute(13) === 'Maintenance/dashboard_mnt.php'
    && roleBoardRoute(16) === 'Procurement/dashboard_proc.php'
    && roleBoardRoute(23) === 'Transport/transfer_dashboard.php' && roleBoardRoute(1) === null);

ok('الوراثة: الدور الفرعي يرث لوحةَ أبيه (18→17 · 14→13)',
    roleBoardRoute(18, 17) === 'Finance/cfo_daily_board_fin.php'
    && roleBoardRoute(14, 13) === 'Maintenance/dashboard_mnt.php' && roleBoardRoute(7, 1) === null);

echo "── ② قاعدة «كل رقمٍ ينقر إلى مصدره» (UX-00 §7) ──\n";

// كل تعريف تنبيهٍ يحمل href وسببًا (label) — بنية المواصفة لا تسمح بغيرها
$specs = roleBoardAlertSpecs(17);
$bad = 0;
foreach ($specs as $s) {
    if (empty($s['href']) || empty($s['label']) || empty($s['key'])) { $bad++; }
}
ok('تعريفات تنبيهات المدير المالي الأربعة كاملةٌ (سبب + قفزة) — نصُّ UX-01 §8.11', count($specs) === 4 && $bad === 0);

// الصيانة: ثلاثةٌ من أربعة — «قطعةٌ منتظرة» بلا مصدرٍ (لا wait_parts · DEC-10)
// فلا تُعرض حتى يُبنى مصدرُها: قاعدةُ عدم التلفيق محروسةٌ بالعدد نفسه.
$mspecs = roleBoardAlertSpecs(13);
$mbad = 0;
foreach ($mspecs as $s) { if (empty($s['href']) || empty($s['label'])) { $mbad++; } }
ok('تنبيهات الصيانة ثلاثةٌ كاملة — والرابع (قطعة منتظرة) غائبٌ عمدًا بلا مصدر',
    count($mspecs) === 3 && $mbad === 0
    && count(array_filter($mspecs, function ($s) { return $s['key'] === 'wait_parts'; })) === 0);

// §8.10 المشتريات أربعةٌ نصًّا · §8.12 النقل ثلاثةٌ نصًّا — كلٌّ بسببٍ وقفزة
$allOk = true;
foreach (array(16 => 4, 23 => 3) as $r => $expected) {
    $sp = roleBoardAlertSpecs($r);
    if (count($sp) !== $expected) { $allOk = false; }
    foreach ($sp as $s) { if (empty($s['href']) || empty($s['label'])) { $allOk = false; } }
}
ok('تنبيهات المشتريات (4) والنقل (3) بنصوص §8.10 و§8.12 — كاملةَ السبب والقفزة', $allOk);

// التنبيهات الحية: ما عدده صفر يختفي («المُنجَز يختفي فورًا» §9)
// بوابةٌ نظاميةٌ صريحة بشركة 4 (نمط tenant_leak_test — لا جلسةَ في CLI)
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
$gate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem(4, 999917, '17'), false, 'enforce');
$alerts = roleBoardAlerts($conn, $gate, 17);
$zeroShown = 0;
foreach ($alerts as $a) { if (intval($a['count']) <= 0) { $zeroShown++; } }
ok('لا تنبيهَ بعددٍ صفر (المُنجَز يختفي) ولا تنبيهَ بلا قفزة', $zeroShown === 0
    && count(array_filter($alerts, function ($a) { return empty($a['href']); })) === 0);

echo "── ③ المكوّنات لا ترمي أبدًا (اللوحة لا تتعطل لعطب مؤشر) ──\n";

$threw = false;
try {
    roleBoardTasks($conn, $gate, 17);
    roleBoardApprovals($conn, $gate, 17, array());
    roleBoardRecent($conn, 999999);
    roleBoardQuickActions($conn, 17, 999999);
    roleBoardAlerts($conn, $gate, 999);   // دورٌ بلا تعريفات → مصفوفة فارغة لا خطأ
} catch (\Throwable $t) { $threw = true; }
ok('الدوال الخمس تعمل بلا رمي — حتى لدورٍ/مستخدمٍ غير معرَّف', $threw === false);

ok('دورٌ بلا تعريفات تنبيهاتٍ يعيد مصفوفةً فارغة (لا اختراع)', roleBoardAlerts($conn, $gate, 999) === array());

echo "── ④ الفحص الساكن للربط ──\n";

$dash = file_get_contents(dirname(__DIR__) . '/main/dashboard.php');
ok('الرئيسية تحوّل المفعَّلَ للوحته خلف العلم (roleBoardEnabled ثم roleBoardRoute)',
    strpos($dash, 'roleBoardEnabled') !== false && strpos($dash, 'roleBoardRoute') !== false);

$cfo = file_get_contents(dirname(__DIR__) . '/Finance/cfo_daily_board_fin.php');
$components = array('مهامي', 'موافقاتي', 'التنبيهات', 'إنشاء سريع', 'نبض الأداء', 'عملي الأخير');
$missing = 0;
foreach ($components as $c) { if (mb_strpos($cfo, $c) === false) { $missing++; } }
ok('لوحة المدير المالي تحمل المكوّنات الستة المضافة (والبطاقات العشر = ① مؤشرات اليوم)', $missing === 0);

ok('استعلامات المحرك بقيم ENUM الفعلية (approved لا ready · حالات الطلبات الصريحة)',
    strpos(file_get_contents(dirname(__DIR__) . '/includes/role_board.php'), "p.state='approved'") !== false
    && strpos(file_get_contents(dirname(__DIR__) . '/includes/role_board.php'), "'draft','under_review','pending_approval','returned'") !== false);

echo "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n";
exit($FAIL > 0 ? 1 : 0);
