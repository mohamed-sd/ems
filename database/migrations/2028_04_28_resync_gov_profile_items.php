<?php
/**
 * 2028_04_28_resync_gov_profile_items.php — إعادةُ مزامنةِ قوالبِ السلطةِ مع المنحِ القائم
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ بنيويٌّ لا في دورٍ واحد**: `get_module_permissions()` تُطبّق
 *   `GOV-AUTH-01` — «المغطّى بقالبٍ نافذٍ يُحكَم بقالبِه حصرًا: لا شاشةَ خارجَ
 *   القالب». فأيُّ شاشةٍ أُضيفت إلى `role_permissions` **بعدَ** بذرِ القوالبِ
 *   (2027-06-13/16) تُمنَع رغمَ ظهورِها في السايدبار — لأنّ السايدبارَ يقرأ
 *   `role_permissions` الخامَّ والحارسَ يقرأ القالب.
 *
 * ◆ **والعلاجُ إعادةُ البذرِ لا تعديلُ الشيفرة**: الجملةُ هنا **هي جملةُ
 *   `2027_06_16_govauth01_partial_switch.php` حرفًا** — مصدرٌ واحدٌ لا ثانٍ
 *   يتفرَّق عنه — مضافًا إليها قصرُها على القوالبِ **النافذة**.
 *
 * ⛔ **و`INSERT IGNORE` لا يتجاهل شيئًا بلا مفتاحٍ فريد**: بدونِ
 *   `uq_item(profile_id,item_kind,item_ref)` يُكرِّر الصفوفَ بدل تخطّيها —
 *   فالهجرةُ **تفحص وجودَه وترفض التشغيلَ عند غيابِه** (fail-closed) بدل أن
 *   تُفسِد الجدولَ صامتًا.
 *
 * ⛔ **ولا تُعدَّل صفوفٌ قائمةٌ ولا تُحذف**: إضافةُ المفقودِ فقط. ولا يُمَسُّ
 *   `gov_role_profiles` ولا `gov_authority_grants` ولا `role_permissions`
 *   ولا `modules` ولا `nav_items`.
 *
 * ◆ **والعكسُ دقيقٌ لا يدويّ**: `item_id` تسلسليٌّ، فتُكتب علامةُ الماءِ
 *   (أقصى معرِّفٍ قبلَ الإدراج) في ملفٍ بجانبِ الهجرة، ويحذف العكسُ ما فوقَها
 *   حصرًا. فلا يُمحى صفٌّ سبق هذه الجولة.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
$t0 = microtime(true);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail: " . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };

/* ═══ ⓪ حارسُ المفتاحِ الفريد — بلا نجاحِه لا تُنفَّذ الهجرة ═══════════════ */
$hasUq = false;
$ix = $conn->query('SHOW INDEX FROM gov_profile_items');
$keys = array();
while ($ix && ($x = $ix->fetch_assoc())) {
    if ((int) $x['Non_unique'] === 0) { $keys[$x['Key_name']][(int) $x['Seq_in_index']] = $x['Column_name']; }
}
foreach ($keys as $cols) { ksort($cols); if (implode(',', $cols) === 'profile_id,item_kind,item_ref') { $hasUq = true; } }
if (!$hasUq) {
    exit("✘ رُفضت الهجرة: لا مفتاحَ فريدًا على (profile_id,item_kind,item_ref).\n"
       . "  و`INSERT IGNORE` بلا هذا المفتاحِ **يُكرِّر** الصفوفَ بدل تخطّيها.\n");
}
echo "▐ ⓪ المفتاحُ الفريدُ قائمٌ — `INSERT IGNORE` يتجاهل المكرَّرَ بحقّ\n";

