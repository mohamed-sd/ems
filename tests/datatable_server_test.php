<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-22 — اختبار قبول المعالجة الخادمية للجداول (UI-01 §4 · §9)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/datatable_server_test.php
 *
 * ما يُثبته:
 *   ① المساعد ems_dt_params: صفحةُ 50 افتراضًا · سقفٌ صلب (طلبُ الكل -1
 *      والمبالغةُ تُقصّان) · فرزٌ بقائمة سماحٍ حصرًا (لا اسمَ عمودٍ من المتصفح).
 *   ② ems_dt_like_clause: بحثٌ في الحقول المعرَّفة بهروبٍ آمن.
 *   ③ الصفحةُ الخادمية الحقيقية (activity_logs — أكبرُ جدولٍ 13 ألف صف):
 *      LIMIT/OFFSET بلا تداخلِ صفحات · العدّان total/filtered · البحثُ يقلّص.
 *   ④ الوصلُ المصدري: الشاشتان المحوَّلتان (serverSide + ?ajax=dt + LIMIT)
 *      وسقوطُ تحميل الـ5000/الدفترِ الكامل · وافتراضاتُ §9 في performance-boost.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/datatable_server.php';
require_once dirname(__DIR__) . '/app/Repositories/ActivityLogRepository.php';

use App\Repositories\ActivityLogRepository;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

fwrite(STDOUT, "\n══ H-22 — المعالجةُ الخادمية للجداول ══\n");

// ═══ ① ems_dt_params ═══
head('① ems_dt_params — الافتراضاتُ والسقوف وقائمةُ سماح الفرز');
$COLS = array(0 => 'al.created_at', 3 => 'al.module_name');

$_REQUEST = array();
$p = ems_dt_params($COLS);
check($p['length'] === 50, 'بلا معاملات: الطولُ الافتراضي 50 (UI-01 §9)');
check($p['start'] === 0 && $p['draw'] === 0, 'بلا معاملات: البدايةُ صفر');
check($p['order_sql'] === 'al.created_at DESC', 'بلا فرزٍ مطلوب: أولُ قائمة السماح تنازليًّا');

$_REQUEST = array('draw' => '7', 'start' => '100', 'length' => '-1');
$p = ems_dt_params($COLS);
check($p['length'] === 250, 'طلبُ «الكل» (-1) يُقصّ للسقف 250 — «لا تحميلَ لكل السجلات»');

$_REQUEST = array('length' => '99999');
$p = ems_dt_params($COLS);
check($p['length'] === 250, 'طولٌ مبالغٌ (99999) يُقصّ للسقف');

$_REQUEST = array('order' => array(array('column' => '3', 'dir' => 'asc')));
$p = ems_dt_params($COLS);
check($p['order_sql'] === 'al.module_name ASC', 'فهرسُ عمودٍ مسموح يُترجم بقائمة السماح');

$_REQUEST = array('order' => array(array('column' => '9', 'dir' => 'asc')));
$p = ems_dt_params($COLS);
check($p['order_sql'] === 'al.created_at ASC', 'فهرسٌ خارج القائمة يسقط لأولها — لا SQL من المتصفح');

$_REQUEST = array('order' => array(array('column' => '0', 'dir' => "asc'; DROP TABLE x--")));
$p = ems_dt_params($COLS);
check($p['order_sql'] === 'al.created_at DESC', 'اتجاهٌ خبيث يسقط إلى DESC');

$_REQUEST = array('search' => array('value' => '  نصُّ بحث  '));
$p = ems_dt_params($COLS);
check($p['search'] === 'نصُّ بحث', 'البحثُ يُقتطع من search[value]');

// ═══ ② ems_dt_like_clause ═══
head('② ems_dt_like_clause — الحقولُ المعرَّفة والهروب');
check(ems_dt_like_clause($conn, '', array('a.x')) === '', 'بلا بحث: شرطٌ فارغ');
check(ems_dt_like_clause($conn, 'abc', array()) === '', 'بلا حقول: شرطٌ فارغ');
$cl = ems_dt_like_clause($conn, 'abc', array('a.x', 'b.y'));
check($cl === "(a.x LIKE '%abc%' OR b.y LIKE '%abc%')", 'شرطُ OR على الحقول المعرَّفة');
$cl = ems_dt_like_clause($conn, "a'b", array('a.x'));
check(strpos($cl, "a\\'b") !== false, 'الاقتباسُ مهرَّب — لا حقن');

// ═══ ③ الصفحةُ الخادمية على activity_logs ═══
head('③ getDataTablePage — ترقيمٌ حقيقيٌّ على أكبر جدول');
$repo = new ActivityLogRepository($conn);
$base = array();

