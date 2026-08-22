<?php
/**
 * 2027_10_23_frd_gov004_denial_verb.php
 *   FR-GOV-004 · CHG-GOV-EVIDENCE-01 — واقعةُ الرفضِ تحمل الفعلَ الذي رُفض
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلبُ بنصِّه** (الدفتر · GAP-14 · P3): «واقعةُ الرفضِ تحمل **الفعلَ الذي
 *   رُفض — بعمودٍ وقيد**» · وسلوكُ الفشل «**رفضٌ من القاعدةِ للواقعةِ الناقصة**»
 *   · ومعيارُ القبول «صفرُ واقعةِ رفضٍ بفعلٍ فارغ».
 *
 * ◆ **و`GAP-14` أُعلن مُغلقًا مرَّتَين وهو مفتوح** — بنصِّ `FINDINGS.md` (F-C05):
 *   «القيدُ الفعليُّ `chk_denial_ref_present` يحرس **`attempted_ref`** —
 *   **ولا عمودَ `verb` في `guard_denials` أصلًا**». وقِيس اليومَ فصدق:
 *   أعمدتُه سبعةٌ ليس فيها فعل.
 *
 * ◆ **وعطبٌ ثانٍ في الشيفرةِ قِيس**: `$verb` كان **يُستعمل في تسجيلِ
 *   `scope_denied` قبلَ تعريفِه بأربعةَ عشرَ سطرًا** — فكلُّ رفضٍ بسببِ المساحةِ
 *   يُسجَّل **بفعلٍ فارغ**، والسجلُّ يقول «رُفض» ولا يقول «رُفض ماذا».
 *   نُقل التعريفُ قبلَ أوّلِ استعمال.
 *
 * ◆ **ولا تُملأ الـ5354 بأثرٍ رجعيّ**: فعلٌ يُختلَق لواقعةٍ مضت **تزويرُ سجلٍّ
 *   أمنيّ**. تُوسَم `PRE_VERB` صراحةً — مرئيّةً في المقام — ويسري القيدُ على
 *   ما يأتي.
 *
 * التشغيل:  php database/migrations/2027_10_23_frd_gov004_denial_verb.php
 * الرجوع :  php database/migrations/2027_10_23_frd_gov004_denial_verb.php --revert
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
$CHK = 'chk_denial_verb_present';

if (in_array('--revert', $argv, true)) {
    $conn->query("ALTER TABLE `guard_denials` DROP CONSTRAINT `{$CHK}`");
    $conn->query("ALTER TABLE `guard_denials` DROP COLUMN `verb`");
    $conn->query("ALTER TABLE `guard_denials` DROP COLUMN `verb_state`");
    echo "↺ أُسقط عمودُ الفعلِ وقيدُه\n";
    exit(0);
}

$before = cnt($conn, "SELECT COUNT(*) FROM `guard_denials`");
printf("① قبل: وقائعُ رفضٍ=%d · أعمدةٌ فيها فعل: %d\n", $before,
       cnt($conn, "SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='guard_denials'
                      AND COLUMN_NAME='verb'"));

/* ── ② العمودان ─────────────────────────────────────────────────────────── */
foreach (array(
    'verb'       => "VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'الفعلُ الذي رُفض — view/add/edit/delete أو رمزُ الفعل'",
    'verb_state' => "VARCHAR(16) NOT NULL DEFAULT 'REQUIRED' COMMENT 'REQUIRED · PRE_VERB (موروثٌ قبلَ العمود)'",
) as $col => $def) {
    if (cnt($conn, "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='guard_denials'
                       AND COLUMN_NAME='{$col}'") > 0) {
        echo "   = `{$col}` قائمٌ سلفًا\n"; continue;
    }
    if ($conn->query("ALTER TABLE `guard_denials` ADD COLUMN `{$col}` {$def}")) {
        echo "   ✔ أُضيف `{$col}`\n";
    } else { exit("⛔ تعذّرت إضافةُ `{$col}`: " . $conn->error . "\n"); }
}

/* ── ③ الوسمُ لا الملء ──────────────────────────────────────────────────── */
$conn->query("UPDATE `guard_denials` SET `verb_state` = 'PRE_VERB'
               WHERE TRIM(`verb`) = ''");
printf("③ وُسِم %d صفًّا **PRE_VERB** — ولا فعلَ اختُلق لواقعةٍ مضت\n", $conn->affected_rows);

/* ── ④ القيدُ — يرفض واقعةً مطلوبةَ الفعلِ وهي فارغة ────────────────────── */
if (cnt($conn, "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='{$CHK}'") === 0) {
    $sql = "ALTER TABLE `guard_denials` ADD CONSTRAINT `{$CHK}` CHECK (
              `verb_state` = 'PRE_VERB' OR TRIM(`verb`) <> '')";
    if ($conn->query($sql)) { echo "④ ✔ قيدُ الفعلِ أُضيف — **الرفضُ من القاعدة**\n"; }
    else { exit("⛔ تعذّر إضافةُ القيد: " . $conn->error . "\n"); }
} else { echo "④ = القيدُ قائمٌ سلفًا\n"; }

/* ── ⑤ التحقُّقُ الحيّ ═══════════════════════════════════════════════════ */
$rejected = false; $err = '';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn->query("INSERT INTO `guard_denials`
        (`company_id`,`guard_code`,`person_id`,`attempted_ref`,`reason_code`,`at`)
        VALUES (4,'PROBE-GOV004',1,'probe/ref','probe',NOW())");
} catch (\Throwable $t) { $rejected = true; $err = $t->getMessage(); }
mysqli_report(MYSQLI_REPORT_OFF);
$conn->query("DELETE FROM `guard_denials` WHERE `guard_code` = 'PROBE-GOV004'");
printf("⑤ التحقُّقُ الحيّ: واقعةُ رفضٍ **بفعلٍ فارغ** ⇒ %s\n",
       $rejected ? '**رفضٌ من القاعدة ✔** — ' . mb_substr($err, 0, 52) : 'مرَّت ✘ — القيدُ لا يعمل');
if (!$rejected) { exit("⛔ القيدُ لا يمنع — أُوقِف\n"); }

/* ── ⑥ المصالحة ───────────────────────────────────────────────────────── */
$after = cnt($conn, "SELECT COUNT(*) FROM `guard_denials`");
$pre   = cnt($conn, "SELECT COUNT(*) FROM `guard_denials` WHERE `verb_state` = 'PRE_VERB'");
$req   = cnt($conn, "SELECT COUNT(*) FROM `guard_denials` WHERE `verb_state` = 'REQUIRED'");
$badReq = cnt($conn, "SELECT COUNT(*) FROM `guard_denials`
                       WHERE `verb_state` = 'REQUIRED' AND TRIM(`verb`) = ''");
printf("\n⑥ بعد: وقائع=%d (%s) · PRE_VERB=%d · REQUIRED=%d · **ناقصُ فعلٍ محكومٌ=%d**\n",
       $after, $after === $before ? '✔ لا فقد' : '✘ **فرق**', $pre, $req, $badReq);
if ($after !== $before || $badReq !== 0) { exit("⛔ اختلَّ المقامُ أو بقي ناقص\n"); }

ems_migration_recorded(__FILE__, $conn, 0);
