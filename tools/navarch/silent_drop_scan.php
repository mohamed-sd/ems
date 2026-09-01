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
 * ═══ ⚠ عطبانِ في هذا الماسحِ نفسِه — قِيسا وعولجا (`SILENT_DROP_FIX`) ═══════
 * ① **المطابقةُ الاسميّةُ كانت تحمل حاشيةَ الانطباق**: ورقةُ الدليلِ تسمّي
 *   بندًا «سجل السياسات — بحسب انطباق الشركة»، و`repair01_screen_registry`
 *   يحمل الاسمَ المعياريَّ «سجل السياسات». **والحاشيةُ ليست اسمًا**
 *   [[govui-round-closure]] — فالمقارنةُ بها **تُعمي عن سجلٍّ وموضعٍ قائمَين**.
 *   ⇒ **قِيس أثرُه: 34 من 58 «سقوطًا» كانت مسجَّلةً وموضوعةً سلفًا** — أخضرُ
 *   مقلوب: حمرةٌ كاذبةٌ لا خضرةٌ كاذبة، وهي كذبٌ على المقياسِ سواءً بسواء.
 * ② **قاعدةُ §9 كانت مكتوبةً في الرأسِ وغيرَ منفَّذةٍ في الشيفرة**: «هدفٌ حكمُه
 *   `TAB_CHILD` أو `DIRECT_ONLY` أو `PROJECTION` لا يُنتظَر له بندُ قائمةٍ
 *   أصلًا فيُعَدُّ ولا يُدان» — والشيفرةُ كانت تُدينه. **و11 من 58 كذلك**:
 *   أهدافٌ يخدمها سطحٌ **موضوعٌ فعلًا** (`fleet/asset_intake` يخدم البنودَ
 *   4 و5 و11 معًا)، **والمفتاحُ الفريدُ `uq_ws_route` يمنع موضعًا ثانيًا لمسارٍ
 *   واحدٍ في مساحةٍ واحدة** — فإدانتُها إدانةٌ لِما يستحيل بناءً.
 *
 * ⭐ **والمطابقةُ صارت بالجسرِ الحاكمِ لا بالاسم**: `nav_placements` ورقةُ
 *   الدليلِ المستوعَبةُ — **413 صفًّا لـ413 هدفًا، واحدًا لواحد** — ومفتاحُها
 *   `target_ref = code·idx·الاسم`. ⇒ **الهدفُ يُجسَر ببندِه لا بنصِّ اسمِه**
 *   [[nav-label-four-source-precedence]]، ثمَّ يُسأل: أَلمسارِ ورقتِه موضعٌ حاكم؟
 *
 * ◆ **ومَن يملك المسارَ حين يتقاسمه هدفان**: القاعدةُ نفسُها التي يستعملها
 *   `classify.php` حرفًا — `MENU_ITEM`/`LANDING_PAGE` يغلب، ثمَّ أصغرُ بند —
 *   فلا يتفرّق قارئان على سؤالٍ واحد [[counter-parity-two-readers]].
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
/** ⭐ حاشيةُ الانطباقِ ليست اسمًا — تُنزَع قبلَ أيِّ مطابقةٍ اسميّة */
$strip = function ($s) use ($nz) {
    return $nz(preg_replace('~\s*[—–-]\s*بحسب\s+انطباق\s+الشرك[ةه]\s*$~u', '', (string) $s));
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

/* ═══ ② حالةُ البناءِ من الدفتر — بالاسمِ الكاملِ ثمَّ بالمجرَّدِ من الحاشية ═══ */
$built = array();
$r = $conn->query('SELECT surface, amd01_state FROM repair01_requirements WHERE surface IS NOT NULL AND surface <> ""');
while ($x = $r->fetch_assoc()) {
    foreach (array($nz($x['surface']), $strip($x['surface'])) as $k) {
        if ($k !== '' && !isset($built[$k])) { $built[$k] = $x['amd01_state']; }
    }
}

/* ═══ ③ سجلُّ الشاشاتِ — الاسمُ ⇒ معرِّفٌ ومسار (‏بالمفتاحَين) ═══ */
$regById = array(); $regByName = array();
$r = $conn->query('SELECT screen_id, canonical_label_ar, screen_file, owner_code FROM repair01_screen_registry');
while ($x = $r->fetch_assoc()) {
    $regById[$x['screen_id']] = $x;
    foreach (array($nz($x['canonical_label_ar']), $strip($x['canonical_label_ar'])) as $n) {
        if ($n !== '' && !isset($regByName[$n])) { $regByName[$n] = $x; }
    }
}

/* ═══ ④ **جسرُ ورقةِ الدليل** — (‏مساحة · بند) ⇒ صفُّ `nav_placements` ═══
   ◆ وواحدٌ لواحدٍ مقيسٌ: 413 صفًّا لـ413 هدفًا. ومن اقتسم مسارًا فمالكُه
     يُحسم بقاعدةِ `classify.php` نفسِها — ولا يتأرجح بين تشغيلَين. */
$leafByIdx = array(); $leafOwner = array();
$r = $conn->query('SELECT id, workspace_id, screen_id, route, target_ref, sort_no,
                          placement_type, group_id
                     FROM nav_placements WHERE active = 1
                    ORDER BY workspace_id, id');
while ($x = $r->fetch_assoc()) {
    $tp = explode('·', (string) $x['target_ref']);
    $x['__idx'] = (count($tp) >= 3) ? (int) trim($tp[1]) : 0;
    $ws = (string) $x['workspace_id'];
    if ($x['__idx'] > 0) { $leafByIdx[$ws . '|' . $x['__idx']] = $x; }
    $k = $rt((string) $x['route']);
    if ($k === '' || strncmp((string) $x['route'], 'GAP:', 4) === 0) { continue; }
    $isMenu = in_array((string) $x['placement_type'], array('MENU_ITEM', 'LANDING_PAGE'), true);
    if (!isset($leafOwner[$ws . '|' . $k])) { $leafOwner[$ws . '|' . $k] = $x; continue; }
    $prev = $leafOwner[$ws . '|' . $k];
    $prevMenu = in_array((string) $prev['placement_type'], array('MENU_ITEM', 'LANDING_PAGE'), true);
    if ($isMenu && !$prevMenu) { $leafOwner[$ws . '|' . $k] = $x; }
    elseif ($isMenu === $prevMenu && $x['__idx'] < (int) $prev['__idx']) { $leafOwner[$ws . '|' . $k] = $x; }
}

/* ═══ ⑤ المواضعُ النشطةُ — بثلاثةِ مفاتيح ═══ */
$plByName = array(); $plByScreen = array(); $plByRoute = array(); $plAnyWs = array();
$r = $conn->query('SELECT workspace_id, screen_id, route, canonical_label, placement_type, source_ref
                     FROM nav_workspace_placements WHERE status = "ACTIVE"');
while ($x = $r->fetch_assoc()) {
    $ws = (string) $x['workspace_id'];
    if ((string) $x['canonical_label'] !== '') {
        $plByName[$ws . '|' . $nz($x['canonical_label'])] = $x;
        $plByName[$ws . '|' . $strip($x['canonical_label'])] = $x;
    }
    if ((string) $x['screen_id'] !== '') { $plByScreen[$ws . '|' . $x['screen_id']] = $x; }
    if ((string) $x['route'] !== '') { $plByRoute[$ws . '|' . $rt($x['route'])] = $x; }
    if ((string) $x['screen_id'] !== '') { $plAnyWs[$x['screen_id']] = $x; }
}

/* ═══ ⑥ الفرز ═══ */
$SERVED_TYPES = array('TAB_CHILD' => 1, 'DIRECT_ONLY' => 1, 'PROJECTION' => 1);
$rows = array(); $byWs = array();
$tot = array('T' => 0, 'NB' => 0, 'OK' => 0, 'DROP' => 0, 'ELSEWHERE' => 0, 'SERVED' => 0);
foreach ($targets as $t) {
    list($ws, $idx, $grp, $name) = $t;
    $n  = $nz($name); $ns = $strip($name);
    $tot['T']++;
    if (!isset($byWs[$ws])) { $byWs[$ws] = array('T' => 0, 'NB' => 0, 'OK' => 0, 'DROP' => 0, 'EL' => 0, 'SV' => 0); }
    $byWs[$ws]['T']++;

    $state = isset($built[$n]) ? $built[$n] : (isset($built[$ns]) ? $built[$ns] : 'NO_REQ_ROW');
    if ($state === 'NOT_IMPLEMENTED' || $state === 'NO_REQ_ROW') {
        $tot['NB']++; $byWs[$ws]['NB']++; continue;      /* غيرُ مبنيّ — ليس سقوطًا */
    }

    /* ⑥-أ **الجسرُ الحاكمُ أوّلًا**: ورقةُ الدليلِ بالبندِ لا بالاسم */
    $leaf = isset($leafByIdx[$ws . '|' . $idx]) ? $leafByIdx[$ws . '|' . $idx] : null;
    $sid  = $leaf && (string) $leaf['screen_id'] !== '' ? (string) $leaf['screen_id']
          : (isset($regByName[$n]) ? $regByName[$n]['screen_id']
            : (isset($regByName[$ns]) ? $regByName[$ns]['screen_id'] : '—'));
    $file = $leaf ? (string) $leaf['route']
          : (isset($regByName[$n]) ? $regByName[$n]['screen_file']
            : (isset($regByName[$ns]) ? $regByName[$ns]['screen_file'] : '—'));

    $hit = null; $why = '';
    if ($leaf !== null) {
        $lk = $rt((string) $leaf['route']);
        if ($lk !== '' && isset($plByRoute[$ws . '|' . $lk])) {
            $owner = isset($leafOwner[$ws . '|' . $lk]) ? $leafOwner[$ws . '|' . $lk] : null;
            $ownsIt = ($owner !== null && (int) $owner['id'] === (int) $leaf['id']);
            if ($ownsIt) { $hit = $plByRoute[$ws . '|' . $lk]; $why = 'OWN_PLACEMENT'; }
            else {
                /* ⭐ §9: هدفٌ يخدمه سطحٌ موضوعٌ — ولا يُنتظَر له بندُ قائمةٍ ثانٍ،
                   و`uq_ws_route` يمنعه بنيويًّا. يُعَدُّ ولا يُدان. */
                $tot['SERVED']++; $byWs[$ws]['SV']++;
                $rows[] = array($ws, $idx, $grp, $name, $sid, $file, $state,
                    'SERVED_BY_' . (string) $owner['screen_id']);
                continue;
            }
        }
    }
    /* ⑥-ب ثمَّ المفاتيحُ الثلاثةُ القديمةُ — للهدفِ الذي لا صفَّ ورقةٍ له */
    if ($hit === null && isset($plByName[$ws . '|' . $n]))  { $hit = $plByName[$ws . '|' . $n]; }
    if ($hit === null && isset($plByName[$ws . '|' . $ns])) { $hit = $plByName[$ws . '|' . $ns]; }
    if ($hit === null && $sid !== '—' && isset($plByScreen[$ws . '|' . $sid])) { $hit = $plByScreen[$ws . '|' . $sid]; }
    if ($hit !== null) { $tot['OK']++; $byWs[$ws]['OK']++; continue; }

    /* موضعٌ في مساحةٍ أخرى؟ — نقلٌ لا سقوط */
    $elsewhere = ($sid !== '—' && isset($plAnyWs[$sid])) ? (string) $plAnyWs[$sid]['workspace_id'] : '';
    if ($elsewhere !== '' && $elsewhere !== $ws) {
        $tot['ELSEWHERE']++; $byWs[$ws]['EL']++;
        $rows[] = array($ws, $idx, $grp, $name, $sid, $file, $state, 'MOVED_TO_' . $elsewhere);
        continue;
    }
    $tot['DROP']++; $byWs[$ws]['DROP']++;
    $rows[] = array($ws, $idx, $grp, $name, $sid, $file, $state, 'SILENT_DROP');
}

/* ═══ ⑦ التقرير ═══ */
$snap = trim(shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));
$md  = "# مسحُ السقوطِ الصامت — هدفُ دليلٍ **مبنيٌّ** بلا موضعٍ حاكم\n\n";
$md .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/navarch/silent_drop_scan.php` @ `" . $snap . "` · " . date('Y-m-d H:i') . "\n";
$md .= "> **المقامُ ورقةُ الدليلِ حرفًا** (‏`01` + مساحتا القيادةِ من `02`) — لا مواضعُ مسجَّلة.\n";
$md .= "> **والمطابقةُ بجسرِ الورقةِ**: (‏مساحة · بند) ⇒ `nav_placements` ⇒ المسار ⇒ الموضعُ الحاكم.\n";
$md .= "> ⭐ **وحاشيةُ الانطباق ليست اسمًا** — تُنزَع قبلَ أيِّ مطابقةٍ اسميّة.\n\n";
$md .= "## ⓪ الخلاصة\n\n| الطبقة | العدد |\n|---|---:|\n";
$md .= '| أهدافُ الدليلِ المقيسة | **' . $tot['T'] . "** |\n";
$md .= '| ◇ غيرُ مبنيّةٍ (لا تُدان) | ' . $tot['NB'] . " |\n";
$md .= '| ✔ مبنيّةٌ ولها موضعٌ في مساحتِها | **' . $tot['OK'] . "** |\n";
$md .= '| ▣ مبنيّةٌ يخدمها سطحٌ موضوعٌ (§9 — تُعَدُّ ولا تُدان) | ' . $tot['SERVED'] . " |\n";
$md .= '| ◆ مبنيّةٌ وموضعُها في مساحةٍ أخرى | ' . $tot['ELSEWHERE'] . " |\n";
$md .= '| ⛔ **مبنيّةٌ وبلا موضعٍ إطلاقًا — سقوطٌ صامت** | **' . $tot['DROP'] . "** |\n\n";

$md .= "## ① السقوطُ الصامتُ بالمساحة\n\n";
$md .= "| المساحة | أهدافُ الدليل | غيرُ مبنيّ | ✔ لها موضع | ▣ مخدومة | ◆ منقولة | ⛔ **ساقطةٌ صامتًا** |\n";
$md .= "|---|---:|---:|---:|---:|---:|---:|\n";
ksort($byWs);
foreach ($byWs as $ws => $s) {
    $md .= '| `' . $ws . '` | ' . $s['T'] . ' | ' . $s['NB'] . ' | ' . $s['OK'] . ' | ' . $s['SV']
         . ' | ' . $s['EL'] . ' | ' . ($s['DROP'] ? '**' . $s['DROP'] . '**' : '0') . " |\n";
}
$md .= '| **الإجمالي** | **' . $tot['T'] . '** | ' . $tot['NB'] . ' | ' . $tot['OK'] . ' | '
     . $tot['SERVED'] . ' | ' . $tot['ELSEWHERE'] . ' | **' . $tot['DROP'] . "** |\n\n";

if ($rows) {
    $md .= "## ② التفصيل — كلُّ هدفٍ باسمِه\n\n";
    $md .= "| المساحة | # | المجموعة | الشاشة | Screen ID | الملف | حالةُ البناء | الحكم |\n";
    $md .= "|---|---:|---|---|---|---|---|---|\n";
    foreach ($rows as $x) {
        $md .= '| `' . $x[0] . '` | ' . $x[1] . ' | ' . mb_substr($x[2], 0, 26) . ' | **' . $x[3]
             . '** | `' . $x[4] . '` | `' . $x[5] . '` | ' . $x[6] . ' | ' . $x[7] . " |\n";
    }
} else {
    $md .= "## ② التفصيل\n\n**ولا صفَّ** — كلُّ هدفٍ مبنيٍّ له موضعٌ حاكمٌ أو سطحٌ موضوعٌ يخدمه.\n";
}
$md .= "\n⛔ **ولماذا لم يمسكه `TARGET_NAV_RECALL`**: مقامُه مواضعُ الدليلِ المسجَّلةُ\n";
$md .= "لا أهدافُ الدليلِ نفسِها — فهدفٌ لم يُنشأ له موضعٌ قطُّ يسقط من البسطِ\n";
$md .= "والمقامِ معًا فتبقى النسبةُ 100٪. ⇒ **و`TARGET_WITHOUT_PLACEMENT` صار في لوحةِ §26.**\n";

$dir = $ROOT . '/docs/REPAIR01_20260823/navarch';
file_put_contents($dir . '/SILENT_DROP_SCAN.md', $md);
file_put_contents($dir . '/SILENT_DROP_SCAN.json', json_encode(
    array('snapshot' => $snap, 'totals' => $tot, 'by_workspace' => $byWs, 'rows' => $rows),
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

printf("أهداف %d · غيرُ مبنيّ %d · لها موضع %d · مخدومة %d · منقولة %d · ⛔ ساقطةٌ صامتًا %d\n=> %s\n",
    $tot['T'], $tot['NB'], $tot['OK'], $tot['SERVED'], $tot['ELSEWHERE'], $tot['DROP'],
    $dir . '/SILENT_DROP_SCAN.md');
