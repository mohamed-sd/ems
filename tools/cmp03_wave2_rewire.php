<?php
/**
 * tools/cmp03_wave2_rewire.php — إعادة توصيل الشاشات من المخزن البيني إلى جداولها
 * ───────────────────────────────────────────────────────────────────────────
 * الكتل مولَّدة موحّدة (cmp03_apply) فالاستبدال نصي دقيق مع تحقق لكل ملف:
 *   • كتلة الحفظ ← cmp03_store_insert()
 *   • كتلة القراءة ← cmp03_store_rows()
 *   • حقن require للمحوّل قبل سطر $CANONICAL
 * ملف لا تنطبق كتلتاه بالحرف يُتخطى معلَنًا (يُعالج يدويًّا) — لا استبدال جزئي.
 * التشغيل: php tools/cmp03_wave2_rewire.php [--apply]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
$ROOT = dirname(__DIR__);
$APPLY = in_array('--apply', $argv, true);

require_once $ROOT . '/includes/cmp03_registry.php';
$reg = cmp03_registry();

/* الشاشات المستهدفة: من السجل — عبر ملفاتها الفعلية */
$targets = array();
foreach (explode("\n", trim((string) shell_exec('git -C "' . $ROOT . '" grep -l cmp03_screen_rows -- "*.php"'))) as $f) {
    $f = trim($f);
    if ($f === '' || strpos($f, 'tools/') === 0 || strpos($f, 'database/') === 0
        || strpos($f, 'includes/') === 0 || strpos($f, 'app/') === 0) { continue; }
    $src = file_get_contents($ROOT . '/' . $f);
    if (preg_match("/\\\$CANONICAL\s*=\s*'([^']+)'/u", $src, $m) && isset($reg[$m[1]])) {
        $targets[$f] = $m[1];
    }
}

$INSERT_OLD = '    $st = $conn->prepare("INSERT INTO cmp03_screen_rows
        (company_id, canonical_file, payload, status, is_seed, created_by, created_by_name)
        VALUES (?, ?, ?, ?, 0, ?, ?)");
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $st->bind_param(\'isssis\', $company_id, $CANONICAL, $json, $status, $uid, $creator);
    $ok = $st->execute();
    $st->close();';
$INSERT_NEW = '    // الموجة ٢: الحفظ في الجدول الأصلي للشاشة (الفارغ NULL — لا مخزن بينيًّا)
    $ok = cmp03_store_insert($conn, $company_id, $CANONICAL, $payload, $status, $uid, $creator);';

$SELECT_OLD = '$rows = array();
$sql = "SELECT id, payload, status, created_by_name, created_at, is_seed
          FROM cmp03_screen_rows
         WHERE canonical_file = ?" . ($is_super_admin && $company_id <= 0 ? \'\' : \' AND company_id = ?\') . "
         ORDER BY id DESC LIMIT 500";
$st = $conn->prepare($sql);
if ($is_super_admin && $company_id <= 0) { $st->bind_param(\'s\', $CANONICAL); }
else { $st->bind_param(\'si\', $CANONICAL, $company_id); }
$st->execute();
$rs = $st->get_result();
while ($x = $rs->fetch_assoc()) {
    $x[\'payload\'] = json_decode((string) $x[\'payload\'], true) ?: array();
    $rows[] = $x;
}
$st->close();';
$SELECT_NEW = '// الموجة ٢: القراءة من الجدول الأصلي — الشكل القديم نفسه (id·payload·status·…)
$rows = cmp03_store_rows($conn, $CANONICAL, ($is_super_admin && $company_id <= 0) ? 0 : $company_id);';

$done = 0; $skipped = array();
foreach ($targets as $f => $canon) {
    $path = $ROOT . '/' . $f;
    $src = file_get_contents($path);
    $srcN = str_replace("\r\n", "\n", $src);
    if (strpos($srcN, $INSERT_OLD) === false || strpos($srcN, $SELECT_OLD) === false) {
        $skipped[] = $f . ' (كتلة غير مطابقة)';
        continue;
    }
    $new = str_replace($INSERT_OLD, $INSERT_NEW, $srcN);
    $new = str_replace($SELECT_OLD, $SELECT_NEW, $new);
    // حقن require للمحوّل قبل سطر $CANONICAL (مرة واحدة)
    if (strpos($new, 'cmp03_local_store.php') === false) {
        $new = preg_replace(
            "/(\n\\\$CANONICAL\s*=)/u",
            "\nrequire_once __DIR__ . '/../includes/cmp03_local_store.php'; // الموجة ٢ — الجدول الأصلي\n\$1",
            $new, 1);
    }
    if (strpos($new, 'cmp03_local_store.php') === false) {
        $skipped[] = $f . ' (تعذر حقن require)';
        continue;
    }
    if ($APPLY) { file_put_contents($path, $new); }
    $done++;
    fwrite(STDOUT, ($APPLY ? '✔ ' : '· ') . $f . " ← {$reg[$canon]['table']}\n");
}
fwrite(STDOUT, "────────────\n" . ($APPLY ? 'وُصل' : 'سيوصل') . ": {$done} · تخطّي: " . count($skipped) . "\n");
foreach ($skipped as $s) { fwrite(STDOUT, "⚠ {$s}\n"); }
exit($skipped ? 2 : 0);
