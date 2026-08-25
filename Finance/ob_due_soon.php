<?php
/**
 * Finance/ob_due_soon.php — المستحق قريبًا والتذكيرات
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: registered · المصدر: FIN-OBL-01 §4-23 · المتطلب: OBL-0044
 * الحكم: OR-04: صفرُ استحقاقٍ يمرُّ بلا تذكيرٍ قبلَه
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Finance/ob_due_soon.php',
    'screen'     => 'ob_due_soon',
    'table'      => 'fin_obl_schedule',
    'title'      => 'المستحق قريبا والتذكيرات',
    'icon'       => 'fa fa-bell',
    'nature'     => 'read',
    'doc'        => 'FIN-OBL-01 §4-23 · OBL-0044',
    'intro'      => 'ما يستحق خلال ثلاثين يوما — بمهلة تذكير قبل كل',
    'rule'       => 'OR-04: صفر استحقاق يمر بلا تذكير قبله',
    'empty_hint' => 'لا مستحق خلال ثلاثين يوما',
    'where'       => 'state IN (\'scheduled\',\'recognized\',\'invoiced\') AND due_date >= CURDATE() AND due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)',
    'order'       => 'due_date ASC',
);
/* ── UXW-01 · القشرة (بوابة ٤): العُدّةُ التاليةُ هي التي تُنفّذ include inheader.php
   (القشرةُ الموحَّدة) ثم insidebar.php قبل التصيير (u13_screen_kit §القالب)
   — فالشاشةُ داخلَ القشرةِ لا خارجَها. */
require __DIR__ . '/../includes/u13_screen_kit.php';
/* حزمةُ الحالاتِ الدنيا (بوابة ٩): تحميلٌ وفراغٌ وخطأٌ — مخفيةٌ افتراضًا
   ويُظهرها منطقُ الشاشةِ عند حالِها. الدالةُ من ux_components التي تُحمِّلها القشرة. */
if (function_exists('ems_states_bundle')) {
    echo ems_states_bundle('لا مستحق خلال الثلاثين يوما القادمة',
                           'وسع الأفق الزمني أو راجع جدول الاستحقاق');
}
