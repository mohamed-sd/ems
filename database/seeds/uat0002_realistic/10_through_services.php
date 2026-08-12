<?php
/**
 * UAT-0002 · بذرةٌ شبهُ حقيقيةٍ **تمرُّ بالخدماتِ لا بكتابةٍ خام**
 * ═══════════════════════════════════════════════════════════════════════════
 * **لماذا هذه البذرةُ موجودةٌ أصلًا**: المِلءُ العامُّ القديم
 * (`uat0001/10_autofill.php`) كتب **خامًا** في دفاترَ يُحرِّم المانفستُ بذرَها،
 * فتجاوز حرّاسَها ووضع نصَّ ملاحظةٍ في أعمدةٍ مُصنَّفةٍ (جملةٌ في «وحدةِ القياس»
 * ومفتاحِ الفترة) وأنشأ إجازاتٍ من صاحبِ الطلبِ نفسِه. أُزيلت تلك الصفوفُ
 * (958 صفًّا) وأُغلق الجذرُ، **وهذه هي الداتا البديلةُ** بقرارِ المالك:
 * «حذفُ الملفَّق وإضافةُ داتا غيرِه غيرِ ملفَّقة … شبهِ حقيقيةٍ وكثيرةٍ لتجربةِ
 * النظام».
 *
 * **والفرقُ الجوهريُّ**: كلُّ صفٍّ هنا يُولد من **مدخلِ خدمتِه** — فيمرُّ بكلِّ
 * حارسٍ، ويُنشئ حقائقَه وقيودَه وسجلَّه كما لو أدخله مستخدمٌ من الشاشة. فما
 * ترفضه الخدمةُ **لا يُكتب**، ويُعلَن سببُ رفضِه في التقرير — لا يُلفَّق له صفٌّ.
 *
 * ◆ عطالةٌ: كلُّ صفٍّ بمرجعٍ يحمل الوسمَ `RS2` فإعادةُ التشغيلِ لا تُكرِّر.
 * ◆ ولا يُمَسُّ صفٌّ قائمٌ: البذرةُ تُضيف ولا تُعدّل ولا تحذف.
 *
 * التشغيل:  php database/seeds/uat0002_realistic/10_through_services.php [--apply]
 *           بلا `--apply` جولةٌ جافّةٌ تُبيّن ما سيُنشأ وبأيِّ خدمة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$APPLY = in_array('--apply', $argv, true);
$ROOT = dirname(__DIR__, 3);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$CO = 4;
$ACTOR = 1;
$_SESSION['user'] = array('id' => $ACTOR, 'role' => '1', 'company_id' => $CO, 'name' => 'uat0002 seed');
$conn = $GLOBALS['conn'];
$gate = ems_tenant_db();
$MARK = 'RS2';

require_once $ROOT . '/Contracts/advance_helpers.php';
require_once $ROOT . '/Contracts/note_helpers.php';
require_once $ROOT . '/app/Services/Contract/ContractGuaranteeService.php';

$o = function ($s) { fwrite(STDOUT, $s . "\n"); };
$report = array();   // دفتر ⇒ array(created, skipped, reasons)

/** يسجّل نتيجةَ نداءِ خدمةٍ: نجاحًا أو سببَ رفضٍ — ولا يلفّق بديلًا */
$note = function ($ledger, $ok, $reason) use (&$report) {
    if (!isset($report[$ledger])) { $report[$ledger] = array('c' => 0, 's' => 0, 'e' => 0, 'r' => array()); }
    if ($ok === 'exists') { $report[$ledger]['e']++; }
    elseif ($ok) { $report[$ledger]['c']++; }
    else {
        $report[$ledger]['s']++;
        $reason = trim((string) $reason);
        if ($reason !== '' && count($report[$ledger]['r']) < 3) { $report[$ledger]['r'][] = $reason; }
    }
};

$o('══ بذرةٌ شبهُ حقيقيةٍ عبر الخدمات — ' . ($APPLY ? 'تنفيذ' : 'جولةٌ جافّة'));

/* الدفاترُ الثلاثةُ وعمودُ مرجعِها الحقيقيُّ — أُثبِتت أسماؤها بـ`SHOW COLUMNS`
   لا بالحدس (`instrument_ref` لا `doc_ref` في الضمانات، و`doc_ref` في الإشعارات). */
$MARKED = array(
    'contract_advances'   => 'doc_ref',
    'contract_guarantees' => 'instrument_ref',
    'credit_debit_notes'  => 'doc_ref',
);
$BEFORE = array();
foreach ($MARKED as $tbl => $col) {
    $q = $conn->query("SELECT COUNT(*) n FROM `{$tbl}` WHERE `{$col}` LIKE '{$MARK}-%'");
    $BEFORE[$tbl] = ($q && ($x = $q->fetch_assoc())) ? (int) $x['n'] : 0;
}

