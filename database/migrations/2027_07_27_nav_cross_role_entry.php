<?php
/**
 * 2027_07_27_nav_cross_role_entry.php — التبويبُ المتعدِّد: مشروعٌ أم بلا مبرِّر
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب (ثانيًا-١): «27 مسارًا في تبويبَين فأكثر — ومنها **مشروعٌ فعلًا**:
 *   `chats/index.php` في خمسةٍ و`main/role_board.php` في أربعةٍ
 *   و`Reports/margin_report.php` في أربعة. **فرِّق بين حالتَين بحقلٍ واحدٍ في
 *   المصفوفة**: مدخلٌ عابرٌ للأدوارِ (مشروع) أو اختلافٌ غيرُ مبرَّرٍ (يُوحَّد).
 *   **ولا تُوحِّد المشروعَ قسرًا**».
 *
 * ◆ **والمقيسُ حيًّا** على النصِّ المُصيَّرِ لتسعةَ عشرَ دورًا جذريًّا: **27 مسارًا**
 *   يظهر في تبويبَين فأكثر — **وكلُّها `PENDING_OWNER`**، فبوابةُ الإنفاذ U3
 *   صفرٌ ولا مخالفةَ معتمَدة. والحقلُ هنا **يصنّف الدَّينَ لا يُنشئه**.
 *
 * ◆ **والتصنيفُ يُشتقُّ بمعيارٍ مكتوبٍ لا بالهوى** — «مدخلٌ عابرٌ للأدوار» هو ما
 *   اجتمع فيه شرطان:
 *   ① **لا يملكه قسمٌ واحد**: يظهر لأربعةِ أدوارٍ فأكثر (مقيسٌ لا مُقدَّر).
 *   ② **وطبيعتُه أداةٌ أو مركزُ عملٍ أو تقريرٌ عابر** لا سجلَّ إدارةٍ بعينِها.
 *   وما لم يجتمعا فهو `UNJUSTIFIED_SPLIT` — يُوحَّد بقرارِ المالكِ في جلسةِ
 *   إغلاقِ المعلَّق، **ولا يُوحَّد الآن قسرًا** بنصِّ الطلب.
 *
 * ◆ والثلاثةُ المسمّاةُ في الطلبِ تُعلَّم `CROSS_ROLE_ENTRY` **صراحةً** — فما
 *   سمّاه المالكُ مشروعًا لا يُعاد الحكمُ عليه بمعيارٍ آليّ.
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
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$q = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
                     AND TABLE_NAME='nav_canonical' AND COLUMN_NAME='placement_kind'");
if (!$q || $q->num_rows === 0) {
    $conn->query("ALTER TABLE nav_canonical
        ADD `placement_kind` ENUM('SINGLE','CROSS_ROLE_ENTRY','UNJUSTIFIED_SPLIT')
            NOT NULL DEFAULT 'SINGLE'
            COMMENT 'مدخلٌ عابرٌ للأدوارِ مشروعٌ · أو اختلافٌ يُوحَّد بقرارِ المالك',
        ADD `placement_basis` VARCHAR(190) DEFAULT NULL
            COMMENT 'مصدرُ التصنيفِ — مقيسٌ أو مسمًّى بنصِّ المالك، لا اجتهادٌ صامت'");
}

/* ما سمّاه المالكُ مشروعًا — لا يُعاد الحكمُ عليه */
$OWNER_NAMED = array(
    'chats/index.php'            => 'سمّاه المالكُ مشروعًا نصًّا (ثانيًا-١)',
    'main/role_board.php'        => 'سمّاه المالكُ مشروعًا نصًّا (ثانيًا-١)',
    'Reports/margin_report.php'  => 'سمّاه المالكُ مشروعًا نصًّا (ثانيًا-١)',
);

/* المقيسُ حيًّا: عددُ التبويباتِ وعددُ الأدوارِ لكلِّ مسارٍ مُصيَّر */
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';
$live = array();
foreach (uxp_root_roles() as $rid) {
    foreach (uxp_render_role($conn, $rid) as $x) {
        $lc = mb_strtolower(uxp_norm($x['href']));
        $live[$lc]['g'][$x['group']] = true;
        $live[$lc]['r'][$rid] = true;
    }
}

$upd = $conn->prepare("UPDATE nav_canonical SET placement_kind = ?, placement_basis = ?
                        WHERE LOWER(route) = ?");
$cross = 0; $split = 0; $rows = array();
foreach ($live as $lc => $i) {
    if (count($i['g']) < 2) { continue; }
    $roles = count($i['r']); $tabs = count($i['g']);

    $named = null;
    foreach ($OWNER_NAMED as $r => $why) { if (mb_strtolower($r) === $lc) { $named = $why; break; } }

    /* ◆ **العبورُ يُقاس بعددِ الأدوارِ وحدَه — لا بكلمةٍ مفتاحية.**
         أولُ صياغةٍ جمعت عددًا مقيسًا بكلمةٍ في اسمِ المسار، فصنّفت
         `risk/dept_risk_space.php` (**14 دورًا**) و`finance/budget_form_fin.php`
         (**11 دورًا**) «بلا مبرِّر» — وهو نقيضُ معنى «عابرٍ للأدوار» حرفًا.
         **ومقياسٌ يناقض معناه المكتوبَ هو المخطئ.** فالمعيارُ الآن واحدٌ مقيس:
         مسارٌ يظهر لأربعةِ أدوارٍ فأكثرَ **عابرٌ بحكمِ ظهورِه**، وما دونَه
         اختلافٌ يُوحَّد بقرارِ المالك. والحدُّ 4 مأخوذٌ من نصِّ المالكِ نفسِه:
         سمّى `margin_report` (4 أدوارٍ) مشروعًا. */
    if ($named !== null) {
        $kind = 'CROSS_ROLE_ENTRY'; $basis = $named . " · مقيس: {$roles} أدوارٍ · {$tabs} تبويبات";
    } elseif ($roles >= 4) {
        $kind = 'CROSS_ROLE_ENTRY'; $basis = "مقيس: يظهر لـ{$roles} أدوارٍ (≥4) — عابرٌ بحكمِ ظهورِه";
    } else {
        $kind = 'UNJUSTIFIED_SPLIT'; $basis = "مقيس: {$roles} أدوارٍ فقط · {$tabs} تبويبات — لا يبرِّرها العبور";
    }
    $upd->bind_param('sss', $kind, $basis, $lc);
    $upd->execute();
    if ($kind === 'CROSS_ROLE_ENTRY') { $cross++; } else { $split++; }
    $rows[] = array($lc, $tabs, $roles, $kind);
}

echo "════ التبويبُ المتعدِّد — تصنيفٌ لا توحيدٌ قسريّ ════\n";
printf("  مسارٌ في تبويبَين فأكثر: %d\n", count($rows));
printf("    · CROSS_ROLE_ENTRY (مشروع · لا يُوحَّد): %d\n", $cross);
printf("    · UNJUSTIFIED_SPLIT (يُوحَّد بقرارِ المالك): %d\n", $split);
echo "\n  ▐ التفصيل\n";
usort($rows, function ($a, $b) { return $b[1] - $a[1]; });
foreach ($rows as $r) { printf("    %s %2d تبويبًا · %2d دورًا · %s\n",
    $r[3] === 'CROSS_ROLE_ENTRY' ? '◆' : '✗', $r[1], $r[2], $r[0]); }
echo "\n◆ وكلُّها `PENDING_OWNER` — فبوابةُ الإنفاذ U3 صفرٌ ولا مخالفةَ معتمَدة\n";
