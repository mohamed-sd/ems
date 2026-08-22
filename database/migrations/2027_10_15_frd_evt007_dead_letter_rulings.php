<?php
/**
 * 2027_10_15_frd_evt007_dead_letter_rulings.php
 *   FR-EVT-007 · CHG-EVT-BUS-01 — لكلِّ رسالةٍ ميتةٍ قرارٌ ومالكٌ وسبب
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلب** (الدفتر · P2): «الرسالةُ الميتةُ لها قرارٌ ومالكٌ وسبب — ولا
 *   تبقى معلَّقة» · ومعيارُ قبولِه: «**صفرُ رسالةٍ ميتةٍ بلا قرارٍ بعدَ المهلة**».
 *
 * ◆ **والكشفُ غيَّر معنى المطلب**: الدفترُ يقول «٢٦ رسالةً بلا قرار»، والمقيسُ
 *   اليومَ **سبعَ عشرةَ ميتةً — كلُّها ملوَّثةٌ بعائلةِ `UAT-2026`**: حمولتُها
 *   `{"_legacy_job_type": "<عبارةُ حشو> · UAT-2026"}` ومحاولاتُها متسلسلةٌ
 *   اصطناعيًّا (5·10·15·20·25) و`last_error` فارغٌ في أكثرِها. **وصفرُ رسالةٍ
 *   ميتةٍ حقيقية.**
 *
 * ◆ **ولو فُبركت لها قراراتُ تشغيلٍ لصار السجلُّ يكذب**: قرارٌ على عطبٍ لم يقع.
 *   فالقرارُ الصادقُ لها **حجرُ تلوّثٍ بدليلِه**، لا «أُعيدت المحاولة».
 *
 * ◆ **ولا حذفَ**: الصفُّ يبقى بحالتِه ويُقيَّد له حكمٌ في سجلٍّ مستقلّ —
 *   §تاسعًا: «التصحيحُ التاريخيُّ **حجرٌ** لا حذف».
 *
 * التشغيل:  php database/migrations/2027_10_15_frd_evt007_dead_letter_rulings.php
 * الرجوع :  php database/migrations/2027_10_15_frd_evt007_dead_letter_rulings.php --revert
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

if (in_array('--revert', $argv, true)) {
    $conn->query("DROP TABLE IF EXISTS `gov_dead_letter_rulings`");
    echo "↺ أُسقط سجلُّ أحكامِ الرسائلِ الميتة\n";
    exit(0);
}

$conn->query("CREATE TABLE IF NOT EXISTS `gov_dead_letter_rulings` (
    `job_id`      BIGINT NOT NULL,
    `job_type`    VARCHAR(64) NOT NULL,
    `ruling`      VARCHAR(32) NOT NULL COMMENT 'TEST_POLLUTION · RETRY · DROP · NEEDS_GOVERNING_SOURCE',
    `owner_role`  VARCHAR(64) NOT NULL,
    `reason`      VARCHAR(400) NOT NULL,
    `evidence`    VARCHAR(300) NOT NULL,
    `ruled_at`    DATETIME NOT NULL,
    PRIMARY KEY (`job_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='FR-EVT-007 — لا رسالةَ ميتةً بلا قرارٍ ومالكٍ وسبب'");

/* ── ① العدُّ قبلًا ─────────────────────────────────────────────────────── */
function cnt(mysqli $c, $sql) { $r = @$c->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; }
$dead0 = cnt($conn, "SELECT COUNT(*) FROM `ems_job_queue` WHERE `state` = 'dead'");
$POL   = "(`payload_json` LIKE '%UAT-2026%' OR COALESCE(`last_error`,'') LIKE '%UAT-2026%')";
$pol0  = cnt($conn, "SELECT COUNT(*) FROM `ems_job_queue` WHERE `state` = 'dead' AND {$POL}");
printf("① قبل: ميتةٌ=%d · ملوَّثةٌ=%d · حقيقيةٌ=%d\n", $dead0, $pol0, $dead0 - $pol0);

