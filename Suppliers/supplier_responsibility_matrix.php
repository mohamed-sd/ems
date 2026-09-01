<?php
/**
 * suppliers/supplier_responsibility_matrix.php — مصفوفة المسؤوليات والتكاليف (DEP-02 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: مصفوفةُ مسؤولياتٍ واحدةٌ لعقدِ مورِّد
 * المالك: إدارة الموردين · مصدرُ الحقيقة: sup_responsibility
 * الأصل: ورقةُ «إدارة الموردين» — السطح «مصفوفة المسؤوليات والتكاليف»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'suppliers/supplier_responsibility_matrix.php',
    'screen'     => 'sup_responsibility',
    'table'      => 'sup_responsibility',
    'title'      => 'مصفوفة المسؤوليات والتكاليف',
    'icon'       => 'fa fa-table-list',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · مصفوفة المسؤوليات والتكاليف',
    'intro'      => 'من يتحمّل ماذا في عقدِ المورِّد: الوقودُ والزيوتُ والصيانةُ والمشغّلُ والترحيلُ والتأمين',
    'rule'       => 'كلُّ بندٍ يُحسم بمرجعٍ من نصِّ العقدِ أو ملحقِه — والفراغُ ليس حكمًا',
    'empty_hint' => 'لا مصفوفةَ معبَّأةٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sup_responsibility.register',
            'label' => 'تسجيلُ مصفوفة المسؤوليات والتكاليف',
            'rule'  => 'كلُّ بندٍ يُحسم بمرجعٍ من نصِّ العقدِ أو ملحقِه — والفراغُ ليس حكمًا',
            'fields' => array(
                'no_supplier' => 'رقم المورد',
                'name_supplier' => 'اسم المورد (بحث)',
                'business_model' => 'نموذج العمل',
                'doc_adaptation' => 'تكييف الوثيقة',
                'evidence_level' => 'مستوى الحجية',
                'fuel' => 'الوقود ▼',
                'oils' => 'الزيوت ▼',
                'maintenance' => 'الصيانة ▼',
                'spare_parts' => 'قطع الغيار ▼',
                'operator' => 'المشغل (السائق) ▼',
                'housing_food' => 'السكن والإعاشة ▼',
                'mobilization' => 'ترحيل الذهاب (Mobilization) ▼',
                'demobilization' => 'ترحيل العودة (Demobilization) ▼',
                'damage' => 'الضرر ▼',
                'waiting' => 'الانتظار ▼',
                'stoppage' => 'التوقف ▼',
                'breakdown' => 'العطل ▼',
                'insurance' => 'التأمين ▼',
                'violations' => 'المخالفات ▼',
                'ref_contract_annex' => 'المرجع (نص العقد/الملحق)',
                'filled_by' => 'المُعبِّئ',
                'fill_date' => 'تاريخ التعبئة',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('no_supplier', 'name_supplier', 'business_model', 'doc_adaptation', 'evidence_level', 'fuel', 'oils', 'maintenance', 'spare_parts', 'operator', 'housing_food', 'mobilization', 'demobilization', 'damage', 'waiting', 'stoppage', 'breakdown', 'insurance', 'violations', 'ref_contract_annex', 'filled_by', 'fill_date');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('sup_responsibility', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SRM-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('sup_responsibility',
                            array('matrix_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في sup_responsibility');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
