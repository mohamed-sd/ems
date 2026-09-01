<?php
/**
 * operations/site_suspension.php — الإيقاف المؤقت للموقع (DEP-12 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: قرارُ إيقافٍ مؤقّتٍ واحدٌ لموقع
 * المالك: إدارة الموقع · مصدرُ الحقيقة: site_suspension
 * الأصل: ورقةُ «إدارة الموقع» — السطح «الإيقاف المؤقت للموقع — بحسب انطباق الشركة»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/site_suspension.php',
    'screen'     => 'site_suspension',
    'table'      => 'site_suspension',
    'title'      => 'الإيقاف المؤقت للموقع',
    'icon'       => 'fa fa-circle-stop',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · الإيقاف المؤقت للموقع — بحسب انطباق الشركة',
    'intro'      => 'إيقافُ الموقعِ مؤقّتًا بسببِه ومدّتِه وأثرِه على المواردِ والعقد',
    'rule'       => 'أثرُ العقدِ يُقرأ من التعاقدِ ولا يُقدَّر — والاستئنافُ بمحضرٍ لا بصمت',
    'empty_hint' => 'لا قرارَ إيقافٍ مؤقّتٍ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.site_suspension.register',
            'label' => 'تسجيلُ الإيقاف المؤقت للموقع',
            'rule'  => 'أثرُ العقدِ يُقرأ من التعاقدِ ولا يُقدَّر — والاستئنافُ بمحضرٍ لا بصمت',
            'fields' => array(
                'site_ref' => 'كود الموقع ◄',
                'suspension_reason' => 'سبب الإيقاف ▼',
                'from_date' => 'من تاريخ',
                'expected_duration' => 'المدة المتوقعة',
                'decision_state' => 'حالة القرار ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('site_ref', 'suspension_reason', 'from_date', 'expected_duration', 'decision_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('site_suspension', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SSP-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('site_suspension',
                            array('decision_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في site_suspension');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
