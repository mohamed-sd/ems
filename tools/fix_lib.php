<?php
/**
 * tools/fix_lib.php — مكتبةُ حزمةِ التصحيحِ FIX (مشتركةٌ بين فواحصِها)
 * ═══════════════════════════════════════════════════════════════════════════
 * موضعٌ واحدٌ لكلِّ ما يتكرر: الاتصالُ · جردُ الأسطح · حلُّ الموديول · العنوان.
 * ◆ لا فحصَ هنا — الفواحصُ تستدعي، وهذه تُجيب. فالحكمُ في موضعٍ واحدٍ يُختبر
 *   مرةً واحدة (CS-05 مطبَّقًا على الأدواتِ نفسِها).
 */

if (!function_exists('fix_env')) {
    /** يقرأ .env في مصفوفةٍ واحدةٍ (بلا تحميلِ تطبيقٍ كامل). */
    function fix_env($root)
    {
        static $cache = null;
        if ($cache !== null) { return $cache; }
        $cache = array();
        $f = $root . '/.env';
        if (is_file($f)) {
            foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
                if ($ln === '' || $ln[0] === '#' || strpos($ln, '=') === false) { continue; }
                list($k, $v) = explode('=', $ln, 2);
                $cache[trim($k)] = trim($v);
            }
        }
        return $cache;
    }
}

if (!function_exists('fix_db')) {
    /** اتصالٌ حيٌّ بالقاعدةِ من .env — يخرج برسالةٍ صريحةٍ عند الفشل (لا صمت). */
    function fix_db()
    {
        $root = dirname(__DIR__);
        $e = fix_env($root);
        $host = 'localhost'; $port = 3307; $user = 'root'; $pass = ''; $name = 'equipation_manage';
        if (isset($e['DB_HOST'])) { $hp = explode(':', $e['DB_HOST']); $host = $hp[0]; if (isset($hp[1])) { $port = (int) $hp[1]; } }
        if (isset($e['DB_PORT'])) { $port = (int) $e['DB_PORT']; }
        if (isset($e['DB_USER'])) { $user = $e['DB_USER']; }
        if (isset($e['DB_PASS'])) { $pass = $e['DB_PASS']; }
        if (isset($e['DB_NAME'])) { $name = $e['DB_NAME']; }
        $db = @new mysqli($host, $user, $pass, $name, $port);
        if ($db->connect_errno) { exit("تعذّر الاتصال بالقاعدة: " . $db->connect_error . "\n"); }
        $db->set_charset('utf8mb4');
        return $db;
    }
}

if (!function_exists('fix_q')) {
    /** اقتباسٌ آمنٌ لسلسلةٍ في SQL مولَّد. */
    function fix_q($db, $s) { return "'" . $db->real_escape_string((string) $s) . "'"; }
}

if (!function_exists('fix_one')) {
    function fix_one($db, $sql)
    {
        $r = $db->query($sql);
        if (!$r) { return null; }
        $x = $r->fetch_row();
        return $x ? $x[0] : null;
    }
}

if (!function_exists('fix_skip_dirs')) {
    /** المجلداتُ خارجَ نطاقِ الأسطحِ الحية (نسخٌ · أدواتٌ · بائعون · وثائق). */
    function fix_skip_dirs()
    {
        return array(
            'storage/', 'vendor/', 'node_modules/', 'tools/', 'tests/', 'docs/',
            'database/', '.git/', '.claude/', 'logs/', 'uploads/', '.ssdiff/', 'backup', 'backups/',
        );
    }
}

if (!function_exists('fix_is_skipped')) {
    function fix_is_skipped($rel)
    {
        $r = strtolower($rel);
        foreach (fix_skip_dirs() as $d) {
            if (strpos($r, strtolower($d)) === 0 || strpos($r, '/' . strtolower($d)) !== false) { return true; }
        }
        return false;
    }
}

if (!function_exists('fix_php_files')) {
    /** كلُّ ملفاتِ PHP الحيةِ في المشروع (بلا النسخِ والأدواتِ والبائعين). */
    function fix_php_files($root)
    {
        static $cache = null;
        if ($cache !== null) { return $cache; }
        $cache = array();
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $f) {
            if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            if (fix_is_skipped($rel)) { continue; }
            $cache[] = $rel;
        }
        sort($cache);
        return $cache;
    }
}

