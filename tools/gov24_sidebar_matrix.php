<?php
/**
 * gov24_sidebar_matrix.php — ⑨ مصفوفةُ تحقُّقِ السايدبارِ الكاملة
 * ═══════════════════════════════════════════════════════════════════════════
 * «تشمل كلَّ الإداراتِ وكلَّ المراحل، وتثبت لكلِّ مرحلة: الإدارة · الترتيب ·
 *  المجموعة · الشاشة · المستندُ الناتج · الدورُ المسؤول · الحالةُ التالية.
 *  ولا يُعلن ١١٧ مرحلة = ١٠٠٪ دون هذا الإثبات.»
 *
 * التشغيل:
 *   php tools/gov24_sidebar_matrix.php              ملخّصٌ وفجوات
 *   php tools/gov24_sidebar_matrix.php --md=<path>  المصفوفةُ الكاملةُ Markdown
 *   php tools/gov24_sidebar_matrix.php --csv=<path> المصفوفةُ الكاملةُ CSV
 *   php tools/gov24_sidebar_matrix.php --gate       رمزُ خروجٍ 1 عند أيِّ فجوة
 *
 * ◆ المصفوفةُ تُظهر الفجوةَ ولا تسدُّها بالتخمين: عمودٌ بلا مصدرٍ حيٍّ يُكتب
 *   «— لا مصدر» ويُعدُّ نقصًا في نسبةِ الإثبات.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$db = $conn;

$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = $m[2] ?? '1'; }
}

// ───────────────── مصادرُ الأعمدةِ السبعة — أيُّها حيٌّ وأيُّها مفقود ─────────────────
$SRC = array(
    'الإدارة'          => 'roles.name عبر link_groups.owner_role_id',
    'الترتيب'          => 'link_groups.stage_no + display_order + nav_items.sort_order',
    'المجموعة'         => 'link_groups.name',
    'الشاشة'           => 'nav_items.label_ar + route',
    'المستند الناتج'   => null,   // يُبحث أدناه
    'الدور المسؤول'    => 'modules.owner_role_id',
    'الحالة التالية'   => null,   // يُبحث أدناه
);

// ◆ المصدرُ الحاكم: gov_stage_outputs — لكلِّ (دور × مرحلة) مستندُها وحالتُها
//   ولكلِّ خانةٍ عمودُ مصدرٍ يقول من أين جاءت (GOV-24 · LAD-01 · nav09_map · cycle_order).
$stageOut = array();
$r = $db->query("SELECT role_id, stage_no, output_doc, output_source, next_state, next_source FROM gov_stage_outputs");
while ($r && ($x = $r->fetch_assoc())) { $stageOut[$x['role_id'] . ':' . $x['stage_no']] = $x; }

// (الاحتياطُ القديم — يبقى لما لا يجد سجلًّا)
// المستندُ الناتج: هل لأيِّ شاشةٍ نوعُ مستندٍ مسنَدٌ في scr_doc_types؟
$docMap = array();
$r = $db->query("SELECT `dept_owning`, `name_doc`, `code_type` FROM `scr_doc_types` WHERE `name_doc` IS NOT NULL");
while ($r && ($x = $r->fetch_assoc())) {
    $k = trim((string) $x['dept_owning']);
    if ($k !== '') { $docMap[$k][] = (string) $x['name_doc']; }
}

// الحالةُ التالية: من scr_state_machines إن رُبطت بنوعِ مستند
$stateMap = array();
$r = $db->query("SELECT `type_doc`, `from_state`, `to_state` FROM `scr_state_machines`
                  WHERE `to_state` IS NOT NULL AND `to_state` <> ''");
while ($r && ($x = $r->fetch_assoc())) {
    $k = trim((string) $x['type_doc']);
    if ($k !== '') { $stateMap[$k] = $x['from_state'] . ' → ' . $x['to_state']; }
}

// ───────────────────────────── جمعُ المصفوفة ─────────────────────────────
$sql = "
SELECT  g.`owner_role_id`                       AS role_id,
        COALESCE(ro.`name`,'(دورٌ محذوف)')      AS dept,
        g.`stage_no`                            AS stage_no,
        COALESCE(g.`stage_title`,'(بلا مرحلة)') AS stage_title,
        g.`display_order`                       AS group_order,
        g.`group_code`                          AS group_code,
        g.`name`                                AS group_name,
        n.`sort_order`                          AS screen_order,
        n.`label_ar`                            AS screen_label,
        n.`route`                               AS screen_route,
        m.`owner_role_id`                       AS screen_owner_role,
        COALESCE(mo.`name`, NULLIF(m.`owner_dept_note`,''), '—') AS screen_owner_name,
        CASE WHEN mo.`name` IS NOT NULL THEN 'role'
             WHEN NULLIF(m.`owner_dept_note`,'') IS NOT NULL THEN 'dept_note'
             ELSE 'none' END                      AS owner_src
  FROM `link_groups` g
  LEFT JOIN `roles` ro      ON ro.`id` = g.`owner_role_id`
  LEFT JOIN `nav_items` n   ON n.`group_id` = g.`id` AND n.`active` = 1
  LEFT JOIN `modules` m     ON m.`id` = n.`module_id`
  LEFT JOIN `roles` mo      ON mo.`id` = m.`owner_role_id`
 WHERE g.`is_active` = 1
 ORDER BY g.`owner_role_id`, g.`stage_no`, g.`display_order`, n.`sort_order`, n.`id`";

$rows = array();
$res = $db->query($sql);
if (!$res) { fwrite(STDERR, "استعلامُ المصفوفةِ فشل: " . $db->error . "\n"); exit(2); }
while ($x = $res->fetch_assoc()) { $rows[] = $x; }

// ───────────────────────── إسنادُ العمودين الناقصين ─────────────────────────
$FIELDS = array('dept', 'order', 'group', 'screen', 'doc', 'owner', 'next_state');
$proven = array_fill_keys($FIELDS, 0);
$total  = 0;
$matrix = array();

foreach ($rows as $x) {
    $hasScreen = ($x['screen_route'] !== null && $x['screen_route'] !== '');
    $total++;

    $dept  = (string) $x['dept'];
    $order = $x['stage_no'] !== null ? ($x['stage_no'] . '.' . (int) $x['group_order'] . '.' . (int) $x['screen_order']) : '—';
    $group = (string) $x['group_name'];
    $scr   = $hasScreen ? ($x['screen_label'] . ' · ' . $x['screen_route']) : '— لا شاشة';
    // الإدارةُ النصيةُ مصدرٌ مشروعٌ للوحدةِ المشترَكةِ بين أدوارٍ كثيرة —
    // وإسنادُ دورٍ واحدٍ لها كذبٌ يرفع نسبةً ويُفسد معنى.
    $owner = ($x['screen_owner_name'] !== '—' && $x['screen_owner_name'] !== null)
        ? ((string) $x['screen_owner_name']
           . ($x['owner_src'] === 'dept_note' ? '  [إدارة]' : ''))
        : ($hasScreen ? '— لا وحدة' : '—');

    // المستندُ الناتجُ والحالةُ التالية — من السجلِّ الحاكمِ بمصدرِهما
    $doc = '— لا مصدر'; $next = '— لا مصدر';
    $k = $x['role_id'] . ':' . $x['stage_no'];
    if (isset($stageOut[$k])) {
        $so = $stageOut[$k];
        if ($so['output_source'] !== 'none' && $so['output_doc'] !== null && $so['output_doc'] !== '') {
            $doc = $so['output_doc'] . '  [' . $so['output_source'] . ']';
        }
        if ($so['next_source'] !== 'none' && $so['next_state'] !== null && $so['next_state'] !== '') {
            $next = $so['next_state'] . '  [' . $so['next_source'] . ']';
        }
    }

    if ($dept !== '' && $dept !== '(دورٌ محذوف)') { $proven['dept']++; }
    if ($order !== '—')                            { $proven['order']++; }
    if ($group !== '')                             { $proven['group']++; }
    if ($hasScreen)                                { $proven['screen']++; }
    if ($doc !== '— لا مصدر')                      { $proven['doc']++; }
    if ($owner !== '—' && $owner !== '— لا وحدة')  { $proven['owner']++; }
    if ($next !== '— لا مصدر')                     { $proven['next_state']++; }

    $matrix[] = array(
        'role_id' => (int) $x['role_id'], 'dept' => $dept, 'stage_no' => $x['stage_no'],
        'stage_title' => (string) $x['stage_title'], 'order' => $order,
        'group_code' => (string) $x['group_code'], 'group' => $group,
        'screen' => $scr, 'doc' => $doc, 'owner' => $owner, 'next' => $next,
        'has_screen' => $hasScreen,
    );
}

// ───────────────────────────── الملخّص ─────────────────────────────
$depts  = count(array_unique(array_column($matrix, 'role_id')));
$stages = count(array_unique(array_map(function ($m) { return $m['role_id'] . ':' . $m['stage_no']; }, $matrix)));
$groups = count(array_unique(array_column($matrix, 'group_code')));
$screens = count(array_filter($matrix, function ($m) { return $m['has_screen']; }));
$emptyGroups = count(array_filter($matrix, function ($m) { return !$m['has_screen']; }));

echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo " ⑨ مصفوفةُ تحقُّقِ السايدبار — كلُّ الإداراتِ وكلُّ المراحل\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
printf("  إدارات (أدوار)      : %d\n", $depts);
printf("  مراحل (دور×مرحلة)   : %d\n", $stages);
printf("  مجموعات             : %d\n", $groups);
printf("  صفوفُ المصفوفة      : %d   (منها %d بشاشةٍ و%d مجموعةٌ فارغة)\n", $total, $screens, $emptyGroups);

echo "\n  إثباتُ الأعمدةِ السبعة:\n";
$LABEL = array('dept' => 'الإدارة', 'order' => 'الترتيب', 'group' => 'المجموعة',
    'screen' => 'الشاشة', 'doc' => 'المستند الناتج', 'owner' => 'الدور المسؤول',
    'next_state' => 'الحالة التالية');
$allPct = array();
foreach ($FIELDS as $f) {
    $pct = $total ? 100 * $proven[$f] / $total : 0;
    $allPct[$f] = $pct;
    printf("    %-18s %5d/%-5d = %5.1f%%  %s\n", $LABEL[$f], $proven[$f], $total, $pct,
        $pct >= 99.5 ? '✔' : ($pct >= 50 ? '◐' : '✘'));
}
$overall = array_sum($allPct) / count($allPct);
printf("\n  ── نسبةُ الإثباتِ الكلية: %.1f%%\n", $overall);

$gaps = array();
foreach ($FIELDS as $f) { if ($allPct[$f] < 99.5) { $gaps[] = $LABEL[$f] . sprintf(' (%.1f%%)', $allPct[$f]); } }
if ($gaps) {
    echo "\n  ◆ فجواتٌ تمنع إعلانَ ١٠٠٪:\n";
    foreach ($gaps as $g) { echo "     · $g\n"; }
    // مصادرُ الخانتين — تُقرأ من السجلِّ لا تُكتب نصًّا مثبَّتًا يتقادم
    $sr = $db->query("SELECT output_source s, COUNT(*) c FROM gov_stage_outputs GROUP BY output_source ORDER BY c DESC");
    echo "
  ◆ مصادرُ «المستندِ الناتج» — لكلِّ خانةٍ مصدرُها المعلَن:
";
    while ($sr && ($sx = $sr->fetch_row())) { printf("     %-16s %s مرحلة
", $sx[0], $sx[1]); }
    $sr = $db->query("SELECT next_source s, COUNT(*) c FROM gov_stage_outputs GROUP BY next_source ORDER BY c DESC");
    echo "  ◆ مصادرُ «الحالةِ التالية»:
";
    while ($sr && ($sx = $sr->fetch_row())) { printf("     %-16s %s مرحلة
", $sx[0], $sx[1]); }
    echo "  ◆ وما بقي بلا مصدرٍ يبقى ظاهرًا — ولا يُملأ تخمينًا.
";
} else {
    echo "\n  ✔ الأعمدةُ السبعةُ مُثبَتةٌ لكلِّ مرحلة\n";
}
echo "═══════════════════════════════════════════════════════════════════════\n\n";

// ───────────────────────────── الإخراج ─────────────────────────────
if (isset($args['md'])) {
    $out = "# ⑨ مصفوفةُ تحقُّقِ السايدبارِ الكاملة\n\n";
    $out .= sprintf("> إدارات **%d** · مراحل **%d** · مجموعات **%d** · صفوف **%d**\n", $depts, $stages, $groups, $total);
    $out .= sprintf("> **نسبةُ الإثباتِ الكلية: %.1f%%**\n\n", $overall);
    $out .= "| الإدارة | الترتيب | المرحلة | المجموعة | الشاشة | المستند الناتج | الدور المسؤول | الحالة التالية |\n";
    $out .= "|---|---|---|---|---|---|---|---|\n";
    foreach ($matrix as $m) {
        $out .= sprintf("| %s | %s | %s | %s | %s | %s | %s | %s |\n",
            str_replace('|', '\\|', $m['dept']), $m['order'],
            str_replace('|', '\\|', $m['stage_title']),
            str_replace('|', '\\|', $m['group']),
            str_replace('|', '\\|', $m['screen']),
            str_replace('|', '\\|', $m['doc']),
            str_replace('|', '\\|', $m['owner']),
            str_replace('|', '\\|', $m['next']));
    }
    file_put_contents($args['md'], $out);
    echo "  المصفوفةُ الكاملة: {$args['md']} (" . number_format(strlen($out)) . " بايت)\n\n";
}

if (isset($args['csv'])) {
    $fh = fopen($args['csv'], 'w');
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, array('الإدارة', 'الترتيب', 'المرحلة', 'المجموعة', 'الشاشة', 'المستند الناتج', 'الدور المسؤول', 'الحالة التالية'));
    foreach ($matrix as $m) {
        fputcsv($fh, array($m['dept'], $m['order'], $m['stage_title'], $m['group'], $m['screen'], $m['doc'], $m['owner'], $m['next']));
    }
    fclose($fh);
    echo "  CSV: {$args['csv']}\n\n";
}

if (isset($args['gate'])) { exit($gaps ? 1 : 0); }
exit(0);
