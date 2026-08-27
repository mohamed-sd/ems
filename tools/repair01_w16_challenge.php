<?php
/**
 * tools/repair01_w16_challenge.php — المراجعةُ الثانيةُ كتحدٍّ مستقلّ · البندُ ٥٠
 * ═══════════════════════════════════════════════════════════════════════════
 * **نصُّ البند**: «شغّلْ «11 · المراجعة الثانية» كـ`Independent Challenge`،
 * **ولا تجعله يعيد استخدام نفس `Rule Engine` الذي بنى `Target`**. يجب أن
 * يستطيع إصدارَ `REDESIGN` **حتى إذا الاختباراتُ البنيويّةُ خضراء**».
 *
 * ◆ **فهذه الأداةُ لا تقرأ دفترَ موجةٍ ولا حكمَ بوّابة**. مصادرُها الأوّليّةُ
 *   وحدَها: **القرصُ** و**المخطَّطُ الحيُّ** و**المصنَّفاتُ المجمَّدة** و**سجلُّ
 *   الأصنافِ نفسُه**. ولو قرأت مخرَجَ ما تفحصه لصارت صدًى لا تحدّيًا.
 *
 * ◆ **ولكلِّ قاعدةٍ مقامُها المطبوع** — فـ«صفرٌ من مقامٍ مجهولٍ لا يُثبت شيئًا»،
 *   والقاعدةُ التي لا تستطيع أن تُخرِج غيرَ `ACCEPT` **زينةٌ لا مراجعة**.
 *
 * ◆ **والشدّةُ خاصّةُ القاعدةِ لا خاصّةُ النتيجة**: قواعدُ بنيويّةٌ تُصدر
 *   `REDESIGN` عند أوّلِ خرق، وقواعدُ فجوةٍ تُصدر `CONCERN` — **والتصنيفُ
 *   مكتوبٌ في القاعدةِ قبل أن تُقاس** فلا يُلطَّف بعد رؤيةِ الرقم.
 *
 * التشغيل: php tools/repair01_w16_challenge.php [--apply]
 * الخروج : 0 لا `REDESIGN` · 2 صدر `REDESIGN`
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { $r = @$conn->query($sql); return $r ? $r->fetch_row()[0] : null; };

$F = array();
/** قاعدةٌ تُسجَّل بشدّتِها المكتوبةِ سلفًا ومقامِها المطبوع. */
function ch($key, $title, $breaches, $den, $denName, $onBreach, $primary, $evidence)
{
    global $F;
    /* ⛔ **ومقامٌ خاوٍ ليس براءة**: قاعدةٌ تفحص مجموعةً فارغةً تُخرِج صفرَ خرقٍ
       **وهي لم تنظر**. فالخلاءُ يُعامَل خرقًا ويُعلَن بنصِّه لا يُخضَّر. */
    if ((int) $den === 0) {
        $sev = $onBreach;
        $evidence = 'مقامٌ خاوٍ — القاعدةُ لم تفحص شيئًا فلا تُخضَّر · ' . $evidence;
    } else {
        $sev = ($breaches > 0) ? $onBreach : 'ACCEPT';
    }
    $F[] = array(
        'key' => $key, 'title' => $title, 'sev' => $sev,
        'num' => (int) $breaches, 'den' => (int) $den, 'den_name' => $denName,
        'primary' => $primary, 'evidence' => $evidence,
    );
}

echo "═══ المراجعةُ الثانيةُ — تحدٍّ مستقلٌّ بمحرّكٍ مغاير · البندُ ٥٠ ═══\n";
echo "  المصادرُ الأوّليّةُ وحدَها: القرص · المخطَّط · المصنَّفاتُ المجمَّدة\n\n";

