<?php
/**
 * se00_name_match.php — الشرطُ المسبقُ TS-01: مطابقةُ الأسماءِ المقترحةِ بالمخططِ الحيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * قراءةٌ فقط. لكلِّ اسمٍ مقترحٍ في المواصفةِ 70 نبحث عن نظيرٍ حيٍّ بطريقتين:
 *   ① بالاسم    : information_schema.TABLES ... LIKE '%نمط%'
 *   ② بالبصمة   : جداولُ تحمل أعمدةً دالّةً على المفهومِ نفسِه (توقيعُ الأعمدة)
 * والحكم: EXISTS (نفسُ الاسم) · EQUIVALENT (نظيرٌ باسمٍ آخر ⇒ تعديلٌ لا إنشاء) ·
 *         NEW (لا نظيرَ ⇒ يُنشأ) · AMBIGUOUS (أكثرُ من مرشَّحٍ ⇒ قرارُ مالك).
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$db = @mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if (!$db) { fwrite(STDERR, "فشل الاتصال\n"); exit(2); }
$db->set_charset('utf8mb4');
$SC = 'equipation_manage';

/* خريطةُ المخططِ الحيِّ كاملةً */
$cols = []; $rows = [];
$r = $db->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$SC'");
while ($x = $r->fetch_row()) { $cols[$x[0]][$x[1]] = true; }
$r = $db->query("SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA='$SC' AND TABLE_TYPE='BASE TABLE'");
while ($x = $r->fetch_row()) { $rows[$x[0]] = (int) $x[1]; }

function liveCount(mysqli $db, string $t): int {
    $q = $db->query("SELECT COUNT(*) FROM `$t`");
    return $q ? (int) $q->fetch_row()[0] : -1;
}

/* المفاهيمُ الثمانيةُ من المواصفة 70 */
$CONCEPTS = [
    [
        'proposed' => 'shift_entries',
        'ar' => 'القيدُ اليوميُّ للوردية — سطرٌ لكلِّ آليةٍ في كلِّ ورديةٍ في كلِّ يوم',
        'like' => ['%shift%', '%timesheet%', '%daily%', '%unit_entr%', '%time_log%'],
        /* بصمةُ المفهوم: ساعاتُ تشغيلٍ + تاريخٌ + وردية */
        'signature' => [['run_hours', 'standby_hours', 'breakdown_hours'], ['shift', 'date'], ['executed_hours', 'shift_hours']],
    ],
    [
        'proposed' => 'sup_handover_events',
        'ar' => 'أحداثُ تسليمِ الحصصِ بين الموردين — دخولٌ وخروجٌ مؤرَّخ',
        'like' => ['%handover%', '%swap%', '%transfer_event%', '%container%'],
        'signature' => [['event_type', 'supplier_id'], ['container_key'], ['counterpart_supplier_id']],
    ],
    [
        'proposed' => 'contract_amendments',
        'ar' => 'ملاحقُ العقودِ وتجديداتُها — «الحاويةُ السنوية»',
        'like' => ['%amendment%', '%renewal%'],
        'signature' => [['contract_id', 'amendment_no'], ['contract_id', 'effective_from']],
    ],
    [
        'proposed' => 'coverage',
        'ar' => 'التزامُ العقدِ بنوعِ المعدة — «حاويةُ النوع» · فيه إجماليُّ الساعاتِ الشهرية',
        'like' => ['%coverage%', '%commitment%', '%monthly_plan%', '%resource_plan%'],
        'signature' => [['machines_count', 'monthly_hours'], ['contract_id', 'equipment_type'], ['hours_monthly_target']],
    ],
    [
        'proposed' => 'units',
        'ar' => 'الوحداتُ التعاقديةُ المرقَّمة — «خانةُ الآلية» · فيها المعدةُ المسنَدةُ حاليًّا',
        'like' => ['unit%', '%_unit', '%_units'],
        'signature' => [['contract_id', 'equipment_id'], ['unit_no'], ['assigned_equipment_id']],
    ],
    [
        'proposed' => 'supplier_quota',
        'ar' => 'حصصُ الموردينَ من العقود — «إسنادُ الخانة» · فيها المتبقي من حصةِ المبيعات',
        'like' => ['%quota%', '%supplier_cap%', '%capacity%'],
        'signature' => [['supplier_id', 'contract_id'], ['supplier_entity_id', 'hours'], ['remaining']],
    ],
    [
        'proposed' => 'supplier_settle',
        'ar' => 'تسوياتُ الموردين',
        'like' => ['%settle%'],
        'signature' => [['supplier_id', 'amount'], ['settlement_no']],
    ],
    [
        'proposed' => 'quota_ledger',
        'ar' => 'دفترُ استهلاكِ الحصة',
        'like' => ['%ledger%', '%consumption%'],
        'signature' => [['consumed', 'remaining'], ['obligation_id']],
    ],
];

