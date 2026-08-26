<?php
/**
 * 2027_11_26_repair01_w9_prc_wh.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W09 — **المشترياتُ والمخازن: دورةُ التوريدِ من الطلبِ إلى العهدة**
 *
 * ◆ **وهذه المرحلةُ تُنفَّذ وحاجبُها البنيويُّ مفتوح** — بأمرِ المالكِ نصًّا
 *   (2026-08-26): «احفظ مكانه واكمل المرحلة بدونه». فـ`DEC-OPEN-15` (أعلامُ
 *   Lot/Serial/Expiry) **مؤجَّلٌ لا مُخمَّن**، و`repair01_w9_deferred` يحمل
 *   موضعَه حرفًا: ما بُني · ما ينتظر · وخطوةُ الاستئنافِ عند وصولِ الجواب.
 *
 * ◆ **والتأجيلُ مقفولٌ في الاتّجاهَين**: صفٌّ مؤجَّلٌ بلا `blocked_by` و`resume_step`
 *   يرفضه `CHECK`؛ والبوّابةُ `W9-24` **تسقط** لحظةَ يصير الحاجبُ معتمَدًا وبقيت
 *   الصفوفُ غيرَ مستهلَكة. فالتأجيلُ بلا إعلانٍ خرقٌ، والإعلانُ بعد الجوابِ تقادُم.
 *
 * ◆ **وبنيةُ التتبّعِ تُبنى كاملةً وهي خامدة**: `proc_item` يحمل الأعلامَ الثلاثةَ
 *   وكلُّها `0`، و`proc_item_track_rule` (فئةٌ ⇒ أعلام) **يُنشأ خاويًا**. فالعمودُ
 *   والبوّابةُ والمسارُ حاضرةٌ، والفئاتُ وحدَها تنتظر — وهي **صفوفُ سجلٍّ لا
 *   تغييرُ مخطَّط**. ولو خُمِّنت لصار التصحيحُ إعادةَ ترحيلِ كلِّ حركةِ مخزنٍ مضت.
 *
 * ◆ **ولماذا طلبُ عروضِ الشراءِ كيانٌ مستقلٌّ عن `supplier_rfqs`** (قرارُ W9-D-02):
 *   حبّةُ القائمِ **طلبٌ × عقدِ عميل** (‏`client_contract_id` مملوءٌ 3/3 و`request_id`
 *   خاوٍ 0/3)، و`rfq_lines.commitment_id` **غيرُ قابلٍ للعدمِ ومفتاحُه الفريدُ
 *   `(rfq_id, commitment_id)`** — فمدُّه لحزمةِ شراءٍ يوجب نزعَ قيدٍ حيٍّ على تسعةِ
 *   صفوف. وحبّةُ المطلوبِ **طلبٌ × حزمةِ شراء** (‏`PRC-06` نصًّا). حبّتانِ مختلفتانِ
 *   ⇒ كيانان — والتمييزُ يُكتب صراحةً كيلا يُدمَجا لاحقًا بحسنِ نيّة.
 *
 * ◆ **و`CHECK` لا قادِح**: مستخدمُ الهجراتِ لا يملك `SUPER` (‏W03 · 2027_11_19).
 *
 * ⛔ **ولا عتبةٌ رقميّةٌ في الشيفرة** — كلُّها في `repair01_w9_thresholds`.
 * ⛔ **ولا يُمَسُّ عمودٌ حيٌّ بحذفٍ أو إعادةِ تعريف** — الإضافةُ وحدَها.
 *
 * التشغيل: php database/migrations/2027_11_26_repair01_w9_prc_wh.php
 *          (⛔ لا `migrate.php up` — الهجرةُ بمسارِها الكاملِ منفردةً)
 * التراجع: php database/migrations/2027_11_26_repair01_w9_prc_wh_down.php
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

function w9_tbl(mysqli $c, $t)
{
    $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}
function w9_col(mysqli $c, $t, $col)
{
    if (!w9_tbl($c, $t)) { return false; }
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

$done = 0; $err = 0; $skip = 0;
$run = function ($sql, $label) use ($conn, &$done, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; return true; }
    echo "  ✘ $label — " . $conn->error . "\n"; $err++; return false;
};
$addCol = function ($t, $col, $ddl) use ($conn, &$done, &$skip, &$err) {
    if (!w9_tbl($conn, $t)) { echo "  ⚠ $t غير موجود — $col يُتخطّى\n"; $skip++; return; }
    if (w9_col($conn, $t, $col)) { echo "  ↷ $t.$col قائم\n"; $skip++; return; }
    if ($conn->query("ALTER TABLE `$t` ADD COLUMN $ddl") === true) { echo "  ✔ $t.$col\n"; $done++; }
    else { echo "  ✘ $t.$col — " . $conn->error . "\n"; $err++; }
};

echo "══ REPAIR01 · W09 — المشترياتُ والمخازن: دورةُ التوريد ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ① دفاترُ المرحلة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① دفاترُ المرحلة ───────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w9_scope` (
  `requirement_id`   VARCHAR(48)  NOT NULL,
  `unit`             VARCHAR(160) NOT NULL DEFAULT '',
  `group_name`       VARCHAR(160) NOT NULL DEFAULT '',
  `surface`          VARCHAR(255) NOT NULL DEFAULT '',
  `anchor_screen_id` VARCHAR(12)  NOT NULL DEFAULT '',
  `anchor_route`     VARCHAR(200) NOT NULL DEFAULT '',
  `anchor_probe`     VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'الجدول الذي يثبت المرساة قياسا',
  `owner_measured`   VARCHAR(12)  NOT NULL DEFAULT '',
  `owner_expected`   VARCHAR(12)  NOT NULL DEFAULT '',
  `owner_verdict`    VARCHAR(32)  NOT NULL DEFAULT '',
  `build_verdict`    VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'LIVE او BUILT_W09 او DEFERRED',
  `map_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `map_why`          VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`          VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`requirement_id`),
  KEY `ix_screen` (`anchor_screen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W09 - ربط متطلبات المرحلة بالسجل المعياري'", 'repair01_w9_scope');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w9_sidebar` (
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
  COMMENT='REPAIR01 W09 - الخطوات السبع للسايدبار داخل النطاق'", 'repair01_w9_sidebar');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w9_decisions` (
  `decision_id` VARCHAR(24)  NOT NULL,
  `question`    VARCHAR(400) NOT NULL DEFAULT '',
  `ruling`      VARCHAR(900) NOT NULL DEFAULT '',
  `rationale`   VARCHAR(900) NULL,
  `scope_rows`  INT          NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`decision_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W09 - قرارات المرحلة'", 'repair01_w9_decisions');

/* ⛔ **دفترُ التأجيل** — موضعُ السؤالِ المفتوحِ محفوظًا لا منسيًّا.
      و`CHECK` يرفض تأجيلًا بلا حاجبٍ مسمًّى وبلا خطوةِ استئنافٍ مكتوبة:
      «مؤجَّلٌ» بلا طريقِ عودةٍ ليس تأجيلًا بل نسيانٌ موثَّق. */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w9_deferred` (
  `defer_key`      VARCHAR(60)  NOT NULL,
  `requirement_id` VARCHAR(48)  NOT NULL DEFAULT '',
  `blocked_by`     VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'معرف القرار الحاجب',
  `part_built`     VARCHAR(600) NOT NULL DEFAULT '' COMMENT 'ما بني رغم التاجيل',
  `part_waiting`   VARCHAR(600) NOT NULL DEFAULT '' COMMENT 'ما ينتظر الجواب',
  `resume_step`    VARCHAR(600) NOT NULL DEFAULT '' COMMENT 'خطوة الاستئناف عند وصول الجواب',
  `probe_sql`      VARCHAR(600) NOT NULL DEFAULT '' COMMENT 'استعلام يثبت ان الانتظار ما زال قائما',
  `consumed`       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'يرفع بعد تنفيذ خطوة الاستئناف',
  `consumed_at`    DATETIME     NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`defer_key`),
  KEY `ix_block` (`blocked_by`),
  CONSTRAINT `chk_w9df_full` CHECK (`blocked_by` <> '' AND `resume_step` <> ''
                                    AND `part_built` <> '' AND `part_waiting` <> ''
                                    AND `probe_sql` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W09 - ما اجل بحاجب مفتوح وخطوة استئنافه'", 'repair01_w9_deferred');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w9_journey` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id`          VARCHAR(40)  NOT NULL DEFAULT '',
  `station_no`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `station`         VARCHAR(160) NOT NULL DEFAULT '',
  `entity`          VARCHAR(40)  NOT NULL DEFAULT '',
  `consumer`        VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'المستهلك بالاسم لا كل المستهلكين',
  `expected`        VARCHAR(400) NOT NULL DEFAULT '',
  `measured`        VARCHAR(400) NOT NULL DEFAULT '',
  `business_effect` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'الاثر التجاري المقيس لا صف الحدث',
  `state_after`     VARCHAR(120) NOT NULL DEFAULT '',
  `passed`          TINYINT(1)   NOT NULL DEFAULT 0,
  `run_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_run` (`run_id`,`station_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W09 - رحلة المشتريات بمحطاتها واثر كل مستهلك'", 'repair01_w9_journey');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w9_states` (
  `entity`        VARCHAR(48)  NOT NULL,
  `from_state`    VARCHAR(48)  NOT NULL,
  `to_state`      VARCHAR(48)  NOT NULL,
  `allowed`       TINYINT(1)   NOT NULL DEFAULT 1,
  `owner_role`    VARCHAR(120) NOT NULL DEFAULT '',
  `precondition`  VARCHAR(500) NOT NULL DEFAULT '',
  `official_doc`  VARCHAR(190) NOT NULL DEFAULT '',
  `approval_gate` VARCHAR(190) NOT NULL DEFAULT '',
  `reopen_rule`   VARCHAR(300) NOT NULL DEFAULT '',
  `correct_rule`  VARCHAR(300) NOT NULL DEFAULT '',
  `forbid_reason` VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`       VARCHAR(190) NOT NULL DEFAULT '',
  PRIMARY KEY (`entity`,`from_state`,`to_state`),
  CONSTRAINT `chk_w9st_forbid` CHECK (`allowed` = 1 OR `forbid_reason` <> ''),
  CONSTRAINT `chk_w9st_allow`  CHECK (`allowed` = 0 OR (`owner_role` <> '' AND `precondition` <> ''
                                       AND `official_doc` <> '' AND `approval_gate` <> ''
                                       AND `reopen_rule` <> '' AND `correct_rule` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W09 - آلة حالة لكل كيان رئيسي'", 'repair01_w9_states');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w9_sod` (
  `process_key`       VARCHAR(60)  NOT NULL,
  `process_name`      VARCHAR(190) NOT NULL DEFAULT '',
  `initiator_role`    VARCHAR(120) NOT NULL DEFAULT '',
  `reviewer_role`     VARCHAR(120) NOT NULL DEFAULT '',
  `approver_role`     VARCHAR(120) NOT NULL DEFAULT '',
  `executor_role`     VARCHAR(120) NOT NULL DEFAULT '',
  `closer_role`       VARCHAR(120) NOT NULL DEFAULT '',
  `forbidden_combo`   VARCHAR(500) NOT NULL DEFAULT '',
  `enforced_by`       VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'رمز الرد الذي ينفذها',
  `authority_rule_id` VARCHAR(60)  NOT NULL DEFAULT '',
  `deputy_role`       VARCHAR(120) NOT NULL DEFAULT '',
  `scope_rule`        VARCHAR(300) NOT NULL DEFAULT '',
  `delegation`        VARCHAR(300) NOT NULL DEFAULT '',
  `effective_date`    DATE         NULL,
  `src_ref`           VARCHAR(190) NOT NULL DEFAULT '',
  PRIMARY KEY (`process_key`),
  CONSTRAINT `chk_w9sod_full` CHECK (`initiator_role` <> '' AND `approver_role` <> ''
                                     AND `executor_role` <> '' AND `closer_role` <> ''
                                     AND `forbidden_combo` <> '' AND `authority_rule_id` <> ''
                                     AND `enforced_by` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W09 - فصل الواجبات بستة ادوار وتركيبة ممنوعة'", 'repair01_w9_sod');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w9_thresholds` (
  `threshold_key` VARCHAR(48)   NOT NULL,
  `value_num`     DECIMAL(14,4) NOT NULL DEFAULT 0,
  `unit_ar`       VARCHAR(40)   NOT NULL DEFAULT '',
  `title_ar`      VARCHAR(190)  NOT NULL DEFAULT '',
  `why`           VARCHAR(600)  NOT NULL DEFAULT '',
  `decision_ref`  VARCHAR(48)   NOT NULL DEFAULT '',
  `src_ref`       VARCHAR(190)  NOT NULL DEFAULT '',
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`threshold_key`),
  CONSTRAINT `chk_w9th_why` CHECK (`why` <> '' AND `decision_ref` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W09 - عتبات المرحلة: من السجل لا من الشيفرة'", 'repair01_w9_thresholds');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w9_fixes` (
  `fix_key`      VARCHAR(60)  NOT NULL,
  `kind`         VARCHAR(32)  NOT NULL DEFAULT '',
  `target`       VARCHAR(255) NOT NULL DEFAULT '',
  `what`         VARCHAR(600) NOT NULL DEFAULT '',
  `revealed_by`  VARCHAR(48)  NOT NULL DEFAULT '',
  `reveal_why`   VARCHAR(600) NOT NULL DEFAULT '',
  `evidence`     VARCHAR(400) NOT NULL DEFAULT '',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`fix_key`),
  KEY `ix_rev` (`revealed_by`),
  CONSTRAINT `chk_w9fx_rev` CHECK (`revealed_by` <> '' AND `reveal_why` <> ''
                                   AND `target` <> '' AND `kind` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W09 - كل اصلاح بمتطلبه الكاشف'", 'repair01_w9_fixes');

/* ═══════════════════════════════════════════════════════════════════════════
   ② أعلامُ التتبّعِ — البنيةُ كاملةٌ والفئاتُ تنتظر (DEC-OPEN-15)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n② أعلامُ التتبّعِ — بنيةٌ حاضرةٌ وفئاتٌ منتظرة ──────────────────\n";

$addCol('proc_item', 'track_lot',
    "`track_lot` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'يشتق من proc_item_track_rule - لا يكتب بيد'");
$addCol('proc_item', 'track_serial',
    "`track_serial` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'يشتق من proc_item_track_rule - لا يكتب بيد'");
$addCol('proc_item', 'track_expiry',
    "`track_expiry` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'يشتق من proc_item_track_rule - لا يكتب بيد'");
$addCol('proc_item', 'track_rule_ref',
    "`track_rule_ref` VARCHAR(60) NOT NULL DEFAULT '' COMMENT 'مفتاح القاعدة التي اشتقت الاعلام - علم بلا قاعدة مرفوض'");

/* ⛔ **يُنشأ خاويًا عمدًا** — الفئاتُ جوابُ المالكِ لا تخميني.
      و`CHECK` يمنع قاعدةً بلا علمٍ واحدٍ على الأقلِّ وبلا سببٍ ومرجعِ قرار. */
$run("
CREATE TABLE IF NOT EXISTS `proc_item_track_rule` (
  `rule_key`      VARCHAR(60)  NOT NULL,
  `company_id`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'صفر يعني كل الكيانات',
  `category`      VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'فئة الصنف كما في proc_item.category',
  `track_lot`     TINYINT(1)   NOT NULL DEFAULT 0,
  `track_serial`  TINYINT(1)   NOT NULL DEFAULT 0,
  `track_expiry`  TINYINT(1)   NOT NULL DEFAULT 0,
  `expiry_policy` VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'BLOCK او WARN_OVERRIDE - فارغ لغير المنتهي',
  `issue_order`   VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'FEFO او FIFO او FREE',
  `why`           VARCHAR(600) NOT NULL DEFAULT '',
  `decision_ref`  VARCHAR(48)  NOT NULL DEFAULT '',
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rule_key`),
  UNIQUE KEY `uq_cat` (`company_id`,`category`),
  CONSTRAINT `chk_pitr_any`  CHECK (`track_lot` = 1 OR `track_serial` = 1 OR `track_expiry` = 1),
  CONSTRAINT `chk_pitr_why`  CHECK (`why` <> '' AND `decision_ref` <> '' AND `category` <> ''),
  CONSTRAINT `chk_pitr_exp`  CHECK (`track_expiry` = 0 OR (`expiry_policy` <> '' AND `issue_order` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W09 - فئة الصنف تحدد اعلام التتبع - ينشا خاويا بانتظار DEC-OPEN-15'", 'proc_item_track_rule');

/* ═══════════════════════════════════════════════════════════════════════════
   ③ دورةُ الشراء — الحزمةُ وطلبُ العروضِ والعرضُ والترسية
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n③ دورةُ الشراء ────────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `proc_package` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `code`         VARCHAR(40)  NOT NULL DEFAULT '',
  `title`        VARCHAR(190) NOT NULL DEFAULT '',
  `period_from`  DATE         NULL,
  `period_to`    DATE         NULL,
  `strategy`     VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'سبب التجميع - وفر كمية او مورد واحد او مسار واحد',
  `member_count` INT          NOT NULL DEFAULT 0 COMMENT 'مشتق من proc_package_member',
  `line_count`   INT          NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `est_value`    DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'مشتق من بنود الطلبات المضمومة',
  `state`        VARCHAR(32)  NOT NULL DEFAULT 'draft',
  `closed_at`    DATETIME     NULL,
  `closed_by`    INT UNSIGNED NOT NULL DEFAULT 0,
  `notes`        VARCHAR(500) NOT NULL DEFAULT '',
  `is_deleted`   TINYINT(1)   NOT NULL DEFAULT 0,
  `created_by`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pkg_code` (`company_id`,`code`),
  KEY `ix_pkg_state` (`company_id`,`state`),
  CONSTRAINT `chk_pkg_strategy` CHECK (`strategy` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PRC-04 حزمة شراء × فترة - حزمة واحدة'", 'proc_package');

/* ⛔ الطلبُ الواحدُ في حزمةٍ واحدةٍ — الفريدُ يمنع الضمَّ المزدوج */
$run("
CREATE TABLE IF NOT EXISTS `proc_package_member` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `package_id`  INT UNSIGNED NOT NULL,
  `request_id`  INT UNSIGNED NOT NULL,
  `join_reason` VARCHAR(300) NOT NULL DEFAULT '',
  `joined_by`   INT UNSIGNED NOT NULL DEFAULT 0,
  `joined_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pkg_req` (`request_id`) COMMENT 'طلب واحد في حزمة واحدة - لا ضم مزدوج',
  KEY `ix_pkgm` (`company_id`,`package_id`),
  CONSTRAINT `fk_pkgm_pkg` FOREIGN KEY (`package_id`) REFERENCES `proc_package` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_pkgm_why` CHECK (`join_reason` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PRC-05 حزمة × طلب مضموم - Junction Child'", 'proc_package_member');

$run("
CREATE TABLE IF NOT EXISTS `proc_rfq` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `code`          VARCHAR(40)  NOT NULL DEFAULT '',
  `package_id`    INT UNSIGNED NOT NULL COMMENT 'حبة الكيان - طلب عروض × حزمة',
  `title`         VARCHAR(190) NOT NULL DEFAULT '',
  `issued_at`     DATETIME     NULL,
  `due_date`      DATE         NULL,
  `open_at`       DATETIME     NULL COMMENT 'موعد فتح المظاريف - لا عرض يقرا قبله',
  `opened_at`     DATETIME     NULL,
  `opened_by`     INT UNSIGNED NOT NULL DEFAULT 0,
  `invite_count`  INT          NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `offer_count`   INT          NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `state`         VARCHAR(32)  NOT NULL DEFAULT 'draft',
  `cancel_reason` VARCHAR(400) NOT NULL DEFAULT '',
  `notes`         VARCHAR(500) NOT NULL DEFAULT '',
  `is_deleted`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_by`    INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rfq_code` (`company_id`,`code`),
  UNIQUE KEY `uq_rfq_pkg` (`package_id`) COMMENT 'حزمة واحدة = طلب عروض واحد',
  CONSTRAINT `fk_prfq_pkg` FOREIGN KEY (`package_id`) REFERENCES `proc_package` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PRC-06 طلب عروض × حزمة - مستقل عن supplier_rfqs بحبته (W9-D-02)'", 'proc_rfq');

$run("
CREATE TABLE IF NOT EXISTS `proc_rfq_invite` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `rfq_id`       INT UNSIGNED NOT NULL,
  `supplier_id`  INT UNSIGNED NOT NULL,
  `invited_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `invited_by`   INT UNSIGNED NOT NULL DEFAULT 0,
  `channel`      VARCHAR(32)  NOT NULL DEFAULT '',
  `responded_at` DATETIME     NULL,
  `response`     VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'offered او declined او silent',
  `decline_why`  VARCHAR(400) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv` (`rfq_id`,`supplier_id`) COMMENT 'مورد واحد دعوة واحدة',
  KEY `ix_inv` (`company_id`,`rfq_id`),
  CONSTRAINT `fk_inv_rfq` FOREIGN KEY (`rfq_id`) REFERENCES `proc_rfq` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_inv_decl` CHECK (`response` <> 'declined' OR `decline_why` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PRC-07 RFQ × مورد مدعو - Invitations Child'", 'proc_rfq_invite');

/* ◆ **رأسُ العرضِ كيانٌ لا سطرٌ** — بلا رأسٍ لا موضعَ لصلاحيةِ العرضِ ولا
     لوقتِ تسليمِه ولا لمظروفِه؛ فتصير المقارنةُ بلا زمنٍ ولا سند. */
$run("
CREATE TABLE IF NOT EXISTS `proc_offer` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `rfq_id`        INT UNSIGNED NOT NULL,
  `supplier_id`   INT UNSIGNED NOT NULL,
  `offer_ref`     VARCHAR(60)  NOT NULL DEFAULT '',
  `submitted_at`  DATETIME     NULL,
  `received_by`   INT UNSIGNED NOT NULL DEFAULT 0,
  `valid_until`   DATE         NULL,
  `currency`      VARCHAR(8)   NOT NULL DEFAULT '',
  `fx_rate`       DECIMAL(14,6) NOT NULL DEFAULT 1,
  `total_amount`  DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'مشتق من proc_offer_line',
  `base_amount`   DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'total × fx_rate',
  `line_count`    INT          NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `delivery_days` INT          NOT NULL DEFAULT 0,
  `payment_terms` VARCHAR(190) NOT NULL DEFAULT '',
  `late`          TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'مشتق من due_date - لا يكتب بيد',
  `state`         VARCHAR(32)  NOT NULL DEFAULT 'received',
  `notes`         VARCHAR(500) NOT NULL DEFAULT '',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_offer` (`rfq_id`,`supplier_id`) COMMENT 'مورد واحد عرض واحد لكل طلب',
  KEY `ix_offer` (`company_id`,`rfq_id`),
  CONSTRAINT `fk_off_rfq` FOREIGN KEY (`rfq_id`) REFERENCES `proc_rfq` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_off_cur` CHECK (`currency` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PRC-08 عرض × مورد × طلب عروض - Child Register برأسه'", 'proc_offer');

$run("
CREATE TABLE IF NOT EXISTS `proc_offer_line` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`      INT UNSIGNED NOT NULL,
  `offer_id`        INT UNSIGNED NOT NULL,
  `request_line_id` INT UNSIGNED NOT NULL COMMENT 'بند الطلب الذي يقابله - مقارنة بندا ببند',
  `item_id`         INT UNSIGNED NOT NULL DEFAULT 0,
  `item_name`       VARCHAR(190) NOT NULL DEFAULT '',
  `qty_offered`     DECIMAL(16,3) NOT NULL DEFAULT 0,
  `unit_price`      DECIMAL(16,4) NOT NULL DEFAULT 0,
  `subtotal`        DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'qty × price - مشتق',
  `brand`           VARCHAR(120) NOT NULL DEFAULT '',
  `is_alternative`  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'بديل عن المطلوب - يعلن ولا يقارن كمطابق',
  `alt_why`         VARCHAR(400) NOT NULL DEFAULT '',
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_offl` (`offer_id`,`request_line_id`),
  KEY `ix_offl` (`company_id`,`offer_id`),
  CONSTRAINT `fk_offl_off` FOREIGN KEY (`offer_id`) REFERENCES `proc_offer` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_offl_alt` CHECK (`is_alternative` = 0 OR `alt_why` <> ''),
  CONSTRAINT `chk_offl_qty` CHECK (`qty_offered` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PRC-09 بند عرض × عرض × بند طلب - Line-by-Line Child'", 'proc_offer_line');

/* ◆ **محضرُ الترسيةِ واحدٌ لكلِّ طلبِ عروض** — والأرخصُ ليس حكمًا آليًّا:
     `award_why` إلزاميٌّ حين لا يكون الفائزُ الأدنى سعرًا. */
$run("
CREATE TABLE IF NOT EXISTS `proc_award` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `rfq_id`         INT UNSIGNED NOT NULL,
  `minute_no`      VARCHAR(40)  NOT NULL DEFAULT '',
  `committee_ref`  VARCHAR(190) NOT NULL DEFAULT '',
  `criteria_ref`   VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'معايير التقييم المعلنة قبل الفتح',
  `winner_id`      INT UNSIGNED NOT NULL DEFAULT 0,
  `winner_amount`  DECIMAL(16,2) NOT NULL DEFAULT 0,
  `lowest_id`      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'مشتق - اقل العروض سعرا',
  `lowest_amount`  DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `is_lowest`      TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'مشتق - لا يكتب بيد',
  `award_why`      VARCHAR(900) NOT NULL DEFAULT '' COMMENT 'الزامي حين الفائز ليس الادنى',
  `approved_by`    INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_at`    DATETIME     NULL,
  `state`          VARCHAR(32)  NOT NULL DEFAULT 'draft',
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_award_rfq` (`rfq_id`) COMMENT 'ترسية واحدة لكل طلب عروض',
  CONSTRAINT `fk_awd_rfq` FOREIGN KEY (`rfq_id`) REFERENCES `proc_rfq` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_awd_why` CHECK (`is_lowest` = 1 OR `award_why` <> ''),
  CONSTRAINT `chk_awd_crit` CHECK (`criteria_ref` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PRC-10 محضر × طلب عروض - ترسية واحدة'", 'proc_award');

/* ═══════════════════════════════════════════════════════════════════════════
   ④ الأمرُ وتعديلاتُه ومتابعةُ توريدِه ومطابقتُه
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n④ الأمرُ وتعديلاتُه ومتابعتُه ─────────────────────────────────\n";

$addCol('proc_order', 'package_id',
    "`package_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'الحزمة التي انتج الامر عنها'");
$addCol('proc_order', 'award_minute_id',
    "`award_minute_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'محضر الترسية - امر بلا سند تنافسي يعلن'");
$addCol('proc_order', 'direct_reason',
    "`direct_reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'سبب الشراء المباشر - الزامي بلا محضر'");
$addCol('proc_order', 'amend_count',
    "`amend_count` INT NOT NULL DEFAULT 0 COMMENT 'مشتق من proc_po_amendment'");

$run("
CREATE TABLE IF NOT EXISTS `proc_po_amendment` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `order_id`      INT UNSIGNED NOT NULL,
  `seq_no`        INT          NOT NULL DEFAULT 1,
  `kind`          VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'QTY او PRICE او DATE او CANCEL او ITEM',
  `before_val`    VARCHAR(300) NOT NULL DEFAULT '',
  `after_val`     VARCHAR(300) NOT NULL DEFAULT '',
  `delta_amount`  DECIMAL(16,2) NOT NULL DEFAULT 0,
  `reason`        VARCHAR(600) NOT NULL DEFAULT '',
  `gov_path`      VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'المسار الحوكمي الذي اعتمده',
  `requested_by`  INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_by`   INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_at`   DATETIME     NULL,
  `state`         VARCHAR(32)  NOT NULL DEFAULT 'pending',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amend` (`order_id`,`seq_no`),
  KEY `ix_amend` (`company_id`,`order_id`),
  CONSTRAINT `chk_amd_full` CHECK (`kind` <> '' AND `reason` <> '' AND `gov_path` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PRC-13 استثناء او تعديل × امر - سطر بمساره الحوكمي'", 'proc_po_amendment');

$run("
CREATE TABLE IF NOT EXISTS `proc_delivery_event` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `order_id`     INT UNSIGNED NOT NULL,
  `event_kind`   VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'PROMISED او SHIPPED او ARRIVED او DELAYED او PARTIAL',
  `event_date`   DATE         NULL,
  `qty_expected` DECIMAL(16,3) NOT NULL DEFAULT 0,
  `qty_actual`   DECIMAL(16,3) NOT NULL DEFAULT 0,
  `delay_days`   INT          NOT NULL DEFAULT 0 COMMENT 'مشتق من الوعد والواقع',
  `delay_why`    VARCHAR(400) NOT NULL DEFAULT '',
  `receipt_id`   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'سند الادخال ان وقع',
  `logged_by`    INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_dlv` (`company_id`,`order_id`,`event_date`),
  CONSTRAINT `chk_dlv_kind` CHECK (`event_kind` <> ''),
  CONSTRAINT `chk_dlv_delay` CHECK (`event_kind` <> 'DELAYED' OR `delay_why` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PRC-14 حدث توريد × امر - سطر متابعة'", 'proc_delivery_event');

/* ◆ **المطابقةُ ثلاثيّةٌ بحبّةِ (فاتورة × أمر)** — و`proc_order.match_state`
     رأسُ الأمرِ وحدَه فلا يتّسع لأمرٍ بفاتورتَين. */
$run("
CREATE TABLE IF NOT EXISTS `proc_invoice_match` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `order_id`       INT UNSIGNED NOT NULL,
  `invoice_no`     VARCHAR(60)  NOT NULL DEFAULT '',
  `invoice_date`   DATE         NULL,
  `invoice_amount` DECIMAL(16,2) NOT NULL DEFAULT 0,
  `po_amount`      DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'مشتق من بنود الامر',
  `grn_amount`     DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'مشتق من سندات الادخال',
  `var_invoice_po` DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `var_grn_po`     DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `within_tol`     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'مشتق من عتبة السجل لا من رقم صلب',
  `verdict`        VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'MATCHED او VARIANCE او BLOCKED',
  `var_decision`   VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'ACCEPT او REJECT - للفرق خارج العتبة',
  `var_reason`     VARCHAR(600) NOT NULL DEFAULT '',
  `decided_by`     INT UNSIGNED NOT NULL DEFAULT 0,
  `decided_at`     DATETIME     NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_match` (`company_id`,`order_id`,`invoice_no`),
  CONSTRAINT `chk_mt_inv` CHECK (`invoice_no` <> ''),
  CONSTRAINT `chk_mt_var` CHECK (`within_tol` = 1 OR `verdict` <> 'MATCHED'),
  CONSTRAINT `chk_mt_dec` CHECK (`var_decision` = '' OR `var_reason` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PRC-15 مطابقة × فاتورة × امر - مطابقة واحدة'", 'proc_invoice_match');

$run("
CREATE TABLE IF NOT EXISTS `proc_supplier_eval` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `supplier_id`   INT UNSIGNED NOT NULL,
  `period_ym`     CHAR(7)      NOT NULL DEFAULT '',
  `orders_count`  INT          NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `on_time_pct`   DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'مشتق من proc_delivery_event',
  `reject_pct`    DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'مشتق من سندات الادخال',
  `variance_pct`  DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'مشتق من proc_invoice_match',
  `score`         DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'مشتق بقاعدة الوزن',
  `score_rule`    VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'قاعدة الاشتقاق - لا رقم بلا قاعدة',
  `grade`         VARCHAR(24)  NOT NULL DEFAULT '',
  `computed_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seval` (`company_id`,`supplier_id`,`period_ym`),
  CONSTRAINT `chk_sev_rule` CHECK (`score_rule` <> '' AND `period_ym` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PRC-16 مورد × فترة - سطر تقييم مشتق'", 'proc_supplier_eval');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ المخازن — الإدخالُ والتتبّعُ والرصيدُ والخطرُ والصرفُ والتحويلُ والجرد
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑤ المخازن ────────────────────────────────────────────────────\n";

/* بنودُ سندِ الإدخالِ — الكميّاتُ الثلاثُ وبياناتُ التتبّعِ بالانطباق (WH-05) */
$addCol('proc_receipt_line', 'qty_received',
    "`qty_received` DECIMAL(16,3) NOT NULL DEFAULT 0 COMMENT 'الوارد'");
$addCol('proc_receipt_line', 'qty_accepted',
    "`qty_accepted` DECIMAL(16,3) NOT NULL DEFAULT 0 COMMENT 'المقبول بعد الفحص'");
$addCol('proc_receipt_line', 'qty_rejected',
    "`qty_rejected` DECIMAL(16,3) NOT NULL DEFAULT 0 COMMENT 'المرفوض'");
$addCol('proc_receipt_line', 'reject_reason',
    "`reject_reason` VARCHAR(400) NOT NULL DEFAULT ''");
$addCol('proc_receipt_line', 'lot_no',
    "`lot_no` VARCHAR(60) NOT NULL DEFAULT '' COMMENT 'يلزم حين track_lot=1 - خامد حتى DEC-OPEN-15'");
$addCol('proc_receipt_line', 'serial_no',
    "`serial_no` VARCHAR(80) NOT NULL DEFAULT '' COMMENT 'يلزم حين track_serial=1'");
$addCol('proc_receipt_line', 'expiry_date',
    "`expiry_date` DATE NULL COMMENT 'يلزم حين track_expiry=1'");
$addCol('proc_receipt_line', 'unit_cost',
    "`unit_cost` DECIMAL(16,4) NOT NULL DEFAULT 0");
$addCol('proc_receipt_line', 'order_line_id',
    "`order_line_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'بند الامر المقابل - للمطابقة الثلاثية'");

/* ◆ حالةُ الرصيدِ بُعدٌ مستقلٌّ عن الكميّة (WH-06): الصالحُ والمحجوزُ
     والمحجورُ والتالفُ لا تُجمع في رقمٍ واحد وإلا صار المتاحُ كذبًا. */
$run("
CREATE TABLE IF NOT EXISTS `proc_stock_state` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `item_id`      INT UNSIGNED NOT NULL,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `state_key`    VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'GOOD او RESERVED او QUARANTINE او DAMAGED او EXPIRED',
  `qty`          DECIMAL(16,3) NOT NULL DEFAULT 0 COMMENT 'مشتق من proc_stock_move - لا يكتب بيد',
  `derive_rule`  VARCHAR(190) NOT NULL DEFAULT '',
  `computed_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sstate` (`company_id`,`item_id`,`warehouse_id`,`state_key`),
  CONSTRAINT `chk_ss_rule` CHECK (`derive_rule` <> '' AND `state_key` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WH-06 صنف × مخزن × حالة - سطر رصيد مشتق'", 'proc_stock_state');

$run("
CREATE TABLE IF NOT EXISTS `proc_hazmat_control` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `item_id`        INT UNSIGNED NOT NULL,
  `hazard_class`   VARCHAR(60)  NOT NULL DEFAULT '',
  `store_rule`     VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'ضوابط التخزين',
  `handling_rule`  VARCHAR(400) NOT NULL DEFAULT '',
  `permit_needed`  TINYINT(1)   NOT NULL DEFAULT 0,
  `permit_ref`     VARCHAR(190) NOT NULL DEFAULT '',
  `issue_gate`     VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'بوابة الصرف - من يجيز',
  `separation_rule` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'ما لا يخزن بجواره',
  `decision_ref`   VARCHAR(48)  NOT NULL DEFAULT '',
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_haz` (`company_id`,`item_id`),
  CONSTRAINT `chk_haz_full` CHECK (`hazard_class` <> '' AND `store_rule` <> ''
                                   AND `handling_rule` <> '' AND `issue_gate` <> ''),
  CONSTRAINT `chk_haz_permit` CHECK (`permit_needed` = 0 OR `permit_ref` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WH-07 صنف خطر × ضوابطه - سطر ضوابط'", 'proc_hazmat_control');

/* ◆ **طلبُ الصرفِ غيرُ سندِ الصرف** (WH-08/09 نصًّا): الجهةُ تطلب والمخزنُ
     يصرف — وخلطُهما يجعل «طُلب ولم يُصرف» غيرَ قابلٍ للقياس. */
$run("
CREATE TABLE IF NOT EXISTS `proc_issue_request` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `code`           VARCHAR(40)  NOT NULL DEFAULT '',
  `warehouse_id`   INT UNSIGNED NOT NULL DEFAULT 0,
  `requesting_dept` VARCHAR(120) NOT NULL DEFAULT '',
  `requester_id`   INT UNSIGNED NOT NULL DEFAULT 0,
  `purpose`        VARCHAR(400) NOT NULL DEFAULT '',
  `equipment_id`   INT UNSIGNED NOT NULL DEFAULT 0,
  `project_id`     INT UNSIGNED NOT NULL DEFAULT 0,
  `maintenance_order_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `need_date`      DATE         NULL,
  `priority`       VARCHAR(24)  NOT NULL DEFAULT '',
  `issue_id`       INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'سند الصرف الذي نفذه ان وقع',
  `state`          VARCHAR(32)  NOT NULL DEFAULT 'draft',
  `reject_reason`  VARCHAR(400) NOT NULL DEFAULT '',
  `notes`          VARCHAR(500) NOT NULL DEFAULT '',
  `is_deleted`     TINYINT(1)   NOT NULL DEFAULT 0,
  `created_by`     INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ireq_code` (`company_id`,`code`),
  KEY `ix_ireq` (`company_id`,`state`),
  CONSTRAINT `chk_ireq_purpose` CHECK (`purpose` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WH-08 طلب صرف × جهة - طلب واحد'", 'proc_issue_request');

$run("
CREATE TABLE IF NOT EXISTS `proc_issue_request_line` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `request_id`   INT UNSIGNED NOT NULL,
  `item_id`      INT UNSIGNED NOT NULL DEFAULT 0,
  `item_name`    VARCHAR(190) NOT NULL DEFAULT '',
  `qty_requested` DECIMAL(16,3) NOT NULL DEFAULT 0,
  /* ◆ **`NULL` تعني «لم يُبَتَّ فيه بعد» لا «صفرٌ معتمَد»** — والفرقُ جوهريّ:
       سطرٌ يُنشأ قبل الاعتمادِ بصفرٍ يجعل قيدَ التعليلِ يقرؤه **خفضًا كاملًا
       بلا سبب** فيرفض إنشاءَ الطلبِ أصلًا. والصفرُ المعتمَدُ عمدًا **يبقى
       خفضًا يوجب سببًا** لأنّه قرارٌ لا غياب. */
  `qty_approved` DECIMAL(16,3) NULL DEFAULT NULL COMMENT 'NULL لم يبت فيه - وصفر قرار يوجب سببا',
  `qty_issued`   DECIMAL(16,3) NOT NULL DEFAULT 0 COMMENT 'مشتق من بنود سند الصرف',
  `cut_reason`   VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'سبب خفض المعتمد عن المطلوب',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ireql` (`company_id`,`request_id`),
  CONSTRAINT `fk_ireql_req` FOREIGN KEY (`request_id`) REFERENCES `proc_issue_request` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_ireql_qty` CHECK (`qty_requested` > 0
                                    AND (`qty_approved` IS NULL OR `qty_approved` <= `qty_requested`)),
  CONSTRAINT `chk_ireql_cut` CHECK (`qty_approved` IS NULL
                                    OR `qty_approved` = `qty_requested` OR `cut_reason` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WH-09 بند × طلب صرف - Request Lines Child غير بنود السند'", 'proc_issue_request_line');

$run("
CREATE TABLE IF NOT EXISTS `proc_transfer` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `code`         VARCHAR(40)  NOT NULL DEFAULT '',
  `from_wh_id`   INT UNSIGNED NOT NULL DEFAULT 0,
  `to_wh_id`     INT UNSIGNED NOT NULL DEFAULT 0,
  `reason`       VARCHAR(400) NOT NULL DEFAULT '',
  `sent_at`      DATETIME     NULL,
  `sent_by`      INT UNSIGNED NOT NULL DEFAULT 0,
  `received_at`  DATETIME     NULL,
  `received_by`  INT UNSIGNED NOT NULL DEFAULT 0,
  `in_transit_qty` DECIMAL(16,3) NOT NULL DEFAULT 0 COMMENT 'مشتق - المرسل ناقص المستلم',
  `state`        VARCHAR(32)  NOT NULL DEFAULT 'draft',
  `notes`        VARCHAR(500) NOT NULL DEFAULT '',
  `is_deleted`   TINYINT(1)   NOT NULL DEFAULT 0,
  `created_by`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trf_code` (`company_id`,`code`),
  CONSTRAINT `chk_trf_diff` CHECK (`from_wh_id` <> `to_wh_id`),
  CONSTRAINT `chk_trf_why` CHECK (`reason` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WH-13 امر تحويل × مخزنين - امر واحد'", 'proc_transfer');

$run("
CREATE TABLE IF NOT EXISTS `proc_transfer_line` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `transfer_id` INT UNSIGNED NOT NULL,
  `item_id`     INT UNSIGNED NOT NULL DEFAULT 0,
  `item_name`   VARCHAR(190) NOT NULL DEFAULT '',
  `qty_sent`    DECIMAL(16,3) NOT NULL DEFAULT 0,
  `qty_received` DECIMAL(16,3) NOT NULL DEFAULT 0,
  `qty_variance` DECIMAL(16,3) NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `variance_why` VARCHAR(400) NOT NULL DEFAULT '',
  `lot_no`      VARCHAR(60)  NOT NULL DEFAULT '',
  `serial_no`   VARCHAR(80)  NOT NULL DEFAULT '',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_trfl` (`company_id`,`transfer_id`),
  CONSTRAINT `fk_trfl_trf` FOREIGN KEY (`transfer_id`) REFERENCES `proc_transfer` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_trfl_qty` CHECK (`qty_sent` > 0),
  CONSTRAINT `chk_trfl_var` CHECK (`qty_variance` = 0 OR `variance_why` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WH-14 بند × امر تحويل - Lines Child'", 'proc_transfer_line');

$run("
CREATE TABLE IF NOT EXISTS `proc_count_session` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `code`         VARCHAR(40)  NOT NULL DEFAULT '',
  `warehouse_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `count_kind`   VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'FULL او CYCLE او SPOT',
  `count_date`   DATE         NULL,
  `counted_by`   INT UNSIGNED NOT NULL DEFAULT 0,
  `reviewed_by`  INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_by`  INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_at`  DATETIME     NULL,
  `line_count`   INT          NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `diff_count`   INT          NOT NULL DEFAULT 0 COMMENT 'مشتق - بنود بفرق',
  `diff_value`   DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `state`        VARCHAR(32)  NOT NULL DEFAULT 'draft',
  `notes`        VARCHAR(500) NOT NULL DEFAULT '',
  `is_deleted`   TINYINT(1)   NOT NULL DEFAULT 0,
  `created_by`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cnt_code` (`company_id`,`code`),
  CONSTRAINT `chk_cnt_kind` CHECK (`count_kind` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WH-15 جلسة جرد × مخزن - Header وبنود الفروق Child'", 'proc_count_session');

/* ⛔ **الفرقُ بلا قرارِ تسويةٍ لا يُقفل** — والقرارُ بسببٍ مكتوب */
$run("
CREATE TABLE IF NOT EXISTS `proc_count_line` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `session_id`    INT UNSIGNED NOT NULL,
  `item_id`       INT UNSIGNED NOT NULL DEFAULT 0,
  `item_name`     VARCHAR(190) NOT NULL DEFAULT '',
  `qty_book`      DECIMAL(16,3) NOT NULL DEFAULT 0 COMMENT 'الدفتري - مشتق من proc_stock_move',
  `qty_counted`   DECIMAL(16,3) NOT NULL DEFAULT 0,
  `qty_diff`      DECIMAL(16,3) NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `unit_cost`     DECIMAL(16,4) NOT NULL DEFAULT 0,
  `diff_value`    DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `settle_action` VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'ADJUST او INVESTIGATE او WRITE_OFF',
  `settle_why`    VARCHAR(600) NOT NULL DEFAULT '',
  `settled_by`    INT UNSIGNED NOT NULL DEFAULT 0,
  `settled_at`    DATETIME     NULL,
  `move_id`       INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'حركة التسوية المنشاة',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cntl` (`session_id`,`item_id`),
  KEY `ix_cntl` (`company_id`,`session_id`),
  CONSTRAINT `fk_cntl_ses` FOREIGN KEY (`session_id`) REFERENCES `proc_count_session` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_cntl_settle` CHECK (`qty_diff` = 0 OR `settle_action` = '' OR `settle_why` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WH-16 صنف × جلسة جرد - Lines Child بقرار التسوية'", 'proc_count_line');

$run("
CREATE TABLE IF NOT EXISTS `proc_wh_close` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `warehouse_id`  INT UNSIGNED NOT NULL,
  `period_ym`     CHAR(7)      NOT NULL DEFAULT '',
  `open_value`    DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `in_value`      DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `out_value`     DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `adj_value`     DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'مشتق من تسويات الجرد',
  `close_value`   DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'مشتق - فتح زائد وارد ناقص منصرف زائد تسوية',
  `balanced`      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'مشتق - المعادلة تنطبق',
  `count_ref`     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'جلسة الجرد المسندة',
  `closed_by`     INT UNSIGNED NOT NULL DEFAULT 0,
  `closed_at`     DATETIME     NULL,
  `state`         VARCHAR(32)  NOT NULL DEFAULT 'open',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_whclose` (`company_id`,`warehouse_id`,`period_ym`),
  CONSTRAINT `chk_whc_period` CHECK (`period_ym` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WH-18 شهر × مخزن - اقفال واحد'", 'proc_wh_close');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ تصحيحُ قيدٍ كتبَته هذه الهجرةُ نفسُها — ويُطبَّق على جدولٍ قائمٍ سلفًا
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **العطبُ كان في القيدِ لا في البيانات**: `chk_ireql_cut` بصيغتِه الأولى
     `qty_approved = qty_requested OR cut_reason <> ''` يقرأ **سطرًا لم يُعتمَد
     بعد** (‏`qty_approved = 0` عند الإنشاء) خفضًا كاملًا بلا سبب، فيرفض إنشاءَ
     بندِ الطلبِ من أساسِه. وكُشف بالرحلةِ لا بالقراءة.
   ◆ والعلاجُ **تمييزُ الغيابِ من القرار**: `NULL` لم يُبَتَّ فيه، والصفرُ
     المعتمَدُ يبقى خفضًا يوجب سببًا. */
echo "\n⑥ تصحيحُ قيدِ بندِ طلبِ الصرف ────────────────────────────────\n";
if (w9_tbl($conn, 'proc_issue_request_line')) {
    $nullable = false;
    $r = $conn->query("SHOW COLUMNS FROM `proc_issue_request_line` LIKE 'qty_approved'");
    if ($r && $x = $r->fetch_assoc()) { $nullable = (strtoupper((string) $x['Null']) === 'YES'); }
    if ($nullable) {
        echo "  ↷ القيدُ مُصحَّحٌ سلفًا\n"; $skip++;
    } else {
        $conn->query("ALTER TABLE `proc_issue_request_line` DROP CONSTRAINT `chk_ireql_cut`");
        $conn->query("ALTER TABLE `proc_issue_request_line` DROP CONSTRAINT `chk_ireql_qty`");
        $run("ALTER TABLE `proc_issue_request_line`
                MODIFY `qty_approved` DECIMAL(16,3) NULL DEFAULT NULL
                COMMENT 'NULL لم يبت فيه - وصفر قرار يوجب سببا'", 'qty_approved تقبل العدم');
        $run("ALTER TABLE `proc_issue_request_line`
                ADD CONSTRAINT `chk_ireql_qty` CHECK (`qty_requested` > 0
                    AND (`qty_approved` IS NULL OR `qty_approved` <= `qty_requested`))", 'chk_ireql_qty');
        $run("ALTER TABLE `proc_issue_request_line`
                ADD CONSTRAINT `chk_ireql_cut` CHECK (`qty_approved` IS NULL
                    OR `qty_approved` = `qty_requested` OR `cut_reason` <> '')", 'chk_ireql_cut');
    }
}

echo "\n───────────────────────────────────────────────────────────────\n";
echo "الخلاصة: نُفِّذ $done · مُتخطًّى $skip · أخطاء $err\n";
exit($err > 0 ? 1 : 0);
