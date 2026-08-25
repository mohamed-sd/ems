<?php
/**
 * 2027_11_23_repair01_w6_ui_purity_down.php — تراجعُ هجرةِ W06
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لا شيءَ من جداولِ W06 أبٌ لجدولٍ حيّ**: السجلُّ يُقرأ ولا يُشار إليه
 *   بمفتاحٍ أجنبيّ — فالترتيبُ هنا للوضوحِ لا للتبعيّة.
 *
 * ⚠ **والتراجعُ لا يُرجع النصَّ المُنقّى**: التنقيةُ تعديلُ بياناتٍ في جداولَ
 *   حيّةٍ (‏`gov_screen_cycle` · `nav_items` · `nav_canonical` · `work_items`)
 *   لا تُنشئها هذه الهجرةُ ولا تملكها. وإرجاعُ النصِّ أمرُ أداةٍ لا هجرة:
 *   `php tools/repair01_w6_apply.php --revert` يُرجع من `repair01_w6_rewrite`
 *   و`repair01_ui_labels.deprecated_label`. **فأرجِعِ النصَّ قبل نزعِ سجلِّه**
 *   وإلّا نزعتَ الدليلَ الذي يُرجَع منه.
 *
 * التشغيل: php database/migrations/2027_11_23_repair01_w6_ui_purity_down.php
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

echo "══ تراجعُ REPAIR01 · W06 ══\n\n";

/* حارسٌ: النصُّ الحيُّ ما زال مُنقّى ولم يُرجَع — النزعُ يمحو دليلَ الإرجاع. */
$pending = 0;
$r = $conn->query("SELECT COUNT(*) FROM repair01_w6_rewrite");
if ($r && ($x = $r->fetch_row())) { $pending = (int) $x[0]; }
if ($pending > 0 && !in_array('--force', $argv, true)) {
    echo "⛔ في `repair01_w6_rewrite` $pending إعادةَ كتابةٍ لم تُرجَع.\n";
    echo "   أرجِعِ النصَّ أوّلًا: php tools/repair01_w6_apply.php --revert\n";
    echo "   أو مرِّرْ --force إن كان الإرجاعُ قد تمّ خارجَه.\n";
    exit(1);
}

$ok = 0; $err = 0;
foreach (array(
    'repair01_w6_sod', 'repair01_w6_states', 'repair01_w6_journey',
    'repair01_w6_decisions', 'repair01_w6_thresholds', 'repair01_w6_reject_log',
    'repair01_w6_rewrite', 'repair01_w6_scope', 'repair01_w6_code_dict',
    /* دفترا الجولةِ الثانية (‏§٤-٢ · §٤-٥) */
    'repair01_w6_file_log', 'repair01_w6_coupled',
    'repair01_ui_labels',
) as $t) {
    if ($conn->query("DROP TABLE IF EXISTS `$t`") === true) { echo "  ✔ نُزع $t\n"; $ok++; }
    else { echo "  ✘ $t — " . $conn->error . "\n"; $err++; }
}

echo "\n" . str_repeat('─', 70) . "\n";
printf("نُزع %d · أخطاء %d\n", $ok, $err);
echo 'الحكم: ' . ($err === 0 ? "رجعت ✔\n" : "فيها خطأ ✘\n");
exit($err === 0 ? 0 : 1);
