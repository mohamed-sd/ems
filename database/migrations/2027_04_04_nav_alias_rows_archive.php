<?php
/**
 * 2027_04_04_nav_alias_rows_archive.php
 * ═══════════════════════════════════════════════════════════════════════════
 * اسمٌ ثانٍ لوجهةٍ واحدةٍ ليس منظرًا — ⇐ INJ-0512 · INJ-0514
 *
 * ── ما كُشف بالقياس بعد هجرةِ `2027_04_03` ─────────────────────────────────
 * حوّلت تلك الهجرةُ ٢٩ مِرساةً ميتةً إلى `?view=`، وظنَّت أنَّ المعاملَ وجهةٌ.
 * والقياسُ بعدها قال ثلاثةَ أشياء:
 *   ① **صفرٌ من ١٥ ملفًّا يقرأ `$_GET['view']`** — فالمعاملُ ميتٌ كالمِرساة.
 *   ② والمِرساةُ الأصليةُ كانت ميتةً في **٢٩ من ٢٩** (`#n9g10i1` رمزُ مجموعةٍ
 *      يولّده المولِّدُ ولا تُقابله `id` في أيِّ شاشة) — فلم يُتلَف عملٌ قائم.
 *   ③ ونزعُ المِرساةِ جعل الصفَّينِ متطابقَينِ أمام حارسِ التكرار، **فابتُلع
 *      ٢٥ رابطًا**: صفُّه حيٌّ في القاعدةِ والمستخدمُ لا يراه في القائمة.
 *      وهذا أخبثُ من التكرارِ: القاعدةُ تَعِدُ والشاشةُ لا تفي.
 *
 * ── القرار ────────────────────────────────────────────────────────────────
 * **ما يُسمّي شيئًا تعرضه الشاشةُ يبقى مِنظرًا مُعلَنًا** في
 * `includes/nav_views.php` — ثمانيةُ رموزٍ أُثبت كلٌّ منها بمطابقةِ نصٍّ في
 * الملفِّ أو بتعدادٍ في القاعدة. **وما لا يُسمّي شيئًا فهو اسمٌ ثانٍ للوجهةِ
 * نفسِها** — تسعةُ رموزٍ — وصفُّه يُؤرشَف: فوعدٌ لا يُوفّى أسوأُ من لا وعد.
 *
 * ◆ **ولا حذفَ بلا رجعة**: كلُّ صفٍّ يُنسَخ إلى `nav_items_archive_alias`
 *   قبل رفعِه، ويُردُّ بأمرٍ واحد.
 * ◆ والوجهةُ نفسُها **لا تُمسّ**: الشاشةُ باقيةٌ ويبلغها المستخدمُ من الرابطِ
 *   الآخرِ في القائمةِ نفسِها — فلا وجهةَ فُقدت، بل اسمٌ مكرَّرٌ رُفع.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/includes/nav_views.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ اسمٌ ثانٍ لوجهةٍ واحدةٍ ليس منظرًا ══\n\n";
$conn->query('CREATE TABLE IF NOT EXISTS nav_items_archive_alias LIKE nav_items');

$kept = 0; $moved = 0; $rows = array();
$r = $conn->query("SELECT id, role_id, label_ar, route FROM nav_items WHERE route LIKE '%view=%' ORDER BY role_id, id");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }

foreach ($rows as $x) {
    $route = (string) $x['route'];
    if (!preg_match('~[?&]view=([^&#]+)~', $route, $m)) { continue; }
    $code = urldecode($m[1]);
    $file = preg_replace('~[?#].*$~', '', $route);
    $decl = ems_nav_view_declared($file, $code);
    if ($decl !== null) {
        $kept++;
        echo '  ✔ مُعلَنٌ — دور ' . $x['role_id'] . ' «' . mb_substr((string) $x['label_ar'], 0, 34) . "»\n";
        continue;
    }
    /* غيرُ مُعلَنٍ: أُرشِف ثم ارفع — وافحص مُرجَعَ كلِّ خطوة */
    $id = (int) $x['id'];
    if (!$conn->query('INSERT INTO nav_items_archive_alias SELECT * FROM nav_items WHERE id = ' . $id)) {
        echo '  ⚠ تعذّرت الأرشفةُ للصفِّ ' . $id . ' — لم يُرفع: ' . $conn->error . "\n";
        continue;
    }
    if ($conn->query('DELETE FROM nav_items WHERE id = ' . $id) && $conn->affected_rows > 0) {
        $moved++;
        echo '  ▸ اسمٌ ثانٍ — دور ' . $x['role_id'] . ' «' . mb_substr((string) $x['label_ar'], 0, 34)
           . '» ⇐ ' . $route . "\n";
    } else {
        echo '  ⚠ تعذّر الرفعُ للصفِّ ' . $id . ' — والأرشيفُ فيه نسخةٌ: ' . $conn->error . "\n";
    }
}

echo "\n  مناظرُ مُعلَنةٌ بقيت: {$kept} · أسماءٌ ثانيةٌ أُرشفت: {$moved}\n";

/* والتحقّقُ الفوريُّ: لا يبقى في القائمةِ رمزٌ غيرُ مُعلَن */
$bad = 0;
$r = $conn->query("SELECT route FROM nav_items WHERE route LIKE '%view=%'");
while ($r && ($x = $r->fetch_row())) {
    if (preg_match('~[?&]view=([^&#]+)~', $x[0], $m)) {
        $f = preg_replace('~[?#].*$~', '', $x[0]);
        if (ems_nav_view_declared($f, urldecode($m[1])) === null) { $bad++; echo "  ✘ باقٍ غيرُ مُعلَن: {$x[0]}\n"; }
    }
}
echo '  ' . ($bad === 0 ? '✔ لا رمزَ غيرَ مُعلَنٍ في القائمة' : "✘ باقٍ: {$bad}") . "\n";
