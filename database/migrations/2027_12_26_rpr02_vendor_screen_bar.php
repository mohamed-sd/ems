<?php
/**
 * 2027_12_26_rpr02_vendor_screen_bar.php — قاعدةٌ مانعةٌ دائمةٌ لمسارِ المكتبات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما يوجبه الأمر** — `RPR-02` §١١: *«**ولا يُسجَّل سطحًا أيُّ مسارٍ تحت
 *   مجلَّدِ المكتباتِ الخارجيّة: قاعدةٌ مانعةٌ دائمةٌ لا معالجةُ حالتَين** —
 *   فالحالةُ تُعالَج مرّةً والقاعدةُ تمنعها دائمًا»* · و§١٢: `ملفُّ مكتبةٍ
 *   مسجَّلٌ شاشةً = صفر · **وقاعدةٌ مانعةٌ مفعَّلة**` (‏المقيسُ عندَ الإصدار:
 *   ٢ · لا قاعدة).
 *
 * ◆ **والقاعدةُ في المخطَّطِ لا في الأداة**: كتّابُ السجلِّ **كثيرون** —
 *   `repair01_w10_negative` و`w11_apply` و`w11_negative` و`w12_apply` و
 *   `w13_apply` وغيرُها. فحارسٌ في أداةٍ واحدةٍ يحرس تلك الأداةَ وحدَها،
 *   **وقيدُ `CHECK` يحرس كلَّ كاتبٍ حاضرٍ وقادم**. ⇒ **المنعُ حيث لا يُلتَفّ عليه**.
 *
 * ◆ **والترتيبُ حتميّ**: تُعالَج الحالتان أوّلًا ثمَّ تُقفل القاعدةُ — فقاعدةٌ
 *   فوقَ صفَّين خارقَين تُرَدُّ فورًا. **وهو الترتيبُ المحروقُ في هذه الجولةِ
 *   كلِّها**: الموضعُ ← الملءُ ← القفل.
 *
 * ◆ **والمرساةُ تتبع الحذفَ لا العكس**: الصفّان داخلَ مقامِ `registry_base`
 *   المرسى عند **٦٥١** (`origin IN (SURFACES,DISK,NAV)`) — فحذفُهما يجعله **٦٤٩**،
 *   ⛔ **وحاجبٌ يقرأ ٦٥١ يحمرُّ بعلاجٍ صحيح** (‏الدرسُ `cure-without-gate-reverts`).
 *   ⇒ تُنقل المرساةُ **بسببٍ مكتوبٍ في الهجرةِ نفسِها** لا يدويًّا بعدَها.
 *
 * التشغيل: php database/migrations/2027_12_26_rpr02_vendor_screen_bar.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);
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
$one = function ($sql) use ($conn) { $r = $conn->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; };

/* ① الحالةُ تُعالَج مرّة — وتُسمّى قبلَ حذفِها ─────────────────────────── */
$BAR = "route LIKE 'vendor/%' OR route LIKE 'node\\_modules/%'
        OR screen_file LIKE 'vendor/%' OR screen_file LIKE 'node\\_modules/%'";
