<?php
/**
 * tools/rpr02_s3_group_bind.php — `RPR-02` §٦ · **س٣** ربطُ البندِ بمجموعتِه المعتمدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٦ الخطوةُ الثالثة: *«صحِّحِ المجموعاتِ لتطابقَ
 *   مجموعاتِ الملفِّ **اسمًا وعددًا**»*. والمقيسُ بـ`rpr02_s6_sidebar.php`:
 *   **١٬٢٢٣** موضعًا يحتاج تصحيحًا على **٢٧٩** مسارًا فريدًا.
 *
 * ◆ **والطرفُ الذي يُقاس عليه `nav_canonical`** — لا `repair01_requirements`
 *   مباشرةً: المقياسُ نفسُه (‏س٣) يقارن **المخزنَ الإرثيَّ بالمعتمَد**،
 *   والمعتمَدُ نفسُه قد حوذي بالملفِّ في `rpr02_sidebar_align.php` (‏#٨:
 *   ٦٨٫١٪ ⇒ ٩٠٫٢٪). ⇒ **فالسلسلةُ: ملفٌّ ⇐ معتمَدٌ ⇐ مخزنٌ إرثيّ** — ولا
 *   يُقفز فوق حلقةٍ منها فيتفرّق قارئان.
 *
 * ◆ **واتجاهُ الكتابةِ إلى المخزن**: `nav_items.group_id` يُعاد توجيهُه إلى
 *   مجموعةِ الدورِ التي اسمُها اسمُ المعتمَد. ⛔ **ولا يُعاد تسميةُ مجموعةٍ
 *   قائمة**: المجموعةُ الواحدةُ يسكنها بنودٌ كثيرة، فإعادةُ تسميتِها لأجلِ
 *   بندٍ **تنقل البقيّةَ معه** — عطبٌ يخلقه العلاج.
 *
 * ◆ **والمجموعةُ تُنشأ حين لا تكون** بترتيبٍ **مشتقٍّ من `sort_no` المعتمَد**
 *   (‏أصغرُ ترتيبِ ساكنٍ) ⛔ **لا مخترَعٍ من عندنا**، وبأيقونةِ المجلَّدِ
 *   الافتراضيّةِ نفسِها التي يعرّفها المخطَّط.
 *
 * ◆ **والمعاينةُ أثرٌ للمراجعةِ لا بوّابةُ إذن** (‏أمرُ الإنهاءِ §٥ القاعدة ١):
 *   `SIDEBAR_S3_BIND.md` قبل/بعد بالأدوارِ والأزواج — **ثمَّ يُطبَّق فورًا**.
 *
 * ⚠ **والأثرُ على ما يراه المستخدمُ يُقاس لا يُظَنّ**: المُصيِّرُ الحيُّ
 *   (`printEmsTenGroupNav`) لا يقرأ `link_groups`، **فالمتوقَّعُ صفرُ فرقٍ في
 *   النصِّ المُصيَّر** — و`--fingerprint` يطبع بصمةَ تصييرِ كلِّ دورٍ لتُقارن
 *   قبل/بعد. ⛔ **وتوقُّعٌ لا يُقاس ليس دليلًا.**
 *
 * التشغيل:
 *   php tools/rpr02_s3_group_bind.php [--apply] [--md] [--fingerprint] [--selftest]
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
$FP    = in_array('--fingerprint', $argv, true);
$SELF  = in_array('--selftest', $argv, true);

/* ═══ ① التطبيعُ — **نسخةُ المقياسِ حرفًا** ولا نسختان تتفرّقان ═══════════ */
function s3_norm($s)
{
    $s = preg_replace('~[\x{064B}-\x{0652}\x{0670}\x{0640}]~u', '', (string) $s);
    $s = preg_replace('~[\x{0622}\x{0623}\x{0625}]~u', "\u{0627}", $s);
    $s = preg_replace('~\x{0649}~u', "\u{064A}", $s);
    $s = preg_replace('~\x{0629}~u', "\u{0647}", $s);
    $s = preg_replace('~[«»"\'\[\]\-–—/·،,\.]~u', ' ', $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
}

/* ═══ ② بصمةُ التصييرِ الحيِّ لكلِّ دور — دليلُ «لا فرقَ يراه مستخدم» ═════ */
function s3_fingerprint($conn)
{
    /* ⛔ **العُدَّةُ تُحمَّل كاملةً**: `uxp_render_role_html` تنادي
       `renderUnifiedNavigationV2` — وبلا `unified_nav.php` يسقط النداءُ فادحًا
       **صامتًا** (‏`config.php` يبتلع مخرجَ CLI فيخرج ٢٥٥ بلا سطرِ خطأ). */
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
if ($FP && !$APPLY) {
    foreach (s3_fingerprint($conn) as $rid => $fp) { echo "  دور $rid  $fp\n"; }
    exit(0);
}

/* ═══ ③ اللقطة — ⛔ ولا صفَّ بلا لقطة ═══════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : '';
if ($APPLY && $sid === '') { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — ولا يُطبَّق ربطٌ بلا لقطة\n"); }

/* ═══ ④ الطرفان: المخزنُ الحيُّ · والسجلُّ المعتمَد ═══════════════════════ */
$canon = array();
$r = $conn->query("SELECT route, group_name, sort_no, status FROM nav_canonical");
while ($r && ($x = $r->fetch_assoc())) { $canon[strtolower(trim((string) $x['route'], '/'))] = $x; }

$groups = array();   /* role_id => [norm(name) => id] */
$gname  = array();   /* id => name */
$r = $conn->query("SELECT id, name, owner_role_id, is_active FROM link_groups");
while ($r && ($x = $r->fetch_assoc())) {
    $gname[(int) $x['id']] = $x['name'];
    if ($x['owner_role_id'] === null || (int) $x['is_active'] !== 1) { continue; }
    $k = s3_norm($x['name']);
    if (!isset($groups[(int) $x['owner_role_id']][$k])) { $groups[(int) $x['owner_role_id']][$k] = (int) $x['id']; }
}

$items = array();
$r = $conn->query("SELECT n.id, n.role_id, n.group_id, n.route, n.label_ar, g.name AS gname
                     FROM nav_items n LEFT JOIN link_groups g ON g.id = n.group_id
                    WHERE n.active = 1 ORDER BY n.role_id, n.id");
while ($r && ($x = $r->fetch_assoc())) { $items[] = $x; }

/* ═══ ⑤ الحكمُ — بندًا بندًا ═══════════════════════════════════════════ */
$plan = array(); $ok = 0; $na = 0; $pairs = array(); $byRole = array(); $routes = array();
foreach ($items as $it) {
    $rt = strtolower(trim((string) $it['route'], '/'));
    /* رمزُ `?view=`/`?tab=` صيغةُ عرضٍ لسطحٍ مسجَّل — يُردُّ إلى أساسِه
       ([[nav-view-param-ownership]])، ولولا الردُّ لعُدَّ بلا معتمَدٍ وهو له. */
    if (!isset($canon[$rt])) {
        $b = preg_replace('~[?#].*$~', '', $rt);
        if ($b !== $rt && isset($canon[$b])) { $rt = $b; }
    }
    $cn = isset($canon[$rt]) ? $canon[$rt] : null;
    if (!$cn || trim((string) $cn['group_name']) === '') { $na++; continue; }
    if (s3_norm($cn['group_name']) === s3_norm($it['gname'])) { $ok++; continue; }
    $plan[] = array('it' => $it, 'canon_route' => $rt, 'target' => $cn['group_name'], 'sort' => (int) $cn['sort_no']);
    $routes[$rt] = 1;
    $byRole[(int) $it['role_id']] = (isset($byRole[(int) $it['role_id']]) ? $byRole[(int) $it['role_id']] : 0) + 1;
    $k = ($it['gname'] === null ? 'بلا مجموعة' : $it['gname']) . ' ⇒ ' . $cn['group_name'];
    $pairs[$k] = (isset($pairs[$k]) ? $pairs[$k] : 0) + 1;
}

/* ═══ ⑥ الاختبارُ السالب ═══════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    if (count($items) < 100) { echo "  X المقامُ الحيُّ " . count($items) . " — القراءةُ لم تتمّ\n"; $fail++; }
    if (count($canon) < 100) { echo "  X السجلُّ المعتمَدُ " . count($canon) . " صفًّا — مصفاةٌ عمياء\n"; $fail++; }
    if (count($groups) < 5)  { echo "  X المجموعاتُ بالأدوارِ " . count($groups) . " — القراءةُ لم تتمّ\n"; $fail++; }
    /* **الكاسر**: مفردةٌ لا وجودَ لها في السجلِّ يجب ألّا تُنتج خطّةً
       ([[measure-token-must-exist]]) — واسمُ مجموعةٍ وهميٌّ لا يُطابق شيئًا */
    if (isset($groups[0])) { echo "  X دورٌ صفريٌّ في خريطةِ المجموعات\n"; $fail++; }
    $probe = s3_norm('zzq مجموعةٌ وهميّةٌ للفحصِ السالب');
    foreach ($groups as $rid => $m) { if (isset($m[$probe])) { echo "  X مجموعةٌ وهميّةٌ وُجدت للدور $rid\n"; $fail++; } }
    /* **وحكمُ التطبيعِ لا يبتلع فرقًا حقيقيًّا** */
    if (s3_norm('العقود وأحكامها') === s3_norm('التعاقد والالتزام')) { echo "  X التطبيعُ يبتلع فرقًا حقيقيًّا\n"; $fail++; }
    if (s3_norm('الميزانية والإنجاز') !== s3_norm('الميزانيه والانجاز')) { echo "  X التطبيعُ لا يوحّد الهمزةَ والتاءَ المربوطة\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — طرفان مقروءان، والتطبيعُ يوحّد ولا يبتلع\n";
    exit($fail ? 1 : 0);
}

/* ═══ ⑦ العرض ═══════════════════════════════════════════════════════════ */
echo "\n═══ `RPR-02` §٦ · **س٣** — ربطُ البندِ بمجموعتِه المعتمدة ═══\n";
printf("  اللقطة %s · بنودٌ حيّةٌ %d · مستقيمٌ %d · **يحتاج ربطًا %d** على **%d** مسارًا فريدًا · لا ينطبق %d\n",
       ($sid !== '' ? $sid : 'DRY'), count($items), $ok, count($plan), count($routes), $na);
arsort($pairs);
printf("\n  أزواجٌ متميّزةٌ %d — أعلاها:\n", count($pairs));
$i = 0;
foreach ($pairs as $k => $v) { printf("    %5d  %s\n", $v, $k); if (++$i >= 12) { break; } }
ksort($byRole);
echo "\n  بالأدوار: ";
foreach ($byRole as $k => $v) { echo "$k:$v "; }
echo "\n";

if (!$APPLY) {
    echo "\n  ⛔ **معاينةٌ — لم يُكتب شيء.** والتطبيقُ بـ`--apply`.\n";
}

/* ═══ ⑧ التطبيق ═══════════════════════════════════════════════════════ */
$made = 0; $moved = 0;
if ($APPLY) {
    $fpBefore = s3_fingerprint($conn);
    /* ترتيبُ المجموعةِ المُنشأةِ = أصغرُ `sort_no` معتمَدٍ لساكنيها — مشتقٌّ لا مخترَع */
    $minSort = array();
    foreach ($plan as $p) {
        $k = (int) $p['it']['role_id'] . '|' . s3_norm($p['target']);
        if (!isset($minSort[$k]) || $p['sort'] < $minSort[$k]) { $minSort[$k] = $p['sort']; }
    }
    $conn->query('START TRANSACTION');
    foreach ($plan as $p) {
        $rid = (int) $p['it']['role_id'];
        $tk  = s3_norm($p['target']);
        $created = 0;
        if (!isset($groups[$rid][$tk])) {
            $ord = isset($minSort[$rid . '|' . $tk]) ? (int) $minSort[$rid . '|' . $tk] : 999;
            $sql = "INSERT INTO `link_groups` (`name`, `owner_role_id`, `icon`, `display_order`, `is_active`)
                    VALUES ('" . $e($p['target']) . "', $rid, 'fa fa-folder', $ord, 1)";
            if (!$conn->query($sql)) { $conn->query('ROLLBACK'); exit("✘ تعذّر إنشاءُ المجموعة: {$conn->error}\n"); }
            $groups[$rid][$tk] = (int) $conn->insert_id;
            $gname[$groups[$rid][$tk]] = $p['target'];
            $created = 1; $made++;
        }
        $gid = (int) $groups[$rid][$tk];
        $bg  = ($p['it']['group_id'] === null) ? null : (int) $p['it']['group_id'];
        if ($bg === $gid) { continue; }   /* ⛔ ولا صفَّ بلا تغييرٍ فعليّ */
        $wit = 'س٣ · المعتمَدُ «' . $p['target'] . '» والمخزنُ «'
             . ($p['it']['gname'] === null ? 'بلا مجموعة' : $p['it']['gname'])
             . '» — `nav_canonical.group_name` لِـ' . $p['canon_route']
             . ' (حالة ' . (isset($canon[$p['canon_route']]) ? $canon[$p['canon_route']]['status'] : '?') . ')';
        $sql = "INSERT INTO `repair01_nav_group_bind`
                (nav_item_id, role_id, route, canon_route, before_group_id, before_group_name,
                 after_group_id, after_group_name, group_created, witness, snapshot_id, applied_at)
                VALUES (" . (int) $p['it']['id'] . ", $rid, '" . $e($p['it']['route']) . "', '" . $e($p['canon_route']) . "', "
              . ($bg === null ? 'NULL' : $bg) . ", '" . $e((string) $p['it']['gname']) . "', $gid, '"
              . $e($p['target']) . "', $created, '" . $e($wit) . "', '" . $e($sid) . "', NOW())
                ON DUPLICATE KEY UPDATE after_group_id = VALUES(after_group_id),
                                        after_group_name = VALUES(after_group_name),
                                        witness = VALUES(witness), applied_at = NOW()";
        if (!$conn->query($sql)) { $conn->query('ROLLBACK'); exit("✘ تعذّر تسجيلُ الربط: {$conn->error}\n"); }
        if (!$conn->query("UPDATE `nav_items` SET `group_id` = $gid WHERE `id` = " . (int) $p['it']['id'])) {
            $conn->query('ROLLBACK'); exit("✘ تعذّر الربط: {$conn->error}\n");
        }
        $moved++;
    }
    $conn->query('COMMIT');
    printf("\n  ✔ **رُبط %d بندًا** · وأُنشئت %d مجموعةً بترتيبٍ مشتقٍّ من `sort_no`\n", $moved, $made);

    $fpAfter = s3_fingerprint($conn);
    $diff = array();
    foreach ($fpAfter as $rid => $fp) {
        if (!isset($fpBefore[$rid]) || $fpBefore[$rid] !== $fp) { $diff[] = $rid; }
    }
    printf("  ◆ **بصمةُ التصييرِ الحيِّ**: %d دورًا · مختلفٌ بعدَ الربطِ **%d**%s\n",
           count($fpAfter), count($diff), $diff ? ' (' . implode(' · ', $diff) . ')' : '');
    echo $diff
        ? "  ⚠ **فرقٌ يراه مستخدم** — يُسمّى ويُراجَع، ⛔ ولا يُمرَّر صامتًا\n"
        : "  ✔ **صفرُ فرقٍ في النصِّ المُصيَّر** — الربطُ مخزنيٌّ بحتٌّ كما تنبّأ المُصيِّر، **ومقيسٌ لا مظنون**\n";
}

/* ═══ ⑨ المعاينةُ المكتوبة ═══════════════════════════════════════════════ */
if ($MD) {
    $o  = "# `RPR-02` §٦ · **س٣** — ربطُ البندِ بمجموعتِه المعتمدة\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `"
        . ($sid !== '' ? $sid : 'DRY') . "`\n\n";
    $o .= 'المقامُ **بنودٌ حيّةٌ ' . count($items) . '** · مستقيمٌ **' . $ok . '** · يحتاج ربطًا **'
        . count($plan) . '** على **' . count($routes) . "** مسارًا فريدًا · لا ينطبق **$na**.\n\n";
    $o .= "⛔ **والعمودان لا يُخلطان**: البندُ يتكرَّر بعددِ الأدوارِ التي تراه.\n\n";
    $o .= "## الأزواجُ — من مجموعةِ المخزنِ إلى المجموعةِ المعتمدة\n\n";
    $o .= "| مواضع | من (مخزنٌ إرثيّ) | إلى (معتمَدٌ في `nav_canonical`) |\n|---:|---|---|\n";
    foreach ($pairs as $k => $v) {
        $x = explode(' ⇒ ', $k, 2);
        $o .= '| ' . $v . ' | ' . $x[0] . ' | **' . (isset($x[1]) ? $x[1] : '') . "** |\n";
    }
    $o .= "\n## بالأدوار\n\n| دور | مواضعُ تحتاج ربطًا |\n|---:|---:|\n";
    foreach ($byRole as $k => $v) { $o .= "| $k | $v |\n"; }
    $o .= "\n⚠ **والأثرُ على المُصيَّرِ يُقاس لا يُظَنّ**: المُصيِّرُ الحيَّ لا يقرأ `link_groups`،\n";
    $o .= "فالمتوقَّعُ صفرُ فرقٍ — و`--apply` يطبع بصمةَ كلِّ دورٍ قبلَ الربطِ وبعدَه.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/SIDEBAR_S3_BIND.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/SIDEBAR_S3_BIND.md\n";
}
