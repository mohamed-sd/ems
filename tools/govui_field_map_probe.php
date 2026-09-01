<?php
/**
 * tools/govui_field_map_probe.php — **جسرُ حقلِ الورقةِ إلى عمودِه المسمَّى**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الحقيقةُ التي يستثمرها**: `tools/gov_exec_dept_build.php` حين بنى جداولَ
 *   الإداراتِ كتب **اسمَ حقلِ الورقةِ في تعليقِ العمودِ حرفًا** (‏«وأسماءُ الأعمدةِ
 *   تعليقُها اسمُ الحقلِ في ورقةِ الدليلِ حرفًا»). ⇒ **فالجسرُ موجودٌ في المخطَّطِ
 *   نفسِه** ولا يُخترع: يُقرأ من `information_schema.COLUMNS`.
 *
 * ◆ **والحاجزُ الذي يرفعه**: أسطحٌ مكتوبةٌ بيدٍ سابقةٍ **لها جدولُ دليلٍ قائمٌ
 *   بأعمدةٍ مسمّاةٍ بأسماءِ الورقة**، والمولِّدُ رفض أن يدهسها بحقٍّ — فبقيت
 *   الأعمدةُ في المخزنِ ولا يُصيَّر منها حرف ([[iaf-field-closure]]). وتأليفُ
 *   الخريطةِ يدًا لثلاثِ مئةِ سطحٍ عملٌ يُخطئ؛ **والمخطَّطُ يعرفها**.
 *
 * ◆ **والمطابقةُ بقاعدةِ القبولِ نفسِها** (`tools/lib/rpr02_field_lib.php`) —
 *   ⛔ ولا معيارَ ثانٍ لنفسِ السؤال ([[counter-parity-two-readers]]).
 *
 * ⛔ **ولا يكتب شيئًا** — يُخرج مواصفةً جاهزةً للفرزِ البشريّ، **والحكمُ الأخير
 *   للمؤلِّف**: عمودٌ اقتُرح بتطابقٍ جزئيٍّ يُراجَع، وحقلٌ بلا عمودٍ يُعلَن
 *   `NO_COLUMN` باسمِه ليُحسَم (‏عمودٌ جديدٌ أو اشتقاقٌ).
 *
 * التشغيل:
 *   php tools/govui_field_map_probe.php --req=SUP-31 [--table=sup_list_ref]
 *   php tools/govui_field_map_probe.php --unit=DEP-02        ← كلُّ ناقصٍ فيها
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/tools/lib/rpr02_field_lib.php';
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');

$REQ = null; $UNIT = null; $TBL = null; $PLAN = null; $EMITSPEC = null;
$SPECOUT = array();
foreach ($argv as $a) {
    if (strpos($a, '--req=') === 0)        { $REQ = substr($a, 6); }
    elseif (strpos($a, '--unit=') === 0)   { $UNIT = substr($a, 7); }
    elseif (strpos($a, '--table=') === 0)  { $TBL = substr($a, 8); }
    /* ◆ **وخُطّةُ المؤلِّفِ تُمرَّر ملفًّا** (`--plan=<json>`): لكلِّ متطلبٍ جدولُه
         ومرساةُ كتلتِه ومعرِّفُ شبكتِه ونصُّ فراغِه — **قراراتٌ لا يملكها
         المِجَسّ**. فيخرج **مواصفةً كاملةً** بدلَ قصاصاتٍ تُنسَخ بيدٍ فتُخطئ. */
    elseif (strpos($a, '--plan=') === 0)   { $PLAN = substr($a, 7); }
    elseif (strpos($a, '--emit-spec=') === 0) { $EMITSPEC = substr($a, 12); }
}
$P = array();
if ($PLAN !== null) {
    $P = json_decode((string) @file_get_contents($PLAN), true);
    if (!is_array($P)) { exit("خُطّةٌ غيرُ صالحة: " . $PLAN . chr(10)); }
    $targets2 = array();
    foreach ($P as $e) { $targets2[] = $e['req']; }
}
if ($REQ === null && $UNIT === null && $PLAN === null) {
    exit("الاستعمال: php tools/govui_field_map_probe.php --req=<REQ> [--table=T] | --unit=<UNIT>\n");
}

