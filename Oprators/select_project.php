<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
$page_title = "إيكوبيشن | اختيار المشروع للتشغيل";
include("../inheader.php");
include("../insidebar.php");

?>

<style>
    @import url('/ems/assets/css/local-fonts.css');

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

        // جلب المشاريع بشكل مرن وآمن مع اختلافات بنية قاعدة البيانات بين الإصدارات
        $current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
        $is_role10 = ($current_role === '10');
        $company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
        $user_project_id = $is_role10 ? intval($_SESSION['user']['project_id'] ?? 0) : 0;

        $project_scope_sql = '1=1';
        $project_has_company_id = function_exists('db_table_has_column') ? db_table_has_column($conn, 'project', 'company_id') : false;
        if ($company_id > 0) {
            if ($project_has_company_id) {
                $project_scope_sql .= " AND p.company_id = $company_id";
            } else {
                $project_client_column = (function_exists('db_table_has_column') && db_table_has_column($conn, 'project', 'client_id'))
                    ? 'client_id'
                    : 'company_client_id';
                $project_scope_sql .= " AND (
                    EXISTS (SELECT 1 FROM users su WHERE su.id = p.created_by AND su.company_id = $company_id)
                    OR EXISTS (
                        SELECT 1
                        FROM clients sc
                        INNER JOIN users scu ON scu.id = sc.created_by
                        WHERE sc.id = p.$project_client_column AND scu.company_id = $company_id
                    )
                )";
            }
        } else {
            // بدون company_id في الجلسة لا نعرض أي مشاريع لمنع تسرب بيانات بين الشركات
            $project_scope_sql .= ' AND 0 = 1';
        }
        if ($is_role10 && $user_project_id > 0) {
            $project_scope_sql .= " AND p.id = $user_project_id";
        }

        $query = "SELECT p.id, p.name, p.project_code, p.location
                  FROM project p
                  WHERE p.status = 1 AND $project_scope_sql
                  ORDER BY p.name ASC";

        $result = mysqli_query($conn, $query);

        $has_mines_project_id_col = function_exists('db_table_has_column') ? db_table_has_column($conn, 'mines', 'project_id') : false;
        $operations_project_col = null;
        if (function_exists('db_table_has_column')) {
            if (db_table_has_column($conn, 'operations', 'project_id')) {
                $operations_project_col = 'project_id';
            } elseif (db_table_has_column($conn, 'operations', 'project')) {
                $operations_project_col = 'project';
            }
        }

        $contracts_project_col = null;
        $contracts_has_mine_id = function_exists('db_table_has_column') ? db_table_has_column($conn, 'contracts', 'mine_id') : false;
        if (function_exists('db_table_has_column')) {
            if (db_table_has_column($conn, 'contracts', 'project_id')) {
                $contracts_project_col = 'project_id';
            } elseif (db_table_has_column($conn, 'contracts', 'project')) {
                $contracts_project_col = 'project';
            }
        }

        if ($result && mysqli_num_rows($result) > 0) {
            while ($project = mysqli_fetch_assoc($result)) {
                $project_id = intval($project['id']);
                $project_name = htmlspecialchars($project['name']);
                $project_code = htmlspecialchars($project['project_code']);
                $location = htmlspecialchars($project['location']);

                $mines_count = 0;
                if ($has_mines_project_id_col) {
                    $mines_q = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM mines WHERE project_id = $project_id AND status = 1");
                    if ($mines_q) {
                        $mines_row = mysqli_fetch_assoc($mines_q);
                        $mines_count = intval($mines_row['cnt'] ?? 0);
                    }
                }

                $operations_count = 0;
                if (!empty($operations_project_col)) {
                    $operations_q = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM operations WHERE $operations_project_col = $project_id AND status = 1");
                    if ($operations_q) {
                        $operations_row = mysqli_fetch_assoc($operations_q);
                        $operations_count = intval($operations_row['cnt'] ?? 0);
                    }
                }

                $contracts_count = 0;
                if (!empty($contracts_project_col)) {
                    $contracts_q = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM contracts WHERE $contracts_project_col = $project_id AND status = 1");
                    if ($contracts_q) {
                        $contracts_row = mysqli_fetch_assoc($contracts_q);
                        $contracts_count = intval($contracts_row['cnt'] ?? 0);
                    }
                } elseif ($contracts_has_mine_id && $has_mines_project_id_col) {
                    $contracts_q = mysqli_query($conn, "SELECT COUNT(*) AS cnt
                                                      FROM contracts c
                                                      INNER JOIN mines m ON c.mine_id = m.id
                                                      WHERE m.project_id = $project_id AND c.status = 1");
                    if ($contracts_q) {
                        $contracts_row = mysqli_fetch_assoc($contracts_q);
                        $contracts_count = intval($contracts_row['cnt'] ?? 0);
                    }
                }

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
                echo '          <div class="stat-label">عقود نشطة</div>';
                echo '      </div>';

                // حساب عدد المعدات المشغلة
                $equip_count = 0;
                if (!empty($operations_project_col)) {
                    $equip_query = "SELECT COUNT(DISTINCT equipment) AS equip_count
                                   FROM operations
                                   WHERE $operations_project_col = $project_id AND status = 1";
                    $equip_result = mysqli_query($conn, $equip_query);
                    if ($equip_result) {
                        $equip_row = mysqli_fetch_assoc($equip_result);
                        $equip_count = intval($equip_row['equip_count'] ?? 0);
                    }
                }

                echo '      <div class="stat-box">';
                echo '          <div class="stat-value">' . $equip_count . '</div>';
                echo '          <div class="stat-label">معدات مشغلة</div>';
                echo '      </div>';

                echo '  </div>';
                echo '</a>';
            }
        } else {
            echo '<div class="no-projects">';
            echo '  <i class="fas fa-folder-open"></i>';
            if (!$result) {
                echo '  <p>تعذر تحميل المشاريع حالياً. يرجى مراجعة إعدادات قاعدة البيانات.</p>';
            } else {
                echo '  <p>لا توجد مشاريع متاحة حالياً</p>';
            }
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
