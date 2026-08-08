<?php
/**
 * tools/u12_link_depth_judge.php — أحكامُ عمقِ الربط ⑤ و⑩ لكلِّ فعل
 * ═══════════════════════════════════════════════════════════════════════════
 * المرجع: تصحيحاتُ UXR v1 — «أعمدةُ العمقِ ⑤ الحارس · ⑩ العطالة · ⑫ الاختبار
 * ناقصةٌ في قاموسِ الأفعال». والعمودُ الفارغُ لا يُملأ بالدعوى بل بالقياس:
 * تُقرأ شيفرةُ الشاشةِ الحيةِ ويُسجَّل ما وُجد نصًّا في عمودِ الشاهد.
 *
 * ⑤ الحارس — أيُحرَس الفعلُ قبل تنفيذه؟ شقّان:
 *     · CSRF: مُنفَذٌ مركزيًّا لكلِّ POST/PUT/PATCH/DELETE في config.php عبر
 *       ems_enforce_csrf_protection() — فلا يُطلب من كلِّ شاشةٍ رمزُها. ويُستثنى
 *       مسارا /api/ و/admin/ صراحةً، فهذان يلزمهما رمزُهما في الملف.
 *     · الصلاحية: حارسٌ محلولٌ في الشاشة (check_page_permissions أو can_*
 *       أو ems_action_guard أو OwnershipDomainGuard أو حارسُ دورٍ صريح).
 *     قراءةٌ خالصة (read_only) ⇒ n_a: لا فعلَ يُحرَس.
 *
 * ⑩ العطالة — أيُؤمَن تكرارُ الفعلِ من إحداثِ أثرٍ مضاعف؟
 *     قراءةٌ خالصة ⇒ n_a.
 *     كتابةٌ ⇒ yes متى وُجد مفتاحُ عطالةٍ أو نشرٌ عبر EventPublisher (وهو يحمل
 *     عطالتَه) أو فريدٌ يمنع التكرار — في الشاشةِ أو في خدمةٍ تستدعيها الشاشةُ
 *     مباشرةً (درجةٌ واحدةٌ من العمق، فالأثرُ يقع في الخدمةِ لا في الشاشة).
 *     وإلا no.
 *
 * ⑫ الاختبار (uat_verified) لا يُملأ هنا: شاهدُه محضرُ تجربةِ مستخدمٍ لا شيفرة،
 * فيبقى pending — ولا يُدَّعى ما لم يُقَس.
 *
 * التشغيل: php tools/u12_link_depth_judge.php [--dry]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$ROOT = dirname(__DIR__);
$dry = in_array('--dry', $argv, true);

/* ── الاتصال بالقاعدة الحية ─────────────────────────────────────────────── */
$cfg = array('host' => 'localhost', 'port' => 3307, 'user' => 'root', 'pass' => '', 'db' => 'equipation_manage');
$envF = $ROOT . '/.env';
if (is_file($envF)) {
    foreach (file($envF, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        if ($ln === '' || $ln[0] === '#' || strpos($ln, '=') === false) { continue; }
        list($k, $v) = explode('=', $ln, 2);
        $k = trim($k); $v = trim($v);
        if ($k === 'DB_HOST') { $hp = explode(':', $v); $cfg['host'] = $hp[0]; if (isset($hp[1])) { $cfg['port'] = (int) $hp[1]; } }
        if ($k === 'DB_USER') { $cfg['user'] = $v; }
        if ($k === 'DB_PASS') { $cfg['pass'] = $v; }
        if ($k === 'DB_NAME') { $cfg['db']   = $v; }
    }
}
$db = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['db'], $cfg['port']);
if ($db->connect_errno) { exit('تعذّر الاتصال: ' . $db->connect_error . "\n"); }
$db->set_charset('utf8mb4');

/* ── خريطةُ الملفاتِ الحقيقية ───────────────────────────────────────────── */
$fileMap = array();
if ($r = @$db->query("SELECT canonical_file, real_path FROM nav09_file_map")) {
    while ($x = $r->fetch_assoc()) {
        $fileMap[trim((string) $x['canonical_file'])] = trim((string) $x['real_path']);
    }
}

