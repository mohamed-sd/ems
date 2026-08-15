<?php
/**
 * tools/fix_dual_store_measure.php — مخزنانِ لحقيقةٍ واحدة: قِسْ ولا تحسم
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0055 · INJ-0054 · INJ-0108 · INJ-0163 · INJ-0214 · INJ-0411 ·
 *   INJ-0356 · INJ-0370
 *
 * ثمانيةُ بنودٍ نوعُها واحد: **حقيقةٌ لها مخزنان**. وإلغاءُ مخزنٍ أو ترحيلُ
 * بيانةٍ **قرارُ مالكٍ لا قرارُ مُصلِح** — فهذه الأداةُ تقيس ولا تحسم:
 *   ① اسما المخزنين · ② عددُ الصفوفِ الحيّةِ في كلٍّ · ③ مَن يقرأ كلًّا منهما
 *   ④ أثرُ إلغاءِ كلِّ خيار.
 *
 * ◆ **والقراءُ يُعدُّون بالبحثِ في الشجرة** لا بالظنّ — وتُقصى
 *   `.claude/` و`storage/backups/` و`vendor/`.
 * ◆ **والاستثناءُ الوحيدُ الذي يُحسم بلا انتظار**: مخزنٌ **صفرُ صفوفٍ حيةٍ فيه
 *   وصفرُ قارئٍ** — يُعلَن ميتًا بالدليلِ وتُدرَج توصيةُ حذفٍ صريحة.
 *
 * التشغيل: php tools/fix_dual_store_measure.php [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');

/* البند · الشاشة · مخزنُ الشاشة · مخزنُ المجال · أثرُ الإلغاء */
$CASES = array(
    array('INJ-0055', 'Operations/attendance.php', 'scr_attendance', 'attendance_days',
        'إلغاءُ مخزنِ الشاشةِ يوصل الحضورَ بالمسيّر · وإلغاءُ جدولِ المجالِ يقطع مدخلاتِ الزمنِ عن المسيّر'),
    array('INJ-0054', 'Workforce/deductions.php', 'scr_deductions', 'payroll_deductions',
        'إلغاءُ مخزنِ الشاشةِ يوصل الخصمَ بالمقاصّة · وإلغاءُ جدولِ المجالِ يعطّل مقاصّاتِ المسيّرِ الآلية'),
    array('INJ-0108', 'Clients/commercial_risks.php', 'commercial_risks', 'risk_register',
        'تحويلُ الكتابةِ إلى السجلِّ المركزيِّ يوحّد الترقيمَ والحوكمة · وإبقاءُ الاثنين يُبقي سجلَّين للمخاطر'),
    array('INJ-0163', 'Equipments/fin_assets.php', 'scr_fin_assets', 'financing_operations',
        'إلغاءُ مخزنِ الشاشةِ يربط العينَ بعمليةِ تمويلِها وحصصِها · ولا محرّكَ يقرأ مخزنَ الشاشةِ اليوم'),
    array('INJ-0214', 'Employees/employee_contracts.php', 'drivercontracts', 'contracts',
        'إيقافُ الكتابةِ في القديمِ يوحّد سجلَّ العقود · وهجرةُ ما فيه تحتاج قرارَ مالكٍ لأنَّها بيانةٌ حيّة'),
    array('INJ-0411', 'Portal/ceo_risk.php', 'exec_decisions', 'risk_register',
        'ربطُ مؤشرِ «المخاطر المفتوحة» بالسجلِّ المركزيِّ يوحّد الرقمَ · وقراراتُ الرئيسِ تبقى سجلًّا مستقلًّا بحقّ'),
    array('INJ-0356', 'Procurement/consumption_rate.php', 'scr_consumption_rate', 'proc_stock_move',
        'حسابُ المعدلِ من الحركاتِ يُلغي الإدخالَ اليدويّ · والحركاتُ هي الحقيقةُ ولا بديلَ عنها'),
    array('INJ-0370', 'Operations/site_gate_equip.php', 'scr_site_gate_equip', 'equipments',
        'ربطُ الإذنِ بمعدةٍ من السجلِّ يُنهي النصوصَ الحرّةَ · وجدولُ المعداتِ لا يُلغى بحال'),
);

