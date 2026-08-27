<?php
/**
 * 2027_12_04_repair01_w15_spaces.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W15 — **المساحاتُ والتقارير: إسقاطٌ لا مصدر**
 *
 * ◆ **الحبّةُ الحاكمة** (‏قيدُ المالك §١): هذه المرحلةُ **لا تملك حقيقةً واحدة**.
 *   ⛔ **ولا جدولَ حقيقةٍ جديدٍ هنا** — ولا نسخةً من جدولِ إدارة. وما تُنشئه هذه
 *   الهجرةُ **دفاترُ حملةٍ** (`repair01_w15_*`) لا جداولَ أعمال، **ولقطةُ أسماءِ
 *   الجداولِ قبلَ المرحلة** تُجمَّد في `repair01_w15_table_snapshot` ليقيس
 *   الحاجبُ أنَّ جداولَ الأعمالِ لم تزد **بجدولٍ واحد**.
 *
 * ◆ **والرؤيةُ لا تساوي السلطة** (‏الأمرُ الأوّل · البند 27): محورانِ منفصلانِ
 *   في `repair01_w15_scope_axis` — `visibility_rule` و`authority_rule` —
 *   و`chk_w15_axis_split` يردُّ صفًّا يجعلهما واحدًا. **فالرئيسُ يرى الشركةَ
 *   كلَّها ولا يعني ذلك أنّه ينفّذ كلَّ معاملةٍ بنفسِه.**
 *
 * ◆ **والقاعدةُ الرباعيّةُ لـ«طلباتي»** (‏`DEC-OPEN-17` معتمَد · القرار ③):
 *   الحوكمةُ تحكم السجلَّ · والنطاقُ يملك تعريفَ طلبِه · و`AAM` يحسم سلطةَ
 *   الاعتماد · والنظامُ ينفّذ التوجيه. و`gov_request_type` **قائمٌ من W14**
 *   بقيودِه الأربعة — وتُضاف إليه هنا **بنيةُ التنفيذِ بيانًا لا شيفرةً**:
 *   `owner_table` و`owner_service` و`projection_user_col`، فيقرأ المُطلِقُ
 *   **إلى أين يُنشئ** من السجلِّ ولا يحمل خريطةً في الشيفرة.
 *   و`chk_grt_binding` يردُّ نوعًا نافذًا بلا رابطةِ مالكٍ مكتملة.
 *
 * ◆ **ومساحةُ عملي `Launcher + Projection` ولا تصير `Owner`**: لا جدولَ طلباتٍ
 *   محلّيًّا يُنشأ هنا، والقراءةُ **مروحةُ دخولٍ حيّةٌ** على جداولِ المُلّاك
 *   بمفتاحِ صاحبِها — و`repair01_w15_launcher` **دفترُ قياسٍ** لا مخزنُ طلب،
 *   و`chk_w15_lnch_no_local` يردُّ أيَّ ادّعاءِ خزنٍ محلّيّ.
 *
 * ◆ **ولا قرارَ مالكٍ يُكتب نيابةً عنه** (‏قيدُ المالك §٩): `repair01_w15_deferred`
 *   يحمل ما لم يُجب عنه المالكُ ببيانِ ما بُني رغمَه، و`chk_w15_def_kind`
 *   يحصر صنفَه في `STRUCTURAL` أو `THRESHOLD`. ⛔ **ولا يُخمَّن جوابُه.**
 *
 * ◆ **والعتبةُ من السجلِّ وحدَه** (‏قيدُ المالك §٦): `repair01_w15_thresholds`
 *   بحالتَين — `OWNER_APPROVED` بنصِّ المالكِ حرفًا · `CONFIG_PENDING` بقيمةِ
 *   عدمٍ لا رقمَ مخترَع — وقيمةُ الاختبارِ في عمودٍ منفصلٍ يمنع
 *   `chk_w15_th_test_not_prod` أن تكون على عتبةٍ معتمَدة.
 *
 * ◆ **و`CHECK` لا قادِح**: مستخدمُ الهجراتِ لا يملك `SUPER`.
 * ⛔ **ولا يُمَسُّ عمودٌ حيٌّ بحذفٍ أو إعادةِ تعريف** — الإضافةُ وحدَها.
 *
 * التشغيل: php database/migrations/2027_12_04_repair01_w15_spaces.php
 *          (⛔ لا `migrate.php up` — الهجرةُ بمسارِها الكاملِ منفردةً)
 * التراجع: php database/migrations/2027_12_04_repair01_w15_spaces_down.php
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

function w15_tbl(mysqli $c, $t)
{
    $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}
function w15_col(mysqli $c, $t, $col)
{
    if (!w15_tbl($c, $t)) { return false; }
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}
function w15_chk(mysqli $c, $name)
{
    $r = $c->query("SELECT 1 FROM information_schema.CHECK_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA = DATABASE()
                       AND CONSTRAINT_NAME = '" . $c->real_escape_string($name) . "'");
    return $r && $r->num_rows > 0;
}

$done = 0; $err = 0; $skip = 0;
$run = function ($sql, $label) use ($conn, &$done, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; return true; }
    echo "  ✘ $label — " . $conn->error . "\n"; $err++; return false;
};
$note = function ($label) use (&$skip) { echo "  ↷ $label\n"; $skip++; };

echo "══ REPAIR01 · W15 — المساحاتُ والتقارير · إسقاطٌ لا مصدر ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ① دفاترُ المرحلة — دفترُ حملةٍ لا جدولُ أعمال
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① دفاترُ المرحلة ───────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w15_scope` (
  `requirement_id`   VARCHAR(48)  NOT NULL,
  `unit`             VARCHAR(160) NOT NULL DEFAULT '',
  `group_name`       VARCHAR(160) NOT NULL DEFAULT '',
  `surface`          VARCHAR(255) NOT NULL DEFAULT '',
  `space_code`       VARCHAR(12)  NOT NULL DEFAULT '',
  `anchor_screen_id` VARCHAR(12)  NOT NULL DEFAULT '',
  `anchor_route`     VARCHAR(200) NOT NULL DEFAULT '',
  `anchor_probe`     VARCHAR(96)  NOT NULL DEFAULT '',
  `backing_table`    VARCHAR(96)  NOT NULL DEFAULT '',
  `backing_owner`    VARCHAR(12)  NOT NULL DEFAULT '',
  `surface_kind`     VARCHAR(24)  NOT NULL DEFAULT '',
  `read_mode`        VARCHAR(24)  NOT NULL DEFAULT '',
  `owner_measured`   VARCHAR(12)  NOT NULL DEFAULT '',
  `owner_expected`   VARCHAR(12)  NOT NULL DEFAULT '',
  `owner_verdict`    VARCHAR(32)  NOT NULL DEFAULT '',
  `build_verdict`    VARCHAR(24)  NOT NULL DEFAULT '',
  `cycle_step`       SMALLINT     NOT NULL DEFAULT 0,
  `map_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `map_why`          VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`          VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`requirement_id`),
  KEY `ix_w15_scope_space` (`space_code`),
  CONSTRAINT `chk_w15_scope_projection`
    CHECK (`surface_kind` = '' OR `surface_kind` = 'PROJECTION'),
  CONSTRAINT `chk_w15_scope_live`
    CHECK (`read_mode` = '' OR `read_mode` = 'LIVE_REFERENCE')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W15 - نطاق المرحلة ومرساة كل متطلب'", 'repair01_w15_scope');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w15_sidebar` (
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
  COMMENT='REPAIR01 W15 - سبع خطوات السايدبار بحكم وقاعدة'", 'repair01_w15_sidebar');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w15_decisions` (
  `decision_id` VARCHAR(16)  NOT NULL,
  `question`    VARCHAR(400) NOT NULL DEFAULT '',
  `answer`      VARCHAR(400) NOT NULL DEFAULT '',
  `rationale`   VARCHAR(900) NOT NULL DEFAULT '',
  `scope_rows`  INT          NOT NULL DEFAULT 0,
  `decided_at`  DATE         NOT NULL,
  `src_ref`     VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`decision_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W15 - قرارات المرحلة'", 'repair01_w15_decisions');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w15_deferred` (
  `deferred_id`  VARCHAR(24)  NOT NULL,
  `question`     VARCHAR(500) NOT NULL DEFAULT '',
  `why_needed`   VARCHAR(500) NOT NULL DEFAULT '',
  `blocked_what` VARCHAR(400) NOT NULL DEFAULT '',
  `built_anyway` VARCHAR(600) NOT NULL DEFAULT '',
  `kind`         VARCHAR(24)  NOT NULL DEFAULT '',
  `raised_at`    DATE         NOT NULL,
  `src_ref`      VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`deferred_id`),
  CONSTRAINT `chk_w15_def_kind` CHECK (`kind` IN ('STRUCTURAL','THRESHOLD')),
  CONSTRAINT `chk_w15_def_built` CHECK (`built_anyway` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W15 - ما ينتظر قرار المالك مسجلا لا مخمنا'", 'repair01_w15_deferred');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w15_states` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity`        VARCHAR(64)  NOT NULL,
  `from_state`    VARCHAR(40)  NOT NULL,
  `to_state`      VARCHAR(40)  NOT NULL,
  `allowed`       TINYINT(1)   NOT NULL DEFAULT 1,
  `owner_role`    VARCHAR(96)  NOT NULL DEFAULT '',
  `precondition`  VARCHAR(400) NOT NULL DEFAULT '',
  `official_doc`  VARCHAR(160) NOT NULL DEFAULT '',
  `approval_gate` VARCHAR(160) NOT NULL DEFAULT '',
  `reopen_rule`   VARCHAR(300) NOT NULL DEFAULT '',
  `correct_rule`  VARCHAR(300) NOT NULL DEFAULT '',
  `forbid_why`    VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`       VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_w15_state` (`entity`, `from_state`, `to_state`),
  CONSTRAINT `chk_w15_state_forbid` CHECK (`allowed` = 1 OR `forbid_why` <> ''),
  CONSTRAINT `chk_w15_state_owner` CHECK (`allowed` = 0 OR `owner_role` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W15 - الة حالة لكل كيان في النطاق'", 'repair01_w15_states');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w15_sod` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `process_key`     VARCHAR(80)  NOT NULL,
  `process_ar`      VARCHAR(200) NOT NULL DEFAULT '',
  `initiator`       VARCHAR(96)  NOT NULL DEFAULT '',
  `reviewer`        VARCHAR(96)  NOT NULL DEFAULT '',
  `approver`        VARCHAR(96)  NOT NULL DEFAULT '',
  `executor`        VARCHAR(96)  NOT NULL DEFAULT '',
  `closer`          VARCHAR(96)  NOT NULL DEFAULT '',
  `forbidden_combo` VARCHAR(400) NOT NULL DEFAULT '',
  `authority_rule`  VARCHAR(48)  NOT NULL DEFAULT '',
  `deputy_role`     VARCHAR(96)  NOT NULL DEFAULT '',
  `scope_rule`      VARCHAR(160) NOT NULL DEFAULT '',
  `delegation`      VARCHAR(160) NOT NULL DEFAULT '',
  `effective_date`  DATE         NULL DEFAULT NULL,
  `src_ref`         VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_w15_sod` (`process_key`),
  CONSTRAINT `chk_w15_sod_combo` CHECK (`forbidden_combo` <> ''),
  CONSTRAINT `chk_w15_sod_authority` CHECK (`authority_rule` <> ''),
  CONSTRAINT `chk_w15_sod_roles`
    CHECK (`initiator` <> '' AND `reviewer` <> '' AND `approver` <> ''
           AND `executor` <> '' AND `closer` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W15 - فصل الواجبات بستة ادوار وتركيبة ممنوعة'", 'repair01_w15_sod');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w15_journey` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `leg`          VARCHAR(48)  NOT NULL DEFAULT '',
  `step_no`      SMALLINT     NOT NULL DEFAULT 0,
  `station`      VARCHAR(240) NOT NULL DEFAULT '',
  `actor_role`   VARCHAR(96)  NOT NULL DEFAULT '',
  `service_call` VARCHAR(200) NOT NULL DEFAULT '',
  `expect`       VARCHAR(400) NOT NULL DEFAULT '',
  `measured`     VARCHAR(500) NOT NULL DEFAULT '',
  `consumer`     VARCHAR(160) NOT NULL DEFAULT '',
  `effect_probe` VARCHAR(400) NOT NULL DEFAULT '',
  `verdict`      VARCHAR(24)  NOT NULL DEFAULT '',
  `ran_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_w15_journey` (`leg`, `step_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W15 - رحلة الاثبات بمحطاتها واثر كل مستهلك'", 'repair01_w15_journey');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w15_fixes` (
  `fix_id`   VARCHAR(16)  NOT NULL,
  `title`    VARCHAR(300) NOT NULL DEFAULT '',
  `found_by` VARCHAR(160) NOT NULL DEFAULT '',
  `what`     VARCHAR(700) NOT NULL DEFAULT '',
  `evidence` VARCHAR(400) NOT NULL DEFAULT '',
  `fixed_at` DATE         NOT NULL,
  PRIMARY KEY (`fix_id`),
  CONSTRAINT `chk_w15_fix_finder` CHECK (`found_by` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W15 - اصلاح بمتطلبه الكاشف'", 'repair01_w15_fixes');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w15_nav_moves` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `route`      VARCHAR(200) NOT NULL DEFAULT '',
  `move_kind`  VARCHAR(32)  NOT NULL DEFAULT '',
  `before_val` VARCHAR(255) NOT NULL DEFAULT '',
  `after_val`  VARCHAR(255) NOT NULL DEFAULT '',
  `why`        VARCHAR(400) NOT NULL DEFAULT '',
  `moved_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_w15_nav_route` (`route`),
  CONSTRAINT `chk_w15_nav_why` CHECK (`why` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W15 - كل تعطيل او نقل او اعادة تسمية بعذره المكتوب'", 'repair01_w15_nav_moves');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w15_thresholds` (
  `th_key`     VARCHAR(80)   NOT NULL,
  `title_ar`   VARCHAR(200)  NOT NULL DEFAULT '',
  `state`      VARCHAR(24)   NOT NULL DEFAULT '',
  `value_num`  DECIMAL(18,4) NULL DEFAULT NULL,
  `test_value` DECIMAL(18,4) NULL DEFAULT NULL,
  `registry`   VARCHAR(60)   NOT NULL DEFAULT '',
  `owner_text` VARCHAR(600)  NOT NULL DEFAULT '',
  `src_ref`    VARCHAR(255)  NOT NULL DEFAULT '',
  PRIMARY KEY (`th_key`),
  CONSTRAINT `chk_w15_th_state` CHECK (`state` IN ('OWNER_APPROVED','CONFIG_PENDING')),
  CONSTRAINT `chk_w15_th_pending_null` CHECK (`state` <> 'CONFIG_PENDING' OR `value_num` IS NULL),
  CONSTRAINT `chk_w15_th_approved_ref`
    CHECK (`state` <> 'OWNER_APPROVED' OR (`value_num` IS NOT NULL AND `owner_text` <> '')),
  CONSTRAINT `chk_w15_th_test_not_prod` CHECK (`test_value` IS NULL OR `state` = 'CONFIG_PENDING'),
  CONSTRAINT `chk_w15_th_registry` CHECK (`registry` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W15 - العتبة من السجل ولا رقم يخترعه مبرمج'", 'repair01_w15_thresholds');

/* ═══════════════════════════════════════════════════════════════════════════
   ② حزامُ «إسقاطٌ لا مصدر» — أربعةُ دفاترِ قياسٍ لا دعوى
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n② حزامُ الإسقاطِ ───────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w15_table_snapshot` (
  `table_name` VARCHAR(96) NOT NULL,
  `taken_at`   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W15 - لقطة اسماء الجداول قبل المرحلة مجمدة'", 'repair01_w15_table_snapshot');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w15_space_writes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_path`   VARCHAR(220) NOT NULL DEFAULT '',
  `space_code`  VARCHAR(12)  NOT NULL DEFAULT '',
  `verb`        VARCHAR(16)  NOT NULL DEFAULT '',
  `table_name`  VARCHAR(96)  NOT NULL DEFAULT '',
  `table_owner` VARCHAR(12)  NOT NULL DEFAULT '',
  `line_no`     INT          NOT NULL DEFAULT 0,
  `verdict`     VARCHAR(32)  NOT NULL DEFAULT '',
  `why`         VARCHAR(400) NOT NULL DEFAULT '',
  `scanned_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_w15_writes_space` (`space_code`, `verdict`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W15 - كل كتابة من مساحات هذه الموجة مقروءة من الشيفرة'", 'repair01_w15_space_writes');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w15_scope_axis` (
  `axis_key`        VARCHAR(64)  NOT NULL,
  `space_code`      VARCHAR(12)  NOT NULL DEFAULT '',
  `role_key`        VARCHAR(96)  NOT NULL DEFAULT '',
  `visibility_rule` VARCHAR(240) NOT NULL DEFAULT '',
  `authority_rule`  VARCHAR(240) NOT NULL DEFAULT '',
  `authority_src`   VARCHAR(60)  NOT NULL DEFAULT '',
  `delegation_src`  VARCHAR(60)  NOT NULL DEFAULT '',
  `why`             VARCHAR(400) NOT NULL DEFAULT '',
  PRIMARY KEY (`axis_key`),
  CONSTRAINT `chk_w15_axis_split`
    CHECK (`visibility_rule` <> '' AND `authority_rule` <> ''
           AND `visibility_rule` <> `authority_rule`),
  CONSTRAINT `chk_w15_axis_authority_src` CHECK (`authority_src` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W15 - الرؤية لا تساوي السلطة محوران لا محور'", 'repair01_w15_scope_axis');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w15_launcher` (
  `type_code`        VARCHAR(48)  NOT NULL,
  `name_ar`          VARCHAR(200) NOT NULL DEFAULT '',
  `definition_owner` VARCHAR(12)  NOT NULL DEFAULT '',
  `registry_gov`     VARCHAR(12)  NOT NULL DEFAULT '',
  `authority_rule`   VARCHAR(48)  NOT NULL DEFAULT '',
  `routing_rule`     VARCHAR(64)  NOT NULL DEFAULT '',
  `owner_table`      VARCHAR(96)  NOT NULL DEFAULT '',
  `owner_service`    VARCHAR(200) NOT NULL DEFAULT '',
  `projection_col`   VARCHAR(64)  NOT NULL DEFAULT '',
  `local_store`      VARCHAR(96)  NOT NULL DEFAULT '',
  `verdict`          VARCHAR(32)  NOT NULL DEFAULT '',
  `src_ref`          VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`type_code`),
  CONSTRAINT `chk_w15_lnch_no_local` CHECK (`local_store` = ''),
  CONSTRAINT `chk_w15_lnch_quad`
    CHECK (`definition_owner` <> '' AND `definition_owner` <> 'WS-MY'
           AND `registry_gov` = 'DEP-08' AND `authority_rule` <> '' AND `routing_rule` <> ''),
  CONSTRAINT `chk_w15_lnch_binding`
    CHECK (`owner_table` <> '' AND `owner_service` <> '' AND `projection_col` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W15 - القاعدة الرباعية لمطلق الطلبات قياسا'", 'repair01_w15_launcher');

/* ═══════════════════════════════════════════════════════════════════════════
   ③ رابطةُ المالكِ في السجلِّ المركزيِّ — بيانًا لا شيفرة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n③ رابطةُ المالكِ في `gov_request_type` ────────────────────────\n";

if (!w15_tbl($conn, 'gov_request_type')) {
    echo "  ✘ `gov_request_type` غيرُ موجود — شغّلْ هجرةَ W14 أوّلًا\n"; $err++;
} else {
    if (!w15_col($conn, 'gov_request_type', 'owner_table')) {
        $run("ALTER TABLE `gov_request_type`
                ADD COLUMN `owner_table` VARCHAR(96) NOT NULL DEFAULT ''
                    COMMENT 'جدول المالك الذي ينشا فيه الطلب - النظام ينفذ التوجيه'",
             'gov_request_type.owner_table');
    } else { $note('gov_request_type.owner_table قائم'); }

    if (!w15_col($conn, 'gov_request_type', 'owner_service')) {
        $run("ALTER TABLE `gov_request_type`
                ADD COLUMN `owner_service` VARCHAR(200) NOT NULL DEFAULT ''
                    COMMENT 'خدمة المالك التي تنشئ السجل - ولا يكتب المطلق مباشرة'",
             'gov_request_type.owner_service');
    } else { $note('gov_request_type.owner_service قائم'); }

    if (!w15_col($conn, 'gov_request_type', 'projection_user_col')) {
        $run("ALTER TABLE `gov_request_type`
                ADD COLUMN `projection_user_col` VARCHAR(64) NOT NULL DEFAULT ''
                    COMMENT 'عمود صاحب الطلب في جدول المالك - به تقرا مساحة عملي اسقاطها'",
             'gov_request_type.projection_user_col');
    } else { $note('gov_request_type.projection_user_col قائم'); }

    /* **ومساحةُ عملي لا تملك تعريفَ طلب** — نصُّ قرارِ المالكِ الثالثِ حرفًا:
       «مساحةُ عملي `Launcher + Projection` ولا تصبح `Owner`». والقيدُ القائمُ
       من W14 يمنع الحوكمةَ أن تملك التعريفَ **ولا يمنع مساحةَ العمل** —
       والحاجبُ يفحص **ردَّ القاعدةِ** لا نيّةَ الشيفرة. ⛔ **وإضافةٌ لا تعديلٌ
       على قيدٍ مُغلَق.** */
    if (!w15_chk($conn, 'chk_grt_not_workspace')) {
        $run("ALTER TABLE `gov_request_type`
                ADD CONSTRAINT `chk_grt_not_workspace`
                CHECK (`definition_owner_dept` <> 'WS-MY')",
             'chk_grt_not_workspace');
    } else { $note('chk_grt_not_workspace قائم'); }

    if (!w15_chk($conn, 'chk_grt_binding')) {
        $run("ALTER TABLE `gov_request_type`
                ADD CONSTRAINT `chk_grt_binding`
                CHECK (`state` <> 'active'
                       OR (`owner_table` <> '' AND `owner_service` <> ''
                           AND `projection_user_col` <> ''))",
             'chk_grt_binding');
    } else { $note('chk_grt_binding قائم'); }
}

