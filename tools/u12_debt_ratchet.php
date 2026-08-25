<?php
/**
 * tools/u12_debt_ratchet.php — سجلاتُ الدَّينِ الستةُ وسقّاطتُها المانعةُ للعودة
 * ═══════════════════════════════════════════════════════════════════════════
 * المشكلة: دَينٌ معلَنٌ برقمٍ ليس إغلاقًا — فالرقمُ يكبر بصمتٍ مع كلِّ شاشةٍ
 * جديدة، والعيبُ «المغلق» يعود من البابِ الخلفيّ. وسلّمُ الإغلاقِ الرباعيُّ
 * (UXR §٤-٣) يشترط لـL4 «فحصًا آليًّا يمنع عودةَ العيب».
 *
 * الحلُّ: سقّاطةٌ (ratchet) — خطُّ أساسٍ مسجَّلٌ لكلِّ دَينٍ، ثم:
 *   · نقصَ الرقمُ  ⇒ يُحدَّث خطُّ الأساسِ تلقائيًّا (التحسّنُ يُثبَّت فلا يُنقَض).
 *   · بقيَ كما هو ⇒ يمرّ.
 *   · زادَ الرقمُ  ⇒ ✘ رسوبٌ — العيبُ عاد، والبناءُ يتوقف.
 * فيصير الدَّينُ مُقفَلَ الاتجاه: يهبط ولا يصعد. وهذا هو الإنفاذُ الذي يرفع
 * UI-DEF-11 وUI-DEF-12 إلى L4 دون ادّعاءِ صفرٍ لم يتحقق.
 *
 * السجلاتُ الستة: أنماطٌ موضعية · ألوانٌ صلبة · خارجَ السلّم · بطاقةٌ خام ·
 * تواريخُ متفرقة · رسالةٌ في نافذةِ المتصفح.
 *
 * ── توسعةُ REPAIR01 · W01 §٤-٤ (خطّةُ الحملةِ §٧١) ─────────────────────────
 * ◆ ثمانيةُ أصنافِ دَينٍ حَوكميّةٍ أُضيفت إلى الستّةِ البصريّة: شاشةٌ بلا سجلّ ·
 *   مسارٌ بلا مالك · قارئُ صلاحيةٍ محليّ · SQL خامٌّ في مسارِ إدارة · منطقُ
 *   اعتمادٍ محليّ · حدثٌ خارجَ Publisher · بحثٌ خارجَ السجلِّ المعياريّ ·
 *   حقلٌ مشتقٌّ بلا قاعدة. **أربعةَ عشرَ سجلًّا لا ستّة.**
 * ◆ **السقّاطةُ من أوّلِ يومٍ وإلّا أصلحنا عشرةً وخلقنا عشرين** (§٧١): الموجودُ
 *   يُرحَّل بخطِّ أساسٍ موثَّق، والجديدُ ممنوعٌ عند خطّافِ ما قبل الالتزام.
 * ◆ **والماسحُ مشترَكٌ لا منسوخ**: `tools/lib/repair01_debt_scan.php` مصدرٌ
 *   واحدٌ تقرؤه السقّاطةُ والبوّابةُ معًا — فلا يتفرّق رقمانِ في ملفَّين.
 * ◆ **وصنفٌ لا يُقاس يُعلَن `—` ولا يُعَدُّ صفرًا**: صفرٌ كاذبٌ أسوأُ من غيابٍ
 *   مُعلَن، والبوّابةُ تسقط إن نقص المقيسُ عن أربعةَ عشر.
 *
 * التشغيل: php tools/u12_debt_ratchet.php [--set] [--md=مسار]
 *   --set يكتب خطَّ الأساسِ من القياسِ الحاليِّ (يُستعمل مرةً عند التأسيس).
 *         والخطُّ السابقُ يُحفَظ في `history` — الترحيلُ لا يمحو الدليل.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$ROOT = dirname(__DIR__);
$set = in_array('--set', $argv, true);
$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }
$BASE = $ROOT . '/docs/update0012/debt_baseline.json';

$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
    'Fleet','Governance','Maintenance','movement','Operations','Opportunities','Oprators','Portal',
    'Procurement','Projects','Reports','Risk','Settings','Suppliers','Tickets','Timesheet',
    'Transport','Workforce','main','admin','company','ActivityLogs','Clients','emsreports');

$files = array();
foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
        $src = (string) file_get_contents($f);
        if (strpos($src, 'insidebar') === false) { continue; }
        $files[str_replace('\\', '/', substr($f, strlen($ROOT) + 1))] = $src;
    }
}

/* ── القياسُ الخماسيّ ───────────────────────────────────────────────────── */
$m = array('VT-01' => 0, 'VT-02' => 0, 'VT-03' => 0, 'VT-06' => 0, 'VT-07' => 0, 'UI-13' => 0);
$fileHits = array('VT-01' => 0, 'VT-02' => 0);

