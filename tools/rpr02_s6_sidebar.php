<?php
/**
 * tools/rpr02_s6_sidebar.php — `RPR-02` §٦ · السايدبارُ قبلَ الشاشات · قياسُ السبع
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٦ سبعةُ تصحيحاتٍ: تعطيلُ ما ليس في الملفِّ **بعذرٍ
 *   مكتوبٍ ومرجعٍ — ولا حذفَ صامت** · وتصحيحُ الأسماءِ على التسميةِ المعتمدة ·
 *   والمجموعاتُ **اسمًا وعددًا** · والترتيبُ **على دورةِ العملِ لا على تاريخِ
 *   الإضافة** · والأبُ والتبويب · والظهورُ بالصلاحيةِ **ولا تُضحَّى الصلاحيةُ
 *   لأجلِ ترتيبٍ أجمل** · والربطُ بسجلِّ الملاحةِ المعياريِّ **ولا ترتيبَ يدويٌّ
 *   موازٍ**.
 *
 * ◆ **ولماذا قياسٌ قبلَ تصحيح**: §٦ يسبق §٧ في الأمر، **والتصحيحُ بلا قياسٍ
 *   قبليٍّ يُفقد الفرقَ الذي أحدثه**. ومقياسُ §١٢ الثامنُ («تطابقُ ترتيبِ
 *   السايدبارِ ومجموعاتِه») **محجوبٌ على هذا القياسِ بعينِه**.
 *
 * ◆ **والمقامُ حيٌّ لا مُعلَن**: `nav_items` النشِطةُ كما تُصيَّر — لا كما
 *   تُعلن في بيانٍ. و`repair01_w4_sidebar` قاس **ستّةً وعشرين** سطحًا هي نطاقُ
 *   موجتِه، **ولا يُقرأ ذلك تغطيةً للسايدبار كلِّه** — والفرقُ يُسمّى بعددِه.
 *
 * ◆ ⛔ **وسبعةُ أحكامٍ لا حكمٌ واحد**: بندٌ قد يستقيم اسمًا ويختلَّ مجموعةً.
 *   وجمعُها في «مطابق/غير مطابق» يُخفي أيَّ الخطواتِ تحتاج عملًا.
 *
 * ⛔ **وهذا يقيس ولا يصحّح** — والتصحيحُ يمسّ ملاحةً حيّةً يراها المستخدمون،
 *   فيُبنى على هذا السجلِّ بعد قراءتِه لا قبلَها.
 *
 * التشغيل:
 *   php tools/rpr02_s6_sidebar.php [--md] [--list] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$MD   = in_array('--md', $argv, true);
$LIST = in_array('--list', $argv, true);
$SELF = in_array('--selftest', $argv, true);

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

/* ⛔ **استعلامٌ يسقط يجب أن يُسمع**: `$conn->query` تُرجع `false` فيصير النداءُ
   عليها فادحًا صامتًا (‏خرجَ ٢٥٥ بلا سطرِ خطأٍ واحد، لأنَّ `config.php` يبتلع
   مخرجَ CLI). فكلُّ استعلامٍ يمرُّ من هنا ويموت باسمِه. */
$q = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    if (!$r) { fwrite(STDERR, "✘ استعلامٌ سقط: {$conn->error}\n   $sql\n"); exit(2); }
    return $r;
};
$one = function ($sql) use ($conn) {
    $x = $conn->query($sql); if (!$x) { return null; }
    $y = $x->fetch_row(); return $y ? $y[0] : null;
};

