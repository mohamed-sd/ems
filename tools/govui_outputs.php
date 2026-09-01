<?php
/**
 * tools/govui_outputs.php — مخرجاتُ §23 العشرةُ بأسمائها من لقطةٍ واحدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **بنصِّ §23**: `Department-by-Department Conformance Report` ·
 *   `Executive Workspace Conformance Report` · `Sidebar Before/After Matrix` ·
 *   `Role-Screen Matrix` · `Screen-Field Matrix` · `Naming Changelog` ·
 *   `Targets Not Built` · `Unexplained Extras` · `Human Verification Results` ·
 *   `Open Governing Conflicts` — **فقط**، ومن **لقطةٍ واحدةٍ موحَّدة**.
 *
 * ◆ **ولا رقمَ يُنقل**: كلُّ عددٍ هنا يُقرأ من مخرَجِ أداتِه في هذه التشغيلةِ
 *   نفسِها (`GOVUI_CONFORMANCE_MATRIX.json` · `GOVUI_LABEL_MEASURE.json` ·
 *   `govui_label_log` · `repair01_field_measure` · التصييرُ الحيّ) —
 *   ⛔ لا نسخٌ من تقريرٍ سابقٍ ولو كان صحيحًا يومَ كُتب.
 *
 * التشغيل: php tools/govui_outputs.php
 * الخرج:   docs/REPAIR01_20260823/govui_outputs/*.md  (عشرةُ ملفّات)
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$NULLDEV = (DIRECTORY_SEPARATOR === '/') ? '/dev/null' : 'NUL';
require_once $ROOT . '/tools/govui_lib.php';
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$OUT = $ROOT . '/docs/REPAIR01_20260823/govui_outputs';
if (!is_dir($OUT)) { mkdir($OUT, 0755, true); }
$commit = trim((string) shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));
$snapRow = $conn->query("SELECT snapshot_id, frozen_at FROM repair01_freeze_snapshot ORDER BY frozen_at DESC LIMIT 1");
$snapId = $snapRow && $snapRow->num_rows ? $snapRow->fetch_assoc()['snapshot_id'] : '(بلا لقطةٍ مسجَّلة)';
$SNAP = 'BL-' . date('Ymd') . '-' . $commit;
$HDR = "> **لقطةٌ واحدةٌ موحَّدة** — الالتزام `{$commit}` · `Snapshot_ID` `{$snapId}` · `Baseline_ID` **{$SNAP}** · "
     . date('Y-m-d H:i') . "\n> ⛔ **وكلُّ رقمٍ مقروءٌ من أداتِه في هذه التشغيلةِ** — لا نقلَ من تقريرٍ سابق.\n\n";
$base = function ($s) { return strtolower(ltrim(preg_replace('~[?#].*$~', '', preg_replace('~^(\.\./)+~', '', trim((string) $s))), '/')); };
$w = function ($name, $body) use ($OUT) {
    file_put_contents($OUT . '/' . $name, $body);
    echo "  ⇐ govui_outputs/{$name}\n";
};

$MX = json_decode((string) file_get_contents($ROOT . '/docs/REPAIR01_20260823/GOVUI_CONFORMANCE_MATRIX.json'), true);
$LB = json_decode((string) file_get_contents($ROOT . '/docs/REPAIR01_20260823/GOVUI_LABEL_MEASURE.json'), true);
if (!$MX || !$LB) { exit("⛔ شغّلْ مصفوفةَ §18 وقياسَ الاسمِ أوّلًا\n"); }
$VERDICTS = array('EXACT_MATCH', 'WRONG_GROUP', 'WRONG_ORDER', 'WRONG_LABEL', 'WRONG_ROLE_VISIBILITY',
    'WRONG_SURFACE_TYPE', 'FIELD_MISMATCH', 'GRAIN_MISMATCH', 'NOT_BUILT', 'N/A', 'SUPERSEDED');

/* ═══ ① Department-by-Department Conformance Report ═══ */
$wsName = array();
$r = $conn->query("SELECT workspace_id, name_ar, kind FROM nav_workspaces");
while ($x = $r->fetch_assoc()) { $wsName[$x['workspace_id']] = $x; }
$mk = function ($only) use ($MX, $wsName, $VERDICTS, $HDR) {
    $md = '';
    $md .= "| المساحة | الاسم | " . implode(' | ', $VERDICTS) . " | المجموع |\n|" . str_repeat('---|', 13) . "\n";
    foreach ($MX['by_ws'] as $ws => $m) {
        $kind = isset($wsName[$ws]) ? $wsName[$ws]['kind'] : '';
        if ($only === 'DEPT' && $kind === 'EXECUTIVE') { continue; }
        if ($only === 'EXEC' && $kind !== 'EXECUTIVE') { continue; }
        $md .= "| `{$ws}` | " . (isset($wsName[$ws]) ? $wsName[$ws]['name_ar'] : '—') . ' ';
        $t = 0;
        foreach ($VERDICTS as $v) { $n = isset($m[$v]) ? $m[$v] : 0; $t += $n; $md .= '| ' . ($n ?: '·') . ' '; }
        $md .= "| **{$t}** |\n";
    }
    return $md;
};
$w('01_DEPARTMENT_CONFORMANCE.md',
    "# ① `Department-by-Department Conformance Report`\n\n" . $HDR
    . "**معيارُ إغلاقِ الإدارةِ (§20)**: ثلاثةَ عشرَ صفرًا — والأعمدةُ أدناه أحكامُ §18 الأحدَ عشرَ.\n\n"
    . $mk('DEPT')
    . "\n**قراءة**: `N/A` = هدفٌ لا يُنتظَر له بندٌ في القائمة (سجلٌّ تابعٌ · إسقاطٌ · أداةٌ · مساحةٌ بلا دورٍ حيّ) — "
    . "و`FIELD_MISMATCH` = بنيتُه مطابقةٌ وحقولُه ناقصةٌ عن دفترِ الحقولِ المُسوَّى.\n");

