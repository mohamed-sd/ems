<?php
/**
 * Audit/iaf_escalations.php — المصعَّد إلى الجهة المشرفة
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: IAF-01 §2-2 · المتطلب: IAF-0041
 * الحكم: IAF §2-2: والتصعيدُ آليٌّ بالمهلةِ ويصل الجهةَ المشرفةَ مباشرةً
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Audit/iaf_escalations.php',
    'screen'     => 'iaf_escalations',
    'table'      => 'iaf_findings',
    'title'      => 'المصعَّد إلى الجهة المشرفة',
    'icon'       => 'fa fa-arrow-up-right-dots',
    'nature'     => 'read',
    'doc'        => 'IAF-01 §2-2 · IAF-0041',
    'intro'      => 'ما تجاوز مهلتَه فصُعِّد آليًّا — ولا يملك أحدٌ منعَه',
    'rule'       => 'IAF §2-2: والتصعيدُ آليٌّ بالمهلةِ ويصل الجهةَ المشرفةَ مباشرةً',
    'empty_hint' => 'لا ملاحظاتٍ مصعَّدة',
    'where'       => 'state = \'escalated\'',
    'order'       => 'escalated_at DESC',
);
require __DIR__ . '/../includes/u13_screen_kit.php';
