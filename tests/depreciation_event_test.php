<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-30 — اختبار قبول: الإهلاكُ حدثًا دوريًّا بمفتاح (أصل × فترة) — SPEC-01 #32
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/depreciation_event_test.php
 *
 * ما يُثبته:
 *   ① **لا إهلاكَ قبل الاقتناء** — والخللُ المقيسُ في الشاشة القديمة يسقط.
 *   ② **والقسطُ محسوبٌ يدويًّا بلا فرق**: (تكلفة − خردة) ÷ عمر.
 *   ③ **ومفتاحُ (أصل × فترة) يمنع التكرار**: تشغيلان ⇒ صفٌّ واحدٌ و**حدثٌ واحد**
 *      (وإعادةُ التشغيل تعيد **مرجعَ الحدث القائم** — عطالةٌ فعليةٌ لا دعوى).
 *   ④ **وقفلُ الفترة يحكم**: فترةٌ مقفلةٌ ⇒ **423 بلا كتابةٍ ولا حدث**.
 *   ⑤ **والاستدراكُ يعمل**: أصلٌ اقتُني قبل أشهرٍ ⇒ قسطٌ لكل شهرٍ **من شهر اقتنائه**.
 *   ⑥ **ولا يتجاوز المتبقّي**: آخرُ قسطٍ **مقصوصٌ** والأصلُ يصير `fully_depreciated`.
 *   ⑦ **والحدثُ بمرجعه**: `expense.depreciation.recorded` بمفتاح `dep:أصل:فترة`.
 *
 * البذرُ معزول: أصولٌ بأكوادٍ موسومةٍ وفتراتٌ في 2088 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '17', 'company_id' => 4, 'name' => 'M30 depreciation test');

require_once dirname(__DIR__) . '/app/Services/Finance/DepreciationService.php';

use App\Services\Finance\DepreciationService as DEP;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999301;
$MARK  = 'M30T' . getmypid();