/** يقرأ ملفَّ الشاشةِ الحيّ (بالخريطةِ أو بالبحثِ عن الاسم) */
function ld_read($ROOT, $fileMap, $canonicalFile)
{
    $cf = trim((string) $canonicalFile);
    if ($cf === '') { return array(null, ''); }
    $cands = array();
    if (isset($fileMap[$cf]) && $fileMap[$cf] !== '') { $cands[] = $fileMap[$cf]; }
    $cands[] = $cf;
    foreach ($cands as $c) {
        $p = $ROOT . '/' . ltrim(str_replace('\\', '/', $c), '/');
        if (is_file($p)) { return array((string) file_get_contents($p), $c); }
    }
    /* البحثُ بالاسمِ المجرَّد في مجلداتِ الشاشات */
    $base = basename($cf);
    foreach (glob($ROOT . '/*/' . $base) as $hit) {
        return array((string) file_get_contents($hit), str_replace('\\', '/', substr($hit, strlen($ROOT) + 1)));
    }
    return array(null, '');
}

/** يجمع شيفرةَ الخدماتِ التي تستدعيها الشاشةُ مباشرةً — درجةٌ واحدةٌ من العمق */
function ld_services($ROOT, $src)
{
    $out = '';
    if (!preg_match_all('~(?:app/Services/[A-Za-z0-9_/]+\.php|App\\\\Services\\\\[A-Za-z0-9_\\\\]+)~', $src, $m)) {
        return $out;
    }
    $seen = array();
    foreach ($m[0] as $ref) {
        $rel = (strpos($ref, 'app/Services/') === 0)
            ? $ref
            : 'app/' . str_replace('\\', '/', substr($ref, 4)) . '.php';
        if (isset($seen[$rel])) { continue; }
        $seen[$rel] = true;
        $p = $ROOT . '/' . $rel;
        if (is_file($p)) { $out .= "\n" . (string) file_get_contents($p); }
    }
    return $out;
}

/* الشاهدُ المركزيُّ لـCSRF — يُقرأ مرةً ويُنسب لكلِّ شاشةٍ خارجَ الاستثناءين */
$csrfCentral = false;
$cfgSrc = (string) @file_get_contents($ROOT . '/config.php');
$secSrc = (string) @file_get_contents($ROOT . '/includes/security.php');
if (strpos($cfgSrc, 'ems_enforce_csrf_protection()') !== false
    && strpos($secSrc, 'function ems_enforce_csrf_protection') !== false) {
    $csrfCentral = true;
}

$rows = array();
$q = $db->query("SELECT canonical_code, canonical_file, live_code, state, write_class FROM nav09_action_map ORDER BY canonical_code");
while ($x = $q->fetch_assoc()) { $rows[] = $x; }

$stat = array('guard' => array(), 'idem' => array());
$updates = array();
$missing = 0;

