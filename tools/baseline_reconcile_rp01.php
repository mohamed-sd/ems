<?php
/**
 * tools/baseline_reconcile_rp01.php — BL-20260828: مصالحة استخراج القرص بالسجل الرسمي
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **التحوّل**: النظام صار يملك سجلًّا رسميًّا (`repair01_screen_registry`) بمعرّفات
 *   `SCR-nnnn` ورموز إدارات `DEP-nn`. فمن اليوم **هو العمود الفقري للمعرّفات**،
 *   واستخراجُ القرص يُصالَح معه ولا يُستبدل به.
 * ◆ **ولا يُطوى الفرق**: ما في القرص ولا في السجل، وما في السجل ولا على القرص،
 *   وما هو `GHOST_TARGET` (هدفٌ لا As-Built) — كلُّها تُعدّ وتُسمّى.
 * ◆ قراءة فقط من extract/*.json.
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
$D = $ROOT . '/docs/baseline_20260821/extract/';
function j($f) { global $D; return json_decode((string) file_get_contents($D . $f . '.json'), true) ?: array(); }
function nr($r) { $r = str_replace(chr(92), '/', trim((string) $r)); return preg_replace('#^(\./|\.\./|/)+#', '', $r); }

$rp    = j('rp01_screen_registry');
$deps  = j('rp01_departments');
$mine  = j('screen_registry');          /* ناتج المُوفِّق القائم */
$disk  = j('disk_surfaces');
$fields= j('field_registry');

$depName = array();
foreach ($deps as $d) { $depName[$d['canonical_code']] = $d['name_ar']; }

/* فهرس السجل الرسمي بالمسار (غير حساس للحالة) */
$rpByRoute = array(); $rpByFile = array();
foreach ($rp as $r) {
    $k = mb_strtolower(nr($r['route']));
    if ($k !== '') { $rpByRoute[$k] = $r; }
    $b = mb_strtolower(basename((string) $r['screen_file']));
    if ($b !== '') { $rpByFile[$b][] = $r; }
}
/* فهرس القرص */
$diskByRoute = array();
foreach ($disk as $x) { $diskByRoute[mb_strtolower(nr($x['path']))] = $x; }

/* ══ المصالحة ══════════════════════════════════════════════════════════ */
$out = array(); $stat = array(
    'rp01_rows' => count($rp), 'disk_surfaces' => count($disk), 'mine_rows' => count($mine),
    'rp01_ghost_target' => 0, 'rp01_asbuilt' => 0,
    'matched_route' => 0, 'matched_basename' => 0, 'rp01_not_on_disk' => 0,
    'disk_not_in_rp01' => 0, 'rp01_owner_missing' => 0,
);
$seenDisk = array();
foreach ($rp as $r) {
    $ghost = ($r['lifecycle'] === 'GHOST_TARGET');
    if ($ghost) { $stat['rp01_ghost_target']++; } else { $stat['rp01_asbuilt']++; }
    if (trim((string) $r['owner_code']) === '') { $stat['rp01_owner_missing']++; }

    $k = mb_strtolower(nr($r['route']));
    $hit = null; $how = '';
    if ($k !== '' && isset($diskByRoute[$k])) { $hit = $diskByRoute[$k]; $how = 'ROUTE'; $stat['matched_route']++; }
    else {
        $b = mb_strtolower(basename((string) $r['screen_file']));
        foreach ($diskByRoute as $dk => $dv) {
            if (basename($dk) === $b) { $hit = $dv; $how = 'BASENAME'; $stat['matched_basename']++; break; }
        }
    }
    if ($hit) { $seenDisk[mb_strtolower(nr($hit['path']))] = 1; }
    elseif (!$ghost) { $stat['rp01_not_on_disk']++; }

    $out[] = array(
        'screen_id' => $r['screen_id'],
        'route' => nr($r['route']),
        'screen_file' => $r['screen_file'],
        'name_ar' => $r['canonical_label_ar'],
        'department_id' => $r['owner_code'],
        'department_name' => $depName[$r['owner_code']] ?? $r['owner_role'],
        'owner_rule' => $r['owner_rule'],
        'lifecycle' => $r['lifecycle'],
        'lifecycle_rule' => $r['lifecycle_rule'],
        'visibility_class' => $r['visibility_class'],
        'visibility_rule' => $r['visibility_rule'],
        'surface_kind' => $r['surface_kind'],
        'guard_kind' => $r['guard_kind'],
        'guard_evidence' => $r['guard_evidence'],
        'permission_policy' => $r['permission_policy'],
        'ownership_verdict' => $r['ownership_verdict'],
        'source_of_truth' => $r['source_of_truth'],
        'verdict_rule' => $r['verdict_rule'],
        'src_ref' => $r['src_ref'],
        'on_disk_measured' => $hit ? 1 : 0,
        'disk_match' => $how,
        'disk_class' => $hit ? $hit['class'] : '',
        'is_asbuilt' => $ghost ? 0 : 1,
    );
}
/* أسطح القرص التي لا يعرفها السجل الرسمي */
$orphans = array();
foreach ($disk as $x) {
    if (!in_array($x['class'], array('SCREEN', 'HANDLER', 'ENTRY', 'CRON'), true)) { continue; }
    $k = mb_strtolower(nr($x['path']));
    if (!isset($seenDisk[$k])) { $orphans[] = array('path' => $x['path'], 'class' => $x['class']); }
}
$stat['disk_not_in_rp01'] = count($orphans);

/* ربط الحقول بمعرّف الشاشة الرسمي */
$sidByRoute = array();
foreach ($out as $o) { if ($o['route'] !== '') { $sidByRoute[mb_strtolower($o['route'])] = $o['screen_id']; } }
$linked = 0; $unlinked = 0;
foreach ($fields as &$f) {
    $k = mb_strtolower(nr($f['route']));
    if (isset($sidByRoute[$k])) { $f['rp01_screen_id'] = $sidByRoute[$k]; $linked++; }
    else { $f['rp01_screen_id'] = 'NEEDS_REVIEW'; $unlinked++; }
}
unset($f);
$stat['fields_linked_to_rp01'] = $linked;
$stat['fields_unlinked'] = $unlinked;
$stat['fields_total'] = count($fields);

file_put_contents($D . 'rp01_reconciled.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
file_put_contents($D . 'rp01_orphans.json', json_encode($orphans, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
file_put_contents($D . 'field_registry.json', json_encode($fields, JSON_UNESCAPED_UNICODE));
file_put_contents($D . 'rp01_stats.json', json_encode($stat, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
foreach ($stat as $k => $v) { echo str_pad($k, 26) . $v . "\n"; }
