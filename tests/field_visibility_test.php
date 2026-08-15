<?php
/**
 * tests/field_visibility_test.php — شاهدُ حجبِ الحقلِ الحسّاسِ في الاستجابة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0159
 *
 * **نصُّ اختبارِ القبولِ حرفيًّا** (السجلُّ الجامع · «حسابات الموردين البنكية»):
 *   «حسابٌ بلا منحةٍ لا يرى الشاشةَ ولا يستطيع الإضافة؛ والمخوَّلُ يرى IBAN
 *    **مقنَّعًا إلا بمنحةٍ فردية**؛ وكلُّ اطّلاعٍ يكتب صفًّا في سجل الاطّلاع.»
 *
 * ── والقياسُ على **جسمِ الاستجابةِ الخام** لا على الشاشةِ المعروضة ───────────
 * إخفاءٌ بأسلوبِ عرضٍ ليس منعًا: القيمةُ تبقى في المصدرِ يقرؤها كلُّ من فتح
 * «عرض المصدر»، ويحملها كلُّ تصدير. فالشرطُ: **غائبةٌ نصًّا** (CS-10).
 *
 * ── والطرفانِ يُقاسان ────────────────────────────────────────────────────────
 *   ① بلا منحةٍ ⇒ الخليةُ محجوبةٌ و**القيمةُ غيرُ موجودةٍ في البايتات**.
 *   ② بمنحةٍ فرديةٍ ⇒ القيمةُ تظهر **ويُكتب سطرُ اطّلاع**.
 * فحجبٌ للجميعِ ليس حجبًا بل عطلٌ — والمنحةُ يجب أن تفتح.
 *
 * ◆ والمنحةُ تُزرع وتُكنس: هي **قرارُ مالكِ نطاقٍ** في الإنتاج، فالفاحصُ يصنعها
 *   لنفسِه ويُزيلها — ولا يترك النظامَ مفتوحًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
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

$ELEMENT = 'supplier.bank_account';
$sweep = function () use ($conn, $ELEMENT) {
    $n = 0;
    foreach (array(
        "DELETE FROM visibility_keys WHERE element_code = '" . $ELEMENT . "'
           AND reason LIKE '%FIELDVISPROBE%'",
        "DELETE FROM sensitive_read_log WHERE context LIKE '%supplier_bank%'
           AND subject_type = 'supplier_bank' AND result = 'allowed'
           AND at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)",
        "DELETE FROM cmp03_screen_rows WHERE canonical_file = 'supplier_bank.php'
           AND payload LIKE '%FIELDVISPROBE%'",
    ) as $q) { if ($conn->query($q)) { $n += $conn->affected_rows; } }
    return $n;
};
$pre = $sweep();
$say('══ INJ-0159 · الحقلُ الحسّاسُ غائبٌ نصًّا بلا منحة'
     . ($pre ? "  (كُنس {$pre} من جولةٍ سابقة)" : ''));

/* ── ① العنصرُ مسجَّلٌ في القاموس — وإلا فالحجبُ عطلٌ لا حكم ─────────────────── */
$inDict = false;
$st = $conn->prepare('SELECT active FROM portal_elements WHERE element_code = ? LIMIT 1');
$st->bind_param('s', $ELEMENT);
$st->execute();
$x = $st->get_result()->fetch_assoc();
$st->close();
$inDict = ($x && (int) $x['active'] === 1);
$ok($inDict, "عنصرُ `{$ELEMENT}` مسجَّلٌ وفعّالٌ في قاموسِ الظهور — فالمنحُ ممكن");

/* ── ② الشاشةُ تستشير الحاكمَ قبل الطباعة ─────────────────────────────────── */
$scr = (string) @file_get_contents($ROOT . '/Suppliers/supplier_bank.php');
$ok(strpos($scr, 'ems_may_see_field') !== false && strpos($scr, '$SENSITIVE_COLS') !== false,
    'وشاشةُ الحساباتِ البنكيةِ تستشير الحاكمَ قبل طباعةِ الخلية');
$hlp = (string) @file_get_contents($ROOT . '/includes/field_visibility.php');
$ok(strpos($hlp, 'VisibilityPolicyService') !== false,
    'والقرارُ مُفوَّضٌ لآليةِ الظهورِ القائمةِ — لا محرّكَ ثانٍ');
$ok(strpos($hlp, "\$allowed = false;") !== false && strpos($hlp, 'الافتراضُ يحمي') !== false,
    '**ومغلقٌ افتراضًا**: فشلُ القرارِ حجبٌ لا كشف');

