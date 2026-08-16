<?php
/**
 * se01_shift_entries_diff.php — جدولُ الفروقِ لِـshift_entries وحدَه (قراءةٌ فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * يقارن أعمدةَ المواصفة 70 الـ29 بالمرشَّحَين الحيَّين: timesheet · unit_entries
 * لكلِّ عمودٍ مقترح: أله نظيرٌ حيٌّ؟ وبأيِّ اسم؟ وبأيِّ نوع؟ وأمملوءٌ فعلًا؟
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$db = @mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
$db->set_charset('utf8mb4');
$SC = 'equipation_manage';

/* الأعمدةُ الـ29 من TSP-0014..TSP-0042 مع نظيرِها المرشَّح */
$SPEC = [
    ['id',                'BIGINT UNSIGNED AI PK',              'timesheet.id',                'unit_entries.id'],
    ['company_id',        'INT UNSIGNED NOT NULL',              'timesheet.company_id',        'unit_entries.company_id'],
    ['entity_layer',      "ENUM(operations,contracting,holding)", '—',                          '—'],
    ['work_date',         'DATE NOT NULL',                      'timesheet.date',              'unit_entries.entry_date'],
    ['shift_no',          'TINYINT UNSIGNED NOT NULL',          'timesheet.shift',             'unit_entries.shift'],
    ['slot_id',           'BIGINT UNSIGNED NOT NULL',           '—',                           'unit_entries.contract_line_id'],
    ['machine_code',      'VARCHAR(32) NOT NULL',               '—',                           'unit_entries.equipment_id'],
    ['operator_id',       'INT UNSIGNED NOT NULL',              'timesheet.employee_id',       'unit_entries.operator_employee_id'],
    ['supplier_id',       'INT UNSIGNED NOT NULL',              '—',                           'unit_entries.supplier_entity_id'],
    ['container_key',     'VARCHAR(32) NOT NULL',               '—',                           '—'],
    ['contract_id',       'INT UNSIGNED NOT NULL',              '—',                           'unit_entries.contract_id'],
    ['client_id',         'INT UNSIGNED NOT NULL',              '—',                           '—'],
    ['run_hours',         'DECIMAL(6,2) NOT NULL DEFAULT 0',    'timesheet.executed_hours',    '—'],
    ['standby_hours',     'DECIMAL(6,2) NOT NULL DEFAULT 0',    'timesheet.standby_hours',     '—'],
    ['breakdown_hours',   'DECIMAL(6,2) NOT NULL DEFAULT 0',    'timesheet.total_fault_hours', '—'],
    ['stop_reason_code',  'VARCHAR(32) NULL',                   'timesheet.fault_type',        '—'],
    ['liable_party',      "ENUM(client,company,supplier)",      'timesheet.fault_department',  '—'],
    ['meter_before',      'DECIMAL(12,2) NULL',                 '—',                           '—'],
    ['meter_after',       'DECIMAL(12,2) NULL',                 '—',                           '—'],
    ['fuel_received_qty', 'DECIMAL(10,2) NOT NULL DEFAULT 0',   '—',                           '—'],
    ['fuel_issued_qty',   'DECIMAL(10,2) NOT NULL DEFAULT 0',   '—',                           '—'],
    ['tons',              'DECIMAL(12,2) NULL',                 'timesheet.tons_count',        '—'],
    ['meters',            'DECIMAL(12,2) NULL',                 'timesheet.meters_count',      '—'],
    ['field_notes',       'TEXT NULL',                          'timesheet.work_notes',        'unit_entries.note'],
    ['entry_state',       "ENUM(draft,submitted,site_approved,corrected)", 'timesheet.status',  'unit_entries.state'],
    ['created_by',        'INT UNSIGNED NOT NULL',              'timesheet.user_id',           'unit_entries.entered_by'],
    ['created_by_role',   'SMALLINT UNSIGNED NOT NULL',         '—',                           '—'],
    ['created_at',        'DATETIME NOT NULL',                  '—',                           'unit_entries.created_at'],
    ['seed_tag',          'VARCHAR(32) NULL',                   '—',                           '—'],
];

/* المخططُ الحيّ */
$live = [];
$r = $db->query("SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA='$SC' AND TABLE_NAME IN ('timesheet','unit_entries')");
while ($x = $r->fetch_assoc()) { $live[$x['TABLE_NAME']][$x['COLUMN_NAME']] = $x['COLUMN_TYPE'] . ($x['IS_NULLABLE'] === 'NO' ? ' NOT NULL' : ''); }

