<?php
/**
 * tools/injint01/journey_matrix.php — مصفوفةُ عقودِ رحلةِ RJ-01 (EXE-01 §6)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأمرُ لا يقبل «انقطاعان فقط» قبل المصفوفة** — وهو مُحِقّ. فلكلِّ انتقالٍ
 *   صفٌّ بأحدَ عشرَ عمودًا، والأعمدةُ الأربعةُ (مبنيّ · موصول · مُمارَس · مُثبَت)
 *   **مستقلّةٌ لا تُدمج**.
 *
 * ⛔ **وصفرُ الصفوفِ وحدَه لا يثبت غيابَ العقد** (‏§6 حرفًا): «تسليمٌ مفقود»
 *   يتطلّب **صفوفًا مؤهَّلةً في الطرفَين** وقاعدةَ عملٍ تتوقّع أكثرَ من صفرٍ
 *   ثمَّ فعليًّا صفرًا. وما دون ذلك **«مبنيٌّ لم يُمارَس»** — والأوّلُ يُبنى
 *   والثاني يُمارَس، والخلطُ بينهما يُنتِج بناءً لا حاجةَ إليه.
 *
 * ⛔ **ولا يُقاس المُمارَسُ بوجودِ الجدول**: جدولٌ قائمٌ بصفرِ صفوفٍ **مبنيٌّ**
 *   لا **مُمارَس**. والتمييزُ هو كلُّ الفرقِ بين «ابنِ جسرًا» و«مرِّر معاملة».
 *
 * التشغيل: php tools/injint01/journey_matrix.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8'); mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
$c = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $p);
if ($c->connect_errno) { exit('تعذّر الاتصال: ' . $c->connect_error . "\n"); }
$c->set_charset('utf8mb4');
$one = function ($q) use ($c) { $r = $c->query($q); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; };
$tex = function ($t) use ($c) { $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'"); return $r && $r->num_rows > 0; };
$cnt = function ($t) use ($c, $tex) { return $tex($t) ? (int) (($x = $c->query("SELECT COUNT(*) FROM `$t`")->fetch_row()) ? $x[0] : 0) : null; };

/* ═══ الانتقالاتُ الأحدَ عشرَ — من نصِّ INT-01 لا من تخمين ═══════════════ */
$T = array(
 array('rule'=>'كلُّ عقدٍ موقَّعٍ يُنتِج احتياجَ تغطية','exp'=>'>0','id'=>'RJ01-INT-01','src'=>'DEP-01','tgt'=>'DEP-02','name'=>'العقد ← احتياجُ التغطية',
       'kind'=>'حدث','src_t'=>'contract_commitments','tgt_t'=>'wf_coverage','ev'=>'contract.signed'),
 array('rule'=>'كلُّ احتياجِ تغطيةٍ بلا مورّدٍ متعاقدٍ يفتح طلبَ عروض','exp'=>'>0','id'=>'RJ01-INT-02','src'=>'DEP-02','tgt'=>'DEP-02','name'=>'التغطية ← مسارُ المورّد',
       'kind'=>'أمر','src_t'=>'wf_coverage','tgt_t'=>'supplier_rfqs','ev'=>'supplier.rfq.opened'),
 array('rule'=>'لا قاعدةَ عملٍ معلَنةٍ لعددِ الحصصِ الموزَّعة','exp'=>'—','id'=>'RJ01-INT-03','src'=>'DEP-02','tgt'=>'DEP-04','name'=>'وحدةُ المورّد ← الأسطول',
       'kind'=>'حدث','src_t'=>'sup_quota_supplier_unit','tgt_t'=>'sup_allocation_unit_equipment','ev'=>'—'),
 array('rule'=>'كلُّ معدّةٍ مُسنَدةٍ تفتح احتياجَ مشغّل','exp'=>'>0','id'=>'RJ01-INT-04','src'=>'DEP-04','tgt'=>'DEP-13','name'=>'المعدّة ← احتياجُ القوى',
       'kind'=>'تسليمٌ بشريّ','src_t'=>'contractequipments','tgt_t'=>'equipment_operators','ev'=>'—'),
 array('rule'=>'كلُّ مشغّلٍ ومعدّةٍ يُربَطانِ بموقعِ العقد','exp'=>'>0','id'=>'RJ01-INT-05','src'=>'DEP-04','tgt'=>'DEP-12','name'=>'المعدّة والمشغّل ← الموقع',
       'kind'=>'إسقاطٌ مرجعيّ','src_t'=>'equipment_operators','tgt_t'=>'contract_operational_sites','ev'=>'—'),
 array('rule'=>'كلُّ قيدِ وحدةٍ يمرُّ باعتماد','exp'=>'>0','id'=>'RJ01-INT-06','src'=>'DEP-12','tgt'=>'DEP-11','name'=>'الموقع ← الأداءُ المعتمَد',
       'kind'=>'حدث','src_t'=>'unit_entries','tgt_t'=>'unit_approvals','ev'=>'operations.unit.approved'),
 array('rule'=>'كلُّ وحدةٍ معتمَدةٍ تدخل مطالبةً','exp'=>'>0','id'=>'RJ01-INT-07','src'=>'DEP-11','tgt'=>'DEP-05','name'=>'الأداءُ المعتمَد ← المطالبة',
       'kind'=>'حدث','src_t'=>'unit_approvals','tgt_t'=>'claims','ev'=>'revenue.unit.recognized'),
 array('rule'=>'كلُّ مطالبةٍ معتمَدةٍ تُنتِج فاتورة','exp'=>'>0','id'=>'RJ01-INT-08','src'=>'DEP-05','tgt'=>'DEP-05','name'=>'المطالبة ← الفاتورة',
       'kind'=>'أثرٌ ماليّ','src_t'=>'claims','tgt_t'=>'ar_claim_invoices','ev'=>'—'),
 array('rule'=>'كلُّ فاتورةٍ تُنتِج ذمّة','exp'=>'>0','id'=>'RJ01-INT-09','src'=>'DEP-05','tgt'=>'DEP-05','name'=>'الفاتورة ← الذمّة',
       'kind'=>'أثرٌ ماليّ','src_t'=>'tax_invoices','tgt_t'=>'fin_receivables','ev'=>'—'),
 array('rule'=>'كلُّ سندِ قبضٍ يُخصَّص على ذمّة','exp'=>'>0','id'=>'RJ01-INT-10','src'=>'DEP-06','tgt'=>'DEP-05','name'=>'القبض ← التخصيص ← الذمّة',
       'kind'=>'أثرٌ ماليّ','src_t'=>'fin_payments','tgt_t'=>'fin_collection_allocations','ev'=>'—'),
 array('rule'=>'كلُّ تخصيصٍ يبلغ الدفترَ المحاسبيّ','exp'=>'>0','id'=>'RJ01-INT-11','src'=>'DEP-05','tgt'=>'—','name'=>'إقفالُ النقد ← الإسقاطات',
       'kind'=>'إسقاطٌ مرجعيّ','src_t'=>'fin_collection_allocations','tgt_t'=>'fin_journal_entries','ev'=>'—'),
);

