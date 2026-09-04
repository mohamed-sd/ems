<?php
/**
 * tools/injint01/sales_audit.php — تدقيقُ أسطحِ المبيعاتِ الستّةِ والأربعين
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **عُدّةُ `tests/dept_suite` تُجيب «هل تغيّر شيء؟» — وهذه تُجيب «هل فيه خلل؟».**
 *   فالانحدارُ الأخضرُ لا ينفي عطبًا قائمًا منذ الأساس.
 *
 * ⛔ **والنسخةُ الأولى من هذا المُنقّي كانت معطوبة**: عدَّت `$can_delete = false;`
 *   جملةَ SQL لأنَّ السطرَ فيه لفظُ `delete` ومتغيّر — فأخرجت 167 إشارةً
 *   جُلُّها وهمٌ. **والعطبُ في المُنقّي لا المُنقَّى.**
 *   ⇐ فالكشفُ الآن **بالسلسلةِ المُستبدِلةِ وحدَها**: في PHP لا تستبدل
 *     `'...'` أصلًا، فلا خطرَ فيها مهما بدت. والخطرُ في `"...$v..."`
 *     وفي الوصلِ `"…" . $v . "…"` — وهذان ما يُقاسان.
 *
 * ⛔ **ولا يُقاس الموضعُ بجدولٍ خطأ**: السايدبارُ يُبلَّغ من `nav_placements`
 *   (ورقةُ الدليل) لا من `nav_items` وحدَه — ومَن قاس بالثاني أخرج ثمانيةَ
 *   عشرَ سطحًا «خارجَ الملاحة» وهي مُعلَنة.
 *
 * ⛔ **ولا يُحكَم على ملفٍّ بنصِّه وحدَه**: السطحُ قد يستدعي عُدّةً مشتركةً
 *   (`u13_screen_kit` · `TenantDb` · حاقنَ CSRF) فيبدو عاريًا وهو محميّ.
 *
 * ◆ **وهذه مرشَّحاتٌ للفحصِ لا أحكام** — كلُّ إشارةٍ تحتاج قراءةَ موضعِها.
 *
 * التشغيل: php tools/injint01/sales_audit.php [--code=XXX]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8'); mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$ONLY = '';
foreach (array_slice($argv, 1) as $a) { if (preg_match('/^--code=(\w+)$/', $a, $m)) { $ONLY = $m[1]; } }

$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
$c = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $p);
if ($c->connect_errno) { exit('تعذّر الاتصال: ' . $c->connect_error . "\n"); }
$c->set_charset('utf8mb4');
$rows = function ($q) use ($c) { $r = $c->query($q); $o = array(); if (!$r) { return $o; } while ($x = $r->fetch_assoc()) { $o[] = $x; } return $o; };
$one  = function ($q) use ($c) { $r = $c->query($q); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; };

/* ═══ ① الأسطحُ من المانيفستِ الحاكم ═════════════════════════════════════ */
$man = (string) file_get_contents($ROOT . '/tests/dept_suite/manifest_sales.php');
preg_match_all("~'([A-Za-z_]+/[A-Za-z_0-9]+\.php)'~", $man, $m);
$files = array_values(array_unique($m[1])); sort($files);
printf("◆ أسطحُ المبيعات: %d\n", count($files));

/* ═══ ② السجلّاتُ الحاكمةُ للموضعِ والتسجيل ═══════════════════════════════ */
$norm = function ($r) { return strtolower(ltrim(preg_replace('~^(\.\./)+~', '', (string) $r), '/')); };
$placed = array();
foreach ($rows("SELECT route FROM nav_placements WHERE active=1") as $r) { $placed[$norm($r['route'])] = true; }
foreach ($rows('SELECT route FROM nav_workspace_placements') as $r) { $placed[$norm($r['route'])] = true; }
foreach ($rows('SELECT DISTINCT route FROM nav_items WHERE active=1') as $r) { $placed[$norm($r['route'])] = true; }
$mods = array();
foreach ($rows('SELECT id, code FROM modules') as $r) { $mods[$norm($r['code'])] = (int) $r['id']; }
printf("◆ مواضعُ مُعلَنةٌ (placements+items): %d · وحداتٌ مسجَّلة: %d\n\n", count($placed), count($mods));

$find = array();
$add = function ($axis, $sev, $code, $file, $line, $what, $why) use (&$find) { $find[] = compact('axis', 'sev', 'code', 'file', 'line', 'what', 'why'); };

