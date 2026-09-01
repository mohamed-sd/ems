<?php
/**
 * fleet/open_points.php — النقاط غير المحسومة (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: نقطةٌ واحدةٌ غيرُ محسومةٍ بمصدرِها وصفِّها
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: flt_open_point
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «النقاط غير المحسومة»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/open_points.php',
    'screen'     => 'flt_open_points',
    'table'      => 'flt_open_point',
    'title'      => 'النقاط غير المحسومة',
    'icon'       => 'fa fa-circle-question',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · النقاط غير المحسومة',
    'intro'      => 'كلُّ نقطةٍ لم تحسمْها المصادرُ بموضعِها في الشيتِ والصفِّ والحقلِ المتأثر',
    'rule'       => 'النقطةُ لا تُغلَق إلا بقرارٍ معتمَدٍ ومرجعِ مستندٍ وتاريخِ حسمٍ (§8)',
    'empty_hint' => 'لا نقطةَ غيرَ محسومةٍ مسجَّلةٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.flt_open_points.register',
            'label' => 'تسجيلُ النقاط غير المحسومة',
            'rule'  => 'النقطةُ لا تُغلَق إلا بقرارٍ معتمَدٍ ومرجعِ مستندٍ وتاريخِ حسمٍ (§8)',
            'fields' => array(
                'source' => 'المصدر ▼',
                'sheet_name' => 'الشيت',
                'row_no' => 'الصف',
                'asset_code' => 'كود العين أو الأصل',
                'operation_code' => 'كود العملية',
                'affected_field' => 'الحقل المتأثر',
                'current_value' => 'القيمة الحالية',
                'problem_type' => 'نوع المشكلة ▼',
                'note_text' => 'نص الملاحظة',
                'severity' => 'الخطورة ▼',
                'responsible_party' => 'الجهة المسؤولة',
                'required_action' => 'الإجراء المطلوب',
                'state' => 'الحالة ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('source', 'sheet_name', 'row_no', 'asset_code', 'operation_code', 'affected_field', 'current_value', 'problem_type', 'note_text', 'severity', 'responsible_party', 'required_action', 'state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('flt_open_point', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'PNT-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('flt_open_point',
                            array('point_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في flt_open_point');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
