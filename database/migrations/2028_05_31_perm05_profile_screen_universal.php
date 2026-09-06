<?php
/**
 * 2028_05_31_perm05_profile_screen_universal.php — «الملفُّ الشخصيُّ» بندٌ في كلِّ قالبٍ نافذ
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **العطبُ المقيسُ حيًّا**: `main/profile.php` (الوحدة 279) في **صفرٍ من 32**
 *   قالبًا نافذًا. والشريطُ العلويُّ يعرض رابطَه **بلا شرطٍ** لكلِّ مسجَّلٍ
 *   (`includes/topbar.php:203`)، فيصطدم كلُّ من نقره بـ«لا تملك صلاحية عرض
 *   هذه الصفحة». مقيسٌ بنداءِ مسارِ القرارِ نفسِه — لا بمحاكاتِه — على مستخدمٍ
 *   حيٍّ لكلِّ دور: **ALLOW=0 · DENY=32**.
 *
 * ◆ **وهو غيابُ بندٍ لا قرارُ منع**: `allow=0` على هذه الشاشةِ **صفرٌ في المخزنِ
 *   كلِّه**؛ والقوالبُ المتقاعدةُ الثلاثةُ والعشرون التي كانت تحملها حملتها
 *   كلُّها `allow=1`. فالحقُّ سقط في توليدِ قوالبِ «هدف الدليل» ولم يُسحَب بحكم.
 *
 * ◆ **والعلاجُ في القالبِ لا في إعفاءِ الحارس**: للحارسِ اليومَ خمسةُ إعفاءاتٍ
 *   مثبَّتةٍ في الشيفرة (اللوحةُ · المراسلاتُ · البلاغاتُ · شاشتا التذاكر)،
 *   وكلُّ إعفاءٍ **مسارُ قرارٍ ثانٍ لا تراه شاشاتُ الحوكمة**: `perm_matrix`
 *   و`auth_profiles` و`perm_explain` تقرأ القالبَ وحدَه. وقد جعلت PERM-01
 *   (§4 · §6-②) القالبَ **مصدرَ القرارِ الواحدَ** وحرّمت السقوطَ إلى غيرِه؛
 *   فإعفاءٌ سادسٌ يُخرج الشاشةَ من كلِّ سطحِ تدقيقٍ ويُعيد «النظامَين».
 *   ⇒ فالبندُ يُكتب، ويبقى القرارُ يُقرأ من مكانٍ واحد.
 *
 * ◆ **عرضٌ فقط — وأعلامُ الكتابةِ صفر**: الشاشةُ بلا مسارِ كتابةٍ (صفرُ تعاملٍ
 *   مع `$_POST`)، وكلُّ استعلامٍ فيها مربوطٌ بـ`$user_id` من الجلسةِ نفسِها —
 *   فهي بطاقةُ صاحبِها لا نافذةٌ على غيرِه. ولا سندَ لعَلَمِ كتابةٍ هنا.
 *
 * ◆ **وفي الاثنتين والثلاثين جميعًا لا في أربعٍ وعشرين**: الأدوارُ 28–35 بلا
 *   صفٍّ لهذه الوحدةِ في الجدولِ القديمِ أصلًا، فالهجرةُ الشقيقةُ التي تُعيد
 *   من `role_permissions` (`2028_05_21_perm01_restore_removed_screens.php`)
 *   **لا تبلغهم**. وبطاقةُ المرءِ عن نفسِه لا تُقسَّم على الأدوار.
 *
 * ⚠ **وما لا تمسُّه هذه الهجرةُ ويجب أن يُقرَّر**: الفجوةُ الأوسعُ ما تزال
 *   **1,147** زوجَ (قالب·شاشة)، والهجرةُ المُعَدَّةُ لها موجودةٌ على القرصِ
 *   و**ليست في `schema_migrations`** — أي لم تُشغَّل قطُّ. ذاك قرارُ مالكٍ
 *   لا شأنَ لهذه الهجرةِ به، وهي تقيسه وتُعلنه ولا تُنفِّذه.
 *
 * ◆ **قابلةٌ لإعادةِ التشغيل**: `INSERT IGNORE` على `uq_item`
 *   (profile_id · item_kind · item_ref) ⇒ تشغيلٌ ثانٍ بصفرِ أثر.
 * ◆ **ولا تمسُّ منحةً ولا تُفعِّل قالبًا**: تجميدا `grants` و`profile_activation`
 *   نافذانِ ولا يُرفعان هنا — تُكتب بنودُ قوالبَ قائمةٍ نافذةٍ فقط.
 *
 * التشغيل: php database/migrations/2028_05_31_perm05_profile_screen_universal.php
 * العكس:   php database/migrations/2028_05_31_perm05_profile_screen_universal_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$t0  = microtime(true);
$one = function ($s) use ($conn) { $r = @$conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

const SCREEN   = 'main/profile.php';
const SEED_TAG = 'perm05_profile_universal_20260906';   // ≤ 60 حرفًا (varchar(60))
$fail = 0;

echo "══ ① الحالُ قبلَ العمل ════════════════════════════════════════════════\n";

/* ⛔ **الكودُ يجب أن يكون واحدًا**: `modules.code` غيرُ فريدٍ (18 مكرَّرًا)،
     وحارسُ القرارِ يقرأ `item_ref = (SELECT code FROM modules WHERE id = ?)`.
     فلو تعدّدت الوحداتُ بهذا الكودِ لصار البندُ يحرس واحدةً ويترك أخرى. */
