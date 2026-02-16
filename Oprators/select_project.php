<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
$page_title = "إيكوبيشن | اختيار المشروع للتشغيل";
include("../inheader.php");
include("../insidebar.php");
include '../config.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap');

    :root {
        --primary-color: #01072a;
        --secondary-color: #e2ae03;
        --gold-color: #debf0f;
        --light-color: #f5f5f5;
        --shadow-color: rgba(0, 0, 0, 0.1);
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
        color: #6c757d;
        font-size: 1.1rem;
        margin-bottom: 3rem;
        font-weight: 500;
    }
    
    .projects-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
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
        box-shadow: 0 15px 50px rgba(226, 174, 3, 0.3);
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
        box-shadow: 0 5px 15px rgba(226, 174, 3, 0.4);
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
        color: #6c757d;
        text-align: center;
        font-family: monospace;
        background: rgba(226, 174, 3, 0.1);
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
        color: #495057;
        font-size: 0.95rem;
    }
    
    .project-detail-item i {
        color: var(--secondary-color);
        width: 20px;
        text-align: center;
    }
    
    .stats-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 2px solid rgba(226, 174, 3, 0.2);
    }
    
    .stat-box {
        background: rgba(226, 174, 3, 0.05);
        padding: 10px;
        border-radius: 10px;
        text-align: center;
    }
    
    .stat-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--primary-color);
    }
    
    .stat-label {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 5px;
        font-weight: 600;
    }
    
    .no-projects {
        text-align: center;
        padding: 3rem;
        color: #6c757d;
        font-size: 1.2rem;
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 20px var(--shadow-color);
    }
    
    .no-projects i {
        font-size: 4rem;
        color: var(--secondary-color);
        margin-bottom: 1rem;
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
        animation: fadeInUp 0.5s ease backwards;
    }
    
    .project-card:nth-child(1) { animation-delay: 0.1s; }
    .project-card:nth-child(2) { animation-delay: 0.2s; }
    .project-card:nth-child(3) { animation-delay: 0.3s; }
    .project-card:nth-child(4) { animation-delay: 0.4s; }
    .project-card:nth-child(5) { animation-delay: 0.5s; }
    .project-card:nth-child(6) { animation-delay: 0.6s; }
</style>

<div class="main">
    <h2>
        <i class="fas fa-cogs"></i>
        اختر المشروع لإدارة التشغيل
    </h2>
    <p class="page-description">
        اختر المشروع الذي تريد إدارة تشغيل المعدات والآليات فيه
    </p>
    
    <div class="projects-grid">
        <?php
        // جلب جميع المشاريع النشطة مع إحصائيات
        $query = "SELECT 
                    p.id,
                    p.name,
                    p.project_code,
                    p.location,
                    (SELECT COUNT(*) FROM mines WHERE project_id = p.id AND status = 1) as mines_count,
                    (SELECT COUNT(*) FROM operations WHERE project_id = p.id AND status = 1) as operations_count,
                    (SELECT COUNT(*) FROM contracts c 
                     INNER JOIN mines m ON c.mine_id = m.id 
                     WHERE m.project_id = p.id AND c.status = 1) as contracts_count
                  FROM project p
                  WHERE p.status = 1
                  ORDER BY p.name ASC";
        
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) > 0) {
            while ($project = mysqli_fetch_assoc($result)) {
                $project_id = intval($project['id']);
                $project_name = htmlspecialchars($project['name']);
                $project_code = htmlspecialchars($project['project_code']);
                $location = htmlspecialchars($project['location']);
                $mines_count = intval($project['mines_count']);
                $operations_count = intval($project['operations_count']);
                $contracts_count = intval($project['contracts_count']);
                
                echo '<a href="oprators.php?project_id=' . $project_id . '" class="project-card">';
                echo '  <div class="project-icon">';
                echo '      <i class="fas fa-hard-hat"></i>';
                echo '  </div>';
                echo '  <h3 class="project-name">' . $project_name . '</h3>';
                
                if (!empty($project_code)) {
                    echo '  <div class="project-code">';
                    echo '      <i class="fas fa-barcode"></i> ' . $project_code;
                    echo '  </div>';
                }
                
                echo '  <div class="project-details">';
                
                if (!empty($location)) {
                    echo '      <div class="project-detail-item">';
                    echo '          <i class="fas fa-map-marker-alt"></i>';
                    echo '          <span>' . $location . '</span>';
                    echo '      </div>';
                }
                
                echo '  </div>';
                
                echo '  <div class="stats-row">';
                
                echo '      <div class="stat-box">';
                echo '          <div class="stat-value">' . $mines_count . '</div>';
                echo '          <div class="stat-label">⛰️ مناجم</div>';
                echo '      </div>';
                
                echo '      <div class="stat-box">';
                echo '          <div class="stat-value">' . $operations_count . '</div>';
                echo '          <div class="stat-label">⚙️ تشغيلات نشطة</div>';
                echo '      </div>';
                
                echo '      <div class="stat-box">';
                echo '          <div class="stat-value">' . $contracts_count . '</div>';
                echo '          <div class="stat-label">📄 عقود نشطة</div>';
                echo '      </div>';
                
                // حساب عدد المعدات المشغلة
                $equip_query = "SELECT COUNT(DISTINCT equipment) as equip_count 
                               FROM operations 
                               WHERE project_id = $project_id AND status = 1";
                $equip_result = mysqli_query($conn, $equip_query);
                $equip_row = mysqli_fetch_assoc($equip_result);
                $equip_count = intval($equip_row['equip_count']);
                
                echo '      <div class="stat-box">';
                echo '          <div class="stat-value">' . $equip_count . '</div>';
                echo '          <div class="stat-label">🚜 معدات مشغلة</div>';
                echo '      </div>';
                
                echo '  </div>';
                echo '</a>';
            }
        } else {
            echo '<div class="no-projects">';
            echo '  <i class="fas fa-folder-open"></i>';
            echo '  <p>لا توجد مشاريع متاحة حالياً</p>';
            echo '</div>';
        }
        ?>
    </div>
</div>

<script>
    // إضافة تأثير hover للكروت
    document.querySelectorAll('.project-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-10px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
</script>

</body>
</html>
