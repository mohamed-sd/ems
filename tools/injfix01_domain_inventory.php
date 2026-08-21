<?php
/**
 * tools/injfix01_domain_inventory.php
 *   جردُ الكتّابِ والقرّاءِ لمساراتِ المجالاتِ الستة — INJ-FIX-01 · الموجة ج
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الحقلان الرابعُ والخامسُ في مصفوفةِ التوحيد** («الكتّابُ اليوم» و«القرّاءُ
 *   اليوم») هما أصعبُ ما يُجرَد وأخطرُ ما يُغفَل: «أن يُطفأ مسارٌ وله قارئٌ لم
 *   يُعلَم به». فيُجرَد بالمسحِ الشجريِّ لا بالذاكرة.
 *
 * ◆ **والكاتبُ يُميَّز عن القارئِ بالعبارةِ لا بذكرِ الاسم**: `INSERT/UPDATE/
 *   DELETE/REPLACE` متبوعةً باسمِ الجدول. وذكرُ الاسمِ وحدَه قراءةٌ (أو تعليق).
 *
 * ◆ **ولا يُعَدُّ ما ليس إنتاجًا**: تُستثنى `tests/` و`tools/` و`docs/` و
 *   `storage/` — فأداةٌ تقرأ جدولًا ليست قارئًا في المعنى الحاكم، وعدُّها
 *   يُضخِّم القائمةَ فيصير التقاعدُ مستحيلًا بلا سبب.
 *
 * التشغيل: php tools/injfix01_domain_inventory.php [--md=<path>]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }

$SKIP = array('/storage/', '/vendor/', '/.git/', '/docs/', '/tests/', '/tools/', '/node_modules/', '/examples/');

$DOMAINS = array(
    'authority'   => array('label' => 'السلطة',    'paths' => array(
        'gov_authority_grants', 'gov_authority_limits', 'gov_delegations',
        'gov_cap_history', 'v_effective_authority')),
    'approval'    => array('label' => 'الاعتماد',  'paths' => array(
        'approval_requests', 'approval_steps', 'approval_workflow_rules',
        'gov_ladders', 'gov_ladder_steps', 'gov_journey_ladders',
        'v_approval_rules_effective', 'authority_signatures', 'gov_approval_decisions')),
    'permissions' => array('label' => 'الصلاحيات', 'paths' => array(
        'role_permissions', 'gov_profile_items', 'gov_role_profiles')),
    'events'      => array('label' => 'الأحداث',   'paths' => array(
        'ems_business_events', 'fin_financial_events', 'ems_event_consumers',
        'ems_event_deliveries', 'capacity_outbox')),
    'navigation'  => array('label' => 'التنقل',    'paths' => array(
        'nav_items', 'nav_canonical', 'nav_canonical_current', 'link_groups', 'nav_route_group')),
    'closing'     => array('label' => 'الإقفال',   'paths' => array(
        'fin_financial_periods', 'scr_monthly_close', 'fin_closing_items')),
    /* ◆ **دفترُ القيدِ ليس مجالَ توحيدٍ سابعًا** — بل أخطرُ جدولٍ في النظام،
         وGAP-27 يطلب له **كاتبًا واحدًا معتمَدًا وفحصًا يُرسِّب عندَ ظهورِ رابع**.
         فيُجرَد هنا بالأداةِ نفسِها لا بأداةٍ ثانيةٍ لغرضٍ واحد. */
    'journal'     => array('label' => 'دفترُ القيد', 'paths' => array(
        'fin_journal_entries', 'fin_journal_lines')),
);

/* ══ وجودُ المسارِ يُقاس قبلَ عدِّ قرّائه ═════════════════════════════════════
   ◆ **فخٌّ وقع فعلًا**: أولُ تشغيلٍ لهذه الأداةِ أعلن مجالَ الإقفالِ **صفرَ
     قارئٍ وصفرَ كاتبٍ في ثلاثةِ جداول** — وقُرئ ذلك «مسارٌ ميتٌ سهلُ التقاعد».
     والحقيقةُ أن **الأسماءَ الثلاثةَ لا وجودَ لها**: الجدولان الحيّان
     `fin_financial_periods` و`scr_monthly_close`. فاسمٌ خاطئٌ يُنتج صفرًا
     يطابق صفرَ المسارِ الميتِ حرفًا — **ولا يُفرَّق بينهما إلا بفحصِ الوجود**.
   ◆ وكذلك `authority_signatures` (أحدُ مساراتِ الاعتمادِ في التشخيص): مقيسٌ
     **غيرَ موجودٍ في القاعدة** — فلا يُعَدُّ مسارًا يُطفأ بل اسمًا لا مُسمّى له. */
