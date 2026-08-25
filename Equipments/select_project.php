<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
$page_title = "إيكوبيشن | اختيار المشروع";
include '../config.php'; // config أولًا (يعرّف $conn/البوابة) — كان بعد الترويسة فيُفشِلها (500 قائم)
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include("../inheader.php");
include("../insidebar.php");
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<style>
    @import url('/ems/assets/css/local-fonts.css');

    :root {
        --primary-color: var(--c-01072a, #01072a);
        --secondary-color: var(--c-e2ae03, #e2ae03);
        --gold-color: var(--c-debf0f, #debf0f);
        --light-color: var(--c-f5f5f5, #f5f5f5);
        --shadow-color: var(--c-rgba00001, rgba(0, 0, 0, 0.1));
    }
    
    * {
        font-family: 'Cairo', sans-serif;
    }
    
    body {
        background: var(--light-color);
    }
    
    .main {
        padding: 2rem;
        background: var(--light-color);
        min-height: 100vh;
    }
    
    .main h2 {
        color: var(--primary-color);
        font-size: 2.5rem;
        font-weight: 900;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 15px;
        text-align: center;
        justify-content: center;
    }

    .main h2 i {
        color: var(--secondary-color);
        font-size: 2.5rem;
    }
    
    .page-description {
        text-align: center;
        color: var(--c-6c757d, #6c757d);
        font-size: 1.1rem;
        margin-bottom: 3rem;
        font-weight: 500;
    }
    
    .projects-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 2rem;
        margin-top: 2rem;
    }
    
    .project-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 10px 40px var(--shadow-color);
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        border: 3px solid transparent;
        position: relative;
        overflow: hidden;
    }
    
    .project-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(90deg, var(--secondary-color), var(--gold-color));
    }
    
    .project-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 50px var(--c-e2ae03-30, rgba(226, 174, 3, 0.3));
        border-color: var(--secondary-color);
    }
    
    .project-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--secondary-color), var(--gold-color));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        font-size: 2.5rem;
        color: var(--primary-color);
        box-shadow: 0 5px 15px var(--c-e2ae03-40, rgba(226, 174, 3, 0.4));
    }
    
    .project-name {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--primary-color);
        text-align: center;
        margin: 0;
    }
    
    .project-code {
        font-size: 1rem;
        color: var(--c-6c757d, #6c757d);
        text-align: center;
        font-family: monospace;
        background: var(--c-e2ae03-10, rgba(226, 174, 3, 0.1));
        padding: 8px 15px;
        border-radius: 10px;
        font-weight: 600;
    }
    
    .project-details {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    
    .project-detail-item {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--c-495057, #495057);
        font-size: 0.95rem;
    }
    
    .project-detail-item i {
        color: var(--secondary-color);
        width: 20px;
    }
    
    .action-button {
        background: linear-gradient(135deg, var(--secondary-color), var(--gold-color));
        color: var(--primary-color);
        padding: 12px 25px;
        border-radius: 10px;
        text-align: center;
        font-weight: 700;
        margin-top: auto;
        transition: all 0.3s ease;
        border: none;
        font-size: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .action-button:hover {
        transform: scale(1.05);
        box-shadow: 0 5px 15px var(--c-e2ae03-40, rgba(226, 174, 3, 0.4));
    }
    
    .no-projects {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--c-6c757d, #6c757d);
        font-size: 1.2rem;
    }
    
    .no-projects i {
        font-size: 4rem;
        color: var(--secondary-color);
        margin-bottom: 1rem;
        display: block;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .project-card {
        animation: fadeInUp 0.5s ease;
    }
    
    @media (max-width: 768px) {
        .projects-grid {
            grid-template-columns: 1fr;
        }
        
        .main h2 {
            font-size: 1.8rem;
        }
    }
</style>

<div class="main">
<?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ — الشاشةُ كانت بلا رأسٍ معلَن. */
$header_icon = 'fas fa-window-maximize';
$header_title_html = htmlspecialchars('Select Project', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
if (function_exists('ems_states_bundle')) {
    echo ems_states_bundle('لا مشروع نشطا متاحا لإدارة معداته',
                           'فعل مشروعا من شاشة المشاريع أو راجع صلاحية إسنادك للمشاريع');
}
?>

    <h2>
        <i class="fas fa-project-diagram"></i>
        اختر المشروع لإدارة المعدات
    </h2>
    
    <p class="page-description">
        <i class="fas fa-info-circle"></i>
        اختر المشروع الذي ترغب في إدارة معداته
    </p>

    <div class="projects-grid">
        <?php
        // المشاريع النشطة معزولةً بالشركة عبر البوابة (يُصلح تسرّبًا كامنًا).
        // ملاحظة صيانة: عدّادا «المعدات/المناجم» كانا يعتمدان على schema غير موجود
        // (جدول mines محذوف + عمود equipments.project_id غير موجود) فيُفشِلان الاستعلام
        // (HTTP 500 قبل الهجرة). صُفِّرا حتى يُعاد تعريف رابط المعدة↔المشروع (عبر operations).
        $projects = ems_tenant_db()->select('project', array('whereRaw' => "status = '1'", 'orderBy' => 'name ASC'));

        if (!empty($projects)) {
            foreach ($projects as $project) {
                $project['equipments_count'] = 0;
                $project['mines_count'] = 0;
                ?>
                <a href="equipments.php?project_id=<?php echo $project['id']; ?>" class="project-card">
                    <div class="project-icon">
                        <i class="fas fa-hard-hat"></i>
                    </div>
                    
                    <h3 class="project-name"><?php echo htmlspecialchars($project['name']); ?></h3>
                    
                    <?php if (!empty($project['project_code'])) { ?>
                        <div class="project-code">
                            <i class="fas fa-barcode"></i> 
                            <?php echo htmlspecialchars($project['project_code']); ?>
                        </div>
                    <?php } ?>
                    
                    <div class="project-details">
                        <?php if (!empty($project['location'])) { ?>
                            <div class="project-detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($project['location']); ?></span>
                            </div>
                        <?php } ?>
                        
                        <div class="project-detail-item">
                            <i class="fas fa-mountain"></i>
                            <span><?php echo intval($project['mines_count']); ?> منجم</span>
                        </div>
                        
                        <div class="project-detail-item">
                            <i class="fas fa-cogs"></i>
                            <span><?php echo intval($project['equipments_count']); ?> معدة مسجلة</span>
                        </div>
                    </div>
                    
                    <div class="action-button">
                        <i class="fas fa-arrow-left"></i>
                        إدارة المعدات
                    </div>
                </a>
                <?php
            }
        } else {
            ?>
            <div class="no-projects">
                <i class="fas fa-folder-open"></i>
                <p>لا توجد مشاريع متاحة حاليا</p>
            </div>
            <?php
        }
        ?>
    </div>
</div>

</body>
</html>



