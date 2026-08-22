<?php
/**
 * tools/injfrd66_xc06_columns_gate.php — بوابةُ XC-06: بوابتا الأعمدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «لكلِّ حقلٍ في المرجعَين **هدفٌ في النظام أو سببٌ موثَّقٌ**
 *   لعدمِ ظهوره — 589/589 و828/828 · **صفرُ حقلٍ بلا حكم**».
 *
 * ◆ **والمصدرُ `gov_field_trace` في القاعدةِ لا مصنَّفٌ في التنزيلات** —
 *   فبوابةٌ تقرأ ملفًّا خارجَ الشجرةِ لا تُعاد على جهازٍ آخر، ويصير «الأخضرُ»
 *   رهنَ حاسوبٍ بعينِه.
 *
 * ◆ **وثلاثةُ أشياءَ تُقاس لا واحد**:
 *   ① **المقامان** — 589 و828. ومقامٌ مغلوطٌ يُبطل كلَّ نسبةٍ فوقَه.
 *   ② **الحكم** — لكلِّ حقلٍ درجةٌ من العشر. و«بلا حكم» يعني حقلًا لا يُعرف
 *      أين يذهب في النظام.
 *   ③ **قاعدةُ الظهور** — وهي «السببُ الموثَّق». وحقلٌ له درجةٌ بلا قاعدةِ
 *      ظهورٍ **نصفُ حكم**: يُعرف صنفُه ولا يُعرف أين يُرى أو لِمَ لا يُرى.
 *
 * ◆ **والدرجاتُ العشرُ مقفلة**: درجةٌ خارجَها تعني تلوُّثًا في المصدرِ لا
 *   توسُّعًا في الحكم — فتُرصد ولا تُبتلع.
 *
 * ◆ قراءةٌ خالصة — لا كتابةَ في القاعدةِ إطلاقًا.
 *
 * التشغيل:
 *   php tools/injfrd66_xc06_columns_gate.php          التقرير
 *   php tools/injfrd66_xc06_columns_gate.php --gate   رمزُ خروجٍ 1 عند أيِّ نقص
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$GATE = in_array('--gate', $argv, true);

/* الدرجاتُ العشرُ وأعدادُها في المرجعِ الحاكم — البابُ الثامن */
$GRADES = array(
    'إدخال أعمال'                 => 605,
    'مرجعي فقط'                   => 189,
    'مستورد للقراءة من مالكه'     => 188,
    'أثر تدقيقي أو بيانات وصفية'  => 126,
    'مشتق محسوب'                  => 97,
    'مفتاح خارجي موروث'           => 96,
    'مرجع من قائمة'               => 43,
    'موروث بالإسناد من الأب'      => 32,
    'مفتاح أساسي مولَّد'           => 31,
    'لقطة تعاقدية'                => 10,
);
$DENOM = array('المبيعات' => 589, 'الموردين' => 828);

