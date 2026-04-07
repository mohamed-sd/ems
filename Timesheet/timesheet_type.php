<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
$page_title = "إيكوبيشن | اختر نوع الآلية";
include("../inheader.php");
include('../insidebar.php');
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

<style>
:root {
  --navy:     #0c1c3e;
  --navy-m:   #132050;
  --navy-l:   #1b2f6e;
  --gold:     #e8b800;
  --gold-l:   #ffd740;
  --gold-d:   rgba(232,184,0,.13);
  --blue:     #2563eb;
  --blue-d:   rgba(37,99,235,.12);
  --orange:   #ea6f00;
  --orange-d: rgba(234,111,0,.12);
  --bg:       #f0f2f8;
  --card:     #ffffff;
  --bdr:      rgba(12,28,62,.07);
  --txt:      #0c1c3e;
  --sub:      #64748b;
  --r:  14px;
  --rl: 20px;
  --rx: 26px;
  --s1: 0 1px 5px rgba(12,28,62,.06);
  --s2: 0 5px 20px rgba(12,28,62,.09);
  --s3: 0 14px 44px rgba(12,28,62,.13);
  --ease: .22s cubic-bezier(.4,0,.2,1);
  --font: 'Cairo', sans-serif;
}

.type-page-wrap {
  font-family: var(--font);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 80vh;
  padding: 40px 20px;
  gap: 50px;
  width: 100%;
}

/* ── Banner ── */
.type-banner {
  position: relative;
  overflow: hidden;
  width: 100%;
  border-radius: var(--rx);
  background: linear-gradient(125deg, var(--navy) 0%, var(--navy-m) 50%, var(--navy-l) 100%);
  padding: 30px 36px;
  box-shadow: var(--s3);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  animation: fadeUp .45s cubic-bezier(.4,0,.2,1) both;
}

.type-banner::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(255,255,255,.055) 1px, transparent 1px);
  background-size: 20px 20px;
  pointer-events: none;
}

.type-banner::after {
  content: '';
  position: absolute;
  right: -60px; top: -60px;
  width: 220px; height: 220px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(232,184,0,.28) 0%, transparent 68%);
  pointer-events: none;
}

.banner-left {
  position: relative;
  z-index: 1;
}

.banner-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(232,184,0,.15);
  border: 1px solid rgba(232,184,0,.3);
  color: var(--gold-l);
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  padding: 3px 12px;
  border-radius: 50px;
  margin-bottom: 10px;
}

.banner-badge i { font-size: .5rem; }

.banner-title {
  font-size: 1.7rem;
  font-weight: 900;
  color: #fff;
  line-height: 1.2;
}

.banner-sub {
  margin-top: 6px;
  font-size: .85rem;
  color: rgba(255,255,255,.5);
  font-weight: 400;
}

.banner-emoji {
  position: relative;
  z-index: 1;
  font-size: 3.4rem;
  flex-shrink: 0;
  animation: bob 4s ease-in-out infinite;
  filter: drop-shadow(0 3px 10px rgba(232,184,0,.35));
}

@keyframes bob {
  0%,100%{ transform: translateY(0) rotate(-4deg); }
  50%    { transform: translateY(-10px) rotate(4deg); }
}

/* ── Cards Section ── */
.type-cards {
  display: grid;
  grid-template-columns: repeat(2, minmax(260px, 1fr));
  gap: 28px;
  width: 100%;
}

.type-card {
  min-width: 0;
  text-decoration: none;
  color: inherit;
  animation: fadeUp .45s cubic-bezier(.4,0,.2,1) both;
}

.type-card:nth-child(1) { animation-delay: .07s; }
.type-card:nth-child(2) { animation-delay: .14s; }
.type-card:nth-child(3) { animation-delay: .21s; }

@keyframes fadeUp {
  from { opacity:0; transform:translateY(14px); }
  to   { opacity:1; transform:translateY(0); }
}

.type-card-inner {
  background: var(--card);
  border: 1.5px solid var(--bdr);
  border-radius: var(--rx);
  padding: 36px 24px 28px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 18px;
  box-shadow: var(--s2);
  transition: all var(--ease);
  text-align: center;
  position: relative;
  overflow: hidden;
}

.type-card-inner::before {
  content: '';
  position: absolute;
  top: -50px; right: -50px;
  width: 130px; height: 130px;
  border-radius: 50%;
  background: var(--icon-glow, var(--gold-d));
  transition: opacity var(--ease);
  opacity: .7;
  pointer-events: none;
}

