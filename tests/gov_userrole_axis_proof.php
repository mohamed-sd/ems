<?php
/**
 * tests/gov_userrole_axis_proof.php — حسمُ ازدواجِ users.role/role_id (م117 · CL-PAT-USERROLE)
 * ═══════════════════════════════════════════════════════════════════════════
 * «الحقيقةُ الواحدةُ لا تُخزَّن بعمودَين يتفرّقان»: `role` هو الحاكمُ (تقرؤه
 * الجلسةُ والحرّاس) و`role_id` أثريٌّ مردومٌ من الحاكمِ وموسومٌ في المخطَّط.
 *
 * أربعةُ فحوص:
 *   ① الانجرافُ صفر — كلُّ صفٍّ role_id = CAST(role AS SIGNED).
 *   ② الوسمُ الأثريُّ قائمٌ في المخطَّطِ نفسِه (لا في وثيقةٍ تُنسى).
 *   ③ قارئُ الإنتاجِ الحاكمُ يقرأ `role` (حارسُ الصلاحيّاتِ من الجلسة).
 *   ④ السالب: صفُّ مِجَسٍّ منجرفٌ ⇒ العدّادُ يتحرّك بواحدٍ ثم يُكنس بإثبات.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once $ROOT . '/config.php';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$PROBE_USER = 'govx_userrole_probe_' . getmypid();
$pass = 0; $fail = 0;
function ok($c, $l, $d = '')
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $fail++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function drift(mysqli $c)
{
    $q = $c->query("SELECT COUNT(*) FROM users WHERE role_id IS NULL OR role_id <> CAST(role AS SIGNED)");
    return $q ? (int) $q->fetch_row()[0] : -1;
}

register_shutdown_function(function () use ($conn, $PROBE_USER) {
    $st = $conn->prepare('DELETE FROM users WHERE username = ?');
    $st->bind_param('s', $PROBE_USER); $st->execute();
    $st = $conn->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $st->bind_param('s', $PROBE_USER); $st->execute();
    if ((int) $st->get_result()->fetch_row()[0] !== 0) {
        fwrite(STDERR, "⛔ كنسُ المِجَسِّ فشل — احذفْ مستخدمَ {$PROBE_USER} يدويًّا\n");
    }
});

echo "══ CL-PAT-USERROLE — الحقيقةُ الواحدةُ بعمودٍ حاكمٍ لا بعمودَين يتفرّقان ══\n";

/* ── ① الانجرافُ صفر ────────────────────────────────────────────────────── */
$before = drift($conn);
ok($before === 0, '① كلُّ صفٍّ متطابقُ العمودَين بعد الردمِ من الحاكم', "الانجراف {$before}");

/* ── ② الوسمُ في المخطَّط ───────────────────────────────────────────────── */
$cm = $conn->query("SELECT COLUMN_COMMENT FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role_id'")->fetch_row();
ok($cm !== null && mb_strpos((string) $cm[0], 'اثري') !== false, '② العمودُ موسومٌ أثريًّا في المخطَّطِ نفسِه');

/* ── ③ القارئُ الحاكم ───────────────────────────────────────────────────── */
$src = (string) file_get_contents($ROOT . '/includes/permissions_helper.php');
$readsRole = (strpos($src, "\$_SESSION['user']['role']") !== false);
$readsRid = preg_match("~\\\$_SESSION\\['user'\\]\\['role_id'\\]~", $src) === 1;
ok($readsRole && !$readsRid, '③ حارسُ الصلاحيّاتِ يقرأ العمودَ الحاكمَ وحدَه من الجلسة');

/* ── ④ السالب: انجرافٌ مِجَسٌّ يُرى فورًا ───────────────────────────────── */
$st = $conn->prepare("INSERT INTO users (username, password, name, role, role_id, company_id)
    VALUES (?, 'x', 'مجس ازدواج الدور', '25', 7, 0)");
$st->bind_param('s', $PROBE_USER);
$ins = $st->execute(); $st->close();
ok($ins, '④أ المِجَسُّ زُرع (role=25 وrole_id=7 متفرّقان)', $ins ? '' : $conn->error);
$after = drift($conn);
ok($after === $before + 1, '④ب العدّادُ تحرّك بواحدٍ — الانجرافُ يُرى لا يُبتلع', "{$before} ⇒ {$after}");
$st = $conn->prepare('DELETE FROM users WHERE username = ?');
$st->bind_param('s', $PROBE_USER); $st->execute();
$swept = ($st->affected_rows === 1); $st->close();
ok($swept, '④ج المِجَسُّ كُنس وثبت كنسُه');
$final = drift($conn);
ok($final === 0, '④د العدّادُ عاد صفرًا', "الانجراف {$final}");

echo "\n═ النتيجة: ✔ {$pass} · ✘ {$fail} ═\n";
exit($fail === 0 ? 0 : 1);
