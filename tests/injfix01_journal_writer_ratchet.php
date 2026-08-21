<?php
/**
 * tests/injfix01_journal_writer_ratchet.php
 *   سقّاطةُ كتّابِ دفترِ القيد — INJ-FIX-01 · GAP-27
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معيارُ القبول بنصِّه**: «كاتبٌ واحدٌ معتمَدٌ للدفتر · وما عداه يمرُّ به
 *   **أو يُوثَّق استثناءً** بقيدٍ يمنع غيرَه · **وفحصٌ يُرسِّب عندَ ظهورِ كاتبٍ
 *   رابع**». فالمطلوبُ سقّاطةٌ لا إعادةَ كتابةِ ثلاثةِ مساراتِ ترحيلٍ مالية —
 *   وتلك عملُ توحيدِ المجالِ الماليِّ لا إصلاحًا عابرًا.
 *
 * ◆ **وتصحيحُ عددٍ في البطاقة**: البطاقةُ تقول «ثلاثةُ كتّابٍ مباشرين».
 *   والمقيسُ **بكشفٍ يرى وجهَي الكتابة** — عبارةَ SQL الخامَّ **ونداءَ البوابة**
 *   (`$g->insert('fin_journal_entries', …)`) — أربعةُ مواضعِ إنتاج: ثلاثةُ
 *   إدراجٍ وواحدُ تحديثِ حالة.
 *
 * ◆ **ولماذا كان الكشفُ ناقصًا**: أولُ جردٍ طابق `INSERT INTO` وحدَه، فلم يظهر
 *   `PostingService` — **الكاتبُ المعتمَدُ نفسُه** — لأنه يكتب بنداءِ البوابة.
 *   وجردٌ ناقصٌ للكتّابِ يجعل التقاعدَ يبدو أسهلَ مما هو، **وهو أخطرُ من جردٍ
 *   زائد**.
 *
 * التشغيل: php tests/injfix01_journal_writer_ratchet.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

/* توحيدُ الفواصل: `$p` يُحوَّل إلى `/` أدناه، فلو بقي `$ROOT` بفواصلِ ويندوز
   لَما قُصِّر المسارُ النسبيُّ فظهر كلُّ كاتبٍ «غيرَ مُعلَن» — رسوبٌ كاذبٌ سببُه
   الفاحصُ لا الشجرة. */
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$pass = 0; $fail = 0;
function ok($c, $l, &$p, &$f, $d = '') { if ($c) { $p++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } else { $f++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } }

/** الجداولُ المحروسة. */
$TABLES = array('fin_journal_entries', 'fin_journal_lines');

/** الكاتبُ المعتمَدُ الوحيد. */
$CANONICAL = 'app/Services/Finance/PostingService.php';

/**
 * استثناءاتٌ **مُعلَنةٌ بسببِها** — لا قائمةُ سكوت.
 * وكلُّ سطرٍ هنا دَينٌ مُعلَنٌ يُسدَّد في توحيدِ المجالِ الماليّ، لا إذنٌ دائم.
 */
$DECLARED = array(
    'Finance/fin_helpers.php' =>
        'ترحيلٌ آليٌّ من الواقعةِ المالية (رأسٌ وسطران متوازنان في معاملةٍ واحدة) — '
        . 'مسارُ ترحيلٍ قائمٌ سابقٌ لـPostingService · يُدمج في توحيدِ المجالِ الماليّ',
    'includes/fx.php' =>
        'قيدُ فرقِ إعادةِ التقييمِ الدوريّ (M-40) — يكتب بالبوابةِ في معاملةٍ · '
        . 'يُدمج في توحيدِ المجالِ الماليّ',
    'Finance/journal_form_fin.php' =>
        'شاشةُ القيدِ اليدويّ — والقيدُ اليدويُّ مشروعٌ محاسبيًّا (بروتوكولُ القياس ②)، '
        . 'وحوكمتُه بالمستندِ والسببِ واليدَين لا بمنعِ الكتابة',
    'app/Services/Governance/GovernanceM14Service.php' =>
        'تحديثُ **حالةٍ** لا إدراجُ قيد (إعادةُ المسوَّدةِ عند الردِّ أو الرفض) — '
        . 'لا يُنشئ أثرًا ماليًّا',
);

$SKIP = array('/storage/', '/vendor/', '/.git/', '/docs/', '/tests/', '/tools/',
              '/node_modules/', '/examples/', '/database/migrations/', '/database/seeds/');

echo "════ سقّاطةُ كتّابِ دفترِ القيد — GAP-27 ════\n";
echo "  المعتمَد: {$CANONICAL}\n";
echo "  استثناءاتٌ مُعلَنة: " . count($DECLARED) . "\n";

