<?php
/**
 * 2027_02_01 — TS-04 · TS-05 · TS-16: مطابقةُ العميلِ وبوابةُ المبيعاتِ والنزاع
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ المرجع: `docs/specs/SPEC_TIMESHEET_CYCLE_ar.md` — المواصفةُ الحاكمةُ بقرارِ
 *   المالك. وهذه الهجرةُ تُنفّذ ثلاثةَ أحكامٍ منها بأعيانها:
 *
 *   `TS-04` مطابقةُ العميلِ تُنتج **واحدةً من أربع**: `matched` ·
 *     `mismatched` · `client_data_unavailable` · `client_response_overdue`.
 *     (وأُضيفت `pending` حالًا ابتدائيًّا — فالمطابقةُ لم تقع بعد، وهي ليست
 *     خامسةً في الحكم بل «قبل الحكم».)
 *
 *   `TS-05` بوابةُ مدير المبيعات **قرارٌ من خيارين لا تجاوزٌ دائم**؛ وقرارُ
 *     التجاوزِ يحمل **سبعةَ حقولٍ إلزامية**: السبب · الدليل · مَن أصدره ·
 *     التاريخ والوقت · نطاقُ الوحدات · وهل يسمح بالأثرِ الأوليِّ فقط أم بالفوترة.
 *     ⇒ جدولٌ مستقلٌّ `unit_match_overrides` لأن القرارَ **واقعةٌ لها سجلُّها**
 *     لا عمودٌ في صفٍّ آخر.
 *
 *   `TS-16` رفضُ العميلِ ⇒ `client_decision='disputed'` **بمرجعِ ملفِّ اختلافٍ
 *     إلزاميّ**، والقبولُ الجزئيُّ يُمثَّل بأن القرارَ **لكلِّ مدخلٍ على حدة**
 *     فلا يُوقف المقبولُ بسبب المختلفِ عليه.
 *
 * ◆ **ولا آلةَ حالاتٍ ثانية:** `unit_entries.state` تبقى حالَ الدورة، وهذه
 *   أعمدةٌ **متعامدةٌ** عليها (مطابقةٌ · قرارُ عميل) — فالخلطُ بينهما يُنتج
 *   تعدادًا يحمل معنيين.
 *
 * ◆ **والقيودُ بنيويةٌ لا تعبدية**: حالةُ مطابقةٍ محسومةٌ بلا وقتٍ ولا يدٍ تُرفض ·
 *   ونزاعٌ بلا مرجعِ ملفٍّ يُرفض · وقرارُ تجاوزٍ بسببٍ فارغٍ يُرفض.
 *   (وگوتشا مقيسةٌ سابقًا: CHECK لا يُقبل على عمودٍ في FK بـ`ON UPDATE CASCADE`.)
 *
 * ◆ مُتحمِّلٌ للتكرار · ويُجَسُّ كلُّ قيدٍ حيًّا قبل إعلانِ النجاح.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);   // گوتشا: بلا config ينفُذ افتراضُ الرمي
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

function col_exists(mysqli $db, $t, $c)
{
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $st->bind_param('ss', $t, $c); $st->execute();
    $n = (int) $st->get_result()->fetch_row()[0]; $st->close();
    return $n > 0;
}
function chk_exists(mysqli $db, $n)
{
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                         WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME=?");
    $st->bind_param('s', $n); $st->execute();
    $x = (int) $st->get_result()->fetch_row()[0]; $st->close();
    return $x > 0;
}
function tbl_exists(mysqli $db, $t)
{
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $st->bind_param('s', $t); $st->execute();
    $n = (int) $st->get_result()->fetch_row()[0]; $st->close();
    return $n > 0;
}
function run(mysqli $db, $sql, $what)
{
    if (!$db->query($sql)) { fwrite(STDERR, "{$what}: " . $db->error . "\n"); exit(1); }
    echo "    ✔ {$what}\n";
}

/* ══ ① أعمدةُ المطابقةِ وقرارِ العميلِ على المدخل ═══════════════════════ */
echo "① TS-04 · TS-16 — أعمدةُ المطابقةِ وقرارِ العميل:\n";
$ADD = array(
    'client_match_state' => "ENUM('pending','matched','mismatched','client_data_unavailable','client_response_overdue')
                             NOT NULL DEFAULT 'pending'
                             COMMENT 'TS-04 — نتيجةُ مطابقةِ نسخةِ العميل'",
    'client_match_at'    => "DATETIME NULL COMMENT 'TS-04 — لحظةُ حسمِ المطابقة'",
    'client_match_by'    => "INT(10) UNSIGNED NULL COMMENT 'TS-04 — يدُ من حسمها'",
    'client_match_ref'   => "VARCHAR(120) NULL COMMENT 'TS-04 — مرجعُ دليلِ المطابقة'",
    'client_decision'    => "ENUM('pending','accepted','disputed') NOT NULL DEFAULT 'pending'
                             COMMENT 'TS-16 — قرارُ العميلِ على هذا المدخلِ وحدَه (القبولُ الجزئيُّ لكلِّ مدخل)'",
    'dispute_ref'        => "VARCHAR(120) NULL COMMENT 'TS-16 — مرجعُ ملفِّ الاختلافِ — إلزاميٌّ عند النزاع'",
);
foreach ($ADD as $c => $def) {
    if (col_exists($db, 'unit_entries', $c)) { echo "    · {$c} قائم\n"; continue; }
    run($db, "ALTER TABLE `unit_entries` ADD COLUMN `{$c}` {$def}", "عمود {$c}");
}
foreach (array('idx_ue_match' => 'client_match_state', 'idx_ue_cdec' => 'client_decision') as $i => $on) {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
                         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unit_entries' AND INDEX_NAME=?");
    $st->bind_param('s', $i); $st->execute();
    $ex = (int) $st->get_result()->fetch_row()[0]; $st->close();
    if ($ex) { echo "    · فهرس {$i} قائم\n"; continue; }
    run($db, "CREATE INDEX `{$i}` ON `unit_entries` (`{$on}`)", "فهرس {$i}");
}

