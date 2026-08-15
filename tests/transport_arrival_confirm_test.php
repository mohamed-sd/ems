<?php
/**
 * tests/transport_arrival_confirm_test.php — «تأكيدُ الوصول» يُكمَل بيدٍ حيّة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شاهدُ هجرةِ `2027_03_27_transport_arrival_confirm_grant`
 *
 * **العيبُ الذي يحرسه هذا الفاحص**: `Transport/transfer_arrival.php` كانت
 * ممنوحةً لدورٍ واحدٍ (23 «إدارة النقل والترحيل») بـ`can_view=1` **و`can_edit=0`**،
 * ومعالجُها يشترط `can_edit`. ولا دورَ آخرَ يملك `can_view` عليها. فالحصيلةُ
 * المقيسةُ حيًّا: **لا حسابَ في النظامِ كلِّه يستطيع تأكيدَ وصول** — الشاشةُ
 * تُصيّر نموذجًا كاملًا يُردُّ كلُّ إرسالٍ له بـ`GOV-PERM-403-WRITE` وصفرِ كتابة.
 *
 * ── ولماذا لا يكفي أن يُقاس صفُّ الصلاحيةِ في القاعدة ──────────────────────
 * صفُّ `role_permissions` **بناءٌ لا تبنٍّ** (MD-05). فيُقاس هنا ما يفعله
 * حسابٌ حيٌّ عبر HTTP من أوّلِ الشاشةِ إلى آخرِها: يدخل · يرى النموذج · يُرسل ·
 * فيُكتب المستند · **ويتقدَّم الأمرُ في الشاشةِ إلى بابِ الإقفال**.
 *
 * ── وما معنى «يتقدَّم» هنا بالضبط ─────────────────────────────────────────
 * ◆ گوتشا مقيسةٌ: `confirmDelivery()` **لا تمسُّ عمودَ `stage` إطلاقًا** — يبقى
 *   `arrived`. والانتقالُ إلى `closed` فعلُ شاشةٍ أخرى (`transfer_close_cost.php`).
 *   فمن يقيس التقدُّمَ بتغيُّرِ `stage` يقرأ فشلًا دائمًا والمنتجُ سليم.
 *   التقدُّمُ الحقيقيُّ **مقيسٌ على ثلاثةٍ معًا**: مستندٌ مخزَّن · وصفُّ الأمرِ في
 *   الشاشةِ ينقلب من نموذجٍ إلى «مُسلَّم» · **وينفتح بابُ الإقفال** (وهو الحارسُ
 *   الترتيبيُّ في `closeWithCost`: لا إقفالَ بلا مستندِ تسليمٍ مخزَّن).
 *
 * ◆ وگوتشا ثانيةٌ دفعت ثمنَها جولةٌ كاملة: `transfer_events.sync_uuid` **فريدٌ**
 *   وقيمتُه `dlv:<شركة>:<أمر>`. فجولةٌ تكنس المستندَ وتترك الواقعةَ تجعل
 *   الجولةَ التاليةَ تسقط في فرعِ التكرارِ فتُعلن «سُجِّل سلفًا» **وقد كتبت صفرَ
 *   مستند**. الكنسُ يشمل الاثنين — وإلا صار الفاحصُ نفسُه صانعَ الثغرة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$CO   = 4;
$BASE = 'http://localhost/ems';
$SCREEN = 'Transport/transfer_arrival.php';
$ROLE   = 23;
/** وسمُ العائلةِ — يُكنس بالعائلةِ لا بالجولة، فلا تعمى جولةٌ عمّا تركته سابقتُها. */
$MARK   = 'ARRGRANT';
$mine   = $MARK . '-' . getmypid();

$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ تأكيدُ الوصول: يدٌ حيةٌ تُكمل الدورةَ من الشاشةِ إلى المستندِ إلى بابِ الإقفال');

/* ══ ① الحوكمةُ موصولةٌ — ويُقاس الحدَّان: تُمنَح لصاحبِها ولا تتسرَّب ══════ */
$say('── ① صفُّ الصلاحيةِ في القاعدة');
$mid = 0;
$st = $conn->prepare('SELECT id FROM modules WHERE code = ? LIMIT 1');
$st->bind_param('s', $SCREEN); $st->execute();
if ($x = $st->get_result()->fetch_row()) { $mid = (int) $x[0]; }
$st->close();
$ok($mid > 0, "الوحدةُ مسجَّلةٌ في `modules` (#{$mid})");

