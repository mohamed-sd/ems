<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
$page_title = "إيكوبيشن | اختيار المشروع للتشغيل";

// M-27 (UX-03 §7 + الدستور §6 «لا قوائمَ وسيطة»): القائمةُ الوسيطة استُوعبت
// في غرفة العمليات — Redirect بعدّاد hits، و?legacy=1 بابُ رجوعٍ معلَن.
if (!isset($_GET['legacy'])) {
    include '../config.php';
    require_once '../includes/audit_trail.php';
    ems_audit_change($conn, 'operations', 'route_redirect', 'legacy_hit', 23,
        array(), array('from' => 'Oprators/select_project.php', 'to' => 'Operations/operations_room.php'),
        array('company_id' => intval($_SESSION['user']['company_id'] ?? 0),
              'user_id' => intval($_SESSION['user']['id'] ?? 0)));
    header("Location: ../Operations/operations_room.php");
    exit();
}
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
        --light-color: var(--c-f5f5f5);
        --shadow-color: var(--c-rgba-000-010, rgba(0, 0, 0, 0.1));
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
        color: var(--c-6c757d);
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
        box-shadow: 0 15px 50px var(--c-rgba-e2ae03-030, rgba(226, 174, 3, 0.3));
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
        color: var(--c-6c757d);
        text-align: center;
        font-family: monospace;
        background: var(--c-rgba-e2ae03-010, rgba(226, 174, 3, 0.1));
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
        color: var(--c-495057);
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
        border-top: 2px solid var(--c-rgba-e2ae03-020, rgba(226, 174, 3, 0.2));
    }

    .stat-box {
        background: var(--c-rgba-e2ae03-005, rgba(226, 174, 3, 0.05));
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
        color: var(--c-6c757d);
        margin-top: 5px;
        font-weight: 600;
    }

    .no-projects {
        text-align: center;
        padding: 3rem;
        color: var(--c-6c757d);
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
<?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ — الشاشةُ كانت بلا رأسٍ معلَن. */
$header_icon = 'fas fa-window-maximize';
$header_title_html = htmlspecialchars('التشغيل', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا مشاريع متاحة لك لإدارة التشغيل فيها', 'راجع تعيينك على المشاريع مع مدير المشاريع ثم أعد فتح الشاشة');
?>

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

        // العزل عبر البوابة (K9 · هجرة 2026-07-15): كشف الأعمدة أُسقط — الأعمدة
        // الفعلية مقيسة (project.company_id/operations.project_id/contracts.project_id
        // موجودة؛ بنية mines أُزيلت من النظام فعدّادها صفرٌ دائمًا كسلوك الأصل الفعلي).
        $sp_gate = ems_tenant_db();
        $sp_failed = false;
        $sp_rows = array();
        if ($company_id > 0) {
            $sp_extra = ''; $sp_params = array();
            if ($is_role10 && $user_project_id > 0) {
                $sp_extra = " AND p.id = ?";
                $sp_params[] = $user_project_id;
            }
            try {
                $sp_rows = $sp_gate->scopedQuery(array(
                    'scope' => array('p' => 'project'),
                ), "SELECT p.id, p.name, p.project_code, p.location
                    FROM project p
                    WHERE {TENANT_SCOPE} AND p.status = 1$sp_extra
                    ORDER BY p.name ASC", $sp_params);
            } catch (\Throwable $t) {
                $sp_failed = true;
            }
        }
        // بدون company_id في الجلسة لا نعرض أي مشاريع لمنع تسرب بيانات بين الشركات

        if (!empty($sp_rows)) {
            foreach ($sp_rows as $project) {
                $project_id = intval($project['id']);
                $project_name = htmlspecialchars($project['name']);
                $project_code = htmlspecialchars($project['project_code']);
                $location = htmlspecialchars($project['location']);

                $mines_count = 0; // بنية المناجم أُزيلت — سلوك الأصل الفعلي: صفر دائمًا

                $operations_count = 0;
                try {
                    $op_rows = $sp_gate->scopedQuery(array(
                        'scope' => array('operations' => 'operations'),
                    ), "SELECT COUNT(*) AS cnt FROM operations WHERE {TENANT_SCOPE} AND project_id = ? AND status = 1", array($project_id));
                    $operations_count = !empty($op_rows) ? intval($op_rows[0]['cnt'] ?? 0) : 0;
                } catch (\Throwable $t) {}

                $contracts_count = 0;
                try {
                    $ct_rows = $sp_gate->scopedQuery(array(
                        'scope' => array('contracts' => 'contracts'),
                    ), "SELECT COUNT(*) AS cnt FROM contracts WHERE {TENANT_SCOPE} AND project_id = ? AND status = 1", array($project_id));
                    $contracts_count = !empty($ct_rows) ? intval($ct_rows[0]['cnt'] ?? 0) : 0;
                } catch (\Throwable $t) {}

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

                // حساب عدد المعدات المشغلة (العزل عبر البوابة)
                $equip_count = 0;
                try {
                    $eqc_rows = $sp_gate->scopedQuery(array(
                        'scope' => array('operations' => 'operations'),
                    ), "SELECT COUNT(DISTINCT equipment) AS equip_count
                        FROM operations
                        WHERE {TENANT_SCOPE} AND project_id = ? AND status = 1", array($project_id));
                    $equip_count = !empty($eqc_rows) ? intval($eqc_rows[0]['equip_count'] ?? 0) : 0;
                } catch (\Throwable $t) {}

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
            if ($sp_failed) {
                echo '  <p>تعذر تحميل المشاريع حاليا. يرجى مراجعة إعدادات قاعدة البيانات.</p>';
            } else {
                echo '  <p>لا توجد مشاريع متاحة حاليا</p>';
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
