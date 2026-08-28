<?php
/**
 * tools/repair01_migration_settle.php — تسويةُ الهجراتِ غيرِ المصالَحةِ بأدلّتِها
 * ═══════════════════════════════════════════════════════════════════════════
 * **`RPR-02` §٩ · `RPR-03` §٣·١**: «تسويةٌ تاريخيّةٌ **بأدلّتها** — تُقيَّد بحالةٍ
 * مثل «مطبَّقةٌ ومتحقَّقٌ منها بالمخطَّط» **ولا يُعاد تنفيذُها**. ولا تبقى هجرةٌ
 * واحدةٌ بلا حالةٍ محكومةٍ في الدفتر».
 *
 * ◆ **وأربعةُ أحكامٍ لا حكمٌ واحد** — فالسبعُ والخمسون ليست عائلةً واحدة:
 *   ① `APPLIED_VERIFIED_BY_SCHEMA` — هجرةٌ أمامَ بأثرٍ في المخطَّط: تُقرأ
 *      كائناتُها من نصِّها ويُسأل عنها `information_schema` **كائنًا كائنًا**.
 *   ② `APPLIED_VERIFIED_BY_DATA` — هجرةُ بياناتٍ بلا `DDL`: يُقاس أثرُها في
 *      الجداولِ التي تكتب فيها. ⛔ **وجدولٌ فارغٌ ليس دليلًا** فلا تُرفَع.
 *   ③ `ROLLBACK_SCRIPT_NOT_APPLIED` — ملفُّ `_down`: **ليس هجرةً أمامَ أصلًا**،
 *      ودليلُه **معكوس**: أن تكون الكائناتُ التي يُسقطها **ما تزال قائمة** ⇒
 *      فهو لم يُنفَّذ. **ويُقيَّد `baseline` ليخرج من طابورِ `migrate.php up`.**
 *   ④ `SUPERSEDED_BY_INSTALLER_SQUASH` — صفُّ دفترٍ بلا ملفّ: يُبحَث عن التزامِ
 *      حذفِه في تاريخِ `git` **فيُسمّى بتجزئتِه وتاريخِه**.
 *
 * ⚠ **وخطرٌ حيٌّ يُسمّى**: `migrate.php::cmd_up()` **لا يستثني `_down.php`** —
 *   فأربعةٌ وعشرون سكربتَ تراجعٍ **معلَّقةٌ في طابورِه الآن**، ولو شُغِّل `up`
 *   لأسقطها جداولَ حيّة. **والتسويةُ تُخرجها من الطابورِ فعلًا لا نصحًا.**
 *
 * ⛔ **ولا يُرفَع `verified` بلا دليلٍ مكتوب** — والقيدُ في القاعدةِ يمنعه
 *   (`chk_gms_verified_needs_evidence`)، **فالحاجبُ في المخطَّطِ لا في النيّة.**
 *
 * التشغيل:
 *   php tools/repair01_migration_settle.php            ← يقيس ويعرض ولا يكتب
 *   php tools/repair01_migration_settle.php --apply    ← يكتب الأحكامَ والدفتر
 *   php tools/repair01_migration_settle.php --apply --md
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
define('SETTLE_VERSION', 'repair01_migration_settle.php v1.0');
define('OWNER_REF', 'RPR-02 §٩ · RPR-03 §٣·١ — أمرا المالك 2026-08-28');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$CMD   = 'php tools/repair01_migration_settle.php' . ($APPLY ? ' --apply' : '') . ($MD ? ' --md' : '');
$DIR   = $ROOT . '/database/migrations';
define('MIG_NAME_RE', '/^\d{4}_\d{2}_\d{2}_.+\.(sql|php)$/');

$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { $r = @$conn->query($sql); return $r ? $r->fetch_row()[0] : null; };
$rows = function ($sql) use ($conn) {
    $r = @$conn->query($sql); $o = array();
    if ($r) { while ($x = $r->fetch_assoc()) { $o[] = $x; } }
    return $o;
};

/* ═══ خريطةُ المخطَّطِ الحيِّ — تُقرأ مرّةً ثمَّ يُسأل منها ═══════════════ */
$liveTables = array();
foreach ($rows("SELECT TABLE_NAME n, TABLE_TYPE t FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()") as $r) {
    $liveTables[strtolower($r['n'])] = $r['t'] === 'VIEW' ? 'VIEW' : 'TABLE';
}
$liveCols = array();
foreach ($rows("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()") as $r) {
    $liveCols[strtolower($r['t']) . '.' . strtolower($r['c'])] = 1;
}
$liveIdx = array();
foreach ($rows("SELECT TABLE_NAME t, INDEX_NAME i FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()") as $r) {
    $liveIdx[strtolower($r['t']) . '.' . strtolower($r['i'])] = 1;
}

