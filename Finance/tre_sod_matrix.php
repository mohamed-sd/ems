<?php
/**
 * Finance/tre_sod_matrix.php — مصفوفة فصل الواجبات الثلاثة عشر
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: FIN-ACC-01 §4-9 · المتطلب: FACC-0058
 * الحكم: PROP-01 §7-2 ⑩: صفرُ حسابٍ يجمع زوجًا من أزواجِ فصلِ الواجبات
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Finance/tre_sod_matrix.php',
    'screen'     => 'tre_sod_matrix',
    'table'      => 'sec_sod_pairs',
    'title'      => 'مصفوفة فصل الواجبات الثلاثة عشر',
    'icon'       => 'fa fa-shield-halved',
    'nature'     => 'register',
    'doc'        => 'FIN-ACC-01 §4-9 · FACC-0058',
    'intro'      => 'ثلاثةَ عشرَ زوجًا — قيدٌ بنيويٌّ يرفض التكليفَ لا سياسةٌ مكتوبة',
    'rule'       => 'PROP-01 §7-2 ⑩: صفرُ حسابٍ يجمع زوجًا من أزواجِ فصلِ الواجبات',
    'empty_hint' => 'لم تُبذر المصفوفةُ بعدُ',
    'order'       => 'code ASC',
    'global_ref'  => 1,
);
require __DIR__ . '/../includes/u13_screen_kit.php';