/* ═══ ③ كشفُ السلاسلِ المُستبدِلةِ — بالمُرمِّزِ لا بالتعبيرِ النمطيّ ═════════ */
/** يُرجِع مواضعَ سلاسلَ مزدوجةٍ/heredoc تحوي SQL ومتغيّرًا مستبدَلًا. */
$interpolatedSql = function ($src) {
    $out = array();
    $toks = @token_get_all($src);
    if (!$toks) { return $out; }
    foreach ($toks as $t) {
        if (!is_array($t)) { continue; }
        list($id, $text, $line) = array($t[0], $t[1], $t[2]);
        /* T_ENCAPSED… لا تظهر مفردةً؛ السلسلةُ المزدوجةُ ذاتُ المتغيّرِ تأتي
           أجزاءً. فنكتفي بـT_CONSTANT_ENCAPSED_STRING (سلسلةٌ بلا استبدال)
           ونستثنيها، ونلتقط T_ENCAPSED_AND_WHITESPACE (‏جزءُ سلسلةٍ مستبدِلة). */
        if ($id !== T_ENCAPSED_AND_WHITESPACE) { continue; }
        if (!preg_match('~\b(SELECT|INSERT\s+INTO|UPDATE|DELETE\s+FROM|WHERE|FROM|JOIN|VALUES|ORDER\s+BY)\b~i', $text)) { continue; }
        $out[] = array('line' => $line, 'text' => trim(preg_replace('~\s+~', ' ', $text)));
    }
    return $out;
};
/** الوصلُ: "…SQL…" . $v — يُلتقط نصًّا لأنَّ المُرمِّزَ يفصله. */
$concatSql = function ($src) {
    $out = array();
    foreach (explode("\n", $src) as $n => $ln) {
        if (preg_match('~^\s*(\*|//|#)~', $ln)) { continue; }
        if (!preg_match('~["\'][^"\']*\b(SELECT|INSERT\s+INTO|UPDATE\s+`?\w|DELETE\s+FROM|WHERE|VALUES)\b~i', $ln)) { continue; }
        if (!preg_match('~["\']\s*\.\s*\$[A-Za-z_]\w*~', $ln)) { continue; }
        if (preg_match('~intval|\(int\)|real_escape|bind_param|prepare|escapeshell~i', $ln)) { continue; }
        $out[] = array('line' => $n + 1, 'text' => trim(mb_substr(trim($ln), 0, 100)));
    }
    return $out;
};

$includesOf = function ($src) { preg_match_all("~(?:require|include)(?:_once)?\s*[( ]\s*[^;]*?['\"]([^'\"]+\.php)['\"]~i", $src, $x); return $x[1]; };

