<?php
/**
 * 2028_05_08_perm01_change_log.php — سجلُّ أثرِ تغييرِ الصلاحية (PERM-01 §7-⑤ · م-6)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السؤالُ الذي بلا جوابٍ اليوم**: «من فتح هذه الشاشةَ لهذا الدورِ ومتى
 *   ولماذا؟». الجدولانِ الحاكمانِ بلا عمودِ مُحدِّثٍ ولا وقتِ تحديث، والشاشاتُ
 *   تكتب بلا سطرِ تسجيل. والمراجعةُ الداخليّةُ والامتثالُ يحتاجان أثرًا للتغيير.
 *
 * ⛔ **ولا يُبنى على `permission_audit_events`**: تلك من الطبقةِ الثالثةِ غيرِ
 *   الموصولةِ بقرارِ فتحِ الشاشة، والبناءُ عليها يوسّع الالتباسَ لا يغلقه.
 *
 * ◆ **والحبّةُ واقعةُ تغييرٍ واحدة**: مَن · متى · أيُّ طبقة · على مَن · أيُّ شاشة ·
 *   ما قبل ⇐ ما بعد · لماذا · من أيِّ منفذ. فسطرٌ واحدٌ يجيب السؤالَ كلَّه.
 *
 * ◆ **وقبل/بعد نصٌّ لا مؤشِّر**: قيمةُ الأعلامِ تُكتب كما كانت — فحذفُ الصفِّ
 *   لاحقًا لا يُفقد ما كان عليه.
 *
 * التشغيل: php database/migrations/2028_05_08_perm01_change_log.php
 * العكس:   php database/migrations/2028_05_08_perm01_change_log_down.php
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

echo "══ ① الجدول ═══════════════════════════════════════════════════════════\n";
$ok = $conn->query("CREATE TABLE IF NOT EXISTS `perm_change_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `changed_at` DATETIME NOT NULL DEFAULT current_timestamp(),
    `actor_user_id` INT NOT NULL DEFAULT 0 COMMENT 'من غير — صفر يعني هجرة او اداة',
    `actor_role` VARCHAR(16) NOT NULL DEFAULT '',
    `layer` VARCHAR(24) NOT NULL COMMENT 'role_permissions | profile_item | grant | profile_state',
    `verb` VARCHAR(16) NOT NULL COMMENT 'insert | update | delete | revoke | activate | retire',
    `subject_kind` VARCHAR(16) NOT NULL DEFAULT '' COMMENT 'role | user | profile',
    `subject_id` INT NOT NULL DEFAULT 0,
    `screen_code` VARCHAR(160) NOT NULL DEFAULT '',
    `before_val` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'ما كان — نص لا مؤشر',
    `after_val` VARCHAR(255) NOT NULL DEFAULT '',
    `reason` VARCHAR(255) NOT NULL DEFAULT '',
    `source_screen` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'من اي منفذ وقع',
    PRIMARY KEY (`id`),
    KEY `ix_subject` (`subject_kind`, `subject_id`),
    KEY `ix_screen` (`screen_code`),
    KEY `ix_when` (`changed_at`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='PERM-01 §7-5 — من غير الصلاحية ومتى ولماذا'");
echo '   perm_change_log: ' . ($ok ? 'قائم' : $conn->error) . "\n";

echo "\n══ ② الشواهد ══════════════════════════════════════════════════════════\n";
$good = true;
$exists = (int) $one("SELECT COUNT(*) FROM information_schema.TABLES
                       WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='perm_change_log'");
printf("   الجدولُ موجود: %d\n", $exists);
if ($exists !== 1) { $good = false; }

/* شاهدُ كتابةٍ وقراءةٍ — جدولٌ لا يُكتب فيه ليس سجلًّا. */
$conn->query("INSERT INTO perm_change_log
   (company_id, actor_user_id, actor_role, layer, verb, subject_kind, subject_id,
    screen_code, before_val, after_val, reason, source_screen)
  VALUES (4, 0, 'migration', 'profile_state', 'activate', 'profile', 0,
          '', 'n/a', 'n/a', 'شاهد انشاء السجل — يحذف فورا', '2028_05_08')");
$probe = (int) $one("SELECT COUNT(*) FROM perm_change_log WHERE source_screen='2028_05_08'");
printf("   شاهدُ كتابةٍ وقراءة: %s\n", $probe === 1 ? 'يُكتب ويُقرأ' : 'تعذّر');
if ($probe !== 1) { $good = false; }
$conn->query("DELETE FROM perm_change_log WHERE source_screen='2028_05_08'");
printf("   ونُظِّف الشاهد: %d\n", $conn->affected_rows);

/* ضابطٌ موجب: لم يُمَسَّ حكمٌ ولا منحة. */
$cov = (int) $one("SELECT COUNT(*) FROM users u WHERE u.is_deleted=0 AND u.status='active' AND u.company_id=4
                    AND EXISTS(SELECT 1 FROM gov_authority_grants g
                                 JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
                                WHERE g.user_id=u.id AND g.revoked_at IS NULL
                                  AND (g.valid_to IS NULL OR g.valid_to>NOW()))");
printf("   ضابطٌ موجب: المغطَّون %d (المتوقَّع 75) %s\n", $cov, $cov === 75 ? '' : 'انحرف');
if ($cov !== 75) { $good = false; }

foreach (array(basename(__FILE__), str_replace('.php', '_down.php', basename(__FILE__))) as $f) {
    $conn->query("INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('" . $conn->real_escape_string($f) . "')");
}
echo "\n" . ($good ? "تمت — السجل قائم ويكتب. والوصل في includes/perm_change_log.php\n" : "سقط شاهد\n");
