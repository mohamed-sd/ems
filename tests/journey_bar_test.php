<?php
/**
 * tests/journey_bar_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 * حارس شريط الرحلة (الدستور §5 «شريط الرحلة أعلى شاشة كل معاملة» · §9 «لا
 * شاشةَ تنتهي بطريقٍ مسدود» · UX-01 §6.1 · UX-02 §5-① و§13-①).
 *
 * يحرس ثلاثة عقود:
 *   ① المصيِّر الموحّد `ems_journey_bar` — بنيةً وتهريبًا وحالاتٍ فارغة
 *   ② التصريف العربي في `ems_journey_ago` (مفردٌ ومثنًّى وجمع — لا "2 days")
 *   ③ خريطةُ حالات الطلب الست عشرة → المراحل السبع في `finreq_journey`،
 *      بما فيها انقسامُ pending_approval بوجود الحدث، والإعادةُ بسببها،
 *      والتوقفُ بإطفاء ما بعده، والتأخرُ من sla_due_at.
 *
 * بلا قاعدة بيانات: بوابةٌ بديلةٌ (stub) تعطي أسماء الأدوار — فالاختبار حتميٌّ
 * لا يتعلق ببياناتٍ حيّةٍ قد تتغير، ولا يكتب صفًّا واحدًا.
 *
 * التشغيل: php tests/journey_bar_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/includes/journey_bar.php';

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

/** يلتقط مخرَج المصيِّر نصًّا. */
function render(array $j) { ob_start(); ems_journey_bar($j); return ob_get_clean(); }

// ═══ ① المصيِّر الموحّد ═══════════════════════════════════════════════════
head('① المصيِّر الموحّد');

check(ems_journey_bar(array()) === false && render(array()) === '',
    'مراحلُ فارغةٌ لا تطبع إطارًا فارغًا (يعيد false)');

$h = render(array('stages' => array(
    array('label' => 'الإنشاء',  'status' => 'done',    'at' => '2026-07-01 08:00:00', 'owner' => 'مقدّم الطلب'),
    array('label' => 'الاعتماد', 'status' => 'current', 'overdue' => true, 'owner' => 'مدير الإدارة'),
    array('label' => 'الإغلاق',  'status' => 'todo'),
)));
check(substr_count($h, 'ems-jstep') === 3, 'يطبع شريحةً لكل مرحلة');
check(strpos($h, 'is-done') !== false && strpos($h, 'is-current is-overdue') !== false
      && strpos($h, 'is-todo') !== false, 'أصنافُ الحالات الثلاث مطبوعة (والمتأخرة تحمل is-overdue)');
check(strpos($h, '>✓<') !== false, 'المنجَزة تحمل علامة صحّ لا رقمًا');
check(strpos($h, 'منذ') !== false, 'المنجَزة تعرض زمنَها بالعربية');

$h2 = render(array(
    'stages' => array(array('label' => 'أ', 'status' => 'current')),
    'next'   => array('label' => 'اعتماد الإدارة', 'owner' => 'مدير الصيانة', 'since' => date('Y-m-d H:i:s', time() - 7200), 'overdue' => true),
));
check(strpos($h2, 'ems-journey-next') !== false && strpos($h2, 'مدير الصيانة') !== false,
    'سطرُ الخطوة التالية يحمل اسمَ صاحبها (الدستور §5)');
check(strpos($h2, 'تجاوزت المهلة') !== false && strpos($h2, 'is-overdue') !== false,
    'تجاوزُ المهلة يظهر نصًّا ولونًا');

foreach (array('return' => 'أُعيد', 'stop' => 'توقفت', 'done' => 'اكتملت') as $kind => $word) {
    $hb = render(array('stages' => array(array('label' => 'أ', 'status' => 'done')),
        'banner' => array('kind' => $kind, 'title' => $word . ' الرحلة', 'text' => 'نصٌّ')));
    check(strpos($hb, 'ems-journey-banner is-' . $kind) !== false, "لافتةُ «{$kind}» تُطبع بصنفها");
}

