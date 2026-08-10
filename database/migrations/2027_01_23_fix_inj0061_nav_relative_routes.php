<?php
/**
 * 2027_01_23_fix_inj0061_nav_relative_routes.php
 * ═══════════════════════════════════════════════════════════════════════════
 * INJ-0061 (P1) — «السايدبار: مسارات نسبية مزدوجة».
 *
 * ◆ الجذرُ في **التركيب** لا في التخزين. `printNavLinkItem` تبني الرابطَ
 *   `href = $basePrefix . $route` وقيمةُ `$basePrefix` الحيةُ `'../'`. فصفٌّ
 *   يخزّن `../Audit/iaf_charter.php` يُصيَّر `../../Audit/iaf_charter.php` —
 *   أي مستوًى فوقَ جذرِ التطبيق. الرابطُ يُطبع سليمَ الشكلِ ويعطي 404.
 *
 * ◆ ولهذا لم يكشفه قياسُ FN-02: ذاك بحث عن `../../` **في العمود** فوجده صفرًا
 *   وهو صادق — المضاعفةُ تولد عند الطباعة. القاعدةُ المستفادة: يُقاس المُصيَّرُ
 *   لا المخزَّن، وأداةُ الشهادة `tools/fix_nav_href_probe.php` تفعل ذلك.
 *
 * ◆ النطاق: 41 صفًّا في سبعةِ أدوار (9 · 17 · 18 · 21 · 31 · 32 · 33)، منها
 *   27 تُصيَّر فعلًا والباقي محجوبٌ بالصلاحيةِ — ومكسورٌ متى ظهر. تُنزع البادئةُ
 *   فتُطابق الصيغةَ القانونيةَ للصفوفِ الـ1609 الأخرى: `Dir/file.php`.
 *
 * ◆ ويُثبَّت القيدُ بعد التنظيف، فالتصحيحُ بلا قيدٍ يعود بأولِ بذرةٍ لاحقة.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال المرحِّل فشل: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$ROOT = dirname(__DIR__, 2);
$fail = function ($msg) { fwrite(STDERR, "✘ {$msg}\n"); exit(1); };

/* ── ① الحصرُ على ثلاثِ حالاتٍ — كشفها قيدُ `uq_nav_role_route` ──────────
   ⓐ **له توأمٌ نظيفٌ** بالدورِ والمسارِ نفسِه: الصفُّ النسبيُّ نسخةٌ زائدة.
      وهذا يعني أن السايدبارَ كان يعرض العنصرَ **مرتين**: مرةً تعمل ومرةً
      تُعطي 404 — عيبٌ ثانٍ لم يكشفه عدُّ الصفوفِ ولا عدُّ الروابطِ المكسورة
      وحدَه. يُحذف الزائدُ لأنه تكرارُ إعدادٍ لا واقعةَ عملٍ تُعكَس.
   ⓑ **بلا توأمٍ وهدفُه قائمٌ**: تُنزع البادئةُ فيطابق الصيغةَ القانونية.
   ⓒ **بلا توأمٍ وهدفُه مفقودٌ**: يُترك ويُعلَن — نزعُ البادئةِ هنا يحوّل
      رابطًا مكسورًا إلى مكسورٍ آخرَ ويخفي العيبَ خلف صيغةٍ سليمة. */
$dupes   = array();
$targets = array();
$orphans = array();
$res = $db->query(
    "SELECT a.id, a.route, b.id AS twin
       FROM nav_items a
       LEFT JOIN nav_items b
              ON b.role_id = a.role_id AND b.route = SUBSTRING(a.route, 4) AND b.id <> a.id
      WHERE a.route LIKE '../%'"
);
while ($x = $res->fetch_assoc()) {
    $clean = preg_replace('#^(\.\./)+#', '', $x['route']);
    if (!empty($x['twin']))                                { $dupes[]   = (int) $x['id']; }
    elseif (is_file($ROOT . '/' . strtok($clean, '?#')))    { $targets[(int) $x['id']] = $clean; }
    else                                                    { $orphans[] = $x['route']; }
}
echo '① ببادئةٍ نسبية: ' . (count($dupes) + count($targets) + count($orphans))
   . ' · مكرَّرٌ يُحذف: ' . count($dupes)
   . ' · يُنظَّف: ' . count($targets)
   . ' · يتيمٌ يُعلَن: ' . count($orphans) . "\n";
foreach ($orphans as $o) { echo "   ⚠ يتيم — هدفُه مفقودٌ فيُترك: {$o}\n"; }

/* ── ② التنفيذُ في معاملةٍ واحدة ─────────────────────────────────────────
   الجولةُ الأولى من هذا الترحيل أجهضت في منتصفها لأنها كانت بلا معاملة،
   فنُظِّف 14 صفًّا وبقي 27. الآن: كلُّه أو لا شيء. وهو idempotent — إعادةُ
   التشغيلِ بعد النجاحِ تجد صفرَ صفٍّ في الحالاتِ الثلاث. */
