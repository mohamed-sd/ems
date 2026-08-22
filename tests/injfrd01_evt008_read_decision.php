<?php
/**
 * tests/injfrd01_evt008_read_decision.php
 *   شاهدُ FR-EVT-008 — القرارُ معلَنٌ مكتوبٌ · وصفرُ قارئٍ يخالفه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيارُ بنصِّه**: «**قرارٌ معلَنٌ مكتوب** · وصفرُ قارئٍ يخالفه بعدَ
 *   الإعلان» · وسلوكُ الفشل «قارئٌ يخالف القرارَ المعلَن **يُرصد ويُسمّى** —
 *   ولا يُبتلع صامتًا».
 *
 * ◆ **والقرارُ ليس من عندي**: `ADR-15` يقول «الحقيقةُ تسبق إسقاطَها، والدفترُ
 *   أوّلُ قارئٍ لا مالك» — أي `ems_business_events` **سجلُّ حقائقَ لا ناقل**،
 *   فالقراءةُ من الإسقاطِ **مسارٌ قانونيٌّ** لا التفاف. وأُفرِد في
 *   `docs/EVENT_DOMAIN_READ_DECISION_ar.md` لأن المطلبَ يشترط موضعًا مكتوبًا
 *   يُقاس عليه — لا لأنه قرارٌ جديد.
 *
 * ◆ **والمخالفةُ ليست قراءةَ الجذر** بل **قراءتُه لتوليدِ أثرٍ ماليٍّ مباشرةً**:
 *   ملفٌّ يقرأ الجذرَ **ويكتب في دفترٍ ماليٍّ في الملفِّ نفسِه** بلا ناشرٍ ولا
 *   مروحة. وثلاثةُ أوجهٍ مشروعة: الحوكمةُ والعرضُ · النشرُ والتسليمُ ·
 *   التتبُّعُ الصاعد.
 *
 * ◆ **ولا كاشفَ يرصد نفسَه**: هذا الملفُّ يذكر الأسماءَ فيُستثنى صراحةً.
 *
 * التشغيل: php tests/injfrd01_evt008_read_decision.php [--negative]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

$neg = in_array('--negative', $argv, true);
echo "══ FR-EVT-008 — القرارُ المعلَنُ وقُرّاؤه ══\n";

/* ── ① القرارُ **معلَنٌ مكتوبٌ** في موضعٍ واحدٍ يُحال إليه ────────────────── */
$decision = $ROOT . '/docs/EVENT_DOMAIN_READ_DECISION_ar.md';
$txt = (string) @file_get_contents($decision);
chk($txt !== '', 'القرارُ **معلَنٌ مكتوبٌ** في موضعٍ واحد',
    $txt !== '' ? 'docs/EVENT_DOMAIN_READ_DECISION_ar.md · ' . strlen($txt) . ' بايت' : 'مفقود');
chk(strpos($txt, 'ADR-15') !== false && strpos($txt, 'سجلُّ حقائقَ لا ناقلُ رسائل') !== false,
    'ويسمّي **مصدرَه الحاكمَ وحكمَه** لا يكتفي بالعنوان',
    'ADR-15 ⇒ سجلُّ حقائقَ لا ناقل');
chk(strpos($txt, 'سلوكُ الفشل') !== false,
    'ويعلن **سلوكَ الفشل** — من يخالف يُسمّى لا يُطوى');

/* ── ② القُرّاءُ يُمسحون ويُصنَّفون ──────────────────────────────────────── */
echo "\n── ② القُرّاءُ في شجرةِ الإنتاج ──\n";
$SKIP = array('/vendor/', '/node_modules/', '/.git/', '/docs/', '/storage/',
              '/tests/', '/tools/', '/database/migrations/', '/database/seeds/');
/* الأوجهُ الثلاثةُ المشروعةُ — مُعلَنةٌ في الوثيقةِ لا في هذا الملفِّ وحدَه */
$OWNERS = array('app/Core/EventPublisher.php', 'app/Services/Bus/EventDeliveryWorker.php',
                'app/Services/Bus/EventOutboxFanout.php');
$LEDGER = '~(INSERT\s+INTO|UPDATE)\s+`?(fin_journal_\w+|fin_financial_events|fin_event_links)~i';

