<?php
/**
 * Finance/ctrl_doc_variance.php — مخالفات الوثائق وحسمُها
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: FIN-OBL-01 OBL-0307 · المتطلب: OBL-0307
 * الحكم: OBL-0307: والحدثُ الذي لا مُطلِقَ له ثغرةٌ تُسجَّل عيبًا لا تُهمَل — والقياسُ عليه
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Finance/ctrl_doc_variance.php',
    'screen'     => 'ctrl_doc_variance',
    'table'      => 'gov_doc_variance',
    'title'      => 'مخالفات الوثائق وحسمها',
    'icon'       => 'fa fa-scale-unbalanced',
    'nature'     => 'register',
    'doc'        => 'FIN-OBL-01 OBL-0307 · OBL-0307',
    'intro'      => 'تعارض الوثيقة مع نفسها ثغرة تسجل ولا تهمل — ولكل حسم أساس مكتوب',
    'rule'       => 'OBL-0307: والحدث الذي لا مطلق له ثغرة تسجل عيبا لا تهمل — والقياس عليه',
    'empty_hint' => 'لا مخالفات مكشوفة',
    'order'       => 'variance_code ASC',
    'global_ref'  => 1,
);
require __DIR__ . '/../includes/u13_screen_kit.php';
