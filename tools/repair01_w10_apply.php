<?php
/**
 * tools/repair01_w10_apply.php — أداةُ المرحلةِ العاشرة (شقُّ الوحدة)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الشقُّ يُكتب في السجلِّ ثمَّ يُطبَّق على الدفترَين معًا**: `repair01_surfaces`
 *   دفترُ الدراسةِ و`repair01_screen_registry` سجلُّ الشاشاتِ المشتقُّ من القرص.
 *   **وشقٌّ يُطبَّق على أحدِهما يترك نصفَ النظامِ على الحكمِ القديم** — والسجلّانِ
 *   اليومَ متفرِّقانِ فعلًا: أحدُهما يقول ١١١/١٢ والآخرُ ٩٩/٥٨.
 *
 * ◆ **ولا مفتاحَ يُمَسّ**: `screen_id` و`route` و`owner_role` تبقى حرفًا. المتغيّرُ
 *   `owner_code` و`canonical_code` وحدَهما — **رمزُ الملكيّةِ لا مُعرِّفُ الصفّ**.
 *   و`repair01_target_gaps.unit` **لا يُدهَس** (‏`W1-10` يعُدُّه) بل يُكتب الشقُّ
 *   في `split_code` إلى جانبِه.
 *
 * ◆ **والمفرداتُ تُشتَقُّ من المخزنِ في كلِّ تشغيل** — ولا قائمةَ أسماءِ ملفّاتٍ
 *   في هذا الملفِّ ولا في مكتبتِه.
 *
 * التشغيل : php tools/repair01_w10_apply.php  [--dry]
 * التراجع : php tools/repair01_w10_apply.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w10_scan.php';
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$argvv  = $argv;
$DRY    = in_array('--dry', $argvv, true);
$REVERT = in_array('--revert', $argvv, true);
$E = function ($s) use ($conn) { return "'" . $conn->real_escape_string((string) $s) . "'"; };
$q = function ($sql) use ($conn) {
    if ($conn->query($sql) === false) { echo "  ✘ " . $conn->error . "\n    " . mb_substr($sql, 0, 160) . "\n"; return false; }
    return true;
};
$one = function ($sql) use ($conn) { return repair01_w10_one($conn, $sql); };

/* ══════════════════════════════════════════════════════════════════════════
   التراجعُ — يعيد الملكيّاتِ إلى ما قبلَ الشقِّ من دفترِ الشقِّ نفسِه
   ══════════════════════════════════════════════════════════════════════════ */
