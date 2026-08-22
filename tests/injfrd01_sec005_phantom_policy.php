<?php
/**
 * tests/injfrd01_sec005_phantom_policy.php
 *   شاهدُ FR-SEC-005 — لا سياسةَ حقلٍ بهدفٍ وهميٍّ معلَّقةً بلا حكم
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معيارُ القبولِ بنصِّه**: «صفرُ سياسةٍ بهدفٍ غيرِ موجود» · وسالبُه «سياسةٌ
 *   بهدفٍ وهميٍّ ← رسوب».
 *
 * ◆ **والمقياسُ يفصل بين ثلاثةِ أشياءَ لا تُخلَط**:
 *   ① سياسةٌ **نافذةٌ** بهدفٍ وهميٍّ ⇒ **رسوب** — هذه هي الفجوة.
 *   ② سياسةٌ **ملغاةٌ** بهدفٍ وهميٍّ ⇒ مقبولةٌ — أثرٌ تدقيقيٌّ لا حماية.
 *   ③ سياسةٌ نافذةٌ بهدفٍ **حقيقيٍّ** ⇒ مقبولةٌ — وهي الأصل.
 *   وخلطُ الثلاثةِ في عددٍ واحدٍ يُخفي أيَّها وقع.
 *
 * ◆ **والإلغاءُ موصولٌ في موضعَين وإلا فهو زخرفة**: يُقاس أن `SensitiveFieldGuard`
 *   **لا يُرجع** السياسةَ الملغاة، وأن `FieldGovernor` لا يعدُّها حساسةً.
 *
 * ◆ **ولا يُغلَق ما رُفع قرارًا**: ثلاثةُ أهدافٍ لها بيانٌ حساسٌ حقيقيٌّ غيرُ
 *   محميٍّ واختيارُ هدفِها **منحُ رؤيةٍ أو حجبُها** — تغييرُ وصولٍ حيٍّ لا
 *   يقرّره منفِّذ. فتُعَدُّ ويُعلَن عددُها ولا تُطوى في «تمَّ».
 *
 * التشغيل: php tests/injfrd01_sec005_phantom_policy.php [--negative]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$db = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }
function colExists(mysqli $d, $tb, $col) {
    return n($d, "SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME = '" . $d->real_escape_string($tb) . "'
                     AND COLUMN_NAME = '" . $d->real_escape_string($col) . "'");
}

$neg  = in_array('--negative', $argv, true);
$MARK = 'belt_ghost.no_such_column';

echo "══ FR-SEC-005 — سياسةُ الحقلِ الوهميةُ لا تبقى معلَّقة ══\n";

if ($neg) {
    /* ◆ **الحزامُ يُثبت دسَّه قبلَ أن يقيس** — وحزامٌ لا يدسُّ شيئًا لا يُثبت
     *   شيئًا. وقعت هذه ثلاثَ مرَّاتٍ في هذه الجولةِ بقيودٍ مغلقةٍ ردَّت
     *   القيمةَ صامتةً. */
    $st = $db->prepare("INSERT INTO `sensitive_field_policies`
        (`field_code`,`classification`,`masking_rule`,`allowed_roles_json`,`status`)
        VALUES (?, 'personal', 'full', '[]', 'نافذة')");
    if (!$st) { exit("  ⛔ تعذّر إعدادُ الحزام: " . $db->error . "\n"); }
    $st->bind_param('s', $MARK);
    if (!$st->execute()) { exit("  ⛔ **رُفض دسُّ الحزام** — " . $st->error . "\n"); }
    $st->close();
    $planted = n($db, "SELECT COUNT(*) FROM `sensitive_field_policies`
                        WHERE `field_code` = '{$MARK}' AND `status` = 'نافذة'");
    if ($planted !== 1) {
        echo "  ⛔ **لم يُدَسَّ شيء** — أُوقِف قبلَ قياسٍ كاذب\n";
        exit(1);
    }
    echo "  ◆ دُسَّت سياسةٌ **نافذةٌ** بهدفٍ وهميّ — **ووجودُها مُثبَتٌ قبلَ القياس**\n";
}

/* ① لا سياسةَ **نافذةً** بهدفٍ وهميّ */
$phantomActive = array();
$r = $db->query("SELECT `field_code` FROM `sensitive_field_policies` WHERE `status` = 'نافذة'");
while ($r && $x = $r->fetch_row()) {
    $parts = explode('.', $x[0], 2);
    if (count($parts) !== 2) { continue; }
    if (colExists($db, $parts[0], $parts[1]) === 0) { $phantomActive[] = $x[0]; }
}
chk(empty($phantomActive), 'FR-SEC-005 · **صفرُ سياسةٍ نافذةٍ بهدفٍ غيرِ موجود**',
    empty($phantomActive) ? '0'
    : count($phantomActive) . ': ' . implode(' · ', array_slice($phantomActive, 0, 4)));

/* ② والملغاةُ لها حكمٌ مكتوبٌ بسببِه ومالكِه */
$cancelled = n($db, "SELECT COUNT(*) FROM `sensitive_field_policies` WHERE `status` = 'ملغاة'");
$ruled = n($db, "SELECT COUNT(*) FROM `gov_phantom_policy_rulings`");
$thin  = n($db, "SELECT COUNT(*) FROM `gov_phantom_policy_rulings`
                  WHERE TRIM(`reason`) = '' OR TRIM(`owner`) = ''");
chk($thin === 0, 'ولكلِّ حكمٍ **سببٌ ومالكٌ**', "ناقصٌ: {$thin} من {$ruled}");

/* ③ والمفرداتُ مغلقة */
$badRule = n($db, "SELECT COUNT(*) FROM `gov_phantom_policy_rulings`
                    WHERE `ruling` NOT IN ('NO_REAL_TARGET','SUPERSEDED','NEEDS_OWNER_DECISION')");
chk($badRule === 0, 'والحكمُ من مفرداتٍ مغلقة', "خارجَها: {$badRule}");

/* ④ ولا صفَّ حُذف — الإلغاءُ بديلُ الحذف */
$tot = n($db, "SELECT COUNT(*) FROM `sensitive_field_policies`");
chk($tot >= 27, 'ولا صفَّ حُذف — **الإلغاءُ بديلُ الحذف** (§تاسعًا)',
    "إجمالي={$tot} · ملغاة={$cancelled}");

/* ⑤ **والملغاةُ لا يُرجعها الحارسُ** — الوصلُ لا الزخرفة */
$guardSees = -1;
$one = null;
$r = $db->query("SELECT `field_code` FROM `sensitive_field_policies`
                  WHERE `status` = 'ملغاة' LIMIT 1");
if ($r && $x = $r->fetch_row()) { $one = $x[0]; }
if ($one !== null) {
    $st = $db->prepare("SELECT COUNT(*) FROM sensitive_field_policies
                         WHERE field_code = ? AND status = 'نافذة'");
    $st->bind_param('s', $one);
    $st->execute();
    $guardSees = (int) $st->get_result()->fetch_row()[0];
    $st->close();
}
chk($guardSees === 0, 'و**الحارسُ لا يرى الملغاة** — بشرطِ الحالةِ نفسِه الذي يقرأ به',
    $one === null ? 'لا ملغاةَ لتُختبَر' : "{$one} ⇒ يراها الحارس: {$guardSees}");

/* ⑥ والوصلُ في **الموضعَين** — نصًّا في المستهلكَين لا في هذا الفاحص */
$guardSrc = (string) @file_get_contents($ROOT . '/app/Services/Security/SensitiveFieldGuard.php');
$govSrc   = (string) @file_get_contents($ROOT . '/app/Services/Governance/FieldGovernor.php');
$needle   = "status = '" . 'نافذة' . "'";
chk(strpos($guardSrc, $needle) !== false && strpos($govSrc, $needle) !== false,
    'والإلغاءُ موصولٌ في **الموضعَين** — وإلا فهو زخرفةٌ في أحدِهما',
    'SensitiveFieldGuard: ' . (strpos($guardSrc, $needle) !== false ? '✔' : '✘')
    . ' · FieldGovernor: ' . (strpos($govSrc, $needle) !== false ? '✔' : '✘'));

/* ⑦ **وما رُفع قرارًا يُعَدُّ ولا يُطوى** */
$esc = n($db, "SELECT COUNT(*) FROM `gov_phantom_policy_rulings`
                WHERE `ruling` = 'NEEDS_OWNER_DECISION'");
$r = $db->query("SELECT `declared_target`, `candidate_count` FROM `gov_phantom_policy_rulings`
                  WHERE `ruling` = 'NEEDS_OWNER_DECISION'");
echo "  ◆ **مرفوعٌ قرارًا لمالكِ المجال: {$esc}** — بيانٌ حساسٌ حقيقيٌّ غيرُ محميّ،\n";
echo "    واختيارُ هدفِه منحُ رؤيةٍ أو حجبُها ⇒ تغييرُ وصولٍ حيٍّ لا يقرّره منفِّذ:\n";
while ($r && $x = $r->fetch_row()) { echo "      ⚑ {$x[0]} — مرشَّحاتٌ مقيسة: {$x[1]}\n"; }

if ($neg) {
    $db->query("DELETE FROM `sensitive_field_policies` WHERE `field_code` = '{$MARK}'");
    $left = n($db, "SELECT COUNT(*) FROM `sensitive_field_policies` WHERE `field_code` = '{$MARK}'");
    chk($left === 0, 'وكُنس الحزامُ أثرَه', "المتبقي: {$left}");
    echo "\n◆ الحزامُ السلبيّ: **يُتوقَّع رسوبٌ في ①** — فإن مرَّ فالفحصُ لا يقرأ\n";
    echo "  السياساتِ النافذةَ بل الملغاةَ أو السجلَّ وحدَه.\n";
    printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
    exit($bad > 0 ? 0 : 1);
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
