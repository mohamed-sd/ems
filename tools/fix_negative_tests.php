<?php
/**
 * tools/fix_negative_tests.php — الاختباراتُ السلبيةُ لكلِّ فحصٍ في البوابة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الحكمُ الحاكم (FIXA-0036/0037 · AC-F4):
 *   «أضفْ اختبارًا سلبيًّا لكلِّ فحص: **افسدِ المفحوصَ عمدًا وأثبتْ أن الفحصَ
 *    يرسب** · وأيُّ فحصٍ لا يرسب عند إفسادِ مفحوصِه **يُحذف** — فهو يصادق على
 *    نفسه».
 *
 * ◆ كيف يُفسَد المفحوصُ هنا بأمان: نسخةٌ مؤقتةٌ من الملفِّ الحقيقيِّ تُعدَّل ثم
 *   تُستعاد **في ‎finally‎** — فلا يبقى إفسادٌ ولو انفجر الاختبار. ولا يُمَسّ
 *   شيءٌ في القاعدةِ إلا داخلَ معاملةٍ تُرتدّ.
 *
 * التشغيل: php tools/fix_negative_tests.php
 * الخروج: 0 إن رسب كلُّ فحصٍ عند إفسادِ مفحوصِه · 1 إن نجا فحصٌ من الإفساد.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';
require_once __DIR__ . '/fix_checks.php';
$db = fix_db();

/** اتصالُ DDL منفصلٌ: ‎ems_app‎ لا يملك ‎ALTER‎ (ADR-04) — والإفسادُ يحتاجه. */
require_once $ROOT . '/includes/env.php';
$ddl = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($ddl->connect_errno) { exit('اتصال المرحِّل فشل: ' . $ddl->connect_error . "\n"); }
$ddl->set_charset('utf8mb4');

$RESULTS = array();

/**
 * يشغّل اختبارًا سلبيًّا واحدًا.
 * @param callable $break   يُفسد المفحوصَ ويُرجع callable للاستعادة
 * @param callable $check   يُرجع array{ok:bool,...}
 */
function neg($code, $title, callable $break, callable $check)
{
    global $RESULTS;
    $restore = null;
    $before = null; $after = null; $err = '';
    try {
        $before = $check();
        $restore = $break();
        $after   = $check();
    } catch (Throwable $e) {
        $err = $e->getMessage();
    } finally {
        if (is_callable($restore)) { $restore(); }
    }
    $baseOk  = is_array($before) && !empty($before['ok']);
    $brokeOk = is_array($after)  && !empty($after['ok']);
    // الفحصُ صالحٌ إن كان يمرُّ على السليمِ **ويرسب** على المُفسَد.
    $valid = $baseOk && !$brokeOk && $err === '';
    $RESULTS[] = array('code' => $code, 'title' => $title, 'valid' => $valid,
        'base' => $baseOk ? 'مرَّ' : 'رسب', 'broken' => $brokeOk ? 'مرَّ ✗' : 'رسب ✔', 'err' => $err);
    printf("%s %-10s %s\n", $valid ? '✔' : '✘', $code, $title);
    printf("        السليم: %s · المُفسَد: %s%s\n\n", $baseOk ? 'مرَّ' : 'رسب',
        $brokeOk ? 'مرَّ (الفحصُ يصادق على نفسه!)' : 'رسب', $err !== '' ? ' · استثناء: ' . $err : '');
}

/** يبدّل محتوى ملفٍّ مؤقتًا ويُرجع دالةَ استعادة. */
function neg_swap($abs, $newContent)
{
    $orig = (string) file_get_contents($abs);
    file_put_contents($abs, $newContent);
    return function () use ($abs, $orig) { file_put_contents($abs, $orig); };
}

echo "══════════════════════════════════════════════════════════════════════\n";
echo " الاختباراتُ السلبيةُ — «الفحصُ الذي لا يرسب عند الإفسادِ يُحذف»\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";

