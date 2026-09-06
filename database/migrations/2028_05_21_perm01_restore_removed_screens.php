<?php
/**
 * 2028_05_21_perm01_restore_removed_screens.php — إكمالُ قوالبِ الأدوارِ بما سُحب بلا قرار
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **المشكلةُ التي يعالجها**: التحوّلُ الكاملُ (2026-09-04 15:47–16:30 · 77 منحةً)
 *   جعل 75/75 مستخدمًا `canonical` محكومين بقوالبِ `TGT-R<الدور>` وحدَها. والقوالبُ
 *   وُلِّدت من **سجلِّ الهدف** لا من المبنيّ، فسقط **1,147 زوجَ (دور·شاشة)**
 *   في 30 دورًا من 32 — و**`allow=0` = صفر من 6,927**: أي أنّ كلَّ الفقدِ
 *   **غيابُ بندٍ لا قرارُ منع**. مقيسٌ حيًّا: مشرفُ المبيعات 43 عرضًا ⇐ 16،
 *   و27 سطحًا انقلب `PASS ⇒ DENY`.
 *
 * ◆ **والإعادةُ من `role_permissions` لا من سجلِّ الهدف**: الهدفُ هو الذي أخطأ،
 *   فمصدرُ الحقيقةِ هنا **ما كان يحكم فعلًا قبلَ التحوّل**. ولا يُعاد إلّا ما
 *   كان `can_view=1` — فلا توسعةَ فوقَ القديم.
 *
 * ◆ **وأعلامُ الكتابةِ تُنقَل مع البند**: `can_add`/`can_edit`/`can_delete` تُؤخذ
 *   من `role_permissions` نفسِه بـ`MAX()` — فلا يُمنح فعلٌ لم يكن ممنوحًا،
 *   ولا يُسحب فعلٌ كان ممنوحًا. (ومعيارُ القبول ㉝ يقيس تكافؤَ الأعلام.)
 *
 * ⛔ **و`modules.code` غيرُ فريدٍ (18 مكرَّرًا)**: فالتجميعُ **بالكودِ** لا بصفِّ
 *   الوحدة، وإلّا انتفخ العددُ وكرّرت البنودُ. (`uq_item` يحمي، والعدُّ يكذب.)
 *
 * ◆ **ولا يمسُّ منحةً ولا يُفعِّل قالبًا**: تجميدا `grants` و`profile_activation`
 *   نافذانِ ولا يُرفعان هنا — هذه الهجرةُ تكتب **بنودَ قوالبَ قائمةٍ نافذةٍ** فقط.
 *
 * الوثيقة: docs/perm_study/PERM01_CUTOVER_ACCESS_LOSS_PROBLEM.md (الخيار «أ»)
 * التشغيل: php database/migrations/2028_05_21_perm01_restore_removed_screens.php
 * العكس:   php database/migrations/2028_05_21_perm01_restore_removed_screens_down.php
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

const SEED_TAG = 'perm01_restore_20260905';

/* ــــ الفجوةُ قبلَ العمل: تُقاس بالمقياسِ نفسِه الذي أنتج الرقمَ 1,147 ــــ */
$gapSql = "SELECT COUNT(*) FROM (
    SELECT p.profile_id, m.code
      FROM gov_role_profiles p
      JOIN role_permissions rp ON rp.role_id = CAST(SUBSTRING(p.profile_code, 6) AS UNSIGNED)
      JOIN modules m ON m.id = rp.module_id
     WHERE p.state = 'active' AND p.profile_code LIKE 'TGT-R%' AND rp.can_view = 1
       AND NOT EXISTS (SELECT 1 FROM gov_profile_items i
                        WHERE i.profile_id = p.profile_id AND i.item_kind = 'screen'
                          AND i.item_ref = m.code AND i.allow = 1)
     GROUP BY p.profile_id, m.code) z";

