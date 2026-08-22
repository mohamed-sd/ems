<?php
/**
 * tests/injalign01_capability_service_proof.php
 *   شاهدُ القدراتِ الخمسِ — INJ-SAL-ALIGN-01 · INJ-SUP-ALIGN-01 §8
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **يُشغَّل حيًّا لا وصفًا**: يُنشئ سجلاتٍ موسومةً، ويحاول اختراقَ كلِّ قاعدةٍ
 *   بعينِها، ثم يكنس أثرَه **بالعائلةِ لا بالفرد**. وبوابةٌ لم تُجرَّب معطوبةً
 *   لا تُصدَّق — فكلُّ قاعدةٍ هنا يُحاوَل خرقُها ثم يُثبَت الردّ.
 *
 * ◆ **وستُّ قواعدَ تُختبر بالفعل**:
 *   ① شرطُ الإتاحةِ في الخدمةِ لا في الزر — لا احتياجَ على فرصةٍ مغلقة
 *   ② الانتقالُ مرةً واحدة — والرفعُ الثاني يُردّ
 *   ③ الحسابُ في الخدمةِ لا في الشاشة — الإجماليُّ يُحسب ويُقارن
 *   ④ المفرداتُ المغلقةُ — نوعٌ خارجَ القائمةِ يُردّ
 *   ⑤ **فصلُ الواجبات: مَن رصد لا يعتمد ولا يُسقط** — وهي أهمُّها
 *   ⑥ العطالة: تكرارُ الفعلِ لا يُنتج أثرًا ثانيًا
 *
 * ◆ **وبوابةُ المستأجِرِ تُبنى هنا وتُمرَّر** — `ems_tenant_db()` تلزمها جلسةٌ
 *   فتسقط في الفاحصاتِ الطرفية.
 *
 * التشغيل: php tests/injalign01_capability_service_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/app/Services/Align/CapabilityService.php';
use App\Services\Align\CapabilityService as CAP;

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

require_once $ROOT . '/app/Core/TenantGateException.php';
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantDb.php';
$CO = 4;
$gate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($CO, 0, '', true));

/* يدانِ متمايزتان — وفصلُ الواجباتِ يُحرَس بالفاعلِ لا بالدور */
$A = 900011; $B = 900012;
/* ◆ وسمُ العائلةِ **ثابتٌ** لا `getmypid()` — «وسمٌ متغيّرٌ يُعمي الجولةَ عن سابقتها» */
$MARK = 'CAPPROOF';

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

/** كنسٌ بالعائلة — قبلًا وبعدًا، ويُفحص مُرجَعُ كلِّ حذف. */
function sweep(mysqli $c, $CO, $MARK)
{
    $n = 0;
    foreach (array(
        "DELETE FROM `sal_client_needs`        WHERE `service_type` LIKE '{$MARK}%'",
        "DELETE FROM `sal_quotation_lines`     WHERE `description`  LIKE '{$MARK}%'",
        "DELETE FROM `sal_quotation_revisions` WHERE `note`         LIKE '{$MARK}%'",
        "DELETE FROM `sup_violations`          WHERE `description`  LIKE '{$MARK}%'",
    ) as $q) {
        if ($c->query($q)) { $n += $c->affected_rows; }
        else { echo "  ⚠ تعذّر الكنس: {$c->error}\n"; }
    }
    return $n;
}

echo "══ خدمةُ القدراتِ الخمسِ — شاهدٌ حيّ ══\n";
printf("  كُنس قبلَ الجولة: %d صفًّا\n\n", sweep($conn, $CO, $MARK));

