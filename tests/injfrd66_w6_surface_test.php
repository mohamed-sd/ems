<?php
/**
 * tests/injfrd66_w6_surface_test.php — شاهدُ الموجةِ ⑥: معاييرُ السطحِ الثلاثةَ عشر
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **إيجابيٌّ ①**: ثلاثةَ عشرَ متطلبًا خضراءُ مقيسةً على السطح.
 * ◆ **سالبٌ ②**: والبوابةُ **ترسُب** ببندِ تنقّلٍ **حيٍّ** مزروعٍ — فصفرُها رؤيةٌ لا عمًى.
 * ◆ **سالبٌ ③**: و**لا ترسُب** بالبندِ **الخامل** — وهذا هو الفصلُ كلُّه:
 *   صفٌّ `active = 0` ليس بندَ تنقّل، والسايدبارُ لا يُصيّره. وعدُّ الصفوفِ
 *   كلِّها كان سيُحمِّر ثلاثةَ عشرَ متطلبًا سليمًا.
 * ◆ **سالبٌ ④**: وترسُب بكتابةٍ مزروعةٍ في جدولٍ محمِيٍّ من مساحةِ الموردين.
 * ◆ **سالبٌ ⑤**: وترسُب باسمٍ فنيٍّ مزروعٍ في بندٍ حيّ.
 * ◆ **إيجابيٌّ ⑥**: و`SAL-05` مسجَّلٌ في دورةِ الإدارةِ بمرحلةِ عميلِه —
 *   صفًّا واحدًا، بموضعٍ **مقروءٍ من مِرساةٍ لا مخترَع**.
 *
 * التشغيل: php tests/injfrd66_w6_surface_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$pass = 0; $fail = 0;
$check = static function (bool $ok, string $msg) use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "   ✔ {$msg}\n"; } else { $fail++; echo "   ✘ {$msg}\n"; }
};
$num = static function (string $sql) use ($conn): int {
    $r = @mysqli_query($conn, $sql);
    return $r ? (int) mysqli_fetch_row($r)[0] : -1;
};
$gate = static function () use ($ROOT): array {
    $o = array(); $rc = 0;
    exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/injfrd66_w6_surface_gate.php')
         . ' --gate 2>&1', $o, $rc);
    return array($rc, implode("\n", $o));
};

/* مسبارُ التنقّلِ يُزال في كلِّ حال — ولا يُترك صفٌّ مزروعٌ في سجلٍّ حيّ */
$PROBE = 'Zz/zz_injfrd66_w6_probe.php';
$wipe = static function () use ($conn, $PROBE): void {
    @mysqli_query($conn, "DELETE FROM nav_items WHERE route = '" . $PROBE . "'");
    @mysqli_query($conn, "DELETE FROM nav_items WHERE label_ar LIKE 'zz_injfrd66_w6%'");
};
$wipe();

echo "① إيجابيٌّ — البوابةُ تعبر:\n";
list($rc, $txt) = $gate();
$check($rc === 0, "رمزُ الخروج {$rc}");
$check(preg_match('~أخضر\s*13\s*·\s*أحمر\s*0~u', $txt) === 1, 'ثلاثةَ عشرَ أخضرَ وصفرُ أحمر');

