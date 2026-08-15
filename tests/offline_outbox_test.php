<?php
/**
 * tests/offline_outbox_test.php — شاهدُ صندوقِ الإرسالِ دونَ اتصال
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0378 · INJ-0548
 *
 * **العيب**: شاشاتُ الميدانِ تعرض شارةَ مزامنةٍ تقرأ `navigator.onLine` وحسب.
 * ومسحُ المستودعِ كلِّه يعيد **صفرًا** لـ`localStorage`/`indexedDB`/service
 * worker — فالشارةُ تجميليةٌ و«الحفظُ قبل الشبكة» وعدٌ بلا آلة. وتأكيدُ الوصولِ
 * من موقعٍ بلا شبكةٍ **يضيع**.
 *
 * **المبنيّ** ثلاثةٌ لا يُغني بعضُها عن بعض:
 *   ① طابورٌ في `IndexedDB` للنماذجِ الموسومةِ **صراحةً** (اعتراضٌ عامٌّ يحوّل
 *      عطلَ شبكةٍ إلى كتابةٍ مؤجَّلةٍ لم يطلبها أحد).
 *   ② **مفتاحُ عطالةٍ من العميل** يُولَّد مرةً ويُعاد كما هو.
 *   ③ عاملُ خدمةٍ يخزّن **الأصولَ الساكنةَ وحدَها** — صفحةُ PHP محفوظةٌ ببياناتٍ
 *      قديمةٍ تُقرأ حاضرةً، وذلك أخطرُ من غيابِ الصفحة.
 *
 * ── والشقُّ الحاسمُ يُقاس على الخادمِ لا في المتصفح ─────────────────────────
 * «يُرسَل مرةً واحدةً بلا تكرار» حكمٌ على **الكتابة**. فيُرسَل الطلبُ نفسُه مرتين
 * بالمفتاحِ نفسِه ويُعَدُّ ما كُتب: مرةً واحدةً لا مرتين. وهذا وحدَه ما يثبت
 * الوعد — أمَّا وجودُ ملفِّ الطابورِ فبناءٌ لا تبنٍّ (MD-05).
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
$say('══ INJ-0378 · INJ-0548 · الحفظُ دونَ اتصالٍ وإرسالٌ مرةً واحدة');

/* ── ① الآلةُ مبنيةٌ ومُنادًى بها ──────────────────────────────────────────── */
$box = (string) @file_get_contents($ROOT . '/assets/js/ems-outbox.js');
$ok($box !== '', 'صندوقُ الإرسالِ مبنيٌّ (' . strlen($box) . ' بايت)');
$ok(strpos($box, "window.indexedDB.open") !== false, 'وطابورُه في `IndexedDB` — يعبر إغلاقَ اللسان');
$ok(strpos($box, "form[data-ems-outbox=\"1\"]") !== false,
    '**ويعمل على الموسومِ صراحةً وحدَه** — لا اعتراضَ عامًّا لكلِّ نموذج');
$ok(strpos($box, "window.addEventListener('online'") !== false, 'ويصرّف عند عودةِ الاتصال');
$ok(strpos($box, 'بانتظار المزامنة') !== false, 'ويُعلن «بانتظار المزامنة» بعددِه');
$ok(strpos($box, 'if (r.status === 409)') !== false
    && strpos($box, 'if (res.conflict) { paint(0, rec.label') !== false,
    '**والتعارُضُ (409) يُعرض ولا يُحذف** — القرارُ للمستخدمِ لا للآلة');
$sw = (string) @file_get_contents($ROOT . '/sw.js');
$ok($sw !== '', 'وعاملُ الخدمةِ موجودٌ — كان صفرًا في المستودعِ كلِّه');
$ok(strpos($sw, "req.method !== 'GET'") !== false && strpos($sw, 'isStaticAsset') !== false,
    'ويخزّن الأصولَ الساكنةَ وحدَها لا صفحاتِ PHP');
$head = (string) file_get_contents($ROOT . '/inheader.php');
$ok(strpos($head, 'ems-outbox.js') !== false && strpos($head, "serviceWorker.register('/ems/sw.js'") !== false,
    '**والقشرةُ تُحمّلهما** — فالبناءُ ليس تبنّيًا');

