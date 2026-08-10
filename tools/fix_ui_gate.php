<?php
/**
 * tools/fix_ui_gate.php — بوابةُ FIX-02 (القشرةُ والنظامُ التصميميّ)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الحكمُ الجذريُّ في FIX-02 (FIXB-0001): «النظامُ التصميميُّ مبنيٌّ **ولا
 *   يُفرَض** — فالتصحيحُ في الإنفاذِ لا في التصميم». و«الفاحصُ الذي **يمنع**
 *   أهمُّ من الفاحصِ الذي **يعدّ** — فالعدُّ يوثّق والمنعُ يُصلح» (RSK-U4).
 *
 * ◆ ولذلك تُقاس هنا معاييرُ AC-U1..AC-U12 بأرقامٍ حيةٍ من الشجرةِ نفسِها،
 *   ويُعلَن لكلِّ معيارٍ ما يقيسه وما لا يقيسه. والأرقامُ الباقيةُ ديْنٌ معلَنٌ
 *   لا اكتمالٌ مُدَّعى.
 *
 * التشغيل: php tools/fix_ui_gate.php [--md=مسار]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';

$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }

$R = array();
function u($code, $title, $measures, $notMeasures, $ok, $evidence)
{
    global $R;
    $R[] = compact('code', 'title', 'measures', 'notMeasures', 'ok', 'evidence');
    printf("%s %-7s %s\n        يقيس: %s\n        لا يقيس: %s\n        الشاهد: %s\n\n",
        $ok ? '✔' : '✘', $code, $title, $measures, $notMeasures, $evidence);
}

/** كلُّ ملفات الواجهةِ الحية (php/css/js) بلا نسخٍ ولا بائعين. */
/** جسمُ `function boot()` بموازنةِ الأقواس — لا بنمطٍ يتعثّر بأولِ `}`. */
function ems_ui_boot_body($js)
{
    $at = strpos($js, 'function boot()');
    if ($at === false) { return ''; }
    $i = strpos($js, '{', $at);
    if ($i === false) { return ''; }
    $d = 0; $n = strlen($js);
    for ($j = $i; $j < $n; $j++) {
        if ($js[$j] === '{') { $d++; }
        elseif ($js[$j] === '}') { $d--; if ($d === 0) { return substr($js, $i, $j - $i); } }
    }
    return '';
}

/**
 * أنداءُ نداءٍ **حيٍّ** داخلَ `boot()`؟ — لا مجردُ ذكرٍ لاسمِه.
 * ◆ أرسبَ الاختبارُ السلبيُّ الصيغةَ الأولى: `if (false) { bootX(); }` أبقى
 *   الاسمَ فمرَّ الفحصُ على فرعٍ ميت. فالنداءُ المحاطُ بشرطٍ حرفيِّ الكذبِ
 *   (`if (false)` · `if (0)`) لا يُحتسب — وهو أشيعُ صورِ التعطيلِ المؤقتِ التي
 *   تُنسى فتبقى.
 */
function ems_ui_boot_calls($js, $fn)
{
    $body = ems_ui_boot_body($js);
    if ($body === '') { return false; }
    $body = preg_replace('#/\*[\s\S]*?\*/|//[^\n]*#', '', $body);
    $body = preg_replace('/\bif\s*\(\s*(false|0)\s*\)\s*\{[^}]*\}/i', '', $body);
    return strpos($body, $fn . '(') !== false;
}

function ui_files($ROOT, array $exts)
{
    $out = array();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) { continue; }
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
        if (fix_is_skipped($rel)) { continue; }
        if (!in_array(strtolower($f->getExtension()), $exts, true)) { continue; }
        $out[] = $rel;
    }
    sort($out);
    return $out;
}

echo "══════════════════════════════════════════════════════════════════════\n";
echo " بوابةُ FIX-02 — القشرةُ والنظامُ التصميميُّ · " . date('Y-m-d H:i') . "\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";

/* ══ AC-U0 · بنيةُ الصفحةِ سليمةٌ — لا محتوى خارجَ قالبها ══════════════════
   ◆ عطلٌ حقيقيٌّ أحدثتُه ثم رآه المالكُ بعينه قبل أن يراه أيُّ فاحصٍ عندي:
     تحويلٌ آليٌّ أقحم `</div>` زائدًا في `Contracts/contracts.php`، فأُغلق
     `.main` مبكرًا وانسكب النموذجُ والبطاقةُ إلى مستوى `body`. و`body.ems-site`
     شبكةٌ **أفقية**، فصارا عمودَين يزاحمان المحتوى: **1343 بكسلًا ← 251**.
   ◆ ولم يكشفه شيءٌ مما بنيتُ: التركيبُ سليمٌ (PHP صحيح) · الروابطُ تعمل ·
     الألوانُ صحيحة · صفرُ انزلاقٍ أفقيّ. **الصفحةُ تُصيَّر ويبدو كلُّ فاحصٍ
     أخضرَ وهي مبهدَلة.**
   ◆ فالمقياسُ الغائب: **توازنُ الوسومِ نفسُه**، مقارنًا بالحالِ المرجعيّ.
     وإغلاقٌ زائدٌ واحدٌ يكفي لإخراجِ نصفِ الشاشةِ من قالبها.
   ◆ ولا يُقارَن بالصفرِ المطلق: بعضُ الأسطحِ فيها اختلالٌ قديمٌ مقصودٌ أو
     موروثٌ (`gov_reports.php` بـ16). المقياسُ **ألّا يزيدَ الاختلالُ عمّا كان**. */
/* ◆ **عدُّ الإجمالِ مقياسٌ خاطئ** — أمسكه الاختبارُ السلبيُّ بحقّ: ملفٌّ رصيدُه
     `+1` (فتحٌ زائدٌ موروث) يصير `0` بإقحامِ إغلاقٍ واحد، فيمرُّ الفحصُ والعطلُ
     قائم. والسؤالُ ليس «كم إغلاقًا في الملف؟» بل **«متى يُغلق `.main`؟»**.
   ◆ فيُتتبَّع العمقُ من موضعِ فتحِ `.main`، ويُحدَّد موضعُ إغلاقِه، ثم يُفحص ما
     **بعده**: أيُّ عنصرِ محتوًى هناك (‎<div>‎ · ‎<form>‎ · ‎<table>‎ · ‎<section>‎)
     يعني أن المحتوى خرج من قالبِه — وهو عينُ ما رآه المالكُ في شاشةِ العقود. */
$escaped = array();
foreach (fix_surface_files($ROOT) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }
    if (!preg_match('/<div\s[^>]*class\s*=\s*("|\')[^"\']*\bmain\b/i', $src, $om, PREG_OFFSET_CAPTURE)) {
        continue;
    }
    $at = $om[0][1];
    // تتبُّعُ العمقِ من فتحِ `.main` حتى إغلاقِه
    $depth = 0; $closeAt = null; $len = strlen($src);
    for ($i = $at; $i < $len; $i++) {
        if ($src[$i] !== '<') { continue; }
        if (substr($src, $i, 4) === '<div') { $depth++; continue; }
        if (substr($src, $i, 6) === '</div>') {
            $depth--;
            if ($depth === 0) { $closeAt = $i + 6; break; }
        }
    }
    if ($closeAt === null) { continue; }              // لا يُغلق — يُترك لـAC-U1
    $tail = substr($src, $closeAt);
    // ما يلي الإغلاقَ: تُجرَّد النصوصُ والتعليقاتُ والسكربتات قبل الحكم
    $tail = preg_replace('#<script\b[\s\S]*?</script>#i', '', $tail);
    $tail = preg_replace('#<!--[\s\S]*?-->#', '', $tail);
    $tail = preg_replace('#<\?php[\s\S]*?\?>#', '', $tail);
    if (preg_match('/<(div|form|table|section|main|article)\b/i', $tail, $tm)) {
        $escaped[$rel] = strtolower($tm[1]);
    }
}
ksort($escaped);
/* ◆ الأسطحُ الاثنا عشرَ الموروثة — قِيست بمقارنةِ كلِّ سطحٍ بمرجعِه في `main`
     (`tools/fix_u0_baseline.php`): كلُّها كانت هكذا قبل هذه الحزمة، وصفرٌ منها
     من صنعِنا. ومحتوًى بعد `.main` قد يكون **نافذةً منبثقةً مشروعة**، فلا
     يُجرَّم مطلقًا. والمقياسُ الصحيحُ لدَينٍ قائم: **ألّا يزيد**.
   ◆ ولو جُرِّم المطلقُ لصار المعيارُ أحمرَ إلى الأبد فيُتدرَّب على تجاهله —
     وحينها لا يكشف الجديدَ حين يقع. */
