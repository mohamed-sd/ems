<?php
/**
 * tools/uxui_adoption_matrix.php — مصفوفةُ تبنّي المكوّنات (⑥ الحقيقيّ)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تصحيحُ المالك (2026-08-19 · ثالثًا): «نقلُ الـ313 سطرًا… يُثبت **إغلاقَ
 *   الأنماطِ المحليةِ** وحدَها — ولا يُثبت معماريةَ مكوّنٍ مركزيّ: فقد تسكن
 *   تسعةُ تصاميمَ مختلفةٍ في ملفٍ واحد. فاعتبر ⑥ «Local Styling Closed» ولا
 *   تعلن «Component Architecture Closed» حتى تُخرج مصفوفةَ تبنٍّ للعشر:
 *   سطحٌ ← قالبٌ ← مكوّنٌ ← variant ← المصدرُ المركزيُّ ← تجاوزٌ محليٌّ ←
 *   قواعدُ CSS منفردة».
 *
 * ◆ **والقاعدةُ المنفردةُ ليست مخالفةً بذاتها** (بنصِّه): تُصنَّف إلى
 *   `JUSTIFIED_SURFACE_VARIANT` (خريطةٌ حرارية · خطٌّ زمنيّ · رسمٌ متخصص) أو
 *   `UNJUSTIFIED_LOCAL_PATTERN`. «فالمشكلةُ ليست التفرّدَ بل **نمطًا قابلًا
 *   لإعادةِ الاستعمالِ مدفونًا استثناءً لشاشةٍ واحدة**».
 *
 * ◆ والمعيارُ الحاكم: **تعديلُ المكوّنِ مرةً واحدةً يغيّر كلَّ نظائرِه بطريقةٍ
 *   متوقَّعة**. فتُقاس نسبةُ ما يأتي من مصدرٍ مركزيٍّ إلى ما يبقى محليًّا.
 *
 * التشغيل:
 *   php tools/uxui_adoption_matrix.php
 *   php tools/uxui_adoption_matrix.php --md=<path>
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');
$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = isset($m[2]) ? $m[2] : '1'; }
}

/* ── القوالبُ الخمسةُ (ف٨-١) ── */
$TEMPLATES = array(
    'RECORD_PAGE'    => 'صفحةُ السجل',
    'ENTITY_CARD'    => 'بطاقةُ الكيان',
    'FORM'           => 'النموذج',
    'DASHBOARD'      => 'اللوحة',
    'APPROVAL_QUEUE' => 'صندوقُ الاعتماد',
);

/* ── المكوّناتُ المركزيةُ وكيف تُكشف في السطحِ المُصيَّرِ أو مصدرِه ── */
$COMPONENTS = array(
    'ترويسةُ الصفحة'   => array('src' => 'includes/page_header.php',      'needle' => 'page_header.php'),
    'شريطُ الرحلة'     => array('src' => 'includes/journey_bar.php',      'needle' => 'journey_bar'),
    'INJAZ DataGrid'   => array('src' => 'assets/js/ui-unification.js',   'needle' => 'alltables'),
    'قائمةُ الأعمدة'   => array('src' => 'assets/js/ui-unification.js',   'needle' => 'ems-colvis|group-'),
    'حالاتُ الشاشة'    => array('src' => 'assets/css/ems-states.css',     'needle' => 'ems_states_bundle|ems_state_empty'),
    'وسمُ الحالة'      => array('src' => 'includes/status_display.php',   'needle' => 'ems_status_badge|ux-status'),
    'الخطوةُ التالية'  => array('src' => 'includes/screen_contract.php',  'needle' => 'ems_next_step'),
    'تعريفُ الشاشة'    => array('src' => 'includes/screen_contract.php',  'needle' => 'ems_screen_about'),
    'الرسائلُ العابرة' => array('src' => 'assets/css/ems-alerts.css',     'needle' => 'EmsAlert|ems-inline-alert'),
    'محاورُ القشرة'    => array('src' => 'includes/screen_contract.php',  'needle' => 'ems_shell_axes'),
    'نظامُ الأزرار'    => array('src' => 'assets/css/ems-buttons.css',    'needle' => 'btn-primary|btn-secondary|ux-btn'),
    'نظامُ النماذج'    => array('src' => 'assets/css/ems-forms.css',      'needle' => 'allforms|ems-form'),
);

