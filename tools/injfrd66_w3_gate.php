<?php
/**
 * tools/injfrd66_w3_gate.php — بوابةُ الموجةِ ③: معاييرُ القبولِ الخمسةَ عشرَ مقيسةً
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا بوابةٌ قبلَ البناء**: معاييرُ قبولِ الموجةِ ③ في الوثيقةِ **حقولٌ
 *   وتفرّدٌ وبيانات**، لا عددُ تبويبات. فتُقاس **اليومَ** بلا انتظارِ بناء —
 *   ومن كان معيارُه أخضرَ سلفًا لا يُبنى له شيءٌ إثباتًا لعملٍ لم يلزم.
 *
 * ◆ **والقياسُ يعلن جهلَه**: ما لا جدولَ له يُطبع «سطحٌ غيرُ مبنيّ» ولا
 *   يُحسب نجاحًا ولا رسوبًا. **وصفرُ صفٍّ في جدولٍ مفقودٍ ليس «صفرَ خرق»** —
 *   وهذا الخلطُ بعينِه يُخضِّر ما لم يُبنَ بعد.
 *
 * ◆ قراءةٌ خالصة — لا كتابةَ في القاعدةِ إطلاقًا.
 *
 * التشغيل:
 *   php tools/injfrd66_w3_gate.php           التقرير
 *   php tools/injfrd66_w3_gate.php --gate    رمزُ خروجٍ 1 عند أيِّ خرقٍ مقيس
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

/* ── عُدّةُ القياس ────────────────────────────────────────────────────── */
$exists = static function (string $t) use ($conn): bool {
    $r = @mysqli_query($conn, "SELECT 1 FROM information_schema.TABLES
                                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '"
                                . mysqli_real_escape_string($conn, $t) . "'");
    return (bool) ($r && mysqli_num_rows($r));
};
$cols = static function (string $t) use ($conn): int {
    $r = @mysqli_query($conn, "SELECT COUNT(*) FROM information_schema.COLUMNS
                                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '"
                                . mysqli_real_escape_string($conn, $t) . "'");
    return $r ? (int) mysqli_fetch_row($r)[0] : 0;
};

/* العدُّ الحقيقيُّ — `COUNT(*)` لا `TABLE_ROWS` (الثاني تقديرُ InnoDB) */
$count = static function (string $t) use ($conn): int {
    $r = @mysqli_query($conn, "SELECT COUNT(*) FROM `" . str_replace('`', '', $t) . "`");
    return $r ? (int) mysqli_fetch_row($r)[0] : 0;
};

$green = 0; $red = 0; $unbuilt = 0; $rows = array();

/**
 * قياسُ معيارٍ عدديّ.
 * @param array $need الجداولُ اللازمة — إن غاب أحدُها فالسطحُ غيرُ مبنيّ
 */
$measure = static function (string $req, string $crit, array $need, string $sql, int $want, string $needRows = '')
        use ($conn, $exists, $count, &$green, &$red, &$unbuilt, &$rows): void {
    foreach ($need as $t) {
        if (!$exists($t)) {
            $unbuilt++;
            $rows[] = array('r' => $req, 'c' => $crit, 's' => 'ــ', 'v' => "سطحٌ غيرُ مبنيّ: {$t}");
            return;
        }
    }
    /* ◆ **حارسُ الجدولِ الفارغ**: معيارٌ صيغتُه «صفرُ س خارجَ ص» **لا يُثبته
         جدولٌ فارغ** — صفرُه عدمُ بياناتٍ لا صحّةُ حارس. وقِيس أنَّ
         `sup_violations` فارغٌ (COUNT(*)=0) بينما `TABLE_ROWS` يقول 1،
         فلو صُدِّق الظنُّ لأُعلن معيارُ SUP-23 أخضرَ وهو لم يُمارَس قط.
         **(والعدُّ هنا `COUNT(*)` لا `TABLE_ROWS` — الثاني تقديرٌ لا عدد.)** */
    if ($needRows !== '' && $count($needRows) === 0) {
        $unbuilt++;
        $rows[] = array('r' => $req, 'c' => $crit, 's' => 'ــ', 'v' => "لا صفوفَ تُقاس في {$needRows} — صفرٌ بلا ممارسة");
        return;
    }
    $res = @mysqli_query($conn, $sql);
    if (!$res) {
        $unbuilt++;
        $rows[] = array('r' => $req, 'c' => $crit, 's' => 'ــ', 'v' => 'تعذّر القياس: ' . mysqli_error($conn));
        return;
    }
    $got = (int) mysqli_fetch_row($res)[0];
    if ($got === $want) { $green++; $rows[] = array('r' => $req, 'c' => $crit, 's' => '✔', 'v' => (string) $got); }
    else { $red++; $rows[] = array('r' => $req, 'c' => $crit, 's' => '✘', 'v' => "{$got} (المطلوب {$want})"); }
};

