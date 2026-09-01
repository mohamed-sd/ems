<?php
/**
 * workforce/wf_qualification_matrix.php — مصفوفة التأهيل والجاهزية (DEP-13 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: قاعدةُ تأهيلٍ واحدةٌ لنوعٍ أو فئة
 * المالك: إدارة القوى التشغيلية · مصدرُ الحقيقة: wf_qualification_matrix
 * الأصل: ورقةُ «إدارة القوى التشغيلية» — السطح «مصفوفة التأهيل والجاهزية»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'workforce/wf_qualification_matrix.php',
    'screen'     => 'wf_qual_matrix',
    'table'      => 'wf_qualification_matrix',
    'title'      => 'مصفوفة التأهيل والجاهزية',
    'icon'       => 'fa fa-table-cells',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · مصفوفة التأهيل والجاهزية',
    'intro'      => 'المصفوفةُ المرجعيةُ: أدنى تأهيلٍ ورخصةٍ وفحصٍ طبيٍّ لكلِّ نوعٍ أو فئة',
    'rule'       => 'المصفوفةُ تُحكم التخصيصَ ولا تُتجاوز إلا باستثناءِ إشرافٍ مسجَّل',
    'empty_hint' => 'لا قاعدةَ مسجَّلةٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.wf_qual_matrix.register',
            'label' => 'تسجيلُ مصفوفة التأهيل والجاهزية',
            'rule'  => 'المصفوفةُ تُحكم التخصيصَ ولا تُتجاوز إلا باستثناءِ إشرافٍ مسجَّل',
            'fields' => array(
                'type_or_category' => 'النوع/الفئة',
                'min_proficiency' => 'مستوى التأهيل الأدنى ▼',
                'required_license' => 'فئة الرخصة المطلوبة ▼',
                'medical_required' => 'فحص طبي مطلوب؟ ▼',
                'recert_period' => 'دورية إعادة الاعتماد',
                'supervised_exception' => 'استثناء بإشراف؟ ▼',
                'rule_state' => 'حالة القاعدة ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('type_or_category', 'min_proficiency', 'required_license', 'medical_required', 'recert_period', 'supervised_exception', 'rule_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('wf_qualification_matrix', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'WFM-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('wf_qualification_matrix',
                            array('rule_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في wf_qualification_matrix');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
