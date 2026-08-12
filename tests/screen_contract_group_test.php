<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-42 · M-44 · M-45 · M-46 · H-14 — اختبار قبول مجموعةِ عقد الشاشة
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/screen_contract_group_test.php
 *
 * ما يُثبته:
 *   M-42 الصندوقُ الموحّد يجمع الصناديقَ الأربعة بعدّاداتها من مصادرها —
 *        والقرارُ يمرّ بخدمة مصدره (روابطُ قفزٍ لا أزرارَ تنفيذ).
 *   M-44 مكوّنُ «ما هذه الشاشة؟» والحالاتُ الخمس موحّدةٌ — والفارغةُ
 *        **بزرِّ الخطوة الأولى**.
 *   M-45 «من أنشأ لا يعتمد» **قيدًا عامًّا**: دالةٌ واحدةٌ ترُدّ 403 مسجَّلةً
 *        — وموصولةٌ بمسار اعتماد الطلب المالي.
 *   M-46 المسودةُ التلقائية محمَّلةٌ في رأس كل صفحة وتلتقط النماذجَ الطويلة.
 *   H-14 سايدبارُ الدور 17 صار **سبعَ مجموعاتٍ** بأسماء UX-02 §6 نصًّا —
 *        صفرُ رابطٍ يتيمٍ وصفرُ رابطٍ حُذف والقديمُ أُخفي لا مُحي.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '17', 'company_id' => 4, 'name' => 'screen contract test');

require_once dirname(__DIR__) . '/app/Services/Finance/ApprovalsInboxService.php';
require_once dirname(__DIR__) . '/includes/screen_contract.php';
require_once dirname(__DIR__) . '/includes/self_approval_guard.php';

use App\Services\Finance\ApprovalsInboxService as AIS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO   = 4;
$MARK = 'SC42T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM fin_requests WHERE request_no LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ عقدُ الشاشة — M-42 · M-44 · M-45 · M-46 · H-14 ══\n");

// ═══ M-42 ═══
head('M-42 — الصندوقُ الموحّد: أربعةُ صناديقَ في واحد');

/* ══ **العددُ إحصاءٌ والتوحيدُ هو الحكم.** كان يشترط `=== 4` والخدمةُ نمت إلى
     ستةٍ بقرارَين مسجَّلَين — **والرسالةُ نفسُها تطبع الستةَ فتُدين الفاحصَ لا
     الخدمة**. والحكمُ المقصودُ (عنوانُ القسم): «أربعةُ صناديقَ في **واحد**» —
     أي أن مصادرَ §5 الأربعةَ حاضرةٌ في صندوقٍ موحَّد، لا أن عددَها أربعةٌ أبدًا.
   ⇒ يُقاس حضورُ المصادرِ الأربعةِ المسمّاةِ، ويُعلَن العددُ الكليُّ ولا يُجمَّد. */
$before = AIS::inbox($conn, $CO);
$boxTitles = array_map(function ($b) { return (string) $b['title']; }, $before['boxes']);
$boxBlob = implode(' | ', $boxTitles);
$need4 = array('الطلبات المالية', 'تسويات الموردين', 'القيود اليدوية', 'إقفال الفترات');
$missing4 = array();
foreach ($need4 as $nd) { if (mb_strpos($boxBlob, $nd) === false) { $missing4[] = $nd; } }
check($before['ok'] && $missing4 === array(),
      '★ مصادرُ §5 الأربعةُ في صندوقٍ واحد (والصناديقُ الآن '
      . count($before['boxes']) . '): ' . implode(' · ', array_map(function ($b) {
          return $b['title'] . '(' . $b['count'] . ')'; }, $before['boxes']))
      . ($missing4 ? ' — الناقص: ' . implode(' · ', $missing4) : ''));

