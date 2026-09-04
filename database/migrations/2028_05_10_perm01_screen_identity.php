<?php
/**
 * 2028_05_10_perm01_screen_identity.php — لكلِّ بابٍ هويّةٌ واحدة
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-01 §9: «لا يبقى دورٌ يرث اتحاد قالبين» — ومعناها في طبقةِ الأبواب:
 *   **لكلِّ شاشةٍ هويّةٌ واحدةٌ يُحكَم بها**. وقِيست ثلاثُ شاشاتٍ تخالف ذلك:
 *
 *   ① `movement/movement_operations.php` كان حارسُها يقرأ **اتّحادَ** سالفَيها
 *      (`move_oprators` و`project_drivers`) ووحدتُها المسجَّلةُ لا تُستشار —
 *      فعشرةُ مستخدمين معياريّين يرون الرابطَ ويردُّهم البابُ. (أُصلح في الملف.)
 *   ② `Timesheet/view_timesheet.php` كانت مفردتُها `timesheet` تُحَلُّ إلى
 *      شاشةٍ أخرى قائمة. (أُصلح في الملف.)
 *   ③ `Equipments/equipment_profile.php` — **بلا حارسِ عرضٍ إطلاقًا**: جلسةٌ
 *      ونطاقُ شركةٍ فقط. وهذه الهجرةُ تمهّد لإغلاقِها.
 *
 * ⛔ **والإغلاقُ يسبقه إغلاقُ الوصول، وإلا كسر عملًا قائمًا**: الكرتُ يُبلَغ
 *   **بالنقرِ من قائمةِ المعدات** (سطرٌ مقيسٌ في `Equipments/equipments.php`)
 *   ولا رابطَ له في القائمة الجانبيّة — فلم يُبذَر في القوالب. وحراستُه بلا
 *   بذرٍ تُسقِط 25 مستخدمًا معياريًّا يفتحونه اليومَ.
 *
 * ◆ **والبذرُ لا يُوسِّع**: يُضاف الكرتُ لمن يملك **القائمةَ التي تقوده إليه**
 *   حصرًا — فهو تصريحٌ بما هو نافذٌ فعلًا اليومَ (الشاشةُ مفتوحةٌ بلا حارس)،
 *   لا منحٌ جديد. وبعدَ الحارسِ يصير الوصولُ **محكومًا** بعدَ أن كان مباحًا.
 * ⛔ **والحبّةُ بالقالبِ لا بالمساحة**: الإضافةُ لكلِّ `profile_id` على حدةٍ —
 *   والبذرُ بالمساحةِ هو الذي فتح 2,030 بابًا في جولةٍ سابقة.
 * ◆ **وعرضٌ فقط**: `can_edit` للكرتِ محكومٌ بـ`equipments_fleet` بنصِّ الشاشةِ
 *   («صلاحية اعتماد الكرت = صلاحية تعديل المعدات») — فلا يُمَسّ.
 *
 * التشغيل: php database/migrations/2028_05_10_perm01_screen_identity.php
 * العكس:   php database/migrations/2028_05_10_perm01_screen_identity_down.php
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

$LIST = 'Equipments/equipments.php';
$CARD = 'Equipments/equipment_profile.php';
$MARK = 'link_closure:equipments';

echo "══ ① الحالُ قبلَ البذر ═══════════════════════════════════════════════\n";
$before = $one("SELECT COUNT(*) FROM gov_role_profiles p WHERE p.state='active'
                 AND EXISTS(SELECT 1 FROM gov_profile_items i WHERE i.profile_id=p.profile_id
                              AND i.item_kind='screen' AND i.allow=1 AND i.item_ref='{$LIST}')
                 AND NOT EXISTS(SELECT 1 FROM gov_profile_items i WHERE i.profile_id=p.profile_id
                              AND i.item_kind='screen' AND i.item_ref='{$CARD}')");
printf("   قوالبُ نافذةٌ فيها القائمةُ وينقصها الكرتُ: %d\n", $before);

echo "\n══ ② البذرُ — بالقالبِ لا بالمساحة ════════════════════════════════════\n";
$conn->query("INSERT IGNORE INTO gov_profile_items
                (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
              SELECT p.company_id, p.profile_id, 'screen', '{$CARD}', 1, 0, 0, 0, '{$MARK}'
                FROM gov_role_profiles p
               WHERE p.state='active'
                 AND EXISTS(SELECT 1 FROM gov_profile_items i WHERE i.profile_id=p.profile_id
                              AND i.item_kind='screen' AND i.allow=1 AND i.item_ref='{$LIST}')
                 AND NOT EXISTS(SELECT 1 FROM gov_profile_items i WHERE i.profile_id=p.profile_id
                              AND i.item_kind='screen' AND i.item_ref='{$CARD}')");
printf("   بنودٌ أُضيفت: %d\n", $conn->affected_rows);

echo "\n══ ③ الشواهد ═════════════════════════════════════════════════════════\n";
$good = true;
$after = $one("SELECT COUNT(*) FROM gov_role_profiles p WHERE p.state='active'
                AND EXISTS(SELECT 1 FROM gov_profile_items i WHERE i.profile_id=p.profile_id
                             AND i.item_kind='screen' AND i.allow=1 AND i.item_ref='{$LIST}')
                AND NOT EXISTS(SELECT 1 FROM gov_profile_items i WHERE i.profile_id=p.profile_id
                             AND i.item_kind='screen' AND i.item_ref='{$CARD}')");
printf("   ★ بقي قالبٌ فيه القائمةُ بلا الكرتِ: %d %s\n", $after, $after === 0 ? '' : '✘');
if ($after !== 0) { $good = false; }

$seeded = $one("SELECT COUNT(*) FROM gov_profile_items WHERE seeded_from='{$MARK}'");
printf("   ★ بنودٌ موسومةٌ بمصدرِها (فالعكسُ يعرف ما يحذف): %d\n", $seeded);
if ($seeded !== $before) { $good = false; printf("      ✘ المتوقَّع %d\n", $before); }

/* ⛔ ضابطٌ سالب: لا يُبذَر الكرتُ لمن لا يملك القائمةَ — وإلا كان توسعة. */
$over = $one("SELECT COUNT(*) FROM gov_profile_items i
               WHERE i.seeded_from='{$MARK}'
                 AND NOT EXISTS(SELECT 1 FROM gov_profile_items j WHERE j.profile_id=i.profile_id
                                  AND j.item_kind='screen' AND j.allow=1 AND j.item_ref='{$LIST}')");
printf("   ★ ضابطٌ سالب — بندٌ بُذر بلا قائمةٍ تقود إليه: %d %s\n", $over, $over === 0 ? '' : '✘ توسعة');
if ($over !== 0) { $good = false; }

$w = $one("SELECT COUNT(*) FROM gov_profile_items WHERE seeded_from='{$MARK}'
            AND (can_add<>0 OR can_edit<>0 OR can_delete<>0)");
printf("   ★ ضابطٌ سالب — بندٌ بُذر بأعلامِ كتابة: %d %s\n", $w, $w === 0 ? '' : '✘');
if ($w !== 0) { $good = false; }

foreach (array(basename(__FILE__), str_replace('.php', '_down.php', basename(__FILE__))) as $f) {
    $conn->query("INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('" . $conn->real_escape_string($f) . "')");
}
echo "\n" . ($good ? "تمت — والكرت مبذور لمن يبلغه، فالحارس لا يكسر عملا.\n" : "سقط شاهد\n");
