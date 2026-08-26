<?php
/**
 * tools/repair01_w9_resume.php — استئنافُ ما أُجِّل بـ`DEC-OPEN-15`
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **يُشغَّل حين يُجيب المالكُ عن السؤالِ المحفوظ** في
 *   `docs/REPAIR01_20260823/open/DEC-OPEN-15.md`. ويفعل ثلاثةً بالترتيب:
 *   ① يبذر قواعدَ الفئاتِ في `proc_item_track_rule` من ملفِّ الجواب.
 *   ② يشتقُّ أعلامَ `proc_item` من القواعدِ ويملأ `track_rule_ref`.
 *   ③ يرفع `consumed` عن بنودِ التأجيلِ التي صار إثباتُ انتظارِها كاذبًا.
 *
 * ◆ **ولا فئةَ مُخمَّنةٌ هنا**: الأداةُ تقرأ ملفَّ الجوابِ ولا تخترع سطرًا.
 *   وبلا ملفٍّ **لا تكتب شيئًا** وتخرج برمزٍ غيرِ صفر.
 *
 * ◆ **صيغةُ ملفِّ الجواب** — سطرٌ لكلِّ فئةٍ داخلَ كتلةٍ محاطةٍ بـ```:
 *   `الفئة | lot,serial,expiry | BLOCK او WARN_OVERRIDE | FEFO او FIFO او FREE | السبب`
 *   والحقلُ الثاني قائمةُ الأعلامِ المفصولةُ بفاصلة، وما بعدَه يلزم للصلاحيةِ وحدَها.
 *
 * ⛔ **ولا يُغلَق التأجيلُ إلّا بإثباتٍ مقيس**: البندُ يُستهلَك حين يصير
 *   `probe_sql` **غيرَ صفريّ** — لا حين يُقال إنّه نُفِّذ.
 *
 * التشغيل: php tools/repair01_w9_resume.php --answer=<ملف> [--report]
 * الخروج : 0 استُؤنف · 1 لا ملفَّ جوابٍ أو لم يكتمل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w9_scan.php';
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$REPORT = in_array('--report', $argv, true);
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w9_one($conn, $sql); };

$answer = '';
foreach ($argv as $a) { if (strpos($a, '--answer=') === 0) { $answer = substr($a, 9); } }
if ($answer === '') { $answer = $ROOT . '/docs/REPAIR01_20260823/open/DEC-OPEN-15.answer.md'; }
if (!is_file($answer)) {
    echo "✘ لا ملفَّ جوابٍ في: $answer\n";
    echo "  السؤالُ ما زال مفتوحًا — انظرْ docs/REPAIR01_20260823/open/DEC-OPEN-15.md\n";
    echo "  ⛔ ولا تُبذَر فئةٌ بلا جوابِ المالك.\n";
    exit(1);
}

echo "══ استئنافُ ما أُجِّل بـDEC-OPEN-15 ══\n\n";

/* ═══ ① بذرُ قواعدِ الفئاتِ من ملفِّ الجواب ═══════════════════════════════ */
echo "① قواعدُ الفئاتِ من ملفِّ الجواب ──────────────────────────────\n";
$lines = file($answer, FILE_IGNORE_NEW_LINES);
$seeded = 0; $bad = array(); $i = 0;
foreach ($lines as $ln) {
    $ln = trim($ln);
    if ($ln === '' || strpos($ln, '|') === false || strpos($ln, '#') === 0) { continue; }
    $f = array_map('trim', explode('|', $ln));
    if (count($f) < 5) { continue; }
    list($cat, $flags, $policy, $order, $why) = array($f[0], strtolower($f[1]), strtoupper($f[2]),
                                                      strtoupper($f[3]), $f[4]);
    if ($cat === '' || $why === '') { $bad[] = $ln; continue; }
    $lot = (strpos($flags, 'lot') !== false) ? 1 : 0;
    $ser = (strpos($flags, 'serial') !== false) ? 1 : 0;
    $exp = (strpos($flags, 'expiry') !== false) ? 1 : 0;
    if ($lot + $ser + $exp === 0) { $bad[] = $ln . ' (بلا علمٍ واحد)'; continue; }
    if ($exp === 1 && ($policy === '' || $order === '')) { $bad[] = $ln . ' (صلاحيةٌ بلا سياسةٍ أو ترتيب)'; continue; }
    $i++;
    $key = 'TR-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
    $sql = "INSERT INTO proc_item_track_rule
            (rule_key, company_id, category, track_lot, track_serial, track_expiry,
             expiry_policy, issue_order, why, decision_ref, is_active)
            VALUES ('" . $esc($key) . "',0,'" . $esc($cat) . "',$lot,$ser,$exp,
                    '" . $esc($exp ? $policy : '') . "','" . $esc($exp ? $order : '') . "',
                    '" . $esc($why) . "','DEC-OPEN-15',1)
            ON DUPLICATE KEY UPDATE track_lot=VALUES(track_lot), track_serial=VALUES(track_serial),
              track_expiry=VALUES(track_expiry), expiry_policy=VALUES(expiry_policy),
              issue_order=VALUES(issue_order), why=VALUES(why), is_active=1";
    if ($REPORT) { echo "  ↷ $cat ⇐ lot=$lot serial=$ser expiry=$exp\n"; $seeded++; continue; }
    if ($conn->query($sql) === true) { echo "  ✔ $cat ⇐ lot=$lot serial=$ser expiry=$exp\n"; $seeded++; }
    else { echo '  ✘ ' . $cat . ' — ' . $conn->error . "\n"; $bad[] = $cat; }
}
printf("  قواعدُ مبذورة %d · مرفوضةٌ %d%s\n\n", $seeded, count($bad),
    $bad ? ' ⇐ ' . implode('، ', array_slice($bad, 0, 2)) : '');
