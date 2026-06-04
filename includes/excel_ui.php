<?php
/**
 * excel_ui.php — مساعد واجهة إطار Excel الموحّد.
 *
 * يتيح لأي شاشة إضافة أزرار (نموذج/تصدير/استيراد) ونافذة معالج الاستيراد
 * بأقل قدر من الكود:
 *
 *   require_once __DIR__ . '/../includes/excel_ui.php';
 *   // داخل بناء $header_actions:
 *   foreach (ems_excel_header_actions('clients', 'العملاء', $can_add) as $a) { $header_actions[] = $a; }
 *   // قرب نهاية الصفحة (مرّة واحدة):
 *   ems_excel_render('clients');   // يطبع النافذة + الأصول
 *
 * @package EMS
 */

if (!function_exists('ems_excel_endpoint_url')) {
    /** رابط المتحكّم الأمامي (جذر المشروع). */
    function ems_excel_endpoint_url()
    {
        return function_exists('ems_url') ? ems_url('excel.php') : '/excel.php';
    }
}

if (!function_exists('ems_excel_header_actions')) {
    /**
     * عناصر جاهزة للإضافة إلى $header_actions: نموذج + تصدير + استيراد.
     *
     * @param string $entity مفتاح الكيان في ExcelRegistry.
     * @param string $title  العنوان المعروض (مثل: «العملاء»).
     * @param bool   $canAdd هل يملك المستخدم صلاحية الإضافة (لإظهار زر الاستيراد)؟
     * @param array  $opts   خيارات: ['exportOnly'=>true] لإظهار التصدير فقط (تقارير/سجلات)،
     *                       أو ['template'=>false] لإخفاء زر النموذج.
     * @return array[]
     */
    function ems_excel_header_actions($entity, $title, $canAdd = true, $opts = [])
    {
        $base = ems_excel_endpoint_url();
        $e = rawurlencode($entity);
        $items = [];

        $exportOnly = !empty($opts['exportOnly']);
        $showTemplate = $exportOnly ? false : (array_key_exists('template', $opts) ? (bool) $opts['template'] : true);

        if ($showTemplate) {
            $items[] = [
                'href'  => $base . '?entity=' . $e . '&action=template',
                'class' => 'btn',
                'title' => 'تحميل نموذج Excel فارغ للاستيراد',
                'icon'  => 'fas fa-file-excel',
                'label' => 'تحميل النموذج',
            ];
        }

        $items[] = [
            'href'  => $base . '?entity=' . $e . '&action=export',
            'class' => 'btn',
            'title' => 'تصدير البيانات إلى ملف Excel منسّق',
            'icon'  => 'fas fa-file-export',
            'label' => 'تصدير Excel',
        ];

        if (!$exportOnly && $canAdd) {
            $items[] = [
                'tag'   => 'button',
                'class' => '',
                'title' => 'استيراد بيانات من ملف Excel أو CSV',
                'icon'  => 'fas fa-file-import',
                'label' => 'استيراد Excel',
                'attrs' => 'type="button" data-ems-excel-import="' . htmlspecialchars($entity, ENT_QUOTES, 'UTF-8')
                    . '" data-ems-excel-title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"',
            ];
        }

        return $items;
    }
}

if (!function_exists('ems_excel_render')) {
    /**
     * يطبع الأصول (CSS/JS) ونافذة المعالج المشتركة. آمن للاستدعاء أكثر من مرّة
     * (يُطبع مرّة واحدة فقط لكل طلب).
     */
    function ems_excel_render()
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $csrf = function_exists('generate_csrf_token') ? generate_csrf_token() : '';
        $cssUrl = function_exists('ems_url') ? ems_url('assets/css/ems-excel.css') : '/assets/css/ems-excel.css';
        $jsUrl  = function_exists('ems_url') ? ems_url('assets/js/ems-excel.js') : '/assets/js/ems-excel.js';
        $endpoint = ems_excel_endpoint_url();
        $h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
        ?>
<link rel="stylesheet" href="<?php echo $h($cssUrl); ?>">
<div class="ems-xl-overlay" id="emsExcelModal" role="dialog" aria-modal="true">
  <div class="ems-xl-modal">
    <div class="ems-xl-head">
      <h5><i class="fas fa-file-import"></i> <span>استيراد</span></h5>
      <button type="button" class="ems-xl-close" aria-label="إغلاق">&times;</button>
    </div>
    <ul class="ems-xl-steps">
      <li class="active"><span class="num">1</span> رفع الملف</li>
      <li><span class="num">2</span> المعاينة والتحقق</li>
      <li><span class="num">3</span> الاستيراد</li>
    </ul>
    <div class="ems-xl-body">
      <div class="ems-xl-msg" id="emsXlMsg"></div>

      <!-- خطوة 1 -->
      <div class="ems-xl-pane is-active" data-step="1">
        <div class="ems-xl-drop" id="emsXlDrop">
          <i class="fas fa-cloud-arrow-up"></i>
          <p>اسحب الملف هنا أو اضغط للاختيار</p>
          <small>الصيغ المدعومة: xlsx، xls، csv — بحد أقصى 5 ميجابايت</small>
          <input type="file" id="emsXlFile" accept=".xlsx,.xls,.csv">
        </div>
        <div class="ems-xl-filename" id="emsXlFilename"></div>
        <div class="ems-xl-tip">
          <i class="fas fa-lightbulb"></i>
          لم تجهّز ملفك بعد؟ حمّل
          <a href="#" data-tpl-link target="_blank">النموذج الجاهز</a>
          ثم املأه وارفعه. سيتم عرض معاينة كاملة قبل الحفظ.
        </div>
      </div>

      <!-- خطوة 2 -->
      <div class="ems-xl-pane" data-step="2">
        <div id="emsXlPreviewArea"></div>
      </div>

      <!-- خطوة 3 -->
      <div class="ems-xl-pane" data-step="3">
        <div id="emsXlResultArea"></div>
      </div>
    </div>
    <div class="ems-xl-foot" id="emsXlFoot">
      <button type="button" class="ems-xl-btn primary" id="emsXlPreviewBtn" disabled><i class="fas fa-search"></i> معاينة وتحقّق</button>
      <button type="button" class="ems-xl-btn ghost" data-ems-xl-close>إلغاء</button>
    </div>
  </div>
</div>
<script>
  window.EMS_EXCEL_ENDPOINT = <?php echo json_encode($endpoint); ?>;
  window.EMS_EXCEL_CSRF = <?php echo json_encode($csrf); ?>;
</script>
<script src="<?php echo $h($jsUrl); ?>" defer></script>
        <?php
    }
}
