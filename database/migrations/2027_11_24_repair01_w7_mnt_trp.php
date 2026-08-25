<?php
/**
 * 2027_11_24_repair01_w7_mnt_trp.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W07 — **الصيانةُ والنقلُ والترحيل**: دورةُ العطلِ والوقائيّةُ
 * والإقفالُ وشهادةُ إعادةِ الخدمة، ودورةُ الترحيلِ ومراحلُها وإقفالُها.
 *
 * ◆ **`DEC-OPEN-12` معتمَدٌ ونصُّه بنيةٌ لا عبارة.** قرارُ المالكِ يقول أربعةَ
 *   أشياءَ تُترجَم إلى مخطَّطٍ مباشرةً:
 *   ① **أربعةُ أبعادٍ تُفصل ولا تُدمَج في حقلٍ واحد** — نوعُ العطل · خطورةُ
 *     السلامة · الأثرُ التشغيليّ · حالةُ الجاهزية. فأربعةُ أعمدةٍ في `mnt_order`
 *     لا عمودٌ واحدٌ اسمُه `severity`.
 *   ② **ومدّةُ التوقّفِ لا تغيّر تصنيفَ السلامةِ تلقائيًّا** — فلا اشتقاقَ
 *     لـ`safety_severity` من `downtime_hours`، و`ops_impact` محورٌ ثانٍ مستقلّ.
 *   ③ **والتصنيفُ قابلٌ للتهيئةِ بحسبِ نوعِ المعدّة** — ⛔ «لا قائمةَ صلبةً
 *     واحدةً لكلِّ المعدّات». فقائمةُ الأنظمةِ الحرجةِ **سجلٌّ** (`mnt_safety_rule`)
 *     بمفتاحِ (نوعُ المعدّة × النظام) — **ولا مصفوفةَ في الشيفرة**.
 *   ④ **وقائمةُ المكوِّناتِ ليست الحكمَ وحدَها** — «أيُّ عطلٍ يرى الفنّيُّ أو
 *     مهندسُ الصيانةِ أو مسؤولُ السلامةِ أنَّ استمرارَ التشغيلِ معه غيرُ آمنٍ
 *     يُصنَّف حرِجًا حتّى تتمَّ مراجعتُه». فعمودا `severity_override_by`
 *     و`severity_override_reason` **جزءٌ من القاعدةِ لا استثناءٌ منها**.
 *
 * ◆ **ولماذا `mnt_return_cert` كيانٌ لا عمودٌ في أمرِ العمل**: `MNT-14` يقول
 *   «الشهادةُ وحدَها تعيد المعدّةَ للخدمة». والمقيسُ أنَّ
 *   `Maintenance/return_to_service.php` اليومَ **يحدّث `mnt_order` و`equipments`
 *   مباشرةً بلا صفِّ شهادةٍ واحد**: لا رقمَ شهادةٍ ولا صلاحيةَ ولا مُعتمِدَ ولا
 *   اختبارًا موثَّقًا. فـ«شهادةُ العودة» في النظامِ اليومَ **حدثُ تحديثٍ لا
 *   مستند**، و`MNT-15` الذي يقيس «التكرارَ خلالَ صلاحيةِ الشهادة» **بلا
 *   صلاحيةٍ يقيسها**.
 *
 * ◆ **ولماذا `trp_closure` منفصلٌ عن `transfer_cost_lines`**: `TRP-12` يقول
 *   نصًّا «الـGrain مفصول: البنودُ في ن07 والإقفالُ هنا». والحبّتانِ مختلفتان
 *   (بندٌ × أمر ‖ أمرٌ × إقفالٌ واحد) — وخلطُهما يجعل «الأمرَ مقفلًا» دعوى
 *   مشتقّةً من عدِّ بنودٍ لا واقعةَ إقفالٍ بمعتمِدها.
 *
 * ◆ **و`CHECK` لا قادِح**: مستخدمُ الهجراتِ لا يملك `SUPER` (‏W03 · 2027_11_19).
 *
 * ⛔ **ولا `CHECK` يجعل الحاجبَ أعمى**: ما يمنعه المخطَّطُ لا يُختبَر في
 *   البوّابة. فالمنعُ عبرَ الصفوفِ (لا شهادةَ بلا أمرٍ منجَز · لا إقفالَ بلا
 *   محضرٍ · لا مغادرةَ بتصريحٍ منتهٍ) **تُرك في الخدمةِ ليُقاس وظيفيًّا**؛
 *   و`CHECK` هنا يمسك ما يقع داخلَ الصفِّ وحدَه.
 *
 * ⛔ **ولا عتبةٌ رقميّةٌ في الشيفرة** — نافذةُ صلاحيةِ الشهادةِ وسماحُ
 *   الوقائيّةِ وحدُّ الأثرِ التشغيليّ كلُّها في `repair01_w7_thresholds`.
 *
 * التشغيل: php database/migrations/2027_11_24_repair01_w7_mnt_trp.php
 *          (⛔ لا `migrate.php up` — الهجرةُ بمسارِها الكاملِ منفردةً)
 * التراجع: php database/migrations/2027_11_24_repair01_w7_mnt_trp_down.php
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

function w7_col(mysqli $c, $t, $col)
{
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}
function w7_tbl(mysqli $c, $t)
{
    $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}
function w7_chk(mysqli $c, $t, $name)
{
    $r = $c->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $c->real_escape_string($t) . "'
                       AND CONSTRAINT_NAME = '" . $c->real_escape_string($name) . "'");
    return $r && $r->num_rows > 0;
}

$done = 0; $err = 0; $skip = 0;
$run = function ($sql, $label) use ($conn, &$done, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; return true; }
    echo "  ✘ $label — " . $conn->error . "\n"; $err++; return false;
};
$addCol = function ($t, $col, $ddl) use ($conn, &$done, &$skip, &$err) {
    if (!w7_tbl($conn, $t)) { echo "  ⚠ $t غير موجود — $col يُتخطّى\n"; $skip++; return; }
    if (w7_col($conn, $t, $col)) { echo "  ↷ $t.$col قائم\n"; $skip++; return; }
    if ($conn->query("ALTER TABLE `$t` ADD COLUMN $ddl") === true) { echo "  ✔ $t.$col\n"; $done++; }
    else { echo "  ✘ $t.$col — " . $conn->error . "\n"; $err++; }
};
$addChk = function ($t, $name, $expr) use ($conn, &$done, &$skip, &$err) {
    if (!w7_tbl($conn, $t)) { echo "  ⚠ $t غير موجود — $name يُتخطّى\n"; $skip++; return; }
    if (w7_chk($conn, $t, $name)) { echo "  ↷ $t.$name قائم\n"; $skip++; return; }
    if ($conn->query("ALTER TABLE `$t` ADD CONSTRAINT `$name` CHECK ($expr)") === true) { echo "  ✔ $t.$name\n"; $done++; }
    else { echo "  ✘ $t.$name — " . $conn->error . "\n"; $err++; }
};

echo "══ REPAIR01 · W07 — الصيانةُ والنقلُ ورحلةُ التوقُّف ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ① دفاترُ المرحلة — النطاقُ والسايدبارُ والقراراتُ والرحلةُ والعتبات
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① دفاترُ المرحلة ───────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w7_scope` (
  `requirement_id`   VARCHAR(48)  NOT NULL,
  `unit`             VARCHAR(160) NOT NULL DEFAULT '',
  `group_name`       VARCHAR(160) NOT NULL DEFAULT '',
  `surface`          VARCHAR(255) NOT NULL DEFAULT '',
  `anchor_screen_id` VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'Canonical Screen_ID او فراغ لما لم يبن',
  `anchor_route`     VARCHAR(200) NOT NULL DEFAULT '',
  `anchor_probe`     VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'الجدول او الصنف الذي يثبت المرساة قياسا',
  `owner_measured`   VARCHAR(12)  NOT NULL DEFAULT '',
  `owner_expected`   VARCHAR(12)  NOT NULL DEFAULT '',
  `owner_verdict`    VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'MATCH او MISMATCH - لا يدهس',
  `map_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `map_why`          VARCHAR(400) NOT NULL DEFAULT '',
  `wave_stage`       VARCHAR(8)   NOT NULL DEFAULT '',
  `src_ref`          VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`requirement_id`),
  KEY `ix_screen` (`anchor_screen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W07 - ربط متطلبات المرحلة بالسجل المعياري للشاشات'", 'repair01_w7_scope');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w7_sidebar` (
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
  `measured_at`    DATETIME     NULL,
  PRIMARY KEY (`screen_id`),
  KEY `ix_owner` (`owner_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W07 - الخطوات السبع للسايدبار داخل نطاق المرحلة'", 'repair01_w7_sidebar');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w7_decisions` (
  `decision_id` VARCHAR(24)  NOT NULL,
  `question`    VARCHAR(400) NOT NULL DEFAULT '',
  `ruling`      VARCHAR(900) NOT NULL DEFAULT '',
  `rationale`   VARCHAR(900) NULL,
  `scope_rows`  INT          NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`decision_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W07 - قرارات المرحلة'", 'repair01_w7_decisions');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w7_journey` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id`          VARCHAR(40)  NOT NULL DEFAULT '',
  `station_no`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `station`         VARCHAR(160) NOT NULL DEFAULT '',
  `entity`          VARCHAR(40)  NOT NULL DEFAULT '',
  `consumer`        VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'المستهلك بالاسم لا كل المستهلكين',
  `expected`        VARCHAR(400) NOT NULL DEFAULT '',
  `measured`        VARCHAR(400) NOT NULL DEFAULT '',
  `business_effect` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'الاثر التجاري المقيس لا صف الحدث',
  `readiness_after` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'حالة الجاهزية المشتقة بعد المحطة',
  `passed`          TINYINT(1)   NOT NULL DEFAULT 0,
  `run_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_run` (`run_id`,`station_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W07 - رحلة التوقف: محطاتها واثر كل مستهلك'", 'repair01_w7_journey');

/* آلاتُ الحالةِ ومصفوفةُ فصلِ الواجبات — مخرَجانِ حاكمانِ يُقاسانِ لا يُسرَدان */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w7_states` (
  `entity`        VARCHAR(48)  NOT NULL,
  `from_state`    VARCHAR(48)  NOT NULL,
  `to_state`      VARCHAR(48)  NOT NULL,
  `allowed`       TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'الممنوع صراحة يكتب ولا يسكت عنه',
  `owner_role`    VARCHAR(120) NOT NULL DEFAULT '',
  `precondition`  VARCHAR(500) NOT NULL DEFAULT '',
  `official_doc`  VARCHAR(190) NOT NULL DEFAULT '',
  `approval_gate` VARCHAR(190) NOT NULL DEFAULT '',
  `reopen_rule`   VARCHAR(300) NOT NULL DEFAULT '',
  `correct_rule`  VARCHAR(300) NOT NULL DEFAULT '',
  `forbid_reason` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'الممنوع بلا سبب مكتوب ليس ممنوعا',
  `src_ref`       VARCHAR(190) NOT NULL DEFAULT '',
  PRIMARY KEY (`entity`,`from_state`,`to_state`),
  CONSTRAINT `chk_w7st_forbid` CHECK (`allowed` = 1 OR `forbid_reason` <> ''),
  CONSTRAINT `chk_w7st_allow`  CHECK (`allowed` = 0 OR (`owner_role` <> '' AND `precondition` <> ''
                                       AND `official_doc` <> '' AND `approval_gate` <> ''
                                       AND `reopen_rule` <> '' AND `correct_rule` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W07 - آلة حالة لكل كيان رئيسي في النطاق'", 'repair01_w7_states');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w7_sod` (
  `process_key`       VARCHAR(60)  NOT NULL,
  `process_name`      VARCHAR(190) NOT NULL DEFAULT '',
  `initiator_role`    VARCHAR(120) NOT NULL DEFAULT '',
  `reviewer_role`     VARCHAR(120) NOT NULL DEFAULT '',
  `approver_role`     VARCHAR(120) NOT NULL DEFAULT '',
  `executor_role`     VARCHAR(120) NOT NULL DEFAULT '',
  `closer_role`       VARCHAR(120) NOT NULL DEFAULT '',
  `forbidden_combo`   VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'التركيبة الممنوعة صراحة',
  `enforced_by`       VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'رمز الرد الذي ينفذها - لا اعلان بلا تنفيذ',
  `authority_rule_id` VARCHAR(60)  NOT NULL DEFAULT '',
  `deputy_role`       VARCHAR(120) NOT NULL DEFAULT '',
  `scope_rule`        VARCHAR(300) NOT NULL DEFAULT '',
  `delegation`        VARCHAR(300) NOT NULL DEFAULT '',
  `effective_date`    DATE         NULL,
  `src_ref`           VARCHAR(190) NOT NULL DEFAULT '',
  PRIMARY KEY (`process_key`),
  CONSTRAINT `chk_w7sod_full` CHECK (`initiator_role` <> '' AND `approver_role` <> ''
                                     AND `executor_role` <> '' AND `closer_role` <> ''
                                     AND `forbidden_combo` <> '' AND `authority_rule_id` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W07 - فصل الواجبات بستة ادوار وتركيبة ممنوعة'", 'repair01_w7_sod');

/* ⛔ عتبةٌ رقميّةٌ صلبةٌ في الشيفرة ممنوعة (§٥) — كلُّها هنا تُقرأ ولا تُكتب */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w7_thresholds` (
  `threshold_key` VARCHAR(48)   NOT NULL,
  `value_num`     DECIMAL(14,4) NOT NULL DEFAULT 0,
  `unit_ar`       VARCHAR(40)   NOT NULL DEFAULT '',
  `title_ar`      VARCHAR(190)  NOT NULL DEFAULT '',
  `why`           VARCHAR(600)  NOT NULL DEFAULT '',
  `decision_ref`  VARCHAR(48)   NOT NULL DEFAULT '',
  `src_ref`       VARCHAR(190)  NOT NULL DEFAULT '',
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`threshold_key`),
  CONSTRAINT `chk_w7th_why` CHECK (`why` <> '' AND `decision_ref` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W07 - عتبات المرحلة: من السجل لا من الشيفرة'", 'repair01_w7_thresholds');

/* ═══════════════════════════════════════════════════════════════════════════
   ② سجلُّ تصنيفِ السلامة — DEC-OPEN-12 ③ «قابلٌ للتهيئةِ بحسبِ نوعِ المعدّة»
   ═══════════════════════════════════════════════════════════════════════════
   ⛔ «لا قائمةَ صلبةً واحدةً لكلِّ المعدّات» — فالسجلُّ بمفتاحِ
      (نوعُ المعدّة × النظام)، و`equipment_type=''` يعني «كلُّ الأنواع».
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n② سجلُّ تصنيفِ السلامة — DEC-OPEN-12 ──────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `mnt_safety_rule` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`       INT          NOT NULL,
  `equipment_type`   VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'فارغ يعني كل الانواع - DEC-OPEN-12 لا قائمة صلبة واحدة',
  `system_key`       VARCHAR(60)  NOT NULL COMMENT 'brakes steering control lifting estop fire structural',
  `system_ar`        VARCHAR(160) NOT NULL DEFAULT '',
  `default_severity` ENUM('minor','major','safety_critical') NOT NULL DEFAULT 'major',
  `requires_cert`    TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'شهادة عودة مستقلة - البسيط لا يحتاجها',
  `requires_lockout` TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'ايقاف ومنع تشغيل قبل الاصلاح',
  `approver_kind`    VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'technician او technical_authority - لا المشغل وحده',
  `rule_ref`         VARCHAR(60)  NOT NULL DEFAULT '',
  `active`           TINYINT(1)   NOT NULL DEFAULT 1,
  `src_ref`          VARCHAR(190) NOT NULL DEFAULT '',
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_msr` (`company_id`,`equipment_type`,`system_key`),
  KEY `ix_msr_sev` (`default_severity`),
  CONSTRAINT `chk_msr_rule` CHECK (`rule_ref` <> ''),
  CONSTRAINT `chk_msr_cert` CHECK (`default_severity` <> 'safety_critical'
                                   OR (`requires_cert` = 1 AND `requires_lockout` = 1 AND `approver_kind` = 'technical_authority'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-W07 - تصنيف السلامة بحسب نوع المعدة والنظام - DEC-OPEN-12'", 'mnt_safety_rule');

/* ═══════════════════════════════════════════════════════════════════════════
   ③ أمرُ العمل — أربعةُ أبعادٍ تُفصل ولا تُدمَج (DEC-OPEN-12 ①)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n③ أمرُ العمل — أربعةُ محاورَ منفصلة ───────────────────────────\n";

$addCol('mnt_order', 'failure_kind',
    "`failure_kind` VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'المحور ١ نوع العطل - كهربائي ميكانيكي هيدروليكي اطارات'");
$addCol('mnt_order', 'safety_severity',
    "`safety_severity` ENUM('minor','major','safety_critical') NULL COMMENT 'المحور ٢ خطورة السلامة - DEC-OPEN-12'");
$addCol('mnt_order', 'safety_rule_ref',
    "`safety_rule_ref` VARCHAR(60) NOT NULL DEFAULT '' COMMENT 'قاعدة التصنيف من mnt_safety_rule - لا حكم بلا قاعدة'");
$addCol('mnt_order', 'safety_system_key',
    "`safety_system_key` VARCHAR(60) NOT NULL DEFAULT '' COMMENT 'النظام المصاب الذي رفع التصنيف'");
$addCol('mnt_order', 'severity_override_by',
    "`severity_override_by` INT NULL COMMENT 'DEC-OPEN-12 تصعيد الفني او مهندس الصيانة او مسؤول السلامة'");
$addCol('mnt_order', 'severity_override_reason',
    "`severity_override_reason` VARCHAR(400) NOT NULL DEFAULT ''");
$addCol('mnt_order', 'ops_impact',
    "`ops_impact` ENUM('low','medium','high','critical') NULL COMMENT 'المحور ٣ الاثر التشغيلي - محور ثان مستقل لا يغير السلامة'");
$addCol('mnt_order', 'ops_impact_rule',
    "`ops_impact_rule` VARCHAR(60) NOT NULL DEFAULT ''");
$addCol('mnt_order', 'lockout_state',
    "`lockout_state` ENUM('none','locked_out','released') NOT NULL DEFAULT 'none' COMMENT 'ايقاف ومنع تشغيل - شرط الحرج للسلامة'");
$addCol('mnt_order', 'lockout_at', "`lockout_at` DATETIME NULL");
$addCol('mnt_order', 'lockout_by', "`lockout_by` INT NULL");
$addCol('mnt_order', 'w7_state_rule',
    "`w7_state_rule` VARCHAR(60) NOT NULL DEFAULT '' COMMENT 'قاعدة الانتقال الاخير - لا حالة بلا قاعدة'");
$addCol('mnt_order', 'w7_cert_id',
    "`w7_cert_id` INT UNSIGNED NULL COMMENT 'شهادة اعادة الخدمة التي اقفلت الامر'");

/* المحورُ ④ (حالةُ الجاهزية) محلُّه الأصلُ لا الأمر — والقيدُ التشغيليُّ معه */
$addCol('equipments', 'w7_readiness_state',
    "`w7_readiness_state` ENUM('operational','operational_restricted','stopped','prohibited','ready_after_approval') NULL COMMENT 'المحور ٤ - DEC-OPEN-12'");
$addCol('equipments', 'w7_readiness_rule', "`w7_readiness_rule` VARCHAR(60) NOT NULL DEFAULT ''");
$addCol('equipments', 'w7_operating_limits',
    "`w7_operating_limits` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'قيود التشغيل من شهادة العودة'");
$addCol('equipments', 'w7_cert_id',
    "`w7_cert_id` INT UNSIGNED NULL COMMENT 'شهادة العودة السارية - لا اعادة خدمة بلا شهادة'");

/* ⛔ تجاوزٌ بلا سببٍ يُرفَض في الصفّ · وإقفالُ اللوكاوت بلا وقتٍ يُرفَض */
$addChk('mnt_order', 'chk_mo_override', "`severity_override_by` IS NULL OR `severity_override_reason` <> ''");
$addChk('mnt_order', 'chk_mo_lockout',  "`lockout_state` = 'none' OR `lockout_at` IS NOT NULL");
$addChk('equipments', 'chk_eq_w7_limits',
        "`w7_readiness_state` IS NULL OR `w7_readiness_state` <> 'operational_restricted' OR `w7_operating_limits` <> ''");

/* ═══════════════════════════════════════════════════════════════════════════
   ④ شهادةُ إعادةِ الخدمة — MNT-14 · «الشهادةُ وحدَها تعيد المعدّةَ للخدمة»
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n④ شهادةُ إعادةِ الخدمة ────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `mnt_return_cert` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`          INT          NOT NULL,
  `cert_no`             VARCHAR(40)  NOT NULL,
  `order_id`            INT          NOT NULL,
  `equipment_id`        INT          NOT NULL,
  `safety_severity`     ENUM('minor','major','safety_critical') NOT NULL DEFAULT 'major'
                        COMMENT 'منقول من الامر لحظة الاصدار - هو الذي اوجب الشهادة',
  `cert_required`       TINYINT(1)   NOT NULL DEFAULT 1,
  `cert_rule`           VARCHAR(60)  NOT NULL DEFAULT '',
  `tech_complete_date`  DATE         NULL COMMENT 'تاريخ الانجاز الفني',
  `test_performed`      VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'الاختبار المنفذ موثقا',
  `test_result`         ENUM('pass','conditional','fail') NULL,
  `meter_at_close`      DECIMAL(14,2) NULL COMMENT 'قراءة العداد عند الاقفال',
  `downtime_hours`      DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'مستورد للقراءة من امر العمل',
  `actual_cost`         DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'مشتق من العمالة والقطع والخارجي',
  `cost_rule`           VARCHAR(60)  NOT NULL DEFAULT '',
  `new_readiness_state` ENUM('operational','operational_restricted','stopped','prohibited','ready_after_approval') NULL,
  `operating_limits`    VARCHAR(400) NOT NULL DEFAULT '',
  `valid_days`          INT          NOT NULL DEFAULT 0 COMMENT 'من repair01_w7_thresholds لا من الشيفرة',
  `valid_until`         DATE         NULL,
  `signed_by`           INT          NULL COMMENT 'توقيع مدير الصيانة او المخول الفني',
  `signer_kind`         VARCHAR(40)  NOT NULL DEFAULT '',
  `state`               ENUM('draft','submitted','approved','rejected','expired','superseded') NOT NULL DEFAULT 'draft',
  `state_rule`          VARCHAR(60)  NOT NULL DEFAULT '',
  `reject_reason`       VARCHAR(400) NOT NULL DEFAULT '',
  `reviewed_by`         INT          NULL,
  `approved_by`         INT          NULL,
  `approved_at`         DATETIME     NULL,
  `superseded_by`       INT UNSIGNED NULL,
  `created_by`          INT          NULL,
  `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `src_ref`             VARCHAR(190) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mrc_no` (`company_id`,`cert_no`),
  KEY `ix_mrc_order` (`order_id`),
  KEY `ix_mrc_eq` (`equipment_id`,`valid_until`),
  CONSTRAINT `chk_mrc_rule`   CHECK (`state_rule` <> '' AND `cert_rule` <> ''),
  CONSTRAINT `chk_mrc_appr`   CHECK (`state` <> 'approved' OR (`approved_by` IS NOT NULL AND `approved_at` IS NOT NULL
                                     AND `test_result` IS NOT NULL AND `test_result` <> 'fail')),
  CONSTRAINT `chk_mrc_evid`   CHECK (`state` NOT IN ('submitted','approved')
                                     OR (`test_performed` <> '' AND `tech_complete_date` IS NOT NULL)),
  CONSTRAINT `chk_mrc_rej`    CHECK (`state` <> 'rejected' OR `reject_reason` <> ''),
  CONSTRAINT `chk_mrc_limits` CHECK (`new_readiness_state` IS NULL
                                     OR `new_readiness_state` <> 'operational_restricted' OR `operating_limits` <> ''),
  CONSTRAINT `chk_mrc_valid`  CHECK (`state` <> 'approved' OR `valid_until` IS NOT NULL),
  CONSTRAINT `chk_mrc_signer` CHECK (`safety_severity` <> 'safety_critical'
                                     OR `state` <> 'approved' OR `signer_kind` = 'technical_authority')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-W07 MNT-14 - شهادة اعادة الخدمة: الشهادة وحدها تعيد المعدة'", 'mnt_return_cert');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ إعادةُ الإصلاح وتحليلُ السبب — MNT-15
   ═══════════════════════════════════════════════════════════════════════════
   ◆ «التكرارُ خلالَ الصلاحيةِ يفتح التحليل» — و`within_validity` **مشتقٌّ**
     من `valid_until` في الشهادة، لا مُدخَل. والمحفِّزُ ليس التكرارَ وحدَه:
     `MNT-15` نصًّا «RCA لا يقتصر على المتكرر».
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑤ سجلُّ إعادةِ الإصلاح ────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `mnt_repeat_repair` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`       INT          NOT NULL,
  `equipment_id`     INT          NOT NULL,
  `origin_order_id`  INT          NOT NULL COMMENT 'رقم الامر الاصلي',
  `origin_cert_id`   INT UNSIGNED NULL,
  `new_order_id`     INT          NULL COMMENT 'رقم الامر الجديد - مشتق',
  `tree_node`        VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'عقدة شجرة الاسباب',
  `repeat_date`      DATE         NOT NULL,
  `days_since_cert`  INT          NULL COMMENT 'مشتق - لا يدخل',
  `within_validity`  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'مشتق من valid_until',
  `rca_trigger`      ENUM('repeat_within_validity','safety_critical','high_cost','manual') NOT NULL,
  `rca_state`        ENUM('open','in_analysis','root_found','closed') NOT NULL DEFAULT 'open',
  `rca_ref`          VARCHAR(190) NOT NULL DEFAULT '',
  `root_cause`       VARCHAR(400) NOT NULL DEFAULT '',
  `decision_ar`      VARCHAR(400) NOT NULL DEFAULT '',
  `derivation_rule`  VARCHAR(60)  NOT NULL DEFAULT '',
  `created_by`       INT          NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `src_ref`          VARCHAR(190) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mrr` (`company_id`,`origin_order_id`,`repeat_date`),
  KEY `ix_mrr_eq` (`equipment_id`),
  CONSTRAINT `chk_mrr_rule`  CHECK (`derivation_rule` <> ''),
  CONSTRAINT `chk_mrr_close` CHECK (`rca_state` <> 'closed' OR (`root_cause` <> '' AND `decision_ar` <> '')),
  CONSTRAINT `chk_mrr_win`   CHECK (`within_validity` = 0 OR `days_since_cert` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-W07 MNT-15 - سجل اعادة الاصلاح وتحليل السبب الجذري'", 'mnt_repeat_repair');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ طلبُ صرفِ القطعِ والإصلاحُ الخارجيُّ والعنايةُ اليومية — MNT-10/11/13
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑥ الصرفُ والإحالةُ الخارجيةُ والعناية ────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `mnt_part_request` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT          NOT NULL,
  `req_no`        VARCHAR(40)  NOT NULL,
  `order_id`      INT          NOT NULL,
  `request_date`  DATE         NULL,
  `warehouse_ref` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'مستورد للقراءة من المخازن',
  `lines_count`   INT          NOT NULL DEFAULT 0 COMMENT 'مشتق من mnt_order_part',
  `priority`      ENUM('normal','urgent') NOT NULL DEFAULT 'normal',
  `custodian_id`  INT          NULL COMMENT 'مستلم العهدة',
  `issue_doc_ref` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'رقم سند الصرف من المخازن',
  `receipt_match` ENUM('pending','matched','variance') NOT NULL DEFAULT 'pending',
  `state`         ENUM('draft','submitted','approved','issued','partially_issued','rejected','cancelled') NOT NULL DEFAULT 'draft',
  `state_rule`    VARCHAR(60)  NOT NULL DEFAULT '',
  `reject_reason` VARCHAR(400) NOT NULL DEFAULT '',
  `created_by`    INT          NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `src_ref`       VARCHAR(190) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mpr` (`company_id`,`req_no`),
  KEY `ix_mpr_order` (`order_id`),
  CONSTRAINT `chk_mpr_rule`  CHECK (`state_rule` <> ''),
  CONSTRAINT `chk_mpr_issue` CHECK (`state` NOT IN ('issued','partially_issued') OR `issue_doc_ref` <> ''),
  CONSTRAINT `chk_mpr_rej`   CHECK (`state` <> 'rejected' OR `reject_reason` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-W07 MNT-10 - طلب صرف القطع لامر العمل - لا صرف لامر مقفل'", 'mnt_part_request');

$run("
CREATE TABLE IF NOT EXISTS `mnt_external_repair` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT          NOT NULL,
  `order_id`       INT          NOT NULL,
  `line_kind`      ENUM('external_referral','warranty_claim') NOT NULL,
  `vendor_id`      INT          NULL COMMENT 'الجهة الخارجية او مورد المعدة',
  `contract_ref`   VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'مرجع العقد او الضمان',
  `scope_ar`       VARCHAR(400) NOT NULL DEFAULT '',
  `estimated_cost` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `actual_cost`    DECIMAL(14,2) NOT NULL DEFAULT 0,
  `claim_result`   ENUM('pending','accepted','partial','rejected') NOT NULL DEFAULT 'pending',
  `receipt_ref`    VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'محضر الاستلام',
  `state`          ENUM('draft','sent','in_progress','received','closed','cancelled') NOT NULL DEFAULT 'draft',
  `state_rule`     VARCHAR(60)  NOT NULL DEFAULT '',
  `created_by`     INT          NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `src_ref`        VARCHAR(190) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `ix_mer_order` (`order_id`),
  CONSTRAINT `chk_mer_rule` CHECK (`state_rule` <> ''),
  CONSTRAINT `chk_mer_ref`  CHECK (`line_kind` <> 'warranty_claim' OR `contract_ref` <> ''),
  CONSTRAINT `chk_mer_recv` CHECK (`state` NOT IN ('received','closed') OR `receipt_ref` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-W07 MNT-11 - الاصلاح الخارجي ومطالبات الضمان'", 'mnt_external_repair');

$run("
CREATE TABLE IF NOT EXISTS `mnt_daily_care` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT          NOT NULL,
  `care_date`      DATE         NOT NULL,
  `equipment_id`   INT          NOT NULL,
  `checklist_ref`  VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'قائمة العناية للنوع',
  `task_key`       VARCHAR(60)  NOT NULL,
  `task_ar`        VARCHAR(190) NOT NULL DEFAULT '',
  `performed_by`   INT          NULL,
  `result`         ENUM('ok','abnormal','not_done') NOT NULL DEFAULT 'ok',
  `abnormal_note`  VARCHAR(400) NOT NULL DEFAULT '',
  `breakdown_id`   INT          NULL COMMENT 'بلاغ متفرع - مشتق لا يدخل',
  `state`          ENUM('open','closed') NOT NULL DEFAULT 'open',
  `state_rule`     VARCHAR(60)  NOT NULL DEFAULT '',
  `created_by`     INT          NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `src_ref`        VARCHAR(190) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mdc` (`company_id`,`equipment_id`,`care_date`,`task_key`),
  KEY `ix_mdc_eq` (`equipment_id`,`care_date`),
  CONSTRAINT `chk_mdc_rule` CHECK (`state_rule` <> ''),
  CONSTRAINT `chk_mdc_abn`  CHECK (`result` <> 'abnormal' OR `abnormal_note` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-W07 MNT-13 - العناية اليومية والتشحيم'", 'mnt_daily_care');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑦ مؤشّراتُ الصيانةِ والنقل — مشتقّةٌ بلا إدخال (MNT-16 · TRP-13)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑦ المؤشّراتُ المشتقّة ─────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `mnt_kpi_period` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`        INT          NOT NULL,
  `period`            CHAR(7)      NOT NULL COMMENT 'YYYY-MM',
  `scope_kind`        ENUM('equipment','category') NOT NULL DEFAULT 'equipment',
  `scope_ref`         INT          NOT NULL DEFAULT 0,
  `breakdowns`        INT          NOT NULL DEFAULT 0,
  `mtbf_hours`        DECIMAL(14,2) NOT NULL DEFAULT 0,
  `mttr_hours`        DECIMAL(14,2) NOT NULL DEFAULT 0,
  `readiness_pct`     DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'مستورد من asset_readiness لا يحسب هنا',
  `pm_done`           INT          NOT NULL DEFAULT 0,
  `pm_due`            INT          NOT NULL DEFAULT 0,
  `pm_compliance_pct` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `cost_per_hour`     DECIMAL(14,4) NOT NULL DEFAULT 0,
  `derivation_rule`   VARCHAR(60)  NOT NULL DEFAULT '',
  `derived_from`      VARCHAR(300) NOT NULL DEFAULT '',
  `derived_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mkp` (`company_id`,`period`,`scope_kind`,`scope_ref`),
  CONSTRAINT `chk_mkp_rule` CHECK (`derivation_rule` <> '' AND `derived_from` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-W07 MNT-16 - مؤشرات الصيانة الدورية مشتقة بلا ادخال'", 'mnt_kpi_period');

$run("
CREATE TABLE IF NOT EXISTS `trp_kpi_period` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`      INT          NOT NULL,
  `period`          CHAR(7)      NOT NULL,
  `orders_total`    INT          NOT NULL DEFAULT 0,
  `orders_closed`   INT          NOT NULL DEFAULT 0,
  `avg_trip_hours`  DECIMAL(14,2) NOT NULL DEFAULT 0,
  `on_time_pct`     DECIMAL(6,2) NOT NULL DEFAULT 0,
  `incidents`       INT          NOT NULL DEFAULT 0,
  `total_cost`      DECIMAL(16,2) NOT NULL DEFAULT 0,
  `cost_per_km`     DECIMAL(14,4) NOT NULL DEFAULT 0,
  `by_carrier`      VARCHAR(400) NOT NULL DEFAULT '',
  `derivation_rule` VARCHAR(60)  NOT NULL DEFAULT '',
  `derived_from`    VARCHAR(300) NOT NULL DEFAULT '',
  `derived_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tkp` (`company_id`,`period`),
  CONSTRAINT `chk_tkp_rule` CHECK (`derivation_rule` <> '' AND `derived_from` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-W07 TRP-13 - تقرير اوامر الترحيل مشتق بلا ادخال'", 'trp_kpi_period');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑧ دورةُ الترحيل — التجهيزُ والمراحلُ والمطالباتُ والإقفال
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑧ دورةُ الترحيل ───────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `trp_origin_handover` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT          NOT NULL,
  `order_id`      INT UNSIGNED NOT NULL,
  `item_key`      VARCHAR(60)  NOT NULL COMMENT 'بند التجهيز',
  `item_ar`       VARCHAR(190) NOT NULL DEFAULT '',
  `performed_by`  INT          NULL,
  `result`        ENUM('pending','ok','failed','na') NOT NULL DEFAULT 'pending',
  `handover_ref`  VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'محضر التسليم الاصلي',
  `photo_ref`     VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'صور حالة ما قبل النقل',
  `route_risk`    ENUM('low','medium','high') NULL COMMENT 'تقييم مخاطر المسار',
  `done_at`       DATETIME     NULL,
  `state`         ENUM('open','completed','blocked') NOT NULL DEFAULT 'open',
  `state_rule`    VARCHAR(60)  NOT NULL DEFAULT '',
  `created_by`    INT          NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `src_ref`       VARCHAR(190) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_toh` (`company_id`,`order_id`,`item_key`),
  KEY `ix_toh_order` (`order_id`),
  CONSTRAINT `chk_toh_rule` CHECK (`state_rule` <> ''),
  CONSTRAINT `chk_toh_done` CHECK (`result` NOT IN ('ok','failed') OR `done_at` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-W07 TRP-06 - تجهيز المغادرة والتسليم الاصلي'", 'trp_origin_handover');

$run("
CREATE TABLE IF NOT EXISTS `trp_trip_leg` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`       INT          NOT NULL,
  `order_id`         INT UNSIGNED NOT NULL,
  `leg_seq`          INT          NOT NULL,
  `from_point`       VARCHAR(190) NOT NULL DEFAULT '',
  `to_point`         VARCHAR(190) NOT NULL DEFAULT '',
  `vehicle_id`       INT          NULL,
  `driver_id`        INT          NULL,
  `distance_km`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `started_at`       DATETIME     NULL,
  `ended_at`         DATETIME     NULL,
  `handover_to_next` TINYINT(1)   NOT NULL DEFAULT 0,
  `events_count`     INT          NOT NULL DEFAULT 0 COMMENT 'مشتق من transfer_events',
  `state`            ENUM('planned','in_transit','arrived','handed_over','cancelled') NOT NULL DEFAULT 'planned',
  `state_rule`       VARCHAR(60)  NOT NULL DEFAULT '',
  `created_by`       INT          NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `src_ref`          VARCHAR(190) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ttl` (`company_id`,`order_id`,`leg_seq`),
  KEY `ix_ttl_order` (`order_id`),
  CONSTRAINT `chk_ttl_rule`  CHECK (`state_rule` <> ''),
  CONSTRAINT `chk_ttl_seq`   CHECK (`leg_seq` > 0),
  CONSTRAINT `chk_ttl_span`  CHECK (`ended_at` IS NULL OR `started_at` IS NULL OR `ended_at` >= `started_at`),
  CONSTRAINT `chk_ttl_start` CHECK (`state` NOT IN ('in_transit','arrived','handed_over') OR `started_at` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-W07 TRP-07 - مراحل الرحلة Trip Legs'", 'trp_trip_leg');

$run("
CREATE TABLE IF NOT EXISTS `trp_damage_claim` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`        INT          NOT NULL,
  `claim_no`          VARCHAR(40)  NOT NULL,
  `order_id`          INT UNSIGNED NOT NULL,
  `incident_ref`      VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'مرجع الواقعة في المحضر او حدث الرحلة',
  `damage_desc`       VARCHAR(400) NOT NULL DEFAULT '',
  `liable_party`      ENUM('carrier','driver','client','company','third_party','undetermined') NOT NULL DEFAULT 'undetermined',
  `liable_rule`       VARCHAR(60)  NOT NULL DEFAULT '' COMMENT 'قاعدة المتحمل من العقد',
  `claim_amount`      DECIMAL(16,2) NOT NULL DEFAULT 0,
  `evidence_ref`      VARCHAR(190) NOT NULL DEFAULT '',
  `claim_route`       ENUM('insurance','carrier','client','internal') NOT NULL DEFAULT 'internal',
  `settlement_ar`     VARCHAR(400) NOT NULL DEFAULT '',
  `settlement_amount` DECIMAL(16,2) NOT NULL DEFAULT 0,
  `state`             ENUM('draft','submitted','under_review','settled','rejected','closed') NOT NULL DEFAULT 'draft',
  `state_rule`        VARCHAR(60)  NOT NULL DEFAULT '',
  `reject_reason`     VARCHAR(400) NOT NULL DEFAULT '',
  `reviewed_by`       INT          NULL,
  `approved_by`       INT          NULL,
  `approved_at`       DATETIME     NULL,
  `created_by`        INT          NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `src_ref`           VARCHAR(190) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tdc` (`company_id`,`claim_no`),
  KEY `ix_tdc_order` (`order_id`),
  CONSTRAINT `chk_tdc_rule` CHECK (`state_rule` <> ''),
  CONSTRAINT `chk_tdc_evid` CHECK (`state` = 'draft' OR (`incident_ref` <> '' AND `damage_desc` <> '')),
  CONSTRAINT `chk_tdc_set`  CHECK (`state` <> 'settled' OR (`settlement_ar` <> '' AND `approved_by` IS NOT NULL)),
  CONSTRAINT `chk_tdc_rej`  CHECK (`state` <> 'rejected' OR `reject_reason` <> ''),
  CONSTRAINT `chk_tdc_liab` CHECK (`liable_party` = 'undetermined' OR `liable_rule` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-W07 TRP-10 - مطالبات التلف والحوادث'", 'trp_damage_claim');

/* ⛔ الحبّةُ مفصولةٌ نصًّا (`TRP-12`): البنودُ في سجلِّها والإقفالُ واقعةٌ واحدة */
$run("
CREATE TABLE IF NOT EXISTS `trp_closure` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`       INT          NOT NULL,
  `order_id`         INT UNSIGNED NOT NULL,
  `delivery_doc_id`  BIGINT UNSIGNED NULL COMMENT 'محضر الاستلام - لا اقفال قبله',
  `cost_lines_count` INT          NOT NULL DEFAULT 0 COMMENT 'مشتق من transfer_cost_lines',
  `total_cost`       DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'مشتق لا يدخل',
  `bearer_split`     VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'التوزيع بالمتحمل - مشتق',
  `meter_posted`     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'ترحيل قراءة العداد الى كرت المعدة',
  `meter_ref`        VARCHAR(120) NOT NULL DEFAULT '',
  `finance_ref`      VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'الاحالة للمالية',
  `note_ar`          VARCHAR(400) NOT NULL DEFAULT '',
  `state`            ENUM('draft','submitted','approved','rejected','reopened') NOT NULL DEFAULT 'draft',
  `state_rule`       VARCHAR(60)  NOT NULL DEFAULT '',
  `reject_reason`    VARCHAR(400) NOT NULL DEFAULT '',
  `reopen_reason`    VARCHAR(400) NOT NULL DEFAULT '',
  `derivation_rule`  VARCHAR(60)  NOT NULL DEFAULT '',
  `reviewed_by`      INT          NULL,
  `approved_by`      INT          NULL,
  `approved_at`      DATETIME     NULL,
  `created_by`       INT          NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `src_ref`          VARCHAR(190) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tcl` (`company_id`,`order_id`),
  CONSTRAINT `chk_tcl_rule` CHECK (`state_rule` <> '' AND `derivation_rule` <> ''),
  CONSTRAINT `chk_tcl_doc`  CHECK (`state` NOT IN ('submitted','approved') OR `delivery_doc_id` IS NOT NULL),
  CONSTRAINT `chk_tcl_appr` CHECK (`state` <> 'approved' OR (`approved_by` IS NOT NULL AND `approved_at` IS NOT NULL AND `meter_posted` = 1)),
  CONSTRAINT `chk_tcl_rej`  CHECK (`state` <> 'rejected' OR `reject_reason` <> ''),
  CONSTRAINT `chk_tcl_reop` CHECK (`state` <> 'reopened' OR `reopen_reason` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-W07 TRP-12 - اقفال امر الترحيل - حبة مفصولة عن بنود التكلفة'", 'trp_closure');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑨ بذرةُ العتباتِ — الأرقامُ من السجلِّ لا من الشيفرة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑨ بذرةُ العتبات ──────────────────────────────────────────────\n";
$TH = array(
  array('W7_CERT_VALID_DAYS_SAFETY', 30, 'يوم', 'صلاحية شهادة العودة للعطل الحرج للسلامة',
        'التكرار خلال الصلاحية يفتح تحليل السبب الجذري — والنافذة تضبط هنا ولا تكتب في الشيفرة', 'DEC-OPEN-12'),
  array('W7_CERT_VALID_DAYS_MAJOR', 60, 'يوم', 'صلاحية شهادة العودة للعطل الرئيسي',
        'الرئيسي دون الحرج في المخاطر فنافذته اوسع — والقيمة قرار مالك يضبط هنا', 'DEC-OPEN-12'),
  array('W7_CERT_VALID_DAYS_MINOR', 0, 'يوم', 'صلاحية شهادة العودة للعطل البسيط',
        'البسيط لا يوجب شهادة مستقلة فصلاحيته صفر ولا شهادة تصدر له', 'DEC-OPEN-12'),
  array('W7_PM_TOLERANCE_HOURS', 50, 'ساعة', 'سماح الخطة الوقائية بالساعات',
        'الفاصل من خطة الصانع او خطة الصيانة والسماح رقم يضبط — ولا مقارنة صلبة في اداة', 'MNT-12'),
  array('W7_OPS_IMPACT_HIGH_HOURS', 24, 'ساعة', 'حد الاثر التشغيلي المرتفع بساعات التوقف',
        'المحور الثالث مستقل عن السلامة — ومدة التوقف تحرك الاثر التشغيلي لا تصنيف السلامة', 'DEC-OPEN-12'),
  array('W7_OPS_IMPACT_CRITICAL_HOURS', 72, 'ساعة', 'حد الاثر التشغيلي الحرج بساعات التوقف',
        'الحد الاعلى للمحور الثالث — ولا يغير خطورة السلامة ابدا', 'DEC-OPEN-12'),
  array('W7_REPEAT_WINDOW_DAYS', 30, 'يوم', 'نافذة رصد تكرار العطل على العقدة نفسها',
        'التكرار خلال الصلاحية يفتح التحليل — والنافذة تقرأ ولا تكتب', 'MNT-15'),
  array('W7_PERMIT_EXPIRY_GRACE_DAYS', 0, 'يوم', 'سماح انتهاء تصريح المسار قبل المغادرة',
        'لا مغادرة لحمولة استثنائية بتصريح منته — والسماح صفر بقرار لا بسهو', 'TRP-05'),
);
foreach ($TH as $t) {
    $run("INSERT INTO `repair01_w7_thresholds`
          (threshold_key, value_num, unit_ar, title_ar, why, decision_ref, src_ref)
          VALUES ('" . $conn->real_escape_string($t[0]) . "'," . (float) $t[1] . ",
                  '" . $conn->real_escape_string($t[2]) . "','" . $conn->real_escape_string($t[3]) . "',
                  '" . $conn->real_escape_string($t[4]) . "','" . $conn->real_escape_string($t[5]) . "',
                  'RPR-W07 §٥ · عتبة من السجل')
          ON DUPLICATE KEY UPDATE title_ar=VALUES(title_ar), why=VALUES(why), unit_ar=VALUES(unit_ar)",
         'عتبة ' . $t[0]);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ⑩ مصدرُ قراءةِ العدّاد «استلامُ ترحيل» — TRP-09
   ═══════════════════════════════════════════════════════════════════════════
   ◆ `TRP-09` نصًّا: «قراءةُ العدّادِ عند الاستلامِ إلزاميّةٌ وتُسجَّل في كرتِ
     المعدّةِ **بمصدرِ «استلام»**». والمقيسُ أنَّ `meter_readings.source` يحمل
     أربعَ مفرداتٍ ليس فيها الاستلام — فالقراءةُ الآتيةُ من محضرِ ترحيلٍ تُقيَّد
     اليومَ `manual` وتختلط بما أدخله موظّفٌ بيدِه.
   ⛔ **ولا يُوسَّع الـ`ENUM` بلا مفردةٍ عربيّةٍ معلَنة**: `W6-09` يسقط على رمزٍ
     داخليٍّ خامٍّ يُعرَض، فالرمزُ يُسجَّل في `repair01_w6_code_dict` قبل أن يُصيَّر.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑩ مصدرُ قراءةِ العدّاد — استلامُ ترحيل ───────────────────────\n";
if (w7_tbl($conn, 'meter_readings')) {
    $col = $conn->query("SHOW COLUMNS FROM `meter_readings` LIKE 'source'");
    $cur = ($col && $col->num_rows) ? (string) $col->fetch_assoc()['Type'] : '';
    if (strpos($cur, "'transfer'") !== false) { echo "  ↷ meter_readings.source يحمل transfer سلفًا\n"; $skip++; }
    else {
        $run("ALTER TABLE `meter_readings` MODIFY COLUMN `source`
              ENUM('manual','inspection','timesheet','reset','transfer') NOT NULL DEFAULT 'manual'",
             'meter_readings.source ← transfer');
    }
}
if (w7_tbl($conn, 'repair01_w6_code_dict')) {
    $run("INSERT INTO `repair01_w6_code_dict`
          (raw_code, display_ar, display_short, code_family, allowed_context, why, src_ref)
          VALUES ('transfer','قراءة عند استلام الترحيل','استلام ترحيل','ENUM','SCREEN',
                  'مفردة مصدر قراءة عداد اضيفت في RPR-W07 لان TRP-09 يوجب مصدر الاستلام',
                  'RPR-W07 §١٠ · meter_readings.source')
          ON DUPLICATE KEY UPDATE display_ar=VALUES(display_ar), why=VALUES(why)",
         'قاموس الرمز transfer');
}

echo "\n───────────────────────────────────────────────────────────────\n";
printf("W07 migration: نُفِّذ %d · تُخطّي %d · أخطاء %d\n", $done, $skip, $err);
echo 'الحكم: ' . ($err === 0 ? "تمّت ✔\n" : "بأخطاء ✘\n");
exit($err === 0 ? 0 : 1);
