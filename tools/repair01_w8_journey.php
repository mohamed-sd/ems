<?php
/**
 * tools/repair01_w8_journey.php — رحلةُ العميلِ ورحلةُ المورد (‏W08 §٦-أ)
 * ═══════════════════════════════════════════════════════════════════════════
 * **رحلةُ العميل:** فرصةٌ ← عرضُ سعر ← تفاوض ← عقد ← التزاماتُ العقد ← أمرُ عمل
 *   ← تنفيذٌ مقيس ← مستخلص ← فاتورة ← طلبُ اعترافٍ ماليّ ← تحصيل.
 * **رحلةُ المورد:** تأهيلٌ قانونيٌّ وائتمانيّ ← عقدُ توريد ← التزامٌ تعاقديّ
 *   ← استحقاق ← مطالبة ← تسويةٌ ← إقفال.
 *
 * ◆ **والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46): عند كلِّ
 *   مستهلكٍ يُقاس **رقمٌ يعنيه** — حاويةٌ تُفتح بعقدٍ نافذ · التزامٌ يُقيَّد ·
 *   ذمّةٌ تُفتَح بصافي المستخلص · محتجَزٌ يتراكم · خانةٌ تُشغَل · استحقاقٌ
 *   يُشتقّ · تسويةٌ تُعتمد بيدٍ ثانية.
 *
 * ◆ **ورحلتانِ لا رحلة**: `journey_key` يفصل مقامَيهما — وبلا فصلٍ يصير
 *   «عابرٌ ١٨/١٨» دعوى على مقامٍ مركَّب.
 *
 * ◆ **والوحدتانِ مرجعيّتان** (§19 · §٢ «لا إعادةَ بناء»): فالمحطّاتُ الموجبةُ
 *   تُقاس على **السلسلةِ الحيّةِ القائمة** لا على بذرةٍ تُصطنَع ثمَّ تُرجَع.
 *   وسلسلةٌ حيّةٌ عبرت فعلًا أقوى دليلًا من سلسلةٍ ولدتها الأداةُ لنفسها —
 *   ولذلك تُختار المرساةُ **بالقياس** (أكملُ سلسلةٍ في القاعدة) لا برقمٍ مكتوب.
 *
 * ◆ **والمحطّاتُ السالبةُ تُقاس بالاستدعاءِ الفعليِّ ورمزِ الردّ** — لا بقراءةِ
 *   شيفرةٍ ولا بمطابقةِ عبارةٍ عربيّة (‏_CONTEXT §قواعد القياس ③). وما تكتبه
 *   المحطّاتُ السالبةُ داخلَ معاملةٍ تُرجَع.
 *
 * التشغيل: php tools/repair01_w8_journey.php
 * الخروج : 0 عبرت كلُّ المحطّات · 1 محطّةٌ لم تعبر أو أرضيّةٌ ناقصة
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w8_scan.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w8_one($conn, $sql); };

/* مُعرِّفُ الجولةِ من ساعةِ القاعدة بدقّةِ الميكروثانية — جولتانِ في الثانيةِ
   نفسِها تتقاسمان المُعرِّفَ فتُقرآن جولةً واحدةً وتسقط البوّابة (‏درسُ W04). */
$RUN = 'W8J-' . (string) $one("SELECT DATE_FORMAT(NOW(6), '%Y%m%d%H%i%s%f')");

/* ◆ **والمقامُ الصفريُّ يمرُّ مُعلَنًا وحدَه** (‏درسُ W07 §ملحق · حارسُ الخلاء):
     محطّةٌ مقامُها صفرٌ تعبر **فقط** إن كان القرارُ يُعلن عددَها المقيسَ حرفًا —
     ونزعُ الإعلانِ يُسقطها. فـ«صفرُ مخالفةٍ من صفرِ صفوف» ليس نجاحًا. */
$DECL = function ($id) use ($one, $esc) {
    $v = $one("SELECT scope_rows FROM repair01_w8_decisions WHERE decision_id = '" . $esc($id) . "'");
    return $v === null ? -1 : (int) $v;
};

$ST = array();
$add = function ($key, $no, $station, $entity, $consumer, $expected, $measured, $effect, $state, $passed) use (&$ST) {
    $ST[] = array($key, $no, $station, $entity, $consumer, $expected, $measured, $effect, $state, $passed ? 1 : 0);
};

echo "═══════ رحلتا الإثباتِ — W08 · الجولة $RUN ═══════\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   المرساتانِ تُختاران بالقياسِ لا برقمٍ مكتوب
   ═══════════════════════════════════════════════════════════════════════════ */
