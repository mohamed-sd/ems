<?php
/**
 * Portal/ceo_assignments.php — موافقات التكليف
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: named · المصدر: PROP-01 §5-1 · المتطلب: CEO-Y0121
 * الحكم: CEO-Y0121: التكليفُ لا يسري ولا يمنح صلاحيةً قبلَ الموافقة · CEO-Y0122: والمتعارضُ لا يُعرض حتى يُحسم
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Portal/ceo_assignments.php',
    'screen'     => 'ceo_assignments',
    'table'      => 'exec_assignments',
    'title'      => 'موافقات التكليف',
    'icon'       => 'fa fa-user-shield',
    'nature'     => 'document',
    'doc'        => 'PROP-01 §5-1 · CEO-Y0121',
    'intro'      => 'كل مسمى قيادي أو رقابي — بفحص تعارض الواجبات والاستقلال آليا قبل العرض',
    'rule'       => 'CEO-Y0121: التكليف لا يسري ولا يمنح صلاحية قبل الموافقة · CEO-Y0122: والمتعارض لا يعرض حتى يحسم',
    'empty_hint' => 'لا طلبات تكليف معروضة',
    'order'       => 'requested_at DESC',

    'actions'    => array(
        'approve' => array(
            'code'  => 'exec.assign.decide',
            'label' => 'الموافقة على تكليف',
            'rule'  => 'CEO-Y0121: للرئيس التنفيذي حصرا — والمتعارض لا يقرر (CEO-Y0122)',
            'fields' => array('assignment_no' => 'رقم التكليف', 'decision_reason' => 'حيثيات القرار'),
            'optional' => array('decision_reason' => true),
            'run' => function ($conn, $co, $uid, $in) {
                require_once __DIR__ . '/../app/Services/Exec/AssignmentGate.php';
                return \App\Services\Exec\AssignmentGate::decide($conn, array(
                    'company_id' => $co, 'assignment_no' => (string) ($in['assignment_no'] ?? ''),
                    'decided_by' => $uid, 'decision' => 'approved',
                    'decision_reason' => (string) ($in['decision_reason'] ?? '')));
            }),
        'reject' => array(
            'code'  => 'exec.assign.decide',
            'label' => 'رد تكليف',
            'rule'  => 'ملزم بحيثياته — ولا يرد بلا سبب',
            'fields' => array('assignment_no' => 'رقم التكليف', 'decision_reason' => 'سبب الرد'),
            'run' => function ($conn, $co, $uid, $in) {
                require_once __DIR__ . '/../app/Services/Exec/AssignmentGate.php';
                return \App\Services\Exec\AssignmentGate::decide($conn, array(
                    'company_id' => $co, 'assignment_no' => (string) ($in['assignment_no'] ?? ''),
                    'decided_by' => $uid, 'decision' => 'rejected',
                    'decision_reason' => (string) ($in['decision_reason'] ?? '')));
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
