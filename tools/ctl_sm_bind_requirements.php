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
