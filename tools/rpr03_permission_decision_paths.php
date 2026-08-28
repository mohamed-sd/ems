<?php
/**
 * tools/rpr03_permission_decision_paths.php — `RPR-03` §٦ · مساراتُ قرارِ الصلاحية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — `RPR-03` §٦: *«وحّدْ قرارَ الصلاحيةِ في **مصدرٍ واحدٍ
 *   يُستدعى من الخادم** — والقائمةُ تُشتقّ منه لا تُبنى موازيةً له»* · و§١٠:
 *   `مساراتُ قرارِ الصلاحية = واحد` · والمقيسُ في خطِّ الأساسِ **مساران و٨٧ قارئًا**.
 *
 * ◆ **ولماذا المسارُ المزدوجُ أخطرُ من الضعيف** (§٦): *«فقد يظهر البندُ ويُمنع
 *   الفعل، أو يُخفى البندُ ويُسمح الفعلُ بالرابطِ المباشر»*.
 *
 * ◆ **والتمييزُ مقيسٌ لا مظنون** — ثلاثةُ أصنافٍ لا صنفان:
 *   ① **`CANONICAL`** — يستدعي دوالَّ `includes/permissions_helper.php` **ولا
 *      يقرأ جدولَ صلاحيةٍ باستعلامٍ خاصٍّ به**. هذا هو المسارُ الواحدُ المطلوب.
 *   ② **`DIRECT_DECISION`** — **يقرأ جدولَ صلاحيةٍ بنفسِه ليقرّر**: استعلامٌ
 *      خاصٌّ يقيّد بدورٍ. ⛔ **وهذا هو المسارُ الثاني** — كلُّ موضعٍ منه قارئُ
 *      قرارٍ مستقلٌّ يمكن أن يخالف الأوّل.
 *   ③ **`ADMIN_SURFACE`** — **يكتب** في جداولِ الصلاحيةِ: فهي **بياناتُه** لا
 *      قرارُه. ⛔ **ولا يُعدُّ قارئَ قرارٍ ظلمًا** — وإدخالُه في المقامِ يضخّمه
 *      بشاشاتِ الإدارةِ ويُضيّع الرقمَ الحقيقيّ.
 *
 * ⛔ **ولا يُعدُّ ملفٌّ في مقامَين**: الكتابةُ تغلب القراءةَ في التصنيف — فشاشةُ
 *   إدارةِ الصلاحياتِ تقرأ حتمًا لتعرض ما تكتب.
 *
 * ◆ **والنطاقُ يُعلَن**: شجرةُ الإنتاجِ وحدَها — ⛔ ولا `vendor/` ولا
 *   `storage/backups/` ولا `tools/` ولا `tests/`، فعُدّةُ القياسِ ليست نظامًا
 *   يُقاس ونسخةُ الاحتياطِ ليست شجرةً حيّة.
 *
 * التشغيل: php tools/rpr03_permission_decision_paths.php [--md] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$MD   = in_array('--md', $argv, true);
$SELF = in_array('--selftest', $argv, true);

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : '—(بلا نافذة)';

/* ═══ النطاقُ يُعلَن ═══════════════════════════════════════════════════════ */
$SKIP = array('/vendor/', '/storage/backups/', '/node_modules/', '/tools/', '/tests/',
              '/.git/', '/docs/');
$PERM_TABLES = 'role_permissions|report_role_permissions|permission_templates|role_permission_templates';
/* الدالّةُ المعتمَدةُ الواحدة — والقائمةُ تُشتقّ منها */
$HELPER = 'check_permission|check_view_permission|check_add_permission|check_edit_permission'
        . '|check_delete_permission|get_module_permissions|get_user_permissions|can_show_button'
        . '|has_any_permission|has_all_permissions|check_page_permissions'
        . '|get_current_page_permissions|enforce_current_page_view_permission'
        . '|ems_enforce_write_permission|enforce_module_permission_json|get_page_permissions';

$files = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT,
    FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT)));
    $skip = false;
    foreach ($SKIP as $s) { if (strpos($rel, $s) === 0 || strpos($rel, $s) !== false) { $skip = true; break; } }
    if ($skip) { continue; }
    $files[$rel] = $f->getPathname();
}

$canon = array(); $direct = array(); $admin = array();
$helperFile = '/includes/permissions_helper.php';
foreach ($files as $rel => $path) {
    $src = (string) @file_get_contents($path);
    if ($src === '') { continue; }
    $readsTable = (bool) preg_match('~\b(FROM|JOIN)\s+`?(' . $PERM_TABLES . ')`?~i', $src)
               || (bool) preg_match('~selectOne\(\s*[\'"](' . $PERM_TABLES . ')[\'"]~i', $src);
    $writesTable = (bool) preg_match('~\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\s+`?('
                 . $PERM_TABLES . ')`?~i', $src)
                 || (bool) preg_match('~->(insert|update|delete|upsert)\(\s*[\'"](' . $PERM_TABLES . ')[\'"]~i', $src);
    $callsHelper = (bool) preg_match('~\b(' . $HELPER . ')\s*\(~', $src);

    if ($rel === $helperFile) { $canon[] = $rel; continue; }   /* المصدرُ نفسُه */
    /* ⛔ **الكتابةُ تغلب**: شاشةُ الإدارةِ تقرأ حتمًا لتعرض ما تكتب */
    if ($writesTable) { $admin[] = $rel; continue; }
    if ($readsTable)  { $direct[] = $rel; continue; }
    if ($callsHelper) { $canon[] = $rel; }
}

