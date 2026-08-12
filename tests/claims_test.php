<?php
/**
 * tests/claims_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 * حارس المستخلص: من الوحدة المعتمدة إلى ذمّة العميل (UX-08 §5.2 · §8.2 · FES §8).
 *
 * الحرّاسُ الثمانية:
 *   ① التوليدُ من **قيود الإيراد المعترَف بها** — صفرُ إدخالِ كمية
 *   ② العميلُ مشتقٌّ بالسلسلة (تشغيل → مشروع → عميل) لا مُدخل
 *   ③ لا وحدةَ تُستخلص مرتين (لا في المستخلص الواحد ولا في مستخلصين)
 *   ④ مستخلصٌ واحدٌ لكل (عقد × فترة) — إعادةُ التوليد تعيد المرجعَ القائم
 *   ⑤ الرفعُ ثم الإجازة → فاتورةٌ ضريبيةٌ + ذمّةٌ مدينة — **بلا إيرادٍ ثانٍ**
 *   ⑥ العطالة: إجازتان لا تفتحان ذمّتين
 *   ⑦ **حارسُ التحويل**: يومٌ لم تحوّله المالية لا يُستخلص (فلا ذمّةَ تتخطّى السلسلة)
 *   ⑧ يدان لا يدٌ واحدة: من رفع لا يُجيز
 *
 * ⚠️ **أُعيد ضبطُه 2026-07-28** على قرارَي المالك: «المروحةُ تعترف والمستخلصُ
 * يفوتر» و«المبيعاتُ تُنشئ والمالية تعتمد». فمدخلُ المستخلص لم يبقَ صفَّ دوامٍ
 * خامًا بل **قيدَ إيرادٍ حوّلته المالية** — ولذا يبذر الحارسُ التحويلَ نفسَه.
 *
 * يبذر عقدَه ودوامَه ويكنس الكلَّ — لا يمسّ عقدًا حقيقيًّا ولا ذمّةً قائمة.
 * التشغيل: php tests/claims_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
  * ⇐ شواهدُ أحكامٍ: FIXA-0008-ب · FIXC-0076-ب
 * (رُبطت بمراجعةٍ خصمٍ 2026-08-12 — الحجّةُ وسببُ قبولِها في docs/fix_progress/BINDINGS.md)
*/

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

// سياقُ المستأجر قبل أول استعمالٍ للبوابة (گوتشا مقيسة: scopedQuery ترفض بلا جلسة)
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'claims test');

require_once dirname(__DIR__) . '/Contracts/claim_helpers.php';

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$CO = 4; $ACTOR = 1;
$MARK = 'CLM' . getmypid();
$scalar = function ($sql) use ($conn) { $r = $conn->query($sql); $v = $r ? $r->fetch_row() : null; return $v ? $v[0] : 0; };

