<?php
/**
 * tools/seed_cmp03_demo.php — باذر البيانات التجريبية لشاشات CMP-03 الوليدة
 * ───────────────────────────────────────────────────────────────────────────
 * يملأ مخزن `cmp03_screen_rows` بصفوف بذرةٍ عربيةٍ واقعيةٍ لكل شاشةٍ من الـ51
 * (الشركة التجريبية co4 — عرف باذر update0007). القيم تُشتق من دلالة تسمية
 * كل عمودٍ (تواريخ · نسب · مبالغ · أسماء · معدات · حالات…) فتُرى الشاشةُ
 * كما ستكون حية. **آمن الإعادة**: صفوف is_seed=1 تُمسح وتُبذر من جديد،
 * وما أدخله المستخدمون (is_seed=0) لا يُمسّ أبدًا.
 *
 * التشغيل: php tools/seed_cmp03_demo.php [--company=4] [--rows=10]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
require_once __DIR__ . '/cmp03_lib.php';
require_once __DIR__ . '/../includes/gov_columns.php';

$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);

$COMPANY = 4; $N = 10;
foreach ($argv as $a) {
    if (strpos($a, '--company=') === 0) { $COMPANY = (int) substr($a, 10); }
    if (strpos($a, '--rows=') === 0)    { $N = (int) substr($a, 7); }
}

/* عينات واقعية — من عالم النظام نفسه (مناجم · معدات · موردون · عملات) */
$P = array(
    'names'      => array('محمد سيد أحمد','يسن سيد أحمد','آدم عمر إبراهيم','مصعب الطيب','أروى عثمان','الطاهر بشير','هاجر النور','عبد الله كرار','سارة الفاتح','معاوية إدريس'),
    'titles'     => array('مدير الموقع','مسؤول التشغيل','محاسب أول','مشرف الورديات','مدير الأسطول','مسؤول المشتريات','أمين المخزن','مسؤول الموردين'),
    'depts'      => array('إدارة التشغيل','إدارة الموقع','المالية والخزينة','إدارة الأسطول','المشتريات','الموارد البشرية','إدارة الموردين'),
    'sites'      => array('منجم الروسية','منجم قبقبة','موقع التخزين المركزي','منجم أم دريسة','محطة الترحيل الشرقية'),
    'equipment'  => array('حفار CAT-349 · EQ-1017','شيول 966H · EQ-1042','قلاب HOWO · EQ-1105','مولد 500KVA · EQ-1210','دركتر D8 · EQ-1033','خلاطة خرسانة · EQ-1300'),
    'suppliers'  => array('شركة النيل للمعدات','مؤسسة الصحراء للنقل','التقنية للقطع والصيانة','مجموعة البركة التجارية','شركة الشرق للوقود'),
    'clients'    => array('شركة التعدين الوطنية','مناجم الشمال المحدودة','شركة الذهب السوداني'),
    'contracts'  => array('CON-2026-011','CON-2026-014','CON-2026-019','SUP-2026-007','EMP-2026-102'),
    'docs'       => array('REQ-3301','PO-2087','INV-5512','GRN-1093','MEM-774'),
    'statuses'   => array('معتمد','معتمد','قيد المراجعة','مسودة','معتمد','موقوف','قيد المراجعة','معتمد','مسودة','ملغي'),
    'currencies' => array('USD','USD','SDG','USD','SDG'),
    'notes'      => array('ضمن الخطة الشهرية','بموافقة مدير الموقع','مرحل من الفترة السابقة','عاجل — أولوية قصوى','مطابق للعقد','بانتظار المستند الأصل',''),
    'yesno'      => array('نعم','لا'),
);

