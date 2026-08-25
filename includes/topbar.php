<?php
require_once __DIR__ . '/../includes/catch_log.php';
/**
 * Shared top bar component (.ems-topbar)
 * ---------------------------------------------------------------------------
 * The well-designed top bar originally lived inline inside main/dashboard.php
 * (the `.shot-topbar` block). It has been promoted here into a single, reusable
 * component so every page that shows the sidebar renders the SAME bar (DRY).
 *
 * It is included once from `insidebar.php`, which every sidebar page already
 * pulls in — so there is no need to touch each page individually.
 *
 * Structure lives here; ALL styling lives in
 *   assets/css/ems.main.all.style.css  (selectors `.ems-topbar*`).
 * The background colour is driven by the `--ems-topbar-bg` token defined there.
 *
 * Self-contained: it derives the role label + user name from the session
 * (role name is resolved from the `roles` table once and cached in the session
 * so we never re-query it on every page load).
 */

if (!defined('EMS_TOPBAR_RENDERED')) {
    define('EMS_TOPBAR_RENDERED', true);

    $ems_tb_user     = (isset($_SESSION['user']) && is_array($_SESSION['user'])) ? $_SESSION['user'] : array();
    $ems_tb_userName = isset($ems_tb_user['name']) ? $ems_tb_user['name'] : '';
    $ems_tb_role     = isset($ems_tb_user['role']) ? (string) $ems_tb_user['role'] : '';
    $ems_tb_roleText = '';

    if ($ems_tb_role !== '') {
        // Session cache: avoid hitting the roles table on every single page.
        if (
            isset($_SESSION['ems_topbar_role_label']['id'], $_SESSION['ems_topbar_role_label']['text'])
            && (string) $_SESSION['ems_topbar_role_label']['id'] === $ems_tb_role
        ) {
            $ems_tb_roleText = $_SESSION['ems_topbar_role_label']['text'];
        } elseif (isset($conn) && $conn) {
            $ems_tb_roleId = intval($ems_tb_role);
            if ($ems_tb_stmt = $conn->prepare('SELECT name FROM roles WHERE id=? LIMIT 1')) {
                $ems_tb_stmt->bind_param('i', $ems_tb_roleId);
                $ems_tb_stmt->execute();
                if ($ems_tb_res = $ems_tb_stmt->get_result()) {
                    if ($ems_tb_row = $ems_tb_res->fetch_assoc()) {
                        $ems_tb_roleText = $ems_tb_row['name'];
                    }
                }
                $ems_tb_stmt->close();
            }
            $_SESSION['ems_topbar_role_label'] = array('id' => $ems_tb_role, 'text' => $ems_tb_roleText);
        }
    }
    if ($ems_tb_roleText === '') {
        $ems_tb_roleText = 'مستخدم';
    }

    // اسم الموظف صاحب الحساب المسجَّل دخوله (عبر الربط users.employee_id)
    // ومسمّاه الوظيفي الحقيقي (employees.job_title_id → job_titles.name).
    // كانت حاويّة «المسمى الوظيفي» تطبع اسمَ الشخص (UI-DEF-01) — الربط صُحّح هنا.
    // يُخزَّنان في الجلسة كالدور لتفادي الاستعلام في كل صفحة (غياب مفتاح title
    // في خبيئة جلسة قديمة يعيد الاستعلام تلقائيًّا).
    $ems_tb_userId   = isset($ems_tb_user['id']) ? intval($ems_tb_user['id']) : 0;
    $ems_tb_empName  = '';
    $ems_tb_jobTitle = '';
    if ($ems_tb_userId > 0) {
        if (
            isset($_SESSION['ems_topbar_emp_label']['uid'], $_SESSION['ems_topbar_emp_label']['text'], $_SESSION['ems_topbar_emp_label']['title'])
            && (int) $_SESSION['ems_topbar_emp_label']['uid'] === $ems_tb_userId
        ) {
            $ems_tb_empName  = $_SESSION['ems_topbar_emp_label']['text'];
            $ems_tb_jobTitle = $_SESSION['ems_topbar_emp_label']['title'];
        } elseif (isset($conn) && $conn) {
            if ($ems_tb_estmt = $conn->prepare('SELECT e.name, jt.name AS job_title FROM users u LEFT JOIN employees e ON e.id = u.employee_id LEFT JOIN job_titles jt ON jt.id = e.job_title_id WHERE u.id = ? LIMIT 1')) {
                $ems_tb_estmt->bind_param('i', $ems_tb_userId);
                $ems_tb_estmt->execute();
                if ($ems_tb_eres = $ems_tb_estmt->get_result()) {
                    if ($ems_tb_erow = $ems_tb_eres->fetch_assoc()) {
                        $ems_tb_empName  = (string) ($ems_tb_erow['name'] ?? '');
                        $ems_tb_jobTitle = (string) ($ems_tb_erow['job_title'] ?? '');
                    }
                }
                $ems_tb_estmt->close();
            }
            $_SESSION['ems_topbar_emp_label'] = array('uid' => $ems_tb_userId, 'text' => $ems_tb_empName, 'title' => $ems_tb_jobTitle);
        }
    }

    // Per-page exceptions (set by the page BEFORE including insidebar.php):
    //   $ems_topbar_variant = 'dashboard'  → deep-yellow bar + the wide logo.
    //   anything else (default)            → gray bar + the square icon.png.
    $ems_tb_isDash   = (isset($ems_topbar_variant) && $ems_topbar_variant === 'dashboard');
    $ems_tb_barClass = $ems_tb_isDash ? 'ems-topbar ems-topbar--dash' : 'ems-topbar ems-topbar--icon';
    $ems_tb_logoFile = $ems_tb_isDash ? 'assets/images/logo 2.svg' : 'assets/images/icon.png';

    // Absolute paths keep the bar correct regardless of the page's folder depth.
    $ems_tb_logo    = function_exists('ems_url') ? ems_url($ems_tb_logoFile) : '/ems/' . $ems_tb_logoFile;
    $ems_tb_logout  = function_exists('ems_url') ? ems_url('logout.php') : '/ems/logout.php';
    $ems_tb_profile = function_exists('ems_url') ? ems_url('main/profile.php') : '/ems/main/profile.php';
    $ems_tb_settings = function_exists('ems_url') ? ems_url('Settings/settings.php') : '/ems/Settings/settings.php';
    // شاشة البلاغات — متاحة لكل المستخدمين عبر الشريط العلوي (نمط المراسلات:
    // بلا فحص صلاحية موديول؛ نطاق الرؤية يُفرَض داخل الشاشة نفسها).
    $ems_tb_tickets = function_exists('ems_url') ? ems_url('Tickets/tickets_list.php') : '/ems/Tickets/tickets_list.php';

    // مبدّلُ المساحة (H-15 · USR-01 §2): الصفةُ الفعّالةُ تُعرض، والتبديلُ
    // في شاشته (لا AJAX — action_guard يحجب غيرَ المسجَّل). العدُّ مكاشٌ في
    // الجلسة كالدور، ويظهر المبدّلُ لمن له أكثرُ من صفةٍ نشطة.
    $ems_tb_capLabel = (isset($_SESSION['active_capacity']['label']))
                     ? (string) $_SESSION['active_capacity']['label'] : '';
    $ems_tb_capCount = 0;
    if ($ems_tb_userId > 0) {
        if (isset($_SESSION['ems_topbar_caps']['uid'])
            && (int) $_SESSION['ems_topbar_caps']['uid'] === $ems_tb_userId) {
            $ems_tb_capCount = (int) $_SESSION['ems_topbar_caps']['n'];
        } elseif (isset($conn) && $conn) {
            if ($ems_tb_cstmt = @$conn->prepare(
                "SELECT COUNT(*) n FROM user_capacities WHERE account_id = ? AND state = 'active'")) {
                $ems_tb_cstmt->bind_param('i', $ems_tb_userId);
                $ems_tb_cstmt->execute();
                if ($ems_tb_cres = $ems_tb_cstmt->get_result()) {
                    if ($ems_tb_crow = $ems_tb_cres->fetch_assoc()) {
                        $ems_tb_capCount = (int) $ems_tb_crow['n'];
                    }
                }
                $ems_tb_cstmt->close();
            }
            $_SESSION['ems_topbar_caps'] = array('uid' => $ems_tb_userId, 'n' => $ems_tb_capCount);
        }
    }
    $ems_tb_capsUrl = function_exists('ems_url') ? ems_url('user_capacities.php') : '/ems/user_capacities.php';
    ?>
    <header class="<?php echo $ems_tb_barClass; ?>">
        <?php
        /* زرُّ فتح السايدبار — موضعُه الطبيعيُّ داخلَ الشريط لا طافيًا فوقه.
           كان <button> عائمًا (position:fixed) يُطبع من insidebar.php بعد
           الشريط، فيجلس فوق أيقونات الإجراءات ويتدلّى تحت حدّه. صار عنصرًا
           في شبكة الشريط يحجز مكانَه. المعرّفُ والصنفُ كما هما بلا تغيير،
           فسلوكُ insidebar.php وكلُّ قواعد CSS القائمة تعمل كما هي — وهو
           `display:none` على الحاسوب بقاعدةٍ قائمةٍ منذ الأصل. */
        ?>
        <button type="button" class="mobile-menu-btn" id="mobileMenuBtn"
                aria-label="القائمة الجانبية" aria-expanded="false" aria-controls="sidebar">
            <i class="fa fa-bars" aria-hidden="true"></i>
        </button>

        <div class="ems-topbar-logo">
            <img src="<?php echo htmlspecialchars($ems_tb_logo, ENT_QUOTES, 'UTF-8'); ?>" alt="Equipation">
        </div>

        <div class="ems-topbar-center">
            <?php
            /* AS-02 (UXR-0032/0033): الحاويّاتُ الأربع (الإدارة · الصفة · المسمى ·
               الموظف) اندمجت في مبدّلِ سياقٍ واحدٍ يعرض «الإدارة | الصفة النشطة»
               ويفتح لوحةً بالتفاصيل وتبديلِ الصفة. `data-value` تبقى لنسق الجوال
               (font-size:0 + ::after — گوتشا فصل الـspans القائمة).
               واسمُ الشخص انتقل لقائمة الحساب (UXR-0034-ب). */
            $ems_tb_ctxShow = $ems_tb_roleText . ($ems_tb_capLabel !== '' ? ' | ' . $ems_tb_capLabel : '');
            ?>
            <div class="ems-ctx-switcher" id="emsCtxSwitcher">
                <button type="button" class="ems-topbar-pill ems-ctx-btn" aria-haspopup="true" aria-expanded="false"
                        title="مبدل السياق — الإدارة والصفة والنطاق"
                        data-value="<?php echo htmlspecialchars($ems_tb_ctxShow, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-user-shield"></i><?php echo htmlspecialchars($ems_tb_roleText, ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($ems_tb_capLabel !== ''): ?><span class="ems-ctx-sep">|</span><?php echo htmlspecialchars($ems_tb_capLabel, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                    <i class="fa fa-chevron-down" style="font-size:.7em;opacity:.55"></i>
                </button>
                <div class="ems-ctx-panel" role="menu" dir="rtl">
                    <div class="ems-ctx-row"><i class="fas fa-user-shield"></i><span class="ems-ctx-k">الإدارة</span><span class="ems-ctx-v"><?php echo htmlspecialchars($ems_tb_roleText, ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <?php if ($ems_tb_capLabel !== '' || $ems_tb_capCount > 1): ?>
                    <div class="ems-ctx-row"><i class="fas fa-people-arrows"></i><span class="ems-ctx-k">الصفة النشطة</span><span class="ems-ctx-v"><?php echo htmlspecialchars($ems_tb_capLabel !== '' ? $ems_tb_capLabel : ('متعددة (' . $ems_tb_capCount . ')'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <?php endif; ?>
                    <?php if ($ems_tb_jobTitle !== ''): ?>
                    <div class="ems-ctx-row"><i class="fas fa-user-circle"></i><span class="ems-ctx-k">المسمى الوظيفي</span><span class="ems-ctx-v"><?php echo htmlspecialchars($ems_tb_jobTitle, ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <?php endif; ?>
                    <div class="ems-ctx-row"><i class="fas fa-id-card-alt"></i><span class="ems-ctx-k">الموظف المسؤول</span><span class="ems-ctx-v"><?php echo $ems_tb_empName !== '' ? htmlspecialchars($ems_tb_empName, ENT_QUOTES, 'UTF-8') : 'غير مرتبط بموظف'; ?></span></div>
                    <?php if ($ems_tb_capCount > 0): ?>
                    <div class="ems-ctx-actions">
                        <a href="<?php echo htmlspecialchars($ems_tb_capsUrl, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-repeat"></i> تبديل الصفة والنطاق</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="ems-topbar-actions">
            <?php /* ── قائمةُ الحساب أولَ أيقونةٍ من اليمين (قرارُ المالك 2026-08-17) ──
                 `.ems-topbar-actions` صندوقٌ مرنٌ يرث `direction: rtl`، فأولُ ابنٍ في
                 DOM هو أقصى اليمين. ونقلُ الكتلةِ هنا هو كلُّ ما يلزم — لا `order`
                 ولا قلبَ اتجاه، فيبقى ترتيبُ التنقّلِ بلوحةِ المفاتيح موافقًا للمرئيّ. */ ?>
            <?php
            /* AS-01 (UXR-0032): الأفعالُ الظاهرة خمسةٌ فقط — مساحةُ عملي · البحث ·
               الجرس · بلاغٌ جديد · الحساب. والبقيةُ (بوابتي · الملف · الإعدادات ·
               الخروج) في قائمة الحساب، ومعها اسمُ الشخص (UXR-0034-ب). */
            $ems_tb_portal = function_exists('ems_url') ? ems_url('Portal/my_portal.php') : '/ems/Portal/my_portal.php';
            ?>
            <div class="ems-account-menu" id="emsAccountMenu">
                <button type="button" class="ems-topbar-icon" title="الحساب" aria-label="الحساب" aria-haspopup="true" aria-expanded="false"
                        style="background:none;border:0;cursor:pointer"><i class="far fa-user"></i></button>
                <div class="ems-account-panel" role="menu" dir="rtl">
                    <div class="ems-acct-head">
                        <strong><?php echo htmlspecialchars($ems_tb_userName !== '' ? $ems_tb_userName : 'مستخدم', ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?php if ($ems_tb_empName !== ''): ?><small><?php echo htmlspecialchars($ems_tb_empName, ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                    </div>
                    <a href="<?php echo htmlspecialchars($ems_tb_portal, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-id-card"></i> بوابتي</a>
                    <a href="<?php echo htmlspecialchars($ems_tb_profile, ENT_QUOTES, 'UTF-8'); ?>"><i class="far fa-user"></i> الملف الشخصي</a>
                    <a href="<?php echo htmlspecialchars($ems_tb_settings, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-gear"></i> الإعدادات</a>
                    <a href="<?php echo htmlspecialchars($ems_tb_logout, ENT_QUOTES, 'UTF-8'); ?>" style="color:var(--danger-deep)"><i class="fas fa-power-off"></i> تسجيل الخروج</a>
                </div>
            </div>
            <?php
            /* ══ المراسلات — أيقونةٌ بعدّادِ ما لم يُقرأ (قرارُ المالك 2026-08-17) ══
               ◆ **موضعُها بجانبِ الحساب** ثانيةً من اليمين، وشكلُها من الصنفِ
                 `.ems-topbar-icon` نفسِه الذي يحمل «البحث الموحد» و«مساحة عملي»
                 — فلا لونَ جديدًا ولا مقاسَ خاصًّا ولا نمطَ موضعيّ: المكوّنُ
                 واحدٌ فالهويةُ واحدةٌ بالبناءِ لا بالمحاكاة. وأيقونتُها صلبةٌ
                 (`fas`) كأختَيها لا مفرَّغةً، فلا تشذُّ عن صفِّها.
               ◆ **وما يعدُّه**: الرسائلُ الواصلةُ إلى صاحبِ الجلسةِ ولم يقرأها —
                 وهو **نفسُه** ما تفتحه `chats/index.php` بالضبط، فالشاشةُ تعرض
                 محادثاتِ صاحبِها وحدَه. فلا يفترق عدّادٌ عن عارضِه، ولا يبقى
                 رقمٌ معلَّقٌ لا يملك المستخدمُ تصفيرَه.
               ◆ **والرقمُ يُطبع من الخادمِ أوّلًا** ثم يُحدَّث دوريًّا من
                 `chats/get_unread_count.php` (نقطةٌ قائمةٌ سلفًا بالعقدِ نفسِه
                 `{count:N}`): فأولُ رسمةٍ صادقةٌ ولا تومض الشارةُ متأخرةً.
               ◆ **والخللُ لا يُسقط الشريط**: `config` يضبط mysqli على عدمِ الرمي،
                 فيُفحص مُرجَعُ كلِّ خطوةٍ ويُعامَل أيُّ تعذُّرٍ صفرًا — شارةٌ لا
                 تظهر، لا شريطٌ علويٌّ مكسور.
               ◆ ولا حارسَ صلاحيةٍ على الوجهة: `chats/index.php` مفتوحةٌ لكلِّ
                 مصادَقٍ (بلا `enforce_current_page_view_permission`)، فالأيقونةُ
                 تُعرض لمن تُفتح له الشاشةُ فعلًا — لا بابَ يُرى ويُردُّ طارقُه. */
            $ems_tb_chats     = function_exists('ems_url') ? ems_url('chats/index.php') : '/ems/chats/index.php';
            $ems_tb_msgCount  = function_exists('ems_url') ? ems_url('chats/get_unread_count.php') : '/ems/chats/get_unread_count.php';
            $ems_tb_msgUnread = 0;
            if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
                try {
                    $__msSt = $GLOBALS['conn']->prepare(
                        "SELECT COUNT(*) c FROM messages
                          WHERE receiver_id = ? AND company_id = ?
                            AND is_read = 0 AND is_deleted_receiver = 0");
                    if ($__msSt) {
                        $__msUid = (int) ($_SESSION['user']['id'] ?? 0);
                        $__msCo  = (int) ($_SESSION['user']['company_id'] ?? 0);
                        $__msSt->bind_param('ii', $__msUid, $__msCo);
                        if ($__msSt->execute()) {
                            $__msRs = $__msSt->get_result();
                            if ($__msRs && ($__msRow = $__msRs->fetch_assoc())) {
                                $ems_tb_msgUnread = (int) $__msRow['c'];
                            }
                        }
                        $__msSt->close();
                    }
                } catch (\Throwable $__msE) { $ems_tb_msgUnread = 0; }
            }
            $ems_tb_msgTitle = $ems_tb_msgUnread > 0
                ? 'المراسلات — ' . $ems_tb_msgUnread . ' غير مقروءة'
                : 'المراسلات';
            ?>
            <a href="<?php echo htmlspecialchars($ems_tb_chats, ENT_QUOTES, 'UTF-8'); ?>"
               class="ems-topbar-icon ems-topbar-icon--badged" id="emsTopbarMessages"
               data-count-src="<?php echo htmlspecialchars($ems_tb_msgCount, ENT_QUOTES, 'UTF-8'); ?>"
               title="<?php echo htmlspecialchars($ems_tb_msgTitle, ENT_QUOTES, 'UTF-8'); ?>"
               aria-label="<?php echo htmlspecialchars($ems_tb_msgTitle, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-envelope"></i><span id="emsMessagesBadge" class="ems-topbar-badge"<?php
                echo $ems_tb_msgUnread > 0
                    ? ' aria-label="' . htmlspecialchars($ems_tb_msgUnread . ' رسالة غير مقروءة', ENT_QUOTES, 'UTF-8') . '">'
                      . htmlspecialchars($ems_tb_msgUnread > 99 ? '99+' : (string) $ems_tb_msgUnread, ENT_QUOTES, 'UTF-8')
                    : ' style="display:none;">';
            ?></span></a>
            <?php
            /* ══ الاعتمادات — أيقونةٌ لخمسِ إداراتٍ لا لكلِّ أحد (قرارُ المالك 2026-08-17) ══
               ◆ **مَن يراها**: مَن له مستوًى في سلسلةِ اعتمادِ الساعاتِ المُعلَنةِ
                 في `EMS_HOURS_APPROVAL_LEVELS` — التشغيلُ ثم الموردون ثم الأسطولُ
                 ثم الموارد البشرية/المشغّلون ثم المبيعات. والقائمةُ **تُشتقُّ من
                 السلسلةِ ولا تُكتب هنا**: لو زِيد مستوًى أو نُقص تبعته الأيقونةُ
                 بلا تعديلِ حرفٍ في هذا الملف. ومَن لا مستوى له لا يراها — فلا
                 بابَ يُعرض ثم يُردُّ طارقُه.
               ◆ **وما يعدُّه**: ما ينتظر **اعتمادَ إدارتِه هو** — لا ما ينتظر
                 السلسلةَ كلَّها. فالمستوى الثالثُ لا يرى ما لم يصله بعد.
                 والتعريفُ في `includes/hours_approval_badge.php` هو نفسُه شرطُ
                 «قيد الاعتماد» في الشاشةِ حرفًا، فلا يفترق عدّادٌ عن عارضِه.
               ◆ **وخبيئةُ دقيقةٍ بمفتاحِ المستخدمِ والكيانِ والمستوى**: عدُّ
                 المستوى الأول يمرُّ على ثمانيةٍ وأربعين ألفَ صفٍّ (36 مللي مقيسةً)،
                 فلا يُعاد في كلِّ طلب. والمفتاحُ يحمل الهويةَ — خبيئةٌ بلا هويةٍ
                 تُسرّب رقمَ حسابٍ إلى آخر (INJ-0407).
               ◆ **والخللُ لا يُسقط الشريط**: أيُّ تعذُّرٍ يعني صفرًا وشارةً لا تظهر. */
            $ems_tb_appLevel = 0;
            $ems_tb_appCount = 0;
            if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
                require_once __DIR__ . '/hours_approval_badge.php';
                $ems_tb_appLevel = ems_hours_approval_level_of($ems_tb_role);
                if ($ems_tb_appLevel > 0) {
                    $ems_tb_appCo  = (int) ($_SESSION['user']['company_id'] ?? 0);
                    $ems_tb_appKey = $ems_tb_appCo . ':' . $ems_tb_userId . ':' . $ems_tb_appLevel;
                    $ems_tb_appHit = isset($_SESSION['ems_hours_badge']) ? $_SESSION['ems_hours_badge'] : null;
                    if (is_array($ems_tb_appHit)
                        && (string) ($ems_tb_appHit['k'] ?? '') === $ems_tb_appKey
                        && (time() - (int) ($ems_tb_appHit['at'] ?? 0)) < 60) {
                        $ems_tb_appCount = (int) $ems_tb_appHit['n'];
                    } else {
                        $ems_tb_appCount = ems_hours_pending_count($GLOBALS['conn'], $ems_tb_role, $ems_tb_appCo);
                        $_SESSION['ems_hours_badge'] = array(
                            'n' => $ems_tb_appCount, 'at' => time(), 'k' => $ems_tb_appKey);
                    }
                }
            }
            if ($ems_tb_appLevel > 0):
                $ems_tb_appUrl   = function_exists('ems_url') ? ems_url('Approvals/hours_approval.php') : '/ems/Approvals/hours_approval.php';
                $ems_tb_appTitle = $ems_tb_appCount > 0
                    ? 'الاعتمادات — ' . $ems_tb_appCount . ' سجل ساعات ينتظر اعتماد إدارتك'
                    : 'الاعتمادات — لا سجل ينتظر اعتماد إدارتك';
            ?>
            <a href="<?php echo htmlspecialchars($ems_tb_appUrl, ENT_QUOTES, 'UTF-8'); ?>"
               class="ems-topbar-icon ems-topbar-icon--badged" id="emsTopbarApprovals"
               title="<?php echo htmlspecialchars($ems_tb_appTitle, ENT_QUOTES, 'UTF-8'); ?>"
               aria-label="<?php echo htmlspecialchars($ems_tb_appTitle, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-stamp"></i><span id="emsApprovalsBadge" class="ems-topbar-badge"<?php
                echo $ems_tb_appCount > 0
                    ? ' aria-label="' . htmlspecialchars($ems_tb_appCount . ' سجلا ينتظر الاعتماد', ENT_QUOTES, 'UTF-8') . '">'
                      . htmlspecialchars($ems_tb_appCount > 99 ? '99+' : (string) $ems_tb_appCount, ENT_QUOTES, 'UTF-8')
                    : ' style="display:none;">';
            ?></span></a>
            <?php endif; ?>
            <?php /* ── «مساحة عملي» رُفعت من شريطِ الأفعال (قرارُ المالك 2026-08-17) ──
                 ورُفع معها **حسابُ عدّادِها**: كان يقرأ القاعدةَ (بخبيئةِ جلسةٍ
                 دقيقةً) في كلِّ طلبٍ ليرسم شارةً لم تعد تُعرض — عملٌ يُدفع ثمنُه
                 ولا يُرى. والشاشةُ نفسُها لم تُغلق: `main/my_workspace.php` قائمةٌ
                 ولها اثنان وثلاثون صفَّ تنقّلٍ نشطًا، فالبابُ مفتوحٌ من موضعِه في
                 السايدبار لا من شريطِ الأفعال. ولإعادتِها: أعِدْ كتلةَ الإصدارِ هذه. */ ?>
            <?php
            /* ══ البلاغات — أيقونةٌ واحدةٌ بدل اثنتين (قرارُ المالك 2026-08-17) ══
               ◆ كانت هنا أيقونتان متجاورتان: **جرسٌ** يحمل عدَّ البلاغاتِ ويفتح
                 سجلَّها، و**بوقٌ** يفتح نموذجَ بلاغٍ جديد. بابان لبابٍ واحدٍ في
                 ذهنِ المستخدم — والجرسُ لغةُ «إشعار» لا لغةُ «بلاغ».
               ◆ فصارت واحدةً: **رمزُ البوقِ** (الذي كان يعبّر عن البلاغات) يحمل
                 **عدَّ الجرسِ ووجهتَه** — سجلُّ البلاغات. والمعرِّفان كما كانا
                 (`emsTopbarTickets` · `emsBreakdownBadge`) فحلقةُ العدِّ الدوريّةُ
                 تعمل بلا تعديلِ حرف.
               ◆ **وبابُ فتحِ بلاغٍ جديدٍ لم يُغلق**: زرُّ «أبلغ عن مشكلة» الطافي
                 أسفلَ كلِّ شاشةٍ مصادَقةٍ يرسل إلى `Tickets/ticket_contextual_open.php`
                 بسياقِ الشاشةِ ووقتِها — وهو أغنى من رابطٍ عارٍ إلى النموذج. */
            ?>
            <a href="<?php echo htmlspecialchars($ems_tb_tickets, ENT_QUOTES, 'UTF-8'); ?>" class="ems-topbar-icon ems-topbar-icon--badged" id="emsTopbarTickets" title="البلاغات" aria-label="البلاغات"><i class="fas fa-bullhorn"></i><span id="emsBreakdownBadge" class="ems-topbar-badge" style="display:none;"></span></a>
            <?php /* ── البحثُ الموحَّد آخرًا (أقصى اليسار) — قرارُ المالك ──
                 (NAV-01 §13-⑤): «يجد الكيانَ أيًّا كان نوعُه — فلا يُسأل المتدربُ
                 عن الشاشة». وموضعُه في ذيلِ الصفِّ لأنه بابُ استكشافٍ لا بابُ
                 عملٍ ينتظرك — فالأبوابُ ذاتُ الأعدادِ تتصدّر. */
            $ems_tb_search = function_exists('ems_url') ? ems_url('main/global_search.php') : '/ems/main/global_search.php'; ?>
            <a href="<?php echo htmlspecialchars($ems_tb_search, ENT_QUOTES, 'UTF-8'); ?>" class="ems-topbar-icon" title="البحث الموحد — بالكود أو الاسم" aria-label="البحث الموحد"><i class="fas fa-search"></i></a>
        </div>
    </header>
    <style>
        /* شارة عدّاد البلاغات على أيقونة التوبار — بنفس روح ألوان النظام (تنبيه أحمر). */
        /* و`--badged` اسمٌ محايدٌ لمرساةِ الشارةِ نفسِها: `breakdowns` وُلد لأيقونةِ
           البلاغات ثم استُعمل لمساحةِ عملي أيضًا، فصار اسمًا لا يصف ما يفعل.
           ولم يبقَ له مستعملٌ بعدَ دمجِ أيقونتَي البلاغاتِ ورفعِ مساحةِ عملي،
           فرُفع من المُحدِّد — ولا يُترك اسمٌ ميتٌ يوهم قارئَه بأنه حيّ. */
        .ems-topbar-icon--badged { position: relative; }

        /* ══ الشارةُ تركب الزاويةَ ولا تجلس على الرمز (قرارُ المالك 2026-08-17) ══
           ◆ **المقيسُ قبلَ التعديل**: الأيقونةُ قرصٌ 28×28 والرمزُ داخلَها 16×13
             فقط. والشارةُ كانت 18 ارتفاعًا (أي ثُلثَي الأيقونة) بإزاحةِ 4 بكسلات
             لا غير — فتقع أكثرُها **على** القرصِ لا خارجَه، وتغطّي من الرمزِ
             نفسِه 32٪ عند «7» و**50٪ عند «33»** و**58٪ عند «99+»**. فيرى
             المستخدمُ رقمًا فوق شكلٍ لا يتبيّنه، ولا يعرف أيَّ بابٍ يُنبِّهه.
           ◆ **والعلاجُ إزاحةٌ لا تصغيرٌ وحدَه**: القرصُ يُدفع إلى خارجِ حدودِ
             الأيقونةِ حتى يقع **فوقَ حافّتِها العلويّةِ الداخلية**، فيلامس الرمزَ
             بشريطٍ رفيعٍ في زاويتِه ولا يغطّي وسطَه. وارتفاعُه 16 لا 18، وحشوتُه
             أضيق — فالشارةُ تابعٌ للأيقونةِ لا ندٌّ لها.
           ◆ **والفجوةُ بين الأيقوناتِ رُفعت 8 ← 10**: الشارةُ المدفوعةُ خارجًا
             تحتاج بكسلَين تتنفّسهما وإلا لامست حلقتُها البيضاءُ جارتَها.
           ◆ **واللونُ مُعلَنٌ صراحةً**: خلفيةٌ حمراءُ `--c-state-danger` (#dc2626)
             وحبرٌ أبيض — وبقيمةٍ احتياطيةٍ صريحةٍ لكلٍّ منهما، فلو تعذّر رمزٌ
             بقيت الشارةُ حمراءَ بيضاءَ لا شفّافةً بلا لون. */
        .ems-topbar-actions { gap: 12px; }
        .ems-topbar-badge {
            position: absolute; top: -7px;
            /* ◆ **المرساةُ على الحافّةِ الخارجيةِ لا الداخلية** — وهذا بيتُ القصيد:
                 `inset-inline-end: -7px` يُثبّت الحافّةَ **الداخليّة** فينمو القرصُ
                 نحوَ الرمز كلّما طال الرقم — 5٪ عند «7» و10٪ عند «33» و17٪ عند
                 «99+». وبتثبيتِ الحافّةِ الخارجيةِ ينمو الرقمُ **بعيدًا** عن الرمز،
                 فالتغطيةُ تبقى **5٪ ثابتةً مهما طال العدد** (مقيسٌ للثلاثة).
               ◆ **وفيزيائيّةٌ لا منطقيّة عن قصد**: القرصُ يحمل `direction: ltr`
                 لتُقرأ أرقامُه، فالخواصُّ المنطقيّةُ تنقلب عليه ولا تستقرّ على
                 جهةٍ — قِستُ الثلاثَ صيغٍ فوقعت المنطقيّةُ في الجهةِ المعاكسة.
                 والصريحُ هنا أصدقُ من المنطقيِّ الملتبس، ومعه مرآتُه للإنجليزية. */
            right: calc(100% - 9px); left: auto;
            min-width: 16px; height: 16px; padding: 0 4px;
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--c-state-danger, #dc2626);
            color: var(--white, #ffffff);
            font-size: .62rem; font-weight: 800;
            border-radius: 999px; line-height: 1;
            font-variant-numeric: tabular-nums;
            direction: ltr; unicode-bidi: isolate;
            /* الحلقةُ بلونِ الشريطِ لا بالأبيض: نصفُها فوقَ الأيقونةِ البيضاءِ
               ونصفُها فوقَ الشريطِ الرماديّ، فالأبيضُ يذوب في أحدِهما. */
            box-shadow: 0 0 0 2px var(--ems-topbar-bg, #e2e2e2);
            pointer-events: none;
        }
        html[dir="ltr"] .ems-topbar-badge,
        body[dir="ltr"] .ems-topbar-badge { right: auto; left: calc(100% - 9px); }
        /* شريطُ اللوحةِ أصفرُ لا رماديّ — فحلقتُه تتبع لونَه */
        .ems-topbar--dash .ems-topbar-badge {
            box-shadow: 0 0 0 2px var(--ems-topbar-bg-dash, #f3be00);
        }
    </style>
    <script>
        // ===== شارات عدّادات الشريط (البلاغات · المراسلات) =====
        // العدّ ضمن نطاق رؤية المستخدم، فلا تكشف الشارة ما لا يراه على الشاشة.
        //
        // عدّادان بمنطقٍ واحد: كانت حلقةُ البلاغاتِ مكتوبةً بعينِها، فأيُّ عدّادٍ
        // ثانٍ يعني نسخَ اثنين وعشرين سطرًا — ونسختان تتفرّقان عند أولِ إصلاح.
        // فصارت واحدةً تأخذ (الشارة · مصدرَ الرقم · صياغةَ الاسمِ الميسور)، وكلُّ
        // عدّادٍ لاحقٍ سطرٌ في القائمةِ لا حلقةٌ جديدة.
        //
        // ◆ ومصدرُ الرقمِ يُقرأ من `data-count-src` على الأيقونةِ نفسِها لا يُثبَّت
        //   هنا: المسارُ يُبنى في PHP بـ`ems_url()` فيتبع موضعَ التطبيق.
        // ◆ والعنوانُ (`title`/`aria-label`) على الأيقونةِ يتبع الرقمَ أيضًا، فمن
        //   لا يرى الشارةَ يسمع العدد.
        (function () {
            var FEEDS = [
                { id: 'emsBreakdownBadge', icon: 'emsTopbarTickets',  url: '/ems/Tickets/get_tickets_count.php',
                  base: 'البلاغات',   one: function (c) { return c + ' بلاغ مفتوح'; } },
                { id: 'emsMessagesBadge',  icon: 'emsTopbarMessages', url: null,
                  base: 'المراسلات', one: function (c) { return c + ' رسالة غير مقروءة'; } }
            ];

            FEEDS.forEach(function (f) {
                var badge = document.getElementById(f.id);
                if (!badge) return;
                var icon = f.icon ? document.getElementById(f.icon) : null;
                var url  = (icon && icon.getAttribute('data-count-src')) || f.url;
                if (!url) return;
                var inFlight = false;

                function paint(c) {
                    if (c > 0) {
                        badge.textContent = c > 99 ? '99+' : String(c);
                        badge.style.display = 'inline-flex';
                        badge.setAttribute('aria-label', f.one(c));
                        if (icon) {
                            icon.setAttribute('title', f.base + ' — ' + f.one(c));
                            icon.setAttribute('aria-label', f.base + ' — ' + f.one(c));
                        }
                    } else {
                        badge.style.display = 'none';
                        badge.removeAttribute('aria-label');
                        if (icon) { icon.setAttribute('title', f.base); icon.setAttribute('aria-label', f.base); }
                    }
                }

                function refresh() {
                    if (document.hidden || inFlight) return;
                    inFlight = true;
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', url, true);
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.onload = function () {
                        try { paint(parseInt(JSON.parse(xhr.responseText).count, 10) || 0); } catch (e) {}
                        inFlight = false;
                    };
                    xhr.onerror = function () { inFlight = false; };
                    xhr.onabort = function () { inFlight = false; };
                    xhr.send();
                }

                refresh();
                document.addEventListener('visibilitychange', function () {
                    if (!document.hidden) refresh();
                });
                setInterval(refresh, 60000);
            });
        })();

        // ===== AS-02/قائمة الحساب: فتح/إغلاق اللوحتين (نقرة خارجية تُغلق · Esc تُغلق) =====
        (function () {
            /* حاضنُ الأفعالِ يقصُّ اللوحةَ ما دام `overflow` فيه غيرَ `visible`
               (حزامُ التمريرِ على الجوّال). فيُرفع القصُّ ما دامت مفتوحةً ويُعاد
               عند الإغلاق — والقاعدةُ نفسُها في ems-shell.css لا نمطًا سطريًّا. */
            function syncClip() {
                document.querySelectorAll('.ems-topbar-actions').forEach(function (row) {
                    row.classList.toggle('ems-actions-menu-open', !!row.querySelector('.ems-account-menu.open'));
                });
            }
            function closeAll() {
                document.querySelectorAll('.ems-ctx-switcher.open, .ems-account-menu.open')
                    .forEach(function (b) {
                        b.classList.remove('open');
                        var t = b.querySelector('button');
                        if (t) t.setAttribute('aria-expanded', 'false');
                    });
                syncClip();
            }
            ['emsCtxSwitcher', 'emsAccountMenu'].forEach(function (id) {
                var box = document.getElementById(id);
                if (!box) return;
                var btn = box.querySelector('button');
                btn.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    var open = box.classList.toggle('open');
                    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                    syncClip();
                });
            });
            document.addEventListener('click', closeAll);
            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape') { closeAll(); }
            });
        })();
    </script>
    <?php
}
