<?php
/**
 * tools/lib/raw_query_scan.php
 *   ماسحُ الاستعلامِ الخامِّ على جداولِ المستأجِر — **مصدرٌ واحدٌ لا مصدران**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ولمَ يُستخرَج**: السقّاطةُ تعدُّ، والسجلُّ يُبنى من المعدود، والبوابةُ تقارن
 *   بالسجل. فلو نسخ كلٌّ منها منطقَ المسحِ لتفرّقت الأرقامُ بصمتٍ عندَ أوّلِ
 *   تعديل — وهو ما وقع قبلًا في عدّادٍ وعارضٍ في ملفَّين. **فالماسحُ واحد.**
 *
 * ◆ **وجداولُ المستأجِرِ تُقرأ من المخطَّطِ** (`COLUMN_NAME = 'company_id'`) لا من
 *   قائمةٍ يدويةٍ تتعفّن.
 *
 * ◆ **والمستبعَدُ مُعلَنٌ لا مُخفى**: أدواتٌ وفاحصاتٌ وهجراتٌ وبذورٌ ليست أسطحَ
 *   تسريبٍ للمستخدم — وعدُّها يُضخِّم المقامَ فيصير التقدُّمُ غيرَ مقروء.
 * ═══════════════════════════════════════════════════════════════════════════
 */

/** جداولُ المستأجِرِ من المخطَّطِ الحيّ */
function ems_tenant_tables(mysqli $conn)
{
    $t = array();
    $q = $conn->query("SELECT TABLE_NAME FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'company_id'");
    while ($q && $x = $q->fetch_row()) { $t[strtolower($x[0])] = true; }
    return $t;
}

/** المستبعَداتُ — مُعلَنةٌ في موضعٍ واحد */
function ems_raw_scan_skips()
{
    return array('/storage/', '/vendor/', '/.git/', '/docs/', '/node_modules/',
                 '/examples/', '/tests/', '/tools/', '/database/migrations/', '/database/seeds/');
}

/**
 * @return array{hits: array<string,true>, scanned: int}
 *   المفاتيحُ مساراتٌ نسبيةٌ بشرطاتٍ أمامية، مرتَّبةٌ أبجديًّا.
 */
function ems_raw_query_hits($ROOT, mysqli $conn)
{
    $tenant = ems_tenant_tables($conn);
    $SKIP   = ems_raw_scan_skips();
    $rx = '/\b(?:FROM|JOIN|UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+`?([a-z_][a-z0-9_]*)`?/i';

    $scanned = 0; $hits = array();
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') { continue; }
        $p = str_replace(DIRECTORY_SEPARATOR, '/', $f->getPathname());
        $skip = false;
        foreach ($SKIP as $s) { if (strpos($p, $s) !== false) { $skip = true; break; } }
        if ($skip) { continue; }
        $scanned++;
        $src = @file_get_contents($p);
        if ($src === false) { continue; }
        if (!preg_match_all($rx, $src, $m)) { continue; }
        foreach ($m[1] as $t) {
            if (isset($tenant[strtolower($t)])) {
                $hits[str_replace($ROOT . '/', '', $p)] = true;
                break;
            }
        }
    }
    ksort($hits);
    return array('hits' => $hits, 'scanned' => $scanned, 'tenant_tables' => count($tenant));
}
