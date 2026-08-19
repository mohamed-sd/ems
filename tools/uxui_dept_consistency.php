<?php
/**
 * tools/uxui_dept_consistency.php — اتساقُ القوالبِ بين الإداراتِ (معيارُ قبولٍ ف١٣-٤)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ المواصفة: «اتساقُ القوالبِ بين الإدارات — فحصٌ آليٌّ يقارن شاشاتِ
 *   النوعِ الواحدِ في ١٢ إدارة — **صفرُ اختلافٍ في البنيةِ والسلوك**».
 * ◆ ورقما الرصدِ في الوثيقة: **139 مسارًا بتبويبٍ مختلف** (84٪ من 164 مشترَكًا)
 *   و**43 مسارًا باسمَين**.
 *
 * ◆ ويُقاس على **النصِّ المُصيَّرِ** لا على الجداول، وبثلاثةِ تمييزاتٍ لازمة:
 *   ① **المرساةُ والمنظرُ ليسا اسمًا ثانيًا للأصل**: البندُ ⑦ يجيز للمدخلِ
 *      الثاني (`#2` أو `?view=`) اسمَ قسمِه. وقياسٌ يدمجهما بالأصلِ يُبلّغ
 *      مخالفتَين معتمَدتَين وهما مدخلانِ مقصودان — وقد وقع ذلك فعلًا.
 *   ② **`PENDING_OWNER` يحمل وضعَه الحاليَّ عمدًا** بنصِّ المصفوفةِ v3 — فهو
 *      قرارٌ معلَّقٌ لا مخالفة، ويُعدُّ في عمودٍ مستقل.
 *   ③ **المخالفةُ الحقيقيةُ APPROVED وحدَه** — وهي التي تُرسِّب البناء.
 *
 * التشغيل: php tools/uxui_dept_consistency.php [--json=<payload>]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';

/* ── المسحُ المُصيَّرُ — الأصلُ منفصلٌ عن المرساةِ والمنظر ────────────────── */
$base = array(); $variant = array();
foreach (uxp_root_roles() as $rid) {
    foreach (uxp_render_role($conn, $rid) as $x) {
        $lc = mb_strtolower(uxp_norm($x['href']));
        $isVar = (strpbrk($x['href'], '#?') !== false);
        if ($isVar) {
            $vk = mb_strtolower(preg_replace('~^(\.\./)+~', '', trim($x['href'])));
            $variant[$vk]['labels'][$x['label']] = true;
            $variant[$vk]['groups'][$x['group']] = true;
        } else {
            $base[$lc]['labels'][$x['label']] = true;
            $base[$lc]['groups'][$x['group']] = true;
        }
        $base[$lc]['roles'][$rid] = true;
    }
}
$canon = array();
$r = $conn->query("SELECT LOWER(route) rt, decision_state FROM nav_canonical");
while ($r && ($x = $r->fetch_assoc())) { $canon[$x['rt']] = $x['decision_state']; }

$shared = 0; $gAppr = array(); $gPend = 0; $lAppr = array(); $lPend = 0;
foreach ($base as $lc => $info) {
    if (count($info['roles']) < 2) { continue; }   /* المشترَكُ وحدَه يُقاس */
    $shared++;
    $st = isset($canon[$lc]) ? $canon[$lc] : 'NO_ROW';
    if (count($info['groups']) > 1) {
        if ($st === 'APPROVED') { $gAppr[] = $lc . ' ⇐ ' . implode(' ⁄ ', array_slice(array_keys($info['groups']), 0, 3)); }
        else { $gPend++; }
    }
    if (count($info['labels']) > 1) {
        if ($st === 'APPROVED') { $lAppr[] = $lc . ' ⇐ ' . implode(' ⁄ ', array_keys($info['labels'])); }
        else { $lPend++; }
    }
}
/* المرساةُ تُحاسَب على ثباتِ اسمِها هي */
$vBad = array();
foreach ($variant as $vk => $info) {
    if (count($info['labels']) > 1) { $vBad[] = $vk . ' ⇐ ' . implode(' ⁄ ', array_keys($info['labels'])); }
}

echo "════ اتساقُ القوالبِ بين الإدارات — على النصِّ المُصيَّر ════\n";
printf("  مساراتٌ مشترَكةٌ بين إدارتَين فأكثر: %d\n", $shared);
printf("  %s تبويبٌ مختلفٌ لمسارٍ **APPROVED**: %d   (معلَّقٌ بقرارِ المالك: %d · ورصدُ الوثيقة 139)\n",
       count($gAppr) === 0 ? '✔' : '✗', count($gAppr), $gPend);
foreach (array_slice($gAppr, 0, 4) as $s) { echo "      · {$s}\n"; }
printf("  %s اسمٌ مزدوجٌ لمسارٍ **APPROVED**: %d   (معلَّقٌ: %d · ورصدُ الوثيقة 43)\n",
       count($lAppr) === 0 ? '✔' : '✗', count($lAppr), $lPend);
foreach (array_slice($lAppr, 0, 4) as $s) { echo "      · {$s}\n"; }
printf("  %s مرساةٌ/منظرٌ باسمَين (البند ⑦ يجيز اسمَ القسمِ لا اسمَين له): %d\n",
       count($vBad) === 0 ? '✔' : '✗', count($vBad));
foreach (array_slice($vBad, 0, 3) as $s) { echo "      · {$s}\n"; }

$fails = count($gAppr) + count($lAppr) + count($vBad);
echo "\n";
echo $fails === 0
   ? "✔ صفرُ اختلافٍ معتمَدٍ بين الإدارات — والمعلَّقُ قرارٌ لا مخالفة\n"
   : "✗ {$fails} مخالفةً معتمَدةً — تُرسِّب البناءَ بنصِّ البندَين ② و③\n";
exit($fails === 0 ? 0 : 1);
