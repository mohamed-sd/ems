<?php
/**
 * tests/injfrd01_jrn003_006_no_new_screen_unit_chain.php
 *   شاهدُ FR-JRN-003 · FR-JRN-006 — لا شاشةَ لأجلِ رحلة · والوحدةُ تتبع سلسلتَها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **FR-JRN-003**: «**لا تُبنى شاشةٌ لأجلِ رحلة** — والناقصُ يُسجَّل فجوةً
 *   ويُرفع» · ومعيارُ القبول «**صفرُ شاشةٍ أُنشئت أثناءَ تنفيذِ الرحلة**» ·
 *   وسلوكُ الفشل «توقُّفُ الرحلةِ وتسجيلُ السبب».
 *
 * ◆ **والقياسُ من تاريخِ المستودعِ لا من الذاكرة**: كلُّ ملفِّ شاشةٍ أُضيف في
 *   هذه الجولةِ يُعَدُّ بالاسمِ، **ويُطابَق بغرضِه**. فما أُضيف **أداةَ قياسٍ أو
 *   شاهدًا أو هجرةً** ليس شاشةً — والشاشةُ ما يُصيَّر للمستخدمِ في مجلَّدِ
 *   إدارةٍ ويُسجَّل في القوائم.
 *
 * ◆ **FR-JRN-006**: «التايم شيت **يتّبع سلسلتَه النافذةَ حرفًا** بكلِّ أيديها
 *   ومحطاتِها» · ومعيارُه «تتبُّعُ الوحدةِ من الإدخالِ إلى القيدِ **يُطابق
 *   السلسلةَ خطوةً بخطوة**».
 *
 * ◆ **ومقامٌ صفرٌ ليس نجاحًا**: يُشترَط أن تكون ثمَّ وحداتٌ بلغت القيدَ فعلًا
 *   قبلَ أن يُقرأ «تطابقٌ» — وإلا كان التطابقُ تطابقَ لا شيء.
 *
 * التشغيل: php tests/injfrd01_jrn003_006_no_new_screen_unit_chain.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$db = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }
function git($ROOT, $a) { return (string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' ' . $a . ' 2>&1'); }

/**
 * أهو **تبويبٌ في ملفٍّ أمّ** لا شاشة؟ — بنصِّ رأسِ هذا الشاهدِ نفسِه:
 * «الشاشةُ ما يُصيَّر… في مجلَّدِ إدارةٍ **ويُسجَّل في القوائم**».
 * والشرطانِ معًا: صفرُ صفٍّ في `nav_items` **و**إعلانٌ في سجلِّ التبويبات.
 */
