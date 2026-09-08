<?php
/**
 * 2028_06_12 · العكس — إعادةُ `_trg_probe` ببنيتِه الأصليّةِ حرفًا
 * ═══════════════════════════════════════════════════════════════════════════
 * البنيةُ منقولةٌ من `SHOW CREATE TABLE` قبلَ الحذفِ لا من ذاكرة:
 *   `id` int(11) NOT NULL · PRIMARY KEY (`id`) · InnoDB · utf8mb4_unicode_ci
 * ◆ ولا بياناتٍ تُعاد — كان **صفرَ صفوفٍ** لحظةَ الحذف.
 * التشغيل: php database/migrations/2028_06_12_drop_trg_probe_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                   ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');

if (!$conn->query("CREATE TABLE IF NOT EXISTS `_trg_probe` (
  `id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")) {
    fwrite(STDERR, "الإنشاءُ فشل: {$conn->error}\n"); exit(1);
}
echo "أُعيد `_trg_probe` ببنيتِه الأصليّة\n";
exit(0);
