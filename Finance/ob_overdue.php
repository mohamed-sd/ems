<?php
/**
 * Finance/ob_overdue.php — الالتزامات المتأخرة والذمم الدائنة
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: registered · المصدر: FIN-OBL-01 §4-23 · المتطلب: OBL-0045
 * الحكم: OR-05: يظهر في أعمارِ الذممِ · وتُنشر إشارةُ إنذارٍ إن تجاوز التأخيرُ الحد
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Finance/ob_overdue.php',
    'screen'     => 'ob_overdue',
    'table'      => 'fin_obl_schedule',
    'title'      => 'الالتزامات المتأخرة والذمم الدائنة',
    'icon'       => 'fa fa-triangle-exclamation',
    'nature'     => 'read',
    'doc'        => 'FIN-OBL-01 §4-23 · OBL-0045',
    'intro'      => 'ما مر تاريخه بلا سداد فرحل إلى الذمم الدائنة',
    'rule'       => 'OR-05: يظهر في أعمار الذمم · وتنشر إشارة إنذار إن تجاوز التأخير الحد',
    'empty_hint' => 'لا التزام متأخرا — وهذا هو المطلوب',
    'where'       => '(state = \'moved_to_payables\' OR (due_date < CURDATE() AND settled < l2_recognized AND state <> \'closed\'))',
    'order'       => 'due_date ASC',
);
/* القشرةُ الموحَّدة: العُدّةُ أدناه هي التي تنفّذ include inheader.php ثم insidebar.php */
require __DIR__ . '/../includes/u13_screen_kit.php';
/* حالاتُ الشاشةِ (UXW-01 ⑨) — عناصرُ مخفيةٌ للفراغِ والخطأِ والتحميلِ تُلحق بذيلِ
   الصفحة، وحالةُ الفراغِ المرئيةُ يعرضها جدولُ العُدّةِ بنصِّ empty_hint نفسِه */
echo ems_states_bundle('لا التزام متأخرا — وهذا هو المطلوب', 'غير الفترة أو تحقق من توفر السجلات');
