<?php
/**
 * contracts/contract_amendments_renewal.php — الملحقات والتجديد والإغلاق (DEP-01 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: ملحقٌ أو تجديدٌ أو إغلاقٌ واحدٌ على عقد
 * المالك: إدارة المبيعات التعاقدية والعقود · مصدرُ الحقيقة: contract_amendments
 * الأصل: ورقةُ «إدارة المبيعات التعاقدية والعقود» — السطح «الملحقات والتجديد والإغلاق»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'contracts/contract_amendments_renewal.php',
    'screen'     => 'sal_amendments',
    'table'      => 'contract_amendments',
    'title'      => 'الملحقات والتجديد والإغلاق',
    'icon'       => 'fa fa-file-pen',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · الملحقات والتجديد والإغلاق',
    'intro'      => 'وثائقُ الملحقاتِ والتجديدِ والإغلاقِ بسريانِها التعاقديِّ والتنفيذيِّ وأثرِها على دوراتِ الالتزام',
    'rule'       => 'الملحقُ بعدَ الاعتمادِ لا يُدهَس — يُقيَّد وثيقةً جديدةً بأثرِها (الدستور §Amendment)',
    'empty_hint' => 'لا ملحقَ مسجَّلٌ بعد',
    'where'       => 'is_deleted = 0',
    'order'       => 'amend_date DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sal_amendments.register',
            'label' => 'تسجيلُ الملحقات والتجديد والإغلاق',
            'rule'  => 'الملحقُ بعدَ الاعتمادِ لا يُدهَس — يُقيَّد وثيقةً جديدةً بأثرِها (الدستور §Amendment)',
            'fields' => array(
                'contract_id' => 'كود العقد',
                'client_no' => 'رقم العميل',
                'client_name' => 'اسم العميل (بحث)',
                'doc_no' => 'رقم الوثيقة',
                'renewal_cycle' => 'التجديد (دورة الالتزام)',
                'container_key' => 'مفتاح دورة الالتزام',
                'doc_text_adaptation' => 'الوثيقة / التكييف النصي',
                'event_adaptation' => 'تكييف الحدث',
                'signed_on' => 'توقيعها',
                'contractual_from' => 'السريان التعاقدي من',
                'contractual_to' => 'إلى',
                'doc_target' => 'مستهدف الوثيقة',
                'uom_ref' => 'الوحدة',
                'cycles_effect' => 'الأثر على دورات الالتزام',
                'evidence_level' => 'الحجية',
                'doc_state' => 'حالة الوثيقة',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('contract_id', 'client_no', 'client_name', 'doc_no', 'renewal_cycle', 'container_key', 'doc_text_adaptation', 'event_adaptation', 'signed_on', 'contractual_from', 'contractual_to', 'doc_target', 'uom_ref', 'cycles_effect', 'evidence_level', 'doc_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('contract_amendments', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في contract_amendments');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
