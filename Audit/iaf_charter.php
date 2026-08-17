<?php
/**
 * Audit/iaf_charter.php — ميثاق المراجعة الداخلية
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: IAF-01 §4-1 · المتطلب: IAF-0007
 * الحكم: IAF-0004: لا تتبع الماليةَ ولا رئيسَ الحساباتِ ولا الحوكمةَ في إصدارِ أحكامها
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Audit/iaf_charter.php',
    'screen'     => 'iaf_charter',
    'table'      => 'iaf_charter',
    'title'      => 'ميثاق المراجعة الداخلية',
    'icon'       => 'fa fa-scroll',
    'nature'     => 'document',
    'doc'        => 'IAF-01 §4-1 · IAF-0007',
    'intro'      => 'الغرضُ والسلطةُ والمسؤوليةُ والاستقلال — معتمدًا من الجهةِ المشرفة',
    'rule'       => 'IAF-0004: لا تتبع الماليةَ ولا رئيسَ الحساباتِ ولا الحوكمةَ في إصدارِ أحكامها',
    'empty_hint' => 'لا ميثاقَ معتمدٌ بعدُ — ولا كونَ رقابيٌّ بلا ميثاق',

    'actions'    => array(
        'approve' => array(
            'code'  => 'iaf.charter.approve',
            'label' => 'اعتمادُ الميثاق',
            'rule'  => 'IAF-0044: ولا كونَ رقابيٌّ بلا ميثاقٍ معتمد',
            'fields' => array('version' => 'نسخةُ الميثاق'),
            'run' => function ($conn, $co, $uid, $in) {
                require_once __DIR__ . '/../app/Services/Audit/InternalAuditService.php';
                return \App\Services\Audit\InternalAuditService::approveCharter($conn, array(
                    'company_id' => $co, 'version' => (string) ($in['version'] ?? ''), 'actor' => $uid));
            }),
    ),

);
require __DIR__ . '/../includes/u13_screen_kit.php';
/* حزمةُ الحالاتِ الدنيا (بوابة ٩): تحميلٌ وفراغٌ وخطأٌ — مخفيةٌ افتراضًا
   ويُظهرها منطقُ الشاشةِ عند حالِها. الدالةُ من ux_components التي تُحمِّلها القشرة. */
if (function_exists('ems_states_bundle')) {
    echo ems_states_bundle('لا ميثاقَ معتمدًا بعدُ لهذه الشركة',
                           'يُعتمد ميثاقُ المراجعةِ الداخليةِ من الجهةِ المشرفةِ أولًا — ولا كونَ رقابيٌّ بلا ميثاقٍ معتمد');
}
