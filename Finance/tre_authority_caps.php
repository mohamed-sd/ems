<?php
/**
 * Finance/tre_authority_caps.php — سقوف سلطة الالتزام والدفع
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: FIN-ACC-01 §4-7 · المتطلب: FACC-0042
 * الحكم: CEO-Y0120: وما تجاوز سقفَ المديرِ الماليِّ والنائبِ يصل الرئيسَ ولا يُنفَّذ قبلَ قرارِه
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Finance/tre_authority_caps.php',
    'screen'     => 'tre_authority_caps',
    'table'      => 'fin_authority_caps',
    'title'      => 'سقوف سلطة الالتزام والدفع',
    'icon'       => 'fa fa-gauge',
    'nature'     => 'register',
    'doc'        => 'FIN-ACC-01 §4-7 · FACC-0042',
    'intro'      => 'اعتماد الالتزام أو الدفع لا يمنح بلا سقف معلن لصاحبه',
    'rule'       => 'CEO-Y0120: وما تجاوز سقف المدير المالي والنائب يصل الرئيس ولا ينفذ قبل قراره',
    'empty_hint' => 'لا سقوف معرفة',
    'order'       => 'max_amount DESC',
);
require __DIR__ . '/../includes/u13_screen_kit.php';
