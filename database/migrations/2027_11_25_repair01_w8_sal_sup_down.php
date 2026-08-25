<?php
/**
 * 2027_11_25_repair01_w8_sal_sup_down.php — تراجعُ هجرةِ W08
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ولا يُنزع ما لم تُنشئه هذه المرحلة**: الهجرةُ أنشأت **دفاترَ قياسٍ
 *   فقط** ولم تُلحِق عمودًا واحدًا بجدولِ أعمالٍ حيّ — فالوحدتانِ مرجعيّتانِ
 *   و§19 يأمر بالانحدارِ لا بالبناء. فالتراجعُ يُسقط الدفاترَ التسعةَ ولا
 *   يمسُّ `claims` ولا `settlements` ولا `supplier_contracts`.
 *
 * ⚠ **وإسقاطُ `repair01_w8_regression` يمحو شوطَ الأساس** — وهو الدليلُ
 *   الوحيدُ على حالِ الوحدتَين **قبل** أوّلِ لمسة. فيُصدَّر قبلَ الإسقاطِ إن
 *   كان له بقيّةُ نفعٍ في مراجعةٍ لاحقة.
 *
 * ⛔ **والتراجعُ البيانيُّ ليس هنا**: ما كتبته الأداةُ في `claims.receivable_id`
 *   وفي `repair01_screen_registry.owner_code` وفي `repair01_target_gaps.wave_stage`
 *   يُنزع بـ`php tools/repair01_w8_apply.php --revert` **قبل** هذه الهجرة.
 *
 * التشغيل: php database/migrations/2027_11_25_repair01_w8_sal_sup_down.php
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

echo "══ تراجعُ REPAIR01 · W08 ══\n\n";

/* وسمُ العقودِ يُنزع قبلَ الدفاتر — والحدثُ نفسُه يبقى فهو ليس من إنشائنا */
if ($conn->query("UPDATE repair01_events SET contract_status='NONE', contract_rule='', contract_stage='',
      trigger_rule='', min_payload='', consumer_list=NULL, consumer_effect=NULL,
      preconditions='', failure_policy='', compensation='' WHERE contract_stage='W08'") === true) {
    echo "  ✔ نُزع وسمُ عقودِ الأثرِ عن أحداثِ W08 (" . $conn->affected_rows . " حدثًا)\n";
}

$done = 0; $err = 0;
foreach (array('repair01_w8_fixes', 'repair01_w8_regression', 'repair01_w8_journey',
               'repair01_w8_sod', 'repair01_w8_states', 'repair01_w8_thresholds',
               'repair01_w8_decisions', 'repair01_w8_sidebar', 'repair01_w8_scope') as $t) {
    if ($conn->query("DROP TABLE IF EXISTS `$t`") === true) { echo "  ✔ أُسقط $t\n"; $done++; }
    else { echo "  ✘ $t — " . $conn->error . "\n"; $err++; }
}

echo "\n───────────────────────────────────────────────────────────────\n";
echo "الخلاصة: أُسقط $done دفترًا · أخطاء $err\n";
echo "⛔ ولم يُمَسَّ جدولُ أعمالٍ حيٌّ — الهجرةُ لم تُلحِق به شيئًا أصلًا.\n";
exit($err > 0 ? 1 : 0);
