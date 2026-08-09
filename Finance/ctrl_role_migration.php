<?php
/**
 * Finance/ctrl_role_migration.php — ترحيل الأدوار المالية القديمة
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: FIN-MGR-01 §4-3 · المتطلب: FMGR-0018
 * الحكم: FMGR-0022: ولا يُحذف دورٌ قديمٌ قبل ترحيلِ حاملِه
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Finance/ctrl_role_migration.php',
    'screen'     => 'ctrl_role_migration',
    'table'      => 'fin_role_migration',
    'title'      => 'ترحيل الأدوار المالية القديمة',
    'icon'       => 'fa fa-right-left',
    'nature'     => 'register',
    'doc'        => 'FIN-MGR-01 §4-3 · FMGR-0018',
    'intro'      => 'إعادةُ تصنيفٍ وبناءٌ فوقَ الموجودِ لا اختراعُ نظامٍ موازٍ',
    'rule'       => 'FMGR-0022: ولا يُحذف دورٌ قديمٌ قبل ترحيلِ حاملِه',
    'empty_hint' => 'لا ترحيلاتٍ مسجَّلة',
    'order'       => 'old_role_id ASC',
);
require __DIR__ . '/../includes/u13_screen_kit.php';
