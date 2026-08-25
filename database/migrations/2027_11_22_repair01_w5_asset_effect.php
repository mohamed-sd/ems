<?php
/**
 * 2027_11_22_repair01_w5_asset_effect.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W05 — **أثرُ الأصلِ والقوى**: دورةُ الأصلِ كاملةً (دخولٌ · حركةٌ ·
 * رقابةٌ فنيّةٌ · خروج) وتخصيصُ القوى وأداؤها.
 *
 * ◆ **لماذا `asset_use_right` سجلٌّ بفترةٍ لا عمودُ مالك**: المقيسُ أنَّ
 *   `asset_ownership_shares` يحمل **٧١ حصّةً على ٣١ معدّة**، وأنَّ ٦٦ زوجًا منها
 *   تتقاطع فتراتُه على ١٨ أصلًا. وبقياسِ **النوافذِ** لا الأزواج: ٥٨ نافذةَ
 *   بدايةٍ، **واحدةٌ منها يبلغ مجموعُ الحصصِ المتزامنةِ فيها ٢٠٠٪** (الأصل ٨ في
 *   2025-09-09: مموِّلٌ يحمل ١٠٠٪ من 2022 إلى 2026، وحصّتانِ ٤٠٪+٦٠٪ تبدآن فوقَه).
 *   والمتطلَّبُ `FLEET-09` يقول نصًّا: «الملكيّةُ متعاقبةٌ لا متزامنة». ومدَّعيانِ
 *   كاملانِ لحقِّ استخدامِ آلةٍ واحدةٍ في النافذةِ نفسِها ليس تفصيلًا محاسبيًّا —
 *   **هو الذي يقرّر مَن يفوتر ساعتَها ومَن يتحمّل التزامَ جاهزيّتِها**.
 *
 *   ⚠ **والحبّةُ هي التي تحكم**: القياسُ بضمِّ الجدولِ إلى نفسِه ثمّ `SUM`
 *   يعطي «٨ نوافذَ حتى ٤٠٠٪» — لأنَّ الحبّةَ الواحدةَ (أصل × تاريخ) تحمل أكثرَ
 *   من صفٍّ فيتضاعف المجموعُ بعددِ صفوفِها. **رقمٌ يُقرأ صحيحًا وهو خطأ**،
 *   والصوابُ ٥٨ نافذةً وواحدةٌ فوقَ المئة.
 *   والعلاجُ ليس دهسَ الصفوف — الدهسُ يمحو الواقعةَ قبل أن تُراجَع (W3-D-04) —
 *   بل **سجلٌّ متعاقبٌ بمفتاحِ (أصل × حائز × بدايةِ فترة)**، والمتزامنُ يُنقل
 *   موسومًا `W5_CONCURRENT_CLAIM_OPEN` بحصّتِه ونافذتِه، وحسمُه عند مالكِه
 *   (التمويل · W11) لا هنا.
 *
 * ◆ **ولماذا `asset_intake` كيانٌ لا حقلٌ في كرتِ الأصل**: `FLEET-03` يقول
 *   «هنا تبدأ دورةُ الأصل — قبل الكرتِ لا بعده». والمقيسُ أنَّ النظامَ يملك
 *   `equipments` (٢١٩ صفًّا · `card_state` بمفردتَين) ولا يملك صفًّا واحدًا
 *   لطلبِ الإدخالِ ولا لواقعةِ التحقُّقِ من المصدرِ ولا لأمرِ التفتيش. فالكرتُ
 *   يُنشَأ بلا سندٍ يسبقه، و«التحقُّقُ من المصدر» الذي يشترطه `FLEET-04`
 *   («لا يُنشأ كرتُ أصلٍ قبل اجتيازِ هذا التحقُّق») **بلا مكانٍ يقع فيه**.
 *
 * ◆ **ولماذا `asset_readiness` و`wf_coverage` مشتقّان بقاعدةٍ مكتوبةٍ في الصفّ**:
 *   `FLEET-20` «مشتقةٌ بالكامل — لا إدخال» و`WRK-03` «سطرُ فجوةٍ مشتقّ».
 *   والمقيسُ في `workforce_requirement`: **٢٠ صفًّا · ١٩ منها عجزُه يخالف
 *   حسابَه · و١٤ حالتُه تخالف حسابَها** — رقمٌ يُقرأ صحيحًا وهو خطأ. فالمشتقُّ
 *   يُكتب في سجلٍّ مستقلٍّ بقاعدتِه، والمُدخَلُ يبقى **مرآةً بفارقِها** دليلًا.
 *
 * ◆ **و`CHECK` لا قادِح**: مستخدمُ الهجراتِ لا يملك `SUPER` (‏W03 · 2027_11_19).
 *
 * ⛔ **ولا `CHECK` يجعل الحاجبَ أعمى**: ما يمنعه المخطَّطُ لا يُختبَر. فتزامنُ
 *   الحصصِ **تُرك ممكنًا في القاعدة** (ولا `CHECK` يقرأ صفًّا آخر أصلًا) ليُقاس
 *   في البوّابة؛ ومطابقةُ المشتقِّ لحسابِه تُعاد في البوّابةِ ولا تُقرأ من العمود.
 *
 * التشغيل: php database/migrations/2027_11_22_repair01_w5_asset_effect.php
 *          (⛔ لا `migrate.php up` — الهجرةُ بمسارِها الكاملِ منفردةً)
 * التراجع: php database/migrations/2027_11_22_repair01_w5_asset_effect_down.php
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

function w5_col(mysqli $c, $t, $col)
{
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

$done = 0; $err = 0;
$run = function ($sql, $label) use ($conn, &$done, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; return true; }
    echo "  ✘ $label — " . $conn->error . "\n"; $err++; return false;
};

echo "══ REPAIR01 · W05 — أثرُ الأصلِ والقوى ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ① دفاترُ المرحلة — النطاقُ والسايدبارُ والقراراتُ والرحلة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① دفاترُ المرحلة ───────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w5_scope` (
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
  COMMENT='REPAIR01 W05 - ربط متطلبات المرحلة بالسجل المعياري للشاشات'", 'repair01_w5_scope');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w5_sidebar` (
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
  COMMENT='REPAIR01 W05 - الخطوات السبع للسايدبار داخل نطاق المرحلة'", 'repair01_w5_sidebar');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w5_decisions` (
  `decision_id` VARCHAR(24)  NOT NULL,
  `question`    VARCHAR(400) NOT NULL DEFAULT '',
  `ruling`      VARCHAR(900) NOT NULL DEFAULT '',
  `rationale`   VARCHAR(900) NULL,
  `scope_rows`  INT          NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`decision_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W05 - قرارات المرحلة'", 'repair01_w5_decisions');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w5_journey` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id`          VARCHAR(40)  NOT NULL DEFAULT '',
  `station_no`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `station`         VARCHAR(160) NOT NULL DEFAULT '',
  `entity`          VARCHAR(40)  NOT NULL DEFAULT '',
  `consumer`        VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'المستهلك بالاسم لا كل المستهلكين',
  `expected`        VARCHAR(400) NOT NULL DEFAULT '',
  `measured`        VARCHAR(400) NOT NULL DEFAULT '',
  `business_effect` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'الاثر التجاري المقيس لا صف الحدث',
  `readiness_after` VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'الجاهزية المشتقة بعد المحطة',
  `passed`          TINYINT(1)   NOT NULL DEFAULT 0,
  `run_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_run` (`run_id`,`station_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W05 - رحلة الاصل: محطاتها واثر كل مستهلك'", 'repair01_w5_journey');

/* ═══════════════════════════════════════════════════════════════════════════
   ② دخولُ الأصل — الطلبُ ثمّ التحقُّقُ من المصدرِ ثمّ أمرُ التفتيش
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n② دخولُ الأصل ─────────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `asset_intake` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT          NOT NULL,
  `intake_no`      VARCHAR(40)  NOT NULL,
  `requested_dept` VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'الادارة الطالبة برمزها المعياري DEP-nn',
  `asset_kind`     VARCHAR(80)  NOT NULL DEFAULT '',
  `source_type`    ENUM('owned','financed','supplier_external','rented') NOT NULL DEFAULT 'owned',
  `supplier_id`    INT          NULL,
  `equipment_id`   INT          NULL COMMENT 'يملا عند اصدار كرت الاصل لا قبله',
  `state`          ENUM('draft','submitted','source_verified','inspection_ordered','inspected',
                        'card_issued','activated','rejected') NOT NULL DEFAULT 'draft',
  `state_rule`     VARCHAR(48)  NOT NULL DEFAULT '',
  `requested_by`   INT          NULL,
  `requested_at`   DATETIME     NULL,
  `decided_by`     INT          NULL,
  `decided_at`     DATETIME     NULL,
  `reject_reason`  VARCHAR(255) NOT NULL DEFAULT '',
  `source_ref`     VARCHAR(120) NOT NULL DEFAULT '',
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asset_intake` (`company_id`,`intake_no`),
  KEY `ix_ai_state` (`state`),
  KEY `ix_ai_equip` (`equipment_id`),
  CONSTRAINT `chk_ai_reject` CHECK (`state` <> 'rejected' OR `reject_reason` <> ''),
  CONSTRAINT `chk_ai_card`   CHECK (`state` NOT IN ('card_issued','activated') OR `equipment_id` IS NOT NULL),
  CONSTRAINT `chk_ai_rule`   CHECK (`state` = 'draft' OR `state_rule` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FLEET-03 طلب ادخال الاصل - هنا تبدا دورة الاصل قبل الكرت لا بعده'", 'asset_intake');

$run("
CREATE TABLE IF NOT EXISTS `asset_source_check` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT          NOT NULL,
  `intake_id`      INT UNSIGNED NOT NULL,
  `check_seq`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `doc_type`       VARCHAR(80)  NOT NULL DEFAULT '',
  `doc_ref`        VARCHAR(120) NOT NULL DEFAULT '',
  `owner_declared` VARCHAR(200) NOT NULL DEFAULT '',
  `owner_legal`    VARCHAR(200) NOT NULL DEFAULT '',
  `verify_result`  ENUM('passed','failed') NOT NULL,
  `verify_rule`    VARCHAR(48)  NOT NULL DEFAULT '',
  `fail_reason`    VARCHAR(255) NOT NULL DEFAULT '',
  `verified_by`    INT          NULL,
  `verified_at`    DATETIME     NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_source_check` (`intake_id`,`check_seq`),
  CONSTRAINT `fk_asc_intake`  FOREIGN KEY (`intake_id`) REFERENCES `asset_intake` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_asc_evid`   CHECK (`verify_result` <> 'passed' OR `doc_ref` <> ''),
  CONSTRAINT `chk_asc_fail`   CHECK (`verify_result` <> 'failed' OR `fail_reason` <> ''),
  CONSTRAINT `chk_asc_rule`   CHECK (`verify_rule` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FLEET-04 التحقق من المصدر - لا ينشا كرت اصل قبل اجتياز هذا التحقق'", 'asset_source_check');

$run("
CREATE TABLE IF NOT EXISTS `asset_inspection_order` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT          NOT NULL,
  `order_no`      VARCHAR(40)  NOT NULL,
  `intake_id`     INT UNSIGNED NULL,
  `equipment_id`  INT          NULL,
  `reason`        ENUM('intake','periodic','post_repair','pre_exit','incident') NOT NULL,
  `reason_rule`   VARCHAR(48)  NOT NULL DEFAULT '',
  `ordered_by`    INT          NULL,
  `ordered_at`    DATETIME     NULL,
  `due_date`      DATE         NULL,
  `state`         ENUM('issued','executed','cancelled') NOT NULL DEFAULT 'issued',
  `inspection_id` INT          NULL COMMENT 'بطاقة التفتيش في mnt_inspection',
  `result`        VARCHAR(60)  NOT NULL DEFAULT '',
  `cancel_reason` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_insp_order` (`company_id`,`order_no`),
  KEY `ix_aio_equip` (`equipment_id`),
  KEY `ix_aio_intake` (`intake_id`),
  CONSTRAINT `chk_aio_target` CHECK (`intake_id` IS NOT NULL OR `equipment_id` IS NOT NULL),
  CONSTRAINT `chk_aio_exec`   CHECK (`state` <> 'executed' OR `inspection_id` IS NOT NULL),
  CONSTRAINT `chk_aio_cancel` CHECK (`state` <> 'cancelled' OR `cancel_reason` <> ''),
  CONSTRAINT `chk_aio_rule`   CHECK (`reason_rule` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FLEET-05 امر التفتيش - التفتيش يبدا بامر لا بزيارة وله خمسة اسباب في دورة الاصل'", 'asset_inspection_order');

/* ═══════════════════════════════════════════════════════════════════════════
   ③ حقُّ الاستخدامِ التشغيليّ — متعاقبٌ لا متزامن
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n③ حقُّ الاستخدامِ التشغيليّ ─────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `asset_use_right` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`       INT          NOT NULL,
  `equipment_id`     INT          NOT NULL,
  `holder_kind`      ENUM('company','financier','supplier','client') NOT NULL DEFAULT 'company',
  `holder_key`       VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'مفتاح الحائز - نوعه ومرجعه معا',
  `holder_ref_id`    INT          NULL,
  `holder_name`      VARCHAR(200) NOT NULL DEFAULT '',
  `percent`          DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  `valid_from`       DATE         NOT NULL,
  `valid_to`         DATE         NULL,
  `doc_ref`          VARCHAR(120) NOT NULL DEFAULT '',
  `source_register`  VARCHAR(48)  NOT NULL DEFAULT '' COMMENT 'السجل الحي الذي قيس منه',
  `source_row_ref`   VARCHAR(60)  NOT NULL DEFAULT '',
  `concurrency_rule` VARCHAR(48)  NOT NULL DEFAULT '',
  `concurrency_pct`  DECIMAL(7,2) NOT NULL DEFAULT 0.00 COMMENT 'مجموع الحصص المتزامنة في نافذة البداية',
  `concurrency_note` VARCHAR(255) NOT NULL DEFAULT '',
  `granted_by`       INT          NULL,
  `granted_at`       DATETIME     NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_use_right` (`company_id`,`equipment_id`,`holder_key`,`valid_from`),
  KEY `ix_aur_equip` (`equipment_id`,`valid_from`),
  CONSTRAINT `chk_aur_pct`    CHECK (`percent` > 0 AND `percent` <= 100),
  CONSTRAINT `chk_aur_period` CHECK (`valid_to` IS NULL OR `valid_to` >= `valid_from`),
  CONSTRAINT `chk_aur_rule`   CHECK (`concurrency_rule` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FLEET-09 حق الاستخدام التشغيلي - الملكية متعاقبة لا متزامنة'", 'asset_use_right');

/* ═══════════════════════════════════════════════════════════════════════════
   ④ الإسنادُ والحركةُ والخروج
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n④ الإسنادُ والحركةُ والخروج ────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `asset_assignment` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT          NOT NULL,
  `equipment_id` INT          NOT NULL,
  `assign_kind`  ENUM('project','site','unit') NOT NULL DEFAULT 'site',
  `project_id`   INT          NULL,
  `site_id`      INT          NULL,
  `unit_ref`     VARCHAR(60)  NOT NULL DEFAULT '',
  `valid_from`   DATE         NOT NULL,
  `valid_to`     DATE         NULL,
  `state`        ENUM('active','ended') NOT NULL DEFAULT 'active',
  `assigned_by`  INT          NULL,
  `assigned_at`  DATETIME     NULL,
  `decision_ref` VARCHAR(120) NOT NULL DEFAULT '',
  `end_reason`   VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asset_assign` (`company_id`,`equipment_id`,`valid_from`),
  KEY `ix_aas_equip` (`equipment_id`,`state`),
  CONSTRAINT `chk_aas_target` CHECK (`project_id` IS NOT NULL OR `site_id` IS NOT NULL),
  CONSTRAINT `chk_aas_end`    CHECK (`state` <> 'ended' OR (`valid_to` IS NOT NULL AND `end_reason` <> '')),
  CONSTRAINT `chk_aas_period` CHECK (`valid_to` IS NULL OR `valid_to` >= `valid_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FLEET-12 و FLEET-13 اسناد الاصل لموقع او مشروع - لا ينشا اصل جديد عند الانتقال'", 'asset_assignment');

$run("
CREATE TABLE IF NOT EXISTS `asset_exit` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`      INT          NOT NULL,
  `equipment_id`    INT          NOT NULL,
  `exit_kind`       ENUM('temporary','permanent') NOT NULL,
  `reason_code`     VARCHAR(60)  NOT NULL DEFAULT '',
  `exit_date`       DATE         NOT NULL,
  `expected_return` DATE         NULL,
  `actual_return`   DATE         NULL,
  `disposal_kind`   VARCHAR(60)  NOT NULL DEFAULT '',
  `finance_ref`     VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'الاثر المالي مرجع من المالية - الاسطول يوثق الواقعة',
  `state`           ENUM('open','returned','closed') NOT NULL DEFAULT 'open',
  `decided_by`      INT          NULL,
  `decided_at`      DATETIME     NULL,
  `doc_ref`         VARCHAR(120) NOT NULL DEFAULT '',
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asset_exit` (`company_id`,`equipment_id`,`exit_kind`,`exit_date`),
  KEY `ix_aex_equip` (`equipment_id`,`state`),
  CONSTRAINT `chk_aex_reason` CHECK (`reason_code` <> ''),
  CONSTRAINT `chk_aex_temp`   CHECK (`exit_kind` <> 'temporary' OR `expected_return` IS NOT NULL),
  CONSTRAINT `chk_aex_perm`   CHECK (`exit_kind` <> 'permanent' OR (`finance_ref` <> '' AND `expected_return` IS NULL)),
  CONSTRAINT `chk_aex_ret`    CHECK (`state` <> 'returned' OR (`exit_kind` = 'temporary' AND `actual_return` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FLEET-21 و FLEET-22 الخروج المؤقت والدائم - والعودة تسجل هنا لا في كرت جديد'", 'asset_exit');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ المشتقّاتُ — الجاهزيّةُ والتغطية: تُشتقُّ ولا تُدخَل
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑤ المشتقّات — الجاهزيّةُ والتغطية ──────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `asset_readiness` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `company_id`      INT           NOT NULL,
  `equipment_id`    INT           NOT NULL,
  `period`          CHAR(7)       NOT NULL COMMENT 'YYYY-MM',
  `shift_hours`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `executed_hours`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `standby_hours`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `fault_hours`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `stop_hours`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `readiness_pct`   DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
  `utilization_pct` DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
  `lifecycle_state` VARCHAR(24)   NOT NULL DEFAULT '' COMMENT 'حالة الاصل المشتقة في اخر يوم من الفترة',
  `derivation_rule` VARCHAR(48)   NOT NULL DEFAULT '',
  `derived_from`    VARCHAR(255)  NOT NULL DEFAULT '' COMMENT 'السجلات المصدر بالاسم',
  `derived_at`      DATETIME      NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asset_readiness` (`company_id`,`equipment_id`,`period`),
  KEY `ix_ard_period` (`period`),
  CONSTRAINT `chk_ard_rule` CHECK (`derivation_rule` <> ''),
  CONSTRAINT `chk_ard_pct`  CHECK (`readiness_pct` >= 0 AND `readiness_pct` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FLEET-20 الجاهزية الشهرية - مشتقة بالكامل لا ادخال'", 'asset_readiness');

$run("
CREATE TABLE IF NOT EXISTS `wf_coverage` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`      INT          NOT NULL,
  `requirement_id`  INT          NOT NULL COMMENT 'صف workforce_requirement المشتق منه',
  `project_id`      INT          NULL,
  `worker_category` VARCHAR(120) NOT NULL DEFAULT '',
  `required_qty`    INT          NOT NULL DEFAULT 0,
  `available_qty`   INT          NOT NULL DEFAULT 0,
  `gap_qty`         INT          NOT NULL DEFAULT 0,
  `surplus_qty`     INT          NOT NULL DEFAULT 0,
  `coverage_state`  VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'SHORTAGE او BALANCED او SURPLUS - مشتق',
  `derivation_rule` VARCHAR(48)  NOT NULL DEFAULT '',
  `declared_state`  VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'المدخل حيا - مراة لا تدهس',
  `declared_gap`    INT          NOT NULL DEFAULT 0,
  `variance_rule`   VARCHAR(48)  NOT NULL DEFAULT '',
  `variance_note`   VARCHAR(255) NOT NULL DEFAULT '',
  `derived_at`      DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wf_coverage` (`company_id`,`requirement_id`),
  KEY `ix_wfc_state` (`coverage_state`),
  CONSTRAINT `chk_wfc_rule` CHECK (`derivation_rule` <> ''),
  CONSTRAINT `chk_wfc_var`  CHECK (`variance_rule` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WRK-03 المطلوب مقابل المتوفر - سطر فجوة مشتق والمدخل مراة بفارقها'", 'wf_coverage');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ كرتُ الأصل — حالةُ الدورةِ وقاعدتُها ووصلُ الطلبِ به
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑥ كرتُ الأصل ──────────────────────────────────────────────────\n";
$cols = array(
    'lifecycle_state' => "ADD COLUMN `lifecycle_state` VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'حالة الاصل في دورته - مشتقة لا مدخلة'",
    'lifecycle_rule'  => "ADD COLUMN `lifecycle_rule` VARCHAR(48) NOT NULL DEFAULT '' COMMENT 'قاعدة اشتقاق الحالة - لا قيمة بلا قاعدة'",
    'intake_id'       => "ADD COLUMN `intake_id` INT UNSIGNED NULL COMMENT 'طلب الادخال الذي انشا هذا الكرت'",
);
foreach ($cols as $col => $clause) {
    if (w5_col($conn, 'equipments', $col)) { echo "  ↷ equipments.$col قائمٌ سلفًا\n"; continue; }
    $run("ALTER TABLE `equipments` $clause", "equipments.$col");
}

echo "\n" . str_repeat('─', 70) . "\n";
printf("نُفِّذ %d · أخطاء %d\n", $done, $err);
echo 'الحكم: ' . ($err === 0 ? "تمّت ✔\n" : "فيها خطأ ✘\n");
exit($err === 0 ? 0 : 1);
