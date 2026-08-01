<?php
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

    // اسم الموظف صاحب الحساب المسجَّل دخوله (عبر الربط users.employee_id).
    // يُخزَّن في الجلسة كالدور لتفادي الاستعلام في كل صفحة.
    $ems_tb_userId  = isset($ems_tb_user['id']) ? intval($ems_tb_user['id']) : 0;
    $ems_tb_empName = '';
    if ($ems_tb_userId > 0) {
        if (
            isset($_SESSION['ems_topbar_emp_label']['uid'], $_SESSION['ems_topbar_emp_label']['text'])
            && (int) $_SESSION['ems_topbar_emp_label']['uid'] === $ems_tb_userId
        ) {
            $ems_tb_empName = $_SESSION['ems_topbar_emp_label']['text'];
        } elseif (isset($conn) && $conn) {
            if ($ems_tb_estmt = $conn->prepare('SELECT e.name FROM users u LEFT JOIN employees e ON e.id = u.employee_id WHERE u.id = ? LIMIT 1')) {
                $ems_tb_estmt->bind_param('i', $ems_tb_userId);
                $ems_tb_estmt->execute();
                if ($ems_tb_eres = $ems_tb_estmt->get_result()) {
                    if ($ems_tb_erow = $ems_tb_eres->fetch_assoc()) {
                        $ems_tb_empName = (string) ($ems_tb_erow['name'] ?? '');
                    }
                }
                $ems_tb_estmt->close();
            }
            $_SESSION['ems_topbar_emp_label'] = array('uid' => $ems_tb_userId, 'text' => $ems_tb_empName);
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
        <div class="ems-topbar-logo">
            <img src="<?php echo htmlspecialchars($ems_tb_logo, ENT_QUOTES, 'UTF-8'); ?>" alt="Equipation">
        </div>

        <div class="ems-topbar-center">
            <span class="ems-topbar-pill" title="الإدارة">
                <i class="fas fa-user-shield"></i>الإدارة: <?php echo htmlspecialchars($ems_tb_roleText, ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <?php if ($ems_tb_capLabel !== '' || $ems_tb_capCount > 1): ?>
                <a class="ems-topbar-pill" href="<?php echo htmlspecialchars($ems_tb_capsUrl, ENT_QUOTES, 'UTF-8'); ?>"
                   title="مبدّل المساحة — صفاتك ونطاقاتك" style="text-decoration:none">
                    <i class="fas fa-people-arrows"></i>الصفة:
                    <?php echo htmlspecialchars($ems_tb_capLabel !== '' ? $ems_tb_capLabel : ('متعددة (' . $ems_tb_capCount . ') ▾'), ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endif; ?>
            <?php if ($ems_tb_userName !== ''): ?>
                <span class="ems-topbar-pill" title="المسمى الوظيفي">
                    <i class="fas fa-user-circle"></i>المسمى الوظيفي: <?php echo htmlspecialchars($ems_tb_userName, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            <?php endif; ?>
            <?php if ($ems_tb_empName !== ''): ?>
                <span class="ems-topbar-pill ems-topbar-pill--employee" title="الموظف المسؤول">
                    <i class="fas fa-id-card-alt"></i>الموظف المسؤول: <?php echo htmlspecialchars($ems_tb_empName, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            <?php else: ?>
                <span class="ems-topbar-pill ems-topbar-pill--muted" title="الموظف المسؤول">
                    <i class="fas fa-id-card-alt"></i>الموظف المسؤول: غير مرتبط بموظف
                </span>
            <?php endif; ?>
        </div>

        <div class="ems-topbar-actions">
            <a href="<?php echo htmlspecialchars($ems_tb_tickets, ENT_QUOTES, 'UTF-8'); ?>" class="ems-topbar-icon ems-topbar-breakdowns" id="emsTopbarTickets" title="البلاغات" aria-label="البلاغات"><i class="fas fa-tower-observation"></i><span id="emsBreakdownBadge" class="ems-topbar-badge" style="display:none;"></span></a>
            <a href="<?php echo htmlspecialchars($ems_tb_logout, ENT_QUOTES, 'UTF-8'); ?>" class="ems-topbar-icon ems-topbar-icon--power" title="تسجيل الخروج" aria-label="تسجيل الخروج"><i class="fas fa-power-off"></i></a>
            <a href="<?php echo htmlspecialchars($ems_tb_profile, ENT_QUOTES, 'UTF-8'); ?>" class="ems-topbar-icon" title="الملف الشخصي" aria-label="الملف الشخصي"><i class="far fa-user"></i></a>
            <a href="<?php echo htmlspecialchars($ems_tb_settings, ENT_QUOTES, 'UTF-8'); ?>" class="ems-topbar-icon" title="الإعدادات" aria-label="الإعدادات"><i class="fas fa-gear"></i></a>
        </div>
    </header>
    <style>
        /* شارة عدّاد البلاغات على أيقونة التوبار — بنفس روح ألوان النظام (تنبيه أحمر). */
        .ems-topbar-breakdowns { position: relative; }
        .ems-topbar-badge {
            position: absolute; top: -4px; inset-inline-end: -4px;
            min-width: 18px; height: 18px; padding: 0 5px;
            display: inline-flex; align-items: center; justify-content: center;
            background: #dc2626; color: #fff; font-size: .68rem; font-weight: 800;
            border-radius: 999px; line-height: 1; box-shadow: 0 0 0 2px rgba(255,255,255,.85);
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
    </script>
    <?php
}
