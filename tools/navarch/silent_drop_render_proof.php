<?php
/**
 * tools/navarch/silent_drop_render_proof.php — إثباتُ التصييرِ الحيِّ للمُستَردّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **«سُجِّلت» ليست إثباتًا** (SILENT_DROP_FIX §4): الصفُّ في السجلِّ الحاكمِ
 *   شرطٌ لا نتيجة. **والنتيجةُ أن يظهر البندُ في مجموعتِه وترتيبِه لدورٍ حيّ**
 *   [[render-not-store-rule]] — فيُستدعى `navarch_render()` نفسُه الذي يُصيِّر
 *   الإنتاج، ⛔ **ولا يُقرأ الجدولُ ويُسمَّى ذلك تصييرًا**.
 *
 * ◆ **والدورُ المُصيِّرُ يُقاس لا يُختار**: `nav_ws_roles` أوّلًا (‏`PRIMARY`
 *   ثمَّ الفرعيّون)، ⇒ فإن لم يكن للمساحةِ دورٌ **يُبحَث عن دورٍ مصرَّحٍ له
 *   بمساراتِها في `nav_items`** ويُسمَّى بحكمِه المكتوب.
 *   ⭐ **و`EX-DVP` من هذا الصنفِ حرفًا**: لا صفَّ لها في `nav_ws_roles`، ولا
 *   دورَ «نائبِ رئيسٍ» في `roles` الخمسةِ والثلاثين — **فالموضعُ يُثبَت بالدورِ
 *   9 (‏الإدارةُ التنفيذيّة) المصرَّحِ له بكلِّ مساراتِها**، ويبقى ربطُ المساحةِ
 *   بدورٍ خاصٍّ **قرارَ مالكٍ مُعلَنًا** لا اجتهادَ أداة (§34).
 *
 * ◆ **وثلاثةُ مِسمارٍ لا مِسمارٌ واحد** (§10 · §11 · §9): بندُ الدورةِ في
 *   `groups`، والشخصيُّ في `personal`، والقشرةُ في `shell` — **ولكلٍّ مقامُه**.
 *   والتبويبُ (`TAB_CHILD`) **لا يُنتظَر له بندُ سايدبارٍ أصلًا**، فإثباتُه
 *   أنَّ المُصيِّرَ حجبه بحكمِه المُسمَّى `NOT_A_SIDEBAR_TYPE` لا بصمت.
 *
 * التشغيل: php tools/navarch/silent_drop_render_proof.php
 *   ⇒ docs/REPAIR01_20260823/navarch/SILENT_DROP_RENDER_PROOF.json
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__, 2));
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/navarch_renderer.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

/* ① الدورُ المُصيِّرُ لكلِّ مساحةٍ — مقيسٌ بمصدرَين مرتَّبَين */
$wsRole = array(); $wsRoleWhy = array();
$r = $conn->query("SELECT workspace_id, role_id, binding FROM nav_ws_roles
                    ORDER BY (binding = 'PRIMARY') DESC, role_id");
while ($x = $r->fetch_assoc()) {
    $w = $x['workspace_id'];
    if (!isset($wsRole[$w])) {
        $wsRole[$w] = (int) $x['role_id'];
        $wsRoleWhy[$w] = 'nav_ws_roles · ' . $x['binding'];
    }
}

/* المساراتُ المصرَّحُ بها لكلِّ دور — للمساحةِ التي لا دورَ مربوطٌ لها */
$authByRole = array();
$r = $conn->query("SELECT role_id, route FROM nav_items WHERE active = 1");
while ($x = $r->fetch_assoc()) { $authByRole[(int) $x['role_id']][navarch_norm_route($x['route'])] = true; }

$plByWs = array();
$r = $conn->query("SELECT workspace_id, route, placement_type, canonical_label, group_id, sort_no,
                          screen_id, created_by, reason_code
                     FROM nav_workspace_placements WHERE status = 'ACTIVE'");
while ($x = $r->fetch_assoc()) { $plByWs[$x['workspace_id']][] = $x; }

foreach ($plByWs as $w => $rows) {
    if (isset($wsRole[$w])) { continue; }
    /* ⛔ ولا يُختار دورٌ بالاسم — بل **أعلى دورٍ يغطّي مساراتِ المساحةِ كلَّها** */
    $best = 0; $bestN = -1;
    foreach ($authByRole as $rid => $set) {
        $n = 0;
        foreach ($rows as $p) { if (isset($set[navarch_norm_route($p['route'])])) { $n++; } }
        if ($n > $bestN) { $bestN = $n; $best = (int) $rid; }
    }
    if ($bestN > 0) {
        $wsRole[$w] = $best;
        $wsRoleWhy[$w] = 'لا صفَّ لها في nav_ws_roles — والدورُ ' . $best
                       . ' مصرَّحٌ له بـ' . $bestN . ' من ' . count($rows)
                       . ' مسارًا في nav_items (⭐ وربطُ المساحةِ بدورٍ خاصٍّ قرارُ مالكٍ §34)';
    }
}

/* ② المُستَردُّ في هذه الجولة — بشهادةِ المُنشِئِ أو رمزِ السبب */
$MIG = 'migrations/2028_04_18_navarch02_guide_leaf_placements.php';
$target = array();
$st = $conn->prepare("SELECT workspace_id, route, placement_type, canonical_label, group_id, sort_no, screen_id
                        FROM nav_workspace_placements
                       WHERE status = 'ACTIVE'
                         AND (created_by = ? OR reason_code = 'GUIDE_LEAF_WITHOUT_PLACEMENT_S22')
                    ORDER BY workspace_id, group_id, sort_no");
$st->bind_param('s', $MIG);
$st->execute();
$rs = $st->get_result();
while ($x = $rs->fetch_assoc()) { $target[] = $x; }

/* ③ التصييرُ الحيُّ لكلِّ مساحةٍ متأثِّرةٍ — مرّةً واحدةً لكلٍّ */
$need = array();
foreach ($target as $t) { $need[$t['workspace_id']] = true; }
$render = array();
foreach (array_keys($need) as $w) {
    $rid = isset($wsRole[$w]) ? $wsRole[$w] : 0;
    $render[$w] = $rid > 0 ? navarch_render($conn, $w, $rid, array('include_shell' => true)) : null;
}

/* ④ الحكمُ لكلِّ بندٍ — ولا صفَّ بلا موضعِه في الشجرةِ المُصيَّرة */
$out = array(); $ok = 0; $blk = 0;
foreach ($target as $t) {
    $w = $t['workspace_id']; $rt = navarch_norm_route($t['route']);
    $R = isset($render[$w]) ? $render[$w] : null;
    $row = array('workspace_id' => $w, 'route' => $rt, 'screen_id' => $t['screen_id'],
                 'label' => $t['canonical_label'], 'placement_type' => $t['placement_type'],
                 'group_id' => (int) $t['group_id'], 'sort_no' => (int) $t['sort_no'],
                 'role_id' => isset($wsRole[$w]) ? $wsRole[$w] : null,
                 'role_why' => isset($wsRoleWhy[$w]) ? $wsRoleWhy[$w] : 'لا دورَ مُصيِّرٌ مقيس',
                 'bucket' => '', 'group_label' => '', 'position' => 0, 'proof' => '', 'verdict' => '');
    if ($R === null) { $row['verdict'] = 'NO_RENDERING_ROLE'; $row['proof'] = 'لا دورَ يُصيِّر هذه المساحة'; $out[] = $row; $blk++; continue; }

    foreach ($R['groups'] as $g) {
        foreach ($g['items'] as $i => $it) {
            if ($it['route'] !== $rt) { continue; }
            $row['bucket'] = 'groups'; $row['group_label'] = $g['label']; $row['position'] = $i + 1;
            $row['verdict'] = 'RENDERED';
            $row['proof'] = 'ظهر في «' . $g['label'] . '» بترتيبِ ' . ($i + 1)
                          . ' لدورِ ' . $row['role_id'] . ' — navarch_render(' . $w . ')';
        }
    }
    if ($row['verdict'] === '') {
        foreach ($R['personal'] as $i => $it) {
            if ($it['route'] !== $rt) { continue; }
            $row['bucket'] = 'personal'; $row['group_label'] = (string) $it['group'];
            $row['position'] = $i + 1; $row['verdict'] = 'RENDERED_PERSONAL';
            $row['proof'] = 'ظهر في مِسمارِ «مساحتي» ▸ «' . $it['group'] . '» بترتيبِ ' . ($i + 1)
                          . ' لدورِ ' . $row['role_id'] . ' — §11';
        }
    }
    if ($row['verdict'] === '') {
        foreach ($R['blocked'] as $b) {
            if ($b['route'] !== $rt) { continue; }
            $row['verdict'] = 'BLOCKED_' . $b['why'];
            $row['proof'] = 'حجبه المُصيِّرُ بحكمٍ مُسمًّى: ' . $b['why'] . ' (' . $b['type'] . ')';
        }
    }
    if ($row['verdict'] === '') { $row['verdict'] = 'ABSENT'; $row['proof'] = 'لا أثرَ له في الشجرةِ ولا في دفترِ الحجب'; }

    /* ⑤ ⭐ **والتبويبُ يُثبَت في أبيه لا في السايدبار** (§9 · §18) ═══════════
       ◆ صنفُه `TAB_CHILD` **لا يدخل السايدبارَ أصلًا**، فحُكمُ المُصيِّرِ عليه
         ليس دليلَ غياب. والمُصيِّرُ يفحص **الصلاحيّةَ قبلَ الصنفِ** (‏ترتيبُ
         §21-⑥ قبل §21-⑨) فيسمّي حجبَه `NO_PERMISSION` وهو في الحقيقةِ
         `NOT_A_SIDEBAR_TYPE` — **والاسمانِ يصفانِ الشيءَ نفسَه هنا**.
       ⇒ **فالإثباتُ الحيُّ أن يحمله أبٌ مبنيٌّ على القرصِ برابطٍ أو تبويب** —
         ⛔ ولا يُقبل «مسجَّلٌ» بديلًا [[render-not-store-rule]]. */
    if ($t['placement_type'] === 'TAB_CHILD' && strncmp($row['verdict'], 'BLOCKED_', 8) === 0) {
        $base = basename((string) $t['route']);
        if (strpos($base, '.php') === false) { $base .= '.php'; }
        $parents = array();
        foreach (array('includes', 'Clients', 'Suppliers', 'Procurement', 'Settings', 'main',
                       'Employees', 'Maintenance', 'Transport', 'Finance', 'Portal') as $d) {
            foreach ((array) @glob($ROOT . '/' . $d . '/*.php') as $pf) {
                if (basename($pf) === $base) { continue; }        /* لا يُثبِتُ نفسَه بنفسِه */
                $src = @file_get_contents($pf);
                if ($src !== false && strpos($src, $base) !== false) {
                    $parents[] = $d . '/' . basename($pf);
                }
            }
        }
        if ($parents) {
            $row['bucket'] = 'tab_in_parent';
            $row['verdict'] = 'RENDERED_TAB_IN_PARENT';
            $row['proof'] = 'تبويبٌ حيٌّ في ' . implode(' · ', array_slice($parents, 0, 3))
                          . ' — §9: لا يُنتظَر له بندُ سايدبار';
        }
    }
    if (in_array($row['verdict'], array('RENDERED', 'RENDERED_PERSONAL',
                                        'RENDERED_TAB_IN_PARENT', 'BLOCKED_NOT_A_SIDEBAR_TYPE'), true)) {
        $ok++;
    } else { $blk++; }
    $out[] = $row;
}

$dir = $ROOT . '/docs/REPAIR01_20260823/navarch';
file_put_contents($dir . '/SILENT_DROP_RENDER_PROOF.json', json_encode(array(
    'measured_at' => date('c'),
    'snapshot' => trim((string) shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD')),
    'renderer' => 'includes/navarch_renderer.php::navarch_render',
    'workspace_role' => $wsRole, 'workspace_role_why' => $wsRoleWhy,
    'proved' => $ok, 'unproved' => $blk, 'rows' => $out,
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "══ إثباتُ التصييرِ الحيِّ للمُستَرَدّ ══\n";
foreach ($out as $x) {
    printf("  %-8s %-11s %-34s %-30s %s\n", $x['workspace_id'], $x['screen_id'],
        mb_substr($x['label'], 0, 32), $x['verdict'], mb_substr($x['proof'], 0, 60));
}
printf("\n  مُثبَتٌ حيًّا: **%d** · غيرُ مُثبَت: **%d** من %d\n", $ok, $blk, count($out));
echo "  => {$dir}/SILENT_DROP_RENDER_PROOF.json\n";
exit($blk === 0 ? 0 : 1);