/* ── ② الشاشتانِ المعنيّتانِ موسومتان ─────────────────────────────────────── */
$ts = (string) file_get_contents($ROOT . '/Timesheet/timesheet.php');
$tr = (string) file_get_contents($ROOT . '/Transport/transfer_arrival.php');
$ok(strpos($ts, 'data-ems-outbox="1"') !== false, 'ونموذجُ إدخالِ الورديةِ موسومٌ');
$ok(strpos($tr, 'data-ems-outbox="1"') !== false, 'ونموذجُ «وصلت» موسومٌ');
$ok(strpos($tr, "'cli'   => trim((string) (\$_POST['ems_idem'] ?? ''))") !== false,
    'ومفتاحُ العميلِ داخلٌ في عطالةِ عقدِ الوصول');
$ok(strpos($ts, "\$__tsIdemKey = sha1('ts.entry.save|'") !== false,
    'ومختومٌ في مسارِ حفظِ التايم شيت');
$ok(strpos($ts, "ems_pc_idem_mark(\$conn, \$__tsIdemKey") !== false
    && strpos($ts, 'يُختم المفتاحُ **بعد** نجاحِ الحفظ') !== false,
    '**والختمُ بعد نجاحِ الحفظِ لا قبلَه** — فختمٌ سابقٌ يبتلع إدخالًا فشل');

