<?php
/**
 * tools/cmp03_wave2_migrate.php — ترحيل صفوف المخزن البيني إلى جداول الشاشات
 * ───────────────────────────────────────────────────────────────────────────
 * لكل شاشةٍ مسجَّلة: صفوفها في cmp03_screen_rows تُنسخ إلى جدولها scr_*
 * (الحمولة ← أعمدة · الفارغ NULL · تاريخٌ لا يطابق YYYY-MM-DD يُعلَن ويُترك
 * NULL) بحفظ company_id/status/is_seed/created_by/created_by_name/created_at،
 * ثم تُحذف من المخزن بعد مطابقة العدّ. غير المسجَّلة (my_tasks.php — شاشتها
 * حية على work_items والصفوف ميتة) تُصدَّر نسخةً ثم تُحذف (سابقة بذور M-00).
 * التشغيل: php tools/cmp03_wave2_migrate.php [--apply]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();
require_once __DIR__ . '/../includes/cmp03_registry.php';
require_once __DIR__ . '/../includes/cmp03_local_store.php';

$APPLY = in_array('--apply', $argv, true);
$reg = cmp03_registry();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$canons = array();
$r = mysqli_query($conn, 'SELECT canonical_file, COUNT(*) c FROM cmp03_screen_rows GROUP BY canonical_file');
while ($r && ($x = mysqli_fetch_assoc($r))) { $canons[$x['canonical_file']] = intval($x['c']); }

/* حارة الجلسة الموازية: شاشاتها ما زالت تقرأ المخزن — صفوفها لا تُمس هنا */
$PARALLEL_LANE = array('po_match.php' => 1, 'supplier_bank.php' => 1, 'wh_receipt.php' => 1);

$movedTotal = 0; $orphans = array(); $warnDates = 0;
foreach ($canons as $canon => $cnt) {
    if (isset($PARALLEL_LANE[$canon])) {
        fwrite(STDOUT, "── حارة موازية: {$canon} ({$cnt}) — تُترك كما هي\n");
        continue;
    }
    if (!isset($reg[$canon])) { $orphans[$canon] = $cnt; continue; }
    $table = $reg[$canon]['table'];
    $map   = $reg[$canon]['map'];
    fwrite(STDOUT, "── {$canon} → {$table} ({$cnt} صفًّا)\n");
    if (!$APPLY) { continue; }

    $rows = array();
    $st = $conn->prepare("SELECT * FROM cmp03_screen_rows WHERE canonical_file = ? ORDER BY id");
    $st->bind_param('s', $canon);
    $st->execute();
    $rs = $st->get_result();
    while ($x = $rs->fetch_assoc()) { $rows[] = $x; }
    $st->close();

    $moved = 0;
    $conn->begin_transaction();
    try {
        foreach ($rows as $x) {
            $p = json_decode((string) $x['payload'], true) ?: array();
            $cols = array('company_id', 'status', 'is_seed', 'created_by', 'created_by_name', 'created_at');
            $vals = array(intval($x['company_id']), (string) $x['status'], intval($x['is_seed']),
                          intval($x['created_by']), (string) $x['created_by_name'], (string) $x['created_at']);
            $types = 'isiiss';
            foreach ($p as $label => $v) {
                $n = cmp03_store_norm($label);
                if (!isset($map[$n])) { continue; } // تسمية خارج تصميم الشاشة — تبقى في النسخة الاحتياطية
                $v = trim((string) $v);
                if ($v === '') { continue; }
                // عمود تاريخ بقيمة غير تاريخية ⇒ NULL معلَن (لا تلفيق تحويل)
                $isDateCol = (bool) preg_match('/^(تاريخ |من تاريخ$|إلى تاريخ$)/u', $n);
                if ($isDateCol && !preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) {
                    fwrite(STDOUT, "  ⚠ تاريخ غير صالح في #{$x['id']} «{$label}»: {$v} → NULL\n");
                    $GLOBALS['warnDates'] = ++$warnDates;
                    continue;
                }
                if ($isDateCol) { $v = substr($v, 0, 10); }
                $cols[] = $map[$n];
                $vals[] = $v;
                $types .= 's';
            }
            $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $cols) . "`)
                    VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ")";
            $ins = $conn->prepare($sql);
            if (!$ins) { throw new Exception($conn->error); }
            $ins->bind_param($types, ...$vals);
            if (!$ins->execute()) { throw new Exception($ins->error); }
            $ins->close();
            $moved++;
        }
        // المطابقة قبل الحذف — لا حذف بلا وصول
        if ($moved !== count($rows)) { throw new Exception("عدّ غير مطابق: {$moved} من " . count($rows)); }
        $del = $conn->prepare("DELETE FROM cmp03_screen_rows WHERE canonical_file = ?");
        $del->bind_param('s', $canon);
        $del->execute();
        if ($del->affected_rows !== count($rows)) { throw new Exception('حذف غير مطابق'); }
        $del->close();
        $conn->commit();
        $movedTotal += $moved;
        fwrite(STDOUT, "  ✔ رُحّل {$moved} وحُذف من المخزن\n");
    } catch (Exception $e) {
        $conn->rollback();
        fwrite(STDOUT, "  ✖ تراجُع: " . $e->getMessage() . "\n");
        exit(1);
    }
}

/* اليتيمة (شاشتها لا تقرأ المخزن): تصدير ثم حذف */
if ($orphans) {
    foreach ($orphans as $canon => $cnt) {
        fwrite(STDOUT, "── يتيمة: {$canon} ({$cnt}) — تصدير ثم حذف\n");
    }
    if ($APPLY) {
        $in = "'" . implode("','", array_map(function ($c) use ($conn) {
            return mysqli_real_escape_string($conn, $c); }, array_keys($orphans))) . "'";
        $rows = array();
        $r = mysqli_query($conn, "SELECT * FROM cmp03_screen_rows WHERE canonical_file IN ({$in})");
        while ($r && ($x = mysqli_fetch_assoc($r))) { $rows[] = $x; }
        $f = __DIR__ . '/../storage/backups/cmp03_wave2_orphans_' . date('Ymd_His') . '.json';
        file_put_contents($f, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        mysqli_query($conn, "DELETE FROM cmp03_screen_rows WHERE canonical_file IN ({$in})");
        fwrite(STDOUT, "  ✔ صُدّر " . count($rows) . " إلى {$f} وحُذف " . mysqli_affected_rows($conn) . "\n");
    }
}

$r = mysqli_query($conn, 'SELECT COUNT(*) FROM cmp03_screen_rows');
fwrite(STDOUT, "────────────\nرُحّل: {$movedTotal} · تواريخ أُعلنت NULL: {$warnDates} · بقي في المخزن: "
    . intval(mysqli_fetch_row($r)[0]) . "\n");