echo "══ ① الفجوةُ قبلَ الإكمال ═════════════════════════════════════════════\n";
$before = $one($gapSql);
$itemsBefore = $one("SELECT COUNT(*) FROM gov_profile_items WHERE item_kind = 'screen'");
printf("   بنودٌ ناقصةٌ (قالب·شاشة): %d\n", $before);
printf("   بنودُ الشاشاتِ الآن:      %d\n", $itemsBefore);

/* ⛔ **والسلامةُ أوّلًا**: قالبٌ لا يقابله دورٌ في `roles` لا يُكتب فيه شيء. */
$orphan = $one("SELECT COUNT(*) FROM gov_role_profiles p
                 WHERE p.state='active' AND p.profile_code LIKE 'TGT-R%'
                   AND NOT EXISTS (SELECT 1 FROM roles r
                                    WHERE r.id = CAST(SUBSTRING(p.profile_code, 6) AS UNSIGNED))");
printf("   قوالبُ `TGT-R*` بلا دورٍ مقابل: %d %s\n", $orphan, $orphan === 0 ? '' : '(تُتخطّى)');

echo "\n══ ② الإكمال ═════════════════════════════════════════════════════════\n";
/* ◆ **الإدراجُ بجملةٍ واحدةٍ من المصدرِ الحاكم** — والتجميعُ بالكودِ لا بالوحدة.
     و`INSERT IGNORE` على `uq_item` يجعلها **قابلةً لإعادةِ التشغيلِ** بلا أثر. */
$ins = "INSERT IGNORE INTO gov_profile_items
          (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
        SELECT 0, p.profile_id, 'screen', m.code, 1,
               MAX(rp.can_add), MAX(rp.can_edit), MAX(rp.can_delete),
               CONCAT('" . SEED_TAG . ":role_permissions:', CAST(SUBSTRING(p.profile_code, 6) AS UNSIGNED))
          FROM gov_role_profiles p
          JOIN roles r  ON r.id = CAST(SUBSTRING(p.profile_code, 6) AS UNSIGNED)
          JOIN role_permissions rp ON rp.role_id = r.id
          JOIN modules m ON m.id = rp.module_id
         WHERE p.state = 'active' AND p.profile_code LIKE 'TGT-R%' AND rp.can_view = 1
           AND NOT EXISTS (SELECT 1 FROM gov_profile_items i
                            WHERE i.profile_id = p.profile_id AND i.item_kind = 'screen'
                              AND i.item_ref = m.code AND i.allow = 1)
         GROUP BY p.profile_id, m.code";
if (!$conn->query($ins)) { exit("✘ فشل الإدراج: " . $conn->error . "\n"); }
$added = $conn->affected_rows;
printf("   بنودٌ أُضيفت: %d\n", $added);

echo "\n══ ③ الشواهد ═════════════════════════════════════════════════════════\n";
$good = true;

$after = $one($gapSql);
printf("   ★ الفجوةُ بعدَ الإكمال: %d %s\n", $after, $after === 0 ? '' : '✘');
if ($after !== 0) { $good = false; }

/* ⛔ **ضابطٌ سالبٌ — لا توسعةَ فوقَ القديم**: كلُّ بندٍ مضافٍ يجب أن يقابله
     `can_view=1` في `role_permissions` لدورِ القالب. صفرٌ أو فشل. */
$over = $one("SELECT COUNT(*) FROM gov_profile_items i
               JOIN gov_role_profiles p ON p.profile_id = i.profile_id
              WHERE i.seeded_from LIKE '" . SEED_TAG . ":%'
                AND NOT EXISTS (SELECT 1 FROM role_permissions rp
                                  JOIN modules m ON m.id = rp.module_id
                                 WHERE rp.role_id = CAST(SUBSTRING(p.profile_code, 6) AS UNSIGNED)
                                   AND m.code = i.item_ref AND rp.can_view = 1)");
printf("   ★ ضابطٌ سالب — بندٌ مضافٌ بلا سندٍ في الجدولِ القديم: %d %s\n", $over, $over === 0 ? '' : '✘');
if ($over !== 0) { $good = false; }

/* ⛔ **وضابطُ أعلامِ الكتابة**: لا عَلَمَ كتابةٍ في بندٍ مضافٍ يتجاوز نظيرَه. */
$flagUp = $one("SELECT COUNT(*) FROM gov_profile_items i
                 JOIN gov_role_profiles p ON p.profile_id = i.profile_id
                WHERE i.seeded_from LIKE '" . SEED_TAG . ":%'
                  AND (i.can_add + i.can_edit + i.can_delete) > 0
                  AND NOT EXISTS (SELECT 1 FROM role_permissions rp
                                    JOIN modules m ON m.id = rp.module_id
                                   WHERE rp.role_id = CAST(SUBSTRING(p.profile_code, 6) AS UNSIGNED)
                                     AND m.code = i.item_ref
                                     AND rp.can_add >= i.can_add
                                     AND rp.can_edit >= i.can_edit
                                     AND rp.can_delete >= i.can_delete)");
printf("   ★ ضابطٌ سالب — عَلَمُ كتابةٍ يتجاوز نظيرَه: %d %s\n", $flagUp, $flagUp === 0 ? '' : '✘');
if ($flagUp !== 0) { $good = false; }

/* ◆ **ولا بندَ مضافٍ بلا بيانِ مصدر** — البذرُ مسمًّى ليُنزَع بالعكسِ وحدَه. */
$noSrc = $one("SELECT COUNT(*) FROM gov_profile_items
                WHERE seeded_from LIKE '" . SEED_TAG . ":%' AND item_ref = ''");
printf("   ★ ضابطٌ سالب — بندٌ بلا مرجعِ شاشة: %d %s\n", $noSrc, $noSrc === 0 ? '' : '✘');
if ($noSrc !== 0) { $good = false; }

/* ◆ **والمنعُ الصريحُ لم يُدهَس**: لا صفَّ `allow=0` قُلب. */
$deny = $one("SELECT COUNT(*) FROM gov_profile_items WHERE item_kind='screen' AND allow = 0");
printf("   ◆ بنودُ منعٍ صريحٍ (لم تُمَسّ): %d\n", $deny);

$itemsAfter = $one("SELECT COUNT(*) FROM gov_profile_items WHERE item_kind = 'screen'");
printf("   ◆ بنودُ الشاشاتِ الآن: %d (كانت %d)\n", $itemsAfter, $itemsBefore);

/* ◆ **وأثرُ الإكمالِ على أوسعِ الأدوارِ تضرُّرًا** — للقراءةِ لا للحكم. */
echo "\n   أثرُ الإكمالِ على الأدوارِ الأكثرِ تضرُّرًا:\n";
$r = $conn->query("SELECT p.profile_code,
                          (SELECT COUNT(DISTINCT i.item_ref) FROM gov_profile_items i
                            WHERE i.profile_id = p.profile_id AND i.item_kind='screen' AND i.allow=1) now_n,
                          (SELECT COUNT(DISTINCT m.code) FROM role_permissions rp JOIN modules m ON m.id=rp.module_id
                            WHERE rp.role_id = CAST(SUBSTRING(p.profile_code,6) AS UNSIGNED) AND rp.can_view=1) legacy_n
                     FROM gov_role_profiles p
                    WHERE p.state='active' AND p.profile_code IN ('TGT-R12','TGT-R15','TGT-R2','TGT-R9','TGT-R6')");
while ($x = $r->fetch_assoc()) {
    printf("     %-9s القالبُ الآن %-4s · القديمُ %-4s\n", $x['profile_code'], $x['now_n'], $x['legacy_n']);
}

foreach (array(basename(__FILE__), str_replace('.php', '_down.php', basename(__FILE__))) as $f) {
    $conn->query("INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('" . $conn->real_escape_string($f) . "')");
}
echo "\n" . ($good
    ? "تمت — والقوالبُ صارت تحوي ما كان يحكم فعلًا، بلا توسعةٍ فوقَه.\n"
      . "◆ والقياسُ الحيُّ شرطُ الإغلاق: php tests/dept_suite/run.php --quick\n"
    : "✘ سقط شاهد — راجعْ قبلَ الاعتماد.\n");
