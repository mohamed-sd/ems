<?php
require_once __DIR__ . '/includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'صلاحيات التقارير';
$current_page = 'report-permissions';

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../emsreports/includes/functions.php';

// جلب جميع الأدوار
$roles = [];
$rr = mysqli_query($conn, "SELECT id, name FROM roles ORDER BY id ASC");
if ($rr) {
    while ($row = mysqli_fetch_assoc($rr)) $roles[] = $row;
}

// كتالوج التقارير
$catalog    = getReportsCatalog();
$categories = [];
foreach ($catalog as $code => $info) {
    $categories[$info['category']][] = $info;
}

// جلب جميع الصلاحيات الحالية دفعة واحدة
$perms = [];
$pr = mysqli_query($conn, "SELECT role_id, report_code FROM report_role_permissions");
if ($pr) {
    while ($row = mysqli_fetch_assoc($pr)) {
        $perms[$row['role_id']][$row['report_code']] = true;
    }
}

// عدد التقارير الكلي
$totalReports = count($catalog);

require_once __DIR__ . '/includes/layout_head.php';
?>

<style>
/* ── page-level styles ─────────────────────────────────────── */
.rp-tabs { display:flex; gap:0; border-bottom:2px solid var(--line); margin-bottom:0; flex-wrap:wrap; }
.rp-tab-btn {
    padding:10px 20px; border:none; background:none;
    font-family:inherit; font-size:.88rem; font-weight:600; color:var(--muted);
    cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px;
    transition:color .15s, border-color .15s; white-space:nowrap;
}
.rp-tab-btn:hover { color:var(--ink); }
.rp-tab-btn.active { color:var(--blue); border-bottom-color:var(--blue); }

.rp-pane { display:none; padding:24px; }
.rp-pane.active { display:block; }

.rp-cat-hd {
    display:flex; align-items:center; gap:8px;
    margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid var(--line);
}
.rp-cat-hd i { color:var(--blue); width:16px; text-align:center; }
.rp-cat-hd strong { font-size:.9rem; color:var(--ink); }
.rp-cat-count { font-size:.72rem; color:var(--muted); background:var(--surface); border-radius:999px; padding:1px 8px; margin-right:4px; }

