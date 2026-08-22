<?php
/**
 * tests/injfrd01_app003_005_ladder_code_coverage.php
 *   شاهدُ FR-APP-003 · FR-APP-005 — رمزُ رفضٍ واحد · ولا نوعَ حيٍّ بلا سلّم
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **FR-APP-003**: «رمزُ رفضٍ **موحَّدٌ واحدٌ** للمنعِ في المواضعِ العشرةِ كلِّها
 *   — لا رمزَ لكلِّ موضع» · ومعيارُ القبول «**مسحُ شجرةٍ يُخرج صفرَ رمزٍ محليّ**»
 *   · وسلوكُ الفشل «لا رمزَ محليًّا في أيِّ موضع».
 *
 * ◆ **والمقيسُ قبلَ التوحيد**: تسعةُ مواضعَ تنادي الحارس — **ثلاثةٌ تكتب
 *   `'GOV-FAIL-422'` حرفًا**، و**اثنان لا يُصدران رمزًا أصلًا**، وأربعةٌ تُمرِّر
 *   رمزَ الحارس. **ورمزٌ محليٌّ ولو طابق اليومَ يتفرّق غدًا**: من يغيّره في
 *   موضعٍ لا يعرف الثمانيةَ الأخرى.
 *
 * ◆ **والقيمةُ ليست مخترَعة**: `GOV-FAIL-422` هي المستعملةُ سلفًا — **يُرفَع
 *   الموجودُ إلى ثابتٍ ولا يُبتدَع رمزٌ جديد** (§ثالثًا: لا تخترع رمزًا).
 *
 * ◆ **FR-APP-005**: «كلُّ نوعِ كيانٍ **منتَجٍ فعلًا** له سلّمٌ حاكم — وما لا سلّمَ
 *   له **يُوقَف ولا يسقط للاحتياط**» · ومعيارُه «صفرُ نوعِ كيانٍ حيٍّ بلا سلّم».
 *   **والمقامُ من المنتَجِ الحيِّ لا من قائمةٍ مكتوبة.**
 *
 * التشغيل: php tests/injfrd01_app003_005_ladder_code_coverage.php [--negative]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$db = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }

$neg = in_array('--negative', $argv, true);
echo "══ FR-APP-003 · FR-APP-005 — رمزٌ واحد · ولا نوعَ بلا سلّم ══\n";

/* ── ① المواضعُ تُمسح من الشجرةِ لا من قائمةٍ عندي ────────────────────────── */
$SKIP = array('/vendor/', '/node_modules/', '/.git/', '/docs/', '/storage/',
              '/tests/', '/tools/', '/database/');
$sites = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') { continue; }
    $pp = str_replace(DIRECTORY_SEPARATOR, '/', $f->getPathname());
    $sk = false;
    foreach ($SKIP as $k) { if (strpos($pp, $k) !== false) { $sk = true; break; } }
    if ($sk) { continue; }
    $src = (string) @file_get_contents($pp);
    if (strpos($src, 'ems_ladder_guard(') === false) { continue; }
    $rel = str_replace($ROOT . '/', '', $pp);
    if ($rel === 'includes/unit_chain_helpers.php' || $rel === 'includes/ladder_gate.php') { continue; }
    $sites[$rel] = $src;
}
printf("  مواضعُ تنادي حارسَ السلّم: **%d**\n", count($sites));
chk(count($sites) > 0, '**المقامُ غيرُ صفريّ** — ثمَّ مواضعُ تُقاس', count($sites) . ' موضعًا');

/* ── ② الثابتُ معرَّفٌ في بيتِ المنطق ──────────────────────────────────── */
$core = (string) @file_get_contents($ROOT . '/includes/unit_chain_helpers.php');
chk(strpos($core, "define('EMS_LADDER_DENY_CODE'") !== false,
    '**ثابتُ الرمزِ معرَّفٌ في بيتِ المنطقِ الواحد**', 'EMS_LADDER_DENY_CODE');

