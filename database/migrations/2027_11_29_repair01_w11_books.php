<?php
/**
 * 2027_11_29_repair01_w11_books.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W11 — **دفاترُ الكياناتِ: الماليةُ والخزينة**
 *
 * ◆ **الحبّةُ `Legal Entity × Accounting Period`** (‏`DEC-OPEN-03` معتمد): كلُّ
 *   جدولٍ هنا يحمل `company_id` **غيرَ قابلٍ للعدم**، ولا إقفالَ ولا قيدَ ولا
 *   فاتورةَ ولا حسابَ بنكيَّ ولا ميزانيةَ ولا قائمةَ بلا كيانٍ قانونيّ.
 *
 * ◆ **وأيُّ رقمٍ يخلط كيانَين يُوسَم أو يُرفض**: `repair01_w11_consolidated`
 *   دفترُ الأرقامِ العابرةِ للكيانات — وكلُّ صفٍّ فيه يحمل وسمَه صراحةً،
 *   و`CHECK` يرفض صفًّا يجمع كيانَين بلا وسمٍ وبلا مالكٍ للقراءة.
 *
 * ◆ **و§48 يُثبَت بجدولٍ لا بنصّ**: `acc_recognition_request` — النطاقُ يصدر
 *   **طلبَ اعترافٍ** والماليّةُ تقرّر وتثبّت. و`CHECK` يمنع أن يكون مصدرُ
 *   الطلبِ الماليّةَ نفسَها: نطاقٌ يكتب قيدَه بيدِه ليس طلبَ اعترافٍ بل قيدًا.
 *
 * ◆ **وترتيبُ الدورةِ يُبنى بترتيبِه** (‏§23): تأسيسٌ ⇐ دفاترُ مساعدة ⇐ تسويات
 *   ⇐ مطابقات ⇐ ميزانُ مراجعة ⇐ قائمةُ إقفال ⇐ إقفالُ فترة ⇐ قوائمُ مالية.
 *   وكلُّ حلقةٍ لها جدولُها، ولا حلقةَ تُقفز.
 *
 * ◆ **و`CHECK` لا قادِح**: مستخدمُ الهجراتِ لا يملك `SUPER` (‏W03 · 2027_11_19).
 *
 * ⛔ **ولا عتبةٌ رقميّةٌ في الشيفرة** — كلُّها في `repair01_w11_thresholds`.
 * ⛔ **ولا يُمَسُّ عمودٌ حيٌّ بحذفٍ أو إعادةِ تعريف** — الإضافةُ وحدَها.
 *
 * التشغيل: php database/migrations/2027_11_29_repair01_w11_books.php
 *          (⛔ لا `migrate.php up` — الهجرةُ بمسارِها الكاملِ منفردةً)
 * التراجع: php database/migrations/2027_11_29_repair01_w11_books_down.php
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

function w11_tbl(mysqli $c, $t)
{
    $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}
function w11_col(mysqli $c, $t, $col)
{
    if (!w11_tbl($c, $t)) { return false; }
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

$done = 0; $err = 0; $skip = 0;
$run = function ($sql, $label) use ($conn, &$done, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; return true; }
    echo "  ✘ $label — " . $conn->error . "\n"; $err++; return false;
};
$addCol = function ($t, $col, $ddl) use ($conn, &$done, &$skip, &$err) {
    if (!w11_tbl($conn, $t)) { echo "  ⚠ $t غير موجود — $col يُتخطّى\n"; $skip++; return; }
    if (w11_col($conn, $t, $col)) { echo "  ↷ $t.$col قائم\n"; $skip++; return; }
    if ($conn->query("ALTER TABLE `$t` ADD COLUMN $ddl") === true) { echo "  ✔ $t.$col\n"; $done++; }
    else { echo "  ✘ $t.$col — " . $conn->error . "\n"; $err++; }
};

echo "══ REPAIR01 · W11 — دفاترُ الكياناتِ: الماليةُ والخزينة ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ① دفاترُ المرحلة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① دفاترُ المرحلة ───────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w11_scope` (
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
  `build_verdict`    VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'LIVE او BUILT_W11',
  `cycle_step`       TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'موضعه من ترتيب الدورة المحاسبية',
  `entity_scoped`    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'مقيس: الجدول يحمل company_id غير قابل للعدم',
  `map_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `map_why`          VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`          VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`requirement_id`),
  KEY `ix_screen` (`anchor_screen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W11 - ربط متطلبات المرحلة بالسجل المعياري'", 'repair01_w11_scope');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w11_sidebar` (
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
  `s4_cycle_step`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
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
  COMMENT='REPAIR01 W11 - الخطوات السبع للسايدبار داخل النطاق'", 'repair01_w11_sidebar');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w11_decisions` (
  `decision_id` VARCHAR(24)   NOT NULL,
  `question`    VARCHAR(400)  NOT NULL DEFAULT '',
  `ruling`      VARCHAR(900)  NOT NULL DEFAULT '',
  `rationale`   VARCHAR(1200) NULL,
  `scope_rows`  INT           NOT NULL DEFAULT 0,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`decision_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W11 - قرارات المرحلة'", 'repair01_w11_decisions');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w11_journey` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id`          VARCHAR(40)  NOT NULL DEFAULT '',
  `station_no`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `leg`             VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'شوط الرحلة',
  `station`         VARCHAR(190) NOT NULL DEFAULT '',
  `entity`          VARCHAR(48)  NOT NULL DEFAULT '',
  `consumer`        VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'المستهلك بالاسم لا كل المستهلكين',
  `expected`        VARCHAR(400) NOT NULL DEFAULT '',
  `measured`        VARCHAR(400) NOT NULL DEFAULT '',
  `business_effect` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'الاثر التجاري المقيس لا صف الحدث',
  `state_after`     VARCHAR(120) NOT NULL DEFAULT '',
  `company_id`      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'الكيان القانوني للمحطة',
  `passed`          TINYINT(1)   NOT NULL DEFAULT 0,
  `run_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_run` (`run_id`,`station_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W11 - رحلة الاقفال بمحطاتها واثر كل مستهلك'", 'repair01_w11_journey');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w11_states` (
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
  CONSTRAINT `chk_w11st_forbid` CHECK (`allowed` = 1 OR `forbid_reason` <> ''),
  CONSTRAINT `chk_w11st_allow`  CHECK (`allowed` = 0 OR (`owner_role` <> '' AND `precondition` <> ''
                                       AND `official_doc` <> '' AND `approval_gate` <> ''
                                       AND `reopen_rule` <> '' AND `correct_rule` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W11 - آلة حالة لكل كيان رئيسي'", 'repair01_w11_states');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w11_sod` (
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
  CONSTRAINT `chk_w11sod_full` CHECK (`initiator_role` <> '' AND `approver_role` <> ''
                                      AND `executor_role` <> '' AND `closer_role` <> ''
                                      AND `forbidden_combo` <> '' AND `authority_rule_id` <> ''
                                      AND `enforced_by` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W11 - فصل الواجبات بستة ادوار وتركيبة ممنوعة'", 'repair01_w11_sod');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w11_thresholds` (
  `threshold_key` VARCHAR(48)   NOT NULL,
  `value_num`     DECIMAL(16,4) NOT NULL DEFAULT 0,
  `unit_ar`       VARCHAR(40)   NOT NULL DEFAULT '',
  `title_ar`      VARCHAR(190)  NOT NULL DEFAULT '',
  `why`           VARCHAR(600)  NOT NULL DEFAULT '',
  `decision_ref`  VARCHAR(48)   NOT NULL DEFAULT '',
  `src_ref`       VARCHAR(190)  NOT NULL DEFAULT '',
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`threshold_key`),
  CONSTRAINT `chk_w11th_why` CHECK (`why` <> '' AND `decision_ref` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W11 - عتبات المرحلة: من السجل لا من الشيفرة'", 'repair01_w11_thresholds');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w11_fixes` (
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
  CONSTRAINT `chk_w11fx_rev` CHECK (`revealed_by` <> '' AND `reveal_why` <> ''
                                    AND `target` <> '' AND `kind` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W11 - كل اصلاح بمتطلبه الكاشف'", 'repair01_w11_fixes');

/* ⛔ **الرقمُ العابرُ للكياناتِ يُوسَم أو يُرفض** (‏§٤-٣ · `DEC-OPEN-03`).
      و`CHECK` يرفض صفًّا يجمع أكثرَ من كيانٍ بلا وسمٍ صريحٍ وبلا مالكِ قراءةٍ
      مكتوب: «مجمَّعٌ» بلا وسمٍ رقمٌ يبدو رقمَ كيانٍ وهو ليس كذلك. */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w11_consolidated` (
  `figure_key`   VARCHAR(80)  NOT NULL,
  `surface`      VARCHAR(200) NOT NULL DEFAULT '',
  `figure_name`  VARCHAR(190) NOT NULL DEFAULT '',
  `entity_count` INT          NOT NULL DEFAULT 1 COMMENT 'عدد الكيانات الداخلة في الرقم',
  `tag`          VARCHAR(48)  NOT NULL DEFAULT '' COMMENT 'SINGLE_ENTITY او GROUP_PROJECTION',
  `tag_label_ar` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'الوسم المعروض للمستخدم',
  `read_owner`   VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'من يملك قراءة الرقم المجمع',
  `why`          VARCHAR(600) NOT NULL DEFAULT '',
  `src_ref`      VARCHAR(190) NOT NULL DEFAULT '',
  PRIMARY KEY (`figure_key`),
  CONSTRAINT `chk_w11cons_tag` CHECK (`entity_count` <= 1
        OR (`tag` = 'GROUP_PROJECTION' AND `tag_label_ar` <> '' AND `read_owner` <> '' AND `why` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W11 - الرقم العابر للكيانات يوسم او يرفض'", 'repair01_w11_consolidated');

/* ═══════════════════════════════════════════════════════════════════════════
   ② §48 — طلبُ الاعترافِ: النطاقُ يطلب والماليّةُ تقرّر وتثبّت
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n② طلبُ الاعترافِ — لا نطاقَ يكتب قيدًا ────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `acc_recognition_request` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`       INT UNSIGNED NOT NULL,
  `request_no`       VARCHAR(40)  NOT NULL DEFAULT '',
  `source_module`    VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'النطاق المصدري - لا يكون finance',
  `source_screen`    VARCHAR(200) NOT NULL DEFAULT '',
  `source_ref`       VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'الواقعة في نطاقها',
  `source_doc_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `event_type`       VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'revenue او expense او payable او receivable',
  `amount`           DECIMAL(18,2) NOT NULL DEFAULT 0,
  `currency`         VARCHAR(8)   NOT NULL DEFAULT '',
  `fx_rate`          DECIMAL(20,8) NOT NULL DEFAULT 1,
  `base_amount`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `period_code`      VARCHAR(16)  NOT NULL DEFAULT '',
  `requested_by`     INT UNSIGNED NOT NULL DEFAULT 0,
  `requested_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finance_decision` VARCHAR(16)  NOT NULL DEFAULT 'pending' COMMENT 'pending او accepted او rejected',
  `decided_by`       INT UNSIGNED NOT NULL DEFAULT 0,
  `decided_at`       DATETIME     NULL,
  `decision_reason`  VARCHAR(500) NOT NULL DEFAULT '',
  `event_id`         INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'الواقعة المالية التي انشاتها المالية',
  `journal_entry_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `idem_key`         VARCHAR(96)  NOT NULL DEFAULT '',
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_recreq_no` (`company_id`,`request_no`),
  UNIQUE KEY `uq_recreq_idem` (`idem_key`),
  KEY `ix_recreq_state` (`company_id`,`finance_decision`),
  CONSTRAINT `chk_recreq_src`  CHECK (`source_module` <> '' AND `source_module` <> 'finance'
                                      AND `source_ref` <> ''),
  CONSTRAINT `chk_recreq_why`  CHECK (`finance_decision` <> 'rejected' OR `decision_reason` <> ''),
  CONSTRAINT `chk_recreq_amt`  CHECK (`amount` > 0 AND `currency` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W11 - طلب اعتراف من نطاق مصدري والمالية تقرر'", 'acc_recognition_request');

/* ═══════════════════════════════════════════════════════════════════════════
   ③ الدفاترُ المساعدة — الذممُ المدينةُ والدائنةُ ببنودها
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n③ الدفاترُ المساعدة ──────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `acc_invoice_line` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `invoice_id`  BIGINT UNSIGNED NOT NULL COMMENT 'ar_claim_invoices',
  `line_no`     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `description` VARCHAR(300) NOT NULL DEFAULT '',
  `qty`         DECIMAL(16,4) NOT NULL DEFAULT 0,
  `unit_price`  DECIMAL(16,4) NOT NULL DEFAULT 0,
  `subtotal`    DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'الكمية في السعر - مشتق',
  `tax_code`    VARCHAR(16)  NOT NULL DEFAULT '',
  `tax_rate`    DECIMAL(8,4) NOT NULL DEFAULT 0,
  `tax_amount`  DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `line_total`  DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invl` (`invoice_id`,`line_no`),
  KEY `ix_invl` (`company_id`,`invoice_id`),
  CONSTRAINT `chk_invl_desc` CHECK (`description` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ACC-08 بند × فاتورة - Invoice Lines Child Register'", 'acc_invoice_line');

/* ⛔ **لا بندَ استحقاقٍ بلا مرجعٍ في بوّابتِه** (`ACC-10` نصًّا): سطرُ المطابقةِ
      الثلاثيّةِ أو بندُ الإقفالِ التعاقديّ — والفراغُ يرفضه `CHECK`. */
$run("
CREATE TABLE IF NOT EXISTS `acc_supplier_accrual_line` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `due_id`      INT UNSIGNED NOT NULL COMMENT 'fin_dues',
  `line_no`     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `gate_kind`   VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'three_way_match او contract_closure',
  `gate_ref`    VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'مرجع البند في بوابته',
  `description` VARCHAR(300) NOT NULL DEFAULT '',
  `amount`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_accl` (`due_id`,`line_no`),
  KEY `ix_accl` (`company_id`,`due_id`),
  CONSTRAINT `chk_accl_gate` CHECK (`gate_kind` <> '' AND `gate_ref` <> '' AND `description` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ACC-10 بند × استحقاق - كل بند بمرجعه في بوابته'", 'acc_supplier_accrual_line');

$run("
CREATE TABLE IF NOT EXISTS `acc_credit_limit` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`         INT UNSIGNED NOT NULL,
  `customer_entity_id` INT UNSIGNED NOT NULL,
  `limit_amount`       DECIMAL(18,2) NOT NULL DEFAULT 0,
  `currency`           VARCHAR(8)   NOT NULL DEFAULT '',
  `exposure_amount`    DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'مشتق من الذمم القائمة - لا يكتب بيد',
  `breach_action`      VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'block او escalate',
  `authority_rule_id`  VARCHAR(60)  NOT NULL DEFAULT '',
  `approved_by`        INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_at`        DATETIME     NULL,
  `is_active`          TINYINT(1)   NOT NULL DEFAULT 1,
  `why`                VARCHAR(500) NOT NULL DEFAULT '',
  `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_credit` (`company_id`,`customer_entity_id`),
  CONSTRAINT `chk_credit_rule` CHECK (`breach_action` <> '' AND `authority_rule_id` <> '' AND `why` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ACC-15 عميل × حد ائتماني - التجاوز يحجب او يصعد بقاعدة'", 'acc_credit_limit');

/* ═══════════════════════════════════════════════════════════════════════════
   ④ التسوياتُ والمطابقاتُ وميزانُ المراجعة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n④ التسوياتُ والمطابقاتُ والميزان ─────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `acc_period_adjustment` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`       INT UNSIGNED NOT NULL,
  `period_id`        INT UNSIGNED NOT NULL,
  `adj_no`           VARCHAR(40)  NOT NULL DEFAULT '',
  `adj_kind`         VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'accrual او prepaid او provision',
  `account_code`     VARCHAR(30)  NOT NULL DEFAULT '',
  `amount`           DECIMAL(18,2) NOT NULL DEFAULT 0,
  `currency`         VARCHAR(8)   NOT NULL DEFAULT '',
  `base_amount`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `basis_doc`        VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'مستند الاساس',
  `reverse_next`     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'يعكس في الفترة التالية',
  `state`            VARCHAR(16)  NOT NULL DEFAULT 'draft' COMMENT 'draft او posted او reversed',
  `prepared_by`      INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_by`      INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_at`      DATETIME     NULL,
  `reversed_at`      DATETIME     NULL,
  `event_id`         INT UNSIGNED NOT NULL DEFAULT 0,
  `journal_entry_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `why`              VARCHAR(500) NOT NULL DEFAULT '',
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adj_no` (`company_id`,`adj_no`),
  KEY `ix_adj_period` (`company_id`,`period_id`,`adj_kind`),
  CONSTRAINT `chk_adj_kind`  CHECK (`adj_kind` <> '' AND `account_code` <> ''),
  CONSTRAINT `chk_adj_basis` CHECK (`basis_doc` <> '' AND `why` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ACC-17 قيد تسوية × فترة - استحقاق ومقدم ومخصص'", 'acc_period_adjustment');

$run("
CREATE TABLE IF NOT EXISTS `acc_account_recon` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `period_id`      INT UNSIGNED NOT NULL,
  `account_code`   VARCHAR(30)  NOT NULL DEFAULT '',
  `control_source` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'المصدر التفصيلي الذي يطابق',
  `gl_balance`     DECIMAL(18,2) NOT NULL DEFAULT 0,
  `source_balance` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `difference`     DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'مشتق - لا يكتب بيد',
  `open_diffs`     INT          NOT NULL DEFAULT 0 COMMENT 'مشتق من البنود المفتوحة',
  `state`          VARCHAR(16)  NOT NULL DEFAULT 'open' COMMENT 'open او reviewed او closed',
  `prepared_by`    INT UNSIGNED NOT NULL DEFAULT 0,
  `reviewed_by`    INT UNSIGNED NOT NULL DEFAULT 0,
  `closed_by`      INT UNSIGNED NOT NULL DEFAULT 0,
  `closed_at`      DATETIME     NULL,
  `note`           VARCHAR(400) NOT NULL DEFAULT '',
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_recon` (`company_id`,`period_id`,`account_code`),
  CONSTRAINT `chk_recon_src` CHECK (`account_code` <> '' AND `control_source` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ACC-20 حساب رقابي × فترة - جلسة مطابقة'", 'acc_account_recon');

/* ⛔ **ولا فرقَ مدفونٌ في حقل** — كلُّ فرقٍ سطرٌ بنوعِه وسببِه ومسؤولِه وإجرائه. */
$run("
CREATE TABLE IF NOT EXISTS `acc_account_recon_line` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`       INT UNSIGNED NOT NULL,
  `recon_id`         INT UNSIGNED NOT NULL,
  `line_kind`        VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'timing او error او missing او other',
  `cause`            VARCHAR(400) NOT NULL DEFAULT '',
  `amount`           DECIMAL(18,2) NOT NULL DEFAULT 0,
  `responsible_role` VARCHAR(120) NOT NULL DEFAULT '',
  `action_taken`     VARCHAR(400) NOT NULL DEFAULT '',
  `state`            VARCHAR(16)  NOT NULL DEFAULT 'open' COMMENT 'open او resolved',
  `resolved_by`      INT UNSIGNED NOT NULL DEFAULT 0,
  `resolved_at`      DATETIME     NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_reconl` (`company_id`,`recon_id`,`state`),
  CONSTRAINT `fk_reconl` FOREIGN KEY (`recon_id`) REFERENCES `acc_account_recon` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_reconl_full` CHECK (`line_kind` <> '' AND `cause` <> ''
                                      AND `responsible_role` <> '' AND `action_taken` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ACC-20 بند فرق × جلسة مطابقة - Differences Child'", 'acc_account_recon_line');

/* ◆ **الميزانُ مشتقٌّ كليًّا من القيودِ المنشورة** ولا يُعدَّل فيه شيء:
     فالجولةُ **لقطةٌ بزمنِها** لا سجلٌّ يُحرَّر، وتوازنُها شرطُ الإقفال. */
$run("
CREATE TABLE IF NOT EXISTS `acc_trial_balance_run` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `period_id`    INT UNSIGNED NOT NULL,
  `run_ref`      VARCHAR(48)  NOT NULL DEFAULT '',
  `total_debit`  DECIMAL(20,2) NOT NULL DEFAULT 0,
  `total_credit` DECIMAL(20,2) NOT NULL DEFAULT 0,
  `balanced`     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'مشتق - مدين يساوي دائن',
  `line_count`   INT          NOT NULL DEFAULT 0,
  `entry_count`  INT          NOT NULL DEFAULT 0,
  `run_by`       INT UNSIGNED NOT NULL DEFAULT 0,
  `run_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `note`         VARCHAR(300) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tb_run` (`company_id`,`period_id`,`run_ref`),
  KEY `ix_tb` (`company_id`,`period_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ACC-21 حساب × فترة - جولة ميزان مراجعة مشتقة'", 'acc_trial_balance_run');

$run("
CREATE TABLE IF NOT EXISTS `acc_trial_balance_line` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `run_id`       INT UNSIGNED NOT NULL,
  `account_code` VARCHAR(30)  NOT NULL DEFAULT '',
  `account_name` VARCHAR(190) NOT NULL DEFAULT '',
  `debit`        DECIMAL(20,2) NOT NULL DEFAULT 0,
  `credit`       DECIMAL(20,2) NOT NULL DEFAULT 0,
  `balance`      DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'مشتق',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tbl` (`run_id`,`account_code`),
  KEY `ix_tbl` (`company_id`,`run_id`),
  CONSTRAINT `fk_tbl_run` FOREIGN KEY (`run_id`) REFERENCES `acc_trial_balance_run` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ACC-21 سطر ميزان مشتق من القيود المنشورة'", 'acc_trial_balance_line');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ الإقفالُ وحوكمةُ إعادةِ الفتح
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑤ الإقفالُ وإعادةُ الفتح ─────────────────────────────────────\n";

/* ◆ **بندُ القائمةِ الناقصُ يُوثَّق استثناؤه بقرار** (`ACC-22` نصًّا) — فالأعمدةُ
     تُضاف إلى الجدولِ الحيِّ ولا يُبنى جدولٌ موازٍ يشقُّ مصدرَ الحقيقة. */
$addCol('fin_closing_items', 'exception_reason',
    "`exception_reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'توثيق استثناء البند الناقص'");
$addCol('fin_closing_items', 'exception_by',
    "`exception_by` INT UNSIGNED NOT NULL DEFAULT 0");
$addCol('fin_closing_items', 'exception_at',
    "`exception_at` DATETIME NULL");
$addCol('fin_closing_items', 'blocks_close',
    "`blocks_close` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'البند يحجب الاقفال ما لم يوثق استثناؤه'");

$run("
CREATE TABLE IF NOT EXISTS `acc_period_reopen_request` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`        INT UNSIGNED NOT NULL,
  `period_id`         INT UNSIGNED NOT NULL,
  `request_no`        VARCHAR(40)  NOT NULL DEFAULT '',
  `justification`     VARCHAR(600) NOT NULL DEFAULT '',
  `scope_from`        DATE         NULL,
  `scope_to`          DATE         NULL,
  `scope_units`       VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'الوحدات المحددة لا كل النظام',
  `authority_rule_id` VARCHAR(60)  NOT NULL DEFAULT '',
  `requested_by`      INT UNSIGNED NOT NULL DEFAULT 0,
  `requested_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `state`             VARCHAR(16)  NOT NULL DEFAULT 'pending'
                      COMMENT 'pending او approved او rejected او applied او reclosed',
  `approved_by`       INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_at`       DATETIME     NULL,
  `applied_at`        DATETIME     NULL,
  `reclosed_at`       DATETIME     NULL,
  `reject_reason`     VARCHAR(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reopen_no` (`company_id`,`request_no`),
  KEY `ix_reopen` (`company_id`,`period_id`,`state`),
  CONSTRAINT `chk_reopen_full` CHECK (`justification` <> '' AND `authority_rule_id` <> ''
                                      AND `scope_units` <> ''),
  CONSTRAINT `chk_reopen_rej`  CHECK (`state` <> 'rejected' OR `reject_reason` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ACC-25 طلب اعادة فتح × فترة - استثناء محكوم'", 'acc_period_reopen_request');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ الخزينة — الأوعيةُ والأدواتُ والحركةُ والتحويلُ والصرفُ الأجنبيّ
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑥ أوعيةُ الخزينةِ وحركتُها ───────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `tre_cash_box` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`      INT UNSIGNED NOT NULL,
  `code`            VARCHAR(40)  NOT NULL DEFAULT '',
  `name`            VARCHAR(160) NOT NULL DEFAULT '',
  `currency`        VARCHAR(8)   NOT NULL DEFAULT '',
  `custodian_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `site_id`         INT UNSIGNED NOT NULL DEFAULT 0,
  `opening_balance` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`      INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_box` (`company_id`,`code`),
  CONSTRAINT `chk_box_name` CHECK (`name` <> '' AND `currency` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='TRS-02 صندوق نقدي - وعاء الخزينة غير البنك'", 'tre_cash_box');

/* ⛔ **فرقُ الصرفِ حركةٌ مستقلّةٌ لا تعديلٌ صامت** (`TRS-10` نصًّا) — والعمودُ
     `is_fx_diff` يميّزها، وكلُّ حركةٍ بمرجعِها الموثَّق. */
$run("
CREATE TABLE IF NOT EXISTS `tre_cash_move` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `move_no`     VARCHAR(40)  NOT NULL DEFAULT '',
  `vessel_kind` VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'bank او cash_box',
  `vessel_id`   INT UNSIGNED NOT NULL DEFAULT 0,
  `direction`   VARCHAR(8)   NOT NULL DEFAULT '' COMMENT 'in او out',
  `amount`      DECIMAL(18,2) NOT NULL DEFAULT 0,
  `currency`    VARCHAR(8)   NOT NULL DEFAULT '',
  `fx_rate`     DECIMAL(20,8) NOT NULL DEFAULT 1,
  `base_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `ref_kind`    VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'مرجع الحركة - سند او امر او تحويل',
  `ref_id`      INT UNSIGNED NOT NULL DEFAULT 0,
  `is_fx_diff`  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'فرق صرف حركة مستقلة لا تعديل صامت',
  `moved_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `moved_by`    INT UNSIGNED NOT NULL DEFAULT 0,
  `note`        VARCHAR(300) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_move_no` (`company_id`,`move_no`),
  KEY `ix_move` (`company_id`,`vessel_kind`,`vessel_id`,`moved_at`),
  CONSTRAINT `chk_move_dir` CHECK (`direction` IN ('in','out') AND `vessel_kind` IN ('bank','cash_box')),
  CONSTRAINT `chk_move_ref` CHECK (`ref_kind` <> '' AND `currency` <> '' AND `amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='TRS-10 حركة × وعاء - سطر حركة موثق بمرجعه'", 'tre_cash_move');

/* ◆ **التحويلُ بين أوعيةِ الشركةِ ليس دفعًا لمستفيد** (`TRS-11` نصًّا): مسارٌ
     أخفُّ بقاعدتِه — وبتوقيعِ مفوَّض. و`CHECK` يمنع وعاءً يحوّل إلى نفسِه. */
$run("
CREATE TABLE IF NOT EXISTS `tre_transfer` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`        INT UNSIGNED NOT NULL,
  `transfer_no`       VARCHAR(40)  NOT NULL DEFAULT '',
  `from_kind`         VARCHAR(16)  NOT NULL DEFAULT '',
  `from_id`           INT UNSIGNED NOT NULL DEFAULT 0,
  `to_kind`           VARCHAR(16)  NOT NULL DEFAULT '',
  `to_id`             INT UNSIGNED NOT NULL DEFAULT 0,
  `amount`            DECIMAL(18,2) NOT NULL DEFAULT 0,
  `currency`          VARCHAR(8)   NOT NULL DEFAULT '',
  `state`             VARCHAR(16)  NOT NULL DEFAULT 'draft' COMMENT 'draft او executed او cancelled',
  `authority_rule_id` VARCHAR(60)  NOT NULL DEFAULT '',
  `signed_by`         INT UNSIGNED NOT NULL DEFAULT 0,
  `executed_by`       INT UNSIGNED NOT NULL DEFAULT 0,
  `executed_at`       DATETIME     NULL,
  `out_move_id`       INT UNSIGNED NOT NULL DEFAULT 0,
  `in_move_id`        INT UNSIGNED NOT NULL DEFAULT 0,
  `why`               VARCHAR(400) NOT NULL DEFAULT '',
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trf_no` (`company_id`,`transfer_no`),
  KEY `ix_trf` (`company_id`,`state`),
  CONSTRAINT `chk_trf_self` CHECK (NOT (`from_kind` = `to_kind` AND `from_id` = `to_id`)),
  CONSTRAINT `chk_trf_full` CHECK (`authority_rule_id` <> '' AND `why` <> '' AND `amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='TRS-11 تحويل × وعاءين - امر تحويل واحد'", 'tre_transfer');

$run("
CREATE TABLE IF NOT EXISTS `tre_fx_deal` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `deal_no`       VARCHAR(40)  NOT NULL DEFAULT '',
  `deal_kind`     VARCHAR(8)   NOT NULL DEFAULT '' COMMENT 'buy او sell',
  `sell_currency` VARCHAR(8)   NOT NULL DEFAULT '',
  `buy_currency`  VARCHAR(8)   NOT NULL DEFAULT '',
  `sell_amount`   DECIMAL(18,2) NOT NULL DEFAULT 0,
  `buy_amount`    DECIMAL(18,2) NOT NULL DEFAULT 0,
  `deal_rate`     DECIMAL(20,8) NOT NULL DEFAULT 0 COMMENT 'سعر الصفقة الموثق',
  `table_rate`    DECIMAL(20,8) NOT NULL DEFAULT 0 COMMENT 'سعر الجدول لحظة الصفقة - للمقارنة لا للاحلال',
  `rate_gap`      DECIMAL(20,8) NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `counterparty`  VARCHAR(160) NOT NULL DEFAULT '',
  `doc_ref`       VARCHAR(120) NOT NULL DEFAULT '',
  `dealt_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dealt_by`      INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fx_no` (`company_id`,`deal_no`),
  CONSTRAINT `chk_fx_cur` CHECK (`sell_currency` <> '' AND `buy_currency` <> ''
                                 AND `sell_currency` <> `buy_currency`),
  CONSTRAINT `chk_fx_doc` CHECK (`doc_ref` <> '' AND `deal_rate` > 0 AND `deal_kind` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='TRS-12 صفقة صرف × عملتين - بسعر الصفقة الموثق'", 'tre_fx_deal');

$run("
CREATE TABLE IF NOT EXISTS `tre_instrument` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `instrument_no` VARCHAR(40)  NOT NULL DEFAULT '',
  `kind`          VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'cheque_in او cheque_out او promissory',
  `party_type`    VARCHAR(16)  NOT NULL DEFAULT '',
  `party_ref`     INT UNSIGNED NOT NULL DEFAULT 0,
  `bank_name`     VARCHAR(120) NOT NULL DEFAULT '',
  `due_date`      DATE         NULL,
  `amount`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `currency`      VARCHAR(8)   NOT NULL DEFAULT '',
  `state`         VARCHAR(16)  NOT NULL DEFAULT 'received'
                  COMMENT 'received او deposited او collected او bounced او returned او handed',
  `payment_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `bounce_reason` VARCHAR(400) NOT NULL DEFAULT '',
  `created_by`    INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_instr_no` (`company_id`,`instrument_no`),
  KEY `ix_instr` (`company_id`,`state`,`due_date`),
  CONSTRAINT `chk_instr_kind`   CHECK (`kind` <> '' AND `currency` <> '' AND `amount` > 0),
  CONSTRAINT `chk_instr_bounce` CHECK (`state` <> 'bounced' OR `bounce_reason` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='TRS-06 اداة مالية × حالة - بدورتها من الاستلام الى التحصيل'", 'tre_instrument');

$run("
CREATE TABLE IF NOT EXISTS `tre_guarantee` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`        INT UNSIGNED NOT NULL,
  `doc_no`            VARCHAR(40)  NOT NULL DEFAULT '',
  `doc_kind`          VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'bank_guarantee او letter_of_credit',
  `beneficiary`       VARCHAR(190) NOT NULL DEFAULT '',
  `facility_id`       INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'الاصدار على تسهيله',
  `amount`            DECIMAL(18,2) NOT NULL DEFAULT 0,
  `currency`          VARCHAR(8)   NOT NULL DEFAULT '',
  `issued_at`         DATE         NULL,
  `expires_at`        DATE         NULL,
  `state`             VARCHAR(16)  NOT NULL DEFAULT 'requested'
                      COMMENT 'requested او issued او extended او released او called',
  `authority_rule_id` VARCHAR(60)  NOT NULL DEFAULT '',
  `release_ref`       VARCHAR(120) NOT NULL DEFAULT '',
  `created_by`        INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grt_no` (`company_id`,`doc_no`),
  KEY `ix_grt` (`company_id`,`state`,`expires_at`),
  CONSTRAINT `chk_grt_fac`  CHECK (`facility_id` > 0 AND `authority_rule_id` <> ''),
  CONSTRAINT `chk_grt_ben`  CHECK (`beneficiary` <> '' AND `doc_kind` <> '' AND `amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='TRS-15 خطاب او اعتماد × مستفيد - الاصدار على تسهيله'", 'tre_guarantee');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑦ الرقابةُ والإقفالُ عند الخزينة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑦ رقابةُ الخزينةِ وإقفالُها ──────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `tre_recon_difference` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`       INT UNSIGNED NOT NULL,
  `statement_id`     INT UNSIGNED NOT NULL COMMENT 'جلسة المطابقة البنكية',
  `match_id`         INT UNSIGNED NOT NULL DEFAULT 0,
  `diff_kind`        VARCHAR(24)  NOT NULL DEFAULT ''
                     COMMENT 'timing او bank_error او book_error او missing_entry او fx',
  `cause`            VARCHAR(400) NOT NULL DEFAULT '',
  `amount`           DECIMAL(18,2) NOT NULL DEFAULT 0,
  `responsible_role` VARCHAR(120) NOT NULL DEFAULT '',
  `action_taken`     VARCHAR(400) NOT NULL DEFAULT '',
  `state`            VARCHAR(16)  NOT NULL DEFAULT 'open' COMMENT 'open او resolved',
  `opened_by`        INT UNSIGNED NOT NULL DEFAULT 0,
  `opened_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_by`      INT UNSIGNED NOT NULL DEFAULT 0,
  `resolved_at`      DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `ix_trd` (`company_id`,`statement_id`,`state`),
  CONSTRAINT `chk_trd_full` CHECK (`diff_kind` <> '' AND `cause` <> ''
                                   AND `responsible_role` <> '' AND `action_taken` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='TRS-16 فرق × جلسة مطابقة - Differences Child'", 'tre_recon_difference');

$run("
CREATE TABLE IF NOT EXISTS `tre_petty_custody` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `custody_no`     VARCHAR(40)  NOT NULL DEFAULT '',
  `holder_id`      INT UNSIGNED NOT NULL DEFAULT 0,
  `ceiling_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `currency`       VARCHAR(8)   NOT NULL DEFAULT '',
  `opened_at`      DATE         NULL,
  `due_date`       DATE         NULL COMMENT 'السقف الزمني للعهدة',
  `spent_amount`   DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'مشتق من البنود المقبولة',
  `settled_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `state`          VARCHAR(16)  NOT NULL DEFAULT 'open' COMMENT 'open او settled او closed',
  `settled_at`     DATETIME     NULL,
  `note`           VARCHAR(300) NOT NULL DEFAULT '',
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pc_no` (`company_id`,`custody_no`),
  KEY `ix_pc` (`company_id`,`holder_id`,`state`),
  CONSTRAINT `chk_pc_cap` CHECK (`ceiling_amount` > 0 AND `currency` <> '' AND `holder_id` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='TRS-17 عهدة نثرية × امين - دورة عهدة بحد وسقف زمني'", 'tre_petty_custody');

$run("
CREATE TABLE IF NOT EXISTS `tre_petty_expense` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `custody_id`    INT UNSIGNED NOT NULL,
  `expense_date`  DATE         NULL,
  `description`   VARCHAR(300) NOT NULL DEFAULT '',
  `amount`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `doc_ref`       VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'مستند المصروف - لا تسوية بلا مستند',
  `state`         VARCHAR(16)  NOT NULL DEFAULT 'claimed' COMMENT 'claimed او accepted او rejected',
  `reject_reason` VARCHAR(400) NOT NULL DEFAULT '',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_pe` (`company_id`,`custody_id`,`state`),
  CONSTRAINT `fk_pe_custody` FOREIGN KEY (`custody_id`) REFERENCES `tre_petty_custody` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_pe_doc` CHECK (`doc_ref` <> '' AND `description` <> '' AND `amount` > 0),
  CONSTRAINT `chk_pe_rej` CHECK (`state` <> 'rejected' OR `reject_reason` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='TRS-17 بند مصروف × عهدة - لا تسوية بلا مستند'", 'tre_petty_expense');

/* ⛔ **الجردُ بلجنةٍ لا بأمينِ الصندوقِ وحدَه** (`TRS-18` نصًّا) — و`CHECK`
     يرفض جلسةً بأقلَّ من عضوَين: «لجنةٌ» من واحدٍ ليست لجنةً بل توقيعَ نفسِه. */
$run("
CREATE TABLE IF NOT EXISTS `tre_cash_count` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`      INT UNSIGNED NOT NULL,
  `count_no`        VARCHAR(40)  NOT NULL DEFAULT '',
  `box_id`          INT UNSIGNED NOT NULL DEFAULT 0,
  `count_kind`      VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'periodic او surprise',
  `counted_at`      DATETIME     NULL,
  `book_balance`    DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'مشتق من الحركات - لا يكتب بيد',
  `counted_balance` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `difference`      DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'مشتق',
  `committee_size`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `counted_by`      INT UNSIGNED NOT NULL DEFAULT 0,
  `state`           VARCHAR(16)  NOT NULL DEFAULT 'draft' COMMENT 'draft او reviewed او approved',
  `approved_by`     INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_at`     DATETIME     NULL,
  `action_ref`      VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'معالجة الفرق فورا بمساره',
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cc_no` (`company_id`,`count_no`),
  KEY `ix_cc` (`company_id`,`box_id`,`state`),
  CONSTRAINT `chk_cc_committee` CHECK (`committee_size` >= 2),
  CONSTRAINT `chk_cc_kind`      CHECK (`count_kind` <> '' AND `box_id` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='TRS-18 جلسة جرد × صندوق - بلجنة لا بامين وحده'", 'tre_cash_count');

$run("
CREATE TABLE IF NOT EXISTS `tre_cash_count_line` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `count_id`     INT UNSIGNED NOT NULL,
  `denomination` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `qty`          INT          NOT NULL DEFAULT 0,
  `amount`       DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'مشتق',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ccl` (`count_id`,`denomination`),
  KEY `ix_ccl` (`company_id`,`count_id`),
  CONSTRAINT `fk_ccl_count` FOREIGN KEY (`count_id`) REFERENCES `tre_cash_count` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='TRS-18 سطر فئة نقدية × جلسة جرد'", 'tre_cash_count_line');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑧ أعمدةٌ تُضاف إلى جداولَ حيّةٍ — الإضافةُ وحدَها
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑧ أعمدةٌ على الجداولِ الحيّة ─────────────────────────────────\n";
$addCol('fin_payments', 'recognition_request_id',
    "`recognition_request_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'طلب الاعتراف الذي انشا الاستحقاق'");
$addCol('fin_journal_entries', 'recognition_request_id',
    "`recognition_request_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'طلب الاعتراف الذي انتج القيد'");
$addCol('fin_journal_entries', 'entity_scope',
    "`entity_scope` VARCHAR(24) NOT NULL DEFAULT 'SINGLE_ENTITY' COMMENT 'SINGLE_ENTITY او GROUP_PROJECTION'");
$addCol('bank_statements', 'diff_count',
    "`diff_count` INT NOT NULL DEFAULT 0 COMMENT 'مشتق من بنود الفروق المفتوحة'");
$addCol('tre_beneficiaries', 'locked_at',
    "`locked_at` DATETIME NULL COMMENT 'الحساب البنكي يقفل ضد التعديل بعد التحقق'");
$addCol('tre_beneficiaries', 'verify_doc_ref',
    "`verify_doc_ref` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'مصدر توثيق الحساب البنكي'");

/* ═══════════════════════════════════════════════════════════════════════════
   ⑨ تسجيلُ مستهلكي أحداثِ النطاق — النشرُ بلا مستهلكٍ مرفوضٌ في الجذر
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑨ مستهلكو أحداثِ النطاق ─────────────────────────────────────\n";
/* ⚠ **الفاصلُ يُكتب مرّةً واحدةً في نصِّ PHP** — و`real_escape_string` يضاعفه
     في نصِّ الاستعلامِ فتخزّن القاعدةُ فاصلًا مفردًا. وكتابتُه مضاعفًا هنا
     تخزّنه مضاعفًا، فيصير الصنفُ اسمًا لا يُحمَّل — والمقيسُ طولُ الحقل. */
$ACC = 'App\\Services\\Finance\\AccountingCycleService';
$TRE = 'App\\Services\\Treasury\\TreasuryCycleService';
$CONS = array(
    array('acc.recognition.requested', $ACC, 'onRecognitionRequested', 'write'),
    array('acc.recognition.decided',   $ACC, 'onRecognitionDecided',   'write'),
    array('acc.entry.posted',          $ACC, 'onEntryPosted',          'write'),
    array('acc.adjustment.posted',     $ACC, 'onAdjustmentPosted',     'write'),
    array('acc.account.reconciled',    $ACC, 'onAccountReconciled',    'write'),
    array('acc.trial.balanced',        $ACC, 'onTrialBalanced',        'write'),
    array('acc.period.closed',         $ACC, 'onPeriodClosed',         'write'),
    array('acc.period.reopened',       $ACC, 'onPeriodReopened',       'write'),
    array('acc.statements.issued',     $ACC, 'onStatementsIssued',     'dashboard_refresh'),
    array('tre.receipt.allocated',     $TRE, 'onReceiptAllocated',     'write'),
    array('tre.payment.executed',      $TRE, 'onPaymentExecuted',      'write'),
    array('tre.bank.reconciled',       $TRE, 'onBankReconciled',       'write'),
    array('tre.count.approved',        $TRE, 'onCashCountApproved',    'write'),
);
foreach ($CONS as $c) {
    $key = 'w11_' . str_replace('.', '_', $c[0]);
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
