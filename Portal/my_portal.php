<?php
/**
 * Portal/my_portal.php — بوابتي (H-18 · الشاشة 187)
 * ───────────────────────────────────────────────────────────────────────────
 * USR-01 §8-①: «بطاقاتٌ تتشكّل بالصفة والمفاتيح · عنصرٌ مغلق: **لا يظهر
 * أصلًا** (لا رسالةَ منع) · كلُّ رقمٍ ينقر لمصدره».
 * «ماذا يخصّني أنا؟» — والبوابةُ لا تملك بيانًا: كلُّ بطاقةٍ من مالكها.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
require_once __DIR__ . '/../app/Services/Portal/PortalFeedService.php';
require_once __DIR__ . '/../app/Services/Portal/CapacityService.php';

use App\Services\Portal\PortalFeedService as PFS;
use App\Services\Portal\CapacityService as CAP;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid        = intval($_SESSION['user']['id'] ?? 0);
$is_super   = (strval($_SESSION['user']['role'] ?? '') === '-1');
if (!$is_super && $company_id <= 0) { header("Location: ../login.php"); exit(); }

$gate = $is_super ? ems_tenant_db()->forAllTenants('my portal super') : ems_tenant_db();

// الصفةُ الفعّالة — من الجلسة أو أولُ نشطةٍ للحساب
$myCaps = CAP::activeOf($conn, $gate, $uid);
$capId = intval($_SESSION['active_capacity']['id'] ?? 0);
if ($capId <= 0) {
    foreach ($myCaps as $c) { if ((string)$c['state'] === 'active') { $capId = intval($c['id']); break; } }
}
$feed = $capId > 0 ? PFS::feed($conn, $gate, $company_id, $uid, $capId) : null;

/* ── بوابتي فوق المحرك (م-د · WFM-01): عدّادات حيّة من work_items/requests/
   approval_links/work_notifications/achievement_records — كل رقمٍ ينقر
   لشاشته (عرف USR-01: البوابة لا تملك بيانًا؛ كلُّ بطاقةٍ من مالكها) ── */