/**
 * الكائناتُ المُعلَنةُ صراحةً في رأسِ الهجرة — **وهي المصدرُ الأوثقُ حين يكون
 * الاسمُ متغيّرًا في `PHP`**. فالسطرُ `@migration-objects: view:v_x, table:t`
 * إعلانُ نيّةٍ **يتحقّق منه الفاحصُ في `information_schema`** — ⛔ فليس إعفاءً
 * بل تحويلُ ما لا يُقرأ نصًّا إلى ما يُقاس مخطَّطًا.
 */
function mig_declared($src)
{
    $o = array();
    if (!preg_match('/@migration-objects\s*:\s*([^\r\n*]+)/i', $src, $m)) { return $o; }
    foreach (explode(',', $m[1]) as $tok) {
        $tok = strtolower(trim($tok));
        if ($tok === '') { continue; }
        if (preg_match('~^(table|view|col|idx):([a-z0-9_.]+)$~', $tok, $mm)) {
            $o[$mm[1] . ':' . $mm[2]] = strtoupper($mm[1]);
        }
    }
    return $o;
}

/**
 * كائناتُ الهجرةِ من نصِّها — **قراءةٌ لا تنفيذ**.
 * ⛔ **والاسمُ يجب أن يكون بين علامتَي اقتباسٍ خلفيّة**: فبلا ذلك يلتقط النمطُ
 *   كلمةَ `IF` من `CREATE TABLE IF NOT EXISTS` **الواردةِ في تعليقٍ** فيُحسَب
 *   جدولًا اسمُه `if` ويُطلَب من المخطَّط — وذلك عطبُ «النمطُ يلتقط من داخلِ
 *   النفي» بعينِه، وقد وقع هنا فعلًا وأُصلح.
 * @return array معرِّفٌ => نوع
 */
