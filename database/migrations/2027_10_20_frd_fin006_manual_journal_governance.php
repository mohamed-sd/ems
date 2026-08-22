<?php
/**
 * 2027_10_20_frd_fin006_manual_journal_governance.php
 *   FR-FIN-006 · CHG-FIN-INTEGRITY-01 — حوكمةُ القيدِ اليدويّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلبُ بنصِّه** (الدفتر · P1) و**§سابعًا من الأمرِ الحاكم**: «كلُّ
 *   Manual Journal يحمل: **النوعَ · المستندَ · السببَ · المُعِدَّ · المعتمِدَ ·
 *   مرجعَ الاعتمادِ · الفترةَ · رابطَ العكس**» · وسلوكُ الفشل «**رفضُ القيدِ
 *   الناقص**» · ومعيارُ القبول «نسبةُ المحوكَمِ إلى الإجماليّ — والحكمُ بعدَ
 *   القياس».
 *
 * ◆ **والمقيسُ اليوم**: 6713 قيدًا، **1644 منها بلا حدث** (يدويّ). ولها
 *   السببُ (`memo` إلزاميٌّ في الشاشة) والمُعِدُّ والمعتمِد — **وليس لها
 *   نوعٌ ولا مستندٌ ولا مرجعُ اعتمادٍ ولا فترةٌ ولا رابطُ عكس**، ولا أعمدةَ
 *   لها أصلًا. فالحوكمةُ ناقصةٌ خمسةَ أثمان.
 *
 * ◆ **ولا تُملأ الألفُ والستُّمئةُ بأثرٍ رجعيّ**: اختلاقُ مستندٍ لقيدٍ مضى
 *   **تزويرُ سجلٍّ باسمِ الحوكمة**. فتُوسَم `PRE_GOVERNANCE` صراحةً — مرئيةً
 *   في المقامِ لا مخفيّة — ويسري القيدُ على ما بعدَها.
 *
 * ◆ **والرفضُ من القاعدةِ لا من الشاشة**: قيدُ `CHECK` يمنع صفًّا محكومًا
 *   ناقصًا. و`ems_migrator` **لا يملك امتيازَ القوادح** (لا SUPER مع سجلٍّ
 *   ثنائيّ) — فالقيدُ `CHECK` هو الأداةُ المتاحةُ وهو كافٍ لشرطٍ على مستوى
 *   الصف. (قِيس: محاولةُ إنشاءِ قادحٍ رُدَّت.)
 *
 * ◆ **ومرجعُ الاعتمادِ يُطلب عندَ الترحيلِ لا عندَ الإنشاء** — فالمسوَّدةُ لا
 *   معتمِدَ لها بعد. والقيدُ يُفرِّق بينهما بـ`state`.
 *
 * التشغيل:  php database/migrations/2027_10_20_frd_fin006_manual_journal_governance.php
 * الرجوع :  php database/migrations/2027_10_20_frd_fin006_manual_journal_governance.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

function cnt(mysqli $c, $sql) { $r = @$c->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; }
function colHas(mysqli $c, $t, $col) {
    return cnt($c, "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}' AND COLUMN_NAME = '{$col}'");
}

$CHK = 'chk_manual_journal_governed';

if (in_array('--revert', $argv, true)) {
    $conn->query("ALTER TABLE `fin_journal_entries` DROP CONSTRAINT `{$CHK}`");
    foreach (array('manual_kind', 'source_doc_ref', 'approval_ref', 'reversal_link',
                   'period_code', 'manual_gov_state') as $c2) {
        $conn->query("ALTER TABLE `fin_journal_entries` DROP COLUMN `{$c2}`");
    }
    echo "↺ أُسقطت أعمدةُ حوكمةِ القيدِ اليدويِّ وقيدُها\n";
    exit(0);
}

/* ══ ① العدُّ قبلًا — بأسمائِه ═══════════════════════════════════════════ */
$total  = cnt($conn, "SELECT COUNT(*) FROM `fin_journal_entries`");
$manual = cnt($conn, "SELECT COUNT(*) FROM `fin_journal_entries`
                       WHERE `event_id` IS NULL OR `event_id` = 0");
$auto   = $total - $manual;
printf("① قبل: إجمالي=%d · **يدويٌّ=%d (%.1f٪)** · آليٌّ بحدث=%d\n",
       $total, $manual, $total ? 100 * $manual / $total : 0, $auto);

/* ══ ② الأعمدةُ الثمانيةُ — ثلاثةٌ قائمةٌ وخمسةٌ تُضاف ═════════════════════ */
$have = array('السبب' => 'memo', 'المُعِدّ' => 'created_by', 'المعتمِد' => 'posted_by');
foreach ($have as $ar => $col) {
    printf("   = %-12s ⇒ `%s` قائمٌ سلفًا\n", $ar, $col);
}
$ADD = array(
    'manual_kind'      => "VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'النوع — تسويةٌ/تصحيحٌ/إقفالٌ/عكس'",
    'source_doc_ref'   => "VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'المستندُ المصدر'",
    'approval_ref'     => "VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'مرجعُ الاعتماد'",
    'reversal_link'    => "BIGINT       NULL              COMMENT 'رابطُ العكس — القيدُ المعكوس'",
    'period_code'      => "VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'الفترةُ YYYY-MM'",
    'manual_gov_state' => "VARCHAR(20)  NOT NULL DEFAULT 'GOVERNED' COMMENT 'GOVERNED · PRE_GOVERNANCE'",
);
$made = 0;
foreach ($ADD as $col => $def) {
    if (colHas($conn, 'fin_journal_entries', $col) > 0) { echo "   = `{$col}` قائمٌ سلفًا\n"; continue; }
    if ($conn->query("ALTER TABLE `fin_journal_entries` ADD COLUMN `{$col}` {$def}")) {
        $made++; echo "   ✔ أُضيف `{$col}`\n";
    } else {
        exit("⛔ تعذّرت إضافةُ `{$col}`: " . $conn->error . "\n");
    }
}

/* ══ ③ **الوسمُ لا الملء** — لا يُختلَق مستندٌ لقيدٍ مضى ══════════════════ */
$conn->query("UPDATE `fin_journal_entries`
                 SET `manual_gov_state` = 'PRE_GOVERNANCE'
               WHERE (`event_id` IS NULL OR `event_id` = 0)
                 AND COALESCE(`source_doc_ref`,'') = ''");
$marked = $conn->affected_rows;
printf("③ وُسِم %d قيدًا يدويًّا **PRE_GOVERNANCE** — مرئيًّا في المقامِ لا ممسوحًا\n", $marked);

/* والآليُّ بحدثٍ لا يحتاج حوكمةَ يدويّ — يُوسَم GOVERNED صراحةً */
$conn->query("UPDATE `fin_journal_entries` SET `manual_gov_state` = 'GOVERNED'
               WHERE `event_id` IS NOT NULL AND `event_id` > 0");

/* ══ ④ القيدُ — يرفض المحكومَ الناقصَ ويقبل الموسومَ سلفًا ═══════════════ */
$exists = cnt($conn, "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                       WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '{$CHK}'");
if ($exists === 0) {
    /* ◆ المسوَّدةُ لا معتمِدَ لها بعد — فمرجعُ الاعتمادِ يُطلب عندَ الترحيلِ وحدَه */
    $sql = "ALTER TABLE `fin_journal_entries` ADD CONSTRAINT `{$CHK}` CHECK (
              (`event_id` IS NOT NULL AND `event_id` > 0)
              OR `manual_gov_state` = 'PRE_GOVERNANCE'
              OR (
                   TRIM(`manual_kind`)    <> ''
               AND TRIM(`source_doc_ref`) <> ''
               AND TRIM(COALESCE(`memo`,'')) <> ''
               AND TRIM(`period_code`)    <> ''
               AND `created_by` > 0
               AND (`state` <> 'posted'
                    OR (TRIM(`approval_ref`) <> '' AND COALESCE(`posted_by`,0) > 0))
              ))";
    if ($conn->query($sql)) { echo "④ ✔ قيدُ الرفضِ أُضيف — **الرفضُ من القاعدةِ لا من الشاشة**\n"; }
    else { exit("⛔ تعذّر إضافةُ القيد: " . $conn->error . "\n"); }
} else {
    echo "④ = القيدُ قائمٌ سلفًا\n";
}

/* ══ ⑤ التحقُّقُ الحيّ — أيرفض فعلًا؟ ═══════════════════════════════════ */
$co = cnt($conn, "SELECT `company_id` FROM `fin_journal_entries` LIMIT 1");
$rejected = false; $err = '';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn->query("INSERT INTO `fin_journal_entries`
        (`company_id`,`entry_no`,`posting_date`,`txn_date`,`currency`,`fx_rate`,
         `total_debit`,`total_credit`,`memo`,`state`,`created_by`,`created_at`)
        VALUES ({$co},'PROBE-FIN006',CURDATE(),CURDATE(),'SDG',1,0,0,'مسبار','draft',1,NOW())");
} catch (\Throwable $t) { $rejected = true; $err = $t->getMessage(); }
mysqli_report(MYSQLI_REPORT_OFF);
$conn->query("DELETE FROM `fin_journal_entries` WHERE `entry_no` = 'PROBE-FIN006'");
printf("⑤ التحقُّقُ الحيّ: قيدٌ يدويٌّ **ناقصُ الحوكمة** ⇒ %s\n",
       $rejected ? '**رفضٌ من القاعدة ✔** — ' . mb_substr($err, 0, 56) : 'مرَّ ✘ — القيدُ لا يعمل');
if (!$rejected) { exit("⛔ القيدُ لا يمنع — أُوقِف قبلَ إعلانِ نجاح\n"); }

/* ══ ⑥ المصالحة ═════════════════════════════════════════════════════════ */
$after   = cnt($conn, "SELECT COUNT(*) FROM `fin_journal_entries`");
$pre     = cnt($conn, "SELECT COUNT(*) FROM `fin_journal_entries` WHERE `manual_gov_state` = 'PRE_GOVERNANCE'");
$gov     = cnt($conn, "SELECT COUNT(*) FROM `fin_journal_entries` WHERE `manual_gov_state` = 'GOVERNED'");
printf("\n⑥ بعد: إجمالي=%d (%s) · PRE_GOVERNANCE=%d · GOVERNED=%d\n",
       $after, $after === $total ? '✔ لا فقد' : '✘ **فرق**', $pre, $gov);
if ($after !== $total) { exit("⛔ اختلَّ المقام\n"); }
printf("⑦ **نسبةُ المحوكَمِ من اليدويّ: %d من %d (%.1f٪)** — والحكمُ بعدَ القياسِ لا قبلَه\n",
       $manual - $pre, $manual, $manual ? 100 * ($manual - $pre) / $manual : 0);
echo "   ◆ والصفرُ هنا صادق: لا قيدَ يدويًّا محكومًا بعدُ، والقيدُ يسري على ما يأتي.\n";

ems_migration_recorded(__FILE__, $conn, 0);