/* ── ③ **صفرُ رمزٍ محليّ** في مواضعِ الرفض ─────────────────────────────── */
$local = array(); $usesConst = 0; $noCode = array();
foreach ($sites as $rel => $src) {
    /* كتلةُ الرفضِ: من `if (!$__lg['ok'])` إلى نهايةِ الكتلةِ التقريبية */
    /* ◆ **مُستخرِجٌ ضيِّقٌ يقول «لا كتلة» عن كتلةٍ قائمة**: أوّلُ تعبيرٍ
     *   انتظر `}` على سطرٍ مستقلٍّ فلم يطابق شكلَ `if/else` في
     *   `Suppliers/settlements.php` — **فقُرئ الموصولُ بلا رمز**.
     *   ⇒ تُؤخَذ 400 محرفٍ بعدَ الشرطِ بلا اشتراطِ شكلِ الإغلاق. */
    if (!preg_match('~if \(!\$__lg\[\x27ok\x27\]\)(.{0,400})~s', $src, $m)) {
        $noCode[] = $rel . ' (لا كتلةَ رفضٍ تُقرأ)';
        continue;
    }
    $blk = $m[1];
    $hasConst = strpos($blk, 'EMS_LADDER_DENY_CODE') !== false;
    $hasGuard = strpos($blk, "lg['code']") !== false;
    $hasLit   = preg_match('~\x27GOV-[A-Z]+-\d+\x27~', $blk);
    if ($hasLit) { $local[] = $rel; }
    if ($hasConst || $hasGuard) { $usesConst++; }
    if (!$hasConst && !$hasGuard && !$hasLit) { $noCode[] = $rel . ' (بلا رمزٍ أصلًا)'; }
}
printf("  يستعمل الثابتَ أو رمزَ الحارس: **%d من %d**\n", $usesConst, count($sites));
chk(empty($local),
    'FR-APP-003 · **صفرُ رمزٍ محليٍّ حرفيٍّ في كتلةِ الرفض**',
    empty($local) ? 'صفرُ حرفيّ' : count($local) . ': ' . implode(' · ', array_slice($local, 0, 4)));
chk(empty($noCode),
    'ولا موضعَ **بلا رمزٍ أصلًا** — فرفضٌ بلا رمزٍ لا يُتتبَّع',
    empty($noCode) ? 'صفرٌ' : count($noCode) . ': ' . implode(' · ', array_slice($noCode, 0, 4)));

/* ── ④ FR-APP-005 — كلُّ نوعِ كيانٍ حيٍّ له سلّم ─────────────────────────── */
echo "\n── ④ الأنواعُ الحيّةُ وسلاليمُها ──\n";
$ladders = array();
/* ◆ **عمودٌ باسمٍ مُخمَّنٍ يُخرج صفرًا فيُتَّهم البريء**: قرأتُ
 *   `entity_kind` والعمودُ اسمُه `entity_type` — فعادت السلاليمُ **صفرًا**
 *   و**رُميت الأنواعُ الثمانيةُ والثلاثون كلُّها بلا سلّم**. ⇒ يُقرأ
 *   الاسمُ من المخطَّطِ لا من الذاكرة. */
