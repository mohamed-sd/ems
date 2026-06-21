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
    // شاشة البلاغات الموحّدة (متاحة لكل المستخدمين عبر التوبار، نمط المراسلات).
    $ems_tb_breakdowns = function_exists('ems_url') ? ems_url('Maintenance/breakdowns.php') : '/ems/Maintenance/breakdowns.php';
    ?>
    <header class="<?php echo $ems_tb_barClass; ?>">
        <div class="ems-topbar-logo">
            <img src="<?php echo htmlspecialchars($ems_tb_logo, ENT_QUOTES, 'UTF-8'); ?>" alt="Equipation">
        </div>

        <div class="ems-topbar-center">
            <span class="ems-topbar-pill"><?php echo htmlspecialchars($ems_tb_roleText, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if ($ems_tb_userName !== ''): ?>
                <span class="ems-topbar-pill"><?php echo htmlspecialchars($ems_tb_userName, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </div>

        <div class="ems-topbar-actions">
            <a href="<?php echo htmlspecialchars($ems_tb_breakdowns, ENT_QUOTES, 'UTF-8'); ?>" class="ems-topbar-icon ems-topbar-breakdowns" id="emsTopbarBreakdowns" title="البلاغات" aria-label="البلاغات"><i class="fas fa-triangle-exclamation"></i><span id="emsBreakdownBadge" class="ems-topbar-badge" style="display:none;"></span></a>
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
        // ===== شارة عدّاد البلاغات الجديدة في التوبار =====
        (function () {
            var badge = document.getElementById('emsBreakdownBadge');
            if (!badge) return;
            var inFlight = false;

            function updateBreakdownBadge() {
                if (document.hidden || inFlight) return;
                inFlight = true;
                var xhr = new XMLHttpRequest();
                xhr.open('GET', '/ems/Maintenance/get_breakdown_count.php', true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.onload = function () {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        var c = parseInt(data.count, 10) || 0;
                        if (c > 0) {
                            badge.textContent = c > 99 ? '99+' : String(c);
                            badge.style.display = 'inline-flex';
                            badge.setAttribute('aria-label', c + ' بلاغ جديد');
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