if (!function_exists('fix_surface_files')) {
    /**
     * الأسطحُ = ملفاتُ PHP التي تُضمِّن insidebar — فهي وحدَها تبلغ حارسَ العرض
     * ‎enforce_current_page_view_permission‎. غيرُها نقاطُ AJAX ومساعدون يحرسهم
     * ‎action_guard‎ لا هذا الحارس (إعلانُ ما لا يُقاس — GT-01).
     */
    function fix_surface_files($root)
    {
        $out = array();
        foreach (fix_php_files($root) as $rel) {
            // الجزئياتُ ليست أسطحًا: تُضمَّن ولا تُطلب. والقشرةُ نفسُها ليست سطحًا.
            if (strpos($rel, 'includes/') === 0) { continue; }
            if (strpos($rel, 'examples/') === 0) { continue; }
            $bn = basename($rel);
            if ($bn === 'inheader.php' || $bn === 'insidebar.php') { continue; }
            $src = (string) @file_get_contents($root . '/' . $rel);
            if ($src === '') { continue; }
            // ◆ الشرطُ عبارةُ تضمينٍ حقيقيةٌ لا ذكرٌ في تعليق (گوتشا: «insidebar»
            //   تُذكر في تعليقاتِ عشراتِ الملفات). التعليقاتُ تُفرَّغ والسلاسلُ
            //   تبقى — لأن اسمَ الملفِّ نفسُه سلسلة.
            $code = fix_strip_comments($src);
            if (!preg_match('/\b(include|include_once|require|require_once)\b[^;]{0,200}insidebar\.php/i', $code)) { continue; }
            $out[] = $rel;
        }
        return $out;
    }
}

if (!function_exists('fix_resolve_module_id')) {
    /**
     * يُحاكي get_module_id_by_script_path حرفًا بحرف — مطابقةٌ تامةٌ للمسارِ
     * النسبيِّ · أو اسمُ الملفِّ التامّ · أو الذيلُ المسبوقُ بـ«/». بلا تفضيلِ
     * دورٍ (الجردُ يسأل «أيوجد صفٌّ ما؟» لا «أيَّ صفٍّ يختار دوري؟»).
     */
    function fix_resolve_module_id($db, $rel)
    {
        $rel      = str_replace('\\', '/', ltrim($rel, '/'));
        $basename = basename($rel);
        if ($basename === '') { return null; }
        $sql = "SELECT id FROM modules
                 WHERE code = " . fix_q($db, $rel) . "
                    OR code = " . fix_q($db, $basename) . "
                    OR code LIKE " . fix_q($db, '%/' . $basename) . "
                 ORDER BY (code = " . fix_q($db, $rel) . ") DESC, id ASC
                 LIMIT 1";
        $v = fix_one($db, $sql);
        return $v === null ? null : (int) $v;
    }
}

if (!function_exists('fix_screen_title')) {
    /** عنوانُ الشاشةِ من ‎$page_title‎ أو من ترويسةِ الملفِّ أو من اسمه. */
    function fix_screen_title($abs, $rel)
    {
        $src = (string) @file_get_contents($abs);
        if ($src !== '') {
            if (preg_match('/\$page_title\s*=\s*[\'"]([^\'"]{2,120})[\'"]/u', $src, $m)) { return $m[1]; }
            if (preg_match('/^\s*\*\s*[A-Za-z0-9_\/\.\-]+\.php\s*[—–-]\s*(.{2,120}?)\s*$/mu', $src, $m)) { return trim($m[1]); }
        }
        return basename($rel, '.php');
    }
}

if (!function_exists('fix_strip_php_noise')) {
    /**
     * يُفرِّغ التعليقاتِ والسلاسلَ النصيةَ من مصدرِ PHP مع **حفظِ أرقامِ الأسطر**
     * (كلُّ محرفٍ مُزال يُستبدل بمسافةٍ وكلُّ سطرٍ جديدٍ يبقى). هذا يمنع أخطر
     * گوتشا في الفواحصِ النصية: عبارةُ ‎INSERT‎ داخلَ تعليقٍ أو سلسلةِ رسالةٍ
     * تُقرأ كتابةً حقيقية، وسطرُ حارسٍ داخلَ تعليقٍ يُقرأ حارسًا.
     */
    function fix_strip_php_noise($src)
    {
        $out = '';
        $n = strlen($src);
        $i = 0;
        $state = 'code'; // code | line_comment | block_comment | sq | dq | heredoc
        $hdLabel = '';
        while ($i < $n) {
            $c  = $src[$i];
            $c2 = ($i + 1 < $n) ? substr($src, $i, 2) : '';
            if ($state === 'code') {
                if ($c2 === '//' || $c === '#') { $state = 'line_comment'; $out .= '  '; $i += ($c2 === '//' ? 2 : 1); continue; }
                if ($c2 === '/*') { $state = 'block_comment'; $out .= '  '; $i += 2; continue; }
                if ($c === "'")  { $state = 'sq'; $out .= ' '; $i++; continue; }
                if ($c === '"')  { $state = 'dq'; $out .= ' '; $i++; continue; }
                if ($c2 === '<<' && preg_match('/^<<<[ \t]*([\'"]?)([A-Za-z_][A-Za-z0-9_]*)\1\r?\n/', substr($src, $i), $m)) {
                    $state = 'heredoc'; $hdLabel = $m[2];
                    $out .= str_repeat(' ', strlen($m[0]) - 1) . "\n";
                    $i += strlen($m[0]); continue;
                }
                $out .= $c; $i++; continue;
            }
            if ($state === 'line_comment') {
                if ($c === "\n") { $state = 'code'; $out .= "\n"; } else { $out .= ' '; }
                $i++; continue;
            }
            if ($state === 'block_comment') {
                if ($c2 === '*/') { $state = 'code'; $out .= '  '; $i += 2; continue; }
                $out .= ($c === "\n") ? "\n" : ' '; $i++; continue;
            }
            if ($state === 'sq' || $state === 'dq') {
                $q = ($state === 'sq') ? "'" : '"';
                if ($c === '\\') { $out .= '  '; $i += 2; continue; }
                if ($c === $q)   { $state = 'code'; $out .= ' '; $i++; continue; }
                $out .= ($c === "\n") ? "\n" : ' '; $i++; continue;
            }
            if ($state === 'heredoc') {
                if ($c === "\n" && preg_match('/^\n[ \t]*' . preg_quote($hdLabel, '/') . '\b/', substr($src, $i), $m)) {
                    $state = 'code'; $out .= "\n" . str_repeat(' ', strlen($m[0]) - 1); $i += strlen($m[0]); continue;
                }
                $out .= ($c === "\n") ? "\n" : ' '; $i++; continue;
            }
        }
        return $out;
    }
}

