<?php
/**
 * tools/navarch/retire.php — مراحلُ التقاعدِ الخمس (§33) بأدلّتِها المقيسة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **§33 حرفًا**: `A` القديمُ والجديدُ موجودانِ مع وسمِ القديم · `B` القديمُ
 *   يُحوِّل إلى الجديد · `C` إزالةُ القديمِ من `Sidebar` · `D` إيقافُ المسارِ
 *   القديمِ **إن لم تبقَ تبعيّة** · `E` دليلُ التقاعد.
 *
 * ◆ **ولا تُرفَع مرحلةٌ إلّا بدليلٍ مقيسٍ في هذا التشغيل** — ⛔ ولا واحدةٌ
 *   تُمنَح بالنيّة. وكلُّ صفٍّ يحمل سببَ توقُّفِه عند مرحلتِه.
 *
 * ◆ **وستُّ التبعيّاتِ تُقاس واحدةً واحدة** (§33): المفضّلات · الروابطُ
 *   الداخليّة · المهامّ · الإشعارات · التقارير · التكاملات. **وما لا سجلَّ له
 *   يُعلَن `NO_REGISTER` ولا يُقرأ صفرًا** — §32 حرفًا: «غيابُ القياسِ ليس
 *   غيابَ استعمال» [[measure-token-must-exist]]. ⇒ **و`NO_REGISTER` يمنع `D`**:
 *   إيقافُ مسارٍ لا نعلم من يناديه **قرارٌ بلا دليل**.
 *
 * التشغيل:
 *   php tools/navarch/retire.php            يقيس ويعرض ولا يكتب
 *   php tools/navarch/retire.php --apply    يكتب `retire_stage`
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__, 2));
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }   /* [[journey-bar]] config يبتلع مخرَجَ CLI */
require_once $ROOT . '/includes/navarch_renderer.php';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);

/* ═══ ① التبعيّاتُ الستُّ — كلٌّ بسجلِّها أو بغيابِه مُعلَنًا ═══════════════ */
$hasTable = function ($t) use ($conn) {
    $q = @$conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
    return $q && $q->num_rows > 0;
};
$hasCol = function ($t, $c) use ($conn) {
    $q = @$conn->query("SHOW COLUMNS FROM `{$t}` LIKE '" . $conn->real_escape_string($c) . "'");
    return $q && $q->num_rows > 0;
};

/* ⛔ **و«لا سجلَّ» بعد فحصِ اسمٍ واحدٍ حكمٌ سابقٌ لأوانه** [[evidence-one-join-away]]:
   `tkt_notifications` و`trs_notifications` عمودُهما `link_url` لا `link`،
   وجدولُ المهامِّ `task_templates` لا `my_tasks` — فبحثٌ باسمٍ واحدٍ يُعلن
   `NO_REGISTER` **وهو عمى قارئ**. ⇒ **تُفتَّش أسماءٌ عدّةٌ ويُسمّى ما وُجد**،
   وهي القائمةُ نفسُها التي يقرؤها `outputs.php` فلا يتناقض تقريران. */
$notifCols = array();
foreach (array(array('personal_notifications', 'link'), array('fin_notifications', 'link'),
               array('tkt_notifications', 'link_url'), array('trs_notifications', 'link_url'),
               array('tkt_notifications', 'link'), array('trs_notifications', 'link')) as $tc) {
    list($t, $c) = $tc;
    if (!isset($notifCols[$t]) && $hasTable($t) && $hasCol($t, $c)) { $notifCols[$t] = $c; }
}
/* ◆ **وجدولٌ بلا عمودِ وجهةٍ يُفتَّش نصُّه كلُّه** — بالقاعدةِ نفسِها التي
     يطبّقها `outputs.php`: تُجمَع أعمدةُ النصِّ في تعبيرٍ واحدٍ ويُبحَث فيه.
     ⛔ فغيابُ عمودٍ اسمُه `link` **ليس غيابَ تبعيّة**. */
