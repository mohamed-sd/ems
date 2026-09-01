<?php
/**
 * tools/navarch/silent_drop_pattern_audit.php — مواءمةُ النمطِ بشاشةِ العملاء
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **النمطُ الحاكمُ مقيسٌ من ملفِّه لا موصوفٌ من ذاكرة** (SILENT_DROP_FIX §2):
 *   `Clients/clients.php` — العُدّةُ السبعُ والأقسامُ الأربعةُ وثلاثةٌ لا تُنسى.
 *   ⛔ **ولا يُقاس سطحٌ بمفردةٍ لا وجودَ لها في المرجع** [[measure-token-must-exist]]،
 *   فكلُّ مفردةٍ هنا **مُثبتةٌ في المرجعِ نفسِه أوّلًا** ويرسُب القياسُ إن غابت.
 *
 * ◆ **⛔ والمطابقةُ ليست إلزامًا شاملًا**: النمطُ يصف **شاشةَ سجلٍّ كاملة**
 *   (‏جدولٌ وإحصاءٌ وفلاترُ ونموذجُ إضافة). ولوحةٌ (`Dashboard`) أو تقريرٌ
 *   مشتقٌّ (`Derived`) **لا نموذجَ إضافةٍ له بحكمِ نوعِه في الدليل** — فمطالبتُه
 *   به مطالبةُ نوعٍ بعقدِ غيرِه [[iaf-field-closure]]. ⇒ **فالمتوقَّعُ يُشتَقُّ
 *   من نوعِ بطاقةِ الدليلِ**، ويُطبع النوعُ مع الحكمِ في كلِّ صفّ.
 *
 * ◆ **وما ينقص يُسمّى ولا يُضاف آليًّا**: هذه أداةُ قياسٍ لا مولِّدُ شيفرة —
 *   والإضافةُ تُكتب في الشاشةِ بيدٍ وتُقاس هنا ثانيةً.
 *
 * التشغيل: php tools/navarch/silent_drop_pattern_audit.php
 *   ⇒ docs/REPAIR01_20260823/navarch/SILENT_DROP_PATTERN_AUDIT.json
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__, 2));
require_once $ROOT . '/tools/lib/rpr02a_guide.php';
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

/* ═══ ① مفرداتُ النمطِ — وكلٌّ تُثبَت في المرجعِ قبل أن تُقاس على غيرِه ═══ */
$REF = $ROOT . '/Clients/clients.php';
$refSrc = (string) @file_get_contents($REF);
if ($refSrc === '') { exit("⛔ المرجعُ مفقود: Clients/clients.php\n"); }

/* ⭐ **ومفردةُ الدَّورِ لا مفردةُ الاسم**: للدارِ لهجتانِ مقيستانِ لكلِّ قسمٍ —
   الأقدمُ في المرجعِ (`stats-card` · `class="filter"`) والأحدثُ في الشاشاتِ
   المبنيّةِ بعدَه (`ems-stat-card` · `ems_filter_box()`)، **وكلتاهما العُدّةُ
   الموحَّدةُ نفسُها**: `assets/js/ems-statcards.js` **يسم البطاقةَ بالدَّورِ لا
   بالاسم** (‏43 اسمًا في 84 صفحةً ⇒ خُطّافٌ واحد `ems-statcard`)، و
   `includes/ems_filter_box.php` **يطبع بنيةَ `filter-body` نفسَها**.
   ⛔ **فقياسُ لهجةِ المرجعِ وحدَها يُدين البناءَ الأحدثَ ظلمًا** — قِيس أثرُه:
   69 من 69 «ناقصةً» في أوّلِ تشغيل، وهي حمرةٌ كاذبةٌ من المقياسِ لا عطبٌ في
   المبنيِّ [[measure-blind-spots]]. ⇒ **فلكلِّ قسمٍ مفرداتُ لهجتَيه معًا.** */
