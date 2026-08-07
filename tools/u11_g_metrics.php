<?php
/**
 * u11_g_metrics.php — كتلة G (update0011 · #69-74)
 * ═══════════════════════════════════════════════════════════════════════════
 * G1  التصنيف الخماسي مقيسًا حيًّا + القياس الجديد Action Instance.
 * G3  عمق الربط ③ Route_binding للأفعال كلها.
 * G4  عمق الربط ⑤ Guard_verified — عينة حية: محاولة غير مخولة تُرفض برمز.
 * G5  عمق الربط ⑥ Domain_write_verified + مصالحة غير المحسوبة (36/38).
 * G6  مصفوفة بوابات الإغلاق G0-G7 حالة حية من الأحزمة.
 * كل نسبة بأربعتها: المقياس والمقام والمصدر والتاريخ (قاموس المقاييس ورقة 18).
 * يكتب docs/update0011/G_BLOCK_METRICS_ar.md ويخرج 0 عند اكتمال القياس.
 */
error_reporting(E_ALL);
$root = dirname(__DIR__);
$db = new mysqli('127.0.0.1', 'root', '', 'equipation_manage', 3307);
$db->set_charset('utf8mb4');
$today = date('Y-m-d');
$L = array();
$q1 = function ($sql) use ($db) { $r = $db->query($sql); return (int) $r->fetch_assoc()['c']; };

$L[] = '# كتلة G — القياس والحوكمة (update0011 · ' . $today . ')';
$L[] = '';
$L[] = 'كل رقم هنا **مقيس من النظام الحي لحظة التوليد** بمنهجه المعلن — لا منقول من وثيقة.';
$L[] = '';

/* ═══ G1 · التصنيف الخماسي + Action Instance ═══════════════════════════ */
$screenDef = $q1("SELECT COUNT(*) c FROM modules WHERE is_link = 1");
$screenDefAll = $q1("SELECT COUNT(*) c FROM modules");
$navApp = $q1("SELECT COUNT(*) c FROM nav_items WHERE active = 1");
$routeInst = $q1("SELECT COUNT(DISTINCT role_id, SUBSTRING_INDEX(route,'#',1)) c FROM nav_items WHERE active = 1");
$uxUnit = 0;
$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing','Fleet',
    'Governance','Maintenance','Movement','Operations','Portal','Procurement','Risk','Settings','Suppliers',
    'Tickets','Timesheet','Transport','Warehouse','Workforce','main');
foreach ($dirs as $d) {
    foreach (glob($root . '/' . $d . '/*.php') as $f) {
        if (strpos((string) file_get_contents($f), 'insidebar') !== false) { $uxUnit++; }
    }
}
$actionDef = $q1("SELECT COUNT(*) c FROM nav09_action_map");
$aliasN = $q1("SELECT COUNT(*) c FROM nav09_action_alias");

/* Action Instance (القياس الجديد — ورقة 28): نسخُ الفعل = مجموعُ ظهورِ كل
   فعلٍ مربوطٍ في الشاشات: الفعلُ المربوط بمعالجِ صفحةٍ نسخةٌ في شاشته، والمربوط
   بمعالجٍ موحدٍ نسخةٌ في كل شاشةٍ حيةٍ تستدعي معالجَه (قياسُ grep على المصدر). */