/* ══ ② القيودُ البنيوية ════════════════════════════════════════════════ */
echo "② القيودُ البنيوية:\n";
if (!chk_exists($db, 'chk_ue_match_evidence')) {
    run($db, "ALTER TABLE `unit_entries` ADD CONSTRAINT `chk_ue_match_evidence` CHECK (
                  `client_match_state` = 'pending'
               OR (`client_match_at` IS NOT NULL AND `client_match_by` IS NOT NULL)
              )", 'قيدُ سندِ المطابقة (حالةٌ محسومةٌ بلا وقتٍ ولا يدٍ تُرفض)');
} else { echo "    · قيدُ سندِ المطابقةِ قائم\n"; }
if (!chk_exists($db, 'chk_ue_dispute_ref')) {
    run($db, "ALTER TABLE `unit_entries` ADD CONSTRAINT `chk_ue_dispute_ref` CHECK (
                  `client_decision` <> 'disputed' OR `dispute_ref` IS NOT NULL
              )", 'قيدُ مرجعِ النزاع (نزاعٌ بلا ملفٍّ يُرفض)');
} else { echo "    · قيدُ مرجعِ النزاعِ قائم\n"; }

/* ══ ③ سجلُّ قرارِ تجاوزِ المطابقة — TS-05 ═══════════════════════════════ */
echo "③ TS-05 — سجلُّ قرارِ بوابةِ المبيعات:\n";
if (!tbl_exists($db, 'unit_match_overrides')) {
    run($db, "CREATE TABLE `unit_match_overrides` (
        `id`            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `company_id`    INT(10) UNSIGNED NOT NULL,
        `entry_id`      INT(10) UNSIGNED NOT NULL COMMENT 'المدخلُ المشمول',
        `reason`        VARCHAR(300) NOT NULL COMMENT 'TS-05-ب ① السبب',
        `evidence_ref`  VARCHAR(160) NULL     COMMENT 'TS-05-ب ② الدليلُ المتاح',
        `decided_by`    INT(10) UNSIGNED NOT NULL COMMENT 'TS-05-ب ③ مَن أصدره',
        `decided_at`    DATETIME NOT NULL DEFAULT current_timestamp() COMMENT 'TS-05-ب ④ التاريخُ والوقت',
        `scope_note`    VARCHAR(300) NOT NULL COMMENT 'TS-05-ب ⑤ نطاقُ الوحداتِ المشمولة',
        `allows`        ENUM('primary_only','billing') NOT NULL
                        COMMENT 'TS-05-ب ⑥ أيسمح بالأثرِ الأوليِّ فقط أم بالفوترة',
        `match_state_at_decision` ENUM('pending','matched','mismatched','client_data_unavailable','client_response_overdue')
                        NOT NULL COMMENT 'TS-05-ب ⑦ حالُ المطابقةِ لحظةَ القرار — فلا يُعاد تفسيرُه لاحقًا',
        `created_at`    DATETIME NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_umo_entry` (`entry_id`),
        KEY `idx_umo_co` (`company_id`),
        CONSTRAINT `fk_umo_entry` FOREIGN KEY (`entry_id`) REFERENCES `unit_entries` (`id`)
            ON DELETE RESTRICT ON UPDATE RESTRICT,
        CONSTRAINT `chk_umo_fields` CHECK (
            `reason` <> '' AND `scope_note` <> '' AND `decided_by` > 0
        ),
        CONSTRAINT `chk_umo_not_matched` CHECK (
            `match_state_at_decision` <> 'matched'
        )
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        COMMENT='TS-05 — قرارُ تجاوزِ مطابقةِ العميلِ بسبعةِ حقولٍ إلزامية'",
        'جدولُ قراراتِ التجاوز');
} else { echo "    · الجدولُ قائم\n"; }

/* ══ ④ الإثباتُ — كلُّ قيدٍ يُجَسُّ ولا يُدَّعى ═══════════════════════════ */
echo "④ الإثبات (جسٌّ حيٌّ داخلَ معاملةٍ ثم تراجُع):\n";
$rs = $db->query('SELECT id, company_id FROM unit_entries LIMIT 1');
$e = $rs ? $rs->fetch_assoc() : null;
if (!$e) { fwrite(STDERR, "لا مدخلَ للجسّ\n"); exit(1); }
$eid = (int) $e['id']; $co = (int) $e['company_id'];

$db->query('START TRANSACTION');
$a = $db->query("UPDATE unit_entries SET client_match_state='matched',
                  client_match_at=NULL, client_match_by=NULL WHERE id={$eid}");
echo '    ' . ($a === false ? '✔' : '✘') . ' مطابقةٌ محسومةٌ بلا وقتٍ ولا يدٍ: '
   . ($a === false ? 'رُفضت — ' . mb_substr($db->error, 0, 46) : '**نفذت! القيدُ لا يمنع**') . "\n";
$db->query('ROLLBACK');

$db->query('START TRANSACTION');
$b = $db->query("UPDATE unit_entries SET client_decision='disputed', dispute_ref=NULL WHERE id={$eid}");
echo '    ' . ($b === false ? '✔' : '✘') . ' نزاعٌ بلا مرجعِ ملفٍّ: '
   . ($b === false ? 'رُفض' : '**نفذ! القيدُ لا يمنع**') . "\n";
$db->query('ROLLBACK');

$db->query('START TRANSACTION');
$c = $db->query("INSERT INTO unit_match_overrides
    (company_id, entry_id, reason, decided_by, scope_note, allows, match_state_at_decision)
    VALUES ({$co}, {$eid}, '', 7, 'نطاق', 'primary_only', 'client_data_unavailable')");
echo '    ' . ($c === false ? '✔' : '✘') . ' قرارُ تجاوزٍ بسببٍ فارغ: '
   . ($c === false ? 'رُفض' : '**نفذ! القيدُ لا يمنع**') . "\n";
$d = $db->query("INSERT INTO unit_match_overrides
    (company_id, entry_id, reason, decided_by, scope_note, allows, match_state_at_decision)
    VALUES ({$co}, {$eid}, 'لا مشرفَ للعميل', 7, 'وحداتُ يونيو', 'primary_only', 'matched')");
echo '    ' . ($d === false ? '✔' : '✘') . ' تجاوزٌ ومطابقتُه **مكتملة**: '
   . ($d === false ? 'رُفض — لا تجاوزَ لما طابق' : '**نفذ! القيدُ لا يمنع**') . "\n";
$f = $db->query("INSERT INTO unit_match_overrides
    (company_id, entry_id, reason, decided_by, scope_note, allows, match_state_at_decision)
    VALUES ({$co}, {$eid}, 'لا مشرفَ للعميل', 7, 'وحداتُ يونيو', 'primary_only', 'client_data_unavailable')");
echo '    ' . ($f !== false ? '✔' : '✘') . ' وقرارٌ مستوفٍ: '
   . ($f !== false ? 'قُبل — القيدُ لا يمنع الصحيح' : 'رُفض! — ' . mb_substr($db->error, 0, 50)) . "\n";
$db->query('ROLLBACK');

if ($a !== false || $b !== false || $c !== false || $d !== false || $f === false) {
    fwrite(STDERR, "الإثباتُ لم يستوفِ شرطَه — لا يُعلَن نجاح\n"); exit(1);
}
echo "\n  الشاهد: php tools/fix_ts04_tests.php\n";
exit(0);
