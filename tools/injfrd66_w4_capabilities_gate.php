<?php
/**
 * tools/injfrd66_w4_capabilities_gate.php — بوابةُ الموجةِ ④: القدراتُ المستجدّة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الوثيقةُ تعدُّ أربعًا «تُبنى أولَ مرة»** (SUP-07 · SUP-16 · SUP-22 · SUP-26)
 *   — **والقياسُ يجد ثلاثًا منها مبنيّةً بجداولِها وصفوفِها الحقيقية**، وواحدةً
 *   غائبةً فعلًا. وهو النمطُ نفسُه الذي تكرَّر في XC-05 وSUP-28 وXC-09 وXC-07:
 *   **الوثيقةُ تصف غيابًا حيث القياسُ يجد بناءً لم يُمارَس.**
 *
 * ◆ **و«مبنيٌّ» ليس «مقبولًا»**: لكلِّ قدرةٍ معيارُ قبولٍ مكتوبٌ يُقاس على
 *   بياناتِها الحيّة — والجدولُ القائمُ بصفٍّ أو صفَّين لا يُغلق معيارًا يقول
 *   «كلُّ عقدٍ» أو «كلُّ رصيد».
 *
 * ◆ **والجدولُ الفارغُ لا يُثبت شيئًا**: «صفرُ مخالفةٍ» في جدولٍ بلا صفوفٍ
 *   عدمُ ممارسةٍ لا صحّةُ حارس — فيُعلَن «لا صفوفَ تُقاس».
 *
 * ◆ قراءةٌ خالصة — لا كتابةَ في القاعدةِ إطلاقًا.
 *
 * التشغيل:
 *   php tools/injfrd66_w4_capabilities_gate.php          التقرير
 *   php tools/injfrd66_w4_capabilities_gate.php --gate   رمزُ خروجٍ 1 عند خرقٍ مقيس
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

$GATE = in_array('--gate', $argv, true);

$has = static function (string $t) use ($conn): bool {
    $r = @mysqli_query($conn, "SELECT 1 FROM information_schema.TABLES
                                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='"
                                . mysqli_real_escape_string($conn, $t) . "'");
    return (bool) ($r && mysqli_num_rows($r));
};
$num = static function (string $sql) use ($conn): int {
    $r = @mysqli_query($conn, $sql);
    return $r ? (int) mysqli_fetch_row($r)[0] : -1;
};

$green = 0; $red = 0; $unbuilt = 0; $unpracticed = 0;

/**
 * @param array  $need   جداولُ القدرة — غيابُ أحدِها ⇐ غيرُ مبنيّة
 * @param string $rows   جدولٌ يجب أن يحمل صفوفًا ليُقاس المعيار
 */
$cap = static function (string $req, string $title, array $need, string $rows, string $crit,
                        ?string $sql, int $want)
        use ($conn, $has, $num, &$green, &$red, &$unbuilt, &$unpracticed): void {
    echo "\n  ── {$req} · {$title}\n";
    $missing = array();
    foreach ($need as $t) { if (!$has($t)) { $missing[] = $t; } }
    if ($missing) {
        $unbuilt++;
        printf("     ✘ غيرُ مبنيّة — جداولٌ مفقودة: %s\n", implode('، ', $missing));
        return;
    }
    $counts = array();
    foreach ($need as $t) { $counts[] = $t . '=' . $num("SELECT COUNT(*) FROM `{$t}`"); }
    printf("     ○ مبنيّةٌ بجداولِها: %s\n", implode(' · ', $counts));

    if ($sql === null) { $unpracticed++; printf("     ⏸ %s — لا مقياسَ آليٌّ لها بعد\n", $crit); return; }
    $live = $num("SELECT COUNT(*) FROM `{$rows}`");
    if ($live === 0) {
        $unpracticed++;
        printf("     ⏸ %s — لا صفوفَ في `%s` تُقاس (صفرٌ بلا ممارسة)\n", $crit, $rows);
        return;
    }
    $got = $num($sql);
    if ($got === $want) { $green++; printf("     ✔ %s = %d\n", $crit, $got); }
    else { $red++; printf("     ✘ %s = %d (المطلوب %d)\n", $crit, $got, $want); }
};

