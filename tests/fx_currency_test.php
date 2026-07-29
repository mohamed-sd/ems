<?php
/**
 * tests/fx_currency_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 * حارس أساس العملة (FES-01 §3.1 «سعرُ الصرف لحظةَ الحدث» · §3.3 «ثلاثيةُ
 * العملة: عملة · سعر · معادلٌ موحّد» · UX-02 §10 «المعادلُ الموحّد بسعر يومه»).
 *
 * الحرّاسُ الستة:
 *   ① عملةُ الأساس مشتقّةٌ لا مكتوبة — من admin_companies إلى سجل العملات
 *   ② التطبيع: رمزٌ · اسمٌ عربي · اسمُ طبقة العقود («جنيه») · والمجهولُ null
 *   ③ السعرُ بتاريخه: آخرُ سريانٍ سابقٍ أو مساوٍ — وقبل أوّلِه لا سعرَ
 *   ④ المعادل = ROUND(amount × rate, 2) ضربًا لا قسمة
 *   ⑤ قاعدةُ عدم التلفيق: بلا سعرٍ لا يُحسب معادلٌ — ok=false بسببه
 *   ⑥ الحارسُ البنيوي: عملةٌ غير مسجَّلةٍ تُرفض في الذمم والدفتر
 *
 * يبذر عملةً وهميةً بعيدةً ويكنسها — لا يمسّ عملةً ولا سعرًا قائمًا.
 * التشغيل: php tests/fx_currency_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '17', 'company_id' => 4, 'name' => 'fx test');
require_once dirname(__DIR__) . '/includes/fx.php';

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$CO   = 4;
$TEST = 'XTS';                       // رمزٌ وهميٌّ لا وجودَ له في أي بيانات

// كنسٌ استباقيٌّ من تشغيلٍ سابقٍ متعثّر
$conn->query("DELETE FROM fin_fx_rates   WHERE currency_code = '{$TEST}'");
$conn->query("DELETE FROM fin_currencies WHERE code = '{$TEST}'");

// ═══════════════════════════════════════════════════════════════════════════
head('① عملةُ الأساس مشتقّةٌ من إعلان الشركة لا مكتوبةً بيد');

$declared = null;
$r = $conn->query("SELECT currency FROM admin_companies WHERE id = {$CO}");
if ($row = $r->fetch_assoc()) { $declared = trim((string) $row['currency']); }

$base = ems_fx_base_currency();
check($base !== null, 'لعملة الأساس رمزٌ مسجَّل');
check($base === $declared,
    "وهي نفسُها المعلَنة في admin_companies (متوقَّع {$declared} · وُجد " . var_export($base, true) . ')');

$r = $conn->query("SELECT COUNT(*) c FROM fin_currencies WHERE company_id={$CO} AND is_base=1 AND COALESCE(is_deleted,0)=0");
$baseCount = intval($r->fetch_assoc()['c']);
check($baseCount === 1, "وواحدةٌ لا أكثر (وُجد {$baseCount})");

$rate = ems_fx_rate($base, date('Y-m-d'));
check($rate !== null && abs($rate - 1.0) < 0.00000001,
    'ونسبتُها إلى نفسها واحدٌ أبدًا');

// ═══════════════════════════════════════════════════════════════════════════
head('② التطبيع: من لغة العقود إلى لغة المال');

check(ems_fx_code('USD') === 'USD',            'الرمزُ المسجَّل يمرّ كما هو');
check(ems_fx_code('usd') === 'USD',            'ولا حساسيةَ لحالة الأحرف');
check(ems_fx_code('الدولار الأمريكي') === 'USD', 'والاسمُ العربي كما سُجّل يُعرَف');
check(ems_fx_code('جنيه')  === 'SDG',          '★ واسمُ طبقة العقود «جنيه» → SDG');
check(ems_fx_code('دولار') === 'USD',          '★ و«دولار» → USD (نفسُ خريطة المروحة)');
check(ems_fx_code('روبية') === null,           'وعملةٌ لا تُعرف تُرجع null — لا افتراضَ صامت');
check(ems_fx_code('')      === null,           'والفراغُ كذلك');

// الاتساقُ مع المروحة: الخريطتان لا تتفرّقان
require_once dirname(__DIR__) . '/app/Services/EffectFanout.php';
$fanoutMap = \App\Services\EffectFanout::CONTRACT_CURRENCY;
$agree = true;
foreach ($fanoutMap as $label => $code) {
    $mine = ems_fx_code($label);
    // ما لم تُسجَّل عملتُه بعد (EUR · SAR) يرجع null — والمسجَّلُ يجب أن يطابق
    if ($mine !== null && $mine !== $code) { $agree = false; }
}
check($agree, 'ولا تخالف خريطةَ المروحة في حرفٍ واحد');

// ═══════════════════════════════════════════════════════════════════════════
head('②-ب قراءةُ الكسر العشري كما يكتبه مستخدمٌ عربي');

check(ems_parse_decimal('0.00166667') === 0.00166667, 'النقطةُ العشرية تُقرأ');
check(ems_parse_decimal('0,0017')     === 0.0017,     '★ والفاصلةُ اللاتينية تُقرأ كسرًا لا تُرفض');
check(ems_parse_decimal('0٫0017')     === 0.0017,     '★ والفاصلةُ العشرية العربية ٫ كذلك');
check(ems_parse_decimal('٠٫٠٠١٧')     === 0.0017,     '★ والأرقامُ الهندية ٠١٢ تُقرأ');
check(ems_parse_decimal('1٬234.5')    === 1234.5,     'وفاصلةُ الآلاف ٬ تُسقط');
check(ems_parse_decimal('1 234.5')    === 1234.5,     'والمسافةُ فاصلةَ آلافٍ كذلك');
check(ems_parse_decimal('.5')         === 0.5,        'وكسرٌ بلا صِفرٍ قبله');
check(ems_parse_decimal('600')        === 600.0,      'والصحيحُ يبقى صحيحًا');
check(ems_parse_decimal('abc')        === null,       'وما ليس رقمًا يُرجع null لا صفرًا');
check(ems_parse_decimal('')           === null,       'والفراغُ كذلك');
check(ems_parse_decimal('1.2.3')      === null,       'ونقطتان تُرفضان — لا تُبتر صامتةً');

// ═══════════════════════════════════════════════════════════════════════════
head('③ السعرُ بتاريخه — لا سعرٌ واحدٌ للأبد');

$conn->query("INSERT INTO fin_currencies (company_id, code, name_ar, symbol, decimals, is_base, active, sort_order, created_at)
              VALUES ({$CO}, '{$TEST}', 'عملةُ اختبار', 'X', 2, 0, 1, 99, NOW())");
$conn->query("INSERT INTO fin_fx_rates (company_id, currency_code, rate_to_base, effective_from, source, created_at)
              VALUES ({$CO}, '{$TEST}', 2.00000000, '2090-01-01', 'test', NOW())");
$conn->query("INSERT INTO fin_fx_rates (company_id, currency_code, rate_to_base, effective_from, source, created_at)
              VALUES ({$CO}, '{$TEST}', 5.00000000, '2090-06-01', 'test', NOW())");
// العملةُ سُجّلت بعد أوّلِ قراءةٍ للذاكرة المؤقتة — تُسقَط لتُقرأ من جديد
// (وهو نفسُ ما تفعله شاشةُ العملات بعد كل حفظ).
ems_fx_cache_clear();
check(ems_fx_code($TEST) === $TEST, 'العملةُ المسجَّلةُ لتوّها تُعرَف بعد إسقاط الذاكرة');

check(ems_fx_rate($TEST, '2089-12-31') === null,
    '★ قبل أوّلِ سريانٍ: لا سعرَ — ولا يُستعار سعرُ ما بعده');
check(ems_fx_rate($TEST, '2090-01-01') == 2.0, 'ويومَ السريان نفسِه يسري (شاملٌ لا حصري)');
check(ems_fx_rate($TEST, '2090-05-31') == 2.0, 'ويبقى حتى آخرِ يومٍ قبل التالي');
check(ems_fx_rate($TEST, '2090-06-01') == 5.0, '★ والتالي يسري من يومه — لا من غدِه');
check(ems_fx_rate($TEST, '2099-12-31') == 5.0, 'وآخرُ سعرٍ يمتدّ لما بعده');

// ═══════════════════════════════════════════════════════════════════════════
head('④ المعادلُ ضربًا لا قسمة، بتقريبِ خانتين');

$res = ems_fx_to_base(100, $TEST, '2090-02-01');
check($res['ok'] === true,            'المعادلُ يُحسب حين يوجد سعرُه');
check($res['code'] === $TEST,         'ويحمل الرمزَ المطبَّع');
check($res['rate'] == 2.0,            'وسعرَ يومِه');
check($res['base'] == 200.00,         '★ 100 × 2 = 200 — ضربًا لا قسمة');

$res = ems_fx_to_base(33.333, $TEST, '2090-06-15');
check($res['base'] == 166.67,         'والتقريبُ خانتان (33.333 × 5 = 166.665 → 166.67)');

$res = ems_fx_to_base(1000, 'USD', date('Y-m-d'));
check($res['ok'] && $res['base'] == 1000.00,
    'وما كان بعملة الأساس فمعادلُه نفسُه');

// ═══════════════════════════════════════════════════════════════════════════
head('⑤ قاعدةُ عدم التلفيق: بلا سعرٍ لا معادل');

$res = ems_fx_to_base(500, $TEST, '2089-01-01');
check($res['ok'] === false,           '★ تاريخٌ قبل أيِّ سعرٍ: لا يُحسب');
check($res['base'] === null,          'ولا يُرجع رقمًا البتة');
check(strpos($res['reason'], 'سعر') !== false, 'والسببُ معلَنٌ نصًّا لا صامت');

$res = ems_fx_to_base(500, 'روبية', date('Y-m-d'));
check($res['ok'] === false && strpos($res['reason'], 'غير مسجَّلة') !== false,
    'وعملةٌ غير مسجَّلةٍ تُعلَن كذلك');

// الصفوفُ الجنيهيةُ الحيّة: بلا سعرٍ بعد ⇒ معادلُها فارغٌ لا صفر
$r = $conn->query("SELECT COUNT(*) c FROM fin_dues
                    WHERE company_id={$CO} AND currency='SDG' AND base_amount IS NOT NULL");
$sdgValued = intval($r->fetch_assoc()['c']);
$hasSdgRate = (ems_fx_rate('SDG', date('Y-m-d')) !== null);
check($hasSdgRate || $sdgValued === 0,
    '★ وما دام الجنيهُ بلا سعرٍ فلا ذمّةَ جنيهيةٌ تحمل معادلًا مخترَعًا');

// ═══════════════════════════════════════════════════════════════════════════
head('⑥ الحارسُ البنيوي: لا حركةَ بعملةٍ غير مسجَّلة');

// config.php يضبط mysqli_report(MYSQLI_REPORT_OFF) — فالاستعلامُ الفاشل يرجع
// false ويضع errno، ولا يرمي استثناءً. الفحصُ على 1452 (خرقُ مفتاحٍ أجنبي).
const FK_VIOLATION = 1452;

$conn->query("INSERT INTO fin_dues (company_id, party_type, party_ref, due_type, direction,
              amount, currency, created_at)
              VALUES ({$CO}, 'supplier', '1', 'hours', 'credit', 1.00, 'ZZZ', NOW())");
check($conn->errno === FK_VIOLATION,
    '★ ذمّةٌ بعملةٍ غير مسجَّلة تُرفض بنيويًّا لا بفحصٍ لاحق (errno=' . $conn->errno . ')');

$conn->query("INSERT INTO ems_business_events (company_id, event_no, event_uuid, event_key,
              category, source_module, entity_type, entity_id, amount, currency,
              event_status, occurred_at, created_at)
              VALUES ({$CO}, 'FXTEST-1', 'FXTESTUUID0000000000000001', 'fx:test:1',
              'revenue', 'test', 'test', 1, 5.00, 'ZZZ', 'recorded', NOW(), NOW())");
check($conn->errno === FK_VIOLATION,
    'والدفترُ كذلك — الحارسُ على الاثنين (errno=' . $conn->errno . ')');

// NULL يمرّ على الدفتر: «مجهولةٌ» حالةٌ مشروعةٌ يعرفها المفتاحُ الأجنبي — وهي
// حالُ الصفوف الثلاثة التي كانت تحمل نصًّا عشوائيًّا فطُهّرت.
// (`fin_dues.currency` بخلافه: NOT NULL DEFAULT 'SDG' — فلا يقبل المجهول أصلًا،
//  وذلك افتراضٌ صامتٌ قائمٌ قبل هذا العمل ومرفوعٌ للمالك.)
$conn->query("INSERT INTO ems_business_events (company_id, event_no, event_uuid, event_key,
              category, source_module, entity_type, entity_id, amount, currency,
              event_status, occurred_at, created_at)
              VALUES ({$CO}, 'FXTEST-2', 'FXTESTUUID0000000000000002', 'fx:test:2',
              'revenue', 'test', 'test', 1, 5.00, NULL, 'recorded', NOW(), NOW())");
$nullOk = ($conn->errno === 0);
check($nullOk, 'والعملةُ المجهولة (NULL) تمرّ — فالمجهولُ مشروعٌ والمخترَعُ مرفوض');
$conn->query("DELETE FROM ems_business_events WHERE event_no LIKE 'FXTEST-%'");

// ═══════════════════════════════════════════════════════════════════════════
head('⑦ الكنس');

$conn->query("DELETE FROM fin_fx_rates   WHERE currency_code = '{$TEST}'");
$conn->query("DELETE FROM fin_currencies WHERE code = '{$TEST}'");
$r = $conn->query("SELECT COUNT(*) c FROM fin_currencies WHERE code='{$TEST}'");
check(intval($r->fetch_assoc()['c']) === 0, 'لا أثرَ لعملة الاختبار');
$r = $conn->query("SELECT COUNT(*) c FROM ems_business_events WHERE event_no LIKE 'FXTEST-%'");
check(intval($r->fetch_assoc()['c']) === 0, 'ولا حدثَ اختبارٍ في الدفتر');

fwrite(STDOUT, "\n══════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
