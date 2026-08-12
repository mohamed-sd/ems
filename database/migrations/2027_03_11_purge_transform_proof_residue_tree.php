<?php
/**
 * 2027_03_11 — 18,700 وحدةَ قدرةٍ وهميّةٍ في العقد 5 من عشرةِ أشواطِ فاحص
 * ═══════════════════════════════════════════════════════════════════════════
 * **المقيسُ**: تحت رئيسيةِ العقد 5 (`#3658`) **عشرُ** حاوياتِ «مورد» سقفُ كلٍّ
 * **1000.00 بالضبط** للمورّد 8 وبلا ملاحظةِ منشأ، ولكلٍّ ذريّةٌ (معدة 600 ⟶
 * مشغّل 300). أوقاتُ إنشائها (2026-08-11 12:40 ⟶ 2026-08-12 04:18) تطابق
 * أشواطَ `tests/container_transform_test.php`: خطوتُه ④ كانت تنادي
 * `OTS::allocate(..., 'مورد', 8, 1000.00)` على **رئيسيةِ العقدِ الحقيقية**،
 * فكلُّ شوطٍ يكتب شجرةً جديدةً في بياناتِ الإنتاج. أُصلح الفاحصُ فصار يبني
 * رئيسيتَه ويكنسها — وهذه بقايا ما قبلَه.
 *
 * **والضررُ في أرقامِ القدرة**: `#3658` كان يحمل `allocated_qty = 10,890` منها
 * **10,000 وحدةٍ موزَّعةٍ على عشرِ حاوياتٍ لا تمثّل تعاقدًا ولا تشغيلًا**، و Σ
 * الشجرةِ كلِّها **18,700 وحدة**. فـ«الموزَّع» و«المتاح للتوزيع» على أهمِّ عقدٍ
 * في النظامِ كانا مغلوطَين — وهما رقمان تُبنى عليهما قراراتُ الإسناد.
 *
 * **بقرارِ المالك** (2026-08-12): «كلُّ البيانات تجريبية — اختر القرارَ المناسب»
 * في شأنِ خللِ Σ. والمناسبُ: **لا تُلمس أرقامُ التعاقدِ ولا التشغيل**، ويُمحى
 * ما لا يمثّل واحدًا منهما. وبعد هذا يبقى خللُ Σ في **23 عقدةً** وهي
 * **تجاوزُ تنفيذٍ حقيقيٌّ** (الأبُ مُشبَعٌ بسقفِه التعاقديِّ والأبناءُ المشتقّون
 * من وقائعِ التشغيلِ يفوقونه) — وذاك حقٌّ يُعلَن لا خللٌ يُصلَح.
 *
 * والحذفُ **مشروطٌ بإثباتاتٍ تُقاس هنا**، وإن سقط أحدُها لم يُحذف شيء:
 *   ① التوقيعُ دقيقٌ: أبٌ واحدٌ · مستوى «مورد» · سقفٌ 1000.00 · بلا ملاحظة.
 *   ② **صفرُ تاريخٍ** على الشجرةِ كلِّها: لا استهلاك ولا تبديل ولا تغطية ولا
 *      دورات ولا مقاعد ولا خطط ولا أداء شهري.
 *   ③ **صفرُ مستهلَكٍ** — فلا ساعةَ عملٍ حقيقيةٍ سُجّلت عليها.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
$one = function ($sql) use ($db) { $r = $db->query($sql); return $r ? $r->fetch_row()[0] : null; };

const MAIN_5 = 3658;

/* ── ① الجمهورُ بتوقيعِه ───────────────────────────────────────────────────── */
$sig = 'parent_id = ' . MAIN_5 . " AND level = 'مورد' AND cap_qty = 1000.00
        AND (origin_note IS NULL OR origin_note = '')";
$roots = array();
$r = $db->query("SELECT id, cap_qty, created_at FROM op_containers WHERE {$sig} ORDER BY id");
while ($r && ($x = $r->fetch_assoc())) { $roots[] = $x; }
echo '── ① جذورُ البقايا: ' . count($roots) . "\n";
if (!$roots) { echo "\n✅ لا بقايا — لا عمل.\n"; exit(0); }
foreach ($roots as $x) { echo '     #' . $x['id'] . ' سقف=' . $x['cap_qty'] . ' · ' . $x['created_at'] . "\n"; }

/* الشجرةُ كاملةً — الأعمقُ آخرًا ليُحذف أوّلًا */
$tree = array(); $cur = array();
foreach ($roots as $x) { $cur[] = (int) $x['id']; }
$tree = $cur;
for ($d = 0; $d < 6; $d++) {
    $r = $db->query('SELECT id FROM op_containers WHERE parent_id IN (' . implode(',', $cur) . ')');
    $next = array();
    while ($r && ($x = $r->fetch_assoc())) { $next[] = (int) $x['id']; }
    if (!$next) { break; }
    $tree = array_merge($tree, $next);
    $cur  = $next;
}
$in = implode(',', $tree);
$sumCap = (float) $one("SELECT ROUND(COALESCE(SUM(cap_qty),0),2) FROM op_containers WHERE id IN ({$in})");
echo '     الشجرةُ كلُّها: ' . count($tree) . ' حاويةً · Σسقوف = ' . number_format($sumCap, 2) . "\n";

