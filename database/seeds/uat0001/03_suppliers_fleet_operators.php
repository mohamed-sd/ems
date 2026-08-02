<?php
/**
 * UAT-0001 · بذرة ③ — الموردون وأسطولُهم والمشغلون.
 *
 * المصادر: م01 · م06 · ف04 · ف07 · أ04 · أ14 · ش01 · ش02.
 * قاعدةٌ حاكمة: **إكوبيشن موردٌ داخليٌّ لنفسها** (رقم المورد 1) — فالمعداتُ المملوكة
 * تُنسب إليه، والخارجيةُ لملّاكها، ولا تُخلط الملكيةُ بالتشغيل.
 */
require __DIR__ . '/_lib.php';

$db    = uat_db();
$actor = uat_actor();
$CO    = UAT_COMPANY;

$SUP_TYPE = ['فرد' => 'فرد', 'شركة' => 'شركة', 'وسيط' => 'وسيط', 'مالك' => 'مالك'];

// ── ① الموردون · م01 (55) ∪ ف04 (28 مالكًا) ──────────────────────────────────
$banks = [];
foreach (uat_json('م__م06_حسابات_الموردين_البنكية') as $r) {
    $n = trim($r['الجهة المستفيدة'] ?? '');
    if ($n !== '') $banks[$n] = $r;
}

$mapSupName = [];   // اسم المورد => id
$mapSupNo   = [];   // رقمه في التايم شيت => id
$sseq       = 0;

$putSupplier = function (array $f) use (&$mapSupName, &$mapSupNo, &$sseq, $banks, $actor, $CO) {
    $name = trim($f['name'] ?? '');
    if ($name === '' || $name === 'nan' || $name === 'غير محدد') return null;
    if (isset($mapSupName[$name])) {
        if (!empty($f['no'])) $mapSupNo[(int) $f['no']] = $mapSupName[$name];
        return $mapSupName[$name];
    }
    $sseq++;
    $bank = $banks[$name] ?? null;
    $id = uat_upsert('suppliers',
        ['company_id' => $CO, 'name' => mb_substr($name, 0, 100)],
        [
            'supplier_code'    => trim($f['code'] ?? '') ?: sprintf('SUP-%04d', $sseq),
            'supplier_type'    => $f['type'] ?? 'شركة',
            'dealing_nature'   => mb_substr($f['nature'] ?? '', 0, 255),
            'equipment_types'  => $f['eqtypes'] ?? null,
            'commercial_registration' => mb_substr($f['cr'] ?? '', 0, 100) ?: null,
            'bank_name'        => $bank ? mb_substr($bank['اسم البنك'] ?? '', 0, 150) : null,
            'bank_account_no'  => $bank ? mb_substr($bank['رقم الحساب'] ?? '', 0, 60) : null,
            'contact_person_name'  => mb_substr($f['contact'] ?? '', 0, 255) ?: null,
            'contact_person_phone' => mb_substr($f['contact_phone'] ?? '', 0, 50) ?: null,
            'full_address'     => trim(($f['country'] ?? '') . ' · ' . ($f['city'] ?? '') . ' · ' . ($f['region'] ?? ''), ' ·') ?: null,
            'financial_registration_status' => ($f['type'] ?? '') === 'فرد' ? 'غير مسجل' : 'مسجل رسميا',
            'phone'            => mb_substr(($f['phone'] ?? '') ?: 'غير متوفر', 0, 15),
            'status'           => 1,
            'is_deleted'       => 0,
        ]);
    $mapSupName[$name] = $id;
    if (!empty($f['no'])) $mapSupNo[(int) $f['no']] = $id;
    uat_log('suppliers', 'مورد');
    return $id;
};

