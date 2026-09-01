<?php
/**
 * maintenance/diagnosis_requests.php — طلب الفحص والتشخيص (DEP-14 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: فحصٌ وتشخيصٌ واحدٌ يسبق أمرَ عمل
 * المالك: إدارة الصيانة · مصدرُ الحقيقة: mnt_diagnosis_request
 * الأصل: ورقةُ «إدارة الصيانة» — السطح «طلب الفحص والتشخيص»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'maintenance/diagnosis_requests.php',
    'screen'     => 'mnt_diagnosis',
    'table'      => 'mnt_diagnosis_request',
    'title'      => 'طلب الفحص والتشخيص',
    'icon'       => 'fa fa-stethoscope',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · طلب الفحص والتشخيص',
    'intro'      => 'الفحصُ والتشخيصُ بعقدةِ الشجرةِ النهائيةِ والقطعِ المتوقَّعةِ وزمنِ الإصلاحِ التقديري',
    'rule'       => 'عقدةُ الشجرةِ النهائيةُ من الشجرةِ المرجعيةِ وحدَها — ولا أمرَ عملٍ بلا تشخيصٍ مقيَّد',
    'empty_hint' => 'لا طلبَ فحصٍ مسجَّلٌ بعد',
    'order'       => 'inspect_date DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.mnt_diagnosis.register',
            'label' => 'تسجيلُ طلب الفحص والتشخيص',
            'rule'  => 'عقدةُ الشجرةِ النهائيةُ من الشجرةِ المرجعيةِ وحدَها — ولا أمرَ عملٍ بلا تشخيصٍ مقيَّد',
            'fields' => array(
                'intake_ref' => 'معرّف الاستقبال ◄',
                'equipment_ref' => 'كود المعدة ◄',
                'inspector_ref' => 'الفاحص ◄',
                'inspect_date' => 'تاريخ الفحص',
                'meter_at_inspect' => 'قراءة العدّاد عند الفحص',
                'final_tree_node' => 'عقدة الشجرة النهائية ▼',
                'diagnosis_desc' => 'وصف التشخيص',
                'expected_parts' => 'القطع المتوقعة',
                'estimated_repair_time' => 'الزمن التقديري للإصلاح',
                'repair_place' => 'مكان الإصلاح ▼',
                'needs_warranty_supplier' => 'يتطلب مورد الضمان؟ ▼',
                'inspect_result' => 'نتيجة الفحص ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('intake_ref', 'equipment_ref', 'inspector_ref', 'inspect_date', 'meter_at_inspect', 'final_tree_node', 'diagnosis_desc', 'expected_parts', 'estimated_repair_time', 'repair_place', 'needs_warranty_supplier', 'inspect_result');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('mnt_diagnosis_request', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'MDR-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('mnt_diagnosis_request',
                            array('diagnosis_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في mnt_diagnosis_request');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
