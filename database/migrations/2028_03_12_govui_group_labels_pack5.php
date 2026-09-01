<?php
/**
 * 2028_03_12_govui_group_labels_pack5.php — أسماءُ مجموعاتِ الدورةِ من الدليلِ ‑5
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: update:nav_lifecycle_groups(label_ar) + log:govui_label_log
 *
 * ◆ **الدفعةُ الثانيةُ من §7** — وهذه المرّةُ **المجموعاتُ لا الشاشات**:
 *   الدليلُ `60a8e3b2` أعاد صياغةَ **ستٍّ وخمسين** من مئةٍ وثمانيَ عشرةَ
 *   مجموعةً — وهي **رؤوسُ الطيِّ التي يراها المستخدم**.
 *
 * ◆ **والمطابقةُ بالموضعِ لا بالاسم** — وهو شرطُ الجولةِ الحاكم: الاسمُ هو
 *   الذي تغيّر، فالمطابقةُ به تفشل. المفتاحُ `(workspace_id, sort_no)`:
 *   مجموعةُ الترتيبِ `n` في المساحةِ `X` هي هي، **فيُحدَّث اسمُها ولا تُنشأ
 *   بديلةٌ ولا تُحذف** — وحذفُها يقطع نسبَ مواضعِها (`nav_placements.group_id`
 *   مفتاحٌ أجنبيٌّ إليها).
 *
 * ◆ **وترتيبُ مجموعاتِ الدليلِ يُقرأ من ترتيبِ ظهورِها في الورقة** — أوّلُ
 *   بطاقةٍ تذكر مجموعةً تُثبت موضعَها؛ وهو الترتيبُ نفسُه الذي بُني به
 *   `nav_lifecycle_groups.sort_no` عند الاستيراد. وقِيس قبلَ الكتابة:
 *   **118 = 118 · صفرُ مجموعةٍ بلا مقابلٍ في الطرفَين**.
 *
 * ◆ ⛔ **ولا يُمَسُّ `group_key`**: هو المفتاحُ المطبَّعُ الذي يجسر الورقةَ
 *   بالمخزن، وتغييرُه يقطع الجسرَ. **الاسمُ المعروضُ وحدَه يتغيّر.**
 *
 * التشغيل: php database/migrations/2028_03_12_govui_group_labels_pack5.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/tools/govui_lib.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

/* ── ① مجموعاتُ الدليلِ بترتيبِ ظهورِها في كلِّ ورقة ── */
$cards = govui_target_cards($ROOT);
$guide = array();
foreach ($cards as $ws => $list) {
    $seen = array(); $n = 0;
    foreach ($list as $c) {
        $g = $c['group_raw'];
        if (isset($seen[$g])) { continue; }
        $seen[$g] = 1;
        $guide[$ws][++$n] = array($g, $c['sheet'], $c['row']);
    }
}
/* ── ② المخزنُ بموضعِه ── */
$store = array();
$r = $conn->query("SELECT id, workspace_id, sort_no, label_ar FROM nav_lifecycle_groups ORDER BY workspace_id, sort_no");
while ($x = $r->fetch_assoc()) { $store[$x['workspace_id']][(int) $x['sort_no']] = $x; }

/* ── ③ حارسُ التطابقِ البنيويِّ قبلَ أيِّ كتابة ── */
$gTot = 0; $sTot = 0; $orphan = array();
foreach ($guide as $ws => $gs) { $gTot += count($gs); foreach ($gs as $n => $v) { if (!isset($store[$ws][$n])) { $orphan[] = "الدليل {$ws}#{$n}"; } } }
foreach ($store as $ws => $ss) { $sTot += count($ss); foreach ($ss as $n => $v) { if (!isset($guide[$ws][$n])) { $orphan[] = "المخزن {$ws}#{$n}"; } } }
printf("  مجموعاتُ الدليل %d · المخزن %d\n", $gTot, $sTot);
if ($orphan) {
    echo "⛔ **لا تُكتب** — موضعٌ بلا مقابلٍ في الطرفِ الآخر (" . count($orphan) . "):\n";
    foreach (array_slice($orphan, 0, 10) as $o) { echo "     · {$o}\n"; }
    exit(1);
}

/* ── ④ الكتابةُ: الاسمُ وحدَه، بالموضع ── */
$log = $conn->prepare("INSERT INTO govui_label_log
    (target_id, store, store_key, old_label, new_label, source_ref, reason) VALUES (?,?,?,?,?,?,?)");
if (!$log) { exit("⛔ prepare: {$conn->error}\n"); }
$upd = $conn->prepare("UPDATE nav_lifecycle_groups SET label_ar = ? WHERE id = ?");
$n = 0;
foreach ($guide as $ws => $gs) {
    foreach ($gs as $pos => $g) {
        list($new, $sheet, $row) = $g;
        $cur = $store[$ws][$pos];
        if (rpr02a_nz($cur['label_ar']) === rpr02a_nz($new)) { continue; }
        $id = (int) $cur['id'];
        $upd->bind_param('si', $new, $id);
        if (!$upd->execute()) { exit("⛔ group {$id}: {$conn->error}\n"); }
        $tid = 'GRP-' . $id;
        $src = $sheet . '·صف ' . $row;
        $store2 = 'nav_lifecycle_groups.label_ar';
        $key = (string) $id;
        $why = 'GOV_UI_RELABEL §7 — مطابقةٌ بالموضع (' . $ws . '#' . $pos . ') لا بالاسم · الدليل 60a8e3b2';
        $log->bind_param('sssssss', $tid, $store2, $key, $cur['label_ar'], $new, $src, $why);
        $log->execute();
        printf("  ✔ %-8s #%-2d «%s» ⇐ «%s»\n", $ws, $pos, mb_substr($cur['label_ar'], 0, 30), mb_substr($new, 0, 34));
        $n++;
    }
}
echo "أسماءُ مجموعاتٍ حُدِّثت: {$n}\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
