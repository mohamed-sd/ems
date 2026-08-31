<?php
/**
 * tools/repair01_claim_gate_negative.php — كسرُ حاجبِ G-CLAIM-01 عمدًا وردُّه
 * ═══════════════════════════════════════════════════════════════════════════
 * **FINAL_CLOSE §١·١**: «ويُثبَت بحركةِ عدّادٍ بواحدٍ ثمَّ عودة» — فحاجبٌ لم
 * يُكسَر عمدًا حاجبٌ غيرُ مُثبَت.
 *
 * ◆ أربعُ خطواتٍ مقيسة:
 *   ① عدّادُ `guard_denials(G-CLAIM-01)` قبلَ الكسر = N.
 *   ② رسالةٌ بنسبةٍ لا يُخرجها مقياسٌ (`93.42186٪` — مفردةٌ فريدةٌ بنصِّ
 *      قاعدةِ الاختبارِ السالب) ⇒ يجب أن تُرَدَّ **ويصير العدّادُ N+1**.
 *   ③ رسالةٌ بنسبةٍ يُخرجها المقياسُ حرفًا ⇒ تمرُّ والعدّادُ ثابت.
 *   ④ يُمحى صفُّ الاختبارِ وحدَه ⇒ **يعود العدّادُ N** — ولا أثرَ يبقى.
 *
 * الخروج: 0 الحاجبُ مُثبَتٌ بحركةِ العدّاد · 1 الحاجبُ تجميليّ
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn']; mysqli_set_charset($conn, 'utf8mb4');

$GATE = $ROOT . '/tools/repair01_claim_gate.php';
$FAKE = '93.42186'; // مفردةٌ فريدةٌ — لا يُخرجها أيُّ مقياسٍ ولا تتكرّر في المدوّنة
$count = function () use ($conn) {
    $r = $conn->query("SELECT COUNT(*) c FROM guard_denials WHERE guard_code='G-CLAIM-01'");
    return (int)$r->fetch_assoc()['c'];
};
$run = function ($msg) use ($GATE) {
    $o = array(); $rc = 0;
    exec('"' . PHP_BINARY . '" ' . escapeshellarg($GATE) . ' --msg=' . escapeshellarg($msg) . ' 2>&1', $o, $rc);
    return $rc;
};

echo "═══ G-CLAIM-01 — الإثباتُ بحركةِ العدّادِ بواحدٍ ثمَّ عودة ═══\n";
$ok = true;
$n0 = $count();
echo "  ① العدّادُ قبلَ الكسر: $n0\n";

$rc = $run("اختبارٌ سالبٌ متعمَّد: بلغ المقياسُ {$FAKE}٪");
$n1 = $count();
$p2 = ($rc === 1 && $n1 === $n0 + 1);
echo "  ② نسبةٌ ملفَّقةٌ {$FAKE}٪ ⇒ خروج $rc والعدّادُ $n1 " . ($p2 ? "✔ رُدَّت والعدّادُ تحرّك بواحد" : "✘") . "\n";
$ok = $ok && $p2;

$rc = $run('المقيسُ الصادق: #8 السايدبار 97.3٪');
$n2 = $count();
$p3 = ($rc === 0 && $n2 === $n1);
echo "  ③ نسبةٌ يُخرجها المقياسُ حرفًا ⇒ خروج $rc والعدّادُ $n2 " . ($p3 ? "✔ مرَّت والعدّادُ ثابت" : "✘") . "\n";
$ok = $ok && $p3;

$conn->query("DELETE FROM guard_denials WHERE guard_code='G-CLAIM-01' AND attempted_ref='{$FAKE}٪'");
$n3 = $count();
$p4 = ($n3 === $n0);
echo "  ④ مُحي صفُّ الاختبارِ وحدَه ⇒ العدّادُ $n3 " . ($p4 ? "✔ عاد إلى أصلِه" : "✘ لم يعُد") . "\n";
$ok = $ok && $p4;

echo $ok ? "\n✔ الحاجبُ مُثبَت — تحرّك العدّادُ بواحدٍ ثمَّ عاد\n" : "\n⛔ الحاجبُ تجميليّ\n";
exit($ok ? 0 : 1);