$row = null;
if ($mid > 0) {
    $st = $conn->prepare('SELECT can_view, can_add, can_edit, can_delete FROM role_permissions
                           WHERE module_id = ? AND role_id = ?');
    $st->bind_param('ii', $mid, $ROLE); $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
}
$ok($row && (int) $row['can_view'] === 1 && (int) $row['can_edit'] === 1,
    "**ودورُ النقلِ ({$ROLE}) يملك العرضَ والكتابة** — وهو عينُ ما كان مفقودًا",
    $row ? "view={$row['can_view']} edit={$row['can_edit']}" : 'لا صفَّ صلاحيةٍ إطلاقًا');

// ولا يتسرَّب المنحُ إلى غيرِ صاحبِه — توسيعُ إذنٍ صامتٌ عيبٌ كالنقصِ سواءً.
$leak = 0;
if ($mid > 0) {
    $r = $conn->query("SELECT COUNT(*) FROM role_permissions
                        WHERE module_id = {$mid} AND role_id <> {$ROLE} AND can_edit = 1");
    if ($r && ($x = $r->fetch_row())) { $leak = (int) $x[0]; }
}
$ok($leak === 0, "ولم يتسرَّب المنحُ إلى دورٍ آخر ({$leak})");

/* ══ ② حسابٌ حيٌّ يدخل — لا يُفترض وجودُه ═══════════════════════════════════ */
$say('── ② الحسابُ الحيّ');
$jar = sys_get_temp_dir() . '/arrgrant_' . getmypid() . '.txt';
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
$who = ''; $whoId = 0;
$st = $conn->prepare("SELECT id, username FROM users WHERE role = ? AND company_id = ? AND username <> '' ORDER BY id");
$rs = (string) $ROLE; $st->bind_param('si', $rs, $CO); $st->execute();
$res = $st->get_result();
while ($res && ($x = $res->fetch_assoc())) {
    if ($login((string) $x['username'])) { $who = (string) $x['username']; $whoId = (int) $x['id']; break; }
}
$st->close();
$ok($who !== '', "دخل حسابُ دورِ النقل ({$who} · #{$whoId})");

/* ══ ③ أمرٌ قابلٌ للتأكيد — يُزرَع ولا يُستهلك أمرُ عملٍ حقيقي ══════════════
     ◆ حارسُ دخولٍ صريح: لو تعذّر الزرعُ **يُعلَن فشلًا** لا يُتخطّى بصمت —
       فجولةٌ لم تقس شيئًا تُقرأ خضراءَ وهي لم تلمس المسار. */
$say('── ③ أمرٌ واصلٌ بلا مستند (مزروعٌ لهذه الجولة)');
$plant = $conn->query(
    "INSERT INTO transfer_orders
        (company_id, order_no, transfer_type_id, direction, source_module,
         from_location_id, to_location_id, request_date, stage, arrival_datetime,
         created_by, is_deleted)
     SELECT company_id, '{$mine}', transfer_type_id, direction, source_module,
            from_location_id, to_location_id, CURDATE(), 'arrived', NOW(), 0, 0
       FROM transfer_orders
      WHERE company_id = {$CO} AND is_deleted = 0
      ORDER BY id LIMIT 1");
$oid = $plant ? (int) $conn->insert_id : 0;
$ok($oid > 0, "زُرع أمرٌ واصلٌ للفحص (#{$oid} · {$mine})", $conn->error);

if ($oid > 0 && $who !== '') {
    /* ══ ④ الشاشةُ تعرض النموذجَ لهذا الأمرِ بعينِه ════════════════════════ */
    $say('── ④ الشاشةُ والإرسال');
    $page = $http($BASE . '/Transport/transfer_arrival.php');
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $page, $ct);
    $tok = isset($ct[1]) ? $ct[1] : '';
    $ok(strpos($page, 'value="' . $oid . '"') !== false,
        'والشاشةُ تعرض نموذجَ التأكيدِ لهذا الأمرِ بعينِه');

    $before = 0;
    $r = $conn->query("SELECT COUNT(*) FROM transfer_delivery_docs WHERE order_id = {$oid}");
    if ($r && ($x = $r->fetch_row())) { $before = (int) $x[0]; }

    $idem = $mine . '-send';
    $body = http_build_query(array(
        'csrf_token' => $tok, 'deliver_id' => $oid, 'witness_name' => 'شاهدُ فاحصٍ',
        'delivery_note' => $mine, 'ems_idem' => $idem));
    $r1 = $http($BASE . '/Transport/transfer_arrival.php', $body);

    /* رسالةُ هذه الشاشةِ **مُصيَّرةٌ في جسمِ الصفحةِ** (`alert alert-info`) لا في
       قناةِ الوميضِ — فتُقرأ من موضعِها لا من `"text":` ولا من `?msg=`. */
    $msg = '';
    if (preg_match('~<div class="alert alert-info">(.*?)</div>~s', $r1, $am)) {
        $msg = trim(html_entity_decode(strip_tags($am[1]), ENT_QUOTES, 'UTF-8'));
    }

    /* ══ ⑤ الحكمُ الأول: الحجبُ زال ═════════════════════════════════════════ */
    $ok(mb_strpos($r1, 'GOV-PERM-403-WRITE') === false,
        '**ولم يُردَّ بـ`GOV-PERM-403-WRITE`** — وهو الانحدارُ الذي يحرسه هذا الفاحص',
        mb_substr($msg, 0, 120));

    /* ══ ⑥ الحكمُ الثاني: مستندٌ واحدٌ مخزَّنٌ بمحتواه ══════════════════════ */
    $doc = null;
    $r = $conn->query("SELECT id, doc_ref, doc_note, witness_name, delivered_at, created_by
                         FROM transfer_delivery_docs WHERE order_id = {$oid}");
    if ($r) { $doc = $r->fetch_assoc(); }
    $after = 0;
    $r = $conn->query("SELECT COUNT(*) FROM transfer_delivery_docs WHERE order_id = {$oid}");
    if ($r && ($x = $r->fetch_row())) { $after = (int) $x[0]; }
    $ok($after - $before === 1, "**وكُتب مستندُ تسليمٍ واحد** (قبل={$before} · بعد={$after})");
    $ok($doc && $doc['witness_name'] === 'شاهدُ فاحصٍ',
        'والشاهدُ مخزَّنٌ بنصِّه لا مُلقًى في جسمِ واقعة',
        $doc ? ('witness=' . $doc['witness_name']) : 'لا مستند');
    $ok($doc && (int) $doc['created_by'] === $whoId,
        "**ومنسوبٌ إلى الحسابِ الذي أرسله فعلًا** (#{$whoId}) — لا صفرًا ولا مجهولًا",
        $doc ? ('created_by=' . $doc['created_by']) : 'لا مستند');
    $ok($doc && $doc['doc_ref'] !== '', 'وله مرجعٌ يُستدعى به',
        $doc ? ('ref=' . $doc['doc_ref']) : 'لا مستند');
    $ok(mb_strpos($msg, 'وُثّق') !== false,
        'والشاشةُ تُعلن التوثيقَ بنصِّه', mb_substr($msg, 0, 140));

    /* ══ ⑦ الحكمُ الثالث: الأمرُ تقدَّم في الشاشةِ وانفتح بابُ الإقفال ═══════
         ◆ الحكمُ على **صفِّ الأمرِ نفسِه** لا على الصفحة: الصفحةُ فيها أوامرُ
           أخرى مُسلَّمةٌ تُعطي «مُسلَّم» و«أقفل» دائمًا، فالبحثُ في كلِّ الصفحةِ
           يُخضِرُّ الفاحصَ بلا أن يتقدَّم أمرُنا خطوةً واحدة. */
    $page2 = $http($BASE . '/Transport/transfer_arrival.php');
    $rowHtml = '';
    if (preg_match_all('~<tr>.*?</tr>~s', $page2, $trs)) {
        foreach ($trs[0] as $tr) {
            if (strpos($tr, 'value="' . $oid . '"') !== false
                || strpos($tr, 'transfer_close_cost.php?id=' . $oid) !== false) { $rowHtml = $tr; break; }
        }
    }
    $ok($rowHtml !== '', 'وصفُّ الأمرِ حاضرٌ في الشاشةِ بعد الإرسال');
    $ok($rowHtml !== '' && strpos($rowHtml, 'value="' . $oid . '"') === false,
        'ولم يعُد يعرض نموذجَ تأكيدٍ — فالخطوةُ استُهلكت');
    $ok($rowHtml !== '' && mb_strpos($rowHtml, 'مُسلَّم') !== false,
        '**وانقلبت لافتتُه إلى «مُسلَّم»**');
    $ok($rowHtml !== '' && strpos($rowHtml, 'transfer_close_cost.php?id=' . $oid) !== false,
        '**وانفتح بابُ الإقفالِ لهذا الأمر** — وهو التقدُّمُ الحقيقيُّ في هذه الدورة');

    // والحارسُ الترتيبيُّ في الخدمةِ يقرأ المستندَ نفسَه — فالبابُ مفتوحٌ فعلًا لا شكلًا.
    require_once $ROOT . '/app/Services/Transport/TransferDeliveryService.php';
    $svc = new \App\Services\Transport\TransferDeliveryService($conn);
    $gate = $svc->deliveryDocOf($CO, $oid);
    $ok($gate !== null,
        'وحارسُ «لا إقفالَ بلا مستند» يجد سندَه — فالإقفالُ صار ممكنًا');

    /* ══ ⑧ ولا يُكتب مرتين: الإعادةُ بالمفتاحِ نفسِه ════════════════════════ */
    $say('── ⑤ الإعادةُ لا تُضاعف');
    $r2 = $http($BASE . '/Transport/transfer_arrival.php', $body);
    $again = 0;
    $r = $conn->query("SELECT COUNT(*) FROM transfer_delivery_docs WHERE order_id = {$oid}");
    if ($r && ($x = $r->fetch_row())) { $again = (int) $x[0]; }
    $ok($again === 1, "**ولم يُكتب مستندٌ ثانٍ بالمفتاحِ نفسِه** ({$again})");

    /* ── گوتشا مفتوحةٌ تُعلَن ولا تُبتلع (لا تُفشِل الجولة) ─────────────────
         `transfer_events.event_type` تعدادٌ لا يضمُّ `delivered`، و`sql_mode`
         خالٍ — فالواقعةُ تُكتب بنوعٍ **فارغٍ** بتحذيرٍ 1265 لا خطأ. والأثرُ:
         عمودُ `delivered` في استعلامِ الشاشةِ (`event_type = 'delivered'`)
         **لا يطابق أبدًا**. خارجُ نطاقِ هذه الهجرة — ويُعلَن ليُقرَّر فيه. */
    $evType = null;
    $r = $conn->query("SELECT event_type FROM transfer_events
                        WHERE order_id = {$oid} AND sync_uuid = 'dlv:{$CO}:{$oid}'");
    if ($r && ($x = $r->fetch_row())) { $evType = (string) $x[0]; }
    if ($evType !== null && $evType !== 'delivered') {
        $say("   ⚠ گوتشا مفتوحةٌ: واقعةُ التسليمِ كُتبت بنوعٍ «{$evType}» لا «delivered»"
           . " — تعدادُ `transfer_events.event_type` لا يضمُّه (تحذير 1265).");
    }

    /* ══ ⑨ الكنسُ بالعائلةِ — ويُفحَص مُرجَعُ كلِّ حذفٍ ═══════════════════════
         الواقعةُ تُحذف مع الأمرِ بـ`ON DELETE CASCADE`، أمَّا المستندُ فلا قيدَ
         له على `transfer_orders` — فيُحذف صراحةً وإلا بقي يتيمًا. */
    $say('── ⑥ الكنس');
    $conn->query("DELETE FROM transfer_delivery_docs WHERE doc_note LIKE '{$MARK}%'");
    $sweptDocs = $conn->affected_rows;
    $conn->query("DELETE FROM transfer_orders WHERE company_id = {$CO} AND order_no LIKE '{$MARK}%'");
    $sweptOrders = $conn->affected_rows;
    $conn->query("DELETE FROM ems_post_idempotency
                   WHERE action_code = 'trs.transfer.confirm_delivery'
                     AND created_at >= NOW() - INTERVAL 10 MINUTE");
    $sweptIdem = $conn->affected_rows;
    $say("   كُنس: {$sweptDocs} مستندًا · {$sweptOrders} أمرًا · {$sweptIdem} ختمًا");
    /* ◆ الحكمُ على **الأمرِ المزروعِ وحدَه**: هو الشيءُ الوحيدُ المضمونُ وجودُه
         في كلِّ جولةٍ — أمَّا المستندُ فلا يُكتب حين يُحجَب الفعل، فاشتراطُ
         حذفِه يُفشل الجولةَ الحمراءَ **مرتين**: مرةً بالحجبِ الحقيقيِّ ومرةً
         بكنسٍ لم يجد ما يكنس. وخلوُّ العائلةِ أدناه هو ضمانُ المستندِ لا هذا. */
    $ok($sweptOrders >= 1,
        "ونجح حذفُ الأمرِ المزروعِ فعلًا — لا رفضَ قيدٍ صامت ({$sweptOrders})");

    $leftDoc = 0; $leftOrd = 0; $leftEv = 0;
    $r = $conn->query("SELECT COUNT(*) FROM transfer_delivery_docs WHERE doc_note LIKE '{$MARK}%'");
    if ($r && ($x = $r->fetch_row())) { $leftDoc = (int) $x[0]; }
    $r = $conn->query("SELECT COUNT(*) FROM transfer_orders WHERE order_no LIKE '{$MARK}%'");
    if ($r && ($x = $r->fetch_row())) { $leftOrd = (int) $x[0]; }
    $r = $conn->query("SELECT COUNT(*) FROM transfer_events WHERE sync_uuid = 'dlv:{$CO}:{$oid}'");
    if ($r && ($x = $r->fetch_row())) { $leftEv = (int) $x[0]; }
    $ok($leftDoc === 0 && $leftOrd === 0 && $leftEv === 0,
        "صفرُ ثغرةٍ من عائلةِ الوسمِ بعد الجولة (مستند={$leftDoc} · أمر={$leftOrd} · واقعة={$leftEv})");
}
@unlink($jar);

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
