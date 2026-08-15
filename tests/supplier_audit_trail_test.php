<?php
/**
 * tests/supplier_audit_trail_test.php — شاهدُ INJ-0162
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0162
 *
 * **اختبارُ القبولِ نصًّا** (السجلُّ الجامع · «سجل الموردين / عقود الموردين»):
 *   «كلُّ تعديلٍ على مورد أو عقده أو **حسابه البنكي** يُنتج صفَّ تدقيقٍ
 *    **بالحقولِ المتغيرةِ وقيمتيها** والفاعلِ والوقت.»
 *
 * **والشقُّ المكسورُ كان البنكيَّ** — وهو أخطرُها: `Suppliers/supplier_bank.php`
 * يكتب عبر `cmp03_stage_insert` (السطر 102) وفي الملفِّ **صفرُ ذكرٍ للتدقيق**؛
 * وفي `cmp03_screen_rows` عشرةُ صفوفٍ للشاشةِ وفي `activity_logs` **لا صفَّ واحدٌ**
 * يخصُّها. فتغييرُ حسابٍ بنكيٍّ لموردٍ — وهو **باب تحويلِ المال** — كان بلا أثر.
 *
 * ── والعلاجُ في الطبقةِ لا في الشاشة ────────────────────────────────────────
 * `cmp03_store_update` و`cmp03_store_reverse` تُدقّقان و`cmp03_stage_insert` لا.
 * وترويسةُ `cmp03_store_audit` تقول نصًّا (CS-09): «التدقيقُ من **طبقةٍ مشتركةٍ
 * لا من الشاشة**». فأُصلحت الدالةُ — فسرى الإصلاحُ على **كلِّ** شاشةٍ تُدرج من
 * هذا الباب، لا على شاشةِ الموردِ وحدَها. ولو أُصلحت الشاشةُ لصار في النظامِ
 * مساران للتدقيقِ يتفرّقان.
 *
 * ◆ ولا يُدقَّق إدراجٌ **فشل** — فأثرٌ لواقعةٍ لم تقع أسوأُ من غيابِ الأثر.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => 4, 'name' => 'supplier audit test');
require_once $ROOT . '/includes/cmp03_local_store.php';

$conn = $GLOBALS['conn'];
$CO = 4;
$FAMILY = 'INJ0162probe';
$SCREEN = $FAMILY . getmypid() . '.php';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $extra = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($extra !== '' ? "  ⟵ {$extra}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$one = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    if ($r === false) { return null; }
    $x = $r->fetch_row();
    return $x === null ? null : $x[0];
};
/* كنسٌ **بعائلةِ الوسمِ** لا بالوسمِ وحدَه — فجولةٌ ماتت تترك ثغرةً تُعمي التالية */
$sweep = function () use ($conn, $FAMILY) {
    $n = 0;
    $conn->query("DELETE FROM activity_logs WHERE screen_name LIKE '{$FAMILY}%'");
    $n += max(0, $conn->affected_rows);
    $conn->query("DELETE FROM cmp03_screen_rows WHERE canonical_file LIKE '{$FAMILY}%'");
    $n += max(0, $conn->affected_rows);
    return $n;
};
$say('══ INJ-0162 · أثرُ تدقيقِ الموردِ وحسابِه البنكيّ  (كُنس ' . $sweep() . ' من عائلةِ ' . $FAMILY . ')');

/* ══ ① العلاجُ في الطبقةِ المشتركةِ لا في الشاشة ═══════════════════════════════ */
$store = (string) file_get_contents($ROOT . '/includes/cmp03_local_store.php');
$ok(function_exists('cmp03_stage_insert') && function_exists('cmp03_store_audit'),
    'الطبقةُ المشتركةُ محمَّلةٌ بدالتيها');
$pos = strpos($store, 'function cmp03_stage_insert');
$end = $pos !== false ? strpos($store, 'if (!function_exists(\'cmp03_store_rows\')', $pos) : false;
$body = ($pos !== false && $end !== false) ? substr($store, $pos, $end - $pos) : '';
$ok($body !== '' && strpos($body, 'cmp03_store_audit') !== false,
    '**والإدراجُ يُدقّق** — `cmp03_stage_insert` تنادي المُوصِلَ المشترك',
    'كانت شقيقتاها تُدقّقان وهي لا — فكلُّ إدراجٍ من هذا الباب بلا أثر');
