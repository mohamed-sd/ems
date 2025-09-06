<?php
$page_title = "إيكوبيشن | الآليات ";
include("../inheader.php");
include('../insidebar.php');
?>

<div class="main">

    <h2 style="text-align:center; margin-bottom:30px;">اختر نوع الآلية</h2>

    <div class="cards-container">

        <!-- كارد الحفارات -->
        <a href="timesheet.php?type=1" class="card-link">
            <div class="card-box">
                <i class="fa-solid fa-digging fa-3x"></i>
                <span>الحفارات</span>
            </div>
        </a>

        <!-- كارد القلابات -->
        <a href="timesheet.php?type=2" class="card-link">
            <div class="card-box">
                <i class="fa-solid fa-truck fa-3x"></i>
                <span>القلابات</span>
            </div>
        </a>

    </div>

</div>

<!-- تحسين التصميم -->
<style>
    .cards-container {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 40px;
        margin-top: 50px;
        flex-wrap: wrap;
    }

    .card-link {
        text-decoration: none;
        color: inherit;
    }

    .card-box {
        width: 200px;
        height: 170px;
        background: #f9f9f9;
        border-radius: 20px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 15px;
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        font-size: 22px;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .card-box:hover {
        background: #000022;
        color: #ffcc00;
        transform: translateY(-8px) scale(1.05);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        cursor: pointer;
    }

    @media(max-width:768px) {
        .cards-container {
            flex-direction: column !important;
            gap: 20px !important;
        }
    }
</style>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</body>
</html>
