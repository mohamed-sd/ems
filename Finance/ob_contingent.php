<?php
/**
 * Finance/ob_contingent.php — الالتزامات المحتملة والإفصاح
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: registered · المصدر: FIN-OBL-01 §4-23 · المتطلب: OBL-0048
 * الحكم: OR-11: الالتزامُ المحتملُ يُفصح ولا يُقيَّد · ولا يُقيَّد مخصَّصٌ إلا بقرارٍ ماليٍّ مسبَّبٍ ومعتمد
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Finance/ob_contingent.php',
    'screen'     => 'ob_contingent',
    'table'      => 'fin_obl_avoidance',
    'title'      => 'الالتزامات المحتملة والإفصاح',
    'icon'       => 'fa fa-file-shield',
    'nature'     => 'document',
    'doc'        => 'FIN-OBL-01 §4-23 · OBL-0048',
    'intro'      => 'ما يُفصح عنه في الإيضاحاتِ ولا يُقيَّد في الميزانية',
    'rule'       => 'OR-11: الالتزامُ المحتملُ يُفصح ولا يُقيَّد · ولا يُقيَّد مخصَّصٌ إلا بقرارٍ ماليٍّ مسبَّبٍ ومعتمد',
    'empty_hint' => 'لا التزاماتٍ محتملةً تستوجب إفصاحًا',
    'where'       => 'verdict IN (\'disclose_only\',\'disclose_with_penalty\',\'recognition_candidate\',\'onerous\')',
);
require __DIR__ . '/../includes/u13_screen_kit.php';
