<?php
/**
 * tests/injfrd66_xc12_boundaries_test.php — شاهدُ XC-12: حدودُ الماليةِ والخزينة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «صفرُ شاشةٍ ماليةٍ أصليةٍ في الإدارتَين · وكلُّ إسقاطٍ موسوم».
 *
 * ◆ **والشقّانِ يُقاسان منفصلَين**:
 *   ① **الأصالة** = **الكتابة** لا القراءة. سطحٌ يقرأ جدولًا ماليًّا إسقاطٌ،
 *      وسطحٌ **يكتب** فيه شاشةٌ ماليةٌ أصليةٌ في غيرِ دارِها. فالمقياسُ
 *      `INSERT`/`UPDATE`/`DELETE` على `fin_*`/`bank_*`.
 *   ② **الوسم** = `nature = 'إسقاطُ قراءة'` مع مالكٍ مسمًّى في السجلِّ المعتمَد.
 *
 * ◆ **والمالكُ يُشتقُّ ممّا يُقرأ لا ممّا كُتب في الحقل**: `collections.php`
 *   يقرأ `fin_payments` ومالكُه المسجَّلُ كان «المبيعات والعقود» — فصُحِّح.
 *
 * ◆ **وما ليس إسقاطًا لا يُوسَم**: `contract_payment_schedule.php` يقرأ
 *   `contracts` لا جدولًا ماليًّا — شرطُ عقدٍ تملكه المبيعات. ووسمُه إسقاطًا
 *   يُخرجه من يدِ مالكِه بلا سبب، وذلك خطأٌ في الاتجاهِ المعاكس.
 *
 * ◆ **سالبٌ**: الفاحصُ يرصد كتابةً ماليةً مزروعةً ثم تُزال — فصفرُه رؤيةٌ لا عمًى.
 *
 * التشغيل: php tests/injfrd66_xc12_boundaries_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$pass = 0; $fail = 0;
$check = static function (bool $ok, string $msg) use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "   ✔ {$msg}\n"; } else { $fail++; echo "   ✘ {$msg}\n"; }
};
$num = static function (string $sql) use ($conn): int {
    $r = @mysqli_query($conn, $sql);
    return $r ? (int) mysqli_fetch_row($r)[0] : -1;
};

$DIRS   = array('Clients', 'Contracts', 'Suppliers', 'Opportunities', 'Projects');
$WRITE  = '~(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?(fin_[a-z_]+|bank_[a-z_]+)`?~i';
$scan   = static function () use ($ROOT, $DIRS, $WRITE): array {
    $hits = array();
    foreach ($DIRS as $d) {
        foreach ((array) glob($ROOT . '/' . $d . '/*.php') as $f) {
            $body = (string) @file_get_contents($f);
            if ($body !== '' && preg_match($WRITE, $body)) { $hits[] = $d . '/' . basename($f); }
        }
    }
    return $hits;
};

echo "① إيجابيٌّ — صفرُ شاشةٍ ماليةٍ أصليةٍ في الإدارتَين:\n";
$w = $scan();
$check(empty($w), sprintf('صفرُ كتابةٍ في `fin_*`/`bank_*` من %d دليلًا', count($DIRS)));
foreach ($w as $x) { echo "      ✘ {$x}\n"; }

echo "\n② سالبٌ — الفاحصُ يرصد كتابةً ماليةً مزروعة:\n";
$probe = $ROOT . '/Contracts/zz_injfrd66_fin_probe.php';
@file_put_contents($probe, "<?php\n/* مسبارٌ — يُزال فورَ القياس */\n\$x = \"UPDATE fin_payments SET amount = 1\";\n");
if (!is_file($probe)) { $fail++; echo "   ✘ تعذّر الزرع\n"; }
else {
    $w2 = $scan();
    @unlink($probe);
    $check(in_array('Contracts/zz_injfrd66_fin_probe.php', $w2, true), 'رُصدت الكتابةُ المزروعةُ باسمِها');
    $check(!is_file($probe), 'أُزيل المسبارُ بعدَ القياس');
    $check(empty($scan()), 'وعاد المسحُ صفرًا');
}

echo "\n③ إيجابيٌّ — كلُّ إسقاطٍ موسومٌ بمالكِه:\n";
$PROJ = array('Contracts/collections.php', 'Contracts/tax_invoices.php');
foreach ($PROJ as $r) {
    $q = @mysqli_query($conn, "SELECT IFNULL(nature,'') nat, IFNULL(owner_dept,'') own
                                 FROM nav_canonical WHERE LOWER(route)=LOWER('" . mysqli_real_escape_string($conn, $r) . "')");
    $x = $q ? mysqli_fetch_assoc($q) : null;
    $ok = $x && $x['nat'] === 'إسقاطُ قراءة' && $x['own'] !== '' && $x['own'] !== '0';
    $check((bool) $ok, sprintf('%-40s «%s» · مالكُه «%s»', $r, $x['nat'] ?? '—', $x['own'] ?? '—'));
}

echo "\n④ سالبٌ — ما ليس إسقاطًا لم يُوسَم:\n";
$q = @mysqli_query($conn, "SELECT IFNULL(nature,'') nat, IFNULL(owner_dept,'') own FROM nav_canonical
                            WHERE LOWER(route)='contracts/contract_payment_schedule.php'");
$x = $q ? mysqli_fetch_assoc($q) : null;
$check($x !== null && $x['nat'] !== 'إسقاطُ قراءة',
    sprintf('خطةُ دفعِ العقدِ لم تُوسَم إسقاطًا (nature=«%s» · مالكُه «%s») — تقرأ `contracts` لا جدولًا ماليًّا',
        $x['nat'] ?? '—', $x['own'] ?? '—'));

echo "\n⑤ إيجابيٌّ — SUP-27 «صفر إدخالٍ في مصادر القدرة»:\n";
$cap  = (string) @file_get_contents($ROOT . '/Suppliers/supplier_capacity.php');
$caps = preg_match('~(INSERT\s+INTO|UPDATE\s+`?[a-z_]+`?\s+SET|DELETE\s+FROM)~i', $cap);
$check($caps === 0, 'supplier_capacity.php قارئةٌ محضةٌ — صفرُ فعلٍ كاتب');

echo "\n⑥ إيجابيٌّ — الإسقاطاتُ تقرأ ولا تكتب:\n";
foreach ($PROJ as $r) {
    $body = (string) @file_get_contents($ROOT . '/' . $r);
    $wr = preg_match('~(INSERT\s+INTO|UPDATE\s+`?[a-z_]+`?\s+SET|DELETE\s+FROM)~i', $body);
    $check($wr === 0, sprintf('%-40s صفرُ فعلٍ كاتب', $r));
}

printf("\n%s  ناجح %d · راسب %d\n", $fail === 0 ? '✔ XC-12' : '✘ XC-12', $pass, $fail);
exit($fail === 0 ? 0 : 1);