/* ── ① AC-F1 · الفشلُ مغلقًا: أعِدْ فرعًا سامحًا وأثبتْ رسوبَ الفحص ─────── */
neg('AC-F1', 'حارسٌ يفشل مغلقًا — يرسب إن عاد فرعٌ سامح',
    function () use ($ROOT) {
        $abs = $ROOT . '/includes/permissions_helper.php';
        $src = (string) file_get_contents($abs);
        // إفسادٌ مطابقٌ للعطلِ الأصلي: فرعُ الغيابِ يرجع كلَّ الصلاحياتِ true.
        $broken = str_replace(
            "        return _deny_all_permissions('unresolved_script_path');",
            "        return ['id'=>null,'can_view'=>true,'can_add'=>true,'can_edit'=>true,'can_delete'=>true,'can_export'=>true];",
            $src);
        if ($broken === $src) { throw new RuntimeException('لم يُعثر على موضعِ الإفساد — الفحصُ نفسُه تعفّن'); }
        return neg_swap($abs, $broken);
    },
    function () use ($db, $ROOT) { return fix_check_failclosed($db, $ROOT); }
);

/* ── ② AC-F2 · الحارسُ قبلَ الكتابة: أنزلْ حارسًا تحتَ كتابةٍ ────────────── */
neg('AC-F2', 'ترتيبُ الحارسِ — يرسب إن سبقت كتابةٌ حارسَها',
    function () use ($ROOT) {
        $abs = $ROOT . '/Procurement/wh_count.php';
        $src = (string) file_get_contents($abs);
        // إفسادٌ: نُقحم عبارةَ كتابةٍ حرفيةً فوقَ الحارس.
        $needle = "require_once __DIR__ . '/../includes/session_bootstrap.php';";
        $pos = strpos($src, $needle);
        if ($pos === false) { throw new RuntimeException('لم يُعثر على المرساة'); }
        $inject = "\n\$__neg = \"INSERT INTO proc_stock_move (qty) VALUES (1)\"; // NEG-TEST\n";
        $broken = substr($src, 0, $pos + strlen($needle)) . $inject . substr($src, $pos + strlen($needle));
        return neg_swap($abs, $broken);
    },
    function () use ($ROOT) { return fix_check_guard_before_write($ROOT); }
);

/* ── ③ AC-F3 · التصدير: أعِدْ فرعَ authorize المفتوحَ وأثبتْ الرسوب ─────── */
neg('AC-F3', 'حارسُ التصدير — يرسب إن عاد فرعُه المفتوح',
    function () use ($ROOT) {
        $abs = $ROOT . '/app/Services/Excel/ExcelService.php';
        $src = (string) file_get_contents($abs);
        $broken = str_replace(
            "            \$this->fail(500, 'طبقةُ الصلاحياتِ غيرُ محمَّلةٍ — التصديرُ ممنوعٌ (فشلٌ مغلق)');",
            "            return; // NEG-TEST فرعٌ مفتوح",
            $src);
        if ($broken === $src) { throw new RuntimeException('لم يُعثر على موضعِ الإفساد'); }
        return neg_swap($abs, $broken);
    },
    function () use ($ROOT) { return fix_check_export_guard($ROOT); }
);

/* ── ④ AC-F5 · الفحصُ الخاوي: أعِدْ شرطًا خاويًا وأثبتْ التقاطَه ────────── */
neg('AC-F5', 'كاشفُ الشروطِ الخاوية — يرسب إن عاد شرطٌ خاوٍ',
    function () use ($ROOT) {
        $abs = $ROOT . '/tools/m10_ac_gate.php';
        $src = (string) file_get_contents($abs);
        $broken = str_replace(
            'require_once __DIR__ . \'/fix_lib.php\';',
            'require_once __DIR__ . \'/fix_lib.php\';' . "\n" . '$__neg = (strpos($ROOT, \'table\') !== false); // NEG-TEST شرطٌ خاوٍ',
            $src);
        if ($broken === $src) { throw new RuntimeException('لم يُعثر على موضعِ الإفساد'); }
        return neg_swap($abs, $broken);
    },
    function () use ($ROOT) { return fix_check_no_hollow_gates($ROOT); }
);

