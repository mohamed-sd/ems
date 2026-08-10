<?php
/**
 * tools/fix_probe_export.php — نداءٌ حيٌّ لمحرّكِ التصدير (شاهدُ AC-F3)
 * ═══════════════════════════════════════════════════════════════════════════
 * يُحمِّل ‎excel.php‎ فعلًا في عمليةٍ منفصلةٍ بلا جلسةٍ (فيخرج 401 بعدَ تنفيذِ
 * رأسِه)، ثم يعلن — من داخلِ ‎register_shutdown_function‎ — أَحُمِّلت طبقةُ
 * الصلاحيات؟ ◆ هذا إثباتٌ بالتحميلِ الفعليِّ لا بمطابقةِ سطرِ ‎require‎ في نصّ.
 *
 * ويعلن كذلك: أيُحجب حقلٌ حساسٌ لدورٍ بلا منحٍ وفقَ حاكمِ الحقول؟ (نداءُ دالةٍ).
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/excel.php';
$_SERVER['REQUEST_URI'] = '/ems/excel.php?entity=suppliers&action=export';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['entity'] = 'suppliers';
$_GET['action'] = 'export';

$verdict = array('helper_loaded' => false, 'governor_loaded' => false, 'blocked_sample' => array(), 'log_table' => false);

register_shutdown_function(static function () use (&$verdict) {
    // يُنفَّذ بعدَ خروجِ excel.php بالـ401 — ورأسُه قد نُفِّذ بالفعل.
    $verdict['helper_loaded'] = function_exists('check_page_permissions');
    // ◆ المحمِّلُ التلقائيُّ لا يُسجَّل في وضعِ CLI — فنُحمِّل الحاكمَ بالمسارِ نفسِه
    //   الذي تُحمِّله به ExcelService (تحميلٌ صريحٌ لا اتكال).
    $gf = dirname(__DIR__) . '/app/Services/Governance/FieldGovernor.php';
    if (!class_exists('\\App\\Services\\Governance\\FieldGovernor', false) && is_file($gf)) { require_once $gf; }
    $verdict['governor_loaded'] = class_exists('\\App\\Services\\Governance\\FieldGovernor', false);
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $db = $GLOBALS['conn'];
        $r = $db->query("SELECT COUNT(*) FROM information_schema.TABLES
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gov_export_log'");
        $verdict['log_table'] = $r && (int) $r->fetch_row()[0] > 0;
        if ($verdict['governor_loaded']) {
            // دورٌ بلا أيِّ منحٍ فرديٍّ على حقولِ المورد البنكية ⇒ يجب أن تُحجب.
            $g = \App\Services\Governance\FieldGovernor::exportableColumns(
                $db, 'suppliers', 999, array('id', 'name', 'bank_account_no', 'bank_iban'), false);
            $verdict['blocked_sample'] = $g['blocked'];
        }
    }
    // ننظّف أيَّ خرجٍ سابقٍ (الـ401 JSON) ثم نطبع الحكمَ في سطرٍ موسومٍ وحدَه.
    while (ob_get_level()) { @ob_end_clean(); }
    echo "\nEXPORT|" . json_encode($verdict, JSON_UNESCAPED_UNICODE) . "\n";
});

// لا جلسةَ مستخدم ⇒ excel.php يخرج 401 بعدَ تنفيذِ رأسِه (وهذا هو المقصود).
require $ROOT . '/excel.php';