/* ═══════════════════════════════════════════════════════════════════════════
 * المقامُ **لكلِّ قالبٍ** لا اثنا عشرَ للجميع (قرارُ المالك · خامسًا)
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ نصُّه: «المصفوفةُ تقيس كلَّ سطحٍ بـ12 مكوّنًا **مهما كان قالبُه** — واللوحةُ
 *   لا تحتاج نظامَ نماذجَ ولا جدولًا موحَّدًا. ولذلك `sites_board` تظهر **4/12**
 *   وهي بصفرِ نمطٍ منفردٍ وصفرِ تجاوز. **اجعلِ المقامَ لكلِّ قالب: المتبنّى ÷
 *   ما يحتاجه هذا القالب**».
 *
 * ◆ **والحاجةُ تُقرأ من بنيةِ القالبِ في ف٨-١ حرفًا** — لا من تقديرٍ:
 *   · صفحةُ السجل: «ترويسةٌ ← شريطٌ موحَّدٌ ← مرشِّحاتٌ ← الجدولُ ← التصفيح»
 *   · بطاقةُ الكيان: «ترويسةٌ بهويةِ الكيانِ وحالتِه ← تبويباتٌ ← محتوًى ← إجراءات»
 *   · النموذج: «ترويسةٌ ← أقسامٌ ← شبكةُ حقولٍ ← شريطُ حفظ»
 *   · اللوحة: «ترويسةٌ ← بطاقاتُ قرارٍ ← رسومٌ ← جدولُ تفصيلٍ واحد»
 *   · صندوقُ الاعتماد: «ترويسةٌ ← تبويباتُ الأنواعِ ← جدولٌ واحدٌ بشريطٍ واحد»
 *
 * ◆ **وخمسةٌ مشترَكةٌ لكلِّ قالبٍ بلا استثناء** — لأنها قشرةٌ لا محتوى:
 *   ترويسةُ الصفحة · حالاتُ الشاشة · الرسائلُ العابرة · نظامُ الأزرار ·
 *   تعريفُ الشاشة. فما من قالبٍ يُعفى منها.
 *
 * ◆ **ولا يُطرح مكوّنٌ لأن الشاشةَ لا تستعمله** — يُطرح لأن **قالبَها لا يقتضيه**.
 *   والفرقُ جوهريّ: الأولُ يُخفي نقصًا، والثاني يُصحّح مقامًا.
 * ═══════════════════════════════════════════════════════════════════════════ */
$TEMPLATE_NEEDS = array(
    /* المشترَكُ في الخمسةِ كلِّها */
    '_shared' => array('ترويسةُ الصفحة', 'حالاتُ الشاشة', 'الرسائلُ العابرة',
                       'نظامُ الأزرار', 'تعريفُ الشاشة'),
    'صفحةُ السجل'      => array('INJAZ DataGrid', 'قائمةُ الأعمدة', 'وسمُ الحالة'),
    'بطاقةُ الكيان'     => array('شريطُ الرحلة', 'وسمُ الحالة', 'الخطوةُ التالية', 'محاورُ القشرة'),
    'النموذج'          => array('نظامُ النماذج', 'الخطوةُ التالية'),
    'اللوحة'           => array('INJAZ DataGrid', 'وسمُ الحالة'),
    'صندوقُ الاعتماد'   => array('INJAZ DataGrid', 'وسمُ الحالة', 'الخطوةُ التالية'),
);

/** ما يقتضيه القالبُ — المشترَكُ + خاصَّتُه. وقالبٌ مجهولٌ يأخذ الاثنَي عشرَ كلَّها. */
function ems_template_needs($tpl, $TEMPLATE_NEEDS, $COMPONENTS) {
    if (!isset($TEMPLATE_NEEDS[$tpl])) { return array_keys($COMPONENTS); }
    return array_values(array_unique(array_merge($TEMPLATE_NEEDS['_shared'], $TEMPLATE_NEEDS[$tpl])));
}
/* ── الأنماطُ القابلةُ لإعادةِ الاستعمالِ — إن ظهرت منفردةً فهي غيرُ مبرَّرة ── */
$REUSABLE_HINTS = array('badge', 'card', 'btn', 'toolbar', 'chip', 'table', 'modal', 'drawer',
                        'tab', 'alert', 'empty', 'loading', 'header', 'filter', 'grid', 'row');
/* ── وأنماطٌ سطحيةٌ بطبيعتِها — تفرُّدُها مبرَّرٌ بنصِّ القرار ── */
$SURFACE_SPECIFIC = array('heat', 'timeline', 'chart', 'gantt', 'map', 'calendar', 'sparkline',
                          'journey', 'gauge', 'progress', 'bell', 'star', 'shot');