/* ── ③ حسابٌ يرى الشاشةَ — والقياسُ عليه ────────────────────────────────── */
$user = ''; $uid = 0; $role = 0;
$q = $conn->query("SELECT rp.role_id FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                    WHERE m.code = 'Suppliers/supplier_bank.php' AND rp.can_view = 1 ORDER BY rp.role_id");
while ($q && ($rr = $q->fetch_row())) {
    $st = $conn->prepare("SELECT id, username FROM users WHERE role = ? AND company_id = ?
                           AND username <> '' ORDER BY id LIMIT 1");
    $rs = (string) $rr[0];
    $st->bind_param('si', $rs, $CO);
    $st->execute();
    $c = $st->get_result()->fetch_assoc();
    $st->close();
    if ($c) { $user = (string) $c['username']; $uid = (int) $c['id']; $role = (int) $rr[0]; break; }
}
$ok($user !== '', "ووُجد حسابٌ يرى الشاشةَ ({$user} · دور {$role})");
if ($user === '') { $say(''); $say("PASS={$PASS} · FAIL={$FAIL}"); exit(1); }

/* قيمةُ IBAN حقيقيةٌ من القاعدةِ — فالغيابُ يُقاس على نصٍّ موجودٍ لا مفترَض */
/* ◆ العمودُ `canonical_file` لا `canonical_screen` — أوّلُ صياغةٍ سألت العمودَ
     الخطأَ فعادت بصفرِ صفٍّ، فمرَّ شرطُ الغيابِ **خاويًا** (`$ibanVal === ''`
     يُصدِّق أيَّ شيء). وهو فخُّ «فاحصٌ يمرُّ بلا مفحوص» — فصار وجودُ القيمةِ
     شرطًا لازمًا لا مجرّدَ تحسين. */
/* ◆ ولا تُؤخذ قيمةٌ قائمةٌ من بياناتِ التجربة: قِيس أنَّ صفوفَها تحمل **القيمةَ
     نفسَها** في IBAN والبنكِ والفرع («دوري 2» في الثلاثة) — فغيابُ الحقلِ
     الحسّاسِ لا يُقاس بنصٍّ يظهر في عمودٍ غيرِ حسّاسٍ بحق. فيُزرع صفٌّ بقيمةٍ
     **فريدةٍ لا تتكرّر في عمودٍ آخر**، ويُكنس بعائلةِ وسمِه. */
/* ◆ INJ-0151: صارت الشاشةُ تقرأ **المصدرَ الموثَّقَ** `suppliers.bank_*` لا
     المخزنَ البينيّ. فالبذرُ يقع حيث تقرأ — وإلا قاس الفاحصُ حجبَ ما لا يُعرض.
     (الحكمُ المحروسُ لم يتغيّر: قيمةٌ حسّاسةٌ لا تعبر الشبكةَ بلا منحة.) */
$ibanVal = 'FIELDVISPROBE-IBAN-' . date('His');
$seedId = 0;
$__sup = 0;
$__r = $conn->query("SELECT id FROM suppliers WHERE company_id = {$CO} ORDER BY id LIMIT 1");
if ($__r && ($__x = $__r->fetch_row())) { $__sup = (int) $__x[0]; }
$__snapBank = null;
if ($__sup > 0) {
    $__r = $conn->query("SELECT bank_name, bank_account_no, bank_iban, bank_doc_ref
                           FROM suppliers WHERE id = {$__sup}");
    $__snapBank = $__r ? $__r->fetch_assoc() : null;
    $st = $conn->prepare("UPDATE suppliers
            SET bank_name = 'بنكُ فاحصٍ ع', bank_account_no = ?, bank_iban = ?,
                bank_doc_ref = 'FIELDVISPROBE-DOC'
          WHERE id = ?");
    if ($st) {
        $acc = 'FIELDVISPROBE-ACC-' . date('His');
        $st->bind_param('ssi', $acc, $ibanVal, $__sup);
        if ($st->execute()) { $seedId = $__sup; }
        $st->close();
    }
}
/* واستعادةُ حسابِ المورّدِ الأصليِّ من اللقطةِ في الكنسِ البعديّ */
register_shutdown_function(function () use ($conn, $__sup, $__snapBank) {
    if ($__sup <= 0 || !is_array($__snapBank)) { return; }
    $st = $conn->prepare('UPDATE suppliers SET bank_name = ?, bank_account_no = ?,
                                 bank_iban = ?, bank_doc_ref = ? WHERE id = ?');
    if (!$st) { return; }
    $st->bind_param('ssssi', $__snapBank['bank_name'], $__snapBank['bank_account_no'],
        $__snapBank['bank_iban'], $__snapBank['bank_doc_ref'], $__sup);
    $st->execute();
    $st->close();
});
$ok($seedId > 0, "زُرع صفٌّ بقيمةِ IBAN **فريدةٍ** يُقاس غيابُها (#{$seedId})", $conn->error);
if ($seedId === 0) { $say(''); $say("PASS={$PASS} · FAIL={$FAIL}"); exit(1); }

$jar = sys_get_temp_dir() . '/fieldvis_' . getmypid() . '.txt';
$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch);
    curl_close($ch);
    return $b;
};
$login = function () use ($http, $BASE, $user, $jar) {
    @unlink($jar);
    $b = $http($BASE . '/login.php');
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b, $t);
    $r = $http($BASE . '/login.php', http_build_query(array(
        'username' => $user, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
    return mb_strpos($r, 'name="password"') === false;
};

/* ── ④ الطرفُ الأول: **بلا منحة** ⇒ غائبةٌ نصًّا ──────────────────────────── */
$ok($login(), 'ودخل');
$bare = $http($BASE . '/Suppliers/supplier_bank.php');
$withheld = preg_match_all('~ems-field-withheld~', $bare);
$ok($withheld > 0, "الخلايا المحجوبةُ ظاهرةٌ بعلامتِها ({$withheld})");
$ok(mb_strpos($bare, $ibanVal) === false,
    '**وقيمةُ IBAN غائبةٌ من بايتاتِ الاستجابة** — لا مخفيّةً بأسلوبِ عرض');

/* ── ⑤ الطرفُ الثاني: **بمنحةٍ فردية** ⇒ تظهر ويُسجَّل الاطّلاع ─────────────── */
$logBefore = 0;
$r = $conn->query("SELECT COUNT(*) FROM sensitive_read_log WHERE person_id = {$uid}");
if ($r) { $logBefore = (int) $r->fetch_row()[0]; }

$grant = $conn->prepare("INSERT INTO visibility_keys
        (company_id, element_code, scope_type, scope_id, mode, reason, granted_by, granted_at)
        VALUES (?, ?, 'account', ?, 'open', 'FIELDVISPROBE — منحةُ فاحصٍ تُكنس', ?, NOW())");
$granted = false;
if ($grant) {
    $sid = (string) $uid;
    $grant->bind_param('issi', $CO, $ELEMENT, $sid, $uid);
    $granted = $grant->execute();
    $grant->close();
}
$ok($granted, 'زُرعت منحةٌ فرديةٌ لهذا الحساب', $conn->error);

/* الجلسةُ تُعاد — فقرارُ الظهورِ محفوظٌ في الذاكرةِ داخلَ الطلبِ الواحد */
$ok($login(), 'وأُعيد الدخولُ — فالقرارُ يُحسب من جديد');
$withGrant = $http($BASE . '/Suppliers/supplier_bank.php');
$ok(mb_strpos($withGrant, $ibanVal) !== false,
    '**وبالمنحةِ تظهر القيمةُ في الاستجابة** — فالحجبُ حكمٌ لا عطل');
$logAfter = 0;
$r = $conn->query("SELECT COUNT(*) FROM sensitive_read_log WHERE person_id = {$uid}");
if ($r) { $logAfter = (int) $r->fetch_row()[0]; }
$ok($logAfter > $logBefore,
    "**وكلُّ اطّلاعٍ مخوَّلٍ يكتب سطرًا في سجل الاطّلاع** ({$logBefore} ⇒ {$logAfter})");

@unlink($jar);
$post = $sweep();
$say("   كُنس ختامًا: {$post} منحةً");
$left = 0;
$r = $conn->query("SELECT COUNT(*) FROM visibility_keys WHERE element_code = '" . $ELEMENT . "'
                    AND reason LIKE '%FIELDVISPROBE%'");
if ($r) { $left = (int) $r->fetch_row()[0]; }
$seedLeft = 0;
$r = $conn->query("SELECT COUNT(*) FROM cmp03_screen_rows
                    WHERE canonical_file = 'supplier_bank.php' AND payload LIKE '%FIELDVISPROBE%'");
if ($r) { $seedLeft = (int) $r->fetch_row()[0]; }
$ok($left === 0 && $seedLeft === 0,
    "صفرُ منحةٍ وصفرُ صفٍّ مزروعٍ بعد الجولة (منح={$left} · صفوف={$seedLeft}) — فالنظامُ لا يبقى مفتوحًا");

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