$wfmCards = array();
if ($uid > 0) {
    $qn = function ($sql) use ($conn) { $r = mysqli_query($conn, $sql);
        return $r ? intval(mysqli_fetch_row($r)[0]) : 0; };
    $coW = $company_id > 0 ? "company_id = {$company_id} AND" : '';
    $wfmCards = array(
        array('مهامي المفتوحة', $qn("SELECT COUNT(*) FROM work_items WHERE {$coW} assigned_user_id = {$uid}
              AND status NOT IN ('closed_accepted','cancelled')"), 'my_tasks.php'),
        array('مهامي المتأخرة', $qn("SELECT COUNT(*) FROM work_items WHERE {$coW} assigned_user_id = {$uid}
              AND (status = 'overdue' OR (due_at < NOW() AND status IN ('assigned','accepted','in_progress')))"), 'my_tasks.php?view=late'),
        array('طلباتي الحية', $qn("SELECT COUNT(*) FROM requests WHERE {$coW} requester_user_id = {$uid}
              AND status IN ('submitted','routed','in_approval','approved','executing','returned')"), 'my_requests.php'),
        array('موافقات بيدي الآن', $qn("SELECT COUNT(*) FROM requests WHERE {$coW} current_holder_user_id = {$uid}
              AND status IN ('submitted','routed','in_approval')"), 'approvals_inbox.php'),
        array('تنبيهات غير مقروءة', $qn("SELECT COUNT(*) FROM personal_notifications WHERE {$coW} user_id = {$uid}
              AND read_at IS NULL"), 'notifications.php'),
        array('إنجازي — 30 يوما', $qn("SELECT COUNT(*) FROM achievement_records WHERE {$coW} person_user_id = {$uid}
              AND reversed_at IS NULL AND recognized_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"), 'my_achievement.php'),
        array('رسائل غير مقروءة', $qn("SELECT COUNT(*) FROM messages WHERE {$coW} receiver_id = {$uid}
              AND is_read = 0 AND COALESCE(is_deleted_receiver,0) = 0"), '../chats/index.php'),
    );
}

/* ── فصلُ الرقمِ عن نصِّه في قيمةِ بطاقةٍ — عرضٌ لا حساب ────────────────────
   بطاقةُ الإحصاءِ الموحَّدةُ تجعل **الرقمَ** صاحبَ الخطِّ الكبير، وقيمُ التغذيةِ
   تأتي من `PortalFeedService` جملةً واحدةً («1501 بانتظار قرارك» · «170 حدثًا»
   · «إجمالي المكوّنات 1234.00»). فيُلتقط أوّلُ عددٍ في الجملةِ فيصير القيمةَ،
   وما بقي حولَه يصير سطرًا تابعًا هادئًا — **بلا فقدِ حرفٍ واحد**.
   وإن لم يكن في الجملةِ عددٌ أصلًا («لا عقدَ في السجل») بقيت كاملةً في موضعِ
   القيمةِ بخطٍّ نصّيٍّ (`ems-statcard__value--text`) فلا تُبتر بقصِّ الـ35px.
   ⚠ `PREG_OFFSET_CAPTURE` يعطي إزاحةً **بالبايتات** ولو مع `/u` — فالقطعُ
     أدناه بـ`substr` البايتيةِ عمدًا، ومزجُها بـ`mb_substr` يقطع حرفًا عربيًّا. */
function ems_ptp_split_value($raw)
{
    $raw = trim(preg_replace('/\s+/u', ' ', (string) $raw));
    if ($raw === '') { return array('num' => '—', 'rest' => '', 'is_num' => true); }
    // قيمةٌ كلُّها رقمٌ أو رمزُ «لا قيمة» — تُعرض كما هي بالخطِّ الكبير
    if (preg_match('/^[0-9\s.,%:\/+\x{2212}\x{2013}\x{2014}-]+$/u', $raw)) {
        return array('num' => $raw, 'rest' => '', 'is_num' => true);
    }
    if (preg_match('/[0-9][0-9.,]*/', $raw, $m, PREG_OFFSET_CAPTURE)) {
        $hit  = $m[0][0];
        $at   = $m[0][1];
        $num  = rtrim($hit, '.,');
        $rest = substr($raw, 0, $at) . ' ' . substr($raw, $at + strlen($hit));
        $rest = trim(preg_replace('/\s+/u', ' ', $rest));
        return array('num' => $num, 'rest' => $rest, 'is_num' => true);
    }
    return array('num' => $raw, 'rest' => '', 'is_num' => false);
}

// سجلُّ نشاطي — يراه صاحبُه (§5)
$activity = array();
$r = $conn->query("SELECT * FROM portal_activity_log
                    WHERE company_id={$company_id} AND account_id={$uid}
                    ORDER BY id DESC LIMIT 20");
while ($r && ($row = $r->fetch_assoc())) { $activity[] = $row; }

$page_title = 'إيكوبيشن | بوابتي';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'بوابتي'; $header_icon = 'fa fa-id-card';
    $header_actions = array(
        array('href' => 'my_achievement.php', 'icon' => 'fa fa-chart-simple', 'label' => 'إنجازي'),
        array('href' => 'my_evaluation.php', 'icon' => 'fa fa-user-check', 'label' => 'التقييم'),
        array('href' => '../user_capacities.php', 'icon' => 'fa fa-people-arrows', 'label' => 'مبدل المساحة'),
    );
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (§2·2-③).
         ⛔ ولا فلترَ يُخترَع: `ems_filter_box` يشتقُّ ضوابطَه من رؤوسِ
         الجدولِ المُصيَّرِ نفسِه، ويخفي نفسَه إن غاب الجدول. */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_my_portal')); ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_my_portal
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف المكون' => 'g20',
            'الحساب' => 'g21',
            'الدور' => 'g22',
            'المكون' => 'g23',
            'محتواه الحي' => 'g24',
            'مصدره' => 'g25',
            'آخر تحديث' => 'g26',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('my_portal');
        echo ems_w14_grid('emsList_my_portal', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في البوابة الشخصية'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
    // حزمةُ الحالاتِ الدنيا (بوابة ٩) — مخفيةٌ افتراضًا ويُظهرها منطقُ الشاشة
    echo ems_states_bundle('لا بطاقات بيانات في بوابتي بعد', 'بوابتي تتشكل بالصفة النشطة ومفاتيح الظهور — راجع الصفة أو مفاتيح العرض');
    ?>

    <?php if ($wfmCards): ?>
    <!-- مساحة عملي فوق المحرك (WFM-01) — كل رقمٍ ينقر لشاشته -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-briefcase"></i> مساحة عملي — من المحرك حيا</h5></div>
    <div class="card-body">
        <!-- بطاقةُ الإحصاءِ الموحَّدة (`ems-statcards.css`) — البطاقةُ هي الرابطُ
             نفسُه فلا غلافَ بينهما، و`ems-statgrid--fill` يمدُّ الأخيرةَ على ما
             بقي من صفِّها فلا تبقى خانةٌ خاوية. -->
        <div class="ems-ptp-grid-sm ems-statgrid ems-statgrid--fill">
            <?php foreach ($wfmCards as $wc): ?>
                <a href="<?php echo htmlspecialchars($wc[2]); ?>" class="ems-ptp-cardlink ems-statcard">
                    <div class="ems-ptp-tile-num ems-statcard__value"><?php echo intval($wc[1]); ?></div>
                    <div class="ems-ptp-tile-lbl ems-statcard__title"><?php echo htmlspecialchars($wc[0]); ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div></div>
    <?php endif; ?>

    <?php if ($feed === null): ?>
        <div class="alert alert-warning">لا صفة نشطة لحسابك — الاشتقاق بيد مدير الصلاحيات (شاشة 182).</div>
    <?php elseif (!$feed['ok']): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($feed['reason']); ?></div>
    <?php else: ?>

    <div class="card"><div class="card-body">
        <!-- التصميمُ نفسُه الذي فوقَه حرفًا بحرف: الرقمُ أوّلًا بالخطِّ الكبير،
             ثم العنوان، ثم سطرٌ تابعٌ هادئٌ يحمل بقيةَ الجملةِ والفترةَ وإشارةَ
             المصدر. و«كلُّ رقمٍ ينقر لمصدره» (USR-01 §8-①) — فالبطاقةُ كلُّها
             هي الرابطُ متى كان لها مصدرٌ، ولا رابطَ داخلَ رابط. -->
        <div class="ems-ptp-grid-lg ems-statgrid ems-statgrid--fill">
            <?php foreach ($feed['cards'] as $c):
                $v    = ems_ptp_split_value($c['value']);
                $meta = array();
                if ($v['rest'] !== '')            { $meta[] = $v['rest']; }
                if ((string) $c['period'] !== '') { $meta[] = (string) $c['period']; }
                $href = $c['source_link'] !== null ? ('../' . $c['source_link']) : null;
                if ($href !== null) { $meta[] = 'المصدر ▸'; }
                $tag  = $href !== null ? 'a' : 'div';
            ?>
                <<?php echo $tag; ?> class="ems-ptp-card ems-statcard<?php
                        echo $href !== null ? ' ems-ptp-cardlink' : ''; ?>"<?php
                        echo $href !== null ? ' href="' . htmlspecialchars($href) . '"' : ''; ?>>
                    <div class="ems-ptp-card-value ems-statcard__value<?php
                        echo $v['is_num'] ? '' : ' ems-statcard__value--text'; ?>"><?php
                        echo htmlspecialchars($v['num']); ?></div>
                    <div class="ems-ptp-card-title ems-statcard__title"><?php
                        echo htmlspecialchars($c['title']); ?></div>
                    <?php if ($meta): ?>
                        <div class="ems-ptp-card-foot ems-statcard__meta"><?php
                            echo htmlspecialchars(implode(' · ', $meta)); ?></div>
                    <?php endif; ?>
                </<?php echo $tag; ?>>
            <?php endforeach; ?>
        </div>
        <?php if ($feed['hidden_sections']): ?>
            <p class="ems-ptp-hidden-note"><i class="fa fa-lock"></i>
                أقسام محجوبة بقرار موثق: <?php echo count($feed['hidden_sections']); ?>
                — إدارتها في لوحة الظهور (ADM-01)</p>
        <?php endif; ?>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-clock-rotate-left"></i>
        سجل نشاطي (لا يعدل ولا يحذف)</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap ems-ptp-w100" data-no-dt="1">
            <thead><tr><th>الوقت</th><th>الفعل</th><th>الهدف</th><th>النتيجة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">الموظف</th>
              <th class="ems-fn-th" data-fn="1">الصفة</th>
              <th class="ems-fn-th" data-fn="1">الإدارة</th>
              <th class="ems-fn-th" data-fn="1">الموقع</th>
              <th class="ems-fn-th" data-fn="1">المعدة المكلف عليها</th>
              <th class="ems-fn-th" data-fn="1">المشغل الآخر على المعدة</th>
              <th class="ems-fn-th" data-fn="1">مهام مفتوحة</th>
              <th class="ems-fn-th" data-fn="1">موافقات تنتظرني</th>
              <th class="ems-fn-th" data-fn="1">طلباتي المعلقة</th>
              <th class="ems-fn-th" data-fn="1">بلاغاتي المفتوحة</th>
              <th class="ems-fn-th" data-fn="1">إنجاز الشهر</th>
              <th class="ems-fn-th" data-fn="1">آخر دخول</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
              </tr></thead>
            <tbody>
            <?php foreach ($activity as $a): ?>
                <tr><td><small><?php echo htmlspecialchars((string)$a['at']); ?></small></td>
                    <td><?php echo htmlspecialchars((string)$a['action_code']); ?></td>
                    <td><?php echo htmlspecialchars($a['target_type'] . ' ' . $a['target_id']); ?></td>
                    <td><?php echo (string)$a['result'] === 'ok'
                        ? "<span class='badge badge-success'>ok</span>"
                        : "<span class='badge badge-danger'>denied</span>"; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
