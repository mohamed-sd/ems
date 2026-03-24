<?php
/**
 * Admin Panel — Shared Layout Header
 * Include AFTER requiring auth.php and calling super_admin_require_login().
 * Expected variables:
 *   $page_title   string  – shown in <title> and topbar
 *   $current_page string  – active nav slug
 *   $admin        array   – from super_admin_current()
 */

if (!function_exists('e')) {
    function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// Pending subscription badge (suppress error if table missing)
$_admin_pending_badge = 0;
$_bp = @mysqli_query($conn, "SELECT COUNT(*) AS c FROM admin_subscription_requests WHERE status='pending'");
if ($_bp) {
    $_br = mysqli_fetch_assoc($_bp);
    $_admin_pending_badge = intval($_br['c']);
}

$_admin_nav = [
    ['slug' => 'dashboard',     'label' => 'لوحة التحكم',    'icon' => 'fa-gauge-high',        'url' => 'dashboard'],
    ['slug' => 'managers',      'label' => 'إدارة المدراء',   'icon' => 'fa-user-shield',       'url' => 'managers'],
    ['slug' => 'companies',     'label' => 'إدارة الشركات',   'icon' => 'fa-building',          'url' => 'companies'],
    ['slug' => 'permissions',   'label' => 'إدارة الصلاحيات',  'icon' => 'fa-lock-open',         'url' => 'permissions'],
    ['slug' => 'subscriptions', 'label' => 'طلبات الاشتراك',  'icon' => 'fa-file-circle-check', 'url' => 'subscriptions/requests', 'badge' => $_admin_pending_badge],
    ['slug' => 'plans',         'label' => 'خطط الاشتراك',    'icon' => 'fa-layer-group',       'url' => 'plans'],
    ['slug' => 'support',       'label' => 'الدعم الفني',     'icon' => 'fa-headset',           'url' => 'support/view'],
    ['slug' => 'audit-log',     'label' => 'سجل المراجعة',    'icon' => 'fa-scroll',            'url' => 'audit-log'],
    ['slug' => 'settings',      'label' => 'الإعدادات',       'icon' => 'fa-gear',              'url' => 'settings'],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($page_title ?? ''); ?> | لوحة الإدارة العليا</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ═══════════════════════════ TOKENS ═══════════════════════════ */
:root {
    --sb-bg:       #0b1933;
    --sb-bd:       rgba(255,255,255,0.065);
    --sb-text:     rgba(255,255,255,0.68);
    --sb-hover:    rgba(255,255,255,0.055);
    --sb-act-bg:   rgba(214,167,0,0.13);
    --sb-act-clr:  #f0c040;
    --sb-act-bd:   rgba(214,167,0,0.45);
    --sb-w:        260px;
    --tb-h:        62px;
    --ink:         #0f2240;
    --ink-2:       #35557f;
    --muted:       #64748b;
    --line:        rgba(15,34,64,0.085);
    --surface:     #f0f4fa;
    --card:        #ffffff;
    --gold:        #d6a700;
    --blue:        #2563eb;
    --red:         #dc2626;
    --green:       #059669;
    --orange:      #d97706;
    --radius:      13px;
    --shadow:      0 2px 14px rgba(15,34,64,0.07);
    --shadow-md:   0 6px 28px rgba(15,34,64,0.10);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
body {
    font-family: 'Cairo', sans-serif;
    background: var(--surface);
    color: var(--ink);
    direction: rtl;
    font-size: 14.5px;
    line-height: 1.65;
}
a { text-decoration: none; }

/* ═══════════════════════════ LAYOUT ═══════════════════════════ */
.layout {
    display: flex;
    flex-direction: row; /* in RTL this keeps the first item (sidebar) on the right */
    height: 100vh;
    overflow: hidden;
}

/* ═══════════════════════════ SIDEBAR ═══════════════════════════ */
.sidebar {
    width: var(--sb-w);
    flex-shrink: 0;
    background: var(--sb-bg);
    height: 100vh;
    display: flex;
    flex-direction: column;
    border-left: 1px solid var(--sb-bd);
    z-index: 100;
    position: relative;
}
.sb-brand {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 20px 16px 17px;
    border-bottom: 1px solid var(--sb-bd);
    flex-shrink: 0;
}
.sb-icon-wrap {
    width: 38px; height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, #1e3f6f, #2563eb);
    color: #d6a700;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.95rem; flex-shrink: 0;
}
.sb-brand-name { font-weight: 800; font-size: 0.92rem; color: #fff; letter-spacing: 0.2px; }
.sb-brand-sub  { font-size: 0.7rem; color: var(--sb-text); }
.sb-nav {
    flex: 1;
    padding: 10px 7px;
    overflow-y: auto;
    display: flex; flex-direction: column; gap: 1px;
}
.sb-nav::-webkit-scrollbar { width: 4px; }
.sb-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
.sb-section-label {
    font-size: 0.65rem; font-weight: 700;
    letter-spacing: 1.1px; text-transform: uppercase;
    color: rgba(255,255,255,0.25);
    padding: 10px 11px 4px;
}
.sb-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 9px;
    color: var(--sb-text); font-weight: 600; font-size: 0.86rem;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
    border: 1px solid transparent; cursor: pointer;
}
.sb-item:hover { background: var(--sb-hover); color: rgba(255,255,255,0.92); }
.sb-item.active {
    background: var(--sb-act-bg);
    color: var(--sb-act-clr);
    border-color: var(--sb-act-bd);
}
.sb-item i { width: 17px; text-align: center; flex-shrink: 0; font-size: 0.87rem; }
.sb-item span.lbl { flex: 1; }
.sb-badge {
    background: #dc2626; color: #fff;
    border-radius: 999px; padding: 2px 7px;
    font-size: 0.68rem; font-weight: 700; min-width: 19px; text-align: center;
}
.sb-footer {
    padding: 7px;
    border-top: 1px solid var(--sb-bd);
    flex-shrink: 0;
}
.sb-item.logout { color: rgba(252,165,165,0.7); }
.sb-item.logout:hover { background: rgba(220,38,38,0.1); color: #fca5a5; }

/* ═══════════════════════════ MAIN WRAP ═══════════════════════════ */
.main-wrap {
    flex: 1; min-width: 0;
    display: flex; flex-direction: column;
    height: 100vh; overflow: hidden;
}

/* ═══════════════════════════ TOPBAR ═══════════════════════════ */
.topbar {
    height: var(--tb-h);
    background: #fff;
    border-bottom: 1px solid var(--line);
    display: flex; align-items: center; gap: 12px;
    padding: 0 24px; flex-shrink: 0;
    box-shadow: 0 1px 5px rgba(15,34,64,0.04);
    z-index: 10;
}
.tb-toggle {
    display: none; background: none; border: none;
    font-size: 1.1rem; color: var(--ink-2);
    cursor: pointer; padding: 7px 9px; border-radius: 8px;
}
.tb-toggle:hover { background: var(--surface); }
.tb-title { flex: 1; font-size: 0.97rem; font-weight: 700; color: var(--ink); }
.tb-user { display: flex; align-items: center; gap: 9px; }
.tb-av {
    width: 34px; height: 34px; border-radius: 50%;
    background: linear-gradient(135deg, #0f2240, #2563eb);
    color: #d6a700; display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 0.9rem; flex-shrink: 0;
}
.tb-name { font-weight: 600; color: var(--ink-2); font-size: 0.83rem; }

/* ═══════════════════════════ CONTENT ═══════════════════════════ */
.content { flex: 1; overflow-y: auto; padding: 26px; }

/* ═══════════════════════════ PAGE HEADER ═══════════════════════════ */
.phead {
    display: flex; align-items: flex-start;
    justify-content: space-between; gap: 14px;
    flex-wrap: wrap; margin-bottom: 22px;
}
.phead h2 { font-size: 1.3rem; font-weight: 800; margin-bottom: 3px; }
.phead .sub { color: var(--muted); font-size: 0.83rem; }
.phead-right { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }

/* ═══════════════════════════ STAT GRID ═══════════════════════════ */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(175px, 1fr));
    gap: 13px; margin-bottom: 22px;
}
.stat-card {
    background: #fff; border: 1px solid var(--line);
    border-radius: var(--radius); padding: 17px 18px;
    box-shadow: var(--shadow);
}
.stat-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.stat-ico {
    width: 42px; height: 42px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; color: #fff; flex-shrink: 0;
}
.stat-val { font-size: 1.65rem; font-weight: 800; line-height: 1; }
.stat-lbl { font-size: 0.8rem; color: var(--muted); margin-top: 3px; }
.stat-foot { font-size: 0.73rem; font-weight: 600; margin-top: 6px; color: var(--muted); border-top: 1px solid var(--line); padding-top: 6px; }
.stat-foot.up   { color: var(--green); }
.stat-foot.warn { color: var(--orange); }

/* ═══════════════════════════ CARD ═══════════════════════════ */
.card {
    background: #fff; border: 1px solid var(--line);
    border-radius: var(--radius); box-shadow: var(--shadow);
}
.card + .card { margin-top: 18px; }
.card-hd {
    padding: 16px 20px; border-bottom: 1px solid var(--line);
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
}
.card-hd-title { font-size: 0.93rem; font-weight: 700; }
.card-body { padding: 20px; }
.card-body-np { }

/* ═══════════════════════════ TABLE ═══════════════════════════ */
.tbl-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
thead th {
    background: var(--surface); padding: 10px 14px;
    text-align: right; font-size: 0.78rem; font-weight: 700;
    color: var(--ink-2); border-bottom: 2px solid var(--line);
    white-space: nowrap;
}
tbody td {
    padding: 11px 14px; border-bottom: 1px solid var(--line);
    font-size: 0.86rem; vertical-align: middle;
}
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: #fafbfd; }

/* ═══════════════════════════ BADGES ═══════════════════════════ */
.badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 9px; border-radius: 999px;
    font-size: 0.72rem; font-weight: 700; white-space: nowrap;
}
.bg-green  { background: rgba(5,150,105,.1);    color: #059669; }
.bg-red    { background: rgba(220,38,38,.1);     color: #dc2626; }
.bg-orange { background: rgba(217,119,6,.1);     color: #d97706; }
.bg-blue   { background: rgba(37,99,235,.1);     color: #2563eb; }
.bg-gray   { background: rgba(100,116,139,.12);  color: #64748b; }
.bg-gold   { background: rgba(214,167,0,.12);    color: #b8900a; }

/* ═══════════════════════════ BUTTONS ═══════════════════════════ */
.btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 17px; border-radius: 9px;
    font-family: inherit; font-size: 0.85rem; font-weight: 700;
    cursor: pointer; border: none; transition: all 0.16s;
    white-space: nowrap;
}
.btn-primary { background: #0f2240; color: #fff; }
.btn-primary:hover { background: #1a3a5c; color: #fff; }
.btn-gold    { background: #d6a700; color: #fff; }
.btn-gold:hover { background: #b89008; }
.btn-danger  { background: #dc2626; color: #fff; }
.btn-danger:hover { background: #b91c1c; }
.btn-success { background: #059669; color: #fff; }
.btn-success:hover { background: #047857; }
.btn-orange  { background: #d97706; color: #fff; }
.btn-orange:hover { background: #b45309; }
.btn-ghost   { background: transparent; color: var(--ink-2); border: 1px solid var(--line); }
.btn-ghost:hover { background: var(--surface); }
.btn-sm { padding: 5px 11px; font-size: 0.77rem; border-radius: 7px; }
.btn-icon { width: 32px; height: 32px; padding: 0; justify-content: center; border-radius: 8px; }

/* ═══════════════════════════ FORM ELEMENTS ═══════════════════════════ */
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-weight: 600; font-size: 0.83rem; margin-bottom: 5px; color: var(--ink-2); }
.form-ctrl {
    width: 100%; padding: 9px 12px;
    border: 1px solid var(--line); border-radius: 8px;
    font-family: inherit; font-size: 0.88rem; color: var(--ink);
    background: var(--surface); outline: none;
    transition: border-color 0.16s, background 0.16s;
}
.form-ctrl:focus { border-color: var(--blue); background: #fff; }
textarea.form-ctrl { resize: vertical; min-height: 80px; }
.form-hint { font-size: 0.75rem; color: var(--muted); margin-top: 4px; }

/* ═══════════════════════════ FILTER BAR ═══════════════════════════ */
.filter-bar {
    display: flex; align-items: center; gap: 9px; flex-wrap: wrap;
    padding: 13px 18px; border-bottom: 1px solid var(--line);
    background: #fafbfd;
}
.input-icon-wrap { position: relative; }
.input-icon-wrap i { position: absolute; right: 11px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 0.85rem; pointer-events: none; }
.input-icon-wrap input { padding-right: 32px; }
.form-ctrl-sm {
    padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px;
    font-family: inherit; font-size: 0.83rem; color: var(--ink);
    background: #fff; outline: none; cursor: pointer;
}
.form-ctrl-sm:focus { border-color: var(--blue); }

/* ═══════════════════════════ TABS ═══════════════════════════ */
.tabs {
    display: flex; gap: 0;
    border-bottom: 2px solid var(--line);
    margin-bottom: 20px;
}
.tab-btn {
    padding: 10px 18px; border: none; background: none;
    font-family: inherit; font-size: 0.86rem; font-weight: 600;
    color: var(--muted); cursor: pointer;
    border-bottom: 2px solid transparent; margin-bottom: -2px;
    transition: color 0.15s, border-color 0.15s;
}
.tab-btn:hover { color: var(--ink); }
.tab-btn.active { color: var(--blue); border-bottom-color: var(--blue); }
.tab-pane { display: none; }
.tab-pane.active { display: block; }

/* ═══════════════════════════ ALERT ═══════════════════════════ */
.alert {
    padding: 11px 15px; border-radius: 9px; font-size: 0.85rem;
    display: flex; align-items: flex-start; gap: 9px;
    margin-bottom: 14px; border: 1px solid transparent;
}
.alert i { margin-top: 2px; flex-shrink: 0; }
.alert-info    { background: rgba(37,99,235,0.07);  color: #1d4ed8; border-color: rgba(37,99,235,0.14); }
.alert-success { background: rgba(5,150,105,0.07);  color: #065f46; border-color: rgba(5,150,105,0.14); }
.alert-warning { background: rgba(217,119,6,0.07);  color: #92400e; border-color: rgba(217,119,6,0.14); }
.alert-danger  { background: rgba(220,38,38,0.07);  color: #991b1b; border-color: rgba(220,38,38,0.14); }

/* ═══════════════════════════ EMPTY STATE ═══════════════════════════ */
.empty-state {
    text-align: center; padding: 50px 20px; color: var(--muted);
}
.empty-state i { font-size: 2.2rem; margin-bottom: 12px; display: block; opacity: 0.3; }
.empty-state p { font-size: 0.88rem; }

/* ═══════════════════════════ COMING SOON BANNER ═══════════════════════════ */
.coming-banner {
    background: linear-gradient(135deg, rgba(37,99,235,0.06), rgba(214,167,0,0.06));
    border: 1px dashed rgba(37,99,235,0.25);
    border-radius: 12px; padding: 36px; text-align: center; color: var(--ink-2);
}
.coming-banner i { font-size: 2rem; color: var(--blue); opacity: 0.6; margin-bottom: 12px; display: block; }
.coming-banner h3 { font-size: 1.05rem; margin-bottom: 6px; }
.coming-banner p { font-size: 0.85rem; color: var(--muted); }

/* ═══════════════════════════ GRID UTILS ═══════════════════════════ */
.g2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.g3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; }
.g4 { display: grid; grid-template-columns: repeat(4,1fr); gap: 13px; }
.flex { display: flex; align-items: center; gap: 8px; }
.mt-4 { margin-top: 4px; }
.mt-16 { margin-top: 16px; }
.mt-20 { margin-top: 20px; }
.text-muted { color: var(--muted); font-size: 0.82rem; }

/* ═══════════════════════════ OVERLAY & MOBILE ═══════════════════════════ */
.sb-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.48); z-index: 150;
}
@media (max-width: 940px) {
    .sidebar {
        position: fixed; right: calc(-1 * var(--sb-w));
        height: 100%; transition: right 0.27s; z-index: 200;
    }
    .sidebar.open { right: 0; }
    .sb-overlay.show { display: block; }
    .tb-toggle { display: flex; }
    .content { padding: 18px; }
    .g2, .g3, .g4 { grid-template-columns: 1fr 1fr; }
    .stat-grid { grid-template-columns: repeat(2,1fr); }
}
@media (max-width: 500px) {
    .g2, .g3, .g4, .stat-grid { grid-template-columns: 1fr; }
    .phead { flex-direction: column; }
}
</style>
</head>
<body>
<div class="layout">

<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar" id="adminSidebar">
    <div class="sb-brand">
        <div class="sb-icon-wrap"><i class="fas fa-user-tie"></i></div>
        <div>
            <div class="sb-brand-name"> EaJaz Super Admin</div>
            <div class="sb-brand-sub">لوحة الإدارة العليا</div>
        </div>
    </div>

    <nav class="sb-nav">
        <div class="sb-section-label">القائمة الرئيسية</div>
        <?php foreach ($_admin_nav as $_ni):
            $_active = (isset($current_page) && $current_page === $_ni['slug']);
        ?>
        <a href="<?php echo e(super_admin_url($_ni['url'])); ?>"
           class="sb-item <?php echo $_active ? 'active' : ''; ?>">
            <i class="fas <?php echo e($_ni['icon']); ?>"></i>
            <span class="lbl"><?php echo e($_ni['label']); ?></span>
            <?php if (!empty($_ni['badge']) && $_ni['badge'] > 0): ?>
                <span class="sb-badge"><?php echo intval($_ni['badge']); ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sb-footer">
        <a href="<?php echo e(super_admin_url('logout.php')); ?>" class="sb-item logout">
            <i class="fas fa-right-from-bracket"></i>
            <span class="lbl">تسجيل الخروج</span>
        </a>
    </div>
</aside>

<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<!-- ═══ MAIN WRAP ═══ -->
<div class="main-wrap">
    <header class="topbar">
        <button class="tb-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        <div class="tb-title"><?php echo e($page_title ?? ''); ?></div>
        <div class="tb-user">
            <div class="tb-av"><?php echo mb_substr($admin['name'] ?? 'A', 0, 1, 'UTF-8'); ?></div>
            <span class="tb-name"><?php echo e($admin['name'] ?? ''); ?></span>
        </div>
    </header>

    <main class="content">