$taskCols = array();
foreach (array('task_templates', 'my_tasks', 'task_assignments', 'recurring_tasks', 'wfm_tasks') as $t) {
    if (!$hasTable($t)) { continue; }
    $expr = null;
    foreach (array('link', 'link_url', 'route', 'url', 'href', 'target_route') as $c) {
        if ($hasCol($t, $c)) { $expr = '`' . $c . '`'; break; }
    }
    if ($expr === null) {
        $cc = array();
        $q = @$conn->query("SHOW COLUMNS FROM `{$t}`");
        while ($q && ($xx = $q->fetch_assoc())) {
            if (preg_match('~char|text~i', (string) $xx['Type'])) { $cc[] = '`' . $xx['Field'] . '`'; }
        }
        if ($cc) { $expr = "CONCAT_WS(' '," . implode(',', $cc) . ')'; }
    }
    if ($expr !== null) { $taskCols[$t] = $expr; }
}
/* المفضّلاتُ والتقاريرُ والتكاملاتُ — سجلٌّ أو لا سجلّ */
$favTable = null;
foreach (array('favorites', 'user_favorites', 'bookmarks', 'nav_favorites') as $t) {
    if ($hasTable($t)) { $favTable = $t; break; }
}
$reportCols = array();
foreach (array('report_definitions', 'emsreports_registry', 'gov_report_registry',
               'exec_daily_report', 'exec_weekly_report', 'gov_integrity_report') as $t) {
    if (!$hasTable($t)) { continue; }
    foreach (array('route', 'link', 'link_url', 'url') as $c) {
        if ($hasCol($t, $c)) { $reportCols[$t] = $c; break; }
    }
}
$intgTable = null;
foreach (array('integrations', 'webhooks', 'api_clients', 'ems_integrations') as $t) {
    if ($hasTable($t)) { $intgTable = $t; break; }
}

/* ═══ ② مرشَّحو التقاعد — لهم خَلَفٌ مُعلَن ═════════════════════════════════ */
$succ = array();
$r = $conn->query("SELECT LOWER(old_route) o, new_route n FROM nav_redirects WHERE active = 1");
while ($r && ($x = $r->fetch_assoc())) { $succ[navarch_norm_route($x['o'])] = $x['n']; }
$r = $conn->query("SELECT LOWER(route) o, merge_into n FROM nav_canonical
                    WHERE merge_into IS NOT NULL AND merge_into <> ''");
while ($r && ($x = $r->fetch_assoc())) {
    $k = navarch_norm_route($x['o']);
    if (!isset($succ[$k])) { $succ[$k] = $x['n']; }
}

/* المواضعُ الحاكمةُ التي تدخل السايدبار — دليلُ المرحلة `C` */
$inSidebar = array();
$r = $conn->query("SELECT LOWER(route) rt FROM nav_workspace_placements
                    WHERE status = 'ACTIVE' AND placement_type IN ('PRIMARY','SECONDARY_APPROVED')
                      AND route IS NOT NULL AND route <> ''");
while ($r && ($x = $r->fetch_row())) { $inSidebar[$x[0]] = 1; }

$rows = array();
$r = $conn->query("SELECT legacy_item_id, current_route, current_workspace, disposition, action,
                          replacement_screen_id, retire_stage
                     FROM nav_legacy_disposition
                    WHERE action IN ('REDIRECT','REPLACE') AND current_route IS NOT NULL");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }

/* ══ الروابطُ الداخليّةُ — تُقاس على شجرةِ الإنتاجِ **ملفًّا ملفًّا** ═══════════
   ⛔ **ولا يُجمَع النصُّ كلُّه في متغيّرٍ واحد**: شجرةُ الإنتاجِ مئاتُ الميغابايت،
     وجمعُها يُنهي الذاكرةَ **فتموت الأداةُ بلا مخرَجٍ ولا رسالة** — صفرٌ يُقرأ
     «لا تبعيّة» وهو انهيارٌ صامت [[measure-token-must-exist]].
   ⇒ يُقرأ الملفُّ ويُفحَص ثمَّ **يُنسى**، والمطلوبُ مجموعةٌ صغيرةٌ من المسارات. */
$cand = array();
foreach ($rows as $x) { $cand[navarch_norm_route($x['current_route'])] = 0; }
$skip = array('.git', 'node_modules', 'vendor', 'docs', 'tests', 'tools', 'database', '.ssdiff');
$files = 0;
$rii = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS),
        function ($f, $k, $it) use ($skip) {
            $n = $f->getFilename();
            if ($f->isDir()) { return !in_array($n, $skip, true) && $n[0] !== '.'; }
            return substr($n, -4) === '.php';
        }
    )
);
foreach ($rii as $f) {
    $src = @file_get_contents($f->getPathname());
    if ($src === false) { continue; }
    $files++;
    $src = mb_strtolower($src);
    foreach ($cand as $rt => $n) {
        if ($n === 0 && strpos($src, $rt . '.php') !== false) { $cand[$rt] = 1; }
    }
    unset($src);
}