/* ═══ المبيعات ═══════════════════════════════════════════════════════ */
$measure('SAL-01', 'كودُ عميلٍ متصادم', array('clients'),
    "SELECT IFNULL(SUM(c-1),0) FROM (SELECT COUNT(*) c FROM clients
       WHERE is_deleted=0 AND client_code IS NOT NULL AND client_code<>'' GROUP BY client_code HAVING c>1) x", 0);
$measure('SAL-01', 'تكرارُ اسمِ عميل', array('clients'),
    "SELECT IFNULL(SUM(c-1),0) FROM (SELECT COUNT(*) c FROM clients
       WHERE is_deleted=0 GROUP BY client_name HAVING c>1) x", 0);

$measure('SAL-02', 'بندُ تنقّلٍ لجهاتِ الاتصال', array('nav_items'),
    "SELECT COUNT(*) FROM nav_items WHERE active=1
       AND (route LIKE '%contact%' OR label_ar LIKE '%جهات الاتصال%' OR label_ar LIKE '%جهاتِ الاتصال%')", 0);

/* ── مصالحةُ رقمَين متجاورَين — وإلا أخفى أحدُهما ما يُظهره الآخر ─────────
   ◆ `SAL-11` «صفر عقدٍ بلا عرضٍ مصدريّ» **أخضرُ** — و`SAL-07` «صفر عرضٍ بلا
     فرصة» **أحمرُ بمئةٍ وعشرين**. والرقمانِ ليسا مستقلَّين: المئةُ والعشرون
     نفسُها هي التي تُخضِّر `SAL-11`.
   ◆ **وهي رَدْمٌ مُعلَنٌ لا إخفاء**: كودُها `QT-BF-*` وملاحظتُها بنصِّها
     «عرضٌ ردميٌّ من العقدِ … بيانٌ تجريبيٌّ بإقرارِ المالك 2026-08-16»،
     و**كلُّها أُنشئت بعدَ عقودِها** (120 من 120) في ثانيةٍ واحدة.
   ◆ **وبوابةُ البياناتِ تُلزم بالفصل**: «يحمل صفوفًا حقيقيةً — لا صفرًا
     **ولا بذرةَ اختبار**». فيُقاس الحقيقيُّ وحدَه، ويُعلَن الردمُ رقمًا
     مستقلًّا — فلا يُعَدُّ خرقًا ولا يُحسب إنجازًا. */
$BF = "(q.quotation_code LIKE 'QT-BF-%' OR q.notes LIKE '%ردمي%')";

$measure('SAL-07', 'عرضٌ حقيقيٌّ بلا فرصة', array('quotations'),
    "SELECT COUNT(*) FROM quotations q WHERE q.is_deleted=0 AND NOT {$BF}
       AND (q.opportunity_id IS NULL OR q.opportunity_id=0)", 0);

$measure('SAL-11', 'عقدٌ بلا عرضٍ مصدريّ', array('contracts'),
    "SELECT COUNT(*) FROM contracts WHERE is_deleted=0 AND (quotation_id IS NULL OR quotation_id=0)", 0);

$measure('SAL-11', 'عقدٌ عرضُه المصدريُّ ردميّ', array('contracts', 'quotations'),
    "SELECT COUNT(*) FROM contracts c JOIN quotations q ON q.id=c.quotation_id
      WHERE c.is_deleted=0 AND {$BF}", 0);

$measure('SAL-13', 'التزامٌ خارجَ مصفوفةِ عقدِه', array('contract_obligations'),
    "SELECT COUNT(*) FROM contract_obligations o
       WHERE o.is_deleted=0 AND NOT EXISTS (SELECT 1 FROM contracts c
              WHERE c.id=o.client_contract_id AND c.is_deleted=0)", 0);

/* ◆ **و«نافذ» حالتان لا حالة**: التعدادُ يحمل «نافذ» **و«قيد التنفيذ»**، وعقدٌ
     قيدَ التنفيذِ بلا خطِّ أساسٍ خرقٌ **أشدُّ** لا أخفّ — يُنفَّذ بلا مرجعٍ يُقاس
     عليه. وقصرُ القياسِ على «نافذ» وحدَها كان يُبلغ **واحدًا وهي أربعة**.
   ◆ ولا يُقاس هذا في بوابتَين: **قارئٌ واحدٌ لكلِّ معيار** — وعدّادانِ في
     ملفَّين يتفرّقان بأوَّلِ تعديل. */
$measure('SAL-14', 'عقدٌ نافذٌ أو قيدَ التنفيذِ بلا خطِّ أساس', array('contracts', 'contract_baseline'),
    "SELECT COUNT(*) FROM contracts c
      WHERE c.is_deleted=0 AND c.contract_status IN ('active','running','نافذ','قيد التنفيذ')
        AND NOT EXISTS (SELECT 1 FROM contract_baseline b WHERE b.contract_id=c.id AND b.is_deleted=0)", 0);

$measure('SAL-19', 'ملحقٌ بلا تاريخِ سريان', array('contract_amendments'),
    "SELECT COUNT(*) FROM contract_amendments WHERE is_deleted=0 AND effective_from IS NULL", 0);

/* ═══ الموردون ═══════════════════════════════════════════════════════ */
$measure('SUP-01', 'موردٌ بلا كود', array('suppliers'),
    "SELECT COUNT(*) FROM suppliers WHERE is_deleted=0 AND (supplier_code IS NULL OR supplier_code='')", 0);
$measure('SUP-01', 'كودُ موردٍ متصادم', array('suppliers'),
    "SELECT IFNULL(SUM(c-1),0) FROM (SELECT COUNT(*) c FROM suppliers
       WHERE is_deleted=0 AND supplier_code IS NOT NULL AND supplier_code<>'' GROUP BY supplier_code HAVING c>1) x", 0);

$measure('SUP-08', 'عقدُ موردٍ بلا مورد', array('supplier_contracts'),
    "SELECT COUNT(*) FROM supplier_contracts WHERE is_deleted=0 AND (supplier_id IS NULL OR supplier_id=0)", 0);
$measure('SUP-08', 'صفٌّ قديمٌ بلا إسقاطٍ كنسيّ', array('supplierscontracts', 'supplier_contracts'),
    "SELECT COUNT(*) FROM supplierscontracts o
       WHERE NOT EXISTS (SELECT 1 FROM supplier_contracts n
                          WHERE n.source_table='supplierscontracts' AND n.source_id=o.id)", 0);

$measure('SUP-10', 'قاعدةُ تحميلٍ خارجَ عقدِها', array('supplier_charge_rules', 'supplier_contracts'),
    "SELECT COUNT(*) FROM supplier_charge_rules r
       WHERE r.contract_id IS NOT NULL AND r.contract_id<>0
         AND NOT EXISTS (SELECT 1 FROM supplier_contracts c WHERE c.id=r.contract_id)", 0, 'supplier_charge_rules');

$measure('SUP-11', 'حصةٌ بلا التزامٍ مرجعي', array('op_containers'),
    "SELECT COUNT(*) FROM op_containers WHERE is_deleted=0 AND (obl_id IS NULL OR obl_id=0)", 0);

$measure('SUP-13', 'حصةٌ نشأت من إحلال', array('sup_handover'),
    "SELECT COUNT(*) FROM sup_handover", 0);

$measure('SUP-18', 'تسويةُ موردٍ بلا طرفٍ مرجعيّ', array('settlements'),
    "SELECT COUNT(*) FROM settlements WHERE is_deleted=0 AND party_type='supplier'
        AND (party_ref IS NULL OR party_ref=0)", 0);

$measure('SUP-23', 'جزاءٌ خارجَ التسوية', array('sup_violations'),
    "SELECT COUNT(*) FROM sup_violations WHERE settlement_id IS NULL OR settlement_id=0", 0, 'sup_violations');

$measure('SUP-25', 'إغلاقٌ بلا تصفيةٍ وإخلاءِ طرف', array('supplier_contract_closures'),
    "SELECT COUNT(*) FROM supplier_contract_closures
      WHERE is_deleted=0 AND closed_at IS NOT NULL
        AND (advances_settled_at IS NULL OR clearance_doc IS NULL OR clearance_doc='')", 0, 'supplier_contract_closures');

/* ── العرض ───────────────────────────────────────────────────────────── */
echo "\n═══ INJ-FRD-01 · بوابةُ الموجةِ ③ — معاييرُ القبولِ مقيسةً ═══\n\n";
printf("  %-8s %-38s %s\n", 'المعرّف', 'المعيار', 'المقيس');
echo '  ' . str_repeat('─', 74) . "\n";
foreach ($rows as $r) { printf("  %-8s %-38s %s %s\n", $r['r'], $r['c'], $r['s'], $r['v']); }

printf("\n  أخضر %d · أحمر %d · غيرُ مبنيٍّ %d   (والمجموع %d)\n",
    $green, $red, $unbuilt, count($rows));
echo "\n  ◆ «غيرُ مبنيّ» ليس «صفرَ خرق»: جدولٌ مفقودٌ لا يُقاس، ولا يُحسب نجاحًا.\n";
echo "  ◆ والأحمرُ خرقٌ حقيقيٌّ في بياناتٍ حيّة — يُعالَج قبلَ إعلانِ أيِّ قبول.\n\n";

exit($GATE && $red > 0 ? 1 : 0);