echo "\n② سالبٌ — بندُ تنقّلٍ حيٌّ مزروعٌ يُحمِّرها:\n";
$seed = @mysqli_query($conn, "INSERT INTO nav_items
        (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, active)
        VALUES (12, 'DAILY', 0, 0, 'جهات اتصال العملاء', '{$PROBE}', 'fa fa-link', 999, 1)");
if (!$seed) { $fail++; echo "   ✘ تعذّر الزرع: " . mysqli_error($conn) . "\n"; }
else {
    list($rc2, $txt2) = $gate();
    $check($rc2 === 1, "رسّبت بالبندِ الحيِّ (رمزٌ {$rc2})");
    $check(mb_strpos($txt2, 'SAL-02') !== false && preg_match('~SAL-02\s+✘~u', $txt2) === 1,
        'وسمَّت المتطلبَ الذي خُرق');
    $check(mb_strpos($txt2, $PROBE) !== false, 'وسمَّت المسارَ باسمِه');

    echo "\n③ سالبٌ — وإطفاؤه وحدَه يُعيدها خضراء (والخاملُ ليس بندَ تنقّل):\n";
    @mysqli_query($conn, "UPDATE nav_items SET active = 0 WHERE route = '{$PROBE}'");
    list($rc3, $txt3) = $gate();
    $check($rc3 === 0, "عادت خضراءَ بالصفِّ نفسِه مُطفأً (رمزٌ {$rc3})");
    $check($num("SELECT COUNT(*) FROM nav_items WHERE route='{$PROBE}'") === 1,
        'والصفُّ ما يزال موجودًا — فالفرقُ في `active` لا في الوجود');
    $wipe();
    $check($num("SELECT COUNT(*) FROM nav_items WHERE route='{$PROBE}'") === 0, 'وأُزيل المسبار');
}

echo "\n④ سالبٌ — كتابةٌ مزروعةٌ في جدولٍ محمِيٍّ من مساحةِ الموردين:\n";
$probeFile = $ROOT . '/Suppliers/zz_injfrd66_w6_equip_write.php';
@file_put_contents($probeFile, "<?php\n/* مسبارٌ — يُزال فورَ القياس */\n"
    . "\$sql = \"INSERT INTO equipments (name, supplier_id) VALUES ('x', 1)\";\n");
if (!is_file($probeFile)) { $fail++; echo "   ✘ تعذّر الزرع\n"; }
else {
    list($rc4, $txt4) = $gate();
    @unlink($probeFile);
    $check($rc4 === 1, "رسّبت بالكتابةِ المزروعة (رمزٌ {$rc4})");
    $check(preg_match('~SUP-04\s+✘~u', $txt4) === 1, 'وسمَّت SUP-04');
    $check(mb_strpos($txt4, 'zz_injfrd66_w6_equip_write.php') !== false, 'وسمَّت الملفَّ باسمِه');
    $check(!is_file($probeFile), 'وأُزيل المسبار');
}

echo "\n⑤ سالبٌ — اسمٌ فنيٌّ مزروعٌ في بندٍ حيّ:\n";
$seed2 = @mysqli_query($conn, "INSERT INTO nav_items
        (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, active)
        VALUES (2, 'DAILY', 0, 0, 'zz_injfrd66_w6_supplier_capacity.php', '{$PROBE}', 'fa fa-link', 999, 1)");
if (!$seed2) { $fail++; echo "   ✘ تعذّر الزرع: " . mysqli_error($conn) . "\n"; }
else {
    list($rc5, $txt5) = $gate();
    $wipe();
    $check($rc5 === 1, "رسّبت باللفظِ الفنيِّ (رمزٌ {$rc5})");
    $check(preg_match('~SAL-15\s+✘~u', $txt5) === 1, 'وسمَّت SAL-15');
    $check($num("SELECT COUNT(*) FROM nav_items WHERE label_ar LIKE 'zz_injfrd66_w6%'") === 0,
        'وأُزيل المسبار');
}

echo "\n⑥ إيجابيٌّ — SAL-05 مسجَّلٌ بمرحلةِ عميلِه:\n";
$row = null;
$r = @mysqli_query($conn, "SELECT dept_name, stage_order, stage_name, group_name, screen_title
                             FROM gov_screen_cycle WHERE screen_file = 'Clients/activities.php'");
if ($r) { $row = mysqli_fetch_assoc($r); }
$check($row !== null, 'صفُّ الدورةِ قائم');
$check($num("SELECT COUNT(*) FROM gov_screen_cycle WHERE screen_file REGEXP 'activities\\\\.php'") === 1,
    'صفٌّ واحدٌ لا أكثر — والتكرارُ يُضاعف المقام');
/* ◆ **والموضعُ مقروءٌ من مِرساةٍ لا مخترَع**: حرفٌ واحدٌ يختلف في اسمِ المرحلةِ
     يُنشئ مرحلةً جديدةً بدل الانضمامِ إلى قائمةٍ قائمة. */
$anchor = null;
$r2 = @mysqli_query($conn, "SELECT dept_name, stage_order, stage_name, group_name
                              FROM gov_screen_cycle WHERE screen_file = 'clients.php' LIMIT 1");
if ($r2) { $anchor = mysqli_fetch_assoc($r2); }
$check($row && $anchor
    && $row['dept_name']  === $anchor['dept_name']
    && $row['stage_name'] === $anchor['stage_name']
    && $row['group_name'] === $anchor['group_name']
    && (int) $row['stage_order'] === (int) $anchor['stage_order'],
    'وموضعُه حرفًا حرفًا موضعُ `clients.php` — مقروءٌ لا مخترَع'
    . ($row ? ' («' . $row['stage_name'] . '» · «' . $row['group_name'] . '»)' : ''));
/* والبندُ يبقى خاملًا: التسجيلُ موضعٌ لا ظهور */
$check($num("SELECT COUNT(*) FROM nav_items WHERE route LIKE '%activities.php%' AND active = 1") === 0,
    'وبندُه ما يزال خاملًا — «ولا يظهر بندًا مستقلًّا» نصُّ المتطلب');

echo "\n⑦ سالبٌ — ونزعُ صفِّ الدورةِ يُحمِّرها (فالشكلُ الرابعُ ليس أعمى):\n";
/* ◆ **وأشكالُ القياسِ في هذه البوابةِ أربعة** — بندٌ حيّ · لفظٌ فنيّ · كتابةٌ
     في جدولٍ محمِيّ · **صفٌّ في دفترِ الدورة**. والثلاثةُ الأُوَل جُسَّت أعلاه،
     والرابعُ يُجَسُّ هنا: **بوابةٌ لا يُحمِّرها غيابُ ما تدَّعي قياسَه عمياء**. */
$saved = null;
$r3 = @mysqli_query($conn, "SELECT dept_name, layer_name, stage_order, stage_name,
                                   group_name, screen_title, screen_file
                              FROM gov_screen_cycle WHERE screen_file = 'Clients/activities.php'");
if ($r3) { $saved = mysqli_fetch_assoc($r3); }
if (!$saved) { $fail++; echo "   ✘ لا صفَّ يُنزَع\n"; }
else {
    @mysqli_query($conn, "DELETE FROM gov_screen_cycle WHERE screen_file = 'Clients/activities.php'");
    list($rc6, $txt6) = $gate();
    /* ويُعاد الصفُّ من نسخةٍ محفوظةٍ حرفًا — لا يُعاد بناؤه من ذاكرةٍ */
    $st = mysqli_prepare($conn, "INSERT INTO gov_screen_cycle
            (company_id, dept_name, layer_name, stage_order, stage_name, group_name, screen_title, screen_file)
            VALUES (0, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($st, 'ssissss',
        $saved['dept_name'], $saved['layer_name'], $saved['stage_order'], $saved['stage_name'],
        $saved['group_name'], $saved['screen_title'], $saved['screen_file']);
    mysqli_stmt_execute($st);
    $check($rc6 === 1, "رسّبت بنزعِ صفِّ الدورة (رمزٌ {$rc6})");
    $check(preg_match('~SAL-05\s+✘~u', $txt6) === 1, 'وسمَّت SAL-05');
    $check($num("SELECT COUNT(*) FROM gov_screen_cycle WHERE screen_file='Clients/activities.php'") === 1,
        'وأُعيد الصفُّ حرفًا — صفٌّ واحدٌ لا صفران');
}

echo "\n⑧ إيجابيٌّ — البوابةُ خضراءُ بعدَ إزالةِ كلِّ مسبار:\n";
list($rc7, ) = $gate();
$check($rc7 === 0, "رمزُ الخروج {$rc7}");

echo "\n⑨ شاهدُ الشواهد — عدّادُ الشاهدِ لا يُدهَس بكلمةِ مرورِ القاعدة:\n";
/* ◆ **عطبٌ يُبلغ رقمًا لا يقيس ما يسمّيه**: `config.php` يُسنِد `$pass` كلمةَ
     مرورِ القاعدة. فشاهدٌ يُعلن `$pass = 0` **قبلَ** ضمِّه يفقد عدّادَه —
     و`$pass++` يصير **زيادةَ سلسلةٍ نصّيةٍ** لا عدًّا، فيطبع `%d` أوَّلَ رقمٍ
     في كلمةِ المرور. ثلاثةُ شواهدَ في العائلةِ كانت تُعلن أرقامًا مختلَقةً
     (٩ بدل ١٧ · وغيرَها) **وهي خضراءُ صادقةٌ في حكمِها** — لأنَّ رمزَ الخروجِ
     على `$fail` لا على `$pass`. **رقمٌ كاذبٌ في تقريرٍ صادقٍ أخطرُ من رسوب.**
   ◆ **وأوَّلُ مسحٍ أعلن أربعةً وأربعين ملفًّا معطوبًا وهي ثلاثة**: قارنتُ
     إزاحةَ `preg_match` (**بالبايت**) بإزاحةِ `mb_strpos` (**بالحرف**) —
     ومع نصٍّ عربيٍّ كثيفٍ تصير البايتاتُ ضِعفَ الحروف. **والوحدةُ جزءٌ من
     الرقم**، ورقمانِ بوحدتَين لا يُقارَنان. */
$shadowed = array();
foreach ((array) glob($ROOT . '/tests/injfrd66_*.php') as $f) {
    $s = (string) @file_get_contents($f);
    if ($s === '') { continue; }
    $decl = strpos($s, '$pass = 0');            /* بالبايت — كما `preg_match` سواءً */
    if ($decl === false) { $decl = strpos($s, '$pass=0'); }
    if ($decl === false) { continue; }
    if (!preg_match('~require(?:_once)?[^;\n]*config\.php~', $s, $mm, PREG_OFFSET_CAPTURE)) { continue; }
    if ($mm[0][1] > $decl) { $shadowed[] = basename($f); }
}
$check($shadowed === array(),
    'صفرُ شاهدٍ في العائلةِ يُعلن عدّادَه قبلَ ضمِّ `config.php`'
    . ($shadowed ? ' — ' . implode('، ', $shadowed) : ''));
/* والفحصُ نفسُه يُجَسُّ: مسبارٌ بالنمطِ المعطوبِ يجب أن يُرصَد */
$shadowProbe = $ROOT . '/tests/injfrd66_zz_shadow_probe.php';
@file_put_contents($shadowProbe,
    "<?php\n/* مسبارٌ — يُزال فورَ القياس */\n\$pass = 0;\nrequire_once __DIR__ . '/../config.php';\n");
$seen = false;
if (is_file($shadowProbe)) {
    $s = (string) @file_get_contents($shadowProbe);
    $d = strpos($s, '$pass = 0');
    if (preg_match('~require(?:_once)?[^;\n]*config\.php~', $s, $mp, PREG_OFFSET_CAPTURE)) {
        $seen = ($mp[0][1] > $d);
    }
    @unlink($shadowProbe);
}
$check($seen, 'والفحصُ يرصد النمطَ المعطوبَ في مسبارٍ مزروع');
$check(!is_file($shadowProbe), 'وأُزيل المسبار');

printf("\n%s  ناجح %d · راسب %d\n", $fail === 0 ? '✔ الموجة ⑥' : '✘ الموجة ⑥', $pass, $fail);
exit($fail === 0 ? 0 : 1);
