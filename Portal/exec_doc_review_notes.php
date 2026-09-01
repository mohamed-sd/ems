<?php
/**
 * portal/exec_doc_review_notes.php — ملاحظات مراجعة الوثيقة قبل التوقيع (EX-CEO · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: ملاحظةُ مراجعةٍ واحدةٌ على وثيقةٍ قبلَ توقيعِها
 * المالك: مساحة الرئيس التنفيذي · مصدرُ الحقيقة: exec_doc_review_note
 * الأصل: ورقةُ «مساحة الرئيس التنفيذي» — السطح «ملاحظات مراجعة الوثيقة قبل التوقيع»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'portal/exec_doc_review_notes.php',
    'screen'     => 'exec_doc_review_notes',
    'table'      => 'exec_doc_review_note',
    'title'      => 'ملاحظات مراجعة الوثيقة قبل التوقيع',
    'icon'       => 'fa fa-triangle-exclamation',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · ملاحظات مراجعة الوثيقة قبل التوقيع',
    'intro'      => 'ملاحظاتُ المراجعينَ قبلَ التوقيعِ بدرجتِها وقيمتِها المعرَّضةِ وهل تحجب الاعتماد',
    'rule'       => 'الملاحظةُ الحاجبةُ تمنع التوقيعَ حتى تُقفَل بمستندِ معالجتِها — ولا توقيعَ فوقَ حاجب',
    'empty_hint' => 'لا ملاحظةَ مراجعةٍ مسجَّلةٌ بعد',
    'order'       => 'observed_date DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.exec_doc_review_notes.register',
            'label' => 'تسجيلُ ملاحظات مراجعة الوثيقة قبل التوقيع',
            'rule'  => 'الملاحظةُ الحاجبةُ تمنع التوقيعَ حتى تُقفَل بمستندِ معالجتِها — ولا توقيعَ فوقَ حاجب',
            'fields' => array(
                'document_ref' => 'Document_ID ◄',
                'entity_ref' => 'الكيان ◄',
                'observed_date' => 'تاريخ الرصد',
                'observer_party' => 'الجهة الراصدة ▼',
                'note_kind' => 'نوع الملاحظة ▼',
                'note_grade' => 'درجة الملاحظة ▼',
                'note_desc' => 'وصف الملاحظة',
                'potential_impact' => 'الأثر المحتمل',
                'value_at_risk' => 'القيمة المعرَّضة',
                'blocks_approval' => 'يحجب الاعتماد؟ ▼',
                'required_action' => 'الإجراء المطلوب',
                'action_deadline' => 'مهلة المعالجة',
                'action_document' => 'مستند المعالجة',
                'note_state' => 'حالة الملاحظة ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('document_ref', 'entity_ref', 'observed_date', 'observer_party', 'note_kind', 'note_grade', 'note_desc', 'potential_impact', 'value_at_risk', 'blocks_approval', 'required_action', 'action_deadline', 'action_document', 'note_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('exec_doc_review_note', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'EDN-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('exec_doc_review_note',
                            array('note_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في exec_doc_review_note');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
