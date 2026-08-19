<?php
/**
 * 2027_08_01_site_gate_person_keys.php — إذنُ دخولِ الأشخاصِ يحمل مفاتيحَه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب (ثامنًا-٤ · CMP-03): «الشاشاتُ المولَّدةُ تكتب من خدمةِ نطاقٍ لا
 *   من مخزنٍ عام».
 *
 * ◆ **والمقامُ صُحِّح أولًا**: v25 تقول «الشاشاتُ الأربعُ مئةٍ بلا خدمةِ نطاق»،
 *   والمقيسُ **42 جدولَ `scr_`** و42 شاشةً مولَّدة — **منها 4 مجسورةٌ**.
 *   فالمتبقّي **38 لا 396**، والمُنجَزُ 9.5٪ لا 1٪.
 *
 * ◆ **وحُوول تصنيفُ الـ38 آليًّا فسقطَ المعيار**: قِيس تشابهُ أسماءِ الأعمدةِ بين
 *   كلِّ `scr_` وكلِّ جدولِ مجال، ثم جُرِّب المعيارُ على **الجسورِ الثلاثةِ
 *   المعروفةِ الصحيحة** — فأعطى **صفرًا في ثلاثتِها** (`scr_attendance` ⇐
 *   `attendance_days` = 0٪). **ومعيارٌ يرسبُ في كلِّ حالةٍ صادقةٍ معلومةٍ لا
 *   يُبنى عليه حكم** — فسُحب، ولم يُعلَن «38 بلا مجال» وهو ناتجُه.
 *   والسببُ أن التسميةَ في `scr_` مولَّدةٌ من العربية (`hours_per_asset_log`)
 *   وفي المجالِ مؤلَّفةٌ إنجليزيًّا — **فالربطُ معنًى لا شكلَ حروف**، ولهذا لم
 *   يتحرّكْ هذا الدَّينُ منذ v21: **هو 38 فعلَ نمذجةٍ لا موجةَ مولِّد**.
 *
 * ◆ **فنُفِّذ الواحدُ الذي له سابقةٌ موثَّقةٌ يُحتذى بها**: `site_gate_person`
 *   توأمُ `site_gate_equip` (INJ-0370) حقلًا بحقل — الإذنُ يبقى في جدولِه
 *   **والناقصُ مفاتيحُه**: شخصٌ نصًّا وموقعٌ نصًّا ومورِّدٌ نصًّا ومعتمِدٌ يكتبه
 *   المُدخِلُ بيدِه. فتُضاف المفاتيحُ الأربعةُ هنا، ويكتبها الجسرُ في الشيفرة.
 *
 * ◆ **ولا يُخترع مرجع**: عمودُ المفتاحِ يبقى NULL لما لا يقابله صفّ — والجسرُ
 *   يرفض الإدخالَ معلَنًا برمز. **والمفتاحُ المخترَعُ أسوأُ من النصِّ الحرّ.**
 * ◆ والمفاتيحُ `RESTRICT` لا `SET NULL`: مَن صدرَ له إذنٌ لا يُحذف صامتًا.
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
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$T = 'scr_site_gate_person';
function hascol(mysqli $c, string $t, string $col): bool {
    $st = $c->prepare("SELECT 1 FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $st->bind_param('ss', $t, $col); $st->execute();
    $n = $st->get_result()->num_rows; $st->close(); return $n > 0;
}

$plan = array(
    'employee_id'        => array("INT(11) NULL DEFAULT NULL COMMENT 'الشخصُ من سجلِّ الموظفين — لا نصًّا حرًّا'", 'employees', 'id'),
    'site_project_id'    => array("INT(11) NULL DEFAULT NULL COMMENT 'الموقعُ من سجلِّ المواقع'", 'project', 'id'),
    'supplier_entity_id' => array("INT(11) NULL DEFAULT NULL COMMENT 'المورِّدُ التابعُ له من سجلِّ المورِّدين'", null, null),
    'approved_by_user'   => array("INT(11) NULL DEFAULT NULL COMMENT 'المعتمِدُ يُشتقُّ من الجلسةِ ولا يُكتب'", 'users', 'id'),
);
$added = 0; $fk = 0;
foreach ($plan as $col => $spec) {
    if (hascol($conn, $T, $col)) { continue; }
    if (!$conn->query("ALTER TABLE `{$T}` ADD `{$col}` {$spec[0]}")) {
        echo "  ✘ {$col}: {$conn->error}\n"; continue;
    }
    $added++;
    if ($spec[1] !== null) {
        if ($conn->query("ALTER TABLE `{$T}` ADD CONSTRAINT `fk_sgp_{$col}`
              FOREIGN KEY (`{$col}`) REFERENCES `{$spec[1]}`(`{$spec[2]}`)
              ON DELETE RESTRICT ON UPDATE RESTRICT")) { $fk++; }
        else { echo "  ◆ {$col}: بلا مفتاحٍ أجنبيّ — " . mb_substr($conn->error, 0, 90) . "\n"; }
    }
}
echo "══ مفاتيحُ إذنِ دخولِ الأشخاص ══\n";
echo "  أُضيف: {$added} عمودًا · مفتاحٌ أجنبيّ: {$fk}\n";
$now = array();
foreach (array_keys($plan) as $col) { if (hascol($conn, $T, $col)) { $now[] = $col; } }
echo "  الحاضرُ الآن: " . implode(' · ', $now) . " (" . count($now) . " من " . count($plan) . ")\n";
exit(count($now) === count($plan) ? 0 : 1);
