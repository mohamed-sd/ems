<?php
/**
 * tools/navarch/silent_drop_scan.php — السقوطُ الصامت: هدفُ دليلٍ مبنيٌّ بلا موضعٍ حاكم
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العطبُ المكتشَفُ بسؤالِ المالك: «جهات اتصال العملاء» (`SAL-02` · `SCR-0034`)
 *   بندٌ ثانٍ في ورقةِ `DEP-01` · حالتُه `EVIDENCE_CLOSED` · وملفُّها يعمل —
 *   **وصفرُ موضعٍ في `nav_workspace_placements`**. فبعد قلبِ المُصيِّر سرت قاعدةُ
 *   §22 (`No Placement = No Sidebar Render`) فاختفت من الشاشة.
 *
 * ◆ **ولماذا لم يمسكه `TARGET_NAV_RECALL`**: مقامُه **مواضعُ الدليلِ المسجَّلة**
 *   لا **أهدافُ الدليلِ نفسِها** — فهدفٌ لم يُنشأ له موضعٌ قطُّ يسقط من البسطِ
 *   والمقامِ معًا فتبقى النسبةُ 100٪ [[measure-blind-spots]].
 *
 * ◆ **والمقامُ هنا ورقةُ الدليلِ حرفًا**: كلُّ بطاقةِ شاشةٍ غيرِ توثيقيّةٍ في
 *   `01 · الدليل المعماري.xlsx` (ومساحتا القيادةِ من `02`) — ثمَّ يُسأل عن كلٍّ:
 *     ① أمبنيّةٌ؟ (‏`repair01_requirements.amd01_state` ≠ `NOT_IMPLEMENTED`)
 *     ② أَلها موضعٌ نشطٌ في `nav_workspace_placements`؟
 *   ⇒ **مبنيّةٌ وبلا موضعٍ = سقوطٌ صامت**.
 *
 * ◆ **والمطابقةُ ثلاثيّةٌ لا اسميّة**: بالاسمِ المطبَّعِ · ثمَّ بمعرِّفِ الشاشةِ
 *   من السجلِّ · ثمَّ بالمسار — فالاسمُ المطابقُ حرفًا قد يكون الشاشةَ الخطأ
 *   [[repair01-ops-sidebar-guide11]]، والاسمُ المعتمَدُ قد يغاير اسمَ الدليل.
 *
 * ◆ ⛔ **ويُفرَّق المصنَّفُ عن الساقط**: هدفٌ حكمُه `TAB_CHILD` أو `DIRECT_ONLY`
 *   أو `PROJECTION` **لا يُنتظَر له بندُ قائمةٍ أصلًا** (§9) — فيُعَدُّ ولا يُدان.
 *
 * التشغيل: php tools/navarch/silent_drop_scan.php
 *   ⇒ docs/REPAIR01_20260823/navarch/SILENT_DROP_SCAN.md + .json
 */

error_reporting(E_ALL & ~E_DEPRECATED);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/tools/lib/rpr02a_guide.php';

ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$nz = 'rpr02a_nz';
$rt = function ($s) {
    $s = preg_replace('~^(\.\./)+~', '', (string) $s);
    $s = preg_replace('~[?#].*$~', '', $s);
    return strtolower(trim(preg_replace('~\.php$~i', '', $s), '/'));
};

/* ═══ ① أهدافُ الدليلِ — المقامُ الحاكم ═══ */
$targets = array();          /* [code, idx, group, name] */
foreach (rpr02a_read_cards($ROOT . '/docs/REPAIR01_20260823/01 · الدليل المعماري.xlsx') as $c) {
    if (rpr02a_is_doc($c)) { continue; }
    $targets[] = array($c['code'], $c['idx'], $c['group'], $c['name']);
}
$EXMAP = array('DEP-01' => 'EX-CEO', 'DEP-02' => 'EX-DVP');
foreach (rpr02a_read_cards($ROOT . '/docs/REPAIR01_20260823/02 · القيادة.xlsx') as $c) {
    if (rpr02a_is_doc($c) || !isset($EXMAP[$c['code']])) { continue; }
    $targets[] = array($EXMAP[$c['code']], $c['idx'], $c['group'], $c['name']);
}

/* ═══ ② حالةُ البناءِ من الدفتر ═══ */
$built = array();
$r = $conn->query('SELECT surface, amd01_state FROM repair01_requirements WHERE surface IS NOT NULL AND surface <> ""');
while ($x = $r->fetch_assoc()) {
    $k = $nz($x['surface']);
    if (!isset($built[$k])) { $built[$k] = $x['amd01_state']; }
}

