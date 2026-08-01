<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-19 — اختبار قبول: مساحاتُ العمل (WSP-01 §3 · §4 · §5 · §7 — W1→W4)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/h19_workspace_test.php
 *
 * ما يُثبته:
 *   W1 **لوحةُ الدخول بحسب الحساب**: مديرُ مشروعٍ ⇒ مساحةُ مشروعه ·
 *      مشرفُ موردٍ ⇒ مساحةُ مورده · مشغّلٌ ⇒ مساحتُه الشخصية —
 *      وWorkspaceOpened يُقيَّد.
 *   W2 **التحوّل**: LayerSwitched يُقيَّد بفاعله ومن/إلى.
 *   W3 **النطاق**: خارجيٌّ يفتح مساحةَ مشروعٍ ليس له ⇒ **403 مسجَّلة** —
 *      والطبقةُ **لا تظهر في مبدّله أصلًا**.
 *   W4 **الحارسُ والتغذية**: بطاقةٌ بلا تغذيةٍ من مالكها ⇒ «غيرُ متاح»
 *      **بمالكها** لا صفرًا مضلِّلًا · وفترةٌ غيرُ صالحة ⇒ 422.
 *   + القاموسُ واحدٌ (UQ) والتخطيطُ بالنوع لا بالكيان (UQ نوع×نسخة).
 *
 * البذرُ معزول بعلامة H19T — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '15', 'company_id' => 4, 'name' => 'H19 workspace test');

require_once dirname(__DIR__) . '/app/Services/Portal/WorkspaceFeedService.php';

