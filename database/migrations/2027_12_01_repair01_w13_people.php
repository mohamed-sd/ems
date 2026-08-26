<?php
/**
 * 2027_12_01_repair01_w13_people.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W13 — **الموارد البشرية والبلاغات: أربعةُ أطرافٍ لا طرفان**
 *
 * ◆ **`Reporter ≠ Subject ≠ Ticket Owner ≠ Resolution Owner`** (§28): أربعةُ
 *   أطرافٍ **صفوفٌ في سجلٍّ واحدٍ بمفتاحٍ فريدٍ لكلِّ دور** — لا أربعةُ أعمدةٍ
 *   في رأسِ البلاغِ ولا `enum` من أربعٍ في جدولِ مشاركين. و`tkt_party` يحمل
 *   **مفتاحَ الفاعلِ لا اسمَه**، ومفتاحُه الفريدُ الثاني
 *   `(company, ticket, actor_kind, actor_id)` **يردُّ في القاعدةِ أن يشغلَ فاعلٌ
 *   واحدٌ دورَين** — فالدمجُ ممتنعٌ بنيويًّا لا ممنوعٌ بالنيّة.
 *
 * ◆ **والبلاغاتُ تملك دورةَ التذكرةِ ولا تملك تنفيذَ الحلّ** (§9): ثلاثةُ قيودٍ
 *   في القاعدة: `chk_tkp_res_not_crp` (‏مالكُ الحلِّ ليس `DEP-10`) ·
 *   `chk_tra_not_crp` (‏منفِّذُ الإجراءِ ليس `DEP-10`) · `chk_tkv_res_not_crp`
 *   (‏من عالج ليس `DEP-10`). ومقابلُها `chk_tkp_own_crp`: **مالكُ التذكرةِ هو
 *   البلاغاتُ وحدَها** — فالملكيّتانِ مفصولتانِ في الاتّجاهَين.
 *
 * ◆ **ولا إغلاقَ بلا تحقّق، ولا تحقّقَ من المنفِّذِ نفسِه**: `chk_tkv_close` و
 *   `chk_tkv_verifier` — والإغلاقُ الآليُّ بنافذةٍ **ممتنعٌ للحرِج**
 *   (`chk_tkv_auto_not_critical` · جوابُ `DEC-OPEN-05`).
 *
 * ◆ **والقضيةُ التأديبيّةُ عمليةٌ بمراحلِها لا حقلُ خصم** (‏`HR-17` مقابل
 *   `HR-18`): `hr_disciplinary_case` يمرُّ **واقعةً ثمَّ تحقيقًا ثمَّ قرارًا**،
 *   و`chk_hrdc_investigator` و`chk_hrdc_decider` يفصلانِ الأيديَ الثلاث،
 *   **والخصمُ يتفرّع بمرجعِ قرارِه** في `payroll_deductions` ولا يُكتب هنا.
 *
 * ◆ **وجوابُ `DEC-OPEN-16`**: التحقيقُ اختصاصٌ أصيلٌ لمالكِ موضوعِه —
 *   التأديبيُّ للموارد (`DEP-07`) والحوكميُّ للحوكمة (`DEP-08`) — والمراجعةُ
 *   الداخليّةُ (`IAF`) **بتكليفٍ موثَّقٍ حالةً بحالةٍ لا باختصاصٍ أصيل**،
 *   ويردُّ `chk_hrdc_iaf` تكليفًا بلا مستند.
 *
 * ◆ **والحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): كلُّ جدولٍ هنا يحمل
 *   `company_id` **غيرَ قابلٍ للعدم**.
 *
 * ◆ **و`CHECK` لا قادِح**: مستخدمُ الهجراتِ لا يملك `SUPER`.
 *
 * ⛔ **ولا عتبةٌ رقميّةٌ في الشيفرة** — كلُّها في `repair01_w13_thresholds`.
 * ⛔ **ولا يُمَسُّ عمودٌ حيٌّ بحذفٍ أو إعادةِ تعريف** — الإضافةُ وحدَها،
 *   والتشديدُ على عمودٍ حيٍّ **يُقاس أوّلًا ويُعلَن ويُردُّ إن وجد مخالفًا**.
 *
 * التشغيل: php database/migrations/2027_12_01_repair01_w13_people.php
 *          (⛔ لا `migrate.php up` — الهجرةُ بمسارِها الكاملِ منفردةً)
 * التراجع: php database/migrations/2027_12_01_repair01_w13_people_down.php
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

function w13_tbl(mysqli $c, $t)
{
    $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}
function w13_col(mysqli $c, $t, $col)
{
    if (!w13_tbl($c, $t)) { return false; }
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}
function w13_one(mysqli $c, $sql)
{
    $r = @$c->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x ? $x[0] : null;
}

$done = 0; $err = 0; $skip = 0;
$run = function ($sql, $label) use ($conn, &$done, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; return true; }
    echo "  ✘ $label — " . $conn->error . "\n"; $err++; return false;
};

echo "══ REPAIR01 · W13 — الموارد البشرية والبلاغات ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ① دفاترُ المرحلة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① دفاترُ المرحلة ───────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w13_scope` (
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
  `party_axis`       VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'اي طرف من الاربعة يمسه السطح ان مسه',
  `resolution_owner` VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'الادارة التي تملك تنفيذ الحل لا البلاغات',
  `map_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `map_why`          VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`          VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`requirement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W13 - نطاق المرحلة ومرساة كل متطلب'", 'repair01_w13_scope');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w13_sidebar` (
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
  COMMENT='REPAIR01 W13 - سبع خطوات السايدبار بحكم وقاعدة'", 'repair01_w13_sidebar');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w13_decisions` (
  `decision_id` VARCHAR(16)  NOT NULL,
  `question`    VARCHAR(400) NOT NULL DEFAULT '',
  `answer`      VARCHAR(400) NOT NULL DEFAULT '',
  `rationale`   VARCHAR(900) NOT NULL DEFAULT '',
  `scope_rows`  INT          NOT NULL DEFAULT 0,
  `decided_at`  DATE         NOT NULL,
  `src_ref`     VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`decision_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W13 - قرارات المرحلة'", 'repair01_w13_decisions');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w13_states` (
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
  UNIQUE KEY `uq_w13st` (`entity`, `from_state`, `to_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W13 - الات الحالة بممنوع صريح بسبب'", 'repair01_w13_states');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w13_sod` (
  `process_key`       VARCHAR(64)  NOT NULL,
  `process_name`      VARCHAR(255) NOT NULL DEFAULT '',
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
  COMMENT='REPAIR01 W13 - فصل الواجبات بستة ادوار وتركيبة ممنوعة'", 'repair01_w13_sod');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w13_thresholds` (
  `threshold_key` VARCHAR(48)    NOT NULL,
  `value_num`     DECIMAL(18,4)  NOT NULL DEFAULT 0,
  `unit_ar`       VARCHAR(48)    NOT NULL DEFAULT '',
  `title_ar`      VARCHAR(160)   NOT NULL DEFAULT '',
  `why`           VARCHAR(400)   NOT NULL DEFAULT '',
  `decision_ref`  VARCHAR(24)    NOT NULL DEFAULT '',
  `src_ref`       VARCHAR(255)   NOT NULL DEFAULT '',
  PRIMARY KEY (`threshold_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W13 - العتبات تقرا ولا تكتب'", 'repair01_w13_thresholds');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w13_fixes` (
  `fix_key`     VARCHAR(64)  NOT NULL,
  `title`       VARCHAR(255) NOT NULL DEFAULT '',
  `revealed_by` VARCHAR(48)  NOT NULL DEFAULT '',
  `before_num`  VARCHAR(80)  NOT NULL DEFAULT '',
  `after_num`   VARCHAR(80)  NOT NULL DEFAULT '',
  `why`         VARCHAR(600) NOT NULL DEFAULT '',
  `src_ref`     VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`fix_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W13 - كل اصلاح بمتطلبه الكاشف'", 'repair01_w13_fixes');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w13_journey` (
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
  KEY `ix_w13j_run` (`run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W13 - محطات رحلة الاثبات باثرها التجاري'", 'repair01_w13_journey');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w13_nav_moves` (
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
  COMMENT='REPAIR01 W13 - الموضع الاصلي لبند نقل ليعود اليه حرفا'", 'repair01_w13_nav_moves');

/* **دفترُ الأطرافِ الأربعة** — أيُّ طرفٍ يُقاس أين، وبأيِّ قيدٍ يُمنَع دمجُه.
   ◆ **ومقامُه ثابتٌ لا يخلو**: بوّابةُ قاموسِ W12 خرجت خضراءَ على العدمِ لأنَّ
     مقامَها كان القيمَ الحيّةَ وحدَها — فالأطرافُ تُعلَن هنا وتُقاس على الحيِّ
     معًا، والحاجبُ يقظٌ من أوّلِ يومٍ لا من أوّلِ صفّ. */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w13_parties` (
  `party_role`     VARCHAR(24)  NOT NULL,
  `name_ar`        VARCHAR(120) NOT NULL DEFAULT '',
  `owns`           VARCHAR(255) NOT NULL DEFAULT '',
  `never_owns`     VARCHAR(255) NOT NULL DEFAULT '',
  `key_column`     VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'المفتاح الذي يعرف الطرف لا الاسم النصي',
  `legacy_column`  VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'العمود الحي الذي كان يحمله مدموجا',
  `merge_rule`     VARCHAR(64)  NOT NULL DEFAULT '',
  `db_constraint`  VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'قيد القاعدة الذي يرد الدمج',
  `why`            VARCHAR(600) NOT NULL DEFAULT '',
  `src_ref`        VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`party_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W13 - الاطراف الاربعة وقاعدة منع دمج كل منها'", 'repair01_w13_parties');

