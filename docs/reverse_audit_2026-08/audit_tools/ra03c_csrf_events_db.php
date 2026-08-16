<?php
/**
 * ra03c_csrf_events_db.php — ثلاثُ طبقاتٍ معماريةٍ في فاحصٍ واحد (قراءةٌ فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * ① CSRF: نماذجُ POST العاديةُ تحت مساراتِ الإنفاذِ بلا csrf_field (إعادةُ عدٍّ مستقلة)
 * ② الأحداث: أنواعُ الوقائعِ في القاعدة ⇆ معالجاتُ المروحة ⇆ اليتيمة · العطالة
 * ③ القاعدة: أعمدةُ مالٍ عائمة · جداولُ بلا PK · أعمدةُ *_id بلا FK · عزلُ الشركات
 * المخرَج: evidence/csrf_events_db.json
 */
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = 'C:/wamp64/www/ems';
$EV   = $ROOT . '/docs/reverse_audit_2026-08/evidence';
$db = mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
$db->set_charset('utf8mb4');
$out = [];

/* ══ ① CSRF — مسحُ النماذجِ العاديةِ تحت مساراتِ الإنفاذ ══════════════ */
$envRaw = file_get_contents($ROOT . '/.env');
preg_match('/^CSRF_ENFORCE_PATHS=(.*)$/m', $envRaw, $m);
$paths = array_filter(array_map('trim', explode(',', $m[1] ?? '')));
$noTok = []; $forms = 0;
foreach ($paths as $p) {
    $d = trim($p, '/');
    if (!is_dir($ROOT . '/' . $d)) { continue; }
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
        $src = file_get_contents($f);
        if (!preg_match_all('/<form\b[^>]*method\s*=\s*["\']?post/i', $src, $fm, PREG_OFFSET_CAPTURE)) { continue; }
        foreach ($fm[0] as $hit) {
            $forms++;
            /* نافذةُ النموذج: من فتحِه إلى </form> — يكفي وجودُ csrf_field أو csrf_token فيها */
            $end = stripos($src, '</form>', $hit[1]);
            $seg = substr($src, $hit[1], $end !== false ? $end - $hit[1] : 4000);
            if (!preg_match('/csrf_field\s*\(|name\s*=\s*["\']csrf_token/i', $seg)) {
                $noTok[] = str_replace($ROOT . '/', '', $f) . ':' . (substr_count(substr($src, 0, $hit[1]), "\n") + 1);
            }
        }
    }
}
$out['csrf'] = ['enforce_prefixes' => count($paths), 'post_forms' => $forms,
                'forms_without_token' => $noTok];

/* ══ ② الأحداث والمروحة والعطالة ══════════════════════════════════════ */
$types = [];
$r = $db->query('SELECT event_key, COUNT(*) c FROM ems_business_events GROUP BY event_key');
while ($x = $r->fetch_assoc()) { $types[$x["event_key"]] = (int) $x['c']; }
/* معالجاتُ المروحة: خريطةُ الأنواعِ داخل محرّك الأثر */
$fanoutSrc = '';
foreach (glob($ROOT . '/app/Services/**/*.php') as $f) { $fanoutSrc .= file_get_contents($f) . "\n"; }
foreach (glob($ROOT . '/app/Services/*.php') as $f) { $fanoutSrc .= file_get_contents($f) . "\n"; }
$handled = [];
foreach (array_keys($types) as $t) {
    if ($t !== '' && (strpos($fanoutSrc, "'" . $t . "'") !== false || strpos($fanoutSrc, '"' . $t . '"') !== false)) { $handled[$t] = true; }
}
$orphan = array_diff_key($types, $handled);
/* مواضعُ النشر في الإنتاج (خارج tests/tools) */
$pub = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = str_replace('\\', '/', (string) $f);
    if (substr($p, -4) !== '.php') { continue; }
    if (preg_match('#/(vendor|\.git|\.claude|tests|tools|docs|storage)/#', $p)) { continue; }
    $s = file_get_contents($p);
    $pub += preg_match_all('/publishFact\s*\(|EventPublisher::publish/', $s);
}
/* العطالة: أزواجٌ مكررةٌ في fin_event_links على المفتاح */
$dupIdem = null;
$r = @$db->query('SELECT COUNT(*) FROM (SELECT idempotency_key FROM fin_event_links GROUP BY idempotency_key HAVING COUNT(*) > 1) t');
if ($r) { $dupIdem = (int) $r->fetch_row()[0]; }
$out['events'] = [
    'distinct_types_in_db' => count($types),
    'types_with_handler_ref' => count($handled),
    'orphan_types' => array_keys($orphan),
    'orphan_rows_total' => array_sum($orphan),
    'publish_sites_production' => $pub,
    'fin_event_links_rows' => ($r2 = @$db->query('SELECT COUNT(*) FROM fin_event_links')) ? (int) $r2->fetch_row()[0] : null,
    'duplicate_idempotency_keys' => $dupIdem,
];

