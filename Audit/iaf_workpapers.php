<?php
/**
 * Audit/iaf_workpapers.php — أوراق العمل والأدلة
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: IAF-01 §4-4 · المتطلب: IAF-0037
 * الحكم: IAF-0037: سحبُ الأدلةِ وحفظُ نسخِ مراجعةٍ غيرِ قابلةٍ للتعديل
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Audit/iaf_workpapers.php',
    'screen'     => 'iaf_workpapers',
    'table'      => 'iaf_workpapers',
    'title'      => 'أوراق العمل والأدلة',
    'icon'       => 'fa fa-folder-open',
    'nature'     => 'register',
    'doc'        => 'IAF-01 §4-4 · IAF-0037',
    'intro'      => 'نسخُ أدلةٍ غيرُ قابلةٍ للتعديلِ ببصمةِ كلٍّ',
    'rule'       => 'IAF-0037: سحبُ الأدلةِ وحفظُ نسخِ مراجعةٍ غيرِ قابلةٍ للتعديل',
    'empty_hint' => 'لا أوراقَ عملٍ ملتقَطة',
    'order'       => 'captured_at DESC',
);
require __DIR__ . '/../includes/u13_screen_kit.php';
