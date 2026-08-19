<?php
/**
 * tools/uxui_record_a11y.php — يسجّل مخرَجَ مسبارِ الوصولِ الحيِّ كما هو
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لا يُعيد صوغَ الرقمِ ولا يُلخّصه: يُحفظ المخرَجُ الخامُّ في `detail_json`
 *   ويُشتقُّ منه العددُ — فمن أراد المراجعةَ يقرأ ما قاسه المتصفحُ لا ما رويتُه.
 * ◆ ويُرفض القياسُ إن لم يُعلن `keyboardModality` — لأن فحصَ التركيزِ حينَها
 *   لم يقعْ، وصفرُه كاذب. والقيدُ في القاعدةِ يمنعه أيضًا (حزامٌ وحمّالة).
 *   php tools/uxui_record_a11y.php --file=out.json
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';

$file = null;
foreach ($argv as $a) { if (strpos($a, '--file=') === 0) { $file = substr($a, 7); } }
if (!$file || !is_file($file)) { exit("✗ --file=مسار مطلوب\n"); }
$data = json_decode((string) file_get_contents($file), true);
if (!is_array($data) || empty($data['url'])) { exit("✗ حمولةٌ غيرُ صالحة\n"); }

$checks = isset($data['checks']) && is_array($data['checks']) ? $data['checks'] : array();
$modality = !empty($checks['focus_visible']['keyboardModality']);
$total = 0;
foreach ($checks as $c) { $total += isset($c['violations']) ? (int) $c['violations'] : 0; }

if (!$modality && $total === 0) {
    exit("✗ صفرُ مخالفاتٍ بلا نمطيةِ لوحةِ مفاتيح — اضغطْ Tab حقيقيةً قبل القياس\n");
}

$screen = ltrim(str_replace('/ems/', '', (string) $data['url']), '/');

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("✗ اتصال\n"); }
$conn->set_charset('utf8mb4');

/* ═══════════════════════════════════════════════════════════════════════════
 * الإصدارُ **من بصمةِ ملفاتِ المكتبةِ الآن** — لا من رقمٍ مكتوبٍ في ملف
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ نفسُ الاشتقاقِ في `uxui_record_measurement.php` حرفًا — فالقياسانِ يُنسبانِ
 *   إلى المرساةِ نفسِها. ولو كُتب الرقمُ يدويًّا لجاز أن يُنسب قياسٌ إلى إصدارٍ
 *   لم يُقَس عليه — وذاك رقمٌ صحيحٌ في نصِّه كاذبٌ في مدلولِه.
 * ◆ وبصمةٌ غيرُ مسجَّلةٍ تعني **أن المكتبةَ تغيّرت بعد آخرِ تسجيل** — فيُرفض
 *   القياسُ ولا يُنسب إلى إصدارٍ قديمٍ لم يعُد قائمًا.
 * ═══════════════════════════════════════════════════════════════════════════ */
$FILES = array('assets/css/uxui-tokens.css', 'assets/css/uxui-components.css',
               'includes/uxui_components.php', 'includes/status_display.php',
               'assets/css/ems-screens.css');
$parts = array();
foreach ($FILES as $f) {
    $p = $ROOT . '/' . $f;
    if (is_file($p)) { $parts[$f] = hash_file('sha256', $p); }
}
ksort($parts);
$fp = hash('sha256', implode('|', array_map(function ($k, $v) { return $k . ':' . $v; },
      array_keys($parts), $parts)));
$r = $conn->query("SELECT version_tag FROM gov_component_versions
                    WHERE fingerprint='" . $conn->real_escape_string($fp) . "'");
$ver = ($r && $r->num_rows > 0) ? $r->fetch_assoc()['version_tag'] : null;
if ($ver === null) {
    exit("✗ بصمةُ المكتبةِ غيرُ مسجَّلة — المكوّنُ تغيّر بعد آخرِ تسجيل، فالقياسُ بلا مرساة\n");
}

$json = json_encode($data, JSON_UNESCAPED_UNICODE);
$km = $modality ? 1 : 0;
$nc = count($checks);
$st = $conn->prepare("INSERT INTO gov_a11y_measurements
        (screen_file, component_version, checks_total, violations_total, keyboard_modality, detail_json)
        VALUES (?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE checks_total=VALUES(checks_total),
        violations_total=VALUES(violations_total), keyboard_modality=VALUES(keyboard_modality),
        detail_json=VALUES(detail_json), measured_at=NOW()");
$st->bind_param('ssiiis', $screen, $ver, $nc, $total, $km, $json);
if (!$st->execute()) { exit("✗ {$st->error}\n"); }
printf("  ✔ %s @ %s · فحوص=%d · مخالفات=%d · لوحةُ مفاتيح=%s\n",
    $screen, $ver, $nc, $total, $modality ? 'نعم' : 'لا');
