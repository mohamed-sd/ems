<?php
/**
 * 2027_08_25_component_version_register.php
 *   بصمةُ الشجرةِ ≠ آخرِ إصدارٍ مسجَّل — تسجيلُ إصدارٍ **قابلٍ للمقارنة**
 *   INJ-FIX-01 · الموجة ب · GAP-26
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **عيبان لا عيبٌ واحد** — والثاني أخطرُ ولم تُسمِّه البطاقة:
 *   ① **انحرافٌ حقيقيّ**: ملفّان من خمسةٍ تغيّرا عن آخرِ إصدارٍ يحمل قائمةَ
 *      ملفات (`ux-1.3.0`): `assets/css/ems-screens.css` و`uxui-components.css`.
 *   ② **وإصدارٌ لا يُقارَن به أصلًا**: `ux-1.4.0` — آخرُ المسجَّلين — مسجَّلٌ
 *      بـ`files_json` **فارغ**. فهو وسمٌ ببصمةٍ مجرَّدةٍ بلا قائمةِ ملفاتٍ
 *      تُشتقُّ منها. **وإصدارٌ لا يُعرَف مِمَّ تكوَّن لا يُقارَن به**، فيبقى
 *      «الانحراف» غيرَ قابلٍ للقياسِ مهما بلغت دقّةُ الأداة.
 *
 * ◆ **ولذلك يُسجَّل إصدارٌ بقائمتِه لا ببصمتِه وحدَها**: لكلِّ ملفٍّ بصمتُه،
 *   وبصمةُ الإصدارِ مشتقّةٌ منها حسابًا. فيصير الانحرافُ التالي **مقيسًا
 *   بالملفِّ لا مُعلَنًا بالجملة**.
 *
 * ◆ **ويُسجَّل `DRAFT` لا مُرقّى**: الترقيةُ فعلُ مالكٍ لا فعلُ منفِّذ.
 *
 * ◆ **وحدُّ ما يُثبته هذا القيد**: بصمةُ **لحظتِه**. وجلساتٌ أخرى تعمل على
 *   الشجرةِ نفسِها وقتَ التسجيل — فأيُّ تعديلٍ لاحقٍ على ملفٍّ متتبَّعٍ يُنتج
 *   انحرافًا جديدًا. وذلك دورةٌ طبيعيةٌ لا عيبٌ في القيد.
 *
 * التشغيل:  php database/migrations/2027_08_25_component_version_register.php
 * الرجوع :  php database/migrations/2027_08_25_component_version_register.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$TAG = 'ux-1.5.0';

if (in_array('--revert', $argv, true)) {
    $st = $conn->prepare("DELETE FROM `gov_component_versions` WHERE `version_tag` = ?");
    $st->bind_param('s', $TAG); $st->execute();
    echo "↺ حُذف {$st->affected_rows} صفًّا للوسم {$TAG}\n";
    exit(0);
}

/* ══ ① مجموعةُ الملفاتِ المتتبَّعة — من آخرِ إصدارٍ **يحمل قائمة** ═════════
   ولا تُخترع القائمةُ هنا: تُورَث ممن سبق، فيبقى المقامُ نفسَه ويُقارَن. */
$r = $conn->query("SELECT version_tag, files_json FROM `gov_component_versions`
                    WHERE files_json <> '' ORDER BY id DESC LIMIT 1");
$ref = $r ? $r->fetch_assoc() : null;
if (!$ref) { exit("✘ لا إصدارَ سابقٌ يحمل قائمةَ ملفات — لا مقامَ يُورَث\n"); }
$tracked = array_keys((array) json_decode($ref['files_json'], true));
echo "① المقامُ موروثٌ من {$ref['version_tag']}: " . count($tracked) . " ملفًّا\n";

/* ══ ② بصمةُ كلِّ ملفٍّ ثم بصمةُ الإصدارِ مشتقّةً منها ════════════════════ */
$files = array(); $missing = array();
foreach ($tracked as $rel) {
    $path = $ROOT . '/' . $rel;
    if (!is_file($path)) { $missing[] = $rel; continue; }
    $files[$rel] = hash_file('sha256', $path);
}
if ($missing) { echo "② ✘ ملفاتٌ مفقودة: " . implode(' · ', $missing) . "\n"; }
ksort($files);
$fingerprint = hash('sha256', implode("\n", array_map(
    function ($k, $v) { return $k . ':' . $v; }, array_keys($files), array_values($files))));
echo "② بصمةُ الإصدارِ المشتقّة: " . substr($fingerprint, 0, 16) . "…\n";

/* ══ ③ فرقٌ مُعلَنٌ عن المرجع — يُكتب في الملاحظةِ لا يُطوى ═══════════════ */
$prev = (array) json_decode($ref['files_json'], true);
$drift = array();
foreach ($files as $rel => $h) {
    if (!isset($prev[$rel])) { $drift[] = $rel . ' (جديد)'; }
    elseif ($prev[$rel] !== $h) { $drift[] = $rel; }
}
echo "③ منحرفٌ عن {$ref['version_tag']}: " . count($drift)
   . (count($drift) ? ' — ' . implode(' · ', $drift) : '') . "\n";

/* ══ ④ القيد ═════════════════════════════════════════════════════════════ */
$r = $conn->query("SELECT COUNT(*) FROM `gov_component_versions` WHERE `version_tag` = '"
                  . $conn->real_escape_string($TAG) . "'");
if ($r && (int) $r->fetch_row()[0] > 0) {
    echo "④ عطالة: {$TAG} مسجَّلٌ سلفًا — لم يُكرَّر\n";
} else {
    $note = 'INJ-FIX-01 §ب · GAP-26 — إصدارٌ **بقائمةِ ملفاتِه** فيصير الانحرافُ التالي مقيسًا بالملفّ. '
          . 'والمنحرفُ عن ' . $ref['version_tag'] . ': ' . (count($drift) ? implode(' · ', $drift) : 'لا شيء')
          . '. وسابقُه ux-1.4.0 كان بقائمةٍ فارغةٍ فلا يُقارَن به.';
    $json = json_encode($files, JSON_UNESCAPED_SLASHES);
    $st = $conn->prepare(
        "INSERT INTO `gov_component_versions` (`version_tag`,`fingerprint`,`files_json`,`state`,`note`)
         VALUES (?, ?, ?, 'DRAFT', ?)");
    $st->bind_param('ssss', $TAG, $fingerprint, $json, $note);
    if (!$st->execute()) { exit("✘ فشلَ التسجيل: " . $st->error . "\n"); }
    echo "④ سُجِّل {$TAG} بحالة DRAFT (#{$st->insert_id}) — والترقيةُ فعلُ مالك\n";
    $st->close();
}

echo "───────────────────────────────────────────────────────────────\n";
$r = $conn->query("SELECT version_tag, LEFT(fingerprint,16) fp, LENGTH(files_json) n, state
                     FROM `gov_component_versions` ORDER BY id DESC LIMIT 2");
while ($r && $x = $r->fetch_assoc()) {
    printf("  %-10s %s… ملفاتٌ=%s بايت · %s\n", $x['version_tag'], $x['fp'], $x['n'], $x['state']);
}
