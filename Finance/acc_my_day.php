<?php
/**
 * Finance/acc_my_day.php — مساحة عملي اليوم — محاسب التخصص
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: FIN-ACC-01 §4-3 · المتطلب: FACC-0028
 * الحكم: FACC-0028..0035: بنطاقِه وتخصصِه وحدَهما — والحدثُ خارجَ نطاقِه لا يظهر له
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Finance/acc_my_day.php',
    'screen'     => 'acc_my_day',
    'table'      => 'work_items',
    'title'      => 'مساحة عملي اليوم — محاسب التخصص',
    'icon'       => 'fa fa-list-check',
    'nature'     => 'read',
    'doc'        => 'FIN-ACC-01 §4-3 · FACC-0028',
    'intro'      => 'ثمانية بنود بنطاق المحاسب وتخصصه وحدهما',
    'rule'       => 'FACC-0028..0035: بنطاقه وتخصصه وحدهما — والحدث خارج نطاقه لا يظهر له',
    'empty_hint' => 'لا مهام في نطاقك اليوم',
    'where'       => 'status NOT IN (\'closed_accepted\',\'cancelled\')',
    'order'       => 'due_at ASC',
    'scope_user'  => 'assigned_user_id',
);
require __DIR__ . '/../includes/u13_screen_kit.php';
