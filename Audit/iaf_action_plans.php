<?php
/**
 * Audit/iaf_action_plans.php — خطط المعالجة ومتابعتها
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: IAF-01 §4-3 · المتطلب: IAF-0028
 * الحكم: IAF-0028: المتابعةُ للمراجعِ — والإدارةُ تنفّذ ولا تشهد على نفسها
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Audit/iaf_action_plans.php',
    'screen'     => 'iaf_action_plans',
    'table'      => 'iaf_findings',
    'title'      => 'خطط المعالجة ومتابعتها',
    'icon'       => 'fa fa-list-check',
    'nature'     => 'read',
    'doc'        => 'IAF-01 §4-3 · IAF-0028',
    'intro'      => 'الاتفاقُ على خططِ المعالجةِ ومتابعةُ تنفيذها بمهلةِ كلٍّ',
    'rule'       => 'IAF-0028: المتابعةُ للمراجعِ — والإدارةُ تنفّذ ولا تشهد على نفسها',
    'empty_hint' => 'لا خططَ معالجةٍ قائمة',
    'where'       => 'action_plan IS NOT NULL AND state <> \'closed\'',
    'order'       => 'action_due ASC',
);
require __DIR__ . '/../includes/u13_screen_kit.php';
