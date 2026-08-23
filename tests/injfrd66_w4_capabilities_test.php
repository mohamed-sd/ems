<?php
/**
 * tests/injfrd66_w4_capabilities_test.php — شاهدُ الموجةِ ④: القدراتُ المستجدّة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الوثيقةُ تعدُّ أربعًا «تُبنى أولَ مرة» — والقياسُ يجد ثلاثًا مبنيّة.**
 *   وهذا الشاهدُ يُثبت البناءَ بجداولِه وصفوفِه، ويُثبت الغيابَ حيث هو غياب.
 *
 * ◆ **إيجابيٌّ ①**: ثلاثُ قدراتٍ لها جداولُها وصفوفُها الحقيقية.
 * ◆ **إيجابيٌّ ②**: معياراهما المرجعيّانِ أخضران (إرساءٌ بعرضٍ · إقفالٌ بعقد).
 * ◆ **سالبٌ ③**: `SUP-22` **غائبةٌ فعلًا** — والبوابةُ تُعلنها «غيرَ مبنيّة»
 *   لا «صفرَ خرق». وجدولٌ مفقودٌ لا يُقاس ولا يُحسب نجاحًا.
 * ◆ **سالبٌ ④**: الحالةُ تُقرأ لا تُعمَّم — `called` و`draft` ليستا مستندًا
 *   حيًّا منتهيًا، وتعميمُهما يُضخّم الرقمَ ثمانيةَ أضعافٍ ويُغرق الخرقَ فيه.
 * ◆ **محجوزٌ ⑤**: ضمانٌ حيٌّ مضى تاريخُه — مبلغٌ محتجَزٌ بلا إفراجٍ ولا تمديد.
 *   وتغييرُ حالتِه قرارٌ ماليٌّ لا هجرةُ بيانات.
 *
 * التشغيل: php tests/injfrd66_w4_capabilities_test.php
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

$pass = 0; $fail = 0; $held = 0;
$check = static function (bool $ok, string $msg) use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "   ✔ {$msg}\n"; } else { $fail++; echo "   ✘ {$msg}\n"; }
};
$num = static function (string $sql) use ($conn): int {
    $r = @mysqli_query($conn, $sql);
    return $r ? (int) mysqli_fetch_row($r)[0] : -1;
};
$tbl = static function (string $t) use ($conn): bool {
    $r = @mysqli_query($conn, "SELECT 1 FROM information_schema.TABLES
                                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='"
                                . mysqli_real_escape_string($conn, $t) . "'");
    return (bool) ($r && mysqli_num_rows($r));
};

echo "① إيجابيٌّ — ثلاثُ قدراتٍ «تُبنى أولَ مرة» مبنيّةٌ سلفًا:\n";
$check($tbl('rfq_quotes') && $num("SELECT COUNT(*) FROM rfq_quotes") > 0,
    'SUP-07 عروضُ الموردين — ' . $num("SELECT COUNT(*) FROM rfq_quotes") . ' عرضًا · '
    . $num("SELECT COUNT(*) FROM rfq_lines") . ' بندًا · ' . $num("SELECT COUNT(*) FROM rfq_awards") . ' إرساءً');
$check($tbl('supplier_contract_closures') && $num("SELECT COUNT(*) FROM supplier_contract_closures") > 0,
    'SUP-16 الإقفالُ التعاقديّ — ' . $num("SELECT COUNT(*) FROM supplier_contract_closures") . ' إقفالًا · '
    . $num("SELECT COUNT(*) FROM seat_assignments") . ' إسنادَ خانة');
$check($tbl('contract_guarantees') && $num("SELECT COUNT(*) FROM contract_guarantees") > 0,
    'SUP-26 المستنداتُ والضمانات — ' . $num("SELECT COUNT(*) FROM contract_guarantees") . ' ضمانًا · '
    . $num("SELECT COUNT(*) FROM gov_doc_registry") . ' مستندًا');

echo "\n② إيجابيٌّ — المعياران المرجعيّان:\n";
$check($num("SELECT COUNT(*) FROM rfq_awards a WHERE a.quote_id IS NULL OR a.quote_id=0
              OR NOT EXISTS (SELECT 1 FROM rfq_quotes q WHERE q.id=a.quote_id)") === 0,
    'SUP-07 صفرُ إرساءٍ بلا عرضٍ مرجعيّ');
$check($num("SELECT COUNT(*) FROM supplier_contract_closures c WHERE c.is_deleted=0
              AND (c.contract_id IS NULL OR c.contract_id=0
                   OR NOT EXISTS (SELECT 1 FROM supplier_contracts s WHERE s.id=c.contract_id))") === 0,
    'SUP-16 صفرُ إقفالٍ بلا عقدٍ مرجعيّ');

echo "\n③ سالبٌ — الغائبُ يُعلَن غيابًا لا صفرَ خرق:\n";
$check(!$tbl('sup_balance_aging'), 'SUP-22 أعمارُ الأرصدة — `sup_balance_aging` غيرُ موجودٍ فعلًا');
$out = array(); $rc = 0;
exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/injfrd66_w4_capabilities_gate.php') . ' 2>&1', $out, $rc);
$txt = implode("\n", $out);
$check(mb_strpos($txt, 'غيرُ مبنيّة') !== false, 'والبوابةُ تُعلنها «غيرَ مبنيّة» لا خضراء');

echo "\n④ سالبٌ — الحالةُ تُقرأ لا تُعمَّم:\n";
$W = "is_deleted=0 AND expiry_date IS NOT NULL AND expiry_date < CURDATE()";
$broad  = $num("SELECT COUNT(*) FROM contract_guarantees WHERE {$W}
                 AND (state IS NULL OR state='' OR state NOT IN ('منتهٍ','مُفرَج','ملغى','released','expired'))");
$narrow = $num("SELECT COUNT(*) FROM contract_guarantees WHERE {$W} AND state='active'");
$called = $num("SELECT COUNT(*) FROM contract_guarantees WHERE {$W} AND state='called'");
$draft  = $num("SELECT COUNT(*) FROM contract_guarantees WHERE {$W} AND state='draft'");
$check($broad === $narrow + $called + $draft,
    sprintf('العامُّ %d = الحيُّ %d + المُصادَرُ %d + المسوَّدةُ %d', $broad, $narrow, $called, $draft));
$check($narrow < $broad,
    sprintf('والتعميمُ يُضخّم %d ضِعفًا — فالمُصادَرُ انتهت دورتُه والمسوَّدةُ لم تُصدَر',
        $narrow > 0 ? (int) round($broad / $narrow) : $broad));

echo "\n⑤ محجوزٌ بسببٍ مكتوب:\n";
if ($narrow > 0) {
    $held++;
    printf("   ⏸ SUP-26 «صفرُ مستندٍ منتهٍ بلا تنبيه» — %d ضمانًا حيًّا مضى تاريخُه:\n", $narrow);
    $r = @mysqli_query($conn, "SELECT id, contract_id, kind, amount, currency, expiry_date,
                                      DATEDIFF(CURDATE(), expiry_date) d
                                 FROM contract_guarantees WHERE {$W} AND state='active'");
    while ($r && ($x = mysqli_fetch_assoc($r))) {
        printf("      · #%s عقد %s · %s %s %s · انتهى %s (منذ %s يومًا)\n",
            $x['id'], $x['contract_id'], $x['kind'], $x['amount'], $x['currency'], $x['expiry_date'], $x['d']);
    }
    echo "      مبلغٌ محتجَزٌ بلا إفراجٍ ولا تمديد. وتغييرُ الحالةِ **قرارٌ ماليّ**\n";
    echo "      (إفراجٌ أم تمديدٌ أم مصادرة؟) لا هجرةُ بيانات — فيُحجَز ويُعلَن.\n";
} else { $pass++; echo "   ✔ صفرُ ضمانٍ حيٍّ مضى تاريخُه\n"; }

printf("\n%s  ناجح %d · راسب %d · محجوز %d\n",
    $fail === 0 ? '✔ الموجة ④' : '✘ الموجة ④', $pass, $fail, $held);
exit($fail === 0 ? 0 : 1);