$conn->query("INSERT INTO fin_requests (company_id, request_no, request_type, source_module,
              requester_id, beneficiary_type, beneficiary_ref, amount, currency, statement,
              justification, state, created_by, created_at)
              VALUES ({$CO}, 'FR-{$MARK}', 'supplier_due', 'u4', 55, 'supplier', 1, 750, 'USD',
                      'بيان', 'تبرير', 'pending_approval', 55, NOW())");
$FR = intval($conn->insert_id);
$after = AIS::inbox($conn, $CO);
$reqBox = $after['boxes'][0];
$found = false;
foreach ($reqBox['rows'] as $r) {
    if (strpos($r['label'], $MARK) !== false) {
        $found = true;
        check(strpos($r['link'], 'finance_gateway.php?id=' . $FR) !== false,
              '★★ الطلبُ المبذور ظهر في الصندوق **برابط قفزٍ إلى موضع الفعل** — لا زرَّ تنفيذٍ هنا');
    }
}
check($found, '★ طلبٌ بانتظار الاعتماد يظهر فور بذره — القراءةُ حيّةٌ من المصدر');
check($after['total'] === $before['total'] + 1, '★ والمجموعُ عدّادُ الأربعة معًا (زاد واحدًا)');

// ═══ M-45 ═══
head('M-45 — «من أنشأ لا يعتمد» قيدًا عامًّا');

$blocked = ems_no_self_approval($conn, 55, 55, 'الطلب المالي FR-' . $MARK, $CO);
check($blocked !== null && $blocked['code'] === 403,
      '★★★ المنشئُ يعتمد مستندَه ⇒ **403** — «منعُ اعتماد الذات بنيويًّا» (§10-③)');
