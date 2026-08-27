<?php
/**
 * 2027_12_06_repair01_freeze_snapshot.php — سجلُّ لقطاتِ التجميد
 * ═══════════════════════════════════════════════════════════════════════════
 * **البندُ ⑬ من أمرِ المالك 2026-08-27**: «**لا يجوز تعديلُ النظامِ أثناءَ نافذةِ
 * قياسٍ رسميّةٍ تُستخدم لإصدارِ `Baseline` أو تقريرِ إغلاق**»، وكلُّ تقريرِ قياسٍ
 * يحمل `Snapshot ID` · `Commit Hash` · `Schema Version` · `Measured At` ·
 * `Tool Version`. **والبندُ ⑨** يوجب `Freeze` قبل `Full Reconciliation`.
 *
 * ◆ **واللقطةُ سجلٌّ لا وثيقة**: وثيقةٌ تُكتب مرّةً ثمَّ تُنسخ وتتفرّق، **وسجلٌّ
 *   يُسأل**. فالمصالحةُ تقرأ منه، والحاجبُ يقارن به، **ولا يبقى رقمٌ يتيمًا في
 *   نصٍّ لا يقرؤه أحد**.
 *
 * ◆ و`chk_frz_fields` **يمنع في القاعدة** لقطةً ناقصةَ حقلٍ من الخمسة —
 *   **فلقطةٌ بلا بصمةٍ كاملةٍ لا تُثبت شيئًا**، وتقريرٌ يستند إليها **لا يمثّل
 *   أيَّ نسخةٍ فعليّةٍ من النظام**.
 *
 * ⛔ **و`released_at` هو المفتاحُ**: ما دام فارغًا **فالنافذةُ مفتوحةٌ والتعديلُ
 *   ممنوع**. ولا يُفَكُّ التجميدُ إلّا بكتابةِ سببِ الفكِّ — فرفعٌ صامتٌ يجعل
 *   القاعدةَ زينة.
 *
 * التشغيل: php database/migrations/2027_12_06_repair01_freeze_snapshot.php
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

$has = $conn->query("SHOW TABLES LIKE 'repair01_freeze_snapshot'");
if ($has && $has->num_rows > 0) { exit("◆ السجلُّ قائمٌ سلفًا — لا شيءَ يُفعل\n"); }

$sql = "CREATE TABLE `repair01_freeze_snapshot` (
  `snapshot_id`    VARCHAR(48)  NOT NULL,
  `commit_hash`    VARCHAR(40)  NOT NULL,
  `branch`         VARCHAR(80)  NOT NULL DEFAULT '',
  `schema_version` VARCHAR(40)  NOT NULL COMMENT 'جداول/أعمدة مقيسة من information_schema',
  `registry_rows`  INT          NOT NULL,
  `config_baseline` VARCHAR(64) NOT NULL COMMENT 'بصمة اعدادات البيئة الحاكمة',
  `frozen_at`      DATETIME     NOT NULL,
  `frozen_by`      VARCHAR(64)  NOT NULL DEFAULT '',
  `purpose`        VARCHAR(160) NOT NULL,
  `released_at`    DATETIME     NULL COMMENT 'فارغ = النافذة مفتوحة والتعديل ممنوع',
  `release_why`    VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`snapshot_id`),
  KEY `ix_frz_open` (`released_at`),
  /* لقطةٌ بلا بصمةٍ كاملةٍ لا تُثبت شيئًا */
  CONSTRAINT `chk_frz_fields` CHECK (
      `commit_hash` <> '' AND `schema_version` <> '' AND `registry_rows` >= 0
      AND `config_baseline` <> '' AND `purpose` <> ''),
  /* ورفعُ التجميدِ بلا سببٍ مكتوبٍ يجعل القاعدةَ زينة */
  CONSTRAINT `chk_frz_release` CHECK (`released_at` IS NULL OR `release_why` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
if (!$conn->query($sql)) { exit("✘ " . $conn->error . "\n"); }
echo "✔ أُنشئ `repair01_freeze_snapshot` بقيدَين\n";

/* ── القيدان يُثبتان بمحاولةِ خرقٍ لا بوصف ────────────────────────────────── */
$try = function ($sql, $what) use ($conn) {
    $ok = @$conn->query($sql);
    echo $ok ? "  ✘ **مرّ** — $what (‏والقيدُ لا يمنع)\n" : "  ✔ رُدَّ — $what\n";
};
echo "── القيدان يُثبتان بخرقٍ ──\n";
$try("INSERT INTO repair01_freeze_snapshot
      (snapshot_id, commit_hash, schema_version, registry_rows, config_baseline, frozen_at, purpose)
      VALUES ('ZZ-1', '', '1T/1C', 0, 'x', NOW(), 'خرق')", 'لقطةٌ بلا بصمةِ التزام');
$try("INSERT INTO repair01_freeze_snapshot
      (snapshot_id, commit_hash, schema_version, registry_rows, config_baseline, frozen_at, purpose,
       released_at, release_why)
      VALUES ('ZZ-2', 'abc', '1T/1C', 0, 'x', NOW(), 'خرق', NOW(), '')", 'فكُّ تجميدٍ بلا سبب');
$conn->query("DELETE FROM repair01_freeze_snapshot WHERE snapshot_id LIKE 'ZZ-%'");
echo "✔ تمّت الهجرة\n";