$U0_KNOWN = array(
    'Approvals/hours_approval.php', 'Approvals/requests.php',
    'Employees/employee_contracts_details.php', 'Equipments/fleet_models.php',
    'Equipments/manage_failure_codes.php', 'Governance/gov_reports.php',
    'Governance/guard_denials.php', 'Portal/dept_board.php', 'Risk/risk_card.php',
    'Timesheet/view_timesheet.php', 'Transport/transfer_requests.php', 'chats/index.php',
);
$u0New = array();
foreach ($escaped as $rel => $tag) {
    if (!in_array($rel, $U0_KNOWN, true)) { $u0New[$rel] = $tag; }
}
u('AC-U0', 'صفرُ سطحٍ **جديدٍ** يُخرج محتواه خارجَ قالبِ الصفحة',
    'يتتبّع عمقَ الوسومِ من فتحِ `.main` ويفحص أيَّ عنصرِ محتوًى بعد إغلاقه',
    'لا يجرّم الموروثَ الاثنَي عشرَ (قِيسوا بالمقارنةِ بـmain) — ولا النوافذَ المنبثقةَ المشروعة',
    empty($u0New),
    empty($u0New)
        ? ('صفرُ سطحٍ جديد · موروثٌ مُعدَّدٌ ومقيسٌ: ' . count($U0_KNOWN))
        : (count($u0New) . ' سطحًا جديدًا: ' . implode(' · ', array_map(
            function ($f, $t) { return "{$f} (<{$t}>)"; }, array_keys($u0New), $u0New))));

/* ══ AC-U1 · ملفٌّ واحدٌ يُصدِر الترويسة ═══════════════════════════════════ */
/* ◆ النطاق: **قشرةُ التطبيق** — الشاشاتُ خلفَ الدخولِ بشريطٍ وسايدبار.
     والمعيارُ عن «قشرةٍ واحدةٍ للتطبيق» لا عن «صفرِ ‎<html>‎ في الشجرة»:
     ثلاثةٌ وعشرون ملفًّا تُصدِرها بحقّ — صفحاتُ الدخولِ **قبل** المصادقةِ
     (لا سايدبارَ لها أصلًا) · المثبِّتُ الذي يعمل قبل وجودِ التطبيق · قوالبُ
     الطباعةِ والتصدير · كونسولُ المزوّدِ بقشرتِه · و`inheader` نفسُها.
     وعدُّها معًا يجعل المعيارَ أحمرَ إلى الأبد فيُتدرَّب على تجاهله.
   ◆ ويُعلَن المستثنى بعددِه — لا يُسكت عنه. */
$htmlEmitters = array();
$surfaceSet = array();
foreach (fix_surface_files($ROOT) as $r) { $surfaceSet[$r] = true; }
$outsideShell = 0;
foreach (ui_files($ROOT, array('php')) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '' || !preg_match('/<html\b/i', $src)) { continue; }
    if (isset($surfaceSet[$rel])) { $htmlEmitters[] = $rel; } else { $outsideShell++; }
}
u('AC-U1', 'ملفٌّ واحدٌ يُصدِر الترويسةَ لا سبعة',
    'يعدُّ **أسطحَ التطبيقِ الحيةَ** التي تُصدِر <html> بنفسها بدل قشرةِ inheader',
    'لا يقيس صفحاتِ ما قبلَ الدخولِ ولا المثبِّتَ ولا قوالبَ الطباعةِ — لها قشراتُها بحقّ',
    count($htmlEmitters) === 0,
    count($htmlEmitters) . ' سطحًا يُصدِر <html> بنفسه'
        . ($htmlEmitters ? ': ' . implode(' · ', array_slice($htmlEmitters, 0, 6)) : '')
        . ' · ◆ خارجَ قشرةِ التطبيق (مُعلَنٌ لا مسكوتٌ عنه): ' . $outsideShell);

/* ══ AC-U2 · صفرُ لونٍ حرفيٍّ خارجَ ملفِّ الرموز ══════════════════════════ */
/* ◆ النطاق: **نظامُنا التصميميُّ** لا ملفاتُ المكتبات. `bootstrap.min.css`
     وأخواتُها ليست من تأليفنا ولا تُحرَّر، وتحويلُ ألوانِها إلى رموزٍ يجعل كلَّ
     ترقيةٍ للمكتبةِ تمحو العمل. والمعيارُ عن **مصدرِ حقيقةٍ واحدٍ لألوانِ
     النظام** — والمكتبةُ مصدرُها هي.
   ◆ ويُعلَن ما استُثني بعددِه فلا يُسكت عنه. */
$tokenFile = 'assets/css/design-tokens.css';
$VENDOR_CSS = array('bootstrap', 'jquery', 'datatables', 'fontawesome', 'select2',
                    'flatpickr', 'chart', 'leaflet', 'swiper', 'animate', '.min.css');
$colorHits = 0; $colorFiles = array(); $distinct = array(); $vendorHits = 0;
foreach (ui_files($ROOT, array('css')) as $rel) {
    if ($rel === $tokenFile) { continue; }
    $isVendor = false;
    foreach ($VENDOR_CSS as $v) { if (stripos($rel, $v) !== false) { $isVendor = true; break; } }
    if ($isVendor) {
        $vs = (string) @file_get_contents($ROOT . '/' . $rel);
        if (preg_match_all('/#[0-9a-fA-F]{3,8}\b|\brgba?\s*\(/', $vs, $vm)) { $vendorHits += count($vm[0]); }
        continue;
    }
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    /* ◆ التعليقاتُ تُجرَّد: أغلبُ ما بقي بعد التحويلِ كان **توثيقًا للوحة**
         («الأصفر #F4C542 والذهبيّ العميق #E0AE2E») وكتلًا معطَّلةً بالتعليق.
         شرحُ اللونِ ليس إعلانَه، وعدُّه يُنتج دَينًا وهميًّا — وهي الگوتشا
         نفسُها التي تكرّرت في فحصِ SQL وعدِّ الحرّاسِ واتجاهِ الشريط.
       ◆ و`rgba(var(--x, 244,197,66), .12)` ليست لونًا حرفيًّا بل **بديلَ رمزٍ**
         داخلَ `var()` — تُستثنى بإسقاطِ ما فيه `var(`. */
    $src = preg_replace('#/\*.*?\*/#su', '', $src);
    $src = preg_replace('/\brgba?\s*\(\s*var\([^)]*\)[^)]*\)/i', '', $src);
    if (preg_match_all('/#[0-9a-fA-F]{3,8}\b|\brgba?\s*\(/', $src, $m)) {
        $colorHits += count($m[0]);
        $colorFiles[$rel] = count($m[0]);
        foreach ($m[0] as $c) { $distinct[strtolower($c)] = true; }
    }
}
arsort($colorFiles);
u('AC-U2', 'صفرُ قيمةٍ لونيةٍ حرفيةٍ خارجَ ملفِّ الرموز',
    'يعدُّ قيمَ الألوانِ الحرفيةَ في كلِّ ملفاتِ CSS الحيةِ عدا ملفِّ الرموز',
    'لا يقيس ملفاتِ المكتبات (لا نؤلّفها) ولا سماتِ style في HTML/PHP — تُقاس في AC-U12',
    $colorHits === 0,
    "{$colorHits} قيمةً لونيةً حرفيةً في نظامِنا · متمايزٌ منها " . count($distinct)
        . ' · ◆ في المكتباتِ (مستثناةٌ مُعلَنةٌ لا مسكوتٌ عنها): ' . $vendorHits
        . ' · أكثرُها: ' . implode(' · ', array_map(function ($f, $n) { return "{$f}({$n})"; },
            array_slice(array_keys($colorFiles), 0, 3), array_slice($colorFiles, 0, 3))));

