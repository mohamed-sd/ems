<?php
/**
 * tools/injfrd01_sec006_raw_query_register.php
 *   FR-SEC-006 · CHG-SEC-SCOPE-01 — سجلُّ استثناءاتِ الاستعلامِ الخام
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلبُ بنصِّه** (الدفتر · GAP-29 · P1): «صفرُ استعلامٍ خامٍّ على جدولِ
 *   مستأجِرٍ **خارجَ سجلِّ استثناءاتٍ معتمَدٍ لكلٍّ سببٌ ومالكٌ وتاريخُ مراجعة**»
 *   · وسلوكُ الفشل «**رسوبُ البناءِ عندَ ملفٍّ جديد**».
 *
 * ◆ **والسقّاطةُ وحدَها لا تكفي**: `injfix01_raw_query_ratchet` تمنع ارتفاعَ
 *   **العدد**، **ومقايضةٌ تعبرها**: ملفٌّ يُحوَّل وملفٌّ جديدٌ يُضاف فيبقى 608.
 *   ⇒ السجلُّ يعرف الملفاتِ **بأسمائِها** — فأيُّ اسمٍ خارجَه يُرسِّب مهما كان
 *   العدد. وهذا هو الفرقُ بين «لا يزيد» و«صفرَ ملفٍّ غيرِ مسجَّل».
 *
 * ◆ **والمالكُ مشتقٌّ لا مخترَع**: `nav_items.route` ⇒ `modules.owner_role_id`
 *   ⇒ `roles.name`. وما لا مسارَ له في القوائم (طبقةُ `app/` و`includes/`)
 *   لا يُنسَب لدورٍ بالتخمين — يُكتب `NEEDS_GOVERNING_SOURCE`.
 *
 * ◆ **ودوريةُ المراجعةِ بلا مصدرٍ حاكم**: بُحث عنها في الدفترِ والوثيقةِ
 *   ووثائقِ المواءمةِ فلم تُذكر. و§ثالثًا يمنع اختراعَ **مدة**. ⇒ الحقلُ
 *   يُكتب `NEEDS_GOVERNING_SOURCE` صراحةً — **لا فارغًا ولا مخترَعًا**.
 *
 * التشغيل:  php tools/injfrd01_sec006_raw_query_register.php --build
 *           php tools/injfrd01_sec006_raw_query_register.php --report
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/tools/lib/raw_query_scan.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$REG   = $ROOT . '/docs/raw_query_exceptions.json';
$build = in_array('--build', $argv, true);

/* ── مالكو المسارات — مشتقُّون من القوائمِ والوحداتِ والأدوار ─────────────── */
$owners = array();
$q = $conn->query("SELECT DISTINCT n.`route`, r.`name`
                     FROM `nav_items` n
                     JOIN `modules` m ON m.`id` = n.`module_id`
                     JOIN `roles`   r ON r.`id` = m.`owner_role_id`
                    WHERE n.`route` LIKE '%.php'");
while ($q && $x = $q->fetch_row()) { $owners[strtolower(ltrim($x[0], '/'))] = $x[1]; }

$scan = ems_raw_query_hits($ROOT, $conn);
$hits = array_keys($scan['hits']);

printf("════ FR-SEC-006 · سجلُّ استثناءاتِ الاستعلامِ الخام ════\n");
printf("  مُسِح: %d ملفَّ إنتاج · جداولُ مستأجِرٍ في المخطَّط: %d\n",
       $scan['scanned'], $scan['tenant_tables']);
printf("  يلمس جدولَ مستأجِرٍ باستعلامٍ خام: **%d**\n", count($hits));
printf("  مساراتٌ لها مالكٌ مشتقٌّ من القوائم: %d\n\n", count($owners));

if (!$build) {
    if (!is_file($REG)) { exit("  ◆ لا سجلَّ بعد — شغّل --build\n"); }
    $reg = json_decode((string) file_get_contents($REG), true);
    $known = isset($reg['entries']) ? $reg['entries'] : array();
    $new = array_values(array_diff($hits, array_keys($known)));
    $gone = array_values(array_diff(array_keys($known), $hits));
    printf("  السجل: %d مدخلًا · **جديدٌ خارجَه: %d** · حُوِّل فخرج منه: %d\n",
           count($known), count($new), count($gone));
    foreach (array_slice($new, 0, 10) as $f) { echo "     ✘ جديدٌ غيرُ مسجَّل: {$f}\n"; }
    $named = 0;
    foreach ($known as $e) { if (($e['owner'] ?? '') !== 'NEEDS_GOVERNING_SOURCE') { $named++; } }
    printf("  مالكٌ مسمّى: %d · بلا مالكٍ مشتقٍّ: %d\n", $named, count($known) - $named);
    exit(empty($new) ? 0 : 1);
}

/* ── البناءُ: مدخلٌ لكلِّ ملفٍّ بسببٍ ومالكٍ وتاريخِ مراجعة ─────────────────── */
$REASON = 'موروثٌ قبلَ البوابة — والعقدُ الحاكمُ (docs/TENANT_GATE_CONTRACT_ar.md §1، '
        . 'إحالةً إلى R02 §3.4) ينصُّ أن الشاشاتِ القديمةَ تبقى على mysqli الخامِّ '
        . 'حتى دورِ هجرتِها. فالاستثناءُ مُعلَنٌ لا مُغفَل، والعزلُ فيه بانضباطِ '
        . 'المطوِّرِ لا بالبوابة — وهذا هو الخطرُ المسجَّل.';
$REVIEW = 'NEEDS_GOVERNING_SOURCE';

/* ── اشتقاقٌ ثانٍ: **إجماعُ الإخوةِ المسجَّلين في القوائم** ─────────────────
 * ◆ أوّلُ بناءٍ ترك 389 ملفًّا بلا مالك — والمطلبُ يقول «لكلٍّ سببٌ **ومالكٌ**».
 *   وأكثرُها شاشاتٌ في مجلَّدِ إدارةٍ لها إخوةٌ **مسجَّلون في القوائمِ بمالكٍ
 *   واحدٍ لا ثانيَ له**. فنسبتُها إلى ذلك المالكِ اشتقاقٌ من مصدرٍ قائمٍ لا
 *   تخمين — **وشرطُه الإجماع**: مجلَّدٌ يختلف مالكو إخوتِه فيه لا يُنسَب.
 * ◆ وطبقةُ `app/` و`includes/` بنيةٌ لا إدارةٌ — فتبقى NEEDS_GOVERNING_SOURCE
 *   بحقِّها لا بعجزٍ عن القياس. */
$dirVotes = array();
foreach ($owners as $route => $own) {
    $d = strpos($route, '/') !== false ? substr($route, 0, strrpos($route, '/')) : '';
    if ($d === '') { continue; }
    $dirVotes[$d][$own] = true;
}
$dirOwner = array();
foreach ($dirVotes as $d => $set) {
    if (count($set) === 1) { $dirOwner[$d] = array_keys($set)[0]; }
}
printf("  مجلَّداتٌ بمالكٍ **مُجمَعٍ عليه**: %d من %d\n\n", count($dirOwner), count($dirVotes));
$entries = array(); $named = 0;
foreach ($hits as $rel) {
    $key = strtolower($rel);
    $dir = strpos($key, '/') !== false ? substr($key, 0, strrpos($key, '/')) : '';
    if (isset($owners[$key])) {
        $owner = $owners[$key];
        $src   = 'nav_items.route ⇒ modules.owner_role_id ⇒ roles.name';
    } elseif ($dir !== '' && isset($dirOwner[$dir])) {
        $owner = $dirOwner[$dir];
        $src   = 'إجماعُ مالكي إخوتِه المسجَّلين في القوائم — مجلَّد ' . $dir;
    } else {
        $owner = 'NEEDS_GOVERNING_SOURCE';
        $src   = 'لا مسارَ في القوائمِ ولا إجماعَ في مجلَّدِه — لا يُنسَب لدورٍ بالتخمين';
    }
    if ($owner !== 'NEEDS_GOVERNING_SOURCE') { $named++; }
    $entries[$rel] = array(
        'owner'       => $owner,
        'owner_source'=> $src,
        'reason'      => $REASON,
        'review_date' => $REVIEW,
    );
}

$doc = array(
    'requirement'   => 'FR-SEC-006 · GAP-29',
    'governing'     => 'INJ-FRD-REM-01 · برنامج الإصلاح §الاستعلام الخام · '
                     . 'docs/TENANT_GATE_CONTRACT_ar.md §1',
    'built_from'    => 'tools/lib/raw_query_scan.php — الماسحُ نفسُه الذي تقرأ به السقّاطة',
    'review_note'   => 'دوريةُ المراجعةِ لا مصدرَ حاكمًا لها في الحزمةِ ولا في وثائقِ '
                     . 'المواءمة — و§ثالثًا يمنع اختراعَ مدة. فالحقلُ NEEDS_GOVERNING_SOURCE.',
    'count'         => count($entries),
    'owner_named'   => $named,
    'owner_unknown' => count($entries) - $named,
    'entries'       => $entries,
);
file_put_contents($REG, json_encode($doc,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

/* ◆ **قراءةٌ ثانيةٌ من القرصِ إلزام** — الكتابةُ التي لا تُقرأ بعدَها مزعومة */
$back = json_decode((string) file_get_contents($REG), true);
if (!is_array($back) || (int) ($back['count'] ?? -1) !== count($entries)) {
    fwrite(STDERR, "⛔ كتابةٌ مزعومة — السجلُّ لم يُقرأ بعدَ كتابتِه\n");
    exit(1);
}
printf("  ✔ كُتب السجل: **%d مدخلًا** · مالكٌ مسمّى: %d · بلا مالكٍ مشتقٍّ: %d\n",
       count($entries), $named, count($entries) - $named);
printf("  ✔ وأُعيدت قراءتُه من القرصِ فطابق (%d)\n", (int) $back['count']);
echo "  ◆ تاريخُ المراجعة: NEEDS_GOVERNING_SOURCE — لا دوريةَ في أيِّ مصدرٍ حاكم\n";
echo "  ◆ الملف: docs/raw_query_exceptions.json\n";
exit(0);