$modN = $one("SELECT COUNT(*) FROM modules WHERE code = '" . SCREEN . "'");
printf("   وحداتٌ بالكود %s: %d %s\n", SCREEN, $modN, $modN === 1 ? '' : '⛔');
if ($modN !== 1) { exit("⛔ الكودُ ليس واحدًا — لا يُكتب بندٌ على مرجعٍ ملتبس.\n"); }

$activeN = $one("SELECT COUNT(*) FROM gov_role_profiles WHERE state = 'active'");
$haveN   = $one("SELECT COUNT(DISTINCT i.profile_id) FROM gov_profile_items i
                   JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
                  WHERE i.item_kind = 'screen' AND i.item_ref = '" . SCREEN . "'");
printf("   قوالبُ نافذةٌ: %d — منها تحمل البندَ: %d ⇒ الفجوةُ %d\n",
    $activeN, $haveN, $activeN - $haveN);

/* ◆ **وشاهدُ «غيابٌ لا منع»** يُقاس قبلَ الكتابةِ لا يُدَّعى. */
$denyRows = $one("SELECT COUNT(*) FROM gov_profile_items
                   WHERE item_kind = 'screen' AND item_ref = '" . SCREEN . "' AND allow = 0");
printf("   بنودُ منعٍ صريحٍ على الشاشةِ في المخزنِ كلِّه: %d %s\n",
    $denyRows, $denyRows === 0 ? '(⇒ غيابٌ لا قرارُ منع)' : '⛔ ثمّةَ منعٌ مقصود');
if ($denyRows !== 0) { exit("⛔ ثمّةَ منعٌ صريحٌ — لا يُدهَس بهجرة.\n"); }

echo "\n══ ② الكتابة ════════════════════════════════════════════════════════\n";
$ins = "INSERT IGNORE INTO gov_profile_items
          (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
        SELECT 0, p.profile_id, 'screen', '" . SCREEN . "', 1, 0, 0, 0, '" . SEED_TAG . "'
          FROM gov_role_profiles p
         WHERE p.state = 'active'";
if (!$conn->query($ins)) { exit("✘ فشل الإدراج: " . $conn->error . "\n"); }
printf("   بنودٌ أُضيفت: %d\n", $conn->affected_rows);

echo "\n══ ③ الشواهد ════════════════════════════════════════════════════════\n";

$gap = $one("SELECT COUNT(*) FROM gov_role_profiles p
              WHERE p.state = 'active'
                AND NOT EXISTS (SELECT 1 FROM gov_profile_items i
                                 WHERE i.profile_id = p.profile_id AND i.item_kind = 'screen'
                                   AND i.item_ref = '" . SCREEN . "' AND i.allow = 1)");
printf("   ★ قوالبُ نافذةٌ بلا البندِ: %d %s\n", $gap, $gap === 0 ? '' : '✘');
if ($gap !== 0) { $fail++; }

/* ⛔ **ضابطٌ سالبٌ — لا عَلَمَ كتابةٍ مُنِح**: الشاشةُ عرضٌ فقط. */
$flags = $one("SELECT COALESCE(SUM(can_add + can_edit + can_delete), 0) FROM gov_profile_items
                WHERE seeded_from = '" . SEED_TAG . "'");
printf("   ★ ضابطٌ سالب — أعلامُ كتابةٍ في البنودِ المضافة: %d %s\n", $flags, $flags === 0 ? '' : '✘');
if ($flags !== 0) { $fail++; }

/* ⛔ **ولا بندَ كُتب خارجَ القوالبِ النافذة**: المسودّاتُ والمتقاعدةُ لا تُمَسّ. */
$stray = $one("SELECT COUNT(*) FROM gov_profile_items i
                JOIN gov_role_profiles p ON p.profile_id = i.profile_id
               WHERE i.seeded_from = '" . SEED_TAG . "' AND p.state <> 'active'");
printf("   ★ ضابطٌ سالب — بندٌ مضافٌ في قالبٍ غيرِ نافذ: %d %s\n", $stray, $stray === 0 ? '' : '✘');
if ($stray !== 0) { $fail++; }

/* ⛔ **ولا شاشةَ أخرى انزاحت**: البذرُ يحمل مرجعَ هذه الشاشةِ وحدَها. */
$other = $one("SELECT COUNT(*) FROM gov_profile_items
                WHERE seeded_from = '" . SEED_TAG . "' AND item_ref <> '" . SCREEN . "'");
printf("   ★ ضابطٌ سالب — بندٌ مضافٌ على شاشةٍ أخرى: %d %s\n", $other, $other === 0 ? '' : '✘');
if ($other !== 0) { $fail++; }

/* ═══ الشاهدُ الحاكم: **القرارُ الحيُّ لا المخزن** ══════════════════════════
   «لا يُقاس سطحٌ بما في جدولِه بل بما يُخرجه مسارُ القرارِ نفسُه». يُنادى
   `ems_permission_trace` — وهي تُبدّل `$_SESSION` لحظةً ثم تُعيدها حتمًا —
   على **كلِّ مستخدمٍ حيٍّ تحكمه طبقةُ القوالب**، لا على عيّنةٍ منهم.

   ⛔ **والمقامُ هو المحكومون بالقالبِ لا كلُّ حيٍّ**: قياسُ «مستخدمٍ لكلِّ دورٍ»
     بـ`MIN(id)` أخرج DENY=1 على المستخدم #1 — وذاك **حكمٌ مسجَّلٌ لا عطبٌ**:
     هو الوحيدُ في النظامِ كلِّه بلا تغطيةِ قالبٍ نافذٍ (الشركةُ 1 المعلَّقة)،
     وقرارُ `permissions_helper.php` فيه منصوصٌ حرفًا: «وعلاجُه إسنادُ قالبٍ
     من شاشةِ الإسناد متى فُعِّلت شركتُه — لا إعادةُ بابٍ خلفيٍّ لأحد».
     فبندُ القالبِ لا يبلغه ولا يُراد له أن يبلغه؛ ودورُه 1 يحكمه في الشركةِ
     النافذةِ المستخدمُ #4 وهو مغطًّى.
   ◆ **والاستثناءُ يُسمّى ولا يُطوى**: يُعدُّ غيرُ المغطَّى ويُطبع صراحةً —
     فإخراجُ صفٍّ من المقامِ بلا ذكرِه أخضرُ كاذبٌ بالتضييق. */
$_SERVER['SCRIPT_NAME'] = '/ems/' . SCREEN;
$_SERVER['DOCUMENT_ROOT'] = $ROOT;
if (!isset($_SESSION)) { $_SESSION = array(); }
require_once $ROOT . '/includes/permissions_helper.php';
$mid = $one("SELECT id FROM modules WHERE code = '" . SCREEN . "' LIMIT 1");
$COVERED = "EXISTS (SELECT 1 FROM gov_authority_grants g
                      JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
                     WHERE g.user_id = u.id AND g.revoked_at IS NULL
                       AND (g.valid_to IS NULL OR g.valid_to > NOW()))";
$allow = 0; $deny = array();
$rs = $conn->query("SELECT u.id, u.role FROM users u
                     WHERE u.is_deleted = 0 AND u.status = 'active' AND {$COVERED}
                     ORDER BY CAST(u.role AS UNSIGNED), u.id");
while ($x = $rs->fetch_assoc()) {
    $tr = ems_permission_trace($conn, $mid, (int) $x['id']);
    if (!empty($tr['allowed'])) { $allow++; }
    else { $deny[] = 'u#' . $x['id'] . '/role' . $x['role']; }
}
$rolesN = $one("SELECT COUNT(DISTINCT u.role) FROM users u
                 WHERE u.is_deleted = 0 AND u.status = 'active' AND {$COVERED}");
printf("   ★ القرارُ الحيُّ — مستخدمٌ محكومٌ بقالبٍ نافذٍ: %d (في %d دورًا) · ALLOW %d · DENY %d %s\n",
    $allow + count($deny), $rolesN, $allow, count($deny), count($deny) === 0 ? '' : '✘');
if ($deny) { printf("     المتبقّي محجوبًا: %s\n", implode(' · ', $deny)); $fail++; }

/* ◆ **وغيرُ المغطَّى يُعدُّ ويُسمّى** — خارجَ حكمِ هذه الهجرةِ بحكمٍ سابقٍ مسجَّل. */
$unc = $conn->query("SELECT u.id, u.name, u.company_id FROM users u
                      WHERE u.is_deleted = 0 AND u.status = 'active' AND NOT {$COVERED}
                      ORDER BY u.id");
$uncList = array();
while ($x = $unc->fetch_assoc()) { $uncList[] = 'u#' . $x['id'] . ' (' . $x['name'] . ' · شركة ' . $x['company_id'] . ')'; }
printf("   ◆ خارجَ المقام — حيٌّ بلا تغطيةِ قالبٍ نافذ: %d%s\n",
    count($uncList), $uncList ? ' ⇒ ' . implode(' · ', $uncList) : '');
if ($uncList) { echo "     (حكمٌ مسجَّلٌ سابقٌ: يُسنَد له قالبٌ من شاشةِ الإسناد — لا بابَ خلفيًّا)\n"; }

/* ◆ **والفجوةُ الأوسعُ تُعلَن ولا تُنفَّذ** — قرارُ مالكٍ لا قرارُ هجرة. */
$wide = $one("SELECT COUNT(*) FROM (
    SELECT p.profile_id, m.code
      FROM gov_role_profiles p
      JOIN role_permissions rp ON rp.role_id = CAST(SUBSTRING(p.profile_code, 6) AS UNSIGNED)
      JOIN modules m ON m.id = rp.module_id
     WHERE p.state = 'active' AND p.profile_code LIKE 'TGT-R%' AND rp.can_view = 1
       AND NOT EXISTS (SELECT 1 FROM gov_profile_items i
                        WHERE i.profile_id = p.profile_id AND i.item_kind = 'screen'
                          AND i.item_ref = m.code AND i.allow = 1)
     GROUP BY p.profile_id, m.code) z");
$restoreRun = $one("SELECT COUNT(*) FROM schema_migrations
                     WHERE filename = '2028_05_21_perm01_restore_removed_screens.php'");
printf("\n   ⚠ الفجوةُ الأوسعُ الباقيةُ (قالب·شاشة): %d — وهجرتُها %s\n",
    $wide, $restoreRun > 0 ? 'مُقيَّدةٌ في الدفتر' : '**ليست في الدفتر: لم تُشغَّل**');

if ($fail > 0) { exit("\n⛔ سقط شاهدٌ ({$fail}) — لا يُعتمد.\n"); }
require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ تمّ — وبطاقةُ المرءِ عن نفسِه صارت بندًا في قالبِه لا إعفاءً في حارسِه.\n";