$teardown = function () use ($conn, $CO, $MARK) {
    $conn->query("DELETE e FROM ems_business_events e
                    JOIN fin_assets a ON a.id = e.entity_id
                   WHERE e.entity_type='fin_asset' AND a.code LIKE '%{$MARK}%'");
    $conn->query("DELETE e FROM fin_financial_events e
                    JOIN fin_assets a ON a.id = e.entity_id
                   WHERE e.entity_type='fin_asset' AND a.code LIKE '%{$MARK}%'");
    $conn->query("DELETE d FROM fin_depreciation d JOIN fin_assets a ON a.id = d.asset_id
                   WHERE a.code LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM fin_assets WHERE code LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM fin_financial_periods WHERE company_id={$CO} AND fiscal_year=2088");
    /* INJ-0033: وملفّاتُ الإهلاكِ المبذورةُ تُكنس بالعائلةِ نفسِها */
    $conn->query("DELETE FROM fleet_depreciation_profile WHERE code LIKE 'DEP-{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-30 — الإهلاكُ حدثًا دوريًّا بمفتاح (أصل × فترة) ══\n");

head('البذر — أصلان: تكلفة 120000 وخردة 0 وعمرٌ 10 شهور (قسطٌ 12000)');

/* ◆ INJ-0033: الملفُّ يطابق **بالفئة**، فلكلِّ أصلٍ فئتُه وملفُّه — وإلا حكم
     ملفٌّ واحدٌ على أعمارٍ ثلاثةٍ مختلفةٍ فاختلّت السيناريوهات. والعمرُ والخردةُ
     في الملفِّ مطابقان لِما كان في حقولِ الأصل، فالأرقامُ المقيسةُ لا تتغيّر. */
$mkAsset = function ($suffix, $cost, $salv, $life, $acq) use ($conn, $CO, $MARK) {
    $cat = 'اختبار-' . $MARK . '-' . $suffix;
    $pct = $cost > 0 ? round($salv / $cost, 4) : 0;
    $conn->query("DELETE FROM fleet_depreciation_profile WHERE code = 'DEP-{$MARK}-{$suffix}'");
    $conn->query("INSERT INTO fleet_depreciation_profile
            (company_id, code, asset_category, method, useful_life, salvage_pct, state, created_at, updated_at)
          VALUES ({$CO}, 'DEP-{$MARK}-{$suffix}', '{$cat}', 'straight_line', {$life}, {$pct}, 'approved', NOW(), NOW())");
    $conn->query("INSERT INTO fin_assets
        (company_id, code, name, category, acquisition_date, acquisition_cost, salvage_value,
         useful_life_months, method, accumulated_depreciation, state, created_at, updated_at)
        VALUES ({$CO}, 'FA-{$MARK}-{$suffix}', 'أصلُ {$MARK}-{$suffix}', '{$cat}',
                '{$acq}', {$cost}, {$salv}, {$life}, 'straight_line', 0, 'active', NOW(), NOW())");
    if ($conn->error) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); return 0; }
    return intval($conn->insert_id);
};
$A1 = $mkAsset('A', 120000, 0, 10, '2088-03-15');
$A2 = $mkAsset('B', 60000, 0, 5, '2088-01-10');
check($A1 > 0 && $A2 > 0, "أصلان #{$A1} (اقتناءُ 2088-03) و#{$A2} (اقتناءُ 2088-01)");

$assetOf = function ($id) use ($conn) {
    return $conn->query("SELECT * FROM fin_assets WHERE id=" . intval($id))->fetch_assoc();
};

// ═══ ① لا إهلاكَ قبل الاقتناء ═══
head('① **لا إهلاكَ قبل الاقتناء** — والخللُ المقيسُ في الشاشة القديمة يسقط');
$c = DEP::computeFor($assetOf($A1), '2088-02');
check(!$c['ok'] && $c['code'] === 422 && mb_strpos($c['reason'], 'قبل شهر الاقتناء') !== false,
      '★★ فترةُ 2088-02 لأصلٍ اقتُني في 2088-03 ⇒ **422** — «لا إهلاكَ لما لم يُقتنَ بعد»');

// ═══ ② القسطُ يدويًّا ═══
head('② **والقسطُ محسوبٌ يدويًّا بلا فرق**');
$c = DEP::computeFor($assetOf($A1), '2088-03');
check($c['ok'] && abs($c['amount'] - 12000.0) < 0.005,
      '★ (120000 − 0) ÷ 10 = **12000**');
check(abs((float) $c['basis']['depreciable'] - 120000.0) < 0.005
      && (int) $c['basis']['life_months'] === 10 && !$c['basis']['clamped'],
      'واللقطةُ تحمل الأساسَ كاملًا (قابلٌ للإهلاك · عمرٌ · مجمّعٌ قبله)');

// ═══ ③ المفتاحُ يمنع التكرار ═══
head('③ **ومفتاحُ (أصل × فترة) يمنع التكرار** — صفًّا وحدثًا');
$SCOPE = array($A1, $A2);
$r = DEP::runPeriod($conn, $gate, $CO, '2088-03', $ACTOR, 'screen', $SCOPE);
check($r['ok'] && $r['posted'] === 2 && abs($r['total'] - 24000.0) < 0.005,
      'الفترةُ 2088-03: قسطان (12000 + 12000) — الأصلان معًا داخل النطاق المسمّى');
$rowN = intval($conn->query("SELECT COUNT(*) c FROM fin_depreciation d JOIN fin_assets a ON a.id=d.asset_id
                              WHERE a.code LIKE '%{$MARK}%' AND d.period_ref='2088-03'")
                     ->fetch_assoc()['c']);
check($rowN === 2, 'صفّان (الأصلان معًا) للفترة 2088-03 — العدد: ' . $rowN);

$evN = intval($conn->query("SELECT COUNT(*) c FROM fin_financial_events e JOIN fin_assets a ON a.id=e.entity_id
                             WHERE e.entity_type='fin_asset' AND a.code LIKE '%{$MARK}%'
                               AND e.idempotency_key LIKE 'dep:%:2088-03'")->fetch_assoc()['c']);
check($evN === 2, '★ وحدثان منشوران بمفتاحَيهما — العدد: ' . $evN);

$r2 = DEP::runPeriod($conn, $gate, $CO, '2088-03', $ACTOR, 'screen', $SCOPE);
check($r2['ok'] && $r2['posted'] === 0 && count($r2['skipped']) >= 2,
      '★★ إعادةُ التشغيل: **صفرُ قسطٍ جديد** والكلُّ متخطًّى بسببه');
$rowN2 = intval($conn->query("SELECT COUNT(*) c FROM fin_depreciation d JOIN fin_assets a ON a.id=d.asset_id
                               WHERE a.code LIKE '%{$MARK}%' AND d.period_ref='2088-03'")
                      ->fetch_assoc()['c']);
$evN2 = intval($conn->query("SELECT COUNT(*) c FROM fin_financial_events e JOIN fin_assets a ON a.id=e.entity_id
                              WHERE e.entity_type='fin_asset' AND a.code LIKE '%{$MARK}%'
                                AND e.idempotency_key LIKE 'dep:%:2088-03'")->fetch_assoc()['c']);
check($rowN2 === 2 && $evN2 === 2,
      '★★ وبعد التشغيلين: **صفّان وحدثان** لا أربعة — العطالةُ فعليةٌ لا دعوى');

// ═══ ⑦ الحدثُ بمرجعه ═══
head('⑦ **والحدثُ بمرجعه ومفتاحه**');
$ev = $conn->query("SELECT e.event_key, e.idempotency_key, e.amount, e.event_type, e.source_module
                      FROM fin_financial_events e
                     WHERE e.entity_type='fin_asset' AND e.entity_id={$A1}
                       AND e.idempotency_key='dep:{$A1}:2088-03'")->fetch_assoc();
check($ev && (string) $ev['event_key'] === 'expense.depreciation.recorded'
      && (string) $ev['source_module'] === 'assets' && abs((float) $ev['amount'] - 12000.0) < 0.005,
      '★★ `expense.depreciation.recorded` بمفتاح **dep:' . $A1 . ':2088-03** ومبلغه 12000');
$link = $conn->query("SELECT event_id, source, method FROM fin_depreciation
                       WHERE asset_id={$A1} AND period_ref='2088-03'")->fetch_assoc();
check($link && (int) $link['event_id'] > 0 && (string) $link['source'] === 'screen'
      && (string) $link['method'] === 'straight_line',
      '★ والصفُّ يحمل **مرجعَ حدثه** ومصدرَه وطريقتَه — يُقرأ بالاتجاهين');

// ═══ ④ قفلُ الفترة ═══
head('④ **وقفلُ الفترة يحكم** — 423 بلا كتابةٍ ولا حدث');
$conn->query("INSERT INTO fin_financial_periods
              (company_id, fiscal_year, period_type, period_no, start_date, end_date,
               state, posting_allowed, created_at)
              VALUES ({$CO}, 2088, 'month', 4, '2088-04-01', '2088-04-30', 'closed', 0, NOW())");
if ($conn->error) { fwrite(STDOUT, '  ! period: ' . $conn->error . "\n"); }
$r = DEP::runPeriod($conn, $gate, $CO, '2088-04', $ACTOR, 'screen', $SCOPE);
check(!$r['ok'] && $r['code'] === 423 && mb_strpos($r['reason'], 'مقفلة') !== false,
      '★★ فترةٌ مقفلةٌ ⇒ **423** — ' . mb_substr($r['reason'], 0, 60));
$n = intval($conn->query("SELECT COUNT(*) c FROM fin_depreciation d JOIN fin_assets a ON a.id=d.asset_id
                           WHERE a.code LIKE '%{$MARK}%' AND d.period_ref='2088-04'")->fetch_assoc()['c']);
check($n === 0, 'وصفرُ صفٍّ كُتب في الفترة المقفلة');
$conn->query("UPDATE fin_financial_periods SET state='open', posting_allowed=1
               WHERE company_id={$CO} AND fiscal_year=2088");

// ═══ ⑤ الاستدراك ═══
head('⑤ **والاستدراكُ يسدّ الفجوة من شهر الاقتناء**');
// عمرُ الأصل 5 شهورٍ من 2088-01 فينتهي في **2088-05** — و06 خارجَ عمره لا
// شهرٌ ناقص. و03 محتسَبٌ سلفًا. فالناقصُ أربعةٌ: 01 · 02 · 04 · 05.
$miss = DEP::missingPeriods($gate, $assetOf($A2), '2088-06');
check(count($miss) === 4 && $miss[0] === '2088-01' && !in_array('2088-06', $miss, true)
      && !in_array('2088-03', $miss, true),
      '★★ الأصلُ #' . $A2 . ' ينقصه **أربعةُ شهور** — و**ما بعد العمر الإنتاجي ليس فجوة**: '
      . implode(' · ', $miss));

$r = DEP::catchUp($conn, $gate, $CO, $ACTOR, '2088-06', 'cron', array($A2));
check($r['ok'], 'الاستدراك: ' . $r['reason']);
$miss2 = DEP::missingPeriods($gate, $assetOf($A2), '2088-06');
check(count($miss2) === 0, '★★ وبعده **صفرُ شهرٍ ناقص** للأصل ' . $A2);

$sum = $conn->query("SELECT ROUND(SUM(depreciation_amount),2) s, COUNT(*) c FROM fin_depreciation
                      WHERE asset_id={$A2}")->fetch_assoc();
check((int) $sum['c'] === 5 && abs((float) $sum['s'] - 60000.0) < 0.005,
      '★★ و**خمسةُ أقساطٍ بمجموع 60000** = كلُّ القيمة القابلة للإهلاك (12000 × 5)');

// ═══ ⑥ ولا يتجاوز المتبقّي ═══
head('⑥ **ولا يتجاوز المتبقّي** — والأصلُ يصير مُهلَكًا بالكامل');
$a2 = $assetOf($A2);
check((string) $a2['state'] === 'fully_depreciated'
      && abs((float) $a2['accumulated_depreciation'] - 60000.0) < 0.005,
      '★★ الأصلُ #' . $A2 . ' **`fully_depreciated`** ومجمّعُه 60000 — لا يتجاوز ولا ينقص');
$c = DEP::computeFor($assetOf($A2), '2088-07');
check(!$c['ok'] && $c['code'] === 409 && mb_strpos($c['reason'], 'ولا تُهلَك خردة') !== false,
      '★ وقسطٌ بعد الاستيفاء ⇒ **409** — «الأصلُ مُهلَكٌ بالكامل»');

// آخرُ قسطٍ مقصوص: أصلٌ عمرُه 3 وقسطُه 3333.33 والمتبقّي في الثالث 3333.34
$A3 = $mkAsset('C', 10000, 0, 3, '2088-01-05');
DEP::runPeriod($conn, $gate, $CO, '2088-01', $ACTOR, 'cron', array($A3));
DEP::runPeriod($conn, $gate, $CO, '2088-02', $ACTOR, 'cron', array($A3));
$c = DEP::computeFor($assetOf($A3), '2088-03');
check($c['ok'] && abs($c['amount'] - 3333.34) < 0.005 && $c['basis']['is_final'] === true,
      '★★ وآخرُ قسطٍ **3333.34 يستوعب فرقَ التقريب** (لا 3333.33) — القرشُ لا يبقى يتيمًا');
DEP::runPeriod($conn, $gate, $CO, '2088-03', $ACTOR, 'cron', array($A3));
$s3 = $conn->query("SELECT ROUND(SUM(depreciation_amount),2) s FROM fin_depreciation
                     WHERE asset_id={$A3}")->fetch_assoc();
check(abs((float) $s3['s'] - 10000.0) < 0.005,
      '★★ ومجموعُ الأقساط الثلاثة **10000 بالضبط** = التكلفةُ القابلةُ للإهلاك');

// ═══ العزل ═══
head('العزلُ محفوظ');
$_SESSION['user']['company_id'] = 1;
$otherGate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::fromSession());
$leak = $otherGate->selectOne('fin_assets', array('where' => array('id' => $A1)));
check($leak === null, '★ أصلُ شركةٍ لا يُقرأ من نطاقٍ آخر — صفرُ تسريب');
$_SESSION['user']['company_id'] = $CO;

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
