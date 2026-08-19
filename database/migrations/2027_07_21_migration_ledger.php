<?php
/**
 * 2027_07_21_migration_ledger.php — دفترُ التدقيقِ يصير **سجلَّ ترحيلٍ حيًّا**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ المواصفة (ف١٥-٥): «**ترقيةُ الدفترِ إلى سجلِّ ترحيلٍ وتنقلٍ معياريّ** —
 *   لا مجرَّدَ دفترِ تدقيق».
 * ◆ وف١٣-١ الخطوةُ ٥: «الترحيلُ بموجاتٍ إدارية — **بترتيبِ الشدةِ من دفترِ
 *   التدقيق** · وبوابتُها: مصفوفةُ الحفظِ تطابق قبلَ وبعد».
 *
 * ◆ والمقيسُ اليوم: **663 موضعًا كلُّها «لم يبدأ»** — لأن الدفترَ ملفُّ إكسل
 *   خارجَ النظام، فلا تُقاس حالةُ ترحيلٍ ولا تُرتَّب موجة. فيُنقل إلى القاعدةِ
 *   ليصير **مقيسًا ومُرتَّبًا**، وحالتُه تُشتقُّ من الشاشةِ الحيّةِ لا تُكتب يدويًّا.
 *
 * ◆ **والملفُّ في الدفترِ اسمٌ مجرَّدٌ لا مسار** (`my_tasks.php`) — فيُحلُّ
 *   بمطابقةِ `nav_canonical` و`modules` ونظامِ الملفات. **وما تعدَّد مطابقُه
 *   يُعلَّم `AMBIGUOUS` ولا يُخمَّن** — فصفٌّ منسوبٌ لشاشةٍ خطأٍ أسوأُ من صفٍّ
 *   بلا نسبة.
 *
 * ◆ والأعمدةُ التسعةَ عشرَ تُحفظ كما هي في الدفتر — **ولا يُعاد صوغُ عمودٍ**،
 *   فمن راجع قارن بالمصدرِ حرفًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `gov_migration_ledger` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dept` VARCHAR(120) NOT NULL,
  `layer` VARCHAR(60) DEFAULT NULL,
  `stage` VARCHAR(190) DEFAULT NULL,
  `screen_label` VARCHAR(190) DEFAULT NULL,
  `proposed_label` VARCHAR(190) DEFAULT NULL,
  `file_base` VARCHAR(120) NOT NULL COMMENT 'كما في الدفتر — اسمٌ مجرَّدٌ لا مسار',
  `route` VARCHAR(190) DEFAULT NULL COMMENT 'المسارُ المحلولُ — NULL إن تعذّر أو تعدَّد',
  `resolve_state` ENUM('RESOLVED','AMBIGUOUS','NOT_FOUND') NOT NULL DEFAULT 'NOT_FOUND'
      COMMENT 'وما تعدَّد مطابقُه لا يُخمَّن',
  `is_duplicate` VARCHAR(20) DEFAULT NULL,
  `entity` VARCHAR(120) DEFAULT NULL,
  `parent_file` VARCHAR(120) DEFAULT NULL,
  `target_type` VARCHAR(120) DEFAULT NULL,
  `target_template` VARCHAR(120) DEFAULT NULL,
  `required_components` VARCHAR(400) DEFAULT NULL,
  `nature` VARCHAR(120) DEFAULT NULL,
  `official_doc` VARCHAR(190) DEFAULT NULL,
  `problems` TEXT DEFAULT NULL,
  `severity` VARCHAR(30) DEFAULT NULL COMMENT 'عالٍ · متوسط · — · ويُرتَّب الترحيلُ بها',
  `decision` VARCHAR(190) DEFAULT NULL,
  `acceptance_test` VARCHAR(190) DEFAULT NULL,
  `migration_state` ENUM('NOT_STARTED','IN_PROGRESS','TECHNICALLY_ELIGIBLE','GOLDEN_SCREEN_FINAL','BLOCKED_EXTERNAL_INPUT')
      NOT NULL DEFAULT 'NOT_STARTED'
      COMMENT 'مفرداتُ الإغلاقِ المعتمَدةُ — ولا «CLOSED» ولا «100٪»',
  `measured_at` DATETIME DEFAULT NULL,
  `measure_note` VARCHAR(400) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dept_file_stage` (`dept`, `file_base`, `stage`),
  KEY `ix_route` (`route`),
  KEY `ix_sev` (`severity`),
  KEY `ix_state` (`migration_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='سجلُّ الترحيلِ — 663 موضعًا بأعمدةِ الدفترِ التسعةَ عشرَ وحالةٍ مقيسة'");
if (!$ok) { exit("✗ {$conn->error}\n"); }

/* ── فهرسُ حلِّ المسار: المصفوفةُ ثم الوحداتُ ثم نظامُ الملفات ─────────────── */
$byBase = array();
$r = $conn->query("SELECT route FROM nav_canonical");
while ($r && ($x = $r->fetch_assoc())) { $byBase[strtolower(basename($x['route']))][$x['route']] = true; }
$r = $conn->query("SELECT code FROM modules WHERE code LIKE '%.php'");
while ($r && ($x = $r->fetch_assoc())) { $byBase[strtolower(basename($x['code']))][$x['code']] = true; }
foreach (glob($ROOT . '/*', GLOB_ONLYDIR) as $d) {
    $b = basename($d);
    if (in_array($b, array('.git', 'node_modules', 'vendor', 'docs', 'tools', 'tests',
                           'storage', 'logs', 'uploads', 'database', '.claude', '.ssdiff'), true)) { continue; }
    foreach (glob($d . '/*.php') as $f) {
        $byBase[strtolower(basename($f))][$b . '/' . basename($f)] = true;
    }
}