$out = array();
foreach ($T as $t) {
    $srcN = $cnt($t['src_t']); $tgtN = $cnt($t['tgt_t']);
    $evN  = $t['ev'] === '—' ? null : (int) $one("SELECT COUNT(*) FROM ems_business_events WHERE event_key='" . $c->real_escape_string($t['ev']) . "'");
    /* الأعمدةُ الأربعةُ مستقلّة */
    $built    = ($srcN !== null && $tgtN !== null);                 /* الجدولانِ قائمان */
    $wired    = $t['ev'] === '—' ? null : ($evN !== null);          /* ثمّةَ مفتاحُ حدثٍ معلَن */
    $exercised = ($tgtN !== null && $tgtN > 0);                     /* صفوفٌ فعليّةٌ في الهدف */
    $verified = ($exercised && ($evN === null || $evN > 0));         /* ومعها شاهدُ حدثٍ إن وُجد */
    /* الحكم — والتمييزُ الحاسم */
    if (!$built) { $v = 'غيرُ منطبق'; }
    elseif ($exercised && $verified) { $v = 'قائمٌ ومُثبَت'; }
    elseif ($exercised) { $v = 'قائمٌ غيرُ مُثبَت'; }
    /* ⛔ **و«تسليمٌ مفقود» يجتمع له ثلاثة** (‏§6 حرفًا): صفوفٌ مؤهَّلةٌ في الطرفَين ·
       **وقاعدةُ عملٍ تتوقّع أكثرَ من صفر** · ثمَّ فعليًّا صفر. وما دون ذلك
       «مبنيٌّ لم يُمارَس». ⇐ فالانتقالُ بلا قاعدةٍ معلَنةٍ (`exp='—'`) **لا يُحكَم
       عليه بالفقدِ أبدًا** — والحكمُ على مجهولِ التوقُّعِ حكمٌ على غيرِ محلِّه. */
    elseif ($srcN > 0 && $tgtN === 0 && $t['exp'] === '>0') { $v = 'مبنيٌّ لم يُمارَس'; }
    else { $v = 'جزئيّ'; }
    $out[] = array_merge($t, array('srcN' => $srcN, 'tgtN' => $tgtN, 'evN' => $evN,
        'built' => $built, 'wired' => $wired, 'exercised' => $exercised, 'verified' => $verified, 'verdict' => $v));
}

