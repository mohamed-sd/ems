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
<style>
:root {
    --navy: #0c1c3e;
    --navy-m: #132050;
    --navy-l: #1b2f6e;
    --gold: #e8b800;
    --gold-l: #ffd740;
    --gold-d: rgba(232, 184, 0, .12);
    --blue: #2563eb;
    --blue-l: #3b82f6;
    --teal: #0d9488;
    --bg: #f0f2f8;
    --card: #ffffff;
    --line: rgba(12, 28, 62, .09);
    --txt: #0c1c3e;
    --muted: #64748b;
    --r: 14px;
    --rl: 20px;
    --s1: 0 2px 8px rgba(12, 28, 62, .08);
    --s2: 0 10px 24px rgba(12, 28, 62, .12);
    --s3: 0 14px 40px rgba(0, 0, 34, .34);
}

body {
    margin: 0;
    font-family: "Cairo", sans-serif;
    background:
      radial-gradient(circle at 85% 8%, rgba(232, 184, 0, .14), transparent 28%),
      radial-gradient(circle at 8% 88%, rgba(37, 99, 235, .10), transparent 30%),
      var(--bg);
}

.topbar-lite {
    position: sticky;
    top: 0;
    z-index: 30;
    background: linear-gradient(120deg, var(--navy), var(--navy-l));
    color: #fff;
    padding: 10px 16px;
    border-bottom: 2px solid rgba(232, 184, 0, .52);
    box-shadow: 0 4px 16px rgba(12, 28, 62, .2);
}

.brand { display: flex; align-items: center; gap: 10px; }

.brand-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--navy), var(--navy-l));
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--s1);
}

.brand-icon i { color: var(--gold); font-size: .95rem; }

.brand-info .sys {
    font-size: .66rem;
    font-weight: 700;
    letter-spacing: .08em;
    color: rgba(255, 255, 255, .7);
    text-transform: uppercase;
}

.brand-info .greet {
    font-size: .9rem;
    font-weight: 900;
    color: #fff8dd;
}

.topbar-lite .btn {
    border-radius: 999px;
    font-weight: 700;
    border-color: rgba(255, 255, 255, .55);
    padding: 6px 14px;
}

.reports-shell {
    padding: 20px;
    animation: pageFade .45s ease;
}

@keyframes pageFade {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

.hero {
    background: linear-gradient(135deg, #000022 0%, #0d1a5c 60%, #1a0a3e 100%);
    color: #fff;
    border-radius: 18px;
    padding: 22px 24px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--s3);
}

.hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 82% 50%, rgba(255, 204, 0, .10) 0%, transparent 70%);
}

.hero h1 {
    margin: 0;
    font-size: 1.32rem;
    font-weight: 900;
    color: #ffcc00;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.hero h1 i {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: rgba(255, 204, 0, .15);
    border: 1px solid rgba(255, 204, 0, .32);
    color: #ffcc00;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.hero p {
    margin: 8px 0 0;
    font-size: .86rem;
    color: rgba(255, 255, 210, .74);
}

.category-section { margin-bottom: 28px; }

.category-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    padding: 10px 12px;
    border: 1px solid var(--line);
    background: var(--card);
    border-radius: 12px;
    box-shadow: var(--s1);
}

.category-header .cat-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(125deg, var(--navy), var(--navy-l));
    color: #fff;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.category-header h2 {
    font-size: 1rem;
    font-weight: 800;
    color: var(--txt);
    margin: 0;
}

.category-header .badge {
    background: linear-gradient(120deg, var(--gold), var(--gold-l)) !important;
    color: #1f2937;
    font-size: .72rem;
    font-weight: 900;
    border-radius: 999px;
    padding: 6px 10px;
}

.report-card {
    background: var(--card);
    border-radius: var(--rl);
    border: 1px solid var(--line);
    padding: 0;
    height: 100%;
    transition: box-shadow .2s, transform .2s, border-color .2s;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: var(--s1);
}

.report-card .card-band {
    height: 4px;
    background: linear-gradient(90deg, var(--gold), var(--gold-l));
}

.report-card:hover {
    box-shadow: var(--s2);
    transform: translateY(-4px);
    border-color: rgba(37, 99, 235, .26);
}

.report-card .card-inner {
    padding: 14px;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.report-card .rc-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(125deg, rgba(37, 99, 235, .12), rgba(13, 148, 136, .12));
    color: var(--blue);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.22rem;
    margin-bottom: 12px;
}

.report-card h3 {
    font-size: .97rem;
    font-weight: 800;
    color: var(--txt);
    margin-bottom: 6px;
    line-height: 1.5;
}

.report-card p {
    font-size: .82rem;
    color: var(--muted);
    flex: 1;
    margin-bottom: 12px;
}

.report-card a.btn {
    border-radius: 999px;
    font-size: .81rem;
    font-weight: 700;
    border-color: rgba(12, 28, 62, .16);
    color: var(--navy-l);
    padding: 6px 12px;
}

.report-card a.btn:hover {
    background: linear-gradient(120deg, var(--blue), var(--blue-l));
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
<body>

<div class="topbar-lite d-flex align-items-center justify-content-between">
    <div class="brand">
        <div class="brand-icon"><i class="fas fa-chart-pie"></i></div>
        <div class="brand-info">
            <div class="sys">إيكوبيشن EPS</div>
            <div class="greet">مركز التقارير</div>
        </div>
    </div>
    <a href="../main/dashboard.php" class="btn btn-sm btn-outline-light">
        <i class="fas fa-home"></i> لوحة التحكم
    </a>
</div>

<main class="reports-shell">

    <div class="hero">
        <h1><i class="fas fa-chart-pie"></i> التقارير</h1>
        <p>مرحباً <?php echo $userName; ?> — إليك التقارير المتاحة لك</p>
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
                <?php foreach ($reports as $report): ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="report-card">
                        <div class="card-band"></div>
                        <div class="card-inner">
                            <div class="rc-icon">
                                <i class="fas <?php echo htmlspecialchars($report['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                            </div>
                            <h3><?php echo htmlspecialchars($report['name_ar'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p><?php echo htmlspecialchars($report['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <a href="<?php echo htmlspecialchars($report['url'], ENT_QUOTES, 'UTF-8'); ?>"
                               class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-1"></i> عرض التقرير
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>
</main>

</body>
</html>