$screens = array();
$q = $conn->query("SELECT screen_file, title_ar, category FROM gov_golden_approvals ORDER BY id");
while ($q && ($x = $q->fetch_assoc())) { $screens[] = $x; }

$css = (string) @file_get_contents($ROOT . '/assets/css/ems-screens.css');
/* قواعدُ الورقةِ المركزيةِ مفكَّكةً: المحدِّدُ ⇐ نصُّه */
$rules = array(); $ruleBody = array();
if (preg_match_all('~([^{}]+)\{([^}]*)\}~s', $css, $mm, PREG_SET_ORDER)) {
    foreach ($mm as $r) {
        $sel = trim(preg_replace('~/\*.*?\*/~s', '', $r[1]));
        if ($sel === '' || $sel[0] === '@') { continue; }
        $rules[] = $sel;
        $ruleBody[$sel] = $r[2];
    }
}

/**
 * أهي **تصميمُ مكوّنٍ** أم **معدِّلٌ فوقَ مكوّن؟** — والفرقُ هو المسألةُ كلُّها.
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تصحيحُ تصنيفي بعدَ قراءةِ القيمِ الفعلية: أولُ صياغةٍ وسمت كلَّ محدِّدٍ فيه
 *   `badge`/`card`/`alert` نمطًا مدفونًا — فوسمت `.fin-inbox-badge`
 *   (`font-size` و`padding` فقط) كما وسمت `.stats-card` (سبعُ خصائصَ بخلفيةٍ
 *   وحدٍّ وظلٍّ ونصفِ قطر). والثاني تصميمٌ كاملٌ مدفون، والأولُ **معدِّلُ مقاسٍ
 *   فوقَ مكوّنٍ قائم** — وهو استعمالٌ مشروعٌ لا مخالفة.
 * ◆ ونصُّ المالك يميّزهما: «المشكلةُ ليست التفرّدَ بل **نمطًا قابلًا لإعادةِ
 *   الاستعمال** مدفونًا» — والمعدِّلُ ليس نمطًا يُعاد استعمالُه بل ضبطُ نسخةٍ.
 * ◆ فالمعيارُ المقيس: **ثلاثُ خصائصَ فأكثر ومنها خاصيةُ هيكلٍ** (خلفيةٌ · حدٌّ ·
 *   نصفُ قطرٍ · ظلٌّ · شبكةٌ) ⇒ تصميمٌ مدفون. وما دونَه معدِّل.
 */
function is_structural($body) {
    $decls = array_filter(array_map('trim', explode(';', (string) $body)));
    if (count($decls) < 3) { return false; }
    return (bool) preg_match('~(^|\s)(background|border|border-radius|box-shadow|grid-template)~i', (string) $body);
}
/* بادئةُ كلِّ سطحٍ تُشتقُّ من أصنافِه في مصدرِه — لا تُخمَّن */
function prefixes_of($src) {
    $p = array();
    if (preg_match_all('~class="([^"]*)"~', $src, $m)) {
        foreach ($m[1] as $cl) {
            foreach (preg_split('/\s+/', $cl) as $c) {
                if (preg_match('~^([a-z]{2,12})-~i', $c, $g)) { $p[strtolower($g[1])] = true; }
            }
        }
    }
    return array_keys($p);
}

