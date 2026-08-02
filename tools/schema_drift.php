<?php
/**
 * tools/schema_drift.php — حارسُ انحراف المخطَّط (EMS Installer · المرحلة ④)
 * ═══════════════════════════════════════════════════════════════════════════
 * العلّةُ التي تقتل كلَّ مقاربةِ «مُثبِّتٍ فقط»: تُضيف عمودًا في التطوير وتنسى
 * إعادةَ توليد `database/schema/`، فيصير مُثبِّتُك يُنتج قاعدةً مختلفةً عن
 * قاعدتك — بصمتٍ، حتى يومِ النشر. هذا الحارسُ يجعل النسيانَ صاخبًا.
 *
 * يقارن قاعدتين كائنًا كائنًا وعمودًا عمودًا وفهرسًا فهرسًا ومفتاحًا مفتاحًا،
 * ويعيد رمزَ خروجٍ غيرَ صفريٍّ عند أوّل فرق.
 *
 * الاستخدام:
 *   php tools/schema_drift.php --expected=equipation_manage --actual=ems_installtest
 *   php tools/schema_drift.php --expected=... --actual=... --host=localhost --user=root --pass=
 *
 * `expected` هي القاعدةُ المرجعية (قاعدةُ تطويرك)، و`actual` قاعدةٌ مثبَّتةٌ
 * حديثًا من المصنوعات. صفرُ فروقٍ = المصنوعاتُ مواكبة.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('403 — CLI only.');
}

error_reporting(E_ALL & ~E_DEPRECATED);

$opt = array('host' => 'localhost', 'user' => 'root', 'pass' => '', 'expected' => '', 'actual' => '');
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)=(.*)$/is', $a, $m) && array_key_exists(strtolower($m[1]), $opt)) {
        $opt[strtolower($m[1])] = $m[2];
    }
}
if ($opt['expected'] === '' || $opt['actual'] === '') {
    fwrite(STDERR, "الاستخدام: php tools/schema_drift.php --expected=<db> --actual=<db> [--host= --user= --pass=]\n");
    exit(2);
}

$conn = @new mysqli($opt['host'], $opt['user'], $opt['pass']);
if ($conn->connect_error) {
    fwrite(STDERR, "فشل الاتصال: {$conn->connect_error}\n");
    exit(2);
}
$conn->set_charset('utf8mb4');

// ═══════════════════════════════════════════════════════════════════════════
// التقاطُ بصمةِ مخطَّطٍ من information_schema
// ═══════════════════════════════════════════════════════════════════════════

/** @return array ['objects'=>[name=>type], 'columns'=>[t.c=>sig], 'indexes'=>[..], 'fks'=>[..]] */
function capture(mysqli $conn, $db)
{
    $q = function ($sql) use ($conn, $db) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            fwrite(STDERR, "فشل التحضير: {$conn->error}\n");
            exit(2);
        }
        $stmt->bind_param('s', $db);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = array();
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $stmt->close();
        return $rows;
    };

    $out = array('objects' => array(), 'columns' => array(), 'indexes' => array(), 'fks' => array());

    foreach ($q("SELECT TABLE_NAME t, TABLE_TYPE ty FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?") as $r) {
        $out['objects'][$r['t']] = $r['ty'];
    }

    // توقيعُ العمود: النوعُ والقابليةُ والافتراضُ والإضافة. الترتيبُ (ORDINAL)
    // متعمَّدُ الاستبعاد: إعادةُ ترتيب أعمدةٍ لا تغيّر سلوكًا ولا تستحق إنذارًا.
    foreach ($q(
        "SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_TYPE ct, IS_NULLABLE n,
                COALESCE(COLUMN_DEFAULT,'∅') d, EXTRA e, COALESCE(COLLATION_NAME,'') col
         FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?"
    ) as $r) {
        $out['columns']["{$r['t']}.{$r['c']}"] = "{$r['ct']}|null={$r['n']}|def={$r['d']}|{$r['e']}|{$r['col']}";
    }

    foreach ($q(
        "SELECT TABLE_NAME t, INDEX_NAME i, NON_UNIQUE nu,
                GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols
         FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ?
         GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE"
    ) as $r) {
        $out['indexes']["{$r['t']}.{$r['i']}"] = "unique=" . ($r['nu'] ? '0' : '1') . "|{$r['cols']}";
    }

    foreach ($q(
        "SELECT k.TABLE_NAME t, k.CONSTRAINT_NAME c,
                GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION) cols,
                k.REFERENCED_TABLE_NAME rt,
                GROUP_CONCAT(k.REFERENCED_COLUMN_NAME ORDER BY k.ORDINAL_POSITION) rcols
         FROM information_schema.KEY_COLUMN_USAGE k
         WHERE k.CONSTRAINT_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL
         GROUP BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.REFERENCED_TABLE_NAME"
    ) as $r) {
        $out['fks']["{$r['t']}.{$r['c']}"] = "{$r['cols']} → {$r['rt']}({$r['rcols']})";
    }

    return $out;
}

