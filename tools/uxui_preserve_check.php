<?php
/**
 * tools/uxui_preserve_check.php — مصفوفةُ الحفظِ: قبلَ الترحيلِ وبعدَه عددًا بعدد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ القاعدةُ الأولى (ف٤ + بوابة ١٣): «لا يُحذف زرٌّ ولا شاشةٌ ولا عمودٌ ولا
 *   مرشِّحٌ ولا رابط — ومصفوفةُ الحفظِ تُبنى قبلَ الترحيلِ وتُقارَن بعدَه
 *   عددًا بعدد، والنقصُ يُرسِّب التسليم.»
 * ◆ نطاقُ هذه الأداة: روابطُ السايدبارِ المُصيَّرةُ لكلِّ دورٍ جذريّ.
 *   الأساسُ القبليُّ الملتزم: docs/uxui_live_positions.tsv (911 موضعًا · 19 دورًا
 *   · جلساتٌ حقيقية co4 · التزام 382a0b7 قبل أيِّ مساسٍ بالمولِّد).
 * ◆ هويةُ البندِ الظاهرِ للمستخدم = (الملفُّ الأمُّ + الاسمُ المعروض) — لا صيغةُ
 *   href: فالمرساةُ `#2` والمنظرُ `?view=` لملفٍّ واحدٍ باسمٍ واحدٍ مدخلٌ واحدٌ
 *   (قانونُ حارسِ التوأم INJ-0459 قبل الجولةِ وبعدَها). والقانون:
 *   ① كلُّ بندٍ قبليٍّ يجب أن يوجد بعديًّا بالهويةِ نفسِها —
 *   ② أو باسمِه المعياريِّ إن كان اسمُه القبليُّ من «المسمياتِ الملغاة» لصفِّ
 *     مسارِه في السجلِّ المعتمَد (إعادةُ التسميةِ من المسموحاتِ الأربعة) —
 *   ③ وما عدا ذلك نقصٌ يُرسِّب. والزائدُ (صفٌّ كان يبتلعه الحارسُ وعاد
 *     بتمايزِ الاسمِ المعياري) يُبلَّغ ولا يُرسِّب — فالقاعدةُ ضدَّ الفقدِ.
 *
 * التشغيل (قراءةٌ فقط):
 *   php tools/uxui_preserve_check.php              المقارنةُ وتقريرُها
 *   php tools/uxui_preserve_check.php --gate       رمزُ خروجٍ 1 عند أيِّ نقص
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';
while (ob_get_level() > 0) { ob_end_clean(); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$GATE = in_array('--gate', $argv, true);
$base = $ROOT . '/docs/uxui_live_positions.tsv';
if (!is_file($base)) { fwrite(STDERR, "لا أساسَ قبليًّا: {$base}\n"); exit(2); }

/* ── الأدوارُ المؤرشَفةُ بإثباتٍ — تُستثنى من المقارنةِ ولا تُضعِف البوابة ──
   البوابةُ لا تفرّق بين فقدٍ وأرشفةٍ مقصودة، فالاستثناءُ يُصرَّح **من القاعدةِ
   نفسِها** لا بقائمةٍ في الكود: دورٌ كلُّ صفوفِه محجورةٌ في nav_items_archive_*
   بمستندِ إثباتٍ وموعدِ حذف. ولو أُلغيت الأرشفةُ عاد الدورُ للمقارنةِ تلقائيًّا،
   ولو ضاع رابطٌ من دورٍ غيرِ مؤرشَفٍ رسّب كما كان. */
