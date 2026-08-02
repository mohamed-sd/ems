<?php
/**
 * UAT-0001 · بذرة ② — العملاءُ والمشاريعُ والمواقعُ والعقودُ والتزاماتُها.
 *
 * المصادر: ب01 (79 عميلًا) · ب02 (79 مشروعًا) · ب03 (102 عقدًا) · ب04 (134 التزامًا).
 * المشتقُّ اجتهادًا (لأن ب08/ب09/ب10 قوالبُ فارغة): نطاقُ التنفيذ · الجدولُ الشهري
 * · خطةُ الدفع · خطُّ الأساس — كلُّها مبنيةٌ على أرقام العقد الحقيقية لا مخترعة.
 */
require __DIR__ . '/_lib.php';

$db    = uat_db();
$actor = uat_actor();
$CO    = UAT_COMPANY;
$today = date('Y-m-d');

$MODEL_UNIT = ['إيجار بالساعة' => 'hour', 'مقاولات النقل' => 'ton', 'تخريم بالمتر' => 'meter', 'النقل بالنقلة' => 'trip'];

// ── ① العملاء ────────────────────────────────────────────────────────────────
$mapClient = [];
foreach (uat_json('ب__ب01_العملاء') as $r) {
    $n = uat_int($r['رقم العميل'] ?? '');
    if (!$n) continue;
    $name = trim($r['الاسم القانوني المعتمد'] ?? '') ?: trim($r['الاسم في التايم شيت'] ?? '');
    if ($name === '') continue;
    $last = uat_date($r['آخر تعامل'] ?? '');
    $id = uat_upsert('clients',
        ['company_id' => $CO, 'client_code' => sprintf('C%04d', $n)],
        [
            'client_name'     => mb_substr($name, 0, 255),
            'entity_type'     => trim($r['نوع العميل'] ?? '') ?: 'محلي',
            'sector_category' => 'تعدين',
            'status'          => ($last && $last >= date('Y-m-d', strtotime('-12 months'))) ? 'نشط' : 'متوقف',
            'created_by'      => $actor,
            'is_deleted'      => 0,
        ]);
    $mapClient[$n] = $id;
    uat_log('clients', 'عميل');
}

