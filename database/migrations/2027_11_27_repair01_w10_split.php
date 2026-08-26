<?php
/**
 * 2027_11_27_repair01_w10_split.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W10 — **شقُّ المالية والخزينة · وشقُّ مكتب الرئيس والنواب**
 *
 * ◆ **الشقُّ قرارُ ملكيّةٍ لا إعادةُ ترقيم**: لا مفتاحَ تقنيٌّ يُمَسّ، ولا مفتاحَ
 *   أجنبيٌّ يُعاد، ولا سجلَّ تدقيقٍ يُحرَّر، ولا رابطَ قديمٌ يُدهَس. **الجسرُ
 *   يترجم** — و`repair01_w10_bridge` يحمل كلَّ مؤشِّرٍ حيٍّ يسمّي الوحدةَ الأمَّ
 *   باسمِها القديمِ مع الشقِّ الذي يحلُّه، ويُبقي النصَّ الحيَّ كما هو حرفًا.
 *
 * ◆ **والعطبُ المقيسُ الذي تُصلحه هذه المرحلة**: `repair01_w2_apply.php` كان
 *   يبني خريطةَ الجسرِ `$cross[legacy_name] = canonical_code` — **ومفتاحُ
 *   الوحدةِ المشقوقةِ مكرَّرٌ صفَّين**. فالصفُّ الثاني يدهس الأوّلَ، ويصير كلُّ
 *   سطحٍ مالكُه «المالية والخزينة» **خزينةً بترتيبِ الصفوفِ لا بمعناه**.
 *   **والشقُّ غيرُ المحسومِ يُحسَم في الشيفرةِ صامتًا إن لم يُحسَم في السجلّ.**
 *
 * ◆ **ومفرداتُ الشقِّ من المخزنِ لا من قائمةٍ مكتوبةٍ في ملفّ**:
 *   `repair01_w10_vocab` يُشتَقُّ من ثلاثةِ مصادرَ مرتَّبةِ الأسبقيّة —
 *   سطحُ المتطلَّبِ (`repair01_requirements` وحدتا 05 و06) · بندُ قاعدةِ الشقِّ
 *   (`repair01_dept_crosswalk.split_rule`) · اسمُ الإدارةِ المعياريّ
 *   (`repair01_departments.name_ar`). ⛔ **ولا قائمةَ أسماءِ ملفّاتٍ في الشيفرة**
 *   — وهي صيغةُ W01 التي تُستبدَل هنا بسجلٍّ يُقرأ.
 *
 * ◆ **و`CHECK` لا قادِح**: مستخدمُ الهجراتِ لا يملك `SUPER` (‏W03 · 2027_11_19).
 *
 * ⛔ **ولا رقمَ صلبٌ في الشيفرة** — أوزانُ الأسبقيّةِ في `repair01_w10_thresholds`.
 *
 * التشغيل: php database/migrations/2027_11_27_repair01_w10_split.php
 *          (⛔ لا `migrate.php up` — الهجرةُ بمسارِها الكاملِ منفردةً)
 * التراجع: php database/migrations/2027_11_27_repair01_w10_split_down.php
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

echo "═══ REPAIR01 · W10 — سجلّاتُ الشقّ ═══\n\n";
echo "① مفرداتُ الشقِّ المشتقّةُ من المخزن\n";
$run("CREATE TABLE IF NOT EXISTS `repair01_w10_vocab` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `token` VARCHAR(190) NOT NULL COMMENT 'المفردة كما وردت في المخزن',
        `token_norm` VARCHAR(190) NOT NULL COMMENT 'المفردة بعد التطبيع',
        `word_count` TINYINT UNSIGNED NOT NULL DEFAULT 1,
        `side` VARCHAR(12) NOT NULL COMMENT 'الشق الذي تدل عليه المفردة',
        `weight` INT NOT NULL DEFAULT 0 COMMENT 'وزن المصدر زائد طول المفردة',
        `src_kind` VARCHAR(40) NOT NULL COMMENT 'REQUIREMENT_SURFACE او CROSSWALK_CLAUSE او DEPARTMENT_NAME',
        `src_ref` VARCHAR(190) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_w10voc` (`token_norm`, `side`),
        KEY `ix_w10voc_side` (`side`),
        CONSTRAINT `chk_w10voc_full` CHECK (`token_norm` <> '' AND `side` <> '' AND `src_kind` <> '' AND `src_ref` <> '')
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='W10 مفردات الشق مشتقة من المخزن لا مكتوبة في شيفرة'", 'repair01_w10_vocab');

echo "\n② أوزانُ الأسبقيّةِ سجلًّا يُقرأ\n";
$run("CREATE TABLE IF NOT EXISTS `repair01_w10_thresholds` (
        `threshold_key` VARCHAR(64) NOT NULL,
        `value_num` INT NOT NULL,
        `why` VARCHAR(400) NOT NULL,
        `ref` VARCHAR(190) NOT NULL,
        PRIMARY KEY (`threshold_key`),
        CONSTRAINT `chk_w10th_full` CHECK (`why` <> '' AND `ref` <> '')
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='W10 كل رقم يقارن به يقرا من هنا'", 'repair01_w10_thresholds');

echo "\n③ دفترُ الشقِّ — صفٌّ لكلِّ سطحٍ بحكمِه وقاعدتِه\n";
$run("CREATE TABLE IF NOT EXISTS `repair01_w10_split` (
        `scope_key` VARCHAR(16) NOT NULL COMMENT 'Screen_ID المعياري',
        `route` VARCHAR(200) NOT NULL DEFAULT '',
        `screen_file` VARCHAR(160) NOT NULL DEFAULT '',
        `title` VARCHAR(255) NOT NULL DEFAULT '',
        `legacy_unit` VARCHAR(160) NOT NULL,
        `in_surfaces` TINYINT(1) NOT NULL DEFAULT 0,
        `in_registry` TINYINT(1) NOT NULL DEFAULT 0,
        `surf_code_before` VARCHAR(12) NOT NULL DEFAULT '',
        `reg_code_before` VARCHAR(12) NOT NULL DEFAULT '',
        `resolved_code` VARCHAR(12) NOT NULL,
        `split_rule` VARCHAR(48) NOT NULL,
        `split_why` VARCHAR(400) NOT NULL,
        `anchor_ref` VARCHAR(190) NOT NULL DEFAULT '',
        `matched_token` VARCHAR(190) NOT NULL DEFAULT '',
        `serves_both` TINYINT(1) NOT NULL DEFAULT 0,
        `moved_surface` TINYINT(1) NOT NULL DEFAULT 0,
        `moved_registry` TINYINT(1) NOT NULL DEFAULT 0,
        `arbitrary_before` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'كان محسوما بترتيب الصفوف لا بمعناه',
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`scope_key`),
        KEY `ix_w10sp_code` (`resolved_code`),
        KEY `ix_w10sp_unit` (`legacy_unit`),
        CONSTRAINT `chk_w10sp_full` CHECK (`resolved_code` <> '' AND `split_rule` <> '' AND `split_why` <> ''),
        CONSTRAINT `chk_w10sp_reg` CHECK (`in_surfaces` = 1 OR `in_registry` = 1)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='W10 قرار الشق سطحا سطحا بقاعدته ومرساته'", 'repair01_w10_split');

echo "\n④ الجسرُ — كلُّ مؤشِّرٍ حيٍّ باسمِ الوحدةِ الأمّ\n";
$run("CREATE TABLE IF NOT EXISTS `repair01_w10_bridge` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `host_table` VARCHAR(64) NOT NULL,
        `pointer_col` VARCHAR(64) NOT NULL,
        `pointer_key` VARCHAR(200) NOT NULL COMMENT 'مفتاح الصف الحي كما هو',
        `legacy_name` VARCHAR(160) NOT NULL,
        `resolved_code` VARCHAR(12) NOT NULL,
        `bridge_rule` VARCHAR(48) NOT NULL,
        `bridge_why` VARCHAR(400) NOT NULL,
        `probe_sql` VARCHAR(600) NOT NULL COMMENT 'استعلام يثبت ان الرابط القديم ما زال يقرا',
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_w10br` (`host_table`, `pointer_key`),
        KEY `ix_w10br_code` (`resolved_code`),
        CONSTRAINT `chk_w10br_full` CHECK (`resolved_code` <> '' AND `bridge_rule` <> ''
                                           AND `bridge_why` <> '' AND `probe_sql` <> '')
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='W10 الجسر يترجم ولا يدهس الرابط القديم'", 'repair01_w10_bridge');

echo "\n⑤ السايدبار — سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح\n";
$run("CREATE TABLE IF NOT EXISTS `repair01_w10_sidebar` (
        `screen_id` VARCHAR(16) NOT NULL,
        `route` VARCHAR(200) NOT NULL DEFAULT '',
        `owner_code` VARCHAR(12) NOT NULL DEFAULT '',
        `s1_verdict` VARCHAR(40) NOT NULL DEFAULT '', `s1_rule` VARCHAR(48) NOT NULL DEFAULT '',
        `s2_verdict` VARCHAR(40) NOT NULL DEFAULT '', `s2_rule` VARCHAR(48) NOT NULL DEFAULT '',
        `s3_verdict` VARCHAR(40) NOT NULL DEFAULT '', `s3_rule` VARCHAR(48) NOT NULL DEFAULT '',
        `s4_verdict` VARCHAR(40) NOT NULL DEFAULT '', `s4_rule` VARCHAR(48) NOT NULL DEFAULT '',
        `s4_order_no` INT NOT NULL DEFAULT 0,
        `s5_verdict` VARCHAR(40) NOT NULL DEFAULT '', `s5_rule` VARCHAR(48) NOT NULL DEFAULT '',
        `s6_verdict` VARCHAR(40) NOT NULL DEFAULT '', `s6_rule` VARCHAR(48) NOT NULL DEFAULT '',
        `s6_perm_rows` INT NOT NULL DEFAULT 0,
        `s7_verdict` VARCHAR(40) NOT NULL DEFAULT '', `s7_rule` VARCHAR(48) NOT NULL DEFAULT '',
        `s7_linked` TINYINT(1) NOT NULL DEFAULT 0,
        `group_name` VARCHAR(190) NOT NULL DEFAULT '',
        PRIMARY KEY (`screen_id`),
        KEY `ix_w10sb_owner` (`owner_code`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='W10 السايدبار قبل الشاشات سبع خطوات مرتبة'", 'repair01_w10_sidebar');

echo "\n⑥ آلاتُ الحالةِ والواجباتُ والقرارات\n";
$run("CREATE TABLE IF NOT EXISTS `repair01_w10_states` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `entity` VARCHAR(64) NOT NULL,
        `from_state` VARCHAR(48) NOT NULL,
        `to_state` VARCHAR(48) NOT NULL,
        `allowed` TINYINT(1) NOT NULL DEFAULT 1,
        `owner_role` VARCHAR(120) NOT NULL DEFAULT '',
        `precondition` VARCHAR(400) NOT NULL DEFAULT '',
        `official_doc` VARCHAR(190) NOT NULL DEFAULT '',
        `approval_gate` VARCHAR(190) NOT NULL DEFAULT '',
        `reopen_rule` VARCHAR(300) NOT NULL DEFAULT '',
        `correct_rule` VARCHAR(300) NOT NULL DEFAULT '',
        `forbid_reason` VARCHAR(400) NOT NULL DEFAULT '',
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_w10st` (`entity`, `from_state`, `to_state`),
        CONSTRAINT `chk_w10st_forbid` CHECK (`allowed` = 1 OR `forbid_reason` <> ''),
        CONSTRAINT `chk_w10st_allow` CHECK (`allowed` = 0 OR (`owner_role` <> '' AND `precondition` <> ''
             AND `official_doc` <> '' AND `approval_gate` <> '' AND `reopen_rule` <> '' AND `correct_rule` <> ''))
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='W10 الة حالة لكل كيان بممنوع صريح مسبب'", 'repair01_w10_states');

$run("CREATE TABLE IF NOT EXISTS `repair01_w10_sod` (
        `process_key` VARCHAR(64) NOT NULL,
        `process_name` VARCHAR(190) NOT NULL DEFAULT '',
        `initiator_role` VARCHAR(120) NOT NULL DEFAULT '',
        `reviewer_role` VARCHAR(120) NOT NULL DEFAULT '',
        `approver_role` VARCHAR(120) NOT NULL DEFAULT '',
        `executor_role` VARCHAR(120) NOT NULL DEFAULT '',
        `closer_role` VARCHAR(120) NOT NULL DEFAULT '',
        `forbidden_combo` VARCHAR(300) NOT NULL DEFAULT '',
        `enforced_by` VARCHAR(80) NOT NULL DEFAULT '',
        `authority_rule_id` VARCHAR(48) NOT NULL DEFAULT '',
        `deputy_role` VARCHAR(120) NOT NULL DEFAULT '',
        `scope_note` VARCHAR(190) NOT NULL DEFAULT '',
        `delegation_rule` VARCHAR(190) NOT NULL DEFAULT '',
        `effective_date` DATE NULL DEFAULT NULL,
        PRIMARY KEY (`process_key`),
        CONSTRAINT `chk_w10sod_full` CHECK (`initiator_role` <> '' AND `approver_role` <> ''
             AND `executor_role` <> '' AND `closer_role` <> '' AND `forbidden_combo` <> '' AND `enforced_by` <> '')
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='W10 فصل الواجبات بادوار ستة وتركيبة ممنوعة'", 'repair01_w10_sod');

$run("CREATE TABLE IF NOT EXISTS `repair01_w10_decisions` (
        `decision_id` VARCHAR(24) NOT NULL,
        `question` VARCHAR(400) NOT NULL,
        `ruling` VARCHAR(400) NOT NULL,
        `rationale` VARCHAR(1200) NOT NULL DEFAULT '',
        `scope_rows` INT NOT NULL DEFAULT 0,
        `src_ref` VARCHAR(190) NOT NULL DEFAULT '',
        PRIMARY KEY (`decision_id`),
        CONSTRAINT `chk_w10dec_full` CHECK (`question` <> '' AND `ruling` <> '')
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='W10 قرارات المرحلة'", 'repair01_w10_decisions');

echo "\n⑦ رحلةُ الإثبات\n";
$run("CREATE TABLE IF NOT EXISTS `repair01_w10_journey` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `run_id` VARCHAR(40) NOT NULL,
        `seq` SMALLINT UNSIGNED NOT NULL,
        `leg` VARCHAR(48) NOT NULL DEFAULT '',
        `station` VARCHAR(190) NOT NULL,
        `actor` VARCHAR(120) NOT NULL DEFAULT '',
        `consumer` VARCHAR(120) NOT NULL DEFAULT '',
        `business_effect` VARCHAR(400) NOT NULL DEFAULT '',
        `passed` TINYINT(1) NOT NULL DEFAULT 0,
        `detail` VARCHAR(600) NOT NULL DEFAULT '',
        `at` DATETIME(6) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_w10jr` (`run_id`, `seq`),
        KEY `ix_w10jr_run` (`run_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='W10 رحلة الشق محطة محطة باثر كل مستهلك'", 'repair01_w10_journey');

echo "\n⑧ شقُّ دفترِ الفجواتِ — بعمودٍ جديدٍ لا بدهسِ الوحدةِ القديمة\n";
/* ⛔ **الوحدةُ القديمةُ في `repair01_target_gaps.unit` لا تُدهَس**: `W1-10` يعُدُّ
     فجواتِ مكتبِ الرئيسِ باسمِه الحيّ، ودهسُه يُسقط حاجبًا مُغلقًا — وهو نفسُ
     مبدأِ الجسر: **يُترجَم ولا يُستبدَل**. فالشقُّ يُكتب في عمودٍ إلى جانبِه. */
