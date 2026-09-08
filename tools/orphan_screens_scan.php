<?php
/**
 * tools/orphan_screens_scan.php — مسحُ الشاشاتِ اليتيمةِ وتحديثُ سجلِّها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السؤالُ المقيس**: أيُّ شاشةٍ مبنيّةٍ **لا يبلغها أحدٌ بأيِّ طريق**؟
 *   الطرقُ الثلاثةُ التي تُفحص، وما لا يمرُّ بواحدةٍ منها فهو يتيم:
 *     ① **سايدبارُ دورٍ حيّ** — يُقاس بالمُصيِّرِ الحاكمِ نفسِه
 *        (`navarch_render` لكلِّ دورٍ عليه مستخدمٌ حيّ) لا بجدولٍ يُقرأ.
 *     ② **رابطٌ من شاشةٍ أخرى** — `href` · `action` · `location` · `Location:`
 *     ③ **مكتبةٌ تُضمَّن** — `require`/`include` (‏وليست شاشةً أصلًا)
 *
 * ⛔ **وثلاثُ تنقياتٍ تعلَّمتها بالقياس** — بدونها يتضخَّم الرقمُ كذبًا:
 *   · المُعالِجاتُ والمولِّدات (`save_` `delete_` `update_` `add_` `ajax_`
 *     `export_` `print_` `cron_`) **ليست شاشات**.
 *   · صفحاتُ التفصيلِ تُفتح **بالنقرِ** لا من السايدبار (`*_details.php`
 *     و`client_profile` …) — فتُلتقط بالفحصِ ②.
 *   · ملفّاتُ `*_helpers.php` **مكتباتٌ** تُضمَّن — تُلتقط بالفحصِ ③.
 *
 * ◆ **والقرارُ لا يُدهَس**: `decision` و`decided_by` و`decided_at` و`note`
 *   تبقى كما كتبها المالك؛ ويُحدَّث القياسُ وحدَه (`last_seen` · الحجم ·
 *   المالك · الوحدة). وشاشةٌ خرجت من اليُتم — أي وُصِلت — **تُوسَم `WIRED`**
 *   ولا تُحذف، فيبقى أثرُ أنّها عولجت.
 *
 * التشغيل: php tools/orphan_screens_scan.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
@session_start();

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/navarch_renderer.php';

$LOG = $ROOT . '/logs/orphan_scan.txt';
$buf = "═══ مسحُ الشاشاتِ اليتيمة · " . date('Y-m-d H:i') . " ═══\n";

$DIRS = array('ActivityLogs','Approvals','Audit','Clients','Contracts','Employees','Equipments',
 'FinRequests','Finance','Financing','Fleet','Governance','Maintenance','Operations','Opportunities',
 'Oprators','Portal','Procurement','Projects','Reports','Risk','Settings','Suppliers','Tickets',
 'Timesheet','Transport','Workforce','main','movement','chats','emsreports');

/* ⛔ **وما ليس شاشةً لا يُعَدُّ يتيمًا**: المُعالِجاتُ ونقاطُ النهايةِ تُنادى
     من جافاسكربت بـ`fetch`/`ajax` فلا يلتقطها فحصُ `href` — فتظهر يتيمةً كذبًا.
     قِيس: 53 من 320 كانت من هذا الصنف (`*_handler` · `*_api` · `get_*` …). */
$SKIP_RX = '~^(cron_|save_|delete_|update_|add_|ajax_|export_|print_|get_|fetch_|check_|upload_|download_|api_|mark_|send_|setup_|install_|seed_|migrate_)'
         . '|(_handler|_api|_actions|_endpoint|_ajax|_json|_export|_print)\.php$~i';

