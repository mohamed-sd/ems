<?php
/**
 * operations/ops_resource_move_orders.php — طلب وقرار حركة الموارد (DEP-11 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: أمرُ حركةِ موردٍ واحدٌ من موقعٍ إلى موقع
 * المالك: إدارة التشغيل · مصدرُ الحقيقة: ops_resource_move_order
 * الأصل: ورقةُ «إدارة التشغيل» — السطح «طلب وقرار حركة الموارد»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/ops_resource_move_orders.php',
    'screen'     => 'ops_move_order',
    'table'      => 'ops_resource_move_order',
    'title'      => 'طلب وقرار حركة الموارد',
    'icon'       => 'fa fa-truck-arrow-right',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · طلب وقرار حركة الموارد',
    'intro'      => 'طلبُ نقلِ معدّةٍ أو مشغّلٍ بسببِه وأثرِه على خطّةِ الشهرِ وأمرِ ترحيلِه المتفرِّع',
    'rule'       => 'ما يحتاج ترحيلًا يتفرّع منه أمرُ ترحيلٍ بمرجعِه — ولا حركةَ صامتة',
    'empty_hint' => 'لا أمرَ حركةٍ مسجَّلٌ بعد',
    'order'       => 'order_date DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.ops_move_order.register',
            'label' => 'تسجيلُ طلب وقرار حركة الموارد',
            'rule'  => 'ما يحتاج ترحيلًا يتفرّع منه أمرُ ترحيلٍ بمرجعِه — ولا حركةَ صامتة',
            'fields' => array(
                'order_date' => 'تاريخ الأمر',
                'resource_kind' => 'نوع المورد المنقول ▼',
                'resource_ref' => 'كود المورد المنقول ◄',
                'move_reason' => 'سبب الحركة ▼',
                'requested_exec_date' => 'تاريخ التنفيذ المطلوب',
                'month_plan_effect' => 'أثر الحركة على خطة الشهر',
                'needs_transfer_order' => 'يتطلب أمر ترحيل؟ ▼',
                'order_state' => 'حالة الأمر ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('order_date', 'resource_kind', 'resource_ref', 'move_reason', 'requested_exec_date', 'month_plan_effect', 'needs_transfer_order', 'order_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('ops_resource_move_order', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'ORM-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('ops_resource_move_order',
                            array('order_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في ops_resource_move_order');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
