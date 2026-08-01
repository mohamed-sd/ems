<?php
/**
 * tests/csrf_client_helper_test.php — حارس الرقعة العميلة لـCSRF (ADR-05)
 * ═══════════════════════════════════════════════════════════════════════════
 * العلة المحروسة (2026-08-01): كان `assets/js/csrf.js` يخطف مسارين معًا —
 * `$.ajaxPrefilter` لجيكويري **و**رقعة `XMLHttpRequest.prototype.send` — فيُرفق
 * الرمز مرتين على الطلب الواحد. و`setRequestHeader` **تُلحق ولا تستبدل**، فتصل
 * الترويسة «token, token» ويسقط `hash_equals` على الخادم: **403 على كل طلب
 * jQuery POST تحت `CSRF_ENFORCE_PATHS`** (كسر اعتماد الساعات وتفاصيل العقود).
 *
 * يحرس أربعة عقود نصية على الملف (فحص ثابت لا عدد):
 *   C1 لا خطّاف jQuery — لأن jQuery يُرسل عبر XHR الأصلي فالرقعة تغطيه.
 *   C2 وسم الإرفاق `__emsCsrfSet` قائم — فلا إرفاق ثانٍ فوق إرفاقٍ يدوي.
 *   C3 رقعة `setRequestHeader` قائمة وتسجّل الوسم.
 *   C4 `send` يفحص الوسم قبل الإرفاق.
 *   C5 وسم السكربت في inheader يحمل بصمة إصدار (Cache-Control شهر).
 * التشغيل: php tests/csrf_client_helper_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

$PASS = 0; $FAIL = 0;
function ok($label, $cond) {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "  ✔ {$label}\n"; }
    else { $FAIL++; echo "  ✘ FAIL: {$label}\n"; }
}

$jsPath = dirname(__DIR__) . '/assets/js/csrf.js';
$js = is_file($jsPath) ? file_get_contents($jsPath) : '';
ok('ملف الرقعة قائم', $js !== '');

// الفحص على **الكود الحي** لا على التوثيق: التعليقات تشرح العلة بأسمائها
// (ajaxPrefilter …) فلو فُحص النصُّ خامًا لسقط الحارسُ على شرحه نفسه.
$code = preg_replace('#/\*.*?\*/#s', '', $js);
$code = preg_replace('#^\s*//.*$#m', '', $code);

echo "\n── الإرفاق مرة واحدة (العلة المحروسة) ──\n";

// C1: لا خطّاف jQuery — إرفاقٌ ثانٍ فوق رقعة XHR = «token, token» = 403 حتمي
ok('C1: لا خطّاف ajaxPrefilter في الكود الحي — لا إرفاق ثانٍ لطلبات jQuery',
    strpos($code, 'ajaxPrefilter') === false);
ok('C1-ب: ولا دالة hookJQuery باقية',
    strpos($code, 'hookJQuery') === false);

// C2·C3·C4: وسم الإرفاق يمنع التكرار حتى مع إرفاقٍ يدوي من شاشة
ok('C2: وسم الإرفاق __emsCsrfSet معرَّف', strpos($code, '__emsCsrfSet') !== false);
ok('C3: setRequestHeader مرقَّعة لتسجيل الإرفاق اليدوي',
    preg_match('/XMLHttpRequest\.prototype\.setRequestHeader\s*=/', $code) === 1
    && stripos($code, "'x-csrf-token'") !== false);
ok('C4: send يفحص الوسم قبل الإرفاق (لا إرفاق فوق إرفاق)',
    preg_match('/if\s*\(\s*!this\.__emsCsrfSet\s*&&/', $code) === 1);
ok('C4-ب: الوسم يُصفَّر عند open — فكائن XHR المعاد استعماله لا يرث وسمًا قديمًا',
    preg_match('/open\s*=\s*function[^}]*__emsCsrfSet\s*=\s*false/s', $code) === 1);

// الرقعتان الأصليتان باقيتان (الإصلاح لا يُسقط التغطية)
ok('تغطية XMLHttpRequest باقية', preg_match('/XMLHttpRequest\.prototype\.send\s*=/', $code) === 1);
ok('تغطية fetch باقية', strpos($code, 'window.fetch =') !== false);
ok('التقييد على same-origin وطرق التغيير باقٍ',
    strpos($code, 'sameOrigin') !== false && preg_match('/POST\|PUT\|PATCH\|DELETE/i', $code) === 1);

echo "\n── التوصيل والنشر ──\n";

$hdrPath = dirname(__DIR__) . '/inheader.php';
$hdr = is_file($hdrPath) ? file_get_contents($hdrPath) : '';
ok('الرقعة محمَّلة في الرأس قبل أي سكربت بيانات',
    strpos($hdr, 'assets/js/csrf.js') !== false);
// C5: بلا بصمة إصدار تبقى النسخة المعطوبة في المتصفحات شهرًا (max-age=2592000)
ok('C5: وسم csrf.js يحمل بصمة إصدار (filemtime) — فالإصلاح يصل المستخدم فورًا',
    preg_match('/csrf\.js[^"\']*\?v=/', $hdr) === 1
    || preg_match("/csrf\.js<\?php[^>]*filemtime/", $hdr) === 1);
ok('وسم meta الرمز مبثوث للرقعة', strpos($hdr, 'csrf_meta()') !== false);

// المعالجات تحت الإنفاذ تقرأ الحقل أو الترويسة — كلاهما مقبول
$secPath = dirname(__DIR__) . '/includes/security.php';
$sec = is_file($secPath) ? file_get_contents($secPath) : '';
ok('الحارس الخادمي يقبل الرمز من الحقل أو الترويسة',
    strpos($sec, "\$_POST['csrf_token']") !== false && strpos($sec, 'HTTP_X_CSRF_TOKEN') !== false);

echo "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n";
exit($FAIL === 0 ? 0 : 1);
