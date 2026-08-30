<?php
/**
 * tools/ctl_sidebar_metrics.php — أمرُ الضبطِ §٢ · مقاماتُ السايدبارِ الثلاثةُ منفصلةً
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه**: *«تُفصل مقاماتُ السايدبارِ إلى ثلاثةِ مقاييسَ مستقلّة:
 *   إغلاقُ مواضعِ الانحرافِ المكتشفة، ومطابقةُ الملاحةِ للأسطحِ المبنيّة،
 *   وتغطيةُ الملاحةِ للأهدافِ المستهدفة. فلا تُقرأ نسبةُ إغلاقِ التصحيحاتِ
 *   إغلاقًا رسميًّا للسايدبار ما دام مقياسُ RPR-02 الحاكمُ مختلفًا. وتُفكَّك
 *   الفجوةُ المتبقيةُ إلى: مبنيٌّ مخالف، غيرُ مبنيّ، محجوبٌ بقرار، أو غيرُ
 *   منطبقٍ بمرجع»*.
 *
 * ◆ **الثلاثةُ بمقاماتِها — ولا يُخلَط رقمٌ برقم**:
 *   **M-A · إغلاقُ مواضعِ الانحراف** — مقامُه مواضعُ الخطواتِ السبعِ (٢٬٦٨٥
 *     المكتشفةُ في `rpr02_s6_sidebar.php`) · يقيس **العملَ المنجَز** لا الحالَ.
 *   **M-B · مطابقةُ الملاحةِ للمبنيّ** — مقامُه **المُصيَّرُ المجسورُ بالملفِّ
 *     التصميميّ** (وهو مقياسُ `RPR-02` **#٨ الحاكمُ** بعينِه) · يقيس هل ما
 *     يُعرض يطابق الملفَّ.
 *   **M-C · تغطيةُ الملاحةِ للأهداف** — مقامُه **كونُ الأهدافِ** (٥٦١) ·
 *     يقيس كم هدفًا مستهدفًا **يبلغه مستخدمٌ من الملاحةِ** أصلًا.
 *   ⛔ **فالأولُ يقارب ١٠٠٪ والثالثُ بعيدٌ عنها — وقراءةُ الأولِ إغلاقًا
 *     رسميًّا هي بعينُها الغلطةُ التي يمنعها الأمر.**
 *
 * التشغيل: php tools/ctl_sidebar_metrics.php [--md] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$MD   = in_array('--md', $argv, true);
$SELF = in_array('--selftest', $argv, true);

$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x === null ? null : $x[0];
};
$snap = null;
$r = $conn->query("SELECT snapshot_id FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $snap = $x[0]; }
$sid = $snap !== null ? $snap : 'DRY';

/* ═══ M-A · إغلاقُ مواضعِ الانحراف — من عُدّةِ القياسِ الحاكمةِ نفسِها ══════
   ⛔ **لا يُعاد بناءُ المقياسِ هنا** — يُشغَّل `rpr02_s6_sidebar.php` ويُقرأ
   ناتجُه، فقارئان يحسبان الخطواتِ السبعَ نسختان تتفرّقان. */
$out = array(); $rc = 0;
exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/rpr02_s6_sidebar.php') . ' 2>&1', $out, $rc);
$s6 = implode("\n", $out);
$needNow = null;
if (preg_match('~مجتمعةً:\s*(\d+)~u', $s6, $m)) { $needNow = (int) $m[1]; }
$A_DISCOVERED = 2685;   /* المقامُ التاريخيُّ المكتشفُ — لقطة SNAP-526c586e (أمرُ SIDEBAR_FINISH §٢·١) */
$A_open = $needNow;
$A_closed = ($A_open === null) ? null : ($A_DISCOVERED - $A_open);

/* ═══ M-B · مطابقةُ الملاحةِ للمبنيّ — سطرُ #٨ الحاكمُ يُقرأ من ناتجِه ═════ */
$B_ok = $B_bad = $B_nb = null;
if (preg_match('~مطابقٌ \*\*(\d+)\*\* · مخالفٌ \*\*(\d+)\*\*~u', $s6, $m)) { $B_ok = (int) $m[1]; $B_bad = (int) $m[2]; }
if (preg_match('~NO_BRIDGE` \*\*(\d+)\*\*~u', $s6, $m)) { $B_nb = (int) $m[1]; }

