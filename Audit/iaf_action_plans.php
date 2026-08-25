<?php
/**
 * Audit/iaf_action_plans.php — خطط المعالجة ومتابعتها
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: IAF-01 §4-3 · المتطلب: IAF-0028
 * الحكم: IAF-0028: المتابعةُ للمراجعِ — والإدارةُ تنفّذ ولا تشهد على نفسها
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Audit/iaf_action_plans.php',
    'screen'     => 'iaf_action_plans',
    'table'      => 'iaf_findings',
    'title'      => 'خطط المعالجة ومتابعتها',
    'icon'       => 'fa fa-list-check',
    'nature'     => 'document',
    'doc'        => 'IAF-01 §4-3 · IAF-0028',
    'intro'      => 'الاتفاق على خطط المعالجة ومتابعة تنفيذها بمهلة كل',
    'rule'       => 'IAF-0028: المتابعة للمراجع — والإدارة تنفذ ولا تشهد على نفسها',
    'empty_hint' => 'لا خطط معالجة قائمة',
    'where'       => 'action_plan IS NOT NULL AND state <> \'closed\'',
    'order'       => 'action_due ASC',

    'actions'    => array(
        'set' => array(
            'code'  => 'iaf.actionplan.set',
            'label' => 'ضبط خطة المعالجة ومالكها ومهلتها',
            'rule'  => 'IAF-0044: لا خطة معالجة بلا رد إدارة سابق',
            'fields' => array('finding_no' => 'رقم الملاحظة', 'action_plan' => 'خطة المعالجة', 'action_owner' => 'مالك الإجراء', 'action_due' => 'المهلة'),
            'run' => function ($conn, $co, $uid, $in) {
                require_once __DIR__ . '/../app/Services/Audit/InternalAuditService.php';
                return \App\Services\Audit\InternalAuditService::setActionPlan($conn, array(
                    'company_id' => $co, 'finding_no' => (string) ($in['finding_no'] ?? ''), 'action_plan' => (string) ($in['action_plan'] ?? ''),
                    'action_owner' => (string) ($in['action_owner'] ?? ''), 'action_due' => (string) ($in['action_due'] ?? '')));
            }),
    ),

);
require __DIR__ . '/../includes/u13_screen_kit.php';
