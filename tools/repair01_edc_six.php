<?php
/**
 * tools/repair01_edc_six.php — الستُّ شاشات: ثلاثٌ تُثبَّت وثلاثٌ تُقاس
 * ═══════════════════════════════════════════════════════════════════════════
 * **حكمُ المالكِ 2026-08-27 (‏أولًا)**: «لا نعيد الاثني عشر بندًا كلَّها إليك
 * كقرارات مالك … ما حسمته الوثائقُ سابقًا **يُطبَّق مباشرةً ولا يُعاد سؤالُك
 * عنه** · وما يحتاج قياسًا تقنيًّا فقط **يُقاس ويُرفع بنتيجتِه لا بخياراتٍ
 * مفتوحة** · وما هو قرارُ أعمالٍ حقيقيٌّ فقط يعود إليك.»
 *
 * ⛔ **وهذا يمنع خطأَين متعاكسَين**: أن يخترع المبرمجُ قرارًا باسمِ المالك،
 *   **وأن يعيد إليه عشراتِ القراراتِ التي حسمَتها الوثائقُ بالفعل**. وكنتُ
 *   سأقع في الثاني: رفعتُ ستًّا وثلاثٌ منها محسومةٌ في الدستور.
 *
 * ═══ الثلاثُ المحسومةُ — تُثبَّت بلا سؤال ═══
 *  · بطاقةُ الموظف        ⇐ `DEP-07` الموارد البشرية
 *  · تقريرُ الأعطال       ⇐ `DEP-14` الصيانة (‏تملك العطلَ والتشخيصَ والإصلاح)
 *  · الخطةُ الشهريّةُ     ⇐ `DEP-11` التشغيل
 *
 * ◆ **ولمسُ جداولِ نطاقٍ آخرَ لا ينقل الملكيّة** — نصُّ الحكم. لكنّه يُقاس:
 *   **قراءةٌ** ⇒ تُسجَّل `CROSS_DOMAIN_REFERENCE` · **كتابةٌ** ⇒ `BOUNDARY_BREACH`
 *   يُصحَّح ⛔ **ولا يتغيّر المالك**.
 *
 * ═══ الثلاثُ التي تُقاس — ويُرفع الالتباسُ وحدَه ═══
 *  · تقريرُ المنع        — أحوكمةٌ (‏منعٌ · خرقُ فصلِ واجباتٍ · تجاوزُ سلطة)
 *                          أم تقريرٌ تقنيٌّ عن محرّكِ الأمن؟
 *  · `aprovment.php`     — أصندوقُ اعتمادٍ موحَّدٌ (‏منصّة) أم اعتمادُ معاملةٍ
 *                          بعينِها (‏نطاقُ مالكِها)؟ ⛔ ولا يُختار من اسمِ المجلَّد
 *  · `projects_reports`  — **التقريرُ الذي يجمع حقائقَ عدّةِ إداراتٍ لا يصبح
 *                          مالكًا لأيٍّ منها** (‏قاعدةٌ دائمةٌ بنصِّ الحكم)
 *
 * التشغيل: php tools/repair01_edc_six.php [--report]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$REPORT = in_array('--report', $argv, true);
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

/* فهرسُ الشجرة */
$idx = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    if (substr($p->getFilename(), -4) !== '.php') { continue; }
    $s = strtr($p->getPathname(), DIRECTORY_SEPARATOR, '/');
    if (strpos($s, '/.git/') !== false || strpos($s, '/vendor/') !== false) { continue; }
    if (!isset($idx[$p->getFilename()])) { $idx[$p->getFilename()] = $s; }
}
$src = function ($b) use ($idx) { return isset($idx[$b]) ? (string) @file_get_contents($idx[$b]) : ''; };

