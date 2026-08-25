<?php
/**
 * Audit/iaf_closure.php — محاضر الإغلاق
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: IAF-01 §4-3 · المتطلب: IAF-0029
 * الحكم: CEO-Y0125: ولا يملك الرئيسُ إغلاقَ ملاحظةٍ بلا دليلٍ يقبله المراجع
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Audit/iaf_closure.php',
    'screen'     => 'iaf_closure',
    'table'      => 'iaf_findings',
    'title'      => 'محاضر الإغلاق',
    'icon'       => 'fa fa-circle-check',
    'nature'     => 'read',
    'doc'        => 'IAF-01 §4-3 · IAF-0029',
    'intro'      => 'ما أغلق بدليل قبله المراجع — ومن أغلقه ومتى',
    'rule'       => 'CEO-Y0125: ولا يملك الرئيس إغلاق ملاحظة بلا دليل يقبله المراجع',
    'empty_hint' => 'لا ملاحظات مغلقة بعد',
    'where'       => 'state = \'closed\'',
    'order'       => 'closed_at DESC',
);
require __DIR__ . '/../includes/u13_screen_kit.php';
