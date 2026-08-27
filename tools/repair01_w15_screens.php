<?php
/**
 * tools/repair01_w15_screens.php — مولِّدُ أسطحِ المرحلةِ الخامسةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولِّدُ ملفّاتٍ لا مولِّدُ ترتيب**: يكتب ملفَّ السطحِ بقالبٍ واحدٍ لكلِّ
 *   أسطحِ الموجة. ⛔ **ولا يكتب بندَ قائمةٍ ولا ترتيبًا** — ذاك من السجلِّ في
 *   `repair01_w15_apply.php` وحدَه.
 *
 * ◆ **وكلُّ سطحٍ هنا `PROJECTION` بلا استثناء** (‏قيدُ المالك §١): القالبُ
 *   **لا يحوي فعلَ كتابةٍ أصلًا** — لا نموذجَ إدخالٍ ولا زرَّ حفظٍ ولا `$_POST`.
 *   فالكتابةُ عند مالكِ الجدولِ وحدَه، وحاجبُ «كتابةٌ من مساحةِ الموجة» يقرأ
 *   هذه الملفّاتِ نفسَها ويسقط على أوّلِ `INSERT` أو `UPDATE` أو `DELETE`.
 *
 * ◆ **والقراءةُ بمرجعٍ حيٍّ عبرَ محرّكِ النطاقِ الواحد**: `w15_rows` تنادي
 *   `ExecProjectionService::read` ومعها `ScopeEngine::visibility` — فالرئيسُ
 *   يرى الشركةَ والنائبُ نطاقَه والموظّفُ صفوفَه، **بالشيفرةِ نفسِها**.
 *   ⛔ **ولا نسخةَ دوريّةً ولا مخزنَ محلّيّ.**
 *
 * ◆ **ونقاءُ لغةِ الواجهةِ شرطُ توليدٍ لا مراجعةٌ لاحقة** (‏قيدُ المالك §٨):
 *   لا اسمَ جدولٍ ولا مفتاحٍ ولا مصطلحٍ تقنيٍّ في نصٍّ مُصيَّر، ولا تشكيلَ،
 *   ولا نقطتَين في اسمِ عنصر.
 *
 * التشغيل: php tools/repair01_w15_screens.php [--force]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$FORCE = in_array('--force', $argv, true);

require_once $ROOT . '/tools/lib/repair01_w15_screens_def.php';
$SCREENS = repair01_w15_screen_defs();

$written = 0; $skipped = 0;
foreach ($SCREENS as $route => $s) {
    $path = $ROOT . '/' . $route;
    if (is_file($path) && !$FORCE) { echo "  ↷ $route قائم\n"; $skipped++; continue; }
    $dir = dirname($path);
    if (!is_dir($dir)) { mkdir($dir, 0777, true); }

    $up = str_repeat('../', substr_count($route, '/'));
    $cards = '';
    foreach ($s['cards'] as $c) {
        switch ($c[0]) {
            case 'count':    $expr = 'count($rows)'; break;
            case 'eq':       $expr = 'ems_w15_count($rows, "' . $c[1] . '", "' . $c[2] . '")'; break;
            case 'distinct': $expr = 'ems_w15_distinct($rows, "' . $c[1] . '")'; break;
            case 'filled':   $expr = 'ems_w15_filled($rows, "' . $c[1] . '")'; break;
            case 'empty':    $expr = 'ems_w15_empty($rows, "' . $c[1] . '")'; break;
            case 'sumf':     $expr = 'ems_w15_num(ems_w15_sumf($rows, "' . $c[1] . '"))'; break;
            default:         $expr = '0';
        }
        $cards .= '        <div class="ems-stat-card"><div class="ems-stat-value"><?= ' . $expr
                . ' ?></div><div class="ems-stat-label">' . $c[3] . "</div></div>\n";
    }
    $head = ''; $body = '';
    foreach ($s['cols'] as $col) {
        $head .= '<th>' . $col[1] . '</th>';
        switch ($col[2]) {
            case 'i': $cell = '(int) $r["' . $col[0] . '"]'; break;
            case 'n': $cell = 'ems_w15_num($r["' . $col[0] . '"])'; break;
            case 's': $cell = 'ems_w15_state((string) $r["' . $col[0] . '"])'; break;
            default:  $cell = 'ems_w15_txt($r["' . $col[0] . '"])';
        }
        $body .= '                    <td><?= ' . $cell . " ?></td>\n";
    }

    /* قيدُ القراءةِ الثابت — يُحقَن في `where` لا يُكتب في نصِّ الصفحة */
    $whereSrc = '';
    if (!empty($s['where'])) {
        $parts = array();
        foreach ($s['where'] as $k => $v) { $parts[] = "'" . $k . "' => '" . $v . "'"; }
        $whereSrc = "'where' => array(" . implode(', ', $parts) . "), ";
    }
    /* أسطحُ المساحةِ الشخصيّةِ تُقيَّد بصاحبِها — الحلقةُ `Record Scope` */
    $selfSrc = '';
    if (!empty($s['self_col'])) {
        $selfSrc = "\n\$opt['where'] = isset(\$opt['where']) ? \$opt['where'] : array();\n"
                 . "\$opt['where']['" . $s['self_col'] . "'] = \$ctx['user_id'];\n";
    }

    $php = "<?php\n"
        . "/**\n"
        . " * " . $route . " — " . $s['title'] . " (RPR-W15)\n"
        . " * ───────────────────────────────────────────────────────────────────────────\n"
        . " * " . $s['note'] . "\n"
        . " *\n"
        . " * ◆ **إسقاطٌ لا مصدر** (‏قيدُ المالك §١): قراءةٌ حيّةٌ من سجلِّ مالكِها\n"
        . " *   **" . $s['owner_ar'] . "** — ⛔ ولا يخزّن هذا السطحُ حقيقةً ولا ينسخها.\n"
        . " *\n"
        . " * ◆ **والرؤيةُ لا تساوي السلطة**: هذا سطحُ قراءةٍ بلا فعلِ كتابة؛ والقرارُ\n"
        . " *   يمرُّ بمحرّكِ الاعتمادِ نفسِه عند مالكِ المستند لا من هنا.\n"
        . " *\n"
        . " * ◆ **والنطاقُ من محرّكٍ واحد**: الرئيسُ يرى الشركةَ والنائبُ نطاقَه\n"
        . " *   والموظّفُ صفوفَه — بالشيفرةِ نفسِها. ⛔ ولا ثلاثةَ أنظمة.\n"
        . " */\n"
        . "require_once __DIR__ . '/" . $up . "includes/session_bootstrap.php';\n"
        . "session_start();\n"
        . "if (!isset(\$_SESSION['user'])) { header('Location: " . $up . "login.php'); exit(); }\n"
        . "include '" . $up . "config.php';\n"
        . "include '" . $up . "includes/permissions_helper.php';\n"
        . "require_once __DIR__ . '/" . $up . "includes/w15_view.php';\n"
        . "\n"
        . "\$ctx = w15_ctx();\n"
        . "\$is_super = \$ctx['is_super'];\n"
        . "if (!\$is_super && \$ctx['company_id'] <= 0) {\n"
        . "    ems_gov_flash_redirect('" . $up . "main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');\n"
        . "    exit();\n"
        . "}\n"
        . "\n"
        . "\$perms = w15_perms(\$conn, '" . $route . "', \$is_super);\n"
        . "if (empty(\$perms['can_view'])) {\n"
        . "    ems_gov_flash_redirect('" . $up . "main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');\n"
        . "    exit();\n"
        . "}\n"
        . "\n"
        . "\$vis = w15_visibility(\$conn, \$ctx);\n"
        . "\$opt = array(" . $whereSrc . "'orderBy' => '" . $s['order'] . "', 'limit' => " . (int) $s['limit']
        . ", 'scope_col' => '" . $s['scope_col'] . "');\n"
        . $selfSrc
        . "\$rows = w15_rows(\$is_super, '" . $s['table'] . "', \$vis, \$opt);\n"
        . "\n"
        . "\$page_title = 'إيكوبيشن | " . $s['title'] . "';\n"
        . "require_once __DIR__ . '/" . $up . "includes/screen_contract.php';\n"
        . "ems_shell_axes(isset(\$perms) ? \$perms : null);\n"
        . "include '" . $up . "inheader.php';\n"
        . "include '" . $up . "insidebar.php';\n"
        . "require_once __DIR__ . '/" . $up . "includes/screen_contract.php'; if (isset(\$conn)) { ems_screen_about_auto(\$conn); }\n"
        . "?>\n"
        . "<div class=\"main ems-unified-page-shell\">\n"
        . "    <?php \$header_title = '" . $s['title'] . "'; \$header_icon = '" . $s['icon'] . "'; \$header_actions = array();\n"
        . "    \$header_back = array('href' => '" . $s['back'][0] . "', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => '" . $s['back'][1] . "');\n"
        . "    include('" . $up . "includes/page_header.php'); ?>\n"
        . "\n"
        . "    <div class=\"ems-stat-cards\">\n"
        . $cards
        . "    </div>\n"
        . "\n"
        . "    <?php require_once __DIR__ . '/" . $up . "includes/ux_components.php';\n"
        . "    echo ems_states_bundle('" . $s['empty'][0] . "', '" . $s['empty'][1] . "'); ?>\n"
        . "\n"
        . "    <div class=\"table-wrap\"><table class=\"data-table\">\n"
        . "        <thead><tr>" . $head . "</tr></thead>\n"
        . "        <tbody>\n"
        . "        <?php if (\$rows): foreach (\$rows as \$r): ?>\n"
        . "            <tr>\n"
        . $body
        . "            </tr>\n"
        . "        <?php endforeach; endif; ?>\n"
        . "        </tbody>\n"
        . "    </table></div>\n"
        . "</div>\n"
        . "</body></html>\n";

    file_put_contents($path, $php);
    echo "  ✔ $route\n";
    $written++;
}
printf("\nأسطحٌ مكتوبة %d · قائمةٌ %d\n", $written, $skipped);