$norm = function ($s) {
    $s = preg_replace('~[\x{064B}-\x{0652}\x{0670}\x{0640}]~u', '', (string) $s);
    $s = preg_replace('~[\x{0622}\x{0623}\x{0625}]~u', "\u{0627}", $s);
    $s = preg_replace('~\x{0649}~u', "\u{064A}", $s);
    $s = preg_replace('~\x{0629}~u', "\u{0647}", $s);
    $s = preg_replace('~[«»"\'\[\]\-–—/·،,\.]~u', ' ', $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
};

/* ═══ ① البنودُ الحيّةُ كما تُصيَّر ═══════════════════════════════════════ */
$items = array();
$r = $q("SELECT n.id, n.role_id, n.group_id, n.route, n.label_ar, n.sort_order,
                          n.permission_code, g.name AS group_name
                     FROM nav_items n
                     LEFT JOIN link_groups g ON g.id = n.group_id
                    WHERE n.active = 1
                    ORDER BY n.role_id, n.group_id, n.sort_order");
while ($x = $r->fetch_assoc()) { $items[] = $x; }
$N = count($items);

/* السجلُّ المبنيُّ بمسارِه — الجسرُ من الرابطِ إلى السطح */
$byRoute = array();
$r = $q("SELECT screen_id, route, screen_file, owner_code, canonical_label_ar,
                          parent_screen_id, guard_kind, ownership_verdict, on_disk
                     FROM repair01_screen_registry WHERE route <> ''");
while ($x = $r->fetch_assoc()) { $byRoute[strtolower(trim($x['route'], '/'))] = $x; }

/* **التسميةُ المعتمدةُ وموضعُها** — و`nav_canonical` تحمل الأربعةَ معًا:
   الاسمَ (`canonical_ar`) والمجموعةَ (`group_name`) والترتيبَ (`sort_no`)
   والمعرِّفَ (`screen_id`). ⇒ فالخطواتُ ٢ و٣ و٤ و٧ تُقاس على **مصدرٍ معتمَدٍ
   واحدٍ** لا على اشتقاقٍ من دفترِ المتطلبات. */
$canon = array();
$r = $q("SELECT route, canonical_ar, group_name, sort_no, screen_id, status FROM nav_canonical");
while ($x = $r->fetch_assoc()) { $canon[strtolower(trim((string) $x['route'], '/'))] = $x; }

/* مجموعاتُ الملفِّ التصميميِّ لكلِّ إدارة */
$specGroup = array();
$r = $q("SELECT unit, group_name FROM repair01_requirements WHERE group_name <> ''");
while ($x = $r->fetch_row()) {
    if (preg_match('~^(\d{2}|AS|E1|E2|WS)~u', $x[0], $m)) {
        $u = ($m[1] === 'AS') ? 'IAF' : (($m[1] === 'E1') ? 'EX-CEO'
           : (($m[1] === 'E2') ? 'EX-DVP' : (($m[1] === 'WS') ? 'WS-MY' : 'DEP-' . $m[1])));
        $specGroup[$u][$norm($x[1])] = 1;
    }
}

/* ترتيبُ دورةِ العملِ التصميميّ: النطاق ⇐ [اسمُ السطحِ مطبَّعًا ⇒ seq] */
$specOrder = array();
$r = $q("SELECT unit, surface, seq FROM repair01_requirements WHERE surface <> ''");
while ($x = $r->fetch_row()) {
    if (preg_match('~^(\d{2}|AS|E1|E2|WS)~u', $x[0], $m)) {
        $u = ($m[1] === 'AS') ? 'IAF' : (($m[1] === 'E1') ? 'EX-CEO'
           : (($m[1] === 'E2') ? 'EX-DVP' : (($m[1] === 'WS') ? 'WS-MY' : 'DEP-' . $m[1])));
        $specOrder[$u][$norm($x[1])] = (int) $x[2];
    }
}

/* ═══ ② الخطواتُ السبعُ — حكمٌ لكلِّ بندٍ في كلِّ خطوة ═══════════════════ */
$S = array();
for ($i = 1; $i <= 7; $i++) { $S[$i] = array('ok' => 0, 'bad' => 0, 'na' => 0, 'ex' => array(), 'rt' => array()); }
$ord = array();

foreach ($items as $it) {
    $rt  = strtolower(trim((string) $it['route'], '/'));
    /* **ورمزُ `?view=`/`?tab=` تبويبٌ مُعلَنٌ لا مسارٌ يتيم**: خمسةٌ وثلاثون
       مسارًا حيًّا بلا صفٍّ في السجلِّ، **وكلُّها تُحلُّ بأساسِها** ⇒ فهي
       صيغُ عرضٍ لسطحٍ مسجَّلٍ لا أسطحًا مفقودة. ولولا الردُّ إلى الأساسِ
       لعُدَّت «ليست في الملف» وهي فيه ([[nav-view-param-ownership]]). */
    $variant = false;
    if (!isset($byRoute[$rt])) {
        $base = preg_replace('~[?#].*$~', '', $rt);
        if ($base !== $rt && isset($byRoute[$base])) { $rt = $base; $variant = true; }
    }
    $scr = isset($byRoute[$rt]) ? $byRoute[$rt] : null;
    $lbl = $norm($it['label_ar']);

    /* س١ — بندٌ حيٌّ بلا سطحٍ مسجَّلٍ على القرصِ = «ما ليس في الملف» */
    if ($scr && (int) $scr['on_disk'] === 1 && $scr['ownership_verdict'] !== 'RETIRE') {
        $S[1]['ok']++;
    } else {
        $S[1]['bad']++;
        if (count($S[1]['ex']) < 8) {
            $S[1]['ex'][] = $it['route'] . ' «' . $it['label_ar'] . '» دور ' . $it['role_id'];
        }
    }

    /* س٢ — الاسمُ المعروضُ على التسميةِ المعتمدة (`canonical_ar` بحالةِ APPROVED) */
    $cn = isset($canon[$rt]) ? $canon[$rt] : null;
    if ($cn && $cn['status'] === 'APPROVED') {
        if ($norm($cn['canonical_ar']) === $lbl) { $S[2]['ok']++; }
        else {
            $S[2]['bad']++; $S[2]['rt'][$rt] = 1;
            if (count($S[2]['ex']) < 8) {
                $S[2]['ex'][] = $it['route'] . ' معروضٌ «' . $it['label_ar'] . '» ومعتمَدٌ «' . $cn['canonical_ar'] . '»';
            }
        }
    } else {
        $S[2]['bad']++; $S[2]['rt'][$rt] = 1;
        if (count($S[2]['ex']) < 8) {
            $S[2]['ex'][] = $it['route'] . ' «' . $it['label_ar'] . '» — '
                          . ($cn ? 'تسميتُه بحالةِ `' . $cn['status'] . '` لا `APPROVED`' : 'لا صفَّ تسميةٍ معتمدة');
        }
    }

    /* س٣ — المجموعةُ المعروضةُ تطابق مجموعةَ التسميةِ المعتمدة */
    $g = $norm($it['group_name']);
    $u = $scr ? $scr['owner_code'] : '';
    if (!$cn || trim((string) $cn['group_name']) === '') { $S[3]['na']++; }
    elseif ($norm($cn['group_name']) === $g) { $S[3]['ok']++; }
    else {
        $S[3]['bad']++; $S[3]['rt'][$rt] = 1;
        if (count($S[3]['ex']) < 8) {
            $S[3]['ex'][] = $it['route'] . ' معروضةٌ «' . $it['group_name'] . '» ومعتمَدةٌ «' . $cn['group_name'] . '»';
        }
    }

    /* س٤ — الترتيبُ: يُجمع الآن ويُحكَم بعدُ **ترتيبيًّا داخلَ كلِّ مجموعةٍ**
       ⛔ ولا يُقارَن `sort_order` بـ`sort_no` عددًا لعدد: المقياسُ **الترتيبُ**
          لا القيمة، فرقمان مختلفان قد يُنتجان الترتيبَ نفسَه. */
    if ($cn && (int) $cn['sort_no'] > 0) {
        $ord[$it['role_id'] . '|' . (int) $it['group_id']][] =
            array((int) $it['sort_order'], (int) $cn['sort_no'], $it['route']);
        $S[4]['ok']++;   /* يُعاد حسمُه بعد الفرزِ الترتيبيّ */
    } else { $S[4]['na']++; }
    /* س٥ — الأبُ والتبويب */
    if (!$scr) { $S[5]['na']++; }
    elseif ($scr['parent_screen_id'] === '') { $S[5]['ok']++; }
    else {
        $p = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                          WHERE screen_id = '" . $conn->real_escape_string($scr['parent_screen_id']) . "'
                            AND owner_code = '" . $conn->real_escape_string($scr['owner_code']) . "'");
        if ($p > 0) { $S[5]['ok']++; }
        else {
            $S[5]['bad']++; $S[5]['rt'][$rt] = 1;
            if (count($S[5]['ex']) < 8) { $S[5]['ex'][] = $scr['screen_id'] . ' أبوه ' . $scr['parent_screen_id'] . ' من إدارةٍ أخرى'; }
        }
    }

    /* س٦ — الظهورُ بالصلاحيةِ وحارسٌ خادميّ */
    $hasPerm  = trim((string) $it['permission_code']) !== '';
    $hasGuard = $scr && trim((string) $scr['guard_kind']) !== '';
    if ($hasPerm && $hasGuard) { $S[6]['ok']++; }
    else {
        $S[6]['bad']++; $S[6]['rt'][$rt] = 1;
        if (count($S[6]['ex']) < 8) {
            $S[6]['ex'][] = $it['route'] . ($hasPerm ? '' : ' بلا رمزِ صلاحية') . ($hasGuard ? '' : ' بلا حارسٍ خادميّ');
        }
    }

    /* س٧ — مربوطٌ بسجلِّ الملاحةِ المعياريِّ **بمعرِّفٍ لا بمسار** */
    if ($cn && trim((string) $cn['screen_id']) !== '' && $scr
        && $cn['screen_id'] === $scr['screen_id']) { $S[7]['ok']++; }
    else {
        $S[7]['bad']++; $S[7]['rt'][$rt] = 1;
        if (count($S[7]['ex']) < 8) {
            $S[7]['ex'][] = $it['route'] . ' — '
                . (!$cn ? 'لا صفَّ في سجلِّ الملاحةِ المعياريّ'
                   : (trim((string) $cn['screen_id']) === '' ? 'صفُّه بلا `screen_id`'
                      : (!$scr ? 'لا سطحَ في السجلِّ بهذا المسار'
                         : 'معرِّفُه ' . $cn['screen_id'] . ' والسجلُّ ' . $scr['screen_id'])));
        }
    }
}

/* ═══ ②·ب س٤ يُحسم ترتيبيًّا — لا عددًا لعدد ═══════════════════════════
     §٦: *«رتِّبِ الأسطحَ على دورةِ العملِ في الملفِّ لا على تاريخِ الإضافة»*.
     ⇒ المقياسُ **تطابقُ الترتيبِ داخلَ المجموعةِ الواحدة**: يُرتَّب بنودُها
     بالمعروضِ ثمَّ بالمعتمَد، فكلُّ بندٍ اختلف موضعُه بين الترتيبَين يُعدّ
     محتاجًا. ⛔ **ولا يُقارَن الرقمان قيمةً**: `sort_order=10` و`sort_no=3`
     قد ينتجان الموضعَ نفسَه، فمقارنةُ القيمةِ تُنتج حُمرةً كاذبةً كاسحة. */
$S[4]['ok'] = 0; $S[4]['bad'] = 0;
foreach ($ord as $key => $rows) {
    $live = $rows; $spec = $rows;
    usort($live, function ($a, $b) { return $a[0] === $b[0] ? strcmp($a[2], $b[2]) : $a[0] - $b[0]; });
    usort($spec, function ($a, $b) { return $a[1] === $b[1] ? strcmp($a[2], $b[2]) : $a[1] - $b[1]; });
    foreach ($live as $pos => $x) {
        if ($spec[$pos][2] === $x[2]) { $S[4]['ok']++; }
        else {
            $S[4]['bad']++; $S[4]['rt'][$x[2]] = 1;
            if (count($S[4]['ex']) < 8) {
                $S[4]['ex'][] = 'مجموعة ' . $key . ' موضع ' . ($pos + 1)
                              . ': معروضٌ ' . $x[2] . ' والدورةُ تضع ' . $spec[$pos][2];
            }
        }
    }
}

/* ═══ ③ الاختبارُ السالب ═══════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    if ($N < 100) { echo "  X المقامُ الحيُّ $N — يبدو أنَّ القراءةَ لم تتمّ\n"; $fail++; }
    for ($i = 1; $i <= 7; $i++) {
        $sum = $S[$i]['ok'] + $S[$i]['bad'] + $S[$i]['na'];
        if ($sum !== $N) { echo "  X الخطوةُ $i مجموعُها $sum والمقامُ $N\n"; $fail++; }
    }
    /* **الكاسرُ**: بندٌ بمسارٍ لا وجودَ له يجب أن يسقط في س١ وس٧ معًا */
    $probe = strtolower('zzq/absent_route_probe.php');
    if (isset($byRoute[$probe])) { echo "  X مسارٌ وهميٌّ وُجد في السجلّ\n"; $fail++; }
    /* ولو كان `$byRoute` فارغًا لسقط كلُّ شيءٍ ومرَّ الفحصُ أخضرَ كاذبًا */
    if (count($byRoute) < 100) { echo '  X جسرُ المساراتِ ' . count($byRoute) . " صفًّا — مصفاةٌ عمياء\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — سبعُ خطواتٍ مجموعُها المقامُ نفسُه، والجسرُ ليس أعمى\n";
    exit($fail ? 1 : 0);
}

/* ═══ ④ العرض ═══════════════════════════════════════════════════════════ */
$TITLE = array(
    1 => 'عطِّل ما ليس في الملف بعذرٍ مكتوبٍ ومرجع',
    2 => 'صحِّح الأسماء المعروضة على التسمية المعتمدة',
    3 => 'صحِّح المجموعات لتطابق مجموعات الملف',
    4 => 'رتِّب الأسطح على دورة العمل لا على تاريخ الإضافة',
    5 => 'صحِّح علاقات الأب والتبويب',
    6 => 'صحِّح الظهور بالصلاحية — ولا تُضحَّى لأجل ترتيب',
    7 => 'اربط الكل بسجل الملاحة المعياري',
);
$w4 = (int) $one("SELECT COUNT(*) FROM repair01_w4_sidebar");
echo "\n═══ `RPR-02` §٦ — السايدبارُ قبلَ الشاشات · قياسُ السبع ═══\n";
printf("  اللقطة %s · **بنودٌ حيّةٌ مُصيَّرةٌ %d** · وقاست الموجةُ الرابعةُ %d سطحًا (نطاقُها لا السايدبار)\n\n",
       $sid, $N, $w4);
printf("  %-3s %-40s %8s %8s %8s %10s\n", '#', 'الخطوة', 'مستقيم', 'يحتاج', 'لا ينطبق', 'مسارٌ فريد');
echo '  ' . str_repeat('─', 78) . "\n";
for ($i = 1; $i <= 7; $i++) {
    printf("  س%-2d %-40s %8d %8d %8d %10d
", $i, mb_substr($TITLE[$i], 0, 40),
           $S[$i]['ok'], $S[$i]['bad'], $S[$i]['na'], count($S[$i]['rt']));
}
$needTot = 0;
for ($i = 1; $i <= 7; $i++) { $needTot += $S[$i]['bad']; }
printf("\n  **مواضعُ تحتاج تصحيحًا في الخطواتِ السبع مجتمعةً: %d**\n", $needTot);

if ($LIST) {
    for ($i = 1; $i <= 7; $i++) {
        if (!$S[$i]['ex']) { continue; }
        echo "\n  ── س$i · شواهدُ ──\n";
        foreach ($S[$i]['ex'] as $x) { echo "     · $x\n"; }
    }
}
echo "\n  ⛔ **وهذا يقيس ولا يصحّح** — والتصحيحُ يمسّ ملاحةً حيّةً يراها المستخدمون،\n";
echo "     فيُبنى على هذا السجلِّ بعد قراءتِه لا قبلَها.\n";

if ($MD) {
    $o  = "# `RPR-02` §٦ — السايدبارُ قبلَ الشاشات · قياسُ السبع\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "المقامُ **بنودٌ حيّةٌ مُصيَّرة " . $N . "** — لا بيانٌ مُعلَن. وقاست الموجةُ الرابعةُ **"
        . $w4 . "** سطحًا هي نطاقُها، ⛔ **ولا يُقرأ ذلك تغطيةً للسايدبار كلِّه**.\n\n";
    $o .= "| # | الخطوة | مستقيم | يحتاج تصحيحًا | لا ينطبق | مسارٌ فريد |\n|---|---|---:|---:|---:|---:|\n";
    for ($i = 1; $i <= 7; $i++) {
        $o .= '| س' . $i . ' | ' . $TITLE[$i] . ' | ' . $S[$i]['ok'] . ' | **' . $S[$i]['bad'] . '** | '
            . $S[$i]['na'] . ' | ' . count($S[$i]['rt']) . " |\n";
    }
    $rtTot = array();
    for ($i = 1; $i <= 7; $i++) { foreach (array_keys($S[$i]['rt']) as $k) { $rtTot[$k] = 1; } }
    $o .= "\n**مواضعُ تحتاج تصحيحًا مجتمعةً: " . $needTot . "** — و**مساراتٌ فريدةٌ تحتاج عملًا: "
        . count($rtTot) . "**.\n";
    $o .= "⛔ **والعمودان لا يُخلطان**: البندُ يتكرَّر بعددِ الأدوارِ التي تراه، "
        . "فـ«موضعٌ» ليس «شاشةً» — والعملُ يُقدَّر بالمساراتِ الفريدةِ لا بالمواضع.\n\n";
    $o .= "⛔ **وسبعةُ أحكامٍ لا حكمٌ واحد**: بندٌ قد يستقيم اسمًا ويختلَّ مجموعةً،\n";
    $o .= "وجمعُها في «مطابق/غير مطابق» يُخفي أيَّ الخطواتِ تحتاج عملًا.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02_S6_SIDEBAR.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/RPR02_S6_SIDEBAR.md\n";
}
