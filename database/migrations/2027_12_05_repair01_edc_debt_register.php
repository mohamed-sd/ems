<?php
/**
 * 2027_12_05_repair01_edc_debt_register.php — سجلُّ أصنافِ الدَّينِ الخمسةَ عشر
 * ═══════════════════════════════════════════════════════════════════════════
 * **حكمُ المالك 2026-08-27**: «**دَينٌ غيرُ مصنَّفٍ = دَينٌ غيرُ مُدار**» — ويُصنَّف
 * الباقي كلُّه في **خمسةَ عشرَ صنفًا** لكلٍّ منها:
 *   `Count` · `Blocking Level` · `Assigned Wave` · `Owner` · `Exit Criteria`.
 *
 * ◆ **والخمسةُ حقولٍ ليست زينةً بل شروطُ إدارة**: عددٌ بلا مستوى حجبٍ لا
 *   يُرتَّب · ومستوًى بلا موجةٍ لا يُجدوَل · وموجةٌ بلا مالكٍ لا تُنفَّذ ·
 *   **ومعيارُ خروجٍ غائبٌ يجعل الصنفَ مفتوحًا إلى الأبد**.
 *
 * ⛔ **والعددُ يُقاس ولا يُكتب**: `measured_count` تملؤه أداةُ القياسِ من
 *   المخزنِ في كلِّ جولة، **فرقمٌ مكتوبٌ يدويًّا يتقادم صامتًا** — وهذا نمطٌ
 *   تكرّر في الحملةِ ثلاثَ مرّات.
 *
 * ◆ و`chk_edc_exit` **يمنع في القاعدةِ** صنفًا بلا معيارِ خروج، و`chk_edc_owner`
 *   يمنع مستوى حجبٍ `BLOCKING` بلا مالكٍ مسمًّى — **فالحاجزُ بلا مالكٍ ينتظر
 *   أحدًا لا يعلم أنّه مطلوب**.
 *
 * التشغيل: php database/migrations/2027_12_05_repair01_edc_debt_register.php
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

$has = $conn->query("SHOW TABLES LIKE 'repair01_debt_register'");
if ($has && $has->num_rows > 0) { exit("◆ السجلُّ قائمٌ سلفًا — لا شيءَ يُفعل\n"); }

$sql = "CREATE TABLE `repair01_debt_register` (
  `class_code`     VARCHAR(24)  NOT NULL,
  `class_name_ar`  VARCHAR(160) NOT NULL,
  `measure_sql`    TEXT         NOT NULL COMMENT 'العدد يقاس من المخزن ولا يكتب يدويا',
  `measured_count` INT          NOT NULL DEFAULT -1 COMMENT '-1 = لم يقس بعد',
  `measured_at`    DATETIME     NULL,
  `blocking_level` ENUM('BLOCKING','MAJOR','MINOR','INFORMATIONAL') NOT NULL,
  `assigned_wave`  VARCHAR(16)  NOT NULL,
  `debt_owner`     VARCHAR(24)  NOT NULL DEFAULT '',
  `exit_criteria`  VARCHAR(255) NOT NULL,
  `owner_ruling`   VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`class_code`),
  /* صنفٌ بلا معيارِ خروجٍ يبقى مفتوحًا إلى الأبد */
  CONSTRAINT `chk_edc_exit`  CHECK (`exit_criteria` <> ''),
  /* وحاجزٌ بلا مالكٍ ينتظر أحدًا لا يعلم أنّه مطلوب */
  CONSTRAINT `chk_edc_owner` CHECK (`blocking_level` <> 'BLOCKING' OR `debt_owner` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
if (!$conn->query($sql)) { exit("✘ " . $conn->error . "\n"); }
echo "✔ أُنشئ `repair01_debt_register` بقيدَين\n";

/* ── إثباتُ القيدَين بمحاولةِ خرقٍ حقيقيّة ─────────────────────────────────── */
$try = function ($sql, $what) use ($conn) {
    $ok = @$conn->query($sql);
    echo $ok ? "  ✘ **مرّ** — $what (‏والقيدُ لا يمنع)\n" : "  ✔ رُدَّ — $what\n";
    if ($ok) { $conn->query("DELETE FROM repair01_debt_register WHERE class_code LIKE 'ZZ-%'"); }
};
echo "── القيدان يُثبتان بخرقٍ لا بوصفٍ ──\n";
$try("INSERT INTO repair01_debt_register (class_code,class_name_ar,measure_sql,blocking_level,assigned_wave,exit_criteria)
      VALUES ('ZZ-1','خرق','SELECT 1','MINOR','W16','')", 'صنفٌ بلا معيارِ خروج');
$try("INSERT INTO repair01_debt_register (class_code,class_name_ar,measure_sql,blocking_level,assigned_wave,debt_owner,exit_criteria)
      VALUES ('ZZ-2','خرق','SELECT 1','BLOCKING','W16','','معيار')", 'حاجزٌ بلا مالك');
$conn->query("DELETE FROM repair01_debt_register WHERE class_code LIKE 'ZZ-%'");
echo "✔ تمّت الهجرة\n";
