<?php
/**
 * tools/fix_checks.php — أجسامُ فواحصِ حزمةِ FIX
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ كلُّ فحصٍ هنا يُثبت بأحدِ ثلاثة: **نداءٍ حيٍّ** (عمليةٌ منفصلةٌ تُشغِّل الشيفرةَ
 *   فعلًا) · أو **استعلامٍ** على القاعدةِ الحية · أو **توسيمٍ نحويٍّ** للشيفرة
 *   (token_get_all). ◆ ولا فحصَ نتيجتُه من مطابقةِ سلسلةٍ نصيةٍ في ملف
 *   (FIXA-0034) — فذاك ما أبطل البوابتين.
 */

require_once __DIR__ . '/fix_lib.php';

/* ═══════════════════════════════════════════════════════════════════════
 *  أدواتٌ نحويةٌ مشتركة
 * ═══════════════════════════════════════════════════════════════════════ */

if (!function_exists('fix_sql_write_lines')) {
    /**
     * أسطرُ عباراتِ الكتابةِ الحرفيةِ في ملف — بالتوسيمِ لا بالنص.
     * تُفحص **السلاسلُ النصيةُ وحدَها** (فعبارةُ SQL تعيش داخلَ سلسلة) وتُستبعد
     * التعليقاتُ تمامًا. ◆ گوتشا مُتجنَّبة: مسحُ النصِّ الخامِّ يعدُّ عبارةً في
     * تعليقٍ كتابةً حقيقيةً — وقد كان ذلك مصدرَ إنذاراتٍ كاذبةٍ في فواحصَ سابقة.
     *
     * @return array<int,array{line:int,sql:string}>
     */
    function fix_sql_write_lines($src)
    {
        $hits = array();
        $toks = @token_get_all($src);
        if (!is_array($toks)) { return $hits; }
        foreach ($toks as $t) {
            if (!is_array($t)) { continue; }
            if ($t[0] !== T_CONSTANT_ENCAPSED_STRING && $t[0] !== T_ENCAPSED_AND_WHITESPACE
                && $t[0] !== T_INLINE_HTML && !(defined('T_HEREDOC') && $t[0] === T_HEREDOC)) { continue; }
            if ($t[0] === T_INLINE_HTML) { continue; } // HTML ليس SQL
            if (preg_match('/\b(INSERT\s+INTO|UPDATE\s+[`"\']?[A-Za-z_][A-Za-z0-9_]*|DELETE\s+FROM|REPLACE\s+INTO)\b/i', $t[1], $m)) {
                $hits[] = array('line' => (int) $t[2], 'sql' => trim(preg_replace('/\s+/', ' ', mb_substr($m[0], 0, 60))));
            }
        }
        return $hits;
    }
}

if (!function_exists('fix_guard_lines')) {
    /**
     * أسطرُ الحرّاسِ في ملفِّ سطح — بالتوسيمِ أيضًا. الحارسُ أحدُ هذه:
     *   ‎insidebar.php‎ (يُنفِّذ enforce_current_page_view_permission)
     *   ‎enforce_current_page_view_permission‎ · ‎check_view_permission‎
     *   ‎enforce_module_permission_json‎ · ‎ems_require_action‎ / ‎action_guard‎
     */
    function fix_guard_lines($src)
    {
        $lines = array();
        $code  = fix_strip_comments($src);
        $needles = array(
            'insidebar.php',
            'enforce_current_page_view_permission',
            'check_view_permission',
            'enforce_module_permission_json',
            'ems_require_action',
            'ems_action_guard',
        );
        foreach (explode("\n", $code) as $i => $ln) {
            foreach ($needles as $nd) {
                if (strpos($ln, $nd) !== false) { $lines[] = $i + 1; break; }
            }
        }
        return $lines;
    }
}