$rows = array(); $totalSingle = 0; $totalJust = 0; $totalUnjust = 0;
foreach ($screens as $s) {
    $file = $s['screen_file'];
    $src = (string) @file_get_contents($ROOT . '/' . $file);
    if ($src === '') { continue; }

    /* ① المكوّناتُ المتبنّاةُ ومصدرُ كلٍّ */
    $used = array(); $missing = array();
    /* ◆ **القالبُ يُحسب هنا لا عند بناءِ الصفّ** — لأن المقامَ يعتمد عليه.
         وحسابُه متأخّرًا جعل الخريطةَ تُستدعى باسمٍ فارغٍ فرجعت الاثنَي عشرَ
         كلَّها، ثم رجعت 0/0 حين وُصلت — **وكلاهما رقمٌ بلا معنى**. */
    $tplName = isset($TEMPLATES[$s['category']]) ? $TEMPLATES[$s['category']] : ($s['category'] ?: '—');
    $needs = ems_template_needs($tplName, $TEMPLATE_NEEDS, $COMPONENTS);
    $notNeeded = array();
    foreach ($COMPONENTS as $name => $def) {
        $isNeeded = in_array($name, $needs, true);
        if (preg_match('~(' . $def['needle'] . ')~i', $src)) {
            /* المتبنّى يُحسب وإن لم يقتضِه القالبُ — وتبنٍّ زائدٌ ليس عيبًا،
               لكنّه لا يُضخّم المقامَ ولا يُنقصه. */
            $used[$name] = $def['src'];
            if (!$isNeeded) { $notNeeded[] = $name; }
        } elseif ($isNeeded) {
            $missing[] = $name;
        }
        /* وما لم يُتبنَّ ولم يقتضِه القالبُ: **خارجَ المقامِ تمامًا** — لا نقصًا
           ولا تبنّيًا. وهو عينُ تصحيحِ المالك (خامسًا). */
    }
    $needCount = count($needs);
    $usedNeeded = 0;
    foreach ($needs as $n) { if (isset($used[$n])) { $usedNeeded++; } }

    /* ② القواعدُ المنفردةُ — محدِّدٌ لا يستعمله إلا هذا السطح */
    $pfx = prefixes_of($src);
    $mine = array();
    foreach ($rules as $sel) {
        foreach ($pfx as $p) {
            if (preg_match('~\.' . preg_quote($p, '~') . '-~i', $sel)) { $mine[] = $sel; break; }
        }
    }
    $mine = array_values(array_unique($mine));
    /* أيُّها يستعمله سطحٌ آخرُ أيضًا؟ (فليس منفردًا) */
    $single = array();
    foreach ($mine as $sel) {
        $usedElsewhere = false;
        foreach ($screens as $o) {
            if ($o['screen_file'] === $file) { continue; }
            $osrc = (string) @file_get_contents($ROOT . '/' . $o['screen_file']);
            foreach (prefixes_of($osrc) as $op) {
                if (preg_match('~\.' . preg_quote($op, '~') . '-~i', $sel)) { $usedElsewhere = true; break 2; }
            }
        }
        if (!$usedElsewhere) { $single[] = $sel; }
    }

    /* ③ تصنيفُ المنفردة */
    $just = array(); $unjust = array();
    foreach ($single as $sel) {
        $low = mb_strtolower($sel);
        $isSurface = false;
        foreach ($SURFACE_SPECIFIC as $h) { if (strpos($low, $h) !== false) { $isSurface = true; break; } }
        $isReusable = false;
        foreach ($REUSABLE_HINTS as $h) { if (strpos($low, $h) !== false) { $isReusable = true; break; } }
        /* المعدِّلُ ليس نمطًا مدفونًا مهما كان اسمُه — والتصميمُ الكاملُ مدفونٌ ولو لم يُسمَّ */
        $structural = is_structural(isset($ruleBody[$sel]) ? $ruleBody[$sel] : '');
        if ($isSurface && !$isReusable) { $just[] = $sel; }
        elseif ($isReusable && $structural) { $unjust[] = $sel; }
        else { $just[] = $sel; }
    }
    $totalSingle += count($single); $totalJust += count($just); $totalUnjust += count($unjust);

    $rows[] = array(
        'file' => $file, 'title' => $s['title_ar'],
        'template' => $tplName,
        'needCount' => $needCount, 'usedNeeded' => $usedNeeded, 'notNeeded' => $notNeeded,
        'used' => $used, 'missing' => $missing,
        'inline' => preg_match_all('~\sstyle\s*=\s*["\']~i', $src),
        'blocks' => preg_match_all('~<style\b~i', $src),
        'single' => count($single), 'just' => $just, 'unjust' => $unjust,
    );
}

