<?php
/**
 * fleet/state_change_log.php — سجل تغير الحالة (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: واقعةُ تغيُّرِ حالةٍ واحدةٌ على أصل
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: fleet_equipment_history
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «سجل تغير الحالة»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/state_change_log.php',
    'screen'     => 'flt_state_history',
    'table'      => 'fleet_equipment_history',
    'title'      => 'سجل تغير الحالة',
    'icon'       => 'fa fa-clock-rotate-left',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · سجل تغير الحالة',
    'intro'      => 'كلُّ تحوّلِ حالةٍ بسببِه وواقعتِه ومدةِ الحالةِ السابقة — تاريخٌ لا يُدهَس',
    'rule'       => 'السجلُّ إلحاقيٌّ: الحالةُ الجديدةُ صفٌّ جديدٌ والقديمةُ تبقى (§18 Current vs History)',
    'empty_hint' => 'لا واقعةَ تغيُّرِ حالةٍ مسجَّلةٌ بعد',
    'order'       => 'event_date DESC, id DESC',

);
require __DIR__ . '/../includes/u13_screen_kit.php';
