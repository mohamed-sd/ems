<?php
/**
 * tests/dept_suite/engine.php — محرّكُ فحصِ الإداراتِ العامّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا هذا الملف**: أن تُفحَص إدارةٌ كاملةٌ — عرضًا وإضافةً وتعديلًا وحذفًا —
 *   بأمرٍ واحد، وأن يُقارَن كلُّ تشغيلٍ بخطِّ أساسٍ محفوظٍ فيُقال بالاسم: **ما الذي
 *   انكسر منذ آخرِ مرّة**. فلا يُعاد الفحصُ اليدويُّ بعد كلِّ تعديل.
 *
 * ◆ **المحرّكُ لا يعرف إدارةً بعينها**. كلُّ ما يخصُّ إدارةً يسكن مانيفستَها
 *   (`manifest_<dept>.php`) بياناتٍ لا كودًا — فتعميمُه على إدارةٍ أخرى كتابةُ
 *   مانيفستٍ جديدٍ وحدَه، بلا سطرِ كودٍ واحدٍ هنا.
 *
 * ◆ **ثلاث قواعدَ تحكم كلَّ حكمٍ يُصدره هذا المحرّك**:
 *
 *   ① **الحكمُ من القاعدةِ لا من الشاشة.** رسالةُ «تم بنجاح ✅» قد تُطبَع ولا
 *      يُكتَب صفّ. فكلُّ إضافةٍ وتعديلٍ وحذفٍ يُتحقَّق منه باستعلامٍ على الجدول.
 *
 *   ② **«غيرُ موجود» ليس «فاشلًا».** سطحٌ بلا حذفٍ يُسجَّل `NA` لا `FAIL`.
 *      خلطُهما يجعل التقريرَ أحمرَ كذبًا فيُهمَل، وإهمالُه يُبطل الأداةَ كلَّها.
 *
 *   ③ **الوسمُ ثابتُ البادئة.** لو حمل كلُّ تشغيلٍ وسمًا فريدًا وحدَه، لبقيت
 *      بقايا أيِّ تشغيلٍ انهار في منتصفه **بلا كانسٍ يعرفها**. فالبادئةُ
 *      `EMSQA` ثابتةٌ ويُكنَس بها — فتُزال بقايا كلِّ تشغيلٍ سابقٍ أيضًا.
 *
 * ◆ **حالاتُ النتيجة**: PASS ناجح · FAIL فاشل · NA العمليةُ غيرُ موجودةٍ في
 *   السطح · DENY رُدَّ بحارسِ صلاحيةٍ · SKIP تعذّرت التهيئةُ (بسببٍ مذكور).
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

define('DS_MARK_PREFIX', 'EMSQA');
define('DS_ROOT', dirname(dirname(__DIR__)));

require_once DS_ROOT . '/includes/env.php';
require_once DS_ROOT . '/tests/_http_flash.php';

// ══════════════════════════════════════════════════════════════════════════
// ① السياق: قاعدةٌ وجرّةُ ارتباطاتٍ ووسمُ التشغيلة
// ══════════════════════════════════════════════════════════════════════════

/** يبني سياقَ التشغيل: اتصالُ القاعدةِ · العنوانُ الأساس · الجرّة · الوسم. */
function ds_ctx(array $opt)
{
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
    if ($db->connect_errno) {
        fwrite(STDERR, "⛔ تعذّر الاتصالُ بالقاعدة: " . $db->connect_error . "\n");
        fwrite(STDERR, "   تأكّد أن MySQL يعمل (WAMP أخضر).\n");
        exit(2);
    }
    $db->set_charset('utf8mb4');

    $jar = tempnam(sys_get_temp_dir(), 'dsq');
    @unlink($jar);

    return array(
        'db'      => $db,
        'base'    => isset($opt['base']) ? rtrim($opt['base'], '/') : 'http://localhost/ems',
        'jar'     => $jar,
        'company' => isset($opt['company']) ? (int) $opt['company'] : 4,
        'run'     => isset($opt['run']) ? $opt['run'] : substr(str_pad((string) getmypid(), 5, '0', STR_PAD_LEFT), -5),
        'mark'    => '',   // يُملأ بعد قليل
        'fix'     => array(),
        'quick'   => !empty($opt['quick']),
        'verbose' => !empty($opt['verbose']),
    );
}

/** الوسمُ الكاملُ لهذه التشغيلة — بادئةٌ ثابتةٌ ثم مُعرِّفُ التشغيلة. */
function ds_mark(array $ctx) { return DS_MARK_PREFIX . '-' . $ctx['run']; }

// ══════════════════════════════════════════════════════════════════════════
// ② طبقةُ HTTP: طلبٌ · دخولٌ · التقاطُ رمزِ CSRF
// ══════════════════════════════════════════════════════════════════════════