$hz = render(array('stages' => array(array('label' => '<script>x</script>', 'status' => 'todo')),
    'banner' => array('kind' => 'return', 'title' => 'ت', 'text' => '"><b>')));
check(strpos($hz, '<script>') === false && strpos($hz, '&lt;script&gt;') !== false,
    'كلُّ نصٍّ مهرَّبٌ — لا حقنَ HTML من السبب أو التسمية');

$ha = render(array('stages' => array(array('label' => 'أ', 'status' => 'current')),
    'banner' => array('kind' => 'return', 'title' => 'ت', 'action' => array('href' => 'x.php', 'label' => 'أكمِل'))));
check(strpos($ha, 'ems-jb-action') !== false && strpos($ha, 'أكمِل') !== false,
    'زرُّ القفز إلى موضع الفعل يُطبع حين يُمرَّر (§9: لا طريقَ مسدود)');

// ═══ ② التصريف العربي ═════════════════════════════════════════════════════
head('② التصريف العربي للزمن');

$now = time();
check(ems_journey_ago(date('Y-m-d H:i:s', $now - 30))     === 'منذ أقل من دقيقة', 'أقل من دقيقة');
check(ems_journey_ago(date('Y-m-d H:i:s', $now - 3600))   === 'منذ ساعة',        'مفرد: ساعة');
check(ems_journey_ago(date('Y-m-d H:i:s', $now - 7200))   === 'منذ ساعتين',      'مثنّى: ساعتين');
check(ems_journey_ago(date('Y-m-d H:i:s', $now - 5*3600)) === 'منذ 5 ساعات',     'جمع: 5 ساعات');
check(ems_journey_ago(date('Y-m-d H:i:s', $now - 2*86400))=== 'منذ يومين',       'مثنّى: يومين');
check(ems_journey_ago(date('Y-m-d H:i:s', $now + 7200))   === 'بعد ساعتين',      'المستقبل: «بعد» لا «منذ»');
// ما فوق العشرة تمييزُه مفردٌ منصوب — «13 يومًا» لا «13 يوم» (خطأُ التوليد الشائع)
check(ems_journey_ago(date('Y-m-d H:i:s', $now - 13*86400)) === 'منذ 13 يومًا',   'تمييزٌ منصوب: 13 يومًا');
check(ems_journey_ago(date('Y-m-d H:i:s', $now - 13*3600))  === 'منذ 13 ساعةً',   'تمييزٌ منصوب: 13 ساعةً');
check(ems_journey_plural(1, 'مرة', 'مرتين', 'مرات', 'مرةً') === 'مرة'
   && ems_journey_plural(2, 'مرة', 'مرتين', 'مرات', 'مرةً') === 'مرتين'
   && ems_journey_plural(4, 'مرة', 'مرتين', 'مرات', 'مرةً') === '4 مرات'
   && ems_journey_plural(12, 'مرة', 'مرتين', 'مرات', 'مرةً') === '12 مرةً',
    'الصيغُ الأربع في ems_journey_plural (مفرد · مثنّى · جمعُ قلة · تمييزٌ منصوب)');
check(ems_journey_ago('') === '' && ems_journey_ago(null) === '' && ems_journey_ago('نصٌّ') === '',
    'قيمةٌ فارغةٌ أو غيرُ تاريخٍ تعيد فراغًا — لا تحطّم الشريط');

// ═══ ③ خريطة حالات الطلب المالي ═══════════════════════════════════════════
head('③ خريطة حالات الطلب → المراحل السبع');

require_once dirname(__DIR__) . '/FinRequests/_finreq_helpers.php';

/** بوابةٌ بديلةٌ تعطي أسماء الأدوار فقط — لا قاعدةَ بيانات في هذا الاختبار. */
class JourneyGateStub {
    public $names = array(13 => 'ادارة الصيانة', 17 => 'إدارة المالية',
                          18 => 'محاسب الإدارة المالية', 21 => 'أمين الخزينة');
    public function selectOne($table, $opts) {
        if ($table !== 'roles') { return null; }
        $id = intval($opts['where']['id']);
        return isset($this->names[$id]) ? array('name' => $this->names[$id]) : null;
    }
}
$gate = new JourneyGateStub();
$routing = array('module_label' => 'الصيانة', 'manager_role_id' => 13, 'reviewer_role_id' => 13);

