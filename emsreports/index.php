<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../config.php';
require_once __DIR__ . '/includes/functions.php';

$roleId   = intval($_SESSION['user']['role']);
$userName = htmlspecialchars($_SESSION['user']['name'] ?? '', ENT_QUOTES, 'UTF-8');

$available  = getAvailableReports($conn, $roleId);
$byCategory = [];
foreach ($available as $report) {
    $byCategory[$report['category']][] = $report;
}

$page_title = 'التقارير';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $page_title; ?></title>
<link rel="stylesheet" href="../assets/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="../assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">
<link rel="stylesheet" href="/ems/assets/css/ems.main.all.style.css">
<style>
:root {
    --navy: #1a1208;
    --navy-m: #2d200a;
    --navy-l: #3a2a12;
    --gold: #f7931a;
    --gold-l: #ffb347;
    --gold-d: rgba(232, 184, 0, .12);
    --blue: #f7931a;
    --blue-l: #e67e00;
    --teal: #6b4e2a;
    --bg: #f5f0e8;
    --card: #ffffff;
    --line: rgba(26, 18, 8, .12);
    --txt: #1a1208;
    --muted: #6b4e2a;
    --r: 14px;
    --rl: 20px;
    --s1: 0 2px 8px rgba(12, 28, 62, .08);
    --s2: 0 10px 24px rgba(12, 28, 62, .12);
    --s3: 0 14px 40px rgba(0, 0, 34, .34);
}

@keyframes pageFade {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

.category-section {margin: 0px 15px 25px 15px;}

.category-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom:10px;
    padding: 5px;
    border-radius: 15px;
    box-shadow: var(--s1);
    background-color: #eee;
    border: 1px solid #555;
}

.category-header .cat-icon {
    width: 35px;
    height: 35px;
    background: #fff;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #000;
    border: 1px solid #555;
}

.category-header h2 {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--txt);
    margin: 0;
}

.category-header .badge {
    background: linear-gradient(120deg, var(--gold), var(--gold-l)) !important;
    color: #1f2937;
    font-size: .72rem;
    font-weight: 900;
    border-radius: 10px;
    padding: 6px 10px;
}

.report-card {
    background:#eee;
    border-radius:20px;
    height: auto;
    transition: box-shadow .2s, transform .2s, border-color .2s;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: var(--s1);
    margin: 0px;
    padding: 5px;;
}

.report-card:hover {
    box-shadow: var(--s2);
    transform: translateY(-4px);
    border-color: rgba(37, 99, 235, .26);
}

.report-card .card-inner {
    padding: 5px;
}

.report-card .rc-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    background: #fff;
    color: #000;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    float:left;
    margin-top: 5px;
    vertical-align: middle;
}

.report-card .rc-icon i{
    float : left;
}


.report-card h3 {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--txt);
    margin-bottom: 6px;
    line-height: 1.5;
}

.report-card p {
    font-size: .82rem;
    color: #888;
    flex: 1;
    margin-bottom: 12px;
}

.report-card a {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    background: #fff;
    color: #000;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    float:left;
    margin-top: 5px;
    vertical-align: middle;
    border: none !important;
    margin: 5px;
    text-decoration: none;
}

.report-card a.btn:hover {
    background: linear-gradient(120deg, var(--navy), var(--navy-l));
    border-color: transparent;
    color: #fff;
}

.no-reports {
    text-align: center;
    padding: 60px 20px;
    color: var(--muted);
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: var(--r);
    box-shadow: var(--s1);
}

.no-reports i {
    font-size: 3.3rem;
    margin-bottom: 14px;
    display: block;
    color: var(--gold);
}

@media (max-width: 768px) {
    .reports-shell { padding: 14px; }
    .hero { padding: 16px; }
    .hero h1 { font-size: 1.08rem; }
    .hero h1 i { width: 36px; height: 36px; }
    .brand-info .sys { display: none; }
}
</style>
</head>
<?php
include '../insidebar.php';
?>
<body class="ems-site">

<div class="main ems-unified-page-shell reports-shell">

    <div class="main_head emsreports-head">
        <div class="head_actions">
            <a href="../main/dashboard.php" class="add-btn">
                <i class="fas fa-home"></i> لوحة التحكم
            </a>
        </div>

        <h1 class="head-title">
            <div class="title-icon"><i class="fas fa-chart-pie"></i></div>
            مركز التقارير
        </h1>

        <div class="head_back">
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <?php if (empty($byCategory)): ?>
        <div class="no-reports">
            <i class="fas fa-lock"></i>
            <h4>لا توجد تقارير متاحة</h4>
            <p>لا تملك صلاحية عرض أي تقرير. تواصل مع مدير النظام.</p>
        </div>
    <?php else: ?>

        <?php foreach ($byCategory as $category => $reports): ?>
        <div class="category-section">
            <div class="category-header">
                <div class="cat-icon"><i class="fas <?php echo getCategoryIcon($category); ?>"></i></div>
                <h2><?php echo getCategoryLabel($category); ?></h2>
                <span class="badge bg-secondary ms-auto"><?php echo count($reports); ?> تقرير</span>
            </div>
            <div class="row g-3">
                <?php
                $total = count($reports);
                $rem   = $total % 3;                 // كم كارت متبقي في الصف الأخير
                foreach ($reports as $i => $report):
                    $isLastRow = $rem !== 0 && $i >= $total - $rem;
                    if ($isLastRow && $rem === 1) {
                        $colClass = 'col-12';                       // كارت واحد يملأ الصف
                    } elseif ($isLastRow && $rem === 2) {
                        $colClass = 'col-lg-6 col-md-6';            // كارتان يملآن الصف
                    } else {
                        $colClass = 'col-xl-4 col-lg-4 col-md-6';   // 3 كروت في الصف
                    }
                ?>
                <div class="<?php echo $colClass; ?>">
                    <div class="report-card">
                        <div class="card-band"></div>
                        <div class="card-inner">
                            <div class="rc-icon">
                                <i class="fas <?php echo htmlspecialchars($report['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                            </div>
                            <a href="<?php echo htmlspecialchars($report['url'], ENT_QUOTES, 'UTF-8'); ?>">
                               <i class="fa-regular fa-eye"></i>
                            </a>
                            <h3><?php echo htmlspecialchars($report['name_ar'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p><?php echo htmlspecialchars($report['description'], ENT_QUOTES, 'UTF-8'); ?></p>

                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

</body>
</html>