$hasCol = false;
$r = $conn->query("SHOW COLUMNS FROM `repair01_target_gaps` LIKE 'split_code'");
if ($r && $r->num_rows > 0) { $hasCol = true; }
if ($hasCol) {
    echo "  ↷ العمودُ قائمٌ سلفًا\n";
} else {
    $run("ALTER TABLE `repair01_target_gaps`
            ADD COLUMN `split_code` VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'W10 شق الوحدة المشقوقة',
            ADD COLUMN `split_rule` VARCHAR(48) NOT NULL DEFAULT '',
            ADD COLUMN `split_why` VARCHAR(400) NOT NULL DEFAULT ''", 'repair01_target_gaps · أعمدة الشقّ');
}

echo "\n⑨ أثرُ المستهلكِ على دفترِ الشقّ\n";
/* ◆ **والنشرُ بلا مستهلكٍ مرفوضٌ في الجذرِ نفسِه** (`BUS_NO_CONSUMER` · CK-11):
     فعقدُ الأثرِ ليس وثيقةً بل **شرطُ نشرٍ منفَّذ**. والمستهلكُ يترك أثرَه هنا. */
$hasV = false;
$r = $conn->query("SHOW COLUMNS FROM `repair01_w10_split` LIKE 'verified_at'");
if ($r && $r->num_rows > 0) { $hasV = true; }
if ($hasV) {
    echo "  ↷ عمودا التحقّقِ قائمانِ سلفًا\n";
} else {
    $run("ALTER TABLE `repair01_w10_split`
            ADD COLUMN `verified_at` DATETIME NULL DEFAULT NULL COMMENT 'اثر مستهلك الحدث',
            ADD COLUMN `verify_ref` VARCHAR(64) NOT NULL DEFAULT ''", 'repair01_w10_split · أثرُ المستهلك');
}

echo "\n⑩ تسجيلُ مستهلكي أحداثِ النطاق\n";
$CONS = array(
    array('dept.split.applied',        'refreshSplitCounters', 'dashboard_refresh'),
    array('surface.owner.reassigned',  'verifyOwner',          'write'),
    array('legacy.pointer.translated', 'verifyBridge',         'write'),
    array('sidebar.item.replaced',     'verifySidebarLink',    'write'),
    array('split.conflict.detected',   'recordConflict',       'write'),
);
foreach ($CONS as $c) {
    $key = 'splitprojection_' . str_replace('.', '_', $c[0]);
    $run("INSERT INTO `event_consumers`
            (`event_name`, `consumer_class`, `consumer_method`, `produces`, `active`,
             `consumer_key`, `max_attempts`, `timeout_seconds`)
          VALUES ('" . $conn->real_escape_string($c[0]) . "',
                  'App\\\\Services\\\\Governance\\\\SplitProjectionConsumer',
                  '" . $conn->real_escape_string($c[1]) . "',
                  '" . $conn->real_escape_string($c[2]) . "', 1,
                  '" . $conn->real_escape_string($key) . "', 5, 60)
          ON DUPLICATE KEY UPDATE `consumer_method` = VALUES(`consumer_method`),
                  `produces` = VALUES(`produces`), `active` = 1,
                  `inactive_reason` = NULL, `inactive_at` = NULL", 'مستهلك ' . $c[0]);
}

echo "\n───────────────────────────────────────────────────────────────\n";
echo "الخلاصة: نُفِّذ $done · أخطاء $err\n";
exit($err > 0 ? 1 : 0);