/* ═══ ③ سجلُّ الشاشاتِ — الاسمُ ⇒ معرِّفٌ ومسار ═══ */
$regById = array(); $regByName = array();
$r = $conn->query('SELECT screen_id, canonical_label_ar, screen_file, owner_code FROM repair01_screen_registry');
while ($x = $r->fetch_assoc()) {
    $regById[$x['screen_id']] = $x;
    $n = $nz($x['canonical_label_ar']);
    if ($n !== '' && !isset($regByName[$n])) { $regByName[$n] = $x; }
}

/* ═══ ④ المواضعُ النشطةُ — بثلاثةِ مفاتيح ═══ */
$plByName = array(); $plByScreen = array(); $plByRoute = array(); $plType = array();
$r = $conn->query('SELECT workspace_id, screen_id, route, canonical_label, placement_type, source_ref
                     FROM nav_workspace_placements WHERE status = "ACTIVE"');
while ($x = $r->fetch_assoc()) {
    $ws = (string) $x['workspace_id'];
    if ((string) $x['canonical_label'] !== '') { $plByName[$ws . '|' . $nz($x['canonical_label'])] = $x; }
    if ((string) $x['screen_id'] !== '') { $plByScreen[$ws . '|' . $x['screen_id']] = $x; }
    if ((string) $x['route'] !== '') { $plByRoute[$ws . '|' . $rt($x['route'])] = $x; }
    /* ومطابقةٌ عبرَ المساحاتِ للاستعارة */
    if ((string) $x['screen_id'] !== '') { $plType['*|' . $x['screen_id']] = $x; }
}

/* ═══ ⑤ الفرز ═══ */
$rows = array(); $byWs = array(); $tot = array('T' => 0, 'NB' => 0, 'OK' => 0, 'DROP' => 0, 'ELSEWHERE' => 0);
foreach ($targets as $t) {
    list($ws, $idx, $grp, $name) = $t;
    $n = $nz($name);
    $tot['T']++;
    if (!isset($byWs[$ws])) { $byWs[$ws] = array('T' => 0, 'NB' => 0, 'OK' => 0, 'DROP' => 0, 'EL' => 0); }
    $byWs[$ws]['T']++;

    $state = isset($built[$n]) ? $built[$n] : 'NO_REQ_ROW';
    if ($state === 'NOT_IMPLEMENTED' || $state === 'NO_REQ_ROW') {
        $tot['NB']++; $byWs[$ws]['NB']++; continue;      /* غيرُ مبنيّ — ليس سقوطًا */
    }

    /* أَلها موضعٌ في مساحتِها؟ */
    $hit = null;
    if (isset($plByName[$ws . '|' . $n])) { $hit = $plByName[$ws . '|' . $n]; }
    if ($hit === null && isset($regByName[$n])) {
        $sid = $regByName[$n]['screen_id']; $rr = $rt($regByName[$n]['screen_file']);
        if (isset($plByScreen[$ws . '|' . $sid])) { $hit = $plByScreen[$ws . '|' . $sid]; }
        elseif (isset($plByRoute[$ws . '|' . $rr])) { $hit = $plByRoute[$ws . '|' . $rr]; }
    }
    if ($hit !== null) { $tot['OK']++; $byWs[$ws]['OK']++; continue; }

    /* موضعٌ في مساحةٍ أخرى؟ — نقلٌ لا سقوط */
    $elsewhere = '';
    if (isset($regByName[$n]) && isset($plType['*|' . $regByName[$n]['screen_id']])) {
        $elsewhere = (string) $plType['*|' . $regByName[$n]['screen_id']]['workspace_id'];
    }
    $sid = isset($regByName[$n]) ? $regByName[$n]['screen_id'] : '—';
    $file = isset($regByName[$n]) ? $regByName[$n]['screen_file'] : '—';
    if ($elsewhere !== '' && $elsewhere !== $ws) {
        $tot['ELSEWHERE']++; $byWs[$ws]['EL']++;
        $rows[] = array($ws, $idx, $grp, $name, $sid, $file, $state, 'MOVED_TO_' . $elsewhere);
        continue;
    }
    $tot['DROP']++; $byWs[$ws]['DROP']++;
    $rows[] = array($ws, $idx, $grp, $name, $sid, $file, $state, 'SILENT_DROP');
}

/* ═══ ⑥ التقرير ═══ */
$snap = trim(shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));
$md  = "# مسحُ السقوطِ الصامت — هدفُ دليلٍ **مبنيٌّ** بلا موضعٍ حاكم\n\n";
$md .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/navarch/silent_drop_scan.php` @ `" . $snap . "` · " . date('Y-m-d H:i') . "\n";
$md .= "> **المقامُ ورقةُ الدليلِ حرفًا** (‏`01` + مساحتا القيادةِ من `02`) — لا مواضعُ مسجَّلة.\n";
$md .= "> **والمطابقةُ ثلاثيّة**: الاسمُ المطبَّع ⇒ معرِّفُ الشاشة ⇒ المسار.\n\n";
$md .= "## ⓪ الخلاصة\n\n| الطبقة | العدد |\n|---|---:|\n";
$md .= '| أهدافُ الدليلِ المقيسة | **' . $tot['T'] . "** |\n";
$md .= '| ◇ غيرُ مبنيّةٍ (لا تُدان) | ' . $tot['NB'] . " |\n";
$md .= '| ✔ مبنيّةٌ ولها موضعٌ في مساحتِها | **' . $tot['OK'] . "** |\n";
$md .= '| ◆ مبنيّةٌ وموضعُها في مساحةٍ أخرى | ' . $tot['ELSEWHERE'] . " |\n";
$md .= '| ⛔ **مبنيّةٌ وبلا موضعٍ إطلاقًا — سقوطٌ صامت** | **' . $tot['DROP'] . "** |\n\n";

$md .= "## ① السقوطُ الصامتُ بالمساحة\n\n";
$md .= "| المساحة | أهدافُ الدليل | غيرُ مبنيّ | ✔ لها موضع | ◆ منقولة | ⛔ **ساقطةٌ صامتًا** |\n";
$md .= "|---|---:|---:|---:|---:|---:|\n";
ksort($byWs);
foreach ($byWs as $ws => $s) {
    $md .= '| `' . $ws . '` | ' . $s['T'] . ' | ' . $s['NB'] . ' | ' . $s['OK'] . ' | ' . $s['EL']
         . ' | ' . ($s['DROP'] ? '**' . $s['DROP'] . '**' : '0') . " |\n";
}
$md .= '| **الإجمالي** | **' . $tot['T'] . '** | ' . $tot['NB'] . ' | ' . $tot['OK'] . ' | '
     . $tot['ELSEWHERE'] . ' | **' . $tot['DROP'] . "** |\n\n";

if ($rows) {
    $md .= "## ② التفصيل — كلُّ هدفٍ باسمِه\n\n";
    $md .= "| المساحة | # | المجموعة | الشاشة | Screen ID | الملف | حالةُ البناء | الحكم |\n";
    $md .= "|---|---:|---|---|---|---|---|---|\n";
    foreach ($rows as $x) {
        $md .= '| `' . $x[0] . '` | ' . $x[1] . ' | ' . mb_substr($x[2], 0, 26) . ' | **' . $x[3]
             . '** | `' . $x[4] . '` | `' . $x[5] . '` | ' . $x[6] . ' | ' . $x[7] . " |\n";
    }
}
$md .= "\n⛔ **ولماذا لم يمسكه `TARGET_NAV_RECALL`**: مقامُه مواضعُ الدليلِ المسجَّلةُ\n";
$md .= "لا أهدافُ الدليلِ نفسِها — فهدفٌ لم يُنشأ له موضعٌ قطُّ يسقط من البسطِ\n";
$md .= "والمقامِ معًا فتبقى النسبةُ 100٪. ⇒ **مقياسٌ جديدٌ لازم**: `TARGET_WITHOUT_PLACEMENT`.\n";

$dir = $ROOT . '/docs/REPAIR01_20260823/navarch';
file_put_contents($dir . '/SILENT_DROP_SCAN.md', $md);
file_put_contents($dir . '/SILENT_DROP_SCAN.json', json_encode(
    array('snapshot' => $snap, 'totals' => $tot, 'by_workspace' => $byWs, 'rows' => $rows),
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

printf("أهداف %d · غيرُ مبنيّ %d · لها موضع %d · منقولة %d · ⛔ ساقطةٌ صامتًا %d\n=> %s\n",
    $tot['T'], $tot['NB'], $tot['OK'], $tot['ELSEWHERE'], $tot['DROP'], $dir . '/SILENT_DROP_SCAN.md');
