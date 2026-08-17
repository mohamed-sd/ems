<?php
/**
 * main/my_workspace.php — مساحة عملي: الحاوية الثمانية فوق الإدارات
 * (update0004 · NAV-07 · NAV-01 §3)
 * ───────────────────────────────────────────────────────────────────────────
 * «ما يخص الشخص لا الإدارة يخرج من قوائم الإدارات إلى مساحة واحدة فوقها» —
 * تُبنى فوق البوابة الشخصية (H-18) ومساحات العمل (H-19) القائمتين: حاوية
 * تنقّل لا شاشة معاملة (المبدأ ⑥-ب) — كل عنصر يقفز إلى موضعه الحي.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/tenant_scope.php';   // نطاقُ الكيانِ من السياقِ لا من رقمٍ صلب
require_once __DIR__ . '/../includes/screen_contract.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$role = strval($_SESSION['user']['role'] ?? '');
if ($company_id <= 0 && $role !== '-1') { header("Location: ../login.php"); exit(); }
/* ⇐ INJ-0408 · INJ-0425 · «بحسابِ سوبر بلا كيان: العدَّاداتُ صفرٌ أو تُطلب
     اختيارُ الكيانِ صراحةً — ولا تُعرض بياناتُ الكيان 4 ضمنًا»، و«السوبر بلا
     كيانٍ يرى **منتقيَ كيانٍ لا أرقامًا**؛ ولا يوجد في الملف رقمُ شركةٍ صلب». */
$company_id  = ems_scope_company($conn);
$__needsPick = ($company_id <= 0);

$q1 = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_assoc() : null; return $x ? intval(reset($x)) : 0; };

// العدادات — من المحرّك (WFM) أولًا ومصادرِ ما قبله جمعًا صادقًا
/* ◆ INJ-0407: و«مهامي» من التعريفِ المركزيِّ نفسِه الذي تقرؤه شارةُ الشريطِ —
     فلا يتفرّق رقمُ البلاطةِ عن رقمِ الشارة. */
require_once __DIR__ . '/../includes/my_workspace_counts.php';
$myTasks = max(0, ems_my_tasks_count($conn, $company_id, $uid));
/* موافقاتي = صندوقُ الاعتمادِ الموحَّدِ الثلاثيُّ (طلبٌ بحَملي + خطوةُ
   approval_links + حلقةُ سلسلةٍ بدوري).
   ◆ **ولا يُحسَب هنا بنصٍّ ثانٍ.** كان يُحسَب، فتفرَّق عن الصندوقِ في خمسةِ
     مواضعَ — منها أنَّ السوبرَ يُدرَج له 7,342 صفَّ مرحلةٍ وتعدُّ بلاطتُه 1,501.
     فصار العددُ والعرضُ يقرآن `includes/approvals_inbox_scope.php` وحدَه
     (INJ-0587: «الرقمُ على البلاطةِ = عددُ صفوفِ الصندوقِ بالضبط، لكلِّ دور»). */
$role = strval($_SESSION['user']['role'] ?? '');
require_once __DIR__ . '/../includes/approvals_inbox_scope.php';
$__apx = ems_approvals_inbox_counts($conn, $company_id, $uid, $role, $role === '-1');
$myApprovals = $__apx['total'];
/* ⇐ INJ-0581 · «رقمُ شارةِ طلباتي = عددُ الصفوفِ في الشاشةِ التي تفتحها البلاطةُ
     **بالضبط**». وكان يجمع `requests` و`fin_requests` والشاشةُ تعرض الأولَ وحدَه
     — **فلا شاشةَ تعرض ما يعدُّه العدّاد**. فصار العدُّ من التعريفِ الواحدِ الذي
     تقرؤه الشاشةُ نفسُها، والماليةُ تُعدُّ وتُعرض **برابطِ شاشتِها** لا تُجمع فيه. */
