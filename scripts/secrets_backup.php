<?php
/**
 * scripts/secrets_backup.php — المصدر الثاني للأسرار (PLAN-05 §3-⑩)
 * ═══════════════════════════════════════════════════════════════════════════
 * «مصدرٌ ثانٍ للأسرار (خزينةٌ أو نسخةٌ مشفَّرة)» — نسخة AES-256-GCM من .env:
 *   • المفتاح في ملفٍ خارج جذر الويب (%USERPROFILE%/.ems_secrets_key) —
 *     يولَّد مرةً واحدة ولا يدخل المستودع ولا مجلد الموقع.
 *   • الناتج في database/backups/secrets/env_YYYYmmdd_HHMMSS.enc
 *     (iv + tag + ciphertext بترميز base64).
 * الاستعادة: php scripts/secrets_backup.php --restore <ملف.enc> [هدف]
 * التشغيل الدوري: يُستدعى من الدوريات أو يدويًّا قبل أي تغيير جوهري.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

$ROOT = dirname(__DIR__);
$envFile = $ROOT . '/.env';
$keyFile = rtrim(getenv('USERPROFILE') ?: getenv('HOME') ?: sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . '.ems_secrets_key';
$outDir = $ROOT . '/database/backups/secrets';

if (!is_dir($outDir)) { mkdir($outDir, 0700, true); }

// المفتاح: يولَّد مرة واحدة خارج جذر الويب
if (!is_readable($keyFile)) {
    $key = bin2hex(random_bytes(32));
    if (file_put_contents($keyFile, $key) === false) {
        fwrite(STDERR, "✘ تعذّر كتابة ملف المفتاح: {$keyFile}\n");
        exit(1);
    }
    echo "✔ وُلّد مفتاح التشفير (مرة واحدة): {$keyFile}\n";
}
$key = hex2bin(trim(file_get_contents($keyFile)));

if (isset($argv[1]) && $argv[1] === '--restore') {
    $enc = isset($argv[2]) ? $argv[2] : null;
    $target = isset($argv[3]) ? $argv[3] : $envFile . '.restored';
    if (!$enc || !is_readable($enc)) { fwrite(STDERR, "✘ مرّر مسار ملف .enc\n"); exit(1); }
    $blob = base64_decode(file_get_contents($enc));
    $iv = substr($blob, 0, 12); $tag = substr($blob, 12, 16); $ct = substr($blob, 28);
    $plain = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) { fwrite(STDERR, "✘ فك التشفير فشل — مفتاح مغاير أو ملف تالف\n"); exit(1); }
    file_put_contents($target, $plain);
    echo "✔ استُعيد إلى: {$target}\n";
    exit(0);
}

if (!is_readable($envFile)) { fwrite(STDERR, "✘ .env غير مقروء\n"); exit(1); }
$plain = file_get_contents($envFile);
$iv = random_bytes(12);
$tag = '';
$ct = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
if ($ct === false) { fwrite(STDERR, "✘ التشفير فشل\n"); exit(1); }
$out = $outDir . '/env_' . date('Ymd_His') . '.enc';
file_put_contents($out, base64_encode($iv . $tag . $ct));

// تحقق ذاتي: فك الناتج ومطابقته بايت-بايت قبل إعلان النجاح
$blob = base64_decode(file_get_contents($out));
$plain2 = openssl_decrypt(substr($blob, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($blob, 0, 12), substr($blob, 12, 16));
if ($plain2 !== $plain) { fwrite(STDERR, "✘ التحقق الذاتي فشل — النسخة لا تُطابق\n"); @unlink($out); exit(1); }
echo "✔ نسخة مشفَّرة متحقَّق منها: {$out} (" . strlen($plain) . " بايت)\n";
exit(0);