/* ◆ INJ-0151: لم تعد شاشةُ الحساباتِ البنكيةِ تكتب من هذا البابِ إطلاقًا —
     صارت **قارئةً** للمصدرِ الموثَّق `suppliers.bank_*`، والكتابةُ بابُها
     `SupplierDocumentService::verifyBank` بمستندِ إثباتٍ ومحقِّقٍ مسجَّل.
     فالمقاسُ هنا: أنَّها لا تكتب، وأنَّها تقرأ المصدرَ المنظَّم. */
$bank = (string) file_get_contents($ROOT . '/Suppliers/supplier_bank.php');
$ok(strpos($bank, 'cmp03_stage_insert') === false,
    'وشاشةُ الحساباتِ البنكيةِ لا تكتب من هذا البابِ أصلًا (INJ-0151)');
$ok(strpos($bank, 'FROM suppliers s') !== false,
    'بل تقرأ `suppliers` — المصدرَ الموثَّقَ المربوطَ بمعرِّفِ المورد');
$ok(strpos($bank, 'ems_audit_change') === false && strpos($bank, 'cmp03_store_audit') === false,
    '   ولا تنادي التدقيقَ بيدها — فلا مسارَ تدقيقٍ ثانٍ يتفرّق عن الطبقة');

/* ══ ② الإدراجُ يُنتج صفًّا **بالحقولِ المتغيرةِ وقيمتيها** ═════════════════════ */
$before = (int) $one("SELECT COUNT(*) FROM activity_logs WHERE screen_name = '{$SCREEN}'");
$payload = array('bank_name' => 'بنكُ النيلِ — جسٌّ', 'account_number' => 'PRB-0162',
                 'iban' => 'SD00PRB0162', 'currency' => 'USD');
$made = cmp03_stage_insert($conn, $CO, $SCREEN, $payload, 'draft', 1, 'جسُّ التدقيق');
$ok($made === true, 'أُدرج صفُّ حسابٍ بنكيٍّ عبر الطبقةِ المشتركة');
$after = (int) $one("SELECT COUNT(*) FROM activity_logs WHERE screen_name = '{$SCREEN}'");
$ok($after - $before === 1, '**وأُنتج صفُّ تدقيقٍ واحدٌ** (' . ($after - $before) . ')',
    'والتكرارُ ضوضاءٌ كالغياب');

$lg = $conn->query("SELECT * FROM activity_logs WHERE screen_name = '{$SCREEN}' ORDER BY id DESC LIMIT 1");
$lg = ($lg && $lg->num_rows) ? $lg->fetch_assoc() : null;
$ok($lg !== null, 'وقُرئ صفُّ التدقيق');
if ($lg !== null) {
    $ok((string) $lg['action_type'] === 'create', '   ورمزُ الفعلِ «create» (' . $lg['action_type'] . ')');
    $ok((int) $lg['record_id'] > 0, '   ومعرِّفُ الصفِّ مُسجَّلٌ (' . $lg['record_id'] . ')',
        'أثرٌ لا يُشير إلى صفٍّ لا يُراجَع');
    foreach (array('bank_name', 'account_number', 'iban') as $f) {
        $ok(mb_strpos((string) $lg['field_name'], $f) !== false, "   ويُسمّي الحقلَ «{$f}»");
    }
    $newV = json_decode((string) $lg['new_value'], true);
    $oldV = json_decode((string) $lg['old_value'], true);
    $ok(is_array($newV) && isset($newV['account_number']) && $newV['account_number'] === 'PRB-0162',
        '   ويحمل القيمةَ **بعد** (' . (is_array($newV) && isset($newV['account_number']) ? $newV['account_number'] : '—') . ')');
    /* «قبل» خاويةٌ بحقٍّ — الصفُّ يُنشأ من عدمٍ فكلُّ حقلٍ تغييرٌ من نُلٍّ */
    $ok(is_array($oldV) && array_key_exists('account_number', $oldV) && $oldV['account_number'] === null,
        '   والقيمةُ **قبل** نُلٌّ مُصرَّحٌ — لا حقلٌ غائبٌ يُقرأ «لم يتغيّر»');
    $ok((int) $lg['user_id'] === 1, '   ويحمل **الفاعلَ** (' . $lg['user_id'] . ')');
    $ok(!empty($lg['created_at']), '   و**الوقتَ** (' . $lg['created_at'] . ')');
}

