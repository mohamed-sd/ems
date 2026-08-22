<?php
/**
 * tests/injfrd01_fin006_manual_journal_governance.php
 *   شاهدُ FR-FIN-006 — القيدُ اليدويُّ لا يُقبل ناقصَ الحوكمة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **§سابعًا من الأمرِ الحاكم**: «كلُّ Manual Journal يحمل: النوعَ · المستندَ ·
 *   السببَ · المُعِدَّ · المعتمِدَ · مرجعَ الاعتمادِ · الفترةَ · **رابطَ العكس**».
 *   وسلوكُ الفشل «**رفضُ القيدِ الناقص**» · ومعيارُ القبول «نسبةُ المحوكَمِ إلى
 *   الإجماليِّ — **والحكمُ بعدَ القياس**».
 *
 * ◆ **والنسبةُ تُقاس ولا تُجمَّل**: 1644 قيدًا يدويًّا من 6713 (24.5٪)، وكلُّها
 *   `PRE_GOVERNANCE` — **صفرٌ محكومٌ منها**. والصفرُ صادقٌ لا مخجل: القيدُ
 *   يسري على ما يأتي، والماضي **يُوسَم ولا يُملأ بأثرٍ رجعيّ** — فاختلاقُ
 *   مستندٍ لقيدٍ مضى تزويرُ سجلٍّ باسمِ الحوكمة.
 *
 * ◆ **وحارسان لا حارسٌ واحد**: الشاشةُ ترفض برسالةٍ تسمّي الناقص، والقاعدةُ
 *   ترفض من يتجاوز الشاشة. ويُقاس الاثنان.
 *
 * التشغيل: php tests/injfrd01_fin006_manual_journal_governance.php [--negative]
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

$neg = in_array('--negative', $argv, true);
echo "══ FR-FIN-006 — حوكمةُ القيدِ اليدويّ (§سابعًا) ══\n";

/* ── ① بنودُ §سابعًا الثمانيةُ لها أعمدةٌ ─────────────────────────────────── */
$FIELDS = array(
    'النوع'         => 'manual_kind',
    'المستند'       => 'source_doc_ref',
    'السبب'         => 'memo',
    'المُعِدّ'        => 'created_by',
    'المعتمِد'       => 'posted_by',
    'مرجعُ الاعتماد' => 'approval_ref',
    'الفترة'        => 'period_code',
    'رابطُ العكس'    => 'reversal_link',
);
$missing = array();
foreach ($FIELDS as $ar => $col) {
    if (n($db, "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fin_journal_entries'
                   AND COLUMN_NAME = '{$col}'") === 0) { $missing[] = "{$ar} ({$col})"; }
}
chk(empty($missing), 'بنودُ §سابعًا **الثمانيةُ** لها أعمدةٌ في الدفتر',
    empty($missing) ? '8 من 8' : 'ناقصٌ: ' . implode(' · ', $missing));

/* ── ② القيدُ موجودٌ ويمنع ────────────────────────────────────────────── */
$hasChk = n($db, "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                   WHERE CONSTRAINT_SCHEMA = DATABASE()
                     AND CONSTRAINT_NAME = 'chk_manual_journal_governed'");
chk($hasChk === 1, 'وقيدُ **الرفضِ من القاعدةِ** قائم', 'chk_manual_journal_governed');

/* ── ③ **النسبةُ تُقاس** — والحكمُ بعدَها ─────────────────────────────────── */
$total  = n($db, "SELECT COUNT(*) FROM `fin_journal_entries`");
$manual = n($db, "SELECT COUNT(*) FROM `fin_journal_entries`
                   WHERE `event_id` IS NULL OR `event_id` = 0");
$pre    = n($db, "SELECT COUNT(*) FROM `fin_journal_entries`
                   WHERE `manual_gov_state` = 'PRE_GOVERNANCE'");
$govMan = $manual - $pre;
printf("\n  المقام: إجمالي=%d · **يدويّ=%d (%.1f٪)** · موسومٌ قبلَ الحوكمة=%d · محكومٌ=%d\n",
       $total, $manual, $total ? 100 * $manual / $total : 0, $pre, $govMan);
chk($manual > 0, '**المقامُ غيرُ صفريّ** — ثمَّ قيودٌ يدويةٌ يُحكَم عليها', "{$manual} قيدًا");
echo "  ◆ **ولا يُملأ الماضي بأثرٍ رجعيّ**: اختلاقُ مستندٍ لقيدٍ مضى تزويرُ سجلٍّ\n";
echo "    باسمِ الحوكمة. فالموسومُ مرئيٌّ في المقامِ لا ممسوحٌ منه.\n";

/* ── ④ ولا صفَّ محكومٍ ناقص — القيدُ يضمنه والقياسُ يشهد ─────────────────── */
$badGov = n($db, "SELECT COUNT(*) FROM `fin_journal_entries`
                   WHERE `manual_gov_state` = 'GOVERNED'
                     AND (`event_id` IS NULL OR `event_id` = 0)
                     AND (TRIM(`manual_kind`) = '' OR TRIM(`source_doc_ref`) = ''
                          OR TRIM(COALESCE(`memo`,'')) = '' OR TRIM(`period_code`) = ''
                          OR COALESCE(`created_by`,0) = 0)");
chk($badGov === 0, 'FR-FIN-006 · **صفرُ قيدٍ محكومٍ ناقصِ البنود**', "ناقصٌ: {$badGov}");

$badPosted = n($db, "SELECT COUNT(*) FROM `fin_journal_entries`
                      WHERE `manual_gov_state` = 'GOVERNED' AND `state` = 'posted'
                        AND (`event_id` IS NULL OR `event_id` = 0)
                        AND (TRIM(`approval_ref`) = '' OR COALESCE(`posted_by`,0) = 0)");
chk($badPosted === 0, 'ولا مرحَّلٌ بلا **مرجعِ اعتمادٍ ومعتمِد**', "ناقصٌ: {$badPosted}");

/* ── ⑤ الشاشةُ حارسٌ أوّلُ لا وحيد ───────────────────────────────────────── */
$scr = (string) @file_get_contents($ROOT . '/Finance/journal_form_fin.php');
$guards = 0;
foreach (array('نوع القيد إلزامي', 'المستند المصدر إلزامي', 'رابط القيد المعكوس') as $g) {
    if (strpos($scr, $g) !== false) { $guards++; }
}
chk($guards === 3, 'والشاشةُ ترفض **برسالةٍ تسمّي الناقص** — حارسان لا حارسٌ واحد',
    "{$guards} من 3 رسائل");
chk(strpos($scr, "'manual_gov_state' => 'GOVERNED'") !== false,
    'والقيدُ الجديدُ يُكتب **محكومًا** لا موسومًا قبلَ الحوكمة');
chk(strpos($scr, 'approval_ref') !== false,
    'ومرجعُ الاعتمادِ يُولَد **عندَ الترحيل** — فالمسوَّدةُ لا معتمِدَ لها بعد');

if ($neg) {
    /* ◆ الحزامُ يحاول قيدًا يدويًّا ناقصًا — والقاعدةُ يجب أن تردَّه */
    $co = n($db, "SELECT `company_id` FROM `fin_journal_entries` LIMIT 1");
    $rejected = false; $err = '';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $db->query("INSERT INTO `fin_journal_entries`
            (`company_id`,`entry_no`,`posting_date`,`txn_date`,`currency`,`fx_rate`,
             `total_debit`,`total_credit`,`memo`,`state`,`created_by`,`created_at`)
            VALUES ({$co},'BELT-FIN006',CURDATE(),CURDATE(),'SDG',1,0,0,'حزام','draft',1,NOW())");
    } catch (\Throwable $t) { $rejected = true; $err = $t->getMessage(); }
    mysqli_report(MYSQLI_REPORT_OFF);
    $left = n($db, "SELECT COUNT(*) FROM `fin_journal_entries` WHERE `entry_no` = 'BELT-FIN006'");
    chk($rejected && $left === 0,
        '**قيدٌ يدويٌّ ناقصٌ ⇒ رفضٌ من القاعدة**',
        $rejected ? 'ردَّته: ' . mb_substr($err, 0, 52) : "مرَّ ✘ · صفوفٌ={$left}");

    /* ◆ **والكاملُ يمرّ** — وإلا كان القيدُ يمنع العملَ لا الخطأ */
    $passed = false; $err2 = '';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $db->query("INSERT INTO `fin_journal_entries`
            (`company_id`,`entry_no`,`posting_date`,`txn_date`,`currency`,`fx_rate`,
             `total_debit`,`total_credit`,`memo`,`state`,`created_by`,`created_at`,
             `manual_kind`,`source_doc_ref`,`period_code`,`manual_gov_state`)
            VALUES ({$co},'BELT-FIN006-OK',CURDATE(),CURDATE(),'SDG',1,0,0,'حزامٌ كامل','draft',1,NOW(),
                    'تسوية','DOC-BELT-1',DATE_FORMAT(CURDATE(),'%Y-%m'),'GOVERNED')");
        $passed = true;
    } catch (\Throwable $t) { $err2 = $t->getMessage(); }
    mysqli_report(MYSQLI_REPORT_OFF);
    $okRow = n($db, "SELECT COUNT(*) FROM `fin_journal_entries` WHERE `entry_no` = 'BELT-FIN006-OK'");
    chk($passed && $okRow === 1,
        'و**القيدُ الكاملُ يمرّ** — فالقيدُ يمنع الخطأَ لا العمل',
        $passed ? 'مرَّ ✔' : 'رُدَّ ✘: ' . mb_substr($err2, 0, 52));
    $db->query("DELETE FROM `fin_journal_entries` WHERE `entry_no` IN ('BELT-FIN006','BELT-FIN006-OK')");
    $swept = n($db, "SELECT COUNT(*) FROM `fin_journal_entries`
                      WHERE `entry_no` IN ('BELT-FIN006','BELT-FIN006-OK')");
    chk($swept === 0, 'وكُنس الحزامُ أثرَه', "المتبقي: {$swept}");
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
