<?php
/**
 * Audit/iaf_competencies.php — اختصاصات المراجعة العشرون
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: IAF-01 §4-3 · المتطلب: IAF-0012
 * الحكم: IAF §4-3: ولكلِّ اختصاصٍ شاهدُ قبولٍ مكتوب
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Audit/iaf_competencies.php',
    'screen'     => 'iaf_competencies',
    'table'      => 'iaf_competencies',
    'title'      => 'اختصاصات المراجعة العشرون',
    'icon'       => 'fa fa-clipboard-list',
    'nature'     => 'register',
    'doc'        => 'IAF-01 §4-3 · IAF-0012',
    'intro'      => 'عشرون اختصاصا من الميثاق إلى تقييم الجودة',
    'rule'       => 'IAF §4-3: ولكل اختصاص شاهد قبول مكتوب',
    'empty_hint' => 'لم تبذر الاختصاصات بعد',
    'order'       => 'seq ASC',
    'global_ref'  => 1,
);
require __DIR__ . '/../includes/u13_screen_kit.php';
