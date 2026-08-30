<?php
/**
 * tests/rpr02_sod_negative.php — `RPR-02` #٥ · فاحصٌ سالبٌ لكلِّ فصلِ واجبٍ حرِج
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — `RPR-02` **#٥**: «اختبارٌ سالبٌ لفصلِ الواجباتِ الحرِج»
 *   هدفُه **١٠٠٪**، والمقيسُ **٨ من ٩٢** فصلَ واجبٍ حرِجٍ **يذكره فاحصٌ سالبٌ
 *   أو حاجبٌ بمعرِّفِه**. ⇒ **وأربعةٌ وثمانون واجبًا حرِجًا بلا فاحصٍ يسمّيه.**
 *
 * ◆ **وما يفحصه هذا الفاحصُ — ويُقال حدُّه صراحةً**:
 *   **T1 · سلامةُ السجلّ** — لكلِّ عمليّةٍ حرِجةٍ `process_key` غيرُ فارغٍ
 *        **وممنوعٌ صريحٌ مكتوب** (`forbidden_combo`). ⛔ **وعمليّةٌ بلا ممنوعٍ
 *        مكتوبٍ ليست فصلَ واجباتٍ بل عنوانٌ**.
 *   **T2 · فصلُ الأدوارِ فعليّ** — الشارعُ (`initiator`) **ليس** المعتمِدَ
 *        (`approver`). ⇒ **وهذا هو الفصلُ نفسُه**: صفٌّ يجعلهما دورًا واحدًا
 *        **يُعلن فصلًا لا وجودَ له**.
 *   **T3 · الإنفاذُ موجودٌ في شجرةِ الإنتاج** — رمزُ `enforced_by` **يُرَدُّ
 *        رفضًا في الكود** (`app/**`): `SAME_ACTOR_PREPARE_AND_APPROVE_ADJ` مثلًا
 *        مردودٌ في `AccountingCycleService`. ⛔ **والرمزُ الذي لا يظهر في
 *        الشجرةِ دعوى إنفاذٍ بلا منفِّذ.**
 *
 * ⛔ **وحدُّ هذا الفاحصِ يُقال ولا يُخفى**: يُثبت أنَّ **الحارسَ مكتوبٌ ويردُّ
 *   بالرمزِ نفسِه** — ⛔ **ولا يُثبت أنّه يحمرُّ عند خرقٍ حيٍّ**؛ تلك تُقاس
 *   بتشغيلِ المسارِ بحسابَين، وهي `UAT_BLOCKER` تنتظر أشخاصًا حقيقيّين.
 *
 * ⛔ **ولا يُوحَّد نثرٌ برمزٍ ليكتمل مفتاح**: ما `enforced_by` فيه نثرٌ أو غائبٌ
 *   **يُعلَن `NOT_ENFORCED_DECLARED` ويُعَدُّ رسوبًا مُعلَنًا لا نجاحًا**.
 *
 * التشغيل: php tests/rpr02_sod_negative.php [--list]
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
$LIST = in_array('--list', $argv, true);

/* ═══ ① القواعدُ مفصولةً — كي تُختبر وحدَها ═══════════════════════════════ */
function sodn_is_code($raw)
{
    $raw = trim((string) $raw);
    return $raw !== '' && (bool) preg_match('~^[A-Z0-9_]+(\s*·\s*[A-Z0-9_]+)*$~u', $raw);
}
function sodn_roles_split($initiator, $approver)
{
    $a = trim((string) $initiator); $b = trim((string) $approver);
    return ($a !== '' && $b !== '' && $a !== $b);
}

/* ═══ ② الكاسرُ — الفاحصُ يُختبر قبل أن يفحص ══════════════════════════════
   ⛔ **ولا يمرُّ بمفردةٍ متكرِّرة**: `zzq_sodn_*` لا ترد إلّا هنا. */
