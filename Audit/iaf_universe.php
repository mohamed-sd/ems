<?php
/**
 * Audit/iaf_universe.php — الكون الرقابي
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: IAF-01 §4-3 · المتطلب: IAF-0013
 * الحكم: IAF-0014: التقييمُ السنويُّ للمخاطرِ أساسُ الخطةِ لا الاجتهاد
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Audit/iaf_universe.php',
    'screen'     => 'iaf_universe',
    'table'      => 'iaf_universe',
    'title'      => 'الكون الرقابي',
    'icon'       => 'fa fa-globe',
    'nature'     => 'register',
    'doc'        => 'IAF-01 §4-3 · IAF-0013',
    'intro'      => 'نطاقاتُ المراجعةِ كلُّها بدرجةِ خطرِ كلٍّ وتاريخِ آخرِ مراجعة',
    'rule'       => 'IAF-0014: التقييمُ السنويُّ للمخاطرِ أساسُ الخطةِ لا الاجتهاد',
    'empty_hint' => 'لم يُبنَ الكونُ الرقابيُّ بعدُ — ولا خطةَ بلا كون',
    'order'       => 'risk_score DESC',

    'actions'    => array(
        'build' => array(
            'code'  => 'iaf.universe.build',
            'label' => 'إدراجُ مجالٍ في الكونِ الرقابي',
            'rule'  => 'IAF-0044: لا كونَ بلا ميثاقٍ معتمد',
            'fields' => array('area_code' => 'رمزُ المجال', 'area_name' => 'اسمُ المجال', 'owner_dept' => 'الإدارةُ المالكة', 'risk_score' => 'درجةُ الخطر'),
            'run' => function ($conn, $co, $uid, $in) {
                require_once __DIR__ . '/../app/Services/Audit/InternalAuditService.php';
                return \App\Services\Audit\InternalAuditService::buildUniverse($conn, array(
                    'company_id' => $co, 'area_code' => (string) ($in['area_code'] ?? ''), 'area_name' => (string) ($in['area_name'] ?? ''),
                    'owner_dept' => (string) ($in['owner_dept'] ?? ''), 'risk_score' => (int) ($in['risk_score'] ?? 0)));
            }),
    ),

);
require __DIR__ . '/../includes/u13_screen_kit.php';
/* حزمةُ الحالاتِ الدنيا (بوابة ٩): تحميلٌ وفراغٌ وخطأٌ — مخفيةٌ افتراضًا
   ويُظهرها منطقُ الشاشةِ عند حالِها. الدالةُ من ux_components التي تُحمِّلها القشرة. */
if (function_exists('ems_states_bundle')) {
    echo ems_states_bundle('لم يُبنَ الكونُ الرقابيُّ بعدُ',
                           'أدرِج مجالاتِ المراجعةِ بدرجةِ خطرِ كلٍّ — فالتقييمُ السنويُّ للمخاطرِ أساسُ الخطةِ لا الاجتهاد');
}
