<?php
/**
 * اختبارات محرّك تفريع الأثر — EffectFanout (D05 §6.1)
 * القواعد الأربع: خريطة الآثار · الذرّية · الربط الأبوي/العطالة · وحدةٌ واحدة.
 * + عدم التلفيق (المعطّل يُعلن skipped) + التبنّي (لا ازدواج مال).
 * التشغيل: php tests/effect_fanout_test.php — رمز الخروج 0/1.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/EffectFanout.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\EffectFanout;

$PASS = 0; $FAIL = 0;
function ok($label, $cond) {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "  ✔ {$label}\n"; }
    else { $FAIL++; echo "  ✘ FAIL: {$label}\n"; }
}

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_APP_USER'), ems_env('DB_APP_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$conn->set_charset('utf8mb4');

$CO = 4;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, 0, '', true));
$MARK = 'FANOUT_TEST_' . getmypid();

// عدّادات خط الأساس
function counts($conn) {
    return array(
        'events' => intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]),
        'dues'   => intval($conn->query("SELECT COUNT(*) FROM fin_dues")->fetch_row()[0]),
        'costs'  => intval($conn->query("SELECT COUNT(*) FROM fin_cost_records")->fetch_row()[0]),
        'links'  => intval($conn->query("SELECT COUNT(*) FROM fin_event_links")->fetch_row()[0]),
        'roots'  => intval($conn->query("SELECT COUNT(*) FROM ems_business_events")->fetch_row()[0]),
    );
}
$c0 = counts($conn);
$unitIds = array();

/** إنشاء وحدةٍ معتمدةٍ خامًا (بلا توأمٍ قديم — مروحةٌ من الصفر). */
function make_unit($conn, $CO, $MARK, $model, $qty, $cprice, $sprice, $sup = 1) {
    $no = $MARK . '_' . substr(md5($model . $qty . microtime(true)), 0, 6);
    $stmt = $conn->prepare(
        "INSERT INTO fin_unit_records (company_id, record_no, record_date, project_id, equipment_id, supplier_entity_id,
         work_model, ops_qty, approved_qty, client_unit_price, supplier_unit_price, match_state, created_by)
         VALUES (?, ?, '2026-07-10', 1, 1, ?, ?, ?, ?, ?, ?, 'approved', 999901)"
    );
    $stmt->bind_param('isisdddd', $CO, $no, $sup, $model, $qty, $qty, $cprice, $sprice);
    $stmt->execute();
    $id = intval($conn->insert_id);
    $stmt->close();
    return $id;
}