/* أمملوءٌ فعلًا؟ */
$fill = function (string $t, string $c) use ($db, $live): string {
    if (!isset($live[$t][$c])) { return '—'; }
    $q = $db->query("SELECT COUNT(*) tot, SUM(`$c` IS NOT NULL AND `$c`<>'' AND `$c`<>0) f FROM `$t`");
    if (!$q) { return '?'; }
    $x = $q->fetch_row();
    $tot = (int) $x[0]; $f = (int) $x[1];
    return $tot ? sprintf('%d/%d = %d%%', $f, $tot, (int) round($f / $tot * 100)) : '0/0';
};

$hit = 0; $miss = 0; $partial = 0;
printf("%-19s | %-38s | %-30s | %-14s | %s\n", 'العمودُ المقترح', 'نظيرُه في timesheet (48,746 صفًّا)', 'نظيرُه في unit_entries (10,142)', 'الامتلاء', 'الحكم');
echo str_repeat('─', 150) . "\n";
$rowsOut = [];
foreach ($SPEC as [$col, $type, $ts, $ue]) {
    $tsCol = $ts !== '—' ? explode('.', $ts)[1] : null;
    $ueCol = $ue !== '—' ? explode('.', $ue)[1] : null;
    $tsOk = $tsCol && isset($live['timesheet'][$tsCol]);
    $ueOk = $ueCol && isset($live['unit_entries'][$ueCol]);
    $verdict = ($tsOk || $ueOk) ? (($tsOk && $ueOk) ? 'نظيرٌ في الاثنين' : 'نظيرٌ في واحد') : '✖ لا نظير';
    if ($verdict === '✖ لا نظير') { $miss++; } elseif ($verdict === 'نظيرٌ في الاثنين') { $hit++; } else { $partial++; }
    $f = $tsOk ? $fill('timesheet', $tsCol) : ($ueOk ? $fill('unit_entries', $ueCol) : '—');
    printf("%-19s | %-38s | %-30s | %-14s | %s\n", $col,
        $tsOk ? $tsCol . ' ' . ($live['timesheet'][$tsCol] ?? '') : '—',
        $ueOk ? $ueCol : '—', $f, $verdict);
    $rowsOut[] = ['col' => $col, 'spec_type' => $type, 'timesheet' => $tsOk ? $tsCol : null,
                  'unit_entries' => $ueOk ? $ueCol : null, 'fill' => $f, 'verdict' => $verdict];
}
echo str_repeat('─', 150) . "\n";
printf("الخلاصة: نظيرٌ في الاثنين=%d · نظيرٌ في واحد=%d · بلا نظير=%d  (من %d عمودًا)\n", $hit, $partial, $miss, count($SPEC));
printf("التغطيةُ الحيّة: %d من %d = %d%%\n", $hit + $partial, count($SPEC), (int) round(($hit + $partial) / count($SPEC) * 100));

/* المفتاحُ الفريدُ المقترح: أيمكن فرضُه على القائم؟ */
echo "\n── المفتاحُ الفريدُ المقترح uq_shift(company_id, work_date, shift_no, slot_id) ──\n";
$q = $db->query("SELECT COUNT(*) FROM (SELECT company_id, `date`, shift, employee_id, COUNT(*) c
                 FROM timesheet GROUP BY 1,2,3,4 HAVING c>1) d");
echo "  تكراراتٌ في timesheet على (company_id,date,shift,employee_id): " . ($q ? $q->fetch_row()[0] : '?') . "\n";
$q = $db->query("SELECT COUNT(*) FROM (SELECT company_id, entry_date, shift, equipment_id, COUNT(*) c
                 FROM unit_entries GROUP BY 1,2,3,4 HAVING c>1) d");
echo "  تكراراتٌ في unit_entries على (company_id,entry_date,shift,equipment_id): " . ($q ? $q->fetch_row()[0] : '?') . "\n";

file_put_contents(__DIR__ . '/../docs/reverse_audit_2026-08/evidence/se01_shift_entries_diff.json',
    json_encode(['spec_cols' => count($SPEC), 'both' => $hit, 'one' => $partial, 'none' => $miss, 'rows' => $rowsOut],
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\nكُتب: evidence/se01_shift_entries_diff.json\n";