/* ── ① ما يبلغه سايدبارُ دورٍ حيّ — بالمُصيِّرِ الحاكمِ لا بجدول ─────────── */
$side = array();
$r = $conn->query("SELECT DISTINCT role FROM users WHERE is_deleted=0 AND status='active'");
$roles = 0;
while ($r && ($x = $r->fetch_row())) {
    $rid = (int) $x[0]; $ws = navarch_role_workspace($conn, $rid);
    if (!$ws) { continue; }
    /* ⛔ **المقياسُ لا يقيس نفسَه**: مساحةُ المراجعةِ تعرض اليتامى، فعَدُّها
         طريقَ وصولٍ يجعل كلَّ يتيمٍ «مبلوغًا» ويُصفِّر العدَّ دائريًّا. */
    if ($ws === 'WS-ORPHAN') { continue; }
    $roles++;
    $t = navarch_render($conn, $ws, $rid, array('include_shell' => true));
    foreach ($t['groups'] as $g) { foreach ($g['items'] as $i) { $side[$i['route']] = 1; } }
    foreach ($t['shell'] as $s)    { $side[$s['route']] = 1; }
    foreach ($t['personal'] as $s) { $side[$s['route']] = 1; }
}
$buf .= "  أدوارٌ مُصيَّرة = {$roles} · مساراتٌ يبلغها السايدبار = " . count($side) . "\n";

/* ── ②③ الروابطُ والتضمين ────────────────────────────────────────────────── */
$linked = array(); $inc = array(); $files = array();
foreach ($DIRS as $d) { $files = array_merge($files, glob($ROOT . "/{$d}/*.php")); }
$files = array_merge($files, glob($ROOT . '/*.php'), glob($ROOT . '/includes/*.php'),
                             glob($ROOT . '/app/*/*.php'));
foreach ($files as $f) {
    $s = @file_get_contents($f); if ($s === false) { continue; }
    if (preg_match_all('~(?:href|action|location(?:\.href)?|Location:)\s*[=:]?\s*[\'"]?[^\'"\s>]*?([a-zA-Z0-9_]+\.php)~', $s, $m)) {
        foreach ($m[1] as $t) { $linked[strtolower($t)] = 1; }
    }
    if (preg_match_all('~(?:require|include)(?:_once)?\s*\(?\s*[^;]*?([a-zA-Z0-9_]+\.php)~', $s, $m)) {
        foreach ($m[1] as $t) { $inc[strtolower($t)] = 1; }
    }
    /* ④ نقطةُ نهايةٍ تُنادى من جافاسكربت — `fetch('x.php')` · `url: 'x.php'` */
    if (preg_match_all('~(?:fetch|ajax|post|get|open|url)\s*[\(:]\s*[\'"`][^\'"`]*?([a-zA-Z0-9_]+\.php)~i', $s, $m)) {
        foreach ($m[1] as $t) { $linked[strtolower($t)] = 1; }
    }
}

