<?php
/**
 * tests/ticket_journey_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 * حارس شريط رحلة البلاغ (الدستور §5/§9 · UX-01 §6.3 · UX-07 §2).
 *
 * يحرس خريطةَ مراحل النظام التسع → المراحل الخمس المعروضة:
 *   ① سُجّل ② وُجّه ③ قيد التنفيذ ④ أُنجز ⑤ أُغلق
 *   و`waiting`/`follow_up` **وقفتان داخل ③** لا مرحلتان · و`cancelled` توقف.
 * ويحرس: آخرَ دخولٍ للمرحلة لا أوّلَه (البلاغ يُعاد فتحُه)، والساعتَين
 * (الاستجابة قبل البدء والإنجاز بعده)، وسببَ الوقفة من سجل الحركات.
 *
 * بلا قاعدة بيانات: تُعرَّف المساعداتُ البديلةُ **قبل** تضمين tkt_helpers.php،
 * فيتخطاها `function_exists` الحارسُ في الملف الأصلي ويبقى `tkt_journey` وحده
 * هو المُختبَر — فالاختبار حتميٌّ ولا يكتب صفًّا.
 *
 * التشغيل: php tests/ticket_journey_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

// ── بدائلُ المساعدات (تُعرَّف أولًا فيتخطاها الحارس في الملف الأصلي) ──────
function tkt_roles_map() {
    return array(13 => 'ادارة الصيانة', 24 => 'إدارة البلاغات', 3 => 'ادارة الاسطول');
}
function tkt_gate($s = false) { return new TktGateStub(); }
class TktGateStub {
    public function selectOne($table, $opts) {
        if ($table === 'users' && intval($opts['where']['id']) === 99) { return array('username' => 'أحمد الفنّي'); }
        return null;
    }
}
function tkt_stages() {
    return array('new' => 'جديدة', 'classified' => 'مصنّفة', 'routed' => 'محالة',
        'in_progress' => 'قيد التنفيذ', 'waiting' => 'بانتظار جهة أخرى',
        'follow_up' => 'قيد المتابعة', 'done' => 'منجزة', 'closed' => 'مغلقة', 'cancelled' => 'ملغاة');
}
function tkt_label($map, $key) { return isset($map[$key]) ? $map[$key] : $key; }
function tkt_is_overdue(array $t) {
    if (empty($t['resolution_due_at'])) { return false; }
    if (in_array($t['stage'], array('done', 'closed', 'cancelled'), true)) { return false; }
    return strtotime($t['resolution_due_at']) < time();
}

require_once dirname(__DIR__) . '/includes/journey_bar.php';
require_once dirname(__DIR__) . '/Tickets/tkt_helpers.php';

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

function mkt($stage, $extra = array()) {
    return array_merge(array(
        'id' => 1, 'ticket_no' => '26-07-9001', 'stage' => $stage,
        'owner_role_id' => 13, 'assigned_user_id' => null,
        'first_action_at' => null, 'response_due_at' => null, 'resolution_due_at' => null,
        'created_at' => date('Y-m-d H:i:s', time() - 86400 * 4),
    ), $extra);
}
function ev($type, $new, $when, $body = '', $old = '') {
    return array('event_type' => $type, 'old_value' => $old, 'new_value' => $new,
                 'body' => $body, 'created_at' => $when);
}
function cur(array $j) {
    foreach ($j['stages'] as $i => $s) { if ($s['status'] === 'current') { return $i + 1; } }
    return 0;
}
function labels(array $j) { return array_map(function ($s) { return $s['label']; }, $j['stages']); }

$T = function ($h) { return date('Y-m-d H:i:s', time() - $h * 3600); };

// ═══ ① الخريطة: تسعُ حالاتٍ → خمسُ مراحل ═════════════════════════════════
head('① خريطة الحالات التسع');

$evRouted = array(ev('system', 'routed', $T(72)));
check(count(labels(tkt_journey(mkt('routed'), $evRouted))) === 5,
    'خمسُ مراحلَ ثابتةُ البنية');
check(labels(tkt_journey(mkt('routed'), $evRouted)) === array('سُجّل', 'وُجّه', 'قيد التنفيذ', 'أُنجز', 'أُغلق'),
    'تسمياتُ المراحل الخمس بلغة المهمة');

check(cur(tkt_journey(mkt('new'), array())) === 1,             'new → ① سُجّل');
check(cur(tkt_journey(mkt('classified'), array())) === 1,      'classified → ① سُجّل (لا مرحلةَ منفصلة)');
check(cur(tkt_journey(mkt('routed'), $evRouted)) === 2,        'routed → ② وُجّه');
check(cur(tkt_journey(mkt('in_progress'), $evRouted)) === 3,   'in_progress → ③ قيد التنفيذ');
check(cur(tkt_journey(mkt('done'), $evRouted)) === 4,          'done → ④ أُنجز');

$jc = tkt_journey(mkt('closed'), array_merge($evRouted, array(ev('status_change', 'closed', $T(2)))));
check(cur($jc) === 0 && $jc['stages'][4]['status'] === 'done', 'closed → كلُّها منجَزةٌ ولا مرحلةَ حالية');
check(isset($jc['banner']) && $jc['banner']['kind'] === 'done' && !isset($jc['next']),
    'closed → لافتةُ اكتمالٍ بلا خطوةٍ تالية');

// ═══ ② الوقفتان داخل ③ لا مرحلتان ════════════════════════════════════════
head('② waiting و follow_up وقفتان لا مرحلتان');

$evWait = array(ev('system', 'routed', $T(72)), ev('status_change', 'in_progress', $T(48)),
                ev('status_change', 'waiting', $T(24), 'بانتظار وصول قطعة الغيار من المشتريات'));
$jw = tkt_journey(mkt('waiting'), $evWait);
check(cur($jw) === 3, 'waiting → المرحلةُ الحالية تبقى ③ (لا تقدّمَ ولا تراجع)');
check(isset($jw['stages'][2]['note']) && $jw['stages'][2]['note'] === 'بانتظار جهة أخرى',
    'waiting → الوقفةُ تظهر ملاحظةً على المرحلة');
check(isset($jw['banner']) && strpos($jw['banner']['text'], 'قطعة الغيار') !== false,
    'waiting → سببُ الوقفة الفعليُّ من السجل بارزٌ في اللافتة');

$evFollow = array_merge($evWait, array(ev('status_change', 'follow_up', $T(6), 'وصلت القطعة')));
$jf = tkt_journey(mkt('follow_up'), $evFollow);
check(cur($jf) === 3, 'follow_up → المرحلةُ الحالية تبقى ③');
check(isset($jf['banner']) && strpos($jf['banner']['title'], 'رُفع المعوّق') !== false,
    'follow_up → لافتةٌ تقول إن المعوّق رُفع');
check(strpos($jf['banner']['text'], 'وصلت القطعة') !== false,
    'follow_up → يأخذ سببَ **آخر** وقفةٍ لا أوّلِها');

// ═══ ③ آخرُ دخولٍ للمرحلة لا أوّلُه (البلاغُ يُعاد فتحُه) ═══════════════════
head('③ إعادةُ الفتح: آخرُ دخولٍ للمرحلة');

$evReopen = array(
    ev('system', 'routed', $T(200)),
    ev('status_change', 'in_progress', $T(190)),
    ev('status_change', 'done', $T(180)),
    ev('status_change', 'closed', $T(170)),
    ev('status_change', 'follow_up', $T(20)),        // أُعيد فتحُه
    ev('status_change', 'in_progress', $T(10)),      // دخلها ثانيةً
);
$jr = tkt_journey(mkt('in_progress'), $evReopen);
check(cur($jr) === 3, 'بعد إعادة الفتح والاستئناف → ③ هي الحالية');
check(abs(strtotime($jr['stages'][2]['at']) - strtotime($T(10))) < 5,
    'زمنُ ③ هو آخرُ دخولٍ (قبل 10 ساعات) لا الأولَ (قبل 190)');

// ═══ ④ الإلغاء: توقفٌ وإطفاءُ ما بعده ════════════════════════════════════
head('④ الإلغاء');

$jx = tkt_journey(mkt('cancelled'), $evRouted);
check(!isset($jx['next']), 'cancelled → لا خطوةَ تاليةً تُعرض');
check($jx['stages'][0]['status'] === 'done' && $jx['stages'][4]['status'] === 'off',
    'cancelled → المبلوغُ منجَزٌ وما بعده مُطفأ');
check(isset($jx['banner']) && $jx['banner']['kind'] === 'stop', 'cancelled → لافتةُ توقف');

// ═══ ⑤ الساعتان ═══════════════════════════════════════════════════════════
head('⑤ ساعتا الاستجابة والإنجاز');

$jResp = tkt_journey(mkt('routed', array('response_due_at' => $T(3))), $evRouted);
check(!empty($jResp['next']['overdue']), 'مهلةُ الاستجابة انكسرت ولم يبدأ أحد → متأخر');
check(isset($jResp['banner']) && strpos($jResp['banner']['title'], 'الاستجابة') !== false,
    'وتُسمّى المهلةُ المكسورة باسمها (الاستجابة لا الإنجاز)');

$jStarted = tkt_journey(mkt('in_progress', array(
    'response_due_at' => $T(3), 'first_action_at' => $T(2))), $evRouted);
check(empty($jStarted['next']['overdue']),
    'بدأ العملُ فعلًا → لا تكسر ساعةُ الاستجابة بعد ذلك');

$jRes = tkt_journey(mkt('in_progress', array(
    'first_action_at' => $T(20), 'resolution_due_at' => $T(2))), $evRouted);
check(!empty($jRes['next']['overdue']) && !empty($jRes['stages'][2]['overdue']),
    'مهلةُ الإنجاز انكسرت → المرحلةُ الحالية متأخرة');

$jDone = tkt_journey(mkt('done', array('resolution_due_at' => $T(99))), $evRouted);
check(empty($jDone['stages'][3]['overdue']), 'المنجَزُ لا يُوسم متأخرًا مهما مضت مهلتُه');

// ═══ ⑥ الأصحاب والتصعيد ═══════════════════════════════════════════════════
head('⑥ أصحابُ المراحل والتصعيد');

$jo = tkt_journey(mkt('routed'), $evRouted);
check($jo['stages'][1]['owner'] === 'ادارة الصيانة',
    'صاحبُ ② من الإدارة المالكة (owner_role_id عبر سجل الأدوار)');
$ja = tkt_journey(mkt('in_progress', array('assigned_user_id' => 99)), $evRouted);
check($ja['stages'][2]['owner'] === 'أحمد الفنّي',
    'حين يُسنَد البلاغُ لشخصٍ يظهر اسمُه بدل اسم الإدارة');
$jn = tkt_journey(mkt('in_progress'), $evRouted);
check($jn['stages'][2]['owner'] === 'ادارة الصيانة',
    'وبلا إسنادٍ تظهر الإدارةُ — لا يُخترع اسمُ شخص (قاعدة عدم التلفيق)');

$jesc1 = tkt_journey(mkt('in_progress'), array_merge($evRouted, array(ev('escalation', '', $T(9)))));
check(strpos($jesc1['next']['label'], 'صُعّد مرة') !== false
   && strpos($jesc1['next']['label'], '1 مرة') === false,
    'تصعيدٌ واحد → «صُعّد مرة» بلا رقمٍ (لا «صُعّد 1 مرة»)');
$jesc = tkt_journey(mkt('in_progress'), array_merge($evRouted,
    array(ev('escalation', '', $T(9)), ev('escalation', '', $T(5)))));
check(strpos($jesc['next']['label'], 'صُعّد مرتين') !== false,
    'تصعيدان → مثنّى «مرتين»');
check(!isset(tkt_journey(mkt('in_progress'), $evRouted)['next']['label'])
      || strpos(tkt_journey(mkt('in_progress'), $evRouted)['next']['label'], 'صُعّد') === false,
    'بلا تصعيدٍ لا تُذكر كلمةُ تصعيدٍ أصلًا');

$jr0 = tkt_journey(mkt('routed', array('owner_role_id' => 999)), $evRouted);
check(is_array($jr0) && count($jr0['stages']) === 5,
    'دورٌ غيرُ معروفٍ لا يكسر الشريط — يُترك المالكُ فارغًا');

fwrite(STDOUT, "\n" . str_repeat('═', 46) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