/* ═══════════════════════════════════════════════════════════════════════════
   ④ لقطةُ الجداولِ — تُؤخَذ مرّةً وتبقى
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n④ لقطةُ جداولِ الأعمالِ قبلَ المرحلة ──────────────────────────\n";

$r = $conn->query("SELECT COUNT(*) FROM repair01_w15_table_snapshot");
$have = 0;
if ($r) { $x = $r->fetch_row(); $have = $x ? (int) $x[0] : 0; }
if ($have > 0) {
    echo "  ↷ اللقطةُ مأخوذةٌ سلفًا ($have جدولًا) — ولا تُعاد فتُمحى الحجّة\n"; $skip++;
} else {
    $n = 0;
    $r = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
                          AND TABLE_NAME NOT LIKE 'repair01\\_w15\\_%'");
    while ($r && ($x = $r->fetch_row())) {
        $conn->query("INSERT IGNORE INTO repair01_w15_table_snapshot (table_name)
                      VALUES ('" . $conn->real_escape_string($x[0]) . "')");
        $n++;
    }
    echo "  ✔ لقطةٌ مجمَّدةٌ: $n جدولًا\n"; $done++;
}

echo "\n────────────────────────────────────────────────────────────\n";
printf("منفَّذ %d · متخطًّى %d · أخطاء %d\n", $done, $skip, $err);
exit($err > 0 ? 1 : 0);
