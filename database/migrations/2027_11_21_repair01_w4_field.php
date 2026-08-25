<?php
/**
 * 2027_11_21_repair01_w4_field.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W04 — **الحقيقةُ الميدانية**: اليومُ الميدانيُّ والوردية والقيدُ
 * اليوميُّ وواقعةُ التوقّف.
 *
 * ◆ **لماذا `site_day` كيانٌ لا عمودُ تاريخ**: المقيسُ أنَّ النظامَ يملك
 *   `daily_plans` (خطّةُ الغدِ للمشروع) و`scr_shift_log` (سجلُّ الورديةِ بأعمدةٍ
 *   نصّيّةٍ كلُّها) — ولا كيانَ واحدًا حبّتُه **موقعٌ × يوم**. فبلا كيانٍ لليومِ
 *   لا مالكَ للإقفال، وبلا مالكٍ للإقفالِ **لا معنى لرفضِ قيدٍ بعدَه**: الرفضُ
 *   يحتاج حالةً يقرؤها، والحالةُ تحتاج صفًّا يحملها.
 *
 * ◆ **ولماذا `ops_stop_register` سجلٌّ واحدٌ لا عمودٌ ثالث**: المقيسُ أنَّ
 *   واقعةَ التوقّفِ الواحدةَ تُكتب مرّتين — مرّةً سطرًا في `unit_time_log`
 *   بحالةٍ تشغيليّةٍ ومسؤولٍ وقابليّةِ فوترة، ومرّةً ساعاتٍ في أعمدةِ
 *   `timesheet` (`hr_fault` · `maintenance_fault` · `ts_supplier_stop_hours` …).
 *   **تسعُ وقائعَ مقيسةٍ حيًّا** على حبّةِ (كيان × يوم × وردية × معدة) تحمل
 *   التسجيلَين معًا. والعلاجُ ليس دهسَ أحدِهما — الدهسُ يمحو الواقعةَ قبل أن
 *   تُراجَع (W3-D-04) — بل **مفتاحُ واقعةٍ واحدٌ** (`occurrence_key`) بفهرسٍ
 *   فريدٍ يدّعيه السجلّان، فيصير أحدُهما حاكمًا والآخرُ **قراءةً مرآةً**
 *   بفارقِها المسجَّل. فالعطالةُ **بمفتاحٍ** لا بفحصِ رصيد (§٤-٣).
 *
 * ◆ **و`unit_entries.field_kind` تصنيفٌ لا استثناء**: خمسةُ صفوفٍ بلا وردية
 *   وبلا معدةٍ وبلا مُدخِلٍ وتواريخُها 2091..2094 — إسقاطُ التزامٍ تعاقديٍّ لا
 *   قيدٌ ميدانيّ. واستثناؤها من المقامِ بالاختيارِ «مقامٌ مختار» (§قواعد
 *   القياس ٤) — فتُصنَّف **بقاعدةٍ مقيسةٍ مكتوبةٍ في الصفِّ نفسِه**، والبوّابةُ
 *   تعيد اشتقاقَ التصنيفِ وتسقط إن خالف المخزَّنُ المقيسَ.
 *
 * ◆ **و`CHECK` لا قادِح**: مستخدمُ الهجراتِ لا يملك `SUPER` وتسجيلُ الثنائيِّ
 *   مُفعَّل، فـ`CREATE TRIGGER` يُردّ (‏W03 · 2027_11_19). و`CHECK` يعطي
 *   الإنفاذَ نفسَه على الإدراجِ والتعديلِ بلا امتياز.
 *
 * ⛔ **ولا `CHECK` يجعل الحاجبَ أعمى**: `chk_ue_w4_shift` يمنع «قيدًا ميدانيًّا
 *   بلا وردية» — فالبوّابةُ **لا تكتفي به**، بل تعيد اشتقاقَ التصنيفِ نفسِه
 *   (الصفُّ المصنَّفُ إسقاطًا وهو يحمل معدةً = كذبٌ يمرُّ من القيدِ سليمًا).
 *
 * التشغيل: php database/migrations/2027_11_21_repair01_w4_field.php
 *          (⛔ لا `migrate.php up` — الهجرةُ بمسارِها الكاملِ منفردةً)
 * التراجع: php database/migrations/2027_11_21_repair01_w4_field_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

function w4_col(mysqli $c, $t, $col)
{
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

$done = 0; $had = 0; $err = 0;
$run = function ($sql, $label) use ($conn, &$done, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; return true; }
    echo "  ✘ $label — " . $conn->error . "\n"; $err++; return false;
};

echo "══ REPAIR01 · W04 — الحقيقةُ الميدانية ══\n\n";

/* ═══ ① نطاقُ المرحلةِ — المتطلَّبُ إلى شاشتِه المعيارية (خطوةُ السايدبارِ ٧) ═ */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w4_scope` (
  `requirement_id`   VARCHAR(48)  NOT NULL,
  `unit`             VARCHAR(160) NOT NULL DEFAULT '',
  `group_name`       VARCHAR(160) NOT NULL DEFAULT '',
  `surface`          VARCHAR(255) NOT NULL DEFAULT '',
  `anchor_screen_id` VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'Canonical Screen_ID أو فراغ لما لم يبن',
  `anchor_route`     VARCHAR(200) NOT NULL DEFAULT '',
  `anchor_probe`     VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'الجدول أو الصنف الذي يثبت المرساة قياسا',
  `owner_measured`   VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'مالك الشاشة كما يقيسه السجل',
  `owner_expected`   VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'الادارة المالكة للمتطلب',
  `owner_verdict`    VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'MATCH او MISMATCH — لا يدهس',
  `map_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `map_why`          VARCHAR(400) NOT NULL DEFAULT '',
  `wave_stage`       VARCHAR(8)   NOT NULL DEFAULT '',
  `src_ref`          VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`requirement_id`),
  KEY `ix_screen` (`anchor_screen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W04 - ربط متطلبات المرحلة بالسجل المعياري للشاشات'", 'repair01_w4_scope');

/* ═══ ② دفترُ الخطواتِ السبعِ للسايدبار ════════════════════════════════════ */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w4_sidebar` (
  `screen_id`      VARCHAR(12)  NOT NULL,
  `route`          VARCHAR(200) NOT NULL DEFAULT '',
  `owner_code`     VARCHAR(12)  NOT NULL DEFAULT '',
  `s1_verdict`     VARCHAR(40)  NOT NULL DEFAULT '',
  `s1_rule`        VARCHAR(48)  NOT NULL DEFAULT '',
  `s2_label_live`  VARCHAR(190) NOT NULL DEFAULT '',
  `s2_label_canon` VARCHAR(190) NOT NULL DEFAULT '',
  `s2_verdict`     VARCHAR(40)  NOT NULL DEFAULT '',
  `s2_rule`        VARCHAR(48)  NOT NULL DEFAULT '',
  `s3_group_live`  VARCHAR(190) NOT NULL DEFAULT '',
  `s3_group_canon` VARCHAR(190) NOT NULL DEFAULT '',
  `s3_verdict`     VARCHAR(40)  NOT NULL DEFAULT '',
  `s3_rule`        VARCHAR(48)  NOT NULL DEFAULT '',
  `s4_order_src`   VARCHAR(48)  NOT NULL DEFAULT '',
  `s4_order_no`    INT          NOT NULL DEFAULT 0,
  `s4_verdict`     VARCHAR(40)  NOT NULL DEFAULT '',
  `s4_rule`        VARCHAR(48)  NOT NULL DEFAULT '',
  `s5_parent`      VARCHAR(12)  NOT NULL DEFAULT '',
  `s5_verdict`     VARCHAR(40)  NOT NULL DEFAULT '',
  `s5_rule`        VARCHAR(48)  NOT NULL DEFAULT '',
  `s5_why`         VARCHAR(400) NOT NULL DEFAULT '',
  `s6_visibility`  VARCHAR(24)  NOT NULL DEFAULT '',
  `s6_perm_rows`   INT          NOT NULL DEFAULT 0,
  `s6_perm_coded`  INT          NOT NULL DEFAULT 0,
  `s6_guard_kind`  VARCHAR(24)  NOT NULL DEFAULT '',
  `s6_verdict`     VARCHAR(40)  NOT NULL DEFAULT '',
  `s6_rule`        VARCHAR(48)  NOT NULL DEFAULT '',
  `s7_linked`      TINYINT(1)   NOT NULL DEFAULT 0,
  `s7_verdict`     VARCHAR(40)  NOT NULL DEFAULT '',
  `s7_rule`        VARCHAR(48)  NOT NULL DEFAULT '',
  `measured_at`    DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`screen_id`),
  KEY `ix_owner` (`owner_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W04 - الخطوات السبع للسايدبار داخل نطاق المرحلة'", 'repair01_w4_sidebar');

/* ═══ ③ قراراتُ المرحلة ═══════════════════════════════════════════════════ */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w4_decisions` (
  `decision_id` VARCHAR(24)  NOT NULL,
  `question`    VARCHAR(400) NOT NULL DEFAULT '',
  `ruling`      VARCHAR(700) NOT NULL DEFAULT '',
  `rationale`   VARCHAR(900) NULL DEFAULT NULL,
  `scope_rows`  INT          NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`decision_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W04 - قرارات المرحلة'", 'repair01_w4_decisions');

/* ═══ ④ رحلةُ الإثبات — محطّاتُها وأثرُ كلِّ مستهلكٍ محفوظًا ═══════════════ */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w4_journey` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id`          VARCHAR(40)  NOT NULL DEFAULT '',
  `station_no`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `station`         VARCHAR(160) NOT NULL DEFAULT '',
  `entity`          VARCHAR(40)  NOT NULL DEFAULT '',
  `consumer`        VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'المستهلك بالاسم لا كل المستهلكين',
  `expected`        VARCHAR(400) NOT NULL DEFAULT '',
  `measured`        VARCHAR(400) NOT NULL DEFAULT '',
  `business_effect` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'الأثر التجاري المقيس لا صف الحدث',
  `passed`          TINYINT(1)   NOT NULL DEFAULT 0,
  `run_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_run` (`run_id`, `station_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W04 - رحلة اليوم: محطاتها وأثر كل مستهلك'", 'repair01_w4_journey');

/* ═══ ⑤ اليومُ الميدانيّ — الحبّة: موقعٌ × يوم ══════════════════════════════
   ◆ `state` آلةُ حالةٍ مغلقةُ المفردات: ‏`open` ← `closed` ← `reopened`.
   ◆ والقيودُ تمنع الحالةَ العارية: مُقفَلٌ بلا مُقفِلٍ ولا وقتٍ يُردّ، ومُعادُ
     الفتحِ بلا سببٍ يُردّ — فالحالةُ لا تُدَّعى بلا سندٍ في الصفِّ نفسِه. */
$run("
CREATE TABLE IF NOT EXISTS `site_day` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT(11)      NOT NULL COMMENT 'DEC-OPEN-03 - لا يوم بلا كيان قانوني',
  `site_id`       INT(11)      NOT NULL,
  `project_id`    INT(11)      NULL DEFAULT NULL,
  `day_date`      DATE         NOT NULL,
  `state`         ENUM('open','closed','reopened') NOT NULL DEFAULT 'open',
  `opened_by`     INT(11)      NOT NULL,
  `opened_at`     DATETIME     NOT NULL,
  `closed_by`     INT(11)      NULL DEFAULT NULL,
  `closed_at`     DATETIME     NULL DEFAULT NULL,
  `close_note`    VARCHAR(255) NOT NULL DEFAULT '',
  `reopened_by`   INT(11)      NULL DEFAULT NULL,
  `reopened_at`   DATETIME     NULL DEFAULT NULL,
  `reopen_reason` VARCHAR(255) NOT NULL DEFAULT '',
  `source_ref`    VARCHAR(120) NOT NULL DEFAULT '',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_site_day` (`company_id`, `site_id`, `day_date`),
  KEY `ix_state` (`state`),
  CONSTRAINT `chk_site_day_closed` CHECK (`state` <> 'closed' OR (`closed_by` IS NOT NULL AND `closed_at` IS NOT NULL)),
  CONSTRAINT `chk_site_day_reopen` CHECK (`state` <> 'reopened' OR (`reopened_by` IS NOT NULL AND `reopen_reason` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W04 - اليوم الميداني: موقع × يوم - فتح واحد وإقفال واحد'", 'site_day');

/* ═══ ⑥ ورديّاتُ اليوم — Shifts Child ═══════════════════════════════════════
   ◆ الحبّةُ **وردية × يوم موقع** — وهي حلُّ تناقضِ الحبّةِ الذي تصفه SITE-06:
     «اليومُ Header وكلُّ ورديةٍ سطر».
   ◆ و«لا تُقفل ورديةٌ بلا محضرٍ بين المشرفَين» (SITE-09) قيدٌ في الصفِّ نفسِه:
     `handed_over` يشترط `handover_to` — فالمحضرُ شرطُ حالةٍ لا توصية. */
$run("
CREATE TABLE IF NOT EXISTS `site_day_shift` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT(11)      NOT NULL,
  `day_id`        INT UNSIGNED NOT NULL,
  `shift`         ENUM('day','night') NOT NULL,
  `state`         ENUM('open','handed_over','closed') NOT NULL DEFAULT 'open',
  `supervisor_id` INT(11)      NULL DEFAULT NULL,
  `opened_at`     DATETIME     NOT NULL,
  `handover_to`   INT(11)      NULL DEFAULT NULL COMMENT 'مشرف الوردية التالية - محضر التسليم',
  `handover_at`   DATETIME     NULL DEFAULT NULL,
  `handover_note` VARCHAR(255) NOT NULL DEFAULT '',
  `closed_at`     DATETIME     NULL DEFAULT NULL,
  `hse_state`     ENUM('clear','observation','incident') NOT NULL DEFAULT 'clear',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_day_shift` (`day_id`, `shift`),
  KEY `ix_company` (`company_id`),
  CONSTRAINT `fk_sds_day` FOREIGN KEY (`day_id`) REFERENCES `site_day` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_sds_handover` CHECK (`state` <> 'handed_over' OR (`handover_to` IS NOT NULL AND `handover_at` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W04 - ورديات اليوم الميداني ومحضر التسليم بينها'", 'site_day_shift');

/* ═══ ⑦ سجلُّ محاولاتِ القيدِ المرفوضة — «تُرفَض وتُقيَّد» (§٦-أ) ══════════
   ◆ رفضٌ بلا قيدٍ **دعوى**: لا يُقاس ولا يُراجَع ولا يُعرف كم مرّةً وقع. */
$run("
CREATE TABLE IF NOT EXISTS `site_day_attempt` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT(11)      NOT NULL,
  `day_id`       INT UNSIGNED NULL DEFAULT NULL,
  `site_id`      INT(11)      NOT NULL,
  `day_date`     DATE         NOT NULL,
  `shift`        VARCHAR(16)  NOT NULL DEFAULT '',
  `attempt_kind` ENUM('unit_entry','stop_register','shift_open','day_close') NOT NULL DEFAULT 'unit_entry',
  `actor_id`     INT(11)      NULL DEFAULT NULL,
  `outcome`      ENUM('rejected','allowed') NOT NULL DEFAULT 'rejected',
  `reason_code`  VARCHAR(48)  NOT NULL DEFAULT '' COMMENT 'DAY_CLOSED - SHIFT_CLOSED - NO_DAY …',
  `reason_note`  VARCHAR(255) NOT NULL DEFAULT '',
  `payload_ref`  VARCHAR(120) NOT NULL DEFAULT '',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_day` (`day_id`),
  KEY `ix_reason` (`reason_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W04 - سجل محاولات القيد بعد الإقفال: ترفض وتقيد'", 'site_day_attempt');

/* ═══ ⑧ سجلُّ واقعةِ التوقّف الواحد — العطالةُ بمفتاحٍ لا بفحصِ رصيد ═══════
   ◆ `occurrence_key` فريدٌ على حبّةِ (كيان × يوم × وردية × معدة × حالة) —
     فمن ادّعاها أوّلًا ملكها، والثاني **لا يُنشئ صفًّا ثانيًا** بل يُسجَّل
     مرآةً في `ops_stop_source`.
   ◆ و`sla_due_at` هو «المهلة» في حبّةِ OPS-09: «كيانٌ مستقلٌّ بمهلة». */
$run("
CREATE TABLE IF NOT EXISTS `ops_stop_register` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`      INT(11)      NOT NULL,
  `occurrence_key`  CHAR(40)     NOT NULL COMMENT 'sha1 لحبة الواقعة - مفتاح العطالة',
  `stop_date`       DATE         NOT NULL,
  `shift`           ENUM('day','night') NOT NULL,
  `equipment_id`    INT(11)      NULL DEFAULT NULL,
  `site_id`         INT(11)      NULL DEFAULT NULL,
  `project_id`      INT(11)      NULL DEFAULT NULL,
  `ops_state`       VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'من مفردات unit_time_log.ops_state',
  `hours`           DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `resp_party`      VARCHAR(24)  NOT NULL DEFAULT '',
  `obligation_type` VARCHAR(32)  NOT NULL DEFAULT '',
  `billable`        TINYINT(1)   NOT NULL DEFAULT 0,
  `authority`       ENUM('unit_time_log','timesheet','manual') NOT NULL DEFAULT 'unit_time_log'
                    COMMENT 'السجل الحاكم للواقعة - والباقي مرآة',
  `authority_rule`  VARCHAR(48)  NOT NULL DEFAULT '',
  `authority_ref`   VARCHAR(64)  NOT NULL DEFAULT '',
  `decision`        ENUM('pending','classified','attributed','disputed','closed') NOT NULL DEFAULT 'pending',
  `decided_by`      INT(11)      NULL DEFAULT NULL,
  `decided_at`      DATETIME     NULL DEFAULT NULL,
  `sla_due_at`      DATETIME     NULL DEFAULT NULL COMMENT 'المهلة - كيان مستقل بمهلة',
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_occurrence` (`occurrence_key`),
  KEY `ix_day` (`company_id`, `stop_date`, `shift`),
  KEY `ix_decision` (`decision`),
  CONSTRAINT `chk_stop_decided` CHECK (`decision` = 'pending' OR (`decided_by` IS NOT NULL AND `decided_at` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W04 - السجل الواحد لواقعة التوقف بمفتاح عطالة فريد'", 'ops_stop_register');

/* ═══ ⑨ قراءةُ كلِّ سجلٍّ مصدرٍ للواقعةِ نفسِها — المرآةُ بفارقِها ══════════
   ◆ **ولا يُحذف أحدُ التسجيلَين**: الشبحُ يُوسَم ولا يُحذف (_CONTEXT)، والقاعدةُ
     نفسُها هنا — التسجيلُ الثاني يبقى ويُوسَم مرآةً بفارقِه المقيس. */
$run("
CREATE TABLE IF NOT EXISTS `ops_stop_source` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT(11)      NOT NULL COMMENT 'DEC-OPEN-03 - لا قراءة سجل بلا كيان قانوني',
  `occurrence_key` CHAR(40)     NOT NULL,
  `register_name`  ENUM('unit_time_log','timesheet') NOT NULL,
  `source_ref`     VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'مفتاح الصف في سجله',
  `role`           ENUM('AUTHORITY','MIRROR') NOT NULL DEFAULT 'MIRROR',
  `hours_read`     DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `variance_hours` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `variance_rule`  VARCHAR(48)  NOT NULL DEFAULT '',
  `variance_note`  VARCHAR(255) NOT NULL DEFAULT '',
  `measured_at`    DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_occ_register` (`occurrence_key`, `register_name`),
  KEY `ix_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W04 - قراءة كل سجل مصدر للواقعة نفسها بفارقها المسجل'", 'ops_stop_source');

/* عاديّةُ التشغيل: جدولٌ أُنشئ قبلَ إضافةِ عمودِ الكيانِ يُلحَق به لا يُعاد بناؤه */
if (w4_col($conn, 'ops_stop_source', 'company_id')) { echo "  ⟳ ops_stop_source.company_id موجود\n"; $had++; }
else {
    $conn->query("DELETE FROM `ops_stop_source`");   /* قراءاتٌ تُعاد بناؤها من الحيِّ في كلِّ تشغيل */
    $run("ALTER TABLE `ops_stop_source`
          ADD COLUMN `company_id` INT(11) NOT NULL COMMENT 'DEC-OPEN-03 - لا قراءة سجل بلا كيان قانوني' AFTER `id`,
          ADD KEY `ix_company` (`company_id`)", 'ops_stop_source.company_id');
}

/* ═══ ⑩ `unit_entries` — تصنيفُ القيدِ ووصلُه باليومِ الميدانيّ ════════════ */
if (w4_col($conn, 'unit_entries', 'field_kind')) { echo "  ⟳ unit_entries.field_kind موجود\n"; $had++; }
else {
    $run("ALTER TABLE `unit_entries`
          ADD COLUMN `field_kind` ENUM('FIELD_DAILY','CONTRACT_PROJECTION') NOT NULL DEFAULT 'FIELD_DAILY'
              COMMENT 'REPAIR01 W04 - قيد ميداني أم إسقاط التزام تعاقدي',
          ADD KEY `ix_field_kind` (`field_kind`)", 'unit_entries.field_kind');
}
if (w4_col($conn, 'unit_entries', 'field_kind_rule')) { echo "  ⟳ unit_entries.field_kind_rule موجود\n"; $had++; }
else {
    $run("ALTER TABLE `unit_entries`
          ADD COLUMN `field_kind_rule` VARCHAR(48) NOT NULL DEFAULT ''
              COMMENT 'REPAIR01 W04 - قاعدة التصنيف: لا قيمة بلا قاعدة'", 'unit_entries.field_kind_rule');
}
if (w4_col($conn, 'unit_entries', 'site_day_id')) { echo "  ⟳ unit_entries.site_day_id موجود\n"; $had++; }
else {
    $run("ALTER TABLE `unit_entries`
          ADD COLUMN `site_day_id` INT UNSIGNED NULL DEFAULT NULL
              COMMENT 'REPAIR01 W04 - اليوم الميداني الذي يحمل القيد',
          ADD KEY `ix_site_day` (`site_day_id`)", 'unit_entries.site_day_id');
}

/* القيدُ: قيدٌ ميدانيٌّ بلا وردية يُردّ — والإسقاطُ التعاقديُّ معفًى بقاعدتِه.
   ⚠ ولا يُضاف قيدٌ يمنع «إسقاطًا يحمل معدةً»: منعُه في القاعدةِ يجعل البوّابةَ
     عمياءَ بالبناء (لا يمكن كسرُها) — فالكذبُ في التصنيفِ يُترك ممكنًا في
     المخطَّطِ ويُقاس في البوّابةِ بإعادةِ اشتقاقِ القاعدة. */
$r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'unit_entries'
                      AND CONSTRAINT_NAME = 'chk_ue_w4_shift'");
$hasChk = $r && ($x = $r->fetch_row()) ? (int) $x[0] : 0;
if ($hasChk > 0) { echo "  ⟳ chk_ue_w4_shift موجود\n"; $had++; }
else {
    /* الصفوفُ القائمةُ تُصنَّف قبلَ القيدِ وإلا رُدَّ إنشاؤه */
    $conn->query("UPDATE `unit_entries`
                     SET `field_kind` = 'CONTRACT_PROJECTION',
                         `field_kind_rule` = 'W4_NO_EQUIPMENT_NO_ENTERER'
                   WHERE `equipment_id` IS NULL AND `entered_by` IS NULL AND `shift` IS NULL");
    $conn->query("UPDATE `unit_entries`
                     SET `field_kind` = 'FIELD_DAILY',
                         `field_kind_rule` = 'W4_HAS_EQUIPMENT'
                   WHERE `equipment_id` IS NOT NULL AND `field_kind_rule` = ''");
    $run("ALTER TABLE `unit_entries` ADD CONSTRAINT `chk_ue_w4_shift`
          CHECK (`field_kind` <> 'FIELD_DAILY' OR `shift` IS NOT NULL)", 'chk_ue_w4_shift');
}

/* ═══ ⑪ `timesheet` — وسمُ دورِ الصفِّ في واقعةِ التوقّف ═══════════════════
   ◆ ولا تُدهَس أعمدةُ الساعات: الوسمُ يقول **من الحاكم**، والقيمُ تبقى دليلًا. */
if (w4_col($conn, 'timesheet', 'stop_register_role')) { echo "  ⟳ timesheet.stop_register_role موجود\n"; $had++; }
else {
    $run("ALTER TABLE `timesheet`
          ADD COLUMN `stop_register_role` ENUM('NONE','AUTHORITY','MIRROR') NOT NULL DEFAULT 'NONE'
              COMMENT 'REPAIR01 W04 - دور هذا الصف في واقعة التوقف: حاكم أم مرآة',
          ADD COLUMN `stop_occurrence_key` CHAR(40) NOT NULL DEFAULT ''
              COMMENT 'REPAIR01 W04 - مفتاح الواقعة التي يرآها هذا الصف',
          ADD KEY `ix_stop_role` (`stop_register_role`)", 'timesheet.stop_register_role');
}

/* ═══ ⑫ عمودُ المرحلةِ في `repair01_events` قائمٌ من W03 — يُتحقَّق فقط ════ */
foreach (array('contract_status', 'contract_stage', 'consumer_list') as $c) {
    if (w4_col($conn, 'repair01_events', $c)) { echo "  ⟳ repair01_events.$c موجود\n"; $had++; }
    else { echo "  ✘ repair01_events.$c مفقود — شغّلْ هجرةَ W03 أوّلًا\n"; $err++; }
}

echo "\n";
printf("الحصيلة: مُنفَّذٌ %d · موجودٌ سلفًا %d · أخطاء %d\n", $done, $had, $err);
echo ($err === 0 ? "الحكم: تمّت ✔\n" : "الحكم: أخطاء ✘\n");
$conn->close();
exit($err === 0 ? 0 : 1);
