<?php
/**
 * tools/gov_exec_wh_recon.php — مصالحةُ نسبِ WH-* مع الحزمةِ الحاكمةِ الجديدة (م 121)
 * ═══════════════════════════════════════════════════════════════════════════
 * الحزمةُ -3/-2 أدرجت «إسناد أمناء المخازن» برقم 3 فانزاح WH-03..18 ⇒ WH-04..19.
 * هذه الأداةُ ترحّل المعرِّفاتِ **بالاسمِ المطبَّعِ لا بالرقم** حاملةً الأحكامَ
 * والأدلّةَ كما هي (لا تمسُّ amd01_state ولا state_evidence ولا أيَّ عمودِ حكم) —
 * وتقيّد المصالحةَ صفًّا صفًّا في `gov_req_id_recon` ثم تدرج الهدفَ الجديدَ
 * وحقولَه الـ16 وفرقَي الحقولِ الآخرَين (WH-02#5 استبدالًا · العهد +«المخزن الصارف»).
 * إعادةُ تشغيلِها آمنةٌ: تكتشف الترقيمَ المنجزَ وتتحقّق بدل أن تعيد.
 * التشغيل: php tools/gov_exec_wh_recon.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/tools/lib/xlsx_io.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("⛔ تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$PACK = 'GOV_PACK_20260831';
$SNAP = trim(shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));

function nz($s)
{
    $s = (string) $s;
    $s = str_replace(array('أ', 'إ', 'آ'), 'ا', $s);
    $s = str_replace('ى', 'ي', $s);
    $s = str_replace('ة', 'ه', $s);
    $s = preg_replace('~[\x{064B}-\x{065F}\x{0640}]~u', '', $s);
    $s = preg_replace('~\s+~u', ' ', trim($s));
    return $s;
}

/* ═══ ① قراءةُ المصدرِ الحاكمِ الجديد ═══ */
$wb = xlsx_read($ROOT . '/docs/REPAIR01_20260823/09 · السجلات المؤسسية والقرارات.xlsx');
$reqSheet = $wb['01_سجل_المتطلبات'];
$fldSheet = $wb['02_تتبع_الحقول'];
$newReq = array();   // new_id => ['surface','grain','group','seq','sot','row']
foreach ($reqSheet as $i => $r) {
    $id = (string) ($r[0] ?? '');
    if (!preg_match('~^WH-\d+$~', $id)) { continue; }
    $newReq[$id] = array('surface' => (string) $r[6], 'grain' => (string) $r[7],
        'group' => (string) $r[5], 'seq' => (string) $r[4], 'sot' => (string) $r[8],
        'dep' => (string) $r[3], 'unit' => (string) $r[2], 'wave' => (string) $r[1], 'row' => $i + 1);
}
$newFld = array();   // new_id => list of ['seq','name','type','rule','row']
foreach ($fldSheet as $i => $r) {
    $id = (string) ($r[0] ?? '');
    if (!preg_match('~^WH-\d+$~', $id)) { continue; }
    $newFld[$id][] = array('seq' => (string) $r[4], 'name' => (string) $r[5],
        'type' => (string) $r[6], 'rule' => (string) $r[7], 'row' => $i + 1);
}
printf("① الحاكمُ الجديد: %d متطلّبًا · %d حقلًا\n", count($newReq), array_sum(array_map('count', $newFld)));

/* ═══ ② المزاوجةُ بالاسمِ المطبَّع ═══ */
$live = array();     // old_id => surface
$q = $conn->query("SELECT requirement_id, surface FROM repair01_requirements WHERE requirement_id LIKE 'WH-%'");
while ($r = $q->fetch_assoc()) { $live[$r['requirement_id']] = $r['surface']; }
$liveByName = array();
foreach ($live as $oid => $srf) { $liveByName[nz($srf)] = $oid; }

