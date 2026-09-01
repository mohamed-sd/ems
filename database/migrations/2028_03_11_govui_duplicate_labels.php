<?php
/**
 * 2028_03_11_govui_duplicate_labels.php — اسمٌ واحدٌ لسطحَين: حكمٌ لكلِّ زوج
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: update:nav_canonical(canonical_ar,status,merge_into)
 *                     + repair01_screen_registry + nav_items + modules + log:govui_label_log
 *
 * ◆ **البندُ `CL-PAT-DUPLABEL` في السجلِّ الجامع** يطلب لكلِّ زوجٍ حكمًا:
 *   دمجٌ · أو إعادةُ تسميةٍ معتمَدة · أو إعلانُ منظورَين شرعيَّين.
 *   وقِيس الآن **خمسةُ أزواجٍ** باسمٍ معتمَدٍ واحدٍ على مسارَين.
 *
 * ◆ **وثلاثةٌ منها توأمانِ حيّان في سايدبارٍ واحد** — والمستخدمُ لا يميّزهما:
 *   · الدور 26: «عمليات التمويل» مرّتَين (`operation_profile` · `financing_board`)
 *   · الدور 15: «فصل الواجبات المتعارضة» مرّتَين (`sod_conflicts` · `sec_governance`)
 *   · الدور 9 : «سجل العقود الموحد» مرّتَين (`exec_contract_registry` · `contract_registry`)
 *
 * ◆ **والقاعدةُ الحاسمةُ مقيسةٌ لا مُخترَعة**: في كلِّ زوجٍ **مسارٌ واحدٌ يحمل
 *   هدفَ الدليلِ والآخرُ بلا هدف** — فالاسمُ الحاكمُ لصاحبِ الهدف، والآخرُ
 *   يأخذ اسمًا مميِّزًا **من ترويسةِ ملفِّه نفسِه** (مرجعُه الحاكمُ مكتوبٌ فيها)،
 *   ⛔ ولا اسمَ من عندي.
 *
 * ◆ **والخامسُ ليس شاشةً أصلًا**: `FinRequests/my_requests.php` يعلن في ترويستِه
 *   *«ويبقى هذا الملفُّ مُحوِّلًا لا شاشة»* ودمجَه مؤرَّخٌ (2026-08-21)،
 *   وبنودُ قائمتِه **صفر** — فيُقيَّد `MERGED` كما قُيِّد `Tickets/dept_inbox.php`
 *   سابقًا، لا يُعاد تسميتُه. **والسابقةُ في المخزنِ نفسِه لا في اجتهادي.**
 *
 * التشغيل: php database/migrations/2028_03_11_govui_duplicate_labels.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$log = $conn->prepare("INSERT INTO govui_label_log
    (target_id, store, store_key, old_label, new_label, source_ref, reason) VALUES (?,?,?,?,?,?,?)");
if (!$log) { exit("⛔ prepare: {$conn->error}\n"); }
$put = function ($tid, $store, $key, $old, $new, $src, $why) use ($log) {
    $log->bind_param('sssssss', $tid, $store, $key, $old, $new, $src, $why);
    $log->execute();
};

/* route => [الاسمُ الجديد, سندُه من ترويسةِ الملفّ, صاحبُ الاسمِ الحاكم] */
$RENAME = array(
    'Financing/financing_board.php' => array('لوحة إدارة التمويل',
        'ترويسةُ الملفِّ: «Financing/financing_board.php — لوحة إدارة التمويل (FIN-26 · الشاشة 214)»',
        'Financing/operation_profile.php ⇐ NT-DEP-03-010 «عمليات التمويل»'),
    'admin/sec_governance.php' => array('مركز حوكمة الصلاحيات',
        'ترويسةُ الملفِّ: «مركز حوكمة الصلاحيات بمجموعاته الثماني (update0004 · SEC-26 · SEC-01 §10.1)»',
        'Governance/sod_conflicts.php ⇐ NT-DEP-08-016 «فصل الواجبات المتعارضة»'),
    'Workforce/contract_registry.php' => array('سجل العقود',
        'ترويسةُ الملفِّ: «سجلُّ العقود الموحّد (H-08-① · CON-01 §6 «سجل العقود»)» — والاسمُ المختصرُ مرجعُها الحاكم',
        'Portal/exec_contract_registry.php ⇐ NT-EX-CEO-012 «سجل العقود الموحَّد»'),
    'Tickets/my_tickets.php' => array('بلاغاتي من سجل البلاغات',
        'ترويسةُ الملفِّ: «قارئةٌ محضةٌ — لا فعلَ كاتبًا فيها … ولها جدولٌ حيٌّ يسندها (tickets)»',
        'Portal/my_reports.php ⇐ NT-WS-MY-005 «بلاغاتي»'),
);
/* مُحوِّلٌ لا شاشة — يُقيَّد دمجًا لا يُعاد تسميتُه */
$MERGE = array(
    'FinRequests/my_requests.php' => array('FinRequests/request_form.php',
        'ترويسةُ الملفِّ: «دُمجت طلباتي المالية في request_form.php (2026-08-21) … ويبقى هذا الملفُّ مُحوِّلًا لا شاشة» · وبنودُ قائمتِه صفر'),
);

