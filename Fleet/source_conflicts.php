<?php
/**
 * fleet/source_conflicts.php — التضاربات بين المصادر (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: تضاربٌ واحدٌ بين مصدرَين على عينٍ واحدة
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: flt_source_conflict
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «التضاربات بين المصادر»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/source_conflicts.php',
    'screen'     => 'flt_src_conflicts',
    'table'      => 'flt_source_conflict',
    'title'      => 'التضاربات بين المصادر',
    'icon'       => 'fa fa-code-compare',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · التضاربات بين المصادر',
    'intro'      => 'موضعُ التضاربِ ومصدراه وقيمتاهما والفرقُ والترجيحُ والتصحيحُ المطلوب',
    'rule'       => 'الترجيحُ يُكتب بسندِه — ولا يُحسَم تضاربٌ اجتهادًا بلا مصدرٍ حاكم (§2)',
    'empty_hint' => 'لا تضاربَ مسجَّلٌ بين المصادرِ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.flt_src_conflicts.register',
            'label' => 'تسجيلُ التضاربات بين المصادر',
            'rule'  => 'الترجيحُ يُكتب بسندِه — ولا يُحسَم تضاربٌ اجتهادًا بلا مصدرٍ حاكم (§2)',
            'fields' => array(
                'asset_code' => 'كود العين',
                'conflict_point' => 'موضع التضارب',
                'source_one' => 'المصدر الأول',
                'value_one' => 'قيمته',
                'source_two' => 'المصدر الثاني',
                'value_two' => 'قيمته',
                'difference' => 'الفرق',
                'preference' => 'الترجيح',
                'required_fix' => 'التصحيح المطلوب',
                'severity' => 'الخطورة',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('asset_code', 'conflict_point', 'source_one', 'value_one', 'source_two', 'value_two', 'difference', 'preference', 'required_fix', 'severity');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('flt_source_conflict', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في flt_source_conflict');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
