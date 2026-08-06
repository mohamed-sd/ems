<?php
/**
 * tools/seed_business_models_real.php — ④-١: قاموس نماذج العمل الحقيقي
 * ───────────────────────────────────────────────────────────────────────────
 * كانت scr_business_models تحمل 10 بذورٍ عشوائية («قياسي 3 · ميداني 4») لا
 * تطابق النظام. النماذج الحية أربعة (أعمدة work_model في المخطط: hour · ton ·
 * trip · meter — والمروحة تفوتر بها M-24)، فتُستبدل البذور: تصديرٌ ثم حذفٌ
 * وبذرُ الأربعة الحقيقية بصفاتها من سلوك النظام الفعلي [تجريبي — ق-15].
 * idempotent بكود النموذج. التشغيل: php tools/seed_business_models_real.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

/* ① تصدير البذور العشوائية ثم حذفها (سابقة بذور M-00 الخمسين) */
$rows = array();
$r = mysqli_query($conn, "SELECT * FROM scr_business_models WHERE is_seed = 1 AND code_model LIKE 'CMP-%'");
while ($r && ($x = mysqli_fetch_assoc($r))) { $rows[] = $x; }
if ($rows) {
    $f = __DIR__ . '/../storage/backups/business_models_generic_seeds_' . date('Ymd_His') . '.json';
    file_put_contents($f, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    mysqli_query($conn, "DELETE FROM scr_business_models WHERE is_seed = 1 AND code_model LIKE 'CMP-%'");
    fwrite(STDOUT, '① صُدّرت ' . count($rows) . " بذرة عشوائية وحُذفت ({$f})\n");
}

/* ② النماذج الأربعة الحية — صفاتها من سلوك النظام الفعلي */
$MODELS = array(
    array('WM-HOUR', 'التشغيل بالساعة', 'ساعة تشغيل', 'ساعة', 'سجل الدوام اليومي (وردية × معدة × مشغل)',
          'التايم شيت المعتمد بسلسلة الأطراف', 'سعر الساعة من أسطر أسعار العقد', 'كل الأنواع العاملة بالعداد',
          'ساعة عداد', 'ساعة', 'ساعة', 'ساعات معتمدة × سعر تعاقدي', 'بالساعة أو شهري بحسب عقده', 'شهرية'),
    array('WM-TON', 'الإنتاج بالطن', 'طن منقول أو منتج', 'طن', 'وزنات المواقع المعتمدة (M-24: الحكم على الواقعة)',
          'سند الوزن أو الكمية المعتمدة من العميل', 'سعر الطن من أسطر أسعار العقد', 'معدات النقل والإنتاج الوزني',
          'ساعة عداد (للجاهزية لا للفوترة)', 'طن', 'طن', 'كميات معتمدة × سعر الطن', 'بالإنتاج أو شهري', 'شهرية'),
    array('WM-METER', 'الإنتاج بالمتر', 'متر منفذ', 'متر', 'قياسات الجبهة المعتمدة (M-24)',
          'محضر القياس المعتمد من العميل', 'سعر المتر من أسطر أسعار العقد', 'معدات الحفر والتنفيذ الطولي',
          'ساعة عداد (للجاهزية لا للفوترة)', 'متر', 'متر', 'كميات معتمدة × سعر المتر', 'بالإنتاج أو شهري', 'شهرية'),
    array('WM-TRIP', 'النقل بالرحلة', 'رحلة ترحيل', 'رحلة', 'أوامر الترحيل المنفذة (trs_)',
          'أمر الترحيل المقفل بمستنده', 'تعرفة الرحلة بالمسار والحمولة', 'الناقلات والقلابات',
          'ساعة عداد (للجاهزية لا للفوترة)', 'رحلة', 'رحلة', 'رحلات منفذة × تعرفة المسار', 'بالرحلة أو شهري', 'شهرية'),
);
$ins = $conn->prepare("INSERT INTO scr_business_models
    (company_id, code_model, name_model, unit_work, unit_measure, method_measure_field,
     doc_proving, basis_pricing, types_equipment_applicable, unit_meter_equipment,
     unit_container_supplier, unit_contracting_supplier, basis_due_supplier, basis_wage_operator,
     cycle_closing, status, status_label, approver_name, date_effective, is_seed, created_by, created_by_name)
    VALUES (4, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'معتمد', 'معتمد',
            'قرار شركة منفذة — ق-15', CURDATE(), 1, 0, 'الموجة ٦ [تجريبي — ق-15]')
    ON DUPLICATE KEY UPDATE name_model = VALUES(name_model)");
$made = 0;
foreach ($MODELS as $m) {
    $exists = $conn->query("SELECT id FROM scr_business_models WHERE code_model = '" . $m[0] . "'")->fetch_assoc();
    if ($exists) { fwrite(STDOUT, "= {$m[0]} قائم\n"); continue; }
    // الأعمدة النصية الـ14 بترتيب المصفوفة
    $ins->bind_param('ssssssssssssss', $m[0], $m[1], $m[2], $m[3], $m[4], $m[5], $m[6], $m[7], $m[8], $m[9], $m[10], $m[11], $m[12], $m[13]);
    if ($ins->execute()) { $made++; fwrite(STDOUT, "+ {$m[0]} {$m[1]}\n"); }
    else { fwrite(STDOUT, "✖ {$m[0]}: " . $ins->error . "\n"); }
}
fwrite(STDOUT, "② بُذر {$made} نموذجًا حيًّا\n");
