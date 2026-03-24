<?php
require_once __DIR__ . '/../includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'إدارة الصلاحيات والأدوار';
$current_page = 'permissions';

require_once __DIR__ . '/../includes/layout_head.php';
?>

<style>
.page-shell {
    background: linear-gradient(135deg, #f5f7fa 0%, #f0f2f5 100%);
    min-height: calc(100vh - 100px);
    padding: 2rem;
}

.page-header {
    margin-bottom: 3rem;
}

.page-header h2 {
    color: var(--navy);
    font-weight: 700;
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.page-header p {
    color: #666;
    font-size: 1.1rem;
}

.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.permission-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(12, 28, 62, 0.08);
    overflow: hidden;
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
}

.permission-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(12, 28, 62, 0.15);
}

.permission-card-header {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-m) 100%);
    color: white;
    padding: 2rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.permission-card-icon {
    font-size: 2.5rem;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.15);
}

.permission-card.roles .permission-card-icon {
    background: linear-gradient(135deg, rgba(232, 184, 0, 0.3), rgba(37, 99, 235, 0.2));
}

.permission-card.modules .permission-card-icon {
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.3), rgba(13, 148, 136, 0.2));
}

.permission-card.detailed .permission-card-icon {
    background: linear-gradient(135deg, rgba(13, 148, 136, 0.3), rgba(232, 184, 0, 0.2));
}

.permission-card-title {
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 0.3rem;
}

.permission-card-subtitle {
    font-size: 0.9rem;
    opacity: 0.9;
}

.permission-card-body {
    padding: 1.5rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.permission-card-desc {
    color: #666;
    font-size: 0.95rem;
    margin-bottom: 1.5rem;
    flex: 1;
    line-height: 1.6;
}

.permission-card-features {
    list-style: none;
    padding: 0;
    margin-bottom: 1.5rem;
    color: #555;
    font-size: 0.9rem;
}

.permission-card-features li {
    margin-bottom: 0.5rem;
    padding-left: 1.5rem;
    position: relative;
}

.permission-card-features li:before {
    content: "✓";
    position: absolute;
    left: 0;
    color: var(--teal);
    font-weight: 700;
}

.permission-card-footer {
    border-top: 1px solid #e0e0e0;
    padding-top: 1rem;
}

.permission-card-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: linear-gradient(135deg, var(--blue) 0%, #1d4ed8 100%);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    width: 100%;
    text-align: center;
    justify-content: center;
}

.permission-card-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.hero-section {
    background: linear-gradient(130deg, var(--navy) 0%, var(--navy-m) 55%, var(--navy-l) 100%);
    border-radius: 12px;
    padding: 2rem;
    color: white;
    margin-bottom: 3rem;
    position: relative;
    overflow: hidden;
}

.hero-section::before {
    content: "";
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px);
    background-size: 18px 18px;
    pointer-events: none;
}

.hero-content {
    position: relative;
    z-index: 1;
}

.hero-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 999px;
    background: rgba(232, 184, 0, 0.16);
    border: 1px solid rgba(232, 184, 0, 0.36);
    color: #ffd740;
    font-size: 0.85rem;
    font-weight: 700;
    margin-bottom: 1rem;
    width: fit-content;
}

.hero-title {
    font-size: 1.8rem;
    font-weight: 900;
    margin-bottom: 0.5rem;
}

.hero-desc {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.84);
    max-width: 600px;
}

@media (max-width: 768px) {
    .permissions-grid {
        grid-template-columns: 1fr;
    }

    .page-header h2 {
        font-size: 1.5rem;
    }

    .permission-card-header {
        flex-direction: column;
        text-align: center;
    }

    .hero-title {
        font-size: 1.3rem;
    }
}
</style>

<div class="page-shell">
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="hero-content">
            <div class="hero-kicker">
                <i class="fas fa-shield-halved"></i> مركز التحكم
            </div>
            <h1 class="hero-title">إدارة الصلاحيات والأدوار</h1>
            <p class="hero-desc">
                قم بإدارة الأدوار والصفحات والصلاحيات المختلفة للنظام. تحكم كامل على من يمكنه الوصول إلى ماذا والقيام بماذا.
            </p>
        </div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <h2>اختر الإدارة المطلوبة</h2>
        <p>انقر على أي من الخيارات أدناه لبدء الإدارة</p>
    </div>

    <!-- Permissions Grid -->
    <div class="permissions-grid">
        <!-- الأدوار والرتب -->
        <a href="roles.php" class="permission-card roles">
            <div class="permission-card-header">
                <div class="permission-card-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div>
                    <div class="permission-card-title">الأدوار والرتب</div>
                    <div class="permission-card-subtitle">إدارة الأدوار الأساسية</div>
                </div>
            </div>
            <div class="permission-card-body">
                <p class="permission-card-desc">
                    أنشئ وعدّل أدواراً جديدة مثل مدير المشاريع أو مدير المستخدمين وحدد الهرمية بينها.
                </p>
                <ul class="permission-card-features">
                    <li>إنشاء أدوار جديدة</li>
                    <li>تحديد الهرمية بين الأدوار</li>
                    <li>تفعيل وتعطيل الأدوار</li>
                    <li>حذف الأدوار غير المستخدمة</li>
                </ul>
                <div class="permission-card-footer">
                    <div class="permission-card-link">
                        <i class="fas fa-arrow-left"></i> إدارة الأدوار
                    </div>
                </div>
            </div>
        </a>

        <!-- الصفحات والمديولات -->
        <a href="modules.php" class="permission-card modules">
            <div class="permission-card-header">
                <div class="permission-card-icon">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div>
                    <div class="permission-card-title">الصفحات والمديولات</div>
                    <div class="permission-card-subtitle">جميع صفحات النظام</div>
                </div>
            </div>
            <div class="permission-card-body">
                <p class="permission-card-desc">
                    أضف صفحات جديدة للنظام وحدد الدور المسؤول عن كل صفحة مع أيقونتها في الشريط الجانبي.
                </p>
                <ul class="permission-card-features">
                    <li>إضافة صفحات جديدة</li>
                    <li>ربط الصفحات بالأدوار</li>
                    <li>اختيار الأيقونات</li>
                    <li>تحديد روابط المشاريع</li>
                </ul>
                <div class="permission-card-footer">
                    <div class="permission-card-link">
                        <i class="fas fa-arrow-left"></i> إدارة الصفحات
                    </div>
                </div>
            </div>
        </a>

        <!-- الصلاحيات المفصلة -->
        <a href="role_permissions.php" class="permission-card detailed">
            <div class="permission-card-header">
                <div class="permission-card-icon">
                    <i class="fas fa-lock-open"></i>
                </div>
                <div>
                    <div class="permission-card-title">الصلاحيات المفصلة</div>
                    <div class="permission-card-subtitle">تحكم دقيق بالصلاحيات</div>
                </div>
            </div>
            <div class="permission-card-body">
                <p class="permission-card-desc">
                    اختر الدور وحدد بدقة ما يمكن لكل دور فعله في كل صفحة (عرض، إضافة، تعديل، حذف).
                </p>
                <ul class="permission-card-features">
                    <li>منح صلاحيات العرض</li>
                    <li>منح صلاحيات الإضافة</li>
                    <li>منح صلاحيات التعديل</li>
                    <li>منح صلاحيات الحذف</li>
                </ul>
                <div class="permission-card-footer">
                    <div class="permission-card-link">
                        <i class="fas fa-arrow-left"></i> إدارة الصلاحيات
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_foot.php'; ?>