try {

echo "── 1) المروحة الكاملة من الصفر (ساعة): إيراد + مستحق + تكلفة ──\n";
$u1 = make_unit($conn, $CO, $MARK, 'hour', 10, 3500, 2200);
$unitIds[] = $u1;
$res1 = null;
$gate->runInTransaction(function ($g) use (&$res1, $conn, $u1) {
    $u = $g->selectOne('fin_unit_records', array('where' => array('id' => $u1)));
    $res1 = EffectFanout::forUnitRecord($conn, $g, $u, 72);
});
ok('توليد 3 آثارٍ حقيقية (إيراد+مستحق+تكلفة)', count($res1['effects']) === 3);
ok('إعلان أثرين غير متاحين (مشغّل+صيانة) — لا تلفيق', count($res1['skipped']) === 2);
$rev = $conn->query("SELECT amount FROM fin_financial_events WHERE entity_type='fin_unit_record' AND entity_id=$u1 AND event_key='revenue.unit.recognized'")->fetch_assoc();
ok('الإيراد = 10 × 3500 = 35000', $rev && floatval($rev['amount']) === 35000.00);
$due = $conn->query("SELECT amount, due_type FROM fin_dues WHERE id=(SELECT target_id FROM fin_event_links WHERE parent_kind='unit_record' AND parent_ref=$u1 AND effect_type='supplier_due')")->fetch_assoc();
ok('مستحق المورد = 10 × 2200 = 22000 بوحدة hours', $due && floatval($due['amount']) === 22000.00 && $due['due_type'] === 'hours');
$cost = $conn->query("SELECT total_cost, revenue, profit, unit FROM fin_cost_records WHERE id=(SELECT target_id FROM fin_event_links WHERE parent_kind='unit_record' AND parent_ref=$u1 AND effect_type='cost_record')")->fetch_assoc();
ok('التكلفة = 22000 · الإيراد = 35000 · الربح المحسوب = 13000', $cost
    && floatval($cost['total_cost']) === 22000.00 && floatval($cost['revenue']) === 35000.00 && floatval($cost['profit']) === 13000.00);
ok('وحدةٌ واحدةٌ للمروحة (hour في كل الآثار)', $cost['unit'] === 'hour');

echo "── 2) العطالة (§6.1 ③): إعادة التشغيل لا تُكرّر أثرًا ──\n";
$before = counts($conn);
$res2 = null;
$gate->runInTransaction(function ($g) use (&$res2, $conn, $u1) {
    $u = $g->selectOne('fin_unit_records', array('where' => array('id' => $u1)));
    $res2 = EffectFanout::forUnitRecord($conn, $g, $u, 72);
});
$after = counts($conn);
ok('إعادة التشغيل: صفر أثرٍ جديد', count($res2['effects']) === 0 && count($res2['adopted']) === 0);
ok('العدّادات ثابتة (لا ازدواج مال)', $after['events'] === $before['events'] && $after['dues'] === $before['dues'] && $after['costs'] === $before['costs'] && $after['links'] === $before['links']);

echo "── 3) الذرّية (§6.1 ②): فشلٌ وسط المروحة ⇒ لا أثرٍ يتيم ──\n";
$u3 = make_unit($conn, $CO, $MARK, 'ton', 100, 600, 380);
$unitIds[] = $u3;
$before3 = counts($conn);
$threw = false;
try {
    $gate->runInTransaction(function ($g) use ($conn, $u3) {
        $u = $g->selectOne('fin_unit_records', array('where' => array('id' => $u3)));
        EffectFanout::forUnitRecord($conn, $g, $u, 72);
        // حقن فشلٍ متعمّدٍ بعد توليد المروحة وقبل الـcommit
        if (!$g->selectOne('fin_financial_events', array('where' => array('id' => 999999999)))) {
            throw new \RuntimeException('حقن فشلٍ متعمّد لاختبار الذرّية');
        }
    });
} catch (\Throwable $e) { $threw = true; }
$after3 = counts($conn);
ok('المعاملة تراجعت (استثناءٌ حُقن)', $threw);
ok('صفر أثرٍ يتيم بعد التراجع (كل العدّادات كما كانت)',
    $after3['events'] === $before3['events'] && $after3['dues'] === $before3['dues']
    && $after3['costs'] === $before3['costs'] && $after3['links'] === $before3['links']);

echo "── 4) التبنّي (لا ازدواج مال): توأمٌ قديمٌ يُربط ولا يُضاعف ──\n";
// وحدةٌ بتوأمين مختومين سلفًا (يحاكي المسار القديم)
$rev_id = $gate->insert('fin_financial_events', array(
    'event_no' => 'FANOUT-OLD-' . getmypid(), 'event_type' => 'revenue', 'source_module' => 'projects',
    'source_ref' => $MARK, 'amount' => 51000, 'currency' => 'SDG', 'state' => 'draft', 'created_by' => 999901,
));
$due_id = $gate->insert('fin_dues', array(
    'party_type' => 'supplier', 'party_ref' => 1, 'due_type' => 'meters', 'direction' => 'credit',
    'amount' => 34000, 'currency' => 'SDG', 'event_id' => $rev_id, 'created_by' => 999901,
));
$u4 = make_unit($conn, $CO, $MARK, 'meter', 85, 600, 400);
$unitIds[] = $u4;
$conn->query("UPDATE fin_unit_records SET revenue_event_id=$rev_id, supplier_due_id=$due_id WHERE id=$u4");
$eventsBeforeAdopt = counts($conn)['events'];
$duesBeforeAdopt = counts($conn)['dues'];
$res4 = null;
$gate->runInTransaction(function ($g) use (&$res4, $conn, $u4) {
    $u = $g->selectOne('fin_unit_records', array('where' => array('id' => $u4)));
    $res4 = EffectFanout::forUnitRecord($conn, $g, $u, 72);
});
$adoptedTypes = array_map(function ($a) { return $a['effect']; }, $res4['adopted']);
ok('التوأمان تُبنّيا (revenue_event + supplier_due)', in_array('revenue_event', $adoptedTypes, true) && in_array('supplier_due', $adoptedTypes, true));
ok('لا حدثَ إيرادٍ جديد (تبنٍّ لا ازدواج)', counts($conn)['events'] === $eventsBeforeAdopt);
ok('لا مستحقَ موردٍ جديد (تبنٍّ لا ازدواج)', counts($conn)['dues'] === $duesBeforeAdopt);
$adoptRev = $conn->query("SELECT target_id FROM fin_event_links WHERE parent_kind='unit_record' AND parent_ref=$u4 AND effect_type='revenue_event'")->fetch_assoc();
ok('رابط التبنّي يشير للتوأم القديم نفسه', $adoptRev && intval($adoptRev['target_id']) === $rev_id);
ok('التكلفة الجديدة تولّدت رغم تبنّي التوأمين', count($res4['effects']) >= 1);

echo "── 5) الخريطة التصريحية: تفعيل مخصّص الصيانة يولّد أثرًا خامسًا ──\n";
$conn->query("UPDATE fin_effect_map SET is_active=1, param_value=50 WHERE company_id=$CO AND source_kind='unit_record' AND effect_type='metric_update'");
$u5 = make_unit($conn, $CO, $MARK, 'hour', 8, 3500, 2200);
$unitIds[] = $u5;
$res5 = null;
$gate->runInTransaction(function ($g) use (&$res5, $conn, $u5) {
    $u = $g->selectOne('fin_unit_records', array('where' => array('id' => $u5)));
    $res5 = EffectFanout::forUnitRecord($conn, $g, $u, 72);
});
$hasProvision = false;
foreach ($res5['effects'] as $e) { if ($e['effect'] === 'metric_update') { $hasProvision = true; } }
ok('القاعدة بياناتٌ لا كود: تفعيل الخريطة ولّد مخصّص الصيانة (8 × 50 = 400)', $hasProvision);
$prov = $conn->query("SELECT total_cost FROM fin_cost_records WHERE cost_type='maintenance' AND id=(SELECT target_id FROM fin_event_links WHERE parent_kind='unit_record' AND parent_ref=$u5 AND effect_type='metric_update')")->fetch_assoc();
ok('مخصّص الصيانة = 8 × 50 = 400', $prov && floatval($prov['total_cost']) === 400.00);
// إرجاع الخريطة لحالتها
$conn->query("UPDATE fin_effect_map SET is_active=0, param_value=0 WHERE company_id=$CO AND source_kind='unit_record' AND effect_type='metric_update'");

} catch (\Throwable $t) {
    $FAIL++;
    echo "  ✘ استثناء غير متوقع: " . $t->getMessage() . "\n" . $t->getTraceAsString() . "\n";
}