$r = $conn->query("SELECT screen_id, route FROM repair01_screen_registry WHERE $BAR");
$victims = array();
while ($x = $r->fetch_assoc()) { $victims[] = $x['screen_id'] . ' · ' . $x['route']; }
$baseBefore = $one("SELECT COUNT(*) FROM repair01_screen_registry
                     WHERE origin IN ('SURFACES','DISK','NAV')");
if ($victims) {
    foreach ($victims as $v) { echo "  ⛔ يُشطب: " . $v . "\n"; }
    if (!$conn->query("DELETE FROM repair01_screen_registry WHERE $BAR")) {
        exit("✘ تعذّر الشطب: {$conn->error}\n");
    }
    printf("  ✔ شُطب %d صفًّا — والحالةُ عولجت مرّةً\n", count($victims));
} else {
    echo "  ◆ لا صفَّ مكتبةٍ مسجَّلًا — الحالةُ معالَجةٌ سلفًا\n";
}
$baseAfter = $one("SELECT COUNT(*) FROM repair01_screen_registry
                    WHERE origin IN ('SURFACES','DISK','NAV')");

/* ② المرساةُ تتبع الحذف — ⛔ ولا يُترك حاجبٌ يحمرُّ بعلاجٍ صحيح ────────── */
if ($baseAfter !== $baseBefore) {
    $why = 'شُطب ' . count($victims) . ' ملفَّ مكتبةٍ خارجيّةٍ كان مسجَّلًا شاشةً — '
         . 'RPR-02 §11 قاعدة مانعة دائمة و§12 المقياس صفر. والمقام ' . $baseBefore
         . ' كان يعدهما، فعاد ' . $baseAfter . ' بحذفهما لا بتغيير قاعدة العد.';
    $pkg = 'RPR-02 §11 · §12 · 2027_12_26_rpr02_vendor_screen_bar';
    $st = $conn->prepare("UPDATE repair01_w00_anchor
        SET anchor_value = ?, package_ref = ?, why = ?, anchored_at = NOW(),
            anchored_by = '2027_12_26_rpr02_vendor_screen_bar.php'
        WHERE metric = 'registry_base'");
    $st->bind_param('iss', $baseAfter, $pkg, $why);
    if (!$st->execute()) { exit("✘ تعذّرت الترسية: {$conn->error}\n"); }
    printf("  ✔ نُقلت مرساةُ `registry_base`: %d ⇐ %d — بسببٍ مكتوب\n", $baseBefore, $baseAfter);
}

/* ③ ثمَّ تُقفل القاعدةُ — والقفلُ يلي صدقَ المحتوى ───────────────────────── */
$has = $one("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'chk_scr_no_vendor'");
if ($has > 0) {
    echo "  ◆ القاعدةُ قائمةٌ سلفًا\n";
} else {
    $viol = $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE $BAR");
    if ($viol > 0) { exit("⛔ **$viol صفًّا يخرق القاعدة** — ولا تُقفل فوقَ خرقٍ قائم\n"); }
    $ok = $conn->query("ALTER TABLE `repair01_screen_registry`
        ADD CONSTRAINT `chk_scr_no_vendor` CHECK (
             `route`       NOT LIKE 'vendor/%'
         AND `route`       NOT LIKE 'node\\_modules/%'
         AND `screen_file` NOT LIKE 'vendor/%'
         AND `screen_file` NOT LIKE 'node\\_modules/%')");
    if (!$ok) { exit("✘ تعذّرت القاعدة: {$conn->error}\n"); }
    echo "  ✔ قُفلت القاعدةُ المانعةُ الدائمة: `chk_scr_no_vendor`\n";
}

/* ④ الإثباتُ بالكسر — ⛔ وقاعدةٌ لم تُختبر دعوى ─────────────────────────── */
$probe = @$conn->query("INSERT INTO repair01_screen_registry (screen_id, screen_file, route, origin)
                        VALUES ('SCR-PROBE-V', 'x.php', 'vendor/probe/x.php', 'DISK')");
if ($probe) {
    $conn->query("DELETE FROM repair01_screen_registry WHERE screen_id = 'SCR-PROBE-V'");
    exit("⛔ **القاعدةُ لم تمنع** — وقاعدةٌ لا تردُّ ليست قاعدة\n");
}
echo "  ✔ **مُثبَتةٌ بالكسر**: محاولةُ تسجيلِ مسارِ مكتبةٍ رُدَّت — " . substr($conn->error, 0, 60) . "\n";

printf("\n  ملفُّ مكتبةٍ مسجَّلٌ شاشةً: **%d** · والقاعدةُ **مفعَّلةٌ ومُثبَتة**\n",
       $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE $BAR"));

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ الحالةُ عولجت مرّةً · والقاعدةُ تمنعها دائمًا\n";
