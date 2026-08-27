<?php
/**
 * tools/repair01_edc_forensic.php — التحليلُ الجنائيُّ للأسطحِ الموروثة (القرار ⑤)
 * ═══════════════════════════════════════════════════════════════════════════
 * **قرارُ المالك 2026-08-27 · القرار الخامس**: «لا تخمّنْ مالكَها ولا تتقاعدها
 * كلَّها. نفّذْ `Legacy Surface Forensic Review`» — واجمعْ لكلِّ ملفٍّ أربعةَ
 * عشرَ حقلًا.
 *
 * ⛔ **والقاعدةُ التي لا يجوز تجاوزُها** (‏نصُّه حرفًا): «أيُّ `Legacy File`
 *   **يكتب في قاعدةِ البيانات** لا يُصنَّف `DEAD` أو `RETIRE` تلقائيًّا.
 *   `WRITE_SURFACE = YES` يعني **`Mandatory Individual Review`** — حتّى لو لم
 *   يظهر في السايدبار ولم يُستخدم كثيرًا واسمُه سيّئٌ ولديه بديلٌ مستهدَف.
 *   **لأنَّ ملفَّ الكتابةِ قد يمثّل مسارَ أعمالٍ أو تكاملًا خفيًّا.**»
 *
 * ◆ **والكتابةُ تُقاس من الشيفرةِ لا من الاسم**: `INSERT`/`UPDATE`/`DELETE`
 *   و`->insert(`/`->update(`/`->delete(` عبرَ بوّابةِ العزل. **واسمُ الملفِّ
 *   لا يقول إن كان يكتب** — `contract_report.php` قد يكتب و`worker_form.php`
 *   قد لا يفعل.
 *
 * ◆ **والتقاعدُ سلسلةٌ لا حذف** (‏نصُّ القرار): منعُ الاستعمالِ الجديدِ ثمَّ
 *   التحويلُ إن أمكن ثمَّ حفظُ التاريخِ ثمَّ المراقبةُ ثمَّ الرفعُ من الملاحةِ
 *   ثمَّ التقاعد. ⛔ **ولا حذفَ ماديٌّ متعجّل.**
 *
 * التشغيل: php tools/repair01_edc_forensic.php
 * المخرَج : docs/REPAIR01_20260823/open/EDC_LEGACY_FORENSIC.md
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
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$n = function ($sql) use ($conn) { $r = @$conn->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; };

/* ═══ ① فهرسُ الشجرةِ مرّةً واحدة ═════════════════════════════════════════ */
$idx = array(); $all = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    if (substr($p->getFilename(), -4) !== '.php') { continue; }
    $s = strtr($p->getPathname(), DIRECTORY_SEPARATOR, '/');
    if (strpos($s, '/.git/') !== false || strpos($s, '/vendor/') !== false) { continue; }
    $all[] = $s;
    if (!isset($idx[$p->getFilename()])) { $idx[$p->getFilename()] = $s; }
}

