<?php
/**
 * 2027_11_07_injfrd66_sal05_cycle_register.php
 *   SAL-05 — تسجيلُ «أنشطة العملاء» في دفترِ دورةِ الإدارة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **نصفُ المعيارِ كان أخضرَ ونصفُه لم يُقَس**: «صفر بندِ تنقّلٍ مستقل» تحقَّق
 *   (صفرُ بندٍ حيٍّ لدورِ المبيعات)، و«**مسجَّلٌ في الدورة بمرحلته**» رسَب —
 *   `Clients/activities.php` **غائبٌ عن `gov_screen_cycle` كلِّه** (صفرُ صف).
 *
 * ◆ **والشاشةُ التي لا بندَ لها ولا صفَّ دورةٍ لا موضعَ لها البتّة**: لا تُرى
 *   في القائمةِ (وهذا مقصود — «سجلٌّ تابعٌ للعميل والفرصة») ولا تُعرَف في
 *   دفترِ الدورةِ الذي يُقرأ منه موضعُها. **فالإخفاءُ صار ضياعًا.**
 *
 * ◆ **والمرحلةُ تُقرأ من نصِّ المتطلبِ لا تُخترَع**: «تابعٌ **للعميل والفرصة**»
 *   — والمرحلةُ الأولى في دورةِ المبيعاتِ اسمُها حرفيًّا «**العملاء والفرص**»
 *   ومجموعتُها «فتح العميل»، وفيها `clients.php` و`projects.php`. فيُوضَع
 *   معهما — لا في مرحلةٍ تُستحسَن.
 *
 * ◆ **وصفٌّ واحدٌ لا أكثر**: `unbilled.php` مسجَّلٌ **مرَّتين** (المبيعاتُ
 *   والتشغيل) لأنَّه يخدم دورتَين — وأنشطةُ العملاءِ تخصُّ المبيعاتِ وحدَها.
 *   والتكرارُ بلا سببٍ يُضاعف المقامَ في كلِّ عدٍّ يقرأ الدفتر.
 *
 * التشغيل:  php database/migrations/2027_11_07_injfrd66_sal05_cycle_register.php
 * الرجوع :  php database/migrations/2027_11_07_injfrd66_sal05_cycle_register.php --revert
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

$FILE   = 'Clients/activities.php';
$REVERT = in_array('--revert', $argv, true);

if ($REVERT) {
    echo "\n══ الرجوع — SAL-05 ══\n\n";
    $st = $conn->prepare("DELETE FROM gov_screen_cycle WHERE screen_file = ?");
    $st->bind_param('s', $FILE);
    $st->execute();
    printf("  ✔ حُذف %d صفًّا\n\n", $st->affected_rows);
    exit(0);
}

echo "\n══ INJ-FRD-01 · SAL-05 — تسجيلُ الأنشطةِ في دورةِ الإدارة ══\n\n";

/* ── ① لا يُسجَّل ما هو مسجَّل — والعطالةُ تُقاس بالملفِّ لا بالعنوان ─────── */
$r = $conn->query("SELECT COUNT(*) FROM gov_screen_cycle WHERE screen_file REGEXP 'activities\\\\.php'");
$already = $r ? (int) $r->fetch_row()[0] : -1;
if ($already !== 0) {
    printf("  ○ مسجَّلٌ سلفًا (%d صفًّا) — لا شيءَ يُفعَل\n\n", $already);
    exit(0);
}

/* ── ② الموضعُ يُقرأ من صفوفِ المرحلةِ القائمةِ لا يُكتَب حرفًا ────────────
   ◆ **ولماذا يُقرأ لا يُكتَب**: أسماءُ المراحلِ والمجموعاتِ نصوصٌ عربيةٌ
     طويلة، وحرفٌ واحدٌ يختلف يُنشئ **مرحلةً جديدةً بدل الانضمامِ إلى قائمة**.
     فتُؤخَذ من صفٍّ شقيقٍ حرفيًّا. */
