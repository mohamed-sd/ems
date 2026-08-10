<?php
/**
 * Audit/iaf_responses.php — ردود الإدارات على الملاحظات
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: IAF-01 §4-3 · المتطلب: IAF-0027
 * الحكم: BF-15: والردُّ إلزاميٌّ بمهلةٍ · والسكوتُ يُصعَّد
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Audit/iaf_responses.php',
    'screen'     => 'iaf_responses',
    'table'      => 'iaf_findings',
    'title'      => 'ردود الإدارات على الملاحظات',
    'icon'       => 'fa fa-comments',
    'nature'     => 'document',
    'doc'        => 'IAF-01 §4-3 · IAF-0027',
    'intro'      => 'ردُّ الإدارةِ إلزاميٌّ بمهلة — والسكوتُ يُصعَّد للجهةِ المشرفة',
    'rule'       => 'BF-15: والردُّ إلزاميٌّ بمهلةٍ · والسكوتُ يُصعَّد',
    'empty_hint' => 'لا ردودَ واردةً بعدُ',
    'where'       => 'state IN (\'responded\',\'in_remediation\',\'evidence_submitted\')',
    'order'       => 'responded_at DESC',

    'actions'    => array(
        'submit' => array(
            'code'  => 'iaf.response.submit',
            'label' => 'ردُّ الإدارةِ على ملاحظة',
            'rule'  => 'فصلُ الواجبات: الردُّ فعلُ الإدارةِ المُلاحَظِ عليها لا فعلُ المراجع',
            'fields' => array('finding_no' => 'رقمُ الملاحظة', 'response_text' => 'نصُّ الرد'),
            'run' => function ($conn, $co, $uid, $in) {
                require_once __DIR__ . '/../app/Services/Audit/InternalAuditService.php';
                return \App\Services\Audit\InternalAuditService::submitResponse($conn, array(
                    'company_id' => $co, 'finding_no' => (string) ($in['finding_no'] ?? ''), 'response_text' => (string) ($in['response_text'] ?? ''),
                    'actor' => $uid));
            }),
    ),

);
require __DIR__ . '/../includes/u13_screen_kit.php';
