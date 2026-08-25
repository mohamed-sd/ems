<?php
/**
 * Transport/cron_transfer.php — محرّك التنبيهات المجدوَل (§7.4 / §4).
 * على نمط Finance/cron_finance_fin.php. يُشغَّل عبر جدولة النظام (CLI) أو GET بمفتاح.
 *
 * يكتشف من الجداول المملوكة فقط:
 *   1) تأخّر الرحلة        planned_date < اليوم والمرحلة ليست (وصل/مغلق/ملغى)
 *   2) عدم تأكيد الوصول    قيد الرحلة ومضى على المغادرة > 2 يوم
 *   3) قرب انتهاء التصريح  expiry_date ضمن نافذة 7 أيام والتصريح غير منتهٍ
 *   4) عتبة الستين يومًا   مشروع أمرٍ مفتوح بلغت مدته 60 يومًا (تحوّل متحمِّل العودة)
 *
 * الأثر: INSERT في trs_notifications (جدول مملوك جديد، مع dedupe يومي) + حدث في transfer_events.
 * صفر مساس بأي جدول قائم — لا يكتب في fin_* ولا في جرس النظام القائم.
 */
$IS_CLI = (PHP_SAPI === 'cli');
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/cron_guard.php';
ems_cron_guard('cron_transfer.php'); // INJ-0025: لا تُشغَّل من المتصفّح
require_once __DIR__ . '/trs_helpers.php';

// حارس التشغيل عبر المتصفح: ?key=... يُطابَق مع TRANSPORT_CRON_KEY من .env (ADR-04).
// fail-closed: مفتاح غير مضبوط في .env = لا مسار ويب إطلاقًا (CLI لا يتأثر).
if (!$IS_CLI) {
    $key = isset($_GET['key']) ? (string) $_GET['key'] : '';
    $expected = (string) ems_env('TRANSPORT_CRON_KEY', '');
    if ($expected === '' || !hash_equals($expected, $key)) { http_response_code(403); exit('forbidden'); }
    header('Content-Type: text/plain; charset=UTF-8');
}

// K9-M2b: القناة الخامسة forSystem (عقد §11) — حلقة شركاتٍ ببوابةٍ معزولةٍ لكل دورة.
// التفويض: CLI أو مفتاح cron المتحقق أعلاه (hash_equals ضد مفتاح .env غير الفارغ).
require_once __DIR__ . '/../app/Core/TenantGateException.php';
require_once __DIR__ . '/../app/Core/TenantRegistry.php';
require_once __DIR__ . '/../app/Core/TenantContext.php';
require_once __DIR__ . '/../app/Core/TenantDb.php';

$today = date('Y-m-d');
$MGR_ROLE = 23;      // مدير النقل والترحيل (الجهة المُخطَرة الأساسية حاليًّا)
$n_delayed = $n_noarr = $n_permit = $n_sixty = 0;
$SYS_AUTH = true;    // بلغنا هذه النقطة = CLI أو المفتاح تحقق (الحارس أعلاه fail-closed)

/** يسجّل حدث تنبيه على الأمر مرّة واحدة يوميًّا (عبر بوابة الدورة المحقونة). */
function trs_alert(&$counter, $conn, $cid, $order_id, $type, $title, $body, $link, $dedupe) {
    if (trs_notify($conn, $cid, $type, $title, $body, $order_id, $GLOBALS['MGR_ROLE'], $link, $dedupe)) {
        $counter++;
        if ($order_id) { trs_log_event($conn, $cid, $order_id, 'alert', $title . ($body ? ' — ' . $body : ''), null, null, null, 'transport'); }
    }
}

// تعداد الشركات ذات الأوامر: سياق نظامٍ super + forAllTenants المسجَّلة (لا خام)
$enumGate = new \App\Core\TenantDb($conn,
    \App\Core\TenantContext::forSystem(0, 0, defined('EMS_ROLE_SUPER_ADMIN') ? EMS_ROLE_SUPER_ADMIN : '-1', $SYS_AUTH),
    false, 'enforce');
$company_ids = array();
foreach ($enumGate->forAllTenants('transport cron company enumeration')
             ->select('transfer_orders', array('columns' => array('company_id'), 'includeDeleted' => true)) as $row) {
    $company_ids[intval($row['company_id'])] = true;
}

