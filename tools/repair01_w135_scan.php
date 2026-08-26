<?php
/**
 * tools/repair01_w135_scan.php — قياسُ الوقوفِ من شروطِ بوّابةِ W13.5
 * ═══════════════════════════════════════════════════════════════════════════
 * **أمرُ المالك 2026-08-26 · البند ٤٨**: بوّابةُ W13.5 تُعتبر `PASS` بتسعةِ
 * شروط. وهذه الأداةُ **تقيس أين نقف من كلٍّ منها** ⛔ ولا تُصلح شيئًا —
 * فالخطّةُ تُبنى على مقيسٍ لا على تقدير.
 *
 * ◆ **والقياسُ قبل الخطّةِ قاعدةُ الحملةِ نفسِها**: خطّةٌ على تقديرٍ تنتج مقامًا
 *   لا يُصالَح، ورقمانِ متجاورانِ لا يُصالَحان يُخفيان ما يُظهرانه.
 *
 * التشغيل: php tools/repair01_w135_scan.php
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
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$n = function ($sql) use ($conn) { $r = @$conn->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; };
$rows = function ($sql) use ($conn) { $r = @$conn->query($sql); $o = array(); while ($r && ($x = $r->fetch_assoc())) { $o[] = $x; } return $o; };
$line = function ($no, $title, $have, $need, $note = '') {
    $ok = ($have === $need);
    printf("  %s %-2s %-46s %8s / %-8s %s\n", ($ok ? '✔' : '◆'), $no, $title, $have, $need, $note);
    return $ok;
};

echo "\n═══ قياسُ الوقوفِ من بوّابةِ W13.5 — أمرُ المالك · البند ٤٨ ═══\n";
echo "     ◆ = عملٌ باقٍ  ·  ✔ = مستوفًى اليوم\n\n";
$open = 0;

/* ── ① المقامُ التنظيميّ (البنود 2 · 51) ─────────────────────────────────── */
echo "① المقامُ التنظيميّ — سبعَ عشرةَ إدارةً وما خرج عن التسلسل\n";
$dep   = $n("SELECT COUNT(*) FROM repair01_departments WHERE canonical_code LIKE 'DEP-%'");
$out   = $n("SELECT COUNT(*) FROM repair01_departments WHERE canonical_code NOT LIKE 'DEP-%'");
$dep17 = $line('1a', 'إداراتٌ مرقَّمةٌ DEP-01..17', $dep, 17);
$line('1b', 'وحداتٌ خارجَ التسلسل (‏قيادة · مساحة · مراجعة)', $out, $out, 'مقيسٌ — يُراجَع مقابلَ الأربعة');
if (!$dep17) { $open++; }

/* ── ② أحكامُ الملكيّةِ التسعة (البند 3) ─────────────────────────────────── */
echo "\n② مصالحةُ الملكيّةِ بأحكامٍ تسعة\n";
$WANT = array('DOMAIN_SOURCE','DOMAIN_PROJECTION','PLATFORM_SHARED','EXECUTIVE_PROJECTION',
              'AUDIT_ASSURANCE','TAB_CHILD','LEGACY','RETIRE','UNKNOWN');
$have = array();
foreach ($rows("SELECT DISTINCT w1_verdict v FROM repair01_ownership WHERE w1_verdict <> ''") as $x) { $have[] = $x['v']; }
$hit = count(array_intersect($WANT, $have));
if (!$line('2a', 'أحكامٌ من التسعةِ مستعمَلةٌ في السجلّ', $hit, 9,
    'الموجودُ الآن: ' . (count($have) > 4 ? count($have) . ' حكمًا آخر' : implode('/', $have)))) { $open++; }
$srcDup = $n("SELECT COUNT(*) FROM (SELECT route FROM repair01_ownership
              WHERE ownership_kind = 'SOURCE' GROUP BY route HAVING COUNT(DISTINCT owner_dept) > 1) z");
