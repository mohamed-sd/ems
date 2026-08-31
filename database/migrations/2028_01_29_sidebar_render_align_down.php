<?php
/**
 * 2028_01_29_sidebar_render_align_down.php — تراجعُ محاذاةِ السايدبارِ المُصيَّرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **التراجعُ يردُّ الإعلاناتِ إلى ما قبلَ المحاذاةِ من السجلِّ نفسِه**:
 *   القائمُ (`had_row=1`) تُردُّ قيمُه الثلاثُ في صفِّه · والمُدرَجُ
 *   (`had_row=0`) يُحذف صفُّه المُدرَجُ بعينِه — ثمَّ يُسقَط السجلّ.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$restored = 0; $deleted = 0;
$q = @$conn->query("SELECT had_row, gt_id, before_group_ar, before_group_no, before_item_no
                      FROM repair01_render_align WHERE applied_at IS NOT NULL AND gt_id IS NOT NULL");
while ($q && ($x = $q->fetch_assoc())) {
    if ((int) $x['had_row'] === 1) {
        $ok = $conn->query("UPDATE `gov_target_nav`
                               SET `group_ar` = '" . $conn->real_escape_string($x['before_group_ar']) . "',
                                   `group_no` = " . max(0, (int) $x['before_group_no']) . ",
                                   `item_no`  = " . max(0, (int) $x['before_item_no']) . "
                             WHERE `id` = " . (int) $x['gt_id']);
        if ($ok) { $restored += $conn->affected_rows; }
    } else {
        $ok = $conn->query("DELETE FROM `gov_target_nav`
                             WHERE `id` = " . (int) $x['gt_id'] . " AND `doc_code` LIKE 'RENDER-ALIGN%'");
        if ($ok) { $deleted += $conn->affected_rows; }
    }
}
echo "  ✔ رُدَّ إعلانٌ قائم: $restored · حُذف مُدرَج: $deleted\n";
if (!$conn->query("DROP TABLE IF EXISTS `repair01_render_align`")) {
    exit("✘ تعذّر الإسقاط: {$conn->error}\n");
}
echo "  ✔ أُسقط `repair01_render_align`\n";