foreach (array_keys($company_ids) as $cid) {
    // بوابة دورةٍ معزولة بشركةٍ واحدة صريحة — تُحقن في helpers الوحدة
    $cycleGate = new \App\Core\TenantDb($conn,
        \App\Core\TenantContext::forSystem($cid, 0, '', $SYS_AUTH), false, 'enforce');
    trs_gate_override($cycleGate);

    // 1) تأخّر الرحلة
    foreach ($cycleGate->select('transfer_orders', array(
        'columns' => array('id', 'order_no', 'planned_date'),
        'whereRaw' => "planned_date IS NOT NULL AND planned_date < ? AND stage NOT IN ('arrived','closed','cancelled')",
        'params' => array($today),
    )) as $o) {
        $oid = (int)$o['id'];
        trs_alert($n_delayed, $conn, $cid, $oid, 'delayed',
            'رحلة متأخرة: ' . $o['order_no'],
            'تجاوزت التاريخ المخطط (' . $o['planned_date'] . ')',
            'transfer_order_form.php?id=' . $oid,
            'delayed:' . $oid . ':' . $today);
    }

    // 2) عدم تأكيد الوصول (مغادرة > يومين وما زالت قيد الرحلة)
    foreach ($cycleGate->select('transfer_orders', array(
        'columns' => array('id', 'order_no', 'departure_datetime'),
        'whereRaw' => "stage='in_transit' AND departure_datetime IS NOT NULL AND departure_datetime < (NOW() - INTERVAL 2 DAY)",
    )) as $o) {
        $oid = (int)$o['id'];
        trs_alert($n_noarr, $conn, $cid, $oid, 'no_arrival',
            'وصول غير مؤكد: ' . $o['order_no'],
            'مغادرة منذ ' . $o['departure_datetime'] . ' دون تأكيد وصول',
            'transfer_order_form.php?id=' . $oid,
            'no_arrival:' . $oid . ':' . $today);
    }

    // 3) قرب انتهاء التصريح (خلال 7 أيام) — ترطيبٌ ثنائي بدل JOIN
    $permits = $cycleGate->select('transfer_permits', array(
        'columns' => array('id', 'order_id', 'permit_type', 'expiry_date'),
        'whereRaw' => "expiry_date IS NOT NULL AND state <> 'expired' AND expiry_date BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)",
        'params' => array($today, $today),
    ));
    if (!empty($permits)) {
        $ord_ids = array();
        foreach ($permits as $p) { $ord_ids[intval($p['order_id'])] = true; }
        $orders_map = array();
        foreach ($cycleGate->select('transfer_orders', array(
            'columns' => array('id', 'order_no', 'stage'),
            'whereRaw' => 'id IN (' . implode(',', array_map('intval', array_keys($ord_ids))) . ')',
        )) as $om) { $orders_map[intval($om['id'])] = $om; }
        $ptypes = trs_permit_types();
        foreach ($permits as $p) {
            $oid = (int)$p['order_id'];
            $om = isset($orders_map[$oid]) ? $orders_map[$oid] : null;
            if ($om === null || in_array($om['stage'], array('closed', 'cancelled'), true)) { continue; }
            trs_alert($n_permit, $conn, $cid, $oid, 'permit_expiry',
                'تصريح يقارب الانتهاء: ' . $om['order_no'],
                trs_label($ptypes, $p['permit_type']) . ' — ينتهي في ' . $p['expiry_date'],
                'transfer_order_form.php?id=' . $oid . '&tab=permits',
                'permit_expiry:' . (int)$p['id'] . ':' . $today);
        }
    }

    // 4) عتبة الستين يومًا
    foreach ($cycleGate->select('transfer_orders', array(
        'columns' => array('id', 'order_no', 'project_id'),
        'whereRaw' => "project_id IS NOT NULL AND stage NOT IN ('closed','cancelled')",
    )) as $o) {
        $oid = (int)$o['id'];
        $pd = trs_project_days($conn, $cid, (int)$o['project_id']);
        if ($pd !== null && $pd >= 60) {
            trs_alert($n_sixty, $conn, $cid, $oid, 'sixty_day',
                'بلوغ عتبة الستين يوما: ' . $o['order_no'],
                'مدة المشروع ' . $pd . ' يوم — راجع متحمل العودة (تحول محتمل إلى الشركة)',
                'transfer_order_form.php?id=' . $oid,
                'sixty_day:' . $oid . ':' . $today);
        }
    }

    trs_gate_override(null); // نهاية الدورة — لا بوابة معلّقة بين الشركات
}

$out = "[transport-cron $today] delayed=$n_delayed no_arrival=$n_noarr permit_expiry=$n_permit sixty_day=$n_sixty\n";
echo $out;
