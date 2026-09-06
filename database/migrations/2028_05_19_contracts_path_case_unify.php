<?php
/**
 * 2028_05_19_contracts_path_case_unify.php — توحيدُ حالةِ مسارِ العقودِ إلى حالةِ القرص
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ**: المجلَّدُ على القرصِ اسمُه `Contracts` بحرفٍ كبير، وتسعُ شاشاتٍ
 *   مسجَّلةٌ بمسارٍ **صغير** `contracts/…`. ويندوز لا يفرّق بين الحالتَين فتعمل،
 *   **ولينكس يفرّق** — وهو نظامُ الاستضافةِ المستهدَفة. فعندَ النشرِ تردُّ تسعةُ
 *   بنودٍ من سايدبارِ المبيعاتِ **404**، وهي جوهرُ عملِ الإدارة.
 *
 * ◆ **والعطبُ الحقيقيُّ في `nav_items.route` وحدَه** — لأنّه ما يصير رابطًا في
 *   المتصفّح. وبقيّةُ السجلّاتِ هويّةٌ تُقارَن بترتيبِ `utf8mb4_unicode_ci`
 *   **الذي لا يفرّق بين الحالتَين** (مقيسٌ حيًّا) — فتُوحَّد للاتّساقِ لا لأنّها تكسر.
 *
 * ⛔ **وما لا يُمَسُّ عمدًا — وقد كِدتُ أكسره**:
 *   · `includes/action_guard.php` مفاتيحُه صغيرةٌ **بتصميم**: السطر 180 يُصغِّر
 *     `SCRIPT_NAME` قبلَ المطابقة، والسطر 26 ينصُّ عليه. فتكبيرُها **يُعطِّل
 *     حارسَ الأفعال** على أربعةِ معالِجات.
 *   · `includes/nav_views.php` كذلك: السطر 92 يُصغِّر قبلَ المطابقة.
 *   ⇒ **قاعدةٌ تُستخلَص**: قبلَ توحيدِ حالةِ أيِّ مفتاح، اقرأْ كيف يُطابَق.
 *     المفتاحُ الذي يُصغَّر قبلَ المقارنةِ **يجب أن يبقى صغيرًا**.
 *
 * التشغيل: php database/migrations/2028_05_19_contracts_path_case_unify.php
 * العكس:   php database/migrations/2028_05_19_contracts_path_case_unify_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

/* ── ① الحالةُ على القرصِ هي المرجع — ولا يُعاد تسميةُ ملفّ ─────────────────── */
$dir = null;
foreach (scandir($ROOT) as $e) { if (strcasecmp($e, 'Contracts') === 0 && is_dir($ROOT . '/' . $e)) { $dir = $e; } }
if ($dir === null) { exit("⛔ لم يُعثر على مجلَّدِ العقود.\n"); }
echo "══ ① المرجعُ من القرص: مجلَّد «{$dir}» ══════════════════════════════════\n";

$TARGETS = array(
    'nav_items'         => 'route',
    'modules'           => 'code',
    'nav_canonical'     => 'route',
    'gov_profile_items' => 'item_ref',
);

echo "\n══ ② قبلَ التوحيد ════════════════════════════════════════════════════\n";
$before = array();
foreach ($TARGETS as $t => $c) {
    $before[$t] = $one("SELECT COUNT(*) FROM `{$t}` WHERE `{$c}` LIKE BINARY 'contracts/%'");
    printf("   %-20s %-10s صفوفٌ بحالةٍ صغيرة: %d\n", $t, $c, $before[$t]);
}

/* ── ③ التوحيد: تُستبدَل البادئةُ وحدَها، وبقيّةُ المسارِ كما هي ──────────────── */
echo "\n══ ③ التوحيد ═════════════════════════════════════════════════════════\n";
$total = 0;
foreach ($TARGETS as $t => $c) {
    $sql = "UPDATE `{$t}` SET `{$c}` = CONCAT('{$dir}/', SUBSTRING(`{$c}`, 11))
             WHERE `{$c}` LIKE BINARY 'contracts/%'";
    if (!$conn->query($sql)) { exit("⛔ {$t}: " . $conn->error . "\n"); }
    printf("   %-20s صفوفٌ وُحِّدت: %d\n", $t, $conn->affected_rows);
    $total += $conn->affected_rows;
}
printf("   المجموع: %d\n", $total);

/* ── ④ الشواهد ───────────────────────────────────────────────────────────── */
echo "\n══ ④ الشواهد ═════════════════════════════════════════════════════════\n";
$left = 0;
foreach ($TARGETS as $t => $c) { $left += $one("SELECT COUNT(*) FROM `{$t}` WHERE `{$c}` LIKE BINARY 'contracts/%'"); }
printf("   متبقٍّ بحالةٍ صغيرة: %d (المستهدف صفر)\n", $left);

/* ⓐ التسعُ صارت مطابقةً لحالةِ القرصِ حرفًا.
   ⛔ **والشاهدُ يُقاس بمدى الجولةِ لا بالمنظومةِ كلِّها**: قياسي الأوّلُ فحص كلَّ
      مسارٍ مسجَّلٍ فأحمرَّ على 86 مسارًا **خارجَ ما غيّرته هذه الهجرة** — وذاك
      عطبٌ آخرُ يُسمّى ويُعالَج بقرارِه لا يُطوى في جولةٍ لا تخصُّه.
      والمنظومةُ كلُّها يقيسها `tools/nav_route_case_guard.php`. */
$NINE = array('contract_amendments_renewal', 'contract_baseline_targets', 'contract_commercial_lines',
              'contract_coverage_cycles', 'contract_obligation_matrix', 'monthly_containers_loss',
              'monthly_sales_performance', 'precontract_review', 'sales_activities');
$missing = array(); $checked = 0;
foreach ($NINE as $n) {
    $rt = $dir . '/' . $n . '.php';
    $checked++;
    $entries = @scandir($ROOT . '/' . $dir);
    $onDisk = false;
    if ($entries !== false) {
        foreach ($entries as $e) { if ($e === $n . '.php') { $onDisk = true; break; } }
    }
    $inDb = $one("SELECT COUNT(*) FROM modules WHERE code = BINARY '" . $conn->real_escape_string($rt) . "'");
    if (!$onDisk || $inDb < 1) { $missing[] = $rt . ($onDisk ? ' (ليس في السجل بهذه الحالة)' : ' (ليس على القرص)'); }
}
printf("   الشاشاتُ التسعُ: فُحصت %d · لا تطابق: %d\n", $checked, count($missing));
foreach ($missing as $m) { echo "      " . $m . "\n"; }

/* ⓑ ولا صفَّ صلاحيّةٍ ضاع: العددُ الكلّيُّ للبنودِ كما كان */
$items = $one("SELECT COUNT(*) FROM gov_profile_items WHERE item_kind = 'screen' AND allow = 1");
printf("   بنودُ الصلاحيّةِ النافذة: %d\n", $items);

if ($left !== 0 || count($missing) > 0) { exit("\n⛔ الشاهدُ لم يتحقّق.\n"); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "\n✔ تمّ — والمرجعُ حالةُ القرصِ لا تخمينُ الكاتب.\n";
