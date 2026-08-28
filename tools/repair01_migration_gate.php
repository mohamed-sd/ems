<?php
/**
 * tools/repair01_migration_gate.php — بوّابةُ الرسوبِ الفوريّةِ على دفترِ الهجرات
 * ═══════════════════════════════════════════════════════════════════════════
 * **`RPR-02` §٩ · `RPR-03` §٣·١ · والخطوةُ ① في ترتيبَي التنفيذِ كليهما**:
 * «**رسوبٌ صلبٌ على أيِّ هجرةٍ جديدةٍ لا تسجّل نفسَها** — ولا نافذةَ ظلّ، فهجرةٌ
 * لا تستدعي الدفترَ ليس لها حالةٌ مشروعةٌ واحدة».
 *
 * ◆ **والعطبُ ليس في القاعدةِ بل في أنَّ استدعاءَها عُرفٌ لا بوّابة**: الدالّةُ
 *   `ems_migration_recorded()` قائمةٌ في `database/migrations/_ledger.php` منذ
 *   `NF-05` **وتعمل حين تُستدعى** — والقياسُ يقول: **٥٢ ملفًّا يستدعيها وكلُّها
 *   مقيَّدة · وسبعةٌ وخمسون لا يستدعيها وكلُّها خارجَ الدفتر**. فالاستدعاءُ
 *   فاصلٌ تامّ، **وهذه البوّابةُ تجعله شرطًا لا عادة**.
 *
 * ◆ **والمعيارُ المصحَّحُ في `RPR-02` §١**: «إجماليُّ الهجراتِ غيرِ المصالَحةِ =
 *   صفر» — **لا «هجرةٌ جديدةٌ خارجَ الدفتر = صفر»**، فذاك يسمح للسبعِ والخمسين
 *   أن تبقى خارجَه أبدًا.
 *
 * ◆ **وأربعةُ حواجبَ بمقاماتٍ مطبوعة** (البند ⑦ — والمقامُ يُعلَن دائمًا):
 *   `G-MIG-01` كلُّ هجرةِ `.php` تستدعي الدفترَ · `G-MIG-02` لا هجرةَ على القرصِ
 *   خارجَ الدفترِ بلا حكمٍ مُصالِح · `G-MIG-03` لا صفَّ دفترٍ بلا ملفٍّ بلا حكم ·
 *   `G-MIG-04` كلُّ اسمٍ خارجَ العُرفِ في المجلَّدِ مُعلَنٌ بحكمِه.
 *
 * ⛔ **ولا تكتب هذه البوّابةُ شيئًا**: تقيس وتَرسُب. والتسويةُ في أداتِها.
 *
 * التشغيل: php tools/repair01_migration_gate.php [--md]
 * الخروج:  0 أخضرُ كامل · 1 رسوبٌ في حاجبٍ واحدٍ فأكثر
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
define('GATE_VERSION', 'repair01_migration_gate.php v1.0');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$MD  = in_array('--md', $argv, true);
$CMD = 'php tools/repair01_migration_gate.php' . ($MD ? ' --md' : '');

/** عُرفُ التسميةِ المعتمدُ في `database/migrate.php::migrate_scan_files()` نفسِه
 *  — ⛔ **ولا يُعاد تعريفُه هنا بقيمةٍ أخرى**، فتعريفان للعُرفِ عطبٌ لا بوّابة. */
define('MIG_NAME_RE', '/^\d{4}_\d{2}_\d{2}_.+\.(sql|php)$/');
define('MIG_DIR', $ROOT . '/database/migrations');

$rows = function ($sql) use ($conn) {
    $r = @$conn->query($sql); $o = array();
    if ($r) { while ($x = $r->fetch_assoc()) { $o[] = $x; } }
    return $o;
};
$one = function ($sql) use ($conn) { $r = @$conn->query($sql); return $r ? $r->fetch_row()[0] : null; };

/* ═══ المسحُ ══════════════════════════════════════════════════════════════ */
$managed = array();      /* اسمٌ => امتداد */
$unmanaged = array();    /* اسمٌ في المجلَّدِ لا يطابق العُرف */
foreach (scandir(MIG_DIR) as $f) {
    if ($f === '.' || $f === '..' || is_dir(MIG_DIR . '/' . $f)) { continue; }
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    if (!in_array($ext, array('php', 'sql'), true)) { continue; }
    if (preg_match(MIG_NAME_RE, $f)) { $managed[$f] = $ext; } else { $unmanaged[$f] = $ext; }
}
ksort($managed); ksort($unmanaged);

$ledger = array();
foreach ($rows("SELECT filename, status FROM schema_migrations") as $r) { $ledger[$r['filename']] = $r['status']; }

