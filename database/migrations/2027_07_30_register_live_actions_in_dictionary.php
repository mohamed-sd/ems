<?php
/**
 * 2027_07_30_register_live_actions_in_dictionary.php — العملياتُ خارجَ القاموس ← صفر
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب (ثامنًا-٢): «العملياتُ خارجَ القاموس ← صفر».
 *
 * ◆ **والرقمُ المُعلَنُ في v25 وفي التقريرِ الإداريِّ «160» بلا مصدرٍ يُعيد إنتاجَه.**
 *   والفاحصُ القائمُ `tools/u9_action_usage_matrix.php` — وهو الوحيدُ الذي يقيسُ
 *   هذا البندَ — يعطي **75 من 92** بتعريفٍ مكتوبٍ في رأسِه (فحص ⑧): «رمزٌ حيٌّ في
 *   `actions` لا يعرفه القاموسُ `nav09_action_map` لا كرمزٍ ولا كـ`live_code`».
 *   وقِيست ثلاثةُ تعريفاتٍ بديلةٍ فلم يعطِ أيٌّ منها 160: الظاهرُ في الكودِ بلا
 *   ربطٍ موثَّق **203**، والقاموسُ بلا أثرٍ في الكود **149**، والـ`actions` بلا
 *   معالجٍ مُسنَد **33**. **فالمُنفَّذُ هنا على المقيسِ لا على المنقول.**
 *
 * ◆ **والتسجيلُ يقرأ ولا يخترع**: الـ43 عمليةَ نطاقٍ تُنقل تسمياتُها العربيةُ من
 *   `actions.name_ar` حرفًا (وهي تسمياتٌ مؤلَّفةٌ سلفًا: «إصدارُ فاتورةٍ ضريبية»
 *   · «عكسُ إهلاكِ فترةٍ بمرجعِه»…). و**الـ32 مسارَ ajax وحدَها** تُؤلَّف لها
 *   تسميةٌ عربيةٌ لأن المسجَّلَ فيها معرِّفٌ تقنيٌّ لا اسمٌ («معالجُ get_messages»)
 *   — **وما لا يُسمّى لا يُعرَّف، وما لا يُعرَّف لا يُتتبَّع** وهي عِلّةُ البندِ نفسِها.
 *
 * ◆ **ولا يُغلَق بالتسمية ما لم يُبنَ**: 31 من الـ75 **بلا معالجٍ على القرص**،
 *   فتُسجَّل `declared_unbuilt` صراحةً. فالعددُ «خارجَ القاموس» يصير صفرًا،
 *   و**دَينُ الواحدةِ والثلاثين يبقى مرئيًّا باسمِه** لا مطويًّا تحت الإغلاق.
 *
 * ◆ صفرُ فقد: `INSERT ... ON DUPLICATE KEY UPDATE` لا يمسُّ صفًّا قائمًا إلا
 *   بملءِ `write_class` الفارغِ — ولا يُعاد كتابةُ تسميةٍ ولا حالةٍ مقرَّرة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/* ══ التسمياتُ العربيةُ لمساراتِ ajax — مؤلَّفةٌ لأن المسجَّلَ معرِّفٌ تقنيّ ══ */
$AJAX_AR = array(
    'ajax.chats.get_messages'                          => 'جلبُ رسائلِ محادثة',
    'ajax.chats.get_unread_count'                      => 'عدُّ الرسائلِ غيرِ المقروءة',
    'ajax.contracts.get_contract_equipments'           => 'معدّاتُ عقدٍ بعينِه',
    'ajax.contracts.get_equipments'                    => 'قائمةُ المعدّاتِ للاختيار',
    'ajax.employees.employee_contract_actions_handler' => 'أفعالُ عقدِ موظفٍ من السجل',
    'ajax.employees.get_employee_contract_equipments'  => 'معدّاتُ عقدِ موظف',
    'ajax.employees.get_employee_data'                 => 'بياناتُ موظفٍ للنموذج',
    'ajax.employees.get_mine_contracts'                => 'عقودُ الموظفِ الجارية',
    'ajax.employees.get_project_hours'                 => 'ساعاتُ موظفٍ في مشروع',
    'ajax.equipments.get_contract_stats'               => 'موجزُ عقدِ معدّة',
    'ajax.equipments.get_equipment_details'            => 'تفاصيلُ معدّةٍ للبطاقة',
    'ajax.equipments.get_mine_contracts'               => 'عقودُ المعدّةِ الجارية',
    'ajax.equipments.get_model_data'                   => 'بياناتُ طرازِ معدّة',
    'ajax.maintenance.get_breakdown_count'             => 'عددُ الأعطالِ القائمة',
    'ajax.maintenance.get_open_orders_count'           => 'عددُ أوامرِ الصيانةِ المفتوحة',
    'ajax.maintenance.get_project_equipment'           => 'معدّاتُ مشروعٍ للصيانة',
    'ajax.oprators.get_contract_stats'                 => 'موجزُ عقدِ مشغِّل',
    'ajax.oprators.get_contract_suppliers'             => 'مورِّدو عقدِ التشغيل',
    'ajax.oprators.get_mine_contracts'                 => 'عقودُ المشغِّلِ الجارية',
    'ajax.reports.get_mine_contracts'                  => 'عقودُ التقريرِ الجارية',
    'ajax.suppliers.get_mine_contracts'                => 'عقودُ المورِّدِ الجارية',
    'ajax.suppliers.get_project_hours'                 => 'ساعاتُ مورِّدٍ في مشروع',
    'ajax.suppliers.get_supplier_contract_equipments'  => 'معدّاتُ عقدِ مورِّد',
    'ajax.suppliers.supplier_contract_actions_handler' => 'أفعالُ عقدِ مورِّدٍ من السجل',
    'ajax.tickets.get_tickets_count'                   => 'عددُ البلاغاتِ القائمة',
    'ajax.timesheet.get_contract_hours'                => 'ساعاتُ عقدٍ في الورقة',
    'ajax.timesheet.get_drivers'                       => 'السائقون المتاحون للورقة',
    'ajax.timesheet.get_failure_codes'                 => 'رموزُ التوقفِ للاختيار',
    'ajax.timesheet.get_operations'                    => 'عملياتُ الوردياتِ للاختيار',
    'ajax.timesheet.get_timesheet'                     => 'ورقةُ ساعاتٍ بعينِها',
    'ajax.timesheet.get_timesheet_data'                => 'بياناتُ ورقةِ الساعات',
    'ajax.timesheet.get_timesheet_failures'            => 'توقفاتُ ورقةِ الساعات',
);

