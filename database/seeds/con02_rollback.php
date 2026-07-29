<?php
/**
 * database/seeds/con02_rollback.php
 * ═══════════════════════════════════════════════════════════════════════════
 * بابُ الرجوع — يحذف **كلَّ ما بذرته سكربتاتُ CON-02 ولا شيءَ سواه**،
 * ويعيد `EMS_ATTRIBUTION_MATRIX` إلى `off`.
 *
 * ── كيف يعرف ما يحذف؟ ────────────────────────────────────────────────────
 * من `con02_seed_manifest.json` وحدَه: كلُّ معرّفٍ بُذر مسجَّلٌ بجدوله، وكلُّ
 * قيمةٍ سابقةٍ لكل تحديث. فلا يُحذف صفٌّ لم يُبذر، ولا يُخمَّن بنمطٍ نصيّ.
 * والسكربتاتُ التالية (الواقعةُ الحية · الاحتساب · المستخلص) **تُلحق** بيانَها
 * بالجرد نفسِه، فيبقى بابُ الرجوع واحدًا مهما تقدّم العمل.
 *
 * ── ترتيبُ الحذف: الإسقاطُ قبل الجذر (ADR-15) ──────────────────────────────
 * `fin_financial_events` إسقاطُ `ems_business_events` بـ`root_event_id`،
 * فحذفُ الجذر أولًا يترك إسقاطًا يتيمًا (أو يرمي FK). والترتيبُ أدناه
 * أبناءٌ ← آباء · إسقاطٌ ← جذر، وهو **معلَنٌ لا ضمنيّ**.
 *
 * التشغيل:  php database/seeds/con02_rollback.php
 *           php database/seeds/con02_rollback.php --keep-flag   (بلا قلب العلم)
 *           php database/seeds/con02_rollback.php --force       (بلا جرد: كنسٌ بالوسم)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__, 2) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => 4, 'name' => 'con02 rollback');

$MANIFEST = __DIR__ . '/con02_seed_manifest.json';
$ENV_FILE = dirname(__DIR__, 2) . '/.env';
$SEED_TAG = 'CON02-SEED-20260728';

$keepFlag = in_array('--keep-flag', $argv, true);
$force    = in_array('--force', $argv, true);

$conn = $GLOBALS['conn'];
function say($m)  { fwrite(STDOUT, $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── " . $m . "\n"); }

// اتصالُ جذرٍ للكنس: بوابةُ المستأجر لا تحذف حذفًا صلبًا (soft-delete-only)،
// والبذرُ يجب أن **يزول** لا أن يُعلَّم محذوفًا وإلا بقيت عدّاداتُه.
$root = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                   ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($root->connect_error) { fwrite(STDERR, 'root: ' . $root->connect_error . "\n"); exit(1); }
$root->set_charset('utf8mb4');

$COUNTERS = array('contract_obligations', 'contract_penalty_rules',
                  'contract_penalty_assessments', 'claims', 'claim_lines',
                  'contract_commitments', 'unit_entries', 'unit_time_log',
                  'fin_financial_events', 'fin_event_links', 'fin_dues');
function counters($conn, array $tables) {
    $out = array();
    foreach ($tables as $t) {
        $r = $conn->query("SELECT COUNT(*) c FROM `{$t}`");
        $out[$t] = $r ? (int) $r->fetch_assoc()['c'] : -1;
    }
    return $out;
}

$before = counters($conn, $COUNTERS);

// ═══════════════════════════════════════════════════════════════════════════
// ترتيبُ الحذف — أبناءٌ ← آباء · إسقاطٌ ← جذر (ADR-15)
// ═══════════════════════════════════════════════════════════════════════════
$ORDER = array(
    'claim_lines',
    'claims',
    'fin_dues',
    'fin_event_links',
    'contract_penalty_assessments',
    'fin_financial_events',          // الإسقاط
    'ems_business_events',           // الجذر — بعد إسقاطه
    'unit_party_awards',
    'unit_approvals',
    'unit_time_log',
    'unit_entries',
    'timesheet_approvals',
    'timesheet',
    'contract_penalty_rules',
    'contract_commitments',
    'contract_obligations',
);

$manifest = null;
if (file_exists($MANIFEST)) {
    $manifest = json_decode(file_get_contents($MANIFEST), true);
}
if (!$manifest && !$force) {
    say("\n✘ لا بيانَ جرد: {$MANIFEST}");
    say('   فلا شيءَ مبذورٌ يُحذف — أو فُقد الجرد. للكنس بالوسم: --force');
    exit(1);
}

$deleted = array();

if ($manifest) {
    head('حذفُ ما سجّله بيانُ الجرد');
    $ins = isset($manifest['inserted']) ? $manifest['inserted'] : array();
    foreach ($ORDER as $table) {
        if (empty($ins[$table])) { continue; }
        $ids = array_values(array_unique(array_map('intval', $ins[$table])));
        $ids = array_filter($ids, function ($i) { return $i > 0; });
        if (empty($ids)) { continue; }
        $list = implode(',', $ids);
        if (!$root->query("DELETE FROM `{$table}` WHERE id IN ({$list})")) {
            say("   ✘ {$table}: " . $root->error);
            continue;
        }
        $deleted[$table] = $root->affected_rows;
        say(sprintf('   %-32s حُذف %s صفًّا', $table, $root->affected_rows));
    }

    // ── ردُّ التحديثات إلى قيمها السابقة ──
    $upd = isset($manifest['updated']) ? $manifest['updated'] : array();
    foreach ($upd as $table => $rows) {
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $sets = array();
            foreach ($r['old'] as $col => $val) {
                if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $col)) { continue; }
                $sets[] = "`{$col}` = " . ($val === null ? 'NULL' : "'" . $root->real_escape_string((string) $val) . "'");
            }
            if (empty($sets)) { continue; }
            if ($root->query("UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE id = {$id}")) {
                say(sprintf('   %-32s رُدّ #%s إلى قيمه السابقة', $table, $id));
            } else {
                say("   ✘ {$table} #{$id}: " . $root->error);
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// الكنسُ بالوسم — شبكةُ أمانٍ ثانية (وهي الوحيدةُ في وضع --force)
// ═══════════════════════════════════════════════════════════════════════════
head('الكنسُ بالوسم — شبكةُ أمان');
$tagSweeps = array(
    "DELETE FROM claim_lines WHERE claim_id IN (SELECT id FROM (SELECT id FROM claims WHERE notes LIKE '%{$SEED_TAG}%') x)",
    "DELETE FROM claims WHERE notes LIKE '%{$SEED_TAG}%'",
    "DELETE FROM contract_penalty_rules WHERE note LIKE '%{$SEED_TAG}%'",
    "DELETE FROM contract_commitments WHERE note LIKE '%{$SEED_TAG}%'",
    "DELETE FROM unit_time_log WHERE cause_note LIKE '%{$SEED_TAG}%'",
    "DELETE FROM unit_entries WHERE note LIKE '%{$SEED_TAG}%'",
    "DELETE FROM timesheet WHERE general_notes LIKE '%{$SEED_TAG}%'",
);
$sweptTotal = 0;
foreach ($tagSweeps as $sql) {
    if ($root->query($sql)) {
        $n = $root->affected_rows;
        $sweptTotal += $n;
        if ($n > 0) { say('   كُنس ' . $n . ' صفًّا ← ' . substr($sql, 12, 40)); }
    } else {
        say('   ⚠ ' . $root->error);
    }
}
if ($force && empty($manifest)) {
    // بلا جرد: `contract_obligations` كان صفرًا في خط الأساس، فكلُّ صفٍّ فيه بذر
    if ($root->query("DELETE FROM contract_obligations")) {
        say('   كُنس ' . $root->affected_rows . ' صفًّا ← contract_obligations (كان صفرًا في خط الأساس)');
        $sweptTotal += $root->affected_rows;
    }
}
if ($sweptTotal === 0) { say('   لا بقايا — الجردُ كنس كلَّ شيء.'); }

// ═══════════════════════════════════════════════════════════════════════════
// ردُّ العَلَم إلى off
// ═══════════════════════════════════════════════════════════════════════════
if (!$keepFlag) {
    head('ردُّ العَلَم');
    if (!is_file($ENV_FILE)) {
        say('   ⚠ لا ملفَّ .env');
    } else {
        $env = file_get_contents($ENV_FILE);
        $new = preg_replace('/^EMS_ATTRIBUTION_MATRIX\s*=.*$/m', 'EMS_ATTRIBUTION_MATRIX=off', $env);
        if ($new !== null && $new !== $env) {
            file_put_contents($ENV_FILE, $new);
            say('   EMS_ATTRIBUTION_MATRIX=off');
        } else {
            say('   EMS_ATTRIBUTION_MATRIX كان off سلفًا (أو لم يُعثر عليه)');
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// إثباتُ العودة إلى خط الأساس
// ═══════════════════════════════════════════════════════════════════════════
$after = counters($conn, $COUNTERS);
$baseline = ($manifest && isset($manifest['baseline'])) ? $manifest['baseline'] : null;

head('العدّادات');
say(sprintf('   %-32s %8s %8s %8s', 'الجدول', 'أساس', 'قبل', 'بعد'));
$drift = array();
foreach ($COUNTERS as $t) {
    $b = ($baseline && isset($baseline[$t])) ? $baseline[$t] : '—';
    say(sprintf('   %-32s %8s %8s %8s%s', $t, $b, $before[$t], $after[$t],
        ($baseline && $after[$t] !== $baseline[$t]) ? '   ✘' : ''));
    if ($baseline && $after[$t] !== $baseline[$t]) {
        $drift[] = $t . ': أساس=' . $baseline[$t] . ' بعد=' . $after[$t];
    }
}

if ($manifest) { @unlink($MANIFEST); say("\n   حُذف بيانُ الجرد."); }

say('');
if ($baseline === null) {
    say('⚠ بلا خط أساسٍ مسجَّل — لا يمكن إثباتُ العودة عدديًّا.');
    exit(0);
}
if (empty($drift)) {
    say('✔ كلُّ العدّادات عادت إلى خط الأساس بالضبط.');
    exit(0);
}
say('✘ انحرافٌ باقٍ:');
foreach ($drift as $d) { say('   ' . $d); }
exit(1);
