<?php
/**
 * 2028_05_27_links_control_screen.php — تسجيلُ شاشةِ «تحكم الروابط» ومنحُها
 * ═══════════════════════════════════════════════════════════════════════════
 * **طلبُ المالك**: رابطٌ في صفحةِ الإعداداتِ إلى شاشةٍ باسم «تحكم الروابط»،
 * **يراه كلُّ من يصل صفحةَ الإعدادات**، موضعُه بعدَ «الملف الشخصي» و«تغيير
 * كلمة المرور».
 *
 * ◆ **ولماذا هجرةٌ لا بطاقةً في الصفحةِ وحدَها**: البطاقةُ في `settings.php`
 *   تُصيَّر للجميعِ فعلًا، لكنَّ النقرَ عليها كان يرتدُّ لكلِّ دورٍ بلا استثناء:
 *   `insidebar.php:13` ينادي `enforce_current_page_view_permission()` وهي
 *   تردُّ كلَّ شاشةٍ **لا تُحَلُّ إلى موديول** بـ`GOV-PERM-404-MODULE` (مقيسٌ
 *   بمسبارِ تصييرٍ قبلَ هذه الهجرة). فالرابطُ بلا تسجيلٍ رابطٌ إلى بابٍ مغلق.
 *
 * ◆ **والمنحُ يُنسخ من `Settings/settings.php` حرفًا لا يُخترع**: «من يصل
 *   الإعداداتِ يصل هذه» تعريفٌ تنفيذيٌّ لا تقدير — فمصدرُ المجتمعِ هو منحُ
 *   الإعداداتِ نفسُه في **الطبقتَين**:
 *   ① طبقةُ القالبِ (`gov_profile_items`) وهي الحاكمةُ لـ75 من 77 مستخدمًا
 *      حيًّا: كلُّ قالبٍ **نافذٍ** يحمل `Settings/settings.php` بـ`allow=1`
 *      (32 من 32) يأخذ بندًا مثلَه لهذه الشاشة.
 *   ② الطبقةُ القائمةُ (`role_permissions`) لغيرِ المغطَّى بقالبٍ نافذ:
 *      الأدوارُ التي تقرأ الإعداداتِ (1..8 · 11 · 12) تقرأ هذه.
 *   ⛔ **والقوالبُ المتقاعدةُ والمسودّةُ خارجَ المدى**: الحارسُ لا يقرأ إلا
 *     النافذَ، ومسودّةٌ تحت المراجعةِ لا تُدهَس بندًا لم يطلبه أحد.
 *
 * ⛔ **قراءةٌ لا تحرير**: كلُّ صفٍّ يُولد هنا `can_add=can_edit=can_delete=0`.
 *   ما تفعله الشاشةُ من أفعالٍ يأتي في الجزءِ الثاني بمنحِه المستقلِّ لا
 *   بمنحةٍ مفتوحةٍ سلفًا.
 *
 * ◆ **وصفُّ الموديولِ يُنسخ من شقيقتِه** `Settings/change_password.php`
 *   (`owner_role_id = NULL` · `is_link = '1'`): وبـ`owner_role_id` فارغًا لا
 *   تبلغ `dynamic_nav` أصلًا (تشترط `INNER JOIN roles ON m.owner_role_id`)،
 *   فلا تظهر في الشريطِ الجانبيِّ — مدخلُها بطاقةُ الإعداداتِ وحدَها.
 *
 * ◆ **والتجميدُ لا يشمل هذا المسار**: قادحاه على `gov_authority_grants`
 *   (منحةٌ جديدة) و`gov_role_profiles` (تفعيلُ قالب) — لا على بنودِ قالبٍ
 *   نافذٍ قائم.
 *
 * التشغيل: php database/migrations/2028_05_27_links_control_screen.php
 * العكس:   php database/migrations/2028_05_27_links_control_screen_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

$SRC  = 'Settings/settings.php';        // مصدرُ المجتمعِ — لا يُمَسّ
$CODE = 'Settings/links_control.php';   // الشاشةُ الجديدة
$NAME = 'تحكم الروابط';
$MARK = 'links_control:2028_05_27';

echo "══ ① قبلَ الكتابة ══════════════════════════════════════════════════════\n";
$srcProfiles = $one("SELECT COUNT(DISTINCT p.profile_id) FROM gov_profile_items i
                       JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
                      WHERE i.item_kind = 'screen' AND i.item_ref = '{$SRC}' AND i.allow = 1");
$srcRoles = $one("SELECT COUNT(DISTINCT rp.role_id) FROM role_permissions rp
                    JOIN modules m ON m.id = rp.module_id
                   WHERE m.code = '{$SRC}' AND rp.can_view = 1");
printf("   قوالبُ نافذةٌ تقرأ الإعدادات: %d\n", $srcProfiles);
printf("   أدوارٌ تقرأ الإعداداتِ في الطبقةِ القائمة: %d\n", $srcRoles);
printf("   صفُّ الموديولِ للشاشةِ الجديدة: %d (المستهدف قبلَ الجولة صفر)\n",
    $one("SELECT COUNT(*) FROM modules WHERE code = '{$CODE}'"));

/* ═══ ② صفُّ الموديول — يُدرَج مرّةً واحدةً ═══════════════════════════════ */
echo "\n══ ② تسجيلُ الشاشةِ في سجلِّ الوحدات ══════════════════════════════════\n";
$mid = $one("SELECT id FROM modules WHERE code = '{$CODE}' ORDER BY id LIMIT 1");
if ($mid < 1) {
    $st = $conn->prepare("INSERT INTO modules
        (name, code, owner_role_id, group_id, is_link, is_quick, icon, display_order)
        VALUES (?, ?, NULL, NULL, '1', 0, 'fa fa-link', 100)");
    $st->bind_param('ss', $NAME, $CODE);
    $st->execute();
    $mid = (int) $conn->insert_id;
    $st->close();
    printf("   أُدرِج صفُّ الموديول: id=%d\n", $mid);
} else {
    printf("   الصفُّ قائمٌ سلفًا: id=%d (لا يُدهَس)\n", $mid);
}
if ($mid < 1) { exit("⛔ تعذّر تسجيلُ الشاشة.\n"); }

/* ═══ ③ الطبقةُ القائمة — الأدوارُ التي تقرأ الإعدادات ═══════════════════ */
echo "\n══ ③ المنحُ في الطبقةِ القائمة (role_permissions) ═════════════════════\n";
$conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
              SELECT DISTINCT rp.role_id, {$mid}, 1, 0, 0, 0
                FROM role_permissions rp
                JOIN modules m ON m.id = rp.module_id
               WHERE m.code = '{$SRC}' AND rp.can_view = 1
                 AND NOT EXISTS (SELECT 1 FROM role_permissions x
                                  WHERE x.role_id = rp.role_id AND x.module_id = {$mid})");
printf("   صفوفٌ مضافة: %d\n", $conn->affected_rows);

/* ═══ ④ طبقةُ القوالبِ النافذةِ — حيث يُحكَم 75 من 77 ═════════════════════ */
echo "\n══ ④ المنحُ في طبقةِ القوالبِ (gov_profile_items) ══════════════════════\n";
$ins = $conn->prepare(
    "INSERT INTO gov_profile_items
       (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
     SELECT p.company_id, p.profile_id, 'screen', ?, 1, 0, 0, 0, ?
       FROM gov_role_profiles p
      WHERE p.profile_id = ? AND p.state = 'active'");
$rs = $conn->query(
    "SELECT DISTINCT p.profile_id FROM gov_role_profiles p
       JOIN gov_profile_items s ON s.profile_id = p.profile_id
            AND s.item_kind = 'screen' AND s.item_ref = '{$SRC}' AND s.allow = 1
      WHERE p.state = 'active'
        AND NOT EXISTS (SELECT 1 FROM gov_profile_items i
                         WHERE i.profile_id = p.profile_id
                           AND i.item_kind = 'screen' AND i.item_ref = '{$CODE}')");
$added = 0;
while ($rs && ($row = $rs->fetch_assoc())) {
    $pid = (int) $row['profile_id'];
    $ins->bind_param('ssi', $CODE, $MARK, $pid);
    $ins->execute();
    $added += $ins->affected_rows;
}
$ins->close();
printf("   بنودٌ مضافة: %d\n", $added);

/* ═══ ⑤ الشواهد — من دالّةِ القرارِ لا من الجدول ══════════════════════════ */
echo "\n══ ⑤ الشاهد ═══════════════════════════════════════════════════════════\n";
$ok = true;

$dstProfiles = $one("SELECT COUNT(DISTINCT p.profile_id) FROM gov_profile_items i
                       JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
                      WHERE i.item_kind = 'screen' AND i.item_ref = '{$CODE}' AND i.allow = 1");
$dstRoles = $one("SELECT COUNT(DISTINCT role_id) FROM role_permissions
                   WHERE module_id = {$mid} AND can_view = 1");
printf("   قوالبُ نافذةٌ تقرأ «تحكم الروابط»: %d من %d\n", $dstProfiles, $srcProfiles);
printf("   أدوارٌ تقرؤها في الطبقةِ القائمة: %d من %d\n", $dstRoles, $srcRoles);
if ($dstProfiles !== $srcProfiles || $dstRoles !== $srcRoles) { $ok = false; }

$wr = $one("SELECT COUNT(*) FROM gov_profile_items
             WHERE seeded_from = '{$MARK}' AND (can_add = 1 OR can_edit = 1 OR can_delete = 1)");
$wr2 = $one("SELECT COUNT(*) FROM role_permissions
              WHERE module_id = {$mid} AND (can_add = 1 OR can_edit = 1 OR can_delete = 1)");
printf("   رايات كتابةٍ وُلدت هنا: %d + %d (المستهدف صفر)\n", $wr, $wr2);
if ($wr !== 0 || $wr2 !== 0) { $ok = false; }

/* ◆ **والحكمُ يُقاس بالمستخدمِ الحيِّ من دالّةِ القرارِ نفسِها لا من الجدولِ
     الذي كتبناه** — والمطلوبُ ليس «يقرأ الجميعُ» بل **«من يقرأ الإعداداتِ
     يقرأ هذه، ومن لا فلا»**: تطابقُ مجتمعَين لا فتحُ باب. فيُقارَن الحكمانِ
     لكلِّ مستخدمٍ حيٍّ على الشاشتَين، والاختلافُ ولو في واحدٍ يوقف الجولة. */
require_once $ROOT . '/includes/permissions_helper.php';
$srcMid = $one("SELECT id FROM modules WHERE code = '{$SRC}' ORDER BY id LIMIT 1");
$users = array();
$r = $conn->query("SELECT id, username, role FROM users WHERE is_deleted = 0 ORDER BY id");
while ($r && ($x = $r->fetch_assoc())) { $users[] = $x; }
$same = 0; $diff = array(); $openBoth = 0; $writeLeak = array();
foreach ($users as $x) {
    $uid = (int) $x['id'];
    $a = ems_permission_trace($conn, $srcMid, $uid);
    $b = ems_permission_trace($conn, $mid, $uid);
    $av = !empty($a['perms']['can_view']); $bv = !empty($b['perms']['can_view']);
    if ($av === $bv) { $same++; } else { $diff[] = $x['username'] . ' (دور ' . $x['role'] . ') الإعدادات=' . ($av ? 'نعم' : 'لا') . ' الروابط=' . ($bv ? 'نعم' : 'لا'); }
    if ($av && $bv) { $openBoth++; }
    /* والدورُ -1 مصرَّحٌ به في مصدرِ القرارِ الواحدِ فيقرأ ويكتب كلَّ شيء —
       فتسرُّبُ الكتابةِ يُحسب لغيرِه وحدَه. */
    if (strval($x['role']) !== '-1' && !empty($b['perms']['can_edit'])) { $writeLeak[] = $x['username']; }
}
printf("   مستخدمون حيّون: %d · تطابقَ حكمُ الشاشتَين لـ%d منهم\n", count($users), $same);
printf("   يقرأ الإعداداتِ و«تحكم الروابط» معًا: %d\n", $openBoth);
printf("   اختلافاتٌ: %d%s\n", count($diff), $diff ? ' ⛔ — ' . implode(' · ', array_slice($diff, 0, 5)) : '');
printf("   تسرُّبُ راية كتابةٍ لغير السوبر: %d (المستهدف صفر)\n", count($writeLeak));
if ($diff || $writeLeak || $openBoth < 1) { $ok = false; }

/* ⛔ ضابطٌ سالبٌ: منحُ الإعداداتِ نفسُه لم يتغيّر بعددِه. */
$srcAfter = $one("SELECT COUNT(DISTINCT p.profile_id) FROM gov_profile_items i
                    JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
                   WHERE i.item_kind = 'screen' AND i.item_ref = '{$SRC}' AND i.allow = 1");
printf("   منحُ الإعداداتِ بعدَ الجولة: %d (كان %d — لا يُمَسّ)\n", $srcAfter, $srcProfiles);
if ($srcAfter !== $srcProfiles) { $ok = false; }

if (!$ok) { exit("\n⛔ الشاهدُ لم يتحقّق — لا تلتزمْ قبلَ المراجعة.\n"); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "\n✔ تمّ — الشاشةُ مسجَّلةٌ ومقروءةٌ لمن يقرأ الإعدادات.\n";
