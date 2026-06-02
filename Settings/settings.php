<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

// ✅ التحقق من الصلاحيات قبل أي HTML
include '../includes/permissions_helper.php';
include '../config.php';

$perms = get_page_permissions($conn, 'Settings/settings.php');

if (!$perms['can_view']) {
    header('Location: ../main/dashboard.php?msg=' . urlencode('❌ لا توجد صلاحية للوصول لهذه الصفحة'));
    exit();
}

$page_title = "الإعدادات";
include("../inheader.php");
include('../insidebar.php');
?>

<link rel="stylesheet" href="../assets/css/admin-style.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">

<style>
    /* ── الهيكل العام ── */
    .settings-shell {
        display: grid;
        gap: 18px;
    }

    /* ── بطاقة الترحيب العلوية ── */
    .settings-hero {
        position: relative;
        background: linear-gradient(135deg, var(--s0), #2d200a);
        border: 1px solid rgba(247, 147, 26, 0.35);
        border-radius: var(--rl);
        padding: 22px;
        color: #fff;
        box-shadow: var(--sh2);
        overflow: hidden;
    }

    /* نقاط الخلفية الزخرفية */
    .settings-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background-image: radial-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px);
        background-size: 18px 18px;
        pointer-events: none;
    }

    /* دائرة ذهبية زخرفية */
    .settings-hero::after {
        content: "";
        position: absolute;
        left: -30px;
        top: -40px;
        width: 190px;
        height: 190px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(232, 184, 0, 0.35) 0%, transparent 70%);
    }

    .settings-hero-content {
        position: relative;
        z-index: 1;
        display: grid;
        gap: 8px;
    }

    /* شارة التصنيف */
    .settings-kicker {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        width: fit-content;
        padding: 4px 12px;
        border-radius: 999px;
        background: rgba(232, 184, 0, 0.16);
        border: 1px solid rgba(232, 184, 0, 0.36);
        color: #ffe7b5;
        font-size: 0.72rem;
        font-weight: 700;
    }

    .settings-hero h2 {
        margin: 0;
        font-size: 1.45rem;
        font-weight: 900;
    }

    .settings-hero p {
        margin: 0;
        font-size: 0.9rem;
        color: rgba(255, 247, 230, 0.9);
        max-width: 760px;
    }

    /* ── قسم الإعدادات ── */
    .settings-section {
        background: var(--s1);
        border: 1.5px solid var(--bdr);
        border-radius: var(--rl);
        padding: 18px;
        box-shadow: var(--sh);
        display: grid;
        gap: 14px;
    }

    .settings-section-title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        color: var(--t1);
        font-size: 1rem;
        font-weight: 800;
    }

    .settings-section-title i {
        color: var(--or);
    }

    /* ── شبكة البطاقات ── */
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 14px;
    }

    /* بطاقة إعداد واحدة */
    .settings-card {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        background: linear-gradient(180deg, var(--s1) 0%, #fffbf5 100%);
        border: 1.5px solid var(--bdr);
        border-radius: 14px;
        text-decoration: none;
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        position: relative;
        overflow: hidden;
    }

    /* شريط ذهبي عند التحويم */
    .settings-card::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, var(--or), var(--or2));
        opacity: 0;
        transition: opacity .2s ease;
    }

    .settings-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--sh2);
        border-color: rgba(247, 147, 26, 0.45);
    }

    .settings-card:hover::before {
        opacity: 1;
    }

    /* أيقونة البطاقة */
    .settings-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .settings-icon.account {
        background: rgba(247, 147, 26, 0.16);
        color: var(--or2);
    }

    .settings-icon.admin {
        background: rgba(26, 18, 8, 0.09);
        color: var(--s0);
    }

    /* نص البطاقة */
    .settings-meta h4 {
        margin: 0 0 4px;
        font-size: 0.95rem;
        font-weight: 800;
        color: var(--t1);
    }

    .settings-meta p {
        margin: 0;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--t2);
        line-height: 1.7;
    }

    /* سهم التنقل */
    .settings-card-arrow {
        margin-right: auto;
        color: var(--t3);
        font-size: 0.9rem;
        align-self: center;
        transition: transform .2s ease, color .2s ease;
    }

    .settings-card:hover .settings-card-arrow {
        transform: translateX(-4px);
        color: var(--t1);
    }

    /* ── استجابة الشاشات الصغيرة ── */
    @media (max-width: 768px) {
        .settings-hero {
            padding: 18px;
        }

        .settings-hero h2 {
            font-size: 1.2rem;
        }

        .settings-section {
            padding: 14px;
        }
    }
</style>

<div class="main ems-unified-page-shell settings-main">


    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'الإعدادات';
    $header_icon    = 'fas fa-gear';
    $header_actions = array();
    $header_back    = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="settings-shell">

        <!-- بطاقة الترحيب -->
        <div class="settings-hero">
            <div class="settings-hero-content">
                <span class="settings-kicker">
                    <i class="fas fa-shield-halved"></i> مركز التحكم
                </span>
                <h2 style="color: #fff;">إدارة إعدادات الحساب والنظام</h2>
                <p>اختر القسم المطلوب لإدارة كلمة المرور أو إعدادات الصلاحيات والموديولات بنفس تصميم صفحات الإدارة داخل
                    النظام.</p>
            </div>
        </div>

        <!-- قسم إعدادات الحساب -->
        <div class="settings-section">
            <h3 class="settings-section-title">
                <i class="fas fa-user-cog"></i> إعدادات الحساب
            </h3>
            <div class="settings-grid">
                <a href="change_password.php" class="settings-card">
                    <div class="settings-icon account"><i class="fas fa-key"></i></div>
                    <div class="settings-meta">
                        <h4>تغيير كلمة المرور</h4>
                        <p>تحديث كلمة مرور حسابك مع الحفاظ على أمان الوصول للنظام.</p>
                    </div>
                    <i class="fas fa-arrow-left settings-card-arrow"></i>
                </a>
            </div>
        </div>

    </div>
</div>