foreach ($files as $f) {
    $path = $ROOT . '/' . $f;
    $src  = (string) @file_get_contents($path);
    if ($src === '') { continue; }
    $lines = explode("\n", $src);
    $incBlob = '';
    foreach ($includesOf($src) as $i) {
        foreach (array($ROOT . '/' . ltrim(preg_replace('~^(\.\./)+~', '', $i), '/'), dirname($path) . '/' . $i) as $cp) {
            if (is_file($cp)) { $incBlob .= (string) file_get_contents($cp); break; }
        }
    }
    $all = $src . "\n" . $incBlob;

    /* ── ⓐ برمجيّ: SQL بمتغيّرٍ مستبدَلٍ بلا تهذيب ──
       ⛔ **ولا يُحكَم بمحيطِ السطر**: `$company_id` مُهذَّبٌ بـ`intval` في رأسِ
          الملفِّ ويُستعمَل بعدَ مئةِ سطر — فنافذةُ ±4 أسطرٍ تراه عاريًا وهو آمن.
          ⇐ فيُتتبَّع **كلُّ إسنادٍ للمتغيِّرِ في الملفِّ كلِّه**، ولا يُبلَّغ عنه
            إلّا إذا كان **فيه إسنادٌ واحدٌ غيرُ مُهذَّب**. */
    $assignSafe = function ($var) use ($src) {
        $v = preg_quote($var, '~');
        if (!preg_match_all('~\$' . $v . '\s*=\s*([^;]{0,200});~', $src, $as)) {
            return null;                       /* لا إسنادَ في هذا الملفِّ — يُبلَّغ */
        }
        foreach ($as[1] as $rhs) {
            /* المُهذَّب: قولبةٌ صريحةٌ · تهريبٌ · سلسلةٌ حرفيّةٌ بلا متغيّر */
            if (preg_match('~intval|\(int\)|\(float\)|floatval|real_escape|escapeshell~i', $rhs)) { continue; }
            if (preg_match('~^\s*["\'][^$]*["\']\s*$~s', $rhs)) { continue; }
            return false;                      /* إسنادٌ غيرُ مُهذَّبٍ واحدٌ يكفي */
        }
        return true;
    };
    foreach (array_merge($interpolatedSql($src), $concatSql($src)) as $hit) {
        $ctx = implode("\n", array_slice($lines, max(0, $hit['line'] - 3), 6));
        if (preg_match('~bind_param|prepare\(~i', $ctx)) { continue; }
        /* المتغيّراتُ المستبدَلةُ في هذا السطرِ ومحيطِ الجملة */
        $stmt = implode("\n", array_slice($lines, max(0, $hit['line'] - 6), 12));
        if (!preg_match_all('~\$([A-Za-z_]\w*)~', $stmt, $vs)) { continue; }
        $unsafe = array();
        foreach (array_unique($vs[1]) as $v) {
            if (in_array($v, array('this', 'GLOBALS', '_SESSION', '_POST', '_GET', 'conn'), true)) { continue; }
            $verdict = $assignSafe($v);
            if ($verdict === false) { $unsafe[] = '$' . $v; }
        }
        if (!$unsafe) { continue; }             /* كلُّ متغيّراتِه مُهذَّبةٌ — لا إشارة */
        $add('برمجيّ', 1, 'SQL_INTERPOLATION', $f, $hit['line'],
            mb_substr($hit['text'], 0, 70) . '  ⟵ ' . implode(' ', array_slice($unsafe, 0, 3)),
            'متغيّرٌ يُسنَد في هذا الملفِّ بلا قولبةٍ ولا تهريبٍ ثمَّ يُستبدَل في جملةٍ — مرشَّحُ حقن.');
    }

    /* ── ⓑ معماريّ: كتابةٌ مباشرةٌ عابرةٌ للنطاق ── */
    $foreign = array('fin_' => 'المالية', 'sup_' => 'الموردين', 'supplier' => 'الموردين',
                     'tre_' => 'الخزينة', 'proc_' => 'المشتريات', 'prc_' => 'المشتريات',
                     'mnt_' => 'الصيانة', 'wh_' => 'المخازن', 'hr_' => 'الموارد البشرية');
    foreach ($lines as $n => $ln) {
        if (preg_match('~^\s*(\*|//|#)~', $ln)) { continue; }
        if (!preg_match('~\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?([a-z0-9_]{4,})`?~i', $ln, $w)) { continue; }
        $tbl = strtolower($w[2]);
        foreach ($foreign as $pre => $dep) {
            if (strpos($tbl, $pre) === 0) {
                $add('معماريّ', 1, 'CROSS_DOMAIN_WRITE', $f, $n + 1, strtoupper(trim($w[1])) . " على `$tbl`",
                    "سطحُ مبيعاتٍ يكتب في نطاقِ $dep مباشرةً — والمالكُ يكتب والمستهلكُ يقرأ.");
            }
        }
    }

    /* ── ⓒ معماريّ: مخطَّطٌ متوازٍ راكد ── */
    foreach (array('sal_quotations', 'sal_claims', 'sal_projects', 'sal_client_needs', 'ops_timesheet', 'supplierscontracts') as $dead) {
        if (!preg_match('~\b' . $dead . '\b~', $src)) { continue; }
        if ((int) $one("SELECT COUNT(*) FROM `$dead`") === 0) {
            $add('معماريّ', 2, 'DEAD_PARALLEL_SCHEMA', $f, 0, "يشير إلى `$dead` وهو فارغٌ (0 صفّ)",
                'مخطَّطٌ متوازٍ لم يُمارَس — إمّا يُهجَر أو يُملأ، والبقاءُ بينهما يُربك القارئ.');
        }
    }

    /* ── ⓓ برمجيّ: نموذجُ POST بلا رمزِ CSRF ── */
    if (preg_match('~<form[^>]*method\s*=\s*[\'"]?post~i', $src) && !preg_match('~csrf~i', $all)) {
        $add('برمجيّ', 1, 'FORM_WITHOUT_CSRF', $f, 0, 'نموذجُ POST بلا أثرِ csrf في الملفِّ ومُدرَجاتِه',
            'الحقنُ الآليُّ يغطّي fetch/XHR لا النموذجَ العاديّ — فالنموذجُ العاري يُردُّ 403 أو يمرُّ بلا حارس.');
    }

    /* ── ⓔ برمجيّ: استعلامٌ بلا فحصِ نتيجة ── */
    $q = preg_match_all('~mysqli_query\s*\(~i', $src);
    if ($q >= 3 && !preg_match('~mysqli_query[^;]{0,200}\)\s*(?:or\b|\?)|if\s*\(\s*\$?\w*\s*=\s*@?mysqli_query~i', $src)) {
        $add('برمجيّ', 2, 'QUERY_RESULT_UNCHECKED', $f, 0, "$q استعلامًا بلا فحصِ نتيجةٍ ظاهر",
            'الاستعلامُ الفاشلُ يُرجِع false فيصير الجدولُ فارغًا صامتًا — فراغٌ يُقرأ «لا بيانات».');
    }

    /* ── ⓕ منطقيّ: اعتمادٌ بلا فصلِ واجبات ── */
    if (preg_match('~\bapprove|اعتماد|موافقة~iu', $src)
        && preg_match('~INSERT\s+INTO|UPDATE|->update\(|->insert\(~i', $src)
        && !preg_match('~submitted_by|created_by|requested_by|<>\s*\$?\w*user|!=\s*\$?\w*user|self_?approv|SoD~i', $all)) {
        $add('منطقيّ', 1, 'SELF_APPROVAL_UNGUARDED', $f, 0, 'اعتمادٌ يكتب بلا مقارنةٍ ظاهرةٍ بمُنشِئِ السجلّ',
            'الاعتمادُ الذاتيُّ ممكنٌ ما لم يُمنَع — وهو من المساراتِ السالبةِ التي يفرض الأمرُ اختبارَها.');
    }

    /* ── ⓖ معماريّ: موضعٌ أو تسجيلٌ ناقص ── */
    $key = $norm($f);
    if (!isset($placed[$key])) {
        $add('معماريّ', 2, 'SURFACE_NOT_PLACED', $f, 0, 'لا موضعَ له في nav_placements ولا nav_items',
            'يُفتَح بالمسارِ ولا يُبلَغ بالملاحة — «الصلاحيةُ تمنح الوصولَ والموضعُ يمنح الملاحة».');
    }
    if (!isset($mods[$key])) {
        $add('معماريّ', 1, 'SURFACE_NOT_A_MODULE', $f, 0, 'لا صفَّ له في `modules`',
            'الشاشةُ غيرُ المسجَّلةِ تُردُّ بالحارسِ المركزيّ (قرارُ المالك 2026-08-05).');
    }
}

