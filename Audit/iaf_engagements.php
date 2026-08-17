<?php
/**
 * Audit/iaf_engagements.php — مهام المراجعة
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: IAF-01 §4-5 · المتطلب: IAF-0016
 * الحكم: IAF-0006: ولا يصبح المراجعُ جزءًا مما سيراجعه لاحقًا
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Audit/iaf_engagements.php',
    'screen'     => 'iaf_engagements',
    'table'      => 'iaf_engagements',
    'title'      => 'مهام المراجعة',
    'icon'       => 'fa fa-briefcase',
    'nature'     => 'document',
    'doc'        => 'IAF-01 §4-5 · IAF-0016',
    'intro'      => 'مراجعاتٌ ماليةٌ وتشغيليةٌ وتقنيةٌ والتزامية',
    'rule'       => 'IAF-0006: ولا يصبح المراجعُ جزءًا مما سيراجعه لاحقًا',
    'empty_hint' => 'لا مهامَّ مراجعةٍ مفتوحة',
    'order'       => 'id DESC',

    'actions'    => array(
        'open' => array(
            'code'  => 'iaf.engagement.open',
            'label' => 'فتحُ مهمةِ مراجعة',
            'rule'  => 'IAF-0044 + IAF-0009: لا مهمةَ بلا خطةٍ معتمدةٍ ولا بلا إقرارِ استقلالٍ سارٍ',
            'fields' => array('plan_id' => 'رقمُ الخطة', 'area_code' => 'رمزُ المجال', 'title' => 'عنوانُ المهمة', 'lead_auditor' => 'المراجعُ المكلَّف'),
            'run' => function ($conn, $co, $uid, $in) {
                require_once __DIR__ . '/../app/Services/Audit/InternalAuditService.php';
                return \App\Services\Audit\InternalAuditService::openEngagement($conn, array(
                    'company_id' => $co, 'plan_id' => (int) ($in['plan_id'] ?? 0), 'area_code' => (string) ($in['area_code'] ?? ''),
                    'title' => (string) ($in['title'] ?? ''), 'lead_auditor' => (int) ($in['lead_auditor'] ?? 0)));
            }),
    ),

);
require __DIR__ . '/../includes/u13_screen_kit.php';
/* حزمةُ الحالاتِ الدنيا (UXW-01 بوابة ٩): تحميلٌ وفراغٌ وخطأٌ — مخفيةٌ افتراضًا
   ويُظهرها منطقُ الشاشةِ عند حالِها. الدالةُ من ux_components التي تُحمِّلها القشرة. */
if (function_exists('ems_states_bundle')) {
    echo ems_states_bundle('لا مهامَّ مراجعةٍ مفتوحة',
                           'تُفتح المهمةُ من خطةٍ معتمدةٍ وبإقرارِ استقلالٍ سارٍ (IAF-0044 + IAF-0009)');
}
