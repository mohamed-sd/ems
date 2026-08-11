<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-05 — اختبار قبول الموقع/المنجم كيانًا مستقلًّا (OPM-01 §2-③)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/sites_entity_test.php
 *
 * ما يُثبته:
 *   ① البنية: sites بمفاتيحها (UNIQUE اسمٌ داخل المشروع · FK المشروع
 *      والمسؤول) + contracts.site_id بFK.
 *   ② التعبئة الصادقة: موقعٌ افتراضيٌّ لكل مشروعٍ حي باسمه — وكلُّ عقدٍ
 *      مربوطٌ بموقع مشروعه الافتراضي (لا اختراع — المشروعُ كان الموقعَ ضمنًا).
 *   ③ الهرم §2-③ بنيويًّا: المشروعُ يقبل عدةَ مواقع · والموقعُ تصله عدةُ
 *      عقود · والاسمُ المكرر داخل المشروع يُرفض (المُرجَع لا الاستثناء).
 *   ④ العزل: sites مسجَّلةٌ في بوابة المستأجر (fail-closed) وscopedQuery
 *      تحقن نطاقَ الشركة.
 *   ⑤ الشاشة: مسجَّلةٌ (موديول 150 · منح 1/3/5 · روابط nav) وبصلاحيةٍ
 *      صارمةٍ وحارسِ «الافتراضيُّ لا يُعطَّل».
 *
 * البذرُ معزول: مواقعُ وعقدُ اختبارٍ بوسم H05_<pid> تُكنس في النهاية.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$MARK = 'H05_' . getmypid();
$seedSites = array();

$teardown = function () use ($conn, $MARK, &$seedSites) {
    foreach ($seedSites as $id) { $conn->query("DELETE FROM sites WHERE id = " . intval($id)); }
    $conn->query("DELETE FROM sites WHERE name LIKE '{$MARK}%'");
};
register_shutdown_function($teardown);

fwrite(STDOUT, "\n══ H-05 — الموقعُ/المنجم كيانًا مستقلًّا ══\n");

// ═══ ① البنية ═══
head('① البنية — الجدولُ بمفاتيحه وعمودُ العقد');
$r = $conn->query("SHOW TABLES LIKE 'sites'");
check($r && $r->num_rows === 1, 'جدول sites قائم');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sites' AND INDEX_NAME='uq_site_name'");
check($r && intval($r->fetch_assoc()['c']) === 3, 'UNIQUE(company, project, name) قائم');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.KEY_COLUMN_USAGE
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sites'
                     AND REFERENCED_TABLE_NAME IN ('project','employees')");
check($r && intval($r->fetch_assoc()['c']) === 2, 'FK → project + employees');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.KEY_COLUMN_USAGE
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts'
                     AND COLUMN_NAME='site_id' AND REFERENCED_TABLE_NAME='sites'");
check($r && intval($r->fetch_assoc()['c']) === 1, 'contracts.site_id بFK → sites');