/** فروقُ خريطتين: مفقودٌ في actual · زائدٌ فيه · مختلفُ القيمة. */
function diffMap(array $exp, array $act)
{
    $missing = array();
    $extra = array();
    $changed = array();
    foreach ($exp as $k => $v) {
        if (!array_key_exists($k, $act)) {
            $missing[$k] = $v;
        } elseif ($act[$k] !== $v) {
            $changed[$k] = array($v, $act[$k]);
        }
    }
    foreach ($act as $k => $v) {
        if (!array_key_exists($k, $exp)) {
            $extra[$k] = $v;
        }
    }
    return array($missing, $extra, $changed);
}

// ═══════════════════════════════════════════════════════════════════════════

echo "\n═══ حارسُ انحراف المخطَّط ═══\n";
echo "  المرجع : {$opt['expected']}\n";
echo "  المُقارَن: {$opt['actual']}\n";
echo str_repeat('─', 78) . "\n";

$expected = capture($conn, $opt['expected']);
$actual   = capture($conn, $opt['actual']);

if (empty($expected['objects'])) {
    fwrite(STDERR, "القاعدةُ المرجعية `{$opt['expected']}` فارغةٌ أو غيرُ موجودة.\n");
    exit(2);
}

$labels = array(
    'objects' => 'الكائنات (جداول/مناظير)',
    'columns' => 'الأعمدة',
    'indexes' => 'الفهارس',
    'fks'     => 'المفاتيح الأجنبية',
);

$totalDiffs = 0;
foreach ($labels as $key => $label) {
    list($missing, $extra, $changed) = diffMap($expected[$key], $actual[$key]);
    $n = count($missing) + count($extra) + count($changed);
    $totalDiffs += $n;

    printf("  %s %-28s المرجع=%-6s المُقارَن=%-6s فروق=%s\n",
        $n === 0 ? '✔' : '✘', $label,
        count($expected[$key]), count($actual[$key]), $n);

    foreach ($missing as $k => $v) {
        echo "      ▸ مفقودٌ في المُقارَن : {$k}  [{$v}]\n";
    }
    foreach ($extra as $k => $v) {
        echo "      ▸ زائدٌ في المُقارَن  : {$k}  [{$v}]\n";
    }
    foreach ($changed as $k => $pair) {
        echo "      ▸ مختلف            : {$k}\n";
        echo "          المرجع  : {$pair[0]}\n";
        echo "          المُقارَن: {$pair[1]}\n";
    }
}

echo str_repeat('─', 78) . "\n";
if ($totalDiffs === 0) {
    echo "✔ صفرُ فروق — مصنوعاتُ database/schema/ مواكبةٌ للقاعدة المرجعية.\n\n";
    exit(0);
}
fwrite(STDERR, "✘ {$totalDiffs} فرقًا. أعِد التوليد: php database/migrate.php dump-schema\n\n");
exit(1);
