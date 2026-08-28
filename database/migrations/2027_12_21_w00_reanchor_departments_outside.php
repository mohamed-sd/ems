<?php
/**
 * 2027_12_21_w00_reanchor_departments_outside.php — المرساةُ تعود إلى ٤
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا هجرةٌ وقد نُقلت المرساةُ بالأداةِ سلفًا**: `repair01_w00_reanchor.php`
 *   يكتب في القاعدةِ ولا يترك أثرًا في الشجرة. **وتغييرٌ لا يُعاد تشغيلُه من
 *   الصفرِ ليس قابلًا لإعادةِ البناء** — و`RPR-03` §٣·٢ يشترط: «بيئةٌ نظيفة ←
 *   تشغيلُ الهجراتِ بترتيبِها ← بذرٌ أدنى ← إقلاعٌ ناجح». ⇒ فالنقلُ يُقيَّد هنا
 *   ليُعاد حتمًا، ⛔ **ولا يبقى فرقٌ بين قاعدةٍ حيّةٍ وقاعدةٍ مبنيّةٍ من دفترِها**.
 *
 * ◆ **والسببُ مقيسٌ لا مُقدَّر**: نقلُ `PLATFORM` إلى سجلٍّ مستقلٍّ
 *   (`2027_12_20`) أخضرَ `repair01_w135_gate` G1 **وأحمرَ `repair01_w0_gate`
 *   G0-05** — وهو الدرسُ `cure-without-gate-reverts`. و`G0-05` لا يحمل ثابتًا
 *   حرفيًّا بل يقرأ هذه المرساة، **وكانت مُعايَرةً على المقامِ الفاسدِ نفسِه**:
 *   نصُّ `why` فيها يقولها — «كانت ٤ فأُضيف `PLATFORM` رمزًا».
 *
 * ◆ **والقيمةُ الحاكمةُ ٤ نصًّا** — `AMD-01` ملحق أ·٥: *«واحدٌ وعشرون رمزًا:
 *   سبعَ عشرةَ إدارةً `DEP-01`..`DEP-17` وأربعةٌ خارجَ التعداد: `EX-CEO` ·
 *   `EX-DVP` · `WS-MY` · `IAF`»*. ⛔ **ولم يُرخَ الحاجبُ ولا حُذف شرطُه** —
 *   صُحِّح مقامُه إلى قيمةِ النصِّ الحاكم.
 *
 * ⛔ **ولا تُرسى قيمةٌ لا تُقاس**: يُقاس الحيُّ أوّلًا، فإن خالف المطلوبَ رُدَّت
 *   الهجرة — **فمن يُرسي مقامًا لا يقيسه يرسي دعوى**.
 *
 * التشغيل: php database/migrations/2027_12_21_w00_reanchor_departments_outside.php
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

$WANT = 4;

/* ① يُقاس الحيُّ — ⛔ ولا تُرسى قيمةٌ لا تُقاس ────────────────────────────── */
$r = $conn->query("SELECT COUNT(*) FROM repair01_departments
                    WHERE sector = 'OUTSIDE' AND display_order IS NULL");
$live = $r ? (int) $r->fetch_row()[0] : -1;
printf("  المقيسُ حيًّا: %d · المطلوب: %d\n", $live, $WANT);
if ($live !== $WANT) {
    exit("⛔ **المقيسُ يخالف المطلوب** — ولا تُرسى دعوى.\n"
       . "   شغِّلْ أوّلًا: php database/migrations/2027_12_20_rpr02_platform_register.php\n");
}

/* ② الترسيةُ — بسببٍ ومرجعِ حزمةٍ، والقاعدةُ الصلبةُ في الجدولِ تردُّ الفارغَ */
$r = $conn->query("SELECT anchor_value FROM repair01_w00_anchor
                    WHERE metric = 'departments_outside'");
if (!$r || !$r->num_rows) { exit("⛔ مقامٌ غيرُ مرسًى: departments_outside\n"); }
$cur = (int) $r->fetch_row()[0];
if ($cur === $WANT) {
    echo "  ◆ المرساةُ عند $WANT سلفًا\n";
} else {
    $why = 'نقل PLATFORM الى سجل مستقل بحكم MASTER_EXEC §5-2 بعد ان قيس انه يفسد مقامين '
         . '(w135_gate G1 يرسب · و baseline_xlsx_build.php:154 يطبع 22 تحت عنوان يعدد 21). '
         . 'فعادت القيمة الى 4: EX-CEO · EX-DVP · IAF · WS-MY — وهي قيمة النص الحاكم '
         . 'في AMD-01 ملحق ا-5. ولم يرخ الحاجب ولا حذف شرطه.';
    $pkg = 'MASTER_EXEC §5-2 · AMD-01 المرحلة 5 · 2027_12_20_rpr02_platform_register';
    $st = $conn->prepare("UPDATE repair01_w00_anchor
        SET anchor_value = ?, package_ref = ?, why = ?, anchored_at = NOW(),
            anchored_by = '2027_12_21_w00_reanchor_departments_outside.php'
        WHERE metric = 'departments_outside'");
    $st->bind_param('iss', $WANT, $pkg, $why);
    if (!$st->execute()) { exit("✘ تعذّرت الترسية: {$conn->error}\n"); }
    printf("  ✔ نُقلت المرساة: %d ⇐ %d — بسببٍ ومرجعِ حزمة\n", $cur, $WANT);
}

/* ③ ⛔ ولا يُصدَّق الكاتبُ على كلمتِه ─────────────────────────────────────── */
$r = $conn->query("SELECT anchor_value FROM repair01_w00_anchor
                    WHERE metric = 'departments_outside'");
$back = (int) $r->fetch_row()[0];
printf("  ✔ أُعيدت القراءة: المرساةُ %d\n", $back);
if ($back !== $WANT) { exit("✘ لم تُنقل\n"); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ المرساةُ تطابق النصَّ الحاكم — والبناءُ من الصفرِ يُعيدها حتمًا\n";
