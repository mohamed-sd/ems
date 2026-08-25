<?php
/**
 * 2027_11_18_repair01_w3_masters.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W03 — **المفاتيحُ الثلاثةَ عشرَ والكياناتُ الأمّ**.
 *
 * ◆ **لماذا سجلُّ مفاتيحَ منفصلٌ عن سجلِّ الشاشات**: حبّةُ `repair01_screen_registry`
 *   **الشاشة**، وحبّةُ هذا السجلِّ **المفتاحُ المشترَك** — والمفتاحُ الواحدُ تقرؤه
 *   عشراتُ الشاشاتِ من عشرِ إداراتٍ ولا يملكه أيٌّ منها. فكتابةُ «مالكِ المفتاح»
 *   عمودًا في سجلِّ الشاشاتِ تجعل الملكيّةَ تتكرَّر بعددِ القارئين ثم يُقاس
 *   «مالكٌ واحد» على مقامٍ ليس مقامَ المفاتيح.
 *
 * ◆ **و`repair01_key_alias` موضعُ الحكمِ لا موضعُ الحذف**: المعرّفُ البديلُ
 *   يُقاس ويُحكم ويُوصَل — ولا يُمحى (⛔ إعادةُ ترقيمٍ مدمِّرة · _CONTEXT).
 *   والحكمُ نفسُه **مقيسٌ**: `SEED_NO_REFERENT` يشترط أن يكون المقيسُ حيًّا
 *   «كلُّ الصفوفِ بذرةٌ وصفرُ قيمةٍ تجد مرجعَها» — فلا يُوسَم جدولٌ حيٌّ بذرةً.
 *
 * ◆ **و`persons` يأخذ `company_id` و`employee_id`** لا لأنّه كيانٌ جديد، بل
 *   لأنَّ المقيسَ: ١٠١ صفًّا في سجلٍّ ثانٍ للحقيقةِ نفسِها (الإنسان) بمعرّفٍ
 *   مستقلٍّ (`PERS-nnnnn`) بلا كيانٍ ولا وصلٍ بـ`employees` — بينما **٤٥ عمودًا**
 *   في النظامِ اسمُها `%person_id%` تحمل فعلًا `employees.id`. ومدَى المعرّفَين
 *   متداخلٌ (‏52..435 داخلَ 1..2181) ⇒ **الرقمُ يُقرأ صحيحًا وهو خطأ**.
 *
 * ◆ **والقادحُ إنفاذٌ لا زينة**: `DEC-OPEN-03` يقول «لا صفَّ في كيانٍ أمٍّ بلا
 *   `Company_ID`» — والقولُ في وثيقةٍ لا يمنع صفًّا. القادحُ يمنعه في القاعدة،
 *   وشاشةُ المعالجِ تمنعه قبلَ أن يصل. طبقتانِ لا واحدة.
 *
 * التشغيل: php database/migrations/2027_11_18_repair01_w3_masters.php
 *          (⛔ لا `migrate.php up` — الهجرةُ بمسارِها الكاملِ منفردةً)
 * التراجع: php database/migrations/2027_11_18_repair01_w3_masters_down.php
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

function w3_col(mysqli $c, $t, $col)
{
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

$done = 0; $had = 0; $err = 0;
$run = function ($sql, $label) use ($conn, &$done, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; return true; }
    echo "  ✘ $label — " . $conn->error . "\n"; $err++; return false;
};

echo "══ REPAIR01 · W03 — المفاتيحُ والكياناتُ الأمّ ══\n\n";

/* ═══ ① سجلُّ المفاتيحِ المشترَكة — الحبّة: مفتاحٌ واحد ═════════════════════ */
$run("
CREATE TABLE IF NOT EXISTS `repair01_key_registry` (
  `key_code`        VARCHAR(40)  NOT NULL COMMENT 'المفتاح المشترك — Company_ID …',
  `key_ar`          VARCHAR(120) NOT NULL DEFAULT '',
  `seq_no`          TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ترتيب المفتاح في الوثيقة — لا ترتيب أبجدي',
  `owner_table`     VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'الجدول المالك — واحد لا أكثر',
  `owner_column`    VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'العمود الحامل للمفتاح',
  `owner_dept`      VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'الرمز المعياري للادارة المالكة',
  `owner_screen_id` VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'SCR-nnnn الشاشة التي تنشئ المفتاح',
  `owner_rule`      VARCHAR(48)  NOT NULL DEFAULT '',
  `create_rule`     VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'قاعدة الإنشاء',
  `create_rule_src` VARCHAR(255) NOT NULL DEFAULT '',
  `read_rule`       VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'قاعدة القراءة',
  `read_rule_src`   VARCHAR(255) NOT NULL DEFAULT '',
  `company_scope`   ENUM('SCOPED','ROOT') NOT NULL DEFAULT 'SCOPED',
  `company_column`  VARCHAR(64)  NOT NULL DEFAULT '',
  `is_master`       TINYINT(1)   NOT NULL DEFAULT 1,
  `measured_rows`   INT          NOT NULL DEFAULT 0,
  `measured_at`     DATETIME     NULL DEFAULT NULL,
  `src_ref`         VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`key_code`),
  UNIQUE KEY `uq_owner` (`owner_table`, `owner_column`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W03 - سجل المفاتيح الثلاثة عشر بمالك واحد'", 'repair01_key_registry');

/* ═══ ② دفترُ المعرّفاتِ البديلة — يُحكم ويُوصَل ولا يُمحى ═════════════════ */
$run("
CREATE TABLE IF NOT EXISTS `repair01_key_alias` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_code`        VARCHAR(40)  NOT NULL,
  `alias_table`     VARCHAR(64)  NOT NULL,
  `alias_column`    VARCHAR(64)  NOT NULL DEFAULT '',
  `alias_kind`      ENUM('PARALLEL_REGISTER','LABEL_ONLY','DENORM_LABEL') NOT NULL,
  `verdict`         ENUM('LINKED','ALTERNATE_ID','SEED_NO_REFERENT','DIFFERENT_GRAIN','DENORM_LABEL') NOT NULL,
  `verdict_rule`    VARCHAR(48)  NOT NULL DEFAULT '',
  `verdict_why`     VARCHAR(400) NOT NULL DEFAULT '',
  `rows_total`      INT          NOT NULL DEFAULT 0,
  `rows_seed`       INT          NOT NULL DEFAULT 0,
  `rows_resolvable` INT          NOT NULL DEFAULT 0,
  `link_column`     VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'العمود الحامل للمفتاح بعد الوصل',
  `rows_linked`     INT          NOT NULL DEFAULT 0,
  `resolved_at`     DATETIME     NULL DEFAULT NULL,
  `wave_stage`      VARCHAR(8)   NOT NULL DEFAULT '' COMMENT 'الموجة التي تحسمه إن لم يحسم هنا',
  `src_ref`         VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alias` (`key_code`, `alias_table`, `alias_column`),
  KEY `ix_verdict` (`verdict`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W03 - المعرفات البديلة للحقيقة نفسها بحكم مقيس'", 'repair01_key_alias');

/* ═══ ③ سجلُّ الكياناتِ الأمّ — DEC-OPEN-03 ════════════════════════════════ */
$run("
CREATE TABLE IF NOT EXISTS `repair01_master_entities` (
  `entity_code`      VARCHAR(40)  NOT NULL,
  `entity_ar`        VARCHAR(160) NOT NULL DEFAULT '',
  `key_code`         VARCHAR(40)  NOT NULL DEFAULT '',
  `table_name`       VARCHAR(64)  NOT NULL DEFAULT '',
  `company_column`   VARCHAR(64)  NOT NULL DEFAULT '',
  `rows_total`       INT          NOT NULL DEFAULT 0,
  `rows_in_use`      INT          NOT NULL DEFAULT 0 COMMENT 'المقام الحاكم لـDEC-OPEN-03',
  `rows_no_company`  INT          NOT NULL DEFAULT 0,
  `rows_quarantined` INT          NOT NULL DEFAULT 0,
  `quarantine_rule`  VARCHAR(48)  NOT NULL DEFAULT '',
  `guard_kind`       ENUM('TRIGGER','NOT_NULL','APP_ONLY','NONE') NOT NULL DEFAULT 'NONE',
  `guard_evidence`   VARCHAR(255) NOT NULL DEFAULT '',
  `verdict`          VARCHAR(48)  NOT NULL DEFAULT '',
  `measured_at`      DATETIME     NULL DEFAULT NULL,
  `src_ref`          VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`entity_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W03 - الكيانات الأم وحكم DEC-OPEN-03'", 'repair01_master_entities');

/* ═══ ④ نطاقُ المرحلةِ — المتطلَّبُ إلى شاشتِه المعيارية (خطوة ٧) ══════════ */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w3_scope` (
  `requirement_id`   VARCHAR(48)  NOT NULL,
  `unit`             VARCHAR(160) NOT NULL DEFAULT '',
  `group_name`       VARCHAR(160) NOT NULL DEFAULT '',
  `surface`          VARCHAR(255) NOT NULL DEFAULT '',
  `anchor_screen_id` VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'Canonical Screen_ID أو فراغ لما لم يبن',
  `map_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `map_why`          VARCHAR(400) NOT NULL DEFAULT '',
  `wave_stage`       VARCHAR(8)   NOT NULL DEFAULT '',
  `src_ref`          VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`requirement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W03 - ربط متطلبات المرحلة بالسجل المعياري للشاشات'", 'repair01_w3_scope');

/* ═══ ⑤ دفترُ الخطواتِ السبعِ للسايدبار — خطوةٌ عمودًا وحكمُها معها ════════ */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w3_sidebar` (
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
  `measured_at`    DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`screen_id`),
  KEY `ix_owner` (`owner_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W03 - الخطوات السبع للسايدبار داخل نطاق المرحلة'", 'repair01_w3_sidebar');

/* ═══ ⑥ قراراتُ المرحلة — منفصلٌ لأنَّ `repair01_decisions` مُجمَّدٌ عند ١٠٨ ══ */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w3_decisions` (
  `decision_id` VARCHAR(24)  NOT NULL,
  `question`    VARCHAR(400) NOT NULL DEFAULT '',
  `ruling`      VARCHAR(600) NOT NULL DEFAULT '',
  `rationale`   VARCHAR(900) NULL DEFAULT NULL,
  `scope_rows`  INT          NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`decision_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W03 - قرارات المرحلة'", 'repair01_w3_decisions');

/* ═══ ⑦ رحلةُ الإثبات — محطّاتُها وأثرُ كلِّ مستهلكٍ محفوظًا ═══════════════ */
$run("
CREATE TABLE IF NOT EXISTS `repair01_w3_journey` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id`          VARCHAR(40)  NOT NULL DEFAULT '',
  `station_no`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `station`         VARCHAR(160) NOT NULL DEFAULT '',
  `key_code`        VARCHAR(40)  NOT NULL DEFAULT '',
  `consumer`        VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'المستهلك بالاسم لا كل المستهلكين',
  `expected`        VARCHAR(400) NOT NULL DEFAULT '',
  `measured`        VARCHAR(400) NOT NULL DEFAULT '',
  `business_effect` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'الأثر التجاري المقيس لا صف الحدث',
  `passed`          TINYINT(1)   NOT NULL DEFAULT 0,
  `run_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_run` (`run_id`, `station_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W03 - رحلة المفتاح: محطاتها وأثر كل مستهلك'", 'repair01_w3_journey');

/* ═══ ⑧ عقدُ الأثرِ — أعمدةٌ في `repair01_events` لا جدولٌ ثانٍ ═════════════
   الحدثُ واحدٌ وعقدُه جزءٌ منه؛ وجدولٌ ثانٍ يجعل «حدثًا بلا عقد» ممكنًا بصمت. */
$eventCols = array(
    'trigger_rule'    => "VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'المحفز - ما الذي يطلقه'",
    'min_payload'     => "VARCHAR(600) NOT NULL DEFAULT '' COMMENT 'الحمولة الدنيا'",
    'consumer_list'   => "TEXT NULL COMMENT 'كل مستهلك فعلي بالاسم'",
    'consumer_effect' => "TEXT NULL COMMENT 'أثر كل مستهلك على حدة'",
    'preconditions'   => "VARCHAR(600) NOT NULL DEFAULT '' COMMENT 'الشروط المسبقة'",
    'failure_policy'  => "VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'سلوك الفشل'",
    'compensation'    => "VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'التعويض'",
    'contract_status' => "ENUM('NONE','RECORDED') NOT NULL DEFAULT 'NONE' COMMENT 'حدث بلا عقد أثر مسجل لا ينفذ'",
    'contract_rule'   => "VARCHAR(48) NOT NULL DEFAULT ''",
    'contract_stage'  => "VARCHAR(8) NOT NULL DEFAULT '' COMMENT 'المرحلة التي كتبت العقد'",
);
foreach ($eventCols as $col => $ddl) {
    if (w3_col($conn, 'repair01_events', $col)) { echo "  ⟳ repair01_events.$col موجود\n"; $had++; continue; }
    $run("ALTER TABLE `repair01_events` ADD COLUMN `$col` $ddl", "repair01_events.$col");
}

/* ═══ ⑨ خطوةُ السايدبارِ السابعة: `nav_canonical.screen_id` ════════════════
   الربطُ بالسجلِّ المعياريِّ يحتاج عمودًا يحمله — والبندُ بلا `Screen_ID`
   يُحكَم من مسارِه، والمسارُ يتغيَّر والمُعرِّفُ لا يتغيَّر. */
if (w3_col($conn, 'nav_canonical', 'screen_id')) { echo "  ⟳ nav_canonical.screen_id موجود\n"; $had++; }
else {
    $run("ALTER TABLE `nav_canonical` ADD COLUMN `screen_id` VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'REPAIR01 - المعرف المعياري للشاشة SCR-nnnn', ADD KEY `ix_screen_id` (`screen_id`)", 'nav_canonical.screen_id');
}

/* ═══ ⑩ `persons` — الوصلُ بالمفتاحِ الأمِّ والكيانُ القانونيّ ══════════════ */
if (w3_col($conn, 'persons', 'company_id')) { echo "  ⟳ persons.company_id موجود\n"; $had++; }
else {
    $run("ALTER TABLE `persons` ADD COLUMN `company_id` INT(11) NULL DEFAULT NULL COMMENT 'DEC-OPEN-03 - لا صف في كيان أم بلا كيان قانوني' AFTER `person_id`, ADD KEY `ix_company` (`company_id`)", 'persons.company_id');
}
if (w3_col($conn, 'persons', 'employee_id')) { echo "  ⟳ persons.employee_id موجود\n"; $had++; }
else {
    $run("ALTER TABLE `persons` ADD COLUMN `employee_id` INT(11) NULL DEFAULT NULL COMMENT 'REPAIR01 W03 - Person_ID المالك employees.id' AFTER `company_id`, ADD UNIQUE KEY `uq_employee` (`employee_id`)", 'persons.employee_id');
}
if (w3_col($conn, 'persons', 'person_class')) { echo "  ⟳ persons.person_class موجود\n"; $had++; }
else {
    $run("ALTER TABLE `persons` ADD COLUMN `person_class` ENUM('WORKFORCE','IDENTITY_ONLY','UNRESOLVED') NOT NULL DEFAULT 'UNRESOLVED' COMMENT 'WORKFORCE يلزمه employee_id - IDENTITY_ONLY صف هوية معلن' AFTER `employee_id`, ADD KEY `ix_class` (`person_class`)", 'persons.person_class');
}
if (w3_col($conn, 'persons', 'w3_link_rule')) { echo "  ⟳ persons.w3_link_rule موجود\n"; $had++; }
else {
    $run("ALTER TABLE `persons` ADD COLUMN `w3_link_rule` VARCHAR(48) NOT NULL DEFAULT '' COMMENT 'قاعدة الوصل - لا قيمة بلا قاعدة'", 'persons.w3_link_rule');
}

echo "\n";
printf("الحصيلة: مُنفَّذٌ %d · موجودٌ سلفًا %d · أخطاء %d\n", $done, $had, $err);
echo ($err === 0 ? "الحكم: تمّت ✔\n" : "الحكم: أخطاء ✘\n");
$conn->close();
exit($err === 0 ? 0 : 1);