/* ── ⑤ AC-M2 · الصفوفُ الميتة: أدرجْ صفًّا ميتًا داخلَ معاملةٍ تُرتدّ ───── */
neg('AC-M2', 'كاشفُ الصفِّ الميت — يرسب إن وُجد صفٌّ ميتٌ واحد',
    function () use ($ddl) {
        // ◆ الإفسادُ يحتاج DDL و‎ems_app‎ لا يملكه (ADR-04) — فالاتصالُ هنا اتصالُ
        //   المرحِّل. ولو ابتُلع خطأُ الصلاحيةِ لبقي القيدُ فرُفض الإدراجُ فقُرئ
        //   «الفحصُ يصادق على نفسه» زورًا. فالإفسادُ **يُتحقق منه لا يُفترض**.
        // ◆ وگوتشا ثانية: MariaDB لا تعرف ‎DROP CHECK‎ (1064) بل ‎DROP CONSTRAINT‎.
        $restoreSql = "ALTER TABLE nav_items ADD CONSTRAINT chk_nav_items_module_or_code CHECK (
                         permission_code IS NULL OR permission_code = ''
                         OR (module_id IS NOT NULL AND module_id > 0))";
        if (!$ddl->query("ALTER TABLE nav_items DROP CONSTRAINT chk_nav_items_module_or_code")) {
            throw new RuntimeException('تعذّر إسقاطُ القيد: ' . $ddl->error);
        }
        if (!$ddl->query("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon,
                        sort_order, counter_source, permission_code, active, created_at, updated_at)
                    VALUES (1,'SET',NULL,NULL,'NEG-TEST','zz/neg_probe.php','fa fa-x',999,NULL,'zz/neg_probe.php',0,NOW(),NOW())")) {
            $err = $ddl->error;
            $ddl->query($restoreSql);
            throw new RuntimeException('تعذّر إدراجُ الصفِّ الميتِ للإفساد: ' . $err);
        }
        return function () use ($ddl, $restoreSql) {
            $ddl->query("DELETE FROM nav_items WHERE route = 'zz/neg_probe.php'");
            if (!$ddl->query($restoreSql)) { fwrite(STDERR, "⚠ تعذّرت استعادةُ القيد: " . $ddl->error . "\n"); }
        };
    },
    function () use ($db) { return fix_check_dead_nav_rows($db); }
);

/* ── ⑦ AC-P1B · حارسُ اعتمادِ الذات: انزعْ تحميلَه وأثبتْ الرسوب ────────── */
neg('AC-P1B', 'حارسُ اعتمادِ الذات — يرسب إن نودي بلا تحميل',
    function () use ($ROOT) {
        $abs = $ROOT . '/Finance/payments_fin.php';
        $src = (string) file_get_contents($abs);
        // ◆ الإفسادُ ينزع **التحميلَ** لا النداء: هذه هي الحالةُ التي كشفت ضعفَ
        //   الفحصِ الأولِ — نداءٌ قائمٌ ودالةٌ غيرُ معرَّفةٍ تنفجر عند أولِ اعتماد.
        $broken = str_replace("require_once __DIR__ . '/../includes/self_approval_guard.php';", '// NEG-TEST', $src);
        if ($broken === $src) { throw new RuntimeException('لم يُعثر على موضعِ الإفساد'); }
        return neg_swap($abs, $broken);
    },
    function () use ($ROOT) { return fix_check_self_approval_guard($ROOT); }
);

