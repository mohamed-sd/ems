<?php
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
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
    <script src="/ems/includes/js/ems-autosave.js" defer></script>
    <title><?php echo $page_title ; ?></title>

    <!-- Font awsome icon link مكتبة الايقونات -->
    <link rel="stylesheet" href="<?php echo $iconsCss; ?>">

    <!-- Call bootstrap 5 -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="/ems/assets/vendor/datatables/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="/ems/assets/css/local-fonts.css">
    <link rel="stylesheet" href="/ems/assets/css/design-tokens.css">
    <!-- Unified page styles: Dashboard + Chat -->
    <link rel="stylesheet" href="/ems/assets/css/ems.main.all.style.css<?php echo ems_css_ver('ems.main.all.style.css'); ?>">
    <!-- Unified Table Styles — loaded LAST so ems-tables.css is the single authoritative source for all table design -->
    <link rel="stylesheet" href="/ems/assets/css/ems-tables.css<?php echo ems_css_ver('ems-tables.css'); ?>">
    <!-- Unified Form Styles — loaded LAST so ems-forms.css is the single authoritative source for ALL form design -->
    <link rel="stylesheet" href="/ems/assets/css/ems-forms.css<?php echo ems_css_ver('ems-forms.css'); ?>">
    <!-- شريط الرحلة الموحّد (الدستور §5) — مكوّنٌ واحدٌ للطلب والبلاغ والوحدة -->
    <link rel="stylesheet" href="/ems/assets/css/ems-journey.css<?php echo ems_css_ver('ems-journey.css'); ?>">
    <!-- قشرة التطبيق الموحدة (UXR-01 · AS-01..08) — تُحمَّل بعد الهوية فتحكم القياسات -->
    <link rel="stylesheet" href="/ems/assets/css/ems-shell.css<?php echo ems_css_ver('ems-shell.css'); ?>">
    <script src="../assets/js/performance-boost.js" defer></script>
    <script src="/ems/assets/js/ui-unification.js<?php $__uijs=__DIR__.'/assets/js/ui-unification.js'; echo is_file($__uijs)?('?v='.filemtime($__uijs)):''; ?>" defer></script>
    <!-- نواة مكونات الواجهة (UXR-01 UI-01..20): الحالات والبطاقات وحارس صفر الأعمدة -->
    <script src="/ems/assets/js/ems-components.js<?php $__cmpjs=__DIR__.'/assets/js/ems-components.js'; echo is_file($__cmpjs)?('?v='.filemtime($__cmpjs)):''; ?>" defer></script>
    <!-- Unified column-groups show/hide (activated per-page via EmsColumnGroups.init) -->
    <script src="/ems/assets/js/column-groups.js" defer></script>
    <!-- Unified Details/View Modal System (نظام نافذة العرض الموحّد) -->
    <script src="/ems/assets/js/ems-details-modal.js" defer></script>
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
<?php
/* UI-DEF-06 → UI-13 (Error State): رسائلُ الحوكمة تُعرض داخل الشاشة لا في
   الرابط — الحارسُ يودعها الجلسةَ (ems_gov_flash_redirect) وهذا الحاملُ
   المركزيُّ يعرضها مرةً واحدةً ثم يمسحها: ما حدث + كيف يُصحَّح + رمزُ الخطأ. */
if (!empty($_SESSION['ems_flash_gov']) && is_array($_SESSION['ems_flash_gov'])) {
    echo '<div id="emsGovFlash" style="position:sticky;top:0;z-index:2000">';
    foreach (array_slice($_SESSION['ems_flash_gov'], 0, 3) as $emsFg) {
        $fgText = htmlspecialchars((string) ($emsFg['text'] ?? ''), ENT_QUOTES, 'UTF-8');
        $fgHint = htmlspecialchars((string) ($emsFg['hint'] ?? ''), ENT_QUOTES, 'UTF-8');
        $fgCode = htmlspecialchars((string) ($emsFg['code'] ?? 'GOV-403'), ENT_QUOTES, 'UTF-8');
        /* اللونُ يتبع الرمزَ لا النصَّ: نجاحٌ أخضرُ وحوكمةٌ حمراءُ وخبرٌ رماديٌّ —
           فالمستخدمُ يقرأ الحكمَ قبل أن يقرأ الحرف (UI-13). */
        $fgKind = (strpos($fgCode, '-OK-') !== false) ? 'ok'
            : ((strpos($fgCode, '-INFO-') !== false) ? 'info' : 'bad');
        $fgSkin = array(
            'ok'   => array('#14532d', '#166534', '#f0fdf4', 'fa-circle-check'),
            'info' => array('#1e3a5f', '#1d4ed8', '#eff6ff', 'fa-circle-info'),
            'bad'  => array('#7f1d1d', '#991b1b', '#fef2f2', 'fa-shield-halved'),
        );
        list($fgBg, $fgBd, $fgFg, $fgIcon) = $fgSkin[$fgKind];
        echo '<div dir="rtl" role="status" style="display:flex;align-items:center;gap:10px;background:' . $fgBg
           . ';color:' . $fgFg . ';padding:10px 16px;font-size:14px;border-bottom:1px solid ' . $fgBd . '">'
           . '<i class="fas ' . $fgIcon . '" aria-hidden="true"></i>'
           . '<span style="flex:1"><strong>' . $fgText . '</strong>'
           . ($fgHint !== '' ? ' — ' . $fgHint : '') . '</span>'
           . '<code style="background:rgba(255,255,255,.12);border-radius:4px;padding:2px 8px;font-size:12px">' . $fgCode . '</code>'
           . '<button type="button" onclick="this.closest(\'#emsGovFlash\').remove()" '
           . 'style="background:none;border:0;color:inherit;font-size:16px;cursor:pointer" aria-label="إغلاق">&times;</button>'
           . '</div>';
    }
    echo '</div>';
    unset($_SESSION['ems_flash_gov']);
}
