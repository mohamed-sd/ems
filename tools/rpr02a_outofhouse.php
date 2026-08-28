<?php
/**
 * tools/rpr02a_outofhouse.php — الأربعةُ والثلاثون خارجَ بيتِها · تكتب أم تقرأ؟
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المقامُ مقيسٌ لا منقول**: `repair01_screen_registry.ownership_verdict =
 *   'EXECUTIVE_PROJECTION'` = **34 سطحًا**. ⚠ **وهو غيرُ الأربعةِ والثلاثين
 *   في ورقةِ `10 › 08`** («مرشَّحةٌ للدمجِ في ملفِّ الكيانِ الأمّ») — تطابقُ
 *   العددِ صدفةٌ لا هويّة، والتقريرُ يُثبت التقاطعَ بالعدِّ لا بالظنّ.
 *
 * ◆ **و«تكتب أم تقرأ» يُقاس من الملفِّ نفسِه لا من وسمٍ**: الوسمُ
 *   `PROJECTION` قرارٌ سابقٌ، والسؤالُ هنا **هل يكتب الملفُّ فعلًا**. فيُمسح
 *   المصدرُ عن كتابةٍ في قاعدةِ البيانات (`INSERT`/`UPDATE`/`DELETE`) وعن
 *   استقبالِ `$_POST` بفعلٍ محروس. ⛔ ووسمٌ يقول «إسقاط» وملفٌّ يكتب = **خرقٌ
 *   يُعلَن**، لا وسمٌ يُصدَّق.
 *
 * ◆ **والحكمُ حكمان**: يقرأ ⇒ يبقى ويُوسَم `IMPORTED_READONLY` بمرجعِ مصدرِه ·
 *   يكتب ⇒ تُنقل ملكيتُه إلى بيتِه ويبقى في مكانِه عرضٌ للقراءةِ فقط.
 *
 * ⛔ **ولا نقلَ ينفَّذ هنا**: هذه أداةُ قياسٍ وحكم. والنقلُ قرارٌ موثَّقٌ يُسجَّل
 *   في `repair01_decisions` — ومحظورُ §٥·٤ يمنع نقلًا بلا قرار.
 *
 * التشغيل: php tools/rpr02a_outofhouse.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI only\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
require_once $ROOT . '/tools/lib/xlsx_io.php';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

/* ═══ ① فهرسُ القرصِ الحقيقيّ ═══ */
$diskIdx = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $fo) {
    $p = strtr($fo->getPathname(), DIRECTORY_SEPARATOR, '/');
    if (strpos($p, '/.git') !== false || strpos($p, 'node_modules') !== false || strpos($p, '/vendor/') !== false) { continue; }
    if (substr($p, -4) !== '.php') { continue; }
    $bn = strtolower(basename($p));
    if (!isset($diskIdx[$bn])) { $diskIdx[$bn] = str_replace(strtr($ROOT, DIRECTORY_SEPARATOR, '/') . '/', '', $p); }
}

