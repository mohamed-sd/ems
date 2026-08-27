<?php
/**
 * 2027_12_03_repair01_w14_control.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W14 — **المخاطرُ والحوكمةُ والمراجعة: ثلاثةُ نطاقاتٍ لا محرّكٌ واحد**
 *
 * ◆ **ثلاثةُ مجالاتٍ بمصادرِها والعلاقاتُ بينها مراجعُ لا مشاركة** (‏قيدُ المالك
 *   §١ · الأمرُ الأوّل البند 46): `Risk_ID` عند المخاطر · `Governance_Case_ID`
 *   و`Compliance_Obligation_ID` و`Integrity_Investigation_ID` عند الحوكمة ·
 *   `Audit_Engagement_ID` و`Audit_Finding_ID` عند المراجعة. **ولا سطحَ مصدرٍ
 *   يملكه اثنان** — و`repair01_w14_domains` يُعلن الملكيّةَ بمفتاحٍ فريدٍ على
 *   الجدولِ نفسِه، فادّعاءُ نطاقَين جدولًا واحدًا **يُردُّ في القاعدة**.
 *
 * ◆ **والانحرافُ يبقى عند مالكِه التشغيليّ** — `ctl_deviation` هو **حبّةُ الرحلة**،
 *   ومالكُه إدارةٌ تشغيليّةٌ لا واحدةٌ من الثلاث: `chk_ctd_owner_not_control`.
 *   فالحوكمةُ والمخاطرُ **تقرآنِه بمرجعِه ولا تنسخانِه** — وهو عينُ
 *   «العطلُ يبقى `Source Event` عند التشغيلِ والصيانة» (‏القرار ②).
 *
 * ◆ **والتمييزُ الثلاثيُّ بقاعدةٍ مكتوبة** (§27): `Operational Deviation ≠ Risk
 *   Exposure ≠ Governance/Compliance Breach`. و`chk_ctd_rule_required` يردُّ
 *   تصنيفًا بلا قاعدةٍ مسجَّلة، و`chk_gvb_basis` يحصر أساسَ فتحِ حالةِ الحوكمةِ
 *   في الثمانيةِ التي سمّاها المالك — **فلا تُفتح حالةُ حوكمةٍ لانحرافٍ تشغيليٍّ
 *   صِرف**.
 *
 * ◆ **والمراجعةُ خطٌّ ثالثٌ مستقلّ** (§12 · قيدُ المالك §١): نتيجةُ المراجعةِ
 *   تُوضَع وتُغلَق **من `IAF` وحدَها** — `chk_iaf_result_dept` و
 *   `chk_iaf_close_dept` على `iaf_findings`، ونطاقُ البرنامجِ يُحدَّد من غيرِ
 *   الحوكمةِ — `chk_ifp_scope_not_gov`. **والحوكمةُ تتابع ولا تعدّل**:
 *   `gov_audit_followup` يحمل مرجعَ الملاحظةِ وخطّةَ الإدارةِ **ولا يحمل
 *   نتيجتَها ولا تقديرَها**.
 *
 * ◆ **والتحقيقُ ثلاثةُ أنواعٍ بثلاثةِ ملّاك** (‏`DEC-OPEN-16` معتمَد):
 *   تأديبيٌّ للموارد `DEP-07` · نزاهةٌ للحوكمة `DEP-08` · تقصٍّ تشغيليٌّ
 *   للإدارةِ المختصّة. و`chk_gin_kind_owner` يردُّ كلَّ تركيبةٍ غيرِها.
 *   **ولا طابورَ تحقيقٍ يوميٍّ للمراجعة**: `chk_gin_iaf_mandate` يشترط تكليفًا
 *   مكتوبًا لكلِّ `SPECIAL_INDEPENDENT`. **والنقرُ الممنوعُ ليس تحقيقًا**:
 *   `chk_gin_denial_triage` يشترط فرزًا قبلَه.
 *
 * ◆ **والحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): كلُّ جدولٍ هنا يحمل `company_id`
 *   **غيرَ قابلٍ للعدم** — والحاجبُ يفحص **عدمَ قبولِ العدمِ في المخطَّط** لا
 *   وجودَ العمود. **ووسمُ ما بين الكيانَين من الآن**: `gov_related_party` يحمل
 *   الخماسيَّ منذ إنشائه — `chk_grp_intercompany`.
 *
 * ◆ **والعتبةُ من السجلِّ وحدَه**: `repair01_w14_thresholds` بثلاثِ حالات —
 *   `OWNER_APPROVED` (‏ما نصَّ عليه المالكُ حرفًا) · `CONFIG_PENDING` (‏قيمةٌ
 *   عدم لا رقمَ مخترَع) · وقيمةُ الاختبارِ **موسومةٌ ولا تنتقل**:
 *   `chk_w14_th_test_not_prod`.
 *
 * ◆ **و`CHECK` لا قادِح**: مستخدمُ الهجراتِ لا يملك `SUPER`.
 *
 * ⛔ **ولا يُمَسُّ عمودٌ حيٌّ بحذفٍ أو إعادةِ تعريف** — الإضافةُ وحدَها.
 *
 * التشغيل: php database/migrations/2027_12_03_repair01_w14_control.php
 *          (⛔ لا `migrate.php up` — الهجرةُ بمسارِها الكاملِ منفردةً)
 * التراجع: php database/migrations/2027_12_03_repair01_w14_control_down.php
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

function w14_tbl(mysqli $c, $t)
{
    $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}
function w14_col(mysqli $c, $t, $col)
{
    if (!w14_tbl($c, $t)) { return false; }
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}
function w14_one(mysqli $c, $sql)
{
    $r = @$c->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x ? $x[0] : null;
}

$done = 0; $err = 0; $skip = 0;
$run = function ($sql, $label) use ($conn, &$done, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; return true; }
    echo "  ✘ $label — " . $conn->error . "\n"; $err++; return false;
};

echo "══ REPAIR01 · W14 — المخاطر والحوكمة والمراجعة ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ① دفاترُ المرحلة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① دفاترُ المرحلة ───────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w14_scope` (
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
  `cycle_step`       SMALLINT     NOT NULL DEFAULT 0 COMMENT 'موضع السطح من دورة العمل لا من الابجدية',
  `entity_scoped`    TINYINT(1)   NOT NULL DEFAULT 0,
  `domain_code`      VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'اي النطاقات الثلاثة يملك السطح',
  `line_of_defence`  VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'الخط الثاني ام الثالث',
  `map_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `map_why`          VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`          VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`requirement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W14 - نطاق المرحلة ومرساة كل متطلب'", 'repair01_w14_scope');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w14_sidebar` (
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
  COMMENT='REPAIR01 W14 - سبع خطوات السايدبار بحكم وقاعدة'", 'repair01_w14_sidebar');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w14_decisions` (
  `decision_id` VARCHAR(16)  NOT NULL,
  `question`    VARCHAR(400) NOT NULL DEFAULT '',
  `answer`      VARCHAR(400) NOT NULL DEFAULT '',
  `rationale`   VARCHAR(900) NOT NULL DEFAULT '',
  `scope_rows`  INT          NOT NULL DEFAULT 0,
  `decided_at`  DATE         NOT NULL,
  `src_ref`     VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`decision_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W14 - قرارات المرحلة'", 'repair01_w14_decisions');

/* **دفترُ المؤجَّلِ — قرارُ المالكِ لا يُكتب نيابةً عنه** (‏قيدُ المالك §٩).
   كلُّ سؤالٍ احتاجَته المرحلةُ ولم يُجب عنه المالكُ يُسجَّل هنا **مؤجَّلًا
   صراحةً** ولا يُخمَّن. والحاجبُ يشترط ألّا يبقى مؤجَّلٌ بلا أثرٍ مُعلَنٍ
   في المخرَجات. */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w14_deferred` (
  `deferred_id`  VARCHAR(24)  NOT NULL,
  `question`     VARCHAR(500) NOT NULL DEFAULT '',
  `why_needed`   VARCHAR(500) NOT NULL DEFAULT '',
  `blocked_what` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'ما الذي توقف فعلا - وفراغه يعني لم يوقف شيئا',
  `built_anyway` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'ما بني رغم التاجيل وكيف',
  `kind`         VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'STRUCTURAL او THRESHOLD',
  `raised_at`    DATE         NOT NULL,
  `src_ref`      VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`deferred_id`),
  CONSTRAINT `chk_w14_def_kind` CHECK (`kind` IN ('STRUCTURAL','THRESHOLD'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W14 - ما ينتظر قرار المالك مسجلا لا مخمنا'", 'repair01_w14_deferred');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w14_states` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity`        VARCHAR(64)  NOT NULL,
  `from_state`    VARCHAR(40)  NOT NULL,
  `to_state`      VARCHAR(40)  NOT NULL,
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
  UNIQUE KEY `uq_w14st` (`entity`, `from_state`, `to_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W14 - الات الحالة بممنوع صريح بسبب'", 'repair01_w14_states');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w14_sod` (
  `process_key`       VARCHAR(64)  NOT NULL,
  `process_name`      VARCHAR(255) NOT NULL DEFAULT '',
  `domain_code`       VARCHAR(12)  NOT NULL DEFAULT '',
  `initiator_role`    VARCHAR(120) NOT NULL DEFAULT '',
  `reviewer_role`     VARCHAR(120) NOT NULL DEFAULT '',
  `approver_role`     VARCHAR(120) NOT NULL DEFAULT '',
  `executor_role`     VARCHAR(120) NOT NULL DEFAULT '',
  `closer_role`       VARCHAR(120) NOT NULL DEFAULT '',
  `forbidden_combo`   VARCHAR(400) NOT NULL DEFAULT '',
  `enforced_by`       VARCHAR(64)  NOT NULL DEFAULT '',
  `authority_rule_id` VARCHAR(48)  NOT NULL DEFAULT '',
  `deputy_role`       VARCHAR(120) NOT NULL DEFAULT '',
  `scope_rule`        VARCHAR(255) NOT NULL DEFAULT '',
  `delegation`        VARCHAR(255) NOT NULL DEFAULT '',
  `effective_date`    DATE         NOT NULL,
  `src_ref`           VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`process_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W14 - فصل الواجبات بستة ادوار وتركيبة ممنوعة'", 'repair01_w14_sod');

/* **العتبةُ بحالتِها — ولا رقمَ يخترعه المبرمج** (‏قيدُ المالك §٦).
   ◆ `OWNER_APPROVED` قيمةٌ نصَّ عليها المالكُ حرفًا بمرجعِها.
   ◆ `CONFIG_PENDING` **قيمتُها عدم** — والمحرّكُ يردُّ ولا يفترض.
   ◆ وقيمةُ الاختبارِ في عمودٍ منفصلٍ موسومةٍ، و`chk_w14_th_test_not_prod`
     يمنع أن تصير قيمةً معتمَدة. */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w14_thresholds` (
  `threshold_key` VARCHAR(64)    NOT NULL,
  `value_num`     DECIMAL(18,4)  NULL DEFAULT NULL COMMENT 'عدم يعني غير معتمدة - ولا يخترع لها رقم',
  `test_value_num` DECIMAL(18,4) NULL DEFAULT NULL COMMENT 'قيمة اختبار موسومة لا تنتقل للانتاج',
  `status`        VARCHAR(24)    NOT NULL DEFAULT 'CONFIG_PENDING',
  `registry`      VARCHAR(48)    NOT NULL DEFAULT '' COMMENT 'السجل الذي تقرا منه القيمة عند اعتمادها',
  `unit_ar`       VARCHAR(48)    NOT NULL DEFAULT '',
  `title_ar`      VARCHAR(160)   NOT NULL DEFAULT '',
  `why`           VARCHAR(400)   NOT NULL DEFAULT '',
  `decision_ref`  VARCHAR(48)    NOT NULL DEFAULT '',
  `src_ref`       VARCHAR(255)   NOT NULL DEFAULT '',
  PRIMARY KEY (`threshold_key`),
  CONSTRAINT `chk_w14_th_status` CHECK (`status` IN ('OWNER_APPROVED','CONFIG_PENDING')),
  CONSTRAINT `chk_w14_th_approved_has_value` CHECK (`status` <> 'OWNER_APPROVED' OR `value_num` IS NOT NULL),
  CONSTRAINT `chk_w14_th_approved_has_ref`   CHECK (`status` <> 'OWNER_APPROVED' OR `decision_ref` <> ''),
  CONSTRAINT `chk_w14_th_pending_no_value`   CHECK (`status` <> 'CONFIG_PENDING' OR `value_num` IS NULL),
  CONSTRAINT `chk_w14_th_test_not_prod`      CHECK (`test_value_num` IS NULL OR `status` = 'CONFIG_PENDING')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W14 - العتبات بحالتها والمعلقة قيمتها عدم'", 'repair01_w14_thresholds');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w14_fixes` (
  `fix_key`     VARCHAR(64)  NOT NULL,
  `title`       VARCHAR(255) NOT NULL DEFAULT '',
  `revealed_by` VARCHAR(48)  NOT NULL DEFAULT '',
  `before_num`  VARCHAR(80)  NOT NULL DEFAULT '',
  `after_num`   VARCHAR(80)  NOT NULL DEFAULT '',
  `why`         VARCHAR(600) NOT NULL DEFAULT '',
  `src_ref`     VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`fix_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W14 - كل اصلاح بمتطلبه الكاشف'", 'repair01_w14_fixes');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w14_journey` (
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
  KEY `ix_w14j_run` (`run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W14 - محطات رحلة الضابط باثرها التجاري'", 'repair01_w14_journey');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w14_nav_moves` (
  `nav_item_id`   INT UNSIGNED NOT NULL,
  `route`         VARCHAR(200) NOT NULL DEFAULT '',
  `role_id`       INT          NOT NULL DEFAULT 0,
  `from_group_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `to_group_id`   INT UNSIGNED NOT NULL DEFAULT 0,
  `to_group_code` VARCHAR(48)  NOT NULL DEFAULT '',
  `why`           VARCHAR(400) NOT NULL DEFAULT '',
  `moved_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`nav_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W14 - الموضع الاصلي لبند نقل ليعود اليه حرفا'", 'repair01_w14_nav_moves');

/* **دفترُ النطاقاتِ الثلاثة — مفتاحُ الجدولِ فريدٌ فلا يملكه نطاقان.**
   ◆ **ومقامُه ثابتٌ لا يخلو** (‏درسُ `W12-27`): النطاقاتُ وجداولُها تُعلَن
     هنا وتُقاس على الحيِّ معًا — فيقظُ الحاجبِ من أوّلِ يومٍ لا من أوّلِ صفّ. */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w14_domains` (
  `table_name`   VARCHAR(64)  NOT NULL COMMENT 'جدول المصدر - ومفتاحه الفريد يمنع ان يملكه نطاقان',
  `domain_code`  VARCHAR(12)  NOT NULL DEFAULT '',
  `domain_ar`    VARCHAR(120) NOT NULL DEFAULT '',
  `source_key`   VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'المعرف الحاكم للمجال',
  `line`         VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'SECOND او THIRD او SOURCE',
  `owns`         VARCHAR(500) NOT NULL DEFAULT '',
  `never_owns`   VARCHAR(500) NOT NULL DEFAULT '',
  `read_by`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'من يقرؤه بمرجعه ولا يكتب فيه',
  `service_file` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'الخدمة الوحيدة التي تكتب فيه',
  `why`          VARCHAR(600) NOT NULL DEFAULT '',
  `src_ref`      VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`table_name`),
  KEY `ix_w14dom` (`domain_code`),
  CONSTRAINT `chk_w14_dom_code` CHECK (`domain_code` IN ('DEP-08','DEP-09','IAF','SOURCE')),
  CONSTRAINT `chk_w14_dom_line` CHECK (`line` IN ('SECOND','THIRD','SOURCE'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W14 - ثلاثة نطاقات ولا جدول يملكه اثنان'", 'repair01_w14_domains');

echo "\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ② الانحرافُ وقاعدةُ تصنيفِه — **حبّةُ الرحلةِ ومالكُها تشغيليّ**
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **القاعدةُ مكتوبةٌ قبل أن يُصنَّف انحراف**: `ctl_classification_rule` يحمل
     شرطَ الانتقالِ إلى تعرُّضٍ وشرطَ الانتقالِ إلى خرقٍ **نصًّا وحقلًا** —
     فالتصنيفُ يستند إلى قاعدةٍ لا إلى اجتهادِ مُصنِّف.
   ◆ **والانحرافُ لا يملكه نطاقُ رقابة**: `chk_ctd_owner_not_control`.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "② الانحرافُ التشغيليُّ وقاعدةُ تصنيفِه ────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `ctl_classification_rule` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `rule_code`     VARCHAR(40)  NOT NULL,
  `title_ar`      VARCHAR(200) NOT NULL DEFAULT '',
  `deviation_kind` VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'صنف الانحراف الذي تحكمه القاعدة',
  `exposure_test` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'متى يصير الانحراف تعرضا عند المخاطر',
  `breach_test`   VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'متى يصير خرق ضابط عند الحوكمة',
  `retain_test`   VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'متى يبقى انحرافا عند مالكه',
  `appetite_key`  VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'مفتاح العتبة في السجل - ولا رقم هنا',
  `control_ref`   VARCHAR(64)  NOT NULL DEFAULT '',
  `policy_ref`    VARCHAR(64)  NOT NULL DEFAULT '',
  `state`         VARCHAR(16)  NOT NULL DEFAULT 'draft',
  `effective_from` DATE        NULL DEFAULT NULL,
  `authored_by`   INT          NOT NULL DEFAULT 0,
  `approved_by`   INT          NOT NULL DEFAULT 0,
  `src_ref`       VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ctlr` (`company_id`, `rule_code`),
  CONSTRAINT `chk_ctlr_state` CHECK (`state` IN ('draft','active','retired')),
  CONSTRAINT `chk_ctlr_tests` CHECK (`state` <> 'active'
        OR (`exposure_test` <> '' AND `breach_test` <> '' AND `retain_test` <> '')),
  CONSTRAINT `chk_ctlr_sod` CHECK (`state` <> 'active' OR `approved_by` <> `authored_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 - قاعدة التمييز الثلاثي مكتوبة قبل ان يصنف انحراف'", 'ctl_classification_rule');

$run("
CREATE TABLE IF NOT EXISTS `ctl_deviation` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `deviation_no`   VARCHAR(40)  NOT NULL,
  `owner_dept`     VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'الادارة التشغيلية المالكة - لا نطاق رقابة',
  `source_module`  VARCHAR(40)  NOT NULL DEFAULT '',
  `source_table`   VARCHAR(64)  NOT NULL DEFAULT '',
  `source_row_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `deviation_kind` VARCHAR(40)  NOT NULL DEFAULT '',
  `downtime_kind`  VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'مخطط ام غير مخطط - والمخطط يستثنى من محفز الاربع والعشرين',
  `occurred_at`    DATETIME     NULL DEFAULT NULL,
  `duration_hours` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `recurrence_no`  SMALLINT     NOT NULL DEFAULT 1,
  `preventable`    TINYINT(1)   NOT NULL DEFAULT 0,
  `classification` VARCHAR(24)  NOT NULL DEFAULT 'PENDING',
  `rule_code`      VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'قاعدة التصنيف المكتوبة',
  `classified_by`  INT          NOT NULL DEFAULT 0,
  `classified_at`  DATETIME     NULL DEFAULT NULL,
  `risk_ref`       VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'مرجع في المخاطر لا نسخة منها',
  `governance_ref` VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'مرجع في الحوكمة لا نسخة منها',
  `state`          VARCHAR(24)  NOT NULL DEFAULT 'registered',
  `why`            VARCHAR(500) NOT NULL DEFAULT '',
  `src_ref`        VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ctd` (`company_id`, `deviation_no`),
  KEY `ix_ctd_src` (`source_table`, `source_row_id`),
  KEY `ix_ctd_class` (`classification`),
  CONSTRAINT `chk_ctd_owner_not_control` CHECK (`owner_dept` NOT IN ('DEP-08','DEP-09','IAF')),
  CONSTRAINT `chk_ctd_owner_set` CHECK (`owner_dept` <> ''),
  CONSTRAINT `chk_ctd_source` CHECK (`source_table` <> '' AND `source_row_id` > 0),
  CONSTRAINT `chk_ctd_class` CHECK (`classification` IN
        ('PENDING','DEVIATION_ONLY','RISK_EXPOSURE','GOVERNANCE_BREACH','EXPOSURE_AND_BREACH')),
  CONSTRAINT `chk_ctd_rule_required` CHECK (`classification` = 'PENDING' OR `rule_code` <> ''),
  CONSTRAINT `chk_ctd_hand` CHECK (`classification` = 'PENDING' OR `classified_by` <> 0),
  CONSTRAINT `chk_ctd_only_no_refs` CHECK (`classification` <> 'DEVIATION_ONLY'
        OR (`risk_ref` = '' AND `governance_ref` = '')),
  CONSTRAINT `chk_ctd_state` CHECK (`state` IN
        ('registered','classified','referred','retained','closed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 - الانحراف التشغيلي عند مالكه والرقابة تقرؤه بمرجعه'", 'ctl_deviation');

echo "\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ③ الحوكمةُ والالتزام — الخطُّ الثاني (`DEP-08`)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③ الحوكمة والالتزام — الخط الثاني ─────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `gov_policy` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `policy_no`    VARCHAR(40)  NOT NULL,
  `version_no`   SMALLINT     NOT NULL DEFAULT 1,
  `title_ar`     VARCHAR(255) NOT NULL DEFAULT '',
  `domain_ar`    VARCHAR(120) NOT NULL DEFAULT '',
  `owner_dept`   VARCHAR(12)  NOT NULL DEFAULT '',
  `owner_person` INT          NOT NULL DEFAULT 0,
  `doc_ref`      VARCHAR(120) NOT NULL DEFAULT '',
  `effective_from` DATE       NULL DEFAULT NULL,
  `review_due`   DATE         NULL DEFAULT NULL,
  `supersedes`   VARCHAR(40)  NOT NULL DEFAULT '',
  `authored_by`  INT          NOT NULL DEFAULT 0,
  `reviewed_by`  INT          NOT NULL DEFAULT 0,
  `approved_by`  INT          NOT NULL DEFAULT 0,
  `approved_at`  DATETIME     NULL DEFAULT NULL,
  `state`        VARCHAR(24)  NOT NULL DEFAULT 'draft',
  `src_ref`      VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gvp` (`company_id`, `policy_no`, `version_no`),
  CONSTRAINT `chk_gvp_state` CHECK (`state` IN ('draft','reviewed','approved','effective','superseded','retired')),
  CONSTRAINT `chk_gvp_owner` CHECK (`state` = 'draft' OR (`owner_dept` <> '' AND `owner_person` <> 0)),
  CONSTRAINT `chk_gvp_sod` CHECK (`approved_by` = 0 OR `approved_by` <> `authored_by`),
  CONSTRAINT `chk_gvp_effective_doc` CHECK (`state` <> 'effective' OR (`doc_ref` <> '' AND `effective_from` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 GOV-03 - سجل السياسات بالاصدار ولا سياسة بلا مالك'", 'gov_policy');

$run("
CREATE TABLE IF NOT EXISTS `gov_obligation` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `obligation_no` VARCHAR(40)  NOT NULL COMMENT 'Compliance_Obligation_ID',
  `title_ar`      VARCHAR(255) NOT NULL DEFAULT '',
  `authority_ar`  VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'الجهة المفروض منها الالتزام',
  `basis_ref`     VARCHAR(160) NOT NULL DEFAULT '',
  `periodicity`   VARCHAR(24)  NOT NULL DEFAULT '',
  `owner_dept`    VARCHAR(12)  NOT NULL DEFAULT '',
  `owner_person`  INT          NOT NULL DEFAULT 0,
  `policy_ref`    VARCHAR(40)  NOT NULL DEFAULT '',
  `next_due`      DATE         NULL DEFAULT NULL,
  `state`         VARCHAR(24)  NOT NULL DEFAULT 'registered',
  `src_ref`       VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gvo` (`company_id`, `obligation_no`),
  CONSTRAINT `chk_gvo_state` CHECK (`state` IN ('registered','monitored','met','breached','retired')),
  CONSTRAINT `chk_gvo_owner` CHECK (`owner_dept` <> '' AND `authority_ar` <> ''),
  CONSTRAINT `chk_gvo_period` CHECK (`periodicity` IN ('once','monthly','quarterly','semiannual','annual','on_event'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 GOV-04 - الالتزام التنظيمي بجهته ودوريته ومالكه'", 'gov_obligation');

$run("
CREATE TABLE IF NOT EXISTS `gov_compliance_due` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `obligation_no` VARCHAR(40)  NOT NULL,
  `due_date`      DATE         NOT NULL,
  `owner_dept`    VARCHAR(12)  NOT NULL DEFAULT '',
  `owner_person`  INT          NOT NULL DEFAULT 0,
  `derived_from`  VARCHAR(48)  NOT NULL DEFAULT '' COMMENT 'مشتق من الالتزام او الترخيص او الاقرار',
  `settled_ref`   VARCHAR(64)  NOT NULL DEFAULT '',
  `state`         VARCHAR(24)  NOT NULL DEFAULT 'due',
  `src_ref`       VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gcd` (`company_id`, `obligation_no`, `due_date`),
  CONSTRAINT `chk_gcd_state` CHECK (`state` IN ('due','met','late','waived')),
  CONSTRAINT `chk_gcd_derived` CHECK (`derived_from` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 GOV-05 - تقويم الامتثال مشتق بمرجع اشتقاقه'", 'gov_compliance_due');

$run("
CREATE TABLE IF NOT EXISTS `gov_filing` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `filing_no`    VARCHAR(40)  NOT NULL,
  `obligation_no` VARCHAR(40) NOT NULL DEFAULT '',
  `authority_ar` VARCHAR(160) NOT NULL DEFAULT '',
  `period_label` VARCHAR(40)  NOT NULL DEFAULT '',
  `due_date`     DATE         NULL DEFAULT NULL,
  `submitted_at` DATETIME     NULL DEFAULT NULL,
  `submitted_by` INT          NOT NULL DEFAULT 0,
  `receipt_ref`  VARCHAR(120) NOT NULL DEFAULT '',
  `state`        VARCHAR(24)  NOT NULL DEFAULT 'due',
  `src_ref`      VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gvf` (`company_id`, `filing_no`),
  CONSTRAINT `chk_gvf_state` CHECK (`state` IN ('due','prepared','submitted','acknowledged','late')),
  CONSTRAINT `chk_gvf_receipt` CHECK (`state` <> 'acknowledged' OR `receipt_ref` <> ''),
  CONSTRAINT `chk_gvf_submitted` CHECK (`state` NOT IN ('submitted','acknowledged') OR `submitted_at` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 GOV-08 - التقديم النظامي بموعده وايصاله'", 'gov_filing');

$run("
CREATE TABLE IF NOT EXISTS `gov_conflict_disclosure` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `disclosure_no` VARCHAR(40) NOT NULL,
  `person_id`    INT          NOT NULL DEFAULT 0,
  `nature_ar`    VARCHAR(400) NOT NULL DEFAULT '',
  `counterparty_ar` VARCHAR(200) NOT NULL DEFAULT '',
  `related_party_no` VARCHAR(40) NOT NULL DEFAULT '',
  `disclosed_at` DATETIME     NULL DEFAULT NULL,
  `assessed_by`  INT          NOT NULL DEFAULT 0,
  `decision`     VARCHAR(24)  NOT NULL DEFAULT '',
  `decision_ref` VARCHAR(120) NOT NULL DEFAULT '',
  `recused_from` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'القرار الذي تنحى عنه صاحب الافصاح',
  `state`        VARCHAR(24)  NOT NULL DEFAULT 'disclosed',
  `src_ref`      VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gcf` (`company_id`, `disclosure_no`),
  CONSTRAINT `chk_gcf_person` CHECK (`person_id` <> 0),
  CONSTRAINT `chk_gcf_state` CHECK (`state` IN ('disclosed','assessed','mitigated','recused','rejected','closed')),
  CONSTRAINT `chk_gcf_decision` CHECK (`decision` IN ('','mitigate','recuse','reject')),
  CONSTRAINT `chk_gcf_self` CHECK (`assessed_by` = 0 OR `assessed_by` <> `person_id`),
  CONSTRAINT `chk_gcf_recusal` CHECK (`decision` <> 'recuse' OR `recused_from` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 GOV-10 - الافصاح واجب والقرار للحوكمة ولا يقرر صاحبه'", 'gov_conflict_disclosure');

/* **الأطرافُ ذاتُ العلاقةِ تحمل الخماسيَّ منذ إنشائها** (‏قرارُ المالك ①):
   `From_Legal_Entity_ID` · `To_Legal_Entity_ID` · `Intercompany_Flag` ·
   `Counterparty_Entity_ID` · `Transaction_Type` والمرجعُ المقابل.
   **فاكتشافُها بأثرٍ رجعيٍّ بعد سنواتٍ غيرُ موثوق.** */
$run("
CREATE TABLE IF NOT EXISTS `gov_related_party` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`      INT UNSIGNED NOT NULL,
  `party_no`        VARCHAR(40)  NOT NULL,
  `party_name`      VARCHAR(255) NOT NULL DEFAULT '',
  `relation_ar`     VARCHAR(200) NOT NULL DEFAULT '',
  `person_id`       INT          NOT NULL DEFAULT 0,
  `deal_ref`        VARCHAR(120) NOT NULL DEFAULT '',
  `deal_amount`     DECIMAL(18,2) NULL DEFAULT NULL,
  `deal_currency`   VARCHAR(8)   NOT NULL DEFAULT '',
  `disclosure_no`   VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'AAM-015 الالزامي',
  `from_legal_entity_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `to_legal_entity_id`   INT UNSIGNED NOT NULL DEFAULT 0,
  `intercompany_flag`    TINYINT(1) NOT NULL DEFAULT 0,
  `counterparty_entity_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `transaction_type`     VARCHAR(40) NOT NULL DEFAULT '',
  `counterparty_ref`     VARCHAR(120) NOT NULL DEFAULT '',
  `state`           VARCHAR(24)  NOT NULL DEFAULT 'declared',
  `src_ref`         VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grp` (`company_id`, `party_no`),
  CONSTRAINT `chk_grp_state` CHECK (`state` IN ('declared','verified','active','ended')),
  CONSTRAINT `chk_grp_disclosure` CHECK (`state` = 'declared' OR `disclosure_no` <> ''),
  CONSTRAINT `chk_grp_intercompany` CHECK (`intercompany_flag` = 0
        OR (`from_legal_entity_id` > 0 AND `to_legal_entity_id` > 0
            AND `counterparty_entity_id` > 0 AND `transaction_type` <> '')),
  CONSTRAINT `chk_grp_not_self` CHECK (`intercompany_flag` = 0
        OR `from_legal_entity_id` <> `to_legal_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 GOV-11 - الطرف ذو العلاقة وتعامله موسوما بين الكيانات منذ انشائه'", 'gov_related_party');

$run("
CREATE TABLE IF NOT EXISTS `gov_gift_disclosure` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `gift_no`      VARCHAR(40)  NOT NULL,
  `person_id`    INT          NOT NULL DEFAULT 0,
  `gift_kind`    VARCHAR(24)  NOT NULL DEFAULT '',
  `giver_ar`     VARCHAR(200) NOT NULL DEFAULT '',
  `est_value`    DECIMAL(18,2) NULL DEFAULT NULL,
  `currency`     VARCHAR(8)   NOT NULL DEFAULT '',
  `threshold_key` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'مفتاح الحد في السجل - ولا رقم هنا',
  `disclosed_at` DATETIME     NULL DEFAULT NULL,
  `decided_by`   INT          NOT NULL DEFAULT 0,
  `decision`     VARCHAR(24)  NOT NULL DEFAULT '',
  `state`        VARCHAR(24)  NOT NULL DEFAULT 'disclosed',
  `src_ref`      VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ggf` (`company_id`, `gift_no`),
  CONSTRAINT `chk_ggf_kind` CHECK (`gift_kind` IN ('gift','hospitality','travel','other')),
  CONSTRAINT `chk_ggf_state` CHECK (`state` IN ('disclosed','assessed','accepted','returned','declined')),
  CONSTRAINT `chk_ggf_decision` CHECK (`decision` IN ('','accept','return','decline')),
  CONSTRAINT `chk_ggf_self` CHECK (`decided_by` = 0 OR `decided_by` <> `person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 GOV-12 - الهدايا والضيافة والحد من السجل لا من الشيفرة'", 'gov_gift_disclosure');

$run("
CREATE TABLE IF NOT EXISTS `gov_conduct_ack` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `employee_id`  INT          NOT NULL DEFAULT 0,
  `code_version` VARCHAR(24)  NOT NULL DEFAULT '',
  `policy_no`    VARCHAR(40)  NOT NULL DEFAULT '',
  `due_date`     DATE         NULL DEFAULT NULL,
  `acked_at`     DATETIME     NULL DEFAULT NULL,
  `evidence_ref` VARCHAR(120) NOT NULL DEFAULT '',
  `state`        VARCHAR(24)  NOT NULL DEFAULT 'due',
  `src_ref`      VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gca` (`company_id`, `employee_id`, `code_version`),
  CONSTRAINT `chk_gca_state` CHECK (`state` IN ('due','acknowledged','overdue','exempt')),
  CONSTRAINT `chk_gca_ack` CHECK (`state` <> 'acknowledged' OR (`acked_at` IS NOT NULL AND `evidence_ref` <> '')),
  CONSTRAINT `chk_gca_emp` CHECK (`employee_id` <> 0 AND `code_version` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 GOV-13 - اقرار مدونة السلوك عند التعيين وعند كل اصدار'", 'gov_conduct_ack');

$run("
CREATE TABLE IF NOT EXISTS `gov_sod_conflict` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `conflict_code` VARCHAR(40)  NOT NULL,
  `title_ar`      VARCHAR(200) NOT NULL DEFAULT '',
  `side_a`        VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'طرف العملية الاول',
  `side_b`        VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'طرف العملية الثاني',
  `process_key`   VARCHAR(64)  NOT NULL DEFAULT '',
  `detected_role_id` INT       NOT NULL DEFAULT 0,
  `detected_user_id` INT       NOT NULL DEFAULT 0,
  `detected_at`   DATETIME     NULL DEFAULT NULL,
  `mitigation_ar` VARCHAR(400) NOT NULL DEFAULT '',
  `exception_no`  VARCHAR(40)  NOT NULL DEFAULT '',
  `state`         VARCHAR(24)  NOT NULL DEFAULT 'defined',
  `src_ref`       VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gsc` (`company_id`, `conflict_code`, `detected_role_id`, `detected_user_id`),
  CONSTRAINT `chk_gsc_sides` CHECK (`side_a` <> '' AND `side_b` <> '' AND `side_a` <> `side_b`),
  CONSTRAINT `chk_gsc_state` CHECK (`state` IN ('defined','detected','mitigated','accepted','closed')),
  CONSTRAINT `chk_gsc_accept` CHECK (`state` <> 'accepted' OR `exception_no` <> ''),
  CONSTRAINT `chk_gsc_mitigate` CHECK (`state` <> 'mitigated' OR `mitigation_ar` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 GOV-16 - التعارض يعرف مرة ويكشف دوما ولا قبول بلا استثناء'", 'gov_sod_conflict');

/* **قناةٌ محميّةٌ بسرّيّةٍ مشدَّدة** (‏GOV-22): هويّةُ المُبلِّغِ محجوبةٌ إلّا
   لمستوًى مخوَّل — **والمستوى نفسُه قرارٌ لم يُجب عنه المالكُ بعد**، فهو
   مسجَّلٌ مؤجَّلًا في `repair01_w14_deferred` ويُقرأ من `disclosure_role_key`
   في السجلِّ ⛔ ولا يُخمَّن في الشيفرة. */
$run("
CREATE TABLE IF NOT EXISTS `gov_integrity_report` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `report_no`      VARCHAR(40)  NOT NULL,
  `channel`        VARCHAR(24)  NOT NULL DEFAULT '',
  `is_anonymous`   TINYINT(1)   NOT NULL DEFAULT 1,
  `reporter_token` VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'رمز لا اسم - والكشف بمستوى مخول',
  `reporter_person` INT         NOT NULL DEFAULT 0,
  `disclosure_role_key` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'مفتاح المستوى المخول من السجل',
  `subject_ar`     VARCHAR(400) NOT NULL DEFAULT '',
  `received_at`    DATETIME     NULL DEFAULT NULL,
  `triage_by`      INT          NOT NULL DEFAULT 0,
  `triage_at`      DATETIME     NULL DEFAULT NULL,
  `referred_to`    VARCHAR(12)  NOT NULL DEFAULT '',
  `investigation_no` VARCHAR(40) NOT NULL DEFAULT '',
  `retaliation_flag` TINYINT(1) NOT NULL DEFAULT 0,
  `state`          VARCHAR(24)  NOT NULL DEFAULT 'received',
  `src_ref`        VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gir` (`company_id`, `report_no`),
  CONSTRAINT `chk_gir_state` CHECK (`state` IN ('received','triaged','referred','closed','dismissed')),
  CONSTRAINT `chk_gir_anon` CHECK (`is_anonymous` = 0 OR `reporter_person` = 0),
  CONSTRAINT `chk_gir_named` CHECK (`is_anonymous` = 1 OR `reporter_person` <> 0),
  CONSTRAINT `chk_gir_token` CHECK (`reporter_token` <> ''),
  CONSTRAINT `chk_gir_referred` CHECK (`state` <> 'referred' OR `referred_to` <> ''),
  CONSTRAINT `chk_gir_triage` CHECK (`state` IN ('received') OR `triage_at` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 GOV-22 - قناة محمية بهوية محجوبة ولا انتقام'", 'gov_integrity_report');

/* **التحقيقُ ثلاثةُ أنواعٍ بثلاثةِ ملّاك** (‏`DEC-OPEN-16` معتمَد) —
   والرابعُ `SPECIAL_INDEPENDENT` **بتكليفٍ مكتوبٍ لا باختصاصٍ أصيل**.
   ⛔ **والنقرُ الممنوعُ ليس تحقيقًا**: `origin='DENIAL'` يشترط فرزًا سابقًا. */
$run("
CREATE TABLE IF NOT EXISTS `gov_investigation` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `inv_no`         VARCHAR(40)  NOT NULL COMMENT 'Integrity_Investigation_ID',
  `inv_kind`       VARCHAR(24)  NOT NULL DEFAULT '',
  `owner_dept`     VARCHAR(12)  NOT NULL DEFAULT '',
  `origin`         VARCHAR(24)  NOT NULL DEFAULT '',
  `origin_ref`     VARCHAR(64)  NOT NULL DEFAULT '',
  `triage_ref`     VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'فرز سابق - وبدونه لا يفتح تحقيق من سجل المنع',
  `mandate_doc_ref` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'التكليف المكتوب - شرط المراجعة الداخلية',
  `subject_person` INT          NOT NULL DEFAULT 0,
  `scope_ar`       VARCHAR(400) NOT NULL DEFAULT '',
  `investigator_id` INT         NOT NULL DEFAULT 0,
  `opened_by`      INT          NOT NULL DEFAULT 0,
  `conflict_flag`  TINYINT(1)   NOT NULL DEFAULT 0,
  `recusal_of`     VARCHAR(120) NOT NULL DEFAULT '',
  `reserved_authority_ref` VARCHAR(120) NOT NULL DEFAULT '',
  `conclusion_ar`  VARCHAR(600) NOT NULL DEFAULT '',
  `concluded_by`   INT          NOT NULL DEFAULT 0,
  `concluded_at`   DATETIME     NULL DEFAULT NULL,
  `referred_to`    VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'الجهة التي تستقبل الاثر لا التي تعيد التحقيق',
  `state`          VARCHAR(24)  NOT NULL DEFAULT 'mandated',
  `src_ref`        VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gin` (`company_id`, `inv_no`),
  CONSTRAINT `chk_gin_kind` CHECK (`inv_kind` IN
        ('DISCIPLINARY','INTEGRITY','OPERATIONAL_FACT','SPECIAL_INDEPENDENT')),
  CONSTRAINT `chk_gin_kind_owner` CHECK (
        (`inv_kind` = 'DISCIPLINARY'        AND `owner_dept` = 'DEP-07')
     OR (`inv_kind` = 'INTEGRITY'           AND `owner_dept` = 'DEP-08')
     OR (`inv_kind` = 'SPECIAL_INDEPENDENT' AND `owner_dept` = 'IAF')
     OR (`inv_kind` = 'OPERATIONAL_FACT'    AND `owner_dept` NOT IN ('DEP-07','DEP-08','IAF') AND `owner_dept` <> '')),
  CONSTRAINT `chk_gin_iaf_mandate` CHECK (`inv_kind` <> 'SPECIAL_INDEPENDENT' OR `mandate_doc_ref` <> ''),
  CONSTRAINT `chk_gin_denial_triage` CHECK (`origin` <> 'DENIAL' OR `triage_ref` <> ''),
  CONSTRAINT `chk_gin_origin` CHECK (`origin` IN
        ('INTEGRITY_REPORT','DENIAL','BREACH','AUDIT_FINDING','MANAGEMENT_REQUEST','OWNER_ORDER','OPERATIONAL')),
  CONSTRAINT `chk_gin_recusal` CHECK (`conflict_flag` = 0
        OR (`recusal_of` <> '' AND `reserved_authority_ref` <> '')),
  CONSTRAINT `chk_gin_self` CHECK (`investigator_id` = 0 OR `investigator_id` <> `subject_person`),
  CONSTRAINT `chk_gin_state` CHECK (`state` IN ('mandated','evidence','concluded','referred','closed')),
  CONSTRAINT `chk_gin_conclusion` CHECK (`state` NOT IN ('concluded','referred','closed')
        OR (`conclusion_ar` <> '' AND `concluded_by` <> 0)),
  CONSTRAINT `chk_gin_hands` CHECK (`concluded_by` = 0 OR `concluded_by` <> `opened_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 GOV-23 - ثلاثة انواع بثلاثة ملاك والمراجعة بتكليف مكتوب'", 'gov_investigation');

/* **حالةُ الحوكمةِ لا تُفتح لانحرافٍ تشغيليٍّ صِرف** — والأساسُ محصورٌ في
   الثمانيةِ التي سمّاها المالكُ في القرار ②. */
$run("
CREATE TABLE IF NOT EXISTS `gov_breach` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `case_no`       VARCHAR(40)  NOT NULL COMMENT 'Governance_Case_ID',
  `opened_basis`  VARCHAR(32)  NOT NULL DEFAULT '',
  `control_ref`   VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'الضابط المكسور - لا يفتح بلا ضابط',
  `policy_no`     VARCHAR(40)  NOT NULL DEFAULT '',
  `obligation_no` VARCHAR(40)  NOT NULL DEFAULT '',
  `deviation_no`  VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'مرجع الانحراف لا نسخته',
  `severity`      VARCHAR(16)  NOT NULL DEFAULT '',
  `title_ar`      VARCHAR(255) NOT NULL DEFAULT '',
  `opened_by`     INT          NOT NULL DEFAULT 0,
  `opened_at`     DATETIME     NULL DEFAULT NULL,
  `investigation_no` VARCHAR(40) NOT NULL DEFAULT '',
  `action_no`     VARCHAR(40)  NOT NULL DEFAULT '',
  `closed_by`     INT          NOT NULL DEFAULT 0,
  `closed_at`     DATETIME     NULL DEFAULT NULL,
  `close_evidence` VARCHAR(200) NOT NULL DEFAULT '',
  `state`         VARCHAR(24)  NOT NULL DEFAULT 'opened',
  `src_ref`       VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gvb` (`company_id`, `case_no`),
  KEY `ix_gvb_dev` (`deviation_no`),
  CONSTRAINT `chk_gvb_basis` CHECK (`opened_basis` IN
        ('MANDATORY_STEP_IGNORED','NO_ESCALATION','AUTHORITY_EXCEEDED','MANIPULATION',
         'CONCEALMENT','FORGERY','POLICY_BREACH','CONTROL_BROKEN')),
  CONSTRAINT `chk_gvb_control` CHECK (`control_ref` <> '' OR `policy_no` <> '' OR `obligation_no` <> ''),
  CONSTRAINT `chk_gvb_severity` CHECK (`severity` IN ('low','medium','high','critical')),
  CONSTRAINT `chk_gvb_state` CHECK (`state` IN
        ('opened','investigated','action_assigned','remediated','closed','reopened')),
  CONSTRAINT `chk_gvb_close` CHECK (`state` <> 'closed'
        OR (`action_no` <> '' AND `close_evidence` <> '' AND `closed_by` <> 0)),
  CONSTRAINT `chk_gvb_hands` CHECK (`closed_by` = 0 OR `closed_by` <> `opened_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 GOV-24 - حالة الحوكمة باساس من الثمانية ولا تفتح لانحراف صرف'", 'gov_breach');

$run("
CREATE TABLE IF NOT EXISTS `gov_corrective_action` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `action_no`    VARCHAR(40)  NOT NULL,
  `source_kind`  VARCHAR(24)  NOT NULL DEFAULT '',
  `source_ref`   VARCHAR(64)  NOT NULL DEFAULT '',
  `title_ar`     VARCHAR(255) NOT NULL DEFAULT '',
  `owner_dept`   VARCHAR(12)  NOT NULL DEFAULT '',
  `owner_person` INT          NOT NULL DEFAULT 0,
  `due_date`     DATE         NULL DEFAULT NULL,
  `assigned_by`  INT          NOT NULL DEFAULT 0,
  `evidence_ref` VARCHAR(200) NOT NULL DEFAULT '',
  `verified_by`  INT          NOT NULL DEFAULT 0,
  `verified_at`  DATETIME     NULL DEFAULT NULL,
  `escalation_level` TINYINT  NOT NULL DEFAULT 0,
  `state`        VARCHAR(24)  NOT NULL DEFAULT 'assigned',
  `src_ref`      VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gca2` (`company_id`, `action_no`),
  CONSTRAINT `chk_gac_source` CHECK (`source_kind` IN ('BREACH','INVESTIGATION','AUDIT_FINDING','RISK_TREATMENT','EXCEPTION')),
  CONSTRAINT `chk_gac_ref` CHECK (`source_ref` <> ''),
  CONSTRAINT `chk_gac_owner` CHECK (`owner_dept` <> '' AND `owner_person` <> 0 AND `due_date` IS NOT NULL),
  CONSTRAINT `chk_gac_state` CHECK (`state` IN ('assigned','in_progress','evidence_submitted','verified','closed','overdue')),
  CONSTRAINT `chk_gac_close` CHECK (`state` NOT IN ('verified','closed')
        OR (`evidence_ref` <> '' AND `verified_by` <> 0)),
  CONSTRAINT `chk_gac_hands` CHECK (`verified_by` = 0 OR `verified_by` <> `owner_person`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 GOV-25 - كل اجراء بمالك ومهلة ودليل اغلاق'", 'gov_corrective_action');

/* **متابعةُ نتائجِ المراجعةِ عند الحوكمةِ قراءةٌ لا تعديل** — الصفُّ يحمل
   **مرجعَ الملاحظةِ وخطّةَ الإدارةِ ومهلتَها** ⛔ ولا يحمل نتيجةَ المراجعةِ
   ولا تقديرَها ولا قرارَ إغلاقِها. */
$run("
CREATE TABLE IF NOT EXISTS `gov_audit_followup` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `followup_no`  VARCHAR(40)  NOT NULL,
  `finding_no`   VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'مرجع لا نسخة - والنتيجة تبقى عند المراجعة',
  `finding_source` VARCHAR(16) NOT NULL DEFAULT '' COMMENT 'داخلية ام خارجية',
  `mgmt_plan_ar` VARCHAR(600) NOT NULL DEFAULT '',
  `plan_owner_dept` VARCHAR(12) NOT NULL DEFAULT '',
  `plan_due`     DATE         NULL DEFAULT NULL,
  `recurrence_no` SMALLINT    NOT NULL DEFAULT 1,
  `action_no`    VARCHAR(40)  NOT NULL DEFAULT '',
  `follow_state` VARCHAR(24)  NOT NULL DEFAULT 'tracking',
  `src_ref`      VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gaf` (`company_id`, `followup_no`),
  KEY `ix_gaf_find` (`finding_no`),
  CONSTRAINT `chk_gaf_ref` CHECK (`finding_no` <> ''),
  CONSTRAINT `chk_gaf_src` CHECK (`finding_source` IN ('internal','external')),
  CONSTRAINT `chk_gaf_state` CHECK (`follow_state` IN ('tracking','overdue','escalated','plan_done')),
  CONSTRAINT `chk_gaf_plan` CHECK (`mgmt_plan_ar` = '' OR (`plan_owner_dept` <> '' AND `plan_due` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 GOV-26 - الحوكمة تتابع خطة الادارة ولا تملك نتيجة المراجعة'", 'gov_audit_followup');

$run("
CREATE TABLE IF NOT EXISTS `gov_committee` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `committee_code` VARCHAR(40) NOT NULL,
  `name_ar`      VARCHAR(200) NOT NULL DEFAULT '',
  `mandate_ar`   VARCHAR(600) NOT NULL DEFAULT '',
  `charter_ref`  VARCHAR(120) NOT NULL DEFAULT '',
  `chair_person` INT          NOT NULL DEFAULT 0,
  `member_count` SMALLINT     NOT NULL DEFAULT 0,
  `quorum_key`   VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'مفتاح النصاب في السجل - ولا رقم هنا',
  `meeting_cycle` VARCHAR(24) NOT NULL DEFAULT '',
  `authority_rule_id` VARCHAR(48) NOT NULL DEFAULT '',
  `state`        VARCHAR(24)  NOT NULL DEFAULT 'formed',
  `src_ref`      VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gcm` (`company_id`, `committee_code`),
  CONSTRAINT `chk_gcm_state` CHECK (`state` IN ('formed','active','suspended','dissolved')),
  CONSTRAINT `chk_gcm_cycle` CHECK (`meeting_cycle` IN ('weekly','monthly','quarterly','semiannual','annual','on_call')),
  CONSTRAINT `chk_gcm_active` CHECK (`state` <> 'active'
        OR (`charter_ref` <> '' AND `chair_person` <> 0 AND `member_count` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 GOV-30 - اللجان النافذة بتشكيلها وصلاحياتها ودوريتها'", 'gov_committee');

/* **سجلُّ أنواعِ الطلبات — قدرةٌ منصّيّةٌ تحكمها الحوكمةُ ولا تملك توجيهَها**
   (‏`DEC-OPEN-17` · قرارُ المالك ③). القاعدةُ الرباعيّةُ مكتوبةٌ في القيود:
   الحوكمةُ تحكم السجلَّ · المجالُ يملك التعريفَ · `AAM` يحلُّ سلطةَ الاعتماد ·
   والنظامُ ينفّذ التوجيه. */
$run("
CREATE TABLE IF NOT EXISTS `gov_request_type` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `type_code`      VARCHAR(48)  NOT NULL,
  `version_no`     SMALLINT     NOT NULL DEFAULT 1,
  `name_ar`        VARCHAR(200) NOT NULL DEFAULT '',
  `definition_owner_dept` VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'المجال يملك تعريف طلبه',
  `registry_governed_by`  VARCHAR(12) NOT NULL DEFAULT 'DEP-08' COMMENT 'الحوكمة تحكم السجل',
  `authority_rule_id`     VARCHAR(48) NOT NULL DEFAULT '' COMMENT 'AAM يحدد من يعتمد',
  `routing_rule_ref`      VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'النظام ينفذ التوجيه',
  `permission_policy`     VARCHAR(60) NOT NULL DEFAULT '',
  `exception_policy`      VARCHAR(60) NOT NULL DEFAULT '',
  `retired_at`     DATE         NULL DEFAULT NULL,
  `state`          VARCHAR(24)  NOT NULL DEFAULT 'draft',
  `src_ref`        VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grt` (`company_id`, `type_code`, `version_no`),
  CONSTRAINT `chk_grt_state` CHECK (`state` IN ('draft','approved','active','superseded','retired')),
  CONSTRAINT `chk_grt_gov` CHECK (`registry_governed_by` = 'DEP-08'),
  CONSTRAINT `chk_grt_domain` CHECK (`definition_owner_dept` <> '' AND `definition_owner_dept` <> 'DEP-08'),
  CONSTRAINT `chk_grt_active` CHECK (`state` <> 'active'
        OR (`authority_rule_id` <> '' AND `routing_rule_ref` <> '' AND `permission_policy` <> '')),
  CONSTRAINT `chk_grt_retired` CHECK (`state` <> 'retired' OR `retired_at` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 - سجل انواع الطلبات قدرة مركزية تحكمها الحوكمة ولا توجه يوميا'", 'gov_request_type');

echo "\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ④ إدارةُ المخاطر — الخطُّ الثاني المستقلُّ (`DEP-09`)
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **العائلاتُ الأربعُ معتمَدةٌ ولا خامسةَ**: `chk_rtx_family`.
   ◆ **والعطلُ لا يُنشئ خطرًا — يُنشئ محفِّزًا** (‏القرار ②): `rsk_trigger`،
     والمخطَّطُ **مستثنًى من محفِّزِ الأربعِ والعشرين ساعة**.
   ◆ **ولا نسخَ للمصدر**: `rsk_event` يحمل مرجعَ المصدرِ ولا حمولتَه.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "④ إدارة المخاطر — الخط الثاني المستقل ─────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `rsk_taxonomy` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `node_code`   VARCHAR(40)  NOT NULL,
  `family_code` VARCHAR(24)  NOT NULL DEFAULT '',
  `category_ar` VARCHAR(160) NOT NULL DEFAULT '',
  `type_ar`     VARCHAR(160) NOT NULL DEFAULT '',
  `parent_code` VARCHAR(40)  NOT NULL DEFAULT '',
  `depth_no`    TINYINT      NOT NULL DEFAULT 1,
  `state`       VARCHAR(16)  NOT NULL DEFAULT 'draft',
  `src_ref`     VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rtx` (`company_id`, `node_code`),
  CONSTRAINT `chk_rtx_family` CHECK (`family_code` IN
        ('OPERATIONAL','CAPITAL','CUSTOMER_CONTRACTUAL','PROCUREMENT_SUPPLY')),
  CONSTRAINT `chk_rtx_state` CHECK (`state` IN ('draft','active','retired')),
  CONSTRAINT `chk_rtx_depth` CHECK (`depth_no` BETWEEN 1 AND 3),
  CONSTRAINT `chk_rtx_parent` CHECK (`depth_no` = 1 OR `parent_code` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 RSK-02 - الشجرة الحاكمة للعائلات الاربع ولا نص حر'", 'rsk_taxonomy');

$run("
CREATE TABLE IF NOT EXISTS `rsk_trigger` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `trigger_no`    VARCHAR(40)  NOT NULL,
  `rule_code`     VARCHAR(40)  NOT NULL DEFAULT '',
  `threshold_key` VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'مفتاح العتبة في السجل - ولا رقم هنا',
  `deviation_no`  VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'مرجع الانحراف لا نسخته',
  `source_table`  VARCHAR(64)  NOT NULL DEFAULT '',
  `source_row_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `downtime_kind` VARCHAR(32)  NOT NULL DEFAULT '',
  `measured_value` DECIMAL(18,4) NULL DEFAULT NULL,
  `raised_at`     DATETIME     NULL DEFAULT NULL,
  `triaged_by`    INT          NOT NULL DEFAULT 0,
  `risk_code`     VARCHAR(40)  NOT NULL DEFAULT '',
  `state`         VARCHAR(24)  NOT NULL DEFAULT 'raised',
  `why`           VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`       VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rtg` (`company_id`, `trigger_no`),
  KEY `ix_rtg_dev` (`deviation_no`),
  CONSTRAINT `chk_rtg_rule` CHECK (`rule_code` IN
        ('UNPLANNED_24H','SIMPLE_ISSUE_3D','RECURRENCE_3X','PREVENTABLE',
         'MATERIAL_PRODUCTION_IMPACT','TECHNICAL_CAPABILITY_GAP','MATERIAL_PROCUREMENT_DELAY')),
  CONSTRAINT `chk_rtg_planned_excluded` CHECK (`rule_code` <> 'UNPLANNED_24H'
        OR `downtime_kind` NOT IN ('PLANNED_MAINTENANCE','PLANNED_OVERHAUL','CLIENT_STANDBY','OPERATIONAL_STANDBY')),
  CONSTRAINT `chk_rtg_source` CHECK (`source_table` <> '' AND `source_row_id` > 0),
  CONSTRAINT `chk_rtg_threshold` CHECK (`threshold_key` <> ''),
  CONSTRAINT `chk_rtg_state` CHECK (`state` IN ('raised','triaged','converted','dismissed')),
  CONSTRAINT `chk_rtg_converted` CHECK (`state` <> 'converted' OR `risk_code` <> ''),
  CONSTRAINT `chk_rtg_dismissed` CHECK (`state` <> 'dismissed' OR `why` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 - العطل ينشئ محفزا لا خطرا والمخطط مستثنى'", 'rsk_trigger');

$run("
CREATE TABLE IF NOT EXISTS `rsk_event` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `event_no`      VARCHAR(40)  NOT NULL,
  `risk_code`     VARCHAR(40)  NOT NULL DEFAULT '',
  `family_code`   VARCHAR(24)  NOT NULL DEFAULT '',
  `source_module` VARCHAR(40)  NOT NULL DEFAULT '',
  `source_table`  VARCHAR(64)  NOT NULL DEFAULT '',
  `source_row_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `source_ref`    VARCHAR(120) NOT NULL DEFAULT '',
  `deviation_no`  VARCHAR(40)  NOT NULL DEFAULT '',
  `event_kind`    VARCHAR(24)  NOT NULL DEFAULT '',
  `loss_amount`   DECIMAL(18,2) NULL DEFAULT NULL,
  `loss_currency` VARCHAR(8)   NOT NULL DEFAULT '',
  `occurred_at`   DATETIME     NULL DEFAULT NULL,
  `recorded_by`   INT          NOT NULL DEFAULT 0,
  `state`         VARCHAR(24)  NOT NULL DEFAULT 'recorded',
  `src_ref`       VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rev` (`company_id`, `event_no`),
  UNIQUE KEY `uq_rev_source` (`company_id`, `source_table`, `source_row_id`, `event_kind`),
  CONSTRAINT `chk_rev_source` CHECK (`source_table` <> '' AND `source_row_id` > 0 AND `source_module` <> ''),
  CONSTRAINT `chk_rev_family` CHECK (`family_code` IN
        ('OPERATIONAL','CAPITAL','CUSTOMER_CONTRACTUAL','PROCUREMENT_SUPPLY')),
  CONSTRAINT `chk_rev_kind` CHECK (`event_kind` IN ('event','near_miss','loss')),
  CONSTRAINT `chk_rev_loss` CHECK (`event_kind` <> 'loss' OR (`loss_amount` IS NOT NULL AND `loss_currency` <> '')),
  CONSTRAINT `chk_rev_state` CHECK (`state` IN ('recorded','assessed','linked','closed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 RSK-04 - حدث الخطر يقرا مصدره بمرجعه ولا ينسخه'", 'rsk_event');

$run("
CREATE TABLE IF NOT EXISTS `rsk_closure` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `closure_no`   VARCHAR(40)  NOT NULL,
  `risk_code`    VARCHAR(40)  NOT NULL DEFAULT '',
  `closure_basis` VARCHAR(32) NOT NULL DEFAULT '',
  `reassessment_ref` VARCHAR(64) NOT NULL DEFAULT '',
  `appetite_key` VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'مفتاح الشهية في السجل - ولا رقم هنا',
  `evidence_ref` VARCHAR(200) NOT NULL DEFAULT '',
  `proposed_by`  INT          NOT NULL DEFAULT 0,
  `approved_by`  INT          NOT NULL DEFAULT 0,
  `approved_at`  DATETIME     NULL DEFAULT NULL,
  `state`        VARCHAR(24)  NOT NULL DEFAULT 'proposed',
  `src_ref`      VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rcl` (`company_id`, `closure_no`),
  CONSTRAINT `chk_rcl_basis` CHECK (`closure_basis` IN
        ('RESIDUAL_WITHIN_LIMIT','CAUSE_REMOVED','SCOPE_ENDED','MERGED_INTO_OTHER')),
  CONSTRAINT `chk_rcl_risk` CHECK (`risk_code` <> ''),
  CONSTRAINT `chk_rcl_evidence` CHECK (`state` NOT IN ('approved','closed') OR `evidence_ref` <> ''),
  CONSTRAINT `chk_rcl_reassess` CHECK (`closure_basis` <> 'RESIDUAL_WITHIN_LIMIT' OR `reassessment_ref` <> ''),
  CONSTRAINT `chk_rcl_state` CHECK (`state` IN ('proposed','evidenced','approved','closed','reopened')),
  CONSTRAINT `chk_rcl_hands` CHECK (`approved_by` = 0 OR `approved_by` <> `proposed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 RSK-12 - لا يغلق الخطر الا باثبات ومن عالج لا يغلق'", 'rsk_closure');

echo "\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ المراجعةُ الداخليّةُ — الخطُّ الثالثُ المستقلّ (`IAF`)
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **الحوكمةُ لا تعطي المراجعَ نطاقَه**: `chk_ifp_scope_not_gov`.
   ◆ **ولا تغيّر نتيجةً ولا تغلقها نيابةً عنه**: عمودان يُضافان إلى
     `iaf_findings` بقيدَيهما.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑤ المراجعة الداخلية — الخط الثالث المستقل ─────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `iaf_program` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `program_no`    VARCHAR(40)  NOT NULL,
  `engagement_no` VARCHAR(40)  NOT NULL DEFAULT '',
  `step_no`       SMALLINT     NOT NULL DEFAULT 1,
  `objective_ar`  VARCHAR(400) NOT NULL DEFAULT '',
  `test_method`   VARCHAR(24)  NOT NULL DEFAULT '',
  `population_ar` VARCHAR(200) NOT NULL DEFAULT '',
  `sample_size`   INT          NOT NULL DEFAULT 0,
  `sampling_basis` VARCHAR(200) NOT NULL DEFAULT '',
  `performer_id`  INT          NOT NULL DEFAULT 0,
  `scope_set_by_dept` VARCHAR(12) NOT NULL DEFAULT 'IAF' COMMENT 'النطاق من المراجعة لا من الحوكمة',
  `reviewed_by`   INT          NOT NULL DEFAULT 0,
  `state`         VARCHAR(24)  NOT NULL DEFAULT 'drafted',
  `src_ref`       VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ifp` (`company_id`, `program_no`, `step_no`),
  CONSTRAINT `chk_ifp_scope_not_gov` CHECK (`scope_set_by_dept` = 'IAF'),
  CONSTRAINT `chk_ifp_method` CHECK (`test_method` IN ('inquiry','observation','inspection','reperformance','analytics')),
  CONSTRAINT `chk_ifp_state` CHECK (`state` IN ('drafted','approved','executing','completed')),
  CONSTRAINT `chk_ifp_objective` CHECK (`objective_ar` <> ''),
  CONSTRAINT `chk_ifp_sampling` CHECK (`state` = 'drafted' OR (`sample_size` > 0 AND `sampling_basis` <> '')),
  CONSTRAINT `chk_ifp_hands` CHECK (`reviewed_by` = 0 OR `reviewed_by` <> `performer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 IAF-07 - البرنامج يربط الهدف بالاختبار والنطاق من المراجعة'", 'iaf_program');

$run("
CREATE TABLE IF NOT EXISTS `iaf_evidence_request` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `request_no`    VARCHAR(40)  NOT NULL,
  `engagement_no` VARCHAR(40)  NOT NULL DEFAULT '',
  `program_no`    VARCHAR(40)  NOT NULL DEFAULT '',
  `auditee_dept`  VARCHAR(12)  NOT NULL DEFAULT '',
  `auditee_person` INT         NOT NULL DEFAULT 0,
  `item_ar`       VARCHAR(400) NOT NULL DEFAULT '',
  `requested_at`  DATETIME     NULL DEFAULT NULL,
  `due_date`      DATE         NULL DEFAULT NULL,
  `provided_at`   DATETIME     NULL DEFAULT NULL,
  `evidence_ref`  VARCHAR(200) NOT NULL DEFAULT '',
  `delay_days`    INT          NOT NULL DEFAULT 0,
  `escalation_level` TINYINT   NOT NULL DEFAULT 0,
  `state`         VARCHAR(24)  NOT NULL DEFAULT 'requested',
  `src_ref`       VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ifr` (`company_id`, `request_no`),
  CONSTRAINT `chk_ifr_due` CHECK (`due_date` IS NOT NULL),
  CONSTRAINT `chk_ifr_auditee` CHECK (`auditee_dept` <> '' AND `auditee_dept` <> 'IAF'),
  CONSTRAINT `chk_ifr_state` CHECK (`state` IN ('requested','provided','overdue','escalated','closed')),
  CONSTRAINT `chk_ifr_provided` CHECK (`state` NOT IN ('provided','closed')
        OR (`provided_at` IS NOT NULL AND `evidence_ref` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 IAF-08 - الدليل يطلب رسميا بمهلة والتاخر واقعة تصعد'", 'iaf_evidence_request');

$run("
CREATE TABLE IF NOT EXISTS `iaf_sample` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `sample_no`    VARCHAR(40)  NOT NULL,
  `program_no`   VARCHAR(40)  NOT NULL DEFAULT '',
  `step_no`      SMALLINT     NOT NULL DEFAULT 1,
  `item_ref`     VARCHAR(120) NOT NULL DEFAULT '',
  `source_table` VARCHAR(64)  NOT NULL DEFAULT '',
  `source_row_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `test_result`  VARCHAR(16)  NOT NULL DEFAULT '',
  `exception_ar` VARCHAR(400) NOT NULL DEFAULT '',
  `tested_by`    INT          NOT NULL DEFAULT 0,
  `tested_at`    DATETIME     NULL DEFAULT NULL,
  `finding_no`   VARCHAR(40)  NOT NULL DEFAULT '',
  `state`        VARCHAR(24)  NOT NULL DEFAULT 'drawn',
  `src_ref`      VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ifs` (`company_id`, `sample_no`),
  KEY `ix_ifs_prog` (`program_no`, `step_no`),
  CONSTRAINT `chk_ifs_result` CHECK (`test_result` IN ('','pass','exception','not_applicable')),
  CONSTRAINT `chk_ifs_state` CHECK (`state` IN ('drawn','tested','concluded')),
  CONSTRAINT `chk_ifs_tested` CHECK (`state` = 'drawn' OR (`test_result` <> '' AND `tested_by` <> 0)),
  CONSTRAINT `chk_ifs_exception` CHECK (`test_result` <> 'exception' OR `exception_ar` <> ''),
  CONSTRAINT `chk_ifs_source` CHECK (`source_table` <> '' AND `source_row_id` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 IAF-09 - العينة تسحب بمنهجية معلنة وكل مفردة بنتيجتها'", 'iaf_sample');

$run("
CREATE TABLE IF NOT EXISTS `iaf_function_risk` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `risk_no`     VARCHAR(40)  NOT NULL,
  `risk_kind`   VARCHAR(32)  NOT NULL DEFAULT '',
  `title_ar`    VARCHAR(255) NOT NULL DEFAULT '',
  `level_ar`    VARCHAR(16)  NOT NULL DEFAULT '',
  `treatment_ar` VARCHAR(400) NOT NULL DEFAULT '',
  `owner_person` INT         NOT NULL DEFAULT 0,
  `reported_to` VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'خط الرفع بالميثاق لا الادارة التنفيذية',
  `review_due`  DATE         NULL DEFAULT NULL,
  `state`       VARCHAR(24)  NOT NULL DEFAULT 'identified',
  `src_ref`     VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ifk` (`company_id`, `risk_no`),
  CONSTRAINT `chk_ifk_kind` CHECK (`risk_kind` IN
        ('INDEPENDENCE_LOSS','COMPETENCY_GAP','COVERAGE_GAP','PLAN_DELAY','QUALITY_GAP','ACCESS_DENIED')),
  CONSTRAINT `chk_ifk_state` CHECK (`state` IN ('identified','assessed','treated','closed')),
  CONSTRAINT `chk_ifk_reported` CHECK (`reported_to` IN ('','owner','audit_committee')),
  CONSTRAINT `chk_ifk_treated` CHECK (`state` NOT IN ('treated','closed') OR `treatment_ar` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W14 IAF-17 - مخاطر الوظيفة نفسها ترفع لخط الرفع بالميثاق'", 'iaf_function_risk');

echo "\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ تشديدُ استقلالِ النتيجةِ على عمودٍ حيّ — **يُقاس أوّلًا ويُردُّ إن وجد مخالفًا**
   ═══════════════════════════════════════════════════════════════════════════
   ◆ `iaf_findings` جدولٌ حيّ. **والإضافةُ وحدَها**: عمودانِ جديدانِ بقيمةٍ
     افتراضيّةٍ خاليةٍ فلا يُدهَس صفٌّ قائم، وقيدانِ يحصرانِ **من يضع النتيجةَ
     ومن يغلقها** في `IAF` وحدَها. والصفوفُ القائمةُ تبقى بقيمةٍ خاليةٍ —
     وهي مسموحةٌ في القيدِ **ومُعلَنةٌ بعددِها** لا مُدَّعاةٌ صفرًا.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑥ استقلال نتيجة المراجعة على جدول حي ──────────────────────────\n";

if (w14_tbl($conn, 'iaf_findings')) {
    $legacy = (int) w14_one($conn, "SELECT COUNT(*) FROM iaf_findings");
    echo "  ◆ صفوفٌ قائمةٌ في سجلِّ الملاحظات: $legacy — تبقى بقيمةٍ خاليةٍ مسموحةٍ في القيد\n";
    if (!w14_col($conn, 'iaf_findings', 'result_set_by_dept')) {
        $run("ALTER TABLE `iaf_findings`
                ADD COLUMN `result_set_by_dept` VARCHAR(12) NOT NULL DEFAULT ''
                    COMMENT 'من وضع النتيجة - المراجعة وحدها',
                ADD COLUMN `result_closed_by_dept` VARCHAR(12) NOT NULL DEFAULT ''
                    COMMENT 'من اغلق النتيجة - المراجعة وحدها'",
             'iaf_findings + عمودا استقلال النتيجة');
    } else { echo "  ↷ عمودا استقلالِ النتيجةِ قائمان\n"; $skip++; }

    if (!w14_one($conn, "SELECT 1 FROM information_schema.CHECK_CONSTRAINTS
                          WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'chk_iaf_result_dept'")) {
        $bad = (int) w14_one($conn, "SELECT COUNT(*) FROM iaf_findings
                                      WHERE result_set_by_dept NOT IN ('','IAF')
                                         OR result_closed_by_dept NOT IN ('','IAF')");
        if ($bad > 0) {
            echo "  ⛔ التشديدُ مردودٌ — $bad صفًّا مخالفًا قائم. القيدُ لا يُضاف على مخالفٍ حيّ.\n";
            $err++;
        } else {
            $run("ALTER TABLE `iaf_findings`
                    ADD CONSTRAINT `chk_iaf_result_dept` CHECK (`result_set_by_dept` IN ('','IAF')),
                    ADD CONSTRAINT `chk_iaf_close_dept`  CHECK (`result_closed_by_dept` IN ('','IAF'))",
                 'iaf_findings + قيدا استقلال النتيجة');
        }
    } else { echo "  ↷ قيدا استقلالِ النتيجةِ قائمان\n"; $skip++; }
} else {
    echo "  ⚠ سجلُّ الملاحظاتِ غيرُ موجود — التشديدُ يُتخطّى ويُعلَن\n"; $skip++;
}

echo "\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ⑦ إثباتُ أنَّ القيودَ حيّةٌ فعلًا — **لا تُدَّعى بوجودِ نصِّها**
   ═══════════════════════════════════════════════════════════════════════════
   ◆ MariaDB يقبل `CHECK` في `CREATE TABLE` ويُنفِّذه — لكنَّ نسخةً قد تتجاهله
     صامتةً. فكلُّ قيدٍ محوريٍّ يُختبَر **بمحاولةِ إدراجٍ مخالفةٍ تُردّ**.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑦ إثباتُ القيودِ بمحاولةٍ مخالفةٍ تُردّ ─────────────────────────\n";

$probe = function ($label, $sql, $cleanup = '') use ($conn, &$done, &$err) {
    @$conn->query($sql);
    if ($conn->errno) { echo "  ✔ $label — رُدَّ في القاعدة\n"; $done++; return true; }
    echo "  ✘ $label — **مرَّ** والقيدُ غيرُ نافذ\n"; $err++;
    if ($cleanup !== '') { @$conn->query($cleanup); }
    return false;
};

$probe('انحرافٌ مالكُه نطاقُ رقابة',
    "INSERT INTO ctl_deviation (company_id,deviation_no,owner_dept,source_table,source_row_id)
     VALUES (0,'W14PROBE-1','DEP-08','probe',1)",
    "DELETE FROM ctl_deviation WHERE deviation_no = 'W14PROBE-1'");

$probe('تصنيفٌ بلا قاعدةٍ مكتوبة',
    "INSERT INTO ctl_deviation (company_id,deviation_no,owner_dept,source_table,source_row_id,
                                classification,classified_by)
     VALUES (0,'W14PROBE-2','DEP-04','probe',1,'RISK_EXPOSURE',9)",
    "DELETE FROM ctl_deviation WHERE deviation_no = 'W14PROBE-2'");

$probe('حالةُ حوكمةٍ بأساسٍ خارجَ الثمانية',
    "INSERT INTO gov_breach (company_id,case_no,opened_basis,control_ref,severity)
     VALUES (0,'W14PROBE-3','OPERATIONAL_DEVIATION','C-1','low')",
    "DELETE FROM gov_breach WHERE case_no = 'W14PROBE-3'");

$probe('تحقيقٌ تأديبيٌّ عند الحوكمة',
    "INSERT INTO gov_investigation (company_id,inv_no,inv_kind,owner_dept,origin)
     VALUES (0,'W14PROBE-4','DISCIPLINARY','DEP-08','OPERATIONAL')",
    "DELETE FROM gov_investigation WHERE inv_no = 'W14PROBE-4'");

$probe('تحقيقٌ مستقلٌّ للمراجعةِ بلا تكليفٍ مكتوب',
    "INSERT INTO gov_investigation (company_id,inv_no,inv_kind,owner_dept,origin)
     VALUES (0,'W14PROBE-5','SPECIAL_INDEPENDENT','IAF','OWNER_ORDER')",
    "DELETE FROM gov_investigation WHERE inv_no = 'W14PROBE-5'");

$probe('تحقيقٌ من سجلِّ المنعِ بلا فرز',
    "INSERT INTO gov_investigation (company_id,inv_no,inv_kind,owner_dept,origin)
     VALUES (0,'W14PROBE-6','INTEGRITY','DEP-08','DENIAL')",
    "DELETE FROM gov_investigation WHERE inv_no = 'W14PROBE-6'");

$probe('محفِّزُ الأربعِ والعشرينَ على صيانةٍ مخطَّطة',
    "INSERT INTO rsk_trigger (company_id,trigger_no,rule_code,threshold_key,source_table,source_row_id,downtime_kind)
     VALUES (0,'W14PROBE-7','UNPLANNED_24H','k','probe',1,'PLANNED_MAINTENANCE')",
    "DELETE FROM rsk_trigger WHERE trigger_no = 'W14PROBE-7'");

$probe('حدثُ خطرٍ بلا مرجعِ مصدر',
    "INSERT INTO rsk_event (company_id,event_no,family_code,event_kind,source_module,source_table,source_row_id)
     VALUES (0,'W14PROBE-8','OPERATIONAL','event','mnt','',0)",
    "DELETE FROM rsk_event WHERE event_no = 'W14PROBE-8'");

$probe('عائلةُ خطرٍ خامسة',
    "INSERT INTO rsk_taxonomy (company_id,node_code,family_code) VALUES (0,'W14PROBE-9','SAFETY')",
    "DELETE FROM rsk_taxonomy WHERE node_code = 'W14PROBE-9'");

$probe('نطاقُ برنامجِ مراجعةٍ تحدّده الحوكمة',
    "INSERT INTO iaf_program (company_id,program_no,objective_ar,test_method,scope_set_by_dept)
     VALUES (0,'W14PROBE-10','هدف','inquiry','DEP-08')",
    "DELETE FROM iaf_program WHERE program_no = 'W14PROBE-10'");

$probe('عتبةٌ معتمَدةٌ بلا مرجعِ قرار',
    "INSERT INTO repair01_w14_thresholds (threshold_key,value_num,status,decision_ref)
     VALUES ('w14.probe.11',5,'OWNER_APPROVED','')",
    "DELETE FROM repair01_w14_thresholds WHERE threshold_key = 'w14.probe.11'");

$probe('عتبةٌ معلَّقةٌ بقيمةٍ مخترَعة',
    "INSERT INTO repair01_w14_thresholds (threshold_key,value_num,status)
     VALUES ('w14.probe.12',100000,'CONFIG_PENDING')",
    "DELETE FROM repair01_w14_thresholds WHERE threshold_key = 'w14.probe.12'");

$probe('قيمةُ اختبارٍ على عتبةٍ معتمَدة',
    "INSERT INTO repair01_w14_thresholds (threshold_key,value_num,test_value_num,status,decision_ref)
     VALUES ('w14.probe.13',5,9,'OWNER_APPROVED','X')",
    "DELETE FROM repair01_w14_thresholds WHERE threshold_key = 'w14.probe.13'");

$probe('تعاملٌ بين كيانَين بلا الخماسيِّ الكامل',
    "INSERT INTO gov_related_party (company_id,party_no,party_name,intercompany_flag)
     VALUES (0,'W14PROBE-14','طرف',1)",
    "DELETE FROM gov_related_party WHERE party_no = 'W14PROBE-14'");

$probe('سجلُّ أنواعِ الطلباتِ تملك تعريفَه الحوكمة',
    "INSERT INTO gov_request_type (company_id,type_code,name_ar,definition_owner_dept)
     VALUES (0,'W14PROBE-15','نوع','DEP-08')",
    "DELETE FROM gov_request_type WHERE type_code = 'W14PROBE-15'");

/* ⚠ **والمقامُ الخالي يُخضِرُّ التحديثَ كذبًا**: `UPDATE` على جدولٍ فارغٍ ينجح
     بصفرِ صفوفٍ ولا يُرَدّ — فيُقرأ «القيدُ غيرُ نافذ» وهو نافذ. فالمِسبارُ
     يشترط صفًّا حيًّا، وخلوُّ الجدولِ **يُعلَن ولا يُعَدُّ نجاحًا ولا فشلًا**. */
if (w14_col($conn, 'iaf_findings', 'result_set_by_dept')) {
    $liveFind = (int) w14_one($conn, "SELECT COUNT(*) FROM iaf_findings");
    if ($liveFind === 0) {
        echo "  ◆ **مقامٌ خالٍ**: لا ملاحظةَ مراجعةٍ حيّةٌ — مِسبارُ «الحوكمةُ تضع النتيجة» مُعلَنٌ لا مُشغَّل\n";
        $skip++;
    } else {
        $keep = (string) w14_one($conn, "SELECT COALESCE(result_set_by_dept,'') FROM iaf_findings ORDER BY id LIMIT 1");
        $fid  = (int) w14_one($conn, "SELECT id FROM iaf_findings ORDER BY id LIMIT 1");
        @$conn->query("UPDATE iaf_findings SET result_set_by_dept = 'DEP-08' WHERE id = $fid");
        if ($conn->errno) { echo "  ✔ الحوكمةُ تضع نتيجةَ مراجعة — رُدَّ في القاعدة\n"; $done++; }
        else {
            echo "  ✘ الحوكمةُ تضع نتيجةَ مراجعة — **مرَّ** والقيدُ غيرُ نافذ\n"; $err++;
            @$conn->query("UPDATE iaf_findings SET result_set_by_dept = '" . $conn->real_escape_string($keep)
                        . "' WHERE id = $fid");
        }
    }
}

echo "\n";

/* ═══════════════════════════════════════════════════════════════════════════
   الخلاصة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "──────────────────────────────────────────────────────────────\n";
printf("منفَّذٌ %d · مُتخطًّى %d · فشلٌ %d\n", $done, $skip, $err);
echo $err === 0 ? "الحكم: تمَّت ✔\n" : "الحكم: فشلت ✘\n";
exit($err === 0 ? 0 : 1);