/* بادئاتُ الجداولِ إلى نطاقِها — عُرفٌ مقيسٌ في هذا المستودع */
$PREF = array('tkt_' => 'DEP-10', 'ticket' => 'DEP-10', 'mnt_' => 'DEP-14', 'trp_' => 'DEP-15',
              'proc_' => 'DEP-16', 'wh_' => 'DEP-17', 'acc_' => 'DEP-05', 'tre_' => 'DEP-06',
              'fin_' => 'DEP-03', 'hr_' => 'DEP-07', 'employee' => 'DEP-07', 'payroll' => 'DEP-07',
              'gov_' => 'DEP-08', 'risk_' => 'DEP-09', 'audit_' => 'IAF', 'iaf_' => 'IAF',
              'equipment' => 'DEP-04', 'asset' => 'DEP-04', 'fleet' => 'DEP-04',
              'timesheet' => 'DEP-11', 'movement' => 'DEP-11', 'contract' => 'DEP-01',
              'supplier' => 'DEP-02', 'project' => 'DEP-12');

/* يقيس لمسَ الملفِّ لجداولِ نطاقاتٍ أخرى: قراءةً أم كتابة */
$touch = function ($code, $own) use ($PREF) {
    $read = array(); $write = array();
    foreach ($PREF as $p => $dep) {
        if ($dep === $own) { continue; }
        $q = preg_quote($p, '~');
        if (preg_match('~\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\s+`?' . $q . '\w*~i', $code)) { $write[$dep] = true; }
        elseif (preg_match('~\b(FROM|JOIN)\s+`?' . $q . '\w*~i', $code)) { $read[$dep] = true; }
    }
    return array(array_keys($read), array_keys($write));
};

echo "\n═══ الستُّ شاشات — حكمُ المالكِ 2026-08-27 ═══\n";
echo ($REPORT ? "  وضعُ التقرير: يقرأ ولا يكتب\n" : "");

