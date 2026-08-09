<?php
/**
 * Audit/iaf_quality.php — تقييم جودة المراجعة الدوري
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: IAF-01 §4-1 · المتطلب: IAF-0008
 * الحكم: IAF-0031: والوظيفةُ التي تقيّم غيرَها تُقيَّم
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Audit/iaf_quality.php',
    'screen'     => 'iaf_quality',
    'table'      => 'iaf_quality_reviews',
    'title'      => 'تقييم جودة المراجعة الدوري',
    'icon'       => 'fa fa-award',
    'nature'     => 'document',
    'doc'        => 'IAF-01 §4-1 · IAF-0008',
    'intro'      => 'تقييمٌ داخليٌّ دوريٌّ وخارجيٌّ عند الانطباق',
    'rule'       => 'IAF-0031: والوظيفةُ التي تقيّم غيرَها تُقيَّم',
    'empty_hint' => 'لا تقييماتِ جودةٍ مسجَّلة',
    'order'       => 'reviewed_at DESC',
);
require __DIR__ . '/../includes/u13_screen_kit.php';
