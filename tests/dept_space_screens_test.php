<?php
/**
 * tests/dept_space_screens_test.php — شاهدُ شاشاتِ «مساحةِ الإدارة» الستِّ والعشرين
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0133 · INJ-0208 · INJ-0217 · INJ-0281 · INJ-0304
 *                  INJ-0337 · INJ-0355 · INJ-0372 · INJ-0532 · INJ-0575
 *                  INJ-0123 · INJ-0211 · INJ-0247 · INJ-0266 · INJ-0267 · INJ-0412
 *                  INJ-0134 · INJ-0156 · INJ-0164 · INJ-0268 · INJ-0354 · INJ-0580
 *
 * **المبنيُّ**: أحدَ عشرَ غلافًا لمكوّنِ «مساحة مخاطر الإدارة» الواحد — إدارةٌ
 * لكلِّ غلافٍ بزاويتِها من `org_units` الحيِّ (INJAZ-UX-01 §4-3: «مكوّنٌ واحدٌ
 * يتغير نطاقُه وعنوانُه بحسب الإدارة — لا ستَّ عشرةَ نسخةً»).
 *
 * ── وثلاثةٌ يجب أن تجتمع، وإلا فالشاشةُ وهمٌ ─────────────────────────────────
 *   ① **ملفٌّ** موجود.
 *   ② **صفٌّ في `modules`** — وشاشةٌ غيرُ مسجَّلةٍ تمرُّ بـ**fail-open** فتُفتح
 *      للجميع؛ فالتسجيلُ حراسةٌ لا توثيق.
 *   ③ **منحٌ يميّز** — أدوارُ الإدارةِ + الرئيسُ + المخاطر، عرضًا فقط.
 *
 * ── والتصييرُ يُقاس بـHTTP **بلا اتّباعِ التحويل** ──────────────────────────
 * مع `CURLOPT_FOLLOWLOCATION` يعود الحجبُ (302 ⇒ لوحة) بـ**200** فيُقرأ نجاحًا.
 * فأوّلُ قياسٍ لهذه الشاشاتِ أعطى 200 لأحدَ عشرَ كلِّها — وكانت عشرةٌ منها
 * **محجوبةً** فعلًا. فالاتّباعُ مُطفأٌ هنا، والحكمُ على الرمزِ وعلى الجسدِ معًا.
 *
 * ◆ ويُثبت **الطرفان**: دورُ المخاطرِ يرى الإحدى عشرةَ · ودورُ إدارةٍ يرى شاشتَه
 *   ويُحجب عن غيرِها. فمنحٌ للجميعِ ليس منحًا، وحجبٌ للجميعِ ليس شاشة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$CO = 4;
$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };

/* اللاحقةُ ⇒ رمزُ وحدةِ الهيكل — المصدرُ نفسُه الذي بنى الأغلفة */
$MAP = array(
    'cap' => 'financing',  'crp' => 'tickets',        'flt' => 'fleet',
    'hrm' => 'hr',         'inv' => 'warehouse',      'mnt' => 'maintenance',
    'ops' => 'ops',        'prc' => 'procurement_ops', 'sit' => 'movement',
    'trp' => 'transport',  'wrk' => 'operators',
);

$say('══ عائلةُ المخاطر — ' . count($MAP) . ' غلافًا');

/* ══ ① الملفُّ والصفُّ والمنح ═══════════════════════════════════════════════ */
$missFile = array(); $missMod = array(); $noGrant = array();
foreach ($MAP as $sfx => $code) {
    $rel = 'Risk/risk_dept_' . $sfx . '.php';
    if (!is_file($ROOT . '/' . $rel)) { $missFile[] = $sfx; continue; }
    $st = $conn->prepare('SELECT id FROM modules WHERE code = ? LIMIT 1');
    $st->bind_param('s', $rel);
    $st->execute();
    $row = $st->get_result()->fetch_row();
    $st->close();
    if ($row === null) { $missMod[] = $sfx; continue; }
    $mid = (int) $row[0];
    $r = $conn->query("SELECT COUNT(*) FROM role_permissions WHERE module_id = {$mid} AND can_view = 1");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n === 0) { $noGrant[] = $sfx; }
}
$ok(!$missFile, 'الملفاتُ الإحدى عشرةَ موجودة', 'غائبٌ: ' . implode(',', $missFile));
$ok(!$missMod, '**وكلُّها مسجَّلةٌ في `modules`**',
    'غيرُ مسجَّلٍ: ' . implode(',', $missMod) . ' — والشاشةُ غيرُ المسجَّلةِ fail-open تُفتح للجميع');
