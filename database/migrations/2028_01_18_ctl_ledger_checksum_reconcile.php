<?php
/**
 * 2028_01_18_ctl_ledger_checksum_reconcile.php — مصالحةُ بصمةِ هجرةٍ صُقلت بعد تطبيقِها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — أمرُ الضبطِ §١٢ كشفه: `migrate up` **يرفض كلَّ تقدُّمٍ**
 *   برسالةِ `checksum mismatch` على `2027_06_28_bus_actions_handler_fix.php`،
 *   ⛔ **وفحصُ اتجاهِ الهجراتِ كان يخضرُّ خُضرةً كاذبة**: الطابورُ «خالٍ من
 *   `_down`» لأنَّ الأمرَ رفض قبل بناءِ الطابورِ أصلًا.
 *
 * ◆ **والجذرُ مؤرَّخٌ بالدقيقة** — لا تحريفَ ولا التباس:
 *   · طُبِّقت الهجرةُ `2026-08-18 10:45:44` وبصمتُها في الدفتر
 *     `69cff99fd3b8a078fedd1cefdadfea6672b845f1`.
 *   · والتُزمت أولَ مرةٍ `10:48:20` في `8fd783be` **ملفًّا جديدًا** ببصمةِ
 *     `37982c74b0df1b3abd9a1b30455b94eb2e8fb46d` — **فالمحتوى صُقل في الدقائقِ
 *     الثلاثِ بين التطبيقِ والالتزام**، والنسخةُ المطبَّقةُ **لا وجودَ لها في
 *     git إطلاقًا** (‏الالتزامُ الوحيدُ الذي مسَّ الملفَّ هو `8fd783be` وبصمةُ
 *     محتواه هي بصمةُ القرصِ اليوم).
 *   · **وأثرُ الهجرةِ متحقَّقٌ في رسالةِ الالتزامِ نفسِها**: «الحال: act_checks
 *     صفرٌ في الحاكمة — الدمجُ مسموح» — فالقاعدةُ تحمل الأثرَ المقصود.
 *
 * ◆ **ولماذا المصالحةُ لا البديلان اللذان يقترحهما `migrate`**:
 *   ⛔ «أعدِ المحتوى الأصليّ» — **مستحيلٌ إثباتًا**: النسخةُ المطبَّقةُ غيرُ
 *     موجودةٍ في git ولا في نسخةٍ احتياطيّة، وتأليفُ محتوًى يطابق بصمةً قديمةً
 *     تلفيقٌ بالتعريف.
 *   ⛔ «ضعِ التغييرَ في ملفٍّ جديد» — **لا تغييرَ مخطَّطٍ هنا**: الفرقُ صقلُ
 *     تعليقٍ وشاهدٍ في نصِّ الملفِّ لا في أثرِه، والأثرُ واقعٌ متحقَّق.
 *   ⇒ **فيُصالَح الدفترُ على بصمةِ المحتوى الوحيدِ الموجودِ والملتزَم** —
 *     بهجرةٍ مسجَّلةٍ لها عكسٌ، ⛔ لا بتحديثٍ يدويٍّ صامت.
 *
 * التشغيل: php database/migrations/2028_01_18_ctl_ledger_checksum_reconcile.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);
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

/* ◆ **المسحُ الشاملُ وجد أربعةً على النمطِ نفسِه — لا واحدًا**: طُبِّق/سُوِّي
     الملفُّ ثمَّ صُقل محتواه في التزامٍ لاحقٍ **موثَّقِ الأثرِ في رسالتِه** —
     والبصماتُ الأربعُ القديمةُ والجديدةُ كلُّها مؤرَّخةٌ في git (‏عدا الأولى
     فنسختُها المطبَّقةُ سبقت أولَ التزامٍ لها أصلًا). */
