<?php
/**
 * tools/ctl_sm_bind_requirements.php — ربطُ المتطلبِ بآلةِ حالتِه بمرجعٍ صريح
 * ═══════════════════════════════════════════════════════════════════════════
 * **أمرُ الاستئنافِ الثالث**: «أكمل ربطَ State Models من المرجعِ الحاكم …
 * ولا تربط نموذجَ حالةٍ بالاسمِ فقط دون مرجعٍ صريح — وطبِّقه بأثرٍ رجعيٍّ
 * على المعاملاتِ المبنيّةِ أيضًا».
 *
 * ◆ أربعُ قنواتٍ **كلُّها اقتباسُ مرجعٍ لا مطابقةُ اسم** — ولا خامسةَ:
 *   R1 `STATES_SRCREF`  — صفُّ جدولِ حالاتِ موجةٍ `src_ref` فيه **معرِّفُ
 *      المتطلبِ نصًّا** (قِيس: w8 وحدَها 58 صفًّا).
 *   R2 `BUILT_REGISTRY` — متطلبٌ سطحُه `MATCHED` في الكونِ وسجلُّ الشاشاتِ
 *      يحمل لسطحِه `state_model_ref` مربوطًا بقاعدةِ الجسرِ المثبتةِ —
 *      **الأثرُ الرجعيُّ على المبنيّ**: يُنسخ المرجعُ نفسُه بسلسلتِه.
 *   R3 `STATES_COLUMNS` — معرِّفُ المتطلبِ في أعمدةِ الشرطِ/المستندِ/البوّابةِ
 *      لصفِّ حالاتٍ (قِيس: w7 ستّةُ صفوف).
 *   R4 `DOC_BLOCK`      — كتلةُ آلةٍ في `W*_STATE_MACHINES.md` (رأسُها
 *      `## … `جدول``) **تذكر معرِّفَ المتطلبِ داخلَها**.
 * ◆ وما لا مرجعَ صريحًا له يبقى `SM_MODEL_UNBOUND` **باسمِه** — تأليفُ
 *   العزوِ حكمُ أعمالٍ لا يُخمَّن (نظيرُ `AUTHORING_BACKLOG`).
 * ◆ تنازعُ قناتَين على متطلبٍ بمرجعَين مختلفَين ⇒ لا يُكتب ويُعلَن
 *   `CONFLICT` — فمرجعان متعارضان ليسا مرجعًا.
 *
 * التشغيل: php tools/ctl_sm_bind_requirements.php [--apply]
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
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$snap = '';
$r = @$conn->query("SELECT snapshot_id FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $snap = (string) $x[0]; }

$REQRE = '(ACC|TRS|HR|GOV|RSK|OPS|SITE|PRC|WH|WRK|MNT|TRP|FIN|FLEET|SUP|SAL|CEO|VP|IAF|TKT|MY)-[0-9]{2}';

/* المعاملاتُ المستهدفةُ بلا ربط */
$tx = array();
$r = $conn->query("SELECT requirement_id FROM repair01_requirements
                    WHERE requirement_type = 'TRANSACTION' AND (sm_model_ref IS NULL OR sm_model_ref = '')");
while ($x = $r->fetch_row()) { $tx[$x[0]] = true; }

/** ترشيحُ ربطٍ: req ⇒ [ref, witness, channel] — والتنازعُ يُسجَّل.
 *  ⛔ **مخزنُ الموجةِ ووثيقتُها وجهان لنموذجٍ واحدٍ** (قِيس: `W8_STATES#claims`
 *  و`W08_STATE_MACHINES#claims` نزاعًا زائفًا ×8) — فمفتاحُ الهُويّةِ الكيانُ
 *  ورقمُ الموجةِ مُطبَّعًا، والمخزنُ (`_STATES`) يُفضَّل مرجعًا لأنّه المقيس. */
$prop = array(); $conflict = array();
$smKey = function ($ref) {
    if (!preg_match('~^W0?(\d+)_[A-Z_]+#(.+)$~', $ref, $m)) { return $ref; }
    return 'W' . $m[1] . '#' . $m[2];
};
$offer = function ($req, $ref, $wit, $ch) use (&$prop, &$conflict, $smKey) {
    if (isset($conflict[$req])) { return; }
    if (isset($prop[$req])) {
        if ($smKey($prop[$req][0]) !== $smKey($ref)) {
            $conflict[$req] = $prop[$req][0] . ' ↯ ' . $ref;
            unset($prop[$req]);
        } elseif (strpos($prop[$req][0], '_STATES#') === false && strpos($ref, '_STATES#') !== false) {
            $prop[$req] = array($ref, $wit, $ch); /* المخزنُ يُفضَّل على الوثيقة */
        }
        return;
    }
    $prop[$req] = array($ref, $wit, $ch);
};

/* ═══ R1 + R3 — جداولُ حالاتِ الموجات ═══════════════════════════════════ */
foreach (array('w6', 'w7', 'w8', 'w9', 'w10', 'w11', 'w12', 'w13', 'w14', 'w15') as $w) {
    $t = 'repair01_' . $w . '_states';
    $q = @$conn->query("SELECT entity, src_ref,
                               CONCAT_WS(' · ', precondition, official_doc, approval_gate, reopen_rule, correct_rule) other_cols
                          FROM `$t`");
    if (!$q) { continue; }
    while ($z = $q->fetch_assoc()) {
        $ent = strtolower(trim((string) $z['entity']));
        if ($ent === '') { continue; }
        $ref = strtoupper($w) . '_STATES#' . $ent;
        if (preg_match_all('~\b' . $REQRE . '\b~', (string) $z['src_ref'], $m0, PREG_PATTERN_ORDER)) {
            foreach ($m0[0] as $req) {
                if (!isset($tx[$req])) { continue; }
                $offer($req, $ref, 'R1: صفُّ `' . $t . '` (' . $ent . ') يذكر المتطلبَ في src_ref «'
                                  . mb_substr((string) $z['src_ref'], 0, 60) . '»', 'STATES_SRCREF');
            }
        }
        if (preg_match_all('~\b' . $REQRE . '\b~', (string) $z['other_cols'], $m1, PREG_PATTERN_ORDER)) {
            foreach ($m1[0] as $req) {
                if (!isset($tx[$req])) { continue; }
                $offer($req, $ref, 'R3: صفُّ `' . $t . '` (' . $ent . ') يذكر المتطلبَ في أعمدةِ شرطِه/مستندِه', 'STATES_COLUMNS');
            }
        }
    }
}

/* ═══ R4 — كتلُ وثائقِ الآلات ═══════════════════════════════════════════ */
foreach (glob($ROOT . '/docs/REPAIR01_20260823/plan/W*_STATE_MACHINES.md') as $f) {
    $w = strtoupper(substr(basename($f), 0, 3));
    $src = (string) file_get_contents($f);
    foreach (preg_split('~^(?=##\s)~mu', $src) as $blk) {
        if (!preg_match('~^##[^\n]*?`([a-z_][a-z0-9_]{2,})`~u', $blk, $mm)) { continue; }
        $ent = strtolower($mm[1]);
        if (!preg_match_all('~\b' . $REQRE . '\b~', $blk, $m2, PREG_PATTERN_ORDER)) { continue; }
        foreach (array_unique($m2[0]) as $req) {
            if (!isset($tx[$req])) { continue; }
            $offer($req, $w . '_STATE_MACHINES#' . $ent,
                   'R4: كتلةُ الآلةِ `' . $ent . '` في ' . basename($f) . ' تذكر المتطلبَ داخلَها', 'DOC_BLOCK');
        }
    }
}

/* ═══ R7 — مقيسٌ من شيفرةِ السطحِ المطابق ═══════════════════════════════
   السطحُ الذي حكم الكونُ أنّه هو المتطلبَ **يتعامل مع جدولِه فعلًا** —
   فتُعَدُّ قراءاتُه وكتاباتُه على جداولِ المستأجِرِ ويُجسَر الغالبُ لآلتِه
   بقواعدِ الجسرِ الثلاثِ المثبتةِ. وحارسان فوق العدّ:
   ① عتبةُ وزنٍ (≥3 — قراءةُ بوّابةٍ صريحةٌ لا ذكرٌ عابر) وغلبةٌ مضاعفةٌ
     على الوصيف، ② **حقُّ نقضٍ عائليٌّ معلَن**: جدولُ عائلةٍ أجنبيّةٍ عن
     بادئةِ المتطلبِ إصابةُ بحثٍ عرَضيّةٌ تُنقَض (قِيس: شاشةُ الموظفين
     تقرأ الموردين قراءةَ تحقّقٍ فكاد HR-07 يُعزى لآلةِ الموردين). */
$models = array();
foreach (array('w6', 'w7', 'w8', 'w9', 'w10', 'w11', 'w12', 'w13', 'w14', 'w15') as $w0) {
    $q0 = @$conn->query("SELECT DISTINCT entity FROM repair01_{$w0}_states");
    while ($q0 && ($z0 = $q0->fetch_row())) { $models[strtolower(trim((string) $z0[0]))] = strtoupper($w0); }
}
$FAMOK = array(
 'suppliers' => array('SUP', 'PRC'), 'supplier_' => array('SUP'), 'settlements' => array('SUP', 'ACC', 'TRS'),
 'contracts' => array('SAL', 'CEO', 'VP'), 'opportunities' => array('SAL'), 'quotations' => array('SAL'),
 'claims' => array('SAL', 'ACC'), 'mnt_' => array('MNT', 'FLEET'), 'trp_' => array('TRP'),
 'transfer_' => array('TRP'), 'tre_' => array('TRS'), 'fin_' => array('ACC', 'TRS', 'FIN'),
 'acc_' => array('ACC'), 'bank_' => array('TRS', 'ACC'), 'proc_' => array('PRC', 'WH'),
 'hr_' => array('HR'), 'tkt_' => array('TKT'), 'tickets' => array('TKT'), 'gov_' => array('GOV'),
 'exec_' => array('CEO', 'VP'), 'site_' => array('SITE', 'OPS'), 'unit_' => array('SITE', 'OPS'),
 'ops_' => array('OPS', 'SITE'), 'daily_' => array('OPS'), 'wf_' => array('WRK'),
 'asset_' => array('FLEET', 'MNT'), 'ui_' => array(),
);
$famOk = function ($ent, $req) use ($FAMOK) {
    $pfx = strtoupper(substr($req, 0, strpos($req, '-')));
    foreach ($FAMOK as $tp => $fams) {
        if (strpos($ent, $tp) === 0) { return in_array($pfx, $fams, true); }
    }
    return true; /* جدولٌ خارجَ الخريطةِ لا يُنقَض — يبقى لحارسِ الوزن */
};
$smb = function ($t) use ($models) {
    $t = strtolower(trim((string) $t)); if ($t === '') { return null; }
    if (isset($models[$t])) { return $t; }
    if (substr($t, -2) === 'es' && isset($models[substr($t, 0, -2)])) { return substr($t, 0, -2); }
    if (substr($t, -1) === 's' && isset($models[substr($t, 0, -1)])) { return substr($t, 0, -1); }
    if (isset($models[$t . 's'])) { return $t . 's'; }
    return null;
};
$r = $conn->query("SELECT q.requirement_id, g.route
                     FROM repair01_requirements q
                     JOIN repair01_target_universe u ON u.requirement_id = q.requirement_id AND u.verdict IN ('MATCHED','MERGED_INTO')
                     JOIN repair01_screen_registry g ON g.screen_id = u.screen_id
                    WHERE q.requirement_type = 'TRANSACTION' AND (q.sm_model_ref IS NULL OR q.sm_model_ref = '')
                      AND g.route <> ''");
$codeHits = array(); $codeAmb = array();
while ($x = $r->fetch_assoc()) {
    $f = $ROOT . '/' . $x['route'];
    if (!is_file($f)) { continue; }
    $src = (string) file_get_contents($f);
    $tabs = array();
    if (preg_match_all("~->select\\(\\s*'([a-z_][a-z0-9_]+)'~", $src, $m)) {
        foreach ($m[1] as $t) { $tabs[$t] = (isset($tabs[$t]) ? $tabs[$t] : 0) + 3; }
    }
    if (preg_match_all('~(?:FROM|INTO|UPDATE)\s+`?([a-z_][a-z0-9_]{3,})`?~i', $src, $m)) {
        foreach ($m[1] as $t) { $t = strtolower($t); $tabs[$t] = (isset($tabs[$t]) ? $tabs[$t] : 0) + 1; }
    }
    $req = (string) $x['requirement_id'];
    $hits = array();
    foreach ($tabs as $t => $n) {
        $mk = $smb($t);
        if ($mk === null) { continue; }
        if (!$famOk($mk, $req)) { continue; }
        $hits[$mk] = (isset($hits[$mk]) ? $hits[$mk] : 0) + $n;
    }
    if (!$hits) { continue; }
    arsort($hits); $keys = array_keys($hits);
    if ($hits[$keys[0]] < 3) { continue; }
    if (count($hits) > 1 && $hits[$keys[0]] < 2 * $hits[$keys[1]]) { $codeAmb[$req] = 1; continue; }
    if (isset($codeHits[$req]) && $codeHits[$req][0] !== $keys[0]) { $codeAmb[$req] = 1; unset($codeHits[$req]); continue; }
    if (!isset($codeHits[$req])) { $codeHits[$req] = array($keys[0], $hits[$keys[0]], (string) $x['route']); }
}
foreach ($codeHits as $req => $h) {
    if (isset($codeAmb[$req])) { continue; }
    $offer($req, strtoupper($models[$h[0]]) . '_STATES#' . $h[0],
           'R7: قِيس من شيفرةِ السطحِ المطابقِ ' . $h[2] . ' — تعاملُه الغالبُ (وزن ' . $h[1] . ') على جدولِ `'
           . $h[0] . '` المجسورِ لآلتِه، بعتبةِ وزنٍ وحقِّ نقضٍ عائليّ', 'SCREEN_CODE');
}

/* ═══ R6 — دفترُ العزوِ المؤلَّفُ الملتزَم ═══════════════════════════════
   `plan/SM_ATTRIBUTION.md` — تأليفُ عزوٍ صفًّا صفًّا بنصَّي الطرفَين حيث
   موضوعُ الآلةِ هو الحبّةُ عينُها أو ابنُها المباشر، ملتزَمٌ للمراجعةِ
   والنقض — فهو المرجعُ الصريحُ لغيرِ المبنيّ. */
$attr = (string) @file_get_contents($ROOT . '/docs/REPAIR01_20260823/plan/SM_ATTRIBUTION.md');
if ($attr !== '' && preg_match_all('~^\|\s*(' . $REQRE . ')\s*\|[^|]*\|\s*`([A-Z0-9_]+#[a-z0-9_]+)`~mu', $attr, $ma, PREG_SET_ORDER)) {
    foreach ($ma as $row) {
        $req = $row[1];
        if (!isset($tx[$req])) { continue; }
        $offer($req, $row[count($row) - 1],
               'R6: دفترُ العزوِ المؤلَّفُ SM_ATTRIBUTION.md — صفُّ ' . $req . ' بعلّتِه البيِّنةِ ونصَّي طرفَيه', 'AUTHORED_DOC');
    }
}

/* ═══ R2 — الأثرُ الرجعيُّ عبر سجلِّ الشاشاتِ للمبنيّ ═══════════════════ */
$r = $conn->query("SELECT u.requirement_id, u.screen_id, g.route, g.state_model_ref
                     FROM repair01_target_universe u
                     JOIN repair01_screen_registry g ON g.screen_id = u.screen_id
                    WHERE u.verdict IN ('MATCHED','MERGED_INTO') AND u.requirement_id <> ''
                      AND g.state_model_ref <> ''");
while ($x = $r->fetch_assoc()) {
    $req = (string) $x['requirement_id'];
    if (!isset($tx[$req])) { continue; }
    $offer($req, (string) $x['state_model_ref'],
           'R2: سطحُه المبنيُّ ' . $x['screen_id'] . ' (' . $x['route'] . ') مربوطٌ في سجلِّ الشاشاتِ بقاعدةِ الجسرِ المثبتة',
           'BUILT_REGISTRY');
}

$byCh = array();
foreach ($prop as $req => $p0) { $byCh[$p0[2]] = 1 + (isset($byCh[$p0[2]]) ? $byCh[$p0[2]] : 0); }
printf("\n═══ ربطُ المعاملاتِ بآلاتِها — مرجعًا صريحًا لا اسمًا ═══\n");
printf("  معاملاتٌ بلا ربط: **%d** · مُرشَّحٌ ربطُها: **%d** · تنازعٌ: **%d**\n\n",
       count($tx), count($prop), count($conflict));
foreach ($byCh as $ch => $n0) { printf("     %-16s %d\n", $ch, $n0); }
echo "\n";
foreach ($prop as $req => $p0) { printf("  ✔ %-10s ⇒ %-38s %s\n", $req, $p0[0], mb_substr($p0[1], 0, 70)); }
foreach ($conflict as $req => $c0) { printf("  ⛔ %-10s تنازعُ مرجعَين: %s — لا يُكتب\n", $req, $c0); }

if (!$APPLY) { echo "\n⛔ معاينةٌ — التطبيقُ بـ--apply\n"; exit(0); }
$n = 0;
foreach ($prop as $req => $p0) {
    $ok = $conn->query("UPDATE repair01_requirements
          SET sm_model_ref = '" . $e($p0[0]) . "', sm_witness = '" . $e($p0[1] . ' · لقطة ' . $snap) . "'
        WHERE requirement_id = '" . $e($req) . "' AND (sm_model_ref IS NULL OR sm_model_ref = '')");
    if (!$ok) { exit("✘ $req: {$conn->error}\n"); }
    $n += $conn->affected_rows;
}
printf("\n✔ رُبط **%d** متطلبَ معاملةٍ بمرجعٍ صريحٍ وشاهدِه · وبقي بلا نموذجٍ (يُؤلَّف عزوُه لا يُخمَّن): %d\n",
       $n, count($tx) - $n);