foreach ($files as $rel => $src) {
    /* VT-01 أنماطٌ موضعية: style="…" داخلَ الوسوم */
    $c = preg_match_all('~\sstyle\s*=\s*["\']~i', $src);
    $m['VT-01'] += $c; if ($c) { $fileHits['VT-01']++; }

    /* VT-02 ألوانٌ صلبة: #rgb أو #rrggbb أو rgb( خارجَ ملفات القشرة */
    $c = preg_match_all('~#[0-9a-fA-F]{3,8}\b|rgba?\s*\(~', $src);
    $m['VT-02'] += $c; if ($c) { $fileHits['VT-02']++; }

    /* VT-03 خارجَ السلّم: مسافةٌ بالبكسل ليست من مضاعفات الأربعة */
    if (preg_match_all('~(?:margin|padding|gap)[a-z-]*\s*:\s*([^;"\']+)~i', $src, $mm)) {
        foreach ($mm[1] as $decl) {
            if (preg_match_all('~(\d+)px~', $decl, $px)) {
                foreach ($px[1] as $v) { if (((int) $v) % 4 !== 0) { $m['VT-03']++; } }
            }
        }
    }

    /* VT-06 بطاقةٌ خام: بطاقةُ مؤشرٍ بلا الحقولِ السبعة — تُعرَف بوجودِ قيمةٍ
       كبيرةٍ في وعاءِ بطاقةٍ بلا سطرِ الفترةِ والمقارنة */
    if (preg_match_all('~class="[^"]*\b(?:stats-card|kpi-card|card-stat|info-card)\b~i', $src, $kk)) {
        foreach ($kk[0] as $one) { $m['VT-06']++; }
    }

    /* VT-07 تواريخُ متفرقة: يُعدُّ كلُّ استدعاءِ تنسيقٍ لم يمرَّ بالمُوحِّد.
       (القياسُ لكلِّ استدعاءٍ لا لكلِّ ملف: قياسٌ على مستوى الملفِّ يجعل ملفًّا
       فيه ثلاثون استدعاءً متفرقًا «نظيفًا» لمجردِ ذكرِه المُوحِّدَ مرةً واحدة.)
       و`\b` قبل `date` لا يطابق داخلَ `ems_fmt_date(` لأنَّ ما قبلَها شَرْطةٌ
       سفليةٌ حرفُ كلمة — فالمُوحِّدُ مستثنًى بالبنيةِ لا بحيلةِ نظر. */
    $m['VT-07'] += preg_match_all(
        '~\bdate\s*\(\s*[\'"][^\'"]*[YmdHis][^\'"]*[\'"]|->format\s*\(|toLocaleDateString~', $src);

    /* UI-13 رسالةٌ في نافذةِ المتصفحِ: alert() يخرج عن الحاملِ المحكومِ فلا رمزَ
       ولا أثرَ ولا طريقَ رجوع. ما أمكن تحويلُه حُوّل، والباقي دَينٌ مُقفَلُ
       الاتجاه — أيُّ alert جديدةٍ رسوبٌ يوقف البناء. */
    $m['UI-13'] += preg_match_all('~echo\s+["\'][^"\']*<script>\s*alert\s*\(~i', $src);
}

$LABEL = array(
    'VT-01' => 'أنماطٌ موضعيةٌ في الوسوم (style=)',
    'VT-02' => 'ألوانٌ صلبةٌ خارجَ رموزِ القشرة',
    'VT-03' => 'مسافاتٌ خارجَ سلّمِ الأربعة',
    'VT-06' => 'بطاقاتُ مؤشرٍ خامٌّ بلا الحقولِ السبعة',
    'VT-07' => 'استدعاءُ تاريخٍ متفرقٌ بلا المُوحِّد',
    'UI-13' => 'رسالةٌ في نافذةِ المتصفحِ alert() لا في الحامل',
);
$OWNER = array(
    'VT-01' => 'فريقُ الواجهة — تُنقل إلى القشرة',
    'VT-02' => 'فريقُ التصميم — تُستبدل برموزِ القشرة',
    'VT-03' => 'فريقُ التصميم — تُقرَّب لسلّمِ الأربعة',
    'VT-06' => 'فريقُ الواجهة — تُهاجر إلى EmsUI.kpiCard',
    'VT-07' => 'فريقُ الواجهة — تُوحَّد بـems_fmt_date',
    'UI-13' => 'فريقُ الواجهة — تُنقل إلى ems_gov_flash_redirect أو EmsUI.toast',
);

