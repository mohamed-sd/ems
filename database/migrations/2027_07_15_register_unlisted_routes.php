<?php
/**
 * 2027_07_15_register_unlisted_routes.php — البندُ ① : صفرُ مسارٍ ظاهرٍ بلا صفّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ المواصفة (ف١٥-١ · البند ١): «لكلِّ مسارٍ في النظامِ صفٌّ واحدٌ… وبوابتُه:
 *   **صفرُ مسارٍ ظاهرٍ في السايدبارِ بلا صفٍّ في السجل**».
 *
 * ◆ والمقيسُ حيًّا: **372 مسارًا فريدًا** يُصيَّر في السايدبار، منها **13 بلا
 *   صفٍّ** في `nav_canonical` — ولا في دفترِ التدقيقِ ولا في المصفوفةِ v3.
 *   (وكان الرقمُ 41 قبلَ تطبيعِ مرساةِ التكرارِ `#N` — و24 منها مسجَّلةٌ بأصلِها.)
 *
 * ◆ **ولا يُخترع اسمٌ معياريٌّ لواحدٍ منها**: البندُ ٤ يقول «الشيوعُ اقتراحٌ لا
 *   مصدرَ حقيقة»، والبندُ ٢ يقول المسارُ الواحدُ اسمُه واحد. فتُسجَّل الثلاثةَ
 *   عشرَ بـ:
 *     · `decision_state = PENDING_OWNER` — فتحمل **اسمَها الحاليَّ** بنصِّ
 *       المصفوفةِ v3، ولا تدَّعي اسمًا معياريًّا لم يقرّه أحد.
 *     · `derivation` يقول **من أين جاء كلُّ حقل**: السايدبارُ الحيُّ لالاسمِ
 *       والمجموعةِ والترتيب، و`modules` لهويةِ الشاشةِ وصلاحيتِها.
 *
 * ◆ فالسجلُّ يكتمل (البند ①) **بلا اختراعِ حقيقة** (البند ④) — والاسمُ
 *   المعياريُّ يبقى قرارَ المالكِ حيث هو.
 *
 * ◆ والمصدرُ الحاكمُ مُثبَتٌ لا مُدَّعًى: الثلاثةَ عشرَ **كلُّها مسجَّلةٌ في
 *   `modules`** بمعرِّفٍ واسمٍ — أي مُعرَّفةٌ في نظامِ الصلاحياتِ سلفًا.
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
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/* ── المساراتُ الظاهرةُ بلا صفّ — تُكتشَف حيًّا لا تُكتب قائمةً ────────────── */
$canon = array();
$r = $conn->query("SELECT route FROM nav_canonical");
while ($r && ($x = $r->fetch_assoc())) { $canon[$x['route']] = 1; }

