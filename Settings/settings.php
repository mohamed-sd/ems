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

$settings_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$settings_is_admin = ($settings_role === '-1');
$settings_user_name = isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : '';

$page_title = "الإعدادات";
include("../inheader.php");
include('../insidebar.php');
?>

<style>
    .settings-shell {
        display: grid;
        gap: 18px;
    }

    /* ── بطاقة الترحيب العلوية ── */
    .settings-hero {
        position: relative;
        background:  #ccc;
        border: 1px solid rgba(244, 197, 66, 0.32);
        border-radius: var(--rl, 14px);
        padding: 26px;
        color: #fff;
        box-shadow: var(--sh2);
        overflow: hidden;
    }

    .settings-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background-image: radial-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px);
        background-size: 18px 18px;
        pointer-events: none;
    }

    .settings-hero::after {
        content: "";
        position: absolute;
        left: -40px;
        top: -50px;
        width: 210px;
        height: 210px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(244, 197, 66, 0.35) 0%, transparent 70%);
        pointer-events: none;
    }

    .settings-hero-content {
        position: relative;
        z-index: 1;
        display: grid;
        gap: 8px;
    }

    .settings-kicker {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        width: fit-content;
        padding: 5px 13px;
        border-radius: 999px;
        background: rgba(244, 197, 66, 0.18);
        border: 1px solid rgba(244, 197, 66, 0.4);
        color: #ffe7b5;
        font-size: 0.74rem;
        font-weight: 800;
    }

    .settings-hero h2 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 900;
        color: #fff;
    }

    .settings-hero p {
        margin: 0;
        font-size: 0.9rem;
        color: rgba(255, 247, 230, 0.9);
        max-width: 760px;
        line-height: 1.7;
    }

    /* ── قسم الإعدادات ── */
    .settings-section {
        background: var(--s1, #fff);
        border: 1.5px solid var(--bdr, #D7DBE0);
        border-radius: var(--rl, 14px);
        padding: 18px;
        box-shadow: var(--sh);
        display: grid;
        gap: 14px;
    }

    .settings-section-title {
        display: flex;
        align-items: center;
        gap: 9px;
        margin: 0;
        color: var(--t1, #111);
        font-size: 1.02rem;
        font-weight: 800;
    }

    .settings-section-title i {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(244, 197, 66, 0.16);
        color: #b8860b;
        font-size: .92rem;
    }

    /* ── شبكة البطاقات ── */
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 14px;
    }

    .settings-card {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        background: linear-gradient(180deg, var(--s1, #fff) 0%, #fffdf8 100%);
        border: 1.5px solid var(--bdr, #D7DBE0);
        border-radius: 14px;
        text-decoration: none;
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        position: relative;
        overflow: hidden;
    }

    .settings-card::before {
        content: "";
        position: absolute;
        right: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, var(--primary-yellow, #F4C542), #D9AB32);
        opacity: 0;
        transition: opacity .2s ease;
    }

    .settings-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--sh2);
        border-color: rgba(244, 197, 66, 0.5);
    }

    .settings-card:hover::before {
        opacity: 1;
    }

    .settings-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        flex-shrink: 0;
    }

    .settings-icon.account {
        background: rgba(244, 197, 66, 0.18);
        color: #b8860b;
    }

    .settings-icon.admin {
        background: rgba(30, 58, 95, 0.1);
        color: #1E3A5F;
    }

    .settings-meta h4 {
        margin: 0 0 4px;
        font-size: 0.96rem;
        font-weight: 800;
        color: var(--t1, #111);
    }

    .settings-meta p {
        margin: 0;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--t3, #666E78);
        line-height: 1.7;
    }

    .settings-card-arrow {
        margin-left: auto;
        color: var(--t3, #9aa0a8);
        font-size: 0.9rem;
        align-self: center;
        transition: transform .2s ease, color .2s ease;
    }

    .settings-card:hover .settings-card-arrow {
        transform: translateX(-4px);
        color: var(--t1, #111);
    }

    @media (max-width: 768px) {
        .settings-hero {
            padding: 20px;
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
    $header_title   = 'الإعدادات';
    $header_icon    = 'fas fa-gear';
    $header_actions = array();
    $header_back    = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="settings-shell">

        <!-- بطاقة الترحيب -->
        <div class="settings-hero">
            <div class="settings-hero-content">
                <span class="settings-kicker">
                    <i class="fas fa-shield-halved"></i> مركز التحكم
                </span>
                <h2>إدارة إعدادات الحساب والنظام</h2>
                <p>اختر القسم المطلوب لإدارة حسابك الشخصي أو إعدادات النظام والصلاحيات، بنفس هوية وتصميم بقية صفحات النظام.</p>
            </div>
        </div>

        <!-- قسم إعدادات الحساب -->
        <div class="settings-section">
            <h3 class="settings-section-title">
                <i class="fas fa-user-cog"></i> إعدادات الحساب
            </h3>
            <div class="settings-grid">
                <a href="../main/profile.php" class="settings-card">
                    <div class="settings-icon account"><i class="fas fa-id-badge"></i></div>
                    <div class="settings-meta">
                        <h4>الملف الشخصي</h4>
                        <p>استعراض بياناتك الشخصية ومعلومات حسابك في النظام.</p>
                    </div>
                    <i class="fas fa-arrow-left settings-card-arrow"></i>
                </a>
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

        <?php if ($settings_is_admin): ?>
        <!-- قسم إدارة النظام (للإدارة العليا) -->
        <div class="settings-section">
            <h3 class="settings-section-title">
                <i class="fas fa-sliders"></i> إدارة النظام والصلاحيات
            </h3>
            <div class="settings-grid">
                <a href="role_permissions.php" class="settings-card">
                    <div class="settings-icon admin"><i class="fas fa-user-lock"></i></div>
                    <div class="settings-meta">
                        <h4>صلاحيات الأدوار</h4>
                        <p>التحكم في صلاحيات العرض والإضافة والتعديل والحذف لكل دور.</p>
                    </div>
                    <i class="fas fa-arrow-left settings-card-arrow"></i>
                </a>
                <a href="roles.php" class="settings-card">
                    <div class="settings-icon admin"><i class="fas fa-users-gear"></i></div>
                    <div class="settings-meta">
                        <h4>إدارة الأدوار</h4>
                        <p>إنشاء وتعديل الأدوار الوظيفية ومستوياتها داخل النظام.</p>
                    </div>
                    <i class="fas fa-arrow-left settings-card-arrow"></i>
                </a>
                <a href="modules.php" class="settings-card">
                    <div class="settings-icon admin"><i class="fas fa-table-cells-large"></i></div>
                    <div class="settings-meta">
                        <h4>الصفحات والموديولات</h4>
                        <p>إدارة صفحات النظام والموديولات المتاحة وربطها بالقوائم.</p>
                    </div>
                    <i class="fas fa-arrow-left settings-card-arrow"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

</body>

</html>
