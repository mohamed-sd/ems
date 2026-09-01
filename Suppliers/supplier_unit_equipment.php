<?php
/**
 * suppliers/supplier_unit_equipment.php — توزيع الوحدات التعاقدية على المعدات (DEP-02 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: إسنادُ معدّةٍ واحدةٍ إلى وحدةٍ تعاقديةٍ في مدّة
 * المالك: إدارة الموردين · مصدرُ الحقيقة: sup_allocation_unit_equipment
 * الأصل: ورقةُ «إدارة الموردين» — السطح «توزيع الوحدات التعاقدية على المعدات»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'suppliers/supplier_unit_equipment.php',
    'screen'     => 'sup_unit_equip',
    'table'      => 'sup_allocation_unit_equipment',
    'title'      => 'توزيع الوحدات التعاقدية على المعدات',
    'icon'       => 'fa fa-truck-field',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · توزيع الوحدات التعاقدية على المعدات',
    'intro'      => 'أيُّ معدّةٍ تشغل أيَّ وحدةٍ تعاقديةٍ ومتى وبأيِّ دورٍ وأثرُ ذلك على المنفَّذ',
    'rule'       => 'المنفَّذُ وساعاتُ المورِّدِ تُشتقّان من التشغيلِ — ولا تُدخَلان هنا',
    'empty_hint' => 'لا إسنادَ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sup_unit_equip.register',
            'label' => 'تسجيلُ توزيع الوحدات التعاقدية على المعدات',
            'rule'  => 'المنفَّذُ وساعاتُ المورِّدِ تُشتقّان من التشغيلِ — ولا تُدخَلان هنا',
            'fields' => array(
                'slot_code' => 'كود الوحدة التعاقدية · Slot_ID',
                'code_contract_supplier' => 'كود عقد المورد',
                'no_supplier' => 'رقم المورد',
                'equipment_code' => 'كود المعدة · Equipment_ID',
                'line_type' => 'نوع الآلية/البند',
                'plate_as_written' => 'رقم اللوحة (كما ورد)',
                'unit_type' => 'نوع الوحدة التعاقدية',
                'from_date' => 'من',
                'to_date' => 'إلى',
                'assign_kind' => 'نوع الإسناد',
                'unit_role' => 'الدور (أساسية/احتياطية)',
                'change_reason' => 'سبب التغيير',
                'readiness_event_ref' => 'مرجع حدث الجاهزية (م12)',
                'agreed_monthly_hours' => 'ساعات الاتفاق الشهرية',
                'mine_site' => 'الموقع (المنجم)',
                'continuity' => 'الاستمرارية',
                'operating_status' => 'الوضع التشغيلي',
                'assign_state' => 'حالة الإسناد',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('slot_code', 'code_contract_supplier', 'no_supplier', 'equipment_code', 'line_type', 'plate_as_written', 'unit_type', 'from_date', 'to_date', 'assign_kind', 'unit_role', 'change_reason', 'readiness_event_ref', 'agreed_monthly_hours', 'mine_site', 'continuity', 'operating_status', 'assign_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('sup_allocation_unit_equipment', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SUE-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('sup_allocation_unit_equipment',
                            array('assign_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في sup_allocation_unit_equipment');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
