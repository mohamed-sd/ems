<?php
/**
 * 2027_11_28_repair01_w9_tracking_policy.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W09-RESUME — **سياسةُ تتبّعِ الصنفِ بجوابِ المالك** (`DEC-OPEN-15`)
 *
 * ◆ **الجوابُ غيَّر الشكلَ لا المحتوى**: بُني في W09 **عَلَمٌ ثنائيٌّ** على مستوى
 *   الفئة (`proc_item.track_lot` = `0/1`)، والمالكُ يطلب **ثلاثيًّا**
 *   (`OFF / OPTIONAL / REQUIRED`) على **مستويَين** (فئةٌ افتراضًا ثمَّ صنفٌ
 *   تخصيصًا) بـ**ثماني خصائص** و**نسخٍ مؤرَّخةٍ بلا أثرٍ رجعيّ**. فهذه هجرةُ
 *   إعادةِ تشكيلٍ لا هجرةُ بذر.
 *
 * ◆ **والقاعدةُ الحاكمة**: «النظامُ يدعم كلَّ مستوياتِ التتبّعِ من البداية،
 *   لكنَّ تفعيلَها وإلزاميّتَها قابلانِ للضبطِ حسب الفئةِ والصنفِ ومرحلةِ
 *   نضجِ البيانات». وثلاثُ كلماتٍ تلخّصها:
 *   **Capability Rich · Configuration Flexible · Operationally Non Blocking**.
 *
 * ◆ **والاختياريُّ لا يمنع أبدًا**: نقصُ البياناتِ يُسجَّل في `proc_track_gap`
 *   **قيدَ جودةٍ لا حاجبَ عمل**. و`CHECK` يمنع رفعَ خاصيّةٍ إلى `REQUIRED`
 *   بلا سببٍ مكتوبٍ ومرجعِ قرار — فالتشديدُ قرارٌ لا سهو.
 *
 * ◆ **ولا أثرَ رجعيّ**: لكلِّ سياسةٍ `version` و`effective_from` و`effective_to`
 *   و`changed_by` و`approved_by`. والحركةُ تُحاسَب بالسياسةِ **السارية لحظتَها**
 *   لا بالسارية اليوم.
 *
 * ◆ **والموروثُ لا يُخترَع له تتبّع**: `LEGACY_UNTRACKED` حالةُ رصيدٍ مستقلّةٌ
 *   تتعايش مع `SERIALIZED` للصنفِ نفسِه.
 *
 * ◆ **و`CHECK` لا قادِح**: مستخدمُ الهجراتِ لا يملك `SUPER` (‏W03 · 2027_11_19).
 *
 * ⛔ **ولا فئةَ مبذورةٌ هنا** — البذرُ في `tools/repair01_w9_resume.php` من
 *   ملفِّ جوابِ المالكِ وحدَه.
 *
 * التشغيل: php database/migrations/2027_11_28_repair01_w9_tracking_policy.php
 * التراجع: php database/migrations/2027_11_28_repair01_w9_tracking_policy_down.php
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

function tp_tbl(mysqli $c, $t)
{
    $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}
function tp_col(mysqli $c, $t, $col)
{
    if (!tp_tbl($c, $t)) { return false; }
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

$done = 0; $err = 0; $skip = 0;
$run = function ($sql, $label) use ($conn, &$done, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; return true; }
    echo "  ✘ $label — " . $conn->error . "\n"; $err++; return false;
};
$addCol = function ($t, $col, $ddl) use ($conn, &$done, &$skip, &$err) {
    if (!tp_tbl($conn, $t)) { echo "  ⚠ $t غير موجود — $col يُتخطّى\n"; $skip++; return; }
    if (tp_col($conn, $t, $col)) { echo "  ↷ $t.$col قائم\n"; $skip++; return; }
    if ($conn->query("ALTER TABLE `$t` ADD COLUMN $ddl") === true) { echo "  ✔ $t.$col\n"; $done++; }
    else { echo "  ✘ $t.$col — " . $conn->error . "\n"; $err++; }
};

echo "══ REPAIR01 · W09-RESUME — سياسةُ تتبّعِ الصنف (DEC-OPEN-15) ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ① سجلُّ السياسة — مستويانِ ونسخٌ مؤرَّخة
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **`scope_kind` يفصل الافتراضَ من التخصيص**: `CATEGORY` يعطي افتراضَ الفئة،
     و`ITEM` يخصّصه لصنفٍ بعينه — و**التخصيصُ يغلب الافتراضَ** عند الحلّ.
   ◆ **والفريدُ على (النطاق · المفتاح · النسخة)** فتتعايش النسخُ ولا تتصادم. */
