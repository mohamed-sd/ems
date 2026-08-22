<?php
/**
 * tests/injfrd01_jrn005_training_isolation.php
 *   شاهدُ FR-JRN-005 — سياقُ التدريبِ يُحمَل · وهل يُقرأ؟
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيارُ بنصِّه**: «كلُّ سجلٍّ من حسابِ تدريبٍ يحمل سياقَ التدريبِ **إلى
 *   آخرِ السلسلة**» · ومعيارُ القبول «**بوابةٌ تُثبت صفرَ تسرُّبٍ إلى إيرادٍ أو
 *   مصروفٍ أو ذمةٍ أو مؤشر**» · وسلوكُ الفشل «رفضُ دخولِ الموسومِ في الإنتاج».
 *
 * ◆ **والتمييزُ الرباعيُّ يُقال هنا كاملًا** (§الحكم النهائي):
 *   · **BUILT** — العمودُ قائمٌ في `users` و`ems_business_events` و
 *     `fin_financial_events`، ومعه `users.training_since`.
 *   · **WIRED** — `EventPublisher::stampTraining()` ينسخ علمَ المستخدمِ إلى
 *     الحدثِ عندَ النشر.
 *   · **ENFORCED** — ✘ **لا**: **لا قارئَ واحدًا يُرشِّح `is_training`** في
 *     شجرةِ الإنتاجِ خارجَ الناشرِ نفسِه. فالعلمُ **يُحمَل ولا يُقرأ**.
 *   · **EXERCISED** — ✘ **لا**: صفرُ مستخدمِ تدريبٍ وصفرُ سجلٍّ موسوم.
 *
 * ◆ **ومقامٌ صفرٌ ليس نجاحًا**: «صفرُ تسرُّبٍ» اليومَ صفرٌ **لأن لا شيءَ يُسرَّب**
 *   لا لأن بوابةً تمنع. فيُعلَن ذلك نصًّا، ولا يُقرأ إغلاقًا.
 *
 * ◆ **والحملُ يُجرَّب حيًّا**: يُوسَم مستخدمٌ اختباريٌّ مؤقتًا، ويُنشأ حدثٌ
 *   باسمِه، ويُنادى الناسخُ، ويُقاس أن العلمَ انتقل — **ثم يُردُّ كلُّ شيءٍ
 *   ويُتحقَّق من الردّ**. فالحملُ يصير مقيسًا لا موصوفًا.
 *
 * التشغيل: php tests/injfrd01_jrn005_training_isolation.php [--exercise]
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

$exercise = in_array('--exercise', $argv, true);
echo "══ FR-JRN-005 — سياقُ التدريبِ: يُحمَل · أيُقرأ؟ ══\n";

/* ── ① BUILT — العمودُ في السلسلةِ كلِّها ────────────────────────────────── */
$TBL = array('users', 'ems_business_events', 'fin_financial_events');
$missing = array();
foreach ($TBL as $t) {
    if (n($db, "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}'
                   AND COLUMN_NAME = 'is_training'") === 0) { $missing[] = $t; }
}
chk(empty($missing), '**BUILT** · عمودُ التدريبِ في السلسلةِ كلِّها',
    empty($missing) ? implode(' · ', $TBL) : 'ناقصٌ: ' . implode(' · ', $missing));
chk(n($db, "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'training_since'") === 1,
    'ومعه زمنُ بدءِ التدريب', 'users.training_since');

/* ── ② WIRED — الناشرُ ينسخ العلم ───────────────────────────────────────── */
$pub = (string) @file_get_contents($ROOT . '/app/Core/EventPublisher.php');
$hasFn   = strpos($pub, 'function stampTraining') !== false;
/* ◆ **`preg_match` ترجع 1 دائمًا لا عددَ المطابقات** — فشرطُ «> 1» لا
 *   يتحقّق أبدًا، **فقُرئ الموصولُ غيرَ موصول**. والناسخُ يُنادى مرَّتَين
 *   فعلًا (`:443` و`:653`). ⇒ `preg_match_all`، **وتُطبَع مواضعُ النداء**
 *   فلا يُصدَّق العدُّ بلا شاهدِه. */
$callN = preg_match_all('~stampTraining\s*\(~', $pub);
$isCalled = ($callN >= 2);   /* التعريفُ + نداءٌ واحدٌ على الأقل */
chk($hasFn && $isCalled, '**WIRED** · الناشرُ ينسخ علمَ المستخدمِ إلى الحدث',
    'EventPublisher::stampTraining' . ($isCalled ? ' — ويُنادى ' . ($callN - 1) . ' مرّة'
                                        : ' — **معرَّفٌ ولا يُنادى** ✘'));

/* ── ③ ENFORCED — أيُرشِّح قارئٌ العلمَ؟ **هذا هو السؤال** ───────────────── */
echo "\n── ③ هل يُقرأ العلمُ في المجاميع؟ ──\n";
$SKIP = array('/vendor/', '/node_modules/', '/.git/', '/docs/', '/storage/',
              '/tests/', '/tools/', '/database/');
$readers = array(); $scanned = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') { continue; }
    $pp = str_replace(DIRECTORY_SEPARATOR, '/', $f->getPathname());
    $sk = false;
    foreach ($SKIP as $k) { if (strpos($pp, $k) !== false) { $sk = true; break; } }
    if ($sk) { continue; }
    $scanned++;
    $src = (string) @file_get_contents($pp);
    if (strpos($src, 'is_training') === false) { continue; }
    $rel = str_replace($ROOT . '/', '', $pp);
    if ($rel === 'app/Core/EventPublisher.php') { continue; }   /* الكاتبُ لا القارئ */
    /* قارئٌ يُرشِّح: يذكر العلمَ في WHERE أو شرطٍ */
    if (preg_match('~(WHERE|AND|OR)[^;]{0,80}is_training~i', $src)) { $readers[] = $rel; }
}
printf("  مُسِح: %d ملفَّ إنتاج · **قارئٌ يُرشِّح العلمَ: %d**\n", $scanned, count($readers));
foreach (array_slice($readers, 0, 6) as $r) { echo "     · {$r}\n"; }
chk(count($readers) > 0,
    '**ENFORCED** · قارئٌ واحدٌ على الأقل يُرشِّح التدريبَ من المجاميع',
    count($readers) > 0 ? count($readers) . ' قارئًا'
        : '**صفرٌ — العلمُ يُحمَل ولا يُقرأ**: لا إيرادٌ ولا مصروفٌ ولا ذمةٌ ولا مؤشرٌ يستثنيه');

/* ── ④ EXERCISED — المقامُ الحيّ ────────────────────────────────────────── */
echo "\n── ④ المقامُ الحيّ ──\n";
$trUsers = n($db, "SELECT COUNT(*) FROM `users` WHERE COALESCE(`is_training`,0) = 1");
$trBiz   = n($db, "SELECT COUNT(*) FROM `ems_business_events` WHERE COALESCE(`is_training`,0) = 1");
$trFin   = n($db, "SELECT COUNT(*) FROM `fin_financial_events` WHERE COALESCE(`is_training`,0) = 1");
printf("  مستخدمو تدريب=%d · أحداثٌ موسومة=%d · وقائعُ ماليةٌ موسومة=%d\n",
       $trUsers, $trBiz, $trFin);
chk($trUsers > 0,
    '**EXERCISED** · ثمَّ حسابُ تدريبٍ حيٌّ يُقاس عليه',
    $trUsers > 0 ? "{$trUsers} حسابًا"
        : '**صفرٌ — فـ«صفرُ تسرُّبٍ» اليومَ صفرٌ لأن لا شيءَ يُسرَّب، لا لأن بوابةً تمنع**');

/* ── ⑤ الحملُ يُجرَّب حيًّا — ويُردُّ كلُّ شيء ─────────────────────────────── */
if ($exercise) {
    echo "\n── ⑤ تجربةُ الحملِ حيًّا ──\n";
    $uid = n($db, "SELECT `id` FROM `users` WHERE COALESCE(`is_deleted`,0) = 0
                    ORDER BY `id` DESC LIMIT 1");
    if ($uid <= 0) { echo "  ⛔ لا مستخدمَ يُجرَّب عليه — أُوقِف\n"; exit(1); }
    $was = n($db, "SELECT COALESCE(`is_training`,0) FROM `users` WHERE `id` = {$uid}");
    $db->query("UPDATE `users` SET `is_training` = 1 WHERE `id` = {$uid}");
    $now = n($db, "SELECT COALESCE(`is_training`,0) FROM `users` WHERE `id` = {$uid}");
    if ($now !== 1) { echo "  ⛔ **لم يُوسَم المستخدم** — أُوقِف قبلَ قياسٍ كاذب\n"; exit(1); }
    printf("  ◆ وُسِم المستخدم #%d تدريبًا مؤقتًا (كان %d) — **والوسمُ مُثبَت**\n", $uid, $was);

    $co  = n($db, "SELECT `company_id` FROM `ems_business_events` LIMIT 1");
    $key = 'jrn005.belt.' . getmypid();
    $db->query("INSERT INTO `ems_business_events`
        (`company_id`,`event_no`,`event_uuid`,`event_key`,`category`,`source_module`,
         `source_ref`,`entity_type`,`entity_id`,`consumers_declared`,`created_by`,`created_at`)
        VALUES ({$co},'BELT-JRN005',UUID(),'{$key}','belt','belt',0,'belt',0,1,{$uid},NOW())");
    $eid = (int) $db->insert_id;
    if ($eid <= 0) {
        $db->query("UPDATE `users` SET `is_training` = {$was} WHERE `id` = {$uid}");
        echo "  ⛔ **تعذّر إنشاءُ الحدث** — " . $db->error . " · رُدَّ المستخدم\n";
        exit(1);
    }
    echo "  ◆ أُنشئ حدثٌ #{$eid} باسمِه — **ووجودُه مُثبَت**\n";

    require_once $ROOT . '/app/Core/EventPublisher.php';
    $ref = new \ReflectionClass('\App\Core\EventPublisher');
    $m = $ref->getMethod('stampTraining');
    $m->setAccessible(true);
    $m->invoke(null, $db, 'ems_business_events', $eid, $uid);
    $stamped = n($db, "SELECT COALESCE(`is_training`,0) FROM `ems_business_events` WHERE `id` = {$eid}");
    chk($stamped === 1, '**الحملُ يعمل** — علمُ المستخدمِ انتقل إلى الحدث',
        "الحدث #{$eid} ⇒ is_training={$stamped}");

    /* الردُّ والتحقُّقُ منه */
    $db->query("DELETE FROM `ems_business_events` WHERE `id` = {$eid}");
    $db->query("UPDATE `users` SET `is_training` = {$was} WHERE `id` = {$uid}");
    $leftE = n($db, "SELECT COUNT(*) FROM `ems_business_events` WHERE `id` = {$eid}");
    $leftU = n($db, "SELECT COALESCE(`is_training`,0) FROM `users` WHERE `id` = {$uid}");
    chk($leftE === 0 && $leftU === $was,
        '**ورُدَّ كلُّ شيءٍ ومُثبَتٌ ردُّه** — لا أثرَ للتجربة',
        "الحدثُ المتبقي={$leftE} · علمُ المستخدم={$leftU} (كان {$was})");
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
echo "◆ **ولا يُقرأ هذا إغلاقًا**: الحملُ مبنيٌّ وموصولٌ، **والقراءةُ غيرُ منفَّذة**\n";
echo "  والتجربةُ الحيّةُ صفر. والفرقُ بين الأربعةِ لا يُختصر في «تمّ».\n";
exit($bad === 0 ? 0 : 1);
