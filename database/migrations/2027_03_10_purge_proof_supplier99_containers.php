<?php
/**
 * 2027_03_10 — ستُّ حاوياتٍ لمورّدٍ لا وجودَ له · و600 وحدةٍ موزَّعةٍ على لا شيء
 * ═══════════════════════════════════════════════════════════════════════════
 * **المقيسُ**: في العقد 5 ستُّ حاوياتٍ مستواها «مورد» و`supplier_id = 99`،
 * و**المورّد 99 غيرُ موجودٍ في `suppliers`** (ولا مفتاحَ أجنبيًّا على العمود،
 * ولذلك مرَّت). أنشأها `tests/containers_screen_http_proof.php` في أشواطٍ
 * سابقةٍ: كنسُه كان `DELETE FROM op_containers WHERE level=…` **يفشل صامتًا**
 * (FK الأبوَّةِ الذاتيُّ و`substitute_coverages`، وmysqli مضبوطٌ على عدم الرمي)
 * فبقي صفٌّ لكلِّ شوط. أُصلح الفاحصُ فصار يعكس ما يُنشئ — وهذه بقايا ما قبلَه.
 *
 * **والضررُ ليس في الصفوفِ بل في العدّاد**: كلُّ حاويةٍ سقفُها 100، وأمُّها
 * `#3658` (`CNT-2026-0001`, رئيسيةُ العقد 5) تحمل `allocated_qty = 10,890`
 * وفيها **600 وحدةٍ موزَّعةٍ على مورّدٍ لا وجودَ له**. فأيُّ تقريرِ قدرةٍ يقرأ
 * «الموزَّع» أو «المتاح للتوزيع» على هذا العقدِ يقرأ رقمًا مغلوطًا بـ600.
 *
 * **بإذنٍ صريحٍ من المالك** (2026-08-12): «أزِلها وأُصحّح عدّادَ الأمِّ».
 *
 * والحذفُ **مشروطٌ بثلاثةِ إثباتاتٍ تُقاس هنا**، وإن سقط أحدُها لم يُحذف شيء:
 *   ① المورّدُ 99 غيرُ موجودٍ في `suppliers` — فالصفوفُ لا تمثّل واقعًا.
 *   ② لا ذريّةَ لهذه الحاويات — فلا يُقطع فرعٌ حيّ.
 *   ③ لا استهلاكَ ولا تبديلَ ولا تغطيةَ عليها — فلا تاريخَ يُمحى.
 * ويُنقص من الأمِّ **مجموعُ سقوفِ ما حُذف فعلًا** لا رقمٌ مُفترَض.
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
const PROOF_SUPPLIER = 99;

/* ── ① المورّدُ غيرُ موجود ─────────────────────────────────────────────────── */
$supExists = (int) $one('SELECT COUNT(*) FROM suppliers WHERE id = ' . PROOF_SUPPLIER);
echo "── ① المورّد #" . PROOF_SUPPLIER . " في `suppliers`: {$supExists}\n";
if ($supExists > 0) {
    fwrite(STDERR, "المورّدُ موجودٌ — فالصفوفُ قد تمثّل واقعًا. لا حذف.\n");
    exit(1);
}

