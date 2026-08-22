<?php
/**
 * 2027_10_24_frd_app005_ladder_entity_map.php
 *   FR-APP-005 · CHG-APP-LADDER-01 — السلّمُ يعرف نوعَ كيانِه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلبُ بنصِّه** (الدفتر · P1): «كلُّ نوعِ كيانٍ **منتَجٍ فعلًا** له سلّمٌ
 *   حاكم — وما لا سلّمَ له **يُوقَف ولا يسقط للاحتياط**» · ومعيارُ القبول
 *   «**صفرُ نوعِ كيانٍ حيٍّ بلا سلّم**».
 *
 * ◆ **والكشفُ من نوعِ «مُثبَتٌ بالمقياس مُعطَّلٌ بالعَلَم»**: `gov_journey_ladders`
 *   يقول **14/14 `ladder_wired = 1`** — و**عمودا `entity_type` و`action`
 *   فارغانِ في الأربعةَ عشرَ كلِّها**. فالسجلُّ يُعلن وصلًا **ولا يحمل الخريطةَ
 *   التي تُحَلُّ بها**: لا يعرف أيُّ نوعِ كيانٍ يخضع لأيِّ سلّم.
 *
 * ◆ **والخريطةُ مشتقّةٌ من مواضعِ النداءِ نفسِها لا مخترَعة**: كلُّ موضعٍ ينادي
 *   `ems_ladder_guard($conn, 'LD-nn', …, 'kind', …)` — **فالزوجُ مكتوبٌ في
 *   الشيفرةِ العاملة**، ويُرفَع إلى السجلِّ كما هو. **تسعةُ أزواجٍ في ثمانيةِ
 *   سلاليم** — ولا يُنسَب نوعٌ لسلّمٍ لا يناديه أحد.
 *
 * ◆ **ولا يُملأ ما لا مصدرَ له**: ستةُ سلاليمَ (LD-01..LD-05) **لا موضعَ
 *   نداءٍ لها في الشجرة** — فتبقى فارغةً **مُعلَنةً** ولا يُختلق لها نوع.
 *
 * التشغيل:  php database/migrations/2027_10_24_frd_app005_ladder_entity_map.php
 * الرجوع :  php database/migrations/2027_10_24_frd_app005_ladder_entity_map.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

function cnt(mysqli $c, $sql) { $r = @$c->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; }

if (in_array('--revert', $argv, true)) {
    $conn->query("UPDATE `gov_journey_ladders` SET `entity_type` = '' WHERE `entity_type` <> ''");
    echo "↺ أُفرغت خريطةُ أنواعِ الكيانات ({$conn->affected_rows})\n";
    exit(0);
}

/* ── ① الخريطةُ تُستخرَج من الشجرةِ لحظةَ التشغيل ─────────────────────────── */
$SKIP = array('/vendor/', '/node_modules/', '/.git/', '/docs/', '/storage/',
              '/tests/', '/tools/', '/database/');
$pairs = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') { continue; }
    $pp = str_replace(DIRECTORY_SEPARATOR, '/', $f->getPathname());
    $skip = false;
    foreach ($SKIP as $k) { if (strpos($pp, $k) !== false) { $skip = true; break; } }
    if ($skip) { continue; }
    $src = (string) @file_get_contents($pp);
    if (strpos($src, 'ems_ladder_guard(') === false) { continue; }
    $rx = '~ems_ladder_guard\(\s*\$\w+\s*,\s*[\'"]([A-Z0-9\-]+)[\'"]\s*,[^,]*,\s*[\'"]([a-z_]+)[\'"]~s';
    if (preg_match_all($rx, $src, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) { $pairs[$x[1]][$x[2]] = true; }
    }
}
ksort($pairs);
$n = 0;
foreach ($pairs as $kinds) { $n += count($kinds); }
printf("① الخريطةُ المشتقّةُ من مواضعِ النداء: **%d زوجًا في %d سلّمًا**\n", $n, count($pairs));
if ($n === 0) {
    exit("⛔ **صفرُ زوجٍ مستخرَج** — ولا تُملأ خريطةٌ من لا شيء. أُوقِف.\n");
}

/* ── ② القيدُ في السجلّ — ولا يُختلَق لما لا نداءَ له ────────────────────── */
$total = cnt($conn, "SELECT COUNT(*) FROM `gov_journey_ladders`");
$before = cnt($conn, "SELECT COUNT(*) FROM `gov_journey_ladders`
                       WHERE COALESCE(`entity_type`,'') <> ''");
printf("② قبل: سلاليم=%d · بنوعِ كيانٍ مُعلَن=%d\n", $total, $before);

$st = $conn->prepare("UPDATE `gov_journey_ladders` SET `entity_type` = ?
                       WHERE `ladder_code` = ?");
if (!$st) { exit("⛔ تعذّر الإعداد: " . $conn->error . "\n"); }
$done = 0; $rows = 0;
foreach ($pairs as $ld => $kinds) {
    /* أكثرُ من نوعٍ لسلّمٍ واحدٍ ⇒ تُكتب مفصولةً — ولا يُختار أحدُها بالتخمين */
    $val = implode(' · ', array_keys($kinds));
    $st->bind_param('ss', $val, $ld);
    if ($st->execute()) { $done++; $rows += $st->affected_rows; }
    printf("   ✔ %-8s ⇐ %s\n", $ld, $val);
}
$st->close();
printf("③ سلاليمُ حُدِّثت: %d (صفوفٌ متأثرة=%d)\n", $done, $rows);

/* ── ③ المصالحةُ — لا صفَّ زِيد ولا حُذف ─────────────────────────────────── */
$after = cnt($conn, "SELECT COUNT(*) FROM `gov_journey_ladders`");
$filled = cnt($conn, "SELECT COUNT(*) FROM `gov_journey_ladders`
                       WHERE COALESCE(`entity_type`,'') <> ''");
$empty  = $after - $filled;
printf("\n④ بعد: سلاليم=%d (%s) · بنوعٍ مُعلَن=%d · **فارغةٌ بلا موضعِ نداء=%d**\n",
       $after, $after === $total ? '✔ لا فقد' : '✘ **فرق**', $filled, $empty);
if ($after !== $total) { exit("⛔ اختلَّ المقام\n"); }

/* ◆ **والفارغُ يُعلَن لا يُملأ**: سلّمٌ لا يناديه موضعٌ لا يُنسَب له نوعٌ بالتخمين */
$r = $conn->query("SELECT `ladder_code` FROM `gov_journey_ladders`
                    WHERE COALESCE(`entity_type`,'') = '' ORDER BY `ladder_code`");
$blank = array();
while ($r && $x = $r->fetch_row()) { $blank[] = $x[0]; }
if ($blank) {
    echo "⑤ **فارغةٌ مُعلَنة** (لا موضعَ نداءٍ لها في الشجرة): " . implode(' · ', $blank) . "\n";
    echo "   ولا يُختلق لها نوعُ كيان — §ثالثًا.\n";
}

/* ── ④ التحقُّقُ من الكتابة — قراءةٌ ثانية ─────────────────────────────── */
if ($filled < $done) {
    exit("⛔ **كتابةٌ مزعومة**: حُدِّث {$done} والمقروءُ {$filled}. أُوقِف.\n");
}

ems_migration_recorded(__FILE__, $conn, 0);