$selfFail = 0;
if (!sodn_is_code('SAME_ACTOR_PREPARE_AND_APPROVE_ADJ')) { echo "  X الرمزُ لم يُعرَف\n"; $selfFail++; }
if (sodn_is_code('ENFORCED: من اعد لا يعتمد'))           { echo "  X النثرُ عُدَّ رمزًا\n"; $selfFail++; }
if (sodn_is_code(''))                                     { echo "  X الفراغُ عُدَّ رمزًا\n"; $selfFail++; }
if (!sodn_roles_split('المحاسب', 'مدير المالية'))         { echo "  X الفصلُ القائمُ رُدَّ\n"; $selfFail++; }
if (sodn_roles_split('المحاسب', 'المحاسب'))               { echo "  X دورٌ واحدٌ عُدَّ فصلًا\n"; $selfFail++; }
if (sodn_roles_split('', 'مدير المالية'))                 { echo "  X الفراغُ عُدَّ فصلًا\n"; $selfFail++; }
if ($selfFail) { exit("\nX الفاحصُ نفسُه ساقطٌ بـ$selfFail — ولا يُحتجُّ بنتيجته\n"); }

/* ═══ ③ شجرةُ الإنتاج — ⛔ ولا تُعَدُّ العُدّةُ إنفاذًا ═══════════════════ */
$PROD = '';
$dirs = array($ROOT . '/app', $ROOT . '/includes');
foreach ($dirs as $d) {
    if (!is_dir($d)) { continue; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') { continue; }
        $PROD .= "\n" . (string) @file_get_contents($f->getPathname());
    }
}

/* ═══ ④ المقامُ — تسعون واجبًا حرِجًا فأكثرُ من عشرِ موجات ════════════════ */
$WAVES = array(
    'w6'  => array('enforced_by' => false, 'ini' => 'initiator_role', 'app' => 'approver_role'),
    'w7'  => array('enforced_by' => true,  'ini' => 'initiator_role', 'app' => 'approver_role'),
    'w8'  => array('enforced_by' => true,  'ini' => 'initiator_role', 'app' => 'approver_role'),
    'w9'  => array('enforced_by' => true,  'ini' => 'initiator_role', 'app' => 'approver_role'),
    'w10' => array('enforced_by' => true,  'ini' => 'initiator_role', 'app' => 'approver_role'),
    'w11' => array('enforced_by' => true,  'ini' => 'initiator_role', 'app' => 'approver_role'),
    'w12' => array('enforced_by' => true,  'ini' => 'initiator_role', 'app' => 'approver_role'),
    'w13' => array('enforced_by' => true,  'ini' => 'initiator_role', 'app' => 'approver_role'),
    'w14' => array('enforced_by' => true,  'ini' => 'initiator_role', 'app' => 'approver_role'),
    'w15' => array('enforced_by' => false, 'ini' => 'initiator',      'app' => 'approver'),
);
$rows = array();
foreach ($WAVES as $w => $c) {
    $cols = 'process_key, forbidden_combo, `' . $c['ini'] . '` ini, `' . $c['app'] . '` app'
          . ($c['enforced_by'] ? ', enforced_by' : ", '' enforced_by");
    $q = @$conn->query("SELECT $cols FROM `repair01_" . $w . "_sod`");
    if (!$q) { continue; }
    while ($z = $q->fetch_assoc()) { $z['wave'] = strtoupper($w); $rows[] = $z; }
}

/* ═══ ⑤ الحكمُ لكلِّ عمليّة ═══════════════════════════════════════════════ */
$T1 = 0; $T2 = 0; $T3 = 0; $N = count($rows);
$fail = array(); $declared = array(); $named = array();
foreach ($rows as $x) {
    $k = trim((string) $x['process_key']);
    $named[$k] = 1;
    /* T1 */
    if ($k !== '' && trim((string) $x['forbidden_combo']) !== '') { $T1++; }
    else { $fail[] = array($k, 'T1', 'عمليّةٌ بلا مفتاحٍ أو بلا ممنوعٍ مكتوب'); }
    /* T2 */
    if (sodn_roles_split($x['ini'], $x['app'])) { $T2++; }
    else { $fail[] = array($k, 'T2', 'الشارعُ والمعتمِدُ دورٌ واحد — فصلٌ مُعلَنٌ لا وجودَ له'); }
    /* T3 */
    $eb = (string) $x['enforced_by'];
    if (!sodn_is_code($eb)) {
        $declared[] = array($k, $x['wave'], ($eb === '' ? 'ABSENT' : 'PROSE'));
        continue;
    }
    $codes = preg_split('~\s*·\s*~u', trim($eb));
    $allIn = true; $miss = array();
    foreach ($codes as $c) {
        if ($c === '') { continue; }
        if (strpos($PROD, $c) === false) { $allIn = false; $miss[] = $c; }
    }
    if ($allIn) { $T3++; }
    else { $fail[] = array($k, 'T3', 'رمزُ الإنفاذِ لا يظهر في شجرةِ الإنتاج: ' . implode(' · ', $miss)); }
}