.type-card:hover .type-card-inner {
  transform: translateY(-8px);
  box-shadow: var(--s3);
  border-color: var(--icon-color, var(--gold));
}

.type-card-icon {
  width: 72px;
  height: 72px;
  border-radius: 20px;
  background: var(--icon-bg, var(--gold-d));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.9rem;
  color: var(--icon-color, var(--gold));
  transition: all var(--ease);
  position: relative;
  z-index: 1;
}

.type-card:hover .type-card-icon {
  background: var(--icon-color, var(--gold));
  color: #fff;
  transform: scale(1.1) rotate(-4deg);
  box-shadow: 0 8px 24px rgba(0,0,0,.18);
}

.type-card-label {
  font-size: 1.1rem;
  font-weight: 800;
  color: var(--txt);
  transition: color var(--ease);
  position: relative;
  z-index: 1;
}

.type-card:hover .type-card-label {
  color: var(--icon-color, var(--gold));
}

.type-card-desc {
  font-size: .8rem;
  font-weight: 500;
  color: var(--sub);
  position: relative;
  z-index: 1;
  line-height: 1.5;
}

.type-card-arrow {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--icon-bg, var(--gold-d));
  color: var(--icon-color, var(--gold));
  border-radius: 50px;
  padding: 6px 18px;
  font-size: .78rem;
  font-weight: 700;
  transition: all var(--ease);
  position: relative;
  z-index: 1;
}

.type-card:hover .type-card-arrow {
  background: var(--icon-color, var(--gold));
  color: #fff;
  box-shadow: 0 4px 14px rgba(0,0,0,.18);
}

/* Back button */
.type-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--card);
  color: var(--sub);
  border: 1.5px solid var(--bdr);
  border-radius: 50px;
  padding: 8px 22px;
  font-family: var(--font);
  font-size: .82rem;
  font-weight: 700;
  text-decoration: none;
  box-shadow: var(--s1);
  transition: all var(--ease);
}

.type-back:hover {
  background: var(--navy);
  color: #fff;
  border-color: var(--navy);
  box-shadow: var(--s2);
}

@media (max-width: 600px) {
  .type-cards {
    grid-template-columns: 1fr;
    gap: 16px;
  }
  .banner-title { font-size: 1.3rem; }
  .type-banner  { padding: 22px 20px; }
}
</style>

<div class="type-page-wrap">

  <!-- Banner -->
  <div class="type-banner">
    <div class="banner-left">
      <div class="banner-badge">
        <i class="fas fa-circle"></i>
        ساعات العمل
      </div>
      <h1 class="banner-title">اختر نوع الآلية</h1>
      <p class="banner-sub">حدد تصنيف الآلية لبدء إدخال ساعات العمل</p>
    </div>
    <div class="banner-emoji">⚙️</div>
  </div>

  <!-- Selection Cards -->
  <div class="type-cards">

    <!-- معدات ثقيلة -->
    <a href="timesheet.php?type=1" class="type-card"
       style="--icon-color:#e8b800; --icon-bg:rgba(232,184,0,.12); --icon-glow:rgba(232,184,0,.10);">
      <div class="type-card-inner">
        <div class="type-card-icon">
          <i class="fas fa-tractor"></i>
        </div>
        <div class="type-card-label">معدات ثقيلة</div>
        <div class="type-card-desc">حفارات، لودرات، جريدرات<br>وجميع المعدات الثقيلة</div>
        <span class="type-card-arrow">
          <i class="fas fa-arrow-left"></i> ابدأ الإدخال
        </span>
      </div>
    </a>

    <!-- شاحنات -->
    <a href="timesheet.php?type=2" class="type-card"
       style="--icon-color:#2563eb; --icon-bg:rgba(37,99,235,.12); --icon-glow:rgba(37,99,235,.09);">
      <div class="type-card-inner">
        <div class="type-card-icon">
          <i class="fas fa-truck"></i>
        </div>
        <div class="type-card-label">الشاحنات</div>
        <div class="type-card-desc">قلابات، شاحنات نقل<br>وجميع مركبات النقل</div>
        <span class="type-card-arrow">
          <i class="fas fa-arrow-left"></i> ابدأ الإدخال
        </span>
      </div>
    </a>

  </div>

  <!-- Back -->
  <a href="../main/dashboard.php" class="type-back">
    <i class="fas fa-arrow-right"></i>
    العودة للرئيسية
  </a>

</div>

</body>
</html>

