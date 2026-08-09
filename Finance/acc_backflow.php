<?php
/**
 * Finance/acc_backflow.php — المرتجَع المالي للإدارات
 * ────────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (u13:generated) — لا يُحرَّر يدويًّا.
 *   عقدُه في `tools/u13_screens_manifest.php` وتصييرُه في
 *   `includes/u13_screen_kit.php`. أعِد التوليدَ بعد تغييرِ البيان:
 *     php tools/u13_screens_build.php --apply
 *
 * الأساس: derived · المصدر: FIN-OBL-01 §4-2 · المتطلب: OBL-0285
 * الحكم: BR-01: فالانتظارُ الصامتُ أسوأُ من الرفض · BR-03: والسببُ برمزٍ محكومٍ لا بنصٍّ حر
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فالتصنيفُ شرطُ الظهورِ لا فحصٌ بعده.
 *
 * ◆ العُدّةُ تُضمَّن في **النطاقِ العامّ** لا داخلَ دالة: `config.php` داخلَ
 *   دالةٍ يجعل $conn محليًّا فتسقط الشاشة، والقشرةُ تعتمد متغيّراتٍ عامة.
 */
$U13 = array(
    'file'       => 'Finance/acc_backflow.php',
    'screen'     => 'acc_backflow',
    'table'      => 'fin_backflow_log',
    'title'      => 'المرتجَع المالي للإدارات',
    'icon'       => 'fa fa-reply-all',
    'nature'     => 'register',
    'doc'        => 'FIN-OBL-01 §4-2 · OBL-0285',
    'intro'      => 'ولكلِّ ما يُرسَل إلى الماليةِ مرتجَعٌ مقابلٌ إلى مصدرِه',
    'rule'       => 'BR-01: فالانتظارُ الصامتُ أسوأُ من الرفض · BR-03: والسببُ برمزٍ محكومٍ لا بنصٍّ حر',
    'empty_hint' => 'لا مرتجَعاتٍ مُطلَقةً بعدُ',
    'order'       => 'fired_at DESC',

    'actions'    => array(
        'resolve' => array(
            'code'  => 'fin.route.backflow.resolve',
            'label' => 'إغلاقُ مرتجَعٍ عولِج',
            'rule'  => 'BR-06: صفرُ إشعارٍ محذوف — والإغلاقُ بسببٍ مسجَّلٍ وفاعلٍ معروف',
            'fields' => array('backflow_id' => 'رقمُ المرتجَع', 'close_reason' => 'سببُ الإغلاق'),
            'run' => function ($conn, $co, $uid, $in) {
                require_once __DIR__ . '/../app/Services/Finance/RoutingEngine.php';
                return \App\Services\Finance\RoutingEngine::resolveBackflow($conn, array(
                    'company_id' => $co, 'backflow_id' => (int) ($in['backflow_id'] ?? 0),
                    'closed_by' => $uid, 'close_reason' => (string) ($in['close_reason'] ?? '')));
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
