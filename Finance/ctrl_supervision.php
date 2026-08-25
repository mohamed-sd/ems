<?php
/**
 * Finance/ctrl_supervision.php — إشراف رئيس الحسابات على محاسبي التخصصات
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: FIN-CTRL-01 §4-1 · المتطلب: FCTRL-0042
 * الحكم: FCTRL-0042: رئيسُ الحساباتِ يوزّع بالقالبِ لا بمنحٍ فرديٍّ كلَّ مرة
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Finance/ctrl_supervision.php',
    'screen'     => 'ctrl_supervision',
    'table'      => 'fin_accountants',
    'title'      => 'إشراف رئيس الحسابات على محاسبي التخصصات',
    'icon'       => 'fa fa-users-gear',
    'nature'     => 'register',
    'doc'        => 'FIN-CTRL-01 §4-1 · FCTRL-0042',
    'intro'      => 'توزيع الأعمال وتحديد نطاق كل محاسب ومنع التداخل غير المحكوم',
    'rule'       => 'FCTRL-0042: رئيس الحسابات يوزع بالقالب لا بمنح فردي كل مرة',
    'empty_hint' => 'لا محاسبين مسندين',
    'where'       => '(is_deleted IS NULL OR is_deleted = 0)',
    'order'       => 'spec_code ASC',
);
require __DIR__ . '/../includes/u13_screen_kit.php';
