<?php
/**
 * 2027_02_27 — سبعةُ أدوارٍ بلا «رئيسية»: منحةُ مساحةِ العملِ الناقصة
 * ═══════════════════════════════════════════════════════════════════════════
 * **المقيسُ**: عنصرُ بابِ `HOME` في `nav_items` **حاضرٌ وحيٌّ لكلِّ دور**، ويشير
 * إلى `main/my_workspace.php` (الوحدة **228**). و`getUnifiedNavItems`
 * (`includes/unified_nav.php:76`) ترشِّح كلَّ عنصرٍ بـ:
 *     `permission_code IS NULL OR EXISTS(role_permissions … can_view = 1)`
 * فالعنصرُ يحمل رمزَ صلاحيةٍ **ولا صفَّ منحةٍ** لسبعةِ أدوار
 * (9 · 27 · 28 · 29 · 30 · 34 · 35) — فيُحجب بابُ الرئيسيةِ عنها كلَّها،
 * **فلا لوحةَ هبوطٍ لصاحبِ الدور**. وستةٌ وعشرون دورًا غيرُها تحمل `can_view=1`
 * على الوحدةِ نفسِها — فالنقصُ **سهوُ منحٍ** لا قرارُ حجب.
 *
 * وهو عينُ ما ينهى عنه حكمُ المالك المسجَّل: **«الممنوحُ يُرى»** — وبابُ الرئيسيةِ
 * ليس شاشةً حساسةً بل مساحةُ عملِ صاحبِ الدورِ نفسِه.
 *
 * والمنحةُ تُنسَخ **من أدوارِها الشقيقةِ المقيسة** لا من تقدير، وتنتهي بشاهدٍ
 * يُشغِّل `getUnifiedNavItems` فعلًا لكلِّ دورٍ نشط.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$fail = array();
$one  = function ($sql) use ($db) { $r = $db->query($sql); return $r ? $r->fetch_row()[0] : null; };

/* ── ① الوحداتُ التي يقصدها بابُ الرئيسيةِ — تُقاس ولا تُفترض ───────────── */
$r = $db->query("SELECT DISTINCT module_id FROM nav_items
                  WHERE door = 'HOME' AND active = 1 AND module_id IS NOT NULL");
$mods = array();
while ($r && ($x = $r->fetch_row())) { $mods[] = (int) $x[0]; }
if (empty($mods)) { echo "\n✅ لا عنصرَ HOME بوحدةٍ — لا عمل.\n"; exit(0); }
echo '── ① وحداتُ بابِ الرئيسية: ' . implode(' · ', $mods) . "\n";

/* ── ② الأدوارُ التي تحمل عنصرًا حيًّا ولا منحةَ له ─────────────────────── */
$modIn = implode(',', $mods);
$r = $db->query("SELECT n.role_id, n.module_id, r.name, r.status
                   FROM nav_items n
                   JOIN roles r ON r.id = n.role_id
                  WHERE n.door = 'HOME' AND n.active = 1 AND n.module_id IN ({$modIn})
                    AND NOT EXISTS (SELECT 1 FROM role_permissions p
                                     WHERE p.role_id = n.role_id AND p.module_id = n.module_id
                                       AND p.can_view = 1)
                  ORDER BY n.role_id");
$need = array();
while ($r && ($x = $r->fetch_assoc())) { $need[] = $x; }
echo '── ② أدوارٌ بلا منحةِ رئيسية: ' . count($need) . "\n";
foreach ($need as $x) {
    echo "     دور {$x['role_id']} ({$x['name']}) · وحدة {$x['module_id']} · حالةُ الدور="
       . (string) $x['status'] . "\n";
}
if (empty($need)) { echo "\n✅ كلُّ دورٍ يملك منحةَ رئيسيتِه — لا عمل.\n"; exit(0); }

/* ── ③ القدوةُ: بم يملكها الأدوارُ الأخرى؟ ────────────────────────────── */
$peer = $db->query("SELECT can_view, can_add, can_edit, can_delete, COUNT(*) n
                      FROM role_permissions WHERE module_id IN ({$modIn})
                     GROUP BY can_view, can_add, can_edit, can_delete
                     ORDER BY n DESC LIMIT 1");
$shape = $peer ? $peer->fetch_assoc() : null;
if (!$shape) { fwrite(STDERR, "لا قدوةَ تُقاس — لا يُمنَح إذنٌ بالتخمين\n"); exit(1); }
echo "── ③ الشكلُ الأغلبُ في الأدوارِ الأخرى: view={$shape['can_view']}"
   . " add={$shape['can_add']} edit={$shape['can_edit']} del={$shape['can_delete']}"
   . " (في {$shape['n']} دورًا)\n";
if ((int) $shape['can_view'] !== 1) { fwrite(STDERR, "القدوةُ بلا can_view — لا يُمنَح\n"); exit(1); }

/* ── ④ المنحُ — مُتحمِّلٌ للتكرار ─────────────────────────────────────────── */
$st = $db->prepare('INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE can_view = VALUES(can_view)');
if (!$st) { fwrite(STDERR, 'prepare: ' . $db->error . "\n"); exit(1); }
$granted = 0;
foreach ($need as $x) {
    $rid = (int) $x['role_id']; $mid = (int) $x['module_id'];
    $v = (int) $shape['can_view']; $a = (int) $shape['can_add'];
    $e = (int) $shape['can_edit']; $dl = (int) $shape['can_delete'];
    $st->bind_param('iiiiii', $rid, $mid, $v, $a, $e, $dl);
    if (!$st->execute()) { $fail[] = 'دور ' . $rid . ': ' . mb_substr($st->error, 0, 60); continue; }
    $granted++;
}
$st->close();
echo "── ④ مُنح {$granted} دورًا منحةَ رئيسيتِه\n";

/* ── ⑤ الشاهدُ المُشغَّل: `getUnifiedNavItems` تُنادى فعلًا لكلِّ دورٍ نشط ── */
echo "── ⑤ الشاهدُ المُشغَّل\n";
$left = (int) $one("SELECT COUNT(*) FROM nav_items n
                     WHERE n.door = 'HOME' AND n.active = 1 AND n.module_id IS NOT NULL
                       AND NOT EXISTS (SELECT 1 FROM role_permissions p
                                        WHERE p.role_id = n.role_id AND p.module_id = n.module_id
                                          AND p.can_view = 1)");
echo "     أدوارٌ بلا منحةِ رئيسيةٍ بعد: {$left} " . ($left === 0 ? "✔\n" : "✘\n");
if ($left !== 0) { $fail[] = "بقي {$left} دورًا بلا منحة"; }

$ROOT = dirname(__DIR__, 2);
$probeDir = $ROOT . '/storage';
if (!is_dir($probeDir)) { @mkdir($probeDir, 0777, true); }
$pf = $probeDir . '/mig0227_nav_probe_' . getmypid() . '.php';
file_put_contents($pf, "<?php\n"
    . "error_reporting(0);\n"
    . 'require ' . var_export($ROOT . '/config.php', true) . ";\n"
    . "while (ob_get_level() > 0) { ob_end_clean(); }\n"
    . 'require_once ' . var_export($ROOT . '/includes/unified_nav.php', true) . ";\n"
    . "\$c = \$GLOBALS['conn'];\n"
    . "\$bad = array();\n"
    . "\$q = \$c->query(\"SELECT id FROM roles WHERE (status='1' OR status=1) AND id <> -1 ORDER BY id\");\n"
    . "while (\$r = \$q->fetch_row()) {\n"
    . "    \$rid = (int) \$r[0];\n"
    . "    \$items = getUnifiedNavItems(\$c, \$rid);\n"
    . "    \$hasHome = false;\n"
    . "    foreach (\$items as \$it) { if (\$it['door'] === 'HOME') { \$hasHome = true; break; } }\n"
    . "    \$live = (int) \$c->query(\"SELECT COUNT(*) FROM nav_items WHERE role_id={\$rid} AND door='HOME' AND active=1\")->fetch_row()[0];\n"
    . "    if (\$live > 0 && !\$hasHome) { \$bad[] = \$rid; }\n"
    . "}\n"
    . "echo empty(\$bad) ? 'ALLHOME' : ('NOHOME:' . implode(',', \$bad));\n");
$out = trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($pf) . ' 2>&1'));
@unlink($pf);
$allHome = (substr($out, -7) === 'ALLHOME');
echo '     getUnifiedNavItems تُعطي بابَ رئيسيةٍ لكلِّ دورٍ نشط: '
   . ($allHome ? "نعم ✔\n" : 'لا ✘ — ' . mb_substr($out, -140) . "\n");
if (!$allHome) { $fail[] = 'العارضُ ما زال يحجب الرئيسيةَ عن دورٍ أو أكثر'; }

echo "\n" . (empty($fail)
    ? "✅ لكلِّ دورٍ نشطٍ بابُ رئيسيةٍ **يُصيَّر فعلًا** — «الممنوحُ يُرى» وما كان محجوبًا سهوًا صار مرئيًّا.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
