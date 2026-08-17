<?php
/**
 * Audit/iaf_independence.php — إقرارات الاستقلال وتعارض المصالح
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: IAF-01 §4-1 · المتطلب: IAF-0009
 * الحكم: IAF-0009: ولا تكليفَ بمهمةٍ بلا إقرارٍ سارٍ قبلَه
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Audit/iaf_independence.php',
    'screen'     => 'iaf_independence',
    'table'      => 'iaf_independence',
    'title'      => 'إقرارات الاستقلال وتعارض المصالح',
    'icon'       => 'fa fa-user-check',
    'nature'     => 'document',
    'doc'        => 'IAF-01 §4-1 · IAF-0009',
    'intro'      => 'إقرارٌ سنويٌّ لكلِّ مراجعٍ وقبلَ كلِّ تكليف',
    'rule'       => 'IAF-0009: ولا تكليفَ بمهمةٍ بلا إقرارٍ سارٍ قبلَه',
    'empty_hint' => 'لا إقراراتِ استقلالٍ مسجَّلة',
    'order'       => 'declared_at DESC',

    'actions'    => array(
        'declare' => array(
            'code'  => 'iaf.independence.declare',
            'label' => 'إقرارُ استقلالٍ وتعارضِ مصالح',
            'rule'  => 'IAF-0009: لا تكليفَ بمهمةٍ بلا إقرارِ استقلالٍ سارٍ',
            'fields' => array('auditor_id' => 'رقمُ المراجع', 'scope_ref' => 'النطاق', 'valid_until' => 'سارٍ حتى'),
            'run' => function ($conn, $co, $uid, $in) {
                require_once __DIR__ . '/../app/Services/Audit/InternalAuditService.php';
                return \App\Services\Audit\InternalAuditService::declareIndependence($conn, array(
                    'company_id' => $co, 'auditor_id' => (int) ($in['auditor_id'] ?? 0), 'scope_ref' => (string) ($in['scope_ref'] ?? ''),
                    'valid_until' => (string) ($in['valid_until'] ?? ''), 'has_conflict' => !empty($in['has_conflict'])));
            }),
    ),

);
require __DIR__ . '/../includes/u13_screen_kit.php';
/* حزمةُ الحالاتِ الدنيا (بوابة ٩): تحميلٌ وفراغٌ وخطأٌ — مخفيةٌ افتراضًا
   ويُظهرها منطقُ الشاشةِ عند حالِها. الدالةُ من ux_components التي تُحمِّلها القشرة. */
if (function_exists('ems_states_bundle')) {
    echo ems_states_bundle('لا إقراراتِ استقلالٍ مسجَّلةً بعدُ',
                           'يقرُّ كلُّ مراجعٍ سنويًّا وقبلَ كلِّ تكليفٍ — ولا تكليفَ بمهمةٍ بلا إقرارٍ سارٍ');
}
