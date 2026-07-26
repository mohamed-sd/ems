<?php
/**
 * حارس قناة الاستيراد — import_events_fin (سدّ خرق v8 §15-أ · UX-02 §9-①)
 * يحرس ثلاثة عقود: ① لا كتابة مباشرة على الدفتر في Finance/ كلّه (فحص ساكن)
 * ② صفر يتيمٍ في الدفتر، والمستورَد منسوبٌ لمستنده ③ عطالة القناة الحية:
 * إعادة نشر أمرٍ مستوردٍ تعيد المرجع القائم بلا صفٍّ جديد.
 * التشغيل: php tests/import_channel_test.php — رمز الخروج 0/1.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';

use App\Core\EventPublisher;

$PASS = 0; $FAIL = 0;
function ok($label, $cond) {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "  ✔ {$label}\n"; }
    else { $FAIL++; echo "  ✘ FAIL: {$label}\n"; }
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_APP_USER'), ems_env('DB_APP_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$conn->set_charset('utf8mb4');

echo "── ① الفحص الساكن: القناة الوحيدة للدفتر هي الناشر ──\n";

// لا insert('fin_financial_events') عبر البوابة في أي شاشة مالية — الناشر حصرًا،
// باستثناءٍ واحدٍ موثَّق: events_list_fin.php = قناة الإدخال اليدوي المشروعة عقديًّا
// (ADR-15: «NULL دلاليٌّ مقصود: صفٌّ يدوي»). أي ملفٍّ جديدٍ يُدرج مباشرةً = خرق.
$ALLOWED_DIRECT = array('events_list_fin.php');
$offenders = array();
foreach (glob(dirname(__DIR__) . '/Finance/*.php') as $file) {
    $src = file_get_contents($file);
    if (preg_match("/->\s*insert\s*\(\s*['\"]fin_financial_events['\"]/u", $src)
        && !in_array(basename($file), $ALLOWED_DIRECT, true)) {
        $offenders[] = basename($file);
    }
}
ok('لا كاتبَ مباشرًا على الدفتر خارج قائمة السماح الموثَّقة (وجد: ' . (count($offenders) ? implode('،', $offenders) : 'لا شيء') . ')',
    count($offenders) === 0);

// شاشة الاستيراد تستدعي الناشر وتتحقق من رمز الفعل.
$importSrc = file_get_contents(dirname(__DIR__) . '/Finance/import_events_fin.php');
ok('شاشة الاستيراد تنشر عبر EventPublisher::publish', strpos($importSrc, 'EventPublisher::publish') !== false);
ok('فعلا التوليد محميان برمز الفعل (fin_verify_action_token ×2)', substr_count($importSrc, 'fin_verify_action_token()') >= 2);

echo "── ② الدفتر: صفر يتيم، والمستورَد منسوب ──\n";

ok('صفر صفِّ دفترٍ بلا جذر (root_event_id IS NULL)',
    intval($conn->query("SELECT COUNT(*) FROM fin_financial_events WHERE root_event_id IS NULL AND notes NOT LIKE 'K3TEST_%'")->fetch_row()[0]) === 0);

ok('كل صفوف الاستيراد منسوبةٌ لمستندها (entity_type/entity_id مملوءان)',
    intval($conn->query("SELECT COUNT(*) FROM fin_financial_events
        WHERE source_module IN('procurement','maintenance') AND source_ref LIKE 'PRC-%'
          AND (entity_type IS NULL OR entity_type = '' OR entity_id IS NULL OR entity_id = 0)")->fetch_row()[0]) === 0);

ok('جذر كل صفِّ استيرادٍ يحمل الكيان نفسه (proc_order/mnt_order)',
    intval($conn->query("SELECT COUNT(*) FROM fin_financial_events f
        JOIN ems_business_events b ON b.id = f.root_event_id
        WHERE f.entity_type IN('proc_order','mnt_order') AND b.entity_type <> f.entity_type")->fetch_row()[0]) === 0);

echo "── ③ عطالة القناة الحية (على أمرٍ مستوردٍ فعلًا) ──\n";

$row = $conn->query(
    "SELECT f.company_id, f.amount, f.currency, f.source_ref, f.entity_id
     FROM fin_financial_events f
     WHERE f.entity_type = 'proc_order' AND f.idempotency_key LIKE 'proc:order:%'
     ORDER BY f.id LIMIT 1"
)->fetch_assoc();

if ($row) {
    $ledger0 = intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]);
    $roots0  = intval($conn->query("SELECT COUNT(*) FROM ems_business_events")->fetch_row()[0]);
    EventPublisher::$rootModeOverride = 'publish';
    try {
        $res = EventPublisher::publish($conn, array(
            'event_key'         => 'expense.purchase.recorded',
            'category'          => 'financial',
            'source_module'     => 'procurement',
            'company_id'        => intval($row['company_id']),
            'entity_type'       => 'proc_order',
            'entity_id'         => intval($row['entity_id']),
            'occurred_at'       => gmdate('Y-m-d H:i:s'),
            'created_by'        => 1,
            'idempotency_key'   => 'proc:order:' . intval($row['entity_id']),
            'legacy_event_type' => 'expense',
            'amount'            => $row['amount'],
            'currency'          => $row['currency'],
            'source_ref'        => $row['source_ref'],
            'payload'           => array('order_id' => intval($row['entity_id']), 'channel' => 'import_channel_test'),
        ));
        $ledger1 = intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]);
        $roots1  = intval($conn->query("SELECT COUNT(*) FROM ems_business_events")->fetch_row()[0]);
        ok('إعادة نشر أمرٍ مستورد → duplicate=true بالمرجع القائم', !empty($res['duplicate']));
        ok('صفر صفِّ دفترٍ جديد', $ledger1 === $ledger0);
        ok('صفر جذرٍ جديد', $roots1 === $roots0);
    } catch (Throwable $t) {
        ok('إعادة النشر لا ترمي (' . $t->getMessage() . ')', false);
        ok('صفر صفِّ دفترٍ جديد', false);
        ok('صفر جذرٍ جديد', false);
    }
    EventPublisher::$rootModeOverride = null;
} else {
    ok('يوجد صفُّ استيرادٍ منشورٌ للاختبار عليه', false);
    ok('(تخطٍّ) صفر صفِّ دفترٍ جديد', false);
    ok('(تخطٍّ) صفر جذرٍ جديد', false);
}

echo "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n";
exit($FAIL > 0 ? 1 : 0);
