<?php
/**
 * Finance/ctrl_quality_kpis.php — مؤشرات جودة المحاسبة الاثنا عشر
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: FIN-CTRL-01 §4-3 · المتطلب: FCTRL-0047
 * الحكم: FCTRL-0047: محسوبٌ من القيودِ لا من إدخالٍ يدوي — ولكلٍّ مصدرُ حسابِه مكتوب
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Finance/ctrl_quality_kpis.php',
    'screen'     => 'ctrl_quality_kpis',
    'table'      => 'fin_quality_kpis',
    'title'      => 'مؤشرات جودة المحاسبة الاثنا عشر',
    'icon'       => 'fa fa-gauge-high',
    'nature'     => 'read',
    'doc'        => 'FIN-CTRL-01 §4-3 · FCTRL-0047',
    'intro'      => 'اثنا عشر مؤشرا بحده ومالكه ودورية قياسه',
    'rule'       => 'FCTRL-0047: محسوب من القيود لا من إدخال يدوي — ولكل مصدر حسابه مكتوب',
    'empty_hint' => 'لم تبذر المؤشرات بعد',
    'order'       => 'seq ASC',
    'global_ref'  => 1,
);
require __DIR__ . '/../includes/u13_screen_kit.php';
