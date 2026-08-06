<?php
/**
 * tools/h06_soft_delete_reconcile.php — مصالحةُ علامتَي الحذف الناعم (ح-06) · v1
 * ═══════════════════════════════════════════════════════════════════════════
 * المشكلة: 662 صفًّا في 64 جدولًا تحمل `deleted_at` بينما `is_deleted = 0` —
 * فالجوابُ عن «أمحذوفٌ هذا الصف؟» يختلف باختلاف العلامة التي يقرؤها الاستعلام.
 *
 * الحكمُ ولماذا:
 *   ① عقدُ البوابة (TenantDb::softDelete) يكتب الثلاثةَ معًا حصرًا:
 *      is_deleted=1 · deleted_at · deleted_by. فصفٌّ بـ deleted_at وحدَه
 *      **لم يمرّ بالبوابة قط** — ليس حذفًا نظاميًّا.
 *   ② الوثائق تنصّ: «عقدُ الحذف الناعم الثلاثي إلزامي» (PROMPT_CON02 §7 · §6).
 *   ③ البصمةُ الجنائية: الـ662 كلُّها deleted_by=4 بلا استثناء عبر 64 جدولًا
 *      وبتواريخَ من 2024 إلى 2026 — بينما الحذفُ النظاميُّ الحقيقيُّ 32 صفًّا
 *      بـ deleted_by متنوع. هذه بصمةُ بذرٍ آليٍّ لا حذفٍ بشري.
 *   ④ الترجيحُ العملي: 245 ملفًّا يرشّح بـ is_deleted مقابل 9 بـ deleted_at.
 *
 * ⇒ `deleted_at` اليتيمُ ضجيجٌ يُمسح، والصفُّ يبقى حيًّا كما هو ظاهرٌ اليوم.
 *   (العكسُ — ضبطُ is_deleted=1 — كان سيُخفي 662 صفًّا ظاهرًا الآن.)
 *
 * أثرُ الرؤية المقيس قبل التنفيذ: **صفر**. القرّاءُ التسعةُ الذين يرشّحون
 * بـ deleted_at يقرؤون `contracts` (صفرُ تناقض) و`unit_party_awards`
 * (خارج مجموعة التناقض — بلا عمود is_deleted). فلا صفَّ تتغيّر رؤيتُه.
 *
 * php tools/h06_soft_delete_reconcile.php            → تجريبٌ (لا يكتب)
 * php tools/h06_soft_delete_reconcile.php --apply    → تنفيذٌ بنسخةِ تراجع
 * php tools/h06_soft_delete_reconcile.php --rollback → استرجاعٌ من النسخة
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$APPLY    = in_array('--apply', $argv, true);
$ROLLBACK = in_array('--rollback', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };
// نسخةُ التراجع ملفٌّ لا جدول: DDL مجمَّدٌ في هذا المستودع (EMS_DDL_FREEZE)
// والملفُّ أنقلُ للمراجعة وأبقى بعد أي استرجاعٍ للقاعدة.
$BACKUP = __DIR__ . '/../storage/backups/h06_soft_delete_backup.json';

$o('══ مصالحةُ علامتَي الحذف الناعم (ح-06) ══');

// ── الجداولُ ذاتُ العلامتين ────────────────────────────────────────────────
$tables = array();
$q = mysqli_query($conn, "SELECT TABLE_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME IN ('is_deleted','deleted_at')
    GROUP BY TABLE_NAME HAVING COUNT(*) = 2 ORDER BY TABLE_NAME");
while ($r = mysqli_fetch_assoc($q)) { $tables[] = $r['TABLE_NAME']; }

// ── الاسترجاع ──────────────────────────────────────────────────────────────
if ($ROLLBACK) {
    if (!file_exists($BACKUP)) { $o('  لا نسخةَ تراجعٍ في ' . $BACKUP); exit(1); }
    $rows = json_decode(file_get_contents($BACKUP), true);
    if (!is_array($rows)) { $o('  نسخةُ التراجع تالفة'); exit(1); }
    $restored = 0;
    foreach ($rows as $r) {
        $t = preg_replace('/[^A-Za-z0-9_]/', '', $r['tbl']);
        $st = $conn->prepare("UPDATE `$t` SET deleted_at = ?, deleted_by = ? WHERE id = ?");
        if (!$st) { continue; }
        $da = $r['deleted_at']; $db = $r['deleted_by']; $rid = intval($r['row_id']);
        $st->bind_param('sii', $da, $db, $rid);
        $st->execute();
        $restored += $st->affected_rows;
        $st->close();
    }
    $o('  استُرجع: ' . $restored . ' صفًّا من ' . count($rows));
    exit(0);
}

// ── الجرد ──────────────────────────────────────────────────────────────────
$plan = array(); $total = 0;
foreach ($tables as $t) {
    $hasBy = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE 'deleted_by'")) > 0;
    $x = @mysqli_query($conn, "SELECT COUNT(*) c FROM `$t` WHERE COALESCE(is_deleted,0)=0 AND deleted_at IS NOT NULL");
    if (!$x) { continue; }
    $n = intval(mysqli_fetch_assoc($x)['c']);
    if ($n > 0) { $plan[$t] = array('n' => $n, 'by' => $hasBy); $total += $n; }
}
$o('  جداولُ متناقضة: ' . count($plan) . ' · صفوفٌ: ' . $total);
if ($total === 0) { $o('  لا شيءَ يُصالَح — نظيف ✓'); exit(0); }

if (!$APPLY) {
    $i = 0;
    foreach ($plan as $t => $d) { if (++$i > 12) { break; } $o(sprintf('    %-34s %s', $t, $d['n'])); }
    if (count($plan) > 12) { $o('    … و' . (count($plan) - 12) . ' جدولًا آخر'); }
    $o('  (تجريبٌ — أعِد التشغيل بـ --apply للتنفيذ)');
    exit(0);
}

// ── نسخةُ التراجع (ملفٌّ — لا DDL) ─────────────────────────────────────────
$dir = dirname($BACKUP);
if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
$snapshot = array(); $now = date('Y-m-d H:i:s');
foreach ($plan as $t => $d) {
    $bySel = $d['by'] ? 'deleted_by' : 'NULL';
    $rs = mysqli_query($conn, "SELECT id, deleted_at, $bySel AS db FROM `$t`
                                WHERE COALESCE(is_deleted,0)=0 AND deleted_at IS NOT NULL");
    if (!$rs) { continue; }
    while ($r = mysqli_fetch_assoc($rs)) {
        $snapshot[] = array('tbl' => $t, 'row_id' => intval($r['id']),
            'deleted_at' => $r['deleted_at'], 'deleted_by' => ($r['db'] === null ? null : intval($r['db'])),
            'taken_at' => $now);
    }
}
if (file_put_contents($BACKUP, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
    $o('  ✗ تعذّرت كتابةُ نسخة التراجع — أُوقف قبل أي تعديل.');
    exit(1);
}
$o('  حُفظ في نسخة التراجع: ' . count($snapshot) . ' صفًّا');
$o('    ' . str_replace('\\', '/', realpath($BACKUP)));

$cleared = 0;
foreach ($plan as $t => $d) {
    $set = $d['by'] ? 'deleted_at = NULL, deleted_by = NULL' : 'deleted_at = NULL';
    mysqli_query($conn, "UPDATE `$t` SET $set WHERE COALESCE(is_deleted,0)=0 AND deleted_at IS NOT NULL");
    $cleared += mysqli_affected_rows($conn);
}
$o('  مُسح الختمُ اليتيم  : ' . $cleared . ' صفًّا');

// ── التحقق ─────────────────────────────────────────────────────────────────
$left = 0;
foreach (array_keys($plan) as $t) {
    $x = @mysqli_query($conn, "SELECT COUNT(*) c FROM `$t` WHERE COALESCE(is_deleted,0)=0 AND deleted_at IS NOT NULL");
    if ($x) { $left += intval(mysqli_fetch_assoc($x)['c']); }
}
$o('  المتبقي بعد التنفيذ: ' . $left . ($left === 0 ? '  ✓' : '  ✗'));
exit($left === 0 ? 0 : 1);
