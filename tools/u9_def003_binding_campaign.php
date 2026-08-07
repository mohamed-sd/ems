<?php
/**
 * tools/u9_def003_binding_campaign.php — حملةُ ربطِ الأفعال (DEF-003 · update0009)
 * ═══════════════════════════════════════════════════════════════════════════
 * بعد إغلاق DEF-001 (هجرة 2026_11_17): كلُّ رمزٍ معلَّقٍ يُحسم بسلّمٍ صادقٍ لا يلفّق:
 *   ① exact  — الرمزُ نفسُه فعلٌ حيٌّ نشطٌ في actions            → alias
 *   ② module — لوحدةِ شاشتِه فعلُ كتابةٍ حيٌّ واحدٌ لا لبسَ فيه   → alias
 *   ③ page   — شاشتُه تعالج POST بنفسها (حارسُها في ملفها)      → bound_page
 *              live_code = 'page:<real_path>' — هويةُ المعالجِ الحقيقيةُ بلا
 *              حشوٍ في actions (المعلقُ لا يدخل actions — عرف الورقة 97)
 *   ④ declared — لا معالجَ بعدُ: زرُّه لم يُبنَ                  → declared_unbuilt
 * «صفر رمزٍ بحالة pending» هو شاهدُ الإغلاق — وفحص ⑤ نصُّه: «صفرُ رمزٍ بلا
 * معالجٍ أو حالةٍ معلنة». الغامضُ (أفعالُ كتابةٍ عدةٌ لوحدةٍ واحدة) يُطرح في
 * ملف مراجعةٍ ولا يُخمَّن.
 *
 * php tools/u9_def003_binding_campaign.php [--apply] [--csv=path]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$APPLY = in_array('--apply', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };
$csvPath = $ROOT . '/docs/update0009/ACTION_BINDING_LEDGER.csv';
foreach ($argv as $a) { if (strpos($a, '--csv=') === 0) { $csvPath = substr($a, 6); } }

/* السجلات المرجعية */
$live = array(); // action_code => row
$r = mysqli_query($conn, "SELECT action_code, module_id, is_write, handler_path, handler_class FROM actions WHERE active = 1");
while ($x = mysqli_fetch_assoc($r)) { $live[$x['action_code']] = $x; }

$moduleByPath = array(); // real_path => module_id
$r = mysqli_query($conn, "SELECT id, code FROM modules");
while ($x = mysqli_fetch_assoc($r)) { $moduleByPath[$x['code']] = (int) $x['id']; }

$writeActionsByModule = array(); // module_id => [action_code,...]
foreach ($live as $code => $a) {
    if ((int) $a['is_write'] === 1 && $a['module_id'] !== null) {
        $writeActionsByModule[(int) $a['module_id']][] = $code;
    }
}

$fileMap = array(); // canonical_file => real_path
$r = mysqli_query($conn, "SELECT canonical_file, real_path FROM nav09_file_map WHERE real_path IS NOT NULL AND state <> 'soon'");
while ($x = mysqli_fetch_assoc($r)) { $fileMap[$x['canonical_file']] = $x['real_path']; }

/* أتعالج الشاشةُ أفعالَها بنفسها؟ (POST ذاتي أو حارسُ أفعالٍ في الملف) */
$selfHandles = function ($realPath) use ($ROOT) {
    $p = $ROOT . '/' . $realPath;
    if (!is_file($p)) { return false; }
    $src = file_get_contents($p);
    if ($src === false) { return false; }
    $marks = function ($s) {
        return (bool) (preg_match('/REQUEST_METHOD.{0,12}POST/', $s)
            || strpos($s, "\$_POST[") !== false
            || strpos($s, 'action_guard') !== false);
    };
    if ($marks($src)) { return true; }
    /* عمقٌ واحد: مضمَّناتُ الشاشةِ المحلية (helpers قد تحمل معالجةَ POST) */
    if (preg_match_all("~(?:require|include)(?:_once)?\\s*\\(?\\s*__DIR__\\s*\\.\\s*['\"]/([A-Za-z0-9_.-]+\\.php)~", $src, $m)) {
        foreach ($m[1] as $inc) {
            $ip = $ROOT . '/' . dirname($realPath) . '/' . $inc;
            if (is_file($ip)) { $isrc = file_get_contents($ip); if ($isrc !== false && $marks($isrc)) { return true; } }
        }
    }
    return false;
};

