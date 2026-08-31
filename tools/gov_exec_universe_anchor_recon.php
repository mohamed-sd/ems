<?php
/**
 * tools/gov_exec_universe_anchor_recon.php — مصالحةُ الكونِ بمراسي دفاترِ الموجات (GOV_EXEC §5 · §13)
 * ═══════════════════════════════════════════════════════════════════════════
 * حكمُ NOT_BUILT في الكونِ قيس بمطابقةِ الاسمِ وحدَها — ودفاترُ الموجاتِ
 * (repair01_w*_scope) أرست المتطلّباتِ على شاشاتِها بمراسٍ **مثبتةٍ من القرصِ
 * في بوّاباتِ موجتِها**. هذه الأداةُ تستنفد المراسيَ قبل الإبقاءِ على NOT_BUILT:
 *   - مرساةٌ لشاشةٍ حيّةٍ + الاسمُ المطبَّعُ يطابق عنوانَها ⇒ MATCHED.
 *   - مرساةٌ لشاشةٍ حيّةٍ بعنوانٍ آخرَ (السطحُ يخدم الهدفَ ضمنَه — بنودٌ
 *     داخل رأسِها ونحوُه) ⇒ MERGED_INTO بشاهدِ المرساة.
 * ⛔ لا يُمسُّ صفٌّ حكمُه غيرُ NOT_BUILT — الأحكامُ القائمةُ لا تُدهس.
 * التشغيل: php tools/gov_exec_universe_anchor_recon.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
date_default_timezone_set((string) ems_env('EMS_APP_TIMEZONE', 'Africa/Cairo'));
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("⛔ تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$SNAP = 'SNAP-govexec-' . trim(shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD')) . '-' . date('Ymd-His');

function nz2($s)
{
    $s = (string) $s;
    $s = str_replace(array('أ', 'إ', 'آ'), 'ا', $s);
    $s = str_replace('ى', 'ي', $s);
    $s = str_replace('ة', 'ه', $s);
    $s = preg_replace('~[\x{064B}-\x{065F}\x{0640}]~u', '', $s);
    $s = preg_replace('~\s+~u', ' ', trim($s));
    return $s;
}

/* مراسي الموجاتِ الشاهدةِ للبناء — الدفاترُ الحاملةُ build_verdict وحدَها،
   والقبولُ لِما شهدت موجتُه أنّه حيٌّ/مبنيٌّ (LIVE/BUILT_*) لا المؤجَّلِ ولا
   المرسى بلا شهادةِ بناء (ارتدادُ 153 حكمًا علّم أنّ المرساةَ وحدَها نطاقُ فحص) */
$anchors = array();
foreach (array('w9', 'w11', 'w12', 'w13', 'w14', 'w15') as $wv) {
    $t = 'repair01_' . $wv . '_scope';
    $q = $conn->query("SELECT requirement_id, anchor_screen_id, build_verdict FROM `$t`
        WHERE anchor_screen_id <> '' AND (build_verdict = 'LIVE' OR build_verdict LIKE 'BUILT_%')");
    while ($q && ($x = $q->fetch_assoc())) {
        if (!isset($anchors[$x['requirement_id']])) {
            $anchors[$x['requirement_id']] = array($x['anchor_screen_id'], $t . '·' . $x['build_verdict']);
        }
    }
}
printf("مراسٍ مجموعة: %d متطلّبًا\n", count($anchors));

/* الشاشاتُ الحيّة */
$scr = array();
$q = $conn->query("SELECT screen_id, screen_file, canonical_label_ar FROM repair01_screen_registry WHERE on_disk = 1");
while ($x = $q->fetch_assoc()) { $scr[$x['screen_id']] = $x; }

$tot = array('MATCHED' => 0, 'MERGED_INTO' => 0, 'skip_no_anchor' => 0, 'skip_dead' => 0);
$upd = $conn->prepare("UPDATE repair01_target_universe SET
        verdict = ?, screen_id = ?, screen_file = ?, source = 'BOTH', match_method = 'WAVE_ANCHOR',
        match_witness = ?, verdict_witness = ?, verdict_snapshot = ?, verdict_at = NOW()
    WHERE target_uid = ? AND verdict = 'NOT_BUILT'");
/* ⛔ المرساةُ مرساةُ نطاقِ فحصٍ لا برهانُ خدمةٍ — فلا يُحسم بها هدفٌ متطلّبُه
   في دفترِه NOT_IMPLEMENTED (ارتدادُ 153 حكمًا قيس في 2026-09-01 علّم هذا الشرط) */
$q = $conn->query("SELECT u.target_uid, u.unit, u.name_ar, u.name_norm, u.requirement_id
    FROM repair01_target_universe u WHERE u.verdict = 'NOT_BUILT'");
while ($x = $q->fetch_assoc()) {
    $rid = $x['requirement_id'];
    if ($rid === '' || !isset($anchors[$rid])) { $tot['skip_no_anchor']++; continue; }
    list($sid, $ledger) = $anchors[$rid];
    if (!isset($scr[$sid])) { $tot['skip_dead']++; continue; }
    $s = $scr[$sid];
    $same = (nz2($s['canonical_label_ar']) === nz2($x['name_ar']));
    $verdict = $same ? 'MATCHED' : 'MERGED_INTO';
    $mw = "مرساةُ دفترِ الموجةِ {$ledger}: {$rid} ⇒ {$sid} «{$s['canonical_label_ar']}» — مثبتةٌ من القرصِ في بوّاباتِ موجتِها";
    $vw = $same
        ? "المرساةُ تطابق الاسمَ المطبَّعَ حرفًا — الجسرُ استنفد دفترَ الموجةِ قبل NOT_BUILT (§13) · لقطة {$SNAP}"
        : "الهدفُ يُخدم ضمن سطحِ مرساتِه «{$s['canonical_label_ar']}» ({$sid}) بحكمِ دفترِ موجتِه — لا شاشةَ مستقلّةً باسمِه · لقطة {$SNAP}";
    $tot[$verdict]++;
    if ($APPLY) {
        $upd->bind_param('sssssss', $verdict, $sid, $s['screen_file'], $mw, $vw, $SNAP, $x['target_uid']);
        $upd->execute();
    }
}
printf("الحصيلة %s: MATCHED %d · MERGED_INTO %d · بلا مرساةٍ %d · مرساةٌ لشاشةٍ ميّتة %d\n",
    $APPLY ? '(كتابة)' : '(قياس)', $tot['MATCHED'], $tot['MERGED_INTO'], $tot['skip_no_anchor'], $tot['skip_dead']);
$left = (int) $conn->query("SELECT COUNT(*) FROM repair01_target_universe WHERE verdict = 'NOT_BUILT'")->fetch_row()[0];
printf("المتبقّي NOT_BUILT في الكون: %d\n", $left);