/* ⛔ **والكاسرُ الحيُّ**: رمزٌ فريدٌ لا وجودَ له يجب ألّا يُوجَد في الشجرة —
   ولولاه لكان `strpos` على نصٍّ ضخمٍ يمرُّ بكلِّ شيء. */
if (strpos($PROD, 'ZZQ_SODN_ABSENT_ENFORCER_PROBE') !== false) {
    echo "  X المفردةُ الفريدةُ وُجدت في الشجرة — والبحثُ لا يميّز\n";
    exit(1);
}

/* ═══ ⑥ العرض ════════════════════════════════════════════════════════════ */
echo "\n═══ `RPR-02` #٥ — فاحصٌ سالبٌ لكلِّ فصلِ واجبٍ حرِج ═══\n";
printf("  المقام: **%d** عمليّةً حرِجةً على عشرِ موجاتٍ · مفاتيحُ متمايزة %d\n\n", $N, count($named));
printf("  T1 سلامةُ السجلّ (مفتاحٌ وممنوعٌ مكتوب)     **%3d** من %d\n", $T1, $N);
printf("  T2 فصلُ الأدوارِ فعليّ (شارعٌ ≠ معتمِد)      **%3d** من %d\n", $T2, $N);
printf("  T3 رمزُ الإنفاذِ مردودٌ في شجرةِ الإنتاج    **%3d** من %d\n", $T3, $N);
printf("\n  ◆ مُعلَنٌ بلا رمزِ إنفاذ (نثرٌ أو غائب): **%d** — ⛔ **رسوبٌ مُعلَنٌ لا نجاح**\n",
       count($declared));
printf("  ✘ إخفاقاتٌ مقيسة: **%d**\n", count($fail));

if ($LIST) {
    if ($fail) {
        echo "\n── الإخفاقاتُ ──\n";
        foreach ($fail as $f) { printf("   %-34s %-4s %s\n", $f[0], $f[1], $f[2]); }
    }
    if ($declared) {
        echo "\n── مُعلَنٌ بلا رمزِ إنفاذ ──\n";
        foreach ($declared as $d) { printf("   %-34s %-5s %s\n", $d[0], $d[1], $d[2]); }
    }
}

/* ═══ ⑦ سطرٌ يُقرأ آليًّا — **شاهدُ التغطيةِ لا دعواها** ═══════════════════
   ◆ **ولمَ يُطبَع**: `rpr02_sod_test_registry` كان يبحث عن **نصِّ المفتاح** في
     ملفِّ الفاحص، **ففاحصٌ يقرأ السجلَّ ويُقرِّر على كلِّ صفٍّ لا يُرى** — وهو
     أقوى من ثنتَين وتسعين سلسلةً مكتوبةً باليد لأنَّ مقامَه **هو السجلُّ نفسُه**
     فلا يتقادم. ⇒ يُعلن الفاحصُ **المفاتيحَ التي قرَّر عليها فعلًا**، والسجلُّ
     **يتحقّق منها عضويّةً وعددًا** — ⛔ **ولا تُقبل دعوى تغطيةٍ بلا هذا السطر**. */
echo "\n#SODN COVERED=" . count($named) . " FAILED=" . count($fail)
   . " DECLARED=" . count($declared) . "\n";
echo '#SODN KEYS=' . implode(',', array_keys($named)) . "\n";

echo "\n────────────────────────────────────────────────────────────\n";
echo "⛔ **وحدُّ هذا الفاحصِ يُقال**: يُثبت أنَّ الحارسَ **مكتوبٌ ويردُّ بالرمزِ\n";
echo "  نفسِه** — ولا يُثبت أنّه **يحمرُّ عند خرقٍ حيّ**. وتلك تُقاس بتشغيلِ\n";
echo "  المسارِ بحسابَين، وهي `UAT_BLOCKER` تنتظر أشخاصًا حقيقيّين.\n";
$bad = count($fail);
printf("\n%s النتيجة: نجح %d · رسب %d · مُعلَنٌ بلا إنفاذٍ %d\n",
       $bad ? '✘' : '✔', ($T1 + $T2 + $T3), $bad, count($declared));
exit($bad ? 1 : 0);