/* ── الفرزُ والكتابة ────────────────────────────────────────────────────── */
$seenNorm = array(); $ins = 0; $upd = 0; $screens = 0;
$ownQ = $conn->prepare("SELECT owner_dept, canonical_ar FROM nav_canonical
                         WHERE LOWER(route)=? LIMIT 1");
$cycQ = $conn->prepare("SELECT dept_name FROM gov_screen_cycle
                         WHERE screen_file=? AND dept_name<>'' LIMIT 1");
$modQ = $conn->prepare("SELECT id, code FROM modules WHERE LOWER(code)=? LIMIT 1");

foreach ($DIRS as $d) {
    foreach (glob($ROOT . "/{$d}/*.php") as $f) {
        $b  = basename($f); $lb = strtolower($b);
        if (preg_match($SKIP_RX, $b)) { continue; }
        /* ⛔ **وجزءٌ مُضمَّنٌ أو ملفٌّ فارغٌ ليس شاشةً**: البادئةُ `_` عُرفُ
             الأجزاءِ في هذه الشجرة (`_board_decisions` · `_board_waiting`)،
             وملفُّ الصفرِ بايتٍ هيكلٌ لم يُكتب. عدُّهما يتيمَين تضخيمٌ كاذب. */
        if ($b[0] === '_' || filesize($f) < 512) { continue; }
        $screens++;
        $route = $d . '/' . $b;
        $norm  = strtolower($d . '/' . preg_replace('~\.php$~', '', $b));
        if (isset($side[$norm]) || isset($inc[$lb]) || isset($linked[$lb])) { continue; }

        $low = strtolower($route);
        $dept = ''; $title = '';
        $ownQ->bind_param('s', $low); $ownQ->execute();
        if ($o = $ownQ->get_result()->fetch_assoc()) { $dept = (string) $o['owner_dept']; $title = (string) $o['canonical_ar']; }
        if ($dept === '') { $cycQ->bind_param('s', $b); $cycQ->execute();
            if ($o = $cycQ->get_result()->fetch_row()) { $dept = (string) $o[0]; } }
        if ($dept === '') { $dept = 'بلا مالك مسجل'; }
        if ($title === '') { $title = preg_replace('~\.php$~', '', $b); }

        $mid = null; $mcode = null;
        $modQ->bind_param('s', $low); $modQ->execute();
        if ($o = $modQ->get_result()->fetch_row()) { $mid = (int) $o[0]; $mcode = (string) $o[1]; }

        $kb = (int) round(filesize($f) / 1024);
        $seenNorm[$norm] = 1;

        $st = $conn->prepare(
            "INSERT INTO gov_orphan_screens
               (route, route_norm, owner_dept, module_id, module_code, title_ar, size_kb)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               route=VALUES(route), owner_dept=VALUES(owner_dept), module_id=VALUES(module_id),
               module_code=VALUES(module_code), title_ar=VALUES(title_ar),
               size_kb=VALUES(size_kb), last_seen=NOW()");
        $st->bind_param('sssissi', $route, $norm, $dept, $mid, $mcode, $title, $kb);
        $st->execute();
        if ($st->affected_rows === 1) { $ins++; } else { $upd++; }
        $st->close();
    }
}

/* ما خرج من اليُتمِ يُوسَم `WIRED` ولا يُحذف — أثرُ المعالجةِ يبقى */
$wired = 0;
$r = $conn->query("SELECT id, route_norm FROM gov_orphan_screens WHERE decision <> 'WIRED'");
$toWire = array();
while ($r && ($x = $r->fetch_assoc())) {
    if (!isset($seenNorm[$x['route_norm']])) { $toWire[] = (int) $x['id']; }
}
if ($toWire) {
    $conn->query("UPDATE gov_orphan_screens SET decision='WIRED', decided_at=NOW()
                   WHERE id IN (" . implode(',', $toWire) . ")");
    $wired = count($toWire);
}

$tot  = (int) $conn->query("SELECT COUNT(*) FROM gov_orphan_screens WHERE decision<>'WIRED'")->fetch_row()[0];
$pend = (int) $conn->query("SELECT COUNT(*) FROM gov_orphan_screens WHERE decision='PENDING'")->fetch_row()[0];
$buf .= "  شاشاتٌ فُحصت = {$screens}\n";
$buf .= "  جديدٌ = {$ins} · محدَّثٌ = {$upd} · خرج من اليُتمِ (WIRED) = {$wired}\n";
$buf .= "  يتامى الآن = {$tot} · منها معلَّقُ القرار = {$pend}\n";
$buf .= "\n  بحسبِ الإدارة:\n";
$r = $conn->query("SELECT owner_dept, COUNT(*) n FROM gov_orphan_screens
                    WHERE decision<>'WIRED' GROUP BY owner_dept ORDER BY n DESC");
while ($r && ($x = $r->fetch_assoc())) { $buf .= sprintf("    %-40s %d\n", $x['owner_dept'], $x['n']); }

@file_put_contents($LOG, $buf);
echo $buf;
exit(0);