$SIG = array(
    /* العُدّةُ السبع (§2·1) */
    'kit_session'   => array('~session_bootstrap\\.php~',            'العُدّة · مخزنُ الجلسة'),
    'kit_config'    => array('~config\\.php|inheader\\.php~',         'العُدّة · التهيئة'),
    'kit_contract'  => array('~screen_contract\\.php~',              'العُدّة · عقدُ الشاشة'),
    'kit_grid'      => array('~w14_grid\\.php|w14_view\\.php~',       'العُدّة · شبكةُ العرض'),
    'kit_tabs'      => array('~entity_tabs\\.php|sales_family_tabs\\.php~', 'العُدّة · تبويبُ الكيان'),
    'kit_xf'        => array('~extra_fields\\.php~',                 'العُدّة · الحقولُ الإضافية'),
    'kit_excel'     => array('~excel_ui\\.php|ems_excel_~',          'العُدّة · زرُّ الإكسل'),
    'kit_sidebar'   => array('~insidebar\\.php~',                    'العُدّة · القشرة'),
    /* الأقسامُ الأربعة (§2·2) — بلهجتَيهما */
    'sec_table'     => array('~ems_w14_grid\\(|table-container|<table~i', '① الجدول'),
    'sec_stats'     => array('~(^|[\\s"\'-])(stats?-(card|section)|statcards?|statsection'
                           . '|kpi-card|kpi-box|metric-box|metrics-|tile-card)~i', '② بطاقاتُ الإحصاء'),
    'sec_filters'   => array('~filter-title|filter-body|filter-field|ems_filter_box\\('
                           . '|ems-filter-box|ems-filters|class=["\']filter["\']~i', '③ الفلاتر'),
    'sec_form'      => array('~allforms|ems-form|u13-act~',         '④ نموذجُ الإضافة'),
    /* ثلاثةٌ لا تُنسى (§2·3) */
    'x_modal'       => array('~EmsDetailsModal~',                   'EmsDetailsModal'),
    /* ◆ **والجدولُ يُهيَّأُ مركزيًّا**: `assets/js/ui-unification.js::initializeMissingDataTables`
       يهيّئُ كلَّ جدولٍ لا يحمل `data-no-dt`، و`ems_w14_grid()` **يطبعُ `<table>`
       بنفسِه** (‏سطر 46). ⇒ فوجودُ الجدولِ إثباتُ التهيئةِ، ⛔ ولا يُطالَبُ
       بتهيئةٍ يدويّةٍ صارت مركزيّة [[datatables-global-statesave]]. */
    'x_datatable'   => array('~\\.DataTable\\(|dataTable|datatables|<table|ems_w14_grid\\(~i', 'DataTable'),
    'x_about'       => array('~ems_screen_about~',                  'عن الشاشة'   /* الآليّةُ واليدويّةُ سواءٌ — كلتاهما تطبع البطاقة */),
);

$missingInRef = array();
foreach ($SIG as $k => $d) { if (!preg_match($d[0], $refSrc)) { $missingInRef[] = $k . ' (' . $d[1] . ')'; } }
if ($missingInRef) {
    /* ⛔ حارسُ المفردات: مفردةٌ لا يحملها المرجعُ لا تُقاس على غيرِه */
    exit("⛔ مفرداتٌ غائبةٌ عن المرجعِ نفسِه — القياسُ باطل:\n  · " . implode("\n  · ", $missingInRef) . "\n");
}

/* ═══ ② نوعُ بطاقةِ الدليلِ لكلِّ هدفٍ — فالمتوقَّعُ من النوعِ لا من الرغبة ═══ */
$cardType = array();          /* ws|idx ⇒ نوعُ البطاقة */
foreach (rpr02a_read_cards($ROOT . '/docs/REPAIR01_20260823/01 · الدليل المعماري.xlsx') as $c) {
    $cardType[$c['code'] . '|' . $c['idx']] = $c['type'];
}
$EXMAP = array('DEP-01' => 'EX-CEO', 'DEP-02' => 'EX-DVP');
foreach (rpr02a_read_cards($ROOT . '/docs/REPAIR01_20260823/02 · القيادة.xlsx') as $c) {
    if (!isset($EXMAP[$c['code']])) { continue; }
    $cardType[$EXMAP[$c['code']] . '|' . $c['idx']] = $c['type'];
}

/* ═══ ②-ب كياناتُ الإكسل المسجَّلة — ⛔ **ولا يُطالَب بزرٍّ لكيانٍ غيرِ مسجَّل** ═══
   ◆ `includes/excel_ui.php` **ليس عُدّةً عامّةً تُحقَن في أيِّ شاشة**: أزرارُه
     مشتقّةٌ من `ExcelRegistry` — نموذجٌ وتصديرٌ واستيرادٌ **لكيانٍ معرَّفٍ
     بأعمدتِه**. فمطالبةُ شاشةٍ لا كيانَ لها في السجلِّ بزرِّ إكسلٍ **مطالبةٌ
     بتسجيلِ كيانٍ جديد** — وهو قرارُ بناءٍ لا نقصُ نمط.
   ⇒ **فالزرُّ يُنتظَر ممّن جدولُه مسجَّلٌ وحدَه**، ويُعلَن للباقي `غيرُ منطبق`. */
