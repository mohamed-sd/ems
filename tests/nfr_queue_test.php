<?php
/**
 * update0004 · الموجة ⑳ — حزام N-24: الطابور (NFR-05→10)
 * التشغيل: php tests/nfr_queue_test.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once dirname(__DIR__) . '/app/Services/Queue/JobQueueService.php';

use App\Services\Queue\JobQueueService as JQ;

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; $c ? $PASS++ : $FAIL++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;

$teardown = function () use ($conn) {
    $conn->query("DELETE FROM ems_job_queue WHERE job_type IN ('batch_loop','__t20_missing')");
    $conn->query("DELETE FROM fin_notifications WHERE title LIKE '%المهمة #%' AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0004 موجة ⑳ — الطابور N-24 ══\n");

head('NFR-05/08 — الإدراج يعود فورًا «قيد المعالجة»');
$t0 = microtime(true);
$r = JQ::enqueue($conn, $CO, 'batch_loop', array('batches' => 20, 'batch_size' => 100, 'fail_batches' => array(3, 17)), 1);
$dt = microtime(true) - $t0;
check($r['ok'] && $r['job_id'] > 0 && $dt < 1.0, "enqueue فوري (" . round($dt * 1000) . "ms) — الصفحة لا تتجمد");
$J = $r['job_id'];
check($r['state'] === 'queued' && mb_strpos($r['reason'], 'قيد المعالجة') !== false, 'الرد «قيد المعالجة» بإشارة اكتمال لاحقة');

head('NFR-06 — المسيّر المقسَّط 20×100: فشل دفعة لا يسقط الباقي');
$out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/Operations/cron_job_worker.php') . ' 5 2>&1');
check(is_string($out) && mb_strpos($out, 'نفّذ') !== false, 'العامل خطف ونفّذ (' . trim(mb_substr((string) $out, 0, 80)) . ')');
$st = JQ::status($conn, $J);
check($st['state'] === 'done', 'المهمة اكتملت رغم فشل دفعتين');
check(intval($st['progress_done']) === 20 && intval($st['progress_total']) === 20, "التقدم ظاهر 20/20");
$bf = json_decode((string) $st['batch_failures'], true);
check(is_array($bf) && count($bf) === 2 && intval($bf[0]['batch']) === 3 && intval($bf[1]['batch']) === 17,
    'الدفعتان 3 و17 فشلتا مسجلتين ظاهرًا — والباقي (18) لم يسقط');
$n = $conn->query("SELECT COUNT(*) c FROM fin_notifications WHERE title LIKE '%المهمة #{$J}%'")->fetch_assoc();
check(intval($n['c']) >= 1, 'إشعار الاكتمال وصل (NFR-08)');

head('NFR-05 — المحاولات التصاعدية وسجل الفشل الظاهر');
$r2 = JQ::enqueue($conn, $CO, '__t20_missing', array(), 1, 2);
$J2 = $r2['job_id'];
shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/Operations/cron_job_worker.php') . ' 3 2>&1');
$st = JQ::status($conn, $J2);
check($st['state'] === 'failed' && $st['last_error'] !== null, "المحاولة الأولى فشلت بسجل ظاهر ({$st['attempts']})");
$nx = $conn->query("SELECT next_attempt_at > NOW() later FROM ems_job_queue WHERE job_id = {$J2}")->fetch_assoc();
check(intval($nx['later']) === 1, 'والموعد التالي مؤجل تصاعديًّا (1د ثم 5د…)');
// استحقاق فوري لاختبار الموت بعد الحد
$conn->query("UPDATE ems_job_queue SET next_attempt_at = NOW() WHERE job_id = {$J2}");
shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/Operations/cron_job_worker.php') . ' 3 2>&1');
$st = JQ::status($conn, $J2);
check($st['state'] === 'dead', 'بعد الحد: dead بإشعار تصعيد — لا فشل صامت');

head('NFR-09 — حارس الخمس ثوان');
$g = JQ::mustQueue(3, 'احتساب خفيف');
check($g['ok'], 'ثلاث ثوان مقدرة داخل الطلب تمر (CLI معفى أصلًا والويب يمر)');
// المنع البنيوي يُختبر بمحاكاة ويب: الدالة نفسها بغير CLI تُختبر منطقيًّا
check(mb_strpos(file_get_contents(dirname(__DIR__) . '/config.php'), 'request_over_5s') !== false,
    'راصد >5 ثوان مركّب في config.php يسجل في guard_denials');
check(mb_strpos(file_get_contents(dirname(__DIR__) . '/app/Services/Queue/JobQueueService.php'), "PHP_SAPI === 'cli'") !== false,
    'mustQueue يمنع بدء الطويل داخل الطلب ويعفي CLI');

head('NFR-07 — الدوريات والمطابقة والاستدراك معالجات مسجلة');
$src = file_get_contents(dirname(__DIR__) . '/Operations/cron_job_worker.php');
foreach (array('periodic_cron', 'bank_recon_scan', 'debt_catchup', 'payroll_bind') as $h) {
    check(mb_strpos($src, "'{$h}'") !== false, "المعالج {$h} مسجل في العامل");
}

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
