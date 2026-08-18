<?php
/**
 * tools/uxw_gates.php — بواباتُ المنعِ الاثنتا عشرةَ (UXW-01 §9-4)
 * ───────────────────────────────────────────────────────────────────────────
 * تُرسِّب البناءَ ولا تنصح: يخرج 1 عند أيِّ مخالفةٍ في النطاقِ المرحَّل.
 *
 * النطاقُ المرحَّل: الشاشاتُ المذكورةُ في tools/uxw_scope.txt (سطرٌ لكلِّ ملف،
 * والتعليقُ بـ#). الشاشةُ تدخل النطاقَ لحظةَ ترحيلِها — ولا تخرج منه أبدًا،
 * فذلك ما يمنع الارتداد. والبواباتُ 7 و11 تُقاس على القاعدةِ كلِّها لا على
 * النطاقِ وحدَه (فهما بوابتا بنيةِ تنقّل).
 *
 * كلُّ بوابةٍ رقمُها كما في UXW-01 §9-4 — والأمرُ يُعاد تشغيلُه:
 *   C:\wamp64\bin\php\php8.2.30\php.exe tools\uxw_gates.php            # الحكم
 *   C:\wamp64\bin\php\php8.2.30\php.exe tools\uxw_gates.php --baseline # أرقامُ النظامِ كلِّه
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$BASELINE_MODE = in_array('--baseline', $argv, true);

/* ── النطاق ── (--scope=path لفحصِ قائمةٍ بديلةٍ أثناءَ الترحيل) ─────────── */
$scopeFile = __DIR__ . '/uxw_scope.txt';
foreach ($argv as $a) {
    if (strpos($a, '--scope=') === 0) $scopeFile = substr($a, 8);
}
$SCOPE = array();
if (is_file($scopeFile)) {
    foreach (file($scopeFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        $ln = trim($ln);
        if ($ln === '' || $ln[0] === '#') continue;
        $SCOPE[] = str_replace('\\', '/', $ln);
    }
}

/* ── الفجواتُ المعلَنةُ بقرارِ مالكٍ معلَّق ─────────────────────────────────
   tools/uxw_declared_gaps.txt: بوابة<TAB>مفتاح<TAB>سببٌ ومرجعٌ — تُعلَن في كلِّ
   تشغيلٍ ولا تُرسِّب، فالفارغُ بحقٍّ يُوثَّق فارغًا حتى يُسنَد لمصدرٍ مقيس. */
$DECLARED = array();
$dgFile = __DIR__ . '/uxw_declared_gaps.txt';
if (is_file($dgFile)) {
    foreach (file($dgFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        if (trim($ln) === '' || $ln[0] === '#') continue;
        $c = explode("\t", $ln);
        if (count($c) >= 3) $DECLARED[$c[0] . '|' . $c[1]] = $c[2];
    }
}

/* ── العُدَّة ──────────────────────────────────────────────────────────── */
$violations = array();   // [gate, file, detail]
$declaredHits = array(); // ما طابق فجوةً معلَنة
$report     = array();   // gate => summary line
function v(&$violations, $gate, $file, $detail) {
    global $DECLARED, $declaredHits;
    foreach ($DECLARED as $key => $reason) {
        list($g, $k) = explode('|', $key, 2);
        if ($g === $gate && mb_strpos($file . ' ' . $detail, $k) !== false) {
            $declaredHits[] = array($gate, $file, $detail, $reason);
            return;
        }
    }
    $violations[] = array($gate, $file, $detail);
}

function read_src($ROOT, $rel) {
    $p = $ROOT . '/' . $rel;
    return is_file($p) ? file_get_contents($p) : null;
}

/* الأصنافُ المعتمدةُ للأزرارِ تُقاس من ملفَّي المكوّنِ لا تُخترع */
$approvedBtn = array('btn'); // الأساسُ البنيوي
foreach (array('assets/css/ems-buttons.css', 'assets/css/design-tokens.css', 'assets/css/ems-shell.css') as $f) {
    $css = read_src($ROOT, $f);
    if ($css === null) continue;
    if (preg_match_all('/\.((?:btn|ems-btn)[A-Za-z0-9_-]*)\s*[,{:.\s]/u', $css, $m)) {
        foreach ($m[1] as $cls) $approvedBtn[] = $cls;
    }
}
$approvedBtn = array_unique(array_merge($approvedBtn, array(
    // بوتستراب الأساس — قياسٌ لا اختراع: هذه أصنافُ الإطارِ المحمَّلِ في القشرة
    'btn-primary','btn-secondary','btn-success','btn-danger','btn-warning','btn-info',
    'btn-light','btn-dark','btn-link','btn-sm','btn-lg','btn-outline-primary',
    'btn-outline-secondary','btn-outline-success','btn-outline-danger','btn-outline-warning',
    'btn-outline-info','btn-outline-light','btn-outline-dark','btn-close','btn-group','btn-ghost',
)));

/* المصطلحاتُ الممنوعةُ — ورقةُ «الممنوع والمفرَّق» من الدفترِ الحاكم */
$FORBIDDEN_TERMS = array(
    'خارج الوثيقة', 'بانتظار المالك', 'إضافات للمالك',
    'Activation Pattern', 'Visibility Guard',
    'الرسم لا يعرض محاور افتراضية', 'نراجع السجلات', 'نبدأ من هنا',
);

/* ═══ البواباتُ الملفّيةُ 1..6 · 8 · 9 · 12 — على النطاقِ المرحَّل ═══════ */
foreach ($SCOPE as $rel) {
    $src = read_src($ROOT, $rel);
    if ($src === null) { v($violations, 'نطاق', $rel, 'ملفٌ في النطاقِ غيرُ موجود'); continue; }

    /* ① لونٌ مثبَّتٌ خارجَ الرموز — hex أو rgb() في وسومِ/كتلِ الأنماطِ والـPHP */
    if (preg_match_all('/(?:#[0-9a-fA-F]{3,8}\b|rgba?\s*\()/u', $src, $m, PREG_OFFSET_CAPTURE)) {
        $n = 0;
        foreach ($m[0] as $hit) {
            $ctx = substr($src, max(0, $hit[1] - 60), 60);
            if (strpos($ctx, 'var(--') !== false) continue;          // قيمةُ رمزٍ
            if (preg_match('/href|src=|route|#\!|anchor/u', $ctx)) continue; // مرساةُ رابط
            $n++;
        }
        if ($n > 0) v($violations, '١ لون مثبَّت', $rel, "$n قيمةَ لونٍ خارجَ الرموز");
    }

    /* ② نمطٌ موضعيٌّ غيرُ مصرَّح — style= بلا وسمِ تصريح data-allow-style */
    $n = preg_match_all('/style\s*=\s*["\'](?![^"\']*data-allow)/u', $src);
    $allowed = preg_match_all('/data-allow-style/u', $src);
    if ($n - $allowed > 0) v($violations, '٢ نمط موضعي', $rel, ($n - $allowed) . ' نمطًا موضعيًّا');

    /* ③ صنفُ زرٍّ غيرُ معتمد */
    if (preg_match_all('/<button[^>]*class\s*=\s*["\']([^"\']+)["\']/u', $src, $m)) {
        foreach ($m[1] as $classAttr) {
            foreach (preg_split('/\s+/', trim($classAttr)) as $cls) {
                if ($cls === '' || strpos($cls, 'btn') !== 0) continue;
                if (!in_array($cls, $approvedBtn, true))
                    v($violations, '٣ صنف زر', $rel, "صنفٌ غيرُ معتمد: $cls");
            }
        }
    }

    /* ④ صفحةٌ خارجَ القشرة — لا بد من inheader (القشرةُ الموحَّدة) */
    /* الأغلفةُ الشرعيةُ تُحمِّل inheader بذاتِها: fin_analysis_shell · eng01_screen_view */
    if (preg_match('/\.php$/', $rel)
        && !preg_match('/(require|include)(_once)?\s*[( ].*(inheader\.php|fin_analysis_shell\.php|eng01_screen_view\.php|u13_screen_kit\.php|dept_gov_space\.php|dept_risk_space\.php)/u', $src)
        && !preg_match('/EMS_PARTIAL|EMS_API|json_encode/u', $src)) {
        v($violations, '٤ خارج القشرة', $rel, 'لا يُحمِّل inheader.php ولا هو جزئيّ/JSON معلَن');
    }

    /* ⑤ جدولٌ خارجَ المكوّنِ الموحَّد — تهيئةُ DataTable محليةٌ ممنوعة */
    if (preg_match('/\.DataTable\s*\(\s*\{/u', $src)) {
        v($violations, '٥ جدول غير موحَّد', $rel, 'تهيئةُ DataTable محليةٌ — المكوّنُ المركزيُّ وحدَه');
    }

    /* ⑥ حقلٌ بلا عنوانٍ مقروء */
    if (preg_match_all('/<(input|select|textarea)\b[^>]*>/iu', $src, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $i => $hit) {
            $tag = $hit[0];
            if (preg_match('/type\s*=\s*["\'](hidden|submit|button)/iu', $tag)) continue;
            if (preg_match('/aria-label|aria-labelledby|placeholder/iu', $tag)) continue;
            if (preg_match('/id\s*=\s*["\']([^"\']+)["\']/iu', $tag, $idm)
                && preg_match('/<label[^>]*for\s*=\s*["\']' . preg_quote($idm[1], '/') . '["\']/u', $src)) continue;
            v($violations, '٦ حقل بلا عنوان', $rel, 'حقلٌ بلا label/aria-label: ' . mb_substr($tag, 0, 60));
        }
    }

    /* ⑧ مصطلحٌ ممنوعٌ في الواجهة */
    foreach ($FORBIDDEN_TERMS as $t) {
        if (mb_stripos($src, $t) !== false)
            v($violations, '٨ مصطلح ممنوع', $rel, "«{$t}» ظاهرٌ في الملف");
    }

    /* ⑨ حالاتُ الشاشة — فراغٌ وخطأٌ وتحميلٌ حدًّا أدنى: الوسمُ الحرفيُّ أو نداءُ
       المكوّنِ المركزيِّ ems_state/ems_states_bundle (includes/ux_components.php) */
    /* شاشاتُ عُدَّةِ u13 تكتسب الحزمةَ من العُدَّةِ نفسِها وقتَ التصيير (سطر 305+) —
       فلا تُطالَب بكتلةٍ ملفّيةٍ تمحوها إعادةُ التوليد.
       وكذلك شاشاتُ ENG-01: غلافُها eng01_screen_view.php يُخرِج الحزمةَ بذاتِه
       بعدَ inheader/insidebar فتستوفيها الثماني دفعةً واحدة */
    $hasBundle = (bool) preg_match('/ems_states_bundle\s*\(/u', $src)
              || (bool) preg_match('/(require|include)(_once)?\s*[( ].*u13_screen_kit\.php/u', $src)
              || (bool) preg_match('/(require|include)(_once)?\s*[( ].*eng01_screen_view\.php/u', $src);
    foreach (array('empty' => 'فراغ', 'error' => 'خطأ', 'loading' => 'تحميل') as $k => $name) {
        if ($hasBundle) break;
        if (!preg_match('/ems[-_]state[-_(\'"]*' . $k . '/u', $src)
            && !preg_match('/ems_state\s*\(\s*[\'"]' . $k . '/u', $src))
            v($violations, '٩ حالة ناقصة', $rel, "بلا حالةِ {$name} (ems-state-{$k} أو ems_state('{$k}'))");
    }

    /* ⑫ شاشةُ دورةٍ بلا خطوةٍ تالية — الوسمُ أو نداءُ ems_next_step إلزاميٌّ لشاشاتِ الدورة */
    if (preg_match('/ems-doc-cycle/u', $src)
        && strpos($src, 'ems-next-step') === false && !preg_match('/ems_next_step\s*\(/u', $src)) {
        v($violations, '١٢ بلا خطوة تالية', $rel, 'شاشةُ دورةٍ (ems-doc-cycle) بلا خطوةٍ تالية');
    }
}
$report['١..٦·٨·٩·١٢'] = 'نطاقُ الفحصِ الملفّي: ' . count($SCOPE) . ' شاشةً مرحَّلة';

/* ═══ ⑦ رابطُ سايدبارٍ بلا مالك — على القاعدةِ كلِّها ═══════════════════ */
$q = $conn->query("SELECT COUNT(*) c FROM nav_items WHERE active=1 AND module_id IS NULL AND permission_code IS NULL");
$orphanNav = $q ? (int) $q->fetch_assoc()['c'] : -1;
$report['٧ رابط بلا مالك'] = "روابطُ سايدبارٍ نشطةٌ بلا شاشةٍ مسجَّلةٍ ولا كودِ صلاحية: {$orphanNav}";
if ($orphanNav > 0) v($violations, '٧ رابط بلا مالك', 'nav_items', "{$orphanNav} رابطًا نشطًا بلا module_id ولا permission_code");

/* ═══ ⑪ دورٌ بأقلَّ من عشرةِ روابطِ سايدبار — على القاعدةِ كلِّها ════════ */
$thin = array();
$q = $conn->query("SELECT n.role_id, r.name, COUNT(*) c
                     FROM nav_items n JOIN roles r ON r.id=n.role_id AND r.status=1
                    WHERE n.active=1 GROUP BY n.role_id HAVING c < 10");
if ($q) while ($row = $q->fetch_assoc()) $thin[] = "{$row['name']} ({$row['c']})";
$report['١١ سايدبار نحيف'] = $thin ? ('أدوارٌ بأقلَّ من عشرةِ روابط: ' . implode(' · ', $thin)) : 'كلُّ دورٍ نشطٍ له ≥10 روابط';
foreach ($thin as $t) v($violations, '١١ سايدبار نحيف', 'nav_items', $t);

/* ═══ ⑩ الثباتُ البصريُّ في الذهبية — خطُّ الأساس .ssdiff ═══════════════ */
$ssdiff = $ROOT . '/.ssdiff';
if (is_dir($ssdiff)) {
    $pend = glob($ssdiff . '/*.diff.txt') ?: array();
    foreach ($pend as $d) v($violations, '١٠ فرق بصري', basename($d), 'فرقٌ بصريٌّ غيرُ معتمدٍ — يُقبل بسببٍ مكتوبٍ أو يُصلَح');
    /* خطُّ الأساسِ هيكلٌ بنيويٌّ (.skel) لا صورة — والعدُّ بلاحقةٍ خاطئةٍ يُبلّغ صفرًا أبدًا */
    $report['١٠ ثبات بصري'] = 'لقطاتُ الأساس: ' . count(glob($ssdiff . '/*.skel') ?: array()) . ' · فروقٌ معلَّقة: ' . count($pend);
} else {
    $report['١٠ ثبات بصري'] = 'لا خطَّ أساسٍ بعدُ (يُبنى مع اعتمادِ الذهبية) — غيرُ مقيسٍ لا مجتاز';
}

/* ═══ أرقامُ النظامِ كلِّه (--baseline) — للتقريرِ السباعيَّ عشرَ ═══════ */
if ($BASELINE_MODE) {
    $globInline = 0; $globColors = 0; $globForbidden = 0; $files = 0;
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $f) {
        $p = str_replace('\\', '/', $f->getPathname());
        if (!preg_match('/\.php$/', $p)) continue;
        if (preg_match('#/(vendor|node_modules|database|docs|tests|tools|\.git|\.claude|storage|app/)#', $p)) continue;
        $s = @file_get_contents($f->getPathname());
        if ($s === false) continue;
        $files++;
        $globInline += preg_match_all('/style\s*=\s*["\']/u', $s);
        $globColors += preg_match_all('/#[0-9a-fA-F]{6}\b/u', $s);
        foreach ($FORBIDDEN_TERMS as $t) if (mb_stripos($s, $t) !== false) $globForbidden++;
    }
    echo "── أرقامُ الأساسِ على {$files} ملفَّ شاشة ──\n";
    echo "أنماطٌ موضعية: {$globInline}\nألوانٌ مثبَّتة: {$globColors}\nملفاتٌ فيها مصطلحٌ ممنوع: {$globForbidden}\n";
}

/* ═══ الحكم ═════════════════════════════════════════════════════════════ */
echo "════ بواباتُ المنعِ الاثنتا عشرةَ — UXW-01 §9-4 ════\n";
foreach ($report as $g => $line) echo "  {$g}: {$line}\n";
if ($declaredHits) {
    echo "\n◆ فجواتٌ معلَنةٌ بقرارٍ معلَّق (" . count($declaredHits) . ") — لا تُرسِّب ولا تُنسى:\n";
    foreach ($declaredHits as $x) echo "  [{$x[0]}] {$x[1]} — {$x[2]}\n      ↳ {$x[3]}\n";
}
if ($violations) {
    echo "\n✗ مخالفات: " . count($violations) . "\n";
    foreach ($violations as $x) echo "  [{$x[0]}] {$x[1]} — {$x[2]}\n";
    exit(1);
}
echo "\n✔ البواباتُ مجتازةٌ على النطاقِ المرحَّلِ الحالي (" . count($SCOPE) . " شاشة)\n";
exit(0);
