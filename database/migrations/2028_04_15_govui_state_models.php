<?php
/**
 * 2028_04_15_govui_state_models.php — سجلُّ آلاتِ الحالةِ الحاكمُ (‏GOV_UI_EXEC §11·§14)
 * @migration-objects: gov_state_models, gov_state_model_bind
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **§14 حرفًا**: «راجع State Machine وApproval وSoD وEvents» — و**المصدرُ
 *   الذرّيُّ** ورقةُ `08_آلات_الحالة` في الموجاتِ الخمسِ (‏04·05·06·07·08):
 *   **62 آلةً معرَّفةً** بسبعةِ أعمدةٍ لكلٍّ (‏الوحدة · الكيان · الحالاتُ
 *   والانتقالاتُ المسموحة · الممنوعة · مَن يملك الانتقال · الشروط ·
 *   إعادةُ الفتحِ والإلغاء).
 *
 * ◆ **ولماذا سجلٌّ لا عمودٌ نصّيّ**: `repair01_screen_registry.state_model_ref`
 *   خانةُ نصٍّ حرّةٍ، وفيها اليومَ مراجعُ من جولاتٍ سابقةٍ بصيغٍ مختلفة
 *   (`FC_STATES#…` · `W8_STATES#…`). **ومرجعٌ أجوفٌ يُقرأ خُضرةً وهو فراغ** —
 *   والمحظورُ ④ في §5 نصَّ عليه. فالسجلُّ يحمل **الحقولَ الثمانيةَ نفسَها**
 *   ويُربَط بالسطحِ بقاعدةٍ مكتوبةٍ وشاهد.
 *
 * ◆ **و`scr_state_machines` القائمُ ليس مصدرًا**: عشرون صفًّا بذرةَ عرضٍ
 *   قيمُها «تشغيلي 4» و«مباشر 1» — ⛔ فلا يُبنى عليه حكم.
 *
 * ◆ **والاعتمادُ والأثرُ محرّكانِ قائمان** (‏لا يُعاد بناؤهما): محرّكُ الاعتمادِ
 *   المركزيُّ و`EventPublisher` — فيُشار إليهما لا يُنسخان.
 *
 * ⛔ إضافةٌ محضةٌ — ولا يُمَسُّ جدولٌ قائم. والعكسُ في `_down.php`.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
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
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$sql = "CREATE TABLE IF NOT EXISTS `gov_state_models` (
  `model_code`   VARCHAR(48)  NOT NULL COMMENT 'SM-<موجة>-<كيان> — ثابتٌ لا يتغيّر بإعادةِ تسمية',
  `wave`         VARCHAR(8)   NOT NULL COMMENT '04..08',
  `unit_ar`      VARCHAR(190) NOT NULL COMMENT 'عمود «الوحدة» — الإدارةُ المالكة',
  `workspace_id` VARCHAR(24)  NULL DEFAULT NULL COMMENT 'المساحةُ بعد الجسر',
  `entity_code`  VARCHAR(64)  NOT NULL COMMENT 'الشقُّ اللاتينيُّ من عمود «الكيان»',
  `entity_ar`    VARCHAR(190) NOT NULL COMMENT 'عمود «الكيان» كاملًا',
  `states_flow`  TEXT         NOT NULL COMMENT '① الحالاتُ والانتقالاتُ المسموحة',
  `forbidden`    TEXT         NOT NULL COMMENT '② الانتقالاتُ الممنوعةُ ومُسبِّباتُها',
  `transition_owner` TEXT     NOT NULL COMMENT '③ مَن يملك الانتقال — فصلُ الواجبات',
  `preconditions`    TEXT     NOT NULL COMMENT '④ الشرطُ المسبقُ لكلِّ انتقال',
  `reopen_cancel`    TEXT     NOT NULL COMMENT '⑤ إعادةُ الفتحِ/الإلغاء',
  `approval_gate`    VARCHAR(190) NOT NULL DEFAULT 'محرّكُ الاعتمادِ المركزيّ'
                     COMMENT '⑥ بوّابةُ الاعتماد — محرّكٌ قائمٌ يُشار إليه لا يُنسَخ',
  `audit_channel`    VARCHAR(190) NOT NULL DEFAULT 'EventPublisher · ems_business_events'
                     COMMENT '⑦ الأثرُ والتدقيق — قناةٌ قائمة',
  `state_count`  SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'عددُ الحالاتِ المستخرَجة',
  `source_file`  VARCHAR(190) NOT NULL,
  `source_sheet` VARCHAR(64)  NOT NULL,
  `source_row`   SMALLINT UNSIGNED NOT NULL,
  `version`      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at`   DATETIME     NOT NULL,
  PRIMARY KEY (`model_code`),
  KEY `ix_entity` (`entity_code`),
  KEY `ix_ws` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'GOV_UI_EXEC §14 — آلاتُ الحالةِ كما تُعرِّفها الملفاتُ الحاكمةُ وحدَها'";
echo $conn->query($sql) ? "+ جدول gov_state_models\n" : "x gov_state_models: " . $conn->error . "\n";

$sql = "CREATE TABLE IF NOT EXISTS `gov_state_model_bind` (
  `bind_id`     VARCHAR(28) NOT NULL,
  `model_code`  VARCHAR(48) NOT NULL,
  `screen_id`   VARCHAR(24) NOT NULL,
  `route`       VARCHAR(190) NOT NULL,
  `workspace_id` VARCHAR(24) NULL DEFAULT NULL,
  `grain_entity` VARCHAR(64) NOT NULL,
  `bind_rule`   VARCHAR(190) NOT NULL COMMENT 'قاعدةُ الربطِ — ⛔ ولا رَبطَ بالاسمِ وحدَه (§14)',
  `bind_witness` VARCHAR(255) NOT NULL COMMENT 'الشاهدُ المقيس',
  `confidence`  ENUM('EXACT_ENTITY','ENTITY_ALIAS','ROUTE_TOKEN','OWNER_UNIT')
                NOT NULL COMMENT 'كيف طُوبِق — والأضعفُ يُعلَن لا يُخفى',
  `created_at`  DATETIME NOT NULL,
  PRIMARY KEY (`bind_id`),
  UNIQUE KEY `uq_screen` (`screen_id`),
  KEY `ix_model` (`model_code`),
  KEY `ix_route` (`route`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'GOV_UI_EXEC §14 — ربطُ السطحِ بآلتِه بقاعدةٍ وشاهدٍ ودرجةِ ثقة'";
echo $conn->query($sql) ? "+ جدول gov_state_model_bind\n" : "x gov_state_model_bind: " . $conn->error . "\n";

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
