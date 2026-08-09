<?php
/**
 * Audit/iaf_findings.php — ملاحظات المراجعة
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: IAF-01 §4-3 · المتطلب: IAF-0025
 * الحكم: لا تُغلق ملاحظةٌ بلا دليلٍ يقبله المراجعُ — ولا تُغلق من الإدارةِ نفسِها
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Audit/iaf_findings.php',
    'screen'     => 'iaf_findings',
    'table'      => 'iaf_findings',
    'title'      => 'ملاحظات المراجعة',
    'icon'       => 'fa fa-flag',
    'nature'     => 'document',
    'doc'        => 'IAF-01 §4-3 · IAF-0025',
    'intro'      => 'كلُّ ملاحظةٍ بخطورتِها ومهلةِ ردِّها وخطةِ معالجتها',
    'rule'       => 'لا تُغلق ملاحظةٌ بلا دليلٍ يقبله المراجعُ — ولا تُغلق من الإدارةِ نفسِها',
    'empty_hint' => 'لا ملاحظاتٍ مفتوحة',
    'order'       => 'raised_at DESC',

    'actions'    => array(
        'accept_evidence' => array(
            'code'  => 'iaf.evidence.accept',
            'label' => 'قبولُ دليلٍ على ملاحظة',
            'rule'  => 'الدليلُ يقبله المراجعُ — وقبولُه شرطُ الإغلاق',
            'fields' => array('finding_no' => 'رقمُ الملاحظة', 'evidence_ref' => 'مرجعُ الدليل'),
            'run' => function ($conn, $co, $uid, $in) {
                require_once __DIR__ . '/../app/Services/Audit/InternalAuditService.php';
                return \App\Services\Audit\InternalAuditService::acceptEvidence($conn, array(
                    'company_id' => $co, 'finding_no' => (string) ($in['finding_no'] ?? ''),
                    'evidence_ref' => (string) ($in['evidence_ref'] ?? ''), 'accepted_by' => $uid));
            }),
        'close' => array(
            'code'  => 'iaf.finding.close',
            'label' => 'إغلاقُ ملاحظة',
            'rule'  => 'CEO-Y0125: ولا يملك الرئيسُ إغلاقَها بلا دليل · ولا تُغلق من الإدارةِ نفسِها',
            'fields' => array('finding_no' => 'رقمُ الملاحظة'),
            'run' => function ($conn, $co, $uid, $in) {
                require_once __DIR__ . '/../app/Services/Audit/InternalAuditService.php';
                return \App\Services\Audit\InternalAuditService::closeFinding($conn, array(
                    'company_id' => $co, 'finding_no' => (string) ($in['finding_no'] ?? ''),
                    'closed_by' => $uid));
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