/** قيمة تجريبية بدلالة تسمية العمود */
function seed_val($label, $i, $P) {
    $L = $label;
    $pick = function ($arr) use ($i) { return $arr[$i % count($arr)]; };
    $has = function ($words) use ($L) {
        foreach ((array) $words as $w) { if (mb_strpos($L, $w) !== false) { return true; } }
        return false;
    };
    if ($has(array('تاريخ','بداية','نهاية','السريان','آخر قسط','أول قسط'))) {
        return date('Y-m-d', strtotime('2026-05-01 +' . (($i * 9) % 120) . ' days'));
    }
    if ($has('الفترة'))                      { return '2026-0' . (($i % 7) + 1); }
    if ($has(array('نسبة','٪','معدل الالتزام'))) { return (string) (55 + ($i * 7) % 45) . '%'; }
    if ($has(array('عملة','العملة')))         { return $pick($P['currencies']); }
    if ($has('سعر الصرف'))                    { return '650 SDG/USD · بنك السودان'; }
    if ($has(array('مبلغ','قيمة','تكلفة','رصيد','إجمالي','سعر','أجر','راتب','رأس المال','قسط','جزاء','هامش','معادل'))) {
        return number_format(1500 + ($i * 3735) % 82000) . ' USD';
    }
    if ($has(array('عدد','كمية','مرات','حالات')))  { return (string) (2 + ($i * 3) % 38); }
    if ($has(array('ساعات','مدة','زمن')))     { return (string) (4 + ($i * 5) % 220) . ' ساعة'; }
    if ($has(array('طاقة','حمولة','وزن')))    { return (string) (10 + ($i * 4) % 90) . ' طنًّا'; }
    if ($has(array('حالة','الحال')))          { return $pick($P['statuses']); }
    if ($has(array('المعتمِد','اعتمده','وقّعه','راجعه','المدير'))) { return $pick($P['names']) . ' — ' . $pick($P['titles']); }
    if ($has(array('الموظف','المستخدم','المشغّل','مقدم','أمين','الفني'))) { return $pick($P['names']); }
    if ($has(array('الصفة','الوظيفة','المنصب'))) { return $pick($P['titles']); }
    if ($has(array('الإدارة','الجهة','القسم'))) { return $pick($P['depts']); }
    if ($has(array('الموقع','المنجم','المحطة','الوجهة'))) { return $pick($P['sites']); }
    if ($has(array('معدة','المعدات','الآلية','الأصل','العين'))) { return $pick($P['equipment']); }
    if ($has(array('مورد','الممول','الناقل'))) { return $pick($P['suppliers']); }
    if ($has(array('عميل','الطرف')))          { return $pick($P['clients']); }
    if ($has(array('عقد','الالتزام')))        { return $pick($P['contracts']); }
    if ($has(array('مرجع','مستند','رقم القيد','رقم الأمر','المرفق','إذن','سند'))) { return $pick($P['docs']) . '-' . ($i + 1); }
    if ($has(array('رقم','كود','معرف')))      { return 'CMP-' . str_pad((string) (100 + $i), 4, '0', STR_PAD_LEFT); }
    if ($has(array('هل','إلزامي','مفعل')))    { return $pick($P['yesno']); }
    if ($has(array('ملاحظ','سبب','وصف','مبرر','تفسير'))) { return $pick($P['notes']); }
    if ($has('درجة'))                          { return $pick(array('نهائي','مبدئي','حساس','عادي')); }
    if ($has('نسخة'))                          { return 'v2026.08-' . (($i % 3) + 1); }
    return $pick(array('قياسي','ميداني','شهري','تشغيلي','مباشر','دوري')) . ' ' . (($i % 4) + 1);
}

$screens = cmp03_doc_screens($ROOT);
$map = cmp03_file_map($conn);

/* الشاشات الوليدة = ما بناه المولد (بصمة الملاحظة) */
$targets = array();
foreach ($screens as $cf => $sc) {
    if (isset($map[$cf]) && strpos((string) ($map[$cf]['note'] ?? ''), 'CMP-03 ⑥:') === 0) { $targets[$cf] = $sc; }
}
if (!$targets) { echo "لا شاشات وليدة في القاموس.\n"; exit(1); }

$AUTO = array('الكيان','المُنشئ — الاسم والصفة','تاريخ الإنشاء','مفتاح منع التكرار','سجل الاطّلاع',
              'معكوس بـ','عكس عن','الجهة المُنشئة','العملة الأساسية');
$autoNorm = array();
foreach ($AUTO as $g) { $autoNorm[cmp03_norm($g)] = 1; }

$ins = $conn->prepare("INSERT INTO cmp03_screen_rows
    (company_id, canonical_file, payload, status, is_seed, created_by, created_by_name, created_at)
    VALUES (?, ?, ?, ?, 1, NULL, ?, ?)");

$total = 0;
foreach ($targets as $cf => $sc) {
    $es = mysqli_real_escape_string($conn, $cf);
    mysqli_query($conn, "DELETE FROM cmp03_screen_rows
                         WHERE canonical_file='$es' AND company_id=$COMPANY AND is_seed=1");
    for ($i = 0; $i < $N; $i++) {
        $payload = array();
        foreach ($sc['cols'] as $col) {
            if (isset($autoNorm[cmp03_norm($col)])) { continue; } // الآلية من المخزن والجلسة
            $payload[$col] = seed_val($col, $i + crc32($cf) % 7, $P);
        }
        $status = $payload['الحالة'] ?? $P['statuses'][$i % count($P['statuses'])];
        $creator = $P['names'][$i % count($P['names'])] . ' — ' . $P['titles'][($i + 2) % count($P['titles'])];
        $createdAt = date('Y-m-d H:i:s', strtotime('2026-06-10 08:30 +' . (($i * 31) % 50) . ' days +' . ($i * 47 % 540) . ' minutes'));
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ins->bind_param('isssss', $COMPANY, $cf, $json, $status, $creator, $createdAt);
        $ins->execute();
        $total++;
    }
    echo "✔ $cf: $N صفوف\n";
}
$ins->close();
echo "\n✔ بُذر $total صفًّا تجريبيًّا لـ" . count($targets) . " شاشةً (co$COMPANY) — آمن الإعادة (is_seed=1).\n";
