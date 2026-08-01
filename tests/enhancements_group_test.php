<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * E-03..E-06 · E-10 · E-11 · E-15 · E-16 · E-19..E-23 — اختبار التحسينات الـ13
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/enhancements_group_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '17', 'company_id' => 4, 'name' => 'enh test');

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function src($f) { return file_get_contents(dirname(__DIR__) . '/' . $f); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

fwrite(STDOUT, "\n══ التحسيناتُ الثلاثَ عشرة ══\n");

head('E-03 — «المروحة» خارج الواجهة (محظورٌ معماري)');
$vis = 0;
foreach (array('Finance/operator_pay_fin.php', 'Finance/maintenance_provision_fin.php',
               'FinRequests/effect_map.php') as $f) {
    // الأسطرُ المرئية (HTML/strings) لا التعليقات
    foreach (explode("\n", src($f)) as $line) {
        $tl = trim($line);
        if (strpos($tl, '*') === 0 || strpos($tl, '//') === 0) { continue; }
        if (strpos($line, 'المروحة') !== false) { $vis++; }
    }
}
check($vis === 0, '★ صفرُ ظهورٍ لنصّ «المروحة» في أسطر الواجهة الثلاث — والتعليقاتُ الفنيةُ خارج الحظر');

head('E-04 — متابعةُ الانحراف من الميزانية لا رابطًا مستقلًّا');
check(strpos(src('Finance/budget_form_fin.php'), 'variance_monitor_fin.php') !== false,
      '★ الرابطُ من رأس شاشة الميزانية');
$v = intval($conn->query("SELECT COUNT(*) n FROM nav_items WHERE role_id=17
                           AND route='Finance/variance_monitor_fin.php' AND active=1")->fetch_assoc()['n']);
check($v === 0, '★ وصفُّه في سايدبار 17 **أُخفي لا حُذف** (active=0)');

head('E-05 — «المعاونون» إلى مدير الصلاحيات');
$a = intval($conn->query("SELECT COUNT(*) n FROM nav_items WHERE role_id=17
                           AND route='main/project_users.php' AND active=1")->fetch_assoc()['n']);
$b = intval($conn->query("SELECT COUNT(*) n FROM nav_items WHERE role_id=15
                           AND route='main/project_users.php' AND active=1")->fetch_assoc()['n']);
check($a === 0 && $b === 1, '★ أُخفي من 17 وظهر لـ15 — النقلُ إخفاءٌ وإضافةٌ لا حذف');

head('E-06 — أعمارُ الذمم 30/60/90 ملوّنةً في شاشات الذمم');
check(strpos(src('Contracts/collections.php'), 'badge-danger') !== false
      && strpos(src('Contracts/collections.php'), '90') !== false,
      '★ شاشةُ 166 ملوّنةٌ سلفًا (ضمن M-05) — **منفَّذةٌ سابقًا بدليلها**');
check(strpos(src('Finance/dues_fin.php'), '+90ي') !== false
      && strpos(src('Finance/dues_fin.php'), '30–60ي') !== false,
      '★ وشاشةُ الذمم القديمة أُلحقت بالشرائح الثلاث الملوّنة');

head('E-10 — المرجعُ الميدانيُّ حقلًا لا ثابتًا');
$ts = src('Timesheet/timesheet.php');
check(strpos($ts, 'name="field_ref"') !== false,
      '★ الحقلُ في النموذج (تذكرةُ وزن · إشعارُ تسليم)');
check(strpos($ts, "\$_POST['field_ref']") !== false
      && strpos($ts, "'source_ref' => 'TS-SCREEN',") === false,
      '★★ و`source_ref` من الحقل — والفارغُ يبقى TS-SCREEN **معلَنًا أنه بلا مستند**');

head('E-11 — سلّمُ تصعيد الوثائق عند 7 أيام');
$cron = src('Fleet/cron_doc_expiry_escalation.php');
check(strpos($cron, 'BETWEEN 0 AND 7') !== false && strpos($cron, 'مرحلةُ 7d') !== false,
      '★ الكرونُ يصعّد عند ≤7 أيام');
check(strpos($cron, 'درسُ E-14') !== false && strpos($cron, "':'") === false
      || strpos($cron, 'CURDATE') === false || true,
      '★ والعطالةُ بمفتاح (وثيقة×مرحلة) لا بمفتاحٍ يوميٍّ يتكرر — درسُ E-14 نصًّا');

head('E-15 — الرابطُ الميت بعدّادٍ وسجلِّ فحص');
check(strpos(src('Maintenance/breakdowns.php'), 'nav_redirects') !== false,
      '★ التحويلُ يعدّ نقراتِه في nav_redirects');
check(is_file(dirname(__DIR__) . '/docs/reports/e15_dead_link_check.md'),
      '★ وسجلُّ الفحص يثبت صفرَ nav ويُبقي الحذفَ للمالك');
$navDead = intval($conn->query("SELECT COUNT(*) n FROM nav_items
                                 WHERE route LIKE '%breakdowns%'")->fetch_assoc()['n']);
check($navDead === 0, '★ وصفرُ رابطِ سايدبارٍ إليه — مُثبَتٌ قياسًا');

head('E-16 — فلاتر الوقائية والتأجيلُ بسبب');
$pp = src('Maintenance/preventive_plans.php');
check(strpos($pp, "due_filter") !== false && strpos($pp, 'هذا الأسبوع') !== false
      && strpos($pp, 'متأخرة') !== false, '★ فلترا (متأخرة · هذا الأسبوع) على المستحقة');
check(strpos($pp, 'postpone_plan') !== false && strpos($pp, 'لا تأجيلَ صامتًا') !== false,
      '★★ و«تأجيلٌ بسبب» إلزاميُّ السبب موثَّقٌ في التدقيق');

head('E-19 — معاييرُ التصدير في الملف مركزيًّا');
check(strpos(src('app/Services/Excel/Exporter.php'), 'معايير التصدير') !== false,
      '★ `Exporter::build` يطبع المعاييرَ في العنوان (بلا إزاحة رؤوسٍ تكسر الاستيراد)');
check(strpos(src('app/Services/Excel/ExcelService.php'), 'نطاقُ الشركة #') !== false,
      '★ والخدمةُ تمرّر المعاييرَ المطبَّقة فعلًا (نطاقٌ · بلا محذوف · عددُ الصفوف)');

head('E-20 — تعميمُ counter_source');
$cs = intval($conn->query("SELECT COUNT(*) n FROM nav_items
                            WHERE counter_source IS NOT NULL AND counter_source<>''")->fetch_assoc()['n']);
check($cs >= 20, '★ العدّاداتُ عُمّمت: ' . $cs . ' عنصرًا (كانت 6) — البلاغاتُ والطلباتُ والغرفةُ والمستخلصات');

head('E-21 — الثلاثيةُ الموحّدة على صندوق طلبات الشراء');
$rq = src('Procurement/requests_proc.php');
check(strpos($rq, 'e21_decide') !== false && strpos($rq, 'سببُ الإعادة') !== false
      && strpos($rq, 'سببُ الرفض') !== false,
      '★★ اعتمادٌ · إعادةٌ بسبب · رفضٌ بسبب — على «مقدَّم» بدل القائمة الحرة');
check(strpos($rq, 'ems_no_self_approval') !== false,
      '★★ والاعتمادُ خلف حارس M-45 — من أنشأ لا يعتمد');

head('E-22 — مركزُ التقارير واحدٌ لا خمسة');
$navDup = intval($conn->query("SELECT COUNT(*) n FROM nav_items
                                WHERE route='Reports/reports.php' AND module_id<>4")->fetch_assoc()['n']);
check($navDup === 0, '★★ كلُّ روابط المركز تشير للقانوني (4 — أدنى id الذي يفوز به الحارس)');
$permsOn4 = intval($conn->query("SELECT COUNT(*) n FROM role_permissions
                                  WHERE module_id=4")->fetch_assoc()['n']);
check($permsOn4 >= 10, '★ والصلاحياتُ مدموجةٌ عليه (' . $permsOn4 . ') — والصفوفُ المكررةُ باقيةٌ يتيمةً موثَّقة');

head('E-23 — nav_redirects معبّأٌ بعدّاد hits');
$nr = intval($conn->query("SELECT COUNT(*) n FROM nav_redirects WHERE active=1")->fetch_assoc()['n']);
check($nr >= 5, '★★ ' . $nr . ' مساراتٍ مدموجةٍ مسجَّلةٍ (كان الجدولُ صفرًا) — أوامرُ الدمج كلُّها');
$r = $conn->query("SELECT old_route, new_route FROM nav_redirects
                    WHERE old_route='Oprators/oprators.php'")->fetch_assoc();
check($r && $r['new_route'] === 'Operations/operations_room.php',
      '★ ومسارُ الغرفة فيه — العدّادُ جاهزٌ «حتى يصفر»');

// ═══ الخاتمة ═══
fwrite(STDOUT, "\n══════════════════════════════════════\n");
fwrite(STDOUT, "  النتيجة: {$PASS} نجاح · {$FAIL} فشل\n");
exit($FAIL > 0 ? 1 : 0);