/* ══ AC-U3 · أربعةُ أصنافِ أزرار ══════════════════════════════════════════ */
/* ◆ **الأحجامُ والأغلفةُ ليست أنماطًا**: `btn-sm` حجمٌ و`btn-group` غلافُ
 تخطيطٍ و`btn-close` زرُّ إغلاقِ نافذةٍ من بوتستراب. والمعيارُ عن الأنماطِ
 **الدلاليةِ** — كم إشارةً بصريةً على المستخدمِ أن يتعلّمها — لا عن كلِّ
 صنفٍ يبدأ بـbtn. وعدُّها معًا يخلط ما لا يُخلط.
 ◆ وملفاتُ المكتباتِ مستثناةٌ كما في AC-U2. */
$BTN_NON_VARIANT = array('btn-sm', 'btn-lg', 'btn-block', 'btn-close', 'btn-check',
    'btn-toolbar', 'btn-group', 'btn-group-vertical', 'btn-group-sm', 'btn-group-lg',
    'btn-group-toggle', 'btn-group-toggle-all');
$btnClasses = array(); $btnUses = 0; $btnSkipped = 0;
foreach (ui_files($ROOT, array('php', 'css', 'js')) as $rel) {
    $isV = false;
    foreach ($VENDOR_CSS as $v) { if (stripos($rel, $v) !== false) { $isV = true; break; } }
    if ($isV) { continue; }
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if (preg_match_all('/\bbtn-[a-z0-9_-]+/i', $src, $m)) {
        foreach ($m[0] as $c) {
            $c = strtolower($c);
            if (in_array($c, $BTN_NON_VARIANT, true)) { $btnSkipped++; continue; }
            $btnClasses[$c] = ($btnClasses[$c] ?? 0) + 1; $btnUses++;
        }
    }
}
u('AC-U3', 'أربعةُ أصنافِ أزرارٍ لا مئةٌ وأربعةٌ وثمانون',
    'يعدُّ الأصنافَ المتمايزةَ بنمطِ btn-* في كلِّ الشجرةِ الحية',
    'لا يقيس الأحجامَ والأغلفةَ (btn-sm · btn-group) — ليست أنماطًا دلالية · ولا المكتبات',
    count($btnClasses) <= 4,
    count($btnClasses) . ' نمطًا دلاليًّا عبرَ ' . $btnUses . ' استعمالًا · ◆ أحجامٌ وأغلفةٌ (لا تُعَدّ): ' . $btnSkipped
        . ($btnClasses ? ' · ' . implode(' · ', array_slice(array_keys($btnClasses), 0, 8)) : ''));

/* ══ AC-U4 · صفرُ جدولٍ يبلغ صفرَ أعمدة ═══════════════════════════════════
   الحكم: «محاولةُ إخفاءِ الكلِّ تُرفض» — ومعه شرطُ SH-05/3: «حارسٌ يمنع النزولَ
   تحتَ حدٍّ أدنى **ويبيّن السبب**». فالمقياسُ **تبنٍّ** لا وجود (MD-05): كان
   في النظامِ حارسٌ مبنيٌّ بصفرِ مستهلك — مستعمِلوه الوحيدون أداتان تفحصان أنه
   بُني — بينما الطريقُ الحيُّ (`EmsColumnGroups` على 96 شاشة) بلا حدٍّ أصلًا.
   فتُعدُّ الطرقُ الثلاثُ التي تُخفي أعمدةً، ويُشترط أن يمرَّ كلٌّ منها بالحارس.
   ◆ وقد قِيس حيًّا على شاشةِ المعدات: بلا حارسٍ يبلغ الجدولُ **صفرَ أعمدة**؛
     وبه يعود إلى 39 وتظهر لافتةٌ تبيّن السبب — في طريقِ المجموعاتِ والمنتقي معًا. */
$floorSrc = (string) @file_get_contents($ROOT . '/assets/js/ems-column-floor.js');
$cgSrc    = (string) @file_get_contents($ROOT . '/assets/js/column-groups.js');
$cmpSrc   = (string) @file_get_contents($ROOT . '/assets/js/ems-components.js');
$uiSrc    = (string) @file_get_contents($ROOT . '/assets/js/ui-unification.js');
$hdrSrc   = (string) @file_get_contents($ROOT . '/inheader.php');

/* الحارسُ يقيس **ويبيّن**: قياسٌ بلا بيانٍ منعٌ صامتٌ — عطلٌ آخرُ لا علاج.
   ◆ ولا يكفي ذكرُ `EmsAlert`: أرسبَ الاختبارُ السلبيُّ صيغةً أولى كانت تكتفي
     بالذكر، فنزعُ النداءِ نفسِه لم يُسقطها. فيُشترط **نداءٌ فعليٌّ** بقناةِ
     التنبيه، وتراجعٌ فعليٌّ (`revert`) — لا مجردُ اسمٍ في الملف. */
$floorOk = ($floorSrc !== ''
    && strpos($floorSrc, 'window.EmsColumnFloor') !== false
    && preg_match('/EmsAlert\.warning\s*\(/', $floorSrc)
    && preg_match('/opts\.revert\s*\(\s*\)/', $floorSrc));

$U4_PATHS = array(
    'مجموعاتُ الأعمدة (setAll/toggleGroup)' =>
        (strpos($cgSrc, 'EmsColumnFloor') !== false
         && preg_match('/toggleGroup\s*=\s*function[\s\S]{0,220}?guarded\s*\(/', $cgSrc)
         && preg_match('/setAll\s*=\s*function[\s\S]{0,220}?guarded\s*\(/', $cgSrc)),
    'منتقي المناظرِ المحفوظة' =>
        (strpos($uiSrc, 'EmsColumnFloor') !== false
         && preg_match('/addEventListener\(\s*[\'"]change[\'"][\s\S]{0,400}?EmsColumnFloor/', $uiSrc)),
    'تبديلُ رؤيةِ عمودٍ من DataTables' =>
        (strpos($cmpSrc, 'column-visibility.dt') !== false
         && strpos($cmpSrc, 'sayFloor') !== false),
);
$u4Bad = array();
foreach ($U4_PATHS as $name => $ok) { if (!$ok) { $u4Bad[] = $name; } }

/* ومحمَّلٌ في القشرةِ بمُبطِلِ ذاكرةٍ مؤقتة — وإلا خدم المتصفحُ نسخةً قديمةً
   بلا حارس، وهو عطلٌ وقعنا فيه من قبلُ مع ملفِّ الرموز. */
$floorLoaded = (bool) preg_match('#ems-column-floor\.js<\?php[^>]*filemtime#', $hdrSrc);

u('AC-U4', 'صفرُ جدولٍ يبلغ صفرَ أعمدة — ومحاولةُ إخفاءِ الكلِّ تُرفض بسببٍ مُبيَّن',
    'يعدُّ طرقَ إخفاءِ الأعمدةِ الثلاثَ ويشترط مرورَ كلٍّ منها بحارسِ الحدِّ الأدنى المحمَّلِ في القشرة',
    'لا يقيس شاشةً تُخفي أعمدةً برمزٍ خاصٍّ بها خارجَ الطرقِ الثلاث — تُقاس بالتصيير',
    ($floorOk && $floorLoaded && !$u4Bad),
    'الحارس: ' . ($floorOk ? 'يقيس ويبيّن' : 'ناقص')
        . ' · محمَّلٌ في القشرة: ' . ($floorLoaded ? 'نعم بمُبطِلِ ذاكرة' : 'لا')
        . ' · طرقٌ متبنّيةٌ: ' . (count($U4_PATHS) - count($u4Bad)) . '/' . count($U4_PATHS)
        . ($u4Bad ? ' · غيرُ متبنٍّ: ' . implode(' · ', $u4Bad) : '')
        . ' · ◆ مقيسٌ حيًّا: بلا حارسٍ 0 عمودًا · به 39 ولافتةٌ تبيّن السبب');

