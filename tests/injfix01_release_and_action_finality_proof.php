<?php
/**
 * tests/injfix01_release_and_action_finality_proof.php
 *   INJ-FIX-01 · GAP-14 · GAP-26 · GAP-32
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **GAP-14** «صفرُ سجلِّ رفضٍ بفعلٍ فارغ» — يُقاس على `guard_denials` كلِّه.
 * ◆ **GAP-26** «بصمةُ الشجرةِ = آخرِ إصدارٍ مسجَّل» — تُحسب من الملفاتِ نفسِها،
 *   **ولا إصداران DRAFT معًا**: اثنان يدّعيان الحاضرَ أسوأُ من واحدٍ متقادم.
 * ◆ **GAP-32** «صفرُ رسالةٍ ميتةٍ بلا قرارٍ ومالكٍ وسبب» + حالةُ الأفعالِ النهائية.
 *   والمعلَّقُ **يُعلَن ولا يُدَّعى إغلاقُه** — وسقّاطةٌ تمنع ازديادَه.
 *
 * التشغيل: php tests/injfix01_release_and_action_finality_proof.php [--retighten]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
$BASE = $ROOT . '/docs/INJFIX01/evidence/GAP-32_action_pending_baseline.json';
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($cond, $msg)
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ✔ {$msg}\n"; } else { $bad++; echo "  ✘ {$msg}\n"; }
}

/* ══ GAP-14 ═══════════════════════════════════════════════════════════════ */
echo "══ GAP-14 · صفرُ سجلِّ رفضٍ بفعلٍ فارغ ══\n";
$tot = (int) $conn->query("SELECT COUNT(*) FROM `guard_denials`")->fetch_row()[0];
$empty = 0; $cols = array('guard_code', 'reason_code', 'attempted_ref');
foreach ($cols as $col) {
    $n = (int) $conn->query("SELECT COUNT(*) FROM `guard_denials`
                              WHERE COALESCE(`{$col}`,'') = ''")->fetch_row()[0];
    printf("     %-16s فارغ=%d\n", $col, $n);
    $empty += $n;
}
chk($empty === 0, "صفرُ خانةِ مفتاحٍ فارغةٍ في {$tot} سجلَّ رفض — المجموع {$empty}");

/* ══ GAP-26 ═══════════════════════════════════════════════════════════════ */
echo "\n══ GAP-26 · بصمةُ الشجرةِ = آخرِ إصدارٍ مسجَّل ══\n";
$q = $conn->query("SELECT `version_tag`,`fingerprint`,`files_json` FROM `gov_component_versions`
                    WHERE `state` = 'DRAFT' ORDER BY `id` DESC");
$drafts = array();
while ($q && $x = $q->fetch_assoc()) { $drafts[] = $x; }
chk(count($drafts) === 1, 'إصدارٌ واحدٌ فقط في DRAFT — لا يدّعي اثنان الحاضرَ (' . count($drafts) . ')');

if (count($drafts) >= 1) {
    $cur = $drafts[0];
    $files = (array) json_decode((string) $cur['files_json'], true);
    $now = array(); $gone = 0;
    foreach (array_keys($files) as $rel) {
        $abs = $ROOT . '/' . $rel;
        if (!is_file($abs)) { $gone++; continue; }
        $now[$rel] = hash('sha256', (string) file_get_contents($abs));
    }
    ksort($now);
    $fp = hash('sha256', json_encode($now));
    printf("     الإصدار %s · ملفاتُه %d%s\n", $cur['version_tag'], count($files),
        $gone ? " · مفقودٌ {$gone}" : '');
    chk($fp === $cur['fingerprint'],
        'البصمةُ المحسوبةُ تطابق المسجَّلة' . ($fp === $cur['fingerprint'] ? '' : " — {$fp} ≠ {$cur['fingerprint']}"));
}

/* ══ GAP-32 ① ═════════════════════════════════════════════════════════════ */
echo "\n══ GAP-32 ① · لكلِّ رسالةٍ ميتةٍ قرارٌ ومالكٌ وسبب ══\n";
$dlq = (int) $conn->query("SELECT COUNT(*) FROM `ems_event_deliveries` WHERE `state`='dlq'")->fetch_row()[0];
$r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_dead_letter_rulings'");
if (!$r || (int) $r->fetch_row()[0] === 0) {
    chk(false, 'سجلُّ أحكامِ الرسائلِ الميتةِ غيرُ موجود');
} else {
    $unruled = array();
    $q = $conn->query("SELECT COALESCE(NULLIF(d.`fail_code`,''),'(فارغ)') fc, COUNT(*) n
                         FROM `ems_event_deliveries` d
                    LEFT JOIN `gov_dead_letter_rulings` g ON g.`fail_code` = d.`fail_code`
                        WHERE d.`state`='dlq' AND g.`fail_code` IS NULL
                     GROUP BY fc");
    while ($q && $x = $q->fetch_assoc()) { $unruled[] = "{$x['fc']}={$x['n']}"; }
    chk(count($unruled) === 0, "صفرُ رسالةٍ ميتةٍ بلا حكم — من {$dlq}"
        . (count($unruled) ? ' — ' . implode(' · ', $unruled) : ''));
    $noWhy = (int) $conn->query("SELECT COUNT(*) FROM `gov_dead_letter_rulings`
                                  WHERE COALESCE(`reason`,'')='' OR COALESCE(`owner_role`,'')=''")->fetch_row()[0];
    chk($noWhy === 0, "لكلِّ حكمٍ مالكٌ وسببٌ مكتوب — بلا={$noWhy}");
    $q = $conn->query("SELECT `fail_code`,`messages`,`ruling`,`owner_role` FROM `gov_dead_letter_rulings`");
    while ($q && $x = $q->fetch_assoc()) {
        printf("     %-20s %3d  %-24s %s\n", $x['fail_code'], $x['messages'], $x['ruling'], $x['owner_role']);
    }
}

/* ══ GAP-32 ② ═════════════════════════════════════════════════════════════ */
echo "\n══ GAP-32 ② · حالةُ الأفعالِ النهائية ══\n";
$by = array();
$q = $conn->query("SELECT `guard_verified` g, COUNT(*) n FROM `nav09_action_map` GROUP BY g");
$all = 0;
while ($q && $x = $q->fetch_assoc()) { $by[$x['g']] = (int) $x['n']; $all += (int) $x['n']; }
foreach ($by as $k => $v) { printf("     %-10s %d\n", $k, $v); }
$pend = $by['pending'] ?? 0;
$final = $all - $pend;
printf("  ◆ حالةٌ نهائية: %d من %d · **معلَّقٌ %d**\n", $final, $all, $pend);

/* ◆ ولا يُعَدُّ n_a نهائيًّا إلا بدليلٍ مكتوب */
$naNoWhy = (int) $conn->query("SELECT COUNT(*) FROM `nav09_action_map`
                                WHERE `guard_verified`='n_a' AND COALESCE(`guard_evidence`,'')=''")->fetch_row()[0];
chk($naNoWhy === 0, "صفرُ فعلٍ n_a بلا دليلٍ مكتوب — {$naNoWhy}");

if (in_array('--retighten', $argv, true) || !is_file($BASE)) {
    if (!is_dir(dirname($BASE))) { mkdir(dirname($BASE), 0777, true); }
    file_put_contents($BASE, json_encode(array('gap' => 'GAP-32', 'denominator' => $all,
        'pending' => $pend), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "  ↦ شُدَّ خطُّ الأساسِ إلى {$pend}\n";
}
$bl = json_decode((string) file_get_contents($BASE), true);
$blP = (int) ($bl['pending'] ?? 0);
chk($pend <= $blP, "المعلَّقُ لا يزداد — {$pend} ≤ {$blP}");
if ($pend < $blP) {
    chk(false, "◆ انخفض إلى {$pend} من {$blP} — **تُشدُّ السقّاطة** بـ--retighten");
}
echo "  ◆ ولا يُقلَب المعلَّقُ آليًّا: تحقُّقُ الحارسِ عملٌ لكلِّ فعلٍ على حِدة،\n";
echo "     وقلبُه بجرَّةِ قلمٍ **إغلاقٌ كاذب** أسوأُ من التعليقِ المُعلَن.\n";

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);

/* حكمُ الإغلاقِ — عقدُ GAP-56: يُصرَّح به بعدَ القياسِ لا يُستنتَج من الذِّكر */
require_once dirname(__DIR__) . '/tools/lib/gap_verdict.php';
gapv('GAP-14', true, 'لا سجلَّ رفضٍ بفعلٍ فارغ — الفعلُ إلزاميٌّ بقيد', $bad);
gapv('GAP-26', true, 'بصمةُ الشجرةِ والإصدارِ مُعلَنتان ومُرقَّاتان — ولا مسوَّدةٌ متراكمة', $bad);
gapv('GAP-32', true, 'الرسائلُ الميتةُ كلُّها محكومةٌ بحكمٍ مكتوبٍ في gov_dead_letter_rulings', $bad);

exit($bad === 0 ? 0 : 1);
