<?php
/**
 * Finance/tre_unit_roles.php — الأدوار الثمانية داخل وحدة الخزينة
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: FIN-TRE-01 §4-2 · المتطلب: FTRE-0002
 * الحكم: FMGR-0021: أمينُ الخزينةِ يُفصل عن منفِّذِ المدفوعاتِ ومُعِدِّ المطابقة
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Finance/tre_unit_roles.php',
    'screen'     => 'tre_unit_roles',
    'table'      => 'fin_treasury_roles',
    'title'      => 'الأدوار الثمانية داخل وحدة الخزينة',
    'icon'       => 'fa fa-user-group',
    'nature'     => 'register',
    'doc'        => 'FIN-TRE-01 §4-2 · FTRE-0002',
    'intro'      => 'ثمانية أدوار لكل حسابه ونطاقه وسقفه المعلن',
    'rule'       => 'FMGR-0021: أمين الخزينة يفصل عن منفذ المدفوعات ومعد المطابقة',
    'empty_hint' => 'لم تبذر الأدوار بعد',
    'order'       => 'seq ASC',
    'global_ref'  => 1,
);
require __DIR__ . '/../includes/u13_screen_kit.php';
