<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * Migration: الردم الصادق ليتامى قناة الاستيراد — 2026-07-26
 * ───────────────────────────────────────────────────────────────────────────
 * الصفوف FIN-EV-IMP-1..10 (2026-07-18) كتبتها شاشة استقبال المعاملات على
 * الدفتر مباشرةً متخطيةً الناشر — بعد نفاذ عقد ADR-15 بيومٍ واحد — فبقيت
 * بلا حقيقةٍ جذرية (root_event_id IS NULL) وبلا نسبِ مستندٍ (entity فارغ).
 *
 * ليست «يدويةً سابقةً للجذر» (معنى NULL الدلالي المشروع)، ومصدرُها معلومٌ
 * يقينًا: كلُّ صفٍّ يطابق proc_order بالكود والمبلغ (تحقق 10/10 يوم 2026-07-26).
 * فالردمُ نسبٌ صادقٌ كامل، لا وسمُ legacy عام:
 *
 *   ① حقيقة expense.purchase.recorded عبر EventPublisher::publishFact نفسه
 *      (ترقيم BE من المتتالية الرسمية · UUID · عطالة uq_ebe_idempotency)
 *      بمفتاح proc:order:{id} — مفتاح القناة الحية نفسه، فأي إعادة توليدٍ
 *      مستقبلية للأمر نفسه تلتقي به ولا تضاعف.
 *   ② ربط صف الدفتر: root_event_id + entity_type/entity_id + event_key/category
 *      + idempotency_key — فتكتمل قراءة الخيط بالاتجاهين حتى أمر الشراء.
 *
 * والحمولة تحمل bypassed_publisher=true فيميّزها كل مستهلكٍ عن السيل الحي.
 * occurred_at = created_at الأصلي (وقت وقوع التسجيل الفعلي، لا وقت الردم).
 *
 * idempotent: آمنٌ لإعادة التشغيل (عطالة الناشر + شرط root_event_id IS NULL).
 * التشغيل: php database/migrations/2026_07_26_import_channel_truthful_backfill.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
mysqli_report(MYSQLI_REPORT_OFF);
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__, 2) . '/includes/env.php';
require_once dirname(__DIR__, 2) . '/app/Core/EventPublisher.php';

$mu = ems_env('DB_MIGRATOR_USER'); $mp = ems_env('DB_MIGRATOR_PASS');
if ($mu === null || $mu === '' || $mp === null || $mp === '') { fwrite(STDERR, "FATAL: DB_MIGRATOR_USER/PASS مطلوبان في .env.\n"); exit(1); }
$conn = new mysqli(ems_env('DB_HOST'), $mu, $mp, ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "FATAL: db connect: " . $conn->connect_error . "\n"); exit(1); }
$conn->set_charset('utf8mb4');

// الردم يكتب الجذر ولو كان علم البيئة off — هذا تصحيحُ سجلٍّ لا سيلًا حيًّا.
\App\Core\EventPublisher::$rootModeOverride = 'publish';

// اليتامى: صفوف استيراد المشتريات بلا جذر، مع أمر الشراء المصدر بالكود والشركة.
$q = $conn->query(
    "SELECT f.id, f.company_id, f.event_no, f.source_ref, f.amount, f.currency,
            f.created_by, f.created_at, o.id AS order_id, o.total_amount
     FROM fin_financial_events f
     JOIN proc_order o ON o.code = f.source_ref AND o.company_id = f.company_id
     WHERE f.root_event_id IS NULL AND f.source_module = 'procurement'
       AND f.event_no LIKE 'FIN-EV-IMP-%'
     ORDER BY f.id"
);
if (!$q) { fwrite(STDERR, "FATAL: قراءة اليتامى: " . $conn->error . "\n"); exit(1); }

$done = 0; $skipped = 0; $failed = 0;
while ($f = $q->fetch_assoc()) {
    // صدق النسب شرطُ الردم: مبلغ الصف = مبلغ الأمر وإلا فلا ننسب زورًا.
    if (abs(floatval($f['amount']) - floatval($f['total_amount'])) >= 0.01) {
        echo "  ! {$f['event_no']}: مبلغ الصف ({$f['amount']}) ≠ مبلغ الأمر ({$f['total_amount']}) — تُرك بلا ردمٍ لقرارٍ يدوي\n";
        $skipped++;
        continue;
    }
    $conn->begin_transaction();
    try {
        $fact = \App\Core\EventPublisher::publishFact($conn, array(
            'event_key'       => 'expense.purchase.recorded',
            'category'        => 'financial',
            'source_module'   => 'procurement',
            'company_id'      => intval($f['company_id']),
            'entity_type'     => 'proc_order',
            'entity_id'       => intval($f['order_id']),
            'occurred_at'     => gmdate('Y-m-d H:i:s', strtotime($f['created_at'])),
            'created_by'      => intval($f['created_by']) > 0 ? intval($f['created_by']) : 1,
            'idempotency_key' => 'proc:order:' . intval($f['order_id']),
            'amount'          => $f['amount'],
            'currency'        => $f['currency'],
            'source_ref'      => $f['source_ref'],
            'payload'         => array(
                'order_id'           => intval($f['order_id']),
                'order_code'         => (string) $f['source_ref'],
                'channel'            => 'import_events_fin',
                'bypassed_publisher' => true,
                'ledger_event_no'    => (string) $f['event_no'],
                'backfilled_at'      => '2026-07-26',
            ),
        ));
        if (!$fact || empty($fact['id'])) { throw new RuntimeException('publishFact أعاد null — علم الجذر؟'); }

        $u = $conn->prepare(
            "UPDATE fin_financial_events
             SET root_event_id = ?, entity_type = 'proc_order', entity_id = ?,
                 event_key = 'expense.purchase.recorded', category = 'financial',
                 idempotency_key = ?
             WHERE id = ? AND root_event_id IS NULL"
        );
        $idem = 'proc:order:' . intval($f['order_id']);
        $rootId = intval($fact['id']); $orderId = intval($f['order_id']); $rowId = intval($f['id']);
        $u->bind_param('iisi', $rootId, $orderId, $idem, $rowId);
        if (!$u->execute()) { throw new RuntimeException('ربط الدفتر: ' . $u->error); }
        $u->close();

        $conn->commit();
        echo "  ✔ {$f['event_no']} ← proc_order#{$f['order_id']} ← BE root #{$rootId}\n";
        $done++;
    } catch (Throwable $t) {
        $conn->rollback();
        echo "  ✘ {$f['event_no']}: " . $t->getMessage() . "\n";
        $failed++;
    }
}

$left = intval($conn->query("SELECT COUNT(*) FROM fin_financial_events WHERE root_event_id IS NULL")->fetch_row()[0]);
echo "\nرُدم: {$done} · تُرك بقرار: {$skipped} · فشل: {$failed} · المتبقي بلا جذر في الدفتر كله: {$left}\n";
exit(($failed > 0) ? 1 : 0);
