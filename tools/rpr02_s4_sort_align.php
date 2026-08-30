<?php
/**
 * tools/rpr02_s4_sort_align.php — `RPR-02` §٦ · **س٤** ترتيبُ المخزنِ على دورةِ العمل
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٦ الخطوةُ الرابعة: *«رتِّبِ الأسطحَ على دورةِ العملِ
 *   في الملفِّ **لا على تاريخِ الإضافة**»*. و`nav_items.sort_order` يحمل تاريخَ
 *   الإضافة، **وهو «الترتيبُ اليدويُّ الموازي» الذي تنهى عنه الخطوةُ السابعةُ
 *   نفسُها**.
 *
 * ◆ **والشقُّ الأولُ سبقه**: `rpr02_sidebar_align.php --apply` حاذى
 *   `nav_canonical.sort_no` بتسلسلِ الملفِّ التصميميِّ في ١٣١ مسارًا — **وهذا
 *   يُلحق المخزنَ بالمعتمَدِ بعدَ استقرارِه**، ⛔ ولا يُقفز فوق حلقة.
 *
 * ◆ **والمقياسُ ترتيبٌ لا قيمة** (‏كما يقيسه `rpr02_s6_sidebar.php` حرفًا):
 *   داخلَ كلِّ سلّةٍ `role|group` يُرتَّب بالمعروضِ ثمَّ بالمعتمَد، وكلُّ بندٍ
 *   اختلف موضعُه بين الترتيبَين يُعدُّ محتاجًا. ⛔ **ولا تُقارَن القيمتان عددًا**.
 *
 * ◆ **والعلاجُ أصغرُ ما يحقّق المطلوب**: تُعاد قيمُ السلّةِ نفسِها موزَّعةً على
 *   بنودِها بترتيبِ الدورة — ⛔ **لا نسخُ `sort_no` قيمةً**: فلا تتحرَّك السلّةُ
 *   بالنسبةِ لأخواتِها في أيِّ مُصيِّرٍ يقرأ `sort_order`، ويتحرَّك ما بداخلها.
 *
 * ◆ **والتعادلُ يُكسر كما يكسره المقياس** — بالمسار: لو بقيت قيمتان متساويتَين
 *   لقرَّر كسرُ التعادلِ الترتيبَ فبقي الموضعُ مخالفًا وهو «مُحاذًى» ظاهرًا.
 *   ⇒ تُجعل القيمُ **متزايدةً تمامًا** داخلَ السلّة.
 *
 * ⚠ **والأثرُ على المُصيَّرِ يُقاس لا يُظَنّ**: `printEmsTenGroupNav` يرتّب
 *   بـ`sort_no` المعتمَدِ لا بـ`sort_order` — **فالمتوقَّعُ صفرُ فرقٍ**،
 *   و`--apply` يطبع بصمةَ تصييرِ كلِّ دورٍ قبلَه وبعدَه. ⛔ **وتوقُّعٌ لا يُقاس
 *   ليس دليلًا.**
 *
 * التشغيل:
 *   php tools/rpr02_s4_sort_align.php [--apply] [--md] [--selftest]
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
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };

$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$SELF  = in_array('--selftest', $argv, true);

function s4_fingerprint($conn)
{
    /* ⛔ العُدَّةُ كاملةً — وإلّا سقط النداءُ فادحًا صامتًا (`config.php` يبتلع CLI) */
    require_once dirname(__DIR__) . '/includes/unified_nav.php';
    require_once dirname(__DIR__) . '/includes/uxui_nav_probe.php';
    $out = array();
    $r = $conn->query("SELECT DISTINCT role_id FROM nav_items WHERE active = 1 ORDER BY role_id");
    while ($r && ($x = $r->fetch_row())) {
        $html = uxp_render_role_html($conn, (int) $x[0]);
        $out[(int) $x[0]] = substr(sha1($html), 0, 12) . '/' . strlen($html);
    }
    return $out;
}