$missing = array();
$r = $conn->query("SELECT n.route, n.label_ar, n.group_id, n.sort_order, n.role_id, n.permission_code
                     FROM nav_items n
                    WHERE n.active = 1 AND n.route IS NOT NULL AND n.route <> '' AND n.route <> '#'
                    ORDER BY n.role_id, n.sort_order");
while ($r && ($x = $r->fetch_assoc())) {
    $rt = trim($x['route']);
    if ($rt === '' || $rt[0] === '#') { continue; }
    $rt = preg_replace('~^\.\./~', '', $rt);
    $rt = preg_replace('~\?.*$~', '', $rt);
    $rt = preg_replace('~#\d+$~', '', $rt);      /* مرساةُ التكرارِ ليست مسارًا */
    $rt = ltrim($rt, '/');
    if ($rt === '' || isset($canon[$rt]) || isset($missing[$rt])) { continue; }
    $missing[$rt] = $x;
}

if (!$missing) { echo "✔ لا مسارَ ظاهرًا بلا صفّ — لا شيءَ يُسجَّل\n"; exit(0); }

/* ── أعلى سطرٍ في السجلِّ لتسلسلِ الصفوفِ الجديدة ─────────────────────────── */
$maxRow = 0;
$r = $conn->query("SELECT COALESCE(MAX(matrix_row), 0) m FROM nav_canonical");
if ($r) { $maxRow = (int) $r->fetch_assoc()['m']; }

$st = $conn->prepare("INSERT INTO nav_canonical
        (route, canonical_ar, canonical_en, level_no, level_name, group_name, sort_no,
         nature, owner_dept, status, decision_state, application_state, derivation,
         current_label, current_parent, matrix_row)
        VALUES (?,?,?,?,?,?,?,?,?,?,'PENDING_OWNER','CURRENT',?,?,?,?)");

$added = 0; $skipped = array();
foreach ($missing as $rt => $x) {
    /* ① هويةُ الشاشةِ من `modules` — وبلا صفٍّ هناك لا تُسجَّل (لا تُخترع) */
    $e = $conn->real_escape_string($rt);
    $m = $conn->query("SELECT id, name FROM modules WHERE code = '{$e}' LIMIT 1");
    $mod = ($m && $m->num_rows) ? $m->fetch_assoc() : null;
    if (!$mod) { $skipped[] = $rt . ' — لا صفَّ في `modules` فلا هويةَ حاكمة'; continue; }

    /* ② المجموعةُ والمستوى من السايدبارِ الحيّ */
    $gid = (int) $x['group_id'];
    $g = $conn->query("SELECT name, stage_no, stage_title, owner_role_id FROM link_groups WHERE id = {$gid}");
    $grp = ($g && $g->num_rows) ? $g->fetch_assoc() : null;
    $groupName = $grp ? (string) $grp['name'] : 'أخرى — للمراجعة';
    $levelNo   = $grp && $grp['stage_no'] !== null ? (int) $grp['stage_no'] : 99;
    $levelName = $grp && $grp['stage_title'] ? (string) $grp['stage_title'] : 'خارجَ المراحلِ المعيارية';
    $ownerDept = $grp ? ('دور ' . (int) $grp['owner_role_id']) : ('دور ' . (int) $x['role_id']);

    /* ③ الاسمُ الحاليُّ كما يُصيَّر — **ولا اسمَ معياريًّا يُخترع** */
    $label = trim((string) $x['label_ar']);
    if ($label === '') { $label = (string) $mod['name']; }
    /* الاسمُ المعياريُّ = الحاليُّ ما دام القرارُ معلَّقًا (نصُّ المصفوفة v3) */
    $canonAr = $label;
    $canonEn = '';

    /* ④ الطبيعةُ من اسمِ الملفِّ — إشارةٌ لا حكم، ومصدرُها معلَن */
    $base = strtolower(basename($rt, '.php'));
    if (preg_match('~_config$|_settings$|_types$|_categories$~', $base))      { $nature = 'إعداد'; }
    elseif (preg_match('~report|watchtower|board|dashboard~', $base))         { $nature = 'قارئة'; }
    elseif (preg_match('~_run$|_close$|classify|search~', $base))             { $nature = 'فعل'; }
    else                                                                      { $nature = 'سجل'; }

    $deriv = 'السايدبارُ الحيُّ (الاسمُ والمجموعةُ والترتيب) + `modules`#'
           . (int) $mod['id'] . ' (الهويةُ والصلاحية) — مسجَّلٌ لإكمالِ البند ①'
           . ' والاسمُ المعياريُّ **لم يُخترع**: القرارُ معلَّقٌ للمالك';

    $maxRow++;
    $sort = (int) $x['sort_order'];
    $parentCur = $groupName;
    /* `status` عمودُ الحالةِ القديمِ — **يُسنَد قبلَ الربطِ لا بعدَه**:
       الربطُ بمرجعٍ لمتغيّرٍ لم يُعرَّف بعدُ يمرُّ صامتًا بقيمةِ NULL في أولِ
       دورةٍ لو تغيَّر ترتيبُ الأسطر، وهو عطبٌ لا يُرى في المخرَج. */
    $statusVal = 'PENDING_OWNER';
    /* وسلسلةُ الأنواعِ **أربعةَ عشرَ حرفًا لأربعةَ عشرَ معاملًا** — والنقصُ
       يجعل mysqli يرفض الجملةَ كلَّها، فيُسجَّل صفرٌ ويُقرأ «لا شيءَ ناقص». */
    $st->bind_param('sssississssssi',
        $rt, $canonAr, $canonEn, $levelNo, $levelName, $groupName, $sort,
        $nature, $ownerDept, $statusVal, $deriv, $label, $parentCur, $maxRow);
    if ($st->execute()) { $added++; }
    else { $skipped[] = $rt . ' — ' . $st->error; }
}

echo "════ تسجيلُ المساراتِ الظاهرةِ بلا صفّ ════\n";
echo "  مكتشَفٌ حيًّا: " . count($missing) . " · سُجِّل: {$added}\n";
foreach ($skipped as $s) { echo "  ⚠ {$s}\n"; }
$n = (int) $conn->query("SELECT COUNT(*) c FROM nav_canonical")->fetch_assoc()['c'];
$pend = (int) $conn->query("SELECT COUNT(*) c FROM nav_canonical WHERE decision_state='PENDING_OWNER'")->fetch_assoc()['c'];
echo "  السجلُّ الآن: {$n} صفًّا · معلَّقٌ للمالك: {$pend}\n";
echo "✔ الاسمُ المعياريُّ لم يُخترع لواحدٍ منها — والقرارُ حيث هو\n";
