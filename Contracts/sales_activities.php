<?php
/**
 * contracts/sales_activities.php — الأنشطة والمتابعات (DEP-01 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: نشاطٌ أو متابعةٌ واحدةٌ على عميلٍ أو فرصةٍ أو عقد
 * المالك: إدارة المبيعات التعاقدية والعقود · مصدرُ الحقيقة: activities
 * الأصل: ورقةُ «إدارة المبيعات التعاقدية والعقود» — السطح «الأنشطة والمتابعات»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'contracts/sales_activities.php',
    'screen'     => 'sal_activities',
    'table'      => 'activities',
    'title'      => 'الأنشطة والمتابعات',
    'icon'       => 'fa fa-list-check',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · الأنشطة والمتابعات',
    'intro'      => 'أنشطةُ البيعِ ومتابعاتُها بنتيجتِها ومسؤولِها وإجرائِها التالي وتاريخِه',
    'rule'       => 'كلُّ نشاطٍ بإجراءٍ تالٍ وتاريخِه — والمتابعةُ بلا إجراءٍ تالٍ لا تُقفل خطًّا',
    'empty_hint' => 'لا نشاطَ مسجَّلٌ بعد',
    'where'       => 'is_deleted = 0',
    'order'       => 'activity_date DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sal_activities.register',
            'label' => 'تسجيلُ الأنشطة والمتابعات',
            'rule'  => 'كلُّ نشاطٍ بإجراءٍ تالٍ وتاريخِه — والمتابعةُ بلا إجراءٍ تالٍ لا تُقفل خطًّا',
            'fields' => array(
                'activity_date' => 'التاريخ',
                'client_no' => 'رقم العميل',
                'client_name' => 'اسم العميل (بحث)',
                'project_no' => 'رقم المشروع',
                'opportunity_no' => 'رقم الفرصة',
                'contract_ref' => 'مرجع العقد',
                'activity_type' => 'نوع النشاط',
                'subject' => 'الموضوع/الملخص',
                'outcome' => 'النتيجة',
                'next_action' => 'الإجراء التالي',
                'next_action_date' => 'تاريخه',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('activity_date', 'client_no', 'client_name', 'project_no', 'opportunity_no', 'contract_ref', 'activity_type', 'subject', 'outcome', 'next_action', 'next_action_date');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('activities', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في activities');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
