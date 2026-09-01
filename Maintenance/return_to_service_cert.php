<?php
/**
 * maintenance/return_to_service_cert.php — الإقفال وشهادة إعادة الخدمة (DEP-14 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: شهادةُ إعادةِ خدمةٍ واحدةٌ لأمرِ عمل
 * المالك: إدارة الصيانة · مصدرُ الحقيقة: mnt_return_cert
 * الأصل: ورقةُ «إدارة الصيانة» — السطح «الإقفال وشهادة إعادة الخدمة»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'maintenance/return_to_service_cert.php',
    'screen'     => 'mnt_return_cert',
    'table'      => 'mnt_return_cert',
    'title'      => 'الإقفال وشهادة إعادة الخدمة',
    'icon'       => 'fa fa-certificate',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · الإقفال وشهادة إعادة الخدمة',
    'intro'      => 'إقفالُ أمرِ العملِ بشهادةِ إعادةِ الخدمةِ واختبارِها ونتيجتِها وقيودِ التشغيلِ بعدها',
    'rule'       => 'الحالةُ الفنيةُ الجديدةُ لا تُعلَن إلا باختبارٍ منفَّذٍ ونتيجتِه — والقيودُ تُكتب لا تُفترَض',
    'empty_hint' => 'لا شهادةَ إعادةِ خدمةٍ مسجَّلةٌ بعد',
    'order'       => 'id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.mnt_return_cert.register',
            'label' => 'تسجيلُ الإقفال وشهادة إعادة الخدمة',
            'rule'  => 'الحالةُ الفنيةُ الجديدةُ لا تُعلَن إلا باختبارٍ منفَّذٍ ونتيجتِه — والقيودُ تُكتب لا تُفترَض',
            'fields' => array(
                'order_id' => 'رقم الأمر ◄',
                'equipment_id' => 'كود المعدة ◄',
                'tech_complete_date' => 'تاريخ الإنجاز الفني',
                'test_performed' => 'الاختبار المنفَّذ ▼',
                'test_result' => 'نتيجة الاختبار ▼',
                'meter_at_close' => 'قراءة العدّاد عند الإقفال',
                'new_readiness_state' => 'الحالة الفنية الجديدة ▼',
                'operating_limits' => 'قيود التشغيل',
                'cert_validity' => 'صلاحية الشهادة',
                'signed_by' => 'توقيع مدير الصيانة',
                'state' => 'حالة الشهادة ▼',
                'reviewed_by' => 'المراجع',
                'approved_at' => 'تاريخ الاعتماد',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('order_id', 'equipment_id', 'tech_complete_date', 'test_performed', 'test_result', 'meter_at_close', 'new_readiness_state', 'operating_limits', 'cert_validity', 'signed_by', 'state', 'reviewed_by', 'approved_at');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('mnt_return_cert', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في mnt_return_cert');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
