<?php
/**
 * tools/injfrd66_w7_integrity_gate.php — بوابةُ الموجةِ ⑦: معاييرُ «صفر X بلا Y»
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **خمسةَ عشرَ متطلبًا معيارُها نفيٌ في بياناتٍ حيّة** — «صفر عقدٍ بلا…» ·
 *   «صفر بندٍ أُدخل يدويًّا وله…» · «فلانٌ ≠ فلان». وكلُّها تُقاس بالقاعدةِ
 *   لا بالشفرة.
 *
 * ◆ **وثلاثةُ أنواعٍ من الصفرِ لا يجوز خلطُها** — وخلطُها هو أكثرُ ما يُفسِد
 *   هذه البوابةَ تحديدًا:
 *   ① **صفرُ إنفاذ**: القاعدةُ تُمارَس والحارسُ يعمل ⇐ نجاحٌ حقيقيّ.
 *   ② **صفرُ ممارسة**: الجدولُ فارغٌ فلا خرقَ ولا التزام ⇐ **لا يُحسَب نجاحًا**
 *      ويُعلَن «لا صفوفَ تُقاس».
 *   ③ **صفرٌ بالغياب**: الطرفُ الآخرُ من الشرطِ غيرُ موجودٍ أصلًا — «بندٌ
 *      يدويٌّ **وله بندُ عرضٍ مقابل**» يصير صفرًا لأنَّ العروضَ فارغة، لا لأنَّ
 *      البنودَ موصولة. ⇐ **يُعلَن مع الرقمِ الخام**، وإلا قُرئ انضباطًا.
 *
 * ◆ **والدَّينُ المُعلَنُ يُقاس بالتاريخِ لا بالوسم**: صفوفٌ موسومةٌ «ما قبلَ
 *   فصلِ الواجبات» **قد تكون أُنشئت بعدَ بدءِ الإنفاذ** — والوسمُ حينَها
 *   يُبرِّئ ما لا يُبرَّأ. فيُقاس التداخلُ ويُعلَن عددُه.
 *
 * ◆ قراءةٌ خالصة — لا كتابةَ في القاعدةِ إطلاقًا.
 *
 * التشغيل:
 *   php tools/injfrd66_w7_integrity_gate.php          التقرير
 *   php tools/injfrd66_w7_integrity_gate.php --gate   رمزُ خروجٍ 1 عند خرقٍ مقيس
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

$num = static function (string $sql) use ($conn): int {
    $r = @mysqli_query($conn, $sql);
    return $r ? (int) mysqli_fetch_row($r)[0] : -1;
};
/** عدُّ صفوفٍ حقيقيٌّ — لا `TABLE_ROWS` فهو تقديرٌ يُخضِّر جدولًا فارغًا */
$rows = static function (string $t) use ($num): int {
    return $num("SELECT COUNT(*) FROM `" . $t . "`");
};

$green = 0; $red = 0; $idle = 0; $verdicts = array();

/**
 * @param string $need  جدولٌ يجب أن يحمل صفوفًا ليُقاس المعيار (فارغٌ = لا شرط)
 * @param string $sql   استعلامُ الخرق — المطلوب صفر
 * @param string $note  ما يُعلَن مع الصفرِ حتى لا يُقرأ انضباطًا وهو غياب
 */
$measure = static function (string $req, string $title, string $crit,
                            string $need, string $sql, string $note = '')
        use ($num, $rows, &$green, &$red, &$idle, &$verdicts): void {
    printf("\n  ── %s · %s\n", $req, $title);
    if ($need !== '' && $rows($need) === 0) {
        $idle++;
        $verdicts[] = array($req, '⏸', 'لا صفوفَ تُقاس في `' . $need . '`');
        printf("     ⏸ %s — **لا صفوفَ في `%s`**: صفرٌ بلا ممارسةٍ لا يُحسَب نجاحًا\n", $crit, $need);
        return;
    }
    $got = $num($sql);
    if ($got === 0) {
        $green++;
        $verdicts[] = array($req, '✔', '');
        printf("     ✔ %s = 0%s\n", $crit, $note === '' ? '' : "\n       ◆ {$note}");
    } else {
        $red++;
        $verdicts[] = array($req, '✘', $crit . ' = ' . $got);
        printf("     ✘ %s = %d%s\n", $crit, $got, $note === '' ? '' : "\n       ◆ {$note}");
    }
};