foreach (uat_json('م__م01_سجل_الموردين') as $r) {
    $putSupplier([
        'name'    => $r['اسم المورد'] ?? '',
        'code'    => $r['كود المورد'] ?? '',
        'type'    => $SUP_TYPE[trim($r['نوع المورد'] ?? '')] ?? 'شركة',
        'nature'  => $r['طبيعة التعاقد'] ?? '',
        'eqtypes' => $r['أنواع المعدات'] ?? '',
        'cr'      => $r['رقم السجل التجاري'] ?? '',
        'contact' => $r['الشخص المفوض'] ?? '',
        'contact_phone' => $r['هاتف المفوض'] ?? '',
        'phone'   => $r['رقم التواصل'] ?? '',
        'country' => $r['البلد'] ?? '', 'city' => $r['المدينة'] ?? '', 'region' => $r['المنطقة'] ?? '',
        'no'      => uat_int($r['رقمه في التايم شيت'] ?? ''),
    ]);
}
foreach (uat_json('ف__ف04_ملاك_الأسطول') as $r) {
    $putSupplier([
        'name'    => $r['اسم المالك'] ?? '',
        'type'    => (trim($r['نوع المورد'] ?? '') === 'EQUIPATION') ? 'شركة' : 'مالك',
        'nature'  => trim($r['التصنيف'] ?? ''),
        'eqtypes' => $r['تركيبة الأسطول'] ?? '',
        'cr'      => $r['رقم السجل التجاري'] ?? '',
        'contact' => $r['جهة الاتصال'] ?? '',
        'no'      => uat_int($r['رقم المورد'] ?? ''),
    ]);
}

// ── ② الأسطول · ف07 (205 معدة) مثرًى بـأ04 وأ14 ──────────────────────────────
$typeIds = [];
foreach ($db->query("SELECT id,type FROM equipments_types") as $t) $typeIds[$t['type']] = (int) $t['id'];
$maxForm = (int) $db->query("SELECT COALESCE(MAX(CAST(form AS UNSIGNED)),0) m FROM equipments_types")->fetch_assoc()['m'];
$typeId = function ($ar) use (&$typeIds, &$maxForm) {
    $ar = trim($ar) ?: 'غير محدد';
    if (isset($typeIds[$ar])) return $typeIds[$ar];
    $maxForm++;
    $id = uat_insert('equipments_types', ['form' => (string) $maxForm, 'type' => mb_substr($ar, 0, 100), 'status' => 'active']);
    uat_log('equipments_types', 'تصنيف');
    return $typeIds[$ar] = $id;
};

$assets = [];
foreach (uat_json('أ__أ04_سجل_الأصول_الرئيسي') as $r) {
    $c = trim($r['كود الأصل'] ?? '');
    if ($c !== '') $assets[$c] = $r;
}
$ident = [];
foreach (uat_json('أ__أ14_جدول_هوية_الأعيان_الموحد') as $r) {
    $c = trim($r['كود العين'] ?? '');
    if ($c !== '') $ident[$c] = $r;
}