/* ═══ العرضُ بأحدَ عشرَ عمودًا — والأربعةُ الوسطى لا تُدمج ═══════════════════ */
echo "══ مصفوفةُ عقودِ الرحلة — أحدَ عشرَ عمودًا ══\n";
foreach ($out as $r) {
    printf("\n① %s — %s\n", $r['id'], $r['name']);
    printf("  ② المصدر·الهدف : %s · %s   (`%s` ⇒ `%s`)\n", $r['src'], $r['tgt'], $r['src_t'], $r['tgt_t']);
    printf("  ③ نوعُ التفاعل  : %s\n", $r['kind']);
    printf("  ④⑤⑥⑦ مبنيّ=%s موصول=%s مُمارَس=%s مُثبَت=%s\n",
        $r['built'] ? '✔' : '✘',
        $r['wired'] === null ? '—' : ($r['wired'] ? '✔' : '✘'),
        $r['exercised'] ? '✔' : '✘', $r['verified'] ? '✔' : '✘');
    printf("  ⑧ المتوقَّع     : %-4s ← %s\n", $r['exp'], $r['rule']);
    printf("  ⑨ الفعليّ      : مصدر=%s · هدف=%s · حدث=%s\n",
        $r['srcN'] === null ? '⛔' : $r['srcN'], $r['tgtN'] === null ? '⛔' : $r['tgtN'],
        $r['evN'] === null ? '—' : $r['evN']);
    printf("  ⑩ الدليل       : `%s` · %s\n", $r['tgt_t'],
        $r['ev'] === '—' ? 'بلا مفتاحِ حدثٍ معلَن' : "ems_business_events.event_key='{$r['ev']}'");
    printf("  ⑪ الحكم        : %s\n", $r['verdict']);
}
echo "\n";

$byV = array_count_values(array_map(function ($x) { return $x['verdict']; }, $out));
echo "\n══ الحصيلة ══\n";
foreach ($byV as $k => $v) { printf("  %-20s %d\n", $k, $v); }

/* المستهلكون الذين تستوجبهم رحلةُ الإيرادِ وحدَها */
$rjKeys = array();
foreach ($T as $t) { if ($t['ev'] !== '—') { $rjKeys[] = $t['ev']; } }
$in = "'" . implode("','", array_map(array($c, 'real_escape_string'), $rjKeys)) . "'";
printf("\n  مفاتيحُ أحداثٍ تخصُّ هذه الرحلة: %d\n", count($rjKeys));
printf("  مستهلكون مسجَّلون عليها        : %s\n", $one("SELECT COUNT(*) FROM event_consumers WHERE event_name IN ($in)"));
printf("  منها نشِطة                     : %s\n", $one("SELECT COUNT(*) FROM event_consumers WHERE event_name IN ($in) AND active=1"));
echo "  ⇐ **ولا تُجبَر الرحلةُ على أحداثِ الصيانةِ والنقلِ والرواتبِ لتحسينِ مقياس.**\n";

file_put_contents($ROOT . '/docs/injint01/journey_matrix.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\n  التفصيل: docs/injint01/journey_matrix.json\n";
