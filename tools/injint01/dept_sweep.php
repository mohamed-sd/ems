<?php
/**
 * tools/injint01/dept_sweep.php — تدقيقُ الإداراتِ كلِّها بأربعةِ أعمدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما وُجد في المبيعاتِ نمطٌ لا حالة** — فيُعمَّم القياسُ لا الحكم. وأربعةُ
 *   أعمدةٍ لكلِّ إدارة: جردٌ خطأ · سجلٌّ بلا موضع · بطاقةٌ بمصدرٍ مزدوج · تحذير.
 *
 * ⛔ **و«جردٌ خطأ» يُقاس بردِّ الحارسِ لا بملكيّةِ الوحدة**: سطحٌ مُعلَنٌ في
 *   ملاحةِ إدارةٍ **ويردُّه الحارسُ عن دورِها** ⇒ الجردُ يدّعيه ومالكُه غيرُها.
 *   ولا يُقاس بجدولِ الصلاحيّاتِ الخامِّ — فبينه وبين الحارسِ طبقةُ قوالب.
 *
 * ⛔ **و«مردودٌ» نوعان**: ردٌّ إلى **اللوحةِ** حكمُ حارسٍ، وردٌّ إلى شاشةٍ أخرى
 *   غالبًا **مُعامِلٌ ناقص**. وخلطُهما يُضخِّم الجردَ الخطأ بأسطحٍ سليمة.
 *
 * ⛔ **والبطاقةُ المزدوجةُ تُقاس بجدولِها لا باسمِها**: `ems_w14_guide_rows('t')`
 *   على جدولٍ **بصفرِ صفٍّ** بينما الشاشةُ تكتب في غيرِه ⇒ رسالةُ فراغٍ تُقرأ
 *   نفيًا لما هو موجود.
 *
 * التشغيل:
 *   EMS_QA_PASS='…' php tools/injint01/dept_sweep.php [--only=DEP-02]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8'); mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/tests/dept_suite/engine.php';

$ONLY = '';
foreach (array_slice($argv, 1) as $a) { if (preg_match('/^--only=([\w-]+)$/', $a, $m)) { $ONLY = $m[1]; } }
$PASS = getenv('EMS_QA_PASS');
if (!$PASS) { exit("⛔ عيِّن EMS_QA_PASS في البيئة — ولا يُكتب سرٌّ في ملفّ.\n"); }

$MAP = json_decode((string) @file_get_contents($ROOT . '/docs/injint01/dept_map.json'), true);
if (!$MAP) { exit("⛔ شغِّل tools/injint01/dept_map.php أوّلًا\n"); }

$ctx = ds_ctx(array('base' => 'http://localhost/ems'));
$db  = $ctx['db'];
$q = function ($sql) use ($db) { $r = $db->query($sql); $o = array(); if (!$r) { return $o; } while ($x = $r->fetch_assoc()) { $o[] = $x; } return $o; };
$one = function ($sql) use ($db) { $r = $db->query($sql); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; };

$norm = function ($r) {
    $s = strtolower(ltrim(preg_replace('~^(\.\./)+~', '', (string) $r), '/'));
    $s = preg_replace('~[?#].*$~', '', $s);
    if ($s !== '' && substr($s, -4) !== '.php') { $s .= '.php'; }
    return $s;
};

/* المواضعُ المُعلَنةُ في السجلَّين — لقياسِ «بلا موضع» */
$placed = array();
foreach ($q("SELECT route FROM nav_placements WHERE active=1") as $r) { $placed[$norm($r['route'])] = 1; }
foreach ($q('SELECT route FROM nav_workspace_placements') as $r) { $placed[$norm($r['route'])] = 1; }