$mapEquip = [];
foreach (uat_json('ف__ف07_الأسطول_بملاكه_الحقيقيين') as $r) {
    $code = trim($r['كود المعدة'] ?? '');
    if ($code === '') continue;
    $a  = $assets[$code] ?? [];
    $iv = $ident[$code] ?? [];
    $supNo   = uat_int($r['رقم المورد'] ?? '');
    $supName = trim($r['اسم المورد'] ?? '');
    $sid = $mapSupName[$supName] ?? ($supNo ? ($mapSupNo[$supNo] ?? null) : null);
    $isOwn = (trim($r['نوع المورد'] ?? '') === 'EQUIPATION');

    $id = uat_upsert('equipments',
        ['company_id' => $CO, 'code' => mb_substr($code, 0, 100)],
        [
            'suppliers'          => (string) ($sid ?? 0),
            'type'               => (string) $typeId($r['نوع المعدة'] ?? ''),
            'name'               => mb_substr(trim(($a['الوصف الموحد'] ?? '') ?: (($r['نوع المعدة'] ?? 'معدة') . ' ' . $code)), 0, 100),
            'serial_number'      => mb_substr(trim(($a['الرقم التسلسلي'] ?? '') ?: ($iv['الرقم التسلسلي (الشاسيه)'] ?? '') ?: ($r['رقم الشاسيه'] ?? '')), 0, 100) ?: null,
            'chassis_number'     => mb_substr(trim($iv['الرقم التسلسلي (الشاسيه)'] ?? ''), 0, 100) ?: null,
            'engine_no'          => mb_substr(trim($iv['رقم المحرك'] ?? ''), 0, 100) ?: null,
            'manufacturer'       => mb_substr(trim(($a['الشركة المصنعة'] ?? '') ?: ($iv['الشركة المصنعة'] ?? '')), 0, 100) ?: null,
            'model'              => mb_substr(trim(($a['الموديل'] ?? '') ?: ($iv['الموديل'] ?? '')), 0, 100) ?: null,
            'manufacturing_year' => uat_int(($a['سنة الصنع'] ?? '') ?: ($iv['سنة الصنع'] ?? '')),
            'plate_no'           => mb_substr(trim($r['رقم اللوحة'] ?? ''), 0, 50) ?: null,
            // N-21: أعمدةُ المالك في equipments مهجورةٌ بحارسٍ في القاعدة —
            //       بياناتُ الملكية في equipment_ownership_registry حصرًا (أدناه).
            'source_type'        => $isOwn ? 'مملوكة' : 'مورّدة',
            'operating_hours'    => uat_int($r['الساعات المنجزة'] ?? '', 0),
            'opening_meter'      => 0,
            'meter_uom'          => 'ساعات',
            'entry_date'         => uat_date($r['أول تشغيل'] ?? ''),
            'acquisition_cost'   => uat_num($a['التكلفة'] ?? ($iv['التكلفة بسجل الأصول'] ?? '')),
            'acquisition_currency' => 'USD',
            'availability_state' => 'متوفرة',
            'availability_status' => (uat_date($r['آخر تشغيل'] ?? '') >= date('Y-m-d', strtotime('-3 months'))) ? 'قيد الاستخدام' : 'متوقفة',
            'equipment_condition' => 'جيدة',
            'card_state'         => 'active',
            'status'             => 1,
            'company_id'         => $CO,
            'created_by'         => $actor,
            'created_at'         => date('Y-m-d H:i:s'),
        ]);
    $mapEquip[$code] = $id;
    uat_log('equipments', 'معدة');

    uat_upsert('equipment_ownership_registry',
        ['company_id' => $CO, 'equipment_id' => $id],
        [
            'actual_owner_name'  => mb_substr(trim(($r['اسم المالك الحقيقي'] ?? '') ?: $supName), 0, 255) ?: 'غير محدد',
            'owner_type'         => $isOwn ? 'شركة داخل المجموعة' : 'مورد خارجي',
            'owner_phone'        => mb_substr(trim($r['هاتف المالك'] ?? ''), 0, 60) ?: null,
            'owner_supplier_relation' => mb_substr(trim($r['طبيعة المورد'] ?? ''), 0, 120) ?: null,
            'operational_source' => $isOwn ? 'financed' : 'supplier_external',
            'purchase_value'     => uat_num($a['التكلفة'] ?? '') ?: null,
            'purchase_currency'  => 'USD',
            'migrated_from'      => 'UAT-2026',
            'note'               => mb_substr('من ف07 · ' . ($r['عدد العقود'] ?? '0') . ' عقدًا · ' . ($r['الساعات المنجزة'] ?? '0') . ' ساعة', 0, 255),
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);
    uat_log('equipment_ownership_registry', 'ملكية');
}

// ── ③ المشغلون · ش01 (242 لإكوبيشن) + ش02 (37 للموردين) ──────────────────────
$mapOperator = [];
$oseq = 0;

$putOperator = function (array $f) use (&$mapOperator, &$oseq, $actor, $CO) {
    $name = trim($f['name'] ?? '');
    if ($name === '' || $name === 'nan' || $name === 'لا يوجد مشغل') return null;
    $oseq++;
    $code = trim($f['code'] ?? '') ?: sprintf('OPR-%04d', $f['no'] ?: $oseq);
    $id = uat_upsert('employees',
        ['company_id' => $CO, 'employee_code' => mb_substr($code, 0, 50)],
        [
            'employee_type'    => 'سائق/مشغّل',
            'name'             => mb_substr($name, 0, 255),
            'identity_number'  => mb_substr(trim($f['identity'] ?? ''), 0, 100) ?: null,
            'identity_type'    => 'رقم وطني',
            'license_type'     => 'رخصة تشغيل معدات ثقيلة',
            'specialized_equipment' => $f['equipment'] ?? null,
            'supplier_id'      => $f['supplier_id'] ?? null,
            'employment_affiliation' => $f['affiliation'] ?? 'إكوبيشن',
            'salary_type'      => 'شهري',
            'employee_status'  => ($f['last'] && $f['last'] >= date('Y-m-d', strtotime('-6 months'))) ? 'نشط' : 'مفصول',
            'start_date'       => $f['first'] ?: null,
            'phone'            => mb_substr(trim($f['phone'] ?? '') ?: 'غير متوفر', 0, 255),
            'status'           => 1,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
    $mapOperator[(string) ($f['no'] ?: $code)] = $id;
    uat_log('employees', 'مشغّل');

    uat_upsert('equipment_operators',
        ['company_id' => $CO, 'employee_id' => $id],
        [
            'license_type'        => 'رخصة تشغيل معدات ثقيلة',
            'license_grade'       => $f['skill'] ?? 'ثقيلة',
            'operating_categories' => $f['equipment'] ?? null,
            'status'              => 1,
            'notes'               => mb_substr('من ' . ($f['src'] ?? 'ش01') . ' · ' . ($f['note'] ?? ''), 0, 500),
        ]);
    uat_log('equipment_operators', 'ترخيص');
    return $id;
};

foreach (uat_json('ش__ش01_مشغلو_إكوبيشن') as $r) {
    $putOperator([
        'no'        => uat_int($r['رقم المشغل'] ?? ''),
        'name'      => $r['الاسم المعتمد'] ?? '',
        'code'      => $r['كود الموظف'] ?? '',
        'identity'  => $r['رقم الهوية'] ?? '',
        'phone'     => $r['رقم الهاتف'] ?? '',
        'equipment' => $r['الآليات'] ?? '',
        'skill'     => $r['النوع الغالب'] ?? '',
        'first'     => uat_date($r['أول عمل'] ?? ''),
        'last'      => uat_date($r['آخر عمل'] ?? ''),
        'affiliation' => 'إكوبيشن — مورد داخلي',
        'src'       => 'ش01',
        'note'      => 'أيام العمل ' . ($r['أيام العمل'] ?? '0') . ' · ساعات ' . ($r['إجمالي الساعات'] ?? '0'),
    ]);
}
foreach (uat_json('ش__ش02_مشغلو_الموردين') as $r) {
    $sup = trim($r['المورد التابع له'] ?? '');
    $putOperator([
        'no'        => uat_int($r['رقمه في الشامل'] ?? ''),
        'name'      => $r['الاسم'] ?? '',
        'identity'  => $r['رقم الهوية'] ?? '',
        'phone'     => $r['رقم الهاتف'] ?? '',
        'equipment' => $r['الآليات'] ?? '',
        'first'     => uat_date(($r['أول شهر'] ?? '') . '-01'),
        'last'      => uat_date(($r['آخر شهر'] ?? '') . '-01'),
        'supplier_id' => $mapSupName[$sup] ?? null,
        'affiliation' => 'مورد خارجي — تسجيل للضبط',
        'src'       => 'ش02',
        'note'      => $r['الوضع التعاقدي'] ?? '',
    ]);
}

file_put_contents(UAT_IMPORT_DIR . '/_map_suppliers.json', json_encode(['byName' => $mapSupName, 'byNo' => $mapSupNo], JSON_UNESCAPED_UNICODE));
file_put_contents(UAT_IMPORT_DIR . '/_map_equipment.json', json_encode($mapEquip, JSON_UNESCAPED_UNICODE));
file_put_contents(UAT_IMPORT_DIR . '/_map_operators.json', json_encode($mapOperator, JSON_UNESCAPED_UNICODE));

uat_print_report('البذرة ③ · الموردون والأسطول والمشغلون');
printf("   الموردون: %d · المعدات: %d · سجلُّ الملكية: %d · العاملون: %d · تراخيصُ التشغيل: %d\n",
    uat_count('suppliers', "company_id=$CO"), uat_count('equipments', "company_id=$CO"),
    uat_count('equipment_ownership_registry', "company_id=$CO"), uat_count('employees', "company_id=$CO"),
    uat_count('equipment_operators', "company_id=$CO"));