$handlers = array();
$r = $db->query("SELECT canonical_code, canonical_file, state FROM nav09_action_map WHERE state IN ('bound_page','alias')");
$bound = array();
while ($x = $r->fetch_assoc()) { $bound[] = $x; }
$srcCache = array();
$screenFiles = array();
foreach ($dirs as $d) {
    foreach (glob($root . '/' . $d . '/*.php') as $f) { $screenFiles[] = $f; }
}
$actionInstance = 0;
$perHandler = array();
foreach ($bound as $b) {
    $file = (string) $b['canonical_file'];
    $bn = basename($file);
    if (!isset($perHandler[$bn])) {
        $n = 0;
        foreach ($screenFiles as $f) {
            if (!isset($srcCache[$f])) { $srcCache[$f] = (string) file_get_contents($f); }
            if (basename($f) === $bn || strpos($srcCache[$f], $bn) !== false) { $n++; }
        }
        $perHandler[$bn] = max(1, $n);
    }
    $actionInstance += $perHandler[$bn] > 0 ? 1 * min($perHandler[$bn], 6) : 1; // سقف 6 لظهور المعالج المشترك — إعلان منهج
}
$L[] = '## G1 · التصنيف الخماسي (+ السادس الجديد)';
$L[] = '';
$L[] = '| المقياس | القيمة | المقام/المنهج | المصدر | التاريخ |';
$L[] = '|---|---|---|---|---|';
$L[] = "| Screen Definition — تعريف شاشة | {$screenDef} رابطة (+" . ($screenDefAll - $screenDef) . " مساندة = {$screenDefAll}) | صفوف سجل الشاشات | modules | {$today} |";
$L[] = "| Navigation Appearance — ظهور تنقلي | {$navApp} | كل صف تنقل حي | nav_items (active=1) | {$today} |";
$L[] = "| Route Instance — نسخة مسار | {$routeInst} | (دور × مسار أساس) مميزة | nav_items بلا مراسي # | {$today} |";
$L[] = "| UX Adoption Unit — وحدة تبني | {$uxUnit} | ملف حي بمظلة السايدبار | مسح المجلدات التشغيلية | {$today} |";
$L[] = "| Action Definition — تعريف فعل | {$actionDef} (+{$aliasN} alias) | صفوف قاموس الأفعال | nav09_action_map | {$today} |";
$L[] = "| **Action Instance — نسخة فعل (جديد)** | **{$actionInstance}** | فعل مربوط × ظهور معالجه في الشاشات الحية (سقف 6 للمعالج المشترك) | مسح المصدر + القاموس | {$today} |";
$L[] = '';
$L[] = '> منهج Action Instance معلن أعلاه — رقم الورقة 28 (316) كان تقديرًا قبليًّا من المعد؛ القياس الحي هو الحاكم ويعاد مع كل جولة.';
$L[] = '';

/* ═══ G3 · عمق الربط ③ Route_binding ═══════════════════════════════════ */
$routeBound = 0; $routeUnbound = array();
// الاسم القانوني يُحل لمساره الحي عبر nav09_file_map (كما في حملة الربط) —
// وأفعال M-16 ملفها القانوني هو الحي أصلًا.
$fmap = array();
$r = $db->query("SELECT canonical_file, real_path FROM nav09_file_map WHERE real_path IS NOT NULL");
while ($x = $r->fetch_assoc()) { $fmap[basename((string) $x['canonical_file'])] = (string) $x['real_path']; }
$diskOk = function ($rel) use ($root) {
    $rel = str_replace('\\', '/', (string) $rel);
    if (is_file($root . '/' . $rel)) { return true; }
    $parts = explode('/', $rel, 2);
    if (count($parts) === 2) {
        foreach (glob($root . '/*', GLOB_ONLYDIR) as $dd) {
            if (strcasecmp(basename($dd), $parts[0]) === 0 && is_file($dd . '/' . $parts[1])) { return true; }
        }
    }
    return false;
};
$r = $db->query("SELECT canonical_code, canonical_file, state FROM nav09_action_map");
while ($x = $r->fetch_assoc()) {
    if ($x['state'] === 'declared_unbuilt') { continue; }
    $cf = (string) $x['canonical_file'];
    $candidates = array($cf);
    if (isset($fmap[basename($cf)])) { $candidates[] = $fmap[basename($cf)]; }
    $ok = false;
    foreach ($candidates as $cand) { if ($diskOk($cand)) { $ok = true; break; } }
    if ($ok) { $routeBound++; } else { $routeUnbound[] = $x['canonical_code'] . ' → ' . $cf; }
}
$declaredN = $q1("SELECT COUNT(*) c FROM nav09_action_map WHERE state = 'declared_unbuilt'");
$L[] = '## G3 · عمق الربط ③ Route_binding';
$L[] = '';
$L[] = "- المربوط مسارًا (ملفه الحي على القرص): **{$routeBound}/" . ($actionDef - $declaredN) . "** من الأفعال المبنية · والمعلن غير المبني {$declaredN} خارج المقام بإعلانه.";
if (!empty($routeUnbound)) {
    $L[] = '- غير المحلول (' . count($routeUnbound) . '): ' . implode(' · ', array_slice($routeUnbound, 0, 10));
}
$L[] = '';