/* معالجاتُ الكتابة الحيةُ التي يستدعيها مصدرُ الشاشة (باسم ملف المعالج) */
$writeHandlerBase = array(); // basename => [action_code,...]
foreach ($live as $code => $a) {
    if ((int) $a['is_write'] === 1 && $a['handler_path'] !== null && $a['handler_path'] !== '') {
        $writeHandlerBase[basename($a['handler_path'])][] = $code;
    }
}
$referencedWriteActions = function ($realPath) use ($ROOT, $writeHandlerBase) {
    $p = $ROOT . '/' . $realPath;
    if (!is_file($p)) { return array(); }
    $src = file_get_contents($p);
    if ($src === false) { return array(); }
    $found = array();
    foreach ($writeHandlerBase as $base => $codes) {
        if (strpos($src, $base) !== false) { foreach ($codes as $c) { $found[$c] = 1; } }
    }
    return array_keys($found);
};
/* ملفُ معالجٍ منفصلٌ يستدعيه المصدر (handler/ajax/هدفُ نموذج) موجودٌ على القرص */
$referencedHandlerFile = function ($realPath) use ($ROOT) {
    $p = $ROOT . '/' . $realPath;
    if (!is_file($p)) { return null; }
    $src = file_get_contents($p);
    if ($src === false) { return null; }
    $cands = array();
    if (preg_match_all("~['\"]([A-Za-z0-9_/.-]+_handler\\.php|[A-Za-z0-9_/.-]*ajax/[A-Za-z0-9_/.-]+\\.php)~", $src, $m)) {
        foreach ($m[1] as $c) { $cands[] = $c; }
    }
    /* هدفُ نموذجٍ صريح: <form action="..."> — معالجُ الفعلِ الفعلي */
    if (preg_match_all("~action=[\"']([A-Za-z0-9_/.-]+\\.php)~", $src, $m)) {
        foreach ($m[1] as $c) { $cands[] = $c; }
    }
    foreach ($cands as $cand) {
        $cand = ltrim($cand, './');
        if (preg_match('~(^|/)(config|inheader|insidebar|footer|dashboard|session_bootstrap)~', $cand)) { continue; }
        foreach (array($cand, dirname($realPath) . '/' . $cand) as $try) {
            if (is_file($ROOT . '/' . $try)) { return str_replace('\\', '/', $try); }
        }
    }
    return null;
};

/* مواءماتٌ يدويةٌ مثبَتة (عرفُ مستورد الورقة 97): رمزٌ → معالجُه المتحقَّقُ منه */
$OVERRIDES = array(
    'msg.send'     => array('page:chats/send_message.php', 'bound_page', 'معالجُ الإرسال الحيُّ — مثبَتٌ يدويًّا'),
    'receipt.open' => array('page:Procurement/receipt_custody_proc.php', 'bound_page', 'شاشةُ سير الاستلام تعالج POST (14 موضعًا) — مثبَتٌ يدويًّا'),
);

$pending = array();
$r = mysqli_query($conn, "SELECT canonical_code, screen_title, canonical_file FROM nav09_action_map WHERE state = 'pending' ORDER BY canonical_code");
while ($x = mysqli_fetch_assoc($r)) { $pending[] = $x; }

@mkdir(dirname($csvPath), 0777, true);
$fh = fopen($csvPath, 'w');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, array('canonical_code', 'screen', 'real_path', 'resolution', 'live_code', 'new_state', 'note'));

