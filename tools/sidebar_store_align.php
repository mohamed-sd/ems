<?php
/**
 * tools/sidebar_store_align.php — محاذاةُ صفوفِ المخزنِ غيرِ المُصيَّرة (البند ⑤)
 * ═══════════════════════════════════════════════════════════════════════════
 * `FINAL_CLOSE` البند ⑤: بعد محاذاةِ المُصيَّرِ (`sidebar_render_align.php`)
 * بقي في مقياسِ `RPR-02` #٨ صفوفُ `nav_items` **لا تُصيَّر لدورِها** (يحجبها
 * قالبٌ أو صلاحيةٌ أو عزلٌ) فلم تلمسها أداةُ التصيير — ومجموعتُها تُقرأ من
 * المعتمَدِ القديمِ فتخالف الملفَّ التصميميّ.
 *
 * ◆ **القناةُ نفسُها**: إعلانٌ في الحاكمِ المُثبَتِ `gov_target_nav`
 *   (`doc_code=RENDER-ALIGN-R<n>`) بمجموعةِ الملفِّ من `repair01_requirements`
 *   بجسرِ الكونِ `MATCHED` — وسجلُّ التراجعِ `repair01_render_align` نفسُه.
 * ◆ ⛔ ولا يُظهر هذا بندًا محجوبًا — الإعلانُ يحكم المجموعةَ إذا صُيِّر،
 *   والحجبُ بسببِه قائمٌ لا يمسُّه.
 *
 * التشغيل: php tools/sidebar_store_align.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn']; mysqli_set_charset($conn, 'utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };
$APPLY = in_array('--apply', $argv, true);

$snap = null;
$r = $conn->query("SELECT snapshot_id FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) $snap = $r->fetch_assoc()['snapshot_id'];
if (!$snap && $APPLY) exit("⛔ لا نافذةَ قياسٍ مفتوحة — جمِّدْ أوّلًا.\n");
$sid = $snap ? $snap : 'DRY';

/* ── جسرُ المقياس #٨ حرفًا ─────────────────────────────────────────────── */
$s2r = array();
$r = $conn->query("SELECT screen_id, requirement_id FROM repair01_target_universe WHERE verdict='MATCHED' AND screen_id<>'' AND requirement_id<>''");
while ($z = $r->fetch_row()) $s2r[$z[0]] = $z[1];
$spg = array();
$r = $conn->query("SELECT requirement_id, group_name FROM repair01_requirements WHERE group_name<>''");
while ($z = $r->fetch_row()) $spg[$z[0]] = $z[1];
$r2s = array();
$r = $conn->query("SELECT screen_id, route FROM repair01_screen_registry WHERE route<>''");
while ($z = $r->fetch_row()) $r2s[strtolower(trim($z[1], '/'))] = $z[0];
$cg = array();
$r = $conn->query("SELECT route, group_name FROM nav_canonical WHERE group_name<>''");
while ($z = $r->fetch_row()) $cg[strtolower(trim((string)$z[0], '/'))] = $z[1];
$dg = array();
$r = $conn->query("SELECT role_id, route, group_ar FROM gov_target_nav");
while ($z = $r->fetch_assoc()) {
    if (strncmp((string)$z['route'], 'GAP:', 4) === 0) continue;
    $k = strtolower(trim(preg_replace('~[?#].*$~', '', (string)$z['route']), '/'));
    if ($k !== '') $dg[(int)$z['role_id'] . '|' . $k] = (string)$z['group_ar'];
}
$nz = function ($s) {
    $s = preg_replace('~\s*\(([A-Za-z0-9 /._-]+)\)~u', '', (string)$s); // حاشية الملف اللاتينية شرح لا اسم عرض
    $s = preg_replace('~[\x{064B}-\x{0652}\x{0670}\x{0640}]~u', '', (string)$s);
    $s = preg_replace('~[\x{0622}\x{0623}\x{0625}]~u', "\u{0627}", $s);
    $s = preg_replace('~\x{0649}~u', "\u{064A}", $s);
    $s = preg_replace('~\x{0629}~u', "\u{0647}", $s);
    $s = preg_replace('~[«»"\'\[\]\-–—/·،,\.]~u', ' ', $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
};

$plan = array(); $seen = array();
$r = $conn->query("SELECT n.role_id, n.route, n.label_ar AS title, g.name AS gname FROM nav_items n LEFT JOIN link_groups g ON g.id=n.group_id WHERE n.active=1");
while ($z = $r->fetch_assoc()) {
    $b = strtolower(trim(preg_replace('~[?#].*$~', '', (string)$z['route']), '/'));
    $sc = isset($r2s[$b]) ? $r2s[$b] : '';
    $rr = ($sc !== '' && isset($s2r[$sc])) ? $s2r[$sc] : '';
    if ($rr === '' || !isset($spg[$rr])) continue;
    $k = (int)$z['role_id'] . '|' . $b;
    if (isset($seen[$k])) continue;
    $shown = isset($dg[$k]) ? $dg[$k] : (isset($cg[$b]) ? $cg[$b] : (string)$z['gname']);
    if ($nz($shown) === $nz($spg[$rr])) continue;
    if (isset($dg[$k])) continue; // إعلانٌ قائمٌ مخالف — شأن أداة التصيير لا هذه
    $seen[$k] = 1;
    $plan[] = array('rid' => (int)$z['role_id'], 'route' => $b, 'req' => $rr,
                    'g_after' => $spg[$rr], 'shown' => $shown, 'label' => (string)$z['title']);
}

echo "═══ محاذاةُ صفوفِ المخزنِ غيرِ المُصيَّرة — لقطة $sid" . ($APPLY ? '' : ' · DRY') . " ═══\n";
echo "  أزواجٌ (دور·مسار) مخالفةٌ بلا إعلان: " . count($plan) . "\n";

/* رقمُ المجموعةِ ورتبةُ البند بمستوى الدور */
$numOf = function ($rid, $g) use ($conn, $e) {
    $q = $conn->query("SELECT group_no FROM gov_target_nav WHERE role_id=$rid AND group_ar='" . $e($g) . "' AND group_no>0 LIMIT 1");
    if ($q && $q->num_rows) return (int)$q->fetch_assoc()['group_no'];
    $q = $conn->query("SELECT COALESCE(MAX(group_no),0)+1 m FROM gov_target_nav WHERE role_id=$rid");
    return (int)$q->fetch_assoc()['m'];
};
$itemOf = function ($rid, $g) use ($conn, $e) {
    $q = $conn->query("SELECT COALESCE(MAX(item_no),0)+1 m FROM gov_target_nav WHERE role_id=$rid AND group_ar='" . $e($g) . "'");
    return (int)$q->fetch_assoc()['m'];
};

$n = 0;
foreach ($plan as $x) {
    echo "  دور {$x['rid']} · {$x['route']} : «{$x['shown']}» ⇒ «{$x['g_after']}» ({$x['req']})\n";
    if (!$APPLY) continue;
    $gno = $numOf($x['rid'], $x['g_after']);
    $ino = $itemOf($x['rid'], $x['g_after']);
    $conn->query('START TRANSACTION');
    $ok1 = $conn->query("INSERT INTO gov_target_nav (doc_code, role_id, group_no, group_ar, item_no, item_ar, route, note)
        VALUES ('" . $e('RENDER-ALIGN-R' . $x['rid']) . "', {$x['rid']}, $gno, '" . $e($x['g_after']) . "', $ino,
                '" . $e(mb_substr($x['label'], 0, 120)) . "', '" . $e($x['route']) . "',
                '" . $e('محاذاة صف مخزن غير مصير — FINAL_CLOSE 5 · ' . $x['req']) . "')");
    $gtId = (int)$conn->insert_id;
    $wit = 'صف مخزن نشط لا يصير لهذا الدور (حجب قالب او صلاحية او عزل) — والملف يقول «' . $x['g_after']
         . '» (' . $x['req'] . ')؛ الاعلان يحكم المجموعة اذا صير ولا يفك الحجب';
    $ok2 = $conn->query("INSERT INTO repair01_render_align (role_id, route, requirement_id, had_row, gt_id,
            before_group_ar, before_group_no, before_item_no, after_group_ar, after_group_no, after_item_no,
            rendered_before, witness, snapshot_id, applied_at)
        VALUES ({$x['rid']}, '" . $e($x['route']) . "', '" . $e($x['req']) . "', 0, $gtId,
                '" . $e($x['shown']) . "', 0, 0, '" . $e($x['g_after']) . "', $gno, $ino,
                '(غير مصير لهذا الدور — صف مخزن نشط محجوب)', '" . $e($wit) . "', '" . $e($sid) . "', NOW())
        ON DUPLICATE KEY UPDATE witness=VALUES(witness)");
    if (!$ok1 || !$ok2) { $err = $conn->error; $conn->query('ROLLBACK'); exit("✘ {$x['rid']}·{$x['route']}: $err\n"); }
    $conn->query('COMMIT');
    $n++;
}
if ($APPLY) echo "  ✔ أُعلن $n زوجًا في الحاكمِ بسجلِّ تراجعِه\n";