$already = isset($live['WH-19']);   // علامةُ ترقيمٍ منجز
$map = array();      // old_id => new_id (المنزاح والثابت)
$newTargets = array();
foreach ($newReq as $nid => $meta) {
    $key = nz($meta['surface']);
    if (isset($liveByName[$key])) { $map[$liveByName[$key]] = $nid; }
    else { $newTargets[] = $nid; }
}
$unmatchedLive = array_diff_key($live, $map);
printf("② مزاوجة: %d مرحَّلًا · %d هدفًا جديدًا · %d حيًّا بلا نظير\n",
    count($map), count($newTargets), count($unmatchedLive));
if (count($unmatchedLive) > 0 && !$already) {
    foreach ($unmatchedLive as $oid => $srf) { echo "  ⛔ بلا نظيرٍ في الحاكم: {$oid} «{$srf}»\n"; }
    exit(1);
}
if ($newTargets !== array('WH-03') && !$already) {
    echo '⛔ المتوقَّعُ هدفٌ جديدٌ واحدٌ (WH-03) — الواقع: ' . implode('،', $newTargets) . "\n"; exit(1);
}

/* ═══ ③ قيدُ المصالحةِ صفًّا صفًّا ═══ */
$ins = $conn->prepare("INSERT IGNORE INTO gov_req_id_recon
    (pack_ref, unit, old_id, new_id, surface_norm, kind, basis) VALUES (?,?,?,?,?,?,?)");
$n3 = 0;
foreach ($map as $oid => $nid) {
    $kind = ($oid === $nid) ? 'UNCHANGED' : 'SHIFTED';
    $basis = ($oid === $nid)
        ? 'الاسمُ المطبَّعُ مطابقٌ والرقمُ ثابتٌ بين -2 و-3'
        : "الحزمةُ -3 أدرجت «إسناد أمناء المخازن» برقم 3 فانزاح {$oid} ⇒ {$nid} — المزاوجةُ بالاسمِ المطبَّعِ «" . nz($newReq[$nid]['surface']) . '»';
    $unit = $newReq[$nid]['unit']; $norm = nz($newReq[$nid]['surface']);
    $ins->bind_param('sssssss', $PACK, $unit, $oid, $nid, $norm, $kind, $basis);
    $ins->execute(); $n3 += $ins->affected_rows > 0 ? 1 : 0;
}
foreach ($newTargets as $nid) {
    $kind = 'NEW_TARGET'; $oid = null;
    $basis = 'هدفٌ جديدٌ بالحزمةِ -3 بلا سلفٍ — «' . $newReq[$nid]['surface'] . '» (Child of خ02 · أساسُ تصفيةِ الحركةِ بنطاقِ الأمين)';
    $unit = $newReq[$nid]['unit']; $norm = nz($newReq[$nid]['surface']);
    $ins->bind_param('sssssss', $PACK, $unit, $oid, $nid, $norm, $kind, $basis);
    $ins->execute(); $n3 += $ins->affected_rows > 0 ? 1 : 0;
}
printf("③ قُيّد في gov_req_id_recon: %d صفًّا جديدًا (الكلّي بعدُ ثابت)\n", $n3);

/* ═══ ④ الترقيمُ تنازليًّا في السجلّاتِ الحيّةِ كلِّها ═══ */
$TABLES = array(
    'repair01_requirements'        => 'requirement_id',
    'repair01_target_universe'     => 'requirement_id',
    'repair01_fields'              => 'requirement_id',
    'repair01_field_measure'       => 'requirement_id',
    'repair01_build_ready'         => 'requirement_id',
    'repair01_build_ready_history' => 'requirement_id',
    'repair01_evidence_closure'    => 'requirement_id',
    'repair01_render_align'        => 'requirement_id',
    'repair01_sidebar_align'       => 'requirement_id',
    'repair01_w9_scope'            => 'requirement_id',
    'repair01_w9_deferred'         => 'requirement_id',
);
if ($already) {
    echo "④ الترقيمُ منجزٌ سلفًا (WH-19 موجود) — تحقّقٌ فقط\n";
} else {
    $shift = array();
    foreach ($map as $oid => $nid) { if ($oid !== $nid) { $shift[$oid] = $nid; } }
    krsort($shift);   // WH-18 أولًا فلا تصادم
    $tot = 0;
    foreach ($shift as $oid => $nid) {
        foreach ($TABLES as $t => $col) {
            $st = $conn->prepare("UPDATE `$t` SET `$col` = ? WHERE `$col` = ?");
            $st->bind_param('ss', $nid, $oid);
            if (!$st->execute()) { exit("⛔ فشل ترقيم $t: {$conn->error}\n"); }
            $tot += $st->affected_rows;
        }
    }
    printf("④ رُقّم تنازليًّا: %d صفًّا عبر %d جدولًا\n", $tot, count($TABLES));
}

/* ═══ ⑤ تحديثُ سطرِ كلِّ متطلّبٍ لمقامِه الجديد (seq · src_ref · شاهدُ الكتلة) ═══ */
$n5 = 0;
$sel = $conn->prepare("SELECT type_witness FROM repair01_requirements WHERE requirement_id = ?");
$upd = $conn->prepare("UPDATE repair01_requirements SET seq = ?, src_ref = ?, type_witness = ? WHERE requirement_id = ?");
foreach ($newReq as $nid => $meta) {
    $sel->bind_param('s', $nid); $sel->execute();
    $row = $sel->get_result()->fetch_row();
    if ($row === null) { continue; }
    $tw = $row[0];
    if ($tw !== null && strpos($tw, 'FC16:') === 0) {
        $tw = preg_replace('~كتلة #\d+~u', 'كتلة #' . (int) $meta['seq'], $tw);
    }
    $src = '09 › 01_سجل_المتطلبات › ص' . $meta['row'];
    $seqS = (string) (int) $meta['seq'];
    $upd->bind_param('ssss', $seqS, $src, $tw, $nid);
    $upd->execute(); $n5 += $upd->affected_rows;
}
printf("⑤ حُدِّث مقامُ الصفوف (seq/src_ref/شاهدُ الكتلة): %d صفًّا\n", $n5);

/* ═══ ⑥ إدراجُ الهدفِ الجديدِ WH-03 (إن لم يوجد) ═══ */
$q = $conn->query("SELECT COUNT(*) FROM repair01_requirements WHERE requirement_id = 'WH-03'");
if ((int) $q->fetch_row()[0] === 0) {
    $m = $newReq['WH-03'];
    $sot = mb_substr($m['sot'], 0, 500);
    $src = '09 › 01_سجل_المتطلبات › ص' . $m['row'];
    $tw = 'FC16: النوع منصوص في الدليل المعماري ورقة 17 كتلة #3 («نوع الشاشة: Child of خ02» في سطر المصدر) — تأسيسٌ مرجعيٌّ تابعٌ لسجلِّ المخازن';
    $sev = 'قياسُ مصالحةِ الحزمةِ -3: لا سطحَ حيًّا يطابق الاسمَ المطبَّعَ «اسناد امناء المخازن» في سجلِّ الشاشاتِ ولا كونِ الأهداف — هدفٌ جديدٌ يُبنى بأمرِ GOV_EXEC §5';
    $ssnap = 'SNAP-govexec-' . $SNAP;
    $st = $conn->prepare("INSERT INTO repair01_requirements
        (requirement_id, wave, stage_no, unit, dependency, seq, group_name, surface, grain,
         source_of_truth, src_ref, amd01_state, requirement_type, type_witness, identity_status,
         state_evidence, state_snapshot, state_at)
        VALUES ('WH-03', ?, 9, ?, ?, ?, ?, ?, ?, ?, ?, 'NOT_IMPLEMENTED', 'STRUCTURAL', ?, 'MATCHED_BY_ID', ?, ?, NOW())");
    $st->bind_param('ssssssssssss', $m['wave'], $m['unit'], $m['dep'], $m['seq'],
        $m['group'], $m['surface'], $m['grain'], $sot, $src, $tw, $sev, $ssnap);
    if (!$st->execute()) { exit("⛔ فشل إدراج WH-03: {$conn->error}\n"); }
    echo "⑥ أُدرج الهدفُ الجديدُ WH-03 «إسناد أمناء المخازن» (NOT_IMPLEMENTED · STRUCTURAL)\n";
} else { echo "⑥ WH-03 قائمٌ سلفًا\n"; }

/* ═══ ⑦ الحقول: فرقا الاستبدالِ والإضافةِ ثم إدراجُ حقولِ WH-03 ثم تسويةُ src_ref/seq من الحاكم ═══ */
$conn->query("UPDATE repair01_fields SET field_name = 'الأمين النافذ اليوم ◄'
    WHERE requirement_id = 'WH-02' AND field_name = 'أمين المخزن ◄'");
if ($conn->affected_rows > 0) { echo "⑦أ WH-02#5: «أمين المخزن» ⇒ «الأمين النافذ اليوم ◄» (يُشتقّ من الإسناد)\n"; }
$q = $conn->query("SELECT COUNT(*) FROM repair01_fields WHERE requirement_id = 'WH-13' AND field_name = 'المخزن الصارف ◄'");
if ((int) $q->fetch_row()[0] === 0) {
    $conn->query("UPDATE repair01_fields SET seq = CAST(seq AS UNSIGNED) + 1
        WHERE requirement_id = 'WH-13' AND CAST(seq AS UNSIGNED) >= 2
        ORDER BY CAST(seq AS UNSIGNED) DESC");
    $conn->query("INSERT INTO repair01_fields (requirement_id, wave, unit, surface, seq, field_name, field_type, visibility_rule, src_ref)
        VALUES ('WH-13', 'B', '17 إدارة المخازن', 'العهد والمرتجعات', '2', 'المخزن الصارف ◄', 'DERIVED', 'قراءة محسوبة — لا إدخال', '')");
    echo "⑦ب WH-13: أُدرج «المخزن الصارف ◄» برقم 2 وأُزيحت البقيّة\n";
}
$q = $conn->query("SELECT COUNT(*) FROM repair01_fields WHERE requirement_id = 'WH-03'");
if ((int) $q->fetch_row()[0] === 0) {
    $st = $conn->prepare("INSERT INTO repair01_fields (requirement_id, wave, unit, surface, seq, field_name, field_type, visibility_rule, src_ref)
        VALUES ('WH-03', 'B', '17 إدارة المخازن', 'إسناد أمناء المخازن', ?, ?, ?, ?, ?)");
    foreach ($newFld['WH-03'] as $f) {
        $src = '09 › 02_تتبع_الحقول › ص' . $f['row'];
        $st->bind_param('sssss', $f['seq'], $f['name'], $f['type'], $f['rule'], $src);
        if (!$st->execute()) { exit("⛔ فشل حقل WH-03#{$f['seq']}: {$conn->error}\n"); }
    }
    echo '⑦ج أُدرجت حقولُ WH-03: ' . count($newFld['WH-03']) . " حقلًا بمصدرِ كلٍّ منها\n";
}
/* تسويةُ src_ref لكلِّ حقولِ WH من مواضعِها الجديدةِ في الحاكم (بالاسمِ والرقم) */
$n7 = 0;
$st = $conn->prepare("UPDATE repair01_fields SET src_ref = ? WHERE requirement_id = ? AND seq = ? AND field_name = ? AND src_ref <> ?");
foreach ($newFld as $nid => $rows) {
    foreach ($rows as $f) {
        $src = '09 › 02_تتبع_الحقول › ص' . $f['row'];
        $st->bind_param('sssss', $src, $nid, $f['seq'], $f['name'], $src);
        $st->execute(); $n7 += $st->affected_rows;
    }
}
printf("⑦د سُوّي src_ref للحقولِ من الحاكمِ الجديد: %d صفًّا\n", $n7);

/* ═══ ⑧ كونُ الأهداف: صفُّ WH-03 الجديد NOT_BUILT ═══ */
$q = $conn->query("SELECT COUNT(*) FROM repair01_target_universe WHERE requirement_id = 'WH-03'");
if ((int) $q->fetch_row()[0] === 0) {
    $mx = (int) $conn->query("SELECT MAX(CAST(SUBSTRING(target_uid,5) AS UNSIGNED)) FROM repair01_target_universe")->fetch_row()[0];
    $uid = sprintf('TGT-%04d', $mx + 1);
    $norm = nz('إسناد أمناء المخازن');
    $st = $conn->prepare("INSERT INTO repair01_target_universe
        (target_uid, source, unit, name_ar, name_norm, screen_file, requirement_id, screen_id,
         match_method, match_witness, verdict, verdict_witness, verdict_snapshot, verdict_at)
        VALUES (?, 'THEIRS', 'DEP-17', 'إسناد أمناء المخازن', ?, '', 'WH-03', '',
         'NONE', 'هدفٌ جديدٌ بالحزمةِ -3 — لا سطحَ حيًّا يطابقه بعد', 'NOT_BUILT',
         'الحزمةُ الحاكمةُ -3 أدرجت الشاشةَ برقم 3 (Child of خ02) — تُبنى بأمرِ GOV_EXEC §5', ?, NOW())");
    $snapRef = 'SNAP-govexec-' . $SNAP;
    $st->bind_param('sss', $uid, $norm, $snapRef);
    if (!$st->execute()) { exit("⛔ فشل كون الأهداف: {$conn->error}\n"); }
    echo "⑧ أُدرج {$uid} في كونِ الأهداف NOT_BUILT\n";
} else { echo "⑧ صفُّ الكونِ قائمٌ سلفًا\n"; }

/* ═══ ⑨ لوحةُ تحقّق ═══ */
$chk = array(
    'أهدافُ WH في الدفتر (المتوقَّع 19)' => "SELECT COUNT(*) FROM repair01_requirements WHERE requirement_id LIKE 'WH-%'",
    'مغلقاتٌ بالدليلِ صامدةٌ (المتوقَّع 8)' => "SELECT COUNT(*) FROM repair01_requirements WHERE requirement_id LIKE 'WH-%' AND amd01_state = 'EVIDENCE_CLOSED'",
    'صفوفُ مصالحةِ الحزمة (المتوقَّع 19)' => "SELECT COUNT(*) FROM gov_req_id_recon WHERE pack_ref = '$PACK'",
    'حقولُ WH (المتوقَّع 279)' => "SELECT COUNT(*) FROM repair01_fields WHERE requirement_id LIKE 'WH-%'",
    'معرِّفٌ قديمٌ يتيمٌ خارجَ الخريطة (المتوقَّع 0)' => "SELECT COUNT(*) FROM repair01_fields f LEFT JOIN repair01_requirements r ON r.requirement_id = f.requirement_id WHERE f.requirement_id LIKE 'WH-%' AND r.requirement_id IS NULL",
);
$fail = 0;
foreach ($chk as $label => $sql) {
    $v = (int) $conn->query($sql)->fetch_row()[0];
    preg_match('~المتوقَّع (\d+)~u', $label, $m);
    $want = (int) $m[1];
    $okc = ($v === $want);
    if (!$okc) { $fail++; }
    printf("  %s ⑨ %s = %d\n", $okc ? '✔' : '✘', $label, $v);
}
echo $fail === 0 ? "═ المصالحةُ تامّةٌ بلا دهسِ حكمٍ ═\n" : "⛔ {$fail} فحصًا راسبًا\n";
exit($fail === 0 ? 0 : 1);
