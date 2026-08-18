<?php
/**
 * 2027_05_29_eng01_schedule_all_entities.php
 * ═══════════════════════════════════════════════════════════════════════════
 * ENG-01 ⑥ · الجدولةُ لكلِّ كيانٍ نشطٍ لا لكيانٍ واحد
 * ───────────────────────────────────────────────────────────────────────────
 * كُشف في أولِ تشغيلٍ حقيقيّ: الجدولاتُ بُذرت بـcompany_id=1، والمعالجُ يقصر
 * عملَه على كيانِ المهمة، فعملت الدورةُ كلُّها على الكيان 1 وتخطّت الكيان 4
 * حيث البياناتُ فعلًا — ولا شيءَ بدا معطَّلًا: ثمانِ مهامٍّ «نجحت» بأصفار.
 *
 * والعلاجُ: company_id=0 في الجدولةِ يعني «كلَّ كيانٍ نشط»، والتجسيدُ يفرد
 * مهمةً لكلِّ كيانٍ بعمودِ عزلٍ حقيقيٍّ — فلا جدولةٌ بلا عزلٍ ولا كيانٌ منسيّ.
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

echo "\n▐ الجدولاتُ تعمل على كلِّ كيانٍ نشط (company_id = 0)\n";
$conn->query("UPDATE `ems_job_schedule` SET `company_id` = 0 WHERE `company_id` = 1");
echo "   ✔ حُوّلت " . $conn->affected_rows . " جدولةً إلى «كلُّ كيانٍ نشط»\n";

$conn->query("ALTER TABLE `ems_job_schedule`
              MODIFY COLUMN `company_id` INT UNSIGNED NOT NULL DEFAULT 0
              COMMENT 'عمودُ العزل — و0 تعني كلَّ كيانٍ نشط، فيُفرَد صفُّ مهمةٍ لكلِّ كيان'");
echo "   ✔ وُثّقت دلالةُ الصفرِ في تعليقِ العمود\n";

$r = $conn->query("SELECT `job_type`,`company_id`,`cron_expr`,`owner_role_id` FROM `ems_job_schedule` ORDER BY `id`");
echo "\n   الجدولاتُ بعدَ التحويل:\n";
while ($x = $r->fetch_row()) {
    printf("      %-18s كيان=%-4s %-14s دور=%s\n", $x[0], $x[1] === '0' ? 'الكل' : $x[1], $x[2], $x[3]);
}
echo "\n";
