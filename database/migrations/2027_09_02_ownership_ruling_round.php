<?php
/**
 * 2027_09_02_ownership_ruling_round.php
 *   جولةُ حسمِ المِلكية — INJ-FIX-01 · GAP-20 موسَّعةً بـ INJ-FIX-02 · NF-14
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المقياسُ قبلَ الجولة:** ٦١٣ سطحًا · حكمٌ صريحٌ ٤٦ · إجماعٌ ٢٢٨ ·
 *   **ترجيحٌ مؤقتٌ ١٢٦** · **بلا أساسٍ ٢١٣**. والمعيار: «رفعُ كلِّ ترجيحٍ مؤقتٍ
 *   إلى حكمٍ صريحٍ **أو مِلكيةٍ مشتركةٍ معلَنة**».
 *
 * ◆ **ولا يُخترع حكم.** الترجيحُ المؤقتُ اختيارُ الأكثرِ ورودًا — وهو **عدٌّ لا
 *   حجة**. فالحسمُ هنا من **سجلٍّ قائمٍ مُحكَمٍ سلفًا**: `gov_space_appearances`
 *   وتصنيفُها `OWNED` («المساحةُ هي المالكُ بعدَ حسمِ المِلكية — أصليةٌ لدورتِها»).
 *
 * ◆ **أربعةُ أحكامٍ لا خامسَ لها، ولكلٍّ شاهدُه:**
 *   ① `OWNER_CONFIRMED` — للمسارِ صفُّ `OWNED` واحدٌ يوافق الترجيح.
 *   ② `OWNER_CHANGED`   — صفُّ `OWNED` واحدٌ **يخالف** الترجيح ⇒ يُصحَّح.
 *   ③ `SHARED_PLATFORM` — لا مساحةَ تدّعيه أصليًّا: كلُّ ظهوراتِه رقابيةٌ أو
 *      سياقيةٌ أو ممنوعة ⇒ **مِلكيةٌ مشتركةٌ معلَنة** (وهو نصُّ المعيار).
 *   ④ `NOT_APPLICABLE`  — معالجٌ أو كرون: **لا مِلكيةَ إداريةً لسطحٍ لا يُصيَّر**،
 *      ومِلكيتُه مِلكيةُ مُنادِيه. وعدُّها «فجوةَ مِلكية» يُضخِّم المقامَ بلا معنًى.
 *
 * ◆ وما لا يُحسم بواحدٍ من الأربعةِ **يبقى مُعلَنًا ناقصًا** — ولا يُغلق بادّعاء.
 *
 * التشغيل:  php database/migrations/2027_09_02_ownership_ruling_round.php
 * الرجوع :  php database/migrations/2027_09_02_ownership_ruling_round.php --revert
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

$MARK = 'INJFIX02-NF14';   /* وسمُ الجولةِ — به يُكنَس الرجوعُ ولا يمسُّ الـ٤٦ السابقة */

