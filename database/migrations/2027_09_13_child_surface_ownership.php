<?php
/**
 * 2027_09_13_child_surface_ownership.php
 *   مِلكيةُ السطحِ الابن — INJ-FIX-01 · GAP-20 (بقيّة) و GAP-23
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العيب**: `Suppliers/supplier_profile.php` و`Operations/sites_board.php`
 *   شاشتان ذهبيّتان **بلا حكمِ مِلكية** — وبقيتا في الـ١٢٨ المتبقيةِ لأن
 *   `parent_file` في السجلِّ **فارغٌ في كلِّ الصفوف**، فلم يكن للوراثةِ سند.
 *
 * ◆ **والسندُ يُستخرَج بالقياسِ لا بالظنّ: مَن يربطها؟**
 *   · `supplier_profile.php` ⟵ `Suppliers/suppliers.php` (وهي **OWNER_CONFIRMED
 *     لإدارةِ الموردين**) ⇒ **الابنُ يرث مالكَ أبيه** — وبطاقةُ كيانٍ تُفتح من
 *     سجلِّه لا من السايدبار.
 *   · `sites_board.php` ⟵ `includes/entity_tabs.php` وهو **مكوِّنٌ بلا مالك**،
 *     فلا وراثةَ منه. والسندُ حينئذٍ **مجلَّدُها `Operations/`** ودورُ الاختبارِ
 *     المسجَّلُ لها في `gov_golden_approvals` (**الدور 1 — إدارةُ التشغيل**)،
 *     و`Operations/containers.php` مالكُها إدارةُ التشغيل. **سندان لا واحد.**
 *
 * ◆ ولا يُخترع مالك: لو لم يكن للأبِ مالكٌ ولا للمجلَّدِ نظيرٌ محسومٌ **لبقيت
 *   مُعلَنةً بلا حكم** — فبقاءُ «لا أعرف» أصدقُ من مالكٍ مُخترَع.
 *
 * التشغيل:  php database/migrations/2027_09_13_child_surface_ownership.php
 * الرجوع :  php database/migrations/2027_09_13_child_surface_ownership.php --revert
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

$MARK = 'INJFIX01-CHILD';

if (in_array('--revert', $argv, true)) {
    $conn->query("DELETE FROM `gov_ownership_rulings` WHERE `reason` LIKE '{$MARK}%'");
    echo "↺ حُذف {$conn->affected_rows} حكمًا\n";
    exit(0);
}

/** مالكُ سطحٍ مُحكَمٍ سلفًا — من سجلِّ الظهوراتِ أو من حكمٍ قائم */
function ownerOf($conn, $base)
{
    $b = mb_strtolower($base);
    $st = $conn->prepare("SELECT `owner_dept_ar` FROM `gov_space_appearances`
                           WHERE LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(`route`,'?',1),'/',-1)) = ?
                             AND `cls` = 'OWNED' LIMIT 1");
    $st->bind_param('s', $b); $st->execute();
    $r = $st->get_result()->fetch_row(); $st->close();
    if ($r && trim((string) $r[0]) !== '') { return (string) $r[0]; }
    $st = $conn->prepare("SELECT `owner_after` FROM `gov_ownership_rulings`
                           WHERE LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(`route`,'?',1),'/',-1)) = ?
                             AND `ruling` IN ('OWNER_CONFIRMED','OWNER_ESTABLISHED') LIMIT 1");
    $st->bind_param('s', $b); $st->execute();
    $r = $st->get_result()->fetch_row(); $st->close();
    return ($r && trim((string) $r[0]) !== '') ? (string) $r[0] : null;
}

/* ══ الحالتان — ولكلٍّ سندُها المقيس ═══════════════════════════════════════ */
$CASES = array(
    array('Suppliers/supplier_profile.php', 'suppliers.php', 'PARENT'),
    array('Operations/sites_board.php',     'containers.php', 'SIBLING'),
);

$st = $conn->prepare("INSERT INTO `gov_ownership_rulings`
        (`route`,`owner_before`,`owner_after`,`witness`,`witness_kind`,`ruling`,`reason`,`decided_at`)
        VALUES (?,?,?,?,?,?,?,NOW())");
$done = 0; $skipped = array();
foreach ($CASES as $cse) {
    list($route, $srcBase, $kind) = $cse;
    $b = mb_strtolower(basename($route));

    $ck = $conn->prepare("SELECT COUNT(*) FROM `gov_ownership_rulings`
                           WHERE LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(`route`,'?',1),'/',-1)) = ?");
    $ck->bind_param('s', $b); $ck->execute();
    if ((int) $ck->get_result()->fetch_row()[0] > 0) { $ck->close(); echo "  · {$route}: محكومٌ سلفًا\n"; continue; }
    $ck->close();

    $owner = ownerOf($conn, $srcBase);
    if ($owner === null) { $skipped[] = "{$route} (لا مالكَ لـ{$srcBase})"; continue; }

    $before = 'UNKNOWN';
    $wit = ($kind === 'PARENT')
        ? "الأبُ {$srcBase} — OWNED/OWNER_CONFIRMED"
        : "نظيرُ المجلَّدِ {$srcBase} — OWNED · ودورُ الاختبارِ في gov_golden_approvals";
    $wk  = ($kind === 'PARENT') ? 'DOC_CYCLE' : 'DATA_READ';
    $rul = 'OWNER_ESTABLISHED';
    $why = $MARK . ' · سطحٌ ابنٌ يُفتح من سجلِّه لا من السايدبار — و`parent_file` فارغٌ في '
         . 'السجلِّ كلِّه فاستُخرج الأبُ **بمن يربطه**. ' . $wit;
    $st->bind_param('sssssss', $route, $before, $owner, $wit, $wk, $rul, $why);
    if ($st->execute()) { $done++; echo "  ✔ {$route} ⇐ «{$owner}» ({$kind})\n"; }
    else { echo "  ✘ {$route}: {$st->error}\n"; }
}
$st->close();

echo "───────────────────────────────────────────────────────────────\n";
echo "① أُحكمت: {$done} شاشة\n";
if ($skipped) { echo "◆ **بقيت بلا حكمٍ** (لا سندَ مقيس): " . implode(' · ', $skipped) . "\n"; }
$tot = (int) $conn->query("SELECT COUNT(*) FROM `gov_ownership_rulings`")->fetch_row()[0];
echo "② أحكامُ المِلكيةِ الآن: {$tot}\n";