// ── تنظيف كامل ──
foreach ($unitIds as $uid) {
    $conn->query("DELETE FROM fin_cost_records WHERE id IN (SELECT target_id FROM fin_event_links WHERE parent_kind='unit_record' AND parent_ref=$uid AND target_table='fin_cost_records')");
    $conn->query("DELETE FROM fin_dues WHERE id IN (SELECT target_id FROM fin_event_links WHERE parent_kind='unit_record' AND parent_ref=$uid AND target_table='fin_dues')");
    $conn->query("DELETE FROM fin_financial_events WHERE id IN (SELECT target_id FROM fin_event_links WHERE parent_kind='unit_record' AND parent_ref=$uid AND target_table='fin_financial_events')");
    $conn->query("DELETE FROM fin_event_links WHERE parent_kind='unit_record' AND parent_ref=$uid");
    $conn->query("DELETE FROM fin_unit_records WHERE id=$uid");
}
$conn->query("DELETE FROM fin_financial_events WHERE source_ref='$MARK' OR notes LIKE '%$MARK%'");
$conn->query("DELETE FROM fin_dues WHERE created_by=999901 AND period_ref IS NULL AND event_id NOT IN (SELECT id FROM fin_financial_events)");
$conn->query("DELETE FROM ems_business_events WHERE payload LIKE '%$MARK%' OR entity_type='fin_unit_record' AND entity_id IN (" . (empty($unitIds) ? '0' : implode(',', $unitIds)) . ")");
$cf = counts($conn);
ok('teardown: العدّادات عادت لخط الأساس', $cf['events'] === $c0['events'] && $cf['dues'] === $c0['dues'] && $cf['costs'] === $c0['costs'] && $cf['links'] === $c0['links'] && $cf['roots'] === $c0['roots']);

echo str_repeat('═', 50) . "\n";
echo "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n";
exit($FAIL === 0 ? 0 : 1);
