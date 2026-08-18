<?php
/** أساسُ اشتقاقِ السقوفِ الثلاثة — للمالكِ (قرارُ ⑥ 2026-08-18): توزيعُ المبالغِ ونسبةُ ما يقع تحتَ كلِّ سقف */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? $r->fetch_row()[0] : null; };

$n = (int) $one("SELECT COUNT(*) FROM fin_financial_events WHERE amount > 0");
$usd = (int) $one("SELECT COUNT(*) FROM fin_financial_events WHERE amount > 0 AND currency='USD'");
echo "═══ أساسُ الاشتقاق — {$n} واقعةً ماليةً موجبةً (منها {$usd} بالدولار) ═══\n\n";

echo "① توزيعُ مبالغِ الواقعةِ الواحدة (USD):\n";
$rows = array(array('الشريحة', 'العدد', 'النسبة', 'التراكمية'));
$bands = array(array(0,100),array(100,250),array(250,500),array(500,1000),array(1000,2000),
               array(2000,5000),array(5000,10000),array(10000,50000),array(50000,null));
$cum = 0;
foreach ($bands as $b) {
    $w = "amount > {$b[0]}" . ($b[1] !== null ? " AND amount <= {$b[1]}" : '');
    $c = (int) $one("SELECT COUNT(*) FROM fin_financial_events WHERE currency='USD' AND $w");
    $cum += $c;
    $lbl = $b[1] !== null ? "{$b[0]}–{$b[1]}" : "فوقَ {$b[0]}";
    printf("   %-14s %6d   %5.1f%%   تراكميًّا %5.1f%%\n", $lbl, $c, $c*100.0/$usd, $cum*100.0/$usd);
    $rows[] = array($lbl, $c, round($c*100.0/$usd,1).'٪', round($cum*100.0/$usd,1).'٪');
}

echo "\n② المئيناتُ الحاكمة (USD · الواقعةُ الواحدة):\n";
foreach (array(50,80,90,95,99) as $p) {
    $v = $one("SELECT amount FROM (SELECT amount, ROW_NUMBER() OVER (ORDER BY amount) rn, COUNT(*) OVER () c
                 FROM fin_financial_events WHERE amount>0 AND currency='USD') t WHERE rn=ROUND(c*{$p}/100)");
    printf("   P%-3d = %10.2f\n", $p, (float) $v);
}

echo "\n③ نسبةُ ما يقع تحتَ كلِّ سقفٍ معتمَد:\n";
foreach (array('LD-05 الاعتمادُ الأوليّ' => 2000, 'LD-06 الفاتورة' => 5000, 'LD-07 النهائيّ' => 10000) as $lbl => $cap) {
    $under = (int) $one("SELECT COUNT(*) FROM fin_financial_events WHERE currency='USD' AND amount > 0 AND amount <= {$cap}");
    printf("   %-28s سقفُه %6d ⇐ يمرُّ دونَه %5.1f%% ويصعَّد فوقَه %4.1f%%\n",
        $lbl, $cap, $under*100.0/$usd, 100 - $under*100.0/$usd);
}

echo "\n④ المجاميعُ الشهريةُ للطرفِ الواحد (مستوى الفاتورة/التسوية):\n";
foreach (array(80,90,95,99) as $p) {
    $v = $one("SELECT s FROM (SELECT SUM(amount) s, ROW_NUMBER() OVER (ORDER BY SUM(amount)) rn, COUNT(*) OVER () c
                 FROM fin_financial_events WHERE amount>0 AND currency='USD'
                 GROUP BY entity_type, entity_id, DATE_FORMAT(created_at,'%Y-%m')) t WHERE rn=ROUND(c*{$p}/100)");
    printf("   P%-3d = %10.2f\n", $p, (float) $v);
}
$mx = $one("SELECT MAX(s) FROM (SELECT SUM(amount) s FROM fin_financial_events
             WHERE amount>0 AND currency='USD' GROUP BY entity_type, entity_id, DATE_FORMAT(created_at,'%Y-%m')) t");
printf("   الأقصى = %10.2f\n", (float) $mx);

$csv = "\xEF\xBB\xBF" . implode("\n", array_map(function ($r) { return implode(',', $r); }, $rows));
file_put_contents(dirname(__DIR__) . '/storage/reports/caps_derivation_basis.csv', $csv);
echo "\n⇒ storage/reports/caps_derivation_basis.csv\n";
