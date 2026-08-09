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
);
require __DIR__ . '/../includes/u13_screen_kit.php';
