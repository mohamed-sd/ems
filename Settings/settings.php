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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

<style>
    /* ── الهيكل العام ── */
    .settings-shell {
        display: grid;
        gap: 18px;
    }

    /* ── بطاقة الترحيب العلوية ── */
    .settings-hero {
        position: relative;
        background: linear-gradient(130deg, var(--navy) 0%, var(--navy-m) 55%, var(--navy-l) 100%);
        border-radius: var(--radius-xl);
        padding: 22px;
        color: #fff;
        box-shadow: var(--shadow-lg);
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
        color: var(--gold-l);
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
        color: rgba(255, 255, 255, 0.84);
        max-width: 760px;
    }

    /* ── قسم الإعدادات ── */
    .settings-section {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 18px;
        box-shadow: var(--shadow-sm);
        display: grid;
        gap: 14px;
    }

    .settings-section-title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        color: var(--txt);
        font-size: 1rem;
        font-weight: 800;
    }

    .settings-section-title i {
        color: var(--gold);
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
        background: #fff;
        border: 1.5px solid var(--border);
        border-radius: 14px;
        text-decoration: none;
        transition: transform var(--ease), box-shadow var(--ease), border-color var(--ease);
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
        background: linear-gradient(180deg, var(--gold), var(--orange));
        opacity: 0;
        transition: opacity var(--ease);
    }

    .settings-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
        border-color: rgba(232, 184, 0, 0.35);
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
        background: var(--gold-soft);
        color: var(--gold);
    }

    .settings-icon.admin {
        background: var(--blue-soft);
        color: var(--blue);
    }

    /* نص البطاقة */
    .settings-meta h4 {
        margin: 0 0 4px;
        font-size: 0.95rem;
        font-weight: 800;
        color: var(--txt);
    }

    .settings-meta p {
        margin: 0;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--sub);
        line-height: 1.7;
    }

    /* سهم التنقل */
    .settings-card-arrow {
        margin-right: auto;
        color: var(--sub);
        font-size: 0.9rem;
        align-self: center;
        transition: transform var(--ease), color var(--ease);
    }

    .settings-card:hover .settings-card-arrow {
        transform: translateX(-4px);
        color: var(--txt);
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

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-gear"></i></div>
            الإعدادات
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="settings-shell">

        <!-- بطاقة الترحيب -->
        <div class="settings-hero">
            <div class="settings-hero-content">
                <span class="settings-kicker">
                    <i class="fas fa-shield-halved"></i> مركز التحكم
                </span>
                <h2>إدارة إعدادات الحساب والنظام</h2>
                <p>اختر القسم المطلوب لإدارة كلمة المرور أو إعدادات الصلاحيات والموديولات بنفس تصميم صفحات الإدارة داخل النظام.</p>
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