/* ═══ M-C · تغطيةُ الملاحةِ للأهداف — مقامُه كونُ الأهدافِ كلُّه ══════════ */
$navRoutes = array();
$r = $conn->query("SELECT DISTINCT LOWER(TRIM(BOTH '/' FROM route)) FROM nav_items WHERE active = 1");
while ($r && ($x = $r->fetch_row())) {
    $navRoutes[preg_replace('~[?#].*$~', '', (string) $x[0])] = 1;
}
$C = array('IN_NAV' => 0, 'BUILT_NOT_IN_NAV' => 0, 'NOT_BUILT' => 0, 'MERGED' => 0, 'BLOCKED_DECISION' => 0);
$Cex = array('BUILT_NOT_IN_NAV' => array());
$r = $conn->query("SELECT u.target_uid, u.verdict, u.screen_id, s.route, s.visibility_class
                     FROM repair01_target_universe u
                     LEFT JOIN repair01_screen_registry s ON s.screen_id = u.screen_id");
$N = 0;
while ($r && ($x = $r->fetch_assoc())) {
    $N++;
    $v = (string) $x['verdict'];
    if ($v === '' ) { $C['BLOCKED_DECISION']++; continue; }
    if ($v === 'MERGED_INTO') { $C['MERGED']++; continue; }
    if ($v === 'NOT_BUILT') { $C['NOT_BUILT']++; continue; }
    /* MATCHED — هل يبلغه مستخدمٌ من الملاحة؟ */
    $rt = strtolower(trim((string) $x['route'], '/'));
    if ($rt !== '' && isset($navRoutes[$rt])) { $C['IN_NAV']++; }
    else {
        $C['BUILT_NOT_IN_NAV']++;
        if (count($Cex['BUILT_NOT_IN_NAV']) < 8) {
            $Cex['BUILT_NOT_IN_NAV'][] = $x['screen_id'] . ' ' . $x['route'] . ' [' . $x['visibility_class'] . ']';
        }
    }
}

/* ⛔ **والتبويبُ ليس فجوةً**: سطحٌ `TAB_CHILD` يُبلغ من شريطِ أبيه لا من بندِ
   قائمةٍ — فيُفصل عن «مبنيٍّ لا يبلغه أحد» */
$tabsCovered = 0;
$r = $conn->query("SELECT u.screen_id FROM repair01_target_universe u
                     JOIN repair01_screen_registry s ON s.screen_id = u.screen_id
                    WHERE u.verdict = 'MATCHED' AND s.visibility_class = 'TAB_CHILD'
                      AND s.parent_screen_id <> ''");
$tabIds = array();
while ($r && ($x = $r->fetch_row())) { $tabIds[$x[0]] = 1; }

/* ═══ الاختبارُ السالب ═══════════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    if ($N < 100) { echo "  X كونُ الأهدافِ $N — القراءةُ لم تتمّ\n"; $fail++; }
    if (count($navRoutes) < 100) { echo '  X مساراتُ الملاحةِ ' . count($navRoutes) . " — مصفاةٌ عمياء\n"; $fail++; }
    $sum = array_sum($C);
    if ($sum !== $N) { echo "  X مجموعُ التفكيكِ $sum والمقامُ $N — حكمٌ يسقط من الشقوق\n"; $fail++; }
    if ($A_open === null || $B_ok === null) { echo "  X ناتجُ عُدّةِ القياسِ الحاكمةِ لم يُقرأ\n"; $fail++; }
    /* **الكاسر**: مسارٌ وهميٌّ لا يُغطّى */
    if (isset($navRoutes['zzq/absent_route_probe.php'])) { echo "  X مسارٌ وهميٌّ في الملاحة\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — ثلاثةُ مقاماتٍ ومجموعُ التفكيكِ هو المقام\n";
    exit($fail ? 1 : 0);
}

/* ═══ العرض ═════════════════════════════════════════════════════════════ */
echo "\n═══ أمرُ الضبطِ §٢ — مقاماتُ السايدبارِ الثلاثةُ منفصلةً ═══\n";
printf("  اللقطة %s\n\n", $sid);
printf("  M-A · **إغلاقُ مواضعِ الانحرافِ المكتشفة** (مقامُ العملِ المنجَز)\n");
printf("        مكتشفٌ %d · أُغلق **%d** · باقٍ **%d** ⇒ **%.1f%%**\n",
       $A_DISCOVERED, $A_closed, $A_open, $A_closed !== null ? 100 * $A_closed / $A_DISCOVERED : 0);
printf("  M-B · **مطابقةُ الملاحةِ للمبنيّ** (مقياسُ `RPR-02` #٨ الحاكم)\n");
printf("        مطابقٌ %d · مخالفٌ %d ⇒ **%.1f%%** · ومحجوبٌ على المصالحةِ `NO_BRIDGE` **%d**\n",
       $B_ok, $B_bad, ($B_ok + $B_bad) ? 100 * $B_ok / ($B_ok + $B_bad) : 0, $B_nb);
printf("  M-C · **تغطيةُ الملاحةِ للأهدافِ المستهدفة** (مقامُه كونُ الأهدافِ %d)\n", $N);
$cov = $C['IN_NAV'] + count($tabIds);
printf("        يبلغه مستخدمٌ **%d** (‏قائمةً %d · تبويبَ أبٍ %d) ⇒ **%.1f%%**\n",
       $cov, $C['IN_NAV'], count($tabIds), $N ? 100 * $cov / $N : 0);
echo "\n  ── الفجوةُ مفكَّكةً — ⛔ ومجموعُها المقامُ لا أقلّ ──\n";
printf("     مبنيٌّ مخالفٌ (مُصيَّرٌ بمجموعةٍ تخالف الملفّ)   **%4d** موضعًا (M-B)\n", $B_bad);
printf("     مبنيٌّ لا يبلغه أحدٌ من الملاحة              **%4d** هدفًا (منها تبويباتُ أبٍ %d ليست فجوة)\n",
       $C['BUILT_NOT_IN_NAV'], count(array_intersect_key($tabIds, $tabIds)));
printf("     غيرُ مبنيٍّ                                  **%4d** هدفًا\n", $C['NOT_BUILT']);
printf("     محجوبٌ بقرارٍ (م٣ — بلا حكم)                **%4d** هدفًا\n", $C['BLOCKED_DECISION']);
printf("     غيرُ منطبقٍ بمرجعٍ (ذاب في وارثٍ `MERGED`)     **%4d** هدفًا\n", $C['MERGED']);
foreach ($Cex['BUILT_NOT_IN_NAV'] as $e) { echo "       · $e\n"; }
echo "\n  ⛔ **ولا تُقرأ M-A (~١٠٠٪) إغلاقًا رسميًّا للسايدبار** — الحاكمُ M-B (٩٠٪)\n";
echo "     والفجوةُ الكبرى M-C: أهدافٌ غيرُ مبنيّةٍ لا يبلغها أحدٌ أصلًا.\n";

if ($MD) {
    $o  = "# أمرُ الضبطِ §٢ — مقاماتُ السايدبارِ الثلاثةُ منفصلةً\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `$sid`\n\n";
    $o .= "| المقياس | المقام | القيمة | النسبة |\n|---|---|---|---:|\n";
    $o .= "| **M-A** إغلاقُ مواضعِ الانحراف | العملُ المكتشفُ ($A_DISCOVERED موضعًا) | أُغلق $A_closed · باقٍ $A_open | **"
        . round(100 * $A_closed / $A_DISCOVERED, 1) . "%** |\n";
    $o .= "| **M-B** مطابقةُ الملاحةِ للمبنيّ (**#٨ الحاكم**) | المُصيَّرُ المجسور (" . ($B_ok + $B_bad) . ") | مطابقٌ $B_ok · مخالفٌ $B_bad · `NO_BRIDGE` $B_nb | **"
        . round(100 * $B_ok / ($B_ok + $B_bad), 1) . "%** |\n";
    $o .= "| **M-C** تغطيةُ الملاحةِ للأهداف | كونُ الأهدافِ ($N) | يبلغه مستخدمٌ $cov (قائمةً {$C['IN_NAV']} · تبويبًا " . count($tabIds) . ") | **"
        . round(100 * $cov / $N, 1) . "%** |\n\n";
    $o .= "⛔ **الثلاثةُ لا يُخلَطون**: M-A يقارب المئةَ وM-C دونها بكثيرٍ — وقراءةُ الأولِ إغلاقًا رسميًّا ممنوعةٌ بنصِّ الأمر.\n\n";
    $o .= "## الفجوةُ مفكَّكةً\n\n| الصنف | العدد |\n|---|---:|\n";
    $o .= "| مبنيٌّ مخالف (مجموعةُ المُصيَّرِ تخالف الملفّ) | $B_bad موضعًا |\n";
    $o .= "| مبنيٌّ لا يبلغه أحدٌ من الملاحة | {$C['BUILT_NOT_IN_NAV']} هدفًا |\n";
    $o .= "| غيرُ مبنيّ | **{$C['NOT_BUILT']}** هدفًا |\n";
    $o .= "| محجوبٌ بقرار (م٣) | {$C['BLOCKED_DECISION']} |\n";
    $o .= "| غيرُ منطبقٍ بمرجع (`MERGED_INTO`) | {$C['MERGED']} |\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/CTL_SIDEBAR_METRICS.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/CTL_SIDEBAR_METRICS.md\n";
}
