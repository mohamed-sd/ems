<?php
/**
 * admin/cron_backup.php — مُشغّل النسخ الاحتياطي المجدوَل لقاعدة EMS.
 * ───────────────────────────────────────────────────────────────────────────
 * طرق التشغيل:
 *   (أ) جدولة النظام (CLI):   php admin/cron_backup.php
 *   (ب) الويب بمفتاح:          admin/cron_backup.php?key=BACKUP_CRON_KEY
 *   (ج) الشبكة الاحتياطية:     php admin/cron_backup.php --lazy   (تُطلقها اللوحة)
 * يأخذ نسخةً مجدولةً فقط إن كانت الجدولة مفعّلةً وحان موعدها (ما لم يُمرَّر
 * --force أو ?force=1 فيأخذها فورًا). يتّبع نمط cron في المشروع (ADR-04):
 * CLI بلا مفتاح، ومسار الويب fail-closed إن كان BACKUP_CRON_KEY فارغًا.
 */

$IS_CLI = (PHP_SAPI === 'cli');

require __DIR__ . '/../config.php';
require __DIR__ . '/includes/db_tools.php';

if (!$IS_CLI) {
    $key = isset($_GET['key']) ? (string) $_GET['key'] : '';
    $expected = (string) ems_env('BACKUP_CRON_KEY', '');
    if ($expected === '' || !hash_equals($expected, $key)) {
        http_response_code(403);
        exit('forbidden');
    }
    header('Content-Type: text/plain; charset=UTF-8');
}

$argvSafe = (isset($argv) && is_array($argv)) ? $argv : array();
$force = in_array('--force', $argvSafe, true) || isset($_GET['force']);

$err = '';
$res = ems_dbtool_run_scheduled($err, $force);

$line = date('Y-m-d H:i:s') . ' [backup-cron] ' . $res['message'];
error_log($line);
echo $line . "\n";

// رمز خروجٍ غير صفريّ عند فشلٍ فعليّ (لا عند التخطّي) — لرصد جدولة النظام.
if (!$IS_CLI) {
    exit;
}
exit((!$res['ok'] && empty($res['skipped'])) ? 1 : 0);