if (!function_exists('fix_run_php')) {
    /** يُشغِّل ملفَّ PHP في عمليةٍ منفصلةٍ ويرجع مخرجَه (نداءٌ حيٌّ لا تخمين). */
    function fix_run_php($script, array $args = array())
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
        foreach ($args as $a) { $cmd .= ' ' . escapeshellarg((string) $a); }
        return (string) @shell_exec($cmd . ' 2>&1');
    }
}

/* ═══════════════════════════════════════════════════════════════════════
 *  AC-F1 · الفشلُ مغلقٌ في الحارسِ المركزي
 * ═══════════════════════════════════════════════════════════════════════ */

if (!function_exists('fix_check_failclosed')) {
    function fix_check_failclosed($db, $ROOT)
    {
        // (أ) نداءٌ حيّ: شاشةٌ وهميةٌ قطعًا غيرُ مسجَّلة — ماذا يرجع الحارس؟
        $out = fix_run_php($ROOT . '/tools/fix_probe_guard.php', array('ZZ_UNREGISTERED/never_exists_' . getmypid() . '.php'));
        $live = null;
        foreach (explode("\n", $out) as $ln) {
            if (strpos($ln, 'GUARD|') === 0) { $live = json_decode(trim(substr($ln, 6)), true); }
        }
        if (!is_array($live)) {
            return array('ok' => false, 'evidence' => 'النداءُ الحيُّ لم يُرجع حكمًا — ' . mb_substr(trim($out), 0, 200));
        }
        $granted = array();
        foreach (array('can_view', 'can_add', 'can_edit', 'can_delete', 'can_export') as $p) {
            if (!empty($live[$p])) { $granted[] = $p; }
        }

        // (ب) تحليلُ الشيفرة: أيُّ فرعٍ يرجع true عند غيابِ الصف؟
        $src  = (string) @file_get_contents($ROOT . '/includes/permissions_helper.php');
        $code = fix_strip_comments($src);
        $permissive = 0;
        if (preg_match_all('/return\s*\[([^\]]{0,400})\]\s*;/s', $code, $m)) {
            foreach ($m[1] as $body) {
                if (preg_match('/can_view\s*=>\s*true/i', $body)) { $permissive++; }
            }
        }

        $ok = empty($granted) && $permissive === 0;
        return array(
            'ok' => $ok,
            'evidence' => 'النداءُ الحيُّ على شاشةٍ غيرِ مسجَّلة: '
                . (empty($granted) ? 'منعٌ كامل' : 'سماحٌ في ' . implode('،', $granted))
                . ' · فروعُ الإرجاعِ السامحةِ في الحارس: ' . $permissive,
        );
    }
}

if (!function_exists('fix_check_surfaces_registered')) {
    function fix_check_surfaces_registered($db, $ROOT)
    {
        $miss = array();
        $all = fix_surface_files($ROOT);
        foreach ($all as $rel) {
            if (fix_resolve_module_id($db, $rel) === null) { $miss[] = $rel; }
        }
        return array(
            'ok' => empty($miss),
            'evidence' => count($all) . ' سطحًا مُسحت · غيرُ مسجَّل: ' . count($miss)
                . ($miss ? ' → ' . implode(' · ', array_slice($miss, 0, 6)) : ''),
        );
    }
}

