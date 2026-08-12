<?php
/**
 * UAT-0001 · بذرة ⑩ — المولِّدُ العام: يملأ كلَّ جدولِ أعمالٍ دون العشرين صفًّا.
 *
 * لا يخترعُ بنيةً ولا يتجاوز حارسًا: يقرأ `information_schema` فيعرف الأنواعَ
 * والإلزامَ وقيمَ الـENUM والأعمدةَ المولَّدة، ويقرأ المفاتيحَ الأجنبيةَ فيربط
 * كلَّ صفٍّ بأبٍ **موجودٍ فعلًا** — فالبياناتُ تبقى مترابطةً لا معلَّقة.
 *
 * وما ترفضه قيودُ القاعدة (CHECK/UNIQUE) يُتخطى ويُعلَن في التقرير — فالجدولُ
 * الذي يأبى الامتلاءَ آليًّا يُقيَّد ملاحظةً لا يُلفَّق له صفٌّ كاذب.
 */
require __DIR__ . '/_lib.php';
set_time_limit(0);
mysqli_report(MYSQLI_REPORT_ERROR);          // الاستثناءاتُ تُلتقط لا تُسقط التنفيذ

$db     = uat_db();
$CO     = UAT_COMPANY;
$actor  = uat_actor();
$TARGET = 20;

/** جداولُ لا تُملأ: الأدوارُ والصفحاتُ والصلاحياتُ + النظاميةُ + المهجورة. */
$SKIP = array_merge(UAT_FORBIDDEN, [
    'super_admins', 'super_admin_password_resets', 'company_user_password_resets', 'ems_sessions',
    'ems_event_dead_letter', 'capacity_shadow_diffs', 'worker_backup', 'api_tokens', 'tenants',
    'drivercontracts', 'drivercontractequipments', 'driver_contract_notes', 'workspace_prefs',
    'schema_migrations', 'admin_companies', 'admin_subscription_plans', 'admin_subscription_requests',
    'admin_audit_log', 'perm_shadow_diffs', 'founding_mode', 'ems_sequences', 'governance_flags',
    'guard_policies', 'guard_override_policies', 'policy_rules', 'financing_models', 'ems_event_consumers',
    'uat_runs', 'uat_evidence', 'fin_financial_periods', 'stop_reason_codes', 'units_of_measure',
    'fin_currencies', 'fin_fx_rates', 'shift_period_defs', 'org_assignment_types', 'sod_conflicts',
    /* ══ دفاترُ «تمتلئ من الدورةِ التشغيلية» — بذرُها يُفسد الشاهدَ نفسَه ═══════
       `docs/uat/UAT_DATA_POPULATION_MANIFEST_ar.md:28-30` يُدرج هذه الثلاثةَ تحت
       «**هذه لا تُبذر**: تمتلئ حين تمرّ الدورةُ من الشاشات، **وامتلاؤها هو
       الشاهد**». وكان المِلءُ العامُّ يُدرجها فأنشأ **20 مراجعةَ سعرٍ ملفَّقةً**
       على عقودِ الشركة 4 الحقيقية: `period_key` يحمل **جملةً عربيةً** مبتورةً
       («وفق المعتمد في م…») بدل مفتاحِ فترة، و`created_by = approved_by = 4`
       أي **مراجعةٌ أجاز صاحبُها نفسَه** — وهو ما يمنعه
       `PriceAdjustmentService.php:455` صراحةً (ولا قيدَ في القاعدةِ يمنعه،
       فالباذرُ يتجاوز الخدمةَ فيتجاوز الحارس). ومنها مراجعةٌ على البند #1 من
       العقد 1 (سعرُه الأساسيُّ 10.00) تُعلن 250.50.
       ⇒ تُستثنى، وإلا أعاد كلُّ تشغيلٍ التلويثَ. */
    'contract_price_terms', 'contract_price_revisions', 'contract_price_index_readings',
]);

// ── معاجمُ توليدٍ عربيةٌ من واقع القطاع ──────────────────────────────────────
$PERSONS = ['عثمان الطاهر إدريس', 'الصادق مكي عبد الرحمن', 'بابكر النور الأمين', 'الطيب موسى دفع الله',
    'جمال الدين عوض السيد', 'النذير خميس آدم', 'عمار الفاتح بشير', 'صلاح الدين قسم السيد',
    'هيثم عبد الرازق يوسف', 'منتصر الخير عبد الله', 'ياسر الأمين محمد خير', 'وليد الهادي عثمان',
    'أنس الفاتح إبراهيم', 'مأمون عادل الطيب', 'سيف الدين حامد بابكر', 'الصديق عبد الماجد النور',
    'مصعب الطاهر النعيم', 'خضر عمر الجاك', 'الفاتح زين العابدين', 'معتصم بابكر الريح'];