/* ── ② الحكمُ على الملوَّثِ — حجرٌ بدليلِه لا قرارُ تشغيلٍ مفبرَك ─────────── */
$ins = $conn->prepare(
  "INSERT INTO `gov_dead_letter_rulings`
     (`job_id`,`job_type`,`ruling`,`owner_role`,`reason`,`evidence`,`ruled_at`)
   VALUES (?,?,?,?,?,?,NOW())
   ON DUPLICATE KEY UPDATE `ruling`=VALUES(`ruling`), `reason`=VALUES(`reason`)");

/* ◆ **إعدادٌ فاشلٌ يقول لا ينفجر**: أوّلُ تشغيلٍ عاد فيه `prepare` بـ`false`
 *   فانفجر السطرُ التالي بـ«bind_param on bool» — وهو خطأٌ يخفي سببَه.
 *   ⇒ يُفحص المُرجَعُ ويُطبع خطأُ القاعدةِ نفسُه. */
if (!$ins) { exit("⛔ تعذّر إعدادُ الإدراج: " . $conn->error . "
"); }

$RULING = 'TEST_POLLUTION';
$OWNER  = 'مالكُ مجالِ الأحداث';
$REASON = 'حمولةٌ نمطيةٌ من مولِّدِ UAT-2026 ومحاولاتٌ متسلسلةٌ اصطناعيًّا — عطبٌ لم يقع، '
        . 'فلا يُفبرَك له قرارُ تشغيل. حُجِر بحكمِه ولم يُحذف.';
$EV     = 'gov_pollution_findings · ems_job_queue · payload_json LIKE %UAT-2026%';

$n = 0;
$r = $conn->query("SELECT `job_id`, `job_type` FROM `ems_job_queue`
                    WHERE `state` = 'dead' AND {$POL}");
while ($r && $x = $r->fetch_assoc()) {
    $jid = (int) $x['job_id'];
    $ins->bind_param('isssss', $jid, $x['job_type'], $RULING, $OWNER, $REASON, $EV);
    if ($ins->execute()) { $n++; }
}
$ins->close();
printf("② حُكم على %d رسالةً ملوَّثةً — **حجرٌ بدليلِه لا قرارُ تشغيلٍ مفبرَك**\n", $n);

/* ── ③ الحقيقيةُ — إن وُجدت فحكمُها ليس بيدي ───────────────────────────── */
$real = cnt($conn, "SELECT COUNT(*) FROM `ems_job_queue` q
                     WHERE q.`state` = 'dead' AND NOT {$POL}
                       AND NOT EXISTS (SELECT 1 FROM `gov_dead_letter_rulings` g
                                        WHERE g.`job_id` = q.`job_id`)");
if ($real > 0) {
    $ins2 = $conn->prepare(
      "INSERT INTO `gov_dead_letter_rulings`
         (`job_id`,`job_type`,`ruling`,`owner_role`,`reason`,`evidence`,`ruled_at`)
       SELECT q.`job_id`, q.`job_type`, 'NEEDS_GOVERNING_SOURCE', 'مالكُ مجالِ الأحداث',
              'رسالةٌ ميتةٌ حقيقيةٌ — وقرارُها (إعادةٌ أم إسقاطٌ) يحتاج حكمًا من مالكِ المجال',
              CONCAT('ems_job_queue#', q.`job_id`), NOW()
         FROM `ems_job_queue` q
        WHERE q.`state` = 'dead' AND NOT {$POL}
          AND NOT EXISTS (SELECT 1 FROM `gov_dead_letter_rulings` g WHERE g.`job_id` = q.`job_id`)");
    if ($ins2) { $ins2->execute(); $ins2->close(); }
    printf("③ و%d رسالةً حقيقيةً وُسِمت NEEDS_GOVERNING_SOURCE — قرارُها لمالكِ المجال\n", $real);
} else {
    echo "③ **وصفرُ رسالةٍ ميتةٍ حقيقية** — فلا قرارَ تشغيلٍ يُنتظَر\n";
}

/* ── ④ المصالحة ─────────────────────────────────────────────────────────── */
$dead1  = cnt($conn, "SELECT COUNT(*) FROM `ems_job_queue` WHERE `state` = 'dead'");
$ruled  = cnt($conn, "SELECT COUNT(*) FROM `gov_dead_letter_rulings`");
$noRule = cnt($conn, "SELECT COUNT(*) FROM `ems_job_queue` q
                       WHERE q.`state` = 'dead'
                         AND NOT EXISTS (SELECT 1 FROM `gov_dead_letter_rulings` g
                                          WHERE g.`job_id` = q.`job_id`)");
printf("\n④ بعد: ميتةٌ=%d (لم تُحذف) · محكومةٌ=%d · **بلا حكم=%d**\n", $dead1, $ruled, $noRule);
printf("⑤ مصالحةُ المقام: %d ⇐ %d ⇒ %s (صفرُ حذف)\n",
       $dead0, $dead1, $dead0 === $dead1 ? '✔ مطابق' : '✘ **فرق**');
if ($dead0 !== $dead1) { exit("⛔ اختلَّ المقام\n"); }

ems_migration_recorded(__FILE__, $conn, 0);