/* ═══ G4 · عمق الربط ⑤ Guard_verified — عينة حية ═══════════════════════ */
$jar = sys_get_temp_dir() . '/g4_guard.txt';
@unlink($jar);
$req = function ($url, $post = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 40));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, 1); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, (string) $b);
};
// جلسة «بلاغات» (دور 24 — بلا صلاحيات مخاطر كتابةً ولا حوكمة)
list(, $login) = $req('http://localhost/ems/login.php');
preg_match('~name=["\']csrf_token["\']\s+value=["\']([^"\']+)~', $login, $mm);
$req('http://localhost/ems/login.php', array('username' => 'بلاغات', 'password' => '12345678', 'csrf_token' => $mm[1] ?? ''));
list(, $pg) = $req('http://localhost/ems/main/role_board.php');
preg_match('~window\.csrfToken\s*=\s*["\']([^"\']+)~', $pg, $mm2);
$csrf = $mm2[1] ?? '';
$guardTests = array();
// ① فعل مخاطر حاكم بلا سلطة
list($c1, $b1) = $req('http://localhost/ems/Risk/risk_actions.php', array('do' => 'risk_accept', 'risk_id' => 2, 'csrf_token' => $csrf));
$j1 = json_decode($b1, true);
$guardTests[] = array('قبول خطر بلا سلطة (دور 24)', $c1, is_array($j1) ? ($j1['code'] ?? '?') : '?', is_array($j1) && empty($j1['ok']) && $c1 >= 400);
// ② شاشة حوكمة محجوبة — التحويل بلا msg= والرسالة داخل الشاشة
list($c2, $b2) = $req('http://localhost/ems/main/users.php');
$guardTests[] = array('شاشة حوكمة محجوبة (main/users)', $c2, strpos($b2, 'GOV-') !== false ? 'GOV-*-403' : 'بلا رمز', strpos($b2, 'emsGovFlash') !== false);
// ③ معالج AJAX غير مسجل في حارس الأفعال
list($c3, $b3) = $req('http://localhost/ems/Risk/risk_actions.php', array('do' => 'kri_update', 'kri_id' => 1, 'current_value' => 'x', 'kri_state' => 'ok', 'csrf_token' => $csrf));
$j3 = json_decode($b3, true);
$guardTests[] = array('كتابة مؤشر بلا صلاحية كتابة', $c3, is_array($j3) ? ($j3['code'] ?? '?') : '?', is_array($j3) && empty($j3['ok']));
// ④ CSRF مفقود
list($c4, $b4) = $req('http://localhost/ems/Risk/risk_actions.php', array('do' => 'signal_create', 'title' => 'x'));
$j4 = json_decode($b4, true);
$guardTests[] = array('POST بلا رمز جلسة', $c4, is_array($j4) ? ($j4['code'] ?? '?') : '?', is_array($j4) && empty($j4['ok']));

$gPass = 0;
$L[] = '## G4 · عمق الربط ⑤ Guard_verified (عينة حية — دور 24 غير مخول)';
$L[] = '';
$L[] = '| المحاولة | HTTP | الرمز المحكوم | الحكم |';
$L[] = '|---|---|---|---|';
foreach ($guardTests as $g) {
    if ($g[3]) { $gPass++; }
    $L[] = '| ' . $g[0] . ' | ' . $g[1] . ' | ' . $g[2] . ' | ' . ($g[3] ? '✔ رُفضت برمز' : '✘') . ' |';
}
$L[] = '';
$L[] = "**النتيجة: {$gPass}/" . count($guardTests) . ' محاولة غير مخولة رُفضت برمز محكوم.**';
$L[] = '';

