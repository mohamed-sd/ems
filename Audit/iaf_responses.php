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
    'nature'     => 'read',
    'doc'        => 'IAF-01 §4-3 · IAF-0027',
    'intro'      => 'ردُّ الإدارةِ إلزاميٌّ بمهلة — والسكوتُ يُصعَّد للجهةِ المشرفة',
    'rule'       => 'BF-15: والردُّ إلزاميٌّ بمهلةٍ · والسكوتُ يُصعَّد',
    'empty_hint' => 'لا ردودَ واردةً بعدُ',
    'where'       => 'state IN (\'responded\',\'in_remediation\',\'evidence_submitted\')',
    'order'       => 'responded_at DESC',
);
require __DIR__ . '/../includes/u13_screen_kit.php';
