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
);
require __DIR__ . '/../includes/u13_screen_kit.php';