/* ── ② الجمهورُ وذريّتُه ───────────────────────────────────────────────────── */
$rows = array();
$r = $db->query('SELECT id, parent_id, cap_qty, consumed_qty, contract_id, container_no
                   FROM op_containers WHERE supplier_id = ' . PROOF_SUPPLIER . ' ORDER BY id');
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
echo '── ② حاوياتُ المورّدِ: ' . count($rows) . "\n";
if (!$rows) { echo "\n✅ لا صفَّ — لا عمل.\n"; exit(0); }

$ids = array();
foreach ($rows as $x) { $ids[] = (int) $x['id']; }
$in = implode(',', $ids);

$kids = (int) $one("SELECT COUNT(*) FROM op_containers WHERE parent_id IN ({$in})");
echo "     ذريّتُها: {$kids}\n";
if ($kids > 0) { fwrite(STDERR, "لها ذريّةٌ — لا تُقطع. لا حذف.\n"); exit(1); }

/* ── ③ لا تاريخَ عليها ────────────────────────────────────────────────────── */
$hist = array(
    'container_consumption' => (int) $one("SELECT COUNT(*) FROM container_consumption WHERE container_id IN ({$in})"),
    'container_swaps'       => (int) $one("SELECT COUNT(*) FROM container_swaps
                                           WHERE container_id IN ({$in}) OR to_container_id IN ({$in})"),
    'substitute_coverages'  => (int) $one("SELECT COUNT(*) FROM substitute_coverages WHERE covered_seat_id IN ({$in})"),
    'operator_rotations'    => (int) $one("SELECT COUNT(*) FROM operator_rotations WHERE container_id IN ({$in})"),
    'seat_assignments'      => (int) $one("SELECT COUNT(*) FROM seat_assignments WHERE container_id IN ({$in})"),
);
echo "── ③ تاريخٌ مرتبطٌ بها:\n";
$histTotal = 0;
foreach ($hist as $t => $n) { echo '     ' . str_pad($t, 24) . $n . "\n"; $histTotal += $n; }
$consumed = 0.0;
foreach ($rows as $x) { $consumed += (float) $x['consumed_qty']; }
echo '     ومستهلَكُها مجموعًا: ' . number_format($consumed, 2) . "\n";
if ($histTotal > 0 || $consumed > 0.001) {
    fwrite(STDERR, "لها تاريخٌ أو استهلاكٌ — لا تُمحى بلا قرارٍ خاصّ.\n");
    exit(1);
}

/* ── ④ الحذفُ وتصحيحُ العدّادِ في معاملةٍ واحدة ───────────────────────────── */
$db->begin_transaction();
$backByParent = array();
$deleted = 0;
foreach ($rows as $x) {
    if ($db->query('DELETE FROM op_containers WHERE id = ' . (int) $x['id']) === false) {
        $db->rollback();
        fwrite(STDERR, 'حذفُ #' . $x['id'] . ' فشل: ' . $db->error . "\n");
        exit(1);
    }
    $deleted++;
    $pid = (int) $x['parent_id'];
    if ($pid > 0) { $backByParent[$pid] = ($backByParent[$pid] ?? 0.0) + (float) $x['cap_qty']; }
}
echo "── ④ حُذف: {$deleted}\n";

foreach ($backByParent as $pid => $back) {
    $before = (float) $one("SELECT allocated_qty FROM op_containers WHERE id = {$pid}");
    $ok = $db->query('UPDATE op_containers
                         SET allocated_qty = GREATEST(0, ROUND(allocated_qty - ' . $back . ', 2))
                       WHERE id = ' . $pid);
    if ($ok === false) {
        $db->rollback();
        fwrite(STDERR, "تصحيحُ عدّادِ #{$pid} فشل: " . $db->error . "\n");
        exit(1);
    }
    $after = (float) $one("SELECT allocated_qty FROM op_containers WHERE id = {$pid}");
    echo "     الأمُّ #{$pid}: " . number_format($before, 2) . ' ⇒ ' . number_format($after, 2)
       . ' (نُقص ' . number_format($back, 2) . ")\n";
}
$db->commit();

/* ── ⑤ الشاهدُ بعدَ العمل ─────────────────────────────────────────────────── */
$left = (int) $one('SELECT COUNT(*) FROM op_containers WHERE supplier_id = ' . PROOF_SUPPLIER);
$orphan = (int) $one('SELECT COUNT(*) FROM op_containers oc
                        LEFT JOIN suppliers s ON s.id = oc.supplier_id
                       WHERE oc.supplier_id IS NOT NULL AND s.id IS NULL');
echo "── ⑤ الباقي بمورّدِ البرهان: {$left}\n";
echo "     وحاوياتٌ بمورّدٍ غيرِ موجودٍ (كلُّ النظام): {$orphan}\n";
if ($left !== 0) { fwrite(STDERR, "بقي صفٌّ — لم يكتمل\n"); exit(1); }

echo "\n✅ أُزيلت {$deleted} حاويةً وهميّةً · وصُحّح عدّادُ الأمِّ بمقدارِ ما حُذف.\n";
exit(0);