$FIRMS = ['شركة البركة للتعدين', 'شركة الرشيد للمقاولات', 'شركة وادي النيل للخدمات', 'شركة السافانا للتعدين',
    'شركة النسور للمعدات', 'شركة الشمالية للحفريات', 'شركة البحر الأحمر للتوريدات', 'شركة دنقلا للنقل الثقيل'];
$NOTES = ['وفق المعتمد في محضر الإدارة', 'مطابقٌ للمستند المرفق', 'بانتظار اعتماد الإدارة المختصة',
    'مراجَعٌ من المالية ومطابق', 'أُثبت من واقع التشغيل الميداني', 'بناءً على طلب الموقع',
    'مُدرجٌ ضمن الدورة الشهرية', 'يخضع لمراجعة الربع القادم'];
$ITEMS = ['فلتر زيت', 'إطار خلفي', 'بطارية 200 أمبير', 'زيت هيدروليك', 'سير مروحة', 'طرمبة وقود',
    'أسنان جردل', 'شمعات إشعال', 'مبرد ماء', 'خرطوم هيدروليك'];

/** قيمُ ENUM من تعريف العمود. */
function enum_values($type)
{
    if (!preg_match("/^enum\((.*)\)$/is", $type, $m)) return [];
    preg_match_all("/'((?:[^']|'')*)'/", $m[1], $v);
    return array_map(fn($s) => str_replace("''", "'", $s), $v[1]);
}