$settled = array();
$hasSettleTbl = (int) $one("SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_migration_settlement'");
if ($hasSettleTbl) {
    foreach ($rows("SELECT filename, kind, ruling, verified FROM gov_migration_settlement") as $r) {
        $settled[$r['filename']] = $r;
    }
}

/* ═══ الحواجبُ الأربعة ════════════════════════════════════════════════════ */
$fail = 0; $lines = array();
$p = function ($s) use (&$lines) { $lines[] = $s; };

/* ── G-MIG-01 · كلُّ هجرةِ `.php` **غيرِ مقيَّدةٍ** تستدعي دفترَها ─────────
   ◆ **والمقامُ غيرُ المقيَّدِ وحدَه — لا كلُّ الملفّات**: فملفٌّ مقيَّدٌ في
     الدفترِ **قد قُيِّد فعلًا** بالمُشغِّلِ أو بالدالّة، ومطالبتُه اليومَ
     باستدعاءٍ تعني **تحريرَ ٢٦٩ ملفًّا مطبَّقًا** — وذلك يغيّر بصمتَها فيُشعل
     كاشفَ التحريرِ في `migrate.php` («ملفٌّ طُبِّق ثمَّ تغيّر محتواه = خطأ»).
     ⛔ **فحاجبٌ يوجب كسرَ حاجبٍ آخرَ ليس حاجبًا.**
   ◆ **والغرضُ يتحقّق كاملًا**: الهجرةُ الجديدةُ التي لا تسجّل نفسَها **لا
     تدخل الدفتر**، فتقع في هذا المقامِ بعينِه وتُرسِب البوّابة. */
$phpMigs = array_keys(array_filter($managed, function ($e) { return $e === 'php'; }));
$phpUnledgered = array_values(array_filter($phpMigs, function ($f) use ($ledger) {
    return !isset($ledger[$f]);
}));
$noCall = array();
foreach ($phpUnledgered as $f) {
    $src = @file_get_contents(MIG_DIR . '/' . $f);
    if ($src === false) { continue; }
    if (strpos($src, 'ems_migration_recorded(') === false) { $noCall[] = $f; }
}
/* والمُسوّى تاريخيًّا يخرج من الحاجبِ **بحكمِه المسجَّلِ وحدَه** — لا بتاريخِه */
$noCallUnsettled = array_values(array_filter($noCall, function ($f) use ($settled) {
    return !isset($settled[$f]);
}));
$g1 = count($noCallUnsettled) === 0;
if (!$g1) { $fail++; }
$p(sprintf('%s **`G-MIG-01`** كلُّ هجرةِ `.php` **غيرِ مقيَّدةٍ** تستدعي '
    . '`ems_migration_recorded()` — المقامُ **%d** غيرِ مقيَّدةٍ من **%d** ملفًّا · '
    . 'بلا استدعاءٍ **%d** · منها بلا حكمٍ مُسوٍّ **%d**',
    $g1 ? '✔' : '✘', count($phpUnledgered), count($phpMigs), count($noCall), count($noCallUnsettled)));
foreach (array_slice($noCallUnsettled, 0, 10) as $f) { $p('    ✘ `' . $f . '` — لا تستدعي الدفترَ ولا حكمَ لها'); }
if (count($noCallUnsettled) > 10) { $p('    … وبقيّةُ ' . (count($noCallUnsettled) - 10)); }

/* ── G-MIG-02 · لا هجرةَ على القرصِ خارجَ الدفترِ بلا حكم ────────────────── */
$diskNotLedger = array();
foreach ($managed as $f => $ext) { if (!isset($ledger[$f])) { $diskNotLedger[] = $f; } }
$dnlUnsettled = array_values(array_filter($diskNotLedger, function ($f) use ($settled) {
    return !isset($settled[$f]) || (int) $settled[$f]['verified'] !== 1;
}));
$g2 = count($dnlUnsettled) === 0;
if (!$g2) { $fail++; }
$p(sprintf('%s **`G-MIG-02`** لا هجرةَ على القرصِ خارجَ الدفتر — المقامُ **%d** '
    . 'هجرةً مُدارةً · خارجَ الدفترِ **%d** · منها بلا حكمٍ متحقَّقٍ **%d**',
    $g2 ? '✔' : '✘', count($managed), count($diskNotLedger), count($dnlUnsettled)));
foreach (array_slice($dnlUnsettled, 0, 10) as $f) {
    $p('    ✘ `' . $f . '` — ' . (isset($settled[$f]) ? 'حكمٌ غيرُ متحقَّق: `' . $settled[$f]['ruling'] . '`' : 'خارجَ الدفترِ بلا حكم'));
}
if (count($dnlUnsettled) > 10) { $p('    … وبقيّةُ ' . (count($dnlUnsettled) - 10)); }

