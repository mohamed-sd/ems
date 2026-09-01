<?php
/**
 * portal/exec_document_approvals.php — اعتمادات العقود والوثائق (EX-CEO · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: وثيقةٌ واحدةٌ معروضةٌ لتوقيعِ الرئيس
 * المالك: مساحة الرئيس التنفيذي · مصدرُ الحقيقة: exec_contract_signings
 * الأصل: ورقةُ «مساحة الرئيس التنفيذي» — السطح «اعتمادات العقود والوثائق»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'portal/exec_document_approvals.php',
    'screen'     => 'exec_doc_approvals',
    'table'      => 'exec_contract_signings',
    'title'      => 'اعتمادات العقود والوثائق',
    'icon'       => 'fa fa-file-signature',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · اعتمادات العقود والوثائق',
    'intro'      => 'الوثائقُ المعروضةُ للتوقيعِ بمراجعاتِها القانونيةِ والماليةِ والالتزاميةِ واستثناءاتِها',
    'rule'       => 'حالةُ التوقيعِ وحدَها تُكتب هنا — وكلُّ ما عداها مشتقٌّ من الوثيقةِ عند مالكِها (§6)',
    'empty_hint' => 'لا وثيقةٌ معروضةٌ للتوقيعِ بعد',
    'order'       => 'signing_date DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.exec_doc_approvals.register',
            'label' => 'تسجيلُ اعتمادات العقود والوثائق',
            'rule'  => 'حالةُ التوقيعِ وحدَها تُكتب هنا — وكلُّ ما عداها مشتقٌّ من الوثيقةِ عند مالكِها (§6)',
            'fields' => array(
                'ceo_signature_status' => 'CEO_Signature_Status ▼',
                'delegation_ref' => 'مرجع التفويض عند الإنابة ◄',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('ceo_signature_status', 'delegation_ref');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('exec_contract_signings', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'EDA-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('exec_contract_signings',
                            array('document_ref' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في exec_contract_signings');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