/** آباءُ الجدول: عمود => [جدول, عمود]. */
function fks($table)
{
    static $c = [];
    if (isset($c[$table])) return $c[$table];
    $db = uat_db();
    $st = $db->prepare("SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                        FROM information_schema.KEY_COLUMN_USAGE
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL");
    $st->bind_param('s', $table);
    $st->execute();
    $r = $st->get_result();
    $out = [];
    while ($x = $r->fetch_assoc()) $out[$x['COLUMN_NAME']] = [$x['REFERENCED_TABLE_NAME'], $x['REFERENCED_COLUMN_NAME']];
    return $c[$table] = $out;
}

/** معرّفاتٌ حقيقيةٌ من جدولِ أبٍ (مفضِّلًا شركةَ التجربة). */
function parent_ids($table, $col)
{
    static $c = [];
    $k = "$table.$col";
    if (isset($c[$k])) return $c[$k];
    $db = uat_db();
    $hasCo = (bool) $db->query("SELECT COUNT(*) n FROM information_schema.COLUMNS
                                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table' AND COLUMN_NAME='company_id'")->fetch_assoc()['n'];
    $sql = "SELECT `$col` v FROM `$table` " . ($hasCo ? "WHERE company_id=" . UAT_COMPANY : '') . " ORDER BY `$col` DESC LIMIT 120";
    $ids = [];
    try { foreach ($db->query($sql) as $r) $ids[] = $r['v']; } catch (Throwable $e) {}
    if (!$ids && $hasCo) { try { foreach ($db->query("SELECT `$col` v FROM `$table` LIMIT 120") as $r) $ids[] = $r['v']; } catch (Throwable $e) {} }
    return $c[$k] = $ids;
}

/** الأعمدةُ الداخلةُ في فهارسَ فريدة. */
function unique_cols($table)
{
    $db = uat_db();
    $out = [];
    try {
        foreach ($db->query("SHOW INDEX FROM `$table` WHERE Non_unique=0") as $r) {
            if ($r['Key_name'] === 'PRIMARY') continue;
            $out[$r['Column_name']] = true;
        }
    } catch (Throwable $e) {}
    return $out;
}

$SPAN_FROM = strtotime('2024-01-01');
$SPAN_TO   = strtotime('2026-06-30');

function gen_value($col, $type, $dataType, $enum, $i, $uniq, $ctx)
{
    global $PERSONS, $FIRMS, $NOTES, $ITEMS, $SPAN_FROM, $SPAN_TO, $actor;
    $c = strtolower($col);
    $pick = fn(array $a) => $a[$i % count($a)];

    if ($enum) return $enum[$i % count($enum)];

    if (in_array($dataType, ['date', 'datetime', 'timestamp'], true)) {
        $ts = $SPAN_FROM + (int) (($SPAN_TO - $SPAN_FROM) * ((($i * 37) % 100) / 100));
        return $dataType === 'date' ? date('Y-m-d', $ts) : date('Y-m-d H:i:s', $ts);
    }
    if ($dataType === 'time') return sprintf('%02d:00:00', 6 + ($i % 12));
    if ($dataType === 'year') return 2020 + ($i % 6);

    if (in_array($dataType, ['int', 'bigint', 'smallint', 'mediumint', 'tinyint'], true)) {
        if (str_contains($c, 'company')) return UAT_COMPANY;
        if (str_contains($c, '_by') || str_contains($c, 'user_id')) return $actor;
        if (str_contains($c, 'is_') || str_contains($c, 'active') || str_contains($c, 'flag') || $type === 'tinyint(1)') return ($c === 'is_deleted') ? 0 : 1;
        if (str_contains($c, 'seq') || str_contains($c, 'order') || str_contains($c, 'no')) return $i + 1;
        if (str_contains($c, 'day')) return 5 + ($i % 25);
        if (str_contains($c, 'count') || str_contains($c, 'qty') || str_contains($c, 'units')) return 1 + ($i % 12);
        if (str_contains($c, 'version')) return 1;
        return 1 + ($i % 30);
    }
    if (in_array($dataType, ['decimal', 'float', 'double'], true)) {
        // السقفُ الحقيقيُّ للعمود من دقّته decimal(p,s) — فلا يُرفض الصفُّ بتجاوزٍ
        $max = null;
        if (preg_match('/\((\d+),(\d+)\)/', $type, $m)) $max = pow(10, (int) $m[1] - (int) $m[2]) - 1;
        $clamp = fn($v) => $max === null ? $v : min($v, $max);

        if (str_contains($c, 'lat')) return round(12 + ($i % 8) + 0.5, 4);
        if (str_contains($c, 'lng') || str_contains($c, 'lon')) return round(30 + ($i % 6) + 0.25, 4);
        if (preg_match('/percent|pct|ratio|share|probability|weight|score|factor|rate/', $c)) return $clamp(round(5 + ($i % 19) * 5, 2));
        if (str_contains($c, 'year')) return $clamp(round(1 + ($i % 9) + 0.5, 2));
        if (str_contains($c, 'hour')) return $clamp(round(4 + ($i % 8) + 0.5, 2));
        if (str_contains($c, 'qty') || str_contains($c, 'count')) return $clamp(round(1 + ($i % 15), 2));
        if (str_contains($c, 'debit') || str_contains($c, 'credit')) return 0;
        return $clamp(round(250 + ($i * 137) % 9000 + 0.5, 2));
    }

    // نصوص
    $len = 190;
    if (preg_match('/\((\d+)\)/', $type, $m)) $len = (int) $m[1];
    if ($dataType === 'json' || str_contains($c, '_json') || str_ends_with($c, 'json')) {
        return json_encode(['source' => UAT_TAG, 'seq' => $i + 1, 'note' => 'مولَّدٌ آليًّا'], JSON_UNESCAPED_UNICODE);
    }
    $seq = $uniq ? '-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT) : '';

    if (str_contains($c, 'email')) return 'uat' . ($i + 1) . '@equipation.sd';
    if (str_contains($c, 'phone') || str_contains($c, 'whatsapp') || str_contains($c, 'mobile')) return '2499' . str_pad((string) (10000000 + $i * 7), 8, '0');
    if (str_contains($c, 'currency')) return 'USD';
    if (str_contains($c, 'uuid')) return sprintf('%08x-%04x-4%03x-%04x-%012x', $i + 1, $i, $i, 0x8000 + $i, $i * 991);
    if (str_contains($c, 'fingerprint') || str_contains($c, 'checksum') || str_contains($c, 'hash')) return sha1($ctx . $i);
    if (str_contains($c, 'period') && $len <= 10) return date('Y-m', $SPAN_FROM + $i * 2592000);
    if (str_contains($c, 'code') || str_contains($c, 'ref') || str_contains($c, '_no') || $c === 'no' || str_contains($c, 'serial') || str_contains($c, 'number')) {
        return mb_substr(strtoupper(substr(preg_replace('/[^a-z_]/', '', $ctx) ?: 'UAT', 0, 4)) . '-' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT), 0, $len);
    }
    if (str_contains($c, 'name') || str_contains($c, 'title') || str_contains($c, 'label')) {
        $v = (str_contains($c, 'company') || str_contains($c, 'firm') || str_contains($c, 'supplier') || str_contains($c, 'client'))
            ? $pick($FIRMS) : $pick($PERSONS);
        return mb_substr($v . $seq, 0, $len);
    }
    if (str_contains($c, 'item') || str_contains($c, 'part')) return mb_substr($pick($ITEMS) . $seq, 0, $len);
    if (str_contains($c, 'path') || str_contains($c, 'file') || str_contains($c, 'photo') || str_contains($c, 'doc')) {
        return mb_substr('storage/uat/' . preg_replace('/[^a-z_]/', '', $ctx) . '-' . ($i + 1) . '.pdf', 0, $len);
    }
    if (str_contains($c, 'json')) return '{"source":"UAT-2026"}';
    return mb_substr($pick($NOTES) . ' · ' . UAT_TAG . $seq, 0, $len);
}

// ── التنفيذ ──────────────────────────────────────────────────────────────────
$tables = [];
foreach ($db->query("SELECT TABLE_NAME t FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME") as $r) {
    if (in_array($r['t'], $SKIP, true)) continue;
    $n = (int) $db->query("SELECT COUNT(*) c FROM `{$r['t']}`")->fetch_assoc()['c'];
    if ($n < $TARGET) $tables[$r['t']] = $n;
}
echo "   جداولُ الهدف: " . count($tables) . "\n";

$filled = 0; $rows = 0; $blocked = [];
foreach ($tables as $t => $have) {
    $cols = [];
    foreach ($db->query("SELECT COLUMN_NAME, COLUMN_TYPE, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
                         FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t'
                         ORDER BY ORDINAL_POSITION") as $c) {
        if (stripos($c['EXTRA'], 'auto_increment') !== false) continue;
        if (stripos($c['EXTRA'], 'GENERATED') !== false && stripos($c['EXTRA'], 'DEFAULT_GENERATED') === false) continue;
        $cols[] = $c;
    }
    if (!$cols) continue;

    $fk   = fks($t);
    $uniq = unique_cols($t);
    $need = $TARGET - $have;
    $ok = 0; $lastErr = '';

    for ($i = 0; $i < $need * 3 && $ok < $need; $i++) {
        $row = [];
        $skipTable = false;
        foreach ($cols as $c) {
            $name = $c['COLUMN_NAME'];
            $nullable = $c['IS_NULLABLE'] === 'YES';
            if ($name === 'company_id') { $row[$name] = $CO; continue; }
            if (isset($fk[$name])) {
                [$pt, $pc] = $fk[$name];
                $ids = parent_ids($pt, $pc);
                if (!$ids) { if ($nullable) { $row[$name] = null; continue; } $skipTable = true; break; }
                $row[$name] = $ids[($i * 7 + strlen($name)) % count($ids)];
                continue;
            }
            if ($nullable && $c['COLUMN_DEFAULT'] !== null && !isset($uniq[$name])) { continue; }   // يُترك للافتراض
            if ($nullable && ($i % 5 === 4) && !isset($uniq[$name])) { $row[$name] = null; continue; }
            $row[$name] = gen_value($name, $c['COLUMN_TYPE'], $c['DATA_TYPE'], enum_values($c['COLUMN_TYPE']), $have + $i, isset($uniq[$name]), $t);
        }
        if ($skipTable) { $blocked[$t] = 'أبٌ خالٍ'; break; }

        try {
            $keys = array_keys($row);
            $ph   = implode(',', array_fill(0, count($keys), '?'));
            $st   = $db->prepare("INSERT INTO `$t` (`" . implode('`,`', $keys) . "`) VALUES ($ph)");
            $st->bind_param(str_repeat('s', count($keys)), ...array_values($row));
            $st->execute();
            $ok++; $rows++;
        } catch (Throwable $e) {
            $lastErr = preg_replace('/\s+/', ' ', substr($e->getMessage(), 0, 90));
        }
    }
    if ($ok > 0) { $filled++; uat_log($t, 'مولَّد', $ok); }
    if ($ok < $need && !isset($blocked[$t])) $blocked[$t] = $lastErr ?: 'تعذّر';
}

echo "\n── البذرة ⑩ · المولِّد العام ──\n";
printf("   جداولُ امتلأت: %d · صفوفٌ مولَّدة: %d · جداولُ أبت: %d\n", $filled, $rows, count($blocked));
if ($blocked) {
    echo "\n   الجداولُ التي رفضت الملءَ الآليّ (تُعلَن ولا تُلفَّق):\n";
    foreach ($blocked as $t => $why) printf("      %-34s %s\n", $t, $why);
}
