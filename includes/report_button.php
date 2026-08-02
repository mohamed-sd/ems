<?php
/**
 * includes/report_button.php — زر «أبلغ عن مشكلة» السياقي (TKT-01 §2 · TKT-15)
 * ───────────────────────────────────────────────────────────────────────────
 * «لا تُسلَّم شاشة تشغيلية بلا زر إبلاغ يحمل سياقها — ولا يُدخل المستخدم
 * حرفًا مما يعرفه النظام».
 *
 * الاستعمال داخل أي شاشة:
 *     require_once __DIR__ . '/../includes/report_button.php';
 *     ems_report_button(array('screen' => 'timesheet', 'site_id' => $siteId,
 *         'equipment_id' => $eqId, 'contract_id' => $cid, 'shift_no' => $shift,
 *         'entity_type' => 'unit_entry', 'entity_id' => $rowId));
 */

if (!function_exists('ems_report_button')) {
    function ems_report_button(array $ctx, $label = 'أبلغ عن مشكلة')
    {
        $fields = '';
        foreach (array('screen', 'site_id', 'equipment_id', 'contract_id', 'project_id',
                       'shift_no', 'period_no', 'entity_type', 'entity_id') as $k) {
            if (isset($ctx[$k]) && $ctx[$k] !== '' && $ctx[$k] !== null) {
                $fields .= '<input type="hidden" name="ctx_' . $k . '" value="'
                    . htmlspecialchars((string) $ctx[$k]) . '">';
            }
        }
        // النموذج POST عادي إلى نقطة الفتح السياقي — السياق محمول لا مُدخَل
        echo '<form method="post" action="' . htmlspecialchars(ems_report_button_base()) . '/Tickets/ticket_contextual_open.php"'
            . ' style="display:inline" class="ems-report-btn-form">'
            . $fields
            . '<button type="submit" class="action-btn" title="' . htmlspecialchars($label) . '"'
            . ' style="color:#c0392b"><i class="fas fa-bullhorn"></i> ' . htmlspecialchars($label) . '</button>'
            . '</form>';
    }

    /** جذر التطبيق نسبيًّا من عمق الشاشة (شاشاتنا كلها على عمق مجلد واحد). */
    function ems_report_button_base()
    {
        return '..';
    }
}
