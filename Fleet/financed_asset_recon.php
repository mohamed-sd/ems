<?php
/**
 * fleet/financed_asset_recon.php — مصالحة الأعيان الممولة (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: عينٌ ممولةٌ واحدةٌ مقابلَ كودِها بالأسطول
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: flt_financed_asset_recon
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «مصالحة الأعيان الممولة»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/financed_asset_recon.php',
    'screen'     => 'flt_financed_recon',
    'table'      => 'flt_financed_asset_recon',
    'title'      => 'مصالحة الأعيان الممولة',
    'icon'       => 'fa fa-money-check-dollar',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · مصالحة الأعيان الممولة',
    'intro'      => 'العينُ الممولةُ وكودُها المطابقُ بالأسطولِ ونوعُ تمويلِها وقرارُ تكويدِها',
    'rule'       => 'كودُ التمويلِ وكودُ الأسطولِ يلتقيان بقرارِ تكويدٍ مكتوبٍ لا بتخمين',
    'empty_hint' => 'لا عينَ ممولةٌ مصالَحةٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.flt_financed_recon.register',
            'label' => 'تسجيلُ مصالحة الأعيان الممولة',
            'rule'  => 'كودُ التمويلِ وكودُ الأسطولِ يلتقيان بقرارِ تكويدٍ مكتوبٍ لا بتخمين',
            'fields' => array(
                'finance_asset_code' => 'كود العين بالتمويل',
                'fleet_matched_code' => 'الكود المطابق بالأسطول',
                'classification' => 'التصنيف',
                'asset_type' => 'النوع',
                'finance_type' => 'نوع التمويل',
                'owner_financier' => 'المالك / الممول',
                'match_state' => 'حالة المطابقة',
                'approved_code' => 'الكود المعتمد',
                'coding_decision' => 'قرار التكويد',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('finance_asset_code', 'fleet_matched_code', 'classification', 'asset_type', 'finance_type', 'owner_financier', 'match_state', 'approved_code', 'coding_decision');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('flt_financed_asset_recon', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في flt_financed_asset_recon');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