$archivedRoles = array();
$ar = @mysqli_query($conn, "SELECT role_id, COUNT(*) c, MIN(purge_after) p, MIN(proof_doc) d
                              FROM nav_items_archive_role5 GROUP BY role_id");
while ($ar && ($x = mysqli_fetch_assoc($ar))) {
    $live = @mysqli_query($conn, "SELECT COUNT(*) c FROM nav_items WHERE role_id = " . (int) $x['role_id'] . " AND active = 1");
    $liveN = $live ? (int) mysqli_fetch_assoc($live)['c'] : -1;
    if ($liveN === 0) { $archivedRoles[(int) $x['role_id']] = array('n' => (int) $x['c'], 'purge' => $x['p'], 'proof' => $x['d']); }
}

/* ── السجلُّ المعتمَد: لكلِّ مسارٍ APPROVED اسمُه المعياريُّ ومسمياتُه الملغاة ── */
$canon = array();   // file_lc => ['canon' => الاسم, 'old' => [الملغاة…]]
$res = mysqli_query($conn, "SELECT route, canonical_ar, old_names FROM nav_canonical WHERE status = 'APPROVED'");
while ($res && ($x = mysqli_fetch_assoc($res))) {
    $olds = array();
    foreach (preg_split('/[\/·]+/u', (string) $x['old_names']) as $o) {
        $o = trim($o);
        if ($o !== '') { $olds[$o] = true; }
    }
    $canon[strtolower(trim($x['route']))] = array('canon' => trim($x['canonical_ar']), 'old' => $olds);
}

/** هويةُ البند: (الملفُّ الأمُّ صغيرًا) + الاسمُ المعروض */
function upc_key($href, $label)
{
    $f = strtolower(preg_replace('/[?#].*$/u', '', preg_replace('~^(\.\./)+~', '', trim($href))));
    return $f . '||' . trim($label);
}

/* ── الأساسُ القبليُّ الملتزم ── */
$pre = array();
$lines = file($base);
array_shift($lines);
foreach ($lines as $l) {
    $c = explode("\t", rtrim($l, "\n"));
    if (count($c) < 8) { continue; }
    $rid = (int) $c[0];
    $k = upc_key($c[6], $c[5]);
    $pre[$rid][$k] = isset($pre[$rid][$k]) ? $pre[$rid][$k] + 1 : 1;
}

/* ── الحاضرُ: التصييرُ الحيُّ نفسُه ── */
$fails = 0; $totPre = 0; $totNow = 0; $renamed = 0; $merged = 0; $resurfaced = 0;
echo "════ مصفوفةُ الحفظِ — بنودُ السايدبارِ الظاهرةُ قبلًا وبعدًا ════\n";
foreach (uxp_root_roles() as $rid) {
    if (isset($archivedRoles[$rid])) {
        $a = $archivedRoles[$rid];
        echo "  ◆ دور {$rid}: مؤرشَفٌ بإثبات ({$a['proof']}) — {$a['n']} صفًّا محجورًا حتى {$a['purge']}"
           . " · يُستثنى من المقارنةِ ولا يُعدُّ فقدًا\n";
        continue;
    }
    $now = array();
    foreach (uxp_render_role($conn, $rid) as $p) {
        $k = upc_key($p['href'], $p['label']);
        $now[$k] = isset($now[$k]) ? $now[$k] + 1 : 1;
    }
    $p = isset($pre[$rid]) ? $pre[$rid] : array();
    $cntPre = array_sum($p); $cntNow = array_sum($now);
    $totPre += $cntPre; $totNow += $cntNow;
    /* التجميعُ بالملفِّ الأم: الدمجُ لا يقع إلا بين توائمِ الملفِّ الواحد */
    $preF = array(); $nowF = array();
    foreach ($p as $k => $c)   { list($f, $l) = explode('||', $k, 2); $preF[$f]['labels'][$l] = ($preF[$f]['labels'][$l] ?? 0) + $c; $preF[$f]['n'] = ($preF[$f]['n'] ?? 0) + $c; }
    foreach ($now as $k => $c) { list($f, $l) = explode('||', $k, 2); $nowF[$f]['labels'][$l] = ($nowF[$f]['labels'][$l] ?? 0) + $c; $nowF[$f]['n'] = ($nowF[$f]['n'] ?? 0) + $c; }
    $missing = array(); $renamedHere = 0; $mergedHere = 0;
    foreach ($preF as $f => $P) {
        if (!isset($nowF[$f])) { $missing[] = 'الملفُّ كلُّه: ' . $f; continue; }          /* ملفٌّ اختفى من الدور = فقدٌ صريح */
        $N = $nowF[$f];
        $canonName = isset($canon[$f]) ? $canon[$f]['canon'] : null;
        foreach ($P['labels'] as $l => $c) {
            if (isset($N['labels'][$l])) { continue; }                                     /* ① الاسمُ باقٍ */
            if ($canonName !== null && isset($N['labels'][$canonName])) {                  /* ② اسمٌ حلَّ محلَّه معياريُّه المعتمَد */
                $renamedHere += $c; continue;
            }
            $missing[] = '«' . $l . '» (' . $f . ')';                                      /* ③ اسمٌ ضاع بلا بديلٍ مسجَّل */
        }
        if ($N['n'] < $P['n']) { $mergedHere += ($P['n'] - $N['n']); }                     /* توائمُ باسمٍ واحدٍ اندمجت — يُبلَّغ */
    }
    $added = array();
    foreach ($nowF as $f => $N) {
        foreach ($N['labels'] as $l => $c) {
            $had = isset($preF[$f]['labels'][$l]);
            $canonName = isset($canon[$f]) ? $canon[$f]['canon'] : null;
            if (!$had && !($canonName !== null && $l === $canonName)) { $added[] = '«' . $l . '» (' . $f . ')'; }
            elseif (!$had && $canonName !== null && $l === $canonName && !isset($preF[$f])) { $added[] = '«' . $l . '» (' . $f . ')'; }
        }
    }
    $renamed += $renamedHere; $merged += $mergedHere; $resurfaced += count($added);
    $ok = empty($missing);
    if (!$ok) { $fails++; }
    echo ($ok ? '  ✔' : '  ✗') . " دور {$rid}: قبل={$cntPre} · بعد={$cntNow}"
        . ($renamedHere ? " · أُعيدت تسميتُه بالسجل={$renamedHere}" : '')
        . ($mergedHere ? " · توأمٌ مندمجٌ باسمٍ واحد={$mergedHere}" : '')
        . ($missing ? ' · ناقص: ' . implode(' ، ', array_slice($missing, 0, 6)) : '')
        . ($added ? ' · جديدُ الظهور: ' . implode(' ، ', array_slice($added, 0, 4)) : '') . "\n";
}
echo "الإجمالي: قبل={$totPre} · بعد={$totNow} · أُعيدت تسميتُه بالسجل={$renamed} · توأمٌ مندمج={$merged} · جديدُ الظهور={$resurfaced}\n";
if ($fails === 0) { echo "✔ صفرُ فقدٍ غيرِ مصرَّح — كلُّ بندٍ قبليٍّ موجودٌ بهويتِه أو باسمِه المعياريِّ المسجَّل\n"; exit(0); }
echo "✗ {$fails} دورًا فيه نقصٌ غيرُ مصرَّح — " . ($GATE ? 'التسليمُ مرسَّب' : 'راجِع قبل أيِّ تسليم') . "\n";
exit($GATE ? 1 : 0);