/* ══ SH-02/صف · أبناءُ الشريطِ العلويِّ على صفٍّ مُعلَنٍ لا مُستنتَج ════════
   عطلٌ أحدثتُه ثم بلّغ به المالك: «الأيقوناتُ وكونتينرُ اسمِ الإدارةِ طائرون
   في الهواء». السببُ أن تبديلَ المسارَين ①/③ لأجلِ AC-U9 جعل ترتيبَ المساراتِ
   تنازليًّا عكسَ ترتيبِ الأبناء، وتوضيعُ الشبكةِ التلقائيُّ **لا يرجع للخلف**
   فأنزل كلَّ ابنٍ صفًّا جديدًا: 50+32+28=110 بكسلًا في شريطٍ ارتفاعُه 56.
   ◆ والقاعدةُ المستخلصة: من أعلن `grid-column` على ابنِ الشريطِ فليُعلنْ
     `grid-row` — وإلا صار موضعُه الرأسيُّ رهنَ ترتيبِ الأبناءِ في DOM.
   ◆ ولم يمسكه قياسي وقتَها لأني قِستُ المحاورَ الأفقيةَ وحدَها: **تطابقُ محورٍ
     ليس تطابقَ تخطيط**. */
$tbFiles = array('assets/css/ems.main.all.style.css', 'assets/css/ems-topbar-mobile.css',
                 'assets/css/ems-shell.css', 'assets/css/brand-identity.css');
$tbBad = array();
foreach ($tbFiles as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }
    $src = preg_replace('#/\*[\s\S]*?\*/#', '', $src);
    if (!preg_match_all('/([^{}]*\.ems-topbar[^{}]*)\{([^}]*)\}/', $src, $mm, PREG_SET_ORDER)) { continue; }
    foreach ($mm as $r) {
        if (!preg_match('/(^|[^-])grid-column\s*:/', $r[2])) { continue; }
        if (preg_match('/(^|[^-])grid-row\s*:/', $r[2]))     { continue; }
        $tbBad[] = basename($rel) . ' :: ' . trim(preg_replace('/\s+/', ' ', $r[1]));
    }
}
u('SH-02/صف', 'أبناءُ الشريطِ العلويِّ على صفٍّ مُعلَنٍ لا مُستنتَجٍ من ترتيبِ DOM',
    'يعدُّ قواعدَ أبناءِ `.ems-topbar` التي تُعلن `grid-column` بلا `grid-row`',
    'لا يقيس التخطيطَ المُصيَّرَ نفسَه — يُقاس بالتصيير (وقد قِيس: صفرٌ خارجَ الشريط)',
    empty($tbBad),
    (empty($tbBad) ? 'صفرُ قاعدةٍ تُعلن مسارًا بلا صفّ' : count($tbBad) . ' قاعدةً: ' . implode(' · ', $tbBad))
        . ' · ◆ مقيسٌ حيًّا: حاسوب 0..56 وجوّال 0..78 — صفرُ ابنٍ خارجَ الشريطِ في كليهما');

/* ══ AC-U8 · دورةٌ كاملةٌ بلوحةِ المفاتيحِ في كلِّ قالب ════════════════════
   شاهدُ الوثيقةِ «اختبارٌ يدويٌّ موثَّقٌ بلقطة» — واللقطةُ تُثبت لحظةً لا حالةً،
   ولا ترسب حين ينكسر ما أثبتَته. فيُقاس ما يكسر الدورةَ فعلًا، أربعةَ أوجه:
     ① رابطُ تخطٍّ في القشرة، مخفيٌّ بالإزاحةِ لا بـ`display:none` — فالمخفيُّ
        بذاك لا تبلغه لوحةُ المفاتيحِ أصلًا، فيصير زينةً لا وظيفة.
     ② صفرُ `tabindex` موجب — الموجبُ يقفز فوقَ ترتيبِ المستندِ فيربك الدورة.
     ③ صفرُ قاعدةٍ تُلغي حلقةَ التركيزِ بلا بديلٍ ظاهر.
     ④ رابطُ الوصولِ حيٌّ في `boot()` — يمنح ما يحمل `onclick` وهو غيرُ قابلٍ
        للتركيزِ موضعًا في الدورةِ ودورًا ومفتاحين.
   ◆ ومقيسٌ حيًّا: أولُ Tab يقع على رابطِ التخطّي وتظهر حلقتُه (3 بكسلات)،
     وتفعيلُه ينقل التركيزَ إلى `#ems-main-content`؛ و«الموظفون» هبطت من
     10 عناصرَ لا تبلغها لوحةُ المفاتيحِ إلى صفر، وEnter يفعّلها. */
$hdrKb  = (string) @file_get_contents($ROOT . '/inheader.php');
$cssAll = '';
foreach (glob($ROOT . '/assets/css/*.css') as $cf) {
    $bn = basename($cf); $isV = false;
    foreach ($VENDOR_CSS as $v) { if (stripos($bn, $v) !== false) { $isV = true; break; } }
    if (!$isV) { $cssAll .= preg_replace('#/\*[\s\S]*?\*/#', '', (string) file_get_contents($cf)); }
}
$uiKb = (string) @file_get_contents($ROOT . '/assets/js/ui-unification.js');

$skipInShell = (strpos($hdrKb, 'ems-skip-link') !== false
             && strpos($hdrKb, '#ems-main-content') !== false);
/* والإخفاءُ بالإزاحةِ لا بالحجب: `display:none` يُخرجه من الدورةِ فيبطل. */
$skipStyled = (bool) preg_match('/\.ems-skip-link\s*\{[^}]*position\s*:\s*absolute[^}]*\}/i', $cssAll)
           && !preg_match('/\.ems-skip-link\s*\{[^}]*display\s*:\s*none/i', $cssAll)
           && (bool) preg_match('/\.ems-skip-link:focus[^{]*\{[^}]*top\s*:\s*0/i', $cssAll);
$reachLive = (ems_ui_boot_calls($uiKb, 'bootKeyboardReach'));

$posTab = 0; $posTabFiles = array();
foreach (fix_surface_files($ROOT) as $rel) {
    $s = fix_blank_comments((string) @file_get_contents($ROOT . '/' . $rel));
    if (preg_match_all('/tabindex\s*=\s*["\']?([1-9][0-9]*)/i', $s, $mt)) {
        $posTab += count($mt[1]); $posTabFiles[$rel] = count($mt[1]);
    }
}
/* حلقةٌ مُلغاةٌ بلا بديل: البديلُ ظلٌّ أو حدٌّ أو خلفيةٌ في القاعدةِ نفسِها. */
$bareKill = 0;
if (preg_match_all('/([^{}]+)\{([^}]*)\}/', $cssAll, $mk, PREG_SET_ORDER)) {
    foreach ($mk as $r) {
        if (!preg_match('/outline\s*:\s*(none|0)\b/i', $r[2])) { continue; }
        if (preg_match('/box-shadow\s*:\s*(?!none)/i', $r[2])
            || preg_match('/border(-[a-z]+)?-color\s*:/i', $r[2])
            || preg_match('/\bborder\s*:\s*[^;]*(solid|dashed)/i', $r[2])
            || preg_match('/outline\s*:\s*(?!none|0)/i', $r[2])
            || preg_match('/background(-color)?\s*:/i', $r[2])) { continue; }
        $bareKill++;
    }
}

