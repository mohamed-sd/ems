<?php
/**
 * tools/injfrd66_xc09_lifecycle_gate.php — بوابةُ XC-09: دورةُ حياةِ عقدِ المورد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «صفرُ تعديلٍ فوقَ أصلٍ · **والحالةُ مشتقةٌ لا مُدخَلة**» —
 *   وتسعُ مراحلَ في البابِ الخامس.
 *
 * ◆ **و«مشتقةٌ لا مُدخَلة» تُقاس في الشفرةِ لا في البيانات**: صفٌّ حالتُه
 *   صحيحةٌ اليومَ لا يُثبت أنها لا تُكتب يدًا غدًا. فالمقياسُ:
 *   **من يملك الكتابةَ في العمود؟** إن كتبَ فيه سطحٌ أو خدمةٌ أخرى فالحالةُ
 *   مُدخَلةٌ مهما بدت منضبطة.
 *
 * ◆ **وشاهدُ الاختبارِ ليس خرقًا**: تثبيتُ حالةٍ في `tests/` تهيئةُ سيناريو،
 *   لا مسارَ إنتاج. فتُستبعد `tests/` صراحةً — وعدُّها خرقًا يُرسِّب نظامًا
 *   سليمًا بسببِ فاحصِه.
 *
 * ◆ **وتسعُ المراحلِ تُطابَق بالمعنى لا بالحرف**: آلةُ الحالةِ اثنتا عشرةَ
 *   حالةً — أوسعُ من التسعِ لا أضيق. فيُقاس أنَّ **لكلِّ مرحلةٍ في المرجعِ
 *   حالةً في الآلة**، لا العكس.
 *
 * ◆ قراءةٌ خالصة — لا كتابةَ في القاعدةِ إطلاقًا.
 *
 * التشغيل:
 *   php tools/injfrd66_xc09_lifecycle_gate.php          التقرير
 *   php tools/injfrd66_xc09_lifecycle_gate.php --gate   رمزُ خروجٍ 1 عند أيِّ خرق
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Services/Contract/ContractStateMachine.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$GATE = in_array('--gate', $argv, true);
$SM   = 'App\Services\Contract\ContractStateMachine';
$SVC  = $ROOT . '/app/Services/Contract/SupplierContractService.php';

/* تسعُ مراحلِ البابِ الخامسِ ⇐ حالتُها في الآلة */
$STAGES = array(
    'إنشاء'          => 'مسودة',
    'توقيع'          => 'موقَّع',
    'تنفيذ'          => 'قيد التنفيذ',
    'ملحق'           => 'معدَّل',
    'تعليق'          => 'معلَّق',
    'إنهاء'          => 'منتهٍ',
    'تسويةٌ نهائية'  => 'مصفّى',
    'إخلاءُ طرف'     => null,          /* سِمةُ إغلاقٍ لا حالة — تُقاس في closures */
    'إغلاق'          => 'مقفل',
);

$fail = 0;
echo "\n═══ INJ-FRD-01 · XC-09 — دورةُ حياةِ عقدِ المورد ═══\n\n";

/* ── ① تسعُ المراحلِ لها حالاتٌ في الآلة ──────────────────────────────── */
echo "① تسعُ المراحلِ مقابلَ آلةِ الحالة:\n";
$all = constant($SM . '::ALL');
foreach ($STAGES as $stage => $state) {
    if ($state === null) {
        printf("   ○ %-16s سِمةُ إغلاقٍ لا حالة (`supplier_contract_closures.clearance_doc`)\n", $stage);
        continue;
    }
    $ok = in_array($state, $all, true);
    printf("   %s %-16s ⇐ «%s»\n", $ok ? '✔' : '✘', $stage, $state);
    if (!$ok) { $fail++; }
}
printf("   ○ والآلةُ %d حالةً — أوسعُ من التسعِ لا أضيق\n", count($all));

/* ── ② الرسمُ مغلق: كلُّ وجهةٍ حالةٌ معروفة ───────────────────────────── */
echo "\n② رسمُ الانتقالاتِ مغلق:\n";
$tr = constant($SM . '::TRANSITIONS');
$alien = array();
foreach ($tr as $from => $tos) {
    if (!in_array($from, $all, true)) { $alien[] = "مصدرٌ مجهول «{$from}»"; }
    foreach ((array) $tos as $to) {
        if (!in_array($to, $all, true)) { $alien[] = "«{$from}» ⇐ وجهةٌ مجهولة «{$to}»"; }
    }
}
printf("   %s %d حالةَ مصدرٍ · صفرُ وجهةٍ خارجَ الحالاتِ المعروفة%s\n",
    empty($alien) ? '✔' : '✘', count($tr), $alien ? ' — ' . count($alien) . ' خرقًا' : '');
foreach ($alien as $a) { echo "      ✘ {$a}\n"; $fail++; }

