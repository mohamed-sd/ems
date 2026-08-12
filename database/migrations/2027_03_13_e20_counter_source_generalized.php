<?php
/**
 * 2027_03_13 — E-20: تعميمُ عدّادِ القوائم فعلًا (العمودُ كان يُكتَب ولا يُقرأ)
 * ═══════════════════════════════════════════════════════════════════════════
 * **العطبُ المقيسُ**: `nav_items.counter_source` كان **بيانًا ميتًا في مسارِ
 * العرض**. `includes/unified_nav.php:72` يجلبه في الاستعلام، لكن التصييرَ يبني
 * الشارةَ من `$badges[$it['route']]` — مصفوفةٍ تأتي من خارجٍ بثلاثةِ مساراتٍ
 * **مثبَّتةٍ نصًّا** في `ems_finreq_nav_badges`. فخمسةُ صفوفٍ كانت تحمل مفتاحًا
 * صحيحًا **ولا شارةَ تظهر لأحدٍ منها**، وحكمُ E-20 يبدو مُسلَّمًا وهو غيرُ مُسلَّم:
 * العمودُ موجودٌ ومملوءٌ جزئيًّا، والوظيفةُ غائبة.
 *
 * وقد صار العمودُ **يُقرأ** الآن (`ems_nav_counter_badges` + `ems_nav_all_badges`
 * موصولةً في `insidebar.php`)، فهذه الهجرةُ **تملأه بمفاتيحَ لكلِّ مسارٍ يعنيها**
 * — وبها تظهر الشاراتُ للمستخدمين فعلًا.
 *
 * ◆ ولا يُوضَع مفتاحٌ إلا حيث **يصدُق معناه على المسار**:
 *   · `FinRequests/my_requests.php` **مستثنًى بقصد**: عدٌّ عامٌّ على «طلباتي»
 *     يكذب (الشارةُ المثبَّتةُ هناك تعدُّ **المُعادَ إلى صاحبِه** وهي أصدق).
 * ◆ ولا يُمَسُّ صفٌّ يحمل مفتاحًا سلفًا — إضافةٌ لا إعادةُ كتابة.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
$one = function ($sql) use ($db) { $r = $db->query($sql); return $r ? $r->fetch_row()[0] : null; };

/** مسار ⇒ مفتاحُ عدٍّ — كلُّ مفتاحٍ له فرعٌ في `ems_nav_counter_dictionary()` */
$MAP = array(
    'Tickets/dept_inbox.php'          => 'dept_tickets_late',
    'Tickets/tickets_list.php'        => 'tickets_open',
    'FinRequests/dept_inbox.php'      => 'finreq_dept_inbox',
    'FinRequests/finance_gateway.php' => 'finreq_pending',
    'Contracts/claims.php'            => 'claims_unbilled',
    'Approvals/hours_approval.php'    => 'hours_approval',
    'Operations/operations_room.php'  => 'units_pending_approval',
);

/* ── ① الحالُ قبل ─────────────────────────────────────────────────────────── */
$before = (int) $one("SELECT COUNT(*) FROM nav_items
                       WHERE counter_source IS NOT NULL AND counter_source <> ''");
echo "── ① صفوفٌ تحمل مفتاحَ عدٍّ قبل: {$before}\n";

/* ── ② التحقّقُ من أن القاموسَ يعرف كلَّ مفتاحٍ سنكتبه ────────────────────── */
$dictFile = dirname(__DIR__, 2) . '/includes/finreq_badges.php';
$dictSrc  = is_file($dictFile) ? (string) file_get_contents($dictFile) : '';
$unknown  = array();
foreach ($MAP as $route => $key) {
    if (strpos($dictSrc, "'" . $key . "'") === false) { $unknown[] = $key; }
}
if ($unknown) {
    fwrite(STDERR, "مفاتيحُ لا فرعَ لها في القاموس: " . implode(' · ', array_unique($unknown))
        . "\n   فلا تُكتب — شارةٌ بمفتاحٍ لا يُحسَب بيانٌ ميتٌ آخر.\n");
    exit(1);
}
echo "── ② كلُّ مفتاحٍ له فرعٌ في `ems_nav_counter_dictionary()` ✔\n";

/* ── ③ الكتابةُ — إضافةٌ لا إعادةُ كتابة ──────────────────────────────────── */
$touched = 0;
foreach ($MAP as $route => $key) {
    $r = $db->real_escape_string($route);
    $k = $db->real_escape_string($key);
    $live = (int) $one("SELECT COUNT(*) FROM nav_items WHERE route = '{$r}' AND active = 1");
    if ($live === 0) { echo "     ○ {$route} — لا صفَّ حيًّا، يُتخطّى\n"; continue; }
    $ok = $db->query("UPDATE nav_items SET counter_source = '{$k}'
                       WHERE route = '{$r}' AND active = 1
                         AND (counter_source IS NULL OR counter_source = '')");
    if ($ok === false) { fwrite(STDERR, "     ✘ {$route}: " . $db->error . "\n"); continue; }
    $n = $db->affected_rows;
    $touched += $n;
    printf("     ✔ %-34s ⇐ %-24s (%d صفًّا من %d حيًّا)\n", $route, $key, $n, $live);
}

/* ── ④ الشاهد ─────────────────────────────────────────────────────────────── */
$after = (int) $one("SELECT COUNT(*) FROM nav_items
                      WHERE counter_source IS NOT NULL AND counter_source <> ''");
$keys  = (int) $one("SELECT COUNT(DISTINCT counter_source) FROM nav_items
                      WHERE counter_source IS NOT NULL AND counter_source <> ''");
echo "── ④ بعد: {$after} صفًّا · بـ{$keys} مفتاحًا مختلفًا (زِيد {$touched})\n";
if ($after < 20) {
    fwrite(STDERR, "الحصيلةُ {$after} < 20 — حكمُ E-20 لا يُغلق بهذا\n");
    exit(1);
}
echo "\n✅ E-20: العمودُ يُقرأ ويُملأ — {$after} صفًّا بـ{$keys} مفتاحًا.\n";
exit(0);
