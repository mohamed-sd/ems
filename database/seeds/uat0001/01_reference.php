<?php
/**
 * UAT-0001 · بذرة ① — البياناتُ المرجعية: شجرةُ الحسابات وتصنيفاتُ المعدات
 * وفئاتُ الإهلاك والعملاتُ ووحداتُ القياس وأسبابُ التوقف.
 *
 * المصادر: د01 · د02 · أ01 · أ02 · القوائم المرجعية.
 */
require __DIR__ . '/_lib.php';

$db    = uat_db();
$actor = uat_actor();
$CO    = UAT_COMPANY;

// ── ① شجرةُ الحسابات · د01 (108 حسابًا في ثلاثة مستويات) ─────────────────────
// الشجرةُ القائمة (16 حسابًا برموزٍ أربعية 1000/1100) تبقى كما هي — لا تُمسّ لأن
// عليها 18 سطرَ قيدٍ قديمة؛ والشجرةُ المعتمدة تُضاف برموزها (1 · 11 · 1101).

$typeOf = function ($code) {
    return ['1' => 'asset', '2' => 'liability', '3' => 'equity', '4' => 'revenue', '5' => 'expense'][substr((string) $code, 0, 1)] ?? 'expense';
};

$accIds = [];
$rows   = uat_json('د__د01_شجرة_الحسابات');
usort($rows, fn($a, $b) => strlen($a['كود الحساب']) <=> strlen($b['كود الحساب']));   // الآباءُ أولًا

foreach ($rows as $r) {
    $code = trim($r['كود الحساب']);
    if ($code === '') continue;
    $parent = trim($r['الحساب الأب']);
    $id = uat_upsert('fin_chart_of_accounts',
        ['company_id' => $CO, 'code' => $code],
        [
            'name'         => mb_substr($r['اسم الحساب'], 0, 160),
            'account_type' => $typeOf($code),
            'parent_id'    => $parent !== '' ? ($accIds[$parent] ?? null) : null,
            'is_postable'  => (mb_strpos($r['نوع الحساب'], 'تجميعي') !== false) ? 0 : 1,
            'active'       => 1,
            'created_by'   => $actor,
        ]);
    $accIds[$code] = $id;
    uat_log('fin_chart_of_accounts', 'حساب');
}

// ── ② الأبعادُ التحليلية · د02 → مراكزُ تكلفةٍ من نوع «بُعد» ──────────────────
// تُضاف الأبعادُ الأربعة عشر بوصفها مراكزَ تكلفةٍ مرجعيةً تحت جذرٍ خاص.
$dimRoot = uat_upsert('fin_cost_centers',
    ['company_id' => $CO, 'code' => 'DIM-ROOT'],
    ['name' => 'الأبعاد التحليلية', 'center_type' => 'cost', 'owner_module' => 'general', 'level' => 0, 'created_by' => $actor]);

foreach (uat_json('د__د02_الأبعاد_التحليلية') as $r) {
    $code = trim($r['رمز البُعد'] ?? '');
    if ($code === '') continue;
    uat_upsert('fin_cost_centers',
        ['company_id' => $CO, 'code' => 'DIM-' . $code],
        [
            'name'         => mb_substr($r['اسم البُعد'] ?? $code, 0, 160),
            'center_type'  => 'cost',
            'parent_id'    => $dimRoot,
            'owner_module' => 'general',
            'level'        => 1,
            'created_by'   => $actor,
        ]);
    uat_log('fin_cost_centers', 'بُعد');
}

// ── ③ تصنيفاتُ المعدات · أ01 (24 رمزًا) ──────────────────────────────────────
// العمود `form` رقمٌ متسلسلٌ في البيانات القائمة — يُواصَل ولا يُكسر.
$maxForm = (int) ($db->query("SELECT COALESCE(MAX(CAST(form AS UNSIGNED)),0) m FROM equipments_types")->fetch_assoc()['m']);
foreach (uat_json('أ__أ01_دليل_رموز_التصنيف') as $r) {
    $ar = trim($r['النوع بالعربية'] ?? '');
    if ($ar === '') continue;
    $exists = uat_one("SELECT id FROM equipments_types WHERE type = ? LIMIT 1", [$ar]);
    if ($exists) continue;
    $maxForm++;
    uat_insert('equipments_types', ['form' => (string) $maxForm, 'type' => mb_substr($ar, 0, 100), 'status' => 'active']);
    uat_log('equipments_types', 'تصنيف');
}