.rp-grid {
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));
    gap:8px;
    margin-bottom:24px;
}
.rp-item {
    display:flex; align-items:center; gap:10px;
    padding:10px 12px; border-radius:8px;
    border:1px solid var(--line); background:#fff;
    cursor:pointer; transition:border-color .15s, background .15s;
    user-select:none;
}
.rp-item:hover { border-color:var(--blue); background:#f5f8ff; }
.rp-item.enabled { border-color:rgba(5,150,105,.35); background:rgba(5,150,105,.04); }
.rp-item.saving { opacity:.6; pointer-events:none; }

.rp-cb {
    width:18px; height:18px; border-radius:4px; border:2px solid #cbd5e1;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
    transition:all .15s; background:#fff;
}
.rp-item.enabled .rp-cb { background:#059669; border-color:#059669; }
.rp-cb svg { display:none; }
.rp-item.enabled .rp-cb svg { display:block; }

.rp-label { font-size:.82rem; color:var(--ink-2); line-height:1.3; }
.rp-label i { color:var(--blue); width:14px; text-align:center; margin-left:3px; }

.rp-toolbar {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:20px; flex-wrap:wrap; gap:10px;
}
.rp-role-title { font-size:.95rem; font-weight:700; color:var(--ink); }
.rp-role-title span { color:var(--blue); }
.rp-count-badge {
    font-size:.75rem; color:var(--muted); background:var(--surface);
    border:1px solid var(--line); border-radius:8px; padding:3px 10px;
}

/* toast */
#rpToast {
    position:fixed; bottom:24px; left:50%; transform:translateX(-50%) translateY(20px);
    background:#1e293b; color:#fff; border-radius:10px; padding:12px 20px;
    font-size:.85rem; font-weight:600; z-index:9999; opacity:0;
    transition:opacity .25s, transform .25s; pointer-events:none;
    display:flex; align-items:center; gap:8px; white-space:nowrap;
    box-shadow:0 4px 20px rgba(0,0,0,.3);
}
#rpToast.show { opacity:1; transform:translateX(-50%) translateY(0); }
#rpToast.ok  { background:#059669; }
#rpToast.err { background:#dc2626; }
</style>

<!-- رأس الصفحة -->
<div class="phead">
    <div>
        <h2><i class="fas fa-chart-pie" style="color:var(--blue);margin-left:8px;"></i>صلاحيات التقارير</h2>
        <p class="sub">تحكم في التقارير التي يمكن لكل دور رؤيتها — <?php echo $totalReports; ?> تقرير إجمالاً</p>
    </div>
</div>

<?php if (empty($roles)): ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <p>لا توجد أدوار في النظام. يرجى إضافة الأدوار أولاً.</p>
        </div>
    </div>
</div>
<?php else: ?>

<div class="card" style="overflow:hidden;">

    <!-- تبويبات الأدوار -->
    <div style="background:var(--surface); border-bottom:1px solid var(--line); padding:0 20px;">
        <div class="rp-tabs" id="rpTabs">
            <?php foreach ($roles as $i => $role): ?>
            <?php
            // حساب عدد التقارير الممنوحة لهذا الدور
            $grantedCount = isset($perms[$role['id']]) ? count($perms[$role['id']]) : 0;
            ?>
            <button class="rp-tab-btn <?php echo $i === 0 ? 'active' : ''; ?>"
                    data-target="rp-pane-<?php echo $role['id']; ?>"
                    type="button">
                <?php echo htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?>
                <span style="font-size:.7rem; opacity:.7; margin-right:4px;">(<?php echo $grantedCount; ?>/<?php echo $totalReports; ?>)</span>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- محتوى كل دور -->
    <?php foreach ($roles as $i => $role): ?>
    <?php
    $grantedCount = isset($perms[$role['id']]) ? count($perms[$role['id']]) : 0;
    ?>
    <div class="rp-pane <?php echo $i === 0 ? 'active' : ''; ?>" id="rp-pane-<?php echo $role['id']; ?>">

        <!-- شريط الأدوات -->
        <div class="rp-toolbar">
            <div class="rp-role-title">
                دور: <span><?php echo htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <span class="rp-count-badge" id="cnt-<?php echo $role['id']; ?>">
                    <i class="fas fa-check" style="color:var(--green);"></i>
                    <span id="cnt-val-<?php echo $role['id']; ?>"><?php echo $grantedCount; ?></span> / <?php echo $totalReports; ?> تقرير مفعّل
                </span>
                <button class="btn btn-sm btn-success" onclick="selectAll(<?php echo $role['id']; ?>)" type="button">
                    <i class="fas fa-check-double"></i> تحديد الكل
                </button>
                <button class="btn btn-sm btn-ghost" onclick="deselectAll(<?php echo $role['id']; ?>)" type="button">
                    <i class="fas fa-times"></i> إلغاء الكل
                </button>
            </div>
        </div>

        <!-- التقارير مجمعة حسب الفئة -->
        <?php foreach ($categories as $cat => $catReports): ?>
        <div style="margin-bottom:20px;">
            <div class="rp-cat-hd">
                <i class="fas <?php echo getCategoryIcon($cat); ?>"></i>
                <strong><?php echo getCategoryLabel($cat); ?></strong>
                <span class="rp-cat-count"><?php echo count($catReports); ?></span>
            </div>
            <div class="rp-grid">
                <?php foreach ($catReports as $report): ?>
                <?php
                $isEnabled = isset($perms[$role['id']][$report['code']]);
                $itemId    = 'ri_' . $role['id'] . '_' . $report['code'];
                ?>
                <div class="rp-item <?php echo $isEnabled ? 'enabled' : ''; ?>"
                     id="<?php echo $itemId; ?>"
                     onclick="toggleItem(this, <?php echo $role['id']; ?>, '<?php echo htmlspecialchars($report['code'], ENT_QUOTES, 'UTF-8'); ?>')"
                     title="<?php echo htmlspecialchars($report['description'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="rp-cb">
                        <svg width="10" height="8" viewBox="0 0 10 8" fill="none">
                            <path d="M1 4L3.5 6.5L9 1" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="rp-label">
                        <i class="fas <?php echo $report['icon']; ?>"></i>
                        <?php echo htmlspecialchars($report['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
    <?php endforeach; ?>

</div>

<!-- Toast إشعار -->
<div id="rpToast"><i class="fas fa-check-circle"></i><span id="rpToastMsg">تم</span></div>

<script>
(function () {
    /* ── Tabs ─────────────────────────────────────────────── */
    document.getElementById('rpTabs').addEventListener('click', function (e) {
        var btn = e.target.closest('.rp-tab-btn');
        if (!btn) return;
        // deactivate all
        document.querySelectorAll('.rp-tab-btn').forEach(function (b) { b.classList.remove('active'); });
        document.querySelectorAll('.rp-pane').forEach(function (p) { p.classList.remove('active'); });
        // activate clicked
        btn.classList.add('active');
        document.getElementById(btn.dataset.target).classList.add('active');
    });

    /* ── Toggle single item ───────────────────────────────── */
    window.toggleItem = function (el, roleId, code) {
        if (el.classList.contains('saving')) return;
        var enable = !el.classList.contains('enabled');
        el.classList.add('saving');
        sendPerm(roleId, code, enable, function (ok, msg) {
            el.classList.remove('saving');
            if (ok) {
                el.classList.toggle('enabled', enable);
                updateCounter(roleId, enable ? 1 : -1);
            } else {
                // rollback
            }
            toast(msg, ok);
        });
    };

    /* ── Select all ───────────────────────────────────────── */
    window.selectAll = function (roleId) {
        var pane  = document.getElementById('rp-pane-' + roleId);
        var items = pane.querySelectorAll('.rp-item:not(.enabled):not(.saving)');
        if (!items.length) { toast('جميع التقارير مفعّلة بالفعل', true); return; }
        var done = 0;
        items.forEach(function (el) {
            el.classList.add('saving');
            sendPerm(roleId, el.id.replace('ri_' + roleId + '_', ''), true, function (ok) {
                el.classList.remove('saving');
                if (ok) el.classList.add('enabled');
                done++;
                if (done === items.length) toast('تم تفعيل جميع التقارير', true);
            });
        });
        // optimistic counter
        updateCounter(roleId, items.length);
    };

    /* ── Deselect all ─────────────────────────────────────── */
    window.deselectAll = function (roleId) {
        var pane  = document.getElementById('rp-pane-' + roleId);
        var items = pane.querySelectorAll('.rp-item.enabled:not(.saving)');
        if (!items.length) { toast('لا توجد صلاحيات لإلغائها', false); return; }
        var done = 0;
        items.forEach(function (el) {
            el.classList.add('saving');
            sendPerm(roleId, el.id.replace('ri_' + roleId + '_', ''), false, function (ok) {
                el.classList.remove('saving');
                if (ok) el.classList.remove('enabled');
                done++;
                if (done === items.length) toast('تم إلغاء جميع التقارير', true);
            });
        });
        updateCounter(roleId, -items.length);
    };

    /* ── AJAX send ────────────────────────────────────────── */
    function sendPerm(roleId, code, enable, cb) {
        var fd = new FormData();
        fd.append('role_id',     roleId);
        fd.append('report_code', code);
        fd.append('action',      enable ? 'enable' : 'disable');
        fetch('update_permission_quick.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) { cb(d.success, d.message); })
            .catch(function ()  { cb(false, 'خطأ في الاتصال'); });
    }

    /* ── Counter badge ────────────────────────────────────── */
    function updateCounter(roleId, delta) {
        var el  = document.getElementById('cnt-val-' + roleId);
        var tab = document.querySelector('[data-target="rp-pane-' + roleId + '"]');
        if (!el) return;
        var val = parseInt(el.textContent) + delta;
        val = Math.max(0, val);
        el.textContent = val;
        // update tab label count
        if (tab) {
            var sm = tab.querySelector('span');
            if (sm) sm.textContent = '(' + val + '/<?php echo $totalReports; ?>)';
        }
    }

    /* ── Toast ────────────────────────────────────────────── */
    var _toastTimer;
    function toast(msg, ok) {
        var el  = document.getElementById('rpToast');
        var txt = document.getElementById('rpToastMsg');
        txt.textContent = msg;
        el.classList.remove('ok', 'err', 'show');
        el.classList.add(ok ? 'ok' : 'err');
        void el.offsetWidth; // reflow
        el.classList.add('show');
        clearTimeout(_toastTimer);
        _toastTimer = setTimeout(function () { el.classList.remove('show'); }, 2500);
    }
})();
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
