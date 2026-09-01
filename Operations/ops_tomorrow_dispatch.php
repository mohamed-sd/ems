<?php
/**
 * operations/ops_tomorrow_dispatch.php — احتياج الغد وتوزيع الموارد (DEP-11 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: سطرُ توزيعِ معدّةٍ ومشغّلٍ على جبهةٍ ليومٍ ووردية
 * المالك: إدارة التشغيل · مصدرُ الحقيقة: daily_plan_lines
 * الأصل: ورقةُ «إدارة التشغيل» — السطح «احتياج الغد وتوزيع الموارد»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/ops_tomorrow_dispatch.php',
    'screen'     => 'ops_tomorrow',
    'table'      => 'daily_plan_lines',
    'title'      => 'احتياج الغد وتوزيع الموارد',
    'icon'       => 'fa fa-calendar-plus',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · احتياج الغد وتوزيع الموارد',
    'intro'      => 'توزيعُ الغدِ: أيُّ معدّةٍ لأيِّ جبهةٍ بأيِّ مشغّلٍ وأيِّ وردية',
    'rule'       => 'حاجبُ الصيانةِ الحرجةِ وتأهيلُ المشغّلِ ورخصتُه تُقرأ من مصادرِها — ولا توزيعَ يتجاوزها',
    'empty_hint' => 'لا سطرَ توزيعٍ مسجَّلٌ بعد',
    'order'       => 'plan_day DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.ops_tomorrow.register',
            'label' => 'تسجيلُ احتياج الغد وتوزيع الموارد',
            'rule'  => 'حاجبُ الصيانةِ الحرجةِ وتأهيلُ المشغّلِ ورخصتُه تُقرأ من مصادرِها — ولا توزيعَ يتجاوزها',
            'fields' => array(
                'plan_day' => 'يوم التوزيع',
                'project_ref' => 'رقم المشروع ◄',
                'work_zone' => 'منطقة العمل/الجبهة',
                'equipment_id' => 'كود المعدة ◄',
                'shift_no' => 'الوردية ▼',
                'operator_employee_id' => 'كود المشغّل ◄',
                'zone_daily_target' => 'مستهدف المنطقة لليوم',
                'row_state' => 'حالة السطر ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('plan_day', 'project_ref', 'work_zone', 'equipment_id', 'shift_no', 'operator_employee_id', 'zone_daily_target', 'row_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('daily_plan_lines', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'OTD-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('daily_plan_lines',
                            array('row_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في daily_plan_lines');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
