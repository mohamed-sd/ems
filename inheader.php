<?php
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
// UXW-01: مكوّناتُ الحالاتِ والخطوةِ التاليةِ متاحةٌ لكلِّ شاشةٍ داخلَ القشرة
require_once __DIR__ . '/includes/ux_components.php';
$iconsCss = function_exists('ems_url') ? ems_url('assets/css/all.min.css') : '../assets/css/all.min.css';

// Cache-busting version for unified stylesheets so CSS edits show immediately
// (the same constant is reused by insidebar.php's defensive CSS loader).
if (!defined('EMS_ASSET_VER')) {
    $__ems_main_css = __DIR__ . '/assets/css/ems.main.all.style.css';
    define('EMS_ASSET_VER', is_file($__ems_main_css) ? (string) filemtime($__ems_main_css) : '1');
}
if (!function_exists('ems_css_ver')) {
    function ems_css_ver($fileName)
    {
        $path = __DIR__ . '/assets/css/' . $fileName;
        return is_file($path) ? ('?v=' . filemtime($path)) : '';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (function_exists('csrf_meta')) { echo csrf_meta(); } ?>
    <!-- CSRF: يُلحق التوكن تلقائياً بكل طلب POST/PUT/DELETE (يجب أن يُحمّل قبل أي سكربت يطلب البيانات)
         بصمةُ الإصدار إلزامية: Cache-Control شهرٌ كامل، فنسخةٌ معطوبةٌ بلا بصمةٍ تبقى في
         متصفحات المستخدمين ثلاثين يومًا بعد إصلاحها (وقع فعلًا 2026-08-01). -->
    <script src="/ems/assets/js/csrf.js<?php $__csrfjs=__DIR__.'/assets/js/csrf.js'; echo is_file($__csrfjs)?('?v='.filemtime($__csrfjs)):''; ?>"></script>
    <!-- M-46: المسودةُ التلقائية كل 30 ثانية — تلتقط النماذجَ الطويلة آليًّا (UI-01 §3) -->
    <?php /* كاسرُ الذاكرةِ إلزاميٌّ كأخواتِه (csrf.js · ems-outbox.js): كان هذا
             الملفُّ **وحدَه بلا بصمة**، فأيُّ إصلاحٍ فيه يخدمه المتصفحُ من نسختِه
             القديمةِ حتى يُحدِّث المستخدمُ بالقوة. قِيس حيًّا: تصحيحُ صنفِ زرٍّ في
             لافتةِ المسودةِ لم يظهر إطلاقًا رغم أن الملفَّ على القرصِ مُصحَّح. */ ?>
    <script src="/ems/includes/js/ems-autosave.js<?php $__asjs = __DIR__ . '/includes/js/ems-autosave.js'; echo is_file($__asjs) ? ('?v=' . filemtime($__asjs)) : ''; ?>" defer></script>
    <title><?php echo $page_title ; ?></title>

    <!-- Font awsome icon link مكتبة الايقونات -->
    <link rel="stylesheet" href="<?php echo $iconsCss; ?>">

    <!-- Call bootstrap 5 -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
    <!-- إضافةُ Responsive مرفوعةٌ من النظام (قرار المالك 2026-08-09): لا طيَّ أعمدةٍ
         تحت سهمٍ — كلُّ الأعمدة جنبًا إلى جنبٍ بتمريرٍ أفقيٍّ كالإكسل (ems-tables.css). -->
    <link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="/ems/assets/css/local-fonts.css">
    <!-- لوحةُ الألوانِ: تُحمَّل أولًا وكلُّ لونٍ في النظامِ يشير إليها. وكاسرُ
         الذاكرةِ إلزاميٌّ هنا كما لأخواتها — بدونه يخدم المتصفحُ نسخةً قديمةً
         فتسقط كلُّ `var()` إلى الافتراضيّ والنظامُ بلا ألوان. -->
    <link rel="stylesheet" href="/ems/assets/css/design-tokens.css<?php echo ems_css_ver('design-tokens.css'); ?>">
    <!-- INJAZ-UXUI-01 ف١٢: رموزُ جولةِ الواجهةِ ومكتبةُ مكوّناتِها (بادئة ux-) —
         طبقةٌ فوق الرموزِ القائمة، خاملةٌ حتى تستعملَها شاشةٌ مرحَّلة -->
    <link rel="stylesheet" href="/ems/assets/css/uxui-tokens.css<?php echo ems_css_ver('uxui-tokens.css'); ?>">
    <link rel="stylesheet" href="/ems/assets/css/uxui-components.css<?php echo ems_css_ver('uxui-components.css'); ?>">
    <!-- Unified page styles: Dashboard + Chat -->
    <link rel="stylesheet" href="/ems/assets/css/ems.main.all.style.css<?php echo ems_css_ver('ems.main.all.style.css'); ?>">
    <!-- Unified Table Styles — loaded LAST so ems-tables.css is the single authoritative source for all table design -->
    <link rel="stylesheet" href="/ems/assets/css/ems-tables.css<?php echo ems_css_ver('ems-tables.css'); ?>">
    <!-- Unified Form Styles — loaded LAST so ems-forms.css is the single authoritative source for ALL form design -->
    <link rel="stylesheet" href="/ems/assets/css/ems-forms.css<?php echo ems_css_ver('ems-forms.css'); ?>">
    <!-- أنماطُ الأزرارِ الأربعةُ — تُحمَّل بعد بوتستراب لتَغلبَ أزرقَه بذهبيِّ العلامة -->
    <link rel="stylesheet" href="/ems/assets/css/ems-buttons.css<?php echo ems_css_ver('ems-buttons.css'); ?>">
    <!-- شريط الرحلة الموحّد (الدستور §5) — مكوّنٌ واحدٌ للطلب والبلاغ والوحدة -->
    <link rel="stylesheet" href="/ems/assets/css/ems-journey.css<?php echo ems_css_ver('ems-journey.css'); ?>">
    <!-- نظام الرسائل الموحّد: توست عابر + لافتة سطرية بلغةٍ بصريةٍ واحدة -->
    <link rel="stylesheet" href="/ems/assets/css/ems-alerts.css<?php echo ems_css_ver('ems-alerts.css'); ?>">
    <!-- قشرة التطبيق الموحدة (UXR-01 · AS-01..08) — تُحمَّل بعد الهوية فتحكم القياسات -->
    <link rel="stylesheet" href="/ems/assets/css/ems-shell.css<?php echo ems_css_ver('ems-shell.css'); ?>">
    <!-- UXW-01: حالاتُ الشاشةِ التسعُ والخطوةُ التالية — مكوّنٌ واحدٌ لكلِّ الشاشات -->
    <link rel="stylesheet" href="/ems/assets/css/ems-states.css<?php echo ems_css_ver('ems-states.css'); ?>">
    <!-- INJ-0442: أنماطُ الشاشاتِ الخاصةُ — تُحمَّل **آخرًا** كما كانت كتلُها
         داخلَ الصفحة، فالنقلُ لا يغيّر مَن يغلب مَن. -->
    <link rel="stylesheet" href="/ems/assets/css/ems-screens.css<?php echo ems_css_ver('ems-screens.css'); ?>">
    <!-- شاشةُ التذكرة — مكوّناتُها مُنطاقةٌ تحت .tkt-form-main فلا تتسرّب لغيرها -->
    <link rel="stylesheet" href="/ems/assets/css/ems-ticket.css<?php echo ems_css_ver('ems-ticket.css'); ?>">
    <!-- INJ-0378 · INJ-0548: صندوقُ الإرسالِ دونَ اتصالٍ وعاملُ الخدمة —
         الطابورُ يعمل على النماذجِ الموسومةِ صراحةً وحدَها، والعاملُ يخزّن
         الأصولَ الساكنةَ لا صفحاتِ PHP (صفحةٌ محفوظةٌ ببياناتٍ قديمةٍ أخطرُ
         من غيابها). -->
    <script src="/ems/assets/js/ems-outbox.js<?php $__objs=__DIR__.'/assets/js/ems-outbox.js'; echo is_file($__objs)?('?v='.filemtime($__objs)):''; ?>" defer></script>
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/ems/sw.js', { scope: '/ems/' })
                .catch(function () { /* بلا عاملِ خدمةٍ يبقى النظامُ عاملًا — تحسينٌ لا شرط */ });
        });
    }
    </script>
    <!-- نظام الرسائل الموحّد — بلا defer عمدًا: 50 رسالةً يطبعها الخادمُ داخل
         <script>alert(…)</script> في متن الصفحة، ولو تأخّر التقاطُ alert()
         لسبقتنا وخرجت بنافذة المتصفح الحاجزة. التوستُ يُصفُّ حتى يجهز body. -->
    <script src="/ems/assets/js/ems-alerts.js<?php $__altjs=__DIR__.'/assets/js/ems-alerts.js'; echo is_file($__altjs)?('?v='.filemtime($__altjs)):''; ?>"></script>
    <script src="../assets/js/performance-boost.js" defer></script>
    <script src="/ems/assets/js/ui-unification.js<?php $__uijs=__DIR__.'/assets/js/ui-unification.js'; echo is_file($__uijs)?('?v='.filemtime($__uijs)):''; ?>" defer></script>
    <!-- حارس الحد الأدنى للأعمدة (AC-U4): مصدرٌ واحدٌ يقيس ويبيّن — يسبق مستهلكيه -->
    <script src="/ems/assets/js/ems-column-floor.js<?php $__cfjs=__DIR__.'/assets/js/ems-column-floor.js'; echo is_file($__cfjs)?('?v='.filemtime($__cfjs)):''; ?>"></script>
    <!-- نواة مكونات الواجهة (UXR-01 UI-01..20): الحالات والبطاقات وحارس صفر الأعمدة -->
    <script src="/ems/assets/js/ems-components.js<?php $__cmpjs=__DIR__.'/assets/js/ems-components.js'; echo is_file($__cmpjs)?('?v='.filemtime($__cmpjs)):''; ?>" defer></script>
    <!-- Unified column-groups show/hide (activated per-page via EmsColumnGroups.init) -->
    <script src="/ems/assets/js/column-groups.js<?php $__cgjs=__DIR__.'/assets/js/column-groups.js'; echo is_file($__cgjs)?('?v='.filemtime($__cgjs)):''; ?>" defer></script>
    <!-- Unified Details/View Modal System (نظام نافذة العرض الموحّد) -->
    <script src="/ems/assets/js/ems-details-modal.js<?php $__dmjs=__DIR__.'/assets/js/ems-details-modal.js'; echo is_file($__dmjs)?('?v='.filemtime($__dmjs)):''; ?>" defer></script>
    <!-- بطاقة «عن الشاشة» الموحّدة: تبني القالبَ الصادرَ من screen_contract.php
         في موضعه الصحيح (تحت الرأس) وتزرع زرَّه — راجع رأس الملف للعلّة. -->
    <script src="/ems/assets/js/ems-screen-about.js<?php $__abtjs=__DIR__.'/assets/js/ems-screen-about.js'; echo is_file($__abtjs)?('?v='.filemtime($__abtjs)):''; ?>" defer></script>
    <!-- Unified Custom Select dropdown for forms (نظام القوائم المنسدلة الموحّد) -->
    <script src="/ems/assets/js/ems-select.js<?php $__emsjs=__DIR__.'/assets/js/ems-select.js'; echo is_file($__emsjs)?('?v='.filemtime($__emsjs)):''; ?>" defer></script>
    <!-- Bootstrap Bundle JS (local, CSP-safe) -->
    <script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <?php
    // CMP-03 ②: سياق طبقة الحوكمة المشتركة — قيم عامة (الكيان · العملة الأساسية)
    // يقرأها ui-unification.js لحشو خلايا أعمدة الحوكمة المحقونة قبل تهيئة الجداول.
    if (isset($_SESSION['user'])) {
        require_once __DIR__ . '/includes/gov_columns.php';
        ems_gov_emit_assets();
    }
    ?>