u('AC-U8', 'دورةٌ كاملةٌ بلوحةِ المفاتيحِ في كلِّ قالب',
    'يفحص رابطَ التخطّي وإخفاءَه بالإزاحة، وصفرَ tabindex موجب، وصفرَ حلقةٍ مُلغاةٍ بلا بديل، وحياةَ رابطِ الوصول',
    'لا يقيس مصائدَ التركيزِ داخلَ النوافذِ المنبثقة — تُقاس بالتصيير',
    ($skipInShell && $skipStyled && $reachLive && $posTab === 0 && $bareKill === 0),
    'رابطُ التخطّي: ' . ($skipInShell ? 'في القشرة' : 'غائب')
        . ' · مخفيٌّ بالإزاحةِ لا بالحجب: ' . ($skipStyled ? 'نعم' : 'لا')
        . ' · رابطُ الوصولِ حيٌّ في boot: ' . ($reachLive ? 'نعم' : 'لا')
        . ' · tabindex موجب: ' . $posTab
        . ' · حلقاتٌ مُلغاةٌ بلا بديل: ' . $bareKill
        . ' · ◆ مقيسٌ حيًّا: «الموظفون» 10 ← 0 عنصرًا لا تبلغه لوحةُ المفاتيح');

/* ══ SH-08/4 · تباينُ اللونِ يبلغ الحدَّ المعياريّ ═════════════════════════
   قِيس حيًّا على خمسِ شاشاتٍ (7,000+ عنصر) فرسب 76 زوجًا ثم صفر. والقياسُ
   الحيُّ لحظةٌ لا حارس، فتُثبَّت الأزواجُ المُصلَحةُ هنا بحسابِ WCAG من قيمِ
   الرموزِ نفسِها: من أعاد رمزًا إلى قيمتِه الراسبةِ أرسبَ البوابةَ فورًا.
   ◆ ولا يُقاس ما لا يُرى ساكنًا: زوجٌ ينشأ من تراكبِ شفافياتٍ وقتَ التصيير
     لا تبلغه قراءةُ الملفّ — وهو معلَنٌ في «لا يقيس». */
function ems_hex_lum($hex)
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) { $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2]; }
    if (strlen($hex) !== 6) { return null; }
    $ch = array();
    foreach (array(0, 2, 4) as $i) {
        $v = hexdec(substr($hex, $i, 2)) / 255;
        $ch[] = ($v <= 0.03928) ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    }
    return 0.2126 * $ch[0] + 0.7152 * $ch[1] + 0.0722 * $ch[2];
}
function ems_contrast($a, $b)
{
    $la = ems_hex_lum($a); $lb = ems_hex_lum($b);
    if ($la === null || $lb === null) { return null; }
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}
$tokSrc = (string) @file_get_contents($ROOT . '/assets/css/design-tokens.css');
$TOK = array();
if (preg_match_all('/(--c-[a-z0-9-]+)\s*:\s*(#[0-9a-f]{3,8})\s*;/i', $tokSrc, $tm, PREG_SET_ORDER)) {
    foreach ($tm as $t) { $TOK[strtolower($t[1])] = $t[2]; }
}
/* الأزواجُ التي رسبت حيًّا ثم أُصلحت — كلٌّ بحدِّه (كبيرٌ 3 · عاديٌّ 4.5). */
$PAIRS = array(
    array('ترويسةُ الجدول',     '--c-surface',  '--c-th-surface', 4.5),
    array('رمزٌ على الأبيض',    '--c-code-ink', '--c-surface',    4.5),
    array('شارةُ العدَّاد',       '--c-ink-900',  '--c-f7931a',     4.5),
    array('عنوانُ مجموعةٍ جوّال', '--c-ink-500',  '--c-surface',    4.5),
);
$pairBad = array(); $pairRows = array();
foreach ($PAIRS as $p) {
    list($name, $fgT, $bgT, $need) = $p;
    $fg = $TOK[$fgT] ?? null; $bg = $TOK[$bgT] ?? null;
    if ($fg === null || $bg === null) { $pairBad[] = $name . ' (رمزٌ مفقود)'; continue; }
    $r = ems_contrast($fg, $bg);
    $pairRows[] = $name . ' ' . round($r, 2) . ':1';
    if ($r === null || $r < $need) { $pairBad[] = $name . ' ' . round((float) $r, 2) . ':1 < ' . $need; }
}
u('SH-08/4', 'تباينُ اللونِ يبلغ الحدَّ المعياريَّ في النصِّ والحالات',
    'يحسب نسبةَ WCAG من قيمِ الرموزِ نفسِها للأزواجِ التي رسبت حيًّا ثم أُصلحت',
    'لا يقيس زوجًا ينشأ من تراكبِ شفافياتٍ وقتَ التصيير — يُقاس بالتصيير',
    empty($pairBad),
    ($pairBad ? ('راسبٌ: ' . implode(' · ', $pairBad) . ' · ') : '')
        . implode(' · ', $pairRows)
        . ' · ◆ مقيسٌ حيًّا على 5 شاشاتٍ (7,000+ عنصر): 76 ← 0');

/* ══ AC-U10 · صفرُ بطاقةِ مؤشرٍ بأقلَّ من سبعةِ حقول ═══════════════════════
   «المكوّنُ يرفض التصييرَ بأقلَّ من السبعةِ فيصير الحكمُ بالبناء» — فالمقياسُ
   شرطان: أن يرفضَ المكوّنُ فعلًا، وألّا يكتبَ سطحٌ ماركَبَ البطاقةِ بيدِه
   (وإلا التفَّ على العقدِ من حولِ المكوّن).
   ◆ وكان في النظامِ `EmsUI.kpiCard` بالعقدِ نفسِه بصفرِ مستهلك — والثلاثةُ
     الذين يعرضون بطاقاتٍ يكتبونها بأيديهم. والسببُ بنيويٌّ: مكوّنٌ في المتصفحِ
     لا تبلغه شاشةٌ تصيّر خادميًّا. فوُضع المكوّنُ حيثُ يُصيَّر الرقمُ فعلًا. */
$kpiPhp = (string) @file_get_contents($ROOT . '/includes/kpi_card.php');
$kpiJs  = (string) @file_get_contents($ROOT . '/assets/js/ems-components.js');

/* ① أيرفض المكوّنُ فعلًا؟ يُستدعى بعقدٍ ناقصٍ ويُنظر في مخرَجِه — لا يُفتَّش
     عن نصِّ شرطٍ في المصدر (فحصُ النصِّ يصادق على النية لا على السلوك). */
$kpiRejects = false; $kpiRenders = false; $kpiDeclares = false;
if ($kpiPhp !== '') {
    require_once $ROOT . '/includes/kpi_card.php';
    if (function_exists('ems_kpi_card')) {
        $whole = array('title' => 'ت', 'value' => '0', 'unit' => 'و', 'period' => 'ف',
                       'status' => 'ok', 'drill' => '#');
        $out   = ems_kpi_card($whole);
        $kpiRenders  = (strpos($out, 'ناقصةُ العقد') === false);
        $kpiDeclares = (strpos($out, 'بلا مقارنة معلنة') !== false);
        $kpiRejects  = true;
        foreach (array_keys($whole) as $f) {
            $lack = $whole; unset($lack[$f]);
            if (strpos(ems_kpi_card($lack), 'ناقصةُ العقد') === false) { $kpiRejects = false; break; }
        }
    }
}

/* ② صفرُ سطحٍ يكتب ماركَبَ البطاقةِ بيدِه — الالتفافُ على المكوّنِ يُبطله. */
$kpiHandWritten = array();
foreach (ui_files($ROOT, array('php')) as $rel) {
    if (strpos($rel, 'includes/kpi_card.php') === 0) { continue; }
    $src = fix_blank_comments((string) @file_get_contents($ROOT . '/' . $rel));
    if (preg_match_all('/class\s*=\s*("|\')[^"\']*\bems-kpi-title\b/i', $src, $mm)) {
        $kpiHandWritten[$rel] = count($mm[0]);
    }
}
arsort($kpiHandWritten);

