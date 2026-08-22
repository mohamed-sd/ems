<?php
/**
 * 2027_10_06_shared_surface_name_uniformity.php
 *   السطحُ المشتركُ اسمُه واحدٌ عبرَ الأدوار — ولو سمّته وثيقةُ إدارةٍ بغيرِه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما كشفته بوابةُ الواجهة U2**: `tickets_list.php?tab=dept` صار يُصيَّر
 *   باسمَين — «صندوقُ بلاغاتِ الإدارة» في سبعةَ عشرَ دورًا، و«البلاغاتُ والمهل»
 *   في المبيعاتِ والموردين لأن وثيقتَيهما تسمّيان بندَ المجموعةِ بذلك.
 *
 * ◆ **والحكمُ لصالحِ التوحيد**: «ممنوعٌ اسمان لشاشةٍ واحدة» قاعدةٌ في الوثيقتين
 *   نفسِهما. وهذا السطحُ **طبقةُ عملٍ شخصيةٍ مركزيةٌ مشتركةٌ على مستوى المنصةِ
 *   كلِّها** — بنصِّ الوثيقتين أيضًا («ولا تُبنى نسخةٌ مستقلةٌ منه داخلَ كلِّ
 *   إدارة»). فوثيقةُ إدارةٍ **لا تملك تسميةَ سطحٍ مشترك**، وتسميتُه لبندٍ في
 *   مجموعتِها وصفٌ لدورِه لا اسمٌ كنسيٌّ له.
 *
 * ◆ **ولا يُعمَّم اسمُ الوثيقةِ على سبعةَ عشرَ دورًا** — ذلك تغييرُ تسميةٍ
 *   على مستوى المنصةِ لا تملكه وثيقةُ إدارة. فالاسمُ المستقرُّ يبقى، ويُقيَّد
 *   السببُ في سجلِّ التنقّلِ المستهدَف.
 *
 * التشغيل:  php database/migrations/2027_10_06_shared_surface_name_uniformity.php
 * الرجوع :  php database/migrations/2027_10_06_shared_surface_name_uniformity.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ROUTE = 'Tickets/tickets_list.php?tab=dept';
$DOCNAME = 'البلاغات والمهل';
$NOTE = 'اسمُ البندِ في وثيقةِ الإدارةِ «البلاغات والمهل» وصفٌ لدورِه في المجموعة — '
      . 'والاسمُ الكنسيُّ للسطحِ المشتركِ يبقى موحَّدًا عبرَ الأدوارِ كلِّها';

if (in_array('--revert', $argv, true)) {
    $st = $conn->prepare("UPDATE `nav_items` SET `label_ar` = ?
                           WHERE `route` = ? AND `role_id` IN (12,2)");
    $st->bind_param('ss', $DOCNAME, $ROUTE); $st->execute();
    echo "↺ أُعيد {$st->affected_rows} اسمًا إلى تسميةِ الوثيقة\n";
    $st->close();
    exit(0);
}

/* الاسمُ السائدُ عبرَ الأدوارِ الأخرى — يُقرأ ولا يُكتب يدًا */
$q = $conn->query("SELECT `label_ar`, COUNT(*) n FROM `nav_items`
                    WHERE `route` = '" . $conn->real_escape_string($ROUTE) . "'
                      AND `active` = 1 AND `role_id` NOT IN (12,2)
                    GROUP BY `label_ar` ORDER BY n DESC LIMIT 1");
$dom = $q ? $q->fetch_assoc() : null;
if (!$dom) { exit("✘ لا اسمَ سائدًا يُقاس — أُوقفت الهجرة\n"); }
printf("① الاسمُ السائدُ عبرَ الأدوارِ الأخرى: «%s» (%d دورًا)\n", $dom['label_ar'], (int) $dom['n']);

$st = $conn->prepare("UPDATE `nav_items` SET `label_ar` = ?
                       WHERE `route` = ? AND `role_id` IN (12,2) AND `label_ar` <> ?");
$st->bind_param('sss', $dom['label_ar'], $ROUTE, $dom['label_ar']);
$st->execute();
printf("② وُحِّد %d صفًّا على الاسمِ السائد\n", $st->affected_rows);
$st->close();

$st = $conn->prepare("UPDATE `gov_target_nav` SET `note` = ? WHERE `route` = ?");
$st->bind_param('ss', $NOTE, $ROUTE);
$st->execute();
printf("③ قُيِّد السببُ في سجلِّ التنقّلِ المستهدَف (%d صفًّا)\n", $st->affected_rows);
$st->close();

ems_migration_recorded(__FILE__, $conn, 0);
