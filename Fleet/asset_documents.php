<?php
/**
 * fleet/asset_documents.php — مستندات الأصل (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: مستندٌ واحدٌ لأصلٍ واحد
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: equipment_documents
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «مستندات الأصل»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/asset_documents.php',
    'screen'     => 'flt_asset_docs',
    'table'      => 'equipment_documents',
    'title'      => 'مستندات الأصل',
    'icon'       => 'fa fa-folder-open',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · مستندات الأصل',
    'intro'      => 'مستنداتُ الأصلِ بتواريخِ إصدارِها وانتهائِها ومسؤولِ تجديدِها',
    'rule'       => 'الإلزاميُّ منها شرطُ تشغيل — والمنتهي يوقف الأهليةَ بأثرِه لا بتذكيرٍ فقط',
    'empty_hint' => 'لا مستندَ مسجَّلٌ لهذا الأصلِ بعد',
    'where'       => 'subject_type = \'equipment\' AND is_deleted = 0',

);
require __DIR__ . '/../includes/u13_screen_kit.php';