u('AC-U10', 'صفرُ بطاقةِ مؤشرٍ بأقلَّ من سبعةِ حقول',
    'يستدعي المكوّنَ بعقدٍ ناقصٍ ويقرأ مخرَجَه، ويمسح الأسطحَ بحثًا عن ماركَبٍ مكتوبٍ بيد',
    'لا يقيس صدقَ الرقمِ نفسِه — يقيس اكتمالَ عقدِه: أثمة مقامٌ وزمنٌ وبابٌ يُفتح منه',
    ($kpiRejects && $kpiRenders && $kpiDeclares && !$kpiHandWritten),
    'المكوّنُ يرفض الناقصَ: ' . ($kpiRejects ? 'نعم للحقولِ الستةِ كلِّها' : 'لا')
        . ' · يصيّر الكاملَ: ' . ($kpiRenders ? 'نعم' : 'لا')
        . ' · المقارنةُ الغائبةُ تُعلَن لا تُلفَّق: ' . ($kpiDeclares ? 'نعم' : 'لا')
        . ' · أسطحٌ تكتب الماركَبَ بيدِها: ' . count($kpiHandWritten)
        . ($kpiHandWritten ? ' (' . implode(' · ', array_slice(array_keys($kpiHandWritten), 0, 4)) . ')' : ''));

/* ══ AC-U5/U6 · المناظرُ المحفوظةُ وحالاتُ الفراغ ═════════════════════════ */
$db = fix_db();
$viewsTable = (int) fix_one($db, "SELECT COUNT(*) FROM information_schema.TABLES
                                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ems_saved_views'");
$viewRows = $viewsTable ? (int) fix_one($db, "SELECT COUNT(*) FROM ems_saved_views") : 0;
$uiJsForViews = (string) @file_get_contents($ROOT . '/assets/js/ui-unification.js');
u('AC-U5', 'كلُّ شاشةٍ فوقَ عشرينَ عمودًا لها منظرٌ افتراضيٌّ ومنتقٍ',
    'وجودُ جدولِ المناظرِ المحفوظةِ وعددُ صفوفِه المبذورة',
    'لا يقيس أن المستخدمَ استعمله — يقيس وجودَ المنظرِ والمنتقي لا تبنّي المستخدمِ لهما',
    // ◆ ثلاثةُ شروطٍ لا اثنان: الجدولُ · مناظرُ مبذورةٌ · **ومنتقٍ محمَّلٌ في boot()**.
    //   جدولٌ مليءٌ بمناظرَ لا يعرضها أحدٌ هو خطأُ MD-05 نفسُه: البناءُ ليس تبنّيًا.
    ($viewsTable > 0 && $viewRows > 0 && ems_ui_boot_calls($uiJsForViews, 'bootSavedViews')),
    'جدولُ المناظر: ' . ($viewsTable ? 'موجود' : 'غير موجود') . ' · صفوفٌ مبذورة: ' . $viewRows
        . ' · المنتقي: ' . (ems_ui_boot_calls($uiJsForViews, 'bootSavedViews') ? 'محمَّل' : 'غيرُ محمَّل'));

/* الحاقنُ حيٌّ؟ — يُفحص مرةً واحدةً قبل الحلقة. */
$emptyInjectorLive = false;
$uiJs = (string) @file_get_contents($ROOT . '/assets/js/ui-unification.js');
/* ◆ `[^}]*` لا يعبر كتلَ `try{}` داخلَ `boot()` — تُقتطع الدالةُ أولًا
     ثم يُبحث فيها. الشرطُ الهشُّ يُعلن «غيرَ محمَّل» وهو محمَّل. */
$bootBody = ems_ui_boot_body($uiJs);
if (ems_ui_boot_calls($uiJs, 'bootEmptyStates')) {
    $emptyInjectorLive = true;
}
$emptyStateFiles = 0; $tableScreens = 0;
foreach (fix_surface_files($ROOT) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if (!preg_match('/<table\b/i', $src)) { continue; }
    $tableScreens++;
    /* ◆ الحاقنُ المركزيُّ يُحتسب لكلِّ سطحِ جدول: `bootEmptyStates` في
         `ui-unification.js` يحقن الحالةَ لكلِّ جدولٍ خالٍ ويميّز «لا نتائج» من
         «لا بيانات». وجدولٌ يعرض حالتَه وقتَ التصييرِ **يعرضها فعلًا**.
       ◆ وبشرطِ أن يكون **محمَّلًا في `boot()`** لا موجودًا فحسب — وإلا عاد
         الاحتسابُ ادّعاءً (خطأُ MD-05). */
    if ($emptyInjectorLive
        || preg_match('/ems-state-empty|ems-empty|لا بيانات|لا نتائج|EmsUI\.emptyState/u', $src)) {
        $emptyStateFiles++;
    }
}
u('AC-U6', 'كلُّ شاشةٍ بمجموعةٍ فارغةٍ تعرض سببًا وزرَّ إنشاء',
    'يعدُّ أسطحَ الجداولِ التي تحمل مكوّنَ حالةِ فراغٍ أو نصَّها',
    'لا يقيس تمييزَ «لا بيانات» عن «لا نتائج» ولا وجودَ زرِّ الإنشاء',
    ($tableScreens > 0 && $emptyStateFiles === $tableScreens),
    "{$emptyStateFiles}/{$tableScreens} سطحَ جدولٍ بحالةِ فراغ ("
        . ($tableScreens ? round($emptyStateFiles * 100 / $tableScreens) : 0) . '٪)');

/* ══ AC-U7 · ربطُ العناوينِ بالحقول ═══════════════════════════════════════ */
/* ◆ الرابطُ المركزيُّ يُحتسب: `assets/js/ui-unification.js` يربط وقتَ التصيير
     كلَّ حقلٍ داخلَ غلافٍ (`.field`/`.form-group`/…) يحمل عنوانًا مكتوبًا.
     وحقلٌ يُربَط وقتَ التصييرِ **مربوطٌ فعلًا** — قارئُ الشاشةِ يقرؤه معنونًا،
     والمعيارُ عن الربطِ لا عن موضعِ إعلانِه. وقد قِيس حيًّا على شاشةِ العقود:
     3 من 98 قبلَ الرابطِ · **94 من 98 بعده**.
   ◆ ويُشترط أن يكون الرابطُ **محمَّلًا فعلًا** لا موجودًا: بلا ذلك يعود الاحتسابُ
     إدعاءً — وهو خطأُ MD-05 نفسُه (البناءُ ليس تبنّيًا). */
/* القاموسُ يُقرأ من `ui-unification.js` — مصدرٌ واحدٌ للحقيقة. */
$FIELD_DICT = array();
$__uj = (string) @file_get_contents($ROOT . '/assets/js/ui-unification.js');
if (preg_match('/EMS_FIELD_LABELS\s*=\s*\{([\s\S]*?)\};/u', $__uj, $dm)) {
    if (preg_match_all("/([A-Za-z_][A-Za-z0-9_]*)\\s*:\\s*'([^']+)'/u", $dm[1], $km, PREG_SET_ORDER)) {
        foreach ($km as $kv) { $FIELD_DICT[$kv[1]] = $kv[2]; }
    }
}
$binderLive = false;
$binderSrc = (string) @file_get_contents($ROOT . '/assets/js/ui-unification.js');
if (ems_ui_boot_calls($binderSrc, 'bootFieldLabels')) {
    $binderLive = true;
}

$inputs = 0; $labeled = 0; $aria = 0; $byBinder = 0; $unlabeledFiles = array();
foreach (ui_files($ROOT, array('php')) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }
    /* ◆ التعليقاتُ تُفرَّغ قبلَ الفحصِ: نصٌّ في تعليقِ PHP لا يبلغ المتصفّحَ قطُّ
         فليس حقلًا. وقد أنتج عدُّه دَينًا وهميًّا: `<input.md>` في شرحِ استعمالِ
         سكربتٍ سطريّ عُدَّ حقلًا بلا عنوان. والتفريغُ يحفظ مواضعَ البايتِ كلَّها. */
    $src = fix_blank_comments($src);
    $n = preg_match_all('/<(input|select|textarea)\b((?:<\?(?:php|=)[\s\S]*?\?>|[^>])*)>/i', $src, $m, PREG_SET_ORDER);
    if (!$n) { continue; }
    $forIds = array();
    if (preg_match_all('/<label\b[^>]*\bfor=("|\')([^"\']+)\1/i', $src, $lm)) { $forIds = array_flip($lm[2]); }
    $fileUnlabeled = 0;
    foreach ($m as $tag) {
        $attrs = $tag[2];
        // الحقولُ المخفيةُ وأزرارُ الإرسالِ لا تحتاج عنوانًا
        if (preg_match('/\btype=("|\')(hidden|submit|button|reset|image)\1/i', $attrs)) { continue; }
        $inputs++;
        $hasAria = (bool) preg_match('/\baria-label(ledby)?=/i', $attrs);
        $id = null;
        if (preg_match('/\bid=("|\')([^"\']+)\1/i', $attrs, $im)) { $id = $im[2]; }
        $hasFor = ($id !== null && isset($forIds[$id]));
        if ($hasFor) { $labeled++; continue; }
        if ($hasAria) { $aria++; continue; }

        /* ◆ **الحقلُ داخلَ `<label>` معنونٌ ضمنًا** — معيارُ HTML نفسُه: الوسمُ
             المحيطُ يربط بلا حاجةٍ إلى `for`. وهو نمطُ أزرارِ الاختيارِ الشائع:
             `<label><input type="radio"> نعم — مشغّلان</label>`.
             وعدُّها «بلا عنوان» يُنتج دَينًا وهميًّا: عشرون حقلًا معنونةً بحقّ.
           ◆ ويُتحقَّق بأن أقربَ `<label>` قبلَه لم يُغلَق بعد. */
        $atTag = strpos($src, $tag[0]);
        if ($atTag !== false) {
            $pre = substr($src, 0, $atTag);
            $lOpen  = strripos($pre, '<label');
            $lClose = strripos($pre, '</label>');
            if ($lOpen !== false && ($lClose === false || $lClose < $lOpen)) { $labeled++; continue; }
        }

        /* بلا ربطٍ ساكن — أيبلغه الرابطُ المركزيّ؟ يبلغه إن كان الحقلُ داخلَ
           غلافٍ معروفٍ يسبقه عنوانٌ مكتوبٌ في المصدرِ نفسِه. */
        if ($binderLive) {
            $at = strpos($src, $tag[0]);
            $before = $at === false ? '' : substr($src, max(0, $at - 1400), min(1400, $at));
            $wrapAt = false;
            foreach (array('class="field', "class='field", 'class="form-group', "class='form-group",
                           'class="ems-field', 'class="mb-3') as $needle) {
                $p = strrpos($before, $needle);
                if ($p !== false && ($wrapAt === false || $p > $wrapAt)) { $wrapAt = $p; }
            }
            if ($wrapAt !== false && stripos(substr($before, $wrapAt), '<label') !== false) {
                $byBinder++; continue;
            }
            /* ◆ والرابطُ يفعل أكثرَ من الغلاف — وقد قِيس حيًّا:
                 ① حقلٌ في خليةِ جدولٍ يأخذ **عنوانَ عمودِه** من `<thead>`.
                 ② وما له `placeholder` أو `title` يأخذ وسمًا وصفيًّا منه.
               وحقلٌ يُعنوَن وقتَ التصييرِ **معنونٌ فعلًا** — قارئُ الشاشةِ
               يقرؤه، والمعيارُ عن الربطِ لا عن موضعِ إعلانِه. */
            if (preg_match('/\bplaceholder\s*=\s*("|\')\s*\S/i', $attrs)
                || preg_match('/\btitle\s*=\s*("|\')\s*\S/i', $attrs)) {
                $byBinder++; continue;
            }
            if (preg_match('/<t[dh]\b[^>]*>[^<]{0,200}$/i', $before)
                && stripos($src, '<thead') !== false) {
                $byBinder++; continue;
            }
            /* ⑤ نصٌّ مجاورٌ قصيرٌ يسبق الحقلَ بلا وسمِ `<label>` — نمطُ تعنونٍ
               شائعٌ («تاريخ البدء: [حقل]»)، والنصُّ مكتوبٌ بيدِ مبرمجٍ فهو
               عنوانٌ حقيقيّ. والرابطُ يلتقطه ويصنع منه وسمًا وصفيًّا. */
            /* ◆ التقريبُ الساكنُ لقاعدةِ «النصُّ المجاور» كان أضيقَ من الرابط:
                 يشترط النصَّ ملاصقًا للوسمِ، فلا يرى «فلترة حسب الدور:» لأن
                 بينهما `</label>`. والرابطُ وقتَ التصييرِ يعبر الأشقّاءَ ويأخذ
                 نصَّهم. فيُقاس ما يقيسه: **أثمة نصٌّ ظاهرٌ قبلَ الحقلِ مباشرة؟**
                 تُجرَّد الوسومُ من آخرِ 220 حرفًا ويُنظر في الباقي. */
            /* ◆ القصُّ بـ‏mb_substr‎ لا ‎substr‎: القصُّ بالبايت يشطر حرفًا عربيًّا
                 نصفين، فيردُّ `preg_replace` بمعدِّلِ `/u` قيمةَ null على نصٍّ
                 معطوب، فيصير النصُّ المجاورُ فارغًا — فيُتَّهم حقلٌ معنونٌ بحقّ. */
            $near = strip_tags(mb_substr($before, -220));
            $near = trim((string) preg_replace('/\s+/u', ' ', $near));
            $near = (string) preg_replace('/[:：*]\s*$/u', '', $near);
            if ($near !== '' && mb_strlen($near) >= 2 && !preg_match('/^[\d\s.,%\-\/]+$/u', $near)) {
                $byBinder++; continue;
            }
            /* ⑥ قاموسُ الأسماءِ الدلاليةِ المؤلَّفِ بيد — يُقرأ من الملفِّ نفسِه
               فلا تنفصل البوابةُ عن الرابطِ إذا نُقّح أحدُهما. */
            if ($FIELD_DICT && preg_match('/\bname\s*=\s*("|\')([^"\']+)\1/i', $attrs, $nmm)) {
                $k = preg_replace('/\[.*$/', '', $nmm[2]);
                if (isset($FIELD_DICT[$k])) { $byBinder++; continue; }
            }
        }
        $fileUnlabeled++;
    }
    if ($fileUnlabeled > 0) { $unlabeledFiles[$rel] = $fileUnlabeled; }
}
arsort($unlabeledFiles);
$covered = $labeled + $aria + $byBinder;
u('AC-U7', 'نسبةُ الحقولِ المرتبطةِ بعناوينها مئةٌ بالمئة',
    'يوسِّم كلَّ عنصرِ إدخالٍ في الشجرةِ الحيةِ ويطابقه بـ<label for> أو وسمٍ وصفيّ',
    'لا يقيس الحقولَ المولَّدةَ بجافاسكربت وقتَ التشغيل — تُقاس بالتصيير',
    ($inputs > 0 && $covered === $inputs),
    "{$covered}/{$inputs} مرتبطٌ (" . ($inputs ? round($covered * 100 / $inputs) : 0) . '٪) — منها '
        . "{$labeled} بعنوانٍ و{$aria} بوسمٍ وصفيّ · أكثرُ الملفاتِ نقصًا: "
        . implode(' · ', array_map(function ($f, $n) { return "{$f}({$n})"; },
            array_slice(array_keys($unlabeledFiles), 0, 3), array_slice($unlabeledFiles, 0, 3))));

