<?php
/**
 * workforce/wf_person_register.php — سجل الأفراد التشغيليين (DEP-13 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: فردٌ تشغيليٌّ واحدٌ — سطرٌ لكلِّ موظّفٍ في فئةٍ تشغيلية
 * المالك: إدارة القوى التشغيلية · مصدرُ الحقيقة: equipment_operators
 * الأصل: ورقةُ «إدارة القوى التشغيلية» — السطح «سجل الأفراد التشغيليين»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'workforce/wf_person_register.php',
    'screen'     => 'wf_person_register',
    'table'      => 'equipment_operators',
    'title'      => 'سجل الأفراد التشغيليين',
    'icon'       => 'fa fa-id-badge',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · سجل الأفراد التشغيليين',
    'intro'      => 'الأفرادُ التشغيليون بفئاتِهم وتبعيّتِهم ورخصِهم وأنواعِ المعداتِ المؤهَّلين عليها',
    'rule'       => 'الاسمُ ورقمُ الموظفِ يُقرآن من الموارد — ولا سجلَّ ثالثًا للإنسان (حكم W03)',
    'empty_hint' => 'لا فردَ تشغيليٌّ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.wf_person_register.register',
            'label' => 'تسجيلُ سجل الأفراد التشغيليين',
            'rule'  => 'الاسمُ ورقمُ الموظفِ يُقرآن من الموارد — ولا سجلَّ ثالثًا للإنسان (حكم W03)',
            'fields' => array(
                'wf_category' => 'الفئة التشغيلية ▼',
                'affiliation' => 'التبعية ▼',
                'distribution_track' => 'مسار التوزيع ▼',
                'employee_id' => 'رقم الموظف بالموارد ◄',
                'license_type' => 'فئة الرخصة ▼',
                'license_expiry_date' => 'انتهاء الرخصة',
                'status' => 'حالة الفرد ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('wf_category', 'affiliation', 'distribution_track', 'employee_id', 'license_type', 'license_expiry_date', 'status');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('equipment_operators', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'WFP-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('equipment_operators',
                            array('person_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في equipment_operators');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
