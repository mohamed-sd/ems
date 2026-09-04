<?php
/**
 * 2028_05_09_perm01_auth_modes.php — أوضاعُ الانتقال: ولا سقوطَ بعدَ التحوّل
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-01 §6-② نصًّا: «لكلِّ مستخدمٍ وضعٌ **معلَن**، ولا يُترك الانتقالُ ضمنيًّا:
 *   قديم · ظلٌّ معياريّ · معياريٌّ منفَّذ. **ومن بلغ «معياريٌّ منفَّذ» لا يعود
 *   إلى الجدولِ القديمِ مهما كان السبب: فإن فُقد قالبُه أو تعذّرت قراءةُ مخزنِ
 *   السياسةِ فالحكمُ منعٌ صريحٌ بإنذارٍ مسجَّل — لا سقوطٌ إلى القديم**. وإلا
 *   تحوّل عطبٌ تقنيٌّ في النظامِ الجديدِ إلى توسعةِ صلاحياتٍ من النظامِ القديم».
 *
 * ⛔ **والعطبُ مقيسٌ لا متوقَّع**: شاهدُ السحبِ أثبته حيًّا — سُحبت منحةُ مستخدمٍ
 *   مغطًّى فصار غيرَ مغطًّى، فسقط إلى `role_permissions` **وبقيت الشاشةُ مفتوحة**.
 *   فالسحبُ لا يسري، والتغطيةُ التامّةُ لا تكفي وحدَها: يلزم **وضعٌ مُعلَن**
 *   يقول «هذا الفاعلُ معياريٌّ» فيمنعَ سقوطَه أيًّا كان سببُ فقدِ قالبِه.
 *
 * ◆ **والوضعُ يُبذَر من الواقعِ المقيسِ لا من رأي**: كلُّ مستخدمٍ حيٍّ مغطًّى
 *   بقالبٍ نافذٍ اليومَ ⇒ `canonical`. ومن لا قالبَ له ⇒ `legacy`.
 *
 * التشغيل: php database/migrations/2028_05_09_perm01_auth_modes.php
 * العكس:   php database/migrations/2028_05_09_perm01_auth_modes_down.php
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
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };
$LIVE = "u.is_deleted = 0 AND u.status = 'active' AND u.company_id = 4";

echo "══ ① سجلُّ الأوضاع ═════════════════════════════════════════════════════\n";
$ok = $conn->query("CREATE TABLE IF NOT EXISTS `perm01_auth_mode` (
    `user_id` INT NOT NULL,
    `mode` ENUM('legacy','shadow','canonical') NOT NULL DEFAULT 'legacy'
        COMMENT 'قديم | ظل معياري يقاس ولا ينفذ | معياري منفذ لا يسقط',
    `set_at` DATETIME NOT NULL DEFAULT current_timestamp(),
    `set_by` INT NOT NULL DEFAULT 0,
    `reason` VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (`user_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='PERM-01 §6-2 — وضع كل مستخدم معلن لا ضمني'");
echo '   perm01_auth_mode: ' . ($ok ? 'قائم' : $conn->error) . "\n";

echo "\n══ ② البذرُ من الواقعِ المقيس ══════════════════════════════════════════\n";
$conn->query("INSERT INTO perm01_auth_mode (user_id, mode, set_by, reason)
              SELECT u.id, 'canonical', 0, 'تحول كامل PERM-01 §6 — مغطى بقالب نافذ عند البذر'
                FROM users u
               WHERE {$LIVE} AND EXISTS(
                     SELECT 1 FROM gov_authority_grants g
                       JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
                      WHERE g.user_id = u.id AND g.revoked_at IS NULL
                        AND (g.valid_to IS NULL OR g.valid_to > NOW()))
              ON DUPLICATE KEY UPDATE mode = VALUES(mode)");
printf("   معياريٌّ منفَّذ: %d\n", (int) $one("SELECT COUNT(*) FROM perm01_auth_mode WHERE mode='canonical'"));
$conn->query("INSERT IGNORE INTO perm01_auth_mode (user_id, mode, set_by, reason)
              SELECT u.id, 'legacy', 0, 'بلا قالب نافذ عند البذر' FROM users u WHERE {$LIVE}");
printf("   قديم: %d\n", (int) $one("SELECT COUNT(*) FROM perm01_auth_mode WHERE mode='legacy'"));

echo "\n══ ③ الشواهد ══════════════════════════════════════════════════════════\n";
$good = true;
$live = (int) $one("SELECT COUNT(*) FROM users u WHERE {$LIVE}");
$modes = (int) $one("SELECT COUNT(*) FROM perm01_auth_mode m JOIN users u ON u.id=m.user_id WHERE {$LIVE}");
printf("   لكلِّ مستخدمٍ حيٍّ وضعٌ مُعلَن: %d من %d %s\n", $modes, $live, $modes === $live ? '' : 'ناقص');
if ($modes !== $live) { $good = false; }

$ambiguous = (int) $one("SELECT COUNT(*) FROM users u LEFT JOIN perm01_auth_mode m ON m.user_id=u.id
                          WHERE {$LIVE} AND m.user_id IS NULL");
printf("   مستخدمٌ حيٌّ بوضعٍ ملتبس: %d\n", $ambiguous);
if ($ambiguous !== 0) { $good = false; }

$canon = (int) $one("SELECT COUNT(*) FROM perm01_auth_mode WHERE mode='canonical'");
printf("   ضابطٌ موجب: المعياريّون %d (المتوقَّع 75) %s\n", $canon, $canon === 75 ? '' : 'انحرف');
if ($canon !== 75) { $good = false; }

foreach (array(basename(__FILE__), str_replace('.php', '_down.php', basename(__FILE__))) as $f) {
    $conn->query("INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('" . $conn->real_escape_string($f) . "')");
}
echo "\n" . ($good ? "تمت — ولكل مستخدم وضع معلن. والانفاذ في get_module_permissions.\n" : "سقط شاهد\n");