/* ── التقرير ── */
echo "════ مصفوفةُ تبنّي المكوّنات — ⑥ الحقيقيّ ════\n\n";
$adoptSum = 0; $compSum = 0;
foreach ($rows as $r) {
    /* ◆ **المقامُ ما يقتضيه القالبُ** لا الاثنَي عشرَ للجميع (قرارُ المالك · خامسًا) */
    $adopt = (int) $r['usedNeeded']; $tot = (int) $r['needCount'];
    $adoptSum += $adopt; $compSum += $tot;
    printf("▐ %s — %s\n", $r['file'], $r['title']);
    printf("   القالب: %s · مكوّناتٌ مركزية: %d/%d · تجاوزٌ محليّ: كتل=%d سطرية=%d\n",
        $r['template'], $adopt, $tot, $r['blocks'], $r['inline']);
    if (!empty($r['notNeeded'])) {
        printf("     ◆ متبنًّى زيادةً على ما يقتضيه القالب (%d): %s
",
               count($r['notNeeded']), implode(' · ', array_slice($r['notNeeded'], 0, 4)));
    }
    if (!empty($r['missing'])) {
        printf("     ✗ ناقصٌ **ويقتضيه القالب** (%d): %s
",
               count($r['missing']), implode(' · ', array_slice($r['missing'], 0, 4)));
    }
    printf("   قواعدُ CSS منفردة: %d — مبرَّرة=%d · **غيرُ مبرَّرة=%d**\n",
        $r['single'], count($r['just']), count($r['unjust']));
    if ($r['unjust']) {
        echo "     ◆ UNJUSTIFIED_LOCAL_PATTERN (نمطٌ قابلٌ لإعادةِ الاستعمالِ مدفونٌ في شاشة):\n";
        foreach (array_slice($r['unjust'], 0, 5) as $u) { echo "       · " . mb_substr(trim($u), 0, 64) . "\n"; }
        if (count($r['unjust']) > 5) { echo "       … و" . (count($r['unjust']) - 5) . " غيرُها\n"; }
    }
    echo "\n";
}
$pct = $compSum > 0 ? round($adoptSum * 100 / $compSum, 1) : 0;
echo "════ الحصيلة ════\n";
echo "  تبنّي المكوّناتِ المركزية: {$adoptSum}/{$compSum} = {$pct}٪\n";
echo "  قواعدُ CSS منفردة: {$totalSingle} · مبرَّرة: {$totalJust} · **غيرُ مبرَّرة: {$totalUnjust}**\n";
echo "  تجاوزٌ محليٌّ في الشاشات: كتلُ style=" . array_sum(array_column($rows, 'blocks'))
   . " · سماتٌ سطرية=" . array_sum(array_column($rows, 'inline')) . "\n\n";
echo "  ◆ الحال: " . ($totalUnjust === 0 && $pct >= 80 ? 'TECHNICALLY_ELIGIBLE' : 'IN_PROGRESS')
   . " — و«مغلقة» لا تُعلَن إلا بصفرِ نمطٍ غيرِ مبرَّرٍ ومقامٍ مكتمل.\n";
echo "  ◆ لا يقيس: **أن تعديلَ المكوّنِ يغيّر نظائرَه فعلًا** — ذاك يحتاج تعديلًا\n";
echo "    تجريبيًّا وقياسَ أثرِه في كلِّ نظير، وهو اختبارٌ مستقلٌّ لم يُجرَ بعد.\n";

if (!empty($args['md'])) {
    $L = array('# مصفوفةُ تبنّي المكوّنات — ⑥ الحقيقيّ', '',
        '· ' . date('Y-m-d H:i') . ' · `php tools/uxui_adoption_matrix.php --md=<الملف>`',
        '· ⑥ حالُه **`LOCAL_STYLING_CLOSED`** — و«معماريةُ مكوّنٍ» لا تُعلَن حتى تصفيرِ غيرِ المبرَّر.', '',
        '| السطح | القالب | مكوّنات مركزية | تجاوز محلي | منفردة | مبرَّرة | **غير مبرَّرة** |',
        '|---|---|---|---|---|---|---|');
    foreach ($rows as $r) {
        $L[] = '| `' . $r['file'] . '` | ' . $r['template'] . ' | ' . count($r['used']) . '/' . (count($r['used']) + count($r['missing']))
             . ' | ' . ($r['blocks'] + $r['inline']) . ' | ' . $r['single'] . ' | ' . count($r['just']) . ' | **' . count($r['unjust']) . '** |';
    }
    $L[] = '';
    $L[] = '**التبنّي:** ' . $adoptSum . '/' . $compSum . ' = ' . $pct . '٪ · **قواعدُ منفردة:** ' . $totalSingle
         . ' (مبرَّرة ' . $totalJust . ' · **غيرُ مبرَّرة ' . $totalUnjust . '**)';
    $L[] = '';
    $L[] = '◆ **لا يقيس**: أن تعديلَ المكوّنِ يغيّر نظائرَه فعلًا — اختبارٌ مستقلٌّ لم يُجرَ بعد.';
    file_put_contents($args['md'], implode("\n", $L) . "\n");
    echo "\nMD ⇐ {$args['md']}\n";
}