/* ═══ ② المادّة — الأسطحُ الموروثةُ الحيّة ══════════════════════════════════ */
$rows = array();
$q = $conn->query("SELECT screen_id, screen_file, route, owner_code, canonical_label_ar,
                          guard_kind, permission_policy, ownership_verdict, surface_kind
                     FROM repair01_screen_registry
                    WHERE ownership_verdict = 'LEGACY' AND on_disk = 1
                    ORDER BY screen_file");
while ($q && ($x = $q->fetch_assoc())) { $rows[] = $x; }

/* ═══ ③ الجرّاحة — أربعةَ عشرَ حقلًا لكلِّ ملفّ ═════════════════════════════ */
$TBL = array();
$q = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
while ($q && ($x = $q->fetch_row())) { if (strlen($x[0]) > 3) { $TBL[] = $x[0]; } }

$F = array();
foreach ($rows as $x) {
    $b = basename((string) $x['screen_file']);
    if (!isset($idx[$b])) { continue; }
    $path = $idx[$b];
    $src  = (string) @file_get_contents($path);
    $rel  = str_replace($ROOT . '/', '', $path);

    /* الكتابةُ من الشيفرةِ لا من الاسم */
    $writes = (bool) preg_match('~\b(INSERT\s+INTO|UPDATE\s+`?\w+`?\s+SET|DELETE\s+FROM|REPLACE\s+INTO)\b~i', $src)
           || (bool) preg_match('~->\s*(insert|update|delete|insertOrUpdate)\s*\(~', $src);
    /* الجداولُ الملموسة */
    $read = array(); $wrote = array();
    foreach ($TBL as $t) {
        if (stripos($src, $t) === false) { continue; }
        if (preg_match('~(INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\s+`?' . preg_quote($t, '~') . '`?~i', $src)) { $wrote[] = $t; }
        else { $read[] = $t; }
    }
    /* من يناديه */
    $calledBy = 0;
    foreach ($all as $f) {
        if ($f === $path) { continue; }
        $c = (string) @file_get_contents($f);
        if (strpos($c, $b) !== false) { $calledBy++; }
    }
    /* الملاحة والصلاحية */
    $inNav = $n("SELECT COUNT(*) FROM nav_items WHERE route LIKE '%" . $conn->real_escape_string($b) . "'")
           + $n("SELECT COUNT(*) FROM nav_canonical WHERE route LIKE '%" . $conn->real_escape_string($b) . "'");
    $roles = $n("SELECT COUNT(DISTINCT rp.role_id) FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                  WHERE rp.can_view = 1 AND m.code LIKE '%" . $conn->real_escape_string($b) . "'");
    /* بديلٌ مستهدَفٌ يحمل اسمَه */
    $repl = '';
    if ((string) $x['canonical_label_ar'] !== '') {
        $repl = (string) $conn->query("SELECT COALESCE(MIN(screen_file),'') FROM repair01_screen_registry
                   WHERE canonical_label_ar = '" . $conn->real_escape_string($x['canonical_label_ar']) . "'
                     AND screen_file <> '" . $conn->real_escape_string($b) . "' AND on_disk = 1")->fetch_row()[0];
    }

    /* ═══ الحكمُ — والقاعدةُ الحاكمةُ أوّلًا ═══════════════════════════════ */
    if ($writes) {
        $cls = 'NEEDS_OWNER_DECISION';
        $why = '**سطحُ كتابةٍ** — نصُّ القرار: مراجعةٌ فرديّةٌ إلزاميّةٌ قبل أيِّ تقاعد';
    } elseif ($inNav === 0 && $calledBy === 0 && $roles === 0) {
        $cls = 'DEAD';
        $why = 'قراءةٌ فقط · لا ملاحةَ ولا نداءَ ولا منحَ صلاحية';
    } elseif ($repl !== '') {
        $cls = 'REDIRECT';
        $why = "قراءةٌ فقط وله بديلٌ يحمل اسمَه: $repl";
    } elseif ($inNav === 0 && $roles > 0) {
        $cls = 'KEEP';
        $why = "قراءةٌ فقط · خارجَ الملاحةِ ويراه $roles دورًا — مسارٌ مباشرٌ حيّ";
    } elseif ($calledBy > 0) {
        $cls = 'KEEP';
        $why = "قراءةٌ فقط · يناديه $calledBy ملفًّا — جزءٌ من مسارٍ قائم";
    } else {
        $cls = 'NEEDS_OWNER_DECISION';
        $why = 'قراءةٌ فقط ولا إشارةَ حاسمة';
    }

    $F[] = array('file' => $rel, 'base' => $b, 'id' => $x['screen_id'],
                 'label' => $x['canonical_label_ar'], 'owner' => $x['owner_code'],
                 'guard' => $x['guard_kind'], 'perm' => $x['permission_policy'],
                 'writes' => $writes, 'read' => count($read), 'wrote' => $wrote,
                 'calledBy' => $calledBy, 'nav' => $inNav, 'roles' => $roles,
                 'repl' => $repl, 'cls' => $cls, 'why' => $why);
}

/* ═══ ④ التقرير ═══════════════════════════════════════════════════════════ */
$tal = array();
foreach ($F as $x) { $tal[$x['cls']] = isset($tal[$x['cls']]) ? $tal[$x['cls']] + 1 : 1; }
echo "\n═══ التحليلُ الجنائيُّ للأسطحِ الموروثة — القرار ⑤ ═══\n\n";
printf("  المقام: %d سطحًا · **أسطحُ كتابةٍ: %d**\n\n", count($F),
    count(array_filter($F, function ($z) { return $z['writes']; })));
arsort($tal);
foreach ($tal as $k => $c) { printf("  %-24s %d\n", $k, $c); }

$m = array();
$m[] = '# التحليلُ الجنائيُّ للأسطحِ الموروثة — القرار ⑤';
$m[] = '';
$m[] = '> ⛔ **مولَّدٌ من الشجرةِ الحيّةِ والمخزن**: `php tools/repair01_edc_forensic.php`';
$m[] = '> **نصُّ قرارِك:** «لا تخمّنْ مالكَها ولا تتقاعدها كلَّها.»';
$m[] = '';
$m[] = '## القاعدةُ التي لا تُتجاوَز';
$m[] = '';
$m[] = '> **أيُّ ملفٍّ يكتب في قاعدةِ البيانات لا يُصنَّف `DEAD` أو `RETIRE` تلقائيًّا.**';
$m[] = '> `WRITE_SURFACE = YES` ⇐ **مراجعةٌ فرديّةٌ إلزاميّة** — حتّى لو لم يظهر في';
$m[] = '> السايدبار ولم يُستخدم كثيرًا واسمُه سيّئٌ ولديه بديل. **لأنَّ ملفَّ الكتابةِ قد';
$m[] = '> يمثّل مسارَ أعمالٍ أو تكاملًا خفيًّا.**';
$m[] = '';
$m[] = 'والكتابةُ **مقيسةٌ من الشيفرةِ لا من الاسم** — فاسمُ الملفِّ لا يقول إن كان يكتب.';
$m[] = '';
$m[] = '| الحكم | العدد |';
$m[] = '|---|---:|';
foreach ($tal as $k => $c) { $m[] = "| `$k` | $c |"; }
$m[] = '';
foreach (array('NEEDS_OWNER_DECISION', 'KEEP', 'REDIRECT', 'DEAD') as $k) {
    $l = array_values(array_filter($F, function ($z) use ($k) { return $z['cls'] === $k; }));
    if (!$l) { continue; }
    $m[] = '---';
    $m[] = '';
    $m[] = "## `$k` — " . count($l) . ' سطحًا';
    $m[] = '';
    $m[] = '| الملفّ | يكتب؟ | جداولُ يكتبها | يناديه | ملاحة | أدوارٌ تراه | حارس | الحكمُ ولماذا |';
    $m[] = '|---|---|---|---:|---:|---:|---|---|';
    foreach ($l as $z) {
        $m[] = '| `' . $z['file'] . '` | ' . ($z['writes'] ? '**نعم**' : 'لا') . ' | '
             . ($z['wrote'] ? '`' . implode('` · `', array_slice($z['wrote'], 0, 3)) . '`'
                . (count($z['wrote']) > 3 ? ' …+' . (count($z['wrote']) - 3) : '') : '—') . ' | '
             . $z['calledBy'] . ' | ' . $z['nav'] . ' | ' . $z['roles'] . ' | '
             . ($z['guard'] ?: '—') . ' | ' . $z['why'] . ' |';
    }
    $m[] = '';
}
$m[] = '---';
$m[] = '';
$m[] = '## التقاعدُ سلسلةٌ لا حذف';
$m[] = '';
$m[] = '```';
$m[] = 'منع الاستعمال الجديد ← تحويل إن أمكن ← حفظ التاريخ ← مراقبة';
$m[] = '  ← رفع من الملاحة ← تقاعد';
$m[] = '```';
$m[] = '';
$m[] = '⛔ **ولا حذفَ ماديٌّ متعجّل** — نصُّ قرارِك.';
$m[] = '';
$m[] = '## المطلوبُ منك';
$m[] = '';
$m[] = 'أمامَ كلِّ سطحٍ في `NEEDS_OWNER_DECISION` اكتبْ واحدًا من ثمانية:';
$m[] = '`KEEP` · `MOVE` · `MERGE` · `TAB` · `REDIRECT` · `RETIRE` · `DEAD` · `NEEDS_OWNER_DECISION`';
$m[] = '';
$m[] = '**وأسرعُ طريقٍ:** قلْ «راجعها معي واحدةً واحدة» فأعرض كلَّ سطحٍ بما يكتبه';
$m[] = 'ومن يناديه وأقترح حكمَه، وتقول نعم أو لا.';
$m[] = '';

$out = $ROOT . '/docs/REPAIR01_20260823/open/EDC_LEGACY_FORENSIC.md';
@mkdir(dirname($out), 0777, true);
file_put_contents($out, implode("\n", $m) . "\n");
printf("\n  ✔ التقرير: docs/REPAIR01_20260823/open/EDC_LEGACY_FORENSIC.md (%s ك.ب)\n",
    number_format(filesize($out) / 1024, 1));
echo "\n⛔ ولا يُصنَّف سطحُ كتابةٍ `DEAD` أو `RETIRE` هنا — نصُّ القرار.\n";
