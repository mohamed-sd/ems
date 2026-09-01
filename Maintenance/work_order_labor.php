<?php
/**
 * maintenance/work_order_labor.php — عمالة أمر العمل (DEP-14 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: سطرُ عمالةِ فنيٍّ واحدٍ على أمرِ عملٍ في يوم
 * المالك: إدارة الصيانة · مصدرُ الحقيقة: mnt_order_labor
 * الأصل: ورقةُ «إدارة الصيانة» — السطح «عمالة أمر العمل»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'maintenance/work_order_labor.php',
    'screen'     => 'mnt_order_labor',
    'table'      => 'mnt_order_labor',
    'title'      => 'عمالة أمر العمل',
    'icon'       => 'fa fa-user-gear',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · عمالة أمر العمل',
    'intro'      => 'ساعاتُ الفنيين على أمرِ العملِ بأدوارِهم وملاحظاتِهم الفنية',
    'rule'       => 'العمالةُ تفصَّل هنا ويُلخَّص مجموعُها في الأمرِ — ولا يُدخَل المجموعُ يدويًّا',
    'empty_hint' => 'لا سطرَ عمالةٍ مسجَّلٌ بعد',
    'order'       => 'id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.mnt_order_labor.register',
            'label' => 'تسجيلُ عمالة أمر العمل',
            'rule'  => 'العمالةُ تفصَّل هنا ويُلخَّص مجموعُها في الأمرِ — ولا يُدخَل المجموعُ يدويًّا',
            'fields' => array(
                'order_id' => 'رقم الأمر ◄',
                'employee_id' => 'كود الفني ◄',
                'role' => 'الدور ▼',
                'labor_date' => 'التاريخ',
                'hours' => 'ساعات العمل',
                'tech_note' => 'ملاحظة فنية',
                'row_state' => 'حالة السطر ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('order_id', 'employee_id', 'role', 'labor_date', 'hours', 'tech_note', 'row_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('mnt_order_labor', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'MOL-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('mnt_order_labor',
                            array('row_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في mnt_order_labor');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
