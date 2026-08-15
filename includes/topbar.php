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
                        title="مبدّل السياق — الإدارة والصفة والنطاق"
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
            <?php
            // مساحةُ عملي — بابٌ فوق الإدارات كلِّها (NAV-01 §3) لا رابطٌ داخل كل قائمة،
            // وعدّادُ ما ينتظر المستخدمَ على أيقونة الباب (NAV-02 §7.5) — بخبيئة جلسةٍ خمسَ دقائق.
            $ems_tb_ws = function_exists('ems_url') ? ems_url('main/my_workspace.php') : '/ems/main/my_workspace.php';
            /* ══ INJ-0407 · «عدَّادٌ واحدٌ بقيمةٍ واحدة» ═══════════════════════════════
                 كانت الشارةُ تُحسب بـ`ApprovalsInboxService::inbox($conn, $company_id)`
                 — **بوسيطِ الكيانِ وحدَه بلا مستخدم**: فتعدُّ ما ينتظر الشركةَ كلَّها
                 ويراه الجميعُ رقمًا واحدًا. ونصُّ القبولِ يشترط أن يرى حسابان في
                 الكيانِ نفسِه **رقمين مختلفين** يطابقان بلاطتَيهما.
               ◆ فصار المصدرُ واحدًا: `ems_workspace_badge` هي نفسُها ما تجمعه
                 البلاطتان — فلا يتفرّق عدّادٌ عن عارضِه.
               ◆ والخبيئةُ **بمفتاحِ المستخدمِ والكيان** ولدقيقةٍ لا خمس: خبيئةٌ
                 بلا هويةٍ تُسرّب رقمَ حسابٍ إلى آخر، وخمسُ دقائقَ تُبقيه بعد الفعل. */
            $ems_tb_wsCount = 0;
            $ems_tb_wsUid   = intval($_SESSION['user']['id'] ?? 0);
            $ems_tb_wsCo    = intval($_SESSION['user']['company_id'] ?? 0);
            $ems_tb_wsKey   = $ems_tb_wsCo . ':' . $ems_tb_wsUid;
            $ems_tb_wsCache = isset($_SESSION['ems_ws_badge']) ? $_SESSION['ems_ws_badge'] : null;
            if (is_array($ems_tb_wsCache)
                && (string) ($ems_tb_wsCache['k'] ?? '') === $ems_tb_wsKey
                && (time() - intval($ems_tb_wsCache['at'])) < 60) {
                $ems_tb_wsCount = intval($ems_tb_wsCache['n']);
            } else {
                if (isset($GLOBALS['conn'])) {
                    require_once __DIR__ . '/my_workspace_counts.php';
                    try {
                        $ems_tb_wsCount = ems_workspace_badge($GLOBALS['conn'], $ems_tb_wsCo,
                            $ems_tb_wsUid, strval($_SESSION['user']['role'] ?? ''));
                    } catch (\Throwable $e) { ems_catch_log($e, __METHOD__); ems_catch_ignored($e, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل بقيمةِ 0 — $ems_tb_wsCount'); $ems_tb_wsCount = 0; }
                }
                $_SESSION['ems_ws_badge'] = array('n' => $ems_tb_wsCount, 'at' => time(), 'k' => $ems_tb_wsKey);
            }
            ?>
            <a href="<?php echo htmlspecialchars($ems_tb_ws, ENT_QUOTES, 'UTF-8'); ?>" class="ems-topbar-icon ems-topbar-breakdowns" title="مساحة عملي — موافقاتي وطلباتي ومهامي" aria-label="مساحة عملي"><i class="fas fa-briefcase"></i><?php if ($ems_tb_wsCount > 0): ?><span class="ems-topbar-badge"><?php echo $ems_tb_wsCount > 99 ? '99+' : $ems_tb_wsCount; ?></span><?php endif; ?></a>
            <?php // البحثُ الموحَّد (NAV-01 §13-⑤): «يجد الكيانَ أيًّا كان نوعُه — فلا يُسأل المتدربُ عن الشاشة»
            $ems_tb_search = function_exists('ems_url') ? ems_url('main/global_search.php') : '/ems/main/global_search.php'; ?>
            <a href="<?php echo htmlspecialchars($ems_tb_search, ENT_QUOTES, 'UTF-8'); ?>" class="ems-topbar-icon" title="البحث الموحد — بالكود أو الاسم" aria-label="البحث الموحد"><i class="fas fa-search"></i></a>
            <?php // AS-08: الجرسُ بعددٍ حقيقي (بلاغات ضمن نطاق الرؤية) — قائمًا منذ S12 ?>
            <a href="<?php echo htmlspecialchars($ems_tb_tickets, ENT_QUOTES, 'UTF-8'); ?>" class="ems-topbar-icon ems-topbar-breakdowns" id="emsTopbarTickets" title="البلاغات" aria-label="البلاغات"><i class="fas fa-bell"></i><span id="emsBreakdownBadge" class="ems-topbar-badge" style="display:none;"></span></a>
            <?php // AS-08: زرُّ بلاغٍ سياقيٌّ ثابتُ الموضع
            $ems_tb_newTicket = function_exists('ems_url') ? ems_url('Tickets/ticket_form.php') : '/ems/Tickets/ticket_form.php'; ?>
            <a href="<?php echo htmlspecialchars($ems_tb_newTicket, ENT_QUOTES, 'UTF-8'); ?>" class="ems-topbar-icon" title="بلاغ جديد" aria-label="بلاغ جديد"><i class="fas fa-bullhorn"></i></a>
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
        </div>
    </header>
    <style>
        /* شارة عدّاد البلاغات على أيقونة التوبار — بنفس روح ألوان النظام (تنبيه أحمر). */
        .ems-topbar-breakdowns { position: relative; }
        .ems-topbar-badge {
            position: absolute; top: -4px; inset-inline-end: -4px;
            min-width: 18px; height: 18px; padding: 0 5px;
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--c-state-danger); color: var(--white); font-size: .68rem; font-weight: 800;
            border-radius: 999px; line-height: 1; box-shadow: 0 0 0 2px var(--c-surface);
        }
    </style>
    <script>
        // ===== شارة عدّاد البلاغات المفتوحة =====
        // العدّ ضمن نطاق رؤية المستخدم، فلا تكشف الشارة ما لا يراه على الشاشة.
        (function () {
            var badge = document.getElementById('emsBreakdownBadge');
            if (!badge) return;
            var inFlight = false;

            function updateBreakdownBadge() {
                if (document.hidden || inFlight) return;
                inFlight = true;
                var xhr = new XMLHttpRequest();
                xhr.open('GET', '/ems/Tickets/get_tickets_count.php', true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.onload = function () {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        var c = parseInt(data.count, 10) || 0;
                        if (c > 0) {
                            badge.textContent = c > 99 ? '99+' : String(c);
                            badge.style.display = 'inline-flex';
                            badge.setAttribute('aria-label', c + ' بلاغ مفتوح');
                        } else {
                            badge.style.display = 'none';
                            badge.removeAttribute('aria-label');
                        }
                    } catch (e) {}
                    inFlight = false;
                };
                xhr.onerror = function () { inFlight = false; };
                xhr.onabort = function () { inFlight = false; };
                xhr.send();
            }

            updateBreakdownBadge();
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) updateBreakdownBadge();
            });
            setInterval(updateBreakdownBadge, 60000);
        })();

        // ===== AS-02/قائمة الحساب: فتح/إغلاق اللوحتين (نقرة خارجية تُغلق · Esc تُغلق) =====
        (function () {
            ['emsCtxSwitcher', 'emsAccountMenu'].forEach(function (id) {
                var box = document.getElementById(id);
                if (!box) return;
                var btn = box.querySelector('button');
                btn.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    var open = box.classList.toggle('open');
                    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
            });
            document.addEventListener('click', function () {
                document.querySelectorAll('.ems-ctx-switcher.open, .ems-account-menu.open')
                    .forEach(function (b) { b.classList.remove('open'); });
            });
            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape') {
                    document.querySelectorAll('.ems-ctx-switcher.open, .ems-account-menu.open')
                        .forEach(function (b) { b.classList.remove('open'); });
                }
            });
        })();
    </script>
    <?php
}
