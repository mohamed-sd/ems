<?php
/**
 * 2027_12_07_repair01_w16_baseline.php — دفاترُ المرحلةِ السادسةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * **البندُ ٥٦**: «لا يصدر `Enterprise Target Baseline` إلّا بعد أن تنجح الثمانية»
 * — والثمانيةُ تُسجَّل صفوفًا بمقاييسِها لا فقراتٍ في وثيقة.
 *
 * **والبندُ ٦٤**: «لا تقبل نسبةً واحدة» — تسعةُ مقاماتٍ لكلِّ نطاق، **ولكلِّ
 * مقياسٍ مقامُه المطبوعُ بجانبِه**؛ فصفٌّ بلا مقامٍ يُسجَّل `NOT_MEASURED`
 * ⛔ **ولا يُسجَّل صفرًا** — «صفرٌ من مقامٍ مجهولٍ لا يُثبت شيئًا».
 *
 * **والبندُ ٦٣**: القبولُ البشريُّ **برحلةِ موظّفٍ حقيقيّ**. فـ`chk_w16_uat_real`
 * **يمنع في القاعدة** أن تُعلَن محطّةٌ ناجحةً بلا فاعلٍ حقيقيٍّ وزمنٍ ودليل —
 * ⛔ **فلا يُخضِرُّ سكربتٌ ولا بذرةُ بيانات**.
 *
 * **والبندُ ٥٠**: المراجعةُ الثانيةُ **بمحرّكٍ مغاير** يجب أن يستطيع إصدارَ
 * `REDESIGN` والاختباراتُ خضراء — فـ`severity` تحمل القيمةَ، و`chk_w16_ch_evidence`
 * يمنع دعوى بلا مصدرٍ أوّليٍّ تُقرأ منه.
 *
 * التشغيل: php database/migrations/2027_12_07_repair01_w16_baseline.php
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

$tables = array();

/* ① الثمانيةُ الطبقاتُ المنصوصةُ في البندِ ٥٦ ──────────────────────────── */
$tables['repair01_w16_layers'] = "CREATE TABLE `repair01_w16_layers` (
  `layer_no`      TINYINT UNSIGNED NOT NULL,
  `layer_key`     VARCHAR(40)  NOT NULL,
  `layer_name_ar` VARCHAR(160) NOT NULL,
  `clause_ref`    VARCHAR(48)  NOT NULL DEFAULT '',
  `measure_sql`   TEXT         NOT NULL COMMENT 'يعيد عمودين: num و den',
  `den_name`      VARCHAR(120) NOT NULL COMMENT 'اسم المقام مطبوعا بجانب الرقم',
  `measured_num`  INT          NOT NULL DEFAULT -1,
  `measured_den`  INT          NOT NULL DEFAULT -1,
  `verdict`       ENUM('PASS','FAIL','NOT_MEASURED') NOT NULL DEFAULT 'NOT_MEASURED',
  `why`           VARCHAR(500) NOT NULL DEFAULT '',
  `measured_at`   DATETIME     NULL,
  PRIMARY KEY (`layer_key`),
  UNIQUE KEY `ux_w16_layer_no` (`layer_no`),
  /* طبقةٌ بلا استعلامِ قياسٍ ولا اسمِ مقامٍ لا تُثبت نجاحَها */
  CONSTRAINT `chk_w16_layer_measure` CHECK (`measure_sql` <> '' AND `den_name` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* ② المحاورُ التسعةُ — البندُ ٦٤ ───────────────────────────────────────── */
$tables['repair01_w16_axes'] = "CREATE TABLE `repair01_w16_axes` (
  `axis_key`     VARCHAR(24)  NOT NULL,
  `axis_no`      TINYINT UNSIGNED NOT NULL,
  `axis_name_ar` VARCHAR(80)  NOT NULL,
  `num_rule`     VARCHAR(400) NOT NULL COMMENT 'ما يعد في البسط نصا',
  `den_rule`     VARCHAR(400) NOT NULL COMMENT 'ما يعد في المقام نصا',
  `instrument`   VARCHAR(600) NOT NULL DEFAULT '' COMMENT 'حد الاداة معلنا: ماذا لا تقيس',
  PRIMARY KEY (`axis_key`),
  UNIQUE KEY `ux_w16_axis_no` (`axis_no`),
  CONSTRAINT `chk_w16_axis_rules` CHECK (`num_rule` <> '' AND `den_rule` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* ③ لوحةُ المقامات — نطاقٌ × محور ─────────────────────────────────────── */
$tables['repair01_w16_scorecard'] = "CREATE TABLE `repair01_w16_scorecard` (
  `domain_code` VARCHAR(12)  NOT NULL,
  `axis_key`    VARCHAR(24)  NOT NULL,
  `num`         INT          NOT NULL DEFAULT -1,
  `den`         INT          NOT NULL DEFAULT -1,
  `den_name`    VARCHAR(120) NOT NULL DEFAULT '',
  `verdict`     ENUM('MEASURED','NOT_MEASURED') NOT NULL DEFAULT 'NOT_MEASURED',
  `note`        VARCHAR(400) NOT NULL DEFAULT '',
  `measured_at` DATETIME     NULL,
  PRIMARY KEY (`domain_code`, `axis_key`),
  KEY `ix_w16_sc_axis` (`axis_key`),
  /* صفر من مقام مجهول لا يثبت شيئا: المقيس يلزمه مقام موجب واسمه.
     ⚠ وكانت den >= 0 فقبلت صفا مقيسا بمقام خاو 0 من 0 - وهو عين ما ينهى عنه
       نص القيد نفسه. كشفه الفحص السلبي في W16 · والقيد الذي يقبل ما يمنعه نصه زينة. */
  CONSTRAINT `chk_w16_sc_den` CHECK (
      (`verdict` = 'MEASURED'     AND `den` > 0 AND `num` >= 0 AND `num` <= `den` AND `den_name` <> '')
   OR (`verdict` = 'NOT_MEASURED' AND `num` = -1 AND `den` = -1 AND `note` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* ④ المراجعةُ الثانيةُ المستقلّة — البندُ ٥٠ ───────────────────────────── */
$tables['repair01_w16_challenge'] = "CREATE TABLE `repair01_w16_challenge` (
  `finding_id`     VARCHAR(16)  NOT NULL,
  `rule_key`       VARCHAR(48)  NOT NULL,
  `title`          VARCHAR(300) NOT NULL,
  `severity`       ENUM('ACCEPT','CONCERN','REDESIGN') NOT NULL,
  `subject`        VARCHAR(255) NOT NULL DEFAULT '',
  `measured`       VARCHAR(255) NOT NULL DEFAULT '',
  `expected`       VARCHAR(255) NOT NULL DEFAULT '',
  `primary_source` VARCHAR(160) NOT NULL COMMENT 'القرص او المخطط او المصنف المجمد - لا دفتر موجة',
  `evidence`       VARCHAR(500) NOT NULL,
  `raised_at`      DATETIME     NOT NULL,
  PRIMARY KEY (`finding_id`),
  KEY `ix_w16_ch_sev` (`severity`),
  /* دعوى بلا مصدر اولي تقرا منه ليست مراجعة مستقلة */
  CONSTRAINT `chk_w16_ch_evidence` CHECK (`primary_source` <> '' AND `evidence` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* ⑤ رحلةُ الموظّفِ الحقيقيّ — البندُ ٦٣ ────────────────────────────────── */
$tables['repair01_w16_uat'] = "CREATE TABLE `repair01_w16_uat` (
  `station_id`      VARCHAR(24)  NOT NULL,
  `journey_key`     VARCHAR(48)  NOT NULL,
  `station_no`      SMALLINT UNSIGNED NOT NULL,
  `station_ar`      VARCHAR(255) NOT NULL,
  `domain_code`     VARCHAR(12)  NOT NULL DEFAULT '',
  `required_role`   VARCHAR(120) NOT NULL,
  `person_slot`     VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'شخص ١ او ٢ او ٣ حيث يلزم فصل الواجبات',
  `is_negative`     TINYINT(1)   NOT NULL DEFAULT 0,
  `actor_user_id`   INT          NOT NULL DEFAULT 0 COMMENT 'مستخدم حقيقي - لا حساب سكربت',
  `actor_name`      VARCHAR(160) NOT NULL DEFAULT '',
  `acted_at`        DATETIME     NULL,
  `evidence_ref`    VARCHAR(300) NOT NULL DEFAULT '',
  `attempt_log_ref` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'قيد المحاولة الممنوعة في سجل المحاولات',
  `status`          ENUM('PENDING','PASSED','FAILED') NOT NULL DEFAULT 'PENDING',
  PRIMARY KEY (`station_id`),
  KEY `ix_w16_uat_journey` (`journey_key`, `station_no`),
  /* لا يعلن اخضر ببذور بيانات ولا بسكربت: الناجح يلزمه فاعل وزمن ودليل */
  CONSTRAINT `chk_w16_uat_real` CHECK (
      `status` <> 'PASSED'
   OR (`actor_user_id` > 0 AND `acted_at` IS NOT NULL AND `evidence_ref` <> '' AND `actor_name` <> '')),
  /* والمسار السالب لا يقبل بلا قيد في سجل المحاولات */
  CONSTRAINT `chk_w16_uat_negative` CHECK (
      `is_negative` = 0 OR `status` <> 'PASSED' OR `attempt_log_ref` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* ⑥ الدَّينُ الموروثُ DC-13 — سبعةٌ وخمسون تبويبًا تُحكم فرادى ────────── */
$tables['repair01_w16_tabs'] = "CREATE TABLE `repair01_w16_tabs` (
  `screen_file`    VARCHAR(160) NOT NULL,
  `dept_code`      VARCHAR(12)  NOT NULL,
  `parent_file`    VARCHAR(160) NOT NULL DEFAULT '',
  `judged_verdict` VARCHAR(32)  NOT NULL COMMENT 'حكم الاداة المقيس من المنح الحي',
  `disposition`    ENUM('KEEP_ITEM','MERGE_INTO_PARENT','PARENT_RAISED','GRANT_GAP_TO_OWNER','RETIRE') NOT NULL,
  `roles_seeing`   VARCHAR(200) NOT NULL DEFAULT '',
  `why`            VARCHAR(600) NOT NULL,
  `decided_by`     VARCHAR(48)  NOT NULL DEFAULT '',
  `decided_at`     DATETIME     NOT NULL,
  `owner_ref`      VARCHAR(48)  NOT NULL DEFAULT '' COMMENT 'معرف التاجيل ان كان القرار للمالك',
  PRIMARY KEY (`screen_file`, `dept_code`),
  KEY `ix_w16_tab_disp` (`disposition`),
  /* لا حكم بلا سبب مكتوب - والحكم للمالك يلزمه معرف تاجيله */
  CONSTRAINT `chk_w16_tab_why` CHECK (
      `why` <> '' AND (`disposition` <> 'GRANT_GAP_TO_OWNER' OR `owner_ref` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* ⑦ سجلُّ الإصدار ──────────────────────────────────────────────────────── */
$tables['repair01_w16_baseline'] = "CREATE TABLE `repair01_w16_baseline` (
  `baseline_id`       VARCHAR(48)  NOT NULL,
  /* ⚠ كانت 24 حرفا فبترت ENTERPRISE-TARGET-BASELINE-v1.0 وهي احد وثلاثون
     - والبتر وقع بتحذير لا بخطا، فقرئ الاسم صحيحا وهو خطا · كشفه W16 */
  `version`           VARCHAR(64)  NOT NULL,
  `state`             ENUM('DRAFT','ISSUED_AWAITING_OWNER','OWNER_APPROVED','REDESIGN') NOT NULL,
  `snapshot_id`       VARCHAR(48)  NOT NULL,
  `commit_hash`       VARCHAR(40)  NOT NULL,
  `layers_pass`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `layers_total`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `challenge_verdict` ENUM('ACCEPT','CONCERN','REDESIGN') NOT NULL,
  `redesign_count`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `concern_count`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `owner_ref`         VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'مرجع ختم المالك - فارغ حتى يختم',
  `issued_at`         DATETIME     NOT NULL,
  `why`               VARCHAR(700) NOT NULL,
  PRIMARY KEY (`baseline_id`),
  /* الاعتماد قرار مالك لا نتيجة اداة: لا OWNER_APPROVED بلا مرجع ختمه */
  CONSTRAINT `chk_w16_bl_owner` CHECK (`state` <> 'OWNER_APPROVED' OR `owner_ref` <> ''),
  CONSTRAINT `chk_w16_bl_fields` CHECK (`snapshot_id` <> '' AND `commit_hash` <> '' AND `why` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* ⑧ الدفاترُ الثلاثةُ المعتادة ────────────────────────────────────────── */
$tables['repair01_w16_decisions'] = "CREATE TABLE `repair01_w16_decisions` (
  `decision_id` VARCHAR(16)  NOT NULL,
  `question`    VARCHAR(400) NOT NULL,
  `answer`      VARCHAR(400) NOT NULL,
  `rationale`   VARCHAR(900) NOT NULL,
  `scope_rows`  INT          NOT NULL DEFAULT 0,
  `decided_at`  DATE         NOT NULL,
  `src_ref`     VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`decision_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['repair01_w16_deferred'] = "CREATE TABLE `repair01_w16_deferred` (
  `deferred_id`  VARCHAR(24)  NOT NULL,
  `question`     VARCHAR(500) NOT NULL,
  `why_needed`   VARCHAR(500) NOT NULL,
  `blocked_what` VARCHAR(400) NOT NULL,
  `built_anyway` VARCHAR(600) NOT NULL,
  `kind`         VARCHAR(24)  NOT NULL,
  `raised_at`    DATE         NOT NULL,
  `src_ref`      VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`deferred_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['repair01_w16_fixes'] = "CREATE TABLE `repair01_w16_fixes` (
  `fix_id`   VARCHAR(16)  NOT NULL,
  `title`    VARCHAR(300) NOT NULL,
  `found_by` VARCHAR(160) NOT NULL,
  `what`     VARCHAR(700) NOT NULL,
  `evidence` VARCHAR(400) NOT NULL,
  `fixed_at` DATE         NOT NULL,
  PRIMARY KEY (`fix_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$made = 0; $had = 0;
foreach ($tables as $name => $ddl) {
    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($name) . "'");
    if ($r && $r->num_rows > 0) { $had++; echo "  ◆ قائمٌ سلفًا: $name\n"; continue; }
    if (!$conn->query($ddl)) { exit("✘ تعذّر إنشاءُ $name: {$conn->error}\n"); }
    $made++; echo "  ✔ أُنشئ: $name\n";
}

/* ═══════════════════════════════════════════════════════════════════════════
   عمودٌ أضيقُ من قيمتِه يبتر بتحذيرٍ لا بخطأ
   ═══════════════════════════════════════════════════════════════════════════
   `version` كان `VARCHAR(24)` و`ENTERPRISE-TARGET-BASELINE-v1.0` أحدٌ وثلاثون
   حرفًا — **فبُترت صامتةً**، وصار السجلُّ يحمل اسمًا ليس اسمَ الإصدار،
   **وكلُّ استعلامٍ يطابق الاسمَ الكاملَ لا يجد شيئًا**. */
$r = $conn->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'repair01_w16_baseline'
                      AND COLUMN_NAME = 'version'");
$vlen = ($r && $r->num_rows) ? (int) $r->fetch_row()[0] : 0;
if ($vlen > 0 && $vlen < 64) {
    $ok = $conn->query("ALTER TABLE `repair01_w16_baseline` MODIFY `version` VARCHAR(64) NOT NULL");
    if (!$ok) { exit("✘ تعذّر توسيعُ version: {$conn->error}\n"); }
    /* والصفوفُ المبتورةُ تُحذف لا تُرقَّع — والإصدارُ يُعاد بأداتِه */
    $conn->query("DELETE FROM repair01_w16_baseline
                   WHERE state <> 'OWNER_APPROVED' AND version <> 'ENTERPRISE-TARGET-BASELINE-v1.0'");
    echo "  ✔ وُسِّع العمود: repair01_w16_baseline.version (‏$vlen ⇐ 64) وحُذف المبتور\n";
} elseif ($vlen > 0) {
    echo "  ◆ العمودُ كافٍ سلفًا: repair01_w16_baseline.version\n";
}

/* ═══════════════════════════════════════════════════════════════════════════
   إصلاحُ قيدٍ يقبل ما يمنعه نصُّه — كشفَه الفحصُ السلبيّ
   ═══════════════════════════════════════════════════════════════════════════
   `chk_w16_sc_den` كُتب `den >= 0` فقبِل صفًّا **مقيسًا بمقامٍ خاوٍ** (‏0 من 0)،
   وهو عينُ ما يمنعه نصُّه. **والقيدُ الذي يقبل ما يمنعه نصُّه زينةٌ لا حزام.**
   ⇒ يُعاد بناؤه `den > 0` على الجدولِ القائمِ أيضًا، بلا مسِّ صفٍّ واحد. */
$r = $conn->query("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'repair01_w16_scorecard'
                      AND CONSTRAINT_NAME = 'chk_w16_sc_den'");
$cc = ($r && $r->num_rows) ? (string) $r->fetch_row()[0] : '';
if ($cc !== '' && strpos($cc, '`den` >= 0') !== false) {
    $conn->query("ALTER TABLE `repair01_w16_scorecard` DROP CONSTRAINT `chk_w16_sc_den`");
    $ok = $conn->query("ALTER TABLE `repair01_w16_scorecard` ADD CONSTRAINT `chk_w16_sc_den` CHECK (
        (`verdict` = 'MEASURED'     AND `den` > 0 AND `num` >= 0 AND `num` <= `den` AND `den_name` <> '')
     OR (`verdict` = 'NOT_MEASURED' AND `num` = -1 AND `den` = -1 AND `note` <> ''))");
    if (!$ok) { exit("✘ تعذّر إصلاحُ chk_w16_sc_den: {$conn->error}\n"); }
    echo "  ✔ أُصلح القيد: chk_w16_sc_den (‏den > 0 بدل den >= 0)\n";
} elseif ($cc !== '') {
    echo "  ◆ القيدُ سليمٌ سلفًا: chk_w16_sc_den\n";
}

/* ═══════════════════════════════════════════════════════════════════════════
   مقياسُ صنفِ الدَّينِ صورتان — والسجلُّ كان يعرف واحدةً
   ═══════════════════════════════════════════════════════════════════════════
   `DC-18` مقياسُه **مسحُ كودٍ لا استعلامُ قاعدة** (‏أهليّةُ صندوقِ الفلترةِ
   تُقاس من الملفّات)، فبقي `measure_sql` خاليًا **وصفرُه لا يُثبت شيئًا** —
   والجردُ الحاجبُ لم يكن يسأل «أثمَّ صنفٌ بلا مقياس؟» أصلًا.
   ⇒ فالعمودُ يُضاف **ليحمل المقياسَ الثاني**، لا لتُخفَّف قاعدة. */
$colExists = false;
$r = $conn->query("SHOW COLUMNS FROM `repair01_debt_register` LIKE 'measure_tool'");
if ($r && $r->num_rows > 0) { $colExists = true; }
if (!$colExists) {
    $ok = $conn->query("ALTER TABLE `repair01_debt_register`
        ADD COLUMN `measure_tool` VARCHAR(200) NOT NULL DEFAULT ''
        COMMENT 'امر مسح كود يطبع عددا واحدا - لصنف مقياسه ليس استعلاما' AFTER `measure_sql`");
    if (!$ok) { exit("✘ تعذّر إضافةُ measure_tool: {$conn->error}\n"); }
    echo "  ✔ أُضيف العمود: repair01_debt_register.measure_tool\n";
} else {
    echo "  ◆ قائمٌ سلفًا: repair01_debt_register.measure_tool\n";
}

echo "\n✔ دفاترُ W16: أُنشئ $made · قائمٌ $had\n";