/* ── G-MIG-03 · لا صفَّ دفترٍ بلا ملفٍّ بلا حكم ──────────────────────────── */
$ledgerNotDisk = array();
foreach ($ledger as $f => $st) { if (!isset($managed[$f]) && !isset($unmanaged[$f])) { $ledgerNotDisk[] = $f; } }
$lndUnsettled = array_values(array_filter($ledgerNotDisk, function ($f) use ($settled) {
    return !isset($settled[$f]);
}));
$g3 = count($lndUnsettled) === 0;
if (!$g3) { $fail++; }
$p(sprintf('%s **`G-MIG-03`** لا صفَّ دفترٍ بلا ملفٍّ ولا حكم — المقامُ **%d** صفًّا '
    . '· بلا ملفٍّ **%d** · منها بلا حكمٍ **%d**',
    $g3 ? '✔' : '✘', count($ledger), count($ledgerNotDisk), count($lndUnsettled)));
foreach (array_slice($lndUnsettled, 0, 6) as $f) { $p('    ✘ `' . $f . '` — صفٌّ بلا ملفٍّ ولا حكم'); }
if (count($lndUnsettled) > 6) { $p('    … وبقيّةُ ' . (count($lndUnsettled) - 6)); }

/* ── G-MIG-04 · كلُّ اسمٍ خارجَ العُرفِ مُعلَنٌ بحكمِه ───────────────────── */
$unmUnsettled = array();
foreach (array_keys($unmanaged) as $f) { if (!isset($settled[$f])) { $unmUnsettled[] = $f; } }
$g4 = count($unmUnsettled) === 0;
if (!$g4) { $fail++; }
$p(sprintf('%s **`G-MIG-04`** كلُّ اسمٍ خارجَ عُرفِ التسميةِ مُعلَنٌ بحكمِه — '
    . 'المقامُ **%d** ملفًّا خارجَ العُرفِ · بلا حكمٍ **%d**',
    $g4 ? '✔' : '✘', count($unmanaged), count($unmUnsettled)));
foreach ($unmUnsettled as $f) { $p('    ✘ `' . $f . '` — اسمٌ خارجَ العُرفِ بلا حكمِ انطباق'); }

/* ═══ الإخراج ═════════════════════════════════════════════════════════════ */
$git = function ($a) use ($ROOT) {
    $o = array(); exec('git -C ' . escapeshellarg($ROOT) . ' ' . $a . ' 2>&1', $o);
    return trim(implode(' ', $o));
};
$head = array();
$head[] = '# `MIGRATION_GATE` — بوّابةُ الرسوبِ على دفترِ الهجرات';
$head[] = '';
$head[] = '> **مولَّدٌ من تشغيلٍ حيٍّ** بالسطر: `' . $CMD . '`';
$head[] = '';
$head[] = '| المفردة | القيمة |';
$head[] = '|---|---|';
$head[] = '| `Commit Hash` | `' . $git('rev-parse HEAD') . '` |';
$head[] = '| `Schema Version` | ' . (int) $one("SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'") . 'T |';
$head[] = '| `Registry Version` | ' . (int) $one("SELECT COUNT(*) FROM repair01_screen_registry") . ' |';
$head[] = '| `Measured At` | ' . date('Y-m-d H:i:s') . ' |';
$head[] = '| `Tool Version` | `' . GATE_VERSION . '` |';
$head[] = '| `Snapshot ID` | `' . ($one("SELECT snapshot_id FROM repair01_freeze_snapshot
    WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1") ?: 'UNFROZEN') . '` |';
$head[] = '';
$head[] = '## الحواجبُ الأربعةُ بمقاماتِها';
$head[] = '';

$tail = array();
$tail[] = '';
$tail[] = sprintf('**النتيجة: %d/4 أخضرُ — %s**', 4 - $fail,
    $fail ? '⛔ **البوّابةُ راسبةٌ ولا يُلتزَم فوقَها**' : '✔ **البوّابةُ خضراء**');
if (!$hasSettleTbl) {
    $tail[] = '';
    $tail[] = '> ⛔ **و`gov_migration_settlement` غيرُ موجود** — فلا حكمَ يُقرأ، والحواجبُ تقرأ فراغًا.';
}

$out = implode("\n", array_merge($head, $lines, $tail)) . "\n";
if ($MD) {
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/MIGRATION_GATE.md', $out);
    echo "✔ كُتب: docs/REPAIR01_20260823/MIGRATION_GATE.md\n";
}
echo $out;
exit($fail ? 1 : 0);
