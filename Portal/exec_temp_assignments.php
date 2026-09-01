<?php
/**
 * portal/exec_temp_assignments.php — التكليفات والإنابات المؤقتة (EX-CEO · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: تكليفٌ أو إنابةٌ مؤقّتةٌ واحدةٌ لشخصٍ في مدّة
 * المالك: مساحة الرئيس التنفيذي · مصدرُ الحقيقة: exec_assignments
 * الأصل: ورقةُ «مساحة الرئيس التنفيذي» — السطح «التكليفات والإنابات المؤقتة»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'portal/exec_temp_assignments.php',
    'screen'     => 'exec_assignments',
    'table'      => 'exec_assignments',
    'title'      => 'التكليفات والإنابات المؤقتة',
    'icon'       => 'fa fa-user-clock',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · التكليفات والإنابات المؤقتة',
    'intro'      => 'التكليفاتُ والإناباتُ بصلاحياتِها وسقفِ اعتمادِها وما لا تشمله ومدّتِها',
    'rule'       => 'الإنابةُ بسقفٍ مكتوبٍ وما لا تشمله مكتوبٌ صراحةً — والفراغُ ليس تفويضًا',
    'empty_hint' => 'لا تكليفَ مؤقّتٌ مسجَّلٌ بعد',
    'order'       => 'effective_from DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.exec_assignments.register',
            'label' => 'تسجيلُ التكليفات والإنابات المؤقتة',
            'rule'  => 'الإنابةُ بسقفٍ مكتوبٍ وما لا تشمله مكتوبٌ صراحةً — والفراغُ ليس تفويضًا',
            'fields' => array(
                'entity_ref' => 'الكيان ◄',
                'assignment_kind' => 'نوعه ▼',
                'assignee_person_ref' => 'المكلَّف — مرجع الشخص',
                'position_scope' => 'المنصب/النطاق',
                'granted_authorities' => 'الصلاحيات الممنوحة',
                'approval_cap' => 'سقف الاعتماد الممنوح',
                'assignment_allowance' => 'بدل التكليف',
                'excluded_scope' => 'ما لا يشمله',
                'from_date' => 'من تاريخ',
                'to_date' => 'إلى تاريخ',
                'assignment_state' => 'حالة التكليف ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('entity_ref', 'assignment_kind', 'assignee_person_ref', 'position_scope', 'granted_authorities', 'approval_cap', 'assignment_allowance', 'excluded_scope', 'from_date', 'to_date', 'assignment_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('exec_assignments', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'ETA-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('exec_assignments',
                            array('assignment_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في exec_assignments');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
