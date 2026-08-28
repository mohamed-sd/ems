<?php
/**
 * tools/repair01_w2_gate.php
 *   بوّابةُ المرحلةِ الثانية — REPAIR01 · السجلُّ المعياريُّ والسايدبار
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تُعيد القياسَ ولا تقرأ ما خزَّنَته الأداة** (‏_CONTEXT §قواعد القياس ١):
 *   كلُّ حاجبٍ هنا يمسح **القرصَ والحيَّ** بنفسِه ثمّ يقارن بما في السجلّ.
 *   ولا حاجبَ واحدٌ يقرأ مخرَجَ `repair01_w2_apply.php`.
 *
 * ◆ **والمقامُ كاملٌ لا مختار** (§قواعد القياس ٤): مقامُ الشاشاتِ اتحادُ ثلاثةِ
 *   مصادرَ — دفترُ الأسطحِ · القرصُ · التنقّلُ الحيّ. والاقتصارُ على أوّلِها
 *   يجعل «١٠٠٪» صحيحةً على مقامٍ اختاره الفاحصُ لنفسِه.
 *
 * ◆ **والرسوُّ على البنيةِ لا العبارة** (§٣): الحواجبُ ترسو على أسماءِ
 *   الأصنافِ والأعمدةِ والمفاتيحِ، وتُطبع برموزِها (`✘ W2-nn`) — فنصُّ حالةِ
 *   الخطأِ لا يطابق رمزًا.
 *
 * التشغيل: php tools/repair01_w2_gate.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w2_scan.php';
require_once $ROOT . '/tools/lib/repair01_debt_scan.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
/* مرساةُ الطورِ صفرِ — **حقيقةٌ مسجَّلةٌ لا ثابتٌ حرفيّ** (RPR-AMD01) */
require_once __DIR__ . '/lib/repair01_w00_anchor.php';
$W00 = w00_anchors($conn);


$PASS = 0; $FAIL = 0; $LINES = array();
function gate($code, $title, $ok, $detail)
{
    global $PASS, $FAIL, $LINES;
    if ($ok) { $PASS++; $mark = '✔'; } else { $FAIL++; $mark = '✘'; }
    $LINES[] = '  ' . $mark . ' ' . str_pad($code, 9) . w2_pad($title, 34) . $detail;
}
/* PHP 8.3 يعرّف `mb_str_pad` — والاسمُ نفسُه هنا يسقط بـFatal. */
function w2_pad($s, $len)
{
    $n = mb_strlen($s, 'UTF-8');
    return $s . ($n < $len ? str_repeat(' ', $len - $n) : ' ');
}
function scalarq(mysqli $c, $sql)
{
    $r = $c->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x ? $x[0] : null;
}

echo "═══════════ بوّابةُ المرحلةِ الثانية — REPAIR01 · السجلُّ والسايدبار ═══════════\n";

/* ── القياسُ الحيُّ المُعاد ─────────────────────────────────────────────── */
$phpFiles = repair01_w2_php_files($ROOT);
$incMap   = repair01_w2_include_map($phpFiles);
$bearers  = repair01_w2_shell_bearers($incMap);
$live     = repair01_w2_live_screens($ROOT);

/* مقامُ الشاشاتِ المُعادُ اشتقاقُه — ثلاثةُ مصادرَ لا واحد */
$universe = array();     /* مفتاحٌ بحروفٍ صغيرة ⇐ [route, on_disk] */
$ghostFiles = array();
/* ⚠ **أداةٌ واحدةٌ بنطاقَين**: `repair01_w2_php_files()` يستثني `vendor/`
     و`storage/` و`api/` … **والمقامُ المبنيُّ من `repair01_surfaces` لا
     يستثني شيئًا**. فدخلت تسعةُ صفوفٍ من `vendor/phpoffice` مقامًا لا
     يراها الفاحصُ، **فظهر فرقٌ لا سببَ له في الواقع**: ملفٌّ «على القرصِ
     وغيرُ موسوم» وهو **صنفُ مكتبةٍ لا شاشة**.
   ⇒ **والمقامُ يُقيَّد بنطاقِ الأداةِ نفسِها** ⛔ لا بنطاقٍ ثانٍ يتفرّق عنه. */