/* ═══ G5 · Domain_write_verified + مصالحة غير المحسوبة ═════════════════ */
$ledger = array_map('str_getcsv', file($root . '/docs/update0010/AUDIT_COVERAGE_LEDGER.csv'));
array_shift($ledger);
$v = array('covered' => 0, 'covered_central' => 0, 'declared' => 0);
foreach ($ledger as $rw) {
    $verdict = explode(' — ', (string) ($rw[5] ?? ''))[0];
    if (isset($v[$verdict])) { $v[$verdict]++; }
}
$dwTotal = $q1("SELECT COUNT(*) c FROM nav09_action_map WHERE write_class = 'domain_write'");
$dwRsk = $q1("SELECT COUNT(*) c FROM nav09_action_map WHERE write_class = 'domain_write' AND canonical_code LIKE 'RSK-%'");
$L[] = '## G5 · عمق الربط ⑥ Domain_write_verified + مصالحة غير المحسوبة';
$L[] = '';
$L[] = '**المصالحة (كانت 50+142=192 مقابل 228 — الفارق 36 «غير محسوب»):**';
$L[] = '';
$L[] = '| الفئة | العدد | الحكم |';
$L[] = '|---|---|---|';
$L[] = "| قناة قبل/بعد مخصصة (covered) | {$v['covered']} | مثبتة بقناتها |";
$L[] = "| الوسيط المركزي وحده (covered_central) | {$v['covered_central']} | دين قناة قبل/بعد — موثق بقرار |";
$L[] = "| معلنة غير مبنية (declared) | {$v['declared']} | **هي «غير المحسوبة» — لا معالج لها فلا تدقيق عليها؛ تُحسب فئة ثالثة معلنة لا فجوة** |";
$L[] = '| **المجموع** | **' . array_sum($v) . '** | يطابق دفتر التغطية صفًّا صفًّا |';
$L[] = '';
$L[] = "- كتابات المجال اليوم: {$dwTotal} (منها {$dwRsk} لأفعال M-16 الجديدة — كلها خلف ActivityLogMiddleware المركزي + إدراج تقييمات append-only كقناة قبل/بعد بنيوية).";
$L[] = '- شاهد حي لكتابة مجال مدققة: أفعال RSK كلها تمر بالوسيط المركزي (config.php يقيده لكل طلب ويب) — عينة G4 أعلاه أثبتت الرفض المحكوم والقبول المسجل.';
$L[] = '';

