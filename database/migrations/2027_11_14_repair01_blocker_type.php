<?php
/**
 * 2027_11_14_repair01_blocker_type.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · RPR-PATCH-01 §3 — محورُ الحجبِ ذو القيمتين.
 *
 * ◆ `blocking_level` الخماسيُّ يصف **متى** يُرفع الحجب. و`blocker_type`
 *   الثنائيُّ يجيب سؤالًا واحدًا حاسمًا: **هل يمنع فتحَ البوّابة؟**
 *     `STRUCTURAL` — يحدّد بنيةَ المستهدَف، فيوقف البناء.
 *     `THRESHOLD`  — رقمٌ يُقرأ من السجلّ، والمحرّكُ يُبنى قابلَ الضبط.
 *   والفصلُ ضروريٌّ لأنّ خلطَهما هو ما جعل ٩٨ قرارًا «حاجبًا» وأربعةً منها
 *   فقط تحجب فعلًا.
 * ◆ **والبوّابةُ تُفتح بـ`STRUCTURAL` وحدَه** — `THRESHOLD` يُسجَّل ولا يمنع.
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

$has = $conn->query("SHOW COLUMNS FROM `repair01_decisions` LIKE 'blocker_type'");
if ($has && $has->num_rows > 0) { exit("= blocker_type قائمٌ سلفًا\n"); }

if ($conn->query("ALTER TABLE `repair01_decisions`
                    ADD COLUMN `blocker_type` ENUM('STRUCTURAL','THRESHOLD') NULL AFTER `blocking_level`,
                    ADD KEY `k_btype` (`blocker_type`)") === false) {
    exit("✘ {$conn->error}\n");
}
echo "✔ blocker_type أُضيف إلى repair01_decisions\n";