$nR = 0; $nN = 0; $nM = 0;
foreach ($RENAME as $route => $r) {
    list($new, $src, $owner) = $r;
    $st = $conn->prepare("SELECT route, canonical_ar, screen_id FROM nav_canonical WHERE LOWER(route) = LOWER(?)");
    $st->bind_param('s', $route); $st->execute();
    $cur = $st->get_result()->fetch_assoc(); $st->close();
    if (!$cur) { echo "  ⚠ لا صفَّ معياريًّا لـ{$route}\n"; continue; }
    if ($cur['canonical_ar'] === $new) { echo "  · {$route} باسمِه سلفًا\n"; continue; }
    $why = 'CL-PAT-DUPLABEL — اسمٌ معتمَدٌ واحدٌ على مسارَين · والاسمُ الحاكمُ لصاحبِ هدفِ الدليل: ' . $owner;
    $why = mb_substr($why, 0, 185);

    $st = $conn->prepare("UPDATE nav_canonical SET canonical_ar = ? WHERE LOWER(route) = LOWER(?)");
    $st->bind_param('ss', $new, $route);
    if (!$st->execute()) { exit("⛔ canon {$route}: {$conn->error}\n"); }
    $st->close();
    $put('DUP-' . $route, 'nav_canonical.canonical_ar', $route, $cur['canonical_ar'], $new, $src, $why);
    $nR++;

    if ((string) $cur['screen_id'] !== '') {
        $st = $conn->prepare("SELECT canonical_label_ar FROM repair01_screen_registry WHERE screen_id = ?");
        $st->bind_param('s', $cur['screen_id']); $st->execute();
        $g = $st->get_result()->fetch_assoc(); $st->close();
        if ($g && $g['canonical_label_ar'] !== $new) {
            $st = $conn->prepare("UPDATE repair01_screen_registry SET canonical_label_ar = ? WHERE screen_id = ?");
            $st->bind_param('ss', $new, $cur['screen_id']);
            $st->execute(); $st->close();
            $put('DUP-' . $route, 'repair01_screen_registry.canonical_label_ar', $cur['screen_id'],
                $g['canonical_label_ar'], $new, $src, $why);
        }
    }
    $navLabel = mb_substr($new, 0, 64);
    $st = $conn->prepare("SELECT id, role_id, label_ar FROM nav_items WHERE LOWER(route) = LOWER(?)");
    $st->bind_param('s', $route); $st->execute();
    $rs = $st->get_result(); $rows = array();
    while ($x = $rs->fetch_assoc()) { $rows[] = $x; }
    $st->close();
    foreach ($rows as $x) {
        if ((string) $x['label_ar'] === $navLabel) { continue; }
        $st = $conn->prepare("UPDATE nav_items SET label_ar = ? WHERE id = ?");
        $st->bind_param('si', $navLabel, $x['id']);
        $st->execute(); $st->close();
        $put('DUP-' . $route, 'nav_items.label_ar', 'id=' . $x['id'] . '·role=' . $x['role_id'],
            $x['label_ar'], $navLabel, $src, $why);
        $nN++;
    }
    $st = $conn->prepare("SELECT id, name FROM modules WHERE LOWER(code) = LOWER(?)");
    $st->bind_param('s', $route); $st->execute();
    $g = $st->get_result()->fetch_assoc(); $st->close();
    if ($g && $g['name'] !== $new) {
        $nm = mb_substr($new, 0, 100);
        $st = $conn->prepare("UPDATE modules SET name = ? WHERE id = ?");
        $st->bind_param('si', $nm, $g['id']);
        $st->execute(); $st->close();
        $put('DUP-' . $route, 'modules.name', 'id=' . $g['id'], $g['name'], $nm, $src, $why);
    }
    printf("  ✔ %-42s «%s» ⇐ «%s»\n", $route, $cur['canonical_ar'], $new);
}

foreach ($MERGE as $route => $m) {
    list($into, $src) = $m;
    $st = $conn->prepare("SELECT status, canonical_ar, merge_into FROM nav_canonical WHERE LOWER(route) = LOWER(?)");
    $st->bind_param('s', $route); $st->execute();
    $cur = $st->get_result()->fetch_assoc(); $st->close();
    if (!$cur || $cur['status'] === 'MERGED') { echo "  · {$route} مقيَّدٌ دمجًا سلفًا\n"; continue; }
    $note = 'يُدمج في ' . $into . ' — والملفُّ يبقى مُحوِّلًا';
    $st = $conn->prepare("UPDATE nav_canonical SET status = 'MERGED', merge_into = ? WHERE LOWER(route) = LOWER(?)");
    $st->bind_param('ss', $note, $route);
    if (!$st->execute()) { exit("⛔ merge {$route}: {$conn->error}\n"); }
    $st->close();
    $put('DUP-' . $route, 'nav_canonical.status', $route, (string) $cur['status'], 'MERGED', $src,
        'CL-PAT-DUPLABEL — مُحوِّلٌ لا شاشة: يُقيَّد دمجًا كسابقةِ Tickets/dept_inbox.php');
    printf("  ✔ %-42s [%s] ⇐ [MERGED ⇒ %s]\n", $route, $cur['status'], $into);
    $nM++;
}
printf("أسماءٌ صُحِّحت %d · بنودُ قائمةٍ %d · دمجٌ %d\n", $nR, $nN, $nM);
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
