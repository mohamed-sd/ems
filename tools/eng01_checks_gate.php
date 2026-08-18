<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * ENG-01 ⑧ · بوابةُ الفحوصِ الثمانية CK-11 → CK-18
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tools/eng01_checks_gate.php  [--json] [--warn-only]
 * رمزُ الخروج: 0 نجحت كلُّها · 1 سقط واحدٌ فأكثر
 *
 * «◆ وترسب افتراضًا» (TSP-0324-ب) — فالبوابةُ تُرسِب ما لم يُمرَّر --warn-only
 * صراحةً، ولا علمَ بيئةٍ يخفّفها. ومن أراد تليينَها كتبها في أمرِه فظهرت في
 * سجلِّ خطِّ التسليم.
 *
 * وكلُّ فحصٍ باستعلامِه كما في TS-01 §٤-١٦ حرفًا — والفرقُ عن الوثيقةِ مذكورٌ
 * في حقلِ note حين يفرضه المخططُ الحيّ.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$db = $conn;

$argvv    = $argv ?? array();
$asJson   = in_array('--json', $argvv, true);
$warnOnly = in_array('--warn-only', $argvv, true);

/**
 * kind: 'zero_rows' = يُتوقَّع صفرُ صفٍّ · 'zero' = يُتوقَّع القيمةُ صفرًا
 *       'equals'    = يُتوقَّع قيمةً بعينِها
 */
$CHECKS = array(
    array(
        'id'    => 'CK-11',
        'title' => '◆ صفرُ نوعِ حدثٍ منشورٍ بلا مستهلك',
        'sql'   => "SELECT DISTINCT o.event_code FROM ems_event_outbox o
                     WHERE NOT EXISTS (SELECT 1 FROM ems_event_subscriptions s
                                        WHERE s.event_code=o.event_code AND s.is_active=1)",
        'kind'  => 'zero_rows',
        'expect'=> 'صفرُ صفٍّ',
        'note'  => 'ems_event_outbox و ems_event_subscriptions مَنظرانِ بأسماءِ TS-01 '
                 . 'فوقَ ems_business_events و event_consumers — الاستعلامُ بنصِّه',
    ),
    array(
        'id'    => 'CK-12',
        'title' => '◆ صفرُ تسليمٍ عالقٍ فوقَ مهلته',
        'sql'   => "SELECT COUNT(*) FROM ems_event_deliveries
                     WHERE state IN ('claimed','processing') AND claimed_at < NOW() - INTERVAL 1 HOUR",
        'kind'  => 'zero',
        'expect'=> 'صفر',
        'note'  => '',
    ),
    array(
        'id'    => 'CK-13',
        'title' => '◆ صفرُ صفٍّ في صندوقِ الموتى أقدمُ من ثلاثةِ أيام',
        'sql'   => "SELECT COUNT(*) FROM ems_event_deliveries
                     WHERE state='dlq' AND processed_at < NOW() - INTERVAL 3 DAY",
        'kind'  => 'zero',
        'expect'=> 'صفر',
        'note'  => '',
    ),
    array(
        'id'    => 'CK-14',
        'title' => '◆ صفرُ مهمةٍ مقفولةٍ فوقَ مهلةِ التحرير',
        'sql'   => "SELECT COUNT(*) FROM ems_job_queue
                     WHERE state='claimed' AND lock_expires_at < NOW()",
        'kind'  => 'zero',
        'expect'=> 'صفر',
        'note'  => '',
    ),
    array(
        'id'    => 'CK-15',
        'title' => '◆ صفرُ جدولةٍ متأخرةٍ فوقَ مهلةِ الإنذار',
        'sql'   => "SELECT job_type FROM ems_job_schedule
                     WHERE is_active=1
                       AND (last_success_at IS NULL
                            OR last_success_at < NOW() - INTERVAL alert_after_seconds SECOND)",
        'kind'  => 'zero_rows',
        'expect'=> 'صفرُ صفٍّ',
        'note'  => '',
    ),
    array(
        'id'    => 'CK-16',
        'title' => '◆ سجلُّ الثنائياتِ مفعَّل',
        'sql'   => "SHOW VARIABLES LIKE 'log_bin'",
        'kind'  => 'equals',
        'expect'=> 'ON',
        'col'   => 'Value',
        'note'  => '',
    ),
    array(
        'id'    => 'CK-17',
        'title' => '◆ صفرُ معدة-شهرٍ عملت بلا إهلاك',
        'sql'   => "SELECT COUNT(*) FROM fa_asset_hours
                     WHERE hours_from_shifts > 0 AND owner_type='company' AND depr_amount IS NULL",
        'kind'  => 'zero',
        'expect'=> 'صفر',
        'note'  => 'fa_asset_hours منظرٌ فوقَ asset_hour_reconciliations — الاستعلامُ بنصِّه',
    ),
    array(
        'id'    => 'CK-18',
        'title' => '◆ صفرُ إهلاكٍ لمعدةِ مورد',
        'sql'   => "SELECT COUNT(*) FROM fa_asset_hours
                     WHERE owner_type='supplier' AND depr_amount IS NOT NULL",
        'kind'  => 'zero',
        'expect'=> 'صفر',
        'note'  => 'ويحرسه chk_owner في القاعدةِ أيضًا — فالتطبيقُ يُتجاوز والقيدُ لا',
    ),
);