if ($REVERT) {
    echo "═══ تراجعُ W10 — إرجاعُ الملكيّاتِ من دفترِ الشقّ ═══\n";
    $n = 0;
    $r = $conn->query("SELECT scope_key, surf_code_before, reg_code_before, in_surfaces, in_registry,
                              moved_surface, moved_registry FROM repair01_w10_split");
    while ($r && $x = $r->fetch_assoc()) {
        if ((int) $x['moved_surface'] === 1 && $x['surf_code_before'] !== '') {
            $q("UPDATE repair01_surfaces SET canonical_code = " . $E($x['surf_code_before'])
               . " WHERE screen_id = " . $E($x['scope_key'])); $n++;
        }
        if ((int) $x['moved_registry'] === 1 && $x['reg_code_before'] !== '') {
            $q("UPDATE repair01_screen_registry SET owner_code = " . $E($x['reg_code_before'])
               . " WHERE screen_id = " . $E($x['scope_key'])); $n++;
        }
    }
    $q("UPDATE repair01_target_gaps SET split_code = '', split_rule = '', split_why = ''");
    foreach (array('repair01_w10_journey', 'repair01_w10_sidebar', 'repair01_w10_bridge',
                   'repair01_w10_split', 'repair01_w10_vocab') as $t) { $q("DELETE FROM `$t`"); }
    $q("DELETE FROM repair01_events WHERE wave = 'W10'");
    echo "أُرجع $n إسنادًا · وأُفرغت سجلّاتُ المرحلة\n";
    exit(0);
}

echo "═══ REPAIR01 · W10 — شقُّ المالية والخزينة ومكتبِ الرئيس ═══\n";
echo ($DRY ? "◆ تشغيلٌ جافٌّ — لا كتابة\n" : "") . "\n";

/* ══ ① أوزانُ الأسبقيّةِ — سجلٌّ يُقرأ ولا يُكتب في شيفرة ═══════════════ */
echo "① أوزانُ الأسبقيّة\n";
$TH = array(
    array('W10_W_REQUIREMENT_SURFACE', 1000,
        'سطحُ المتطلَّبِ أقوى دليلٍ على الشقِّ لأنّه اسمُ السطحِ في المستهدَفِ نفسِه، والمستهدَفُ يفصل الوحدتَين سلفًا',
        'repair01_requirements · وحدتا 05 و06 و E1 و E2'),
    array('W10_W_CROSSWALK_CLAUSE', 500,
        'بندُ قاعدةِ الشقِّ في الجسرِ نصُّ القرارِ لا اجتهادَ فيه — وهو أضعفُ من اسمِ السطحِ لأنّه وصفُ مجالٍ لا اسمُ شاشة',
        'repair01_dept_crosswalk.split_rule'),
    array('W10_W_DEPARTMENT_NAME', 100,
        'اسمُ الإدارةِ المعياريُّ آخرُ ما يُرجَّح به — فورودُه في اسمِ سطحٍ قد يكون وصفَ نطاقٍ لا إسنادَ ملكيّة',
        'repair01_departments.name_ar'),
    array('W10_MIN_TOKEN_WORDS', 2,
        'المفردةُ المفردةُ تشقُّ بالخطأ: «الحسابات» في دليلِ الحساباتِ وفي الحساباتِ البنكية، و«الصرف» في أسعارِ الصرفِ وفي تنفيذِ الصرف',
        'W10-D-01 · قاعدةُ الاشتقاق'),
);
if (!$DRY) {
    foreach ($TH as $t) {
        $q("INSERT INTO repair01_w10_thresholds (threshold_key, value_num, why, ref)
             VALUES (" . $E($t[0]) . ", " . (int) $t[1] . ", " . $E($t[2]) . ", " . $E($t[3]) . ")
             ON DUPLICATE KEY UPDATE value_num = VALUES(value_num), why = VALUES(why), ref = VALUES(ref)");
    }
}
echo "   عتباتٌ مسجَّلة " . count($TH) . "\n";

/* ══ ② اشتقاقُ المفرداتِ من المخزن ════════════════════════════════════ */
echo "\n② مفرداتُ الشقِّ المشتقّةُ من المخزن\n";
$vocab = repair01_w10_derive_vocab($conn);
$uniq = array();
foreach ($vocab as $v) {
    $k = $v['norm'] . '|' . $v['side'];
    if (!isset($uniq[$k]) || $v['weight'] > $uniq[$k]['weight']) { $uniq[$k] = $v; }
}
if (!$DRY) {
    $q("DELETE FROM repair01_w10_vocab");
    foreach ($uniq as $v) {
        $q("INSERT INTO repair01_w10_vocab (token, token_norm, word_count, side, weight, src_kind, src_ref)
             VALUES (" . $E($v['token']) . ", " . $E($v['norm']) . ", " . (int) $v['wc'] . ", "
             . $E($v['side']) . ", " . (int) $v['weight'] . ", " . $E($v['kind']) . ", " . $E($v['ref']) . ")");
    }
}
$bySide = array();
foreach ($uniq as $v) { $bySide[$v['side']] = ($bySide[$v['side']] ?? 0) + 1; }
ksort($bySide);
echo "   مفرداتٌ " . count($uniq) . " ⇐ ";
foreach ($bySide as $s => $c) { echo "$s:$c  "; }
echo "\n";

/* ══ ③ الحلُّ الكاملُ وكتابةُ دفترِ الشقّ ══════════════════════════════ */
echo "\n③ دفترُ الشقّ\n";
$res = repair01_w10_resolve_all($conn);
$byRule = array(); $byCode = array();
foreach ($res as $v) {
    $byRule[$v['split_rule']] = ($byRule[$v['split_rule']] ?? 0) + 1;
    $byCode[$v['resolved_code']] = ($byCode[$v['resolved_code']] ?? 0) + 1;
}
$movedS = 0; $movedR = 0; $arb = repair01_w10_arbitrary_rows($conn);
if (!$DRY) { $q("DELETE FROM repair01_w10_split"); }
foreach ($res as $k => $v) {
    $ms = ((int) $v['in_surfaces'] === 1 && $v['surf_code_before'] !== $v['resolved_code']) ? 1 : 0;
    $mr = ((int) $v['in_registry'] === 1 && $v['reg_code_before'] !== $v['resolved_code']) ? 1 : 0;
    $movedS += $ms; $movedR += $mr;
    $ab = isset($arb[$k]) ? 1 : 0;
    if ($DRY) { continue; }
    $q("INSERT INTO repair01_w10_split (scope_key, route, screen_file, title, legacy_unit,
            in_surfaces, in_registry, surf_code_before, reg_code_before, resolved_code,
            split_rule, split_why, anchor_ref, matched_token, serves_both,
            moved_surface, moved_registry, arbitrary_before)
        VALUES (" . $E($k) . ", " . $E($v['route']) . ", " . $E($v['screen_file']) . ", "
        . $E(mb_substr($v['title'] !== '' ? $v['title'] : $v['canonical_ar'], 0, 250)) . ", "
        . $E($v['legacy_unit']) . ", " . (int) $v['in_surfaces'] . ", " . (int) $v['in_registry'] . ", "
        . $E($v['surf_code_before']) . ", " . $E($v['reg_code_before']) . ", " . $E($v['resolved_code']) . ", "
        . $E($v['split_rule']) . ", " . $E(mb_substr($v['split_why'], 0, 390)) . ", "
        . $E(mb_substr($v['anchor_ref'], 0, 185)) . ", " . $E(mb_substr($v['matched_token'], 0, 185)) . ", "
        . (int) $v['serves_both'] . ", $ms, $mr, $ab)");
}
echo "   أسطحُ النطاق " . count($res) . " · انتقالٌ في دفترِ الأسطح $movedS · في سجلِّ الشاشات $movedR\n";
echo "   بالحكم: ";
ksort($byCode); foreach ($byCode as $c => $n) { echo "$c:$n  "; }
echo "\n   بالقاعدة:\n";
arsort($byRule); foreach ($byRule as $r2 => $n) { printf("     %-34s %d\n", $r2, $n); }

/* ══ ④ تطبيقُ الشقِّ على الدفترَين ═════════════════════════════════════ */
echo "\n④ تطبيقُ الشقِّ على الدفترَين\n";
$appS = 0; $appR = 0; $skipDecided = array();
if (!$DRY) {
    foreach ($res as $k => $v) {
        if ((int) $v['in_surfaces'] === 1 && $v['surf_code_before'] !== $v['resolved_code']) {
            $q("UPDATE repair01_surfaces SET canonical_code = " . $E($v['resolved_code'])
               . ", canon_rule = " . $E(mb_substr($v['split_rule'], 0, 46))
               . ", canon_why = " . $E(mb_substr($v['split_why'], 0, 390))
               . " WHERE screen_id = " . $E($k) . " AND dept_legacy = " . $E($v['legacy_unit']));
            $appS++;
        }
        if ((int) $v['in_registry'] === 1 && $v['reg_code_before'] !== $v['resolved_code']) {
            /* ⛔ **الاشتقاقُ لا يدهس القرارَ المسجَّل** (RPR-AMD01): حكمت
                 `W15 §٤-٢` في أربعةِ أسطحٍ بعينِها أنَّ مالكَها إدارتُها لا
                 مكتبَ الرئيس (`SCR-0505` · `SCR-0508` · `SCR-0515` · `SCR-0532`)،
                 وشقُّ W10 يُعيد اشتقاقَها `EX-CEO` في كلِّ تشغيل. فكانت السلسلةُ
                 تتأرجح: **W10 تُعيدها و`W15` تردُّها ولا تبلغ نقطةَ ثبات** —
                 وقِيس ذلك حقنًا بتتبُّعِ الأربعةِ موجةً موجة.
               ⇒ **ما حُكم فيه بقاعدةٍ مكتوبةٍ يُصان**، وما عداه يُشتقّ كما كان،
                 **والدفترُ يسجّل التخطّي فلا يصمُت عنه.** */
            $decided = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                                    WHERE screen_id = " . $E($k) . " AND verdict_rule LIKE 'RPR-W15%'");
            if ($decided > 0) { $skipDecided[] = $k; continue; }
            $q("UPDATE repair01_screen_registry SET owner_code = " . $E($v['resolved_code'])
               . ", owner_rule = " . $E(mb_substr('W10_SPLIT:' . $v['split_rule'], 0, 46))
               . " WHERE screen_id = " . $E($k));
            $appR++;
        }
    }
}
echo "   طُبِّق على دفترِ الأسطح $appS · على سجلِّ الشاشات $appR\n";

/* ══ ⑤ شقُّ دفترِ الفجواتِ — بعمودٍ إلى جانبِ الوحدةِ لا بدهسِها ═══════ */
echo "\n⑤ شقُّ دفترِ الفجوات\n";
$vocabRows = array();
$r = $conn->query("SELECT token, token_norm, word_count, side, weight, src_kind, src_ref FROM repair01_w10_vocab");
while ($r && $x = $r->fetch_assoc()) {
    $vocabRows[] = array('token' => $x['token'], 'norm' => $x['token_norm'], 'wc' => (int) $x['word_count'],
                         'side' => $x['side'], 'weight' => (int) $x['weight'], 'kind' => $x['src_kind'],
                         'ref' => $x['src_ref']);
}
$units = repair01_w10_split_units($conn);
$gapStat = array();
$r = $conn->query("SELECT id, unit, surface_name FROM repair01_target_gaps WHERE unit IN ("
    . implode(',', array_map($E, array_keys($units))) . ")");
$gapRows = array();
while ($r && $x = $r->fetch_assoc()) { $gapRows[] = $x; }
foreach ($gapRows as $g) {
    $sides = array();
    foreach ($units[$g['unit']] as $code => $_) { $sides[$code] = true; }
    $parent = array_keys($sides); sort($parent); $parent = $parent[0];
    $name = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', (string) $g['surface_name']));
    $txt = repair01_w10_norm($name);
    $c = repair01_w10_classify($txt, $vocabRows, $sides);
    if ($c['side'] === '') {
        $code = $parent; $rule = 'W10_PARENT_DEFAULT';
        $why  = 'اسمُ السطحِ المستهدَفِ لم يطابق مفردةَ شقٍّ — فيبقى مع الشقِّ الأمِّ مُعلَنًا بعددِه';
    } else {
        $code = $c['side']; $rule = $c['rule']; $why = $c['why'];
    }
    $gapStat[$code] = ($gapStat[$code] ?? 0) + 1;
    if (!$DRY) {
        $q("UPDATE repair01_target_gaps SET split_code = " . $E($code) . ", split_rule = " . $E($rule)
           . ", split_why = " . $E(mb_substr($why, 0, 390)) . " WHERE id = " . (int) $g['id']);
    }
}
echo "   فجواتُ الوحدتَينِ المشقوقتَين " . count($gapRows) . " ⇐ ";
ksort($gapStat); foreach ($gapStat as $c => $n) { echo "$c:$n  "; }
echo "\n";

/* ══ ⑥ الجسرُ — كلُّ مؤشِّرٍ حيٍّ يسمّي الوحدةَ الأمَّ باسمِها القديم ═══ */
echo "\n⑥ الجسر\n";
$splitByRoute = array(); $splitByStem = array();
$r = $conn->query("SELECT scope_key, route, screen_file, resolved_code FROM repair01_w10_split");
while ($r && $x = $r->fetch_assoc()) {
    if ($x['route'] !== '') { $splitByRoute[mb_strtolower(str_replace('\\', '/', $x['route']))] = $x['resolved_code']; }
    $splitByStem[repair01_w10_stem($x['route'] !== '' ? $x['route'] : $x['screen_file'])] = $x['resolved_code'];
}
$bridgeN = 0; $bridgeStat = array(); $dupPointer = 0; $seenPointer = array();
if (!$DRY) { $q("DELETE FROM repair01_w10_bridge"); }
foreach (repair01_w10_pointer_sources() as $src) {
    $t = $src['table']; $col = $src['col']; $key = $src['key'];
    $exists = repair01_w10_one($conn, "SHOW TABLES LIKE " . $E($t));
    if ($exists === null) { echo "   ↷ $t غيرُ موجود\n"; continue; }
    $r = $conn->query("SELECT `$key` AS k, `$col` AS d FROM `$t`
                        WHERE `$col` IN (" . implode(',', array_map($E, array_keys($units))) . ")");
    while ($r && $x = $r->fetch_assoc()) {
        $legacy = (string) $x['d'];
        $sides = array();
        foreach ($units[$legacy] as $code => $_) { $sides[$code] = true; }
        $parent = array_keys($sides); sort($parent); $parent = $parent[0];
        $kk = (string) $x['k'];
        $lk = mb_strtolower(str_replace('\\', '/', $kk));
        if (isset($splitByRoute[$lk])) {
            $code = $splitByRoute[$lk]; $rule = 'W10_BRIDGE_BY_SURFACE';
            $why  = 'المؤشِّرُ مسارُ سطحٍ محسومٍ في دفترِ الشقِّ — فالجسرُ يقرأ حكمَه ولا يجتهد';
        } elseif (isset($splitByStem[repair01_w10_stem($kk)])) {
            $code = $splitByStem[repair01_w10_stem($kk)]; $rule = 'W10_BRIDGE_BY_STEM';
            $why  = 'المؤشِّرُ ملفٌّ جذعُه محسومٌ في دفترِ الشقّ';
        } else {
            $code = $parent; $rule = 'W10_BRIDGE_PARENT_ENTRY';
            $why  = 'مؤشِّرٌ لا يقابله سطحٌ محسوم — يدخل من الشقِّ الأمِّ، والشقُّ المنفِّذُ يراه إسقاطًا فوقَ طلباتِ الإدارات لا ملكيّةً جديدة';
        }
        $probe = "SELECT COUNT(*) FROM `$t` WHERE `$key` = " . $E($kk) . " AND `$col` = " . $E($legacy);
        if (isset($seenPointer[$t . '|' . $kk])) { $dupPointer++; continue; }
        $seenPointer[$t . '|' . $kk] = true;
        $bridgeStat[$t . ':' . $code] = ($bridgeStat[$t . ':' . $code] ?? 0) + 1;
        $bridgeN++;
        if ($DRY) { continue; }
        $q("INSERT INTO repair01_w10_bridge (host_table, pointer_col, pointer_key, legacy_name,
                resolved_code, bridge_rule, bridge_why, probe_sql)
             VALUES (" . $E($t) . ", " . $E($col) . ", " . $E(mb_substr($kk, 0, 195)) . ", " . $E($legacy) . ", "
             . $E($code) . ", " . $E($rule) . ", " . $E(mb_substr($why, 0, 390)) . ", " . $E(mb_substr($probe, 0, 590)) . ")
             ON DUPLICATE KEY UPDATE resolved_code = VALUES(resolved_code), bridge_rule = VALUES(bridge_rule),
                bridge_why = VALUES(bridge_why), probe_sql = VALUES(probe_sql)");
    }
}
echo "   مؤشِّراتٌ حيّةٌ مترجَمة $bridgeN ⇐ ";
ksort($bridgeStat); foreach ($bridgeStat as $k2 => $n) { echo "$k2:$n  "; }
echo "\n";

/* ══ ⑦ السايدبار — سبعُ خطواتٍ بترتيبِها قبل أيِّ بناء ═════════════════ */
echo "\n⑦ السايدبار — سبعُ خطوات\n";
require_once $ROOT . '/tools/lib/repair01_w10_sidebar.php';
$sb = repair01_w10_sidebar_apply($conn, $ROOT, $res, $DRY);
printf("   أسطحٌ في الدفتر %d · مُعطَّلٌ بعذرٍ %d · اسمٌ صُحِّح %d · مجموعةٌ صُحِّحت %d\n",
    $sb['rows'], $sb['s1_off'], $sb['s2_fix'], $sb['s3_fix']);
printf("   ترتيبٌ من السجلِّ %d · أبٌ أو تبويبٌ %d · منحُ صلاحيةٍ %d · مربوطٌ بالمعياريّ %d\n",
    $sb['s4_ok'], $sb['s5_tab'], $sb['s6_grants'], $sb['s7_linked']);

/* ══ ⑧ آلاتُ الحالةِ وفصلُ الواجباتِ والقراراتُ وعقودُ الأثر ═══════════ */
echo "\n⑧ الحوكمة\n";
require_once $ROOT . '/tools/lib/repair01_w10_contracts.php';
$gov = repair01_w10_contracts_apply($conn, $res, $DRY, array(
    'two_axes' => $sb['two_axes'], 'no_guard' => $sb['s6_no_guard'],
    'moved_surface' => $movedS, 'moved_registry' => $movedR, 'arbitrary' => count($arb),
    'parent_default' => $byRule['W10_PARENT_DEFAULT'] ?? 0,
    'shared' => $byRule['W10_W01_SHARED_KEPT'] ?? 0,
    'dvp_target_only' => $byRule['W10_DVP_IS_TARGET_ONLY'] ?? 0,
    'bridge' => $bridgeN, 'dup_pointer' => $dupPointer,
));
printf("   آلاتُ حالةٍ %d كيانًا · %d انتقالًا (‏ممنوعٌ صراحةً %d) · فصلُ واجباتٍ %d عمليّة\n",
    $gov['entities'], $gov['transitions'], $gov['forbidden'], $gov['sod']);
printf("   قراراتٌ %d · عقودُ أثرٍ %d\n", $gov['decisions'], $gov['events']);

echo "\n───────────────────────────────────────────────────────────────\n";
echo $DRY ? "تشغيلٌ جافٌّ — لم يُكتب شيء\n" : "تمَّت الكتابة\n";
exit(0);