</head>
<body class="ems-site"<?php
/* CM-00 (DEC-E · update0010): بذرُ محاورِ الغلافِ الحاكم من الخادم — الشاشةُ
   المتبنيةُ تملأ $GLOBALS['EMS_AX'] قبل تضمين inheader (العون ems_shell_axes
   في screen_contract.php) — وEmsScreenShell.seed() يلتقطها من data-ems-ax-*.
   لا شيءَ يُطبع لغير المتبنية — فالتبني يُقاس لا يُفترض (القرار الحاكم ١). */
if (!empty($GLOBALS['EMS_AX']) && is_array($GLOBALS['EMS_AX'])) {
    foreach ($GLOBALS['EMS_AX'] as $axK => $axV) {
        if (preg_match('/^[a-z-]+$/', (string) $axK) && preg_match('/^[a-z-]+$/', (string) $axV)) {
            echo ' data-ems-ax-' . $axK . '="' . $axV . '"';
        }
    }
}
?>>
<!-- AC-U8 · SH-08: رابطُ التخطّي — أولُ ما تبلغه لوحةُ المفاتيح.
     بدونَه تُجبَر الدورةُ على المرورِ بالشريطِ الجانبيِّ كلِّه (عشراتُ الروابط)
     قبلَ بلوغِ المحتوى، فتصير الدورةُ «كاملةً» ورقيًّا لا عمليًّا. مخفيٌّ حتى
     يُركَّز عليه — لا يزاحم البصرَ ويحضر للوحةِ المفاتيح. -->