$out = [];
echo "══════════════════════════════════════════════════════════════════════\n";
echo "  TS-01 · جدولُ الفروقِ بين أسماءِ المواصفة 70 والمخططِ الحيِّ (555 جدولًا)\n";
echo "══════════════════════════════════════════════════════════════════════\n";

foreach ($CONCEPTS as $c) {
    $p = $c['proposed'];
    $exact = isset($cols[$p]);

    /* ① بحثُ الاسم */
    $byName = [];
    foreach ($c['like'] as $pat) {
        $q = $db->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE' AND TABLE_NAME LIKE ?");
        $q->bind_param('ss', $SC, $pat);
        $q->execute();
        $res = $q->get_result();
        while ($x = $res->fetch_row()) { $byName[$x[0]] = true; }
        $q->close();
    }

    /* ② بحثُ البصمة */
    $bySig = [];
    foreach ($c['signature'] as $sig) {
        foreach ($cols as $t => $cc) {
            $hit = 0;
            foreach ($sig as $needle) { if (isset($cc[$needle])) { $hit++; } }
            if ($hit === count($sig)) { $bySig[$t] = ($bySig[$t] ?? 0) + $hit; }
        }
    }
    arsort($bySig);

    $cand = array_unique(array_merge(array_keys($byName), array_keys($bySig)));
    /* رتِّب: مطابقةُ البصمةِ أولًا ثم عددُ الصفوف */
    usort($cand, function ($a, $b) use ($bySig, $rows) {
        $d = ($bySig[$b] ?? 0) <=> ($bySig[$a] ?? 0);
        return $d !== 0 ? $d : (($rows[$b] ?? 0) <=> ($rows[$a] ?? 0));
    });

    $verdict = $exact ? 'EXISTS' : (count($bySig) ? 'EQUIVALENT?' : (count($byName) ? 'NAME_ONLY?' : 'NEW'));

    printf("\n▐ %-22s %s\n", $p, $exact ? '✔ موجودٌ بالاسمِ نفسِه' : '✖ لا وجودَ لهذا الاسم');
    printf("   المفهوم: %s\n", $c['ar']);
    printf("   الحكمُ المبدئي: %s · مرشَّحون: %d\n", $verdict, count($cand));
    $shown = 0;
    foreach ($cand as $t) {
        if ($shown >= 6) { break; }
        $n = liveCount($db, $t);
        printf("     %-34s صفوف=%-8s %s%s\n", $t, $n < 0 ? '?' : number_format($n),
            isset($bySig[$t]) ? 'بصمةٌ مطابقة' : 'بالاسمِ فقط',
            $t === $p ? '  ← نفسُ الاسم' : '');
        $shown++;
    }
    $out[$p] = ['verdict' => $verdict, 'exact' => $exact, 'candidates' => array_slice($cand, 0, 8),
                'by_signature' => array_slice(array_keys($bySig), 0, 8)];
}

file_put_contents(__DIR__ . '/../docs/reverse_audit_2026-08/evidence/se00_name_match.json',
    json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\n\nكُتب: docs/reverse_audit_2026-08/evidence/se00_name_match.json\n";