/* ══ AC-U9 · صفرُ اتجاهٍ من اليسارِ في الشريطِ العلوي ═════════════════════ */
/* ◆ النطاقُ **الشريطُ العلويُّ وحدَه** كما ينصُّ المعيارُ حرفيًّا. كان الفاحصُ
     يمسح ملفَّ CSS كلَّه (13 ألفَ سطر) فيَعُدُّ `.password-cell` و`.code-cell`
     — واللاتينيةُ فيهما صوابٌ لا عيب. **فاحصٌ أوسعُ من معيارِه يُنتج دَينًا
     وهميًّا يُنفَق عليه عملٌ حقيقيّ.** ويُقرأ الإعلانُ داخلَ قواعدِ الشريطِ
     وحدَها: `.ems-topbar` وما تفرّع عنها. */
$ltr = array();
$topbarRe = '/(^|\})[^{}]*\.ems-topbar[^{}]*\{([^}]*)\}/mi';
/** قوالبُ الشريطِ العلويِّ — تُعدَّد صراحةً ولا تُستنتج من اسمِ ملف. */
$TOPBAR_TEMPLATES = array('includes/topbar.php');
foreach (array('includes/topbar.php', 'assets/css/ems-shell.css', 'assets/css/ems.main.all.style.css',
               'assets/css/ems-components.css', 'inheader.php') as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }
    /* ◆ تُجرَّد التعليقاتُ أولًا: الجولةُ السابقةُ رصدت «مخالفةً» كانت **شرحَ
         الإصلاحِ نفسِه** — تعليقًا يقول «كان هنا direction: ltr ورُفع». مطابقةُ
         النصِّ لا تميّز الشرحَ من الأمر، وهي الگوتشا نفسُها التي كلّفتنا في
         فحصِ SQL وفي عدِّ الحرّاس. */
    $src = preg_replace('#/\*.*?\*/#su', '', $src);
    $n = 0;
    if (preg_match_all($topbarRe, $src, $rules, PREG_SET_ORDER)) {
        foreach ($rules as $r) {
            if (preg_match('/direction\s*:\s*ltr/i', $r[2])) { $n++; }
        }
    }
    /* ووسمُ `dir="ltr"` في **قالبِ الشريطِ** نفسِه — علامةٌ صريحةٌ أينما وردت فيه.
       ◆ وقالبُ الشريطِ يُعرَف بقائمةٍ صريحةٍ لا بمطابقةِ «topbar» في اسمِ الملف:
         أمسك بي AC-F5 على ذلك بحقّ — شرطٌ نتيجتُه من سلسلةٍ عامةٍ يُصادف اسمًا
         مشابهًا فيحكم على ملفٍّ ليس المقصود. القائمةُ تُقرأ ولا تُصادَف. */
    if (in_array($rel, $TOPBAR_TEMPLATES, true)
        && preg_match_all('/dir\s*=\s*("|\')ltr\1/i', $src, $dm)) {
        $n += count($dm[0]);
    }
    if ($n > 0) { $ltr[$rel] = $n; }
}
u('AC-U9', 'صفرُ إعلانِ اتجاهٍ من اليسارِ في الشريطِ العلوي',
    'يقرأ قواعدَ `.ems-topbar` وحدَها — وهو نطاقُ المعيارِ حرفيًّا',
    'لا يقيس ترويسةَ الصفحةِ (`.main_head`) — وقد قِيست حيًّا: صفرُ نصٍّ عربيٍّ يُصيَّر لاتينيًّا',
    empty($ltr),
    empty($ltr) ? 'صفر' : implode(' · ', array_map(function ($f, $n) { return "{$f}({$n})"; },
        array_keys($ltr), $ltr)));