$db->query('START TRANSACTION');

if ($dupes) {
    $ok = $db->query('DELETE FROM nav_items WHERE id IN (' . implode(',', $dupes) . ')');
    if (!$ok) { $db->query('ROLLBACK'); $fail('حذفُ المكرَّرِ فشل: ' . $db->error); }
}
if ($targets) {
    $st = $db->prepare('UPDATE nav_items SET route=?, updated_at=NOW() WHERE id=?');
    if (!$st) { $db->query('ROLLBACK'); $fail('تحضيرُ التحديثِ فشل: ' . $db->error); }
    foreach ($targets as $id => $clean) {
        $st->bind_param('si', $clean, $id);
        if (!$st->execute()) { $st->close(); $db->query('ROLLBACK'); $fail("تحديثُ #{$id} فشل: " . $st->error); }
    }
    $st->close();
}
$db->query('COMMIT');
echo '② حُذف ' . count($dupes) . ' مكرَّرًا ونُظِّف ' . count($targets) . " صفًّا\n";

/* ── ③ القيد — بعد التنظيف حصرًا، وإلا رفضته البياناتُ القائمة ────────── */
$db->query('ALTER TABLE nav_items DROP CONSTRAINT chk_nav_route_not_relative');   // idempotent
$mk = $db->query("ALTER TABLE nav_items ADD CONSTRAINT chk_nav_route_not_relative
                  CHECK (route IS NULL OR route NOT LIKE '../%')");
if (!$mk) { $fail('إنشاءُ القيدِ فشل: ' . $db->error); }
echo "③ القيدُ chk_nav_route_not_relative مُنشأ\n";

/* ── ④ إثباتُ القيدِ وظيفيًّا — بضابطٍ ومعالَجٍ لا بمحاولةٍ واحدة ────────
   محاولةُ خرقٍ **واحدةٌ** لا تُثبت شيئًا: الجولةُ الأولى من هذا الفحصِ مرّت
   بـ`errno=0` لأن صفَّ الجسِّ كان ينكسر على قيدٍ آخر (`chk_nav_items_module_or_code`
   يشترط `module_id` أو `permission_code`) — فمرَّ البرهانُ لسببٍ خاطئ.
   العلاج: صفّان متطابقان في كلِّ شيءٍ إلا المسار.
     • **الضابط** بمسارٍ نظيفٍ — إن رُفض فالصفُّ نفسُه معيبٌ والبرهانُ باطل.
     • **المعالَج** بمسارٍ نسبيٍّ — يجب أن يُرفض، وبرقمِ خطأِ القيودِ تحديدًا. */
// `chk_nav_items_module_or_code` نصُّه: permission_code فارغٌ **أو** module_id>0.
// فصفُّ الجسِّ يتركه NULL — لا لتفادي القيدِ بل لأنه لا يحتاجه، فيبقى المسارُ
// وحدَه المتغيِّرَ بين الضابطِ والمعالَج.
$shape = 'role_id, door, label_ar, route, sort_order, active';
$vals  = "-99, 'GOV', 'جسُّ قيدٍ', '%s', 0, 0";

$db->query('START TRANSACTION');
$control = $db->query("INSERT INTO nav_items ({$shape}) VALUES (" . sprintf($vals, '__probe__.php') . ')');
if (!$control) {
    $e = $db->error; $db->query('ROLLBACK');
    $fail("الضابطُ رُفض فالبرهانُ باطل — صفُّ الجسِّ معيبٌ لسببٍ غيرِ المسار: {$e}");
}
$treated = $db->query("INSERT INTO nav_items ({$shape}) VALUES (" . sprintf($vals, '../__probe__.php') . ')');
$tErrno  = $db->errno;
$tError  = $db->error;
$db->query('ROLLBACK');

if ($treated) { $fail('القيدُ لا يمنع — قُبل صفٌّ ببادئةٍ نسبية. أُسقط الترحيل.'); }
if ($tErrno !== 4025 && stripos($tError, 'chk_nav_route_not_relative') === false) {
    $fail("رُفض المعالَجُ لسببٍ غيرِ قيدِنا (errno={$tErrno}): {$tError}");
}
echo "④ القيدُ أثبت نفسَه: الضابطُ قُبل والمعالَجُ رُفض بـchk_nav_route_not_relative\n";

/* ── ⑤ الشاهدُ النهائيُّ على الحالة ──────────────────────────────────── */
$left = $db->query("SELECT COUNT(*) n FROM nav_items WHERE route LIKE '../%'")->fetch_assoc()['n'];
if ((int) $left !== count($orphans)) {
    $fail("بقي {$left} صفًّا ببادئةٍ نسبيةٍ والمتوقَّعُ " . count($orphans));
}
echo "⑤ المتبقي ببادئةٍ نسبية: {$left} (كلُّها يتيمةٌ مُعلَنة)\n";
echo "\n✔ 2027_01_23 تمّ. والشاهدُ الحيُّ: php tools/fix_nav_href_probe.php\n";