foreach ($rows as $row) {
    $code = $row['canonical_code'];
    $wc = (string) $row['write_class'];
    list($src, $realPath) = ld_read($ROOT, $fileMap, $row['canonical_file']);

    /* ── ⑤ الحارس ─────────────────────────────────────────────────────── */
    if ($wc === 'read_only') {
        $g = 'n_a'; $ge = 'قراءةٌ خالصة — لا فعلَ يُحرَس (write_class=read_only)';
    } elseif ($src === null) {
        $g = 'no'; $ge = 'لم يُعثر على ملفِّ الشاشةِ الحيّ — لا شاهدَ يُقرأ';
        $missing++;
    } else {
        $hasPerm = (strpos($src, 'check_page_permissions') !== false)
            || preg_match('~\$can_(add|edit|delete)\b~', $src) === 1
            || strpos($src, 'ems_action_guard') !== false
            || strpos($src, 'action_guard') !== false
            || strpos($src, 'OwnershipDomainGuard') !== false
            || strpos($src, 'ems_gov_flash_redirect') !== false && preg_match('~GOV-PERM-403~', $src) === 1
            || preg_match("~\\\$(is_super|is_super_admin|current_role|ctx\\['role'\\])~", $src) === 1;
        /* الاستثناءان اللذان يعلنهما ems_enforce_csrf_protection نفسُها */
        $exempt = (strpos($realPath, 'admin/') === 0) || (strpos($realPath, 'api/') === 0);
        $ownCsrf = (stripos($src, 'csrf') !== false);
        $csrfOk = $exempt ? $ownCsrf : ($csrfCentral || $ownCsrf);

        if ($hasPerm && $csrfOk) {
            $g = 'yes';
            $ge = 'حارسُ صلاحيةٍ محلولٌ في ' . $realPath . ' + CSRF '
                . ($exempt ? 'خاصٌّ بالملف (مسارٌ مستثنًى مركزيًّا)'
                           : 'مُنفَذٌ مركزيًّا: config.php ⇐ ems_enforce_csrf_protection()');
        } elseif (!$hasPerm && $csrfOk) {
            $g = 'no'; $ge = 'CSRF مُنفَذٌ وحارسُ الصلاحيةِ غيرُ مقروءٍ في ' . $realPath;
        } elseif ($hasPerm) {
            $g = 'no'; $ge = 'حارسُ صلاحيةٍ موجودٌ وCSRF غيرُ منفَذٍ (مسارٌ مستثنًى بلا رمزِه) — ' . $realPath;
        } else {
            $g = 'no'; $ge = 'لا حارسَ صلاحيةٍ ولا CSRF — ' . $realPath;
        }
    }

    /* ── ⑩ العطالة ───────────────────────────────────────────────────── */
    if ($wc === 'read_only') {
        $d = 'n_a'; $de = 'قراءةٌ خالصة — لا أثرَ يتضاعف بالتكرار';
    } elseif ($src === null) {
        $d = 'no'; $de = 'لم يُعثر على ملفِّ الشاشةِ الحيّ — لا شاهدَ يُقرأ';
    } else {
        $deep = $src . ld_services($ROOT, $src);
        $where = ($deep === $src) ? $realPath : ($realPath . ' (+ خدماتُها)');
        $hasKey  = (stripos($deep, 'idempotency_key') !== false) || (stripos($deep, 'idempotencyKey') !== false);
        $hasPub  = (strpos($deep, 'publishFact') !== false) || (strpos($deep, 'EventPublisher') !== false);
        $hasUniq = (stripos($deep, 'ON DUPLICATE KEY') !== false) || (stripos($deep, 'INSERT IGNORE') !== false);
        if ($hasKey)       { $d = 'yes'; $de = 'مفتاحُ عطالةٍ صريحٌ في مسارِ الكتابة — ' . $where; }
        elseif ($hasPub)   { $d = 'yes'; $de = 'النشرُ عبر EventPublisher وهو يحمل عطالتَه — ' . $where; }
        elseif ($hasUniq)  { $d = 'yes'; $de = 'فريدٌ في القاعدةِ يمنع التكرار (ON DUPLICATE / INSERT IGNORE) — ' . $where; }
        else               { $d = 'no';  $de = 'لا مفتاحَ عطالةٍ ولا نشرَ حقائقَ ولا فريدَ مانع — ' . $where; }
    }

    $stat['guard'][$g] = (isset($stat['guard'][$g]) ? $stat['guard'][$g] : 0) + 1;
    $stat['idem'][$d]  = (isset($stat['idem'][$d])  ? $stat['idem'][$d]  : 0) + 1;
    $updates[] = array($code, $g, mb_substr($ge, 0, 185), $d, mb_substr($de, 0, 185));
}

if (!$dry) {
    $st = $db->prepare("UPDATE nav09_action_map
        SET guard_verified = ?, guard_evidence = ?, idempotency_verified = ?, idempotency_evidence = ?,
            updated_at = NOW()
        WHERE canonical_code = ?");
    $ok = 0;
    foreach ($updates as $u) {
        $st->bind_param('sssss', $u[1], $u[2], $u[3], $u[4], $u[0]);
        if ($st->execute()) { $ok += $db->affected_rows > 0 ? 1 : 0; }
    }
    $st->close();
    echo "صفوفٌ حُدّثت: {$ok}\n";
}

echo 'أحكامُ عمقِ الربط ⑤ و⑩ — قياسًا لا دعوى' . ($dry ? '  [تشغيلٌ جافّ]' : '') . "\n";
echo str_repeat('═', 60), "\n";
echo 'أفعالٌ في القاموس: ' . count($rows) . "\n";
echo "ملفاتٌ لم يُعثر عليها: {$missing}\n\n";
echo "⑤ الحارس:\n";
foreach ($stat['guard'] as $k => $v) { echo '  ' . str_pad($k, 8) . $v . "\n"; }
echo "\n⑩ العطالة:\n";
foreach ($stat['idem'] as $k => $v) { echo '  ' . str_pad($k, 8) . $v . "\n"; }
echo "\n⑫ الاختبار: يبقى pending لكلِّها — شاهدُه محضرُ تجربةٍ لا شيفرة\n";
exit(0);