if ($seeded === 0) { echo "✘ لا قاعدةَ واحدةً في ملفِّ الجواب — لا استئناف\n"; exit(1); }

/* ═══ ② اشتقاقُ أعلامِ الصنفِ من قاعدةِ فئتِه ═══════════════════════════ */
echo "② اشتقاقُ أعلامِ الأصنافِ من القواعد ───────────────────────────\n";
if (!$REPORT) {
    $conn->query("UPDATE proc_item SET track_lot=0, track_serial=0, track_expiry=0, track_rule_ref=''");
    $conn->query("UPDATE proc_item i
                    JOIN proc_item_track_rule r ON r.category = i.category AND r.is_active = 1
                                                AND (r.company_id = 0 OR r.company_id = i.company_id)
                     SET i.track_lot = r.track_lot, i.track_serial = r.track_serial,
                         i.track_expiry = r.track_expiry, i.track_rule_ref = r.rule_key");
}
$flagged = (int) $one("SELECT COUNT(*) FROM proc_item
                        WHERE track_lot=1 OR track_serial=1 OR track_expiry=1");
$noRule  = (int) $one("SELECT COUNT(*) FROM proc_item
                        WHERE (track_lot=1 OR track_serial=1 OR track_expiry=1)
                          AND COALESCE(track_rule_ref,'') = ''");
printf("  أصنافٌ بعلمٍ %d · علمٌ بلا قاعدةٍ %d\n\n", $flagged, $noRule);

/* ═══ ③ استهلاكُ بنودِ التأجيلِ — بإثباتٍ مقيسٍ لا بدعوى ═══════════════ */
echo "③ استهلاكُ بنودِ التأجيل ──────────────────────────────────────\n";
$consumed = 0; $still = 0;
$r = $conn->query("SELECT defer_key, probe_sql FROM repair01_w9_deferred WHERE consumed = 0");
$pend = array();
while ($r && $x = $r->fetch_assoc()) { $pend[] = $x; }
foreach ($pend as $x) {
    $v = repair01_w9_one($conn, (string) $x['probe_sql']);
    if ($v !== null && (int) $v > 0) {
        if (!$REPORT) {
            $conn->query("UPDATE repair01_w9_deferred SET consumed = 1, consumed_at = NOW()
                           WHERE defer_key = '" . $esc($x['defer_key']) . "'");
        }
        echo '  ✔ ' . $x['defer_key'] . " — الإثباتُ صار غيرَ صفريٍّ ($v) فاستُهلك\n";
        $consumed++;
    } else {
        echo '  ⛔ ' . $x['defer_key'] . " — الإثباتُ ما زال صفرًا فلا يُستهلَك\n";
        $still++;
    }
}
printf("  استُهلك %d · باقٍ %d\n\n", $consumed, $still);

/* ═══ ④ إغلاقُ الحاجبِ في سجلِّ القرارات ═════════════════════════════ */
if ($still === 0 && !$REPORT) {
    echo "④ إغلاقُ الحاجب ──────────────────────────────────────────────\n";
    $conn->query("UPDATE repair01_decisions SET status = 'APPROVED'
                   WHERE decision_id = 'DEC-OPEN-15'");
    echo "  ✔ DEC-OPEN-15 صار معتمَدًا\n\n";
}

echo "───────────────────────────────────────────────────────────────\n";
echo "الخطوةُ التالية: php tools/repair01_w9_gate.php\n";
echo ($still === 0
    ? "الحكم: استُؤنف كاملًا ✔ — والمرحلةُ تصير قابلةَ الإغلاق\n"
    : "الحكم: استئنافٌ ناقصٌ — $still بندًا ما زال ينتظر ✘\n");
exit($still === 0 ? 0 : 1);
