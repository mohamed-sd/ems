<?php
/**
 * cron_events_task.php — مدخلُ المهمّةِ المجدولةِ لعاملِ الأحداث (FINAL_CLOSE ⑩)
 * ═══════════════════════════════════════════════════════════════════════════
 * `RPR-03` #١٩ · E1: مهمّةُ الويندوز `EMS_cron_events` كانت تنفّذ
 * `cron_events.php` رأسًا — **وحارسُ التشغيلِ اليدويِّ يصدُّه** (rc=3) فتعمل
 * المهمّةُ ولا تفعل شيئًا، ويسكن الناقلُ كلُّه صامتًا والدفترُ يقرؤه نجاحًا.
 *
 * ◆ هذا المدخلُ يدخل من **البابِ المشروعِ الوحيدِ** الذي أعلنه الحارسُ نفسُه
 *   (`includes/manual_run_guard.php`): «العاملُ الخلفيُّ يضبط الثابتَ قبلَ
 *   التضمين» — فالمهمّةُ المجدولةُ عاملٌ خلفيٌّ لا تشغيلٌ يدويّ.
 * ◆ ⛔ ولا منطقَ هنا غيرُ الدخول — كلُّ العملِ في `cron_events.php` نفسِه.
 *
 * التشغيل: المهمّةُ المجدولة `EMS_cron_events` (أو php cron_events_task.php)
 *
 * ◆ CLOSURE_SYSTEM · WORK-03 — قبولُ الجدولةِ يشترط نبضًا يتحرّك وصفرَ
 *   تشغيلٍ مزدوج، وكلاهما هنا لا في العامل:
 *   ① **قفلُ الازدواج** `flock` غيرُ حاجبٍ على ملفِّ قفلٍ — تشغيلةٌ تجد
 *     سابقتَها حيّةً تسجّل `SKIP duplicate-invocation` وتنصرف صفرًا: فالمهمّةُ
 *     تتكرّر كلَّ ربعِ ساعةٍ وتشغيلةٌ طويلةٌ لا يجوز أن تُزاحَم على المؤشّر.
 *   ② **النبضُ** `storage/logs/cron_events.log`: سطرُ START بمعرِّفِ العمليةِ
 *     وسطرُ END بمخرجِ العاملِ كاملًا — فمخرجُ `php-win.exe` يضيع بلا التقاط،
 *     ونبضٌ لا يُقرأ ليس نبضًا. والالتقاطُ بدالّةِ إطفاءٍ لأنَّ حارسَ التشغيلِ
 *     اليدويِّ قد يُنهي العاملَ بـ`exit` قبل بلوغِ آخرِ سطرٍ هنا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
define('EMS_JOB_WORKER', true);

/* ساعةُ السجلِّ ساعةُ التطبيقِ نفسُها (config.php:274) — سطرا START/SKIP يُكتبان
   قبل تحميلِها فبِلاها يحملان ساعةَ النظامِ ويقرأ السجلُّ بساعتَين. */
date_default_timezone_set('Africa/Cairo');

$emsCronLogDir = __DIR__ . '/storage/logs';
if (!is_dir($emsCronLogDir)) { @mkdir($emsCronLogDir, 0775, true); }
$emsCronLog  = $emsCronLogDir . '/cron_events.log';
$emsCronLock = fopen($emsCronLogDir . '/cron_events.lock', 'c');

if ($emsCronLock === false || !flock($emsCronLock, LOCK_EX | LOCK_NB)) {
    file_put_contents($emsCronLog,
        '[' . date('Y-m-d H:i:s') . '] SKIP duplicate-invocation — قفلُ تشغيلةٍ سابقةٍ قائم' . "\n",
        FILE_APPEND);
    exit(0);
}

file_put_contents($emsCronLog,
    '[' . date('Y-m-d H:i:s') . '] START pid=' . getmypid() . "\n", FILE_APPEND);
ob_start();
register_shutdown_function(function () use ($emsCronLog, $emsCronLock) {
    $out = '';
    while (ob_get_level() > 0) { $out .= (string) ob_get_clean(); }
    $err = error_get_last();
    $tail = ($err !== null && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true))
        ? 'END rc=1 FATAL ' . $err['message'] . ' @ ' . $err['file'] . ':' . $err['line']
        : 'END rc=0';
    file_put_contents($emsCronLog,
        $out . '[' . date('Y-m-d H:i:s') . '] ' . $tail . "\n", FILE_APPEND);
    flock($emsCronLock, LOCK_UN);
});

require __DIR__ . '/cron_events.php';
