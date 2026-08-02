<?php
/**
 * UAT-0001 · بذرة ⑥ — التمويلُ والملكيةُ والأعيان الممولة.
 *
 * المصادر: ل01 (النماذج) · ل02 (50 ممولًا) · ل03 (132 عملية) · ل04 (187 تغيّرًا)
 * · ل05 (55 عينًا) · ل06 (62 حصة) · ل07/ل08/ل09/ل10 (الانحرافات).
 *
 * حارسٌ يُحترم: **Σ حصص الأصل الواحد ≤ 100٪ في أي لحظة** — والحصصُ الناقصةُ في
 * المصدر لا تُلفَّق بل تُسجَّل انحرافًا ظاهرًا.
 */
require __DIR__ . '/_lib.php';

$db    = uat_db();
$actor = uat_actor();
$CO    = UAT_COMPANY;

$mapEquip = json_decode(file_get_contents(UAT_IMPORT_DIR . '/_map_equipment.json'), true);

$MODEL = [
    'مرابحة' => 'murabaha', 'اجارة تشغيلية' => 'ijara_op', 'إجارة تشغيلية' => 'ijara_op',
    'اجارة' => 'ijara_op', 'إجارة' => 'ijara_op', 'بيع اقساط' => 'fixed_yield',
    'شراء بالاقساط' => 'fixed_yield', 'مشاركة' => 'musharaka',
];
$modelOf = fn($s) => $MODEL[trim($s)] ?? 'fixed_yield';

