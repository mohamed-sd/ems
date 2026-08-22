<?php
/**
 * 2027_10_22_frd_nav004_variant_ownership.php
 *   FR-NAV-004 · CHG-NAV-DERIVE-01 — المتغايرُ يرث مالكَ أساسِه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلبُ بنصِّه** (الدفتر · P1): «كلُّ سطحٍ **قابلٍ للتصييرِ** له مالكٌ
 *   نهائيّ — ولا يبقى ترجيحٌ مؤقتٌ محسوبًا محسومًا» · ومعيارُ القبول «صفرُ
 *   سطحٍ بلا أساسِ مِلكية» · وسالبُه «سطحٌ بلا أساسٍ أو بترجيحٍ مؤقت ← رسوب».
 *
 * ◆ **والحالُ المقيس**: 623 سطحًا في السجلِّ الموحَّد · **123 بلا حسمٍ نهائيّ**
 *   (110 `NONE` + 13 `MAJORITY`) — مطابقٌ لما في الدفتر.
 *
 * ◆ **والكشف**: **27 من الـ123 ليست أسطحًا مستقلّةً** بل **متغايراتِ شاشاتٍ
 *   مالكُها محسومٌ سلفًا** — مسارُها هو المسارُ نفسُه بذيلِ `?view=…` أو `#2`:
 *   `Equipments/equipments.php#2` مثلًا، وأساسُه `Equipments/equipments.php`
 *   مملوكٌ لإدارةِ الأسطولِ بحكمٍ نهائيّ.
 *
 * ◆ **والوراثةُ اشتقاقٌ لا اختراع**: المتغايرُ **هو الملفُّ نفسُه** يُعرَض بمنظرٍ
 *   آخر — فمالكُه مالكُه. **وشرطُه الإجماع**: أساسٌ له أكثرُ من مالكٍ محسومٍ لا
 *   يُورَّث منه شيء. ولا يُنسَب سطحٌ لا أساسَ له.
 *
 * ◆ **ولا يُمَسُّ السجلُّ الموحَّد**: `screen_registry.json` لقطةٌ تُولَّد بأداتِها.
 *   والحكمُ يُقيَّد في `gov_ownership_rulings` — **سجلُّ القراراتِ الدائم**،
 *   فمصدرُ الحقيقةِ واحدٌ لا اثنان.
 *
 * التشغيل:  php database/migrations/2027_10_22_frd_nav004_variant_ownership.php
 * الرجوع :  php database/migrations/2027_10_22_frd_nav004_variant_ownership.php --revert
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

/* ◆ **مفرداتٌ مغلقةٌ تبتر صامتةً**: `witness_kind` **تعدادٌ** بأربعِ قيم،
 *   فقيمتي `VARIANT_OF_RULED_BASE` بُترت إلى **فراغ** و`execute()` عاد صادقًا
 *   — فطُبع «قُيِّد 27» والعدُّ بنوعِ الشاهدِ صفر، **و27 صفًّا كُتبت بفراغ**.
 *   نُظِّفت، ووُسِّع التعدادُ بقيمةٍ مشروعةٍ في الهجرةِ نفسِها قبلَ الكتابة.
 * ◆ ولولا التحقُّقُ بعدَ الكتابةِ لمرَّت الكتابةُ المزعومةُ خضراء. */
$WITNESS = 'VARIANT_OF_BASE';

if (in_array('--revert', $argv, true)) {
    $conn->query("DELETE FROM `gov_ownership_rulings` WHERE `witness_kind` = '{$WITNESS}'");
    echo "↺ أُزيلت أحكامُ وراثةِ المتغايرات ({$conn->affected_rows})\n";
    exit(0);
}

