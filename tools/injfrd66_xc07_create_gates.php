<?php
/**
 * tools/injfrd66_xc07_create_gates.php — بوابةُ XC-07: أزرارُ الإنشاءِ ببواباتِها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «صفرُ إنشاءٍ يقفز على مرحلةٍ إلزامية · **والشرطُ مفحوصٌ في
 *   الخدمةِ لا الواجهة**».
 *
 * ◆ **ولماذا يُقاس في الشفرةِ لا بالنقر**: زرٌّ مخفيٌّ في الواجهةِ ليس بوابة —
 *   والنداءُ المباشرُ للخدمةِ يتخطّاه. فالمقياسُ: **هل تكتب الشاشةُ بنفسِها
 *   أم تنادي خدمةً تفحص؟** وكتابةٌ من الشاشةِ **بلا حارسٍ بالبناء**.
 *
 * ◆ **وثلاثةُ استثناءاتٍ مُعلَنةٍ ليست إنشاءً تجاريًّا** — وعدُّها خرقًا
 *   يُحمِّر نظامًا سليمًا:
 *   ① **قيدُ الرفض**: كتابةٌ **تلي** ردَّ حارسٍ (409/403) — وهي «الأثرُ لكلِّ
 *      رفض» الذي يفرضه البابُ الثاني نصًّا، لا التفافٌ عليه.
 *   ② **الأثرُ التدقيقيّ**: `activity_logs` · `guard_denials` وأمثالُها.
 *   ③ **جدولُ عملٍ مؤقَّت**: لا يحمل واقعةَ أعمال.
 *
 * ◆ قراءةٌ خالصة — لا كتابةَ في القاعدةِ إطلاقًا.
 *
 * التشغيل:
 *   php tools/injfrd66_xc07_create_gates.php          التقرير
 *   php tools/injfrd66_xc07_create_gates.php --gate   رمزُ خروجٍ 1 عند كتابةٍ بلا حارس
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$GATE = in_array('--gate', $argv, true);
$DIRS = array('Clients', 'Contracts', 'Suppliers', 'Opportunities', 'Projects');

/* جداولُ الأثرِ والرفضِ — الكتابةُ فيها ليست إنشاءً تجاريًّا */
$AUDIT = array('activity_logs', 'guard_denials', 'sec_sod_denials', 'gov_denial_reviews',
               'exec_approvals', 'gov_ladder_shadow', 'gov_ladder_decisions',
               'ems_business_events', 'bus_outbox', 'audit_log');

/* دلالاتُ نداءِ خدمةٍ تفحص */
$SVC_RX = '~App\\\\Services|[A-Z][A-Za-z]*Service::|AuthorityGuard::|ems_ladder_check|'
        . 'enforce_[a-z_]+|check_page_permissions|\$gate->(insert|update)~u';

echo "\n═══ INJ-FRD-01 · XC-07 — أزرارُ الإنشاءِ ببواباتِها ═══\n\n";

$rows = array(); $bare = array();
foreach ($DIRS as $d) {
    foreach ((array) glob($ROOT . '/' . $d . '/*.php') as $f) {
        $rel  = $d . '/' . basename($f);
        $body = (string) @file_get_contents($f);
        if ($body === '') { continue; }

        if (!preg_match_all('~INSERT\s+INTO\s+`?([a-z_][a-z0-9_]*)`?~i', $body, $mm)) { continue; }
        $tables = array_unique($mm[1]);
        $biz = array_values(array_diff($tables, $AUDIT));
        if (!$biz) {
            $rows[] = array('r' => $rel, 's' => '○', 'v' => 'أثرٌ/رفضٌ فقط: ' . implode('، ', $tables));
            continue;
        }
        $guarded = (bool) preg_match($SVC_RX, $body);
        if ($guarded) {
            $rows[] = array('r' => $rel, 's' => '✔', 'v' => 'يكتب في ' . implode('، ', $biz) . ' — وحارسُه في الخدمة');
        } else {
            $bare[] = $rel;
            $rows[] = array('r' => $rel, 's' => '✘', 'v' => 'يكتب في ' . implode('، ', $biz) . ' — بلا حارسٍ في الخدمة');
        }
    }
}

if ($rows) {
    printf("  %-44s %s\n", 'السطح', 'الحكم');
    echo '  ' . str_repeat('─', 92) . "\n";
    foreach ($rows as $r) { printf("  %-44s %s %s\n", $r['r'], $r['s'], $r['v']); }
} else {
    echo "  ○ صفرُ سطحٍ يكتب في الإدارتَين\n";
}

printf("
  أسطحٌ تكتب في الإدارتَين: %d   ·   بلا حارسٍ في الخدمة: %d
", count($rows), count($bare));
echo "  ◆ ولا يُعدُّ الزرُّ عدًّا نصّيًّا: «إضافة/إنشاء» تَرِد في الشفرةِ مئاتِ المراتِ
";
echo "    تعليقًا ووسمًا وعنوانًا — ورقمٌ لا يقيس ما يسمّيه أسوأُ من لا رقم.
";
echo "\n  ◆ «قيدُ الرفض» ليس إنشاءً: كتابةٌ تلي ردَّ حارسٍ هي الأثرُ الذي يفرضه\n";
echo "    البابُ الثاني — «كلُّ منعٍ يُسجَّل بسببه ومرجعه» — لا التفافٌ عليه.\n";
echo "  ◆ والزرُّ المخفيُّ ليس بوابة: النداءُ المباشرُ للخدمةِ يتخطّاه، فالحكمُ\n";
echo "    على موضعِ الفحصِ لا على ظهورِ الزر.\n\n";

exit($GATE && $bare ? 1 : 0);