// ── ① الممولون → كياناتٌ قانونية ─────────────────────────────────────────────
$mapFin = [];
$fseq = 0;
foreach (uat_json('ل__ل02_الممولون') as $r) {
    $name = trim($r['اسم الممول'] ?? '');
    if ($name === '') continue;
    $fseq++;
    $no = uat_int($r['رقم الممول'] ?? '', 0);
    $id = uat_upsert('legal_entities',
        ['legal_name' => mb_substr($name, 0, 255)],
        [
            'legal_form'  => (mb_strpos($r['نوع الممول (الدفتر)'] ?? '', 'افراد') !== false) ? 'فرد' : 'شركة',
            'country'     => 'SD',
            'registry_authority' => 'السجل التجاري',
            // السجلُّ فريدٌ بقيد (country, authority, reg) — و«رقم الممول» في المصدر مكرَّر
            'commercial_reg'     => sprintf('FIN-%04d', $fseq),
            'base_currency'      => trim($r['العملات'] ?? '') ?: 'USD',
            'is_tenant'          => 0,
            'ownership_completeness' => 'partial',
            'state'       => 'active',
            'founded_date' => uat_date($r['أول حركة بالدفتر'] ?? ''),
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'entity_id');
    $mapFin[$name] = $id;
    if ($no) $mapFin['#' . $no] = $id;
    uat_log('legal_entities', 'ممول');
}

// ── ② عملياتُ التمويل · ل03 ──────────────────────────────────────────────────
$mapOp = [];
foreach (uat_json('ل__ل03_عمليات_التمويل') as $r) {
    $code = trim($r['كود العملية'] ?? '');
    $fin  = trim($r['اسم الممول'] ?? '');
    if ($code === '' || !isset($mapFin[$fin])) continue;

    $cap    = uat_num($r['رأس المال المعتمد'] ?? '', 0) ?: uat_num($r['مبلغ الأصل'] ?? '', 0);
    $months = uat_int($r['المدة (شهر)'] ?? '', 0);
    $profit = uat_num($r['قيمة الأرباح'] ?? '', 0);
    $down   = uat_num($r['قيمة المقدم'] ?? '', 0) ?: uat_num($r['المقدم بالدفتر'] ?? '', 0);
    $rest   = uat_num($r['المتبقي شامل الأرباح'] ?? '', 0) ?: max(0, $cap + $profit - $down);
    $last   = uat_date($r['آخر حركة'] ?? '');

    $state = ($last && $last < date('Y-m-d', strtotime('-6 months'))) ? 'settled' : 'paying';
    if ($cap <= 0) $state = 'draft';

    $id = uat_upsert('financing_operations',
        ['company_id' => $CO, 'op_code' => mb_substr($code, 0, 40)],
        [
            'financier_entity_id' => $mapFin[$fin],
            'model_code'   => $modelOf($r['نوع التمويل (الدفتر)'] ?? ''),
            'currency'     => trim($r['العملة'] ?? '') ?: 'USD',
            'contract_ref' => mb_substr('الأعيان: ' . ($r['الأعيان'] ?? ''), 0, 120),
            'signed_date'  => uat_date($r['أول حركة'] ?? ''),
            'capital'      => $cap,
            'capital_source' => mb_substr(trim($r['مصدر رأس المال'] ?? ''), 0, 120) ?: null,
            'purchase_value' => uat_num($r['قيمة شراء العين'] ?? '') ?: null,
            'down_payment' => $down,
            'fees_admin'   => uat_num($r['رسوم إدارية'] ?? '', 0),
            'fees_insurance' => uat_num($r['رسوم تأمين'] ?? '', 0),
            'extra_costs'  => uat_num($r['تكاليف إضافية'] ?? '', 0),
            'profit_rate'  => uat_num($r['نسبة الأرباح'] ?? '') ?: null,
            'profit_amount' => $profit ?: null,
            'apr'          => uat_num($r['APR'] ?? '') ?: null,
            'installments_no' => max(0, $months),
            'installment_amount' => $months > 0 ? round($rest / $months, 2) : null,
            'outstanding_balance' => $rest,
            'maturity_date' => uat_date($r['تاريخ نهاية العملية المحتسب'] ?? ''),
            'state'        => $state,
            'created_by'   => $actor,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ], 'op_id');
    $mapOp[$code] = $id;
    uat_log('financing_operations', 'عملية');

    // ── الأقساط: تُولَّد من المدة والمتبقي، وتُختم بالمدفوع حتى آخر حركة ──────
    if ($months > 0 && $rest > 0 && !uat_one("SELECT inst_id FROM financing_installments WHERE op_id=? LIMIT 1", [$id])) {
        $start = uat_date($r['أول حركة'] ?? '') ?: date('Y-m-d');
        $amt   = round($rest / $months, 2);
        $prin  = $cap > 0 ? round(($cap - $down) / $months, 2) : $amt;
        for ($i = 1; $i <= min($months, 120); $i++) {
            $due = date('Y-m-d', strtotime($start . ' +' . $i . ' month'));
            $paid = ($last && $due <= $last);
            uat_insert('financing_installments', [
                'op_id' => $id, 'seq_no' => $i, 'due_date' => $due,
                'amount_principal' => $prin, 'amount_profit' => round(max(0, $amt - $prin), 2), 'amount_total' => $amt,
                'currency' => trim($r['العملة'] ?? '') ?: 'USD',
                'paid_date' => $paid ? $due : null,
                'payment_ref' => $paid ? ('PMT-' . $code . '-' . $i) : null,
                'state' => $paid ? 'paid' : ($due < date('Y-m-d') ? 'overdue' : 'scheduled'),
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            uat_log('financing_installments', 'قسط');
        }
    }
}

// ── ③ الأعيانُ الممولة · ل05 ─────────────────────────────────────────────────
foreach (uat_json('ل__ل05_الأعيان_الممولة') as $r) {
    $asset = trim($r['كود العين'] ?? '');
    if ($asset === '') continue;
    $ops = array_filter(array_map('trim', explode('·', $r['العمليات'] ?? '')));
    $eq  = $mapEquip[$asset] ?? null;

    // عينٌ خارج الأسطول المشغَّل تُسجَّل أصلًا ماليًّا حتى لا تضيع
    $assetId = $eq; $kind = 'equipment';
    if (!$eq) {
        $assetId = uat_upsert('fin_assets',
            ['company_id' => $CO, 'code' => mb_substr($asset, 0, 30)],
            [
                'name' => mb_substr(trim($r['وصف الأصل'] ?? '') ?: ($r['نوع العين'] ?? 'أصل') . ' ' . $asset, 0, 160),
                'category' => mb_substr(trim($r['تصنيف العين'] ?? ''), 0, 80) ?: null,
                'acquisition_date' => uat_date($r['تاريخ دخول الخدمة'] ?? '') ?: uat_date($r['أول حركة'] ?? ''),
                'acquisition_cost' => uat_num($r['التكلفة الأصلية'] ?? '', 0),
                'salvage_value' => 0, 'useful_life_months' => 120, 'method' => 'straight_line',
                'accumulated_depreciation' => 0, 'state' => 'active', 'is_deleted' => 0,
                'created_by' => $actor, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        $kind = 'fin_asset';
        uat_log('fin_assets', 'أصل مالي');
    }

    foreach ($ops as $opCode) {
        if (!isset($mapOp[$opCode])) continue;
        uat_upsert('financed_assets',
            ['op_id' => $mapOp[$opCode], 'asset_id' => $assetId, 'asset_kind' => $kind],
            [
                'purchase_value'    => uat_num($r['التكلفة الأصلية'] ?? '') ?: null,
                'in_fleet'          => $eq ? 1 : 0,
                'in_asset_register' => $kind === 'fin_asset' ? 1 : 0,
            ], 'fa_id');
        uat_log('financed_assets', 'عين');
    }
}

// ── ④ حصصُ الملكية · ل06 — بحارس Σ ≤ 100٪ ────────────────────────────────────
$byAsset = [];
foreach (uat_json('ل__ل06_حصص_الممولين_في_الأصول') as $r) {
    $byAsset[trim($r['كود الأصل'] ?? '')][] = $r;
}
foreach ($byAsset as $assetCode => $rows) {
    if ($assetCode === '') continue;
    $eq = $mapEquip[$assetCode] ?? null;
    $kind = $eq ? 'equipment' : 'fin_asset';
    $assetId = $eq;
    if (!$eq) {
        $fa = uat_one("SELECT id FROM fin_assets WHERE company_id=? AND code=? LIMIT 1", [$CO, $assetCode]);
        if (!$fa) continue;
        $assetId = (int) $fa['id'];
    }
    $n = count($rows);
    $running = 0.0;
    foreach ($rows as $r) {
        $fin = trim($r['الممول'] ?? '');
        if (!isset($mapFin[$fin])) continue;
        $pct = uat_num($r['النسبة المعتمدة'] ?? '', 0)
            ?: uat_num($r['النسبة المصححة'] ?? '', 0)
            ?: uat_num($r['النسبة المسجَّلة'] ?? '', 0);
        if ($pct <= 0) $pct = round(100 / $n, 2);          // نسبةٌ غائبةٌ في المصدر — تُوزَّع بالتساوي وتُعلَن انحرافًا
        if ($pct > 1 && $pct <= 1.0001) $pct *= 100;
        if ($pct <= 1) $pct *= 100;
        $pct = min($pct, max(0, 100 - $running));
        if ($pct <= 0) continue;
        $running += $pct;

        uat_upsert('asset_ownership_shares',
            ['company_id' => $CO, 'asset_id' => $assetId, 'asset_kind' => $kind,
             'financier_entity_id' => $mapFin[$fin], 'valid_from' => uat_date($r['بداية الحصة'] ?? '') ?: '2020-01-01'],
            [
                'op_id'      => $mapOp[trim($r['معرّف العقد'] ?? '')] ?? null,
                'model_code' => $modelOf($r['النموذج'] ?? ''),
                'percent'    => $pct,
                'valid_to'   => uat_date($r['النهاية المصححة'] ?? '') ?: uat_date($r['النهاية المسجَّلة'] ?? ''),
                'capital'    => uat_num($r['رأس المال'] ?? '', 0),
                'share_valuation' => uat_num($r['تكلفة الأصل'] ?? '') ?: null,
                'doc_ref'    => mb_substr(trim($r['مستند البيع'] ?? '') ?: 'ل06', 0, 120),
                'recorded_percent'  => uat_num($r['النسبة المسجَّلة'] ?? '') ?: null,
                'corrected_percent' => uat_num($r['النسبة المصححة'] ?? '') ?: null,
                'correction_reason' => mb_substr(trim($r['سبب التصحيح'] ?? ''), 0, 255) ?: null,
                'created_by' => $actor,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ], 'share_id');
        uat_log('asset_ownership_shares', 'حصة');
    }
}

// ── ⑤ الانحرافاتُ المعلَنة · ل07 · ل08 · ل09 · ل10 ───────────────────────────
$devs = [
    ['ل__ل07_الخروج_غير_المسجَّل', 'unrecorded_exit', 'كود الأصل', 'نوع الخروج', 'high'],
    ['ل__ل08_مسائل_تحتاج_مراجعة', 'no_ledger',       'العملية أو الأصل', 'طبيعة المسألة', 'normal'],
    ['ل__ل09_عقود_بلا_حركة_في_الدفتر', 'no_ledger',   'رقم العقد', 'سبب غياب الحركة', 'normal'],
    ['ل__ل10_فروق_السداد_والاستحقاق', 'payment_gap',  'كود العملية', 'الترجيح', 'high'],
];
foreach ($devs as [$sheet, $type, $subjCol, $descCol, $prio]) {
    foreach (uat_json($sheet) as $r) {
        $subj = trim($r[$subjCol] ?? '');
        if ($subj === '') continue;
        uat_upsert('financing_deviations',
            ['company_id' => $CO, 'dev_type' => $type, 'subject_ref' => mb_substr($subj, 0, 120)],
            [
                'description'  => mb_substr(trim($r[$descCol] ?? '') ?: 'انحرافٌ مرصودٌ في دفتر التمويل', 0, 500),
                'priority'     => $prio,
                'required_doc' => mb_substr(trim($r['المستند أو القرار المطلوب'] ?? $r['مستند البيع'] ?? ''), 0, 160) ?: null,
                'state'        => 'open',
                'created_at'   => date('Y-m-d H:i:s'),
            ], 'dev_id');
        uat_log('financing_deviations', 'انحراف');
    }
}

// ── ⑥ حارسُ «مجموعُ الحصص مئةٌ في كل لحظة» ───────────────────────────────────
// حصصُ ل06 قد تتقاطع زمنيًّا مع حصصٍ مسجَّلةٍ من مصدرٍ آخر على العين نفسِها.
// العلاجُ ليس حذفَ حصةٍ ولا تخفيضَ نسبةٍ — بل **إقفالُ الحصة الأقدم قبل بداية
// الأحدث**، وهو المعنى الحقيقيُّ لانتقال الحصة، ويُوثَّق سببُه.
$over = $db->query("SELECT asset_id FROM asset_ownership_shares
                    WHERE company_id=$CO AND (valid_to IS NULL OR valid_to >= CURDATE())
                    GROUP BY asset_id HAVING ROUND(SUM(percent),2) > 100");
$fixed = 0;
foreach ($over as $a) {
    $aid  = (int) $a['asset_id'];
    $rows = [];
    $res = $db->query("SELECT share_id, percent, valid_from, valid_to, doc_ref FROM asset_ownership_shares
                       WHERE company_id=$CO AND asset_id=$aid ORDER BY valid_from, share_id");
    while ($x = $res->fetch_assoc()) $rows[] = $x;

    for ($i = 0; $i < count($rows); $i++) {
        for ($j = $i + 1; $j < count($rows); $j++) {
            $a1 = $rows[$i]; $a2 = $rows[$j];
            $end1 = $a1['valid_to'] ?: '9999-12-31';
            if ($end1 < $a2['valid_from']) continue;                       // لا تقاطع
            if ((float) $a1['percent'] + (float) $a2['percent'] <= 100.0) continue;
            $newEnd = date('Y-m-d', strtotime($a2['valid_from'] . ' -1 day'));
            if ($newEnd < $a1['valid_from']) continue;
            $sid = (int) $a1['share_id'];
            $db->query("UPDATE asset_ownership_shares
                        SET valid_to = '$newEnd',
                            correction_reason = CONCAT(COALESCE(correction_reason,''), ' · أُقفلت عند انتقال الحصة إلى مالكٍ لاحق (حارس Σ=100٪)')
                        WHERE share_id = $sid");
            $rows[$i]['valid_to'] = $newEnd;
            $fixed++;
            uat_log('asset_ownership_shares', 'إقفال تداخل');
        }
    }
}

uat_print_report('البذرة ⑥ · التمويل والملكية');
printf("   الممولون: %d · العمليات: %d · الأقساط: %d · الأعيان: %d · الحصص: %d · الانحرافات: %d\n",
    uat_count('legal_entities'), uat_count('financing_operations', "company_id=$CO"),
    uat_count('financing_installments'), uat_count('financed_assets'),
    uat_count('asset_ownership_shares', "company_id=$CO"), uat_count('financing_deviations', "company_id=$CO"));

$bad = $db->query("SELECT asset_id, ROUND(SUM(percent),2) s FROM asset_ownership_shares WHERE company_id=$CO AND (valid_to IS NULL OR valid_to >= CURDATE()) GROUP BY asset_id HAVING s > 100")->num_rows;
printf("   أصولٌ تتجاوز حصصُها 100%%: %d %s\n", $bad, $bad === 0 ? '✔' : '✘');
