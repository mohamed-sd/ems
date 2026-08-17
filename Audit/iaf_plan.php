<?php
/**
 * Audit/iaf_plan.php — خطة المراجعة السنوية
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: IAF-01 §4-3 · المتطلب: IAF-0015
 * الحكم: IAF-0044: لا مهمةَ بلا خطةٍ ولا خطةَ بلا كونٍ رقابيٍّ ولا كونَ بلا ميثاق
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Audit/iaf_plan.php',
    'screen'     => 'iaf_plan',
    'table'      => 'iaf_plan',
    'title'      => 'خطة المراجعة السنوية',
    'icon'       => 'fa fa-calendar-check',
    'nature'     => 'document',
    'doc'        => 'IAF-01 §4-3 · IAF-0015',
    'intro'      => 'خطةٌ مبنيةٌ على المخاطرِ معتمدةٌ من الجهةِ المشرفة',
    'rule'       => 'IAF-0044: لا مهمةَ بلا خطةٍ ولا خطةَ بلا كونٍ رقابيٍّ ولا كونَ بلا ميثاق',
    'empty_hint' => 'لا خطةَ معتمدةٌ بعدُ',
    'order'       => 'plan_year DESC',

    'actions'    => array(
        'approve' => array(
            'code'  => 'iaf.plan.approve',
            'label' => 'اعتمادُ الخطةِ السنوية',
            'rule'  => 'IAF-0044: لا خطةَ بلا كونٍ رقابيٍّ مبنيّ',
            'fields' => array('plan_year' => 'سنةُ الخطة', 'title' => 'عنوانُ الخطة', 'basis' => 'الأساس'),
            'run' => function ($conn, $co, $uid, $in) {
                require_once __DIR__ . '/../app/Services/Audit/InternalAuditService.php';
                return \App\Services\Audit\InternalAuditService::approvePlan($conn, array(
                    'company_id' => $co, 'plan_year' => (int) ($in['plan_year'] ?? 0), 'title' => (string) ($in['title'] ?? ''),
                    'basis' => (string) ($in['basis'] ?? ''), 'actor' => $uid));
            }),
    ),

);
require __DIR__ . '/../includes/u13_screen_kit.php';
/* حزمةُ الحالاتِ الدنيا (بوابة ٩): تحميلٌ وفراغٌ وخطأٌ — مخفيةٌ افتراضًا
   ويُظهرها منطقُ الشاشةِ عند حالِها. الدالةُ من ux_components التي تُحمِّلها القشرة. */
if (function_exists('ems_states_bundle')) {
    echo ems_states_bundle('لا خطةَ سنويةً معتمدةً بعدُ',
                           'تُبنى الخطةُ على الكونِ الرقابيِّ ودرجاتِ خطرِه ثم تعتمدها الجهةُ المشرفة — ولا مهمةَ بلا خطة');
}