// ── ④ فئاتُ الإهلاك · أ02 (11 فئةً بوحدات الإنتاج) ───────────────────────────
foreach (uat_json('أ__أ02_فئات_الإهلاك') as $r) {
    $code = trim($r['رمز الفئة'] ?? '');
    if ($code === '' || $code === 'رمز الفئة') continue;
    $life = uat_num($r['العمر الإنتاجي'] ?? '', 0);
    uat_upsert('fleet_depreciation_profile',
        ['company_id' => $CO, 'code' => $code],
        [
            'asset_category' => mb_substr($r['وصف الفئة'] ?? $code, 0, 120),
            'method'         => (stripos($r['طريقة الإهلاك'] ?? '', 'UOP') !== false) ? 'uop' : 'sl',
            'useful_life'    => $life,
            'salvage_pct'    => uat_num($r['نسبة القيمة المتبقية'] ?? '', 0),
            'notes'          => trim(($r['المصدر المعياري'] ?? '') . ' · ' . ($r['الملاحظات'] ?? '')),
            'state'          => 'approved',
            'approved_by'    => $actor,
            'approved_at'    => date('Y-m-d H:i:s'),
            'is_deleted'     => 0,
            'created_by'     => $actor,
        ]);
    uat_log('fleet_depreciation_profile', 'فئة');
}

// ── ⑤ العملاتُ ووحداتُ القياس · القوائم المرجعية ─────────────────────────────
$curNames = [
    'USD' => 'الدولار الأمريكي', 'SDG' => 'الجنيه السوداني', 'AED' => 'الدرهم الإماراتي',
    'QAR' => 'الريال القطري', 'SAR' => 'الريال السعودي', 'EGP' => 'الجنيه المصري', 'EUR' => 'اليورو',
];
$order = 10;
foreach (uat_json('ر__القوائم_المرجعية') as $r) {
    $c = strtoupper(trim($r['العملات'] ?? ''));
    if ($c === '' || !preg_match('/^[A-Z]{3}$/', $c)) continue;
    $order++;
    uat_upsert('fin_currencies',
        ['company_id' => $CO, 'code' => $c],
        ['name_ar' => $curNames[$c] ?? $c, 'decimals' => 2, 'is_base' => 0, 'active' => 1, 'sort_order' => $order, 'created_by' => $actor]);
    uat_log('fin_currencies', 'عملة');
}

$uomCat = ['ساعة تشغيل' => 'زمن', 'طن' => 'وزن', 'متر طولي' => 'طول', 'نقلة' => 'عدد', 'متر مكعب' => 'حجم', 'وردية' => 'زمن', 'يوم' => 'زمن'];
$maxUom = (int) preg_replace('/\D/', '', (string) ($db->query("SELECT COALESCE(MAX(uom_code),'UOM-1000') m FROM units_of_measure WHERE company_id=$CO")->fetch_assoc()['m']));
foreach (uat_json('ر__القوائم_المرجعية') as $r) {
    $u = trim($r['وحدة الاستحقاق'] ?? '');
    if ($u === '') continue;
    if (uat_one("SELECT id FROM units_of_measure WHERE company_id=? AND name=? LIMIT 1", [$CO, $u])) continue;
    $maxUom++;
    uat_insert('units_of_measure', [
        'company_id' => $CO, 'uom_code' => 'UOM-' . $maxUom, 'name' => $u,
        'category' => $uomCat[$u] ?? 'عدد', 'factor' => 1, 'notes' => 'من القوائم المرجعية المعتمدة',
        'status' => 1, 'created_by' => $actor,
    ]);
    uat_log('units_of_measure', 'وحدة');
}

// ── ⑥ أسبابُ التوقف · أعمدةُ التعطل في ب07 ───────────────────────────────────
$stops = [
    'unexecuted_loss'      => ['فاقد غير منفَّذ', 'access_road'],
    'maintenance_downtime' => ['تعطل صيانة', 'equipment_readiness'],
    'hr_delay'             => ['تأخير موارد بشرية', 'operators'],
    'reliability_downtime' => ['تعطل اعتمادية', 'equipment_readiness'],
    'holidays_downtime'    => ['إجازات وعطل رسمية', 'force_majeure'],
    'marketing_downtime'   => ['تعطل تسويق', 'loading_equipment'],
    'fuel_shortage'        => ['نقص وقود', 'fuel'],
    'client_access'        => ['تعذّر جبهة العمل', 'access_road'],
    'permits_safety'       => ['إيقاف لأسباب سلامة', 'permits_safety'],
    'other'                => ['أخرى', null],
];
foreach ($stops as $code => [$nameAr, $obl]) {
    $ex = uat_one("SELECT code FROM stop_reason_codes WHERE code=? LIMIT 1", [$code]);
    if ($ex) continue;
    uat_insert('stop_reason_codes', ['code' => $code, 'name_ar' => $nameAr, 'obligation_type' => $obl, 'active' => 1]);
    uat_log('stop_reason_codes', 'سبب');
}

uat_print_report('البذرة ① · البيانات المرجعية');
printf("   الحسابات في القاعدة الآن: %d · التصنيفات: %d · الوحدات: %d · العملات: %d · فئات الإهلاك: %d\n",
    uat_count('fin_chart_of_accounts', 'company_id=' . $CO),
    uat_count('equipments_types'),
    uat_count('units_of_measure', 'company_id=' . $CO),
    uat_count('fin_currencies', 'company_id=' . $CO),
    uat_count('fleet_depreciation_profile', 'company_id=' . $CO));