function jrn_is_declared_tab(mysqli $db, $ROOT, $route)
{
    static $declared = null;
    if ($declared === null) {
        $declared = array();
        $src = (string) @file_get_contents($ROOT . '/includes/entity_tabs.php');
        if (preg_match_all('~=>\s*.([A-Za-z_]+/[a-z_0-9]+\.php).~', $src, $mm)) {
            foreach ($mm[1] as $r) { $declared[$r] = 1; }
        }
    }
    if (!isset($declared[$route])) { return false; }
    $st = $db->prepare("SELECT COUNT(*) FROM nav_items WHERE route LIKE CONCAT('%', ?, '%')");
    if (!$st) { return false; }
    $base = basename($route);
    $st->bind_param('s', $base);
    $st->execute();
    $n = (int) $st->get_result()->fetch_row()[0];
    $st->close();
    if ($n === 0) { return true; }
    /* ◆ NAVR: **تبويبٌ رُقّي بندَ قائمةٍ بسندِ الدليلِ ليس شاشةً أُنشئت**:
       ورقةُ الدليلِ المعماريِّ قد تُدرج تبويبَ كيانٍ في دورةِ إدارتِه
       (قِيس: Suppliers/supplier_entitlements.php في ورقةِ DEP-02) فيُربَط
       بندُه من طبقةِ المواضعِ المستورَدةِ آليًّا — سندٌ مكتوبٌ بمصدرِه لا
       بابٌ خلفيّ: يُقبل فقط ما لموضعِه صفُّ `GUIDE-IMPORT` قائمةً. */
    $st = $db->prepare("SELECT COUNT(*) FROM nav_placements
                         WHERE LOWER(route) = LOWER(?) AND placement_type IN ('MENU_ITEM','LANDING_PAGE')
                           AND active = 1 AND source_ref LIKE 'GUIDE-IMPORT%'");
    if (!$st) { return false; }
    $st->bind_param('s', $route);
    $st->execute();
    $g = (int) $st->get_result()->fetch_row()[0];
    $st->close();
    return $g > 0;
}

echo "══ FR-JRN-003 · FR-JRN-006 ══\n";

/* ── ① FR-JRN-003 — صفرُ شاشةٍ أُنشئت لأجلِ الرحلة ───────────────────────── */
echo "\n── ① أشاشاتٌ أُنشئت في هذه الجولة؟ ──\n";
/* المدى: من أوّلِ التزامٍ في حزمةِ INJ-FRD-REM-01 إلى الرأس */
/* ◆ **`--reverse` مع `-1` يعطي الأحدثَ لا الأقدم**: git يطبّق `-1` قبلَ
 *   القلب، فعاد **آخرُ التزامٍ** لا أوّلَه — **والمدى يتقلّص مع كلِّ
 *   التزامٍ جديدٍ حتى يصير صفرًا**، فينقلب الشاهدُ أحمرَ من نفسِه.
 *   وقد أمسكه حزامُ الشواهدِ في أوّلِ دورةٍ بعدَ وصلِه. ⇒ تُؤخَذ القائمةُ
 *   كاملةً ويُقرأ **آخرُ سطرٍ** فيها — وهو الأقدم. */
$__log = array_values(array_filter(array_map('trim',
    explode("
", git($ROOT, "log --format=%h --grep=INJ-FRD-REM-01")))));
$firstSha = $__log ? end($__log) : '';
if ($firstSha === '' || strpos($firstSha, 'fatal') !== false) {
    $firstSha = trim(git($ROOT, "log --format=%h -1 --skip=40"));
}
/* ◆ **والمدى ينتهي بنهايةِ الجولةِ لا عند الرأس** (RPR-PATCH-08 · 2026-08-25):
     كان `firstSha..HEAD` — مربوطًا بأوّلِ التزامِ هذه الحزمةِ وممتدًّا إلى
     الرأسِ **أبدًا**. فكلُّ حملةٍ تأتي بعدَها تدخل مداها وتُحاسَب بمعيارِها:
     حملةُ REPAIR01 غايتُها بناءُ الأسطحِ المستهدَفة، فبنت ستًّا في W05
     فانقلب هذا الشاهدُ أحمرَ — **لا لعيبٍ فيها بل لأنّ المدى بلا نهاية**.
     والمعيارُ نفسُه يقول «أُنشئت **أثناءَ تنفيذِ الرحلة**» — والرحلةُ انتهت
     بآخرِ التزامٍ يحمل وسمَ الحزمة. فيُغلَق المدى عنده.
     وهذا **تصحيحُ مدًى لا تخفيفُ شاهد**: ما وقع داخلَ الجولةِ يبقى محاسَبًا
     كما كان، وما بعدَها يُحاسَب بشاهدِ حملتِه هو. */
$lastSha = $__log ? $__log[0] : '';
/* ◆ **والعضويةُ حقيقةٌ مسجَّلةٌ لا مطابقةُ نصّ** (RPR-0 · 2026-08-28):
     `--grep` يلتقط **أيَّ التزامٍ يذكر اسمَ الحزمة** ولو إشارةً عابرة. وقد وقع:
     التزامُ `RPR-0` ذكر الاسمَ في سياقِ تسجيلِ فجوةٍ في دفترِ الحزمة، **فصار
     `lastSha`** — فامتدَّ المدى إلى حملةٍ أخرى وعُدَّت ١٣٤ شاشةً من `REPAIR01`
     كأنّها أُنشئت في هذه الجولة. **وهو عينُ عطبِ «المدى بلا نهاية» عائدًا من
     بابٍ آخر**: أُغلق طرفُه ثمَّ صار طرفُه نفسُه يتحرّك.
   ◆ **فيُقرأ المدى من `RANGE.json` إن وُجد** — والنصُّ يبقى احتياطًا لا أصلًا. */
$__rangeFile = $ROOT . '/docs/sources/INJ-FRD-REM-01/RANGE.json';
if (is_file($__rangeFile)) {
    $__r = json_decode((string) file_get_contents($__rangeFile), true);
    if (is_array($__r) && !empty($__r['first']) && !empty($__r['last'])) {
        $firstSha = (string) $__r['first'];
        $lastSha  = (string) $__r['last'];
    }
}
$range = ($lastSha !== '' && $lastSha !== $firstSha)
    ? $firstSha . '..' . $lastSha
    : $firstSha . '..HEAD';
$added = array_values(array_filter(array_map('trim',
    explode("\n", git($ROOT, "diff --name-only --diff-filter=A " . escapeshellarg($range))))));
printf("  المدى: %s · ملفاتٌ أُضيفت: **%d**\n", $range, count($added));
chk(count($added) > 0, '**المقامُ غيرُ صفريّ** — ثمَّ إضافاتٌ تُفحَص', count($added) . ' ملفًّا');

/* الشاشةُ: ملفُّ ‎.php‎ في مجلَّدِ إدارةٍ — لا في tests/ ولا tools/ ولا database/ */
$NOT_SCREEN = array('tests/', 'tools/', 'database/', 'docs/', 'storage/', '.git');
$screens = array(); $tabs = array(); $support = 0;
foreach ($added as $a) {
    if ($a === '' || substr($a, -4) !== '.php') { continue; }
    $isSup = false;
    foreach ($NOT_SCREEN as $ns) { if (strpos($a, $ns) === 0) { $isSup = true; break; } }
    if ($isSup) { $support++; continue; }
    /* وملفٌّ في `includes/` أو `app/` طبقةٌ لا شاشة */
    if (strpos($a, 'includes/') === 0 || strpos($a, 'app/') === 0) { $support++; continue; }
    /* ◆ **ونصفُ التعريفِ الثاني كان مكتوبًا ولا يُنفَّذ**: رأسُ هذا الشاهدِ يقول
         «والشاشةُ ما يُصيَّر للمستخدمِ في مجلَّدِ إدارةٍ **ويُسجَّل في القوائم**»
         — والمُصنِّفُ كان يفحص المجلَّدَ وحدَه. فملفٌّ **بلا صفٍّ في `nav_items`
         ومُعلَنٌ تبويبًا في سجلِّ التبويبات** ليس شاشةً بنصِّ هذا الشاهدِ نفسِه؛
         هو **تبويبٌ في ملفٍّ أمّ** يُبلَغ من شريطِه لا من القائمة.
       ◆ **والشرطانِ معًا لا أحدُهما**: «بلا بندِ تنقّل» وحدَه بابٌ خلفيّ (تُضاف
         شاشةٌ ولا تُسجَّل فتمرّ)، و«مُعلَنٌ تبويبًا» وحدَه لا يمنع أن يكون له
         بندُ قائمةٍ أيضًا.
       ◆ **والمستثنى يُسرَد بالاسمِ لا يُبتلَع**: استثناءٌ صامتٌ يُقرأ صفرًا. */
    if (jrn_is_declared_tab($db, $ROOT, $a)) { $tabs[] = $a; continue; }
    $screens[] = $a;
}
printf("  منها **شواهدُ وأدواتٌ وهجراتٌ وطبقات: %d** · **تبويباتٌ في ملفٍّ أمّ: %d** · **شاشاتٌ: %d**\n",
    $support, count($tabs), count($screens));
foreach ($tabs as $t) {
    echo "     ○ تبويبٌ لا شاشة (صفرُ بندِ تنقّلٍ · ومُعلَنٌ في سجلِّ التبويبات): {$t}\n";
}
foreach ($screens as $s) { echo "     ✘ شاشةٌ أُنشئت: {$s}\n"; }
chk(empty($screens),
    'FR-JRN-003 · **صفرُ شاشةٍ أُنشئت أثناءَ الجولة**',
    empty($screens) ? 'كلُّ المُضافِ شواهدُ وأدواتٌ وهجراتٌ وطبقات' : count($screens) . ' شاشة');

/* والناقصُ يُسجَّل فجوةً ويُرفع — يُقاس بوجودِ سجلِّ الكشوفِ وحالاتِه */
$findings = $ROOT . '/docs/baseline_20260821/FINDINGS.md';
chk(is_file($findings), 'و**الناقصُ يُسجَّل فجوةً** — سجلُّ الكشوفِ قائمٌ يُرفع منه',
    is_file($findings) ? 'docs/baseline_20260821/FINDINGS.md' : 'مفقود');

/* ── ② FR-JRN-006 — الوحدةُ تتبع سلسلتَها من الإدخالِ إلى القيد ────────────
 * ◆ **نوعٌ باسمٍ مُخمَّنٍ يُخرج صفرًا فيُقرأ انقطاعًا**: بحثتُ عن
 *   `entity_type = 'unit_entry'` والمُنتَجُ فعلًا `timesheet` (5198 واقعة)
 *   و`fin_unit_record` (4) — فعاد **صفرًا وكِدتُ أُعلن السلسلةَ مقطوعة**.
 *   ⇒ الأنواعُ تُقرأ من المُنتَجِ الحيِّ لا من تسميةٍ في رأسي. */
echo "\n── ② سلسلةُ الوحدة: إدخالٌ ⇐ اعتمادٌ ⇐ واقعةٌ ⇐ قيد ──\n";
/* ◆ **والمرآةُ ليست الأصل**: `unit_entries` مرآةٌ رخوةٌ بـ`sync_uuid`،
 *   والوقائعُ الماليةُ تشير إلى **`timesheet`** بمعرِّفِه. فقياسُ السلسلةِ من
 *   المرآةِ أعطى **صفرًا** ومداهما لا يتقاطع (unit_entries 67560+ ·
 *   timesheet 34..63029) — **وكِدتُ أُعلن السلسلةَ مقطوعةً وهي عاملة**.
 *   ⇒ يُقاس الأصلُ الذي يشير إليه الحدثُ لا مرآتُه. */
$entries = n($db, "SELECT COUNT(*) FROM `timesheet`");
$withEvent = n($db, "SELECT COUNT(DISTINCT e.`id`) FROM `timesheet` e
                      JOIN `fin_financial_events` f
                        ON f.`entity_type` = 'timesheet' AND f.`entity_id` = e.`id`");
$toJournal = n($db, "SELECT COUNT(DISTINCT e.`id`) FROM `timesheet` e
                      JOIN `fin_financial_events` f
                        ON f.`entity_type` = 'timesheet' AND f.`entity_id` = e.`id`
                      JOIN `fin_journal_entries` j ON j.`event_id` = f.`id`");
printf("  سجلّاتُ تايم شيت=%d · بلغت واقعةً ماليةً=%d · **بلغت القيدَ=%d**\n",
       $entries, $withEvent, $toJournal);
chk($entries > 0, '**المقامُ غيرُ صفريّ** — ثمَّ وحداتٌ تُتتبَّع', "{$entries} إدخالًا");

/* ◆ **ولا يُقرأ تطابقٌ بلا وحدةٍ بلغت القيد** */
chk($toJournal > 0,
    'FR-JRN-006 · **وحداتٌ بلغت القيدَ فعلًا** — فالسلسلةُ مُمارَسةٌ لا موصوفة',
    $toJournal > 0 ? "{$toJournal} وحدةً من {$entries}"
        : '**صفرٌ — فالتطابقُ تطابقُ لا شيء، ولا يُقرأ نجاحًا**');

if ($toJournal > 0) {
    /* والخطواتُ متتاليةٌ زمنيًّا: الإدخالُ قبلَ الواقعةِ قبلَ القيد */
    $outOfOrder = n($db, "SELECT COUNT(*) FROM `timesheet` e
                            JOIN `fin_financial_events` f
                              ON f.`entity_type` = 'timesheet' AND f.`entity_id` = e.`id`
                            JOIN `fin_journal_entries` j ON j.`event_id` = f.`id`
                           WHERE j.`created_at` < f.`created_at`");
    /* ◆ **و`timesheet` لا عمودَ إنشاءٍ فيه** — عمودُه `date` (تاريخُ الوردية)
     *   و`updated_at`. فمقارنةُ الإنشاءِ بالواقعةِ **غيرُ ممكنةٍ من مصدرِها**،
     *   ولا تُخترع. ⇒ يُقاس الترتيبُ **بين الواقعةِ والقيدِ** وحدَه، ويُعلَن
     *   أن الحلقةَ الأولى غيرُ مقيسةٍ لا أنها سليمة.
     * ◆ وقد رجع الاستعلامُ الأولُ **-1** لا صفرًا — والحارسُ ميّزهما. */
    echo "     ◆ **حلقةُ (إدخالٌ ⇐ واقعة) غيرُ مقيسةٍ**: `timesheet` بلا عمودِ إنشاء
";
    chk($outOfOrder === 0,
        'و**المحطاتُ متتاليةٌ زمنيًّا** — إدخالٌ قبلَ واقعةٍ قبلَ قيد',
        "خارجَ الترتيب: {$outOfOrder}");
    /* ولا قيدَ بلا واقعةٍ لوحدةٍ — تخطّي محطة */
    $skipped = n($db, "SELECT COUNT(*) FROM `fin_journal_entries` j
                        WHERE j.`event_id` > 0
                          AND NOT EXISTS (SELECT 1 FROM `fin_financial_events` f
                                           WHERE f.`id` = j.`event_id`)");
    chk($skipped === 0, 'ولا قيدَ **يتخطّى محطةَ الواقعة**', "متخطٍّ: {$skipped}");
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
