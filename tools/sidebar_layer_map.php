<?php
/**
 * tools/sidebar_layer_map.php — خريطةُ طبقاتِ السايدبار · كلُّ رابطٍ ومجموعةٍ بطبقتِها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ سؤالُ المالك: «ورقةٌ لكلِّ إدارةٍ فيها مجموعاتُها وروابطُها، وتوضيحُ أيِّ
 *   طبقةٍ يتبع كلُّ رابطٍ ومجموعة» — فالسايدبارُ يجمع طبقاتٍ لا طبقةً واحدة.
 *
 * ◆ [[render-not-store-rule]]: المقامُ **الشجرةُ المُصيَّرةُ حرفًا** بعمليّةٍ نقيّةٍ
 *   لكلِّ دور — ⛔ لا صفوفُ جدول. وترتيبُ الصفوفِ هو ترتيبُ العرضِ نفسُه.
 *
 * ◆ **خمسُ طبقاتٍ** — ولكلٍّ مصدرٌ حاكمٌ مختلفٌ ومصيرٌ مختلف:
 *   ① `GUIDE`     هدفُ ورقةِ الإدارةِ في «01 · الدليل المعماري» — **الطبقةُ الحاكمة**.
 *   ② `ANCHOR`    مرساتا الدستورِ §6 (الرئيسية · المراسلات) — في 18 مساحةٍ من 18.
 *   ③ `PERSONAL`  ورقةُ «مساحة عملي» `WS-MY` — تظهر لكلِّ دورٍ بحكمٍ قائم.
 *   ④ `SHARED`    هدفُ دليلٍ **مملوكٌ لمساحةٍ أخرى** يظهر هنا — استعارة.
 *   ⑤ `LEGACY`    التصنيفُ الاثنا عشرَ القديمُ — **سابقٌ للدليلِ ولم يُحسم**.
 *
 * ◆ **والمجموعةُ تأخذ طبقتَها من غالبِ بنودِها**، ويُعلَن الخليطُ صراحةً — فرأسٌ
 *   يجمع طبقتَين حقيقةٌ لا تُخفى (وهو أوّلُ ما يربك القارئ).
 *
 * التشغيل: php tools/sidebar_layer_map.php
 *   ⇒ docs/REPAIR01_20260823/SIDEBAR_LAYER_MAP.xlsx  (ورقةٌ لكلِّ مساحة)
 */

error_reporting(E_ALL & ~E_DEPRECATED);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/rpr02a_guide.php';
require_once $ROOT . '/tools/lib/xlsx_out.php';

ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$nz = 'rpr02a_nz';
$ANCHOR = array('main/role_board' => 1, 'chats/index' => 1);
$rt = function ($s) {
    $s = preg_replace('~^(\.\./)+~', '', (string) $s);
    $s = preg_replace('~[?#].*$~', '', $s);
    return strtolower(trim(preg_replace('~\.php$~i', '', $s), '/'));
};

