<?php
/**
 * tests/w135_vendor_not_a_screen.php — أيمنع السجلُّ صنفَ مكتبةٍ أن يصير شاشةً؟
 * ═══════════════════════════════════════════════════════════════════════════
 * **الواقعةُ التي وُلد منها**: `repair01_ingest` لم يستثنِ `vendor/`، فسُجِّل
 * صنفانِ من `phpoffice/phpspreadsheet` **شاشتَين حيَّتَين** بمالكٍ ومسمًّى عربيٍّ
 * وحكمِ `DOMAIN_SOURCE` — أي **مصدرَي حقيقةٍ للإهلاكِ والدفع**.
 * **وهما اللذان جعلا `Duplicate Source` يبدو حقيقيًّا** في البندَين اللذَين أمر
 * المالكُ بإغلاقهما. ⇒ **والقياسُ أثبت أنَّ التكرارَ لم يكن أصلًا.**
 *
 * ◆ **وحاجبٌ لم يُثبَت رسوبُه ليس حاجبًا** — نصُّ المالك. فالفحصُ يزرع الحالةَ
 *   الخاطئةَ ويطالب الحاجبَ بمسكِها، ثمَّ يكنس ما زرع.
 *
 * التشغيل: php tests/w135_vendor_not_a_screen.php   (خروج 0 = نجاح)
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$pass = 0; $fail = 0;
$ok = function ($c, $m) use (&$pass, &$fail) {
    if ($c) { $pass++; echo "  ✔ $m\n"; } else { $fail++; echo "  ✘ $m\n"; }
};

/* الكاشفُ نفسُه — يُقاس لا يُفترَض */
$detect = function () use ($conn) {
    return (int) $conn->query("SELECT COUNT(*) FROM repair01_screen_registry
        WHERE on_disk = 1 AND ownership_verdict NOT IN ('RETIRE')
          AND (route LIKE 'vendor/%' OR route LIKE '%/vendor/%'
               OR route LIKE 'node_modules/%' OR route LIKE '%/node_modules/%')")->fetch_row()[0];
};

echo "\n══ ① الحالُ النظيفُ يمرّ ══\n";
$ok($detect() === 0, 'صفرُ سطحٍ مسجَّلٍ حيًّا يشير إلى ملفِّ مكتبة');

echo "\n══ ② والحاجبُ يعرف كيف يرسُب — الزرعُ بالقيمةِ الحقيقيّة ══\n";
/* **مسارُ مكتبةٍ حقيقيٌّ آخرُ لا نسخةٌ من المحجوز**: `uq_route` يمنع تكرارَ
   المسار، والصفُّ المتقاعدُ ما يزال يحجز مسارَ `Depreciation.php`. **والزرعُ
   بقيمةٍ محجوزةٍ يُرفض فيُنتج نجاحًا فارغًا في الفحصِ التالي** — وقد أنتجه فعلًا. */
$SEED = 'SCR-T-VEND';  /* ⚠ اثنا عشرَ حرفًا حدُّ العمود — وزرعٌ أطولُ يُرفض في الوضعِ الصارمِ فيُنتج **نجاحًا فارغًا** في الفحصِ التالي */
$conn->query("DELETE FROM repair01_screen_registry WHERE screen_id = '$SEED'");
$seeded = $conn->query("INSERT INTO repair01_screen_registry
    (screen_id, screen_file, route, owner_code, on_disk, surface_kind,
     ownership_verdict, verdict_rule, canonical_label_ar)
    VALUES ('$SEED', 'Amortization.php',
            'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Calculation/Financial/Amortization.php',
            'DEP-05', 1, 'SOURCE', 'DOMAIN_SOURCE',
            'زرع اختبار سالب — يكنس في نهاية الفحص', 'الإهلاك والقيمة الدفترية')");
$ok($seeded, 'زُرع صنفُ مكتبةٍ سطحًا حيًّا بحكمِ DOMAIN_SOURCE' . ($seeded ? '' : ' — ' . $conn->error));
$ok($detect() === 1, '**والكاشفُ أمسكه** — ولولا ذلك لكان جدارًا أبيض');

echo "\n══ ③ ولا يُمسِك البريءَ — سطحُ تطبيقٍ حقيقيٌّ يمرّ ══\n";
$conn->query("UPDATE repair01_screen_registry SET route = 'Finance/_edc_seed_probe.php'  /* مسارُ تطبيقٍ غيرُ محجوز — و`uq_route` يمنع الحجزَ المزدوج */ WHERE screen_id = '$SEED'");
$ok($detect() === 0, 'سطحٌ في شجرةِ التطبيقِ لا يُمسَك — فالحاجبُ يميّز ولا يعمّ');

echo "\n══ ④ الكنس ══\n";
$conn->query("DELETE FROM repair01_screen_registry WHERE screen_id = '$SEED'");
$ok((int) $conn->query("SELECT COUNT(*) FROM repair01_screen_registry WHERE screen_id = '$SEED'")->fetch_row()[0] === 0,
    'كُنس الزرع — ولا أثرَ للفحصِ في المخزن');
$ok($detect() === 0, 'والحالُ عاد نظيفًا');

echo "\n══ ⑤ والمُدخِلُ نفسُه لا يعيد إدخالَه ══\n";
$src = (string) file_get_contents($ROOT . '/tools/repair01_ingest.php');
$ok(strpos($src, "'/vendor/'") !== false, '`repair01_ingest` يستثني vendor صراحةً');
$ok(strpos($src, 'node_modules') !== false, 'ويستثني node_modules');

echo "\n──────────────────────────────────────────────────────────\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
