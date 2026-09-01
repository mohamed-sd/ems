<?php
/**
 * fleet/numbering_bridge.php — مصالحة جسر الترقيم (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: لوحةٌ واحدةٌ بأكوادِها القديمةِ والحالية
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: flt_numbering_bridge
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «مصالحة جسر الترقيم»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/numbering_bridge.php',
    'screen'     => 'flt_numbering_bridge',
    'table'      => 'flt_numbering_bridge',
    'title'      => 'مصالحة جسر الترقيم',
    'icon'       => 'fa fa-bridge',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · مصالحة جسر الترقيم',
    'intro'      => 'اللوحةُ الواحدةُ وأكوادُها القديمةُ والحاليةُ وقرارُ توحيدِها',
    'rule'       => 'لوحةٌ بأكثرَ من كودٍ حيٍّ حالةُ مطابقةٍ مفتوحةٌ حتى يُعتمَد كودٌ واحد',
    'empty_hint' => 'لا لوحةَ مسجَّلةٌ في جسرِ الترقيمِ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.flt_numbering_bridge.register',
            'label' => 'تسجيلُ مصالحة جسر الترقيم',
            'rule'  => 'لوحةٌ بأكثرَ من كودٍ حيٍّ حالةُ مطابقةٍ مفتوحةٌ حتى يُعتمَد كودٌ واحد',
            'fields' => array(
                'plate_no' => 'رقم اللوحة',
                'old_codes' => 'الأكواد القديمة',
                'old_codes_count' => 'عددها',
                'current_code' => 'الكود الحالي المشغَّل',
                'equipment_type' => 'نوع المعدة',
                'owner_supplier' => 'المورد المالك',
                'hours_done' => 'الساعات المنجزة',
                'match_state' => 'حالة المطابقة',
                'approved_code' => 'الكود المعتمد',
                'unification_decision' => 'قرار التوحيد',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('plate_no', 'old_codes', 'old_codes_count', 'current_code', 'equipment_type', 'owner_supplier', 'hours_done', 'match_state', 'approved_code', 'unification_decision');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('flt_numbering_bridge', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في flt_numbering_bridge');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
