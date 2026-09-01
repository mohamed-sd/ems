<?php
/**
 * fleet/external_auditor_notes.php — ملاحظات المراجع الخارجي (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: ملاحظةُ مراجعٍ خارجيٍّ واحدةٌ
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: flt_external_auditor_note
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «ملاحظات المراجع الخارجي»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/external_auditor_notes.php',
    'screen'     => 'flt_ext_auditor',
    'table'      => 'flt_external_auditor_note',
    'title'      => 'ملاحظات المراجع الخارجي',
    'icon'       => 'fa fa-user-shield',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · ملاحظات المراجع الخارجي',
    'intro'      => 'ملاحظاتُ المراجعِ الخارجيِّ على الأسطولِ بإجرائِها ومسؤولِها المقترحِ وتاريخِها المستهدَف',
    'rule'       => 'الملاحظةُ تبقى مفتوحةً حتى يُسجَّل إجراؤها — ولا تُطوى بلا أثر',
    'empty_hint' => 'لا ملاحظةَ مراجعٍ خارجيٍّ مسجَّلةٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.flt_ext_auditor.register',
            'label' => 'تسجيلُ ملاحظات المراجع الخارجي',
            'rule'  => 'الملاحظةُ تبقى مفتوحةً حتى يُسجَّل إجراؤها — ولا تُطوى بلا أثر',
            'fields' => array(
                'item_no' => 'م',
                'severity' => 'الخطورة',
                'category' => 'الفئة',
                'item_or_asset' => 'البند أو الأصل',
                'problem_desc' => 'وصف المشكلة',
                'required_action' => 'الإجراء المطلوب',
                'proposed_owner' => 'المسؤول المقترح',
                'target_date' => 'التاريخ المستهدف',
                'accounting_impact' => 'الأثر المحاسبي',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('item_no', 'severity', 'category', 'item_or_asset', 'problem_desc', 'required_action', 'proposed_owner', 'target_date', 'accounting_impact');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('flt_external_auditor_note', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في flt_external_auditor_note');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
