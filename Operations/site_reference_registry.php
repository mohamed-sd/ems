<?php
/**
 * operations/site_reference_registry.php — سجلات الموقع المرجعية (DEP-12 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: سطرُ مرجعٍ واحدٌ يخدم الموقع
 * المالك: إدارة الموقع · مصدرُ الحقيقة: site_reference_registry
 * الأصل: ورقةُ «إدارة الموقع» — السطح «سجلات الموقع المرجعية»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/site_reference_registry.php',
    'screen'     => 'site_ref_registry',
    'table'      => 'site_reference_registry',
    'title'      => 'سجلات الموقع المرجعية',
    'icon'       => 'fa fa-book',
    'nature'     => 'read',
    'doc'        => '01 · الدليل المعماري · سجلات الموقع المرجعية',
    'intro'      => 'المراجعُ التي يقرؤها الموقعُ بمالكِ كلٍّ ونطاقِ الاطّلاعِ عليه',
    'rule'       => 'المرجعُ يُقرأ عند مالكِه — والسطرُ هنا دالٌّ لا نسخة (§17 · §19)',
    'empty_hint' => 'لا مرجعَ مسجَّلٌ بعد',

);
require __DIR__ . '/../includes/u13_screen_kit.php';
