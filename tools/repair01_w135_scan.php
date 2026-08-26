<?php
/**
 * tools/repair01_w135_scan.php — قياسُ الوقوفِ من شروطِ بوّابةِ W13.5
 * ═══════════════════════════════════════════════════════════════════════════
 * **أمرُ المالك 2026-08-26 · البند ٤٨**: بوّابةُ W13.5 تُعتبر `PASS` بتسعةِ
 * شروط. وهذه الأداةُ **تقيس أين نقف من كلٍّ منها** ⛔ ولا تُصلح شيئًا.
 *
 * ◆ **والقياسُ يقرأ الأعمدةَ الحيّةَ لا خريطةً محفوظةً فيه**: نسختُه الأولى
 *   كانت تحمل أسماءَ الأعمدةِ الناقصةِ نصًّا، فلمّا أُنشئت بقيت تقول «ناقص».
 *   **ومقياسٌ لا يُعيد قراءةَ ما يقيس يتقادم بصمت** — فصار كلُّ عددٍ استعلامًا.
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
$col = function ($t, $c) use ($conn) {
    $r = @$conn->query("SHOW COLUMNS FROM `$t` LIKE '" . $conn->real_escape_string($c) . "'");
    return $r && $r->num_rows > 0;
};
$open = 0;
$line = function ($no, $title, $have, $need, $note = '') use (&$open) {
    $ok = ($have === $need);
    if (!$ok) { $open++; }
    printf("  %s %-3s %-44s %7s / %-7s %s\n", ($ok ? '✔' : '◆'), $no, $title, $have, $need, $note);
    return $ok;
};
$TEN = "'DEP-03','DEP-04','DEP-05','DEP-06','DEP-07','DEP-08','DEP-09','DEP-13','DEP-16','DEP-17'";

echo "\n═══ قياسُ الوقوفِ من بوّابةِ W13.5 — أمرُ المالك · البند ٤٨ ═══\n";
echo "     ◆ = عملٌ باقٍ  ·  ✔ = مستوفًى اليوم\n\n";

/* ── ① المقامُ التنظيميّ (البندان 2 · 51) ────────────────────────────────── */
echo "① المقامُ التنظيميّ\n";
$line('1a', 'إداراتٌ مرقَّمةٌ DEP-01..17',
      $n("SELECT COUNT(*) FROM repair01_departments WHERE canonical_code LIKE 'DEP-%'"), 17);
$line('1b', 'وحداتٌ خارجَ التسلسلِ الأربع',
      $n("SELECT COUNT(*) FROM repair01_departments WHERE canonical_code NOT LIKE 'DEP-%'"), 4);

