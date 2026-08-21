<?php
/**
 * tests/injfix01_nav_disable_hole_proof.php
 *   ثقبُ التعطيلِ في التنقل — INJ-FIX-01 · GAP-19
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معيارُ القبول**: «صفرُ رابطٍ **مصطنَعٍ من سجلٍّ معطَّل**».
 *
 * ◆ **العطبُ المقيس**: طبقةُ «صفرِ الفقد» تصطنع رابطًا لكلِّ مسارٍ يحمله
 *   `nav_canonical_current` ولا يُغطّيه صفُّ تبعية. و`$items` لا يحمل إلا
 *   الصفوفَ **النشطة** — فالمسارُ الذي **صفُّه موجودٌ ومعطَّلٌ عمدًا** لا يُعَدُّ
 *   مغطًّى، فيُعاد اصطناعُه بأيقونةِ `fa fa-link`.
 *   ⇐ **فتعطيلُ الرابطِ لا يُخفيه**: يعود من بابٍ آخر، ومن عطَّله يظنُّه مُزالًا.
 *   وقُيس **٢٢ مسارًا** في هذه الحال قبلَ الإصلاح.
 *
 * ◆ **وموضعان لا موضعٌ واحد**: المُهيِّئُ (`printStageNav`) **ودالةُ التصيير**
 *   (`printEmsTenGroupNav`). وسدُّه في الأولِ وحدَه أبقى ستةَ روابطَ تُصيَّر —
 *   فالثاني هو الذي يُخرج الوسمَ فعلًا.
 *
 * ◆ **والمرساةُ استثناءٌ مُعلَنٌ لا ثقب**: «الرئيسية» (`main/role_board.php`)
 *   يجب أن تظهر **أولًا** لكلِّ دور، وصفُّها في `nav_items` قد يكون معطَّلًا
 *   لأن **موضعَه** خطأٌ لا لأن الرابطَ ممنوع. فتوليدُها من السجلِّ مقصودٌ
 *   وموثَّقٌ في متنِ الدالة. فيُقاس الثقبُ **خارجَ المرساتَين** — وإلا عُوقب
 *   سلوكٌ صحيحٌ باسمِ إغلاقِ ثقب.
 *
 * التشغيل: php tests/injfix01_nav_disable_hole_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';
while (ob_get_level() > 0) { ob_end_clean(); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/** مرساتان مُعلَنتان تُولَّدان من السجلِّ ولو عُطِّل صفُّهما — بنصِّ الدالة. */
$ANCHORS = array('main/role_board.php', 'chats/index.php');

$okN = 0; $badN = 0;
function ok($c, $l, &$p, &$f, $d = '') { if ($c) { $p++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } else { $f++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } }

echo "════ ثقبُ التعطيلِ في التنقل — GAP-19 ════\n";

/* ── ① المقام: كم صفًّا معطَّلًا يحمله السجلُّ أصلًا؟ ────────────────────── */
$q = $conn->query("SELECT COUNT(*) FROM `nav_canonical_current` c
                     JOIN `nav_items` n ON LOWER(n.`route`) = LOWER(c.`route`) AND n.`role_id` = c.`role_id`
                    WHERE n.`active` = 0");
$candidates = $q ? (int) $q->fetch_row()[0] : -1;
echo "\n── ① المقام ──\n";
ok($candidates > 0, 'ثمَّ مساراتٌ في السجلِّ وصفُّها معطَّل — المقامُ غيرُ صفر',
   $okN, $badN, "{$candidates} مسارًا · فالفحصُ قادرٌ على الرسوب");

/* ── ② المقيسُ في التصييرِ الحيّ ─────────────────────────────────────────── */
echo "\n── ② التصييرُ الحيّ ──\n";
$leaked = array(); $anchored = array(); $rolesScanned = 0;
foreach (uxp_root_roles() as $rid) {
    $rolesScanned++;
    $rendered = array();
    foreach (uxp_parse_nav_html(uxp_render_role_html($conn, $rid)) as $p) {
        $rendered[strtolower(uxp_norm($p['href']))] = true;
    }
    $q = $conn->query("SELECT LOWER(`route`) r FROM `nav_items`
                        WHERE `role_id` = " . (int) $rid . " AND `active` = 0");
    while ($q && $x = $q->fetch_assoc()) {
        if (!isset($rendered[$x['r']])) { continue; }
        if (in_array($x['r'], $ANCHORS, true)) { $anchored[] = "دور {$rid} :: {$x['r']}"; }
        else { $leaked[] = "دور {$rid} :: {$x['r']}"; }
    }
}
echo "  أدوارٌ فُحصت: {$rolesScanned}\n";
ok(count($leaked) === 0,
   '**صفرُ رابطٍ مصطنَعٍ من صفٍّ معطَّل** (خارجَ المرساتَين)', $okN, $badN,
   count($leaked) ? implode(' · ', array_slice($leaked, 0, 6)) : 'لا شيء');

echo "  ◆ المرساتانِ المُعلَنتان (سلوكٌ مقصودٌ لا ثقب): " . count($anchored) . "\n";
foreach (array_slice($anchored, 0, 3) as $a) { echo "      · {$a}\n"; }
if (count($anchored) > 3) { echo "      · …و" . (count($anchored) - 3) . " غيرُها\n"; }

/* ◆ والمرساةُ يجب أن تظهر فعلًا — وإلا كان «صفرُ التسريب» فقدًا مقنَّعًا */
ok(count($anchored) > 0, 'و«الرئيسية» ما تزال تُصيَّر لمن عُطِّل صفُّها — لا فقد',
   $okN, $badN, count($anchored) . ' موضعًا');

/* ── ③ الحارسُ في الموضعَين معًا ────────────────────────────────────────── */
echo "\n── ③ الحارسُ في موضعَيه ──\n";
$src = (string) @file_get_contents($ROOT . '/includes/unified_nav.php');
ok(substr_count($src, 'معطَّلٌ بقرارٍ — لا يُصطنع') === 2,
   'الاستثناءُ مطبَّقٌ في المُهيِّئِ **ودالةِ التصيير** معًا', $okN, $badN,
   substr_count($src, 'معطَّلٌ بقرارٍ — لا يُصطنع') . ' موضعًا');

/* ── ④ وبواباتُ الجولةِ لم تُكسر ────────────────────────────────────────── */
echo "\n── ④ صفرُ فقدٍ لم يُكسر ──\n";
$out = array(); $rc = 0;
exec('"C:/wamp64/bin/php/php8.2.30/php.exe" ' . escapeshellarg($ROOT . '/tools/uxui_preserve_check.php')
     . ' --gate 2>&1', $out, $rc);
ok($rc === 0, 'بوابةُ «صفرِ الفقد» ما تزال خضراءَ بعدَ سدِّ الثقب', $okN, $badN, "rc={$rc}");

echo "───────────────────────────────────────────────────────────────\n";
echo ($badN === 0 ? "✔" : "✘") . " النتيجة: نجح {$okN} · رسب {$badN}\n";

/* حكمُ الإغلاقِ — عقدُ GAP-56: يُصرَّح به بعدَ القياسِ لا يُستنتَج من الذِّكر */
require_once dirname(__DIR__) . '/tools/lib/gap_verdict.php';
gapv('GAP-19', true, 'ثقبُ تعطيلِ صفِّ التنقّلِ مسدودٌ في المُهيِّئِ ودالةِ التصييرِ معًا — بصفرِ فقد', $badN);

exit($badN === 0 ? 0 : 1);