/* ── ② صفرُ تاريخٍ ────────────────────────────────────────────────────────── */
$refs = array(
    'container_consumption' => 'container_id',
    'container_swaps'       => 'container_id',
    'substitute_coverages'  => 'covered_seat_id',
    'operator_rotations'    => 'container_id',
    'seat_assignments'      => 'container_id',
    'monthly_performance'   => 'container_id',
);
echo "── ② تاريخٌ مرتبط:\n";
$hist = 0;
foreach ($refs as $t => $col) {
    $n = (int) $one("SELECT COUNT(*) FROM {$t} WHERE {$col} IN ({$in})");
    echo '     ' . str_pad($t, 24) . $n . "\n";
    $hist += $n;
}
$dpl = (int) $one("SELECT COUNT(*) FROM daily_plan_lines
                    WHERE equipment_container_id IN ({$in}) OR operator_container_id IN ({$in})");
echo '     ' . str_pad('daily_plan_lines', 24) . $dpl . "\n";
$hist += $dpl;
$swapTo = (int) $one("SELECT COUNT(*) FROM container_swaps WHERE to_container_id IN ({$in})");
$hist += $swapTo;
if ($hist > 0) { fwrite(STDERR, "للشجرةِ تاريخٌ مرتبطٌ — لا تُمحى بلا قرارٍ خاصّ.\n"); exit(1); }

/* ── ③ صفرُ مستهلَك ──────────────────────────────────────────────────────── */
$consumed = (float) $one("SELECT ROUND(COALESCE(SUM(consumed_qty),0),2) FROM op_containers WHERE id IN ({$in})");
echo '── ③ مستهلَكُ الشجرةِ: ' . number_format($consumed, 2) . "\n";
if ($consumed > 0.001) { fwrite(STDERR, "عليها استهلاكٌ حقيقيٌّ — لا تُمحى.\n"); exit(1); }

/* ── ④ الحذفُ (الأعمقُ أوّلًا) وتصحيحُ عدّادِ الأمِّ ─────────────────────────── */
$backToMain = 0.0;
foreach ($roots as $x) { $backToMain += (float) $x['cap_qty']; }

$db->begin_transaction();
foreach (array_reverse($tree) as $id) {
    if ($db->query('DELETE FROM op_containers WHERE id = ' . (int) $id) === false) {
        $db->rollback();
        fwrite(STDERR, 'حذفُ #' . $id . ' فشل: ' . $db->error . "\n");
        exit(1);
    }
}
$beforeA = (float) $one('SELECT allocated_qty FROM op_containers WHERE id = ' . MAIN_5);
$ok = $db->query('UPDATE op_containers
                     SET allocated_qty = GREATEST(0, ROUND(allocated_qty - ' . $backToMain . ', 2))
                   WHERE id = ' . MAIN_5);
if ($ok === false) {
    $db->rollback();
    fwrite(STDERR, 'تصحيحُ عدّادِ الأمِّ فشل: ' . $db->error . "\n");
    exit(1);
}
$afterA = (float) $one('SELECT allocated_qty FROM op_containers WHERE id = ' . MAIN_5);
$db->commit();

echo '── ④ حُذفت ' . count($tree) . " حاويةً\n";
echo '     الأمُّ #' . MAIN_5 . ': ' . number_format($beforeA, 2) . ' ⇒ ' . number_format($afterA, 2)
   . ' (نُقص ' . number_format($backToMain, 2) . ")\n";

/* ── ⑤ الشاهدُ: الأمُّ متّسقةٌ مع أبنائها الباقين ─────────────────────────── */
$kids = (float) $one('SELECT ROUND(COALESCE(SUM(cap_qty),0),2) FROM op_containers
                       WHERE parent_id = ' . MAIN_5);
echo '── ⑤ Σسقوفِ أبناءِ الأمِّ الباقين: ' . number_format($kids, 2)
   . ' · وموزَّعُها: ' . number_format($afterA, 2) . "\n";
if (abs($kids - $afterA) > 0.01) {
    fwrite(STDERR, "الأمُّ غيرُ متّسقةٍ بعد التصحيح — راجِع\n");
    exit(1);
}
echo "\n✅ أُزيلت " . count($tree) . ' حاويةً و" ' . number_format($sumCap, 2)
   . ' " وحدةَ قدرةٍ وهميّةٍ · والأمُّ متّسقةٌ مع أبنائها.' . "\n";
exit(0);
