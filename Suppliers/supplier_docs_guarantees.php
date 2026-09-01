<?php
/**
 * suppliers/supplier_docs_guarantees.php — سجل المستندات والضمانات (DEP-02 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: مستندٌ أو ضمانٌ واحدٌ لمورِّدٍ أو عقد
 * المالك: إدارة الموردين · مصدرُ الحقيقة: sup_doc_guarantee
 * الأصل: ورقةُ «إدارة الموردين» — السطح «سجل المستندات والضمانات»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'suppliers/supplier_docs_guarantees.php',
    'screen'     => 'sup_docs_guarantees',
    'table'      => 'sup_doc_guarantee',
    'title'      => 'سجل المستندات والضمانات',
    'icon'       => 'fa fa-shield-halved',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · سجل المستندات والضمانات',
    'intro'      => 'مستنداتُ الموردين وضماناتُهم بنطاقِها وحالتِها وتاريخِ انتهائِها',
    'rule'       => 'المنتهي يوقف الأهليّةَ بأثرِه — والنطاقُ يُصرَّح عقدًا أو مورِّدًا لا يُظَنّ',
    'empty_hint' => 'لا مستندَ مسجَّلٌ بعد',
    'order'       => 'date_expiry ASC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sup_docs_guarantees.register',
            'label' => 'تسجيلُ سجل المستندات والضمانات',
            'rule'  => 'المنتهي يوقف الأهليّةَ بأثرِه — والنطاقُ يُصرَّح عقدًا أو مورِّدًا لا يُظَنّ',
            'fields' => array(
                'type_doc' => 'نوع المستند',
                'ref' => 'المرجع',
                'no_supplier' => 'رقم المورد',
                'name_supplier' => 'اسم المورد (بحث)',
                'doc_scope' => 'النطاق (عقد/مورد)',
                'doc_state' => 'الحالة',
                'date_expiry' => 'تاريخ الانتهاء',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('type_doc', 'ref', 'no_supplier', 'name_supplier', 'doc_scope', 'doc_state', 'date_expiry');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('sup_doc_guarantee', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SDG-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('sup_doc_guarantee',
                            array('doc_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في sup_doc_guarantee');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
