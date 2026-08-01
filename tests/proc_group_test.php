<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-43 · M-49 · M-50 · M-51 · E-17 · E-18 — اختبار قبول مجموعةِ المشتريات
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/proc_group_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '16', 'company_id' => 4, 'name' => 'proc group test');

require_once dirname(__DIR__) . '/app/Services/Procurement/ProcReorderService.php';

use App\Services\Procurement\ProcReorderService as PRS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO   = 4;
$MARK = 'PG6T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE l FROM proc_request_line l JOIN proc_request r ON r.id = l.request_id
                   WHERE r.code LIKE '%AUTO%' AND r.notes LIKE '%M-43%'
                     AND EXISTS (SELECT 1 FROM proc_item i WHERE i.id = l.item_id AND i.name LIKE '%{$MARK}%')");
    $conn->query("DELETE r FROM proc_request r
                   WHERE r.code LIKE 'PRQ-AUTO-%' AND NOT EXISTS
                         (SELECT 1 FROM proc_request_line l WHERE l.request_id = r.id)");
    $conn->query("DELETE FROM proc_stock_move WHERE note LIKE '%{$MARK}%'");
    $conn->query("DELETE op FROM proc_orderpoint op JOIN proc_item i ON i.id = op.item_id
                   WHERE i.name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM proc_item WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ المشتريات — M-43 · M-49 · M-50 · M-51 · E-17 · E-18 ══\n");

head('البذر — صنفٌ بنقطة طلبٍ وحركاتِ استلامٍ وصرف');

$conn->query("INSERT INTO proc_item (company_id, code, name, uom, min_qty, max_qty,
              lead_time_days, safety_stock, status, created_by)
              VALUES ({$CO}, 'IT-{$MARK}', 'صنفُ {$MARK}', 'قطعة', 10, 100, 7, 5, 'active', 1)");
$IT = intval($conn->insert_id);
$conn->query("INSERT INTO proc_orderpoint (company_id, item_id, min_qty, max_qty, trigger_qty,
              safety_stock, mode, created_by)
              VALUES ({$CO}, {$IT}, 10, 100, 15, 5, 'auto', 1)");
// استلامُ 50 ثم صرفُ 45 على 30 يومًا ⇒ الرصيد 5 ≤ الحد 15 · والمتوسط ≈ 0.5/يوم
$conn->query("INSERT INTO proc_stock_move (company_id, item_id, move_type, qty, note, moved_at, created_by)
              VALUES ({$CO}, {$IT}, 'استلام', 50, 'بذر {$MARK}', DATE_SUB(NOW(), INTERVAL 60 DAY), 1)");
$conn->query("INSERT INTO proc_stock_move (company_id, item_id, move_type, qty, note, moved_at, created_by)
              VALUES ({$CO}, {$IT}, 'صرف', 45, 'بذر {$MARK}', DATE_SUB(NOW(), INTERVAL 10 DAY), 1)");
check($IT > 0, "صنفٌ #{$IT} بنقطة طلبٍ (حد 15 · أعلى 100) ورصيدٍ حيٍّ 5");

// ═══ M-51 ═══
head('M-51 — متوسطُ الاستهلاك مصدرًا لحدِّ إعادة الطلب');

$bal = PRS::balance($conn, $CO, $IT);
check(abs($bal - 5.0) < 0.005, '★ الرصيدُ من الحركات لا من حقل: 50 − 45 = 5');
$c = PRS::consumption($conn, $CO, $IT, 90);
check(abs($c['consumed'] - 45.0) < 0.005 && abs($c['avg_daily'] - 0.5) < 0.005,
      '★★ المتوسطُ اليومي من الصرف الفعلي: 45 ÷ 90 = 0.5/يوم');
check(abs($c['suggested_trigger'] - (0.5 * 7 + 5)) < 0.005,
      '★★★ والحدُّ المقترح = متوسطٌ×مهلة+أمان = 8.5 — **مصدرٌ محسوبٌ يُعرض ولا يُفرض**');

// ═══ M-43 ═══
head('M-43 — التوليدُ الآلي بمفتاح (صنف × دورة)');

$dry = PRS::run($conn, ems_tenant_db(), $CO, 1, true);
$hit = null;
foreach ($dry['generated'] as $g) { if ($g['item_id'] === $IT) { $hit = $g; } }
check($hit !== null && abs($hit['qty'] - 95.0) < 0.005,
      '★ التجريب: الصنفُ بلغ حدَّه ⇒ مرشحٌ بكمية (100 − 5) = 95 **وصفرُ كتابة**');
$before = intval($conn->query("SELECT COUNT(*) n FROM proc_request")->fetch_assoc()['n']);
$run = PRS::run($conn, ems_tenant_db(), $CO, 1, false);
$hit = null;
foreach ($run['generated'] as $g) { if ($g['item_id'] === $IT) { $hit = $g; } }
check($hit !== null && !empty($hit['request_id']),
      '★★ التوليدُ الفعلي أنشأ طلبَ الشراء #' . intval($hit['request_id'] ?? 0) . ' بسطر صنفه');
$run2 = PRS::run($conn, ems_tenant_db(), $CO, 1, false);
$again = null; $skipped = null;
foreach ($run2['generated'] as $g) { if ($g['item_id'] === $IT) { $again = $g; } }
foreach ($run2['skipped'] as $s) { if (strpos($s['item'], $MARK) !== false) { $skipped = $s; } }
check($again === null && $skipped !== null && strpos($skipped['reason'], 'دورةٌ جارية') !== false,
      '★★★ **وإعادةُ التشغيل لا تولّد ثانيًا**: الدورةُ الجاريةُ تمنع — مفتاحُ (صنف × دورة) يعمل');

// ═══ M-49 ═══
head('M-49 — بطاقةُ المورد بتبويباتها السبعة');

$m = $conn->query("SELECT id FROM modules WHERE code='Procurement/supplier_card_proc.php'")->fetch_assoc();
check($m && intval($m['id']) === 198, '★ الشاشةُ 198 مسجَّلة');
$card = file_get_contents(dirname(__DIR__) . '/Procurement/supplier_card_proc.php');
$tabs = 0;
foreach (array('البيانات', 'أوامرُ الشراء', 'الاستلامات', 'الفواتيرُ والمطابقة', 'العهد', 'الأصناف', 'السجل') as $t) {
    if (strpos($card, $t) !== false) { $tabs++; }
}
check($tabs === 7, '★★ التبويباتُ السبعةُ كلُّها (' . $tabs . '/7) — بدل الجدول المسطّح');
check(strpos(file_get_contents(dirname(__DIR__) . '/Procurement/suppliers_proc.php'),
             'supplier_card_proc.php') !== false,
      '★ والقائمةُ تصل للبطاقة — لا شاشةَ معزولة');

// ═══ M-50 ═══
head('M-50 — لوحةُ أمين المستودع دورًا مستقلًّا');

$role = $conn->query("SELECT name FROM roles WHERE id=25")->fetch_assoc();
check($role && $role['name'] === 'أمين المستودع', '★ الدورُ 25 «أمين المستودع» في القاعدة');
require_once dirname(__DIR__) . '/includes/roles.php';
check(defined('EMS_ROLE_WAREHOUSE_KEEPER') && EMS_ROLE_WAREHOUSE_KEEPER === '25'
      && isset(EMS_ROLE_NAMES['25']),
      '★★ وثابتُه واسمُه في includes/roles.php — **حارسُ ADR-07 لن ينحرف**');
$m = $conn->query("SELECT id FROM modules WHERE code='Procurement/warehouse_board.php'")->fetch_assoc();
check($m && intval($m['id']) === 199, '★ ولوحتُه الشاشةُ 199 مسجَّلة');
$nav = intval($conn->query("SELECT COUNT(*) n FROM nav_items WHERE role_id=25")->fetch_assoc()['n']);
check($nav >= 6, '★ وسايدبارُه بشاشات يومه (' . $nav . ' روابط)');

// ═══ E-17 ═══
head('E-17 — زرُّ «طلبُ شراءٍ بمرجع الأمر» عند النقص');

$iss = file_get_contents(dirname(__DIR__) . '/Procurement/issue_proc.php');
check(strpos($iss, 'proc_shortage_flash') !== false
      && strpos($iss, 'طلبُ شراءٍ بمرجع الأمر') !== false
      && strpos($iss, 'need_source=') !== false,
      '★★ بعد الصرف: الصنفُ الهابطُ لحدّه يُعلَن **بزرٍّ يحمل مرجعَ الأمر وصنفَه** لا رسالةً');
check(strpos(file_get_contents(dirname(__DIR__) . '/Procurement/requests_proc.php'),
             'prefill_item') !== false,
      '★ وشاشةُ الطلبات تستقبل التعبئةَ بمرجعها');

// ═══ E-18 ═══
head('E-18 — PartialReceived وLate حالتين صريحتين بعدّاديهما');

$ord = file_get_contents(dirname(__DIR__) . '/Procurement/orders_proc.php');
check(strpos($ord, 'استلامٌ جزئي — متبقٍ') !== false && strpos($ord, 'received_pct') !== false,
      '★★ «استلامٌ جزئي» **بعدّاد المتبقي٪** على القائمة — لا تنبيهًا ضمنيًّا');
check(strpos($ord, 'متأخرٌ ') !== false && strpos($ord, 'expected_delivery_date') !== false,
      '★★ و«متأخر» **بأيامه** من موعد التسليم — حالتان مصرَّحتان (UX-09 §5.1)');

// ═══ الخاتمة ═══
fwrite(STDOUT, "\n══════════════════════════════════════\n");
fwrite(STDOUT, "  النتيجة: {$PASS} نجاح · {$FAIL} فشل\n");
exit($FAIL > 0 ? 1 : 0);
