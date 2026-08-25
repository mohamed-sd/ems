<?php
/**
 * 2027_11_23_repair01_w6_ui_purity.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W06 — **نقاءُ لغةِ الواجهة**: سجلُّ المسمّياتِ المركزيُّ ودفاترُ
 * المرحلةِ وقاموسُ عرضِ الرموزِ الداخليةِ وسجلُّ الرفض.
 *
 * ◆ **لماذا سجلُّ مسمّياتٍ لا عمودُ اسمٍ في كلِّ جدول**: المقيسُ أنَّ اسمَ
 *   الشاشةِ الواحدةِ يُقرأ اليومَ من **أربعةِ مصادرَ بأسبقيّة**
 *   (`nav_canonical.canonical_ar` ← `nav_canonical_current.cur_label` ←
 *   `nav_items.label_ar`) ومجموعتُها من خامسٍ (`link_groups.name`) —
 *   فالمصطلحُ يظهر بثلاثِ صيغٍ في ثلاثِ شاشاتٍ بلا أن يخالف أحدٌ قاعدةً.
 *   والعلاجُ **مفتاحٌ تقنيٌّ واحدٌ لكلِّ مسمًّى** يُقرأ منه المصدرُ لا العكس.
 *
 * ◆ **ولماذا `deprecated_label` و`replacement_label` عمودان لا حذف**: النصُّ
 *   القديمُ دليلٌ — بحثُ المستخدمِ عنه وروابطُه المحفوظةُ وذاكرتُه. ومحوُه
 *   يمحو القدرةَ على إثباتِ أنَّ الحيَّ لم يعد يحمله. **والحذفُ يمحو الدليلَ
 *   قبل أن يُراجَع** — وهو الحكمُ نفسُه الذي حكمته W02 على الشبح.
 *
 * ◆ **ولا `CHECK` يجعل الحاجبَ أعمى** (‏_CONTEXT §قواعد القياس ٢ · W05):
 *   ما يمنعه المخطَّطُ لا يُختبَر. فالتشكيلُ **مُتاحٌ في القاعدة** عمدًا،
 *   ويُردُّ في `UiLabelRegistry::register()` **ويُقيَّد الرفضُ في
 *   `repair01_w6_reject_log`** — فيبقى الردُّ قابلًا للكسرِ والقياسِ في
 *   الفحصِ السلبيّ، ولا يصير «أخضرَ بالبناء».
 *
 * ◆ **والعتبةُ من السجلِّ لا من الشيفرة** (‏W06 §٥): حدودُ الطولِ الأربعةُ
 *   (‏زرٌّ · حقلٌ · تبويبٌ · بندُ قائمة) صفوفٌ في `repair01_w6_thresholds`
 *   يقرؤها الفاحصُ — ولا يُكتب `if (mb_strlen($s) > 40)` في أداةٍ أبدًا.
 *
 * ◆ **وصنفُ الظهورِ عمودٌ لا اجتهاد** (‏W06 §٤-٧): `USER_VISIBLE` ·
 *   `AUDITOR_VISIBLE` · `ADMIN_VISIBLE` · `DEVELOPER_ONLY`. و`gov_screen_cycle`
 *   `.screen_title` يحمل اسمَ الملفِّ بين قوسين (٦٧٠ صفًّا) **ولا يُصيَّر في
 *   شاشةٍ** — فيُصنَّف `ADMIN_VISIBLE` ويُعذَر، لا يُنقّى بلا داعٍ ولا يُسكت عنه.
 *
 * التشغيل: php database/migrations/2027_11_23_repair01_w6_ui_purity.php
 *          (⛔ لا `migrate.php up` — الهجرةُ بمسارِها الكاملِ منفردةً)
 * التراجع: php database/migrations/2027_11_23_repair01_w6_ui_purity_down.php
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

$done = 0; $err = 0;
$run = function ($sql, $label) use ($conn, &$done, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; return true; }
    echo "  ✘ $label — " . $conn->error . "\n"; $err++; return false;
};

echo "══ REPAIR01 · W06 — نقاءُ لغةِ الواجهة ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ① سجلُّ المسمّياتِ المركزيّ — الأعمدةُ السبعةُ التي أمرت بها W06 §٤-٣
      وحولَها حَوكمتُها: مِن أين يُقرأ · مَن يملكه · بأيِّ قاعدةٍ صار كذلك.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① سجلُّ المسمّياتِ المركزيّ ───────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_ui_labels` (
  `technical_key`     VARCHAR(160) NOT NULL COMMENT 'المفتاح التقني - screen او group او action او code او state',
  `arabic_ui_label`   VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'المسمى العربي المعتمد - منقى',
  `short_label`       VARCHAR(60)  NOT NULL DEFAULT '' COMMENT 'الصيغة القصيرة للزر والتبويب',
  `allowed_context`   VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'SIDEBAR TAB BUTTON COLUMN STATE WORK_ITEM CYCLE',
  `sensitive`         TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'مسمى حساس لا يظهر لكل دور',
  `deprecated_label`  VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'الصيغة المتقاعدة - لا تحذف فهي دليل',
  `replacement_label` VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'ما حل محل المتقاعد',
  `visibility_class`  VARCHAR(20)  NOT NULL DEFAULT '' COMMENT 'USER_VISIBLE AUDITOR_VISIBLE ADMIN_VISIBLE DEVELOPER_ONLY',
  `label_state`       VARCHAR(16)  NOT NULL DEFAULT 'ACTIVE' COMMENT 'DRAFT ACTIVE DEPRECATED REPLACED',
  `source_table`      VARCHAR(64)  NOT NULL DEFAULT '',
  `source_column`     VARCHAR(64)  NOT NULL DEFAULT '',
  `source_key`        VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'مفتاح الصف في مصدره - لا اسم بلا صف يعود اليه',
  `owner_code`        VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'الادارة المالكة بالرمز المعياري',
  `rule_id`           VARCHAR(48)  NOT NULL DEFAULT '' COMMENT 'قاعدة الاشتقاق - لا قيمة بلا قاعدة',
  `src_ref`           VARCHAR(255) NOT NULL DEFAULT '',
  `origin`            VARCHAR(8)   NOT NULL DEFAULT 'W06' COMMENT 'ختم الموجة التي سجلت الصف',
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`technical_key`),
  KEY `ix_label`  (`arabic_ui_label`),
  KEY `ix_dep`    (`deprecated_label`),
  KEY `ix_state`  (`label_state`),
  KEY `ix_vis`    (`visibility_class`),
  KEY `ix_source` (`source_table`, `source_column`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W06 - سجل المسميات المركزي: مفتاح تقني واحد لكل مسمى'", 'repair01_ui_labels');

/* ═══════════════════════════════════════════════════════════════════════════
   ② قاموسُ عرضِ الرموزِ الداخلية (‏§٤-٦) — ولا يظهر الرمزُ الخامُّ في إنتاج
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n② قاموسُ عرضِ الرموزِ الداخلية ───────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w6_code_dict` (
  `raw_code`        VARCHAR(120) NOT NULL COMMENT 'الرمز الداخلي كما يكتب في القاعدة او الشيفرة',
  `display_ar`      VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'ما يراه المستخدم',
  `display_short`   VARCHAR(60)  NOT NULL DEFAULT '',
  `code_family`     VARCHAR(48)  NOT NULL DEFAULT '' COMMENT 'STATE WORKSTREAM EVENT DENY_REASON READINESS',
  `allowed_context` VARCHAR(120) NOT NULL DEFAULT '',
  `why`             VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`         VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`raw_code`),
  KEY `ix_family` (`code_family`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W06 - قاموس عرض الرموز الداخلية للمستخدم'", 'repair01_w6_code_dict');

/* ═══════════════════════════════════════════════════════════════════════════
   ③ دفترُ النطاقِ — مصادرُ النصِّ المُصيَّرِ بقياسِ ما قبلَ وما بعد
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n③ دفترُ النطاق ───────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w6_scope` (
  `source_key`       VARCHAR(80)  NOT NULL COMMENT 'الجدول.العمود',
  `source_table`     VARCHAR(64)  NOT NULL DEFAULT '',
  `source_column`    VARCHAR(64)  NOT NULL DEFAULT '',
  `row_filter`       VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'شرط المقام - المقام كامل لا مختار',
  `is_rendered`      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'يصير للمستخدم ام سجل حوكمة',
  `renderer`         VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'المستهلك بالاسم لا كل المستهلكين',
  `visibility_class` VARCHAR(20)  NOT NULL DEFAULT '',
  `rows_total`       INT NOT NULL DEFAULT 0,
  `dia_before`       INT NOT NULL DEFAULT 0,
  `dia_after`        INT NOT NULL DEFAULT 0,
  `decor_before`     INT NOT NULL DEFAULT 0,
  `decor_after`      INT NOT NULL DEFAULT 0,
  `tech_before`      INT NOT NULL DEFAULT 0,
  `tech_after`       INT NOT NULL DEFAULT 0,
  `eq_before`        INT NOT NULL DEFAULT 0,
  `eq_after`         INT NOT NULL DEFAULT 0,
  `purify_order`     INT NOT NULL DEFAULT 0 COMMENT 'ترتيب التنقية - المولد قبل المولد',
  `map_rule`         VARCHAR(48)  NOT NULL DEFAULT '',
  `map_why`          VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`          VARCHAR(255) NOT NULL DEFAULT '',
  `measured_at`      DATETIME NULL,
  PRIMARY KEY (`source_key`),
  KEY `ix_order` (`purify_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W06 - مصادر النص المصير وقياسها قبل التنقية وبعدها'", 'repair01_w6_scope');

/* ═══════════════════════════════════════════════════════════════════════════
   ④ سجلُّ إعادةِ الكتابةِ غيرِ الآليّة — المعادلاتُ والمصطلحاتُ التقنيّة.
      ولماذا سجلٌّ لا `if` في الأداة: كلُّ استبدالٍ يدويٍّ يحتاج مرجعَ صفٍّ
      وقاعدةً مسمّاةً — وإلّا صار تحريرًا بلا أثر.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n④ سجلُّ إعادةِ الكتابة ────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w6_rewrite` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_table`  VARCHAR(64)  NOT NULL DEFAULT '',
  `source_column` VARCHAR(64)  NOT NULL DEFAULT '',
  `source_key`    VARCHAR(200) NOT NULL DEFAULT '',
  `old_text`      VARCHAR(600) NOT NULL DEFAULT '',
  `new_text`      VARCHAR(600) NOT NULL DEFAULT '',
  `defect_kind`   VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'EQUATION TECH_TERM RAW_CODE DECOR LENGTH',
  `rule_id`       VARCHAR(48)  NOT NULL DEFAULT '',
  `why`           VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`       VARCHAR(255) NOT NULL DEFAULT '',
  `applied_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_src`  (`source_table`, `source_column`),
  KEY `ix_kind` (`defect_kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W06 - كل اعادة كتابة غير الية بقاعدتها ومرجعها'", 'repair01_w6_rewrite');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ سجلُّ الرفض — «تُرفَض ويُقيَّد الرفض» (‏§٦-أ) · والردُّ في الخدمةِ لا في
      المخطَّطِ حتى يبقى قابلًا للكسرِ في الفحصِ السلبيّ.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑤ سجلُّ الرفض ────────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w6_reject_log` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `technical_key` VARCHAR(160) NOT NULL DEFAULT '',
  `attempted`     VARCHAR(600) NOT NULL DEFAULT '',
  `reject_code`   VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'DIACRITICS TECH_TERM EQUATION RAW_CODE TOO_LONG NOT_REGISTERED',
  `reject_detail` VARCHAR(400) NOT NULL DEFAULT '',
  `caller`        VARCHAR(190) NOT NULL DEFAULT '',
  `actor_id`      INT UNSIGNED NOT NULL DEFAULT 0,
  `run_id`        VARCHAR(48)  NOT NULL DEFAULT '',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_code` (`reject_code`),
  KEY `ix_run`  (`run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W06 - كل محاولة تسجيل مسمى مرفوضة تقيد بسببها'", 'repair01_w6_reject_log');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ العتباتُ من السجلِّ لا من الشيفرة (‏§٥)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑥ دفترُ العتبات ──────────────────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w6_thresholds` (
  `threshold_key` VARCHAR(48)  NOT NULL COMMENT 'MAX_LEN_BUTTON MAX_LEN_FIELD MAX_LEN_TAB MAX_LEN_MENU',
  `value_no`      INT          NOT NULL DEFAULT 0,
  `unit`          VARCHAR(24)  NOT NULL DEFAULT 'حرف',
  `applies_to`    VARCHAR(120) NOT NULL DEFAULT '',
  `why`           VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`       VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`threshold_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W06 - حدود الطول تقرا من هنا ولا تكتب في اداة'", 'repair01_w6_thresholds');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑦ قراراتُ المرحلةِ ودفترُ الرحلة
      (`repair01_decisions` مُجمَّدٌ عند ١٠٨ — والنمطُ `repair01_wN_decisions`
       هو ما التزمته W01…W05.)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑦ قراراتُ المرحلةِ والرحلة ───────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w6_decisions` (
  `decision_id`  VARCHAR(24)  NOT NULL,
  `title`        VARCHAR(255) NOT NULL DEFAULT '',
  `rationale`    TEXT NULL,
  `scope_rows`   INT NOT NULL DEFAULT 0,
  `decided_by`   VARCHAR(80)  NOT NULL DEFAULT '',
  `decided_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `src_ref`      VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`decision_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W06 - قرارات المرحلة'", 'repair01_w6_decisions');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w6_journey` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id`          VARCHAR(48)  NOT NULL DEFAULT '',
  `station_no`      INT          NOT NULL DEFAULT 0,
  `station`         VARCHAR(190) NOT NULL DEFAULT '',
  `consumer`        VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'المستهلك بالاسم',
  `rendered_text`   VARCHAR(600) NOT NULL DEFAULT '' COMMENT 'النص المصير نفسه لا صف الجدول',
  `business_effect` VARCHAR(600) NOT NULL DEFAULT '',
  `passed`          TINYINT(1)   NOT NULL DEFAULT 0,
  `detail`          VARCHAR(600) NOT NULL DEFAULT '',
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_run` (`run_id`, `station_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W06 - رحلة النص بمحطاتها واثر كل مستهلك'", 'repair01_w6_journey');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑧ آلةُ حالةِ المسمّى وفصلُ الواجبات — الانتقالُ صفٌّ لا نصٌّ حرّ (§٧)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑧ آلةُ الحالةِ وفصلُ الواجبات ────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w6_states` (
  `entity`        VARCHAR(40)  NOT NULL COMMENT 'ui_label او ui_text_source',
  `from_state`    VARCHAR(24)  NOT NULL,
  `to_state`      VARCHAR(24)  NOT NULL,
  `allowed`       TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0 = ممنوع صراحة لا مسكوت عنه',
  `owner_role`    VARCHAR(48)  NOT NULL DEFAULT '',
  `precondition`  VARCHAR(400) NOT NULL DEFAULT '',
  `official_doc`  VARCHAR(190) NOT NULL DEFAULT '',
  `approval_gate` VARCHAR(120) NOT NULL DEFAULT '',
  `reopen_rule`   VARCHAR(300) NOT NULL DEFAULT '',
  `correct_rule`  VARCHAR(300) NOT NULL DEFAULT '',
  `src_ref`       VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`entity`, `from_state`, `to_state`),
  KEY `ix_allowed` (`entity`, `allowed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W06 - انتقالات مسموحة وممنوعة صراحة لكيانات المرحلة'", 'repair01_w6_states');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w6_sod` (
  `process_key`       VARCHAR(60)  NOT NULL,
  `process_name`      VARCHAR(190) NOT NULL DEFAULT '',
  `initiator_role`    VARCHAR(48)  NOT NULL DEFAULT '',
  `reviewer_role`     VARCHAR(48)  NOT NULL DEFAULT '',
  `approver_role`     VARCHAR(48)  NOT NULL DEFAULT '',
  `executor_role`     VARCHAR(48)  NOT NULL DEFAULT '',
  `closer_role`       VARCHAR(48)  NOT NULL DEFAULT '',
  `forbidden_combo`   VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'التركيبة الممنوعة صراحة',
  `authority_rule_id` VARCHAR(48)  NOT NULL DEFAULT '',
  `deputy_role`       VARCHAR(48)  NOT NULL DEFAULT '',
  `scope_rule`        VARCHAR(190) NOT NULL DEFAULT '',
  `delegation`        VARCHAR(190) NOT NULL DEFAULT '',
  `effective_date`    DATE NULL,
  `src_ref`           VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`process_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W06 - فصل الواجبات بادوار ستة وتركيبة ممنوعة'", 'repair01_w6_sod');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑨ الجولةُ الثانيةُ مصحَّحةُ النطاق (‏W06 §٤-٢ · §٤-٥) — **ملفُّ الشاشةِ مصدرًا**
      الجولةُ الأولى فحصت سبعةَ جداولَ وخرجت خضراءَ ١٨/١٨، و٨٧٢ ملفَّ شاشةٍ
      خارجَ مقامِها. وهذان الدفتران يجعلان الملفَّ مقيسًا كالجدول:
      `repair01_w6_file_log` قياسُ كلِّ ملفٍّ قبلَ وبعد ·
      `repair01_w6_coupled` **معجمُ الاقترانِ المُعلَنُ بعددِه** — نصٌّ يُقارَن
      لا يُعرَض، ونزعُ تشكيلِه في طرفٍ دون طرفٍ يفكُّ المقارنةَ **بصمت**.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑨ دفترا الملفّاتِ والاقتران ─────────────────────────────────\n";

$run("
CREATE TABLE IF NOT EXISTS `repair01_w6_file_log` (
  `rel_path`      VARCHAR(255) NOT NULL COMMENT 'مسار الملف من جذر المستودع',
  `ui_before`     INT NOT NULL DEFAULT 0 COMMENT 'علامات تشكيل في نص واجهة قبل التنقية',
  `ui_after`      INT NOT NULL DEFAULT 0,
  `coupled_marks` INT NOT NULL DEFAULT 0 COMMENT 'في موضع مقارنة او مفتاح او استعلام - تعذر',
  `excused_marks` INT NOT NULL DEFAULT 0 COMMENT 'نص واجهة يساوي مفردة مقترنة - تعذر',
  `comment_marks` INT NOT NULL DEFAULT 0 COMMENT 'تعليق شيفرة باي لغة - خارج المقام',
  `spans_changed` INT NOT NULL DEFAULT 0,
  `sha_before`    CHAR(40) NOT NULL DEFAULT '',
  `sha_after`     CHAR(40) NOT NULL DEFAULT '',
  `lint_ok`       TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'php -l بعد الكتابة',
  `run_id`        VARCHAR(48) NOT NULL DEFAULT '',
  `applied_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`rel_path`),
  KEY `ix_run` (`run_id`),
  KEY `ix_dirty` (`ui_after`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W06 - قياس كل ملف شاشة قبل التنقية وبعدها'", 'repair01_w6_file_log');

$run("
CREATE TABLE IF NOT EXISTS `repair01_w6_coupled` (
  /* ⛔ **مقارنةٌ ثنائيّةٌ لا لغويّة**: ترتيبُ `utf8mb4_unicode_ci` **يُهمل
     التشكيل** فيرى «معلَّق» و«معلّق» مفردةً واحدة — وهما في هذا الجدولِ
     مفردتانِ مختلفتان بالضبط لأنَّ الفرقَ بينهما هو موضوعُه. وبالترتيبِ
     اللغويِّ سقطت ثلاثُ مفرداتٍ من ٢٣٦ على المفتاحِ الأوّليّ **بصمت**،
     فصار المُعلَنُ أقلَّ من المُعذَر — وهو الصفرُ الكاذبُ الذي تمنعه القاعدة. */
  `term`        VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
                COMMENT 'النص المشكول الذي يقارن به - مقارنة ثنائية فالتشكيل هو الفرق',
  `couple_kind` VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'COMPARE CASE INDEX SQL JS_COMPARE JS_INDEX ENUM',
  `first_seen`  VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'اول موضع اقتران بالاسم',
  `marks`       INT NOT NULL DEFAULT 0 COMMENT 'علامات التشكيل المعفاة بسببه',
  `why`         VARCHAR(400) NOT NULL DEFAULT '',
  `owner_wave`  VARCHAR(8)   NOT NULL DEFAULT '' COMMENT 'الموجة التي تملك بيانات هذا المصطلح',
  `src_ref`     VARCHAR(255) NOT NULL DEFAULT '',
  `measured_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`term`),
  KEY `ix_kind` (`couple_kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W06 - معجم الاقتران المعلن بعدده لا المدعى صفرا'", 'repair01_w6_coupled');

/* والجدولُ إن كان قائمًا من تشغيلٍ سابقٍ بترتيبٍ لغويٍّ يُصحَّح — `CREATE IF NOT
   EXISTS` لا تغيّر قائمًا، و`ems_app` لا يملك `DROP`. والأمرُ مُعادٌ بلا أثر. */
$run("ALTER TABLE `repair01_w6_coupled`
        MODIFY `term` VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
        COMMENT 'النص المشكول الذي يقارن به - مقارنة ثنائية فالتشكيل هو الفرق'",
     'repair01_w6_coupled · ترتيبٌ ثنائيٌّ للمفتاح');

echo "\n" . str_repeat('─', 70) . "\n";
printf("نُفِّذ %d · أخطاء %d\n", $done, $err);
echo 'الحكم: ' . ($err === 0 ? "تمّت ✔\n" : "فيها خطأ ✘\n");
exit($err === 0 ? 0 : 1);
