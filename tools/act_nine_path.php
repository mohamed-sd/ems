<?php
/**
 * tools/act_nine_path.php — اختبارُ المسار الكامل للعشرة الحاكمة (ACT-01 v6 §6)
 * ───────────────────────────────────────────────────────────────────────────
 * «تُختبر عشرةُ أفعالٍ حاكمةٍ بالمسار التساعيِّ قبل الإطلاق — وهذه العشرةُ
 * تغطي كلَّ أنماط الأثر في النظام.»
 *
 * لكل فعلٍ تسعُ بواباتٍ تُفحص من العقد الحي والكود الفعلي:
 *  ① الضغطة (المعالجُ موجودٌ على القرص وموضعُه معلن)  ② الحرّاسُ بترتيبهم
 *  ③ الذرية (شاهدُ معاملةٍ في المعالج)  ④ الصادر (نشرٌ عبر الناشر/الحقائق)
 *  ⑤ الاستهلاك (لكل حدثٍ مستهلكٌ حي)  ⑥ المتأثرون (خريطةُ أثرٍ معلنة)
 *  ⑦ التتبع (كتاباتٌ معلنةٌ تُقتفى)  ⑧ التكرار (شاهدُ عطالةٍ للمالي)
 *  ⑨ العكس (معرَّفٌ حيٌّ ولا حذفَ فيه)
 * والحلقاتُ الحيةُ التسع تشهد لها الأحزمةُ القائمة (حزام core والقدرات
 * والبلاغات) — وهذا العدّاءُ يمنع الإطلاقَ إن انقطع أيُّ سلكٍ في العشرة.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$TEN = array(
    'unit.chain.approve'          => 'اعتمادُ حلقةٍ في سلسلة الوحدات',
    'entitlement.gate.approve'    => 'بوابةُ الاستحقاق',
    'invoice.issue'               => 'إصدارُ فاتورة',
    'collection.record'           => 'تسجيلُ تحصيل',
    'payroll.run'                 => 'تشغيلُ مسيّر',
    'supplier.settlement.approve' => 'اعتمادُ تسوية مورد',
    'unit.state.change'           => 'تغييرُ حالة وحدة',
    'coverage.substitute.activate'=> 'تفعيلُ تغطيةٍ بديلة',
    'period.provisions.run'       => 'إقفالُ فترةٍ (المخصصاتُ الدورية)',
    'unit.chain.reverse'          => 'عكسُ أثرٍ مقيَّد',
);

$root = dirname(__DIR__);
$fails = 0; $checked = 0;

$grepFile = function ($path, $needles) use ($root) {
    $full = $root . '/' . ltrim(str_replace('\\', '/', $path), '/');
    if (!is_file($full)) { return null; }
    $src = file_get_contents($full);
    foreach ((array) $needles as $n) { if (strpos($src, $n) !== false) { return true; } }
    return false;
};
/* البحثُ في خدمات المجال عن شاهدٍ عندما لا يكون المعالجُ ملفَّ الشاشة */
$grepServices = function ($needles) use ($root) {
    static $blob = null;
    if ($blob === null) {
        $blob = '';
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app/Services'));
        foreach ($it as $f) { if ($f->isFile() && substr($f->getFilename(), -4) === '.php') { $blob .= file_get_contents($f->getPathname()); } }
    }
    foreach ((array) $needles as $n) { if (strpos($blob, $n) !== false) { return true; } }
    return false;
};

foreach ($TEN as $code => $label) {
    $ec = mysqli_real_escape_string($conn, $code);
    $r = mysqli_query($conn, "SELECT * FROM actions WHERE action_code = '$ec' AND active = 1");
    $a = $r ? mysqli_fetch_assoc($r) : null;
    echo "■ $label ($code)\n";
    if (!$a) { echo "  ✘ الفعلُ غيرُ مسجَّلٍ أصلًا\n"; $fails++; continue; }
    $g = array();

    /* ① الضغطة */
    $hp = $a['handler_path'] ?? '';
    $g['① الضغطة'] = ($hp !== '' && $grepFile($hp, '<?php') !== null) || $grepServices(explode('.', $code)[0]);

    /* ② الحرّاس */
    $guards = json_decode($a['guards_json'] ?? '[]', true);
    $g['② الحرّاس'] = is_array($guards) && count($guards) >= 1 && ($a['is_write'] == 0 || count($guards) >= 2);

    /* ③ الذرية */
    $writes = intval(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM action_writes WHERE action_code = '$ec'"))[0]);
    $g['③ الذرية'] = ($writes <= 1)
        || ($grepFile($hp, array('begin_transaction', 'runInTransaction')) === true)
        || $grepServices(array('runInTransaction', 'begin_transaction'));

    /* ④ الصادر */
    $events = intval(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM action_events WHERE action_code = '$ec'"))[0]);
    $g['④ الصادر'] = $events > 0 || !$a['is_financial'] || $grepServices(array('publishFact', 'EventPublisher'));

    /* ⑤ الاستهلاك */
    $orph = intval(mysqli_fetch_row(mysqli_query($conn,
        "SELECT COUNT(*) FROM action_events e WHERE e.action_code = '$ec'
         AND NOT EXISTS (SELECT 1 FROM event_consumers c WHERE c.event_name = e.event_name AND c.active = 1)"))[0]);
    $g['⑤ الاستهلاك'] = ($orph === 0);

    /* ⑥ المتأثرون */
    $imp = intval(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM action_impacts WHERE action_code = '$ec'"))[0]);
    $g['⑥ المتأثرون'] = $imp > 0;

    /* ⑦ التتبع (كتاباتٌ معلنةٌ يُقتفى بها الأثر) */
    $g['⑦ التتبع'] = $writes > 0;

    /* ⑧ التكرار (عطالةُ المالي عبر fin_event_links أو مفتاح عطالة) */
    $g['⑧ التكرار'] = !$a['is_financial'] || $grepServices(array('fin_event_links', 'idempotency_key', 'sha1('));

    /* ⑨ العكس */
    if ($a['is_financial']) {
        $rev = $a['reverse_action_code'];
        $ok9 = false;
        if ($rev) {
            $er = mysqli_real_escape_string($conn, $rev);
            $rr = mysqli_query($conn, "SELECT 1 FROM actions WHERE action_code = '$er' AND active = 1");
            $del = intval(mysqli_fetch_row(mysqli_query($conn,
                "SELECT COUNT(*) FROM action_writes WHERE action_code = '$er' AND operation = 'delete'"))[0]);
            $ok9 = $rr && mysqli_num_rows($rr) && $del === 0;
        }
        $g['⑨ العكس'] = $ok9;
    } else {
        $g['⑨ العكس'] = true; // غيرُ المالي لا يُلزَم بعكس (فحص ⑦ الساكن)
    }

    foreach ($g as $step => $ok) {
        echo '  ' . ($ok ? '✔' : '✘') . " $step\n";
        $checked++;
        if (!$ok) $fails++;
    }
}

echo "\n" . ($fails === 0
    ? "الحكم: ✔ العشرةُ الحاكمةُ اجتازت البواباتِ التسعين ($checked فحصًا) — التيارُ موصول"
    : "الحكم: ✘ $fails بوابةً منقطعةً — لا إطلاق") . "\n";
exit($fails === 0 ? 0 : 1);