use App\Services\Portal\WorkspaceFeedService as WFS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$MARK  = 'H19T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE l FROM workspace_navigation_log l JOIN users u ON u.id = l.account_id
                   WHERE u.username LIKE '{$MARK}%'");
    $conn->query("DELETE c FROM user_capacities c JOIN users u ON u.id = c.account_id
                   WHERE u.username LIKE '{$MARK}%'");
    $conn->query("DELETE FROM users WHERE username LIKE '{$MARK}%'");
    $conn->query("DELETE FROM suppliers WHERE name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-19 — مساحاتُ العمل: البنيةُ واحدةٌ والمحتوى يختلف ══\n");

head('البذر — مديرُ مشروعٍ ومشرفُ موردٍ ومشغّل');

$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروعُ {$MARK}', 'ع', 'م', '0')");
$PRJ = intval($conn->insert_id);
$conn->query("INSERT INTO suppliers (company_id, name, phone, status, created_at)
              VALUES ({$CO}, 'موردُ {$MARK}', '1', 'active', NOW())");
$SUP = intval($conn->insert_id);

$mk = function ($sfx, $role, $supplier = 'NULL') use ($conn, $CO, $MARK) {
    $conn->query("INSERT INTO users (name, username, email, password, role, company_id,
                  supplier_entity_id, parent_id, status, created_at)
                  VALUES ('{$sfx} {$MARK}', '{$MARK}-{$sfx}', '{$MARK}{$sfx}@t.t', 'x',
                          '{$role}', {$CO}, {$supplier}, '', 'active', NOW())");
    return intval($conn->insert_id);
};
$U_PM = $mk('pm', '5');
$U_SS = $mk('ss', '8', (string) $SUP);
$U_OP = $mk('op', '5');
$cap = function ($u, $type, $scopeT, $scopeI, $role = '5') use ($conn, $CO) {
    $si = $scopeI === null ? 'NULL' : intval($scopeI);
    $conn->query("INSERT INTO user_capacities (company_id, account_id, capacity_type, role,
                  scope_type, scope_id, source_type, source_note, valid_from, state)
                  VALUES ({$CO}, {$u}, '{$type}', '{$role}', '{$scopeT}', {$si}, 'delegation',
                          'بذر h19', '2026-01-01', 'active')");
    return intval($conn->insert_id);
};
$C_PM = $cap($U_PM, 'project_manager', 'project', $PRJ);
$C_SS = $cap($U_SS, 'supplier_supervisor', 'supplier', $SUP, '8');
$C_OP = $cap($U_OP, 'operator', 'company', null);
check($PRJ && $SUP && $U_PM && $U_SS && $U_OP && $C_PM && $C_SS && $C_OP,
      "مشروعٌ #{$PRJ} وموردٌ #{$SUP} · مديرُ مشروعٍ #{$U_PM} ومشرفٌ #{$U_SS} ومشغّلٌ #{$U_OP}");

// ═══ W1 لوحة الدخول ═══
head('W1 — لوحةُ الدخول بحسب الحساب لا لوحةٌ عامة');

$pmCap = $conn->query("SELECT * FROM user_capacities WHERE id={$C_PM}")->fetch_assoc();
$e = WFS::entryFor($pmCap);
check($e['entity_type'] === 'project' && $e['entity_id'] === $PRJ,
      '★★ مديرُ المشروع يُفتح على **مساحة مشروعه** (§5)');
$ssCap = $conn->query("SELECT * FROM user_capacities WHERE id={$C_SS}")->fetch_assoc();
$e = WFS::entryFor($ssCap);
check($e['entity_type'] === 'supplier' && $e['entity_id'] === $SUP,
      '★ ومشرفُ المورد على **مساحة مورده في نطاقه حصرًا**');
$opCap = $conn->query("SELECT * FROM user_capacities WHERE id={$C_OP}")->fetch_assoc();
$e = WFS::entryFor($opCap);
check($e['entity_type'] === 'person' && $e['entity_id'] === $U_OP,
      '★ والمشغّلُ على **مساحته الشخصية** — «لأنها عملُه كلُّه»');

$f = WFS::feed($conn, $gate, $CO, $U_PM, 'project', $PRJ, 'today');
check($f['ok'] && $f['entity']['type'] === 'project',
      '★ مساحةُ المشروع فُتحت ببطاقاتها (' . count($f['cards']) . ')');
$opened = intval($conn->query("SELECT COUNT(*) n FROM workspace_navigation_log
                                WHERE account_id={$U_PM} AND result='ok'")->fetch_assoc()['n']);
check($opened >= 1, '★ وWorkspaceOpened **قُيّد في سجل التنقل** (Insert-only)');

// ═══ W2 التحوّل ═══
head('W2 — LayerSwitched يُقيَّد بمن/إلى');

WFS::logSwitch($conn, $CO, $U_PM, 'project:' . $PRJ, 'person:' . $U_OP, (string) $U_OP);
$sw = $conn->query("SELECT * FROM workspace_navigation_log
                     WHERE account_id={$U_PM} AND from_layer='project:{$PRJ}'
                       AND to_layer='person:{$U_OP}'")->fetch_assoc();
check($sw !== null, '★ التحوّلُ من مساحة المشروع إلى مساحة العضو **مقيَّدٌ بطرفيه** (W2)');

// ═══ W3 النطاق ═══
head('W3 — الخارجيُّ في نطاقه حصرًا والطبقةُ الممنوعة لا تُعرض');

$layers = WFS::allowedLayers($conn, $gate, $CO, $U_SS);
$types = array_map(function ($l) { return $l['entity_type'] . ':' . $l['entity_id']; }, $layers);
check(in_array('supplier:' . $SUP, $types, true) && !in_array('project:' . $PRJ, $types, true),
      '★★ مبدّلُ المشرف يحمل موردَه **ولا يعرض مشروعًا ليس له أصلًا** (§3)');

$f = WFS::feed($conn, $gate, $CO, $U_SS, 'project', $PRJ, 'today');
check(!$f['ok'] && $f['code'] === 403,
      '★★★ ومحاولتُه فتحَ مساحة المشروع بمعرّفٍ صريح ⇒ **403** (W3)');
$denied = intval($conn->query("SELECT COUNT(*) n FROM workspace_navigation_log
                                WHERE account_id={$U_SS} AND result='denied'")->fetch_assoc()['n']);
check($denied >= 1, '★ والمحاولةُ **مسجَّلةٌ في سجل التنقل**');

// ═══ W4 الحارس والتغذية ═══
head('W4 — «غيرُ متاح» بمالكه لا صفرًا · وفترةٌ فاسدة 422');

$f = WFS::feed($conn, $gate, $CO, $U_PM, 'project', $PRJ, 'today');
$unavail = null;
foreach ($f['cards'] as $c) { if ($c['code'] === 'stops.by_owner') { $unavail = $c; } }
check($unavail !== null && $unavail['unavailable'] !== null
      && strpos($unavail['unavailable'], 'UX-03') !== false && $unavail['value'] === null,
      '★★★ بطاقةُ التوقفات بلا تغذيةٍ ⇒ **«غيرُ متاح» باسم مالكها UX-03** — لا صفرًا مضلِّلًا (W4)');
$live = null;
foreach ($f['cards'] as $c) { if ($c['code'] === 'decisions.pending') { $live = $c; } }
check($live !== null && $live['live'] === true,
      '★ وعدّاداتُ الانتظار **حيّةٌ بلا كاش** (cache_ttl=0) — «البطاقاتُ الحيةُ بلا كاش»');

$f = WFS::feed($conn, $gate, $CO, $U_PM, 'project', $PRJ, 'yesterday');
check(!$f['ok'] && $f['code'] === 422, '★ وفترةٌ غيرُ صالحة ⇒ **422**');
$f = WFS::feed($conn, $gate, $CO, $U_PM, 'galaxy', 1, 'today');
check(!$f['ok'] && $f['code'] === 422, 'ونوعُ كيانٍ مجهول ⇒ 422');

// القاموسُ والتخطيط
$dup = $conn->query("INSERT INTO workspace_cards (code, title_ar, owner_doc, source_service)
                     VALUES ('prod.units.period', 'مكرر', 'X', 'y')");
check($dup === false, '★ ومؤشران بمعنًى واحد **يرفضهما UQ القاموس** — «قاموسٌ واحد»');
$dup2 = $conn->query("INSERT INTO workspace_layouts (entity_type, layout_json, version)
                      VALUES ('project', '[]', 1)");
check($dup2 === false, 'والتخطيطُ **بالنوع والنسخة UQ** — فلا تتفرق الأشكال');

// فتاتُ الطريق
$bc = WFS::breadcrumb('project', $PRJ);
check(count($bc) === 2 && $bc[0]['entity_type'] === 'department',
      '★ فتاتُ الطريق: شركتي ← المشروع — ويُنقر للرجوع لأي مستوى');

// ═══ الخاتمة ═══
fwrite(STDOUT, "\n══════════════════════════════════════\n");
fwrite(STDOUT, "  النتيجة: {$PASS} نجاح · {$FAIL} فشل\n");
exit($FAIL > 0 ? 1 : 0);