/* ── ③ القياسُ الحاسم: الطلبُ نفسُه مرتين ⇒ كتابةٌ واحدة ────────────────────── */
$jar = sys_get_temp_dir() . '/outbox_' . getmypid() . '.txt';
$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 120));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch); curl_close($ch);
    return $b;
};
$login = function ($user) use ($jar, $BASE, $http) {
    @unlink($jar);
    $b = $http($BASE . '/login.php');
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b, $t);
    $r = $http($BASE . '/login.php', http_build_query(array(
        'username' => $user, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
    return mb_strpos($r, 'name="password"') === false;
};
$userOfRole = function ($role) use ($conn, $CO, $login) {
    $st = $conn->prepare("SELECT username FROM users WHERE role = ? AND company_id = ? AND username <> '' ORDER BY id");
    $r = (string) $role; $st->bind_param('si', $r, $CO); $st->execute();
    $res = $st->get_result();
    while ($res && ($x = $res->fetch_row())) { if ($login((string) $x[0])) { $st->close(); return (string) $x[0]; } }
    $st->close();
    return '';
};

/* ── القياسُ الحاسمُ على مسارِ التايم شيت ───────────────────────────────────
     ◆ ولماذا يُقاس شقُّ «مرةً واحدةً» هنا لا على شاشةِ الوصول: مفتاحُ العميلِ
       في التايم شيت يُختم **بعد نجاحِ الحفظ** فيُقاس الردُّ «مُطبَّقٌ سلفًا»
       مباشرةً؛ وشاشةُ الوصولِ لها عطالتُها المركَّبةُ في القاعدة (فريدُ
       `sync_uuid`) ويقيسها فاحصُها الخاصُّ من أوّلِ الشاشةِ إلى بابِ الإقفال.
     ◆ **وقد كان هذا الموضعُ يُثبت الحجبَ لا الإتمام**: القياسُ كشف أنَّ لا حسابَ
       في النظامِ كلِّه يستطيع تأكيدَ وصول — دورُ النقلِ (23) وحدَه يراها
       وصلاحيةُ تحريرِه صفر — فكان الردُّ `GOV-PERM-403-WRITE`. وقد **مُنحت
       الكتابةُ لصاحبِها** في هجرةِ `2027_03_27_transport_arrival_confirm_grant`،
       فصار المقيسُ هنا **إتمامَ الفعلِ لا إعلانَ منعِه**، والتفصيلُ في
       `tests/transport_arrival_confirm_test.php`. */
$u6 = $userOfRole(6);
$ok($u6 !== '', "دخل حسابُ إدارةِ الموقعِ — ويملك الكتابةَ في التايم شيت ({$u6})");
$tsIdem = 'obx-' . getmypid();
$tsKey = sha1('ts.entry.save|' . $CO . '|' . $tsIdem);
$conn->query("DELETE FROM ems_post_idempotency WHERE idem_key = '" . $conn->real_escape_string($tsKey) . "'");
$stampSt = $conn->prepare("INSERT IGNORE INTO ems_post_idempotency
                             (idem_key, action_code, actor_user_id, result_ref, created_at)
                           VALUES (?, 'ts.entry.save', 0, 'timesheet#0', NOW())");
$stampSt->bind_param('s', $tsKey);
$stamped = (bool) $stampSt->execute();
$stampSt->close();
$ok($stamped, 'وخُتم مفتاحُ إرسالٍ كأنَّه وصل مرةً — لمحاكاةِ إعادةِ الصندوق');

$tsBefore = 0;
$r = $conn->query('SELECT COUNT(*) FROM timesheet');
if ($r && ($x = $r->fetch_row())) { $tsBefore = (int) $x[0]; }
$tsPage = $http($BASE . '/Timesheet/timesheet.php?type=1');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $tsPage, $tc);
$replay = $http($BASE . '/Timesheet/timesheet.php?type=1', http_build_query(array(
    'csrf_token' => isset($tc[1]) ? $tc[1] : '', 'operator' => 'مشغّلُ فاحصٍ',
    'date' => date('Y-m-d'), 'shift' => '1', 'type' => '1', 'ems_idem' => $tsIdem)));
$tsAfter = 0;
$r = $conn->query('SELECT COUNT(*) FROM timesheet');
if ($r && ($x = $r->fetch_row())) { $tsAfter = (int) $x[0]; }
$ok(mb_strpos($replay, 'مُطبَّقٌ سلفًا') !== false,
    '**والإعادةُ بالمفتاحِ نفسِه تُعلَن «مُطبَّقٌ سلفًا»** لا تُكتب صامتةً',
    mb_substr(trim(strip_tags($replay)), 0, 120));
$ok($tsAfter === $tsBefore, "**ولم يُكتب صفٌّ ثانٍ** (قبل={$tsBefore} · بعد={$tsAfter})");
$conn->query("DELETE FROM ems_post_idempotency WHERE idem_key = '" . $conn->real_escape_string($tsKey) . "'");
$leftKey = 0;
$r = $conn->query("SELECT COUNT(*) FROM ems_post_idempotency WHERE idem_key = '" . $conn->real_escape_string($tsKey) . "'");
if ($r && ($x = $r->fetch_row())) { $leftKey = (int) $x[0]; }
$ok($leftKey === 0, "وكُنس الختمُ بعد الجولة ({$leftKey})");

$u23 = $userOfRole(23);
$ok($u23 !== '', "ودخل حسابُ النقل ({$u23})");
/* ◆ الشرطُ في الخدمةِ صريح: `stage === 'arrived'` **ولا مستندَ قائمٌ** — فأوّلُ
     صياغةٍ اختارت أمرًا «في الطريق» فردَّته الخدمةُ 409 وكُتب صفرُ مستندٍ، فقرأ
     الفاحصُ «لم يُكتب مرتين» وهو لم يكتب أصلًا. القياسُ يبدأ من حالةٍ صالحة. */
$oid = 0; $stageBefore = '';
$r = $conn->query("SELECT o.id, o.stage FROM transfer_orders o
                     LEFT JOIN transfer_delivery_docs d ON d.order_id = o.id
                    WHERE o.company_id = {$CO} AND o.stage = 'arrived' AND d.id IS NULL
                    ORDER BY o.id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $oid = (int) $x[0]; $stageBefore = (string) $x[1]; }
$ok($oid > 0, "ووُجد أمرٌ **واصلٌ بلا مستندٍ** — الحالةُ التي تقبل التأكيد (#{$oid})");

if ($oid > 0 && $u23 !== '') {
    $page = $http($BASE . '/Transport/transfer_arrival.php');
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $page, $ct);
    $tok = isset($ct[1]) ? $ct[1] : '';
    $ok(strpos($page, 'data-ems-outbox="1"') !== false, 'والنموذجُ الموسومُ يصل إلى المتصفح');

    $before = 0;
    $rr = $conn->query("SELECT COUNT(*) FROM transfer_delivery_docs WHERE order_id = {$oid}");
    if ($rr && ($x = $rr->fetch_row())) { $before = (int) $x[0]; }

    $idem = 'probe-' . getmypid() . '-' . $oid;
    $body = http_build_query(array(
        'csrf_token' => $tok, 'deliver_id' => $oid,
        'witness_name' => 'شاهدُ فاحصٍ', 'delivery_note' => 'OUTBOXPROBE',
        'ems_idem' => $idem));
    $r1 = $http($BASE . '/Transport/transfer_arrival.php', $body);
    $r2 = $http($BASE . '/Transport/transfer_arrival.php', $body);   /* الإعادةُ نفسُها */

    $after = 0;
    $rr = $conn->query("SELECT COUNT(*) FROM transfer_delivery_docs WHERE order_id = {$oid}");
    if ($rr && ($x = $rr->fetch_row())) { $after = (int) $x[0]; }
    $made = $after - $before;
    /* ◆ **الكتابةُ هنا كانت محجوبةً بالحوكمةِ لا بالعُدَّة** فصار يُقاس إتمامُها:
         كان الردُّ `GOV-PERM-403-WRITE` لأنَّ دورَ النقلِ — وهو الوحيدُ الذي يرى
         الشاشةَ — بلا صلاحيةِ تحرير، فكانت كتابةٌ صفريةٌ تُقرأ خطأً «لم يُكتب
         مرتين» وهي لم تكتب أصلًا. ومنذ هجرةِ المنحِ (2027_03_27) يُقاس الإتمامُ
         نفسُه: **إرسالان بالمفتاحِ نفسِه ⇒ مستندٌ واحدٌ لا صفرٌ ولا اثنان** —
         وهو أقوى من قياسِ الحجب، لأنه يُثبت الوعدَ بدلَ أن يُثبت تعذُّرَه. */
    $ok(mb_strpos($r1, 'GOV-PERM-403-WRITE') === false,
        '**ولم تعُد الكتابةُ محجوبةً** — زال `GOV-PERM-403-WRITE` عن شاشةِ الوصول',
        mb_substr(trim(strip_tags($r1)), 0, 100));
    $ok($made === 1,
        "**وأُتمَّ التأكيدُ فكُتب مستندٌ واحد** — لا صفرٌ (حجبٌ) ولا اثنان (تكرار) ({$made})",
        "قبل={$before} · بعد={$after}");
    $ok(strpos($page, 'data-ems-outbox-label') !== false,
        'ووسمُ الطابورِ على نموذجِ «وصلت» يبلغ المتصفحَ بعنوانِه');

    /* ── كنسُ ما زُرع: المستندُ **والواقعةُ** والحالةُ وأختامُ العطالة ────────
         ◆ گوتشا دفعت ثمنَها جولةٌ كاملة: `transfer_events.sync_uuid` **فريدٌ**
           وقيمتُه `dlv:<شركة>:<أمر>`. فكنسُ المستندِ وحدَه يترك الواقعةَ حيةً،
           فتسقط الجولةُ التاليةُ في فرعِ التكرارِ داخلَ الخدمةِ فتُعلن «سُجِّل
           التسليمُ سلفًا» **وقد كتبت صفرَ مستند** — فيُقرأ حجبٌ حيث لا حجب،
           ويُتَّهم المنتجُ بما جنته جولةٌ سابقة. يُكنس الاثنان معًا. */
    $conn->query("DELETE FROM transfer_delivery_docs
                   WHERE order_id = {$oid} AND doc_note = 'OUTBOXPROBE'");
    $swept = $conn->affected_rows;
    $conn->query("DELETE FROM transfer_events
                   WHERE company_id = {$CO} AND order_id = {$oid}
                     AND sync_uuid = 'dlv:{$CO}:{$oid}'");
    $sweptEv = $conn->affected_rows;
    $st2 = $conn->prepare('UPDATE transfer_orders SET stage = ? WHERE id = ? AND company_id = ?');
    $st2->bind_param('sii', $stageBefore, $oid, $CO);
    $st2->execute();
    $st2->close();
    $conn->query("DELETE FROM ems_post_idempotency
                   WHERE action_code = 'trs.transfer.confirm_delivery'
                     AND created_at >= NOW() - INTERVAL 5 MINUTE");
    $say("   كُنس ختامًا: {$swept} مستندًا · {$sweptEv} واقعةً · وأُعيدت الحالةُ إلى «{$stageBefore}»");
    $leftDoc = 0; $leftEv = 0;
    $rr = $conn->query("SELECT COUNT(*) FROM transfer_delivery_docs WHERE doc_note = 'OUTBOXPROBE'");
    if ($rr && ($x = $rr->fetch_row())) { $leftDoc = (int) $x[0]; }
    $rr = $conn->query("SELECT COUNT(*) FROM transfer_events WHERE sync_uuid = 'dlv:{$CO}:{$oid}'");
    if ($rr && ($x = $rr->fetch_row())) { $leftEv = (int) $x[0]; }
    $ok($leftDoc === 0 && $leftEv === 0,
        "صفرُ ثغرةٍ من عائلةِ الوسمِ بعد الجولة (مستند={$leftDoc} · واقعة={$leftEv})");
}
@unlink($jar);

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