$ok(!$noGrant, '   ولكلٍّ منحُ عرضٍ واحدٌ على الأقل', 'بلا منحٍ: ' . implode(',', $noGrant));

/* ══ ② الغلافُ يثبّت زاويةً **موجودةً في الهيكلِ الحيّ** ════════════════════ */
$badUnit = array();
foreach ($MAP as $sfx => $code) {
    $src = (string) @file_get_contents($ROOT . '/Risk/risk_dept_' . $sfx . '.php');
    if (strpos($src, "unit_code = '{$code}'") === false) { $badUnit[] = $sfx . ':نصًّا'; continue; }
    $r = $conn->query("SELECT unit_id FROM org_units WHERE company_id = {$CO} AND unit_code = '{$code}' AND active = 1 LIMIT 1");
    if (!$r || !$r->num_rows) { $badUnit[] = $sfx . ':لا وحدةَ'; }
}
$ok(!$badUnit, 'وكلُّ غلافٍ يثبّت زاويتَه على وحدةٍ **حيّةٍ ونشطة**',
    'مختلٌّ: ' . implode(' · ', $badUnit) . ' — زاويةٌ على وحدةٍ غائبةٍ تُصيّر شاشةً خاويةً تبدو سليمة');
$ok(count(array_unique(array_values($MAP))) === count($MAP),
    '   ولا وحدتانِ لغلافٍ واحدٍ ولا غلافانِ لوحدةٍ واحدة');

/* ══ ③ التصييرُ بـHTTP — **بلا اتّباعِ تحويل** ══════════════════════════════ */
$J = sys_get_temp_dir() . '/rds_' . getmypid() . '.txt';
@unlink($J);
$rq = function ($url, $post = null, $follow = false) use ($J) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_COOKIEJAR => $J, CURLOPT_COOKIEFILE => $J, CURLOPT_TIMEOUT => 90,
        CURLOPT_POST => $post !== null));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $loc = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return array('body' => (string) $b, 'code' => $code, 'loc' => $loc);
};
$login = function ($user, $pass) use ($rq, $BASE, $J) {
    @unlink($J);
    $r = $rq($BASE . '/login.php', null, true);
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $r['body'], $t);
    $r = $rq($BASE . '/login.php', array('username' => $user, 'password' => $pass,
                                         'csrf_token' => isset($t[1]) ? $t[1] : ''), true);
    return mb_strpos($r['body'], 'name="password"') === false;
};

/* اسمُ الدخولِ **يُقرأ من القاعدةِ بالدورِ** لا يُكتب بيد — والعمودُ `username`
   لا `name` (فـ`login.php` يستعلم بـ`WHERE username = ?`، والاسمانِ يختلفان:
   الدخولُ «مخاطر» والاسمُ «مدير المخاطر»). واسمٌ مكتوبٌ بيدٍ يجعل الشاهدَ
   يرسب لسببٍ لا علاقةَ له بالمفحوص. */
