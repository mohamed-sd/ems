<?php
/**
 * 2027_11_30_repair01_w12_financing.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W12 — **التمويلُ والممولون: فصلُ الإقفالاتِ الثلاثةِ وفصلُ التاريخيّ**
 *
 * ◆ **ثلاثةُ إقفالاتٍ ثلاثةُ كياناتٍ لا ثلاثُ حالاتٍ لكيانٍ واحد** (§22):
 *   `fin_contract_close` (‏تعاقديّ · ممول × عملية × فترة تعاقديّة) ·
 *   `fin_monthly_close` (‏شهريّ · ممول × عملية × شهر تقويميّ × عملة) ·
 *   `fin_final_close` (‏نهائيّ · عمليةٌ واحدةٌ مرّةً واحدة).
 *   ولكلٍّ **جدولُه ومفتاحُه وحبّتُه**، و`CHECK` على `close_kind` يمنع أن يحمل
 *   جدولٌ صنفَ إقفالٍ ليس صنفَه — فصفٌّ واحدٌ لا يخدم معنيَين.
 *
 * ◆ **والشهريُّ شهرٌ تقويميٌّ بقيدِ القاعدةِ لا بالنيّة**: `DAYOFMONTH=1` و
 *   `LAST_DAY` — ففترةٌ تعاقديّةٌ تُدسُّ في جدولِ الشهريِّ تُردّ. **والتعاقديُّ
 *   يلزمه رقمُ فترتِه التعاقديّة** فلا يصير شهرًا مقنَّعًا.
 *
 * ◆ **وأمرُ الدفعِ المستقبليُّ لا يرث محدوديّةَ التاريخيّ** (§22):
 *   `fin_payment_order` طبقةُ **`FUTURE` وحدَها** بقيدِ `CHECK`، وحقولُ
 *   نموذجِه **غيرُ قابلةٍ للعدم** (‏طالبٌ · تاريخُ طلبٍ · مبلغٌ · عملة)؛
 *   و`fin_legacy_payment_aggregate` طبقةُ **`LEGACY` وحدَها** بحجّيّتِها
 *   ومرجعِ صفِّها الأصليّ، و**`allocatable = 0` بقيدٍ** فلا تُخصَّص كأنّها أمرٌ.
 *   ⛔ **ولا يُصمَّم المستقبلُ على قياسِ ما تستطيعه الصفوفُ المجمَّعة.**
 *
 * ◆ **والأثرُ الماليُّ يُقرأ من الأحداثِ ولا يُكتب قيدًا** (§48): النطاقُ يصدر
 *   **طلبَ اعترافٍ** إلى `acc_recognition_request` (‏بابُ W11 الواحد)، و`CHECK`
 *   هناك يمنع أن يكون مصدرُه الماليّةَ نفسَها. ولا سطرَ في `fin_journal_*` من هنا.
 *
 * ◆ **والحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): كلُّ جدولٍ هنا يحمل
 *   `company_id` **غيرَ قابلٍ للعدم**.
 *
 * ◆ **و`CHECK` لا قادِح**: مستخدمُ الهجراتِ لا يملك `SUPER` (‏W03 · 2027_11_19).
 *
 * ⛔ **ولا عتبةٌ رقميّةٌ في الشيفرة** — كلُّها في `repair01_w12_thresholds`.
 * ⛔ **ولا يُمَسُّ عمودٌ حيٌّ بحذفٍ أو إعادةِ تعريف** — الإضافةُ وحدَها.
 *
 * التشغيل: php database/migrations/2027_11_30_repair01_w12_financing.php
 *          (⛔ لا `migrate.php up` — الهجرةُ بمسارِها الكاملِ منفردةً)
 * التراجع: php database/migrations/2027_11_30_repair01_w12_financing_down.php
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

function w12_tbl(mysqli $c, $t)
{
    $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}
function w12_col(mysqli $c, $t, $col)
{
    if (!w12_tbl($c, $t)) { return false; }
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

$done = 0; $err = 0; $skip = 0;
$run = function ($sql, $label) use ($conn, &$done, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; return true; }
    echo "  ✘ $label — " . $conn->error . "\n"; $err++; return false;
};
$addCol = function ($t, $col, $ddl) use ($conn, &$done, &$skip, &$err) {
    if (!w12_tbl($conn, $t)) { echo "  ⚠ $t غير موجود — $col يُتخطّى\n"; $skip++; return; }
    if (w12_col($conn, $t, $col)) { echo "  ↷ $t.$col قائم\n"; $skip++; return; }
    if ($conn->query("ALTER TABLE `$t` ADD COLUMN $ddl") === true) { echo "  ✔ $t.$col\n"; $done++; }
    else { echo "  ✘ $t.$col — " . $conn->error . "\n"; $err++; }
};

echo "══ REPAIR01 · W12 — التمويلُ والممولون ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ① دفاترُ المرحلة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① دفاترُ المرحلة ───────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w12_scope` (
  `requirement_id`   VARCHAR(48)  NOT NULL,
  `unit`             VARCHAR(160) NOT NULL DEFAULT '',
  `group_name`       VARCHAR(160) NOT NULL DEFAULT '',
  `surface`          VARCHAR(255) NOT NULL DEFAULT '',
  `anchor_screen_id` VARCHAR(12)  NOT NULL DEFAULT '',
  `anchor_route`     VARCHAR(200) NOT NULL DEFAULT '',
  `anchor_probe`     VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'الجدول او الخدمة التي تثبت المرساة قياسا',
  `owner_measured`   VARCHAR(12)  NOT NULL DEFAULT '',
  `owner_expected`   VARCHAR(12)  NOT NULL DEFAULT '',
  `owner_verdict`    VARCHAR(32)  NOT NULL DEFAULT '',
  `build_verdict`    VARCHAR(24)  NOT NULL DEFAULT '',
  `cycle_step`       SMALLINT     NOT NULL DEFAULT 0 COMMENT 'موضع السطح من دورة التمويل لا من الابجدية',
  `entity_scoped`    TINYINT(1)   NOT NULL DEFAULT 0,
  `close_kind`       VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'صنف الاقفال ان كان السطح اقفالا',
  `payment_layer`    VARCHAR(8)   NOT NULL DEFAULT '' COMMENT 'FUTURE او LEGACY ان كان السطح دفعا',
  `map_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `map_why`          VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`          VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`requirement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - نطاق المرحلة ومرساة كل متطلب'", 'repair01_w12_scope');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w12_sidebar` (
  `screen_id`       VARCHAR(12)  NOT NULL,
  `route`           VARCHAR(200) NOT NULL DEFAULT '',
  `owner_code`      VARCHAR(12)  NOT NULL DEFAULT '',
  `s1_verdict`      VARCHAR(32)  NOT NULL DEFAULT '',
  `s1_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `s2_label_live`   VARCHAR(255) NOT NULL DEFAULT '',
  `s2_label_canon`  VARCHAR(255) NOT NULL DEFAULT '',
  `s2_verdict`      VARCHAR(32)  NOT NULL DEFAULT '',
  `s2_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `s3_group_live`   VARCHAR(160) NOT NULL DEFAULT '',
  `s3_group_canon`  VARCHAR(160) NOT NULL DEFAULT '',
  `s3_verdict`      VARCHAR(32)  NOT NULL DEFAULT '',
  `s3_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `s4_order_src`    VARCHAR(48)  NOT NULL DEFAULT '',
  `s4_order_no`     INT          NOT NULL DEFAULT 0,
  `s4_cycle_step`   SMALLINT     NOT NULL DEFAULT 0,
  `s4_verdict`      VARCHAR(32)  NOT NULL DEFAULT '',
  `s4_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `s5_parent`       VARCHAR(12)  NOT NULL DEFAULT '',
  `s5_verdict`      VARCHAR(32)  NOT NULL DEFAULT '',
  `s5_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `s5_why`          VARCHAR(400) NOT NULL DEFAULT '',
  `s6_visibility`   VARCHAR(24)  NOT NULL DEFAULT '',
  `s6_perm_rows`    INT          NOT NULL DEFAULT 0,
  `s6_guard_kind`   VARCHAR(32)  NOT NULL DEFAULT '',
  `s6_verdict`      VARCHAR(32)  NOT NULL DEFAULT '',
  `s6_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `s7_linked`       TINYINT(1)   NOT NULL DEFAULT 0,
  `s7_verdict`      VARCHAR(32)  NOT NULL DEFAULT '',
  `s7_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `measured_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`screen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - سبع خطوات السايدبار بحكم وقاعدة'", 'repair01_w12_sidebar');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w12_decisions` (
  `decision_id` VARCHAR(16)  NOT NULL,
  `question`    VARCHAR(400) NOT NULL DEFAULT '',
  `answer`      VARCHAR(400) NOT NULL DEFAULT '',
  `rationale`   VARCHAR(900) NOT NULL DEFAULT '',
  `scope_rows`  INT          NOT NULL DEFAULT 0,
  `decided_at`  DATE         NOT NULL,
  `src_ref`     VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`decision_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - قرارات المرحلة'", 'repair01_w12_decisions');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w12_journey` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id`          VARCHAR(40)  NOT NULL,
  `station_no`      SMALLINT     NOT NULL DEFAULT 0,
  `leg`             VARCHAR(48)  NOT NULL DEFAULT '',
  `station`         VARCHAR(255) NOT NULL DEFAULT '',
  `entity`          VARCHAR(80)  NOT NULL DEFAULT '',
  `consumer`        VARCHAR(160) NOT NULL DEFAULT '',
  `expected`        VARCHAR(400) NOT NULL DEFAULT '',
  `measured`        VARCHAR(400) NOT NULL DEFAULT '',
  `business_effect` VARCHAR(400) NOT NULL DEFAULT '',
  `state_after`     VARCHAR(80)  NOT NULL DEFAULT '',
  `company_id`      INT          NOT NULL DEFAULT 0,
  `passed`          TINYINT(1)   NOT NULL DEFAULT 0,
  `measured_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_w12j_run` (`run_id`,`station_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - محطات رحلة التمويل بمقيسها'", 'repair01_w12_journey');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w12_states` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity`        VARCHAR(64)  NOT NULL,
  `from_state`    VARCHAR(40)  NOT NULL DEFAULT '',
  `to_state`      VARCHAR(40)  NOT NULL DEFAULT '',
  `allowed`       TINYINT(1)   NOT NULL DEFAULT 1,
  `owner_role`    VARCHAR(120) NOT NULL DEFAULT '',
  `preconditions` VARCHAR(600) NOT NULL DEFAULT '',
  `output_doc`    VARCHAR(255) NOT NULL DEFAULT '',
  `approval_gate` VARCHAR(160) NOT NULL DEFAULT '',
  `reopen_rule`   VARCHAR(400) NOT NULL DEFAULT '',
  `correct_rule`  VARCHAR(400) NOT NULL DEFAULT '',
  `forbid_why`    VARCHAR(600) NOT NULL DEFAULT '',
  `src_ref`       VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_w12st` (`entity`,`from_state`,`to_state`),
  CONSTRAINT `chk_w12st_forbid` CHECK (`allowed` = 1 OR `forbid_why` <> ''),
  CONSTRAINT `chk_w12st_owner`  CHECK (`allowed` = 0 OR (`owner_role` <> '' AND `preconditions` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - الة حالة كل كيان بممنوعها المسبب'", 'repair01_w12_states');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w12_sod` (
  `process_key`       VARCHAR(64)  NOT NULL,
  `process_name`      VARCHAR(255) NOT NULL DEFAULT '',
  `initiator_role`    VARCHAR(120) NOT NULL DEFAULT '',
  `reviewer_role`     VARCHAR(120) NOT NULL DEFAULT '',
  `approver_role`     VARCHAR(120) NOT NULL DEFAULT '',
  `executor_role`     VARCHAR(120) NOT NULL DEFAULT '',
  `closer_role`       VARCHAR(120) NOT NULL DEFAULT '',
  `forbidden_combo`   VARCHAR(400) NOT NULL DEFAULT '',
  `enforced_by`       VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'رمز الرد الذي ينفذ المنع',
  `authority_rule_id` VARCHAR(48)  NOT NULL DEFAULT '',
  `deputy_role`       VARCHAR(120) NOT NULL DEFAULT '',
  `scope_rule`        VARCHAR(255) NOT NULL DEFAULT '',
  `delegation`        VARCHAR(255) NOT NULL DEFAULT '',
  `effective_date`    DATE         NOT NULL,
  `src_ref`           VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`process_key`),
  CONSTRAINT `chk_w12sod_full` CHECK (`forbidden_combo` <> '' AND `enforced_by` <> ''
                                      AND `authority_rule_id` <> '' AND `initiator_role` <> ''
                                      AND `approver_role` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - فصل الواجبات بستة ادوار وتركيبة ممنوعة'", 'repair01_w12_sod');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w12_thresholds` (
  `threshold_key` VARCHAR(48)  NOT NULL,
  `value_num`     DECIMAL(18,4) NOT NULL DEFAULT 0,
  `unit_ar`       VARCHAR(48)  NOT NULL DEFAULT '',
  `title_ar`      VARCHAR(160) NOT NULL DEFAULT '',
  `why`           VARCHAR(400) NOT NULL DEFAULT '',
  `decision_ref`  VARCHAR(24)  NOT NULL DEFAULT '',
  `src_ref`       VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`threshold_key`),
  CONSTRAINT `chk_w12th_why` CHECK (`why` <> '' AND `decision_ref` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - العتبات تقرا ولا تكتب في شيفرة'", 'repair01_w12_thresholds');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w12_fixes` (
  `fix_key`     VARCHAR(64)  NOT NULL,
  `title`       VARCHAR(255) NOT NULL DEFAULT '',
  `revealed_by` VARCHAR(48)  NOT NULL DEFAULT '' COMMENT 'المتطلب الكاشف — لا اصلاح بلا كاشف',
  `before_num`  VARCHAR(80)  NOT NULL DEFAULT '',
  `after_num`   VARCHAR(80)  NOT NULL DEFAULT '',
  `why`         VARCHAR(600) NOT NULL DEFAULT '',
  `src_ref`     VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`fix_key`),
  CONSTRAINT `chk_w12fix_rev` CHECK (`revealed_by` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - كل اصلاح بمتطلبه الكاشف'", 'repair01_w12_fixes');

/* ── دفترُ نقلِ بنودِ القائمة — **الإرجاعُ يعيدُ ما نقل لا يتركه يتيمًا** ────
   ◆ الخطوةُ ③ تنقل بندًا حيًّا إلى مجموعةٍ مختومةٍ بموجتِه بدل تسميةِ مجموعةٍ
     مشتركة. وإرجاعٌ يحذف المجموعةَ المختومةَ **يترك البندَ يشير إلى صفٍّ لا
     وجودَ له** — وهو أثرٌ باقٍ أسوأُ من عدمِ الإرجاع. فيُقيَّد الموضعُ الأصليُّ
     لحظةَ النقلِ ويُعاد إليه حرفًا. */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w12_nav_moves` (
  `nav_item_id`   INT UNSIGNED NOT NULL,
  `route`         VARCHAR(200) NOT NULL DEFAULT '',
  `role_id`       INT          NOT NULL DEFAULT 0,
  `from_group_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'الموضع الاصلي — اليه يعود',
  `to_group_id`   INT UNSIGNED NOT NULL DEFAULT 0,
  `to_group_code` VARCHAR(48)  NOT NULL DEFAULT '',
  `why`           VARCHAR(400) NOT NULL DEFAULT '',
  `moved_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`nav_item_id`),
  CONSTRAINT `chk_w12mv_from` CHECK (`from_group_id` > 0 AND `why` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - موضع كل بند قبل نقله ليعود اليه بالارجاع'", 'repair01_w12_nav_moves');

/* ── دفترُ فصلِ الطبقتَين: تصميمُ المستقبلِ مقابلَ محدوديّةِ التاريخيّ ──── */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w12_layers` (
  `capability_key` VARCHAR(64)  NOT NULL COMMENT 'قدرة في نموذج امر الدفع المستقبلي',
  `title_ar`       VARCHAR(200) NOT NULL DEFAULT '',
  `future_column`  VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'العمود الذي يحملها في fin_payment_order',
  `future_required` TINYINT(1)  NOT NULL DEFAULT 0 COMMENT 'الزامي في نموذج المستقبل',
  `legacy_can_supply` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'هل تستطيع الصفوف المجمعة التاريخية توفيرها',
  `constrained_by_legacy` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'هل خفض التصميم ليناسب التاريخي',
  `why`            VARCHAR(600) NOT NULL DEFAULT '',
  `src_ref`        VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`capability_key`),
  CONSTRAINT `chk_w12layer_why` CHECK (`why` <> '' AND `future_column` <> ''),
  CONSTRAINT `chk_w12layer_free` CHECK (`constrained_by_legacy` = 0
                                        OR (`legacy_can_supply` = 0 AND `future_required` = 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - قدرات نموذج المستقبل ومقابلها في الطبقة التاريخية'", 'repair01_w12_layers');

/* ═══════════════════════════════════════════════════════════════════════════
   ② التأسيسُ المرجعيّ — الممولُ وجهاتُ اتّصالِه ووثائقُ عنايتِه
   ═══════════════════════════════════════════════════════════════════════════
   ⛔ **ولا سجلَّ ثانٍ للممول**: الممولُ كيانٌ في `legal_entities` بدورِه في
     `entity_roles` — وإنشاءُ جدولٍ ثالثٍ يشقُّ مصدرَ الحقيقةِ ويجعل للممولِ
     هويّتَين (‏درسُ `persons` في W03). فالأبناءُ هنا **يشيرون إلى الكيان**.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n② التأسيسُ المرجعيّ ──────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `fin_financier_contact` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `entity_id`     INT UNSIGNED NOT NULL COMMENT 'legal_entities.entity_id — الممول',
  `person_name`   VARCHAR(160) NOT NULL DEFAULT '',
  `role_ar`       VARCHAR(120) NOT NULL DEFAULT '',
  `is_authorized` TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'مفوض بالتوقيع',
  `mandate_ref`   VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'مستند التفويض',
  `phone`         VARCHAR(40)  NOT NULL DEFAULT '',
  `email`         VARCHAR(120) NOT NULL DEFAULT '',
  `valid_from`    DATE         NOT NULL,
  `valid_to`      DATE         NULL COMMENT 'فارغ = ساري — والنسخة مؤرخة لا تدهس',
  `state`         VARCHAR(16)  NOT NULL DEFAULT 'active',
  `created_by`    INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ffc_entity` (`company_id`,`entity_id`,`valid_from`),
  CONSTRAINT `chk_ffc_name` CHECK (`person_name` <> ''),
  CONSTRAINT `chk_ffc_mandate` CHECK (`is_authorized` = 0 OR `mandate_ref` <> ''),
  CONSTRAINT `chk_ffc_span` CHECK (`valid_to` IS NULL OR `valid_to` >= `valid_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - جهات اتصال الممول والمفوض عبر الزمن'", 'fin_financier_contact');

$run("
CREATE TABLE IF NOT EXISTS `fin_financier_document` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `entity_id`    INT UNSIGNED NOT NULL,
  `doc_kind`     VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'license او registry او tax او kyc او rating',
  `doc_ref`      VARCHAR(160) NOT NULL DEFAULT '',
  `issued_on`    DATE         NULL,
  `expires_on`   DATE         NULL,
  `verified_by`  INT UNSIGNED NOT NULL DEFAULT 0,
  `verified_at`  DATETIME     NULL,
  `state`        VARCHAR(16)  NOT NULL DEFAULT 'pending' COMMENT 'pending او verified او expired او rejected',
  `note`         VARCHAR(400) NOT NULL DEFAULT '',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ffd_entity` (`company_id`,`entity_id`,`doc_kind`),
  CONSTRAINT `chk_ffd_ref` CHECK (`doc_kind` <> '' AND `doc_ref` <> ''),
  CONSTRAINT `chk_ffd_ver` CHECK (`state` <> 'verified' OR `verified_by` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - وثائق التاهيل والعناية الواجبة للممول'", 'fin_financier_document');

$run("
CREATE TABLE IF NOT EXISTS `fin_ref_list` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `list_key`    VARCHAR(48)  NOT NULL COMMENT 'اسم القائمة — لاتيني يقارن',
  `item_code`   VARCHAR(48)  NOT NULL DEFAULT '',
  `field_name`  VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'تعريف حقل حين تكون السطر قاموسا',
  `definition`  VARCHAR(600) NOT NULL DEFAULT '',
  `owner_role`  VARCHAR(120) NOT NULL DEFAULT '',
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `src_ref`     VARCHAR(160) NOT NULL DEFAULT '',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_frl` (`company_id`,`list_key`,`item_code`,`field_name`),
  CONSTRAINT `chk_frl_def` CHECK (`definition` <> '' AND `owner_role` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - القوائم وقاموس البيانات للتمويل'", 'fin_ref_list');

/* ═══════════════════════════════════════════════════════════════════════════
   ③ الدورةُ — حاجةٌ ثمَّ عرضٌ ثمَّ مراجعةٌ ما قبل التعاقد
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n③ دورةُ ما قبل التعاقد ───────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `fin_funding_need` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `need_code`      VARCHAR(40)  NOT NULL,
  `title`          VARCHAR(255) NOT NULL DEFAULT '',
  `requester_dept` VARCHAR(120) NOT NULL DEFAULT '',
  `purpose`        VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'equipment او operational او supplier او general',
  `amount_needed`  DECIMAL(18,2) NOT NULL DEFAULT 0,
  `currency`       VARCHAR(8)   NOT NULL DEFAULT '',
  `needed_by`      DATE         NULL,
  `justification`  VARCHAR(600) NOT NULL DEFAULT '',
  `state`          VARCHAR(16)  NOT NULL DEFAULT 'draft' COMMENT 'draft او submitted او approved او rejected او sourced',
  `raised_by`      INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_by`    INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_at`    DATETIME     NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ffn_code` (`company_id`,`need_code`),
  CONSTRAINT `chk_ffn_amt` CHECK (`amount_needed` > 0 AND `currency` <> ''),
  CONSTRAINT `chk_ffn_why` CHECK (`state` = 'draft' OR `justification` <> ''),
  CONSTRAINT `chk_ffn_sod` CHECK (`state` <> 'approved' OR (`approved_by` > 0 AND `approved_by` <> `raised_by`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - حاجة تمويلية واحدة'", 'fin_funding_need');

$run("
CREATE TABLE IF NOT EXISTS `fin_funding_offer` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `offer_code`    VARCHAR(40)  NOT NULL,
  `need_id`       INT UNSIGNED NOT NULL DEFAULT 0,
  `entity_id`     INT UNSIGNED NOT NULL COMMENT 'الممول مقدم العرض',
  `version_no`    SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'طبقة اصدارات — التفاوض نسخ لا دهس',
  `model_code`    VARCHAR(32)  NOT NULL DEFAULT '',
  `principal`     DECIMAL(18,2) NOT NULL DEFAULT 0,
  `currency`      VARCHAR(8)   NOT NULL DEFAULT '',
  `profit_rate`   DECIMAL(9,4) NOT NULL DEFAULT 0,
  `tenor_months`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `grace_months`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `fees_total`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `collateral`    VARCHAR(400) NOT NULL DEFAULT '',
  `offer_doc_ref` VARCHAR(160) NOT NULL DEFAULT '',
  `received_on`   DATE         NULL,
  `valid_until`   DATE         NULL,
  `state`         VARCHAR(16)  NOT NULL DEFAULT 'received' COMMENT 'received او negotiating او shortlisted او accepted او declined او expired',
  `superseded_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_by`    INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ffo_ver` (`company_id`,`offer_code`,`version_no`),
  KEY `ix_ffo_need` (`company_id`,`need_id`,`state`),
  CONSTRAINT `chk_ffo_amt` CHECK (`principal` > 0 AND `currency` <> ''),
  CONSTRAINT `chk_ffo_doc` CHECK (`state` NOT IN ('shortlisted','accepted') OR `offer_doc_ref` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - عرض تمويل واحد بطبقة اصدارات'", 'fin_funding_offer');

$run("
CREATE TABLE IF NOT EXISTS `fin_precontract_review` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `review_code`   VARCHAR(40)  NOT NULL,
  `offer_id`      INT UNSIGNED NOT NULL DEFAULT 0,
  `legal_opinion` VARCHAR(600) NOT NULL DEFAULT '',
  `legal_by`      INT UNSIGNED NOT NULL DEFAULT 0,
  `finance_opinion` VARCHAR(600) NOT NULL DEFAULT '',
  `finance_by`    INT UNSIGNED NOT NULL DEFAULT 0,
  `risk_opinion`  VARCHAR(600) NOT NULL DEFAULT '',
  `risk_by`       INT UNSIGNED NOT NULL DEFAULT 0,
  `verdict`       VARCHAR(16)  NOT NULL DEFAULT 'pending' COMMENT 'pending او cleared او blocked',
  `blocking_reason` VARCHAR(600) NOT NULL DEFAULT '',
  `decided_by`    INT UNSIGNED NOT NULL DEFAULT 0,
  `decided_at`    DATETIME     NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fpr_code` (`company_id`,`review_code`),
  CONSTRAINT `chk_fpr_block` CHECK (`verdict` <> 'blocked' OR `blocking_reason` <> ''),
  CONSTRAINT `chk_fpr_clear` CHECK (`verdict` <> 'cleared'
                                    OR (`legal_by` > 0 AND `finance_by` > 0 AND `decided_by` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - مراجعة ما قبل التعاقد براي كل جهة'", 'fin_precontract_review');

/* ═══════════════════════════════════════════════════════════════════════════
   ④ التعاقدُ — العقدُ وبنودُه ومصفوفةُ التزاماتِه
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n④ التعاقد ────────────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `fin_finance_contract` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `contract_code`  VARCHAR(40)  NOT NULL,
  `entity_id`      INT UNSIGNED NOT NULL COMMENT 'الممول',
  `offer_id`       INT UNSIGNED NOT NULL DEFAULT 0,
  `review_id`      INT UNSIGNED NOT NULL DEFAULT 0,
  `op_id`          INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'financing_operations.op_id',
  `model_code`     VARCHAR(32)  NOT NULL DEFAULT '',
  `principal`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `currency`       VARCHAR(8)   NOT NULL DEFAULT '',
  `signed_on`      DATE         NULL,
  `start_on`       DATE         NULL,
  `end_on`         DATE         NULL,
  `contract_doc_ref` VARCHAR(160) NOT NULL DEFAULT '',
  `periods_total`  SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'عدد الفترات التعاقدية',
  `state`          VARCHAR(16)  NOT NULL DEFAULT 'draft' COMMENT 'draft او signed او active او suspended او closed',
  `signed_by`      INT UNSIGNED NOT NULL DEFAULT 0,
  `prepared_by`    INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ffc2_code` (`company_id`,`contract_code`),
  KEY `ix_ffc2_entity` (`company_id`,`entity_id`,`state`),
  CONSTRAINT `chk_ffc2_amt` CHECK (`principal` > 0 AND `currency` <> ''),
  CONSTRAINT `chk_ffc2_sign` CHECK (`state` = 'draft'
                                    OR (`contract_doc_ref` <> '' AND `signed_on` IS NOT NULL AND `signed_by` > 0)),
  CONSTRAINT `chk_ffc2_sod` CHECK (`state` = 'draft' OR `signed_by` <> `prepared_by`),
  CONSTRAINT `chk_ffc2_span` CHECK (`end_on` IS NULL OR `start_on` IS NULL OR `end_on` >= `start_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - عقد تمويل واحد'", 'fin_finance_contract');

$run("
CREATE TABLE IF NOT EXISTS `fin_contract_term` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `contract_id`  INT UNSIGNED NOT NULL,
  `term_key`     VARCHAR(48)  NOT NULL COMMENT 'بند تعاقدي — لاتيني يقارن',
  `term_value`   VARCHAR(400) NOT NULL DEFAULT '',
  `clause_ref`   VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'رقم البند في المستند',
  `is_binding`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fct` (`company_id`,`contract_id`,`term_key`),
  CONSTRAINT `chk_fct_val` CHECK (`term_value` <> '' AND `clause_ref` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - بنود وشروط التمويل - كل بند سطر لا عمود مخترع'", 'fin_contract_term');

$run("
CREATE TABLE IF NOT EXISTS `fin_contract_covenant` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `contract_id`   INT UNSIGNED NOT NULL,
  `covenant_key`  VARCHAR(48)  NOT NULL,
  `covenant_ar`   VARCHAR(255) NOT NULL DEFAULT '',
  `obligation_on` VARCHAR(16)  NOT NULL DEFAULT 'us' COMMENT 'us او financier او both',
  `measure_rule`  VARCHAR(400) NOT NULL DEFAULT '',
  `threshold_key` VARCHAR(48)  NOT NULL DEFAULT '' COMMENT 'العتبة من السجل لا رقم في شيفرة',
  `frequency`     VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'monthly او quarterly او annual او event',
  `evidence_doc`  VARCHAR(160) NOT NULL DEFAULT '',
  `state`         VARCHAR(16)  NOT NULL DEFAULT 'active' COMMENT 'active او breached او waived او expired',
  `breach_ref`    VARCHAR(120) NOT NULL DEFAULT '',
  `waiver_ref`    VARCHAR(160) NOT NULL DEFAULT '',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fcc` (`company_id`,`contract_id`,`covenant_key`),
  CONSTRAINT `chk_fcc_rule` CHECK (`measure_rule` <> '' AND `covenant_ar` <> ''),
  CONSTRAINT `chk_fcc_waiv` CHECK (`state` <> 'waived' OR `waiver_ref` <> ''),
  CONSTRAINT `chk_fcc_brch` CHECK (`state` <> 'breached' OR `breach_ref` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - مصفوفة الالتزامات التمويلية'", 'fin_contract_covenant');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ **الإقفالاتُ الثلاثةُ — ثلاثةُ جداولَ لا ثلاثُ حالات**
   ═══════════════════════════════════════════════════════════════════════════
   ◆ `close_kind` في كلِّ جدولٍ **مقيَّدٌ بقيمةٍ واحدة**: فجدولُ التعاقديِّ لا
     يحمل شهريًّا ولا نهائيًّا، وصفٌّ واحدٌ **لا يخدم معنيَين**.
   ◆ **والحبّةُ تُفرَض بالمفتاحِ الفريدِ لا بالتوثيق**:
     تعاقديٌّ = `عملية × رقم فترة تعاقدية` · شهريٌّ = `عملية × شهر × عملة` ·
     نهائيٌّ = `عملية` مرّةً واحدةً لا غير.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑤ الإقفالاتُ الثلاثةُ — ثلاثةُ كياناتٍ متمايزة ────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `fin_contract_close` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`        INT UNSIGNED NOT NULL,
  `close_kind`        VARCHAR(16)  NOT NULL DEFAULT 'CONTRACTUAL',
  `close_code`        VARCHAR(40)  NOT NULL COMMENT 'FCON',
  `op_id`             INT UNSIGNED NOT NULL COMMENT 'financing_operations.op_id',
  `entity_id`         INT UNSIGNED NOT NULL COMMENT 'الممول',
  `contract_id`       INT UNSIGNED NOT NULL DEFAULT 0,
  `contract_period_no` SMALLINT UNSIGNED NOT NULL COMMENT 'رقم الفترة التعاقدية — ما يميزه عن الشهري',
  `period_start`      DATE         NOT NULL,
  `period_end`        DATE         NOT NULL,
  `currency`          VARCHAR(8)   NOT NULL,
  `open_principal`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `open_profit`       DECIMAL(18,2) NOT NULL DEFAULT 0,
  `due_principal`     DECIMAL(18,2) NOT NULL DEFAULT 0,
  `due_profit`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `due_fees`          DECIMAL(18,2) NOT NULL DEFAULT 0,
  `approved_adjust`   DECIMAL(18,2) NOT NULL DEFAULT 0,
  `allocated_paid`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `close_principal`   DECIMAL(18,2) NOT NULL DEFAULT 0,
  `close_profit`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `arrears_amount`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `arrears_days`      INT          NOT NULL DEFAULT 0,
  `next_due_on`       DATE         NULL,
  `rollforward_ok`    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'الافتتاحي يساوي ختامي السابق',
  `statement_ref`     VARCHAR(160) NOT NULL DEFAULT '',
  `data_state`        VARCHAR(16)  NOT NULL DEFAULT 'derived',
  `state`             VARCHAR(16)  NOT NULL DEFAULT 'draft' COMMENT 'draft او prepared او reviewed او approved او superseded',
  `prepared_by`       INT UNSIGNED NOT NULL DEFAULT 0,
  `reviewed_by`       INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_by`       INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_at`       DATETIME     NULL,
  `note`              VARCHAR(400) NOT NULL DEFAULT '',
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fcon_grain` (`company_id`,`op_id`,`contract_period_no`),
  UNIQUE KEY `uq_fcon_code` (`company_id`,`close_code`),
  KEY `ix_fcon_state` (`company_id`,`state`,`period_end`),
  CONSTRAINT `chk_fcon_kind`   CHECK (`close_kind` = 'CONTRACTUAL'),
  CONSTRAINT `chk_fcon_period` CHECK (`contract_period_no` > 0 AND `period_end` >= `period_start`),
  CONSTRAINT `chk_fcon_cur`    CHECK (`currency` <> ''),
  CONSTRAINT `chk_fcon_appr`   CHECK (`state` <> 'approved'
                                      OR (`approved_by` > 0 AND `approved_at` IS NOT NULL
                                          AND `approved_by` <> `prepared_by`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - الاقفال التعاقدي - ممول × عملية × فترة تعاقدية'", 'fin_contract_close');

$run("
CREATE TABLE IF NOT EXISTS `fin_monthly_close` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`       INT UNSIGNED NOT NULL,
  `close_kind`       VARCHAR(16)  NOT NULL DEFAULT 'MONTHLY',
  `close_code`       VARCHAR(40)  NOT NULL COMMENT 'FMC',
  `op_id`            INT UNSIGNED NOT NULL,
  `entity_id`        INT UNSIGNED NOT NULL,
  `accounting_month` CHAR(7)      NOT NULL COMMENT 'YYYY-MM — الشهر التقويمي',
  `month_start`      DATE         NOT NULL,
  `month_end`        DATE         NOT NULL,
  `currency`         VARCHAR(8)   NOT NULL,
  `contract_closes_n` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'عدد الاقفالات التعاقدية في الشهر',
  `open_balance`     DECIMAL(18,2) NOT NULL DEFAULT 0,
  `due_in_month`     DECIMAL(18,2) NOT NULL DEFAULT 0,
  `paid_in_month`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `allocated_in_month` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `unallocated_in_month` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `arrears_in_month` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `close_balance`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `rollforward_ok`   TINYINT(1)   NOT NULL DEFAULT 0,
  `financier_stmt_match` VARCHAR(16) NOT NULL DEFAULT 'unmatched' COMMENT 'unmatched او matched او disputed',
  `data_state`       VARCHAR(16)  NOT NULL DEFAULT 'derived',
  `state`            VARCHAR(16)  NOT NULL DEFAULT 'draft',
  `prepared_by`      INT UNSIGNED NOT NULL DEFAULT 0,
  `reviewed_by`      INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_by`      INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_at`      DATETIME     NULL,
  `note`             VARCHAR(400) NOT NULL DEFAULT '',
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fmc_grain` (`company_id`,`op_id`,`accounting_month`,`currency`),
  UNIQUE KEY `uq_fmc_code` (`company_id`,`close_code`),
  KEY `ix_fmc_state` (`company_id`,`state`,`accounting_month`),
  CONSTRAINT `chk_fmc_kind`  CHECK (`close_kind` = 'MONTHLY'),
  /* ⛔ **الشهريُّ شهرٌ تقويميٌّ بقيدِ القاعدةِ لا بالنيّة** — ففترةٌ تعاقديّةٌ
       تُدسُّ هنا تُردّ، ولا يصير الشهريُّ وعاءً يخدم معنى التعاقديِّ أيضًا. */
  CONSTRAINT `chk_fmc_month` CHECK (DAYOFMONTH(`month_start`) = 1
                                    AND `month_end` = LAST_DAY(`month_start`)
                                    AND `accounting_month` = CONCAT(YEAR(`month_start`), '-',
                                                                    LPAD(MONTH(`month_start`), 2, '0'))),
  CONSTRAINT `chk_fmc_cur`   CHECK (`currency` <> ''),
  CONSTRAINT `chk_fmc_appr`  CHECK (`state` <> 'approved'
                                    OR (`approved_by` > 0 AND `approved_at` IS NOT NULL
                                        AND `approved_by` <> `prepared_by`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - الاقفال الشهري - ممول × عملية × شهر تقويمي × عملة'", 'fin_monthly_close');

$run("
CREATE TABLE IF NOT EXISTS `fin_final_close` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`        INT UNSIGNED NOT NULL,
  `close_kind`        VARCHAR(16)  NOT NULL DEFAULT 'FINAL',
  `close_code`        VARCHAR(40)  NOT NULL COMMENT 'FFIN',
  `op_id`             INT UNSIGNED NOT NULL,
  `entity_id`         INT UNSIGNED NOT NULL,
  `currency`          VARCHAR(8)   NOT NULL,
  `requested_on`      DATE         NULL,
  `closed_on`         DATE         NULL,
  `last_periodic_close_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'fin_contract_close.id',
  `last_payment_ref`  VARCHAR(160) NOT NULL DEFAULT '',
  `residual_principal` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `residual_profit`   DECIMAL(18,2) NOT NULL DEFAULT 0,
  `open_dues_n`       INT          NOT NULL DEFAULT 0,
  `open_deviations_n` INT          NOT NULL DEFAULT 0,
  `ownership_transferred` TINYINT(1) NOT NULL DEFAULT 0,
  `ownership_doc_ref` VARCHAR(160) NOT NULL DEFAULT '',
  `clearance_doc_ref` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'اخلاء طرف او شهادة اقفال',
  `early_settlement_ref` VARCHAR(160) NOT NULL DEFAULT '',
  `state`             VARCHAR(16)  NOT NULL DEFAULT 'requested' COMMENT 'requested او reviewed او approved او rejected',
  `prepared_by`       INT UNSIGNED NOT NULL DEFAULT 0,
  `reviewed_by`       INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_by`       INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_at`       DATETIME     NULL,
  `note`              VARCHAR(400) NOT NULL DEFAULT '',
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  /* **مرّةً واحدةً لعمليةٍ لا غير** — والنهائيُّ لا يتكرّر بتكرارِ فترةٍ */
  UNIQUE KEY `uq_ffin_grain` (`company_id`,`op_id`),
  UNIQUE KEY `uq_ffin_code` (`company_id`,`close_code`),
  CONSTRAINT `chk_ffin_kind` CHECK (`close_kind` = 'FINAL'),
  CONSTRAINT `chk_ffin_cur`  CHECK (`currency` <> ''),
  CONSTRAINT `chk_ffin_appr` CHECK (`state` <> 'approved'
                                    OR (`approved_by` > 0 AND `approved_by` <> `prepared_by`
                                        AND `clearance_doc_ref` <> ''
                                        AND `open_dues_n` = 0 AND `open_deviations_n` = 0
                                        AND `last_periodic_close_id` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - الاقفال النهائي - عملية واحدة مرة واحدة'", 'fin_final_close');

/* ── رابطُ الإقفالاتِ: الأبُ لا يكون من صنفِ ابنِه ───────────────────────
   ⛔ **ورابطٌ من صنفٍ إلى صنفِه نفسِه هو عينُ «إقفالٍ يخدم معنيَين»** — فيُردّ
     في القاعدةِ لا يُكتشَف بتقرير. والأزواجُ المسموحةُ ثلاثةٌ لا رابع. */
$run("
CREATE TABLE IF NOT EXISTS `fin_close_link` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `parent_kind` VARCHAR(16)  NOT NULL,
  `parent_id`   INT UNSIGNED NOT NULL,
  `child_kind`  VARCHAR(16)  NOT NULL,
  `child_id`    INT UNSIGNED NOT NULL,
  `link_rule`   VARCHAR(48)  NOT NULL DEFAULT '',
  `why`         VARCHAR(400) NOT NULL DEFAULT '',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fcl` (`company_id`,`parent_kind`,`parent_id`,`child_kind`,`child_id`),
  CONSTRAINT `chk_fcl_self` CHECK (`parent_kind` <> `child_kind`),
  CONSTRAINT `chk_fcl_pair` CHECK (
        (`parent_kind` = 'MONTHLY' AND `child_kind` = 'CONTRACTUAL')
     OR (`parent_kind` = 'FINAL'   AND `child_kind` = 'CONTRACTUAL')
     OR (`parent_kind` = 'FINAL'   AND `child_kind` = 'MONTHLY')),
  CONSTRAINT `chk_fcl_why` CHECK (`link_rule` <> '' AND `why` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - ربط الاقفالات الثلاثة بلا دمج معانيها'", 'fin_close_link');

/* ── سجلُّ الاستهلاك: من يقرأ أيَّ صنفِ إقفالٍ ولماذا ──────────────────── */
$run("
CREATE TABLE IF NOT EXISTS `fin_close_consumption` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `consumer_key`    VARCHAR(64)  NOT NULL,
  `consumer_surface` VARCHAR(200) NOT NULL DEFAULT '',
  `close_kind`      VARCHAR(16)  NOT NULL,
  `purpose`         VARCHAR(48)  NOT NULL DEFAULT '' COMMENT 'الغرض — والغرض الواحد لا يقرا صنفين',
  `read_table`      VARCHAR(64)  NOT NULL DEFAULT '',
  `why`             VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`         VARCHAR(160) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fcc3` (`consumer_key`,`close_kind`,`purpose`),
  CONSTRAINT `chk_fcc3_kind` CHECK (`close_kind` IN ('CONTRACTUAL','MONTHLY','FINAL')),
  CONSTRAINT `chk_fcc3_why`  CHECK (`purpose` <> '' AND `why` <> '' AND `read_table` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - من يقرا اي صنف اقفال ولاي غرض'", 'fin_close_consumption');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ **الطبقتان — نموذجُ المستقبلِ منفصلٌ عن التجميعِ التاريخيّ**
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑥ نموذجُ الدفعِ المستقبليُّ والطبقةُ التاريخيّة ───────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `fin_payment_order` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`      INT UNSIGNED NOT NULL,
  `source_kind`     VARCHAR(8)   NOT NULL DEFAULT 'FUTURE',
  `order_code`      VARCHAR(40)  NOT NULL COMMENT 'FPAYO',
  `op_id`           INT UNSIGNED NOT NULL,
  `entity_id`       INT UNSIGNED NOT NULL COMMENT 'الممول المستفيد',
  `requested_at`    DATETIME     NOT NULL,
  `requested_by`    INT UNSIGNED NOT NULL,
  `requested_amount` DECIMAL(18,2) NOT NULL,
  `currency`        VARCHAR(8)   NOT NULL,
  `approved_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `approved_by`     INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_at`     DATETIME     NULL,
  `state`           VARCHAR(16)  NOT NULL DEFAULT 'requested'
                    COMMENT 'draft او requested او approved او executed او rejected او cancelled',
  `executed_on`     DATE         NULL,
  `executed_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `method`          VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'bank او cheque او cash',
  `bank_ref`        VARCHAR(160) NOT NULL DEFAULT '',
  `treasury_ref`    VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'مرجع الخزينة او المالية',
  `match_state`     VARCHAR(16)  NOT NULL DEFAULT 'unmatched',
  `reject_reason`   VARCHAR(400) NOT NULL DEFAULT '',
  `recognition_request_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'طلب الاعتراف عند المالية §48',
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fpo_code` (`company_id`,`order_code`),
  KEY `ix_fpo_op` (`company_id`,`op_id`,`state`),
  /* ⛔ **الطبقةُ التاريخيّةُ لا تدخل نموذجَ المستقبل** — والفصلُ قيدٌ لا عُرف */
  CONSTRAINT `chk_fpo_future` CHECK (`source_kind` = 'FUTURE'),
  /* ⛔ **وحقولُ النموذجِ لا تُخفَّض لتناسب ما تستطيعه الصفوفُ المجمَّعة** */
  CONSTRAINT `chk_fpo_req`  CHECK (`requested_by` > 0 AND `requested_amount` > 0 AND `currency` <> ''),
  CONSTRAINT `chk_fpo_appr` CHECK (`state` NOT IN ('approved','executed')
                                   OR (`approved_by` > 0 AND `approved_amount` > 0
                                       AND `approved_at` IS NOT NULL)),
  CONSTRAINT `chk_fpo_sod`  CHECK (`state` NOT IN ('approved','executed')
                                   OR `approved_by` <> `requested_by`),
  CONSTRAINT `chk_fpo_exec` CHECK (`state` <> 'executed'
                                   OR (`executed_on` IS NOT NULL AND `executed_amount` > 0
                                       AND `bank_ref` <> '' AND `method` <> '')),
  CONSTRAINT `chk_fpo_rej`  CHECK (`state` <> 'rejected' OR `reject_reason` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - امر الدفع المستقبلي - طبقة FUTURE وحدها'", 'fin_payment_order');

$run("
CREATE TABLE IF NOT EXISTS `fin_legacy_payment_aggregate` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `layer`          VARCHAR(8)   NOT NULL DEFAULT 'LEGACY',
  `op_id`          INT UNSIGNED NOT NULL,
  `entity_id`      INT UNSIGNED NOT NULL DEFAULT 0,
  `period_label`   VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'الفترة كما وردت في المصدر التاريخي',
  `paid_aggregate` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'المبلغ المدفوع مجمعا لا مفصلا',
  `ledger_rows`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'عدد صفوف الدفتر التي جمعها هذا السطر',
  `currency`       VARCHAR(8)   NOT NULL DEFAULT '',
  `evidence_grade` VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'الحجية — documented او aggregate او asserted',
  `source_row_ref` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'مرجع الصف في المصدر',
  `data_state`     VARCHAR(16)  NOT NULL DEFAULT 'legacy',
  `allocatable`    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'صفر دائما — المجمع لا يخصص كامر',
  `note`           VARCHAR(400) NOT NULL DEFAULT '',
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_flpa_op` (`company_id`,`op_id`,`period_label`),
  CONSTRAINT `chk_flpa_layer` CHECK (`layer` = 'LEGACY'),
  /* ⛔ **حجّيّةٌ ومرجعُ صفٍّ أو لا سطر** — ورقمٌ تاريخيٌّ بلا سندٍ يُقرأ رقمًا */
  CONSTRAINT `chk_flpa_evid`  CHECK (`evidence_grade` <> '' AND `source_row_ref` <> ''),
  CONSTRAINT `chk_flpa_alloc` CHECK (`allocatable` = 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - الطبقة التاريخية المجمعة بحجيتها ومرجع صفها'", 'fin_legacy_payment_aggregate');

$run("
CREATE TABLE IF NOT EXISTS `fin_payment_allocation` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `order_id`       INT UNSIGNED NOT NULL COMMENT 'fin_payment_order.id — ولا تخصيص من الطبقة التاريخية',
  `installment_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'financing_installments.inst_id',
  `close_kind`     VARCHAR(16)  NOT NULL DEFAULT 'CONTRACTUAL',
  `close_id`       INT UNSIGNED NOT NULL DEFAULT 0,
  `amount`         DECIMAL(18,2) NOT NULL DEFAULT 0,
  `part_kind`      VARCHAR(16)  NOT NULL DEFAULT 'principal' COMMENT 'principal او profit او fees',
  `allocated_by`   INT UNSIGNED NOT NULL DEFAULT 0,
  `allocated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `note`           VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `ix_fpa_order` (`company_id`,`order_id`),
  KEY `ix_fpa_inst` (`company_id`,`installment_id`),
  CONSTRAINT `chk_fpa_order` CHECK (`order_id` > 0),
  CONSTRAINT `chk_fpa_amt`   CHECK (`amount` > 0),
  CONSTRAINT `chk_fpa_kind`  CHECK (`close_kind` IN ('CONTRACTUAL','MONTHLY','FINAL')),
  CONSTRAINT `chk_fpa_part`  CHECK (`part_kind` IN ('principal','profit','fees'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W12 - تخصيص السداد على الاقساط من امر الدفع وحده'", 'fin_payment_allocation');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑦ أعمدةٌ على الجداولِ الحيّة — إضافةً لا إعادةَ تعريف
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑦ أعمدةٌ على الجداولِ الحيّة ─────────────────────────────────\n";
$addCol('financing_operations', 'contract_id',
        '`contract_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT "fin_finance_contract.id"');
$addCol('financing_operations', 'final_close_id',
        '`final_close_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT "fin_final_close.id — الاقفال النهائي"');
$addCol('financing_installments', 'contract_close_id',
        '`contract_close_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT "الاقفال التعاقدي الذي يقع فيه القسط"');
$addCol('financing_installments', 'allocated_amount',
        '`allocated_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT "مخصص من اوامر الدفع — مشتق"');
$addCol('financing_deviations', 'final_close_block',
        '`final_close_block` TINYINT(1) NOT NULL DEFAULT 1 COMMENT "هل يحجب الاقفال النهائي"');

/* ── تشديدُ حبّةِ الكيان — **الاستثناءُ الوحيدُ لقاعدةِ «الإضافةُ وحدَها»** ──
   ◆ `DEC-OPEN-03` يقول: **لا صفَّ بلا كيانٍ قانونيّ**. وعمودٌ يقبل `NULL` يسمح
     بصفٍّ بلا كيانٍ فتصير الحبّةُ دعوى (‏درسُ `W11-12`: الوجودُ لا يكفي).
   ⛔ **والتشديدُ لا يقع على بياناتٍ مخالفة**: يُقاس أوّلًا، وصفٌّ واحدٌ بلا
     كيانٍ يمنع التغييرَ ويُعلَن — فالهجرةُ لا تخترع كيانًا لصفٍّ لا يملكه. */
$tighten = function ($t, $col, $ddl) use ($conn, &$done, &$skip, &$err) {
    if (!w12_col($conn, $t, $col)) { echo "  ⚠ $t.$col غير موجود — التشديد يُتخطّى\n"; $skip++; return; }
    $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '" . $conn->real_escape_string($col) . "'");
    $x = $r ? $r->fetch_assoc() : null;
    if ($x && strtoupper((string) $x['Null']) === 'NO') { echo "  ↷ $t.$col مشدَّدٌ سلفًا\n"; $skip++; return; }
    $bad = $conn->query("SELECT COUNT(*) FROM `$t` WHERE `$col` IS NULL OR `$col` = 0");
    $bad = $bad ? (int) $bad->fetch_row()[0] : -1;
    if ($bad !== 0) {
        echo "  ⚠ $t.$col فيه $bad صفًّا بلا كيان — التشديدُ يُمنَع ولا يُخترع كيان\n";
        $skip++; return;
    }
    if ($conn->query("ALTER TABLE `$t` MODIFY COLUMN $ddl") === true) {
        echo "  ✔ $t.$col صار إلزاميًّا (مقيسٌ 0 صفٍّ بلا كيان)\n"; $done++;
    } else { echo "  ✘ $t.$col — " . $conn->error . "\n"; $err++; }
};
$tighten('financing_installments', 'company_id',
         '`company_id` INT(11) NOT NULL COMMENT "الكيان القانوني - DEC-OPEN-03"');
$tighten('financed_assets', 'company_id',
         '`company_id` INT(11) NOT NULL COMMENT "الكيان القانوني - DEC-OPEN-03"');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑧ مستهلكو أحداثِ النطاق
   ═══════════════════════════════════════════════════════════════════════════
   ⚠ **الفاصلُ يُكتب مرّةً واحدةً في نصِّ PHP** — و`real_escape_string` يضاعفه
     في نصِّ الاستعلام، فكتابتُه مضاعفًا هنا تخزّنه مضاعفًا (‏درسُ W11).
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑧ مستهلكو أحداثِ النطاق ─────────────────────────────────────\n";
$FIN = 'App\\Services\\Financing\\FinancingCycleService';
$CONS = array(
    array('fin.contract.signed',      $FIN, 'onContractSigned',      'write'),
    array('fin.schedule.generated',   $FIN, 'onScheduleGenerated',   'write'),
    array('fin.order.approved',       $FIN, 'onOrderApproved',       'write'),
    array('fin.order.executed',       $FIN, 'onOrderExecuted',       'write'),
    array('fin.payment.allocated',    $FIN, 'onPaymentAllocated',    'write'),
    array('fin.contract.closed',      $FIN, 'onContractClosed',      'write'),
    array('fin.monthly.closed',       $FIN, 'onMonthlyClosed',       'write'),
    array('fin.final.closed',         $FIN, 'onFinalClosed',         'write'),
    array('fin.deviation.raised',     $FIN, 'onDeviationRaised',     'write'),
    array('fin.ownership.transferred', $FIN, 'onOwnershipTransferred', 'write'),
);
foreach ($CONS as $c) {
    $key = 'w12_' . str_replace('.', '_', $c[0]);
    $run("INSERT INTO `event_consumers`
            (`event_name`, `consumer_class`, `consumer_method`, `produces`, `active`,
             `consumer_key`, `max_attempts`, `timeout_seconds`)
          VALUES ('" . $conn->real_escape_string($c[0]) . "',
                  '" . $conn->real_escape_string($c[1]) . "',
                  '" . $conn->real_escape_string($c[2]) . "',
                  '" . $conn->real_escape_string($c[3]) . "', 1,
                  '" . $conn->real_escape_string($key) . "', 5, 60)
          ON DUPLICATE KEY UPDATE `consumer_method` = VALUES(`consumer_method`),
                  `consumer_class` = VALUES(`consumer_class`),
                  `produces` = VALUES(`produces`), `active` = 1,
                  `inactive_reason` = NULL, `inactive_at` = NULL", 'مستهلك ' . $c[0]);
}

echo "\n───────────────────────────────────────────────────────────────\n";
echo "الخلاصة: نُفِّذ $done · مُتخطًّى $skip · أخطاء $err\n";
exit($err > 0 ? 1 : 0);