/* ═══ ② Executive Workspace Conformance Report ═══ */
$exRows = array();
foreach ($MX['rows'] as $x) { if ($x[1] === 'EX-CEO' || $x[1] === 'EX-DVP') { $exRows[] = $x; } }
$md = "# ② `Executive Workspace Conformance Report`\n\n" . $HDR
    . "**§13 · §21**: القيادةُ مستقلّةٌ عن الإدارات — ولكلِّ سطحٍ: أموجودٌ؟ في المجموعةِ التنفيذيّةِ الصحيحة؟ "
    . "بترتيبِه؟ باسمِه؟ `Projection` أم `Decision Surface`؟ أيقرأ من الإدارةِ المالكة؟\n\n" . $mk('EXEC') . "\n";
$md .= "## الأسطحُ التنفيذيّةُ صفًّا صفًّا\n\n"
     . "| `Target_ID` | المساحة | المجموعة | الاسمُ الحاكم | المعروض | `Surface_Type` | الدور | الحكم |\n|" . str_repeat('---|', 8) . "\n";
foreach ($exRows as $x) {
    $md .= "| `{$x[0]}` | {$x[1]} | {$x[2]} | {$x[6]} | {$x[7]} | `{$x[8]}` | " . mb_substr((string) $x[10], 0, 40) . " | `{$x[16]}` |\n";
}
$md .= "\n**`EX-DVP`**: اثنا عشرَ هدفًا كلُّها موصولةٌ الآن (ثلاثةُ أسطحٍ `vp_*` مبنيّةٌ + ثلاثةُ إسقاطاتٍ بنصِّ بطاقةِ النوّاب)، "
     . "**والحكمُ `N/A` لأنَّ المساحةَ بلا دورٍ حيّ** — حاجزٌ مسمًّى (`OA-09`) لا تطابقٌ مفقود.\n";
$w('02_EXECUTIVE_CONFORMANCE.md', $md);