$pg = $repo->getDataTablePage($base, array(), '', '', 0, 5);
check(count($pg['rows']) <= 5 && count($pg['rows']) > 0, 'صفحةُ 5: تعيد ≤5 صفوف (' . count($pg['rows']) . ')');
check($pg['total'] >= 13000, 'العدُّ الكلي ≥ 13000 (المقيس: ' . $pg['total'] . ') — ولا يمرّ للمتصفح');
check($pg['total'] === $pg['filtered'], 'بلا بحثٍ: الكليُّ = المرشَّح');

$pg2 = $repo->getDataTablePage($base, array(), '', '', 5, 5);
$ids1 = array_map(fn($r) => $r['id'], $pg['rows']);
$ids2 = array_map(fn($r) => $r['id'], $pg2['rows']);
check(count(array_intersect($ids1, $ids2)) === 0, 'الصفحةُ الثانية (OFFSET 5) لا تتداخل مع الأولى');

$srch = ems_dt_like_clause($conn, 'login', array('al.action_type', 'al.module_name'));
$pg3 = $repo->getDataTablePage($base, array(), $srch, '', 0, 5);
check($pg3['filtered'] < $pg3['total'], 'البحثُ يقلّص المرشَّح (' . $pg3['filtered'] . ' من ' . $pg3['total'] . ')');
check($pg3['total'] === $pg['total'], 'البحثُ لا يمسّ العدَّ الكلي');

$pgA = $repo->getDataTablePage($base, array(), '', 'al.created_at ASC', 0, 2);
$pgD = $repo->getDataTablePage($base, array(), '', 'al.created_at DESC', 0, 2);
check(!empty($pgA['rows']) && !empty($pgD['rows'])
   && $pgA['rows'][0]['created_at'] <= $pgD['rows'][0]['created_at'],
   'الفرزُ الخادمي يعمل بالاتجاهين');

$pgF = $repo->getDataTablePage(array('company_id' => 4), array('http_method' => 'CLI'), '', '', 0, 5);
check($pgF['filtered'] <= $pgF['total'], 'فلاترُ الشاشة تدخل المرشَّحَ لا الكلي');

// ═══ ④ الوصلُ المصدري ═══
head('④ الوصلُ المصدري — الشاشتان والافتراضاتُ العامة');
$root = dirname(__DIR__);

$src = file_get_contents($root . '/ActivityLogs/activity_logs.php');
check(strpos($src, "serverSide: true") !== false, 'activity_logs: serverSide مفعَّل');
check(strpos($src, "'dt'") !== false && strpos($src, 'ems_dt_emit') !== false, 'activity_logs: نقطةُ ?ajax=dt بالمساعد');
check(strpos($src, 'getInitialPage') === false || strpos($src, '$initialRows = [];') !== false,
      'activity_logs: سقط تحميلُ الـ5000 صف');
check(strpos($src, 'pageLength: 50') !== false, 'activity_logs: صفحةُ 50');
check(strpos($src, 'searchDelay: 400') !== false, 'activity_logs: تأخيرُ البحث 400ms (UI-01 §4)');
check(strpos($src, 'ext.search.push') === false, 'activity_logs: سقطت التصفيةُ المتصفحية — الفلاترُ خادمية');

$src = file_get_contents($root . '/Finance/events_list_fin.php');
check(strpos($src, 'serverSide: true') !== false, 'events_list_fin: serverSide مفعَّل');
check(strpos($src, 'ems_dt_emit') !== false && strpos($src, 'OFFSET') !== false, 'events_list_fin: نقطةُ ?ajax=dt بصفحة LIMIT/OFFSET');
check(strpos($src, 'fin_event_row_cells') !== false, 'events_list_fin: بناءُ الصف دالةٌ تستهلكها النقطة');
check(substr_count($src, 'ORDER BY e.id DESC",') === 0, 'events_list_fin: سقط استعلامُ الدفتر الكامل بلا LIMIT');

$src = file_get_contents($root . '/assets/js/performance-boost.js');
check(strpos($src, 'pageLength: 50') !== false, 'performance-boost: الافتراضُ العام 50 (UI-01 §9)');
check(strpos($src, 'searchDelay: 400') !== false, 'performance-boost: تأخيرُ البحث العام 400ms');

$src = file_get_contents($root . '/includes/datatable_server.php');
check(strpos($src, 'ems_dt_params') !== false && strpos($src, 'ems_dt_emit') !== false
   && strpos($src, 'ems_dt_like_clause') !== false, 'المساعدُ العام قائمٌ بدواله الثلاث');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
