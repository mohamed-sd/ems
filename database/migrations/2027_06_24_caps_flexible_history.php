<?php
/**
 * 2027_06_24_caps_flexible_history.php
 * ═══════════════════════════════════════════════════════════════════════════
 * قرارُ المالكِ ⑥ (2026-08-18): السقوفُ قيمٌ ابتدائيةٌ معتمدةٌ الآن — وتصير
 * مرنةً من الشاشةِ بلا تعديلٍ برمجيّ:
 *   ① سجلٌّ تاريخيٌّ `gov_cap_history`: لا يُحذف قديمٌ — لكلِّ تغييرٍ المبلغُ
 *     والعملةُ وتاريخُ السريانِ ومن غيَّره ولماذا.
 *   ② التعديلُ باعتمادِ المالكِ وحدَه (الدورُ 9 — الإدارةُ التنفيذية) يفرضه
 *     قادحٌ على الإدراج.
 *   ③ يسري على الجديدِ فقط: عمودُ `cap_snapshot` على سجلِّ سلاسلِ المعاملاتِ
 *     يُختم عندَ الإنشاءِ — وما كان قيدَ الاعتمادِ يُكمل بسقفِه وقتَ إنشائِه.
 *   ④ السلوكُ القائمُ باقٍ: غيرُ المحسومِ يوقف (chk_ld_cap) ببطاقةِ
 *     «بانتظارِ اعتمادِ السقف» لا كعطل.
 *   والستةُ الباقيةُ تُعرض على المالكِ معَ بناءِ سلاسلِها (صفوفُها تُبذر هنا
 *   بحالةِ «سلّمُها لم يُبنَ» فتظهر في الشاشةِ كاملةَ التسعة).
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
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };
$run = function (string $s, string $l) use ($conn) {
    if ($conn->query($s) === false) { echo "   ✗ {$l}: {$conn->error}\n"; return false; }
    echo "   ✔ {$l}\n"; return true;
};

echo "\n▐ ① السجلُّ التاريخيُّ للسقوف\n";
$run("CREATE TABLE IF NOT EXISTS `gov_cap_history` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `company_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `ladder_code` VARCHAR(10) NOT NULL COMMENT 'LD-01..LD-20',
        `cap_amount` DECIMAL(16,2) NULL COMMENT 'NULL = غيرُ محسومٍ (يوقف)',
        `cap_currency` VARCHAR(8) NOT NULL DEFAULT 'USD',
        `effective_from` DATETIME NOT NULL COMMENT 'يسري على ما يُنشأ بعدَه فقط',
        `changed_by` INT NOT NULL COMMENT 'باعتمادِ المالكِ وحدَه — يفرضه القادح',
        `reason` VARCHAR(300) NOT NULL COMMENT 'لماذا — يُقرأ بعدَ سنة',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `ix_ladder` (`ladder_code`, `effective_from`),
        CONSTRAINT `chk_cap_reason` CHECK (`reason` <> '')
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='قرارُ المالكِ ⑥ — سقوفٌ مرنةٌ بسجلٍّ لا يُحذف: من غيَّر ومتى ولماذا'",
    'gov_cap_history');

echo "\n▐ ② قادحُ «باعتمادِ المالكِ وحدَه» (الدورُ 9)\n";
$conn->query("DROP TRIGGER IF EXISTS `trg_cap_owner_only`");
$run("CREATE TRIGGER `trg_cap_owner_only` BEFORE INSERT ON `gov_cap_history` FOR EACH ROW
      BEGIN
        DECLARE r INT DEFAULT NULL;
        SELECT `role` INTO r FROM `users` WHERE `id` = NEW.`changed_by` LIMIT 1;
        IF r IS NULL OR r <> 9 THEN
          SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'تعديلُ السقفِ باعتمادِ المالكِ وحدَه (الدور 9)';
        END IF;
      END", 'trg_cap_owner_only');

echo "\n▐ ③ بذرُ التسعةِ: الثلاثةُ الساريةُ بأثرٍ رجعيٍّ موثَّق · والستةُ بانتظارِ سلاليمِها\n";
$owner = (int) $one("SELECT id FROM users WHERE role = 9 AND status = 'active' ORDER BY id LIMIT 1");
$seed = $conn->prepare(
    "INSERT INTO gov_cap_history (ladder_code, cap_amount, cap_currency, effective_from, changed_by, reason)
     SELECT ?, ?, 'USD', NOW(), ?, ?
      WHERE NOT EXISTS (SELECT 1 FROM gov_cap_history WHERE ladder_code = ?)");
$INIT = array(
    array('LD-05', 2000.00,  'قيمةٌ ابتدائيةٌ أقرَّها المالكُ 2026-08-18 — مشتقةٌ من مئيناتِ 5,069 واقعة (P99=2,935)'),
    array('LD-06', 5000.00,  'قيمةٌ ابتدائيةٌ أقرَّها المالكُ 2026-08-18 — فوقَ P95 الشهريِّ بثلاثةِ أمثال'),
    array('LD-07', 10000.00, 'قيمةٌ ابتدائيةٌ أقرَّها المالكُ 2026-08-18 — دونَ أقصى المجمَّعِ الشهريِّ الحي (15,444)'),
);
foreach ($INIT as $i) {
    list($lc, $amt, $rs) = $i;
    $seed->bind_param('sdiss', $lc, $amt, $owner, $rs, $lc);
    $seed->execute();
}
// الستةُ الباقية: صفوفُ انتظارٍ بلا مبلغٍ — «سلّمُها لم يُبنَ» فتكتمل التسعةُ في الشاشة
$PENDING6 = array('LD-08' => 'طلبُ الدفع', 'LD-09' => 'الخزينة', 'LD-10' => 'طلبُ الشراء',
                  'LD-11' => 'أمرُ الشراء', 'LD-12' => 'الاستلام', 'LD-13' => 'التسويةُ النهائية');
foreach ($PENDING6 as $lc => $nm) {
    $rs = "بانتظارِ بناءِ سلّمِ {$nm} — يُعرض على المالكِ معَ بنائِه (قرار ⑥)";
    $amt = null;
    $seed->bind_param('sdiss', $lc, $amt, $owner, $rs, $lc);
    $seed->execute();
}
$seed->close();
printf("   ✔ صفوفُ السجل: %s (3 ساريةً + 6 انتظارًا)\n", $one("SELECT COUNT(*) FROM gov_cap_history"));

echo "\n▐ ④ لقطةُ السقفِ عندَ الإنشاء — «قيدُ الاعتمادِ يُكمل بسقفِه»\n";
$has = (int) $one("SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unit_approvals' AND COLUMN_NAME = 'cap_snapshot'");
if ($has === 0) {
    $run("ALTER TABLE `unit_approvals`
            ADD COLUMN `cap_snapshot` DECIMAL(16,2) NULL
                COMMENT 'سقفُ السلّمِ لحظةَ فتحِ حلقةِ الاعتماد — التعديلُ اللاحقُ لا يمسُّها (قرار ⑥)',
            ADD COLUMN `cap_currency_snapshot` VARCHAR(8) NULL",
        'unit_approvals.cap_snapshot');
} else { echo "   · قائمٌ سلفًا\n"; }

echo "\n▐ ⑤ السلبيّ: تغييرٌ من غيرِ المالكِ يُرفض\n";
$rogue = (int) $one("SELECT id FROM users WHERE role = 17 AND status = 'active' LIMIT 1");
$neg = $conn->query("INSERT INTO gov_cap_history (ladder_code, cap_amount, effective_from, changed_by, reason)
                     VALUES ('LD-05', 9999, NOW(), {$rogue}, 'اختبار سلبي')");
echo $neg === false ? "   ✔ رُفض ({$conn->errno})\n" : "   ✗ مرَّ!\n";
if ($neg !== false) { $conn->query("DELETE FROM gov_cap_history WHERE reason = 'اختبار سلبي'"); }

echo "\n✔ السقوفُ مرنةٌ بسجلٍّ وسريانٍ على الجديدِ فقط — والشاشةُ التالية\n";