$SKIPD = repair01_w2_skip_dirs();
$inScope = function ($path) use ($SKIPD) {
    $rel = ltrim(strtr((string) $path, '\\', '/'), '/');
    foreach ($SKIPD as $sd) {
        if (strpos($rel, $sd) === 0 || strpos($rel, '/' . $sd) !== false) { return false; }
    }
    return true;
};
$r = $conn->query("SELECT DISTINCT screen_file, disk_path, on_disk FROM repair01_surfaces");
while ($r && $x = $r->fetch_assoc()) {
    if (!$inScope($x['disk_path'])) { continue; }
    if ((int) $x['on_disk'] === 1) {
        $rt = repair01_w2_norm_route($x['disk_path']);
        $universe[strtolower($rt)] = array($rt, 1);
    } else {
        $ghostFiles[strtolower($x['screen_file'])] = $x['screen_file'];
    }
}
foreach ($live as $rel => $c2) { $universe[strtolower($rel)] = array($rel, 1); }
$navActive = array();
$r = $conn->query("SELECT DISTINCT route FROM nav_items WHERE active = 1 AND route <> ''");
while ($r && $x = $r->fetch_row()) {
    $rt = repair01_w2_norm_route($x[0]);
    if ($rt === '' || substr($rt, -4) !== '.php') { continue; }
    $navActive[strtolower($rt)] = $rt;
    if (!isset($universe[strtolower($rt)])) {
        $universe[strtolower($rt)] = array($rt, is_file($ROOT . '/' . $rt) ? 1 : 0);
    }
}
$expected = count($universe) + count($ghostFiles);

