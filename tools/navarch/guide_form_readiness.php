<?php
/**
 * tools/navarch/guide_form_readiness.php — أَيكفي الدليلُ لبناءِ نموذجِ الإضافة؟
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **حكمُ المالك**: «كلُّ ما تحتاجه موجودٌ في الدليلِ المعماريّ». وهذه الأداةُ
 *   **تقيس صدقَ ذلك سطحًا سطحًا قبل أن يُكتب سطرُ نموذج** — ⛔ فلا يُبنى حقلٌ
 *   بالاجتهاد، ولا يُدَّعى نقصٌ بلا قياس.
 *
 * ◆ **والمقامُ سجلٌّ حاكمٌ لا ورقةٌ تُقرأ وقتَ التشغيل**: `repair01_fields` —
 *   7,608 حقلًا مستوعَبًا من «09 · 02_تتبع_الحقول» بمرجعِ خليّةٍ لكلِّ صفّ.
 *   ومفرداتُ `field_type` **تسعٌ مغلقة**، والقابلُ للإدخالِ منها ثلاثةٌ:
 *     · `BUSINESS_INPUT`  «خانة إدخال مفتوحة»
 *     · `REFERENCE`       «قائمة محكومة من بيتها»       ⇒ قائمةٌ منسدلة
 *     · `FK_INHERITED`    «مفتاح موروث — يُختار ولا يُكتب» ⇒ قائمةُ الأب
 *   وما عداها (`DERIVED` · `AUDIT` · `PK_GENERATED` · `IMPORTED_READONLY` ·
 *   `PARENT_INHERITED` · `SNAPSHOT`) **لا يدخل نموذجًا بنصِّ قاعدتِه**.
 *
 * ◆ **وجسرُ الاسمِ إلى العمودِ موجودٌ في الشاشةِ نفسِها**: كتلةُ
 *   `$GUIDE_COLS = array('اسمُ حقلِ الدليل' => 'عمودُ الجدول')` التي يقرؤها
 *   `ems_w14_grid` — **فالخريطةُ واحدةٌ للعرضِ والإدخال**، ⛔ ولا تُكتب ثانيةً
 *   [[declared-column-not-built]].
 *
 * ◆ **والعمودُ يُثبَت في المخطَّطِ قبل أن يُقبَل**: اسمٌ في الخريطةِ بلا عمودٍ
 *   حقيقيٍّ **جسرٌ مكسورٌ** يُعلَن ولا يُبتلع [[finish-round-closure]].
 *
 * التشغيل: php tools/navarch/guide_form_readiness.php
 *   ⇒ docs/REPAIR01_20260823/navarch/GUIDE_FORM_READINESS.json
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
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

/** تسويةٌ عربيّةٌ خفيفةٌ + نزعُ وسمِ القائمةِ/الاشتقاق (▼ ◄) من اسمِ الحقل */
$fz = function ($s) {
    $s = str_replace(array('▼', '◄', '►', '▲'), '', (string) $s);
    $s = str_replace(array('أ', 'إ', 'آ'), 'ا', $s);
    $s = str_replace(array('ة', 'ى', 'ـ', "\xC2\xA0"), array('ه', 'ي', '', ' '), $s);
    $s = preg_replace('~[\x{064B}-\x{0652}]~u', '', $s);
    /* ⭐ **وحاشيةُ الانطباقِ ليست اسمًا** — وهو الفخُّ نفسُه الذي أعمى المسحَ:
       `repair01_fields.surface` يحمل «… — بحسب انطباق الشركة» و`canonical_label_ar`
       مجرَّدٌ منها. ⇒ تُنزَع هنا كما نُزعت هناك [[govui-round-closure]]. */
    $s = preg_replace('~\s*[—–-]\s*بحسب\s+انطباق\s+الشرك[هة]\s*$~u', '', $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
};
$ENTERABLE = array('BUSINESS_INPUT' => 'input', 'REFERENCE' => 'select', 'FK_INHERITED' => 'select');

/* ═══ ① الأسطحُ المطلوبةُ: `SOURCE` بلا نموذجِ إضافةٍ في مسحِ النمط ═══ */
$PA = json_decode((string) @file_get_contents(
    $ROOT . '/docs/REPAIR01_20260823/navarch/SILENT_DROP_PATTERN_AUDIT.json'), true);
if (!is_array($PA)) { exit("⛔ شغّل silent_drop_pattern_audit.php أوّلًا\n"); }

$reg = array();
$r = $conn->query('SELECT screen_id, canonical_label_ar, surface_kind FROM repair01_screen_registry');
while ($x = $r->fetch_assoc()) { $reg[$x['screen_id']] = $x; }

/* ═══ ② حقولُ الدليلِ بالسطح — والمطابقةُ بالاسمِ المُسوّى ═══ */
$fieldsBySurface = array();
$r = $conn->query("SELECT surface, seq, field_name, field_type, visibility_rule, src_ref, requirement_id
                     FROM repair01_fields WHERE surface <> '' ORDER BY surface, CAST(seq AS UNSIGNED), id");
while ($x = $r->fetch_assoc()) { $fieldsBySurface[$fz($x['surface'])][] = $x; }

/* ═══ ③ أعمدةُ كلِّ جدولٍ من المخطَّط — ولا عمودَ يُقبَل بلا وجودٍ مقيس ═══ */
$colsOf = function ($table) use ($conn) {
    static $cache = array();
    if (isset($cache[$table])) { return $cache[$table]; }
    $out = array();
    $q = @$conn->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
    while ($q && ($x = $q->fetch_assoc())) { $out[$x['Field']] = $x; }
    return $cache[$table] = $out;
};

$rows = array(); $ready = 0; $blocked = 0;
foreach ((array) $PA['rows'] as $p) {
    $sid = (string) $p['screen_id'];
    $kind = isset($reg[$sid]) ? (string) $reg[$sid]['surface_kind'] : '';
    if ($kind !== 'SOURCE') { continue; }        /* الإسقاطُ لا يُطالَب بنموذج */

    /* ملفُّ الشاشةِ وخريطتُه وجدولُه */
    $abs = $ROOT . '/' . $p['route'];
    if (!is_file($abs)) {
        foreach ((array) @glob($ROOT . '/' . dirname($p['route']) . '/*.php') as $c2) {
            if (strcasecmp(basename($c2), basename($p['route'])) === 0) { $abs = $c2; break; }
        }
    }
    $src = (string) @file_get_contents($abs);
    /* ⭐ **والمقامُ صار الموصولَ فعلًا**: أوّلُ تشغيلٍ كان مقامُه «ما ينقصه نموذج»،
       فلمّا وُصلت التسعُ والعشرون **فرغ المقامُ وقُرئ صفرًا** — وهو أخضرُ كاذبٌ
       من تغيُّرِ المقامِ لا من تغيُّرِ الحال [[measure-blind-spots]].
       ⇒ يُقاس **مَن يستدعي المكوِّنَ** ومَن ما زال بلا نموذجٍ معًا. */
    $wired = (strpos($src, 'ems_w14_guide_form') !== false);
    $lacks = in_array('④ نموذجُ الإضافة', (array) $p['missing'], true);
    if (!$wired && !$lacks) { continue; }

    /* الجدولُ الحاكم: من `ems_w14_guide_rows('<table>')` أو عقدِ `$U13['table']` */
    $table = '';
    if (preg_match("~ems_w14_guide_rows\s*\(\s*['\"]([a-z0-9_]+)~i", $src, $m)) { $table = $m[1]; }
    elseif (preg_match("~'table'\s*=>\s*'([a-z0-9_]+)'~i", $src, $m)) { $table = $m[1]; }
    /* ◆ **وقارئٌ ثالثٌ للجدولِ الحاكم**: شاشاتُ `W14` تقرأ بـ`w14_rows($is_super, '<table>')`
       — ⛔ ولا يُستنتَج الجدولُ من اسمِ الملفِّ [[nav-route-two-sources]]. */
    elseif (preg_match("~w14_rows\s*\([^,]+,\s*'([a-z0-9_]+)'~i", $src, $m)) { $table = $m[1]; }

    /* خريطةُ `$GUIDE_COLS`: اسمُ حقلِ الدليل ⇒ عمود */
    $map = array();
    if (preg_match('~\$GUIDE_COLS\s*=\s*array\s*\((.*?)\)\s*;~su', $src, $mm)) {
        if (preg_match_all("~'([^']+)'\s*=>\s*'([^']*)'~u", $mm[1], $pairs, PREG_SET_ORDER)) {
            foreach ($pairs as $pr) { $map[$fz($pr[1])] = $pr[2]; }
        }
    }

    /* حقولُ الدليلِ لهذا السطح — بالاسمِ الحاكمِ من الورقةِ لا من السجل */
    /* ⛔ **وحقولُ السطحِ حقولُ سطحِه هو لا حقولُ هدفٍ يخدمه**: `proc_offers`
       سطحُه «عروض الموردين المستلمة» وهو **يخدم** هدفَ «بنود عروض الموردين»
       — فقراءةُ حقولِ المخدومِ تعطي أسماءً لا خريطةَ لها في الشاشة، ويُقرأ
       ذلك «بلا عمود» وهو في الحقيقةِ **سطحٌ آخر** [[grain-measure-shared-kit-trap]].
       ⇒ **الاسمُ المعياريُّ من السجلِّ أوّلًا**، ثمَّ اسمُ الهدفِ ثانيًا. */
    $surfKeys = array();
    if (isset($reg[$sid])) { $surfKeys[] = $fz($reg[$sid]['canonical_label_ar']); }
    $surfKeys[] = $fz($p['label']);
    /* ⛔ **ولا يُرجَّح بالترتيبِ بل بالخريطةِ المقيسة**: السطحُ قد يُسجَّل باسمٍ
       ويُخرَّط باسمِ هدفٍ يخدمه. ⇒ **تُجرَّب أسماؤه كلُّها ويفوز ما تنطبق
       خريطتُه فعلًا** — فلا يتأرجح الحكمُ بترتيبِ مصدرَين [[counter-parity-two-readers]]. */
    $gf = array(); $gfBest = -1; $gfWhich = '';
    foreach ($surfKeys as $k) {
        if (!isset($fieldsBySurface[$k])) { continue; }
        $hit = 0;
        foreach ($fieldsBySurface[$k] as $f) {
            if (!isset($ENTERABLE[$f['field_type']])) { continue; }
            $cc = isset($map[$fz($f['field_name'])]) ? ltrim($map[$fz($f['field_name'])], '@') : '';
            if ($cc !== '' && $cc[0] !== '#') { $hit++; }
        }
        if ($hit > $gfBest) { $gfBest = $hit; $gf = $fieldsBySurface[$k]; $gfWhich = $k; }
    }

    $cols = $table !== '' ? $colsOf($table) : array();
    $enter = array(); $noCol = array(); $skipped = 0;
    foreach ($gf as $f) {
        if (!isset($ENTERABLE[$f['field_type']])) { $skipped++; continue; }
        $nm = $fz($f['field_name']);
        $col = isset($map[$nm]) ? $map[$nm] : '';
        /* ◆ **ولغةُ الخريطةِ ثلاثةُ أشكال** (`includes/w14_grid.php` سطر 57-62):
             · `col`   عمودٌ خام
             · `@col`  **العمودُ نفسُه** يُعرَض معرَّبًا بـ`ems_w14_ar()` — وهي
                       بالضبطِ أعمدةُ «القائمةِ المحكومة» (‏ENUM بمفرداتٍ تقنيّة)
             · `#key`  دالّةٌ مشتقّةٌ من `$D` — **ليست عمودًا ولا تُدخَل**
           ⛔ فرفضُ `@col` رفضٌ لعمودٍ قائمٍ — وهو ما وقع في أوّلِ قياس. */
        $arabized = false;
        if ($col !== '' && $col[0] === '@') { $col = substr($col, 1); $arabized = true; }
        if ($col !== '' && $col[0] === '#') { $col = ''; }
        /* عمودُ الشبكةِ قد يكون رمزًا (`g7`) لا عمودًا — يُثبَت في المخطَّط */
        if ($col === '' || !isset($cols[$col])) {
            $noCol[] = $f['field_name'] . ' (' . $f['field_type'] . ($col !== '' ? ' ⇒ ' . $col . ' غيرُ موجود' : ' ⇒ لا خريطة') . ')';
            continue;
        }
        $enter[] = array('label' => $f['field_name'], 'type' => $f['field_type'],
                         'control' => $ENTERABLE[$f['field_type']], 'column' => $col,
                         'arabized' => $arabized,
                         'col_type' => $cols[$col]['Type'], 'null' => $cols[$col]['Null'],
                         'rule' => $f['visibility_rule'], 'src' => $f['src_ref']);
    }

    /* ⭐ **ونمطُ الترقيمِ حاجزُ أوّلِ سطر**: حقلُ `PK_GENERATED` بلا نمطٍ مقيسٍ
       (‏صفوفٌ قائمةٌ) ولا نطاقٍ مسجَّلٍ في `ems_sequences` **يمنع أوّلَ إدخال** —
       وورقةُ `DEP-08` تنصُّ أنَّ الحوكمةَ تملك «نمطَ الترقيم»، ⛔ فلا يُخترَع. */
    $genCols = array(); $needSeq = array();
    foreach ($gf as $f2) {
        if ($f2['field_type'] !== 'PK_GENERATED') { continue; }
        $gc = isset($map[$fz($f2['field_name'])]) ? ltrim($map[$fz($f2['field_name'])], '@') : '';
        if ($gc === '' || $table === '' || !isset($cols[$gc])) { continue; }
        $genCols[] = $gc;
        $qq = @$conn->query('SELECT `' . $gc . '` FROM `' . $table . '` WHERE `' . $gc . "` <> '' LIMIT 1");
        $hasRows = ($qq && $qq->num_rows);
        $qs = @$conn->query("SELECT 1 FROM ems_sequences WHERE scope LIKE '"
                          . $conn->real_escape_string($table) . ":%' LIMIT 1");
        $hasScope = ($qs && $qs->num_rows);
        if (!$hasRows && !$hasScope) { $needSeq[] = $gc; }
    }

    $v = (!$gf) ? 'NO_GUIDE_FIELDS'
       : (($table === '') ? 'NO_TABLE'
       : ((!$map) ? 'NO_GUIDE_COLS_MAP'
       : ((!$enter) ? 'NO_ENTERABLE_COLUMN' : 'READY')));
    if ($v === 'READY') { $ready++; } else { $blocked++; }

    $rows[] = array('ws' => $p['ws'], 'screen_id' => $sid, 'route' => $p['route'],
                    'label' => $p['label'], 'table' => $table,
                    'guide_surface' => $gfWhich, 'wired' => $wired,
                    'generated_cols' => $genCols, 'needs_sequence' => $needSeq,
                    'guide_fields' => count($gf), 'not_enterable' => $skipped,
                    'enterable' => $enter, 'unmapped' => $noCol, 'verdict' => $v);
}

$dir = $ROOT . '/docs/REPAIR01_20260823/navarch';
file_put_contents($dir . '/GUIDE_FORM_READINESS.json', json_encode(array(
    'measured_at' => date('c'),
    'snapshot' => trim((string) shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD')),
    'source_of_truth' => 'repair01_fields (‏09 · 02_تتبع_الحقول) + $GUIDE_COLS في الشاشة + مخطَّطُ الجدول',
    'ready' => $ready, 'blocked' => $blocked, 'rows' => $rows,
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "══ أَيكفي الدليلُ لبناءِ نموذجِ الإضافة؟ ══\n";
foreach ($rows as $x) {
    printf("  %-8s %-11s %-40s %-20s حقول %2d · قابلٌ للإدخال %2d · بلا عمود %2d\n",
        $x['ws'], $x['screen_id'], mb_substr($x['route'], 0, 38), $x['verdict'],
        $x['guide_fields'], count($x['enterable']), count($x['unmapped']));
}
printf("\n  جاهزٌ للبناءِ من الدليل: **%d** · محجوبٌ بسببٍ مُسمًّى: **%d** من %d\n",
    $ready, $blocked, count($rows));
echo "  => {$dir}/GUIDE_FORM_READINESS.json\n";