/* ══ ③ ولا يُدقَّق إدراجٌ **فشل** ═══════════════════════════════════════════════
     أثرٌ لواقعةٍ لم تقع أسوأُ من غيابِ الأثر.
     ◆ والفشلُ يُستدعى بقيدٍ **حقيقيٍّ في الجدول**: `CHECK (json_valid(payload))`.
       فبايتاتٌ مكسورةُ الترميزِ تُرجع `json_encode` كاذبةً ⇒ حمولةٌ خاويةٌ ⇒ يردُّها
       القيد. ولم أختر مدًى (varchar 80) لأنَّ `sql_mode` **خاويةٌ** فتُبتَر صامتةً
       ويمرُّ الإدراجُ — فيصير فرعُ الفشلِ فرعًا لا يُحكَم عليه.
     ◆ والحكمُ على **رقمِ الخطأ** لا على «رُفض» وحدَها: 4025 في MariaDB · 3819 في
       MySQL — وإلا لمرَّ الفرعُ برفضٍ لسببٍ آخرَ تمامًا. */
$b2 = (int) $one("SELECT COUNT(*) FROM activity_logs WHERE screen_name = '{$SCREEN}b'");
$badOk = cmp03_stage_insert($conn, $CO, $SCREEN . 'b',
                            array('x' => "\xB1\x31\xC0"), 'draft', 1, 'جسُّ الفشل');
$a2 = (int) $one("SELECT COUNT(*) FROM activity_logs WHERE screen_name = '{$SCREEN}b'");
$ok($badOk === false, '**وإدراجٌ يردُّه القيدُ يُعلن فشلَه** (المُرجَعُ ' . var_export($badOk, true) . ')',
    'و`config.php` يضبط mysqli على عدمِ الرمي — فالحكمُ على المُرجَعِ لا على غيابِ استثناء');
/* ورقمُ الخطأ يعيش على **الجملةِ** والدالةُ تُغلقها قبل العودة — فيُقرأ الرقمُ من
   جملةٍ مباشرةٍ بالحمولةِ نفسِها؛ فيُعرَف أنَّ الرادَّ هو القيدُ لا سببٌ آخر. */
$errno = 0;
$raw = $conn->prepare('INSERT INTO cmp03_screen_rows
        (company_id, canonical_file, payload, status, is_seed, created_by, created_by_name)
        VALUES (?, ?, ?, ?, 0, ?, ?)');
if ($raw) {
    $bad = (string) json_encode(array('x' => "\xB1\x31\xC0"), JSON_UNESCAPED_UNICODE);
    $cid = $CO; $can = $SCREEN . 'c'; $stt = 'draft'; $u1 = 1; $who = 'جسُّ الرقم';
    $raw->bind_param('isssis', $cid, $can, $bad, $stt, $u1, $who);
    $raw->execute();
    $errno = (int) $raw->errno;
    $raw->close();
}
$ok($errno === 4025 || $errno === 3819,
    '   والرادُّ هو **قيدُ CHECK** لا سببٌ آخر (errno ' . $errno . ')',
    'رفضٌ لسببٍ آخرَ يجعل الفرعَ كاذبًا');
$ok($a2 === $b2 && $a2 === 0,
    '   و**لا صفَّ تدقيقٍ لواقعةٍ لم تقع** (' . $b2 . ' = ' . $a2 . ')',
    'أثرٌ لإدراجٍ فاشلٍ يُلوّث السجلَّ بأسوأَ من الغياب');

/* ══ ④ والشقُّ الآخرُ من المعيار: تعديلُ الموردِ يُدقَّق سلفًا ══════════════════ */
$verify = (int) $one("SELECT COUNT(*) FROM activity_logs WHERE action_type = 'verify_bank'");
$ok($verify > 0, 'والشقُّ الآخرُ حيٌّ: توثيقُ حسابِ موردٍ يُنتج أثرًا (' . $verify . ' صفًّا)',
    'وهو الشقُّ الذي كان متحقّقًا قبل هذا الإصلاح');

$left = $sweep();
$say('   كُنس ختامًا: ' . $left . ' صفًّا');
$chk = (int) $one("SELECT COUNT(*) FROM activity_logs WHERE screen_name LIKE '{$FAMILY}%'");
$ok($chk === 0, 'صفرُ ثغرةٍ من عائلةِ الوسمِ بعد الجولة (' . $chk . ')');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