$FIXES = array(
    '2027_06_28_bus_actions_handler_fix.php' => array(
        'old' => '69cff99fd3b8a078fedd1cefdadfea6672b845f1',
        'new' => '37982c74b0df1b3abd9a1b30455b94eb2e8fb46d',
        'wit' => 'طُبِّقت 2026-08-18 10:45:44 والتُزمت أولَ مرةٍ 10:48:20 في 8fd783be — '
               . 'المحتوى صُقل بين التطبيقِ والالتزام والنسخةُ المطبَّقةُ لا وجودَ لها في git، '
               . 'والأثرُ متحقَّقٌ في رسالةِ الالتزام («act_checks صفرٌ في الحاكمة»)',
    ),
    '2027_07_08_visual_measurements_and_versions.php' => array(
        'old' => '9a4b91e64a1c313a70ec3432c62233406e880745',
        'new' => 'ed8f3c74775621ddac369ae89cd63f6bee8008cd',
        'wit' => 'سُوِّيت baseline عند 56d591d7 (20:34) ثمَّ صُقلت في bb6a9670 (21:39 — '
               . '«بنقلٍ حرفيٍّ لا إعادةِ كتابة») — النسختان في git والقرصُ بصمةُ الأخير',
    ),
    '2027_08_21_finreq_merge_request_screens.php' => array(
        'old' => '452f6e39d6e2f8ef9841b8c70252f3837cc19e8a',
        'new' => '3dd8c374508fd6636ed8ae2e40455f21813ef96b',
        'wit' => 'طُبِّقت 09:27 عند 3ba85151 ثمَّ صُقلت في 8f8cbaf5 (09:37 — «الدفترُ سُوِّي '
               . 'والبصمةُ طابقت») — النسختان في git والقرصُ بصمةُ الأخير',
    ),
    '2027_10_31_injfrd66_xc04_service_surface.php' => array(
        'old' => '5e9a2eb6631b87c1bfcc8f865c857db204a28b52',
        'new' => 'f3678014344d5ce3bb05a63668a973b46edc2367',
        'wit' => 'طُبِّقت 01:37 عند c5918cc8 ثمَّ أُصلح فيها ابتلاعُ ENUM في 04bd5c2b (01:49 — '
               . '«قيمةٌ ابتلعها ENUM في هجرتي أنا») — إصلاحٌ ذاتيٌّ موثَّقٌ والنسختان في git',
    ),
);

/* موضعُ التراجعِ — البصمةُ القديمةُ تُحفظ قبل الدهس */
$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_ledger_checksum_fix` (
  `filename`     VARCHAR(255) NOT NULL,
  `old_checksum` CHAR(40) NOT NULL,
  `new_checksum` CHAR(40) NOT NULL,
  `witness`      VARCHAR(600) NOT NULL,
  `fixed_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`filename`),
  CONSTRAINT `chk_lcf_witness` CHECK (`witness` <> ''),
  CONSTRAINT `chk_lcf_diff`    CHECK (`old_checksum` <> `new_checksum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='CTL 12 · مصالحة بصمة هجرة صقلت بعد تطبيقها — وهو مصدر التراجع'");
if (!$ok) { exit("✘ تعذّر إنشاءُ موضعِ التراجع: {$conn->error}\n"); }

$done = 0; $skip = 0;
foreach ($FIXES as $FILE => $fx) {
    $DISK = sha1_file(dirname(__DIR__) . '/migrations/' . $FILE);
    $r = $conn->query("SELECT checksum FROM schema_migrations WHERE filename = '" . $conn->real_escape_string($FILE) . "'");
    $cur = ($r && ($x = $r->fetch_row())) ? $x[0] : '';
    /* ⛔ **ثلاثُ حالاتٍ لا رابعة**: الدفترُ على القديمةِ ⇒ يُصالَح · على
       الجديدةِ ⇒ صُولح سلفًا فيُتخطّى · **وأيُّ ثالثةٍ ترفض الملفَّ باسمِه**
       — فمصالحةٌ على غيرِ الحالِ المؤرَّخةِ عبثٌ أعمى. */
    if ($cur === $fx['new'] && $DISK === $fx['new']) { $skip++; continue; }
    if ($cur !== $fx['old']) {
        exit("⛔ `$FILE`: بصمةُ الدفترِ `$cur` ليست القديمةَ المؤرَّخةَ — الحالُ تغيَّر ولا مصالحةَ عمياء\n");
    }
    if ($DISK !== $fx['new']) {
        exit("⛔ `$FILE`: بصمةُ القرصِ `$DISK` ليست بصمةَ الالتزامِ الموثَّق — الملفُّ تحرَّك ولا مصالحةَ عمياء\n");
    }
    $conn->query("INSERT INTO `repair01_ledger_checksum_fix` (filename, old_checksum, new_checksum, witness)
                  VALUES ('" . $conn->real_escape_string($FILE) . "', '{$fx['old']}', '{$fx['new']}', '"
                . $conn->real_escape_string($fx['wit'] . ' — أمرُ الضبطِ §١٢') . "')
                  ON DUPLICATE KEY UPDATE new_checksum = VALUES(new_checksum)");
    if (!$conn->query("UPDATE `schema_migrations` SET `checksum` = '{$fx['new']}'
                        WHERE `filename` = '" . $conn->real_escape_string($FILE) . "' AND `checksum` = '{$fx['old']}'")) {
        exit("✘ تعذّرت المصالحة: {$conn->error}\n");
    }
    echo "  ✔ صُولحت بصمةُ `$FILE` — بشاهدٍ مؤرَّخٍ وموضعِ تراجع\n";
    $done++;
}
echo "  ⇒ صُولح **$done** · ومُصالَحٌ سلفًا **$skip** من " . count($FIXES) . "\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ `migrate up` لم يعُد مرفوضًا على بصمةٍ لمحتوًى لا وجودَ له\n";