// ─────────────────────────── التشغيل ───────────────────────────
$results = array();
$failed  = 0;

foreach ($CHECKS as $c) {
    $row = array('id' => $c['id'], 'title' => $c['title'], 'expect' => $c['expect'],
                 'note' => $c['note'], 'pass' => false, 'actual' => null, 'detail' => array());

    $res = $db->query($c['sql']);
    if ($res === false) {
        $row['actual'] = 'خطأُ استعلام: ' . $db->error;
        $row['pass'] = false;
    } elseif ($c['kind'] === 'zero_rows') {
        $n = $res->num_rows;
        $row['actual'] = $n . ' صفًّا';
        $row['pass'] = ($n === 0);
        $k = 0;
        while ($k < 10 && ($x = $res->fetch_row())) { $row['detail'][] = (string) $x[0]; $k++; }
        if ($n > 10) { $row['detail'][] = '… و' . ($n - 10) . ' غيرُها'; }
    } elseif ($c['kind'] === 'zero') {
        $v = (int) ($res->fetch_row()[0] ?? -1);
        $row['actual'] = (string) $v;
        $row['pass'] = ($v === 0);
    } else { // equals
        $r = $res->fetch_assoc();
        $v = $r !== null ? (string) ($r[$c['col']] ?? '') : '';
        $row['actual'] = $v !== '' ? $v : '(لا قيمة)';
        $row['pass'] = ($v === $c['expect']);
    }

    if (!$row['pass']) { $failed++; }
    $results[] = $row;
}

// ─────────────────────────── الإخراج ───────────────────────────
if ($asJson) {
    echo json_encode(array(
        'gate' => 'ENG-01 · CK-11..CK-18',
        'failed' => $failed, 'total' => count($CHECKS),
        'warn_only' => $warnOnly, 'checks' => $results,
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
} else {
    echo "\n═══════════════════════════════════════════════════════════════════════\n";
    echo " بوابةُ المحرّكاتِ المشتركة — الفحوصُ الثمانية CK-11 → CK-18\n";
    echo " (ترسبُ افتراضًا — ولا تُلَيَّن إلا بـ--warn-only ظاهرًا في الأمر)\n";
    echo "═══════════════════════════════════════════════════════════════════════\n";
    foreach ($results as $r) {
        printf("\n%s  %-8s %s\n", $r['pass'] ? '✔' : '✘', $r['id'], $r['title']);
        printf("        المتوقَّع: %-12s  المقيس: %s\n", $r['expect'], $r['actual']);
        if ($r['note'] !== '') { printf("        ملحظ: %s\n", $r['note']); }
        foreach ($r['detail'] as $d) { printf("          · %s\n", $d); }
    }
    echo "\n═══════════════════════════════════════════════════════════════════════\n";
    printf(" النتيجة: %d/%d ناجحًا · %d ساقطًا\n", count($CHECKS) - $failed, count($CHECKS), $failed);
    echo "═══════════════════════════════════════════════════════════════════════\n\n";
}

if ($failed > 0 && $warnOnly) {
    fwrite(STDERR, "[gate] --warn-only: سقط $failed فحصًا ولم تُرسِب البوابة — والتليينُ مسجَّلٌ في الأمر.\n");
    exit(0);
}
exit($failed > 0 ? 1 : 0);
