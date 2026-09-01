<?php
/**
 * operations/site_request_items.php — بنود طلب الموقع (DEP-12 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: بندُ صنفٍ واحدٌ في طلبِ موقع
 * المالك: إدارة الموقع · مصدرُ الحقيقة: site_request_item
 * الأصل: ورقةُ «إدارة الموقع» — السطح «بنود طلب الموقع»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/site_request_items.php',
    'screen'     => 'site_request_items',
    'table'      => 'site_request_item',
    'title'      => 'بنود طلب الموقع',
    'icon'       => 'fa fa-list-ol',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · بنود طلب الموقع',
    'intro'      => 'بنودُ طلبِ الموقعِ بكمياتِها والمستلَمِ تراكميًّا والمتبقّي',
    'rule'       => 'المتبقّي يُشتقُّ من المطلوبِ والمستلَمِ ولا يُكتب — والبندُ ابنُ طلبِه',
    'empty_hint' => 'لا بندَ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.site_request_items.register',
            'label' => 'تسجيلُ بنود طلب الموقع',
            'rule'  => 'المتبقّي يُشتقُّ من المطلوبِ والمستلَمِ ولا يُكتب — والبندُ ابنُ طلبِه',
            'fields' => array(
                'request_ref' => 'رقم الطلب ◄',
                'item_ref' => 'كود الصنف ◄',
                'requested_qty' => 'الكمية المطلوبة',
                'item_state' => 'حالة البند ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('request_ref', 'item_ref', 'requested_qty', 'item_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('site_request_item', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SRI-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('site_request_item',
                            array('item_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في site_request_item');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