/** الترتيبُ داخلَ السلّة — **نسخةُ المقياسِ حرفًا**: القيمةُ ثمَّ المسارُ كاسرًا */
function s4_sort_live(&$rows) {
    usort($rows, function ($a, $b) { return $a['live'] === $b['live'] ? strcmp($a['rt'], $b['rt']) : $a['live'] - $b['live']; });
}
function s4_sort_spec(&$rows) {
    usort($rows, function ($a, $b) { return $a['spec'] === $b['spec'] ? strcmp($a['rt'], $b['rt']) : $a['spec'] - $b['spec']; });
}

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : '';
if ($APPLY && $sid === '') { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — ولا تُطبَّق محاذاةٌ بلا لقطة\n"); }

/* ═══ ① الطرفان ═════════════════════════════════════════════════════════ */
$canon = array();
$r = $conn->query("SELECT route, sort_no FROM nav_canonical");
while ($r && ($x = $r->fetch_assoc())) { $canon[strtolower(trim((string) $x['route'], '/'))] = (int) $x['sort_no']; }

$buckets = array(); $na = 0; $N = 0;
$r = $conn->query("SELECT id, role_id, group_id, route, sort_order FROM nav_items WHERE active = 1");
while ($r && ($x = $r->fetch_assoc())) {
    $N++;
    $b = strtolower(trim((string) $x['route'], '/'));
    if (!isset($canon[$b])) {
        $p = preg_replace('~[?#].*$~', '', $b);
        if (isset($canon[$p])) { $b = $p; }
    }
    if (!isset($canon[$b]) || $canon[$b] <= 0) { $na++; continue; }
    $buckets[(int) $x['role_id'] . '|' . (int) $x['group_id']][] = array(
        'id' => (int) $x['id'], 'role' => (int) $x['role_id'], 'gid' => (int) $x['group_id'],
        'rt' => (string) $x['route'], 'live' => (int) $x['sort_order'], 'spec' => $canon[$b],
    );
}

/* ═══ ② الحكمُ والخطّة ══════════════════════════════════════════════════ */
$plan = array(); $ok = 0; $bad = 0; $badRt = array(); $badBuckets = 0;
foreach ($buckets as $key => $rows) {
    $live = $rows; $spec = $rows;
    s4_sort_live($live); s4_sort_spec($spec);
    $d = 0;
    foreach ($live as $pos => $x) {
        if ($spec[$pos]['rt'] === $x['rt']) { $ok++; } else { $bad++; $badRt[$x['rt']] = 1; $d++; }
    }
    if ($d === 0) { continue; }
    $badBuckets++;
    /* قيمُ السلّةِ نفسِها — مرتَّبةً ثمَّ متزايدةً تمامًا، تُوزَّع على ترتيبِ الدورة */
    $vals = array(); foreach ($rows as $x) { $vals[] = $x['live']; }
    sort($vals);
    $prev = null;
    foreach ($vals as $i => $v) { if ($prev !== null && $v <= $prev) { $v = $prev + 1; } $vals[$i] = $v; $prev = $v; }
    foreach ($spec as $pos => $x) {
        if ($x['live'] === $vals[$pos]) { continue; }   /* ⛔ ولا صفَّ بلا تغييرٍ فعليّ */
        $plan[] = array('row' => $x, 'after' => $vals[$pos], 'bucket' => $key, 'n' => count($rows), 'pos' => $pos + 1);
    }
}

/* ═══ ③ الاختبارُ السالب ════════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    if ($N < 100) { echo "  X المقامُ الحيُّ $N — القراءةُ لم تتمّ\n"; $fail++; }
    if (count($canon) < 100) { echo '  X السجلُّ المعتمَدُ ' . count($canon) . " صفًّا — مصفاةٌ عمياء\n"; $fail++; }
    if (count($buckets) < 10) { echo '  X السلالُ ' . count($buckets) . " — القراءةُ لم تتمّ\n"; $fail++; }
    /* **الكاسرُ ①**: كلُّ سلّةٍ بعدَ الخطّةِ تُنتج ترتيبَ الدورةِ بالضبط */
    $sim = array();
    foreach ($plan as $p) { $sim[$p['row']['id']] = $p['after']; }
    $rem = 0;
    foreach ($buckets as $rows) {
        foreach ($rows as $i => $x) { if (isset($sim[$x['id']])) { $rows[$i]['live'] = $sim[$x['id']]; } }
        $l = $rows; $s = $rows; s4_sort_live($l); s4_sort_spec($s);
        foreach ($l as $pos => $x) { if ($s[$pos]['rt'] !== $x['rt']) { $rem++; } }
    }
    if ($rem !== 0) { echo "  X الخطّةُ تترك $rem موضعًا مخالفًا — محاكاةً\n"; $fail++; }
    /* **الكاسرُ ②**: القيمُ متزايدةٌ تمامًا **في السلالِ التي مسَّتها الخطّةُ وحدَها**
       — فلا يقرّر كسرُ التعادلِ ترتيبًا فيها. ⛔ **ولا يُوسَّع الشرطُ إلى سلّةٍ
       مستقيمةٍ سلفًا**: قيمتان متساويتان فيها لا تُنتجان مخالفةً (كسرُ التعادلِ
       بالمسارِ يوافق الدورةَ)، وإصلاحُها **إعادةُ ما بلغ مستهدَفَه** لا علاج. */
    $touched = array();
    foreach ($plan as $p) { $touched[$p['bucket']] = 1; }
    foreach ($buckets as $key => $rows) {
        if (!isset($touched[$key])) { continue; }
        foreach ($rows as $i => $x) { if (isset($sim[$x['id']])) { $rows[$i]['live'] = $sim[$x['id']]; } }
        $seen = array();
        foreach ($rows as $x) {
            if (isset($seen[$x['live']])) { echo "  X قيمتان متساويتان بعدَ المحاذاةِ في سلّةٍ مَسَّتها الخطّة: $key\n"; $fail++; break 2; }
            $seen[$x['live']] = 1;
        }
    }
    /* **وخبرٌ لا حكم**: سلالٌ مستقيمةٌ سلفًا بقيمٍ متساوية — تُعَدُّ ولا تُمَسّ */
    $dupStraight = 0;
    foreach ($buckets as $key => $rows) {
        if (isset($touched[$key])) { continue; }
        $seen = array();
        foreach ($rows as $x) {
            if (isset($seen[$x['live']])) { $dupStraight++; break; }
            $seen[$x['live']] = 1;
        }
    }
    echo "  ◆ خبرٌ: سلالٌ مستقيمةٌ سلفًا بقيمٍ متساوية **$dupStraight** — لا تُمَسّ (‏كسرُ التعادلِ فيها يوافق الدورةَ)\n";
    /* **الكاسرُ ③**: مسارٌ لا وجودَ له لا يجد ترتيبًا معتمَدًا ([[measure-token-must-exist]]) */
    if (isset($canon['zzq/absent_route_probe.php'])) { echo "  X مسارٌ وهميٌّ وُجد في السجلِّ المعتمَد\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — الخطّةُ تُصفِّر المخالفَ محاكاةً، والقيمُ متزايدةٌ تمامًا\n";
    exit($fail ? 1 : 0);
}

/* ═══ ④ العرض ═══════════════════════════════════════════════════════════ */
echo "\n═══ `RPR-02` §٦ · **س٤** — ترتيبُ المخزنِ على دورةِ العمل ═══\n";
printf("  اللقطة %s · بنودٌ حيّةٌ %d · **سلالٌ %d** · مستقيمٌ %d · **يحتاج ترتيبًا %d** على **%d** مسارًا فريدًا · لا ينطبق %d\n",
       ($sid !== '' ? $sid : 'DRY'), $N, count($buckets), $ok, $bad, count($badRt), $na);
printf("  ◆ سلالٌ يقع فيها الخلافُ: **%d** من %d — **والباقي مستقيمٌ سلفًا فلا يُمَسّ**\n",
       $badBuckets, count($buckets));
printf("  ⇒ **خطّةٌ: %d بندًا تُعاد قيمتُه داخلَ سلّتِه** (‏قيمُ السلّةِ نفسِها موزَّعةً على ترتيبِ الدورة)\n", count($plan));
$i = 0;
foreach ($plan as $p) {
    printf("     · سلّة %-10s موضع %d/%d : %s  %d ⇒ %d\n", $p['bucket'], $p['pos'], $p['n'],
           $p['row']['rt'], $p['row']['live'], $p['after']);
    if (++$i >= 10) { break; }
}
if (!$APPLY) { echo "\n  ⛔ **معاينةٌ — لم يُكتب شيء.** والتطبيقُ بـ`--apply`.\n"; }

/* ═══ ⑤ التطبيق ═════════════════════════════════════════════════════════ */
if ($APPLY) {
    $fpBefore = s4_fingerprint($conn);
    $conn->query('START TRANSACTION');
    $n = 0;
    foreach ($plan as $p) {
        $x = $p['row'];
        $wit = 'س٤ · ترتيبُ الدورةِ لا تاريخُ الإضافة: سلّة `' . $p['bucket'] . '` (' . $p['n'] . ' بندًا) '
             . 'موضعُ الدورةِ ' . $p['pos'] . ' · `nav_canonical.sort_no` = ' . $x['spec']
             . ' · وقيمُ السلّةِ نفسِها أُعيد توزيعُها ⛔ لا نسخُ `sort_no` قيمةً';
        $sql = "INSERT INTO `repair01_nav_sort_align`
                (nav_item_id, role_id, group_id, route, before_sort, after_sort, canon_sort, bucket_size,
                 witness, snapshot_id, applied_at)
                VALUES (" . $x['id'] . ", " . $x['role'] . ", " . $x['gid'] . ", '" . $e($x['rt']) . "', "
              . $x['live'] . ", " . (int) $p['after'] . ", " . $x['spec'] . ", " . (int) $p['n'] . ", '"
              . $e($wit) . "', '" . $e($sid) . "', NOW())
                ON DUPLICATE KEY UPDATE after_sort = VALUES(after_sort), canon_sort = VALUES(canon_sort),
                                        witness = VALUES(witness), applied_at = NOW()";
        if (!$conn->query($sql)) { $conn->query('ROLLBACK'); exit("✘ تعذّر تسجيلُ المحاذاة: {$conn->error}\n"); }
        if (!$conn->query("UPDATE `nav_items` SET `sort_order` = " . (int) $p['after'] . " WHERE `id` = " . $x['id'])) {
            $conn->query('ROLLBACK'); exit("✘ تعذّرت المحاذاة: {$conn->error}\n");
        }
        $n++;
    }
    $conn->query('COMMIT');
    printf("\n  ✔ **رُتِّب %d بندًا** في %d سلّةً — بقيمِ السلّةِ نفسِها\n", $n, $badBuckets);

    $fpAfter = s4_fingerprint($conn);
    $diff = array();
    foreach ($fpAfter as $rid => $fp) { if (!isset($fpBefore[$rid]) || $fpBefore[$rid] !== $fp) { $diff[] = $rid; } }
    printf("  ◆ **بصمةُ التصييرِ الحيِّ**: %d دورًا · مختلفٌ بعدَ الترتيبِ **%d**%s\n",
           count($fpAfter), count($diff), $diff ? ' (' . implode(' · ', $diff) . ')' : '');
    echo $diff
        ? "  ⚠ **فرقٌ يراه مستخدم** — يُسمّى ويُراجَع، ⛔ ولا يُمرَّر صامتًا\n"
        : "  ✔ **صفرُ فرقٍ في النصِّ المُصيَّر** — المُصيِّرُ يرتّب بـ`sort_no` المعتمَدِ، **ومقيسٌ لا مظنون**\n";
}

/* ═══ ⑥ المعاينةُ المكتوبة ══════════════════════════════════════════════ */
if ($MD) {
    $o  = "# `RPR-02` §٦ · **س٤** — ترتيبُ المخزنِ على دورةِ العمل\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `"
        . ($sid !== '' ? $sid : 'DRY') . "`\n\n";
    $o .= '| المفردة | العدد |' . "\n|---|---:|\n";
    $o .= "| بنودٌ حيّة | $N |\n| سلالُ `role\\|group` | " . count($buckets) . " |\n";
    $o .= "| مستقيمٌ سلفًا | $ok |\n| **يحتاج ترتيبًا** | **$bad** |\n";
    $o .= '| مسارٌ فريدٌ يحتاج | **' . count($badRt) . "** |\n| سلالٌ يقع فيها الخلاف | **$badBuckets** |\n";
    $o .= "| لا ينطبق (بلا `sort_no` معتمَد) | $na |\n\n";
    $o .= "⛔ **والمقياسُ ترتيبٌ لا قيمة**: `sort_order=10` و`sort_no=3` قد ينتجان الموضعَ نفسَه —\n";
    $o .= "فمقارنةُ القيمةِ تُنتج حُمرةً كاذبةً كاسحة.\n\n";
    $o .= "## الخطّة — قيمُ السلّةِ نفسِها موزَّعةً على ترتيبِ الدورة\n\n";
    $o .= "| سلّة `role\\|group` | موضعُ الدورة | المسار | قبل | بعد |\n|---|---:|---|---:|---:|\n";
    foreach ($plan as $p) {
        $o .= '| `' . $p['bucket'] . '` | ' . $p['pos'] . '/' . $p['n'] . ' | `' . $p['row']['rt'] . '` | '
            . $p['row']['live'] . ' | **' . $p['after'] . "** |\n";
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/SIDEBAR_S4_SORT.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/SIDEBAR_S4_SORT.md\n";
}