// ── بذرُ بيئةٍ كاملة: عميل → مشروع → عقد → سطر تسعير → تشغيل → دوام ────────
$conn->query("INSERT INTO clients (company_id, client_code, client_name, status, created_at)
              VALUES ({$CO}, 'TSTC-{$MARK}', 'عميلٌ اختباري {$MARK}', 'active', NOW())");
$CLIENT = $conn->insert_id;
$conn->query("INSERT INTO project (company_id, client_id, name) VALUES ({$CO}, {$CLIENT}, 'مشروعٌ اختباري {$MARK}')");
$PROJ = $conn->insert_id;
$conn->query("INSERT INTO contracts (company_id, project_id, price_currency_contract, created_at)
              VALUES ({$CO}, {$PROJ}, 'جنيه', NOW())");
$CONTRACT = $conn->insert_id;
$conn->query("INSERT INTO contractequipments (company_id, contract_id, equip_type, equip_unit, equip_price, equip_price_currency, created_at)
              VALUES ({$CO}, {$CONTRACT}, 'حفار-{$MARK}', 'ساعة', 50.00, 'جنيه', NOW())");
$conn->query("INSERT INTO operations (company_id, equipment, equipment_type, project_id, contract_id, op_state)
              VALUES ({$CO}, 'EQ-{$MARK}', 'حفار-{$MARK}', {$PROJ}, {$CONTRACT}, 'تعمل')");
$OP = $conn->insert_id;

$tsIds = array();
foreach (array(array('2027-08-02', 8.0), array('2027-08-03', 6.5), array('2027-08-04', 10.0)) as $d) {
    $conn->query("INSERT INTO timesheet (company_id, operator, `date`, executed_hours, tons_count, meters_count, status)
                  VALUES ({$CO}, {$OP}, '{$d[0]}', {$d[1]}, 0, 0, 1)");
    $tsIds[] = $conn->insert_id;
}
// دوامٌ خارج الفترة وآخرُ غيرُ معتمد — لا يدخلان المستخلص
$conn->query("INSERT INTO timesheet (company_id, operator, `date`, executed_hours, tons_count, meters_count, status)
              VALUES ({$CO}, {$OP}, '2027-09-01', 7.0, 0, 0, 1)");
$tsOut = $conn->insert_id;
$conn->query("INSERT INTO timesheet (company_id, operator, `date`, executed_hours, tons_count, meters_count, status)
              VALUES ({$CO}, {$OP}, '2027-08-05', 9.0, 0, 0, 0)");
$tsUnapproved = $conn->insert_id;

// ── بذرُ التحويل المالي: قيدُ إيرادٍ معترَفٌ به لكل يومٍ + رابطُه الأبوي ─────
// هذا **مدخلُ المستخلص الجديد**: وجودُ الرابط يعني أن اليومَ اكتمل اعتمادُه
// الرباعي وحوّلته المالية. يُنشر بالناشر لا بإدراجٍ خام (فالجذرُ يبقى متسقًا).
// ⚠️ الترتيب: **يُبذَر بعد لقطة خط الأساس** لا قبلها — وإلا حُسبت صفوفُ البذر
//    في الأساس وكنسُها في النهاية يبدو انزياحًا (وقع فعلًا).
require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';
$revIds = array();
$seedRevenue = function ($tsId, $date, $hours) use ($conn, $CO, $CLIENT, $PROJ, $CONTRACT, $ACTOR, &$revIds) {
    $amount = round($hours * 50.0, 2);
    $r = \App\Core\EventPublisher::publish($conn, array(
        'event_key' => 'revenue.unit.recognized', 'category' => 'financial',
        'source_module' => 'movement', 'company_id' => $CO,
        'entity_type' => 'timesheet', 'entity_id' => intval($tsId),
        'occurred_at' => $date . ' 00:00:00', 'created_by' => $ACTOR,
        'idempotency_key' => 'fanout:ts:' . intval($tsId) . ':revenue',
        'legacy_event_type' => 'revenue', 'amount' => $amount, 'currency' => 'SDG',
        'quantity' => $hours, 'unit' => 'hour', 'source_ref' => 'TS-' . intval($tsId),
        'project_id' => $PROJ, 'customer_entity_id' => $CLIENT, 'contract_id' => $CONTRACT,
        'notes' => 'بذرُ حارس المستخلص',
        'payload' => array('unit_price' => 50.0, 'qty' => $hours, 'unit' => 'hour'),
    ));
    $eid = intval($r['id']);
    $revIds[] = $eid;
    $conn->query("INSERT INTO fin_event_links (company_id, parent_kind, parent_ref, effect_type,
                  target_table, target_id, event_id)
                  VALUES ({$CO}, 'timesheet', " . intval($tsId) . ", 'revenue_event',
                          'fin_financial_events', {$eid}, {$eid})");
    return $eid;
};
$claimIds = array();
$cleanup = function () use ($conn, $CLIENT, $PROJ, $CONTRACT, $OP, $tsIds, $tsOut, $tsUnapproved, &$claimIds, &$revIds) {
    foreach ($claimIds as $cid) {
        $conn->query("DELETE FROM fin_receivables WHERE doc_ref IN (SELECT invoice_no FROM claims WHERE id=" . intval($cid) . ")");
        $conn->query("DELETE FROM fin_financial_events WHERE entity_type='claim' AND entity_id=" . intval($cid));
        $conn->query("DELETE FROM ems_business_events WHERE entity_type='claim' AND entity_id=" . intval($cid));
        $conn->query("DELETE FROM claim_lines WHERE claim_id=" . intval($cid));
        // M-03: `tax_invoices.claim_id` مفتاحٌ أجنبيٌّ بـ**RESTRICT** (نصُّ
        // ENT-03 §7) — فالمستندُ الضريبيُّ يُكنس قبل مستخلصه، وإلا بقي المستخلصُ
        // حيًّا صامتًا وانكسر خطُّ الأساس في التشغيل التالي.
        $conn->query("DELETE FROM tax_invoices WHERE claim_id=" . intval($cid));
        $conn->query("DELETE FROM claims WHERE id=" . intval($cid));
    }
    $all = array_merge($tsIds, array($tsOut, $tsUnapproved));
    // الإسقاطُ قبل الجذر (root_event_id يمنع العكس) ثم روابطُه
    $conn->query("DELETE FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref IN ("
        . implode(',', array_map('intval', $all)) . ")");
    foreach ($revIds as $eid) {
        $conn->query("DELETE FROM fin_financial_events WHERE id=" . intval($eid));
    }
    $conn->query("DELETE FROM ems_business_events WHERE entity_type='timesheet' AND entity_id IN ("
        . implode(',', array_map('intval', $all)) . ")");
    $conn->query("DELETE FROM timesheet WHERE id IN (" . implode(',', array_map('intval', $all)) . ")");
    $conn->query("DELETE FROM operations WHERE id=" . intval($OP));
    $conn->query("DELETE FROM contractequipments WHERE contract_id=" . intval($CONTRACT));
    $conn->query("DELETE FROM contracts WHERE id=" . intval($CONTRACT));
    $conn->query("DELETE FROM project WHERE id=" . intval($PROJ));
    $conn->query("DELETE FROM clients WHERE id=" . intval($CLIENT));
};
register_shutdown_function($cleanup);

fwrite(STDOUT, "  (بُذرت بيئةٌ: عميل#{$CLIENT} → مشروع#{$PROJ} → عقد#{$CONTRACT} · سعر 50/ساعة · 3 أيام معتمدة = 24.5 ساعة)\n");

$recv0 = (int) $scalar("SELECT COUNT(*) FROM fin_receivables");
$ev0   = (int) $scalar("SELECT COUNT(*) FROM fin_financial_events");
// خطُّ أساسٍ لا صفر: الجدولُ لم يعد بكرًا منذ أول مستخلصٍ حيٍّ في تاريخ النظام
// (CLM-2026-0001 · تفعيل CON-02) — انزياحُ توقُّعٍ مصنَّفٌ في أمر التنفيذ §3.
$claims0 = (int) $scalar("SELECT COUNT(*) FROM claims");

// ثم يُبذَر التحويلُ المالي (قيودُ الإيراد) — بعد اللقطة لا قبلها
foreach (array(array($tsIds[0], '2027-08-02', 8.0), array($tsIds[1], '2027-08-03', 6.5),
              array($tsIds[2], '2027-08-04', 10.0)) as $s) { $seedRevenue($s[0], $s[1], $s[2]); }
$seedRevenue($tsOut, '2027-09-01', 7.0);   // محوَّلٌ لكنه خارج الفترة — الفلترُ يستبعده
// و`$tsUnapproved` يبقى **بلا تحويل**: حارسُ ⑦ يقيس رفضَه

// ═══ ① التوليد ════════════════════════════════════════════════════════════
head('① التوليد من قيود الإيراد المعترَف بها');

$g = claim_gate(false);
$res = claim_generate($conn, $CONTRACT, '2027-08-01', '2027-08-31', $ACTOR);
check($res['status'] === 'created', 'وُلّد المستخلص (' . $res['status'] . ')');
if ($res['claim_id']) { $claimIds[] = $res['claim_id']; }
check($res['lines'] === 3, "ثلاثةُ بنودٍ للأيام الثلاثة المعتمدة (وُجد {$res['lines']})");
check(abs($res['gross'] - 1225.00) < 0.01, "الإجمالي 24.5 ساعة × 50 = 1,225 (وُجد {$res['gross']})");

$claim = $conn->query("SELECT * FROM claims WHERE id=" . intval($res['claim_id']))->fetch_assoc();
check($claim && preg_match('~^CLM-\d{4}-\d{4}$~', (string)$claim['claim_no']) === 1,
    'ورقمُه تسلسليٌّ بصيغته (' . ($claim['claim_no'] ?? '-') . ')');
check($claim && (string)$claim['state'] === 'draft', 'وحالتُه «مسودة»');

// ═══ ② العميلُ مشتق ═══════════════════════════════════════════════════════
head('② العميلُ مشتقٌّ بالسلسلة لا مُدخل');

check($claim && intval($claim['client_id']) === $CLIENT,
    '★ العميلُ استُنبط: دوام → تشغيل → مشروع → عميل');
check($claim && intval($claim['project_id']) === $PROJ, 'والمشروعُ معه');
// العقدُ مبذورٌ بـ«جنيه» (لغةُ طبقة العقود)، والمستخلصُ ماليٌّ فيحمل الرمزَ
// المعياري — الترجمةُ عند الحدّ كما تفعل المروحة. والقيمةُ من العقد لا افتراضًا:
// عقدٌ بـ«دولار» كان يُفوتَر بـSDG صامتًا قبل التطبيع.
check($claim && (string)$claim['currency'] === 'SDG',
    'وعملةُ العقد مطبَّعةً رمزًا («جنيه» → SDG) لا خامًا ولا افتراضية');

$lines = array();
$r = $conn->query("SELECT * FROM claim_lines WHERE claim_id=" . intval($res['claim_id']) . " ORDER BY work_date");
while ($x = $r->fetch_assoc()) { $lines[] = $x; }
check(count($lines) === 3, 'ثلاثةُ سطورٍ محفوظة');
if ($lines) {
    check(abs((float)$lines[0]['unit_price'] - 50.00) < 0.01, 'السعرُ مشتقٌّ من القيد (400 ÷ 8 = 50)');
    check((string)$lines[0]['unit_type'] === 'hour', 'ووحدتُه وحدةُ القيد نفسُها');
    check(abs((float)$lines[0]['qty'] - 8.0) < 0.01 && abs((float)$lines[0]['amount'] - 400.00) < 0.01,
        'والكميةُ والقيمةُ **كما وقعتا في الدفتر** لا محسوبتين هنا (8 × 50 = 400)');
    check(intval($lines[0]['source_ref']) === $tsIds[0], 'ورابطُ الأصل يشير إلى صفّ الدوام');
    check(!empty($lines[0]['event_id']) && in_array(intval($lines[0]['event_id']), $revIds, true),
        '★ والبندُ مرجعُه قيدُ الإيراد المعترَف به (event_id) — فلا إيرادَ يُنشأ ثانيًا');
}

$srcRefs = array_map(function ($l) { return intval($l['source_ref']); }, $lines);
check(!in_array($tsOut, $srcRefs, true), 'دوامٌ محوَّلٌ خارج الفترة لم يدخل');
check(!in_array($tsUnapproved, $srcRefs, true), 'ودوامٌ غيرُ معتمدٍ (ولا محوَّل) لم يدخل');

// ═══ ③ لا وحدةَ مرتين ═════════════════════════════════════════════════════
head('③ لا وحدةَ تُستخلص مرتين');

$again = claim_billable_units($g, $CONTRACT, '2027-08-01', '2027-08-31');
check(count($again) === 0, '★ الوقائعُ المستخلَصةُ لم تعد قابلةً للاستخلاص');

// M-08 (ENT-03 §7): كانت تعيد «لا وحدات» صامتةً — صارت 409 بمرجع سطر كلِّ
// وحدةٍ فُوترت سابقًا (صفرُ ازدواجٍ في الإيراد، والسببُ مسمًّى لا مُخمَّن)
$res2 = claim_generate($conn, $CONTRACT, '2027-08-01', '2027-08-15', $ACTOR);
check($res2['status'] === 'conflict' && intval($res2['code'] ?? 0) === 409,
    'وفترةٌ فرعيةٌ داخلها تُرفض 409 (فُوترت سابقًا) لا بنودًا مكررة: ' . $res2['status']);
check(!empty($res2['billed_conflicts']) && strpos((string) $res2['reason'], 'سطر #') !== false,
    'وبمرجع سطر كلِّ وحدة: ' . mb_substr((string) $res2['reason'], 0, 90));

/* ══ **الفوترةُ المزدوجةُ تقاطعٌ في الفترة لا تشارُكٌ في المرجع.** كان الشرطُ
     «صفرُ مرجعٍ مشتركٍ بين مستخلصَين **مهما تباعدت فترتاهما**»، فعدَّ 1334 سطرًا
     — وكلُّها كتلةٌ تجريبيةٌ مؤرَّخةٌ 2020: مستخلصٌ لكلِّ شهرٍ، والباذرُ وضع
     `source_ref` من **حوضٍ من مئةِ واقعةٍ** لا من العملِ المفوتَر فعلًا (302 سطرًا
     بمئةِ مرجعٍ متمايز). ومستخلصُ مارس ومستخلصُ أبريل **لا يمكن أن يفوترا يومًا
     واحدًا** وإن تشابه مرجعُهما الملفَّق.
   ⇒ فيُقاس الحكمُ الماليُّ الحقيقيّ: مرجعٌ واحدٌ في مستخلصَين **فترتاهما
     متقاطعتان** — وهو **صفرٌ** في القاعدةِ كلِّها. والحارسُ نفسُه مُثبَتٌ وظيفيًّا
     أعلاه (409 بمرجعِ سطرِ كلِّ وحدة)، فالمقياسُ يسند البرهانَ ولا يعيد اختراعَه. */
$overlap = (int) $scalar("SELECT COUNT(*) FROM claim_lines cl1
        JOIN claim_lines cl2 ON cl1.source_kind = cl2.source_kind
             AND cl1.source_ref = cl2.source_ref AND cl1.id <> cl2.id
        JOIN claims c1 ON c1.id = cl1.claim_id
        JOIN claims c2 ON c2.id = cl2.claim_id
      WHERE cl1.claim_id <> cl2.claim_id
        AND COALESCE(c1.is_deleted,0) = 0 AND COALESCE(c2.is_deleted,0) = 0
        AND c1.period_from <= c2.period_to AND c2.period_from <= c1.period_to");
check($overlap === 0,
    'وصفرُ واقعةٍ مفوترةٍ مرتين لفترتين متقاطعتين في القاعدة كلِّها' . ($overlap ? " ({$overlap})" : ''));

// ═══ ④ مستخلصٌ واحدٌ للفترة ════════════════════════════════════════════════
head('④ مستخلصٌ واحدٌ لكل (عقد × فترة)');

$dup = claim_generate($conn, $CONTRACT, '2027-08-01', '2027-08-31', $ACTOR);
check($dup['status'] === 'exists', 'إعادةُ التوليد للفترة نفسها → exists');
check(intval($dup['claim_id']) === intval($res['claim_id']), 'وتعيد مرجعَ المستخلص القائم');
check(strpos($dup['reason'], 'CLM-') !== false, 'ورقمُه في الرسالة');

$bad = claim_generate($conn, $CONTRACT, '2027-08-31', '2027-08-01', $ACTOR);
check($bad['status'] === 'failed' && strpos($bad['reason'], 'نهاية') !== false, 'وفترةٌ مقلوبةٌ تُرفض');

// ═══ ⑤ الاعتماد ═══════════════════════════════════════════════════════════
head('⑤ الرفعُ ثم الإجازة → فاتورةٌ + ذمّة (بلا إيرادٍ ثانٍ)');

// لا تُجاز مسودةٌ لم تُرفع — يدُ المبيعات أولًا
$early = claim_approve($conn, $res['claim_id'], 'VAT15', 99);
check($early['status'] === 'blocked' && strpos($early['reason'], 'رفعته') !== false,
    '★ لا إجازةَ لمسودةٍ لم تُرفع: ' . $early['reason']);

$sub = claim_submit($res['claim_id'], $ACTOR);
check($sub['status'] === 'submitted', 'رفعتها المبيعاتُ للمالية (' . $sub['status'] . ')');
$claimR = $conn->query("SELECT state, submitted_by FROM claims WHERE id=" . intval($res['claim_id']))->fetch_assoc();
check($claimR && (string)$claimR['state'] === 'review' && intval($claimR['submitted_by']) === $ACTOR,
    'وحالتُه «قيد المراجعة» باسم رافعه');

$evBeforeAp = (int) $scalar("SELECT COUNT(*) FROM fin_financial_events");
$ap = claim_approve($conn, $res['claim_id'], 'VAT15', 99);   // يدٌ ثانيةٌ تُجيز
check($ap['status'] === 'approved', 'أجازتها المالية (' . $ap['status'] . ')');
check(!empty($ap['invoice_no']) && strpos($ap['invoice_no'], 'INV-') === 0,
    'وتولّدت فاتورةٌ ضريبيةٌ برقمها (' . $ap['invoice_no'] . ')');

$claim = $conn->query("SELECT * FROM claims WHERE id=" . intval($res['claim_id']))->fetch_assoc();
check((string)$claim['state'] === 'invoiced', 'وحالةُ المستخلص «مفوتر»');
check(abs((float)$claim['tax_amount'] - 183.75) < 0.01,
    'والضريبةُ 15٪ من 1,225 = 183.75 (وُجد ' . $claim['tax_amount'] . ')');

// ★★ صفرُ قيدٍ جديدٍ في الدفتر: الاعترافُ وقع في المروحة، وهذه فوترةٌ لا قيد.
$ev = $conn->query("SELECT * FROM fin_financial_events WHERE entity_type='claim' AND entity_id=" . intval($res['claim_id']))->fetch_assoc();
check($ev === null,
    '★★ لا قيدَ إيرادٍ ثانيًا للمستخلص — منشأُ ازدواج 56,000/28,000 مسدودٌ بنيويًّا');
check((int) $scalar("SELECT COUNT(*) FROM fin_financial_events") === $evBeforeAp,
    'والدفترُ لم يزدد صفًّا واحدًا بالإجازة');
$fact = $conn->query("SELECT * FROM ems_business_events WHERE entity_type='claim' AND entity_id=" . intval($res['claim_id']))->fetch_assoc();
check($fact !== null && (string)$fact['event_key'] === 'billing.claim.invoiced',
    'وحقيقةُ الفوترة مدوَّنةٌ في الجذر وحده (' . ($fact ? $fact['event_key'] : '—') . ')');
if ($fact) {
    $pl = json_decode((string) $fact['payload'], true);   // عمودُ الجذر اسمُه `payload`
    check(is_array($pl) && !empty($pl['billed_revenue_events']),
        'ولقطتُها تسمّي قيودَ الإيراد التي فوترتها (شفافيةُ المراجع)');
}

$recv = $conn->query("SELECT * FROM fin_receivables WHERE id=" . intval($ap['receivable_id']))->fetch_assoc();
check($recv !== null, '★ وفُتحت ذمّةٌ مدينة');
if ($recv) {
    check(intval($recv['customer_entity_id']) === $CLIENT, 'باسم العميل');
    check(abs((float)$recv['amount'] - 1408.75) < 0.01, 'بقيمة الصافي + الضريبة (1,225 + 183.75)');
    check((string)$recv['state'] === 'open' && abs((float)$recv['outstanding'] - 1408.75) < 0.01,
        'ومفتوحةٌ بكامل مبلغها بانتظار التحصيل');
    check((string)$recv['doc_type'] === 'invoice' && (string)$recv['doc_ref'] === $ap['invoice_no'],
        'ومرجعُها الفاتورةُ الضريبية');
}

// ═══ ⑥ العطالة ════════════════════════════════════════════════════════════
head('⑥ إجازتان لا تفتحان ذمّتين');

$evBefore = (int) $scalar("SELECT COUNT(*) FROM fin_financial_events");
$rcBefore = (int) $scalar("SELECT COUNT(*) FROM fin_receivables");
$ap2 = claim_approve($conn, $res['claim_id'], 'VAT15', 99);
check($ap2['status'] === 'exists', 'الإجازةُ الثانية تعيد exists');
check((int) $scalar("SELECT COUNT(*) FROM fin_financial_events") === $evBefore, 'صفر حدثٍ جديد');
check((int) $scalar("SELECT COUNT(*) FROM fin_receivables") === $rcBefore, 'وصفر ذمّةٍ جديدة');

// ═══ ⑦ حارسُ التحويل ═══════════════════════════════════════════════════════
head('⑦ يومٌ لم تحوّله المالية لا يُستخلص');

// يومٌ مقبولٌ بكل شيءٍ **إلا أن المالية لم تحوّله** — لا قيدَ إيرادٍ فلا بند.
// (وهنا تُرَث قاعدةُ عدم التلفيق كلُّها: وحدةٌ لا يسجّلها الدوام لا يولّد لها
//  المحرّكُ قيدًا أصلًا، فلا يحتاج المستخلصُ حكمًا ثانيًا يكرّره ويخالفه.)
$conn->query("INSERT INTO timesheet (company_id, operator, `date`, executed_hours, tons_count, meters_count, status)
              VALUES ({$CO}, {$OP}, '2027-10-05', 9.0, 0, 0, 1)");
$tsNoConvert = $conn->insert_id;
// اعتمادٌ رباعيٌّ كاملٌ — فالباقي الوحيد هو ختمُ المالية
$conn->query("INSERT INTO timesheet_approvals (company_id, timesheet_id, approval_level, approved_by, approved_by_name, status)
              VALUES ({$CO}, {$tsNoConvert}, 4, 1, 'حارس المستخلص', 1)");
$resM = claim_generate($conn, $CONTRACT, '2027-10-01', '2027-10-31', $ACTOR);
check($resM['status'] === 'empty', '★ يومٌ مكتملُ الاعتماد بلا تحويلٍ ماليٍّ → لا مستخلص ولا ذمّة');
check(strpos($resM['reason'], 'بانتظار تحويل المالية') !== false,
    'والتشخيصُ يقول أين وقف بالضبط: ' . mb_substr($resM['reason'], 0, 90));
$conn->query("DELETE FROM timesheet_approvals WHERE timesheet_id=" . intval($tsNoConvert));
$conn->query("DELETE FROM timesheet WHERE id=" . intval($tsNoConvert));

// ═══ ⑧ يدان لا يدٌ واحدة ═══════════════════════════════════════════════════
head('⑧ من رفع لا يُجيز');

$res8 = claim_generate($conn, $CONTRACT, '2027-09-01', '2027-09-30', $ACTOR);
check($res8['status'] === 'created', 'مستخلصٌ ثانٍ لشهرٍ آخر (' . $res8['status'] . ')');
if ($res8['claim_id']) {
    $claimIds[] = $res8['claim_id'];
    claim_submit($res8['claim_id'], 77);                       // رفعه المستخدم 77
    $self = claim_approve($conn, $res8['claim_id'], null, 77); // ويحاول إجازتَه
    check($self['status'] === 'blocked' && strpos($self['reason'], 'من رفعه') !== false,
        '★ الإجازةُ يدٌ ثانية: ' . $self['reason']);
    $other = claim_approve($conn, $res8['claim_id'], null, 88);
    check($other['status'] === 'approved', 'ويدٌ ثانيةٌ تُجيزه (' . $other['status'] . ')');
}

// ── النظافة ───────────────────────────────────────────────────────────────
$cleanup();
check((int) $scalar("SELECT COUNT(*) FROM fin_receivables") === $recv0, 'بعد الكنس: الذممُ كما كانت');
check((int) $scalar("SELECT COUNT(*) FROM fin_financial_events") === $ev0, 'والدفترُ كما كان');
check((int) $scalar("SELECT COUNT(*) FROM claims") === $claims0, 'ولا مستخلصَ زائدًا على خط الأساس (' . $claims0 . ')');

fwrite(STDOUT, "\n" . str_repeat('═', 46) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