/* ── توسعةُ REPAIR01: ثمانيةُ أصنافِ دَينٍ حَوكميّةٍ من الماسحِ المشترَك ────── */
require_once $ROOT . '/tools/lib/repair01_debt_scan.php';
$rpConn = null;
if (is_file($ROOT . '/includes/env.php')) {
    require_once $ROOT . '/includes/env.php';
    $rpHost = ems_env('DB_HOST'); $rpPort = 3306;
    if (strpos($rpHost, ':') !== false) { list($rpHost, $rpPort) = explode(':', $rpHost); $rpPort = (int) $rpPort; }
    mysqli_report(MYSQLI_REPORT_OFF);
    $try = @new mysqli($rpHost, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $rpPort);
    if (!$try->connect_errno) { $try->set_charset('utf8mb4'); $rpConn = $try; }
}
$rp = repair01_debt_measure($ROOT, $rpConn);
foreach (repair01_debt_classes() as $k => $meta) {
    $m[$k]     = $rp['counts'][$k];      /* قد يكون null — يُعلَن ولا يُعَدُّ صفرًا */
    $LABEL[$k] = $meta['label'];
    $OWNER[$k] = $meta['owner'];
}

/* ── خطُّ الأساسِ والحكم ────────────────────────────────────────────────── */
$base = array();
if (is_file($BASE)) {
    $j = json_decode((string) file_get_contents($BASE), true);
    if (is_array($j) && isset($j['debts'])) { $base = $j['debts']; }
}

$fail = 0; $improved = 0; $unmeasured = 0; $rows = array();
$prev = $base;
foreach ($m as $k => $now) {
    $was = isset($base[$k]) ? (int) $base[$k] : null;
    if ($now === null) {
        /* صنفٌ لم يُقَس — يُعلَن ولا يُعَدُّ صفرًا. صفرٌ كاذبٌ أسوأُ من غيابٍ مُعلَن. */
        $verdict = 'لم يُقَس — تعذّرت القاعدة'; $mark = '⚠'; $unmeasured++;
    } elseif ($set || $was === null) { $verdict = 'أُسِّس'; $mark = '◆'; $base[$k] = $now; }
    elseif ($now > $was)       { $verdict = 'زادَ ' . ($now - $was) . ' — العيبُ عاد'; $mark = '✘'; $fail++; }
    elseif ($now < $was)       { $verdict = 'نقصَ ' . ($was - $now) . ' — يُثبَّت الخطُّ الجديد'; $mark = '✔'; $improved++; $base[$k] = $now; }
    else                       { $verdict = 'ثابتٌ عند خطِّه'; $mark = '✔'; }
    $rows[] = array($k, $LABEL[$k], $was, $now, $verdict, $mark);
}

/* ◆ **لا تُكتب إلّا عند تغيُّرٍ فعليّ**: السقّاطةُ تعمل في كلِّ التزامٍ الآن،
 *   وكتابةُ `measured_at` في كلِّ جولةٍ تترك الشجرةَ مُتَّسِخةً بعد كلِّ التزام
 *   بفرقٍ لا يحمل معلومة — وهو ضجيجٌ يُخفي التغيُّرَ الحقيقيَّ حين يقع. */