/* ══ ③ قواعدُ القاعدة ════════════════════════════════════════════════ */
$one = function (string $sql) use ($db) { $r = @$db->query($sql); return $r ? (int) $r->fetch_row()[0] : null; };
$floatMoney = [];
$r = $db->query("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE() AND DATA_TYPE IN ('float','double')
                   AND (COLUMN_NAME REGEXP 'amount|price|cost|salary|rate|total|balance|value|fee|wage')");
while ($x = $r->fetch_row()) { $floatMoney[] = $x[0] . '.' . $x[1] . ' (' . $x[2] . ')'; }
$noPk = [];
$r = $db->query("SELECT t.TABLE_NAME FROM information_schema.TABLES t
                 LEFT JOIN information_schema.TABLE_CONSTRAINTS c
                   ON c.TABLE_SCHEMA=t.TABLE_SCHEMA AND c.TABLE_NAME=t.TABLE_NAME AND c.CONSTRAINT_TYPE='PRIMARY KEY'
                 WHERE t.TABLE_SCHEMA=DATABASE() AND t.TABLE_TYPE='BASE TABLE' AND c.CONSTRAINT_NAME IS NULL");
while ($x = $r->fetch_row()) { $noPk[] = $x[0]; }
$idNoFk = $one("SELECT COUNT(*) FROM information_schema.COLUMNS c
                WHERE c.TABLE_SCHEMA=DATABASE() AND c.COLUMN_NAME LIKE '%\\_id' AND c.COLUMN_NAME NOT IN ('company_id')
                  AND NOT EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
                                  WHERE k.TABLE_SCHEMA=c.TABLE_SCHEMA AND k.TABLE_NAME=c.TABLE_NAME
                                    AND k.COLUMN_NAME=c.COLUMN_NAME AND k.REFERENCED_TABLE_NAME IS NOT NULL)");
$idTotal = $one("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME LIKE '%\\_id' AND COLUMN_NAME NOT IN ('company_id')");
$noTenant = [];
$r = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES t WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'
                 AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS c WHERE c.TABLE_SCHEMA=t.TABLE_SCHEMA AND c.TABLE_NAME=t.TABLE_NAME AND c.COLUMN_NAME='company_id')
                 ORDER BY TABLE_NAME");
while ($x = $r->fetch_row()) { $noTenant[] = $x[0]; }
$out['db_rules'] = [
    'float_money_columns' => $floatMoney,
    'tables_without_pk' => $noPk,
    'id_cols_without_fk' => $idNoFk, 'id_cols_total' => $idTotal,
    'tables_without_company_id' => count($noTenant),
    'no_tenant_list' => $noTenant,
];

file_put_contents($EV . '/csrf_events_db.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
printf("① CSRF: %d نموذج POST تحت %d مسار إنفاذ · بلا رمز: %d\n", $forms, count($paths), count($noTok));
foreach (array_slice($noTok, 0, 8) as $n) { echo "   ⚠ $n\n"; }
printf("② أحداث: %d نوعًا في القاعدة · بمعالجٍ: %d · يتيمة: %d نوعًا (%d صفًّا) · مواضع نشر: %d · عطالة مكررة: %s\n",
    $out['events']['distinct_types_in_db'], $out['events']['types_with_handler_ref'],
    count($out['events']['orphan_types']), $out['events']['orphan_rows_total'], $pub,
    var_export($dupIdem, true));
printf("③ قاعدة: مالٌ عائم: %d عمودًا · بلا PK: %d جدولًا · *_id بلا FK: %d من %d · بلا company_id: %d جدولًا\n",
    count($floatMoney), count($noPk), $idNoFk, $idTotal, count($noTenant));
