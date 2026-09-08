<?php
/**
 * 2028_06_13 · العكس — إعادةُ جدولَي إعادةِ كلمةِ المرور ببنيتِهما حرفًا
 * ═══════════════════════════════════════════════════════════════════════════
 * البنيةُ منقولةٌ من `SHOW CREATE TABLE` **قبلَ الحذفِ** لا من ذاكرةٍ ولا من
 * `schema.sql` — بمفاتيحِها وفهارسِها وقيودِها كما كانت.
 * ◆ ولا بياناتٍ تُعاد: كان كلاهما **صفرَ صفوفٍ** لحظةَ الحذف.
 * ⚠ و`super_admin_password_resets` يشير إلى `super_admins` — فإن كان ذاك
 *   محذوفًا يومَ العكسِ فسيفشل الإنشاءُ، وهو الصحيح: لا ابنَ بلا أب.
 * التشغيل: php database/migrations/2028_06_13_drop_platform_password_resets_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                   ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');

$DDL = array(
'super_admin_password_resets' => "CREATE TABLE IF NOT EXISTS `super_admin_password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `super_admin_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_super_admin_password_resets_token_hash` (`token_hash`),
  KEY `idx_super_admin_password_resets_admin_id` (`super_admin_id`),
  CONSTRAINT `fk_super_admin_password_resets_admin` FOREIGN KEY (`super_admin_id`)
    REFERENCES `super_admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'company_user_password_resets' => "CREATE TABLE IF NOT EXISTS `company_user_password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_user_password_resets_token_hash` (`token_hash`),
  KEY `idx_company_user_password_resets_user_id` (`user_id`),
  CONSTRAINT `fk_company_user_password_resets_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
);

foreach ($DDL as $t => $sql) {
    if (!$conn->query($sql)) { fwrite(STDERR, "إنشاءُ {$t} فشل: {$conn->error}\n"); exit(1); }
    echo "  ✔ أُعيد {$t}\n";
}
echo "اكتمل العكس\n";
exit(0);
