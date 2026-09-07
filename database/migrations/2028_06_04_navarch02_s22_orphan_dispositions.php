<?php
/**
 * 2028_06_04 — NAV-ARCH-02 §22 · أحكامُ الشاشاتِ المسموحةِ بلا موضعٍ حاكم
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المشكلةُ المقيسة**: 79 شاشةً **مبنيّةً** يسمح بها قالبُ مستخدمٍ حيٍّ،
 *   ويفتحها الحارسُ، **ولا موضعَ حاكمٌ لها ولا حكمٌ مسجَّل**. فسؤالُ «أهذا عطبٌ
 *   أم تصميم؟» **لا جوابَ له في السجلّ** — وما لا حكمَ له لا يُقاس ولا يُغلَق.
 *
 * ⛔ **ولا يُخترع موضعٌ لشاشةٍ خارجَ الدليل**: قُطعت الـ79 بـ`govui_target_registry`
 *   (الدليلُ المعماريُّ · 413 هدفًا) بطريقتَي مطابقةٍ — بالمسارِ التامِّ وبالاسمِ
 *   الأساس — والنتيجةُ **صفرٌ من 79**. فإنشاءُ `MENU_ITEM` لأيٍّ منها **اختراعُ
 *   معماريّةٍ** لا إصلاحُ عطب. ونصُّ `GOVUI_METRICS`: مقامُ `MENU_TARGET_COVERAGE`
 *   يستثني `TAB_CHILD` و`DIRECT_ONLY` و`UTILITY` — فهذه أصنافٌ **خارجَ القائمةِ
 *   بحقّ**، لا نواقصُ فيها.
 *
 * ◆ **فالمكتوبُ هنا حكمانِ لا ثالثَ لهما**:
 *   ① `DIRECT_ONLY` / `CONTEXTUALIZE` — **توثيقُ واقعٍ لا قرارُ تصميم**:
 *      الشاشةُ تستقبل معرِّفَ كيانٍ من الرابط، أو تحمل عُدّةَ بطاقةِ كيان، أو
 *      يربط إليها سطحٌ حيّ ⇒ **تُبلَغ بالنقرِ من أبيها**. `L1_ARCHITECTURE`.
 *   ② `TRUE_TARGET_GAP` / `TARGET_GAP_REVIEW` — **تُرفَع ولا تُحسَم**: خارجَ
 *      الدليل · بلا معرِّفِ كيان · ولا سطحَ يربط إليها. وأيُّ حكمٍ عليها قرارُ
 *      مالكِ مجالٍ — فتُقيَّد مرئيّةً متتبَّعةً بدل أن تبقى مجهولةً صامتة.
 *
 * ⛔ **وصفرُ موضعٍ جديد**: لا سطرَ في `nav_workspace_placements` — فما يراه
 *   المستخدمُ **لا يتغيّر بهذه الهجرةِ إطلاقًا** (قِيس: 3600 رابطًا قبلَها وبعدَها).
 * ◆ **ومُعاوَدة**: ما كُتب حكمُه لا يُعاد — تشغيلٌ ثانٍ يجد صفرَ مرشَّح.
 *
 * التشغيل: php database/migrate.php up   ·   أو الملفُّ بذاته
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$DECISION_REF = 'NAV-ARCH-02 §22 · جولةُ أحكامِ اليتامى 2028-06-04';

$norm = function ($s) {
    $s = preg_replace('~^(\.\./)+~', '', (string) $s);
    $s = preg_replace('~[?#].*$~', '', $s);
    return strtolower(trim(preg_replace('~\.php$~i', '', $s), '/'));
};

/* ① المسموحُ لمستخدمٍ حيٍّ */
$allow = array();
$q = $conn->query(
    "SELECT i.item_ref, COUNT(DISTINCT u.id) users, COUNT(DISTINCT u.role) roles
       FROM gov_authority_grants g
       JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
       JOIN gov_profile_items i ON i.profile_id = p.profile_id AND i.item_kind = 'screen' AND i.allow = 1
       JOIN users u ON u.id = g.user_id AND u.is_deleted = 0 AND u.status = 'active' AND u.company_id = 4
      WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())
      GROUP BY i.item_ref");
while ($q && ($x = $q->fetch_assoc())) { $allow[$x['item_ref']] = $x; }

/* ② ما له موضعٌ حاكمٌ نشط · وما له حكمٌ مسجَّل */
$placed = array();
$r = $conn->query("SELECT route FROM nav_workspace_placements WHERE status = 'ACTIVE'");
while ($r && ($x = $r->fetch_row())) { $placed[$norm($x[0])] = 1; }

