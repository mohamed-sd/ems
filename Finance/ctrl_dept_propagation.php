<?php
/**
 * Finance/ctrl_dept_propagation.php — الأحكام المنتشرة على الإدارات الست عشرة
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: PROP-01 §6-1 · المتطلب: PROP-01 §6-1
 * الحكم: PROP-01 §6-1: أُدرجت في السجلاتِ الذريةِ فعلًا — فتُفحص في تدقيقِ كلِّ إدارة
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Finance/ctrl_dept_propagation.php',
    'screen'     => 'ctrl_dept_propagation',
    'table'      => 'gov_dept_propagation',
    'title'      => 'الأحكام المنتشرة على الإدارات الست عشرة',
    'icon'       => 'fa fa-share-nodes',
    'nature'     => 'read',
    'doc'        => 'PROP-01 §6-1 · PROP-01 §6-1',
    'intro'      => 'خمسمئة وثلاثة وعشرون حكما منتشرا في ست عشرة إدارة',
    'rule'       => 'PROP-01 §6-1: أدرجت في السجلات الذرية فعلا — فتفحص في تدقيق كل إدارة',
    'empty_hint' => 'لم يبذر الانتشار بعد',
    'order'       => 'propagated DESC',
    'global_ref'  => 1,
);
require __DIR__ . '/../includes/u13_screen_kit.php';