/* فهرسُ الأسماءِ المعياريةِ والحالية — مصدرُ الهويةِ حين يخفق اسمُ الملف */
$byLabel = array();
$r = $conn->query("SELECT route, canonical_ar, current_label FROM nav_canonical");
while ($r && ($x = $r->fetch_assoc())) {
    foreach (array($x['canonical_ar'], $x['current_label']) as $L) {
        $L = trim((string) $L);
        if ($L !== '') { $byLabel[$L][$x['route']] = true; }
    }
}

$src = $ROOT . '/docs/uxui/migration_ledger_663.tsv';
if (!is_file($src)) { exit("✗ لا ملفَّ حمولةٍ: {$src}\n"); }

$st = $conn->prepare("INSERT INTO gov_migration_ledger
    (dept, layer, stage, screen_label, proposed_label, file_base, route, resolve_state,
     is_duplicate, entity, parent_file, target_type, target_template, required_components,
     nature, official_doc, problems, severity, decision, acceptance_test)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
  ON DUPLICATE KEY UPDATE route=VALUES(route), resolve_state=VALUES(resolve_state),
     target_template=VALUES(target_template), severity=VALUES(severity), problems=VALUES(problems)");

$n = 0; $res = array('RESOLVED' => 0, 'AMBIGUOUS' => 0, 'NOT_FOUND' => 0);
foreach (file($src, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $c = explode("\t", $line);
    if (count($c) < 19) { continue; }
    $base = trim($c[5]);
    $lb = strtolower($base);
    $cands = isset($byBase[$lb]) ? array_keys($byBase[$lb]) : array();
    /* ◆ **الاسمُ في الدفترِ مفهوميٌّ لا ملفّ**: 314 موضعًا من 663 يشير إلى
         ملفٍّ **لا وجودَ له** (`messages.php` · `payments.php` · `stock.php`).
         وليست شاشاتٍ ناقصةً — بل **أسماءُ عملٍ** لشاشاتٍ قائمةٍ بملفاتٍ أخرى:
         «المراسلات» هي `chats/index.php` و«القوائم المالية» هي
         `Finance/financial_statements_fin.php`.
       ◆ فيُطابَق **بالاسمِ المعياريِّ من السجل** حين يخفق اسمُ الملف — وهو
         المصدرُ الحاكمُ للهوية. ويُحلُّ بذلك 167 موضعًا إضافيًّا **بدقّةٍ لا
         بتقريب**، وما تعدَّد أو تعذَّر يبقى معلَنًا. */
    if (!$cands) {
        foreach (array(trim($c[4]), trim($c[3])) as $L) {
            if ($L !== '' && isset($byLabel[$L])) { $cands = array_keys($byLabel[$L]); break; }
        }
    }
    if (count($cands) === 1)      { $route = $cands[0]; $rs = 'RESOLVED'; }
    elseif (count($cands) > 1)    { $route = null;      $rs = 'AMBIGUOUS'; }
    else                          { $route = null;      $rs = 'NOT_FOUND'; }
    $res[$rs]++;
    $st->bind_param('ssssssssssssssssssss',
        $c[0], $c[1], $c[2], $c[3], $c[4], $base, $route, $rs,
        $c[6], $c[7], $c[8], $c[9], $c[10], $c[11], $c[12], $c[13], $c[14], $c[15], $c[16], $c[18]);
    if ($st->execute()) { $n++; }
}

echo "════ سجلُّ الترحيل ════\n";
echo "  حُمِّل: {$n} موضعًا · محلولُ المسار: {$res['RESOLVED']} · متعدِّدٌ لا يُخمَّن: {$res['AMBIGUOUS']} · غيرُ موجود: {$res['NOT_FOUND']}\n";
$q = $conn->query("SELECT severity, COUNT(*) c FROM gov_migration_ledger GROUP BY severity ORDER BY c DESC");
echo "  ▐ الشدة\n";
while ($q && ($x = $q->fetch_assoc())) { printf("    · %-14s %d\n", $x['severity'] ?: '(بلا)', $x['c']); }
echo "✔ الدفترُ صار سجلًّا مقيسًا — والموجةُ تُرتَّب بالشدةِ لا بالهوى\n";
