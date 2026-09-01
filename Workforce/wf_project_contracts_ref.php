<?php
/**
 * workforce/wf_project_contracts_ref.php — عقود المشاريع المرجعية (DEP-13 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: عقدُ مشروعٍ واحدٌ لفردٍ واحد
 * المالك: إدارة القوى التشغيلية · مصدرُ الحقيقة: worker_contract
 * الأصل: ورقةُ «إدارة القوى التشغيلية» — السطح «عقود المشاريع المرجعية»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'workforce/wf_project_contracts_ref.php',
    'screen'     => 'wf_project_contracts',
    'table'      => 'worker_contract',
    'title'      => 'عقود المشاريع المرجعية',
    'icon'       => 'fa fa-file-contract',
    'nature'     => 'read',
    'doc'        => '01 · الدليل المعماري · عقود المشاريع المرجعية',
    'intro'      => 'عقودُ المشاريعِ مرجعًا: مدّتُها ومحفِّزُ انتهائِها وحالتُها',
    'rule'       => 'كلُّ حقلٍ مشتقٌّ أو مستورَد — والعقدُ يُحرَّر عند مالكِه لا هنا (§17 · §19)',
    'empty_hint' => 'لا عقدَ مشروعٍ مسجَّلٌ بعد',
    'order'       => 'date_start DESC, id DESC',

);
require __DIR__ . '/../includes/u13_screen_kit.php';
