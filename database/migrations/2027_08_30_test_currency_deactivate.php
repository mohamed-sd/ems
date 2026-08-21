<?php
/**
 * 2027_08_30_test_currency_deactivate.php
 *   عملةُ اختبارٍ نشطةٌ في جدولٍ إنتاجيّ — INJ-FIX-01 · GAP-25
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **القرارُ مُتَّخَذٌ بتفويضِ المالك (2026-08-21)** ومبناهُ قياس:
 *   `fin_currencies` يحمل `TSR` باسمِ **«ريالُ اختبار»** و`active = 1` — فهي
 *   تظهر في كلِّ منتقي عملةٍ في النظامِ كعملةٍ حقيقية.
 *
 * ◆ **والقياسُ يُبيح التعطيلَ بلا فقد**: صفرُ استعمالٍ حيٍّ — لا في `fin_dues`
 *   ولا `settlements` ولا `fin_journal_entries` ولا `fin_requests` ولا
 *   `fin_financial_events` ولا `fin_fx_rates`. فلا صفَّ واحدٌ يُسعَّر بها.
 *
 * ◆ **ولا تُحذف بل تُعطَّل**: الحذفُ يكسر أيَّ مرجعٍ تاريخيٍّ قد يظهر، والتعطيلُ
 *   يُخرجها من المنتقياتِ ويُبقي سجلَّها. و`--revert` يعيدها بسطرٍ واحد.
 *
 * ◆ **وهذا لا يُغلق GAP-25**: ستُّ عملاتٍ نشطةٍ ما تزال **بلا سعرِ صرفٍ**
 *   (AED · EUR · QAR · SAR · SDG@co1 · TSR). وسعرُ الصرفِ **بيانٌ ماليٌّ له
 *   مصدر** — واختراعُه ليصير البندُ أخضرَ عينُ ما يمنعه «لا رقمَ بلا مصدر».
 *   فالتعطيلُ يُنقص المقامَ بواحدةٍ **بحقّ** (عملةُ اختبارٍ ليست عملةَ عمل)،
 *   والخمسُ الباقياتُ تنتظر أسعارَها من المالية.
 *   ◆ وطبقةُ الصرفِ **fail-closed أصلًا**: `ems_fx_rate` تُرجع `null` بلا سعرٍ
 *     يغطّي التاريخ، و`ems_fx_convert` تردُّ بسببٍ مكتوب — فلا تُسعَّر معاملةٌ
 *     بعملةٍ بلا سعر. **فالآليةُ سليمةٌ والبياناتُ ناقصة.**
 *
 * التشغيل:  php database/migrations/2027_08_30_test_currency_deactivate.php
 * الرجوع :  php database/migrations/2027_08_30_test_currency_deactivate.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$CODE = 'TSR';

if (in_array('--revert', $argv, true)) {
    $conn->query("UPDATE `fin_currencies` SET `active` = 1 WHERE `code` = '{$CODE}'");
    echo "↺ أُعيد تنشيطُ {$CODE} ({$conn->affected_rows} صفًّا)\n";
    exit(0);
}

/* ══ ① التحقُّقُ قبلَ الفعل — لا يُعطَّل ما يُستعمل ═══════════════════════ */
$used = 0;
foreach (array('fin_dues', 'settlements', 'fin_journal_entries', 'fin_requests',
               'fin_financial_events') as $t) {
    $c = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}' AND COLUMN_NAME = 'currency'");
    if (!$c || (int) $c->fetch_row()[0] === 0) { continue; }
    $q = $conn->query("SELECT COUNT(*) FROM `{$t}` WHERE `currency` = '{$CODE}'");
    $used += $q ? (int) $q->fetch_row()[0] : 0;
}
$q = $conn->query("SELECT COUNT(*) FROM `fin_fx_rates` WHERE `currency_code` = '{$CODE}'");
$used += $q ? (int) $q->fetch_row()[0] : 0;

echo "① الاستعمالُ الحيُّ لـ{$CODE}: {$used}\n";
if ($used > 0) {
    exit("✘ مُستعمَلةٌ فعلًا — لا تُعطَّل بلا قرارِ مالك. أُوقفت الهجرة.\n");
}

/* ══ ② التعطيل ═══════════════════════════════════════════════════════════ */
$conn->query("UPDATE `fin_currencies` SET `active` = 0 WHERE `code` = '{$CODE}' AND `active` = 1");
echo "② عُطِّلت {$CODE}: {$conn->affected_rows} صفًّا\n";

/* ══ ③ ما بقي من GAP-25 — يُقاس ويُعلَن ═══════════════════════════════════ */
echo "───────────────────────────────────────────────────────────────\n";
$q = $conn->query(
    "SELECT c.company_id, c.code
       FROM `fin_currencies` c
      WHERE c.active = 1
        AND NOT EXISTS (SELECT 1 FROM `fin_fx_rates` r
                         WHERE r.company_id = c.company_id AND r.currency_code = c.code
                           AND COALESCE(r.is_deleted, 0) = 0)
      ORDER BY c.company_id, c.code");
$missing = array();
while ($q && $x = $q->fetch_assoc()) { $missing[] = 'co' . $x['company_id'] . ':' . $x['code']; }
$q = $conn->query("SELECT COUNT(*) FROM `fin_currencies` WHERE `active` = 1");
$act = $q ? (int) $q->fetch_row()[0] : 0;
echo "عملاتٌ نشطة: {$act} · **بلا سعرِ صرف: " . count($missing) . "** — " . implode(' · ', $missing) . "\n";
echo "◆ وهذه تنتظر أسعارَها من المالية — ولا تُخترع (BLOCKED_OWNER_INPUT).\n";