if (!function_exists('injfix_path_exists')) {
    function injfix_path_exists(mysqli $c, $name)
    {
        $st = $c->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $st->bind_param('s', $name);
        $st->execute(); $st->bind_result($n); $st->fetch(); $st->close();
        return (int) $n > 0;
    }
}
require_once $ROOT . '/config.php';

/** ملفاتُ الإنتاجِ الحيةِ مرةً واحدة. */
$files = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') { continue; }
    $p = str_replace('\\', '/', $f->getPathname());
    $bad = false;
    foreach ($SKIP as $s) { if (strpos($p, $s) !== false) { $bad = true; break; } }
    if ($bad) { continue; }
    $files[] = $p;
}

$md = array();
$md[] = '# جردُ الكتّابِ والقرّاءِ — المجالاتُ الستة';
$md[] = '';
$md[] = '> مقامُ المسح: **' . count($files) . '** ملفَّ إنتاجٍ حيّ (بلا tests/tools/docs/storage).';
$md[] = '';

echo "مقامُ المسح: " . count($files) . " ملفَّ إنتاج\n";
foreach ($DOMAINS as $key => $d) {
    echo "\n══ {$d['label']} ══\n";
    $md[] = '## ' . $d['label'];
    $md[] = '';
    $md[] = '| المسار | قرّاء | كتّاب | الكتّابُ بأسمائِهم |';
    $md[] = '|---|---:|---:|---|';
    foreach ($d['paths'] as $t) {
        if (!injfix_path_exists($conn, $t)) {
            printf("  %-30s ⛔ **غيرُ موجودٍ في القاعدة** — لا يُعَدُّ مسارًا يُطفأ\n", $t);
            $md[] = '| `' . $t . '` | ⛔ | ⛔ | **غيرُ موجودٍ في القاعدة** — اسمٌ بلا مُسمًّى، ولا يُقرأ صفرُه «مسارًا ميتًا» |';
            continue;
        }
        $readers = array(); $writers = array();
        /* ══ الكاتبُ بوجهَيه — والاكتفاءُ بأحدِهما يُنقص الجردَ صامتًا ═══════
           ◆ **خطأٌ وقع في أولِ جردٍ وصُحِّح**: كان الكشفُ يطابق **SQL الخام**
             وحدَه (`INSERT INTO x`). و`TenantGate` تكتب بنداءٍ لا بعبارة:
             `$g->insert('fin_journal_entries', …)`. فـ`PostingService` —
             **الكاتبُ المعتمَدُ لأخطرِ جدولٍ في النظام** — لم يظهر كاتبًا
             إطلاقًا، وظهر بدلَه هجراتٌ وبذور.
           ◆ **وأثرُ ذلك أن كلَّ عددِ كتّابٍ في المصفوفةِ كان ناقصًا** — وجردٌ
             ناقصٌ للكتّابِ يجعل التقاعدَ يبدو أسهلَ مما هو، وهو أخطرُ من
             جردٍ زائد. فيُقاس الوجهان معًا. */
        $rxSql  = '/(INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\s+`?' . preg_quote($t, '/') . '`?\b/i';
        $rxGate = '/->\s*(insert|update|delete|upsert|replace)\s*\(\s*[\'"]' . preg_quote($t, '/') . '[\'"]/i';
        foreach ($files as $p) {
            $src = @file_get_contents($p);
            if ($src === false || stripos($src, $t) === false) { continue; }
            $rel = str_replace($ROOT . '/', '', $p);
            $readers[] = $rel;
            if (preg_match($rxSql, $src) || preg_match($rxGate, $src)) { $writers[] = $rel; }
        }
        $nR = count($readers); $nW = count($writers);
        printf("  %-30s قرّاء=%-4s كتّاب=%-3s %s\n", $t, $nR, $nW,
            $nW ? implode(' · ', array_slice($writers, 0, 2)) . ($nW > 2 ? ' …+' . ($nW - 2) : '') : '');
        $md[] = '| `' . $t . '` | ' . $nR . ' | ' . $nW . ' | '
              . ($nW ? '`' . implode('` · `', array_slice($writers, 0, 6)) . '`'
                       . ($nW > 6 ? ' …+' . ($nW - 6) : '') : '—') . ' |';
    }
    $md[] = '';
}

if ($mdOut !== null) {
    file_put_contents($mdOut, implode("\n", $md) . "\n");
    echo "\n✔ كُتب: {$mdOut}\n";
}
