<?php
/**
 * tools/round_check_runner.php — مُلتقِطُ طلبِ الفحصِ من الشاشة
 * ═══════════════════════════════════════════════════════════════════════════
 * **الشاشةُ لا تُشغِّل شيئًا — تكتب طلبًا فحسب.** وسببُ ذلك مقيسٌ لا مظنون:
 * إطلاقُ عمليةٍ من داخلِ Apache على ويندوز يُربك عمليةَ الأبِ فتظهر
 * `AH02965: Child: Unable to retrieve my generation from the parent`
 * في سجلِّ الخادم (وقع فعلًا 2026-09-07). والتشغيلُ المتزامنُ يتجاوز
 * `max_execution_time` فينتهي بمهلةٍ لا بنتيجة.
 *
 * ◆ **فالدورةُ ثلاثُ خطواتٍ آمنة**: الشاشةُ تكتب `request.flag` ⇐ هذه الأداةُ
 *   (بمهمّةٍ مجدولةٍ كلَّ دقيقة) تلتقطه وتُشغّل الفحصَ ⇐ الشاشةُ تعرض النتيجة.
 *
 * ◆ **ورخيصةٌ حين لا طلب**: تخرج فورًا إن لم يوجد العَلَم — فتشغيلُها كلَّ
 *   دقيقةٍ لا يكلّف شيئًا.
 *
 * التشغيل: php tools/round_check_runner.php
 * التسجيل: schtasks /Create /TN EMS_round_check /SC MINUTE /MO 1 ^
 *            /TR "\"<php.exe>\" \"<tools/round_check_runner.php>\"" /F
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$DIR  = $ROOT . '/storage/round_check';
$REQ  = $DIR . '/request.flag';
$LOCK = $DIR . '/running.lock';
$OUT  = $DIR . '/last.txt';
$TOOL = $ROOT . '/tools/round_closeout.php';
if (!is_dir($DIR)) { @mkdir($DIR, 0777, true); }

/* ── القفلُ العالقُ يسقط بعدَ عشرِ دقائقَ كي لا يُجمَّد الفحصُ أبدًا ─────── */
if (is_file($LOCK) && (time() - (int) @filemtime($LOCK) > 600)) { @unlink($LOCK); }
if (is_file($LOCK)) { exit("فحصٌ يعمل الآن — يُترك\n"); }
if (!is_file($REQ)) { exit(0); }        /* لا طلبَ — خروجٌ صامتٌ رخيص */
if (!is_file($TOOL)) { @unlink($REQ); exit("⛔ أداةُ الفحصِ مفقودة\n"); }

/* ── الطلبُ يُستهلَك أوّلًا: فلا يُعاد الفحصُ إن سقط المُشغِّلُ في منتصفِه ── */
$who = trim((string) @file_get_contents($REQ));
@unlink($REQ);
@file_put_contents($LOCK, (string) time());

$t0 = microtime(true);
$out = array(); $rc = 0;
@exec('"' . PHP_BINARY . '" "' . $TOOL . '" 2>&1', $out, $rc);
$body = implode("\n", $out);
$head = 'تشغيلٌ بطلبٍ من الشاشة' . ($who !== '' ? ' · ' . $who : '')
      . ' · زمنٌ ' . number_format(microtime(true) - $t0, 1) . ' ثانية' . "\n\n";
@file_put_contents($OUT, $head . $body);
@unlink($LOCK);
printf("✔ اكتمل · رمزُ الخروج %d · %s\n", $rc, $OUT);
exit(0);