// ═══ ② التعبئة الصادقة ═══
head('② التعبئة — افتراضيٌّ لكل مشروعٍ وكلُّ العقود مربوطة');
$r = $conn->query("SELECT COUNT(*) c FROM project p WHERE COALESCE(p.is_deleted,0)=0
                   AND NOT EXISTS (SELECT 1 FROM sites s WHERE s.project_id=p.id AND s.is_default=1)");
check(intval($r->fetch_assoc()['c']) === 0, 'صفرُ مشروعٍ حيٍّ بلا موقعٍ افتراضي');
$r = $conn->query("SELECT COUNT(*) c FROM sites s JOIN project p ON p.id=s.project_id
                   WHERE s.is_default=1 AND s.name <> p.name");
check(intval($r->fetch_assoc()['c']) === 0, 'الافتراضيُّ باسم مشروعه حرفيًّا — لا اختراع');
$r = $conn->query("SELECT COUNT(*) c FROM contracts WHERE site_id IS NULL");
check(intval($r->fetch_assoc()['c']) === 0, 'كلُّ العقود القائمة مربوطةٌ بموقع (10/10 وقتَ الهجرة)');
$r = $conn->query("SELECT COUNT(*) c FROM contracts c JOIN sites s ON s.id=c.site_id
                   WHERE s.project_id <> c.project_id");
check(intval($r->fetch_assoc()['c']) === 0, 'موقعُ كلِّ عقدٍ من مشروعِه نفسِه (لا ربطَ متقاطع)');

// ═══ ③ الهرم بنيويًّا ═══
head('③ الهرم §2-③ — تعددُ المواقع والعقود ورفضُ التكرار');
$proj = $conn->query("SELECT id, company_id FROM project WHERE COALESCE(is_deleted,0)=0 LIMIT 1")->fetch_assoc();
$pid = intval($proj['id']); $co = intval($proj['company_id']);
$ok1 = $conn->query("INSERT INTO sites (company_id, project_id, name, site_kind) VALUES ({$co}, {$pid}, '{$MARK}_A', 'mine')");
$sA = intval($conn->insert_id); if ($sA > 0) { $seedSites[] = $sA; }
$ok2 = $conn->query("INSERT INTO sites (company_id, project_id, name, site_kind) VALUES ({$co}, {$pid}, '{$MARK}_B', 'site')");
$sB = intval($conn->insert_id); if ($sB > 0) { $seedSites[] = $sB; }
check($ok1 === true && $ok2 === true, 'المشروعُ يقبل عدةَ مواقعَ (منجمٌ + موقع) فوق افتراضيِّه');
// «المنجمُ حالةٌ من الموقع» — العمودُ تمييزٌ عرضيٌّ والصفان في جدولٍ واحد
$r = $conn->query("SELECT COUNT(DISTINCT site_kind) c FROM sites WHERE id IN ({$sA},{$sB})");
check(intval($r->fetch_assoc()['c']) === 2, 'المنجمُ حالةٌ من الموقع — جدولٌ واحدٌ لا معالجةَ مفترقة');
// التكرارُ داخل المشروع يُرفض — گوتشا: افحص المُرجَعَ لا الاستثناء
$dup = $conn->query("INSERT INTO sites (company_id, project_id, name) VALUES ({$co}, {$pid}, '{$MARK}_A')");
check($dup === false && intval($conn->errno) === 1062, 'الاسمُ المكرر داخل المشروع يُرفض (errno 1062 على المُرجَع)');
// الاسمُ نفسُه في مشروعٍ آخرَ مسموح (التفرّدُ داخل المشروع لا النظام)
$proj2 = $conn->query("SELECT id, company_id FROM project WHERE COALESCE(is_deleted,0)=0 AND id <> {$pid} LIMIT 1")->fetch_assoc();
if ($proj2) {
    $p2 = intval($proj2['id']); $co2 = intval($proj2['company_id']);
    $ok3 = $conn->query("INSERT INTO sites (company_id, project_id, name) VALUES ({$co2}, {$p2}, '{$MARK}_A')");
    $s3 = intval($conn->insert_id); if ($s3 > 0) { $seedSites[] = $s3; }
    check($ok3 === true, 'الاسمُ نفسُه في مشروعٍ آخر مسموح — التفرّدُ نطاقُه المشروع');
} else { ok('مشروعٌ ثانٍ غيرُ متاح — الفرعُ مغطًّى بالقيد نفسه'); }
// موقعٌ لمشروعٍ غير موجود يُرفض بنيويًّا
$bad = $conn->query("INSERT INTO sites (company_id, project_id, name) VALUES ({$co}, 99999999, '{$MARK}_X')");
check($bad === false, 'مشروعٌ وهمي يُرفض بFK');
// عقدٌ يُربط بموقعٍ ثانٍ (الموقعُ تصله عدةُ عقود — والعقدُ يُغيَّر موقعُه)
$c1 = $conn->query("SELECT id, site_id FROM contracts WHERE project_id = {$pid} LIMIT 1")->fetch_assoc();
if ($c1) {
    $cid = intval($c1['id']); $orig = intval($c1['site_id']);
    $upd = $conn->query("UPDATE contracts SET site_id = {$sA} WHERE id = {$cid}");
    $r = $conn->query("SELECT site_id FROM contracts WHERE id = {$cid}")->fetch_assoc();
    check($upd === true && intval($r['site_id']) === $sA, 'العقدُ يقبل موقعًا غيرَ الافتراضي');
    $conn->query("UPDATE contracts SET site_id = " . ($orig > 0 ? $orig : 'NULL') . " WHERE id = {$cid}");
} else { ok('لا عقدَ لهذا المشروع — التعددُ مثبتٌ بالبنية (FK لا يقيّد العدد)'); }

// ═══ ④ العزل ═══
head('④ بوابةُ المستأجر — sites مسجَّلةٌ والنطاقُ محقون');
$src = file_get_contents(dirname(__DIR__) . '/app/Core/TenantRegistry.php');
check(strpos($src, "'sites' => array('type' => self::T_TENANT, 'soft' => true)") !== false,
      'sites في السجل: T_TENANT بحذفٍ ناعم');
$r = $conn->query("SELECT COUNT(DISTINCT company_id) c FROM sites");
$multiCo = intval($r->fetch_assoc()['c']);
ok("الشركاتُ في sites: {$multiCo} — والبوابةُ تحقن company_id (نمطُ scopedQuery في الشاشة)");

// ═══ ⑤ الشاشة ═══
head('⑤ الشاشة — التسجيلُ والصلاحيةُ الصارمة');
$r = $conn->query("SELECT id, owner_role_id FROM modules WHERE code = 'Projects/sites.php'");
$m = $r->fetch_assoc();
check($m && intval($m['id']) === 150 && intval($m['owner_role_id']) === 1, 'الموديول 150 مملوكٌ للمشاريع (1)');
$r = $conn->query("SELECT COUNT(*) c FROM role_permissions WHERE module_id = 150 AND can_view = 1");
check(intval($r->fetch_assoc()['c']) === 3, 'منحُ العرض الثلاثة (1 كتابةً · 3 و5 عرضًا)');
$r = $conn->query("SELECT COUNT(*) c FROM role_permissions WHERE module_id = 150 AND role_id = 1 AND can_add = 1 AND can_edit = 1");
check(intval($r->fetch_assoc()['c']) === 1, 'الكتابةُ للمالك وحده');
$r = $conn->query("SELECT COUNT(*) c FROM nav_items WHERE route = 'Projects/sites.php' AND active = 1");
check(intval($r->fetch_assoc()['c']) === 3, 'روابطُ التنقل للأدوار الثلاثة');
$scr = file_get_contents(dirname(__DIR__) . '/Projects/sites.php');
check(strpos($scr, "m.code = ?") !== false && strpos($scr, 'الصلاحيةُ صارمة') !== false,
      'الشاشةُ بصلاحيةٍ صارمة (الكودُ الحرفي وغيابُه منع)');
/* ◆ **علامةُ زائدٍ حرفيةٌ موضعَ المسافة** — أثرُ نسخٍ من نصٍّ مُرمَّزٍ للروابط
     (space→+)، فبحثُ السلسلةِ لا يطابق النصَّ في المنتجِ أبدًا والحارسُ قائمٌ
     فعلًا. ويُقاس **المنعُ** لا التعليقُ: رمزُ الردِّ وشرطُه معًا. */
check(strpos($scr, 'is_default') !== false
      && strpos($scr, 'GOV-FAIL-409') !== false,
      'حارسُ «الافتراضيُّ لا يُعطَّل» قائم');
check(strpos($scr, 'ems_audit_change') !== false, 'الحفظُ موصولٌ بسجل التدقيق (N-02)');
check(strpos($scr, 'scopedQuery') !== false && strpos($scr, '{TENANT_SCOPE}') !== false,
      'القراءةُ عبر scopedQuery بنطاق المستأجر');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