/* ── المسح ─────────────────────────────────────────────────────────────── */
$found = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') { continue; }
    $p = str_replace('\\', '/', $f->getPathname());
    $bad = false;
    foreach ($SKIP as $s) { if (strpos($p, $s) !== false) { $bad = true; break; } }
    if ($bad) { continue; }
    $src = @file_get_contents($p);
    if ($src === false) { continue; }
    $rel = str_replace($ROOT . '/', '', $p);
    foreach ($TABLES as $t) {
        if (stripos($src, $t) === false) { continue; }
        $rxSql  = '/(INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\s+`?' . preg_quote($t, '/') . '`?\b/i';
        $rxGate = '/->\s*(insert|update|delete|upsert|replace)\s*\(\s*[\'"]' . preg_quote($t, '/') . '[\'"]/i';
        if (preg_match($rxSql, $src) || preg_match($rxGate, $src)) { $found[$rel] = true; }
    }
}
ksort($found);
$found = array_keys($found);

echo "\n── الكتّابُ المقيسون في شجرةِ الإنتاج ──\n";
$undeclared = array();
foreach ($found as $rel) {
    if ($rel === $CANONICAL)      { echo "  ✔ معتمَد   {$rel}\n"; continue; }
    if (isset($DECLARED[$rel]))   { echo "  ◆ مُعلَن   {$rel}\n"; continue; }
    echo "  ✘ **غيرُ مُعلَن** {$rel}\n";
    $undeclared[] = $rel;
}

/* ── الأحكام ────────────────────────────────────────────────────────────── */
echo "\n── الحكم ──\n";
ok(in_array($CANONICAL, $found, true),
   'الكاتبُ المعتمَدُ حيٌّ ويكتب فعلًا', $pass, $fail, $CANONICAL);

ok(count($undeclared) === 0,
   '**صفرُ كاتبٍ غيرِ مُعلَن** — لا يظهر كاتبٌ جديدٌ صامتًا', $pass, $fail,
   count($undeclared) ? implode(' · ', $undeclared) : count($found) . ' كاتبًا كلُّهم معروف');

/* ◆ والاستثناءُ المُعلَنُ الذي لم يعد موجودًا يُنظَّف — فقائمةٌ تنمو ولا تنقص
     تصير غطاءً. والدَّينُ المُسدَّدُ يُشطب من الدفترِ لا يبقى فيه. */
$stale = array();
foreach (array_keys($DECLARED) as $rel) {
    if (!in_array($rel, $found, true)) { $stale[] = $rel; }
}
ok(count($stale) === 0,
   'كلُّ استثناءٍ مُعلَنٍ ما يزال كاتبًا فعلًا — لا استثناءَ متقادم', $pass, $fail,
   count($stale) ? 'متقادم: ' . implode(' · ', $stale) : '—');

/* ◆ ولكلِّ استثناءٍ سببٌ مكتوبٌ — لا سطرَ صامتًا في القائمة. */
$noReason = array();
foreach ($DECLARED as $rel => $why) {
    if (trim((string) $why) === '') { $noReason[] = $rel; }
}
ok(count($noReason) === 0, 'لكلِّ استثناءٍ سببٌ مكتوب', $pass, $fail,
   count($noReason) ? implode(' · ', $noReason) : count($DECLARED) . ' سببًا');

echo "\n── الدَّينُ المُعلَنُ ──\n";
foreach ($DECLARED as $rel => $why) { echo "  · {$rel}\n    ↳ {$why}\n"; }

echo "───────────────────────────────────────────────────────────────\n";
echo ($fail === 0 ? "✔" : "✘") . " النتيجة: نجح {$pass} · رسب {$fail}\n";
if ($undeclared) {
    echo "◆ ظهر كاتبٌ جديدٌ لدفترِ القيد. إمّا يمرُّ بـ{$CANONICAL}،\n";
    echo "  وإمّا يُعلَن في \$DECLARED بسببٍ مكتوبٍ وتاريخِ سداد.\n";
}

/* حكمُ الإغلاقِ — عقدُ GAP-56: يُصرَّح به بعدَ القياسِ لا يُستنتَج من الذِّكر */
require_once dirname(__DIR__) . '/tools/lib/gap_verdict.php';
gapv('GAP-27', true, 'كتّابُ دفترِ القيدِ محصورون في قائمةٍ مُعلَنةٍ بسببٍ مكتوبٍ لكلٍّ — ولا كاتبَ جديد', $fail);

exit($fail === 0 ? 0 : 1);