$r = $conn->query("SELECT dept_name, layer_name, stage_order, stage_name, group_name
                     FROM gov_screen_cycle
                    WHERE screen_file = 'clients.php' LIMIT 1");
if (!$r || !($anchor = $r->fetch_assoc())) {
    exit("  ✘ لم أجد صفَّ `clients.php` مِرساةً للموضع — لا أخترع مرحلةً\n\n");
}
printf("  ○ المِرساة: %s · %s · مرحلة %s «%s» · مجموعة «%s»\n",
    $anchor['dept_name'], $anchor['layer_name'], $anchor['stage_order'],
    $anchor['stage_name'], $anchor['group_name']);

/* ── ③ العنوانُ بصيغةِ الدفترِ نفسِها: «العنوان (الملف)» ─────────────────── */
$title = 'أنشطة العملاء والمتابعات (activities.php)';

$st = $conn->prepare("INSERT INTO gov_screen_cycle
        (company_id, dept_name, layer_name, stage_order, stage_name, group_name, screen_title, screen_file)
        VALUES (0, ?, ?, ?, ?, ?, ?, ?)");
$st->bind_param('ssissss',
    $anchor['dept_name'], $anchor['layer_name'], $anchor['stage_order'],
    $anchor['stage_name'], $anchor['group_name'], $title, $FILE);
if (!$st->execute()) { exit("  ✘ تعذّر الإدراج: {$conn->error}\n\n"); }
echo "  ✔ سُجِّل «{$title}» عند {$FILE}\n";

/* ── ④ حُرّاسُ ما بعدَ الكتابة ───────────────────────────────────────────── */
echo "\n  ── حُرّاسُ ما بعدَ الكتابة\n";
$halt = 0;

$r = $conn->query("SELECT COUNT(*) FROM gov_screen_cycle WHERE screen_file REGEXP 'activities\\\\.php'");
$n = $r ? (int) $r->fetch_row()[0] : -1;
if ($n !== 1) { $halt++; printf("     ✘ %d صفًّا لا صفٌّ واحد — تكرارٌ يُضاعف المقام\n", $n); }
else { echo "     ✔ صفٌّ واحدٌ لا أكثر\n"; }

/* والنصُّ لم يُبتَر: عمودٌ ضيّقٌ يبتلع الفرقَ صامتًا ويُبلغ نجاحًا */
$st2 = $conn->prepare("SELECT screen_title, screen_file, stage_name, group_name
                         FROM gov_screen_cycle WHERE screen_file = ?");
$st2->bind_param('s', $FILE); $st2->execute();
$back = $st2->get_result()->fetch_assoc();
$intact = $back
    && $back['screen_title'] === $title
    && $back['screen_file']  === $FILE
    && $back['stage_name']   === $anchor['stage_name']
    && $back['group_name']   === $anchor['group_name'];
if (!$intact) {
    $halt++;
    echo "     ✘ ما قُرئ يخالف ما كُتب — بترٌ في عمودٍ ضيّق:\n";
    foreach (array('screen_title' => $title, 'screen_file' => $FILE,
                   'stage_name' => $anchor['stage_name'], 'group_name' => $anchor['group_name']) as $k => $v) {
        if (!$back || $back[$k] !== $v) {
            printf("        · %s: كُتب «%s» وقُرئ «%s»\n", $k, $v, $back ? $back[$k] : '—');
        }
    }
} else { echo "     ✔ ما قُرئ = ما كُتب حرفًا — لا بترَ في عمودٍ ضيّق\n"; }

/* ولم يُمَسَّ سواه */
$r = $conn->query("SELECT COUNT(*) FROM gov_screen_cycle");
printf("     ○ مجموعُ الدفتر: %s صفًّا\n", $r ? $r->fetch_row()[0] : '؟');

if ($halt > 0) {
    $st3 = $conn->prepare("DELETE FROM gov_screen_cycle WHERE screen_file = ?");
    $st3->bind_param('s', $FILE); $st3->execute();
    echo "\n  ⛔ توقَّفت الهجرةُ وأُزيل ما كُتب — صفٌّ لا يجتاز حُرّاسَه يُقرأ ويُصدَّق.\n\n";
    exit(1);
}

echo "\n  ◆ والبندُ يبقى خاملًا في التنقّل: «ولا يظهر بندًا مستقلًّا» نصُّ المتطلب.\n";
echo "    التسجيلُ في الدفترِ **موضعٌ لا ظهور**.\n\n";
exit(0);
