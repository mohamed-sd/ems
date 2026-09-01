<?php
/**
 * operations/site_supply_requests.php — طلبات الموقع للصرف والاستلام (DEP-12 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: طلبُ صرفٍ واحدٌ من موقعٍ إلى مخزن
 * المالك: إدارة الموقع · مصدرُ الحقيقة: site_supply_request
 * الأصل: ورقةُ «إدارة الموقع» — السطح «طلبات الموقع للصرف والاستلام»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/site_supply_requests.php',
    'screen'     => 'site_supply_request',
    'table'      => 'site_supply_request',
    'title'      => 'طلبات الموقع للصرف والاستلام',
    'icon'       => 'fa fa-truck-ramp-box',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · طلبات الموقع للصرف والاستلام',
    'intro'      => 'طلباتُ الموقعِ من المخازنِ بمبرِّرها ومستلِمِ عهدتِها ومطابقةِ استلامِها',
    'rule'       => 'سندُ الصرفِ يُقرأ من المخازنِ ولا يُنشأ هنا — والمطابقةُ حكمٌ مكتوب',
    'empty_hint' => 'لا طلبَ صرفٍ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.site_supply_request.register',
            'label' => 'تسجيلُ طلبات الموقع للصرف والاستلام',
            'rule'  => 'سندُ الصرفِ يُقرأ من المخازنِ ولا يُنشأ هنا — والمطابقةُ حكمٌ مكتوب',
            'fields' => array(
                'site_ref' => 'الموقع ◄',
                'request_date' => 'تاريخ الطلب',
                'request_type' => 'نوع الطلب ▼',
                'items_text' => 'الأصناف المطلوبة',
                'quantities' => 'الكميات',
                'justification' => 'مبرر الطلب',
                'actual_receipt_date' => 'تاريخ الاستلام الفعلي',
                'receipt_match' => 'مطابقة الاستلام ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('site_ref', 'request_date', 'request_type', 'items_text', 'quantities', 'justification', 'actual_receipt_date', 'receipt_match');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('site_supply_request', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SSQ-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('site_supply_request',
                            array('request_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في site_supply_request');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
