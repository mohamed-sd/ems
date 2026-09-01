<?php
/**
 * suppliers/supplier_readiness_reserve.php — الجاهزية والإحلال والاحتياط (DEP-02 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: حدثُ جاهزيةٍ أو إحلالٍ واحدٌ على وحدةٍ تعاقدية
 * المالك: إدارة الموردين · مصدرُ الحقيقة: sup_replacement_reserve
 * الأصل: ورقةُ «إدارة الموردين» — السطح «الجاهزية والإحلال والاحتياط»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'suppliers/supplier_readiness_reserve.php',
    'screen'     => 'sup_readiness',
    'table'      => 'sup_replacement_reserve',
    'title'      => 'الجاهزية والإحلال والاحتياط',
    'icon'       => 'fa fa-shuffle',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · الجاهزية والإحلال والاحتياط',
    'intro'      => 'أحداثُ الجاهزيةِ والإحلالِ والاحتياطِ بقيمةِ ما قبلَها وما بعدَها ومستندِها',
    'rule'       => 'الحدثُ يُقيَّد بقيمتَيه قبلَ وبعد — فالتاريخُ يُقرأ ولا يُستنتَج (§18)',
    'empty_hint' => 'لا حدثَ جاهزيةٍ مسجَّلٌ بعد',
    'order'       => 'date DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sup_readiness.register',
            'label' => 'تسجيلُ الجاهزية والإحلال والاحتياط',
            'rule'  => 'الحدثُ يُقيَّد بقيمتَيه قبلَ وبعد — فالتاريخُ يُقرأ ولا يُستنتَج (§18)',
            'fields' => array(
                'slot_code' => 'كود الوحدة التعاقدية · Slot_ID',
                'business_model' => 'نموذج العمل',
                'no_month' => 'رقم الشهر',
                'event_date' => 'التاريخ',
                'event_name' => 'الحدث',
                'unit_type' => 'نوع الوحدة التعاقدية',
                'no_supplier' => 'رقم المورد',
                'name_supplier' => 'اسم المورد (بحث)',
                'equipment_code' => 'كود المعدة',
                'counterparty' => 'الطرف المقابل',
                'from_reserve' => 'من إسناد بدور احتياطي؟',
                'value_before' => 'القيمة قبل',
                'value_after' => 'القيمة بعد',
                'doc_ref' => 'مستند/مرجع (كما ورد)',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('slot_code', 'business_model', 'no_month', 'event_date', 'event_name', 'unit_type', 'no_supplier', 'name_supplier', 'equipment_code', 'counterparty', 'from_reserve', 'value_before', 'value_after', 'doc_ref');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('sup_replacement_reserve', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SRR-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('sup_replacement_reserve',
                            array('event_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في sup_replacement_reserve');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