$whoIs = function ($role) use ($conn, $CO) {
    $r = $conn->query("SELECT username FROM users
                        WHERE role = '{$role}' AND company_id = {$CO} AND username <> ''
                        ORDER BY id LIMIT 1");
    return ($r && $r->num_rows) ? (string) $r->fetch_row()[0] : '';
};
$RISK_USER = $whoIs(28);
$ok($RISK_USER !== '', 'وُجد حسابُ الدور 28 بالقاعدة (' . ($RISK_USER !== '' ? $RISK_USER : '—') . ')');
$logged = $RISK_USER !== '' && $login($RISK_USER, '12345678');
$ok($logged, 'ودخلَ «' . $RISK_USER . '» (الدور 28 — المحفظةُ الكاملة)',
    'بلا دخولٍ لا يُحكَم على تصييرٍ ولا حجب');

if ($logged) {
    $rendered = array(); $blocked = array();
    foreach ($MAP as $sfx => $code) {
        $r = $rq($BASE . '/Risk/risk_dept_' . $sfx . '.php');
        $isRedirect = ($r['code'] >= 300 && $r['code'] < 400);
        if ($isRedirect) { $blocked[] = $sfx . '(' . $r['code'] . ')'; continue; }
        if ($r['code'] !== 200) { $blocked[] = $sfx . '(' . $r['code'] . ')'; continue; }
        /* والجسدُ يجب أن يحمل المكوّنَ لا صفحةَ خطأ */
        if (mb_strpos($r['body'], 'مخاطر') === false) { $blocked[] = $sfx . '(بلا مضمون)'; continue; }
        $rendered[] = $sfx;
    }
    $ok(count($rendered) === count($MAP),
        '**وصُيِّرت الإحدى عشرةَ لدورِ المخاطر** (' . count($rendered) . '/' . count($MAP) . ')',
        'محجوبٌ: ' . implode(' · ', $blocked));

    /* ── والطرفُ الآخر: دورُ إدارةٍ يرى شاشتَه ويُحجب عن غيرِها ───────────── */
    $WH_USER = $whoIs(25);
    $logged2 = $WH_USER !== '' && $login($WH_USER, '12345678');
    $ok($logged2, 'ودخلَ «' . $WH_USER . '» (الدور 25 — المخازن)');
    if ($logged2) {
        $mine = $rq($BASE . '/Risk/risk_dept_inv.php');
        $other = $rq($BASE . '/Risk/risk_dept_cap.php');
        $ok($mine['code'] === 200 && mb_strpos($mine['body'], 'مخاطر') !== false,
            '   **يرى مخاطرَ المخازن** (HTTP ' . $mine['code'] . ')');
        $isBlocked = ($other['code'] >= 300 && $other['code'] < 400);
        $ok($isBlocked,
            '   **ويُحجب عن مخاطرِ التمويلِ** (HTTP ' . $other['code'] .
            ($other['loc'] !== '' ? ' ⇒ ' . basename(parse_url($other['loc'], PHP_URL_PATH)) : '') . ')',
            'منحٌ لا يميّز ليس منحًا — ولو تُبع التحويلُ لعاد 200 فقُرئ نجاحًا');
    }
}
@unlink($J);

/* ══ ④ ولا مسارَ تدقيقٍ ثانٍ: الغلافُ لا يستعلم بنفسِه ══════════════════════ */
$fat = array();
foreach ($MAP as $sfx => $code) {
    $src = (string) @file_get_contents($ROOT . '/Risk/risk_dept_' . $sfx . '.php');
    $body = preg_replace('~/\*.*?\*/~s', '', $src);
    /* الاستعلامُ الوحيدُ المسموحُ هو قراءةُ unit_id لتثبيتِ الزاوية */
    $q = preg_match_all('~->prepare\(~', $body);
    if ($q > 1) { $fat[] = $sfx . '(' . $q . ')'; }
    if (strpos($body, 'dept_risk_space.php') === false) { $fat[] = $sfx . ':لا يُضمّن المكوّن'; }
}
$ok(!$fat, 'وكلُّ غلافٍ **رقيقٌ**: استعلامُ الزاويةِ ثم المكوّنُ المشترك',
    'سمينٌ: ' . implode(' · ', $fat) . ' — ونسخةٌ من المنطقِ في الغلافِ تتفرّق عن المكوّن');

/* ══ ⑤ عائلةُ الحوكمةِ — والفخُّ الذي كاد يُشحَن اثنتَي عشرةَ مرّة ═══════════
     مكوّنُ «حوكمة الإدارة» كان يُصيّر «✔ صفر خرق لفصل الواجبات» حتى حين تكون
     `sod_queries` **خاويةً** — براءةٌ مُعلَنةٌ عمّا لم يُقَس. فبناءُ اثنَي عشرَ
     غلافًا بلا قياسٍ كان سيشحن اثنتَي عشرةَ طمأنينةً كاذبة. */
$GOVMAP = array(
    'cap' => 'Financing',  'crp' => 'Tickets',     'flt' => 'Equipments',
    'hrm' => 'Workforce',  'inv' => 'Procurement', 'mnt' => 'Maintenance',
    'ops' => 'Operations', 'prc' => 'Procurement', 'sit' => 'Operations',
    'trp' => 'Transport',  'wrk' => 'Workforce',   'ceo' => 'Portal',
);
$say('── عائلةُ الحوكمة (' . count($GOVMAP) . ')');
$govMiss = array(); $govUnreg = array();
foreach ($GOVMAP as $sfx => $dir) {
    $rel = $dir . '/gov_dept_' . $sfx . '.php';
    if (!is_file($ROOT . '/' . $rel)) { $govMiss[] = $sfx; continue; }
    $st = $conn->prepare('SELECT id FROM modules WHERE code = ? LIMIT 1');
    $st->bind_param('s', $rel);
    $st->execute();
    if ($st->get_result()->fetch_row() === null) { $govUnreg[] = $sfx; }
    $st->close();
}
$ok(!$govMiss, 'ملفاتُ حوكمةِ الإدارةِ موجودة', 'غائبٌ: ' . implode(',', $govMiss));
$ok(!$govUnreg, '   وكلُّها مسجَّلةٌ في `modules`', 'غيرُ مسجَّلٍ: ' . implode(',', $govUnreg));

/* **«صفرُ خرقٍ» يلزمه قياسٌ** — يُثبَت على المكوّنِ نفسِه لا على غلافٍ واحد */
$comp = (string) file_get_contents($ROOT . '/includes/dept_gov_space.php');
$ok(strpos($comp, "elseif (!empty(\$GOV_DEPT['sod_queries']))") !== false,
    '**والفرعُ الأخضرُ مشروطٌ بوجودِ قياسٍ** في المكوّنِ المشترك',
    'قائمةٌ خاويةٌ كانت تُصيَّر «✔ صفر خرق» — براءةٌ عمّا لم يُقَس');
$ok(mb_strpos($comp, 'لا قياسَ معلَن') !== false,
    '   وحالةٌ ثالثةٌ **تُعلن الصمتَ صمتًا**');

/* ونطاقُ التصديقِ مُعمَّمٌ ومُطابَقٌ على سجلِّ الشاشاتِ لا مأخوذٌ نصًّا */
$act = (string) file_get_contents($ROOT . '/Governance/gov_m14_actions.php');
$ok(strpos($act, "preg_match('~^gov_dept_[a-z]{2,8}\$~', \$attestScope)") !== false,
    'ونطاقُ التصديقِ محصورٌ بنمطٍ — لا مسارَ يمرُّ منه');
$ok(strpos($act, 'FROM modules WHERE code LIKE ?') !== false
    && strpos($act, "'gov_dept_gov:' . gmdate") === false,
    '   **ويُطابَق على سجلِّ الشاشات** ثم يُقاس الإذنُ على الشاشةِ المطلوبةِ نفسِها',
    'كان مثبَّتًا على gov_dept_gov — فكلُّ غلافٍ جديدٍ يُسجّل تصديقَه تحتَ إدارةٍ أخرى');

/* ══ ⑥ الشاشتانِ المستقلّتان ═══════════════════════════════════════════════ */
$say('── الشاشتانِ المستقلّتان');
foreach (array('Governance/read_log.php' => 'سجلُّ الاطّلاعِ الحساس',
               'Tickets/ticket_close.php' => 'طابورُ إغلاقِ البلاغات') as $rel => $lbl) {
    $exists = is_file($ROOT . '/' . $rel);
    $st = $conn->prepare('SELECT id FROM modules WHERE code = ? LIMIT 1');
    $st->bind_param('s', $rel);
    $st->execute();
    $reg = $st->get_result()->fetch_row() !== null;
    $st->close();
    $ok($exists && $reg, $lbl . ' — ملفٌّ وصفٌّ في السجل');
}
/* وطابورُ الإغلاقِ **لا يكتب**: مسارا إقفالٍ يتفرّقان أسوأُ من شاشةٍ ناقصة */
$tc = (string) @file_get_contents($ROOT . '/Tickets/ticket_close.php');
$tcBody = preg_replace('~/\*.*?\*/~s', '', $tc);
$ok(stripos($tcBody, 'UPDATE ') === false && stripos($tcBody, 'INSERT ') === false
    && strpos($tcBody, "'stage'") === false,
    '   وطابورُ الإغلاقِ **يسرد ولا يكتب** — والإقفالُ بمسارِه الواحد',
    'حارسُ «من رفع البلاغَ لا يُقفله» يعيش في ticket_form — ونسخةٌ ثانيةٌ تتخطّاه');
$ok(strpos($tcBody, 'ticket_form.php?id=') !== false,
    '   ويقود إلى ذلك المسار');
/* وسجلُّ الاطّلاعِ قارئٌ محضٌ — سجلُّ تدقيقٍ يُحرَّر من شاشتِه ليس سجلَّ تدقيق */
$rl = (string) @file_get_contents($ROOT . '/Governance/read_log.php');
$rlBody = preg_replace('~/\*.*?\*/~s', '', $rl);
$ok(stripos($rlBody, 'UPDATE sensitive_read_log') === false
    && stripos($rlBody, 'DELETE FROM sensitive_read_log') === false
    && stripos($rlBody, 'INSERT INTO sensitive_read_log') === false,
    '   وسجلُّ الاطّلاعِ **قارئٌ محضٌ** — لا يُصحَّح من شاشتِه');

/* ══ ⑦ شاشاتُ القراءةِ الستُّ — على بيانٍ حيٍّ لا على نموذجٍ مخترَع ══════════ */
$say('── شاشاتُ القراءة (6)');
$LIST = array(
    'Fleet/readiness_cert.php'             => array('readiness_lines', 15),
    'Procurement/warehouses.php'           => array('proc_warehouse', 25),
    'Operations/monthly_plan.php'          => array('scr_op_monthly', 15),
    'Suppliers/quota_approval_minutes.php' => array('substitute_coverages', 2),
    'Tickets/ticket_kpi.php'               => array('tickets', 24),
    'Tickets/my_tickets.php'               => array('tickets', 24),
);
$lMiss = array(); $lUnreg = array(); $lWrite = array();
foreach ($LIST as $rel => $m) {
    if (!is_file($ROOT . '/' . $rel)) { $lMiss[] = basename($rel); continue; }
    $st = $conn->prepare('SELECT id FROM modules WHERE code = ? LIMIT 1');
    $st->bind_param('s', $rel);
    $st->execute();
    if ($st->get_result()->fetch_row() === null) { $lUnreg[] = basename($rel); }
    $st->close();
    /* قارئةٌ محضةٌ — والتعليقاتُ تُنزع فلا يُطابَق شرحي على نفسِه */
    $body = preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents($ROOT . '/' . $rel));
    if (preg_match('~\b(INSERT\s+INTO|UPDATE\s+`?\w+`?\s+SET|DELETE\s+FROM)\b~i', $body)) {
        $lWrite[] = basename($rel);
    }
}
$ok(!$lMiss, 'ملفاتُ شاشاتِ القراءةِ الستُّ موجودة', 'غائبٌ: ' . implode(',', $lMiss));
$ok(!$lUnreg, '   وكلُّها مسجَّلةٌ في `modules`', 'غيرُ مسجَّلٍ: ' . implode(',', $lUnreg));
$ok(!$lWrite, '   **وكلُّها قارئةٌ محضة** — لا فعلَ كاتبًا',
    'تكتب: ' . implode(',', $lWrite) . ' — فمسارُ كتابةٍ ثانٍ يتفرّق عن مسارِ وحدتِه');