/* ═══ ① القياسُ قبل ═══════════════════════════════════════════════════════ */
$GAP_SQL = "SELECT seeded.profile_id, p.profile_code,
                   CAST(SUBSTRING_INDEX(seeded.seeded_from, ':', -1) AS UNSIGNED) role_id,
                   COUNT(DISTINCT m.code) gap
              FROM (SELECT DISTINCT profile_id, seeded_from FROM gov_profile_items
                     WHERE item_kind = 'screen' AND seeded_from LIKE 'role_permissions:%') seeded
              JOIN gov_role_profiles p ON p.profile_id = seeded.profile_id AND p.state = 'active'
              JOIN role_permissions rp ON rp.role_id = SUBSTRING_INDEX(seeded.seeded_from, ':', -1) AND rp.can_view = 1
              JOIN modules m ON m.id = rp.module_id
             WHERE NOT EXISTS (SELECT 1 FROM gov_profile_items g
                                WHERE g.profile_id = seeded.profile_id
                                  AND g.item_kind = 'screen' AND g.item_ref = m.code)
             GROUP BY seeded.profile_id, p.profile_code, role_id
             ORDER BY gap DESC";

echo "\n▐ ① الفجوةُ قبلَ الإصلاح — شاشاتٌ في المنحِ وليست في القالب\n";
$before = array(); $gapBefore = 0;
$r = $conn->query($GAP_SQL);
while ($r && ($x = $r->fetch_assoc())) {
    $before[] = $x; $gapBefore += (int) $x['gap'];
    printf("   %-4s %-10s دور %-4s ⇒ %d\n", $x['profile_id'], $x['profile_code'], $x['role_id'], $x['gap']);
}
if (!$before) { echo "   (لا فجوة)\n"; }
$itemsBefore = (int) $one('SELECT COUNT(*) FROM gov_profile_items');
printf("   قوالبُ بفجوة: %d · مجموعُ الفجوة: %d · بنودُ الجدولِ قبلًا: %d\n",
    count($before), $gapBefore, $itemsBefore);

/* ═══ ② علامةُ الماء — يحذف العكسُ ما فوقَها حصرًا ═══════════════════════
   ⛔ **ولا تُدهَس عند إعادةِ التشغيل**: الهجرةُ مُعادةُ التشغيلِ بطبعِها، فلو
   كُتبت العلامةُ في كلِّ مرّةٍ لصارت في الثانيةِ «أقصى معرِّفٍ **بعدَ** الإدراجِ
   الأوّل» — فيعجز العكسُ عن حذفِ ما أضافته الجولةُ الأولى ويصير التراجعُ
   وهمًا يُعلَن نجاحًا. فتُقرأ العلامةُ المحفوظةُ إن وُجدت وتبقى **علامةَ أوّلِ
   تطبيقٍ** حتمًا. */
$stampFile = __DIR__ . '/2028_04_28_resync_gov_profile_items.watermark.json';
$prev = is_file($stampFile) ? json_decode((string) file_get_contents($stampFile), true) : null;
$firstRun = !(is_array($prev) && isset($prev['watermark']));
$watermark = $firstRun
    ? (int) $one('SELECT COALESCE(MAX(item_id), 0) FROM gov_profile_items')
    : (int) $prev['watermark'];
printf("\n▐ ② علامةُ الماء: %d %s\n", $watermark,
    $firstRun ? '(أوّلُ تطبيقٍ — أقصى معرِّفٍ قبلَ الإدراج)' : '(محفوظةٌ من أوّلِ تطبيقٍ — لا تُدهَس)');

