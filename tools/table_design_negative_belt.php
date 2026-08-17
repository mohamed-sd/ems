<?php
/**
 * break_test.php — الحزامُ السلبيُّ لحارسِ توحيدِ الجداول.
 * تُكسَر كلُّ بوابةٍ عمدًا فيُتوقَّع أن تحمرَّ، ثم يُستعاد الملفُّ فورًا.
 * بوابةٌ تبقى خضراءَ وهي مكسورةٌ = بوابةٌ لا تحرسُ شيئًا.
 */
$ROOT = 'C:/wamp64/www/ems';
$PHP  = 'C:/wamp64/bin/php/php8.2.30/php.exe';
$TEST = $ROOT . '/tests/table_design_unification_test.php';

function run_gate($PHP, $TEST) {
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $TEST . '" 2>&1', $out, $code);
    return array($code, implode("\n", $out));
}

/* الحالةُ الأساس: يجب أن تكون خضراء */
list($c0, $o0) = run_gate($PHP, $TEST);
echo 'الأساس: ' . ($c0 === 0 ? 'أخضر ✔' : 'أحمر ✘ (خللٌ قبلَ البدء)') . PHP_EOL;
if ($c0 !== 0) { echo $o0 . PHP_EOL; exit(1); }

$breaks = array(
    'G1 (شاشةٌ يتيمة)' => array(
        'file'   => $ROOT . '/admin/includes/layout_head.php',
        'find'   => "<?php require_once dirname(__DIR__, 2) . '/includes/table_design.php'; ?>",
        'repl'   => '<!-- كُسِرت عمدًا -->',
        'expect' => 'G1',
    ),
    'G2 (لونٌ صريح)' => array(
        'file'   => $ROOT . '/assets/css/ems-screens.css',
        'append' => "\n.broken-probe-table tbody td { color: #ff00ff !important; }\n",
        'expect' => 'G2',
    ),
    'G3 (ورقةٌ بعدَها)' => array(
        'file'   => $ROOT . '/inheader.php',
        'find'   => "<?php require_once __DIR__ . '/includes/table_design.php'; ?>",
        'repl'   => "<?php require_once __DIR__ . '/includes/table_design.php'; ?>\n    <link rel=\"stylesheet\" href=\"/ems/assets/css/ems-screens.css\">",
        'expect' => 'G3',
    ),
    'G4 (مفتاحٌ ناقص)' => array(
        'file'   => $ROOT . '/assets/css/ems-tables.css',
        'find'   => '--table-row-hover-bg:      #fcf3d6;',
        'repl'   => '/* --table-row-hover-bg مُزال عمدًا */',
        'expect' => 'G4',
    ),
    'G5 (قاعدةٌ مفصولة)' => array(
        'file'   => $ROOT . '/assets/css/ems-tables.css',
        'find'   => 'color: var(--table-text, #161616) !important;',
        'repl'   => 'color: #161616 !important;',
        'expect' => 'G5',
    ),
    'G7 (غلافٌ بلا تصفير)' => array(
        'file'   => $ROOT . '/assets/css/ems-tables.css',
        'find'   => 'body.ems-site .table-responsive-wrapper,',
        'repl'   => 'body.ems-site .probe-removed-wrapper,',
        'expect' => 'G7',
    ),
    'G6 (الطبقةُ الكونيّة)' => array(
        'file'   => $ROOT . '/assets/css/ems-tables.css',
        'find'   => 'UNIVERSAL TABLE BASE',
        'repl'   => 'REMOVED MARKER',
        'expect' => 'G6',
    ),
);

$pass = 0; $total = 0;
foreach ($breaks as $label => $b) {
    $total++;
    $orig = file_get_contents($b['file']);
    if (isset($b['append'])) {
        file_put_contents($b['file'], $orig . $b['append']);
        $applied = true;
    } else {
        if (strpos($orig, $b['find']) === false) {
            echo '  ⚠ ' . $label . ' — لم يُعثر على موضعِ الكسر، تُخطَّى' . PHP_EOL;
            continue;
        }
        file_put_contents($b['file'], str_replace($b['find'], $b['repl'], $orig));
        $applied = true;
    }

    list($c, $o) = run_gate($PHP, $TEST);
    file_put_contents($b['file'], $orig);          // الاستعادةُ فورًا

    /* هل احمرّت البوابةُ المقصودةُ بعينها؟ */
    $hitLine = '';
    foreach (explode("\n", $o) as $ln) {
        if (strpos($ln, '✘') !== false && strpos($ln, $b['expect']) !== false) { $hitLine = trim($ln); break; }
    }
    $ok = ($c !== 0 && $hitLine !== '');
    if ($ok) $pass++;
    echo '  ' . ($ok ? '✔' : '✘') . '  ' . $label
       . ' → ' . ($ok ? 'احمرَّت كما يجب' : 'بقيت خضراءَ وهي مكسورة!') . PHP_EOL;
    if ($ok) echo '        ' . mb_substr($hitLine, 0, 100) . PHP_EOL;
}

/* التأكّدُ من أن كلَّ شيءٍ عاد كما كان */
list($cz, $oz) = run_gate($PHP, $TEST);
echo PHP_EOL . 'بعدَ الاستعادة: ' . ($cz === 0 ? 'أخضر ✔' : 'أحمر ✘ — لم تكتملِ الاستعادة!') . PHP_EOL;
echo 'الحزامُ السلبيّ: ' . $pass . ' من ' . $total . PHP_EOL;
exit(($pass === $total && $cz === 0) ? 0 : 1);