/* السجلُّ كما هو */
$reg = array(); $regGhost = array();
$r = $conn->query("SELECT screen_id, screen_file, route, route_rule, owner_code, owner_role, owner_rule,
                          lifecycle, lifecycle_rule, parent_screen_id, parent_rule, visibility_class,
                          visibility_rule, on_disk, ghost_verdict, ghost_why, guard_kind, guard_evidence
                   FROM repair01_screen_registry");
while ($r && $x = $r->fetch_assoc()) {
    if ($x['route'] !== null && $x['route'] !== '') { $reg[strtolower(repair01_w2_norm_route($x['route']))] = $x; }
    else { $regGhost[strtolower($x['screen_file'])] = $x; }
}
$regAll = count($reg) + count($regGhost);

/* ══ W2-01 · المُعرِّفُ المعياريُّ لكلِّ شاشةٍ في المقامِ المُعادِ اشتقاقُه ══ */
$missing = array();
foreach ($universe as $k => $v) { if (!isset($reg[$k])) { $missing[] = $v[0]; } }
foreach ($ghostFiles as $k => $f) { if (!isset($regGhost[$k])) { $missing[] = 'ghost:' . $f; } }
$noId = (int) scalarq($conn, "SELECT COUNT(*) FROM repair01_screen_registry WHERE screen_id NOT REGEXP '^SCR-[0-9]{4}$'");
gate('W2-01', 'Screen_ID لكلِّ شاشةٍ في المقام', count($missing) === 0 && $noId === 0,
     "المقامُ المُعادُ $expected · في السجلّ $regAll · ناقصٌ " . count($missing) . " · مُعرِّفٌ مخالفُ الصيغة $noId"
     . (count($missing) ? ' ⇐ ' . implode('، ', array_slice($missing, 0, 3)) : ''));

/* ══ W2-02 · مرجعُ دفترِ الأسطحِ إلى السجلِّ سليمٌ لكلِّ صفّ ═══════════════
   ◆ **ولماذا هذا لا «المُعرِّفُ فريد»**: تفرُّدُ `screen_id` يضمنه المفتاحُ
     الأساسيُّ وتفرُّدُ `route` يضمنه `uq_route` — وحاجبٌ يفحص ما يضمنه
     المخطَّطُ **لا يسقط أبدًا مهما كُسر**، وهو تعريفُ الحاجبِ الأعمى.
   ◆ **والقابلُ للكسرِ فعلًا** هو الوصلُ: `repair01_surfaces.screen_id`
     عمودٌ حرٌّ بلا مفتاحٍ أجنبيٍّ — ٦٦٤ صفًّا تشير إلى ٣٨٤ مُعرِّفًا، وصفٌّ
     واحدٌ يشير إلى مُعرِّفٍ لا وجودَ له يُيتِّم دورةً كاملة. */
$ids = array();
$r = $conn->query("SELECT screen_id FROM repair01_screen_registry");
while ($r && $x = $r->fetch_row()) { $ids[$x[0]] = true; }
$surfNoRef = (int) scalarq($conn, "SELECT COUNT(*) FROM repair01_surfaces WHERE screen_id = ''");
$surfBad = 0; $surfSplit = 0;
$r = $conn->query("SELECT screen_file, disk_path, on_disk, screen_id FROM repair01_surfaces WHERE screen_id <> ''");
$byKey = array();
while ($r && $x = $r->fetch_assoc()) {
    if (!isset($ids[$x['screen_id']])) { $surfBad++; }
    $k = ((int) $x['on_disk'] === 1)
        ? strtolower(repair01_w2_norm_route($x['disk_path']))
        : 'ghost:' . strtolower($x['screen_file']);
    $byKey[$k][$x['screen_id']] = true;
}
foreach ($byKey as $k => $set) { if (count($set) > 1) { $surfSplit++; } }
gate('W2-02', 'مرجعُ دفترِ الأسطحِ سليم', $surfNoRef === 0 && $surfBad === 0 && $surfSplit === 0,
     "بلا مرجعٍ $surfNoRef · مرجعٌ يتيمٌ $surfBad · شاشةٌ بمُعرِّفَين $surfSplit");

/* ══ W2-03 · المسارُ المبنيُّ ملفٌّ قائمٌ على القرص ══════════════════════ */
$badPath = 0; $badSample = '';
foreach ($reg as $k => $x) {
    if ((int) $x['on_disk'] !== 1) { continue; }
    if (!is_file($ROOT . '/' . $x['route'])) { $badPath++; if ($badSample === '') { $badSample = $x['route']; } }
}
$diskNotFlagged = 0;
foreach ($universe as $k => $v) {
    if ($v[1] === 1 && isset($reg[$k]) && (int) $reg[$k]['on_disk'] !== 1) { $diskNotFlagged++; }
}
gate('W2-03', 'المسارُ المبنيُّ ملفٌّ قائم', $badPath === 0 && $diskNotFlagged === 0,
     "مسارٌ بلا ملفّ $badPath" . ($badSample ? " ($badSample)" : '') . " · على القرصِ وغيرُ موسومٍ $diskNotFlagged");

/* ══ W2-04 · لا قيمةَ بلا قاعدةٍ مُعلَنة (ترتيبُ §٣٥ محفوظٌ عمودًا عمودًا) ══ */
$bare = (int) scalarq($conn, "SELECT COUNT(*) FROM repair01_screen_registry
    WHERE (route IS NOT NULL AND route_rule = '')
       OR owner_rule = '' OR lifecycle_rule = '' OR visibility_rule = ''
       OR (parent_screen_id <> '' AND parent_rule = '')");
gate('W2-04', 'لا قيمةَ بلا قاعدةٍ مُعلَنة', $bare === 0, "صفٌّ بقيمةٍ عاريةٍ من قاعدتِها $bare");

/* ══ W2-05 · المالكُ بمصدرٍ قائم — والمؤشِّرُ إلى قرارٍ يجب أن يجدَه ══════ */
$ptr = array();
$r = $conn->query("SELECT DISTINCT SUBSTRING(owner_rule, 13) d FROM repair01_screen_registry
                   WHERE owner_rule LIKE 'W2\\_DECISION:%'");
while ($r && $x = $r->fetch_row()) { $ptr[] = $x[0]; }
$dangling = 0;
foreach ($ptr as $d) {
    $n = (int) scalarq($conn, "SELECT COUNT(*) FROM repair01_w2_decisions
        WHERE decision_id = '" . $conn->real_escape_string($d) . "'
          AND question <> '' AND ruling <> '' AND rationale IS NOT NULL AND rationale <> ''");
    if ($n === 0) { $dangling++; }
}
$noOwnerSrc = (int) scalarq($conn, "SELECT COUNT(*) FROM repair01_screen_registry
    WHERE owner_code = '' AND owner_rule NOT LIKE 'W2\\_DECISION:%'");
gate('W2-05', 'المالكُ بمصدرٍ أو بقرارٍ قائم', $dangling === 0 && $noOwnerSrc === 0,
     'قراراتٌ مُشارٌ إليها ' . count($ptr) . " · معلَّقةٌ في الهواء $dangling · بلا مصدرٍ ولا قرارٍ $noOwnerSrc");

/* ══ W2-06 · الشبحُ محسومٌ كلُّه بحكمٍ وعذرٍ مكتوب ═══════════════════════ */
/* ⚠ **والنطاقُ يُطبَّق على الطرفَين**: قيَّدتُ المقامَ بنطاقِ الأداةِ أعلاه
     **وتركتُ عدَّ الأشباحِ بلا قيد** — فبقي طرفانِ يُقاسان بمسطرتَين.
     ⇒ **ومقارنةٌ بين مقامَين مختلفَي النطاقِ فرقُها مصنوعٌ لا حقيقيّ.** */
$ghostReg = 0; $ghostNoV = 0;
$gq = $conn->query("SELECT route, ghost_verdict, ghost_why FROM repair01_screen_registry WHERE on_disk = 0");
while ($gq && ($gy = $gq->fetch_assoc())) {
    if (!$inScope($gy['route'])) { continue; }
    $ghostReg++;
    if ((string) $gy['ghost_verdict'] === '' || (string) $gy['ghost_why'] === '') { $ghostNoV++; }
}
$ghostOnDsk = 0;
foreach ($regGhost as $k => $x) { if (isset($universe[$k])) { $ghostOnDsk++; } }
gate('W2-06', 'الشبحُ محسومٌ بحكمٍ وعذر', $ghostReg === count($ghostFiles) && $ghostNoV === 0 && $ghostOnDsk === 0,
     'الشبحُ المُعادُ قياسُه ' . count($ghostFiles) . " · في السجلّ $ghostReg · بلا حكمٍ $ghostNoV · موسومٌ شبحًا وهو على القرصِ $ghostOnDsk");

/* ══ W2-07 · المنقولُ إلى دفترِ الفجواتِ بموجتِه — والـ١٧٤ الأصليُّ سليم ══ */
$moved   = (int) scalarq($conn, "SELECT COUNT(*) FROM repair01_target_gaps WHERE origin_stage = 'W02'");
$noWave  = (int) scalarq($conn, "SELECT COUNT(*) FROM repair01_target_gaps
                                  WHERE origin_stage = 'W02' AND (wave_stage = '' OR origin_note = '')");
$orig    = (int) scalarq($conn, "SELECT COUNT(*) FROM repair01_target_gaps WHERE origin_stage = ''");
$toMove  = (int) scalarq($conn, "SELECT COUNT(*) FROM repair01_screen_registry
                                  WHERE on_disk = 0 AND ghost_verdict = 'MOVED_TO_TARGET_GAPS'");
gate('W2-07', 'المنقولُ بموجتِه والأصلُ سليم', $moved === $toMove && $noWave === 0 && $orig === $W00['gaps_original'],
     "منقولٌ $moved من $toMove · بلا موجةٍ $noWave · الفجواتُ الأصليّةُ $orig (يجب 174)");

/* ══ W2-08 · صفرُ بندِ قائمةٍ يدويٍّ في القشرة ═══════════════════════════ */
$manual = repair01_w2_manual_nav_items($ROOT);
$manualN = 0; $manualWhere = array();
foreach ($manual as $f => $routes) { $manualN += count($routes); $manualWhere[] = $f . ':' . count($routes); }
gate('W2-08', 'صفرُ بندِ قائمةٍ يدويّ', $manualN === 0,
     "مسارٌ حرفيٌّ في القشرة $manualN" . ($manualWhere ? ' ⇐ ' . implode('، ', $manualWhere) : ''));

/* ══ W2-09 · لا شاشةَ بلا حارسِ عرضٍ على الخادم ═════════════════════════ */
$noGuard = array();
foreach ($live as $rel => $clean) {
    $g = repair01_w2_guard($rel, $clean, $incMap[$rel] ?? array(), $bearers);
    if ($g['kind'] === 'NONE') { $noGuard[$rel] = $g['evidence']; }
}
/* ولا يكفي صفرُ «بلا حارس» على القرص: **كلُّ صفٍّ مبنيٍّ في السجلِّ يجب أن
   يحمل حكمَ حارسِه**. صفٌّ بحكمٍ فارغٍ يمرُّ في عدِّ القرصِ ولا يُقرأ في السجلّ
   — وهو ما وقع فعلًا حين أعاد الفحصُ السلبيُّ إدراجَ صفٍّ بأعمدتِه المفحوصةِ
   وحدَها فعاد **بلا `guard_kind`**: إرجاعٌ ناقصٌ لا يراه حاجبٌ لا يفحص العمود. */
$blankGuard = (int) scalarq($conn, "SELECT COUNT(*) FROM repair01_screen_registry
                                     WHERE on_disk = 1 AND guard_kind = ''");
gate('W2-09', 'لا شاشةَ بلا حارسِ خادم', count($noGuard) === 0 && $blankGuard === 0,
     'الشاشاتُ المقيسةُ ' . count($live) . ' · بلا حارسٍ ' . count($noGuard)
     . " · صفٌّ مبنيٌّ بحكمِ حارسٍ فارغٍ $blankGuard"
     . (count($noGuard) ? ' ⇐ ' . implode('، ', array_slice(array_keys($noGuard), 0, 3)) : ''));

/* ══ W2-10 · إخفاءُ الرابطِ لا يفتح المسارَ المباشر ═════════════════════
   كلُّ مسارٍ **أُطفئ صفُّه** أو صُنِّف `FORBIDDEN` في مساحةٍ يجب أن يكون خلفَ
   حارسٍ على الخادم. فالظهورُ ليس صلاحيةً (§٣٦) — وإخفاءٌ بلا حارسٍ ستارةٌ. */
$hiddenRoutes = array();
$r = $conn->query("SELECT DISTINCT route FROM nav_items WHERE active = 0 AND route <> ''");
while ($r && $x = $r->fetch_row()) { $hiddenRoutes[strtolower(repair01_w2_norm_route($x[0]))] = 1; }
$r = $conn->query("SELECT DISTINCT route FROM gov_space_appearances WHERE cls = 'FORBIDDEN' AND route <> ''");
while ($r && $x = $r->fetch_row()) { $hiddenRoutes[strtolower(repair01_w2_norm_route($x[0]))] = 1; }
$liveLc = array();
foreach ($live as $rel => $clean) { $liveLc[strtolower($rel)] = $rel; }
$curtain = array(); $hiddenBuilt = 0;
foreach ($hiddenRoutes as $k => $v) {
    if (!isset($liveLc[$k])) { continue; }       /* لا ملفَّ ⇒ لا مسارَ مباشرًا يُفتح */
    $hiddenBuilt++;
    $rel = $liveLc[$k];
    $g = repair01_w2_guard($rel, $live[$rel], $incMap[$rel] ?? array(), $bearers);
    if ($g['kind'] === 'NONE') { $curtain[] = $rel; }
}
gate('W2-10', 'المخفيُّ محروسٌ لا مستورٌ فقط', count($curtain) === 0,
     "مسارٌ مخفيٌّ مبنيٌّ $hiddenBuilt · بلا حارسٍ " . count($curtain)
     . (count($curtain) ? ' ⇐ ' . implode('، ', array_slice($curtain, 0, 3)) : ''));

/* ══ W2-11 · مِرساةُ القشرةِ لها صفٌّ في السجلِّ المعياريِّ للتنقّل ══════ */
$calls = array();
foreach (repair01_w2_shell_files() as $rel) {
    $p = $ROOT . '/' . $rel;
    if (!is_file($p)) { continue; }
    $clean = repair01_w2_strip_comments((string) file_get_contents($p));
    if (preg_match_all('~ems_nav_anchor(?:_li)?\s*\(\s*[^,]{1,40},\s*[\'"]([A-Z_]{2,24})[\'"]~', $clean, $m)) {
        foreach ($m[1] as $k) { $calls[$k] = true; }
    }
}
$hasCol = $conn->query("SHOW COLUMNS FROM `nav_canonical` LIKE 'anchor_key'");
$colOk = $hasCol && $hasCol->num_rows > 0;
$orphanAnchor = array();
if ($colOk) {
    foreach (array_keys($calls) as $k) {
        $n = (int) scalarq($conn, "SELECT COUNT(*) FROM nav_canonical
              WHERE anchor_key = '" . $conn->real_escape_string($k) . "' AND route <> ''");
        if ($n !== 1) { $orphanAnchor[] = $k; }
    }
}
gate('W2-11', 'المِرساةُ من السجلِّ لا من الشيفرة', $colOk && count($calls) > 0 && count($orphanAnchor) === 0,
     'مِرساةٌ منادَاةٌ ' . count($calls) . ' · بلا صفٍّ في nav_canonical ' . count($orphanAnchor)
     . ($colOk ? '' : ' · العمودُ anchor_key مفقود')
     . (count($orphanAnchor) ? ' ⇐ ' . implode('، ', $orphanAnchor) : ''));

/* ══ W2-12 · صنفُ الظهورِ يطابق الحيَّ — لا وسمَ بلا سند ════════════════ */
$menuNoNav = 0; $notBuiltOnDisk = 0; $navNotMenu = 0;
foreach ($reg as $k => $x) {
    if ($x['visibility_class'] === 'MENU_ITEM' && !isset($navActive[$k])) { $menuNoNav++; }
    if ($x['visibility_class'] === 'NOT_BUILT' && isset($universe[$k]) && $universe[$k][1] === 1) { $notBuiltOnDisk++; }
    if (isset($navActive[$k]) && !in_array($x['visibility_class'], array('MENU_ITEM', 'ANCHOR'), true)) { $navNotMenu++; }
}
gate('W2-12', 'صنفُ الظهورِ يطابق الحيّ', $menuNoNav === 0 && $notBuiltOnDisk === 0 && $navNotMenu === 0,
     "بندٌ بلا صفٍّ نشِطٍ $menuNoNav · «لم يُبنَ» وهو مبنيٌّ $notBuiltOnDisk · نشِطٌ وليس بندًا $navNotMenu");

/* ══ W2-13 · مخزنُ W00/W01 لم يُمَسَّ بهذه المرحلة ═══════════════════════ */
$d0 = (int) scalarq($conn, "SELECT COUNT(*) FROM repair01_decisions");
$s0 = (int) scalarq($conn, "SELECT COUNT(*) FROM repair01_source_files");
$u0 = (int) scalarq($conn, "SELECT COUNT(*) FROM repair01_surfaces");
$f0 = (int) scalarq($conn, "SELECT COUNT(*) FROM repair01_ownership WHERE classification = 'FORBIDDEN'");
$w1 = (int) scalarq($conn, "SELECT COUNT(*) FROM repair01_ownership WHERE classification = 'FORBIDDEN' AND w1_verdict IS NULL");
$cc = (int) scalarq($conn, "SELECT COUNT(*) FROM repair01_surfaces WHERE canonical_code IS NULL OR canonical_code = ''");
gate('W2-13', 'مخزنُ W00/W01 لم يُمَسّ', $d0 === $W00['decisions'] && $s0 === $W00['source_files'] && $u0 === $W00['surfaces'] && $f0 === $W00['ownership_forbidden'] && $w1 === 0 && $cc === 0,
     "قرارات $d0 · مصادر $s0 · أسطح $u0 · محرَّم $f0 · بلا حكمِ W01 $w1 · بلا رمزٍ $cc");

/* ══ W2-14 · الاستثناءُ مُعلَنٌ لا صامت ══════════════════════════════════
   صفٌّ في السجلِّ مسارُه على القرصِ **وليس شاشةً** استثناءٌ حقيقيّ — ومقامُ
   «المبنيِّ» يحمله. فإمّا أن يُعلَن بقرارٍ يحمل عددَه، أو يمرَّ صامتًا فيُقرأ
   الرقمُ صحيحًا وهو خطأ. والعددُ يُعاد قياسُه هنا من القرصِ لا من القرار. */
/* ⚠ المطابقةُ **بحروفٍ صغيرة**: `nav_items` تكتب `risk/dept_risk_space.php`
   والقرصُ يكتب `Risk/…` — ومطابقةٌ حسّاسةٌ للحالةِ تعدُّ غلافًا مشترَكًا
   محروسًا «ليس شاشةً» فيتفرَّق عددُ البوّابةِ عن عددِ الأداةِ بواحد. */
$phpLc = array();
foreach ($phpFiles as $rel => $clean) { $phpLc[strtolower($rel)] = $rel; }
$nsMeasured = 0; $nsSample = array();
foreach ($reg as $k => $x) {
    if ((int) $x['on_disk'] !== 1) { continue; }
    if (isset($liveLc[$k])) { continue; }                    /* شاشةٌ حيّة */
    if (isset($phpLc[$k])) {
        /* ملفٌّ إنتاجيٌّ لا يحمل قشرةً بنفسِه: مُحوِّلٌ أو غلافٌ — يُعذَر إن حُرس */
        $rel = $phpLc[$k];
        $g = repair01_w2_guard($rel, $phpFiles[$rel], $incMap[$rel] ?? array(), $bearers);
        if ($g['kind'] !== 'NONE') { continue; }
    }
    $nsMeasured++; if (count($nsSample) < 3) { $nsSample[] = $x['route']; }
}
$nsDecl = (int) scalarq($conn, "SELECT scope_rows FROM repair01_w2_decisions
                                 WHERE decision_id = 'W2-D-02' AND rationale IS NOT NULL AND rationale <> ''");
gate('W2-14', 'ما ليس شاشةً مُعلَنٌ بقرار', $nsMeasured === $nsDecl,
     "مقيسٌ $nsMeasured · مُعلَنٌ في W2-D-02 $nsDecl"
     . ($nsSample ? ' ⇐ ' . implode('، ', $nsSample) : ''));

echo implode("\n", $LINES) . "\n";
echo str_repeat('─', 78) . "\n";
$ghostUnresolved = $ghostNoV + $ghostOnDsk;
printf("W2 gate: %d/%d  ·  Screen_ID %s  ·  شبحٌ غيرُ محسوم %d  ·  بندُ قائمةٍ يدويّ %d  ·  شاشةٌ بلا حارسِ خادم %d\n",
    $PASS, $PASS + $FAIL,
    ($expected > 0 && count($missing) === 0) ? '100%' : sprintf('%.1f%%', 100 * ($expected - count($missing)) / max(1, $expected)),
    $ghostUnresolved, $manualN, count($noGuard));
echo 'الحكم: ' . ($FAIL === 0 ? "خضراء ✔\n" : "حمراء ✘\n");
exit($FAIL === 0 ? 0 : 1);