/* ═══ ① الثلاثُ المحسومةُ في الوثائق ═══════════════════════════════════════ */
echo "\n① محسومةٌ في الدستورِ — تُثبَّت ولا تُرفع\n";
$SETTLED = array(
    array('employee_card.php',   'DEP-07', 'الموارد البشرية تملك سجل الانسان — بطاقة الموظف عرضه'),
    array('failure_report.php',  'DEP-14', 'الصيانة تملك العطل والتشخيص وامر الصيانة والاصلاح واعادة الخدمة ومؤشرات الاعطال'),
    array('monthly_plan.php',    'DEP-11', 'ادارة التشغيل تملك خطة التشغيل الشهرية'),
);
$breach = array();
foreach ($SETTLED as $s) {
    list($b, $own, $why) = $s;
    list($rd, $wr) = $touch($src($b), $own);
    $note = '';
    if ($wr) { $note = 'BOUNDARY_BREACH: يكتب في ' . implode('/', $wr); $breach[] = array($b, $wr); }
    elseif ($rd) { $note = 'CROSS_DOMAIN_REFERENCE: يقرأ ' . implode('/', $rd) . ' — ولا ينقل الملكية'; }
    else { $note = 'لا لمس نطاق اخر'; }
    printf("  · %-24s ⇐ %-8s %s\n", $b, $own, $note);
    if ($REPORT) { continue; }
    $conn->query("UPDATE repair01_screen_registry
        SET owner_code = '" . $e($own) . "',
            verdict_rule = CONCAT(verdict_rule, ' | حكم المالك 2026-08-27 اولا: " . $e($why) . " · " . $e($note) . "')
      WHERE screen_file = '" . $e($b) . "' AND on_disk = 1");
}

/* ═══ ② الثلاثُ التي تُقاس ═══════════════════════════════════════════════ */
echo "\n② تُقاس — ويُرفع الالتباسُ وحدَه\n";
$out = array();

/* ⓐ تقريرُ المنع — حوكمةٌ أم منصّة؟ */
$c = $src('guard_denials_report.php');
$govSig = preg_match_all('~(SoD|فصل الواجبات|صلاحي|سياس|تجاوز|خرق|policy|authority)~ui', $c);
$secSig = preg_match_all('~(guard|session|csrf|token|firewall|engine|denial_log|http)~i', $c);
$v = ($govSig >= 3 && $govSig > $secSig) ? array('DEP-08', 'رقابة حوكمة: يعرض المنع وخرق فصل الواجبات وتجاوز السلطة')
   : (($secSig > $govSig * 2) ? array('PLATFORM', 'تقرير تقني عن محرك الامن — قدرة منصة لا ادارة')
   : array('', "التباسٌ: إشاراتُ حوكمةٍ $govSig مقابلَ إشاراتِ أمنٍ $secSig"));
$out[] = array('guard_denials_report.php', $v[0], $v[1]);

/* ⓑ aprovment — صندوقٌ موحَّدٌ أم اعتمادُ معاملةٍ بعينِها؟ */
$c = $src('aprovment.php');
$uni = preg_match_all('~(approval_requests|approvals|inbox|pending_approvals|ApprovalEngine|approval_engine)~i', $c);
$one = preg_match_all('~(timesheet|shift|unit|hours)~i', $c);
$v = ($uni >= 2 && $uni >= $one) ? array('PLATFORM', 'صندوق اعتماد موحد يتبع محرك الاعتماد — قدرة منصة')
   : (($one > $uni) ? array('DEP-11', 'اعتماد معاملة بعينها: التايم شيت والورديات — نطاق مالك المعاملة')
   : array('', "التباسٌ: إشاراتُ صندوقٍ موحَّدٍ $uni مقابلَ إشاراتِ معاملةٍ بعينِها $one"));
$out[] = array('aprovment.php', $v[0], $v[1]);

/* ⓒ projects_reports — القاعدةُ الدائمة */
$c = $src('projects_reports.php');
$doms = array();
foreach ($PREF as $p => $dep) {
    if (preg_match('~\b(FROM|JOIN)\s+`?' . preg_quote($p, '~') . '\w*~i', $c)) { $doms[$dep] = true; }
}
$v = (count($doms) >= 3)
   ? array('PLATFORM', 'تقرير مؤسسي يجمع ' . count($doms) . ' نطاقات — والتقرير الذي يجمع حقائق عدة ادارات لا يصبح مالكا لايها')
   : array('', 'يقرأ ' . count($doms) . ' نطاقًا فقط — لا يبلغ حدَّ التقريرِ المؤسّسيّ');
$out[] = array('projects_reports.php', $v[0], $v[1]);

$still = array();
foreach ($out as $o) {
    list($b, $own, $why) = $o;
    if ($own === '') { $still[] = array($b, $why); printf("  ◆ %-26s %s\n", $b, $why); continue; }
    printf("  ✔ %-26s ⇐ %-9s %s\n", $b, $own, $why);
    if ($REPORT) { continue; }
    $conn->query("UPDATE repair01_screen_registry
        SET owner_code = '" . $e($own) . "',
            verdict_rule = CONCAT(verdict_rule, ' | قياس EDC بمعيار المالك: " . $e($why) . "')
      WHERE screen_file = '" . $e($b) . "' AND on_disk = 1");
}

/* ═══ ③ الحصيلة ═══════════════════════════════════════════════════════════ */
echo "\n────────────────────────────────────────────────────────────\n";
if ($breach) {
    echo "⚠ خرقُ حدودِ نطاقٍ يُصحَّح (‏ولا يتغيّر المالك):\n";
    foreach ($breach as $x) { printf("   · %-24s يكتب في %s\n", $x[0], implode('/', $x[1])); }
}
if (!$REPORT) {
    $n = (int) $conn->query("SELECT COUNT(*) FROM repair01_screen_registry
                              WHERE COALESCE(owner_code,'') = '' AND on_disk = 1")->fetch_row()[0];
    printf("سطحٌ حيٌّ بلا مالكٍ الآن: %d\n", $n);
}
printf("يُرفع للمالكِ بعد القياس: %d\n", count($still));
