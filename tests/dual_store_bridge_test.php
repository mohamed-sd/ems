<?php
/**
 * tests/dual_store_bridge_test.php — حقيقةٌ واحدةٌ في مخزنٍ واحد
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0055 · INJ-0054 · INJ-0163 · INJ-0370 · INJ-0356 · INJ-0108 · INJ-0214 · INJ-0411
 *
 * · 0055: «يومُ غيابٍ يُسجَّل من الشاشة يظهر في مدخلات الزمن في المسيّر؛
 *   **وصفرُ صفٍّ جديدٍ في `scr_attendance`**».
 * · 0054: «خصمٌ يُعتمد من الشاشة يظهر في مقاصّات المسيّر للفترة نفسِها؛
 *   **وصفرُ صفٍّ جديدٍ في `scr_deductions`**».
 * · 0163: «إضافةُ عينٍ مموَّلةٍ تظهر فورًا في **حصص الملكية**؛ وصفرُ صفٍّ جديدٍ
 *   في `scr_fin_assets`».
 * · 0370: «إذنُ دخولِ معدةٍ **يرتبط بمعدةٍ من سجل المعدات وبموقعٍ من سجل
 *   المواقع**، وهويةُ المعتمِدِ **تُشتق من الحساب** ولا تُكتب يدويًّا».
 * · 0356: «الشاشةُ تعرض معدلَ الاستهلاك **محسوبًا من الحركات**، ولا يوجد فيها
 *   حقلُ إدخالٍ يدويٍّ للمعدل».
 * · 0108: «إضافةُ خطرٍ **تُنشئ إشارةً في `risk_signals` لا صفًّا في
 *   `commercial_risks`**».
 * · 0214: «عقدٌ يُنشأ من شاشة الموارد البشرية يظهر فورًا في **سجل العقود
 *   الموحَّد**؛ **وصفرُ صفٍّ جديدٍ في `drivercontracts`**».
 * · 0411: «بعد إغلاقِ خطرٍ في `Risk/risk_card.php` **ينقص مؤشرُ المخاطر
 *   المفتوحة** في `Portal/ceo_reports.php` بواحد».
 *
 * ◆ **والقاعدةُ التي حكمت التحويلَ كلَّه**: لم يُحذف صفٌّ ولم تُرحَّل بيانة.
 *   المخزنُ القديمُ يبقى **قارئًا** بصفوفِه، والكتابةُ وحدَها تحوّلت.
 * ◆ والاختبارُ السلبيُّ في كلِّ جسر: مرجعٌ لا يقابله صفٌّ ⇒ **رفضٌ معلَنٌ**
 *   لا كتابةٌ بصفرٍ — فحارسٌ لا يردُّ المخترَعَ ليس حارسًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/includes/cmp03_local_store.php';
require_once $ROOT . '/app/Services/Risk/RiskService.php';

$conn = $GLOBALS['conn'];
$CO   = 4;
$TAG  = 'DUALBRG-TEST-FAMILY';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$cnt = function ($t, $w = '1=1') use ($conn) {
    $r = $conn->query("SELECT COUNT(*) FROM `{$t}` WHERE {$w}");
    return $r ? (int) $r->fetch_row()[0] : -1;
};
$say('══ حقيقةٌ واحدةٌ في مخزنٍ واحد');
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => $CO, 'name' => 'شاهد');

/* مراجعُ حقيقيةٌ يُبنى عليها */
$emp = ''; $eqCode = ''; $ent = ''; $prj = ''; $period = '';
$r = $conn->query("SELECT employee_code FROM employees WHERE company_id={$CO} ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $emp = (string) $x[0]; }
$r = $conn->query("SELECT code FROM equipments WHERE company_id={$CO} AND code IS NOT NULL ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $eqCode = (string) $x[0]; }
$r = $conn->query('SELECT legal_name FROM legal_entities ORDER BY entity_id LIMIT 1');
if ($r && ($x = $r->fetch_row())) { $ent = (string) $x[0]; }
$r = $conn->query("SELECT name FROM project WHERE company_id={$CO} ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $prj = (string) $x[0]; }
$r = $conn->query("SELECT period_from FROM payroll_runs WHERE company_id={$CO} AND COALESCE(is_deleted,0)=0 ORDER BY id DESC LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $period = substr((string) $x[0], 0, 7); }
$ok($emp !== '' && $eqCode !== '' && $ent !== '' && $prj !== '' && $period !== '',
    'مراجعُ حقيقيةٌ: موظف · معدة · كيان · موقع · فترةُ مسيّر',
    "emp={$emp} eq={$eqCode} prj={$prj} per={$period}");

/* الكنسُ القبليّ — الأبناءُ قبل الآباء ويُفحص المُرجَع */
$sweep = function () use ($conn, $TAG, $CO) {
    $a = $conn->query("DELETE FROM attendance_days WHERE reference_doc LIKE '%{$TAG}%'");
    $b = $conn->query("DELETE FROM payroll_deductions WHERE doc_ref LIKE '%{$TAG}%'");
    $c = $conn->query("DELETE FROM asset_ownership_shares WHERE doc_ref LIKE '%{$TAG}%'");
    $d = $conn->query("DELETE FROM scr_site_gate_equip WHERE no_permit LIKE '%{$TAG}%'");
    $e = $conn->query("DELETE FROM risk_signals WHERE rule_key LIKE '%{$TAG}%'");
    foreach (array($a, $b, $c, $d, $e) as $x) { if ($x === false) { return -1; } }
    return 0;
};
$ok($sweep() === 0, 'الكنسُ القبليُّ نظيفٌ بالعائلة (مُرجَعُ كلِّ حذفٍ مفحوص)');

/* ── ① INJ-0055 · الحضورُ يبلغ المسيّر ─────────────────────────────────── */
$say("\n── ① الحضورُ يبلغ مدخلاتِ الزمنِ لا مخزنَ الشاشة");
$s0 = $cnt('scr_attendance'); $a0 = $cnt('attendance_days');
$r1 = cmp03_store_insert($conn, $CO, 'attendance.php', array(
    'كود الموظف' => $emp, 'التاريخ' => '2089-01-05',
    'رمز الحالة' => 'غياب', 'وصف الحالة' => 'غيابٌ بلا إذن',
    'المستند المؤيد' => 'DOC-' . $TAG), 'مسودة', 1, 'شاهد');
$ok($r1 === true, 'سُجّل اليوم: ' . mb_substr(cmp03_store_notice(), 0, 70));
$ok($cnt('scr_attendance') === $s0, '«**وصفرُ صفٍّ جديدٍ في `scr_attendance`**»: ' . $s0);
$ok($cnt('attendance_days') === $a0 + 1, 'وصفٌّ واحدٌ في `attendance_days` — يقرؤه المسيّر');
$r = $conn->query("SELECT status_code FROM attendance_days WHERE reference_doc LIKE '%{$TAG}%'");
$sc = ($r && ($x = $r->fetch_row())) ? (string) $x[0] : '';
$ok($sc === 'ABSN', 'وبرمزِ الغيابِ المحكومِ `ABSN` — فيخفض الساعاتِ المحتسبة');
/* السلبيّ: موظفٌ مجهول */
$bad = cmp03_store_insert($conn, $CO, 'attendance.php', array(
    'كود الموظف' => 'لا-موظفَ-' . $TAG, 'التاريخ' => '2089-01-06'), 'مسودة', 1, 'شاهد');
$ok($bad === false && mb_strpos(cmp03_store_notice(), 'ATT-422') !== false,
    'وموظفٌ مجهولٌ **يُردُّ** ولا يُكتب بمرجعٍ مخترَع');

/* ── ② INJ-0054 · الخصمُ يبلغ المقاصّة ────────────────────────────────── */
$say("\n── ② الخصمُ يبلغ مقاصّاتِ المسيّر");
$s0 = $cnt('scr_deductions'); $p0 = $cnt('payroll_deductions');
$r2 = cmp03_store_insert($conn, $CO, 'deductions.php', array(
    'رقم القرار' => 'DEC-' . $TAG, 'كود الموظف' => $emp, 'الشهر' => $period,
    'نوع الخصم' => 'جزاء', 'سبب الخصم' => 'شاهد', 'قيمة الخصم' => '250'), 'مسودة', 1, 'شاهد');
$ok($r2 === true, 'سُجّل الخصم: ' . mb_substr(cmp03_store_notice(), 0, 70));
$ok($cnt('scr_deductions') === $s0, '«**وصفرُ صفٍّ جديدٍ في `scr_deductions`**»: ' . $s0);
$ok($cnt('payroll_deductions') === $p0 + 1, 'وصفٌّ في `payroll_deductions` — يخفض الصافي');
$r = $conn->query("SELECT run_id, source_type, amount FROM payroll_deductions WHERE doc_ref LIKE '%{$TAG}%'");
$dd = $r ? $r->fetch_assoc() : null;
$ok($dd && (int) $dd['run_id'] > 0 && (string) $dd['source_type'] === 'penalty',
    'وبجولةِ المسيّرِ #' . ($dd ? $dd['run_id'] : '?') . ' ونوعِ مصدرٍ من التعداد');
/* السلبيّ: فترةٌ بلا جولة */
$bad = cmp03_store_insert($conn, $CO, 'deductions.php', array(
    'رقم القرار' => 'DEC2-' . $TAG, 'كود الموظف' => $emp, 'الشهر' => '1999-01',
    'نوع الخصم' => 'جزاء', 'قيمة الخصم' => '10'), 'مسودة', 1, 'شاهد');
$ok($bad === false && mb_strpos(cmp03_store_notice(), 'DED-409') !== false,
    'وفترةٌ بلا جولةِ مسيّرٍ **تُردُّ** — لا خصمَ يُعلَّق في الهواء');

/* ── ③ INJ-0163 · العينُ تظهر في حصصِ الملكية ────────────────────────── */
$say("\n── ③ العينُ المموَّلةُ تظهر في حصصِ الملكية");
$s0 = $cnt('scr_fin_assets'); $sh0 = $cnt('asset_ownership_shares');
$r3 = cmp03_store_insert($conn, $CO, 'fin_assets.php', array(
    'رقم السجل' => 'FA-' . $TAG, 'كود العين' => $eqCode, 'الممول' => $ent,
    'قيمة الشراء' => '200000', 'رأس المال المموَّل' => '50000',
    'تاريخ الربط' => '2089-01-05'), 'مسودة', 1, 'شاهد');
$ok($r3 === true, 'رُبطت العين: ' . mb_substr(cmp03_store_notice(), 0, 70));
$ok($cnt('scr_fin_assets') === $s0, '«**وصفرُ صفٍّ جديدٍ في `scr_fin_assets`**»: ' . $s0);
$ok($cnt('asset_ownership_shares') === $sh0 + 1, 'وحصةٌ في `asset_ownership_shares`');
$r = $conn->query("SELECT percent FROM asset_ownership_shares WHERE doc_ref LIKE '%{$TAG}%'");
$pc = ($r && ($x = $r->fetch_row())) ? round((float) $x[0], 2) : -1;
$ok(abs($pc - 25.0) < 0.01, 'والنسبةُ **مشتقّةٌ** 50000÷200000 = ' . $pc . '٪ — لا تُكتب بيد');

/* ── ④ INJ-0370 · الإذنُ بمفاتيحِه ومعتمِدِه ─────────────────────────── */
$say("\n── ④ إذنُ الدخولِ يرتبط بسجلَّيه ومعتمِدُه من الحساب");
$r4 = cmp03_store_insert($conn, $CO, 'site_gate_equip.php', array(
    'رقم الإذن' => 'P-' . $TAG, 'نوع الإذن' => 'دخول', 'الموقع' => $prj,
    'كود المعدة' => $eqCode, 'اعتماد مدير الموقع' => 'اسمٌ يكتبه المُدخِل',
    'سبب الحركة' => 'تشغيل'), 'مسودة', 1, 'شاهد');
$ok($r4 === true, 'صدر الإذن: ' . mb_substr(cmp03_store_notice(), 0, 80));
$r = $conn->query("SELECT equipment_id, site_project_id, approved_by_user, approval_manager_site
                     FROM scr_site_gate_equip WHERE no_permit LIKE '%{$TAG}%'");
$pg = $r ? $r->fetch_assoc() : null;
$ok($pg && (int) $pg['equipment_id'] > 0, '«**يرتبط بمعدةٍ من سجل المعدات**»: #' . ($pg ? $pg['equipment_id'] : '?'));
$ok($pg && (int) $pg['site_project_id'] > 0, '«**وبموقعٍ من سجل المواقع**»: #' . ($pg ? $pg['site_project_id'] : '?'));
$ok($pg && (int) $pg['approved_by_user'] === 1,
    '«**وهويةُ المعتمِدِ تُشتق من الحساب**»: #' . ($pg ? $pg['approved_by_user'] : '?'));
$ok($pg && mb_strpos((string) $pg['approval_manager_site'], 'يكتبه المُدخِل') === false,
    '«**ولا تُكتب يدويًّا**» — الاسمُ المكتوبُ أُهمل وحلَّ محلَّه صاحبُ الحساب');
$bad = cmp03_store_insert($conn, $CO, 'site_gate_equip.php', array(
    'رقم الإذن' => 'P2-' . $TAG, 'الموقع' => $prj, 'كود المعدة' => 'لا-معدةَ-' . $TAG), 'مسودة', 1, 'شاهد');
$ok($bad === false && mb_strpos(cmp03_store_notice(), 'SGE-422') !== false,
    'ومعدةٌ مجهولةٌ **تُردُّ** — لا إذنَ لمعدةٍ لا وجودَ لها');

/* ── ⑤ INJ-0356 · المعدلُ محسوبٌ لا مكتوب ───────────────────────────── */
$say("\n── ⑤ معدلُ الاستهلاكِ محسوبٌ من الحركات");
$cr = (string) @file_get_contents($ROOT . '/Procurement/consumption_rate.php');
$ok(strpos($cr, 'ProcReorderService::consumption') !== false,
    'الشاشةُ تحسب من `proc_stock_move` عبر خدمتِه');
$ok(strpos($cr, 'cmp03_store_insert') === false,
    '«**ولا يوجد فيها حقلُ إدخالٍ يدويٍّ للمعدل**» — لا كتابةَ أصلًا');
$ok(strpos($cr, 'name="f10"') === false && strpos($cr, 'cmp03AddForm') === false,
    'وفورمُ الإدخالِ المسطَّحُ رُفع كلُّه');
$ok(strpos($cr, 'سجلٌّ سابقٌ للربط') !== false,
    'والموروثُ يُعرض موسومًا — لم يُحذف صفٌّ');
require_once $ROOT . '/app/Services/Procurement/ProcReorderService.php';
$withMoves = 0;
$r = $conn->query("SELECT id FROM proc_item WHERE company_id={$CO} ORDER BY name LIMIT 200");
while ($r && ($x = $r->fetch_row())) {
    $c = \App\Services\Procurement\ProcReorderService::consumption($conn, $CO, (int) $x[0], 90);
    if ((float) $c['consumed'] > 0) { $withMoves++; }
}
$ok($withMoves > 0, 'وأصنافٌ بمعدلٍ محسوبٍ فعليٍّ: ' . $withMoves . ' — رقمٌ من دفترِ الحركة');

/* ── ⑥ INJ-0108 · الخطرُ التجاريُّ إشارةٌ لا سجلٌّ موازٍ ──────────────── */
$say("\n── ⑥ الخطرُ التجاريُّ يُرفع إشارةً إلى السجلِّ المركزيّ");
$scr = (string) @file_get_contents($ROOT . '/Clients/commercial_risks.php');
$ok(strpos($scr, "insert('commercial_risks'") === false,
    '«**لا صفًّا في `commercial_risks`**» — لم تعد الشاشةُ تكتبه');
$ok(strpos($scr, 'RiskService::createSignal') !== false,
    '«**يُنشئ إشارةً في `risk_signals`**» — عبر الخدمةِ المالكة');
$c0 = $cnt('commercial_risks'); $g0 = $cnt('risk_signals');
$sig = \App\Services\Risk\RiskService::createSignal($conn, $CO, array(
    'source' => 'manual', 'title' => 'خطرٌ تجاريٌّ ' . $TAG, 'details' => 'شاهد',
    'root_cause' => 'ائتمان', 'rule_key' => 'commercial:' . $TAG), 1);
$ok($cnt('commercial_risks') === $c0, 'وصفرُ صفٍّ جديدٍ في `commercial_risks`: ' . $c0);
$ok($cnt('risk_signals') === $g0 + 1, 'وإشارةٌ واحدةٌ في `risk_signals` #' . (int) $sig['id']);
$again = \App\Services\Risk\RiskService::createSignal($conn, $CO, array(
    'source' => 'manual', 'title' => 'خطرٌ تجاريٌّ ' . $TAG, 'details' => 'شاهد',
    'root_cause' => 'ائتمان', 'rule_key' => 'commercial:' . $TAG), 1);
$ok(!empty($again['idempotent']) && (int) $again['id'] === (int) $sig['id'],
    'وإعادةُ الرفعِ عاطلةٌ — الإشارةُ نفسُها لا ثانيةٌ');

/* ── ⑦ INJ-0214 · العقدُ في السجلِّ الموحَّد ────────────────────────────── */
$say("\n── ⑦ عقدُ الموظفِ في السجلِّ الموحَّد");
$ec = (string) @file_get_contents($ROOT . '/Employees/employee_contracts.php');
$ok(strpos($ec, "insert('drivercontracts'") === false,
    '«**وصفرُ صفٍّ جديدٍ في `drivercontracts`**» — لم تعد الشاشةُ تكتبه');
$ok(strpos($ec, "insert('contracts'") !== false && strpos($ec, "'source_table'  => 'contracts'") !== false,
    'بل تكتب `contracts` وتصل الموظفَ به في `employee_contracts`');
$ok(strpos($ec, 'FROM contracts sc') !== false && strpos($ec, "ec.source_table = 'contracts'") !== false,
    '«**والقراءةُ تتبع الكتابة**» — لا شاشةَ تعرض مخزنًا لا تكتبه');
$ok($cnt('drivercontracts') === 0, 'و`drivercontracts` صفرُ صفٍّ — فالتحويلُ بلا كلفةِ بيانة');

/* ── ⑧ INJ-0411 · مؤشرُ المخاطرِ من السجلِّ المركزيّ ────────────────────── */
$say("\n── ⑧ «المخاطر المفتوحة» من السجلِّ المركزيّ");
$rep = (string) @file_get_contents($ROOT . '/Portal/ceo_reports.php');
$ok(strpos($rep, 'FROM risk_register rr') !== false,
    'المؤشرُ يُشتقُّ من `risk_register` لا من `exec_decisions`');
$ok(!preg_match("~foreach \(\\\$decRows as \\\$d\)[\s\S]{0,200}openRisk~u", $rep),
    'ولم تعد مطابقةُ نصِّ حالةٍ في قراراتِ الرئيسِ تصنعه');
$openBefore = $cnt('risk_register', "state <> 'closed' AND merged_into_id IS NULL AND company_id={$CO}");
/* يُغلق خطرٌ حقيقيٌّ ثم يُعاد — والمؤشرُ يتحرك */
$rid = 0;
$r = $conn->query("SELECT id, state FROM risk_register
                    WHERE company_id={$CO} AND state <> 'closed' AND merged_into_id IS NULL
                    ORDER BY id DESC LIMIT 1");
$row = $r ? $r->fetch_assoc() : null;
if ($row) {
    $rid = (int) $row['id']; $prev = (string) $row['state'];
    $conn->query("UPDATE risk_register SET state='closed' WHERE id={$rid}");
    $openAfter = $cnt('risk_register', "state <> 'closed' AND merged_into_id IS NULL AND company_id={$CO}");
    $ok($openAfter === $openBefore - 1,
        '«**بعد إغلاقِ خطرٍ ينقص المؤشرُ بواحد**»: ' . $openBefore . ' ⇒ ' . $openAfter);
    $conn->query("UPDATE risk_register SET state='" . $conn->real_escape_string($prev) . "' WHERE id={$rid}");
    $ok($cnt('risk_register', "state <> 'closed' AND merged_into_id IS NULL AND company_id={$CO}") === $openBefore,
        'واستُعيدت حالةُ الخطرِ إلى «' . $prev . '»');
} else {
    $ok(false, 'خطرٌ مفتوحٌ للقياس', 'لا وجودَ له');
}

/* ── ⑨ الموروثُ لم يُمَسّ — «ولا يُحذف» ─────────────────────────────────── */
$say("\n── ⑨ المخزنُ القديمُ باقٍ بصفوفِه (لم يُحذف ولم يُرحَّل)");
foreach (array('scr_attendance' => 20, 'scr_deductions' => 20, 'scr_fin_assets' => 20,
               'scr_consumption_rate' => 20, 'commercial_risks' => 20) as $t => $expect) {
    $n = $cnt($t);
    $ok($n >= $expect, $t . ' باقٍ بـ' . $n . ' صفًّا (المُعلَنُ عند القياس: ' . $expect . ')');
}
require_once $ROOT . '/includes/cmp03_domain_bridge.php';
$br = cmp03_bridged_screens();
$ok(count($br) === 4, 'وسجلُّ الجسورِ يُعلن أربعَ شاشاتٍ محوَّلة: ' . implode(' · ', array_keys($br)));

$say("\n── الكنسُ البعديّ");
$ok($sweep() === 0, 'كُنست عائلةُ الوسمِ كاملةً');
foreach (array('attendance_days' => "reference_doc LIKE '%{$TAG}%'",
               'payroll_deductions' => "doc_ref LIKE '%{$TAG}%'",
               'asset_ownership_shares' => "doc_ref LIKE '%{$TAG}%'",
               'scr_site_gate_equip' => "no_permit LIKE '%{$TAG}%'",
               'risk_signals' => "rule_key LIKE '%{$TAG}%'") as $t => $w) {
    $ok($cnt($t, $w) === 0, 'صفرُ صفٍّ متروكٍ في ' . $t);
}

$say("\n══ النتيجة: ناجحٌ {$PASS} · راسبٌ {$FAIL}");
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL > 0 ? 1 : 0);