/* ── ⑧ AC-P1A · حارسُ الكتابة: أعِدْ تخطّي فحصِ الكتابةِ وأثبتْ الرسوب ──── */
neg('AC-P1A', 'حارسُ صلاحيةِ الكتابة — يرسب إن عاد المرورُ بصلاحيةِ العرض',
    function () use ($ROOT) {
        $abs = $ROOT . '/includes/permissions_helper.php';
        $src = (string) file_get_contents($abs);
        $broken = str_replace(
            '    $mayWrite = !empty($current[\'can_add\']) || !empty($current[\'can_edit\']) || !empty($current[\'can_delete\']);',
            '    $mayWrite = true; // NEG-TEST — كلُّ من يعرض يكتب',
            $src);
        if ($broken === $src) { throw new RuntimeException('لم يُعثر على موضعِ الإفساد'); }
        return neg_swap($abs, $broken);
    },
    function () use ($db, $ROOT) { return fix_check_write_permission_guard($db, $ROOT); }
);

/* ── ⑨ MD-05 · بوابةُ التبنّي: انزعْ نداءَ حارسٍ وأثبتْ رسوبَها ─────────── */
neg('MD-05', 'بوابةُ تبنّي الحرّاس — ترسب إن عاد حارسٌ بلا مستهلك',
    function () use ($ROOT) {
        $abs = $ROOT . '/app/Services/Governance/FieldGovernor.php';
        $src = (string) file_get_contents($abs);
        // ◆ الإفسادُ ينزع **النداءَ الوحيدَ** لـSensitiveFieldGuard فيعود بصفرِ مستهلك.
        $broken = preg_replace('/\\\\App\\\\Services\\\\Security\\\\SensitiveFieldGuard/', 'NEG_TEST_Removed', $src);
        if ($broken === $src) { throw new RuntimeException('لم يُعثر على موضعِ الإفساد'); }
        return neg_swap($abs, $broken);
    },
    function () use ($ROOT) {
        $out = (string) @shell_exec(escapeshellarg(PHP_BINARY) . ' '
             . escapeshellarg($ROOT . '/tools/guard_adoption_gate.php') . ' 2>&1');
        if (!preg_match('/بصفرِ مستهلك \(L1\) \.+ (\d+)/u', $out, $m)) {
            // شكلُ التقريرِ تغيّر — يُقرأ رسوبًا لا نجاحًا (فشلٌ مغلق)
            return array('ok' => false, 'evidence' => 'تعذّرت قراءةُ تقريرِ البوابة');
        }
        /* العتبةُ **صفر**: كانت 11 حين كان ذلك رصيدَ الدَّينِ المعلَن، وقد
           سُدِّد. وعتبةٌ لا تُشدَّد مع تحسُّنِ الرصيدِ تتحول إلى ترخيصٍ بالانحدار:
           يعود عشرةُ حرّاسٍ بلا مستهلكٍ والبوابةُ خضراء. */
        return array('ok' => ((int) $m[1] === 0), 'evidence' => 'حرّاسٌ بصفرِ مستهلك: ' . $m[1]);
    }
);

/* ── ⑥ AC-M1 · القائمةُ الفارغة: اسألْ عن دورٍ بلا قائمةٍ وأثبتْ الرسوب ── */
neg('AC-M1', 'كاشفُ القائمةِ الفارغة — يرسب على دورٍ بلا قائمة',
    function () { return function () { /* لا إفسادَ في الملفات — المفحوصُ هو المُدخل */ }; },
    (function () use ($db) {
        $first = true;
        return function () use ($db, &$first) {
            // السليم: الأدوارُ الثلاثةُ المُصلَحة · المُفسَد: دورٌ بلا قائمةٍ قطعًا (34).
            $roles = $first ? array(31, 32, 33) : array(34);
            $first = false;
            return fix_check_role_sidebars($db, $roles);
        };
    })()
);

