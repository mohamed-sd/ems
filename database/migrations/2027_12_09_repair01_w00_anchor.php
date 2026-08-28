<?php
/**
 * 2027_12_09_repair01_w00_anchor.php — مرساةُ الطورِ صفرِ حقيقةً مسجَّلةً لا ثابتًا حرفيًّا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العيبُ الذي تعالجه (RPR-AMD01)**: اثنا عشرَ حاجبًا في `repair01_w*_gate.php`
 *   يحرسون قاعدةً صحيحة — *«مخزنُ الطورِ صفرِ لم يُمَسَّ بهذه المرحلة»* — بجملةٍ
 *   واحدةٍ من الثوابتِ الحرفيّة:
 *
 *       $dec === 108 && $sf === 13 && $surf === 664 && $base === 651 …
 *
 *   فحين أمر المالكُ باعتمادِ الحزمةِ الشاملةِ المحدَّثة (٢٠٢٦-٠٨-٢٨) انتقل
 *   مقامُ القراراتِ **108 ⇒ 125** ومقامُ الفجواتِ الأصليّةِ **174 ⇒ 175**،
 *   **فسقط الاثنا عشرَ جميعًا دفعةً واحدة** — لا لأنَّ عطبًا وقع، بل لأنَّ
 *   المقامَ تحرَّك بأمرٍ مشروع. ⛔ **وحاجبٌ يسقط على قرارٍ مشروعٍ لا يفرّق بين
 *   الانزياحِ المأذونِ والتلويثِ الصامت — وهو ما يجعله يُكسَر بدل أن يُقرأ.**
 *
 * ◆ **والعلاجُ في الأداةِ لا في المخرَج**: المرساةُ تصير **صفًّا مسجَّلًا**
 *   يحمل قيمتَه **واستعلامَ قياسِه** و**الحزمةَ التي أثبتَتها** وتاريخَها ومن
 *   رساها ولماذا. فيبقى الحاجبُ **بأسنانِه كاملةً**: أيُّ انزياحٍ عن المرساةِ
 *   يسقطه كما كان — **وإعادةُ الترسيةِ تصير حدثًا موثَّقًا لا تحريرَ شيفرة.**
 *   وهي عينُ سنّةِ `RPR-0b` حين صار مدى الحزمةِ `RANGE.json` بدل مطابقةِ النصّ.
 *
 * ◆ **ولا مرساةَ بلا استعلامٍ يُعيد قياسَها** (`measure_sql`): قيمةٌ بلا تعريفٍ
 *   تُقرأ رقمًا ولا تُراجَع، **وصفرٌ من مفردةٍ لا وجودَ لها أخضرُ كاذب**. فالقيدُ
 *   يمنع صفًّا بلا استعلامٍ وبلا مرجعِ حزمةٍ وبلا سبب.
 *
 * ◆ **والتاريخُ لا يُدهَس**: كلُّ إعادةِ ترسيةٍ تُقيَّد صفًّا في
 *   `repair01_w00_anchor_log` بالقيمةِ القديمةِ والجديدةِ وسببِها — فسؤالُ
 *   «متى تحرَّك هذا المقامُ ولماذا؟» يبقى له جواب.
 *
 * التشغيل: php database/migrations/2027_12_09_repair01_w00_anchor.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);

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

$made = 0; $had = 0;
$tables = array();

/* ① المرساةُ — صفٌّ لكلِّ مقامٍ يحرسه حاجبٌ ─────────────────────────────── */
$tables['repair01_w00_anchor'] = "CREATE TABLE `repair01_w00_anchor` (
  `metric`       VARCHAR(64)  NOT NULL COMMENT 'مفتاح المقام - تقرؤه الحواجب',
  `label_ar`     VARCHAR(160) NOT NULL,
  `measure_sql`  TEXT         NOT NULL COMMENT 'استعلام يعيد عددا واحدا - به تعاد القياس',
  `anchor_value` INT          NOT NULL,
  `package_ref`  VARCHAR(190) NOT NULL COMMENT 'الحزمة او الامر الذي اثبت هذه القيمة',
  `src_ref`      VARCHAR(300) NOT NULL,
  `why`          VARCHAR(500) NOT NULL COMMENT 'لماذا هذه القيمة - لا ترسية بلا سبب',
  `anchored_at`  DATETIME     NOT NULL,
  `anchored_by`  VARCHAR(120) NOT NULL,
  PRIMARY KEY (`metric`),
  /* ⛔ مرساةٌ بلا استعلامِ قياسٍ ولا مرجعِ حزمةٍ ولا سببٍ **قيمةٌ عمياء** —
       تُقرأ رقمًا ولا تُراجَع، والحاجبُ الذي يقرؤها يصير دعوى لا قياسًا. */
  CONSTRAINT `chk_w00_anchor_traced` CHECK (
    `measure_sql` <> '' AND `package_ref` <> '' AND `src_ref` <> '' AND `why` <> ''),
  /* والقيمةُ السالبةُ ليست مقامًا */
  CONSTRAINT `chk_w00_anchor_value` CHECK (`anchor_value` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='مرساة الطور صفر - حقيقة مسجلة تقرؤها الحواجب بدل ثابت حرفي'";

/* ② دفترُ إعادةِ الترسية — التاريخُ لا يُدهَس ──────────────────────────── */
$tables['repair01_w00_anchor_log'] = "CREATE TABLE `repair01_w00_anchor_log` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `metric`       VARCHAR(64)  NOT NULL,
  `value_before` INT          NULL COMMENT 'خالٍ عند الترسية الاولى',
  `value_after`  INT          NOT NULL,
  `measured_now` INT          NULL COMMENT 'ما قيس حيا لحظة الترسية',
  `package_ref`  VARCHAR(190) NOT NULL,
  `why`          VARCHAR(500) NOT NULL,
  `moved_at`     DATETIME     NOT NULL,
  `moved_by`     VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_w00_anchor_log_metric` (`metric`),
  /* ⛔ **ولا إعادةَ ترسيةٍ بلا سببٍ مكتوبٍ ومرجعِ حزمة** — وهذا هو الفرقُ بين
       «انتقل المقامُ بأمرٍ» و«كُسِر الحاجبُ ليَخضرّ». */
  CONSTRAINT `chk_w00_log_reason` CHECK (`why` <> '' AND `package_ref` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='سجل حركة مرساة الطور صفر - كل انتقال بسببه ومرجعه'";

foreach ($tables as $name => $ddl) {
    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($name) . "'");
    if ($r && $r->num_rows > 0) { $had++; echo "  ◆ قائمٌ سلفًا: $name\n"; continue; }
    if (!$conn->query($ddl)) { exit("✘ تعذّر إنشاءُ $name: {$conn->error}\n"); }
    $made++; echo "  ✔ أُنشئ: $name\n";
}

/* ③ البذرةُ — سبعةُ مقاماتٍ تقرؤها الحواجبُ الاثنا عشرَ ─────────────────
   ⛔ **والقيمةُ هنا ليست اختراعًا**: خمسةٌ منها **هي عينُ الثابتِ الحرفيِّ**
     الذي كان في الحواجب (‏لم تتحرّك)، واثنان انتقلا **بالحزمةِ التي أمر
     المالكُ باعتمادِها** — وكلٌّ منهما يحمل سببَه ومرجعَه. */
$NOW = date('Y-m-d H:i:s');
$PKG_OLD = 'REPAIR01 · الطور صفر · الحزمة الأولى (RPR-02 v2.3)';
$PKG_NEW = 'RPR-02-A · الحزمة الشاملة المحدَّثة — استُوعبت 2026-08-28 18:56';

$SEED = array(
    array('decisions', 'قرارات السجل المؤسسي',
        "SELECT COUNT(*) FROM repair01_decisions",
        125, $PKG_NEW, 'مصنَّف «09 · السجلات المؤسسية والقرارات» في الحزمة المحدَّثة',
        'الحزمةُ المحدَّثةُ رفعت السجلَّ 108 ⇒ 125 (‏114 معتمَدًا + 11 منتظِرًا) — انتقالٌ بأمرٍ لا تلويث'),

    array('source_files', 'ملفَّات المصدر المجمَّدة',
        "SELECT COUNT(*) FROM repair01_source_files",
        13, $PKG_OLD, 'repair01_ingest.php — تجزئةُ المصنَّفاتِ المجمَّدة',
        'لم تتحرّك عبر الحزمتَين — 12 مصنَّفًا + دليلُ العرضِ بالأدوار'),

    array('surfaces', 'أسطح الدراسة',
        "SELECT COUNT(*) FROM repair01_surfaces",
        664, $PKG_OLD, 'لقطةُ الدراسةِ المجمَّدة — `G0-07b`',
        'مصدرُها حيٌّ لا مصنَّف، ونطاقُ التصميم `--scope=design` يتركها عمدًا — فلم تتحرّك'),

    array('registry_base', 'أساس سجل الشاشات (SURFACES · DISK · NAV)',
        "SELECT COUNT(*) FROM repair01_screen_registry WHERE origin IN ('SURFACES','DISK','NAV')",
        651, $PKG_OLD, 'repair01_w2_apply.php — الكونُ المعياريُّ بأصولِه الثلاثة',
        'لم تتحرّك. ⚠ وقِيست 669 يومَ 2026-08-28 بعطبٍ في `w2_apply` دهس ختمَ النموِّ لـ18 صفَّ W11 — أُصلح في الأداةِ وعادت 651'),

    array('ownership_forbidden', 'أحكام الظهور المحرَّم (W01)',
        "SELECT COUNT(*) FROM repair01_ownership WHERE classification = 'FORBIDDEN'",
        265, $PKG_OLD, 'repair01_w1_apply.php — الظهورُ المحرَّم',
        'لم تتحرّك — و265 هي التي يوجب الاستيعابُ صونَها قبلَه وإعادتَها بعدَه'),

    array('gaps_original', 'فجوات الدفتر الأصلية (بلا مرحلة منشأ)',
        "SELECT COUNT(*) FROM repair01_target_gaps WHERE COALESCE(origin_stage,'') = ''",
        175, $PKG_NEW, 'مصنَّف «10 · المصالحة مع النظام» — المقامُ 429 ⇒ 433',
        'الحزمةُ المحدَّثةُ أضافت فجوةً واحدةً أصليّةً 174 ⇒ 175 — انتقالٌ بأمرٍ لا تلويث'),

    array('events_study', 'أحداث الدراسة (بلا عقد مرحلة)',
        "SELECT COUNT(*) FROM repair01_events WHERE contract_stage = ''",
        632, $PKG_OLD, 'كتالوجُ الأحداثِ — مصنَّف «03 · الدستور» ورقة C',
        'لم تتحرّك — و632 هي أحداثُ الدراسةِ التي لم تُمَسَّ بعقدِ أيِّ موجة'),
);

$seeded = 0; $kept = 0;
foreach ($SEED as $s) {
    list($metric, $label, $sql, $val, $pkg, $src, $why) = $s;
    $e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };
    $r = $conn->query("SELECT anchor_value FROM repair01_w00_anchor WHERE metric = '" . $e($metric) . "'");
    if ($r && $r->num_rows > 0) { $kept++; continue; }
    /* ما قِيس حيًّا لحظةَ الترسية — يُسجَّل في الدفترِ شاهدًا */
    $m = null; $q = @$conn->query($sql);
    if ($q) { $m = (int) $q->fetch_row()[0]; }
    $ok = $conn->query("INSERT INTO repair01_w00_anchor
        (metric, label_ar, measure_sql, anchor_value, package_ref, src_ref, why, anchored_at, anchored_by)
        VALUES ('" . $e($metric) . "','" . $e($label) . "','" . $e($sql) . "'," . (int) $val . ",
                '" . $e($pkg) . "','" . $e($src) . "','" . $e($why) . "','" . $e($NOW) . "','RPR-AMD01')");
    if (!$ok) { exit("✘ تعذّرت ترسيةُ $metric: {$conn->error}\n"); }
    $conn->query("INSERT INTO repair01_w00_anchor_log
        (metric, value_before, value_after, measured_now, package_ref, why, moved_at, moved_by)
        VALUES ('" . $e($metric) . "', NULL, " . (int) $val . ",
                " . ($m === null ? 'NULL' : (int) $m) . ",'" . $e($pkg) . "','" . $e($why) . "',
                '" . $e($NOW) . "','RPR-AMD01')");
    $seeded++;
    printf("  ✔ رُسيت %-20s = %-4d %s\n", $metric, $val,
        ($m !== null && $m !== $val) ? "⚠ والمقيسُ حيًّا $m" : '');
}

require_once __DIR__ . '/_ledger.php';
$ms = (int) round((microtime(true) - $t0) * 1000);
ems_migration_recorded(__FILE__, $conn, $ms);

echo "\n✔ مرساةُ W00: جداولُ أُنشئت $made · قائمةٌ $had · مقاماتٌ رُسيت $seeded · قائمةٌ $kept\n";