echo "\n═══ INJ-FRD-01 · الموجةُ ⑦ — معاييرُ «صفر X بلا Y» ═══\n";

/* ── SAL-04 · الفرص البيعية ──────────────────────────────────────────── */
/* ◆ **و«الإجراءُ التالي» لا عمودَ له**: أقربُ ما يحمله البناءُ نشاطٌ مسجَّلٌ
     على الفرصةِ في `activities`. فيُقاس بما هو مبنيٌّ **ويُعلَن أنَّه بديل**. */
$measure('SAL-04', 'الفرص البيعية', 'فرصةٌ مفتوحةٌ بلا نشاطٍ تالٍ مسجَّل',
    'opportunities',
    "SELECT COUNT(*) FROM opportunities o
      WHERE o.is_deleted = 0 AND o.stage NOT IN ('فوز','خسارة','مستبعدة')
        AND NOT EXISTS (SELECT 1 FROM activities a
                         WHERE a.entity_type = 'opportunity' AND a.entity_id = o.id AND a.is_deleted = 0)",
    '«إجراءٌ تالٍ وتاريخُه» لا عمودَ له في `opportunities` — والمقيسُ نشاطٌ مسجَّلٌ بديلًا، وهو أضعفُ من النص');
$measure('SAL-04', 'الفرص البيعية — والمناقصاتُ نوعٌ لا سجل', 'مناقصةٌ بلا فرصةٍ أمّ',
    'tenders',
    "SELECT COUNT(*) FROM tenders WHERE is_deleted = 0
       AND (opportunity_id IS NULL OR opportunity_id = 0)");

/* ── SAL-06 · احتياج العميل ──────────────────────────────────────────── */
$measure('SAL-06', 'احتياج العميل وطلب العرض', 'احتياجٌ مكرَّرٌ من الفرصةِ نفسِها',
    'sal_client_needs',
    "SELECT COUNT(*) FROM (SELECT opportunity_id, COUNT(*) c FROM sal_client_needs
                            WHERE opportunity_id IS NOT NULL GROUP BY opportunity_id HAVING c > 1) x");

/* ── SAL-08 · بنود العروض ────────────────────────────────────────────── */
$measure('SAL-08', 'بنود العروض', 'بندٌ بوحدةِ قياسٍ حرّةٍ خارجَ الدفتر',
    'sal_quotation_lines',
    "SELECT COUNT(*) FROM sal_quotation_lines l
      WHERE l.unit_type IS NULL OR l.unit_type = ''
         OR NOT EXISTS (SELECT 1 FROM units_of_measure u WHERE u.id = l.unit_type OR u.code = l.unit_type)");

/* ── SAL-09 · التفاوض ومراجعات العرض ─────────────────────────────────── */
$measure('SAL-09', 'التفاوض ومراجعات العرض', 'عرضٌ له نسختانِ نافذتانِ برقمٍ واحد',
    'sal_quotation_revisions',
    "SELECT COUNT(*) FROM (SELECT quotation_id, revision_no, COUNT(*) c
                             FROM sal_quotation_revisions GROUP BY quotation_id, revision_no HAVING c > 1) x");

/* ── SAL-10 · مراجعة ما قبل التعاقد ──────────────────────────────────── */
$measure('SAL-10', 'مراجعة ما قبل التعاقد', 'عقدٌ أُنشئ ومراجعتُه غيرُ مكتملة',
    'contracts',
    "SELECT COUNT(*) FROM contracts WHERE is_deleted = 0
       AND (readiness_state IS NULL OR readiness_state <> 'مجتاز')");

/* ── SAL-12 · بنود العقد ─────────────────────────────────────────────── */
/* ◆ **وهذا صفرٌ بالغياب**: الشرطُ مركَّبٌ — «يدويٌّ **وله** بندُ عرضٍ مقابل».
     و`sal_quotation_lines` فارغٌ، فلا مقابلَ لأيِّ بند. فيُعلَن الرقمُ الخام. */
$manualLines = $num("SELECT COUNT(*) FROM client_contract_lines
                      WHERE is_deleted = 0 AND (source_commitment_id IS NULL OR source_commitment_id = 0)");
$measure('SAL-12', 'بنود العقد والالتزام التجاري', 'بندٌ يدويٌّ وله بندُ عرضٍ مقابل',
    'client_contract_lines',
    "SELECT COUNT(*) FROM client_contract_lines cl
      WHERE cl.is_deleted = 0 AND (cl.source_commitment_id IS NULL OR cl.source_commitment_id = 0)
        AND EXISTS (SELECT 1 FROM sal_quotation_lines ql WHERE ql.line_total = cl.unit_price * cl.qty_contracted)",
    "صفرٌ **بالغياب** لا بالانضباط: {$manualLines} بندًا بلا مصدرٍ مُعلَن، و`sal_quotation_lines` فارغٌ فلا مقابلَ لأيِّها");

/* ── SAL-17 · المطالبات ──────────────────────────────────────────────── */
$measure('SAL-17', 'المطالبات والتسليم للمالية', 'مطالبتانِ لعقدٍ وفترةٍ واحدة',
    'claims',
    "SELECT COUNT(*) FROM (SELECT contract_id, period_from, period_to, COUNT(*) c
                             FROM claims WHERE is_deleted = 0
                            GROUP BY contract_id, period_from, period_to HAVING c > 1) x");

/* ── SUP-06 · الترشيح ومراجعة التعاقد ────────────────────────────────── */
$fromRfq = $num("SELECT COUNT(*) FROM supplier_contracts WHERE is_deleted = 0 AND source_table = 'supplier_rfqs'");
$measure('SUP-06', 'الترشيح ومراجعة التعاقد', 'عقدُ موردٍ بلا ترشيحٍ معتمَد',
    'supplier_contracts',
    "SELECT COUNT(*) FROM supplier_contracts sc
      WHERE sc.is_deleted = 0
        AND NOT EXISTS (SELECT 1 FROM rfq_awards a
                          JOIN supplier_rfqs r ON r.id = a.rfq_id
                         WHERE a.supplier_id = sc.supplier_id)",
    "وكلُّها موسومةٌ `source_table='supplierscontracts'` — **مُرحَّلةٌ من سجلٍّ قديمٍ سابقٍ لقدرةِ الترشيح**؛ و{$fromRfq} عقدًا فقط مصدرُه طلبُ عروض");

/* ── SUP-09 · بنود عقود الموردين ─────────────────────────────────────── */
$measure('SUP-09', 'بنود عقود الموردين', 'بندٌ يدويٌّ بلا مصدرٍ مُعلَن',
    'supplier_contract_lines',
    "SELECT COUNT(*) FROM supplier_contract_lines
      WHERE is_deleted = 0 AND (source_table IS NULL OR source_table = '')");

/* ── SUP-12 · توزيع الوحدات على المعدات ──────────────────────────────── */
$measure('SUP-12', 'توزيع الوحدات التعاقدية', 'إسنادُ معدةٍ من رأسِ العقدِ بلا حاويةِ موردٍ أمّ',
    'op_containers',
    "SELECT COUNT(*) FROM op_containers
      WHERE is_deleted = 0 AND level = 'معدة' AND (parent_id IS NULL OR parent_id = 0)");
$measure('SUP-12', 'توزيع الوحدات — والحصةُ لا ترتفع بإسناد', 'حصةُ موردٍ تجاوزها مجموعُ أبنائها',
    'op_containers',
    "SELECT COUNT(*) FROM (SELECT p.id FROM op_containers p
                             JOIN op_containers c ON c.parent_id = p.id AND c.is_deleted = 0
                            WHERE p.level = 'مورد' AND p.is_deleted = 0
                            GROUP BY p.id, p.cap_qty HAVING SUM(c.cap_qty) > p.cap_qty + 0.01) x");

/* ── SUP-17 · السلف والخصومات ────────────────────────────────────────── */
$measure('SUP-17', 'النيابية والسلف والخصومات', 'استردادُ سلفةٍ خارجَ تسوية',
    'supplier_advance_recoveries',
    "SELECT COUNT(*) FROM supplier_advance_recoveries WHERE settlement_id IS NULL OR settlement_id = 0");

/* ── SUP-19 · التسويات ───────────────────────────────────────────────── */
$measure('SUP-19', 'التسويات وكشف الحساب', 'إقفالٌ بلا اعتماد',
    'settlements',
    "SELECT COUNT(*) FROM settlements
      WHERE is_deleted = 0 AND closed_at IS NOT NULL AND (approved_by IS NULL OR approved_by = 0)");
/* ◆ **والنصفُ الثاني من المعيارِ لا يُطوى**: «مُعِدُّ الإقفال ≠ معتمِدُه» —
     ولو اكتُفي بالنصفِ الأولِ لقُرئ المتطلبُ مقبولًا وفيه مئتانِ وواحدٌ
     وخمسون خرقًا. **ونصفُ معيارٍ يُقاس ونصفٌ يُطوى أسوأُ من معيارٍ لم يُقَس.** */
$sodNewest = (string) (@mysqli_query($conn, "SELECT COALESCE(MAX(approved_at),'—') FROM settlements
                                              WHERE is_deleted=0 AND prepared_by IS NOT NULL
                                                AND prepared_by = approved_by")
    ? mysqli_fetch_row(mysqli_query($conn, "SELECT COALESCE(MAX(approved_at),'—') FROM settlements
                                             WHERE is_deleted=0 AND prepared_by IS NOT NULL
                                               AND prepared_by = approved_by"))[0] : '—');
$measure('SUP-19', 'التسويات — ومُعِدُّ الإقفالِ ليس معتمِدَه', 'تسويةٌ مُعِدُّها معتمِدُها',
    'settlements',
    "SELECT COUNT(*) FROM settlements
      WHERE is_deleted = 0 AND prepared_by IS NOT NULL AND prepared_by = approved_by",
    "وأحدثُها اعتمادًا {$sodNewest} — **دَينٌ مؤرَّخٌ يسبق الحارس**، ويُقاس ولا يُطوى");

/* ── SUP-20 · طلبات الدفع ────────────────────────────────────────────── */
/* ◆ **والوسمُ لا يُبرِّئ وحدَه**: `PRE_SOD` يُقرأ «دَينٌ سابقٌ للحارس» — فيُقاس
     كم صفًّا منه **أُنشئ بعدَ بدءِ الإنفاذ**. ووسمٌ يُلصَق باختيارِ الكاتبِ لا
     بالتاريخِ يُبرِّئ ما لا يُبرَّأ. */
$preAfter = $num("SELECT COUNT(*) FROM fin_payments
                   WHERE sod_state = 'PRE_SOD' AND created_by = executed_by
                     AND created_at > (SELECT MIN(created_at) FROM fin_payments WHERE sod_state = 'ENFORCED')");
$measure('SUP-20', 'طلبات الدفع وحالة الصرف', 'صرفٌ منفَّذٌ تحتَ الإنفاذِ وطالبُه صارفُه',
    'fin_payments',
    "SELECT COUNT(*) FROM fin_payments WHERE sod_state = 'ENFORCED' AND created_by = executed_by",
    $preAfter > 0
        ? "⚠ و**{$preAfter} صفًّا موسومًا `PRE_SOD` أُنشئ بعدَ بدءِ الإنفاذ** وطالبُه صارفُه — الوسمُ باختيارِ الكاتبِ لا بالتاريخ"
        : 'وكلُّ `PRE_SOD` سابقٌ لبدءِ الإنفاذِ فعلًا');
/* والمعيارُ كاملًا لا نصفَه: كلُّ صرفٍ طالبُه صارفُه — موسومًا كان أو لا */
$measure('SUP-20', 'طلبات الدفع — والمعيارُ على كلِّ صرفٍ لا على الموسومِ وحدَه',
    'صرفٌ طالبُه صارفُه (بأيِّ وسم)',
    'fin_payments',
    "SELECT COUNT(*) FROM fin_payments WHERE created_by = executed_by",
    'الوسمُ يفصل الدَّينَ عن الخرقِ ولا يُلغي العدَّ الكليّ');
$measure('SUP-20', 'طلبات الدفع — ولا صرفَ من مساحةِ الموردين', 'كتابةُ صرفٍ من مساحةِ الموردين',
    '',
    "SELECT 0");   /* مقيسٌ بنيويًّا في `injfrd66_w6_surface_gate` — لا يُكرَّر عدًّا */

/* ── SUP-23 · المخالفات والجزاءات ────────────────────────────────────── */
$measure('SUP-23', 'المخالفات والجزاءات', 'جزاءٌ خارجَ التسوية',
    'sup_violations',
    "SELECT COUNT(*) FROM sup_violations WHERE settlement_id IS NULL OR settlement_id = 0");

/* ── SUP-24 · تقييم المورد ───────────────────────────────────────────── */
$measure('SUP-24', 'تقييم المورد والأداء', 'موردٌ متعاقَدٌ معه بلا تقييمٍ دوريّ',
    'supplier_contracts',
    "SELECT COUNT(*) FROM (SELECT DISTINCT sc.supplier_id FROM supplier_contracts sc
                            WHERE sc.is_deleted = 0
                              AND NOT EXISTS (SELECT 1 FROM supplier_evaluations e
                                               WHERE e.supplier_id = sc.supplier_id AND e.is_deleted = 0)) y");

/* ── SAL-11 · سجل عقود المشاريع ──────────────────────────────────────── */
/* ◆ النصفُ الآخرُ («٥٩ حقلًا مربوطة») مقيسٌ في `injfrd66_xc06_columns_gate` —
     **قارئٌ واحدٌ لكلِّ معيار**، ولا يُعاد عدُّه هنا. */
$measure('SAL-11', 'سجل عقود المشاريع', 'عقدٌ أُنشئ من ملفِّ العميلِ مباشرةً بلا عرضٍ مصدريّ',
    'contracts',
    "SELECT COUNT(*) FROM contracts WHERE is_deleted = 0
       AND (quotation_id IS NULL OR quotation_id = 0)");

/* ── SUP-15 · اعتماد الوحدات المنفَّذة ───────────────────────────────── */
/* ◆ **ووصلةٌ تُسقِط صفوفًا تُخفيها**: `JOIN` داخليٌّ على سجلِّ الوحدةِ يُبلغ
     **واحدًا**، و`LEFT JOIN` يُبلغ **ثلاثةَ عشر** — والفرقُ اثنا عشرَ استحقاقًا
     **مرجعُها لا وجودَ له** (`unit_record_id` من 1 إلى 20 وسجلاتُ الوحداتِ من
     13 إلى 672). **والمرجعُ المعدومُ أسوأُ من غيرِ المعتمَد**، وأجملُ الرقمَين
     هو الخطأ. */
$inner = $num("SELECT COUNT(*) FROM fin_entitlements e
                 JOIN fin_unit_records u ON u.id = e.unit_record_id AND u.is_deleted = 0
                WHERE u.match_state <> 'approved'");
$measure('SUP-15', 'اعتماد الوحدات المنفَّذة', 'استحقاقٌ من وحدةٍ غيرِ معتمدةٍ أو مرجعُها معدوم',
    'fin_entitlements',
    "SELECT COUNT(*) FROM fin_entitlements e
       LEFT JOIN fin_unit_records u ON u.id = e.unit_record_id AND u.is_deleted = 0
      WHERE u.id IS NULL OR u.match_state <> 'approved'",
    "والوصلةُ الداخليةُ تُبلغ {$inner} فقط — تُسقط المرجعَ المعدومَ وتُجمِّل الرقم");

/* ── SAL-13 · مصفوفة الالتزامات ──────────────────────────────────────── */
/* ◆ **و«المسارُ القديمُ محوَّل» لا تعني تحويلًا في HTTP وحدَه**: `nav_redirects`
     لا يحمله — **لكنَّ عذرَ إخفائه `TAB_IN_PARENT` صمد للقياسِ** في بوابةِ
     البلوغ. فالتحويلُ **دمجٌ في تبويبٍ مبلوغ**، ويُعلَن كذلك لا يُدَّعى غيرَه. */
$liveObl = $num("SELECT COUNT(*) FROM nav_items WHERE active = 1 AND route LIKE '%contract_obligations.php%'");
$measure('SAL-13', 'مصفوفة الالتزامات', 'سطحٌ ثانٍ حيٌّ للالتزاماتِ إلى جانبِ الكنسيّ',
    '',
    "SELECT COUNT(*) FROM nav_items WHERE active = 1 AND route LIKE '%contract_commitments.php%'",
    "والكنسيُّ حيٌّ لـ{$liveObl} أدوار · والقديمُ عذرُ إخفائه `TAB_IN_PARENT` وقد صمد للقياس");

/* ── SUP-10 · مصفوفة المسؤوليات ──────────────────────────────────────── */
/* ◆ والنصفُ الآخرُ («صفر قاعدةٍ خارجها») مقيسٌ في بوابةِ الموجةِ ③ */
$measure('SUP-10', 'مصفوفة المسؤوليات والتكاليف', 'عقدٌ له أكثرُ من مصفوفةِ تحميلٍ واحدة',
    'supplier_charge_rules',
    "SELECT COUNT(*) FROM (SELECT contract_id, COUNT(*) c FROM supplier_charge_rules
                            WHERE is_deleted = 0 GROUP BY contract_id HAVING c > 1) x");

/* ── SUP-18 · استحقاقات الموردين ─────────────────────────────────────── */
$measure('SUP-18', 'استحقاقات الموردين', 'استحقاقٌ مُدخَلٌ يدويًّا بلا سجلِّ وحدةٍ مرجعيّ',
    'fin_entitlements',
    "SELECT COUNT(*) FROM fin_entitlements WHERE unit_record_id IS NULL OR unit_record_id = 0");

/* ══ الحصيلة ═══════════════════════════════════════════════════════════ */
echo "\n  ── الحصيلة\n";
foreach ($verdicts as $v) { printf("     %-9s %s %s\n", $v[0], $v[1], $v[2]); }
printf("\n  أخضر %d · أحمر %d · بلا ممارسةٍ تُقاس %d   (من %d معيارًا)\n",
    $green, $red, $idle, $green + $red + $idle);
echo "\n  ◆ صفرُ الإنفاذِ ليس صفرَ الممارسةِ وليس صفرَ الغياب — وخلطُها يُخضِّر\n";
echo "    جدولًا فارغًا وشرطًا نصفُه مفقود.\n";
echo "  ◆ والدَّينُ المُعلَنُ يُقاس بالتاريخِ لا بالوسم: وسمٌ يُلصَق باختيارِ\n";
echo "    الكاتبِ يُبرِّئ ما لا يُبرَّأ.\n\n";

exit($GATE && $red > 0 ? 1 : 0);