echo "\n═══ INJ-FRD-01 · الموجةُ ④ — القدراتُ المحكومةُ المستجدّة ═══\n";

/* SUP-07 — «كلُّ عقدِ موردٍ له عرضٌ فائزٌ مرجعيّ · صفرُ عرضٍ مخترع» */
$cap('SUP-07', 'عروضُ الموردينَ والتفاوض',
    array('supplier_rfqs', 'rfq_quotes', 'rfq_lines', 'rfq_awards'),
    'rfq_awards', 'إرساءٌ بلا عرضٍ مرجعيّ',
    "SELECT COUNT(*) FROM rfq_awards a
      WHERE a.quote_id IS NULL OR a.quote_id = 0
         OR NOT EXISTS (SELECT 1 FROM rfq_quotes q WHERE q.id = a.quote_id)", 0);

/* SUP-16 — «كلُّ وحدةٍ منتهيةٍ لها إقفالٌ تعاقديٌّ معتمَد» */
$cap('SUP-16', 'الإقفالُ التعاقديُّ للخانات',
    array('supplier_contract_closures', 'seat_assignments'),
    'supplier_contract_closures', 'إقفالٌ بلا عقدٍ مرجعيّ',
    "SELECT COUNT(*) FROM supplier_contract_closures c
      WHERE c.is_deleted = 0
        AND (c.contract_id IS NULL OR c.contract_id = 0
             OR NOT EXISTS (SELECT 1 FROM supplier_contracts s WHERE s.id = c.contract_id))", 0);

/* SUP-22 — «كلُّ رصيدٍ له شريحةُ عمرٍ وإجراءٌ مقترح» */
$cap('SUP-22', 'أعمارُ الأرصدةِ والالتزامات',
    array('sup_balance_aging'),
    'sup_balance_aging', 'رصيدٌ بلا شريحةِ عمر', null, 0);

/* SUP-26 — «صفرُ مستندٍ منتهٍ بلا تنبيه» */
$cap('SUP-26', 'سجلُّ المستنداتِ والضمانات',
    array('contract_guarantees', 'gov_doc_registry'),
    'contract_guarantees', 'ضمانٌ منتهٍ وحالتُه لم تُحدَّث',
    /* ◆ **الحالةُ تُقرأ لا تُعمَّم**: من الثمانيةِ التي رصدها القياسُ الأوّلُ
         **واحدةٌ خرقٌ حقيقيّ**. والسبعُ الباقيةُ حالتانِ مشروعتان:
         ① `called` — الضمانُ **صُودر**، فدورتُه انتهت بمصادرتِه لا بانتهاءِ
            تاريخِه؛ وتاريخُ الانتهاءِ بعدَها لا معنى له.
         ② `draft` بمبلغِ صفرٍ — **لم يُصدَر قط**، ومهلةُ مسوَّدةٍ لم تُصدَر
            ليست مستندًا حيًّا منتهيًا.
         فيُقاس **الحيُّ المنتهي** وحدَه: `active` وقد مضى تاريخُه. وتعميمُ
         الثمانيةِ يُضخّم الرقمَ ثمانيةَ أضعافٍ ويُغرق الخرقَ الحقيقيَّ فيه. */
    "SELECT COUNT(*) FROM contract_guarantees
      WHERE is_deleted = 0 AND expiry_date IS NOT NULL AND expiry_date < CURDATE()
        AND state = 'active'", 0);

printf("\n  أخضر %d · أحمر %d · غيرُ مبنيٍّ %d · مبنيٌّ غيرُ مُمارَسٍ %d   (من 4 قدرات)\n",
    $green, $red, $unbuilt, $unpracticed);
echo "\n  ◆ «مبنيّ» ليس «مقبولًا»: معيارٌ يقول «كلُّ عقدٍ» لا يُغلقه جدولٌ بصفَّين.\n";
echo "  ◆ و«غيرُ مبنيّ» ليس «صفرَ خرق»: جدولٌ مفقودٌ لا يُقاس ولا يُحسب نجاحًا.\n\n";

exit($GATE && $red > 0 ? 1 : 0);