/* ═══ ② المقام ═══ */
$rows = array();
$r = $conn->query("SELECT screen_id, owner_code, canonical_label_ar, screen_file, route, surface_kind,
                          lifecycle, source_of_truth, action_guard
                     FROM repair01_screen_registry
                    WHERE ownership_verdict = 'EXECUTIVE_PROJECTION'
                    ORDER BY owner_code, screen_id");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }

/* ═══ ③ المقامُ الآخرُ — مرشَّحاتُ الدمجِ في ورقةِ `10 › 08` ═══ */
/* ⚠ **الفئةُ الخالصةُ غيرُ الذاكرة**: رأسُ الورقةِ يُعلن «34» وهي الفئةُ الخالصةُ
     وحدَها؛ وخمسةُ صفوفٍ أخرى **تذكر الدمجَ ضمنَ مشكلةٍ مركَّبة**. فيُقاس
     المقامان معًا ولا يُخلطان — وإلّا صار 34 يُقرأ 36 بلا سبب. */
$mergeIds = array(); $mergeWide = array();
$WB = xlsx_read($ROOT . '/docs/REPAIR01_20260823/10 · المصالحة مع النظام.xlsx');
foreach ($WB['08_مؤشرات_المشاكل'] as $ri => $row) {
    if ($ri <= 3) { continue; }
    ksort($row);
    $sid = trim((string) ($row[0] ?? ''));
    $iss = trim((string) ($row[5] ?? ''));
    if ($sid === '' || strpos($sid, 'SCR-') !== 0) { continue; }
    if ($iss === 'مرشَّحةٌ للدمجِ في ملفِّ الكيانِ الأم') { $mergeIds[$sid] = 1; }
    if (strpos($iss, 'مرشَّحةٌ للدمج') !== false) { $mergeWide[$sid] = 1; }
}

/* ═══ ④ «تكتب أم تقرأ» — من المصدرِ نفسِه ═══ */
function oh_probe($path) {
    if (!is_file($path)) { return array('read' => true, 'why' => 'لا ملفَّ على القرص', 'hits' => array()); }
    $src = (string) file_get_contents($path);
    $hits = array();
    if (preg_match_all('~\b(INSERT\s+INTO|REPLACE\s+INTO|UPDATE\s+`?\w+`?\s+SET|DELETE\s+FROM)\b~i', $src, $m)) {
        $c = array_count_values(array_map('strtoupper', array_map(function ($s) { return preg_replace('~\s+~', ' ', $s); }, $m[1])));
        foreach ($c as $k => $v) { $hits[] = $k . ' ×' . $v; }
    }
    $post = preg_match('~\$_POST\s*\[~', $src) ? 1 : 0;
    $guard = preg_match('~(ems_action_guard|csrf_verify|ems_csrf|require_csrf)~i', $src) ? 1 : 0;
    $writes = (bool) $hits;
    return array(
        'read'  => !$writes,
        'why'   => $writes ? ('كتابةٌ في المصدر: ' . implode(' · ', $hits) . ($post ? ' · يستقبل `$_POST`' : '') . ($guard ? ' · بحارسِ فعل' : ' · **بلا حارسِ فعلٍ مرصود**'))
                           : ('صفرُ عبارةِ كتابةٍ في المصدر' . ($post ? ' · لكنّه يستقبل `$_POST`' : '')),
        'hits'  => $hits, 'post' => $post, 'guard' => $guard,
    );
}

$W = 0; $R = 0; $out = array();
foreach ($rows as $x) {
    $bn = strtolower(basename((string) $x['screen_file']));
    $disk = isset($diskIdx[$bn]) ? $diskIdx[$bn] : '';
    $p = oh_probe($disk !== '' ? ($ROOT . '/' . $disk) : '');
    $x['disk'] = $disk; $x['probe'] = $p;
    $x['in_merge'] = isset($mergeIds[$x['screen_id']]);
    if ($p['read']) { $R++; } else { $W++; }
    $out[] = $x;
}

/* البيتُ المعياريُّ — من مصدرِ الحقيقةِ المسجَّلِ لا من تخمين */
function oh_home($x) {
    $s = trim((string) $x['source_of_truth']);
    if ($s !== '') { return $s; }
    return '⛔ **غيرُ مسجَّل** — يُحسم بقرارٍ موثَّق';
}

$ts = date('Y-m-d H:i:s');
$md  = "# RPR-02-A · الأربعةُ والثلاثون خارجَ بيتِها — بحكمٍ لكلٍّ\n\n";
$md .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/rpr02a_outofhouse.php`\n> **مولَّدٌ**: " . $ts . "\n";
$md .= "> **المقام**: `repair01_screen_registry.ownership_verdict = 'EXECUTIVE_PROJECTION'` · **الحكم**: مسحُ المصدرِ على القرص\n\n";

$md .= "## ⓪ أيُّ أربعةٍ وثلاثين؟ — مقامان يتشابهان عددًا ويختلفان هويّة\n\n";
$md .= "| المقام | العدد | التقاطعُ مع الآخر |\n|---|---:|---:|\n";
$inter = 0;
foreach ($out as $x) { if ($x['in_merge']) { $inter++; } }
$md .= "| `repair01_screen_registry.ownership_verdict = 'EXECUTIVE_PROJECTION'` | **" . count($out) . "** | " . $inter . " |\n";
$interW = 0;
foreach ($out as $x) { if (isset($mergeWide[$x['screen_id']])) { $interW++; } }
$md .= "| `10 › 08_مؤشرات_المشاكل` — «مرشَّحةٌ للدمج» **فئةً خالصة** (وهو رقمُ رأسِ الورقة) | **" . count($mergeIds) . "** | " . $inter . " |\n";
$md .= "| — وبضمِّ المشكلاتِ المركَّبةِ التي تذكر الدمجَ أيضًا | " . count($mergeWide) . " | " . $interW . " |\n\n";
$md .= "**الحكم:** " . ($inter === 0
    ? '⛔ **مقامان مختلفان تمامًا — والتقاطعُ صفر.** فتطابقُ العددِ صدفةٌ لا هويّة، والأمرُ افترض أنّهما واحد.'
    : 'تقاطعُهما ' . $inter . ' سطحًا — فليسا المقامَ نفسَه.') . "\n\n";

$md .= "## ① الحكمُ سطحًا سطحًا\n\n";
$md .= "| # | الرمز | البيتُ الحالي | السطح | البيتُ المعياريُّ (مصدرُ الحقيقة) | يكتب/يقرأ | الدليل | الحكم |\n";
$md .= "|---:|---|---|---|---|---|---|---|\n";
$i = 0;
foreach ($out as $x) {
    $i++;
    $verdict = $x['probe']['read']
        ? '**يبقى** · يُوسَم `IMPORTED_READONLY` بمرجعِ مصدرِه'
        : '⛔ **تُنقل ملكيتُه إلى بيتِه** ويبقى هنا عرضٌ للقراءةِ فقط — **بقرارٍ موثَّق**';
    $md .= '| ' . $i . ' | `' . $x['screen_id'] . '` | `' . $x['owner_code'] . '` | '
         . ($x['canonical_label_ar'] !== '' ? $x['canonical_label_ar'] : '`' . $x['screen_file'] . '`') . ' | '
         . oh_home($x) . ' | ' . ($x['probe']['read'] ? '**يقرأ**' : '**يكتب**') . ' | `'
         . ($x['disk'] !== '' ? $x['disk'] : '—') . '` — ' . $x['probe']['why'] . ' | ' . $verdict . " |\n";
}