require_once __DIR__ . '/../includes/my_workspace_counts.php';
$myRequests    = max(0, ems_my_requests_count($conn, $company_id, $uid));
$myFinRequests = max(0, ems_my_fin_requests_count($conn, $company_id, $uid));
$myTickets = $q1("SELECT COUNT(*) FROM tickets t JOIN ticket_participants p ON p.tk_id = t.id
                   WHERE t.company_id = {$company_id} AND p.person_id = {$uid} AND t.head_state = 'open'");
$myMsgs = $q1("SELECT COUNT(*) FROM personal_notifications WHERE company_id = {$company_id}
                AND user_id = {$uid} AND read_at IS NULL")
        + $q1("SELECT COUNT(*) FROM fin_notifications WHERE company_id = {$company_id}
                AND is_read = 0 AND (target_user_id = {$uid} OR target_user_id IS NULL)");

/* UXR-0071 (UI-DEF: ترتيب الأولوية معكوس): ما ينتظر قراري وتنفيذي يتصدر —
   القرارات (موافقاتي) أولًا ثم التنفيذ (مهامي) ثم متابعاتي، والمعلوماتي بعدها.

   ◆ البلاطاتُ **حقولٌ مسمّاةٌ لا مواضعُ في مصفوفة**: كانت `$t[0]..$t[4]`
     فتُقرأ بالعدِّ، وكانت الرتبةُ (①…⑩) **ملصوقةً في نصِّ العنوان** فلا سبيلَ
     لعرضِها بحجمٍ أو لونٍ غيرِ حجمِ العنوانِ ولونِه. فصارت حقلًا مستقلًّا:
     الترتيبُ الحاكمُ محفوظٌ كما هو، والعنوانُ نظيفٌ يُقرأ.
   ◆ و`group`: البلاطاتُ نوعان لا نوعٌ واحد — **ما ينتظرني** (يحمل عددًا
     فيُقرأ رقمُه) و**مداخلي** (تنقّلٌ بلا عدد). وكانت الأحدَ عشرةَ تُصيَّر
     بمظهرٍ واحدٍ، فبلاطةٌ عليها 1,501 موافقةً تبدو كبلاطةِ «ملفي». والترتيبُ
     داخلَ المجموعتين هو ترتيبُ NAV-01 §3 نفسُه بلا إزاحة. */
$tiles = array(
    array('group' => 'wait', 'ord' => '①', 'title' => 'موافقاتي',
          'desc' => 'صندوق الاعتماد الموحد: طلباتٌ وخطواتٌ وحلقاتُ سلسلةٍ — كلٌّ يقفز لموضع فعله',
          'icon' => 'fa fa-check-double', 'href' => '../Portal/approvals_inbox.php', 'count' => $myApprovals),
    array('group' => 'wait', 'ord' => '②', 'title' => 'مهامي',
          'desc' => 'كل ما ينتظر تنفيذي — لا ما ينتظر قراري',
          'icon' => 'fa fa-tasks', 'href' => '../Portal/my_tasks.php', 'count' => $myTasks),
    array('group' => 'wait', 'ord' => '③', 'title' => 'بلاغاتي',
          'desc' => 'رفع بلاغ · المفتوحة · ما ينتظر ردي — فالبلاغ يخص الشخص لا الإدارة',
          'icon' => 'fa fa-bullhorn', 'href' => '../Tickets/ticket_contextual_open.php', 'count' => $myTickets),
    array('group' => 'wait', 'ord' => '④', 'title' => 'طلباتي',
          'desc' => 'من قاموس الأنواع الـ62 — وكلُّ طلبٍ يُعرف عند مَن توقف',
          'icon' => 'fa fa-paper-plane', 'href' => '../Portal/my_requests.php', 'count' => $myRequests),
    // INJ-0581: الماليةُ بلاطتُها وشاشتُها — فلا تُجمع في رقمٍ لا شاشةَ له ولا تُخفى
    array('group' => 'wait', 'ord' => '④-ب', 'title' => 'طلباتي المالية',
          'desc' => 'بوابةُ الطلبات المالية — عدُّها من شاشتها لا من غيرها',
          'icon' => 'fa fa-file-invoice-dollar', 'href' => '../FinRequests/my_requests.php', 'count' => $myFinRequests),
    array('group' => 'wait', 'ord' => '⑤', 'title' => 'المراسلات والتنبيهات',
          'desc' => 'تنبيهاتي (وذو الفعل يتحول مهمةً) والمراسلات من الشريط',
          'icon' => 'fa fa-bell', 'href' => '../Portal/notifications.php', 'count' => $myMsgs),
    array('group' => 'door', 'ord' => '⑥', 'title' => 'طلب جديد',
          'desc' => 'تقديمٌ من القاموس الحاكم — والمالي عبر بوابته',
          'icon' => 'fa fa-plus-circle', 'href' => '../Portal/my_requests.php', 'count' => null),
    array('group' => 'door', 'ord' => '⑦', 'title' => 'لوحة دوري',
          'desc' => 'ما ينتظرني اليوم بترتيب الإلحاح',
          'icon' => 'fa fa-tachometer-alt', 'href' => '../main/dashboard.php', 'count' => null),
    array('group' => 'door', 'ord' => '⑧', 'title' => 'ملفي',
          'desc' => 'ملفي الشخصي وكشوفي ووثائقي',
          'icon' => 'fa fa-id-card', 'href' => '../Portal/my_portal.php', 'count' => null),
    // NAV-01 v6 §7 (update0007 S-03/S-04): عنصران إلزاميان لكل حسابٍ بلا استثناء
    array('group' => 'door', 'ord' => '⑨', 'title' => 'إنجازي',
          'desc' => 'ما أنجزتُه أمسِ والأسبوعَ والشهرَ — وبمدةٍ أحددها بتاريخين · بلغة عملي',
          'icon' => 'fa fa-trophy', 'href' => '../Portal/my_achievement.php', 'count' => null),
    array('group' => 'door', 'ord' => '⑩', 'title' => 'بوابتي',
          'desc' => 'ما يخصّني ومحيطي المباشر: معدتي · ورديتي · موقعي — بحسب دوري',
          'icon' => 'fa fa-door-open', 'href' => '../Portal/my_portal.php?view=gateway', 'count' => null),
);

/* مجموعتان تُصيَّران بالترتيب — بطاقةٌ لكلٍّ برأسِ بطاقةٍ كسائرِ الشاشات. */
$tileGroups = array(
    'wait' => array('label' => 'ما ينتظرني', 'icon' => 'fas fa-hourglass-half'),
    'door' => array('label' => 'مداخلي',     'icon' => 'fas fa-compass'),
);

$page_title = 'إيكوبيشن | مساحة عملي';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'مساحة عملي — فوق الإدارات كلها'; $header_icon = 'fa fa-user-circle';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about(
        'ما يخصك أنت لا إدارتك: ثمانية عناصر تُفتح من أي موضع وتحل عشر روابط مكررة '
        . 'في أربع عشرة قائمة (NAV-01 §3) — حاوية تنقل تقفز بك إلى موضع الفعل.',
        array('البلاغ والموافقة والطلب تخص الشخص — فمكانها هنا لا في قائمة كل إدارة'));
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا بلاطاتِ مساحةِ عملٍ معروضةً لهذا الحساب', 'اختر كيانَ العملِ أولًا — ثم تظهر موافقاتُك ومهامُّك وطلباتُك');
    ?>
    <?php if ($__needsPick): ?>
        <!-- INJ-0425 · «السوبر بلا كيانٍ يرى منتقيَ كيانٍ **لا أرقامًا**»:
             فلا تُصيَّر البلاطاتُ أصلًا — رقمٌ بلا كيانٍ مُعلَنٍ يُقرأ خطأً. -->
        <?php echo ems_company_picker($conn, $company_id); ?>
    <?php else: ?>
    <?php
    /* ══ التصييرُ بلغةِ الشاشاتِ الرئيسةِ نفسِها — بصفرِ CSS جديد ═════════
       ◆ الشقيقةُ المباشرةُ لهذه الشاشةِ هي `Portal/my_portal.php`، ولغتُها
         مكوّنُ `ems-ptp-*` في `ems-screens.css`: حدٌّ واحدٌ (1px) واستدارةُ 10
         وحشوةُ 12 وخلفيةٌ فاتحةٌ — **بلا ظلٍّ ولا تدرُّجٍ ولا تحويمٍ ولا شكلٍ
         سداسيّ**. وغلافُها `card` / `card-header` / `card-body` كما في كلِّ
         شاشاتِ النظامِ الرئيسة (المعدات · العملاء · المشاريع · الموردون …).
       ◆ فالبلاطةُ هنا **نفسُ بلاطةِ البوابة**: قيمةٌ كبيرةٌ فوق عنوانٍ خافت.
         القيمةُ عددٌ في «ما ينتظرني»، وأيقونةٌ في «مداخلي» — مكوّنٌ واحدٌ
         وموضعٌ واحدٌ للقيمة. والشرحُ في `title` فلا يُثقل البلاطة.
       ◆ ولا نسخةَ محليةً: صفرُ `<style>` وصفرُ `style=` وصفرُ صنفٍ جديدٍ في
         ورقةِ الأنماط. */
    foreach ($tileGroups as $gKey => $g):
        $gTiles = array_values(array_filter($tiles, function ($t) use ($gKey) { return $t['group'] === $gKey; }));
        if (!$gTiles) { continue; }
    ?>
    <div class="card">
        <div class="card-header">
            <h5><i class="<?php echo htmlspecialchars($g['icon']); ?>"></i> <?php echo htmlspecialchars($g['label']); ?></h5>
        </div>
        <div class="card-body">
            <div class="ems-ptp-grid-sm">
                <?php foreach ($gTiles as $t): ?>
                <?php /* ⚠ `href` أولُ سمةٍ عمدًا: شاهدُ INJ-0587
                         (`tests/approvals_inbox_parity_test.php`) يمسك البلاطةَ
                         بمرساةِ `<a href="../Portal/approvals_inbox.php"` حرفيًّا،
                         وكان `class` يسبقه فلا يجدها فيقرأ الرقمَ NULL ويسقط.
                         ويحمل موضعُ القيمةِ الوسمَ `ems-ws-badge` لأن الشاهدَ
                         يقرأ الرقمَ بـ`~badge[^>]*>\s*([0-9]+)~`؛ ولا يحمل
                         `badge-` لأن `span[class*="badge-"]` في `ems-tables.css`
                         يمحو التصميمَ صامتًا. */ ?>
                <a href="<?php echo htmlspecialchars($t['href']); ?>" class="ems-ptp-cardlink" title="<?php echo htmlspecialchars($t['desc']); ?>">
                    <div class="ems-ptp-tile">
                        <?php if ($t['count'] === null): ?>
                            <div class="ems-ptp-tile-num"><i class="<?php echo htmlspecialchars($t['icon']); ?>"></i></div>
                        <?php else: ?>
                            <?php /* الرقمُ حاضرٌ ولو كان صفرًا: بلاطةٌ بلا رقمٍ
                                     كانت تلتبس ببلاطةٍ عدَّادُها صفر. */ ?>
                            <div class="ems-ptp-tile-num ems-ws-badge"><?php echo intval($t['count']); ?></div>
                        <?php endif; ?>
                        <div class="ems-ptp-tile-lbl"><?php echo htmlspecialchars($t['ord'] . ' ' . $t['title']); ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
