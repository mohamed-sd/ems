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
require_once __DIR__ . '/trs_helpers.php';

if (!$IS_CLI) {
    $key = isset($_GET['key']) ? $_GET['key'] : '';
    if ($key !== 'transport-cron') { http_response_code(403); exit('forbidden'); }
    header('Content-Type: text/plain; charset=UTF-8');
}

$today = date('Y-m-d');
$MGR_ROLE = 23;      // مدير النقل والترحيل (الجهة المُخطَرة الأساسية حاليًّا)
$n_delayed = $n_noarr = $n_permit = $n_sixty = 0;

/** يسجّل حدث تنبيه على الأمر مرّة واحدة يوميًّا (يتبع نفس مفتاح المنع). */
function trs_alert(&$counter, $conn, $cid, $order_id, $type, $title, $body, $link, $dedupe) {
    if (trs_notify($conn, $cid, $type, $title, $body, $order_id, $GLOBALS['MGR_ROLE'], $link, $dedupe)) {
        $counter++;
        if ($order_id) { trs_log_event($conn, $cid, $order_id, 'alert', $title . ($body ? ' — ' . $body : ''), null, null, null, 'transport'); }
    }
}

// 1) تأخّر الرحلة
$r = mysqli_query($conn, "SELECT id, company_id, order_no, planned_date FROM transfer_orders
    WHERE planned_date IS NOT NULL AND planned_date < '$today' AND stage NOT IN('arrived','closed','cancelled')");
if ($r) { while ($o = mysqli_fetch_assoc($r)) {
    $cid = (int)$o['company_id']; $oid = (int)$o['id'];
    trs_alert($n_delayed, $conn, $cid, $oid, 'delayed',
        'رحلة متأخّرة: ' . $o['order_no'],
        'تجاوزت التاريخ المخطط (' . $o['planned_date'] . ')',
        'transfer_order_form.php?id=' . $oid,
        'delayed:' . $oid . ':' . $today);
} }

// 2) عدم تأكيد الوصول (مضى على المغادرة أكثر من يومين وما زالت قيد الرحلة)
$r = mysqli_query($conn, "SELECT id, company_id, order_no, departure_datetime FROM transfer_orders
    WHERE stage='in_transit' AND departure_datetime IS NOT NULL AND departure_datetime < (NOW() - INTERVAL 2 DAY)");
if ($r) { while ($o = mysqli_fetch_assoc($r)) {
    $cid = (int)$o['company_id']; $oid = (int)$o['id'];
    trs_alert($n_noarr, $conn, $cid, $oid, 'no_arrival',
        'وصول غير مؤكَّد: ' . $o['order_no'],
        'مغادرة منذ ' . $o['departure_datetime'] . ' دون تأكيد وصول',
        'transfer_order_form.php?id=' . $oid,
        'no_arrival:' . $oid . ':' . $today);
} }

// 3) قرب انتهاء التصريح (خلال 7 أيام)
$r = mysqli_query($conn, "SELECT pm.id AS pid, pm.order_id, pm.company_id, pm.permit_type, pm.expiry_date, o.order_no
    FROM transfer_permits pm JOIN transfer_orders o ON o.id = pm.order_id
    WHERE pm.expiry_date IS NOT NULL AND pm.state <> 'expired'
      AND pm.expiry_date BETWEEN '$today' AND DATE_ADD('$today', INTERVAL 7 DAY)
      AND o.stage NOT IN('closed','cancelled')");
if ($r) { while ($p = mysqli_fetch_assoc($r)) {
    $cid = (int)$p['company_id']; $oid = (int)$p['order_id'];
    $ptypes = trs_permit_types();
    trs_alert($n_permit, $conn, $cid, $oid, 'permit_expiry',
        'تصريح يقارب الانتهاء: ' . $p['order_no'],
        trs_label($ptypes, $p['permit_type']) . ' — ينتهي في ' . $p['expiry_date'],
        'transfer_order_form.php?id=' . $oid . '&tab=permits',
        'permit_expiry:' . (int)$p['pid'] . ':' . $today);
} }

// 4) عتبة الستين يومًا — أوامر مفتوحة ذات مشروعٍ بلغت مدته 60 يومًا فأكثر
$r = mysqli_query($conn, "SELECT id, company_id, order_no, project_id, direction FROM transfer_orders
    WHERE project_id IS NOT NULL AND stage NOT IN('closed','cancelled')");
if ($r) { while ($o = mysqli_fetch_assoc($r)) {
    $cid = (int)$o['company_id']; $oid = (int)$o['id'];
    $pd = trs_project_days($conn, $cid, (int)$o['project_id']);
    if ($pd !== null && $pd >= 60) {
        trs_alert($n_sixty, $conn, $cid, $oid, 'sixty_day',
            'بلوغ عتبة الستين يومًا: ' . $o['order_no'],
            'مدة المشروع ' . $pd . ' يوم — راجع متحمِّل العودة (تحوّل محتمل إلى الشركة)',
            'transfer_order_form.php?id=' . $oid,
            'sixty_day:' . $oid . ':' . $today);
    }
} }

$out = "[transport-cron $today] delayed=$n_delayed no_arrival=$n_noarr permit_expiry=$n_permit sixty_day=$n_sixty\n";
echo $out;
