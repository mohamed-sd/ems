<?php
/**
 * tests/source_of_truth_test.php — لكلِّ بيانٍ مصدرٌ واحدٌ يحكم ويُعرض
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0033 · INJ-0074 · INJ-0109 · INJ-0151
 *
 * · 0033: «تغييرُ نسبةِ الإهلاك في ملفٍّ معتمدٍ ثم إعادةُ الاحتساب **يغيّر
 *   قيمةَ الإهلاك الشهريِّ بالنسبة نفسِها**؛ وأصلٌ بلا ملفٍّ معتمدٍ **يُرفض
 *   احتسابُه بسببٍ محكوم**».
 * · 0074: «كلُّ معدةٍ في لوحة الجاهزية تعرض **مرجعَ آخر شهادةِ جاهزيةٍ وتاريخَها
 *   ومُصدرَها**، والنقرُ عليه يفتح الأمرَ الذي أصدرها».
 * · 0109: «حوِّل إشارةً إلى خطرٍ بلا `owner_unit_id`: **يجب 422**».
 * · 0151: «كلُّ حسابٍ بنكيٍّ **مرتبطٌ بمعرّف موردٍ حقيقي**، وشاشةُ الدفع تقرأ
 *   الحسابَ الموثَّقَ للمورد المحدَّد **بلا إدخالٍ يدوي**».
 *
 * ◆ الوسمُ عائليٌّ ثابتٌ · والكنسُ الأبناءُ قبل الآباء · ويُفحص مُرجَعُ كلِّ حذف.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/app/Services/Finance/DepreciationService.php';
require_once $ROOT . '/app/Services/Operations/OperationsBoardService.php';
require_once $ROOT . '/app/Services/Risk/RiskService.php';

$conn = $GLOBALS['conn'];
$CO   = 4;
$TAG  = 'SOT-TEST-FAMILY';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ لكلِّ بيانٍ مصدرٌ واحدٌ يحكم ويُعرض');

/* ── ① INJ-0033 · ملفُّ الإهلاكِ يحكم القسط ─────────────────────────────── */
$say("\n── ① ملفُّ الإهلاكِ المعتمدُ يحكم القسطَ الشهريّ");
$cat = 'فئةُ ' . $TAG;
$conn->query("DELETE FROM fleet_depreciation_profile WHERE code LIKE 'DEP-{$TAG}%'");
$mkProf = function ($life, $pct) use ($conn, $CO, $TAG, $cat) {
    $conn->query("DELETE FROM fleet_depreciation_profile WHERE code = 'DEP-{$TAG}'");
    return $conn->query("INSERT INTO fleet_depreciation_profile
            (company_id, code, asset_category, method, useful_life, salvage_pct, state, created_at, updated_at)
          VALUES ({$CO}, 'DEP-{$TAG}', '{$cat}', 'straight_line', {$life}, {$pct}, 'approved', NOW(), NOW())");
};
$ok($mkProf(10, 0) !== false, 'ملفٌّ معتمدٌ: عمرٌ 10 شهور · خردةٌ 0٪');

$asset = array('id' => 0, 'company_id' => $CO, 'category' => $cat, 'equipment_id' => 0,
    'acquisition_date' => '2088-01-15', 'acquisition_cost' => 120000, 'salvage_value' => 0,
    'useful_life_months' => 999, 'method' => 'straight_line', 'accumulated_depreciation' => 0);
$p1 = \App\Services\Finance\DepreciationService::profileFor($conn, $CO, $asset);
$ok($p1 !== null && (string) $p1['code'] === 'DEP-' . $TAG, 'وطُوبق بالفئة');
$c1 = \App\Services\Finance\DepreciationService::computeFor($asset, '2088-02', $p1);
$ok(!empty($c1['ok']) && abs($c1['basis']['monthly'] - 12000) < 0.005,
    'القسطُ 12000 من الملفِّ لا من حقلِ الأصلِ (999 شهرًا): ' . ($c1['basis']['monthly'] ?? '?'));
$ok(isset($c1['basis']['terms_source']) && strpos((string) $c1['basis']['terms_source'], 'profile:') === 0,
    'ومصدرُ الشروطِ **مُعلَنٌ** في الأساس: ' . ($c1['basis']['terms_source'] ?? '?'));

/* «تغييرُ نسبةِ الإهلاك … يغيّر القسطَ بالنسبة نفسِها» */
$ok($mkProf(20, 0) !== false, 'ثم غُيّر العمرُ إلى 20 شهرًا (نصفُ النسبةِ الشهرية)');
$p2 = \App\Services\Finance\DepreciationService::profileFor($conn, $CO, $asset);
$c2 = \App\Services\Finance\DepreciationService::computeFor($asset, '2088-02', $p2);
$ok(!empty($c2['ok']) && abs($c2['basis']['monthly'] - 6000) < 0.005,
    '**فتغيّر القسطُ بالنسبة نفسِها**: 12000 ⇒ ' . ($c2['basis']['monthly'] ?? '?'));
/* وأصلٌ بلا ملفٍّ معتمد */
$noProf = $asset; $noProf['category'] = 'فئةٌ لا ملفَّ لها ' . $TAG;
$ok(\App\Services\Finance\DepreciationService::profileFor($conn, $CO, $noProf) === null,
    '«**وأصلٌ بلا ملفٍّ معتمدٍ**» لا يجد ملفًّا — والترحيلُ يردُّه DEP-422');
$svc = (string) @file_get_contents($ROOT . '/app/Services/Finance/DepreciationService.php');
$ok(strpos($svc, 'DEP-422') !== false && strpos($svc, 'profileFor($conn, $companyId, $asset)') !== false,
    'والردُّ **بسببٍ محكومٍ** في مسارِ الترحيلِ لا في المعاينة');
$conn->query("DELETE FROM fleet_depreciation_profile WHERE code LIKE 'DEP-{$TAG}%'");

/* ── ② INJ-0109 · لا خطرَ بلا وحدةٍ مالكة ──────────────────────────────── */
$say("\n── ② «لا خطرَ يُسجَّل بلا وحدةٍ مالكة» ⇒ 422");
$ru = 0;
$r = $conn->query('SELECT id FROM risk_units ORDER BY id LIMIT 1');
if ($r && ($x = $r->fetch_row())) { $ru = (int) $x[0]; }
$threw = ''; $madeId = 0;
try {
    $res = \App\Services\Risk\RiskService::createRisk($conn, $CO, array(
        'ru_id' => $ru, 'title' => 'خطرُ شاهدٍ ' . $TAG, 'root_cause' => 'شاهد',
        'scope_type' => 'إداري', 'owner_unit_id' => null), 1, true);
    $madeId = isset($res['id']) ? (int) $res['id'] : 0;
} catch (\Throwable $t) { $threw = $t->getMessage(); }
$ok(strpos($threw, 'RSK-422') === 0, '«يجب 422» بلا وحدةٍ مالكة: ' . mb_substr($threw, 0, 80));
$ok($madeId === 0, 'ولم يُكتب صفٌّ');
$scrSig = (string) @file_get_contents($ROOT . '/Risk/risk_signals.php');
$ok(strpos($scrSig, 'sigOwner') !== false && strpos($scrSig, 'owner_unit_id') !== false,
    'والنموذجُ يرسل الوحدةَ المالكةَ عند التحويل');
/* والصحيحُ يمرُّ ويظهر لإدارتِه */
$ou = 0;
$r = $conn->query("SELECT unit_id FROM org_units WHERE company_id={$CO} ORDER BY unit_id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $ou = (int) $x[0]; }
$conn->query("DELETE FROM risk_register WHERE company_id={$CO} AND title LIKE '%{$TAG}%'");
$madeOk = 0;
try {
    $res = \App\Services\Risk\RiskService::createRisk($conn, $CO, array(
        'ru_id' => $ru, 'title' => 'خطرُ شاهدٍ ' . $TAG, 'root_cause' => 'شاهد',
        'scope_type' => 'إداري', 'owner_unit_id' => $ou), 1, true);
    $madeOk = isset($res['id']) ? (int) $res['id'] : 0;
} catch (\Throwable $t) { $madeOk = 0; }
$ok($madeOk > 0, 'وبوحدةٍ صحيحةٍ يُسجَّل #' . $madeOk);
$r = $conn->query("SELECT owner_unit_id FROM risk_register WHERE id={$madeOk}");
$own = ($r && ($x = $r->fetch_row())) ? (int) $x[0] : 0;
$ok($own === $ou, '«وبعد التمرير الصحيح» يحمل الصفُّ وحدتَه: ' . $own . ' = ' . $ou
    . ' — فيراه حارسُ النطاقِ الذي يطابق بالمساواة');
$d = $conn->query("DELETE FROM risk_register WHERE company_id={$CO} AND title LIKE '%{$TAG}%'");
$r = $conn->query("SELECT COUNT(*) FROM risk_register WHERE company_id={$CO} AND title LIKE '%{$TAG}%'");
$ok($d !== false && $r && (int) $r->fetch_row()[0] === 0, 'وكُنست عائلةُ الخطر');

/* ── ③ INJ-0151 · حساباتُ الموردين من مصدرِها المنظَّم ─────────────────── */
$say("\n── ③ «كلُّ حسابٍ بنكيٍّ مرتبطٌ بمعرّف موردٍ حقيقي»");
$bank = (string) @file_get_contents($ROOT . '/Suppliers/supplier_bank.php');
$ok(strpos($bank, 'cmp03_stage_insert') === false,
    'الشاشةُ لم تعد تكتب المخزنَ البينيَّ — «بلا إدخالٍ يدوي»');
$ok(strpos($bank, 'FROM suppliers s') !== false && strpos($bank, 'bank_verified_at') !== false,
    'وصارت تقرأ `suppliers` — المصدرَ الموثَّقَ المربوطَ بمعرِّفه');
$r = $conn->query("SELECT COUNT(*) FROM suppliers
                    WHERE company_id={$CO} AND COALESCE(bank_account_no,'') <> ''");
$nBank = ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
$ok($nBank > 0, 'وفي المصدرِ ' . $nBank . ' حسابًا كلُّها بمعرِّفِ مورد');
$r = $conn->query("SELECT COUNT(*) FROM suppliers
                    WHERE company_id={$CO} AND COALESCE(bank_account_no,'') <> '' AND id IS NULL");
$orphan = ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
$ok($orphan === 0, 'وصفرُ حسابٍ بلا مورد: ' . $orphan);
/* والمخزنُ البينيُّ **لا يُحذف** — يُعلَن ويبقى لقرارِ المالك */
$r = $conn->query("SELECT COUNT(*) FROM cmp03_screen_rows WHERE canonical_file='supplier_bank.php'");
$stale = ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
$ok($stale >= 0, 'والمخزنُ البينيُّ باقٍ بـ' . $stale . ' صفًّا — يُعلَن لقرارِ المالكِ ولا يُحذف');

/* ── ④ INJ-0074 · شهادةُ الجاهزيةِ تُخزَّن وتُعرض ─────────────────────── */
$say("\n── ④ «مرجعُ آخر شهادةِ جاهزيةٍ وتاريخُها ومُصدرُها»");
$r = $conn->query("SHOW COLUMNS FROM mnt_order LIKE 'readiness_cert_ref'");
$ok($r && $r->num_rows === 1, 'عمودُ مرجعِ الشهادةِ قائمٌ في `mnt_order`');
$rts = (string) @file_get_contents($ROOT . '/Maintenance/return_to_service.php');
$ok(strpos($rts, 'readiness_cert_ref=?') !== false,
    'وعودةُ الخدمةِ تخزّنه — لا تكتبه في سجلِّ التدقيقِ وحدَه');

$eq = 0;
$r = $conn->query("SELECT id FROM equipments WHERE company_id={$CO} ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $eq = (int) $x[0]; }
$conn->query("DELETE FROM mnt_order WHERE company_id={$CO} AND code LIKE '%{$TAG}%'");
$certRef = 'CERT-' . $TAG;
$insOrd = $conn->query("INSERT INTO mnt_order
        (company_id, code, equipment_id, maint_type, state, readiness_cert_ref,
         closed_at, closed_by, created_by, created_at, updated_at)
      VALUES ({$CO}, 'MO-{$TAG}', {$eq}, 'corrective', 'Closed', '{$certRef}',
              NOW(), 1, 1, NOW(), NOW())");
$ordId = $insOrd ? (int) $conn->insert_id : 0;
$ok($ordId > 0, 'أمرُ صيانةٍ مقفَلٌ بشهادةٍ #' . $ordId, $conn->error);

$grid = \App\Services\Operations\OperationsBoardService::readinessGrid($conn, $CO, 0, '');
$cell = null;
foreach ($grid['cells'] as $c) { if ((int) $c['id'] === $eq) { $cell = $c; break; } }
$ok($cell !== null, 'والمعدةُ في شبكةِ الجاهزية');
$ok($cell && (string) $cell['cert_ref'] === $certRef,
    'وتعرض **مرجعَ** الشهادة: «' . ($cell ? $cell['cert_ref'] : '?') . '»');
$ok($cell && (string) $cell['cert_at'] !== '', 'و**تاريخَها**: ' . ($cell ? substr($cell['cert_at'], 0, 10) : '?'));
$ok($cell && (string) $cell['cert_by'] !== '', 'و**مُصدرَها**: ' . ($cell ? $cell['cert_by'] : '?'));
$ok($cell && strpos((string) $cell['cert_link'], (string) $ordId) !== false,
    '«**والنقرُ عليه يفتح الأمرَ الذي أصدرها**»: ' . ($cell ? $cell['cert_link'] : '?'));
$board = (string) @file_get_contents($ROOT . '/Fleet/readiness_board.php');
$ok(strpos($board, 'cert_ref') !== false && strpos($board, 'cert_link') !== false,
    'واللوحةُ تُصيّرها فعلًا — لا تبقى في مُرجَعِ الخدمة');
$ok(strpos($board, 'لا شهادةَ جاهزيةٍ مسجَّلةٌ بعد') !== false,
    'ومَن لا شهادةَ له يُقال فيه ذلك صراحةً — فالسكوتُ يُقرأ سلامةً');

$d = $conn->query("DELETE FROM mnt_order WHERE company_id={$CO} AND code LIKE '%{$TAG}%'");
$r = $conn->query("SELECT COUNT(*) FROM mnt_order WHERE company_id={$CO} AND code LIKE '%{$TAG}%'");
$ok($d !== false && $r && (int) $r->fetch_row()[0] === 0, 'وكُنس أمرُ الشاهد (مُرجَعُ الحذفِ مفحوص)');

$say("\n══ النتيجة: ناجحٌ {$PASS} · راسبٌ {$FAIL}");
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL > 0 ? 1 : 0);