/* مراسٍ حيّةٌ من البيانات — ولا يُختلق مفتاحٌ لا وجودَ له */
$r = $conn->query("SELECT `id` FROM `opportunities`
                    WHERE `company_id`={$CO} AND `is_deleted`=0
                      AND `stage` NOT IN ('فوز','خسارة','مستبعدة') ORDER BY `id` LIMIT 1");
$OPP_OPEN = $r ? (int) ($r->fetch_row()[0] ?? 0) : 0;
$r = $conn->query("SELECT `id` FROM `opportunities`
                    WHERE `company_id`={$CO} AND `stage` IN ('فوز','خسارة','مستبعدة') ORDER BY `id` LIMIT 1");
$OPP_SHUT = $r ? (int) ($r->fetch_row()[0] ?? 0) : 0;
$r = $conn->query("SELECT `id` FROM `quotations` WHERE `company_id`={$CO} ORDER BY `id` LIMIT 1");
$QUO = $r ? (int) ($r->fetch_row()[0] ?? 0) : 0;
$r = $conn->query("SELECT `id` FROM `suppliers` WHERE `company_id`={$CO} ORDER BY `id` LIMIT 1");
$SUP = $r ? (int) ($r->fetch_row()[0] ?? 0) : 0;
printf("  مراسٍ: فرصةٌ مفتوحة=%d · فرصةٌ مغلقة=%d · عرض=%d · مورّد=%d\n\n", $OPP_OPEN, $OPP_SHUT, $QUO, $SUP);

/* ══ ① احتياجُ العميلِ — شرطُ الإتاحةِ في الخدمة ═══════════════════════ */
echo "① احتياجُ العميلِ وطلبُ العرضِ — الورقة ٠٦\n";
$r1 = CAP::openNeed($conn, $gate, $CO, array(
    'opportunity_id' => 0, 'service_type' => $MARK . '-حفر', 'qty' => 10), $A);
chk(empty($r1['ok']) && (int) $r1['code'] === 422, '**لا احتياجَ بلا فرصةٍ أمّ** — السجلُّ تابعٌ لا مستقل', $r1['reason']);

if ($OPP_SHUT > 0) {
    $r2 = CAP::openNeed($conn, $gate, $CO, array(
        'opportunity_id' => $OPP_SHUT, 'service_type' => $MARK . '-حفر', 'qty' => 10), $A);
    chk(empty($r2['ok']) && (int) $r2['code'] === 409,
        '**ولا يُسجَّل على فرصةٍ مغلقة** — والشرطُ يُفحص في الخدمةِ لا في الزر', $r2['reason']);
} else { echo "  ◆ لا فرصةَ مغلقةً في البيانات — القاعدةُ غيرُ مقيسةٍ هذه الجولة\n"; }

$NEED = 0;
if ($OPP_OPEN > 0) {
    $r3 = CAP::openNeed($conn, $gate, $CO, array(
        'opportunity_id' => $OPP_OPEN, 'service_type' => $MARK . '-حفر', 'qty' => 10,
        'unit_type' => 'hour', 'duration_months' => 6), $A);
    chk(!empty($r3['ok']), 'ويُسجَّل على فرصةٍ مفتوحة', $r3['reason']);
    $NEED = (int) ($r3['id'] ?? 0);

    $r4 = CAP::openNeed($conn, $gate, $CO, array(
        'opportunity_id' => $OPP_OPEN, 'service_type' => $MARK . '-حفر', 'qty' => 10), $A);
    chk(empty($r4['ok']) && (int) $r4['code'] === 200,
        '**والتكرارُ عطالةٌ لا سجلٌّ ثانٍ** — بالخدمةِ والكميةِ نفسِهما', $r4['reason']);

    if ($NEED > 0) {
        $r5 = CAP::submitNeed($conn, $gate, $CO, $NEED);
        chk(!empty($r5['ok']), 'ويُرفع فيصير إصدارُ العرضِ متاحًا', $r5['reason']);
        $r6 = CAP::submitNeed($conn, $gate, $CO, $NEED);
        chk(empty($r6['ok']) && (int) $r6['code'] === 409,
            '**ولا يُرفع مرتين** — الانتقالُ من مسودّةٍ وحدَها', $r6['reason']);
    }
} else { echo "  ◆ لا فرصةَ مفتوحةً في البيانات — العقدةُ غيرُ مقيسةٍ هذه الجولة\n"; }

/* ══ ② بنودُ العرضِ — الحسابُ في الخدمةِ لا في الشاشة ══════════════════ */
echo "\n② بنودُ العروضِ — الورقة ٠٨\n";
$r7 = CAP::addQuotationLine($conn, $gate, $CO, array(
    'quotation_id' => $QUO, 'description' => $MARK . '-بند', 'qty' => 0, 'unit_price' => 5,
    'currency' => 'SDG'), $A);
chk(empty($r7['ok']) && (int) $r7['code'] === 422, 'كميةٌ غيرُ موجبةٍ تُردّ', $r7['reason']);

$r8 = CAP::addQuotationLine($conn, $gate, $CO, array(
    'quotation_id' => $QUO, 'description' => $MARK . '-بند', 'qty' => 4, 'unit_price' => 5,
    'currency' => ''), $A);
chk(empty($r8['ok']) && (int) $r8['code'] === 422, '**ولا مبلغَ بلا عملة**', $r8['reason']);

if ($QUO > 0) {
    $r9 = CAP::addQuotationLine($conn, $gate, $CO, array(
        'quotation_id' => $QUO, 'description' => $MARK . '-بند', 'qty' => 10, 'unit_price' => 100,
        'currency' => 'SDG', 'discount_pct' => 10), $A);
    chk(!empty($r9['ok']), 'ويُضاف البندُ برأسِ عرضٍ قائم', $r9['reason']);
    $lid = (int) ($r9['id'] ?? 0);
    if ($lid > 0) {
        $q = $conn->query("SELECT `line_total`, `line_no` FROM `sal_quotation_lines` WHERE `id`={$lid}");
        $row = $q ? $q->fetch_assoc() : array();
        /* ◆ **الإجماليُّ يُحسب في الخدمةِ ويُقارَن** — ١٠×١٠٠×٠٫٩ = ٩٠٠ */
        chk(abs((float) ($row['line_total'] ?? 0) - 900.00) < 0.005,
            '**والإجماليُّ يُحسب في الخدمةِ لا في الشاشة**',
            'المخزَّن=' . (string) ($row['line_total'] ?? '—') . ' · المتوقَّع=900.00');
        chk((int) ($row['line_no'] ?? 0) > 0, 'ورقمُ البندِ يُولَّد بالتسلسلِ لا يُدخَل',
            'رقمُه=' . (string) ($row['line_no'] ?? '—'));
    }
}

/* ══ ③ التفاوضُ — مفرداتٌ مغلقةٌ ونصٌّ إلزاميّ ═════════════════════════ */
echo "\n③ التفاوضُ ومراجعاتُ العرضِ — الورقة ٠٩\n";
$r10 = CAP::logNegotiation($conn, $gate, $CO, array(
    'quotation_id' => $QUO, 'event_kind' => 'haggle', 'party' => 'us',
    'note' => $MARK . ' نصٌّ كافٍ للشرح'), $A);
chk(empty($r10['ok']) && (int) $r10['code'] === 422, '**نوعُ الواقعةِ من قائمةٍ مغلقة**', $r10['reason']);

$r11 = CAP::logNegotiation($conn, $gate, $CO, array(
    'quotation_id' => $QUO, 'event_kind' => 'sent', 'party' => 'us', 'note' => 'قصير'), $A);
chk(empty($r11['ok']) && (int) $r11['code'] === 422, '**ولا واقعةَ بلا نصٍّ يشرحها**', $r11['reason']);

if ($QUO > 0) {
    $note = $MARK . ' أُرسل العرضُ للعميلِ بالبريدِ الرسميّ';
    $r12 = CAP::logNegotiation($conn, $gate, $CO, array(
        'quotation_id' => $QUO, 'event_kind' => 'sent', 'party' => 'us', 'note' => $note,
        'amount_before' => 1000, 'amount_after' => 950, 'currency' => 'SDG'), $A);
    chk(!empty($r12['ok']), 'وتُسجَّل الواقعةُ بنسختِها المتسلسلة', $r12['reason']);
    $r13 = CAP::logNegotiation($conn, $gate, $CO, array(
        'quotation_id' => $QUO, 'event_kind' => 'sent', 'party' => 'us', 'note' => $note), $A);
    chk(empty($r13['ok']) && (int) $r13['code'] === 200, 'والتكرارُ بالنصِّ نفسِه عطالة', $r13['reason']);
}

/* ══ ④ المخالفاتُ — وفصلُ الواجباتِ أهمُّ ما فيها ══════════════════════ */
echo "\n④ المخالفاتُ والجزاءاتُ — الورقة م١٩\n";
$r14 = CAP::recordViolation($conn, $gate, $CO, array(
    'supplier_id' => $SUP, 'violation_kind' => 'lateness', 'occurred_on' => '2099-01-05',
    'description' => $MARK . ' تأخُّرٌ في التوريد'), $A);
chk(empty($r14['ok']) && (int) $r14['code'] === 422, '**نوعُ المخالفةِ من قائمةٍ مغلقة**', $r14['reason']);

$r15 = CAP::recordViolation($conn, $gate, $CO, array(
    'supplier_id' => $SUP, 'violation_kind' => 'delay', 'occurred_on' => '2099-01-05',
    'description' => $MARK . ' تأخُّرٌ في التوريد', 'penalty_amount' => 500, 'currency' => ''), $A);
chk(empty($r15['ok']) && (int) $r15['code'] === 422, '**ولا جزاءَ بمبلغٍ بلا عملة**', $r15['reason']);

$VIO = 0;
if ($SUP > 0) {
    $r16 = CAP::recordViolation($conn, $gate, $CO, array(
        'supplier_id' => $SUP, 'violation_kind' => 'delay', 'occurred_on' => '2099-01-05',
        'description' => $MARK . ' تأخُّرٌ في التوريد', 'penalty_amount' => 500,
        'currency' => 'SDG', 'evidence_ref' => 'DOC-2099-1'), $A);
    chk(!empty($r16['ok']), 'وتُرصد المخالفةُ بوصفٍ ودليل', $r16['reason']);
    $VIO = (int) ($r16['id'] ?? 0);

    $r17 = CAP::recordViolation($conn, $gate, $CO, array(
        'supplier_id' => $SUP, 'violation_kind' => 'delay', 'occurred_on' => '2099-01-05',
        'description' => $MARK . ' تأخُّرٌ في التوريد', 'penalty_amount' => 500, 'currency' => 'SDG'), $A);
    chk(empty($r17['ok']) && (int) $r17['code'] === 200,
        'والتكرارُ بالتاريخِ والنوعِ نفسِهما عطالة', $r17['reason']);
}

if ($VIO > 0) {
    /* ◆ **الحزامُ السلبيُّ**: تُحاوَل المخالفةُ بيدِ الراصدِ نفسِه فتُردّ */
    $r18 = CAP::approveViolation($conn, $gate, $CO, $VIO, $A);
    chk(empty($r18['ok']) && (int) $r18['code'] === 403,
        '**مَن رصد لا يعتمد** — والجزاءُ أثرٌ ماليٌّ لا يقرّره راصدُه وحدَه', $r18['reason']);

    $r19 = CAP::waiveViolation($conn, $gate, $CO, $VIO, 'سببٌ مكتوبٌ كافٍ للإسقاط', $A);
    chk(empty($r19['ok']) && (int) $r19['code'] === 403, '**ومَن رصد لا يُسقط**', $r19['reason']);

    $r20 = CAP::waiveViolation($conn, $gate, $CO, $VIO, 'قصير', $B);
    chk(empty($r20['ok']) && (int) $r20['code'] === 422, '**ولا إسقاطَ بلا سببٍ مكتوبٍ مفهوم**', $r20['reason']);

    $r21 = CAP::approveViolation($conn, $gate, $CO, $VIO, $B);
    chk(!empty($r21['ok']), 'ويعتمدها غيرُ راصدِها — يدانِ لا واحدة', $r21['reason']);

    $r22 = CAP::approveViolation($conn, $gate, $CO, $VIO, $B);
    chk(empty($r22['ok']) && (int) $r22['code'] === 200, 'والاعتمادُ الثاني عطالةٌ لا أثرٌ ثانٍ', $r22['reason']);

    $r23 = CAP::waiveViolation($conn, $gate, $CO, $VIO, 'سببٌ مكتوبٌ كافٍ للإسقاط', $B);
    chk(empty($r23['ok']) && (int) $r23['code'] === 409,
        '**ولا يُسقَط ما اعتُمد** — الإسقاطُ قبلَ الاعتمادِ لا بعدَه', $r23['reason']);

    $q = $conn->query("SELECT `recorded_by`, `approved_by`, `state` FROM `sup_violations` WHERE `id`={$VIO}");
    $row = $q ? $q->fetch_assoc() : array();
    chk((int) ($row['recorded_by'] ?? 0) !== (int) ($row['approved_by'] ?? 0),
        '**والأثرُ التدقيقيُّ يحفظ اليدَين متمايزتَين**',
        'رصدها=' . (string) ($row['recorded_by'] ?? '—') . ' · اعتمدها=' . (string) ($row['approved_by'] ?? '—'));
}

/* ══ ⑤ العمودُ المُعلَنُ يقول الحقّ — لا صفرًا صامتًا ══════════════════
 * ◆ **الشاهدُ أمسك هذا حيًّا**: البوابةُ تعزل الأبناءَ **بأبيهم** فلا تحقن
 *   `company_id`، والعمودُ مُعلَنٌ في الجداولِ الثلاثة — فبقي صفرًا. والقراءةُ
 *   سليمةٌ لأن العزلَ بالأب، لكنَّ **أيَّ استعلامٍ لاحقٍ يرشّح بالعمودِ يعود
 *   بصفرِ صفوفٍ صامتًا**. فيُكتب صراحةً في الخدمة، ويُقاس هنا. */
echo "
⑤ العمودُ المُعلَنُ يقول الحقَّ لا صفرًا
";
foreach (array(
    array('sal_client_needs', 'service_type', 'احتياجُ العميل'),
    array('sal_quotation_lines', 'description', 'بندُ العرض'),
    array('sal_quotation_revisions', 'note', 'واقعةُ التفاوض'),
    array('sup_violations', 'description', 'المخالفة'),
) as $t) {
    $q = $conn->query("SELECT COUNT(*) AS n, SUM(`company_id` = {$CO}) AS good
                          FROM `{$t[0]}` WHERE `{$t[1]}` LIKE '{$MARK}%'");
    $x = $q ? $q->fetch_assoc() : array('n' => 0, 'good' => 0);
    $n = (int) $x['n']; $g = (int) $x['good'];
    if ($n === 0) { echo "  ◆ {$t[2]}: لا صفَّ في هذه الجولة — غيرُ مقيس
"; continue; }
    chk($g === $n, "**و`company_id` مكتوبٌ بالحقِّ في** {$t[2]}", "{$g}/{$n} صفًّا بالشركة {$CO}");
}

/* ══ الكنسُ البعديُّ — بالعائلةِ لا بالفرد ═══════════════════════════ */
printf("\n  كُنس بعدَ الجولة: %d صفًّا\n", sweep($conn, $CO, $MARK));
$q = $conn->query("SELECT (SELECT COUNT(*) FROM `sal_client_needs`        WHERE `service_type` LIKE '{$MARK}%')
                        + (SELECT COUNT(*) FROM `sal_quotation_lines`     WHERE `description`  LIKE '{$MARK}%')
                        + (SELECT COUNT(*) FROM `sal_quotation_revisions` WHERE `note`         LIKE '{$MARK}%')
                        + (SELECT COUNT(*) FROM `sup_violations`          WHERE `description`  LIKE '{$MARK}%')");
$left = $q ? (int) $q->fetch_row()[0] : -1;
chk($left === 0, '**صفرُ بقيةٍ من هذه الجولة** — الشاهدُ لا يترك أثرًا في البيانات', "المتبقي={$left}");

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