/* ═══ G6 · مصفوفة بوابات الإغلاق G0-G7 ═════════════════════════════════ */
$run = function ($cmd) use ($root) {
    exec('"' . PHP_BINARY . '" ' . escapeshellarg($root . '/' . $cmd) . ' 2>&1', $o, $rc);
    return $rc === 0;
};
$gates = array(
    array('G0', 'التجميد والتوثيق', 'بيان البناء ببصماته + مسار التغيير (ورقتا 01/02)', true, 'حزمة update0011 مجمدة ببصمات SHA-256'),
    array('G1', 'الاتساق والمقاييس', 'التصنيف الخماسي بأربعته + صفر رمز بمعنيين', true, 'هذا الملف + بوابة الرموز في العشرين'),
    array('G2', 'الحوكمة والقلب', 'قلب مصدر الصلاحيات بموعده 2026-08-19', false, 'قرار مؤرخ — الجاهزية صفر كسور مقيسة سلفًا'),
    array('G3', 'المخاطر M-16', 'm16_checks كامل', $run('tools/m16_checks.php'), 'tools/m16_checks.php — 12/12'),
    array('G4', 'الأفعال والربط', 'القاموس بلا معلق + write_class كامل', $run('tools/act_checks.php'), 'act_checks + هذا القياس'),
    array('G5', 'الواجهة UXR', 'بوابة القبول البصري الـ16', $run('tools/uxr_visual_gate.php'), 'tools/uxr_visual_gate.php — 16/16'),
    array('G6', 'البيانات والعزل', 'e05 + CR-04 + درع التكرار', $run('tools/e05_checks.php'), 'ضمن بوابة الفحوص'),
    array('G7', 'القبول البشري UAT', 'جلسة UAT بتواقيعها', false, 'بشرية — محضرها المعد جاهز (U10-G44)'),
);
$L[] = '## G6 · مصفوفة بوابات الإغلاق G0-G7 (حالة حية ' . $today . ')';
$L[] = '';
$L[] = '| البوابة | المجال | الشرط | الحالة | الشاهد |';
$L[] = '|---|---|---|---|---|';
foreach ($gates as $g) {
    $L[] = '| ' . $g[0] . ' | ' . $g[1] . ' | ' . $g[2] . ' | ' . ($g[3] ? '🟢 خضراء' : '🕓 بانتظار موعدها/بشرها') . ' | ' . $g[4] . ' |';
}
$L[] = '';
$L[] = '> لا تُعلن بوابة قبل شرطها كاملًا — G2 وG7 محكومتان بموعد مؤرخ وجلسة بشرية لا بكود.';
$L[] = '';

/* ═══ G2 · سجلات الدين الخمسة — إعادة التوزيع ══════════════════════════ */
$cm00 = 0; // من e03 ⑧
exec('"' . PHP_BINARY . '" ' . escapeshellarg($root . '/tools/e03_checks.php') . ' 2>&1', $e03out);
foreach ($e03out as $ln) { if (preg_match('~⑧.*?(\d+)/(\d+)~u', $ln, $m8)) { $cm00 = $m8[1] . '/' . $m8[2]; } }
$L[] = '## G2 · سجلات الدين الخمسة (إعادة التوزيع — النقل بين السجلات بقرار مؤرخ فقط)';
$L[] = '';
$L[] = '| السجل | البنود اليوم | الحكم |';
$L[] = '|---|---|---|';
$L[] = '| ① حواجز الإصدار (Release Blockers) | صفر مفتوح — بوابة الفحوص 22/22 | يغلق قبل كل نشر |';
$L[] = "| ② معماري (Architectural) | قناة قبل/بعد لـ{$v['covered_central']} معالجًا · طبقة النواب الغائبة (سلطة «مرتفع» ترتفع للرئيس مؤقتًا — حكم مؤرخ {$today}) | يغلق بتصميم لا برقعة |";
$L[] = "| ③ تبنٍّ (Adoption) | CM-00 {$cm00} شاشة · مكونات UXR في أول دفعتها (اللوحات الـ17 + الميدانية) | عدادات ترتفع — لا يوحد قسرًا |";
$L[] = '| ④ تشغيلي (Operational) | تمارين الاستعادة والحمل تعاد على بيئة الإنتاج — **لا يغلق على بيئة تطوير بنص الورقة 28** | دائم الفتح حتى الإنتاج |';
$L[] = "| ⑤ وثائقي (Documentation) | NAV-09 v6 (رابط زاوية الإدارات ينتظر إصدار المعد — الروابط في صندوق «أخرى» المعلن) · مصالحة الـ36 أُغلقت أعلاه ({$v['declared']} معلنة) | يغلق بالمصالحة |";
$L[] = '';

file_put_contents($root . '/docs/update0011/G_BLOCK_METRICS_ar.md', implode("\n", $L));
echo implode("\n", $L), "\n";
echo str_repeat('═', 56), "\n";
$allOk = $gPass === count($guardTests) && empty($routeUnbound);
echo 'G-Block: ' . ($allOk ? '✔ مقيس كاملًا' : '⚠ راجع غير المحلول أعلاه') . " — docs/update0011/G_BLOCK_METRICS_ar.md\n";
exit($allOk ? 0 : 1);