/** طلبٌ واحدٌ يحفظ الارتباطاتِ ولا يتبع التحويل — لنقرأ `Location` بأنفسنا. */
function ds_req($url, array $ctx, $post = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_COOKIEJAR      => $ctx['jar'],
        CURLOPT_COOKIEFILE     => $ctx['jar'],
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_USERAGENT      => 'EMS-DeptSuite/1.0',
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $hs   = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) { return array(0, '', '', $err); }
    return array($code, substr($raw, 0, $hs), substr($raw, $hs), '');
}

/** رمزُ CSRF من صفحةٍ مُصيَّرة — يُلتقط من النموذجِ الحقيقيِّ لا يُخترع. */
function ds_csrf($html)
{
    return preg_match('~name="csrf_token"\s+value="([^"]+)"~', (string) $html, $m) ? $m[1] : '';
}

/** ترويسةُ التحويل. */
function ds_location($headers)
{
    return preg_match('~^Location:\s*(.+)$~mi', (string) $headers, $m) ? trim($m[1]) : '';
}

/** دخولٌ حقيقيٌّ بالمستخدمِ وكلمتِه — بلا مسٍّ لأيِّ hash في القاعدة. */
function ds_login(array $ctx, $user, $pass)
{
    @unlink($ctx['jar']);
    list($c, $h, $b) = ds_req($ctx['base'] . '/login.php', $ctx);
    if ($c !== 200) { return array(false, "صفحةُ الدخولِ ردَّت HTTP {$c} — هل Apache يعمل؟"); }
    $tok = ds_csrf($b);
    if ($tok === '') { return array(false, 'لم يُعثر على رمزِ CSRF في صفحةِ الدخول'); }

    list($c2, $h2) = ds_req($ctx['base'] . '/login.php', $ctx,
        array('username' => $user, 'password' => $pass, 'csrf_token' => $tok));
    $loc = ds_location($h2);
    if ($c2 !== 302 || strpos($loc, 'login') !== false) {
        return array(false, "رُفض الدخولُ للمستخدم «{$user}» (HTTP {$c2})");
    }
    return array(true, $loc);
}

// ══════════════════════════════════════════════════════════════════════════
// ③ البدائل: {MARK} · {CSRF} · {CLIENT} … تُحَلُّ وقتَ التنفيذ
// ══════════════════════════════════════════════════════════════════════════

/** يستبدل البدائلَ في قيمةٍ واحدةٍ أو مصفوفةِ حقول. */
function ds_subst($val, array $ctx, array $extra = array())
{
    if (is_array($val)) {
        $out = array();
        foreach ($val as $k => $v) { $out[$k] = ds_subst($v, $ctx, $extra); }
        return $out;
    }
    $map = array('{MARK}' => ds_mark($ctx));
    foreach ($ctx['fix'] as $k => $v) { $map['{' . $k . '}'] = (string) $v; }
    foreach ($extra as $k => $v)      { $map['{' . $k . '}'] = (string) $v; }
    $map['{TODAY}']    = date('Y-m-d');
    $map['{TOMORROW}'] = date('Y-m-d', strtotime('+1 day'));
    $map['{NEXTYEAR}'] = date('Y-m-d', strtotime('+1 year'));
    return strtr((string) $val, $map);
}

// ══════════════════════════════════════════════════════════════════════════
// ④ الأساساتُ المبذورة: عميلٌ ← مشروعٌ ← عقدٌ ← فرصةٌ ← عرض
//    تُبذر مرّةً لتخدم الأسطحَ الابنة، وتُكنَس كاملةً في النهاية.
// ══════════════════════════════════════════════════════════════════════════

/**
 * ◆ **الترتيبُ ليس تفصيلًا**: القاعدةُ تفرض بمُطلِقٍ سلسلةَ «عميل ⇐ فرصة ⇐ عرض
 *   ⇐ عقد» — فعقدٌ بلا `quotation_id` **يُرفَض**. ولو بُذر بترتيبٍ خاطئٍ لعاد
 *   `insert_id` بمعرِّفِ الإدخالِ الناجحِ السابق، فيُقاس ستةُ أسطحٍ سليمةٍ على
 *   عقدٍ لا وجودَ له وتُقرأ كلُّها فاشلةً — **والعطبُ في العُدّةِ لا في المنتَج**.
 *
 * ◆ ولذلك: **كلُّ إدخالٍ هنا يُفحص خطؤه ويُوقِف التشغيلَ إن أخفق.** بذرٌ صامتُ
 *   الفشلِ يُنتج تقريرًا كاذبًا بالكامل، وتقريرٌ كاذبٌ أسوأُ من لا تقرير.
 */
