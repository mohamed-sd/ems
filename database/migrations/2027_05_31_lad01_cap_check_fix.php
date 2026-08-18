<?php
/**
 * 2027_05_31_lad01_cap_check_fix.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تصحيحُ `chk_ld_cap` وإكمالُ LD-05 · LD-06 · LD-07
 * ───────────────────────────────────────────────────────────────────────────
 * القيدُ كما أُنشئ:
 *   cap_kind <> 'amount' OR (cap_state='resolved' AND amount IS NOT NULL AND currency IS NOT NULL)
 * وهو **يناقض البندَ ③ نفسَه**: يشترط أن يكون كلُّ سقفِ مبلغٍ محسومًا، فيمنع
 * تسجيلَ سلّمٍ بسقفٍ غيرِ محسوم — وحالُ «غيرِ المحسوم» هي بعينُها الـfail-closed
 * المطلوب: يُسجَّل السلّمُ ويُوقَف حتى يُعتمد الرقم، لا أن يُمنع تسجيلُه.
 *
 * والصواب: **المحسومُ** وحدَه يلزمه رقمٌ وعملة.
 *
 * ◆ ودرسٌ مقيس: حذفي للجدولين قبلَ إعادةِ الهجرة جرى باتصالِ التطبيق
 *   (`ems_app` — DML فقط بعد ADR-04)، فسقط DROP صامتًا وبقي القيدُ القديم،
 *   و`CREATE TABLE IF NOT EXISTS` تخطّى. فـDDL بمُرحِّلٍ لا بتطبيق.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$run = function (string $s, string $l) use ($conn): bool {
    if ($conn->query($s)) { echo "   ✔ $l\n"; return true; }
    echo "   ✗ $l — " . $conn->error . "\n"; return false;
};
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };

echo "\n▐ ① تصحيحُ chk_ld_cap — المحسومُ وحدَه يلزمه رقمٌ وعملة\n";
$cur = (string) $one("SELECT COALESCE(CHECK_CLAUSE,'') FROM information_schema.CHECK_CONSTRAINTS
                       WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_ld_cap'");
echo "   القيدُ قبل: " . ($cur !== '' ? mb_substr($cur, 0, 110) : '(غير موجود)') . "\n";
if ($cur !== '') { $conn->query("ALTER TABLE `gov_ladders` DROP CONSTRAINT `chk_ld_cap`"); }
$run("ALTER TABLE `gov_ladders` ADD CONSTRAINT `chk_ld_cap` CHECK (
        `cap_state` <> 'resolved'
        OR `cap_kind` <> 'amount'
        OR (`cap_amount` IS NOT NULL AND `cap_currency` IS NOT NULL)
      )", 'chk_ld_cap ⇐ يسمح بسقفٍ غيرِ محسومٍ موقوف');

echo "\n▐ ② إكمالُ LD-05 · LD-06 · LD-07 (بوابةُ المالية وما بعدَها)\n";
$LAD = array(
    array('LD-05','unit_fin_prelim',    'الاعتمادُ الماليُّ الأوليّ',  7, 24, 'الواقعةُ مؤهَّلةٌ للفوترة'),
    array('LD-06','unit_client_invoice','إصدارُ المستخلصِ والفاتورة',  8, 72, 'مستخلصٌ وفاتورةٌ صادرة'),
    array('LD-07','unit_fin_final',     'الاعتمادُ الماليُّ النهائيّ', 9, 24, 'القيدُ مُرحَّلٌ والإيرادُ معترَفٌ به'),
);
$li = $conn->prepare(
    "INSERT INTO `gov_ladders`
        (`ladder_code`,`company_id`,`slug`,`name_ar`,`cycle_no`,`escalate_after_hours`,
         `cap_kind`,`cap_state`,`payload_note`,`doc_ref`,`is_active`)
     VALUES (?,0,?,?,?,?,'amount','unresolved',?, 'LAD-01', 1)
     ON DUPLICATE KEY UPDATE `slug`=VALUES(`slug`), `name_ar`=VALUES(`name_ar`),
        `cycle_no`=VALUES(`cycle_no`), `escalate_after_hours`=VALUES(`escalate_after_hours`),
        `payload_note`=VALUES(`payload_note`), `is_active`=1"
);
foreach ($LAD as $L) {
    list($c, $s, $n, $cy, $e, $pay) = $L;
    $li->bind_param('sssiis', $c, $s, $n, $cy, $e, $pay);
    if ($li->execute()) { echo "   ✔ $c — $n (سقفٌ غيرُ محسومٍ ⇐ موقوف)\n"; }
    else { echo "   ✗ $c — " . $li->error . "\n"; }
}
$li->close();

echo "\n▐ ③ خطواتُها — والمحاسبُ مسموحٌ هنا لأنها بوابةُ المالية وما بعدَها\n";
$STEPS = array(
    array('LD-05',1,'finance_accountant','محاسب المالية','review',1,1,0,null),
    array('LD-05',2,'finance_manager','مدير الإدارة المالية','approve',0,1,1,null),
    array('LD-06',1,'finance_accountant','محاسب المالية','review',1,1,0,null),
    array('LD-06',2,'client_acceptance','قبول العميل','approve',0,1,1,'لا فاتورةَ نافذةٌ بلا قبولٍ مسجَّل'),
    array('LD-07',1,'finance_manager','مدير الإدارة المالية','review',0,1,0,null),
    array('LD-07',2,'cfo','المدير المالي','approve',0,1,1,'◆ لا يعتمد من أعدَّ القيدَ نفسَه'),
);
$conn->query("DELETE FROM `gov_ladder_steps` WHERE `ladder_code` IN ('LD-05','LD-06','LD-07')");
$si = $conn->prepare(
    "INSERT INTO `gov_ladder_steps`
        (`company_id`,`ladder_code`,`step_no`,`actor_code`,`actor_name_ar`,`step_kind`,
         `is_accountant`,`is_finance_gate`,`may_approve`,`forbid_note`)
     VALUES (0,?,?,?,?,?,?,?,?,?)"
);
$n = 0;
foreach ($STEPS as $S) {
    list($c, $no, $ac, $an, $k, $isAcc, $isFin, $may, $note) = $S;
    $si->bind_param('sisssiiis', $c, $no, $ac, $an, $k, $isAcc, $isFin, $may, $note);
    if ($si->execute()) { $n++; } else { echo "   ✗ $c/$no — " . $si->error . "\n"; }
}
$si->close();
echo "   ✔ $n خطوة\n";

echo "\n▐ ④ التحقُّق\n";
printf("   السلاليم              : %s/7\n", $one("SELECT COUNT(*) FROM `gov_ladders`"));
printf("   الخطوات               : %s\n",   $one("SELECT COUNT(*) FROM `gov_ladder_steps`"));
printf("   محاسبٌ قبلَ المالية    : %s   [المتوقَّع 0]\n",
    $one("SELECT COUNT(*) FROM `gov_ladder_steps` WHERE `is_accountant`=1 AND `is_finance_gate`=0"));
printf("   مدخلٌ يعتمد            : %s   [المتوقَّع 0]\n",
    $one("SELECT COUNT(*) FROM `gov_ladder_steps` WHERE `step_kind`='entry' AND `may_approve`=1"));
printf("   سلاليمُ بسقفٍ موقوف    : %s   [③ fail-closed]\n",
    $one("SELECT COUNT(*) FROM `gov_ladders` WHERE `cap_kind`='amount' AND `cap_state`<>'resolved'"));
printf("   سلّمٌ لكلِّ مرحلةِ دورة : %s/9 مرحلةً مغطّاة\n",
    $one("SELECT COUNT(DISTINCT `cycle_no`) FROM `gov_ladders` WHERE `cycle_no` IS NOT NULL"));
echo "\n";