echo "══ NAV-ARCH-02 §33 — مراحلُ التقاعدِ بأدلّتِها ══\n";
echo "  مرشَّحون (‏حكمُهم REDIRECT/REPLACE): " . count($rows) . "\n";
echo "  ملفَّاتُ الإنتاجِ الممسوحةُ للروابطِ الداخليّة: {$files}\n\n";

/* ═══ ③ التبعيّاتُ الستُّ — حالةُ سجلِّ كلٍّ ════════════════════════════════ */
$DEP = array(
    'المفضّلات'         => $favTable   ? 'REGISTER:' . $favTable        : 'NO_REGISTER',
    'الروابطُ الداخليّة' => 'MEASURED:' . $files . ' ملفًّا',
    'المهامّ'           => $taskCols   ? 'REGISTER:' . implode(',', array_keys($taskCols)) : 'NO_REGISTER',
    'الإشعارات'        => $notifCols  ? 'REGISTER:' . implode(',', array_keys($notifCols)) : 'NO_REGISTER',
    'التقارير'         => $reportCols ? 'REGISTER:' . implode(',', array_keys($reportCols)) : 'NO_REGISTER',
    'التكاملات'        => $intgTable  ? 'REGISTER:' . $intgTable        : 'NO_REGISTER',
);
echo "  ── تبعيّاتُ §33 الستّ ──\n";
$noReg = array();
foreach ($DEP as $k => $v) {
    printf("     %-20s %s\n", $k, $v);
    if ($v === 'NO_REGISTER') { $noReg[] = $k; }
}
echo "\n";

/* ═══ ④ حكمُ المرحلةِ لكلِّ صفّ ═════════════════════════════════════════════ */
/* ⛔ **ومفرداتُ المرحلةِ تُقرأ من المخطَّطِ لا تُكتب هنا**: العمودُ
   `enum('NONE','A_COEXIST','B_REDIRECT','C_OUT_OF_SIDEBAR','D_ROUTE_OFF','E_EVIDENCE')`،
   وكتابةُ الحرفِ المجرَّدِ «C» **تبتلعها MySQL إلى سلسلةٍ فارغةٍ صامتةً**
   فتُقرأ ثلاثةٌ وستّون صفًّا بلا مرحلةٍ وهي مُرحَّلة
   [[employee-classification-axis]] · [[enum-vocabulary-consumers]].
   ⇒ **يُشتقُّ الاسمُ الكاملُ من المخطَّطِ نفسِه**، وإن تغيّرت المفرداتُ
   انكسر الاشتقاقُ صراحةً بدل أن يكتب فراغًا. */