$xlTables = array();
$xlSrc = (string) @file_get_contents($ROOT . '/app/Services/Excel/ExcelRegistry.php');
if (preg_match_all('~new EntityDefinition\\(\\"([a-z0-9_]+)\\",[^,]+,\s*\\"([a-z0-9_]+)\\"~i', $xlSrc, $mx, PREG_SET_ORDER)) {
    foreach ($mx as $m) { $xlTables[strtolower($m[2])] = $m[1]; }
}

/** المتوقَّعُ من نوعِ البطاقة — ⛔ ولا يُطالَب نوعٌ بعقدِ غيرِه */
$expectFor = function ($type) {
    $t = mb_strtolower((string) $type);
    $base = array('kit_session', 'kit_config', 'kit_contract', 'kit_sidebar', 'x_about');
    if (strpos($t, 'dashboard') !== false || strpos($t, 'derived') !== false
        || strpos($t, 'periodic') !== false || strpos($t, 'exception') !== false) {
        /* لوحةٌ/مشتقٌّ/دوريّ: عرضٌ لا إدخال — جدولٌ وإحصاءٌ وفلاترُ بلا نموذج */
        return array_merge($base, array('sec_table', 'sec_stats', 'sec_filters'));
    }
    if (strpos($t, 'child') !== false) {
        /* تبويبٌ تابعٌ: جدولٌ ونموذجٌ داخلَ أبٍ — ولا إحصاءَ مستقلًّا يُطالَب به */
        return array_merge($base, array('kit_grid', 'sec_table', 'sec_form'));
    }
    /* سجلٌّ/معاملةٌ/مرجعٌ كامل: النمطُ بتمامِه */
    return array_merge($base, array('kit_grid', 'kit_excel', 'sec_table', 'sec_stats',
                                    'sec_filters', 'sec_form', 'x_datatable'));
};

/* ═══ ②-ج **صنفُ السطحِ من السجلِّ الحاكم** — والمشروعُ لا يُطالَب بغيرِ عقدِه ═══
   ◆ `repair01_screen_registry.surface_kind`: **`PROJECTION` سطحُ قراءةٍ لا
     كاتبُ حكم** — يعرض ما وقع، والقرارُ يقع في خدمةِ نطاقِه بحارسِه. فمطالبتُه
     بنموذجِ إضافةٍ **مطالبةُ سطحٍ بعقدِ غيرِه**، وهي عينُ الخطأ الذي يمنعه
     `AMD-01 §3-1` [[iaf-field-closure]].
   ⇒ **فنموذجُ الإضافةِ يُنتظَر من `SOURCE` وحدَه**، ويُعلَن للإسقاطِ «غيرُ منطبق».
   ⛔ **ولا يُقرأ الصنفُ من نصِّ رأسِ الملفّ** — بل من السجلِّ الحاكمِ وحدَه، فالنصُّ
     وصفٌ والسجلُّ حكم [[two-registers-target-vs-built]]. */
$kindBySid = array();
$rK = $conn->query('SELECT screen_id, surface_kind FROM repair01_screen_registry');
while ($x = $rK->fetch_assoc()) { $kindBySid[$x['screen_id']] = (string) $x['surface_kind']; }

/* ═══ ②-د **وعُدّةٌ مشترَكةٌ تُصيِّرُ الشاشةَ كلَّها تُقرأ مع مصدرِها** ═══════
   ◆ `includes/u13_screen_kit.php` **قالبٌ يُصيِّر السطحَ بتمامِه** من عقدِ
     `$U13`: القشرةُ والعقدُ و«عن الشاشة» ونموذجُ الأفعال (`class="u13-act"`).
     والملفُّ المولَّدُ (`gov_exec:generated`) **مواصفةٌ لا صفحة** — فقياسُه
     بمصدرِه وحدَه يُدينه بغيابِ ما يوفّره قالبُه [[grain-measure-shared-kit-trap]].
   ⇒ **فمصدرُ القياسِ = مصدرُ الشاشةِ + مصادرُ عُدَدِها المُصيِّرةِ المُستدعاة.** */
$KITS = array('includes/u13_screen_kit.php', 'includes/u13_screen_kit_cols.php',
              'includes/entity_tabs.php', 'includes/sales_family_tabs.php',
              'includes/ems_filter_box.php', 'includes/page_header.php');

