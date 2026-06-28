<?php
/**
 * طبقة القوى التشغيلية (EQUIP-OPE-S04) — مكوّن «العرض» الموحّد لشاشات الطبقة.
 *
 * يوفّر أيقونة عينٍ في عمود الإجراءات + نافذة تفاصيل عبر EmsDetailsModal (المكوّن
 * الموحّد في assets/js/ems-details-modal.js) — لا تُبنى نوافذ يدوية (المعيارية).
 *
 * الاستخدام في الشاشة:
 *   require_once __DIR__.'/../app/Services/Workforce/ViewModal.php';
 *   $WF_VIEW = [];                       // قبل حلقة الجدول
 *   $WF_VIEW[$id] = ems_wf_view_payload('العنوان','fas fa-...', [ ems_wf_field(...), ... ]);
 *   echo ems_wf_view_button($id);        // داخل عمود الإجراءات
 *   ems_wf_view_modal($WF_VIEW);         // مرّةً واحدةً أسفل الصفحة
 *
 * نمط دوالٍ خفيفٌ بلا autoloader، يُضمَّن بـ require_once مباشرةً (كبقية الطبقة).
 */

if (!function_exists('ems_wf_view_button')) {
    /** زرّ العرض (عين) لصفٍّ — يربط بخريطة WF_VIEW عبر data-vid. */
    function ems_wf_view_button($vid)
    {
        $vid = (int) $vid;
        return '<a href="javascript:void(0)" class="viewBtn action-btn view" data-vid="' . $vid
             . '" title="عرض التفاصيل"><i class="fas fa-eye"></i></a>';
    }
}

if (!function_exists('ems_wf_field')) {
    /**
     * يبني حقلاً لنافذة التفاصيل.
     * @param array $extra مفاتيح إضافية اختيارية: type('status') · tone('active'|'inactive') · size('sm'..'full')
     */
    function ems_wf_field($label, $value, $icon = 'fas fa-circle-info', $extra = [])
    {
        return array_merge([
            'label' => (string) $label,
            'value' => ($value === null) ? '' : (string) $value,
            'icon'  => $icon,
        ], is_array($extra) ? $extra : []);
    }
}

if (!function_exists('ems_wf_view_payload')) {
    /** يغلّف عنوان/أيقونة/حقول السجل في الشكل الذي يتوقّعه EmsDetailsModal.open. */
    function ems_wf_view_payload($title, $icon, array $fields, array $sections = [])
    {
        $p = ['title' => (string) $title, 'icon' => $icon, 'fields' => array_values($fields)];
        if (!empty($sections)) {
            $p['sections'] = array_values($sections);
        }
        return $p;
    }
}

if (!function_exists('ems_wf_view_modal')) {
    /**
     * يطبع خريطة WF_VIEW (id → payload) + المعالِج الموحّد (مرّةً واحدةً).
     * المعالِج بالـ vanilla JS (بلا اعتماد jQuery) ويعمل بالتفويض، فيصمد مع DataTables.
     */
    function ems_wf_view_modal($map)
    {
        if (!is_array($map)) {
            $map = [];
        }
        echo '<script>window.WF_VIEW=' . json_encode($map, JSON_UNESCAPED_UNICODE) . ';';
        echo <<<'JS'
(function(){
  if (window.__wfViewBound) return; window.__wfViewBound = true;
  document.addEventListener('click', function(e){
    var btn = e.target.closest ? e.target.closest('.viewBtn') : null;
    if (!btn) return;
    e.preventDefault();
    var payload = (window.WF_VIEW || {})[btn.getAttribute('data-vid')];
    if (payload && window.EmsDetailsModal) { EmsDetailsModal.open(payload); }
  });
})();
JS;
        echo '</script>';
    }
}
