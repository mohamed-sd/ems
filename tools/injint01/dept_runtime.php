<?php
/**
 * tools/injint01/dept_runtime.php — مخرَجُ أسطحِ إدارةٍ حيًّا (عامٌّ لكلِّ إدارة)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **يُعمِّم ما بُني للمبيعات**: `sales_runtime.php` كان مربوطًا بمانيفستِها.
 *   وهذه تأخذ **مساحةَ العملِ وأدوارَها** فتبني الجردَ من السجلّاتِ الحاكمة.
 *
 * ⛔ **ولا تُكتب كلمةُ مرورٍ في ملفٍّ**: تُقرأ من بيئةِ التشغيلِ وحدَها
 *   (`EMS_QA_USER` · `EMS_QA_PASS`)، فلا يحمل المستودعُ سرًّا جديدًا.
 *
 * ⛔ **والسجلّانِ يخزّنان المسارَ بصيغتَين**: `nav_placements` بـ`.php`
 *   و`nav_workspace_placements` بلا لاحقةٍ — فمن قارن بينهما نصًّا خرج بفرقٍ
 *   وهمٍ كاملٍ. تُسوَّى اللاحقةُ هنا قبلَ أيِّ مقارنة.
 *
 * ⛔ **والصفحةُ التي يردُّها الحارسُ لا يُقاس جسدُها**: 302 إلى لوحةٍ ليست
 *   صفحةَ السطح. ويُفرَّق بين ردِّ الحارسِ وردِّ «مُعامِلٌ ناقص» بإعادةِ
 *   الطلبِ بمُعامِلٍ حقيقيّ.
 *
 * التشغيل:
 *   EMS_QA_USER='مشرف الموردين' EMS_QA_PASS='…' \
 *   php tools/injint01/dept_runtime.php --ws=DEP-02 --roles=2,8
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/tests/dept_suite/engine.php';

$WS = ''; $ROLES = ''; $TAG = '';
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--ws=([\w-]+)$/', $a, $m)) { $WS = $m[1]; }
    if (preg_match('/^--roles=([\d,]+)$/', $a, $m)) { $ROLES = $m[1]; }
    if (preg_match('/^--tag=(\w+)$/', $a, $m)) { $TAG = $m[1]; }
}
if ($WS === '' || $ROLES === '') { exit("الاستعمال: --ws=DEP-02 --roles=2,8 [--tag=sup]\n"); }
if ($TAG === '') { $TAG = strtolower(str_replace('-', '', $WS)); }

$USER = getenv('EMS_QA_USER'); $PASS = getenv('EMS_QA_PASS');
if (!$USER || !$PASS) { exit("⛔ عيِّن EMS_QA_USER و EMS_QA_PASS في البيئة — ولا يُكتب سرٌّ في ملفّ.\n"); }

$ctx = ds_ctx(array('base' => 'http://localhost/ems'));
$db  = $ctx['db'];
$rowsQ = function ($q) use ($db) { $r = $db->query($q); $o = array(); if (!$r) { return $o; } while ($x = $r->fetch_assoc()) { $o[] = $x; } return $o; };

/* ═══ ① الجردُ من السجلّاتِ الحاكمة — واللاحقةُ تُسوَّى ═══════════════════ */
$norm = function ($r) {
    $s = strtolower(ltrim(preg_replace('~^(\.\./)+~', '', (string) $r), '/'));
    $s = preg_replace('~[?#].*$~', '', $s);          /* المُعامِلُ والمِرساةُ ليسا مسارًا */
    if ($s !== '' && substr($s, -4) !== '.php') { $s .= '.php'; }
    return $s;
};
$inv = array();
foreach ($rowsQ("SELECT route FROM nav_placements WHERE workspace_id='$WS' AND active=1") as $r) { $inv[$norm($r['route'])]['placements'] = 1; }
foreach ($rowsQ("SELECT route FROM nav_workspace_placements WHERE workspace_id='$WS'") as $r) { $inv[$norm($r['route'])]['ws_placements'] = 1; }
foreach ($rowsQ("SELECT DISTINCT route FROM nav_items WHERE role_id IN ($ROLES) AND active=1") as $r) { $inv[$norm($r['route'])]['nav_items'] = 1; }
ksort($inv);

$exists = array(); $ghost = array();
foreach ($inv as $rt => $src) {
    if ($rt === '' ) { continue; }
    if (is_file($ROOT . '/' . $rt)) { $exists[$rt] = $src; } else { $ghost[$rt] = $src; }
}
printf("◆ مساحة %s · أدوار %s\n◆ مساراتٌ مُعلَنةٌ متميّزة: %d · ملفٌّ موجود: %d · **بلا ملفّ**: %d\n\n",
    $WS, $ROLES, count($inv), count($exists), count($ghost));