/* ═══ ③ مدارُ الجولة — أهدافُ الـ58 وما استُرِدَّ معها ═══ */
$SD = json_decode((string) @file_get_contents(
    $ROOT . '/docs/REPAIR01_20260823/navarch/SILENT_DROP_SCAN.json'), true);
$PR = json_decode((string) @file_get_contents(
    $ROOT . '/docs/REPAIR01_20260823/navarch/SILENT_DROP_RENDER_PROOF.json'), true);

$scope = array();             /* route ⇒ [ws, idx, screen_id, label, origin] */
$addRoute = function ($ws, $idx, $sid, $label, $route, $origin) use (&$scope) {
    $r = ltrim((string) $route, '/');
    if ($r === '' || $r === '—') { return; }
    if (substr($r, -4) !== '.php') { $r .= '.php'; }
    if (isset($scope[$r])) { return; }
    $scope[$r] = array('ws' => $ws, 'idx' => $idx, 'screen_id' => $sid, 'label' => $label, 'origin' => $origin);
};
/* بندُ الورقةِ لكلِّ مسارٍ — فنوعُ البطاقةِ يُقرأ ببندِه لا بصفرٍ افتراضيّ */
$idxByRoute = array();
$rI = $conn->query("SELECT workspace_id, route, target_ref FROM nav_placements WHERE active = 1");
while ($x = $rI->fetch_assoc()) {
    $tp = explode('·', (string) $x['target_ref']);
    if (count($tp) < 3) { continue; }
    $k = strtolower(trim(preg_replace('~\.php$~i', '', ltrim((string) $x['route'], '/')), '/'));
    $idxByRoute[$x['workspace_id'] . '|' . $k] = (int) trim($tp[1]);
}
$idxOf = function ($ws, $route) use ($idxByRoute) {
    $k = strtolower(trim(preg_replace('~\.php$~i', '', ltrim((string) $route, '/')), '/'));
    return isset($idxByRoute[$ws . '|' . $k]) ? $idxByRoute[$ws . '|' . $k] : 0;
};
/* ③-أ ما استُرِدَّ بموضعٍ في هذه الجولة */
foreach ((array) (isset($PR['rows']) ? $PR['rows'] : array()) as $x) {
    $addRoute($x['workspace_id'], $idxOf($x['workspace_id'], $x['route']),
              $x['screen_id'], $x['label'], $x['route'], 'RESTORED');
}
/* ③-ب وما حكمه المسحُ `SERVED_BY` — يُقاس سطحُه الخادم */
foreach ((array) (isset($SD['rows']) ? $SD['rows'] : array()) as $x) {
    $addRoute($x[0], (int) $x[1], $x[4], $x[3], $x[5], 'SERVING_PARENT');
}
/* ③-ج والـ58 التي كانت مسجَّلةً وموضوعةً سلفًا — بجسرِ الورقة */
$sd58 = array();
$r = $conn->query("SELECT p.workspace_id, p.route, p.target_ref, p.screen_id, s.canonical_label_ar
                     FROM nav_placements p
                LEFT JOIN repair01_screen_registry s ON s.screen_id = p.screen_id
                    WHERE p.active = 1");
while ($x = $r->fetch_assoc()) {
    $tp = explode('·', (string) $x['target_ref']);
    $idx = (count($tp) >= 3) ? (int) trim($tp[1]) : 0;
    $sd58[$x['workspace_id'] . '|' . $idx] = $x;
}
/* قائمةُ الـ58 كما وردت في أمرِ الجولة — بالمساحةِ والبند */
$LIST58 = array(
    'DEP-01' => array(2, 8), 'DEP-02' => array(2),
    'DEP-04' => array(4, 5, 11, 13, 20, 22),
    'DEP-05' => array(15, 18, 20, 21, 23, 24),
    'DEP-06' => array(3, 6, 11, 12, 15, 18),
    'DEP-07' => array(8, 9, 12, 15, 16, 19),
    'DEP-08' => array(3, 5, 8, 10, 11, 12, 13, 22, 23, 24, 25, 26, 30),
    'DEP-14' => array(10, 12, 14), 'DEP-15' => array(6, 10),
    'DEP-16' => array(5, 9), 'DEP-17' => array(3, 10),
    'EX-DVP' => array(1, 2, 3, 4, 5, 6, 10, 11, 12),
);
foreach ($LIST58 as $ws => $ixs) {
    foreach ($ixs as $i) {
        if (!isset($sd58[$ws . '|' . $i])) { continue; }
        $x = $sd58[$ws . '|' . $i];
        $addRoute($ws, $i, (string) $x['screen_id'], (string) $x['canonical_label_ar'],
                  (string) $x['route'], 'IN_58');
    }
}

/* ═══ ④ القياس ═══ */
ksort($scope);
$out = array(); $full = 0; $part = 0; $absent = 0;
foreach ($scope as $route => $m) {
    $abs = $ROOT . '/' . $route;
    if (!is_file($abs)) {
        /* مسارُ الورقةِ يخالف حالةَ الحرفِ أحيانًا — يُبحَث عنه بغيرِ حساسيّة */
        $g = @glob($ROOT . '/' . dirname($route) . '/*.php');
        $abs = '';
        foreach ((array) $g as $c) {
            if (strcasecmp(basename($c), basename($route)) === 0) { $abs = $c; break; }
        }
    }
    if ($abs === '' || !is_file($abs)) {
        $out[] = array('route' => $route) + $m + array('type' => '', 'verdict' => 'NOT_ON_DISK',
            'have' => array(), 'missing' => array(), 'na' => array());
        $absent++; continue;
    }
    $src = (string) @file_get_contents($abs);
    $viaKit = array();
    foreach ($KITS as $kf) {
        if (strpos($src, basename($kf)) === false) { continue; }
        $ks = (string) @file_get_contents($ROOT . '/' . $kf);
        if ($ks !== '') { $src .= "
/*__KIT:" . $kf . "*/
" . $ks; $viaKit[] = $kf; }
    }
    $type = isset($cardType[$m['ws'] . '|' . $m['idx']]) ? $cardType[$m['ws'] . '|' . $m['idx']] : '';
    $exp = $expectFor($type);
    /* ⛔ ونموذجُ الإضافةِ لا يُطالَب به إلّا `SOURCE` — والإسقاطُ يعرض ما وقع */
    $kind = isset($kindBySid[$m['screen_id']]) ? $kindBySid[$m['screen_id']] : '';
    if ($kind === 'PROJECTION') { $exp = array_values(array_diff($exp, array('sec_form'))); }
    /* ⛔ وزرُّ الإكسلِ لا يُطالَب به إلّا مَن كيانُه في `ExcelRegistry` */
    if (in_array('kit_excel', $exp, true)) {
        $tbl = '';
        if (preg_match('~ems_w14_grid\\(\s*[\\"\\\']emsList_([a-z0-9_]+)~i', $src, $mt)) { $tbl = strtolower($mt[1]); }
        if ($tbl === '' || !isset($xlTables[$tbl])) {
            $exp = array_values(array_diff($exp, array('kit_excel')));
        }
    }
    $have = array(); $missing = array(); $na = array();
    foreach ($SIG as $k => $d) {
        $hit = (bool) preg_match($d[0], $src);
        if ($hit) { $have[] = $d[1]; }
        elseif (in_array($k, $exp, true)) { $missing[] = $d[1]; }
        else { $na[] = $d[1]; }
    }
    $v = $missing ? 'PARTIAL' : 'PATTERN_COMPLETE';
    if ($v === 'PATTERN_COMPLETE') { $full++; } else { $part++; }
    $out[] = array('route' => $route) + $m + array('type' => $type, 'surface_kind' => $kind,
        'via_kit' => $viaKit, 'verdict' => $v,
        'have' => $have, 'missing' => $missing, 'na' => $na);
}

$dir = $ROOT . '/docs/REPAIR01_20260823/navarch';
file_put_contents($dir . '/SILENT_DROP_PATTERN_AUDIT.json', json_encode(array(
    'measured_at' => date('c'),
    'snapshot' => trim((string) shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD')),
    'reference' => 'Clients/clients.php',
    'tokens' => count($SIG), 'complete' => $full, 'partial' => $part, 'not_on_disk' => $absent,
    'rows' => $out,
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "══ مواءمةُ النمطِ — المرجع Clients/clients.php · " . count($SIG) . " مفردةً مُثبَتةً فيه ══\n";
foreach ($out as $x) {
    printf("  %-8s %-11s %-42s %-18s %s\n", $x['ws'], $x['screen_id'] ?: '—',
        mb_substr($x['route'], 0, 40), $x['verdict'],
        $x['missing'] ? 'ينقص: ' . implode(' · ', $x['missing']) : '');
}
printf("\n  مطابقٌ تامًّا: **%d** · ناقصٌ: **%d** · غيرُ موجودٍ على القرص: **%d** من %d سطحًا\n",
    $full, $part, $absent, count($out));
echo "  => {$dir}/SILENT_DROP_PATTERN_AUDIT.json\n";
