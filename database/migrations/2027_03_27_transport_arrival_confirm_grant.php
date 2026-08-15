<?php
/**
 * 2027_03_27 — يدُ النقلِ تُوصَل: صلاحيةُ الكتابةِ في شاشةِ «تأكيدِ الوصول»
 * ═══════════════════════════════════════════════════════════════════════════
 * **العيبُ المقيسُ حيًّا** (لا مستنتَجًا): شاشةُ `Transport/transfer_arrival.php`
 * مسجَّلةٌ في `modules` (#433) وممنوحةٌ **لدورٍ واحدٍ لا غير** — 23
 * «إدارة النقل والترحيل» — بـ`can_view=1` و`can_edit=0`. ومعالجُ الـPOST فيها
 * يشترط `'perm' => 'can_edit'`. فالنتيجةُ المقيسةُ بحساب «مشرف النقل» حيًّا:
 *
 *     POST deliver_id + witness_name  ⇒  302 · `GOV-PERM-403-WRITE`
 *     صفرُ صفٍّ في `transfer_delivery_docs` · والحالةُ كما هي
 *
 * وبما أنَّ **لا دورَ آخرَ يملك `can_view` على الوحدة**، فالحكمُ ليس «دورٌ ينقصه
 * إذن» بل **لا حسابَ في النظامِ كلِّه يستطيع تأكيدَ وصول**: الشاشةُ تُصيّر نموذجًا
 * مكتملًا — شاهدًا وملاحظةً وزرَّ إرسالٍ — لا يملك أحدٌ إرسالَه. وهو عينُ ما تنهى
 * عنه MD-05: **البناءُ ليس تبنّيًا**، والنموذجُ الذي لا يُرسَل زخرفةٌ لا بابٌ.
 *
 * ── ولماذا الدورُ 23 هو صاحبُ الفعلِ لا سواه ────────────────────────────────
 * القدوةُ تُقاس ولا تُفترَض: الدورُ 23 يملك `can_edit` على **المرحلةِ السابقةِ**
 * (`transfer_in_transit.php` — وهي التي تُدخل الأمرَ في `arrived`) وعلى
 * **المرحلةِ التالية** (`transfer_close_cost.php` — وهي التي تُقفله بتكلفته).
 * والشاشتانِ الوحيدتانِ في `Transport/` اللتان تستعملان `ems_post_contract`
 * بـ`can_edit` هما `transfer_close_cost.php` و`transfer_arrival.php` — الأولى
 * ممنوحةٌ والثانيةُ لا. فالنقصُ **سهوُ بذرٍ في صفٍّ واحد** لا سياسةُ حوكمةٍ
 * مقصودة: لا يُعقل أن يملك الدورُ إدخالَ الأمرِ في «واصل» وإقفالَه، ويُمنع من
 * الخطوةِ بينهما وحدَها.
 *
 * **قرارُ المالك 2026-08-14**: يُمنَح الدورُ 23 `can_edit` على الوحدةِ 433،
 * **مرآةً حرفيةً لشقيقتِها** `transfer_close_cost.php`:
 *
 *     can_view = 1 · can_add = 0 · can_edit = 1 · can_delete = 0
 *
 * ولا يُمنَح `can_add`: عقدُ الشاشةِ لا يسأل إلا `can_edit`، وتأكيدُ التسليمِ
 * تقدُّمٌ بأمرٍ قائمٍ في دورتِه لا إنشاءُ مستندٍ من عدم — وهو ذاتُ التمييزِ
 * المعمولِ به في منحِ يدِ المبيعاتِ (هجرة 2027_02_20). ولا `can_delete`:
 * مستندُ التسليمِ سندُ إقفالٍ لا يُمحى من الشاشة.
 *
 * مُتحمِّلةٌ للتكرار · وتنتهي بشاهدٍ يقيس القرارَ **من دالةِ الشاشةِ نفسِها**
 * لا باستعلامٍ موازٍ يوهم النجاح.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$fail = array();
$one  = function ($sql) use ($db) { $r = $db->query($sql); return $r ? $r->fetch_row()[0] : null; };

const TRANSPORT_ROLE = 23;
const SCREEN         = 'Transport/transfer_arrival.php';
const PEER_SCREEN    = 'Transport/transfer_close_cost.php';

/* ── ① الوحدةُ تُقاس ولا تُفترض ──────────────────────────────────────────── */
$mid = (int) $one("SELECT id FROM modules WHERE code = '" . SCREEN . "'");
if ($mid === 0) {
    fwrite(STDERR, 'الوحدةُ ' . SCREEN . " غير مسجَّلةٍ في modules — لا يُخترع تسجيل\n");
    exit(1);
}
echo "── ① الوحدة: #{$mid} (" . SCREEN . ")\n";

