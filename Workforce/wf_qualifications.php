<?php
/**
 * workforce/wf_qualifications.php — التأهيل والشهادات (DEP-13 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: تأهيلٌ واحدٌ لفردٍ واحد
 * المالك: إدارة القوى التشغيلية · مصدرُ الحقيقة: worker_qualification
 * الأصل: ورقةُ «إدارة القوى التشغيلية» — السطح «التأهيل والشهادات»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'workforce/wf_qualifications.php',
    'screen'     => 'wf_qualifications',
    'table'      => 'worker_qualification',
    'title'      => 'التأهيل والشهادات',
    'icon'       => 'fa fa-certificate',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · التأهيل والشهادات',
    'intro'      => 'شهاداتُ التأهيلِ بجهاتِها ومستوياتِها وتواريخِ انتهائِها ونتيجةِ اختبارِها العملي',
    'rule'       => 'حالةُ التأهيلِ تُشتقُّ من تاريخِ الانتهاءِ ولا تُكتب — والمنتهي لا يؤهِّل',
    'empty_hint' => 'لا تأهيلَ مسجَّلٌ بعد',
    'order'       => 'expiry_date DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.wf_qualifications.register',
            'label' => 'تسجيلُ التأهيل والشهادات',
            'rule'  => 'حالةُ التأهيلِ تُشتقُّ من تاريخِ الانتهاءِ ولا تُكتب — والمنتهي لا يؤهِّل',
            'fields' => array(
                'employee_id' => 'كود الفرد ◄',
                'equipment_type' => 'نوع المعدة/المهارة',
                'proficiency_level' => 'مستوى التأهيل ▼',
                'issuer' => 'جهة التأهيل ▼',
                'certificate_no' => 'رقم الشهادة',
                'expiry_date' => 'تاريخ الانتهاء',
                'practical_test_result' => 'نتيجة الاختبار العملي',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('employee_id', 'equipment_type', 'proficiency_level', 'issuer', 'certificate_no', 'expiry_date', 'practical_test_result');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('worker_qualification', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'WFQ-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('worker_qualification',
                            array('qual_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في worker_qualification');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