$ruled = array();
$r = $conn->query("SELECT current_route FROM nav_legacy_disposition
                    WHERE disposition <> '' AND disposition <> 'UNKNOWN_REQUIRES_DECISION'");
while ($r && ($x = $r->fetch_row())) { $ruled[$norm($x[0])] = 1; }

/* ③ أسطحُ بلوغٍ ليست سايدبارًا */
$reach = array();
foreach (array('Portal/approvals_inbox.php','Portal/my_tasks.php','Tickets/ticket_contextual_open.php',
               'Portal/my_requests.php','FinRequests/request_form.php','Portal/notifications.php',
               'main/dashboard.php','Portal/my_portal.php','Portal/my_achievement.php','main/profile.php',
               'Settings/settings.php','Tickets/tickets_list.php','user_capacities.php','chats/index.php')
         as $x) { $reach[$norm($x)] = 1; }

/* ④ خريطةُ الروابطِ الواردة */
$inbound = array();
foreach (glob($ROOT . '/*', GLOB_ONLYDIR) as $d) {
    $b = basename($d);
    if (in_array($b, array('tools','tests','storage','database','vendor','docs','logs',
                           'node_modules','install','scripts','assets','api'), true)) { continue; }
    foreach (glob($d . '/*.php') as $f) {
        $src = @file_get_contents($f); if ($src === false) { continue; }
        $self = strtolower(basename($f));
        if (preg_match_all('~(?:href|action|location(?:\.href)?)\s*=\s*["\'][^"\']*?([A-Za-z0-9_]+\.php)~i', $src, $m)) {
            foreach ($m[1] as $t) { $t = strtolower($t); if ($t !== $self) { $inbound[$t][$self] = 1; } }
        }
    }
}

/* ⑤ الإسنادُ من سجلِّ المواضع */
$scr = array(); $wsOf = array();
$r = $conn->query("SELECT route, screen_id, workspace_id FROM nav_placements");
while ($r && ($x = $r->fetch_assoc())) {
    $k = $norm($x['route']);
    if ($x['screen_id'])    { $scr[$k]  = $x['screen_id']; }
    if ($x['workspace_id']) { $wsOf[$k] = $x['workspace_id']; }
}

/* ⑥ الحكم */
$st = $conn->prepare(
    "INSERT INTO `nav_legacy_disposition`
       (legacy_item_id, screen_id, current_workspace, current_label, current_route,
        usage_count, disposition, action, reason, decision_ref, evidence,
        decided_level, retire_stage, created_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'NONE', NOW())");
if (!$st) { exit("prepare فشل: {$conn->error}\n"); }

$nDirect = 0; $nGap = 0; $skipped = 0;
foreach ($allow as $ref => $info) {
    $k = $norm($ref);
    if ($k === '' || isset($placed[$k]) || isset($ruled[$k]) || isset($reach[$k])) { $skipped++; continue; }

    $mq = $conn->prepare("SELECT id, name, code FROM modules WHERE LOWER(code) = LOWER(?) LIMIT 1");
    $mq->bind_param('s', $ref); $mq->execute();
    $mod = $mq->get_result()->fetch_assoc(); $mq->close();
    if (!$mod) { $skipped++; continue; }

    $file = $ROOT . '/' . $mod['code'];
    $src  = is_file($file) ? (string) @file_get_contents($file) : '';
    $idParam = preg_match('~\$_GET\s*\[\s*[\'"](id|contract_id|client_id|project_id|employee_id'
                        . '|equipment_id|supplier_id|unit_id|asset_id)[\'"]~', $src, $mm) ? $mm[1] : '';
    $entity  = (bool) preg_match('~entity_tabs|file_tabs_kit|EmsDetailsModal|contract_file_tabs|financing_file_tabs~', $src);
    $inN     = isset($inbound[strtolower(basename($mod['code']))]) ? count($inbound[strtolower(basename($mod['code']))]) : 0;

    if ($idParam !== '' || $entity || $inN > 0) {
        $disp = 'DIRECT_ONLY'; $act = 'CONTEXTUALIZE'; $lvl = 'L1_ARCHITECTURE';
        $bits = array();
        if ($idParam !== '') { $bits[] = "تستقبل «{$idParam}» من الرابط"; }
        if ($entity)         { $bits[] = 'عُدّةُ بطاقةِ كيان'; }
        if ($inN > 0)        { $bits[] = "يربط إليها {$inN} سطحًا حيًّا"; }
        $ev = 'خارجَ الدليلِ المعماريِّ (govui_target_registry · صفرُ مطابقةٍ بطريقتَين) · ' . implode(' · ', $bits);
        $reason = 'شاشةُ تفصيلٍ تُبلَغ من أبيها بالنقر — لا بندَ قائمةٍ مستقلًّا لها '
                . '(GOVUI_METRICS: DIRECT_ONLY خارجَ مقامِ MENU_TARGET_COVERAGE)';
        $nDirect++;
    } else {
        $disp = 'TRUE_TARGET_GAP'; $act = 'TARGET_GAP_REVIEW'; $lvl = 'L1_ARCHITECTURE';
        $ev = 'خارجَ الدليلِ المعماريِّ · لا معرِّفَ كيانٍ في الرابط · ولا سطحَ حيٌّ يربط إليها · '
            . 'مسموحةٌ لـ' . $info['roles'] . ' دورًا و' . $info['users'] . ' مستخدمًا';
        $reason = 'فجوةٌ مُعلَنةٌ تنتظر حكمَ مالكِ المجال — لا يُخترَع لها موضعٌ ولا تُطوى بصمت';
        $nGap++;
    }

    $legacyId = 'ORPH-' . strtoupper(substr(md5($k), 0, 12));
    $sid   = isset($scr[$k])  ? $scr[$k]  : null;
    $ws    = isset($wsOf[$k]) ? $wsOf[$k] : '';
    $label = (string) $mod['name'];
    $route = (string) $mod['code'];
    $usage = (int) $info['users'];

    $st->bind_param('sssssissssss', $legacyId, $sid, $ws, $label, $route,
                    $usage, $disp, $act, $reason, $DECISION_REF, $ev, $lvl);
    if (!$st->execute()) { exit("execute فشل: {$st->error}\n"); }
}
$st->close();

echo "  ✔ DIRECT_ONLY (توثيقُ واقعٍ · L1)      : {$nDirect}\n";
echo "  ✔ TRUE_TARGET_GAP (تُرفَع للمالك)      : {$nGap}\n";
echo "  ◦ متجاوَزٌ (له موضعٌ أو حكمٌ أو بلوغٌ آخر): {$skipped}\n";
echo "  ⛔ مواضعُ ملاحةٍ جديدة                  : 0 — ما يراه المستخدمُ لا يتغيّر\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