/** طلبٌ اصطناعيٌّ بحالته. */
function mkreq($state, $extra = array()) {
    return array_merge(array(
        'id' => 1, 'request_no' => 'FR-TEST-1', 'state' => $state, 'request_type' => 'disbursement',
        'event_id' => null, 'sla_due_at' => null, 'rejection_class' => null,
        'created_at' => date('Y-m-d H:i:s', time() - 86400 * 3),
    ), $extra);
}
/** فهرسُ المرحلة الحالية (1-based) أو 0 إن لم توجد. */
function cur(array $j) {
    foreach ($j['stages'] as $i => $s) { if ($s['status'] === 'current') { return $i + 1; } }
    return 0;
}
function labels(array $j) { return array_map(function ($s) { return $s['label']; }, $j['stages']); }

$tl_submitted = array(array('event_type' => 'submit', 'new_value' => 'under_review',
    'body' => '', 'created_at' => date('Y-m-d H:i:s', time() - 86400 * 2)));

check(count(labels(finreq_journey($gate, mkreq('draft'), array(), $routing))) === 7,
    'سبعُ مراحلَ ثابتةُ البنية لكل الحالات');

check(cur(finreq_journey($gate, mkreq('draft'), array(), $routing)) === 1,
    'draft → المرحلة ① الإنشاء والإرسال');
check(cur(finreq_journey($gate, mkreq('under_review'), $tl_submitted, $routing)) === 2,
    'under_review → المرحلة ② اعتماد الإدارة');
check(cur(finreq_journey($gate, mkreq('pending_approval'), $tl_submitted, $routing)) === 3,
    'pending_approval بلا حدثٍ → ③ مكتب المحاسب');
check(cur(finreq_journey($gate, mkreq('pending_approval', array('event_id' => 562)), $tl_submitted, $routing)) === 4,
    'pending_approval بحدثٍ → ④ الاعتماد المالي (تمييزُ finreq_badge_counts نفسُه)');
check(cur(finreq_journey($gate, mkreq('approved'), $tl_submitted, $routing)) === 5,
    'approved → ⑤ القيد');
check(cur(finreq_journey($gate, mkreq('posted'), $tl_submitted, $routing)) === 6,
    'posted → ⑥ الصرف');
check(cur(finreq_journey($gate, mkreq('paid'), $tl_submitted, $routing)) === 7,
    'paid → ⑦ الإغلاق');

$jc = finreq_journey($gate, mkreq('closed'), $tl_submitted, $routing);
check(cur($jc) === 0 && $jc['stages'][6]['status'] === 'done',
    'closed → كلُّ المراحل منجَزةٌ ولا مرحلةَ حالية');
check(isset($jc['banner']) && $jc['banner']['kind'] === 'done' && !isset($jc['next']),
    'closed → لافتةُ اكتمالٍ بلا خطوةٍ تالية');

// الإعادة: السببُ بارزٌ وبالرقم نفسه (الدستور §4.3)
$tl_ret = array_merge($tl_submitted, array(array('event_type' => 'return',
    'new_value' => 'returned', 'body' => 'الفاتورة المرفقة غير مقروءة',
    'created_at' => date('Y-m-d H:i:s', time() - 3600))));
$jr = finreq_journey($gate, mkreq('returned'), $tl_ret, $routing);
check(cur($jr) === 1, 'returned → تعود المرحلةُ الحالية إلى ① صاحبِ الطلب');
check(isset($jr['banner']) && $jr['banner']['kind'] === 'return'
      && strpos($jr['banner']['text'], 'غير مقروءة') !== false,
    'returned → السببُ الفعليُّ من السجل بارزٌ في اللافتة');
