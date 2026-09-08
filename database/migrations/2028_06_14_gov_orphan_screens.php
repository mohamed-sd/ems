<?php
/**
 * 2028_06_14 — سجلُّ الشاشاتِ اليتيمة `gov_orphan_screens`
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما هي اليتيمة**: شاشةٌ مبنيّةٌ على القرصِ **لا يبلغها أحد** — لا من
 *   سايدبارِ أيِّ دورٍ حيّ، ولا برابطٍ من شاشةٍ أخرى، وليست مكتبةً تُضمَّن.
 *   قِيست 320 من 806 شاشةً حقيقيّة (‏40٪) بمسحِ 2026-09-08.
 *
 * ◆ **ولماذا جدولٌ لا تقرير**: التقريرُ يُقرأ مرّةً ويُنسى، والجدولُ **يحمل
 *   القرارَ**: لكلِّ شاشةٍ حكمٌ (‏معلَّقٌ · تُوصَل · تُتقاعَد · وُصِلت) بمن
 *   قرَّره ومتى. فالمراجعةُ تتقدَّم ولا تُعاد من الصفرِ كلَّ جولة.
 *
 * ◆ **والمسحُ يُعاد بأداتِه** `tools/orphan_screens_scan.php` — فالجدولُ
 *   إسقاطُ قياسٍ لا قائمةٌ مكتوبةٌ بيدٍ: شاشةٌ تُوصَل تخرج منه تلقائيًّا.
 *   ⛔ **والقرارُ لا يُدهَس بإعادةِ المسح**: التحديثُ يمسُّ حقولَ القياسِ
 *     وحدَها ويترك `decision` و`decided_by` و`note` كما كتبها المالك.
 * التشغيل: php database/migrate.php up
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                   ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');

$sql = "CREATE TABLE IF NOT EXISTS `gov_orphan_screens` (
  `id`           int(10) unsigned NOT NULL AUTO_INCREMENT,
  `route`        varchar(190) NOT NULL COMMENT 'المسار بحرفه كما على القرص',
  `route_norm`   varchar(190) NOT NULL COMMENT 'مسوى: صغير وبلا لاحقة — مفتاح المطابقة',
  `owner_dept`   varchar(120) NOT NULL DEFAULT '' COMMENT 'من nav_canonical ثم gov_screen_cycle',
  `module_id`    int(11) DEFAULT NULL COMMENT 'صف modules إن وجد — شرط التصريح بالقالب',
  `module_code`  varchar(160) DEFAULT NULL,
  `title_ar`     varchar(190) NOT NULL DEFAULT '',
  `size_kb`      int(10) unsigned NOT NULL DEFAULT 0,
  `first_seen`   datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen`    datetime NOT NULL DEFAULT current_timestamp(),
  `decision`     enum('PENDING','KEEP','RETIRE','WIRED') NOT NULL DEFAULT 'PENDING'
                 COMMENT 'حكم المالك: معلق · تبقى وتوصل · تتقاعد · وصلت فعلا',
  `decided_by`   int(11) DEFAULT NULL,
  `decided_at`   datetime DEFAULT NULL,
  `note`         varchar(400) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orphan_route` (`route_norm`),
  KEY `idx_orphan_dept` (`owner_dept`),
  KEY `idx_orphan_decision` (`decision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='شاشات مبنية لا يبلغها أحد — جرد وقرار'";

if (!$conn->query($sql)) { fwrite(STDERR, "الإنشاءُ فشل: {$conn->error}\n"); exit(1); }
echo "✔ `gov_orphan_screens` جاهز\n";
echo "  المسحُ بعدَه: php tools/orphan_screens_scan.php\n";
exit(0);