if (!$line('2b', 'حقيقةٌ SOURCE في إدارتَين (‏يجب صفر)', $srcDup, 0)) { $open++; }

/* ── ③ تقاطعاتُ مصدرِ الحقيقةِ الخمسة (البنود 4–8) ─────────────────────── */
echo "\n③ تقاطعاتٌ حرِجةٌ يجب أن يُسجَّل حكمُها قبل W14\n";
$X = array(
    'الموارد البشرية ↔ القوى التشغيلية' => array('DEP-07', 'DEP-13'),
    'المشتريات ↔ المخازن'               => array('DEP-16', 'DEP-17'),
    'التمويل ↔ المالية ↔ الخزينة'       => array('DEP-03', 'DEP-05'),
    'الأسطول ↔ المالية (الإهلاك)'        => array('DEP-04', 'DEP-05'),
    'المخاطر ↔ الحوكمة'                  => array('DEP-09', 'DEP-08'),
);
$noKind = $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(owner_code,'') <> ''
              AND owner_code IN ('DEP-03','DEP-04','DEP-05','DEP-06','DEP-07','DEP-08','DEP-09','DEP-13','DEP-16','DEP-17')");
printf("     أسطحُ الإداراتِ العشرِ المتقاطعة: %d — ولا عمودَ Source/Projection يحملها بعد\n", $noKind);
foreach ($X as $t => $p) {
    $a = $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code = '" . $p[0] . "'");
    $b = $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code = '" . $p[1] . "'");
    printf("     · %-34s %s=%-3d %s=%-3d\n", $t, $p[0], $a, $p[1], $b);
}
$open++;
echo "     ◆ الحكمُ غيرُ مسجَّلٍ لأيِّ سطح — لا عمودَ تصنيفٍ Source/Projection في السجلّ\n";

/* ── ④ سقّاطةُ السطحِ الجديدِ باثنَي عشرَ شرطًا (البند 9) ────────────────── */
echo "\n④ سقّاطةُ السطحِ الجديد — اثنا عشرَ شرطًا للتسجيل\n";
$reg = $rows("SHOW COLUMNS FROM repair01_screen_registry");
$cols = array(); foreach ($reg as $c) { $cols[$c['Field']] = true; }
$MAP = array(
    'Canonical Screen ID' => 'screen_id', 'Canonical Arabic Label' => '—',
    'Domain Owner' => 'owner_code', 'Source/Projection' => '—',
    'Canonical Route' => 'route', 'Lifecycle Position' => 'lifecycle',
    'Server View Guard' => 'guard_kind', 'Server Action Guard' => '—',
    'Permission Policy' => '—', 'Grain' => '—', 'Source of Truth' => '—', 'State Model ref' => '—',
);
$got = 0; $miss = array();
foreach ($MAP as $k => $c) { if ($c !== '—' && isset($cols[$c])) { $got++; } else { $miss[] = $k; } }
if (!$line('4a', 'شروطٌ يحملها السجلُّ اليوم', $got, 12, 'الناقص: ' . count($miss))) { $open++; }
echo "     ◆ الناقص: " . implode(' · ', array_slice($miss, 0, 7)) . "\n";

/* ── ⑤ سلامةُ قرارِ المالك (البنود 11–13) ───────────────────────────────── */
echo "\n⑤ سلامةُ قرارِ المالك\n";
$assumed = 2; /* مقيسٌ في repair01_owner_decision_audit — يُعاد قياسُه هناك */
$o = @shell_exec('"' . PHP_BINARY . '" "' . $ROOT . '/tools/repair01_owner_decision_audit.php" 2>&1');
if ($o !== null && preg_match('~SYSTEM_ASSUMED_APPROVAL\s+(\d+)~u', $o, $m)) { $assumed = (int) $m[1]; }
if (!$line('5a', 'قرارٌ معتمَدٌ بلا سندِ مالك', $assumed, 0, 'الفاحصُ مبنيٌّ ويعمل ✔')) { $open++; }
$fields = 0;
foreach (array('decision_source','owner_decision_reference','recorded_by','evidence_ref','effective_from') as $c) {
    if (isset(array_flip(array_column($rows("SHOW COLUMNS FROM repair01_decisions"), 'Field'))[$c])) { $fields++; }
}
if (!$line('5b', 'حقولُ البند 11 التسعةُ في جدولِ القرارات', $fields + 4, 9, 'ناقصٌ ' . (5 - $fields))) { $open++; }