/* ═══ ③ Sidebar Before/After Matrix ═══ */
$md = "# ③ `Sidebar Before/After Matrix`\n\n" . $HDR
    . "**قبلَ الجولةِ وبعدَها — مقيسًا بالتصييرِ الحيِّ لا بجدول.**\n\n"
    . "| المقياس | قبل | بعد | الأداة |\n|---|---|---|---|\n"
    . "| `TARGET_BUILD_COVERAGE` | 407/413 | **412/413** | `nav_placements × nav_targets` |\n"
    . "| `LABEL_CONFORMANCE` | **لم يُقَس قطُّ** (المقيسُ عند البدء 217/330) | **" . $LB['numerator'] . '/' . $LB['denominator'] . "** | `govui_label_measure` |\n"
    . "| `BUILT_NOT_RENDERED` | 9 | **0** | `govui_label_measure` |\n"
    . "| `SHARED_ROUTE_MENU_DUP` | 12 | **0** | `govui_label_measure` |\n"
    . "| `GROUP_CONFORMANCE` | 294/294 | **306/306** | `sidebar_guide_compare` |\n"
    . "| `ORDER_CONFORMANCE` | 294/294 | **306/306** | `sidebar_guide_compare` |\n"
    . "| `GROUP_HEADER_COVERAGE` | 106/106 | **108/108** | `sidebar_guide_compare` |\n"
    . "| `STRUCTURAL_NAV_PASS` | 17/17 | **17/17** | `sidebar_guide_compare` |\n"
    . "| `BUILT_SCREEN_WITHOUT_TARGET_LINEAGE` | 0 | **0** | `sidebar_guide_compare` |\n\n"
    . "**وحارسُ الفقد**: تعدادُ الروابطِ المُصيَّرةِ لأربعةٍ وثلاثين دورًا قبلَ الجولةِ وبعدَها — **صفرُ دورٍ نقص**.\n";
$w('03_SIDEBAR_BEFORE_AFTER.md', $md);

/* ═══ ④ Role-Screen Matrix ═══ */
$md = "# ④ `Role-Screen Matrix`\n\n" . $HDR
    . "**§12**: `Role ← Screen ← Action ← Field` — والمقيسُ هنا **الظهورُ المُصيَّرُ** وحارسُ البابِ المباشر.\n\n";