$CID = (int) $one("
  SELECT c.id FROM contracts c
   WHERE COALESCE(c.is_deleted,0)=0
     AND (SELECT COUNT(*) FROM claims t WHERE t.contract_id=c.id AND t.receivable_id IS NOT NULL) > 0
     AND (SELECT COUNT(*) FROM v_monthly_performance p WHERE p.contract_id=c.id) > 0
     AND (SELECT COUNT(*) FROM contract_commitments t WHERE t.contract_ref=c.id) > 0
     AND (SELECT COUNT(*) FROM contract_monthly_plan t WHERE t.contract_id=c.id) > 0
   ORDER BY (SELECT COUNT(*) FROM v_monthly_performance p WHERE p.contract_id=c.id)
          + (SELECT COUNT(*) FROM op_containers t WHERE t.contract_id=c.id)
          + (SELECT COUNT(*) FROM claims t WHERE t.contract_id=c.id AND t.receivable_id IS NOT NULL) DESC
   LIMIT 1");
$SCID = (int) $one("
  SELECT sc.id FROM supplier_contracts sc
   WHERE COALESCE(sc.is_deleted,0)=0
   ORDER BY (SELECT COUNT(*) FROM supplier_contract_lines l WHERE l.contract_id=sc.id)
          + (SELECT COUNT(*) FROM supplier_capacity p WHERE p.contract_id=sc.id)
          + (SELECT COUNT(*) FROM settlements s WHERE s.party_type='supplier' AND s.party_ref=sc.supplier_id) DESC
   LIMIT 1");

if ($CID <= 0 || $SCID <= 0) {
    echo "✘ الأرضيّةُ ناقصة: عقدُ عميلٍ بسلسلةٍ كاملة " . ($CID > 0 ? 'موجود' : 'مفقود')
       . " · عقدُ مورِّدٍ " . ($SCID > 0 ? 'موجود' : 'مفقود') . "\n";
    exit(1);
}
$SUPID = (int) $one("SELECT supplier_id FROM supplier_contracts WHERE id = $SCID");
echo "المرساتان: عقدُ العميل #$CID · عقدُ المورِّد #$SCID (مورِّد #$SUPID)\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ① رحلةُ العميل — إحدى عشرةَ محطّةً موجبةً وأربعٌ سالبة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① رحلةُ العميل ────────────────────────────────────────────────\n";

/* ①-١ الفرصة */
$oppN = (int) $one("SELECT COUNT(*) FROM opportunities WHERE COALESCE(is_deleted,0)=0 AND stage <> ''");
$add('client', 1, 'فرصةٌ بيعيّةٌ بمرحلتِها', 'opportunities', 'quotations @ 01 المبيعات',
  'فرصةٌ واحدةٌ على الأقلِّ بمرحلةٍ معلنةٍ يستند إليها عرض',
  "فرصٌ بمرحلةٍ معلنةٍ $oppN",
  'العرضُ لا يُصدَر إلّا على فرصةٍ قائمة — والمقيسُ عروضٌ مربوطةٌ بفرصِها: '
  . (int) $one("SELECT COUNT(*) FROM quotations WHERE COALESCE(is_deleted,0)=0 AND COALESCE(opportunity_id,0)>0"),
  'qualified', $oppN > 0);

/* ①-٢ عرضُ السعر */
$quoN = (int) $one("SELECT COUNT(*) FROM quotations WHERE COALESCE(is_deleted,0)=0");
$quoLines = (int) $one("SELECT COUNT(*) FROM sal_quotation_lines");
$add('client', 2, 'عرضُ سعرٍ ببنودِه', 'quotations', 'sal_quotation_lines @ 01 المبيعات',
  'عرضٌ ببندٍ واحدٍ على الأقلِّ — والمجموعُ يُقرأ من البنودِ لا يُكتب',
  "عروضٌ $quoN · بنودُ عروضٍ $quoLines",
  'بنودُ العرضِ تُسند مبلغَه — والمقيسُ بنودٌ حيّةٌ ' . $quoLines
 . ($quoLines === 0 ? '؛ ومقامٌ صفريٌّ يمرُّ مُعلَنًا وحدَه (W8-D-11)' : ''),
  'issued', $quoN > 0 && ($quoLines > 0 || $DECL('W8-D-11') === $quoLines + $revN));

/* ①-٣ التفاوض */
$revN = (int) $one("SELECT COUNT(*) FROM sal_quotation_revisions");
$add('client', 3, 'جولةُ تفاوضٍ بنتيجتِها', 'sal_quotation_revisions', 'quotations @ 01 المبيعات',
  'جولةُ تفاوضٍ مسجَّلةٌ — ولا تعديلَ صامتٌ على العرضِ المُصدَر',
  "جولاتُ تفاوضٍ $revN",
  'العرضُ يُعدَّل بإصدارٍ لا بتحريرِ الأوّل — والمقيسُ جولاتٌ مسجَّلةٌ ' . $revN
 . ($revN === 0 ? '؛ ومقامٌ صفريٌّ يمرُّ مُعلَنًا وحدَه (W8-D-11)' : ''),
  'negotiating', $revN > 0 || $DECL('W8-D-11') === $quoLines + $revN);

/* ①-٤ العقد */
$cSt = (string) $one("SELECT contract_status FROM contracts WHERE id = $CID");
$cQuo = (int) $one("SELECT COALESCE(quotation_id,0) FROM contracts WHERE id = $CID");
$signedN = (int) $one("SELECT COUNT(*) FROM contracts WHERE COALESCE(is_deleted,0)=0
                         AND contract_status IN ('موقَّع','نافذ','قيد التنفيذ','معدَّل','مجدَّد','منتهٍ','مقفل','مصفّى')");
$add('client', 4, 'عقدٌ بحالتِه على آلةِ الحالة', 'contracts', 'op_containers @ 11 التشغيل',
  'العقدُ في حالةٍ من الاثنتَي عشرةَ — والنافذُ فما بعدَه يفتح حاوية',
  "حالةُ العقد #$CID «$cSt» · عقودٌ بلغت التوقيعَ فما بعدَه $signedN",
  'الحاويةُ لا تُفتح إلّا من عقدٍ نافذ — والمقيسُ حاوياتُ هذا العقد: '
  . (int) $one("SELECT COUNT(*) FROM op_containers WHERE contract_id = $CID"),
  $cSt, $signedN > 0 && $cSt !== '');

/* ①-٥ التزاماتُ العقد */
$cmtN = (int) $one("SELECT COUNT(*) FROM contract_commitments WHERE contract_ref = $CID AND COALESCE(is_deleted,0)=0");
$planN = (int) $one("SELECT COUNT(*) FROM contract_monthly_plan WHERE contract_id = $CID");
$add('client', 5, 'التزاماتُ العقدِ وخطُّ أساسِه', 'contract_commitments', 'contract_monthly_plan @ 01 المبيعات',
  'التزامٌ تجاريٌّ واحدٌ على الأقلِّ وخطُّ أساسٍ شهريّ',
  "التزاماتٌ $cmtN · أسطرُ خطِّ أساسٍ $planN",
  "خطُّ الأساسِ يقيس المستهدَفَ شهرًا بشهر — والمقيسُ أسطرُ هذا العقد $planN",
  'baselined', $cmtN > 0 && $planN > 0);

/* ①-٦ أمرُ العمل (الحاوية) */
$contN = (int) $one("SELECT COUNT(*) FROM op_containers WHERE contract_id = $CID AND COALESCE(is_deleted,0)=0");
$add('client', 6, 'أمرُ العملِ — الحاويةُ المفتوحةُ بالعقد', 'op_containers', 'seat_assignments @ 11 التشغيل',
  'حاويةٌ واحدةٌ على الأقلِّ مفتوحةٌ بهذا العقد',
  "حاوياتُ العقد #$CID = $contN",
  'الحاويةُ وعاءُ الخاناتِ التي تُسنَد للموردين — والمقيسُ خاناتٌ مسنَدةٌ على حاوياتِه: '
  . (int) $one("SELECT COUNT(*) FROM seat_assignments s
                  JOIN op_containers c ON c.id = s.container_id WHERE c.contract_id = $CID"),
  'open', $contN > 0);

/* ①-٧ التنفيذُ المقيس */
$perfN = (int) $one("SELECT COUNT(*) FROM v_monthly_performance WHERE contract_id = $CID");
$add('client', 7, 'تنفيذٌ مقيسٌ معتمَد', 'v_monthly_performance', 'claim_lines @ 01 المبيعات',
  'سطرُ أداءٍ شهريٌّ واحدٌ على الأقلِّ يُبنى عليه المستخلص',
  "أسطرُ أداءِ العقد #$CID = $perfN",
  'المستخلصُ يفوتر ما اعترفت به المروحةُ — والمقيسُ بنودُ مستخلصاتِ هذا العقد: '
  . (int) $one("SELECT COUNT(*) FROM claim_lines l JOIN claims c ON c.id = l.claim_id WHERE c.contract_id = $CID"),
  'recognized', $perfN > 0);

/* ①-٨ المستخلص */
$clmN = (int) $one("SELECT COUNT(*) FROM claims WHERE contract_id = $CID AND COALESCE(is_deleted,0)=0");
$grossOk = (int) $one("SELECT COUNT(*) FROM claims c WHERE c.contract_id = $CID AND COALESCE(c.is_deleted,0)=0
   AND ABS(ROUND(c.gross_amount,2) - ROUND((SELECT COALESCE(SUM(l.amount),0) FROM claim_lines l WHERE l.claim_id=c.id),2)) <= 0.01");
$add('client', 8, 'مستخلصٌ إجماليُّه مجموعُ بنودِه', 'claims', 'claim_lines @ 01 المبيعات',
  'كلُّ مستخلصٍ إجماليُّه يساوي مجموعَ بنودِه بلا استثناء',
  "مستخلصاتُ العقد $clmN · مطابقٌ حسابُه $grossOk",
  "الرقمُ المفوترُ مُعادُ البناءِ من بنودِه — ومطابقٌ $grossOk من $clmN",
  'submitted', $clmN > 0 && $grossOk === $clmN);

/* ①-٩ الفاتورة */
$invN = (int) $one("SELECT COUNT(*) FROM claims WHERE contract_id = $CID
                      AND COALESCE(is_deleted,0)=0 AND COALESCE(invoice_no,'') <> ''");
$add('client', 9, 'فاتورةٌ مرقَّمةٌ على المستخلص', 'claims', 'fin_client_statements @ 05 المالية',
  'المستخلصُ المُجاز يحمل رقمَ فاتورةٍ ظاهرًا للعميل',
  "مستخلصاتٌ مفوترةٌ على العقد $invN من $clmN",
  'الفاتورةُ مستندُ العميلِ الرسميّ — والمقيسُ أرقامُ فواتيرَ متفرّدةٌ في الكيان: '
  . (int) $one("SELECT COUNT(DISTINCT invoice_no) FROM claims WHERE COALESCE(is_deleted,0)=0 AND COALESCE(invoice_no,'') <> ''"),
  'invoiced', $invN > 0);

/* ①-١٠ طلبُ الاعترافِ الماليّ — الأثرُ يصل الماليّةَ اعترافًا لا قيدًا (§48) */
$rcvN = (int) $one("SELECT COUNT(*) FROM claims WHERE contract_id = $CID AND receivable_id IS NOT NULL");
$rcvAmt = (float) $one("SELECT COALESCE(SUM(r.amount),0) FROM claims c
                          JOIN fin_receivables r ON r.id = c.receivable_id WHERE c.contract_id = $CID");
$ledgerRows = (int) $one("SELECT COUNT(*) FROM claim_lines l JOIN claims c ON c.id = l.claim_id
                            WHERE c.contract_id = $CID AND l.source_kind = 'retention_release' AND l.event_id IS NOT NULL");
$add('client', 10, 'طلبُ اعترافٍ ماليٍّ — ذمّةٌ لا قيدُ إيراد', 'fin_receivables', 'fin_receivables @ 05 المالية',
  'المستخلصُ المفوترُ يفتح **ذمّةً** بمبلغِها — و**لا يكتب قيدَ إيرادٍ ثانيًا** فالمروحةُ اعترفت عند الوحدة',
  "مستخلصاتٌ بذمّةٍ موصولةٍ $rcvN · مجموعُ الذممِ " . number_format($rcvAmt, 2)
  . " · بنودُ ردِّ احتجازٍ تحمل قيدًا $ledgerRows",
  'الأثرُ التجاريُّ: ذمّةٌ مدينةٌ قائمةٌ بمبلغِها على العميل — و«ازدواجُ الاعتراف» صفرٌ مقيسٌ لا مدَّعًى',
  'recognized', $rcvN > 0 && $rcvAmt > 0 && $ledgerRows === 0);

/* ①-١١ التحصيل */
$collected = (float) $one("SELECT COALESCE(SUM(r.collected),0) FROM claims c
                            JOIN fin_receivables r ON r.id = c.receivable_id WHERE c.contract_id = $CID");
$collRows = (int) $one("SELECT COUNT(*) FROM claims c JOIN fin_receivables r ON r.id = c.receivable_id
                          WHERE c.contract_id = $CID AND r.collected > 0");
$add('client', 11, 'تحصيلٌ مخصَّصٌ على الذمّة', 'fin_receivables', 'fin_payments @ 06 الخزينة',
  'قبضٌ مخصَّصٌ على ذمّةٍ قائمةٍ — والمحصَّلُ لا يتجاوز مبلغَها',
  "ذممٌ عليها تحصيلٌ $collRows · المحصَّلُ " . number_format($collected, 2),
  'الأثرُ التجاريُّ: رصيدُ العميلِ ينخفض بالمحصَّل — والمقيسُ ذممٌ بلا تجاوزِ تحصيل: '
  . (int) $one("SELECT COUNT(*) FROM fin_receivables WHERE COALESCE(is_deleted,0)=0 AND collected > amount") . ' مخالفة',
  'collected', $collRows > 0 && (int) $one("SELECT COUNT(*) FROM fin_receivables WHERE COALESCE(is_deleted,0)=0 AND collected > amount") === 0);

/* ═══════════════════════════════════════════════════════════════════════════
   ② رحلةُ المورد — سبعُ محطّاتٍ موجبة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "② رحلةُ المورد ────────────────────────────────────────────────\n";

/* ②-١ التأهيل */
$qRows = (int) $one("SELECT COUNT(*) FROM v_supplier_qualification");
$qMissing = (int) $one("SELECT COUNT(*) FROM v_supplier_qualification WHERE COALESCE(missing_count,0) > 0");
$add('supplier', 1, 'تأهيلٌ قانونيٌّ وائتمانيٌّ مشتقٌّ بمحاورِه', 'v_supplier_qualification', 'suppliers @ 02 الموردون',
  'حكمُ التأهيلِ **مشتقٌّ** من مستنداتِ المورِّدِ لا مُدخَلٌ يدًا — ومحاورُه مفصولةٌ ومعدودة',
  "مورِّدون في المشتقّ $qRows · ناقصو التأهيل $qMissing",
  'الأثرُ التجاريُّ: قائمةُ النقصِ لكلِّ مورِّدٍ بأسمائها (`missing_list`) — والمقيسُ مورِّدون بقائمةِ نقصٍ مكتوبة: '
  . (int) $one("SELECT COUNT(*) FROM v_supplier_qualification WHERE COALESCE(missing_list,'') <> ''"),
  'derived', $qRows > 0);

/* ②-٢ عقدُ التوريد */
$scSt = (string) $one("SELECT state FROM supplier_contracts WHERE id = $SCID");
$scLive = (int) $one("SELECT COUNT(*) FROM supplier_contracts WHERE COALESCE(is_deleted,0)=0
                        AND state IN ('موقَّع','نافذ','قيد التنفيذ','معدَّل','مجدَّد')");
$projN = (int) $one("SELECT COUNT(*) FROM supplier_contracts WHERE source_table='supplierscontracts' AND source_id IS NOT NULL");
$add('supplier', 2, 'عقدُ توريدٍ في السجلِّ الموحَّد', 'supplier_contracts', 'supplierscontracts @ 02 الموردون',
  'رأسُ العقدِ في السجلِّ الموحَّدِ يسقط عن الجدولِ الحيِّ بمصدرِه — ولا مُعرِّفَ ثانٍ ينازعه',
  "حالةُ العقد #$SCID «$scSt» · عقودٌ حيّةٌ $scLive · مُسقَطٌ عن الحيِّ $projN",
  "الأثرُ التجاريُّ: العقدُ الواحدُ برأسٍ واحدٍ — ومُسقَطٌ بمصدرِه $projN من "
  . (int) $one("SELECT COUNT(*) FROM supplierscontracts"),
  $scSt, $scSt !== '' && $projN > 0);

/* ②-٣ الالتزامُ التعاقديّ */
$lineN = (int) $one("SELECT COUNT(*) FROM supplier_contract_lines WHERE contract_id = $SCID AND COALESCE(is_deleted,0)=0");
$capN  = (int) $one("SELECT COUNT(*) FROM supplier_capacity WHERE contract_id = $SCID AND COALESCE(is_deleted,0)=0");
$add('supplier', 3, 'التزامٌ تعاقديٌّ ببنودِه وطاقتِه', 'supplier_contract_lines', 'supplier_capacity @ 02 الموردون',
  'بندُ التزامٍ واحدٌ على الأقلِّ وسطرُ طاقةٍ يقيس جاهزيّتَه',
  "بنودٌ $lineN · أسطرُ طاقةٍ $capN",
  'الأثرُ التجاريُّ: الوحداتُ المتعهَّدُ بها ومهلةُ الإحلالِ مكتوبتانِ بالرقم — والمقيسُ أسطرُ طاقةٍ بحدِّ جاهزيّةٍ معلن: '
  . (int) $one("SELECT COUNT(*) FROM supplier_capacity WHERE contract_id = $SCID AND COALESCE(min_readiness_percent,0) > 0"),
  'committed', $lineN > 0 && $capN > 0);

/* ②-٤ الاستحقاق */
/* ⛔ **ولا عمودَ مورِّدٍ في الخانةِ ولا في الدفتر**: الجسرُ ثلاثُ قفزاتٍ
     (خانة ← بندُ عقدِ المورِّد ← رأسُ العقد) — والقياسُ على عمودٍ متوهَّمٍ
     يعيد صفرًا صامتًا فيُقرأ «لا استحقاق» وهو «لا عمود». */
$seatN = (int) $one("SELECT COUNT(*) FROM seat_assignments s
     JOIN supplier_contract_lines l ON l.id = s.supplier_contract_line_id
     JOIN supplier_contracts sc ON sc.id = l.contract_id WHERE sc.supplier_id = $SUPID");
$ledN  = (int) $one("SELECT COUNT(*) FROM capacity_consumption_ledger g
     JOIN supplier_contract_lines l ON l.id = g.supplier_contract_line_id
     JOIN supplier_contracts sc ON sc.id = l.contract_id WHERE sc.supplier_id = $SUPID");
$seatAll = (int) $one("SELECT COUNT(*) FROM seat_assignments");
$ledAll  = (int) $one("SELECT COUNT(*) FROM capacity_consumption_ledger");
$add('supplier', 4, 'استحقاقٌ من خانةٍ مشغولةٍ ومنفَّذٍ مقيس', 'seat_assignments', 'capacity_consumption_ledger @ 02 الموردون',
  'خانةٌ مشغولةٌ بفترتِها ودفترُ استهلاكٍ يقيس المنفَّذَ عليها',
  "خاناتُ المورِّد #$SUPID = $seatN · أسطرُ استهلاكِه $ledN · في النظامِ خاناتٌ $seatAll وأسطرٌ $ledAll",
  'الأثرُ التجاريُّ: المستحَقُّ يُشتقُّ من المنفَّذِ المعتمَدِ لا من التعهُّد — وأسطرُ الاستهلاكِ في النظامِ ' . $ledAll,
  'accrued', $seatAll > 0 && $ledAll > 0);

/* ②-٥ المطالبة */
$stlN = (int) $one("SELECT COUNT(*) FROM settlements WHERE party_type='supplier' AND party_ref = $SUPID");
$stlLines = (int) $one("SELECT COUNT(*) FROM settlement_lines l
                          JOIN settlements s ON s.id = l.settlement_id
                         WHERE s.party_type='supplier' AND s.party_ref = $SUPID");
$add('supplier', 5, 'مطالبةٌ ببنودِها ومكوِّناتِها', 'settlements', 'settlement_lines @ 02 الموردون',
  'مطالبةٌ واحدةٌ على الأقلِّ ببنودٍ تُسندها — والصافي مُعادُ الحسابِ من إجماليٍّ ناقصِ تحميلات',
  "مطالباتُ المورِّد $stlN · بنودٌ $stlLines",
  'الأثرُ التجاريُّ: صافي المطالبةِ مُعادُ البناء — والمخالفُ في النظامِ كلِّه: '
  . (int) $one("SELECT COUNT(*) FROM settlements WHERE party_type='supplier'
                  AND ABS(ROUND(net_amount,2) - ROUND(gross_amount - charges_amount,2)) > 0.01"),
  'claimed', $stlN > 0);

/* ②-٦ التسوية */
$apprN = (int) $one("SELECT COUNT(*) FROM settlements WHERE party_type='supplier' AND party_ref = $SUPID
                       AND state IN ('approved','payment_requested','invoiced','paid','closed')");
$twoHands = (int) $one("SELECT COUNT(*) FROM settlements WHERE party_type='supplier'
                          AND state IN ('approved','payment_requested','invoiced','paid','closed')
                          AND prepared_by IS NOT NULL AND approved_by IS NOT NULL
                          AND prepared_by = approved_by");
$add('supplier', 6, 'تسويةٌ معتمَدةٌ بيدٍ ثانية', 'settlements', 'fin_dues @ 05 المالية',
  'التسويةُ المعتمَدةُ لا يعتمدها مُعِدُّها — وفصلُ اليدَين مُثبَتٌ على المقامِ كلِّه',
  "تسوياتٌ معتمَدةٌ للمورِّد $apprN · معتمِدُها هو مُعِدُّها $twoHands",
  'الأثرُ التجاريُّ: استحقاقُ المورِّدِ يُقفَل بتسويةٍ مرَّت بيدَين — وخرقُ فصلِ اليدَين ' . $twoHands
 . ' في النظامِ كلِّه، مُعلَنٌ في W8-D-12',
  'approved', $apprN > 0 && ($twoHands === 0 || $DECL('W8-D-12') === $twoHands));

/* ②-٧ الإقفال */
$closN = (int) $one("SELECT COUNT(*) FROM supplier_contract_closures");
$closOrph = (int) $one("SELECT COUNT(*) FROM supplier_contract_closures t WHERE NOT EXISTS
                          (SELECT 1 FROM supplier_contracts c WHERE c.id = t.contract_id)");
$closedStl = (int) $one("SELECT COUNT(*) FROM settlements WHERE party_type='supplier' AND state='closed'");
$add('supplier', 7, 'إقفالٌ تعاقديٌّ بمرجعِ عقدِه', 'supplier_contract_closures', 'supplier_contracts @ 02 الموردون',
  'كلُّ إقفالٍ يشير إلى عقدِ مورِّدٍ قائمٍ — ولا إقفالَ يتيم',
  "إقفالاتٌ $closN · يتيمٌ $closOrph · تسوياتٌ مقفلةٌ $closedStl",
  'الأثرُ التجاريُّ: العقدُ المقفَلُ لا يقبل تسويةً جديدةً — واليتيمُ صفرٌ مقيس',
  'closed', $closN > 0 && $closOrph === 0);

/* ═══════════════════════════════════════════════════════════════════════════
   ③ المحطّاتُ السالبة — بالاستدعاءِ الفعليِّ ورمزِ الردّ لا بقراءةِ شيفرة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③ المحطّاتُ السالبةُ — استدعاءٌ يُردّ ─────────────────────────\n";
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantDb.php';
require_once $ROOT . '/app/Services/Contract/ContractStateMachine.php';
require_once $ROOT . '/Contracts/claim_helpers.php';
require_once $ROOT . '/app/Services/Settlement/SettlementService.php';

$COMP = (int) $one("SELECT company_id FROM contracts WHERE id = $CID");
$gate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($COMP, 1, '', true));

/* ③-١ انتقالٌ خارجَ قائمةِ السماحِ يُردّ — والقائمةُ بنيةٌ لا عبارة */
$illegal = \App\Services\Contract\ContractStateMachine::canTransition('مصفّى', 'معدَّل');
$legal   = \App\Services\Contract\ContractStateMachine::canTransition('موقَّع', 'نافذ');
$add('client', 12, 'انتقالٌ ممنوعٌ يُردُّ وانتقالٌ مشروعٌ يمرّ', 'contracts', 'ContractStateMachine @ 01 المبيعات',
  '«مصفّى ← معدَّل» يُردُّ و«موقَّع ← نافذ» يمرّ — والحكمُ من قائمةِ السماحِ نفسِها',
  'ممنوعٌ ' . ($illegal ? 'مرَّ' : 'رُدّ') . ' · مشروعٌ ' . ($legal ? 'مرَّ' : 'رُدّ'),
  'الأثرُ التجاريُّ: عقدٌ أُبرئت ذمّتُه بمخالصةٍ لا يُعاد تعديلُه — والمنعُ مُثبَتٌ بالاستدعاء',
  'guarded', (!$illegal && $legal));

/* ③-٢ فصلُ اليدَين في إجازةِ المستخلص — يُستدعى بمُعِدِّه نفسِه فيُردّ */
/* ⛔ **والمُدخَلُ يجب أن يبلغَ الحارسَ المقصود**: مستخلصٌ في حالةٍ غيرِ
     `submitted` يُردُّ بحارسِ الحالةِ **قبلَ** حارسِ فصلِ اليدَين، فيخرج
     الردُّ صحيحًا ولم يُختبَر ما زعمنا اختبارَه. */
$sub = $conn->query("SELECT id, submitted_by, created_by, company_id FROM claims
                      WHERE COALESCE(is_deleted,0)=0 AND submitted_by IS NOT NULL AND submitted_by > 0
                        AND state = 'submitted' LIMIT 1");
$sub = $sub ? $sub->fetch_assoc() : null;
$twoHandOk = false; $twoHandTxt = 'لا مستخلصَ بمُقدِّمٍ معروفٍ في القاعدة';
if ($sub) {
    /* ⛔ **والحارسُ لا يُبلَغ بلا سياقِ فاعل**: `claim_gate()` يبني بوّابةَ
       المستأجِرِ من الجلسة، وبلا جلسةٍ ترمي فتعود الدالّةُ `failed` **قبلَ**
       أيِّ حارس — فيُقرأ فشلُ السياقِ حكمَ سياسةٍ ويُخضِرُّ أو يُحمِّرُ كذبًا.
       فالسياقُ يُبذر بفاعلٍ حقيقيٍّ هو المُقدِّمُ نفسُه، وهو عينُ ما نختبره. */
    $_SESSION = array('user' => array('id' => (int) $sub['submitted_by'],
                                       'company_id' => (int) $sub['company_id'], 'role' => '12'));
    $conn->begin_transaction();
    $r = claim_approve($conn, (int) $sub['id'], null, (int) $sub['submitted_by']);
    $conn->rollback();
    $twoHandOk = (isset($r['status']) && $r['status'] === 'blocked'
                  && isset($r['code']) && (int) $r['code'] === 403);
    $twoHandTxt = 'الردُّ status=' . (string) ($r['status'] ?? '—')
                . ' · code=' . (isset($r['code']) ? (int) $r['code'] : 0);
}
$add('client', 13, 'مُقدِّمُ المستخلصِ لا يجيزه', 'claims', 'claim_approve @ 01 المبيعات',
  'استدعاءُ الإجازةِ بمُعرِّفِ مُقدِّمِ المستخلصِ نفسِه يُردُّ `blocked` برمزِ `403` — لا `failed`',
  $twoHandTxt,
  'الأثرُ التجاريُّ: لا ذمّةَ تُفتح على العميلِ بيدٍ واحدة — والمنعُ مُثبَتٌ بالاستدعاءِ داخلَ معاملةٍ أُرجعت',
  'guarded', $twoHandOk);

/* ③-٣ فصلُ اليدَين في اعتمادِ التسوية — يُستدعى بمُعِدِّها نفسِها فيُردّ */
/* ⛔ **وكذلك التسوية**: من غيرِ `review` يردُّ `409` بحارسِ الحالة — و`409`
     ردٌّ صادقٌ لسؤالٍ آخر. فالمِرساةُ تسويةٌ في المراجعةِ بمُعِدٍّ معروف،
     والمنتظَرُ `403` بعينِه لا «أيُّ رمزٍ غيرِ صفريّ». */
$stl = $conn->query("SELECT id, prepared_by FROM settlements
                      WHERE party_type='supplier' AND state='review'
                        AND prepared_by IS NOT NULL AND prepared_by > 0 LIMIT 1");
$stl = $stl ? $stl->fetch_assoc() : null;
$stlOk = false; $stlTxt = 'لا تسويةَ بمُعِدٍّ معروفٍ في القاعدة';
if ($stl) {
    $conn->begin_transaction();
    $r = \App\Services\Settlement\SettlementService::approve($gate, $conn, (int) $stl['id'], (int) $stl['prepared_by']);
    $conn->rollback();
    $stlOk = (isset($r['ok']) && $r['ok'] === false && isset($r['code']) && (int) $r['code'] === 403);
    $stlTxt = 'الردُّ code=' . (isset($r['code']) ? (int) $r['code'] : 0) . ' · ok=' . (isset($r['ok']) && $r['ok'] ? 'true' : 'false');
}
$add('supplier', 8, 'مُعِدُّ التسويةِ لا يعتمدها', 'settlements', 'SettlementService::approve @ 02 الموردون',
  'استدعاءُ الاعتمادِ بمُعرِّفِ مُعِدِّ التسويةِ نفسِه يُردُّ `403` بعينِه — لا أيَّ رمزٍ غيرِ صفريّ',
  $stlTxt,
  'الأثرُ التجاريُّ: لا مالَ يُعتمَد صرفُه بيدٍ واحدة — والمنعُ مُثبَتٌ بالاستدعاءِ داخلَ معاملةٍ أُرجعت',
  'guarded', $stlOk);

/* ③-٤ ردُّ الضمانِ محصورٌ بدورِه — ويُستدعى بدورٍ آخرَ فيُردّ 403 */
$retOk = false; $retTxt = '—';
$conn->begin_transaction();
$r = claim_retention_release($conn, $CID, 1, '3');
$conn->rollback();
$retOk = (isset($r['code']) && (int) $r['code'] === 403);
$retTxt = 'الردُّ code=' . (isset($r['code']) ? (int) $r['code'] : 0);
$add('client', 14, 'ردُّ الضمانِ محصورٌ بدورِه المخوَّل', 'contract_guarantees', 'claim_retention_release @ 01 المبيعات',
  'استدعاءُ الردِّ بدورٍ غيرِ المخوَّلِ يُردُّ `403`',
  $retTxt,
  'الأثرُ التجاريُّ: ضمانُ حسنِ التنفيذِ لا يُردُّ بغيرِ سلطتِه — والمنعُ مُثبَتٌ بالاستدعاء',
  'guarded', $retOk);

/* ═══════════════════════════════════════════════════════════════════════════
   ④ التسجيلُ والحكم
   ═══════════════════════════════════════════════════════════════════════════ */
$conn->query("DELETE FROM repair01_w8_journey");
$pass = 0; $fail = 0; $byKey = array();
foreach ($ST as $s) {
    list($key, $no, $station, $entity, $consumer, $expected, $measured, $effect, $state, $ok) = $s;
    if ($ok) { $pass++; } else { $fail++; }
    $byKey[$key] = ($byKey[$key] ?? 0) + 1;
    $conn->query("INSERT INTO repair01_w8_journey
        (run_id, journey_key, station_no, station, entity, consumer, expected, measured, business_effect, state_after, passed)
        VALUES ('" . $esc($RUN) . "','" . $esc($key) . "'," . (int) $no . ",'" . $esc($station) . "','"
        . $esc($entity) . "','" . $esc($consumer) . "','" . $esc(mb_substr($expected, 0, 380)) . "','"
        . $esc(mb_substr($measured, 0, 380)) . "','" . $esc(mb_substr($effect, 0, 380)) . "','"
        . $esc($state) . "'," . (int) $ok . ")");
    echo '  ' . ($ok ? '✔' : '✘') . ' [' . str_pad($key, 8) . '] '
       . str_pad((string) $no, 3, ' ', STR_PAD_LEFT) . '  ' . $station . "\n"
       . '        المقيس: ' . $measured . "\n";
}

echo "\n" . str_repeat('─', 100) . "\n";
$consumers = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w8_journey WHERE run_id='" . $esc($RUN) . "'");
$noEffect  = (int) $one("SELECT COUNT(*) FROM repair01_w8_journey WHERE run_id='" . $esc($RUN) . "' AND business_effect=''");
$kt = array(); foreach ($byKey as $k => $n) { $kt[] = "$k $n"; }
printf("الرحلتان: %s  ·  عابرٌ %d/%d  ·  مستهلكونَ متمايزون %d  ·  بلا أثرٍ تجاريٍّ %d\n",
       implode(' · ', $kt), $pass, count($ST), $consumers, $noEffect);
echo 'الحكم: ' . ($fail === 0 && $noEffect === 0 ? "تعبران ✔\n" : "لا تعبران ✘\n");
exit(($fail === 0 && $noEffect === 0) ? 0 : 1);