/* ── عقودٌ حقيقيةٌ نعمل عليها (لا تُخترع عقودٌ) ────────────────────────────── */
$contracts = array();
$r = $conn->query("SELECT c.id, c.price_currency_contract cur
                     FROM contracts c
                    WHERE c.company_id = {$CO} AND c.price_currency_contract IS NOT NULL
                      AND c.price_currency_contract <> ''
                    ORDER BY c.id LIMIT 12");
while ($r && ($x = $r->fetch_assoc())) { $contracts[] = $x; }
$o('  عقودٌ حقيقيةٌ للعمل عليها: ' . count($contracts));
if (!$contracts) { $o('  ✘ لا عقدَ بعملةٍ معروفة — أُوقفت البذرة'); exit(1); }

/* ══ ① مقدَّماتُ عقودٍ — عبر `advance_record()` ═══════════════════════════════
     الخدمةُ تفتح المقدَّمَ وتنشر حقيقتَه وتربط سقفَ الاستقطاع. */
$amounts = array(15000.00, 22500.00, 8750.00, 31000.00, 12250.00, 45000.00);
$i = 0;
foreach ($contracts as $c) {
    if ($i >= 6) { break; }
    $amt = $amounts[$i % count($amounts)];
    $day = sprintf('2026-%02d-%02d', 3 + ($i % 6), 5 + ($i * 3) % 20);
    $ref = $MARK . '-ADV-' . $c['id'];
    if (!$APPLY) { $note('contract_advances', true, ''); $i++; continue; }
    $res = advance_record($conn, $gate, (int) $c['id'], $amt, $day, $ref,
        'مقدَّمُ تعاقدٍ مستلَمٌ بحوالةٍ بنكية', $ACTOR);
    $note('contract_advances', !empty($res['ok']), isset($res['reason']) ? $res['reason'] : '');
    $i++;
}

/* ══ ② أدواتُ ضمانٍ — عبر `ContractGuaranteeService::add()` ═══════════════════
     النوعُ يحسم الطبيعةَ حتمًا، والخدمةُ ترفض خلطَ الأصلِ بالالتزامِ المحتمل. */
$kinds = array(
    array('bank_guarantee', 'بنك النيل — خطابُ ضمانِ حسنِ تنفيذ', 50000.00, '2027-12-31'),
    array('cash_retention', 'محتجزٌ نقديٌّ من المستخلصات',        0.00,     null),
    array('insurance',      'شركةُ شيكان — تأمينُ مسؤوليةٍ مدنية', 25000.00, '2027-06-30'),
    array('surety',         'كفالةٌ تضامنيةٌ من الشركةِ الأمّ',     40000.00, '2028-03-31'),
    array('bank_guarantee', 'بنكُ الخرطوم — ضمانُ دفعةٍ مقدَّمة',  30000.00, '2027-09-30'),
);
$i = 0;
foreach ($contracts as $c) {
    if ($i >= count($kinds)) { break; }
    list($kind, $issuer, $amt, $exp) = $kinds[$i];
    $a = array('kind' => $kind, 'amount' => $amt, 'issuer' => $issuer,
               'instrument_ref' => $MARK . '-GRT-' . $c['id'],
               'expiry_date' => $exp, 'currency' => $c['cur']);
    /* والمحتجَزُ النقديُّ **أصلٌ بذمةٍ مؤجَّلة** — فالخدمةُ تُلزمه موعدَ ردٍّ أو شرطَه
       (رفضت أوّلَ محاولةٍ بلا موعد، وهو حارسٌ يعمل لا عقبة). */
    if ($kind === 'cash_retention') {
        $a['percent_value'] = 5.00;
        $a['due_release_date'] = '2028-06-30';
        $a['release_condition'] = 'يُردُّ بعد شهادةِ الإنجازِ النهائيِّ وانتهاءِ فترةِ الضمان';
    }
    if (!$APPLY) { $note('contract_guarantees', true, ''); $i++; continue; }
    /* ⚠️ **العطالةُ هنا مسؤوليةُ البذرةِ لا الخدمة.** `ContractGuaranteeService::add`
       بلا مفتاحِ عطالةٍ (بخلاف `cdnote_create` الذي يقبل `idem_key`، و
       `advance_record` الذي يحرس `doc_ref`) — فشوطٌ ثانٍ يُنشئ صفًّا ثانيًا.
       (وقع فعلًا: أربعةُ صفوفٍ مكرَّرةٍ بمرجعٍ واحدٍ قبل هذا الجسّ.) فيُجَسُّ
       المرجعُ قبل النداء — والجسُّ في البذرةِ لا تعديلٌ في الخدمة. */
    $dup = $gate->selectOne('contract_guarantees', array(
        'columns' => array('id'),
        'where' => array('instrument_ref' => $a['instrument_ref'])));
    if ($dup !== null) { $note('contract_guarantees', 'exists', ''); $i++; continue; }
    $res = \App\Services\Contract\ContractGuaranteeService::add($conn, $gate, $CO, (int) $c['id'], $a, $ACTOR);
    $note('contract_guarantees', !empty($res['ok']), isset($res['reason']) ? $res['reason'] : '');
    $i++;
}

/* ══ ③ إشعاراتٌ دائنة/مدينة — عبر `cdnote_create()` ═══════════════════════════
     لا إشعارَ إلا على مستخلصٍ **صدرت فاتورتُه** — والخدمةُ تحرس ذلك. */
$claims = array();
$r = $conn->query("SELECT cl.id, cl.net_amount FROM claims cl
                    WHERE cl.company_id = {$CO} AND cl.state = 'invoiced'
                      AND COALESCE(cl.is_deleted,0) = 0
                      AND cl.net_amount > 200
                    ORDER BY cl.id DESC LIMIT 6");
while ($r && ($x = $r->fetch_assoc())) { $claims[] = $x; }
$o('  مستخلصاتٌ مفوترةٌ للإشعارات: ' . count($claims));
$reasons = array('فرقُ قياسٍ ميدانيٍّ بعد المراجعة', 'خصمُ تأخيرٍ بحسب العقد',
                 'تسويةُ كميةٍ مرفوضةٍ في الاستلام', 'إضافةُ عملٍ مأمورٍ به خارج البند');
$i = 0;
foreach ($claims as $cl) {
    $kind = ($i % 2 === 0) ? 'credit' : 'debit';
    $amt = round(min(150.00, max(25.00, ((float) $cl['net_amount']) * 0.05)), 2);
    $ref = $MARK . '-CDN-' . $cl['id'];
    if (!$APPLY) { $note('credit_debit_notes', true, ''); $i++; continue; }
    $res = cdnote_create($gate, (int) $cl['id'], $kind, $amt,
        $reasons[$i % count($reasons)], $ref, null, $ref, $ACTOR);
    $note('credit_debit_notes', !empty($res['ok']), isset($res['reason']) ? $res['reason'] : '');
    $i++;
}

/* ── التقرير ─────────────────────────────────────────────────────────────── */
$o('');
$o('── الحصيلة:');
$tc = 0; $ts = 0; $te = 0;
foreach ($report as $ledger => $v) {
    $tc += $v['c']; $ts += $v['s']; $te += $v['e'];
    printf("   %-26s نجح %3d · موجودٌ سلفًا %3d · رُفض %3d%s\n", $ledger, $v['c'], $v['e'], $v['s'],
        $v['r'] ? '  ⟵ ' . mb_substr(implode(' · ', $v['r']), 0, 90) : '');
}
$o('   ══ نجح ' . $tc . ' · موجودٌ سلفًا ' . $te . ' · رُفض ' . $ts . ($APPLY ? '' : '  (جولةٌ جافّة)'));

/* ── والفرقُ المقيسُ هو الحكم ────────────────────────────────────────────────
   «نجح» أعلاه = **ما قبلته الخدمةُ**، لا ما دخل الجدول. و`advance_record`
   و`cdnote_create` عاطلتان داخليًّا (تُرجعان `ok` للمرجعِ القائمِ بلا إدراج)،
   فلا يُميّز مُرجَعُهما إنشاءً من وجودٍ سابق. فلا أُخمّن: **أقيس** عددَ صفوفِ
   الوسمِ قبل الشوطِ وبعده، وأُعلن الفرقَ. صفرٌ عند إعادةِ التشغيلِ = عطالةٌ
   مُثبَتةٌ بالقياس لا بالوعد. */
if ($APPLY) {
    $o('');
    $o('── العطالةُ بالقياس (فرقُ صفوفِ الوسمِ ' . $MARK . '):');
    $tot = 0;
    foreach ($MARKED as $tbl => $col) {
        $n = 0;
        $q = $conn->query("SELECT COUNT(*) n FROM `{$tbl}` WHERE `{$col}` LIKE '{$MARK}-%'");
        if ($q && ($x = $q->fetch_assoc())) { $n = (int) $x['n']; }
        $before = isset($BEFORE[$tbl]) ? $BEFORE[$tbl] : 0;
        $d = $n - $before;
        $tot += $d;
        printf("   %-26s قبل %3d ⇒ بعد %3d  ·  فرقٌ %+d\n", $tbl, $before, $n, $d);
    }
    $o('   ══ صفوفٌ دخلت فعلًا: ' . $tot
       . ($tot === 0 ? '  ⇐ عاطلةٌ ✔ (شوطٌ مُعاد)' : ''));
}
if (!$APPLY) { $o(''); $o('◆ للتنفيذ: --apply'); }
exit(0);
