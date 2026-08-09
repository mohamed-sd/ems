<?php
/**
 * Finance/tre_cycle_stages.php — مراحل دورتي الدفع والقبض
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: FIN-TRE-01 §4-4 · المتطلب: FTRE-0059
 * الحكم: FTRE-0059/0060: اختبارُ تسلسل — المراحلُ بترتيبها ولا تُقفز
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Finance/tre_cycle_stages.php',
    'screen'     => 'tre_cycle_stages',
    'table'      => 'fin_cycle_stages',
    'title'      => 'مراحل دورتي الدفع والقبض',
    'icon'       => 'fa fa-diagram-successor',
    'nature'     => 'register',
    'doc'        => 'FIN-TRE-01 §4-4 · FTRE-0059',
    'intro'      => 'خمسَ عشرةَ مرحلةً للدفعِ وتسعٌ للقبض — بترتيبها ولا تُقفز',
    'rule'       => 'FTRE-0059/0060: اختبارُ تسلسل — المراحلُ بترتيبها ولا تُقفز',
    'empty_hint' => 'لم تُبذر المراحلُ بعدُ',
    'where'       => 'cycle_kind IN (\'payment\',\'receipt\')',
    'order'       => 'cycle_kind ASC, seq ASC',
    'global_ref'  => 1,
);
require __DIR__ . '/../includes/u13_screen_kit.php';