$md .= "\n## ② الحصيلة\n\n| الحكم | العدد |\n|---|---:|\n";
$md .= "| **يقرأ** ⇒ يبقى ويُوسَم إسقاطًا | **" . $R . "** |\n";
$md .= "| **يكتب** ⇒ تُنقل ملكيتُه بقرارٍ موثَّق | **" . $W . "** |\n";
$md .= "| **المجموع** | **" . count($out) . "** |\n\n";
$md .= "> ⛔ **وسمٌ يقول «إسقاط» وملفٌّ يكتب**: الأربعةُ والثلاثون كلُّها موسومةٌ `PROJECTION` في السجل،\n";
$md .= "> **والمقيسُ أنّ " . $W . " منها تكتب فعلًا في قاعدةِ البيانات** — فالوسمُ لا يُصدَّق، والمصدرُ يُقاس.\n\n";

$md .= "## ③ المراجعُ الداخليُّ المستقلّ — استقلالُ التأكيدِ مقيسًا\n\n";
$iafOwn = (int) $conn->query("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code='IAF' AND lifecycle<>'GHOST_TARGET'")->fetch_row()[0];
$iafNonAudit = (int) $conn->query("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code='IAF' AND lifecycle<>'GHOST_TARGET' AND ownership_verdict<>'AUDIT_ASSURANCE'")->fetch_row()[0];
$md .= "| المقياس | العدد |\n|---|---:|\n";
$md .= "| أسطحٌ حيّةٌ يملكها `IAF` | " . $iafOwn . " |\n";
$md .= "| منها **ليست** `AUDIT_ASSURANCE` — أي خارجَ وظيفةِ التأكيد | **" . $iafNonAudit . "** |\n";
$r2 = $conn->query("SELECT owner_dept, classification, COUNT(*) n FROM repair01_ownership
                     WHERE space_role LIKE '%المراجع%' GROUP BY owner_dept, classification ORDER BY n DESC");
$md .= "\n**ظهورُه في مساحاتِ غيرِه** — وهو **قراءةٌ رقابيّةٌ لا ملكيّة**:\n\n| بيتُ السطح | التصنيف | عدد |\n|---|---|---:|\n";
while ($r2 && ($x = $r2->fetch_assoc())) { $md .= '| ' . $x['owner_dept'] . ' | `' . $x['classification'] . '` | ' . $x['n'] . " |\n"; }
$md .= "\n**الحكم:** " . ($iafNonAudit === 0
    ? '✔ **المراجعُ الداخليُّ لا يملك سطحًا خارجَ وظيفةِ التأكيد** — فكلُّ ما يملكه `AUDIT_ASSURANCE` و`iaf_*`. '
      . 'وحضورُه على أسطحِ المالية والخزينة والقيادة **قراءةٌ مصنَّفةٌ `CONTROL_OVERSIGHT`** لا ملكيّة. '
      . '⇒ **م 110 غيرُ مخروقةٍ في الملكية**، وما وصفه الأمرُ خرقًا هو الحضورُ الرقابيُّ المشروع.'
    : '⛔ يملك ' . $iafNonAudit . ' سطحًا خارجَ وظيفةِ التأكيد — يُنقل أوّلًا.') . "\n";

$path = $ROOT . '/docs/REPAIR01_20260823/RPR02A_OUTOFHOUSE.md';
file_put_contents($path, $md);
printf("المقام %d · يقرأ %d · يكتب %d · تقاطعٌ مع مرشَّحاتِ الدمج %d من %d · IAF خارجَ التأكيد %d\n",
    count($out), $R, $W, $inter, count($mergeIds), $iafNonAudit);
echo "=> " . $path . "\n";