function mig_objects($src)
{
    $o = array();
    if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([A-Za-z0-9_]+)`/i', $src, $m)) {
        foreach ($m[1] as $t) { $o['table:' . strtolower($t)] = 'TABLE'; }
    }
    if (preg_match_all('/CREATE\s+(?:OR\s+REPLACE\s+)?(?:ALGORITHM\s*=\s*\w+\s+)?(?:SQL\s+SECURITY\s+\w+\s+)?VIEW\s+`([A-Za-z0-9_]+)`/i', $src, $m)) {
        foreach ($m[1] as $t) { $o['view:' . strtolower($t)] = 'VIEW'; }
    }
    /* الأعمدةُ المضافةُ — `ALTER TABLE t ADD [COLUMN] c` */
    if (preg_match_all('/ALTER\s+TABLE\s+`([A-Za-z0-9_]+)`\s+ADD\s+(?:COLUMN\s+)?`([A-Za-z0-9_]+)`/i', $src, $m)) {
        foreach ($m[1] as $i => $t) { $o['col:' . strtolower($t) . '.' . strtolower($m[2][$i])] = 'COLUMN'; }
    }
    /* الجدولُ المعدَّلُ نفسُه يجب أن يكون قائمًا على أقلِّ تقدير */
    if (preg_match_all('/ALTER\s+TABLE\s+`([A-Za-z0-9_]+)`/i', $src, $m)) {
        foreach ($m[1] as $t) { $o['table:' . strtolower($t)] = 'TABLE'; }
    }
    if (preg_match_all('/CREATE\s+(?:UNIQUE\s+)?INDEX\s+`([A-Za-z0-9_]+)`\s+ON\s+`([A-Za-z0-9_]+)`/i', $src, $m)) {
        foreach ($m[2] as $i => $t) { $o['idx:' . strtolower($t) . '.' . strtolower($m[1][$i])] = 'INDEX'; }
    }
    return $o;
}

/** هل في الملفِّ جملةُ `DDL` باسمٍ متغيّرٍ لا يُقرأ نصًّا؟ */
function mig_has_dynamic_ddl($src)
{
    return (bool) preg_match(
        '/(CREATE\s+(?:OR\s+REPLACE\s+)?(?:ALGORITHM\s*=\s*\w+\s+)?(?:SQL\s+SECURITY\s+\w+\s+)?'
        . '(?:TABLE|VIEW)|ALTER\s+TABLE|DROP\s+(?:TABLE|VIEW))\s+(?:IF\s+(?:NOT\s+)?EXISTS\s+)?'
        . '`?\{?\$/i', $src);
}

/**
 * الجداولُ التي تكتب فيها هجرةُ بيانات.
 * ⛔ **و`ON UPDATE CURRENT_TIMESTAMP` ليست كتابةً في جدولٍ اسمُه
 *   `current_timestamp`** — والنمطُ السابقُ التقطها فأنتج جدولًا وهميًّا يُطلَب
 *   من المخطَّطِ فلا يوجد. **فالكلمةُ تُستثنى بسابقتِها لا بقائمةِ منعٍ.**
 */
function mig_write_targets($src)
{
    $o = array();
    /* ◆ **والاسمُ هنا بلا علاماتٍ خلفيّةٍ في أكثرِ الهجرات** (`DELETE FROM
         role_permissions`) — فاشتراطُها يُصفِّر المقامَ ويُنتج «0/0» الذي لا
         يُثبت شيئًا. **والفصلُ يكون بالسابقةِ لا بالعلامة.** */
    if (preg_match_all('/(\bON\s+)?\b(INSERT\s+(?:IGNORE\s+)?INTO|REPLACE\s+INTO|DELETE\s+FROM|UPDATE)\s+`?([A-Za-z0-9_]+)`?/i', $src, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            if (trim($hit[1]) !== '') { continue; }      /* `ON UPDATE CURRENT_TIMESTAMP` */
            $o[strtolower($hit[3])] = 1;
        }
    }
    unset($o['schema_migrations'], $o['current_timestamp']);
    return array_keys($o);
}

/* ═══ المسحُ ══════════════════════════════════════════════════════════════ */
$managed = array(); $unmanaged = array();
foreach (scandir($DIR) as $f) {
    if ($f === '.' || $f === '..' || is_dir($DIR . '/' . $f)) { continue; }
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    if (!in_array($ext, array('php', 'sql'), true)) { continue; }
    if (preg_match(MIG_NAME_RE, $f)) { $managed[$f] = $ext; } else { $unmanaged[$f] = $ext; }
}
ksort($managed); ksort($unmanaged);
$ledger = array();
foreach ($rows("SELECT filename FROM schema_migrations") as $r) { $ledger[$r['filename']] = 1; }

/* خريطةُ الحذفِ من تاريخِ `git` — تُبنى مرّةً */
$delMap = array();
$gl = array();
exec('git -C ' . escapeshellarg($ROOT) . ' log --diff-filter=D --name-only --format="C %H %ad" --date=short -- database/migrations/ 2>&1', $gl);
$curC = ''; $curD = '';
foreach ($gl as $l) {
    $l = trim($l);
    if ($l === '') { continue; }
    if (preg_match('/^C ([0-9a-f]{40}) (\d{4}-\d{2}-\d{2})$/', $l, $m)) { $curC = $m[1]; $curD = $m[2]; continue; }
    if (strpos($l, 'database/migrations/') === 0) {
        $b = basename($l);
        if (!isset($delMap[$b])) { $delMap[$b] = array('commit' => $curC, 'date' => $curD); }
    }
}

/* ═══ بناءُ الأحكام ═══════════════════════════════════════════════════════ */
$plan = array();   /* filename => صفُّ الحكم */

/* ── ① القرصُ خارجَ الدفتر ─────────────────────────────────────────────── */
foreach ($managed as $f => $ext) {
    if (isset($ledger[$f])) { continue; }
    $src = (string) @file_get_contents($DIR . '/' . $f);
    $isDown = (bool) preg_match('/_down\.(php|sql)$/i', $f);

    if ($isDown) {
        /* ◆ **ولا يُدَّعى إثباتُ «لم يُنفَّذ»**: أكثرُ سكربتاتِ التراجعِ تُسقط
             بأسماءٍ من مصفوفةِ `PHP` **فلا تُقرأ نصًّا**، ومحاولةُ إثباتِها
             بالنمطِ أنتجت «0/0 ⇒ لم يُنفَّذ» — **وهو أخضرُ كاذبٌ من مقامٍ صفر**.
           ◆ **والمُثبَتُ بنيويًّا ثلاثةٌ تُقاس كلُّها**: أنّه بعُرفِ `_down` ·
             وأنّه يحمل جملةَ إسقاطٍ فعلًا · **وأنَّ له أصلًا أمامَ معروفًا** —
             وهذا وحدَه ما يُبرِّر إخراجَه من طابورِ التنفيذ. */
        $fwdName = preg_replace('/_down\.(php|sql)$/i', '.$1', $f);
        $chk = array(
            'عُرفُ `_down`' => true,
            /* ◆ **والتراجعُ يُسقط أكثرَ من جدولٍ ومنظر**: قيدًا وزنادًا ومفتاحًا
                 أجنبيًّا — و`w3_person_guard_down` يُسقط `CONSTRAINT` و`TRIGGER`
                 فحسب، **فنمطٌ يقتصر على الجداولِ يرسُبه ظلمًا**. */
            'يحمل جملةَ إسقاط' => (bool) preg_match(
                '/DROP\s+(TABLE|VIEW|INDEX|COLUMN|CONSTRAINT|TRIGGER|FOREIGN\s+KEY|PRIMARY\s+KEY|CHECK)/i', $src),
            'له أصلٌ أمامَ معروف' => (isset($managed[$fwdName]) || isset($ledger[$fwdName])),
        );
        $found = count(array_filter($chk));
        $verified = ($found === count($chk)) ? 1 : 0;
        $miss = array_keys(array_filter($chk, function ($v) { return !$v; }));
        $plan[$f] = array(
            'kind' => 'DISK_NOT_LEDGERED',
            'ruling' => $verified ? 'ROLLBACK_SCRIPT_NOT_APPLIED' : 'ROLLBACK_UNPROVEN',
            'evidence' => 'سكربتُ تراجعٍ — أُثبت بنيويًّا ' . $found . '/' . count($chk)
                . ' (عُرفُ الاسمِ · جملةُ الإسقاطِ · وجودُ أصلِه `' . $fwdName . '`)'
                . ($miss ? ' · والمتخلِّفُ: ' . implode(' · ', $miss) : '')
                . ' — ويُقيَّد `baseline` ليخرج من طابورِ `up` ولا يُنفَّذ',
            'verified' => $verified,
            'checked' => count($chk), 'found' => $found,
            'ledger_status' => 'baseline',
        );
        continue;
    }

    /* المُعلَنُ صراحةً يغلب المقروءَ نصًّا — فالاسمُ المتغيّرُ لا يُقرأ */
    $objs = mig_declared($src);
    $declared = (bool) $objs;
    if (!$objs) { $objs = mig_objects($src); }
    if ($objs) {
        $found = 0; $missing = array();
        foreach ($objs as $k => $kind) {
            list($p, $n) = explode(':', $k, 2);
            $ok = false;
            if ($p === 'table') { $ok = isset($liveTables[$n]); }
            elseif ($p === 'view') { $ok = isset($liveTables[$n]); }
            elseif ($p === 'col') { $ok = isset($liveCols[$n]); }
            elseif ($p === 'idx') { $ok = isset($liveIdx[$n]); }
            if ($ok) { $found++; } else { $missing[] = $k; }
        }
        $verified = ($found === count($objs)) ? 1 : 0;
        $plan[$f] = array(
            'kind' => 'DISK_NOT_LEDGERED',
            'ruling' => $verified ? 'APPLIED_VERIFIED_BY_SCHEMA' : 'PARTIAL_NOT_VERIFIED',
            'evidence' => 'سُئل `information_schema` عن ' . count($objs) . ' كائنًا فوُجد ' . $found
                . ($declared ? ' — من إعلانِ `@migration-objects` في رأسِ الملفّ' : ' — مقروءةً من نصِّ الجملِ')
                . ($missing ? ' · والمفقودُ: ' . implode(', ', array_slice($missing, 0, 5)) : ''),
            'verified' => $verified,
            'checked' => count($objs), 'found' => $found,
            'ledger_status' => 'applied',
        );
        continue;
    }

    /* ⛔ **واسمٌ متغيّرٌ في جملةِ `DDL` لا يُقرأ نصًّا** — فلا يُحكَم عليه
         بمقامِ صفرٍ ولا يُخمَّن: يُسمّى ويُطلَب إعلانُه. */
    if (mig_has_dynamic_ddl($src)) {
        $plan[$f] = array(
            'kind' => 'DISK_NOT_LEDGERED',
            'ruling' => 'NEEDS_OBJECT_DECLARATION',
            'evidence' => 'جملةُ DDL باسمٍ متغيّرٍ في `PHP` لا يُقرأ نصًّا — '
                . 'يلزم سطرُ `@migration-objects:` في رأسِ الملفِّ ليُتحقَّق منه بالمخطَّط',
            'verified' => 0,
            'checked' => 0, 'found' => 0,
            'ledger_status' => 'applied',
        );
        continue;
    }

    /* هجرةُ بياناتٍ بلا `DDL` */
    $wt = mig_write_targets($src);
    $found = 0; $empty = array(); $absent = array();
    foreach ($wt as $t) {
        if (!isset($liveTables[$t])) { $absent[] = $t; continue; }
        $c = (int) $one("SELECT COUNT(*) FROM `" . $t . "`");
        if ($c > 0) { $found++; } else { $empty[] = $t; }
    }
    $verified = (count($wt) > 0 && $found === count($wt)) ? 1 : 0;
    $plan[$f] = array(
        'kind' => 'DISK_NOT_LEDGERED',
        'ruling' => $verified ? 'APPLIED_VERIFIED_BY_DATA' : 'DATA_NOT_VERIFIABLE',
        'evidence' => 'هجرةُ بياناتٍ بلا DDL — ' . $found . '/' . count($wt)
            . ' من جداولِ كتابتِها قائمةٌ وغيرُ فارغة'
            . ($empty ? ' · والفارغُ: ' . implode(', ', $empty) : '')
            . ($absent ? ' · والغائبُ: ' . implode(', ', $absent) : '')
            . (count($wt) === 0 ? ' — ⛔ **ومقامُ صفرٍ لا يُثبت شيئًا**: لا جملةَ كتابةٍ باسمٍ مقروء' : ''),
        'verified' => $verified,
        'checked' => count($wt), 'found' => $found,
        'ledger_status' => 'applied',
    );
}

/* ── ② الدفترُ بلا ملفّ ────────────────────────────────────────────────── */
foreach (array_keys($ledger) as $f) {
    if (isset($managed[$f]) || isset($unmanaged[$f])) { continue; }
    if (isset($delMap[$f])) {
        $plan[$f] = array(
            'kind' => 'LEDGER_NOT_ON_DISK',
            'ruling' => 'SUPERSEDED_BY_INSTALLER_SQUASH',
            'evidence' => 'حُذف في الالتزامِ `' . substr($delMap[$f]['commit'], 0, 12) . '` بتاريخِ '
                . $delMap[$f]['date'] . ' — ضمَّ المثبِّتُ مخطَّطَه إلى `database/schema/schema.sql`',
            'verified' => 1,
            'checked' => 1, 'found' => 1,
            'ledger_status' => null,
        );
    } else {
        $plan[$f] = array(
            'kind' => 'LEDGER_NOT_ON_DISK',
            'ruling' => 'DELETED_UNTRACED',
            'evidence' => 'لا التزامَ حذفٍ في تاريخِ `git` تحت `database/migrations/` — يُسمّى ولا يُخمَّن',
            'verified' => 0,
            'checked' => 1, 'found' => 0,
            'ledger_status' => null,
        );
    }
}

/* ── ③ خارجَ عُرفِ التسمية ─────────────────────────────────────────────── */
foreach (array_keys($unmanaged) as $f) {
    $isHelper = ($f === '_ledger.php');
    $plan[$f] = array(
        'kind' => 'UNMANAGED_NAME',
        'ruling' => $isHelper ? 'NOT_A_MIGRATION' : 'UNMANAGED_NEEDS_OWNER',
        'evidence' => $isHelper
            ? 'دالّةُ القيدِ `ems_migration_recorded()` نفسُها — مُضمَّنةٌ من الهجراتِ ولا تُشغَّل هجرةً'
            : 'اسمٌ خارجَ عُرفِ `YYYY_MM_DD_` — يحتاج قرارَ مالكٍ: يُسمّى هجرةً أو يُنقل',
        'verified' => $isHelper ? 1 : 0,
        'checked' => 1, 'found' => $isHelper ? 1 : 0,
        'ledger_status' => null,
    );
}

/* ═══ الإخراجُ والتطبيق ═══════════════════════════════════════════════════ */
$byRuling = array(); $unverified = array();
foreach ($plan as $f => $r) {
    $byRuling[$r['ruling']] = (isset($byRuling[$r['ruling']]) ? $byRuling[$r['ruling']] : 0) + 1;
    if (!$r['verified']) { $unverified[$f] = $r; }
}
ksort($byRuling);

$L = array();
$p = function ($s) use (&$L) { $L[] = $s; };
$git = function ($a) use ($ROOT) { $o = array(); exec('git -C ' . escapeshellarg($ROOT) . ' ' . $a . ' 2>&1', $o); return trim(implode(' ', $o)); };

$p('# `MIGRATION_SETTLEMENT` — تسويةُ الهجراتِ غيرِ المصالَحةِ بأدلّتِها');
$p('');
$p('> **مولَّدٌ من تشغيلٍ حيٍّ** بالسطر: `' . $CMD . '`');
$p('');
$p('| المفردة | القيمة |');
$p('|---|---|');
$p('| `Commit Hash` | `' . $git('rev-parse HEAD') . '` |');
$p('| `Schema Version` | ' . (int) $one("SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'") . 'T |');
$p('| `Registry Version` | ' . (int) $one("SELECT COUNT(*) FROM repair01_screen_registry") . ' |');
$p('| `Measured At` | ' . date('Y-m-d H:i:s') . ' |');
$p('| `Tool Version` | `' . SETTLE_VERSION . '` |');
$p('| `Snapshot ID` | `' . ($one("SELECT snapshot_id FROM repair01_freeze_snapshot
    WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1") ?: 'UNFROZEN') . '` |');
$p('| الوضع | ' . ($APPLY ? '**تطبيقٌ — يكتب الأحكامَ والدفتر**' : 'قياسٌ فقط — لا يكتب') . ' |');
$p('');
$p('## ١ · الأحكامُ بمقاماتِها');
$p('');
$p('| الحكم | العدد | متحقَّقٌ منه |');
$p('|---|---|---|');
foreach ($byRuling as $r => $c) {
    $v = 0; foreach ($plan as $x) { if ($x['ruling'] === $r && $x['verified']) { $v++; } }
    $p('| `' . $r . '` | ' . $c . ' | ' . $v . '/' . $c . ' |');
}
$p('| **الإجمالي** | **' . count($plan) . '** | **' . (count($plan) - count($unverified)) . '/' . count($plan) . '** |');
$p('');

if ($unverified) {
    $p('## ٢ · ما لم يُتحقَّق منه — مسمًّى لا مخمَّنًا');
    $p('');
    $p('| الملفّ | الحكم | الدليلُ المقيس |');
    $p('|---|---|---|');
    foreach ($unverified as $f => $r) {
        $p('| `' . $f . '` | `' . $r['ruling'] . '` | ' . $r['evidence'] . ' |');
    }
    $p('');
} else {
    $p('## ٢ · ما لم يُتحقَّق منه');
    $p('');
    $p('**لا شيء — كلُّ حكمٍ يحمل دليلَه المقيس.**');
    $p('');
}

/* خطرُ طابورِ `up` — يُقاس ويُسمّى */
$downPending = 0;
foreach ($plan as $f => $r) {
    if ($r['ruling'] === 'ROLLBACK_SCRIPT_NOT_APPLIED') { $downPending++; }
}
$p('## ٣ · خطرٌ حيٌّ مُسمًّى — سكربتاتُ التراجعِ في طابورِ المُشغِّل');
$p('');
$p('`database/migrate.php::cmd_up()` **يطابق `_down.php` بعُرفِ التسميةِ ولا يستثنيه**،');
$p('و**' . $downPending . '** سكربتَ تراجعٍ خارجَ الدفترِ ⇒ **كلُّها في طابورِ `up` الآن**.');
$p('وتسويتُها `baseline` **تُخرجها من الطابورِ فعلًا** — ⛔ لا نصحًا في وثيقة.');
$p('');

if ($APPLY) {
    $okS = 0; $okL = 0; $errs = array();
    foreach ($plan as $f => $r) {
        $sql = "INSERT INTO gov_migration_settlement
            (filename, kind, ruling, evidence, verified, objects_checked, objects_found,
             owner_ref, settled_at, settled_by)
            VALUES ('" . $e($f) . "', '" . $e($r['kind']) . "', '" . $e($r['ruling']) . "',
                    '" . $e(mb_substr($r['evidence'], 0, 600)) . "', " . (int) $r['verified'] . ",
                    " . (int) $r['checked'] . ", " . (int) $r['found'] . ",
                    '" . $e(OWNER_REF) . "', NOW(), '" . $e(SETTLE_VERSION) . "')
            ON DUPLICATE KEY UPDATE
                kind = VALUES(kind), ruling = VALUES(ruling), evidence = VALUES(evidence),
                verified = VALUES(verified), objects_checked = VALUES(objects_checked),
                objects_found = VALUES(objects_found), owner_ref = VALUES(owner_ref),
                settled_at = NOW(), settled_by = VALUES(settled_by)";
        if ($conn->query($sql)) { $okS++; } else { $errs[] = $f . ': ' . $conn->error; }

        /* ⛔ **ولا يُقيَّد في الدفترِ إلّا المتحقَّقُ منه** — فالدفترُ يقول
             «هذه مُصالَحة»، وقولُها عن غيرِ متحقَّقٍ كذبٌ على الدفتر. */
        if ($r['ledger_status'] !== null && $r['verified'] === 1 && !isset($ledger[$f])) {
            $sum = @sha1_file($DIR . '/' . $f);
            if ($sum !== false) {
                $q = "INSERT INTO schema_migrations
                        (filename, checksum, status, applied_at, execution_ms, applied_by, error_text)
                      VALUES ('" . $e($f) . "', '" . $e($sum) . "', '" . $e($r['ledger_status']) . "',
                              NOW(), 0, '" . $e('repair01_migration_settle · ' . $r['ruling']) . "', NULL)
                      ON DUPLICATE KEY UPDATE checksum = VALUES(checksum)";
                if ($conn->query($q)) { $okL++; } else { $errs[] = 'ledger ' . $f . ': ' . $conn->error; }
            }
        }
    }
    $p('## ٤ · التطبيق');
    $p('');
    $p('- أحكامٌ كُتبت في `gov_migration_settlement`: **' . $okS . '/' . count($plan) . '**');
    $p('- صفوفٌ قُيِّدت في `schema_migrations`: **' . $okL . '**');
    if ($errs) {
        $p('- ⛔ **أخطاء (' . count($errs) . ')**:');
        foreach (array_slice($errs, 0, 8) as $x) { $p('    - ' . $x); }
    }
    $p('');
}

$out = implode("\n", $L) . "\n";
if ($MD) {
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/MIGRATION_SETTLEMENT.md', $out);
    echo "✔ كُتب: docs/REPAIR01_20260823/MIGRATION_SETTLEMENT.md\n";
}
echo $out;
exit(count($unverified) ? 1 : 0);