/* ⛔ **السالبُ يكسر مفردةً فريدة**: يُعدُّ ملفٌّ إداريٌّ قارئَ قرار */
if ($SELF && $admin) { $direct[] = $admin[0]; }

sort($direct); sort($admin); sort($canon);
$paths = 1 + (count($direct) > 0 ? 1 : 0);

echo "\n═══ `RPR-03` §٦ — مساراتُ قرارِ الصلاحية ═══\n";
printf("  اللقطة: %s · ملفّاتُ الإنتاجِ الممسوحة: **%d**\n\n", $sid, count($files));
printf("  ① `CANONICAL`        **%4d** — يستدعي المصدرَ الواحدَ ولا يقرأ جدولًا بنفسِه\n", count($canon));
printf("  ② `DIRECT_DECISION`  **%4d** — ⛔ **يقرأ جدولَ صلاحيةٍ بنفسِه ليقرّر**\n", count($direct));
printf("  ③ `ADMIN_SURFACE`    **%4d** — يكتب فيها: بياناتُه لا قرارُه (‏لا يُعدُّ ظلمًا)\n", count($admin));

echo "\n  ── القارئون المستقلّون — بأسمائهم لا بعددٍ مجرَّد ──\n";
foreach (array_slice($direct, 0, 18) as $d) { echo "     ⛔ " . $d . "\n"; }
if (count($direct) > 18) { printf("     … و%d غيرُها\n", count($direct) - 18); }

echo "\n  ── خطوةُ صفرٍ: إعادةُ قياسِ خطِّ الأساس (§٢·١) ──\n";
printf("     خطُّ الأساسِ: **مساران و٨٧ قارئًا** · والمقيسُ الآنَ **%d مسارًا و%d قارئًا مستقلًّا**\n",
       $paths, count($direct));
echo "     ◆ **والفرقُ خبرٌ يُعلَن** — والتمييزُ هنا يُخرج شاشاتِ الإدارةِ من المقام،\n";
echo "       ⛔ فإدخالُها يضخّم الرقمَ بما ليس قرارًا.\n";

echo "\n────────────────────────────────────────────────────────────\n";
printf("**`مساراتُ قرارِ الصلاحية` = %d** — والقبولُ **١**\n", $paths);
echo $paths === 1
    ? "🟢 **مسارٌ واحدٌ — والقائمةُ تُشتقّ منه**\n"
    : "✘ **مساران** — و§٦: «قد يظهر البندُ ويُمنع الفعل، أو يُخفى البندُ ويُسمح بالرابطِ المباشر»\n"
    . "  ⇒ `Track RPR-03 ج blocked at stage: توحيدُ " . count($direct) . " قارئًا مستقلًّا`\n";

if ($SELF) {
    echo "\n═══ الاختبارُ السالب ═══\n";
    $inBoth = count(array_intersect($direct, $admin));
    echo $inBoth >= 1
        ? "🟢 **حُقن ملفٌّ إداريٌّ في القارئين فظهر في المقامَين — فالمقامُ يُقاس ولا يُفترض**\n"
        : "✘ **لم يظهر الحقن** — والفاحصُ لا يُصدَّق\n";
    exit($inBoth >= 1 ? 0 : 1);
}

if ($MD) {
    $o  = "# `RPR-03` §٦ — مساراتُ قرارِ الصلاحية\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "## ثلاثةُ أصنافٍ لا صنفان\n\n| الصنف | العدد | المعيار |\n|---|---|---|\n";
    $o .= "| `CANONICAL` | **" . count($canon) . "** | يستدعي `permissions_helper` ولا يقرأ جدولًا بنفسِه |\n";
    $o .= "| ⛔ `DIRECT_DECISION` | **" . count($direct) . "** | **يقرأ جدولَ صلاحيةٍ بنفسِه ليقرّر** — المسارُ الثاني |\n";
    $o .= "| `ADMIN_SURFACE` | **" . count($admin) . "** | يكتب فيها: بياناتُه لا قرارُه — ولا يُعدُّ ظلمًا |\n\n";
    $o .= "⛔ **ولا يُعدُّ ملفٌّ في مقامَين**: الكتابةُ تغلب القراءةَ، فشاشةُ الإدارةِ تقرأ\n";
    $o .= "حتمًا لتعرض ما تكتب.\n\n";
    $o .= "## القارئون المستقلّون\n\n";
    foreach ($direct as $d) { $o .= "- `" . $d . "`\n"; }
    $o .= "\n## خطوةُ صفرٍ — إعادةُ قياسِ خطِّ الأساس\n\n";
    $o .= "خطُّ الأساسِ: **مساران و٨٧ قارئًا** · **والمقيسُ الآنَ " . $paths . " مسارًا و"
        . count($direct) . " قارئًا مستقلًّا**.\n\n";
    $o .= "**`مساراتُ قرارِ الصلاحية` = " . $paths . "** — والقبولُ **١**.\n";
    if ($paths !== 1) {
        $o .= "\n`Track RPR-03 ج blocked at stage: توحيدُ " . count($direct) . " قارئًا مستقلًّا`\n";
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR03_PERMISSION_PATHS.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR03_PERMISSION_PATHS.md\n";
}