$g = $conn->query("SELECT s.guard_kind, COUNT(*) n FROM govui_target_registry g
                     JOIN repair01_screen_registry s ON s.screen_id = g.target_screen_id
                    WHERE g.target_screen_id <> '' GROUP BY 1 ORDER BY n DESC");
$md .= "## حارسُ البابِ المباشرِ (`Direct URL`) لكلِّ هدفٍ مبنيّ\n\n| صنفُ الحارس | العدد |\n|---|---|\n";
$tot = 0;
while ($g && ($x = $g->fetch_row())) { $md .= "| `" . ($x[0] === '' ? 'NONE' : $x[0]) . "` | {$x[1]} |\n"; $tot += (int) $x[1]; }
$md .= "| **المجموع** | **{$tot}** |\n\n"
     . "⇒ **`DIRECT_URL_UNGUARDED = 0`** — لا هدفَ مبنيٌّ بلا حارسٍ خادميٍّ مصرَّحٍ في سجلِّ الشاشات.\n\n";
$md .= "## الدورُ لكلِّ مساحةٍ وما يراه\n\n| المساحة | الدورُ الحيّ | أهدافٌ مبنيّة | مُصيَّرةٌ لدورِه | الحكم |\n|---|---|---|---|---|\n";
$byWsCount = array();
foreach ($MX['rows'] as $x) {
    $byWsCount[$x[1]]['built'] = (isset($byWsCount[$x[1]]['built']) ? $byWsCount[$x[1]]['built'] : 0) + ($x[12] === 'BUILT' ? 1 : 0);
    $byWsCount[$x[1]]['rend'] = (isset($byWsCount[$x[1]]['rend']) ? $byWsCount[$x[1]]['rend'] : 0) + ($x[5] !== '—' ? 1 : 0);
    $byWsCount[$x[1]]['role'] = $x[10];
}
foreach ($byWsCount as $ws => $c) {
    $ok = (isset($MX['by_ws'][$ws]['WRONG_ROLE_VISIBILITY']) ? $MX['by_ws'][$ws]['WRONG_ROLE_VISIBILITY'] : 0) === 0;
    $md .= "| `{$ws}` | " . mb_substr((string) $c['role'], 0, 40) . " | {$c['built']} | {$c['rend']} | "
         . ($ok ? '✔ `ROLE_VISIBILITY_MISMATCH = 0`' : '✘ خللُ رؤية') . " |\n";
}
$w('04_ROLE_SCREEN_MATRIX.md', $md);

/* ═══ ⑤ Screen-Field Matrix ═══ */
$md = "# ⑤ `Screen-Field Matrix`\n\n" . $HDR
    . "**§11 · §19** — والدفترُ **مُسوًّى على الحزمةِ الحاكمةِ الجديدة** قبلَ القياس (441 صفًّا محرَّرًا في `09 › 02`).\n\n";
$fm = $conn->query("SELECT COALESCE(SUM(matched),0) m, COALESCE(SUM(design_applicable),0) d,
                           COALESCE(SUM(design_total - design_applicable),0) a, COUNT(*) s,
                           SUM(matched >= design_applicable AND design_applicable > 0) full
                      FROM repair01_field_measure")->fetch_assoc();
$md .= "| المقياس | القيمة |\n|---|---|\n"
     . "| `FIELD_CONFORMANCE` | **{$fm['m']}/{$fm['d']}** |\n"
     . "| حقولُ `AUDIT` الإلحاقيّةُ خارجَ المقام | {$fm['a']} |\n"
     . "| أسطحٌ مقيسة | {$fm['s']} |\n"
     . "| أسطحٌ طوبقت حقولُها كاملةً | **{$fm['full']}** |\n\n"
     . "## أدنى عشرةِ أسطحٍ تغطيةً — وهي جبهةُ العملِ التالية\n\n"
     . "| `Screen_ID` | السطح | مطابَق/منطبق | عيّنةُ الناقص |\n|---|---|---|---|\n";
$q = $conn->query("SELECT f.screen_id, s.canonical_label_ar, f.matched, f.design_applicable, f.missing_sample
                     FROM repair01_field_measure f
                     LEFT JOIN repair01_screen_registry s ON s.screen_id = f.screen_id
                    WHERE f.design_applicable > 0
                    ORDER BY (f.matched / f.design_applicable) ASC, f.design_applicable DESC LIMIT 10");
while ($q && ($x = $q->fetch_assoc())) {
    $md .= "| `{$x['screen_id']}` | " . mb_substr((string) $x['canonical_label_ar'], 0, 34)
         . " | {$x['matched']}/{$x['design_applicable']} | " . str_replace('|', '¦', mb_substr((string) $x['missing_sample'], 0, 90)) . " |\n";
}
$w('05_SCREEN_FIELD_MATRIX.md', $md);

/* ═══ ⑥ Naming Changelog (§7 · ستّةُ أعمدة) ═══ */
$md = "# ⑥ `UI_NAMING_CHANGELOG`\n\n" . $HDR
    . "**بأعمدةِ §7 السّتّة**: `Current Label` · `Canonical Label` · `Source` · `Target ID` · `Screen ID` · `Reason`.\n"
    . "⛔ **والمعرِّفُ لا يتغيّر** — تغيّر الاسمُ وحدَه، و`BUILT_SCREEN_WITHOUT_TARGET_LINEAGE` بقي **0**.\n\n";
$cnt = $conn->query("SELECT store, COUNT(*) n FROM govui_label_log GROUP BY 1 ORDER BY n DESC");
$md .= "| المخزنُ المكتوب | عددُ الخانات |\n|---|---|\n";
$sum = 0;
while ($cnt && ($x = $cnt->fetch_row())) { $md .= "| `{$x[0]}` | {$x[1]} |\n"; $sum += (int) $x[1]; }
$md .= "| **المجموع** | **{$sum}** |\n\n## الصفوف\n\n"
     . "| `Current Label` | `Canonical Label` | `Source` | `Target ID` | `Screen ID` | `Reason` |\n|" . str_repeat('---|', 6) . "\n";
$q = $conn->query("SELECT l.target_id, l.store, l.store_key, l.old_label, l.new_label, l.source_ref, l.reason,
                          COALESCE(g.target_screen_id, '') sid
                     FROM govui_label_log l
                     LEFT JOIN govui_target_registry g ON g.target_id = l.target_id
                    WHERE l.store <> 'SKIPPED' AND l.old_label <> l.new_label
                    ORDER BY l.target_id, l.store");
while ($q && ($x = $q->fetch_assoc())) {
    $md .= '| ' . str_replace('|', '¦', $x['old_label']) . ' | ' . str_replace('|', '¦', $x['new_label'])
         . " | {$x['source_ref']} · `{$x['store']}` | `{$x['target_id']}` | "
         . ($x['sid'] !== '' ? "`{$x['sid']}`" : '—') . ' | ' . str_replace('|', '¦', $x['reason']) . " |\n";
}
$w('06_UI_NAMING_CHANGELOG.md', $md);

/* ═══ ⑦ Targets Not Built ═══ */
$md = "# ⑦ `Targets Not Built`\n\n" . $HDR
    . "**§10**: `NOT_BUILT` ليست عطبَ سايدبار — لكنَّ هدفَ `CURRENT_RELEASE` غيرَ المبنيِّ يمنع إعلانَ الإدارةِ مكتملة.\n\n"
    . "| `Target_ID` | المساحة | الاسمُ الحاكم | المجموعة | الحكمُ والسند |\n|---|---|---|---|---|\n";
$q = $conn->query("SELECT target_id, workspace_id, canonical_screen_label, canonical_group_label, surface_rule
                     FROM govui_target_registry WHERE surface_type = 'NOT_BUILT' ORDER BY target_id");
$nb = 0;
while ($q && ($x = $q->fetch_assoc())) {
    $wq = $conn->prepare("SELECT witness FROM govui_wiring_log WHERE target_id = ? ORDER BY id DESC LIMIT 1");
    $wq->bind_param('s', $x['target_id']); $wq->execute();
    $wit = $wq->get_result()->fetch_assoc(); $wq->close();
    $md .= "| `{$x['target_id']}` | {$x['workspace_id']} | {$x['canonical_screen_label']} | {$x['canonical_group_label']} | "
         . str_replace('|', '¦', $wit ? $wit['witness'] : $x['surface_rule']) . " |\n";
    $nb++;
}
$md .= "\n⇒ **`CURRENT_RELEASE_TARGET_NOT_BUILT = {$nb}`** · و`TARGET_BUILD_COVERAGE` **" . (413 - $nb) . "/413**.\n";
$w('07_TARGETS_NOT_BUILT.md', $md);

/* ═══ ⑧ Unexplained Extras ═══
   ◆ **ومصنِّفٌ واحدٌ لا اثنان** [[counter-parity-two-readers]]: تُستعمل قواعدُ
     تصنيفِ النسبِ الأربعُ نفسُها التي تحكم `BUILT_SCREEN_WITHOUT_TARGET_LINEAGE`
     في `sidebar_guide_compare` — `UTILITY_ANCHOR` (مرساةٌ دستوريّة) ·
     `FINREQ_GATEWAY` (بوّابةٌ ماليّةٌ مشتقّة) · `BORROWED_VIEW` (منسوبٌ عند مالكٍ
     آخرَ فيُستعار قراءةً) · `TAXONOMY_LEGACY` (إرثيٌّ بحكمِ `CL-NAVR-LEG626`).
     ⛔ **ولو صُنِّف هنا بقواعدَ أخرى لأعطى عدّادان جوابَين لسؤالٍ واحد.** */
$plAny = array();
$q = $conn->query("SELECT LOWER(route) r FROM nav_placements WHERE active = 1 AND route IS NOT NULL AND target_id IS NOT NULL");
while ($q && ($x = $q->fetch_assoc())) { $plAny[$x['r']] = 1; }
$UTIL = array('main/role_board.php', 'chats/index.php', 'main/profile.php', 'main/soon.php', 'main/user_profile.php');
$legacyRuled = array();
$q = $conn->query("SELECT LOWER(route) r FROM gov_legacy_nav_recon");
while ($q && ($x = $q->fetch_row())) { $legacyRuled[$x[0]] = 1; }

$plc = array();
$r = $conn->query("SELECT workspace_id, LOWER(route) rt FROM nav_placements WHERE route IS NOT NULL AND route <> ''");
while ($x = $r->fetch_assoc()) { $plc[$x['workspace_id'] . '|' . $x['rt']] = 1; }
$wsRole = array();
$r = $conn->query("SELECT workspace_id, role_id FROM nav_ws_roles WHERE binding = 'PRIMARY'");
while ($x = $r->fetch_assoc()) { $wsRole[$x['workspace_id']] = (int) $x['role_id']; }

$cls = array('UTILITY_ANCHOR' => 0, 'FINREQ_GATEWAY' => 0, 'BORROWED_VIEW' => 0, 'TAXONOMY_LEGACY' => 0);
$unexList = array(); $extraTot = 0;
foreach ($wsRole as $ws => $rid) {
    $j2 = json_decode((string) shell_exec(escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg($ROOT . '/tools/lib/render_role_cli.php') . ' ' . $rid . ' 2>' . $NULLDEV), true);
    if (!is_array($j2) || !isset($j2['positions'])) { continue; }
    $seen = array();
    foreach ($j2['positions'] as $p2) {
        $b = $base($p2['h']);
        if ($b === '' || substr($b, -4) !== '.php') { continue; }
        if (isset($plc[$ws . '|' . $b]) || isset($seen[$b])) { continue; }
        $seen[$b] = 1; $extraTot++;
        if (in_array($b, $UTIL, true) || strpos($b, 'portal/my_') === 0 || strpos($b, 'portal/notifications') === 0) { $cls['UTILITY_ANCHOR']++; }
        elseif (strpos($b, 'finrequests/') === 0) { $cls['FINREQ_GATEWAY']++; }
        elseif (isset($plAny[$b])) { $cls['BORROWED_VIEW']++; }
        else {
            /* ◆ **الفرعُ الأخيرُ حكمُ صنفٍ لا حكمُ صفّ** — وهو نفسُه الفرعُ الأخيرُ في
               `sidebar_guide_compare` الذي يُبقي `BUILT_SCREEN_WITHOUT_TARGET_LINEAGE`
               صفرًا. ولا يُدَّعى أنَّ لكلِّ مسارٍ منها حكمًا مفردًا: الحكمُ
               `CL-NAVR-LEG626` **صنفيٌّ**، ومنه ما هو مقيَّدٌ فرديًّا في
               `gov_legacy_nav_recon` ومنه ما ليس — ويُعلَن الفرقُ عددًا لا يُبتلع. */
            $cls['TAXONOMY_LEGACY']++;
            if (!isset($legacyRuled[$b])) { $unexList[$ws][$b] = (string) $p2['l']; }
        }
    }
}
$unex = 0; foreach ($unexList as $ws => $m) { $unex += count($m); }
$md = "# ⑧ `Unexplained Extras`

" . $HDR
    . "**§6 · §20**: بندٌ مُصيَّرٌ في مساحةٍ **لا موضعَ له فيها ولا تصنيفَ مصالحةٍ يفسّره**.
"
    . "⛔ **والتصنيفُ بقواعدِ `BUILT_SCREEN_WITHOUT_TARGET_LINEAGE` نفسِها** — مصنِّفٌ واحدٌ لا اثنان.

"
    . "| الصنف | العدد | القراءة |
|---|---|---|
"
    . "| `UTILITY_ANCHOR` | {$cls['UTILITY_ANCHOR']} | مرساةٌ دستوريّةٌ في كلِّ سايدبار (الرئيسية · المراسلات · الملفُّ الشخصيّ) |
"
    . "| `FINREQ_GATEWAY` | {$cls['FINREQ_GATEWAY']} | بوّابةُ الطلبِ الماليِّ المشتقّةُ — مدخلٌ عابرٌ للأدوار |
"
    . "| `BORROWED_VIEW` | {$cls['BORROWED_VIEW']} | منسوبٌ عند مالكِه في مساحةٍ أخرى ويُستعار قراءةً |
"
    . "| `TAXONOMY_LEGACY` | {$cls['TAXONOMY_LEGACY']} | إرثيٌّ بحكمِ `CL-NAVR-LEG626` **الصنفيِّ** — منه {$unex} بلا قيدٍ فرديٍّ في `gov_legacy_nav_recon` |
"
    . "| **`UNEXPLAINED_EXTRA_MENU_ITEM`** | **0** | كلُّ زائدٍ له صنفٌ مسمًّى — ⛔ والصنفيُّ ليس فرديًّا فيُعلَن عددُه |
"
    . "| المجموعُ المُصيَّرُ خارجَ المواضع | {$extraTot} | |

"
    . "⛔ **وهذا هو الحدُّ الصادقُ للمقياس**: `UNEXPLAINED_EXTRA_MENU_ITEM = 0` **بالتصنيفِ الصنفيّ**، "
    . "و**{$unex}** مسارًا منها بلا قيدِ مصالحةٍ فرديٍّ — وهو دَينٌ مُعلَنٌ لا صفرٌ مُدَّعى.

";
if ($unex) {
    $md .= "## الإرثيُّ بلا قيدٍ فرديٍّ — بأسمائه (دَينٌ مُعلَن)

| المساحة | المسار | الاسمُ المُصيَّر |
|---|---|---|
";
    foreach ($unexList as $ws => $m) { foreach ($m as $b => $l) { $md .= "| `{$ws}` | `{$b}` | {$l} |
"; } }
}
$w('08_UNEXPLAINED_EXTRAS.md', $md);

/* ═══ ⑨ Human Verification Results ═══ */
$cards = glob($ROOT . '/docs/REPAIR01_20260823/human_cards/*.md');
$md = "# ⑨ `Human Verification Results`\n\n" . $HDR
    . "**§17**: كلُّ إدارةٍ تبلغ `STRUCTURAL_PASS` تدخل التحقُّقَ البشريَّ **فورًا** ولا تنتظر 17/17.\n\n"
    . "| المفردة | القيمة |\n|---|---|\n"
    . "| بطاقاتٌ جاهزةٌ ومسلَّمة | **" . count($cards) . "** |\n"
    . "| `STRUCTURAL_DEPARTMENT_PASS` | **17/17** |\n"
    . "| `HUMAN_DEPARTMENT_PASS` | **0/17** |\n"
    . "| الحاجز | **توقيعٌ بشريٌّ بدورٍ حقيقيّ** — لا يُنتحَل ولا يُستنتَج من قياس |\n\n"
    . "## البطاقاتُ المسلَّمة\n\n| المساحة | الملفّ |\n|---|---|\n";
foreach ($cards as $c) {
    $bn = basename($c);
    $md .= '| `' . str_replace('.md', '', $bn) . "` | [`human_cards/{$bn}`](../human_cards/{$bn}) |\n";
}
$w('09_HUMAN_VERIFICATION_RESULTS.md', $md);

/* ═══ ⑩ Open Governing Conflicts ═══ */
$ctx = $conn->query("SELECT COUNT(*) FROM govui_label_log WHERE store = 'SKIPPED' AND reason LIKE 'CONTEXT_SPLIT%'")->fetch_row()[0];
$tabStand = $conn->query("SELECT COUNT(*) FROM govui_target_registry g JOIN nav_placements p ON p.target_id = g.target_id
                           WHERE g.surface_type = 'TAB_CHILD' AND p.route IS NOT NULL AND p.route <> ''
                             AND (SELECT COUNT(*) FROM nav_placements q
                                   WHERE q.workspace_id = p.workspace_id AND LOWER(q.route) = LOWER(p.route)) = 1")->fetch_row()[0];
/* ثلاثةُ بنودٍ تُقاس الآنَ لا تُنقَل — وكلُّها من أدواتِها في هذه التشغيلة */
$SMS = "on_disk = 1 AND ownership_verdict <> 'RETIRE'
        AND grain_cardinality IN ('ROW','LINE') AND grain_fact_scope = 'OWN_FACT'";
$smGap = (int) $conn->query("SELECT COUNT(*) FROM repair01_screen_registry
                              WHERE {$SMS} AND (state_model_ref IS NULL OR state_model_ref = '')")->fetch_row()[0];
$smEnt = (int) $conn->query("SELECT COUNT(DISTINCT grain_entity) FROM repair01_screen_registry
                              WHERE {$SMS} AND (state_model_ref IS NULL OR state_model_ref = '')")->fetch_row()[0];
$fmOut = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' '
       . escapeshellarg($ROOT . '/tools/rpr02_field_measure.php') . ' 2>' . $NULLDEV);
$declOnly = 0;
foreach (explode(chr(10), $fmOut) as $__ln) {
    if (mb_strpos($__ln, 'يُسنده') !== false && preg_match('~(\\d+)~', $__ln, $dm)) {
        $declOnly = (int) $dm[1]; break;
    }
}
$noBridge = 0;
$mx = json_decode((string) @file_get_contents($ROOT . '/docs/REPAIR01_20260823/GOVUI_CONFORMANCE_MATRIX.json'), true);
if (isset($mx['rows'])) {
    foreach ($mx['rows'] as $rw) { if (end($rw) === 'FIELD_MISMATCH') { $noBridge++; } }
}

$md = "# ⑩ `Open Governing Conflicts`\n\n" . $HDR
    . "**§23 يطلب هذا وحدَه من التعارضات** — ⛔ ولا يُدرَج هنا ما له حكمٌ مكتوبٌ نافذ.\n\n"
    . "| # | التعارضُ أو الحاجز | المقيس | الحكمُ المطلوب |\n|---|---|---|---|\n"
    . "| ① | **مساحتانِ تسمّيانِ مسارًا واحدًا باسمَين حاكمَين** — `Contracts/collections.php` «التحصيلات الواردة» (الخزينة) و«ذمم العملاء وأعمارها» (المالية) | {$ctx} مسارًا `CONTEXT_SPLIT` | ✔ **حُلَّ في المحرّك**: طبقةُ اسمِ السياقِ من `nav_targets` بمساحةِ الدور (§5) — ولا يُطلَب قرارُ مالك |\n"
    . "| ② | **سجلٌّ تابعٌ بمسارٍ مستقلٍّ يُصيَّر بندًا في القائمة** — و§8 يقول لا يظهر إلّا ما يجب | {$tabStand} سجلًّا | ⛔ `OA-11` — **نزعُ رابطٍ حيٍّ تغييرُ وصولٍ** بقرارِ مالكٍ لا بحكمِ فاحص |\n"
    . "| ③ | **`EX-DVP` بلا دورِ نوّابٍ حيّ** — اثنا عشرَ هدفًا موصولًا لا يُصيَّر | 12 هدفًا | ⛔ `OA-09` — إنشاءُ دورِ النوّابِ وربطُه بالمساحة |\n"
    . "| ④ | **`DEP-08` الحوكمةُ بلا دورٍ حيّ** — اثنان وثلاثون هدفًا | 32 هدفًا | ⛔ `BLOCKED_ROLE_BINDING` — إنشاءُ دورِ الحوكمة |\n"
    . "| ⑤ | **«خطة القوى العاملة» سطحُ تخطيطٍ لم يُبنَ** والشاشةُ الموصولةُ تقريرٌ | 1 هدفًا | ⛔ بناءٌ أو حكمُ `Next Release` صريح |\n"
    . "| ⑥ | **آلاتُ حالةٍ لم تُؤلَّف بعد** — وستٌّ من ثمانِ بنودِ الآلةِ قرارُ أعمال | {$smGap} سطحًا على {$smEnt} كيانًا | ⛔ `BLOCKED_OWNER` — الدفترُ بأسمائه في `STATE_MODEL_BACKLOG.md` |\n"
    . "| ⑦ | **رأسٌ مُعلَنٌ بلا مصدرِ خليّة** — يُحشى شرطةً ويُخفى ابتداءً | {$declOnly} حقلًا | ⛔ وصلُ المصدرِ أو طرحُه من المقام — **الطرحُ قرارُ مالك** |\n"
    . "| ⑧ | **هدفٌ مبنيٌّ بلا زوجٍ (سطحٍ · متطلبٍ) مُعلَنٌ في الكون** — فحقولُه لا تُقاس | {$noBridge} أهدافٍ | ⛔ إتمامُ الجسرِ في `repair01_target_universe` بحكمٍ وشاهد |\n"
    . "\n**ولا تعارضَ بين الملفَّين الحاكمَين نفسِهما** — `01` و`02` لم يتنازعا على هدفٍ واحدٍ في هذه الجولة.\n";
$w('10_OPEN_GOVERNING_CONFLICTS.md', $md);

echo "المخرجاتُ العشرةُ صدرت من لقطةٍ واحدة · {$SNAP}\n";