$STAGE = array('NONE' => 'NONE');
$q = $conn->query("SHOW COLUMNS FROM nav_legacy_disposition LIKE 'retire_stage'");
if ($q && ($qx = $q->fetch_assoc()) && preg_match_all("~'([^']+)'~", $qx['Type'], $mm)) {
    foreach ($mm[1] as $v) { $STAGE[substr($v, 0, 1)] = $v; }
}
foreach (array('A', 'B', 'C', 'D', 'E') as $L) {
    if (!isset($STAGE[$L])) { exit("⛔ مفردةُ المرحلةِ {$L} غائبةٌ عن ENUM — لا يُكتب حرفٌ مجرَّد
"); }
}
$stageCnt = array('NONE' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0);
$why = array();
$upd = $conn->prepare("UPDATE nav_legacy_disposition SET retire_stage = ? WHERE legacy_item_id = ?");
$applied = 0;

foreach ($rows as $x) {
    $rt = navarch_norm_route($x['current_route']);
    $stage = 'NONE'; $reason = '';

    /* ── A: القديمُ والجديدُ موجودانِ **والقديمُ موسوم** ─────────────────── */
    $hasSucc = isset($succ[$rt]);
    $oldFile = is_file($ROOT . '/' . $rt . '.php');
    if (!$hasSucc) { $reason = 'لا خَلَفَ مُعلَنٌ في nav_redirects ولا merge_into'; }
    else {
        $stage = 'A';
        /* ── B: القديمُ **يُحوِّل فعلًا** — يُقاس في نصِّ الملفِّ نفسِه ──────── */
        $redirects = false;
        if ($oldFile) {
            $src = mb_strtolower((string) @file_get_contents($ROOT . '/' . $rt . '.php'));
            $redirects = (strpos($src, 'header(') !== false && strpos($src, 'location') !== false)
                      || strpos($src, 'ems_nav_redirect') !== false;
        } elseif (!$oldFile) {
            $redirects = true;   /* لا ملفَّ ⇒ المسارُ لا يُفتح أصلًا */
        }
        if (!$redirects) { $reason = 'الملفُّ القديمُ قائمٌ ولا يُحوِّل — B غيرُ مُثبَتة'; }
        else {
            $stage = 'B';
            /* ── C: **أُزيل من السايدبار** — لا موضعَ حاكمًا يدخل القائمة ──── */
            if (isset($inSidebar[$rt])) { $reason = 'ما يزال له موضعٌ حاكمٌ يدخل السايدبار'; }
            else {
                $stage = 'C';
                /* ── D: **إيقافُ المسار** — لا تبعيّةَ باقية ─────────────── */
                $hits = array();
                foreach ($notifCols as $t => $cc) {
                    $q = @$conn->query("SELECT COUNT(*) FROM `{$t}` WHERE LOWER(`{$cc}`) LIKE '%"
                                     . $conn->real_escape_string($rt) . "%'");
                    if ($q && (int) $q->fetch_row()[0] > 0) { $hits[] = 'إشعارات:' . $t; }
                }
                foreach ($taskCols as $t => $expr) {
                    $q = @$conn->query("SELECT COUNT(*) FROM `{$t}` WHERE LOWER({$expr}) LIKE '%"
                                     . $conn->real_escape_string($rt) . "%'");
                    if ($q && (int) $q->fetch_row()[0] > 0) { $hits[] = 'مهامّ:' . $t; }
                }
                if (!empty($cand[$rt])) { $hits[] = 'روابطُ داخليّةٌ في الشيفرة'; }
                if ($hits) {
                    $reason = 'تبعيّاتٌ باقيةٌ مقيسة: ' . implode(' · ', $hits);
                } elseif ($noReg) {
                    /* ⛔ §32 — ما لا سجلَّ له لا يُقرأ صفرًا */
                    $reason = 'D محجوبة: بلا سجلٍّ يُقاس — ' . implode(' · ', $noReg);
                } else {
                    $stage = 'D';
                    $reason = 'E تحتاج دليلَ تقاعدٍ موقَّعًا (§33-E)';
                }
            }
        }
    }
    $stageCnt[$stage]++;
    $why[$stage][] = $x['current_route'] . ($reason !== '' ? ' — ' . $reason : '');
    if ($APPLY) {
        $sv = $STAGE[$stage];
        $upd->bind_param('ss', $sv, $x['legacy_item_id']);
        if ($upd->execute()) { $applied += $upd->affected_rows; }
    }
}
$upd->close();

echo "  ── المراحلُ المقيسة ──\n";
foreach ($stageCnt as $s => $n) { printf("     %-6s %d\n", $s, $n); }
echo "\n  ── سببُ التوقُّفِ عند كلِّ مرحلةٍ (‏عيّنة) ──\n";
foreach ($why as $s => $list) {
    printf("     %s (%d):\n", $s, count($list));
    foreach (array_slice($list, 0, 3) as $l) { echo "        · {$l}\n"; }
}
if ($APPLY) { echo "\n  ⇒ كُتب `retire_stage` في {$applied} صفًّا\n"; }
else { echo "\n  ◆ قياسٌ فقط — أضِف `--apply` للكتابة\n"; }

$out = $ROOT . '/docs/REPAIR01_20260823/navarch/NAV_RETIREMENT_LEDGER.json';
file_put_contents($out, json_encode(array(
    'measured_at' => date('c'), 'candidates' => count($rows),
    'stages' => $stageCnt, 'dependencies' => $DEP, 'no_register' => $noReg,
    'files_scanned' => $files
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "  ⇒ {$out}\n";
exit(0);