$r = $db->query("SELECT DISTINCT `entity_type` FROM `gov_journey_ladders`
                  WHERE COALESCE(`entity_type`,'') <> ''");
/* ◆ **والقيمةُ قد تحمل أكثرَ من نوعٍ مفصولةً** — تُفكَّك ولا تُقرأ نصًّا واحدًا */
while ($r && $x = $r->fetch_row()) {
    foreach (preg_split('~\s*·\s*~u', (string) $x[0]) as $one) {
        $one = strtolower(trim($one));
        if ($one !== '') { $ladders[$one] = true; }
    }
}
if (empty($ladders)) {
    /* العمودُ قد يُسمّى غيرَ ذلك — يُقرأ من المخطَّطِ لا يُخمَّن */
    $cols = array();
    $r = $db->query("SHOW COLUMNS FROM `gov_journey_ladders`");
    while ($r && $x = $r->fetch_row()) { $cols[] = $x[0]; }
    echo "  ◆ أعمدةُ السلاليم: " . implode(' · ', $cols) . "\n";
}
$live = array();
$r = $db->query("SELECT `entity_type`, COUNT(*) c FROM `ems_business_events`
                  WHERE COALESCE(`entity_type`,'') <> '' GROUP BY `entity_type`");
while ($r && $x = $r->fetch_row()) { $live[strtolower(trim($x[0]))] = (int) $x[1]; }
printf("  أنواعُ كياناتٍ **منتَجةٌ فعلًا**: %d · سلاليمُ مُعلَنة: %d\n",
       count($live), count($ladders));
chk(count($live) > 0, '**المقامُ من المنتَجِ الحيِّ** لا من قائمةٍ مكتوبة',
    count($live) . ' نوعًا');
/* ◆ **والمقامُ يُقسَم لا يُجمَع**: 38 نوعًا مُنتَجًا، والسلاليمُ تحرس
 *   أنواعَ **الاعتمادِ والصرفِ** لا كلَّ واقعةِ نظام. فيُعرَض العددان
 *   منفصلَين: **ما يحرسه سلّمٌ** و**ما لا سلّمَ له** — ولا يُقرأ الثاني
 *   عطبًا قبلَ أن يُقرَّر أيُّ الأنواعِ يخضع لسلّمٍ أصلًا (قرارُ مالك). */
$noLadder = array(); $guarded = array();
foreach ($live as $k => $c) {
    if (isset($ladders[$k])) { $guarded[] = $k; } else { $noLadder[] = "{$k}({$c})"; }
}
printf("  **يحرسه سلّمٌ مُعلَن: %d** · بلا سلّمٍ مُعلَن: %d
", count($guarded), count($noLadder));
chk(empty($noLadder),
    'FR-APP-005 · **صفرُ نوعِ كيانٍ حيٍّ بلا سلّم**',
    empty($noLadder) ? 'كلُّها مغطّاة'
        : count($noLadder) . ' بلا سلّم: ' . implode(' · ', array_slice($noLadder, 0, 6)));

if ($neg) {
    echo "\n── الحزامُ السالب ──\n";
    /* ◆ يُدسُّ ملفٌّ جديدٌ ينادي الحارسَ برمزٍ محليٍّ حرفيّ — يجب أن يُرصد */
    $belt = $ROOT . '/Reports/_app003_belt.php';
    file_put_contents($belt,
        "<?php\n/* حزامُ FR-APP-003 — رمزٌ محليٌّ حرفيّ. يُكنس فورًا. */\n"
      . "\$__lg = ems_ladder_guard(\$conn, 'LD-99', 4, 'belt', 1, 1, 'belt');\n"
      . "if (!\$__lg['ok']) {\n"
      . "    ems_gov_flash_redirect('x.php', 'رفض', 'GOV-FAIL-422', '');\n"
      . "    exit();\n"
      . "}\n");
    clearstatcache();
    $src2 = (string) @file_get_contents($belt);
    if ($src2 === '' || strpos($src2, 'GOV-FAIL-422') === false) {
        echo "  ⛔ **لم يُدَسَّ الحزام** — أُوقِف\n"; exit(1);
    }
    echo "  ◆ دُسَّ موضعٌ برمزٍ محليٍّ — **ومحتواه مُثبَتٌ قبلَ القياس**\n";
    /* ◆ **`&&` تُنتج bool لا int** — فشرطُ `=== 1` لا يتحقّق أبدًا ويُقرأ
     *   الرسوبُ نجاحًا في التفصيلِ ورسوبًا في العدّ. ⇒ يُقارَن منطقيًّا. */
    $caught = (bool) (preg_match('~if \(!\$__lg\[\x27ok\x27\]\)(.{0,400})~s', $src2, $mm)
              && preg_match('~\x27GOV-[A-Z]+-\d+\x27~', $mm[1]));
    chk($caught, '**والكاشفُ يمسك الرمزَ المحليَّ المدسوس**',
        $caught ? 'أُمسك ✔' : 'مرَّ ✘ — الكاشفُ لا يعمل');
    @unlink($belt);
    clearstatcache();
    chk(!is_file($belt), 'وكُنس الحزامُ أثرَه');
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