$cnt = array('exact' => 0, 'module' => 0, 'handler_ref' => 0, 'page' => 0, 'declared' => 0, 'ambiguous' => 0);
$updates = array(); // [code, live_code|null, state]
foreach ($pending as $p) {
    $code = $p['canonical_code'];
    $real = isset($fileMap[$p['canonical_file']]) ? $fileMap[$p['canonical_file']] : null;
    $res = null; $lc = null; $state = null; $note = '';

    if (isset($OVERRIDES[$code])) {                                  /* ⓪ مثبَتٌ يدويًّا */
        $res = 'exact'; list($lc, $state, $note) = $OVERRIDES[$code];
    } elseif (isset($live[$code])) {                                       /* ① exact */
        $res = 'exact'; $lc = $code; $state = 'alias';
        $note = 'الرمزُ نفسُه حيٌّ في actions';
    } elseif ($real !== null && isset($moduleByPath[$real])
              && isset($writeActionsByModule[$moduleByPath[$real]])) { /* ② module */
        $cands = $writeActionsByModule[$moduleByPath[$real]];
        if (count($cands) === 1) {
            $res = 'module'; $lc = $cands[0]; $state = 'alias';
            $note = 'فعلُ الكتابةِ الحيُّ الوحيدُ لوحدةِ الشاشة';
        } else {
            $res = 'ambiguous'; $note = 'أفعالُ كتابةٍ عدةٌ للوحدة: ' . implode(' · ', $cands) . ' — مراجعةٌ يدوية';
        }
    }
    if (($res === null || $res === 'ambiguous') && $real !== null) {  /* ②ب معالجٌ حيٌّ يستدعيه المصدر */
        $refs = $referencedWriteActions($real);
        if (count($refs) === 1) {
            $res = 'handler_ref'; $lc = $refs[0]; $state = 'alias';
            $note = 'مصدرُ الشاشةِ يستدعي معالجَ الكتابةِ الحيَّ ' . $refs[0];
        } elseif (count($refs) > 1 && $res === null) {
            $res = 'ambiguous'; $note = 'المصدرُ يستدعي معالجاتِ كتابةٍ عدة: ' . implode(' · ', $refs) . ' — مراجعةٌ يدوية';
        }
    }
    if ($res === null || $res === 'ambiguous') {
        $hf = $real !== null ? $referencedHandlerFile($real) : null;
        if ($real !== null && $selfHandles($real)) {                  /* ③ page */
            $prev = $res; // الغامض يهبط للصفحة مع إبقاء الملاحظة
            $res = 'page'; $lc = 'page:' . $real; $state = 'bound_page';
            $note = ($prev === 'ambiguous' ? $note . ' — ' : '') . 'الشاشةُ تعالج أفعالَها بنفسها (POST ذاتي/حارس)';
        } elseif ($hf !== null) {                                     /* ③ب معالجٌ منفصلٌ حي */
            $res = 'page'; $lc = 'page:' . $hf; $state = 'bound_page';
            $note = 'مصدرُ الشاشةِ يستدعي ملفَّ المعالجِ الحيَّ ' . $hf;
        } elseif ($res !== 'ambiguous') {                             /* ④ declared */
            $res = 'declared'; $state = 'declared_unbuilt';
            $note = $real === null ? 'لا وجهةَ حيةً بعد'
                : 'لا معالجَ مثبَتًا آليًّا — عرضٌ محضٌ أو معالجٌ غيرُ مقتفًى · للمراجعة اليدوية';
        }
    }
    if ($lc !== null && mb_strlen($lc) > 80) { $lc = mb_substr($lc, 0, 80); }
    $cnt[$res === 'ambiguous' ? 'ambiguous' : $res]++;
    if ($state !== null) { $updates[] = array($code, $lc, $state); }
    fputcsv($fh, array($code, $p['screen_title'], $real, $res, $lc, $state, $note));
}
fclose($fh);

$o('══ حملةُ الربط — ' . ($APPLY ? 'APPLY' : 'DRY-RUN') . ' · معلَّق قبلها: ' . count($pending) . ' ══');
$o("① exact (alias): {$cnt['exact']}");
$o("② module الوحيد (alias): {$cnt['module']}");
$o("②ب معالجٌ حيٌّ يستدعيه المصدر (alias): {$cnt['handler_ref']}");
$o("③ معالجُ الصفحة (bound_page): {$cnt['page']}");
$o("④ معلَنُ عدمِ البناء (declared_unbuilt): {$cnt['declared']}");
$o("⚠ غامضٌ بقي غامضًا: {$cnt['ambiguous']}");
$o('الدفتر: ' . $csvPath);

if ($APPLY) {
    $conn->begin_transaction();
    try {
        $st = $conn->prepare("UPDATE nav09_action_map SET live_code = ?, state = ? WHERE canonical_code = ? AND state = 'pending'");
        $n = 0;
        foreach ($updates as $u) {
            $st->bind_param('sss', $u[1], $u[2], $u[0]);
            $st->execute() or throw new RuntimeException($st->error);
            $n += $conn->affected_rows;
        }
        $st->close();
        $conn->commit();
        $o("✔ COMMITTED — حُسم: $n");
    } catch (\Throwable $t) {
        $conn->rollback();
        $o('✘ ROLLED BACK: ' . $t->getMessage());
        exit(1);
    }
    $r = mysqli_query($conn, "SELECT state, COUNT(*) c FROM nav09_action_map GROUP BY state ORDER BY state");
    $parts = array();
    while ($x = mysqli_fetch_assoc($r)) { $parts[] = "{$x['state']}={$x['c']}"; }
    $o('الشاهد: ' . implode(' · ', $parts));
}