/* ── قراءُ اسمٍ في الشجرة ─────────────────────────────────────────────────── */
$files = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = str_replace('\\', '/', $f->getPathname());
    if (substr($p, -4) !== '.php') { continue; }
    foreach (array('/.claude/', '/storage/backups/', '/vendor/', '/node_modules/') as $sk) {
        if (strpos($p, $sk) !== false) { continue 2; }
    }
    $files[] = $p;
}
$readersOf = function ($needle) use ($files, $ROOT) {
    $hits = array('screens' => array(), 'services' => array(), 'tools' => array(), 'tests' => array());
    foreach ($files as $abs) {
        $src = (string) @file_get_contents($abs);
        if (strpos($src, $needle) === false) { continue; }
        $rel = ltrim(str_replace($ROOT, '', $abs), '/');
        if (strpos($rel, 'tools/') === 0)      { $hits['tools'][] = $rel; }
        elseif (strpos($rel, 'tests/') === 0)  { $hits['tests'][] = $rel; }
        elseif (strpos($rel, 'app/') === 0)    { $hits['services'][] = $rel; }
        else                                   { $hits['screens'][] = $rel; }
    }
    return $hits;
};
$rowsOf = function ($table) use ($conn) {
    $r = $conn->query("SELECT COUNT(*) FROM `{$table}`");
    if (!$r) { return array(-1, -1); }
    $all = (int) $r->fetch_row()[0];
    $live = $all;
    $c = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'is_deleted'");
    if ($c && $c->num_rows > 0) {
        $r2 = $conn->query("SELECT COUNT(*) FROM `{$table}` WHERE COALESCE(is_deleted,0) = 0");
        if ($r2) { $live = (int) $r2->fetch_row()[0]; }
    }
    return array($all, $live);
};

$MD = in_array('--md', $argv, true);
$out = array();
echo "══ مخزنانِ لحقيقةٍ واحدة — قياسٌ بلا حسم ══\n\n";
foreach ($CASES as $c) {
    list($inj, $screen, $tA, $tB, $impact) = $c;
    list($allA, $liveA) = $rowsOf($tA);
    list($allB, $liveB) = $rowsOf($tB);
    $rA = $readersOf($tA);
    $rB = $readersOf($tB);
    $prodA = count($rA['screens']) + count($rA['services']);
    $prodB = count($rB['screens']) + count($rB['services']);
    /* الاستثناءُ الوحيدُ: صفرُ صفٍّ حيٍّ **وصفرُ قارئٍ إنتاجيّ** */
    $dead = ($liveA === 0 && $prodA === 0);
    $out[] = compact('inj', 'screen', 'tA', 'tB', 'allA', 'liveA', 'allB', 'liveB',
                     'prodA', 'prodB', 'impact', 'dead') + array('rA' => $rA, 'rB' => $rB);

    echo '── ' . $inj . ' · ' . $screen . "\n";
    printf("   %-26s حيّة=%-7s كلّ=%-7s قرّاءٌ إنتاجيّون=%d\n", $tA, $liveA, $allA, $prodA);
    printf("   %-26s حيّة=%-7s كلّ=%-7s قرّاءٌ إنتاجيّون=%d\n", $tB, $liveB, $allB, $prodB);
    echo '   الأثر: ' . $impact . "\n";
    if ($dead) { echo "   ◆ **ميتٌ بالدليل**: صفرُ صفٍّ حيٍّ وصفرُ قارئٍ إنتاجيّ ⇒ توصيةُ حذفٍ صريحة\n"; }
    echo "\n";
}

if ($MD) {
    $md = "| البند | الشاشة | مخزنُ الشاشة | حيّةٌ فيه | قرّاؤه | مخزنُ المجال | حيّةٌ فيه | قرّاؤه | أثرُ الإلغاء |\n";
    $md .= "|---|---|---|---:|---:|---|---:|---:|---|\n";
    foreach ($out as $x) {
        $md .= '| **' . $x['inj'] . '** | `' . $x['screen'] . '` | `' . $x['tA'] . '` | '
             . ($x['liveA'] < 0 ? '—' : $x['liveA']) . ' | ' . $x['prodA'] . ' | `' . $x['tB'] . '` | '
             . ($x['liveB'] < 0 ? '—' : $x['liveB']) . ' | ' . $x['prodB'] . ' | ' . $x['impact'] . " |\n";
    }
    file_put_contents($ROOT . '/docs/fix_progress/dual_store_measure.md', $md);
    file_put_contents($ROOT . '/docs/fix_progress/dual_store_measure.json',
        json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "  · كُتب: docs/fix_progress/dual_store_measure.md و .json\n";
}