/* ══ الرجوع ═══════════════════════════════════════════════════════════════ */
if (in_array('--revert', $argv, true)) {
    $conn->query("DELETE FROM `gov_ownership_rulings` WHERE `reason` LIKE '{$MARK}%'");
    echo "↺ حُذف {$conn->affected_rows} حكمًا من جولةِ {$MARK}\n";
    $conn->query("ALTER TABLE `gov_ownership_rulings`
                  MODIFY `ruling` ENUM('OWNER_CONFIRMED','OWNER_CHANGED','APPEARANCE_MISSING') NOT NULL");
    echo "↺ أُعيد ENUM الحكمِ إلى قيمِه الثلاث\n";
    exit(0);
}

/* ══ ① توسيعُ ENUM الحكمِ بقيمتَين ═══════════════════════════════════════ */
if (!$conn->query("ALTER TABLE `gov_ownership_rulings`
        MODIFY `ruling` ENUM('OWNER_CONFIRMED','OWNER_CHANGED','APPEARANCE_MISSING',
                             'SHARED_PLATFORM','NOT_APPLICABLE','OWNER_ESTABLISHED') NOT NULL")) {
    exit("✘ تعذّر توسيعُ ENUM: {$conn->error}\n");
}
echo "① وُسِّع ENUM الحكم: +SHARED_PLATFORM +NOT_APPLICABLE +OWNER_ESTABLISHED\n";

/* ══ ② القراءةُ من السجلاتِ القائمة ═══════════════════════════════════════ */
$EX = $ROOT . '/docs/baseline_20260821/extract/';
$reg = json_decode((string) file_get_contents($EX . 'screen_registry.json'), true);
$cf  = json_decode((string) file_get_contents($EX . 'reconcile_conflicts.json'), true);
$majorityTop = array();
foreach ($cf as $x) {
    if (($x['kind'] ?? '') !== 'owner') { continue; }
    $v = $x['values']; arsort($v);
    $majorityTop[$x['route']] = array_key_first($v);
}

function bn($r) { return mb_strtolower(basename(preg_replace('/[?\#].*$/', '', (string) $r))); }

$ap = array();
$q = $conn->query("SELECT `route`,`cls`,`owner_dept_ar`,`space_ar` FROM `gov_space_appearances`");
while ($q && $x = $q->fetch_assoc()) { $b = bn($x['route']); if ($b !== '') { $ap[$b][] = $x; } }

$already = array();
$q = $conn->query("SELECT `route` FROM `gov_ownership_rulings`");
while ($q && $x = $q->fetch_row()) { $already[bn($x[0])] = 1; }

/* ══ ③ الحكمُ سطحًا سطحًا ═════════════════════════════════════════════════ */
$st = $conn->prepare("INSERT INTO `gov_ownership_rulings`
        (`route`,`owner_before`,`owner_after`,`witness`,`witness_kind`,`ruling`,`reason`,`decided_at`)
        VALUES (?,?,?,?,?,?,?,NOW())");

$n = array('OWNER_CONFIRMED' => 0, 'OWNER_CHANGED' => 0, 'OWNER_ESTABLISHED' => 0,
           'SHARED_PLATFORM' => 0, 'NOT_APPLICABLE' => 0,
           'SKIPPED_DUPLICATE_SURFACE' => 0, 'SKIPPED_HAS_RULING' => 0, 'UNRESOLVED' => 0);
$changed = array(); $unresolved = array(); $done = array();

foreach ($reg as $r) {
    $basis = (string) ($r['owner_basis'] ?? '');
    if ($basis === 'RULING' || $basis === 'CONSENSUS') { continue; }   /* محسومٌ سلفًا */
    $route = (string) ($r['route'] ?? '');
    if ($route === '') { continue; }
    $b = bn($route);
    if (isset($already[$b])) { $n['SKIPPED_HAS_RULING']++; continue; }
    /* ◆ صفوفُ السجلِّ قد تتعدَّد لسطحٍ واحدٍ (متغيّراتُ `?view=`) — والحكمُ للسطحِ لا للصف */
    if (isset($done[$b])) { $n['SKIPPED_DUPLICATE_SURFACE']++; continue; }
    $done[$b] = 1;

    $before = (string) ($r['owner_dept'] ?? 'UNKNOWN');
    $type   = (string) ($r['surface_type'] ?? '');

    /* ④ معالجٌ أو كرون — لا مِلكيةَ إداريةً لسطحٍ لا يُصيَّر */
    if ($type === 'HANDLER' || $type === 'CRON') {
        $after = 'غيرُ منطبق — مِلكيتُه مِلكيةُ مُنادِيه';
        $wit   = 'Surface_Type=' . $type;
        $kind  = 'NONE';
        $rul   = 'NOT_APPLICABLE';
        $why   = $MARK . ' · سطحٌ لا يُصيَّر في مساحة — والمِلكيةُ الإداريةُ حكمُ مساحةٍ لا حكمُ ملفّ';
        $st->bind_param('sssssss', $route, $before, $after, $wit, $kind, $rul, $why);
        if ($st->execute()) { $n['NOT_APPLICABLE']++; }
        continue;
    }

    $rows = $ap[$b] ?? array();
    $ownedBy = array();
    foreach ($rows as $a) {
        if ($a['cls'] === 'OWNED') { $ownedBy[($a['owner_dept_ar'] ?: $a['space_ar'])] = 1; }
    }

    /* ①② مالكٌ أصليٌّ واحدٌ في سجلِّ الظهورات */
    if (count($ownedBy) === 1) {
        $after = array_key_first($ownedBy);
        $top   = $majorityTop[$route] ?? null;
        /* ◆ لا مالكَ سابقًا ⇒ **إثباتٌ** لا تغيير. وتسميتُه تغييرًا تدّعي نقضَ حكمٍ لم يكن. */
        if ($top === null || $top === '' || $top === 'UNKNOWN') { $rul = 'OWNER_ESTABLISHED'; }
        else { $rul = ($after === $top) ? 'OWNER_CONFIRMED' : 'OWNER_CHANGED'; }
        $wit   = 'gov_space_appearances.cls=OWNED';
        $kind  = 'DOC_CYCLE';
        $why   = $MARK . ' · المساحةُ تدّعيه أصليًّا لدورتِها — والترجيحُ عدٌّ لا حجة'
               . ($rul === 'OWNER_CHANGED' ? " · صُحِّح من «{$top}»" : '')
               . ($rul === 'OWNER_ESTABLISHED' ? ' · ولا مالكَ سابقًا فهو إثباتٌ لا نقض' : '');
        $st->bind_param('sssssss', $route, $before, $after, $wit, $kind, $rul, $why);
        if ($st->execute()) {
            $n[$rul]++;
            if ($rul === 'OWNER_CHANGED') { $changed[] = "{$b}: «{$top}» ⇐ «{$after}»"; }
        }
        continue;
    }
    /* مالكان أصليّان فأكثر ⇒ مشتركةٌ معلَنة */
    if (count($ownedBy) > 1) {
        $after = 'مشتركةٌ معلَنة: ' . implode(' · ', array_keys($ownedBy));
        $wit   = 'gov_space_appearances — OWNED في ' . count($ownedBy) . ' مساحة';
        $kind  = 'DOC_CYCLE'; $rul = 'SHARED_PLATFORM';
        $why   = $MARK . ' · أكثرُ من مساحةٍ تدّعيه أصليًّا — والمعيارُ يُجيز المِلكيةَ المشترَكةَ المعلَنة';
        $st->bind_param('sssssss', $route, $before, $after, $wit, $kind, $rul, $why);
        if ($st->execute()) { $n['SHARED_PLATFORM']++; }
        continue;
    }
    /* ③ ظهوراتٌ كلُّها رقابيةٌ أو سياقيةٌ أو ممنوعة ⇒ لا مساحةَ تدّعيه أصليًّا */
    if (!empty($rows)) {
        $cls = array();
        foreach ($rows as $a) { $cls[$a['cls']] = 1; }
        $after = 'مشتركةٌ معلَنة — منصةٌ بلا مساحةٍ أصلية';
        $wit   = 'ظهوراتُه كلُّها: ' . implode('+', array_keys($cls));
        $kind  = 'DATA_READ'; $rul = 'SHARED_PLATFORM';
        $why   = $MARK . ' · صفرُ ظهورٍ أصليٍّ (OWNED) في أيِّ مساحة — فلا مالكَ أصليًّا يُدَّعى';
        $st->bind_param('sssssss', $route, $before, $after, $wit, $kind, $rul, $why);
        if ($st->execute()) { $n['SHARED_PLATFORM']++; }
        continue;
    }
    /* لا ظهورَ أصلًا — لا يُحسم بادّعاء */
    $n['UNRESOLVED']++;
    $unresolved[] = $b . ' (' . $type . ')';
}
$st->close();

/* ══ ④ الحصيلة ════════════════════════════════════════════════════════════ */
echo "───────────────────────────────────────────────────────────────\n";
foreach ($n as $k => $v) { printf("  %-22s %d\n", $k, $v); }
echo "───────────────────────────────────────────────────────────────\n";
if ($changed) {
    echo "◆ مِلكيةٌ **صُحِّحت** (الترجيحُ كان خطأً):\n";
    foreach ($changed as $ch) { echo "   {$ch}\n"; }
}
$total = (int) $conn->query("SELECT COUNT(*) FROM `gov_ownership_rulings`")->fetch_row()[0];
echo "② أحكامُ المِلكيةِ الآن: {$total} (كانت 46)\n";
printf("③ **يبقى بلا حسمٍ: %d** — ولا ظهورَ له في أيِّ مساحةٍ فلا يُحسم بادّعاء\n", $n['UNRESOLVED']);
foreach (array_slice($unresolved, 0, 12) as $x) { echo "   · {$x}\n"; }
if (count($unresolved) > 12) { echo "   · … و" . (count($unresolved) - 12) . " غيرُها\n"; }
