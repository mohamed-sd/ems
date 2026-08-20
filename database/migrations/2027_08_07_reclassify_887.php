<?php
/**
 * 2027_08_07_reclassify_887.php — إعادةُ تصنيفِ الـ887 وحسمُ المعلَّق (ثامنًا ④⑤)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب (ثانيًا): «**أعدْ تصنيفَ الـ887 كاملةً بعدَ التصحيح**. وما كان
 *   ممنوعًا قبلَه **لا يُنفَّذ عليه حكمٌ حتى يبقى ممنوعًا بعدَ إعادةِ القياس**.
 *   والـ21 رقمٌ مؤقتٌ لا قائمةُ تنفيذ».
 *   و(ثالثًا): «حسمُ المعلَّقِ بشجرةِ قرارٍ **ولا تسألني عن واحدةٍ منها**».
 *
 * ◆ **وشجرةُ القرارِ تُنفَّذ بترتيبِها حرفًا — يُوقَف عند أولِ «نعم»**:
 *   ① أصليةٌ لدورةِ المساحة؟            ⇒ OWNED
 *   ② مساحةٌ شخصيةٌ أو منظرُ الإدارةِ عن نفسِها؟ ⇒ PERSONAL_SPACE / DEPT_SELF_VIEW
 *   ③ وصلته مهمةً أو اعتمادًا؟          ⇒ SHARED_WORK_ITEM (محلُّه مركزُ العمل)
 *   ④ مساحةٌ رقابيةٌ أم تنفيذية؟        ⇒ CONTROL_OVERSIGHT / EXECUTIVE_OVERSIGHT
 *   ⑤ يحتاج حقولًا منها توقّفًا حقيقيًّا؟ ⇒ CONTEXTUAL_READ_ONLY (+ الحقولُ والصفوف)
 *   ⑥ وإلا                              ⇒ FORBIDDEN
 *   **والترتيبُ نفسُه حكمٌ**: عقدةٌ سابقةٌ تُغني عن لاحقةٍ، فلا يُصنَّف «ممنوعًا»
 *   ما هو مهمةٌ واردةٌ لمجرّدِ أنه ماليٌّ في مساحةٍ لا تملكه.
 *
 * ◆ **وكلُّ حكمٍ يُشتقُّ من حقلٍ مقيسٍ لا من رأي**:
 *   · «أصليةٌ لدورةِ المساحة» = المساحةُ هي المالكُ **بعدَ حسمِ الستَّةِ والأربعين**
 *     (`gov_space_appearances.owner_dept_ar` المُحدَّث) — لا المالكُ القديم.
 *   · «مساحةٌ شخصية» = `owner_kind = PLATFORM_SHARED` **وتظهر في ≥12 مساحة**:
 *     ما يظهر لكلِّ إدارةٍ تقريبًا **منصةٌ مشترَكةٌ بحكمِ الانتشار** لا تسرّبٌ.
 *   · «رقابية/تنفيذية» = `space_kind` نفسُه (`CONTROL`/`EXECUTIVE`) — والحكمُ
 *     على التسرّبِ يختلف باختلافِ نوعِ المساحةِ (نصُّ ٢٠-٣).
 *   · «مرجعٌ يتوقف عليه العمل» = مسارٌ يظهر في **مساحاتٍ متعددةٍ (≥4)** وليس
 *     ماليًّا: انتشارُه دليلُ حاجةٍ عرضية.
 *
 * ◆ **والممنوعُ يُعاد قياسُه ولا يُنقَل**: يُعاد الحكمُ على الـ21 من الصفرِ
 *   بالشجرةِ نفسِها. فما بقيَ ممنوعًا بعدَ إعادةِ القياسِ يُنفَّذ عليه، **وما
 *   خرجَ من المنعِ يُعلَن خروجُه ولا يُنفَّذ عليه حكمُ الدفترِ القديم**.
 *
 * ◆ **ولا يُنفَّذ في هذه الهجرةِ منعٌ ولا إزالة** — تصنيفٌ وحسمٌ فقط.
 *   الإزالةُ خطوةٌ سابعةٌ في الترتيبِ ولها هجرتُها ومقامُها.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/** مسارٌ ماليٌّ أو تجاريٌّ — مجالُ العقدةِ السادسةِ حصرًا */