echo "\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ② الأطرافُ الأربعةُ — سجلٌّ واحدٌ بمفتاحَين فريدَين
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **المفتاحُ الأوّل** `(company, ticket, party_role)` يقول: لكلِّ دورٍ صفٌّ
     واحدٌ لا أكثر — فلا مُبلِّغانِ ولا مالكا حلٍّ متزامنان.
   ◆ **والمفتاحُ الثاني** `(company, ticket, actor_kind, actor_id)` يقول: لا
     يشغل فاعلٌ واحدٌ دورَين في بلاغٍ واحد — **وهو عينُ `Reporter ≠ Subject ≠
     Ticket Owner ≠ Resolution Owner`** مكتوبًا في القاعدةِ لا في الوثيقة.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "② الأطرافُ الأربعةُ وكتالوجُ محلِّ البلاغ ───────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `tkt_subject_type` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `type_code`   VARCHAR(40)  NOT NULL,
  `name_ar`     VARCHAR(160) NOT NULL,
  `entity_kind` VARCHAR(16)  NOT NULL DEFAULT 'ASSET',
  `ref_table`   VARCHAR(64)  NOT NULL,
  `ref_key`     VARCHAR(64)  NOT NULL DEFAULT 'id',
  `owner_dept`  VARCHAR(12)  NOT NULL,
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `why`         VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`     VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tkst_code` (`company_id`, `type_code`),
  CONSTRAINT `chk_tkst_ref` CHECK (`ref_table` <> '' AND `ref_key` <> ''),
  CONSTRAINT `chk_tkst_kind` CHECK (`entity_kind` IN ('PERSON','ASSET','CONTRACT','SITE','ORG_UNIT','DOCUMENT'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W13 TKT-03 - كتالوج انواع محل البلاغ بسجله المرجعي'", 'tkt_subject_type');

$run("
CREATE TABLE IF NOT EXISTS `tkt_party` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`        INT UNSIGNED NOT NULL,
  `ticket_id`         INT UNSIGNED NOT NULL,
  `party_role`        VARCHAR(24)  NOT NULL,
  `actor_kind`        VARCHAR(16)  NOT NULL DEFAULT 'PERSON',
  `actor_id`          BIGINT UNSIGNED NOT NULL,
  `actor_dept`        VARCHAR(12)  NOT NULL DEFAULT '',
  `subject_type_code` VARCHAR(40)  NOT NULL DEFAULT '',
  `recorded_by`       INT UNSIGNED NOT NULL,
  `recorded_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `why`               VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`           VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tkp_role`  (`company_id`, `ticket_id`, `party_role`),
  UNIQUE KEY `uq_tkp_actor` (`company_id`, `ticket_id`, `actor_kind`, `actor_id`),
  KEY `ix_tkp_ticket` (`ticket_id`),
  CONSTRAINT `chk_tkp_role`   CHECK (`party_role` IN ('REPORTER','SUBJECT','TICKET_OWNER','RESOLUTION_OWNER')),
  CONSTRAINT `chk_tkp_kind`   CHECK (`actor_kind` IN ('PERSON','ASSET','CONTRACT','SITE','ORG_UNIT','DOCUMENT')),
  CONSTRAINT `chk_tkp_actor`  CHECK (`actor_id` > 0),
  CONSTRAINT `chk_tkp_subject_typed` CHECK (`party_role` <> 'SUBJECT' OR `subject_type_code` <> ''),
  CONSTRAINT `chk_tkp_person_roles`  CHECK (`party_role` = 'SUBJECT' OR `actor_kind` = 'PERSON'),
  CONSTRAINT `chk_tkp_own_crp`     CHECK (`party_role` <> 'TICKET_OWNER' OR `actor_dept` = 'DEP-10'),
  CONSTRAINT `chk_tkp_res_not_crp` CHECK (`party_role` <> 'RESOLUTION_OWNER' OR (`actor_dept` <> 'DEP-10' AND `actor_dept` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W13 TKT-04 - الاطراف الاربعة صفوف بمفتاحين فريدين لا اعمدة في الراس'", 'tkt_party');

echo "\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ③ السجلاتُ التابعةُ للبلاغ — كلُّ واقعةٍ سطرٌ بتاريخِه ومسؤولِه
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③ السجلاتُ التابعةُ للبلاغ ──────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `tkt_routing_history` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `ticket_id`  INT UNSIGNED NOT NULL,
  `seq_no`     SMALLINT     NOT NULL DEFAULT 1,
  `route_kind` VARCHAR(20)  NOT NULL DEFAULT 'AUTO',
  `from_dept`  VARCHAR(12)  NOT NULL DEFAULT '',
  `to_dept`    VARCHAR(12)  NOT NULL,
  `rule_ref`   VARCHAR(64)  NOT NULL DEFAULT '',
  `reason`     VARCHAR(400) NOT NULL DEFAULT '',
  `routed_by`  INT UNSIGNED NOT NULL,
  `routed_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `src_ref`    VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tkr_seq` (`company_id`, `ticket_id`, `seq_no`),
  CONSTRAINT `chk_tkr_kind`   CHECK (`route_kind` IN ('AUTO','CENTER_CORRECTION')),
  CONSTRAINT `chk_tkr_move`   CHECK (`to_dept` <> '' AND `to_dept` <> `from_dept`),
  CONSTRAINT `chk_tkr_reason` CHECK (`route_kind` <> 'CENTER_CORRECTION' OR `reason` <> ''),
  CONSTRAINT `chk_tkr_rule`   CHECK (`route_kind` <> 'AUTO' OR `rule_ref` <> ''),
  CONSTRAINT `chk_tkr_not_crp` CHECK (`to_dept` <> 'DEP-10')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W13 TKT-05 - كل توجيه سطر والالي بقاعدته والتصحيح بسببه'", 'tkt_routing_history');

$run("
CREATE TABLE IF NOT EXISTS `tkt_assignment_history` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `ticket_id`      INT UNSIGNED NOT NULL,
  `seq_no`         SMALLINT     NOT NULL DEFAULT 1,
  `from_person_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `to_person_id`   INT UNSIGNED NOT NULL,
  `to_dept`        VARCHAR(12)  NOT NULL,
  `reason`         VARCHAR(400) NOT NULL,
  `assigned_by`    INT UNSIGNED NOT NULL,
  `assigned_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `received_at`    DATETIME     NULL DEFAULT NULL,
  `src_ref`        VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tka_seq` (`company_id`, `ticket_id`, `seq_no`),
  CONSTRAINT `chk_tka_reason`  CHECK (`reason` <> ''),
  CONSTRAINT `chk_tka_person`  CHECK (`to_person_id` > 0 AND `to_person_id` <> `from_person_id`),
  CONSTRAINT `chk_tka_not_crp` CHECK (`to_dept` <> 'DEP-10' AND `to_dept` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W13 TKT-06 - كل تغيير مكلف سطر بسببه ولا مكلف بلا وقت استلام'", 'tkt_assignment_history');

$run("
CREATE TABLE IF NOT EXISTS `tkt_resolution_action` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`        INT UNSIGNED NOT NULL,
  `ticket_id`         INT UNSIGNED NOT NULL,
  `seq_no`            SMALLINT     NOT NULL DEFAULT 1,
  `executor_dept`     VARCHAR(12)  NOT NULL,
  `executor_person_id` INT UNSIGNED NOT NULL,
  `action_ar`         VARCHAR(400) NOT NULL,
  `dept_screen_ref`   VARCHAR(200) NOT NULL,
  `dept_doc_ref`      VARCHAR(120) NOT NULL DEFAULT '',
  `acted_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `src_ref`           VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tra_seq` (`company_id`, `ticket_id`, `seq_no`),
  CONSTRAINT `chk_tra_not_crp` CHECK (`executor_dept` <> 'DEP-10' AND `executor_dept` <> ''),
  CONSTRAINT `chk_tra_ref`     CHECK (`dept_screen_ref` <> ''),
  CONSTRAINT `chk_tra_action`  CHECK (`action_ar` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W13 TKT-07 - كل اجراء سطر بمرجعه في شاشة الادارة المعالجة'", 'tkt_resolution_action');

$run("
CREATE TABLE IF NOT EXISTS `tkt_verification` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `ticket_id`      INT UNSIGNED NOT NULL,
  `cycle_no`       SMALLINT     NOT NULL DEFAULT 1,
  `priority_code`  VARCHAR(16)  NOT NULL DEFAULT 'normal',
  `resolved_at`    DATETIME     NOT NULL,
  `resolved_by`    INT UNSIGNED NOT NULL,
  `resolved_dept`  VARCHAR(12)  NOT NULL,
  `window_hours`   SMALLINT     NOT NULL DEFAULT 0,
  `verify_kind`    VARCHAR(20)  NOT NULL DEFAULT '',
  `verified_at`    DATETIME     NULL DEFAULT NULL,
  `verified_by`    INT UNSIGNED NULL DEFAULT NULL,
  `closed_at`      DATETIME     NULL DEFAULT NULL,
  `closed_by`      INT UNSIGNED NULL DEFAULT NULL,
  `state`          VARCHAR(20)  NOT NULL DEFAULT 'resolved',
  `note`           VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`        VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tkv_cycle` (`company_id`, `ticket_id`, `cycle_no`),
  CONSTRAINT `chk_tkv_state`    CHECK (`state` IN ('resolved','verification','verified','closed','reopened')),
  CONSTRAINT `chk_tkv_res_not_crp` CHECK (`resolved_dept` <> 'DEP-10' AND `resolved_dept` <> ''),
  CONSTRAINT `chk_tkv_verifier` CHECK (`verified_by` IS NULL OR `verified_by` <> `resolved_by`),
  CONSTRAINT `chk_tkv_close`    CHECK (`closed_at` IS NULL OR `verified_at` IS NOT NULL),
  CONSTRAINT `chk_tkv_kind`     CHECK (`verified_at` IS NULL OR `verify_kind` IN ('REPORTER','SPECIALIST','AUTO_WINDOW')),
  CONSTRAINT `chk_tkv_auto_not_critical` CHECK (`verify_kind` <> 'AUTO_WINDOW' OR `priority_code` <> 'critical'),
  CONSTRAINT `chk_tkv_window`   CHECK (`window_hours` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W13 TKT-11 - المسار الثلاثي معالجة ثم تحقق ثم اغلاق ولا اغلاق بلا تحقق'", 'tkt_verification');

$run("
CREATE TABLE IF NOT EXISTS `tkt_reopen` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `ticket_id`      INT UNSIGNED NOT NULL,
  `seq_no`         SMALLINT     NOT NULL DEFAULT 1,
  `prior_cycle_no` SMALLINT     NOT NULL DEFAULT 1,
  `reopen_reason`  VARCHAR(24)  NOT NULL,
  `note`           VARCHAR(400) NOT NULL,
  `raised_by`      INT UNSIGNED NOT NULL,
  `raised_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `back_to_dept`   VARCHAR(12)  NOT NULL,
  `src_ref`        VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tkro_seq` (`company_id`, `ticket_id`, `seq_no`),
  CONSTRAINT `chk_tkro_reason` CHECK (`reopen_reason` IN ('REPORTER_OBJECTION','RECURRENCE')),
  CONSTRAINT `chk_tkro_note`   CHECK (`note` <> ''),
  CONSTRAINT `chk_tkro_back`   CHECK (`back_to_dept` <> 'DEP-10' AND `back_to_dept` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W13 TKT-10 - اعادة الفتح واقعة بسجلها وتعود لمساره لا لبدايته'", 'tkt_reopen');

echo "\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ④ دورةُ الموظّفِ — من الشاغرِ إلى التصفية
   ═══════════════════════════════════════════════════════════════════════════ */
echo "④ دورةُ الموظّف ────────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `hr_employee_document` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `employee_id`    INT UNSIGNED NOT NULL,
  `doc_type`       VARCHAR(40)  NOT NULL,
  `doc_no`         VARCHAR(80)  NOT NULL,
  `issued_at`      DATE         NULL DEFAULT NULL,
  `expires_at`     DATE         NULL DEFAULT NULL,
  `is_mandatory`   TINYINT(1)   NOT NULL DEFAULT 0,
  `file_ref`       VARCHAR(255) NOT NULL,
  `state`          VARCHAR(16)  NOT NULL DEFAULT 'valid',
  `replaced_by_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `note`           VARCHAR(400) NOT NULL DEFAULT '',
  `created_by`     INT UNSIGNED NOT NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `src_ref`        VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hred` (`company_id`, `employee_id`, `doc_type`, `doc_no`),
  KEY `ix_hred_exp` (`expires_at`),
  CONSTRAINT `chk_hred_file`  CHECK (`file_ref` <> ''),
  CONSTRAINT `chk_hred_state` CHECK (`state` IN ('valid','expiring','expired','replaced')),
  CONSTRAINT `chk_hred_mand`  CHECK (`is_mandatory` = 0 OR `expires_at` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W13 HR-08 - كل مستند بصلاحيته والالزامي المنتهي يعلم الملف'", 'hr_employee_document');

$run("
CREATE TABLE IF NOT EXISTS `hr_onboarding_item` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`       INT UNSIGNED NOT NULL,
  `employee_id`      INT UNSIGNED NOT NULL,
  `item_code`        VARCHAR(40)  NOT NULL,
  `item_ar`          VARCHAR(160) NOT NULL,
  `mandatory`        TINYINT(1)   NOT NULL DEFAULT 1,
  `state`            VARCHAR(16)  NOT NULL DEFAULT 'pending',
  `waiver_doc_ref`   VARCHAR(160) NOT NULL DEFAULT '',
  `custody_doc_ref`  VARCHAR(160) NOT NULL DEFAULT '',
  `done_at`          DATETIME     NULL DEFAULT NULL,
  `done_by`          INT UNSIGNED NULL DEFAULT NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `src_ref`          VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hron` (`company_id`, `employee_id`, `item_code`),
  CONSTRAINT `chk_hron_state`  CHECK (`state` IN ('pending','done','waived')),
  CONSTRAINT `chk_hron_waiver` CHECK (`state` <> 'waived' OR `waiver_doc_ref` <> ''),
  CONSTRAINT `chk_hron_done`   CHECK (`state` <> 'done' OR `done_by` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W13 HR-09 - لا مباشرة كاملة قبل اكتمال البنود او توثيق استثنائها'", 'hr_onboarding_item');

$run("
CREATE TABLE IF NOT EXISTS `hr_job_movement` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`       INT UNSIGNED NOT NULL,
  `employee_id`      INT UNSIGNED NOT NULL,
  `movement_kind`    VARCHAR(20)  NOT NULL,
  `from_position_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `to_position_id`   INT UNSIGNED NOT NULL,
  `from_org_unit_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `to_org_unit_id`   INT UNSIGNED NOT NULL DEFAULT 0,
  `effective_date`   DATE         NOT NULL,
  `doc_ref`          VARCHAR(160) NOT NULL,
  `requested_by`     INT UNSIGNED NOT NULL,
  `approved_by`      INT UNSIGNED NULL DEFAULT NULL,
  `approved_at`      DATETIME     NULL DEFAULT NULL,
  `applied_at`       DATETIME     NULL DEFAULT NULL,
  `state`            VARCHAR(16)  NOT NULL DEFAULT 'submitted',
  `note`             VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`          VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `ix_hrjm_emp` (`company_id`, `employee_id`),
  CONSTRAINT `chk_hrjm_kind`  CHECK (`movement_kind` IN ('transfer','promotion','secondment','demotion','return')),
  CONSTRAINT `chk_hrjm_doc`   CHECK (`doc_ref` <> ''),
  CONSTRAINT `chk_hrjm_state` CHECK (`state` IN ('submitted','approved','rejected','applied')),
  CONSTRAINT `chk_hrjm_appr`  CHECK (`state` NOT IN ('approved','applied') OR (`approved_by` IS NOT NULL AND `approved_by` <> `requested_by`)),
  CONSTRAINT `chk_hrjm_to`    CHECK (`to_position_id` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W13 HR-12 - النقل والترقية والانتداب حركات موثقة بموجبها واعتمادها'", 'hr_job_movement');

$run("
CREATE TABLE IF NOT EXISTS `hr_training_record` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`       INT UNSIGNED NOT NULL,
  `employee_id`      INT UNSIGNED NOT NULL,
  `program_code`     VARCHAR(40)  NOT NULL,
  `program_ar`       VARCHAR(160) NOT NULL,
  `training_kind`    VARCHAR(16)  NOT NULL DEFAULT 'technical',
  `mandatory`        TINYINT(1)   NOT NULL DEFAULT 0,
  `started_at`       DATE         NULL DEFAULT NULL,
  `completed_at`     DATE         NULL DEFAULT NULL,
  `certificate_ref`  VARCHAR(160) NOT NULL DEFAULT '',
  `valid_until`      DATE         NULL DEFAULT NULL,
  `state`            VARCHAR(16)  NOT NULL DEFAULT 'planned',
  `note`             VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`          VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hrtr` (`company_id`, `employee_id`, `program_code`, `state`),
  KEY `ix_hrtr_valid` (`valid_until`),
  CONSTRAINT `chk_hrtr_kind`  CHECK (`training_kind` IN ('safety','compliance','technical','admin')),
  CONSTRAINT `chk_hrtr_state` CHECK (`state` IN ('planned','in_progress','completed','expired','failed')),
  CONSTRAINT `chk_hrtr_cert`  CHECK (`state` <> 'completed' OR `certificate_ref` <> ''),
  CONSTRAINT `chk_hrtr_mand`  CHECK (`mandatory` = 0 OR `state` <> 'completed' OR `valid_until` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W13 HR-15 - التدريب الالزامي يتابع بانتهاء صلاحيته'", 'hr_training_record');

$run("
CREATE TABLE IF NOT EXISTS `hr_performance_review` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `employee_id`   INT UNSIGNED NOT NULL,
  `cycle_code`    VARCHAR(24)  NOT NULL,
  `review_kind`   VARCHAR(20)  NOT NULL DEFAULT 'ADMIN_PERIODIC',
  `criteria_ref`  VARCHAR(120) NOT NULL,
  `score`         DECIMAL(6,2) NULL DEFAULT NULL,
  `reviewer_id`   INT UNSIGNED NOT NULL,
  `moderator_id`  INT UNSIGNED NULL DEFAULT NULL,
  `state`         VARCHAR(16)  NOT NULL DEFAULT 'draft',
  `final_at`      DATETIME     NULL DEFAULT NULL,
  `note`          VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`       VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hrpr` (`company_id`, `employee_id`, `cycle_code`),
  CONSTRAINT `chk_hrpr_self`  CHECK (`reviewer_id` <> `employee_id`),
  CONSTRAINT `chk_hrpr_kind`  CHECK (`review_kind` = 'ADMIN_PERIODIC'),
  CONSTRAINT `chk_hrpr_state` CHECK (`state` IN ('draft','submitted','moderated','finalized','disputed')),
  CONSTRAINT `chk_hrpr_crit`  CHECK (`criteria_ref` <> ''),
  CONSTRAINT `chk_hrpr_final` CHECK (`state` <> 'finalized' OR (`score` IS NOT NULL AND `final_at` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W13 HR-16 - التقييم الوظيفي للاداريين دوري بمعاييره والتشغيلي مشتق عند القوى'", 'hr_performance_review');

/* **القضيةُ التأديبيّةُ عمليةٌ بمراحلِها لا حقلُ خصم** (‏`HR-17` مقابل `HR-18`).
   ◆ **وثلاثُ أيدٍ لا يدٌ واحدة**: من بلَّغ لا يحقّق، ومن حقّق لا يقرّر —
     `chk_hrdc_investigator` و`chk_hrdc_decider`.
   ◆ **وجوابُ `DEC-OPEN-16`**: `investigation_owner_dept` من ثلاثةٍ لا غير،
     و`IAF` **بتكليفٍ موثَّقٍ حالةً بحالةٍ** يردُّه `chk_hrdc_iaf` بلا مستند. */
$run("
CREATE TABLE IF NOT EXISTS `hr_disciplinary_case` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`          INT UNSIGNED NOT NULL,
  `case_no`             VARCHAR(40)  NOT NULL,
  `employee_id`         INT UNSIGNED NOT NULL,
  `incident_at`         DATETIME     NOT NULL,
  `incident_ar`         VARCHAR(400) NOT NULL,
  `reported_by`         INT UNSIGNED NOT NULL,
  `investigator_id`     INT UNSIGNED NULL DEFAULT NULL,
  `investigation_owner_dept` VARCHAR(12) NOT NULL DEFAULT 'DEP-07',
  `assignment_doc_ref`  VARCHAR(160) NOT NULL DEFAULT '',
  `decision_kind`       VARCHAR(16)  NOT NULL DEFAULT 'none',
  `decision_ref`        VARCHAR(160) NOT NULL DEFAULT '',
  `decided_by`          INT UNSIGNED NULL DEFAULT NULL,
  `decided_at`          DATETIME     NULL DEFAULT NULL,
  `state`               VARCHAR(16)  NOT NULL DEFAULT 'incident',
  `note`                VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`             VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hrdc_no` (`company_id`, `case_no`),
  KEY `ix_hrdc_emp` (`company_id`, `employee_id`),
  CONSTRAINT `chk_hrdc_self`   CHECK (`reported_by` <> `employee_id`),
  CONSTRAINT `chk_hrdc_state`  CHECK (`state` IN ('incident','investigation','decided','closed','appealed')),
  CONSTRAINT `chk_hrdc_kind`   CHECK (`decision_kind` IN ('none','warning','deduction','suspension','termination')),
  CONSTRAINT `chk_hrdc_owner`  CHECK (`investigation_owner_dept` IN ('DEP-07','DEP-08','IAF')),
  CONSTRAINT `chk_hrdc_iaf`    CHECK (`investigation_owner_dept` <> 'IAF' OR `assignment_doc_ref` <> ''),
  CONSTRAINT `chk_hrdc_investigator` CHECK (`investigator_id` IS NULL OR (`investigator_id` <> `reported_by` AND `investigator_id` <> `employee_id`)),
  CONSTRAINT `chk_hrdc_decider` CHECK (`decided_by` IS NULL OR (`decided_by` <> `investigator_id` AND `decided_by` <> `employee_id`)),
  CONSTRAINT `chk_hrdc_dec`    CHECK (`state` NOT IN ('decided','closed') OR (`decided_by` IS NOT NULL AND `decision_ref` <> '')),
  CONSTRAINT `chk_hrdc_inv`    CHECK (`state` NOT IN ('investigation','decided','closed') OR `investigator_id` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W13 HR-17 - القضية عملية تاديبية بمراحلها والخصم يتفرع بمرجع قرارها'", 'hr_disciplinary_case');

$run("
CREATE TABLE IF NOT EXISTS `hr_disciplinary_stage` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `case_id`    BIGINT UNSIGNED NOT NULL,
  `seq_no`     SMALLINT     NOT NULL DEFAULT 1,
  `stage`      VARCHAR(20)  NOT NULL,
  `actor_id`   INT UNSIGNED NOT NULL,
  `actor_role` VARCHAR(120) NOT NULL DEFAULT '',
  `at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `note`       VARCHAR(400) NOT NULL,
  `doc_ref`    VARCHAR(160) NOT NULL DEFAULT '',
  `src_ref`    VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hrds` (`company_id`, `case_id`, `seq_no`),
  UNIQUE KEY `uq_hrds_stage` (`company_id`, `case_id`, `stage`),
  CONSTRAINT `chk_hrds_stage` CHECK (`stage` IN ('incident','investigation','decision')),
  CONSTRAINT `chk_hrds_doc`   CHECK (`stage` <> 'decision' OR `doc_ref` <> ''),
  CONSTRAINT `chk_hrds_note`  CHECK (`note` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W13 HR-17 - مراحل القضية واقعة ثم تحقيق ثم قرار ولا قفز مرحلة'", 'hr_disciplinary_stage');

$run("
CREATE TABLE IF NOT EXISTS `hr_benefit_enrollment` (
  `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`             INT UNSIGNED NOT NULL,
  `employee_id`            INT UNSIGNED NOT NULL,
  `benefit_code`           VARCHAR(40)  NOT NULL,
  `benefit_ar`             VARCHAR(160) NOT NULL,
  `provider_ref`           VARCHAR(120) NOT NULL DEFAULT '',
  `employer_share`         DECIMAL(14,2) NOT NULL DEFAULT 0,
  `employee_share`         DECIMAL(14,2) NOT NULL DEFAULT 0,
  `currency`               VARCHAR(8)   NOT NULL DEFAULT 'SDG',
  `effective_from`         DATE         NOT NULL,
  `effective_to`           DATE         NULL DEFAULT NULL,
  `payroll_component_ref`  VARCHAR(80)  NOT NULL,
  `state`                  VARCHAR(16)  NOT NULL DEFAULT 'active',
  `note`                   VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`                VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hrbe` (`company_id`, `employee_id`, `benefit_code`, `effective_from`),
  CONSTRAINT `chk_hrbe_state` CHECK (`state` IN ('active','suspended','ended')),
  CONSTRAINT `chk_hrbe_ref`   CHECK (`payroll_component_ref` <> ''),
  CONSTRAINT `chk_hrbe_share` CHECK (`employer_share` >= 0 AND `employee_share` >= 0),
  CONSTRAINT `chk_hrbe_span`  CHECK (`effective_to` IS NULL OR `effective_to` >= `effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='W13 HR-19 - الاشتراكات النظامية بحصتيها تصب في المسير بمرجعها'", 'hr_benefit_enrollment');

echo "\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ تشديدُ الحبّةِ على أعمدةٍ حيّةٍ — **يُقاس أوّلًا ويُردُّ إن وجد مخالفًا**
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **الاستثناءُ الوحيدُ لقاعدةِ «الإضافةُ وحدَها»** (‏سابقةُ W12 §٦-٤):
     عمودٌ حيٌّ يقبل العدمَ يسمح بصفٍّ بلا كيانٍ قانونيّ. والتشديدُ **لا يقع
     على بياناتٍ مخالفة**: يُعَدُّ المخالفُ أوّلًا، وصفٌّ واحدٌ بلا كيانٍ يمنع
     التغييرَ **ويُعلنه** بدل أن يُدهَس.
   ◆ **وما لا يُشدَّد يُعلَن بعددِه** — ولا يُدَّعى صفرًا.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑤ تشديدُ حبّةِ الكيانِ على أعمدةٍ حيّة ───────────────────────────\n";

$TIGHTEN = array(
    array('workforce_requirement', 'company_id', 'INT UNSIGNED NOT NULL'),
    array('worker_evaluation',     'company_id', 'INT UNSIGNED NOT NULL'),
    array('job_titles',            'company_id', 'INT UNSIGNED NOT NULL'),
    array('ticket_communications', 'company_id', 'INT UNSIGNED NOT NULL'),
    array('worker_leave_absence',  'company_id', 'INT UNSIGNED NOT NULL'),
);
$tightened = 0; $refused = array();
foreach ($TIGHTEN as $t) {
    list($tbl, $col, $ddl) = $t;
    if (!w13_tbl($conn, $tbl) || !w13_col($conn, $tbl, $col)) {
        echo "  ⚠ $tbl.$col غير موجود — يُتخطّى\n"; $skip++; continue;
    }
    $cur = $conn->query("SHOW COLUMNS FROM `$tbl` LIKE '$col'");
    $cx  = $cur ? $cur->fetch_assoc() : null;
    if ($cx && strtoupper((string) $cx['Null']) === 'NO') { echo "  ↷ $tbl.$col مشدَّدٌ سلفًا\n"; $skip++; continue; }
    $bad = (int) w13_one($conn, "SELECT COUNT(*) FROM `$tbl` WHERE `$col` IS NULL OR `$col` = 0");
    if ($bad > 0) {
        $refused[] = "$tbl.$col ($bad صفًّا بلا كيان)";
        echo "  ⛔ $tbl.$col — $bad صفًّا بلا كيانٍ يمنع التشديدَ ويُعلَن\n"; $skip++; continue;
    }
    if ($conn->query("ALTER TABLE `$tbl` MODIFY `$col` $ddl") === true) {
        echo "  ✔ $tbl.$col شُدِّد على صفرِ مخالف\n"; $done++; $tightened++;
    } else { echo "  ✘ $tbl.$col — " . $conn->error . "\n"; $err++; }
}

/* **وثلاثةُ أعمدةٍ لا تُشدَّد اليوم ويُعلَن عددُها** — صفوفُها القائمةُ بلا
   كيانٍ سابقةٌ لهذه المرحلة، والتشديدُ عليها دهسٌ لبياناتٍ حيّةٍ لا إصلاحُ حبّة. */
$DECLARED = array('employees', 'ticket_escalations', 'ticket_participants', 'ticket_responses');
$declaredN = 0;
foreach ($DECLARED as $tbl) {
    if (!w13_tbl($conn, $tbl) || !w13_col($conn, $tbl, 'company_id')) { continue; }
    $n = (int) w13_one($conn, "SELECT COUNT(*) FROM `$tbl` WHERE `company_id` IS NULL");
    $declaredN += $n;
    echo "  ◆ $tbl.company_id — صفوفٌ بلا كيانٍ $n · مُعلَنٌ بعددِه في W13-D-06 لا مُشدَّد\n";
}
printf("  شُدِّد %d · رُفض تشديدُه %d%s · مُعلَنٌ بعددِه %d صفًّا\n\n",
    $tightened, count($refused), $refused ? ' ⇐ ' . implode('، ', $refused) : '', $declaredN);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ إثباتُ أنَّ القيودَ حيّةٌ فعلًا — **لا تُدَّعى بوجودِ نصِّها**
   ═══════════════════════════════════════════════════════════════════════════
   ◆ MariaDB يقبل `CHECK` في `CREATE TABLE` ويُنفِّذه — لكنَّ نسخةً قد تتجاهله
     صامتةً. فالهجرةُ **تُحاول كتابةً مخالفةً وتشترط أن تُرَدَّ** ثمَّ تكنس.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑥ إثباتُ القيودِ بمحاولةٍ مخالفةٍ تُردّ ─────────────────────────\n";
$PROBE = array(
    array('tkt_party', 'chk_tkp_res_not_crp',
          "INSERT INTO tkt_party (company_id,ticket_id,party_role,actor_kind,actor_id,actor_dept,recorded_by)
           VALUES (999999,999999,'RESOLUTION_OWNER','PERSON',1,'DEP-10',1)"),
    array('tkt_resolution_action', 'chk_tra_not_crp',
          "INSERT INTO tkt_resolution_action (company_id,ticket_id,seq_no,executor_dept,executor_person_id,action_ar,dept_screen_ref)
           VALUES (999999,999999,1,'DEP-10',1,'محاولة','x.php')"),
    array('tkt_verification', 'chk_tkv_close',
          "INSERT INTO tkt_verification (company_id,ticket_id,cycle_no,resolved_at,resolved_by,resolved_dept,window_hours,closed_at)
           VALUES (999999,999999,1,NOW(),1,'DEP-14',72,NOW())"),
    array('hr_disciplinary_case', 'chk_hrdc_iaf',
          "INSERT INTO hr_disciplinary_case (company_id,case_no,employee_id,incident_at,incident_ar,reported_by,investigation_owner_dept)
           VALUES (999999,'W13PROBE',1,NOW(),'محاولة',2,'IAF')"),
);
$liveChk = 0; $deadChk = array();
foreach ($PROBE as $pr) {
    list($tbl, $name, $sql) = $pr;
    if (!w13_tbl($conn, $tbl)) { continue; }
    $ok = @$conn->query($sql);
    if ($ok === true) {
        $deadChk[] = $name;
        echo "  ✘ $name — القيدُ لم يردَّ المخالفَ فهو معطَّل\n"; $err++;
        @$conn->query("DELETE FROM `$tbl` WHERE company_id = 999999");
    } else {
        echo "  ✔ $name ردَّ المخالفَ\n"; $liveChk++;
    }
}
printf("  قيودٌ حيّةٌ مُثبَتة %d · معطَّلةٌ %d%s\n\n", $liveChk, count($deadChk),
    $deadChk ? ' ⇐ ' . implode('، ', $deadChk) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   الخلاصة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "──────────────────────────────────────────────────────────────\n";
printf("منفَّذٌ %d · متخطًّى %d · فشل %d\n", $done, $skip, $err);
echo $err === 0 ? "الحكم: تمَّت ✔\n" : "الحكم: فشلت ✘\n";
exit($err === 0 ? 0 : 1);
