<?php
/**
 * 2028_02_02_navr_placement_model.php — نموذجُ الملاحةِ الجديد (حملة NAVR)
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: table:nav_workspaces, table:nav_ws_roles,
 *   table:nav_lifecycle_groups, table:nav_placements, table:gov_nav_findings
 *
 * ◆ أمرُ المالك «إصلاح نموذج الملاحة من الجذور»: فصلُ هويّةِ الشاشةِ عن مكانِ
 *   ظهورِها عن مجموعةِ دورتِها عن ترتيبِها عن صلاحيةِ الدور. الجذرُ المقيس:
 *   `nav_route_group` مفتاحُه `route` وحدَه — **حقيقةٌ سياقيّةٌ (لكلِّ مساحةٍ)
 *   خُزنت علاقةً عالميّةً** فحُشرت 557 مسارًا في 12 رأسَ تصنيفٍ موحَّدًا
 *   و147/162 شاشةً مُصيَّرةً وقعت في غيرِ مجموعةِ دليلِها.
 *
 * ◆ **الهجرةُ مخطَّطٌ وأحكامٌ ثابتةٌ فقط** — مواضعُ الدليلِ يكتبها المستورِدُ
 *   المراجَعُ `tools/navr_import_guide.php` (آليًّا من الورقةِ لا يدويًّا)،
 *   وربطُ الأدوارِ يكتبه بجسرِ الحملةِ الواحد `tools/lib/navr_bridge.php`.
 *
 * ◆ **أحكامُ الحالاتِ الخاصّةِ (المطلوب ٩) تُخزَّن في صفِّ المساحةِ نفسِه**
 *   عمودَ `ruling` — فمن فتح الجدولَ قرأ الحكمَ (نمطُ GAP-24).
 *
 * التشغيل: php database/migrations/2028_02_02_navr_placement_model.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);
$mk = function ($sql, $what) use ($conn) {
    if (!$conn->query($sql)) { exit("⛔ {$what} فشل: {$conn->error}\n"); }
    echo "  ✔ {$what}\n";
};

/* ── ① مساحاتُ العمل — الهويّةُ والحكم ─────────────────────────────────── */
$mk("CREATE TABLE IF NOT EXISTS `nav_workspaces` (
    `workspace_id` VARCHAR(24) NOT NULL,
    `kind` ENUM('DEPARTMENT','EXECUTIVE','PERSONAL','PLATFORM_UTILITY') NOT NULL,
    `name_ar` VARCHAR(150) NOT NULL,
    `dept_code` VARCHAR(24) NULL,
    `ruling` VARCHAR(400) NOT NULL COMMENT 'حكمُ التصنيفِ مكتوبًا في الصفِّ نفسِه — لا يُخلط مقامٌ بلا حكم',
    `source_ref` VARCHAR(190) NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='NAVR: مساحاتُ العملِ — طبقةٌ كانت حقيقةً سياقيّةً بلا مخزن'", 'nav_workspaces');

/* ── ② ربطُ الدورِ بمساحتِه ────────────────────────────────────────────── */
$mk("CREATE TABLE IF NOT EXISTS `nav_ws_roles` (
    `workspace_id` VARCHAR(24) NOT NULL,
    `role_id` INT NOT NULL,
    `binding` ENUM('PRIMARY','SECONDARY') NOT NULL DEFAULT 'PRIMARY',
    `source_ref` VARCHAR(190) NOT NULL,
    PRIMARY KEY (`workspace_id`, `role_id`),
    UNIQUE KEY `uq_role_binding` (`role_id`, `binding`),
    CONSTRAINT `fk_wsr_ws` FOREIGN KEY (`workspace_id`) REFERENCES `nav_workspaces`(`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='NAVR: مساحةُ كلِّ دور — PRIMARY واحدة'", 'nav_ws_roles');

/* ── ③ مجموعاتُ دورةِ كلِّ مساحة — من ورقتِها لا من Taxonomy عامّة ──────── */
$mk("CREATE TABLE IF NOT EXISTS `nav_lifecycle_groups` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `workspace_id` VARCHAR(24) NOT NULL,
    `group_key` VARCHAR(150) NOT NULL COMMENT 'الاسمُ المطبَّعُ (navr_gz) — مفتاحُ المطابقة',
    `label_ar` VARCHAR(150) NOT NULL COMMENT 'اسمُ العرضِ كما في الورقة',
    `sort_no` TINYINT UNSIGNED NOT NULL,
    `source_ref` VARCHAR(190) NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ws_group` (`workspace_id`, `group_key`),
    UNIQUE KEY `uq_ws_sort` (`workspace_id`, `sort_no`),
    CONSTRAINT `fk_nlg_ws` FOREIGN KEY (`workspace_id`) REFERENCES `nav_workspaces`(`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='NAVR: مجموعاتُ الدورةِ خاصّةٌ بالمساحة'", 'nav_lifecycle_groups');

/* ── ④ المواضع — الشاشةُ الواحدةُ بموضعٍ لكلِّ مساحةٍ (1:N لا 1:1) ──────── */
$mk("CREATE TABLE IF NOT EXISTS `nav_placements` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `workspace_id` VARCHAR(24) NOT NULL,
    `screen_id` VARCHAR(12) NULL COMMENT 'SCR-#### متى كانت مبنيّةً مجسورة',
    `route` VARCHAR(160) NULL COMMENT 'مسارُ الشاشةِ المبنيّة — NULL لغيرِ المبنيّ',
    `target_ref` VARCHAR(190) NOT NULL COMMENT 'هويّةُ هدفِ الدليل: code·idx·الاسم',
    `group_id` INT NOT NULL,
    `sort_no` SMALLINT UNSIGNED NOT NULL,
    `placement_type` ENUM('MENU_ITEM','TAB_CHILD','DIRECT_ONLY','PROJECTION','UTILITY','NOT_BUILT') NOT NULL,
    `source_ref` VARCHAR(190) NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ws_target` (`workspace_id`, `target_ref`),
    KEY `ix_ws_grp_sort` (`workspace_id`, `group_id`, `sort_no`),
    KEY `ix_route` (`route`),
    CONSTRAINT `fk_np_ws` FOREIGN KEY (`workspace_id`) REFERENCES `nav_workspaces`(`workspace_id`),
    CONSTRAINT `fk_np_grp` FOREIGN KEY (`group_id`) REFERENCES `nav_lifecycle_groups`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='NAVR: موضعُ الشاشةِ في مساحتِها — والصلاحياتُ طبقةٌ مستقلّةٌ لا تُخزَّن هنا'", 'nav_placements');

/* ── ⑤ كشوفُ الملاحةِ المعماريّة — الفشلُ يُرى ولا يُبتلع ─────────────── */
$mk("CREATE TABLE IF NOT EXISTS `gov_nav_findings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kind` VARCHAR(40) NOT NULL,
    `role_id` INT NULL,
    `workspace_id` VARCHAR(24) NULL,
    `detail` VARCHAR(400) NOT NULL,
    `hits` INT UNSIGNED NOT NULL DEFAULT 1,
    `first_seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_finding` (`kind`, `role_id`, `workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='NAVR: BUSINESS_WORKSPACE_GLOBAL_FALLBACK وأخواتُها — Fail-visible لا سقوطًا صامتًا'", 'gov_nav_findings');

/* ── ⑥ بذرُ المساحاتِ بأحكامِها (المطلوب ٩ — كلٌّ بحكمِه لا خلطَ مقام) ─── */
$WS = array(
    /* الإداراتُ السبعَ عشرةَ — ورقةُ دليلٍ لكلٍّ */
    array('DEP-01','DEPARTMENT','إدارة المبيعات التعاقدية والعقود','DEP-01','إدارةٌ بورقةِ دليل'),
    array('DEP-02','DEPARTMENT','إدارة الموردين','DEP-02','إدارةٌ بورقةِ دليل'),
    array('DEP-03','DEPARTMENT','إدارة التمويل والممولين','DEP-03','إدارةٌ بورقةِ دليل'),
    array('DEP-04','DEPARTMENT','إدارة الأسطول والأصول','DEP-04','إدارةٌ بورقةِ دليل'),
    array('DEP-05','DEPARTMENT','الإدارة المالية','DEP-05','إدارةٌ بورقةِ دليل'),
    array('DEP-06','DEPARTMENT','إدارة الخزينة','DEP-06','إدارةٌ بورقةِ دليل — الدورُ بإسنادٍ صريحٍ (21)'),
    array('DEP-07','DEPARTMENT','إدارة الموارد البشرية','DEP-07','إدارةٌ بورقةِ دليل'),
    array('DEP-08','DEPARTMENT','إدارة الحوكمة والالتزام','DEP-08','إدارةٌ بورقةِ دليلٍ **بلا دورٍ حيٍّ مربوط** — المواضعُ تُسجَّل الآن والربطُ متى أُنشئ الدور؛ فجوةُ دورٍ لا فجوةُ ملاحةٍ وتُقيَّد Finding باسمِها'),
    array('DEP-09','DEPARTMENT','إدارة المخاطر','DEP-09','إدارةٌ بورقةِ دليل'),
    array('DEP-10','DEPARTMENT','إدارة البلاغات','DEP-10','إدارةٌ بورقةِ دليل'),
    array('DEP-11','DEPARTMENT','إدارة التشغيل','DEP-11','إدارةٌ بورقةِ دليل — سابقةُ RPR-OPS-11 أثبتت إمكانَ التعميم'),
    array('DEP-12','DEPARTMENT','إدارة الموقع','DEP-12','إدارةٌ بورقةِ دليل'),
    array('DEP-13','DEPARTMENT','إدارة القوى التشغيلية','DEP-13','إدارةٌ بورقةِ دليل'),
    array('DEP-14','DEPARTMENT','إدارة الصيانة','DEP-14','إدارةٌ بورقةِ دليل'),
    array('DEP-15','DEPARTMENT','إدارة النقل والترحيل','DEP-15','إدارةٌ بورقةِ دليل'),
    array('DEP-16','DEPARTMENT','إدارة المشتريات التشغيلية','DEP-16','إدارةٌ بورقةِ دليل'),
    array('DEP-17','DEPARTMENT','إدارة المخازن','DEP-17','إدارةٌ بورقةِ دليل — الدورُ بإسنادٍ صريحٍ (25)'),
    array('IAF','DEPARTMENT','المراجعة الداخلية','IAF','إدارةٌ بورقةِ دليل — الدورُ بإسنادٍ صريحٍ (33)'),
    /* غيرُ الإداريّ — كلٌّ بحكمِه ولا يُخلط في مقامِ الإدارات */
    array('WS-MY','PERSONAL','مساحتي','', 'مساحةٌ شخصيّةٌ: بنودُها تُحقن داخلَ كلِّ دورٍ — **ليست إدارةً** ولا تدخل مقامَ تطابقِ الإدارات'),
    array('EX-CEO','EXECUTIVE','مساحة الرئيس التنفيذي','', 'مساحةٌ تنفيذيّةٌ (الدور 9) — دورتُها تنفيذيّةٌ لا إداريّةٌ ولها ورقةُ دليل؟ لا — بنودُها بأحكامِ EX'),
    array('EX-DVP','EXECUTIVE','مساحة نائب الرئيس','', 'مساحةٌ تنفيذيّةٌ **بلا ورقةِ دليلٍ** (NO_SPEC) — مواضعُها تُحكم متى صدرت ورقتُها؛ ولا تُعَدُّ غيابَ تطابق'),
    array('WS-PLATFORM','PLATFORM_UTILITY','أدوات المنصة المشتركة','', 'أسطحُ PLATFORM_SHARED تُصيَّر أدواتٍ خارجَ الدورةِ بإعلانِها (حكم T13) — لا تُحشر في دورةِ إدارة'),
);
$ins = $conn->prepare("INSERT INTO `nav_workspaces` (`workspace_id`,`kind`,`name_ar`,`dept_code`,`ruling`,`source_ref`)
                       VALUES (?,?,?,NULLIF(?,''),?, 'NAVR·أمر المالك 2026-08-31 + NAVR_ROOT_AUDIT.md §⑤')
                       ON DUPLICATE KEY UPDATE `ruling` = VALUES(`ruling`)");
$n = 0;
foreach ($WS as $w) {
    $ins->bind_param('sssss', $w[0], $w[1], $w[2], $w[3], $w[4]);
    if ($ins->execute()) { $n++; }
}
echo "  ✔ بُذرت المساحاتُ بأحكامِها: {$n}/" . count($WS) . "\n";

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "✔ اكتملت وقُيّدت ذاتيًّا\n";