/* ── ② مصالحةُ الملكيّةِ التساعيّة (البند 3) ─────────────────────────────── */
echo "\n② مصالحةُ الملكيّةِ بأحكامٍ تسعة\n";
$tot = $n("SELECT COUNT(*) FROM repair01_screen_registry");
$line('2a', 'سطحٌ يحمل حكمًا من التسعة',
      $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE ownership_verdict <> ''"), $tot);
$line('2b', 'حكمٌ بلا قاعدةِ اشتقاقٍ مكتوبة',
      $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE ownership_verdict <> '' AND verdict_rule = ''"), 0);
$line('2c', 'حقيقةٌ SOURCE في إدارتَين',
      $n("SELECT COUNT(*) FROM (SELECT LOWER(screen_file) f FROM repair01_screen_registry
           WHERE surface_kind = 'SOURCE' GROUP BY f HAVING COUNT(DISTINCT owner_code) > 1) z"), 0);
$line('2d', 'مجهولٌ مُعلَنٌ يُرفع للمالك',
      $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE ownership_verdict = 'UNKNOWN'"), 0);

/* ── ③ التقاطعاتُ الحرِجةُ الخمسة (البنود 4-8) ──────────────────────────── */
echo "\n③ تقاطعاتٌ حرِجةٌ — حكمُها مسجَّلٌ قبل W14\n";
$xAll  = $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code IN ($TEN)");
$xKind = $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code IN ($TEN) AND surface_kind <> ''");
$line('3a', 'سطحٌ متقاطعٌ مُصنَّفٌ مصدرًا أو إسقاطًا', $xKind, $xAll);
$r = @$conn->query("SELECT owner_code, COUNT(*) a, SUM(surface_kind = 'PROJECTION') p
                      FROM repair01_screen_registry WHERE owner_code IN ($TEN)
                      GROUP BY owner_code ORDER BY owner_code");
while ($r && ($x = $r->fetch_assoc())) {
    printf("       · %-8s أسطح=%-4d إسقاط=%d\n", $x['owner_code'], $x['a'], $x['p']);
}

/* ── ④ سقّاطةُ السطحِ الجديد (البند 9) ───────────────────────────────────── */
echo "\n④ سقّاطةُ السطحِ الجديد — اثنا عشرَ شرطًا\n";
$MAP = array(
    'Canonical Screen ID' => 'screen_id',        'Canonical Arabic Label' => 'canonical_label_ar',
    'Domain Owner'        => 'owner_code',       'Source or Projection'   => 'surface_kind',
    'Canonical Route'     => 'route',            'Lifecycle Position'     => 'lifecycle',
    'Server View Guard'   => 'guard_kind',       'Server Action Guard'    => 'action_guard',
    'Permission Policy'   => 'permission_policy','Grain'                  => 'grain_ar',
    'Source of Truth'     => 'source_of_truth',  'State Model ref'        => 'state_model_ref',
);
$got = 0; $miss = array();
foreach ($MAP as $k => $c) { if ($col('repair01_screen_registry', $c)) { $got++; } else { $miss[] = $k; } }
$line('4a', 'شروطٌ يحملها السجلُّ بنيةً', $got, 12, $miss ? ('الناقص: ' . implode(' · ', $miss)) : '');
$ratchet = is_file($ROOT . '/tools/repair01_w135_ratchet.php');
$line('4b', 'سقّاطةٌ تردُّ سطحًا جديدًا ناقصًا', ($ratchet ? 1 : 0), 1, $ratchet ? '' : 'الأداةُ لم تُبنَ بعد');

/* ── ⑤ سلامةُ قرارِ المالك (البنود 11-13) ───────────────────────────────── */
echo "\n⑤ سلامةُ قرارِ المالك\n";
$assumed = -1;
$o = @shell_exec('"' . PHP_BINARY . '" "' . $ROOT . '/tools/repair01_owner_decision_audit.php" 2>&1');
if ($o !== null && preg_match('~SYSTEM_ASSUMED_APPROVAL\s+(\d+)~u', $o, $m)) { $assumed = (int) $m[1]; }
$line('5a', 'قرارٌ معتمَدٌ بلا سندِ مالك', $assumed, 0);
$f9 = 0;
foreach (array('decision_source','owner_decision_reference','recorded_by','evidence_ref','effective_from') as $c) {
    if ($col('repair01_decisions', $c)) { $f9++; }
}
$line('5b', 'حقولُ البند 11 في جدولِ القرارات', $f9 + 4, 9);
$aud = $n("SELECT COUNT(*) FROM repair01_decision_audit");
$line('5c', 'أحكامُ المراجعةِ العكسيّةِ مقيَّدة', ($aud > 0 ? 1 : 0), 1, "صفوف $aud");

/* ── ⑥ قراراتُ التسميةِ والملكيّة (البندان 14 · 18) ─────────────────────── */
echo "\n⑥ التسميةُ والملكيّة\n";
$line('6a', 'اسمٌ غيرُ معتمَدٍ في السجلِّ المعياريّ',
      $n("SELECT COUNT(*) FROM nav_canonical WHERE status <> 'APPROVED'"), 0);
$line('6b', 'سطحٌ حيٌّ بلا إدارةٍ مالكة',
      $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(owner_code,'') = '' AND on_disk = 1"), 0);

/* ── ⑦ تسييجُ دَينِ الماليةِ والخزينة (البندان 15 · 16) ─────────────────── */
echo "\n⑦ تسييجُ دَينِ الماليةِ والخزينة\n";
$finAll = $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code IN ('DEP-05','DEP-06')");
$line('7a', 'سطحٌ ماليٌّ حيٌّ بلا مسارٍ أو حارس',
      $n("SELECT COUNT(*) FROM repair01_screen_registry
           WHERE owner_code IN ('DEP-05','DEP-06') AND on_disk = 1
             AND (COALESCE(route,'') = '' OR COALESCE(guard_kind,'') = '')"), 0, "من $finAll");
$line('7b', 'سطحٌ ماليٌّ مُصنَّفٌ لتسييجِ دَينِه',
      $n("SELECT COUNT(*) FROM repair01_screen_registry
           WHERE owner_code IN ('DEP-05','DEP-06') AND finance_debt_class <> ''"), $finAll);

/* ── ⑧ منهجُ الأشباح (البند 17) ─────────────────────────────────────────── */
echo "\n⑧ منهجُ الأشباح\n";
$ghost = $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE ghost_verdict = 'MOVED_TO_TARGET_GAPS'");
$line('8a', 'شبحٌ له قرارٌ من الستّةِ بسببِه',
      $n("SELECT COUNT(*) FROM repair01_target_gaps WHERE ghost_disposition <> ''"), $ghost, "المقام $ghost");

/* ── ⑨ حدودُ W14 (البنود 36-47) ─────────────────────────────────────────── */
echo "\n⑨ حدودُ الرابعةَ عشرة\n";
$line('9a', 'ملكيّةُ التحقيقِ بجوابِ مالكٍ بدليل',
      $n("SELECT COUNT(*) FROM repair01_decisions WHERE decision_id = 'DEC-OPEN-16'
           AND status = 'APPROVED' AND src_ref LIKE '%sha256:%'"), 1);
$line('9b', 'مالكُ سجلِّ توجيهِ الكيانات DEC-OPEN-17',
      $n("SELECT COUNT(*) FROM repair01_decisions WHERE decision_id = 'DEC-OPEN-17' AND status = 'APPROVED'"),
      1, 'حاجبٌ بنيويٌّ مفتوح');

/* ── الحصيلة ─────────────────────────────────────────────────────────────── */
$TOTAL = 19;
echo "\n───────────────────────────────────────────────────────────────────────────────\n";
printf("بنودٌ مستوفاةٌ: %d من %d · **باقٍ عملٌ في %d**\n", $TOTAL - $open, $TOTAL, $open);
echo "⛔ ولا يبدأ W14 قبل أن تصير كلُّها خضراء — البند 48.\n";
exit($open === 0 ? 0 : 1);