/* ══ الشاشةُ المسؤولةُ تُشتقُّ من مسارِ المعالجِ لا تُخترع ══════════════════ */
function screen_of(string $path, string $code): string {
    $path = trim($path);
    if ($path !== '') {
        $dir = trim(dirname(str_replace('\\', '/', $path)), './');
        if ($dir !== '' && $dir !== '.') { return $dir; }
    }
    $seg = explode('.', $code);
    return isset($seg[0]) ? $seg[0] : '—';
}

/* ══ ما يعرفه القاموسُ الآن ══════════════════════════════════════════════ */
$known = array();
$r = $conn->query("SELECT canonical_code, live_code FROM nav09_action_map");
while ($r && ($x = $r->fetch_assoc())) {
    $known[$x['canonical_code']] = 1;
    if ((string) $x['live_code'] !== '') { $known[$x['live_code']] = 1; }
}

$r = $conn->query("SELECT action_code, name_ar, is_write, is_financial, handler_path FROM actions ORDER BY action_code");
$rows = array();
while ($r && ($x = $r->fetch_assoc())) {
    if (isset($known[$x['action_code']])) { continue; }
    $rows[] = $x;
}
if (!$rows) { echo "◆ لا عمليةَ خارجَ القاموس — لا شيءَ يُسجَّل.\n"; exit(0); }

$ins = $conn->prepare(
    "INSERT INTO nav09_action_map
        (canonical_code, label_ar, screen_title, live_code, state, write_class, actor_ar)
     VALUES (?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE
        write_class = COALESCE(write_class, VALUES(write_class))"
);
if (!$ins) { exit("تعذّر التحضير: {$conn->error}\n"); }

$n = 0; $unbuilt = 0; $ajaxN = 0; $failed = 0;
foreach ($rows as $x) {
    $code  = (string) $x['action_code'];
    $isAjax = (strpos($code, 'ajax.') === 0);
    $label = $isAjax
        ? (isset($AJAX_AR[$code]) ? $AJAX_AR[$code] : (string) $x['name_ar'])
        : (string) $x['name_ar'];
    $path  = trim((string) $x['handler_path']);
    $screen = screen_of($path, $code);
    $live   = ($path !== '') ? ('page:' . $path) : '';
    $state  = ($path !== '') ? 'bound_page' : 'declared_unbuilt';
    $wclass = ((int) $x['is_write'] === 0) ? 'read_only' : 'domain_write';
    $actor  = 'سجلُّ الأفعالِ الحيّ (actions · ADR-06)';

    $ins->bind_param('sssssss', $code, $label, $screen, $live, $state, $wclass, $actor);
    if (!$ins->execute()) { echo "  ✘ {$code}: {$ins->error}\n"; $failed++; continue; }
    $n++;
    if ($path === '') { $unbuilt++; }
    if ($isAjax) { $ajaxN++; }
}
$ins->close();

/* ══ التحقّقُ من المُرجَعِ لا من النية ══════════════════════════════════════ */
$left = 0;
$known2 = array();
$r = $conn->query("SELECT canonical_code, live_code FROM nav09_action_map");
while ($r && ($x = $r->fetch_assoc())) {
    $known2[$x['canonical_code']] = 1;
    if ((string) $x['live_code'] !== '') { $known2[$x['live_code']] = 1; }
}
$r = $conn->query("SELECT action_code FROM actions");
while ($r && ($x = $r->fetch_row())) { if (!isset($known2[$x[0]])) { $left++; } }

echo "══ تسجيلُ العملياتِ الحيةِ في القاموس ══\n";
echo "  سُجِّل: {$n}" . ($failed ? " · أخفق: {$failed}" : '') . "\n";
echo "    · مساراتُ ajax بتسميةٍ عربيةٍ مؤلَّفة: {$ajaxN}\n";
echo "    · عملياتُ نطاقٍ بتسميتِها المنقولةِ من actions: " . ($n - $ajaxN) . "\n";
echo "  ◆ منها **{$unbuilt} بلا معالجٍ على القرص** — سُجِّلت `declared_unbuilt`،\n";
echo "    فالدَّينُ مرئيٌّ باسمِه ولم يُطوَ تحت الإغلاق.\n";
echo "  المتبقي خارجَ القاموس (مُرجَعٌ مقيسٌ بعد الكتابة): {$left}\n";
echo (($left === 0 && $failed === 0) ? "✔ صفر\n" : "✘ لم يبلغْ صفرًا\n");
exit(($left === 0 && $failed === 0) ? 0 : 1);