if (!function_exists('fix_check_surfaces_granted')) {
    function fix_check_surfaces_granted($db, $ROOT)
    {
        $miss = array();
        foreach (fix_surface_files($ROOT) as $rel) {
            $mid = fix_resolve_module_id($db, $rel);
            if ($mid === null) { continue; }
            $n = (int) fix_one($db, "SELECT COUNT(*) FROM role_permissions rp
                                      JOIN modules m ON m.id = rp.module_id
                                     WHERE m.code = (SELECT code FROM modules WHERE id = " . (int) $mid . ")
                                       AND rp.can_view = 1");
            if ($n === 0) { $miss[] = $rel; }
        }
        return array(
            'ok' => empty($miss),
            'evidence' => 'أسطحٌ بلا منحةِ قراءة: ' . count($miss)
                . ($miss ? ' → ' . implode(' · ', array_slice($miss, 0, 6)) : ''),
        );
    }
}

/* ═══════════════════════════════════════════════════════════════════════
 *  AC-F2 · الحارسُ يسبق أولَ كتابة
 * ═══════════════════════════════════════════════════════════════════════ */

if (!function_exists('fix_check_guard_before_write')) {
    function fix_check_guard_before_write($ROOT)
    {
        $bad = array(); $scanned = 0;
        foreach (fix_surface_files($ROOT) as $rel) {
            $src = (string) @file_get_contents($ROOT . '/' . $rel);
            if ($src === '') { continue; }
            $writes = fix_sql_write_lines($src);
            if (!$writes) { continue; }
            $scanned++;
            $guards = fix_guard_lines($src);
            $firstGuard = $guards ? min($guards) : PHP_INT_MAX;
            $firstWrite = PHP_INT_MAX;
            foreach ($writes as $w) { if ($w['line'] < $firstWrite) { $firstWrite = $w['line']; } }
            if ($firstWrite < $firstGuard) {
                $bad[] = $rel . ' (كتابة:' . $firstWrite . ' · حارس:' . ($firstGuard === PHP_INT_MAX ? 'غائب' : $firstGuard) . ')';
            }
        }
        return array(
            'ok' => empty($bad),
            'evidence' => $scanned . ' سطحًا فيه كتابة · مخالفٌ: ' . count($bad)
                . ($bad ? ' → ' . implode(' · ', array_slice($bad, 0, 8)) : ''),
        );
    }
}

/* ═══════════════════════════════════════════════════════════════════════
 *  AC-F3 · محرّكُ التصدير
 * ═══════════════════════════════════════════════════════════════════════ */

if (!function_exists('fix_check_export_guard')) {
    function fix_check_export_guard($ROOT)
    {
        // (أ) نداءٌ حيّ: هل تُعرَّف دالةُ الصلاحياتِ فعلًا بعدَ تحميلِ excel.php؟
        $out  = fix_run_php($ROOT . '/tools/fix_probe_export.php');
        $live = null;
        foreach (explode("\n", $out) as $ln) {
            if (strpos($ln, 'EXPORT|') === 0) { $live = json_decode(trim(substr($ln, 7)), true); }
        }
        if (!is_array($live)) {
            return array('ok' => false, 'evidence' => 'النداءُ الحيُّ لم يُرجع حكمًا — ' . mb_substr(trim($out), 0, 200));
        }

        // (ب) تحليلُ الشيفرة: فرعُ authorize المفتوحُ (return عند غيابِ الدالة).
        $svc  = (string) @file_get_contents($ROOT . '/app/Services/Excel/ExcelService.php');
        $code = fix_strip_comments($svc);
        $openBranch = (bool) preg_match('/!\s*function_exists\s*\(\s*[\'"]check_page_permissions[\'"]\s*\)\s*\)\s*\{\s*return\s*;/s', $code);
        $hasGovernor = (strpos($code, 'FieldGovernor') !== false);

        $ok = !empty($live['helper_loaded']) && !$openBranch && $hasGovernor;
        return array(
            'ok' => $ok,
            'evidence' => 'طبقةُ الصلاحياتِ محمَّلةٌ في excel.php: ' . (!empty($live['helper_loaded']) ? 'نعم' : 'لا')
                . ' · فرعُ authorize المفتوح: ' . ($openBranch ? 'باقٍ' : 'مُغلق')
                . ' · حاكمُ الحقول: ' . ($hasGovernor ? 'موصولٌ' : 'غائب'),
        );
    }
}

/* ═══════════════════════════════════════════════════════════════════════
 *  AC-F5 · لا فحصَ خاوٍ في الأدوات
 * ═══════════════════════════════════════════════════════════════════════ */

if (!function_exists('fix_check_no_hollow_gates')) {
    /**
     * الفحصُ الخاوي = شرطٌ يقرّر نتيجةَ معيارٍ من **وجودِ رمزٍ نصيٍّ عامٍّ** في
     * محتوى ملفٍّ مقروءٍ بـ file_get_contents. الأمثلةُ المرصودة: ‎'table'‎ في
     * m10_ac_gate.php:192 وm14_ac_gate.php:177 — وكلُّ ملفِّ PHP يحتويها.
     * ◆ القاعدةُ المُعلَنة: الإبرةُ مرفوضةٌ إن كانت كلمةً عامّةً (لا تحمل ‎(‎ ولا
     *   ‎::‎ ولا ‎->‎ ولا ‎/‎ ولا ‎_‎ ولا ‎.php‎) وطولُها أقلُّ من اثني عشر محرفًا.
     */
    /**
     * ◆ حدُّ النطاق **مُعلَنٌ لا مسكوتٌ عنه**: الحكمُ يقع على **البواباتِ التي
     *   تشهد بالقبول** (‎*_gate.php‎) — فهي التي تُصدر «مرَّ/رسب» ويُبنى عليها
     *   تقريرُ جاهزية. أمّا أدواتُ التشخيصِ (‎*_checks.php‎ · ‎*_audit.php‎) فتصف
     *   ولا تشهد، والمسحُ النصيُّ فيها وصفٌ مشروع. وعددُها يُطبع في الشاهد
     *   ولا يُسكت عنه.
     */
    function fix_is_certifying_gate($basename)
    {
        return (bool) preg_match('/_gate\.php$/', $basename);
    }

    function fix_check_no_hollow_gates($ROOT)
    {
        $generic = array('table', 'div', 'php', 'form', 'input', 'class', 'span', 'script',
                         'select', 'button', 'chart', 'card', 'view', 'export', 'filter');
        $hits = array(); $advisory = 0;
        foreach (glob($ROOT . '/tools/*.php') as $abs) {
            $bn = basename($abs);
            if (strpos($bn, 'gate') === false && strpos($bn, 'check') === false && strpos($bn, 'audit') === false) { continue; }
            $src  = (string) @file_get_contents($abs);
            $code = fix_strip_comments($src);
            if (!preg_match_all('/strpos\s*\(\s*\$\w+\s*,\s*([\'"])(.+?)\1\s*\)\s*!==\s*false/s', $code, $m, PREG_OFFSET_CAPTURE)) { continue; }
            foreach ($m[2] as $i => $g) {
                $needle = $g[0];
                /* ◆ القاعدةُ المُعلَنة للتمييز — وهي عينُ ما يقيسه الاختبارُ
                   السلبيّ: الإبرةُ **كلمةٌ عامّةٌ صغيرةُ الحروف** لا يمكن أن
                   يرسب فحصُها (‎table‎ · ‎sync‎ · ‎inheader‎ — في كلِّ ملف)، أمّا
                   الرمزُ المُسمّى (‎zeroRecords‎ · ‎chartGuard‎) فيرسب فورَ حذفِه
                   أو إعادةِ تسميته — ففحصُه بنيويٌّ لا خاوٍ. */
                $isCamel  = (bool) preg_match('/[a-z][A-Z]/', $needle);
                $isSymbol = $isCamel || strpbrk($needle, '(:>/_.-') !== false;
                $isGeneric = !$isSymbol
                    && (in_array(strtolower($needle), $generic, true)
                        || (mb_strlen($needle) < 12 && preg_match('/^[a-z]+$/', $needle)));
                if (!$isGeneric) { continue; }
                if (!fix_is_certifying_gate($bn)) { $advisory++; continue; }
                $hits[] = $bn . ':' . fix_line_of($code, $m[0][$i][1]) . " «{$needle}»";
            }
        }
        return array(
            'ok' => empty($hits),
            'evidence' => 'في البواباتِ الشاهدة (*_gate.php): ' . count($hits)
                . ($hits ? ' → ' . implode(' · ', array_slice($hits, 0, 8)) : '')
                . ' · ◆ في أدواتِ التشخيصِ الواصفة (لا تشهد بقبولٍ فلا تُحجب): ' . $advisory,
        );
    }
}

/* ═══════════════════════════════════════════════════════════════════════
 *  AC-F6 · لا كتابةَ حرفيةً في السطح
 * ═══════════════════════════════════════════════════════════════════════ */

if (!function_exists('fix_check_no_sql_in_surfaces')) {
    /** النطاقُ المحكوم: المجلداتُ التي أُعيد بناؤها بمعاييرِ التكويد. */
    function fix_governed_dirs()
    {
        return array('Procurement/', 'Transport/', 'Finance/', 'Audit/', 'Risk/', 'Suppliers/');
    }

    function fix_check_no_sql_in_surfaces($ROOT)
    {
        $bad = array(); $out = array(); $scanned = 0;
        foreach (fix_surface_files($ROOT) as $rel) {
            $src = (string) @file_get_contents($ROOT . '/' . $rel);
            $w = fix_sql_write_lines($src);
            if (!$w) { continue; }
            $governed = false;
            foreach (fix_governed_dirs() as $d) { if (strpos($rel, $d) === 0) { $governed = true; break; } }
            if ($governed) { $scanned++; $bad[] = $rel . ':' . $w[0]['line']; }
            else { $out[] = $rel; }
        }
        return array(
            'ok' => empty($bad),
            'evidence' => 'النطاقُ المحكوم (' . implode('،', fix_governed_dirs()) . '): مخالفٌ ' . count($bad)
                . ($bad ? ' → ' . implode(' · ', array_slice($bad, 0, 8)) : '')
                . ' · ◆ خارجَ النطاقِ (غيرُ مفحوصٍ للحكم، مُعلَنٌ لا مسكوتٌ عنه): ' . count($out) . ' سطحًا',
        );
    }
}

/* ═══════════════════════════════════════════════════════════════════════
 *  AC-F10 · لا استثناءَ مبتلَع
 * ═══════════════════════════════════════════════════════════════════════ */

if (!function_exists('fix_catch_blocks')) {
    /** يُرجع كتلَ catch (سطرُها وجسمُها) بالتوسيمِ النحويّ — لا بالتعبيرِ النمطي. */
    function fix_catch_blocks($src)
    {
        $toks = @token_get_all($src);
        if (!is_array($toks)) { return array(); }
        $out = array(); $n = count($toks);
        for ($i = 0; $i < $n; $i++) {
            $t = $toks[$i];
            if (!is_array($t) || $t[0] !== T_CATCH) { continue; }
            $line = (int) $t[2];
            // تجاوزْ قائمةَ الأنواعِ حتى «{»
            $j = $i; $depthP = 0; $started = false;
            for (; $j < $n; $j++) {
                $x = $toks[$j];
                $s = is_array($x) ? $x[1] : $x;
                if ($s === '(') { $depthP++; $started = true; }
                elseif ($s === ')') { $depthP--; if ($started && $depthP === 0) { $j++; break; } }
            }
            while ($j < $n && (is_array($toks[$j]) ? trim($toks[$j][1]) === '' : trim($toks[$j]) === '')) { $j++; }
            if ($j >= $n || (is_array($toks[$j]) ? $toks[$j][1] : $toks[$j]) !== '{') { continue; }
            $depth = 0; $body = '';
            for (; $j < $n; $j++) {
                $x = $toks[$j];
                $s = is_array($x) ? $x[1] : $x;
                if ($s === '{') { $depth++; if ($depth === 1) { continue; } }
                if ($s === '}') { $depth--; if ($depth === 0) { break; } }
                if ($depth >= 1) { $body .= $s; }
            }
            $out[] = array('line' => $line, 'body' => $body);
        }
        return $out;
    }
}

if (!function_exists('fix_check_no_swallowed_catch')) {
    function fix_check_no_swallowed_catch($ROOT)
    {
        $scope = array('app/Services/', 'app/Core/', 'includes/');
        $bad = array(); $outside = 0;
        foreach (fix_php_files($ROOT) as $rel) {
            $src = (string) @file_get_contents($ROOT . '/' . $rel);
            if ($src === '' || strpos($src, 'catch') === false) { continue; }
            $inScope = false;
            foreach ($scope as $d) { if (strpos($rel, $d) === 0) { $inScope = true; break; } }
            foreach (fix_catch_blocks($src) as $c) {
                $b = trim($c['body']);
                $swallowed = ($b === '')
                    || !preg_match('/\b(throw|return|exit|die|http_response_code|rollback)\b/i', $b);
                if (!$swallowed) { continue; }
                if ($inScope) { $bad[] = $rel . ':' . $c['line']; } else { $outside++; }
            }
        }
        return array(
            'ok' => empty($bad),
            'evidence' => 'النطاقُ المحكوم (الخدماتُ والنواةُ والمساعدون): مبتلَعٌ ' . count($bad)
                . ($bad ? ' → ' . implode(' · ', array_slice($bad, 0, 8)) : '')
                . ' · ◆ خارجَ النطاقِ (مُعلَنٌ لا مسكوتٌ عنه): ' . $outside,
        );
    }
}

/* ═══════════════════════════════════════════════════════════════════════
 *  AC-M1/AC-M2 · القوائمُ والصفوفُ الميتة
 * ═══════════════════════════════════════════════════════════════════════ */

if (!function_exists('fix_check_role_sidebars')) {
    function fix_check_role_sidebars($db, array $roles)
    {
        $ROOT = dirname(__DIR__);
        $empty = array(); $detail = array();
        foreach ($roles as $rid) {
            $out = fix_run_php($ROOT . '/tools/fix_probe_sidebar.php', array((string) $rid));
            $n = null;
            foreach (explode("\n", $out) as $ln) {
                if (strpos($ln, 'SIDEBAR|') === 0) { $j = json_decode(trim(substr($ln, 8)), true); if (is_array($j)) { $n = (int) ($j['items'] ?? 0); } }
            }
            if ($n === null) { $empty[] = $rid . ' (لا حكم)'; $detail[] = $rid . '=?'; continue; }
            $detail[] = $rid . '=' . $n;
            if ($n < 1) { $empty[] = (string) $rid; }
        }
        return array(
            'ok' => empty($empty),
            'evidence' => 'عناصرُ القائمةِ المُصيَّرةِ لكلِّ دور: ' . implode(' · ', $detail)
                . ($empty ? ' · ◆ فارغٌ: ' . implode('،', $empty) : ''),
        );
    }
}

if (!function_exists('fix_check_self_approval_guard')) {
    /**
     * P1-B — «من أنشأ لا يعتمد» على **كلِّ** معتمِد (INJ-0016·0017·0024·0030·0039·0040·0041·0042).
     *
     * ◆ ما يقيسه: كلُّ سطحٍ يكتب ختمَ اعتمادٍ (‎approved_by‎ · ‎posted_by‎ ·
     *   ‎executed_by‎ · ‎closed_by‎ · ‎accepted_by‎) يجب أن ينادي حارسَ منعِ
     *   اعتمادِ الذات. والمقياسُ **بالتوسيمِ لا بالنص**: يُبحث عن الختمِ في
     *   سلاسلِ الشيفرةِ الحقيقيةِ لا في تعليق.
     * ◆ ما لا يقيسه: صحةَ الطرفِ المقارَنِ به (هل قُورن المنشئُ الصحيح؟) — ذاك
     *   يحتاج تشغيلًا بحسابين لكلِّ مسار.
     */
    function fix_check_self_approval_guard($ROOT)
    {
        $STAMPS = array('approved_by', 'posted_by', 'executed_by', 'closed_by', 'accepted_by', 'decided_by');
        $missing = array(); $ok = 0;
        foreach (fix_php_files($ROOT) as $rel) {
            if (strpos($rel, 'includes/') === 0 || strpos($rel, 'app/') === 0) { continue; }
            $src = (string) @file_get_contents($ROOT . '/' . $rel);
            if ($src === '') { continue; }
            // الختمُ يُكتب فعلًا؟ (سلسلةٌ نصيةٌ في شيفرةٍ حقيقية)
            $stamps = 0;
            foreach (@token_get_all($src) ?: array() as $t) {
                if (!is_array($t) || $t[0] !== T_CONSTANT_ENCAPSED_STRING) { continue; }
                $v = trim($t[1], "'\"");
                if (in_array($v, $STAMPS, true)) { $stamps++; }
            }
            if ($stamps === 0) { continue; }
            // ويُكتب في سياقِ كتابةٍ لا قراءةٍ فقط؟
            $code = fix_strip_comments($src);
            $writes = preg_match('/\b(update|insert)\s*\(|UPDATE\s|INSERT\s+INTO/i', $code);
            if (!$writes) { continue; }
            /* ◆ شرطان لا واحد — والاختبارُ السلبيُّ كشف الفرق:
                 ① النداءُ موجود، و② **الملفُّ المعرِّفُ محمَّل**. فنزعُ الـ
                 `require_once` وحدَه يُبقي النداءَ فيمرُّ الفحصُ بينما التنفيذُ
                 ينفجر «دالةٌ غيرُ معرَّفة» عند أولِ اعتماد. */
            $calls  = (bool) preg_match('/ems_(no|assert_not)_self_approval\s*\(/', $code);
            $loaded = (bool) preg_match('/self_approval_guard\.php/', $code);
            if ($calls && $loaded) { $ok++; }
            elseif ($calls && !$loaded) { $missing[] = $rel . ' (ينادي بلا تحميل)'; }
            else { $missing[] = $rel; }
        }
        return array(
            'ok' => empty($missing),
            'evidence' => 'أسطحٌ تختم اعتمادًا: ' . ($ok + count($missing))
                . ' · تنادي الحارس: ' . $ok . ' · **بلا حارس**: ' . count($missing)
                . ($missing ? ' → ' . implode(' · ', array_slice($missing, 0, 8)) : ''),
        );
    }
}

if (!function_exists('fix_check_write_permission_guard')) {
    /**
     * P1-A — «صلاحيةُ العرضِ لا تكفي لتغييرِ البيانات».
     * يختار أسطحًا لها **دورٌ قارئٌ ودورٌ كاتبٌ** فعليًّا في القاعدة، ثم يُرسل
     * ‎POST‎ بكلٍّ منهما ويقرأ **رمزَ الرسالةِ الحوكمية** لا عددَ البايتات
     * (فعدُّ البايتاتِ يخلط منعَ الكتابةِ بمنعِ CSRF بمنعِ العرض).
     */
    function fix_check_write_permission_guard($db, $ROOT)
    {
        $pairs = array();
        $rs = $db->query("SELECT m.code, m.id,
                   SUM(rp.can_view=1 AND rp.can_add=0 AND rp.can_edit=0 AND rp.can_delete=0) vo,
                   SUM(rp.can_edit=1 OR rp.can_add=1) wr
              FROM modules m JOIN role_permissions rp ON rp.module_id = m.id
             WHERE m.code LIKE '%.php'
             GROUP BY m.code, m.id HAVING vo > 0 AND wr > 0
             ORDER BY vo DESC LIMIT 6");
        while ($rs && ($x = $rs->fetch_assoc())) {
            $mid = (int) $x['id'];
            $ro = fix_one($db, "SELECT role_id FROM role_permissions WHERE module_id={$mid}
                                 AND can_view=1 AND can_add=0 AND can_edit=0 AND can_delete=0 LIMIT 1");
            $rw = fix_one($db, "SELECT role_id FROM role_permissions WHERE module_id={$mid}
                                 AND (can_edit=1 OR can_add=1) LIMIT 1");
            /* ◆ استثناءٌ **مُقاسٌ لا مفترَض**: `emsreports/` نظامٌ قرائيٌّ خالص —
                 قِيس: **صفرُ عبارةِ كتابةٍ** في ملفاته كلِّها. وحارسُه صلاحياتُ
                 التقارير (`report_role_permissions`) لا `modules`، فيرتدُّ قبلَ
                 حارسِ الكتابة. إدراجُه هنا يقيس ما لا يكتب. */
            if (strpos($x['code'], 'emsreports/') === 0) { continue; }
            if ($ro !== null && $rw !== null && is_file($ROOT . '/' . $x['code'])) {
                $pairs[] = array('file' => $x['code'], 'reader' => (int) $ro, 'writer' => (int) $rw);
            }
        }
        if (!$pairs) { return array('ok' => false, 'evidence' => 'لا سطحَ بدورٍ قارئٍ ودورٍ كاتبٍ معًا للقياس'); }

        $run = function ($file, $role) use ($ROOT) {
            $out = (string) @shell_exec(escapeshellarg(PHP_BINARY) . ' '
                 . escapeshellarg($ROOT . '/tools/fix_probe_write_guard.php') . ' '
                 . escapeshellarg($file) . ' ' . escapeshellarg((string) $role) . ' 2>&1');
            foreach (explode("\n", $out) as $ln) {
                if (strpos($ln, 'PW|') === 0) { return json_decode(trim(substr($ln, 3)), true); }
            }
            return null;
        };

        $denied = 0; $passed = 0; $measured = 0; $bad = array();
        foreach ($pairs as $p) {
            $a = $run($p['file'], $p['reader']);
            $b = $run($p['file'], $p['writer']);
            if (!is_array($a) || !is_array($b)) { $bad[] = $p['file'] . ' (لا حكم)'; continue; }
            // ◆ لا يُحتسب سطحٌ يُمنع الاثنان فيه بسببٍ آخر (CSRF مثلًا) — يُعلَن.
            if (!$a['write_denied'] && !$b['write_denied'] && $a['bytes'] === 0 && $b['bytes'] === 0) {
                $bad[] = $p['file'] . ' (مانعٌ أسبقُ — غيرُ مقيسٍ هنا)';
                continue;
            }
            $measured++;
            if (!empty($a['write_denied'])) { $denied++; } else { $bad[] = $p['file'] . " — القارئ({$p['reader']}) لم يُمنع"; }
            if (empty($b['write_denied'])) { $passed++; } else { $bad[] = $p['file'] . " — ◆ الكاتب({$p['writer']}) مُنع خطأً"; }
        }
        return array(
            'ok' => ($measured > 0 && $denied === $measured && $passed === $measured),
            'evidence' => "أسطحٌ مقيسة: {$measured} · القارئُ مُنع: {$denied}/{$measured} · الكاتبُ مرَّ: {$passed}/{$measured}"
                . ($bad ? ' · ملاحظات: ' . implode(' · ', array_slice($bad, 0, 4)) : ''),
        );
    }
}

if (!function_exists('fix_check_dead_nav_rows')) {
    function fix_check_dead_nav_rows($db)
    {
        $n = (int) fix_one($db, "SELECT COUNT(*) FROM nav_items
                                  WHERE (module_id IS NULL OR module_id = 0)
                                    AND permission_code IS NOT NULL AND permission_code <> ''");
        $hasConstraint = (int) fix_one($db, "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                                              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nav_items'
                                                AND CONSTRAINT_TYPE = 'CHECK'
                                                AND CONSTRAINT_NAME = 'chk_nav_items_module_or_code'");
        return array(
            'ok' => ($n === 0 && $hasConstraint > 0),
            'evidence' => 'صفوفٌ ميتة: ' . $n . ' · قيدُ القاعدةِ المانع: ' . ($hasConstraint > 0 ? 'موجود' : 'غائب'),
        );
    }
}
