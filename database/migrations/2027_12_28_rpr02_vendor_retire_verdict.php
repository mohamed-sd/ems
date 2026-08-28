<?php
/**
 * 2027_12_28_rpr02_vendor_retire_verdict.php — مسارُ المكتبةِ يُوسَم `RETIRE`
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما كشفه القياسُ أخيرًا — وهو الحكمُ الصحيحُ الذي كان قائمًا**:
 *   `repair01_w135_gate` `G2` يعُدُّ مسارَ المكتبةِ **بشرطِ
 *   `ownership_verdict NOT IN ('RETIRE')`**. ⇒ فالنظامُ **لم يكن يتجاهل
 *   `GAP-67`**: كان يحمل الصفَّين موسومَين `RETIRE` — **حكمٌ مسجَّلٌ لا إهمال**.
 *
 * ◆ **وثلاثُ محاولاتٍ خاطئةٍ سبقت هذا الفهم — تُسجَّل ولا تُخفى**:
 *   ① **شطبُهما** (`2027_12_26`) ⇒ تسعةُ مراجعَ يتيمةٍ وأربعةُ حواجبَ حمراء.
 *   ② **إعادتُهما `GHOST_TARGET`** (`2027_12_27`) ⇒ خالف المُشتَقَّ فرسَب
 *      `W2-06` (‏الشبحُ ١٦٠ مُشتقًّا و١٦٢ في السجل).
 *   ③ **إعادةُ اشتقاقِ الكونِ** بـ`w2_apply` ⇒ استعادت الصفَّين **ودهست أحكامَ
 *      موجاتٍ لاحقةٍ** فسقطت `w10` و`w12` — فأُعيد الشلّالُ `w3..w15`.
 *   ⇒ **والصوابُ كان أصغرَ الثلاثة**: وسمٌ واحدٌ على عمودٍ واحد.
 *
 * ◆ **والدرسُ الحاكم**: `GAP-67` **لم يكن عطبًا مفتوحًا** بل **حكمًا مسجَّلًا
 *   قرأتُه أنا عطبًا**. ⛔ **فقبل أن يُعالَج رقمٌ يُقرأ الحاجبُ الذي أنتجه** —
 *   فالحاجبُ يقول بشرطِه ما يقبله، وقراءةُ الرقمِ وحدَه بلا شرطِه تُنتج علاجًا
 *   لمرضٍ غيرِ قائم.
 *
 * التشغيل: php database/migrations/2027_12_28_rpr02_vendor_retire_verdict.php
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

$VEND = "route LIKE 'vendor/%' OR route LIKE '%/vendor/%'
      OR route LIKE 'node\\_modules/%' OR route LIKE '%/node\\_modules/%'";

$before = $one("SELECT COUNT(*) FROM repair01_screen_registry
                 WHERE ($VEND) AND COALESCE(ownership_verdict,'') <> 'RETIRE'");
printf("  مسارُ مكتبةٍ بلا وسمِ `RETIRE`: **%d**\n", $before);

if ($before > 0) {
    $why = 'ملف مكتبة خارجية لا يملك حقيقة اعمال — RPR-02 §11 · GAP-67. '
         . 'والوسم RETIRE هو الحكم المسجل الذي يقرؤه w135/G2، لا الشطب.';
    $ok = $conn->query("UPDATE repair01_screen_registry
        /* ⛔ **ولا حكمَ بلا قاعدتِه**: القيدُ `chk_w135_why` في المخطَّطِ يشترط
             `verdict_rule <> ''` مع كلِّ `ownership_verdict` — **فردَّ أوّلَ
             تحديثٍ كتب السببَ في `w2_why` وحدَه**. والقاعدةُ محقّةٌ: حكمٌ بلا
             قاعدةٍ مكتوبةٍ لا يُعرَف على أيِّ أساسٍ صدر. */
        SET ownership_verdict = 'RETIRE',
            verdict_rule = 'RPR-02 §11 · GAP-67 — vendor not a screen',
            ghost_verdict = 'VENDOR_NOT_A_SCREEN',
            w2_why = '" . $conn->real_escape_string($why) . "',
            updated_at = NOW()
      WHERE ($VEND) AND COALESCE(ownership_verdict,'') <> 'RETIRE'");
    if (!$ok) { exit("✘ تعذّر الوسم: {$conn->error}\n"); }
    printf("  ✔ وُسم %d صفًّا `RETIRE` بسببٍ مكتوب\n", $conn->affected_rows);
}

/* ⛔ ولا يُصدَّق الكاتبُ على كلمتِه */
$after = $one("SELECT COUNT(*) FROM repair01_screen_registry
                WHERE on_disk = 1 AND ownership_verdict NOT IN ('RETIRE') AND ($VEND)");
printf("  ✔ أُعيدت القراءة: ما يعُدُّه `w135/G2` الآن = **%d**\n", $after);

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ الحكمُ المسجَّلُ أُعيد — ولم يكن `GAP-67` عطبًا مفتوحًا\n";