check(strpos($jr['banner']['meta'], 'FR-TEST-1') !== false,
    'returned → «بالرقم نفسه» ظاهرٌ في اللافتة');

// التوقف: ما بعد ما بُلغ يُطفأ ولا خطوةَ تالية
$jx = finreq_journey($gate, mkreq('rejected'), $tl_submitted, $routing);
check(!isset($jx['next']), 'rejected → لا خطوةَ تاليةً تُعرض');
check($jx['stages'][0]['status'] === 'done' && $jx['stages'][6]['status'] === 'off',
    'rejected → المبلوغُ منجَزٌ وما بعده مُطفأ (is-off)');
check(isset($jx['banner']) && $jx['banner']['kind'] === 'stop', 'rejected → لافتةُ توقفٍ حمراء');

foreach (array('withdrawn', 'cancelled', 'expired', 'merged') as $st) {
    $js = finreq_journey($gate, mkreq($st), $tl_submitted, $routing);
    check(isset($js['banner']) && $js['banner']['kind'] === 'stop' && !isset($js['next']),
        "{$st} → توقفٌ بلا خطوةٍ تالية");
}

// التأخر من مهلة المرحلة المختومة (§8.1)
$jo = finreq_journey($gate, mkreq('under_review', array('sla_due_at' => date('Y-m-d H:i:s', time() - 7200))), $tl_submitted, $routing);
check(!empty($jo['next']['overdue']) && !empty($jo['stages'][1]['overdue']),
    'sla_due_at ماضٍ → المرحلةُ الحالية والخطوةُ التالية متأخرتان');
$jn = finreq_journey($gate, mkreq('under_review', array('sla_due_at' => date('Y-m-d H:i:s', time() + 7200))), $tl_submitted, $routing);
check(empty($jn['next']['overdue']), 'sla_due_at مستقبليٌّ → لا تأخر');
$jz = finreq_journey($gate, mkreq('closed', array('sla_due_at' => date('Y-m-d H:i:s', time() - 99999))), $tl_submitted, $routing);
check(empty($jz['stages'][6]['overdue']), 'المقفلُ لا يُوسم متأخرًا مهما مضت مهلتُه');

// التسميةُ تتبع نوع الطلب لا نصًّا ثابتًا
$jcol = finreq_journey($gate, mkreq('posted', array('request_type' => 'collection')), $tl_submitted, $routing);
check(labels($jcol)[5] === 'التحصيل', 'نوعُ التحصيل يسمّي المرحلة ⑥ «التحصيل» لا «الصرف»');
check(labels(finreq_journey($gate, mkreq('posted'), $tl_submitted, $routing))[5] === 'الصرف',
    'وسائرُ الأنواع «الصرف»');

// الأصحاب من التوجيه وسجل الأدوار — لا نصٌّ مكتوب
$jow = finreq_journey($gate, mkreq('under_review'), $tl_submitted, $routing);
check($jow['stages'][1]['owner'] === 'ادارة الصيانة',
    'صاحبُ ② من manager_role_id في صفّ التوجيه (سجلُّ الأدوار)');
check($jow['stages'][2]['owner'] === 'محاسب الإدارة المالية' && $jow['stages'][5]['owner'] === 'أمين الخزينة',
    'أصحابُ ③ و⑥ من سجل الأدوار (18 و21)');
$jnr = finreq_journey($gate, mkreq('under_review'), $tl_submitted, null);
check(is_array($jnr) && count($jnr['stages']) === 7,
    'بلا صفِّ توجيهٍ لا ينكسر الشريط (المالكُ يُترك فارغًا — قاعدةُ عدم التلفيق)');

// أزمنةُ المراحل من السجل لا من تخمين
check(isset($jow['stages'][0]['at']) && !isset($jow['stages'][3]['at']),
    'زمنُ الإنجاز يُعرض للمنجَز وحده — وما لا سجلَّ له بلا زمن');

fwrite(STDOUT, "\n" . str_repeat('═', 46) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
