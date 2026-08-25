<?php
/**
 * Finance/ctrl_authority_limits.php — الحدود الصريحة — ما لا يملكه كل دور
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: FIN-ACC-01 §4-8 · المتطلب: FACC-0045
 * الحكم: الحدُّ بلا مُنفِذٍ دعوى لا قيد — والعمودُ «المُنفِذ» يُفحص في كلِّ بوابة
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Finance/ctrl_authority_limits.php',
    'screen'     => 'ctrl_authority_limits',
    'table'      => 'gov_authority_limits',
    'title'      => 'الحدود الصريحة — ما لا يملكه كل دور',
    'icon'       => 'fa fa-ban',
    'nature'     => 'register',
    'doc'        => 'FIN-ACC-01 §4-8 · FACC-0045',
    'intro'      => 'خمسة وخمسون حدا من خمس وثائق — ولكل منفذه الحي',
    'rule'       => 'الحد بلا منفذ دعوى لا قيد — والعمود «المنفذ» يفحص في كل بوابة',
    'empty_hint' => 'لم تبذر الحدود بعد',
    'order'       => 'doc_code ASC, seq ASC',
    'global_ref'  => 1,
);
require __DIR__ . '/../includes/u13_screen_kit.php';