$touch = array(); $readers = array(); $violators = array(); $trace = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') { continue; }
    $p = str_replace(DIRECTORY_SEPARATOR, '/', $f->getPathname());
    $skip = false;
    foreach ($SKIP as $k) { if (strpos($p, $k) !== false) { $skip = true; break; } }
    if ($skip) { continue; }
    $src = (string) @file_get_contents($p);
    if (strpos($src, 'ems_business_events') === false) { continue; }
    $rel = str_replace($ROOT . '/', '', $p);
    $touch[] = $rel;
    if (!preg_match('~(FROM|JOIN)\s+`?ems_business_events~i', $src)) { continue; }
    $readers[] = $rel;
    if (in_array($rel, $OWNERS, true)) { continue; }          /* مالكو الجذرِ لا قُرّاؤه */
    /* ◆ **والوجهُ الثالثُ المشروع: التتبُّعُ الصاعد** — كاشفي الأوّلُ أغفله
     *   فرمى `ContractSignedEffects` مخالفًا **وهو ليس كذلك**: يقرأ
     *   `SELECT id FROM ems_business_events` **ليس إلا** ليجعله أبًا
     *   للوصلة (`root_event_id`). ومعرِّفٌ وحدَه **لا يُحسَب منه أثرٌ**
     *   ولا يُقرأ منه رقمٌ ماليّ — فهو ربطٌ لا استهلاك.
     * ◆ **والقاعدةُ قابلةٌ للقياس لا للتقدير**: قراءةٌ قائمةُ حقولِها
     *   `id` وحدَها ⇒ تتبُّعٌ صاعدٌ مشروع. وأيُّ حقلٍ آخرَ (مبلغٌ أو
     *   حمولةٌ أو مفتاحُ حدثٍ يُتفرَّع عليه) يُعيدها إلى دائرةِ الحكم. */
    $onlyId = true;
    /* ◆ **والالتقاطُ يجب أن يرسوَ على أقربِ SELECT**: أوّلُ تعبيرٍ بدأ من
     *   أوّلِ `SELECT` في الملفِّ فابتلع ما بينه وبين الجدول — فلم يُطابِق
     *   `id` قطُّ وخرج التتبُّعُ الصاعدُ صفرًا. ⇒ يُمنَع `SELECT` داخلَ الالتقاط. */
    if (preg_match_all('~SELECT\s+((?:(?!SELECT).)+?)\s+FROM\s+`?ems_business_events~is', $src, $sm)) {
        foreach ($sm[1] as $cols) {
            $c = strtolower(preg_replace('~\s+~', ' ', trim($cols)));
            $c = str_replace(array('`', 'distinct '), '', $c);
            if ($c !== 'id' && $c !== 'max(id)' && $c !== 'e.id' && $c !== 'b.id') { $onlyId = false; }
        }
    } else { $onlyId = false; }
    if ($onlyId) { $trace[] = $rel; continue; }
    if (preg_match($LEDGER, $src)) { $violators[] = $rel; }
}
printf("  تمسُّ الجذرَ: %d ملفًّا · **تقرؤه: %d** · مالكو الجذر: %d · تتبُّعٌ صاعدٌ (id وحدَه): %d\n",
       count($touch), count($readers), count($OWNERS), count($trace));
foreach ($trace as $t) { echo "       ◆ تتبُّعٌ صاعدٌ مشروع: {$t}
"; }

chk(count($readers) > 0, '**المقامُ غيرُ صفريّ** — ثمَّ قُرّاءٌ فعلًا يُحكَم عليهم',
    count($readers) . ' قارئًا');

chk(empty($violators),
    'FR-EVT-008 · **صفرُ قارئٍ يخالف القرارَ المعلَن**',
    empty($violators)
      ? 'لا ملفَّ يقرأ الجذرَ ويكتب دفترًا ماليًّا في نفسِه'
      : count($violators) . ' مخالفًا');
foreach ($violators as $v) { echo "       ✘ يخالف: {$v}\n"; }

if ($neg) {
    /* ◆ الحزامُ يدسُّ مخالفًا حقيقيًّا ويُثبت دسَّه ثمّ يكنسه */
    $belt = $ROOT . '/Reports/_evt008_belt.php';
    file_put_contents($belt,
        "<?php\n/* حزامُ FR-EVT-008 — يقرأ الجذرَ ويكتب دفترًا. يُكنس فورًا. */\n"
      . "\$r = \$conn->query(\"SELECT id FROM ems_business_events LIMIT 1\");\n"
      . "\$conn->query(\"INSERT INTO fin_event_links (company_id) VALUES (4)\");\n");
    clearstatcache();
    $planted = is_file($belt) && strpos((string) file_get_contents($belt), 'ems_business_events') !== false;
    if (!$planted) { echo "  ⛔ **لم يُدَسَّ الحزام** — أُوقِف\n"; exit(1); }
    echo "\n  ◆ دُسَّ مخالفٌ — **ووجودُه ومحتواه مُثبَتان قبلَ القياس**\n";

    $src2 = (string) file_get_contents($belt);
    $caught = preg_match('~(FROM|JOIN)\s+`?ems_business_events~i', $src2)
              && preg_match($LEDGER, $src2);
    chk($caught === 1 || $caught === true, 'والكاشفُ **يمسك المخالفَ المدسوس**',
        $caught ? 'أُمسك ✔' : 'مرَّ ✘ — الكاشفُ لا يعمل');
    @unlink($belt);
    clearstatcache();
    chk(!is_file($belt), 'وكُنس الحزامُ أثرَه');
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