/* ── ⓪ التعدادُ يُوسَّع بقيمةٍ مشروعةٍ **قبلَ** الكتابة ────────────────── */
$conn->query("ALTER TABLE `gov_ownership_rulings` MODIFY COLUMN `witness_kind`
  ENUM('DOMAIN_WRITE','DATA_READ','DOC_CYCLE','NONE','VARIANT_OF_BASE') NOT NULL");
$vocab = '';
$vq = $conn->query("SHOW COLUMNS FROM `gov_ownership_rulings` LIKE 'witness_kind'");
if ($vq) { $vx = $vq->fetch_assoc(); $vocab = (string) $vx['Type']; }
if (strpos($vocab, 'VARIANT_OF_BASE') === false) {
    exit("⛔ التعدادُ لم يتّسع — ولا تُكتب قيمةٌ تبترها القاعدةُ صامتةً. أُوقِف.\n");
}
echo "⓪ التعدادُ وُسِّع بقيمةٍ مشروعةٍ ومُثبَتٍ من القرص\n";

$REG = $ROOT . '/docs/baseline_20260821/extract/screen_registry.json';
if (!is_file($REG)) { exit("⛔ سجلُّ الشاشاتِ مفقود — يُشغَّل tools/baseline_reconcile.php\n"); }
$reg = json_decode((string) file_get_contents($REG), true);
if (!is_array($reg) || !$reg) { exit("⛔ سجلُّ الشاشاتِ لا يُقرأ\n"); }

/* ── ① خريطةُ الملكيةِ المحسومةِ بالملفِّ الأساس ─────────────────────────── */
$owned = array();
foreach ($reg as $r) {
    $b = isset($r['owner_basis']) ? $r['owner_basis'] : '';
    if ($b !== 'RULING' && $b !== 'CONSENSUS') { continue; }
    $base = preg_replace('~[?#].*$~', '', (string) (isset($r['route']) ? $r['route'] : ''));
    $o = trim((string) (isset($r['owner_dept']) ? $r['owner_dept'] : ''));
    if ($base === '' || $o === '') { continue; }
    $owned[strtolower($base)][$o] = true;
}
printf("① ملفاتٌ أساسٌ لها مالكٌ محسوم: %d\n", count($owned));

/* ── ② المتغايراتُ التي ترث — بشرطِ الإجماع ────────────────────────────── */
$open = 0; $inherit = array(); $still = 0; $noBase = 0;
foreach ($reg as $r) {
    $b = isset($r['owner_basis']) ? $r['owner_basis'] : '';
    if ($b !== 'NONE' && $b !== 'MAJORITY') { continue; }
    $open++;
    $route = (string) (isset($r['route']) ? $r['route'] : '');
    $base  = preg_replace('~[?#].*$~', '', $route);
    $k     = strtolower($base);
    if ($base !== $route && isset($owned[$k]) && count($owned[$k]) === 1) {
        $inherit[$route] = array('base' => $base, 'owner' => array_keys($owned[$k])[0], 'basis' => $b);
    } elseif ($base === '' || !is_file($ROOT . '/' . ltrim($base, '/'))) {
        $noBase++;
    } else {
        $still++;
    }
}
printf("② بلا حسمٍ=%d ⇒ **يرث=%d** · بلا أساسٍ على القرص=%d · سطحٌ حقيقيٌّ بلا مالك=%d\n",
       $open, count($inherit), $noBase, $still);
if (empty($inherit)) {
    echo "   ◆ **مقامٌ صفرٌ** — لا متغايرَ يرث. لا يُدَّعى إصلاحٌ ولا يُكتب شيء.\n";
    ems_migration_recorded(__FILE__, $conn, 0);
    exit(0);
}

/* ── ③ القيدُ في **سجلِّ القراراتِ الدائم** لا في اللقطة ────────────────── */
$ins = $conn->prepare(
  "INSERT INTO `gov_ownership_rulings`
     (`route`,`owner_before`,`owner_after`,`witness`,`witness_kind`,`ruling`,`reason`,`decided_at`)
   VALUES (?,?,?,?,?,?,?,NOW())
   /* ◆ **تحديثٌ ناقصٌ يُعلن نجاحًا كاذبًا**: أوّلُ صيغةٍ حدَّثت المالكَ والسببَ
      وحدَهما، والصفوفُ قائمةٌ سلفًا بمفتاحِ المسار — **فطُبع «قُيِّد 27» والعدُّ
      بنوعِ الشاهدِ صفر**. ولولا التحقُّقُ بعدَه لمرَّت. ⇒ يُحدَّث كلُّ ما يُقاس به. */
   ON DUPLICATE KEY UPDATE `owner_after` = VALUES(`owner_after`),
     `reason` = VALUES(`reason`), `witness` = VALUES(`witness`),
     `witness_kind` = VALUES(`witness_kind`), `ruling` = VALUES(`ruling`),
     `decided_at` = NOW()");
if (!$ins) { exit("⛔ تعذّر الإعداد: " . $conn->error . "\n"); }

$RULING = 'OWNER_ESTABLISHED';
$n = 0;
foreach ($inherit as $route => $d) {
    $before = '';
    $witness = 'أساسُه ' . $d['base'] . ' — مالكٌ محسومٌ بإجماعٍ في السجلِّ الموحَّد';
    $reason  = 'متغايرُ عرضٍ لا سطحٌ مستقلّ: المسارُ هو الملفُّ نفسُه بذيلِ منظرٍ '
             . '(`?view=` أو `#`). فمالكُه مالكُ أساسِه — **اشتقاقٌ من حكمٍ قائمٍ '
             . 'لا اختراعُ ملكية**. وشرطُه الإجماع: أساسٌ بأكثرَ من مالكٍ لا يُورَّث منه.';
    $ins->bind_param('sssssss', $route, $before, $d['owner'], $witness, $WITNESS, $RULING, $reason);
    if ($ins->execute()) { $n++; }
}
$ins->close();
printf("③ قُيِّد %d حكمَ وراثةٍ في `gov_ownership_rulings` — سجلُّ القراراتِ الدائم\n", $n);

/* ── ④ المصالحة ───────────────────────────────────────────────────────── */
function cnt(mysqli $c, $sql) { $r = @$c->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; }
$ruled = cnt($conn, "SELECT COUNT(*) FROM `gov_ownership_rulings` WHERE `witness_kind` = '{$WITNESS}'");
$thin  = cnt($conn, "SELECT COUNT(*) FROM `gov_ownership_rulings`
                      WHERE `witness_kind` = '{$WITNESS}'
                        AND (TRIM(`witness`) = '' OR TRIM(`reason`) = '' OR TRIM(`owner_after`) = '')");
printf("④ أحكامُ الوراثةِ في السجل: %d · بلا شاهدٍ أو سببٍ أو مالك: %d\n", $ruled, $thin);
if ($thin !== 0) { exit("⛔ حكمٌ بلا شاهد — أُوقِف\n"); }
if ($ruled !== $n) { exit("⛔ **كتابةٌ مزعومة**: أُعلن {$n} والمقروءُ {$ruled}. أُوقِف.\n"); }
printf("⑤ **الباقي بلا مالكٍ نهائيّ: %d من %d** — أسطحٌ حقيقيةٌ على القرصِ لا متغايرات،\n",
       $still + $noBase, count($reg));
echo "   وحسمُها **نسبةُ سطحٍ إلى إدارة** — قرارُ مالكِ الحوكمةِ لا قرارُ منفِّذ.\n";

ems_migration_recorded(__FILE__, $conn, 0);
