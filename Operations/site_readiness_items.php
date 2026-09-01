<?php
/**
 * operations/site_readiness_items.php — تجهيز الموقع والجاهزية (DEP-12 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: بندُ تجهيزٍ واحدٌ على موقعٍ واحد
 * المالك: إدارة الموقع · مصدرُ الحقيقة: site_readiness_item
 * الأصل: ورقةُ «إدارة الموقع» — السطح «تجهيز الموقع والجاهزية — بحسب انطباق الشركة»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/site_readiness_items.php',
    'screen'     => 'site_readiness',
    'table'      => 'site_readiness_item',
    'title'      => 'تجهيز الموقع والجاهزية',
    'icon'       => 'fa fa-helmet-safety',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · تجهيز الموقع والجاهزية — بحسب انطباق الشركة',
    'intro'      => 'بنودُ تجهيزِ الموقعِ ومسؤولوها ونتائجُها وإعلانُ الجاهزية',
    'rule'       => 'انطباقُ السطحِ حكمُ تشغيلٍ لا شرطُ بناء (§16) — ولا جاهزيةَ تُعلَن ببندٍ مفتوح',
    'empty_hint' => 'لا بندَ تجهيزٍ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.site_readiness.register',
            'label' => 'تسجيلُ تجهيز الموقع والجاهزية',
            'rule'  => 'انطباقُ السطحِ حكمُ تشغيلٍ لا شرطُ بناء (§16) — ولا جاهزيةَ تُعلَن ببندٍ مفتوح',
            'fields' => array(
                'site_ref' => 'كود الموقع ◄',
                'readiness_item' => 'بند التجهيز ▼',
                'owner_ref' => 'المسؤول ◄',
                'result' => 'النتيجة ▼',
                'item_state' => 'حالة البند ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('site_ref', 'readiness_item', 'owner_ref', 'result', 'item_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('site_readiness_item', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SRD-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('site_readiness_item',
                            array('item_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في site_readiness_item');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
