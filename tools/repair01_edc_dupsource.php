<?php
/**
 * tools/repair01_edc_dupsource.php — التكرارُ يُثبَت بالحبّةِ لا بالاسم
 * ═══════════════════════════════════════════════════════════════════════════
 * **حكمُ المالك 2026-08-27**: في الإهلاكِ «المالية تملك محاسبة الإهلاك»
 * والمعياريُّ يُختار **بمقارنةِ الحبّةِ ومسارِ الكتابةِ ونموذجِ البياناتِ ومعرِّفِ
 * الشاشةِ والحقولِ والتكاملات** ⛔ **«لا يتم بناءً على الجديد أو القديم»**.
 * وفي الدفعِ «**لا نعتبر التكرار مثبتًا قبل قياس Grain**».
 *
 * ◆ **والاسمُ الواحدُ دعوى تكرارٍ لا إثباتُه**: سطحان بالاسمِ نفسِه قد يكونا
 *   حبّتَين مختلفتَين (‏أصلٌ مقابلَ فترة، طلبٌ مقابلَ دفعة) — **ودمجُهما حينئذٍ
 *   يفقد معنًى** لا يعيده اسمٌ موحَّد.
 *
 * التشغيل: php tools/repair01_edc_dupsource.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

$idx = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) { if (substr($p->getFilename(), -4) !== '.php') continue;
  $s = strtr($p->getPathname(), DIRECTORY_SEPARATOR, '/');
  if (strpos($s, '/.git/') !== false) continue;
  if (!isset($idx[$p->getFilename()])) $idx[$p->getFilename()] = $s; }

/* ستّةُ محاورِ المالك — كلٌّ مقيسٌ من الكودِ والمخطَّط */
$axes = function ($b) use ($idx, $conn) {
  $f = isset($idx[$b]) ? $idx[$b] : null;
  $c = $f ? (string) @file_get_contents($f) : '';
  preg_match_all('~\b(?:FROM|JOIN|INSERT\s+INTO|UPDATE)\s+`?([a-z_][a-z_0-9]*)~i', $c, $m);
  $tbl = array_values(array_unique(array_map('strtolower', $m[1])));
  sort($tbl);
  /* الحبّة: أعمدةُ المفتاحِ التي يرشِّح بها */
  $grain = array();
  foreach (array('asset_id','equipment_id','period','month','year','fiscal','request_id',
                 'payment_id','batch_id','invoice_id','supplier_id','contract_id','installment_id') as $g) {
    if (preg_match('~\b' . $g . '\b~i', $c)) { $grain[] = $g; }
  }
  return array(
    'bytes'  => strlen($c),
    'write'  => preg_match('~\$_POST~', $c) ? 'POST' : 'READ',
    'tables' => $tbl,
    'grain'  => $grain,
    'fields' => preg_match_all('~<(?:input|select|textarea)\b~i', $c),
    'integ'  => preg_match_all('~(Service|Publisher|Engine|require_once)~i', $c),
    'path'   => $f ? substr($f, strlen(dirname(dirname($f))) + 1) : '—',
  );
};

$PAIRS = array(
  array('الإهلاك', 'assets_fin.php', 'Depreciation.php', 'DEP-05',
        'المالية تملك محاسبة الإهلاك — حكم المالك 2026-08-27'),
  array('الدفع',   'payments_fin.php', 'Payments.php',    'DEP-06',
        'الخزينة تملك تنفيذ الدفع — ولا يثبت التكرار قبل قياس الحبة'),
);

foreach ($PAIRS as $P) {
  list($ttl, $a, $b, $own, $rule) = $P;
  echo "\n═══ $ttl ═══\n";
  $A = $axes($a); $B = $axes($b);
  printf("  %-22s %-34s %-6s حقول=%-3d تكامل=%-3d %d بايت\n", $a, $A['path'], $A['write'], $A['fields'], $A['integ'], $A['bytes']);
  printf("  %-22s %-34s %-6s حقول=%-3d تكامل=%-3d %d بايت\n", $b, $B['path'], $B['write'], $B['fields'], $B['integ'], $B['bytes']);
  printf("  حبّة  %-16s: %s\n", $a, $A['grain'] ? implode(',', $A['grain']) : '—');
  printf("  حبّة  %-16s: %s\n", $b, $B['grain'] ? implode(',', $B['grain']) : '—');
  $shared = array_intersect($A['tables'], $B['tables']);
  printf("  جداولُ مشتركة: %d · خاصةٌ بـ%s: %d · خاصةٌ بـ%s: %d\n",
    count($shared), $a, count(array_diff($A['tables'], $B['tables'])), $b, count(array_diff($B['tables'], $A['tables'])));

  /* ═══ الحكم ═══ */
  $sameGrain = ($A['grain'] == $B['grain']);
  $overlap   = count($shared) / max(1, min(count($A['tables']), count($B['tables'])));
  if (!$sameGrain || $overlap < 0.5) {
    printf("  ⇒ **ليس تكرارًا**: %s — سطحان لحقيقتَين، والدمجُ يفقد معنًى\n",
      !$sameGrain ? 'حبّتان مختلفتان' : 'تقاطعُ جداولٍ ' . round($overlap * 100) . '٪ فقط');
    continue;
  }
  /* المعياريُّ: أعمقُ حبّةً ثمَّ أوسعُ تكاملًا ⛔ لا الأحدثُ ولا الأقدم */
  $win = (count($A['grain']) !== count($B['grain']))
       ? (count($A['grain']) > count($B['grain']) ? $a : $b)
       : (($A['integ'] >= $B['integ']) ? $a : $b);
  $los = ($win === $a) ? $b : $a;
  printf("  ⇒ **تكرارٌ مثبَت** · المعياريُّ %s · الإسقاطُ %s\n", $win, $los);
  printf("     القاعدة: حبّةٌ واحدةٌ وتقاطعُ جداولٍ %d٪ · اختير بعمقِ الحبّةِ ثمَّ سعةِ التكامل\n", round($overlap * 100));
  if (!$APPLY) { continue; }
  $why = "قياس EDC ستة محاور: حبة واحدة وتقاطع جداول " . round($overlap * 100) . " بالمئة · $rule · المعياري $win بعمق الحبة لا بالجديد او القديم";
  $conn->query("UPDATE repair01_screen_registry SET surface_kind='PROJECTION', ownership_verdict='DOMAIN_PROJECTION',
      verdict_rule=CONCAT(verdict_rule,' | " . $e($why) . "'), verdict_at=NOW()
    WHERE screen_file='" . $e($los) . "' AND on_disk=1");
  $conn->query("UPDATE repair01_screen_registry SET owner_code='" . $e($own) . "', surface_kind='SOURCE',
      source_of_truth='" . $e($win) . "', verdict_rule=CONCAT(verdict_rule,' | " . $e($why) . "'), verdict_at=NOW()
    WHERE screen_file='" . $e($win) . "' AND on_disk=1");
  echo "     ✔ ثُبِّت\n";
}