if (!function_exists('fix_strip_comments')) {
    /**
     * يُفرِّغ التعليقاتِ وحدَها ويُبقي السلاسلَ النصية (لأن أسماءَ الملفاتِ في
     * عباراتِ التضمينِ سلاسل) — مع حفظِ أرقامِ الأسطر. يستعمل موسِّمَ PHP نفسَه
     * فلا يخطئ في حالةٍ حافّة.
     */
    function fix_strip_comments($src)
    {
        $out = '';
        foreach (@token_get_all($src) as $t) {
            if (is_array($t)) {
                if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) {
                    $out .= str_repeat("\n", substr_count($t[1], "\n"));
                    continue;
                }
                $out .= $t[1];
            } else {
                $out .= $t;
            }
        }
        return $out;
    }
}

if (!function_exists('fix_blank_comments')) {
    /**
     * كـ‏fix_strip_comments‎ لكنه يحفظ **طولَ النصِّ بالبايت** أيضًا: يستبدل بكلِّ
     * بايتِ تعليقٍ مسافةً ويُبقي الأسطر. فتبقى كلُّ مواضعِ البايتِ صالحةً بعده.
     *
     * الحاجةُ إليه: نصٌّ في تعليقِ PHP لا يبلغ المتصفّحَ قطُّ، فعدُّه HTML يخلق
     * دَينًا وهميًّا — مثالٌ حيّ: `<input.md>` في شرحِ استعمالِ سكربتٍ سطريّ.
     */
    function fix_blank_comments($src)
    {
        $out = '';
        foreach (@token_get_all($src) as $t) {
            $txt = is_array($t) ? $t[1] : $t;
            if (is_array($t) && ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT)) {
                $out .= preg_replace('/[^\n]/', ' ', $txt);
                continue;
            }
            $out .= $txt;
        }
        return (strlen($out) === strlen($src)) ? $out : $src;   // فشلُ التوسيمِ ⇒ الأصلُ سالمًا
    }
}

if (!function_exists('fix_line_of')) {
    /** رقمُ السطرِ (1-based) لموضعِ بايتٍ في نصّ. */
    function fix_line_of($src, $pos) { return substr_count(substr($src, 0, $pos), "\n") + 1; }
}

if (!function_exists('fix_render_screen')) {
    /**
     * يُصيِّر شاشةً بدورٍ في عمليةٍ منفصلةٍ ويُرجع جسمَها الحقيقيّ.
     * ◆ گوتشا: ترتيبُ الحكمِ في u13_render_one يضع «تنبيه» فوقَ «جسمٌ فارغ»،
     *   وحاقنُ الجلسةِ يُنتج تنبيهًا دائمًا — فالحكمُ يُبنى على **الجسم** لا على
     *   تصنيفِ الحاقن.
     * @return array{body:string,bytes:int,fatal:string}
     */
    function fix_render_screen($ROOT, $rel, $role, $uid = 891, $co = 4)
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/tools/u13_render_one.php')
             . ' ' . escapeshellarg($rel) . ' ' . escapeshellarg((string) $role)
             . ' ' . (int) $uid . ' ' . (int) $co . ' --body 2>&1';
        $out = (string) @shell_exec($cmd);
        $verdict = ''; $body = '';
        $pos = strpos($out, 'VERDICT|');
        if ($pos !== false) {
            $nl = strpos($out, "\n", $pos);
            $verdict = trim(substr($out, $pos + 8, $nl === false ? null : $nl - $pos - 8));
            $body = $nl === false ? '' : substr($out, $nl + 1);
        }
        $body = preg_replace('/^ACT\|[^\n]*\n/m', '', $body);
        return array(
            'body'  => $body,
            'bytes' => strlen(trim($body)),
            'fatal' => strpos($verdict, 'fatal|') === 0 ? $verdict : '',
        );
    }
}