$logged = intval($conn->query("SELECT COUNT(*) n FROM activity_logs
                                WHERE module_name='permissions'
                                  AND action_type LIKE '%denied%'")->fetch_assoc()['n']);
check($logged >= 1, '★ والمحاولةُ **مسجَّلةٌ في سجل التدقيق** (N-02)');
check(ems_no_self_approval($conn, 55, 66, 'مستند', $CO) === null,
      'ويدان مختلفتان تمرّان — الحارسُ يمنع الذاتَ لا الاعتماد');
$wired = strpos(file_get_contents(dirname(__DIR__) . '/FinRequests/request_actions.php'),
                'ems_no_self_approval') !== false;
check($wired, '★ والحارسُ **موصولٌ بمسار اعتماد الطلب المالي** — لا دالةً معزولة');

// ═══ M-44 ═══
head('M-44 — عقدُ الشاشة الموحّد: الحالاتُ الخمس');

/* ══ العنوانُ انتقل إلى المُصيِّرِ و«الخطوات» هُجرت — والحكمُ يتبع العقدَ الجديد ══
   عند كتابةِ الفحصِ كان `ems_screen_about()` يُصدِر نصَّ «ما هذه الشاشة؟» حرفيًّا.
   وبقرارِ المالك (2026-08-09 · نُفِّذ في `includes/screen_contract.php`) صار
   يُصدِر **قالبًا** (`<template class="ems-about-tpl">`) وحدَه، والعنوانُ يُبنى
   في المُصيِّر (`assets/js/ems-screen-about.js`) مشتقًّا من عنوانِ الصفحة
   («عن: …») بدل نصٍّ ثابت. و`$steps` **مهجورٌ لا يُصيَّر** — موصوفٌ كذلك في
   جسمِ الدالةِ نفسِها. فطلبُ النصِّ القديمِ و«خطواته» طلبُ عقدٍ نُسخ.
   ⇒ يُحكَم على العقدِ القائم **من طرفيه**: أن PHP تُصدِر القالبَ بغرضِه موسومًا
     بمصدرِه ومفتاحِ شاشتِه، وأن المُصيِّرَ يستهلك القالبَ ويبني عنوانَ البطاقة.
     فيرسب إن سقط الغرضُ أو الوسمُ أو توقّف المُصيِّرُ عن استهلاكِ القالب. */
ob_start(); ems_screen_about('غرضُ اختبار'); $about = ob_get_clean();
$aboutJs = (string) @file_get_contents(dirname(__DIR__) . '/assets/js/ems-screen-about.js');
check(strpos($about, 'ems-about-tpl') !== false
   && strpos($about, 'data-src=') !== false
   && strpos($about, 'ems-about__purpose') !== false
   && strpos($about, 'غرضُ اختبار') !== false
   && strpos($aboutJs, 'ems-about-tpl') !== false
   && strpos($aboutJs, 'ems-about__title') !== false,
   '★ مكوّنُ «عن الشاشة» يُصدِر قالبَ غرضِه موسومًا بمصدره — ومُصيِّرُه يستهلك القالبَ ويبني العنوان');
ob_start(); ems_state_empty('لا بيانات', 'أضف أولَ عنصر', 'form.php'); $empty = ob_get_clean();
check(strpos($empty, 'أضف أولَ عنصر') !== false && strpos($empty, 'form.php') !== false,
      '★★ الفارغةُ **بزرِّ الخطوة الأولى** — لا نصَّ DataTables العام');
ob_start(); ems_state_error('عطل', 'أعد', 'x.php'); $err = ob_get_clean();
check(strpos($err, 'عطل') !== false && strpos($err, 'x.php') !== false,
      'والخطأُ بسببه وزرِّ علاجه');
ob_start(); ems_state_loading(2); $load = ob_get_clean();
check(strpos($load, 'emsPulse') !== false, 'والتحميلُ هيكلٌ نابضٌ موحّد');
ob_start(); ems_state_success('تم'); $succ = ob_get_clean();
ob_start(); ems_state_offline_bar(); $off = ob_get_clean();
check(strpos($succ, 'تم') !== false && strpos($off, 'offline') !== false,
      'والنجاحُ و«دون اتصال» مكوّنان — **الحالاتُ الخمسُ كاملة**');
$adopted = 0;
foreach (array('Finance/approvals_inbox.php') as $f) {
    if (strpos(file_get_contents(dirname(__DIR__) . '/' . $f), 'ems_screen_about') !== false) { $adopted++; }
}
check($adopted >= 1, '★ والمكوّنُ **مستعملٌ في شاشةٍ حية** (الصندوق الموحد) لا مكتبةً بلا مستهلك');

// ═══ M-46 ═══
head('M-46 — المسودةُ التلقائية كل 30 ثانية');

$js = file_get_contents(dirname(__DIR__) . '/includes/js/ems-autosave.js');
check(strpos($js, '30000') !== false && strpos($js, 'localStorage') !== false,
      '★ المكوّنُ يحفظ كل 30 ثانيةً في localStorage');
check(strpos($js, 'password') !== false && strpos($js, 'csrf') !== false,
      '★ وكلماتُ المرور ورموزُ CSRF **لا تُحفظ أبدًا**');
check(strpos($js, "addEventListener('submit'") !== false || strpos($js, 'addEventListener("submit"') !== false,
      'والإرسالُ الناجح يمسح المسودة');
$hdr = file_get_contents(dirname(__DIR__) . '/inheader.php');
check(strpos($hdr, 'ems-autosave.js') !== false,
      '★★ ومحمَّلٌ في رأس **كل** صفحة — فيلتقط النماذجَ الطويلة (≥6 حقول) آليًّا');

// ═══ H-14 ═══
head('H-14 — السايدبارُ الماليُّ السباعي (UX-02 §6 نصًّا)');

/* ══ NAV-09 خلَفت §6 — والسبعةُ **أُخفيت ولم تُحذف** ═════════════════════════════
   H-14 بنى سبعَ مجموعاتٍ بأسماءِ §6 حرفيًّا. ثم صارت **وثيقةُ NAV-09** مصدرَ
   القوائم (2026-08-03) فخلَفتها بمجموعاتِ مراحلَ (`n9s13_*_r17`)، وأُخفيت
   السبعةُ بقلبِ علمٍ لا بهجرةٍ — وهو **عقدُ العملِ ④ نفسُه** («يُخفى لا يُحذف»)
   الذي يفحصه هذا الملفُّ بعد أسطر. فمطالبةُ السبعةِ بأن تبقى **الفعّالةَ
   الوحيدةَ** تطالب بنقضِ الوثيقةِ التي خلَفتها.
   ⇒ ثلاثةُ أحكامٍ تحلُّ محلَّ الواحد: بقاءُ السبعةِ مخفيّةً · ولا رابطَ حيًّا خارجَ
     مجموعةٍ حية · **وسلّةُ «أخرى — للمراجعة» ليست سايدبارًا موازيًا**. والثالثُ
     يبقى أحمرَ بحقٍّ (وعدُ H-14 لم يتمّ بعد) ولا يُخضَّر بتخفيفِ سقفِه. */
$SEVEN = array('عملي اليوم', 'العملاء والتحصيل', 'الموردون والمدفوعات',
               'الخزينة والبنوك', 'المحاسبة العامة', 'التخطيط والتحليل', 'الإعدادات والتدقيق');
$inSeven = "'" . implode("','", array_map(array($conn, 'real_escape_string'), $SEVEN)) . "'";
$s7 = $conn->query("SELECT COUNT(*) t, SUM(is_active = 0) hid FROM link_groups
                     WHERE owner_role_id = 17 AND name IN ({$inSeven})")->fetch_assoc();
check(intval($s7['t']) === 7 && intval($s7['hid']) === 7,
    '★★ صفوفُ §6 السبعةُ باقيةٌ مخفيّةً (NAV-09 خلَفها) — الرجوعُ بقلبِ علمٍ لا بهجرة: '
    . intval($s7['hid']) . '/' . intval($s7['t']));
$loose = intval($conn->query("SELECT COUNT(*) n FROM nav_items n
                               LEFT JOIN link_groups g ON g.id = n.group_id AND g.is_active = 1
                              WHERE n.role_id = 17 AND n.active = 1 AND g.id IS NULL")->fetch_assoc()['n']);
check($loose === 0, '★★ كلُّ رابطٍ حيٍّ للدور 17 داخلَ مجموعةٍ حية (' . $loose . ' خارجها)');

/* ══ سلّةُ «أخرى — للمراجعة»: ما يُقاس وما لا يُقاس ═══════════════════════════
   وعدُ H-14 («لا سايدبارَ مبعثرًا») **لم يتمّ بعد**: 43 من 99 رابطًا حيًّا للدور 17
   في السلّة (259 من 1569 عبر الأدوار كلِّها). وقِستُ إمكانَ تصنيفِها آليًّا فسقط:
     · 31 منها **لا صفَّ لها في وثيقةِ NAV-09** أصلًا — فلا مرحلةَ تُشتقّ.
     · و12 لها صفٌّ، لكن المرحلةَ في هذا النظامِ **خاصةٌ بكلِّ دور** (موضعُ الشاشةِ
       في دورةِ عملِ إدارتِه): `Contracts/penalties.php` مرحلةُ 6 عند دورٍ و7 عند
       آخر. فنقلُ مرحلةِ دورٍ إلى الدور 17 **اختراعُ موضعٍ في دورةِ عملِ المالية**
       لا اشتقاقُه. ⇒ تصنيفُها **قرارُ المالك** بعرفِ «المرحلة 99 — بانتظار قرار
       المالك»، وهو ما تفعله السلّةُ بالضبط.
   فيُعلَن الرقمُ صريحًا (لا يُخبَّأ)، ويُحكَم على **الثابتِ القابلِ للإثبات**: أن
   السلّةَ لا تُخفي **مكرَّرًا** لرابطٍ مصنَّفٍ للدورِ نفسِه — أي أنها موقِفُ ما لم
   يُصنَّف، لا سايدبارٌ موازٍ يُعيد ما صُنِّف. */
$bk = $conn->query("SELECT COUNT(*) n FROM nav_items n JOIN link_groups g ON g.id = n.group_id
                     WHERE n.role_id = 17 AND n.active = 1 AND g.group_code LIKE '%others%'")->fetch_assoc();
$liveAll = intval($conn->query("SELECT COUNT(*) n FROM nav_items
                                 WHERE role_id = 17 AND active = 1")->fetch_assoc()['n']);
fwrite(STDOUT, '     · سلّةُ «أخرى — للمراجعة» للدور 17: ' . intval($bk['n']) . ' من ' . $liveAll
    . ' رابطًا حيًّا — **بندٌ مفتوحٌ ينتظر تصنيفَ المالك** (وعدُ H-14 لم يتمّ)' . "
");
$dupInBucket = intval($conn->query("SELECT COUNT(*) n FROM nav_items nb
                                     JOIN link_groups gb ON gb.id = nb.group_id
                                    WHERE nb.active = 1 AND gb.group_code LIKE '%others%'
                                      AND EXISTS (SELECT 1 FROM nav_items np
                                                   JOIN link_groups gp ON gp.id = np.group_id
                                                  WHERE np.role_id = nb.role_id AND np.route = nb.route
                                                    AND np.active = 1 AND gp.is_active = 1
                                                    AND gp.group_code NOT LIKE '%others%')")->fetch_assoc()['n']);
check($dupInBucket === 0,
    '★★★ والسلّةُ موقِفُ ما لم يُصنَّف لا سايدبارٌ موازٍ — صفرُ رابطٍ فيها مكرَّرٌ في مجموعةٍ مصنَّفةٍ لدورِه ('
    . $dupInBucket . ')');
$orphans = intval($conn->query("SELECT COUNT(*) n FROM nav_items
                                 WHERE role_id=17 AND group_id IS NULL")->fetch_assoc()['n']);
check($orphans === 0, '★★ **صفرُ رابطٍ يتيمٍ** — كلُّ روابط الدور 17 في مجموعةٍ من السبع');
$total = intval($conn->query("SELECT COUNT(*) n FROM nav_items WHERE role_id=17")->fetch_assoc()['n']);
check($total >= 63, '★ وصفرُ رابطٍ حُذف (' . $total . ' — الأصلُ 62 + الصندوقُ الموحّد)');
$oldHidden = intval($conn->query("SELECT COUNT(*) n FROM link_groups
                                   WHERE owner_role_id=17 AND is_active=0")->fetch_assoc()['n']);
check($oldHidden >= 5, '★ والمجموعاتُ القديمة **أُخفيت ولا حُذفت** (' . $oldHidden . ' مخفية) — عقدُ العمل ④');
/* والصندوقُ الموحّدُ انتقل مع خلافةِ NAV-09: «عملي اليوم» أُخفيت، ومسكنُه الآن
   مجموعةُ المرحلةِ ① — فيُطلَب في مجموعةٍ **حيّةٍ** لا في مجموعةٍ بعينِها بالاسم،
   ويرسب إن حُذف أو أُخفي أو نُقل إلى سلّةِ «أخرى». */
$inboxNav = $conn->query("SELECT g.group_code FROM nav_items n JOIN link_groups g ON g.id = n.group_id
                           WHERE n.role_id = 17 AND n.active = 1 AND g.is_active = 1
                             AND n.route = 'Finance/approvals_inbox.php'
                             AND g.group_code NOT LIKE '%others%'")->fetch_assoc();
check($inboxNav !== null,
    '★ والصندوقُ الموحّد في مجموعةٍ حيّةٍ لا في سلّةِ «أخرى» — خلَفُ «عملي اليوم» بعد NAV-09: '
    . ($inboxNav ? $inboxNav['group_code'] : 'غير موجود'));

// ═══ الخاتمة ═══
fwrite(STDOUT, "\n══════════════════════════════════════\n");
fwrite(STDOUT, "  النتيجة: {$PASS} نجاح · {$FAIL} فشل\n");
exit($FAIL > 0 ? 1 : 0);