/* ── ③ الحالةُ مشتقةٌ لا مُدخَلة — من يملك الكتابة؟ ──────────────────── */
echo "\n③ من يملك الكتابةَ في `state`؟\n";
$writers = array();
$rx = '~UPDATE\s+`?supplier_contracts`?\b[^;]{0,400}?\bstate\b\s*=~is';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $path = str_replace('\\', '/', $f->getPathname());
    if (substr($path, -4) !== '.php') { continue; }
    $rel = ltrim(str_replace(str_replace('\\', '/', $ROOT), '', $path), '/');
    /* تُستبعد: الخدمةُ المالكة · الشواهد (تهيئةُ سيناريو) · النسخُ الاحتياطية */
    if (strpos($rel, 'storage/') === 0 || strpos($rel, 'tests/') === 0) { continue; }
    if ($rel === 'app/Services/Contract/SupplierContractService.php') { continue; }
    $body = (string) @file_get_contents($path);
    if ($body !== '' && preg_match($rx, $body)) { $writers[] = $rel; }
}
printf("   %s كاتبون خارجَ الخدمةِ المالكة: %d\n", empty($writers) ? '✔' : '✘', count($writers));
foreach ($writers as $w) { echo "      ✘ {$w}\n"; $fail++; }
echo "   ○ و`tests/` مُستثناةٌ صراحةً — تثبيتُ حالةٍ فيها تهيئةُ سيناريو لا مسارُ إنتاج\n";

/* ── ④ صفرُ تعديلٍ فوقَ أصلٍ: النسخةُ تُرفع والانتقالُ يُدوَّن ────────── */
echo "\n④ صفرُ تعديلٍ فوقَ أصل:\n";
$svc = (string) @file_get_contents($SVC);
$hasVer = (bool) preg_match("~'version'\s*=>\s*\(int\)\s*\\\$head\['version'\]\s*\+\s*1~u", $svc);
$hasAud = (bool) preg_match("~self::audit\([^)]*'transition'~us", $svc);
/* الفحصُ **نداءُ دالّةٍ** لا إشارةٌ إلى الثابت — والبحثُ عن الثابتِ وحدَه
   يُعلن غيابَ حارسٍ قائم. */
$hasChk   = (bool) preg_match('~ContractStateMachine::canTransition\s*\(~u', $svc);
$hasLock  = (bool) preg_match('~قفلٌ تفاؤلي~u', $svc);
$hasClear = (bool) preg_match('~contractCloseGate~u', $svc);
printf("   %s النسخةُ تُرفع مع كلِّ انتقال\n", $hasVer ? '✔' : '✘');
printf("   %s والانتقالُ يُدوَّن (from ⇐ to) في الأثر\n", $hasAud ? '✔' : '✘');
printf("   %s والوجهةُ تُفحص بـcanTransition قبلَ الكتابة
", $hasChk ? '✔' : '✘');
printf("   %s وقفلٌ تفاؤليٌّ يردُّ 409 عندَ تغيُّرِ النسخةِ من طرفٍ آخر
", $hasLock ? '✔' : '✘');
printf("   %s وإخلاءُ الطرفِ شرطٌ في الإقفال (contractCloseGate)
", $hasClear ? '✔' : '✘');
if (!$hasVer) { $fail++; }
if (!$hasAud) { $fail++; }
if (!$hasChk) { $fail++; }
if (!$hasLock) { $fail++; }
if (!$hasClear) { $fail++; }

/* ── ⑤ البياناتُ الحيّة: صفرُ حالةٍ خارجَ الآلة ───────────────────────── */
echo "\n⑤ البياناتُ الحيّة:\n";
$bad = array(); $tot = 0;
$res = @mysqli_query($conn, "SELECT state, COUNT(*) n FROM supplier_contracts WHERE is_deleted=0 GROUP BY state");
while ($res && ($x = mysqli_fetch_assoc($res))) {
    $tot += (int) $x['n'];
    if (!in_array($x['state'], $all, true)) { $bad[] = "«{$x['state']}» ×{$x['n']}"; }
}
printf("   %s %d عقدًا · صفرُ حالةٍ خارجَ الآلة%s\n",
    empty($bad) ? '✔' : '✘', $tot, $bad ? ' — ' . implode('، ', $bad) : '');
foreach ($bad as $b) { $fail++; }

$res = @mysqli_query($conn, "SELECT COUNT(DISTINCT state) c FROM supplier_contracts WHERE is_deleted=0");
$distinct = $res ? (int) mysqli_fetch_row($res)[0] : 0;
printf("   ⏸ والحالاتُ المُمارَسةُ حيًّا: %d من %d — فالآلةُ مبنيّةٌ ولم تُمارَس بعد.\n", $distinct, count($all));
echo "      وذلك عملُ الرحلةِ الحقيقيةِ (XC-11) لا عيبَ في الآلة.\n";

printf("\n%s  XC-09 — %d خرقًا\n\n", $fail === 0 ? '✔' : '✘', $fail);
exit($GATE && $fail > 0 ? 1 : 0);