if (!function_exists('fix_screen_view_evidence')) {
    /**
     * GT-01 · البديلُ الصحيحُ للشرطِ الخاوي «هل في الملفِّ سلسلةُ table؟».
     *
     * ◆ ما كان يُقاس: وجودُ الحروفِ ‎t-a-b-l-e‎ في نصِّ ملفِّ PHP — وكلُّ ملفٍّ
     *   يحتويها (‎$table‎ · ‎mysqli_fetch_assoc‎ في تعليقٍ · اسمُ جدولٍ في استعلام).
     *   فالمعيارُ كان يمرُّ أخضرَ على كلِّ شيءٍ ولا يرسب على أيِّ شيء.
     *
     * ◆ ما يُقاس الآن — بالتصييرِ الحيِّ وتحليلِ **الناتج** لا المصدر:
     *   ① أفي الجسمِ المُصيَّرِ عنصرُ ‎<table>‎ حقيقيٌّ بصفوفِ رأس؟
     *   ② أعددُ أعمدتِه فوقَ الصفر (حارسُ «صفرِ الأعمدة» — SH-05)؟
     *   ③ أله منتقي منظرٍ فعليّ: إمّا صنفُ إطارِ الجداولِ الموحَّد الذي يحقن
     *      منتقي الأعمدة، أو منتقي مناظرَ مبنيٌّ في الشاشة — ولا يُقبل
     *      ‎data-no-dt‎ (وهي إعلانُ «لا إطارَ لي» صراحةً).
     *
     * @return array{ok:bool,reason:string,tables:int,cols:int}
     */
    function fix_screen_view_evidence($ROOT, $rel, $role)
    {
        $r = fix_render_screen($ROOT, $rel, $role);
        if ($r['fatal'] !== '') { return array('ok' => false, 'reason' => 'عطبٌ عند التصيير: ' . $r['fatal'], 'tables' => 0, 'cols' => 0); }
        if ($r['bytes'] === 0)  { return array('ok' => false, 'reason' => 'ارتدادٌ حوكميٌّ — لم تُصيَّر لهذا الدور', 'tables' => 0, 'cols' => 0); }

        $body = $r['body'];
        $tables = preg_match_all('/<table\b/i', $body);
        if ($tables === 0) { return array('ok' => false, 'reason' => 'صُيِّرت بلا أيِّ جدول', 'tables' => 0, 'cols' => 0); }

        // أعمدةُ أولِ رأسٍ مُصيَّر — حارسُ «صفرِ الأعمدة».
        $cols = 0;
        if (preg_match('/<thead\b.*?<tr\b(.*?)<\/tr>/is', $body, $m)) { $cols = preg_match_all('/<th\b/i', $m[1]); }
        if ($cols === 0) { $cols = preg_match_all('/<th\b/i', $body); }
        if ($cols === 0) { return array('ok' => false, 'reason' => 'جدولٌ بلا رأسٍ ولا أعمدة (صفرُ أعمدة)', 'tables' => $tables, 'cols' => 0); }

        // منتقي المنظر: إطارُ الجداولِ الموحَّد (يحقن colvis) أو منتقٍ مبنيّ.
        $unified = (strpos($body, 'alltables') !== false)
                || (strpos($body, 'dataTable') !== false)
                || (strpos($body, 'ems-view-picker') !== false)
                || (strpos($body, 'data-view-key') !== false);
        $optedOut = (preg_match('/<table[^>]*\bdata-no-dt\b/i', $body) === $tables && $tables > 0);
        if (!$unified) {
            return array('ok' => false, 'tables' => $tables, 'cols' => $cols,
                'reason' => $optedOut ? 'كلُّ جداولِها معلَنةٌ خارجَ الإطارِ الموحَّد (data-no-dt) فلا منتقي منظرٍ لها'
                                      : 'جدولٌ بلا إطارٍ موحَّدٍ ولا منتقي منظر');
        }
        return array('ok' => true, 'tables' => $tables, 'cols' => $cols,
            'reason' => "صُيِّرت بـ{$tables} جدولًا و{$cols} عمودًا ومنتقي منظرٍ فعّال");
    }
}
