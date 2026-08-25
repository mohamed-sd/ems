<?php
/**
 * Finance/ob_register.php — سجل الالتزامات
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: registered · المصدر: FIN-OBL-01 §4-23 · المتطلب: OBL-0042
 * الحكم: OR-01: العقدُ النافذُ يولّد جدولَ استحقاقٍ لكلِّ مدتِه فورًا · والصمتُ عنه يُخفي التزامًا حقيقيًّا
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Finance/ob_register.php',
    'screen'     => 'ob_register',
    'table'      => 'fin_obl_register',
    'title'      => 'سجل الالتزامات',
    'icon'       => 'fa fa-file-contract',
    'nature'     => 'document',
    'doc'        => 'FIN-OBL-01 §4-23 · OBL-0042',
    'intro'      => 'كل التزام مولد عند نفاذ عقده — لا عند أول دفعة',
    'rule'       => 'OR-01: العقد النافذ يولد جدول استحقاق لكل مدته فورا · والصمت عنه يخفي التزاما حقيقيا',
    'empty_hint' => 'لا التزام مولد بعد — يولد آليا عند نفاذ عقد باختبار تجنب مسجل',

    'actions'    => array(
        'terminate' => array(
            'code'  => 'fin.obl.terminate',
            'label' => 'إنهاء التزام بإنهاء عقده',
            'rule'  => 'OR-08: يغلق ما لم يستحق بعد — والمستحق قبل الإنهاء يبقى دينا',
            'fields' => array('source_kind' => 'نوع المصدر (contract)', 'source_ref' => 'مرجع العقد',
                              'on_date' => 'تاريخ الإنهاء (YYYY-MM-DD)', 'why' => 'سبب الإنهاء'),
            'run' => function ($conn, $co, $uid, $in) {
                require_once __DIR__ . '/../app/Services/Finance/ObligationEngine.php';
                $d = trim((string) ($in['on_date'] ?? ''));
                if ($d === '' || !preg_match('~^\\d{4}-\\d{2}-\\d{2}$~', $d)) {
                    return array('ok' => false, 'reason' => 'تاريخ الإنهاء لازم بصيغة YYYY-MM-DD');
                }
                return \App\Services\Finance\ObligationEngine::terminate($conn, $co,
                    (string) ($in['source_kind'] ?? 'contract'), (string) ($in['source_ref'] ?? ''),
                    $d, (string) ($in['why'] ?? ''));
            }),
    ),
);
/* ── UXW-01 · القشرة (بوابة ٤): العُدّةُ التاليةُ هي التي تُنفّذ include inheader.php
   (القشرةُ الموحَّدة) ثم insidebar.php قبل التصيير (u13_screen_kit §القالب)
   — فالشاشةُ داخلَ القشرةِ لا خارجَها. */
require __DIR__ . '/../includes/u13_screen_kit.php';
/* حزمةُ الحالاتِ الدنيا (بوابة ٩): تحميلٌ وفراغٌ وخطأٌ — مخفيةٌ افتراضًا
   ويُظهرها منطقُ الشاشةِ عند حالِها. الدالةُ من ux_components التي تُحمِّلها القشرة. */
if (function_exists('ems_states_bundle')) {
    echo ems_states_bundle('لا التزامات مسجلة ضمن هذا الترشيح',
                           'يولد الالتزام آليا عند نفاذ عقد باختبار تجنب مسجل — وسع الترشيح أو راجع العقود النافذة');
}