/* وجدولُ كلٍّ **حيٌّ ومسؤولٌ عنه استعلامٌ يعمل** — فشاشةٌ على عمودٍ وهميٍّ
   تُصيَّر خاويةً وتبدو سليمة. */
$deadTable = array();
foreach ($LIST as $rel => $m) {
    $r = $conn->query('SELECT COUNT(*) FROM `' . $m[0] . '` WHERE company_id = ' . $CO);
    if ($r === false) { $deadTable[] = $m[0]; }
}
$ok(!$deadTable, '   وجداولُها حيّةٌ ونطاقُ الشركةِ يعمل عليها',
    'تعذّر: ' . implode(',', $deadTable));

/* ── وخمسٌ **لم تُبنَ عمدًا** — يُصرَّح بها ولا تُلفَّق ────────────────────────
     `disposal` · `fin_exit` · `fin_variance` · `fin_idle` · `site_approval`:
     جُسَّت القاعدةُ فلا جدولَ لأيٍّ منها ولا لمرادفاتِها. وبناؤها اختراعُ نموذجِ
     بيانات — وسابقتُه INJ-0416 الموقوفُ بوسمِ «مصدرٌ مفقود». */
$NOMODEL = array('equipment_disposals', 'asset_disposals', 'fin_ownership_exits',
                 'site_approvals', 'site_approval_minutes');
$appeared = array();
foreach ($NOMODEL as $t) {
    $r = $conn->query("SHOW TABLES LIKE '{$t}'");
    if ($r && $r->num_rows) { $appeared[] = $t; }
}
$ok(!$appeared,
    '**وخمسُ شاشاتٍ لم تُبنَ**: لا جدولَ لنموذجِها — يُصرَّح ولا يُلفَّق',
    'ظهر جدولٌ (' . implode(',', $appeared) . ') — فالسببُ المُعلَنُ لم يعد صادقًا وتُبنى الآن');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