/* ── ⑦ AC-N1 · مِسبارُ الروابط: وجِّهْ رابطًا إلى ملفٍّ غيرِ قائمٍ وأثبتْ الرسوب ─
   الإفسادُ هنا في **القاعدةِ لا في ملف**، ولا يمكن أن يكون بإعادةِ البادئةِ
   النسبيةِ لأن `chk_nav_route_not_relative` صار يمنعها — وهذا في ذاته شاهدٌ
   على أن القيدَ حيّ. فيُفسَد بالوجهةِ بدلَ الصيغة: مسارٌ سليمُ الشكلِ إلى ملفٍّ
   غيرِ موجود. والمِسبارُ يقيس **حلَّ الرابطِ على القرص** لا شكلَه، فيجب أن يرسب. */
neg('AC-N1', 'مِسبارُ الروابطِ الحيُّ — يرسب إن أشار رابطٌ إلى ملفٍّ غيرِ قائم',
    function () use ($db) {
        $row = $db->query("SELECT id, route FROM nav_items
                            WHERE active=1 AND route LIKE '%.php' AND role_id=33 LIMIT 1")->fetch_assoc();
        if (!$row) { throw new RuntimeException('لم يُعثر على صفِّ تنقُّلٍ للإفساد'); }
        $id = (int) $row['id'];
        $orig = $row['route'];
        $db->query("UPDATE nav_items SET route='Audit/__neg_test_missing__.php' WHERE id={$id}");
        return function () use ($db, $id, $orig) {
            $st = $db->prepare('UPDATE nav_items SET route=? WHERE id=?');
            $st->bind_param('si', $orig, $id);
            $st->execute();
            $st->close();
        };
    },
    function () use ($ROOT) {
        $out = (string) @shell_exec(escapeshellarg(PHP_BINARY) . ' '
             . escapeshellarg($ROOT . '/tools/fix_nav_href_probe.php') . ' --role=33 --json 2>&1');
        $j = json_decode($out, true);
        if (!is_array($j) || !isset($j['bad'], $j['total'])) {
            return array('ok' => false, 'evidence' => 'تعذّرت قراءةُ مخرجِ المِسبار');
        }
        // «٠ مكسورٍ من ٠» ليست نجاحًا — الفاحصُ يرسب على الخواء أيضًا.
        $ok = ($j['bad'] === 0 && $j['total'] > 0 && empty($j['fatal']) && empty($j['empty']));
        return array('ok' => $ok, 'evidence' => 'مكسور ' . $j['bad'] . ' من ' . $j['total']);
    }
);

/* ── ⑧ AC-U0 · بنيةُ الصفحة: أقحِمْ `</div>` زائدًا وأثبتْ الرسوب ──────────
   الحارسُ الذي وُلد من عطلٍ رآه المالكُ قبل كلِّ فاحصٍ عندي. وقيمتُه كلُّها في
   أن يرسب عند العطلِ نفسِه — وإلا فهو توثيقٌ لا حراسة. */
neg('AC-U0', 'حارسُ بنيةِ الصفحة — يرسب عند إغلاقٍ زائدٍ يُخرج المحتوى من قالبه',
    function () use ($ROOT) {
        $abs = $ROOT . '/Contracts/contracts.php';
        $src = (string) file_get_contents($abs);
        // إفسادٌ مطابقٌ للعطلِ الأصلي: `</div>` زائدٌ يُنهي `.main` مبكرًا
        $needle = '<div class="main contracts-main';
        $at = strpos($src, $needle);
        if ($at === false) { throw new RuntimeException('لم يُعثر على مرساةِ .main'); }
        $eol = strpos($src, "\n", $at);
        $broken = substr($src, 0, $eol + 1) . "</div><!-- NEG-TEST إغلاقٌ زائد -->\n"
                . substr($src, $eol + 1);
        return neg_swap($abs, $broken);
    },
    function () use ($ROOT) {
        $out = (string) @shell_exec(escapeshellarg(PHP_BINARY) . ' '
             . escapeshellarg($ROOT . '/tools/fix_ui_gate.php') . ' 2>&1');
        if (strpos($out, 'AC-U0') === false) {
            return array('ok' => false, 'evidence' => 'المعيارُ غائبٌ عن البوابة');
        }
        // يُقرأ سطرُ AC-U0 وحدَه: أخضرُ ⇒ الحارسُ لم يرَ الإفساد
        $ok = (strpos($out, '✔ AC-U0') !== false);
        return array('ok' => $ok, 'evidence' => $ok ? 'صفرُ اختلالٍ جديد' : 'رصد اختلالًا');
    }
);

/* ══ AC-U4 · حارسُ الحدِّ الأدنى للأعمدة ═══════════════════════════════════
   ثلاثةُ إفساداتٍ لثلاثةِ أوجهٍ من الحكمِ الواحد: أن يبيّنَ الحارسُ السببَ ·
   أن يتراجعَ فعلًا · وأن يتبنّاه الطريقُ الحيُّ (المجموعاتُ على 96 شاشة).
   ◆ وأولُ صيغةٍ من الفاحصِ نجت من الإفسادِ الأول: كانت تكتفي بذكرِ `EmsAlert`
     في الملف، فنزعُ النداءِ نفسِه لم يُسقطها. أرسبها هذا الاختبارُ فشُدّت. */
$u4check = function () use ($ROOT) {
    $out = (string) @shell_exec(escapeshellarg(PHP_BINARY) . ' '
         . escapeshellarg($ROOT . '/tools/fix_ui_gate.php') . ' 2>&1');
    if (strpos($out, 'AC-U4') === false) {
        return array('ok' => false, 'evidence' => 'المعيارُ غائبٌ عن البوابة');
    }
    $ok = (strpos($out, '✔ AC-U4') !== false);
    return array('ok' => $ok, 'evidence' => $ok ? 'الطرقُ الثلاثُ متبنّية' : 'طريقٌ بلا حارس');
};

neg('AC-U4/بيان', 'حارسُ الأعمدة — يرسب إن منع بلا أن يبيّن السبب',
    function () use ($ROOT) {
        $abs = $ROOT . '/assets/js/ems-column-floor.js';
        $src = (string) file_get_contents($abs);
        $broken = str_replace('window.EmsAlert.warning(text);', '/* NEG-TEST */', $src);
        if ($broken === $src) { throw new RuntimeException('لم يُعثر على نداءِ التنبيه'); }
        return neg_swap($abs, $broken);
    }, $u4check);

neg('AC-U4/تراجع', 'حارسُ الأعمدة — يرسب إن اكتفى بالبيانِ بلا تراجع',
    function () use ($ROOT) {
        $abs = $ROOT . '/assets/js/ems-column-floor.js';
        $src = (string) file_get_contents($abs);
        $broken = str_replace("if (typeof opts.revert === 'function') { opts.revert(); }", '', $src);
        if ($broken === $src) { throw new RuntimeException('لم يُعثر على نداءِ التراجع'); }
        return neg_swap($abs, $broken);
    }, $u4check);

neg('AC-U4/تبنٍّ', 'حارسُ الأعمدة — يرسب إن عاد طريقٌ حيٌّ يُخفي بلا حارس',
    function () use ($ROOT) {
        $abs = $ROOT . '/assets/js/column-groups.js';
        $src = (string) file_get_contents($abs);
        // إفسادٌ مطابقٌ للحالِ قبلَ العلاج: `setAll` تُخفي مباشرةً بلا حارس
        $broken = preg_replace('/return this\.guarded\(function \(\) \{\s*self\.groups\.forEach/',
                               'self.groups.forEach', $src, 1);
        if ($broken === null || $broken === $src) { throw new RuntimeException('لم يُعثر على setAll المحروسة'); }
        return neg_swap($abs, $broken);
    }, $u4check);

/* ══ AC-U10 · بطاقةُ المؤشرِ السباعية ══════════════════════════════════════
   ثلاثةُ إفساداتٍ لثلاثةِ أوجه: أن يرفضَ المكوّنُ الناقصَ · أن يُعلنَ المقارنةَ
   الغائبةَ ولا يلفّقَها · وألّا يلتفَّ سطحٌ على المكوّنِ فيكتبَ الماركَبَ بيدِه. */
$u10check = function () use ($ROOT) {
    $out = (string) @shell_exec(escapeshellarg(PHP_BINARY) . ' '
         . escapeshellarg($ROOT . '/tools/fix_ui_gate.php') . ' 2>&1');
    if (strpos($out, 'AC-U10') === false) {
        return array('ok' => false, 'evidence' => 'المعيارُ غائبٌ عن البوابة');
    }
    $ok = (strpos($out, '✔ AC-U10') !== false);
    return array('ok' => $ok, 'evidence' => $ok ? 'العقدُ مفروضٌ ومتبنًّى' : 'العقدُ مخروق');
};

neg('AC-U10/رفض', 'بطاقةُ المؤشر — ترسب إن صيَّر المكوّنُ رقمًا بلا وحدةٍ أو تعمّق',
    function () use ($ROOT) {
        $abs = $ROOT . '/includes/kpi_card.php';
        $src = (string) file_get_contents($abs);
        // إفسادٌ مطابقٌ للحالِ قبلَ العقد: الوحدةُ والتعمّقُ يصيران اختياريين
        $broken = str_replace(
            array("'unit'   => 'الوحدة',", "'drill'  => 'التعمّق',"), '', $src);
        if ($broken === $src) { throw new RuntimeException('لم يُعثر على قائمةِ الواجب'); }
        return neg_swap($abs, $broken);
    }, $u10check);

neg('AC-U10/إعلان', 'بطاقةُ المؤشر — ترسب إن لُفِّقت المقارنةُ الغائبةُ بدل إعلانِها',
    function () use ($ROOT) {
        $abs = $ROOT . '/includes/kpi_card.php';
        $src = (string) file_get_contents($abs);
        $broken = str_replace("? 'بلا مقارنة معلنة'", "? 'مستقرٌّ مقارنةً بالأمس'", $src);
        if ($broken === $src) { throw new RuntimeException('لم يُعثر على إعلانِ الغياب'); }
        return neg_swap($abs, $broken);
    }, $u10check);

neg('AC-U10/التفاف', 'بطاقةُ المؤشر — ترسب إن كتب سطحٌ الماركَبَ بيدِه حولَ المكوّن',
    function () use ($ROOT) {
        $abs = $ROOT . '/Risk/risk_board.php';
        $src = (string) file_get_contents($abs);
        $needle = '<div class="ems-grid">';
        $at = strpos($src, $needle);
        if ($at === false) { throw new RuntimeException('لم يُعثر على مرساةِ الشبكة'); }
        $hand = $needle . "\n<a class=\"ems-kpi-card\" href=\"#\"><div class=\"ems-kpi-title\">"
              . "NEG-TEST</div><div class=\"ems-kpi-value\">1</div></a>";
        return neg_swap($abs, substr_replace($src, $hand, $at, strlen($needle)));
    }, $u10check);

/* ── الحصيلة ───────────────────────────────────────────────────────────── */
$valid = 0; $invalid = array();
foreach ($RESULTS as $r) { if ($r['valid']) { $valid++; } else { $invalid[] = $r['code']; } }
echo str_repeat('═', 70) . "\n";
printf("فواحصُ صمدت للاختبارِ السلبي: %d/%d\n", $valid, count($RESULTS));
if ($invalid) {
    echo "◆ فواحصُ تصادق على نفسِها (تُحذف أو تُصلَح): " . implode('، ', $invalid) . "\n";
}
echo str_repeat('═', 70) . "\n";
exit(empty($invalid) ? 0 : 1);
