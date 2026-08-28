<?php
/**
 * 2027_11_20_repair01_w3_label_keys.php
 *
 * @migration-objects: col:scr_production.site_id, col:scr_unit_perf.equipment_id, col:scr_site_shift_plan.operator_employee_id, idx:scr_production.ix_w3_site_id, idx:scr_unit_perf.ix_w3_equipment_id, idx:scr_site_shift_plan.ix_w3_operator_employee_id
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W03 — **مفتاحُ الحقيقةِ الأمِّ في الجداولِ التي تعرّفها بنصّ**.
 *
 * ◆ **المقيسُ الذي أوجب الهجرة**: مسحُ المخطَّطِ الحيِّ وجد ٢٥ مرشَّحًا يعرّف
 *   حقيقةً أمًّا بنصٍّ بلا مفتاح؛ اثنانِ وعشرونَ منها **بذرةٌ بلا مرجع** (كلُّ
 *   صفوفِها `is_seed = 1` وصفرُ نصٍّ يجد مرجعَه) — وثلاثةٌ فيها **صفوفٌ حيّةٌ
 *   أدخلها مستخدمٌ**: `scr_production.site_name` · `scr_unit_perf.code_equipment`
 *   · `scr_site_shift_plan.operator_assignee`. فالمعرّفُ البديلُ هنا **حيٌّ لا
 *   مفترَض**.
 *
 * ◆ **والإصلاحُ إضافيٌّ لا هادم**: يُضاف عمودُ المفتاحِ فيصير النصُّ **لافتةً
 *   بجانبِ مفتاحٍ** لا معرّفًا بديلًا. ولا يُحذف عمودُ النصِّ ولا يُعاد ترقيمُ
 *   شيء (⛔ _CONTEXT).
 *
 * ◆ **والملءُ ليس جزءًا من الهجرة**: `tools/repair01_w3_apply.php` يملأ ما يجد
 *   مرجعَه ويقيس ما لا يجده — فالهجرةُ بنيةٌ والأداةُ حكمٌ، ولا تختلط الطبقتان.
 *
 * التشغيل: php database/migrations/2027_11_20_repair01_w3_label_keys.php
 * التراجع: php database/migrations/2027_11_20_repair01_w3_label_keys_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/tools/lib/repair01_w3_scan.php';
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ REPAIR01 · W03 — مفتاحُ الحقيقةِ الأمِّ بدلَ التعريفِ بالنصّ ══\n\n";
$done = 0; $had = 0; $err = 0;
foreach (repair01_w3_label_repairs() as $r) {
    $t = $r['table']; $c = $r['key_col'];
    $exists = $conn->query("SHOW COLUMNS FROM `$t` LIKE '" . $conn->real_escape_string($c) . "'");
    if ($exists && $exists->num_rows > 0) { echo "  ⟳ $t.$c موجود\n"; $had++; continue; }
    $sql = "ALTER TABLE `$t` ADD COLUMN `$c` INT(11) NULL DEFAULT NULL COMMENT '" . $r['comment'] . "', ADD KEY `ix_w3_$c` (`$c`)";
    if ($conn->query($sql) === true) { echo "  ✔ $t.$c ⇐ {$r['key_code']}\n"; $done++; }
    else { echo "  ✘ $t.$c — " . $conn->error . "\n"; $err++; }
}
echo "\n";
printf("الحصيلة: مُنفَّذٌ %d · موجودٌ سلفًا %d · أخطاء %d\n", $done, $had, $err);
echo ($err === 0 ? "الحكم: تمّت ✔ — والملءُ بـ`php tools/repair01_w3_apply.php`\n" : "الحكم: أخطاء ✘\n");
$conn->close();
exit($err === 0 ? 0 : 1);
