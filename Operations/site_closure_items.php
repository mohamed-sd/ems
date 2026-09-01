<?php
/**
 * operations/site_closure_items.php — إغلاق الموقع وتسريحه (DEP-12 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: بندُ إغلاقٍ واحدٌ على موقعٍ واحد
 * المالك: إدارة الموقع · مصدرُ الحقيقة: site_closure_item
 * الأصل: ورقةُ «إدارة الموقع» — السطح «إغلاق الموقع وتسريحه — بحسب انطباق الشركة»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/site_closure_items.php',
    'screen'     => 'site_closure',
    'table'      => 'site_closure_item',
    'title'      => 'إغلاق الموقع وتسريحه',
    'icon'       => 'fa fa-flag-checkered',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · إغلاق الموقع وتسريحه — بحسب انطباق الشركة',
    'intro'      => 'بنودُ إغلاقِ الموقعِ وتسريحِه ونتائجُها ومحضرُ التسليمِ النهائي',
    'rule'       => 'لا إغلاقَ بمحضرٍ نهائيٍّ وبندٌ واحدٌ مفتوح',
    'empty_hint' => 'لا بندَ إغلاقٍ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.site_closure.register',
            'label' => 'تسجيلُ إغلاق الموقع وتسريحه',
            'rule'  => 'لا إغلاقَ بمحضرٍ نهائيٍّ وبندٌ واحدٌ مفتوح',
            'fields' => array(
                'site_ref' => 'كود الموقع ◄',
                'closure_item' => 'بند الإغلاق ▼',
                'owner_ref' => 'المسؤول ◄',
                'result' => 'النتيجة ▼',
                'item_state' => 'حالة البند ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('site_ref', 'closure_item', 'owner_ref', 'result', 'item_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('site_closure_item', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SCL-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('site_closure_item',
                            array('item_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في site_closure_item');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