/* ═══ CH-01 · حارسُ خادمٍ يُقرأ من الملفِّ لا من عمودِ حكمٍ سابق ═══════════ */
$GUARD = '~(check_page_permissions|require_login|require_permission|checkPermission|ems_require_|can_view|hasPermission|\$_SESSION\s*\[\s*.user.)~i';
$incTargets = function ($file, $src) use ($ROOT) {
    preg_match_all('~(?:require|include)(?:_once)?\s*\(?\s*(?:__DIR__\s*\.\s*)?[\'"]([^\'"]+)[\'"]~i', $src, $m);
    $o = array(); $d = dirname($file);
    foreach ($m[1] as $t) {
        $t = ltrim($t, '/');
        foreach (array($d . '/' . $t, $ROOT . '/' . $t) as $p) {
            $rp = @realpath($p);
            if ($rp && is_file($rp)) { $o[$rp] = true; break; }
        }
    }
    return array_keys($o);
};
$liveRoutes = array();
$r = $conn->query("SELECT route FROM repair01_screen_registry
                    WHERE on_disk = 1 AND guard_kind <> 'NOT_A_SCREEN'
                      AND lifecycle IN ('LIVE_REGISTERED','LIVE_UNREGISTERED')");
while ($r && ($x = $r->fetch_row())) { $liveRoutes[] = $x[0]; }
$read = 0; $unguarded = array();
foreach ($liveRoutes as $rt) {
    $p = $ROOT . '/' . $rt;
    if (!is_file($p)) { continue; }
    $read++;
    $s = (string) @file_get_contents($p);
    $hit = (bool) preg_match($GUARD, $s);
    if (!$hit) {
        foreach ($incTargets($p, $s) as $t) {
            if (preg_match($GUARD, (string) @file_get_contents($t))) { $hit = true; break; }
        }
    }
    if (!$hit) { $unguarded[] = $rt; }
}
ch('CH-01', 'سطحٌ حيٌّ على القرصِ بلا حارسٍ خادميّ', count($unguarded), $read,
   'ملفّاتُ الأسطحِ الحيّةِ المقروءةُ من القرص', 'REDESIGN', 'القرص',
   'قراءةُ ' . $read . ' ملفًّا وتتبُّعُ تضميناتِها طبقةً واحدة'
   . ($unguarded ? ' ⇐ ' . implode('، ', array_slice($unguarded, 0, 3)) : ''));

/* ═══ CH-02 · صنفُ دَينٍ بلا استعلامِ قياس ═══════════════════════════════ */
$dcTot  = (int) $one("SELECT COUNT(*) FROM repair01_debt_register");
/* ⚠ **والقاعدةُ كانت أضيقَ من دعواها**: عنوانُها «غيرُ قابلٍ للقياس» وكانت تعدُّ
   **غيابَ الاستعلامِ وحدَه** — فترسّب صنفًا مقياسُه أداةُ مسحٍ مسجَّلةٌ تُشغَّل.
   ⇒ فالمقياسُ **صورتاه معًا**، وهذا وفاءٌ لنصِّ القاعدةِ لا تليينٌ لها. */
$dcNoQ  = (int) $one("SELECT COUNT(*) FROM repair01_debt_register
                       WHERE TRIM(measure_sql) = '' AND TRIM(measure_tool) = ''");
ch('CH-02', 'صنفُ دَينٍ صفرُه غيرُ قابلٍ للقياس', $dcNoQ, $dcTot,
   'أصنافُ الدَّينِ في السجلّ', 'CONCERN', 'سجلُّ الأصناف',
   'صفرٌ من مقامٍ مجهولٍ لا يُثبت شيئًا — والصنفُ بلا استعلامٍ ولا أداةِ مسحٍ لا يُعاد قياسُه');

/* ═══ CH-03 · استعلامُ قياسٍ مسجَّلٌ لا يعمل ═════════════════════════════ */
$dcRun = 0; $dcFail = array();
$q = $conn->query("SELECT class_code, measure_sql FROM repair01_debt_register WHERE TRIM(measure_sql) <> ''");
while ($q && ($x = $q->fetch_assoc())) {
    $dcRun++;
    $t = @$conn->query($x['measure_sql']);
    if (!$t) { $dcFail[] = $x['class_code']; continue; }
    $v = $t->fetch_row();
    if (!$v || !is_numeric($v[0])) { $dcFail[] = $x['class_code']; }
}
ch('CH-03', 'استعلامُ قياسٍ مسجَّلٌ لا يعمل أو لا يعيد عددًا', count($dcFail), $dcRun,
   'أصنافٌ تحمل استعلامًا وشُغِّل الآن', 'REDESIGN', 'المخطَّطُ الحيّ',
   'تشغيلُ كلِّ استعلامِ قياسٍ مسجَّلٍ على القاعدة'
   . ($dcFail ? ' ⇐ ' . implode('، ', $dcFail) : ''));

/* ═══ CH-04 · عمودٌ لا وجودَ له في مقياسِ أداة ═══════════════════════════ */
$colKnown = array();
$r = $conn->query("SELECT DISTINCT LOWER(COLUMN_NAME) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()");
while ($r && ($x = $r->fetch_row())) { $colKnown[$x[0]] = true; }
$refs = 0; $blind = array();
foreach (glob($ROOT . '/tools/repair01_*.php') as $f) {
    $s = (string) @file_get_contents($f);
    if (!preg_match_all('~COALESCE\s*\(\s*([A-Za-z_][A-Za-z_0-9]*)\s*(?:\.\s*([A-Za-z_][A-Za-z_0-9]*)\s*)?,~i',
                        $s, $m, PREG_SET_ORDER)) { continue; }
    foreach ($m as $g) {
        $col = strtolower((isset($g[2]) && $g[2] !== '') ? $g[2] : $g[1]);
        $refs++;
        if (!isset($colKnown[$col])) { $blind[] = basename($f) . ':' . $col; }
    }
}
$blind = array_values(array_unique($blind));
ch('CH-04', 'مقياسُ أداةٍ يسأل عمودًا لا وجودَ له فيقرأ صفرًا أبدًا', count($blind), $refs,
   'مواضعُ COALESCE في أدواتِ الحملة', 'CONCERN', 'القرصُ والمخطَّطُ معًا',
   'مطابقةُ كلِّ عمودٍ مذكورٍ باسمِه على information_schema'
   . ($blind ? ' ⇐ ' . implode('، ', array_slice($blind, 0, 3)) : ''));

/* ═══ CH-05 · نسبةٌ مجمَّعةٌ منشورةٌ — نقضٌ صريحٌ للبندِ ٦٤ ═══════════════ */
$docs = array_merge(glob($ROOT . '/docs/REPAIR01_20260823/*.md'),
                    glob($ROOT . '/docs/REPAIR01_20260823/plan/*.md'),
                    glob($ROOT . '/docs/REPAIR01_20260823/open/*.md'));
$aggr = array();
foreach ($docs as $d) {
    $s = (string) @file_get_contents($d);
    if (preg_match('~[0-9]{1,3}(?:[.,][0-9]+)?\s*%\s*(?:مكتمل|اكتمال|منجز|إنجاز)~u', $s)
     || preg_match('~(?:مكتمل|اكتمال|الإنجاز الكلي)\s*[:：]?\s*[0-9]{1,3}(?:[.,][0-9]+)?\s*%~u', $s)) {
        $aggr[] = basename($d);
    }
}
ch('CH-05', 'وثيقةٌ تنشر نسبةً مجمَّعةً واحدةً بدل المقاماتِ التسعة', count($aggr), count($docs),
   'وثائقُ الحملةِ المقروءةُ من القرص', 'REDESIGN', 'القرص',
   'قراءةُ ' . count($docs) . ' وثيقةً والبحثُ عن صيغةِ نسبةِ اكتمالٍ مجمَّعة'
   . ($aggr ? ' ⇐ ' . implode('، ', $aggr) : ''));

/* ═══ CH-06 · حقيقةٌ واحدةٌ يكتبها نطاقان — تُقاس من الكتابةِ في الشيفرة ═ */
$biz = array();
$r = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
                      AND TABLE_NAME NOT LIKE 'repair01\\_%'");
while ($r && ($x = $r->fetch_row())) { $biz[strtolower($x[0])] = true; }
$writers = array();
$q = $conn->query("SELECT route, owner_code FROM repair01_screen_registry
                    WHERE on_disk = 1 AND surface_kind = 'SOURCE' AND owner_code <> ''
                      AND lifecycle IN ('LIVE_REGISTERED','LIVE_UNREGISTERED')");
while ($q && ($x = $q->fetch_assoc())) {
    $p = $ROOT . '/' . $x['route'];
    if (!is_file($p)) { continue; }
    $s = (string) @file_get_contents($p);
    preg_match_all('~\b(?:INSERT\s+INTO|UPDATE|REPLACE\s+INTO|DELETE\s+FROM)\s+`?([a-z_][a-z_0-9]{2,})~i', $s, $m);
    foreach (array_unique(array_map('strtolower', $m[1])) as $t) {
        if (!isset($biz[$t])) { continue; }
        $writers[$t][$x['owner_code']] = true;
    }
}
$dupW = array();
foreach ($writers as $t => $ow) { if (count($ow) > 1) { $dupW[] = $t . '←' . implode('/', array_keys($ow)); } }
ch('CH-06', 'جدولُ حقيقةٍ يكتب فيه سطحانِ مصدرانِ من نطاقَين', count($dupW), count($writers),
   'جداولُ الأعمالِ التي يكتب فيها سطحٌ مصدرٌ حيٌّ مباشرةً', 'REDESIGN', 'القرص',
   'قراءةُ كتاباتِ الأسطحِ المصدرِ من ملفّاتِها ونسبةُ كلِّ جدولٍ إلى مالكِ كاتبِه'
   . ($dupW ? ' ⇐ ' . implode('، ', array_slice($dupW, 0, 3)) : ''));

/* ═══ CH-07 · سجلٌّ يزعم وجودَ ملفٍّ لا وجودَ له ═════════════════════════ */
$onDisk = 0; $absent = array();
$q = $conn->query("SELECT route FROM repair01_screen_registry WHERE on_disk = 1 AND route <> ''");
while ($q && ($x = $q->fetch_row())) {
    $onDisk++;
    if (!is_file($ROOT . '/' . $x[0])) { $absent[] = $x[0]; }
}
ch('CH-07', 'صفٌّ يزعم وجودَه على القرصِ والملفُّ غائب', count($absent), $onDisk,
   'صفوفُ السجلِّ الموسومةُ on_disk', 'REDESIGN', 'القرص',
   'محاولةُ فتحِ كلِّ مسارٍ مزعوم'
   . ($absent ? ' ⇐ ' . implode('، ', array_slice($absent, 0, 3)) : ''));

/* ═══ CH-08 · انحرافُ المصدرِ المجمَّد — إعادةُ تجزئةٍ مستقلّة ═══════════ */
$srcN = 0; $drift = array();
$q = $conn->query("SELECT file_name, sha256 FROM repair01_source_files");
while ($q && ($x = $q->fetch_assoc())) {
    $srcN++;
    $p = $ROOT . '/docs/REPAIR01_20260823/' . $x['file_name'];
    if (!is_file($p)) { $drift[] = $x['file_name'] . ' (غائب)'; continue; }
    if (hash_file('sha256', $p) !== $x['sha256']) { $drift[] = $x['file_name']; }
}
ch('CH-08', 'مصنَّفُ الدخولِ المجمَّدُ تغيّر عمّا خُتم', count($drift), $srcN,
   'الملفّاتُ الثلاثةَ عشرَ المجمَّدةُ في سجلِّ المصادر', 'REDESIGN', 'المصنَّفاتُ المجمَّدة',
   'إعادةُ تجزئةِ sha256 لكلِّ ملفٍّ ومقارنتُها بالمختوم'
   . ($drift ? ' ⇐ ' . implode('، ', $drift) : ''));

/* ═══ CH-09 · تبويبٌ من الدَّينِ الموروثِ بلا حكمٍ مسجَّل ════════════════ */
$tabN = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                     WHERE ownership_verdict = 'TAB_CHILD' AND on_disk = 1");
$tabNo = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry t
                      LEFT JOIN repair01_w16_tabs w ON w.screen_file = t.screen_file
                                                   AND w.dept_code   = t.owner_code
                     WHERE t.ownership_verdict = 'TAB_CHILD' AND t.on_disk = 1
                       AND w.screen_file IS NULL");
ch('CH-09', 'تبويبٌ من الدَّينِ الموروثِ بلا حكمٍ مسجَّلٍ فرادى', $tabNo, $tabN,
   'التبويباتُ الموسومةُ TAB_CHILD والقائمةُ على القرص', 'CONCERN', 'المخطَّطُ الحيّ',
   'مقابلةُ كلِّ تبويبٍ بصفِّ حكمِه في دفترِ المرحلة');

/* ═══ CH-10 · القبولُ البشريُّ لم يُؤدَّ بعد ═════════════════════════════ */
$uatN  = (int) $one("SELECT COUNT(*) FROM repair01_w16_uat");
$uatOk = (int) $one("SELECT COUNT(*) FROM repair01_w16_uat WHERE status = 'PASSED'");
ch('CH-10', 'محطّةُ قبولٍ بشريٍّ لم يعبرها فاعلٌ حقيقيٌّ بعد', max(0, $uatN - $uatOk), $uatN,
   'محطّاتُ رحلةِ الإثباتِ المسجَّلةُ لهذه المرحلة', 'CONCERN', 'دفترُ الرحلةِ البشريّة',
   'العبورُ يلزمه فاعلٌ حقيقيٌّ وزمنٌ ودليل — والقاعدةُ تردُّ الإعلانَ بلا هذه الثلاثة');

/* ═══ CH-11 · مرحلةٌ بلا بوّابةٍ أو بلا فحصٍ سلبيّ ═══════════════════════ */
$stages = array('w0', 'w1', 'w2', 'w3', 'w4', 'w5', 'w6', 'w7', 'w8', 'w9',
                'w10', 'w11', 'w12', 'w13', 'w135', 'w14', 'w15', 'w16');
$stageBad = array();
foreach ($stages as $s) {
    $g = is_file($ROOT . "/tools/repair01_{$s}_gate.php");
    /* ◆ **والشاهدُ السالبُ صورتان**: أداةُ كسرٍ وإرجاعٍ في `tools/`، أو شواهدُ
       سالبةٌ في `tests/` — وهي صورةُ W13.5. **وقاعدةٌ تعرف صورةً واحدةً
       تُرسِّب البريءَ بجهلِها هي**، فتُقاس الصورتان معًا. */
    $n = is_file($ROOT . "/tools/repair01_{$s}_negative.php")
      || count(glob($ROOT . "/tests/{$s}_*.php")) > 0;
    if (!$g || !$n) { $stageBad[] = $s . (!$g ? ' (لا بوّابة)' : ' (لا فحصَ سلبيًّا)'); }
}
ch('CH-11', 'مرحلةٌ بلا بوّابةٍ أو بلا فحصٍ سلبيٍّ على القرص', count($stageBad), count($stages),
   'مراحلُ الحملةِ المسجَّلةُ في هذه القاعدة', 'REDESIGN', 'القرص',
   'وجودُ بوّابةٍ وشاهدٍ سالبٍ - أداةً في tools أو شواهدَ في tests - لكلِّ مرحلة'
   . ($stageBad ? ' ⇐ ' . implode('، ', $stageBad) : ''));

/* ═══ CH-12 · نطاقٌ بلا المقاماتِ التسعةِ المنشورة ══════════════════════ */
require_once $ROOT . '/tools/lib/repair01_w16_scan.php';
$DOM = repair01_w16_domains();
$missAxis = array();
foreach ($DOM as $d) {
    $n = (int) $one("SELECT COUNT(*) FROM repair01_w16_scorecard WHERE domain_code = '" . $esc($d) . "'");
    if ($n !== 9) { $missAxis[] = $d . ':' . $n; }
}
ch('CH-12', 'نطاقٌ لم تُنشَر له المقاماتُ التسعةُ كاملةً', count($missAxis), count($DOM),
   'نطاقاتُ النشرِ المعياريّة', 'REDESIGN', 'المخطَّطُ الحيّ',
   'عدُّ صفوفِ المحاورِ لكلِّ نطاقٍ ومطابقتُها بتسعة'
   . ($missAxis ? ' ⇐ ' . implode('، ', array_slice($missAxis, 0, 4)) : ''));

/* ═══════════════════════════════════════════════════════════════════════════
   الحكم
   ═══════════════════════════════════════════════════════════════════════════ */
$red = 0; $con = 0; $acc = 0;
foreach ($F as $f) {
    if ($f['sev'] === 'REDESIGN') { $red++; } elseif ($f['sev'] === 'CONCERN') { $con++; } else { $acc++; }
    printf("  %-9s %-9s %-52s %d / %d  ·  %s\n",
        ($f['sev'] === 'REDESIGN' ? '✘ REDESIGN' : ($f['sev'] === 'CONCERN' ? '◆ CONCERN' : '✔ ACCEPT')),
        $f['key'], mb_substr($f['title'], 0, 52), $f['num'], $f['den'], $f['den_name']);
}
$verdict = $red > 0 ? 'REDESIGN' : ($con > 0 ? 'CONCERN' : 'ACCEPT');
echo "\n────────────────────────────────────────────────────────────\n";
printf("قواعدُ التحدّي: %d  ·  ACCEPT %d · CONCERN %d · REDESIGN %d\n", count($F), $acc, $con, $red);
printf("حكمُ المراجعةِ المستقلّة: **%s**\n", $verdict);

if ($APPLY) {
    $conn->query("DELETE FROM repair01_w16_challenge");
    $i = 0;
    foreach ($F as $f) {
        $i++;
        $id = sprintf('W16-CH-%02d', $i);
        $ok = $conn->query("INSERT INTO repair01_w16_challenge
            (finding_id, rule_key, title, severity, subject, measured, expected,
             primary_source, evidence, raised_at) VALUES (
            '" . $esc($id) . "', '" . $esc($f['key']) . "', '" . $esc($f['title']) . "',
            '" . $esc($f['sev']) . "', '" . $esc($f['den_name']) . "',
            '" . $esc($f['num'] . ' من ' . $f['den']) . "',
            '" . $esc('0 من ' . $f['den']) . "',
            '" . $esc($f['primary']) . "', '" . $esc(mb_substr($f['evidence'], 0, 480)) . "', NOW())");
        if (!$ok) { exit("✘ تعذّر تسجيلُ $id: {$conn->error}\n"); }
    }
    printf("✔ سُجِّلت %d قاعدةً في repair01_w16_challenge\n", count($F));
}

exit($red > 0 ? 2 : 0);