// ── ② المشاريع والمواقع ──────────────────────────────────────────────────────
$mapProject = [];
$mapSite    = [];
foreach (uat_json('ب__ب02_المشاريع_والمواقع') as $r) {
    $pn = uat_int($r['رقم المشروع'] ?? '');
    $cn = uat_int($r['رقم العميل'] ?? '');
    if (!$pn || !isset($mapClient[$cn])) continue;
    $pname = trim($r['اسم المشروع'] ?? '') ?: ('مشروع ' . $cn);
    $state = trim($r['الولاية'] ?? '') ?: 'غير محدد';
    $mine  = trim($r['اسم المنجم'] ?? '');
    $pid = uat_upsert('project',
        ['company_id' => $CO, 'project_code' => sprintf('PR-%04d', $pn)],
        [
            'client_id'  => $mapClient[$cn],
            'name'       => mb_substr($pname, 0, 150),
            'client'     => mb_substr(trim($r['اسم العميل'] ?? ''), 0, 150),
            'location'   => mb_substr(trim($r['المنطقة أو البلوك'] ?? '') ?: $state, 0, 200),
            'mine_code'  => $mine !== '' ? mb_substr($mine, 0, 100) : null,
            'category'   => 'تعدين',
            'sub_sector' => trim($r['نوع الموقع'] ?? '') ?: 'موقع تشغيل',
            'state'      => mb_substr($state, 0, 100),
            'total'      => '0',
            'status'     => 1,
            'created_by' => $actor,
            'is_deleted' => 0,
        ]);
    $mapProject[$pn] = $pid;
    uat_log('project', 'مشروع');

    $sid = uat_upsert('sites',
        ['company_id' => $CO, 'project_id' => $pid, 'name' => mb_substr($pname, 0, 190)],
        [
            'site_kind'     => $mine !== '' ? 'mine' : 'site',
            'location_text' => mb_substr($state, 0, 255),
            'status'        => 1,
            'is_default'    => 1,
            'is_deleted'    => 0,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    $mapSite[$pn] = [$sid];
    uat_log('sites', 'موقع');
}

// ── ②-ب · المشروعُ متعددُ المواقع — شرطُ `UAT-01 §3` ─────────────────────────
// يُختار المشروعُ الأكثرُ عقودًا فتُضاف له جبهتان إضافيتان (ثلاثةُ مواقعَ إجمالًا).
$byProject = [];
foreach (uat_json('ب__ب03_عقود_العملاء') as $r) {
    $pn = uat_int($r['رقم المشروع'] ?? '');
    if ($pn) $byProject[$pn] = ($byProject[$pn] ?? 0) + 1;
}
arsort($byProject);
$multiSiteProject = (int) array_key_first($byProject);
if (isset($mapProject[$multiSiteProject])) {
    $base = uat_one("SELECT name FROM project WHERE id=?", [$mapProject[$multiSiteProject]])['name'];
    foreach (['الجبهة الشمالية', 'الجبهة الجنوبية'] as $i => $sub) {
        $sid = uat_upsert('sites',
            ['company_id' => $CO, 'project_id' => $mapProject[$multiSiteProject], 'name' => mb_substr($base . ' — ' . $sub, 0, 190)],
            [
                'site_kind' => 'mine', 'location_text' => $sub, 'status' => 1, 'is_default' => 0, 'is_deleted' => 0,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        $mapSite[$multiSiteProject][] = $sid;
        uat_log('sites', 'جبهة');
    }
}

// ── ③ العقود ─────────────────────────────────────────────────────────────────
/** سجلُّ المفاتيح الخارجية: contract_notes يحمل «UAT-KEY|<المفتاح>» فتُعاد البذرةُ بلا مضاعفة. */
function uat_contract_by_key($key)
{
    $r = uat_one("SELECT contract_id FROM contract_notes WHERE company_id=? AND note=? LIMIT 1", [UAT_COMPANY, 'UAT-KEY|' . $key]);
    return $r ? (int) $r['contract_id'] : null;
}

$mapContract = [];
foreach (uat_json('ب__ب03_عقود_العملاء') as $r) {
    $key = trim($r['مفتاح العقد'] ?? '');
    $pn  = uat_int($r['رقم المشروع'] ?? '');
    if ($key === '' || !isset($mapProject[$pn])) continue;

    if ($cid = uat_contract_by_key($key)) { $mapContract[$key] = $cid; continue; }

    $start = uat_date($r['بداية العقد الموقع'] ?? '') ?: uat_date($r['أول تشغيل فعلي'] ?? '');
    $end   = uat_date($r['نهاية العقد الموقع'] ?? '') ?: uat_date($r['آخر تشغيل فعلي'] ?? '');
    if (!$start) continue;
    $days   = $end ? max(1, (int) ((strtotime($end) - strtotime($start)) / 86400)) : 30;
    $months = max(1, (int) round($days / 30));

    $st = trim($r['حالة العقد'] ?? '');
    $status = (mb_strpos($st, 'نشط') !== false) ? 'قيد التنفيذ'
            : ((mb_strpos($st, 'منته') !== false) ? 'منتهٍ' : (($end && $end < $today) ? 'منتهٍ' : 'قيد التنفيذ'));

    $units = uat_int($r['إجمالي الحاويات المتعاقدة'] ?? '', 0);

    $cid = uat_insert('contracts', [
        'company_id'               => $CO,
        'project_id'               => $mapProject[$pn],
        'site_id'                  => $mapSite[$pn][0] ?? null,
        'contract_signing_date'    => $start,
        'actual_start'             => $start,
        'actual_end'               => $end,
        'contract_duration_days'   => $days,
        'contract_duration_months' => $months,
        'grace_period_days'        => 0,
        'total_contract_units'     => $units,
        'equip_total_contract_daily' => $units,
        'equip_shifts_contract'    => 2,
        'shift_contract'           => 2,
        'daily_work_hours'         => '10',
        'daily_operators'          => '2',
        'first_party'              => 'شركة إكوبيشن للاستثمار المحدودة',
        'second_party'             => mb_substr(trim($r['اسم العميل'] ?? ''), 0, 255),
        'price_currency_contract'  => 'دولار',
        'payment_time'             => '30 يومًا من تاريخ الفاتورة',
        'retention_pct'            => 5.00,
        'advance_recovery_pct'     => 20.00,
        'contract_status'          => $status,
        'readiness_state'          => 'مجتاز',
        'status'                   => 1,
        'is_deleted'               => 0,
    ]);
    uat_insert('contract_notes', [
        'company_id' => $CO, 'contract_id' => $cid,
        'note' => 'UAT-KEY|' . $key, 'user_id' => $actor, 'created_by' => $actor,
    ]);
    uat_insert('contract_notes', [
        'company_id' => $CO, 'contract_id' => $cid,
        'note' => sprintf('%s · نموذج %s · العقد الموقع رقم %s · المصدر: %s · منفَّذ: %s ساعة / %s طن / %s متر',
            UAT_TAG, $r['نموذج العمل'] ?? '', $r['رقم العقد الموقع'] ?? '—', $r['مصدر التواريخ'] ?? '—',
            $r['ساعات منفذة'] ?? '0', $r['أطنان منفذة'] ?? '0', $r['أمتار منفذة'] ?? '0'),
        'user_id' => $actor, 'created_by' => $actor,
    ]);
    $mapContract[$key] = $cid;
    uat_log('contracts', 'عقد');

    // نطاقُ التنفيذ — الموقعُ بُعدٌ تشغيليٌّ لا أبٌ تعاقديّ
    foreach ($mapSite[$pn] as $i => $sid) {
        uat_upsert('contract_operational_sites',
            ['company_id' => $CO, 'contract_id' => $cid, 'site_id' => $sid],
            [
                'scope_name' => mb_substr(uat_one("SELECT name FROM sites WHERE id=?", [$sid])['name'], 0, 190),
                'start_date' => $start, 'end_date' => $end,
                'state'        => $status === 'منتهٍ' ? 'closed' : 'active',
                // حارسُ القاعدة ck_cos_closed يمنع إقفالَ نطاقٍ بلا سبب — وهو محقّ
                'close_reason' => $status === 'منتهٍ' ? 'انتهاءُ مدة العقد' : null,
                'is_primary'   => $i === 0 ? 1 : 0,
                'is_deleted' => 0, 'created_by' => $actor,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        uat_log('contract_operational_sites', 'نطاق');
    }
}

// ── ④ الالتزاماتُ التعاقدية · ب04 ────────────────────────────────────────────
$seq = 0;
foreach (uat_json('ب__ب04_التغطية_التعاقدية') as $r) {
    $key = trim($r['مفتاح العقد'] ?? '');
    if (!isset($mapContract[$key])) continue;
    $cid   = $mapContract[$key];
    $type  = mb_substr(trim($r['نوع المعدة'] ?? '') ?: 'غير محدد', 0, 40);
    $unit  = $MODEL_UNIT[trim($r['نموذج العمل'] ?? '')] ?? 'hour';
    $prim  = uat_int($r['الوحدات المتعاقد عليها'] ?? '', 0);
    $stand = uat_int($r['الوحدات الاحتياطية'] ?? '', 0);
    $qtyM  = uat_num($r['الساعات الشهرية للوحدة'] ?? '', 0);
    $seq++;

    $code = sprintf('CMT-%05d', $seq);
    if (uat_one("SELECT id FROM contract_commitments WHERE company_id=? AND commitment_code=?", [$CO, $code])) continue;
    $exists = uat_one("SELECT id FROM contract_commitments WHERE company_id=? AND contract_ref=? AND equipment_type_code=? AND is_deleted=0", [$CO, $cid, $type]);
    if ($exists) continue;

    uat_insert('contract_commitments', [
        'company_id'                 => $CO,
        'commitment_code'            => $code,
        'party_scope'                => 'client',
        'contract_ref'               => $cid,
        'commitment_type'            => 'equipment_count',
        'equipment_type_code'        => $type,
        'primary_units_contracted'   => $prim,
        'standby_units_required'     => 0,
        'standby_units_allowed'      => $stand,
        'qty_per_primary_unit_month' => $qtyM,
        'measure_code'               => $unit === 'trip' ? 'trip' : $unit,
        'standby_compensation_type'  => 'readiness_allowance',
        'standby_hours_treatment'    => 'separate_line',
        'plan_state'                 => 'approved',
        'unit_type'                  => $unit,
        'qty'                        => $prim,
        'period'                     => 'contract',
        'obliged_party'              => 'company',
        'shortfall_rule'             => 'invoice_actual',
        'surplus_rule'               => 'same_price',
        'note'                       => mb_substr('تغطية: ' . ($r['حالة التغطية'] ?? '') . ' · فجوة ' . ($r['فجوة التغطية'] ?? '0'), 0, 160),
        'created_by'                 => $actor,
        'is_deleted'                 => 0,
    ]);
    uat_log('contract_commitments', 'التزام');
}

// ── الخرائط للبذور اللاحقة ───────────────────────────────────────────────────
file_put_contents(UAT_IMPORT_DIR . '/_map_clients.json',   json_encode($mapClient, JSON_UNESCAPED_UNICODE));
file_put_contents(UAT_IMPORT_DIR . '/_map_projects.json',  json_encode($mapProject, JSON_UNESCAPED_UNICODE));
file_put_contents(UAT_IMPORT_DIR . '/_map_sites.json',     json_encode($mapSite, JSON_UNESCAPED_UNICODE));
file_put_contents(UAT_IMPORT_DIR . '/_map_contracts.json', json_encode($mapContract, JSON_UNESCAPED_UNICODE));

uat_print_report('البذرة ② · العملاء والمشاريع والعقود');
printf("   العملاء: %d · المشاريع: %d · المواقع: %d · العقود: %d · النطاقات: %d · الالتزامات: %d\n",
    uat_count('clients', "company_id=$CO"), uat_count('project', "company_id=$CO"),
    uat_count('sites', "company_id=$CO"), uat_count('contracts', "company_id=$CO"),
    uat_count('contract_operational_sites', "company_id=$CO"), uat_count('contract_commitments', "company_id=$CO"));
printf("   المشروعُ متعددُ المواقع: #%d بثلاثة مواقع\n", $multiSiteProject);
