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

// قرارُ المالك 2026-08-21: «الرئيسية» صارت لوحةَ كلِّ صاحبِ إعدادٍ عام — والأربعُ
// المخصصةُ الباقيةُ (13·16·23·26) شاشاتٌ قائمةٌ بذاتها لا إعدادَ عامًّا لها.
$boardRoles = array(1, 2, 3, 4, 5, 6, 12, 15, 17, 24);
$routeOk = true;
foreach ($boardRoles as $r) { if (roleBoardRoute($r) !== 'main/dashboard.php') { $routeOk = false; } }
ok('الخريطة: أربعُ لوحاتٍ مخصصةٌ باقيةٌ في شاشاتها + عشرةُ أدوارٍ على «الرئيسية»',
    $routeOk
    && roleBoardRoute(13) === 'Maintenance/dashboard_mnt.php'
    && roleBoardRoute(16) === 'Procurement/dashboard_proc.php'
    && roleBoardRoute(23) === 'Transport/transfer_dashboard.php'
    && roleBoardRoute(26) === 'Financing/financing_board.php'
    && roleBoardRoute(999) === null);

// «الفرعيُّ يرث أباه، ومَن له مدخلٌ صريحٌ لا يرث»
ok('الوراثة: الفرعيُّ بلا مدخلٍ يرث أباه — وصاحبُ المدخلِ الصريحِ لا يرث',
    roleBoardRoute(7, 1) === 'main/dashboard.php'
    && roleBoardRoute(18, 17) === 'main/dashboard.php'
    && roleBoardRoute(14, 13) === 'Maintenance/dashboard_mnt.php'
    && roleBoardRoute(24, 1) === 'main/dashboard.php');

// إعدادُ اللوحة كاملٌ لكلِّ دورٍ عليها — وكلُّ بطاقةٍ ومهمةٍ بقفزة (href)
$cfgOk = true; $permOk = true;
foreach ($boardRoles as $r) {
    $cfg = roleBoardGenericConfig($r);
    if ($cfg === null || empty($cfg['cards']) || empty($cfg['pulse'])) { $cfgOk = false; continue; }
    foreach (array_merge($cfg['cards'], $cfg['tasks']) as $def) {
        if (empty($def[0]) || empty($def[4])) { $cfgOk = false; }
    }
}
ok('إعدادُ الأدوارِ العشرةِ كامل — كل بطاقةٍ ومهمةٍ باسمٍ وقفزة', $cfgOk);

// ولوحةٌ نُقلت من شاشةٍ محروسةٍ تحمل معها قفلَها: `perm` تُعلن الشاشةَ التي
// يحكم can_view عليها رؤيتَها، وإلا سقط الحارسُ بالتضمين.
ok('اللوحةُ المالية تحمل قفلَ شاشتها الأصلية (perm)',
    roleBoardGenericConfig(17)['perm'] === 'Finance/cfo_daily_board_fin.php');
foreach ($boardRoles as $r) {
    $cfg = roleBoardGenericConfig($r);
    $p = isset($cfg['perm']) ? $cfg['perm'] : 'main/role_board.php';
    if ($p === '') { $permOk = false; }
}
ok('كلُّ لوحةٍ تُعلن شاشةَ صلاحيتِها (صراحةً أو بالافتراض)', $permOk);
ok('دورٌ غير معرَّفٍ يعيد null', roleBoardGenericConfig(999) === null);

// تنبيهات التشغيل (UX-03 §1 · UX-01 §8.1): ثلاثةٌ من أربعة — «انحرافُ الالتزام»
// بلا محركٍ بعد (تبويب غرفة العمليات ④ القادم) فلا يُعرض — عدم التلفيق محروس.
$ops = roleBoardAlertSpecs(1);
ok('تنبيهات التشغيل ثلاثةٌ كاملة — و«انحراف الالتزام» غائبٌ عمدًا بلا محرك',
    count($ops) === 3
    && count(array_filter($ops, function ($s) { return empty($s['href']) || empty($s['label']); })) === 0
    && count(array_filter($ops, function ($s) { return $s['key'] === 'commitment_variance'; })) === 0);

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

// المكوّنات ②-⑦ صارت في قالبٍ واحدٍ مشترك — فالحارسُ يفحص القالبَ نفسه ثم تضمينَه.
// (قبلَ الاستخراج كان يفحص نصَّ لوحة المدير المالي؛ وبعده صار ذلك الفحصُ يمرّ على
//  التعليقات وحدها — فنُقل إلى مصدر الحقيقة الحقيقي: role_board_widgets.php.)
$tpl = file_get_contents(dirname(__DIR__) . '/includes/role_board_widgets.php');
$components = array('مهامي', 'موافقاتي', 'التنبيهات', 'إنشاء سريع', 'عملي الأخير');
$missing = 0;
foreach ($components as $c) { if (mb_strpos($tpl, $c) === false) { $missing++; } }
ok('القالب المشترك يحمل المكوّنات ②-⑦ (النبض بعنوانٍ من الصفحة + canvas#rbPulse وسكربته)',
    $missing === 0 && strpos($tpl, 'id="rbPulse"') !== false && strpos($tpl, 'id="rbPulseInit"') !== false
    && strpos($tpl, '$rb_pulse_title') !== false && strpos($tpl, '$rb_pulse_series') !== false);

// اللوحاتُ الأربع تضمّن القالبَ ولا تكرّر سكربتَ Chart (① مؤشرات اليوم تبقى لكلٍّ بطاقاتُها)
$boards = array('Finance/cfo_daily_board_fin.php', 'Maintenance/dashboard_mnt.php',
                'Procurement/dashboard_proc.php', 'Transport/transfer_dashboard.php');
$bad = array();
foreach ($boards as $b) {
    $src = file_get_contents(dirname(__DIR__) . '/' . $b);
    if (strpos($src, "includes/role_board_widgets.php") === false) { $bad[] = "$b (لا تضمين)"; }
    if (strpos($src, 'new Chart(') !== false) { $bad[] = "$b (سكربت Chart مكرر)"; }
    if (strpos($src, '$rb_pulse_series') === false) { $bad[] = "$b (بلا سلسلتَي النبض)"; }
}
ok('اللوحاتُ الأربع على القالب المشترك بلا تكرار (' . implode(' · ', $bad) . ')', $bad === array());

ok('استعلامات المحرك بقيم ENUM الفعلية (approved لا ready · حالات الطلبات الصريحة)',
    strpos(file_get_contents(dirname(__DIR__) . '/includes/role_board.php'), "p.state='approved'") !== false
    && strpos(file_get_contents(dirname(__DIR__) . '/includes/role_board.php'), "'draft','under_review','pending_approval','returned'") !== false);

echo "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n";
exit($FAIL > 0 ? 1 : 0);