<a class="ems-skip-link" href="#ems-main-content">تخطّي إلى المحتوى</a>
<?php
/* GOV-AUTH-01 §6-2 — شريطُ جلسةِ النيابةِ الدائمُ الظاهر: «تعمل الآن موضعَ
   فلان — بسببِ كذا — تنتهي في كذا». لا يُخفى عن الفاعلِ نفسِه فينسى أنه
   فيها — وإنهاؤها بضغطةٍ من الشريط. يُعرض فقط حين تكون جلسةٌ جارية. */
if (isset($_SESSION['imp_session']['imp_id'])
    && strtotime((string) ($_SESSION['imp_session']['valid_to'] ?? '')) >= time()) {
    $__imp = $_SESSION['imp_session'];
    echo '<div class="ems-imp-strip" dir="rtl" role="status">'
       . '<span class="ems-imp-strip-text">تعمل الآن موضعَ <b>'
       . htmlspecialchars((string) $__imp['target_name'], ENT_QUOTES, 'UTF-8')
       . '</b> — بسببِ: ' . htmlspecialchars((string) $__imp['reason'], ENT_QUOTES, 'UTF-8')
       . ' — تنتهي في ' . htmlspecialchars((string) $__imp['valid_to'], ENT_QUOTES, 'UTF-8')
       . '</span>'
       . '<form method="post" action="' . htmlspecialchars(function_exists('ems_url') ? ems_url('Governance/impersonations.php') : 'Governance/impersonations.php', ENT_QUOTES, 'UTF-8') . '" class="ems-imp-strip-form">'
       . (function_exists('csrf_field') ? csrf_field() : '')
       . '<input type="hidden" name="imp_action" value="close">'
       . '<button type="submit" class="btn btn-sm btn-light">إنهاءُ الجلسة</button>'
       . '</form>'
       . '</div>';
}
?>
<?php
/* UI-DEF-06 → UI-13 (Error State): رسائلُ الحوكمة تُعرض داخل الشاشة لا في
   الرابط — الحارسُ يودعها الجلسةَ (ems_gov_flash_redirect) وهذا الحاملُ
   المركزيُّ يعرضها مرةً واحدةً ثم يمسحها: ما حدث + كيف يُصحَّح + رمزُ الخطأ.

   2026-08-09: كان يُرسم شريطًا لاصقًا بأنماطٍ داخليةٍ صفًّا مرنًا، فإذا ضاقت
   حاويتُه انكسر النصُّ كلمةً في كل سطرٍ وتقطّع الرمزُ نفسُه (GOV- / INFO- /
   200) — بلّغ به المالكُ بلقطةٍ حيّة. صار يمرُّ على نظام الرسائل الموحَّد:
   توستٌ ثابتُ الموضع (position:fixed) لا تحكمه حاويةٌ أصلًا، فزال سببُ العطب
   لا عرضُه، واتّحد شكلُه مع بقية رسائل النظام. */
