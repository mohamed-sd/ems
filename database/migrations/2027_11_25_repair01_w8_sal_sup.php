<?php
/**
 * 2027_11_25_repair01_w8_sal_sup.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W08 — **المبيعاتُ والموردون: انحدارٌ لا إعادةُ بناء**
 *
 * ◆ **ولماذا دفترُ انحدارٍ قبلَ كلِّ شيء** (§٤-٢): الوحدتانِ مرجعيّتانِ
 *   (‏Reference Implementations)، و§19 يأمر بتشغيلِ الانحدارِ **أوّلًا** وتسجيلِ
 *   نتيجتِه **قبل** أيِّ تعديل. فـ`repair01_w8_regression` يحمل شوطَين:
 *   `BASELINE` قبل أوّلِ لمسة، و`AFTER` بعد الإصلاح — والبوّابةُ تقارنهما
 *   فتسقط على **تراجعٍ** لا على مجرَّدِ سقوطٍ لحظيّ.
 *
 * ◆ **و`repair01_w8_fixes` يجعل «تعديلٌ بلا متطلَّبٍ كاشف» قابلًا للقياس**
 *   (§٦ · المتوقَّع `0`): كلُّ إصلاحٍ يحمل `revealed_by` = مُعرِّفَ المتطلَّبِ
 *   الذي كشفه، و`CHECK` يمنع صفًّا بلا كاشف. **فالمنعُ في المخطَّطِ لا في النيّة.**
 *
 * ◆ **ورحلتانِ لا رحلة** (§٦-أ): رحلةُ العميلِ ورحلةُ المورد. فـ`journey_key`
 *   عمودٌ في دفترِ الرحلةِ — وبلا فصلٍ تُخلَط محطّاتُهما ويصير «عابرٌ ٢٤/٢٤»
 *   دعوى على مقامٍ مركَّب.
 *
 * ◆ **و`CHECK` لا قادِح**: مستخدمُ الهجراتِ لا يملك `SUPER` (‏W03 · 2027_11_19).
 *
 * ⛔ **ولا عتبةٌ رقميّةٌ في الشيفرة** (§٥) — كلُّها في `repair01_w8_thresholds`.
 *
 * ⛔ **ولا يُمَسُّ جدولُ أعمالٍ حيٌّ من جداولِ الوحدتَين في هذه الهجرة**: الوحدتانِ
 *   مرجعيّتانِ (§19)، والأعمدةُ الملحقةُ في §③ كشفها المستهدَفُ الجديدُ وحدَه
 *   وكلُّها مسجَّلةٌ في `repair01_w8_fixes` بمتطلَّبِها الكاشف.
 *
 * التشغيل: php database/migrations/2027_11_25_repair01_w8_sal_sup.php
 *          (⛔ لا `migrate.php up` — الهجرةُ بمسارِها الكاملِ منفردةً)
 * التراجع: php database/migrations/2027_11_25_repair01_w8_sal_sup_down.php
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

function w8_tbl(mysqli $c, $t)
{
    $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}
function w8_col(mysqli $c, $t, $col)
{
    if (!w8_tbl($c, $t)) { return false; }
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

$done = 0; $err = 0; $skip = 0;
$run = function ($sql, $label) use ($conn, &$done, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; return true; }
    echo "  ✘ $label — " . $conn->error . "\n"; $err++; return false;
};
$addCol = function ($t, $col, $ddl) use ($conn, &$done, &$skip, &$err) {
    if (!w8_tbl($conn, $t)) { echo "  ⚠ $t غير موجود — $col يُتخطّى\n"; $skip++; return; }
    if (w8_col($conn, $t, $col)) { echo "  ↷ $t.$col قائم\n"; $skip++; return; }
    if ($conn->query("ALTER TABLE `$t` ADD COLUMN $ddl") === true) { echo "  ✔ $t.$col\n"; $done++; }
    else { echo "  ✘ $t.$col — " . $conn->error . "\n"; $err++; }
};

echo "══ REPAIR01 · W08 — المبيعاتُ والموردون: انحدارٌ لا إعادةُ بناء ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ① دفاترُ المرحلة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① دفاترُ المرحلة ───────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w8_scope` (
  `requirement_id`   VARCHAR(48)  NOT NULL,
  `unit`             VARCHAR(160) NOT NULL DEFAULT '',
  `group_name`       VARCHAR(160) NOT NULL DEFAULT '',
  `surface`          VARCHAR(255) NOT NULL DEFAULT '',
  `anchor_screen_id` VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'Canonical Screen_ID او فراغ لما لم يبن',
  `anchor_route`     VARCHAR(200) NOT NULL DEFAULT '',
  `anchor_probe`     VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'الجدول او الصنف الذي يثبت المرساة قياسا',
  `owner_measured`   VARCHAR(12)  NOT NULL DEFAULT '',
  `owner_expected`   VARCHAR(12)  NOT NULL DEFAULT '',
  `owner_verdict`    VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'MATCH او MISMATCH - يعلن ولا يدهس',
  `map_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `map_why`          VARCHAR(400) NOT NULL DEFAULT '',
  `wave_stage`       VARCHAR(8)   NOT NULL DEFAULT '',
  `src_ref`          VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`requirement_id`),
  KEY `ix_screen` (`anchor_screen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W08 - ربط متطلبات المرحلة بالسجل المعياري للشاشات'", 'repair01_w8_scope');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w8_sidebar` (
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
  COMMENT='REPAIR01 W08 - الخطوات السبع للسايدبار داخل نطاق المرحلة'", 'repair01_w8_sidebar');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w8_decisions` (
  `decision_id` VARCHAR(24)  NOT NULL,
  `question`    VARCHAR(400) NOT NULL DEFAULT '',
  `ruling`      VARCHAR(900) NOT NULL DEFAULT '',
  `rationale`   VARCHAR(900) NULL,
  `scope_rows`  INT          NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`decision_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W08 - قرارات المرحلة'", 'repair01_w8_decisions');

/* ⛔ رحلتان لا رحلة — و`journey_key` يمنع خلطَ مقامَيهما */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w8_journey` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id`          VARCHAR(40)  NOT NULL DEFAULT '',
  `journey_key`     VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'client او supplier - رحلتان لا رحلة',
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
  KEY `ix_run` (`run_id`,`journey_key`,`station_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W08 - رحلتا العميل والمورد: محطاتهما واثر كل مستهلك'", 'repair01_w8_journey');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w8_states` (
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
  CONSTRAINT `chk_w8st_forbid` CHECK (`allowed` = 1 OR `forbid_reason` <> ''),
  CONSTRAINT `chk_w8st_allow`  CHECK (`allowed` = 0 OR (`owner_role` <> '' AND `precondition` <> ''
                                       AND `official_doc` <> '' AND `approval_gate` <> ''
                                       AND `reopen_rule` <> '' AND `correct_rule` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W08 - آلة حالة لكل كيان رئيسي في النطاق'", 'repair01_w8_states');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w8_sod` (
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
  CONSTRAINT `chk_w8sod_full` CHECK (`initiator_role` <> '' AND `approver_role` <> ''
                                     AND `executor_role` <> '' AND `closer_role` <> ''
                                     AND `forbidden_combo` <> '' AND `authority_rule_id` <> ''
                                     AND `enforced_by` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W08 - فصل الواجبات بستة ادوار وتركيبة ممنوعة'", 'repair01_w8_sod');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w8_thresholds` (
  `threshold_key` VARCHAR(48)   NOT NULL,
  `value_num`     DECIMAL(14,4) NOT NULL DEFAULT 0,
  `unit_ar`       VARCHAR(40)   NOT NULL DEFAULT '',
  `title_ar`      VARCHAR(190)  NOT NULL DEFAULT '',
  `why`           VARCHAR(600)  NOT NULL DEFAULT '',
  `decision_ref`  VARCHAR(48)   NOT NULL DEFAULT '',
  `src_ref`       VARCHAR(190)  NOT NULL DEFAULT '',
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`threshold_key`),
  CONSTRAINT `chk_w8th_why` CHECK (`why` <> '' AND `decision_ref` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W08 - عتبات المرحلة: من السجل لا من الشيفرة'", 'repair01_w8_thresholds');

/* ═══════════════════════════════════════════════════════════════════════════
   ② دفترُ الانحدارِ ودفترُ الإصلاحات
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n② دفترُ الانحدارِ ودفترُ الإصلاحات ─────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w8_regression` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `phase`        ENUM('BASELINE','AFTER') NOT NULL DEFAULT 'BASELINE',
  `run_id`       VARCHAR(40)  NOT NULL DEFAULT '',
  `check_key`    VARCHAR(60)  NOT NULL DEFAULT '',
  `family`       VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'SAL او SUP او XCUT',
  `title_ar`     VARCHAR(190) NOT NULL DEFAULT '',
  `denominator`  INT          NOT NULL DEFAULT 0 COMMENT 'مقام المقياس - صفر يعلن ولا يمر صامتا',
  `measured`     INT          NOT NULL DEFAULT 0,
  `expected`     VARCHAR(60)  NOT NULL DEFAULT '',
  `verdict`      ENUM('PASS','FAIL','EMPTY_DENOM') NOT NULL DEFAULT 'PASS',
  `detail`       VARCHAR(600) NOT NULL DEFAULT '',
  `revealed_by`  VARCHAR(48)  NOT NULL DEFAULT '' COMMENT 'المتطلب الذي يقيسه هذا الفحص',
  `run_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_phase_check` (`phase`,`check_key`),
  KEY `ix_run` (`run_id`),
  CONSTRAINT `chk_w8rg_rev` CHECK (`revealed_by` <> '' AND `check_key` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W08 - انحدار الوحدتين المرجعيتين: شوط قبل واخر بعد'", 'repair01_w8_regression');

/* ⛔ «تعديلٌ بلا متطلَّبٍ كاشف» يُمنع في المخطَّطِ لا في النيّة */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w8_fixes` (
  `fix_key`      VARCHAR(60)  NOT NULL,
  `kind`         VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'SCHEMA او CODE او REGISTRY او TOOL',
  `target`       VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'الملف او الجدول او العمود الملموس',
  `what`         VARCHAR(600) NOT NULL DEFAULT '',
  `revealed_by`  VARCHAR(48)  NOT NULL DEFAULT '' COMMENT 'معرف المتطلب الكاشف - لا اصلاح بلا كاشف',
  `reveal_why`   VARCHAR(600) NOT NULL DEFAULT '',
  `evidence`     VARCHAR(400) NOT NULL DEFAULT '',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`fix_key`),
  KEY `ix_rev` (`revealed_by`),
  CONSTRAINT `chk_w8fx_rev` CHECK (`revealed_by` <> '' AND `reveal_why` <> ''
                                   AND `target` <> '' AND `kind` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W08 - كل اصلاح بمتطلبه الكاشف'", 'repair01_w8_fixes');
echo "\n───────────────────────────────────────────────────────────────\n";
echo "الخلاصة: نُفِّذ $done · مُتخطًّى $skip · أخطاء $err\n";
exit($err > 0 ? 1 : 0);