/* ── ② القدوةُ: ما تملكه الشقيقةُ في السلسلةِ هو ما يُمنَح هنا ───────────────
     ◆ ولماذا الشقيقةُ لا «أدوارٌ أخرى على الوحدةِ نفسِها»: الوحدةُ 433 ممنوحةٌ
       لدورٍ **واحد**، فلا قدوةَ فيها أصلًا — وهذا هو العيبُ عينُه. فتُقاس القدوةُ
       على الشاشةِ التاليةِ في السلسلةِ التي تحمل **عقدَ الكتابةِ نفسَه**. */
$pid = (int) $one("SELECT id FROM modules WHERE code = '" . PEER_SCREEN . "'");
if ($pid === 0) {
    fwrite(STDERR, 'الشقيقةُ ' . PEER_SCREEN . " غير مسجَّلةٍ — لا قدوةَ تُقاس\n");
    exit(1);
}
$r = $db->query("SELECT can_view, can_add, can_edit, can_delete
                   FROM role_permissions
                  WHERE module_id = {$pid} AND role_id = " . TRANSPORT_ROLE);
$peer = $r ? $r->fetch_assoc() : null;
if (!$peer) {
    fwrite(STDERR, 'لا صفَّ للدورِ ' . TRANSPORT_ROLE . ' على ' . PEER_SCREEN
        . " — لا يُمنَح إذنٌ بالتخمين\n");
    exit(1);
}
echo '── ② القدوةُ (' . PEER_SCREEN . ' · دور ' . TRANSPORT_ROLE . '): '
   . "view={$peer['can_view']} add={$peer['can_add']} edit={$peer['can_edit']} del={$peer['can_delete']}\n";
if ((int) $peer['can_edit'] !== 1) {
    fwrite(STDERR, "القدوةُ نفسُها بلا can_edit — لا تُنسَخ صلاحيةٌ غيرُ قائمة\n");
    exit(1);
}

/* ◆ القدوةُ تُثبت **حقَّ الكتابةِ لصاحبِ السلسلة** ولا تُنسَخ عمودًا بعمود:
     الشقيقةُ تحمل `can_add=1` لأنها تُنشئ سطورَ تكلفةٍ جديدة، وهذه لا تُنشئ
     شيئًا — تُقدِّم أمرًا قائمًا في دورتِه. ونسخُ الأعمدةِ حرفيًّا كان سيمنح
     `can_add` بلا فعلٍ يستهلكها (عقدُ الشاشةِ لا يسأل إلا `can_edit`)، وهو
     توسيعُ إذنٍ بلا سببٍ مقيس. فيُعلَن المنحُ صراحةً كما قُرِّر. */
$grant = array('can_view' => 1, 'can_add' => 0, 'can_edit' => 1, 'can_delete' => 0);

/* ── ③ الحالُ قبلَ المنحِ — يُطبع ليُقرأ الأثرُ لا ليُفترض ─────────────────── */
$r = $db->query("SELECT can_view, can_add, can_edit, can_delete
                   FROM role_permissions WHERE module_id = {$mid} AND role_id = " . TRANSPORT_ROLE);
$was = $r ? $r->fetch_assoc() : null;
echo '── ③ قبل: ' . ($was
    ? "view={$was['can_view']} add={$was['can_add']} edit={$was['can_edit']} del={$was['can_delete']}\n"
    : "لا صفَّ صلاحيةٍ إطلاقًا\n");

/* ── ④ المنحُ — مُتحمِّلٌ للتكرار ─────────────────────────────────────────── */
if ($was === null) {
    $ok = $db->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                      VALUES (" . TRANSPORT_ROLE . ", {$mid}, {$grant['can_view']}, {$grant['can_add']},
                              {$grant['can_edit']}, {$grant['can_delete']})");
    if (!$ok) { $fail[] = 'المنح: ' . $db->error; }
    echo '── ④ صفُّ الصلاحيةِ لدور ' . TRANSPORT_ROLE . ': ' . ($ok ? "أُنشئ\n" : "تعذّر — {$db->error}\n");
} else {
    /* `can_add`/`can_delete` تُضبطان صراحةً على قيمةِ القدوةِ أيضًا — فتركُهما
       «كما هي» يجعل الهجرةَ غيرَ حتميةٍ لو بُذرت بقيمٍ مختلفةٍ في بيئةٍ أخرى. */
    $ok = $db->query("UPDATE role_permissions
                         SET can_view   = {$grant['can_view']},
                             can_add    = {$grant['can_add']},
                             can_edit   = {$grant['can_edit']},
                             can_delete = {$grant['can_delete']}
                       WHERE module_id = {$mid} AND role_id = " . TRANSPORT_ROLE);
    if (!$ok) { $fail[] = 'التحديث: ' . $db->error; }
    echo '── ④ صفُّ الصلاحيةِ قائمٌ — ضُبط على المنحِ المقرَّر'
       . ($ok ? " ✔\n" : " ✘ — {$db->error}\n");
}

/* ── ⑤ الشاهدُ المُشغَّل: القرارُ يُقاس من دالةِ الشاشةِ نفسِها ───────────── */
echo "── ⑤ الشاهدُ المُشغَّل\n";
$r = $db->query("SELECT can_view, can_add, can_edit, can_delete FROM role_permissions
                  WHERE module_id = {$mid} AND role_id = " . TRANSPORT_ROLE);
$row = $r ? $r->fetch_assoc() : null;
$dbOk = $row && (int) $row['can_view'] === 1 && (int) $row['can_edit'] === 1;
echo '     صفُّ القاعدة: ' . ($dbOk
    ? "view=1 edit=1 add={$row['can_add']} del={$row['can_delete']} ✔\n" : "ناقصٌ ✘\n");
if (!$dbOk) { $fail[] = 'صفُّ الصلاحيةِ لم يثبت'; }

/* ◆ **الجلسةُ تُزرع بعدَ `config.php` لا قبلَه**: `config.php` يستدعي
     `session_start()` فيُحِلُّ محتوى الجلسةِ المخزَّنِ محلَّ ما وُضع قبلَه —
     فتُقرأ `DENY` والصلاحيةُ ممنوحةٌ فعلًا (گوتشا هجرةِ 2027_02_20 نفسُها). */
$ROOT = dirname(__DIR__, 2);
$probeDir = $ROOT . '/storage';
if (!is_dir($probeDir)) { @mkdir($probeDir, 0777, true); }
$probeFile = $probeDir . '/mig0327_perm_probe_' . getmypid() . '.php';
file_put_contents($probeFile, "<?php\n"
    . "error_reporting(0);\n"
    . 'require ' . var_export($ROOT . '/config.php', true) . ";\n"
    . "while (ob_get_level() > 0) { ob_end_clean(); }\n"
    . '$_SESSION[\'user\'] = array(\'id\' => 888, \'role\' => \'' . TRANSPORT_ROLE . "', 'company_id' => 4);\n"
    . 'require_once ' . var_export($ROOT . '/includes/permissions_helper.php', true) . ";\n"
    . '$p = check_page_permissions($GLOBALS[\'conn\'], ' . var_export(SCREEN, true) . ");\n"
    . "echo (empty(\$p['can_view']) ? 'NOVIEW' : '') . (empty(\$p['can_edit']) ? 'NOEDIT' : 'WRITE');\n");
$out = trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probeFile) . ' 2>&1'));
@unlink($probeFile);
$allow = (substr($out, -5) === 'WRITE');
echo '     check_page_permissions لدور ' . TRANSPORT_ROLE . ': '
   . ($allow ? "can_edit ✔\n" : 'لم تُعطِ WRITE ✘ — ' . mb_substr($out, -160) . "\n");
if (!$allow) { $fail[] = 'دالةُ الشاشةِ لا تسمح بالكتابة'; }

/* ── ⑥ والحدُّ الآخرُ يُقاس أيضًا: لا يتوسَّع المنحُ إلى غيرِ صاحبِه ───────── */
$others = (int) $one("SELECT COUNT(*) FROM role_permissions
                       WHERE module_id = {$mid} AND role_id <> " . TRANSPORT_ROLE . ' AND can_edit = 1');
echo "     أدوارٌ أخرى نالت كتابةً على الوحدة: {$others} " . ($others === 0 ? "✔\n" : "✘\n");
if ($others !== 0) { $fail[] = 'المنحُ تسرَّب إلى أدوارٍ أخرى'; }

echo "\n" . (empty($fail)
    ? "✅ يدُ النقلِ موصولةٌ: الدورُ 23 يكتب في «تأكيدِ الوصول» بقرارِ دالةِ الشاشةِ نفسِها.\n"
    : '⚠ ' . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
