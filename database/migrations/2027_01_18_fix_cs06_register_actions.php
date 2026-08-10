<?php
/**
 * 2027_01_18_fix_cs06_register_actions.php
 * ═══════════════════════════════════════════════════════════════════════════
 * FIX-01 · CS-06 (FIXA-0007) — «كلُّ فعلٍ برمزٍ مسجَّلٍ وصنفِ كتابةٍ معلَن · لا
 * نداءَ POST بلا رمزِ فعلٍ في القاموس · وغيرُ المسجَّلِ يُحجب · ◆ وصفرُ رمزٍ
 * يحمل معنيين».
 *
 * الأفعالُ الستةُ التي أُعيد بناءُ معالجاتها بالعقدِ السبعيِّ في هذه الحزمة —
 * تُسجَّل هنا بصنفِ كتابةٍ معلَنٍ وأثرٍ وعكسٍ وشاهدِ حراسةٍ وعطالة.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال المرحِّل فشل: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$ACTIONS = array(
    array(
        'code' => 'proc.stock.count_adjust', 'label' => 'تسويةُ فرقِ الجرد',
        'screen' => 'الجرد والتسويات', 'file' => 'wh_count.php', 'actor' => 'المخازن',
        'writes' => 'proc_stock_move', 'event' => 'StockCountAdjusted',
        'effect' => 'حركةُ تسويةِ زيادةٍ أو عجزٍ بسببٍ موثَّقٍ — والرصيدُ محسوبٌ من الحركاتِ لا عمودٌ يُعدَّل',
        'reverse' => 'تسويةٌ عاكسةٌ بالإشارةِ المقابلةِ ومرجعِ الأصل — ولا حذف',
        'live' => 'page:Procurement/wh_count.php', 'class' => 'domain_write',
    ),
    array(
        'code' => 'proc.stock.warehouse_transfer', 'label' => 'التحويلُ بين المخازن',
        'screen' => 'التحويل بين المخازن', 'file' => 'wh_transfer.php', 'actor' => 'المخازن',
        'writes' => 'proc_stock_move', 'event' => 'StockTransferred',
        'effect' => 'حركتان ذريّتان (صادرٌ ووارد) بمرجعٍ واحدٍ داخلَ معاملةٍ — ولا صنفَ في مخزنين',
        'reverse' => 'تحويلٌ عكسيٌّ بمرجعِ الأصل',
        'live' => 'page:Procurement/wh_transfer.php', 'class' => 'domain_write',
    ),
    array(
        'code' => 'proc.stock.return_to_warehouse', 'label' => 'إرجاعُ مصروفٍ إلى المخزن',
        'screen' => 'المرتجعات', 'file' => 'wh_returns.php', 'actor' => 'المخازن',
        'writes' => 'proc_stock_move', 'event' => 'StockReturned',
        'effect' => 'مرتجعٌ بمرجعِ سندِ الصرفِ إلزامًا — ومجموعُ المرتجعِ لا يتجاوز مجموعَ المصروفِ (قادحٌ في القاعدة)',
        'reverse' => 'صرفٌ جديدٌ بمرجعِ المرتجع — ولا حذف',
        'live' => 'page:Procurement/wh_returns.php', 'class' => 'domain_write',
    ),
    array(
        'code' => 'proc.rfq.award', 'label' => 'ترسيةُ عرضِ مورد',
        'screen' => 'مقارنة العروض والترسية', 'file' => 'rfq_compare_award.php', 'actor' => 'المشتريات',
        'writes' => 'rfq_awards · supplier_rfqs', 'event' => 'RfqAwarded',
        'effect' => 'ترسيةٌ واحدةٌ لكلِّ بندٍ بسببٍ موثَّقٍ — ومسارُ كتابةٍ واحدٌ على الجدول',
        'reverse' => 'RfqAwardService::reverse — حركةٌ عاكسةٌ بمرجعِ الأصلِ والأصلُ باقٍ',
        'live' => 'page:Procurement/rfq_compare_award.php', 'class' => 'domain_write',
    ),
    array(
        'code' => 'trs.transfer.confirm_delivery', 'label' => 'توثيقُ تسليمِ أمرِ الترحيل',
        'screen' => 'الوصول والتسليم', 'file' => 'transfer_arrival.php', 'actor' => 'النقل',
        'writes' => 'transfer_delivery_docs · transfer_events', 'event' => 'TransferDelivered',
        'effect' => 'مستندُ تسليمٍ مخزَّنٌ بمرجعِه ووقتِه وشاهدِه — وحدثٌ واحدٌ مهما تكرر الإرسال',
        'reverse' => 'لا عكسَ له بطبيعته — التصحيحُ بمستندِ تسليمٍ مصحَّحٍ بمرجعِ الأول',
        'live' => 'page:Transport/transfer_arrival.php', 'class' => 'domain_write',
    ),
    array(
        'code' => 'trs.transfer.close_with_cost', 'label' => 'إقفالُ أمرِ الترحيلِ بتكلفته',
        'screen' => 'إقفال الأمر وتحميل التكلفة', 'file' => 'transfer_close_cost.php', 'actor' => 'النقل',
        'writes' => 'transfer_orders · transfer_events · transfer_cost_lines', 'event' => 'TransferClosed',
        'effect' => 'إقفالٌ بتكلفةٍ محمَّلةٍ على المشروع — مرفوضٌ بلا مستندِ تسليمٍ مخزَّن',
        'reverse' => 'فتحٌ بحركةِ تكلفةٍ عاكسةٍ بمرجعِ الإقفال',
        'live' => 'page:Transport/transfer_close_cost.php', 'class' => 'domain_write',
    ),
);

$db->begin_transaction();
try {
    $ins = 0; $upd = 0;
    foreach ($ACTIONS as $a) {
        $st = $db->prepare("SELECT COUNT(*) FROM nav09_action_map WHERE canonical_code = ?");
        if (!$st) { throw new RuntimeException('prepare: ' . $db->error); }
        $st->bind_param('s', $a['code']);
        $st->execute();
        $exists = (int) $st->get_result()->fetch_row()[0];
        $st->close();

        $guardEv = 'العقدُ السبعيُّ في includes/post_contract.php: طريقةٌ ← CSRF ← فعلٌ مسجَّل ← صلاحية ← عطالة ← مدخلات ← معاملة · وحارسُ الشاشةِ فوقَ المعالج (RF-02)';
        $idemEv  = 'مفتاحٌ مركَّبٌ من محتوى الطلبِ في ems_post_idempotency بفريدٍ في القاعدة (CS-07)';

        if ($exists > 0) {
            $st = $db->prepare("UPDATE nav09_action_map SET label_ar=?, screen_title=?, canonical_file=?,
                                    actor_ar=?, writes_text=?, event_name=?, effect_text=?, reverse_text=?,
                                    live_code=?, state='bound_page', guard_verified='yes', guard_evidence=?,
                                    idempotency_verified='yes', idempotency_evidence=?, write_class=?, updated_at=NOW()
                                 WHERE canonical_code=?");
            if (!$st) { throw new RuntimeException('prepare upd: ' . $db->error); }
            $st->bind_param('sssssssssssss', $a['label'], $a['screen'], $a['file'], $a['actor'],
                $a['writes'], $a['event'], $a['effect'], $a['reverse'], $a['live'],
                $guardEv, $idemEv, $a['class'], $a['code']);
            if (!$st->execute()) { throw new RuntimeException('update: ' . $st->error); }
            $st->close();
            $upd++;
            continue;
        }

        $st = $db->prepare("INSERT INTO nav09_action_map
              (canonical_code, label_ar, screen_title, canonical_file, actor_ar, writes_text, event_name,
               consumers_text, effect_text, reverse_text, live_code, state, guard_verified, guard_evidence,
               idempotency_verified, idempotency_evidence, uat_verified, uat_evidence, write_class, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?, 'bound_page', 'yes', ?, 'yes', ?, 'pending', '', ?, NOW())");
        if (!$st) { throw new RuntimeException('prepare ins: ' . $db->error); }
        $consumers = 'المالية · الحوكمة';
        $st->bind_param('ssssssssssssss', $a['code'], $a['label'], $a['screen'], $a['file'], $a['actor'],
            $a['writes'], $a['event'], $consumers, $a['effect'], $a['reverse'], $a['live'],
            $guardEv, $idemEv, $a['class']);
        if (!$st->execute()) { throw new RuntimeException('insert: ' . $st->error); }
        $st->close();
        $ins++;
    }
    $db->commit();
    echo "[CS-06] أفعالٌ مُسجَّلة: {$ins} · محدَّثة: {$upd}\n";
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}

/* ── إثباتٌ: كلُّ رمزٍ يُسأل عنه بمنطقِ العقدِ نفسِه فيُوجَد ─────────────── */
require_once dirname(__DIR__, 2) . '/includes/post_contract.php';
$missing = array();
foreach ($ACTIONS as $a) {
    if (ems_pc_action_registered($db, $a['code']) !== true) { $missing[] = $a['code']; }
}
if ($missing) { throw new RuntimeException('أفعالٌ لم يجدها العقد: ' . implode('، ', $missing)); }
echo "[CS-06] الإثباتُ الوظيفي: العقدُ يجد الأفعالَ الستةَ كلَّها ✔\n";