if (!empty($_SESSION['ems_flash_gov']) && is_array($_SESSION['ems_flash_gov'])) {
    $emsFgPayload = array();
    foreach (array_slice($_SESSION['ems_flash_gov'], 0, 3) as $emsFg) {
        $fgText = trim((string) ($emsFg['text'] ?? ''));
        if ($fgText === '') { continue; }
        $fgCode = (string) ($emsFg['code'] ?? 'GOV-403');
        /* النوعُ من الرمز، إلا GOV-INFO-200 فهو حاملٌ عامٌّ تُرسَل به رسائلُ
           النجاح والمنعِ معًا (تم الحذف بنجاح · الحساب غير مرتبط بشركة) —
           فيُستنتج نوعُه من نصِّه وإلا خرج نجاحٌ بلون خبرٍ محايد. */
        if (strpos($fgCode, '-OK-') !== false)        { $fgType = 'success'; }
        elseif (strpos($fgCode, '-INFO-') !== false)  { $fgType = ''; }
        else                                          { $fgType = 'error'; }
        $emsFgPayload[] = array(
            'type' => $fgType,
            'text' => $fgText,
            'hint' => trim((string) ($emsFg['hint'] ?? '')),
            'code' => $fgCode,
        );
    }
    unset($_SESSION['ems_flash_gov']);
    if ($emsFgPayload) {
        echo '<script>(function(){var f=' . json_encode($emsFgPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
           . ';f.forEach(function(m){window.EmsAlert.show({type:m.type||undefined,text:m.text,hint:m.hint||undefined,code:m.code});});})();</script>';
        // بلا جافاسكربت: لافتةٌ سطريةٌ بالتصميم الموحَّد نفسِه — لا تُفقد الرسالة
        echo '<noscript>';
        foreach ($emsFgPayload as $m) {
            $cls = $m['type'] === 'success' ? 'alert-success' : ($m['type'] === 'error' ? 'alert-danger' : 'alert-info');
            echo '<div class="alert ' . $cls . '" role="status" style="margin:12px">'
               . htmlspecialchars($m['text'], ENT_QUOTES, 'UTF-8')
               . ($m['hint'] !== '' ? ' — ' . htmlspecialchars($m['hint'], ENT_QUOTES, 'UTF-8') : '')
               . ' <code>' . htmlspecialchars($m['code'], ENT_QUOTES, 'UTF-8') . '</code></div>';
        }
        echo '</noscript>';
    }
}