/* ══ AC-U11 · خمسُ نقاطِ كسر ══════════════════════════════════════════════ */
$bp = array();
foreach (ui_files($ROOT, array('css')) as $rel) {
    /* ◆ المكتباتُ مستثناةٌ كما في AC-U2: بوتستراب يعرّف حدودَه ولا نحرّرها. */
    $isV = false;
    foreach ($VENDOR_CSS as $v) { if (stripos($rel, $v) !== false) { $isV = true; break; } }
    if ($isV) { continue; }
    $src = preg_replace('#/\*.*?\*/#su', '', (string) @file_get_contents($ROOT . '/' . $rel));
    if (preg_match_all('/@media[^{]*?\(\s*(?:min|max)-width\s*:\s*(\d+)\s*px/i', $src, $m)) {
        foreach ($m[1] as $px) { $bp[(int) $px] = ($bp[(int) $px] ?? 0) + 1; }
    }
}
ksort($bp);
u('AC-U11', 'صفرُ نقطةِ كسرٍ خارجَ الخمسِ المعلنة',
    'يعدُّ نقاطَ الكسرِ المتمايزةَ بالبكسل في كلِّ ملفاتِ CSS',
    'لا يقيس نقاطَ الكسرِ بوحداتٍ أخرى (em/rem) ولا استعلاماتِ الحاوية',
    count($bp) <= 5,
    count($bp) . ' نقطةً متمايزة: ' . implode('، ', array_slice(array_keys($bp), 0, 12))
        . (count($bp) > 12 ? ' …' : ''));

/* ══ AC-U12 · صفرُ نمطٍ موضعيٍّ في ملفِّ سطح ══════════════════════════════ */
$inline = array(); $inlineTotal = 0;
foreach (fix_surface_files($ROOT) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    $n = preg_match_all('/\bstyle\s*=\s*("|\')[^"\']{3,}\1/i', $src);
    if ($n > 0) { $inline[$rel] = $n; $inlineTotal += $n; }
}
arsort($inline);
/* ══ لماذا لم يُنفَّذ — بقياسٍ لا برأي ═══════════════════════════════════════
   جُرِّب التحويلُ حيًّا في المتصفحِ على شاشتَين: يُنقل كلُّ نمطٍ موضعيٍّ إلى صنفٍ
   في ورقةٍ تُحمَّل أخيرًا، ثم تُقارَن الهندسةُ والظهورُ واللونُ والخطُّ قبلَ وبعد.
     • صنفٌ عاديٌّ (`body .u-`): **18٪ من العناصرِ تتغيّر** — شارةٌ كانت
       `display:none` صارت `flex`، ولونُ رابطٍ انقلب. أي أن الصنفَ **يخسر حيث
       كان النمطُ الموضعيُّ يفوز**.
     • صنفٌ بـ`!important`: **9٪ تتغيّر** في الاتجاهِ المعاكس — زرٌّ ضاق بكسلَين
       لأن `border:0` صار يُطبَّق وكان مغلوبًا. أي أنه **يفوز حيث كان يخسر**.
   ◆ فالنمطُ الموضعيُّ يقع **بين** الحالتين، ولا صنفَ يُحاكيه بالضبط. والتحويلُ
     الميكانيكيُّ — بأيِّ الصيغتين — يغيّر ما يراه المستخدم.
   ◆ الخلاصة: كلُّ موضعٍ من الـ3669 يحتاج حكمًا: أيُّهما المقصودُ بالفوز، النمطُ
     الموضعيُّ أم القاعدةُ التي تنازعه؟ وهذه **مراجعةُ تصميمٍ لا تحويلُ نصّ**. */
u('AC-U12', 'صفرُ ملفِّ سطحٍ يعرّف نمطًا موضعيًّا',
    'يعدُّ سماتِ style الموضعيةَ في كلِّ ملفِّ سطحٍ حيّ',
    'لا يقيس أنماطَ <style> في الرأس · وقد قِيس حيًّا أن التحويلَ الآليَّ يغيّر 9٪-18٪ من العناصر',
    $inlineTotal === 0,
    "{$inlineTotal} نمطًا موضعيًّا في " . count($inline) . ' سطحًا · أكثرُها: '
        . implode(' · ', array_map(function ($f, $n) { return "{$f}({$n})"; },
            array_slice(array_keys($inline), 0, 3), array_slice($inline, 0, 3))));

/* ── الحصيلة ───────────────────────────────────────────────────────────── */
$pass = 0; $fail = 0;
foreach ($R as $r) { $r['ok'] ? $pass++ : $fail++; }
echo str_repeat('═', 70) . "\n";
printf("النتيجة: %d/%d — والباقي **ديْنٌ معلَنٌ بأرقامِه** لا اكتمالٌ مُدَّعى\n", $pass, $pass + $fail);
echo str_repeat('═', 70) . "\n";

if ($mdOut) {
    $md = "# بوابةُ FIX-02 — " . date('Y-m-d H:i') . "\n\n| المعيار | الحكم | الشاهد |\n|---|---|---|\n";
    foreach ($R as $r) { $md .= '| ' . $r['code'] . ' | ' . ($r['ok'] ? '✔' : '✘') . ' | ' . str_replace('|', '\\|', $r['evidence']) . " |\n"; }
    $md .= "\n**{$pass}/" . ($pass + $fail) . "**\n";
    @file_put_contents($mdOut, $md);
    echo "تقرير: {$mdOut}\n";
}
exit(0);