/* ═══ ④ العرض ══════════════════════════════════════════════════════════ */
$byCode = array();
foreach ($find as $x) { if ($ONLY && $x['code'] !== $ONLY) { continue; } $byCode[$x['code']][] = $x; }
uasort($byCode, function ($a, $b) { return count($b) - count($a); });
foreach (array('معماريّ', 'برمجيّ', 'منطقيّ') as $ax) {
    $tot = 0;
    foreach ($byCode as $items) { if ($items[0]['axis'] === $ax) { $tot += count($items); } }
    printf("\n══ %s — %d إشارة ══\n", $ax, $tot);
    foreach ($byCode as $code => $items) {
        if ($items[0]['axis'] !== $ax) { continue; }
        printf("\n  ◆ %-24s [%s] × %d\n     %s\n", $code, $items[0]['sev'] === 1 ? 'عالٍ' : 'متوسّط', count($items), $items[0]['why']);
        $s = 0;
        foreach ($items as $it) {
            if ($s++ >= 8) { printf("     … و%d موضعًا آخر\n", count($items) - 8); break; }
            printf("     · %-40s%-6s %s\n", $it['file'], $it['line'] ? ':' . $it['line'] : '', mb_substr($it['what'], 0, 66));
        }
    }
}
printf("\n\n══ الحصيلة ══\n  أسطحٌ مفحوصة = %d · إشاراتٌ = %d\n", count($files), count($find));
file_put_contents($ROOT . '/docs/injint01/sales_audit.json', json_encode($find, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "  التفصيل: docs/injint01/sales_audit.json\n\n⛔ مرشَّحاتٌ للفحصِ لا أحكام.\n";