$q = @mysqli_query($conn, "SELECT 1 FROM information_schema.TABLES
                            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_field_trace'");
if (!$q || !mysqli_num_rows($q)) {
    fwrite(STDERR, "✘ `gov_field_trace` غيرُ موجود — شغّلْ هجرةَ 2027_11_02 أولًا\n");
    exit(2);
}

$fail = 0;
$num = static function (string $sql) use ($conn): int {
    $r = @mysqli_query($conn, $sql);
    return $r ? (int) mysqli_fetch_row($r)[0] : -1;
};

echo "\n═══ INJ-FRD-01 · XC-06 — بوابتا الأعمدة ═══\n\n";

/* ── ① المقامان ───────────────────────────────────────────────────────── */
echo "① المقامان:\n";
$res = @mysqli_query($conn, "SELECT book, COUNT(*) n FROM gov_field_trace GROUP BY book ORDER BY book");
$seen = array();
while ($res && ($x = mysqli_fetch_assoc($res))) {
    $want = 0;
    foreach ($DENOM as $k => $v) { if (mb_strpos($x['book'], $k) !== false) { $want = $v; } }
    $ok = ((int) $x['n'] === $want);
    $seen[$x['book']] = (int) $x['n'];
    printf("   %s %-24s %d/%d\n", $ok ? '✔' : '✘', $x['book'], (int) $x['n'], $want);
    if (!$ok) { $fail++; }
}
$tot = array_sum($seen);
printf("   %s %-24s %d/1417\n", $tot === 1417 ? '✔' : '✘', 'الإجمالي', $tot);
if ($tot !== 1417) { $fail++; }

/* ── ② صفرُ حقلٍ بلا حكم ─────────────────────────────────────────────── */
echo "\n② الحكمُ لكلِّ حقل:\n";
$noJudge = $num("SELECT COUNT(*) FROM gov_field_trace WHERE to_be='' OR to_be='—'");
printf("   %s حقولٌ بلا حكم: %d\n", $noJudge === 0 ? '✔' : '✘', $noJudge);
if ($noJudge !== 0) { $fail++; }

/* ── ③ السببُ الموثَّق: قاعدةُ الظهور ─────────────────────────────────── */
echo "\n③ السببُ الموثَّق (قاعدةُ الظهور):\n";
$noRule = $num("SELECT COUNT(*) FROM gov_field_trace WHERE visibility_rule='' OR visibility_rule='—'");
printf("   %s حقولٌ بلا قاعدةِ ظهور: %d\n", $noRule === 0 ? '✔' : '✘', $noRule);
if ($noRule !== 0) { $fail++; }

/* ── ④ الدرجاتُ العشرُ وأعدادُها ──────────────────────────────────────── */
echo "\n④ الدرجاتُ العشرُ مقابلَ المرجع:\n";
$res = @mysqli_query($conn, "SELECT to_be, COUNT(*) n FROM gov_field_trace GROUP BY to_be ORDER BY n DESC");
$live = array();
while ($res && ($x = mysqli_fetch_assoc($res))) { $live[$x['to_be']] = (int) $x['n']; }
foreach ($GRADES as $g => $want) {
    $got = $live[$g] ?? 0;
    $ok  = ($got === $want);
    printf("   %s %-32s %4d / %4d\n", $ok ? '✔' : '✘', $g, $got, $want);
    if (!$ok) { $fail++; }
    unset($live[$g]);
}
if ($live) {
    echo "\n   ✘ درجاتٌ خارجَ العشرِ المقفلة:\n";
    foreach ($live as $g => $n) { printf("      «%s» ×%d\n", $g, $n); $fail++; }
}

/* ── ⑤ المتطلبُ الحاكمُ لكلِّ حقل ─────────────────────────────────────── */
echo "\n⑤ المتطلبُ الحاكم:\n";
$noReq = $num("SELECT COUNT(*) FROM gov_field_trace WHERE req_id='' OR req_id='—'");
printf("   %s حقولٌ بلا متطلبٍ حاكم: %d\n", $noReq === 0 ? '✔' : '✘', $noReq);
if ($noReq !== 0) { $fail++; }
$reqs = $num("SELECT COUNT(DISTINCT req_id) FROM gov_field_trace");
printf("   ○ متطلباتٌ حاكمةٌ متمايزة: %d من 66\n", $reqs);

/* المتطلبُ الحاكمُ يجب أن يكون أحدَ الستةِ والستين — لا رمزًا مخترَعًا */
$state = json_decode((string) @file_get_contents($ROOT . '/tools/injfrd66_tasks.json'), true);
if (is_array($state)) {
    $known = array_flip(array_column($state, 'id'));
    $res = @mysqli_query($conn, "SELECT DISTINCT req_id FROM gov_field_trace WHERE req_id<>''");
    $alien = array();
    while ($res && ($x = mysqli_fetch_assoc($res))) {
        if (!isset($known[$x['req_id']])) { $alien[] = $x['req_id']; }
    }
    printf("   %s رموزٌ حاكمةٌ خارجَ الستةِ والستين: %d %s\n",
        empty($alien) ? '✔' : '✘', count($alien), $alien ? '(' . implode('، ', $alien) . ')' : '');
    if ($alien) { $fail++; }
}

printf("\n%s  XC-06 — %d نقصًا\n\n", $fail === 0 ? '✔' : '✘', $fail);
exit($GATE && $fail > 0 ? 1 : 0);
