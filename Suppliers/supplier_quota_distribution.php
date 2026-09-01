<?php
/**
 * suppliers/supplier_quota_distribution.php — الخانات المكافئة وتوزيع الحصص (DEP-02 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: سطرُ توزيعِ حصّةٍ لمورِّدٍ في دورةِ التزامٍ سنوية
 * المالك: إدارة الموردين · مصدرُ الحقيقة: sup_slot_allocation_quota
 * الأصل: ورقةُ «إدارة الموردين» — السطح «الخانات المكافئة وتوزيع الحصص»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'suppliers/supplier_quota_distribution.php',
    'screen'     => 'sup_quota_dist',
    'table'      => 'sup_slot_allocation_quota',
    'title'      => 'الخانات المكافئة وتوزيع الحصص',
    'icon'       => 'fa fa-scale-unbalanced',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · الخانات المكافئة وتوزيع الحصص',
    'intro'      => 'الوحداتُ التعاقديةُ المكافئةُ والممنوحةُ لكلِّ مورِّدٍ وفارقُ أكبرِ البواقي',
    'rule'       => 'الممنوحُ أعدادٌ صحيحةٌ والمكافئُ يُشتقّ — والفرقُ يُعرَض ولا يُسوّى بالكتابة',
    'empty_hint' => 'لا سطرَ توزيعٍ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sup_quota_dist.register',
            'label' => 'تسجيلُ الخانات المكافئة وتوزيع الحصص',
            'rule'  => 'الممنوحُ أعدادٌ صحيحةٌ والمكافئُ يُشتقّ — والفرقُ يُعرَض ولا يُسوّى بالكتابة',
            'fields' => array(
                'granted_units' => 'الوحدات التعاقدية الممنوحة (أعدادًا صحيحة)',
                'supplier_class' => 'تصنيف المورد ▼',
                'shared_slot_member' => 'عضو خانة مشتركة؟ ▼',
                'valid_from' => 'ساري من تاريخ',
                'dist_decision_ref' => 'مرجع قرار التوزيع',
                'dist_state' => 'حالة التوزيع ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('granted_units', 'supplier_class', 'shared_slot_member', 'valid_from', 'dist_decision_ref', 'dist_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('sup_slot_allocation_quota', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SQD-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('sup_slot_allocation_quota',
                            array('dist_row_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في sup_slot_allocation_quota');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