/* جداولُ البطاقاتِ الفارغةِ — تُقاس مرّةً */
$emptyTbl = array();
foreach ($q("SELECT TABLE_NAME t FROM information_schema.TABLES
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'") as $r) {
    $emptyTbl[$r['t']] = null;   /* يُملأ عند الحاجةِ فقط */
}

$FAM = array('Warning' => '~\bWarning\s*:~', 'Notice' => '~\bNotice\s*:~', 'Deprecated' => '~\bDeprecated\s*:~',
             'Fatal' => '~\b(Fatal error|Parse error)\s*:~');

$TABLE = array();
foreach ($MAP as $wsId => $d) {
    if ($ONLY && $wsId !== $ONLY) { continue; }
    if (empty($d['user'])) { $TABLE[] = array('ws' => $wsId, 'skip' => 'لا حسابَ حيّ'); continue; }

    /* ① الجردُ: مواضعُ المساحةِ + بنودُ ملاحةِ أدوارِها */
    $inv = array();
    foreach ($q("SELECT route FROM nav_placements WHERE workspace_id='$wsId' AND active=1") as $r) { $inv[$norm($r['route'])]['plc'] = 1; }
    foreach ($q("SELECT route FROM nav_workspace_placements WHERE workspace_id='$wsId'") as $r) { $inv[$norm($r['route'])]['plc'] = 1; }
    $rids = implode(',', array_map('intval', $d['roles']));
    foreach ($q("SELECT DISTINCT route FROM nav_items WHERE role_id IN ($rids) AND active=1") as $r) { $inv[$norm($r['route'])]['nav'] = 1; }
    unset($inv['']);
    ksort($inv);

    /* ② الدخولُ بحسابِ الإدارة */
    list($ok, $why) = ds_login($ctx, $d['user'], $PASS);
    if (!$ok) { $TABLE[] = array('ws' => $wsId, 'skip' => 'تعذّر الدخول: ' . mb_substr($why, 0, 40)); continue; }

    $clean = 0; $warn = 0; $denied = array(); $param = 0; $bad = 0; $unplaced = array(); $cards = array(); $warnList = array();
    foreach (array_keys($inv) as $rt) {
        if (!is_file($ROOT . '/' . $rt)) { continue; }

        /* بلا موضعٍ في سجلَّي المواضع */
        if (!isset($placed[$rt])) { $unplaced[] = $rt; }

        /* بطاقةٌ بمصدرٍ مزدوج */
        $src = (string) @file_get_contents($ROOT . '/' . $rt);
        if (preg_match_all("~ems_w14_guide_rows\(\s*'([a-z0-9_]+)'~i", $src, $mm)) {
            foreach ($mm[1] as $t) {
                if (!array_key_exists($t, $emptyTbl)) { continue; }
                if ($emptyTbl[$t] === null) { $emptyTbl[$t] = (int) $one("SELECT COUNT(*) FROM `$t`"); }
                if ($emptyTbl[$t] === 0) { $cards[] = basename($rt) . " ⇐ `$t`"; }
            }
        }

        /* التصييرُ الحيّ */
        list($code, $hh, $body) = ds_req($ctx['base'] . '/' . $rt, $ctx);
        $loc = ''; if (preg_match('~Location:\s*(\S+)~i', (string) $hh, $lm)) { $loc = basename(parse_url($lm[1], PHP_URL_PATH)); }
        if ($code === 302 || $code === 301) {
            /* ⛔ الردُّ إلى اللوحةِ حكمُ حارس · وإلى شاشةٍ أخرى مُعامِلٌ ناقص */
            if (stripos($loc, 'dashboard') !== false) { $denied[] = $rt; } else { $param++; }
            continue;
        }
        if ($code !== 200) { $bad++; continue; }
        $plain = strip_tags((string) $body);
        $hit = array();
        foreach ($FAM as $f => $re) { if (preg_match($re, $plain)) { $hit[] = $f; } }
        if ($hit) { $warn++; $warnList[] = basename($rt) . ' [' . implode('·', $hit) . ']'; } else { $clean++; }
    }

    $TABLE[] = array('ws' => $wsId, 'roles' => count($d['roles']), 'user' => $d['user'],
        'surfaces' => $clean + $warn + count($denied) + $param + $bad,
        'clean' => $clean, 'warn' => $warn, 'warnList' => $warnList,
        'wrongInv' => $denied, 'param' => $param, 'http' => $bad,
        'unplaced' => $unplaced, 'cards' => array_values(array_unique($cards)));
    printf("  ✔ %-8s أسطح=%-4d نظيف=%-4d تحذير=%-3d جردٌ خطأ=%-3d بلا موضع=%-3d بطاقات=%d\n",
        $wsId, $clean + $warn + count($denied) + $param + $bad, $clean, $warn, count($denied), count($unplaced), count($cards));
}

file_put_contents($ROOT . '/docs/injint01/dept_sweep.json', json_encode($TABLE, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\n◆ النتيجة: docs/injint01/dept_sweep.json\n";
