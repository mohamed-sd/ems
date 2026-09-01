<?php
/**
 * operations/ops_seasonal_factors.php — الخطة الموسمية ومعاملاتها (DEP-11 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: معاملُ معايرةٍ موسميٌّ واحدٌ في مدّة
 * المالك: إدارة التشغيل · مصدرُ الحقيقة: ops_seasonal_factor
 * الأصل: ورقةُ «إدارة التشغيل» — السطح «الخطة الموسمية ومعاملاتها»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/ops_seasonal_factors.php',
    'screen'     => 'ops_seasonal',
    'table'      => 'ops_seasonal_factor',
    'title'      => 'الخطة الموسمية ومعاملاتها',
    'icon'       => 'fa fa-cloud-sun-rain',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · الخطة الموسمية ومعاملاتها',
    'intro'      => 'معاملاتُ معايرةِ الخطّةِ بالمواسمِ ونطاقِ تطبيقِها وأساسِ احتسابِها',
    'rule'       => 'المعامِلُ يُعاير الخطّةَ ولا يُعاير المنفَّذ — وأساسُ احتسابِه مكتوبٌ لا مفترَض',
    'empty_hint' => 'لا معاملَ موسميٌّ مسجَّلٌ بعد',
    'order'       => 'from_date DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.ops_seasonal.register',
            'label' => 'تسجيلُ الخطة الموسمية ومعاملاتها',
            'rule'  => 'المعامِلُ يُعاير الخطّةَ ولا يُعاير المنفَّذ — وأساسُ احتسابِه مكتوبٌ لا مفترَض',
            'fields' => array(
                'season' => 'الموسم',
                'from_date' => 'من تاريخ',
                'to_date' => 'إلى تاريخ',
                'business_model' => 'نموذج العمل ▼',
                'calibration_factor' => 'معامل المعايرة',
                'calc_basis' => 'أساس الاحتساب',
                'apply_scope' => 'نطاق التطبيق ▼',
                'factor_state' => 'حالة المعامل ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('season', 'from_date', 'to_date', 'business_model', 'calibration_factor', 'calc_basis', 'apply_scope', 'factor_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('ops_seasonal_factor', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'OSF-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('ops_seasonal_factor',
                            array('factor_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في ops_seasonal_factor');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