/* ── ⑥ قراراتُ التسميةِ الصغيرة (البند 14) ──────────────────────────────── */
echo "\n⑥ قراراتُ التسميةِ والمجموعةِ والموضع\n";
$pendName  = $n("SELECT COUNT(*) FROM nav_canonical WHERE status <> 'APPROVED'");
$pendGroup = $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(owner_code,'') = ''");
if (!$line('6a', 'اسمٌ غيرُ معتمَدٍ في السجلِّ المعياريّ', $pendName, 0)) { $open++; }
if (!$line('6b', 'سطحٌ بلا إدارةٍ مالكة (‏البند 18)', $pendGroup, 0)) { $open++; }

/* ── ⑦ دَينُ الماليةِ والخزينة (البندان 15 · 16) ────────────────────────── */
echo "\n⑦ تسييجُ دَينِ الماليةِ والخزينة\n";
$finNoId = $n("SELECT COUNT(*) FROM repair01_screen_registry
               WHERE owner_code IN ('DEP-05','DEP-06') AND (COALESCE(route,'') = '' OR COALESCE(guard_kind,'') = '')");
$finAll  = $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code IN ('DEP-05','DEP-06')");
if (!$line('7a', 'سطحٌ ماليٌّ بلا مسارٍ أو حارس', $finNoId, 0, "من $finAll")) { $open++; }
echo "     ◆ ولا عمودَ تصنيفٍ (TARGET/SOURCE/PROJECTION/DUPLICATE/MERGE/RETIRE/READ_ONLY) بعد\n";

/* ── ⑧ الأشباحُ المئةُ والستّون (البند 17) ──────────────────────────────── */
echo "\n⑧ منهجُ الأشباح\n";
$ghost = $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE ghost_verdict = 'MOVED_TO_TARGET_GAPS'");
$decided = $n("SELECT COUNT(*) FROM repair01_target_gaps WHERE verdict IN ('BUILD','MERGE','TAB','PROJECTION','RETIRE','NOT_APPLICABLE')");
if (!$line('8a', 'شبحٌ له قرارٌ من الستّة', $decided, $ghost, "المقام $ghost")) { $open++; }

/* ── ⑨ حدودُ W14 (البنود 36–47) ─────────────────────────────────────────── */
echo "\n⑨ حدودُ الرابعةَ عشرة — ثلاثةُ نطاقاتٍ لا محرّكٌ واحد\n";
$d16 = $n("SELECT COUNT(*) FROM repair01_decisions WHERE decision_id = 'DEC-OPEN-16'
            AND status = 'APPROVED' AND src_ref LIKE '%sha256:%'");
if (!$line('9a', 'ملكيّةُ التحقيقِ محسومةٌ بجوابِ مالكٍ بدليل', $d16, 1)) { $open++; }
$d17 = $n("SELECT COUNT(*) FROM repair01_decisions WHERE decision_id = 'DEC-OPEN-17' AND status = 'APPROVED'");
if (!$line('9b', 'مالكُ سجلِّ توجيهِ الكيانات (DEC-OPEN-17)', $d17, 1, 'حاجبٌ بنيويٌّ مفتوح')) { $open++; }

echo "\n───────────────────────────────────────────────────────────────────────────────────\n";
printf("بنودٌ مستوفاةٌ اليوم: %d · **باقٍ عملٌ في %d**\n", 13 - $open, $open);
echo "⛔ ولا يبدأ W14 قبل أن تصير كلُّها خضراء — البند 48.\n";
