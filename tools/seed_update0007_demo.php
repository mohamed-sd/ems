<?php
/**
 * tools/seed_update0007_demo.php — باذرُ تجربة update0007-ب الكاملة (UAT)
 * ───────────────────────────────────────────────────────────────────────────
 * يملأ كلَّ جداول الوحدات الجديدة ببياناتٍ تجريبيةٍ مترابطةٍ (co4) بلا جدولٍ
 * فارغ: التمويلُ (عمليات · أقساطٌ متسقةُ الرصيد · أعيانٌ · حصصٌ Σ=100 ·
 * منحُ اطّلاع · انحرافات) والتوظيفُ (شواغرُ · متقدمون على المراحل الاثنتي
 * عشرة · سجلُّ انتقالٍ كامل) والتغطياتُ بتسوياتها ودفترُ القدرات.
 * آمنُ الإعادة: يتوقف إن وجد البذرة (op_code=FIN-2026-001).
 * التشغيل: php tools/seed_update0007_demo.php
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$CO = 4;                 // شركة الاختبار
$FIN_USER = 859;         // مدير التمويل (دور 26)
$HR_USER  = 7;           // الموارد البشرية (دور 4)

$run = function ($sql) use ($conn) {
    if (!mysqli_query($conn, $sql)) {
        fwrite(STDERR, "✘ SQL: " . mysqli_error($conn) . "\n" . mb_substr($sql, 0, 160) . "\n");
        exit(1);
    }
    return mysqli_insert_id($conn);
};
$esc = function ($s) use ($conn) { return mysqli_real_escape_string($conn, (string)$s); };

$r = mysqli_query($conn, "SELECT 1 FROM financing_operations WHERE op_code = 'FIN-2026-001' LIMIT 1");
if ($r && mysqli_num_rows($r)) { echo "البذرةُ موجودةٌ من قبل — لا شيءَ يُعاد\n"; exit(0); }

/* ─── ① المموّلون (legal_entities) ─────────────────────────────────────── */
$financiers = array();
foreach (array(
    array('بنك أم درمان الوطني', 'BNK-ONB-114'),
    array('بنك فيصل الإسلامي السوداني', 'BNK-FIB-208'),
    array('مصرف التنمية الصناعية', 'BNK-IDB-301'),
    array('شركة الإجارة الوطنية للتأجير التمويلي', 'LSE-NLC-052'),
    array('بنك الخرطوم — فرع المعدات', 'BNK-BOK-417'),
    array('مجموعة الفطيم للتمويل التجاري', 'FIN-AFG-660'),
) as $f) {
    $financiers[] = $run("INSERT INTO legal_entities (legal_name, country, registry_authority, commercial_reg, base_currency, is_tenant, ownership_completeness, state)
        VALUES ('{$esc($f[0])}', 'SD', 'السجل التجاري', '{$f[1]}', 'SDG', 0, 'full', 'active')");
}
foreach ($financiers as $fe) { // دورُ «مموّل» في سجل أدوار الكيانات — لوحةُ التمويل تعدُّ منه
    $run("INSERT INTO entity_roles (entity_id, role, valid_from, doc_ref) VALUES ($fe, 'financier', '2025-01-01', 'REG/FIN/$fe')");
}
$TENANT_ENT = 2; // «ايكوبيشن» كيان المستأجر نفسه شريكًا في المشاركات
echo "① المموّلون: " . count($financiers) . "\n";

/* ─── ② عملياتُ التمويل العشرون بأقساطها المتسقة ──────────────────────── */
$models = array('murabaha', 'ijara_op', 'fixed_yield', 'musharaka');
$states = array('negotiation','signed','active','active','paying','paying','paying','paying','active','settled',
                'settled','settled','settled','closed','closed','paying','active','paying','defaulted','signed');
$eqPool = array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,18,19,20,21,22,23);
$ops = array(); $instTotal = 0; $shareRows = 0; $faRows = 0;
for ($i = 1; $i <= 20; $i++) {
    $code   = sprintf('FIN-2026-%03d', $i);
    $fe     = $financiers[($i - 1) % count($financiers)];
    $model  = $models[($i - 1) % 4];
    $cur    = ($i % 3 === 0) ? 'SDG' : 'USD';
    $capital = ($cur === 'USD') ? (40000 + $i * 9500) : (18000000 + $i * 2400000);
    $down   = round($capital * 0.15, 2);
    $rate   = 8 + ($i % 5) * 1.5;                       // 8% → 14%
    $profit = round($capital * $rate / 100, 2);
    $n      = array(12, 18, 24, 36)[$i % 4];
    $instAmt = round(($capital + $profit - $down) / $n, 2);
    $signed = sprintf('2025-%02d-%02d', ($i % 9) + 1, ($i % 27) + 1);
    $state  = $states[$i - 1];
    $mat    = date('Y-m-d', strtotime("$signed +$n months"));
    $eq     = $eqPool[$i - 1];

    $opId = $run("INSERT INTO financing_operations (company_id, op_code, financier_entity_id, model_code, currency,
            contract_ref, signed_date, capital, capital_source, purchase_value, down_payment, fees_admin, fees_insurance,
            profit_rate, profit_amount, installments_no, installment_amount, outstanding_balance, maturity_date, state, created_by)
        VALUES ($CO, '$code', $fe, '$model', '$cur', 'CTR/{$code}/{$esc($model)}', '$signed', $capital,
            'تمويل شراء معدة ثقيلة — عرض سعر معتمد', " . round($capital * 1.12, 2) . ", $down,
            " . round($capital * 0.006, 2) . ", " . round($capital * 0.011, 2) . ",
            $rate, $profit, $n, $instAmt, $capital, '$mat', '{$esc($state)}', $FIN_USER)");
    $ops[] = array('id' => $opId, 'code' => $code, 'fe' => $fe, 'model' => $model, 'cur' => $cur,
                   'capital' => $capital, 'profit' => $profit, 'n' => $n, 'signed' => $signed,
                   'state' => $state, 'eq' => $eq);

    /* العينُ الممولة */
    $run("INSERT INTO financed_assets (op_id, asset_id, asset_kind, purchase_value, in_fleet, in_asset_register)
          VALUES ($opId, $eq, 'equipment', " . round($capital * 1.12, 2) . ", 1, 1)");
    $faRows++;

    /* الأقساطُ لكل عقدٍ موقَّعٍ فما فوق — والمسدَّدُ بحسب الحالة */
    if (!in_array($state, array('negotiation'), true)) {
        $prinPer = round($capital / $n, 2);
        $profPer = round($profit / $n, 2);
        $paidK = 0;
        if (in_array($state, array('settled', 'closed'), true)) $paidK = $n;
        elseif ($state === 'paying')    $paidK = intdiv($n, 2) + ($i % 3);
        elseif ($state === 'active')    $paidK = ($i % 4);
        elseif ($state === 'defaulted') $paidK = 3;
        $paidPrincipal = 0;
        for ($s = 1; $s <= $n; $s++) {
            $due = date('Y-m-d', strtotime("$signed +$s months"));
            if ($s <= $paidK) {
                $st = 'paid';
                $pd = "'" . date('Y-m-d', strtotime("$due +" . ($s % 6) . " days")) . "'";
                $ref = "'BNK/{$code}/S$s'";
                $paidPrincipal += $prinPer;
            } else {
                $st = (strtotime($due) < strtotime('2026-08-02'))
                    ? (($state === 'defaulted' || $s <= $paidK + 2) ? 'overdue' : 'due')
                    : 'scheduled';
                $pd = 'NULL'; $ref = 'NULL';
            }
            $run("INSERT INTO financing_installments (op_id, seq_no, due_date, amount_principal, amount_profit,
                    amount_total, currency, paid_date, payment_ref, state)
                  VALUES ($opId, $s, '$due', $prinPer, $profPer, " . ($prinPer + $profPer) . ", '{$ops[$i-1]['cur']}', $pd, $ref, '$st')");
            $instTotal++;
        }
        $outstanding = max(0, round($capital - $paidPrincipal, 2));
        $run("UPDATE financing_operations SET outstanding_balance = $outstanding WHERE op_id = $opId");
    }

    /* حصصُ الملكية Σ=100 لكل أصلٍ ممول (المرابحة والمشاركة تُنشئان ملكية) */
    if (in_array($model, array('murabaha', 'musharaka'), true)) {
        $p = ($model === 'musharaka') ? (40 + ($i % 4) * 10) : 100; // المشاركة حصتان والمرابحة كاملة للمموّل حتى السداد
        if ($p < 100) {
            $run("INSERT INTO asset_ownership_shares (company_id, asset_id, asset_kind, financier_entity_id, op_id, model_code,
                    percent, valid_from, capital, doc_ref, created_by)
                  VALUES ($CO, $eq, 'equipment', $fe, $opId, '$model', $p, '$signed', " . round($capital * $p / 100, 2) . ",
                          'WQL/{$code}/A', $FIN_USER)");
            $run("INSERT INTO asset_ownership_shares (company_id, asset_id, asset_kind, financier_entity_id, op_id, model_code,
                    percent, valid_from, capital, doc_ref, created_by)
                  VALUES ($CO, $eq, 'equipment', $TENANT_ENT, $opId, '$model', " . (100 - $p) . ", '$signed',
                          " . round($capital * (100 - $p) / 100, 2) . ", 'WQL/{$code}/B', $FIN_USER)");
            $shareRows += 2;
        } else {
            $run("INSERT INTO asset_ownership_shares (company_id, asset_id, asset_kind, financier_entity_id, op_id, model_code,
                    percent, valid_from, capital, doc_ref, created_by)
                  VALUES ($CO, $eq, 'equipment', $fe, $opId, '$model', 100, '$signed', $capital, 'WQL/{$code}', $FIN_USER)");
            $shareRows++;
        }
    }
}
/* تاريخُ نقلِ حصةٍ لثلاثة أصول: القديمةُ تُغلق وتُفتح خلفاؤها (لا تعديلَ رجعيًّا) */
$r = mysqli_query($conn, "SELECT share_id, asset_id, financier_entity_id, op_id, model_code, percent, capital
                          FROM asset_ownership_shares WHERE company_id = $CO AND percent < 100 AND valid_to IS NULL
                          ORDER BY share_id LIMIT 3");
while ($sh = mysqli_fetch_assoc($r)) {
    $moved = round($sh['percent'] / 2, 2);
    $rest  = round($sh['percent'] - $moved, 2);
    $newFe = $financiers[5];
    $run("UPDATE asset_ownership_shares SET valid_to = '2026-06-30' WHERE share_id = {$sh['share_id']}");
    $run("INSERT INTO asset_ownership_shares (company_id, asset_id, asset_kind, financier_entity_id, op_id, model_code,
            percent, valid_from, capital, doc_ref, created_by)
          VALUES ($CO, {$sh['asset_id']}, 'equipment', {$sh['financier_entity_id']}, " . intval($sh['op_id']) . ",
                  '{$sh['model_code']}', $rest, '2026-07-01', " . round($sh['capital'] * $rest / $sh['percent'], 2) . ",
                  'TRF/2026/{$sh['share_id']}/R', $FIN_USER)");
    $run("INSERT INTO asset_ownership_shares (company_id, asset_id, asset_kind, financier_entity_id, op_id, model_code,
            percent, valid_from, capital, doc_ref, created_by)
          VALUES ($CO, {$sh['asset_id']}, 'equipment', $newFe, " . intval($sh['op_id']) . ",
                  '{$sh['model_code']}', $moved, '2026-07-01', " . round($sh['capital'] * $moved / $sh['percent'], 2) . ",
                  'TRF/2026/{$sh['share_id']}/M', $FIN_USER)");
    $shareRows += 2;
}
echo "② العمليات: 20 · الأقساط: $instTotal · الأعيان: $faRows · الحصص: $shareRows\n";

/* سجلُّ ملكية المعدات: صفٌّ لكل معدةٍ ممولةٍ ليست فيه (شاشةُ الملّاك تقود منه) */
foreach ($ops as $k => $op) {
    $r2 = mysqli_query($conn, "SELECT 1 FROM equipment_ownership_registry WHERE company_id = $CO AND equipment_id = {$op['eq']} LIMIT 1");
    if ($r2 && mysqli_num_rows($r2)) continue;
    $feName = array('بنك أم درمان الوطني','بنك فيصل الإسلامي السوداني','مصرف التنمية الصناعية',
                    'شركة الإجارة الوطنية للتأجير التمويلي','بنك الخرطوم — فرع المعدات','مجموعة الفطيم للتمويل التجاري')[$k % 6];
    $run("INSERT INTO equipment_ownership_registry (company_id, equipment_id, actual_owner_name, owner_type,
            operational_source, purchase_value, purchase_currency, migrated_from, note, source_decided_by, source_decided_at)
          VALUES ($CO, {$op['eq']}, '{$esc($feName)}', 'مموّل', 'financed', " . round($op['capital'] * 1.12, 2) . ",
                  '{$op['cur']}', 'seed_demo', 'عينٌ ممولةٌ بعقد {$op['code']}', $FIN_USER, NOW())");
}

/* ─── ③ منحُ الاطّلاع العشرون ─────────────────────────────────────────── */
$perms = array('ownership.owner_view', 'ownership.finance_terms', 'ownership.purchase_value');
$grantUsers = array(4,5,6,7,8,13,16,19,20,43,56,58,69,71,72,73,74,75,76,77);
foreach ($grantUsers as $k => $u) {
    $pc = $perms[$k % 3];
    $state = ($k % 7 === 6) ? 'revoked' : 'active';
    $rev = ($state === 'revoked') ? ", revoked_by = $FIN_USER, revoked_at = '2026-07-2" . ($k % 8) . " 10:00:00'" : '';
    // «قيمةُ الشراء» أشدُّ المنح: القيد يوجب سببًا ومدةً محدودةَ الطرفين
    $vt = ($pc === 'ownership.purchase_value' || $k % 2) ? "'2026-12-31'" : 'NULL';
    $run("INSERT INTO ownership_access_grants (company_id, person_id, permission_code, reason, valid_from, valid_to, granted_by, state)
          VALUES ($CO, $u, '$pc', 'حاجةُ عملٍ موثَّقة — مذكرة OWN/2026/" . (100 + $k) . "', '2026-07-01',
                  $vt, $FIN_USER, '$state')");
    if ($rev !== '') $run("UPDATE ownership_access_grants SET state = 'revoked'$rev WHERE company_id = $CO AND person_id = $u AND permission_code = '$pc'");
}
echo "③ منحُ الاطّلاع: " . count($grantUsers) . "\n";

/* ─── ④ الانحرافاتُ العشرون ───────────────────────────────────────────── */
$devTypes = array('no_ledger', 'payment_gap', 'unrecorded_exit');
for ($i = 1; $i <= 20; $i++) {
    $t = $devTypes[$i % 3];
    $subj = ($t === 'unrecorded_exit') ? "asset:eq#" . $eqPool[$i - 1] : "op:" . $ops[$i - 1]['code'];
    $pri = array('low', 'normal', 'high')[$i % 3];
    $descMap = array(
        'no_ledger'       => 'عقدُ تمويلٍ نشطٌ بلا حركةِ سدادٍ مسجلةٍ تسعين يومًا',
        'payment_gap'     => 'أقساطٌ تجاوزت استحقاقَها بلا سدادٍ ولا إعادةِ جدولة',
        'unrecorded_exit' => 'عينٌ خرجت من الأسطول ولم يُسجَّل التصرفُ في سجل الملكية',
    );
    if ($i <= 8) {
        $run("INSERT INTO financing_deviations (company_id, dev_type, subject_ref, description, priority, required_doc, state,
                decision, decision_doc_ref, closed_by, closed_at)
              VALUES ($CO, '$t', '{$esc($subj)}', '{$descMap[$t]}', '$pri', 'كشفُ سدادٍ بنكيٌّ مختوم', 'closed',
                      'عولج بإعادة جدولةٍ معتمدةٍ من لجنة التمويل — محضر FIN/CMT/2026/$i', 'DOC/DEV/2026/$i', $FIN_USER,
                      '2026-07-" . sprintf('%02d', ($i % 27) + 1) . " 12:30:00')");
    } else {
        $run("INSERT INTO financing_deviations (company_id, dev_type, subject_ref, description, priority, required_doc, state)
              VALUES ($CO, '$t', '{$esc($subj)}', '{$descMap[$t]}', '$pri', 'كشفُ سدادٍ بنكيٌّ مختوم', 'open')");
    }
}
echo "④ الانحرافات: 20 (8 مغلقةٌ بقرار · 12 مفتوحة)\n";

/* ─── ⑤ دورةُ التوظيف: شواغرُ ومتقدمون وسجلُّ مراحلَ كامل ─────────────── */
$titles = array('مشغل حفار ثقيل','سائق قلاب مفصلي','ميكانيكي معدات ثقيلة','كهربائي معدات','فني هيدروليك',
    'مشرف موقع تعدين','مساح كميات','محاسب مواقع','أمين مخزن قطع غيار','مسؤول سلامة مهنية',
    'مشغل بلدوزر','مشغل جريدر','سائق شاحنة وقود','فني إطارات','لحّام هياكل',
    'منسق حركة','كاتب ورديات','مراقب جودة خام','فني تشحيم','مساعد إداري مواقع');
$vacIds = array();
for ($i = 1; $i <= 20; $i++) {
    $state = ($i <= 12) ? 'open' : (($i <= 17) ? 'filled' : 'cancelled');
    $vacIds[] = $run("INSERT INTO rec_vacancies (company_id, vacancy_no, job_title_id, title_text, org_unit_id, site_scope,
            headcount, reason, state, posted_at, created_by)
          VALUES ($CO, 'VAC-2026-" . sprintf('%03d', $i) . "', " . (($i % 10) + 1) . ", '{$esc($titles[$i-1])}',
                  " . (($i % 15) + 1) . ", '" . ($i % 2 ? 'منجم إليانس' : 'عموم المواقع') . "', " . (1 + $i % 3) . ",
                  'توسعةُ العمليات — طلبُ إدارة التشغيل رقم OPS/REQ/2026/$i', '$state',
                  '2026-07-" . sprintf('%02d', ($i % 27) + 1) . "', $HR_USER)");
}
$STAGES = array('received','screening','interview','practical_test','offer','offer_accepted','contracting','onboarded','probation','confirmed');
$names = array('أحمد عبد الرحمن الطيب','محمد الفاتح عثمان','عمر بشير الأمين','خالد حسن إدريس','مصطفى النور عبد الله',
    'إبراهيم موسى آدم','يوسف الصادق المهدي','عبد العزيز الجيلي','الطاهر محجوب سليمان','بكري عوض الكريم',
    'حسام الدين الرشيد','معتصم قسم السيد','وليد أبو القاسم','صلاح التجاني بابكر','منتصر الهادي جبريل',
    'أسامة عبد الغفار','هيثم الزبير محمد','نادر السر الخليفة','عماد حمد النيل','فيصل عبد الماجد',
    'رامي إسحق النور','قصي عبد الوهاب','مأمون الطريفي','شهاب الدين كرار','عاطف مكي عبد القادر',
    'زين العابدين فرح','المعز عبد الحفيظ','أنس محمد زين','حاتم البشير علي','طارق منصور أرباب',
    'ضياء الدين حامد','مجاهد عثمان النذير');
$stageOf = array('received','received','received','received','screening','screening','screening','screening',
    'interview','interview','interview','interview','practical_test','practical_test','practical_test',
    'offer','offer','offer','offer_accepted','offer_accepted','contracting','contracting',
    'onboarded','onboarded','probation','probation','probation','confirmed','confirmed','rejected','rejected','withdrawn');
$empPool = array(30,31,32,33,34,35,36); $ep = 0; $logRows = 0;
foreach ($stageOf as $k => $stage) {
    $vac = $vacIds[$k % 12]; // على الشواغر المفتوحة
    $emp = 'NULL'; $prob = 'NULL'; $score = 'NULL'; $offer = 'NULL'; $ivw = 'NULL';
    $idx = array_search($stage, $STAGES, true);
    if ($idx !== false && $idx >= 2) $ivw = "'2026-07-" . sprintf('%02d', ($k % 25) + 1) . " 10:00:00'";
    if ($idx !== false && $idx >= 3) $score = (string)(62 + ($k * 3) % 36);
    if ($idx !== false && $idx >= 4) $offer = "'OFR/2026/" . (200 + $k) . "'";
    if ($idx !== false && $idx >= 7) { $emp = (string)$empPool[$ep % count($empPool)]; $ep++; }
    if (in_array($stage, array('probation', 'confirmed'), true)) $prob = "'" . date('Y-m-d', strtotime('2026-07-15 +90 days')) . "'";
    $note = ($stage === 'rejected') ? 'لم يجتز الاختبارَ العمليَّ — الدرجةُ دون الحد' : (($stage === 'withdrawn') ? 'انسحب لقبوله عرضًا آخر' : '');
    $appId = $run("INSERT INTO rec_applications (company_id, vac_id, applicant_name, applicant_phone, cv_ref, stage,
            stage_note, interview_at, test_score, offer_ref, employee_id, probation_end)
          VALUES ($CO, $vac, '{$esc($names[$k])}', '09" . sprintf('%08d', 12000000 + $k * 137) . "',
                  'CV/2026/" . (500 + $k) . ".pdf', '$stage', '{$esc($note)}', $ivw, $score, $offer, $emp, $prob)");
    /* سجلُّ الانتقال الكامل من الاستلام حتى مرحلته الحالية */
    $path = ($idx === false) ? array_slice($STAGES, 0, min(4, ($k % 4) + 1)) : array_slice($STAGES, 0, $idx + 1);
    $prev = 'NULL';
    foreach ($path as $step => $st2) {
        $at = date('Y-m-d H:i:s', strtotime('2026-06-20 09:00:00 +' . ($k * 11 + $step * 29) . ' hours'));
        $run("INSERT INTO rec_stage_log (app_id, from_stage, to_stage, note, by_person, at)
              VALUES ($appId, $prev, '$st2', 'انتقالٌ موثَّق من شاشة دورة التوظيف', $HR_USER, '$at')");
        $prev = "'$st2'"; $logRows++;
    }
    if ($idx === false) { // rejected/withdrawn: خطوةُ الخروج
        $run("INSERT INTO rec_stage_log (app_id, from_stage, to_stage, note, by_person, at)
              VALUES ($appId, $prev, '$stage', '{$esc($note)}', $HR_USER, '2026-07-30 14:00:00')");
        $logRows++;
    }
}
echo "⑤ الشواغر: 20 · المتقدمون: " . count($stageOf) . " · سجلُّ المراحل: $logRows\n";

/* ─── ⑥ التغطياتُ بالبدائل وتسوياتُها ─────────────────────────────────── */
$seatRows = array();
$r = mysqli_query($conn, "SELECT id, supplier_id, equipment_id FROM op_containers
                          WHERE company_id = $CO AND is_deleted = 0 ORDER BY id LIMIT 40");
while ($x = mysqli_fetch_assoc($r)) $seatRows[] = $x;
$covStates = array('pending_approvals','pending_approvals','pending_approvals','approved','approved','approved','approved','approved',
    'active','active','active','active','active','active','ended','ended','ended','ended','rejected','rejected');
$levels  = array('own_standby', 'cross_supplier', 'source_change');
$reasons = array('breakdown', 'scheduled_maintenance', 'relocation_exit', 'document_expired', 'operator_shortage');
$covIds = array(); $lnRows = 0;
for ($i = 1; $i <= 20; $i++) {
    $seat = $seatRows[($i * 2 - 1) % count($seatRows)];
    $lvl  = $levels[$i % 3];
    $cs   = (($i % 8) === 0) ? 8 : (($i % 7) + 1);
    $fs   = ($lvl === 'cross_supplier') ? ((($cs % 7) + 1)) : 'NULL';
    $ceq  = $eqPool[($i + 7) % 20];
    $st   = $covStates[$i - 1];
    $vf   = '2026-07-' . sprintf('%02d', ($i % 25) + 1);
    $vt   = date('Y-m-d', strtotime("$vf +" . (3 + $i % 10) . " days"));
    $cov = $run("INSERT INTO substitute_coverages (company_id, level, covered_seat_id, covering_supplier_id,
            failed_supplier_id, covering_equipment_id, reason_code, reason_ref, valid_from, valid_to, estimated_hours,
            approvals_ref, approvals_json, state, note, created_by)
          VALUES ($CO, '$lvl', {$seat['id']}, $cs, $fs, $ceq, '{$reasons[$i % 5]}', 'MNT/2026/" . (300 + $i) . "',
                  '$vf', '$vt', " . (24 + ($i * 7) % 90) . ", 'APR/COV/2026/$i',
                  '{\"site_manager\": \"" . ($st === 'pending_approvals' ? 'pending' : 'approved') . "\", \"movement\": \"" . (in_array($st, array('pending_approvals','approved')) && $i % 2 ? 'pending' : 'approved') . "\"}',
                  '$st', 'تغطيةُ مقعدٍ متوقفٍ وفقَ سلم البدائل الثلاثي', 4)");
    $covIds[] = array('id' => $cov, 'state' => $st, 'cs' => $cs, 'fs' => ($fs === 'NULL' ? 0 : $fs), 'i' => $i);
    /* التسويةُ الرباعيةُ للمنتهية والنشطة: العميلُ يُفوتَر والفاجعُ يحمل الفجوةَ والمغطي سطرٌ استثنائي والمشغلُ استحقاق */
    if (in_array($st, array('active', 'ended'), true)) {
        $qty = 8 + ($i * 5) % 40;
        $run("INSERT INTO coverage_settlement_lines (company_id, cov_id, party, effect, qty, measure_code, amount, currency, settlement_ref, note)
              VALUES ($CO, $cov, 'client', 'billable', $qty, 'hour', " . ($qty * 95) . ", 'USD', 'STL/2026/$i/C', 'ساعاتُ التغطية تُفوتر للعميل كالتزامِ العقد')");
        $lnRows++;
        if ($covIds[count($covIds) - 1]['fs']) {
            $run("INSERT INTO coverage_settlement_lines (company_id, cov_id, party, effect, qty, measure_code, amount, currency, settlement_ref, note)
                  VALUES ($CO, $cov, 'failed_supplier', 'gap_kept', $qty, 'hour', 0, 'USD', 'STL/2026/$i/F', 'الفجوةُ تبقى على المورد الفاجع — لا تُنسب له ساعاتُ الغير')");
            $lnRows++;
        }
        $run("INSERT INTO coverage_settlement_lines (company_id, cov_id, party, effect, qty, measure_code, amount, currency, settlement_ref, note)
              VALUES ($CO, $cov, 'covering_supplier', 'exceptional_line', $qty, 'hour', " . ($qty * 70) . ", 'USD', 'STL/2026/$i/S', 'سطرٌ استثنائيٌّ للمغطي خارجَ حصته الأصلية')");
        $lnRows++;
        if ($i % 2) {
            $run("INSERT INTO coverage_settlement_lines (company_id, cov_id, party, effect, qty, measure_code, amount, currency, settlement_ref, note)
                  VALUES ($CO, $cov, 'operator', 'entitlement', $qty, 'hour', " . ($qty * 6) . ", 'USD', 'STL/2026/$i/O', 'استحقاقُ مشغلِ ساعاتِ التغطية')");
            $lnRows++;
        }
    }
}
echo "⑥ التغطيات: 20 · سطورُ التسوية: $lnRows\n";

/* ─── ⑦ دفترُ استهلاك القدرات ─────────────────────────────────────────── */
$ueIds = array();
$r = mysqli_query($conn, "SELECT id, qty FROM unit_entries WHERE company_id = $CO AND qty > 0 ORDER BY id DESC LIMIT 45");
while ($x = mysqli_fetch_assoc($r)) $ueIds[] = $x;
$ledRows = 0; $firstLed = 0;
foreach (array_slice($ueIds, 0, 14) as $k => $ue) {
    $q = floatval($ue['qty']);
    $sup = (($k % 7) + 1);
    $per = ($k % 3 === 0) ? '2026-08' : '2026-07';
    /* الثلاثيةُ المتوازنة: التزامُ عميلٍ وحصةُ موردٍ واستحقاقُ مشغلٍ للواقعة نفسِها */
    $l1 = $run("INSERT INTO capacity_consumption_ledger (company_id, unit_record_id, unit_record_version, effect_target_type,
            effect_target_ref, measure_code, qty, operational_hours, effect_type, role_snapshot, period, created_by)
          VALUES ($CO, {$ue['id']}, 1, 'client', 'contract:main', 'hour', $q, $q, 'client_obligation', 'primary', '$per', 4)");
    if (!$firstLed) $firstLed = $l1;
    $run("INSERT INTO capacity_consumption_ledger (company_id, unit_record_id, unit_record_version, effect_target_type,
            effect_target_ref, measure_code, qty, operational_hours, effect_type, role_snapshot, period, created_by)
          VALUES ($CO, {$ue['id']}, 1, 'supplier', 'supplier:$sup', 'hour', $q, $q, 'supplier_share', 'primary', '$per', 4)");
    $run("INSERT INTO capacity_consumption_ledger (company_id, unit_record_id, unit_record_version, effect_target_type,
            effect_target_ref, measure_code, qty, operational_hours, effect_type, role_snapshot, period, created_by)
          VALUES ($CO, {$ue['id']}, 1, 'operator', 'employee:" . (($k % 20) + 1) . "', 'hour', $q, $q, 'operator_entitlement', 'primary', '$per', 4)");
    $ledRows += 3;
}
/* تغطيتان استثنائيتان وعكسٌ واحدٌ يشهد لقاعدة «العكسُ صفٌّ لا حذف» */
foreach (array_slice($covIds, 8, 2) as $k => $cv) {
    $ue = $ueIds[20 + $k];
    $run("INSERT INTO capacity_consumption_ledger (company_id, unit_record_id, unit_record_version, effect_target_type,
            effect_target_ref, measure_code, qty, operational_hours, effect_type, role_snapshot, coverage_id, period, created_by)
          VALUES ($CO, {$ue['id']}, 1, 'supplier', 'supplier:{$cv['cs']}', 'hour', " . floatval($ue['qty']) . ",
                  " . floatval($ue['qty']) . ", 'exceptional_coverage', 'standby', {$cv['id']}, '2026-07', 4)");
    $ledRows++;
}
$run("INSERT INTO capacity_consumption_ledger (company_id, unit_record_id, unit_record_version, effect_target_type,
        effect_target_ref, measure_code, qty, effect_type, period, reverses_led_id, created_by)
      SELECT company_id, unit_record_id, 2, effect_target_type, effect_target_ref, measure_code, qty, 'reversal', period, led_id, 4
      FROM capacity_consumption_ledger WHERE led_id = $firstLed"); // الكميةُ موجبةٌ دائمًا (ck_led_qty_positive) — العكسُ من النوع والمرجع
$ledRows++;
echo "⑦ دفترُ القدرات: +$ledRows\n";

echo "\n✔ اكتملت البذرةُ كلُّها\n";