function is_money_route($route) {
    $r = mb_strtolower($route);
    foreach (array('finance/', 'financing/', 'contracts/collections', 'contracts/tax_invoices',
                   'contracts/price_terms', 'contracts/margin', 'reports/margin') as $pre) {
        if (strpos($r, $pre) === 0 || strpos($r, $pre) !== false) { return true; }
    }
    return false;
}

/** تطبيعُ اسمِ الإدارةِ للمطابقة — «ادارة»/«إدارة» و«ال» تختلف بحرفٍ لا بمعنى */
function norm_dept($s) {
    $s = trim((string) $s);
    $s = str_replace(array('أ', 'إ', 'آ'), 'ا', $s);
    $s = preg_replace('/^(ا?دارة|قسم|مكتب)\s+/u', '', $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return $s;
}
function dept_match($a, $b) {
    $a = norm_dept($a); $b = norm_dept($b);
    if ($a === '' || $b === '') { return false; }
    if ($a === $b) { return true; }
    $short = mb_strlen($a) <= mb_strlen($b) ? $a : $b;
    $long  = mb_strlen($a) <= mb_strlen($b) ? $b : $a;
    return mb_strlen($short) >= 4 && mb_strpos($long, $short) !== false;
}

/* ══ انتشارُ المسارِ عبرَ المساحاتِ — يُقاس ولا يُنقَل من عمودِ الدفتر ══════ */
$spread = array();
$r = $conn->query("SELECT route, COUNT(DISTINCT space_ar) n FROM gov_space_appearances GROUP BY route");
while ($r && ($x = $r->fetch_assoc())) { $spread[$x['route']] = (int) $x['n']; }

/* ══ التبعيةُ الحقيقيةُ — «توقّفٌ حقيقيٌّ لا راحة» تُقاس بالرابطِ لا بالانتشار ══
   ◆ **أولُ تنفيذٍ استعمل الانتشارَ (≥4 مساحات) مقياسًا للحاجة، فأعطى 191 ممنوعًا
     بدل 21 — وفيها `main/glossary.php`.** ومسردُ مصطلحاتٍ «ممنوعٌ» حكمٌ يكذّب
     نفسَه. والعلّة: **الانتشارُ مقياسُ شيوعٍ لا مقياسُ حاجة**؛ شاشةٌ يحتاجها
     قسمان بشدةٍ تسقط، وشاشةٌ يتصفحها خمسةٌ بلا حاجةٍ تنجو.
   ◆ **والمقياسُ الصادقُ: أتربطها شاشةٌ تملكها هذه المساحةُ فعلًا؟** فإن كان
     مسارُ عملِ الإدارةِ يقود إليها بيدِه فالتوقّفُ عليها حقيقيّ. */
/* ◆ **ومحاولةٌ ثانيةٌ سقطت أيضًا فتُسجَّل**: قِيست التبعيةُ بـ«أتربطها شاشةٌ
     تملكها المساحة؟» فلم تُطلِق إلا خمسَ مرات، وارتفعَ الممنوعُ إلى 354.
     والعلّة: **روابطُ السايدبارِ يولّدها المولِّدُ لا تُكتب في الشاشة**، فالرسمُ
     البيانيُّ فارغٌ بنيويًّا. **ومقياسٌ لا يُطلِق يُحوِّل كلَّ شيءٍ إلى ممنوع.**
   ◆ **والمقياسُ الثالثُ هو الصادق — استهلاكُ البيانات**: إن كانت شاشاتُ هذه
     المساحةِ **تقرأ الجداولَ التي تكتبها الشاشةُ المرشَّحة**، فعملُها يقوم على
     بياناتِها فعلًا. وهي قاعدتُك حرفًا: **«من يستهلك أثرَها يملك مستندَه هو»**
     — والاستهلاكُ يُقاس ولا يُفترَض. */
require_once $ROOT . '/tools/uxui_owner_tables.php';
$TBL = uot_tables($conn);
$PLAT = uot_platform($conn, $ROOT, $TBL);

$writesOf = array(); $readsOf = array();
foreach (array_keys($spread) as $rt) {
    $f = $ROOT . '/' . $rt;
    if (!is_file($f)) { continue; }
    $sn = array();
    $src = uot_path_src($f, $ROOT, 2, $sn);
    if ($src === '') { continue; }
    $writesOf[$rt] = uot_writes($src, $TBL, $PLAT);
    $readsOf[$rt]  = uot_reads($src, $TBL, $PLAT);
}

/* مِلكيةُ كلِّ مسارٍ لمساحةٍ — ثم اتحادُ ما تقرؤه شاشاتُ المساحةِ المملوكة */
$ownedBy = array();
$r = $conn->query("SELECT space_ar, route, owner_dept_ar FROM gov_space_appearances");
while ($r && ($x = $r->fetch_assoc())) {
    if (dept_match($x['space_ar'], $x['owner_dept_ar'])) { $ownedBy[$x['space_ar']][$x['route']] = 1; }
}
$spaceReads = array();
foreach ($ownedBy as $space => $routes) {
    $u = array();
    foreach (array_keys($routes) as $rt) {
        if (isset($readsOf[$rt])) { foreach (array_keys($readsOf[$rt]) as $t) { $u[$t] = 1; } }
    }
    $spaceReads[$space] = $u;
}

/** أتقرأ شاشاتُ هذه المساحةِ جدولًا تكتبه الشاشةُ المرشَّحة؟ */
function space_depends($space, $route, array $spaceReads, array $writesOf, &$shared = null) {
    $shared = array();
    if (!isset($spaceReads[$space]) || !isset($writesOf[$route])) { return false; }
    foreach (array_keys($writesOf[$route]) as $t) {
        if (isset($spaceReads[$space][$t])) { $shared[] = $t; }
    }
    return !empty($shared);
}

$rows = array();
$r = $conn->query("SELECT id, space_ar, space_kind, route, owner_dept_ar, owner_kind, src_class
                     FROM gov_space_appearances ORDER BY id");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }

$upd = $conn->prepare("UPDATE gov_space_appearances
                          SET cls = ?, decision = 'CONFIRMED', rule_step = ?, basis = ?
                        WHERE id = ?");
if (!$upd) { exit("تعذّر التحضير: {$conn->error}\n"); }

$cnt = array(); $steps = array(); $n = 0;
foreach ($rows as $x) {
    $route = $x['route'];
    $sp    = isset($spread[$route]) ? $spread[$route] : 1;
    $cls = ''; $step = 0; $why = '';

    /* ① أصليةٌ لدورةِ المساحة؟ — بالمالكِ **بعدَ حسمِ الستَّةِ والأربعين** */
    if (dept_match($x['space_ar'], $x['owner_dept_ar'])) {
        $cls = 'OWNED'; $step = 1;
        $why = 'المساحةُ هي المالكُ بعدَ حسمِ المِلكية — أصليةٌ لدورتِها';
    }
    /* ② مساحةٌ شخصيةٌ أو منظرُ الإدارةِ عن نفسِها؟ */
    elseif ($x['owner_kind'] === 'PLATFORM_SHARED' && $sp >= 12) {
        $cls = 'PERSONAL_SPACE'; $step = 2;
        $why = "منصةٌ مشترَكةٌ بحكمِ الانتشار: تظهر في {$sp} مساحةً — ما يظهر لكلِّ إدارةٍ ليس تسرّبًا";
    }
    elseif ($x['owner_kind'] === 'PLATFORM_SHARED' && $sp >= 4) {
        $cls = 'DEPT_SELF_VIEW'; $step = 2;
        $why = "منظرُ الإدارةِ عن نفسِها: شاشةٌ واحدةٌ ونطاقُها يتبع الدورَ ({$sp} مساحة)";
    }
    /* ③ وصلته مهمةً أو اعتمادًا؟ */
    elseif (preg_match('~(my_tasks|my_requests|my_tickets|approvals?|requests?|inbox|tickets?)~i', $route)) {
        $cls = 'SHARED_WORK_ITEM'; $step = 3;
        $why = 'مهمةٌ أو اعتمادٌ أو بلاغٌ يصل من إدارةٍ أخرى — محلُّه مركزُ العمل';
    }
    /* ④ مساحةٌ رقابيةٌ أم تنفيذية؟ */
    elseif ($x['space_kind'] === 'CONTROL') {
        $cls = 'CONTROL_OVERSIGHT'; $step = 4;
        $why = 'مساحةٌ رقابية — اطّلاعٌ موسومٌ لا يعدّل مستندَ المالكِ ولا يُحسب مِلكية';
    }
    elseif ($x['space_kind'] === 'EXECUTIVE') {
        $cls = 'EXECUTIVE_OVERSIGHT'; $step = 4;
        $why = 'مساحةٌ تنفيذية — مجمَّعٌ أولًا وتعمُّقٌ مصرَّحٌ ثانيًا';
    }
    /* ⑤ مرجعٌ يتوقف عليه العملُ توقّفًا حقيقيًّا؟ — **بالرابطِ لا بالانتشار** */
    /* ◆ **والماليُّ لا يدخل هذه العقدةَ ولو ثبتَ الاستهلاك** — وهي قاعدتُك حرفًا:
         «الماليةُ لا تدخل شاشةَ التشغيلِ لتعرف الساعات — يصلها حدثُ اعتمادِها
         فتُنتج استحقاقَها». **والعكسُ عكسُه**: التشغيلُ لا يأخذ `payments_fin`
         لأنه يقرأ جدولًا تكتبه المالية — **يأخذ مستندَه هو**.
         وتشغيلٌ بلا هذا القيدِ أخرجَ اثنتَي عشرةَ شاشةً ماليةً من المنعِ
         **بحجّةِ الاستهلاكِ نفسِها التي تُوجب منعَها**. والمواصفةُ صريحة:
         الماليُّ في مساحةٍ لا تملكه **يُزال ويُستبدَل بمنظرٍ مقيَّدٍ إن توقّف
         العملُ عليه** — فالمنظرُ بديلُ الإزالةِ لا مانعُها. */
    elseif (!is_money_route($route)
            && space_depends($x['space_ar'], $route, $spaceReads, $writesOf, $sharedT)) {
        $cls = 'CONTEXTUAL_READ_ONLY'; $step = 5;
        $why = 'توقّفٌ حقيقيّ مقيس: شاشاتُ هذه المساحةِ **تقرأ ما تكتبه هذه الشاشةُ** — '
             . implode(' · ', array_slice($sharedT, 0, 3));
    }
    /* ⑤-ب والشاشةُ المنصيةُ المشترَكةُ لا تُمنَع أصلًا (نصُّ ٢٠-٣: لا تُجبَر
           على مِلكيةِ إدارةٍ لا تملكها) — فمسردٌ أو دليلٌ عامٌّ ليس تسرّبًا */
    elseif ($x['owner_kind'] === 'PLATFORM_SHARED' && !is_money_route($route)) {
        $cls = 'CONTEXTUAL_READ_ONLY'; $step = 5;
        $why = "شاشةٌ منصيةٌ مشترَكةٌ لا تملكها إدارةٌ بعينِها — مرجعٌ مقيَّدٌ لا ممنوع ({$sp} مساحة)";
    }
    /* ⑥ وإلا */
    else {
        $cls = 'FORBIDDEN'; $step = 6;
        $why = is_money_route($route)
            ? 'شاشةٌ ماليةٌ أو تجاريةٌ في مساحةِ إدارةٍ لا تملكها'
            : "لا عقدةَ سابقةً تنطبق: ليست مملوكةً ولا مشترَكةً ولا رقابيةً ولا يربطها عملُ هذه المساحة ({$sp} مساحة)";
    }

    $upd->bind_param('siss', $cls, $step, $why, $x['id']);
    if ($upd->execute()) { $n++; }
    $cnt[$cls] = isset($cnt[$cls]) ? $cnt[$cls] + 1 : 1;
    $steps[$step] = isset($steps[$step]) ? $steps[$step] + 1 : 1;
}
$upd->close();

echo "══ إعادةُ تصنيفِ الـ887 وحسمُ المعلَّق ══\n";
echo "  أُعيد تصنيفُ: {$n} من " . count($rows) . " — **صفرُ معلَّقٍ باقٍ بحكمِ الشجرة**\n";

echo "\n  ┌ الصنفُ بعدَ إعادةِ القياس (وقبلَها بين قوسَين)\n";
$before = array();
$q = $conn->query("SELECT src_class, COUNT(*) n FROM gov_space_appearances GROUP BY src_class");
while ($q && ($x = $q->fetch_assoc())) { $before[$x['src_class']] = (int) $x['n']; }
arsort($cnt);
foreach ($cnt as $k => $v) {
    $b = isset($before[$k]) ? $before[$k] : 0;
    $d = $v - $b;
    printf("  │ %-24s %4d   (كان %4d · %+d)\n", $k, $v, $b, $d);
}
if (isset($before['UNRESOLVED'])) {
    printf("  │ %-24s %4d   (كان %4d · **حُسمت كلُّها**)\n", 'UNRESOLVED', 0, $before['UNRESOLVED']);
}
echo "  ├ عقدةُ الشجرةِ التي حسمَت\n";
ksort($steps);
$NODE = array(1 => 'أصليةٌ لدورةِ المساحة', 2 => 'شخصيةٌ أو منظرُ الإدارةِ عن نفسِها',
              3 => 'مهمةٌ أو اعتماد', 4 => 'رقابيةٌ أو تنفيذية',
              5 => 'مرجعٌ يتوقف عليه العمل', 6 => 'لا عقدةَ سابقةً تنطبق');
foreach ($steps as $s => $v) { printf("  │ ⑥%d %-32s %4d\n", $s, $NODE[$s], $v); }
echo "  └──────────────────────────────────────────────\n";

/* ══ الممنوعُ: أُعيد قياسُه — ولا يُنفَّذ إلا على ما بقيَ ══════════════════ */
$q = $conn->query("SELECT
        SUM(src_class='FORBIDDEN' AND cls='FORBIDDEN') stay,
        SUM(src_class='FORBIDDEN' AND cls<>'FORBIDDEN') left_,
        SUM(src_class<>'FORBIDDEN' AND cls='FORBIDDEN') new_
      FROM gov_space_appearances");
$f = $q ? $q->fetch_assoc() : array('stay' => 0, 'left_' => 0, 'new_' => 0);
echo "\n  ◆ **الممنوعُ أُعيد قياسُه لا نُقل** (وهو نصُّ «الـ21 رقمٌ مؤقتٌ لا قائمةُ تنفيذ»):\n";
echo "    · بقيَ ممنوعًا بعدَ إعادةِ القياس: **{$f['stay']}** ← وعليه وحدَه يُنفَّذ\n";
echo "    · **خرجَ من المنعِ: {$f['left_']}** ← ولا يُنفَّذ عليه حكمُ الدفترِ القديم\n";
echo "    · دخلَ المنعَ بإعادةِ القياس: **{$f['new_']}**\n";

$q = $conn->query("SELECT space_ar, COUNT(*) n FROM gov_space_appearances
                    WHERE cls='FORBIDDEN' GROUP BY space_ar ORDER BY n DESC");
echo "\n  ┌ الممنوعُ النهائيُّ بحسبِ المساحة\n";
$tot = 0;
while ($q && ($x = $q->fetch_assoc())) { $tot += (int) $x['n']; printf("  │ %-26s %3d\n", $x['space_ar'], $x['n']); }
echo "  └ الإجمالي: {$tot}\n";
exit($n === count($rows) ? 0 : 1);
