<?php
$year = date('Y');

$scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '/index.php';
$baseUrl = rtrim(dirname($scriptName), '/');
if ($baseUrl === '/' || $baseUrl === '\\') {
    $baseUrl = '';
}

function landing_url($path = '') {
    global $baseUrl;
    if ($path === '' || $path === '/') {
        return $baseUrl === '' ? '/' : $baseUrl;
    }

    return ($baseUrl === '' ? '' : $baseUrl) . '/' . ltrim($path, '/');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>منصة إنجاز | إدارة التعدين والتشغيل</title>
    <meta name="description" content="منصة إنجاز SaaS لإدارة شركات التعدين والمشاريع والمعدات والعقود وساعات العمل عبر تجربة تشغيل موحدة.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy-950: #081426;
            --navy-900: #0c1f39;
            --navy-800: #123159;
            --blue-700: #1f5f92;
            --gold-500: #d6a700;
            --gold-400: #efc341;
            --gold-soft: rgba(214, 167, 0, 0.18);
            --ink: #102443;
            --muted: #5f7390;
            --bg: #ecf2fa;
            --line: rgba(16, 36, 67, 0.14);
            --card: #ffffff;
            --ok: #0f8a5f;
            --danger: #bc3f3f;
            --shadow: 0 20px 55px rgba(12, 28, 62, 0.16);
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            margin: 0;
            font-family: 'Cairo', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 10% 15%, rgba(214, 167, 0, 0.2), transparent 28%),
                radial-gradient(circle at 90% 3%, rgba(31, 95, 146, 0.18), transparent 26%),
                radial-gradient(circle at 50% 120%, rgba(12, 31, 57, 0.12), transparent 32%),
                linear-gradient(160deg, #edf3fb 0%, #f7fafe 45%, #edf2fa 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .ambient {
            position: fixed;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            overflow: hidden;
        }

        .ambient span {
            position: absolute;
            border-radius: 50%;
            filter: blur(2px);
            opacity: 0.34;
            animation: floaty 16s ease-in-out infinite;
        }

        .ambient span:nth-child(1) {
            width: 340px;
            height: 340px;
            background: radial-gradient(circle, rgba(214, 167, 0, 0.34), transparent 68%);
            top: -90px;
            right: -50px;
        }

        .ambient span:nth-child(2) {
            width: 290px;
            height: 290px;
            background: radial-gradient(circle, rgba(17, 58, 95, 0.42), transparent 72%);
            top: 35%;
            left: -90px;
            animation-delay: 1.8s;
        }

        .ambient span:nth-child(3) {
            width: 260px;
            height: 260px;
            background: radial-gradient(circle, rgba(31, 95, 146, 0.24), transparent 70%);
            bottom: -70px;
            right: 20%;
            animation-delay: 3.2s;
        }

        @keyframes floaty {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-20px) scale(1.02); }
        }

        .container {
            width: min(1240px, calc(100% - 28px));
            margin-inline: auto;
        }

        .header {
            position: sticky;
            top: 0;
            z-index: 60;
            backdrop-filter: blur(14px);
            background: rgba(255, 255, 255, 0.66);
            border-bottom: 1px solid rgba(255, 255, 255, 0.65);
        }

        .navbar {
            min-height: 80px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--ink);
        }

        .logo-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--navy-900), var(--blue-700));
            color: #fff;
            box-shadow: 0 12px 30px rgba(12, 31, 57, 0.28);
        }

        .logo h1 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 900;
            line-height: 1.2;
        }

        .logo small {
            display: block;
            margin-top: 2px;
            color: var(--muted);
            font-size: 0.76rem;
            font-weight: 800;
        }

        .nav-links {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--ink);
            font-size: 0.87rem;
            font-weight: 800;
            border-radius: 999px;
            border: 1px solid transparent;
            padding: 9px 13px;
            transition: all .22s ease;
        }

        .nav-links a:hover {
            border-color: rgba(31, 95, 146, 0.22);
            background: #fff;
            transform: translateY(-1px);
        }

        .nav-cta {
            text-decoration: none;
            color: #fff;
            font-weight: 800;
            border-radius: 12px;
            padding: 11px 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(140deg, var(--navy-900), var(--blue-700));
            box-shadow: 0 10px 24px rgba(12, 31, 57, 0.24);
            transition: all .22s ease;
        }

        .nav-cta:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(12, 31, 57, 0.28);
        }

        .hero {
            margin-top: 18px;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 14px;
            align-items: stretch;
        }

        .hero-main {
            position: relative;
            border-radius: 28px;
            overflow: hidden;
            background: linear-gradient(160deg, var(--navy-900) 0%, var(--navy-800) 58%, #205f91 100%);
            color: #fff;
            padding: 36px;
            box-shadow: 0 24px 60px rgba(12, 28, 62, 0.24);
            isolation: isolate;
        }

        .hero-main::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px);
            background-size: 20px 20px;
            pointer-events: none;
        }

        .hero-main::after {
            content: '';
            position: absolute;
            bottom: -120px;
            right: -70px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(214, 167, 0, 0.42), transparent 68%);
            z-index: 0;
        }

        .pill {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(214, 167, 0, 0.18);
            border: 1px solid rgba(214, 167, 0, 0.58);
            color: #f8d15a;
            border-radius: 999px;
            padding: 8px 13px;
            font-size: .82rem;
            font-weight: 800;
        }

        .hero-main h2 {
            position: relative;
            z-index: 1;
            margin: 14px 0 10px;
            font-size: clamp(1.55rem, 3vw, 2.34rem);
            line-height: 1.45;
            max-width: 680px;
        }

        .hero-main p {
            position: relative;
            z-index: 1;
            margin: 0;
            max-width: 650px;
            line-height: 1.95;
            color: rgba(255, 255, 255, 0.9);
        }

        .hero-actions {
            position: relative;
            z-index: 1;
            margin-top: 24px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            text-decoration: none;
            border-radius: 13px;
            padding: 12px 18px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 900;
            transition: all .22s ease;
        }

        .btn-gold {
            background: linear-gradient(135deg, var(--gold-500), var(--gold-400));
            color: #302504;
            box-shadow: 0 12px 30px rgba(214, 167, 0, 0.34);
        }

        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 32px rgba(214, 167, 0, 0.36);
        }

        .btn-ghost {
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.36);
            background: rgba(255, 255, 255, 0.1);
        }

        .btn-ghost:hover {
            border-color: rgba(214, 167, 0, 0.62);
            background: rgba(255, 255, 255, 0.16);
        }

        .hero-metrics {
            position: relative;
            z-index: 1;
            margin-top: 22px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

        .hero-metric {
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 12px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.09);
            backdrop-filter: blur(4px);
        }

        .hero-metric b {
            display: block;
            font-size: 1.12rem;
            font-weight: 900;
            color: #fff;
        }

        .hero-metric span {
            font-size: .75rem;
            color: rgba(255, 255, 255, 0.86);
            font-weight: 700;
        }

        .hero-side {
            display: grid;
            gap: 10px;
            align-content: start;
        }

        .glass-card {
            border-radius: 20px;
            padding: 14px;
            background: rgba(255, 255, 255, 0.86);
            border: 1px solid rgba(255, 255, 255, 0.82);
            box-shadow: var(--shadow);
            backdrop-filter: blur(8px);
        }

        .slider {
            position: relative;
            overflow: hidden;
            border-radius: 16px;
            min-height: 302px;
        }

        .slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            transform: scale(1.02);
            transition: opacity .55s ease, transform .55s ease;
            pointer-events: none;
        }

        .slide.active {
            opacity: 1;
            transform: scale(1);
            pointer-events: auto;
        }

        .slide img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            border-radius: 16px;
        }

        .slide-caption {
            position: absolute;
            right: 12px;
            left: 12px;
            bottom: 12px;
            color: #fff;
            background: linear-gradient(180deg, rgba(8, 20, 38, 0.08), rgba(8, 20, 38, 0.8));
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 12px;
            padding: 10px;
        }

        .slide-caption strong {
            display: block;
            font-size: .9rem;
        }

        .slide-caption span {
            display: block;
            margin-top: 2px;
            font-size: .76rem;
            color: rgba(255, 255, 255, 0.88);
        }

        .slider-controls {
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
        }

        .slider-buttons {
            display: inline-flex;
            gap: 6px;
        }

        .slider-buttons button {
            border: 0;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            cursor: pointer;
            color: #fff;
            background: linear-gradient(145deg, var(--navy-900), var(--blue-700));
            transition: transform .2s ease;
        }

        .slider-buttons button:hover {
            transform: translateY(-1px);
        }

        .slider-dots {
            display: inline-flex;
            gap: 6px;
            align-items: center;
        }

        .slider-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 0;
            padding: 0;
            cursor: pointer;
            background: #c8d6eb;
            transition: all .2s ease;
        }

        .slider-dot.active {
            width: 24px;
            border-radius: 8px;
            background: linear-gradient(145deg, var(--gold-500), var(--gold-400));
        }

        .entry-list {
            list-style: none;
            margin: 12px 0 0;
            padding: 0;
            display: grid;
            gap: 8px;
        }

        .entry-list a {
            text-decoration: none;
            color: var(--ink);
            font-weight: 800;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all .2s ease;
        }

        .entry-list a:hover {
            background: #f8fbff;
            border-color: rgba(31, 95, 146, 0.3);
            transform: translateY(-1px);
        }

        .entry-list i {
            width: 31px;
            height: 31px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #1f4c7b;
            background: #e7eef9;
        }

        .kpi-grid {
            margin-top: 14px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .kpi {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 10px 28px rgba(12, 28, 62, 0.08);
            position: relative;
            overflow: hidden;
        }

        .kpi::before {
            content: '';
            position: absolute;
            top: -40px;
            left: -40px;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(31, 95, 146, 0.16), transparent 70%);
        }

        .kpi .num {
            font-size: 1.4rem;
            font-weight: 900;
            color: var(--ink);
        }

        .kpi .lbl {
            margin-top: 3px;
            font-size: .8rem;
            color: var(--muted);
            font-weight: 700;
        }

        .section { margin-top: 24px; }

        .section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .section-head h3 {
            margin: 0;
            font-size: 1.24rem;
        }

        .section-head p {
            margin: 0;
            color: var(--muted);
            font-size: .89rem;
            font-weight: 700;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .feature {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 16px;
            box-shadow: 0 10px 28px rgba(12, 28, 62, 0.08);
            transition: transform .24s ease, box-shadow .24s ease;
        }

        .feature:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 32px rgba(12, 28, 62, 0.12);
        }

        .feature i {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #e6edf9, #dce8fa);
            color: #1a4471;
            margin-bottom: 8px;
        }

        .feature h4 {
            margin: 0 0 6px;
            font-size: .96rem;
        }

        .feature p {
            margin: 0;
            font-size: .84rem;
            color: var(--muted);
            line-height: 1.8;
        }

        .comparison {
            overflow: hidden;
            border-radius: 16px;
            border: 1px solid var(--line);
            background: #fff;
            box-shadow: var(--shadow);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        th, td {
            padding: 13px;
            text-align: right;
            border-bottom: 1px solid #edf1f6;
            font-size: .86rem;
        }

        th {
            background: #f4f8ff;
            color: #214674;
            font-weight: 900;
        }

        .yes { color: var(--ok); font-weight: 800; }
        .no { color: var(--danger); font-weight: 800; }

        .journey {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .step {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px 14px;
            box-shadow: 0 10px 24px rgba(12, 28, 62, 0.08);
            position: relative;
            overflow: hidden;
        }

        .step::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 90px;
            height: 4px;
            border-radius: 0 0 0 8px;
            background: linear-gradient(135deg, var(--gold-500), var(--gold-400));
        }

        .step-num {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--gold-soft);
            color: #7f6200;
            font-size: .84rem;
            font-weight: 900;
            margin-bottom: 8px;
        }

        .step h4 {
            margin: 0 0 5px;
            font-size: .95rem;
        }

        .step p {
            margin: 0;
            color: var(--muted);
            font-size: .84rem;
            line-height: 1.78;
        }

        .cta {
            margin-top: 22px;
            border-radius: 20px;
            background: linear-gradient(145deg, var(--navy-900), var(--navy-800));
            color: #fff;
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            box-shadow: 0 18px 44px rgba(12, 28, 62, 0.24);
        }

        .cta h3 {
            margin: 0 0 5px;
            font-size: 1.28rem;
        }

        .cta p {
            margin: 0;
            color: rgba(255, 255, 255, 0.86);
            font-size: .92rem;
        }

        .footer {
            margin-top: 24px;
            border-top: 1px solid rgba(16, 36, 67, 0.08);
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(7px);
        }

        .footer-wrap {
            padding: 16px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .footer small {
            color: var(--muted);
            font-weight: 700;
        }

        .footer-links {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .footer-links a {
            text-decoration: none;
            color: var(--ink);
            font-size: .82rem;
            font-weight: 700;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 6px 11px;
            background: #fff;
            transition: all .2s ease;
        }

        .footer-links a:hover {
            border-color: rgba(31, 95, 146, 0.28);
            transform: translateY(-1px);
        }

        @media (max-width: 1080px) {
            .hero { grid-template-columns: 1fr; }
            .feature-grid, .journey { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 670px) {
            .navbar {
                min-height: auto;
                padding: 8px 0;
                flex-direction: column;
                align-items: stretch;
            }
            .nav-links { justify-content: center; }
            .nav-cta { justify-content: center; }
            .hero-main { padding: 24px; }
            .hero-metrics { grid-template-columns: 1fr; }
            .feature-grid, .journey, .kpi-grid { grid-template-columns: 1fr; }
            .slider { min-height: 250px; }
            .cta { padding: 18px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.001ms !important;
                scroll-behavior: auto !important;
            }
        }
    </style>
</head>
<body>
    <div class="ambient" aria-hidden="true">
        <span></span>
        <span></span>
        <span></span>
    </div>

    <header class="header">
        <div class="container navbar">
            <a class="logo" href="<?php echo landing_url('index.php'); ?>" aria-label="الصفحة الرئيسية">
                <span class="logo-icon"><i class="fas fa-layer-group"></i></span>
                <span>
                    <h1>منصة إنجاز</h1>
                    <small>Mining Operations SaaS</small>
                </span>
            </a>

            <nav class="nav-links" aria-label="التنقل الرئيسي">
                <a href="#features">المزايا</a>
                <a href="#comparison">المقارنة</a>
                <a href="#journey">رحلة الاستخدام</a>
                <a href="<?php echo landing_url('company/login.php'); ?>">بوابة الشركات</a>
                <a href="<?php echo landing_url('admin/login.php'); ?>">لوحة الإدارة</a>
            </nav>

            <a class="nav-cta" href="<?php echo landing_url('login.php'); ?>"><i class="fas fa-right-to-bracket"></i> دخول النظام</a>
        </div>
    </header>

    <main class="container">
        <section class="hero">
            <div class="hero-main">
                <span class="pill"><i class="fas fa-bolt"></i> منصة SaaS متخصصة في إدارة التعدين</span>
                <h2>واجهة تشغيل عالمية بطابع عربي متقن، تجمع المشاريع والمعدات والعقود وساعات التشغيل في تجربة واحدة سلسة.</h2>
                <p>
                    إنجاز تمزج قوة المنصات العالمية مع فهم حقيقي لقطاع التعدين المحلي.
                    النتيجة: دورة عمل واضحة، واجهة أسرع، ومعلومات تشغيلية جاهزة لاتخاذ القرار من دون تعقيد.
                </p>
                <div class="hero-actions">
                    <a class="btn btn-gold" href="<?php echo landing_url('company/register.php'); ?>"><i class="fas fa-building-circle-check"></i> تسجيل شركة جديدة</a>
                    <a class="btn btn-ghost" href="<?php echo landing_url('login.php'); ?>"><i class="fas fa-arrow-left"></i> تسجيل الدخول للنظام</a>
                </div>

                <div class="hero-metrics" aria-label="مؤشرات سريعة">
                    <article class="hero-metric"><b>+12</b><span>وحدة تشغيل مترابطة</span></article>
                    <article class="hero-metric"><b>3</b><span>بوابات دخول حسب الدور</span></article>
                    <article class="hero-metric"><b>24/7</b><span>وصول سحابي للفرق</span></article>
                </div>
            </div>

            <aside class="hero-side">
                <article class="glass-card">
                    <div class="slider" id="heroSlider" aria-label="معرض مرئي للمنصة">
                        <figure class="slide active" data-slide="0">
                            <img src="<?php echo landing_url('assets/images/slide-mine-1.svg'); ?>" alt="مشهد تعدين حديث">
                            <figcaption class="slide-caption">
                                <strong>تشغيل ميداني منظم</strong>
                                <span>متابعة مواقع العمل والمعدات في رؤية واحدة.</span>
                            </figcaption>
                        </figure>
                        <figure class="slide" data-slide="1">
                            <img src="<?php echo landing_url('assets/images/slide-dashboard-2.svg'); ?>" alt="لوحة بيانات تشغيلية">
                            <figcaption class="slide-caption">
                                <strong>تحليلات لحظية</strong>
                                <span>مؤشرات عقود ومشاريع تساعد في قرارات أسرع.</span>
                            </figcaption>
                        </figure>
                        <figure class="slide" data-slide="2">
                            <img src="<?php echo landing_url('assets/images/slide-team-3.svg'); ?>" alt="فريق تشغيل معدات">
                            <figcaption class="slide-caption">
                                <strong>تنسيق الفرق</strong>
                                <span>ربط المشغلين بالمعدات والمشاريع بدون فجوات.</span>
                            </figcaption>
                        </figure>
                    </div>

                    <div class="slider-controls">
                        <div class="slider-buttons" role="group" aria-label="أزرار السلايدر">
                            <button type="button" id="nextSlide" aria-label="الشريحة التالية"><i class="fas fa-chevron-right"></i></button>
                            <button type="button" id="prevSlide" aria-label="الشريحة السابقة"><i class="fas fa-chevron-left"></i></button>
                        </div>
                        <div class="slider-dots" id="sliderDots" aria-label="نقاط التنقل"></div>
                    </div>
                </article>

                <article class="glass-card">
                    <h3><i class="fas fa-route" style="color:#2563eb"></i> مسارات الدخول</h3>
                    <p style="margin:0;color:var(--muted);font-size:.88rem;line-height:1.8;">اختر المسار الصحيح حسب نوع حسابك.</p>
                    <ul class="entry-list">
                        <li><a href="<?php echo landing_url('company/register.php'); ?>"><i class="fas fa-user-plus"></i> إنشاء حساب شركة</a></li>
                        <li><a href="<?php echo landing_url('company/login.php'); ?>"><i class="fas fa-users"></i> دخول مستخدمي الشركات</a></li>
                        <li><a href="<?php echo landing_url('login.php'); ?>"><i class="fas fa-gauge-high"></i> دخول نظام التشغيل</a></li>
                        <li><a href="<?php echo landing_url('admin/login.php'); ?>"><i class="fas fa-user-shield"></i> دخول الإدارة العليا</a></li>
                    </ul>
                </article>

                <article class="glass-card">
                    <h3><i class="fas fa-shield-halved" style="color:#0f8a5f"></i> تجربة موثوقة</h3>
                    <p style="margin:0;color:var(--muted);font-size:.88rem;line-height:1.8;">
                        واجهات واضحة، صلاحيات مرنة، وتدفق استخدام قصير يقلل الأخطاء اليومية ويزيد سرعة اعتماد النظام داخل الشركة.
                    </p>
                </article>
            </aside>
        </section>

        <section class="kpi-grid" aria-label="مؤشرات القيمة">
            <article class="kpi"><div class="num">+12</div><div class="lbl">وحدة تشغيل وإدارة متكاملة</div></article>
            <article class="kpi"><div class="num">3</div><div class="lbl">بوابات دخول حسب نوع المستخدم</div></article>
            <article class="kpi"><div class="num">RTL</div><div class="lbl">تجربة عربية كاملة من البداية</div></article>
            <article class="kpi"><div class="num">24/7</div><div class="lbl">وصول سحابي مستمر للفرق</div></article>
        </section>

        <section class="section" id="features">
            <div class="section-head">
                <h3>مزايا موجهة لعمليات التعدين</h3>
                <p>بدل الاعتماد على حلول عامة، إنجاز يقدم تجربة مصممة لسياق العمل الحقيقي.</p>
            </div>
            <div class="feature-grid">
                <article class="feature">
                    <i class="fas fa-mountain"></i>
                    <h4>إدارة المناجم والمشاريع</h4>
                    <p>ربط المناجم بالمشاريع والعملاء مع تتبع الحالة ونوع التشغيل في شاشة موحدة.</p>
                </article>
                <article class="feature">
                    <i class="fas fa-file-signature"></i>
                    <h4>دورة حياة العقود</h4>
                    <p>تجديد، إيقاف، إنهاء، تسوية ودمج العقود مع سجل تدقيق واضح لكل إجراء.</p>
                </article>
                <article class="feature">
                    <i class="fas fa-truck-monster"></i>
                    <h4>تشغيل المعدات والمشغلين</h4>
                    <p>تخصيص المعدات والسائقين على المشاريع وربطها مباشرة بساعات العمل.</p>
                </article>
                <article class="feature">
                    <i class="fas fa-clock"></i>
                    <h4>ساعات عمل دقيقة</h4>
                    <p>إدخال ساعات التشغيل اليومية مع تتبع الأعطال والملاحظات لكل وردية.</p>
                </article>
                <article class="feature">
                    <i class="fas fa-chart-column"></i>
                    <h4>تقارير للإدارة</h4>
                    <p>نظرة سريعة على الأداء التشغيلي والعقود والموردين لدعم اتخاذ القرار.</p>
                </article>
                <article class="feature">
                    <i class="fas fa-user-lock"></i>
                    <h4>صلاحيات حسب الدور</h4>
                    <p>تحكم دقيق في الوصول والعمليات لكل مستخدم داخل الشركة أو الإدارة.</p>
                </article>
            </div>
        </section>

        <section class="section" id="comparison">
            <div class="section-head">
                <h3>مقارنة معيارية مع حلول SaaS العامة</h3>
                <p>التركيز على متطلبات التعدين ينعكس مباشرة على سهولة الاستخدام والنتائج.</p>
            </div>
            <div class="comparison" role="region" aria-label="جدول المقارنة" style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>المعيار</th>
                            <th>منصة إنجاز</th>
                            <th>حلول SaaS عامة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>إدارة المناجم ضمن دورة المشروع</td>
                            <td class="yes">مبنية داخل النظام</td>
                            <td class="no">تخصيص إضافي غالبًا</td>
                        </tr>
                        <tr>
                            <td>مسارات عمل العقود (تجديد/إيقاف/دمج)</td>
                            <td class="yes">مدعومة بالكامل</td>
                            <td class="no">جزئية أو خارجية</td>
                        </tr>
                        <tr>
                            <td>تجربة عربية RTL أصلية</td>
                            <td class="yes">مدمجة افتراضيًا</td>
                            <td class="no">تخصيص ناقص غالبًا</td>
                        </tr>
                        <tr>
                            <td>ربط المعدات بالمشغلين وساعات التشغيل</td>
                            <td class="yes">تدفق واحد مترابط</td>
                            <td class="no">أدوات متعددة منفصلة</td>
                        </tr>
                        <tr>
                            <td>بوابات دخول متعددة (شركة/إدارة/تشغيل)</td>
                            <td class="yes">موجودة وجاهزة</td>
                            <td class="no">غير قياسي</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="section" id="journey">
            <div class="section-head">
                <h3>رحلة استخدام مختصرة وواضحة</h3>
                <p>3 خطوات فقط للانطلاق من التسجيل حتى التشغيل اليومي.</p>
            </div>
            <div class="journey">
                <article class="step">
                    <span class="step-num">1</span>
                    <h4>تسجيل الشركة</h4>
                    <p>إنشاء حساب الشركة وتفعيل البيئة مع بيانات المستخدمين الأساسيين.</p>
                </article>
                <article class="step">
                    <span class="step-num">2</span>
                    <h4>تهيئة البيانات الأساسية</h4>
                    <p>إضافة العملاء، المشاريع، المناجم، الموردين والمعدات وفق الهيكل المطلوب.</p>
                </article>
                <article class="step">
                    <span class="step-num">3</span>
                    <h4>تشغيل ومتابعة</h4>
                    <p>إدارة العقود وساعات العمل واستخراج التقارير اليومية والإدارية بسهولة.</p>
                </article>
            </div>
        </section>

        <section class="cta" aria-label="دعوة لاتخاذ إجراء">
            <div>
                <h3>ابدأ الآن بمنصة إنجاز</h3>
                <p>نقطة دخول واحدة لجميع المستخدمين مع تجربة تشغيل احترافية وثابتة.</p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a class="btn btn-gold" href="<?php echo landing_url('company/register.php'); ?>"><i class="fas fa-building-circle-check"></i> تسجيل شركة جديدة</a>
                <a class="btn btn-ghost" href="<?php echo landing_url('admin/login.php'); ?>"><i class="fas fa-user-shield"></i> لوحة الإدارة</a>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container footer-wrap">
            <small>&copy; <?php echo $year; ?> منصة إنجاز - جميع الحقوق محفوظة</small>
            <div class="footer-links">
                <a href="<?php echo landing_url('login.php'); ?>">دخول النظام</a>
                <a href="<?php echo landing_url('company/login.php'); ?>">بوابة الشركات</a>
                <a href="<?php echo landing_url('admin/login.php'); ?>">الإدارة العليا</a>
            </div>
        </div>
    </footer>

    <script>
        (function () {
            var slides = Array.prototype.slice.call(document.querySelectorAll('#heroSlider .slide'));
            var dotsWrap = document.getElementById('sliderDots');
            var nextBtn = document.getElementById('nextSlide');
            var prevBtn = document.getElementById('prevSlide');
            var index = 0;
            var timer = null;

            if (!slides.length || !dotsWrap || !nextBtn || !prevBtn) {
                return;
            }

            function renderDots() {
                slides.forEach(function (_, i) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'slider-dot' + (i === 0 ? ' active' : '');
                    btn.setAttribute('aria-label', 'الانتقال إلى الشريحة ' + (i + 1));
                    btn.addEventListener('click', function () {
                        goTo(i);
                        restartAuto();
                    });
                    dotsWrap.appendChild(btn);
                });
            }

            function updateUI() {
                var dots = dotsWrap.querySelectorAll('.slider-dot');
                slides.forEach(function (slide, i) {
                    slide.classList.toggle('active', i === index);
                });
                Array.prototype.forEach.call(dots, function (dot, i) {
                    dot.classList.toggle('active', i === index);
                });
            }

            function goTo(nextIndex) {
                index = (nextIndex + slides.length) % slides.length;
                updateUI();
            }

            function next() { goTo(index + 1); }
            function prev() { goTo(index - 1); }

            function startAuto() {
                timer = window.setInterval(next, 5000);
            }

            function stopAuto() {
                if (timer) {
                    window.clearInterval(timer);
                    timer = null;
                }
            }

            function restartAuto() {
                stopAuto();
                startAuto();
            }

            nextBtn.addEventListener('click', function () {
                next();
                restartAuto();
            });

            prevBtn.addEventListener('click', function () {
                prev();
                restartAuto();
            });

            document.getElementById('heroSlider').addEventListener('mouseenter', stopAuto);
            document.getElementById('heroSlider').addEventListener('mouseleave', startAuto);

            renderDots();
            updateUI();
            startAuto();
        })();
    </script>
</body>
</html>
