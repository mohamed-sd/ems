<?php
/**
 * Finance/ctrl_doc_registry.php — سجل بنود الوثائق وتغطيتها
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: FIN-OBL-01 OBL-0307 · المتطلب: OBL-0307
 * الحكم: OBL-0307: البندُ المعلَنُ بلا أثرٍ حيٍّ ثغرةٌ تُسجَّل لا تُهمَل
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Finance/ctrl_doc_registry.php',
    'screen'     => 'ctrl_doc_registry',
    'table'      => 'gov_doc_registry',
    'title'      => 'سجل بنود الوثائق وتغطيتها',
    'icon'       => 'fa fa-list-check',
    'nature'     => 'register',
    'doc'        => 'FIN-OBL-01 OBL-0307 · OBL-0307',
    'intro'      => 'كلُّ بندٍ تعلنه الوثائقُ وأثرُه الحيُّ — والفارغُ ثغرةٌ تُرى',
    'rule'       => 'OBL-0307: البندُ المعلَنُ بلا أثرٍ حيٍّ ثغرةٌ تُسجَّل لا تُهمَل',
    'empty_hint' => 'لم يُزامَن السجلُّ بعدُ — شغّل u13_reverse_audit --sync',
    'order'       => 'doc_code ASC, family ASC, seq ASC',
    'global_ref'  => 1,
);
require __DIR__ . '/../includes/u13_screen_kit.php';