$run("
CREATE TABLE IF NOT EXISTS `proc_track_policy` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  /* ◆ **دلالةُ الكتالوج**: `NULL` صفٌّ عامٌّ لكلِّ الكيانات، والرقمُ صفُّ شركةٍ
       بعينها. و`T_CATALOG` في `TenantRegistry` يقرأ «العامَّ أو المِلكيَّ» معًا —
       و`T_TENANT` كان يحجب العامَّ فيعود الحلُّ `NONE` على سياسةٍ مبذورة. */
  `company_id`     INT UNSIGNED NULL DEFAULT NULL COMMENT 'العدم يعني كل الكيانات',
  `scope_kind`     ENUM('CATEGORY','ITEM') NOT NULL COMMENT 'الفئة تعطي افتراضا والصنف يخصص',
  `scope_key`      VARCHAR(160) NOT NULL COMMENT 'اسم الفئة او معرف الصنف نصا',
  `version`        INT UNSIGNED NOT NULL DEFAULT 1,
  `effective_from` DATE         NOT NULL,
  `effective_to`   DATE         NULL COMMENT 'العدم يعني سارية الى الان',

  `lot`            ENUM('OFF','OPTIONAL','REQUIRED') NOT NULL DEFAULT 'OFF',
  `serial`         ENUM('OFF','OPTIONAL','REQUIRED') NOT NULL DEFAULT 'OFF',
  `mfg_date`       ENUM('OFF','OPTIONAL','REQUIRED') NOT NULL DEFAULT 'OFF',
  `expiry`         ENUM('OFF','OPTIONAL','REQUIRED') NOT NULL DEFAULT 'OFF',
  `warranty`       ENUM('OFF','OPTIONAL','REQUIRED') NOT NULL DEFAULT 'OFF',
  `expiry_enforce` ENUM('WARNING','APPROVAL_REQUIRED','HARD_BLOCK') NOT NULL DEFAULT 'WARNING',
  `issue_policy`   ENUM('FIFO','FEFO','MANUAL') NOT NULL DEFAULT 'FIFO',
  `requalify`      ENUM('ENABLED','DISABLED') NOT NULL DEFAULT 'DISABLED',

  `override_authority` VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'سلطة تجاوز الصلاحية - دور لا اسم',
  `why`            VARCHAR(600) NOT NULL DEFAULT '',
  `strict_why`     VARCHAR(600) NOT NULL DEFAULT '' COMMENT 'سبب رفع خاصية الى الزامي',
  `decision_ref`   VARCHAR(48)  NOT NULL DEFAULT '',
  `changed_by`     INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_by`    INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scope_ver` (`company_id`,`scope_kind`,`scope_key`,`version`),
  KEY `ix_live` (`scope_kind`,`scope_key`,`effective_from`),
  /* ⛔ **الإلزامُ قرارٌ لا سهو**: خاصيّةٌ واحدةٌ `REQUIRED` توجب سببًا مكتوبًا */
  CONSTRAINT `chk_tp_strict` CHECK (
      (`lot` <> 'REQUIRED' AND `serial` <> 'REQUIRED' AND `mfg_date` <> 'REQUIRED'
       AND `expiry` <> 'REQUIRED' AND `warranty` <> 'REQUIRED')
      OR `strict_why` <> ''),
  /* ⛔ **وتجاوزُ الصلاحيةِ بسلطةٍ لا بأحد**: الاعتمادُ يوجب دورًا مسمًّى */
  CONSTRAINT `chk_tp_auth` CHECK (`expiry_enforce` <> 'APPROVAL_REQUIRED' OR `override_authority` <> ''),
  /* ⛔ **وسياسةُ الصرفِ بالصلاحيةِ توجب تتبّعَها** */
  CONSTRAINT `chk_tp_fefo` CHECK (`issue_policy` <> 'FEFO' OR `expiry` <> 'OFF'),
  CONSTRAINT `chk_tp_why`  CHECK (`why` <> '' AND `decision_ref` <> '' AND `scope_key` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='DEC-OPEN-15 سياسة تتبع بمستويين وثماني خصائص ونسخ مؤرخة'", 'proc_track_policy');

/* ═══════════════════════════════════════════════════════════════════════════
   ② سجلُّ الدفعة
   ═══════════════════════════════════════════════════════════════════════════ */
$run("
CREATE TABLE IF NOT EXISTS `proc_lot` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `lot_no`        VARCHAR(60)  NOT NULL,
  `item_id`       INT UNSIGNED NOT NULL,
  `supplier_id`   INT UNSIGNED NOT NULL DEFAULT 0,
  `order_id`      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'امر الشراء',
  `receipt_id`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'سند الادخال',
  `qty_received`  DECIMAL(16,3) NOT NULL DEFAULT 0,
  `qty_available` DECIMAL(16,3) NOT NULL DEFAULT 0 COMMENT 'مشتق من الحركات',
  `mfg_date`      DATE         NULL COMMENT 'مستقل عن الصلاحية - قد يوجد احدهما',
  `expiry_date`   DATE         NULL,
  `warehouse_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `bin`           VARCHAR(60)  NOT NULL DEFAULT '',
  `quality_state` VARCHAR(24)  NOT NULL DEFAULT 'GOOD',
  `policy_version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'نسخة السياسة السارية لحظة الانشاء',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lot` (`company_id`,`item_id`,`lot_no`),
  KEY `ix_lot_exp` (`company_id`,`expiry_date`),
  CONSTRAINT `chk_lot_no` CHECK (`lot_no` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='DEC-OPEN-15 سجل الدفعة - الحقول الالزامية بحسب سياسة الصنف وحدها'", 'proc_lot');

/* ═══════════════════════════════════════════════════════════════════════════
   ③ سجلُّ الرقمِ التسلسليّ
   ═══════════════════════════════════════════════════════════════════════════ */
$run("
CREATE TABLE IF NOT EXISTS `proc_serial` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `serial_no`      VARCHAR(80)  NOT NULL,
  `item_id`        INT UNSIGNED NOT NULL,
  `lot_id`         INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'صنف ثم دفعة ثم ارقام - مدعوم لا مفروض',
  `supplier_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `order_id`       INT UNSIGNED NOT NULL DEFAULT 0,
  `receipt_id`     INT UNSIGNED NOT NULL DEFAULT 0,
  `warranty_until` DATE         NULL,
  `warranty_ref`   VARCHAR(190) NOT NULL DEFAULT '',
  `warehouse_id`   INT UNSIGNED NOT NULL DEFAULT 0,
  `custodian_id`   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'حائز العهدة',
  `asset_id`       INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'الاصل المركب عليه',
  `installed_at`   DATE         NULL,
  `removed_at`     DATE         NULL,
  `repair_count`   INT          NOT NULL DEFAULT 0 COMMENT 'مشتق من سجل الاصلاح',
  `state`          VARCHAR(24)  NOT NULL DEFAULT 'IN_STOCK',
  `disposed_at`    DATE         NULL,
  `policy_version` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_serial` (`company_id`,`item_id`,`serial_no`),
  KEY `ix_ser_asset` (`company_id`,`asset_id`),
  CONSTRAINT `chk_ser_no` CHECK (`serial_no` <> ''),
  /* ⛔ **الفكُّ لا يسبق التركيب** */
  CONSTRAINT `chk_ser_dates` CHECK (`removed_at` IS NULL OR `installed_at` IS NULL
                                    OR `removed_at` >= `installed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='DEC-OPEN-15 سجل الرقم التسلسلي بدورة حياته'", 'proc_serial');

/* ═══════════════════════════════════════════════════════════════════════════
   ④ قيدُ جودةٍ لا حاجبُ عمل
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **الاختياريُّ الناقصُ يُسجَّل هنا ويمضي**: «بياناتُ التتبّعِ غيرُ مكتملة»
     ملاحظةٌ تُقاس ولا تُوقف — والفرقُ بين هذا الجدولِ ورمزِ الردِّ هو الفرقُ
     بين إدارةِ الجودةِ وتعطيلِ التشغيل. */
$run("
CREATE TABLE IF NOT EXISTS `proc_track_gap` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `item_id`      INT UNSIGNED NOT NULL,
  `op_kind`      VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'RECEIPT او ISSUE او TRANSFER او COUNT',
  `op_ref`       VARCHAR(60)  NOT NULL DEFAULT '',
  `missing`      VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'الحقول الناقصة مفصولة بواو',
  `policy_level` VARCHAR(24)  NOT NULL DEFAULT 'OPTIONAL',
  `resolved`     TINYINT(1)   NOT NULL DEFAULT 0,
  `logged_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_gap_item` (`company_id`,`item_id`,`resolved`),
  CONSTRAINT `chk_gap_what` CHECK (`missing` <> '' AND `op_kind` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='DEC-OPEN-15 نقص بيانات التتبع - قيد جودة لا حاجب عمل'", 'proc_track_gap');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ اعتمادُ تجاوزِ الصلاحية — بسلطةٍ لا باسم
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **وأمينُ المخزنِ لا يمدّد الصلاحيةَ من عنده**: `approver_role` يُقارَن
     بسلطةِ السياسةِ لا بدورِ المنفِّذ. */
$run("
CREATE TABLE IF NOT EXISTS `proc_expiry_override` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `item_id`       INT UNSIGNED NOT NULL,
  `lot_id`        INT UNSIGNED NOT NULL DEFAULT 0,
  `op_kind`       VARCHAR(24)  NOT NULL DEFAULT '',
  `op_ref`        VARCHAR(60)  NOT NULL DEFAULT '',
  `expiry_date`   DATE         NULL,
  `requested_by`  INT UNSIGNED NOT NULL DEFAULT 0,
  `approver_role` VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'الدور المخول في السياسة',
  `approved_by`   INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_at`   DATETIME     NULL,
  `reason`        VARCHAR(600) NOT NULL DEFAULT '',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ovr_item` (`company_id`,`item_id`),
  CONSTRAINT `chk_ovr_full` CHECK (`reason` <> '' AND `approver_role` <> '' AND `op_kind` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='DEC-OPEN-15 تجاوز صرف المنتهي باعتماد سلطة السياسة'", 'proc_expiry_override');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ إعادةُ التأهيل — دورةٌ تُفعَّل بالسياسةِ وتُعطَّل بها
   ═══════════════════════════════════════════════════════════════════════════ */
$run("
CREATE TABLE IF NOT EXISTS `proc_requalification` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `item_id`       INT UNSIGNED NOT NULL,
  `lot_id`        INT UNSIGNED NOT NULL DEFAULT 0,
  `serial_id`     INT UNSIGNED NOT NULL DEFAULT 0,
  `old_expiry`    DATE         NULL,
  `quarantined`   TINYINT(1)   NOT NULL DEFAULT 0,
  `inspected_at`  DATE         NULL,
  `inspected_by`  INT UNSIGNED NOT NULL DEFAULT 0,
  `tech_doc_ref`  VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'المستند الفني - لا تاريخ جديد بلا مستند',
  `new_expiry`    DATE         NULL,
  `approved_by`   INT UNSIGNED NOT NULL DEFAULT 0,
  `approved_at`   DATETIME     NULL,
  `state`         VARCHAR(24)  NOT NULL DEFAULT 'opened',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_req_item` (`company_id`,`item_id`),
  /* ⛔ **لا تاريخَ جديدًا بلا مستندٍ فنيٍّ ومعتمِد** */
  CONSTRAINT `chk_req_doc` CHECK (`new_expiry` IS NULL
                                  OR (`tech_doc_ref` <> '' AND `approved_by` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='DEC-OPEN-15 اعادة الفحص والتاهيل - تفعل بالسياسة'", 'proc_requalification');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑦ أعمدةٌ محلولةٌ على الصنفِ — تُشتقُّ ولا تُكتب بيد
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **الأعلامُ الثنائيّةُ من W09 تبقى ولا تُنزَع**: بوّاباتُ W09 تقرؤها،
     ونزعُها يُسقط مرحلةً مُغلَقة. وتصير **مشتقّةً من الثلاثيّ**:
     `REQUIRED` ⇐ `1` · وما دونه `0`. فالقديمُ يبقى صادقًا بمعناه الأصليّ
     «أيُلزِم هذا الصنفُ بياناتِ تتبّعٍ؟» والجديدُ يحمل الدرجةَ كاملةً. */
$addCol('proc_item', 'track_lot_level',
    "`track_lot_level` ENUM('OFF','OPTIONAL','REQUIRED') NOT NULL DEFAULT 'OFF' COMMENT 'محلول من السياسة'");
$addCol('proc_item', 'track_serial_level',
    "`track_serial_level` ENUM('OFF','OPTIONAL','REQUIRED') NOT NULL DEFAULT 'OFF' COMMENT 'محلول من السياسة'");
$addCol('proc_item', 'track_mfg_level',
    "`track_mfg_level` ENUM('OFF','OPTIONAL','REQUIRED') NOT NULL DEFAULT 'OFF' COMMENT 'محلول من السياسة'");
$addCol('proc_item', 'track_expiry_level',
    "`track_expiry_level` ENUM('OFF','OPTIONAL','REQUIRED') NOT NULL DEFAULT 'OFF' COMMENT 'محلول من السياسة'");
$addCol('proc_item', 'track_warranty_level',
    "`track_warranty_level` ENUM('OFF','OPTIONAL','REQUIRED') NOT NULL DEFAULT 'OFF' COMMENT 'محلول من السياسة'");
$addCol('proc_item', 'expiry_enforce',
    "`expiry_enforce` ENUM('WARNING','APPROVAL_REQUIRED','HARD_BLOCK') NOT NULL DEFAULT 'WARNING' COMMENT 'محلول من السياسة'");
$addCol('proc_item', 'issue_policy',
    "`issue_policy` ENUM('FIFO','FEFO','MANUAL') NOT NULL DEFAULT 'FIFO' COMMENT 'محلول من السياسة'");
$addCol('proc_item', 'requalify',
    "`requalify` ENUM('ENABLED','DISABLED') NOT NULL DEFAULT 'DISABLED' COMMENT 'محلول من السياسة'");
$addCol('proc_item', 'policy_scope',
    "`policy_scope` VARCHAR(16) NOT NULL DEFAULT '' COMMENT 'ITEM ان خصص و CATEGORY ان ورث و NONE ان لا سياسة'");
$addCol('proc_item', 'policy_version',
    "`policy_version` INT UNSIGNED NOT NULL DEFAULT 0");

/* بنودُ سندِ الإدخالِ تحمل مرجعَ الدفعةِ والرقمِ التسلسليِّ لا نصَّهما وحدَه */
$addCol('proc_receipt_line', 'lot_id',
    "`lot_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'مرجع سجل الدفعة'");
$addCol('proc_receipt_line', 'mfg_date',
    "`mfg_date` DATE NULL COMMENT 'مستقل عن الصلاحية'");
$addCol('proc_receipt_line', 'warranty_until',
    "`warranty_until` DATE NULL");

/* ═══════════════════════════════════════════════════════════════════════════
   ⑧ حالتا الرصيدِ الموروثِ والمرقَّم — تتعايشان للصنفِ نفسِه
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **ولا يُخترَع رقمٌ تسلسليٌّ لرصيدٍ تاريخيّ**: القديمُ `LEGACY_UNTRACKED`
     والجديدُ `SERIALIZED`، ونفسُ الصنفِ قد يحمل الاثنَين معًا. */
echo "\n⑧ حالتا الموروثِ والمرقَّم — تُبذَران في سجلِّ الحالات ─────────\n";
if (tp_tbl($conn, 'proc_stock_state')) {
    echo "  ↷ `proc_stock_state` قائمٌ — والحالتانِ تُكتبانِ بالاشتقاقِ لا بالهجرة\n"; $skip++;
} else {
    echo "  ⚠ `proc_stock_state` غيرُ موجود — شغّلْ هجرةَ W09 أوّلًا\n"; $skip++;
}

echo "\n───────────────────────────────────────────────────────────────\n";
echo "الخلاصة: نُفِّذ $done · مُتخطًّى $skip · أخطاء $err\n";
exit($err > 0 ? 1 : 0);