/* ═══ ① مواضعُ الدليلِ لكلِّ مساحة ═══ */
$byWsRoute = array(); $byWsName = array(); $routeAnyWs = array(); $guideGroup = array();
$r = $conn->query("SELECT p.workspace_id, p.route, p.target_ref, p.sort_no, g.label_ar, g.sort_no gno
                     FROM nav_placements p
                     LEFT JOIN nav_lifecycle_groups g ON g.id = p.group_id
                    WHERE p.active = 1");
while ($x = $r->fetch_assoc()) {
    $ws = (string) $x['workspace_id'];
    if ((string) $x['route'] !== '') {
        $k = $rt($x['route']);
        $byWsRoute[$ws][$k] = array($x['label_ar'], $x['gno'], $x['sort_no']);
        if (!isset($routeAnyWs[$k])) { $routeAnyWs[$k] = $ws; }
    }
    if ((string) $x['label_ar'] !== '') { $guideGroup[$ws][$nz($x['label_ar'])] = (int) $x['gno']; }
    $tr = (string) $x['target_ref'];
    if ($tr !== '' && preg_match('~·\s*(\d+)\s*·\s*(.+)$~u', $tr, $m)) {
        $byWsName[$ws][$nz($m[2])] = (int) $m[1];
    }
}

/* ═══ ② المساحاتُ وأدوارُها ═══ */
$wsRole = array(); $wsName = array();
$r = $conn->query("SELECT w.workspace_id, w.name_ar, wr.role_id, ro.name rname
                     FROM nav_workspaces w
                     LEFT JOIN nav_ws_roles wr ON wr.workspace_id = w.workspace_id AND wr.binding = 'PRIMARY'
                     LEFT JOIN roles ro ON ro.id = wr.role_id
                    WHERE w.active = 1 ORDER BY w.workspace_id");
while ($x = $r->fetch_assoc()) {
    $wsName[$x['workspace_id']] = $x['name_ar'];
    if ($x['role_id'] !== null) { $wsRole[$x['workspace_id']] = array((int) $x['role_id'], $x['rname']); }
}

function render_role_pos($ROOT, $rid)
{
    $out = array();
    @exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/lib/render_role_cli.php') . ' ' . (int) $rid . ' 2>NUL', $out);
    $j = json_decode(implode("\n", $out), true);
    return (is_array($j) && isset($j['positions'])) ? $j['positions'] : null;
}

$LAYER_AR = array(
    'GUIDE'    => '① الدليل — ورقةُ الإدارة',
    'ANCHOR'   => '② المرساة — الدستور §6',
    'PERSONAL' => '③ الشخصيّة — مساحة عملي',
    'SHARED'   => '④ المستعارة — إدارةٌ أخرى',
    'LEGACY'   => '⑤ الإرث — التصنيف القديم',
);
$FATE = array(
    'GUIDE'    => 'حاكم — يبقى بترتيبِه',
    'ANCHOR'   => 'يبقى — بنصِّ الدستور',
    'PERSONAL' => 'يبقى — بحكمٍ قائم',
    'SHARED'   => 'يُراجَع — أتُبقى استعارةً أم تُخفى؟',
    'LEGACY'   => 'يُحسَم — تُضاف للدليلِ أم تُخفى بحكم؟',
);

/* ═══ ③ البناء ═══ */
$sheets = array(); $sum = array();
$grand = array('GUIDE' => 0, 'ANCHOR' => 0, 'PERSONAL' => 0, 'SHARED' => 0, 'LEGACY' => 0, 'T' => 0);
$HDR = array('ترتيبُ العرض', 'رأسُ الطيِّ (المجموعة)', 'طبقةُ المجموعة', 'اسمُ الرابط',
             'المسار', 'طبقةُ الرابط', 'مصدرُه الحاكم', 'مصيرُه', 'قرارُ المالك');

foreach ($wsRole as $ws => $role) {
    list($rid, $rname) = $role;
    $pos = render_role_pos($ROOT, $rid);
    if ($pos === null) { continue; }

    /* طبقةُ كلِّ بند */
    $items = array(); $byHead = array();
    foreach ($pos as $p) {
        $route = $rt($p['h']); $lbl = (string) $p['l']; $head = (string) $p['g'];
        if (isset($byWsRoute[$ws][$route]) || isset($byWsName[$ws][$nz($lbl)])) {
            $L = 'GUIDE'; $src = 'ورقةُ ' . $ws . ' في الدليلِ المعماريّ';
        } elseif (isset($ANCHOR[$route])) {
            $L = 'ANCHOR'; $src = 'الدستورُ §6 — مرساةٌ في كلِّ سايدبار';
        } elseif (isset($byWsRoute['WS-MY'][$route])) {
            $L = 'PERSONAL'; $src = 'ورقةُ «مساحة عملي» WS-MY';
        } elseif (isset($routeAnyWs[$route])) {
            $own = $routeAnyWs[$route];
            $L = 'SHARED'; $src = 'ورقةُ ' . $own . ' — ' . (isset($wsName[$own]) ? $wsName[$own] : '');
        } else {
            $L = 'LEGACY'; $src = 'التصنيفُ الاثنا عشرَ — سابقٌ للدليل';
        }
        $items[] = array($head, $lbl, $route, $L, $src);
        $byHead[$head][$L] = (isset($byHead[$head][$L]) ? $byHead[$head][$L] : 0) + 1;
        $grand[$L]++; $grand['T']++;
    }

    /* طبقةُ الرأسِ = غالبُ بنودِه · والخليطُ يُعلَن */
    $headLayer = array();
    foreach ($byHead as $h => $c) {
        arsort($c); $top = key($c);
        $headLayer[$h] = (count($c) > 1)
            ? $LAYER_AR[$top] . ' (مختلطٌ: ' . implode(' + ', array_map(
                function ($k, $v) use ($LAYER_AR) { return mb_substr($LAYER_AR[$k], 0, 2) . '×' . $v; },
                array_keys($c), array_values($c))) . ')'
            : $LAYER_AR[$top];
    }

    $rows = array($HDR); $i = 0; $cnt = array();
    foreach ($items as $it) {
        list($head, $lbl, $route, $L, $src) = $it;
        $cnt[$L] = (isset($cnt[$L]) ? $cnt[$L] : 0) + 1;
        $rows[] = array(++$i, $head, $headLayer[$head], $lbl, $route, $LAYER_AR[$L], $src, $FATE[$L], '');
    }
    $sheets[$ws . ' ' . mb_substr($wsName[$ws], 0, 20)] = $rows;
    $sum[$ws] = array($wsName[$ws], $rid . ' · ' . $rname, count($items), count($byHead),
        isset($cnt['GUIDE']) ? $cnt['GUIDE'] : 0, isset($cnt['ANCHOR']) ? $cnt['ANCHOR'] : 0,
        isset($cnt['PERSONAL']) ? $cnt['PERSONAL'] : 0, isset($cnt['SHARED']) ? $cnt['SHARED'] : 0,
        isset($cnt['LEGACY']) ? $cnt['LEGACY'] : 0);
}

/* ═══ ④ ورقتا الخلاصةِ والدليلِ أوّلًا ═══ */
$leg = array(array('الطبقة', 'ما هي', 'مصدرُها الحاكم', 'مصيرُها'));
$leg[] = array($LAYER_AR['GUIDE'], 'الشاشاتُ التي ترسمها ورقةُ الإدارةِ في الدليل',
    '01 · الدليل المعماري — ورقةُ الإدارة', 'حاكمةٌ — تبقى بمجموعتِها وترتيبِها');
$leg[] = array($LAYER_AR['ANCHOR'], '«الرئيسية» و«المراسلات» — أوّلُ ما يُرى',
    'الدستور §6 — مقيسةٌ في 18 مساحةٍ من 18', 'تبقى — لا تُخفى ولا تُضاف لورقةِ إدارة');
$leg[] = array($LAYER_AR['PERSONAL'], 'بنودُ المستخدمِ نفسِه: مهامُّه وطلباتُه وبلاغاتُه',
    'ورقةُ «مساحة عملي» WS-MY في الدليل', 'تبقى — تظهر لكلِّ دورٍ بحكمٍ قائم');
$leg[] = array($LAYER_AR['SHARED'], 'شاشةُ إدارةٍ أخرى تظهر هنا لحاجةِ عمل',
    'ورقةُ الإدارةِ المالكة — لا ورقةُ هذه', 'تُراجَع — استعارةٌ مشروعةٌ أم زيادةٌ تُخفى؟');
$leg[] = array($LAYER_AR['LEGACY'], 'روابطُ بُنيت قبل أن يوجدَ الدليلُ المعماريّ',
    'التصنيفُ الاثنا عشرَ القديم — لا ورقةَ لها', 'تُحسَم — تُضاف للدليلِ أم تُخفى بحكمٍ مكتوب');

$idx = array(array('المساحة', 'الإدارة', 'الدورُ المُصيَّر', 'روابطُ ظاهرة', 'رؤوسُ طيّ',
                   '① دليل', '② مرساة', '③ شخصيّة', '④ مستعارة', '⑤ إرث'));
foreach ($sum as $ws => $s) {
    $idx[] = array($ws, $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7], $s[8]);
}
$idx[] = array('—', 'الإجمالي', '—', $grand['T'], '—', $grand['GUIDE'], $grand['ANCHOR'],
    $grand['PERSONAL'], $grand['SHARED'], $grand['LEGACY']);

$sheets = array('00 الخلاصة' => $idx, '01 دليل الطبقات' => $leg) + $sheets;
$xlsx = $ROOT . '/docs/REPAIR01_20260823/SIDEBAR_LAYER_MAP.xlsx';
xlsx_create($xlsx, $sheets);

/* ═══ ⑤ مجاميعُ الطبقاتِ **قارئًا ثانيًا مكتوبًا** — لا رقمًا مجمَّدًا ═════════
   ◆ **المقيسُ قبلَ الحكم**: `tools/navarch/classify.php` كان يحرس ثباتَ طبقاتِه
     بمصفوفةٍ حرفيّةٍ (‏342/36/88/323/400) منقولةٍ من §1 من الأمر — وهي مجاميعُ
     **لقطةٍ بعينِها** (1,189 رابطًا). فلمّا بُنيت شاشاتُ `GOV_UI_FINISH` الثلاثُ
     صار الحيُّ 1,192 و**رسَب الحارسُ على تحسُّنٍ مشروع** — وهو عينُ فخِّ
     «حاجبُ مرحلةٍ بثابتٍ رقميٍّ يجمّد» [[repair01-w04-field]].
   ◆ **وغرضُ الحارسِ يبقى كما هو**: أن **يتفق قارئان مستقلّان** على مجاميعِ
     الطبقاتِ الخمس — فمن يحسبها مرّتَين بحسابَين يكذب أحدُهما
     [[counter-parity-two-readers]]. فيُكتب حسابُ هذا الملفِّ **مع معرِّفِ
     الأساسِ الذي قِيس عليه**، ويقرؤه المصنِّفُ فيقارن حسابَه بحسابِه.
   ⛔ **والمقارنةُ تُلغى صراحةً إن اختلف الأساس** — فقياسان من لقطتَين لا
     يتقارنان (§5 حرفًا: «لا تستخدم قياسات مولدة من Commits مختلفة»). */
$blFile = $ROOT . '/docs/REPAIR01_20260823/navarch/NAV_ARCH_BASELINE.json';
$blId   = is_file($blFile)
    ? (string) json_decode(file_get_contents($blFile), true)['baseline_id'] : '';
file_put_contents($ROOT . '/docs/REPAIR01_20260823/navarch/SIDEBAR_LAYER_TOTALS.json',
    json_encode(array(
        'baseline_id' => $blId,
        'reader'      => 'tools/sidebar_layer_map.php',
        'workspaces'  => count($sum),
        'total'       => $grand['T'],
        'layers'      => array('GUIDE' => $grand['GUIDE'], 'ANCHOR' => $grand['ANCHOR'],
                               'PERSONAL' => $grand['PERSONAL'], 'SHARED' => $grand['SHARED'],
                               'LEGACY' => $grand['LEGACY']),
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

printf("مساحات %d · روابط %d · دليل %d · مرساة %d · شخصيّة %d · مستعارة %d · إرث %d\n=> %s\n",
    count($sum), $grand['T'], $grand['GUIDE'], $grand['ANCHOR'], $grand['PERSONAL'],
    $grand['SHARED'], $grand['LEGACY'], $xlsx);