/* ═══ ③ الإدراج — جملةُ 2027_06_16 حرفًا، مقصورةً على النافذ ═══════════════ */
$conn->query(
    "INSERT IGNORE INTO gov_profile_items
        (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
     SELECT 0, seeded.profile_id, 'screen', m.code, rp.can_view, rp.can_add, rp.can_edit, rp.can_delete,
            seeded.seeded_from
       FROM (SELECT DISTINCT profile_id, seeded_from FROM gov_profile_items
              WHERE item_kind = 'screen' AND seeded_from LIKE 'role_permissions:%') seeded
       JOIN gov_role_profiles p ON p.profile_id = seeded.profile_id AND p.state = 'active'
       JOIN role_permissions rp ON rp.role_id = SUBSTRING_INDEX(seeded.seeded_from, ':', -1) AND rp.can_view = 1
       JOIN modules m ON m.id = rp.module_id");
if ($conn->errno) { exit("✘ فشل الإدراج: " . $conn->error . "\n"); }
$added = (int) $one('SELECT COUNT(*) FROM gov_profile_items') - $itemsBefore;
echo "\n▐ ③ أُضيفت: {$added} بندًا\n";

$r = $conn->query("SELECT profile_id, COUNT(*) n FROM gov_profile_items
                    WHERE item_id > {$watermark} GROUP BY profile_id ORDER BY n DESC");
while ($r && ($x = $r->fetch_assoc())) {
    $code = $one("SELECT profile_code FROM gov_role_profiles WHERE profile_id = " . (int) $x['profile_id']);
    printf("   قالب %-4s %-10s ⇒ %s بندًا\n", $x['profile_id'], (string) $code, $x['n']);
}

/* ═══ ④ القياسُ بعد — الحكمُ بالقياسِ لا بالنيّة ══════════════════════════ */
echo "\n▐ ④ الفجوةُ بعدَ الإصلاح\n";
$gapAfter = 0; $rowsAfter = 0;
$r = $conn->query($GAP_SQL);
while ($r && ($x = $r->fetch_assoc())) {
    $rowsAfter++; $gapAfter += (int) $x['gap'];
    printf("   ✘ %-4s %-10s دور %-4s ⇒ %d\n", $x['profile_id'], $x['profile_code'], $x['role_id'], $x['gap']);
}
printf("   قوالبُ بفجوة: %d · مجموعُ الفجوة: %d\n", $rowsAfter, $gapAfter);

/* ⛔ ولا صفَّ مكرَّرٌ — الحارسُ يقيس ما ضمنه المفتاحُ الفريدُ ولا يفترضه. */
$dups = (int) $one("SELECT COUNT(*) FROM (SELECT profile_id, item_kind, item_ref
                      FROM gov_profile_items GROUP BY profile_id, item_kind, item_ref
                     HAVING COUNT(*) > 1) d");
printf("   صفوفٌ مكرَّرة: %d %s\n", $dups, $dups === 0 ? 'PASS' : '** FAIL **');

/* ═══ ⑤ كتابةُ علامةِ الماءِ ليقرأها العكس ═══════════════════════════════ */
$stamp = array(
    'migration'    => basename(__FILE__),
    'watermark'    => $watermark,                    /* علامةُ **أوّلِ** تطبيقٍ حتمًا */
    'added'        => (int) ($firstRun ? 0 : ($prev['added'] ?? 0)) + $added,   /* تراكميّ */
    'gap_before'   => $firstRun ? $gapBefore : (int) ($prev['gap_before'] ?? $gapBefore),
    'gap_after'    => $gapAfter,
    'first_run_at' => $firstRun ? (gmdate('Y-m-d H:i:s') . ' UTC') : (string) ($prev['first_run_at'] ?? ''),
    'last_run_at'  => gmdate('Y-m-d H:i:s') . ' UTC',
    'runs'         => (int) ($firstRun ? 0 : ($prev['runs'] ?? 1)) + 1,
);
file_put_contents($stampFile, json_encode($stamp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
echo "\n▐ ⑤ كُتبت علامةُ الماءِ في " . basename($stampFile) . "\n";

$ok = ($gapAfter === 0 && $dups === 0);
echo "\n" . ($ok ? "✔ المزامنةُ تامّةٌ — صفرُ فجوةٍ وصفرُ تكرار\n"
               : "✘ ناقصٌ: فجوةٌ {$gapAfter} · تكرارٌ {$dups}\n");

if (function_exists('ems_migration_recorded')) {
    ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
} else {
    echo "◆ شُغِّلت خارجَ المُشغِّل — قيِّدها بـ`php database/migrate.php mark-applied " . basename(__FILE__) . "`\n";
}