/* ── دفترُ الأعمدةِ المسمّاة: تعليقُ العمودِ ⇐ (جدولٌ · عمودٌ) ───────────────── */
$byTable = array();
$q = $conn->query("SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_COMMENT k
                     FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_COMMENT <> ''
                    ORDER BY TABLE_NAME, ORDINAL_POSITION");
while ($q && $r = $q->fetch_assoc()) {
    $byTable[$r['t']][] = array('col' => $r['c'], 'label' => $r['k']);
}

/* ── الأسطحُ المطلوبة ─────────────────────────────────────────────────────── */
$targets = array();
if ($PLAN !== null) { $targets = $targets2; }
elseif ($REQ !== null) { $targets[] = $REQ; }
else {
    $st = $conn->prepare("SELECT DISTINCT tu.requirement_id
                            FROM repair01_target_universe tu
                           WHERE tu.verdict = 'MATCHED' AND tu.unit = ? AND tu.requirement_id <> ''
                           ORDER BY tu.requirement_id");
    $st->bind_param('s', $UNIT); $st->execute();
    $rs = $st->get_result();
    while ($x = $rs->fetch_assoc()) { $targets[] = $x['requirement_id']; }
}

/* مسارُ السطحِ لكلِّ متطلب — للإخراجِ الجاهز */
$route = array();
$q = $conn->query("SELECT tu.requirement_id, sr.route, sr.canonical_label_ar
                     FROM repair01_target_universe tu
                     JOIN repair01_screen_registry sr ON sr.screen_id = tu.screen_id
                    WHERE tu.verdict = 'MATCHED'");
while ($q && $r = $q->fetch_assoc()) {
    if (!isset($route[$r['requirement_id']])) {
        $route[$r['requirement_id']] = array(str_replace(chr(92), '/', (string) $r['route']),
                                             (string) $r['canonical_label_ar']);
    }
}

foreach ($targets as $req) {
    $st = $conn->prepare("SELECT field_name, field_type FROM repair01_fields
                           WHERE requirement_id = ? ORDER BY id");
    $st->bind_param('s', $req); $st->execute();
    $rs = $st->get_result();
    $fields = array();
    while ($x = $rs->fetch_assoc()) { $fields[] = $x; }
    $st->close();
    if (!$fields) { continue; }

    /* ── أفضلُ جدولٍ: أكثرُ تعليقاتِ أعمدةٍ تطابق أسماءَ حقولِ الورقة ───────── */
    $pl = null;
    foreach ($P as $e) { if ($e['req'] === $req) { $pl = $e; break; } }
    $fixT = ($pl !== null && isset($pl['table'])) ? $pl['table'] : $TBL;
    $best = $fixT; $bestN = -1; $scores = array();
    $cands = ($fixT !== null) ? array($fixT) : array_keys($byTable);
    foreach ($cands as $t) {
        if (!isset($byTable[$t])) { continue; }
        $bagStr = array(); $bagTok = array();
        foreach ($byTable[$t] as $c0) {
            $nv = fm_norm($c0['label']);
            if ($nv !== '') { $bagStr[$nv] = 1; }
            foreach (fm_tok($c0['label'], $FM_STOP) as $tk) { $bagTok[$tk] = 1; }
        }
        $n = 0;
        foreach ($fields as $f) {
            if ($f['field_type'] === 'AUDIT') { continue; }
            if (fm_hit(fm_tok($f['field_name'], $FM_STOP), $bagTok, $bagStr,
                       fm_norm($f['field_name'])) !== '') { $n++; }
        }
        $scores[$t] = $n;
        if ($n > $bestN) { $bestN = $n; $best = $t; }
    }
    arsort($scores);

    $rt = isset($route[$req]) ? $route[$req] : array('?', '?');
    printf("\n══ %s · %s · %s\n", $req, $rt[1], $rt[0]);
    $top = array_slice($scores, 0, 3, true);
    $line = array();
    foreach ($top as $t => $n) { $line[] = $t . ' (' . $n . ')'; }
    echo "   المرشَّحون: " . implode(' · ', $line) . "\n";
    if ($best === null || !isset($byTable[$best])) { echo "   ⛔ لا جدولَ مرشَّحًا\n"; continue; }

    /* ── الخريطةُ المقترَحة: حقلُ الورقةِ ⇐ عمودُه المسمَّى ─────────────────── */
    $used = array(); $mapOut = array();
    echo "    'map' => array(\n";
    foreach ($fields as $f) {
        $isAudit = ($f['field_type'] === 'AUDIT');
        $dTok = fm_tok($f['field_name'], $FM_STOP);
        $dNorm = fm_norm($f['field_name']);
        $pick = ''; $how = '';
        foreach ($byTable[$best] as $c0) {
            if (isset($used[$c0['col']])) { continue; }
            $cNorm = fm_norm($c0['label']);
            if ($cNorm !== '' && $cNorm === $dNorm) { $pick = $c0['col']; $how = 'EXACT'; break; }
        }
        if ($pick === '') {
            foreach ($byTable[$best] as $c0) {
                if (isset($used[$c0['col']])) { continue; }
                $bagStr = array(fm_norm($c0['label']) => 1);
                $bagTok = array();
                foreach (fm_tok($c0['label'], $FM_STOP) as $tk) { $bagTok[$tk] = 1; }
                if (fm_hit($dTok, $bagTok, $bagStr, $dNorm) !== '') {
                    $pick = $c0['col']; $how = 'COVER'; break;
                }
            }
        }
        /* التنقيةُ نفسُها التي في tools/govui_field_close.php::gfc_key —
           ولا قاعدةَ ثانيةٌ تتفرّق عنها. */
        $clean = str_replace(array("\xE2\x97\x84", "\xE2\x96\xBC"), '', $f['field_name']);
        if (preg_match('~^(.*?)\s*\x{00B7}\s*([^\x{0600}-\x{06FF}]+)$~u', $clean, $mm2)) { $clean = $mm2[1]; }
        $clean = str_replace(array("\xC2\xB7", "\xE2\x80\x94", "\xE2\x80\x93", "\xE2\x80\xA2"), ' ', $clean);
        $clean = trim(preg_replace('~\s+~u', ' ',
                 preg_replace('~[\x{0640}\x{064B}-\x{0652}\x{0670}]~u', '', $clean)));
        if ($pick !== '') {
            $used[$pick] = 1;
            $mapOut[$clean] = $pick;
            printf("        '%s' => '%s',%s\n", $clean, $pick,
                   $how === 'COVER' ? '   // COVER — راجِعْ' : '');
        } else {
            $mapOut[$clean] = '';
            printf("        '%s' => '',   // NO_COLUMN%s\n", $clean, $isAudit ? ' (AUDIT)' : '');
        }
    }
    echo "    ),\n";
    if ($pl !== null) { $SPECOUT[] = array('pl' => $pl, 'map' => $mapOut, 'table' => $best); }
    $left = array();
    foreach ($byTable[$best] as $c0) { if (!isset($used[$c0['col']])) { $left[] = $c0['col']; } }
    if ($left) { echo "   أعمدةٌ لم تُستعمَل: " . implode(' · ', array_slice($left, 0, 20)) . "\n"; }
}
echo "\n";

/* ═══ إخراجُ المواصفةِ كاملةً — `--emit-spec=<file>` ═════════════════════════
 * ◆ **الخريطةُ لا تُنسَخ بيدٍ**: نسخُ ثلاثِ مئةِ سطرٍ من مخرَجٍ إلى مواصفةٍ
 *   يُخطئ حرفًا فيصير عمودٌ لحقلٍ آخر، **وحرفٌ واحدٌ في اسمٍ عربيٍّ لا يُرى**.
 * ◆ **وأوّلُ حقلٍ بلا عمودٍ يأخذ `id`**: جداولُ المولِّدِ تبدأ تعليقاتُها من
 *   الحقلِ الثاني، والأوّلُ معرِّفُ السطرِ نفسُه — وهو `id` لا عمودٌ جديد.
 * ◆ **وما بقي بلا عمودٍ يُعلَن `+col`** بنوعٍ افتراضيٍّ يراجعه المؤلِّف.
 * ═══════════════════════════════════════════════════════════════════════════ */
if ($EMITSPEC !== null) {
    $slugI = 0;
    $out = "<?php\n/**\n * " . basename($EMITSPEC) . " — مواصفةٌ مولَّدةٌ من `tools/govui_field_map_probe.php`\n"
         . " * ◆ الخريطةُ من **تعليقاتِ أعمدةِ المخطَّطِ نفسِها** لا من نسخٍ بيد.\n"
         . " * ⛔ وتُراجَع قبل التطبيق: عمودٌ اقتُرح بتغطيةٍ جزئيّةٍ قد يخطئ الحقل.\n */\nreturn array(\n\n";
    $dept = '';
    foreach ($SPECOUT as $e) { if (isset($e['pl']['dept'])) { $dept = $e['pl']['dept']; break; } }
    $out .= "'dept' => '" . $dept . "',\n'screens' => array(\n";
    foreach ($SPECOUT as $e) {
        $pl = $e['pl'];
        $out .= "\narray(\n    'req' => '" . $pl['req'] . "', 'route' => '" . $pl['route'] . "',\n"
              . "    'table' => '" . $e['table'] . "',\n";
        if (!empty($pl['anchor'])) { $out .= "    'anchor' => '" . str_replace("'", "\'", $pl['anchor']) . "',\n"; }
        if (!empty($pl['rows']))   { $out .= "    'rows' => \"" . str_replace('"', '\\"', $pl['rows']) . "\",\n"; }
        $out .= "    'grid_id' => '" . $pl['grid_id'] . "', 'empty' => '" . str_replace("'", '', $pl['empty']) . "',\n";
        $out .= "    'map' => array(\n";
        $firstBlank = true;
        foreach ($e['map'] as $lbl => $col) {
            if ($col === '') {
                /* ◆ **وأوّلُ حقلٍ ليس معرِّفًا بالضرورة**: جداولُ المولِّدِ تبدأ
                     تعليقاتُها من الحقلِ الثاني حين يكون الأوّلُ **رقمَ السطرِ
                     نفسَه**، لا حين يكون اسمًا. فمُلئ `id` بحقلٍ اسمُه «نموذج
                     العمل» يعرض معرِّفًا مكانَ نموذج — **خطأُ قراءةٍ لا يُرى**.
                   ⇒ فالملءُ مشروطٌ بأن يقرأ الاسمُ معرِّفًا. */
                $idish = (bool) preg_match('~^(?:#|رقم|كود|معرف|معرّف)~u', $lbl)
                       && (bool) preg_match('~(?:#|قيد|سطر|طلب|رصيد|محضر|تمرين|إقفال|اقفال)~u', $lbl);
                if ($firstBlank && ($lbl === '#' || $idish)) { $col = 'id'; $firstBlank = false; }
                else { $col = '+' . 'gf' . (++$slugI) . ':VARCHAR(190) NULL DEFAULT NULL'; }
            }
            $out .= "        '" . str_replace("'", '', $lbl) . "' => '" . $col . "',\n";
        }
        $out .= "    ),\n),\n";
    }
    $out .= "\n),\n);\n";
    file_put_contents($EMITSPEC, $out);
    echo "  ✔ كُتبت المواصفةُ: " . $EMITSPEC . "\n";
}
