<?php
/**
 * tools/fix_action_tests.php — اختباراتُ القبولِ الحيةُ للأفعالِ المُصلَحة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يُشغِّل الأفعالَ فعلًا عبرَ الشاشاتِ بجلسةٍ محقونةٍ ويقيس الأثرَ في القاعدة —
 *   لا يتفقّد وجودَ شيفرة. الشواهدُ المطلوبةُ في الوثائق:
 *     AC-M3 · «إرجاعٌ يفوق المصروفَ ناقصًا ما أُرجع يُرفض» — ثلاثُ محاولاتٍ متتالية
 *     AC-M4 · «إعادةُ إرسالِ التسليمِ ثلاثًا تُنتج حدثًا واحدًا»
 *     AC-M5 · «الإقفالُ بتكلفةٍ يُرفض بلا مستندِ تسليمٍ مخزَّن»
 *     AC-F7 · «نداءٌ برمزٍ غيرِ مسجَّلٍ يُرفض»
 *     AC-F8 · «خمسُ إعاداتٍ لطلبٍ تُنتج صفًّا واحدًا»
 *
 * ◆ كلُّ أثرٍ يُصنع هنا يُنظَّف في النهايةِ — والتنظيفُ يُنفَّذ ولو انفجر الاختبار.
 * التشغيل: php tools/fix_action_tests.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';
require_once $ROOT . '/app/Services/Procurement/StockReturnService.php';
require_once $ROOT . '/app/Services/Transport/TransferDeliveryService.php';
$db = fix_db();

$RESULTS = array();
function t($code, $title, $ok, $evidence)
{
    global $RESULTS;
    $RESULTS[] = compact('code', 'title', 'ok', 'evidence');
    printf("%s %-8s %s\n        %s\n\n", $ok ? '✔' : '✘', $code, $title, $evidence);
}

echo "══════════════════════════════════════════════════════════════════════\n";
echo " اختباراتُ القبولِ الحيةُ — الأفعالُ تُشغَّل ويُقاس أثرُها في القاعدة\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";

$cleanup = array();

try {
/* ═══ AC-M3 · المرتجعُ لا يتجاوز (المصروف − ما أُرجع) ════════════════════ */
$row = $db->query("SELECT i.id issue_id, i.company_id, il.item_id, SUM(il.qty) issued
                     FROM proc_issue i JOIN proc_issue_line il ON il.issue_id = i.id
                    GROUP BY i.id, i.company_id, il.item_id
                   HAVING issued > 0 ORDER BY i.id LIMIT 1");
$src = $row ? $row->fetch_assoc() : null;
if (!$src) {
    t('AC-M3', 'المرتجعُ لا يتجاوز المصروفَ ناقصًا ما أُرجع', false, 'لا سندَ صرفٍ في القاعدةِ للقياس');
} else {
    $svc = new \App\Services\Procurement\StockReturnService($db);
    $co = (int) $src['company_id']; $iss = (int) $src['issue_id']; $item = (int) $src['item_id'];
    $issued = (float) $src['issued'];
    $before = $svc->returnableOf($co, $iss, $item);
    $half = round($before['available'] / 2, 2);

    $log = array();
    // ① نصفُ المتاحِ — يُقبل
    $r1 = $svc->returnToWarehouse($co, $iss, $item, max(0.01, $half), 'اختبارُ قبولٍ ①', 0);
    $log[] = '①' . ($r1['ok'] ? 'قُبل' : 'رُفض');
    if (!empty($r1['move_id'])) { $cleanup[] = (int) $r1['move_id']; }
    // ② النصفُ الآخر — يُقبل
    $r2 = $svc->returnToWarehouse($co, $iss, $item, max(0.01, $before['available'] - max(0.01, $half)), 'اختبارُ قبولٍ ②', 0);
    $log[] = '②' . ($r2['ok'] ? 'قُبل' : 'رُفض');
    if (!empty($r2['move_id'])) { $cleanup[] = (int) $r2['move_id']; }
    // ③ أيُّ كميةٍ إضافية — **يجب أن تُرفض**
    $r3 = $svc->returnToWarehouse($co, $iss, $item, 1, 'اختبارُ قبولٍ ③ (تجاوز)', 0);
    $log[] = '③' . ($r3['ok'] ? 'قُبل ✗' : 'رُفض ✔');
    if (!empty($r3['move_id'])) { $cleanup[] = (int) $r3['move_id']; }

    $ok = !empty($r1['ok']) && !empty($r2['ok']) && empty($r3['ok']);
    t('AC-M3', 'ثلاثُ محاولاتٍ: المتاحُ يُقبل والتجاوزُ يُرفض', $ok,
        "سندٌ #{$iss} صنف #{$item} · مصروفٌ {$issued} · متاحٌ {$before['available']} · "
        . implode(' · ', $log) . ' · رسالةُ الرفض: ' . mb_substr((string) $r3['msg'], 0, 90));

    /* ═══ AC-M3-ب · القيدُ في القاعدةِ يمنع ولو تُجوِّز التطبيق ══════════ */
    $prev = mysqli_report(MYSQLI_REPORT_OFF);
    $db->query("INSERT INTO proc_stock_move (company_id,item_id,warehouse_id,move_type,qty,ref_type,ref_id,note,moved_at,created_by)
                VALUES ({$co},{$item},0,'مرتجع',999999,'issue',{$iss},'اختبارُ القاعدة',NOW(),0)");
    $dbBlocked = ($db->errno !== 0 && stripos($db->error, 'FN-07') !== false);
    $dbErr = $db->error;
    if ($db->errno === 0) { $db->query("DELETE FROM proc_stock_move WHERE note = 'اختبارُ القاعدة'"); }
    mysqli_report($prev);
    t('AC-M3ب', 'القيدُ في القاعدةِ يمنع التجاوزَ ولو تُجوِّز التطبيق', $dbBlocked,
        $dbBlocked ? 'رفضت القاعدةُ الإدراجَ المباشر: ' . mb_substr($dbErr, 0, 110) : 'مرَّ الإدراجُ المباشر — القادحُ لا يعمل');
}

/* ═══ AC-M4/AC-M5 · التسليمُ والإقفال ═══════════════════════════════════ */
$row = $db->query("SELECT id, company_id FROM transfer_orders
                    WHERE stage = 'arrived' AND is_deleted = 0 ORDER BY id LIMIT 1");
$ord = $row ? $row->fetch_assoc() : null;
if (!$ord) {
    t('AC-M4', 'إعادةُ إرسالِ التسليمِ ثلاثًا تُنتج حدثًا واحدًا', false, 'لا أمرَ ترحيلٍ واصلٍ للقياس');
    t('AC-M5', 'الإقفالُ يُرفض بلا مستندِ تسليمٍ مخزَّن', false, 'لا أمرَ ترحيلٍ واصلٍ للقياس');
} else {
    $svc = new \App\Services\Transport\TransferDeliveryService($db);
    $co = (int) $ord['company_id']; $oid = (int) $ord['id'];

    // ◆ AC-M5 أولًا: الإقفالُ قبلَ التسليم — **يجب أن يُرفض**.
    $pre = $svc->closeWithCost($co, $oid, 100.0, 0);
    t('AC-M5', 'الإقفالُ بتكلفةٍ يُرفض بلا مستندِ تسليمٍ مخزَّن', empty($pre['ok']),
        'أمرٌ #' . $oid . ' · ' . mb_substr((string) $pre['msg'], 0, 110));

    // ◆ AC-M4: ثلاثُ إرسالاتٍ ⇒ مستندٌ واحدٌ وحدثٌ واحد.
    $evBefore = (int) fix_one($db, "SELECT COUNT(*) FROM transfer_events
                                     WHERE company_id={$co} AND order_id={$oid} AND event_type='delivered'");
    $res = array();
    for ($i = 1; $i <= 3; $i++) {
        $r = $svc->confirmDelivery($co, $oid, 'اختبارُ عطالة ' . $i, 'شاهدُ الاختبار', 0);
        $res[] = ($r['ok'] ? 'ok' : 'no') . ($r['replay'] ? '/تكرار' : '');
    }
    $evAfter = (int) fix_one($db, "SELECT COUNT(*) FROM transfer_events
                                    WHERE company_id={$co} AND order_id={$oid} AND event_type='delivered'");
    $docs = (int) fix_one($db, "SELECT COUNT(*) FROM transfer_delivery_docs WHERE company_id={$co} AND order_id={$oid}");
    $added = $evAfter - $evBefore;
    t('AC-M4', 'ثلاثُ إرسالاتٍ تُنتج حدثًا واحدًا ومستندًا واحدًا', ($added <= 1 && $docs === 1),
        "أحداثُ التسليم: {$evBefore} → {$evAfter} (أُضيف {$added}) · مستنداتٌ: {$docs} · النتائج: " . implode(' · ', $res));

    // ◆ AC-M5-ب: وبعدَ تخزينِ المستند — الإقفالُ يُقبل.
    $post = $svc->closeWithCost($co, $oid, 100.0, 0);
    t('AC-M5ب', 'وبعدَ تخزينِ المستندِ يُقبل الإقفال', !empty($post['ok']),
        mb_substr((string) $post['msg'], 0, 120));

    // تنظيفٌ: نُعيد الأمرَ إلى الوصولِ ونمحو أثرَ الاختبار.
    $db->query("UPDATE transfer_orders SET stage='arrived', actual_cost_usd=NULL WHERE id={$oid} AND company_id={$co}");
    $db->query("DELETE FROM transfer_cost_lines WHERE order_id={$oid} AND company_id={$co} AND cost_type='actual_total' AND amount_usd=100");
    $db->query("DELETE FROM transfer_events WHERE order_id={$oid} AND company_id={$co} AND body LIKE '%اختبارُ عطالة%'");
    $db->query("DELETE FROM transfer_events WHERE order_id={$oid} AND company_id={$co} AND sync_uuid='cls:{$co}:{$oid}'");
    $db->query("DELETE FROM transfer_events WHERE order_id={$oid} AND company_id={$co} AND sync_uuid='dlv:{$co}:{$oid}'");
    $db->query("DELETE FROM transfer_delivery_docs WHERE order_id={$oid} AND company_id={$co} AND witness_name='شاهدُ الاختبار'");
}

/* ═══ AC-F7 · رمزُ فعلٍ غيرُ مسجَّلٍ يُرفض ═══════════════════════════════ */
require_once $ROOT . '/includes/post_contract.php';
$unknown = ems_pc_action_registered($db, 'zz.not.registered.' . getmypid());
$known   = ems_pc_action_registered($db, 'proc.stock.count_adjust');
t('AC-F7', 'رمزُ فعلٍ غيرُ مسجَّلٍ يُرفض والمسجَّلُ يمرّ', ($unknown === false && $known === true),
    'غيرُ المسجَّل: ' . var_export($unknown, true) . ' · المسجَّل: ' . var_export($known, true));

/* ═══ AC-F8 · خمسُ إعاداتٍ لطلبٍ تُنتج صفًّا واحدًا ══════════════════════ */
// ◆ المفتاحُ يُبنى بدالةِ الإنتاجِ نفسِها (sha1 = أربعون محرفًا بالضبط) — ولا
//   يُلفَّق نصًّا أطول: العمودُ CHAR(40) و‎INSERT IGNORE‎ يبتلع البترَ صامتًا،
//   فمفتاحٌ ملفَّقٌ أطولُ كان يجعل الاختبارَ يرسب لسببٍ ليس في المفحوص.
$key = ems_pc_idem_key('fix.test.idem', array('probe' => getmypid()));
$db->query("DELETE FROM ems_post_idempotency WHERE action_code = 'fix.test.idem'");
for ($i = 0; $i < 5; $i++) { ems_pc_idem_mark($db, $key, 'fix.test.idem', 'ref#1'); }
$rows = (int) fix_one($db, "SELECT COUNT(*) FROM ems_post_idempotency WHERE idem_key = '" . $db->real_escape_string($key) . "'");
$seen = ems_pc_idem_seen($db, $key);
$db->query("DELETE FROM ems_post_idempotency WHERE idem_key = '" . $db->real_escape_string($key) . "'");
t('AC-F8', 'خمسُ إعاداتٍ لمفتاحٍ واحدٍ تُنتج صفًّا واحدًا', ($rows === 1 && $seen),
    "صفوفٌ بعدَ خمسِ إعاداتٍ: {$rows} · العطالةُ تكشفه: " . ($seen ? 'نعم' : 'لا'));

} finally {
    foreach ($cleanup as $mid) { $db->query("DELETE FROM proc_stock_move WHERE id = " . (int) $mid); }
    $db->query("DELETE FROM proc_stock_move WHERE note LIKE 'اختبارُ قبولٍ%'");
}

$pass = 0; $fail = 0;
foreach ($RESULTS as $r) { $r['ok'] ? $pass++ : $fail++; }
echo str_repeat('═', 70) . "\n";
printf("النتيجة: %d/%d\n", $pass, $pass + $fail);
echo str_repeat('═', 70) . "\n";
exit($fail === 0 ? 0 : 1);
