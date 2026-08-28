<?php
/**
 * 2027_12_27_rpr02_restore_0626_0627.php — سطحان أُعيدا بهويّتِهما الصحيحة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تصحيحٌ على فعلي أنا في `2027_12_26`**: شطبتُ `SCR-0626` و`SCR-0627`
 *   بوصفِهما «ملفَّي مكتبةٍ مسجَّلَين شاشتَين» (`GAP-67`) — **وكان الشطبُ خطأً**.
 *
 * ◆ **وما كشفه القياسُ بعدَ الشطب**: `repair01_surfaces` يسمّيهما بغيرِ ما سمّاهما
 *   السجل:
 *   · `SCR-0626` ⇐ `payments.php` — **«طلبات الدفع والسداد»**
 *   · `SCR-0627` ⇐ `depreciation.php` — **«الإهلاك والقيمة الدفترية»**
 *   **فهما سطحان مستهدَفان حقيقيّان**، ⛔ **لا ملفَّا مكتبة**.
 *
 * ◆ **والعطبُ الأصليُّ مطابقةٌ باسمٍ لا يفرّق حالةَ الأحرف**: لا `payments.php`
 *   ولا `depreciation.php` موجودٌ على القرصِ خارجَ `vendor/`، **فالتُقط توأمُهما**
 *   `…/Financial/CashFlow/…/Payments.php` و`…/Financial/Depreciation.php`
 *   وكُتب `route` عليهما. ⇒ **`GAP-67` نفسُه كان أثرَ التقاطٍ خاطئ** لا تسجيلَ
 *   مكتبةٍ متعمَّدًا — وهذه ثالثةُ مرّةٍ تعضُّ فيها مطابقةُ الاسمِ في هذه الجولة.
 *
 * ◆ **فالحقيقةُ المسجَّلة**: سطحٌ مستهدَفٌ **لا ملفَّ له على القرص** ⇒
 *   `GHOST_TARGET` و`on_disk = 0` و`route` فارغ. **وهذا يُرضي القاعدةَ المانعةَ
 *   بحقٍّ** (‏لا مسارَ مكتبةٍ) **ويُعيد الهدفَين إلى الكون** ويشفي تسعةَ مراجعَ
 *   يتيمةٍ في دفترِ الأسطح.
 *
 * ⛔ **والدرسُ**: لا يُشطب صفٌّ قبل أن يُقرأ في كلِّ دفترٍ يذكره — فدفترُ الأسطحِ
 *   كان يحمل هويّتَهما الصحيحةَ طوالَ الوقت.
 *
 * التشغيل: php database/migrations/2027_12_27_rpr02_restore_0626_0627.php
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

$ROWS = array(
    array('SCR-0626', 'payments.php',     'طلبات الدفع والسداد',      'DEP-06'),
    array('SCR-0627', 'depreciation.php', 'الإهلاك والقيمة الدفترية', 'DEP-05'),
);
$WHY = 'اعيد بهويته من repair01_surfaces بعد شطب خاطئ في 2027_12_26: '
     . 'التقط اسمه توأما في vendor بمطابقة لا تفرق حالة الاحرف. '
     . 'سطح مستهدف لا ملف له على القرص ⇒ GHOST_TARGET.';

/* ⛔ **وصفٌّ أُدرج بفراغٍ في تشغيلٍ سابقٍ يُصحَّح لا يُترك** */
$conn->query("UPDATE repair01_screen_registry SET route = NULL
               WHERE screen_id IN ('SCR-0626','SCR-0627') AND route = ''");

$done = 0;
foreach ($ROWS as $r) {
    list($sid, $file, $label, $owner) = $r;
    if ($one("SELECT COUNT(*) FROM repair01_screen_registry WHERE screen_id='" . $sid . "'") > 0) {
        echo "  ◆ `$sid` قائمٌ سلفًا\n"; continue;
    }
    /* ⛔ **ولا يُعاد بمسارِ مكتبة** — و`route` **`NULL` لا فراغٌ**: على العمودِ
         مفتاحٌ فريدٌ `uq_route`، **والفراغُ قيمةٌ واحدةٌ لا تتكرّر** بينما `NULL`
         يتكرّر — ولذلك تحمله الأشباحُ الستّون ومئةٌ كلُّها. وقد رُدَّ الإدراجُ
         الأوّلُ بـ`Duplicate entry '' for key 'uq_route'` **فكشف القاعدةَ**. */
    $st = $conn->prepare("INSERT INTO repair01_screen_registry
        (screen_id, screen_file, route, owner_code, lifecycle, on_disk, origin,
         canonical_label_ar, ghost_verdict, ghost_why, w2_why, src_ref, updated_at)
        VALUES (?, ?, NULL, ?, 'GHOST_TARGET', 0, 'SURFACES', ?, 'RESTORED_TRUE_IDENTITY', ?, ?,
                'repair01_surfaces · 2027_12_27', NOW())");
    $st->bind_param('ssssss', $sid, $file, $owner, $label, $WHY, $WHY);
    if (!$st->execute()) { exit("✘ تعذّرت إعادةُ $sid: {$conn->error}\n"); }
    printf("  ✔ أُعيد `%s` ⇐ %s «%s» · `GHOST_TARGET` بلا مسار\n", $sid, $file, $label);
    $done++;
}

/* المرساةُ تعود مع الصفَّين */
$base = $one("SELECT COUNT(*) FROM repair01_screen_registry
               WHERE origin IN ('SURFACES','DISK','NAV')");
$cur  = $one("SELECT anchor_value FROM repair01_w00_anchor WHERE metric='registry_base'");
if ($cur !== $base) {
    $why = 'اعيد SCR-0626 و SCR-0627 بهويتهما الصحيحة بعد شطب خاطئ — والمقام يعود ' . $base;
    $pkg = 'RPR-02 §11 · تصحيح 2027_12_26 · 2027_12_27_rpr02_restore_0626_0627';
    $st = $conn->prepare("UPDATE repair01_w00_anchor
        SET anchor_value = ?, package_ref = ?, why = ?, anchored_at = NOW(),
            anchored_by = '2027_12_27_rpr02_restore_0626_0627.php'
        WHERE metric = 'registry_base'");
    $st->bind_param('iss', $base, $pkg, $why);
    $st->execute();
    printf("  ✔ عادت مرساةُ `registry_base`: %d ⇐ %d\n", $cur, $base);
}

$orphan = $one("SELECT COUNT(*) FROM repair01_surfaces
                 WHERE screen_id <> '' AND screen_id NOT IN
                       (SELECT screen_id FROM repair01_screen_registry)");
$vendorRows = $one("SELECT COUNT(*) FROM repair01_screen_registry
                     WHERE route LIKE 'vendor/%' OR screen_file LIKE 'vendor/%'");
printf("\n  أُعيد %d · مرجعٌ يتيمٌ في دفترِ الأسطح: **%d** · مسارُ مكتبةٍ مسجَّل: **%d**\n",
       $done, $orphan, $vendorRows);

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ عادا بهويّتِهما — والقاعدةُ المانعةُ باقيةٌ وراضية\n";