function ds_fixtures_build(array &$ctx)
{
    $db = $ctx['db']; $co = $ctx['company']; $M = ds_mark($ctx);
    $f  = array();

    $seed = function ($sql, $what) use ($db) {
        if (!$db->query($sql)) {
            fwrite(STDERR, "\n⛔ تعذّر بذرُ «{$what}»: " . $db->error . "\n");
            fwrite(STDERR, "   لا يصحُّ إكمالُ الفحصِ بأساسٍ ناقص — يُقرأ السليمُ فاشلًا.\n");
            exit(2);
        }
        return (int) $db->insert_id;
    };

    $f['CLIENT'] = $seed(
        "INSERT INTO clients (company_id, client_code, client_name, status, created_at)
         VALUES ({$co}, '{$M}-CLI', 'عميلُ فحصٍ آليٍّ {$M}', 'نشط', NOW())", 'عميل');

    // `project_code` يُملأ بالوسمِ عمدًا: هو مقبضُ الكنسِ لشجرةِ الأساساتِ كلِّها.
    $f['PROJECT'] = $seed(
        "INSERT INTO project (company_id, client_id, project_code, name, client, location, total)
         VALUES ({$co}, {$f['CLIENT']}, '{$M}-P0', 'مشروعُ فحصٍ {$M}', 'عميلُ فحصٍ {$M}', 'موقعُ فحص', '0')", 'مشروع');

    $f['OPPORTUNITY'] = $seed(
        "INSERT INTO opportunities (company_id, opp_code, title, client_id, stage, currency, created_at)
         VALUES ({$co}, '{$M}-OPP', 'فرصةُ فحصٍ {$M}', {$f['CLIENT']}, 'جديدة', 'SDG', NOW())", 'فرصة');

    $f['QUOTATION'] = $seed(
        "INSERT INTO quotations (company_id, quotation_code, client_id, opportunity_id, currency, state, created_at)
         VALUES ({$co}, '{$M}-QUO', {$f['CLIENT']}, {$f['OPPORTUNITY']}, 'SDG', 'مقبول', NOW())", 'عرض سعر');

    // العقدُ آخرًا وبمرجعِ عرضِه — وإلّا ردَّه مُطلِقُ السلسلة.
    $f['CONTRACT'] = $seed(
        "INSERT INTO contracts (company_id, project_id, quotation_id, contract_signing_date, contract_status, created_at)
         VALUES ({$co}, {$f['PROJECT']}, {$f['QUOTATION']}, CURDATE(), 'مسودة', NOW())", 'عقد');

    $f['SITE'] = $seed(
        "INSERT INTO sites (company_id, project_id, name)
         VALUES ({$co}, {$f['PROJECT']}, 'موقعُ فحصٍ {$M}')", 'موقع');

    // مواضعُ تُملأ لاحقًا بتصديرِ الأسطح (`export`) — تُهيَّأ بصفرٍ لئلّا يتسرَّب
    // نصُّ البديلِ نفسُه إلى عنوانِ الطلب.
    $f['LINE'] = 0;

    $ctx['fix'] = $f;
    return $f;
}

/**
 * كنسُ كلِّ ما يحمل البادئةَ الثابتة — من هذا التشغيلِ **ومن أيِّ تشغيلٍ سابقٍ
 * انهار**. والترتيبُ من الابنِ إلى الأب.
 */
/** هل الجدولُ والعمودُ موجودان فعلًا؟ (فحصُ مخطَّطٍ قبلَ أيِّ حذف) */
function ds_has_col(mysqli $db, $t, $col)
{
    $r = @$db->query("SHOW TABLES LIKE '{$t}'");
    if (!$r || !$r->num_rows) { return false; }
    $c = @$db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$col}'");
    return $c && $c->num_rows > 0;
}

/** كنسُ الصفوفِ الموسومةِ نصًّا في جداولِ المانيفست. */
function ds_sweep(array $ctx, array $tables)
{
    $db = $ctx['db']; $P = DS_MARK_PREFIX; $removed = 0;
    foreach ($tables as $t => $col) {
        $t   = preg_replace('/[^a-z0-9_]/i', '', $t);
        $col = preg_replace('/[^a-z0-9_]/i', '', $col);
        if ($t === '' || $col === '' || !ds_has_col($db, $t, $col)) { continue; }
        @$db->query("DELETE FROM `{$t}` WHERE `{$col}` LIKE '{$P}%'");
        if ($db->affected_rows > 0) { $removed += $db->affected_rows; }
    }
    return $removed;
}

/**
 * الكنسُ الكامل: الأبناءُ بمعرِّفِ الأساسِ المبذور، ثم الموسومُ نصًّا، ثم شجرةُ
 * الأساسات (عرضٌ · فرصةٌ · عقدٌ · موقعٌ · مشروعٌ · عميل) بترتيبٍ عكسيّ.
 * ويُكنَس بالبادئةِ الثابتةِ فتُزال بقايا **أيِّ** تشغيلٍ سابقٍ انهار، لا هذه وحدَها.
 */
function ds_sweep_all(array $ctx, array $manifest)
{
    $db = $ctx['db']; $P = DS_MARK_PREFIX; $n = 0;

    // ① الأبناءُ المرتبطون بأساسِ هذه التشغيلة (إن بُذر)
    foreach ((array) (isset($manifest['sweep_by']) ? $manifest['sweep_by'] : array()) as $sb) {
        if (empty($ctx['fix'][$sb['fix']])) { continue; }
        $t   = preg_replace('/[^a-z0-9_]/i', '', $sb['table']);
        $col = preg_replace('/[^a-z0-9_]/i', '', $sb['col']);
        $id  = (int) $ctx['fix'][$sb['fix']];
        if (!ds_has_col($db, $t, $col)) { continue; }
        @$db->query("DELETE FROM `{$t}` WHERE `{$col}` = {$id}");
        if ($db->affected_rows > 0) { $n += $db->affected_rows; }
    }

    // ② الموسومُ نصًّا في جداولِ المانيفست
    $n += ds_sweep($ctx, isset($manifest['sweep']) ? $manifest['sweep'] : array());

    // ③ شجرةُ الأساسات — الابنُ قبلَ الأب
    $ids = array();
    $r = @$db->query("SELECT id FROM project WHERE project_code LIKE '{$P}%' OR name LIKE '%{$P}%'");
    if ($r) { while ($x = $r->fetch_row()) { $ids[] = (int) $x[0]; } }
    if (!empty($ids)) {
        $in = implode(',', $ids);
        foreach (array('contract_obligations' => 'client_contract_id',
                       'contract_guarantees'  => 'contract_id',
                       'client_contract_lines' => 'contract_id') as $ct => $cc) {
            if (!ds_has_col($db, $ct, $cc)) { continue; }
            @$db->query("DELETE FROM `{$ct}` WHERE `{$cc}` IN (SELECT id FROM contracts WHERE project_id IN ({$in}))");
        }
        @$db->query("DELETE FROM contracts WHERE project_id IN ({$in})");
        @$db->query("DELETE FROM sites     WHERE project_id IN ({$in})");
        @$db->query("DELETE FROM project   WHERE id IN ({$in})");
    }
    foreach (array('quotations' => 'quotation_code', 'opportunities' => 'opp_code',
                   'clients' => 'client_code') as $t => $c) {
        if (!ds_has_col($db, $t, $c)) { continue; }
        @$db->query("DELETE FROM `{$t}` WHERE `{$c}` LIKE '{$P}%'");
        if ($db->affected_rows > 0) { $n += $db->affected_rows; }
    }
    return $n;
}

/**
 * ◆ **الوضعُ السريعُ لا يكتب — لكنه يحتاج أبًا حقيقيًّا.** بلا ذلك تُفتح الأسطحُ
 *   التابعةُ بمعرِّفٍ صفريٍّ فتُردُّ، فيُقرأ عشرةُ أسطحٍ سليمةٍ فاشلةً. فيُستعار
 *   هنا **صفٌّ قائمٌ بالقراءةِ وحدَها** — ولا يُنشأ ولا يُعدَّل شيء.
 */
function ds_fixtures_borrow(array &$ctx)
{
    $db = $ctx['db']; $co = $ctx['company'];
    $one = function ($sql) use ($db) {
        $r = @$db->query($sql);
        return ($r && $r->num_rows) ? (int) $r->fetch_row()[0] : 0;
    };
    $f = array(
        'CONTRACT'    => $one("SELECT id FROM contracts     WHERE company_id={$co} ORDER BY id DESC LIMIT 1"),
        'QUOTATION'   => $one("SELECT id FROM quotations    WHERE company_id={$co} ORDER BY id DESC LIMIT 1"),
        'OPPORTUNITY' => $one("SELECT id FROM opportunities WHERE company_id={$co} ORDER BY id DESC LIMIT 1"),
        'CLIENT'      => $one("SELECT id FROM clients       WHERE company_id={$co} AND is_deleted=0 ORDER BY id DESC LIMIT 1"),
        'SITE'        => $one("SELECT id FROM sites         WHERE company_id={$co} ORDER BY id DESC LIMIT 1"),
    );
    $f['PROJECT'] = $one("SELECT project_id FROM contracts WHERE id={$f['CONTRACT']}");
    $f['LINE']    = $one("SELECT id FROM client_contract_lines WHERE contract_id={$f['CONTRACT']} ORDER BY id DESC LIMIT 1");
    if ($f['LINE'] === 0) { $f['LINE'] = $one("SELECT id FROM client_contract_lines ORDER BY id DESC LIMIT 1"); }
    $ctx['fix'] = $f;
    return $f;
}

/** كم صفًّا يحمل البادئةَ الآن — لفحصِ البقايا قبلَ التشغيلِ وبعده. */
function ds_residue(array $ctx, array $tables)
{
    $db = $ctx['db']; $P = DS_MARK_PREFIX; $n = 0;
    foreach ($tables as $t => $col) {
        $t = preg_replace('/[^a-z0-9_]/i', '', $t);
        $col = preg_replace('/[^a-z0-9_]/i', '', $col);
        if ($t === '' || $col === '') { continue; }
        $chk = @$db->query("SHOW TABLES LIKE '{$t}'");
        if (!$chk || !$chk->num_rows) { continue; }
        $cc = @$db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$col}'");
        if (!$cc || !$cc->num_rows) { continue; }
        $r = @$db->query("SELECT COUNT(*) FROM `{$t}` WHERE `{$col}` LIKE '{$P}%'");
        if ($r) { $n += (int) $r->fetch_row()[0]; }
    }
    foreach (array('clients' => 'client_code', 'quotations' => 'quotation_code',
                   'opportunities' => 'opp_code', 'project' => 'project_code') as $t => $c) {
        if (isset($tables[$t])) { continue; }   // لا يُعَدُّ مرّتين
        $r = @$db->query("SELECT COUNT(*) FROM `{$t}` WHERE `{$c}` LIKE '{$P}%'");
        if ($r) { $n += (int) $r->fetch_row()[0]; }
    }
    return $n;
}

// ══════════════════════════════════════════════════════════════════════════
// ⑤ التحقّقُ من القاعدة — قلبُ الأمانةِ في هذا المحرّك
// ══════════════════════════════════════════════════════════════════════════

/**
 * يعدُّ الصفوفَ المطابقةَ لشرطِ التحقّق. `verify` = array(table, where).
 * تُستبدل البدائلُ في `where` قبلَ التنفيذ.
 */
function ds_verify_count(array $ctx, array $verify, array $extra = array())
{
    $t = preg_replace('/[^a-z0-9_]/i', '', $verify['table']);
    $w = ds_subst($verify['where'], $ctx, $extra);
    $r = @$ctx['db']->query("SELECT COUNT(*) FROM `{$t}` WHERE {$w}");
    if (!$r) { return array(-1, $ctx['db']->error); }
    return array((int) $r->fetch_row()[0], '');
}

/** أوّلُ معرِّفٍ مطابقٍ لشرطِ التحقّق (لاستعماله في التعديلِ والحذف). */
function ds_verify_id(array $ctx, array $verify, array $extra = array())
{
    $t  = preg_replace('/[^a-z0-9_]/i', '', $verify['table']);
    $id = isset($verify['id_col']) ? preg_replace('/[^a-z0-9_]/i', '', $verify['id_col']) : 'id';
    $w  = ds_subst($verify['where'], $ctx, $extra);
    $r  = @$ctx['db']->query("SELECT `{$id}` FROM `{$t}` WHERE {$w} ORDER BY `{$id}` DESC LIMIT 1");
    if (!$r || !$r->num_rows) { return 0; }
    return (int) $r->fetch_row()[0];
}

// ══════════════════════════════════════════════════════════════════════════
// ⑥ فحصُ سطحٍ واحد: عرض → إضافة → تعديل → حذف → حارس
// ══════════════════════════════════════════════════════════════════════════

function ds_res($op, $status, $note) { return array('op' => $op, 'status' => $status, 'note' => $note); }

/**
 * ◆ `$ctx` **بالإشارة**: سطحٌ قد يُصدِّر معرِّفَ ما أنشأه (`export`) ليستعمله سطحٌ
 *   تالٍ — فبندُ العقدِ يُنشأ ثم تُفتح عليه «الخطةُ الشهرية» في حالتِها ذاتِ المعنى.
 *   وبلا ذلك يُفتح السطحُ التالي فارغًا فلا يُقاس منه شيء.
 */
function ds_run_screen(array $spec, array &$ctx)
{
    $out   = array();
    $route = $spec['route'];
    $url   = $ctx['base'] . '/' . $route;

    // ── ① العرض ───────────────────────────────────────────────────────────
    $params = isset($spec['view']['params']) ? ds_subst($spec['view']['params'], $ctx) : '';
    $vurl   = $url . ($params !== '' ? (strpos($params, '?') === 0 ? $params : '?' . $params) : '');
    list($c, $h, $body, $cerr) = ds_req($vurl, $ctx);

    $loc   = ds_location($h);
    $fatal = (stripos($body, 'Fatal error') !== false || stripos($body, 'Parse error') !== false);
    $csrf  = ds_csrf($body);

    if ($cerr !== '') {
        $out[] = ds_res('VIEW', 'FAIL', "تعذّر الاتصال: {$cerr}");
        return $out;
    } elseif ($fatal) {
        preg_match('~(Fatal error:.{0,140})~s', strip_tags($body), $fm);
        $out[] = ds_res('VIEW', 'FAIL', 'خطأٌ قاتل — ' . trim(preg_replace('/\s+/', ' ', isset($fm[1]) ? $fm[1] : '')));
    } elseif ($c === 302 && strpos($loc, 'login') !== false) {
        $out[] = ds_res('VIEW', 'FAIL', 'طُرد إلى صفحةِ الدخول — الجلسةُ لا تصمد');
    } elseif ($c === 302) {
        $out[] = ds_res('VIEW', 'DENY', 'رُدَّ بحارسٍ إلى ' . basename((string) parse_url($loc, PHP_URL_PATH)));
    } elseif ($c !== 200) {
        $out[] = ds_res('VIEW', 'FAIL', "HTTP {$c}");
    } else {
        $miss = array();
        foreach ((array) (isset($spec['view']['must_contain']) ? $spec['view']['must_contain'] : array()) as $needle) {
            if (mb_strpos($body, ds_subst($needle, $ctx)) === false) { $miss[] = $needle; }
        }
        if (!empty($miss)) {
            $out[] = ds_res('VIEW', 'FAIL', 'صُيِّرت 200 لكن غابت علاماتُها: ' . implode(' · ', $miss));
        } else {
            $out[] = ds_res('VIEW', 'PASS', 'HTTP 200 · ' . number_format(strlen($body) / 1024, 0) . ' ك.ب');
        }
    }

    // العرضُ إن لم ينجح فلا معنى لما بعده على هذا السطح
    if ($out[0]['status'] !== 'PASS') {
        foreach (array('ADD', 'EDIT', 'DELETE', 'GUARD') as $op) {
            if (isset($spec['crud'][strtolower($op)]) && $spec['crud'][strtolower($op)] !== null) {
                $out[] = ds_res($op, 'SKIP', 'لم يُصيَّر العرضُ فلم تُجرَّب');
            } else {
                $out[] = ds_res($op, 'NA', 'لا وجودَ لهذه العمليةِ في السطح');
            }
        }
        return $out;
    }

    // لا كتابةَ في الوضعِ السريع
    if ($ctx['quick'] || empty($spec['crud'])) {
        foreach (array('ADD', 'EDIT', 'DELETE', 'GUARD') as $op) {
            $out[] = ds_res($op, $ctx['quick'] && !empty($spec['crud']) ? 'SKIP' : 'NA',
                $ctx['quick'] && !empty($spec['crud']) ? 'الوضعُ السريع — بلا كتابة' : 'سطحُ قراءةٍ لا كتابةَ فيه');
        }
        return $out;
    }

    $crud   = $spec['crud'];
    $newId  = 0;
    $extra  = array('CSRF' => $csrf);

    /* ◆ **الإرسالُ إلى عنوانِ العرضِ نفسِه لا إلى المسارِ المجرَّد**: نموذجُ
         `action=""` يُرسل إلى العنوانِ الحاليِّ **بمُعامَلاته**. فسطحٌ يقرأ أباه
         من `?client=` يستقبل صفرًا لو أُرسل إلى المسارِ عاريًا، فيُكتب الصفُّ
         بمرجعٍ صفريٍّ أو لا يُكتب — ويُقرأ سطحٌ سليمٌ فاشلًا. */
    $purl = $vurl;

    // ── ② الإضافة ─────────────────────────────────────────────────────────
    if (empty($crud['add'])) {
        $out[] = ds_res('ADD', 'NA', 'لا إضافةَ في هذا السطح');
    } else {
        $vf  = $crud['add']['verify'];
        list($before, $e0) = ds_verify_count($ctx, $vf, $extra);
        $post = ds_subst($crud['add']['post'], $ctx, $extra);
        if ($csrf !== '' && !isset($post['csrf_token'])) { $post['csrf_token'] = $csrf; }
        list($ac, $ah, $ab) = ds_req($purl, $ctx, $post);
        list($after, $e1) = ds_verify_count($ctx, $vf, $extra);

        if ($before < 0 || $after < 0) {
            $out[] = ds_res('ADD', 'FAIL', 'تعذّر التحقّقُ من القاعدة: ' . ($e0 ?: $e1));
        } elseif ($after > $before) {
            $newId = ds_verify_id($ctx, $vf, $extra);
            $out[] = ds_res('ADD', 'PASS', "صفٌّ أُنشئ فعلًا في `{$vf['table']}` (id={$newId})");
        } else {
            $msg = ds_screen_msg($ah, $ctx, $route);
            $out[] = ds_res('ADD', 'FAIL', 'لم يُكتب صفٌّ في `' . $vf['table'] . '`'
                . ($msg !== '' ? ' — قالت الشاشة: «' . mb_substr($msg, 0, 90) . '»' : " (HTTP {$ac})"));
        }
    }

    $extra['ID'] = $newId;
    // تصديرُ المعرِّفِ لمن بعده (إن أعلنه المانيفست)
    if (!empty($crud['export']) && $newId > 0) { $ctx['fix'][$crud['export']] = $newId; }

    // ── ③ التعديل ─────────────────────────────────────────────────────────
    if (empty($crud['edit'])) {
        $out[] = ds_res('EDIT', 'NA', 'لا تعديلَ في هذا السطح');
    } elseif ($newId <= 0) {
        $out[] = ds_res('EDIT', 'SKIP', 'لم تنجح الإضافةُ فلا صفَّ يُعدَّل');
    } else {
        $vf   = $crud['edit']['verify'];
        $post = ds_subst($crud['edit']['post'], $ctx, $extra);
        if ($csrf !== '' && !isset($post['csrf_token'])) { $post['csrf_token'] = $csrf; }
        list($ec, $eh, $eb) = ds_req($purl, $ctx, $post);
        list($cnt, $err) = ds_verify_count($ctx, $vf, $extra);
        if ($cnt > 0) {
            $out[] = ds_res('EDIT', 'PASS', 'القيمةُ تغيّرت فعلًا في القاعدة');
        } else {
            $msg = ds_screen_msg($eh, $ctx, $route);
            $out[] = ds_res('EDIT', 'FAIL', 'القيمةُ لم تتغيّر في القاعدة'
                . ($msg !== '' ? ' — قالت الشاشة: «' . mb_substr($msg, 0, 90) . '»' : " (HTTP {$ec})"));
        }
    }

    // ── ④ الحذف ───────────────────────────────────────────────────────────
    if (empty($crud['delete'])) {
        $out[] = ds_res('DELETE', 'NA', 'لا حذفَ في هذا السطح');
    } elseif ($newId <= 0) {
        $out[] = ds_res('DELETE', 'SKIP', 'لم تنجح الإضافةُ فلا صفَّ يُحذف');
    } else {
        $d = $crud['delete'];
        if (isset($d['get'])) {
            $q = ds_subst($d['get'], $ctx, $extra);
            // مُعامَلاتُ العرضِ تُدمَج مع مُعامَلِ الحذف — السطحُ الابنُ يحتاج أباه هنا أيضًا
            $q = ($params !== '' ? ltrim($params, '?') . '&' : '') . $q;
            list($dc, $dh, $dbo) = ds_req($url . '?' . $q, $ctx);
        } else {
            $post = ds_subst($d['post'], $ctx, $extra);
            if ($csrf !== '' && !isset($post['csrf_token'])) { $post['csrf_token'] = $csrf; }
            list($dc, $dh, $dbo) = ds_req($purl, $ctx, $post);
        }
        list($cnt, $err) = ds_verify_count($ctx, $d['verify'], $extra);
        if ($cnt === 0) {
            $out[] = ds_res('DELETE', 'PASS', 'الصفُّ زال فعلًا (أو وُسِم محذوفًا)');
        } else {
            $msg = ds_screen_msg($dh, $ctx, $route);
            $out[] = ds_res('DELETE', 'FAIL', 'الصفُّ ما زال قائمًا بعد الحذف'
                . ($msg !== '' ? ' — قالت الشاشة: «' . mb_substr($msg, 0, 90) . '»' : ''));
        }
    }

    // ── ⑤ الحارس (اختبارٌ سالب: رمزٌ مكسورٌ يجب أن يُرَدّ) ──────────────────
    if (empty($crud['add']) || empty($crud['guard'])) {
        $out[] = ds_res('GUARD', 'NA', 'لا اختبارَ سالبًا معرَّفًا لهذا السطح');
    } else {
        $vf   = $crud['guard']['verify'];
        list($g0) = ds_verify_count($ctx, $vf, $extra);
        $post = ds_subst($crud['guard']['post'], $ctx, $extra);
        $post['csrf_token'] = 'رمزٌ-مكسورٌ-عمدًا-' . ds_mark($ctx);
        ds_req($purl, $ctx, $post);
        list($g1) = ds_verify_count($ctx, $vf, $extra);
        if ($g1 === $g0 && $g0 === 0) {
            $out[] = ds_res('GUARD', 'PASS', 'رمزٌ مكسورٌ لم يُنشئ صفًّا — الحارسُ حيّ');
        } else {
            $out[] = ds_res('GUARD', 'FAIL', '⚠ رمزٌ مكسورٌ **أنشأ صفًّا** — الحارسُ مخترَق');
        }
    }

    return $out;
}

/** رسالةُ الشاشةِ — من العنوانِ أو من وميضِ الجلسة، بالمساعدِ المشترك. */
function ds_screen_msg($headers, array $ctx, $route)
{
    $dir = $ctx['base'] . '/' . trim(dirname($route), '.');
    return ems_flash_or_msg($headers, rtrim($dir, '/'), function ($u) use ($ctx) {
        list(, , $b) = ds_req($u, $ctx);
        return (string) $b;
    });
}

// ══════════════════════════════════════════════════════════════════════════
// ⑦ التشغيلُ الكاملُ لإدارة
// ══════════════════════════════════════════════════════════════════════════

function ds_run(array $manifest, array &$ctx)
{
    $screens = $manifest['screens'];
    $total   = count($screens);
    $result  = array(
        'dept'      => $manifest['dept'],
        'dept_ar'   => $manifest['dept_ar'],
        'at'        => date('Y-m-d H:i:s'),
        'mode'      => $ctx['quick'] ? 'quick' : 'full',
        'screens'   => array(),
    );

    $i = 0;
    foreach ($screens as $spec) {
        $i++;
        fwrite(STDOUT, sprintf("\r  [%2d/%2d] %-46s", $i, $total, mb_substr($spec['route'], 0, 46)));
        $ops = ds_run_screen($spec, $ctx);
        $result['screens'][$spec['route']] = array(
            'label' => $spec['label'],
            'group' => isset($spec['group']) ? $spec['group'] : '—',
            'ops'   => $ops,
        );
    }
    fwrite(STDOUT, "\r" . str_repeat(' ', 70) . "\r");
    return $result;
}

// ══════════════════════════════════════════════════════════════════════════
// ⑧ الفرقُ عن خطِّ الأساس — جوهرُ الأداة
// ══════════════════════════════════════════════════════════════════════════

/**
 * يقارن نتيجةً بخطِّ أساسٍ ويصنّف كلَّ عمليةٍ إلى: broke (انكسر) · fixed (تحسّن)
 * · same (كما هو) · added (سطحٌ/عمليةٌ جديدة) · gone (اختفى).
 */
function ds_diff(array $now, $base, array $onlyOps = array())
{
    $d = array('broke' => array(), 'fixed' => array(), 'changed' => array(),
               'added' => array(), 'gone' => array(), 'same' => 0);
    if (!is_array($base) || empty($base['screens'])) { return null; }

    $rank = array('FAIL' => 0, 'SKIP' => 1, 'DENY' => 2, 'NA' => 3, 'PASS' => 4);

    foreach ($now['screens'] as $route => $cur) {
        if (!isset($base['screens'][$route])) {
            $d['added'][] = array('route' => $route, 'label' => $cur['label']);
            continue;
        }
        $bops = array();
        foreach ($base['screens'][$route]['ops'] as $o) { $bops[$o['op']] = $o; }
        foreach ($cur['ops'] as $o) {
            $op = $o['op'];
            if (!isset($bops[$op])) { continue; }
            // الوضعُ السريعُ لم يُجرِّب الكتابةَ — فلا يُقارَن ما لم يُقَس
            if (!empty($onlyOps) && !in_array($op, $onlyOps, true)) { continue; }
            $was = $bops[$op]['status']; $is = $o['status'];
            if ($was === $is) { $d['same']++; continue; }
            $rec = array('route' => $route, 'label' => $cur['label'], 'op' => $op,
                         'was' => $was, 'is' => $is, 'note' => $o['note']);
            $rw = isset($rank[$was]) ? $rank[$was] : 5;
            $ri = isset($rank[$is])  ? $rank[$is]  : 5;
            if ($is === 'FAIL' && $was !== 'FAIL')      { $d['broke'][] = $rec; }
            elseif ($was === 'FAIL' && $is !== 'FAIL')  { $d['fixed'][] = $rec; }
            elseif ($ri < $rw)                          { $d['broke'][] = $rec; }
            elseif ($ri > $rw)                          { $d['fixed'][] = $rec; }
            else                                        { $d['changed'][] = $rec; }
        }
    }
    foreach ($base['screens'] as $route => $b) {
        if (!isset($now['screens'][$route])) { $d['gone'][] = array('route' => $route, 'label' => $b['label']); }
    }
    return $d;
}

/** إحصاءٌ سريعٌ لنتيجة. */
function ds_tally(array $result)
{
    $t = array('PASS' => 0, 'FAIL' => 0, 'NA' => 0, 'DENY' => 0, 'SKIP' => 0);
    $byop = array();
    foreach ($result['screens'] as $s) {
        foreach ($s['ops'] as $o) {
            if (!isset($t[$o['status']])) { $t[$o['status']] = 0; }
            $t[$o['status']]++;
            if (!isset($byop[$o['op']])) { $byop[$o['op']] = array('PASS' => 0, 'FAIL' => 0, 'NA' => 0, 'DENY' => 0, 'SKIP' => 0); }
            $byop[$o['op']][$o['status']]++;
        }
    }
    return array($t, $byop);
}