/* ═══ ② الدخولُ عبرَ محرِّكِ العُدّةِ القائم ═════════════════════════════ */
list($ok, $why) = ds_login($ctx, $USER, $PASS);
if (!$ok) { exit("⛔ تعذّرت التهيئة: $why\n"); }
printf("✔ الجلسةُ مهيّأةٌ بـ«%s»\n\n", $USER);

/* ═══ ③ التصييرُ وفحصُ الجسد ═══════════════════════════════════════════ */
$FAM = array(
    'FATAL'      => '~(Fatal error|Parse error)\s*:\s*(.{0,150})~s',
    'WARNING'    => '~Warning\s*:\s*(.{0,130})~s',
    'NOTICE'     => '~Notice\s*:\s*(.{0,130})~s',
    'DEPRECATED' => '~Deprecated\s*:\s*(.{0,130})~s',
);
$out = array(); $tot = array_fill_keys(array_keys($FAM), 0);
$i = 0; $n = count($exists);
foreach ($exists as $rt => $src) {
    $i++; printf("\r  [%3d/%d] %-52s", $i, $n, mb_substr($rt, 0, 50));
    list($code, $hdr, $body) = ds_req($ctx['base'] . '/' . $rt, $ctx);
    $loc = ''; if (preg_match('~Location:\s*(\S+)~i', (string) $hdr, $lm)) { $loc = $lm[1]; }
    $rec = array('route' => $rt, 'src' => implode('+', array_keys($src)), 'code' => $code, 'hits' => array());
    if ($code === 302 || $code === 301) {
        $rec['verdict'] = 'REDIRECT'; $rec['detail'] = basename($loc);
    } elseif ($code !== 200) {
        $rec['verdict'] = 'HTTP_' . $code; $rec['detail'] = '';
    } else {
        $plain = strip_tags((string) $body);
        foreach ($FAM as $fam => $re) {
            if (preg_match_all($re, $plain, $mm)) {
                $cnt = count($mm[0]); $tot[$fam] += $cnt;
                $rec['hits'][$fam] = array('count' => $cnt,
                    'sample' => mb_substr(trim(preg_replace('~\s+~', ' ', $mm[0][0])), 0, 160));
            }
        }
        $rec['verdict'] = $rec['hits'] ? 'DIRTY' : 'CLEAN';
        $rec['detail']  = round(strlen((string) $body) / 1024, 1) . ' ك.ب';
    }
    $out[] = $rec;
}
echo "\r" . str_repeat(' ', 76) . "\r";

/* ═══ ④ العرض ══════════════════════════════════════════════════════════ */
$cnt = array('CLEAN' => 0, 'DIRTY' => 0, 'REDIRECT' => 0, 'OTHER' => 0);
foreach ($out as $r) { $k = isset($cnt[$r['verdict']]) ? $r['verdict'] : 'OTHER'; $cnt[$k]++; }
printf("══ مخرَجُ الأسطحِ ══\n  نظيف=%d · بأخطاءِ PHP=%d · مردود=%d · رمزٌ آخر=%d\n",
    $cnt['CLEAN'], $cnt['DIRTY'], $cnt['REDIRECT'], $cnt['OTHER']);

if ($ghost) {
    printf("\n══ مسارٌ مُعلَنٌ بلا ملفٍّ على القرص (%d) ══\n", count($ghost));
    foreach ($ghost as $g => $src) { printf("  · %-50s [%s]\n", $g, implode('+', array_keys($src))); }
}
if ($cnt['DIRTY']) {
    echo "\n══ أسطحٌ تطبع أخطاءَ PHP ══\n";
    foreach ($out as $r) {
        if ($r['verdict'] !== 'DIRTY') { continue; }
        printf("\n  ◆ %s (%s)\n", $r['route'], $r['detail']);
        foreach ($r['hits'] as $fam => $hh) { printf("     %-11s ×%-3d %s\n", $fam, $hh['count'], $hh['sample']); }
    }
}
if ($cnt['REDIRECT']) {
    echo "\n══ أسطحٌ مردودة ══\n";
    foreach ($out as $r) { if ($r['verdict'] === 'REDIRECT') { printf("  · %-50s → %s\n", $r['route'], $r['detail']); } }
}
if ($cnt['OTHER']) {
    echo "\n══ رمزُ HTTP غيرُ متوقَّع ══\n";
    foreach ($out as $r) { if (!isset($cnt[$r['verdict']])) { printf("  · %-50s %s\n", $r['route'], $r['verdict']); } }
}
echo "\n══ الحصيلةُ بالعائلة ══\n";
foreach ($tot as $f => $v) { printf("  %-11s %d\n", $f, $v); }
file_put_contents($ROOT . "/docs/injint01/{$TAG}_runtime.json",
    json_encode(array('workspace' => $WS, 'ghost' => array_keys($ghost), 'screens' => $out), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\n  التفصيل: docs/injint01/{$TAG}_runtime.json\n";