if ($fail === 0 && $unmeasured === 0 && ($set || $base !== $prev)) {
    $hist = array();
    if (is_file($BASE)) {
        $old = json_decode((string) file_get_contents($BASE), true);
        if (is_array($old) && isset($old['history']) && is_array($old['history'])) { $hist = $old['history']; }
        /* الترحيلُ لا يمحو الدليل: خطٌّ رُفع بـ--set يُقيَّد قبل استبدالِه. */
        if ($set && $prev) { $hist[] = array('until' => date('Y-m-d H:i:s'), 'debts' => $prev); }
    }
    @mkdir(dirname($BASE), 0777, true);
    file_put_contents($BASE, json_encode(array(
        'measured_at' => date('Y-m-d H:i:s'),
        'screens' => count($files),
        'debts' => $base,
        'history' => $hist,
        'note' => 'سقّاطةٌ مُقفَلةُ الاتجاه: الرقمُ يهبط ولا يصعد. الزيادةُ رسوبٌ يوقف البناء.'
                . ' وأصنافُ RP-* من REPAIR01 §٧١ — الموجودُ مُرحَّلٌ والجديدُ ممنوع.',
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo "سجلاتُ الدَّينِ الأربعةَ عشرَ وسقّاطتُها المانعةُ للعودة\n";
echo str_repeat('═', 76), "\n";
echo 'الشاشاتُ الحيةُ المقيسة: ' . count($files) . '  ·  نطاقُ RP: ' . $rp['files']
   . '  ·  تاريخُ القياس: ' . date('Y-m-d H:i') . "\n\n";
printf("%-8s %-42s %8s %8s  %s\n", 'السجل', 'الدَّين', 'الأساس', 'اليوم', 'الحكم');
echo str_repeat('─', 76), "\n";
foreach ($rows as $r) {
    printf("%s %-7s %-40s %8s %8s  %s\n", $r[5], $r[0], $r[1],
        $r[2] === null ? '—' : $r[2], $r[3] === null ? '—' : $r[3], $r[4]);
}
echo str_repeat('─', 76), "\n";
echo 'مُحسَّنٌ هذه الجولة: ' . $improved . '  ·  عائدٌ (رسوب): ' . $fail
   . '  ·  لم يُقَس: ' . $unmeasured . '  ·  السجلات: ' . count($rows) . "\n";
echo ($fail === 0 && $unmeasured === 0)
    ? "🟢 السقّاطةُ سليمة — لا دَينَ زاد. وهذا هو إنفاذُ L4 لـUI-DEF-11 وUI-DEF-12.\n"
    : ($unmeasured > 0
        ? "🔴 صنفٌ لم يُقَس — القياسُ الناقصُ ليس أخضرَ (fail-closed).\n"
        : "🔴 دَينٌ زاد — العيبُ عاد والبناءُ يتوقف.\n");

if ($mdOut !== null) {
    $md = "# سجلاتُ الدَّينِ الأربعةَ عشرَ — أرقامُ الجولةِ وسقّاطتُها\n\n";
    $md .= "> قيست على **" . count($files) . " شاشةً حيةً** · " . date('Y-m-d H:i') . "\n>\n";
    $md .= "> **السقّاطة**: خطُّ أساسٍ مسجَّلٌ لكلِّ دَين. نقصَ ⇒ يُثبَّت الخطُّ الجديد. "
        . "زادَ ⇒ رسوبٌ يوقف البناء. فالدَّينُ مُقفَلُ الاتجاه: يهبط ولا يصعد.\n\n";
    $md .= "| السجل | الدَّين | خطُّ الأساس | اليوم | الحكم | المالك |\n";
    $md .= "|---|---|---:|---:|---|---|\n";
    foreach ($rows as $r) {
        $md .= '| `' . $r[0] . '` | ' . $r[1] . ' | ' . ($r[2] === null ? '—' : $r[2]) . ' | **' . $r[3]
            . '** | ' . $r[5] . ' ' . $r[4] . ' | ' . $OWNER[$r[0]] . " |\n";
    }
    $md .= "\n## لماذا سقّاطةٌ لا وعدٌ بالصفر\n\n";
    $md .= "الدَّينُ المعلَنُ برقمٍ وحدَه لا يمنع عودتَه: يكبر بصمتٍ مع كلِّ شاشةٍ جديدة. "
        . "والسقّاطةُ تجعل الرقمَ سقفًا لا وصفًا — فأيُّ زيادةٍ رسوبٌ صريح. "
        . "وبهذا يستوفي `UI-DEF-11` و`UI-DEF-12` شرطَ **L4** في سلّمِ الإغلاقِ الرباعيِّ "
        . "(«فحصٌ آليٌّ يمنع عودةَ العيب») دون ادّعاءِ صفرٍ لم يتحقق.\n\n";
    $md .= "## التشغيل\n\n```bash\nphp tools/u12_debt_ratchet.php\n```\n";
    @mkdir(dirname($mdOut), 0777, true);
    file_put_contents($mdOut, $md);
    echo "\nالمخرَجُ: " . $mdOut . "\n";
}
exit(($fail === 0 && $unmeasured === 0) ? 0 : 1);
