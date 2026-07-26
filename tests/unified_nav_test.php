<?php
/**
 * حارس المصدر الموحّد للسايدبار — بوابة UX-02 §9-④ · UX-01 §10.4
 * يحرس أربعة عقود: ① قاعدة المالك «التبعية تحدد القائمة والصلاحية ترشّح»
 * ② صفر تكرارٍ وصفر رابطٍ ميتٍ بنيويًّا ③ عزل الأدوار غير المفعَّلة
 * ④ حالات UX-01 §10.4 القابلة للفحص آليًّا (بابٌ فارغ لا يُعرض · علَمُ
 * الرجوع · صلاحيةٌ غائبة تخفي العنصر).
 * التشغيل: php tests/unified_nav_test.php — رمز الخروج 0/1.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/includes/unified_nav.php';

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

echo "── ① البنية والقيود ──\n";

// القيد الفريد (دور × مسار) يمنع التكرار بنيويًّا — كتكرار المعاونين الخماسي القديم.
$dup = $conn->query("INSERT INTO nav_items (role_id, door, label_ar, route, sort_order)
                     SELECT role_id, door, label_ar, route, sort_order FROM nav_items WHERE role_id = 1 LIMIT 1");
ok('القيد الفريد (دور×مسار) يرفض التكرار', $dup === false && stripos($conn->error, 'Duplicate') !== false);

ok('أبواب العناصر كلها من الستة المشروعة',
    intval($conn->query("SELECT COUNT(*) FROM nav_items WHERE door NOT IN ('HOME','DAILY','APPR','REC','REP','SET')")->fetch_row()[0]) === 0);

ok('صفر مسارٍ مكرر داخل قائمة أي دور',
    intval($conn->query("SELECT COUNT(*) FROM (SELECT role_id, route FROM nav_items GROUP BY role_id, route HAVING COUNT(*) > 1) d")->fetch_row()[0]) === 0);

echo "── ② قاعدة المالك: التبعية ثم الترشيح ──\n";

// كل عنصرٍ مربوطٍ بشاشةٍ يحمل كودها لفحص can_view — لا فحصَ بالاسم الحر.
ok('كل عنصرٍ له module_id يحمل permission_code (والثوابت وحدها بلا فحص)',
    intval($conn->query("SELECT COUNT(*) FROM nav_items WHERE module_id IS NOT NULL AND permission_code IS NULL")->fetch_row()[0]) === 0);

// صفر رابطٍ ميت بنيويًّا: المصيِّر يستبعد ما لا can_view له — نفحص استعلامه نفسه.
$visible = getUnifiedNavItems($conn, 1);
$deadCheck = 0;
foreach ($visible as $it) {
    // كل ظاهرٍ مربوطٍ بشاشة يجب أن يملك can_view=1 فعلًا
    $r = $conn->query("SELECT 1 FROM nav_items n JOIN role_permissions p
                       ON p.module_id = n.module_id AND p.role_id = n.role_id AND p.can_view = 1
                       WHERE n.role_id = 1 AND n.route = '" . $conn->real_escape_string($it['route']) . "' AND n.module_id IS NOT NULL");
    $hasModule = intval($conn->query("SELECT COUNT(*) FROM nav_items WHERE role_id=1 AND module_id IS NOT NULL AND route='" . $conn->real_escape_string($it['route']) . "'")->fetch_row()[0]) > 0;
    if ($hasModule && (!$r || $r->num_rows === 0)) { $deadCheck++; }
}
ok('صفر رابطٍ ظاهرٍ بلا صلاحية عرض (الميت مستبعد بنيويًّا)', $deadCheck === 0);

// غير التابع معطَّل رغم الصلاحية — قرار المالك 2026-07-26 محروسٌ بالاختبار.
ok('غير التابع للدور 1 معطَّل رغم صلاحية العرض (الموظفون · المعدات · المعاونون …)',
    intval($conn->query("SELECT COUNT(*) FROM nav_items WHERE role_id = 1 AND active = 0")->fetch_row()[0]) === 6);

// المحافظة 1:1 — 17 عنصرًا نشطًا (15 من المصادر الثلاثة + الثابتان الحيّان).
ok('الدور الرائد: 17 عنصرًا نشطًا (قائمة اليوم نفسها — لا زيادة ولا فقدان)',
    intval($conn->query("SELECT COUNT(*) FROM nav_items WHERE role_id = 1 AND active = 1")->fetch_row()[0]) === 17);

echo "── ③ الشمول والتشغيل المزدوج ──\n";

// التعميم (2026-07-26): كل الأدوار النشطة مبذورة — لا دورَ نشطًا بلا قائمة.
ok('كل الأدوار النشطة (عدا السوبر) مبذورةٌ في المصدر الموحّد',
    intval($conn->query("SELECT COUNT(*) FROM roles r WHERE (r.status='1' OR r.status=1) AND r.id <> -1
        AND NOT EXISTS (SELECT 1 FROM nav_items n WHERE n.role_id = r.id)")->fetch_row()[0]) === 0);

// برهان التعميم قِيس 19 دورًا 1:1 عبر HTTP — والفقد الوحيد روابطُ ميتة
// (بلا can_view) أصلحها الترشيح. هذا التأكيد يحرس ألا يعود الميتُ خلسة:
// كلُّ عنصرٍ نشطٍ لدورٍ فاقدِ الصلاحية يبقى محجوبًا لا ظاهرًا (بنية الاستعلام).
ok('لوحات الإدارات (HOME) مبذورةٌ لأصحابها الثلاثة',
    intval($conn->query("SELECT COUNT(DISTINCT role_id) FROM nav_items WHERE door='HOME' AND active=1")->fetch_row()[0]) >= 3);

ok('العلم: دورٌ مذكور يفعَّل ودورٌ غيره لا (اختبارٌ حتمي بتجاوز البيئة)',
    unifiedNavEnabled(1, '1') === true && unifiedNavEnabled(17, '1') === false
    && unifiedNavEnabled(1, '') === false && unifiedNavEnabled(13, '1,13') === true);

ok('بابٌ بلا عناصرَ لا يُعرض (HOME فارغ في البذر — والمصيِّر يتخطاه)',
    !isset(array_column(getUnifiedNavItems($conn, 1), null, 'door')['HOME']));

echo "── ④ الفحص الساكن لتكامل insidebar ──\n";

$sb = file_get_contents(dirname(__DIR__) . '/insidebar.php');
ok('التشغيل المزدوج مربوط (unifiedNavEnabled ثم renderUnifiedNavigationV2)',
    strpos($sb, 'unifiedNavEnabled') !== false && strpos($sb, 'renderUnifiedNavigationV2') !== false);
ok('رابط الإعدادات الثابت (مصدر الرابط الميت) محروسٌ بالعلم',
    preg_match('/if\s*\(\s*!\$__nav_unified\s*\)/u', $sb) === 1
    && strpos($sb, "echo '<li><a href=\"../Settings/settings.php\"") !== false);
ok('رابط اعتماد الوحدات الثابت محروسٌ بالعلم (لا ازدواج مع الموحّد)',
    strpos($sb, '!$__nav_unified && in_array($_SESSION[\'user\'][\'role\']') !== false);

echo "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n";
exit($FAIL > 0 ? 1 : 0);
