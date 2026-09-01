<?php
/**
 * fleet/owner_reconciliation.php — مصالحة الأسطول بالملاك (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: معدّةٌ واحدةٌ مقابلَ مالكِها ومورِّدِها
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: flt_owner_reconciliation
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «مصالحة الأسطول بالملاك»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/owner_reconciliation.php',
    'screen'     => 'flt_owner_recon',
    'table'      => 'flt_owner_reconciliation',
    'title'      => 'مصالحة الأسطول بالملاك',
    'icon'       => 'fa fa-handshake',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · مصالحة الأسطول بالملاك',
    'intro'      => 'كلُّ معدّةٍ بمورِّدِها ومالكِها الحقيقيِّ وساعاتِها وعقودِها ودوراتِ التزامِها',
    'rule'       => 'المالكُ الحقيقيُّ غيرُ المورِّدِ أحيانًا — والفرقُ يُكتب لا يُطوى',
    'empty_hint' => 'لا مصالحةَ ملّاكٍ مسجَّلةٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.flt_owner_recon.register',
            'label' => 'تسجيلُ مصالحة الأسطول بالملاك',
            'rule'  => 'المالكُ الحقيقيُّ غيرُ المورِّدِ أحيانًا — والفرقُ يُكتب لا يُطوى',
            'fields' => array(
                'equipment_code' => 'كود المعدة',
                'equipment_type' => 'نوع المعدة',
                'plate_no' => 'رقم اللوحة',
                'supplier_no' => 'رقم المورد',
                'supplier_name' => 'اسم المورد',
                'supplier_type' => 'نوع المورد',
                'supplier_nature' => 'طبيعة المورد',
                'real_owner_name' => 'اسم المالك الحقيقي',
                'owner_phone' => 'هاتف المالك',
                'old_code' => 'الكود القديم',
                'chassis_no' => 'رقم الشاسيه',
                'notes' => 'ملاحظات',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('equipment_code', 'equipment_type', 'plate_no', 'supplier_no', 'supplier_name', 'supplier_type', 'supplier_nature', 'real_owner_name', 'owner_phone', 'old_code', 'chassis_no', 'notes');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('flt_owner_reconciliation', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في flt_owner_reconciliation');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
